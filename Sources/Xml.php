<?php

/**
 * Maintains all XML-based interaction (mainly XMLhttp)
 * 
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');


function XMLhttpMain()
{
	loadTemplate('Xml');

	$sub_actions = array(
		'jumpto' => array(
			'function' => 'GetJumpTo',
		),
		'messageicons' => array(
			'function' => 'ListMessageIcons',
		),
		'previews' => array(
			'function' => 'RetrievePreview',
		),
	);
	if (!isset($_REQUEST['sa'], $sub_actions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	$sub_actions[$_REQUEST['sa']]['function']();
}

/**
 * Get a list of boards and categories used for the jumpto dropdown.
 */
function GetJumpTo()
{
	global $user_info, $context, $smcFunc, $sourcedir;

	// Find the boards/cateogories they can see.
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

function ListMessageIcons()
{
	global $context, $sourcedir, $board;

	require_once($sourcedir . '/Subs-Editor.php');
	$context['icons'] = getMessageIcons($board);

	$context['sub_template'] = 'message_icons';
}

function RetrievePreview()
{
	global $context;

	$subActions = array(
		'newspreview' => 'newspreview',
		'newsletterpreview' => 'newsletterpreview',
		'sig_preview' => 'sig_preview',
	);

	$context['sub_template'] = 'generic_xml';

	if (!isset($_POST['item']) || !in_array($_POST['item'], $subActions))
		return false;

	$subActions[$_POST['item']]();
}

function newspreview()
{
	global $context, $sourcedir, $smcFunc;

	require_once($sourcedir . '/Subs-Post.php');

	$errors = array();
	$news = !isset($_POST['news'])? '' : $smcFunc['htmlspecialchars']($_POST['news'], ENT_QUOTES);
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
function newsletterpreview()
{
	global $context, $sourcedir, $smcFunc, $txt;

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

function sig_preview()
{
	global $context, $sourcedir, $smcFunc, $txt, $user_info;

	require_once($sourcedir . '/Profile-Modify.php');
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
		$current_signature = parse_bbc($current_signature, true, 'sig' . $user);

		$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
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

?>