<?php

/**
 * This file takes care of all administration of smileys.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\MessageIndex;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Msg;
use SMF\PackageManager\SubsPackage;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * This class takes care of all administration of smileys.
 */
class Smileys implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageSmileys',
			'list_getSmileySets' => 'list_getSmileySets',
			'list_getNumSmileySets' => 'list_getNumSmileySets',
			'list_getSmileys' => 'list_getSmileys',
			'list_getNumSmileys' => 'list_getNumSmileys',
			'list_getMessageIcons' => 'list_getMessageIcons',
			'addSmiley' => 'AddSmiley',
			'editSmileys' => 'EditSmileys',
			'editSmileyOrder' => 'EditSmileyOrder',
			'installSmileySet' => 'InstallSmileySet',
			'editMessageIcons' => 'EditMessageIcons',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// code...

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'editsets';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'editsets' => 'editSets',
		'import' => 'editSets',
		'modifyset' => 'editSets',
		'addsmiley' => 'add',
		'editsmileys' => 'edit',
		'modifysmiley' => 'edit',
		'setorder' => 'setOrder',
		'install' => 'install',
		'editicon' => 'editIcon',
		'editicons' => 'editIcon',
		'settings' => 'settings',
	];

	/**
	 * @var array
	 *
	 * Allowed file extensions for smiley images.
	 */
	public static array $allowed_extenions = [
		'gif',
		'png',
		'jpg',
		'jpeg',
		'tiff',
		'svg',
	];

	/**
	 * @var array
	 *
	 * Allowed MIME types for smiley images.
	 */
	public static array $allowed_mime_types = [
		'image/gif',
		'image/png',
		'image/jpeg',
		'image/tiff',
		'image/svg+xml',
	];

	/**
	 * @var array
	 *
	 * Forbidden file names.
	 * Uploading a smiley image with one of these names will give a fatal error.
	 */
	public static array $illegal_files = [
		'con',
		'com1',
		'com2',
		'com3',
		'com4',
		'prn',
		'aux',
		'lpt1',
		'.htaccess',
		'index.php',
	];

	/**
	 * @var array
	 *
	 * Info about all known smiley sets.
	 */
	public static array $smiley_sets = [];

	/**
	 * @var string
	 *
	 * Path to the base smileys directory.
	 * All smiley sets have subdirectories inside this directory.
	 */
	public static string $smileys_dir;

	/**
	 * @var bool
	 *
	 * Whether the base smileys directory exists in the file system.
	 */
	public static bool $smileys_dir_found;

	/*********************
	 * Internal properties
	 *********************/

	// code...

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * List, add, remove, modify smileys sets.
	 */
	public function editSets()
	{
		// Set the right tab to be selected.
		Menu::$loaded['admin']['current_subsection'] = 'editsets';

		// They must have submitted a form.
		if (isset($_POST['smiley_save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-mss', 'request');

			// Delete selected smiley sets.
			if (!empty($_POST['delete']) && !empty($_POST['smiley_set'])) {
				foreach ($_POST['smiley_set'] as $id => $val) {
					// Can't delete sets that don't exist.
					if (!isset(self::$smiley_sets[$id])) {
						continue;
					}

					// Can't the default set or the only one remaining.
					if (self::$smiley_sets[$id]['is_default'] || count(self::$smiley_sets) < 2) {
						continue;
					}

					// Delete this set's entries from the smiley_files table
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}smiley_files
						WHERE smiley_set = {string:smiley_set}',
						[
							'smiley_set' => $id,
						],
					);

					// Remove this set from our lists
					unset(self::$smiley_sets[$id]);
				}

				self::saveSets();
			}
			// Add a new smiley set.
			elseif (!empty($_POST['add'])) {
				$this->subaction = 'modifyset';
			}
			// Create or modify a smiley set.
			elseif (isset($_POST['set'])) {
				// @todo Needs a more precise error message.
				if (!isset($_POST['smiley_sets_name'])) {
					ErrorHandler::fatalLang('smiley_set_not_found', false);
				}

				$_POST['smiley_sets_name'] = self::sanitizeString($_POST['smiley_sets_name']);

				// No spaces or weirdness allowed in the directory name.
				if (!isset($_POST['smiley_sets_path']) || $_POST['smiley_sets_path'] !== self::sanitizeFileName($_POST['smiley_sets_path'])) {
					ErrorHandler::fatalLang('smiley_set_dir_not_found', false, Utils::htmlspecialchars($_POST['smiley_sets_name']));
				}

				// Create a new smiley set.
				if ($_POST['set'] == -1 && isset($_POST['smiley_sets_path'])) {
					if (isset(self::$smiley_sets[$_POST['smiley_sets_path']])) {
						ErrorHandler::fatalLang('smiley_set_already_exists', false);
					}

					if (!is_dir(self::$smileys_dir . '/' . $_POST['smiley_sets_path'])) {
						$this->createDir($_POST['smiley_sets_path'], $_POST['smiley_sets_name']);
					}

					if (!empty($_POST['smiley_sets_default'])) {
						foreach (self::$smiley_sets as $id => $set) {
							self::$smiley_sets[$id]['is_default'] = false;
						}
					}

					self::$smiley_sets[$_POST['smiley_sets_path']] = [
						'id' => max(array_map(fn ($set) => $set['id'], self::$smiley_sets)) + 1,
						'raw_path' => $_POST['smiley_sets_path'],
						'raw_name' => $_POST['smiley_sets_name'],
						'path' => Utils::htmlspecialchars($_POST['smiley_sets_path']),
						'name' => Utils::htmlspecialchars($_POST['smiley_sets_name']),
						'is_default' => !empty($_POST['smiley_sets_default']),
						'is_new' => true,
					];

					self::saveSets();
				}
				// Modify an existing smiley set.
				else {
					// Make sure the smiley set exists.
					if (!isset(self::$smiley_sets[$_POST['set']])) {
						ErrorHandler::fatalLang('smiley_set_not_found', false);
					}

					// Make sure the path is not yet used by another smiley set.
					foreach (self::$smiley_sets as $id => $set) {
						if ($id === $_POST['set']) {
							continue;
						}

						if ($set['raw_path'] === $_POST['smiley_sets_path']) {
							ErrorHandler::fatalLang('smiley_set_path_already_used', false);
						}
					}

					self::$smiley_sets[$_POST['set']]['raw_path'] = $_POST['smiley_sets_path'];
					self::$smiley_sets[$_POST['set']]['raw_name'] = $_POST['smiley_sets_name'];

					if (!empty($_POST['smiley_sets_default'])) {
						foreach (self::$smiley_sets as $id => $set) {
							self::$smiley_sets[$id]['is_default'] = $id === $_POST['set'];
						}
					}

					self::saveSets();
				}

				// Import, but only the ones that match existing smileys
				$this->import($_POST['smiley_sets_path'], false);
			}

			self::resetCache();
		}

		// Importing any smileys from an existing set?
		if ($this->subaction == 'import') {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-mss', 'request');

			// Sanity check - then import.
			if (isset(self::$smiley_sets[$_GET['set']])) {
				$this->import(self::$smiley_sets[$_GET['set']]['raw_path'], true);
			}

			// Force the process to continue.
			$this->subaction = 'modifyset';
			Utils::$context['sub_template'] = 'modifyset';
		}

		// If we're modifying or adding a smiley set, some context info needs to be set.
		if ($this->subaction == 'modifyset') {
			$_GET['set'] = !isset($_GET['set']) ? -1 : $_GET['set'];

			if ($_GET['set'] == -1 || !isset(self::$smiley_sets[$_GET['set']])) {
				Utils::$context['current_set'] = [
					'id' => '-1',
					'raw_path' => '',
					'raw_name' => '',
					'path' => '',
					'name' => '',
					'is_default' => false,
					'is_new' => true,
				];
			} else {
				Utils::$context['current_set'] = &self::$smiley_sets[$_GET['set']];
				Utils::$context['current_set']['is_new'] = false;

				// Calculate whether there are any smileys in the directory that can be imported.
				if (
					!empty(Config::$modSettings['smiley_enable'])
					&& !empty(self::$smileys_dir)
					&& is_dir(self::$smileys_dir . '/' . Utils::$context['current_set']['path'])
				) {
					$smileys = [];

					$dir = dir(self::$smileys_dir . '/' . Utils::$context['current_set']['path']);

					while ($entry = $dir->read()) {
						$pathinfo = pathinfo($entry);

						if (empty($pathinfo['filename']) || empty($pathinfo['extension'])) {
							continue;
						}

						if (in_array($pathinfo['extension'], self::$allowed_extenions) && $pathinfo['filename'] != 'blank') {
							$smileys[Utils::convertCase($entry, 'fold')] = $entry;
						}
					}

					$dir->close();

					if (empty($smileys)) {
						ErrorHandler::fatalLang('smiley_set_dir_not_found', false, [Utils::$context['current_set']['name']]);
					}

					// Exclude the smileys that are already in the database.
					$request = Db::$db->query(
						'',
						'SELECT filename
						FROM {db_prefix}smiley_files
						WHERE filename IN ({array_string:smiley_list})
							AND smiley_set = {string:smiley_set}',
						[
							'smiley_list' => $smileys,
							'smiley_set' => Utils::$context['current_set']['path'],
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						if (isset($smileys[Utils::convertCase($row['filename'], 'fold')])) {
							unset($smileys[Utils::convertCase($row['filename'], 'fold')]);
						}
					}
					Db::$db->free_result($request);

					Utils::$context['current_set']['can_import'] = count($smileys);

					Utils::$context['current_set']['import_url'] = Config::$scripturl . '?action=admin;area=smileys;sa=import;set=' . Utils::$context['current_set']['path'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
				}
			}

			// Retrieve all potential smiley set directories.
			Utils::$context['smiley_set_dirs'] = [];

			if (self::$smileys_dir_found) {
				$dir = dir(self::$smileys_dir);

				while ($entry = $dir->read()) {
					if (!in_array($entry, ['.', '..']) && is_dir(self::$smileys_dir . '/' . $entry)) {
						Utils::$context['smiley_set_dirs'][] = [
							'id' => $entry,
							'path' => self::$smileys_dir . '/' . $entry,
							'selectable' => $entry == Utils::$context['current_set']['path'] || !in_array($entry, explode(',', Config::$modSettings['smiley_sets_known'])),
							'current' => $entry == Utils::$context['current_set']['path'],
						];
					}
				}

				$dir->close();

				// Creating a new set, but there are no available directories.
				// Therefore, show the UI for making a new directory.
				if (Utils::$context['current_set']['is_new']) {
					Utils::$context['make_new'] = array_filter(Utils::$context['smiley_set_dirs'], fn ($dir) => $dir['selectable']) === [];
				}
			}
		}

		// This is our safe haven.
		SecurityToken::create('admin-mss', 'request');

		// In case we need to import smileys, we need to add the token in now.
		if (isset(Utils::$context['current_set']['import_url'])) {
			Utils::$context['current_set']['import_url'] .= ';' . Utils::$context['admin-mss_token_var'] . '=' . Utils::$context['admin-mss_token'];

			Utils::$context['smiley_set_unused_message'] = sprintf(Lang::$txt['smiley_set_unused'], Config::$scripturl . '?action=admin;area=smileys;sa=editsmileys', Config::$scripturl . '?action=admin;area=smileys;sa=addsmiley', Utils::$context['current_set']['import_url']);
		}

		$listOptions = [
			'id' => 'smiley_set_list',
			'title' => Lang::$txt['smiley_sets'],
			'no_items_label' => Lang::$txt['smiley_sets_none'],
			'base_href' => Config::$scripturl . '?action=admin;area=smileys;sa=editsets',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => __CLASS__ . '::list_getSmileySets',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumSmileySets',
			],
			'columns' => [
				'default' => [
					'header' => [
						'value' => Lang::$txt['smiley_sets_default'],
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							return $rowData['is_default'] ? '<span class="main_icons valid"></span>' : '';
						},
						'class' => 'centercol',
					],
					'sort' => [
						'default' => 'is_default',
						'reverse' => 'is_default DESC',
					],
				],
				'name' => [
					'header' => [
						'value' => Lang::$txt['smiley_sets_name'],
					],
					'data' => [
						'db_htmlsafe' => 'name',
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'url' => [
					'header' => [
						'value' => Lang::$txt['smiley_sets_url'],
					],
					'data' => [
						'sprintf' => [
							'format' => Config::$modSettings['smileys_url'] . '/<strong>%1$s</strong>/...',
							'params' => [
								'path' => true,
							],
						],
					],
					'sort' => [
						'default' => 'path',
						'reverse' => 'path DESC',
					],
				],
				'modify' => [
					'header' => [
						'value' => Lang::$txt['smiley_set_modify'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=smileys;sa=modifyset;set=%1$s">' . Lang::$txt['smiley_set_modify'] . '</a>',
							'params' => [
								'path' => true,
							],
						],
						'class' => 'centercol',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							return $rowData['is_default'] ? '' : sprintf('<input type="checkbox" name="smiley_set[%1$s]">', $rowData['path']);
						},
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=smileys;sa=editsets',
				'token' => 'admin-mss',
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'value' => '<input type="hidden" name="smiley_save"><input type="submit" name="delete" value="' . Lang::$txt['smiley_sets_delete'] . '" data-confirm="' . Lang::$txt['smiley_sets_confirm'] . '" class="button you_sure"> <a class="button" href="' . Config::$scripturl . '?action=admin;area=smileys;sa=modifyset' . '">' . Lang::$txt['smiley_sets_add'] . '</a> ',
				],
				[
					'position' => 'below_table_data',
					'value' => '<input type="hidden" name="smiley_save"><input type="submit" name="delete" value="' . Lang::$txt['smiley_sets_delete'] . '" data-confirm="' . Lang::$txt['smiley_sets_confirm'] . '" class="button you_sure"> <a class="button" href="' . Config::$scripturl . '?action=admin;area=smileys;sa=modifyset' . '">' . Lang::$txt['smiley_sets_add'] . '</a> ',
				],
			],
		];

		new ItemList($listOptions);
	}

	/**
	 * Add a smiley, that's right.
	 */
	public function add()
	{
		// This will hold the names of the added files for each set
		$filename_array = [];

		// Submitting a form?
		if (isset($_POST[Utils::$context['session_var']], $_POST['smiley_code'])) {
			User::$me->checkSession();

			foreach (['smiley_code', 'smiley_filename', 'smiley_description'] as $key) {
				$_POST[$key] = Utils::normalize($_POST[$key]);
			}

			$_POST['smiley_code'] = Utils::htmlTrim($_POST['smiley_code']);

			$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

			$_POST['smiley_filename'] = Utils::htmlTrim($_POST['smiley_filename']);

			// Make sure some code was entered.
			if (empty($_POST['smiley_code'])) {
				ErrorHandler::fatalLang('smiley_has_no_code', false);
			}

			// Check whether the new code has duplicates. It should be unique.
			$request = Db::$db->query(
				'',
				'SELECT id_smiley
				FROM {db_prefix}smileys
				WHERE code = {raw:mysql_binary_statement} {string:smiley_code}',
				[
					'mysql_binary_statement' => Db::$db->title == MYSQL_TITLE ? 'BINARY' : '',
					'smiley_code' => $_POST['smiley_code'],
				],
			);

			if (Db::$db->num_rows($request) > 0) {
				ErrorHandler::fatalLang('smiley_not_unique', false);
			}
			Db::$db->free_result($request);

			// If we are uploading - check all the smiley sets are writable!
			if ($_POST['method'] != 'existing') {
				$writeErrors = [];

				foreach (self::$smiley_sets as $set) {
					if (!is_writable(self::$smileys_dir . '/' . $set['raw_path'])) {
						$writeErrors[] = $set['path'];
					}
				}

				if (!empty($writeErrors)) {
					ErrorHandler::fatalLang('smileys_upload_error_notwritable', false, [implode(', ', $writeErrors)]);
				}
			}

			// Uploading just one smiley for all of them?
			if (isset($_POST['sameall'], $_FILES['uploadSmiley']['name'])   && $_FILES['uploadSmiley']['name'] != '') {
				$filename_array = array_merge($filename_array, $this->moveImageIntoPlace($_FILES['uploadSmiley']['name'], $_FILES['uploadSmiley']['tmp_name'], array_map(fn ($set) => $set['raw_path'], self::$smiley_sets)));
			}
			// What about uploading several files?
			elseif ($_POST['method'] != 'existing') {
				foreach ($_FILES as $name => $data) {
					if ($_FILES[$name]['name'] == '') {
						ErrorHandler::fatalLang('smileys_upload_error_blank', false);
					}
				}

				foreach (self::$smiley_sets as $set => $smiley_set) {
					$filename_array = array_merge($filename_array, $this->moveImageIntoPlace($_FILES['individual_' . $smiley_set['raw_path']]['name'], $_FILES['individual_' . $smiley_set['raw_path']]['tmp_name'], [$smiley_set['raw_path']]));
				}
			}
			// Re-using an existing image
			else {
				// Make sure a filename was given
				if (empty($_POST['smiley_filename'])) {
					ErrorHandler::fatalLang('smiley_has_no_filename', false);
				}

				// And make sure it is legitimate
				$pathinfo = pathinfo($_POST['smiley_filename']);

				if (!in_array($pathinfo['extension'], self::$allowed_extenions)) {
					ErrorHandler::fatalLang('smileys_upload_error_types', false, [implode(', ', self::$allowed_extenions)]);
				}

				if (strpos($pathinfo['filename'], '.') !== false) {
					ErrorHandler::fatalLang('smileys_upload_error_illegal', false);
				}

				if (!isset(self::$smiley_sets[$pathinfo['dirname']])) {
					ErrorHandler::fatalLang('smiley_set_not_found', false);
				}

				if (!file_exists(self::$smileys_dir . '/' . $pathinfo['dirname'] . '/' . $pathinfo['basename'])) {
					ErrorHandler::fatalLang('smiley_not_found', false);
				}

				// Now ensure every set has a file to use for this smiley
				foreach (self::$smiley_sets as $set => $smiley_set) {
					unset($basename);

					// Check whether any similarly named files exist in the other set's directory
					$similar_files = glob(self::$smileys_dir . '/' . $set . '/' . $pathinfo['filename'] . '.{' . implode(',', self::$allowed_extenions) . '}', GLOB_BRACE);

					// If there's a similarly named file already there, use it.
					if (!empty($similar_files)) {
						// Prefer an exact match if there is one.
						foreach ($similar_files as $similar_file) {
							if (basename($similar_file) == $pathinfo['basename']) {
								$basename = $pathinfo['basename'];
							}
						}

						// Same name, different extension.
						if (empty($basename)) {
							$basename = basename(reset($similar_files));
						}
					}
					// Otherwise, copy the image to the other set's directory
					else {
						copy(self::$smileys_dir . '/' . $pathinfo['dirname'] . '/' . $pathinfo['basename'], self::$smileys_dir . '/' . $set . '/' . $pathinfo['basename']);

						Utils::makeWritable(self::$smileys_dir . '/' . $set . '/' . $pathinfo['basename'], 0644);

						$basename = $pathinfo['basename'];
					}

					// Double-check that everything went as expected
					if (empty($basename) || !file_exists(self::$smileys_dir . '/' . $set . '/' . $basename)) {
						ErrorHandler::fatalLang('smiley_not_found', false);
					}

					// Okay, let's add this one
					$filename_array[$set] = $basename;
				}
			}

			// Find the position on the right.
			$smiley_order = '0';

			if ($_POST['smiley_location'] != 1) {
				$request = Db::$db->query(
					'',
					'SELECT MAX(smiley_order) + 1
					FROM {db_prefix}smileys
					WHERE hidden = {int:smiley_location}
						AND smiley_row = {int:first_row}',
					[
						'smiley_location' => $_POST['smiley_location'],
						'first_row' => 0,
					],
				);
				list($smiley_order) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				if (empty($smiley_order)) {
					$smiley_order = '0';
				}
			}

			// Add the new smiley to the main smileys table
			$new_id_smiley = Db::$db->insert(
				'',
				'{db_prefix}smileys',
				[
					'code' => 'string-30', 'description' => 'string-80', 'hidden' => 'int', 'smiley_order' => 'int',
				],
				[
					$_POST['smiley_code'], $_POST['smiley_description'], $_POST['smiley_location'], $smiley_order,
				],
				['id_smiley'],
				1,
			);

			// Add the filename info to the smiley_files table
			$inserts = [];

			foreach ($filename_array as $set => $basename) {
				$inserts[] = [$new_id_smiley, $set, $basename];
			}

			Db::$db->insert(
				'ignore',
				'{db_prefix}smiley_files',
				[
					'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
				],
				$inserts,
				['id_smiley', 'smiley_set'],
			);

			self::resetCache();

			// No errors? Out of here!
			Utils::redirectexit('action=admin;area=smileys;sa=editsmileys');
		}

		Utils::$context['selected_set'] = Config::$modSettings['smiley_sets_default'];

		// Get all possible filenames for the smileys.
		Utils::$context['filenames'] = [];

		if (self::$smileys_dir_found) {
			foreach (self::$smiley_sets as $smiley_set) {
				if (!file_exists(self::$smileys_dir . '/' . $smiley_set['raw_path'])) {
					continue;
				}

				$dir = dir(self::$smileys_dir . '/' . $smiley_set['raw_path']);

				while ($entry = $dir->read()) {
					$entry_info = pathinfo($entry);

					if (empty($entry_info['filename']) || empty($entry_info['extension'])) {
						continue;
					}

					if (empty(Utils::$context['filenames'][$smiley_set['path']][self::sanitizeFileName($entry_info['filename'])]) && in_array(strtolower($entry_info['extension']), self::$allowed_extenions)) {
						Utils::$context['filenames'][$smiley_set['path']][self::sanitizeFileName($entry_info['filename'])] = [
							'id' => Utils::htmlspecialchars($entry),
							'selected' => $entry_info['filename'] == 'smiley' && $smiley_set['path'] == Utils::$context['selected_set'],
						];
					}
				}

				$dir->close();

				ksort(Utils::$context['filenames'][$smiley_set['path']]);
			}

			ksort(Utils::$context['filenames']);
		}

		// Create a new smiley from scratch.
		Utils::$context['current_smiley'] = [
			'id' => 0,
			'code' => '',
			'filename' => Utils::$context['filenames'][Utils::$context['selected_set']]['smiley']['id'],
			'description' => Lang::$txt['smileys_default_description'],
			'location' => 0,
			'is_new' => true,
		];
	}

	/**
	 * Add, remove, edit smileys.
	 */
	public function edit()
	{
		// Force the correct tab to be displayed.
		Menu::$loaded['admin']['current_subsection'] = 'editsmileys';

		// Submitting a form?
		if (isset($_POST['smiley_save']) || isset($_POST['smiley_action']) || isset($_POST['deletesmiley'])) {
			User::$me->checkSession();

			// Changing the selected smileys?
			if (isset($_POST['smiley_action']) && !empty($_POST['checked_smileys'])) {
				foreach ($_POST['checked_smileys'] as $id => $smiley_id) {
					$_POST['checked_smileys'][$id] = (int) $smiley_id;
				}

				if ($_POST['smiley_action'] == 'delete') {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}smileys
						WHERE id_smiley IN ({array_int:checked_smileys})',
						[
							'checked_smileys' => $_POST['checked_smileys'],
						],
					);

					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}smiley_files
						WHERE id_smiley IN ({array_int:checked_smileys})',
						[
							'checked_smileys' => $_POST['checked_smileys'],
						],
					);
				}
				// Changing the status of the smiley?
				else {
					// Check it's a valid type.
					$displayTypes = [
						'post' => 0,
						'hidden' => 1,
						'popup' => 2,
					];

					if (isset($displayTypes[$_POST['smiley_action']])) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}smileys
							SET hidden = {int:display_type}
							WHERE id_smiley IN ({array_int:checked_smileys})',
							[
								'checked_smileys' => $_POST['checked_smileys'],
								'display_type' => $displayTypes[$_POST['smiley_action']],
							],
						);
					}
				}
			}
			// Create/modify a smiley.
			elseif (isset($_POST['smiley'])) {
				// Is it a delete?
				if (!empty($_POST['deletesmiley']) && $_POST['smiley'] == (int) $_POST['smiley']) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}smileys
						WHERE id_smiley = {int:current_smiley}',
						[
							'current_smiley' => $_POST['smiley'],
						],
					);

					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}smiley_files
						WHERE id_smiley = {int:current_smiley}',
						[
							'current_smiley' => $_POST['smiley'],
						],
					);
				}
				// Otherwise an edit.
				else {
					foreach (['smiley_code', 'smiley_description'] as $key) {
						$_POST[$key] = Utils::normalize($_POST[$key]);
					}

					$_POST['smiley'] = (int) $_POST['smiley'];
					$_POST['smiley_code'] = Utils::htmlTrimRecursive($_POST['smiley_code']);
					$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

					// Make sure some code was entered.
					if (empty($_POST['smiley_code'])) {
						ErrorHandler::fatalLang('smiley_has_no_code', false);
					}

					// If upload a new smiley image, check that smiley set folders are writable for the sets with new images.
					$writeErrors = [];

					foreach ($_FILES['smiley_upload']['name'] as $set => $name) {
						if (!empty($name) && !is_writable(self::$smileys_dir . '/' . $set)) {
							$writeErrors[] = $set;
						}
					}

					if (!empty($writeErrors)) {
						ErrorHandler::fatalLang('smileys_upload_error_notwritable', false, [implode(', ', $writeErrors)]);
					}

					foreach (self::$smiley_sets as $set => $smiley_set) {
						if (empty($_FILES['smiley_upload']['name'][$set])) {
							continue;
						}

						foreach ($this->moveImageIntoPlace($_FILES['smiley_upload']['name'][$set], $_FILES['smiley_upload']['tmp_name'][$set], [$set]) as $dir => $destination_name) {
							// Overwrite smiley filename with uploaded filename
							$_POST['smiley_filename'][$dir] = $destination_name;
						}
					}

					// Make sure all submitted filenames are clean.
					$filenames = [];

					foreach ($_POST['smiley_filename'] as $posted_set => $posted_filename) {
						$posted_set = self::sanitizeFileName($posted_set);
						$posted_filename = self::sanitizeFileName($posted_filename);

						// Make sure the set already exists.
						if (!isset(self::$smiley_sets[$posted_set])) {
							continue;
						}

						$filenames[$posted_set] = pathinfo($posted_filename, PATHINFO_BASENAME);
					}

					// Fill in any missing sets.
					foreach (self::$smiley_sets as $set => $smiley_set) {
						// Uh-oh, something is missing.
						if (empty($filenames[$set])) {
							// Try to make it the same as the default set.
							if (!empty($filenames[Config::$modSettings['smiley_sets_default']])) {
								$filenames[$set] = $filenames[Config::$modSettings['smiley_sets_default']];
							}
							// As a last resort, just try to get whatever the first one is.
							elseif (!empty($filenames)) {
								$filenames[$set] = reset($filenames);
							}
						}
					}

					// Can't do anything without filenames for the smileys.
					if (empty($filenames)) {
						ErrorHandler::fatalLang('smiley_has_no_filename', false);
					}

					// Check whether the new code has duplicates. It should be unique.
					$request = Db::$db->query(
						'',
						'SELECT id_smiley
						FROM {db_prefix}smileys
						WHERE code = {raw:mysql_binary_type} {string:smiley_code}' . (empty($_POST['smiley']) ? '' : '
							AND id_smiley != {int:current_smiley}'),
						[
							'current_smiley' => $_POST['smiley'],
							'mysql_binary_type' => Db::$db->title == MYSQL_TITLE ? 'BINARY' : '',
							'smiley_code' => $_POST['smiley_code'],
						],
					);

					if (Db::$db->num_rows($request) > 0) {
						ErrorHandler::fatalLang('smiley_not_unique', false);
					}
					Db::$db->free_result($request);

					Db::$db->query(
						'',
						'UPDATE {db_prefix}smileys
						SET
							code = {string:smiley_code},
							description = {string:smiley_description},
							hidden = {int:smiley_location}
						WHERE id_smiley = {int:current_smiley}',
						[
							'smiley_location' => $_POST['smiley_location'],
							'current_smiley' => $_POST['smiley'],
							'smiley_code' => $_POST['smiley_code'],
							'smiley_description' => $_POST['smiley_description'],
						],
					);

					// Update filename info in the smiley_files table
					$inserts = [];

					foreach ($filenames as $set => $filename) {
						$inserts[] = [$_POST['smiley'], $set, $filename];
					}

					Db::$db->insert(
						'replace',
						'{db_prefix}smiley_files',
						[
							'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
						],
						$inserts,
						['id_smiley', 'smiley_set'],
					);
				}
			}

			self::resetCache();
		}

		// Prepare overview of all (custom) smileys.
		if ($this->subaction == 'editsmileys') {
			// Determine the language specific sort order of smiley locations.
			$smiley_locations = [
				Lang::$txt['smileys_location_form'],
				Lang::$txt['smileys_location_hidden'],
				Lang::$txt['smileys_location_popup'],
			];

			asort($smiley_locations);

			// Create a list of options for selecting smiley sets.
			$smileyset_option_list = '
				<select name="set" onchange="changeSet(this.options[this.selectedIndex].value);">';

			foreach (self::$smiley_sets as $smiley_set) {
				$smileyset_option_list .= '
					<option value="' . $smiley_set['path'] . '"' . ($smiley_set['is_default'] ? ' selected' : '') . '>' . $smiley_set['name'] . '</option>';
			}

			$smileyset_option_list .= '
				</select>';

			$listOptions = [
				'id' => 'smiley_list',
				'title' => Lang::$txt['smileys_edit'],
				'items_per_page' => 40,
				'base_href' => Config::$scripturl . '?action=admin;area=smileys;sa=editsmileys',
				'default_sort_col' => 'filename',
				'get_items' => [
					'function' => __CLASS__ . '::list_getSmileys',
				],
				'get_count' => [
					'function' => __CLASS__ . '::list_getNumSmileys',
				],
				'no_items_label' => Lang::$txt['smileys_no_entries'],
				'columns' => [
					'picture' => [
						'data' => [
							'function' => function ($rowData) {
								$return = '';

								foreach ($rowData['filename_array'] as $set => $filename) {
									$return .= ' <a href="' . Config::$scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=' . $rowData['id_smiley'] . '" class="smiley_set ' . $set . '"><img src="' . Config::$modSettings['smileys_url'] . '/' . $set . '/' . $filename . '" alt="' . $rowData['description'] . '" style="padding: 2px;" id="smiley' . $rowData['id_smiley'] . '"><input type="hidden" name="smileys[' . $rowData['id_smiley'] . '][filename]" value="' . $filename . '"></a>';
								}

								return $return;
							},
							'class' => 'centercol',
						],
					],
					'smileys_code' => [
						'header' => [
							'value' => Lang::$txt['smileys_code'],
						],
						'data' => [
							'db_htmlsafe' => 'code',
						],
						'sort' => [
							'default' => 'code',
							'reverse' => 'code DESC',
						],
					],
					'filename' => [
						'header' => [
							'value' => Lang::$txt['smileys_filename'],
						],
						'data' => [
							'function' => function ($rowData) {
								$return = '<span style="display:none">' . $rowData['filename'] . '</span>';

								foreach ($rowData['filename_array'] as $set => $filename) {
									$return .= ' <span class="smiley_set ' . $set . '">' . $filename . '</span>';
								}

								return $return;
							},
						],
						'sort' => [
							'default' => 'filename',
							'reverse' => 'filename DESC',
						],
					],
					'location' => [
						'header' => [
							'value' => Lang::$txt['smileys_location'],
						],
						'data' => [
							'function' => function ($rowData) {
								if (empty($rowData['hidden'])) {
									return Lang::$txt['smileys_location_form'];
								}

								if ($rowData['hidden'] == 1) {
									return Lang::$txt['smileys_location_hidden'];
								}

								return Lang::$txt['smileys_location_popup'];
							},
						],
						'sort' => [
							'default' => Db::$db->custom_order('hidden', array_keys($smiley_locations)),
							'reverse' => Db::$db->custom_order('hidden', array_keys($smiley_locations), true),
						],
					],
					'description' => [
						'header' => [
							'value' => Lang::$txt['smileys_description'],
						],
						'data' => [
							'function' => function ($rowData) {
								if (!self::$smileys_dir_found) {
									return Utils::htmlspecialchars($rowData['description']);
								}

								// Check if there are smileys missing in some sets.
								$missing_sets = [];

								foreach (self::$smiley_sets as $smiley_set) {
									if (
										empty($rowData['filename_array'][$smiley_set['path']])
										|| !file_exists(
											sprintf(
												'%1$s/%2$s/%3$s',
												self::$smileys_dir,
												$smiley_set['path'],
												$rowData['filename_array'][$smiley_set['path']],
											),
										)
									) {
										$missing_sets[] = $smiley_set['path'];
									}
								}

								$description = Utils::htmlspecialchars($rowData['description']);

								if (!empty($missing_sets)) {
									$description .= sprintf(
										'<br><span class="smalltext"><strong>%1$s:</strong> %2$s</span>',
										Lang::$txt['smileys_not_found_in_set'],
										implode(', ', $missing_sets),
									);
								}

								return $description;
							},
						],
						'sort' => [
							'default' => 'description',
							'reverse' => 'description DESC',
						],
					],
					'modify' => [
						'header' => [
							'value' => Lang::$txt['smileys_modify'],
							'class' => 'centercol',
						],
						'data' => [
							'sprintf' => [
								'format' => '<a href="' . Config::$scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d">' . Lang::$txt['smileys_modify'] . '</a>',
								'params' => [
									'id_smiley' => false,
								],
							],
							'class' => 'centercol',
						],
					],
					'check' => [
						'header' => [
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
							'class' => 'centercol',
						],
						'data' => [
							'sprintf' => [
								'format' => '<input type="checkbox" name="checked_smileys[]" value="%1$d">',
								'params' => [
									'id_smiley' => false,
								],
							],
							'class' => 'centercol',
						],
					],
				],
				'form' => [
					'href' => Config::$scripturl . '?action=admin;area=smileys;sa=editsmileys',
					'name' => 'smileyForm',
				],
				'additional_rows' => [
					[
						'position' => 'above_column_headers',
						'value' => $smileyset_option_list,
						'class' => 'righttext',
					],
					[
						'position' => 'below_table_data',
						'value' => '
							<select name="smiley_action" onchange="makeChanges(this.value);">
								<option value="-1">' . Lang::$txt['smileys_with_selected'] . ':</option>
								<option value="-1" disabled>--------------</option>
								<option value="hidden">' . Lang::$txt['smileys_make_hidden'] . '</option>
								<option value="post">' . Lang::$txt['smileys_show_on_post'] . '</option>
								<option value="popup">' . Lang::$txt['smileys_show_on_popup'] . '</option>
								<option value="delete">' . Lang::$txt['smileys_remove'] . '</option>
							</select>
							<noscript>
								<input type="submit" name="perform_action" value="' . Lang::$txt['go'] . '" class="button">
							</noscript>',
						'class' => 'righttext',
					],
				],
				'javascript' => '
					function makeChanges(action)
					{
						if (action == \'-1\')
							return false;
						else if (action == \'delete\')
						{
							if (confirm(\'' . Lang::$txt['smileys_confirm'] . '\'))
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
			];

			new ItemList($listOptions);

			// The list is the only thing to show, so make it the main template.
			Utils::$context['default_list'] = 'smiley_list';
			Utils::$context['sub_template'] = 'show_list';

			addInlineJavaScript("\n\t" . 'changeSet("' . Config::$modSettings['smiley_sets_default'] . '");', true);
		}
		// Modifying smileys.
		elseif ($this->subaction == 'modifysmiley') {
			Utils::$context['selected_set'] = Config::$modSettings['smiley_sets_default'];

			$request = Db::$db->query(
				'',
				'SELECT s.id_smiley AS id, s.code, f.filename, f.smiley_set, s.description, s.hidden AS location
				FROM {db_prefix}smileys AS s
					LEFT JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
				WHERE s.id_smiley = {int:current_smiley}',
				[
					'current_smiley' => (int) $_REQUEST['smiley'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// The empty() bit is for just in case the default set is missing this smiley
				if ($row['smiley_set'] == Utils::$context['selected_set'] || empty(Utils::$context['current_smiley'])) {
					Utils::$context['current_smiley'] = $row;
				}

				$filenames[$row['smiley_set']] = $row['filename'];
			}
			Db::$db->free_result($request);

			if (empty(Utils::$context['current_smiley'])) {
				ErrorHandler::fatalLang('smiley_not_found', false);
			}

			Utils::$context['current_smiley']['code'] = Utils::htmlspecialchars(Utils::$context['current_smiley']['code']);

			Utils::$context['current_smiley']['description'] = Utils::htmlspecialchars(Utils::$context['current_smiley']['description']);

			Utils::$context['current_smiley']['filename'] = Utils::htmlspecialchars(Utils::$context['current_smiley']['filename']);

			// Get all possible filenames for the smileys.
			Utils::$context['filenames'] = [];
			Utils::$context['missing_sets'] = [];

			if (self::$smileys_dir_found) {
				foreach (self::$smiley_sets as $smiley_set) {
					if (!file_exists(self::$smileys_dir . '/' . $smiley_set['raw_path'])) {
						continue;
					}

					// No file currently defined for this smiley in this set? That's no good.
					if (!isset($filenames[$smiley_set['raw_path']])) {
						Utils::$context['missing_sets'][] = $smiley_set['raw_path'];

						Utils::$context['filenames'][$smiley_set['path']][''] = [
							'id' => '',
							'selected' => true,
							'disabled' => true,
						];
					}

					$dir = dir(self::$smileys_dir . '/' . $smiley_set['raw_path']);

					while ($entry = $dir->read()) {
						if (
							empty(Utils::$context['filenames'][$smiley_set['path']][$entry])
							&& in_array(pathinfo($entry, PATHINFO_EXTENSION), self::$allowed_extenions)
						) {
							Utils::$context['filenames'][$smiley_set['path']][$entry] = [
								'id' => Utils::htmlspecialchars($entry),
								'selected' => isset($filenames[$smiley_set['raw_path']]) && strtolower($entry) == strtolower($filenames[$smiley_set['raw_path']]),
								'disabled' => false,
							];
						}
					}

					$dir->close();

					ksort(Utils::$context['filenames'][$smiley_set['path']]);
				}

				ksort(Utils::$context['filenames']);
			}
		}
	}

	/**
	 * Allows to edit smileys order.
	 */
	public function setOrder()
	{
		// Move smileys to another position.
		if (isset($_REQUEST['reorder'])) {
			User::$me->checkSession('get');

			$_GET['location'] = empty($_GET['location']) || $_GET['location'] != 'popup' ? 0 : 2;
			$_GET['source'] = empty($_GET['source']) ? 0 : (int) $_GET['source'];

			if (empty($_GET['source'])) {
				ErrorHandler::fatalLang('smiley_not_found', false);
			}

			if (!empty($_GET['after'])) {
				$_GET['after'] = (int) $_GET['after'];

				$request = Db::$db->query(
					'',
					'SELECT smiley_row, smiley_order, hidden
					FROM {db_prefix}smileys
					WHERE hidden = {int:location}
						AND id_smiley = {int:after_smiley}',
					[
						'location' => $_GET['location'],
						'after_smiley' => $_GET['after'],
					],
				);

				if (Db::$db->num_rows($request) != 1) {
					ErrorHandler::fatalLang('smiley_not_found', false);
				}
				list($smiley_row, $smiley_order, $smileyLocation) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			} else {
				$smiley_row = (int) $_GET['row'];
				$smiley_order = -1;
				$smileyLocation = (int) $_GET['location'];
			}

			Db::$db->query(
				'',
				'UPDATE {db_prefix}smileys
				SET smiley_order = smiley_order + 1
				WHERE hidden = {int:new_location}
					AND smiley_row = {int:smiley_row}
					AND smiley_order > {int:smiley_order}',
				[
					'new_location' => $_GET['location'],
					'smiley_row' => $smiley_row,
					'smiley_order' => $smiley_order,
				],
			);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}smileys
				SET
					smiley_order = {int:smiley_order} + 1,
					smiley_row = {int:smiley_row},
					hidden = {int:new_location}
				WHERE id_smiley = {int:current_smiley}',
				[
					'smiley_order' => $smiley_order,
					'smiley_row' => $smiley_row,
					'new_location' => $smileyLocation,
					'current_smiley' => $_GET['source'],
				],
			);

			self::resetCache();
		}

		$request = Db::$db->query(
			'',
			'SELECT s.id_smiley, s.code, f.filename, s.description, s.smiley_row, s.smiley_order, s.hidden
			FROM {db_prefix}smileys AS s
				LEFT JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley AND f.smiley_set = {string:smiley_set})
			WHERE s.hidden != {int:popup}
			ORDER BY s.smiley_order, s.smiley_row',
			[
				'popup' => 1,
				'smiley_set' => Config::$modSettings['smiley_sets_default'],
			],
		);
		Utils::$context['smileys'] = [
			'postform' => [
				'rows' => [],
			],
			'popup' => [
				'rows' => [],
			],
		];

		while ($row = Db::$db->fetch_assoc($request)) {
			$location = empty($row['hidden']) ? 'postform' : 'popup';

			Utils::$context['smileys'][$location]['rows'][$row['smiley_row']][] = [
				'id' => $row['id_smiley'],
				'code' => Utils::htmlspecialchars($row['code']),
				'filename' => Utils::htmlspecialchars($row['filename']),
				'description' => Utils::htmlspecialchars($row['description']),
				'row' => $row['smiley_row'],
				'order' => $row['smiley_order'],
				'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
			];
		}
		Db::$db->free_result($request);

		Utils::$context['move_smiley'] = empty($_REQUEST['move']) ? 0 : (int) $_REQUEST['move'];

		// Make sure all rows are sequential.
		foreach (array_keys(Utils::$context['smileys']) as $location) {
			Utils::$context['smileys'][$location] = [
				'id' => $location,
				'title' => $location == 'postform' ? Lang::$txt['smileys_location_form'] : Lang::$txt['smileys_location_popup'],
				'description' => $location == 'postform' ? Lang::$txt['smileys_location_form_description'] : Lang::$txt['smileys_location_popup_description'],
				'last_row' => count(Utils::$context['smileys'][$location]['rows']),
				'rows' => array_values(Utils::$context['smileys'][$location]['rows']),
			];
		}

		// Check & fix smileys that are not ordered properly in the database.
		foreach (array_keys(Utils::$context['smileys']) as $location) {
			foreach (Utils::$context['smileys'][$location]['rows'] as $id => $smiley_row) {
				// Fix empty rows if any.
				if ($id != $smiley_row[0]['row']) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}smileys
						SET smiley_row = {int:new_row}
						WHERE smiley_row = {int:current_row}
							AND hidden = {int:location}',
						[
							'new_row' => $id,
							'current_row' => $smiley_row[0]['row'],
							'location' => $location == 'postform' ? '0' : '2',
						],
					);

					// Only change the first row value of the first smiley (we don't need the others :P).
					Utils::$context['smileys'][$location]['rows'][$id][0]['row'] = $id;
				}

				// Make sure the smiley order is always sequential.
				foreach ($smiley_row as $order_id => $smiley) {
					if ($order_id != $smiley['order']) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}smileys
							SET smiley_order = {int:new_order}
							WHERE id_smiley = {int:current_smiley}',
							[
								'new_order' => $order_id,
								'current_smiley' => $smiley['id'],
							],
						);
					}
				}
			}
		}

		self::resetCache();
	}

	/**
	 * Install a smiley set.
	 */
	public function install()
	{
		User::$me->isAllowedTo('manage_smileys');
		User::$me->checkSession('request');

		// One of these two may be necessary
		Lang::load('Errors');
		Lang::load('Packages');

		// Installing unless proven otherwise
		$testing = false;

		if (isset($_REQUEST['set_gz'])) {
			$base_name = strtr(basename($_REQUEST['set_gz']), ':/', '-_');
			$name = Utils::htmlspecialchars(strtok(basename($_REQUEST['set_gz']), '.'));
			Utils::$context['filename'] = $base_name;

			// Check that the smiley is from simplemachines.org, for now... maybe add mirroring later.
			if (
				!preg_match('~^https://[\w_\-]+\.simplemachines\.org/~', $_REQUEST['set_gz'])
				|| strpos($_REQUEST['set_gz'], 'dlattach') !== false
			) {
				ErrorHandler::fatalLang('not_on_simplemachines', false);
			}

			$destination = Config::$packagesdir . '/' . $base_name;

			if (file_exists($destination)) {
				ErrorHandler::fatalLang('package_upload_error_exists', false);
			}

			// Let's copy it to the Packages directory
			file_put_contents($destination, WebFetchApi::fetch($_REQUEST['set_gz']));

			$testing = true;
		} elseif (isset($_REQUEST['package'])) {
			$base_name = basename($_REQUEST['package']);
			$name = Utils::htmlspecialchars(strtok(basename($_REQUEST['package']), '.'));
			Utils::$context['filename'] = $base_name;

			$destination = Config::$packagesdir . '/' . basename($_REQUEST['package']);
		}

		if (empty($destination) || !file_exists($destination)) {
			ErrorHandler::fatalLang('package_no_file', false);
		}

		// Make sure temp directory exists and is empty.
		if (file_exists(Config::$packagesdir . '/temp')) {
			SubsPackage::deltree(Config::$packagesdir . '/temp', false);
		}

		if (!SubsPackage::mktree(Config::$packagesdir . '/temp', 0755)) {
			SubsPackage::deltree(Config::$packagesdir . '/temp', false);

			if (!SubsPackage::mktree(Config::$packagesdir . '/temp', 0777)) {
				SubsPackage::deltree(Config::$packagesdir . '/temp', false);

				// @todo not sure about url in destination_url
				SubsPackage::create_chmod_control([Config::$packagesdir . '/temp/delme.tmp'], ['destination_url' => Config::$scripturl . '?action=admin;area=smileys;sa=install;set_gz=' . $_REQUEST['set_gz'], 'crash_on_error' => true]);

				SubsPackage::deltree(Config::$packagesdir . '/temp', false);

				if (!SubsPackage::mktree(Config::$packagesdir . '/temp', 0777)) {
					ErrorHandler::fatalLang('package_cant_download', false);
				}
			}
		}

		$extracted = SubsPackage::read_tgz_file($destination, Config::$packagesdir . '/temp');

		if (!$extracted) {
			ErrorHandler::fatalLang('packageget_unable', false, ['https://custom.simplemachines.org/mods/index.php?action=search;type=12;basic_search=' . $name]);
		}

		if ($extracted && !file_exists(Config::$packagesdir . '/temp/package-info.xml')) {
			foreach ($extracted as $file) {
				if (basename($file['filename']) == 'package-info.xml') {
					$base_path = dirname($file['filename']) . '/';
					break;
				}
			}
		}

		if (!isset($base_path)) {
			$base_path = '';
		}

		if (!file_exists(Config::$packagesdir . '/temp/' . $base_path . 'package-info.xml')) {
			ErrorHandler::fatalLang('package_get_error_missing_xml', false);
		}

		$smileyInfo = SubsPackage::getPackageInfo(Utils::$context['filename']);

		if (!is_array($smileyInfo)) {
			ErrorHandler::fatalLang($smileyInfo, false);
		}

		// See if it is installed?
		$request = Db::$db->query(
			'',
			'SELECT version, themes_installed, db_changes
			FROM {db_prefix}log_packages
			WHERE package_id = {string:current_package}
				AND install_state != {int:not_installed}
			ORDER BY time_installed DESC
			LIMIT 1',
			[
				'not_installed' => 0,
				'current_package' => $smileyInfo['id'],
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			ErrorHandler::fatalLang('package_installed_warning1', false);
		}

		// Everything is fine, now it's time to do something
		$actions = SubsPackage::parsePackageInfo($smileyInfo['xml'], true, 'install');

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=smileys;sa=install;package=' . $base_name;
		Utils::$context['has_failure'] = false;
		Utils::$context['actions'] = [];
		Utils::$context['ftp_needed'] = false;

		foreach ($actions as $action) {
			if ($action['type'] == 'readme' || $action['type'] == 'license') {
				$type = 'package_' . $action['type'];

				if (file_exists(Config::$packagesdir . '/temp/' . $base_path . $action['filename'])) {
					Utils::$context[$type] = Utils::htmlspecialchars(trim(file_get_contents(Config::$packagesdir . '/temp/' . $base_path . $action['filename']), "\n\r"));
				} elseif (file_exists($action['filename'])) {
					Utils::$context[$type] = Utils::htmlspecialchars(trim(file_get_contents($action['filename']), "\n\r"));
				}

				if (!empty($action['parse_bbc'])) {
					Msg::preparsecode(Utils::$context[$type]);
					Utils::$context[$type] = BBCodeParser::load()->parse(Utils::$context[$type]);
				} else {
					Utils::$context[$type] = nl2br(Utils::$context[$type]);
				}

				continue;
			}

			if ($action['type'] == 'require-dir') {
				// Do this one...
				$thisAction = [
					'type' => Lang::$txt['package_extract'] . ' ' . ($action['type'] == 'require-dir' ? Lang::$txt['package_tree'] : Lang::$txt['package_file']),
					'action' => Utils::htmlspecialchars(strtr($action['destination'], [Config::$boarddir => '.'])),
				];

				$file = Config::$packagesdir . '/temp/' . $base_path . $action['filename'];

				if (isset($action['filename']) && (!file_exists($file) || !is_writable(dirname($action['destination'])))) {
					Utils::$context['has_failure'] = true;

					$thisAction += [
						'description' => Lang::$txt['package_action_error'],
						'failed' => true,
					];
				}

				// @todo None given?
				if (empty($thisAction['description'])) {
					$thisAction['description'] = $action['description'] ?? '';
				}

				Utils::$context['actions'][] = $thisAction;
			} elseif ($action['type'] == 'credits') {
				// Time to build the billboard
				$credits_tag = [
					'url' => $action['url'],
					'license' => $action['license'],
					'copyright' => $action['copyright'],
					'title' => $action['title'],
				];
			}
		}

		if ($testing) {
			Utils::$context['sub_template'] = 'view_package';
			Utils::$context['uninstalling'] = false;
			Utils::$context['is_installed'] = false;
			Utils::$context['package_name'] = $smileyInfo['name'];

			loadTemplate('Packages');
		}
		// Do the actual install
		else {
			// @TODO Does this call have side effects? ($actions is not used)
			$actions = SubsPackage::parsePackageInfo($smileyInfo['xml'], false, 'install');

			foreach (Utils::$context['actions'] as $action) {
				Config::updateModSettings([
					'smiley_sets_known' => Config::$modSettings['smiley_sets_known'] . ',' . basename($action['action']),
					'smiley_sets_names' => Config::$modSettings['smiley_sets_names'] . "\n" . $smileyInfo['name'] . (count(Utils::$context['actions']) > 1 ? ' ' . (!empty($action['description']) ? Utils::htmlspecialchars($action['description']) : basename($action['action'])) : ''),
				]);
			}

			SubsPackage::package_flush_cache();

			// Credits tag?
			$credits_tag = (empty($credits_tag)) ? '' : Utils::jsonEncode($credits_tag);

			Db::$db->insert(
				'',
				'{db_prefix}log_packages',
				[
					'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
					'id_member_installed' => 'int', 'member_installed' => 'string', 'time_installed' => 'int',
					'install_state' => 'int', 'failed_steps' => 'string', 'themes_installed' => 'string',
					'member_removed' => 'int', 'db_changes' => 'string', 'credits' => 'string',
				],
				[
					$smileyInfo['filename'], $smileyInfo['name'], $smileyInfo['id'], $smileyInfo['version'],
					User::$me->id, User::$me->name, time(),
					1, '', '',
					0, '', $credits_tag,
				],
				['id_install'],
			);

			Logging::logAction('install_package', ['package' => Utils::htmlspecialchars($smileyInfo['name']), 'version' => Utils::htmlspecialchars($smileyInfo['version'])], 'admin');

			self::resetCache();
		}

		if (file_exists(Config::$packagesdir . '/temp')) {
			SubsPackage::deltree(Config::$packagesdir . '/temp');
		}

		if (!$testing) {
			Utils::redirectexit('action=admin;area=smileys');
		}
	}

	/**
	 * Handles editing message icons
	 */
	public function editIcon()
	{
		// Get a list of icons.
		Utils::$context['icons'] = [];
		$last_icon = 0;
		$trueOrder = 0;

		$request = Db::$db->query(
			'',
			'SELECT m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
			FROM {db_prefix}message_icons AS m
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE ({query_see_board} OR b.id_board IS NULL)
			ORDER BY m.icon_order',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['icons'][$row['id_icon']] = [
				'id' => $row['id_icon'],
				'title' => $row['title'],
				'filename' => $row['filename'],
				'image_url' => Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['filename'] . '.png') ? 'actual_images_url' : 'default_images_url'] . '/post/' . $row['filename'] . '.png',
				'board_id' => $row['id_board'],
				'board' => empty($row['board_name']) ? Lang::$txt['icons_edit_icons_all_boards'] : $row['board_name'],
				'order' => $row['icon_order'],
				'true_order' => $trueOrder++,
				'after' => $last_icon,
			];

			$last_icon = $row['id_icon'];
		}
		Db::$db->free_result($request);

		// Submitting a form?
		if (isset($_POST['icons_save']) || isset($_POST['delete'])) {
			User::$me->checkSession();

			// Deleting icons?
			if (isset($_POST['delete']) && !empty($_POST['checked_icons'])) {
				$deleteIcons = [];

				foreach ($_POST['checked_icons'] as $icon) {
					$deleteIcons[] = (int) $icon;
				}

				// Do the actual delete!
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}message_icons
					WHERE id_icon IN ({array_int:icon_list})',
					[
						'icon_list' => $deleteIcons,
					],
				);
			}
			// Editing/Adding an icon?
			elseif ($this->subaction == 'editicon' && isset($_GET['icon'])) {
				$_GET['icon'] = (int) $_GET['icon'];

				foreach (['icon_filename', 'icon_description'] as $key) {
					$_POST[$key] = Utils::normalize($_POST[$key]);
				}

				// Do some preperation with the data... like check the icon exists *somewhere*
				if (strpos($_POST['icon_filename'], '.png') !== false) {
					$_POST['icon_filename'] = substr($_POST['icon_filename'], 0, -4);
				}

				if (!file_exists(Theme::$current->settings['default_theme_dir'] . '/images/post/' . $_POST['icon_filename'] . '.png')) {
					ErrorHandler::fatalLang('icon_not_found', false);
				}

				// There is a 16 character limit on message icons...
				if (strlen($_POST['icon_filename']) > 16) {
					ErrorHandler::fatalLang('icon_name_too_long', false);
				}

				if ($_POST['icon_location'] == $_GET['icon'] && !empty($_GET['icon'])) {
					ErrorHandler::fatalLang('icon_after_itself', false);
				}

				// First do the sorting... if this is an edit reduce the order of everything after it by one ;)
				if ($_GET['icon'] != 0) {
					$oldOrder = Utils::$context['icons'][$_GET['icon']]['true_order'];

					foreach (Utils::$context['icons'] as $id => $data) {
						if ($data['true_order'] > $oldOrder) {
							Utils::$context['icons'][$id]['true_order']--;
						}
					}
				}

				// If there are no existing icons and this is a new one, set the id to 1 (mainly for non-mysql)
				if (empty($_GET['icon']) && empty(Utils::$context['icons'])) {
					$_GET['icon'] = 1;
				}

				// Get the new order.
				$newOrder = $_POST['icon_location'] == 0 ? 0 : Utils::$context['icons'][$_POST['icon_location']]['true_order'] + 1;

				// Do the same, but with the one that used to be after this icon, done to avoid conflict.
				foreach (Utils::$context['icons'] as $id => $data) {
					if ($data['true_order'] >= $newOrder) {
						Utils::$context['icons'][$id]['true_order']++;
					}
				}

				// Finally set the current icon's position!
				Utils::$context['icons'][$_GET['icon']]['true_order'] = $newOrder;

				// Simply replace the existing data for the other bits.
				Utils::$context['icons'][$_GET['icon']]['title'] = $_POST['icon_description'];
				Utils::$context['icons'][$_GET['icon']]['filename'] = $_POST['icon_filename'];
				Utils::$context['icons'][$_GET['icon']]['board_id'] = (int) $_POST['icon_board'];

				// Do a huge replace ;)
				$iconInsert = [];
				$iconInsert_new = [];

				foreach (Utils::$context['icons'] as $id => $icon) {
					if ($id != 0) {
						$iconInsert[] = [$id, $icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']];
					} else {
						$iconInsert_new[] = [$icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']];
					}
				}

				Db::$db->insert(
					'replace',
					'{db_prefix}message_icons',
					['id_icon' => 'int', 'id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'],
					$iconInsert,
					['id_icon'],
				);

				if (!empty($iconInsert_new)) {
					Db::$db->insert(
						'insert',
						'{db_prefix}message_icons',
						['id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'],
						$iconInsert_new,
						['id_icon'],
					);
				}
			}

			// Unless we're adding a new thing, we'll escape
			if (!isset($_POST['add'])) {
				Utils::redirectexit('action=admin;area=smileys;sa=editicons');
			}
		}

		Menu::$loaded['admin']['current_subsection'] = 'editicons';

		$listOptions = [
			'id' => 'message_icon_list',
			'title' => Lang::$txt['icons_edit_message_icons'],
			'base_href' => Config::$scripturl . '?action=admin;area=smileys;sa=editicons',
			'get_items' => [
				'function' => __CLASS__ . '::list_getMessageIcons',
			],
			'no_items_label' => Lang::$txt['icons_no_entries'],
			'columns' => [
				'icon' => [
					'data' => [
						'function' => function ($rowData) {
							$images_url = Theme::$current->settings[file_exists(sprintf('%1$s/images/post/%2$s.png', Theme::$current->settings['theme_dir'], $rowData['filename'])) ? 'actual_images_url' : 'default_images_url'];

							return sprintf('<img src="%1$s/post/%2$s.png" alt="%3$s">', $images_url, $rowData['filename'], Utils::htmlspecialchars($rowData['title']));
						},
						'class' => 'centercol',
					],
				],
				'filename' => [
					'header' => [
						'value' => Lang::$txt['smileys_filename'],
					],
					'data' => [
						'sprintf' => [
							'format' => '%1$s.png',
							'params' => [
								'filename' => true,
							],
						],
					],
				],
				'description' => [
					'header' => [
						'value' => Lang::$txt['smileys_description'],
					],
					'data' => [
						'db_htmlsafe' => 'title',
					],
				],
				'board' => [
					'header' => [
						'value' => Lang::$txt['icons_board'],
					],
					'data' => [
						'function' => function ($rowData) {
							return empty($rowData['board_name']) ? Lang::$txt['icons_edit_icons_all_boards'] : $rowData['board_name'];
						},
					],
				],
				'modify' => [
					'header' => [
						'value' => Lang::$txt['smileys_modify'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=smileys;sa=editicon;icon=%1$s">' . Lang::$txt['smileys_modify'] . '</a>',
							'params' => [
								'id_icon' => false,
							],
						],
						'class' => 'centercol',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="checked_icons[]" value="%1$d">',
							'params' => [
								'id_icon' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=smileys;sa=editicons',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="delete" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button"> <a class="button" href="' . Config::$scripturl . '?action=admin;area=smileys;sa=editicon">' . Lang::$txt['icons_add_new'] . '</a>',
				],
			],
		];

		new ItemList($listOptions);

		// If we're adding/editing an icon we'll need a list of boards
		if ($this->subaction == 'editicon' || isset($_POST['add'])) {
			// Force the sub_template just in case.
			Utils::$context['sub_template'] = 'editicon';

			Utils::$context['new_icon'] = !isset($_GET['icon']);

			// Get the properties of the current icon from the icon list.
			if (!Utils::$context['new_icon']) {
				Utils::$context['icon'] = Utils::$context['icons'][$_GET['icon']];
			}

			// Get a list of boards needed for assigning this icon to a specific board.
			$boardListOptions = [
				'use_permissions' => true,
				'selected_board' => Utils::$context['icon']['board_id'] ?? 0,
			];

			Utils::$context['categories'] = MessageIndex::getBoardList($boardListOptions);
		}
	}

	/**
	 * Handles modifying smileys settings.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Setup the basics of the settings template.
		Utils::$context['sub_template'] = 'show_settings';

		// Finish up the form...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=smileys;save;sa=settings';

		// Saving the settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Validate the smiley set name.
			$_POST['smiley_sets_default'] = empty(self::$smiley_sets[$_POST['smiley_sets_default']]) ? Config::$modSettings['smiley_sets_default'] : $_POST['smiley_sets_default'];

			IntegrationHook::call('integrate_save_smiley_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;

			self::resetCache();

			Utils::redirectexit('action=admin;area=smileys;sa=settings');
		}

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
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

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the news area.
	 */
	public static function getConfigVars(): array
	{
		// The directories...
		self::findSmileysDir();

		// Get the names of the smiley sets.
		self::getKnownSmileySets();

		// All the settings for the page...
		$config_vars = [
			['title', 'settings'],
			// Inline permissions.
			['permissions', 'manage_smileys'],
			'',

			// array('select', 'smiley_sets_default', self::$smiley_sets),
			['select', 'smiley_sets_default', array_map(fn ($set) => $set['raw_name'], self::$smiley_sets)],
			['check', 'smiley_sets_enable'],
			['check', 'smiley_enable', 'subtext' => Lang::$txt['smileys_enable_note']],
			['text', 'smileys_url', 40],
			['warning', !is_dir(self::$smileys_dir) ? 'setting_smileys_dir_wrong' : ''],
			['text', 'smileys_dir', 'invalid' => !self::$smileys_dir_found, 40],
			'',

			// Message icons.
			['check', 'messageIcons_enable', 'subtext' => Lang::$txt['setting_messageIcons_enable_note']],
			['check', 'messageIconChecks_enable', 'subtext' => Lang::$txt['setting_messageIconChecks_enable_note']],
		];

		IntegrationHook::call('integrate_modify_smiley_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @todo to be moved to Subs-Smileys?
	 *
	 * @param int $start The item to start with (not used here)
	 * @param int $items_per_page The number of items to show per page (not used here)
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of info about the smiley sets
	 */
	public static function list_getSmileySets($start, $items_per_page, $sort)
	{
		if (empty(self::$smiley_sets)) {
			self::getKnownSmileySets();
		}

		$cols = [
			'id' => [],
			'is_default' => [],
			'path' => [],
			'name' => [],
		];

		foreach (self::$smiley_sets as $set => $smiley_set) {
			$cols['id'][] = $smiley_set['id'];
			$cols['is_default'][] = $smiley_set['is_default'];
			$cols['path'][] = $smiley_set['raw_path'];
			$cols['name'][] = $smiley_set['raw_name'];
		}

		$sort_flag = strpos($sort, 'DESC') === false ? SORT_ASC : SORT_DESC;

		if (substr($sort, 0, 4) === 'name') {
			array_multisort($cols['name'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['is_default'], $cols['id']);
		} elseif (substr($sort, 0, 4) === 'path') {
			array_multisort($cols['path'], $sort_flag, SORT_REGULAR, $cols['name'], $cols['is_default'], $cols['id']);
		} else {
			array_multisort($cols['is_default'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['name'], $cols['id']);
		}

		$smiley_sets = [];

		foreach ($cols['id'] as $i => $id) {
			$smiley_sets[] = [
				'id' => $id,
				'path' => $cols['path'][$i],
				'name' => $cols['name'][$i],
				'is_default' => $cols['is_default'][$i],
			];
		}

		return $smiley_sets;
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @todo to be moved to Subs-Smileys?
	 * @return int The total number of known smiley sets
	 */
	public static function list_getNumSmileySets()
	{
		return count(explode(',', Config::$modSettings['smiley_sets_known']));
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @param int $start The item to start with (not used here)
	 * @param int $items_per_page The number of items to show per page (not used here)
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of info about the smileys
	 */
	public static function list_getSmileys($start, $items_per_page, $sort)
	{
		$smileys = [];

		$request = Db::$db->query(
			'',
			'SELECT s.id_smiley, s.code, f.filename, f.smiley_set, s.description, s.smiley_row, s.smiley_order, s.hidden
			FROM {db_prefix}smileys AS s
				LEFT JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
			ORDER BY {raw:sort}',
			[
				'sort' => $sort,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($smileys[$row['id_smiley']])) {
				$smileys[$row['id_smiley']] = $row;

				unset($smileys[$row['id_smiley']]['smiley_set']);

				$smileys[$row['id_smiley']]['filename_array'] = [$row['smiley_set'] => $row['filename']];
			} else {
				$smileys[$row['id_smiley']]['filename_array'][$row['smiley_set']] = $row['filename'];
			}

			// Use the filename for the default set as the primary filename for this smiley
			if (isset($smileys[$row['id_smiley']]['filename_array'][Config::$modSettings['smiley_sets_default']])) {
				$smileys[$row['id_smiley']]['filename'] = $smileys[$row['id_smiley']]['filename_array'][Config::$modSettings['smiley_sets_default']];
			} else {
				$smileys[$row['id_smiley']]['filename'] = reset($smileys[$row['id_smiley']]['filename_array']);
			}
		}
		Db::$db->free_result($request);

		return $smileys;
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @return int The number of smileys
	 */
	public static function list_getNumSmileys()
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}smileys',
			[],
		);
		list($numSmileys) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numSmileys;
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @param int $start The item to start with (not used here)
	 * @param int $items_per_page The number of items to display per page (not used here)
	 * @param string $sort A string indicating how to sort the items (not used here)
	 * @return array An array of information about message icons
	 */
	public static function list_getMessageIcons($start, $items_per_page, $sort)
	{
		$message_icons = [];

		$request = Db::$db->query(
			'',
			'SELECT m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
			FROM {db_prefix}message_icons AS m
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE ({query_see_board} OR b.id_board IS NULL)
			ORDER BY m.icon_order',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$message_icons[] = $row;
		}
		Db::$db->free_result($request);

		return $message_icons;
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public function editSmileySettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = '';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the addsmiley sub-action.
	 */
	public static function addSmiley(): void
	{
		self::load();
		self::$obj->subaction = 'addsmiley';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the editsmileys sub-action.
	 */
	public static function editSmileys(): void
	{
		self::load();
		self::$obj->subaction = 'editsmileys';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the setorder sub-action.
	 */
	public static function editSmileyOrder(): void
	{
		self::load();
		self::$obj->subaction = 'setorder';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the install sub-action.
	 */
	public static function installSmileySet(): void
	{
		self::load();
		self::$obj->subaction = 'install';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the editsets sub-action.
	 */
	public static function editMessageIcons(): void
	{
		self::load();
		self::$obj->subaction = 'editsets';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		User::$me->isAllowedTo('manage_smileys');

		Lang::load('ManageSmileys');
		loadTemplate('ManageSmileys');

		// If customized smileys is disabled don't show the setting page
		if (empty(Config::$modSettings['smiley_enable'])) {
			unset(self::$subactions['addsmiley'], self::$subactions['editsmileys'], self::$subactions['setorder'], self::$subactions['modifysmiley']);
		}

		if (empty(Config::$modSettings['messageIcons_enable'])) {
			unset(self::$subactions['editicon'], self::$subactions['editicons']);
		}

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['smileys_manage'],
			'help' => 'smileys',
			'description' => Lang::$txt['smiley_settings_explain'],
			'tabs' => [
				'editsets' => [
					'description' => Lang::$txt['smiley_editsets_explain'],
				],
				'addsmiley' => [
					'description' => Lang::$txt['smiley_addsmiley_explain'],
				],
				'editsmileys' => [
					'description' => Lang::$txt['smiley_editsmileys_explain'],
				],
				'setorder' => [
					'description' => Lang::$txt['smiley_setorder_explain'],
				],
				'editicons' => [
					'description' => Lang::$txt['icons_edit_icons_explain'],
				],
				'settings' => [
					'description' => Lang::$txt['smiley_settings_explain'],
				],
			],
		];

		// Some settings may not be enabled, disallow these from the tabs as appropriate.
		if (empty(Config::$modSettings['messageIcons_enable'])) {
			Menu::$loaded['admin']->tab_data['tabs']['editicons']['disabled'] = true;
		}

		if (empty(Config::$modSettings['smiley_enable'])) {
			Menu::$loaded['admin']->tab_data['tabs']['addsmiley']['disabled'] = true;
			Menu::$loaded['admin']->tab_data['tabs']['editsmileys']['disabled'] = true;
			Menu::$loaded['admin']->tab_data['tabs']['setorder']['disabled'] = true;
		}

		IntegrationHook::call('integrate_manage_smileys', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		Utils::$context['sub_action'] = &$this->subaction;

		Utils::$context['page_title'] = Lang::$txt['smileys_manage'];
		Utils::$context['sub_template'] = $this->subaction;

		self::findSmileysDir();
		self::getKnownSmileySets();

		Utils::$context['smiley_sets'] = &self::$smiley_sets;
	}

	/**
	 * Imports new smileys from an existing directory into the database.
	 *
	 * @param string $smileyPath The path to the directory to import smileys from
	 * @param bool $create Whether or not to make brand new smileys for files that don't match any existing smileys
	 */
	protected function import($smileyPath, $create = false)
	{
		if (!self::$smileys_dir_found || !is_dir(self::$smileys_dir . '/' . $smileyPath)) {
			ErrorHandler::fatalLang('smiley_set_unable_to_import', false);
		}

		$known_sets = explode(',', Config::$modSettings['smiley_sets_known']);
		sort($known_sets);

		// Get the smileys in the folder
		$smileys = [];
		$dir = dir(self::$smileys_dir . '/' . $smileyPath);

		while ($entry = $dir->read()) {
			$pathinfo = pathinfo($entry);

			if (empty($pathinfo['filename']) || empty($pathinfo['extension'])) {
				continue;
			}

			if (in_array($pathinfo['extension'], self::$allowed_extenions) && $pathinfo['filename'] != 'blank' && strlen($pathinfo['basename']) <= 48) {
				$smiley_files[strtolower($pathinfo['basename'])] = $pathinfo['basename'];
			}
		}

		$dir->close();

		// Get the smileys that are already in the database.
		$existing_smileys = [];

		$request = Db::$db->query(
			'',
			'SELECT id_smiley, smiley_set, filename
			FROM {db_prefix}smiley_files',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$existing_smileys[pathinfo($row['filename'], PATHINFO_FILENAME)][$row['id_smiley']][] = $row['smiley_set'];
		}
		Db::$db->free_result($request);

		// Filter $smiley_files down to just the ones not already in the database.
		$to_unset = [];
		$to_fix = [];

		foreach ($smiley_files as $key => $smiley_file) {
			$smiley_name = pathinfo($smiley_file, PATHINFO_FILENAME);

			// A brand new one
			if (empty($existing_smileys[$smiley_name])) {
				continue;
			}

			// A file with this name is already being used for at least one smiley, so we have more work to do...
			foreach ($existing_smileys[$smiley_name] as $existing_id => $existing_sets) {
				$to_unset[$key][$existing_id] = false;

				sort($existing_sets);

				// Already done
				if ($existing_sets === $known_sets) {
					$to_unset[$key][$existing_id] = true;
				}
				// Used in some sets but not others
				else {
					// Do the other sets have some other file already defined?
					foreach ($existing_smileys as $file => $info) {
						foreach ($info as $info_id => $info_sets) {
							if ($existing_id == $info_id) {
								$existing_sets = array_unique(array_merge($existing_sets, $info_sets));
							}
						}
					}

					sort($existing_sets);

					// If every set already has a file for this smiley, we can skip it
					if ($known_sets == $existing_sets) {
						$to_unset[$key][$existing_id] = true;
					}
					// Need to add the file for these sets
					else {
						$to_fix[$key][$existing_id] = array_diff($known_sets, $existing_sets);
					}
				}
			}
		}

		// Fix any sets with missing files
		// This part handles files for pre-existing smileys in a newly created smiley set
		$inserts = [];

		foreach ($to_fix as $key => $ids) {
			foreach ($ids as $id_smiley => $sets_missing) {
				// Find the file we need to copy to the other sets
				if (file_exists(self::$smileys_dir . '/' . $smileyPath . '/' . $smiley_files[$key])) {
					$p = $smileyPath;
				} else {
					foreach (array_diff($known_sets, $sets_missing) as $set) {
						if (file_exists(self::$smileys_dir . '/' . $set . '/' . $smiley_files[$key])) {
							$p = $set;
							break;
						}
					}
				}

				foreach ($sets_missing as $set) {
					if ($set !== $p) {
						// Copy the file into the set's folder
						copy(self::$smileys_dir . '/' . $p . '/' . $smiley_files[$key], self::$smileys_dir . '/' . $set . '/' . $smiley_files[$key]);

						Utils::makeWritable(self::$smileys_dir . '/' . $set . '/' . $smiley_files[$key], 0644);
					}

					// Double-check that everything went as expected
					if (!file_exists(self::$smileys_dir . '/' . $set . '/' . $smiley_files[$key])) {
						continue;
					}

					// Update the database
					$inserts[] = [$id_smiley, $set, $smiley_files[$key]];

					// This isn't a new smiley
					$to_unset[$key][$id_smiley] = true;
				}
			}
		}

		// Remove anything that isn't actually new from our list of files
		foreach ($to_unset as $key => $ids) {
			if (array_reduce($ids, function ($carry, $item) { return $carry * $item; }, true) == true) {
				unset($smiley_files[$key]);
			}
		}

		// We only create brand new smileys if asked.
		if (empty($create)) {
			$smiley_files = [];
		}

		// New smileys go at the end of the list
		$request = Db::$db->query(
			'',
			'SELECT MAX(smiley_order)
			FROM {db_prefix}smileys
			WHERE hidden = {int:postform}
				AND smiley_row = {int:first_row}',
			[
				'postform' => 0,
				'first_row' => 0,
			],
		);
		list($smiley_order) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// This part handles brand new smileys that don't exist in any set
		$new_smileys = [];

		foreach ($smiley_files as $key => $smiley_file) {
			// Ensure every set has a file to use for the new smiley
			foreach ($known_sets as $set) {
				unset($basename);

				if ($smileyPath != $set) {
					// Check whether any similarly named files exist in the other set's directory
					$similar_files = glob(self::$smileys_dir . '/' . $set . '/' . pathinfo($smiley_file, PATHINFO_FILENAME) . '.{' . implode(',', self::$allowed_extenions) . '}', GLOB_BRACE);

					// If there's a similarly named file already there, use it
					if (!empty($similar_files)) {
						// Prefer an exact match if there is one
						foreach ($similar_files as $similar_file) {
							if (basename($similar_file) == $smiley_file) {
								$basename = $smiley_file;
							}
						}

						// Same name, different extension
						if (empty($basename)) {
							$basename = basename(reset($similar_files));
						}
					}
					// Otherwise, copy the image to the other set's directory
					else {
						copy(self::$smileys_dir . '/' . $smileyPath . '/' . $smiley_file, self::$smileys_dir . '/' . $set . '/' . $smiley_file);

						Utils::makeWritable(self::$smileys_dir . '/' . $set . '/' . $smiley_file, 0644);

						$basename = $smiley_file;
					}

					// Double-check that everything went as expected
					if (empty($basename) || !file_exists(self::$smileys_dir . '/' . $set . '/' . $basename)) {
						continue;
					}
				} else {
					$basename = $smiley_file;
				}

				$new_smileys[$key]['files'][$set] = $basename;
			}

			$new_smileys[$key]['info'] = [':' . pathinfo($smiley_file, PATHINFO_FILENAME) . ':', pathinfo($smiley_file, PATHINFO_FILENAME), 0, ++$smiley_order];
		}

		// Add the info for any new smileys to the database
		foreach ($new_smileys as $new_smiley) {
			$new_id_smiley = Db::$db->insert(
				'',
				'{db_prefix}smileys',
				[
					'code' => 'string-30', 'description' => 'string-80', 'smiley_row' => 'int', 'smiley_order' => 'int',
				],
				$new_smiley['info'],
				['id_smiley'],
				1,
			);

			// We'll also need to add filename info to the smiley_files table
			foreach ($new_smiley['files'] as $set => $filename) {
				$inserts[] = [$new_id_smiley, $set, $filename];
			}
		}

		// Finally, update the smiley_files table with all our new files
		if (!empty($inserts)) {
			Db::$db->insert(
				'replace',
				'{db_prefix}smiley_files',
				[
					'id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48',
				],
				$inserts,
				['id_smiley', 'smiley_set'],
			);

			self::resetCache();
		}
	}

	/**
	 *
	 */
	protected function createDir($dir, $name)
	{
		// Can't do this if we couldn't find the base smileys directory.
		if (!self::$smileys_dir_found) {
			ErrorHandler::fatalLang('smiley_set_dir_not_found', false, Utils::htmlspecialchars($name));
		}

		$path = realpath(self::$smileys_dir . DIRECTORY_SEPARATOR . $dir);

		// Must be an immediate child directory of the base smileys directory.
		if (dirname($path) !== realpath(self::$smileys_dir)) {
			ErrorHandler::fatalLang('smiley_set_dir_not_found', false, Utils::htmlspecialchars($name));
		}

		// Must not already exist.
		if (file_exists($path)) {
			if (isset(self::$smiley_sets[$dir])) {
				ErrorHandler::fatalLang('smiley_set_already_exists', false);
			}

			ErrorHandler::fatalLang('smiley_set_dir_not_found', false, Utils::htmlspecialchars($name));
		}

		// Let's try to create it. Make some noise if we fail.
		if (@mkdir($path, 0755) === false) {
			ErrorHandler::fatalLang('smiley_set_dir_not_found', false, Utils::htmlspecialchars($name));
		}
	}

	/**
	 *
	 */
	protected function validateImage(string $name, string $tmp_name): bool
	{
		return in_array(pathinfo($name, PATHINFO_EXTENSION), self::$allowed_extenions) && Utils::checkMimeType($tmp_name, Utils::buildRegex(self::$allowed_mime_types, '~'), true);
	}

	/**
	 *
	 */
	protected function moveImageIntoPlace(string $name, string $tmp_name, array $destination_dirs): array
	{
		$filename_array = [];

		if ($name == '') {
			ErrorHandler::fatalLang('smileys_upload_error_blank', false);
		}

		if (!is_uploaded_file($tmp_name) || (ini_get('open_basedir') == '' && !file_exists($tmp_name))) {
			ErrorHandler::fatalLang('smileys_upload_error', false);
		}

		// Sorry, no spaces, dots, or anything else but letters allowed.
		$name = self::sanitizeFileName($name);

		// We only allow image files - it's THAT simple - no messing around here...
		if (!$this->validateImage($name, $tmp_name)) {
			ErrorHandler::fatalLang('smileys_upload_error_types', false, [implode(', ', self::$allowed_extenions)]);
		}

		// We only need the filename...
		$destination_name = basename($name);

		// Make sure they aren't trying to upload a nasty file - for their own good here!
		if (in_array(strtolower($destination_name), self::$illegal_files)) {
			ErrorHandler::fatalLang('smileys_upload_error_illegal', false);
		}

		$source_file = $tmp_name;
		$func = 'move_uploaded_file';

		foreach ($destination_dirs as $dir) {
			$destination = self::$smileys_dir . '/' . $dir . '/' . $destination_name;

			if (!file_exists($destination)) {
				@$func($source_file, $destination);

				// Double-check
				if (!file_exists($destination)) {
					ErrorHandler::fatalLang('smiley_not_found', false);
				}

				Utils::makeWritable($destination, 0644);

				$source_file = $destination;

				$func = 'copy';

				// Make sure it's saved correctly!
				$filename_array[$dir] = $destination_name;
			}
		}

		return $filename_array;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Sets self::$smileys_dir and self::$smileys_dir_found.
	 */
	protected static function findSmileysDir()
	{
		self::$smileys_dir = empty(Config::$modSettings['smileys_dir']) ? Config::$boarddir . '/Smileys' : Config::$modSettings['smileys_dir'];

		self::$smileys_dir_found = is_dir(self::$smileys_dir);
	}

	/**
	 * Populates self::$smiley_sets with info about known smiley sets.
	 */
	protected static function getKnownSmileySets()
	{
		$smiley_sets = explode(',', Config::$modSettings['smiley_sets_known']);
		$set_names = explode("\n", Config::$modSettings['smiley_sets_names']);

		foreach ($smiley_sets as $i => $set) {
			self::$smiley_sets[$set] = [
				'id' => $i,
				'raw_path' => $set,
				'raw_name' => $set_names[$i],
				'path' => Utils::htmlspecialchars($set),
				'name' => Utils::htmlspecialchars($set_names[$i]),
				'is_default' => $set == Config::$modSettings['smiley_sets_default'],
				'is_new' => false,
			];
		}
	}

	/**
	 *
	 */
	protected static function saveSets()
	{
		$sets_known = [];
		$sets_names = [];

		foreach (self::$smiley_sets as $id => $set) {
			$sets_known[$id] = $set['raw_path'] ?? Utils::htmlspecialcharsDecode($set['path']) ?? $id;
			$sets_names[$id] = $set['raw_name'] ?? Utils::htmlspecialcharsDecode($set['name']) ?? $id;

			if (!empty($set['is_default'])) {
				$default_set = $sets_known[$id];
			}
		}

		if (!isset($default_set)) {
			$default_set = isset(Config::$modSettings['smiley_sets_default'], self::$smiley_sets[Config::$modSettings['smiley_sets_default']]) ? Config::$modSettings['smiley_sets_default'] : reset($sets_known);
		}

		Config::updateModSettings([
			'smiley_sets_known' => implode(',', $sets_known),
			'smiley_sets_names' => implode("\n", $sets_names),
			'smiley_sets_default' => $default_set,
		]);
	}

	/**
	 *
	 */
	protected static function sanitizeString(string $string): string
	{
		$string = Utils::sanitizeChars(Utils::normalize($string));

		// Get rid of unnecessary whitespace.
		$string = Utils::htmlTrim(Utils::normalizeSpaces($string, true, true, ['no_breaks' => true, 'collapse_hspace' => true, 'replace_tabs' => true]));

		return $string;
	}

	/**
	 *
	 */
	protected static function sanitizeFileName(string $string): string
	{
		$string = Utils::normalize(self::sanitizeString($string), 'kc_casefold');

		$pathinfo = pathinfo($string);

		// Get rid of all whitespace and dots in the filename component.
		$pathinfo['filename'] = preg_replace('/[\s._]+/', '_', Utils::htmlTrim($pathinfo['filename']));

		// Just in case there's something unexpected in the extension...
		if (!empty($pathinfo['extension'])) {
			$pathinfo['extension'] = preg_replace('/[\s_]+/', '_', Utils::htmlTrim($pathinfo['extension']));
		}

		return $pathinfo['filename'] . (!empty($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '');
	}

	/**
	 *
	 */
	protected static function resetCache(): void
	{
		foreach (self::$smiley_sets as $smiley_set) {
			CacheApi::put('parsing_smileys_' . $smiley_set['raw_path'], null, 480);
			CacheApi::put('posting_smileys_' . $smiley_set['raw_path'], null, 480);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Smileys::exportStatic')) {
	Smileys::exportStatic();
}

?>