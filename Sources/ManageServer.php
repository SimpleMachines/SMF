<?php

/**
 * Contains all the functionality required to be able to edit the core server
 * settings. This includes anything from which an error may result in the forum
 * destroying itself in a firey fury.
 *
 * Adding options to one of the setting screens isn't hard. Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 * 		array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 * 		array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In these cases, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 * 		array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 * 		array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 * 		array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 * 		array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 * 		array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 * 		array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 * 		Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 * 		array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 * 		array('permissions', 'manage_groups'),
 * - A BBC selection box.
 * 		array('bbc', 'sig_bbc'),
 *
 * For each option:
 * 	- type (see above), variable name, size/possible values.
 * 	  OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *  	array('text', 'invalidlabel', 3, 'label' => 'Actual Label')
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2013 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
 * the appropriate one based on the sub-action.
 *
 * Requires the admin_forum permission.
 * Redirects to the appropriate function based on the sub-action.
 *
 * @uses edit_settings adminIndex.
 */
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
		'phpinfo' => 'ShowPHPinfoSettings',
	);

	call_integration_hook('integrate_server_settings', array(&$subActions));

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Any messages to speak of?
	$context['settings_message'] = (isset($_REQUEST['msg']) && isset($txt[$_REQUEST['msg']])) ? $txt[$_REQUEST['msg']] : '';

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

/**
 * General forum settings - forum name, maintenance mode, etc.
 * Practically, this shows an interface for the settings in Settings.php to be changed.
 *
 * - It uses the rawdata sub template (not theme-able.)
 * - Requires the admin_forum permission.
 * - Uses the edit_settings administration area.
 * - Contains the actual array of settings to show from Settings.php.
 * - Accessed from ?action=admin;area=serversettings;sa=general.
 *
 * @param $return_config
 */
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

	call_integration_hook('integrate_general_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=general;save';
	$context['settings_title'] = $txt['general_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_general_settings');

		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id']. ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * Basic database and paths settings - database name, host, etc.
 *
 * - It shows an interface for the settings in Settings.php to be changed.
 * - It contains the actual array of settings to show from Settings.php.
 * - It uses the rawdata sub template (not theme-able.)
 * - Requires the admin_forum permission.
 * - Uses the edit_settings administration area.
 * - Accessed from ?action=admin;area=serversettings;sa=database.
 *
 * @param $return_config
 */
function ModifyDatabaseSettings($return_config = false)
{
	global $scripturl, $context, $settings, $txt, $boarddir;

	/* If you're writing a mod, it's a bad idea to add things here....
		For each option:
		variable name, description, type (constant), size/possible values, helptext.
		OR an empty string for a horizontal rule.
		OR a string for a titled section. */
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
		array('autoOptMaxOnline', $txt['autoOptMaxOnline'], 'subtext' => $txt['zero_for_no_limit'], 'db', 'int'),
		'',
		array('boardurl', $txt['admin_url'], 'file', 'text', 36),
		array('boarddir', $txt['boarddir'], 'file', 'text', 36),
		array('sourcedir', $txt['sourcesdir'], 'file', 'text', 36),
		array('cachedir', $txt['cachedir'], 'file', 'text', 36),
	);

	call_integration_hook('integrate_database_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=database;save';
	$context['settings_title'] = $txt['database_paths_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_database_settings');

		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * This function handles cookies settings modifications.
 *
 * @param bool $return_config = false
 */
function ModifyCookieSettings($return_config = false)
{
	global $context, $scripturl, $txt, $sourcedir, $modSettings, $cookiename, $user_settings, $boardurl;

	// Define the variables we want to edit.
	$config_vars = array(
		// Cookies...
		array('cookiename', $txt['cookie_name'], 'file', 'text', 20),
		array('cookieTime', $txt['cookieTime'], 'db', 'int', 'postinput' => $txt['minutes']),
		array('localCookies', $txt['localCookies'], 'subtext' => $txt['localCookies_note'], 'db', 'check', false, 'localCookies'),
		array('globalCookies', $txt['globalCookies'], 'subtext' => $txt['globalCookies_note'], 'db', 'check', false, 'globalCookies'),
		array('globalCookiesDomain', $txt['globalCookiesDomain'], 'subtext' => $txt['globalCookiesDomain_note'], 'db', 'text', false, 'globalCookiesDomain'),
		array('secureCookies', $txt['secureCookies'], 'subtext' => $txt['secureCookies_note'], 'db', 'check', false, 'secureCookies',  'disabled' => !isset($_SERVER['HTTPS']) || !(strtolower($_SERVER['HTTPS']) == 'on' || strtolower($_SERVER['HTTPS']) == '1')),
		array('httponlyCookies', $txt['httponlyCookies'], 'subtext' => $txt['httponlyCookies_note'], 'db', 'check', false, 'httponlyCookies'),
		'',
		// Sessions
		array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
		array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
		array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime', 'postinput' => $txt['seconds']),
	);

	call_integration_hook('integrate_cookie_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cookie;save';
	$context['settings_title'] = $txt['cookies_sessions_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_cookie_settings');

		if (!empty($_POST['globalCookiesDomain']) && strpos($boardurl, $_POST['globalCookiesDomain']) === false)
			fatal_lang_error('invalid_cookie_domain', false);

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

		redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $context['session_id']. ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * Simply modifying cache functions
 *
 * @param bool $return_config = false
 */
function ModifyCacheSettings($return_config = false)
{
	global $context, $scripturl, $txt, $helptxt, $cache_enable;

	// Detect all available optimizers
	$detected = array();
	if (function_exists('eaccelerator_put'))
		$detected['eaccelerator'] = $txt['eAccelerator_cache'];
	if (function_exists('mmcache_put'))
		$detected['mmcache'] = $txt['mmcache_cache'];
	if (function_exists('apc_store'))
		$detected['apc'] = $txt['apc_cache'];
	if (function_exists('output_cache_put') || function_exists('zend_shm_cache_store'))
		$detected['zend'] = $txt['zend_cache'];
	if (function_exists('memcache_set') || function_exists('memcached_set'))
		$detected['memcached'] = $txt['memcached_cache'];
	if (function_exists('xcache_set'))
		$detected['xcache'] = $txt['xcache_cache'];
	if (function_exists('file_put_contents'))
		$detected['smf'] = $txt['default_cache'];

	// set our values to show what, if anything, we found
	if (empty($detected))
	{
		$txt['cache_settings_message'] = $txt['detected_no_caching'];
		$cache_level = array($txt['cache_off']);
		$detected['none'] = $txt['cache_off'];
	}
	else
	{
		$txt['cache_settings_message'] = sprintf($txt['detected_accelerators'], implode(', ', $detected));
		$cache_level = array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3']);
	}

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a few settings, but they are important
		array('', $txt['cache_settings_message'], '', 'desc'),
		array('cache_enable', $txt['cache_enable'], 'file', 'select', $cache_level, 'cache_enable'),
		array('cache_accelerator', $txt['cache_accelerator'], 'file', 'select', $detected),
		array('cache_memcached', $txt['cache_memcached'], 'file', 'text', $txt['cache_memcached'], 'cache_memcached'),
		array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir'),
	);

	// some javascript to enable / disable certain settings if the option is not selected
	$context['settings_post_javascript'] = '
		var cache_type = document.getElementById(\'cache_accelerator\');
		createEventListener(cache_type);
		cache_type.addEventListener("change", toggleCache);
		toggleCache();';

	call_integration_hook('integrate_modify_cache_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		call_integration_hook('integrate_save_cache_settings');

		saveSettings($config_vars);

		// we need to save the $cache_enable to $modSettings as well
		updatesettings(array('cache_enable' => (int) $_POST['cache_enable']));

		// exit so we reload our new settings on the page
		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
	}

	loadLanguage('ManageMaintenance');
	createToken('admin-maint');
	$context['template_layers'][] = 'clean_cache_button';

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];
	$context['settings_message'] = $txt['caching_information'];

	// Prepare the template.
	createToken('admin-ssc');
	prepareServerSettingsContext($config_vars);
}

/**
 * Allows to edit load balancing settings.
 *
 * @param bool $return_config = false
 */
function ModifyLoadBalancingSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	// Setup a warning message, but disabled by default.
	$disabled = true;
	$context['settings_message'] = $txt['loadavg_disabled_conf'];

	if (stripos(PHP_OS, 'win') === 0)
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
		array('check', 'loadavg_enable', 'disabled' => $disabled),
	);

	// Set the default values for each option.
	$default_values = array(
		'loadavg_auto_opt' => '1.0',
		'loadavg_search' => '2.5',
		'loadavg_allunread' => '2.0',
		'loadavg_unreadreplies' => '3.5',
		'loadavg_show_posts' => '2.0',
		'loadavg_userstats' => '10.0',
		'loadavg_bbc' => '30.0',
		'loadavg_forum' => '40.0',
	);

	// Loop through the settings.
	foreach ($default_values as $name => $value)
	{
		// Use the default value if the setting isn't set yet.
		$value = !isset($modSettings[$name]) ? $value : $modSettings[$name];
		$config_vars[] = array('text', $name, 'value' => $value, 'disabled' => $disabled);
	}

	call_integration_hook('integrate_loadavg_settings', array(&$config_vars));

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

		call_integration_hook('integrate_save_loadavg_settings');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
	}

	createToken('admin-ssc');
	createToken('admin-dbsc');
	prepareDBSettingContext($config_vars);
}


/**
 * Helper function, it sets up the context for the manage server settings.
 * - The basic usage of the six numbered key fields are
 * - array (0 ,1, 2, 3, 4, 5
 *		0 variable name - the name of the saved variable
 *		1 label - the text to show on the settings page
 *		2 saveto - file or db, where to save the variable name - value pair
 *		3 type - type of data to save, int, float, text, check
 *		4 size - false or field size
 *		5 help - '' or helptxt variable name
 *	)
 *
 * the following named keys are also permitted
 * 'disabled' => 'postinput' => 'preinput' =>
 *
 * @param array $config_vars
 */
function prepareServerSettingsContext(&$config_vars)
{
	global $context, $modSettings, $smcFunc;

	$context['config_vars'] = array();
	foreach ($config_vars as $identifier => $config_var)
	{
		if (!is_array($config_var) || !isset($config_var[1]))
			$context['config_vars'][] = $config_var;
		else
		{
			$varname = $config_var[0];
			global $$varname;

			// Set the subtext in case it's part of the label.
			// @todo Temporary. Preventing divs inside label tags.
			$divPos = strpos($config_var[1], '<div');
			$subtext = '';
			if ($divPos !== false)
			{
				$subtext = preg_replace('~</?div[^>]*>~', '', substr($config_var[1], $divPos));
				$config_var[1] = substr($config_var[1], 0, $divPos);
			}

			$context['config_vars'][$config_var[0]] = array(
				'label' => $config_var[1],
				'help' => isset($config_var[5]) ? $config_var[5] : '',
				'type' => $config_var[3],
				'size' => empty($config_var[4]) ? 0 : $config_var[4],
				'data' => isset($config_var[4]) && is_array($config_var[4]) && $config_var[3] != 'select' ? $config_var[4] : array(),
				'name' => $config_var[0],
				'value' => $config_var[2] == 'file' ? $smcFunc['htmlspecialchars']($$varname) : (isset($modSettings[$config_var[0]]) ? $smcFunc['htmlspecialchars']($modSettings[$config_var[0]]) : (in_array($config_var[3], array('int', 'float')) ? 0 : '')),
				'disabled' => !empty($context['settings_not_writable']) || !empty($config_var['disabled']),
				'invalid' => false,
				'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : $subtext,
				'javascript' => '',
				'preinput' => !empty($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => !empty($config_var['postinput']) ? $config_var['postinput'] : '',
			);

			// If this is a select box handle any data.
			if (!empty($config_var[4]) && is_array($config_var[4]))
			{
				// If it's associative
				$config_values = array_values($config_var[4]);
				if (isset($config_values[0]) && is_array($config_values[0]))
					$context['config_vars'][$config_var[0]]['data'] = $config_var[4];
				else
				{
					foreach ($config_var[4] as $key => $item)
						$context['config_vars'][$config_var[0]]['data'][] = array($key, $item);
				}
			}
		}
	}

	// Two tokens because saving these settings requires both saveSettings and saveDBSettings
	createToken('admin-ssc');
	createToken('admin-dbsc');
}

/**
 * Helper function, it sets up the context for database settings.
 * @todo see rev. 10406 from 2.1-requests
 *
 * @param array $config_vars
 */
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $helptxt, $context, $modSettings, $sourcedir, $smcFunc;

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
				'value' => isset($modSettings[$config_var[1]]) ? ($config_var[0] == 'select' ? $modSettings[$config_var[1]] : $smcFunc['htmlspecialchars']($modSettings[$config_var[1]])) : (in_array($config_var[0], array('int', 'float')) ? 0 : (!empty($config_var['multiple']) ? serialize(array()) : '')),
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
			// @todo Temporary. Preventing divs inside label tags.
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
				// @todo  'tag_' . ?
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

	call_integration_hook('integrate_prepare_db_settings', array(&$config_vars));
	createToken('admin-dbsc');
}

/**
 * Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
 *
 * - Saves those settings set from ?action=admin;area=serversettings.
 * - Requires the admin_forum permission.
 * - Contains arrays of the types of data to save into Settings.php.
 *
 * @param $config_vars
 */
function saveSettings(&$config_vars)
{
	global $boarddir, $sc, $cookiename, $modSettings, $user_settings;
	global $sourcedir, $context, $cachedir;

	validateToken('admin-ssc');

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
		'boarddir', 'sourcedir',
		'cachedir', 'cache_accelerator', 'cache_memcached',
	);

	// All the numeric variables.
	$config_ints = array(
		'cache_enable',
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

	// Now loop through the remaining (database-based) settings.
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

/**
 * Helper function for saving database settings.
 * @todo see rev. 10406 from 2.1-requests
 *
 * @param array $config_vars
 */
function saveDBSettings(&$config_vars)
{
	global $sourcedir, $context;

	validateToken('admin-dbsc');

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

/**
 * Allows us to see the servers php settings
 *
 * - loads the settings into an array for display in a template
 * - drops cookie values just in case
 */
function ShowPHPinfoSettings()
{
	global $context, $txt;

	$info_lines = array();
	$category = $txt['phpinfo_settings'];

	// get the data
	ob_start();
	phpinfo();

	// We only want it for its body, pigs that we are
	$info_lines = preg_replace('~^.*<body>(.*)</body>.*$~', '$1', ob_get_contents());
	$info_lines = explode("\n", strip_tags($info_lines, "<tr><td><h2>"));
	ob_end_clean();

	// remove things that could be considered sensitive
	$remove = '_COOKIE|Cookie|_GET|_REQUEST|REQUEST_URI|QUERY_STRING|REQUEST_URL|HTTP_REFERER';

	// put all of it into an array
	foreach ($info_lines as $line)
	{
		if (preg_match('~(' . $remove . ')~', $line))
			continue;

		// new category?
		if (strpos($line, '<h2>') !== false)
			$category = preg_match('~<h2>(.*)</h2>~', $line, $title) ? $category = $title[1] : $category;

		// load it as setting => value or the old setting local master
		if (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			$pinfo[$category][$val[1]] = $val[2];
		elseif (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			$pinfo[$category][$val[1]] = array($txt['phpinfo_localsettings'] => $val[2], $txt['phpinfo_defaultsettings'] => $val[3]);
	}

	// load it in to context and display it
	$context['pinfo'] = $pinfo;
	$context['page_title'] = $txt['admin_server_settings'];
	$context['sub_template'] = 'php_info';
	return;
}

?>