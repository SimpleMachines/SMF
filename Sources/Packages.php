<?php

/**
 * This file is the main Package Manager.
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
 * This is the notoriously defunct package manager..... :/.
 */
function Packages()
{
	global $txt, $sourcedir, $context;

	// @todo Remove this!
	if (isset($_GET['get']) || isset($_GET['pgdownload']))
	{
		require_once($sourcedir . '/PackageGet.php');
		return PackageGet();
	}

	isAllowedTo('admin_forum');

	// Load all the basic stuff.
	require_once($sourcedir . '/Subs-Package.php');
	loadLanguage('Packages');
	loadTemplate('Packages', 'admin');

	$context['page_title'] = $txt['package'];

	// Delegation makes the world... that is, the package manager go 'round.
	$subActions = array(
		'browse' => 'PackageBrowse',
		'remove' => 'PackageRemove',
		'list' => 'PackageList',
		'ftptest' => 'PackageFTPTest',
		'install' => 'PackageInstallTest',
		'install2' => 'PackageInstall',
		'uninstall' => 'PackageInstallTest',
		'uninstall2' => 'PackageInstall',
		'options' => 'PackageOptions',
		'perms' => 'PackagePermissions',
		'flush' => 'FlushInstall',
		'examine' => 'ExamineFile',
		'showoperations' => 'ViewOperations',
	);

	// Work out exactly who it is we are calling.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'browse';

	// Set up some tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['package_manager'],
		// @todo 'help' => 'registrations',
		'description' => $txt['package_manager_desc'],
		'tabs' => array(
			'browse' => array(
			),
			'packageget' => array(
				'description' => $txt['download_packages_desc'],
			),
			'perms' => array(
				'description' => $txt['package_file_perms_desc'],
			),
			'options' => array(
				'description' => $txt['package_install_options_desc'],
			),
		),
	);

	if ($context['sub_action'] == 'browse')
		loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');

	call_integration_hook('integrate_manage_packages', array(&$subActions));

	// Call the function we're handing control to.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * Test install a package.
 */
function PackageInstallTest()
{
	global $boarddir, $txt, $context, $scripturl, $sourcedir, $packagesdir, $modSettings, $smcFunc, $settings;

	// You have to specify a file!!
	if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '')
		redirectexit('action=admin;area=packages');
	$context['filename'] = preg_replace('~[\.]+~', '.', $_REQUEST['package']);

	// Do we have an existing id, for uninstalls and the like.
	$context['install_id'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

	require_once($sourcedir . '/Subs-Package.php');

	// Load up the package FTP information?
	create_chmod_control();

	// Make sure temp directory exists and is empty.
	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp', false);

	if (!mktree($packagesdir . '/temp', 0755))
	{
		deltree($packagesdir . '/temp', false);
		if (!mktree($packagesdir . '/temp', 0777))
		{
			deltree($packagesdir . '/temp', false);
			create_chmod_control(array($packagesdir . '/temp/delme.tmp'), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=' . $_REQUEST['sa'] . ';package=' . $_REQUEST['package'], 'crash_on_error' => true));

			deltree($packagesdir . '/temp', false);
			if (!mktree($packagesdir . '/temp', 0777))
				fatal_lang_error('package_cant_download', false);
		}
	}

	$context['uninstalling'] = $_REQUEST['sa'] == 'uninstall';

	// Change our last link tree item for more information on this Packages area.
	$context['linktree'][count($context['linktree']) - 1] = array(
		'url' => $scripturl . '?action=admin;area=packages;sa=browse',
		'name' => $context['uninstalling'] ? $txt['package_uninstall_actions'] : $txt['install_actions']
	);
	$context['page_title'] .= ' - ' . ($context['uninstalling'] ? $txt['package_uninstall_actions'] : $txt['install_actions']);

	$context['sub_template'] = 'view_package';

	if (!file_exists($packagesdir . '/' . $context['filename']))
	{
		deltree($packagesdir . '/temp');
		fatal_lang_error('package_no_file', false);
	}

	// Extract the files so we can get things like the readme, etc.
	if (is_file($packagesdir . '/' . $context['filename']))
	{
		$context['extracted_files'] = read_tgz_file($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');

		if ($context['extracted_files'] && !file_exists($packagesdir . '/temp/package-info.xml'))
			foreach ($context['extracted_files'] as $file)
				if (basename($file['filename']) == 'package-info.xml')
				{
					$context['base_path'] = dirname($file['filename']) . '/';
					break;
				}

		if (!isset($context['base_path']))
			$context['base_path'] = '';
	}
	elseif (is_dir($packagesdir . '/' . $context['filename']))
	{
		copytree($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');
		$context['extracted_files'] = listtree($packagesdir . '/temp');
		$context['base_path'] = '';
	}
	else
		fatal_lang_error('no_access', false);

	// Load up any custom themes we may want to install into...
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'known_theme_list' => explode(',', $modSettings['knownThemes']),
			'default_theme' => 1,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$theme_paths = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	// Get the package info...
	$packageInfo = getPackageInfo($context['filename']);

	if (!is_array($packageInfo))
		fatal_lang_error($packageInfo);

	$packageInfo['filename'] = $context['filename'];
	$context['package_name'] = isset($packageInfo['name']) ? $packageInfo['name'] : $context['filename'];

	// Set the type of extraction...
	$context['extract_type'] = isset($packageInfo['type']) ? $packageInfo['type'] : 'modification';

	// The mod isn't installed.... unless proven otherwise.
	$context['is_installed'] = false;

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
			'current_package' => $packageInfo['id'],
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$old_themes = explode(',', $row['themes_installed']);
		$old_version = $row['version'];
		$db_changes = empty($row['db_changes']) ? array() : $smcFunc['json_decode']($row['db_changes'], true);
	}
	$smcFunc['db_free_result']($request);

	$context['database_changes'] = array();
	if (isset($packageInfo['uninstall']['database']))
		$context['database_changes'][] = sprintf($txt['package_db_code'], $packageInfo['uninstall']['database']);
	if (!empty($db_changes))
	{
		foreach ($db_changes as $change)
		{
			if (isset($change[2]) && isset($txt['package_db_' . $change[0]]))
				$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1], $change[2]);
			elseif (isset($txt['package_db_' . $change[0]]))
				$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1]);
			else
				$context['database_changes'][] = $change[0] . '-' . $change[1] . (isset($change[2]) ? '-' . $change[2] : '');
		}
	}

	// Uninstalling?
	if ($context['uninstalling'])
	{
		// Wait, it's not installed yet!
		if (!isset($old_version) && $context['uninstalling'])
		{
			deltree($packagesdir . '/temp');
			fatal_lang_error('package_cant_uninstall', false);
		}

		$actions = parsePackageInfo($packageInfo['xml'], true, 'uninstall');

		// Gadzooks!  There's no uninstaller at all!?
		if (empty($actions))
		{
			deltree($packagesdir . '/temp');
			fatal_lang_error('package_uninstall_cannot', false);
		}

		// Can't edit the custom themes it's edited if you're uninstalling, they must be removed.
		$context['themes_locked'] = true;

		// Only let them uninstall themes it was installed into.
		foreach ($theme_paths as $id => $data)
			if ($id != 1 && !in_array($id, $old_themes))
				unset($theme_paths[$id]);
	}
	elseif (isset($old_version) && $old_version != $packageInfo['version'])
	{
		// Look for an upgrade...
		$actions = parsePackageInfo($packageInfo['xml'], true, 'upgrade', $old_version);

		// There was no upgrade....
		if (empty($actions))
			$context['is_installed'] = true;
		else
		{
			// Otherwise they can only upgrade themes from the first time around.
			foreach ($theme_paths as $id => $data)
				if ($id != 1 && !in_array($id, $old_themes))
					unset($theme_paths[$id]);
		}
	}
	elseif (isset($old_version) && $old_version == $packageInfo['version'])
		$context['is_installed'] = true;

	if (!isset($old_version) || $context['is_installed'])
		$actions = parsePackageInfo($packageInfo['xml'], true, 'install');

	$context['actions'] = array();
	$context['ftp_needed'] = false;
	$context['has_failure'] = false;
	$chmod_files = array();

	// no actions found, return so we can display an error
	if (empty($actions))
		return;

	// This will hold data about anything that can be installed in other themes.
	$themeFinds = array(
		'candidates' => array(),
		'other_themes' => array(),
	);

	// Now prepare things for the template.
	foreach ($actions as $action)
	{
		// Not failed until proven otherwise.
		$failed = false;
		$thisAction = array();

		if ($action['type'] == 'chmod')
		{
			$chmod_files[] = $action['filename'];
			continue;
		}
		elseif ($action['type'] == 'readme' || $action['type'] == 'license')
		{
			$type = 'package_' . $action['type'];
			if (file_exists($packagesdir . '/temp/' . $context['base_path'] . $action['filename']))
				$context[$type] = $smcFunc['htmlspecialchars'](trim(file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename']), "\n\r"));
			elseif (file_exists($action['filename']))
				$context[$type] = $smcFunc['htmlspecialchars'](trim(file_get_contents($action['filename']), "\n\r"));

			if (!empty($action['parse_bbc']))
			{
				require_once($sourcedir . '/Subs-Post.php');
				$context[$type] = preg_replace('~\[[/]?html\]~i', '', $context[$type]);
				preparsecode($context[$type]);
				$context[$type] = parse_bbc($context[$type]);
			}
			else
				$context[$type] = nl2br($context[$type]);

			continue;
		}
		// Don't show redirects.
		elseif ($action['type'] == 'redirect')
			continue;
		elseif ($action['type'] == 'error')
		{
			$context['has_failure'] = true;
			if (isset($action['error_msg']) && isset($action['error_var']))
				$context['failure_details'] = sprintf($txt['package_will_fail_' . $action['error_msg']], $action['error_var']);
			elseif (isset($action['error_msg']))
				$context['failure_details'] = isset($txt['package_will_fail_' . $action['error_msg']]) ? $txt['package_will_fail_' . $action['error_msg']] : $action['error_msg'];
		}
		elseif ($action['type'] == 'modification')
		{
			if (!file_exists($packagesdir . '/temp/' . $context['base_path'] . $action['filename']))
			{
				$context['has_failure'] = true;

				$context['actions'][] = array(
					'type' => $txt['execute_modification'],
					'action' => $smcFunc['htmlspecialchars'](strtr($action['filename'], array($boarddir => '.'))),
					'description' => $txt['package_action_missing'],
					'failed' => true,
				);
			}
			else
			{
				if ($action['boardmod'])
					$mod_actions = parseBoardMod(@file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename']), true, $action['reverse'], $theme_paths);
				else
					$mod_actions = parseModification(@file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename']), true, $action['reverse'], $theme_paths);

				if (count($mod_actions) == 1 && isset($mod_actions[0]) && $mod_actions[0]['type'] == 'error' && $mod_actions[0]['filename'] == '-')
					$mod_actions[0]['filename'] = $action['filename'];

				foreach ($mod_actions as $key => $mod_action)
				{
					// Lets get the last section of the file name.
					if (isset($mod_action['filename']) && substr($mod_action['filename'], -13) != '.template.php')
						$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
					elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
						$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php' . '||' . $action['filename']);
					else
						$actual_filename = $key;

					if ($mod_action['type'] == 'opened')
						$failed = false;
					elseif ($mod_action['type'] == 'failure')
					{
						if (empty($mod_action['is_custom']))
							$context['has_failure'] = true;
						$failed = true;
					}
					elseif ($mod_action['type'] == 'chmod')
					{
						$chmod_files[] = $mod_action['filename'];
					}
					elseif ($mod_action['type'] == 'saved')
					{
						if (!empty($mod_action['is_custom']))
						{
							if (!isset($context['theme_actions'][$mod_action['is_custom']]))
								$context['theme_actions'][$mod_action['is_custom']] = array(
									'name' => $theme_paths[$mod_action['is_custom']]['name'],
									'actions' => array(),
									'has_failure' => $failed,
								);
							else
								$context['theme_actions'][$mod_action['is_custom']]['has_failure'] |= $failed;

							$context['theme_actions'][$mod_action['is_custom']]['actions'][$actual_filename] = array(
								'type' => $txt['execute_modification'],
								'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
								'description' => $failed ? $txt['package_action_failure'] : $txt['package_action_success'],
								'failed' => $failed,
							);
						}
						elseif (!isset($context['actions'][$actual_filename]))
						{
							$context['actions'][$actual_filename] = array(
								'type' => $txt['execute_modification'],
								'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
								'description' => $failed ? $txt['package_action_failure'] : $txt['package_action_success'],
								'failed' => $failed,
							);
						}
						else
						{
							$context['actions'][$actual_filename]['failed'] |= $failed;
							$context['actions'][$actual_filename]['description'] = $context['actions'][$actual_filename]['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'];
						}
					}
					elseif ($mod_action['type'] == 'skipping')
					{
						$context['actions'][$actual_filename] = array(
							'type' => $txt['execute_modification'],
							'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
							'description' => $txt['package_action_skipping']
						);
					}
					elseif ($mod_action['type'] == 'missing' && empty($mod_action['is_custom']))
					{
						$context['has_failure'] = true;
						$context['actions'][$actual_filename] = array(
							'type' => $txt['execute_modification'],
							'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
							'description' => $txt['package_action_missing'],
							'failed' => true,
						);
					}
					elseif ($mod_action['type'] == 'error')
						$context['actions'][$actual_filename] = array(
							'type' => $txt['execute_modification'],
							'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
							'description' => $txt['package_action_error'],
							'failed' => true,
						);
				}

				// We need to loop again just to get the operations down correctly.
				foreach ($mod_actions as $operation_key => $mod_action)
				{
					// Lets get the last section of the file name.
					if (isset($mod_action['filename']) && substr($mod_action['filename'], -13) != '.template.php')
						$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
					elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
						$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php' . '||' . $action['filename']);
					else
						$actual_filename = $key;

					// We just need it for actual parse changes.
					if (!in_array($mod_action['type'], array('error', 'result', 'opened', 'saved', 'end', 'missing', 'skipping', 'chmod')))
					{
						if (empty($mod_action['is_custom']))
							$context['actions'][$actual_filename]['operations'][] = array(
								'type' => $txt['execute_modification'],
								'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
								'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
								'position' => $mod_action['position'],
								'operation_key' => $operation_key,
								'filename' => $action['filename'],
								'is_boardmod' => $action['boardmod'],
								'failed' => $mod_action['failed'],
								'ignore_failure' => !empty($mod_action['ignore_failure']),
							);

						// Themes are under the saved type.
						if (isset($mod_action['is_custom']) && isset($context['theme_actions'][$mod_action['is_custom']]))
							$context['theme_actions'][$mod_action['is_custom']]['actions'][$actual_filename]['operations'][] = array(
								'type' => $txt['execute_modification'],
								'action' => $smcFunc['htmlspecialchars'](strtr($mod_action['filename'], array($boarddir => '.'))),
								'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
								'position' => $mod_action['position'],
								'operation_key' => $operation_key,
								'filename' => $action['filename'],
								'is_boardmod' => $action['boardmod'],
								'failed' => $mod_action['failed'],
								'ignore_failure' => !empty($mod_action['ignore_failure']),
							);
					}
				}
			}
		}
		elseif ($action['type'] == 'code')
		{
			$thisAction = array(
				'type' => $txt['execute_code'],
				'action' => $smcFunc['htmlspecialchars']($action['filename']),
			);
		}
		elseif ($action['type'] == 'database' && !$context['uninstalling'])
		{
			$thisAction = array(
				'type' => $txt['execute_database_changes'],
				'action' => $smcFunc['htmlspecialchars']($action['filename']),
			);
		}
		elseif (in_array($action['type'], array('create-dir', 'create-file')))
		{
			$thisAction = array(
				'type' => $txt['package_create'] . ' ' . ($action['type'] == 'create-dir' ? $txt['package_tree'] : $txt['package_file']),
				'action' => $smcFunc['htmlspecialchars'](strtr($action['destination'], array($boarddir => '.')))
			);
		}
		elseif ($action['type'] == 'hook')
		{
			$action['description'] = !isset($action['hook'], $action['function']) ? $txt['package_action_failure'] : $txt['package_action_success'];

			if (!isset($action['hook'], $action['function']))
				$context['has_failure'] = true;

			$thisAction = array(
				'type' => $action['reverse'] ? $txt['execute_hook_remove'] : $txt['execute_hook_add'],
				'action' => sprintf($txt['execute_hook_action' . ($action['reverse'] ? '_inverse' : '')], $smcFunc['htmlspecialchars']($action['hook'])),
			);
		}
		elseif ($action['type'] == 'credits')
		{
			$thisAction = array(
				'type' => $txt['execute_credits_add'],
				'action' => sprintf($txt['execute_credits_action'], $smcFunc['htmlspecialchars']($action['title'])),
			);
		}
		elseif ($action['type'] == 'requires')
		{
			$installed = false;
			$version = true;

			// package missing required values?
			if (!isset($action['id']))
				$context['has_failure'] = true;
			else
			{
				// See if this dependancy is installed
				$request = $smcFunc['db_query']('', '
					SELECT version
					FROM {db_prefix}log_packages
					WHERE package_id = {string:current_package}
						AND install_state != {int:not_installed}
					ORDER BY time_installed DESC
					LIMIT 1',
					array(
						'not_installed' => 0,
						'current_package' => $action['id'],
					)
				);
				$installed = ($smcFunc['db_num_rows']($request) !== 0);
				if ($installed)
					list ($version) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// do a version level check (if requested) in the most basic way
				$version = (isset($action['version']) ? $version == $action['version'] : true);
			}

			// Set success or failure information
			$action['description'] = ($installed && $version) ? $txt['package_action_success'] : $txt['package_action_failure'];
			$context['has_failure'] = !($installed && $version);

			$thisAction = array(
				'type' => $txt['package_requires'],
				'action' => $txt['package_check_for'] . ' ' . $action['id'] . (isset($action['version']) ? (' / ' . ($version ? $action['version'] : '<span class="error">' . $action['version'] . '</span>')) : ''),
			);
		}
		elseif (in_array($action['type'], array('require-dir', 'require-file')))
		{
			// Do this one...
			$thisAction = array(
				'type' => $txt['package_extract'] . ' ' . ($action['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
				'action' => $smcFunc['htmlspecialchars'](strtr($action['destination'], array($boarddir => '.')))
			);

			// Could this be theme related?
			if (!empty($action['unparsed_destination']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_destination'], $matches))
			{
				// Is the action already stated?
				$theme_action = !empty($action['theme_action']) && in_array($action['theme_action'], array('no', 'yes', 'auto')) ? $action['theme_action'] : 'auto';
				// If it's not auto do we think we have something we can act upon?
				if ($theme_action != 'auto' && !in_array($matches[1], array('languagedir', 'languages_dir', 'imagesdir', 'themedir')))
					$theme_action = '';
				// ... or if it's auto do we even want to do anything?
				elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir')
					$theme_action = '';

				// So, we still want to do something?
				if ($theme_action != '')
					$themeFinds['candidates'][] = $action;
				// Otherwise is this is going into another theme record it.
				elseif ($matches[1] == 'themes_dir')
					$themeFinds['other_themes'][] = strtolower(strtr(parse_path($action['unparsed_destination']), array('\\' => '/')) . '/' . basename($action['filename']));
			}
		}
		elseif (in_array($action['type'], array('move-dir', 'move-file')))
			$thisAction = array(
				'type' => $txt['package_move'] . ' ' . ($action['type'] == 'move-dir' ? $txt['package_tree'] : $txt['package_file']),
				'action' => $smcFunc['htmlspecialchars'](strtr($action['source'], array($boarddir => '.'))) . ' => ' . $smcFunc['htmlspecialchars'](strtr($action['destination'], array($boarddir => '.')))
			);
		elseif (in_array($action['type'], array('remove-dir', 'remove-file')))
		{
			$thisAction = array(
				'type' => $txt['package_delete'] . ' ' . ($action['type'] == 'remove-dir' ? $txt['package_tree'] : $txt['package_file']),
				'action' => $smcFunc['htmlspecialchars'](strtr($action['filename'], array($boarddir => '.')))
			);

			// Could this be theme related?
			if (!empty($action['unparsed_filename']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_filename'], $matches))
			{
				// Is the action already stated?
				$theme_action = !empty($action['theme_action']) && in_array($action['theme_action'], array('no', 'yes', 'auto')) ? $action['theme_action'] : 'auto';
				$action['unparsed_destination'] = $action['unparsed_filename'];

				// If it's not auto do we think we have something we can act upon?
				if ($theme_action != 'auto' && !in_array($matches[1], array('languagedir', 'languages_dir', 'imagesdir', 'themedir')))
					$theme_action = '';
				// ... or if it's auto do we even want to do anything?
				elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir')
					$theme_action = '';

				// So, we still want to do something?
				if ($theme_action != '')
					$themeFinds['candidates'][] = $action;
				// Otherwise is this is going into another theme record it.
				elseif ($matches[1] == 'themes_dir')
					$themeFinds['other_themes'][] = strtolower(strtr(parse_path($action['unparsed_filename']), array('\\' => '/')) . '/' . basename($action['filename']));
			}
		}

		if (empty($thisAction))
			continue;

		if (!in_array($action['type'], array('hook', 'credits')))
		{
			if ($context['uninstalling'])
				$file = in_array($action['type'], array('remove-dir', 'remove-file')) ? $action['filename'] : $packagesdir . '/temp/' . $context['base_path'] . $action['filename'];
			else
				$file = $packagesdir . '/temp/' . $context['base_path'] . $action['filename'];
		}

		// Don't fail if a file/directory we're trying to create doesn't exist...
		if (isset($action['filename']) && !file_exists($file) && !in_array($action['type'], array('create-dir', 'create-file')))
		{
			$context['has_failure'] = true;

			$thisAction += array(
				'description' => $txt['package_action_missing'],
				'failed' => true,
			);
		}

		// @todo None given?
		if (empty($thisAction['description']))
			$thisAction['description'] = isset($action['description']) ? $action['description'] : '';

		$context['actions'][] = $thisAction;
	}

	// Have we got some things which we might want to do "multi-theme"?
	if (!empty($themeFinds['candidates']))
	{
		foreach ($themeFinds['candidates'] as $action_data)
		{
			// Get the part of the file we'll be dealing with.
			preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir)(\\|/)*(.+)*~i', $action_data['unparsed_destination'], $matches);

			if ($matches[1] == 'imagesdir')
				$path = '/' . basename($settings['default_images_url']);
			elseif ($matches[1] == 'languagedir' || $matches[1] == 'languages_dir')
				$path = '/languages';
			else
				$path = '';

			if (!empty($matches[3]))
				$path .= $matches[3];

			if (!$context['uninstalling'])
				$path .= '/' . basename($action_data['filename']);

			// Loop through each custom theme to note it's candidacy!
			foreach ($theme_paths as $id => $theme_data)
			{
				if (isset($theme_data['theme_dir']) && $id != 1)
				{
					$real_path = $theme_data['theme_dir'] . $path;

					// Confirm that we don't already have this dealt with by another entry.
					if (!in_array(strtolower(strtr($real_path, array('\\' => '/'))), $themeFinds['other_themes']))
					{
						// Check if we will need to chmod this.
						if (!mktree(dirname($real_path), false))
						{
							$temp = dirname($real_path);
							while (!file_exists($temp) && strlen($temp) > 1)
								$temp = dirname($temp);
							$chmod_files[] = $temp;
						}

						if ($action_data['type'] == 'require-dir' && !is_writable($real_path) && (file_exists($real_path) || !is_writable(dirname($real_path))))
							$chmod_files[] = $real_path;

						if (!isset($context['theme_actions'][$id]))
							$context['theme_actions'][$id] = array(
								'name' => $theme_data['name'],
								'actions' => array(),
							);

						if ($context['uninstalling'])
							$context['theme_actions'][$id]['actions'][] = array(
								'type' => $txt['package_delete'] . ' ' . ($action_data['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
								'action' => strtr($real_path, array('\\' => '/', $boarddir => '.')),
								'description' => '',
								'value' => base64_encode($smcFunc['json_encode'](array('type' => $action_data['type'], 'orig' => $action_data['filename'], 'future' => $real_path, 'id' => $id))),
								'not_mod' => true,
							);
						else
							$context['theme_actions'][$id]['actions'][] = array(
								'type' => $txt['package_extract'] . ' ' . ($action_data['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
								'action' => strtr($real_path, array('\\' => '/', $boarddir => '.')),
								'description' => '',
								'value' => base64_encode($smcFunc['json_encode'](array('type' => $action_data['type'], 'orig' => $action_data['destination'], 'future' => $real_path, 'id' => $id))),
								'not_mod' => true,
							);
					}
				}
			}
		}
	}

	// Trash the cache... which will also check permissions for us!
	package_flush_cache(true);

	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp');

	if (!empty($chmod_files))
	{
		$ftp_status = create_chmod_control($chmod_files);
		$context['ftp_needed'] = !empty($ftp_status['files']['notwritable']) && !empty($context['package_ftp']);
	}

	$context['post_url'] = $scripturl . '?action=admin;area=packages;sa=' . ($context['uninstalling'] ? 'uninstall' : 'install') . ($context['ftp_needed'] ? '' : '2') . ';package=' . $context['filename'] . ';pid=' . $context['install_id'];
	checkSubmitOnce('register');
}

/**
 * Apply another type of (avatar, language, etc.) package.
 */
function PackageInstall()
{
	global $txt, $context, $boardurl, $scripturl, $sourcedir, $packagesdir, $modSettings;
	global $user_info, $smcFunc;

	// Make sure we don't install this mod twice.
	checkSubmitOnce('check');
	checkSession();

	// If there's no file, what are we installing?
	if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '')
		redirectexit('action=admin;area=packages');
	$context['filename'] = $_REQUEST['package'];

	// If this is an uninstall, we'll have an id.
	$context['install_id'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

	require_once($sourcedir . '/Subs-Package.php');

	// @todo Perhaps do it in steps, if necessary?

	$context['uninstalling'] = $_REQUEST['sa'] == 'uninstall2';

	// Set up the linktree for other.
	$context['linktree'][count($context['linktree']) - 1] = array(
		'url' => $scripturl . '?action=admin;area=packages;sa=browse',
		'name' => $context['uninstalling'] ? $txt['uninstall'] : $txt['extracting']
	);
	$context['page_title'] .= ' - ' . ($context['uninstalling'] ? $txt['uninstall'] : $txt['extracting']);

	$context['sub_template'] = 'extract_package';

	if (!file_exists($packagesdir . '/' . $context['filename']))
		fatal_lang_error('package_no_file', false);

	// Load up the package FTP information?
	create_chmod_control(array(), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=' . $_REQUEST['sa'] . ';package=' . $_REQUEST['package']));

	// Make sure temp directory exists and is empty!
	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp', false);
	else
		mktree($packagesdir . '/temp', 0777);

	// Let the unpacker do the work.
	if (is_file($packagesdir . '/' . $context['filename']))
	{
		$context['extracted_files'] = read_tgz_file($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');

		if (!file_exists($packagesdir . '/temp/package-info.xml'))
			foreach ($context['extracted_files'] as $file)
				if (basename($file['filename']) == 'package-info.xml')
				{
					$context['base_path'] = dirname($file['filename']) . '/';
					break;
				}

		if (!isset($context['base_path']))
			$context['base_path'] = '';
	}
	elseif (is_dir($packagesdir . '/' . $context['filename']))
	{
		copytree($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');
		$context['extracted_files'] = listtree($packagesdir . '/temp');
		$context['base_path'] = '';
	}
	else
		fatal_lang_error('no_access', false);

	// Are we installing this into any custom themes?
	$custom_themes = array(1);
	$known_themes = explode(',', $modSettings['knownThemes']);
	if (!empty($_POST['custom_theme']))
	{
		foreach ($_POST['custom_theme'] as $tid)
			if (in_array($tid, $known_themes))
				$custom_themes[] = (int) $tid;
	}

	// Now load up the paths of the themes that we need to know about.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme IN ({array_int:custom_themes})
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'custom_themes' => $custom_themes,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$theme_paths = array();
	$themes_installed = array(1);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];

	$smcFunc['db_free_result']($request);

	// Are there any theme copying that we want to take place?
	$context['theme_copies'] = array(
		'require-file' => array(),
		'require-dir' => array(),
	);
	if (!empty($_POST['theme_changes']))
	{
		foreach ($_POST['theme_changes'] as $change)
		{
			if (empty($change))
				continue;
			$theme_data = $smcFunc['json_decode'](base64_decode($change), true);
			if (empty($theme_data['type']))
				continue;

			$themes_installed[] = $theme_data['id'];
			$context['theme_copies'][$theme_data['type']][$theme_data['orig']][] = $theme_data['future'];
		}
	}

	// Get the package info...
	$packageInfo = getPackageInfo($context['filename']);
	if (!is_array($packageInfo))
		fatal_lang_error($packageInfo);

	$packageInfo['filename'] = $context['filename'];

	// Set the type of extraction...
	$context['extract_type'] = isset($packageInfo['type']) ? $packageInfo['type'] : 'modification';

	// Create a backup file to roll back to! (but if they do this more than once, don't run it a zillion times.)
	if (!empty($modSettings['package_make_full_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != $context['filename'] . ($context['uninstalling'] ? '$$' : '$')))
	{
		$_SESSION['last_backup_for'] = $context['filename'] . ($context['uninstalling'] ? '$$' : '$');
		$result = package_create_backup(($context['uninstalling'] ? 'backup_' : 'before_') . strtok($context['filename'], '.'));
		if (!$result)
			fatal_lang_error('could_not_package_backup', false);
	}

	// The mod isn't installed.... unless proven otherwise.
	$context['is_installed'] = false;

	// Is it actually installed?
	$request = $smcFunc['db_query']('', '
		SELECT version, themes_installed, db_changes
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $packageInfo['id'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$old_themes = explode(',', $row['themes_installed']);
		$old_version = $row['version'];
		$db_changes = empty($row['db_changes']) ? array() : $smcFunc['json_decode']($row['db_changes'], true);
	}
	$smcFunc['db_free_result']($request);

	// Wait, it's not installed yet!
	// @todo Replace with a better error message!
	if (!isset($old_version) && $context['uninstalling'])
	{
		deltree($packagesdir . '/temp');
		fatal_error('Hacker?', false);
	}
	// Uninstalling?
	elseif ($context['uninstalling'])
	{
		$install_log = parsePackageInfo($packageInfo['xml'], false, 'uninstall');

		// Gadzooks!  There's no uninstaller at all!?
		if (empty($install_log))
			fatal_lang_error('package_uninstall_cannot', false);

		// They can only uninstall from what it was originally installed into.
		foreach ($theme_paths as $id => $data)
			if ($id != 1 && !in_array($id, $old_themes))
				unset($theme_paths[$id]);
	}
	elseif (isset($old_version) && $old_version != $packageInfo['version'])
	{
		// Look for an upgrade...
		$install_log = parsePackageInfo($packageInfo['xml'], false, 'upgrade', $old_version);

		// There was no upgrade....
		if (empty($install_log))
			$context['is_installed'] = true;
		else
		{
			// Upgrade previous themes only!
			foreach ($theme_paths as $id => $data)
				if ($id != 1 && !in_array($id, $old_themes))
					unset($theme_paths[$id]);
		}
	}
	elseif (isset($old_version) && $old_version == $packageInfo['version'])
		$context['is_installed'] = true;

	if (!isset($old_version) || $context['is_installed'])
		$install_log = parsePackageInfo($packageInfo['xml'], false, 'install');

	$context['install_finished'] = false;

	// @todo Make a log of any errors that occurred and output them?

	if (!empty($install_log))
	{
		$failed_steps = array();
		$failed_count = 0;

		foreach ($install_log as $action)
		{
			$failed_count++;

			if ($action['type'] == 'modification' && !empty($action['filename']))
			{
				if ($action['boardmod'])
					$mod_actions = parseBoardMod(file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename']), false, $action['reverse'], $theme_paths);
				else
					$mod_actions = parseModification(file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename']), false, $action['reverse'], $theme_paths);

				// Any errors worth noting?
				foreach ($mod_actions as $key => $modAction)
				{
					if ($modAction['type'] == 'failure')
						$failed_steps[] = array(
							'file' => $modAction['filename'],
							'large_step' => $failed_count,
							'sub_step' => $key,
							'theme' => 1,
						);

					// Gather the themes we installed into.
					if (!empty($modAction['is_custom']))
						$themes_installed[] = $modAction['is_custom'];
				}
			}
			elseif ($action['type'] == 'code' && !empty($action['filename']))
			{
				// This is just here as reference for what is available.
				global $txt, $boarddir, $sourcedir, $modSettings, $context, $settings, $smcFunc;

				// Now include the file and be done with it ;).
				if (file_exists($packagesdir . '/temp/' . $context['base_path'] . $action['filename']))
					require($packagesdir . '/temp/' . $context['base_path'] . $action['filename']);
			}
			elseif ($action['type'] == 'credits')
			{
				// Time to build the billboard
				$credits_tag = array(
					'url' => $action['url'],
					'license' => $action['license'],
					'licenseurl' => $action['licenseurl'],
					'copyright' => $action['copyright'],
					'title' => $action['title'],
				);
			}
			elseif ($action['type'] == 'hook' && isset($action['hook'], $action['function']))
			{
				// Set the system to ignore hooks, but only if it wasn't changed before.
				if (!isset($context['ignore_hook_errors']))
					$context['ignore_hook_errors'] = true;

				if ($action['reverse'])
					remove_integration_function($action['hook'], $action['function'], true, $action['include_file'], $action['object']);
				else
					add_integration_function($action['hook'], $action['function'], true, $action['include_file'], $action['object']);
			}
			// Only do the database changes on uninstall if requested.
			elseif ($action['type'] == 'database' && !empty($action['filename']) && (!$context['uninstalling'] || !empty($_POST['do_db_changes'])))
			{
				// These can also be there for database changes.
				global $txt, $boarddir, $sourcedir, $modSettings, $context, $settings, $smcFunc;
				global $db_package_log;

				// We'll likely want the package specific database functionality!
				db_extend('packages');

				// Let the file work its magic ;)
				if (file_exists($packagesdir . '/temp/' . $context['base_path'] . $action['filename']))
					require($packagesdir . '/temp/' . $context['base_path'] . $action['filename']);
			}
			// Handle a redirect...
			elseif ($action['type'] == 'redirect' && !empty($action['redirect_url']))
			{
				$context['redirect_url'] = $action['redirect_url'];
				$context['redirect_text'] = !empty($action['filename']) && file_exists($packagesdir . '/temp/' . $context['base_path'] . $action['filename']) ? $smcFunc['htmlspecialchars'](file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $action['filename'])) : ($context['uninstalling'] ? $txt['package_uninstall_done'] : $txt['package_installed_done']);
				$context['redirect_timeout'] = empty($action['redirect_timeout']) ? 5 : (int) ceil($action['redirect_timeout'] / 1000);
				if (!empty($action['parse_bbc']))
				{
					require_once($sourcedir . '/Subs-Post.php');
					$context['redirect_text'] = preg_replace('~\[[/]?html\]~i', '', $context['redirect_text']);
					preparsecode($context['redirect_text']);
					$context['redirect_text'] = parse_bbc($context['redirect_text']);
				}

				// Parse out a couple of common urls.
				$urls = array(
					'$boardurl' => $boardurl,
					'$scripturl' => $scripturl,
					'$session_var' => $context['session_var'],
					'$session_id' => $context['session_id'],
				);

				$context['redirect_url'] = strtr($context['redirect_url'], $urls);
			}
		}

		package_flush_cache();

		// See if this is already installed, and change it's state as required.
		$request = $smcFunc['db_query']('', '
			SELECT package_id, install_state, db_changes
			FROM {db_prefix}log_packages
			WHERE install_state != {int:not_installed}
				AND package_id = {string:current_package}
				' . ($context['install_id'] ? ' AND id_install = {int:install_id} ' : '') . '
			ORDER BY time_installed DESC
			LIMIT 1',
			array(
				'not_installed' => 0,
				'install_id' => $context['install_id'],
				'current_package' => $packageInfo['id'],
			)
		);
		$is_upgrade = false;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Uninstalling?
			if ($context['uninstalling'])
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_packages
					SET install_state = {int:not_installed}, member_removed = {string:member_name},
						id_member_removed = {int:current_member}, time_removed = {int:current_time}
					WHERE package_id = {string:package_id}
						AND id_install = {int:install_id}',
					array(
						'current_member' => $user_info['id'],
						'not_installed' => 0,
						'current_time' => time(),
						'package_id' => $row['package_id'],
						'member_name' => $user_info['name'],
						'install_id' => $context['install_id'],
					)
				);
			}
			// Otherwise must be an upgrade.
			else
			{
				$is_upgrade = true;
				$old_db_changes = empty($row['db_changes']) ? array() : $smcFunc['json_decode']($row['db_changes'], true);

				// Mark the old version as uninstalled
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_packages
					SET install_state = {int:not_installed}, member_removed = {string:member_name},
						id_member_removed = {int:current_member}, time_removed = {int:current_time}
					WHERE package_id = {string:package_id}
						AND version = {string:old_version}',
					array(
						'current_member' => $user_info['id'],
						'not_installed' => 0,
						'current_time' => time(),
						'package_id' => $row['package_id'],
						'member_name' => $user_info['name'],
						'old_version' => $old_version,
					)
				);
			}
		}

		// Assuming we're not uninstalling, add the entry.
		if (!$context['uninstalling'])
		{
			// Reload the settings table for mods that have altered them upon installation
			reloadSettings();

			// Any db changes from older version?
			if (!empty($old_db_changes))
				$db_package_log = empty($db_package_log) ? $old_db_changes : array_merge($old_db_changes, $db_package_log);

			// If there are some database changes we might want to remove then filter them out.
			if (!empty($db_package_log))
			{
				// We're really just checking for entries which are create table AND add columns (etc).
				$tables = array();

				usort($db_package_log, function ($a, $b)
				{
					if ($a[0] == $b[0])
						return 0;
					return $a[0] == 'remove_table' ? -1 : 1;
				});

				foreach ($db_package_log as $k => $log)
				{
					if ($log[0] == 'remove_table')
						$tables[] = $log[1];
					elseif (in_array($log[1], $tables))
						unset($db_package_log[$k]);
				}
				$db_changes = $smcFunc['json_encode']($db_package_log);
			}
			else
				$db_changes = '';

			// What themes did we actually install?
			$themes_installed = array_unique($themes_installed);
			$themes_installed = implode(',', $themes_installed);

			// What failed steps?
			$failed_step_insert = $smcFunc['json_encode']($failed_steps);

			// Un-sanitize things before we insert them...
			$keys = array('filename', 'name', 'id', 'version');
			foreach ($keys as $key)
			{
				// Yay for variable variables...
				${"package_$key"} = un_htmlspecialchars($packageInfo[$key]);
			}

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
					$package_filename, $package_name, $package_id, $package_version,
					$user_info['id'], $user_info['name'], time(),
					$is_upgrade ? 2 : 1, $failed_step_insert, $themes_installed,
					0, $db_changes, $credits_tag,
				),
				array('id_install')
			);
		}
		$smcFunc['db_free_result']($request);

		$context['install_finished'] = true;
	}

	// If there's database changes - and they want them removed - let's do it last!
	if (!empty($db_changes) && !empty($_POST['do_db_changes']))
	{
		// We're gonna be needing the package db functions!
		db_extend('packages');

		foreach ($db_changes as $change)
		{
			if ($change[0] == 'remove_table' && isset($change[1]))
				$smcFunc['db_drop_table']($change[1]);
			elseif ($change[0] == 'remove_column' && isset($change[2]))
				$smcFunc['db_remove_column']($change[1], $change[2]);
			elseif ($change[0] == 'remove_index' && isset($change[2]))
				$smcFunc['db_remove_index']($change[1], $change[2]);
		}
	}

	// Clean house... get rid of the evidence ;).
	if (file_exists($packagesdir . '/temp'))
		deltree($packagesdir . '/temp');

	// Log what we just did.
	logAction($context['uninstalling'] ? 'uninstall_package' : (!empty($is_upgrade) ? 'upgrade_package' : 'install_package'), array('package' => $smcFunc['htmlspecialchars']($packageInfo['name']), 'version' => $smcFunc['htmlspecialchars']($packageInfo['version'])), 'admin');

	// Just in case, let's clear the whole cache and any minimized CSS and JS to avoid anything going up the swanny.
	clean_cache();
	deleteAllMinified();

	// Restore file permissions?
	create_chmod_control(array(), array(), true);
}

/**
 * List the files in a package.
 */
function PackageList()
{
	global $txt, $scripturl, $context, $sourcedir, $packagesdir;

	require_once($sourcedir . '/Subs-Package.php');

	// No package?  Show him or her the door.
	if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '')
		redirectexit('action=admin;area=packages');

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=admin;area=packages;sa=list;package=' . $_REQUEST['package'],
		'name' => $txt['list_file']
	);
	$context['page_title'] .= ' - ' . $txt['list_file'];
	$context['sub_template'] = 'list';

	// The filename...
	$context['filename'] = $_REQUEST['package'];

	// Let the unpacker do the work.
	if (is_file($packagesdir . '/' . $context['filename']))
		$context['files'] = read_tgz_file($packagesdir . '/' . $context['filename'], null);
	elseif (is_dir($packagesdir . '/' . $context['filename']))
		$context['files'] = listtree($packagesdir . '/' . $context['filename']);
}

/**
 * Display one of the files in a package.
 */
function ExamineFile()
{
	global $txt, $scripturl, $context, $sourcedir, $packagesdir, $smcFunc;

	require_once($sourcedir . '/Subs-Package.php');

	// No package?  Show him or her the door.
	if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '')
		redirectexit('action=admin;area=packages');

	// No file?  Show him or her the door.
	if (!isset($_REQUEST['file']) || $_REQUEST['file'] == '')
		redirectexit('action=admin;area=packages');

	$_REQUEST['package'] = preg_replace('~[\.]+~', '.', strtr($_REQUEST['package'], array('/' => '_', '\\' => '_')));
	$_REQUEST['file'] = preg_replace('~[\.]+~', '.', $_REQUEST['file']);

	if (isset($_REQUEST['raw']))
	{
		if (is_file($packagesdir . '/' . $_REQUEST['package']))
			echo read_tgz_file($packagesdir . '/' . $_REQUEST['package'], $_REQUEST['file'], true);
		elseif (is_dir($packagesdir . '/' . $_REQUEST['package']))
			echo file_get_contents($packagesdir . '/' . $_REQUEST['package'] . '/' . $_REQUEST['file']);

		obExit(false);
	}

	$context['linktree'][count($context['linktree']) - 1] = array(
		'url' => $scripturl . '?action=admin;area=packages;sa=list;package=' . $_REQUEST['package'],
		'name' => $txt['package_examine_file']
	);
	$context['page_title'] .= ' - ' . $txt['package_examine_file'];
	$context['sub_template'] = 'examine';

	// The filename...
	$context['package'] = $_REQUEST['package'];
	$context['filename'] = $_REQUEST['file'];

	// Let the unpacker do the work.... but make sure we handle images properly.
	if (in_array(strtolower(strrchr($_REQUEST['file'], '.')), array('.bmp', '.gif', '.jpeg', '.jpg', '.png')))
		$context['filedata'] = '<img src="' . $scripturl . '?action=admin;area=packages;sa=examine;package=' . $_REQUEST['package'] . ';file=' . $_REQUEST['file'] . ';raw" alt="' . $_REQUEST['file'] . '">';
	else
	{
		if (is_file($packagesdir . '/' . $_REQUEST['package']))
			$context['filedata'] = $smcFunc['htmlspecialchars'](read_tgz_file($packagesdir . '/' . $_REQUEST['package'], $_REQUEST['file'], true));
		elseif (is_dir($packagesdir . '/' . $_REQUEST['package']))
			$context['filedata'] = $smcFunc['htmlspecialchars'](file_get_contents($packagesdir . '/' . $_REQUEST['package'] . '/' . $_REQUEST['file']));

		if (strtolower(strrchr($_REQUEST['file'], '.')) == '.php')
			$context['filedata'] = highlight_php_code($context['filedata']);
	}
}

/**
 * Delete a package.
 */
function PackageRemove()
{
	global $scripturl, $packagesdir;

	// Check it.
	checkSession('get');

	// Ack, don't allow deletion of arbitrary files here, could become a security hole somehow!
	if (!isset($_GET['package']) || $_GET['package'] == 'index.php' || $_GET['package'] == 'backups')
		redirectexit('action=admin;area=packages;sa=browse');
	$_GET['package'] = preg_replace('~[\.]+~', '.', strtr($_GET['package'], array('/' => '_', '\\' => '_')));

	// Can't delete what's not there.
	if (file_exists($packagesdir . '/' . $_GET['package']) && (substr($_GET['package'], -4) == '.zip' || substr($_GET['package'], -4) == '.tgz' || substr($_GET['package'], -7) == '.tar.gz' || is_dir($packagesdir . '/' . $_GET['package'])) && $_GET['package'] != 'backups' && substr($_GET['package'], 0, 1) != '.')
	{
		create_chmod_control(array($packagesdir . '/' . $_GET['package']), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=remove;package=' . $_GET['package'], 'crash_on_error' => true));

		if (is_dir($packagesdir . '/' . $_GET['package']))
			deltree($packagesdir . '/' . $_GET['package']);
		else
		{
			smf_chmod($packagesdir . '/' . $_GET['package'], 0777);
			unlink($packagesdir . '/' . $_GET['package']);
		}
	}

	redirectexit('action=admin;area=packages;sa=browse');
}

/**
 * Browse a list of installed packages.
 */
function PackageBrowse()
{
	global $txt, $scripturl, $context, $sourcedir, $smcFunc;

	$context['page_title'] .= ' - ' . $txt['browse_packages'];

	$context['forum_version'] = SMF_FULL_VERSION;
	$context['available_modification'] = array();
	$context['available_avatar'] = array();
	$context['available_language'] = array();
	$context['available_unknown'] = array();
	$context['modification_types'] = array('modification', 'avatar', 'language', 'unknown');

	call_integration_hook('integrate_modification_types');

	require_once($sourcedir . '/Subs-List.php');

	foreach ($context['modification_types'] as $type)
	{
		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'packages_lists_' . $type,
			'title' => $txt[$type . '_package'],
			'no_items_label' => $txt['no_packages'],
			'get_items' => array(
				'function' => 'list_getPackages',
				'params' => array('type' => $type),
			),
			'base_href' => $scripturl . '?action=admin;area=packages;sa=browse;type=' . $type,
			'default_sort_col' => 'id' . $type,
			'columns' => array(
				'id' . $type => array(
					'header' => array(
						'value' => $txt['package_id'],
						'style' => 'width: 40px;',
					),
					'data' => array(
						'function' => function($package_md5) use ($type, &$context)
						{
							if (isset($context['available_' . $type . ''][$package_md5]))
								return $context['available_' . $type . ''][$package_md5]['sort_id'];
						},
					),
					'sort' => array(
						'default' => 'sort_id',
						'reverse' => 'sort_id'
					),
				),
				'mod_name' . $type => array(
					'header' => array(
						'value' => $txt['mod_name'],
						'style' => 'width: 25%;',
					),
					'data' => array(
						'function' => function($package_md5) use ($type, &$context)
						{
							if (isset($context['available_' . $type . ''][$package_md5]))
								return $context['available_' . $type . ''][$package_md5]['name'];
						},
					),
					'sort' => array(
						'default' => 'name',
						'reverse' => 'name',
					),
				),
				'version' . $type => array(
					'header' => array(
						'value' => $txt['mod_version'],
					),
					'data' => array(
						'function' => function($package_md5) use ($type, &$context)
						{
							if (isset($context['available_' . $type . ''][$package_md5]))
								return $context['available_' . $type . ''][$package_md5]['version'];
						},
					),
					'sort' => array(
						'default' => 'version',
						'reverse' => 'version',
					),
				),
				'time_installed' . $type => array(
					'header' => array(
						'value' => $txt['mod_installed_time'],
					),
					'data' => array(
						'function' => function($package_md5) use ($type, $txt, &$context)
						{
							if (isset($context['available_' . $type . ''][$package_md5]))
								return !empty($context['available_' . $type . ''][$package_md5]['time_installed']) ? timeformat($context['available_' . $type . ''][$package_md5]['time_installed']) : $txt['not_applicable'];
						},
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'time_installed',
						'reverse' => 'time_installed',
					),
				),
				'operations' . $type => array(
					'header' => array(
						'value' => '',
					),
					'data' => array(
						'function' => function($package_md5) use ($type, &$context, $scripturl, $txt)
						{
							if (!isset($context['available_' . $type . ''][$package_md5]))
								return '';

							// Rewrite shortcut
							$package = $context['available_' . $type . ''][$package_md5];
							$return = '';

							if ($package['can_uninstall'])
								$return = '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=uninstall;package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '" class="button">' . $txt['uninstall'] . '</a>';
							elseif ($package['can_emulate_uninstall'])
								$return = '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=uninstall;ve=' . $package['can_emulate_uninstall'] . ';package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '" class="button">' . $txt['package_emulate_uninstall'] . ' ' . $package['can_emulate_uninstall'] . '</a>';
							elseif ($package['can_upgrade'])
								$return = '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '" class="button">' . $txt['package_upgrade'] . '</a>';
							elseif ($package['can_install'])
								$return = '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '" class="button">' . $txt['install_mod'] . '</a>';
							elseif ($package['can_emulate_install'])
								$return = '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=install;ve=' . $package['can_emulate_install'] . ';package=' . $package['filename'] . '" class="button">' . $txt['package_emulate_install'] . ' ' . $package['can_emulate_install'] . '</a>';

							return $return . '
									<a href="' . $scripturl . '?action=admin;area=packages;sa=list;package=' . $package['filename'] . '" class="button">' . $txt['list_files'] . '</a>
									<a href="' . $scripturl . '?action=admin;area=packages;sa=remove;package=' . $package['filename'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"' . ($package['is_installed'] && $package['is_current'] ? ' data-confirm="' . $txt['package_delete_bad'] . '"' : '') . ' class="button' . ($package['is_installed'] && $package['is_current'] ? ' you_sure' : '') . '">' . $txt['package_delete'] . '</a>';
						},
						'class' => 'righttext',
					),
				),
			),
		);

		createList($listOptions);
	}

	$context['sub_template'] = 'browse';
	$context['default_list'] = 'packages_lists';

	$get_versions = $smcFunc['db_query']('', '
		SELECT data FROM {db_prefix}admin_info_files WHERE filename={string:versionsfile} AND path={string:smf}',
		array(
			'versionsfile' => 'latest-versions.txt',
			'smf' => '/smf/',
		)
	);

	$data = $smcFunc['db_fetch_assoc']($get_versions);
	$smcFunc['db_free_result']($get_versions);

	// Decode the data.
	$items = $smcFunc['json_decode']($data['data'], true);

	$context['emulation_versions'] = preg_replace('~^SMF ~', '', $items);

	// Current SMF version, which is selected by default
	$context['default_version'] = SMF_VERSION;

	$context['emulation_versions'][] = $context['default_version'];

	// Version we're currently emulating, if any
	$context['selected_version'] = preg_replace('~^SMF ~', '', $context['forum_version']);
}

/**
 * Get a listing of all the packages
 * Determines if the package is a mod, avatar, language package
 * Determines if the package has been installed or not
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to show per page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @param string? $params A key for the $packages array
 * @return array An array of information about the packages
 */
function list_getPackages($start, $items_per_page, $sort, $params)
{
	global $scripturl, $packagesdir, $context;
	static $packages, $installed_mods;

	// Start things up
	if (!isset($packages[$params]))
		$packages[$params] = array();

	// We need the packages directory to be writable for this.
	if (!@is_writable($packagesdir))
		create_chmod_control(array($packagesdir), array('destination_url' => $scripturl . '?action=admin;area=packages', 'crash_on_error' => true));

	$the_version = SMF_VERSION;

	// Here we have a little code to help those who class themselves as something of gods, version emulation ;)
	if (isset($_GET['version_emulate']) && strtr($_GET['version_emulate'], array('SMF ' => '')) == $the_version)
	{
		unset($_SESSION['version_emulate']);
	}
	elseif (isset($_GET['version_emulate']))
	{
		if (($_GET['version_emulate'] === 0 || $_GET['version_emulate'] === SMF_FULL_VERSION) && isset($_SESSION['version_emulate']))
			unset($_SESSION['version_emulate']);
		elseif ($_GET['version_emulate'] !== 0)
			$_SESSION['version_emulate'] = strtr($_GET['version_emulate'], array('-' => ' ', '+' => ' ', 'SMF ' => ''));
	}
	if (!empty($_SESSION['version_emulate']))
	{
		$context['forum_version'] = 'SMF ' . $_SESSION['version_emulate'];
		$the_version = $_SESSION['version_emulate'];
	}
	if (isset($_SESSION['single_version_emulate']))
		unset($_SESSION['single_version_emulate']);

	if (empty($installed_mods))
	{
		$instmods = loadInstalledPackages();
		$installed_mods = array();
		// Look through the list of installed mods...
		foreach ($instmods as $installed_mod)
			$installed_mods[$installed_mod['package_id']] = array(
				'id' => $installed_mod['id'],
				'version' => $installed_mod['version'],
				'time_installed' => $installed_mod['time_installed'],
			);

		// Get a list of all the ids installed, so the latest packages won't include already installed ones.
		$context['installed_mods'] = array_keys($installed_mods);
	}

	if ($dir = @opendir($packagesdir))
	{
		$dirs = array();
		$sort_id = array(
			'modification' => 1,
			'avatar' => 1,
			'language' => 1,
			'unknown' => 1,
		);
		call_integration_hook('integrate_packages_sort_id', array(&$sort_id, &$packages));

		while ($package = readdir($dir))
		{
			if ($package == '.' || $package == '..' || $package == 'temp' || (!(is_dir($packagesdir . '/' . $package) && file_exists($packagesdir . '/' . $package . '/package-info.xml')) && substr(strtolower($package), -7) != '.tar.gz' && substr(strtolower($package), -4) != '.tgz' && substr(strtolower($package), -4) != '.zip'))
				continue;

			// Skip directories or files that are named the same.
			if (is_dir($packagesdir . '/' . $package))
			{
				if (in_array($package, $dirs))
					continue;
				$dirs[] = $package;
			}
			elseif (substr(strtolower($package), -7) == '.tar.gz')
			{
				if (in_array(substr($package, 0, -7), $dirs))
					continue;
				$dirs[] = substr($package, 0, -7);
			}
			elseif (substr(strtolower($package), -4) == '.zip' || substr(strtolower($package), -4) == '.tgz')
			{
				if (in_array(substr($package, 0, -4), $dirs))
					continue;
				$dirs[] = substr($package, 0, -4);
			}

			$packageInfo = getPackageInfo($package);
			if (!is_array($packageInfo))
				continue;

			if (!empty($packageInfo))
			{
				$packageInfo['installed_id'] = isset($installed_mods[$packageInfo['id']]) ? $installed_mods[$packageInfo['id']]['id'] : 0;
				$packageInfo['time_installed'] = isset($installed_mods[$packageInfo['id']]) ? $installed_mods[$packageInfo['id']]['time_installed'] : 0;

				if (!isset($sort_id[$packageInfo['type']]))
					$packageInfo['sort_id'] = $sort_id['unknown'];
				else
					$packageInfo['sort_id'] = $sort_id[$packageInfo['type']];

				$packageInfo['is_installed'] = isset($installed_mods[$packageInfo['id']]);
				$packageInfo['is_current'] = $packageInfo['is_installed'] && ($installed_mods[$packageInfo['id']]['version'] == $packageInfo['version']);
				$packageInfo['is_newer'] = $packageInfo['is_installed'] && ($installed_mods[$packageInfo['id']]['version'] > $packageInfo['version']);

				$packageInfo['can_install'] = false;
				$packageInfo['can_uninstall'] = false;
				$packageInfo['can_upgrade'] = false;
				$packageInfo['can_emulate_install'] = false;
				$packageInfo['can_emulate_uninstall'] = false;

				// This package is currently NOT installed.  Check if it can be.
				if (!$packageInfo['is_installed'] && $packageInfo['xml']->exists('install'))
				{
					// Check if there's an install for *THIS* version of SMF.
					$installs = $packageInfo['xml']->set('install');
					foreach ($installs as $install)
					{
						if (!$install->exists('@for') || matchPackageVersion($the_version, $install->fetch('@for')))
						{
							// Okay, this one is good to go.
							$packageInfo['can_install'] = true;
							break;
						}
					}

					// no install found for this version, lets see if one exists for another
					if ($packageInfo['can_install'] === false && $install->exists('@for') && empty($_SESSION['version_emulate']))
					{
						$reset = true;

						// Get the highest install version that is available from the package
						foreach ($installs as $install)
						{
							$packageInfo['can_emulate_install'] = matchHighestPackageVersion($install->fetch('@for'), $reset, $the_version);
							$reset = false;
						}
					}
				}
				// An already installed, but old, package.  Can we upgrade it?
				elseif ($packageInfo['is_installed'] && !$packageInfo['is_current'] && $packageInfo['xml']->exists('upgrade'))
				{
					$upgrades = $packageInfo['xml']->set('upgrade');

					// First go through, and check against the current version of SMF.
					foreach ($upgrades as $upgrade)
					{
						// Even if it is for this SMF, is it for the installed version of the mod?
						if (!$upgrade->exists('@for') || matchPackageVersion($the_version, $upgrade->fetch('@for')))
							if (!$upgrade->exists('@from') || matchPackageVersion($installed_mods[$packageInfo['id']]['version'], $upgrade->fetch('@from')))
							{
								$packageInfo['can_upgrade'] = true;
								break;
							}
					}
				}
				// Note that it has to be the current version to be uninstallable.  Shucks.
				elseif ($packageInfo['is_installed'] && $packageInfo['is_current'] && $packageInfo['xml']->exists('uninstall'))
				{
					$uninstalls = $packageInfo['xml']->set('uninstall');

					// Can we find any uninstallation methods that work for this SMF version?
					foreach ($uninstalls as $uninstall)
					{
						if (!$uninstall->exists('@for') || matchPackageVersion($the_version, $uninstall->fetch('@for')))
						{
							$packageInfo['can_uninstall'] = true;
							break;
						}
					}

					// no uninstall found for this version, lets see if one exists for another
					if ($packageInfo['can_uninstall'] === false && $uninstall->exists('@for') && empty($_SESSION['version_emulate']))
					{
						$reset = true;

						// Get the highest install version that is available from the package
						foreach ($uninstalls as $uninstall)
						{
							$packageInfo['can_emulate_uninstall'] = matchHighestPackageVersion($uninstall->fetch('@for'), $reset, $the_version);
							$reset = false;
						}
					}
				}

				// Save some memory by not passing the xmlArray object into context.
				unset($packageInfo['xml']);

				if (isset($sort_id[$packageInfo['type']], $packages[$packageInfo['type']], $context['available_' . $packageInfo['type']]) && $params == $packageInfo['type'])
				{
					$sort_id[$packageInfo['type']]++;
					$packages[$packageInfo['type']][strtolower($packageInfo[$sort]) . '_' . $sort_id[$packageInfo['type']]] = md5($package);
					$context['available_' . $packageInfo['type']][md5($package)] = $packageInfo;
				}
				elseif (!isset($sort_id[$packageInfo['type']], $packages[$packageInfo['type']], $context['available_' . $packageInfo['type']]) && $params == 'unknown')
				{
					$packageInfo['sort_id'] = $sort_id['unknown'];
					$sort_id['unknown']++;
					$packages['unknown'][strtolower($packageInfo[$sort]) . '_' . $sort_id['unknown']] = md5($package);
					$context['available_unknown'][md5($package)] = $packageInfo;
				}
			}
		}
		closedir($dir);
	}

	if (isset($_GET['type']) && $_GET['type'] == $params)
	{
		if (isset($_GET['desc']))
			krsort($packages[$params]);
		else
			ksort($packages[$params]);
	}

	return $packages[$params];
}

/**
 * Used when a temp FTP access is needed to package functions
 */
function PackageOptions()
{
	global $txt, $context, $modSettings, $smcFunc;

	if (isset($_POST['save']))
	{
		checkSession();

		updateSettings(array(
			'package_server' => trim($smcFunc['htmlspecialchars']($_POST['pack_server'])),
			'package_port' => trim($smcFunc['htmlspecialchars']($_POST['pack_port'])),
			'package_username' => trim($smcFunc['htmlspecialchars']($_POST['pack_user'])),
			'package_make_backups' => !empty($_POST['package_make_backups']),
			'package_make_full_backups' => !empty($_POST['package_make_full_backups'])
		));
		$_SESSION['adm-save'] = true;

		redirectexit('action=admin;area=packages;sa=options');
	}

	if (preg_match('~^/home\d*/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
		$default_username = $match[1];
	else
		$default_username = '';

	$context['page_title'] = $txt['package_settings'];
	$context['sub_template'] = 'install_options';

	$context['package_ftp_server'] = isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost';
	$context['package_ftp_port'] = isset($modSettings['package_port']) ? $modSettings['package_port'] : '21';
	$context['package_ftp_username'] = isset($modSettings['package_username']) ? $modSettings['package_username'] : $default_username;
	$context['package_make_backups'] = !empty($modSettings['package_make_backups']);
	$context['package_make_full_backups'] = !empty($modSettings['package_make_full_backups']);

	if (!empty($_SESSION['adm-save']))
	{
		$context['saved_successful'] = true;
		unset ($_SESSION['adm-save']);
	}
}

/**
 * List operations
 */
function ViewOperations()
{
	global $context, $txt, $sourcedir, $packagesdir, $smcFunc, $modSettings;

	// Can't be in here buddy.
	isAllowedTo('admin_forum');

	// We need to know the operation key for the search and replace, mod file looking at, is it a board mod?
	if (!isset($_REQUEST['operation_key'], $_REQUEST['filename']) && !is_numeric($_REQUEST['operation_key']))
		fatal_lang_error('operation_invalid', 'general');

	// Load the required file.
	require_once($sourcedir . '/Subs-Package.php');

	// Uninstalling the mod?
	$reverse = isset($_REQUEST['reverse']) ? true : false;

	// Get the base name.
	$context['filename'] = preg_replace('~[\.]+~', '.', $_REQUEST['package']);

	// We need to extract this again.
	if (is_file($packagesdir . '/' . $context['filename']))
	{
		$context['extracted_files'] = read_tgz_file($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');

		if ($context['extracted_files'] && !file_exists($packagesdir . '/temp/package-info.xml'))
			foreach ($context['extracted_files'] as $file)
				if (basename($file['filename']) == 'package-info.xml')
				{
					$context['base_path'] = dirname($file['filename']) . '/';
					break;
				}

		if (!isset($context['base_path']))
			$context['base_path'] = '';
	}
	elseif (is_dir($packagesdir . '/' . $context['filename']))
	{
		copytree($packagesdir . '/' . $context['filename'], $packagesdir . '/temp');
		$context['extracted_files'] = listtree($packagesdir . '/temp');
		$context['base_path'] = '';
	}

	// Load up any custom themes we may want to install into...
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'known_theme_list' => explode(',', $modSettings['knownThemes']),
			'default_theme' => 1,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$theme_paths = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	// If we're viewing uninstall operations, only consider themes that
	// the package is actually installed into.
	if (isset($_REQUEST['reverse']) && !empty($_REQUEST['install_id']))
	{
		$install_id = (int) $_REQUEST['install_id'];
		if ($install_id > 0)
		{
			$old_themes = array();
			$request = $smcFunc['db_query']('', '
				SELECT themes_installed
				FROM {db_prefix}log_packages
				WHERE id_install = {int:install_id}',
				array(
					'install_id' => $install_id,
				)
			);

			if ($smcFunc['db_num_rows']($request) == 1)
			{
				list ($old_themes) = $smcFunc['db_fetch_row']($request);
				$old_themes = explode(',', $old_themes);

				foreach ($theme_paths as $id => $data)
					if ($id != 1 && !in_array($id, $old_themes))
						unset($theme_paths[$id]);
			}
			$smcFunc['db_free_result']($request);
		}
	}

	// Boardmod?
	if (isset($_REQUEST['boardmod']))
		$mod_actions = parseBoardMod(@file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $_REQUEST['filename']), true, $reverse, $theme_paths);
	else
		$mod_actions = parseModification(@file_get_contents($packagesdir . '/temp/' . $context['base_path'] . $_REQUEST['filename']), true, $reverse, $theme_paths);

	// Ok lets get the content of the file.
	$context['operations'] = array(
		'search' => strtr($smcFunc['htmlspecialchars']($mod_actions[$_REQUEST['operation_key']]['search_original']), array('[' => '&#91;', ']' => '&#93;')),
		'replace' => strtr($smcFunc['htmlspecialchars']($mod_actions[$_REQUEST['operation_key']]['replace_original']), array('[' => '&#91;', ']' => '&#93;')),
		'position' => $mod_actions[$_REQUEST['operation_key']]['position'],
	);

	// Let's do some formatting...
	$operation_text = $context['operations']['position'] == 'replace' ? 'operation_replace' : ($context['operations']['position'] == 'before' ? 'operation_after' : 'operation_before');
	$context['operations']['search'] = parse_bbc('[code=' . $txt['operation_find'] . ']' . ($context['operations']['position'] == 'end' ? '?&gt;' : $context['operations']['search']) . '[/code]');
	$context['operations']['replace'] = parse_bbc('[code=' . $txt[$operation_text] . ']' . $context['operations']['replace'] . '[/code]');

	// No layers
	$context['template_layers'] = array();
	$context['sub_template'] = 'view_operations';
}

/**
 * Allow the admin to reset permissions on files.
 */
function PackagePermissions()
{
	global $context, $txt, $modSettings, $boarddir, $sourcedir, $cachedir, $smcFunc, $package_ftp;

	// Let's try and be good, yes?
	checkSession('get');

	// If we're restoring permissions this is just a pass through really.
	if (isset($_GET['restore']))
	{
		create_chmod_control(array(), array(), true);
		fatal_lang_error('no_access', false);
	}

	// This is a memory eat.
	setMemoryLimit('128M');
	@set_time_limit(600);

	// Load up some FTP stuff.
	create_chmod_control();

	if (empty($package_ftp) && !isset($_POST['skip_ftp']))
	{
		require_once($sourcedir . '/Class-Package.php');
		$ftp = new ftp_connection(null);
		list ($username, $detect_path, $found_path) = $ftp->detect_path($boarddir);

		$context['package_ftp'] = array(
			'server' => isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost',
			'port' => isset($modSettings['package_port']) ? $modSettings['package_port'] : '21',
			'username' => empty($username) ? (isset($modSettings['package_username']) ? $modSettings['package_username'] : '') : $username,
			'path' => $detect_path,
			'form_elements_only' => true,
		);
	}
	else
		$context['ftp_connected'] = true;

	// Define the template.
	$context['page_title'] = $txt['package_file_perms'];
	$context['sub_template'] = 'file_permissions';

	// Define what files we're interested in, as a tree.
	$context['file_tree'] = array(
		strtr($boarddir, array('\\' => '/')) => array(
			'type' => 'dir',
			'contents' => array(
				'agreement.txt' => array(
					'type' => 'file',
					'writable_on' => 'standard',
				),
				'Settings.php' => array(
					'type' => 'file',
					'writable_on' => 'restrictive',
				),
				'Settings_bak.php' => array(
					'type' => 'file',
					'writable_on' => 'restrictive',
				),
				'attachments' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'avatars' => array(
					'type' => 'dir',
					'writable_on' => 'standard',
				),
				'cache' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'custom_avatar_dir' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'Smileys' => array(
					'type' => 'dir_recursive',
					'writable_on' => 'standard',
				),
				'Sources' => array(
					'type' => 'dir_recursive',
					'list_contents' => true,
					'writable_on' => 'standard',
					'contents' => array(
						'tasks' => array(
							'type' => 'dir',
							'list_contents' => true,
						),
					),
				),
				'Themes' => array(
					'type' => 'dir_recursive',
					'writable_on' => 'standard',
					'contents' => array(
						'default' => array(
							'type' => 'dir_recursive',
							'list_contents' => true,
							'contents' => array(
								'languages' => array(
									'type' => 'dir',
									'list_contents' => true,
								),
							),
						),
					),
				),
				'Packages' => array(
					'type' => 'dir',
					'writable_on' => 'standard',
					'contents' => array(
						'temp' => array(
							'type' => 'dir',
						),
						'backup' => array(
							'type' => 'dir',
						),
					),
				),
			),
		),
	);

	// Directories that can move.
	if (substr($sourcedir, 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Sources']);
		$context['file_tree'][strtr($sourcedir, array('\\' => '/'))] = array(
			'type' => 'dir',
			'list_contents' => true,
			'writable_on' => 'standard',
		);
	}

	// Moved the cache?
	if (substr($cachedir, 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['cache']);
		$context['file_tree'][strtr($cachedir, array('\\' => '/'))] = array(
			'type' => 'dir',
			'list_contents' => false,
			'writable_on' => 'restrictive',
		);
	}

	// Are we using multiple attachment directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['attachments']);

		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = $smcFunc['json_decode']($modSettings['attachmentUploadDir'], true);

		// @todo Should we suggest non-current directories be read only?
		foreach ($modSettings['attachmentUploadDir'] as $dir)
			$context['file_tree'][strtr($dir, array('\\' => '/'))] = array(
				'type' => 'dir',
				'writable_on' => 'restrictive',
			);
	}
	elseif (substr($modSettings['attachmentUploadDir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['attachments']);
		$context['file_tree'][strtr($modSettings['attachmentUploadDir'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'restrictive',
		);
	}

	if (substr($modSettings['smileys_dir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Smileys']);
		$context['file_tree'][strtr($modSettings['smileys_dir'], array('\\' => '/'))] = array(
			'type' => 'dir_recursive',
			'writable_on' => 'standard',
		);
	}
	if (substr($modSettings['avatar_directory'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['avatars']);
		$context['file_tree'][strtr($modSettings['avatar_directory'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'standard',
		);
	}
	if (isset($modSettings['custom_avatar_dir']) && substr($modSettings['custom_avatar_dir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['custom_avatar_dir']);
		$context['file_tree'][strtr($modSettings['custom_avatar_dir'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'restrictive',
		);
	}

	// Load up any custom themes.
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_theme > {int:default_theme_id}
			AND id_member = {int:guest_id}
			AND variable = {string:theme_dir}
		ORDER BY value ASC',
		array(
			'default_theme_id' => 1,
			'guest_id' => 0,
			'theme_dir' => 'theme_dir',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (substr(strtolower(strtr($row['value'], array('\\' => '/'))), 0, strlen($boarddir) + 7) == strtolower(strtr($boarddir, array('\\' => '/')) . '/Themes'))
			$context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Themes']['contents'][substr($row['value'], strlen($boarddir) + 8)] = array(
				'type' => 'dir_recursive',
				'list_contents' => true,
				'contents' => array(
					'languages' => array(
						'type' => 'dir',
						'list_contents' => true,
					),
				),
			);
		else
		{
			$context['file_tree'][strtr($row['value'], array('\\' => '/'))] = array(
				'type' => 'dir_recursive',
				'list_contents' => true,
				'contents' => array(
					'languages' => array(
						'type' => 'dir',
						'list_contents' => true,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	// If we're submitting then let's move on to another function to keep things cleaner..
	if (isset($_POST['action_changes']))
		return PackagePermissionsAction();

	$context['look_for'] = array();
	// Are we looking for a particular tree - normally an expansion?
	if (!empty($_REQUEST['find']))
		$context['look_for'][] = base64_decode($_REQUEST['find']);
	// Only that tree?
	$context['only_find'] = isset($_GET['xml']) && !empty($_REQUEST['onlyfind']) ? $_REQUEST['onlyfind'] : '';
	if ($context['only_find'])
		$context['look_for'][] = $context['only_find'];

	// Have we got a load of back-catalogue trees to expand from a submit etc?
	if (!empty($_GET['back_look']))
	{
		$potententialTrees = $smcFunc['json_decode'](base64_decode($_GET['back_look']), true);
		foreach ($potententialTrees as $tree)
			$context['look_for'][] = $tree;
	}
	// ... maybe posted?
	if (!empty($_POST['back_look']))
		$context['only_find'] = array_merge($context['only_find'], $_POST['back_look']);

	$context['back_look_data'] = base64_encode($smcFunc['json_encode'](array_slice($context['look_for'], 0, 15)));

	// Are we finding more files than first thought?
	$context['file_offset'] = !empty($_REQUEST['fileoffset']) ? (int) $_REQUEST['fileoffset'] : 0;
	// Don't list more than this many files in a directory.
	$context['file_limit'] = 150;

	// How many levels shall we show?
	$context['default_level'] = empty($context['only_find']) ? 2 : 25;

	// This will be used if we end up catching XML data.
	$context['xml_data'] = array(
		'roots' => array(
			'identifier' => 'root',
			'children' => array(
				array(
					'value' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
			),
		),
		'folders' => array(
			'identifier' => 'folder',
			'children' => array(),
		),
	);

	foreach ($context['file_tree'] as $path => $data)
	{
		// Run this directory.
		if (file_exists($path) && (empty($context['only_find']) || substr($context['only_find'], 0, strlen($path)) == $path))
		{
			// Get the first level down only.
			fetchPerms__recursive($path, $context['file_tree'][$path], 1);
			$context['file_tree'][$path]['perms'] = array(
				'chmod' => @is_writable($path),
				'perms' => @fileperms($path),
			);
		}
		else
			unset($context['file_tree'][$path]);
	}

	// Is this actually xml?
	if (isset($_GET['xml']))
	{
		loadTemplate('Xml');
		$context['sub_template'] = 'generic_xml';
		$context['template_layers'] = array();
	}
}

/**
 * Checkes the permissions of all the areas that will be affected by the package
 *
 * @param string $path The path to the directiory to check permissions for
 * @param array $data An array of data about the directory
 * @param int $level How far deep to go
 */
function fetchPerms__recursive($path, &$data, $level)
{
	global $context;

	$isLikelyPath = false;
	foreach ($context['look_for'] as $possiblePath)
		if (substr($possiblePath, 0, strlen($path)) == $path)
			$isLikelyPath = true;

	// Is this where we stop?
	if (isset($_GET['xml']) && !empty($context['look_for']) && !$isLikelyPath)
		return;
	elseif ($level > $context['default_level'] && !$isLikelyPath)
		return;

	// Are we actually interested in saving this data?
	$save_data = empty($context['only_find']) || $context['only_find'] == $path;

	// @todo Shouldn't happen - but better error message?
	if (!is_dir($path))
		fatal_lang_error('no_access', false);

	// This is where we put stuff we've found for sorting.
	$foundData = array(
		'files' => array(),
		'folders' => array(),
	);

	$dh = opendir($path);
	while ($entry = readdir($dh))
	{
		// Some kind of file?
		if (is_file($path . '/' . $entry))
		{
			// Are we listing PHP files in this directory?
			if ($save_data && !empty($data['list_contents']) && substr($entry, -4) == '.php')
				$foundData['files'][$entry] = true;
			// A file we were looking for.
			elseif ($save_data && isset($data['contents'][$entry]))
				$foundData['files'][$entry] = true;
		}
		// It's a directory - we're interested one way or another, probably...
		elseif ($entry != '.' && $entry != '..')
		{
			// Going further?
			if ((!empty($data['type']) && $data['type'] == 'dir_recursive') || (isset($data['contents'][$entry]) && (!empty($data['contents'][$entry]['list_contents']) || (!empty($data['contents'][$entry]['type']) && $data['contents'][$entry]['type'] == 'dir_recursive'))))
			{
				if (!isset($data['contents'][$entry]))
					$foundData['folders'][$entry] = 'dir_recursive';
				else
					$foundData['folders'][$entry] = true;

				// If this wasn't expected inherit the recusiveness...
				if (!isset($data['contents'][$entry]))
					// We need to do this as we will be going all recursive.
					$data['contents'][$entry] = array(
						'type' => 'dir_recursive',
					);

				// Actually do the recursive stuff...
				fetchPerms__recursive($path . '/' . $entry, $data['contents'][$entry], $level + 1);
			}
			// Maybe it is a folder we are not descending into.
			elseif (isset($data['contents'][$entry]))
				$foundData['folders'][$entry] = true;
			// Otherwise we stop here.
		}
	}
	closedir($dh);

	// Nothing to see here?
	if (!$save_data)
		return;

	// Now actually add the data, starting with the folders.
	ksort($foundData['folders']);
	foreach ($foundData['folders'] as $folder => $type)
	{
		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $folder),
				'perms' => @fileperms($path . '/' . $folder),
			),
		);
		if ($type !== true)
			$additional_data['type'] = $type;

		// If there's an offset ignore any folders in XML mode.
		if (isset($_GET['xml']) && $context['file_offset'] == 0)
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 1,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $folder),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $folder,
			);
		}
		elseif (!isset($_GET['xml']))
		{
			if (isset($data['contents'][$folder]))
				$data['contents'][$folder] = array_merge($data['contents'][$folder], $additional_data);
			else
				$data['contents'][$folder] = $additional_data;
		}
	}

	// Now we want to do a similar thing with files.
	ksort($foundData['files']);
	$counter = -1;
	foreach ($foundData['files'] as $file => $dummy)
	{
		$counter++;

		// Have we reached our offset?
		if ($context['file_offset'] > $counter)
			continue;
		// Gone too far?
		if ($counter > ($context['file_offset'] + $context['file_limit']))
			continue;

		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $file),
				'perms' => @fileperms($path . '/' . $file),
			),
		);

		// XML?
		if (isset($_GET['xml']))
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 0,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => $counter == ($context['file_offset'] + $context['file_limit']) ? 1 : 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $file),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $file,
			);
		}
		elseif ($counter != ($context['file_offset'] + $context['file_limit']))
		{
			if (isset($data['contents'][$file]))
				$data['contents'][$file] = array_merge($data['contents'][$file], $additional_data);
			else
				$data['contents'][$file] = $additional_data;
		}
	}
}

/**
 * Actually action the permission changes they want.
 */
function PackagePermissionsAction()
{
	global $smcFunc, $context, $txt, $time_start, $package_ftp;

	umask(0);

	$timeout_limit = 5;

	$context['method'] = $_POST['method'] == 'individual' ? 'individual' : 'predefined';
	$context['sub_template'] = 'action_permissions';
	$context['page_title'] = $txt['package_file_perms_applying'];
	$context['back_look_data'] = isset($_POST['back_look']) ? $_POST['back_look'] : array();

	// Skipping use of FTP?
	if (empty($package_ftp))
		$context['skip_ftp'] = true;

	// We'll start off in a good place, security. Make sure that if we're dealing with individual files that they seem in the right place.
	if ($context['method'] == 'individual')
	{
		// Only these path roots are legal.
		$legal_roots = array_keys($context['file_tree']);
		$context['custom_value'] = (int) $_POST['custom_value'];

		// Continuing?
		if (isset($_POST['toProcess']))
			$_POST['permStatus'] = $smcFunc['json_decode'](base64_decode($_POST['toProcess']), true);

		if (isset($_POST['permStatus']))
		{
			$context['to_process'] = array();
			$validate_custom = false;
			foreach ($_POST['permStatus'] as $path => $status)
			{
				// Nothing to see here?
				if ($status == 'no_change')
					continue;
				$legal = false;
				foreach ($legal_roots as $root)
					if (substr($path, 0, strlen($root)) == $root)
						$legal = true;

				if (!$legal)
					continue;

				// Check it exists.
				if (!file_exists($path))
					continue;

				if ($status == 'custom')
					$validate_custom = true;

				// Now add it.
				$context['to_process'][$path] = $status;
			}
			$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : count($context['to_process']);

			// Make sure the chmod status is valid?
			if ($validate_custom)
			{
				if (preg_match('~^[4567][4567][4567]$~', $context['custom_value']) == false)
					fatal_error($txt['chmod_value_invalid']);
			}

			// Nothing to do?
			if (empty($context['to_process']))
				redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode($smcFunc['json_encode']($context['back_look_data'])) : '') . ';' . $context['session_var'] . '=' . $context['session_id']);
		}
		// Should never get here,
		else
			fatal_lang_error('no_access', false);

		// Setup the custom value.
		$custom_value = octdec('0' . $context['custom_value']);

		// Start processing items.
		foreach ($context['to_process'] as $path => $status)
		{
			if (in_array($status, array('execute', 'writable', 'read')))
				package_chmod($path, $status);
			elseif ($status == 'custom' && !empty($custom_value))
			{
				// Use FTP if we have it.
				if (!empty($package_ftp) && !empty($_SESSION['pack_ftp']))
				{
					$ftp_file = strtr($path, array($_SESSION['pack_ftp']['root'] => ''));
					$package_ftp->chmod($ftp_file, $custom_value);
				}
				else
					smf_chmod($path, $custom_value);
			}

			// This fish is fried...
			unset($context['to_process'][$path]);

			// See if we're out of time?
			if ((time() - $time_start) > $timeout_limit)
			{
				// Prepare template usage for to_process.
				$context['to_process_encode'] = base64_encode($smcFunc['json_encode']($context['to_process']));

				return false;
			}
		}

		// Prepare template usage for to_process.
		$context['to_process_encode'] = base64_encode($smcFunc['json_encode']($context['to_process']));
	}
	// If predefined this is a little different.
	else
	{
		$context['predefined_type'] = isset($_POST['predefined']) ? $_POST['predefined'] : 'restricted';

		$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : 0;
		$context['directory_list'] = isset($_POST['dirList']) ? $smcFunc['json_decode'](base64_decode($_POST['dirList']), true) : array();

		$context['file_offset'] = isset($_POST['fileOffset']) ? (int) $_POST['fileOffset'] : 0;

		// Haven't counted the items yet?
		if (empty($context['total_items']))
		{
			/**
			 * Counts all the directories under a given path
			 *
			 * @param string $dir
			 * @return integer
			 */
			function count_directories__recursive($dir)
			{
				global $context;

				$count = 0;
				$dh = @opendir($dir);
				while ($entry = readdir($dh))
				{
					if ($entry != '.' && $entry != '..' && is_dir($dir . '/' . $entry))
					{
						$context['directory_list'][$dir . '/' . $entry] = 1;
						$count++;
						$count += count_directories__recursive($dir . '/' . $entry);
					}
				}
				closedir($dh);

				return $count;
			}

			foreach ($context['file_tree'] as $path => $data)
			{
				if (is_dir($path))
				{
					$context['directory_list'][$path] = 1;
					$context['total_items'] += count_directories__recursive($path);
					$context['total_items']++;
				}
			}
		}

		// Have we built up our list of special files?
		if (!isset($_POST['specialFiles']) && $context['predefined_type'] != 'free')
		{
			$context['special_files'] = array();

			/**
			 * Builds a list of special files recursively for a given path
			 *
			 * @param string $path
			 * @param array $data
			 */
			function build_special_files__recursive($path, &$data)
			{
				global $context;

				if (!empty($data['writable_on']))
					if ($context['predefined_type'] == 'standard' || $data['writable_on'] == 'restrictive')
						$context['special_files'][$path] = 1;

				if (!empty($data['contents']))
					foreach ($data['contents'] as $name => $contents)
						build_special_files__recursive($path . '/' . $name, $contents);
			}

			foreach ($context['file_tree'] as $path => $data)
				build_special_files__recursive($path, $data);
		}
		// Free doesn't need special files.
		elseif ($context['predefined_type'] == 'free')
			$context['special_files'] = array();
		else
			$context['special_files'] = $smcFunc['json_decode'](base64_decode($_POST['specialFiles']), true);

		// Now we definitely know where we are, we need to go through again doing the chmod!
		foreach ($context['directory_list'] as $path => $dummy)
		{
			// Do the contents of the directory first.
			$dh = @opendir($path);
			$file_count = 0;
			$dont_chmod = false;
			while ($entry = readdir($dh))
			{
				$file_count++;
				// Actually process this file?
				if (!$dont_chmod && !is_dir($path . '/' . $entry) && (empty($context['file_offset']) || $context['file_offset'] < $file_count))
				{
					$status = $context['predefined_type'] == 'free' || isset($context['special_files'][$path . '/' . $entry]) ? 'writable' : 'execute';
					package_chmod($path . '/' . $entry, $status);
				}

				// See if we're out of time?
				if (!$dont_chmod && (time() - $time_start) > $timeout_limit)
				{
					$dont_chmod = true;
					// Don't do this again.
					$context['file_offset'] = $file_count;
				}
			}
			closedir($dh);

			// If this is set it means we timed out half way through.
			if ($dont_chmod)
			{
				$context['total_files'] = $file_count;
				$context['directory_list_encode'] = base64_encode($smcFunc['json_encode']($context['directory_list']));
				$context['special_files_encode'] = base64_encode($smcFunc['json_encode']($context['special_files']));
				return false;
			}

			// Do the actual directory.
			$status = $context['predefined_type'] == 'free' || isset($context['special_files'][$path]) ? 'writable' : 'execute';
			package_chmod($path, $status);

			// We've finished the directory so no file offset, and no record.
			$context['file_offset'] = 0;
			unset($context['directory_list'][$path]);

			// See if we're out of time?
			if ((time() - $time_start) > $timeout_limit)
			{
				// Prepare this for usage on templates.
				$context['directory_list_encode'] = base64_encode($smcFunc['json_encode']($context['directory_list']));
				$context['special_files_encode'] = base64_encode($smcFunc['json_encode']($context['special_files']));

				return false;
			}
		}

		// Prepare this for usage on templates.
		$context['directory_list_encode'] = base64_encode($smcFunc['json_encode']($context['directory_list']));
		$context['special_files_encode'] = base64_encode($smcFunc['json_encode']($context['special_files']));
	}

	// If we're here we are done!
	redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode($smcFunc['json_encode']($context['back_look_data'])) : '') . ';' . $context['session_var'] . '=' . $context['session_id']);
}

/**
 * Test an FTP connection.
 */
function PackageFTPTest()
{
	global $context, $txt, $package_ftp;

	checkSession('get');

	// Try to make the FTP connection.
	create_chmod_control(array(), array('force_find_error' => true));

	// Deal with the template stuff.
	loadTemplate('Xml');
	$context['sub_template'] = 'generic_xml';
	$context['template_layers'] = array();

	// Define the return data, this is simple.
	$context['xml_data'] = array(
		'results' => array(
			'identifier' => 'result',
			'children' => array(
				array(
					'attributes' => array(
						'success' => !empty($package_ftp) ? 1 : 0,
					),
					'value' => !empty($package_ftp) ? $txt['package_ftp_test_success'] : (isset($context['package_ftp'], $context['package_ftp']['error']) ? $context['package_ftp']['error'] : $txt['package_ftp_test_failed']),
				),
			),
		),
	);
}

?>