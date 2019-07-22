<?php

/**
 * This file takes care of all administration of smileys.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This is the dispatcher of smileys administration.
 */
function ManageSmileys()
{
	global $context, $txt, $modSettings;

	isAllowedTo('manage_smileys');

	loadLanguage('ManageSmileys');
	loadTemplate('ManageSmileys');

	$subActions = array(
		'addsmiley' => 'AddSmiley',
		'editicon' => 'EditMessageIcons',
		'editicons' => 'EditMessageIcons',
		'editsets' => 'EditSmileySets',
		'editsmileys' => 'EditSmileys',
		'import' => 'EditSmileySets',
		'modifyset' => 'EditSmileySets',
		'modifysmiley' => 'EditSmileys',
		'setorder' => 'EditSmileyOrder',
		'settings' => 'EditSmileySettings',
		'install' => 'InstallSmileySet'
	);

	// If customized smileys is disabled don't show the setting page
	if (empty($modSettings['smiley_enable']))
	{
		unset($subActions['addsmiley']);
		unset($subActions['editsmileys']);
		unset($subActions['setorder']);
		unset($subActions['modifysmiley']);
	}
	if (empty($modSettings['messageIcons_enable']))
	{
		unset($subActions['editicon']);
		unset($subActions['editicons']);
	}

	// Default the sub-action to 'edit smiley settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'editsets';

	$context['page_title'] = $txt['smileys_manage'];
	$context['sub_action'] = $_REQUEST['sa'];
	$context['sub_template'] = $context['sub_action'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['smileys_manage'],
		'help' => 'smileys',
		'description' => $txt['smiley_settings_explain'],
		'tabs' => array(
			'editsets' => array(
				'description' => $txt['smiley_editsets_explain'],
			),
			'addsmiley' => array(
				'description' => $txt['smiley_addsmiley_explain'],
			),
			'editsmileys' => array(
				'description' => $txt['smiley_editsmileys_explain'],
			),
			'setorder' => array(
				'description' => $txt['smiley_setorder_explain'],
			),
			'editicons' => array(
				'description' => $txt['icons_edit_icons_explain'],
			),
			'settings' => array(
				'description' => $txt['smiley_settings_explain'],
			),
		),
	);

	// Some settings may not be enabled, disallow these from the tabs as appropriate.
	if (empty($modSettings['messageIcons_enable']))
		$context[$context['admin_menu_name']]['tab_data']['tabs']['editicons']['disabled'] = true;
	if (empty($modSettings['smiley_enable']))
	{
		$context[$context['admin_menu_name']]['tab_data']['tabs']['addsmiley']['disabled'] = true;
		$context[$context['admin_menu_name']]['tab_data']['tabs']['editsmileys']['disabled'] = true;
		$context[$context['admin_menu_name']]['tab_data']['tabs']['setorder']['disabled'] = true;
	}

	call_integration_hook('integrate_manage_smileys', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Handles modifying smileys settings.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function EditSmileySettings($return_config = false)
{
	global $modSettings, $context, $txt, $boarddir, $sourcedir, $scripturl;

	// The directories...
	$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
	$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

	// Get the names of the smiley sets.
	$smiley_sets = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);

	$smiley_context = array();
	foreach ($smiley_sets as $i => $set)
		$smiley_context[$set] = $set_names[$i];

	// All the settings for the page...
	$config_vars = array(
		array('title', 'settings'),
		// Inline permissions.
		array('permissions', 'manage_smileys'),
		'',

		array('select', 'smiley_sets_default', $smiley_context),
		array('check', 'smiley_sets_enable'),
		array('check', 'smiley_enable', 'subtext' => $txt['smileys_enable_note']),
		array('text', 'smileys_url', 40),
		array('warning', !is_dir($context['smileys_dir']) ? 'setting_smileys_dir_wrong' : ''),
		array('text', 'smileys_dir', 'invalid' => !$context['smileys_dir_found'], 40),
		'',

		// Message icons.
		array('check', 'messageIcons_enable', 'subtext' => $txt['setting_messageIcons_enable_note']),
	);

	call_integration_hook('integrate_modify_smiley_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the basics of the settings template.
	require_once($sourcedir . '/ManageServer.php');
	$context['sub_template'] = 'show_settings';

	// Finish up the form...
	$context['post_url'] = $scripturl . '?action=admin;area=smileys;save;sa=settings';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();

		// Validate the smiley set name.
		$_POST['smiley_sets_default'] = empty($smiley_context[$_POST['smiley_sets_default']]) ? $modSettings['smiley_sets_default'] : $_POST['smiley_sets_default'];

		call_integration_hook('integrate_save_smiley_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;

		foreach (explode(',', $modSettings['smiley_sets_known']) as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
			cache_put_data('posting_smileys_' . $smiley_set, null, 480);
		}

		redirectexit('action=admin;area=smileys;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * List, add, remove, modify smileys sets.
 */
function EditSmileySets()
{
	global $modSettings, $context, $txt;
	global $smcFunc, $scripturl, $sourcedir;

	// Set the right tab to be selected.
	$context[$context['admin_menu_name']]['current_subsection'] = 'editsets';

	$allowedTypes = array('gif', 'png', 'jpg', 'jpeg', 'tiff', 'svg');

	// They must've been submitted a form.
	if (isset($_POST['smiley_save']))
	{
		checkSession();
		validateToken('admin-mss', 'request');

		// Delete selected smiley sets.
		if (!empty($_POST['delete']) && !empty($_POST['smiley_set']))
		{
			$set_paths = explode(',', $modSettings['smiley_sets_known']);
			$set_names = explode("\n", $modSettings['smiley_sets_names']);
			foreach ($_POST['smiley_set'] as $id => $val)
			{
				// If this is the set you've marked as default, or the only one remaining, you can't delete it
				if ($modSettings['smiley_sets_default'] != $set_paths[$id] && count($set_paths) != 1 && isset($set_paths[$id], $set_names[$id]))
				{
					// Delete this set's entries from the smiley_files table
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}smiley_files
						WHERE smiley_set = {string:smiley_set}',
						array(
							'smiley_set' => $set_paths[$id],
						)
					);

					// Remove this set from our lists
					unset($set_paths[$id], $set_names[$id]);
				}
			}

			// Shortcut... array_merge() on a single array resets the numeric keys
			$set_paths = array_merge($set_paths);
			$set_names = array_merge($set_names);

			updateSettings(array(
				'smiley_sets_known' => implode(',', $set_paths),
				'smiley_sets_names' => implode("\n", $set_names),
				'smiley_sets_default' => in_array($modSettings['smiley_sets_default'], $set_paths) ? $modSettings['smiley_sets_default'] : $set_paths[0],
			));
		}
		// Add a new smiley set.
		elseif (!empty($_POST['add']))
			$context['sub_action'] = 'modifyset';
		// Create or modify a smiley set.
		elseif (isset($_POST['set']))
		{
			$set_paths = explode(',', $modSettings['smiley_sets_known']);
			$set_names = explode("\n", $modSettings['smiley_sets_names']);

			// Create a new smiley set.
			if ($_POST['set'] == -1 && isset($_POST['smiley_sets_path']))
			{
				if (in_array($_POST['smiley_sets_path'], $set_paths))
					fatal_lang_error('smiley_set_already_exists');

				updateSettings(array(
					'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . $_POST['smiley_sets_path'],
					'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $_POST['smiley_sets_name'],
					'smiley_sets_default' => empty($_POST['smiley_sets_default']) ? $modSettings['smiley_sets_default'] : $_POST['smiley_sets_path'],
				));
			}
			// Modify an existing smiley set.
			else
			{
				// Make sure the smiley set exists.
				if (!isset($set_paths[$_POST['set']]) || !isset($set_names[$_POST['set']]))
					fatal_lang_error('smiley_set_not_found');

				// Make sure the path is not yet used by another smileyset.
				if (in_array($_POST['smiley_sets_path'], $set_paths) && $_POST['smiley_sets_path'] != $set_paths[$_POST['set']])
					fatal_lang_error('smiley_set_path_already_used');

				$set_paths[$_POST['set']] = $_POST['smiley_sets_path'];
				$set_names[$_POST['set']] = $_POST['smiley_sets_name'];
				updateSettings(array(
					'smiley_sets_known' => implode(',', $set_paths),
					'smiley_sets_names' => implode("\n", $set_names),
					'smiley_sets_default' => empty($_POST['smiley_sets_default']) ? $modSettings['smiley_sets_default'] : $_POST['smiley_sets_path']
				));
			}

			// Import, but only the ones that match existing smileys
			ImportSmileys($_POST['smiley_sets_path'], false);
		}

		foreach ($set_paths as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
			cache_put_data('posting_smileys_' . $smiley_set, null, 480);
		}
	}

	// Load all available smileysets...
	$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	foreach ($context['smiley_sets'] as $i => $set)
		$context['smiley_sets'][$i] = array(
			'id' => $i,
			'raw_path' => $set,
			'path' => $smcFunc['htmlspecialchars']($set),
			'name' => $smcFunc['htmlspecialchars']($set_names[$i]),
			'selected' => $set == $modSettings['smiley_sets_default']
		);

	// Importing any smileys from an existing set?
	if ($context['sub_action'] == 'import')
	{
		checkSession('get');
		validateToken('admin-mss', 'request');

		$_GET['set'] = (int) $_GET['set'];

		// Sanity check - then import.
		if (isset($context['smiley_sets'][$_GET['set']]))
			ImportSmileys(un_htmlspecialchars($context['smiley_sets'][$_GET['set']]['path']), true);

		// Force the process to continue.
		$context['sub_action'] = 'modifyset';
		$context['sub_template'] = 'modifyset';
	}
	// If we're modifying or adding a smileyset, some context info needs to be set.
	if ($context['sub_action'] == 'modifyset')
	{
		$_GET['set'] = !isset($_GET['set']) ? -1 : (int) $_GET['set'];
		if ($_GET['set'] == -1 || !isset($context['smiley_sets'][$_GET['set']]))
			$context['current_set'] = array(
				'id' => '-1',
				'raw_path' => '',
				'path' => '',
				'name' => '',
				'selected' => false,
				'is_new' => true,
			);
		else
		{
			$context['current_set'] = &$context['smiley_sets'][$_GET['set']];
			$context['current_set']['is_new'] = false;

			// Calculate whether there are any smileys in the directory that can be imported.
			if (!empty($modSettings['smiley_enable']) && !empty($modSettings['smileys_dir']) && is_dir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']))
			{
				$smileys = array();
				$dir = dir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']);
				while ($entry = $dir->read())
				{
					$pathinfo = pathinfo($entry);
					if (empty($pathinfo['filename']) || empty($pathinfo['extension']))
						continue;
					if (in_array($pathinfo['extension'], $allowedTypes) && $pathinfo['filename'] != 'blank')
						$smileys[strtolower($entry)] = $entry;
				}
				$dir->close();

				if (empty($smileys))
					fatal_lang_error('smiley_set_dir_not_found', false, array($context['current_set']['name']));

				// Exclude the smileys that are already in the database.
				$request = $smcFunc['db_query']('', '
					SELECT filename
					FROM {db_prefix}smiley_files
					WHERE filename IN ({array_string:smiley_list})
						AND smiley_set = {string:smiley_set}',
					array(
						'smiley_list' => $smileys,
						'smiley_set' => $context['current_set']['path'],
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					if (isset($smileys[strtolower($row['filename'])]))
						unset($smileys[strtolower($row['filename'])]);
				}

				$smcFunc['db_free_result']($request);

				$context['current_set']['can_import'] = count($smileys);
				$context['current_set']['import_url'] = $scripturl . '?action=admin;area=smileys;sa=import;set=' . $context['current_set']['id'] . ';' . $context['session_var'] . '=' . $context['session_id'];
			}
		}

		// Retrieve all potential smiley set directories.
		$context['smiley_set_dirs'] = array();
		if (!empty($modSettings['smileys_dir']) && is_dir($modSettings['smileys_dir']))
		{
			$dir = dir($modSettings['smileys_dir']);
			while ($entry = $dir->read())
			{
				if (!in_array($entry, array('.', '..')) && is_dir($modSettings['smileys_dir'] . '/' . $entry))
					$context['smiley_set_dirs'][] = array(
						'id' => $entry,
						'path' => $modSettings['smileys_dir'] . '/' . $entry,
						'selectable' => $entry == $context['current_set']['path'] || !in_array($entry, explode(',', $modSettings['smiley_sets_known'])),
						'current' => $entry == $context['current_set']['path'],
					);
			}
			$dir->close();
		}
	}

	// This is our save haven.
	createToken('admin-mss', 'request');

	// In case we need to import smileys, we need to add the token in now.
	if (isset($context['current_set']['import_url']))
	{
		$context['current_set']['import_url'] .= ';' . $context['admin-mss_token_var'] . '=' . $context['admin-mss_token'];
		$context['smiley_set_unused_message'] = sprintf($txt['smiley_set_unused'], $scripturl . '?action=admin;area=smileys;sa=editsmileys', $scripturl . '?action=admin;area=smileys;sa=addsmiley', $context['current_set']['import_url']);
	}

	$listOptions = array(
		'id' => 'smiley_set_list',
		'title' => $txt['smiley_sets'],
		'no_items_label' => $txt['smiley_sets_none'],
		'base_href' => $scripturl . '?action=admin;area=smileys;sa=editsets',
		'default_sort_col' => 'name',
		'get_items' => array(
			'function' => 'list_getSmileySets',
		),
		'get_count' => array(
			'function' => 'list_getNumSmileySets',
		),
		'columns' => array(
			'default' => array(
				'header' => array(
					'value' => $txt['smiley_sets_default'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return $rowData['selected'] ? '<span class="main_icons valid"></span>' : '';
					},
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => 'selected',
					'reverse' => 'selected DESC',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['smiley_sets_name'],
				),
				'data' => array(
					'db_htmlsafe' => 'name',
				),
				'sort' => array(
					'default' => 'name',
					'reverse' => 'name DESC',
				),
			),
			'url' => array(
				'header' => array(
					'value' => $txt['smiley_sets_url'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => $modSettings['smileys_url'] . '/<strong>%1$s</strong>/...',
						'params' => array(
							'path' => true,
						),
					),
				),
				'sort' => array(
					'default' => 'path',
					'reverse' => 'path DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['smiley_set_modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifyset;set=%1$d">' . $txt['smiley_set_modify'] . '</a>',
						'params' => array(
							'id' => true,
						),
					),
					'class' => 'centercol',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return $rowData['selected'] ? '' : sprintf('<input type="checkbox" name="smiley_set[%1$d]">', $rowData['id']);
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=smileys;sa=editsets',
			'token' => 'admin-mss',
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<input type="hidden" name="smiley_save"><input type="submit" name="delete" value="' . $txt['smiley_sets_delete'] . '" data-confirm="' . $txt['smiley_sets_confirm'] . '" class="button you_sure"> <a class="button" href="' . $scripturl . '?action=admin;area=smileys;sa=modifyset' . '">' . $txt['smiley_sets_add'] . '</a> ',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="hidden" name="smiley_save"><input type="submit" name="delete" value="' . $txt['smiley_sets_delete'] . '" data-confirm="' . $txt['smiley_sets_confirm'] . '" class="button you_sure"> <a class="button" href="' . $scripturl . '?action=admin;area=smileys;sa=modifyset' . '">' . $txt['smiley_sets_add'] . '</a> ',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);
}

/**
 * Callback function for createList().
 *
 * @todo to be moved to Subs-Smileys?
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to show per page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about the smiley sets
 */
function list_getSmileySets($start, $items_per_page, $sort)
{
	global $modSettings;

	$known_sets = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	$cols = array(
		'id' => array(),
		'selected' => array(),
		'path' => array(),
		'name' => array(),
	);
	foreach ($known_sets as $i => $set)
	{
		$cols['id'][] = $i;
		$cols['selected'][] = $set == $modSettings['smiley_sets_default'];
		$cols['path'][] = $set;
		$cols['name'][] = $set_names[$i];
	}
	$sort_flag = strpos($sort, 'DESC') === false ? SORT_ASC : SORT_DESC;
	if (substr($sort, 0, 4) === 'name')
		array_multisort($cols['name'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['selected'], $cols['id']);
	elseif (substr($sort, 0, 4) === 'path')
		array_multisort($cols['path'], $sort_flag, SORT_REGULAR, $cols['name'], $cols['selected'], $cols['id']);
	else
		array_multisort($cols['selected'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['name'], $cols['id']);

	$smiley_sets = array();
	foreach ($cols['id'] as $i => $id)
		$smiley_sets[] = array(
			'id' => $id,
			'path' => $cols['path'][$i],
			'name' => $cols['name'][$i],
			'selected' => $cols['selected'][$i],
		);

	return $smiley_sets;
}

/**
 * Callback function for createList().
 *
 * @todo to be moved to Subs-Smileys?
 * @return int The total number of known smiley sets
 */
function list_getNumSmileySets()
{
	global $modSettings;

	return count(explode(',', $modSettings['smiley_sets_known']));
}

/**
 * Add a smiley, that's right.
 */
function AddSmiley()
{
	global $modSettings, $context, $txt, $boarddir, $smcFunc;

	// Get a list of all known smiley sets.
	$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
	$context['smileys_dir_found'] = is_dir($context['smileys_dir']);
	$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	foreach ($context['smiley_sets'] as $i => $set)
		$context['smiley_sets'][$i] = array(
			'id' => $i,
			'raw_path' => $set,
			'path' => $smcFunc['htmlspecialchars']($set),
			'name' => $smcFunc['htmlspecialchars']($set_names[$i]),
			'selected' => $set == $modSettings['smiley_sets_default']
		);

	// Some useful arrays... types we allow - and ports we don't!
	$allowedTypes = array('gif', 'png', 'jpg', 'jpeg', 'tiff', 'svg');
	$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');

	// This will hold the names of the added files for each set
	$filename_array = array();

	// Submitting a form?
	if (isset($_POST[$context['session_var']], $_POST['smiley_code']))
	{
		checkSession();

		$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
		$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];
		$_POST['smiley_filename'] = htmltrim__recursive($_POST['smiley_filename']);

		// Make sure some code was entered.
		if (empty($_POST['smiley_code']))
			fatal_lang_error('smiley_has_no_code');

		// Check whether the new code has duplicates. It should be unique.
		$request = $smcFunc['db_query']('', '
			SELECT id_smiley
			FROM {db_prefix}smileys
			WHERE code = {raw:mysql_binary_statement} {string:smiley_code}',
			array(
				'mysql_binary_statement' => $smcFunc['db_title'] == 'MySQL' ? 'BINARY' : '',
				'smiley_code' => $_POST['smiley_code'],
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
			fatal_lang_error('smiley_not_unique');
		$smcFunc['db_free_result']($request);

		// If we are uploading - check all the smiley sets are writable!
		if ($_POST['method'] != 'existing')
		{
			$writeErrors = array();
			foreach ($context['smiley_sets'] as $set)
			{
				if (!is_writable($context['smileys_dir'] . '/' . $set['raw_path']))
					$writeErrors[] = $set['path'];
			}
			if (!empty($writeErrors))
				fatal_lang_error('smileys_upload_error_notwritable', true, array(implode(', ', $writeErrors)));
		}

		// Uploading just one smiley for all of them?
		if (isset($_POST['sameall']) && isset($_FILES['uploadSmiley']['name']) && $_FILES['uploadSmiley']['name'] != '')
		{
			if (!is_uploaded_file($_FILES['uploadSmiley']['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['uploadSmiley']['tmp_name'])))
				fatal_lang_error('smileys_upload_error');

			// Sorry, no spaces, dots, or anything else but letters allowed.
			$_FILES['uploadSmiley']['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['uploadSmiley']['name']);

			// We only allow image files - it's THAT simple - no messing around here...
			if (!in_array(strtolower(pathinfo($_FILES['uploadSmiley']['name'], PATHINFO_EXTENSION)), $allowedTypes))
				fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

			// We only need the filename...
			$destName = basename($_FILES['uploadSmiley']['name']);

			// Make sure they aren't trying to upload a nasty file - for their own good here!
			if (in_array(strtolower($destName), $disabledFiles))
				fatal_lang_error('smileys_upload_error_illegal');

			// Check if the file already exists... and if not move it to EVERY smiley set directory.
			$smileyLocation = null;
			foreach ($context['smiley_sets'] as $i => $set)
			{
				// Okay, we're going to put the smiley right here, since it's not there yet!
				if (!file_exists($context['smileys_dir'] . '/' . $context['smiley_sets'][$i]['raw_path']) . '/' . $destName)
				{
					$smileyLocation = $context['smileys_dir'] . '/' . $context['smiley_sets'][$i]['raw_path'] . '/' . $destName;
					move_uploaded_file($_FILES['uploadSmiley']['tmp_name'], $smileyLocation);
					smf_chmod($smileyLocation, 0644);
					break;
				}
			}

			// Now, we want to move it from there to all the other sets.
			foreach ($context['smiley_sets'] as $j => $set)
			{
				$currentPath = $context['smileys_dir'] . '/' . $context['smiley_sets'][$j]['raw_path'] . '/' . $destName;

				// Copy the first one we made to here, unless it already exists there
				if (!empty($smileyLocation) && !file_exists($currentPath))
				{
					copy($smileyLocation, $currentPath);
					smf_chmod($currentPath, 0644);
				}

				// Double-check
				if (!file_exists($currentPath))
					fatal_lang_error('smiley_not_found');

				// Finally make sure it's saved correctly!
				$filename_array[$context['smiley_sets'][$j]['raw_path']] = $destName;
			}
		}
		// What about uploading several files?
		elseif ($_POST['method'] != 'existing')
		{
			$newName = '';
			foreach ($_FILES as $name => $data)
			{
				if ($_FILES[$name]['name'] == '')
					fatal_lang_error('smileys_upload_error_blank');

				// if (empty($newName))
				// 	$newName = basename($_FILES[$name]['name']);
				// elseif (basename($_FILES[$name]['name']) != $newName)
				// 	fatal_lang_error('smileys_upload_error_name');
			}

			foreach ($context['smiley_sets'] as $i => $set)
			{
				$set['name'] = un_htmlspecialchars($set['name']);

				if (!isset($_FILES['individual_' . $set['raw_path']]['name']) || $_FILES['individual_' . $set['raw_path']]['name'] == '')
					continue;

				// Got one...
				if (!is_uploaded_file($_FILES['individual_' . $set['raw_path']]['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['individual_' . $set['raw_path']]['tmp_name'])))
					fatal_lang_error('smileys_upload_error');

				// Sorry, no spaces, dots, or anything else but letters allowed.
				$_FILES['individual_' . $set['raw_path']]['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['individual_' . $set['raw_path']]['name']);

				// We only allow image files - it's THAT simple - no messing around here...
				if (!in_array(strtolower(pathinfo($_FILES['individual_' . $set['raw_path']]['name'], PATHINFO_EXTENSION)), $allowedTypes))
					fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

				// We only need the filename...
				$destName = basename($_FILES['individual_' . $set['raw_path']]['name']);

				// Make sure they aren't trying to upload a nasty file - for their own good here!
				if (in_array(strtolower($destName), $disabledFiles))
					fatal_lang_error('smileys_upload_error_illegal');

				// If the file exists - ignore it.
				$smileyLocation = $context['smileys_dir'] . '/' . $set['raw_path'] . '/' . $destName;
				if (!file_exists($smileyLocation))
				{
					// Finally - move the image!
					move_uploaded_file($_FILES['individual_' . $set['raw_path']]['tmp_name'], $smileyLocation);
					smf_chmod($smileyLocation, 0644);
				}

				// Double-check
				if (!file_exists($smileyLocation))
					fatal_lang_error('smiley_not_found');

				// Should always be saved correctly!
				$filename_array[$set['raw_path']] = $destName;
			}
		}
		// Re-using an existing image
		else
		{
			// Make sure a filename was given
			if (empty($_POST['smiley_filename']))
				fatal_lang_error('smiley_has_no_filename');

			// And make sure it is legitimate
			$pathinfo = pathinfo($_POST['smiley_filename']);

			if (!in_array($pathinfo['extension'], $allowedTypes))
				fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));
			if (strpos($pathinfo['filename'], '.') !== false)
				fatal_lang_error('smileys_upload_error_illegal');
			if (!in_array($pathinfo['dirname'], explode(',', $modSettings['smiley_sets_known'])))
				fatal_lang_error('smiley_set_not_found');
			if (!file_exists($context['smileys_dir'] . '/' . $pathinfo['dirname'] . '/' . $pathinfo['basename']))
				fatal_lang_error('smiley_not_found');

			// Now ensure every set has a file to use for this smiley
			foreach (explode(',', $modSettings['smiley_sets_known']) as $set)
			{
				unset($basename);

				// Check whether any similarly named files exist in the other set's directory
				$similar_files = glob($context['smileys_dir'] . '/' . $set . '/' . $pathinfo['filename'] . '.{' . implode(',', $allowedTypes) . '}', GLOB_BRACE);

				// If there's a similarly named file already there, use it
				if (!empty($similar_files))
				{
					// Prefer an exact match if there is one
					foreach ($similar_files as $similar_file)
					{
						if (basename($similar_file) == $pathinfo['basename'])
							$basename = $pathinfo['basename'];
					}

					// Same name, different extension
					if (empty($basename))
						$basename = basename(reset($similar_files));
				}
				// Otherwise, copy the image to the other set's directory
				else
				{
					copy($context['smileys_dir'] . '/' . $pathinfo['dirname'] . '/' . $pathinfo['basename'], $context['smileys_dir'] . '/' . $set . '/' . $pathinfo['basename']);
					smf_chmod($context['smileys_dir'] . '/' . $set . '/' . $pathinfo['basename'], 0644);

					$basename = $pathinfo['basename'];
				}

				// Double-check that everything went as expected
				if (empty($basename) || !file_exists($context['smileys_dir'] . '/' . $set . '/' . $basename))
					fatal_lang_error('smiley_not_found');

				// Okay, let's add this one
				$filename_array[$set] = $basename;
			}
		}

		// Find the position on the right.
		$smiley_order = '0';
		if ($_POST['smiley_location'] != 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT MAX(smiley_order) + 1
				FROM {db_prefix}smileys
				WHERE hidden = {int:smiley_location}
					AND smiley_row = {int:first_row}',
				array(
					'smiley_location' => $_POST['smiley_location'],
					'first_row' => 0,
				)
			);
			list ($smiley_order) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (empty($smiley_order))
				$smiley_order = '0';
		}

		// Add the new smiley to the main smileys table
		$new_id_smiley = $smcFunc['db_insert']('',
			'{db_prefix}smileys',
			array(
				'code' => 'string-30', 'description' => 'string-80', 'hidden' => 'int', 'smiley_order' => 'int',
			),
			array(
				$_POST['smiley_code'], $_POST['smiley_description'], $_POST['smiley_location'], $smiley_order,
			),
			array('id_smiley'),
			1
		);

		// Add the filename info to the smiley_files table
		$inserts = array();
		foreach ($filename_array as $set => $basename)
			$inserts[] = array($new_id_smiley, $set, $basename);

		$smcFunc['db_insert']('ignore',
			'{db_prefix}smiley_files',
			array(
				'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
			),
			$inserts,
			array('id_smiley', 'smiley_set')
		);

		foreach ($context['smiley_sets'] as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set['raw_path'], null, 480);
			cache_put_data('posting_smileys_' . $smiley_set['raw_path'], null, 480);
		}

		// No errors? Out of here!
		redirectexit('action=admin;area=smileys;sa=editsmileys');
	}

	$context['selected_set'] = $modSettings['smiley_sets_default'];

	// Get all possible filenames for the smileys.
	$context['filenames'] = array();
	if ($context['smileys_dir_found'])
	{
		foreach ($context['smiley_sets'] as $smiley_set)
		{
			if (!file_exists($context['smileys_dir'] . '/' . $smiley_set['raw_path']))
				continue;

			$dir = dir($context['smileys_dir'] . '/' . $smiley_set['raw_path']);
			while ($entry = $dir->read())
			{
				$entry_info = pathinfo($entry);
				if (empty($entry_info['filename']) || empty($entry_info['extension']))
					continue;
				if (empty($context['filenames'][$smiley_set['path']][strtolower($entry_info['filename'])]) && in_array(strtolower($entry_info['extension']), $allowedTypes))
					$context['filenames'][$smiley_set['path']][strtolower($entry_info['filename'])] = array(
						'id' => $smcFunc['htmlspecialchars']($entry),
						'selected' => $entry_info['filename'] == 'smiley' && $smiley_set['path'] == $context['selected_set'],
					);
			}
			$dir->close();
			ksort($context['filenames'][$smiley_set['path']]);
		}
		ksort($context['filenames']);
	}

	// Create a new smiley from scratch.
	$context['current_smiley'] = array(
		'id' => 0,
		'code' => '',
		'filename' => $context['filenames'][$smcFunc['htmlspecialchars']($context['selected_set'])]['smiley']['id'],
		'description' => $txt['smileys_default_description'],
		'location' => 0,
		'is_new' => true,
	);
}

/**
 * Add, remove, edit smileys.
 */
function EditSmileys()
{
	global $modSettings, $context, $txt, $boarddir;
	global $smcFunc, $scripturl, $sourcedir;

	// Force the correct tab to be displayed.
	$context[$context['admin_menu_name']]['current_subsection'] = 'editsmileys';

	$allowedTypes = array('gif', 'png', 'jpg', 'jpeg', 'tiff', 'svg');
	$known_sets = explode(',', $modSettings['smiley_sets_known']);

	// Submitting a form?
	if (isset($_POST['smiley_save']) || isset($_POST['smiley_action']) || isset($_POST['deletesmiley']))
	{
		checkSession();

		// Changing the selected smileys?
		if (isset($_POST['smiley_action']) && !empty($_POST['checked_smileys']))
		{
			foreach ($_POST['checked_smileys'] as $id => $smiley_id)
				$_POST['checked_smileys'][$id] = (int) $smiley_id;

			if ($_POST['smiley_action'] == 'delete')
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}smileys
					WHERE id_smiley IN ({array_int:checked_smileys})',
					array(
						'checked_smileys' => $_POST['checked_smileys'],
					)
				);
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}smiley_files
					WHERE id_smiley IN ({array_int:checked_smileys})',
					array(
						'checked_smileys' => $_POST['checked_smileys'],
					)
				);
			}
			// Changing the status of the smiley?
			else
			{
				// Check it's a valid type.
				$displayTypes = array(
					'post' => 0,
					'hidden' => 1,
					'popup' => 2
				);
				if (isset($displayTypes[$_POST['smiley_action']]))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}smileys
						SET hidden = {int:display_type}
						WHERE id_smiley IN ({array_int:checked_smileys})',
						array(
							'checked_smileys' => $_POST['checked_smileys'],
							'display_type' => $displayTypes[$_POST['smiley_action']],
						)
					);
			}
		}
		// Create/modify a smiley.
		elseif (isset($_POST['smiley']))
		{
			// Is it a delete?
			if (!empty($_POST['deletesmiley']) && $_POST['smiley'] == (int) $_POST['smiley'])
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}smileys
					WHERE id_smiley = {int:current_smiley}',
					array(
						'current_smiley' => $_POST['smiley'],
					)
				);
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}smiley_files
					WHERE id_smiley = {int:current_smiley}',
					array(
						'current_smiley' => $_POST['smiley'],
					)
				);
			}
			// Otherwise an edit.
			else
			{
				$_POST['smiley'] = (int) $_POST['smiley'];
				$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
				$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

				// Make sure some code was entered.
				if (empty($_POST['smiley_code']))
					fatal_lang_error('smiley_has_no_code');

				// Make sure all submitted filenames are clean.
				$filenames = array();
				foreach ($_POST['smiley_filename'] as $posted_set => $posted_filename)
				{
					$posted_set = htmltrim__recursive($posted_set);
					$posted_filename = htmltrim__recursive($posted_filename);

					// Make sure the set already exists.
					if (!in_array($posted_set, $known_sets))
						continue;

					$filenames[$posted_set] = pathinfo($posted_filename, PATHINFO_BASENAME);
				}
				// Fill in any missing sets.
				foreach ($known_sets as $known_set)
				{
					// Uh-oh, something is missing.
					if (empty($filenames[$known_set]))
					{
						// Try to make it the same as the default set.
						if (!empty($filenames[$modSettings['smiley_sets_default']]))
							$filenames[$known_set] = $filenames[$modSettings['smiley_sets_default']];
						// As a last resort, just try to get whatever the first one is.
						elseif (!empty($filenames))
							$filenames[$known_set] = reset($filenames);
					}
				}

				// Can't do anything without filenames for the smileys.
				if (empty($filenames))
					fatal_lang_error('smiley_has_no_filename');

				// Check whether the new code has duplicates. It should be unique.
				$request = $smcFunc['db_query']('', '
					SELECT id_smiley
					FROM {db_prefix}smileys
					WHERE code = {raw:mysql_binary_type} {string:smiley_code}' . (empty($_POST['smiley']) ? '' : '
						AND id_smiley != {int:current_smiley}'),
					array(
						'current_smiley' => $_POST['smiley'],
						'mysql_binary_type' => $smcFunc['db_title'] == 'MySQL' ? 'BINARY' : '',
						'smiley_code' => $_POST['smiley_code'],
					)
				);
				if ($smcFunc['db_num_rows']($request) > 0)
					fatal_lang_error('smiley_not_unique');
				$smcFunc['db_free_result']($request);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}smileys
					SET
						code = {string:smiley_code},
						description = {string:smiley_description},
						hidden = {int:smiley_location}
					WHERE id_smiley = {int:current_smiley}',
					array(
						'smiley_location' => $_POST['smiley_location'],
						'current_smiley' => $_POST['smiley'],
						'smiley_code' => $_POST['smiley_code'],
						'smiley_description' => $_POST['smiley_description'],
					)
				);

				// Update filename info in the smiley_files table
				$inserts = array();
				foreach ($filenames as $set => $filename)
					$inserts[] = array($_POST['smiley'], $set, $filename);

				$smcFunc['db_insert']('replace',
					'{db_prefix}smiley_files',
					array(
						'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
					),
					$inserts,
					array('id_smiley', 'smiley_set')
				);
			}
		}

		foreach ($known_sets as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
			cache_put_data('posting_smileys_' . $smiley_set, null, 480);
		}
	}

	// Load all known smiley sets.
	$context['smiley_sets'] = array_flip($known_sets);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	foreach ($context['smiley_sets'] as $set => $i)
		$context['smiley_sets'][$set] = array(
			'id' => $i,
			'raw_path' => $set,
			'path' => $smcFunc['htmlspecialchars']($set),
			'name' => $smcFunc['htmlspecialchars']($set_names[$i]),
			'selected' => $set == $modSettings['smiley_sets_default']
		);

	// Prepare overview of all (custom) smileys.
	if ($context['sub_action'] == 'editsmileys')
	{
		// Determine the language specific sort order of smiley locations.
		$smiley_locations = array(
			$txt['smileys_location_form'],
			$txt['smileys_location_hidden'],
			$txt['smileys_location_popup'],
		);
		asort($smiley_locations);

		// Create a list of options for selecting smiley sets.
		$smileyset_option_list = '
			<select name="set" onchange="changeSet(this.options[this.selectedIndex].value);">';
		foreach ($context['smiley_sets'] as $smiley_set)
			$smileyset_option_list .= '
				<option value="' . $smiley_set['path'] . '"' . ($modSettings['smiley_sets_default'] == $smiley_set['path'] ? ' selected' : '') . '>' . $smiley_set['name'] . '</option>';
		$smileyset_option_list .= '
			</select>';

		$listOptions = array(
			'id' => 'smiley_list',
			'title' => $txt['smileys_edit'],
			'items_per_page' => 40,
			'base_href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
			'default_sort_col' => 'filename',
			'get_items' => array(
				'function' => 'list_getSmileys',
			),
			'get_count' => array(
				'function' => 'list_getNumSmileys',
			),
			'no_items_label' => $txt['smileys_no_entries'],
			'columns' => array(
				'picture' => array(
					'data' => array(
						'function' => function($rowData) use ($scripturl, $modSettings, $context)
						{
							$return = '';

							foreach ($rowData['filename_array'] as $set => $filename)
							{
								$return .= ' <a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=' . $rowData['id_smiley'] . '" class="smiley_set ' . $set . '"><img src="' . $modSettings['smileys_url'] . '/' . $set . '/' . $filename . '" alt="' . $rowData['description'] . '" style="padding: 2px;" id="smiley' . $rowData['id_smiley'] . '"><input type="hidden" name="smileys[' . $rowData['id_smiley'] . '][filename]" value="' . $filename . '"></a>';
							}

							return $return;
						},
						'class' => 'centercol',
					),
				),
				'smileys_code' => array(
					'header' => array(
						'value' => $txt['smileys_code'],
					),
					'data' => array(
						'db_htmlsafe' => 'code',
					),
					'sort' => array(
						'default' => 'code',
						'reverse' => 'code DESC',
					),
				),
				'filename' => array(
					'header' => array(
						'value' => $txt['smileys_filename'],
					),
					'data' => array(
						'function' => function($rowData)
						{
							$return = '<span style="display:none">' . $rowData['filename'] . '</span>';

							foreach ($rowData['filename_array'] as $set => $filename)
								$return .= ' <span class="smiley_set ' . $set . '">' . $filename . '</span>';

							return $return;
						},
					),
					'sort' => array(
						'default' => 'filename',
						'reverse' => 'filename DESC',
					),
				),
				'location' => array(
					'header' => array(
						'value' => $txt['smileys_location'],
					),
					'data' => array(
						'function' => function($rowData) use ($txt)
						{
							if (empty($rowData['hidden']))
								return $txt['smileys_location_form'];
							elseif ($rowData['hidden'] == 1)
								return $txt['smileys_location_hidden'];
							else
								return $txt['smileys_location_popup'];
						},
					),
					'sort' => array(
						'default' => $smcFunc['db_custom_order']('hidden', array_keys($smiley_locations)),
						'reverse' => $smcFunc['db_custom_order']('hidden', array_keys($smiley_locations), true),
					),
				),
				'description' => array(
					'header' => array(
						'value' => $txt['smileys_description'],
					),
					'data' => array(
						'function' => function($rowData) use ($context, $txt, $modSettings, $smcFunc)
						{
							if (empty($modSettings['smileys_dir']) || !is_dir($modSettings['smileys_dir']))
								return $smcFunc['htmlspecialchars']($rowData['description']);

							// Check if there are smileys missing in some sets.
							$missing_sets = array();
							foreach ($context['smiley_sets'] as $smiley_set)
								if (empty($rowData['filename_array'][$smiley_set['path']]) || !file_exists(sprintf('%1$s/%2$s/%3$s', $modSettings['smileys_dir'], $smiley_set['path'], $rowData['filename_array'][$smiley_set['path']])))
									$missing_sets[] = $smiley_set['path'];

							$description = $smcFunc['htmlspecialchars']($rowData['description']);

							if (!empty($missing_sets))
								$description .= sprintf('<br><span class="smalltext"><strong>%1$s:</strong> %2$s</span>', $txt['smileys_not_found_in_set'], implode(', ', $missing_sets));

							return $description;
						},
					),
					'sort' => array(
						'default' => 'description',
						'reverse' => 'description DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['smileys_modify'],
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d">' . $txt['smileys_modify'] . '</a>',
							'params' => array(
								'id_smiley' => false,
							),
						),
						'class' => 'centercol',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="checked_smileys[]" value="%1$d">',
							'params' => array(
								'id_smiley' => false,
							),
						),
						'class' => 'centercol',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
				'name' => 'smileyForm',
			),
			'additional_rows' => array(
				array(
					'position' => 'above_column_headers',
					'value' => $smileyset_option_list,
					'class' => 'righttext',
				),
				array(
					'position' => 'below_table_data',
					'value' => '
						<select name="smiley_action" onchange="makeChanges(this.value);">
							<option value="-1">' . $txt['smileys_with_selected'] . ':</option>
							<option value="-1" disabled>--------------</option>
							<option value="hidden">' . $txt['smileys_make_hidden'] . '</option>
							<option value="post">' . $txt['smileys_show_on_post'] . '</option>
							<option value="popup">' . $txt['smileys_show_on_popup'] . '</option>
							<option value="delete">' . $txt['smileys_remove'] . '</option>
						</select>
						<noscript>
							<input type="submit" name="perform_action" value="' . $txt['go'] . '" class="button">
						</noscript>',
					'class' => 'righttext',
				),
			),
			'javascript' => '
				function makeChanges(action)
				{
					if (action == \'-1\')
						return false;
					else if (action == \'delete\')
					{
						if (confirm(\'' . $txt['smileys_confirm'] . '\'))
							document.forms.smileyForm.submit();
					}
					else
						document.forms.smileyForm.submit();
					return true;
				}
				function changeSet(newSet)
				{
					$(".smiley_set").hide();
					$(".smiley_set." + newSet).show();
				}',
		);

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		// The list is the only thing to show, so make it the main template.
		$context['default_list'] = 'smiley_list';
		$context['sub_template'] = 'show_list';

		addInlineJavaScript("\n\t" . 'changeSet("' . $modSettings['smiley_sets_default'] . '");', true);
	}
	// Modifying smileys.
	elseif ($context['sub_action'] == 'modifysmiley')
	{
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

		$context['selected_set'] = $modSettings['smiley_sets_default'];

		$request = $smcFunc['db_query']('', '
			SELECT s.id_smiley AS id, s.code, f.filename, f.smiley_set, s.description, s.hidden AS location
			FROM {db_prefix}smileys AS s
				JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
			WHERE s.id_smiley = {int:current_smiley}',
			array(
				'current_smiley' => (int) $_REQUEST['smiley'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// The empty() bit is for just in case the default set is missing this smiley
			if ($row['smiley_set'] == $context['selected_set'] || empty($context['current_smiley']))
				$context['current_smiley'] = $row;

			$filenames[$row['smiley_set']] = $row['filename'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($context['current_smiley']))
			fatal_lang_error('smiley_not_found');

		$context['current_smiley']['code'] = $smcFunc['htmlspecialchars']($context['current_smiley']['code']);
		$context['current_smiley']['description'] = $smcFunc['htmlspecialchars']($context['current_smiley']['description']);
		$context['current_smiley']['filename'] = $smcFunc['htmlspecialchars']($context['current_smiley']['filename']);

		// Get all possible filenames for the smileys.
		$context['filenames'] = array();
		$context['missing_sets'] = array();
		if ($context['smileys_dir_found'])
		{
			foreach ($context['smiley_sets'] as $smiley_set)
			{
				if (!file_exists($context['smileys_dir'] . '/' . $smiley_set['raw_path']))
					continue;

				// No file currently defined for this smiley in this set? That's no good.
				if (!isset($filenames[$smiley_set['raw_path']]))
				{
					$context['missing_sets'][] = $smiley_set['raw_path'];
					$context['filenames'][$smiley_set['path']][''] = array('id' => '', 'selected' => true, 'disabled' => true);
				}

				$dir = dir($context['smileys_dir'] . '/' . $smiley_set['raw_path']);
				while ($entry = $dir->read())
				{
					if (empty($context['filenames'][$smiley_set['path']][$entry]) && in_array(pathinfo($entry, PATHINFO_EXTENSION), $allowedTypes))
						$context['filenames'][$smiley_set['path']][$entry] = array(
							'id' => $smcFunc['htmlspecialchars']($entry),
							'selected' => isset($filenames[$smiley_set['raw_path']]) && strtolower($entry) == strtolower($filenames[$smiley_set['raw_path']]),
							'disabled' => false,
						);
				}
				$dir->close();
				ksort($context['filenames'][$smiley_set['path']]);
			}
			ksort($context['filenames']);
		}
	}
}

/**
 * Callback function for createList().
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to show per page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about the smileys
 */
function list_getSmileys($start, $items_per_page, $sort)
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT s.id_smiley, s.code, f.filename, f.smiley_set, s.description, s.smiley_row, s.smiley_order, s.hidden
		FROM {db_prefix}smileys AS s
			JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
		ORDER BY {raw:sort}',
		array(
			'sort' => $sort,
		)
	);
	$smileys = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($smileys[$row['id_smiley']]))
		{
			$smileys[$row['id_smiley']] = $row;
			unset($smileys[$row['id_smiley']]['smiley_set']);
			$smileys[$row['id_smiley']]['filename_array'] = array($row['smiley_set'] => $row['filename']);
		}
		else
		{
			$smileys[$row['id_smiley']]['filename_array'][$row['smiley_set']] = $row['filename'];
		}

		// Use the filename for the default set as the primary filename for this smiley
		if (isset($smileys[$row['id_smiley']]['filename_array'][$modSettings['smiley_sets_default']]))
			$smileys[$row['id_smiley']]['filename'] = $smileys[$row['id_smiley']]['filename_array'][$modSettings['smiley_sets_default']];
		else
			$smileys[$row['id_smiley']]['filename'] = reset($smileys[$row['id_smiley']]['filename_array']);
	}
	$smcFunc['db_free_result']($request);

	return $smileys;
}

/**
 * Callback function for createList().
 *
 * @return int The number of smileys
 */
function list_getNumSmileys()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}smileys',
		array()
	);
	list($numSmileys) = $smcFunc['db_fetch_row'];
	$smcFunc['db_free_result']($request);

	return $numSmileys;
}

/**
 * Allows to edit smileys order.
 */
function EditSmileyOrder()
{
	global $context, $txt, $smcFunc, $modSettings;

	// Move smileys to another position.
	if (isset($_REQUEST['reorder']))
	{
		checkSession('get');

		$_GET['location'] = empty($_GET['location']) || $_GET['location'] != 'popup' ? 0 : 2;
		$_GET['source'] = empty($_GET['source']) ? 0 : (int) $_GET['source'];

		if (empty($_GET['source']))
			fatal_lang_error('smiley_not_found');

		if (!empty($_GET['after']))
		{
			$_GET['after'] = (int) $_GET['after'];

			$request = $smcFunc['db_query']('', '
				SELECT smiley_row, smiley_order, hidden
				FROM {db_prefix}smileys
				WHERE hidden = {int:location}
					AND id_smiley = {int:after_smiley}',
				array(
					'location' => $_GET['location'],
					'after_smiley' => $_GET['after'],
				)
			);
			if ($smcFunc['db_num_rows']($request) != 1)
				fatal_lang_error('smiley_not_found');
			list ($smiley_row, $smiley_order, $smileyLocation) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$smiley_row = (int) $_GET['row'];
			$smiley_order = -1;
			$smileyLocation = (int) $_GET['location'];
		}

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}smileys
			SET smiley_order = smiley_order + 1
			WHERE hidden = {int:new_location}
				AND smiley_row = {int:smiley_row}
				AND smiley_order > {int:smiley_order}',
			array(
				'new_location' => $_GET['location'],
				'smiley_row' => $smiley_row,
				'smiley_order' => $smiley_order,
			)
		);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}smileys
			SET
				smiley_order = {int:smiley_order} + 1,
				smiley_row = {int:smiley_row},
				hidden = {int:new_location}
			WHERE id_smiley = {int:current_smiley}',
			array(
				'smiley_order' => $smiley_order,
				'smiley_row' => $smiley_row,
				'new_location' => $smileyLocation,
				'current_smiley' => $_GET['source'],
			)
		);

		foreach (explode(',', $modSettings['smiley_sets_known']) as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
			cache_put_data('posting_smileys_' . $smiley_set, null, 480);
		}
	}

	$request = $smcFunc['db_query']('', '
		SELECT s.id_smiley, s.code, f.filename, s.description, s.smiley_row, s.smiley_order, s.hidden
		FROM {db_prefix}smileys AS s
			JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
		WHERE s.hidden != {int:popup}
			AND f.smiley_set = {string:smiley_set}
		ORDER BY s.smiley_order, s.smiley_row',
		array(
			'popup' => 1,
			'smiley_set' => $modSettings['smiley_sets_default'],
		)
	);
	$context['smileys'] = array(
		'postform' => array(
			'rows' => array(),
		),
		'popup' => array(
			'rows' => array(),
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$location = empty($row['hidden']) ? 'postform' : 'popup';
		$context['smileys'][$location]['rows'][$row['smiley_row']][] = array(
			'id' => $row['id_smiley'],
			'code' => $smcFunc['htmlspecialchars']($row['code']),
			'filename' => $smcFunc['htmlspecialchars']($row['filename']),
			'description' => $smcFunc['htmlspecialchars']($row['description']),
			'row' => $row['smiley_row'],
			'order' => $row['smiley_order'],
			'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
		);
	}
	$smcFunc['db_free_result']($request);

	$context['move_smiley'] = empty($_REQUEST['move']) ? 0 : (int) $_REQUEST['move'];

	// Make sure all rows are sequential.
	foreach (array_keys($context['smileys']) as $location)
		$context['smileys'][$location] = array(
			'id' => $location,
			'title' => $location == 'postform' ? $txt['smileys_location_form'] : $txt['smileys_location_popup'],
			'description' => $location == 'postform' ? $txt['smileys_location_form_description'] : $txt['smileys_location_popup_description'],
			'last_row' => count($context['smileys'][$location]['rows']),
			'rows' => array_values($context['smileys'][$location]['rows']),
		);

	// Check & fix smileys that are not ordered properly in the database.
	foreach (array_keys($context['smileys']) as $location)
	{
		foreach ($context['smileys'][$location]['rows'] as $id => $smiley_row)
		{
			// Fix empty rows if any.
			if ($id != $smiley_row[0]['row'])
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}smileys
					SET smiley_row = {int:new_row}
					WHERE smiley_row = {int:current_row}
						AND hidden = {int:location}',
					array(
						'new_row' => $id,
						'current_row' => $smiley_row[0]['row'],
						'location' => $location == 'postform' ? '0' : '2',
					)
				);
				// Only change the first row value of the first smiley (we don't need the others :P).
				$context['smileys'][$location]['rows'][$id][0]['row'] = $id;
			}
			// Make sure the smiley order is always sequential.
			foreach ($smiley_row as $order_id => $smiley)
				if ($order_id != $smiley['order'])
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}smileys
						SET smiley_order = {int:new_order}
						WHERE id_smiley = {int:current_smiley}',
						array(
							'new_order' => $order_id,
							'current_smiley' => $smiley['id'],
						)
					);
		}
	}

	foreach (explode(',', $modSettings['smiley_sets_known']) as $smiley_set)
	{
		cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
		cache_put_data('posting_smileys_' . $smiley_set, null, 480);
	}
}

/**
 * Install a smiley set.
 */
function InstallSmileySet()
{
	global $sourcedir, $boarddir, $packagesdir, $modSettings, $smcFunc, $scripturl, $context, $txt, $user_info;

	isAllowedTo('manage_smileys');
	checkSession('request');
	// One of these two may be necessary
	loadLanguage('Errors');
	loadLanguage('Packages');

	require_once($sourcedir . '/Subs-Package.php');

	// Installing unless proven otherwise
	$testing = false;

	if (isset($_REQUEST['set_gz']))
	{
		$base_name = strtr(basename($_REQUEST['set_gz']), ':/', '-_');
		$name = $smcFunc['htmlspecialchars'](strtok(basename($_REQUEST['set_gz']), '.'));
		$context['filename'] = $base_name;

		// Check that the smiley is from simplemachines.org, for now... maybe add mirroring later.
		// @ TODO: Our current xml files serve http links.  Allowing both for now until we serve https.
		if (preg_match('~^https?://[\w_\-]+\.simplemachines\.org/~', $_REQUEST['set_gz']) == 0 || strpos($_REQUEST['set_gz'], 'dlattach') !== false)
			fatal_lang_error('not_on_simplemachines');

		$destination = $packagesdir . '/' . $base_name;

		if (file_exists($destination))
			fatal_lang_error('package_upload_error_exists');

		// Let's copy it to the Packages directory
		file_put_contents($destination, fetch_web_data($_REQUEST['set_gz']));
		$testing = true;
	}
	elseif (isset($_REQUEST['package']))
	{
		$base_name = basename($_REQUEST['package']);
		$name = $smcFunc['htmlspecialchars'](strtok(basename($_REQUEST['package']), '.'));
		$context['filename'] = $base_name;

		$destination = $packagesdir . '/' . basename($_REQUEST['package']);
	}

	if (empty($destination) || !file_exists($destination))
		fatal_lang_error('package_no_file', false);

	// Make sure temp directory exists and is empty.
	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp', false);

	if (!mktree($packagesdir . '/temp', 0755))
	{
		deltree($packagesdir . '/temp', false);
		if (!mktree($packagesdir . '/temp', 0777))
		{
			deltree($packagesdir . '/temp', false);
			// @todo not sure about url in destination_url
			create_chmod_control(array($packagesdir . '/temp/delme.tmp'), array('destination_url' => $scripturl . '?action=admin;area=smileys;sa=install;set_gz=' . $_REQUEST['set_gz'], 'crash_on_error' => true));

			deltree($packagesdir . '/temp', false);
			if (!mktree($packagesdir . '/temp', 0777))
				fatal_lang_error('package_cant_download', false);
		}
	}

	$extracted = read_tgz_file($destination, $packagesdir . '/temp');
	if (!$extracted)
		fatal_lang_error('packageget_unable', false, array('https://custom.simplemachines.org/mods/index.php?action=search;type=12;basic_search=' . $name));
	if ($extracted && !file_exists($packagesdir . '/temp/package-info.xml'))
		foreach ($extracted as $file)
			if (basename($file['filename']) == 'package-info.xml')
			{
				$base_path = dirname($file['filename']) . '/';
				break;
			}

	if (!isset($base_path))
		$base_path = '';

	if (!file_exists($packagesdir . '/temp/' . $base_path . 'package-info.xml'))
		fatal_lang_error('package_get_error_missing_xml', false);

	$smileyInfo = getPackageInfo($context['filename']);
	if (!is_array($smileyInfo))
		fatal_lang_error($smileyInfo);

	// See if it is installed?
	$request = $smcFunc['db_query']('', '
		SELECT version, themes_installed, db_changes
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $smileyInfo['id'],
		)
	);

	if ($smcFunc['db_num_rows']($request) > 0)
		fatal_lang_error('package_installed_warning1');

	// Everything is fine, now it's time to do something
	$actions = parsePackageInfo($smileyInfo['xml'], true, 'install');

	$context['post_url'] = $scripturl . '?action=admin;area=smileys;sa=install;package=' . $base_name;
	$context['has_failure'] = false;
	$context['actions'] = array();
	$context['ftp_needed'] = false;

	foreach ($actions as $action)
	{
		if ($action['type'] == 'readme' || $action['type'] == 'license')
		{
			$type = 'package_' . $action['type'];
			if (file_exists($packagesdir . '/temp/' . $base_path . $action['filename']))
				$context[$type] = $smcFunc['htmlspecialchars'](trim(file_get_contents($packagesdir . '/temp/' . $base_path . $action['filename']), "\n\r"));
			elseif (file_exists($action['filename']))
				$context[$type] = $smcFunc['htmlspecialchars'](trim(file_get_contents($action['filename']), "\n\r"));

			if (!empty($action['parse_bbc']))
			{
				require_once($sourcedir . '/Subs-Post.php');
				preparsecode($context[$type]);
				$context[$type] = parse_bbc($context[$type]);
			}
			else
				$context[$type] = nl2br($context[$type]);

			continue;
		}
		elseif ($action['type'] == 'require-dir')
		{
			// Do this one...
			$thisAction = array(
				'type' => $txt['package_extract'] . ' ' . ($action['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
				'action' => $smcFunc['htmlspecialchars'](strtr($action['destination'], array($boarddir => '.')))
			);

			$file = $packagesdir . '/temp/' . $base_path . $action['filename'];
			if (isset($action['filename']) && (!file_exists($file) || !is_writable(dirname($action['destination']))))
			{
				$context['has_failure'] = true;

				$thisAction += array(
					'description' => $txt['package_action_error'],
					'failed' => true,
				);
			}
			// @todo None given?
			if (empty($thisAction['description']))
				$thisAction['description'] = isset($action['description']) ? $action['description'] : '';

			$context['actions'][] = $thisAction;
		}
		elseif ($action['type'] == 'credits')
		{
			// Time to build the billboard
			$credits_tag = array(
				'url' => $action['url'],
				'license' => $action['license'],
				'copyright' => $action['copyright'],
				'title' => $action['title'],
			);
		}
	}

	if ($testing)
	{
		$context['sub_template'] = 'view_package';
		$context['uninstalling'] = false;
		$context['is_installed'] = false;
		$context['package_name'] = $smileyInfo['name'];
		loadTemplate('Packages');
	}
	// Do the actual install
	else
	{
		// @TODO Does this call have side effects? ($actions is not used)
		$actions = parsePackageInfo($smileyInfo['xml'], false, 'install');
		foreach ($context['actions'] as $action)
		{
			updateSettings(array(
				'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . basename($action['action']),
				'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $smileyInfo['name'] . (count($context['actions']) > 1 ? ' ' . (!empty($action['description']) ? $smcFunc['htmlspecialchars']($action['description']) : basename($action['action'])) : ''),
			));
		}

		package_flush_cache();

		// Credits tag?
		$credits_tag = (empty($credits_tag)) ? '' : $smcFunc['json_encode']($credits_tag);
		$smcFunc['db_insert']('',
			'{db_prefix}log_packages',
			array(
				'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
				'id_member_installed' => 'int', 'member_installed' => 'string', 'time_installed' => 'int',
				'install_state' => 'int', 'failed_steps' => 'string', 'themes_installed' => 'string',
				'member_removed' => 'int', 'db_changes' => 'string', 'credits' => 'string',
			),
			array(
				$smileyInfo['filename'], $smileyInfo['name'], $smileyInfo['id'], $smileyInfo['version'],
				$user_info['id'], $user_info['name'], time(),
				1, '', '',
				0, '', $credits_tag,
			),
			array('id_install')
		);

		logAction('install_package', array('package' => $smcFunc['htmlspecialchars']($smileyInfo['name']), 'version' => $smcFunc['htmlspecialchars']($smileyInfo['version'])), 'admin');

		foreach (explode(',', $modSettings['smiley_sets_known']) as $smiley_set)
		{
			cache_put_data('parsing_smileys_' . $smiley_set, null, 480);
			cache_put_data('posting_smileys_' . $smiley_set, null, 480);
		}
	}

	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp');

	if (!$testing)
		redirectexit('action=admin;area=smileys');
}

/**
 * A function to import new smileys from an existing directory into the database.
 *
 * @param string $smileyPath The path to the directory to import smileys from
 * @param bool $create Whether or not to make brand new smileys for files that don't match any existing smileys
 */
function ImportSmileys($smileyPath, $create = false)
{
	global $modSettings, $smcFunc;

	if (empty($modSettings['smileys_dir']) || !is_dir($modSettings['smileys_dir'] . '/' . $smileyPath))
		fatal_lang_error('smiley_set_unable_to_import');

	$allowedTypes = array('gif', 'png', 'jpg', 'jpeg', 'tiff', 'svg');
	$known_sets = explode(',', $modSettings['smiley_sets_known']);
	sort($known_sets);

	// Get the smileys in the folder
	$smileys = array();
	$dir = dir($modSettings['smileys_dir'] . '/' . $smileyPath);
	while ($entry = $dir->read())
	{
		$pathinfo = pathinfo($entry);
		if (empty($pathinfo['filename']) || empty($pathinfo['extension']))
			continue;
		if (in_array($pathinfo['extension'], $allowedTypes) && $pathinfo['filename'] != 'blank' && strlen($pathinfo['basename']) <= 48)
			$smiley_files[strtolower($pathinfo['basename'])] = $pathinfo['basename'];
	}
	$dir->close();

	// Get the smileys that are already in the database.
	$existing_smileys = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_smiley, smiley_set, filename
		FROM {db_prefix}smiley_files',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$existing_smileys[pathinfo($row['filename'], PATHINFO_FILENAME)][$row['id_smiley']][] = $row['smiley_set'];
	$smcFunc['db_free_result']($request);

	// Filter $smiley_files down to just the ones not already in the database.
	$to_unset = array();
	$to_fix = array();
	foreach ($smiley_files as $key => $smiley_file)
	{
		$smiley_name = pathinfo($smiley_file, PATHINFO_FILENAME);

		// A brand new one
		if (empty($existing_smileys[$smiley_name]))
			continue;

		// A file with this name is already being used for at least one smiley, so we have more work to do...
		foreach ($existing_smileys[$smiley_name] as $existing_id => $existing_sets)
		{
			$to_unset[$key][$existing_id] = false;

			sort($existing_sets);

			// Already done
			if ($existing_sets === $known_sets)
				$to_unset[$key][$existing_id] = true;

			// Used in some sets but not others
			else
			{
				// Do the other sets have some other file already defined?
				foreach ($existing_smileys as $file => $info)
				{
					foreach ($info as $info_id => $info_sets)
					{
						if ($existing_id == $info_id)
							$existing_sets = array_unique(array_merge($existing_sets, $info_sets));
					}
				}
				sort($existing_sets);

				// If every set already has a file for this smiley, we can skip it
				if ($known_sets == $existing_sets)
					$to_unset[$key][$existing_id] = true;

				// Need to add the file for these sets
				else
					$to_fix[$key][$existing_id] = array_diff($known_sets, $existing_sets);
			}
		}
	}

	// Fix any sets with missing files
	// This part handles files for pre-existing smileys in a newly created smiley set
	$inserts = array();
	foreach ($to_fix as $key => $ids)
	{
		foreach ($ids as $id_smiley => $sets_missing)
		{
			// Find the file we need to copy to the other sets
			if (file_exists($modSettings['smileys_dir'] . '/' . $smileyPath . '/' . $smiley_files[$key]))
				$p = $smileyPath;
			else
			{
				foreach (array_diff($known_sets, $sets_missing) as $set)
				{
					if (file_exists($modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_files[$key]))
					{
						$p = $set;
						break;
					}
				}
			}

			foreach ($sets_missing as $set)
			{
				if ($set !== $p)
				{
					// Copy the file into the set's folder
					copy($modSettings['smileys_dir'] . '/' . $p . '/' . $smiley_files[$key], $modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_files[$key]);
					smf_chmod($modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_files[$key], 0644);
				}

				// Double-check that everything went as expected
				if (!file_exists($modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_files[$key]))
					continue;

				// Update the database
				$inserts[] = array($id_smiley, $set, $smiley_files[$key]);

				// This isn't a new smiley
				$to_unset[$key][$id_smiley] = true;
			}
		}
	}

	// Remove anything that isn't actually new from our list of files
	foreach ($to_unset as $key => $ids)
	{
		if (array_reduce($ids, function ($carry, $item) { return $carry * $item; }, true) == true)
			unset($smiley_files[$key]);
	}

	// We only create brand new smileys if asked.
	if (empty($create))
		$smiley_files = array();

	// New smileys go at the end of the list
	$request = $smcFunc['db_query']('', '
		SELECT MAX(smiley_order)
		FROM {db_prefix}smileys
		WHERE hidden = {int:postform}
			AND smiley_row = {int:first_row}',
		array(
			'postform' => 0,
			'first_row' => 0,
		)
	);
	list ($smiley_order) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// This part handles brand new smileys that don't exist in any set
	$new_smileys = array();
	foreach ($smiley_files as $key => $smiley_file)
	{
		// Ensure every set has a file to use for the new smiley
		foreach ($known_sets as $set)
		{
			unset($basename);

			if ($smileyPath != $set)
			{
				// Check whether any similarly named files exist in the other set's directory
				$similar_files = glob($modSettings['smileys_dir'] . '/' . $set . '/' . pathinfo($smiley_file, PATHINFO_FILENAME) . '.{' . implode(',', $allowedTypes) . '}', GLOB_BRACE);

				// If there's a similarly named file already there, use it
				if (!empty($similar_files))
				{
					// Prefer an exact match if there is one
					foreach ($similar_files as $similar_file)
					{
						if (basename($similar_file) == $smiley_file)
							$basename = $smiley_file;
					}

					// Same name, different extension
					if (empty($basename))
						$basename = basename(reset($similar_files));
				}
				// Otherwise, copy the image to the other set's directory
				else
				{
					copy($modSettings['smileys_dir'] . '/' . $smileyPath . '/' . $smiley_file, $modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_file);
					smf_chmod($modSettings['smileys_dir'] . '/' . $set . '/' . $smiley_file, 0644);

					$basename = $smiley_file;
				}

				// Double-check that everything went as expected
				if (empty($basename) || !file_exists($modSettings['smileys_dir'] . '/' . $set . '/' . $basename))
					continue;
			}
			else
				$basename = $smiley_file;

			$new_smileys[$key]['files'][$set] = $basename;
		}

		$new_smileys[$key]['info'] = array(':' . pathinfo($smiley_file, PATHINFO_FILENAME) . ':', pathinfo($smiley_file, PATHINFO_FILENAME), 0, ++$smiley_order);
	}

	// Add the info for any new smileys to the database
	foreach ($new_smileys as $new_smiley)
	{
		$new_id_smiley = $smcFunc['db_insert']('',
			'{db_prefix}smileys',
			array(
				'code' => 'string-30', 'description' => 'string-80', 'smiley_row' => 'int', 'smiley_order' => 'int',
			),
			$new_smiley['info'],
			array('id_smiley'),
			1
		);

		// We'll also need to add filename info to the smiley_files table
		foreach ($new_smiley['files'] as $set => $filename)
			$inserts[] = array($new_id_smiley, $set, $filename);
	}

	// Finally, update the smiley_files table with all our new files
	if (!empty($inserts))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}smiley_files',
			array(
				'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
			),
			$inserts,
			array('id_smiley', 'smiley_set')
		);

		foreach ($known_sets as $set)
		{
			cache_put_data('parsing_smileys_' . $set, null, 480);
			cache_put_data('posting_smileys_' . $set, null, 480);
		}
	}
}

/**
 * Handles editing message icons
 */
function EditMessageIcons()
{
	global $context, $settings, $txt;
	global $smcFunc, $scripturl, $sourcedir;

	// Get a list of icons.
	$context['icons'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
		FROM {db_prefix}message_icons AS m
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE ({query_see_board} OR b.id_board IS NULL)
		ORDER BY m.icon_order',
		array(
		)
	);
	$last_icon = 0;
	$trueOrder = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['icons'][$row['id_icon']] = array(
			'id' => $row['id_icon'],
			'title' => $row['title'],
			'filename' => $row['filename'],
			'image_url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $row['filename'] . '.png') ? 'actual_images_url' : 'default_images_url'] . '/post/' . $row['filename'] . '.png',
			'board_id' => $row['id_board'],
			'board' => empty($row['board_name']) ? $txt['icons_edit_icons_all_boards'] : $row['board_name'],
			'order' => $row['icon_order'],
			'true_order' => $trueOrder++,
			'after' => $last_icon,
		);
		$last_icon = $row['id_icon'];
	}
	$smcFunc['db_free_result']($request);

	// Submitting a form?
	if (isset($_POST['icons_save']) || isset($_POST['delete']))
	{
		checkSession();

		// Deleting icons?
		if (isset($_POST['delete']) && !empty($_POST['checked_icons']))
		{
			$deleteIcons = array();
			foreach ($_POST['checked_icons'] as $icon)
				$deleteIcons[] = (int) $icon;

			// Do the actual delete!
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}message_icons
				WHERE id_icon IN ({array_int:icon_list})',
				array(
					'icon_list' => $deleteIcons,
				)
			);
		}
		// Editing/Adding an icon?
		elseif ($context['sub_action'] == 'editicon' && isset($_GET['icon']))
		{
			$_GET['icon'] = (int) $_GET['icon'];

			// Do some preperation with the data... like check the icon exists *somewhere*
			if (strpos($_POST['icon_filename'], '.png') !== false)
				$_POST['icon_filename'] = substr($_POST['icon_filename'], 0, -4);
			if (!file_exists($settings['default_theme_dir'] . '/images/post/' . $_POST['icon_filename'] . '.png'))
				fatal_lang_error('icon_not_found');
			// There is a 16 character limit on message icons...
			elseif (strlen($_POST['icon_filename']) > 16)
				fatal_lang_error('icon_name_too_long');
			elseif ($_POST['icon_location'] == $_GET['icon'] && !empty($_GET['icon']))
				fatal_lang_error('icon_after_itself');

			// First do the sorting... if this is an edit reduce the order of everything after it by one ;)
			if ($_GET['icon'] != 0)
			{
				$oldOrder = $context['icons'][$_GET['icon']]['true_order'];
				foreach ($context['icons'] as $id => $data)
					if ($data['true_order'] > $oldOrder)
						$context['icons'][$id]['true_order']--;
			}

			// If there are no existing icons and this is a new one, set the id to 1 (mainly for non-mysql)
			if (empty($_GET['icon']) && empty($context['icons']))
				$_GET['icon'] = 1;

			// Get the new order.
			$newOrder = $_POST['icon_location'] == 0 ? 0 : $context['icons'][$_POST['icon_location']]['true_order'] + 1;
			// Do the same, but with the one that used to be after this icon, done to avoid conflict.
			foreach ($context['icons'] as $id => $data)
				if ($data['true_order'] >= $newOrder)
					$context['icons'][$id]['true_order']++;

			// Finally set the current icon's position!
			$context['icons'][$_GET['icon']]['true_order'] = $newOrder;

			// Simply replace the existing data for the other bits.
			$context['icons'][$_GET['icon']]['title'] = $_POST['icon_description'];
			$context['icons'][$_GET['icon']]['filename'] = $_POST['icon_filename'];
			$context['icons'][$_GET['icon']]['board_id'] = (int) $_POST['icon_board'];

			// Do a huge replace ;)
			$iconInsert = array();
			$iconInsert_new = array();
			foreach ($context['icons'] as $id => $icon)
			{
				if ($id != 0)
				{
					$iconInsert[] = array($id, $icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
				}
				else
				{
					$iconInsert_new[] = array($icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
				}
			}

			$smcFunc['db_insert']('replace',
				'{db_prefix}message_icons',
				array('id_icon' => 'int', 'id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'),
				$iconInsert,
				array('id_icon')
			);

			if (!empty($iconInsert_new))
			{
				$smcFunc['db_insert']('insert',
					'{db_prefix}message_icons',
					array('id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'),
					$iconInsert_new,
					array('id_icon')
				);
			}
		}

		// Unless we're adding a new thing, we'll escape
		if (!isset($_POST['add']))
			redirectexit('action=admin;area=smileys;sa=editicons');
	}

	$context[$context['admin_menu_name']]['current_subsection'] = 'editicons';

	$listOptions = array(
		'id' => 'message_icon_list',
		'title' => $txt['icons_edit_message_icons'],
		'base_href' => $scripturl . '?action=admin;area=smileys;sa=editicons',
		'get_items' => array(
			'function' => 'list_getMessageIcons',
		),
		'no_items_label' => $txt['icons_no_entries'],
		'columns' => array(
			'icon' => array(
				'data' => array(
					'function' => function($rowData) use ($settings, $smcFunc)
					{
						$images_url = $settings[file_exists(sprintf('%1$s/images/post/%2$s.png', $settings['theme_dir'], $rowData['filename'])) ? 'actual_images_url' : 'default_images_url'];
						return sprintf('<img src="%1$s/post/%2$s.png" alt="%3$s">', $images_url, $rowData['filename'], $smcFunc['htmlspecialchars']($rowData['title']));
					},
					'class' => 'centercol',
				),
			),
			'filename' => array(
				'header' => array(
					'value' => $txt['smileys_filename'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s.png',
						'params' => array(
							'filename' => true,
						),
					),
				),
			),
			'description' => array(
				'header' => array(
					'value' => $txt['smileys_description'],
				),
				'data' => array(
					'db_htmlsafe' => 'title',
				),
			),
			'board' => array(
				'header' => array(
					'value' => $txt['icons_board'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return empty($rowData['board_name']) ? $txt['icons_edit_icons_all_boards'] : $rowData['board_name'];
					},
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['smileys_modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=editicon;icon=%1$s">' . $txt['smileys_modify'] . '</a>',
						'params' => array(
							'id_icon' => false,
						),
					),
					'class' => 'centercol',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="checked_icons[]" value="%1$d">',
						'params' => array(
							'id_icon' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=smileys;sa=editicons',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" class="button"> <a class="button" href="' . $scripturl . '?action=admin;area=smileys;sa=editicon">' . $txt['icons_add_new'] . '</a>',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// If we're adding/editing an icon we'll need a list of boards
	if ($context['sub_action'] == 'editicon' || isset($_POST['add']))
	{
		// Force the sub_template just in case.
		$context['sub_template'] = 'editicon';

		$context['new_icon'] = !isset($_GET['icon']);

		// Get the properties of the current icon from the icon list.
		if (!$context['new_icon'])
			$context['icon'] = $context['icons'][$_GET['icon']];

		// Get a list of boards needed for assigning this icon to a specific board.
		$boardListOptions = array(
			'use_permissions' => true,
			'selected_board' => isset($context['icon']['board_id']) ? $context['icon']['board_id'] : 0,
		);
		require_once($sourcedir . '/Subs-MessageIndex.php');
		$context['categories'] = getBoardList($boardListOptions);
	}
}

/**
 * Callback function for createList().
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to display per page (not used here)
 * @param string $sort A string indicating how to sort the items (not used here)
 * @return array An array of information about message icons
 */
function list_getMessageIcons($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
		FROM {db_prefix}message_icons AS m
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE ({query_see_board} OR b.id_board IS NULL)
		ORDER BY m.icon_order',
		array()
	);

	$message_icons = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$message_icons[] = $row;
	$smcFunc['db_free_result']($request);

	return $message_icons;
}

?>