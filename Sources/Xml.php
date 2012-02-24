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

	$errors = array();
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

	if (empty($_POST['subject']))
		$errors[] = array('value' => $txt['error_no_subject'], 'attributes' => array('type' => 'subject_preview'));
	if (empty($_POST['message']))
		$errors[] = array('value' => $txt['error_no_message'], 'attributes' => array('type' => 'message_preview'));

	prepareMailingForPreview();

	$context['xml_data'] = array(
		'message' => array(
			'identifier' => 'preview_message',
			'children' => array(
				array(
					'value' => $context['preview_message'],
				),
			),
		),
		'subject' => array(
			'identifier' => 'preview_subject',
			'children' => array(
				array(
					'value' => $context['preview_subject'],
				),
			),
		),
		'errors' => array(
			'identifier' => 'error',
			'children' => $errors
		),
	);
}


?>