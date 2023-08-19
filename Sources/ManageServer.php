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
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
 * the appropriate one based on the sub-action.
 *
 * Requires the admin_forum permission.
 * Redirects to the appropriate function based on the sub-action.
 *
 * Uses edit_settings adminIndex.
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
		'export' => 'ModifyExportSettings',
		'loads' => 'ModifyLoadBalancingSettings',
		'phpinfo' => 'ShowPHPinfoSettings',
	);

	// Warn the user if there's any relevant information regarding Settings.php.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	if ($settings_backup_fail)
		$context['settings_message'] = array(
			'label' => $txt['admin_backup_fail'],
			'tag' => 'div',
			'class' => 'centertext strong'
		);

	$context['settings_not_writable'] = $settings_not_writable;

	call_integration_hook('integrate_server_settings', array(&$subActions));

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';

	$context['sub_action'] = $_REQUEST['sa'];

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
	global $scripturl, $context, $txt, $modSettings, $boardurl, $sourcedir, $smcFunc;

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
		'force_ssl' => array('force_ssl', $txt['force_ssl'], 'db', 'select', array($txt['force_ssl_off'], $txt['force_ssl_complete']), 'force_ssl'),
		array('image_proxy_enabled', $txt['image_proxy_enabled'], 'file', 'check', null, 'image_proxy_enabled'),
		array('image_proxy_secret', $txt['image_proxy_secret'], 'file', 'text', 30, 'image_proxy_secret'),
		array('image_proxy_maxsize', $txt['image_proxy_maxsize'], 'file', 'int', null, 'image_proxy_maxsize'),
		'',
		array('enable_sm_stats', $txt['enable_sm_stats'], 'db', 'check', null, 'enable_sm_stats'),
	);

	call_integration_hook('integrate_general_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// If no cert, force_ssl must remain 0 (The admin search doesn't require this)
	$config_vars['force_ssl']['disabled'] = empty($modSettings['force_ssl']) && !ssl_cert_found($boardurl);

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=general;save';
	$context['settings_title'] = $txt['general_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_general_settings');

		foreach ($config_vars as $config_var)
		{
			if (is_array($config_var) && isset($config_var[3]) && $config_var[3] == 'text' && !empty($_POST[$config_var[0]]))
				$_POST[$config_var[0]] = $smcFunc['normalize']($_POST[$config_var[0]]);
		}

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
	if (empty($context['settings_not_writable']))
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
	updateSettingsFile(array('boardurl' => $newval));

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
	if ($smcFunc['db_title'] === POSTGRE_TITLE)
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
		array('cookieTime', $txt['cookieTime'], 'db', 'select', array_filter(array_map(
			function ($str) use ($txt)
			{
				return isset($txt[$str]) ? $txt[$str] : '';
			},
			$context['login_cookie_times']
		))),
		array('localCookies', $txt['localCookies'], 'db', 'check', false, 'localCookies'),
		array('globalCookies', $txt['globalCookies'], 'db', 'check', false, 'globalCookies'),
		array('globalCookiesDomain', $txt['globalCookiesDomain'], 'db', 'text', false, 'globalCookiesDomain'),
		array('secureCookies', $txt['secureCookies'], 'db', 'check', false, 'secureCookies', 'disabled' => !httpsOn()),
		array('httponlyCookies', $txt['httponlyCookies'], 'db', 'check', false, 'httponlyCookies'),
		array('samesiteCookies', $txt['samesiteCookies'], 'db', 'select', array(
				'none' 		=> $txt['samesiteNone'],
				'lax' 		=> $txt['samesiteLax'],
				'strict' 	=> $txt['samesiteStrict']
			),
			'samesiteCookies'),
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
	});
	', true);

	if (empty($user_settings['tfa_secret']))
		addInlineJavaScript('');

	call_integration_hook('integrate_cookie_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cookie;save';
	$context['settings_title'] = $txt['cookies_sessions_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		call_integration_hook('integrate_save_cookie_settings');

		$_POST['cookiename'] = $smcFunc['normalize']($_POST['cookiename']);

		// Local and global do not play nicely together.
		if (!empty($_POST['localCookies']) && empty($_POST['globalCookies']))
			unset ($_POST['globalCookies']);

		if (empty($modSettings['localCookies']) != empty($_POST['localCookies']) || empty($modSettings['globalCookies']) != empty($_POST['globalCookies']))
			$scope_changed = true;

		if (!empty($_POST['globalCookiesDomain']))
		{
			$_POST['globalCookiesDomain'] = parse_iri(normalize_iri((strpos($_POST['globalCookiesDomain'], '//') === false ? 'http://' : '') . ltrim($_POST['globalCookiesDomain'], '.')), PHP_URL_HOST);

			if (!preg_match('/(?:^|\.)' . preg_quote($_POST['globalCookiesDomain'], '/') . '$/u', parse_iri($boardurl, PHP_URL_HOST)))
				fatal_lang_error('invalid_cookie_domain', false);
		}

		// Per spec, if samesite setting is 'none', cookies MUST be secure. Thems the rules. Else you lock everyone out...
		if (!empty($_POST['samesiteCookies']) && ($_POST['samesiteCookies'] === 'none') && empty($_POST['secureCookies']))
			fatal_lang_error('samesiteSecureRequired', false);

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

		array('check', 'allow_cors'),
		array('check', 'allow_cors_credentials'),
		array('text', 'cors_domains'),
		array('text', 'cors_headers'),
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
				'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For',
				'HTTP_CLIENT_IP' => 'Client-IP',
				'HTTP_X_REAL_IP' => 'X-Real-IP',
				'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP'
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
		if (!empty($_POST['cors_domains']))
		{
			$cors_domains = explode(',', $_POST['cors_domains']);

			foreach ($cors_domains as &$cors_domain)
			{
				if (strpos($cors_domain, '//') === false)
					$cors_domain = '//' . $cors_domain;

				$temp = parse_iri(normalize_iri($cors_domain));

				if (strpos($temp['host'], '*') !== false)
					$temp['host'] = substr($temp['host'], strrpos($temp['host'], '*'));

				$cors_domain = (!empty($temp['scheme']) ? $temp['scheme'] . '://' : '') . $temp['host'] . (!empty($temp['port']) ? ':' . $temp['port'] : '');
			}

			$_POST['cors_domains'] = implode(',', $cors_domains);
		}

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
	global $context, $scripturl, $txt, $cacheAPI, $cache_enable, $cache_accelerator;

	// Detect all available optimizers
	$detectedCacheApis = loadCacheAPIs();
	$apis_names = array();

	/* @var CacheApiInterface $cache_api */
	foreach ($detectedCacheApis as $class_name => $cache_api)
	{
		$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

		$apis_names[$class_name] = isset($txt[$class_name_txt_key . '_cache']) ?
			$txt[$class_name_txt_key . '_cache'] : $class_name;
	}

	// set our values to show what, if anything, we found
	if (empty($detectedCacheApis))
	{
		$txt['cache_settings_message'] = '<strong class="alert">' . $txt['detected_no_caching'] . '</strong>';
		$cache_level = array($txt['cache_off']);
		$apis_names['none'] = $txt['cache_off'];
	}

	else
	{
		$txt['cache_settings_message'] = '<strong class="success">' .
			sprintf($txt['detected_accelerators'], implode(', ', $apis_names)) . '</strong>';

		$cache_level = array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3']);
	}

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a few settings, but they are important
		array('', $txt['cache_settings_message'], '', 'desc'),
		array('cache_enable', $txt['cache_enable'], 'file', 'select', $cache_level, 'cache_enable'),
		array('cache_accelerator', $txt['cache_accelerator'], 'file', 'select', $apis_names),
	);

	// some javascript to enable / disable certain settings if the option is not selected
	$context['settings_post_javascript'] = '
		$(document).ready(function() {
			$("#cache_accelerator").change();
		});';

	call_integration_hook('integrate_modify_cache_settings', array(&$config_vars));

	// Maybe we have some additional settings from the selected accelerator.
	if (!empty($detectedCacheApis))
		/* @var CacheApiInterface $cache_api */
		foreach ($detectedCacheApis as $class_name_txt_key => $cache_api)
			if (is_callable(array($cache_api, 'cacheSettings')))
				$cache_api->cacheSettings($config_vars);

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		call_integration_hook('integrate_save_cache_settings');

		if (is_callable(array($cacheAPI, 'cleanCache')) && ((int) $_POST['cache_enable'] < $cache_enable || $_POST['cache_accelerator'] != $cache_accelerator))
		{
			$cacheAPI->cleanCache();
		}

		saveSettings($config_vars);
		$_SESSION['adm-save'] = true;

		// We need to save the $cache_enable to $modSettings as well
		updateSettings(array('cache_enable' => (int) $_POST['cache_enable']));

		// exit so we reload our new settings on the page
		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
	}

	loadLanguage('ManageMaintenance');
	createToken('admin-maint');
	$context['template_layers'][] = 'clean_cache_button';

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];

	// Changing cache settings won't have any effect if Settings.php is not writable.
	$context['save_disabled'] = $context['settings_not_writable'];

	// Decide what message to show.
	if (!$context['save_disabled'])
		$context['settings_message'] = $txt['caching_information'];

	// Prepare the template.
	prepareServerSettingsContext($config_vars);
}

/**
 * Controls settings for data export functionality
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyExportSettings($return_config = false)
{
	global $context, $scripturl, $txt, $modSettings, $boarddir, $sourcedir;

	// Fill in a default value for this if it is missing.
	if (empty($modSettings['export_dir']))
		$modSettings['export_dir'] = $boarddir . DIRECTORY_SEPARATOR . 'exports';

	/*
		Some paranoid hosts worry that the disk space functions pose a security
		risk. Usually these hosts just disable the functions and move on, which
		is fine. A rare few, however, are not only paranoid, but also think it'd
		be a "clever" security move to overload the disk space functions with
		custom code that intentionally delivers false information, which is
		idiotic and evil. At any rate, if the functions are unavailable or if
		they report obviously insane values, it's not possible to track disk
		usage correctly.
	 */
	$diskspace_disabled = (!function_exists('disk_free_space') || !function_exists('disk_total_space') || intval(@disk_total_space(file_exists($modSettings['export_dir']) ? $modSettings['export_dir'] : $boarddir)) < 1440);

	$context['settings_message'] = $txt['export_settings_description'];

	$config_vars = array(
		array('text', 'export_dir', 40),
		array('int', 'export_expiry', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['days_word']),
		array('int', 'export_min_diskspace_pct', 'postinput' => '%', 'max' => 80, 'disabled' => $diskspace_disabled),
		array('int', 'export_rate', 'min' => 5, 'max' => 500, 'step' => 5, 'subtext' => $txt['export_rate_desc']),
	);

	call_integration_hook('integrate_export_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	if (isset($_REQUEST['save']))
	{
		$prev_export_dir = is_dir($modSettings['export_dir']) ? rtrim($modSettings['export_dir'], '/\\') : '';

		if (!empty($_POST['export_dir']))
			$_POST['export_dir'] = rtrim($_POST['export_dir'], '/\\');

		if ($diskspace_disabled)
			$_POST['export_min_diskspace_pct'] = 0;

		$_POST['export_rate'] = max(5, min($_POST['export_rate'], 500));

		saveDBSettings($config_vars);

		// Create the new directory, but revert to the previous one if anything goes wrong.
		require_once($sourcedir . '/Profile-Export.php');
		create_export_dir($prev_export_dir);

		// Ensure we don't lose track of any existing export files.
		if (!empty($prev_export_dir) && $prev_export_dir != $modSettings['export_dir'])
		{
			$export_files = glob($prev_export_dir . DIRECTORY_SEPARATOR . '*');

			foreach ($export_files as $export_file)
			{
				if (!in_array(basename($export_file), array('index.php', '.htaccess')))
				{
					rename($export_file, $modSettings['export_dir'] . DIRECTORY_SEPARATOR . basename($export_file));
				}
			}
		}

		call_integration_hook('integrate_save_export_settings');

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=serversettings;sa=export;' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=export;save';
	$context['settings_title'] = $txt['export_settings'];

	prepareDBSettingContext($config_vars);
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
	$context['settings_message'] = array('label' => $txt['loadavg_disabled_conf'], 'class' => 'error');

	if (DIRECTORY_SEPARATOR === '\\')
	{
		$context['settings_message']['label'] = $txt['loadavg_disabled_windows'];
		if (isset($_GET['save']))
			$_SESSION['adm-save'] = $context['settings_message']['label'];
	}
	elseif (stripos(PHP_OS, 'darwin') === 0)
	{
		$context['settings_message']['label'] = $txt['loadavg_disabled_osx'];
		if (isset($_GET['save']))
			$_SESSION['adm-save'] = $context['settings_message']['label'];
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
			$context['settings_message']['label'] = sprintf($txt['loadavg_warning'], $modSettings['load_average']);
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
	global $context, $modSettings, $smcFunc, $txt;

	if (!empty($context['settings_not_writable']))
		$context['settings_message'] = array(
			'label' => $txt['settings_not_writable'],
			'tag' => 'div',
			'class' => 'centertext strong'
		);

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
				'size' => !empty($config_var[4]) && !is_array($config_var[4]) ? $config_var[4] : 0,
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
				if (empty($config_var['size']) && !empty($config_var['multiple']))
					$context['config_vars'][$config_var[1]]['size'] = max(4, count($config_var[2]));
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
			if (!isset($tag['require_parents']))
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
					'show_help' => isset($helptxt['tag_' . $tag]),
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

		$_POST['boardurl'] = normalize_iri($_POST['boardurl']);
	}

	require_once($sourcedir . '/Subs-Admin.php');

	// Any passwords?
	$config_passwords = array();

	// All the numeric variables.
	$config_nums = array();

	// All the checkboxes
	$config_bools = array();

	// Ones that accept multiple types (should be rare)
	$config_multis = array();

	// Get all known setting definitions and assign them to our groups above.
	$settings_defs = get_settings_defs();
	foreach ($settings_defs as $var => $def)
	{
		if (!is_string($var))
			continue;

		if (!empty($def['is_password']))
		{
			$config_passwords[] = $var;
		}
		else
		{
			// Special handling if multiple types are allowed.
			if (is_array($def['type']))
			{
				// Obviously, we don't need null here.
				$def['type'] = array_filter(
					$def['type'],
					function ($type)
					{
						return $type !== 'NULL';
					}
				);

				$type = count($def['type']) == 1 ? reset($def['type']) : 'multiple';
			}
			else
				$type = $def['type'];

			switch ($type)
			{
				case 'multiple':
					$config_multis[$var] = $def['type'];

				case 'double':
					$config_nums[] = $var;
					break;

				case 'integer':
					// Some things saved as integers are presented as booleans
					foreach ($config_vars as $config_var)
					{
						if (is_array($config_var) && $config_var[0] == $var)
						{
							if ($config_var[3] == 'check')
							{
								$config_bools[] = $var;
								break 2;
							}
							else
								break;
						}
					}
					$config_nums[] = $var;
					break;

				case 'boolean':
					$config_bools[] = $var;
					break;

				default:
					break;
			}
		}
	}

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = array();
	// Figure out which config vars we're saving here...
	foreach ($config_vars as $config_var)
	{
		if (!is_array($config_var) || $config_var[2] != 'file')
			continue;

		$var_name = $config_var[0];

		// Unknown setting?
		if (!isset($settings_defs[$var_name]) && isset($config_var[3]))
		{
			switch ($config_var[3])
			{
				case 'int':
				case 'float':
					$config_nums[] = $var_name;
					break;

				case 'check':
					$config_bools[] = $var_name;
					break;

				default:
					break;
			}
		}

		if (!in_array($var_name, $config_bools) && !isset($_POST[$var_name]))
			continue;

		if (in_array($var_name, $config_passwords))
		{
			if (isset($_POST[$var_name][1]) && $_POST[$var_name][0] == $_POST[$var_name][1])
				$new_settings[$var_name] = $_POST[$var_name][0];
		}
		elseif (in_array($var_name, $config_nums))
		{
			$new_settings[$var_name] = (int) $_POST[$var_name];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($config_var['min']) ? $config_var['min'] : 0;
			$new_settings[$var_name] = max($min, $new_settings[$var_name]);

			// Is there a max value for this as well?
			if (isset($config_var['max']))
				$new_settings[$var_name] = min($config_var['max'], $new_settings[$var_name]);
		}
		elseif (in_array($var_name, $config_bools))
		{
			$new_settings[$var_name] = !empty($_POST[$var_name]);
		}
		elseif (isset($config_multis[$var_name]))
		{
			$is_acceptable_type = false;

			foreach ($config_multis[$var_name] as $type)
			{
				$temp = $_POST[$var_name];
				settype($temp, $type);

				if ($temp == $_POST[$var_name])
				{
					$new_settings[$var_name] = $temp;
					$is_acceptable_type = true;
					break;
				}
			}

			if (!$is_acceptable_type)
				fatal_error('Invalid config_var \'' . $var_name . '\'');
		}
		else
		{
			$new_settings[$var_name] = $_POST[$var_name];
		}
	}

	// Save the relevant settings in the Settings.php file.
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
	global $sourcedir;

	$cacheAPIdir = $sourcedir . '/Cache';

	$loadedApis = array();
	$apis_dir = $cacheAPIdir .'/'. CacheApi::APIS_FOLDER;

	$api_classes = new GlobIterator($apis_dir . '/*.php', FilesystemIterator::NEW_CURRENT_AND_KEY);

	foreach ($api_classes as $file_path => $file_info)
	{
		require_once($apis_dir . '/' . $file_path);

		$class_name = $file_info->getBasename('.php');
		$fully_qualified_class_name = CacheApi::APIS_NAMESPACE . $class_name;

		/* @var CacheApiInterface $cache_api */
		$cache_api = new $fully_qualified_class_name();

		// Deal with it!
		if (!($cache_api instanceof CacheApiInterface) || !($cache_api instanceof CacheApi))
			continue;

		// No Support?  NEXT!
		if (!$cache_api->isSupported(true))
			continue;

		$loadedApis[$class_name] = $cache_api;
	}

	call_integration_hook('integrate_load_cache_apis', array(&$loadedApis));

	return $loadedApis;
}

/**
 * Registers the site with the Simple Machines Stat collection. This function
 * purposely does not use updateSettings.php as it will be called shortly after
 * this process completes by the saveSettings() function.
 *
 * @see SMStats() for more information.
 * @link https://www.simplemachines.org/about/stats.php for more info.
 *
 */
function registerSMStats()
{
	global $modSettings, $boardurl, $smcFunc;

	// Already have a key?  Can't register again.
	if (!empty($modSettings['sm_stats_key']))
		return true;

	$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);
	if (!$fp)
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