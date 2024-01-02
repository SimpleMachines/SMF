<?php

/**
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
use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\PackageManager\{SubsPackage, XmlArray};
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class concerns itself almost completely with theme administration.
 *
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
 *
 * Creating and distributing theme packages:
 * There isn't that much required to package and distribute your own themes...
 * just do the following:
 * - create a theme_info.xml file, with the root element theme-info.
 * - its name should go in a name element, just like description.
 * - your name should go in author. (email in the email attribute.)
 * - any support website for the theme should be in website.
 * - layers and templates (non-default) should go in those elements ;).
 * - if the images dir isn't images, specify in the images element.
 * - any extra rows for themes should go in extra, serialized. (as in array(variable => value).)
 * - tar and gzip the directory - and you're done!
 * - please include any special license in a license.txt file.
 */
class Themes implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ThemesMain',
			'themeAdmin' => 'ThemeAdmin',
			'themeList' => 'ThemeList',
			'setThemeOptions' => 'SetThemeOptions',
			'removeTheme' => 'RemoveTheme',
			'enableTheme' => 'EnableTheme',
			'themeInstall' => 'ThemeInstall',
			'editTheme' => 'EditTheme',
			'copyTemplate' => 'CopyTemplate',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'admin';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'admin' => 'admin',
		'list' => 'list',
		'reset' => 'setOptions',
		'options' => 'setOptions',
		'remove' => 'remove',
		'enable' => 'enable',
		'install' => 'install',
		'edit' => 'edit',
		'copy' => 'copy',
	];

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
		// Whatever they decide to do, clean the minify cache.
		Theme::deleteAllMinified();

		if (isset(self::$subactions[$this->subaction])) {
			$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);
		} else {
			$call = Utils::getCallable($this->subaction);
		}

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * This method allows administration of themes and their settings,
	 * as well as global theme settings.
	 *  - sets the settings theme_allow, theme_guests, and knownThemes.
	 *  - requires the admin_forum permission.
	 *  - accessed with ?action=admin;area=theme;sa=admin.
	 *
	 * Uses Themes template
	 * Uses Admin language file
	 */
	public function admin()
	{
		// Are handling any settings?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-tm');

			if (isset($_POST['options']['known_themes'])) {
				foreach ($_POST['options']['known_themes'] as $key => $id) {
					$_POST['options']['known_themes'][$key] = (int) $id;
				}
			} else {
				ErrorHandler::fatalLang('themes_none_selectable', false);
			}

			if (!in_array($_POST['options']['theme_guests'], $_POST['options']['known_themes'])) {
				ErrorHandler::fatalLang('themes_default_selectable', false);
			}

			// Commit the new settings.
			Config::updateModSettings([
				'theme_allow' => $_POST['options']['theme_allow'],
				'theme_guests' => $_POST['options']['theme_guests'],
				'knownThemes' => implode(',', $_POST['options']['known_themes']),
			]);

			if ((int) $_POST['theme_reset'] == 0 || in_array($_POST['theme_reset'], $_POST['options']['known_themes'])) {
				User::updateMemberData(null, ['id_theme' => (int) $_POST['theme_reset']]);
			}

			Utils::redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=admin');
		}

		Theme::loadTemplate('Themes');

		// List all enabled themes.
		$this->getAllThemes(true);

		// Can we create a new theme?
		Utils::$context['can_create_new'] = is_writable(Config::$boarddir . '/Themes');
		Utils::$context['new_theme_dir'] = substr(realpath(Config::$boarddir . '/Themes/default'), 0, -7);

		// Look for a non existent theme directory. (ie theme87.)
		$theme_dir = Config::$boarddir . '/Themes/theme';
		$i = 1;

		while (file_exists($theme_dir . $i)) {
			$i++;
		}

		Utils::$context['new_theme_name'] = 'theme' . $i;

		// A bunch of tokens for a bunch of forms.
		SecurityToken::create('admin-tm');
		SecurityToken::create('admin-t-file');
		SecurityToken::create('admin-t-copy');
		SecurityToken::create('admin-t-dir');
	}

	/**
	 * This function lists the available themes and provides an interface to reset
	 * the paths of all the installed themes.
	 */
	public function list()
	{
		if (isset($_REQUEST['th'])) {
			$this->setSettings();

			return;
		}

		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-tl');

			$this->getInstalledThemes();

			$setValues = [];

			foreach (Utils::$context['themes'] as $id => $theme) {
				if (file_exists($_POST['reset_dir'] . '/' . basename($theme['theme_dir']))) {
					$setValues[] = [$id, 0, 'theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['theme_dir']))];
					$setValues[] = [$id, 0, 'theme_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir'])];
					$setValues[] = [$id, 0, 'images_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir']) . '/' . basename($theme['images_url'])];
				}

				if (isset($theme['base_theme_dir']) && file_exists($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir']))) {
					$setValues[] = [$id, 0, 'base_theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir']))];
					$setValues[] = [$id, 0, 'base_theme_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir'])];
					$setValues[] = [$id, 0, 'base_images_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir']) . '/' . basename($theme['base_images_url'])];
				}

				CacheApi::put('theme_settings-' . $id, null, 90);
			}

			if (!empty($setValues)) {
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
					$setValues,
					['id_theme', 'variable', 'id_member'],
				);
			}

			Utils::redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		Theme::loadTemplate('Themes');

		// Get all installed themes.
		$this->getInstalledThemes();

		Utils::$context['reset_dir'] = realpath(Config::$boarddir . '/Themes');
		Utils::$context['reset_url'] = Config::$boardurl . '/Themes';

		Utils::$context['sub_template'] = 'list_themes';
		SecurityToken::create('admin-tl');
		SecurityToken::create('admin-tr', 'request');
		SecurityToken::create('admin-tre', 'request');
	}

	/**
	 * Administrative global settings.
	 */
	public function setOptions()
	{
		$_GET['th'] = (int) ($_GET['th'] ?? $_GET['id'] ?? 0);

		if (empty($_GET['th'])) {
			Utils::$context['themes'] = [];

			$request = Db::$db->query(
				'',
				'SELECT id_theme, variable, value
				FROM {db_prefix}themes
				WHERE variable IN ({string:name}, {string:theme_dir})
					AND id_member = {int:no_member}',
				[
					'no_member' => 0,
					'name' => 'name',
					'theme_dir' => 'theme_dir',
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!isset(Utils::$context['themes'][$row['id_theme']])) {
					Utils::$context['themes'][$row['id_theme']] = [
						'id' => $row['id_theme'],
						'num_default_options' => 0,
						'num_members' => 0,
					];
				}

				Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
			}
			Db::$db->free_result($request);

			$request = Db::$db->query(
				'',
				'SELECT id_theme, COUNT(*) AS value
				FROM {db_prefix}themes
				WHERE id_member = {int:guest_member}
				GROUP BY id_theme',
				[
					'guest_member' => -1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['themes'][$row['id_theme']]['num_default_options'] = $row['value'];
			}
			Db::$db->free_result($request);

			// Need to make sure we don't do custom fields.
			$customFields = [];

			$request = Db::$db->query(
				'',
				'SELECT col_name
				FROM {db_prefix}custom_fields',
				[
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$customFields[] = $row['col_name'];
			}
			Db::$db->free_result($request);

			$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

			$request = Db::$db->query(
				'themes_count',
				'SELECT COUNT(DISTINCT id_member) AS value, id_theme
				FROM {db_prefix}themes
				WHERE id_member > {int:no_member}
					' . $customFieldsQuery . '
				GROUP BY id_theme',
				[
					'no_member' => 0,
					'custom_fields' => empty($customFields) ? [] : $customFields,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['themes'][$row['id_theme']]['num_members'] = $row['value'];
			}
			Db::$db->free_result($request);

			// There has to be a Settings template!
			foreach (Utils::$context['themes'] as $k => $v) {
				if (empty($v['theme_dir']) || (!file_exists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members']))) {
					unset(Utils::$context['themes'][$k]);
				}
			}

			Theme::loadTemplate('Themes');
			Utils::$context['sub_template'] = 'reset_list';

			SecurityToken::create('admin-stor', 'request');

			return;
		}

		// Submit?
		if (isset($_POST['submit']) && empty($_POST['who'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-sto');

			if (empty($_POST['options'])) {
				$_POST['options'] = [];
			}

			if (empty($_POST['default_options'])) {
				$_POST['default_options'] = [];
			}

			// Set up the sql query.
			$setValues = [];

			foreach ($_POST['options'] as $opt => $val) {
				$setValues[] = [-1, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val];
			}

			$old_settings = [];

			foreach ($_POST['default_options'] as $opt => $val) {
				$old_settings[] = $opt;

				$setValues[] = [-1, 1, $opt, is_array($val) ? implode(',', $val) : $val];
			}

			// If we're actually inserting something..
			if (!empty($setValues)) {
				// Are there options in non-default themes set that should be cleared?
				if (!empty($old_settings)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}themes
						WHERE id_theme != {int:default_theme}
							AND id_member = {int:guest_member}
							AND variable IN ({array_string:old_settings})',
						[
							'default_theme' => 1,
							'guest_member' => -1,
							'old_settings' => $old_settings,
						],
					);
				}

				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
					$setValues,
					['id_theme', 'variable', 'id_member'],
				);
			}

			CacheApi::put('theme_settings-' . $_GET['th'], null, 90);
			CacheApi::put('theme_settings-1', null, 90);

			Utils::redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
		} elseif (isset($_POST['submit']) && $_POST['who'] == 1) {
			User::$me->checkSession();
			SecurityToken::validate('admin-sto');

			$_POST['options'] = empty($_POST['options']) ? [] : $_POST['options'];
			$_POST['options_master'] = empty($_POST['options_master']) ? [] : $_POST['options_master'];
			$_POST['default_options'] = empty($_POST['default_options']) ? [] : $_POST['default_options'];
			$_POST['default_options_master'] = empty($_POST['default_options_master']) ? [] : $_POST['default_options_master'];

			$old_settings = [];

			foreach ($_POST['default_options'] as $opt => $val) {
				if ($_POST['default_options_master'][$opt] == 0) {
					continue;
				}

				if ($_POST['default_options_master'][$opt] == 1) {
					// Delete then insert for ease of database compatibility!
					Db::$db->query(
						'substring',
						'DELETE FROM {db_prefix}themes
						WHERE id_theme = {int:default_theme}
							AND id_member > {int:no_member}
							AND variable = SUBSTRING({string:option}, 1, 255)',
						[
							'default_theme' => 1,
							'no_member' => 0,
							'option' => $opt,
						],
					);

					Db::$db->query(
						'substring',
						'INSERT INTO {db_prefix}themes
							(id_member, id_theme, variable, value)
						SELECT id_member, 1, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
						FROM {db_prefix}members',
						[
							'option' => $opt,
							'value' => (is_array($val) ? implode(',', $val) : $val),
						],
					);

					$old_settings[] = $opt;
				} elseif ($_POST['default_options_master'][$opt] == 2) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}themes
						WHERE variable = {string:option_name}
							AND id_member > {int:no_member}',
						[
							'no_member' => 0,
							'option_name' => $opt,
						],
					);
				}
			}

			// Delete options from other themes.
			if (!empty($old_settings)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}themes
					WHERE id_theme != {int:default_theme}
						AND id_member > {int:no_member}
						AND variable IN ({array_string:old_settings})',
					[
						'default_theme' => 1,
						'no_member' => 0,
						'old_settings' => $old_settings,
					],
				);
			}

			foreach ($_POST['options'] as $opt => $val) {
				if ($_POST['options_master'][$opt] == 0) {
					continue;
				}

				if ($_POST['options_master'][$opt] == 1) {
					// Delete then insert for ease of database compatibility - again!
					Db::$db->query(
						'substring',
						'DELETE FROM {db_prefix}themes
						WHERE id_theme = {int:current_theme}
							AND id_member > {int:no_member}
							AND variable = SUBSTRING({string:option}, 1, 255)',
						[
							'current_theme' => $_GET['th'],
							'no_member' => 0,
							'option' => $opt,
						],
					);

					Db::$db->query(
						'substring',
						'INSERT INTO {db_prefix}themes
							(id_member, id_theme, variable, value)
						SELECT id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
						FROM {db_prefix}members',
						[
							'current_theme' => $_GET['th'],
							'option' => $opt,
							'value' => (is_array($val) ? implode(',', $val) : $val),
						],
					);
				} elseif ($_POST['options_master'][$opt] == 2) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}themes
						WHERE variable = {string:option}
							AND id_member > {int:no_member}
							AND id_theme = {int:current_theme}',
						[
							'no_member' => 0,
							'current_theme' => $_GET['th'],
							'option' => $opt,
						],
					);
				}
			}

			Utils::redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
		} elseif (!empty($_GET['who']) && $_GET['who'] == 2) {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-stor', 'request');

			// Don't delete custom fields!!
			if ($_GET['th'] == 1) {
				$customFields = [];

				$request = Db::$db->query(
					'',
					'SELECT col_name
					FROM {db_prefix}custom_fields',
					[
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$customFields[] = $row['col_name'];
				}
				Db::$db->free_result($request);
			}

			$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}themes
				WHERE id_member > {int:no_member}
					AND id_theme = {int:current_theme}
					' . $customFieldsQuery,
				[
					'no_member' => 0,
					'current_theme' => $_GET['th'],
					'custom_fields' => empty($customFields) ? [] : $customFields,
				],
			);

			Utils::redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
		}

		$old_id = Theme::$current->settings['theme_id'];
		$old_settings = Theme::$current->settings;

		Theme::load($_GET['th'], false);

		Lang::load('Profile');

		// @todo Should we just move these options so they are no longer theme dependant?
		Lang::load('PersonalMessage');

		// Let the theme take care of the settings.
		Theme::loadTemplate('Settings');
		Theme::loadSubTemplate('options');

		// Let mods hook into the theme options.
		IntegrationHook::call('integrate_theme_options');

		Utils::$context['sub_template'] = 'set_options';
		Utils::$context['page_title'] = Lang::$txt['theme_settings'];

		Utils::$context['options'] = Utils::$context['theme_options'];
		Utils::$context['theme_settings'] = Theme::$current->settings;

		if (empty($_REQUEST['who'])) {
			Utils::$context['theme_options'] = [];

			$request = Db::$db->query(
				'',
				'SELECT variable, value
				FROM {db_prefix}themes
				WHERE id_theme IN (1, {int:current_theme})
					AND id_member = {int:guest_member}',
				[
					'current_theme' => $_GET['th'],
					'guest_member' => -1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['theme_options'][$row['variable']] = $row['value'];
			}
			Db::$db->free_result($request);

			Utils::$context['theme_options_reset'] = false;
		} else {
			Utils::$context['theme_options'] = [];
			Utils::$context['theme_options_reset'] = true;
		}

		foreach (Utils::$context['options'] as $i => $setting) {
			// Just skip separators
			if (!is_array($setting)) {
				continue;
			}

			// Is this disabled?
			if (isset($setting['enabled']) && $setting['enabled'] === false) {
				unset(Utils::$context['options'][$i]);

				continue;
			}

			if (!isset($setting['type']) || $setting['type'] == 'bool') {
				Utils::$context['options'][$i]['type'] = 'checkbox';
			} elseif ($setting['type'] == 'int' || $setting['type'] == 'integer') {
				Utils::$context['options'][$i]['type'] = 'number';
			} elseif ($setting['type'] == 'string') {
				Utils::$context['options'][$i]['type'] = 'text';
			}

			if (isset($setting['options'])) {
				Utils::$context['options'][$i]['type'] = 'list';
			}

			Utils::$context['options'][$i]['value'] = !isset(Utils::$context['theme_options'][$setting['id']]) ? '' : Utils::$context['theme_options'][$setting['id']];
		}

		// Restore the existing theme.
		Theme::load($old_id, false);
		Theme::$current->settings = $old_settings;

		Theme::loadTemplate('Themes');
		SecurityToken::create('admin-sto');
	}

	/**
	 * Administrative global settings.
	 * - saves and requests global theme settings. (Theme::$current->settings)
	 * - loads the Admin language file.
	 * - calls admin() if no theme is specified. (the theme center.)
	 * - requires admin_forum permission.
	 * - accessed with ?action=admin;area=theme;sa=list&th=xx.
	 */
	public function setSettings()
	{
		if (empty($_GET['th']) && empty($_GET['id'])) {
			$this->admin();

			return;
		}

		$_GET['th'] = (int) ($_GET['th'] ?? $_GET['id']);

		// Select the best fitting tab.
		Menu::$loaded['admin']['current_subsection'] = 'list';

		// Validate inputs/user.
		if (empty($_GET['th'])) {
			ErrorHandler::fatalLang('no_theme', false);
		}

		// Fetch the smiley sets...
		$sets = explode(',', 'none,' . Config::$modSettings['smiley_sets_known']);
		$set_names = explode("\n", Lang::$txt['smileys_none'] . "\n" . Config::$modSettings['smiley_sets_names']);

		Utils::$context['smiley_sets'] = [
			'' => Lang::$txt['smileys_no_default'],
		];

		foreach ($sets as $i => $set) {
			Utils::$context['smiley_sets'][$set] = Utils::htmlspecialchars($set_names[$i]);
		}

		$old_id = Theme::$current->id;

		Theme::load($_GET['th'], false);

		// Sadly we really do need to init the template.
		Theme::loadSubTemplate('init', 'ignore');

		// Also load the actual themes language file - in case of special settings.
		Lang::load('Settings', '', true, true);

		// And the custom language strings...
		Lang::load('ThemeStrings', '', false, true);

		// Let the theme take care of the settings.
		Theme::loadTemplate('Settings');
		Theme::loadSubTemplate('settings');

		// Load the variants separately...
		Theme::$current->settings['theme_variants'] = [];

		if (file_exists(Theme::$current->settings['theme_dir'] . '/index.template.php')) {
			$file_contents = implode('', file(Theme::$current->settings['theme_dir'] . '/index.template.php'));

			if (preg_match('~((?:SMF\\\\)?Theme::\$current(?:->|_)|\$)settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches)) {
				eval('use SMF\\Theme; global $settings; ' . $matches[0]);
			}
		}

		// Let mods hook into the theme settings.
		IntegrationHook::call('integrate_theme_settings');

		// Submitting!
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-sts');

			if (empty($_POST['options'])) {
				$_POST['options'] = [];
			}

			if (empty($_POST['default_options'])) {
				$_POST['default_options'] = [];
			}

			// Make sure items are cast correctly.
			foreach (Utils::$context['theme_settings'] as $item) {
				// Disregard this item if this is just a separator.
				if (!is_array($item)) {
					continue;
				}

				foreach (['options', 'default_options'] as $option) {
					if (!isset($_POST[$option][$item['id']])) {
						continue;
					}

					// Checkbox.
					if (empty($item['type'])) {
						$_POST[$option][$item['id']] = $_POST[$option][$item['id']] ? 1 : 0;
					}
					// Number
					elseif ($item['type'] == 'number') {
						$_POST[$option][$item['id']] = (int) $_POST[$option][$item['id']];
					}
				}
			}

			// Set up the sql query.
			$inserts = [];

			foreach ($_POST['options'] as $opt => $val) {
				$inserts[] = [0, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val];
			}

			foreach ($_POST['default_options'] as $opt => $val) {
				$inserts[] = [0, 1, $opt, is_array($val) ? implode(',', $val) : $val];
			}

			// If we're actually inserting something..
			if (!empty($inserts)) {
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
					$inserts,
					['id_member', 'id_theme', 'variable'],
				);
			}

			CacheApi::put('theme_settings-' . $_GET['th'], null, 90);
			CacheApi::put('theme_settings-1', null, 90);

			// Invalidate the cache.
			Config::updateModSettings(['settings_updated' => time()]);

			Utils::redirectexit('action=admin;area=theme;sa=list;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		Utils::$context['sub_template'] = 'set_settings';
		Utils::$context['page_title'] = Lang::$txt['theme_settings'];

		foreach (Theme::$current->settings as $setting => $dummy) {
			if (!in_array($setting, ['theme_url', 'theme_dir', 'images_url', 'template_dirs'])) {
				Theme::$current->settings[$setting] = Utils::htmlspecialcharsRecursive(Theme::$current->settings[$setting]);
			}
		}

		Utils::$context['settings'] = Utils::$context['theme_settings'];
		Utils::$context['theme_settings'] = Theme::$current->settings;

		foreach (Utils::$context['settings'] as $i => $setting) {
			// Separators are dummies, so leave them alone.
			if (!is_array($setting)) {
				continue;
			}

			if (!isset($setting['type']) || $setting['type'] == 'bool') {
				Utils::$context['settings'][$i]['type'] = 'checkbox';
			} elseif ($setting['type'] == 'int' || $setting['type'] == 'integer') {
				Utils::$context['settings'][$i]['type'] = 'number';
			} elseif ($setting['type'] == 'string') {
				Utils::$context['settings'][$i]['type'] = 'text';
			}

			if (isset($setting['options'])) {
				Utils::$context['settings'][$i]['type'] = 'list';
			}

			Utils::$context['settings'][$i]['value'] = !isset(Theme::$current->settings[$setting['id']]) ? '' : Theme::$current->settings[$setting['id']];
		}

		// Do we support variants?
		if (!empty(Theme::$current->settings['theme_variants'])) {
			Utils::$context['theme_variants'] = [];

			foreach (Theme::$current->settings['theme_variants'] as $variant) {
				// Have any text, old chap?
				Utils::$context['theme_variants'][$variant] = [
					'label' => Lang::$txt['variant_' . $variant] ?? $variant,
					'thumbnail' => !file_exists(Theme::$current->settings['theme_dir'] . '/images/thumbnail.png') || file_exists(Theme::$current->settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? Theme::$current->settings['images_url'] . '/thumbnail_' . $variant . '.png' : (Theme::$current->settings['images_url'] . '/thumbnail.png'),
				];
			}

			Utils::$context['default_variant'] = !empty(Theme::$current->settings['default_variant']) && isset(Utils::$context['theme_variants'][Theme::$current->settings['default_variant']]) ? Theme::$current->settings['default_variant'] : Theme::$current->settings['theme_variants'][0];
		}

		// Restore the current theme.
		Theme::load($old_id, false);

		// Reinit just incase.
		Theme::loadSubTemplate('init', 'ignore');

		Theme::loadTemplate('Themes');

		// We like Kenny better than Token.
		SecurityToken::create('admin-sts');
	}

	/**
	 * Remove a theme from the database.
	 * - removes an installed theme.
	 * - requires an administrator.
	 * - accessed with ?action=admin;area=theme;sa=remove.
	 */
	public function remove()
	{
		User::$me->checkSession('get');

		SecurityToken::validate('admin-tr', 'request');

		// The theme's ID must be an integer.
		$themeID = (int) ($_GET['th'] ?? $_GET['id'] ?? 1);

		// You can't delete the default theme!
		if ($themeID == 1) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$theme_info = $this->getSingleTheme($themeID, ['theme_dir']);

		// Remove it from the DB.
		$this->removeFromDb($themeID);

		// And remove all its files and folders too.
		if (!empty($theme_info) && !empty($theme_info['theme_dir'])) {
			$this->deltree($theme_info['theme_dir']);
		}

		// Go back to the list page.
		Utils::redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';done=removing');
	}

	/**
	 * Handles enabling/disabling a theme from the admin center
	 */
	public function enable()
	{
		User::$me->checkSession('get');

		SecurityToken::validate('admin-tre', 'request');

		// The theme's ID must be an string.
		$themeID = trim((string) ($_GET['th'] ?? $_GET['id']));

		// Get the current list.
		$enableThemes = explode(',', Config::$modSettings['enableThemes']);

		// Are we disabling it?
		if (isset($_GET['disabled'])) {
			$enableThemes = array_diff($enableThemes, [$themeID]);
		}
		// Nope? then enable it!
		else {
			$enableThemes[] = (string) $themeID;
		}

		// Update the setting.
		$enableThemes = strtr(implode(',', $enableThemes), [',,' => ',']);
		Config::updateModSettings(['enableThemes' => $enableThemes]);

		// Done!
		Utils::redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';done=' . (isset($_GET['disabled']) ? 'disabling' : 'enabling'));
	}

	/**
	 * Installs new themes, calls the respective function according to the install type.
	 * - puts themes in Config::$boardurl/Themes.
	 * - assumes the gzip has a root directory in it. (ie default.)
	 * Requires admin_forum.
	 * Accessed with ?action=admin;area=theme;sa=install.
	 */
	public function install()
	{
		User::$me->checkSession('request');

		// Make it easier to change the path and url.
		Utils::$context['themedir'] = Config::$boarddir . '/Themes';
		Utils::$context['themeurl'] = Config::$boardurl . '/Themes';

		Theme::loadTemplate('Themes');

		$do_actions = [
			'file' => 'installFile',
			'copy' => 'installCopy',
			'dir' => 'installDir',
		];

		// Is there a function to call?
		if (isset($_GET['do']) && !empty($_GET['do']) && isset($do_actions[$_GET['do']])) {
			$do_action = Utils::htmlspecialchars(trim($_GET['do']));

			// Got any info from the specific form?
			if (!isset($_POST['save_' . $do_action])) {
				ErrorHandler::fatalLang('theme_install_no_action', false);
			}

			SecurityToken::validate('admin-t-' . $do_action);

			// Hopefully the themes directory is writable, or we might have a problem.
			if (!is_writable(Utils::$context['themedir'])) {
				ErrorHandler::fatalLang('theme_install_write_error', 'critical');
			}

			// Call the function and handle the result.
			$result = call_user_func([$this, $do_actions[$do_action]]);

			// Everything went better than expected!
			if (!empty($result)) {
				Utils::$context['sub_template'] = 'installed';
				Utils::$context['page_title'] = Lang::$txt['theme_installed'];
				Utils::$context['installed_theme'] = $result;
			}
		}
		// Nope, show a nice error.
		else {
			ErrorHandler::fatalLang('theme_install_no_action', false);
		}
	}

	/**
	 * Shows an interface for editing the templates.
	 * - uses the Themes template and edit_template/edit_style sub template.
	 * - accessed via ?action=admin;area=theme;sa=edit
	 */
	public function edit()
	{
		// @todo Should this be removed?
		if (isset($_REQUEST['preview'])) {
			die('die() with fire');
		}

		Theme::loadTemplate('Themes');

		$_GET['th'] = (int) ($_GET['th'] ?? $_GET['id'] ?? 0);

		if (empty($_GET['th'])) {
			$this->getInstalledThemes();

			foreach (Utils::$context['themes'] as $key => $theme) {
				// There has to be a Settings template!
				if (!file_exists($theme['theme_dir'] . '/index.template.php') && !file_exists($theme['theme_dir'] . '/css/index.css')) {
					unset(Utils::$context['themes'][$key]);
				} else {
					Utils::$context['themes'][$key]['can_edit_style'] = file_exists($theme['theme_dir'] . '/css/index.css');
				}
			}

			Utils::$context['sub_template'] = 'edit_list';

			return 'no_themes';
		}

		Utils::$context['session_error'] = false;

		// Get the directory of the theme we are editing.
		$currentTheme = $this->getSingleTheme($_GET['th']);

		Utils::$context['theme_id'] = $currentTheme['id'];
		Utils::$context['browse_title'] = sprintf(Lang::$txt['themeadmin_browsing_theme'], $currentTheme['name']);

		if (!file_exists($currentTheme['theme_dir'] . '/index.template.php') && !file_exists($currentTheme['theme_dir'] . '/css/index.css')) {
			ErrorHandler::fatalLang('theme_edit_missing', false);
		}

		if (!isset($_REQUEST['filename'])) {
			if (isset($_GET['directory'])) {
				if (substr($_GET['directory'], 0, 1) == '.') {
					$_GET['directory'] = '';
				} else {
					$_GET['directory'] = preg_replace(['~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'], ['', '/', '/'], $_GET['directory']);

					$temp = realpath($currentTheme['theme_dir'] . '/' . $_GET['directory']);

					if (empty($temp) || substr($temp, 0, strlen(realpath($currentTheme['theme_dir']))) != realpath($currentTheme['theme_dir'])) {
						$_GET['directory'] = '';
					}
				}
			}

			if (isset($_GET['directory']) && $_GET['directory'] != '') {
				Utils::$context['theme_files'] = $this->getFileList($currentTheme['theme_dir'] . '/' . $_GET['directory'], $_GET['directory'] . '/');

				$temp = dirname($_GET['directory']);

				array_unshift(Utils::$context['theme_files'], [
					'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
					'is_writable' => is_writable($currentTheme['theme_dir'] . '/' . $temp),
					'is_directory' => true,
					'is_template' => false,
					'is_image' => false,
					'is_editable' => false,
					'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . $temp,
					'size' => '',
				]);
			} else {
				Utils::$context['theme_files'] = $this->getFileList($currentTheme['theme_dir'], '');
			}

			// Do not list minified_ files
			foreach (Utils::$context['theme_files'] as $key => $file) {
				if (strpos($file['filename'], 'minified_') !== false) {
					unset(Utils::$context['theme_files'][$key]);
				}
			}

			Utils::$context['sub_template'] = 'edit_browse';

			return;
		}

		if (substr($_REQUEST['filename'], 0, 1) == '.') {
			$_REQUEST['filename'] = '';
		} else {
			$_REQUEST['filename'] = preg_replace(['~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'], ['', '/', '/'], $_REQUEST['filename']);

			$temp = realpath($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);

			if (empty($temp) || substr($temp, 0, strlen(realpath($currentTheme['theme_dir']))) != realpath($currentTheme['theme_dir'])) {
				$_REQUEST['filename'] = '';
			}
		}

		if (empty($_REQUEST['filename'])) {
			ErrorHandler::fatalLang('theme_edit_missing', false);
		}

		if (isset($_POST['save'])) {
			if (User::$me->checkSession('post', '', false) == '' && SecurityToken::validate('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']), 'post', false) == true) {
				if (is_array($_POST['entire_file'])) {
					$_POST['entire_file'] = implode("\n", $_POST['entire_file']);
				}

				$_POST['entire_file'] = rtrim(strtr($_POST['entire_file'], ["\r" => '', '   ' => "\t"]));

				// Check for a parse error!
				if (substr($_REQUEST['filename'], -13) == '.template.php' && is_writable($currentTheme['theme_dir']) && ini_get('display_errors')) {
					Config::safeFileWrite($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php', $_POST['entire_file']);

					$error = @file_get_contents($currentTheme['theme_url'] . '/tmp_' . session_id() . '.php');

					if (preg_match('~ <b>(\d+)</b><br( /)?' . '>$~i', $error) != 0) {
						$error_file = $currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php';
					} else {
						unlink($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php');
					}
				}

				if (!isset($error_file)) {
					Config::safeFileWrite($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], $_POST['entire_file']);

					// Nuke any minified files and update Config::$modSettings['browser_cache']
					Theme::deleteAllMinified();

					Utils::redirectexit('action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . dirname($_REQUEST['filename']));
				}
			}
			// Session timed out.
			else {
				Lang::load('Errors');

				Utils::$context['session_error'] = true;
				Utils::$context['sub_template'] = 'edit_file';

				// Recycle the submitted data.
				if (is_array($_POST['entire_file'])) {
					Utils::$context['entire_file'] = Utils::htmlspecialchars(implode("\n", $_POST['entire_file']));
				} else {
					Utils::$context['entire_file'] = Utils::htmlspecialchars($_POST['entire_file']);
				}

				Utils::$context['edit_filename'] = Utils::htmlspecialchars($_POST['filename']);

				// You were able to submit it, so it's reasonable to assume you are allowed to save.
				Utils::$context['allow_save'] = true;

				// Re-create the token so that it can be used
				SecurityToken::create('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));

				return;
			}
		}

		Utils::$context['allow_save'] = is_writable($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);

		Utils::$context['allow_save_filename'] = strtr($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], [Config::$boarddir => '...']);

		Utils::$context['edit_filename'] = Utils::htmlspecialchars($_REQUEST['filename']);

		if (substr($_REQUEST['filename'], -4) == '.css') {
			Utils::$context['sub_template'] = 'edit_style';

			Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), ["\t" => '   ']));
		} elseif (substr($_REQUEST['filename'], -13) == '.template.php') {
			Utils::$context['sub_template'] = 'edit_template';

			if (!isset($error_file)) {
				$file_data = file($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
			} else {
				if (preg_match('~(<b>.+?</b>:.+?<b>).+?(</b>.+?<b>\d+</b>)<br( /)?' . '>$~i', $error, $match) != 0) {
					Utils::$context['parse_error'] = $match[1] . $_REQUEST['filename'] . $match[2];
				}

				$file_data = file($error_file);
				unlink($error_file);
			}

			$j = 0;
			Utils::$context['file_parts'] = [['lines' => 0, 'line' => 1, 'data' => '']];

			for ($i = 0, $n = count($file_data); $i < $n; $i++) {
				if (isset($file_data[$i + 1]) && substr($file_data[$i + 1], 0, 9) == 'function ') {
					// Try to format the functions a little nicer...
					Utils::$context['file_parts'][$j]['data'] = trim(Utils::$context['file_parts'][$j]['data']) . "\n";

					if (empty(Utils::$context['file_parts'][$j]['lines'])) {
						unset(Utils::$context['file_parts'][$j]);
					}

					Utils::$context['file_parts'][++$j] = ['lines' => 0, 'line' => $i + 1, 'data' => ''];
				}

				Utils::$context['file_parts'][$j]['lines']++;
				Utils::$context['file_parts'][$j]['data'] .= Utils::htmlspecialchars(strtr($file_data[$i], ["\t" => '   ']));
			}

			Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(implode('', $file_data), ["\t" => '   ']));
		} else {
			Utils::$context['sub_template'] = 'edit_file';

			Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), ["\t" => '   ']));
		}

		// Create a special token to allow editing of multiple files.
		SecurityToken::create('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));
	}

	/**
	 * Makes a copy of a template file in a new location
	 *
	 * @uses template_copy_template()
	 */
	public function copy()
	{
		Theme::loadTemplate('Themes');

		Menu::$loaded['admin']['current_subsection'] = 'edit';

		$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

		if (empty($_GET['th'])) {
			ErrorHandler::fatalLang('theme_install_invalid_id');
		}

		// Get the theme info.
		$theme = $this->getSingleTheme($_GET['th']);
		Utils::$context['theme_id'] = $theme['id'];

		if (isset($_REQUEST['template']) && preg_match('~[\./\\\\:\0]~', $_REQUEST['template']) == 0) {
			if (file_exists(Theme::$current->settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php')) {
				$filename = Theme::$current->settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php';
			} else {
				ErrorHandler::fatalLang('no_access', false);
			}

			$fp = fopen($theme['theme_dir'] . '/' . $_REQUEST['template'] . '.template.php', 'w');
			fwrite($fp, file_get_contents($filename));
			fclose($fp);

			Utils::redirectexit('action=admin;area=theme;th=' . Utils::$context['theme_id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=copy');
		} elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^\./\\\\:\0]\.[^\./\\\\:\0]$~', $_REQUEST['lang_file']) != 0) {
			if (file_exists(Theme::$current->settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php')) {
				$filename = Theme::$current->settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php';
			} else {
				ErrorHandler::fatalLang('no_access', false);
			}

			$fp = fopen($theme['theme_dir'] . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
			fwrite($fp, file_get_contents($filename));
			fclose($fp);

			Utils::redirectexit('action=admin;area=theme;th=' . Utils::$context['theme_id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=copy');
		}

		$templates = [];
		$lang_files = [];

		$dir = dir(Theme::$current->settings['default_theme_dir']);

		while ($entry = $dir->read()) {
			if (substr($entry, -13) == '.template.php') {
				$templates[] = substr($entry, 0, -13);
			}
		}

		$dir->close();

		$dir = dir(Theme::$current->settings['default_theme_dir'] . '/languages');

		while ($entry = $dir->read()) {
			if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches)) {
				$lang_files[] = $matches[1];
			}
		}

		$dir->close();

		natcasesort($templates);
		natcasesort($lang_files);

		Utils::$context['available_templates'] = [];

		foreach ($templates as $template) {
			Utils::$context['available_templates'][$template] = [
				'filename' => $template . '.template.php',
				'value' => $template,
				'already_exists' => false,
				'can_copy' => is_writable($theme['theme_dir']),
			];
		}

		Utils::$context['available_language_files'] = [];

		foreach ($lang_files as $file) {
			Utils::$context['available_language_files'][$file] = [
				'filename' => $file . '.php',
				'value' => $file,
				'already_exists' => false,
				'can_copy' => file_exists($theme['theme_dir'] . '/languages') ? is_writable($theme['theme_dir'] . '/languages') : is_writable($theme['theme_dir']),
			];
		}

		$dir = dir($theme['theme_dir']);

		while ($entry = $dir->read()) {
			if (substr($entry, -13) == '.template.php' && isset(Utils::$context['available_templates'][substr($entry, 0, -13)])) {
				Utils::$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;

				Utils::$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme['theme_dir'] . '/' . $entry);
			}
		}

		$dir->close();

		if (file_exists($theme['theme_dir'] . '/languages')) {
			$dir = dir($theme['theme_dir'] . '/languages');

			while ($entry = $dir->read()) {
				if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && isset(Utils::$context['available_language_files'][$matches[1]])) {
					Utils::$context['available_language_files'][$matches[1]]['already_exists'] = true;

					Utils::$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme['theme_dir'] . '/languages/' . $entry);
				}
			}

			$dir->close();
		}

		Utils::$context['sub_template'] = 'copy_template';
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
	 * Backward compatibility wrapper for the admin sub-action.
	 */
	public static function themeAdmin(): void
	{
		self::load();
		self::$obj->subaction = 'admin';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the list sub-action.
	 */
	public static function themeList(): void
	{
		self::load();
		self::$obj->subaction = 'list';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the options sub-action.
	 */
	public static function setThemeOptions(): void
	{
		self::load();
		self::$obj->subaction = 'options';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the remove sub-action.
	 */
	public static function removeTheme(): void
	{
		self::load();
		self::$obj->subaction = 'remove';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the enable sub-action.
	 */
	public static function enableTheme(): void
	{
		self::load();
		self::$obj->subaction = 'enable';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the install sub-action.
	 */
	public static function themeInstall(): void
	{
		self::load();
		self::$obj->subaction = 'install';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the edit sub-action.
	 */
	public static function editTheme(): void
	{
		self::load();
		self::$obj->subaction = 'edit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the copy sub-action.
	 */
	public static function copyTemplate(): void
	{
		self::load();
		self::$obj->subaction = 'copy';
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
		// PickTheme() has been migrated to SMF\Theme::pickTheme()
		if (isset($_GET['sa']) && $_GET['sa'] === 'pick') {
			Utils::redirectexit('action=theme;sa=pick' . (isset($_GET['u']) ? ';u=' . $_GET['u'] : ''));
		}
		// Everything in this file should be accessed via the ACP, not the 'theme' action.
		elseif ($_REQUEST['action'] === 'theme') {
			Utils::redirectexit('action=admin;area=theme;' . (isset($_GET['sa']) ? ';sa=' . $_GET['sa'] : '') . (isset($_GET['u']) ? ';u=' . $_GET['u'] : ''));
		}

		User::$me->isAllowedTo('admin_forum');

		// Load the important language files...
		Lang::load('Admin');
		Lang::load('Themes');
		Lang::load('Settings');
		Lang::load('Drafts');

		// Default the page title to Theme Administration by default.
		Utils::$context['page_title'] = Lang::$txt['themeadmin_title'];

		if (!empty(Utils::$context['admin_menu_name'])) {
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::$txt['themeadmin_title'],
				'description' => Lang::$txt['themeadmin_description'],
				'tabs' => [
					'admin' => [
						'description' => Lang::$txt['themeadmin_admin_desc'],
					],
					'list' => [
						'description' => Lang::$txt['themeadmin_list_desc'],
					],
					'reset' => [
						'description' => Lang::$txt['themeadmin_reset_desc'],
					],
					'edit' => [
						'description' => Lang::$txt['themeadmin_edit_desc'],
					],
				],
			];
		}

		// CRUD self::$subactions as needed.
		IntegrationHook::call('integrate_manage_themes', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Installs a theme from a theme package.
	 *
	 * Stores the theme files on a temp dir, on success it renames the dir to
	 * the new theme's name.
	 * Ends execution with ErrorHandler::fatalLang() on any error.
	 *
	 * @return array The newly created theme's info.
	 */
	protected function installFile()
	{
		// Set a temp dir for dumping all required files on it.
		$dirtemp = Utils::$context['themedir'] . '/temp';

		// Make sure the temp dir doesn't already exist
		if (file_exists($dirtemp)) {
			$this->deltree($dirtemp);
		}

		// Create the temp dir.
		mkdir($dirtemp, 0777);

		// Hopefully the temp directory is writable, or we might have a problem.
		if (!is_writable($dirtemp)) {
			// Lets give it a try.
			Utils::makeWritable($dirtemp, '0755');

			// How about now?
			if (!is_writable($dirtemp)) {
				ErrorHandler::fatalLang('theme_install_write_error', 'critical');
			}
		}

		// This happens when the admin session is gone and the user has to login again.
		if (!isset($_FILES) || !isset($_FILES['theme_gz']) || empty($_FILES['theme_gz'])) {
			Utils::redirectexit('action=admin;area=theme;sa=admin;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Another error check layer, something went wrong with the upload.
		if (isset($_FILES['theme_gz']['error']) && $_FILES['theme_gz']['error'] != 0) {
			ErrorHandler::fatalLang('theme_install_error_file_' . $_FILES['theme_gz']['error'], false);
		}

		// Get the theme's name.
		$name = pathinfo($_FILES['theme_gz']['name'], PATHINFO_FILENAME);
		$name = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/', '/\.tar$/'], ['_', '.', '', ''], $name);

		// Start setting some vars.
		Utils::$context['to_install'] = [
			'theme_dir' => Utils::$context['themedir'] . '/' . $name,
			'theme_url' => Utils::$context['themeurl'] . '/' . $name,
			'images_url' => Utils::$context['themeurl'] . '/' . $name . '/images',
			'name' => $name,
		];

		// Extract the file on the proper themes dir.
		$extracted = SubsPackage::read_tgz_file($_FILES['theme_gz']['tmp_name'], $dirtemp, false, true);

		if ($extracted) {
			// Read its info form the XML file.
			$theme_info = $this->getThemeInfo($dirtemp);
			Utils::$context['to_install'] += $theme_info;

			// Install the theme. addToDb() will return the new installed ID.
			Utils::$context['to_install']['id'] = $this->addToDb(Utils::$context['to_install']);

			// Rename the temp dir to the actual theme name.
			rename($dirtemp, Utils::$context['to_install']['theme_dir']);

			// return all the info.
			return Utils::$context['to_install'];
		}

		ErrorHandler::fatalLang('theme_install_error_title', false);
	}

	/**
	 * Makes a copy from the default theme, assigns a name for it and installs it.
	 *
	 * Creates a new .xml file containing all the theme's info.
	 *
	 * @return array The newly created theme's info.
	 */
	protected function installCopy()
	{
		// There's gotta be something to work with.
		if (!isset($_REQUEST['copy']) || empty($_REQUEST['copy'])) {
			ErrorHandler::fatalLang('theme_install_error_title', false);
		}

		// Get a cleaner version.
		$name = preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

		// Is there a theme already named like this?
		if (file_exists(Utils::$context['themedir'] . '/' . $name)) {
			ErrorHandler::fatalLang('theme_install_already_dir', false);
		}

		// This is a brand new theme so set all possible values.
		Utils::$context['to_install'] = [
			'theme_dir' => Utils::$context['themedir'] . '/' . $name,
			'theme_url' => Utils::$context['themeurl'] . '/' . $name,
			'name' => $name,
			'images_url' => Utils::$context['themeurl'] . '/' . $name . '/images',
			'version' => '1.0',
			'install_for' => '3.0 - 3.0.99, ' . SMF_VERSION,
			'based_on' => '',
			'based_on_dir' => Utils::$context['themedir'] . '/default',
			'theme_layers' => 'html,body',
			'theme_templates' => 'index',
		];

		// Create the specific dir.
		umask(0);
		mkdir(Utils::$context['to_install']['theme_dir'], 0777);

		// Buy some time.
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		// Create subdirectories for css and javascript files.
		mkdir(Utils::$context['to_install']['theme_dir'] . '/css', 0777);
		mkdir(Utils::$context['to_install']['theme_dir'] . '/scripts', 0777);

		// Create subdirectory for language files
		mkdir(Utils::$context['to_install']['theme_dir'] . '/languages', 0777);

		// Copy over the default non-theme files.
		$to_copy = [
			'/index.php',
			'/index.template.php',
			'/css/admin.css',
			'/css/calendar.css',
			'/css/calendar.rtl.css',
			'/css/index.css',
			'/css/responsive.css',
			'/css/rtl.css',
			'/scripts/theme.js',
			'/languages/index.php',
			'/languages/Settings.english.php',
		];

		foreach ($to_copy as $file) {
			copy(Theme::$current->settings['default_theme_dir'] . $file, Utils::$context['to_install']['theme_dir'] . $file);
			Utils::makeWritable(Utils::$context['to_install']['theme_dir'] . $file, 0777);
		}

		// And now the entire images directory!
		SubsPackage::copytree(Theme::$current->settings['default_theme_dir'] . '/images', Utils::$context['to_install']['theme_dir'] . '/images');
		SubsPackage::package_flush_cache();

		// Any data from the default theme that we want?
		foreach ($this->getSingleTheme(1, ['theme_layers', 'theme_templates']) as $variable => $value) {
			if ($variable == 'theme_templates' || $variable == 'theme_layers') {
				Utils::$context['to_install'][$variable] = $value;
			}
		}

		// Lets add a theme_info.xml to this theme.
		$xml_info = '<' . '?xml version="1.0"?' . '>
<theme-info xmlns="http://www.simplemachines.org/xml/theme-info" xmlns:smf="http://www.simplemachines.org/">
<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
<id>smf:' . Utils::strtolower(Utils::$context['to_install']['name']) . '</id>
<!-- The theme\'s version, please try to use semantic versioning. -->
<version>1.0</version>
<!-- Install for, the SMF versions this theme was designed for. Uses the same wildcards used in the packager manager. This field is mandatory. -->
<install for="' . Utils::$context['to_install']['install_for'] . '" />
<!-- Theme name, used purely for aesthetics. -->
<name>' . Utils::$context['to_install']['name'] . '</name>
<!-- Author: your email address or contact information. The name attribute is optional. -->
<author name="Simple Machines">info@simplemachines.org</author>
<!-- Website... where to get updates and more information. -->
<website>https://www.simplemachines.org/</website>
<!-- Template layers to use, defaults to "html,body". -->
<layers>' . Utils::$context['to_install']['theme_layers'] . '</layers>
<!-- Templates to load on startup. Default is "index". -->
<templates>' . Utils::$context['to_install']['theme_templates'] . '</templates>
<!-- Base this theme off another? Default is blank, or no. It could be "default". -->
<based-on></based-on>
</theme-info>';

		// Now write it.
		$fp = @fopen(Utils::$context['to_install']['theme_dir'] . '/theme_info.xml', 'w+');

		if ($fp) {
			fwrite($fp, $xml_info);
			fclose($fp);
		}

		// Install the theme. addToDb() will take care of possible errors.
		Utils::$context['to_install']['id'] = $this->addToDb(Utils::$context['to_install']);

		// return the info.
		return Utils::$context['to_install'];
	}

	/**
	 * Install a theme from a specific dir
	 *
	 * Assumes the dir is located on the main Themes dir.
	 * Ends execution with ErrorHandler::fatalLang() on any error.
	 *
	 * @return array The newly created theme's info.
	 */
	protected function installDir()
	{
		// Cannot use the theme dir as a theme dir.
		if (!isset($_REQUEST['theme_dir']) || empty($_REQUEST['theme_dir']) || rtrim(realpath($_REQUEST['theme_dir']), '/\\') == realpath(Utils::$context['themedir'])) {
			ErrorHandler::fatalLang('theme_install_invalid_dir', false);
		}

		// Check is there is "something" on the dir.
		elseif (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme_info.xml')) {
			ErrorHandler::fatalLang('theme_install_error', false);
		}

		$name = basename($_REQUEST['theme_dir']);
		$name = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $name);

		// All good! set some needed vars.
		Utils::$context['to_install'] = [
			'theme_dir' => $_REQUEST['theme_dir'],
			'theme_url' => Utils::$context['themeurl'] . '/' . $name,
			'name' => $name,
			'images_url' => Utils::$context['themeurl'] . '/' . $name . '/images',
		];

		// Read its info form the XML file.
		$theme_info = $this->getThemeInfo(Utils::$context['to_install']['theme_dir']);
		Utils::$context['to_install'] += $theme_info;

		// Install the theme. addToDb() will take care of possible errors.
		Utils::$context['to_install']['id'] = $this->addToDb(Utils::$context['to_install']);

		// return the info.
		return Utils::$context['to_install'];
	}

	/**
	 * Gets a single theme's info.
	 *
	 * @param int $id The theme ID to get the info from.
	 * @param string[] $variables
	 * @return array The theme info as an array.
	 */
	protected function getSingleTheme($id, array $variables = [])
	{
		// No data, no fun!
		if (empty($id)) {
			return false;
		}

		// Make sure $id is an int.
		$id = (int) $id;

		// Make changes if you really want it.
		IntegrationHook::call('integrate_get_single_theme', [&$variables, $id]);

		$single = [
			'id' => $id,
		];

		// Make our known/enable themes a little easier to work with.
		$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : [];
		$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : [];

		$request = Db::$db->query(
			'',
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_theme = ({int:id_theme})
				AND id_member = {int:no_member}' . (!empty($variables) ? '
				AND variable IN ({array_string:variables})' : ''),
			[
				'variables' => $variables,
				'id_theme' => $id,
				'no_member' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$single[$row['variable']] = $row['value'];

			// Fix the path and tell if its a valid one.
			if ($row['variable'] == 'theme_dir') {
				$single['theme_dir'] = realpath($row['value']);
				$single['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
			}
		}

		// Is this theme installed and enabled?
		$single['known'] = in_array($single['id'], $knownThemes);
		$single['enable'] = in_array($single['id'], $enableThemes);

		// It should at least return if the theme is a known one or if its enable.
		return $single;
	}

	/**
	 * Loads and returns all installed themes.
	 *
	 * Stores all themes on Utils::$context['themes'] for easier use.
	 *
	 * Config::$modSettings['knownThemes'] stores themes that the user is able to select.
	 *
	 * @param bool $enable_only Whether to fetch only enabled themes. Default is false.
	 */
	protected function getAllThemes($enable_only = false)
	{
		// Make our known/enable themes a little easier to work with.
		$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : [];
		$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : [];

		// List of all possible themes values.
		$themeValues = [
			'theme_dir',
			'images_url',
			'theme_url',
			'name',
			'theme_layers',
			'theme_templates',
			'version',
			'install_for',
			'based_on',
		];

		// Make changes if you really want it.
		IntegrationHook::call('integrate_get_all_themes', [&$themeValues, $enable_only]);

		// So, what is it going to be?
		$query_where = $enable_only ? $enableThemes : $knownThemes;

		// Perform the query as requested.
		$request = Db::$db->query(
			'',
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({array_string:theme_values})
				AND id_theme IN ({array_string:query_where})
				AND id_member = {int:no_member}',
			[
				'query_where' => $query_where,
				'theme_values' => $themeValues,
				'no_member' => 0,
			],
		);

		Utils::$context['themes'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset(Utils::$context['themes'][$row['id_theme']])) {
				Utils::$context['themes'][$row['id_theme']] = [
					'id' => (int) $row['id_theme'],
					'known' => in_array($row['id_theme'], $knownThemes),
					'enable' => in_array($row['id_theme'], $enableThemes),
				];
			}

			// Fix the path and tell if its a valid one.
			if ($row['variable'] == 'theme_dir') {
				$row['value'] = realpath($row['value']);
				Utils::$context['themes'][$row['id_theme']]['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
			}
			Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}

		Db::$db->free_result($request);
	}

	/**
	 * Loads and returns all installed themes.
	 *
	 * Stores all themes on Utils::$context['themes'] for easier use.
	 *
	 * Config::$modSettings['knownThemes'] stores themes that the user is able to select.
	 */
	protected function getInstalledThemes()
	{
		// Make our known/enable themes a little easier to work with.
		$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : [];
		$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : [];

		// List of all possible themes values.
		$themeValues = [
			'theme_dir',
			'images_url',
			'theme_url',
			'name',
			'theme_layers',
			'theme_templates',
			'version',
			'install_for',
			'based_on',
		];

		// Make changes if you really want it.
		IntegrationHook::call('integrate_get_installed_themes', [&$themeValues]);

		// Perform the query as requested.
		$request = Db::$db->query(
			'',
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({array_string:theme_values})
				AND id_member = {int:no_member}',
			[
				'theme_values' => $themeValues,
				'no_member' => 0,
			],
		);

		Utils::$context['themes'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset(Utils::$context['themes'][$row['id_theme']])) {
				Utils::$context['themes'][$row['id_theme']] = [
					'id' => (int) $row['id_theme'],
					'known' => in_array($row['id_theme'], $knownThemes),
					'enable' => in_array($row['id_theme'], $enableThemes),
				];
			}

			// Fix the path and tell if its a valid one.
			if ($row['variable'] == 'theme_dir') {
				$row['value'] = realpath($row['value']);
				Utils::$context['themes'][$row['id_theme']]['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
			}
			Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}

		Db::$db->free_result($request);
	}

	/**
	 * Reads an .xml file and returns the data as an array
	 *
	 * Removes the entire theme if the .xml file couldn't be found or read.
	 *
	 * @param string $path The absolute path to the xml file.
	 * @return array An array with all the info extracted from the xml file.
	 */
	protected function getThemeInfo($path)
	{
		if (empty($path)) {
			return false;
		}

		$xml_data = [];

		// Perhaps they are trying to install a mod, lets tell them nicely this is the wrong function.
		if (file_exists($path . '/package-info.xml')) {
			Lang::load('Errors');

			// We need to delete the dir otherwise the next time you try to install a theme you will get the same error.
			$this->deltree($path);

			Lang::$txt['package_get_error_is_mod'] = str_replace('{MANAGEMODURL}', Config::$scripturl . '?action=admin;area=packages;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Lang::$txt['package_get_error_is_mod']);
			ErrorHandler::fatalLang('package_theme_upload_error_broken', false, Lang::$txt['package_get_error_is_mod']);
		}

		// Parse theme-info.xml into an XmlArray.
		$theme_info_xml = new XmlArray(file_get_contents($path . '/theme_info.xml'));

		// Error message, there isn't any valid info.
		if (!$theme_info_xml->exists('theme-info[0]')) {
			$this->deltree($path);
			ErrorHandler::fatalLang('package_get_error_packageinfo_corrupt', false);
		}

		// Check for compatibility with 2.1 or greater.
		if (!$theme_info_xml->exists('theme-info/install')) {
			$this->deltree($path);
			ErrorHandler::fatalLang('package_get_error_theme_not_compatible', false, SMF_FULL_VERSION);
		}

		// So, we have an install tag which is cool and stuff but we also need to check it and match your current SMF version...
		$the_version = SMF_VERSION;
		$install_versions = $theme_info_xml->fetch('theme-info/install/@for');

		// The theme isn't compatible with the current SMF version.
		if (!$install_versions || !SubsPackage::matchPackageVersion($the_version, $install_versions)) {
			$this->deltree($path);
			ErrorHandler::fatalLang('package_get_error_theme_not_compatible', false, SMF_FULL_VERSION);
		}

		$theme_info_xml = $theme_info_xml->to_array('theme-info[0]');

		$xml_elements = [
			'theme_layers' => 'layers',
			'theme_templates' => 'templates',
			'based_on' => 'based-on',
			'version' => 'version',
		];

		// Assign the values to be stored.
		foreach ($xml_elements as $var => $name) {
			if (!empty($theme_info_xml[$name])) {
				$xml_data[$var] = $theme_info_xml[$name];
			}
		}

		// Add the supported versions.
		$xml_data['install_for'] = $install_versions;

		// Overwrite the default images folder.
		if (!empty($theme_info_xml['images'])) {
			$xml_data['images_url'] = $path . '/' . $theme_info_xml['images'];
			$xml_data['explicit_images'] = true;
		} else {
			$xml_data['explicit_images'] = false;
		}

		if (!empty($theme_info_xml['extra'])) {
			$xml_data += Utils::jsonDecode($theme_info_xml['extra'], true);
		}

		return $xml_data;
	}

	/**
	 * Inserts a theme's data to the DataBase.
	 *
	 * Ends execution with ErrorHandler::fatalLang() if an error appears.
	 *
	 * @param array $to_install An array containing all values to be stored into the DB.
	 * @return int The newly created theme ID.
	 */
	protected function addToDb($to_install = [])
	{
		// External use? no problem!
		if (!empty($to_install)) {
			Utils::$context['to_install'] = $to_install;
		}

		// One last check.
		if (empty(Utils::$context['to_install']['theme_dir']) || basename(Utils::$context['to_install']['theme_dir']) == 'Themes') {
			ErrorHandler::fatalLang('theme_install_invalid_dir', false);
		}

		// OK, is this a newer version of an already installed theme?
		if (!empty(Utils::$context['to_install']['version'])) {
			$request = Db::$db->query(
				'',
				'SELECT id_theme
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND variable = {literal:name}
					AND value LIKE {string:name_value}
				LIMIT 1',
				[
					'no_member' => 0,
					'name_value' => '%' . Utils::$context['to_install']['name'] . '%',
				],
			);
			list($id_to_update) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$to_update = $this->getSingleTheme($id_to_update, ['version']);

			// Got something, lets figure it out what to do next.
			if (!empty($id_to_update) && !empty($to_update['version'])) {
				switch (SubsPackage::compareVersions(Utils::$context['to_install']['version'], $to_update['version'])) {
					case 1: // Got a newer version, update the old entry.
						Db::$db->query(
							'',
							'UPDATE {db_prefix}themes
							SET value = {string:new_value}
							WHERE variable = {literal:version}
								AND id_theme = {int:id_theme}',
							[
								'new_value' => Utils::$context['to_install']['version'],
								'id_theme' => $id_to_update,
							],
						);

						// Done with the update, tell the user about it.
						Utils::$context['to_install']['updated'] = true;

						return $id_to_update;

					case 0: // This is exactly the same theme.
					case -1: // The one being installed is older than the one already installed.
					default: // Any other possible result.
						ErrorHandler::fatalLang('package_get_error_theme_no_new_version', false, [Utils::$context['to_install']['version'], $to_update['version']]);
				}
			}
		}

		if (!empty(Utils::$context['to_install']['based_on'])) {
			// No need for elaborated stuff when the theme is based on the default one.
			if (Utils::$context['to_install']['based_on'] == 'default') {
				Utils::$context['to_install']['theme_url'] = Theme::$current->settings['default_theme_url'];

				Utils::$context['to_install']['images_url'] = Theme::$current->settings['default_images_url'];
			}
			// Custom theme based on another custom theme, lets get some info.
			elseif (Utils::$context['to_install']['based_on'] != '') {
				Utils::$context['to_install']['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', Utils::$context['to_install']['based_on']);

				// Get the theme info first.
				$request = Db::$db->query(
					'',
					'SELECT id_theme
					FROM {db_prefix}themes
					WHERE id_member = {int:no_member}
						AND (value LIKE {string:based_on} OR value LIKE {string:based_on_path})
					LIMIT 1',
					[
						'no_member' => 0,
						'based_on' => '%/' . Utils::$context['to_install']['based_on'],
						'based_on_path' => '%' . '\\' . Utils::$context['to_install']['based_on'],
					],
				);
				list($id_based_on) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				$temp = $this->getSingleTheme($id_based_on, ['theme_dir', 'images_url', 'theme_url']);

				// Found the based on theme info, add it to the current one being installed.
				if (!empty($temp)) {
					Utils::$context['to_install']['base_theme_url'] = $temp['theme_url'];
					Utils::$context['to_install']['base_theme_dir'] = $temp['theme_dir'];

					if (empty(Utils::$context['to_install']['explicit_images']) && !empty(Utils::$context['to_install']['base_theme_url'])) {
						Utils::$context['to_install']['theme_url'] = Utils::$context['to_install']['base_theme_url'];
					}
				}
				// Nope, sorry, couldn't find any theme already installed.
				else {
					ErrorHandler::fatalLang('package_get_error_theme_no_based_on_found', false, Utils::$context['to_install']['based_on']);
				}
			}

			unset(Utils::$context['to_install']['based_on']);
		}

		// Find the newest id_theme.
		$result = Db::$db->query(
			'',
			'SELECT MAX(id_theme)
			FROM {db_prefix}themes',
			[
			],
		);
		list($id_theme) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// This will be theme number...
		$id_theme++;

		// Last minute changes? although, the actual array is a context value you might want to use the new ID.
		IntegrationHook::call('integrate_theme_install', [&Utils::$context['to_install'], $id_theme]);

		$inserts = [];

		foreach (Utils::$context['to_install'] as $var => $val) {
			$inserts[] = [$id_theme, $var, $val];
		}

		if (!empty($inserts)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}themes',
				['id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
				$inserts,
				['id_theme', 'variable'],
			);
		}

		// Update the known and enable Theme's settings.
		$known = strtr(Config::$modSettings['knownThemes'] . ',' . $id_theme, [',,' => ',']);
		$enable = strtr(Config::$modSettings['enableThemes'] . ',' . $id_theme, [',,' => ',']);
		Config::updateModSettings(['knownThemes' => $known, 'enableThemes' => $enable]);

		return $id_theme;
	}

	/**
	 * Removes a theme from the DB, includes all possible places where the theme might be used.
	 *
	 * @param int $themeID The theme ID
	 * @return bool true when success, false on error.
	 */
	protected function removeFromDb($themeID)
	{
		// Can't delete the default theme, sorry!
		if (empty($themeID) || $themeID == 1) {
			return false;
		}

		$known = explode(',', Config::$modSettings['knownThemes']);
		$enable = explode(',', Config::$modSettings['enableThemes']);

		// Remove it from the themes table.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}themes
			WHERE id_theme = {int:current_theme}',
			[
				'current_theme' => $themeID,
			],
		);

		// Update users preferences.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}members
			SET id_theme = {int:default_theme}
			WHERE id_theme = {int:current_theme}',
			[
				'default_theme' => 0,
				'current_theme' => $themeID,
			],
		);

		// Some boards may have it as preferred theme.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET id_theme = {int:default_theme}
			WHERE id_theme = {int:current_theme}',
			[
				'default_theme' => 0,
				'current_theme' => $themeID,
			],
		);

		// Remove it from the list of known themes.
		$known = array_diff($known, [$themeID]);

		// And the enable list too.
		$enable = array_diff($enable, [$themeID]);

		// Back to good old comma separated string.
		$known = strtr(implode(',', $known), [',,' => ',']);
		$enable = strtr(implode(',', $enable), [',,' => ',']);

		// Update the enableThemes list.
		Config::updateModSettings(['enableThemes' => $enable, 'knownThemes' => $known]);

		// Fix it if the theme was the overall default theme.
		if (Config::$modSettings['theme_guests'] == $themeID) {
			Config::updateModSettings(['theme_guests' => '1']);
		}

		return true;
	}

	/**
	 * Removes a directory from the themes dir.
	 *
	 * This is a recursive function, it will call itself if there are subdirs inside the main directory.
	 *
	 * @param string $path The absolute path to the directory to be removed
	 * @return bool true when success, false on error.
	 */
	protected function deltree($path)
	{
		if (empty($path)) {
			return false;
		}

		if (is_dir($path)) {
			$objects = scandir($path);

			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (filetype($path . '/' . $object) == 'dir') {
						$this->deltree($path . '/' . $object);
					} else {
						unlink($path . '/' . $object);
					}
				}
			}
		}

		reset($objects);
		rmdir($path);
	}

	/**
	 * Generates a list of files in a given directory.
	 *
	 * @param string $path The full path to the directory
	 * @param string $relative The relative path (relative to the Themes directory)
	 * @return array An array of information about the files and directories found
	 */
	protected function getFileList($path, $relative)
	{
		// Is it even a directory?
		if (!is_dir($path)) {
			ErrorHandler::fatalLang('error_invalid_dir', 'critical');
		}

		$dir = dir($path);
		$entries = [];

		while ($entry = $dir->read()) {
			$entries[] = $entry;
		}

		$dir->close();

		natcasesort($entries);

		$list1 = [];
		$list2 = [];

		foreach ($entries as $entry) {
			// Skip all dot files, including .htaccess.
			if (substr($entry, 0, 1) == '.' || $entry == 'CVS') {
				continue;
			}

			if (is_dir($path . '/' . $entry)) {
				$list1[] = [
					'filename' => $entry,
					'is_writable' => is_writable($path . '/' . $entry),
					'is_directory' => true,
					'is_template' => false,
					'is_image' => false,
					'is_editable' => false,
					'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . $relative . $entry,
					'size' => '',
				];
			} else {
				$size = filesize($path . '/' . $entry);

				if ($size > 2048 || $size == 1024) {
					$size = Lang::numberFormat($size / 1024) . ' ' . Lang::$txt['themeadmin_edit_kilobytes'];
				} else {
					$size = Lang::numberFormat($size) . ' ' . Lang::$txt['themeadmin_edit_bytes'];
				}

				$list2[] = [
					'filename' => $entry,
					'is_writable' => is_writable($path . '/' . $entry),
					'is_directory' => false,
					'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
					'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
					'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
					'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
					'size' => $size,
					'last_modified' => Time::create('@' . filemtime($path . '/' . $entry))->format(),
				];
			}
		}

		return array_merge($list1, $list2);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Themes::exportStatic')) {
	Themes::exportStatic();
}

?>