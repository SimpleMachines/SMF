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
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

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
	global $txt, $context, $sourcedir;

	// Load the important language files...
	loadLanguage('Themes');
	loadLanguage('Settings');
	loadLanguage('Drafts');

	// No funny business - guests only.
	is_not_guest();

	require_once($sourcedir . '/Subs-Themes.php');

	// Default the page title to Theme Administration by default.
	$context['page_title'] = $txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'reset' => 'SetThemeOptions',
		'options' => 'SetThemeOptions',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveTheme',
		'pick' => 'PickTheme',
		'edit' => 'EditTheme',
		'enable' => 'EnableTheme',
		'copy' => 'CopyTemplate',
	);

	// @todo Layout Settings?  huh?
	if (!empty($context['admin_menu_name']))
	{
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['themeadmin_title'],
			'help' => 'themes',
			'description' => $txt['themeadmin_description'],
			'tabs' => array(
				'admin' => array(
					'description' => $txt['themeadmin_admin_desc'],
				),
				'list' => array(
					'description' => $txt['themeadmin_list_desc'],
				),
				'reset' => array(
					'description' => $txt['themeadmin_reset_desc'],
				),
				'edit' => array(
					'description' => $txt['themeadmin_edit_desc'],
				),
			),
		);
	}

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_themes', array(&$subActions));

	// Whatever you decide to do, clean the minify cache.
	cache_put_data('minimized_css', null);

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
 * @uses Themes template
 * @uses Admin language file
 */
function ThemeAdmin()
{
	global $context, $boarddir;

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
		updateSettings(array(
			'theme_allow' => $_POST['options']['theme_allow'],
			'theme_guests' => $_POST['options']['theme_guests'],
			'knownThemes' => implode(',', $_POST['options']['known_themes']),
		));
		if ((int) $_POST['theme_reset'] == 0 || in_array($_POST['theme_reset'], $_POST['options']['known_themes']))
			updateMemberData(null, array('id_theme' => (int) $_POST['theme_reset']));

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=admin');
	}

	loadLanguage('Admin');
	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	// List all installed and enabled themes.
	get_all_themes(true);

	// Can we create a new theme?
	$context['can_create_new'] = is_writable($boarddir . '/Themes');
	$context['new_theme_dir'] = substr(realpath($boarddir . '/Themes/default'), 0, -7);

	// Look for a non existent theme directory. (ie theme87.)
	$theme_dir = $boarddir . '/Themes/theme';
	$i = 1;
	while (file_exists($theme_dir . $i))
		$i++;

	$context['new_theme_name'] = 'theme' . $i;

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
	global $context, $boarddir, $boardurl, $smcFunc;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	if (isset($_REQUEST['th']))
		return SetThemeSettings();

	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-tl');

		// Calling the almighty power of global vars!
		get_all_themes(false);

		$setValues = array();
		foreach ($context['themes'] as $id => $theme)
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

			cache_put_data('theme_settings-' . $id, null, 90);
		}

		if (!empty($setValues))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
	}

	loadTemplate('Themes');

	// Get all installed themes.
	get_all_themes(false);

	$context['reset_dir'] = realpath($boarddir . '/Themes');
	$context['reset_url'] = $boardurl . '/Themes';

	$context['sub_template'] = 'list_themes';
	createToken('admin-tl');
	createToken('admin-tr', 'request');
	createToken('admin-tre', 'request');
}

/**
 * Administrative global settings.
 */
function SetThemeOptions()
{
	global $txt, $context, $settings, $modSettings, $smcFunc;

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

	isAllowedTo('admin_forum');

	if (empty($_GET['th']) && empty($_GET['id']))
	{
		$request = $smcFunc['db_query']('', '
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
		$context['themes'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($context['themes'][$row['id_theme']]))
				$context['themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'num_default_options' => 0,
					'num_members' => 0,
				);
			$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_theme, COUNT(*) AS value
			FROM {db_prefix}themes
			WHERE id_member = {int:guest_member}
			GROUP BY id_theme',
			array(
				'guest_member' => -1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['themes'][$row['id_theme']]['num_default_options'] = $row['value'];
		$smcFunc['db_free_result']($request);

		// Need to make sure we don't do custom fields.
		$request = $smcFunc['db_query']('', '
			SELECT col_name
			FROM {db_prefix}custom_fields',
			array(
			)
		);
		$customFields = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$customFields[] = $row['col_name'];
		$smcFunc['db_free_result']($request);
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		$request = $smcFunc['db_query']('themes_count', '
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
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['themes'][$row['id_theme']]['num_members'] = $row['value'];
		$smcFunc['db_free_result']($request);

		// There has to be a Settings template!
		foreach ($context['themes'] as $k => $v)
			if (empty($v['theme_dir']) || (!file_exists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members'])))
				unset($context['themes'][$k]);

		loadTemplate('Themes');
		$context['sub_template'] = 'reset_list';

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
				$smcFunc['db_query']('', '
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

			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		cache_put_data('theme_settings-1', null, 90);

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
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
				$smcFunc['db_query']('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:default_theme}
						AND id_member != {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'default_theme' => 1,
						'no_member' => 0,
						'option' => $opt,
					)
				);
				$smcFunc['db_query']('substring', '
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
				$smcFunc['db_query']('', '
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
			$smcFunc['db_query']('', '
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
				$smcFunc['db_query']('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:current_theme}
						AND id_member != {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'current_theme' => $_GET['th'],
						'no_member' => 0,
						'option' => $opt,
					)
				);
				$smcFunc['db_query']('substring', '
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
				$smcFunc['db_query']('', '
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

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
	}
	elseif (!empty($_GET['who']) && $_GET['who'] == 2)
	{
		checkSession('get');
		validateToken('admin-stor', 'request');

		// Don't delete custom fields!!
		if ($_GET['th'] == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT col_name
				FROM {db_prefix}custom_fields',
				array(
				)
			);
			$customFields = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$customFields[] = $row['col_name'];
			$smcFunc['db_free_result']($request);
		}
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		$smcFunc['db_query']('', '
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

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
	}

	$old_id = $settings['theme_id'];
	$old_settings = $settings;

	loadTheme($_GET['th'], false);

	loadLanguage('Profile');
	// @todo Should we just move these options so they are no longer theme dependant?
	loadLanguage('PersonalMessage');

	// Let the theme take care of the settings.
	loadTemplate('Settings');
	loadSubTemplate('options');

	// Let mods hook into the theme options.
	call_integration_hook('integrate_theme_options');

	$context['sub_template'] = 'set_options';
	$context['page_title'] = $txt['theme_settings'];

	$context['options'] = $context['theme_options'];
	$context['theme_settings'] = $settings;

	if (empty($_REQUEST['who']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN (1, {int:current_theme})
				AND id_member = {int:guest_member}',
			array(
				'current_theme' => $_GET['th'],
				'guest_member' => -1,
			)
		);
		$context['theme_options'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['theme_options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);

		$context['theme_options_reset'] = false;
	}
	else
	{
		$context['theme_options'] = array();
		$context['theme_options_reset'] = true;
	}

	foreach ($context['options'] as $i => $setting)
	{
		// Just skip separators
		if (!is_array($setting))
			continue;

		// Is this disabled?
		if (isset($setting['enabled']) && $setting['enabled'] === false)
		{
			unset($context['options'][$i]);
			continue;
		}

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['options'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['options'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['options'][$i]['type'] = 'text';

		if (isset($setting['options']))
			$context['options'][$i]['type'] = 'list';

		$context['options'][$i]['value'] = !isset($context['theme_options'][$setting['id']]) ? '' : $context['theme_options'][$setting['id']];
	}

	// Restore the existing theme.
	loadTheme($old_id, false);
	$settings = $old_settings;

	loadTemplate('Themes');
	createToken('admin-sto');
}

/**
 * Administrative global settings.
 * - saves and requests global theme settings. ($settings)
 * - loads the Admin language file.
 * - calls ThemeAdmin() if no theme is specified. (the theme center.)
 * - requires admin_forum permission.
 * - accessed with ?action=admin;area=theme;sa=list&th=xx.
 */
function SetThemeSettings()
{
	global $txt, $context, $settings, $modSettings, $smcFunc;

	if (empty($_GET['th']) && empty($_GET['id']))
		return ThemeAdmin();

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// Select the best fitting tab.
	$context[$context['admin_menu_name']]['current_subsection'] = 'list';

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// Validate inputs/user.
	if (empty($_GET['th']))
		fatal_lang_error('no_theme', false);

	// Fetch the smiley sets...
	$sets = explode(',', 'none,' . $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $txt['smileys_none'] . "\n" . $modSettings['smiley_sets_names']);
	$context['smiley_sets'] = array(
		'' => $txt['smileys_no_default']
	);
	foreach ($sets as $i => $set)
		$context['smiley_sets'][$set] = $smcFunc['htmlspecialchars']($set_names[$i]);

	$old_id = $settings['theme_id'];
	$old_settings = $settings;

	loadTheme($_GET['th'], false);

	// Sadly we really do need to init the template.
	loadSubTemplate('init', 'ignore');

	// Also load the actual themes language file - in case of special settings.
	loadLanguage('Settings', '', true, true);

	// And the custom language strings...
	loadLanguage('ThemeStrings', '', false, true);

	// Let the theme take care of the settings.
	loadTemplate('Settings');
	loadSubTemplate('settings');

	// Load the variants separately...
	$settings['theme_variants'] = array();
	if (file_exists($settings['theme_dir'] . '/index.template.php'))
	{
		$file_contents = implode('', file($settings['theme_dir'] . '/index.template.php'));
		if (preg_match('~\$settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
			eval('global $settings;' . $matches[0]);
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
		foreach ($context['theme_settings'] as $item)
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
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_member', 'id_theme', 'variable')
			);
		}

		cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		cache_put_data('theme_settings-1', null, 90);

		// Invalidate the cache.
		updateSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=theme;sa=list;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['sub_template'] = 'set_settings';
	$context['page_title'] = $txt['theme_settings'];

	foreach ($settings as $setting => $dummy)
	{
		if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
			$settings[$setting] = htmlspecialchars__recursive($settings[$setting]);
	}

	$context['settings'] = $context['theme_settings'];
	$context['theme_settings'] = $settings;

	foreach ($context['settings'] as $i => $setting)
	{
		// Separators are dummies, so leave them alone.
		if (!is_array($setting))
			continue;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['settings'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['settings'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['settings'][$i]['type'] = 'text';

		if (isset($setting['options']))
			$context['settings'][$i]['type'] = 'list';

		$context['settings'][$i]['value'] = !isset($settings[$setting['id']]) ? '' : $settings[$setting['id']];
	}

	// Do we support variants?
	if (!empty($settings['theme_variants']))
	{
		$context['theme_variants'] = array();
		foreach ($settings['theme_variants'] as $variant)
		{
			// Have any text, old chap?
			$context['theme_variants'][$variant] = array(
				'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
				'thumbnail' => !file_exists($settings['theme_dir'] . '/images/thumbnail.png') || file_exists($settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $settings['images_url'] . '/thumbnail_' . $variant . '.png' : ($settings['images_url'] . '/thumbnail.png'),
			);
		}
		$context['default_variant'] = !empty($settings['default_variant']) && isset($context['theme_variants'][$settings['default_variant']]) ? $settings['default_variant'] : $settings['theme_variants'][0];
	}

	// Restore the current theme.
	loadTheme($old_id, false);

	// Reinit just incase.
	loadSubTemplate('init', 'ignore');

	$settings = $old_settings;

	loadTemplate('Themes');

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
	global $context;

	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tr', 'request');

	// The theme's ID must be an integer.
	$themeID = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// You can't delete the default theme!
	if ($themeID == 1)
		fatal_lang_error('no_access', false);

	$theme_info = get_single_theme($themeID);

	// Remove it from the DB.
	remove_theme($themeID);

	// And remove all its files and folders too.
	if (!empty($theme_info) && !empty($theme_info['theme_dir']))
		remove_dir($theme_info['theme_dir']);

	// Go back to the list page.
	redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id'] . ';done=removing');
}

/**
 * Handles enabling/disabling a theme from the admin center
 */
function EnableTheme()
{
	global $modSettings, $context;

	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tre', 'request');

	// The theme's ID must be an string.
	$themeID = isset($_GET['th']) ? (string) trim($_GET['th']) : (string) trim($_GET['id']);

	// Get the current list.
	$enableThemes = explode(',', $modSettings['enableThemes']);

	// Are we disabling it?
	if (isset($_GET['disabled']))
		$enableThemes = array_diff($enableThemes, array($themeID));

	// Nope? then enable it!
	else
		$enableThemes[] = (string) $themeID;

	// Update the setting.
	$enableThemes = strtr(implode(',', $enableThemes), array(',,' => ','));
	updateSettings(array('enableThemes' => $enableThemes));

	// Done!
	redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id'] . ';done=' . (isset($_GET['disabled']) ? 'disabling' : 'enabling'));
}

/**
 * Choose a theme from a list.
 * allows an user or administrator to pick a new theme with an interface.
 * - can edit everyone's (u = 0), guests' (u = -1), or a specific user's.
 * - uses the Themes template. (pick sub template.)
 * - accessed with ?action=admin;area=theme;sa=pick.
 *
 * @todo thought so... Might be better to split this file in ManageThemes and Themes,
 * with centralized admin permissions on ManageThemes.
 */
function PickTheme()
{
	global $txt, $context, $modSettings, $user_info, $language, $smcFunc, $settings, $scripturl;

	loadLanguage('Profile');
	loadTemplate('Themes');

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=theme;sa=pick;u=' . (!empty($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0),
		'name' => $txt['theme_pick'],
	);
	$context['default_theme_id'] = $modSettings['theme_default'];

	$_SESSION['id_theme'] = 0;

	if (isset($_GET['id']))
		$_GET['th'] = $_GET['id'];

	// Saving a variant cause JS doesn't work - pretend it did ;)
	if (isset($_POST['save']))
	{
		// Which theme?
		foreach ($_POST['save'] as $k => $v)
			$_GET['th'] = (int) $k;

		if (isset($_POST['vrt'][$k]))
			$_GET['vrt'] = $_POST['vrt'][$k];
	}

	// Have we made a decision, or are we just browsing?
	if (isset($_GET['th']))
	{
		checkSession('get');

		$_GET['th'] = (int) $_GET['th'];

		// Save for this user.
		if (!isset($_REQUEST['u']) || !allowedTo('admin_forum') || (!empty($_REQUEST['u']) && $_REQUEST['u'] == $user_info['id']))
		{
			updateMemberData($user_info['id'], array('id_theme' => (int) $_GET['th']));

			// A variants to save for the user?
			if (!empty($_GET['vrt']))
			{
				$smcFunc['db_insert']('replace',
					'{db_prefix}themes',
					array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
					array($_GET['th'], $user_info['id'], 'theme_variant', $_GET['vrt']),
					array('id_theme', 'id_member', 'variable')
				);
				cache_put_data('theme_settings-' . $_GET['th'] . ':' . $user_info['id'], null, 90);

				$_SESSION['id_variant'] = 0;
			}

			redirectexit('action=profile;area=theme');
		}

		// If changing members or guests - and there's a variant - assume changing default variant.
		if (!empty($_GET['vrt']) && ($_REQUEST['u'] == '0' || $_REQUEST['u'] == '-1'))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				array($_GET['th'], 0, 'default_variant', $_GET['vrt']),
				array('id_theme', 'id_member', 'variable')
			);

			// Make it obvious that it's changed
			cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		}

		// For everyone.
		if ($_REQUEST['u'] == '0')
		{
			updateMemberData(null, array('id_theme' => (int) $_GET['th']));

			// Remove any custom variants.
			if (!empty($_GET['vrt']))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:current_theme}
						AND variable = {string:theme_variant}',
					array(
						'current_theme' => (int) $_GET['th'],
						'theme_variant' => 'theme_variant',
					)
				);
			}

			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
		}
		// Change the default/guest theme.
		elseif ($_REQUEST['u'] == '-1')
		{
			updateSettings(array('theme_guests' => (int) $_GET['th']));

			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
		}
		// Change a specific member's theme.
		else
		{
			updateMemberData((int) $_REQUEST['u'], array('id_theme' => (int) $_GET['th']));

			if (!empty($_GET['vrt']) && $_GET['th'] != 0)
			{
				$smcFunc['db_insert']('replace',
					'{db_prefix}themes',
					array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
					array($_GET['th'], (int) $_REQUEST['u'], 'theme_variant', $_GET['vrt']),
					array('id_theme', 'id_member', 'variable')
				);
				cache_put_data('theme_settings-' . $_GET['th'] . ':' . (int) $_REQUEST['u'], null, 90);

				if ($user_info['id'] == $_REQUEST['u'])
					$_SESSION['id_variant'] = 0;
			}
			elseif ($_GET['th'] == 0)
			{
				// Remove any custom variants.
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE
						variable = {string:theme_variant}
						AND id_member = {int:id_member}',
					array(
						'theme_variant' => 'theme_variant',
						'id_member' => (int) $_REQUEST['u'],
					)
				);
			}

			redirectexit('action=profile;u=' . (int) $_REQUEST['u'] . ';area=theme');
		}
	}

	// Figure out who the member of the minute is, and what theme they've chosen.
	if (!isset($_REQUEST['u']) || !allowedTo('admin_forum'))
	{
		$context['current_member'] = $user_info['id'];
		$context['current_theme'] = $user_info['theme'];
	}
	// Everyone can't chose just one.
	elseif ($_REQUEST['u'] == '0')
	{
		$context['current_member'] = 0;
		$context['current_theme'] = 0;
	}
	// Guests and such...
	elseif ($_REQUEST['u'] == '-1')
	{
		$context['current_member'] = -1;
		$context['current_theme'] = $modSettings['theme_guests'];
	}
	// Someones else :P.
	else
	{
		$context['current_member'] = (int) $_REQUEST['u'];

		$request = $smcFunc['db_query']('', '
			SELECT id_theme
			FROM {db_prefix}members
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $context['current_member'],
			)
		);
		list ($context['current_theme']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Get the theme name and descriptions.
	$context['available_themes'] = array();
	if (!empty($modSettings['knownThemes']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_url}, {string:theme_dir}, {string:images_url}, {string:disable_user_variant})' . (!allowedTo('admin_forum') ? '
				AND id_theme IN ({array_string:known_themes})' : '') . '
				AND id_theme != {int:default_theme}
				AND id_member = {int:no_member}
				AND id_theme IN ({array_string:enable_themes})',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'disable_user_variant' => 'disable_user_variant',
				'known_themes' => explode(',', $modSettings['knownThemes']),
				'enable_themes' => explode(',', $modSettings['enableThemes']),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($context['available_themes'][$row['id_theme']]))
				$context['available_themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'selected' => $context['current_theme'] == $row['id_theme'],
					'num_users' => 0
				);
			$context['available_themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);
	}

	// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
	if (!isset($context['available_themes'][$modSettings['theme_guests']]))
	{
		$context['available_themes'][0] = array(
			'num_users' => 0
		);
		$guest_theme = 0;
	}
	else
		$guest_theme = $modSettings['theme_guests'];

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, COUNT(*) AS the_count
		FROM {db_prefix}members
		GROUP BY id_theme
		ORDER BY id_theme DESC',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Figure out which theme it is they are REALLY using.
		if (!empty($modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',', $modSettings['knownThemes'])))
			$row['id_theme'] = $guest_theme;
		elseif (empty($modSettings['theme_allow']))
			$row['id_theme'] = $guest_theme;

		if (isset($context['available_themes'][$row['id_theme']]))
			$context['available_themes'][$row['id_theme']]['num_users'] += $row['the_count'];
		else
			$context['available_themes'][$guest_theme]['num_users'] += $row['the_count'];
	}
	$smcFunc['db_free_result']($request);

	// Get any member variant preferences.
	$variant_preferences = array();
	if ($context['current_member'] > 0)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE variable = {string:theme_variant}
				AND id_member IN ({array_int:id_member})
			ORDER BY id_member ASC',
			array(
				'theme_variant' => 'theme_variant',
				'id_member' => isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? array(-1, $context['current_member']) : array(-1),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$variant_preferences[$row['id_theme']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}

	// Save the setting first.
	$current_images_url = $settings['images_url'];
	$current_theme_variants = !empty($settings['theme_variants']) ? $settings['theme_variants'] : array();

	foreach ($context['available_themes'] as $id_theme => $theme_data)
	{
		// Don't try to load the forum or board default theme's data... it doesn't have any!
		if ($id_theme == 0)
			continue;

		// The thumbnail needs the correct path.
		$settings['images_url'] = &$theme_data['images_url'];

		if (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php');
		elseif (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php');
		else
		{
			$txt['theme_thumbnail_href'] = $theme_data['images_url'] . '/thumbnail.png';
			$txt['theme_description'] = '';
		}

		$context['available_themes'][$id_theme]['thumbnail_href'] = $txt['theme_thumbnail_href'];
		$context['available_themes'][$id_theme]['description'] = $txt['theme_description'];

		// Are there any variants?
		if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || allowedTo('admin_forum')))
		{
			$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));
			if (preg_match('~\$settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
			{
				$settings['theme_variants'] = array();

				// Fill settings up.
				eval('global $settings;' . $matches[0]);

				if (!empty($settings['theme_variants']))
				{
					loadLanguage('Settings');

					$context['available_themes'][$id_theme]['variants'] = array();
					foreach ($settings['theme_variants'] as $variant)
						$context['available_themes'][$id_theme]['variants'][$variant] = array(
							'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
							'thumbnail' => !file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') || file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : ($theme_data['images_url'] . '/thumbnail.png'),
						);

					$context['available_themes'][$id_theme]['selected_variant'] = isset($_GET['vrt']) ? $_GET['vrt'] : (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty($settings['default_variant']) ? $settings['default_variant'] : $settings['theme_variants'][0]));
					if (!isset($context['available_themes'][$id_theme]['variants'][$context['available_themes'][$id_theme]['selected_variant']]['thumbnail']))
						$context['available_themes'][$id_theme]['selected_variant'] = $settings['theme_variants'][0];

					$context['available_themes'][$id_theme]['thumbnail_href'] = $context['available_themes'][$id_theme]['variants'][$context['available_themes'][$id_theme]['selected_variant']]['thumbnail'];
					// Allow themes to override the text.
					$context['available_themes'][$id_theme]['pick_label'] = isset($txt['variant_pick']) ? $txt['variant_pick'] : $txt['theme_pick_variant'];
				}
			}
		}
	}
	// Then return it.
	$settings['images_url'] = $current_images_url;
	$settings['theme_variants'] = $current_theme_variants;

	// As long as we're not doing the default theme...
	if (!isset($_REQUEST['u']) || $_REQUEST['u'] >= 0)
	{
		if ($guest_theme != 0)
			$context['available_themes'][0] = $context['available_themes'][$guest_theme];

		$context['available_themes'][0]['id'] = 0;
		$context['available_themes'][0]['name'] = $txt['theme_forum_default'];
		$context['available_themes'][0]['selected'] = $context['current_theme'] == 0;
		$context['available_themes'][0]['description'] = $txt['theme_global_description'];
	}

	ksort($context['available_themes']);

	$context['page_title'] = $txt['theme_pick'];
	$context['sub_template'] = 'pick';
}

/**
 * Installs new themes, calls the respective function according to the install type.
 * - puts themes in $boardurl/Themes.
 * - assumes the gzip has a root directory in it. (ie default.)
 * Requires admin_forum.
 * Accessed with ?action=admin;area=theme;sa=install.
 */
function ThemeInstall()
{
	global $sourcedir, $txt, $context, $boarddir, $boardurl;
	global $themedir, $themeurl, $smcFunc;

	checkSession('request');
	isAllowedTo('admin_forum');

	require_once($sourcedir . '/Subs-Package.php');

	// Make it easier to change the path and url.
	$themedir = $boarddir . '/Themes';
	$themeurl = $boardurl . '/Themes';

	loadTemplate('Themes');

	$subActions = array(
		'file' => 'InstallFile',
		'copy' => 'InstallCopy',
		'dir' => 'InstallDir',
	);

	// Is there a function to call?
	if (isset($_GET['do']) && !empty($_GET['do']) && isset($subActions[$_GET['do']]))
	{
		$action = $smcFunc['htmlspecialchars'](trim($_GET['do']));

		// Got any info from the specific form?
		if (!isset($_POST['save_' . $action]))
			fatal_lang_error('theme_install_no_action', false);

		validateToken('admin-t-' . $action);

		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable($themedir))
			fatal_lang_error('theme_install_write_error', 'critical');

		// Call the function and handle the result.
		$result = $subActions[$action]();

		// Everything went better than expected!
		if (!empty($result))
		{
			$context['sub_template'] = 'installed';
			$context['page_title'] = $txt['theme_installed'];
			$context['installed_theme'] = $result;
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
	global $themedir, $themeurl, $context;

	// Set a temp dir for dumping all required files on it.
	$dirtemp = $themedir . '/temp';

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
		redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);

	// Another error check layer, something went wrong with the upload.
	if (isset($_FILES['theme_gz']['error']) && $_FILES['theme_gz']['error'] != 0)
		fatal_lang_error('theme_install_error_file_' . $_FILES['theme_gz']['error'], false);

	// Get the theme's name.
	$name = pathinfo($_FILES['theme_gz']['name'], PATHINFO_FILENAME);
	$name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/', '/\.tar$/'), array('_', '.', '', ''), $name);

	// Start setting some vars.
	$context['to_install'] = array(
		'theme_dir' => $themedir . '/' . $name,
		'theme_url' => $themeurl . '/' . $name,
		'images_url' => $themeurl . '/' . $name . '/images',
		'name' => $name,
	);

	// Extract the file on the proper themes dir.
	$extracted = read_tgz_file($_FILES['theme_gz']['tmp_name'], $dirtemp, false, true);

	if ($extracted)
	{
		// Read its info form the XML file.
		$theme_info = get_theme_info($dirtemp);
		$context['to_install'] += $theme_info;

		// Install the theme. theme_install() will return the new installed ID.
		$context['to_install']['id'] = theme_install($context['to_install']);

		// Rename the temp dir to the actual theme name.
		rename($dirtemp, $context['to_install']['theme_dir']);

		// return all the info.
		return $context['to_install'];
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
	global $themedir, $themeurl, $settings, $smcFunc, $context;
	global $forum_version;

	// There's gotta be something to work with.
	if (!isset($_REQUEST['copy']) || empty($_REQUEST['copy']))
		fatal_lang_error('theme_install_error_title', false);

	// Get a cleaner version.
	$name = preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

	// Is there a theme already named like this?
	if (file_exists($themedir . '/' . $name))
		fatal_lang_error('theme_install_already_dir', false);

	// This is a brand new theme so set all possible values.
	$context['to_install'] = array(
		'theme_dir' => $themedir . '/' . $name,
		'theme_url' => $themeurl . '/' . $name,
		'name' => $name,
		'images_url' => $themeurl . '/' . $name . '/images',
		'version' => '1.0',
		'install_for' => '2.1 - 2.1.99, ' . strtr($forum_version, array('SMF ' => '')),
		'based_on' => '',
		'based_on_dir' => $themedir . '/default',
	);

	// Create the specific dir.
	umask(0);
	mkdir($context['to_install']['theme_dir'], 0777);

	// Buy some time.
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Create subdirectories for css and javascript files.
	mkdir($context['to_install']['theme_dir'] . '/css', 0777);
	mkdir($context['to_install']['theme_dir'] . '/scripts', 0777);

	// Copy over the default non-theme files.
	$to_copy = array('/index.php', '/index.template.php', '/css/index.css', '/css/responsive.css', '/css/slider.min.css', '/css/rtl.css', '/css/calendar.css', '/css/calendar.rtl.css', '/css/admin.css', '/scripts/theme.js');

	foreach ($to_copy as $file)
	{
		copy($settings['default_theme_dir'] . $file, $context['to_install']['theme_dir'] . $file);
		smf_chmod($context['to_install']['theme_dir'] . $file, 0777);
	}

	// And now the entire images directory!
	copytree($settings['default_theme_dir'] . '/images', $context['to_install']['theme_dir'] . '/images');
	package_flush_cache();

	// Lets get some data for the new theme.
	$request = $smcFunc['db_query']('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:theme_templates}, {string:theme_layers})
			AND id_member = {int:no_member}
			AND id_theme = {int:default_theme}',
		array(
			'no_member' => 0,
			'default_theme' => 1,
			'theme_templates' => 'theme_templates',
			'theme_layers' => 'theme_layers',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['variable'] == 'theme_templates')
			$theme_templates = $row['value'];
		elseif ($row['variable'] == 'theme_layers')
			$theme_layers = $row['value'];
		else
			continue;
	}

	$smcFunc['db_free_result']($request);

	$context['to_install'] += array(
		'theme_layers' => empty($theme_layers) ? 'html,body' : $theme_layers,
		'theme_templates' => empty($theme_templates) ? 'index' : $theme_templates,
	);

	// Lets add a theme_info.xml to this theme.
	$xml_info = '<' . '?xml version="1.0"?' . '>
<theme-info xmlns="http://www.simplemachines.org/xml/theme-info" xmlns:smf="http://www.simplemachines.org/">
<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
<id>smf:' . $smcFunc['strtolower']($context['to_install']['name']) . '</id>
<!-- The theme\'s version, please try to use semantic versioning. -->
<version>1.0</version>
<!-- Install for, the SMF versions this theme was designed for. Uses the same wildcards used in the packager manager. This field is mandatory. -->
<install for="' . $context['to_install']['install_for'] . '" />
<!-- Theme name, used purely for aesthetics. -->
<name>' . $context['to_install']['name'] . '</name>
<!-- Author: your email address or contact information. The name attribute is optional. -->
<author name="Simple Machines">info@simplemachines.org</author>
<!-- Website... where to get updates and more information. -->
<website>https://www.simplemachines.org/</website>
<!-- Template layers to use, defaults to "html,body". -->
<layers>' . $context['to_install']['theme_layers'] . '</layers>
<!-- Templates to load on startup. Default is "index". -->
<templates>' . $context['to_install']['theme_templates'] . '</templates>
<!-- Base this theme off another? Default is blank, or no. It could be "default". -->
<based-on></based-on>
</theme-info>';

	// Now write it.
	$fp = @fopen($context['to_install']['theme_dir'] . '/theme_info.xml', 'w+');
	if ($fp)
	{
		fwrite($fp, $xml_info);
		fclose($fp);
	}

	// Install the theme. theme_install() will take care of possible errors.
	$context['to_install']['id'] = theme_install($context['to_install']);

	// return the info.
	return $context['to_install'];
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
	global $themedir, $themeurl, $context;

	// Cannot use the theme dir as a theme dir.
	if (!isset($_REQUEST['theme_dir']) || empty($_REQUEST['theme_dir']) || rtrim(realpath($_REQUEST['theme_dir']), '/\\') == realpath($themedir))
		fatal_lang_error('theme_install_invalid_dir', false);

	// Check is there is "something" on the dir.
	elseif (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme_info.xml'))
		fatal_lang_error('theme_install_error', false);

	$name = basename($_REQUEST['theme_dir']);
	$name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $name);

	// All good! set some needed vars.
	$context['to_install'] = array(
		'theme_dir' => $_REQUEST['theme_dir'],
		'theme_url' => $themeurl . '/' . $name,
		'name' => $name,
		'images_url' => $themeurl . '/' . $name . '/images',
	);

	// Read its info form the XML file.
	$theme_info = get_theme_info($context['to_install']['theme_dir']);
	$context['to_install'] += $theme_info;

	// Install the theme. theme_install() will take care of possible errors.
	$context['to_install']['id'] = theme_install($context['to_install']);

	// return the info.
	return $context['to_install'];
}

/**
 * Possibly the simplest and best example of how to use the template system.
 *  - allows the theme to take care of actions.
 *  - happens if $settings['catch_action'] is set and action isn't found
 *   in the action array.
 *  - can use a template, layers, sub_template, filename, and/or function.
 */
function WrapAction()
{
	global $context, $settings;

	// Load any necessary template(s)?
	if (isset($settings['catch_action']['template']))
	{
		// Load both the template and language file. (but don't fret if the language file isn't there...)
		loadTemplate($settings['catch_action']['template']);
		loadLanguage($settings['catch_action']['template'], '', false);
	}

	// Any special layers?
	if (isset($settings['catch_action']['layers']))
		$context['template_layers'] = $settings['catch_action']['layers'];

	// Any function to call?
	if (isset($settings['catch_action']['function']))
	{
		$hook = $settings['catch_action']['function'];

		if (!isset($settings['catch_action']['filename']))
			$settings['catch_action']['filename'] = '';

		add_integration_function('integrate_wrap_action', $hook, false, $settings['catch_action']['filename'], false);
		call_integration_hook('integrate_wrap_action');
	}
	// And finally, the main sub template ;).
	if (isset($settings['catch_action']['sub_template']))
		$context['sub_template'] = $settings['catch_action']['sub_template'];
}

/**
 * Set an option via javascript.
 * - sets a theme option without outputting anything.
 * - can be used with javascript, via a dummy image... (which doesn't require
 * the page to reload.)
 * - requires someone who is logged in.
 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
 * - does not log access to the Who's Online log. (in index.php..)
 */
function SetJavaScript()
{
	global $settings, $user_info, $smcFunc, $options;

	// Check the session id.
	checkSession('get');

	// This good-for-nothing pixel is being used to keep the session alive.
	if (empty($_GET['var']) || !isset($_GET['val']))
		redirectexit($settings['images_url'] . '/blank.png');

	// Sorry, guests can't go any further than this.
	if ($user_info['is_guest'] || $user_info['id'] == 0)
		obExit(false);

	$reservedVars = array(
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
		'name',
	);

	// Can't change reserved vars.
	if (in_array(strtolower($_GET['var']), $reservedVars))
		redirectexit($settings['images_url'] . '/blank.png');

	// Use a specific theme?
	if (isset($_GET['th']) || isset($_GET['id']))
	{
		// Invalidate the current themes cache too.
		cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 60);

		$settings['theme_id'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];
	}

	// If this is the admin preferences the passed value will just be an element of it.
	if ($_GET['var'] == 'admin_preferences')
	{
		$options['admin_preferences'] = !empty($options['admin_preferences']) ? $smcFunc['json_decode']($options['admin_preferences'], true) : array();
		// New thingy...
		if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5)
			$options['admin_preferences'][$_GET['admin_key']] = $_GET['val'];

		// Change the value to be something nice,
		$_GET['val'] = $smcFunc['json_encode']($options['admin_preferences']);
	}

	// Update the option.
	$smcFunc['db_insert']('replace',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($settings['theme_id'], $user_info['id'], $_GET['var'], is_array($_GET['val']) ? implode(',', $_GET['val']) : $_GET['val']),
		array('id_theme', 'id_member', 'variable')
	);

	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 60);

	// Don't output anything...
	redirectexit($settings['images_url'] . '/blank.png');
}

/**
 * Shows an interface for editing the templates.
 * - uses the Themes template and edit_template/edit_style sub template.
 * - accessed via ?action=admin;area=theme;sa=edit
 */
function EditTheme()
{
	global $context, $scripturl, $boarddir, $smcFunc, $txt;

	// @todo Should this be removed?
	if (isset($_REQUEST['preview']))
		die('die() with fire');

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) @$_GET['id'];

	if (empty($_GET['th']))
	{
		get_all_themes();

		foreach ($context['themes'] as $key => $theme)
		{
			// There has to be a Settings template!
			if (!file_exists($theme['theme_dir'] . '/index.template.php') && !file_exists($theme['theme_dir'] . '/css/index.css'))
				unset($context['themes'][$key]);

			else
				$context['themes'][$key]['can_edit_style'] = file_exists($theme['theme_dir'] . '/css/index.css');
		}

		$context['sub_template'] = 'edit_list';

		return 'no_themes';
	}

	$context['session_error'] = false;

	// Get the directory of the theme we are editing.
	$currentTheme = get_single_theme($_GET['th']);
	$context['theme_id'] = $currentTheme['id'];
	$context['browse_title'] = sprintf($txt['themeadmin_browsing_theme'], $currentTheme['name']);

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
			$context['theme_files'] = get_file_listing($currentTheme['theme_dir'] . '/' . $_GET['directory'], $_GET['directory'] . '/');

			$temp = dirname($_GET['directory']);
			array_unshift($context['theme_files'], array(
				'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
				'is_writable' => is_writable($currentTheme['theme_dir'] . '/' . $temp),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=' . $temp,
				'size' => '',
			));
		}
		else
			$context['theme_files'] = get_file_listing($currentTheme['theme_dir'], '');

		// Do not list minified_ files
		foreach ($context['theme_files'] as $key => $file)
		{
			if (strpos($file['filename'], 'minified_') !== false)
				unset($context['theme_files'][$key]);
		}

		$context['sub_template'] = 'edit_browse';

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
				$fp = fopen($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php', 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				$error = @file_get_contents($currentTheme['theme_url'] . '/tmp_' . session_id() . '.php');
				if (preg_match('~ <b>(\d+)</b><br( /)?' . '>$~i', $error) != 0)
					$error_file = $currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php';
				else
					unlink($currentTheme['theme_dir'] . '/tmp_' . session_id() . '.php');
			}

			if (!isset($error_file))
			{
				$fp = fopen($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				redirectexit('action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=' . dirname($_REQUEST['filename']));
			}
		}
		// Session timed out.
		else
		{
			loadLanguage('Errors');

			$context['session_error'] = true;
			$context['sub_template'] = 'edit_file';

			// Recycle the submitted data.
			if (is_array($_POST['entire_file']))
				$context['entire_file'] = $smcFunc['htmlspecialchars'](implode("\n", $_POST['entire_file']));
			else
				$context['entire_file'] = $smcFunc['htmlspecialchars']($_POST['entire_file']);

			$context['edit_filename'] = $smcFunc['htmlspecialchars']($_POST['filename']);

			// You were able to submit it, so it's reasonable to assume you are allowed to save.
			$context['allow_save'] = true;

			// Re-create the token so that it can be used
			createToken('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));

			return;
		}
	}

	$context['allow_save'] = is_writable($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
	$context['allow_save_filename'] = strtr($currentTheme['theme_dir'] . '/' . $_REQUEST['filename'], array($boarddir => '...'));
	$context['edit_filename'] = $smcFunc['htmlspecialchars']($_REQUEST['filename']);

	if (substr($_REQUEST['filename'], -4) == '.css')
	{
		$context['sub_template'] = 'edit_style';

		$context['entire_file'] = $smcFunc['htmlspecialchars'](strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
	elseif (substr($_REQUEST['filename'], -13) == '.template.php')
	{
		$context['sub_template'] = 'edit_template';

		if (!isset($error_file))
			$file_data = file($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']);
		else
		{
			if (preg_match('~(<b>.+?</b>:.+?<b>).+?(</b>.+?<b>\d+</b>)<br( /)?' . '>$~i', $error, $match) != 0)
				$context['parse_error'] = $match[1] . $_REQUEST['filename'] . $match[2];
			$file_data = file($error_file);
			unlink($error_file);
		}

		$j = 0;
		$context['file_parts'] = array(array('lines' => 0, 'line' => 1, 'data' => ''));
		for ($i = 0, $n = count($file_data); $i < $n; $i++)
		{
			if (isset($file_data[$i + 1]) && substr($file_data[$i + 1], 0, 9) == 'function ')
			{
				// Try to format the functions a little nicer...
				$context['file_parts'][$j]['data'] = trim($context['file_parts'][$j]['data']) . "\n";

				if (empty($context['file_parts'][$j]['lines']))
					unset($context['file_parts'][$j]);
				$context['file_parts'][++$j] = array('lines' => 0, 'line' => $i + 1, 'data' => '');
			}

			$context['file_parts'][$j]['lines']++;
			$context['file_parts'][$j]['data'] .= $smcFunc['htmlspecialchars'](strtr($file_data[$i], array("\t" => '   ')));
		}

		$context['entire_file'] = $smcFunc['htmlspecialchars'](strtr(implode('', $file_data), array("\t" => '   ')));
	}
	else
	{
		$context['sub_template'] = 'edit_file';

		$context['entire_file'] = $smcFunc['htmlspecialchars'](strtr(file_get_contents($currentTheme['theme_dir'] . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}

	// Create a special token to allow editing of multiple files.
	createToken('admin-te-' . md5($_GET['th'] . '-' . $_REQUEST['filename']));
}

/**
 * Makes a copy of a template file in a new location
 *
 * @uses Themes template, copy_template sub-template.
 */
function CopyTemplate()
{
	global $context, $settings;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$context[$context['admin_menu_name']]['current_subsection'] = 'edit';

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	if (empty($_GET['th']))
		fatal_lang_error('theme_install_invalid_id');

	// Get the theme info.
	$theme = get_single_theme($_GET['th']);
	$context['theme_id'] = $theme['id'];

	if (isset($_REQUEST['template']) && preg_match('~[\./\\\\:\0]~', $_REQUEST['template']) == 0)
	{
		if (file_exists($settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = $settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php';

		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme['theme_dir'] . '/' . $_REQUEST['template'] . '.template.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=copy');
	}
	elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^\./\\\\:\0]\.[^\./\\\\:\0]$~', $_REQUEST['lang_file']) != 0)
	{
		if (file_exists($settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php'))
			$filename = $settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php';

		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme['theme_dir'] . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=copy');
	}

	$templates = array();
	$lang_files = array();

	$dir = dir($settings['default_theme_dir']);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php')
			$templates[] = substr($entry, 0, -13);
	}
	$dir->close();

	$dir = dir($settings['default_theme_dir'] . '/languages');
	while ($entry = $dir->read())
	{
		if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches))
			$lang_files[] = $matches[1];
	}
	$dir->close();

	natcasesort($templates);
	natcasesort($lang_files);

	$context['available_templates'] = array();
	foreach ($templates as $template)
		$context['available_templates'][$template] = array(
			'filename' => $template . '.template.php',
			'value' => $template,
			'already_exists' => false,
			'can_copy' => is_writable($theme['theme_dir']),
		);
	$context['available_language_files'] = array();
	foreach ($lang_files as $file)
		$context['available_language_files'][$file] = array(
			'filename' => $file . '.php',
			'value' => $file,
			'already_exists' => false,
			'can_copy' => file_exists($theme['theme_dir'] . '/languages') ? is_writable($theme['theme_dir'] . '/languages') : is_writable($theme['theme_dir']),
		);

	$dir = dir($theme['theme_dir']);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php' && isset($context['available_templates'][substr($entry, 0, -13)]))
		{
			$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;
			$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme['theme_dir'] . '/' . $entry);
		}
	}
	$dir->close();

	if (file_exists($theme['theme_dir'] . '/languages'))
	{
		$dir = dir($theme['theme_dir'] . '/languages');
		while ($entry = $dir->read())
		{
			if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && isset($context['available_language_files'][$matches[1]]))
			{
				$context['available_language_files'][$matches[1]]['already_exists'] = true;
				$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme['theme_dir'] . '/languages/' . $entry);
			}
		}
		$dir->close();
	}

	$context['sub_template'] = 'copy_template';
}

?>