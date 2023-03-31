<?php

/**
 * This file concerns itself almost completely with theme administration.
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
 *
 * @todo Update this for the new package manager?
 *
 * Creating and distributing theme packages:
 * 	There isn't that much required to package and distribute your own themes...
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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\PackageManager\SubsPackage;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Subaction handler - manages the action and delegates control to the proper
 * sub-action.
 * It loads both the Themes and Settings language files.
 * Checks the session by GET or POST to verify the sent data.
 * Requires the user not be a guest. (@todo what?)
 * Accessed via ?action=admin;area=theme.
 */
function ThemesMain()
{
	// PickTheme() has been migrated to SMF\Theme::pickTheme()
	if (isset($_GET['sa']) && $_GET['sa'] === 'pick')
	{
		redirectexit('action=theme;sa=pick' . (isset($_GET['u']) ? ';u=' . $_GET['u'] : ''));
	}
	// Everything in this file should be accessed via the ACP, not the 'theme' action.
	elseif ($_REQUEST['action'] === 'theme')
	{
		redirectexit('action=admin;area=theme;' . (isset($_GET['sa']) ? ';sa=' . $_GET['sa'] : '') . (isset($_GET['u']) ? ';u=' . $_GET['u'] : ''));
	}

	// Load the important language files...
	Lang::load('Themes');
	Lang::load('Settings');
	Lang::load('Drafts');

	// No funny business - guests only.
	is_not_guest();

	require_once(Config::$sourcedir . '/Subs-Themes.php');

	// Default the page title to Theme Administration by default.
	Utils::$context['page_title'] = Lang::$txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'reset' => 'SetThemeOptions',
		'options' => 'SetThemeOptions',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveTheme',
		'edit' => 'EditTheme',
		'enable' => 'EnableTheme',
		'copy' => 'CopyTemplate',
	);

	// @todo Layout Settings?  huh?
	if (!empty(Utils::$context['admin_menu_name']))
	{
		Utils::$context[Utils::$context['admin_menu_name']]['tab_data'] = array(
			'title' => Lang::$txt['themeadmin_title'],
			'description' => Lang::$txt['themeadmin_description'],
			'tabs' => array(
				'admin' => array(
					'description' => Lang::$txt['themeadmin_admin_desc'],
				),
				'list' => array(
					'description' => Lang::$txt['themeadmin_list_desc'],
				),
				'reset' => array(
					'description' => Lang::$txt['themeadmin_reset_desc'],
				),
				'edit' => array(
					'description' => Lang::$txt['themeadmin_edit_desc'],
				),
			),
		);
	}

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_themes', array(&$subActions));

	// Whatever you decide to do, clean the minify cache.
	CacheApi::put('minimized_css', null);

	// Follow the sa or just go to administration.
	if (isset($_GET['sa']) && !empty($subActions[$_GET['sa']]))
		call_helper($subActions[$_GET['sa']]);

	else
		call_helper($subActions['admin']);
}

/**
 * This function allows administration of themes and their settings,
 * as well as global theme settings.
 *  - sets the settings theme_allow, theme_guests, and knownThemes.
 *  - requires the admin_forum permission.
 *  - accessed with ?action=admin;area=theme;sa=admin.
 *
 * Uses Themes template
 * Uses Admin language file
 */
function ThemeAdmin()
{
	// Are handling any settings?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-tm');

		if (isset($_POST['options']['known_themes']))
			foreach ($_POST['options']['known_themes'] as $key => $id)
				$_POST['options']['known_themes'][$key] = (int) $id;

		else
			fatal_lang_error('themes_none_selectable', false);

		if (!in_array($_POST['options']['theme_guests'], $_POST['options']['known_themes']))
			fatal_lang_error('themes_default_selectable', false);

		// Commit the new settings.
		Config::updateModSettings(array(
			'theme_allow' => $_POST['options']['theme_allow'],
			'theme_guests' => $_POST['options']['theme_guests'],
			'knownThemes' => implode(',', $_POST['options']['known_themes']),
		));
		if ((int) $_POST['theme_reset'] == 0 || in_array($_POST['theme_reset'], $_POST['options']['known_themes']))
			User::updateMemberData(null, array('id_theme' => (int) $_POST['theme_reset']));

		redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=admin');
	}

	Lang::load('Admin');
	isAllowedTo('admin_forum');
	Theme::loadTemplate('Themes');

	// List all enabled themes.
	get_all_themes(true);

	// Can we create a new theme?
	Utils::$context['can_create_new'] = is_writable(Config::$boarddir . '/Themes');
	Utils::$context['new_theme_dir'] = substr(realpath(Config::$boarddir . '/Themes/default'), 0, -7);

	// Look for a non existent theme directory. (ie theme87.)
	$theme_dir = Config::$boarddir . '/Themes/theme';
	$i = 1;
	while (file_exists($theme_dir . $i))
		$i++;

	Utils::$context['new_theme_name'] = 'theme' . $i;

	// A bunch of tokens for a bunch of forms.
	createToken('admin-tm');
	createToken('admin-t-file');
	createToken('admin-t-copy');
	createToken('admin-t-dir');
}

/**
 * This function lists the available themes and provides an interface to reset
 * the paths of all the installed themes.
 */
function ThemeList()
{
	Lang::load('Admin');
	isAllowedTo('admin_forum');

	if (isset($_REQUEST['th']))
		return SetThemeSettings();

	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-tl');

		// Calling the almighty power of global vars!
		get_installed_themes();

		$setValues = array();
		foreach (Utils::$context['themes'] as $id => $theme)
		{
			if (file_exists($_POST['reset_dir'] . '/' . basename($theme['theme_dir'])))
			{
				$setValues[] = array($id, 0, 'theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['theme_dir'])));
				$setValues[] = array($id, 0, 'theme_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir']));
				$setValues[] = array($id, 0, 'images_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir']) . '/' . basename($theme['images_url']));
			}

			if (isset($theme['base_theme_dir']) && file_exists($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir'])))
			{
				$setValues[] = array($id, 0, 'base_theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir'])));
				$setValues[] = array($id, 0, 'base_theme_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir']));
				$setValues[] = array($id, 0, 'base_images_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir']) . '/' . basename($theme['base_images_url']));
			}

			CacheApi::put('theme_settings-' . $id, null, 90);
		}

		if (!empty($setValues))
		{
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}

	Theme::loadTemplate('Themes');

	// Get all installed themes.
	get_installed_themes();

	Utils::$context['reset_dir'] = realpath(Config::$boarddir . '/Themes');
	Utils::$context['reset_url'] = Config::$boardurl . '/Themes';

	Utils::$context['sub_template'] = 'list_themes';
	createToken('admin-tl');
	createToken('admin-tr', 'request');
	createToken('admin-tre', 'request');
}

/**
 * Administrative global settings.
 */
function SetThemeOptions()
{
	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

	isAllowedTo('admin_forum');

	if (empty($_GET['th']) && empty($_GET['id']))
	{
		$request = Db::$db->query('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_dir})
				AND id_member = {int:no_member}',
			array(
				'no_member' => 0,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			)
		);
		Utils::$context['themes'] = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (!isset(Utils::$context['themes'][$row['id_theme']]))
				Utils::$context['themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'num_default_options' => 0,
					'num_members' => 0,
				);
			Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);

		$request = Db::$db->query('', '
			SELECT id_theme, COUNT(*) AS value
			FROM {db_prefix}themes
			WHERE id_member = {int:guest_member}
			GROUP BY id_theme',
			array(
				'guest_member' => -1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['themes'][$row['id_theme']]['num_default_options'] = $row['value'];
		Db::$db->free_result($request);

		// Need to make sure we don't do custom fields.
		$request = Db::$db->query('', '
			SELECT col_name
			FROM {db_prefix}custom_fields',
			array(
			)
		);
		$customFields = array();
		while ($row = Db::$db->fetch_assoc($request))
			$customFields[] = $row['col_name'];
		Db::$db->free_result($request);
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		$request = Db::$db->query('themes_count', '
			SELECT COUNT(DISTINCT id_member) AS value, id_theme
			FROM {db_prefix}themes
			WHERE id_member > {int:no_member}
				' . $customFieldsQuery . '
			GROUP BY id_theme',
			array(
				'no_member' => 0,
				'custom_fields' => empty($customFields) ? array() : $customFields,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['themes'][$row['id_theme']]['num_members'] = $row['value'];
		Db::$db->free_result($request);

		// There has to be a Settings template!
		foreach (Utils::$context['themes'] as $k => $v)
			if (empty($v['theme_dir']) || (!file_exists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members'])))
				unset(Utils::$context['themes'][$k]);

		Theme::loadTemplate('Themes');
		Utils::$context['sub_template'] = 'reset_list';

		createToken('admin-stor', 'request');
		return;
	}

	// Submit?
	if (isset($_POST['submit']) && empty($_POST['who']))
	{
		checkSession();
		validateToken('admin-sto');

		if (empty($_POST['options']))
			$_POST['options'] = array();
		if (empty($_POST['default_options']))
			$_POST['default_options'] = array();

		// Set up the sql query.
		$setValues = array();

		foreach ($_POST['options'] as $opt => $val)
			$setValues[] = array(-1, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val);

		$old_settings = array();
		foreach ($_POST['default_options'] as $opt => $val)
		{
			$old_settings[] = $opt;

			$setValues[] = array(-1, 1, $opt, is_array($val) ? implode(',', $val) : $val);
		}

		// If we're actually inserting something..
		if (!empty($setValues))
		{
			// Are there options in non-default themes set that should be cleared?
			if (!empty($old_settings))
				Db::$db->query('', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme != {int:default_theme}
						AND id_member = {int:guest_member}
						AND variable IN ({array_string:old_settings})',
					array(
						'default_theme' => 1,
						'guest_member' => -1,
						'old_settings' => $old_settings,
					)
				);

			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		CacheApi::put('theme_settings-' . $_GET['th'], null, 90);
		CacheApi::put('theme_settings-1', null, 90);

		redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
	}
	elseif (isset($_POST['submit']) && $_POST['who'] == 1)
	{
		checkSession();
		validateToken('admin-sto');

		$_POST['options'] = empty($_POST['options']) ? array() : $_POST['options'];
		$_POST['options_master'] = empty($_POST['options_master']) ? array() : $_POST['options_master'];
		$_POST['default_options'] = empty($_POST['default_options']) ? array() : $_POST['default_options'];
		$_POST['default_options_master'] = empty($_POST['default_options_master']) ? array() : $_POST['default_options_master'];

		$old_settings = array();
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if ($_POST['default_options_master'][$opt] == 0)
				continue;
			elseif ($_POST['default_options_master'][$opt] == 1)
			{
				// Delete then insert for ease of database compatibility!
				Db::$db->query('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:default_theme}
						AND id_member > {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'default_theme' => 1,
						'no_member' => 0,
						'option' => $opt,
					)
				);
				Db::$db->query('substring', '
					INSERT INTO {db_prefix}themes
						(id_member, id_theme, variable, value)
					SELECT id_member, 1, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
					FROM {db_prefix}members',
					array(
						'option' => $opt,
						'value' => (is_array($val) ? implode(',', $val) : $val),
					)
				);

				$old_settings[] = $opt;
			}
			elseif ($_POST['default_options_master'][$opt] == 2)
			{
				Db::$db->query('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:option_name}
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'option_name' => $opt,
					)
				);
			}
		}

		// Delete options from other themes.
		if (!empty($old_settings))
			Db::$db->query('', '
				DELETE FROM {db_prefix}themes
				WHERE id_theme != {int:default_theme}
					AND id_member > {int:no_member}
					AND variable IN ({array_string:old_settings})',
				array(
					'default_theme' => 1,
					'no_member' => 0,
					'old_settings' => $old_settings,
				)
			);

		foreach ($_POST['options'] as $opt => $val)
		{
			if ($_POST['options_master'][$opt] == 0)
				continue;
			elseif ($_POST['options_master'][$opt] == 1)
			{
				// Delete then insert for ease of database compatibility - again!
				Db::$db->query('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:current_theme}
						AND id_member > {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'current_theme' => $_GET['th'],
						'no_member' => 0,
						'option' => $opt,
					)
				);
				Db::$db->query('substring', '
					INSERT INTO {db_prefix}themes
						(id_member, id_theme, variable, value)
					SELECT id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
					FROM {db_prefix}members',
					array(
						'current_theme' => $_GET['th'],
						'option' => $opt,
						'value' => (is_array($val) ? implode(',', $val) : $val),
					)
				);
			}
			elseif ($_POST['options_master'][$opt] == 2)
			{
				Db::$db->query('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:option}
						AND id_member > {int:no_member}
						AND id_theme = {int:current_theme}',
					array(
						'no_member' => 0,
						'current_theme' => $_GET['th'],
						'option' => $opt,
					)
				);
			}
		}

		redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
	}
	elseif (!empty($_GET['who']) && $_GET['who'] == 2)
	{
		checkSession('get');
		validateToken('admin-stor', 'request');

		// Don't delete custom fields!!
		if ($_GET['th'] == 1)
		{
			$request = Db::$db->query('', '
				SELECT col_name
				FROM {db_prefix}custom_fields',
				array(
				)
			);
			$customFields = array();
			while ($row = Db::$db->fetch_assoc($request))
				$customFields[] = $row['col_name'];
			Db::$db->free_result($request);
		}
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		Db::$db->query('', '
			DELETE FROM {db_prefix}themes
			WHERE id_member > {int:no_member}
				AND id_theme = {int:current_theme}
				' . $customFieldsQuery,
			array(
				'no_member' => 0,
				'current_theme' => $_GET['th'],
				'custom_fields' => empty($customFields) ? array() : $customFields,
			)
		);

		redirectexit('action=admin;area=theme;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=reset');
	}

	$old_id = Theme::$current->settings['theme_id'];
	$old_settings = Theme::$current->settings;

	Theme::load($_GET['th'], false);

	Lang::load('Profile');
	// @todo Should we just move these options so they are no longer theme dependent?
	Lang::load('PersonalMessage');

	// Let the theme take care of the settings.
	Theme::loadTemplate('Settings');
	Theme::loadSubTemplate('options');

	// Let mods hook into the theme options.
	call_integration_hook('integrate_theme_options');

	Utils::$context['sub_template'] = 'set_options';
	Utils::$context['page_title'] = Lang::$txt['theme_settings'];

	Utils::$context['options'] = Utils::$context['theme_options'];
	Utils::$context['theme_settings'] = Theme::$current->settings;

	if (empty($_REQUEST['who']))
	{
		$request = Db::$db->query('', '
			SELECT variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN (1, {int:current_theme})
				AND id_member = {int:guest_member}',
			array(
				'current_theme' => $_GET['th'],
				'guest_member' => -1,
			)
		);
		Utils::$context['theme_options'] = array();
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['theme_options'][$row['variable']] = $row['value'];
		Db::$db->free_result($request);

		Utils::$context['theme_options_reset'] = false;
	}
	else
	{
		Utils::$context['theme_options'] = array();
		Utils::$context['theme_options_reset'] = true;
	}

	foreach (Utils::$context['options'] as $i => $setting)
	{
		// Just skip separators
		if (!is_array($setting))
			continue;

		// Is this disabled?
		if (isset($setting['enabled']) && $setting['enabled'] === false)
		{
			unset(Utils::$context['options'][$i]);
			continue;
		}

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			Utils::$context['options'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			Utils::$context['options'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			Utils::$context['options'][$i]['type'] = 'text';

		if (isset($setting['options']))
			Utils::$context['options'][$i]['type'] = 'list';

		Utils::$context['options'][$i]['value'] = !isset(Utils::$context['theme_options'][$setting['id']]) ? '' : Utils::$context['theme_options'][$setting['id']];
	}

	// Restore the existing theme.
	Theme::load($old_id, false);
	Theme::$current->settings = $old_settings;

	Theme::loadTemplate('Themes');
	createToken('admin-sto');
}

/**
 * Administrative global settings.
 * - saves and requests global theme settings. (Theme::$current->settings)
 * - loads the Admin language file.
 * - calls ThemeAdmin() if no theme is specified. (the theme center.)
 * - requires admin_forum permission.
 * - accessed with ?action=admin;area=theme;sa=list&th=xx.
 */
function SetThemeSettings()
{
	if (empty($_GET['th']) && empty($_GET['id']))
		return ThemeAdmin();

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// Select the best fitting tab.
	Utils::$context[Utils::$context['admin_menu_name']]['current_subsection'] = 'list';

	Lang::load('Admin');
	isAllowedTo('admin_forum');

	// Validate inputs/user.
	if (empty($_GET['th']))
		fatal_lang_error('no_theme', false);

	// Fetch the smiley sets...
	$sets = explode(',', 'none,' . Config::$modSettings['smiley_sets_known']);
	$set_names = explode("\n", Lang::$txt['smileys_none'] . "\n" . Config::$modSettings['smiley_sets_names']);
	Utils::$context['smiley_sets'] = array(
		'' => Lang::$txt['smileys_no_default']
	);
	foreach ($sets as $i => $set)
		Utils::$context['smiley_sets'][$set] = Utils::htmlspecialchars($set_names[$i]);

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
	Theme::$current->settings['theme_variants'] = array();
	if (file_exists(Theme::$current->settings['theme_dir'] . '/index.template.php'))
	{
		$file_contents = implode('', file(Theme::$current->settings['theme_dir'] . '/index.template.php'));
		if (preg_match('~((?:SMF\\\\)?Theme::\$current(?:->|_)|\$)settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
			eval('use SMF\Theme; global $settings; ' . $matches[0]);
	}

	// Let mods hook into the theme settings.
	call_integration_hook('integrate_theme_settings');

	// Submitting!
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-sts');

		if (empty($_POST['options']))
			$_POST['options'] = array();
		if (empty($_POST['default_options']))
			$_POST['default_options'] = array();

		// Make sure items are cast correctly.
		foreach (Utils::$context['theme_settings'] as $item)
		{
			// Disregard this item if this is just a separator.
			if (!is_array($item))
				continue;

			foreach (array('options', 'default_options') as $option)
			{
				if (!isset($_POST[$option][$item['id']]))
					continue;
				// Checkbox.
				elseif (empty($item['type']))
					$_POST[$option][$item['id']] = $_POST[$option][$item['id']] ? 1 : 0;
				// Number
				elseif ($item['type'] == 'number')
					$_POST[$option][$item['id']] = (int) $_POST[$option][$item['id']];
			}
		}

		// Set up the sql query.
		$inserts = array();
		foreach ($_POST['options'] as $opt => $val)
			$inserts[] = array(0, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val);
		foreach ($_POST['default_options'] as $opt => $val)
			$inserts[] = array(0, 1, $opt, is_array($val) ? implode(',', $val) : $val);
		// If we're actually inserting something..
		if (!empty($inserts))
		{
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_member', 'id_theme', 'variable')
			);
		}

		CacheApi::put('theme_settings-' . $_GET['th'], null, 90);
		CacheApi::put('theme_settings-1', null, 90);

		// Invalidate the cache.
		Config::updateModSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=theme;sa=list;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}

	Utils::$context['sub_template'] = 'set_settings';
	Utils::$context['page_title'] = Lang::$txt['theme_settings'];

	foreach (Theme::$current->settings as $setting => $dummy)
	{
		if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
			Theme::$current->settings[$setting] = htmlspecialchars__recursive(Theme::$current->settings[$setting]);
	}

	Utils::$context['settings'] = Utils::$context['theme_settings'];
	Utils::$context['theme_settings'] = Theme::$current->settings;

	foreach (Utils::$context['settings'] as $i => $setting)
	{
		// Separators are dummies, so leave them alone.
		if (!is_array($setting))
			continue;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			Utils::$context['settings'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			Utils::$context['settings'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			Utils::$context['settings'][$i]['type'] = 'text';

		if (isset($setting['options']))
			Utils::$context['settings'][$i]['type'] = 'list';

		Utils::$context['settings'][$i]['value'] = !isset(Theme::$current->settings[$setting['id']]) ? '' : Theme::$current->settings[$setting['id']];
	}

	// Do we support variants?
	if (!empty(Theme::$current->settings['theme_variants']))
	{
		Utils::$context['theme_variants'] = array();
		foreach (Theme::$current->settings['theme_variants'] as $variant)
		{
			// Have any text, old chap?
			Utils::$context['theme_variants'][$variant] = array(
				'label' => isset(Lang::$txt['variant_' . $variant]) ? Lang::$txt['variant_' . $variant] : $variant,
				'thumbnail' => !file_exists(Theme::$current->settings['theme_dir'] . '/images/thumbnail.png') || file_exists(Theme::$current->settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? Theme::$current->settings['images_url'] . '/thumbnail_' . $variant . '.png' : (Theme::$current->settings['images_url'] . '/thumbnail.png'),
			);
		}
		Utils::$context['default_variant'] = !empty(Theme::$current->settings['default_variant']) && isset(Utils::$context['theme_variants'][Theme::$current->settings['default_variant']]) ? Theme::$current->settings['default_variant'] : Theme::$current->settings['theme_variants'][0];
	}

	// Restore the current theme.
	Theme::load($old_id, false);

	// Reinit just incase.
	Theme::loadSubTemplate('init', 'ignore');

	Theme::loadTemplate('Themes');

	// We like Kenny better than Token.
	createToken('admin-sts');
}

/**
 * Remove a theme from the database.
 * - removes an installed theme.
 * - requires an administrator.
 * - accessed with ?action=admin;area=theme;sa=remove.
 */
function RemoveTheme()
{
	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tr', 'request');

	// The theme's ID must be an integer.
	$themeID = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// You can't delete the default theme!
	if ($themeID == 1)
		fatal_lang_error('no_access', false);

	$theme_info = get_single_theme($themeID, array('theme_dir'));

	// Remove it from the DB.
	remove_theme($themeID);

	// And remove all its files and folders too.
	if (!empty($theme_info) && !empty($theme_info['theme_dir']))
		remove_dir($theme_info['theme_dir']);

	// Go back to the list page.
	redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';done=removing');
}

/**
 * Handles enabling/disabling a theme from the admin center
 */
function EnableTheme()
{
	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tre', 'request');

	// The theme's ID must be an string.
	$themeID = isset($_GET['th']) ? (string) trim($_GET['th']) : (string) trim($_GET['id']);

	// Get the current list.
	$enableThemes = explode(',', Config::$modSettings['enableThemes']);

	// Are we disabling it?
	if (isset($_GET['disabled']))
		$enableThemes = array_diff($enableThemes, array($themeID));

	// Nope? then enable it!
	else
		$enableThemes[] = (string) $themeID;

	// Update the setting.
	$enableThemes = strtr(implode(',', $enableThemes), array(',,' => ','));
	Config::updateModSettings(array('enableThemes' => $enableThemes));

	// Done!
	redirectexit('action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';done=' . (isset($_GET['disabled']) ? 'disabling' : 'enabling'));
}

/**
 * Installs new themes, calls the respective function according to the install type.
 * - puts themes in Config::$boardurl/Themes.
 * - assumes the gzip has a root directory in it. (ie default.)
 * Requires admin_forum.
 * Accessed with ?action=admin;area=theme;sa=install.
 */
function ThemeInstall()
{
	checkSession('request');
	isAllowedTo('admin_forum');

	// Make it easier to change the path and url.
	Utils::$context['themedir'] = Config::$boarddir . '/Themes';
	Utils::$context['themeurl'] = Config::$boardurl . '/Themes';

	Theme::loadTemplate('Themes');

	$subActions = array(
		'file' => 'InstallFile',
		'copy' => 'InstallCopy',
		'dir' => 'InstallDir',
	);

	// Is there a function to call?
	if (isset($_GET['do']) && !empty($_GET['do']) && isset($subActions[$_GET['do']]))
	{
		$action = Utils::htmlspecialchars(trim($_GET['do']));

		// Got any info from the specific form?
		if (!isset($_POST['save_' . $action]))
			fatal_lang_error('theme_install_no_action', false);

		validateToken('admin-t-' . $action);

		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable(Utils::$context['themedir']))
			fatal_lang_error('theme_install_write_error', 'critical');

		// Call the function and handle the result.
		$result = $subActions[$action]();

		// Everything went better than expected!
		if (!empty($result))
		{
			Utils::$context['sub_template'] = 'installed';
			Utils::$context['page_title'] = Lang::$txt['theme_installed'];
			Utils::$context['installed_theme'] = $result;
		}
	}

	// Nope, show a nice error.
	else
		fatal_lang_error('theme_install_no_action', false);
}

/**
 * Installs a theme from a theme package.
 *
 * Stores the theme files on a temp dir, on success it renames the dir to the new theme's name. Ends execution with fatal_lang_error() on any error.
 *
 * @return array The newly created theme's info.
 */
function InstallFile()
{
	// Set a temp dir for dumping all required files on it.
	$dirtemp = Utils::$context['themedir'] . '/temp';

	// Make sure the temp dir doesn't already exist
	if (file_exists($dirtemp))
		remove_dir($dirtemp);

	// Create the temp dir.
	mkdir($dirtemp, 0777);

	// Hopefully the temp directory is writable, or we might have a problem.
	if (!is_writable($dirtemp))
	{
		// Lets give it a try.
		smf_chmod($dirtemp, '0755');

		// How about now?
		if (!is_writable($dirtemp))
			fatal_lang_error('theme_install_write_error', 'critical');
	}

	// This happens when the admin session is gone and the user has to login again.
	if (!isset($_FILES) || !isset($_FILES['theme_gz']) || empty($_FILES['theme_gz']))
		redirectexit('action=admin;area=theme;sa=admin;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);

	// Another error check layer, something went wrong with the upload.
	if (isset($_FILES['theme_gz']['error']) && $_FILES['theme_gz']['error'] != 0)
		fatal_lang_error('theme_install_error_file_' . $_FILES['theme_gz']['error'], false);

	// Get the theme's name.
	$name = pathinfo($_FILES['theme_gz']['name'], PATHINFO_FILENAME);
	$name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/', '/\.tar$/'), array('_', '.', '', ''), $name);

	// Start setting some vars.
	Utils::$context['to_install'] = array(
		'theme_dir' => Utils::$context['themedir'] . '/' . $name,
		'theme_url' => Utils::$context['themeurl'] . '/' . $name,
		'images_url' => Utils::$context['themeurl'] . '/' . $name . '/images',
		'name' => $name,
	);

	// Extract the file on the proper themes dir.
	$extracted = SubsPackage::read_tgz_file($_FILES['theme_gz']['tmp_name'], $dirtemp, false, true);

	if ($extracted)
	{
		// Read its info form the XML file.
		$theme_info = get_theme_info($dirtemp);
		Utils::$context['to_install'] += $theme_info;

		// Install the theme. theme_install() will return the new installed ID.
		Utils::$context['to_install']['id'] = theme_install(Utils::$context['to_install']);

		// Rename the temp dir to the actual theme name.
		rename($dirtemp, Utils::$context['to_install']['theme_dir']);

		// return all the info.
		return Utils::$context['to_install'];
	}

	else
		fatal_lang_error('theme_install_error_title', false);
}

/**
 * Makes a copy from the default theme, assigns a name for it and installs it.
 *
 * Creates a new .xml file containing all the theme's info.
 *
 * @return array The newly created theme's info.
 */
function InstallCopy()
{
	// There's gotta be something to work with.
	if (!isset($_REQUEST['copy']) || empty($_REQUEST['copy']))
		fatal_lang_error('theme_install_error_title', false);

	// Get a cleaner version.
	$name = preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

	// Is there a theme already named like this?
	if (file_exists(Utils::$context['themedir'] . '/' . $name))
		fatal_lang_error('theme_install_already_dir', false);

	// This is a brand new theme so set all possible values.
	Utils::$context['to_install'] = array(
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
	);

	// Create the specific dir.
	umask(0);
	mkdir(Utils::$context['to_install']['theme_dir'], 0777);

	// Buy some time.
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Create subdirectories for css and javascript files.
	mkdir(Utils::$context['to_install']['theme_dir'] . '/css', 0777);
	mkdir(Utils::$context['to_install']['theme_dir'] . '/scripts', 0777);

	// Create subdirectory for language files
	mkdir(Utils::$context['to_install']['theme_dir'] . '/languages', 0777);

	// Copy over the default non-theme files.
	$to_copy = array(
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
	);

	foreach ($to_copy as $file)
	{
		copy(Theme::$current->settings['default_theme_dir'] . $file, Utils::$context['to_install']['theme_dir'] . $file);
		smf_chmod(Utils::$context['to_install']['theme_dir'] . $file, 0777);
	}

	// And now the entire images directory!
	SubsPackage::copytree(Theme::$current->settings['default_theme_dir'] . '/images', Utils::$context['to_install']['theme_dir'] . '/images');
	SubsPackage::package_flush_cache();

	// Any data from the default theme that we want?
	foreach (get_single_theme(1, array('theme_layers', 'theme_templates')) as $variable => $value)
		if ($variable == 'theme_templates' || $variable == 'theme_layers')
			Utils::$context['to_install'][$variable] = $value;

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
	if ($fp)
	{
		fwrite($fp, $xml_info);
		fclose($fp);
	}

	// Install the theme. theme_install() will take care of possible errors.
	Utils::$context['to_install']['id'] = theme_install(Utils::$context['to_install']);

	// return the info.
	return Utils::$context['to_install'];
}

/**
 * Install a theme from a specific dir
 *
 * Assumes the dir is located on the main Themes dir. Ends execution with fatal_lang_error() on any error.
 *
 * @return array The newly created theme's info.
 */
function InstallDir()
{
	// Cannot use the theme dir as a theme dir.
	if (!isset($_REQUEST['theme_dir']) || empty($_REQUEST['theme_dir']) || rtrim(realpath($_REQUEST['theme_dir']), '/\\') == realpath(Utils::$context['themedir']))
		fatal_lang_error('theme_install_invalid_dir', false);

	// Check is there is "something" on the dir.
	elseif (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme_info.xml'))
		fatal_lang_error('theme_install_error', false);

	$name = basename($_REQUEST['theme_dir']);
	$name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $name);

	// All good! set some needed vars.
	Utils::$context['to_install'] = array(
		'theme_dir' => $_REQUEST['theme_dir'],
		'theme_url' => Utils::$context['themeurl'] . '/' . $name,
		'name' => $name,
		'images_url' => Utils::$context['themeurl'] . '/' . $name . '/images',
	);

	// Read its info form the XML file.
	$theme_info = get_theme_info(Utils::$context['to_install']['theme_dir']);
	Utils::$context['to_install'] += $theme_info;

	// Install the theme. theme_install() will take care of possible errors.
	Utils::$context['to_install']['id'] = theme_install(Utils::$context['to_install']);

	// return the info.
	return Utils::$context['to_install'];
}

/**
 * Shows an interface for editing the templates.
 * - uses the Themes template and edit_template/edit_style sub template.
 * - accessed via ?action=admin;area=theme;sa=edit
 */
function EditTheme()
{
	// @todo Should this be removed?
	if (isset($_REQUEST['preview']))
		die('die() with fire');

	isAllowedTo('admin_forum');
	Theme::loadTemplate('Themes');

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) @$_GET['id'];

	if (empty($_GET['th']))
	{
		get_installed_themes();

		foreach (Utils::$context['themes'] as $key => $theme)
		{
			// There has to be a Settings template!
			if (!file_exists($theme['theme_dir'] . '/index.template.php') && !file_exists($theme['theme_dir'] . '/css/index.css'))
				unset(Utils::$context['themes'][$key]);

			else
				Utils::$context['themes'][$key]['can_edit_style'] = file_exists($theme['theme_dir'] . '/css/index.css');
		}

		Utils::$context['sub_template'] = 'edit_list';

		return 'no_themes';
	}

	Utils::$context['session_error'] = false;

	// Get the directory of the theme we are editing.
	$currentTheme = get_single_theme($_GET['th']);
	Utils::$context['theme_id'] = $currentTheme['id'];
	Utils::$context['browse_title'] = sprintf(Lang::$txt['themeadmin_browsing_theme'], $currentTheme['name']);

	if (!file_exists($currentTheme['theme_dir'] . '/index.template.php') && !file_exists($currentTheme['theme_dir'] . '/css/index.css'))
		fatal_lang_error('theme_edit_missing', false);

	if (!isset($_REQUEST['filename']))
	{
		if (isset($_GET['directory']))
		{
			if (substr($_GET['directory'], 0, 1) == '.')
				$_GET['directory'] = '';
			else
			{
				$_GET['directory'] = preg_replace(array('~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'), array('', '/', '/'), $_GET['directory']);

				$temp = realpath($currentTheme['theme_dir'] . '/' . $_GET['directory']);
				if (empty($temp) || substr($temp, 0, strlen(realpath($currentTheme['theme_dir']))) != realpath($currentTheme['theme_dir']))
					$_GET['directory'] = '';
			}
		}

		if (isset($_GET['directory']) && $_GET['directory'] != '')
		{
			Utils::$context['theme_files'] = get_file_listing($currentTheme['theme_dir'] . '/' . $_GET['directory'], $_GET['directory'] . '/');

			$temp = dirname($_GET['directory']);
			array_unshift(Utils::$context['theme_files'], array(
				'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
				'is_writable' => is_writable($currentTheme['theme_dir'] . '/' . $temp),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . $temp,
				'size' => '',
			));
		}
		else
			Utils::$context['theme_files'] = get_file_listing($currentTheme['theme_dir'], '');

		// Do not list minified_ files
		foreach (Utils::$context['theme_files'] as $key => $file)
		{
			if (strpos($file['filename'], 'minified_') !== false)
				unset(Utils::$context['theme_files'][$key]);
		}

		Utils::$context['sub_template'] = 'edit_browse';

		return;
	}
	else
	{
		if (substr($_REQUEST['filename'], 0, 1) == '.')
			$_REQUEST['filename'] = '';
		else
		{
			$_REQUEST['filename'] = preg_replace(array('~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'), array('', '/', '/'), $_REQUEST['filename']);

			$temp = realpath($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
			if (empty($temp) || substr($temp, 0, strlen(realpath($currentTheme['theme_dir']))) != realpath($currentTheme['theme_dir']))
				$_REQUEST['filename'] = '';
		}

		if (empty($_REQUEST['filename']))
			fatal_lang_error('theme_edit_missing', false);
	}

	if (isset($_POST['save']))
	{
		if (checkSession('post', '', false) == '' && validateToken('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']), 'post', false) == true)
		{
			if (is_array($_POST['entire_file']))
				$_POST['entire_file'] = implode("\n", $_POST['entire_file']);

			$_POST['entire_file'] = rtrim(strtr($_POST['entire_file'], array("\r" => '', '   ' => "\t")));

			// Check for a parse error!
			if (substr($_REQUEST['filename'], -13) == '.template.php' && is_writable($currentTheme['theme_dir']) && ini_get('display_errors'))
			{
				Config::safeFileWrite($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php', $_POST['entire_file']);

				$error = @file_get_contents($currentTheme['theme_url'] . '/tmp_' . session_id() . '.php');
				if (preg_match('~ <b>(\d+)</b><br( /)?' . '>$~i', $error) != 0)
					$error_file = $currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php';
				else
					unlink($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php');
			}

			if (!isset($error_file))
			{
				Config::safeFileWrite($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], $_POST['entire_file']);

				// Nuke any minified files and update Config::$modSettings['browser_cache']
				Theme::deleteAllMinified();

				redirectexit('action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . dirname($_REQUEST['filename']));
			}
		}
		// Session timed out.
		else
		{
			Lang::load('Errors');

			Utils::$context['session_error'] = true;
			Utils::$context['sub_template'] = 'edit_file';

			// Recycle the submitted data.
			if (is_array($_POST['entire_file']))
				Utils::$context['entire_file'] = Utils::htmlspecialchars(implode("\n", $_POST['entire_file']));
			else
				Utils::$context['entire_file'] = Utils::htmlspecialchars($_POST['entire_file']);

			Utils::$context['edit_filename'] = Utils::htmlspecialchars($_POST['filename']);

			// You were able to submit it, so it's reasonable to assume you are allowed to save.
			Utils::$context['allow_save'] = true;

			// Re-create the token so that it can be used
			createToken('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));

			return;
		}
	}

	Utils::$context['allow_save'] = is_writable($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
	Utils::$context['allow_save_filename'] = strtr($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], array(Config::$boarddir => '...'));
	Utils::$context['edit_filename'] = Utils::htmlspecialchars($_REQUEST['filename']);

	if (substr($_REQUEST['filename'], -4) == '.css')
	{
		Utils::$context['sub_template'] = 'edit_style';

		Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
	elseif (substr($_REQUEST['filename'], -13) == '.template.php')
	{
		Utils::$context['sub_template'] = 'edit_template';

		if (!isset($error_file))
			$file_data = file($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
		else
		{
			if (preg_match('~(<b>.+?</b>:.+?<b>).+?(</b>.+?<b>\d+</b>)<br( /)?' . '>$~i', $error, $match) != 0)
				Utils::$context['parse_error'] = $match[1] . $_REQUEST['filename'] . $match[2];
			$file_data = file($error_file);
			unlink($error_file);
		}

		$j = 0;
		Utils::$context['file_parts'] = array(array('lines' => 0, 'line' => 1, 'data' => ''));
		for ($i = 0, $n = count($file_data); $i < $n; $i++)
		{
			if (isset($file_data[$i + 1]) && substr($file_data[$i + 1], 0, 9) == 'function ')
			{
				// Try to format the functions a little nicer...
				Utils::$context['file_parts'][$j]['data'] = trim(Utils::$context['file_parts'][$j]['data']) . "\n";

				if (empty(Utils::$context['file_parts'][$j]['lines']))
					unset(Utils::$context['file_parts'][$j]);
				Utils::$context['file_parts'][++$j] = array('lines' => 0, 'line' => $i + 1, 'data' => '');
			}

			Utils::$context['file_parts'][$j]['lines']++;
			Utils::$context['file_parts'][$j]['data'] .= Utils::htmlspecialchars(strtr($file_data[$i], array("\t" => '   ')));
		}

		Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(implode('', $file_data), array("\t" => '   ')));
	}
	else
	{
		Utils::$context['sub_template'] = 'edit_file';

		Utils::$context['entire_file'] = Utils::htmlspecialchars(strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}

	// Create a special token to allow editing of multiple files.
	createToken('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));
}

/**
 * Makes a copy of a template file in a new location
 *
 * @uses template_copy_template()
 */
function CopyTemplate()
{
	isAllowedTo('admin_forum');
	Theme::loadTemplate('Themes');

	Utils::$context[Utils::$context['admin_menu_name']]['current_subsection'] = 'edit';

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	if (empty($_GET['th']))
		fatal_lang_error('theme_install_invalid_id');

	// Get the theme info.
	$theme = get_single_theme($_GET['th']);
	Utils::$context['theme_id'] = $theme['id'];

	if (isset($_REQUEST['template']) && preg_match('~[\./\\\\:\0]~', $_REQUEST['template']) == 0)
	{
		if (file_exists(Theme::$current->settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = Theme::$current->settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php';

		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme['theme_dir'] . '/' . $_REQUEST['template'] . '.template.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . Utils::$context['theme_id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=copy');
	}
	elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^\./\\\\:\0]\.[^\./\\\\:\0]$~', $_REQUEST['lang_file']) != 0)
	{
		if (file_exists(Theme::$current->settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php'))
			$filename = Theme::$current->settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php';

		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme['theme_dir'] . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . Utils::$context['theme_id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=copy');
	}

	$templates = array();
	$lang_files = array();

	$dir = dir(Theme::$current->settings['default_theme_dir']);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php')
			$templates[] = substr($entry, 0, -13);
	}
	$dir->close();

	$dir = dir(Theme::$current->settings['default_theme_dir'] . '/languages');
	while ($entry = $dir->read())
	{
		if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches))
			$lang_files[] = $matches[1];
	}
	$dir->close();

	natcasesort($templates);
	natcasesort($lang_files);

	Utils::$context['available_templates'] = array();
	foreach ($templates as $template)
		Utils::$context['available_templates'][$template] = array(
			'filename' => $template . '.template.php',
			'value' => $template,
			'already_exists' => false,
			'can_copy' => is_writable($theme['theme_dir']),
		);
	Utils::$context['available_language_files'] = array();
	foreach ($lang_files as $file)
		Utils::$context['available_language_files'][$file] = array(
			'filename' => $file . '.php',
			'value' => $file,
			'already_exists' => false,
			'can_copy' => file_exists($theme['theme_dir'] . '/languages') ? is_writable($theme['theme_dir'] . '/languages') : is_writable($theme['theme_dir']),
		);

	$dir = dir($theme['theme_dir']);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php' && isset(Utils::$context['available_templates'][substr($entry, 0, -13)]))
		{
			Utils::$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;
			Utils::$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme['theme_dir'] . '/' . $entry);
		}
	}
	$dir->close();

	if (file_exists($theme['theme_dir'] . '/languages'))
	{
		$dir = dir($theme['theme_dir'] . '/languages');
		while ($entry = $dir->read())
		{
			if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && isset(Utils::$context['available_language_files'][$matches[1]]))
			{
				Utils::$context['available_language_files'][$matches[1]]['already_exists'] = true;
				Utils::$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme['theme_dir'] . '/languages/' . $entry);
			}
		}
		$dir->close();
	}

	Utils::$context['sub_template'] = 'copy_template';
}

?>