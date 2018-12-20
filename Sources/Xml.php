<?php

/**
 * Maintains all XML-based interaction (mainly XMLhttp)
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

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
	global $context, $sourcedir;

	// Find the boards/categories they can see.
	require_once($sourcedir . '/Subs-MessageIndex.php');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
	);
	$context['jump_to'] = getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach ($context['jump_to'] as $id_cat => $cat)
	{
		$context['jump_to'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	$context['sub_template'] = 'jump_to';
}

/**
 * Gets a list of available message icons and sends the info to the template for display
 */
function ListMessageIcons()
{
	global $context, $sourcedir, $board;

	require_once($sourcedir . '/Subs-Editor.php');
	$context['icons'] = getMessageIcons($board);

	$context['sub_template'] = 'message_icons';
}

/**
 * Handles retrieving previews of news items, newsletters, signatures and warnings.
 * Calls the appropriate function based on $_POST['item']
 *
 * @return void|bool Returns false if $_POST['item'] isn't set or isn't valid
 */
function RetrievePreview()
{
	global $context;

	$items = array(
		'newspreview',
		'newsletterpreview',
		'sig_preview',
		'warning_preview',
	);

	$context['sub_template'] = 'generic_xml';

	if (!isset($_POST['item']) || !in_array($_POST['item'], $items))
		return false;

	$_POST['item']();
}

/**
 * Handles previewing news items
 */
function newspreview()
{
	global $context, $sourcedir, $smcFunc;

	require_once($sourcedir . '/Subs-Post.php');

	$errors = array();
	$news = !isset($_POST['news']) ? '' : $smcFunc['htmlspecialchars']($_POST['news'], ENT_QUOTES);
	if (empty($news))
		$errors[] = array('value' => 'no_news');
	else
		preparsecode($news);

	$context['xml_data'] = array(
		'news' => array(
			'identifier' => 'parsedNews',
			'children' => array(
				array(
					'value' => parse_bbc($news),
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
	global $context, $sourcedir, $txt;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/ManageNews.php');
	loadLanguage('Errors');

	$context['post_error']['messages'] = array();
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

	if (empty($_POST['subject']))
		$context['post_error']['messages'][] = $txt['error_no_subject'];
	if (empty($_POST['message']))
		$context['post_error']['messages'][] = $txt['error_no_message'];

	prepareMailingForPreview();

	$context['sub_template'] = 'pm';
}

/**
 * Handles previewing signatures
 */
function sig_preview()
{
	global $context, $sourcedir, $smcFunc, $txt, $user_info;

	require_once($sourcedir . '/Profile-Modify.php');
	loadLanguage('Profile');
	loadLanguage('Errors');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
	$is_owner = $user == $user_info['id'];

	// @todo Temporary
	// Borrowed from loadAttachmentContext in Display.php
	$can_change = $is_owner ? allowedTo(array('profile_extra_any', 'profile_extra_own')) : allowedTo('profile_extra_any');

	$errors = array();
	if (!empty($user) && $can_change)
	{
		$request = $smcFunc['db_query']('', '
			SELECT signature
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $user,
			)
		);
		list($current_signature) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		censorText($current_signature);
		$current_signature = !empty($current_signature) ? parse_bbc($current_signature, true, 'sig' . $user) : $txt['no_signature_set'];

		$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : $txt['no_signature_preview'];
		$validation = profileValidateSignature($preview_signature);

		if ($validation !== true && $validation !== false)
			$errors[] = array('value' => $txt['profile_error_' . $validation], 'attributes' => array('type' => 'error'));

		censorText($preview_signature);
		$preview_signature = parse_bbc($preview_signature, true, 'sig' . $user);
	}
	elseif (!$can_change)
	{
		if ($is_owner)
			$errors[] = array('value' => $txt['cannot_profile_extra_own'], 'attributes' => array('type' => 'error'));
		else
			$errors[] = array('value' => $txt['cannot_profile_extra_any'], 'attributes' => array('type' => 'error'));
	}
	else
		$errors[] = array('value' => $txt['no_user_selected'], 'attributes' => array('type' => 'error'));

	$context['xml_data']['signatures'] = array(
		'identifier' => 'signature',
		'children' => array()
	);
	if (isset($current_signature))
		$context['xml_data']['signatures']['children'][] = array(
			'value' => $current_signature,
			'attributes' => array('type' => 'current'),
		);
	if (isset($preview_signature))
		$context['xml_data']['signatures']['children'][] = array(
			'value' => $preview_signature,
			'attributes' => array('type' => 'preview'),
		);
	if (!empty($errors))
		$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array_merge(
				array(
					array(
						'value' => $txt['profile_errors_occurred'],
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
	global $context, $sourcedir, $smcFunc, $txt, $user_info, $scripturl, $mbname;

	require_once($sourcedir . '/Subs-Post.php');
	loadLanguage('Errors');
	loadLanguage('ModerationCenter');

	$context['post_error']['messages'] = array();
	if (allowedTo('issue_warning'))
	{
		$warning_body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
		$context['preview_subject'] = !empty($_POST['title']) ? trim($smcFunc['htmlspecialchars']($_POST['title'])) : '';
		if (isset($_POST['issuing']))
		{
			if (empty($_POST['title']) || empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['warning_notify_blank'];
		}
		else
		{
			if (empty($_POST['title']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_body'];
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
				$user_info['name'],
				$mbname,
				$scripturl,
				$txt['regards_team'],
			);
			$warning_body = str_replace($find, $replace, $warning_body);
		}

		if (!empty($_POST['body']))
		{
			preparsecode($warning_body);
			$warning_body = parse_bbc($warning_body, true);
		}
		$context['preview_message'] = $warning_body;
	}
	else
		$context['post_error']['messages'][] = array('value' => $txt['cannot_issue_warning'], 'attributes' => array('type' => 'error'));

	$context['sub_template'] = 'warning';
}

?>