<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

namespace SMF\PackageManager;

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\EmailAddress;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Parser;
use SMF\Sapi;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * This is the main package manager.
 */
class PackageManager
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Delegation makes the world... that is, the package manager go 'round.
	 */
	public $subactions = [
		// Sub-actions for working with package files.
		'browse' => 'browse',
		'remove' => 'remove',
		'list' => 'list',
		'ftptest' => 'ftpTest',
		'install' => 'installTest',
		'install2' => 'install',
		'uninstall' => 'installTest',
		'uninstall2' => 'install',
		'options' => 'options',
		'perms' => 'permissions',
		'examine' => 'examineFile',
		'showoperations' => 'showOperations',

		// Sub-actions for working with package servers.
		'upload' => 'upload',
		'download' => 'download',
		'servers' => 'servers',
		'serveradd' => 'serverAdd',
		'serverremove' => 'serverRemove',
		'serverbrowse' => 'serverBrowse',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * For backward compatibility, maps the old names of some subactions that
	 * used to live in an obsolete PackageGet.php file to their new names.
	 */
	protected $packageget_subactions = [
		'upload' => 'upload',
		'download' => 'download',
		'servers' => 'servers',
		'add' => 'serveradd',
		'remove' => 'serverremove',
		'browse' => 'serverbrowse',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * An instance of this class.
	 */
	protected static $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Main dispatcher.
	 */
	public function execute(): void
	{
		// Load all the basic stuff.
		Theme::loadTemplate('Packages', 'admin');

		Utils::$context['page_title'] = Lang::getTxt('package', file: 'General');

		// Work out exactly who it is we are calling.
		if (isset($_REQUEST['sa'], $this->subactions[$_REQUEST['sa']])) {
			Utils::$context['sub_action'] = $_REQUEST['sa'];
		} else {
			Utils::$context['sub_action'] = 'browse';
		}

		// Set up some tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::getTxt('package_manager', file: 'Packages'),
			// @todo 'help' => 'registrations',
			'description' => Lang::getTxt('package_manager_desc', file: 'Packages'),
			'tabs' => [
				'browse' => [
				],
				'packageget' => [
					'description' => Lang::getTxt('download_packages_desc', file: 'Packages'),
				],
				'perms' => [
					'description' => Lang::getTxt('package_file_perms_desc', file: 'Packages'),
				],
				'options' => [
					'description' => Lang::getTxt('package_install_options_desc', file: 'Packages'),
				],
			],
		];

		if (Utils::$context['sub_action'] == 'browse') {
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
		}

		// We need to force the "Download" tab as selected.
		if (\in_array(Utils::$context['sub_action'], $this->packageget_subactions)) {
			Utils::$context['menu_data_' . Utils::$context['admin_menu_id']]['current_subsection'] = 'packageget';
		}

		// Call the function we're handing control to.
		if (method_exists($this, $this->subactions[Utils::$context['sub_action']])) {
			\call_user_func([$this, $this->subactions[Utils::$context['sub_action']]]);
		} else {
			$call = Utils::getCallable($this->subactions[Utils::$context['sub_action']]);

			if (!empty($call)) {
				\call_user_func($call);
			}
		}
	}

	/**
	 * Test install a package.
	 */
	public function installTest(): void
	{
		// You have to specify a file!!
		if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '') {
			Utils::redirectexit('action=admin;area=packages');
		}
		Utils::$context['filename'] = preg_replace('~[\.]+~', '.', $_REQUEST['package']);

		// Do we have an existing id, for uninstalls and the like.
		Utils::$context['install_id'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

		// Load up the package FTP information?
		PackageUtils::createChmodControl();

		// Make sure temp directory exists and is empty.
		if (file_exists(Config::$packagesdir . '/temp')) {
			PackageUtils::deltree(Config::$packagesdir . '/temp', false);
		}

		if (!PackageUtils::mktree(Config::$packagesdir . '/temp', 0755)) {
			PackageUtils::deltree(Config::$packagesdir . '/temp', false);

			if (!PackageUtils::mktree(Config::$packagesdir . '/temp', 0777)) {
				PackageUtils::deltree(Config::$packagesdir . '/temp', false);
				PackageUtils::createChmodControl([Config::$packagesdir . '/temp/delme.tmp'], ['destination_url' => Config::$scripturl . '?action=admin;area=packages;sa=' . $_REQUEST['sa'] . ';package=' . $_REQUEST['package'], 'crash_on_error' => true]);

				PackageUtils::deltree(Config::$packagesdir . '/temp', false);

				if (!PackageUtils::mktree(Config::$packagesdir . '/temp', 0777)) {
					ErrorHandler::fatalLang('package_cant_download', false);
				}
			}
		}

		Utils::$context['uninstalling'] = $_REQUEST['sa'] == 'uninstall';

		// Change our last link tree item for more information on this Packages area.
		Utils::$context['linktree'][\count(Utils::$context['linktree']) - 1] = [
			'url' => Config::$scripturl . '?action=admin;area=packages;sa=browse',
			'name' => Lang::getTxt(Utils::$context['uninstalling'] ? 'package_uninstall_actions' : 'install_actions', file: 'Packages'),
		];
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt(Utils::$context['uninstalling'] ? 'uninstall_actions' : 'install_actions', file: 'Packages');

		Utils::$context['sub_template'] = 'view_package';

		if (!file_exists(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			PackageUtils::deltree(Config::$packagesdir . '/temp');
			ErrorHandler::fatalLang('package_no_file', false);
		}

		// Extract the files so we can get things like the readme, etc.
		if (is_file(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['extracted_files'] = PackageUtils::readTgzFile(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');

			if (Utils::$context['extracted_files'] && !file_exists(Config::$packagesdir . '/temp/package-info.xml')) {
				foreach (Utils::$context['extracted_files'] as $file) {
					if (basename($file['filename']) == 'package-info.xml') {
						Utils::$context['base_path'] = \dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset(Utils::$context['base_path'])) {
				Utils::$context['base_path'] = '';
			}
		} elseif (is_dir(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			PackageUtils::copytree(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');
			Utils::$context['extracted_files'] = PackageUtils::listtree(Config::$packagesdir . '/temp');
			Utils::$context['base_path'] = '';
		} else {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Load up any custom themes we may want to install into...
		$request = Db::$db->query(
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
				AND variable IN ({string:name}, {string:theme_dir})',
			[
				'known_theme_list' => explode(',', Config::$modSettings['knownThemes']),
				'default_theme' => 1,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			],
		);
		$theme_paths = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);

		// Get the package info...
		$packageInfo = PackageUtils::getPackageInfo(Utils::$context['filename']);

		if (!\is_array($packageInfo)) {
			ErrorHandler::fatalLang($packageInfo);
		}

		$packageInfo['filename'] = Utils::$context['filename'];
		Utils::$context['package_name'] = $packageInfo['name'] ?? Utils::$context['filename'];

		// Set the type of extraction...
		Utils::$context['extract_type'] = $packageInfo['type'] ?? 'modification';

		// Get our validation info.
		Utils::$context['validation_tests'] = PackageUtils::validateInstallTest([
			'file_name' => Config::$packagesdir . '/' . Utils::$context['filename'],
			'custom_id' => !empty($packageInfo['id']) ? $packageInfo['id'] : '',
			'custom_type' => Utils::$context['extract_type'],
		]);

		// The mod isn't installed.... unless proven otherwise.
		Utils::$context['is_installed'] = false;

		// See if it is installed?
		$request = Db::$db->query(
			'SELECT version, themes_installed, db_changes
			FROM {db_prefix}log_packages
			WHERE package_id = {string:current_package}
				AND install_state != {int:not_installed}
			ORDER BY time_installed DESC
			LIMIT 1',
			[
				'not_installed' => 0,
				'current_package' => $packageInfo['id'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$old_themes = explode(',', $row['themes_installed']);
			$old_version = $row['version'];
			$db_changes = empty($row['db_changes']) ? [] : Utils::jsonDecode($row['db_changes'], true);
		}
		Db::$db->free_result($request);

		Utils::$context['database_changes'] = [];

		if (isset($packageInfo['uninstall']['database'])) {
			Utils::$context['database_changes'][] = Lang::getTxt('package_db_code', [$packageInfo['uninstall']['database']], file: 'Packages');
		}

		if (!empty($db_changes)) {
			foreach ($db_changes as $change) {
				if (Lang::txtExists('package_db_' . $change[0], file: 'Packages')) {
					Utils::$context['database_changes'][] = Lang::getTxt('package_db_' . array_shift($change), $change, file: 'Packages');
				} else {
					Utils::$context['database_changes'][] = $change[0] . '-' . $change[1] . (isset($change[2]) ? '-' . $change[2] : '');
				}
			}
		}

		// Uninstalling?
		if (Utils::$context['uninstalling']) {
			// Wait, it's not installed yet!
			if (!isset($old_version) && Utils::$context['uninstalling']) {
				PackageUtils::deltree(Config::$packagesdir . '/temp');
				ErrorHandler::fatalLang('package_cant_uninstall', false);
			}

			$actions = PackageUtils::parsePackageInfo($packageInfo['xml'], true, 'uninstall');

			// Gadzooks!  There's no uninstaller at all!?
			if (empty($actions)) {
				PackageUtils::deltree(Config::$packagesdir . '/temp');
				ErrorHandler::fatalLang('package_uninstall_cannot', false);
			}

			// Can't edit the custom themes it's edited if you're uninstalling, they must be removed.
			Utils::$context['themes_locked'] = true;

			// Only let them uninstall themes it was installed into.
			foreach ($theme_paths as $id => $data) {
				if ($id != 1 && !\in_array($id, $old_themes)) {
					unset($theme_paths[$id]);
				}
			}
		} elseif (isset($old_version) && $old_version != $packageInfo['version']) {
			// Look for an upgrade...
			$actions = PackageUtils::parsePackageInfo($packageInfo['xml'], true, 'upgrade', $old_version);

			// There was no upgrade....
			if (empty($actions)) {
				Utils::$context['is_installed'] = true;
			} else {
				// Otherwise they can only upgrade themes from the first time around.
				foreach ($theme_paths as $id => $data) {
					if ($id != 1 && !\in_array($id, $old_themes)) {
						unset($theme_paths[$id]);
					}
				}
			}
		} elseif (isset($old_version) && $old_version == $packageInfo['version']) {
			Utils::$context['is_installed'] = true;
		}

		if (!isset($old_version) || Utils::$context['is_installed']) {
			$actions = PackageUtils::parsePackageInfo($packageInfo['xml'], true, 'install');
		}

		Utils::$context['actions'] = [];
		Utils::$context['ftp_needed'] = false;
		Utils::$context['has_failure'] = false;
		$chmod_files = [];

		// no actions found, return so we can display an error
		if (empty($actions)) {
			return;
		}

		// This will hold data about anything that can be installed in other themes.
		$themeFinds = [
			'candidates' => [],
			'other_themes' => [],
		];

		// Now prepare things for the template.
		foreach ($actions as $action) {
			// Not failed until proven otherwise.
			$failed = false;
			$thisAction = [];

			if ($action['type'] == 'chmod') {
				$chmod_files[] = $action['filename'];

				continue;
			}

			if ($action['type'] == 'readme' || $action['type'] == 'license') {
				$type = 'package_' . $action['type'];

				if (file_exists(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'])) {
					Utils::$context[$type] = Utils::htmlspecialchars(trim(file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), "\n\r"));
				} elseif (file_exists($action['filename'])) {
					Utils::$context[$type] = Utils::htmlspecialchars(trim(file_get_contents($action['filename']), "\n\r"));
				}

				if (!empty($action['parse_bbc'])) {
					Utils::$context[$type] = Parser::transform(Parser::sanitize(preg_replace('~\[/?html\]~i', '', Utils::$context[$type])));
				} else {
					Utils::$context[$type] = nl2br(Utils::$context[$type]);
				}

				continue;
			}

			// Don't show redirects.
			if ($action['type'] == 'redirect') {
				continue;
			}

			if ($action['type'] == 'error') {
				Utils::$context['has_failure'] = true;

				if (isset($action['error_msg'])) {
					Utils::$context['failure_details'] = Lang::txtExists('package_will_fail_' . $action['error_msg'], file: 'Packages') ? Lang::getTxt('package_will_fail_' . $action['error_msg'], [$action['error_var'] ?? ''], file: 'Packages') : Lang::formatText($action['error_msg'], [$action['error_var'] ?? '']);
				}
			} elseif ($action['type'] == 'modification') {
				if (!file_exists(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'])) {
					Utils::$context['has_failure'] = true;

					Utils::$context['actions'][] = [
						'type' => Lang::getTxt('execute_modification', file: 'Packages'),
						'action' => Utils::htmlspecialchars(strtr($action['filename'], [Config::$boarddir => '.'])),
						'description' => Lang::getTxt('package_action_missing', file: 'Packages'),
						'failed' => true,
					];
				} else {
					if ($action['diff']) {
						$mod_actions = PackageUtils::parseDiff(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), true, $action['reverse'], $theme_paths);
					} elseif ($action['boardmod']) {
						$mod_actions = PackageUtils::parseBoardMod(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), true, $action['reverse'], $theme_paths);
					} else {
						$mod_actions = PackageUtils::parseModification(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), true, $action['reverse'], $theme_paths);
					}

					if (\count($mod_actions) == 1 && isset($mod_actions[0]) && $mod_actions[0]['type'] == 'error' && $mod_actions[0]['filename'] == '-') {
						$mod_actions[0]['filename'] = $action['filename'];
					}

					foreach ($mod_actions as $key => $mod_action) {
						// Lets get the last section of the file name.
						if (isset($mod_action['filename']) && !str_ends_with($mod_action['filename'], '.template.php')) {
							$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
						} elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches)) {
							$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php' . '||' . $action['filename']);
						} else {
							$actual_filename = $key;
						}

						if ($mod_action['type'] == 'opened') {
							$failed = false;
						} elseif ($mod_action['type'] == 'failure') {
							if (empty($mod_action['is_custom'])) {
								Utils::$context['has_failure'] = true;
							}
							$failed = true;
						} elseif ($mod_action['type'] == 'chmod') {
							$chmod_files[] = $mod_action['filename'];
						} elseif ($mod_action['type'] == 'saved') {
							if (!empty($mod_action['is_custom'])) {
								if (!isset(Utils::$context['theme_actions'][$mod_action['is_custom']])) {
									Utils::$context['theme_actions'][$mod_action['is_custom']] = [
										'name' => $theme_paths[$mod_action['is_custom']]['name'],
										'actions' => [],
										'has_failure' => $failed,
									];
								} else {
									Utils::$context['theme_actions'][$mod_action['is_custom']]['has_failure'] |= $failed;
								}

								Utils::$context['theme_actions'][$mod_action['is_custom']]['actions'][$actual_filename] = [
									'type' => Lang::getTxt('execute_modification', file: 'Packages'),
									'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
									'description' => Lang::getTxt($failed ? 'package_action_failure' : 'package_action_success', file: 'Packages'),
									'failed' => $failed,
								];
							} elseif (!isset(Utils::$context['actions'][$actual_filename])) {
								Utils::$context['actions'][$actual_filename] = [
									'type' => Lang::getTxt('execute_modification', file: 'Packages'),
									'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
									'description' => Lang::getTxt($failed ? 'package_action_failure' : 'package_action_success', file: 'Packages'),
									'failed' => $failed,
								];
							} else {
								Utils::$context['actions'][$actual_filename]['failed'] |= $failed;
								Utils::$context['actions'][$actual_filename]['description'] = Lang::getTxt(Utils::$context['actions'][$actual_filename]['failed'] ? 'package_action_failure' : 'package_action_success', file: 'Packages');
							}
						} elseif ($mod_action['type'] == 'skipping') {
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('execute_modification', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
								'description' => Lang::getTxt('package_action_skipping', file: 'Packages'),
							];
						} elseif ($mod_action['type'] == 'missing' && empty($mod_action['is_custom'])) {
							Utils::$context['has_failure'] = true;
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('execute_modification', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
								'description' => Lang::getTxt('package_action_missing', file: 'Packages'),
								'failed' => true,
							];
						} elseif ($mod_action['type'] == 'error') {
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('execute_modification', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
								'description' => Lang::getTxt('package_action_error', file: 'Packages'),
								'failed' => true,
							];
						} elseif ($mod_action['type'] == 'create-file') {
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('package_create_file', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
								'description' => $failed ? Lang::getTxt('package_action_failure', file: 'Packages') : '',
								'failed' => $failed,
							];
						} elseif ($mod_action['type'] == 'move-file') {
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('package_move_file', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['source'], [Config::$boarddir => '.'])) . ' => ' . Utils::htmlspecialchars(strtr($mod_action['destination'], [Config::$boarddir => '.'])),
								'description' => $failed ? Lang::getTxt('package_action_missing', file: 'Packages') : '',
								'failed' => $failed,
							];
						} elseif ($mod_action['type'] == 'remove-file') {
							Utils::$context['actions'][$actual_filename] = [
								'type' => Lang::getTxt('package_delete_file', file: 'Packages'),
								'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
								'description' => $failed ? Lang::getTxt('package_action_missing', file: 'Packages') : '',
								'failed' => $failed,
							];
						}
					}

					// We need to loop again just to get the operations down correctly.
					foreach ($mod_actions as $operation_key => $mod_action) {
						// Lets get the last section of the file name.
						if (isset($mod_action['filename']) && !str_ends_with($mod_action['filename'], '.template.php')) {
							$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
						} elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches)) {
							$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php' . '||' . $action['filename']);
						} else {
							$actual_filename = $key;
						}

						// We just need it for actual parse changes.
						if (!\in_array($mod_action['type'], ['error', 'result', 'opened', 'saved', 'end', 'missing', 'skipping', 'chmod', 'create-file', 'move-file', 'remove-file'])) {
							if (!isset(Utils::$context['actions'][$actual_filename])) {
								Utils::$context['actions'][$actual_filename] = [
									'type' => Lang::getTxt('execute_modification', file: 'Packages'),
									'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
									'description' => $failed ? Lang::getTxt('package_action_failure', file: 'Packages') : Lang::getTxt('package_action_success', file: 'Packages'),
									'failed' => $failed,
								];
							}

							if (empty($mod_action['is_custom'])) {
								Utils::$context['actions'][$actual_filename]['operations'][] = [
									'type' => Lang::getTxt('execute_modification', file: 'Packages'),
									'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
									'description' => Lang::getTxt($mod_action['failed'] ? 'package_action_failure' : 'package_action_success', file: 'Packages'),
									'position' => $mod_action['position'],
									'operation_key' => $operation_key,
									'filename' => $action['filename'],
									'is_boardmod' => $action['boardmod'],
									'is_diff' => $action['diff'],
									'failed' => $mod_action['failed'],
									'ignore_failure' => !empty($mod_action['ignore_failure']),
								];
							}

							// Themes are under the saved type.
							if (isset($mod_action['is_custom'], Utils::$context['theme_actions'][$mod_action['is_custom']])) {
								Utils::$context['theme_actions'][$mod_action['is_custom']]['actions'][$actual_filename]['operations'][] = [
									'type' => Lang::getTxt('execute_modification', file: 'Packages'),
									'action' => Utils::htmlspecialchars(strtr($mod_action['filename'], [Config::$boarddir => '.'])),
									'description' => Lang::getTxt($mod_action['failed'] ? 'package_action_failure' : 'package_action_success', file: 'Packages'),
									'position' => $mod_action['position'],
									'operation_key' => $operation_key,
									'filename' => $action['filename'],
									'is_boardmod' => $action['boardmod'],
									'is_diff' => $action['diff'],
									'failed' => $mod_action['failed'],
									'ignore_failure' => !empty($mod_action['ignore_failure']),
								];
							}
						}
					}
				}
			} elseif ($action['type'] == 'code') {
				$thisAction = [
					'type' => Lang::getTxt('execute_code', file: 'Packages'),
					'action' => Utils::htmlspecialchars($action['filename']),
				];
			} elseif ($action['type'] == 'database' && !Utils::$context['uninstalling']) {
				$thisAction = [
					'type' => Lang::getTxt('execute_database_changes', file: 'Packages'),
					'action' => Utils::htmlspecialchars($action['filename']),
				];
			} elseif (\in_array($action['type'], ['create-dir', 'create-file'])) {
				$thisAction = [
					'type' => Lang::getTxt($action['type'] == 'create-dir' ? 'package_create_tree' : 'package_create_file', file: 'Packages'),
					'action' => Utils::htmlspecialchars(strtr($action['destination'], [Config::$boarddir => '.'])),
				];
			} elseif ($action['type'] == 'hook') {
				$action['description'] = Lang::getTxt(!isset($action['hook'], $action['function']) ? 'package_action_failure' : 'package_action_success', file: 'Packages');

				if (!isset($action['hook'], $action['function'])) {
					Utils::$context['has_failure'] = true;
				}

				$thisAction = [
					'type' => Lang::getTxt($action['reverse'] ? 'execute_hook_remove' : 'execute_hook_add', file: 'Packages'),
					'action' => Lang::getTxt('execute_hook_action' . ($action['reverse'] ? '_inverse' : ''), ['hook' => Utils::htmlspecialchars($action['hook'])], file: 'Packages'),
				];
			} elseif ($action['type'] == 'credits') {
				$thisAction = [
					'type' => Lang::getTxt('execute_credits_add', file: 'Packages'),
					'action' => Lang::getTxt('execute_credits_action', ['title' => Utils::htmlspecialchars($action['title'])], file: 'Packages'),
				];
			} elseif ($action['type'] == 'requires') {
				$installed = false;
				$version = true;

				// package missing required values?
				if (!isset($action['id'])) {
					Utils::$context['has_failure'] = true;
				} else {
					// See if this dependency is installed
					$request = Db::$db->query(
						'SELECT version
						FROM {db_prefix}log_packages
						WHERE package_id = {string:current_package}
							AND install_state != {int:not_installed}
						ORDER BY time_installed DESC
						LIMIT 1',
						[
							'not_installed' => 0,
							'current_package' => $action['id'],
						],
					);
					$installed = (Db::$db->num_rows($request) !== 0);

					if ($installed) {
						list($version) = Db::$db->fetch_row($request);
					}
					Db::$db->free_result($request);

					// do a version level check (if requested) in the most basic way
					$version = (isset($action['version']) ? $version == $action['version'] : true);
				}

				// Set success or failure information
				$action['description'] = Lang::getTxt(($installed && $version) ? 'package_action_success' : 'package_action_failure', file: 'Packages');
				Utils::$context['has_failure'] = !($installed && $version);

				$thisAction = [
					'type' => Lang::getTxt('package_requires', file: 'Packages'),
					'action' => Lang::getTxt('package_check_for', ['requirement' => $action['id'] . (isset($action['version']) ? (' / ' . ($version ? $action['version'] : '<span class="error">' . $action['version'] . '</span>')) : '')], file: 'Packages'),
				];
			} elseif (\in_array($action['type'], ['require-dir', 'require-file'])) {
				// Do this one...
				$thisAction = [
					'type' => Lang::getTxt($action['type'] == 'require-dir' ? 'package_extract_tree' : 'package_extract_file', file: 'Packages'),
					'action' => Utils::htmlspecialchars(strtr($action['destination'], [Config::$boarddir => '.'])),
				];

				// Could this be theme related?
				if (!empty($action['unparsed_destination']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_destination'], $matches)) {
					// Is the action already stated?
					$theme_action = !empty($action['theme_action']) && \in_array($action['theme_action'], ['no', 'yes', 'auto']) ? $action['theme_action'] : 'auto';

					// If it's not auto do we think we have something we can act upon?
					if ($theme_action != 'auto' && !\in_array($matches[1], ['languagedir', 'languages_dir', 'imagesdir', 'themedir'])) {
						$theme_action = '';
					}
					// ... or if it's auto do we even want to do anything?
					elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir') {
						$theme_action = '';
					}

					// So, we still want to do something?
					if ($theme_action != '') {
						$themeFinds['candidates'][] = $action;
					}
					// Otherwise is this is going into another theme record it.
					elseif ($matches[1] == 'themes_dir') {
						$themeFinds['other_themes'][] = strtolower(strtr(PackageUtils::parsePath($action['unparsed_destination']), ['\\' => '/']) . '/' . basename($action['filename']));
					}
				}
			} elseif (\in_array($action['type'], ['move-dir', 'move-file'])) {
				$thisAction = [
					'type' => Lang::getTxt($action['type'] == 'move-dir' ? 'package_move_tree' : 'package_move_file', file: 'Packages'),
					'action' => Utils::htmlspecialchars(strtr($action['source'], [Config::$boarddir => '.'])) . ' => ' . Utils::htmlspecialchars(strtr($action['destination'], [Config::$boarddir => '.'])),
				];
			} elseif (\in_array($action['type'], ['remove-dir', 'remove-file'])) {
				$thisAction = [
					'type' => Lang::getTxt($action['type'] == 'remove-dir' ? 'package_delete_tree' : 'package_delete_file', file: 'Packages'),
					'action' => Utils::htmlspecialchars(strtr($action['filename'], [Config::$boarddir => '.'])),
				];

				// Could this be theme related?
				if (!empty($action['unparsed_filename']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_filename'], $matches)) {
					// Is the action already stated?
					$theme_action = !empty($action['theme_action']) && \in_array($action['theme_action'], ['no', 'yes', 'auto']) ? $action['theme_action'] : 'auto';
					$action['unparsed_destination'] = $action['unparsed_filename'];

					// If it's not auto do we think we have something we can act upon?
					if ($theme_action != 'auto' && !\in_array($matches[1], ['languagedir', 'languages_dir', 'imagesdir', 'themedir'])) {
						$theme_action = '';
					}
					// ... or if it's auto do we even want to do anything?
					elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir') {
						$theme_action = '';
					}

					// So, we still want to do something?
					if ($theme_action != '') {
						$themeFinds['candidates'][] = $action;
					}
					// Otherwise is this is going into another theme record it.
					elseif ($matches[1] == 'themes_dir') {
						$themeFinds['other_themes'][] = strtolower(strtr(PackageUtils::parsePath($action['unparsed_filename']), ['\\' => '/']) . '/' . basename($action['filename']));
					}
				}
			}

			if (empty($thisAction)) {
				continue;
			}

			if (!\in_array($action['type'], ['hook', 'credits'])) {
				if (Utils::$context['uninstalling']) {
					$file = \in_array($action['type'], ['remove-dir', 'remove-file']) ? $action['filename'] : Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'];
				} else {
					$file = Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'];
				}
			}

			// Don't fail if a file/directory we're trying to create doesn't exist...
			if (isset($action['filename']) && !file_exists($file) && !\in_array($action['type'], ['create-dir', 'create-file', 'move-dir', 'move-file']) && $action['error'] != 'ignore') {
				Utils::$context['has_failure'] = true;

				$thisAction += [
					'description' => Lang::getTxt('package_action_missing', file: 'Packages'),
					'failed' => true,
				];
			}

			// @todo None given?
			if (empty($thisAction['description'])) {
				$thisAction['description'] = $action['description'] ?? '';
			}

			Utils::$context['actions'][] = $thisAction;
		}

		// Have we got some things which we might want to do "multi-theme"?
		if (!empty($themeFinds['candidates'])) {
			foreach ($themeFinds['candidates'] as $action_data) {
				// Get the part of the file we'll be dealing with.
				preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir)(\\|/)*(.+)*~i', $action_data['unparsed_destination'], $matches);

				if ($matches[1] == 'imagesdir') {
					$path = '/' . basename(Theme::$current->settings['default_images_url']);
				} elseif ($matches[1] == 'languagedir' || $matches[1] == 'languages_dir') {
					$path = '/languages';
				} else {
					$path = '';
				}

				if (!empty($matches[3])) {
					$path .= $matches[3];
				}

				if (!Utils::$context['uninstalling']) {
					$path .= '/' . basename($action_data['filename']);
				}

				// Loop through each custom theme to note it's candidacy!
				foreach ($theme_paths as $id => $theme_data) {
					if (isset($theme_data['theme_dir']) && $id != 1) {
						$real_path = $theme_data['theme_dir'] . $path;

						// Confirm that we don't already have this dealt with by another entry.
						if (!\in_array(strtolower(strtr($real_path, ['\\' => '/'])), $themeFinds['other_themes'])) {
							// Check if we will need to chmod this.
							if (!PackageUtils::mktree(\dirname($real_path), false)) {
								$temp = \dirname($real_path);

								while (!file_exists($temp) && \strlen($temp) > 1) {
									$temp = \dirname($temp);
								}
								$chmod_files[] = $temp;
							}

							if ($action_data['type'] == 'require-dir' && !is_writable($real_path) && (file_exists($real_path) || !is_writable(\dirname($real_path)))) {
								$chmod_files[] = $real_path;
							}

							if (!isset(Utils::$context['theme_actions'][$id])) {
								Utils::$context['theme_actions'][$id] = [
									'name' => $theme_data['name'],
									'actions' => [],
								];
							}

							if (Utils::$context['uninstalling']) {
								Utils::$context['theme_actions'][$id]['actions'][] = [
									'type' => Lang::getTxt($action_data['type'] == 'require-dir' ? 'package_delete_tree' : 'package_delete_file', file: 'Packages'),
									'action' => strtr($real_path, ['\\' => '/', Config::$boarddir => '.']),
									'description' => '',
									'value' => base64_encode(Utils::jsonEncode(['type' => $action_data['type'], 'orig' => $action_data['filename'], 'future' => $real_path, 'id' => $id])),
									'not_mod' => true,
								];
							} else {
								Utils::$context['theme_actions'][$id]['actions'][] = [
									'type' => Lang::getTxt($action_data['type'] == 'require-dir' ? 'package_extract_tree' : 'package_extract_file', file: 'Packages'),
									'action' => strtr($real_path, ['\\' => '/', Config::$boarddir => '.']),
									'description' => '',
									'value' => base64_encode(Utils::jsonEncode(['type' => $action_data['type'], 'orig' => $action_data['destination'], 'future' => $real_path, 'id' => $id])),
									'not_mod' => true,
								];
							}
						}
					}
				}
			}
		}

		// Trash the cache... which will also check permissions for us!
		PackageUtils::flushCache(true);

		if (file_exists(Config::$packagesdir . '/temp')) {
			PackageUtils::deltree(Config::$packagesdir . '/temp');
		}

		if (!empty($chmod_files)) {
			$ftp_status = PackageUtils::createChmodControl($chmod_files);
			Utils::$context['ftp_needed'] = !empty($ftp_status['files']['notwritable']) && !empty(Utils::$context['package_ftp']);
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=packages;sa=' . (Utils::$context['uninstalling'] ? 'uninstall' : 'install') . (Utils::$context['ftp_needed'] ? '' : '2') . ';package=' . Utils::$context['filename'] . ';pid=' . Utils::$context['install_id'];
		Security::checkSubmitOnce('register');
	}

	/**
	 * Apply another type of (avatar, language, etc.) package.
	 */
	public function install(): void
	{
		// Make sure we don't install this mod twice.
		Security::checkSubmitOnce('check');
		User::$me->checkSession();

		// If there's no file, what are we installing?
		if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '') {
			Utils::redirectexit('action=admin;area=packages');
		}
		Utils::$context['filename'] = $_REQUEST['package'];

		// If this is an uninstall, we'll have an id.
		Utils::$context['install_id'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

		// @todo Perhaps do it in steps, if necessary?

		Utils::$context['uninstalling'] = $_REQUEST['sa'] == 'uninstall2';

		// Set up the linktree for other.
		Utils::$context['linktree'][\count(Utils::$context['linktree']) - 1] = [
			'url' => Config::$scripturl . '?action=admin;area=packages;sa=browse',
			'name' => Lang::getTxt(Utils::$context['uninstalling'] ? 'uninstall' : 'extracting', file: 'Packages'),
		];
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt(Utils::$context['uninstalling'] ? 'uninstall' : 'extracting', file: 'Packages');

		Utils::$context['sub_template'] = 'extract_package';

		if (!file_exists(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			ErrorHandler::fatalLang('package_no_file', false);
		}

		// Load up the package FTP information?
		PackageUtils::createChmodControl([], ['destination_url' => Config::$scripturl . '?action=admin;area=packages;sa=' . $_REQUEST['sa'] . ';package=' . $_REQUEST['package']]);

		// Make sure temp directory exists and is empty!
		if (file_exists(Config::$packagesdir . '/temp')) {
			PackageUtils::deltree(Config::$packagesdir . '/temp', false);
		} else {
			PackageUtils::mktree(Config::$packagesdir . '/temp', 0777);
		}

		// Let the unpacker do the work.
		if (is_file(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['extracted_files'] = PackageUtils::readTgzFile(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');

			if (!file_exists(Config::$packagesdir . '/temp/package-info.xml')) {
				foreach (Utils::$context['extracted_files'] as $file) {
					if (basename($file['filename']) == 'package-info.xml') {
						Utils::$context['base_path'] = \dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset(Utils::$context['base_path'])) {
				Utils::$context['base_path'] = '';
			}
		} elseif (is_dir(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			PackageUtils::copytree(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');
			Utils::$context['extracted_files'] = PackageUtils::listtree(Config::$packagesdir . '/temp');
			Utils::$context['base_path'] = '';
		} else {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Are we installing this into any custom themes?
		$custom_themes = [1];
		$known_themes = explode(',', Config::$modSettings['knownThemes']);

		if (!empty($_POST['custom_theme'])) {
			foreach ($_POST['custom_theme'] as $tid) {
				if (\in_array($tid, $known_themes)) {
					$custom_themes[] = (int) $tid;
				}
			}
		}

		// Now load up the paths of the themes that we need to know about.
		$request = Db::$db->query(
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN ({array_int:custom_themes})
				AND variable IN ({string:name}, {string:theme_dir})',
			[
				'custom_themes' => $custom_themes,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			],
		);
		$theme_paths = [];
		$themes_installed = [1];

		while ($row = Db::$db->fetch_assoc($request)) {
			$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
		}

		Db::$db->free_result($request);

		// Are there any theme copying that we want to take place?
		Utils::$context['theme_copies'] = [
			'require-file' => [],
			'require-dir' => [],
		];

		if (!empty($_POST['theme_changes'])) {
			foreach ($_POST['theme_changes'] as $change) {
				if (empty($change)) {
					continue;
				}
				$theme_data = Utils::jsonDecode(base64_decode($change), true);

				if (empty($theme_data['type'])) {
					continue;
				}

				$themes_installed[] = $theme_data['id'];
				Utils::$context['theme_copies'][$theme_data['type']][$theme_data['orig']][] = $theme_data['future'];
			}
		}

		// Get the package info...
		$packageInfo = PackageUtils::getPackageInfo(Utils::$context['filename']);

		if (!\is_array($packageInfo)) {
			ErrorHandler::fatalLang($packageInfo);
		}

		if (is_dir(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['package_sha256_hash'] = '';
		} else {
			Utils::$context['package_sha256_hash'] = hash_file('sha256', Config::$packagesdir . '/' . Utils::$context['filename']);
		}
		$packageInfo['filename'] = Utils::$context['filename'];

		// Set the type of extraction...
		Utils::$context['extract_type'] = $packageInfo['type'] ?? 'modification';

		// Create a backup file to roll back to! (but if they do this more than once, don't run it a zillion times.)
		if (!empty(Config::$modSettings['package_make_full_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != Utils::$context['filename'] . (Utils::$context['uninstalling'] ? '$$' : '$'))) {
			$_SESSION['last_backup_for'] = Utils::$context['filename'] . (Utils::$context['uninstalling'] ? '$$' : '$');
			$result = PackageUtils::createBackup((Utils::$context['uninstalling'] ? 'backup_' : 'before_') . strtok(Utils::$context['filename'], '.'));

			if (!$result) {
				ErrorHandler::fatalLang('could_not_package_backup', false);
			}
		}

		// The mod isn't installed.... unless proven otherwise.
		Utils::$context['is_installed'] = false;

		// Is it actually installed?
		$request = Db::$db->query(
			'SELECT version, themes_installed, db_changes
			FROM {db_prefix}log_packages
			WHERE package_id = {string:current_package}
				AND install_state != {int:not_installed}
			ORDER BY time_installed DESC
			LIMIT 1',
			[
				'not_installed' => 0,
				'current_package' => $packageInfo['id'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$old_themes = explode(',', $row['themes_installed']);
			$old_version = $row['version'];
			$db_changes = empty($row['db_changes']) ? [] : Utils::jsonDecode($row['db_changes'], true);
		}
		Db::$db->free_result($request);

		// Wait, it's not installed yet!
		// @todo Replace with a better error message!
		if (!isset($old_version) && Utils::$context['uninstalling']) {
			PackageUtils::deltree(Config::$packagesdir . '/temp');
			ErrorHandler::fatal('Hacker?', false);
		}
		// Uninstalling?
		elseif (Utils::$context['uninstalling']) {
			$install_log = PackageUtils::parsePackageInfo($packageInfo['xml'], false, 'uninstall');

			// Gadzooks!  There's no uninstaller at all!?
			if (empty($install_log)) {
				ErrorHandler::fatalLang('package_uninstall_cannot', false);
			}

			// They can only uninstall from what it was originally installed into.
			foreach ($theme_paths as $id => $data) {
				if ($id != 1 && !\in_array($id, $old_themes)) {
					unset($theme_paths[$id]);
				}
			}

			Utils::$context['keep_url'] = Config::$scripturl . '?action=admin;area=packages;sa=browse;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
			Utils::$context['remove_url'] = Config::$scripturl . '?action=admin;area=packages;sa=remove;package=' . Utils::$context['filename'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		} elseif (isset($old_version) && $old_version != $packageInfo['version']) {
			// Look for an upgrade...
			$install_log = PackageUtils::parsePackageInfo($packageInfo['xml'], false, 'upgrade', $old_version);

			// There was no upgrade....
			if (empty($install_log)) {
				Utils::$context['is_installed'] = true;
			} else {
				// Upgrade previous themes only!
				foreach ($theme_paths as $id => $data) {
					if ($id != 1 && !\in_array($id, $old_themes)) {
						unset($theme_paths[$id]);
					}
				}
			}
		} elseif (isset($old_version) && $old_version == $packageInfo['version']) {
			Utils::$context['is_installed'] = true;
		}

		if (!isset($old_version) || Utils::$context['is_installed']) {
			$install_log = PackageUtils::parsePackageInfo($packageInfo['xml'], false, 'install');
		}

		// Disable background tasks while the code is in flux.
		Config::updateSettingsFile(['package_installing' => true]);

		Utils::$context['install_finished'] = false;

		// @todo Make a log of any errors that occurred and output them?

		if (!empty($install_log)) {
			$failed_steps = [];
			$failed_count = 0;

			foreach ($install_log as $action) {
				$failed_count++;

				if ($action['type'] == 'modification' && !empty($action['filename'])) {
					if ($action['diff']) {
						$mod_actions = PackageUtils::parseDiff(file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), false, $action['reverse'], $theme_paths);
					} elseif ($action['boardmod']) {
						$mod_actions = PackageUtils::parseBoardMod(file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), false, $action['reverse'], $theme_paths);
					} else {
						$mod_actions = PackageUtils::parseModification(file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']), false, $action['reverse'], $theme_paths);
					}

					// Any errors worth noting?
					foreach ($mod_actions as $key => $modAction) {
						if ($modAction['type'] == 'failure') {
							$failed_steps[] = [
								'file' => $modAction['filename'],
								'large_step' => $failed_count,
								'sub_step' => $key,
								'theme' => 1,
							];
						}

						// Gather the themes we installed into.
						if (!empty($modAction['is_custom'])) {
							$themes_installed[] = $modAction['is_custom'];
						}
					}
				} elseif ($action['type'] == 'code' && !empty($action['filename'])) {
					// Now include the file and be done with it ;).
					if (file_exists(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'])) {
						// Get all our backward compatibility globals into scope
						// just in case the file's code wants to use them.
						if (!empty(Config::$backward_compatibility)) {
							$backcompat_globals = [
								'boardurl' => &Config::$boardurl,
								'scripturl' => &Config::$scripturl,
								'sourcedir' => &Config::$sourcedir,
								'packagesdir' => &Config::$packagesdir,
								'modSettings' => &Config::$modSettings,
								'context' => &Utils::$context,
								'smcFunc' => &Utils::$smcFunc,
								'txt' => &Lang::$txt,
								'user_info' => &User::$me,
								'settings' => &Theme::$current->settings,
							];

							extract($backcompat_globals, EXTR_REFS | EXTR_SKIP);
						}

						require Sapi::canonicalPath(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']);
					}
				} elseif ($action['type'] == 'credits') {
					// Time to build the billboard
					$credits_tag = [
						'url' => $action['url'],
						'license' => $action['license'],
						'licenseurl' => $action['licenseurl'],
						'copyright' => $action['copyright'],
						'title' => $action['title'],
					];
				} elseif ($action['type'] == 'hook' && isset($action['hook'], $action['function'])) {
					// Set the system to ignore hooks, but only if it wasn't changed before.
					if (!isset(Utils::$context['ignore_hook_errors'])) {
						Utils::$context['ignore_hook_errors'] = true;
					}

					if ($action['reverse']) {
						IntegrationHook::remove($action['hook'], $action['function'], true, $action['include_file'], $action['object']);
					} else {
						IntegrationHook::add($action['hook'], $action['function'], true, $action['include_file'], $action['object']);
					}
				}
				// Only do the database changes on uninstall if requested.
				elseif ($action['type'] == 'database' && !empty($action['filename']) && (!Utils::$context['uninstalling'] || !empty($_POST['do_db_changes']))) {
					// Let the file work its magic ;)
					if (file_exists(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'])) {
						// Get all our backward compatibility globals into scope
						// just in case the file's code wants to use them.
						if (!empty(Config::$backward_compatibility)) {
							$backcompat_globals = [
								'boardurl' => &Config::$boardurl,
								'scripturl' => &Config::$scripturl,
								'sourcedir' => &Config::$sourcedir,
								'packagesdir' => &Config::$packagesdir,
								'modSettings' => &Config::$modSettings,
								'context' => &Utils::$context,
								'smcFunc' => &Utils::$smcFunc,
								'txt' => &Lang::$txt,
								'user_info' => &User::$me,
								'settings' => &Theme::$current->settings,
							];

							extract($backcompat_globals, EXTR_REFS | EXTR_SKIP);
						}

						require Sapi::canonicalPath(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']);
					}
				}
				// Handle a redirect...
				elseif ($action['type'] == 'redirect' && !empty($action['redirect_url'])) {
					Utils::$context['redirect_url'] = $action['redirect_url'];
					Utils::$context['redirect_text'] = !empty($action['filename']) && file_exists(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename']) ? Utils::htmlspecialchars(file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $action['filename'])) : Lang::getTxt(Utils::$context['uninstalling'] ? 'package_uninstall_done' : 'package_installed_done', file: 'Packages');
					Utils::$context['redirect_timeout'] = empty($action['redirect_timeout']) ? 5 : (int) ceil($action['redirect_timeout'] / 1000);

					if (!empty($action['parse_bbc'])) {
						Utils::$context['redirect_text'] = Parser::transform(Parser::sanitize(preg_replace('~\[/?html\]~i', '', Utils::$context['redirect_text'])));
					}

					// Parse out a couple of common urls.
					$urls = [
						'$boardurl' => Config::$boardurl,
						'$scripturl' => Config::$scripturl,
						'$session_var' => Utils::$context['session_var'],
						'$session_id' => Utils::$context['session_id'],
					];

					Utils::$context['redirect_url'] = strtr(Utils::$context['redirect_url'], $urls);
				}
			}

			PackageUtils::flushCache();

			// See if this is already installed, and change it's state as required.
			$request = Db::$db->query(
				'SELECT package_id, install_state, db_changes
				FROM {db_prefix}log_packages
				WHERE install_state != {int:not_installed}
					AND package_id = {string:current_package}
					' . (Utils::$context['install_id'] ? ' AND id_install = {int:install_id} ' : '') . '
				ORDER BY time_installed DESC
				LIMIT 1',
				[
					'not_installed' => 0,
					'install_id' => Utils::$context['install_id'],
					'current_package' => $packageInfo['id'],
				],
			);
			$is_upgrade = false;

			while ($row = Db::$db->fetch_assoc($request)) {
				// Uninstalling?
				if (Utils::$context['uninstalling']) {
					Db::$db->query(
						'UPDATE {db_prefix}log_packages
						SET
							install_state = {int:not_installed},
							member_removed = {string:member_name},
							id_member_removed = {int:current_member},
							time_removed = {int:current_time},
							sha256_hash = {string:package_hash}
						WHERE package_id = {string:package_id}
							AND id_install = {int:install_id}',
						[
							'current_member' => User::$me->id,
							'not_installed' => 0,
							'current_time' => time(),
							'package_id' => $row['package_id'],
							'member_name' => User::$me->name,
							'install_id' => Utils::$context['install_id'],
							'package_hash' => Utils::$context['package_sha256_hash'],
						],
					);
				}
				// Otherwise must be an upgrade.
				else {
					$is_upgrade = true;
					$old_db_changes = empty($row['db_changes']) ? [] : Utils::jsonDecode($row['db_changes'], true);

					// Mark the old version as uninstalled
					Db::$db->query(
						'UPDATE {db_prefix}log_packages
						SET
							install_state = {int:not_installed},
							member_removed = {string:member_name},
							id_member_removed = {int:current_member},
							time_removed = {int:current_time},
							sha256_hash = {string:package_hash}
						WHERE package_id = {string:package_id}
							AND version = {string:old_version}',
						[
							'current_member' => User::$me->id,
							'not_installed' => 0,
							'current_time' => time(),
							'package_id' => $row['package_id'],
							'member_name' => User::$me->name,
							'old_version' => $old_version,
							'package_hash' => Utils::$context['package_sha256_hash'],
						],
					);
				}
			}

			// Assuming we're not uninstalling, add the entry.
			if (!Utils::$context['uninstalling']) {
				// Reload the settings table for mods that have altered them upon installation
				Config::reloadModSettings();

				// Any db changes from older version?
				if (!empty($old_db_changes)) {
					Db::$package_log = empty(Db::$package_log) ? $old_db_changes : array_merge($old_db_changes, Db::$package_log);
				}

				// If there are some database changes we might want to remove then filter them out.
				if (!empty(Db::$package_log)) {
					// We're really just checking for entries which are create table AND add columns (etc).
					$tables = [];

					usort(Db::$package_log, function ($a, $b) {
						if ($a[0] == $b[0]) {
							return 0;
						}

						return $a[0] == 'remove_table' ? -1 : 1;
					});

					foreach (Db::$package_log as $k => $log) {
						if ($log[0] == 'remove_table') {
							$tables[] = $log[1];
						} elseif (\in_array($log[1], $tables)) {
							unset(Db::$package_log[$k]);
						}
					}
					$db_changes = Utils::jsonEncode(Db::$package_log);
				} else {
					$db_changes = '';
				}

				// What themes did we actually install?
				$themes_installed = array_unique($themes_installed);
				$themes_installed = implode(',', $themes_installed);

				// What failed steps?
				$failed_step_insert = Utils::jsonEncode($failed_steps);

				// Un-sanitize things before we insert them...
				$keys = ['filename', 'name', 'id', 'version'];

				foreach ($keys as $key) {
					// Yay for variable variables...
					${"package_{$key}"} = Utils::htmlspecialcharsDecode($packageInfo[$key]);
				}

				// Credits tag?
				$credits_tag = (empty($credits_tag)) ? '' : Utils::jsonEncode($credits_tag);

				// Log that we installed it.
				Db::$db->insert(
					'',
					'{db_prefix}log_packages',
					[
						'filename' => 'string',
						'name' => 'string',
						'package_id' => 'string',
						'version' => 'string',
						'id_member_installed' => 'int',
						'member_installed' => 'string',
						'time_installed' => 'int',
						'install_state' => 'int',
						'failed_steps' => 'string',
						'themes_installed' => 'string',
						'member_removed' => 'int',
						'db_changes' => 'string',
						'credits' => 'string',
						'sha256_hash' => 'string',
						'smf_version' => 'string',
					],
					[
						[
							$package_filename,
							$package_name,
							$package_id,
							$package_version,
							User::$me->id,
							User::$me->name,
							time(),
							$is_upgrade ? 2 : 1,
							$failed_step_insert,
							$themes_installed,
							0,
							$db_changes,
							$credits_tag,
							Utils::$context['package_sha256_hash'],
							Utils::$context['smf_version'],
						],
					],
					['id_install'],
				);
			}
			Db::$db->free_result($request);

			Utils::$context['install_finished'] = true;
		}

		// If there's database changes - and they want them removed - let's do it last!
		if (!empty($db_changes) && !empty($_POST['do_db_changes'])) {
			foreach ($db_changes as $change) {
				if ($change[0] == 'remove_table' && isset($change[1])) {
					Db::$db->drop_table($change[1]);
				} elseif ($change[0] == 'remove_column' && isset($change[2])) {
					Db::$db->remove_column($change[1], $change[2]);
				} elseif ($change[0] == 'remove_index' && isset($change[2])) {
					Db::$db->remove_index($change[1], $change[2]);
				}
			}
		}

		// Clean house... get rid of the evidence ;).
		if (file_exists(Config::$packagesdir . '/temp')) {
			PackageUtils::deltree(Config::$packagesdir . '/temp');
		}

		// Log what we just did.
		Logging::logAction(Utils::$context['uninstalling'] ? 'uninstall_package' : (!empty($is_upgrade) ? 'upgrade_package' : 'install_package'), ['package' => Utils::htmlspecialchars($packageInfo['name']), 'version' => Utils::htmlspecialchars($packageInfo['version'])], 'admin');

		// Just in case, let's clear the whole cache and any minimized CSS and JS to avoid anything going up the swanny.
		CacheApi::clean();
		Theme::deleteAllMinified();

		foreach (['css_files', 'javascript_files'] as $file_type) {
			foreach (Utils::$context[$file_type] as $id => $file) {
				if (isset($file['filePath']) && !file_exists($file['filePath'])) {
					unset(Utils::$context[$file_type][$id]);
				}
			}
		}

		// Restore file permissions?
		PackageUtils::createChmodControl([], [], true);

		// Does Config::$backward_compatibility need to be updated?
		$this->updateBackwardCompatibility();

		// Resume background tasks.
		Config::updateSettingsFile(['package_installing' => null]);
	}

	/**
	 * Enables Config::$backward_compatibility if it is needed.
	 *
	 * Once support for backward compatibility behaviours has been discontinued
	 * in a future version of SMF, this method can be removed.
	 */
	public function updateBackwardCompatibility(): void
	{
		// If it has been forced on, leave it alone.
		if ((Config::$backward_compatibility ?? null) === 2) {
			return;
		}

		$lowest_found_version = $min_version = preg_replace('/^(\d+\.\d+).*/', '$1', SMF_VERSION);

		$request = Db::$db->query(
			'SELECT smf_version
			FROM {db_prefix}log_packages
			WHERE install_state != {int:not_installed}
			ORDER BY smf_version ASC',
			[
				'not_installed' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['smf_version'] = strtr(strtolower($row['smf_version']), ' ', '.');

			if (version_compare($row['smf_version'], $lowest_found_version, '<')) {
				$lowest_found_version = $row['smf_version'];
			}
		}

		Db::$db->free_result($request);

		Config::updateSettingsFile([
			'backward_compatibility' => (int) version_compare($lowest_found_version, $min_version, '<'),
		]);
	}

	/**
	 * List the files in a package.
	 */
	public function list(): void
	{
		// No package?  Show him or her the door.
		if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '') {
			Utils::redirectexit('action=admin;area=packages');
		}

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=admin;area=packages;sa=list;package=' . $_REQUEST['package'],
			'name' => Lang::getTxt('list_file', file: 'Packages'),
		];
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt('list_file', file: 'Packages');
		Utils::$context['sub_template'] = 'list';

		// The filename...
		Utils::$context['filename'] = $_REQUEST['package'];

		// Let the unpacker do the work.
		if (is_file(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['files'] = PackageUtils::readTgzFile(Config::$packagesdir . '/' . Utils::$context['filename'], null);
		} elseif (is_dir(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['files'] = PackageUtils::listtree(Config::$packagesdir . '/' . Utils::$context['filename']);
		}
	}

	/**
	 * Display one of the files in a package.
	 */
	public function examineFile(): void
	{
		// No package?  Show him or her the door.
		if (!isset($_REQUEST['package']) || $_REQUEST['package'] == '') {
			Utils::redirectexit('action=admin;area=packages');
		}

		// No file?  Show him or her the door.
		if (!isset($_REQUEST['file']) || $_REQUEST['file'] == '') {
			Utils::redirectexit('action=admin;area=packages');
		}

		$_REQUEST['package'] = preg_replace('~\.+~', '.', strtr($_REQUEST['package'], ['/' => '_', '\\' => '_']));
		$_REQUEST['file'] = preg_replace('~\.+~', '.', $_REQUEST['file']);

		if (isset($_REQUEST['raw'])) {
			if (is_file(Config::$packagesdir . '/' . $_REQUEST['package'])) {
				echo PackageUtils::readTgzFile(Config::$packagesdir . '/' . $_REQUEST['package'], $_REQUEST['file'], true);
			} elseif (is_dir(Config::$packagesdir . '/' . $_REQUEST['package'])) {
				echo file_get_contents(Config::$packagesdir . '/' . $_REQUEST['package'] . '/' . $_REQUEST['file']);
			}

			Utils::obExit(false);
		}

		Utils::$context['linktree'][\count(Utils::$context['linktree']) - 1] = [
			'url' => Config::$scripturl . '?action=admin;area=packages;sa=list;package=' . $_REQUEST['package'],
			'name' => Lang::getTxt('package_examine_file', file: 'Packages'),
		];
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt('package_examine_file', file: 'Packages');
		Utils::$context['sub_template'] = 'examine';

		// The filename...
		Utils::$context['package'] = $_REQUEST['package'];
		Utils::$context['filename'] = $_REQUEST['file'];

		// Let the unpacker do the work.... but make sure we handle images properly.
		if (\in_array(strtolower(strrchr($_REQUEST['file'], '.')), ['.bmp', '.gif', '.jpeg', '.jpg', '.png', '.webp'])) {
			Utils::$context['filedata'] = '<img src="' . Config::$scripturl . '?action=admin;area=packages;sa=examine;package=' . $_REQUEST['package'] . ';file=' . $_REQUEST['file'] . ';raw" alt="' . $_REQUEST['file'] . '">';
		} else {
			if (is_file(Config::$packagesdir . '/' . $_REQUEST['package'])) {
				Utils::$context['filedata'] = Utils::htmlspecialchars(PackageUtils::readTgzFile(Config::$packagesdir . '/' . $_REQUEST['package'], $_REQUEST['file'], true));
			} elseif (is_dir(Config::$packagesdir . '/' . $_REQUEST['package'])) {
				Utils::$context['filedata'] = Utils::htmlspecialchars(file_get_contents(Config::$packagesdir . '/' . $_REQUEST['package'] . '/' . $_REQUEST['file']));
			}

			if (strtolower(strrchr($_REQUEST['file'], '.')) == '.php') {
				Utils::$context['filedata'] = Parser::highlightPhpCode(Utils::$context['filedata']);
			}
		}
	}

	/**
	 * Delete a package.
	 */
	public function remove(): void
	{
		// Check it.
		User::$me->checkSession('get');

		// Ack, don't allow deletion of arbitrary files here, could become a security hole somehow!
		if (!isset($_GET['package']) || $_GET['package'] == 'index.php' || $_GET['package'] == 'backups') {
			Utils::redirectexit('action=admin;area=packages;sa=browse');
		}
		$_GET['package'] = preg_replace('~\.+~', '.', strtr($_GET['package'], ['/' => '_', '\\' => '_']));

		// Can't delete what's not there.
		if (file_exists(Config::$packagesdir . '/' . $_GET['package']) && (str_ends_with($_GET['package'], '.zip') || str_ends_with($_GET['package'], '.tgz') || str_ends_with($_GET['package'], '.tar.gz') || is_dir(Config::$packagesdir . '/' . $_GET['package'])) && $_GET['package'] != 'backups' && !str_starts_with($_GET['package'], '.')) {
			PackageUtils::createChmodControl([Config::$packagesdir . '/' . $_GET['package']], ['destination_url' => Config::$scripturl . '?action=admin;area=packages;sa=remove;package=' . $_GET['package'], 'crash_on_error' => true]);

			if (is_dir(Config::$packagesdir . '/' . $_GET['package'])) {
				PackageUtils::deltree(Config::$packagesdir . '/' . $_GET['package']);
			} else {
				Utils::makeWritable(Config::$packagesdir . '/' . $_GET['package'], 0777);
				unlink(Config::$packagesdir . '/' . $_GET['package']);
			}
		}

		Utils::redirectexit('action=admin;area=packages;sa=browse');
	}

	/**
	 * Browse a list of installed packages.
	 */
	public function browse(): void
	{
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt('browse_packages', file: 'Admin');

		Utils::$context['forum_version'] = SMF_FULL_VERSION;
		Utils::$context['available_packages'] = 0;
		Utils::$context['modification_types'] = ['modification', 'avatar', 'language', 'unknown', 'smiley'];

		IntegrationHook::call('integrate_modification_types');

		foreach (Utils::$context['modification_types'] as $type) {
			// Use the standard templates for showing this.
			$listOptions = [
				'id' => 'packages_lists_' . $type,
				'title' => Lang::getTxt($type . '_package', file: 'Packages'),
				'no_items_label' => Lang::getTxt('no_packages', file: 'Packages'),
				'get_items' => [
					'function' => [$this, 'list_getPackages'],
					'params' => [$type],
				],
				'base_href' => Config::$scripturl . '?action=admin;area=packages;sa=browse;type=' . $type,
				'default_sort_col' => 'id' . $type,
				'columns' => [
					'id' . $type => [
						'header' => [
							'value' => Lang::getTxt('package_id', file: 'Packages'),
						],
						'data' => [
							'db' => 'sort_id',
						],
						'sort' => [
							'default' => 'sort_id',
							'reverse' => 'sort_id',
						],
					],
					'package_name' . $type => [
						'header' => [
							'value' => Lang::getTxt('package_name_header', file: 'Packages'),
							'style' => 'width: 25%;',
						],
						'data' => [
							'db' => 'name',
						],
						'sort' => [
							'default' => 'name',
							'reverse' => 'name',
						],
					],
					'version' . $type => [
						'header' => [
							'value' => Lang::getTxt('package_version_header', file: 'Packages'),
						],
						'data' => [
							'db' => 'version',
						],
						'sort' => [
							'default' => 'version',
							'reverse' => 'version',
						],
					],
					'time_installed' . $type => [
						'header' => [
							'value' => Lang::getTxt('package_installed_time', file: 'Packages'),
						],
						'data' => [
							'function' => function ($package) {
								return !empty($package['time_installed'])
									? Time::create('@' . $package['time_installed'])->format()
									: Lang::getTxt('not_applicable', file: 'General');
							},
							'class' => 'smalltext',
						],
						'sort' => [
							'default' => 'time_installed',
							'reverse' => 'time_installed',
						],
					],
					'operations' . $type => [
						'header' => [
							'value' => '',
						],
						'data' => [
							'function' => function ($package) use ($type) {
								$return = '';

								if ($package['can_uninstall']) {
									$return = '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=uninstall;package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '" class="button floatnone">' . Lang::getTxt(Lang::txtExists('uninstall_' . $type, file: 'Packages') ? 'uninstall_' . $type : 'uninstall', file: 'Packages') . '</a>';
								} elseif ($package['can_emulate_uninstall']) {
									$return = '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=uninstall;ve=' . $package['can_emulate_uninstall'] . ';package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '" class="button floatnone">' . Lang::getTxt('package_emulate_uninstall', ['version' => $package['can_emulate_uninstall']], file: 'Packages') . '</a>';
								} elseif ($package['can_upgrade']) {
									$return = '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '" class="button floatnone">' . Lang::getTxt('package_upgrade', file: 'Packages') . '</a>';
								} elseif ($package['can_install']) {
									$return = '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '" class="button floatnone">' . Lang::getTxt('install_' . $type, file: 'Packages') . '</a>';
								} elseif ($package['can_emulate_install']) {
									$return = '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=install;ve=' . $package['can_emulate_install'] . ';package=' . $package['filename'] . '" class="button floatnone">' . Lang::getTxt('package_emulate_install', ['version' => $package['can_emulate_install']], file: 'Packages') . '</a>';
								}

								return $return . '
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=list;package=' . $package['filename'] . '" class="button floatnone">' . Lang::getTxt('list_files', file: 'Packages') . '</a>
										<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=remove;package=' . $package['filename'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '"' . ($package['is_installed'] && $package['is_current'] ? ' data-confirm="' . Lang::getTxt('package_delete_bad', file: 'Packages') . '"' : '') . ' class="button' . ($package['is_installed'] && $package['is_current'] ? ' you_sure' : '') . ' floatnone">' . Lang::getTxt('package_delete', file: 'Packages') . '</a>';
							},
							'class' => 'righttext',
						],
					],
				],
			];

			new ItemList($listOptions);
		}

		Utils::$context['sub_template'] = 'browse';
		Utils::$context['default_list'] = 'packages_lists';

		$get_versions = Db::$db->query(
			'SELECT data FROM {db_prefix}admin_info_files WHERE filename={string:versionsfile} AND path={string:smf}',
			[
				'versionsfile' => 'latest-versions.txt',
				'smf' => '/smf/',
			],
		);

		$data = Db::$db->fetch_assoc($get_versions);
		Db::$db->free_result($get_versions);

		// Decode the data.
		// This list is fetched from simplemachines.org and cached in the
		// database. A forum that has never managed to reach it — a fresh
		// install, or one with no outbound access — has nothing stored, and
		// jsonDecode() hands back null rather than a list of versions.
		$items = Utils::jsonDecode($data['data'] ?? '', true);

		Utils::$context['emulation_versions'] = \is_array($items) ? preg_replace('~^SMF ~', '', $items) : [];

		// Current SMF version, which is selected by default
		Utils::$context['default_version'] = SMF_VERSION;

		if (!\in_array(Utils::$context['default_version'], Utils::$context['emulation_versions'])) {
			Utils::$context['emulation_versions'][] = Utils::$context['default_version'];
		}

		// Version we're currently emulating, if any
		Utils::$context['selected_version'] = preg_replace('~^SMF ~', '', Utils::$context['forum_version']);
	}

	/**
	 * Used when a temp FTP access is needed to package functions
	 */
	public function options(): void
	{
		if (isset($_POST['save'])) {
			User::$me->checkSession();

			Config::updateModSettings([
				'package_server' => trim(Utils::htmlspecialchars($_POST['pack_server'])),
				'package_port' => trim(Utils::htmlspecialchars($_POST['pack_port'])),
				'package_username' => trim(Utils::htmlspecialchars($_POST['pack_user'])),
				'package_make_backups' => !empty($_POST['package_make_backups']),
				'package_make_full_backups' => !empty($_POST['package_make_full_backups']),
			]);
			$_SESSION['adm-save'] = true;

			Utils::redirectexit('action=admin;area=packages;sa=options');
		}

		if (preg_match('~^/home\d*/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match)) {
			$default_username = $match[1];
		} else {
			$default_username = '';
		}

		Utils::$context['page_title'] = Lang::getTxt('package_settings', file: 'Admin');
		Utils::$context['sub_template'] = 'install_options';

		Utils::$context['package_ftp_server'] = Config::$modSettings['package_server'] ?? 'localhost';
		Utils::$context['package_ftp_port'] = Config::$modSettings['package_port'] ?? '21';
		Utils::$context['package_ftp_username'] = Config::$modSettings['package_username'] ?? $default_username;
		Utils::$context['package_make_backups'] = !empty(Config::$modSettings['package_make_backups']);
		Utils::$context['package_make_full_backups'] = !empty(Config::$modSettings['package_make_full_backups']);

		if (!empty($_SESSION['adm-save'])) {
			Utils::$context['saved_successful'] = true;
			unset($_SESSION['adm-save']);
		}
	}

	/**
	 * List operations
	 */
	public function showOperations(): void
	{
		// Can't be in here buddy.
		User::$me->isAllowedTo('admin_forum');

		// We need to know the operation key for the search and replace, mod file looking at, is it a board mod?
		if (!isset($_REQUEST['operation_key'], $_REQUEST['filename']) && !is_numeric($_REQUEST['operation_key'])) {
			ErrorHandler::fatalLang('operation_invalid', 'general');
		}

		// Uninstalling the mod?
		$reverse = isset($_REQUEST['reverse']) ? true : false;

		// Get the base name.
		Utils::$context['filename'] = preg_replace('~\.+~', '.', $_REQUEST['package']);

		// We need to extract this again.
		if (is_file(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			Utils::$context['extracted_files'] = PackageUtils::readTgzFile(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');

			if (Utils::$context['extracted_files'] && !file_exists(Config::$packagesdir . '/temp/package-info.xml')) {
				foreach (Utils::$context['extracted_files'] as $file) {
					if (basename($file['filename']) == 'package-info.xml') {
						Utils::$context['base_path'] = \dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset(Utils::$context['base_path'])) {
				Utils::$context['base_path'] = '';
			}
		} elseif (is_dir(Config::$packagesdir . '/' . Utils::$context['filename'])) {
			PackageUtils::copytree(Config::$packagesdir . '/' . Utils::$context['filename'], Config::$packagesdir . '/temp');
			Utils::$context['extracted_files'] = PackageUtils::listtree(Config::$packagesdir . '/temp');
			Utils::$context['base_path'] = '';
		}

		// Load up any custom themes we may want to install into...
		$request = Db::$db->query(
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
				AND variable IN ({string:name}, {string:theme_dir})',
			[
				'known_theme_list' => explode(',', Config::$modSettings['knownThemes']),
				'default_theme' => 1,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			],
		);
		$theme_paths = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);

		// If we're viewing uninstall operations, only consider themes that
		// the package is actually installed into.
		if (isset($_REQUEST['reverse']) && !empty($_REQUEST['install_id'])) {
			$install_id = (int) $_REQUEST['install_id'];

			if ($install_id > 0) {
				$old_themes = [];
				$request = Db::$db->query(
					'SELECT themes_installed
					FROM {db_prefix}log_packages
					WHERE id_install = {int:install_id}',
					[
						'install_id' => $install_id,
					],
				);

				if (Db::$db->num_rows($request) == 1) {
					list($old_themes) = Db::$db->fetch_row($request);
					$old_themes = explode(',', $old_themes);

					foreach ($theme_paths as $id => $data) {
						if ($id != 1 && !\in_array($id, $old_themes)) {
							unset($theme_paths[$id]);
						}
					}
				}
				Db::$db->free_result($request);
			}
		}

		if (isset($_REQUEST['diff'])) {
			$mod_actions = PackageUtils::parseDiff(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $_REQUEST['filename']), true, $reverse, $theme_paths);
		} elseif (isset($_REQUEST['boardmod'])) {
			$mod_actions = PackageUtils::parseBoardMod(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $_REQUEST['filename']), true, $reverse, $theme_paths);
		} else {
			$mod_actions = PackageUtils::parseModification(@file_get_contents(Config::$packagesdir . '/temp/' . Utils::$context['base_path'] . $_REQUEST['filename']), true, $reverse, $theme_paths);
		}

		// Ok lets get the content of the file.
		Utils::$context['operations'] = [
			'search' => strtr(Utils::htmlspecialchars($mod_actions[$_REQUEST['operation_key']]['search_original']), ['[' => '&#91;', ']' => '&#93;']),
			'replace' => strtr(Utils::htmlspecialchars($mod_actions[$_REQUEST['operation_key']]['replace_original']), ['[' => '&#91;', ']' => '&#93;']),
			'position' => $mod_actions[$_REQUEST['operation_key']]['position'],
		];

		// Let's do some formatting...
		$operation_text = Utils::$context['operations']['position'] == 'replace' ? 'operation_replace' : (Utils::$context['operations']['position'] == 'before' ? 'operation_after' : 'operation_before');
		Utils::$context['operations']['search'] = Parser::transform('[code=' . Lang::getTxt(Utils::$context['operations']['position'] == 'end' || !empty(Utils::$context['operations']['is_reg_exp']) ? 'operation_find_regex' : 'operation_find') . ']' . (Utils::$context['operations']['position'] == 'end' ? '(\\n\\?&gt;)?$' : Utils::$context['operations']['search']) . '[/code]');
		Utils::$context['operations']['replace'] = Parser::transform('[code=' . Lang::getTxt($operation_text, file: 'Packages') . ']' . Utils::$context['operations']['replace'] . '[/code]');

		// No layers
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'view_operations';

		// We only want to load these three JavaScript files.
		Utils::$context['javascript_files'] = array_intersect_key(
			Utils::$context['javascript_files'],
			[
				'smf_script_js' => true,
				'smf_jquery_js' => true,
			],
		);

		// Since the alerts code is loaded very late in the process, it must be disabled separately.
		Theme::$current->settings['disable_files'] = ['smf_alerts'];
	}

	/**
	 * Allow the admin to reset permissions on files.
	 */
	public function permissions(): void
	{
		// Let's try to be good, yes?
		User::$me->checkSession('get');

		// If we're restoring permissions this is just a pass through really.
		if (isset($_GET['restore'])) {
			PackageUtils::createChmodControl([], [], true);
			ErrorHandler::fatalLang('no_access', false);
		}

		// This is a memory eat.
		Sapi::setMemoryLimit('128M');
		Sapi::setTimeLimit();

		// Load up some FTP stuff.
		PackageUtils::createChmodControl();

		if (empty(PackageUtils::$package_ftp) && !isset($_POST['skip_ftp'])) {
			$ftp = new FtpConnection(null);
			list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$boarddir);

			Utils::$context['package_ftp'] = [
				'server' => Config::$modSettings['package_server'] ?? 'localhost',
				'port' => Config::$modSettings['package_port'] ?? '21',
				'username' => empty($username) ? (Config::$modSettings['package_username'] ?? '') : $username,
				'path' => $detect_path,
				'form_elements_only' => true,
			];
		} else {
			Utils::$context['ftp_connected'] = true;
		}

		// Define the template.
		Utils::$context['page_title'] = Lang::getTxt('package_file_perms', file: 'Admin');
		Utils::$context['sub_template'] = 'file_permissions';

		// Define what files we're interested in, as a tree.
		Utils::$context['file_tree'] = [
			strtr(Config::$boarddir, ['\\' => '/']) => [
				'type' => 'dir',
				'contents' => [
					'agreement.txt' => [
						'type' => 'file',
						'writable_on' => 'standard',
					],
					'Settings.php' => [
						'type' => 'file',
						'writable_on' => 'restrictive',
					],
					'Settings_bak.php' => [
						'type' => 'file',
						'writable_on' => 'restrictive',
					],
					'attachments' => [
						'type' => 'dir',
						'writable_on' => 'restrictive',
					],
					'avatars' => [
						'type' => 'dir',
						'writable_on' => 'standard',
					],
					'cache' => [
						'type' => 'dir',
						'writable_on' => 'restrictive',
					],
					'custom_avatar_dir' => [
						'type' => 'dir',
						'writable_on' => 'restrictive',
					],
					'Smileys' => [
						'type' => 'dir_recursive',
						'writable_on' => 'standard',
					],
					'Sources' => [
						'type' => 'dir_recursive',
						'list_contents' => true,
						'writable_on' => 'standard',
						'contents' => [
							'tasks' => [
								'type' => 'dir',
								'list_contents' => true,
							],
						],
					],
					'Themes' => [
						'type' => 'dir_recursive',
						'writable_on' => 'standard',
						'contents' => [
							'default' => [
								'type' => 'dir_recursive',
								'list_contents' => true,
								'contents' => [
									'languages' => [
										'type' => 'dir',
										'list_contents' => true,
									],
								],
							],
						],
					],
					'Packages' => [
						'type' => 'dir',
						'writable_on' => 'standard',
						'contents' => [
							'temp' => [
								'type' => 'dir',
							],
							'backup' => [
								'type' => 'dir',
							],
						],
					],
				],
			],
		];

		// Directories that can move.
		if (!str_starts_with(Config::$sourcedir, Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['Sources']);
			Utils::$context['file_tree'][strtr(Config::$sourcedir, ['\\' => '/'])] = [
				'type' => 'dir',
				'list_contents' => true,
				'writable_on' => 'standard',
			];
		}

		// Moved the cache?
		if (!str_starts_with(Config::$cachedir, Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['cache']);
			Utils::$context['file_tree'][strtr(Config::$cachedir, ['\\' => '/'])] = [
				'type' => 'dir',
				'list_contents' => false,
				'writable_on' => 'restrictive',
			];
		}

		// Are we using multiple attachment directories?
		if (!empty(Config::$modSettings['currentAttachmentUploadDir'])) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['attachments']);

			if (!\is_array(Config::$modSettings['attachmentUploadDir'])) {
				Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
			}

			// @todo Should we suggest non-current directories be read only?
			foreach (Config::$modSettings['attachmentUploadDir'] as $dir) {
				Utils::$context['file_tree'][strtr($dir, ['\\' => '/'])] = [
					'type' => 'dir',
					'writable_on' => 'restrictive',
				];
			}
		} elseif (!str_starts_with(Config::$modSettings['attachmentUploadDir'], Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['attachments']);
			Utils::$context['file_tree'][strtr(Config::$modSettings['attachmentUploadDir'], ['\\' => '/'])] = [
				'type' => 'dir',
				'writable_on' => 'restrictive',
			];
		}

		if (!str_starts_with(Config::$modSettings['smileys_dir'], Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['Smileys']);
			Utils::$context['file_tree'][strtr(Config::$modSettings['smileys_dir'], ['\\' => '/'])] = [
				'type' => 'dir_recursive',
				'writable_on' => 'standard',
			];
		}

		if (!str_starts_with(Config::$modSettings['avatar_directory'], Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['avatars']);
			Utils::$context['file_tree'][strtr(Config::$modSettings['avatar_directory'], ['\\' => '/'])] = [
				'type' => 'dir',
				'writable_on' => 'standard',
			];
		}

		if (isset(Config::$modSettings['custom_avatar_dir']) && !str_starts_with(Config::$modSettings['custom_avatar_dir'], Config::$boarddir)) {
			unset(Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['custom_avatar_dir']);
			Utils::$context['file_tree'][strtr(Config::$modSettings['custom_avatar_dir'], ['\\' => '/'])] = [
				'type' => 'dir',
				'writable_on' => 'restrictive',
			];
		}

		// Load up any custom themes.
		$request = Db::$db->query(
			'SELECT value
			FROM {db_prefix}themes
			WHERE id_theme > {int:default_theme_id}
				AND id_member = {int:guest_id}
				AND variable = {string:theme_dir}
			ORDER BY value ASC',
			[
				'default_theme_id' => 1,
				'guest_id' => 0,
				'theme_dir' => 'theme_dir',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (substr(strtolower(strtr($row['value'], ['\\' => '/'])), 0, \strlen(Config::$boarddir) + 7) == strtolower(strtr(Config::$boarddir, ['\\' => '/']) . '/Themes')) {
				Utils::$context['file_tree'][strtr(Config::$boarddir, ['\\' => '/'])]['contents']['Themes']['contents'][substr($row['value'], \strlen(Config::$boarddir) + 8)] = [
					'type' => 'dir_recursive',
					'list_contents' => true,
					'contents' => [
						'languages' => [
							'type' => 'dir',
							'list_contents' => true,
						],
					],
				];
			} else {
				Utils::$context['file_tree'][strtr($row['value'], ['\\' => '/'])] = [
					'type' => 'dir_recursive',
					'list_contents' => true,
					'contents' => [
						'languages' => [
							'type' => 'dir',
							'list_contents' => true,
						],
					],
				];
			}
		}
		Db::$db->free_result($request);

		// If we're submitting then let's move on to another function to keep things cleaner..
		if (isset($_POST['action_changes'])) {
			$this->PackagePermissionsAction();

			return;
		}

		Utils::$context['look_for'] = [];

		// Are we looking for a particular tree - normally an expansion?
		if (!empty($_REQUEST['find'])) {
			Utils::$context['look_for'][] = base64_decode($_REQUEST['find']);
		}
		// Only that tree?
		Utils::$context['only_find'] = isset($_GET['xml']) && !empty($_REQUEST['onlyfind']) ? $_REQUEST['onlyfind'] : '';

		if (Utils::$context['only_find']) {
			Utils::$context['look_for'][] = Utils::$context['only_find'];
		}

		// Have we got a load of back-catalogue trees to expand from a submit etc?
		if (!empty($_GET['back_look'])) {
			$potententialTrees = Utils::jsonDecode(base64_decode($_GET['back_look']), true);

			foreach ($potententialTrees as $tree) {
				Utils::$context['look_for'][] = $tree;
			}
		}

		// ... maybe posted?
		if (!empty($_POST['back_look'])) {
			Utils::$context['only_find'] = array_merge(Utils::$context['only_find'], $_POST['back_look']);
		}

		Utils::$context['back_look_data'] = base64_encode(Utils::jsonEncode(\array_slice(Utils::$context['look_for'], 0, 15)));

		// Are we finding more files than first thought?
		Utils::$context['file_offset'] = !empty($_REQUEST['fileoffset']) ? (int) $_REQUEST['fileoffset'] : 0;
		// Don't list more than this many files in a directory.
		Utils::$context['file_limit'] = 150;

		// How many levels shall we show?
		Utils::$context['default_level'] = empty(Utils::$context['only_find']) ? 2 : 25;

		// This will be used if we end up catching XML data.
		Utils::$context['xml_data'] = [
			'roots' => [
				'identifier' => 'root',
				'children' => [
					[
						'value' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find']),
					],
				],
			],
			'folders' => [
				'identifier' => 'folder',
				'children' => [],
			],
		];

		foreach (Utils::$context['file_tree'] as $path => $data) {
			// Run this directory.
			if (file_exists($path) && (empty(Utils::$context['only_find']) || str_starts_with(Utils::$context['only_find'], $path))) {
				// Get the first level down only.
				$this->fetchPerms__recursive($path, Utils::$context['file_tree'][$path], 1);
				Utils::$context['file_tree'][$path]['perms'] = [
					'chmod' => @is_writable($path),
					'perms' => @fileperms($path),
				];
			} else {
				unset(Utils::$context['file_tree'][$path]);
			}
		}

		// Is this actually xml?
		if (isset($_GET['xml'])) {
			Theme::loadTemplate('Xml');
			Utils::$context['sub_template'] = 'generic_xml';
			Utils::$context['template_layers'] = [];
		}
	}

	/**
	 * Actually action the permission changes they want.
	 */
	public function PackagePermissionsAction(): ?bool
	{
		umask(0);

		$timeout_limit = 5;

		Utils::$context['method'] = $_POST['method'] == 'individual' ? 'individual' : 'predefined';
		Utils::$context['sub_template'] = 'action_permissions';
		Utils::$context['page_title'] = Lang::getTxt('package_file_perms_applying', file: 'Packages');
		Utils::$context['back_look_data'] = $_POST['back_look'] ?? [];

		// Skipping use of FTP?
		if (empty(PackageUtils::$package_ftp)) {
			Utils::$context['skip_ftp'] = true;
		}

		// We'll start off in a good place, security. Make sure that if we're dealing with individual files that they seem in the right place.
		if (Utils::$context['method'] == 'individual') {
			// Only these path roots are legal.
			$legal_roots = array_keys(Utils::$context['file_tree']);
			Utils::$context['custom_value'] = (int) $_POST['custom_value'];

			// Continuing?
			if (isset($_POST['toProcess'])) {
				$_POST['permStatus'] = Utils::jsonDecode(base64_decode($_POST['toProcess']), true);
			}

			if (isset($_POST['permStatus'])) {
				Utils::$context['to_process'] = [];
				$validate_custom = false;

				foreach ($_POST['permStatus'] as $path => $status) {
					// Nothing to see here?
					if ($status == 'no_change') {
						continue;
					}
					$legal = false;

					foreach ($legal_roots as $root) {
						if (str_starts_with($path, $root)) {
							$legal = true;
						}
					}

					if (!$legal) {
						continue;
					}

					// Check it exists.
					if (!file_exists($path)) {
						continue;
					}

					if ($status == 'custom') {
						$validate_custom = true;
					}

					// Now add it.
					Utils::$context['to_process'][$path] = $status;
				}
				Utils::$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : \count(Utils::$context['to_process']);

				// Make sure the chmod status is valid?
				if ($validate_custom) {
					if (preg_match('~^[4567][4567][4567]$~', Utils::$context['custom_value']) == false) {
						ErrorHandler::fatal(Lang::getTxt('chmod_value_invalid', file: 'Packages'));
					}
				}

				// Nothing to do?
				if (empty(Utils::$context['to_process'])) {
					Utils::redirectexit('action=admin;area=packages;sa=perms' . (!empty(Utils::$context['back_look_data']) ? ';back_look=' . base64_encode(Utils::jsonEncode(Utils::$context['back_look_data'])) : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
				}
			}
			// Should never get here,
			else {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Setup the custom value.
			$custom_value = octdec('0' . Utils::$context['custom_value']);

			// Start processing items.
			foreach (Utils::$context['to_process'] as $path => $status) {
				if (\in_array($status, ['execute', 'writable', 'read'])) {
					PackageUtils::chmod($path, $status);
				} elseif ($status == 'custom' && !empty($custom_value)) {
					// Use FTP if we have it.
					if (!empty(PackageUtils::$package_ftp) && !empty($_SESSION['pack_ftp'])) {
						$ftp_file = strtr($path, [$_SESSION['pack_ftp']['root'] => '']);
						PackageUtils::$package_ftp->chmod($ftp_file, $custom_value);
					} else {
						Utils::makeWritable($path, $custom_value);
					}
				}

				// This fish is fried...
				unset(Utils::$context['to_process'][$path]);

				// See if we're out of time?
				if ((time() - TIME_START) > $timeout_limit) {
					// Prepare template usage for to_process.
					Utils::$context['to_process_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['to_process']));

					return false;
				}
			}

			// Prepare template usage for to_process.
			Utils::$context['to_process_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['to_process']));
		}
		// If predefined this is a little different.
		else {
			Utils::$context['predefined_type'] = $_POST['predefined'] ?? 'restricted';

			Utils::$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : 0;
			Utils::$context['directory_list'] = isset($_POST['dirList']) ? Utils::jsonDecode(base64_decode($_POST['dirList']), true) : [];

			Utils::$context['file_offset'] = isset($_POST['fileOffset']) ? (int) $_POST['fileOffset'] : 0;

			// Haven't counted the items yet?
			if (empty(Utils::$context['total_items'])) {
				foreach (Utils::$context['file_tree'] as $path => $data) {
					if (is_dir($path)) {
						Utils::$context['directory_list'][$path] = 1;
						Utils::$context['total_items'] += $this->count_directories__recursive($path);
						Utils::$context['total_items']++;
					}
				}
			}

			// Have we built up our list of special files?
			if (!isset($_POST['specialFiles']) && Utils::$context['predefined_type'] != 'free') {
				Utils::$context['special_files'] = [];

				foreach (Utils::$context['file_tree'] as $path => $data) {
					$this->build_special_files__recursive($path, $data);
				}
			}
			// Free doesn't need special files.
			elseif (Utils::$context['predefined_type'] == 'free') {
				Utils::$context['special_files'] = [];
			} else {
				Utils::$context['special_files'] = Utils::jsonDecode(base64_decode($_POST['specialFiles']), true);
			}

			// Now we definitely know where we are, we need to go through again doing the chmod!
			foreach (Utils::$context['directory_list'] as $path => $dummy) {
				// Do the contents of the directory first.
				$dh = @opendir($path);
				$file_count = 0;
				$dont_chmod = false;

				while ($entry = readdir($dh)) {
					// Bypass directory abbreviations altogether...
					if ($entry == '.' || $entry == '..') {
						continue;
					}

					$file_count++;

					// Actually process this file?
					if (!$dont_chmod && !is_dir($path . '/' . $entry) && (empty(Utils::$context['file_offset']) || Utils::$context['file_offset'] < $file_count)) {
						$status = Utils::$context['predefined_type'] == 'free' || isset(Utils::$context['special_files'][$path . '/' . $entry]) ? 'writable' : 'execute';
						PackageUtils::chmod($path . '/' . $entry, $status);
					}

					// See if we're out of time?
					if (!$dont_chmod && (time() - TIME_START) > $timeout_limit) {
						$dont_chmod = true;
						// Don't do this again.
						Utils::$context['file_offset'] = $file_count;
					}
				}
				closedir($dh);

				// If this is set it means we timed out half way through.
				if ($dont_chmod) {
					Utils::$context['total_files'] = $file_count;
					Utils::$context['directory_list_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['directory_list']));
					Utils::$context['special_files_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['special_files']));

					return false;
				}

				// Do the actual directory.
				$status = Utils::$context['predefined_type'] == 'free' || isset(Utils::$context['special_files'][$path]) ? 'writable' : 'execute';
				PackageUtils::chmod($path, $status);

				// We've finished the directory so no file offset, and no record.
				Utils::$context['file_offset'] = 0;
				unset(Utils::$context['directory_list'][$path]);

				// See if we're out of time?
				if ((time() - TIME_START) > $timeout_limit) {
					// Prepare this for usage on templates.
					Utils::$context['directory_list_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['directory_list']));
					Utils::$context['special_files_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['special_files']));

					return false;
				}
			}

			// Prepare this for usage on templates.
			Utils::$context['directory_list_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['directory_list']));
			Utils::$context['special_files_encode'] = base64_encode(Utils::jsonEncode(Utils::$context['special_files']));
		}

		// If we're here we are done!
		Utils::redirectexit('action=admin;area=packages;sa=perms' . (!empty(Utils::$context['back_look_data']) ? ';back_look=' . base64_encode(Utils::jsonEncode(Utils::$context['back_look_data'])) : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}

	/**
	 * Test an FTP connection.
	 */
	public function ftpTest(): void
	{
		User::$me->checkSession('get');

		// Try to make the FTP connection.
		PackageUtils::createChmodControl([], ['force_find_error' => true]);

		// Deal with the template stuff.
		Theme::loadTemplate('Xml');
		Utils::$context['sub_template'] = 'generic_xml';
		Utils::$context['template_layers'] = [];

		// Define the return data, this is simple.
		Utils::$context['xml_data'] = [
			'results' => [
				'identifier' => 'result',
				'children' => [
					[
						'attributes' => [
							'success' => !empty(PackageUtils::$package_ftp) ? 1 : 0,
						],
						'value' => !empty(PackageUtils::$package_ftp) ? Lang::getTxt('package_ftp_test_success', file: 'Packages') : (isset(Utils::$context['package_ftp'], Utils::$context['package_ftp']['error']) ? Utils::$context['package_ftp']['error'] : Lang::getTxt('package_ftp_test_failed', file: 'Packages')),
					],
				],
			],
		];
	}

	/**
	 * Load a list of package servers.
	 */
	public function servers(): void
	{
		// Ensure we use the correct template, and page title.
		Utils::$context['sub_template'] = 'servers';
		Utils::$context['page_title'] .= ' - ' . Lang::getTxt('download_packages', file: 'Admin');

		// Load the list of servers.
		$request = Db::$db->query(
			'SELECT id_server, name, url
			FROM {db_prefix}package_servers',
			[
			],
		);
		Utils::$context['servers'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['servers'][] = [
				'name' => $row['name'],
				'url' => $row['url'],
				'id' => $row['id_server'],
			];
		}
		Db::$db->free_result($request);

		Utils::$context['package_download_broken'] = !is_writable(Config::$packagesdir);

		if (Utils::$context['package_download_broken']) {
			Utils::makeWritable(Config::$packagesdir, 0777);
		}

		Utils::$context['package_download_broken'] = !is_writable(Config::$packagesdir);

		if (Utils::$context['package_download_broken']) {
			if (isset($_POST['ftp_username'])) {
				$ftp = new FtpConnection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

				if ($ftp->error === false) {
					// I know, I know... but a lot of people want to type /home/xyz/... which is wrong, but logical.
					if (!$ftp->chdir($_POST['ftp_path'])) {
						$ftp_error = $ftp->error;
						$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
					}
				}
			}

			if (!isset($ftp) || $ftp->error !== false) {
				if (!isset($ftp)) {
					$ftp = new FtpConnection(null);
				} elseif ($ftp->error !== false && !isset($ftp_error)) {
					$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;
				}

				list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$packagesdir);

				if ($found_path || !isset($_POST['ftp_path'])) {
					$_POST['ftp_path'] = $detect_path;
				}

				if (!isset($_POST['ftp_username'])) {
					$_POST['ftp_username'] = $username;
				}

				Utils::$context['package_ftp'] = [
					'server' => $_POST['ftp_server'] ?? (Config::$modSettings['package_server'] ?? 'localhost'),
					'port' => $_POST['ftp_port'] ?? (Config::$modSettings['package_port'] ?? '21'),
					'username' => $_POST['ftp_username'] ?? (Config::$modSettings['package_username'] ?? ''),
					'path' => $_POST['ftp_path'],
					'error' => empty($ftp_error) ? null : $ftp_error,
				];
			} else {
				Utils::$context['package_download_broken'] = false;

				$ftp->chmod('.', 0777);
				$ftp->close();
			}
		}

		Theme::addInlineJavaScript('
		$(\'.new_package_content\').hide();
		$(\'.download_new_package\').on(\'click\', function() {
			var collapseState = $(\'.new_package_content\').css(\'display\');
			var icon = $(\'.download_new_package\').children(\'span\');
			var collapsedDiv = $(\'.new_package_content\');

			if (collapseState == \'none\')
			{
				collapsedDiv.show(\'slow\');
				icon.removeClass(\'toggle_down\').addClass(\'toggle_up\');
				icon.prop(\'title\', ' . Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) . ');
			}

			else
			{
				collapsedDiv.hide(\'slow\');
				icon.removeClass(\'toggle_up\').addClass(\'toggle_down\');
				icon.prop(\'title\', ' . Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) . ');
			}
		});', true);
	}

	/**
	 * Browse a server's list of packages.
	 */
	public function serverBrowse(): void
	{
		if (isset($_GET['server'])) {
			if ($_GET['server'] == '') {
				Utils::redirectexit('action=admin;area=packages;get');
			}

			$server = (int) $_GET['server'];

			// Query the server list to find the current server.
			$request = Db::$db->query(
				'SELECT name, url
				FROM {db_prefix}package_servers
				WHERE id_server = {int:current_server}
				LIMIT 1',
				[
					'current_server' => $server,
				],
			);
			list($name, $url) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// If the server does not exist, dump out.
			if (empty($url)) {
				ErrorHandler::fatalLang('couldnt_connect', false);
			}

			// If there is a relative link, append to the stored server url.
			if (isset($_GET['relative'])) {
				$url = $url . (str_ends_with($url, '/') ? '' : '/') . $_GET['relative'];
			}

			$the_version = SMF_VERSION;

			if (!empty($_SESSION['version_emulate'])) {
				$the_version = $_SESSION['version_emulate'];
			}

			// Sub out any variables we support in the url.
			$url = strtr($url, [
				'{SMF_VERSION}' => urlencode($the_version),
			]);

			// Clear any "absolute" URL.  Since "server" is present, "absolute" is garbage.
			unset($_GET['absolute']);
		} elseif (isset($_GET['absolute']) && $_GET['absolute'] != '') {
			// Initialize the required variables.
			$server = '';
			$url = $_GET['absolute'];
			$name = '';
			$_GET['package'] = $url . '/packages.xml?language=' . User::$me->language;

			// Clear any "relative" URL.  Since "server" is not present, "relative" is garbage.
			unset($_GET['relative']);

			$token = Security::checkConfirm('get_absolute_url');

			if ($token !== true) {
				Utils::$context['sub_template'] = 'package_confirm';

				Utils::$context['page_title'] = Lang::getTxt('package_servers', file: 'Packages');
				Utils::$context['confirm_message'] = Lang::getTxt('package_confirm_view_package_content', [Utils::htmlspecialchars($_GET['absolute'])], file: 'Packages');
				Utils::$context['proceed_href'] = Config::$scripturl . '?action=admin;area=packages;get;sa=browse;absolute=' . urlencode($_GET['absolute']) . ';confirm=' . $token;

				return;
			}
		}
		// Minimum required parameter did not exist so dump out.
		else {
			ErrorHandler::fatalLang('couldnt_connect', false);
		}

		// Attempt to connect.  If unsuccessful... try the URL.
		if (!isset($_GET['package']) || file_exists($_GET['package'])) {
			$_GET['package'] = $url . '/packages.xml?language=' . User::$me->language;
		}

		// Check to be sure the packages.xml file actually exists where it should be... or dump out.
		if ((isset($_GET['absolute']) || isset($_GET['relative'])) && !PackageUtils::urlExists($_GET['package'])) {
			ErrorHandler::fatalLang('packageget_unable', false, [$url . '/index.php']);
		}

		// Might take some time.
		Sapi::setTimeLimit();

		// Read packages.xml and parse into XmlArray. (the true tells it to trim things ;).)
		$listing = new XmlArray(WebFetchApi::fetch($_GET['package']), true);

		// Errm.... empty file?  Try the URL....
		if (!$listing->exists('package-list')) {
			ErrorHandler::fatalLang('packageget_unable', false, [$url . '/index.php']);
		}

		// List out the packages...
		Utils::$context['package_list'] = [];

		$listing = $listing->path('package-list[0]');

		// Use the package list's name if it exists.
		if ($listing->exists('list-title')) {
			$name = Utils::htmlspecialchars($listing->fetch('list-title'));
		}

		// Pick the correct template.
		Utils::$context['sub_template'] = 'package_list';

		Utils::$context['page_title'] = Lang::getTxt('package_server_name', ['name' => $name], file: 'Packages');
		Utils::$context['package_server'] = $server;

		// By default we use an unordered list, unless there are no lists with more than one package.
		Utils::$context['list_type'] = 'ul';

		$instmods = PackageUtils::loadInstalledPackages();

		$installed_mods = [];

		// Look through the list of installed mods...
		foreach ($instmods as $installed_mod) {
			$installed_mods[$installed_mod['package_id']] = $installed_mod['version'];
		}

		// Get default author and email if they exist.
		if ($listing->exists('default-author')) {
			$default_author = Utils::htmlspecialchars($listing->fetch('default-author'));

			if ($listing->exists('default-author/@email') && EmailAddress::create($listing->fetch('default-author/@email'))->isValid()) {
				$default_email = Utils::htmlspecialchars($listing->fetch('default-author/@email'));
			}
		}

		// Get default web site if it exists.
		if ($listing->exists('default-website')) {
			$default_website = Utils::htmlspecialchars($listing->fetch('default-website'));

			if ($listing->exists('default-website/@title')) {
				$default_title = Utils::htmlspecialchars($listing->fetch('default-website/@title'));
			}
		}

		$the_version = SMF_VERSION;

		if (!empty($_SESSION['version_emulate'])) {
			$the_version = $_SESSION['version_emulate'];
		}

		$packageNum = 0;
		$packageSection = 0;

		$sections = $listing->set('section');

		foreach ($sections as $i => $section) {
			Utils::$context['package_list'][$packageSection] = [
				'title' => '',
				'text' => '',
				'items' => [],
			];

			$packages = $section->set('title|heading|text|remote|rule|modification|language|avatar-pack|theme|smiley-set');

			foreach ($packages as $thisPackage) {
				$package = [
					'type' => $thisPackage->name(),
				];

				if (\in_array($package['type'], ['title', 'text'])) {
					Utils::$context['package_list'][$packageSection][$package['type']] = Utils::htmlspecialchars($thisPackage->fetch('.'));
				}
				// It's a Title, Heading, Rule or Text.
				elseif (\in_array($package['type'], ['heading', 'rule'])) {
					$package['name'] = Utils::htmlspecialchars($thisPackage->fetch('.'));
				}
				// It's a Remote link.
				elseif ($package['type'] == 'remote') {
					$remote_type = $thisPackage->exists('@type') ? $thisPackage->fetch('@type') : 'relative';

					if ($remote_type == 'relative' && !str_starts_with($thisPackage->fetch('@href'), 'http://') && !str_starts_with($thisPackage->fetch('@href'), 'https://')) {
						if (isset($_GET['absolute'])) {
							$current_url = $_GET['absolute'] . '/';
						} elseif (isset($_GET['relative'])) {
							$current_url = $_GET['relative'] . '/';
						} else {
							$current_url = '';
						}

						$current_url .= $thisPackage->fetch('@href');

						if (isset($_GET['absolute'])) {
							$package['href'] = Config::$scripturl . '?action=admin;area=packages;get;sa=browse;absolute=' . $current_url;
						} else {
							$package['href'] = Config::$scripturl . '?action=admin;area=packages;get;sa=browse;server=' . Utils::$context['package_server'] . ';relative=' . $current_url;
						}
					} else {
						$current_url = $thisPackage->fetch('@href');
						$package['href'] = Config::$scripturl . '?action=admin;area=packages;get;sa=browse;absolute=' . $current_url;
					}

					$package['name'] = Utils::htmlspecialchars($thisPackage->fetch('.'));
					$package['link'] = '<a href="' . $package['href'] . '">' . $package['name'] . '</a>';
				}
				// It's a package...
				else {
					if (isset($_GET['absolute'])) {
						$current_url = $_GET['absolute'] . '/';
					} elseif (isset($_GET['relative'])) {
						$current_url = $_GET['relative'] . '/';
					} else {
						$current_url = '';
					}

					$server_att = $server != '' ? ';server=' . $server : '';

					$package += $thisPackage->to_array();

					if (isset($package['website'])) {
						unset($package['website']);
					}
					$package['author'] = [];

					if ($package['description'] == '') {
						$package['description'] = Lang::getTxt('package_no_description', file: 'Packages');
					} else {
						$package['description'] = Utils::adjustHeadingLevels(Parser::transform(preg_replace('~\[[/]?html\]~i', '', Utils::htmlspecialchars($package['description']))), null);
					}

					$package['is_installed'] = isset($installed_mods[$package['id']]);
					$package['is_current'] = $package['is_installed'] && ($installed_mods[$package['id']] == $package['version']);
					$package['is_newer'] = $package['is_installed'] && ($installed_mods[$package['id']] > $package['version']);

					// This package is either not installed, or installed but old.  Is it supported on this version of SMF?
					if (!$package['is_installed'] || (!$package['is_current'] && !$package['is_newer'])) {
						if ($thisPackage->exists('version/@for')) {
							$package['can_install'] = PackageUtils::matchPackageVersion($the_version, $thisPackage->fetch('version/@for'));
						}
					}
					// Okay, it's already installed AND up to date.
					else {
						$package['can_install'] = false;
					}

					$already_exists = PackageUtils::getPackageInfo(basename($package['filename']));
					$package['download_conflict'] = \is_array($already_exists) && $already_exists['id'] == $package['id'] && $already_exists['version'] != $package['version'];

					$package['href'] = $url . '/' . $package['filename'];
					$package['link'] = '<a href="' . $package['href'] . '">' . $package['name'] . '</a>';
					$package['download']['href'] = Config::$scripturl . '?action=admin;area=packages;get;sa=download' . $server_att . ';package=' . $current_url . $package['filename'] . ($package['download_conflict'] ? ';conflict' : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					$package['download']['link'] = '<a href="' . $package['download']['href'] . '">' . $package['name'] . '</a>';

					if ($thisPackage->exists('author') || isset($default_author)) {
						if ($thisPackage->exists('author/@email') && EmailAddress::create($thisPackage->fetch('author/@email'))->isValid()) {
							$package['author']['email'] = $thisPackage->fetch('author/@email');
						} elseif (isset($default_email)) {
							$package['author']['email'] = $default_email;
						}

						if ($thisPackage->exists('author') && $thisPackage->fetch('author') != '') {
							$package['author']['name'] = Utils::htmlspecialchars($thisPackage->fetch('author'));
						} else {
							$package['author']['name'] = $default_author;
						}

						if (!empty($package['author']['email'])) {
							$package['author']['link'] = '<a href="mailto:' . $package['author']['email'] . '">' . $package['author']['name'] . '</a>';
						}
					}

					if ($thisPackage->exists('website') || isset($default_website)) {
						if ($thisPackage->exists('website') && $thisPackage->exists('website/@title')) {
							$package['author']['website']['name'] = Utils::htmlspecialchars($thisPackage->fetch('website/@title'));
						} elseif (isset($default_title)) {
							$package['author']['website']['name'] = $default_title;
						} elseif ($thisPackage->exists('website')) {
							$package['author']['website']['name'] = Utils::htmlspecialchars($thisPackage->fetch('website'));
						} else {
							$package['author']['website']['name'] = $default_website;
						}

						if ($thisPackage->exists('website') && $thisPackage->fetch('website') != '') {
							$authorhompage = Utils::htmlspecialchars($thisPackage->fetch('website'));
						} else {
							$authorhompage = $default_website;
						}

						$package['author']['website']['href'] = $authorhompage;
						$package['author']['website']['link'] = '<a href="' . $authorhompage . '">' . $package['author']['website']['name'] . '</a>';
					} else {
						$package['author']['website']['href'] = '';
						$package['author']['website']['link'] = '';
					}
				}

				$package['is_remote'] = $package['type'] == 'remote';
				$package['is_title'] = $package['type'] == 'title';
				$package['is_heading'] = $package['type'] == 'heading';
				$package['is_text'] = $package['type'] == 'text';
				$package['is_line'] = $package['type'] == 'rule';

				$packageNum = \in_array($package['type'], ['title', 'heading', 'text', 'remote', 'rule']) ? 0 : $packageNum + 1;
				$package['count'] = $packageNum;

				if (!\in_array($package['type'], ['title', 'text'])) {
					Utils::$context['package_list'][$packageSection]['items'][] = $package;
				}

				if ($package['count'] > 1) {
					Utils::$context['list_type'] = 'ol';
				}
			}

			$packageSection++;
		}

		// Lets make sure we get a nice new spiffy clean $package to work with.  Otherwise we get PAIN!
		unset($package);

		foreach (Utils::$context['package_list'] as $ps_id => $packageSection) {
			foreach ($packageSection['items'] as $i => $package) {
				if ($package['count'] == 0 || isset($package['can_install'])) {
					continue;
				}

				Utils::$context['package_list'][$ps_id]['items'][$i]['can_install'] = false;

				$packageInfo = PackageUtils::getPackageInfo($url . '/' . $package['filename']);

				if (\is_array($packageInfo) && $packageInfo['xml']->exists('install')) {
					$installs = $packageInfo['xml']->set('install');

					foreach ($installs as $install) {
						if (!$install->exists('@for') || PackageUtils::matchPackageVersion($the_version, $install->fetch('@for'))) {
							// Okay, this one is good to go.
							Utils::$context['package_list'][$ps_id]['items'][$i]['can_install'] = true;
							break;
						}

						// no install found for this version, lets see if one exists for another
						if (Utils::$context['package_list'][$ps_id]['items'][$i]['can_install'] === false && $install->exists('@for')) {
							$reset = true;

							// Get the highest install version that is available from the package
							foreach ($installs as $install) {
								Utils::$context['package_list'][$ps_id]['items'][$i]['can_emulate_install'] = PackageUtils::matchHighestPackageVersion($install->fetch('@for'), $reset, $the_version);
								$reset = false;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Download a package.
	 */
	public function download(): void
	{
		// Use the downloaded sub template.
		Utils::$context['sub_template'] = 'downloaded';

		// Security is good...
		User::$me->checkSession('get');

		// To download something, we need a valid server or url.
		if (empty($_GET['server']) && (!empty($_GET['get']) && !empty($_REQUEST['package']))) {
			ErrorHandler::fatalLang('package_get_error_is_zero', false);
		}

		if (isset($_GET['server'])) {
			$server = (int) $_GET['server'];

			// Query the server table to find the requested server.
			$request = Db::$db->query(
				'SELECT name, url
				FROM {db_prefix}package_servers
				WHERE id_server = {int:current_server}
				LIMIT 1',
				[
					'current_server' => $server,
				],
			);
			list($name, $url) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// If server does not exist then dump out.
			if (empty($url)) {
				ErrorHandler::fatalLang('couldnt_connect', false);
			}

			$the_version = SMF_VERSION;

			if (!empty($_SESSION['version_emulate'])) {
				$the_version = $_SESSION['version_emulate'];
			}

			// Sub out any variables we support in the url.
			$url = strtr($url, [
				'{SMF_VERSION}' => urlencode($the_version),
			]);

			$url = $url . '/';
		} else {
			// Initialize the required variables.
			$server = '';
			$url = '';
		}

		if (isset($_REQUEST['byurl']) && !empty($_POST['filename'])) {
			$package_name = basename($_REQUEST['filename']);
		} else {
			$package_name = basename($_REQUEST['package']);
		}

		if (isset($_REQUEST['conflict']) || (isset($_REQUEST['auto']) && file_exists(Config::$packagesdir . '/' . $package_name))) {
			// Find the extension, change abc.tar.gz to abc_1.tar.gz...
			if (strrpos(substr($package_name, 0, -3), '.') !== false) {
				$ext = substr($package_name, strrpos(substr($package_name, 0, -3), '.'));
				$package_name = substr($package_name, 0, strrpos(substr($package_name, 0, -3), '.')) . '_';
			} else {
				$ext = '';
			}

			// Find the first available.
			$i = 1;

			while (file_exists(Config::$packagesdir . '/' . $package_name . $i . $ext)) {
				$i++;
			}

			$package_name = $package_name . $i . $ext;
		}

		// Use FTP if necessary.
		PackageUtils::createChmodControl([Config::$packagesdir . '/' . $package_name], ['destination_url' => Config::$scripturl . '?action=admin;area=packages;get;sa=download' . (isset($_GET['server']) ? ';server=' . $_GET['server'] : '') . (isset($_REQUEST['auto']) ? ';auto' : '') . ';package=' . $_REQUEST['package'] . (isset($_REQUEST['conflict']) ? ';conflict' : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], 'crash_on_error' => true]);
		PackageUtils::packagePutContents(Config::$packagesdir . '/' . $package_name, WebFetchApi::fetch($url . $_REQUEST['package']));

		// Done!  Did we get this package automatically?
		// @ TODO: These are usually update packages.  Allowing both for now until more testing has been done.
		if (preg_match('~^https?://[\w_\-]+\.simplemachines\.org/~', $_REQUEST['package']) == 1 && !str_contains($_REQUEST['package'], 'dlattach') && isset($_REQUEST['auto'])) {
			Utils::redirectexit('action=admin;area=packages;sa=install;package=' . $package_name);
		}

		// You just downloaded a mod from SERVER_NAME_GOES_HERE.
		Utils::$context['package_server'] = $server;

		Utils::$context['package'] = PackageUtils::getPackageInfo($package_name);

		if (!\is_array(Utils::$context['package'])) {
			ErrorHandler::fatalLang('package_cant_download', false);
		}

		if (!isset(Utils::$context['package']['type'])) {
			Utils::$context['package']['install']['link'] = '';
		} else {
			Utils::$context['package']['install']['link'] = '<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=install;package=' . Utils::$context['package']['filename'] . '">[ ' . Lang::getTxt(Lang::txtExists('install_' . Utils::$context['package']['type'], file: 'Packages') ? 'install_' . Utils::$context['package']['type'] : 'install_unknown', file: 'Packages') . ' ]</a>';
		}

		// Does a 3rd party hook want to do some additional changes?
		IntegrationHook::call('integrate_package_download');

		Utils::$context['package']['list_files']['link'] = '<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=list;package=' . Utils::$context['package']['filename'] . '">[ ' . Lang::getTxt('list_files', file: 'Packages') . ' ]</a>';

		// Free a little bit of memory...
		unset(Utils::$context['package']['xml']);

		Utils::$context['page_title'] = Lang::getTxt('download_success', file: 'Packages');
	}

	/**
	 * Upload a new package to the directory.
	 */
	public function upload(): void
	{
		// Setup the correct template, even though I'll admit we ain't downloading ;)
		Utils::$context['sub_template'] = 'downloaded';

		// @todo Use FTP if the Packages directory is not writable.

		// Check the file was even sent!
		if (!isset($_FILES['package']['name']) || $_FILES['package']['name'] == '') {
			ErrorHandler::fatalLang('package_upload_error_nofile', false);
		} elseif (!is_uploaded_file($_FILES['package']['tmp_name']) || (\ini_get('open_basedir') == '' && !file_exists($_FILES['package']['tmp_name']))) {
			ErrorHandler::fatalLang('package_upload_error_failed', false);
		}

		// Make sure it has a sane filename.
		$_FILES['package']['name'] = preg_replace(['/\s/', '/\.+/', '/[^\w.-]/'], ['_', '.', ''], $_FILES['package']['name']);

		$found_ext = preg_match('/\.(zip|tgz|tar\.gz)$/i', $_FILES['package']['name'], $match);

		if ($found_ext === 0) {
			ErrorHandler::fatalLang('package_upload_error_supports', false, ['zip, tgz, tar.gz']);
		}

		// We only need the filename...
		$packageName = substr($_FILES['package']['name'], 0, -\strlen($match[0]));
		$packageFileName = PackageUtils::generateUniqueFilename(Config::$packagesdir, $packageName, $match[1]) . $match[0];

		// Setup the destination and throw an error if the file is already there!
		$destination = Config::$packagesdir . '/' . $packageFileName;

		if (file_exists($destination)) {
			ErrorHandler::fatalLang('package_upload_error_exists', false);
		}

		// Now move the file.
		move_uploaded_file($_FILES['package']['tmp_name'], $destination);
		Utils::makeWritable($destination, 0777);

		// If we got this far that should mean it's available.
		Utils::$context['package'] = PackageUtils::getPackageInfo($packageFileName);
		Utils::$context['package_server'] = '';

		// Not really a package, you lazy bum!
		if (!\is_array(Utils::$context['package'])) {
			@unlink($destination);
			ErrorHandler::fatalLang(
				'package_upload_error_broken',
				false,
				[
					Lang::getTxt(
						Utils::$context['package'],
						[
							'MANAGETHEMEURL' => Config::$scripturl . '?action=admin;area=theme;sa=admin;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '#theme_install',
						],
						file: 'Errors',
					),
				],
			);
		}
		// Is it already uploaded, maybe?
		elseif ($dir = @opendir(Config::$packagesdir)) {
			while ($package = readdir($dir)) {
				if ($package == '.' || $package == '..' || $package == 'temp' || $package == $packageFileName || (!(is_dir(Config::$packagesdir . '/' . $package) && file_exists(Config::$packagesdir . '/' . $package . '/package-info.xml')) && !str_ends_with(strtolower($package), '.tar.gz') && !str_ends_with(strtolower($package), '.tgz') && !str_ends_with(strtolower($package), '.zip'))) {
					continue;
				}

				$packageInfo = PackageUtils::getPackageInfo($package);

				if (!\is_array($packageInfo)) {
					continue;
				}

				if ($packageInfo['id'] == Utils::$context['package']['id'] && $packageInfo['version'] == Utils::$context['package']['version']) {
					@unlink($destination);
					ErrorHandler::fatalLang('package_upload_error_exists', false);
				}
			}
			closedir($dir);
		}

		if (!isset(Utils::$context['package']['type'])) {
			Utils::$context['package']['install']['link'] = '';
		} else {
			Utils::$context['package']['install']['link'] = '<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=install;package=' . Utils::$context['package']['filename'] . '">[ ' . Lang::getTxt(Lang::txtExists('install_' . Utils::$context['package']['type'], file: 'Packages') ? 'install_' . Utils::$context['package']['type'] : 'install_unknown', file: 'Packages') . ' ]</a>';
		}

		// Does a 3rd party hook want to do some additional changes?
		IntegrationHook::call('integrate_package_upload');

		Utils::$context['package']['list_files']['link'] = '<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=list;package=' . Utils::$context['package']['filename'] . '">[ ' . Lang::getTxt('list_files', file: 'Packages') . ' ]</a>';

		unset(Utils::$context['package']['xml']);

		Utils::$context['page_title'] = Lang::getTxt('package_uploaded_success', file: 'Packages');
	}

	/**
	 * Add a package server to the list.
	 */
	public function serverAdd(): void
	{
		// Validate the user.
		User::$me->checkSession();

		// If they put a slash on the end, get rid of it.
		if (str_ends_with($_POST['serverurl'], '/')) {
			$_POST['serverurl'] = substr($_POST['serverurl'], 0, -1);
		}

		// Are they both nice and clean?
		$servername = trim(Utils::htmlspecialchars($_POST['servername']));
		$serverurl = trim(Utils::htmlspecialchars($_POST['serverurl']));

		// Make sure the URL has the correct prefix.
		if (!str_starts_with($serverurl, 'http://') && !str_starts_with($serverurl, 'https://')) {
			$serverurl = 'http://' . $serverurl;
		}

		Db::$db->insert(
			'',
			'{db_prefix}package_servers',
			[
				'name' => 'string-255',
				'url' => 'string-255',
			],
			[
				[
					$servername,
					$serverurl,
				],
			],
			['id_server'],
		);

		Utils::redirectexit('action=admin;area=packages;get');
	}

	/**
	 * Remove a server from the list.
	 */
	public function serverRemove(): void
	{
		User::$me->checkSession('get');

		Db::$db->query(
			'DELETE FROM {db_prefix}package_servers
			WHERE id_server = {int:current_server}',
			[
				'current_server' => (int) $_GET['server'],
			],
		);

		Utils::redirectexit('action=admin;area=packages;get');
	}

	/**
	 * Get a listing of all the packages
	 *
	 * Determines if the package is a mod, avatar, or language package and
	 * groups it accordingly. If a package is not recognised as one of the
	 * above, it is then put into a special group, "unknown".
	 *
	 * Determines whether the package has been installed or not by
	 * checking it against {@link loadInstalledPackages()}.
	 *
	 * @param int $start The item to start with (not used here)
	 * @param int $items_per_page The number of items to show per page (not used here)
	 * @param string $sort A string indicating how to sort the results
	 * @param string $params Type of packages
	 * @return array An array of information about the packages
	 */
	public function list_getPackages(int $start, int $items_per_page, string $sort, string $params): array
	{
		static $installed_mods;

		$packages = [];
		$column = [];

		// We need the packages directory to be writable for this.
		if (!@is_writable(Config::$packagesdir)) {
			PackageUtils::createChmodControl([Config::$packagesdir], ['destination_url' => Config::$scripturl . '?action=admin;area=packages', 'crash_on_error' => true]);
		}

		$the_version = SMF_VERSION;

		// Here we have a little code to help those who class themselves as something of gods, version emulation ;)
		if (isset($_GET['version_emulate']) && strtr($_GET['version_emulate'], ['SMF ' => '']) == $the_version) {
			unset($_SESSION['version_emulate']);
		} elseif (isset($_GET['version_emulate'])) {
			if (($_GET['version_emulate'] === 0 || $_GET['version_emulate'] === SMF_FULL_VERSION) && isset($_SESSION['version_emulate'])) {
				unset($_SESSION['version_emulate']);
			} elseif ($_GET['version_emulate'] !== 0) {
				$_SESSION['version_emulate'] = strtr($_GET['version_emulate'], ['-' => ' ', '+' => ' ', 'SMF ' => '']);
			}
		}

		if (!empty($_SESSION['version_emulate'])) {
			Utils::$context['forum_version'] = 'SMF ' . $_SESSION['version_emulate'];
			$the_version = $_SESSION['version_emulate'];
		}

		if (isset($_SESSION['single_version_emulate'])) {
			unset($_SESSION['single_version_emulate']);
		}

		if (empty($installed_mods)) {
			$instmods = PackageUtils::loadInstalledPackages();
			$installed_mods = [];

			// Look through the list of installed mods...
			foreach ($instmods as $installed_mod) {
				$installed_mods[$installed_mod['package_id']] = [
					'id' => $installed_mod['id'],
					'version' => $installed_mod['version'],
					'time_installed' => $installed_mod['time_installed'],
				];
			}

			// Get a list of all the ids installed, so the latest packages won't include already installed ones.
			Utils::$context['installed_mods'] = array_keys($installed_mods);
		}

		if ($dir = @opendir(Config::$packagesdir)) {
			$dirs = [];
			$sort_id = [
				'modification' => 1,
				'avatar' => 1,
				'language' => 1,
				'unknown' => 1,
				'smiley' => 1,
			];
			IntegrationHook::call('integrate_packages_sort_id', [&$sort_id, &$packages]);

			while ($package = readdir($dir)) {
				if ($package == '.' || $package == '..' || $package == 'temp' || (!(is_dir(Config::$packagesdir . '/' . $package) && file_exists(Config::$packagesdir . '/' . $package . '/package-info.xml')) && !str_ends_with(strtolower($package), '.tar.gz') && !str_ends_with(strtolower($package), '.tgz') && !str_ends_with(strtolower($package), '.zip'))) {
					continue;
				}

				// Skip directories or files that are named the same.
				if (is_dir(Config::$packagesdir . '/' . $package)) {
					if (\in_array($package, $dirs)) {
						continue;
					}
					$dirs[] = $package;
				} elseif (str_ends_with(strtolower($package), '.tar.gz')) {
					if (\in_array(substr($package, 0, -7), $dirs)) {
						continue;
					}
					$dirs[] = substr($package, 0, -7);
				} elseif (str_ends_with(strtolower($package), '.zip') || str_ends_with(strtolower($package), '.tgz')) {
					if (\in_array(substr($package, 0, -4), $dirs)) {
						continue;
					}
					$dirs[] = substr($package, 0, -4);
				}

				$packageInfo = PackageUtils::getPackageInfo($package);

				if (!\is_array($packageInfo)) {
					continue;
				}

				if (!empty($packageInfo)) {
					if (!isset($sort_id[$packageInfo['type']])) {
						$packageInfo['sort_id'] = $sort_id['unknown'];
					} else {
						$packageInfo['sort_id'] = $sort_id[$packageInfo['type']];
					}

					$packageInfo['time_installed'] = 0;
					$packageInfo['is_installed'] = isset($installed_mods[$packageInfo['id']]);

					if ($packageInfo['is_installed']) {
						$packageInfo['is_current'] = $installed_mods[$packageInfo['id']]['version'] == $packageInfo['version'];
						$packageInfo['is_newer'] = $installed_mods[$packageInfo['id']]['version'] > $packageInfo['version'];
						$packageInfo['installed_id'] = $installed_mods[$packageInfo['id']]['id'];

						if ($packageInfo['is_current']) {
							$packageInfo['time_installed'] = $installed_mods[$packageInfo['id']]['time_installed'];
						}
					}

					$packageInfo['can_install'] = false;
					$packageInfo['can_uninstall'] = false;
					$packageInfo['can_upgrade'] = false;
					$packageInfo['can_emulate_install'] = false;
					$packageInfo['can_emulate_uninstall'] = false;

					// This package is currently NOT installed.  Check if it can be.
					if (!$packageInfo['is_installed'] && $packageInfo['xml']->exists('install')) {
						// Check if there's an install for *THIS* version of SMF.
						$installs = $packageInfo['xml']->set('install');

						foreach ($installs as $install) {
							if (!$install->exists('@for') || PackageUtils::matchPackageVersion($the_version, $install->fetch('@for'))) {
								// Okay, this one is good to go.
								$packageInfo['can_install'] = true;
								break;
							}
						}

						// no install found for this version, lets see if one exists for another
						if ($packageInfo['can_install'] === false && $install->exists('@for') && empty($_SESSION['version_emulate'])) {
							$reset = true;

							// Get the highest install version that is available from the package
							foreach ($installs as $install) {
								$packageInfo['can_emulate_install'] = PackageUtils::matchHighestPackageVersion($install->fetch('@for'), $reset, $the_version);
								$reset = false;
							}
						}
					}
					// An already installed, but old, package.  Can we upgrade it?
					elseif ($packageInfo['is_installed'] && !$packageInfo['is_current'] && $packageInfo['xml']->exists('upgrade')) {
						$upgrades = $packageInfo['xml']->set('upgrade');

						// First go through, and check against the current version of SMF.
						foreach ($upgrades as $upgrade) {
							// Even if it is for this SMF, is it for the installed version of the mod?
							if (!$upgrade->exists('@for') || PackageUtils::matchPackageVersion($the_version, $upgrade->fetch('@for'))) {
								if (!$upgrade->exists('@from') || PackageUtils::matchPackageVersion((string) $installed_mods[$packageInfo['id']]['version'], $upgrade->fetch('@from'))) {
									$packageInfo['can_upgrade'] = true;
									break;
								}
							}
						}
					}
					// Note that it has to be the current version to be uninstallable.  Shucks.
					elseif ($packageInfo['is_installed'] && $packageInfo['is_current'] && $packageInfo['xml']->exists('uninstall')) {
						$uninstalls = $packageInfo['xml']->set('uninstall');

						// Can we find any uninstallation methods that work for this SMF version?
						foreach ($uninstalls as $uninstall) {
							if (!$uninstall->exists('@for') || PackageUtils::matchPackageVersion($the_version, $uninstall->fetch('@for'))) {
								$packageInfo['can_uninstall'] = true;
								break;
							}
						}

						// no uninstall found for this version, lets see if one exists for another
						if ($packageInfo['can_uninstall'] === false && $uninstall->exists('@for') && empty($_SESSION['version_emulate'])) {
							$reset = true;

							// Get the highest install version that is available from the package
							foreach ($uninstalls as $uninstall) {
								$packageInfo['can_emulate_uninstall'] = PackageUtils::matchHighestPackageVersion($uninstall->fetch('@for'), $reset, $the_version);
								$reset = false;
							}
						}
					}

					// Save some memory by not passing the XmlArray object into context.
					unset($packageInfo['xml']);

					if (isset($sort_id[$packageInfo['type']]) && $params == $packageInfo['type']) {
						$column[] = $packageInfo[$sort];
						$sort_id[$packageInfo['type']]++;
						$packages[] = $packageInfo;
					} elseif (!isset($sort_id[$packageInfo['type']]) && $params == 'unknown') {
						$column[] = $packageInfo[$sort];
						$packageInfo['sort_id'] = $sort_id['unknown'];
						$sort_id['unknown']++;
						$packages[] = $packageInfo;
					}
				}
			}
			closedir($dir);
		}
		Utils::$context['available_packages'] += \count($packages);
		array_multisort(
			$column,
			isset($_GET['desc']) ? SORT_DESC : SORT_ASC,
			$packages,
		);

		return $packages;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Instantiates this class, but never more than once.
	 *
	 * @todo Add a reference to Utils::$context['instances'] as well?
	 *
	 * @return self An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Protected constructor in order to force instantiation via load()
	 */
	protected function __construct()
	{
		User::$me->isAllowedTo('admin_forum');

		// Backward compatibility with old URLs.
		if (isset($_REQUEST['get']) || (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'packageget')) {
			if (!isset($_REQUEST['sa'])) {
				$_REQUEST['sa'] = 'servers';
			}

			if (\in_array($_REQUEST['sa'], ['add', 'remove', 'browse'])) {
				$_REQUEST['sa'] = 'server' . $_REQUEST['sa'];
			}

			if (!isset($this->subactions[$_REQUEST['sa']])) {
				$_REQUEST['sa'] = 'servers';
			}

			$temp = array_map(fn($sa) => $this->subactions[$sa], $this->packageget_subactions);

			// MOD AUTHORS: integrate_package_get is deprecated. Use integrate_manage_packages instead.
			if (!empty(Config::$backward_compatibility)) {
				IntegrationHook::call('integrate_package_get', [&$temp]);
			}

			foreach ($temp as $sa => $func) {
				$this->subactions[$this->packageget_subactions[$sa] ?? $sa] = $func;
			}
		} elseif (isset($_REQUEST['pgdownload'])) {
			$_REQUEST['sa'] = 'download';
		}

		// Give mods access to the sub-actions.
		IntegrationHook::call('integrate_manage_packages', [&$this->subactions]);
	}

	/**
	 * Checks the permissions of all the areas that will be affected by the package
	 *
	 * @param string $path The path to the directory to check permissions for
	 * @param array $data An array of data about the directory
	 * @param int $level How far deep to go
	 */
	protected function fetchPerms__recursive(string $path, array &$data, int $level): void
	{
		$isLikelyPath = false;

		foreach (Utils::$context['look_for'] as $possiblePath) {
			if (str_starts_with($possiblePath, $path)) {
				$isLikelyPath = true;
			}
		}

		// Is this where we stop?
		if (isset($_GET['xml']) && !empty(Utils::$context['look_for']) && !$isLikelyPath) {
			return;
		}

		if ($level > Utils::$context['default_level'] && !$isLikelyPath) {
			return;
		}

		// Are we actually interested in saving this data?
		$save_data = empty(Utils::$context['only_find']) || Utils::$context['only_find'] == $path;

		// @todo Shouldn't happen - but better error message?
		if (!is_dir($path)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// This is where we put stuff we've found for sorting.
		$foundData = [
			'files' => [],
			'folders' => [],
		];

		$dh = opendir($path);

		while ($entry = readdir($dh)) {
			// Bypass directory abbreviations altogether...
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			// Some kind of file?
			if (is_file($path . '/' . $entry)) {
				// Are we listing PHP files in this directory?
				if ($save_data && !empty($data['list_contents']) && str_ends_with($entry, '.php')) {
					$foundData['files'][$entry] = true;
				}
				// A file we were looking for.
				elseif ($save_data && isset($data['contents'][$entry])) {
					$foundData['files'][$entry] = true;
				}
			}
			// It's a directory - we're interested one way or another, probably...
			else {
				// Going further?
				if ((!empty($data['type']) && $data['type'] == 'dir_recursive') || (isset($data['contents'][$entry]) && (!empty($data['contents'][$entry]['list_contents']) || (!empty($data['contents'][$entry]['type']) && $data['contents'][$entry]['type'] == 'dir_recursive')))) {
					if (!isset($data['contents'][$entry])) {
						$foundData['folders'][$entry] = 'dir_recursive';
					} else {
						$foundData['folders'][$entry] = true;
					}

					// If this wasn't expected inherit the recursiveness...
					if (!isset($data['contents'][$entry])) {
						// We need to do this as we will be going all recursive.
						$data['contents'][$entry] = [
							'type' => 'dir_recursive',
						];
					}

					// Actually do the recursive stuff...
					$this->fetchPerms__recursive($path . '/' . $entry, $data['contents'][$entry], $level + 1);
				}
				// Maybe it is a folder we are not descending into.
				elseif (isset($data['contents'][$entry])) {
					$foundData['folders'][$entry] = true;
				}
				// Otherwise we stop here.
			}
		}
		closedir($dh);

		// Nothing to see here?
		if (!$save_data) {
			return;
		}

		// Now actually add the data, starting with the folders.
		ksort($foundData['folders']);

		foreach ($foundData['folders'] as $folder => $type) {
			$additional_data = [
				'perms' => [
					'chmod' => @is_writable($path . '/' . $folder),
					'perms' => @fileperms($path . '/' . $folder),
				],
			];

			if ($type !== true) {
				$additional_data['type'] = $type;
			}

			// If there's an offset ignore any folders in XML mode.
			if (isset($_GET['xml']) && Utils::$context['file_offset'] == 0) {
				Utils::$context['xml_data']['folders']['children'][] = [
					'attributes' => [
						'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
						'permissions' => substr(\sprintf('%o', $additional_data['perms']['perms']), -4),
						'folder' => 1,
						'path' => Utils::$context['only_find'],
						'level' => $level,
						'more' => 0,
						'offset' => Utils::$context['file_offset'],
						'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find'] . '/' . $folder),
						'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find']),
					],
					'value' => $folder,
				];
			} elseif (!isset($_GET['xml'])) {
				if (isset($data['contents'][$folder])) {
					$data['contents'][$folder] = array_merge($data['contents'][$folder], $additional_data);
				} else {
					$data['contents'][$folder] = $additional_data;
				}
			}
		}

		// Now we want to do a similar thing with files.
		ksort($foundData['files']);
		$counter = -1;

		foreach ($foundData['files'] as $file => $dummy) {
			$counter++;

			// Have we reached our offset?
			if (Utils::$context['file_offset'] > $counter) {
				continue;
			}

			// Gone too far?
			if ($counter > (Utils::$context['file_offset'] + Utils::$context['file_limit'])) {
				continue;
			}

			$additional_data = [
				'perms' => [
					'chmod' => @is_writable($path . '/' . $file),
					'perms' => @fileperms($path . '/' . $file),
				],
			];

			// XML?
			if (isset($_GET['xml'])) {
				Utils::$context['xml_data']['folders']['children'][] = [
					'attributes' => [
						'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
						'permissions' => substr(\sprintf('%o', $additional_data['perms']['perms']), -4),
						'folder' => 0,
						'path' => Utils::$context['only_find'],
						'level' => $level,
						'more' => $counter == (Utils::$context['file_offset'] + Utils::$context['file_limit']) ? 1 : 0,
						'offset' => Utils::$context['file_offset'],
						'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find'] . '/' . $file),
						'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find']),
					],
					'value' => $file,
				];
			} elseif ($counter != (Utils::$context['file_offset'] + Utils::$context['file_limit'])) {
				if (isset($data['contents'][$file])) {
					$data['contents'][$file] = array_merge($data['contents'][$file], $additional_data);
				} else {
					$data['contents'][$file] = $additional_data;
				}
			}
		}
	}

	/**
	 * Counts all the directories under a given path
	 *
	 * @param string $dir
	 * @return int
	 */
	protected function count_directories__recursive(string $dir): int
	{
		$count = 0;
		$dh = @opendir($dir);

		while ($entry = readdir($dh)) {
			if ($entry != '.' && $entry != '..' && is_dir($dir . '/' . $entry)) {
				Utils::$context['directory_list'][$dir . '/' . $entry] = 1;
				$count++;
				$count += $this->count_directories__recursive($dir . '/' . $entry);
			}
		}
		closedir($dh);

		return $count;
	}

	/**
	 * Builds a list of special files recursively for a given path
	 *
	 * @param string $path
	 * @param array $data
	 */
	protected function build_special_files__recursive(string $path, array &$data): void
	{
		if (!empty($data['writable_on'])) {
			if (Utils::$context['predefined_type'] == 'standard' || $data['writable_on'] == 'restrictive') {
				Utils::$context['special_files'][$path] = 1;
			}
		}

		if (!empty($data['contents'])) {
			foreach ($data['contents'] as $name => $contents) {
				$this->build_special_files__recursive($path . '/' . $name, $contents);
			}
		}
	}
}
