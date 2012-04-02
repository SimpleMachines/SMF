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
		'corefeatures' => array(
			'function' => 'EnableCoreFeatures',
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

function EnableCoreFeatures()
{
	global $context, $smcFunc, $sourcedir, $modSettings, $txt;

	$context['xml_data'] = array();
	// Just in case, maybe we don't need it
	loadLanguage('Errors');

	$errors = array();
	$returns = array();
	$tokens = array();
	if (allowedTo('admin_forum'))
	{
		$validation = validateSession();
		if (empty($validation))
		{
			require_once($sourcedir . '/ManageSettings.php');
			$result = ModifyCoreFeatures();

			if (empty($result))
			{
				$id = isset($_POST['feature_id']) ? $_POST['feature_id'] : '';

				if (!empty($id) && isset($context['features'][$id]))
				{
					$feature = $context['features'][$id];

					$returns[] = array(
						'value' => (!empty($_POST['feature_' . $id]) && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']),
					);

					createToken('admin-core', 'post');
					$tokens = array(
						array(
							'value' => $context['admin-core_token'],
							'attributes' => array('type' => 'token_var'),
						),
						array(
							'value' => $context['admin-core_token_var'],
							'attributes' => array('type' => 'token'),
						),
					);
				}
				else
				{
					$errors[] = array(
						'value' => $txt['feature_no_exists'],
					);
				}
			}
			else
			{
				$errors[] = array(
					'value' => $txt[$result],
				);
			}
		}
		else
		{
			$errors[] = array(
				'value' => $txt[$validation],
			);
		}
	}
	else
	{
		$errors[] = array(
			'value' => $txt['cannot_admin_forum']
		);
	}

	$context['sub_template'] = 'generic_xml';
	$context['xml_data'] = array (
		'corefeatures' => array (
			'identifier' => 'corefeature',
			'children' => $returns,
		),
		'tokens' => array (
			'identifier' => 'token',
			'children' => $tokens,
		),
		'errors' => array (
			'identifier' => 'error',
			'children' => $errors,
		),
	);
}
?>