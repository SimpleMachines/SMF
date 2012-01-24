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

/*	This file contains all the functionality required to be able to edit the
	core server settings. This includes anything from which an error may result
	in the forum destroying itself in a firey fury.

	void ModifySettings()
		- Sets up all the available sub-actions.
		- Requires the admin_forum permission.
		- Uses the edit_settings adminIndex.
		- Sets up all the tabs and selects the appropriate one based on the sub-action.
		- Redirects to the appropriate function based on the sub-action.

	void ModifyGeneralSettings()
		- shows an interface for the settings in Settings.php to be changed.
		- uses the rawdata sub template (not theme-able.)
		- requires the admin_forum permission.
		- uses the edit_settings administration area.
		- contains the actual array of settings to show from Settings.php.
		- accessed from ?action=admin;area=serversettings;sa=general.

	void ModifyDatabaseSettings()
		- shows an interface for the settings in Settings.php to be changed.
		- uses the rawdata sub template (not theme-able.)
		- requires the admin_forum permission.
		- uses the edit_settings administration area.
		- contains the actual array of settings to show from Settings.php.
		- accessed from ?action=admin;area=serversettings;sa=database.

	void ModifyCookieSettings()
		// !!!

	void ModifyCacheSettings()
		// !!!

	void ModifyLoadBalancingSettings()
		// !!!

	void AddLanguage()
		// !!!

	void DownloadLanguage()
		- Uses the ManageSettings template and the download_language sub-template.
		- Requires a valid download ID ("did") in the URL.
		- Also handles installing language files.
		- Attempts to chmod things as needed.
		- Uses a standard list to display information about all the files and where they'll be put.

	void ManageLanguages()
		// !!!

	void ModifyLanguages()
		// !!!

	int list_getNumLanguages()
		// !!!

	array list_getLanguages()
		- Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
		- Determines which languages are available by looking for the "index.{language}.php" file.
		- Also figures out how many users are using a particular language.

	void ModifyLanguageSettings()
		// !!!

	void ModifyLanguage()
		// !!!

	void prepareServerSettingsContext(array config_vars)
		// !!!

	void prepareDBSettingContext(array config_vars)
		// !!!

	void saveSettings(array config_vars)
		- saves those settings set from ?action=admin;area=serversettings to the
		  Settings.php file and the database.
		- requires the admin_forum permission.
		- contains arrays of the types of data to save into Settings.php.

	void saveDBSettings(array config_vars)
		// !!!

*/

/*	Adding options to one of the setting screens isn't hard. Call prepareDBSettingsContext;
	The basic format for a checkbox is:
		array('check', 'nameInModSettingsAndSQL'),

	   And for a text box:
		array('text', 'nameInModSettingsAndSQL')
	   (NOTE: You have to add an entry for this at the bottom!)

	   In these cases, it will look for $txt['nameInModSettingsAndSQL'] as the description,
	   and $helptxt['nameInModSettingsAndSQL'] as the help popup description.

	Here's a quick explanation of how to add a new item:

	* A text input box.  For textual values.
	ie.	array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A text input box.  For numerical values.
	ie.	array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A text input box.  For floating point values.
	ie.	array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A large text input box. Used for textual values spanning multiple lines.
	ie.	array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),

	* A check box.  Either one or zero. (boolean)
	ie.	array('check', 'nameInModSettingsAndSQL'),

	* A selection box.  Used for the selection of something from a list.
	ie.	array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
	Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.

	* A password input box. Used for passwords, no less!
	ie.	array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A permission - for picking groups who have a permission.
	ie.	array('permissions', 'manage_groups'),

	* A BBC selection box.
	ie.	array('bbc', 'sig_bbc'),

	For each option:
		type (see above), variable name, size/possible values.
	OR	make type '' for an empty string for a horizontal rule.
	SET	preinput - to put some HTML prior to the input box.
	SET	postinput - to put some HTML following the input box.
	SET	invalid - to mark the data as invalid.
	PLUS	You can override label and help parameters by forcing their keys in the array, for example:
		array('text', 'invalidlabel', 3, 'label' => 'Actual Label') */

// This is the main pass through function, it creates tabs and the like.
function ModifySettings()
{
	global $context, $txt, $scripturl, $boarddir;

	// This is just to keep the database password more secure.
	isAllowedTo('admin_forum');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_server_settings'],
		'help' => 'serversettings',
		'description' => $txt['admin_basic_settings'],
	);

	checkSession('request');

	// The settings are in here, I swear!
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['admin_server_settings'];
	$context['sub_template'] = 'show_settings';

	$subActions = array(
		'general' => 'ModifyGeneralSettings',
		'database' => 'ModifyDatabaseSettings',
		'cookie' => 'ModifyCookieSettings',
		'cache' => 'ModifyCacheSettings',
		'loads' => 'ModifyLoadBalancingSettings',
	);

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Warn the user if there's any relevant information regarding Settings.php.
	if ($_REQUEST['sa'] != 'cache')
	{
		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable($boarddir . '/Settings.php');
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

		if ($settings_not_writable)
			$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br />';
		elseif ($settings_backup_fail)
			$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br />';

		$context['settings_not_writable'] = $settings_not_writable;
	}

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
}

// General forum settings - forum name, maintenance mode, etc.
function ModifyGeneralSettings($return_config = false)
{
	global $scripturl, $context, $txt;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('mbname', $txt['admin_title'], 'file', 'text', 30),
		'',
		array('maintenance', $txt['admin_maintain'], 'file', 'check'),
		array('mtitle', $txt['maintenance_subject'], 'file', 'text', 36),
		array('mmessage', $txt['maintenance_message'], 'file', 'text', 36),
		'',
		array('webmaster_email', $txt['admin_webmaster_email'], 'file', 'text', 30),
		'',
		array('enableCompressedOutput', $txt['enableCompressedOutput'], 'db', 'check', null, 'enableCompressedOutput'),
		array('disableTemplateEval', $txt['disableTemplateEval'], 'db', 'check', null, 'disableTemplateEval'),
		array('disableHostnameLookup', $txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=general;save';
	$context['settings_title'] = $txt['general_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Basic database and paths settings - database name, host, etc.
function ModifyDatabaseSettings($return_config = false)
{
	global $scripturl, $context, $settings, $txt, $boarddir;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('db_server', $txt['database_server'], 'file', 'text'),
		array('db_user', $txt['database_user'], 'file', 'text'),
		array('db_passwd', $txt['database_password'], 'file', 'password'),
		array('db_name', $txt['database_name'], 'file', 'text'),
		array('db_prefix', $txt['database_prefix'], 'file', 'text'),
		array('db_persist', $txt['db_persist'], 'file', 'check', null, 'db_persist'),
		array('db_error_send', $txt['db_error_send'], 'file', 'check'),
		array('ssi_db_user', $txt['ssi_db_user'], 'file', 'text', null, 'ssi_db_user'),
		array('ssi_db_passwd', $txt['ssi_db_passwd'], 'file', 'password'),
		'',
		array('autoFixDatabase', $txt['autoFixDatabase'], 'db', 'check', false, 'autoFixDatabase'),
		array('autoOptMaxOnline', $txt['autoOptMaxOnline'], 'db', 'int'),
		'',
		array('boardurl', $txt['admin_url'], 'file', 'text', 36),
		array('boarddir', $txt['boarddir'], 'file', 'text', 36),
		array('sourcedir', $txt['sourcesdir'], 'file', 'text', 36),
		array('cachedir', $txt['cachedir'], 'file', 'text', 36),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=database;save';
	$context['settings_title'] = $txt['database_paths_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// This function basically edits anything which is configuration and stored in the database, except for caching.
function ModifyCookieSettings($return_config = false)
{
	global $context, $scripturl, $txt, $sourcedir, $modSettings, $cookiename, $user_settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Cookies...
		array('cookiename', $txt['cookie_name'], 'file', 'text', 20),
		array('cookieTime', $txt['cookieTime'], 'db', 'int'),
		array('localCookies', $txt['localCookies'], 'db', 'check', false, 'localCookies'),
		array('globalCookies', $txt['globalCookies'], 'db', 'check', false, 'globalCookies'),
		array('secureCookies', $txt['secureCookies'], 'db', 'check', false, 'secureCookies',  'disabled' => !isset($_SERVER['HTTPS']) || !(strtolower($_SERVER['HTTPS']) == 'on' || strtolower($_SERVER['HTTPS']) == '1')),
		'',
		// Sessions
		array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
		array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
		array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime'),
	);

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cookie;save';
	$context['settings_title'] = $txt['cookies_sessions_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);

		// If the cookie name was changed, reset the cookie.
		if ($cookiename != $_POST['cookiename'])
		{
			$original_session_id = $context['session_id'];
			include_once($sourcedir . '/Subs-Auth.php');

			// Remove the old cookie.
			setLoginCookie(-3600, 0);

			// Set the new one.
			$cookiename = $_POST['cookiename'];
			setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

			redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $original_session_id, $context['server']['needs_login_fix']);
		}

		redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Simply modifying cache functions
function ModifyCacheSettings($return_config = false)
{
	global $context, $scripturl, $txt, $helptxt, $modSettings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a couple of settings, but they are important
		array('select', 'cache_enable', array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3'])),
		array('text', 'cache_memcached'),
	);

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		saveDBSettings($config_vars);

		// We have to manually force the clearing of the cache otherwise the changed settings might not get noticed.
		$modSettings['cache_enable'] = 1;
		cache_put_data('modSettings', null, 90);

		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];
	$context['settings_message'] = $txt['caching_information'];

	// Detect an optimizer?
	if (function_exists('eaccelerator_put'))
		$detected = 'eAccelerator';
	elseif (function_exists('mmcache_put'))
		$detected = 'MMCache';
	elseif (function_exists('apc_store'))
		$detected = 'APC';
	elseif (function_exists('output_cache_put'))
		$detected = 'Zend';
	elseif (function_exists('memcache_set'))
		$detected = 'Memcached';
	elseif (function_exists('xcache_set'))
		$detected = 'XCache';
	else
		$detected = 'no_caching';

	$context['settings_message'] = sprintf($context['settings_message'], $txt['detected_' . $detected]);

	// Prepare the template.
	prepareDBSettingContext($config_vars);
}

function ModifyLoadBalancingSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	// Setup a warning message, but disabled by default.
	$disabled = true;
	$context['settings_message'] = $txt['loadavg_disabled_conf'];

	if (strpos(strtolower(PHP_OS), 'win') === 0)
		$context['settings_message'] = $txt['loadavg_disabled_windows'];
	else
	{
		$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
		if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) !== 0)
			$modSettings['load_average'] = (float) $matches[1];
		elseif (($modSettings['load_average'] = @`uptime`) !== null && preg_match('~load averages?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) !== 0)
			$modSettings['load_average'] = (float) $matches[1];
		else
			unset($modSettings['load_average']);

		if (!empty($modSettings['load_average']))
		{
			$context['settings_message'] = sprintf($txt['loadavg_warning'], $modSettings['load_average']);
			$disabled = false;
		}
	}

	// Start with a simple checkbox.
	$config_vars = array(
		array('check', 'loadavg_enable'),
	);

	// Set the default values for each option.
	$default_values = array(
		'loadavg_auto_opt' => '1.0',
		'loadavg_search' => '2.5',
		'loadavg_allunread' => '2.0',
		'loadavg_unreadreplies' => '3.5',
		'loadavg_show_posts' => '2.0',
		'loadavg_forum' => '40.0',
	);

	// Loop through the settings.
	foreach ($default_values as $name => $value)
	{
		// Use the default value if the setting isn't set yet.
		$value = !isset($modSettings[$name]) ? $value : $modSettings[$name];
		$config_vars[] = array('text', $name, 'value' => $value, 'disabled' => $disabled);
	}

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=loads;save';
	$context['settings_title'] = $txt['load_balancing_settings'];

	// Saving?
	if (isset($_GET['save']))
	{
		// Stupidity is not allowed.
		foreach ($_POST as $key => $value)
		{
			if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable')
				continue;
			elseif ($key == 'loadavg_auto_opt' && $value <= 1)
				$_POST['loadavg_auto_opt'] = '1.0';
			elseif ($key == 'loadavg_forum' && $value < 10)
				$_POST['loadavg_forum'] = '10.0';
			elseif ($value < 2)
				$_POST[$key] = '2.0';
		}

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
	}

	prepareDBSettingContext($config_vars);
}

// This is the main function for the language area.
function ManageLanguages()
{
	global $context, $txt, $scripturl, $modSettings;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];
	$context['sub_template'] = 'show_settings';

	$subActions = array(
		'edit' => 'ModifyLanguages',
		'add' => 'AddLanguage',
		'settings' => 'ModifyLanguageSettings',
		'downloadlang' => 'DownloadLanguage',
		'editlang' => 'ModifyLanguage',
	);

	// By default we're managing languages.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['language_configuration'],
		'description' => $txt['language_description'],
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// Interface for adding a new language
function AddLanguage()
{
	global $context, $sourcedir, $forum_version, $boarddir, $txt, $smcFunc, $scripturl;

	// Are we searching for new languages courtesy of Simple Machines?
	if (!empty($_POST['smf_add_sub']))
	{
		// Need fetch_web_data.
		require_once($sourcedir . '/Subs-Package.php');

		$context['smf_search_term'] = htmlspecialchars(trim($_POST['smf_add']));

		// We're going to use this URL.
		$url = 'http://download.simplemachines.org/fetch_language.php?version=' . urlencode(strtr($forum_version, array('SMF ' => '')));

		// Load the class file and stick it into an array.
		loadClassFile('Class-Package.php');
		$language_list = new xmlArray(fetch_web_data($url), true);

		// Check it exists.
		if (!$language_list->exists('languages'))
			$context['smf_error'] = 'no_response';
		else
		{
			$language_list = $language_list->path('languages[0]');
			$lang_files = $language_list->set('language');
			$context['smf_languages'] = array();
			foreach ($lang_files as $file)
			{
				// Were we searching?
				if (!empty($context['smf_search_term']) && strpos($file->fetch('name'), $smcFunc['strtolower']($context['smf_search_term'])) === false)
					continue;

				$context['smf_languages'][] = array(
					'id' => $file->fetch('id'),
					'name' => $smcFunc['ucwords']($file->fetch('name')),
					'version' => $file->fetch('version'),
					'utf8' => $file->fetch('utf8'),
					'description' => $file->fetch('description'),
					'link' => $scripturl . '?action=admin;area=languages;sa=downloadlang;did=' . $file->fetch('id') . ';' . $context['session_var'] . '=' . $context['session_id'],
				);
			}
			if (empty($context['smf_languages']))
				$context['smf_error'] = 'no_files';
		}
	}

	$context['sub_template'] = 'add_language';
}

// Download a language file from the Simple Machines website.
function DownloadLanguage()
{
	global $context, $sourcedir, $forum_version, $boarddir, $txt, $smcFunc, $scripturl, $modSettings;

	loadLanguage('ManageSettings');
	require_once($sourcedir . '/Subs-Package.php');

	// Clearly we need to know what to request.
	if (!isset($_GET['did']))
		fatal_lang_error('no_access', false);

	// Some lovely context.
	$context['download_id'] = $_GET['did'];
	$context['sub_template'] = 'download_language';
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'add';

	// Can we actually do the installation - and do they want to?
	if (!empty($_POST['do_install']) && !empty($_POST['copy_file']))
	{
		checkSession('get');

		$chmod_files = array();
		$install_files = array();
		// Check writable status.
		foreach ($_POST['copy_file'] as $file)
		{
			// Check it's not very bad.
			if (strpos($file, '..') !== false || (substr($file, 0, 6) != 'Themes' && !preg_match('~agreement\.[A-Za-z-_0-9]+\.txt$~', $file)))
				fatal_error($txt['languages_download_illegal_paths']);

			$chmod_files[] = $boarddir . '/' . $file;
			$install_files[] = $file;
		}

		// Call this in case we have work to do.
		$file_status = create_chmod_control($chmod_files);
		$files_left = $file_status['files']['notwritable'];

		// Something not writable?
		if (!empty($files_left))
			$context['error_message'] = $txt['languages_download_not_chmod'];
		// Otherwise, go go go!
		elseif (!empty($install_files))
		{
			$archive_content = read_tgz_file('http://download.simplemachines.org/fetch_language.php?version=' . urlencode(strtr($forum_version, array('SMF ' => ''))) . ';fetch=' . urlencode($_GET['did']), $boarddir, false, true, $install_files);
			// Make sure the files aren't stuck in the cache.
			package_flush_cache();
			$context['install_complete'] = sprintf($txt['languages_download_complete_desc'], $scripturl . '?action=admin;area=languages');

			return;
		}
	}

	// Open up the old china.
	if (!isset($archive_content))
		$archive_content = read_tgz_file('http://download.simplemachines.org/fetch_language.php?version=' . urlencode(strtr($forum_version, array('SMF ' => ''))) . ';fetch=' . urlencode($_GET['did']), null);

	if (empty($archive_content))
		fatal_error($txt['add_language_error_no_response']);

	// Now for each of the files, let's do some *stuff*
	$context['files'] = array(
		'lang' => array(),
		'other' => array(),
	);
	$context['make_writable'] = array();
	foreach ($archive_content as $file)
	{
		$dirname = dirname($file['filename']);
		$filename = basename($file['filename']);
		$extension = substr($filename, strrpos($filename, '.') + 1);

		// Don't do anything with files we don't understand.
		if (!in_array($extension, array('php', 'jpg', 'gif', 'jpeg', 'png', 'txt')))
			continue;

		// Basic data.
		$context_data = array(
			'name' => $filename,
			'destination' => $boarddir . '/' . $file['filename'],
			'generaldest' => $file['filename'],
			'size' => $file['size'],
			// Does chmod status allow the copy?
			'writable' => false,
			// Should we suggest they copy this file?
			'default_copy' => true,
			// Does the file already exist, if so is it same or different?
			'exists' => false,
		);

		// Does the file exist, is it different and can we overwrite?
		if (file_exists($boarddir . '/' . $file['filename']))
		{
			if (is_writable($boarddir . '/' . $file['filename']))
				$context_data['writable'] = true;

			// Finally, do we actually think the content has changed?
			if ($file['size'] == filesize($boarddir . '/' . $file['filename']) && $file['md5'] == md5_file($boarddir . '/' . $file['filename']))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			// Attempt to discover newline character differences.
			elseif ($file['md5'] == md5(preg_replace("~[\r]?\n~", "\r\n", file_get_contents($boarddir . '/' . $file['filename']))))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			else
				$context_data['exists'] = 'different';
		}
		// No overwrite?
		else
		{
			// Can we at least stick it in the directory...
			if (is_writable($boarddir . '/' . $dirname))
				$context_data['writable'] = true;
		}

		// I love PHP files, that's why I'm a developer and not an artistic type spending my time drinking absinth and living a life of sin...
		if ($extension == 'php' && preg_match('~\w+\.\w+(?:-utf8)?\.php~', $filename))
		{
			$context_data += array(
				'version' => '??',
				'cur_version' => false,
				'version_compare' => 'newer',
			);

			list ($name, $language) = explode('.', $filename);

			// Let's get the new version, I like versions, they tell me that I'm up to date.
			if (preg_match('~\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '~i', $file['preview'], $match) == 1)
				$context_data['version'] = $match[1];

			// Now does the old file exist - if so what is it's version?
			if (file_exists($boarddir . '/' . $file['filename']))
			{
				// OK - what is the current version?
				$fp = fopen($boarddir . '/' . $file['filename'], 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Find the version.
				if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				{
					$context_data['cur_version'] = $match[1];

					// How does this compare?
					if ($context_data['cur_version'] == $context_data['version'])
						$context_data['version_compare'] = 'same';
					elseif ($context_data['cur_version'] > $context_data['version'])
						$context_data['version_compare'] = 'older';

					// Don't recommend copying if the version is the same.
					if ($context_data['version_compare'] != 'newer')
						$context_data['default_copy'] = false;
				}
			}

			// Add the context data to the main set.
			$context['files']['lang'][] = $context_data;
		}
		else
		{
			// If we think it's a theme thing, work out what the theme is.
			if (substr($dirname, 0, 6) == 'Themes' && preg_match('~Themes[\\/]([^\\/]+)[\\/]~', $dirname, $match))
				$theme_name = $match[1];
			else
				$theme_name = 'misc';

			// Assume it's an image, could be an acceptance note etc but rare.
			$context['files']['images'][$theme_name][] = $context_data;
		}

		// Collect together all non-writable areas.
		if (!$context_data['writable'])
			$context['make_writable'][] = $context_data['destination'];
	}

	// So, I'm a perfectionist - let's get the theme names.
	$theme_indexes = array();
	foreach ($context['files']['images'] as $k => $dummy)
		$indexes[] = $k;

	$context['theme_names'] = array();
	if (!empty($indexes))
	{
		$value_data = array(
			'query' => array(),
			'params' => array(),
		);

		foreach ($indexes as $k => $index)
		{
			$value_data['query'][] = 'value LIKE {string:value_' . $k . '}';
			$value_data['params']['value_' . $k] = '%' . $index;
		}

		$request = $smcFunc['db_query']('', '
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {string:theme_dir}
				AND (' . implode(' OR ', $value_data['query']) . ')',
			array_merge($value_data['params'], array(
				'no_member' => 0,
				'theme_dir' => 'theme_dir',
				'index_compare_explode' => 'value LIKE \'%' . implode('\' OR value LIKE \'%', $indexes) . '\'',
			))
		);
		$themes = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Find the right one.
			foreach ($indexes as $index)
				if (strpos($row['value'], $index) !== false)
					$themes[$row['id_theme']] = $index;
		}
		$smcFunc['db_free_result']($request);

		if (!empty($themes))
		{
			// Now we have the id_theme we can get the pretty description.
			$request = $smcFunc['db_query']('', '
				SELECT id_theme, value
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND variable = {string:name}
					AND id_theme IN ({array_int:theme_list})',
				array(
					'theme_list' => array_keys($themes),
					'no_member' => 0,
					'name' => 'name',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Now we have it...
				$context['theme_names'][$themes[$row['id_theme']]] = $row['value'];
			}
			$smcFunc['db_free_result']($request);
		}
	}

	// Before we go to far can we make anything writable, eh, eh?
	if (!empty($context['make_writable']))
	{
		// What is left to be made writable?
		$file_status = create_chmod_control($context['make_writable']);
		$context['still_not_writable'] = $file_status['files']['notwritable'];

		// Mark those which are now writable as such.
		foreach ($context['files'] as $type => $data)
		{
			if ($type == 'lang')
			{
				foreach ($data as $k => $file)
					if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
						$context['files'][$type][$k]['writable'] = true;
			}
			else
			{
				foreach ($data as $theme => $files)
					foreach ($files as $k => $file)
						if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
							$context['files'][$type][$theme][$k]['writable'] = true;
			}
		}

		// Are we going to need more language stuff?
		if (!empty($context['still_not_writable']))
			loadLanguage('Packages');
	}

	// This is the list for the main files.
	$listOptions = array(
		'id' => 'lang_main_files_list',
		'title' => $txt['languages_download_main_files'],
		'get_items' => array(
			'function' => create_function('', '
				global $context;
				return $context[\'files\'][\'lang\'];
			'),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['languages_download_filename'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $context, $txt;

						return \'<strong>\' . $rowData[\'name\'] . \'</strong><br /><span class="smalltext">\' . $txt[\'languages_download_dest\'] . \': \' . $rowData[\'destination\'] . \'</span>\' . ($rowData[\'version_compare\'] == \'older\' ? \'<br />\' . $txt[\'languages_download_older\'] : \'\');
					'),
				),
			),
			'writable' => array(
				'header' => array(
					'value' => $txt['languages_download_writable'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return \'<span style="color: \' . ($rowData[\'writable\'] ? \'green\' : \'red\') . \';">\' . ($rowData[\'writable\'] ? $txt[\'yes\'] : $txt[\'no\']) . \'</span>\';
					'),
					'style' => 'text-align: center',
				),
			),
			'version' => array(
				'header' => array(
					'value' => $txt['languages_download_version'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return \'<span style="color: \' . ($rowData[\'version_compare\'] == \'older\' ? \'red\' : ($rowData[\'version_compare\'] == \'same\' ? \'orange\' : \'green\')) . \';">\' . $rowData[\'version\'] . \'</span>\';
					'),
				),
			),
			'exists' => array(
				'header' => array(
					'value' => $txt['languages_download_exists'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $rowData[\'exists\'] ? ($rowData[\'exists\'] == \'same\' ? $txt[\'languages_download_exists_same\'] : $txt[\'languages_download_exists_different\']) : $txt[\'no\'];
					'),
				),
			),
			'copy' => array(
				'header' => array(
					'value' => $txt['languages_download_copy'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="checkbox" name="copy_file[]" value="\' . $rowData[\'generaldest\'] . \'" \' . ($rowData[\'default_copy\'] ? \'checked="checked"\' : \'\') . \' class="input_check" />\';
					'),
					'style' => 'text-align: center; width: 4%;',
				),
			),
		),
	);

	// Kill the cache, as it is now invalid..
	if (!empty($modSettings['cache_enable']))
	{
		cache_put_data('known_languages', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
		cache_put_data('known_languages_all', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['default_list'] = 'lang_main_files_list';
}

// This lists all the current languages and allows editing of them.
function ModifyLanguages()
{
	global $txt, $context, $scripturl;
	global $user_info, $smcFunc, $sourcedir, $language, $boarddir, $forum_version;

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();

		if ($_POST['def_language'] != $language)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('language' => '\'' . $_POST['def_language'] . '\''));
			$language = $_POST['def_language'];
		}
	}

	$listOptions = array(
		'id' => 'language_list',
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=languages',
		'title' => $txt['edit_languages'],
		'get_items' => array(
			'function' => 'list_getLanguages',
		),
		'get_count' => array(
			'function' => 'list_getNumLanguages',
		),
		'columns' => array(
			'default' => array(
				'header' => array(
					'value' => $txt['languages_default'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="radio" name="def_language" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'default\'] ? \'checked="checked"\' : \'\') . \' onclick="highlightSelected(\\\'list_language_list_\' . $rowData[\'id\'] . \'\\\');" class="input_radio" />\';
					'),
					'style' => 'text-align: center; width: 8%;',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['languages_lang_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $context;

						return sprintf(\'<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>\', $scripturl, $rowData[\'id\'], $rowData[\'name\']);
					'),
				),
			),
			'character_set' => array(
				'header' => array(
					'value' => $txt['languages_character_set'],
				),
				'data' => array(
					'db_htmlsafe' => 'char_set',
				),
			),
			'count' => array(
				'header' => array(
					'value' => $txt['languages_users'],
				),
				'data' => array(
					'db_htmlsafe' => 'count',
					'style' => 'text-align: center',
				),
			),
			'locale' => array(
				'header' => array(
					'value' => $txt['languages_locale'],
				),
				'data' => array(
					'db_htmlsafe' => 'locale',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=languages',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" /><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable($boarddir . '/Settings.php') ? '' : ' disabled="disabled"') . ' class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
		// For highlighting the default.
		'javascript' => '
					var prevClass = "";
					var prevDiv = "";
					function highlightSelected(box)
					{
						if (prevClass != "")
							prevDiv.className = prevClass;

						prevDiv = document.getElementById(box);
						prevClass = prevDiv.className;

						prevDiv.className = "highlight2";
					}
					highlightSelected("list_language_list_' . ($language == '' ? 'english' : $language). '");
		',
	);

	// Display a warning if we cannot edit the default setting.
	if (!is_writable($boarddir . '/Settings.php'))
		$listOptions['additional_rows'][] = array(
				'position' => 'after_title',
				'value' => $txt['language_settings_writable'],
				'class' => 'smalltext alert',
			);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'language_list';
}

// How many languages?
function list_getNumLanguages()
{
	global $settings;

	// Return how many we have.
	return count(getLanguages(true, false));
}

// Fetch the actual language information.
function list_getLanguages()
{
	global $settings, $smcFunc, $language, $context, $txt;

	$languages = array();
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $settings['actual_theme_dir'];
	$backup_base_theme_dir = !empty($settings['base_theme_dir']) ? $settings['base_theme_dir'] : '';

	// Override these for now.
	$settings['actual_theme_dir'] = $settings['base_theme_dir'] = $settings['default_theme_dir'];
	getLanguages(true, false);

	// Put them back.
	$settings['actual_theme_dir'] = $backup_actual_theme_dir;
	if (!empty($backup_base_theme_dir))
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	else
		unset($settings['base_theme_dir']);

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		require($settings['default_theme_dir'] . '/languages/index.' . $lang['filename'] . '.php');

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'char_set' => $txt['lang_character_set'],
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => $smcFunc['ucwords'](strtr($lang['filename'], array('_' => ' ', '-utf8' => ''))),
		);
	}

	// Work out how many people are using each language.
	$request = $smcFunc['db_query']('', '
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	$smcFunc['db_free_result']($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

// Edit language related settings.
function ModifyLanguageSettings($return_config = false)
{
	global $scripturl, $context, $txt, $boarddir, $settings, $smcFunc;

	// Warn the user if the backup of Settings.php failed.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
		array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
	);

	if ($return_config)
		return $config_vars;

	// Get our languages. No cache and use utf8.
	getLanguages(false, false);
	foreach ($context['languages'] as $lang)
		$config_vars['language'][4][$lang['filename']] = array($lang['filename'], strtr($lang['name'], array('-utf8' => ' (UTF-8)')));

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		checkSession();
		saveSettings($config_vars);
		redirectexit('action=admin;area=languages;sa=settings');
	}

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=languages;sa=settings;save';
	$context['settings_title'] = $txt['language_settings'];
	$context['save_disabled'] = $settings_not_writable;

	if ($settings_not_writable)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br />';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br />';

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Edit a particular set of language entries.
function ModifyLanguage()
{
	global $settings, $context, $smcFunc, $txt, $modSettings, $boarddir, $sourcedir, $language;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];
	$context['sub_template'] = 'modify_language_entries';

	$context['lang_id'] = $_GET['lid'];
	list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '+') === false ? array(1, '') : explode('+', $_REQUEST['tfid']);

	// Clean the ID - just in case.
	preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
	$context['lang_id'] = $matches[1];

	// Get all the theme data.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	// This will be where we look
	$lang_dirs = array();
	// Check we have themes with a path and a name - just in case - and add the path.
	foreach ($themes as $id => $data)
	{
		if (count($data) != 2)
			unset($themes[$id]);
		elseif (is_dir($data['theme_dir'] . '/languages'))
			$lang_dirs[$id] = $data['theme_dir'] . '/languages';

		// How about image directories?
		if (is_dir($data['theme_dir'] . '/images/' . $context['lang_id']))
			$images_dirs[$id] = $data['theme_dir'] . '/images/' . $context['lang_id'];
	}

	$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $file_id . '.' . $context['lang_id'] . '.php' : '';

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $theme => $theme_dir)
	{
		// Open it up.
		$dir = dir($theme_dir);
		while ($entry = $dir->read())
		{
			// We're only after the files for this language.
			if (preg_match('~^([A-Za-z]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
				continue;

			//!!! Temp!
			if ($matches[1] == 'EmailTemplates')
				continue;

			if (!isset($context['possible_files'][$theme]))
				$context['possible_files'][$theme] = array(
					'id' => $theme,
					'name' => $themes[$theme]['name'],
					'files' => array(),
				);

			$context['possible_files'][$theme]['files'][] = array(
				'id' => $matches[1],
				'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
				'selected' => $theme_id == $theme && $file_id == $matches[1],
			);
		}
		$dir->close();
	}

	// We no longer wish to speak this language.
	if (!empty($_POST['delete_main']) && $context['lang_id'] != 'english')
	{
		checkSession();

		// !!! Todo: FTP Controls?
		require_once($sourcedir . '/Subs-Package.php');

		// First, Make a backup?
		if (!empty($modSettings['package_make_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != $context['lang_id'] . '$$$'))
		{
			$_SESSION['last_backup_for'] = $context['lang_id'] . '$$$';
			package_create_backup('backup_lang_' . $context['lang_id']);
		}

		// Second, loop through the array to remove the files.
		foreach ($lang_dirs as $curPath)
		{
			foreach ($context['possible_files'][1]['files'] as $lang)
				if (file_exists($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php'))
					unlink($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php');

			// Check for the email template.
			if (file_exists($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php'))
				unlink($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php');
		}

		// Third, the agreement file.
		if (file_exists($boarddir . '/agreement.' . $context['lang_id'] . '.txt'))
			unlink($boarddir . '/agreement.' . $context['lang_id'] . '.txt');

		// Fourth, a related images folder?
		foreach ($images_dirs as $curPath)
			if (is_dir($curPath))
				deltree($curPath);

		// Members can no longer use this language.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET lngfile = {string:empty_string}
			WHERE lngfile = {string:current_language}',
			array(
				'empty_string' => '',
				'current_language' => $context['lang_id'],
			)
		);

		// Fifth, update getLanguages() cache.
		if (!empty($modSettings['cache_enable']))
		{
			cache_put_data('known_languages', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
			cache_put_data('known_languages_all', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
		}

		// Sixth, if we deleted the default language, set us back to english?
		if ($context['lang_id'] == $language)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			$language = 'english';
			updateSettingsFile(array('language' => '\'' . $language . '\''));
		}

		// Seventh, get out of here.
		redirectexit('action=admin;area=languages;sa=edit;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Saving primary settings?
	$madeSave = false;
	if (!empty($_POST['save_main']) && !$current_file)
	{
		checkSession();

		// Read in the current file.
		$current_data = implode('', file($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php'));
		// These are the replacements. old => new
		$replace_array = array(
			'~\$txt\[\'lang_character_set\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_character_set\'] = \'' . addslashes($_POST['character_set']) . '\';',
			'~\$txt\[\'lang_locale\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_locale\'] = \'' . addslashes($_POST['locale']) . '\';',
			'~\$txt\[\'lang_dictionary\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_dictionary\'] = \'' . addslashes($_POST['dictionary']) . '\';',
			'~\$txt\[\'lang_spelling\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_spelling\'] = \'' . addslashes($_POST['spelling']) . '\';',
			'~\$txt\[\'lang_rtl\'\]\s=\s[A-Za-z0-9]+;~' => '$txt[\'lang_rtl\'] = ' . (!empty($_POST['rtl']) ? 'true' : 'false') . ';',
		);
		$current_data = preg_replace(array_keys($replace_array), array_values($replace_array), $current_data);
		$fp = fopen($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php', 'w+');
		fwrite($fp, $current_data);
		fclose($fp);

		$madeSave = true;
	}

	// Quickly load index language entries.
	$old_txt = $txt;
	require($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	$context['lang_file_not_writable_message'] = is_writable($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php') ? '' : sprintf($txt['lang_file_not_writable'], $settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	// Setup the primary settings context.
	$context['primary_settings'] = array(
		'name' => $smcFunc['ucwords'](strtr($context['lang_id'], array('_' => ' ', '-utf8' => ''))),
		'character_set' => $txt['lang_character_set'],
		'locale' => $txt['lang_locale'],
		'dictionary' => $txt['lang_dictionary'],
		'spelling' => $txt['lang_spelling'],
		'rtl' => $txt['lang_rtl'],
	);

	// Restore normal service.
	$txt = $old_txt;

	// Are we saving?
	$save_strings = array();
	if (isset($_POST['save_entries']) && !empty($_POST['entry']))
	{
		checkSession();

		// Clean each entry!
		foreach ($_POST['entry'] as $k => $v)
		{
			// Only try to save if it's changed!
			if ($_POST['entry'][$k] != $_POST['comp'][$k])
				$save_strings[$k] = cleanLangString($v, false);
		}
	}

	// If we are editing a file work away at that.
	if ($current_file)
	{
		$context['entries_not_writable_message'] = is_writable($current_file) ? '' : sprintf($txt['lang_entries_not_writable'], $current_file);

		$entries = array();
		// We can't just require it I'm afraid - otherwise we pass in all kinds of variables!
		$multiline_cache = '';
		foreach (file($current_file) as $line)
		{
			// Got a new entry?
			if ($line[0] == '$' && !empty($multiline_cache))
			{
				preg_match('~\$(helptxt|txt)\[\'(.+)\'\]\s=\s(.+);~', strtr($multiline_cache, array("\n" => '', "\t" => '')), $matches);
				if (!empty($matches[3]))
				{
					$entries[$matches[2]] = array(
						'type' => $matches[1],
						'full' => $matches[0],
						'entry' => $matches[3],
					);
					$multiline_cache = '';
				}
			}
			$multiline_cache .= $line . "\n";
		}
		// Last entry to add?
		if ($multiline_cache)
		{
			preg_match('~\$(helptxt|txt)\[\'(.+)\'\]\s=\s(.+);~', strtr($multiline_cache, array("\n" => '', "\t" => '')), $matches);
			if (!empty($matches[3]))
				$entries[$matches[2]] = array(
					'type' => $matches[1],
					'full' => $matches[0],
					'entry' => $matches[3],
				);
		}

		// These are the entries we can definitely save.
		$final_saves = array();

		$context['file_entries'] = array();
		foreach ($entries as $entryKey => $entryValue)
		{
			// Ignore some things we set separately.
			$ignore_files = array('lang_character_set', 'lang_locale', 'lang_dictionary', 'lang_spelling', 'lang_rtl');
			if (in_array($entryKey, $ignore_files))
				continue;

			// These are arrays that need breaking out.
			$arrays = array('days', 'days_short', 'months', 'months_titles', 'months_short');
			if (in_array($entryKey, $arrays))
			{
				// Get off the first bits.
				$entryValue['entry'] = substr($entryValue['entry'], strpos($entryValue['entry'], '(') + 1, strrpos($entryValue['entry'], ')') - strpos($entryValue['entry'], '('));
				$entryValue['entry'] = explode(',', strtr($entryValue['entry'], array(' ' => '')));

				// Now create an entry for each item.
				$cur_index = 0;
				$save_cache = array(
					'enabled' => false,
					'entries' => array(),
				);
				foreach ($entryValue['entry'] as $id => $subValue)
				{
					// Is this a new index?
					if (preg_match('~^(\d+)~', $subValue, $matches))
					{
						$cur_index = $matches[1];
						$subValue = substr($subValue, strpos($subValue, '\''));
					}

					// Clean up some bits.
					$subValue = strtr($subValue, array('"' => '', '\'' => '', ')' => ''));

					// Can we save?
					if (isset($save_strings[$entryKey . '-+- ' . $cur_index]))
					{
						$save_cache['entries'][$cur_index] = strtr($save_strings[$entryKey . '-+- ' . $cur_index], array('\'' => ''));
						$save_cache['enabled'] = true;
					}
					else
						$save_cache['entries'][$cur_index] = $subValue;

					$context['file_entries'][] = array(
						'key' => $entryKey . '-+- ' . $cur_index,
						'value' => $subValue,
						'rows' => 1,
					);
					$cur_index++;
				}

				// Do we need to save?
				if ($save_cache['enabled'])
				{
					// Format the string, checking the indexes first.
					$items = array();
					$cur_index = 0;
					foreach ($save_cache['entries'] as $k2 => $v2)
					{
						// Manually show the custom index.
						if ($k2 != $cur_index)
						{
							$items[] = $k2 . ' => \'' . $v2 . '\'';
							$cur_index = $k2;
						}
						else
							$items[] = '\'' . $v2 . '\'';

						$cur_index++;
					}
					// Now create the string!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = array(' . implode(', ', $items) . ');',
					);
				}
			}
			else
			{
				// Saving?
				if (isset($save_strings[$entryKey]) && $save_strings[$entryKey] != $entryValue['entry'])
				{
					// !!! Fix this properly.
					if ($save_strings[$entryKey] == '')
						$save_strings[$entryKey] = '\'\'';

					// Set the new value.
					$entryValue['entry'] = $save_strings[$entryKey];
					// And we know what to save now!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = ' . $save_strings[$entryKey] . ';',
					);
				}

				$editing_string = cleanLangString($entryValue['entry'], true);
				$context['file_entries'][] = array(
					'key' => $entryKey,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
				);
			}
		}

		// Any saves to make?
		if (!empty($final_saves))
		{
			checkSession();

			$file_contents = implode('', file($current_file));
			foreach ($final_saves as $save)
				$file_contents = strtr($file_contents, array($save['find'] => $save['replace']));

			// Save the actual changes.
			$fp = fopen($current_file, 'w+');
			fwrite($fp, $file_contents);
			fclose($fp);

			$madeSave = true;
		}

		// Another restore.
		$txt = $old_txt;
	}

	// If we saved, redirect.
	if ($madeSave)
		redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);
}

// This function could be two functions - either way it cleans language entries to/from display.
function cleanLangString($string, $to_display = true)
{
	global $smcFunc;

	// If going to display we make sure it doesn't have any HTML in it - etc.
	$new_string = '';
	if ($to_display)
	{
		// Are we in a string (0 = no, 1 = single quote, 2 = parsed)
		$in_string = 0;
		$is_escape = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle ecapes first.
			if ($string{$i} == '\\')
			{
				// Toggle the escape.
				$is_escape = !$is_escape;
				// If we're now escaped don't add this string.
				if ($is_escape)
					continue;
			}
			// Special case - parsed string with line break etc?
			elseif (($string{$i} == 'n' || $string{$i} == 't') && $in_string == 2 && $is_escape)
			{
				// Put the escape back...
				$new_string .= $string{$i} == 'n' ? "\n" : "\t";
				$is_escape = false;
				continue;
			}
			// Have we got a single quote?
			elseif ($string{$i} == '\'')
			{
				// Already in a parsed string, or escaped in a linear string, means we print it - otherwise something special.
				if ($in_string != 2 && ($in_string != 1 || !$is_escape))
				{
					// Is it the end of a single quote string?
					if ($in_string == 1)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 1;

					// Don't actually include this character!
					continue;
				}
			}
			// Otherwise a double quote?
			elseif ($string{$i} == '"')
			{
				// Already in a single quote string, or escaped in a parsed string, means we print it - otherwise something special.
				if ($in_string != 1 && ($in_string != 2 || !$is_escape))
				{
					// Is it the end of a double quote string?
					if ($in_string == 2)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 2;

					// Don't actually include this character!
					continue;
				}
			}
			// A join/space outside of a string is simply removed.
			elseif ($in_string == 0 && (empty($string{$i}) || $string{$i} == '.'))
				continue;
			// Start of a variable?
			elseif ($in_string == 0 && $string{$i} == '$')
			{
				// Find the whole of it!
				preg_match('~([\$A-Za-z0-9\'\[\]_-]+)~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					// Come up with some pseudo thing to indicate this is a var.
					//!!! Do better than this, please!
					$new_string .= '{%' . $matches[1] . '%}';

					// We're not going to reparse this.
					$i += strlen($matches[1]) - 1;
				}

				continue;
			}
			// Right, if we're outside of a string we have DANGER, DANGER!
			elseif ($in_string == 0)
			{
				continue;
			}

			// Actually add the character to the string!
			$new_string .= $string{$i};
			// If anything was escaped it ain't any longer!
			$is_escape = false;
		}

		// Unhtml then rehtml the whole thing!
		$new_string = htmlspecialchars(un_htmlspecialchars($new_string));
	}
	else
	{
		// Keep track of what we're doing...
		$in_string = 0;
		// This is for deciding whether to HTML a quote.
		$in_html = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle line breaks!
			if ($string{$i} == "\n" || $string{$i} == "\t")
			{
				// Are we in a string? Is it the right type?
				if ($in_string == 1)
				{
					// Change type!
					$new_string .= '\' . "\\' . ($string{$i} == "\n" ? 'n' : 't');
					$in_string = 2;
				}
				elseif ($in_string == 2)
					$new_string .= '\\' . ($string{$i} == "\n" ? 'n' : 't');
				// Otherwise start one off - joining if required.
				else
					$new_string .= ($new_string ? ' . ' : '') . '"\\' . ($string{$i} == "\n" ? 'n' : 't');

				continue;
			}
			// We don't do parsed strings apart from for breaks.
			elseif ($in_string == 2)
			{
				$in_string = 0;
				$new_string .= '"';
			}

			// Not in a string yet?
			if ($in_string != 1)
			{
				$in_string = 1;
				$new_string .= ($new_string ? ' . ' : '') . '\'';
			}

			// Is this a variable?
			if ($string{$i} == '{' && $string{$i + 1} == '%' && $string{$i + 2} == '$')
			{
				// Grab the variable.
				preg_match('~\{%([\$A-Za-z0-9\'\[\]_-]+)%\}~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					if ($in_string == 1)
						$new_string .= '\' . ';
					elseif ($new_string)
						$new_string .= ' . ';

					$new_string .= $matches[1];
					$i += strlen($matches[1]) + 3;
					$in_string = 0;
				}

				continue;
			}
			// Is this a lt sign?
			elseif ($string{$i} == '<')
			{
				// Probably HTML?
				if ($string{$i + 1} != ' ')
					$in_html = true;
				// Assume we need an entity...
				else
				{
					$new_string .= '&lt;';
					continue;
				}
			}
			// What about gt?
			elseif ($string{$i} == '>')
			{
				// Will it be HTML?
				if ($in_html)
					$in_html = false;
				// Otherwise we need an entity...
				else
				{
					$new_string .= '&gt;';
					continue;
				}
			}
			// Is it a slash? If so escape it...
			if ($string{$i} == '\\')
				$new_string .= '\\';
			// The infamous double quote?
			elseif ($string{$i} == '"')
			{
				// If we're in HTML we leave it as a quote - otherwise we entity it.
				if (!$in_html)
				{
					$new_string .= '&quot;';
					continue;
				}
			}
			// A single quote?
			elseif ($string{$i} == '\'')
			{
				// Must be in a string so escape it.
				$new_string .= '\\';
			}

			// Finally add the character to the string!
			$new_string .= $string{$i};
		}

		// If we ended as a string then close it off.
		if ($in_string == 1)
			$new_string .= '\'';
		elseif ($in_string == 2)
			$new_string .= '"';
	}

	return $new_string;
}

// Helper function, it sets up the context for the manage server settings.
function prepareServerSettingsContext(&$config_vars)
{
	global $context, $modSettings;

	$context['config_vars'] = array();
	foreach ($config_vars as $identifier => $config_var)
	{
		if (!is_array($config_var) || !isset($config_var[1]))
			$context['config_vars'][] = $config_var;
		else
		{
			$varname = $config_var[0];
			global $$varname;

			$context['config_vars'][] = array(
				'label' => $config_var[1],
				'help' => isset($config_var[5]) ? $config_var[5] : '',
				'type' => $config_var[3],
				'size' => empty($config_var[4]) ? 0 : $config_var[4],
				'data' => isset($config_var[4]) && is_array($config_var[4]) ? $config_var[4] : array(),
				'name' => $config_var[0],
				'value' => $config_var[2] == 'file' ? htmlspecialchars($$varname) : (isset($modSettings[$config_var[0]]) ? htmlspecialchars($modSettings[$config_var[0]]) : (in_array($config_var[3], array('int', 'float')) ? 0 : '')),
				'disabled' => !empty($context['settings_not_writable']) || !empty($config_var['disabled']),
				'invalid' => false,
				'javascript' => '',
				'preinput' => '',
				'postinput' => '',
			);
		}
	}
}

// Helper function, it sets up the context for database settings.
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $helptxt, $context, $modSettings, $sourcedir;

	loadLanguage('Help');

	$context['config_vars'] = array();
	$inlinePermissions = array();
	$bbcChoice = array();
	foreach ($config_vars as $config_var)
	{
		// HR?
		if (!is_array($config_var))
			$context['config_vars'][] = $config_var;
		else
		{
			// If it has no name it doesn't have any purpose!
			if (empty($config_var[1]))
				continue;

			// Special case for inline permissions
			if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				$inlinePermissions[] = $config_var[1];
			elseif ($config_var[0] == 'permissions')
				continue;

			// Are we showing the BBC selection box?
			if ($config_var[0] == 'bbc')
				$bbcChoice[] = $config_var[1];

			$context['config_vars'][$config_var[1]] = array(
				'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
				'help' => isset($helptxt[$config_var[1]]) ? $config_var[1] : '',
				'type' => $config_var[0],
				'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0),
				'data' => array(),
				'name' => $config_var[1],
				'value' => isset($modSettings[$config_var[1]]) ? ($config_var[0] == 'select' ? $modSettings[$config_var[1]] : htmlspecialchars($modSettings[$config_var[1]])) : (in_array($config_var[0], array('int', 'float')) ? 0 : ''),
				'disabled' => false,
				'invalid' => !empty($config_var['invalid']),
				'javascript' => '',
				'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
				'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
			);

			// If this is a select box handle any data.
			if (!empty($config_var[2]) && is_array($config_var[2]))
			{
				// If we allow multiple selections, we need to adjust a few things.
				if ($config_var[0] == 'select' && !empty($config_var['multiple']))
				{
					$context['config_vars'][$config_var[1]]['name'] .= '[]';
					$context['config_vars'][$config_var[1]]['value'] = unserialize($context['config_vars'][$config_var[1]]['value']);
				}

				// If it's associative
				if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
				else
				{
					foreach ($config_var[2] as $key => $item)
						$context['config_vars'][$config_var[1]]['data'][] = array($key, $item);
				}
			}

			// Finally allow overrides - and some final cleanups.
			foreach ($config_var as $k => $v)
			{
				if (!is_numeric($k))
				{
					if (substr($k, 0, 2) == 'on')
						$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
					else
						$context['config_vars'][$config_var[1]][$k] = $v;
				}

				// See if there are any other labels that might fit?
				if (isset($txt['setting_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
				elseif (isset($txt['groups_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
			}

			// Set the subtext in case it's part of the label.
			// !!! Temporary. Preventing divs inside label tags.
			$divPos = strpos($context['config_vars'][$config_var[1]]['label'], '<div');
			if ($divPos !== false)
			{
				$context['config_vars'][$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr($context['config_vars'][$config_var[1]]['label'], $divPos));
				$context['config_vars'][$config_var[1]]['label'] = substr($context['config_vars'][$config_var[1]]['label'], 0, $divPos);
			}
		}
	}

	// If we have inline permissions we need to prep them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		require_once($sourcedir . '/ManagePermissions.php');
		init_inline_permissions($inlinePermissions, isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());
	}

	// What about any BBC selection boxes?
	if (!empty($bbcChoice))
	{
		// What are the options, eh?
		$temp = parse_bbc(false);
		$bbcTags = array();
		foreach ($temp as $tag)
			$bbcTags[] = $tag['tag'];

		$bbcTags = array_unique($bbcTags);
		$totalTags = count($bbcTags);

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Start working out the context stuff.
		$context['bbc_columns'] = array();
		$tagsPerColumn = ceil($totalTags / $numColumns);

		$col = 0; $i = 0;
		foreach ($bbcTags as $tag)
		{
			if ($i % $tagsPerColumn == 0 && $i != 0)
				$col++;

			$context['bbc_columns'][$col][] = array(
				'tag' => $tag,
				// !!! 'tag_' . ?
				'show_help' => isset($helptxt[$tag]),
			);

			$i++;
		}

		// Now put whatever BBC options we may have into context too!
		$context['bbc_sections'] = array();
		foreach ($bbcChoice as $bbc)
		{
			$context['bbc_sections'][$bbc] = array(
				'title' => isset($txt['bbc_title_' . $bbc]) ? $txt['bbc_title_' . $bbc] : $txt['bbcTagsToUse_select'],
				'disabled' => empty($modSettings['bbc_disabled_' . $bbc]) ? array() : $modSettings['bbc_disabled_' . $bbc],
				'all_selected' => empty($modSettings['bbc_disabled_' . $bbc]),
			);
		}
	}
}

// Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
function saveSettings(&$config_vars)
{
	global $boarddir, $sc, $cookiename, $modSettings, $user_settings;
	global $sourcedir, $context, $cachedir;

	// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
	if (isset($_POST['cookiename']))
		$_POST['cookiename'] = preg_replace('~[,;\s\.$]+~' . ($context['utf8'] ? 'u' : ''), '', $_POST['cookiename']);

	// Fix the forum's URL if necessary.
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
	}

	// Any passwords?
	$config_passwords = array(
		'db_passwd',
		'ssi_db_passwd',
	);

	// All the strings to write.
	$config_strs = array(
		'mtitle', 'mmessage',
		'language', 'mbname', 'boardurl',
		'cookiename',
		'webmaster_email',
		'db_name', 'db_user', 'db_server', 'db_prefix', 'ssi_db_user',
		'boarddir', 'sourcedir', 'cachedir',
	);
	// All the numeric variables.
	$config_ints = array(
	);
	// All the checkboxes.
	$config_bools = array(
		'db_persist', 'db_error_send',
		'maintenance',
	);

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = array();
	foreach ($config_passwords as $config_var)
	{
		if (isset($_POST[$config_var][1]) && $_POST[$config_var][0] == $_POST[$config_var][1])
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var][0], '\'\\') . '\'';
	}
	foreach ($config_strs as $config_var)
	{
		if (isset($_POST[$config_var]))
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var], '\'\\') . '\'';
	}
	foreach ($config_ints as $config_var)
	{
		if (isset($_POST[$config_var]))
			$new_settings[$config_var] = (int) $_POST[$config_var];
	}
	foreach ($config_bools as $key)
	{
		if (!empty($_POST[$key]))
			$new_settings[$key] = '1';
		else
			$new_settings[$key] = '0';
	}

	// Save the relevant settings in the Settings.php file.
	require_once($sourcedir . '/Subs-Admin.php');
	updateSettingsFile($new_settings);

	// Now loopt through the remaining (database-based) settings.
	$new_settings = array();
	foreach ($config_vars as $config_var)
	{
		// We just saved the file-based settings, so skip their definitions.
		if (!is_array($config_var) || $config_var[2] == 'file')
			continue;

		// Rewrite the definition a bit.
		$new_settings[] = array($config_var[3], $config_var[0]);
	}

	// Save the new database-based settings, if any.
	if (!empty($new_settings))
		saveDBSettings($new_settings);
}

// Helper function for saving database settings.
function saveDBSettings(&$config_vars)
{
	global $sourcedir, $context;

	$inlinePermissions = array();
	foreach ($config_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			continue;

		// Checkboxes!
		elseif ($var[0] == 'check')
			$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
		// Select boxes!
		elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
			$setArray[$var[1]] = $_POST[$var[1]];
		elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != array())
		{
			// For security purposes we validate this line by line.
			$options = array();
			foreach ($_POST[$var[1]] as $invar)
				if (in_array($invar, array_keys($var[2])))
					$options[] = $invar;

			$setArray[$var[1]] = serialize($options);
		}
		// Integers!
		elseif ($var[0] == 'int')
			$setArray[$var[1]] = (int) $_POST[$var[1]];
		// Floating point!
		elseif ($var[0] == 'float')
			$setArray[$var[1]] = (float) $_POST[$var[1]];
		// Text!
		elseif ($var[0] == 'text' || $var[0] == 'large_text')
			$setArray[$var[1]] = $_POST[$var[1]];
		// Passwords!
		elseif ($var[0] == 'password')
		{
			if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
				$setArray[$var[1]] = $_POST[$var[1]][0];
		}
		// BBC.
		elseif ($var[0] == 'bbc')
		{

			$bbcTags = array();
			foreach (parse_bbc(false) as $tag)
				$bbcTags[] = $tag['tag'];

			if (!isset($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = array();
			elseif (!is_array($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = array($_POST[$var[1] . '_enabledTags']);

			$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
		}
		// Permissions?
		elseif ($var[0] == 'permissions')
			$inlinePermissions[] = $var[1];
	}

	if (!empty($setArray))
		updateSettings($setArray);

	// If we have inline permissions we need to save them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		require_once($sourcedir . '/ManagePermissions.php');
		save_inline_permissions($inlinePermissions);
	}
}

?>