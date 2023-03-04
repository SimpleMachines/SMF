<?php

/**
 * Maintains all XML-based interaction (mainly XMLhttp)
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main handler and designator for AJAX stuff - jumpto, message icons and previews
 */
function XMLhttpMain()
{
	loadTemplate('Xml');

	$subActions = array(
		'jumpto' => 'GetJumpTo',
		'messageicons' => 'ListMessageIcons',
		'previews' => 'RetrievePreview',
	);

	// Easy adding of sub actions.
	call_integration_hook('integrate_XMLhttpMain_subActions', array(&$subActions));

	if (!isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Get a list of boards and categories used for the jumpto dropdown.
 */
function GetJumpTo()
{
	// Find the boards/categories they can see.
	require_once($sourcedir . '/Subs-MessageIndex.php');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset(Utils::$context['current_board']) ? Utils::$context['current_board'] : 0,
	);
	Utils::$context['jump_to'] = getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach (Utils::$context['jump_to'] as $id_cat => $cat)
	{
		Utils::$context['jump_to'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			Utils::$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	Utils::$context['sub_template'] = 'jump_to';
}

/**
 * Gets a list of available message icons and sends the info to the template for display
 */
function ListMessageIcons()
{
	require_once(Config::$sourcedir . '/Subs-Editor.php');
	Utils::$context['icons'] = getMessageIcons(Board::$info->id);

	Utils::$context['sub_template'] = 'message_icons';
}

/**
 * Handles retrieving previews of news items, newsletters, signatures and warnings.
 * Calls the appropriate function based on $_POST['item']
 *
 * @return void|bool Returns false if $_POST['item'] isn't set or isn't valid
 */
function RetrievePreview()
{
	$items = array(
		'newspreview',
		'newsletterpreview',
		'sig_preview',
		'warning_preview',
	);

	Utils::$context['sub_template'] = 'generic_xml';

	if (!isset($_POST['item']) || !in_array($_POST['item'], $items))
		return false;

	$_POST['item']();
}

/**
 * Handles previewing news items
 */
function newspreview()
{
	require_once(Config::$sourcedir . '/Msg.php');

	$errors = array();
	$news = !isset($_POST['news']) ? '' : Utils::htmlspecialchars($_POST['news'], ENT_QUOTES);
	if (empty($news))
		$errors[] = array('value' => 'no_news');
	else
		preparsecode($news);

	Utils::$context['xml_data'] = array(
		'news' => array(
			'identifier' => 'parsedNews',
			'children' => array(
				array(
					'value' => BBCodeParser::load()->parse($news),
				),
			),
		),
		'errors' => array(
			'identifier' => 'error',
			'children' => $errors
		),
	);
}

/**
 * Handles previewing newsletters
 */
function newsletterpreview()
{
	require_once(Config::$sourcedir . '/Msg.php');
	require_once(Config::$sourcedir . '/ManageNews.php');
	Lang::load('Errors');

	Utils::$context['post_error']['messages'] = array();
	Utils::$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	Utils::$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

	if (empty($_POST['subject']))
		Utils::$context['post_error']['messages'][] = Lang::$txt['error_no_subject'];
	if (empty($_POST['message']))
		Utils::$context['post_error']['messages'][] = Lang::$txt['error_no_message'];

	prepareMailingForPreview();

	Utils::$context['sub_template'] = 'pm';
}

/**
 * Handles previewing signatures
 */
function sig_preview()
{
	require_once(Config::$sourcedir . '/Profile-Modify.php');
	Lang::load('Profile');
	Lang::load('Errors');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
	$is_owner = $user == User::$me->id;

	// @todo Temporary
	// Borrowed from loadAttachmentContext in Display.php
	$can_change = $is_owner ? allowedTo(array('profile_extra_any', 'profile_extra_own')) : allowedTo('profile_extra_any');

	$errors = array();
	if (!empty($user) && $can_change)
	{
		$request = Db::$db->query('', '
			SELECT signature
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $user,
			)
		);
		list($current_signature) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Lang::censorText($current_signature);
		$allowedTags = BBCodeParser::getSigTags();
		$current_signature = !empty($current_signature) ? BBCodeParser::load()->parse($current_signature, true, 'sig' . $user, $allowedTags) : Lang::$txt['no_signature_set'];

		$preview_signature = !empty($_POST['signature']) ? Utils::htmlspecialchars($_POST['signature']) : Lang::$txt['no_signature_preview'];
		$validation = profileValidateSignature($preview_signature);

		if ($validation !== true && $validation !== false)
			$errors[] = array('value' => Lang::$txt['profile_error_' . $validation], 'attributes' => array('type' => 'error'));

		Lang::censorText($preview_signature);
		$preview_signature = BBCodeParser::load()->parse($preview_signature, true, 'sig' . $user, $allowedTags);
	}
	elseif (!$can_change)
	{
		if ($is_owner)
			$errors[] = array('value' => Lang::$txt['cannot_profile_extra_own'], 'attributes' => array('type' => 'error'));
		else
			$errors[] = array('value' => Lang::$txt['cannot_profile_extra_any'], 'attributes' => array('type' => 'error'));
	}
	else
		$errors[] = array('value' => Lang::$txt['no_user_selected'], 'attributes' => array('type' => 'error'));

	Utils::$context['xml_data']['signatures'] = array(
		'identifier' => 'signature',
		'children' => array()
	);
	if (isset($current_signature))
		Utils::$context['xml_data']['signatures']['children'][] = array(
			'value' => $current_signature,
			'attributes' => array('type' => 'current'),
		);
	if (isset($preview_signature))
		Utils::$context['xml_data']['signatures']['children'][] = array(
			'value' => $preview_signature,
			'attributes' => array('type' => 'preview'),
		);
	if (!empty($errors))
		Utils::$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array_merge(
				array(
					array(
						'value' => Lang::$txt['profile_errors_occurred'],
						'attributes' => array('type' => 'errors_occurred'),
					),
				),
				$errors
			),
		);
}

/**
 * Handles previewing user warnings
 */
function warning_preview()
{
	require_once(Config::$sourcedir . '/Msg.php');
	Lang::load('Errors');
	Lang::load('ModerationCenter');

	Utils::$context['post_error']['messages'] = array();
	if (allowedTo('issue_warning'))
	{
		$warning_body = !empty($_POST['body']) ? trim(Lang::censorText($_POST['body'])) : '';
		Utils::$context['preview_subject'] = !empty($_POST['title']) ? trim(Utils::htmlspecialchars($_POST['title'])) : '';
		if (isset($_POST['issuing']))
		{
			if (empty($_POST['title']) || empty($_POST['body']))
				Utils::$context['post_error']['messages'][] = Lang::$txt['warning_notify_blank'];
		}
		else
		{
			if (empty($_POST['title']))
				Utils::$context['post_error']['messages'][] = Lang::$txt['mc_warning_template_error_no_title'];
			if (empty($_POST['body']))
				Utils::$context['post_error']['messages'][] = Lang::$txt['mc_warning_template_error_no_body'];
			// Add in few replacements.
			/**
			 * These are the defaults:
			 * - {MEMBER} - Member Name. => current user for review
			 * - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
			 * - {FORUMNAME} - Forum Name.
			 * - {SCRIPTURL} - Web address of forum.
			 * - {REGARDS} - Standard email sign-off.
			 */
			$find = array(
				'{MEMBER}',
				'{FORUMNAME}',
				'{SCRIPTURL}',
				'{REGARDS}',
			);
			$replace = array(
				User::$me->name,
				Config::$mbname,
				Config::$scripturl,
				sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']),
			);
			$warning_body = str_replace($find, $replace, $warning_body);
		}

		if (!empty($_POST['body']))
		{
			preparsecode($warning_body);
			$warning_body = BBCodeParser::load()->parse($warning_body);
		}
		Utils::$context['preview_message'] = $warning_body;
	}
	else
		Utils::$context['post_error']['messages'][] = array('value' => Lang::$txt['cannot_issue_warning'], 'attributes' => array('type' => 'error'));

	Utils::$context['sub_template'] = 'warning';
}

?>