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
 * - A list of boards to choose from
 *  	array('boards', 'likes_boards'),
 *  	Note that the storage in the database is as 1,2,3,4
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
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
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
	global $context, $txt, $boarddir;

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
		'security' => 'ModifyGeneralSecuritySettings',
		'cache' => 'ModifyCacheSettings',
		'loads' => 'ModifyLoadBalancingSettings',
		'phpinfo' => 'ShowPHPinfoSettings',
	);

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Warn the user if there's any relevant information regarding Settings.php.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	if ($settings_not_writable)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div>';

	$context['settings_not_writable'] = $settings_not_writable;

	call_integration_hook('integrate_server_settings', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * General forum settings - forum name, maintenance mode, etc.
 * Practically, this shows an interface for the settings in Settings.php to be changed.
 *
 * - Requires the admin_forum permission.
 * - Uses the edit_settings administration area.
 * - Contains the actual array of settings to show from Settings.php.
 * - Accessed from ?action=admin;area=serversettings;sa=general.
 *
 * @param bool $return_config Whether to return the $config_vars array (for pagination purposes)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyGeneralSettings($return_config = false)
{
	global $scripturl, $context, $txt, $modSettings, $boardurl, $sourcedir;

	// If no cert, force_ssl must remain 0
	require_once($sourcedir . '/Subs.php');
	if (!ssl_cert_found($boardurl) && empty($modSettings['force_ssl']))
		$disable_force_ssl = true;
	else
		$disable_force_ssl = false;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext, optional 'min' (minimum value for float/int, defaults to 0), optional 'max' (maximum value for float/int), optional 'step' (amount to increment/decrement value for float/int)
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
		array('disableHostnameLookup', $txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'),
		'',
		array('force_ssl', $txt['force_ssl'], 'db', 'select', array($txt['force_ssl_off'], $txt['force_ssl_complete']), 'force_ssl', 'disabled' => $disable_force_ssl),
		array('image_proxy_enabled', $txt['image_proxy_enabled'], 'file', 'check', null, 'image_proxy_enabled'),
		array('image_proxy_secret', $txt['image_proxy_secret'], 'file', 'text', 30, 'image_proxy_secret'),
		array('image_proxy_maxsize', $txt['image_proxy_maxsize'], 'file', 'int', null, 'image_proxy_maxsize'),
		'',
		array('enable_sm_stats', $txt['enable_sm_stats'], 'db', 'check', null, 'enable_sm_stats'),
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

		// Are we saving the stat collection?
		if (!empty($_POST['enable_sm_stats']) && empty($modSettings['sm_stats_key']))
		{
			$registerSMStats = registerSMStats();

			// Failed to register, disable it again.
			if (empty($registerSMStats))
				$_POST['enable_sm_stats'] = 0;
		}

		// Ensure all URLs are aligned with the new force_ssl setting
		// Treat unset like 0
		if (isset($_POST['force_ssl']))
			AlignURLsWithSSLSetting($_POST['force_ssl']);
		else
			AlignURLsWithSSLSetting(0);

		saveSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);

	// Some javascript for SSL
	addInlineJavaScript('
$(function()
{
	$("#force_ssl").change(function()
	{
		var mode = $(this).val() == 1 ? false : true;
		$("#image_proxy_enabled").prop("disabled", mode);
		$("#image_proxy_secret").prop("disabled", mode);
		$("#image_proxy_maxsize").prop("disabled", mode);
	}).change();
});', true);
}

/**
 * Align URLs with SSL Setting.
 *
 * If force_ssl has changed, ensure all URLs are aligned with the new setting.
 * This includes:
 *     - $boardurl
 *     - $modSettings['smileys_url']
 *     - $modSettings['avatar_url']
 *     - $modSettings['custom_avatar_url'] - if found
 *     - theme_url - all entries in the themes table
 *     - images_url - all entries in the themes table
 *
 * This function will NOT overwrite URLs that are not subfolders of $boardurl.
 * The admin must have pointed those somewhere else on purpose, so they must be updated manually.
 *
 * A word of caution: You can't trust the http/https scheme reflected for these URLs in $globals
 * (e.g., $boardurl) or in $modSettings.  This is because SMF may change them in memory to comply
 * with the force_ssl setting - a soft redirect may be in effect...  Thus, conditional updates
 * to these values do not work.  You gotta just brute force overwrite them based on force_ssl.
 *
 * @param int $new_force_ssl is the current force_ssl setting.
 * @return void Returns nothing, just does its job
 */
function AlignURLsWithSSLSetting($new_force_ssl = 0)
{
	global $boardurl, $modSettings, $sourcedir, $smcFunc;
	require_once($sourcedir . '/Subs-Admin.php');

	// Check $boardurl
	if (!empty($new_force_ssl))
		$newval = strtr($boardurl, array('http://' => 'https://'));
	else
		$newval = strtr($boardurl, array('https://' => 'http://'));
	updateSettingsFile(array('boardurl' => '\'' . addslashes($newval) . '\''));

	$new_settings = array();

	// Check $smileys_url, but only if it points to a subfolder of $boardurl
	if (BoardurlMatch($modSettings['smileys_url']))
	{
		if (!empty($new_force_ssl))
			$newval = strtr($modSettings['smileys_url'], array('http://' => 'https://'));
		else
			$newval = strtr($modSettings['smileys_url'], array('https://' => 'http://'));
		$new_settings['smileys_url'] = $newval;
	}

	// Check $avatar_url, but only if it points to a subfolder of $boardurl
	if (BoardurlMatch($modSettings['avatar_url']))
	{
		if (!empty($new_force_ssl))
			$newval = strtr($modSettings['avatar_url'], array('http://' => 'https://'));
		else
			$newval = strtr($modSettings['avatar_url'], array('https://' => 'http://'));
		$new_settings['avatar_url'] = $newval;
	}

	// Check $custom_avatar_url, but only if it points to a subfolder of $boardurl
	// This one had been optional in the past, make sure it is set first
	if (isset($modSettings['custom_avatar_url']) && BoardurlMatch($modSettings['custom_avatar_url']))
	{
		if (!empty($new_force_ssl))
			$newval = strtr($modSettings['custom_avatar_url'], array('http://' => 'https://'));
		else
			$newval = strtr($modSettings['custom_avatar_url'], array('https://' => 'http://'));
		$new_settings['custom_avatar_url'] = $newval;
	}

	// Save updates to the settings table
	if (!empty($new_settings))
		updateSettings($new_settings, true);

	// Now we move onto the themes.
	// First, get a list of theme URLs...
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable in ({string:themeurl}, {string:imagesurl})
			AND id_member = {int:zero}',
		array(
			'themeurl' => 'theme_url',
			'imagesurl' => 'images_url',
			'zero' => 0,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// First check to see if it points to a subfolder of $boardurl
		if (BoardurlMatch($row['value']))
		{
			if (!empty($new_force_ssl))
				$newval = strtr($row['value'], array('http://' => 'https://'));
			else
				$newval = strtr($row['value'], array('https://' => 'http://'));

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}themes
				SET value = {string:theme_val}
				WHERE variable = {string:theme_var}
					AND id_theme = {string:theme_id}
					AND id_member = {int:zero}',
				array(
					'theme_val' => $newval,
					'theme_var' => $row['variable'],
					'theme_id' => $row['id_theme'],
					'zero' => 0,
				)
			);
		}
	}
	$smcFunc['db_free_result']($request);
}

/**
 * $boardurl Match.
 *
 * Helper function to see if the url being checked is based off of $boardurl.
 * If not, it was overridden by the admin to some other value on purpose, and should not
 * be stepped on by SMF when aligning URLs with the force_ssl setting.
 * The site admin must change URLs that are not aligned with $boardurl manually.
 *
 * @param string $url is the url to check.
 * @return bool Returns true if the url is based off of $boardurl (without the scheme), false if not
 */
function BoardurlMatch($url = '')
{
	global $boardurl;

	// Strip the schemes
	$urlpath = strtr($url, array('http://' => '', 'https://' => ''));
	$boardurlpath = strtr($boardurl, array('http://' => '', 'https://' => ''));

	// If leftmost portion of path matches boardurl, return true
	$result = strpos($urlpath, $boardurlpath);
	if ($result === false || $result != 0)
		return false;
	else
		return true;
}

/**
 * Basic database and paths settings - database name, host, etc.
 *
 * - It shows an interface for the settings in Settings.php to be changed.
 * - It contains the actual array of settings to show from Settings.php.
 * - Requires the admin_forum permission.
 * - Uses the edit_settings administration area.
 * - Accessed from ?action=admin;area=serversettings;sa=database.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyDatabaseSettings($return_config = false)
{
	global $scripturl, $context, $txt, $smcFunc;
	db_extend('extra');

	/* If you're writing a mod, it's a bad idea to add things here....
		For each option:
		variable name, description, type (constant), size/possible values, helptext, optional 'min' (minimum value for float/int, defaults to 0), optional 'max' (maximum value for float/int), optional 'step' (amount to increment/decrement value for float/int)
		OR an empty string for a horizontal rule.
		OR a string for a titled section. */
	$config_vars = array(
		array('db_persist', $txt['db_persist'], 'file', 'check', null, 'db_persist'),
		array('db_error_send', $txt['db_error_send'], 'file', 'check'),
		array('ssi_db_user', $txt['ssi_db_user'], 'file', 'text', null, 'ssi_db_user'),
		array('ssi_db_passwd', $txt['ssi_db_passwd'], 'file', 'password'),
		'',
		array('autoFixDatabase', $txt['autoFixDatabase'], 'db', 'check', false, 'autoFixDatabase')
	);

	// Add PG Stuff
	if ($smcFunc['db_title'] == "PostgreSQL")
	{
		$request = $smcFunc['db_query']('', 'SELECT cfgname FROM pg_ts_config', array());
		$fts_language = array();

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$fts_language[$row['cfgname']] = $row['cfgname'];

		$config_vars = array_merge($config_vars, array(
				'',
				array('search_language', $txt['search_language'], 'db', 'select', $fts_language, 'pgFulltextSearch')
			)
		);
	}

	call_integration_hook('integrate_database_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=database;save';
	$context['settings_title'] = $txt['database_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	if (!$smcFunc['db_allow_persistent']())
		addInlineJavaScript('
			$(function()
			{
				$("#db_persist").prop("disabled", true);
			});', true);

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_database_settings');

		saveSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * This function handles cookies settings modifications.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyCookieSettings($return_config = false)
{
	global $context, $scripturl, $txt, $sourcedir, $modSettings, $cookiename, $user_settings, $boardurl, $smcFunc;

	// Define the variables we want to edit.
	$config_vars = array(
		// Cookies...
		array('cookiename', $txt['cookie_name'], 'file', 'text', 20),
		array('cookieTime', $txt['cookieTime'], 'db', 'int', 'postinput' => $txt['minutes']),
		array('localCookies', $txt['localCookies'], 'db', 'check', false, 'localCookies'),
		array('globalCookies', $txt['globalCookies'], 'db', 'check', false, 'globalCookies'),
		array('globalCookiesDomain', $txt['globalCookiesDomain'], 'db', 'text', false, 'globalCookiesDomain'),
		array('secureCookies', $txt['secureCookies'], 'db', 'check', false, 'secureCookies', 'disabled' => !httpsOn()),
		array('httponlyCookies', $txt['httponlyCookies'], 'db', 'check', false, 'httponlyCookies'),
		'',
		// Sessions
		array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
		array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
		array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime', 'postinput' => $txt['seconds']),
		'',
		// 2FA
		array('tfa_mode', $txt['tfa_mode'], 'db', 'select', array(
			0 => $txt['tfa_mode_disabled'],
			1 => $txt['tfa_mode_enabled'],
		) + (empty($user_settings['tfa_secret']) ? array() : array(
			2 => $txt['tfa_mode_forced'],
		)) + (empty($user_settings['tfa_secret']) ? array() : array(
			3 => $txt['tfa_mode_forcedall'],
		)), 'subtext' => $txt['tfa_mode_subtext'] . (empty($user_settings['tfa_secret']) ? '<br><strong>' . $txt['tfa_mode_forced_help'] . '</strong>' : ''), 'tfa_mode'),
	);

	addInlineJavaScript('
	function hideGlobalCookies()
	{
		var usingLocal = $("#localCookies").prop("checked");
		$("#setting_globalCookies").closest("dt").toggle(!usingLocal);
		$("#globalCookies").closest("dd").toggle(!usingLocal);

		var usingGlobal = !usingLocal && $("#globalCookies").prop("checked");
		$("#setting_globalCookiesDomain").closest("dt").toggle(usingGlobal);
		$("#globalCookiesDomain").closest("dd").toggle(usingGlobal);
	};
	hideGlobalCookies();

	$("#localCookies, #globalCookies").click(function() {
		hideGlobalCookies();
	});', true);

	if (empty($user_settings['tfa_secret']))
		addInlineJavaScript('');

	call_integration_hook('integrate_cookie_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cookie;save';
	$context['settings_title'] = $txt['cookies_sessions_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_cookie_settings');

		// Local and global do not play nicely together.
		if (!empty($_POST['localCookies']) && empty($_POST['globalCookies']))
			unset ($_POST['globalCookies']);

		if (empty($modSettings['localCookies']) != empty($_POST['localCookies']) || empty($modSettings['globalCookies']) != empty($_POST['globalCookies']))
			$scope_changed = true;

		if (!empty($_POST['globalCookiesDomain']) && strpos($boardurl, $_POST['globalCookiesDomain']) === false)
			fatal_lang_error('invalid_cookie_domain', false);

		saveSettings($config_vars);

		// If the cookie name or scope were changed, reset the cookie.
		if ($cookiename != $_POST['cookiename'] || !empty($scope_changed))
		{
			$original_session_id = $context['session_id'];
			include_once($sourcedir . '/Subs-Auth.php');

			// Remove the old cookie.
			setLoginCookie(-3600, 0);

			// Set the new one.
			$cookiename = !empty($_POST['cookiename']) ? $_POST['cookiename'] : $cookiename;
			setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], hash_salt($user_settings['passwd'], $user_settings['password_salt']));

			redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $original_session_id, $context['server']['needs_login_fix']);
		}

		//If we disabled 2FA, reset all members and membergroups settings.
		if (isset($_POST['tfa_mode']) && empty($_POST['tfa_mode']))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}membergroups
				SET tfa_required = {int:zero}',
				array(
					'zero' => 0,
				)
			);
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET tfa_secret = {string:empty}, tfa_backup = {string:empty}',
				array(
					'empty' => '',
				)
			);
		}

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * Settings really associated with general security aspects.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyGeneralSecuritySettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = array(
		array('int', 'failed_login_threshold'),
		array('int', 'loginHistoryDays', 'subtext' => $txt['zero_to_disable']),
		'',

		array('check', 'securityDisable'),
		array('check', 'securityDisable_moderate'),
		'',

		// Reactive on email, and approve on delete
		array('check', 'send_validation_onChange'),
		array('check', 'approveAccountDeletion'),
		'',

		// Password strength.
		array(
			'select',
			'password_strength',
			array(
				$txt['setting_password_strength_low'],
				$txt['setting_password_strength_medium'],
				$txt['setting_password_strength_high']
			)
		),
		array('check', 'enable_password_conversion'),
		'',

		// Reporting of personal messages?
		array('check', 'enableReportPM'),
		'',

		array(
			'select',
			'frame_security',
			array(
				'SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'],
				'DENY' => $txt['setting_frame_security_DENY'],
				'DISABLE' => $txt['setting_frame_security_DISABLE']
			)
		),
		'',

		array(
			'select',
			'proxy_ip_header',
			array(
				'disabled' => $txt['setting_proxy_ip_header_disabled'],
				'autodetect' => $txt['setting_proxy_ip_header_autodetect'],
				'HTTP_X_FORWARDED_FOR' => 'HTTP_X_FORWARDED_FOR',
				'HTTP_CLIENT_IP' => 'HTTP_CLIENT_IP',
				'HTTP_X_REAL_IP' => 'HTTP_X_REAL_IP',
				'CF-Connecting-IP' => 'CF-Connecting-IP'
			)
		),
		array('text', 'proxy_ip_servers'),
	);

	call_integration_hook('integrate_general_security_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;

		call_integration_hook('integrate_save_general_security_settings');

		writeLog();
		redirectexit('action=admin;area=serversettings;sa=security;' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;save;sa=security';
	$context['settings_title'] = $txt['security_settings'];

	prepareDBSettingContext($config_vars);
}

/**
 * Simply modifying cache functions
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyCacheSettings($return_config = false)
{
	global $context, $scripturl, $txt;

	// Detect all available optimizers
	$detected = loadCacheAPIs();

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
	);

	// some javascript to enable / disable certain settings if the option is not selected
	$context['settings_post_javascript'] = '
		$(document).ready(function() {
			$("#cache_accelerator").change();
		});';

	call_integration_hook('integrate_modify_cache_settings', array(&$config_vars));

	// Maybe we have some additional settings from the selected accelerator.
	if (!empty($detected))
	{
		foreach ($detected as $tryCache => $dummy)
		{
			$cache_class_name = $tryCache . '_cache';

			// loadCacheAPIs has already included the file, just see if we can't add the settings in.
			if (is_callable(array($cache_class_name, 'cacheSettings')))
			{
				$testAPI = new $cache_class_name();
				call_user_func_array(array($testAPI, 'cacheSettings'), array(&$config_vars));
			}
		}
	}
	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		call_integration_hook('integrate_save_cache_settings');

		saveSettings($config_vars);
		$_SESSION['adm-save'] = true;

		// We need to save the $cache_enable to $modSettings as well
		updatesettings(array('cache_enable' => (int) $_POST['cache_enable']));

		// exit so we reload our new settings on the page
		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
	}

	loadLanguage('ManageMaintenance');
	createToken('admin-maint');
	$context['template_layers'][] = 'clean_cache_button';

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];

	// Changing cache settings won't have any effect if Settings.php is not writeable.
	$context['save_disabled'] = $context['settings_not_writable'];

	// Decide what message to show.
	if (!$context['save_disabled'])
		$context['settings_message'] = $txt['caching_information'];

	// Prepare the template.
	prepareServerSettingsContext($config_vars);
}

/**
 * Allows to edit load balancing settings.
 *
 * @param bool $return_config Whether or not to return the config_vars array
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyLoadBalancingSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings;

	// Setup a warning message, but disabled by default.
	$disabled = true;
	$context['settings_message'] = $txt['loadavg_disabled_conf'];

	if (DIRECTORY_SEPARATOR === '\\')
	{
		$context['settings_message'] = $txt['loadavg_disabled_windows'];
		if (isset($_GET['save']))
			$_SESSION['adm-save'] = $txt['loadavg_disabled_windows'];
	}
	elseif (stripos(PHP_OS, 'darwin') === 0)
	{
		$context['settings_message'] = $txt['loadavg_disabled_osx'];
		if (isset($_GET['save']))
			$_SESSION['adm-save'] = $txt['loadavg_disabled_osx'];
	}
	else
	{
		$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
		if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) !== 0)
			$modSettings['load_average'] = (float) $matches[1];
		elseif (($modSettings['load_average'] = @`uptime`) !== null && preg_match('~load averages?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) !== 0)
			$modSettings['load_average'] = (float) $matches[1];
		else
			unset($modSettings['load_average']);

		if (!empty($modSettings['load_average']) || (isset($modSettings['load_average']) && $modSettings['load_average'] === 0.0))
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
		'loadavg_auto_opt' => 1.0,
		'loadavg_search' => 2.5,
		'loadavg_allunread' => 2.0,
		'loadavg_unreadreplies' => 3.5,
		'loadavg_show_posts' => 2.0,
		'loadavg_userstats' => 10.0,
		'loadavg_bbc' => 30.0,
		'loadavg_forum' => 40.0,
	);

	// Loop through the settings.
	foreach ($default_values as $name => $value)
	{
		// Use the default value if the setting isn't set yet.
		$value = !isset($modSettings[$name]) ? $value : $modSettings[$name];
		$config_vars[] = array('float', $name, 'value' => $value, 'disabled' => $disabled);
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
			if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable' || !in_array($key, array_keys($default_values)))
				continue;
			else
				$_POST[$key] = (float) $value;

			if ($key == 'loadavg_auto_opt' && $value <= 1)
				$_POST['loadavg_auto_opt'] = 1.0;
			elseif ($key == 'loadavg_forum' && $value < 10)
				$_POST['loadavg_forum'] = 10.0;
			elseif ($value < 2)
				$_POST[$key] = 2.0;
		}

		call_integration_hook('integrate_save_loadavg_settings');

		saveDBSettings($config_vars);
		if (!isset($_SESSION['adm-save']))
			$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
	}

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
 * 'disabled' => A string of code that will determine whether or not the setting should be disabled
 * 'postinput' => Text to display after the input field
 * 'preinput' => Text to display before the input field
 * 'subtext' => Additional descriptive text to display under the field's label
 * 'min' => minimum allowed value (for int/float). Defaults to 0 if not set.
 * 'max' => maximum allowed value (for int/float)
 * 'step' => how much to increment/decrement the value by (only for int/float - mostly used for float values).
 *
 * @param array $config_vars An array of configuration variables
 */
function prepareServerSettingsContext(&$config_vars)
{
	global $context, $modSettings, $smcFunc;

	if (isset($_SESSION['adm-save']))
	{
		if ($_SESSION['adm-save'] === true)
			$context['saved_successful'] = true;
		else
			$context['saved_failed'] = $_SESSION['adm-save'];

		unset($_SESSION['adm-save']);
	}

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

			// Handle min/max/step if necessary
			if ($config_var[3] == 'int' || $config_var[3] == 'float')
			{
				// Default to a min of 0 if one isn't set
				if (isset($config_var['min']))
					$context['config_vars'][$config_var[0]]['min'] = $config_var['min'];
				else
					$context['config_vars'][$config_var[0]]['min'] = 0;

				if (isset($config_var['max']))
					$context['config_vars'][$config_var[0]]['max'] = $config_var['max'];

				if (isset($config_var['step']))
					$context['config_vars'][$config_var[0]]['step'] = $config_var['step'];
			}

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
 *
 * @todo see rev. 10406 from 2.1-requests
 *
 * @param array $config_vars An array of configuration variables
 */
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $helptxt, $context, $modSettings, $sourcedir, $smcFunc;

	loadLanguage('Help');

	if (isset($_SESSION['adm-save']))
	{
		if ($_SESSION['adm-save'] === true)
			$context['saved_successful'] = true;
		else
			$context['saved_failed'] = $_SESSION['adm-save'];

		unset($_SESSION['adm-save']);
	}

	$context['config_vars'] = array();
	$inlinePermissions = array();
	$bbcChoice = array();
	$board_list = false;
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

			if ($config_var[0] == 'boards')
				$board_list = true;

			// Are we showing the BBC selection box?
			if ($config_var[0] == 'bbc')
				$bbcChoice[] = $config_var[1];

			// We need to do some parsing of the value before we pass it in.
			if (isset($modSettings[$config_var[1]]))
			{
				switch ($config_var[0])
				{
					case 'select':
						$value = $modSettings[$config_var[1]];
						break;
					case 'json':
						$value = $smcFunc['htmlspecialchars']($smcFunc['json_encode']($modSettings[$config_var[1]]));
						break;
					case 'boards':
						$value = explode(',', $modSettings[$config_var[1]]);
						break;
					default:
						$value = $smcFunc['htmlspecialchars']($modSettings[$config_var[1]]);
				}
			}
			else
			{
				// Darn, it's empty. What type is expected?
				switch ($config_var[0])
				{
					case 'int':
					case 'float':
						$value = 0;
						break;
					case 'select':
						$value = !empty($config_var['multiple']) ? $smcFunc['json_encode'](array()) : '';
						break;
					case 'boards':
						$value = array();
						break;
					default:
						$value = '';
				}
			}

			$context['config_vars'][$config_var[1]] = array(
				'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
				'help' => isset($helptxt[$config_var[1]]) ? $config_var[1] : '',
				'type' => $config_var[0],
				'size' => !empty($config_var['size']) ? $config_var['size'] : (!empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0)),
				'data' => array(),
				'name' => $config_var[1],
				'value' => $value,
				'disabled' => false,
				'invalid' => !empty($config_var['invalid']),
				'javascript' => '',
				'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
				'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
			);

			// Handle min/max/step if necessary
			if ($config_var[0] == 'int' || $config_var[0] == 'float')
			{
				// Default to a min of 0 if one isn't set
				if (isset($config_var['min']))
					$context['config_vars'][$config_var[1]]['min'] = $config_var['min'];
				else
					$context['config_vars'][$config_var[1]]['min'] = 0;

				if (isset($config_var['max']))
					$context['config_vars'][$config_var[1]]['max'] = $config_var['max'];

				if (isset($config_var['step']))
					$context['config_vars'][$config_var[1]]['step'] = $config_var['step'];
			}

			// If this is a select box handle any data.
			if (!empty($config_var[2]) && is_array($config_var[2]))
			{
				// If we allow multiple selections, we need to adjust a few things.
				if ($config_var[0] == 'select' && !empty($config_var['multiple']))
				{
					$context['config_vars'][$config_var[1]]['name'] .= '[]';
					$context['config_vars'][$config_var[1]]['value'] = !empty($context['config_vars'][$config_var[1]]['value']) ? $smcFunc['json_decode']($context['config_vars'][$config_var[1]]['value'], true) : array();
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
		init_inline_permissions($inlinePermissions);
	}

	if ($board_list)
	{
		require_once($sourcedir . '/Subs-MessageIndex.php');
		$context['board_list'] = getBoardList();
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

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Now put whatever BBC options we may have into context too!
		$context['bbc_sections'] = array();
		foreach ($bbcChoice as $bbcSection)
		{
			$context['bbc_sections'][$bbcSection] = array(
				'title' => isset($txt['bbc_title_' . $bbcSection]) ? $txt['bbc_title_' . $bbcSection] : $txt['enabled_bbc_select'],
				'disabled' => empty($modSettings['bbc_disabled_' . $bbcSection]) ? array() : $modSettings['bbc_disabled_' . $bbcSection],
				'all_selected' => empty($modSettings['bbc_disabled_' . $bbcSection]),
				'columns' => array(),
			);

			if ($bbcSection == 'legacyBBC')
				$sectionTags = array_intersect($context['legacy_bbc'], $bbcTags);
			else
				$sectionTags = array_diff($bbcTags, $context['legacy_bbc']);

			$totalTags = count($sectionTags);
			$tagsPerColumn = ceil($totalTags / $numColumns);

			$col = 0;
			$i = 0;
			foreach ($sectionTags as $tag)
			{
				if ($i % $tagsPerColumn == 0 && $i != 0)
					$col++;

				$context['bbc_sections'][$bbcSection]['columns'][$col][] = array(
					'tag' => $tag,
					// @todo  'tag_' . ?
					'show_help' => isset($helptxt[$tag]),
				);

				$i++;
			}
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
 * @param array $config_vars An array of configuration variables
 */
function saveSettings(&$config_vars)
{
	global $sourcedir, $context;

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
		'cachedir', 'cachedir_sqlite', 'cache_accelerator', 'cache_memcached',
		'image_proxy_secret',
	);

	// All the numeric variables.
	$config_ints = array(
		'cache_enable',
		'image_proxy_maxsize',
	);

	// All the checkboxes
	$config_bools = array('db_persist', 'db_error_send', 'maintenance', 'image_proxy_enabled');

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = array();
	// Figure out which config vars we're saving here...
	foreach ($config_vars as $var)
	{
		if (!is_array($var) || $var[2] != 'file' || (!in_array($var[0], $config_bools) && !isset($_POST[$var[0]])))
			continue;

		$config_var = $var[0];

		if (in_array($config_var, $config_passwords))
		{
			if (isset($_POST[$config_var][1]) && $_POST[$config_var][0] == $_POST[$config_var][1])
				$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var][0], '\'\\') . '\'';
		}
		elseif (in_array($config_var, $config_strs))
		{
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var], '\'\\') . '\'';
		}
		elseif (in_array($config_var, $config_ints))
		{
			$new_settings[$config_var] = (int) $_POST[$config_var];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$new_settings[$config_var] = max($min, $new_settings[$config_var]);

			// Is there a max value for this as well?
			if (isset($var['max']))
				$new_settings[$config_var] = min($var['max'], $new_settings[$config_var]);
		}
		elseif (in_array($config_var, $config_bools))
		{
			if (!empty($_POST[$config_var]))
				$new_settings[$config_var] = '1';
			else
				$new_settings[$config_var] = '0';
		}
		else
		{
			// This shouldn't happen, but it might...
			fatal_error('Unknown config_var \'' . $config_var . '\'');
		}
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

		$new_setting = array($config_var[3], $config_var[0]);

		// Select options need carried over, too.
		if (isset($config_var[4]))
			$new_setting[] = $config_var[4];

		// Include min and max if necessary
		if (isset($config_var['min']))
			$new_setting['min'] = $config_var['min'];

		if (isset($config_var['max']))
			$new_setting['max'] = $config_var['max'];

		// Rewrite the definition a bit.
		$new_settings[] = $new_setting;
	}

	// Save the new database-based settings, if any.
	if (!empty($new_settings))
		saveDBSettings($new_settings);
}

/**
 * Helper function for saving database settings.
 *
 * @todo see rev. 10406 from 2.1-requests
 *
 * @param array $config_vars An array of configuration variables
 */
function saveDBSettings(&$config_vars)
{
	global $sourcedir, $smcFunc;
	static $board_list = null;

	validateToken('admin-dbsc');

	$inlinePermissions = array();
	foreach ($config_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && $var[0] != 'boards' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
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
			$lOptions = array();
			foreach ($_POST[$var[1]] as $invar)
				if (in_array($invar, array_keys($var[2])))
					$lOptions[] = $invar;

			$setArray[$var[1]] = $smcFunc['json_encode']($lOptions);
		}
		// List of boards!
		elseif ($var[0] == 'boards')
		{
			// We just need a simple list of valid boards, nothing more.
			if ($board_list === null)
			{
				$board_list = array();
				$request = $smcFunc['db_query']('', '
					SELECT id_board
					FROM {db_prefix}boards');

				while ($row = $smcFunc['db_fetch_row']($request))
					$board_list[$row[0]] = true;

				$smcFunc['db_free_result']($request);
			}

			$lOptions = array();

			if (!empty($_POST[$var[1]]))
				foreach ($_POST[$var[1]] as $invar => $dummy)
					if (isset($board_list[$invar]))
						$lOptions[] = $invar;

			$setArray[$var[1]] = !empty($lOptions) ? implode(',', $lOptions) : '';
		}
		// Integers!
		elseif ($var[0] == 'int')
		{
			$setArray[$var[1]] = (int) $_POST[$var[1]];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$setArray[$var[1]] = max($min, $setArray[$var[1]]);

			// Do we have a max value for this as well?
			if (isset($var['max']))
				$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
		}
		// Floating point!
		elseif ($var[0] == 'float')
		{
			$setArray[$var[1]] = (float) $_POST[$var[1]];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$setArray[$var[1]] = max($min, $setArray[$var[1]]);

			// Do we have a max value for this as well?
			if (isset($var['max']))
				$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
		}
		// Text!
		elseif (in_array($var[0], array('text', 'large_text', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time')))
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

/**
 * Get the installed Cache API implementations.
 *
 */
function loadCacheAPIs()
{
	global $sourcedir, $txt;

	// Make sure our class is in session.
	require_once($sourcedir . '/Class-CacheAPI.php');

	$apis = array();
	if ($dh = opendir($sourcedir))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (is_file($sourcedir . '/' . $file) && preg_match('~^CacheAPI-([A-Za-z\d_]+)\.php$~', $file, $matches))
			{
				$tryCache = strtolower($matches[1]);

				require_once($sourcedir . '/' . $file);
				$cache_class_name = $tryCache . '_cache';
				$testAPI = new $cache_class_name();

				// No Support?  NEXT!
				if (!$testAPI->isSupported(true))
					continue;

				$apis[$tryCache] = isset($txt[$tryCache . '_cache']) ? $txt[$tryCache . '_cache'] : $tryCache;
			}
		}
	}
	closedir($dh);

	return $apis;
}

/**
 * Registers the site with the Simple Machines Stat collection. This function
 * purposely does not use updateSettings.php as it will be called shortly after
 * this process completes by the saveSettings() function.
 *
 * @see Stats.php SMStats() for more information.
 * @link https://www.simplemachines.org/about/stats.php for more info.
 *
 */
function registerSMStats()
{
	global $modSettings, $boardurl, $smcFunc;

	// Already have a key?  Can't register again.
	if (!empty($modSettings['sm_stats_key']))
		return true;

	$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
	if ($fp)
	{
		$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode($boardurl) . ' HTTP/1.1' . "\r\n";
		$out .= 'Host: www.simplemachines.org' . "\r\n";
		$out .= 'Connection: Close' . "\r\n\r\n";
		fwrite($fp, $out);

		$return_data = '';
		while (!feof($fp))
			$return_data .= fgets($fp, 128);

		fclose($fp);

		// Get the unique site ID.
		preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

		if (!empty($ID[1]))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array('sm_stats_key', $ID[1]),
				array('variable')
			);
			return true;
		}
	}

	return false;
}

?>