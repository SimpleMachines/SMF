<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file concerns itself almost completely with theme administration.
	Its tasks include changing theme settings, installing and removing
	themes, choosing the current theme, and editing themes.  This is done in:

	void ThemesMain()
		- manages the action and delegates control to the proper sub action.
		- loads both the Themes and Settings language files.
		- checks the session by GET or POST to verify the sent data.
		- requires the user not be a guest.
		- is accessed via ?action=admin;area=theme.

	void ThemeAdmin()
		- administrates themes and their settings, as well as global theme
		  settings.
		- sets the settings theme_allow, theme_guests, and knownThemes.
		- loads the template Themes.
		- requires the admin_forum permission.
		- accessed with ?action=admin;area=theme;sa=admin.

	void ThemeList()
		- lists the available themes.
		- provides an interface to reset the paths of all the installed themes.

	void SetThemeOptions()
		// !!!

	void SetThemeSettings()
		- saves and requests global theme settings. ($settings)
		- loads the Admin language file.
		- calls ThemeAdmin() if no theme is specified. (the theme center.)
		- requires an administrator.
		- accessed with ?action=admin;area=theme;sa=settings&th=xx.

	void RemoveTheme()
		- removes an installed theme.
		- requires an administrator.
		- accessed with ?action=admin;area=theme;sa=remove.

	void PickTheme()
		- allows user or administrator to pick a new theme with an interface.
		- can edit everyone's (u = 0), guests' (u = -1), or a specific user's.
		- uses the Themes template. (pick sub template.)
		- accessed with ?action=admin;area=theme;sa=pick.

	void ThemeInstall()
		- installs new themes, either from a gzip or copy of the default.
		- requires an administrator.
		- puts themes in $boardurl/Themes.
		- assumes the gzip has a root directory in it. (ie default.)
		- accessed with ?action=admin;area=theme;sa=install.

	void WrapAction()
		- allows the theme to take care of actions.
		- happens if $settings['catch_action'] is set and action isn't found
		  in the action array.
		- can use a template, layers, sub_template, filename, and/or function.

	void SetJavaScript()
		- sets a theme option without outputting anything.
		- can be used with javascript, via a dummy image... (which doesn't
		  require the page to reload.)
		- requires someone who is logged in.
		- accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
		- does not log access to the Who's Online log. (in index.php..)

	void EditTheme()
		- shows an interface for editing the templates.
		- uses the Themes template and edit_template/edit_style sub template.
		- accessed via ?action=admin;area=theme;sa=edit

	function convert_template($output_dir, $old_template = '')
		// !!!

	function phpcodefix(string string)
		// !!!

	function makeStyleChanges(&$old_template)
		// !!!

	// !!! Update this for the new package manager?
	Creating and distributing theme packages:
	---------------------------------------------------------------------------
		There isn't that much required to package and distribute your own
		themes... just do the following:
		- create a theme_info.xml file, with the root element theme-info.
		- its name should go in a name element, just like description.
		- your name should go in author. (email in the email attribute.)
		- any support website for the theme should be in website.
		- layers and templates (non-default) should go in those elements ;).
		- if the images dir isn't images, specify in the images element.
		- any extra rows for themes should go in extra, serialized.
		   (as in array(variable => value).)
		- tar and gzip the directory - and you're done!
		- please include any special license in a license.txt file.
	// !!! Thumbnail?
*/

// Subaction handler.
function ThemesMain()
{
	global $txt, $context, $scripturl;

	// Load the important language files...
	loadLanguage('Themes');
	loadLanguage('Settings');

	// No funny business - guests only.
	is_not_guest();

	// Default the page title to Theme Administration by default.
	$context['page_title'] = $txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'reset' => 'SetThemeOptions',
		'settings' => 'SetThemeSettings',
		'options' => 'SetThemeOptions',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveTheme',
		'pick' => 'PickTheme',
		'edit' => 'EditTheme',
		'copy' => 'CopyTemplate',
	);

	// !!! Layout Settings?
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

	// Follow the sa or just go to administration.
	if (isset($_GET['sa']) && !empty($subActions[$_GET['sa']]))
		$subActions[$_GET['sa']]();
	else
		$subActions['admin']();
}

function ThemeAdmin()
{
	global $context, $boarddir, $modSettings, $smcFunc;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// If we aren't submitting - that is, if we are about to...
	if (!isset($_POST['submit']))
	{
		loadTemplate('Themes');

		// Make our known themes a little easier to work with.
		$knownThemes = !empty($modSettings['knownThemes']) ? explode(',',$modSettings['knownThemes']) : array();

		// Load up all the themes.
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, value AS name
			FROM {db_prefix}themes
			WHERE variable = {string:name}
				AND id_member = {int:no_member}
			ORDER BY id_theme',
			array(
				'no_member' => 0,
				'name' => 'name',
			)
		);
		$context['themes'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['themes'][] = array(
				'id' => $row['id_theme'],
				'name' => $row['name'],
				'known' => in_array($row['id_theme'], $knownThemes),
			);
		$smcFunc['db_free_result']($request);

		// Can we create a new theme?
		$context['can_create_new'] = is_writable($boarddir . '/Themes');
		$context['new_theme_dir'] = substr(realpath($boarddir . '/Themes/default'), 0, -7);

		// Look for a non existent theme directory. (ie theme87.)
		$theme_dir = $boarddir . '/Themes/theme';
		$i = 1;
		while (file_exists($theme_dir . $i))
			$i++;
		$context['new_theme_name'] = 'theme' . $i;
	}
	else
	{
		checkSession();

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
}

function ThemeList()
{
	global $context, $boarddir, $boardurl, $smcFunc;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	if (isset($_POST['submit']))
	{
		checkSession();

		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:base_theme_dir}, {string:base_theme_url}, {string:base_images_url})
				AND id_member = {int:no_member}',
			array(
				'no_member' => 0,
				'theme_dir' => 'theme_dir',
				'theme_url' => 'theme_url',
				'images_url' => 'images_url',
				'base_theme_dir' => 'base_theme_dir',
				'base_theme_url' => 'base_theme_url',
				'base_images_url' => 'base_images_url',
			)
		);
		$themes = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$themes[$row['id_theme']][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);

		$setValues = array();
		foreach ($themes as $id => $theme)
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

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_url}, {string:images_url})
			AND id_member = {int:no_member}',
		array(
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
			'theme_url' => 'theme_url',
			'images_url' => 'images_url',
		)
	);
	$context['themes'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['themes'][$row['id_theme']]))
			$context['themes'][$row['id_theme']] = array(
				'id' => $row['id_theme'],
			);
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($request);

	foreach ($context['themes'] as $i => $theme)
	{
		$context['themes'][$i]['theme_dir'] = realpath($context['themes'][$i]['theme_dir']);

		if (file_exists($context['themes'][$i]['theme_dir'] . '/index.template.php'))
		{
			// Fetch the header... a good 256 bytes should be more than enough.
			$fp = fopen($context['themes'][$i]['theme_dir'] . '/index.template.php', 'rb');
			$header = fread($fp, 256);
			fclose($fp);

			// Can we find a version comment, at all?
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$context['themes'][$i]['version'] = $match[1];
		}

		$context['themes'][$i]['valid_path'] = file_exists($context['themes'][$i]['theme_dir']) && is_dir($context['themes'][$i]['theme_dir']);
	}

	$context['reset_dir'] = realpath($boarddir . '/Themes');
	$context['reset_url'] = $boardurl . '/Themes';

	$context['sub_template'] = 'list_themes';
}

// Administrative global settings.
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

		return;
	}

	// Submit?
	if (isset($_POST['submit']) && empty($_POST['who']))
	{
		checkSession();

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
	//!!! Should we just move these options so they are no longer theme dependant?
	loadLanguage('PersonalMessage');

	// Let the theme take care of the settings.
	loadTemplate('Settings');
	loadSubTemplate('options');

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
		// Is this disabled?
		if ($setting['id'] == 'calendar_start_day' && empty($modSettings['cal_enabled']))
		{
			unset($context['options'][$i]);
			continue;
		}
		elseif (($setting['id'] == 'topics_per_page' || $setting['id'] == 'messages_per_page') && !empty($modSettings['disableCustomPerPage']))
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
}

// Administrative global settings.
function SetThemeSettings()
{
	global $txt, $context, $settings, $modSettings, $sourcedir, $smcFunc;

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
		$context['smiley_sets'][$set] = htmlspecialchars($set_names[$i]);

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

	// Submitting!
	if (isset($_POST['submit']))
	{
		checkSession();

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

		redirectexit('action=admin;area=theme;sa=settings;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id']);
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
				'thumbnail' => !file_exists($settings['theme_dir'] . '/images/thumbnail.gif') || file_exists($settings['theme_dir'] . '/images/thumbnail_' . $variant . '.gif') ? $settings['images_url'] . '/thumbnail_' . $variant . '.gif' : ($settings['images_url'] . '/thumbnail.gif'),
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
}

// Remove a theme from the database.
function RemoveTheme()
{
	global $modSettings, $context, $smcFunc;

	checkSession('get');

	isAllowedTo('admin_forum');

	// The theme's ID must be an integer.
	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// You can't delete the default theme!
	if ($_GET['th'] == 1)
		fatal_lang_error('no_access', false);

	$known = explode(',', $modSettings['knownThemes']);
	for ($i = 0, $n = count($known); $i < $n; $i++)
	{
		if ($known[$i] == $_GET['th'])
			unset($known[$i]);
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $_GET['th'],
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $_GET['th'],
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $_GET['th'],
		)
	);

	$known = strtr(implode(',', $known), array(',,' => ','));

	// Fix it if the theme was the overall default theme.
	if ($modSettings['theme_guests'] == $_GET['th'])
		updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));
	else
		updateSettings(array('knownThemes' => $known));

	redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
}

// Choose a theme from a list.
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

	// Have we made a desicion, or are we just browsing?
	if (isset($_GET['th']))
	{
		checkSession('get');

		$_GET['th'] = (int) $_GET['th'];

		// Save for this user.
		if (!isset($_REQUEST['u']) || !allowedTo('admin_forum'))
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

			if (!empty($_GET['vrt']))
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
				AND id_member = {int:no_member}',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'disable_user_variant' => 'disable_user_variant',
				'known_themes' => explode(',', $modSettings['knownThemes']),
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
		if (!empty($modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',',$modSettings['knownThemes'])))
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
			WHERE variable = {string:theme_variant}',
			array(
				'theme_variant' => 'theme_variant',
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
			$txt['theme_thumbnail_href'] = $theme_data['images_url'] . '/thumbnail.gif';
			$txt['theme_description'] = '';
		}

		$context['available_themes'][$id_theme]['thumbnail_href'] = $txt['theme_thumbnail_href'];
		$context['available_themes'][$id_theme]['description'] = $txt['theme_description'];

		// Are there any variants?
		if (file_exists($theme_data['theme_dir'] . '/index.template.php') && empty($theme_data['disable_user_variant']))
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
							'thumbnail' => !file_exists($theme_data['theme_dir'] . '/images/thumbnail.gif') || file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.gif') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.gif' : ($theme_data['images_url'] . '/thumbnail.gif'),
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

function ThemeInstall()
{
	global $sourcedir, $boarddir, $boardurl, $txt, $context, $settings, $modSettings, $smcFunc;

	checkSession('request');

	isAllowedTo('admin_forum');
	checkSession('request');

	require_once($sourcedir . '/Subs-Package.php');

	loadTemplate('Themes');

	if (isset($_GET['theme_id']))
	{
		$result = $smcFunc['db_query']('', '
			SELECT value
			FROM {db_prefix}themes
			WHERE id_theme = {int:current_theme}
				AND id_member = {int:no_member}
				AND variable = {string:name}
			LIMIT 1',
			array(
				'current_theme' => (int) $_GET['theme_id'],
				'no_member' => 0,
				'name' => 'name',
			)
		);
		list ($theme_name) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		$context['sub_template'] = 'installed';
		$context['page_title'] = $txt['theme_installed'];
		$context['installed_theme'] = array(
			'id' => (int) $_GET['theme_id'],
			'name' => $theme_name,
		);

		return;
	}

	if ((!empty($_FILES['theme_gz']) && (!isset($_FILES['theme_gz']['error']) || $_FILES['theme_gz']['error'] != 4)) || !empty($_REQUEST['theme_gz']))
		$method = 'upload';
	elseif (isset($_REQUEST['theme_dir']) && rtrim(realpath($_REQUEST['theme_dir']), '/\\') != realpath($boarddir . '/Themes') && file_exists($_REQUEST['theme_dir']))
		$method = 'path';
	else
		$method = 'copy';

	if (!empty($_REQUEST['copy']) && $method == 'copy')
	{
		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable($boarddir . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		$theme_dir = $boarddir . '/Themes/' . preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

		umask(0);
		mkdir($theme_dir, 0777);

		@set_time_limit(600);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Create subdirectories for css and javascript files.
		mkdir($theme_dir . '/css', 0777);
		mkdir($theme_dir . '/scripts', 0777);

		// Copy over the default non-theme files.
		$to_copy = array('/index.php', '/index.template.php', '/css/index.css', '/css/rtl.css', '/scripts/theme.js');
		foreach ($to_copy as $file)
		{
			copy($settings['default_theme_dir'] . $file, $theme_dir . $file);
			@chmod($theme_dir . $file, 0777);
		}

		// And now the entire images directory!
		copytree($settings['default_theme_dir'] . '/images', $theme_dir . '/images');
		package_flush_cache();

		$theme_name = $_REQUEST['copy'];
		$images_url = $boardurl . '/Themes/' . basename($theme_dir) . '/images';
		$theme_dir = realpath($theme_dir);

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

		// Lets add a theme_info.xml to this theme.
		$xml_info = '<' . '?xml version="1.0"?' . '>
<theme-info xmlns="http://www.simplemachines.org/xml/theme-info" xmlns:smf="http://www.simplemachines.org/">
	<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
	<id>smf:' . $smcFunc['strtolower'](str_replace(array(' '), '_', $_REQUEST['copy'])) . '</id>
	<version>' . $modSettings['smfVersion'] . '</version>
	<!-- Theme name, used purely for aesthetics. -->
	<name>' . $_REQUEST['copy'] . '</name>
	<!-- Author: your email address or contact information. The name attribute is optional. -->
	<author name="Simple Machines">info@simplemachines.org</author>
	<!-- Website... where to get updates and more information. -->
	<website>http://www.simplemachines.org/</website>
	<!-- Template layers to use, defaults to "html,body". -->
	<layers>' . (empty($theme_layers) ? 'html,body' : $theme_layers) . '</layers>
	<!-- Templates to load on startup. Default is "index". -->
	<templates>' . (empty($theme_templates) ? 'index' : $theme_templates) . '</templates>
	<!-- Base this theme off another? Default is blank, or no. It could be "default". -->
	<based-on></based-on>
</theme-info>';

		// Now write it.
		$fp = @fopen($theme_dir . '/theme_info.xml', 'w+');
		if ($fp)
		{
			fwrite($fp, $xml_info);
			fclose($fp);
		}
	}
	elseif (isset($_REQUEST['theme_dir']) && $method == 'path')
	{
		if (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme_info.xml'))
			fatal_lang_error('theme_install_error', false);

		$theme_name = basename($_REQUEST['theme_dir']);
		$theme_dir = $_REQUEST['theme_dir'];
	}
	elseif ($method = 'upload')
	{
		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable($boarddir . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		require_once($sourcedir . '/Subs-Package.php');

		// Set the default settings...
		$theme_name = strtok(basename(isset($_FILES['theme_gz']) ? $_FILES['theme_gz']['name'] : $_REQUEST['theme_gz']), '.');
		$theme_name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $theme_name);
		$theme_dir = $boarddir . '/Themes/' . $theme_name;

		if (isset($_FILES['theme_gz']) && is_uploaded_file($_FILES['theme_gz']['tmp_name']) && (@ini_get('open_basedir') != '' || file_exists($_FILES['theme_gz']['tmp_name'])))
			$extracted = read_tgz_file($_FILES['theme_gz']['tmp_name'], $boarddir . '/Themes/' . $theme_name, false, true);
		elseif (isset($_REQUEST['theme_gz']))
		{
			// Check that the theme is from simplemachines.org, for now... maybe add mirroring later.
			if (preg_match('~^http://[\w_\-]+\.simplemachines\.org/~', $_REQUEST['theme_gz']) == 0 || strpos($_REQUEST['theme_gz'], 'dlattach') !== false)
				fatal_lang_error('not_on_simplemachines');

			$extracted = read_tgz_file($_REQUEST['theme_gz'], $boarddir . '/Themes/' . $theme_name, false, true);
		}
		else
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Something go wrong?
	if ($theme_dir != '' && basename($theme_dir) != 'Themes')
	{
		// Defaults.
		$install_info = array(
			'theme_url' => $boardurl . '/Themes/' . basename($theme_dir),
			'images_url' => isset($images_url) ? $images_url : $boardurl . '/Themes/' . basename($theme_dir) . '/images',
			'theme_dir' => $theme_dir,
			'name' => $theme_name
		);

		if (file_exists($theme_dir . '/theme_info.xml'))
		{
			$theme_info = file_get_contents($theme_dir . '/theme_info.xml');

			$xml_elements = array(
				'name' => 'name',
				'theme_layers' => 'layers',
				'theme_templates' => 'templates',
				'based_on' => 'based-on',
			);
			foreach ($xml_elements as $var => $name)
			{
				if (preg_match('~<' . $name . '>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</' . $name . '>~', $theme_info, $match) == 1)
					$install_info[$var] = $match[1];
			}

			if (preg_match('~<images>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</images>~', $theme_info, $match) == 1)
			{
				$install_info['images_url'] = $install_info['theme_url'] . '/' . $match[1];
				$explicit_images = true;
			}
			if (preg_match('~<extra>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</extra>~', $theme_info, $match) == 1)
				$install_info += unserialize($match[1]);
		}

		if (isset($install_info['based_on']))
		{
			if ($install_info['based_on'] == 'default')
			{
				$install_info['theme_url'] = $settings['default_theme_url'];
				$install_info['images_url'] = $settings['default_images_url'];
			}
			elseif ($install_info['based_on'] != '')
			{
				$install_info['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', $install_info['based_on']);

				$request = $smcFunc['db_query']('', '
					SELECT th.value AS base_theme_dir, th2.value AS base_theme_url' . (!empty($explicit_images) ? '' : ', th3.value AS images_url') . '
					FROM {db_prefix}themes AS th
						INNER JOIN {db_prefix}themes AS th2 ON (th2.id_theme = th.id_theme
							AND th2.id_member = {int:no_member}
							AND th2.variable = {string:theme_url})' . (!empty($explicit_images) ? '' : '
						INNER JOIN {db_prefix}themes AS th3 ON (th3.id_theme = th.id_theme
							AND th3.id_member = {int:no_member}
							AND th3.variable = {string:images_url})') . '
					WHERE th.id_member = {int:no_member}
						AND (th.value LIKE {string:based_on} OR th.value LIKE {string:based_on_path})
						AND th.variable = {string:theme_dir}
					LIMIT 1',
					array(
						'no_member' => 0,
						'theme_url' => 'theme_url',
						'images_url' => 'images_url',
						'theme_dir' => 'theme_dir',
						'based_on' => '%/' . $install_info['based_on'],
						'based_on_path' => '%' . "\\" . $install_info['based_on'],
					)
				);
				$temp = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);

				// !!! An error otherwise?
				if (is_array($temp))
				{
					$install_info = $temp + $install_info;

					if (empty($explicit_images) && !empty($install_info['base_theme_url']))
						$install_info['theme_url'] = $install_info['base_theme_url'];
				}
			}

			unset($install_info['based_on']);
		}

		// Find the newest id_theme.
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_theme)
			FROM {db_prefix}themes',
			array(
			)
		);
		list ($id_theme) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		// This will be theme number...
		$id_theme++;

		$inserts = array();
		foreach ($install_info as $var => $val)
			$inserts[] = array($id_theme, $var, $val);

		if (!empty($inserts))
			$smcFunc['db_insert']('insert',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_theme', 'variable')
			);

		updateSettings(array('knownThemes' => strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));
	}

	redirectexit('action=admin;area=theme;sa=install;theme_id=' . $id_theme . ';' . $context['session_var'] . '=' . $context['session_id']);
}

// Possibly the simplest and best example of how to ues the template system.
function WrapAction()
{
	global $context, $settings, $sourcedir;

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

	// Just call a function?
	if (isset($settings['catch_action']['function']))
	{
		if (isset($settings['catch_action']['filename']))
			template_include($sourcedir . '/' . $settings['catch_action']['filename'], true);

		$settings['catch_action']['function']();
	}
	// And finally, the main sub template ;).
	elseif (isset($settings['catch_action']['sub_template']))
		$context['sub_template'] = $settings['catch_action']['sub_template'];
}

// Set an option via javascript.
function SetJavaScript()
{
	global $settings, $user_info, $smcFunc, $options;

	// Check the session id.
	checkSession('get');

	// This good-for-nothing pixel is being used to keep the session alive.
	if (empty($_GET['var']) || !isset($_GET['val']))
		redirectexit($settings['images_url'] . '/blank.gif');

	// Sorry, guests can't go any further than this..
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
		redirectexit($settings['images_url'] . '/blank.gif');

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
		$options['admin_preferences'] = !empty($options['admin_preferences']) ? unserialize($options['admin_preferences']) : array();
		// New thingy...
		if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5)
			$options['admin_preferences'][$_GET['admin_key']] = $_GET['val'];

		// Change the value to be something nice,
		$_GET['val'] = serialize($options['admin_preferences']);
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
	redirectexit($settings['images_url'] . '/blank.gif');
}

function EditTheme()
{
	global $context, $settings, $scripturl, $boarddir, $smcFunc;

	if (isset($_REQUEST['preview']))
	{
		// !!! Should this be removed?
		die;
	}

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) @$_GET['id'];

	if (empty($_GET['th']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_templates}, {string:theme_layers})
				AND id_member = {int:no_member}',
			array(
				'name' => 'name',
				'theme_dir' => 'theme_dir',
				'theme_templates' => 'theme_templates',
				'theme_layers' => 'theme_layers',
				'no_member' => 0,
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

		foreach ($context['themes'] as $key => $theme)
		{
			// There has to be a Settings template!
			if (!file_exists($theme['theme_dir'] . '/index.template.php') && !file_exists($theme['theme_dir'] . '/css/index.css'))
				unset($context['themes'][$key]);
			else
			{
				if (!isset($theme['theme_templates']))
					$templates = array('index');
				else
					$templates = explode(',', $theme['theme_templates']);

				foreach ($templates as $template)
					if (file_exists($theme['theme_dir'] . '/' . $template . '.template.php'))
					{
						// Fetch the header... a good 256 bytes should be more than enough.
						$fp = fopen($theme['theme_dir'] . '/' . $template . '.template.php', 'rb');
						$header = fread($fp, 256);
						fclose($fp);

						// Can we find a version comment, at all?
						if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
						{
							$ver = $match[1];
							if (!isset($context['themes'][$key]['version']) || $context['themes'][$key]['version'] > $ver)
								$context['themes'][$key]['version'] = $ver;
						}
					}

				$context['themes'][$key]['can_edit_style'] = file_exists($theme['theme_dir'] . '/css/index.css');
			}
		}

		$context['sub_template'] = 'edit_list';

		return 'no_themes';
	}

	$context['session_error'] = false;

	// Get the directory of the theme we are editing.
	$request = $smcFunc['db_query']('', '
		SELECT value, id_theme
		FROM {db_prefix}themes
		WHERE variable = {string:theme_dir}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $_GET['th'],
			'theme_dir' => 'theme_dir',
		)
	);
	list ($theme_dir, $context['theme_id']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (!isset($_REQUEST['filename']))
	{
		if (isset($_GET['directory']))
		{
			if (substr($_GET['directory'], 0, 1) == '.')
				$_GET['directory'] = '';
			else
			{
				$_GET['directory'] = preg_replace(array('~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'), array('', '/', '/'), $_GET['directory']);

				$temp = realpath($theme_dir . '/' . $_GET['directory']);
				if (empty($temp) || substr($temp, 0, strlen(realpath($theme_dir))) != realpath($theme_dir))
					$_GET['directory'] = '';
			}
		}

		if (isset($_GET['directory']) && $_GET['directory'] != '')
		{
			$context['theme_files'] = get_file_listing($theme_dir . '/' . $_GET['directory'], $_GET['directory'] . '/');

			$temp = dirname($_GET['directory']);
			array_unshift($context['theme_files'], array(
				'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
				'is_writable' => is_writable($theme_dir . '/' . $temp),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=' . $temp,
				'size' => '',
			));
		}
		else
			$context['theme_files'] = get_file_listing($theme_dir, '');

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

			$temp = realpath($theme_dir . '/' . $_REQUEST['filename']);
			if (empty($temp) || substr($temp, 0, strlen(realpath($theme_dir))) != realpath($theme_dir))
				$_REQUEST['filename'] = '';
		}

		if (empty($_REQUEST['filename']))
			fatal_lang_error('theme_edit_missing', false);
	}

	if (isset($_POST['submit']))
	{
		if (checkSession('post', '', false) == '')
		{
			if (is_array($_POST['entire_file']))
				$_POST['entire_file'] = implode("\n", $_POST['entire_file']);
			$_POST['entire_file'] = rtrim(strtr($_POST['entire_file'], array("\r" => '', '   ' => "\t")));

			// Check for a parse error!
			if (substr($_REQUEST['filename'], -13) == '.template.php' && is_writable($theme_dir) && @ini_get('display_errors'))
			{
				$request = $smcFunc['db_query']('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE variable = {string:theme_url}
						AND id_theme = {int:current_theme}
					LIMIT 1',
					array(
						'current_theme' => $_GET['th'],
						'theme_url' => 'theme_url',
					)
				);
				list ($theme_url) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				$fp = fopen($theme_dir . '/tmp_' . session_id() . '.php', 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				// !!! Use fetch_web_data()?
				$error = @file_get_contents($theme_url . '/tmp_' . session_id() . '.php');
				if (preg_match('~ <b>(\d+)</b><br( /)?' . '>$~i', $error) != 0)
					$error_file = $theme_dir . '/tmp_' . session_id() . '.php';
				else
					unlink($theme_dir . '/tmp_' . session_id() . '.php');
			}

			if (!isset($error_file))
			{
				$fp = fopen($theme_dir . '/' . $_REQUEST['filename'], 'w');
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
			$context['entire_file'] = htmlspecialchars($_POST['entire_file']);

			// You were able to submit it, so it's reasonable to assume you are allowed to save.
			$context['allow_save'] = true;

			return;
		}
	}

	$context['allow_save'] = is_writable($theme_dir . '/' . $_REQUEST['filename']);
	$context['allow_save_filename'] = strtr($theme_dir . '/' . $_REQUEST['filename'], array($boarddir => '...'));
	$context['edit_filename'] = htmlspecialchars($_REQUEST['filename']);

	if (substr($_REQUEST['filename'], -4) == '.css')
	{
		$context['sub_template'] = 'edit_style';

		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($theme_dir . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
	elseif (substr($_REQUEST['filename'], -13) == '.template.php')
	{
		$context['sub_template'] = 'edit_template';

		if (!isset($error_file))
			$file_data = file($theme_dir . '/' . $_REQUEST['filename']);
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
			$context['file_parts'][$j]['data'] .= htmlspecialchars(strtr($file_data[$i], array("\t" => '   ')));
		}

		$context['entire_file'] = htmlspecialchars(strtr(implode('', $file_data), array("\t" => '   ')));
	}
	else
	{
		$context['sub_template'] = 'edit_file';

		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($theme_dir . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
}

function get_file_listing($path, $relative)
{
	global $scripturl, $txt, $context;

	// Is it even a directory?
	if (!is_dir($path))
		fatal_lang_error('error_invalid_dir', 'critical');

	$dir = dir($path);
	$entries = array();
	while ($entry = $dir->read())
		$entries[] = $entry;
	$dir->close();

	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) == '.' || $entry == 'CVS')
			continue;

		if (is_dir($path . '/' . $entry))
			$listing1[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = comma_format($size / 1024) . ' ' . $txt['themeadmin_edit_kilobytes'];
			else
				$size = comma_format($size) . ' ' . $txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => timeformat(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

function CopyTemplate()
{
	global $context, $settings, $smcFunc;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$context[$context['admin_menu_name']]['current_subsection'] = 'edit';

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	$request = $smcFunc['db_query']('', '
		SELECT th1.value, th1.id_theme, th2.value
		FROM {db_prefix}themes AS th1
			LEFT JOIN {db_prefix}themes AS th2 ON (th2.variable = {string:base_theme_dir} AND th2.id_theme = {int:current_theme})
		WHERE th1.variable = {string:theme_dir}
			AND th1.id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $_GET['th'],
			'base_theme_dir' => 'base_theme_dir',
			'theme_dir' => 'theme_dir',
		)
	);
	list ($theme_dir, $context['theme_id'], $base_theme_dir) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (isset($_REQUEST['template']) && preg_match('~[\./\\\\:\0]~', $_REQUEST['template']) == 0)
	{
		if (!empty($base_theme_dir) && file_exists($base_theme_dir . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = $base_theme_dir . '/' . $_REQUEST['template'] . '.template.php';
		elseif (file_exists($settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = $settings['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/' . $_REQUEST['template'] . '.template.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=copy');
	}
	elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^\./\\\\:\0]\.[^\./\\\\:\0]$~', $_REQUEST['lang_file']) != 0)
	{
		if (!empty($base_theme_dir) && file_exists($base_theme_dir . '/languages/' . $_REQUEST['lang_file'] . '.php'))
			$filename = $base_theme_dir . '/languages/' . $_REQUEST['template'] . '.php';
		elseif (file_exists($settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php'))
			$filename = $settings['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
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

	if (!empty($base_theme_dir))
	{
		$dir = dir($base_theme_dir);
		while ($entry = $dir->read())
		{
			if (substr($entry, -13) == '.template.php' && !in_array(substr($entry, 0, -13), $templates))
				$templates[] = substr($entry, 0, -13);
		}
		$dir->close();

		if (file_exists($base_theme_dir . '/languages'))
		{
			$dir = dir($base_theme_dir . '/languages');
			while ($entry = $dir->read())
			{
				if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && !in_array($matches[1], $lang_files))
					$lang_files[] = $matches[1];
			}
			$dir->close();
		}
	}

	natcasesort($templates);
	natcasesort($lang_files);

	$context['available_templates'] = array();
	foreach ($templates as $template)
		$context['available_templates'][$template] = array(
			'filename' => $template . '.template.php',
			'value' => $template,
			'already_exists' => false,
			'can_copy' => is_writable($theme_dir),
		);
	$context['available_language_files'] = array();
	foreach ($lang_files as $file)
		$context['available_language_files'][$file] = array(
			'filename' => $file . '.php',
			'value' => $file,
			'already_exists' => false,
			'can_copy' => file_exists($theme_dir . '/languages') ? is_writable($theme_dir . '/languages') : is_writable($theme_dir),
		);

	$dir = dir($theme_dir);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php' && isset($context['available_templates'][substr($entry, 0, -13)]))
		{
			$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;
			$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme_dir . '/' . $entry);
		}
	}
	$dir->close();

	if (file_exists($theme_dir . '/languages'))
	{
		$dir = dir($theme_dir . '/languages');
		while ($entry = $dir->read())
		{
			if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && isset($context['available_language_files'][$matches[1]]))
			{
				$context['available_language_files'][$matches[1]]['already_exists'] = true;
				$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme_dir . '/languages/' . $entry);
			}
		}
		$dir->close();
	}

	$context['sub_template'] = 'copy_template';
}

?>