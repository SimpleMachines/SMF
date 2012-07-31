<?php

/**
 * This file is here to make it easier for installed mods to have
 * settings and options.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * This just avoids some repetition.
 *
 * @param array $subActions = array()
 * @param string $defaultAction = ''
 */
function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
{
	global $context, $txt, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	$context['sub_template'] = 'show_settings';

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($defaultAction) ? $defaultAction : array_pop($temp = array_keys($subActions)));
	$context['sub_action'] = $_REQUEST['sa'];
}

/**
 * This function passes control through to the relevant tab.
 */
function ModifyFeatureSettings()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['modSettings_title'];

	$subActions = array(
		'basic' => 'ModifyBasicSettings',
		'layout' => 'ModifyLayoutSettings',
		'karma' => 'ModifyKarmaSettings',
		'sig' => 'ModifySignatureSettings',
		'profile' => 'ShowCustomProfiles',
		'profileedit' => 'EditCustomProfiles',
	);

	call_integration_hook('integrate_modify_features', array(&$subActions));

	// If Advanced Profile Fields are disabled don't show the setting page
	if (!in_array('cp', $context['admin_features']))
		unset($subActions['profile']);

	// Same for Karma
	if (!in_array('k', $context['admin_features']))
		unset($subActions['karma']);

	loadGeneralSettingParameters($subActions, 'basic');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['modSettings_title'],
		'help' => 'featuresettings',
		'description' => sprintf($txt['modSettings_desc'], $settings['theme_id'], $context['session_id'], $context['session_var']),
		'tabs' => array(
			'basic' => array(
			),
			'layout' => array(
			),
			'karma' => array(
			),
			'sig' => array(
				'description' => $txt['signature_settings_desc'],
			),
			'profile' => array(
				'description' => $txt['custom_profile_desc'],
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

/**
 * This function passes control through to the relevant security tab.
 */
function ModifySecuritySettings()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['admin_security_moderation'];

	$subActions = array(
		'general' => 'ModifyGeneralSecuritySettings',
		'spam' => 'ModifySpamSettings',
		'moderation' => 'ModifyModerationSettings',
	);

	call_integration_hook('integrate_modify_security', array(&$subActions));

	// If Warning System is disabled don't show the setting page
	if (!in_array('w', $context['admin_features']))
		unset($subActions['moderation']);

	loadGeneralSettingParameters($subActions, 'general');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_security_moderation'],
		'help' => 'securitysettings',
		'description' => $txt['security_settings_desc'],
		'tabs' => array(
			'general' => array(
			),
			'spam' => array(
				'description' => $txt['antispam_Settings_desc'] ,
			),
			'moderation' => array(
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

/**
 * This my friend, is for all the mod authors out there.
 */
function ModifyModSettings()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['admin_modifications'];

	$subActions = array(
		'hooks' => 'list_integration_hooks',
		'general' => 'ModifyGeneralModSettings',
		// Mod authors, once again, if you have a whole section to add do it AFTER this line, and keep a comma at the end.
	);

	// Make it easier for mods to add new areas.
	call_integration_hook('integrate_modify_modifications', array(&$subActions));

	loadGeneralSettingParameters($subActions, $context['hooks_exist'] ? 'hooks' : 'general');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_modifications'],
		'help' => 'modsettings',
		'description' => $txt['modification_settings_desc'],
		'tabs' => array(
			'hooks' => array(
			),
			'general' => array(
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

/**
 * This is an overall control panel enabling/disabling lots of SMF's key feature components.
 *
 * @param $return_config
 */
function ModifyCoreFeatures($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings;

	/* This is an array of all the features that can be enabled/disabled - each option can have the following:
		title		- Text title of this item (If standard string does not exist).
		desc		- Description of this feature (If standard string does not exist).
		settings	- Array of settings to change (For each name => value) on enable - reverse is done for disable. If > 1 will not change value if set.
		setting_callback- Function that returns an array of settings to save - takes one parameter which is value for this feature.
		save_callback	- Function called on save, takes state as parameter.
	*/
	$core_features = array(
		// cd = calendar.
		'cd' => array(
			'url' => 'action=admin;area=managecalendar',
			'settings' => array(
				'cal_enabled' => 1,
			),
		),
		// cp = custom profile fields.
		'cp' => array(
			'url' => 'action=admin;area=featuresettings;sa=profile',
			'save_callback' => create_function('$value', '
				global $smcFunc;
				if (!$value)
				{
					$smcFunc[\'db_query\'](\'\', \'
						UPDATE {db_prefix}custom_fields
						SET active = 0\');
				}
			'),
			'setting_callback' => create_function('$value', '
				if (!$value)
					return array(
						\'disabled_profile_fields\' => \'\',
						\'registration_fields\' => \'\',
						\'displayFields\' => \'\',
					);
				else
					return array();
			'),
		),
		// dr = drafts
		'dr' => array(
			'url' => 'action=admin;area=managedrafts',
			'settings' => array(
				'drafts_enabled' => 1,
				'drafts_post_enabled' => 2,
				'drafts_pm_enabled' => 2,
				'drafts_autosave_enabled' => 2,
				'drafts_show_saved_enabled' => 2,
			),
			'setting_callback' => create_function('$value', '
				global $smcFunc, $sourcedir;

				// Set the correct disabled value for the scheduled task.
				$smcFunc[\'db_query\'](\'\', \'
					UPDATE {db_prefix}scheduled_tasks
					SET disabled = {int:disabled}
					WHERE task = {string:task}\',
					array(
						\'disabled\' => $value ? 0 : 1,
						\'task\' => \'remove_old_drafts\',
					)
				);
			'),
		),
		// k = karma.
		'k' => array(
			'url' => 'action=admin;area=featuresettings;sa=karma',
			'settings' => array(
				'karmaMode' => 2,
			),
		),
		// ml = moderation log.
		'ml' => array(
			'url' => 'action=admin;area=logs;sa=modlog',
			'settings' => array(
				'modlog_enabled' => 1,
			),
		),
		// pm = post moderation.
		'pm' => array(
			'url' => 'action=admin;area=permissions;sa=postmod',
			'setting_callback' => create_function('$value', '
				global $sourcedir;

				// Cant use warning post moderation if disabled!
				if (!$value)
				{
					require_once($sourcedir . \'/PostModeration.php\');
					approveAllData();

					return array(\'warning_moderate\' => 0);
				}
				else
					return array();
			'),
		),
		// ps = Paid Subscriptions.
		'ps' => array(
			'url' => 'action=admin;area=paidsubscribe',
			'settings' => array(
				'paid_enabled' => 1,
			),
			'setting_callback' => create_function('$value', '
				global $smcFunc, $sourcedir;

				// Set the correct disabled value for scheduled task.
				$smcFunc[\'db_query\'](\'\', \'
					UPDATE {db_prefix}scheduled_tasks
					SET disabled = {int:disabled}
					WHERE task = {string:task}\',
					array(
						\'disabled\' => $value ? 0 : 1,
						\'task\' => \'paid_subscriptions\',
					)
				);

				// Should we calculate next trigger?
				if ($value)
				{
					require_once($sourcedir . \'/ScheduledTasks.php\');
					CalculateNextTrigger(\'paid_subscriptions\');
				}
			'),
		),
		// rg = report generator.
		'rg' => array(
			'url' => 'action=admin;area=reports',
		),
		// w = warning.
		'w' => array(
			'url' => 'action=admin;area=securitysettings;sa=moderation',
			'setting_callback' => create_function('$value', '
				global $modSettings;
				list ($modSettings[\'warning_enable\'], $modSettings[\'user_limit\'], $modSettings[\'warning_decrement\']) = explode(\',\', $modSettings[\'warning_settings\']);
				$warning_settings = ($value ? 1 : 0) . \',\' . $modSettings[\'user_limit\'] . \',\' . $modSettings[\'warning_decrement\'];
				if (!$value)
				{
					$returnSettings = array(
						\'warning_watch\' => 0,
						\'warning_moderate\' => 0,
						\'warning_mute\' => 0,
					);
				}
				elseif (empty($modSettings[\'warning_enable\']) && $value)
				{
					$returnSettings = array(
						\'warning_watch\' => 10,
						\'warning_moderate\' => 35,
						\'warning_mute\' => 60,
					);
				}
				else
					$returnSettings = array();

				$returnSettings[\'warning_settings\'] = $warning_settings;
				return $returnSettings;
			'),
		),
		// Search engines
		'sp' => array(
			'url' => 'action=admin;area=sengines',
			'settings' => array(
				'spider_mode' => 1,
			),
			'setting_callback' => create_function('$value', '
				// Turn off the spider group if disabling.
				if (!$value)
					return array(\'spider_group\' => 0, \'show_spider_online\' => 0);
			'),
			'on_save' => create_function('', '
				global $sourcedir, $modSettings;
				require_once($sourcedir . \'/ManageSearchEngines.php\');
				recacheSpiderNames();
			'),
		),
	);

	// Anyone who would like to add a core feature?
	call_integration_hook('integrate_core_features', array(&$core_features));

	// Are we getting info for the help section.
	if ($return_config)
	{
		$return_data = array();
		foreach ($core_features as $id => $data)
			$return_data[] = array('switch', isset($data['title']) ? $data['title'] : $txt['core_settings_item_' . $id]);
		return $return_data;
	}

	loadGeneralSettingParameters();

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();

	if (isset($_GET['xml']))
	{
		$tokenValidation = validateToken('admin-core', 'post', false);

		if (empty($tokenValidation))
			return 'token_verify_fail';
	}
	else
		validateToken('admin-core');

		$setting_changes = array('admin_features' => array());

		// Cycle each feature and change things as required!
		foreach ($core_features as $id => $feature)
		{
			// Enabled?
			if (!empty($_POST['feature_' . $id]))
				$setting_changes['admin_features'][] = $id;

			// Setting values to change?
			if (isset($feature['settings']))
			{
				foreach ($feature['settings'] as $key => $value)
				{
					if (empty($_POST['feature_' . $id]) || (!empty($_POST['feature_' . $id]) && ($value < 2 || empty($modSettings[$key]))))
						$setting_changes[$key] = !empty($_POST['feature_' . $id]) ? $value : !$value;
				}
			}
			// Is there a call back for settings?
			if (isset($feature['setting_callback']))
			{
				$returned_settings = $feature['setting_callback'](!empty($_POST['feature_' . $id]));
				if (!empty($returned_settings))
					$setting_changes = array_merge($setting_changes, $returned_settings);
			}

			// Standard save callback?
			if (isset($feature['on_save']))
				$feature['on_save']();
		}

		// Make sure this one setting is a string!
		$setting_changes['admin_features'] = implode(',', $setting_changes['admin_features']);

		// Make any setting changes!
		updateSettings($setting_changes);

		// This is needed to let menus appear if cache > 2
		clean_cache('data');

		// Any post save things?
		foreach ($core_features as $id => $feature)
		{
			// Standard save callback?
			if (isset($feature['save_callback']))
				$feature['save_callback'](!empty($_POST['feature_' . $id]));
		}

		if (!isset($_REQUEST['xml']))
			redirectexit('action=admin;area=corefeatures;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Put them in context.
	$context['features'] = array();
	foreach ($core_features as $id => $feature)
		$context['features'][$id] = array(
			'title' => isset($feature['title']) ? $feature['title'] : $txt['core_settings_item_' . $id],
			'desc' => isset($feature['desc']) ? $feature['desc'] : $txt['core_settings_item_' . $id . '_desc'],
			'enabled' => in_array($id, $context['admin_features']),
			'state' => in_array($id, $context['admin_features']) ? 'on' : 'off',
			'url' => !empty($feature['url']) ? $scripturl . '?' . $feature['url'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '',
			'image' => (file_exists($settings['theme_dir'] . '/images/admin/feature_' . $id . '.png') ? $settings['images_url'] : $settings['default_images_url']) . '/admin/feature_' . $id . '.png',
		);

	// Are they a new user?
	$context['is_new_install'] = !isset($modSettings['admin_features']);
	$context['force_disable_tabs'] = $context['is_new_install'];
	// Don't show them this twice!
	if ($context['is_new_install'])
		updateSettings(array('admin_features' => ''));

	// sub_template is already generic_xml and the token is created somewhere else
	if (isset($_REQUEST['xml']))
		return;

	$context['sub_template'] = 'core_features';
	$context['page_title'] = $txt['core_settings_title'];

	// We love our tokens.
	createToken('admin-core');
}

/**
 *
 * @param $return_config
 */
function ModifyBasicSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings;

	$config_vars = array(
			// Big Options... polls, sticky, bbc....
			array('select', 'pollMode', array($txt['disable_polls'], $txt['enable_polls'], $txt['polls_as_topics'])),
		'',
			// Basic stuff, titles, flash, permissions...
			array('check', 'allow_guestAccess'),
			array('check', 'enable_buddylist'),
			array('check', 'allow_editDisplayName'),
			array('check', 'allow_hideOnline'),
			array('check', 'titlesEnable'),
			array('text', 'default_personal_text', 'subtext' => $txt['default_personal_text_note']),
		'',
			// Jquery source
			array('select', 'jquery_source', array('auto' => $txt['jquery_auto'], 'local' => $txt['jquery_local'], 'cdn' => $txt['jquery_cdn'])),
		'',
			// SEO stuff
			array('check', 'queryless_urls', 'subtext' => '<strong>' . $txt['queryless_urls_note'] . '</strong>'),
			array('text', 'meta_keywords', 'subtext' => $txt['meta_keywords_note'], 'size' => 50),
		'',
			// Number formatting, timezones.
			array('text', 'time_format'),
			array('float', 'time_offset', 'subtext' => $txt['setting_time_offset_note'], 6, 'postinput' => $txt['hours']),
			'default_timezone' => array('select', 'default_timezone', array()),
		'',
			// Who's online?
			array('check', 'who_enabled'),
			array('int', 'lastActive', 6, 'postinput' => $txt['minutes']),
		'',
			// Statistics.
			array('check', 'trackStats'),
			array('check', 'hitStats'),
		'',
			// Option-ish things... miscellaneous sorta.
			array('check', 'allow_disableAnnounce'),
			array('check', 'disallow_sendBody'),
	);

	// Get all the time zones.
	if (function_exists('timezone_identifiers_list') && function_exists('date_default_timezone_set'))
	{
		$all_zones = timezone_identifiers_list();
		// Make sure we set the value to the same as the printed value.
		foreach ($all_zones as $zone)
			$config_vars['default_timezone'][2][$zone] = $zone;
	}
	else
		unset($config_vars['default_timezone']);

	call_integration_hook('integrate_modify_basic_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Prevent absurd boundaries here - make it a day tops.
		if (isset($_POST['lastActive']))
			$_POST['lastActive'] = min((int) $_POST['lastActive'], 1440);

		call_integration_hook('integrate_save_basic_settings');

		saveDBSettings($config_vars);

		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

/**
 * Settings really associated with general security aspects.
 *
 * @param $return_config
 */
function ModifyGeneralSecuritySettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings;

	$config_vars = array(
			array('check', 'guest_hideContacts'),
			array('check', 'make_email_viewable'),
		'',
			array('int', 'failed_login_threshold'),
			array('int', 'loginHistoryDays'),
		'',
			array('check', 'enableErrorLogging'),
			array('check', 'enableErrorQueryLogging'),
		'',
			array('check', 'securityDisable'),
			array('check', 'securityDisable_moderate'),
		'',
			// Reactive on email, and approve on delete
			array('check', 'send_validation_onChange'),
			array('check', 'approveAccountDeletion'),
		'',
			// Password strength.
			array('select', 'password_strength', array($txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high'])),
		'',
			// Reporting of personal messages?
			array('check', 'enableReportPM'),
	);

	call_integration_hook('integrate_general_security_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);

		call_integration_hook('integrate_save_general_security_settings');

		writeLog();
		redirectexit('action=admin;area=securitysettings;sa=general');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=general';
	$context['settings_title'] = $txt['mods_cat_security_general'];

	prepareDBSettingContext($config_vars);
}

/**
 *
 * @param $return_config
 */
function ModifyLayoutSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc;

	$config_vars = array(
			// Pagination stuff.
			array('check', 'compactTopicPagesEnable'),
			array('int', 'compactTopicPagesContiguous', null, $txt['contiguous_page_display'] . '<div class="smalltext">' . str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br />' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>') . '</div>'),
			array('int', 'defaultMaxMembers'),
		'',
			// Stuff that just is everywhere - today, search, online, etc.
			array('select', 'todayMod', array($txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'])),
			array('check', 'topbottomEnable'),
			array('check', 'onlineEnable'),
			array('check', 'enableVBStyleLogin'),
		'',
			// Automagic image resizing.
			array('int', 'max_image_width'),
			array('int', 'max_image_height'),
		'',
			// This is like debugging sorta.
			array('check', 'timeLoadPageEnable'),
	);

	call_integration_hook('integrate_layout_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_layout_settings');

		saveDBSettings($config_vars);
		writeLog();

		redirectexit('action=admin;area=featuresettings;sa=layout');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=layout';
	$context['settings_title'] = $txt['mods_cat_layout'];

	prepareDBSettingContext($config_vars);
}

/**
 *
 * @param $return_config
 */
function ModifyKarmaSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc;

	$config_vars = array(
			// Karma - On or off?
			array('select', 'karmaMode', explode('|', $txt['karma_options'])),
		'',
			// Who can do it.... and who is restricted by time limits?
			array('int', 'karmaMinPosts', 6, 'postinput' => strtolower($txt['posts'])),
			array('float', 'karmaWaitTime', 6, 'postinput' => $txt['hours']),
			array('check', 'karmaTimeRestrictAdmins'),
		'',
			// What does it look like?  [smite]?
			array('text', 'karmaLabel'),
			array('text', 'karmaApplaudLabel'),
			array('text', 'karmaSmiteLabel'),
	);

	call_integration_hook('integrate_karma_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_karma_settings');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=featuresettings;sa=karma');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=karma';
	$context['settings_title'] = $txt['karma'];

	prepareDBSettingContext($config_vars);
}

/**
 * Moderation type settings - although there are fewer than we have you believe ;)
 *
 * @param bool $return_config = false
 */
function ModifyModerationSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings;

	$config_vars = array(
			// Warning system?
			array('int', 'warning_watch', 'subtext' => $txt['setting_warning_watch_note'], 'help' => 'warning_enable'),
			'moderate' => array('int', 'warning_moderate', 'subtext' => $txt['setting_warning_moderate_note']),
			array('int', 'warning_mute', 'subtext' => $txt['setting_warning_mute_note']),
			'rem1' => array('int', 'user_limit', 'subtext' => $txt['setting_user_limit_note']),
			'rem2' => array('int', 'warning_decrement', 'subtext' => $txt['setting_warning_decrement_note']),
			array('select', 'warning_show', 'subtext' => $txt['setting_warning_show_note'], array($txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all'])),
	);

	call_integration_hook('integrate_moderation_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Cannot use moderation if post moderation is not enabled.
	if (!$modSettings['postmod_active'])
		unset($config_vars['moderate']);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Make sure these don't have an effect.
		if (substr($modSettings['warning_settings'], 0, 1) != 1)
		{
			$_POST['warning_watch'] = 0;
			$_POST['warning_moderate'] = 0;
			$_POST['warning_mute'] = 0;
		}
		else
		{
			$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
			$_POST['warning_moderate'] = $modSettings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
			$_POST['warning_mute'] = min($_POST['warning_mute'], 100);
		}

		// Fix the warning setting array!
		$_POST['warning_settings'] = '1,' . min(100, (int) $_POST['user_limit']) . ',' . min(100, (int) $_POST['warning_decrement']);
		$save_vars = $config_vars;
		$save_vars[] = array('text', 'warning_settings');
		unset($save_vars['rem1'], $save_vars['rem2']);

		call_integration_hook('integrate_save_karma_settings', array(&$save_vars));

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=securitysettings;sa=moderation');
	}

	// We actually store lots of these together - for efficiency.
	list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=moderation';
	$context['settings_title'] = $txt['moderation_settings'];

	prepareDBSettingContext($config_vars);
}

/**
 * Let's try keep the spam to a minimum ah Thantos?
 * @param bool $return_config = false
 */
function ModifySpamSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings, $smcFunc;

	// Generate a sample registration image.
	$context['use_graphic_library'] = in_array('gd', get_loaded_extensions());
	$context['verification_image_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

	$config_vars = array(
				array('check', 'reg_verification'),
				array('check', 'search_enable_captcha'),
				// This, my friend, is a cheat :p
				'guest_verify' => array('check', 'guests_require_captcha', 'subtext' => $txt['setting_guests_require_captcha_desc']),
				array('int', 'posts_require_captcha', 'subtext' => $txt['posts_require_captcha_desc'], 'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}'),
				array('check', 'guests_report_require_captcha'),
			'',
			// PM Settings
				'pm1' => array('int', 'max_pm_recipients', 'subtext' => $txt['max_pm_recipients_note']),
				'pm2' => array('int', 'pm_posts_verification', 'subtext' => $txt['pm_posts_verification_note']),
				'pm3' => array('int', 'pm_posts_per_hour', 'subtext' => $txt['pm_posts_per_hour_note']),
			// Visual verification.
			array('title', 'configure_verification_means'),
			array('desc', 'configure_verification_means_desc'),
				'vv' => array('select', 'visual_verification_type', array($txt['setting_image_verification_off'], $txt['setting_image_verification_vsimple'], $txt['setting_image_verification_simple'], $txt['setting_image_verification_medium'], $txt['setting_image_verification_high'], $txt['setting_image_verification_extreme']), 'subtext'=> $txt['setting_visual_verification_type_desc'], 'onchange' => $context['use_graphic_library'] ? 'refreshImages();' : ''),
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			array('title', 'setup_verification_questions'),
			array('desc', 'setup_verification_questions_desc'),
				array('int', 'qa_verification_number', 'subtext' => $txt['setting_qa_verification_number_desc']),
				array('callback', 'question_answer_list'),
	);

	call_integration_hook('integrate_spam_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Load any question and answers!
	$context['question_answers'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_comment, body AS question, recipient_name AS answer
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:ver_test}',
		array(
			'ver_test' => 'ver_test',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['question_answers'][$row['id_comment']] = array(
			'id' => $row['id_comment'],
			'question' => $row['question'],
			'answer' => $row['answer'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Fix PM settings.
		$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

		// Hack in guest requiring verification!
		if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
			$_POST['posts_require_captcha'] = -1;

		$save_vars = $config_vars;
		unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3'], $save_vars['guest_verify']);

		$save_vars[] = array('text', 'pm_spam_settings');

		// Handle verification questions.
		$questionInserts = array();
		$count_questions = 0;
		foreach ($_POST['question'] as $id => $question)
		{
			$question = trim($smcFunc['htmlspecialchars']($question, ENT_COMPAT, $context['character_set']));
			$answer = trim($smcFunc['strtolower']($smcFunc['htmlspecialchars']($_POST['answer'][$id], ENT_COMPAT, $context['character_set'])));

			// Already existed?
			if (isset($context['question_answers'][$id]))
			{
				$count_questions++;
				// Changed?
				if ($context['question_answers'][$id]['question'] != $question || $context['question_answers'][$id]['answer'] != $answer)
				{
					if ($question == '' || $answer == '')
					{
						$smcFunc['db_query']('', '
							DELETE FROM {db_prefix}log_comments
							WHERE comment_type = {string:ver_test}
								AND id_comment = {int:id}',
							array(
								'id' => $id,
								'ver_test' => 'ver_test',
							)
						);
						$count_questions--;
					}
					else
						$request = $smcFunc['db_query']('', '
							UPDATE {db_prefix}log_comments
							SET body = {string:question}, recipient_name = {string:answer}
							WHERE comment_type = {string:ver_test}
								AND id_comment = {int:id}',
							array(
								'id' => $id,
								'ver_test' => 'ver_test',
								'question' => $question,
								'answer' => $answer,
							)
						);
				}
			}
			// It's so shiney and new!
			elseif ($question != '' && $answer != '')
			{
				$questionInserts[] = array(
					'comment_type' => 'ver_test',
					'body' => $question,
					'recipient_name' => $answer,
				);
			}
		}

		// Any questions to insert?
		if (!empty($questionInserts))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}log_comments',
				array('comment_type' => 'string', 'body' => 'string-65535', 'recipient_name' => 'string-80'),
				$questionInserts,
				array('id_comment')
			);
			$count_questions++;
		}

		if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
			$_POST['qa_verification_number'] = $count_questions;

		call_integration_hook('integrate_save_spam_settings', array(&$save_vars));

		// Now save.
		saveDBSettings($save_vars);

		cache_put_data('verificationQuestionIds', null, 300);

		redirectexit('action=admin;area=securitysettings;sa=spam');
	}

	$character_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	$_SESSION['visual_verification_code'] = '';
	for ($i = 0; $i < 6; $i++)
		$_SESSION['visual_verification_code'] .= $character_range[array_rand($character_range)];

	// Some javascript for CAPTCHA.
	$context['settings_post_javascript'] = '';
	if ($context['use_graphic_library'])
		$context['settings_post_javascript'] .= '
		function refreshImages()
		{
			var imageType = document.getElementById(\'visual_verification_type\').value;
			document.getElementById(\'verification_image\').src = \'' . $context['verification_image_href'] . ';type=\' + imageType;
		}';

	// Show the image itself, or text saying we can't.
	if ($context['use_graphic_library'])
		$config_vars['vv']['postinput'] = '<br /><img src="' . $context['verification_image_href'] . ';type=' . (empty($modSettings['visual_verification_type']) ? 0 : $modSettings['visual_verification_type']) . '" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image" /><br />';
	else
		$config_vars['vv']['postinput'] = '<br /><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';

	// Hack for PM spam settings.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Hack for guests requiring verification.
	$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
	$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

	// Some minor javascript for the guest post setting.
	if ($modSettings['posts_require_captcha'])
		$context['settings_post_javascript'] .= '
		document.getElementById(\'guests_require_captcha\').disabled = true;';

	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=spam';
	$context['settings_title'] = $txt['antispam_Settings'];

	prepareDBSettingContext($config_vars);
}

/**
 * You'll never guess what this function does...
 *
 * @param $return_config
 */
function ModifySignatureSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sig_start, $smcFunc, $helptxt, $scripturl;

	$config_vars = array(
			// Are signatures even enabled?
			array('check', 'signature_enable'),
		'',
			// Tweaking settings!
			array('int', 'signature_max_length', 'subtext' => $txt['zero_for_no_limit']),
			array('int', 'signature_max_lines', 'subtext' => $txt['zero_for_no_limit']),
			array('int', 'signature_max_font_size', 'subtext' => $txt['zero_for_no_limit']),
			array('check', 'signature_allow_smileys', 'onclick' => 'document.getElementById(\'signature_max_smileys\').disabled = !this.checked;'),
			array('int', 'signature_max_smileys', 'subtext' => $txt['zero_for_no_limit']),
		'',
			// Image settings.
			array('int', 'signature_max_images', 'subtext' => $txt['signature_max_images_note']),
			array('int', 'signature_max_image_width', 'subtext' => $txt['zero_for_no_limit']),
			array('int', 'signature_max_image_height', 'subtext' => $txt['zero_for_no_limit']),
		'',
			array('bbc', 'signature_bbc'),
	);

	call_integration_hook('integrate_signature_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template.
	$context['page_title'] = $txt['signature_settings'];
	$context['sub_template'] = 'show_settings';

	// Disable the max smileys option if we don't allow smileys at all!
	$context['settings_post_javascript'] = 'document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;';

	// Load all the signature settings.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);
	$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

	// Applying to ALL signatures?!!
	if (isset($_GET['apply']))
	{
		// Security!
		checkSession('get');

		$sig_start = time();
		// This is horrid - but I suppose some people will want the option to do it.
		$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$done = false;

		$request = $smcFunc['db_query']('', '
			SELECT MAX(id_member)
			FROM {db_prefix}members',
			array(
			)
		);
		list ($context['max_member']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		while (!$done)
		{
			$changes = array();

			$request = $smcFunc['db_query']('', '
				SELECT id_member, signature
				FROM {db_prefix}members
				WHERE id_member BETWEEN ' . $_GET['step'] . ' AND ' . $_GET['step'] . ' + 49
					AND id_group != {int:admin_group}
					AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
				array(
					'admin_group' => 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Apply all the rules we can realistically do.
				$sig = strtr($row['signature'], array('<br />' => "\n"));

				// Max characters...
				if (!empty($sig_limits[1]))
					$sig = $smcFunc['substr']($sig, 0, $sig_limits[1]);
				// Max lines...
				if (!empty($sig_limits[2]))
				{
					$count = 0;
					for ($i = 0; $i < strlen($sig); $i++)
					{
						if ($sig[$i] == "\n")
						{
							$count++;
							if ($count >= $sig_limits[2])
								$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), array("\n" => ' '));
						}
					}
				}

				if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $sig, $matches) !== false && isset($matches[2]))
				{
					foreach ($matches[1] as $ind => $size)
					{
						$limit_broke = 0;
						// Attempt to allow all sizes of abuse, so to speak.
						if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
							$limit_broke = $sig_limits[7] . 'px';
						elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
							$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
						elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
							$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
						elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
							$limit_broke = 'large';

						if ($limit_broke)
							$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
					}
				}

				// Stupid images - this is stupidly, stupidly challenging.
				if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6])))
				{
					$replaces = array();
					$img_count = 0;
					// Get all BBC tags...
					preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br />)*([^<">]+?)(?:<br />)*\[/img\]~i', $sig, $matches);
					// ... and all HTML ones.
					preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);
					// And stick the HTML in the BBC.
					if (!empty($matches2))
					{
						foreach ($matches2[0] as $ind => $dummy)
						{
							$matches[0][] = $matches2[0][$ind];
							$matches[1][] = '';
							$matches[2][] = '';
							$matches[3][] = '';
							$matches[4][] = '';
							$matches[5][] = '';
							$matches[6][] = '';
							$matches[7][] = $matches2[1][$ind];
						}
					}
					// Try to find all the images!
					if (!empty($matches))
					{
						$image_count_holder = array();
						foreach ($matches[0] as $key => $image)
						{
							$width = -1; $height = -1;
							$img_count++;
							// Too many images?
							if (!empty($sig_limits[3]) && $img_count > $sig_limits[3])
							{
								// If we've already had this before we only want to remove the excess.
								if (isset($image_count_holder[$image]))
								{
									$img_offset = -1;
									$rep_img_count = 0;
									while ($img_offset !== false)
									{
										$img_offset = strpos($sig, $image, $img_offset + 1);
										$rep_img_count++;
										if ($rep_img_count > $image_count_holder[$image])
										{
											// Only replace the excess.
											$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));
											// Stop looping.
											$img_offset = false;
										}
									}
								}
								else
									$replaces[$image] = '';

								continue;
							}

							// Does it have predefined restraints? Width first.
							if ($matches[6][$key])
								$matches[2][$key] = $matches[6][$key];
							if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
							{
								$width = $sig_limits[5];
								$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
							}
							elseif ($matches[2][$key])
								$width = $matches[2][$key];
							// ... and height.
							if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width != -1)
									$width = $width * ($height / $matches[4][$key]);
							}
							elseif ($matches[4][$key])
								$height = $matches[4][$key];

							// If the dimensions are still not fixed - we need to check the actual image.
							if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
							{
								$sizes = url_image_size($matches[7][$key]);
								if (is_array($sizes))
								{
									// Too wide?
									if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
									{
										$width = $sig_limits[5];
										$sizes[1] = $sizes[1] * ($width / $sizes[0]);
									}
									// Too high?
									if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
									{
										$height = $sig_limits[6];
										if ($width == -1)
											$width = $sizes[0];
										$width = $width * ($height / $sizes[1]);
									}
									elseif ($width != -1)
										$height = $sizes[1];
								}
							}

							// Did we come up with some changes? If so remake the string.
							if ($width != -1 || $height != -1)
							{
								$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
							}

							// Record that we got one.
							$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
						}
						if (!empty($replaces))
							$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
					}
				}
				// Try to fix disabled tags.
				if (!empty($disabledTags))
				{
					$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
					$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
				}

				$sig = strtr($sig, array("\n" => '<br />'));
				call_integration_hook('integrate_apply_signature_settings', array(&$sig, $sig_limits, $disabledTags));
				if ($sig != $row['signature'])
					$changes[$row['id_member']] = $sig;
			}
			if ($smcFunc['db_num_rows']($request) == 0)
				$done = true;
			$smcFunc['db_free_result']($request);

			// Do we need to delete what we have?
			if (!empty($changes))
			{
				foreach ($changes as $id => $sig)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}members
						SET signature = {string:signature}
						WHERE id_member = {int:id_member}',
						array(
							'id_member' => $id,
							'signature' => $sig,
						)
					);
			}

			$_GET['step'] += 50;
			if (!$done)
				pauseSignatureApplySettings();
		}
	}

	$context['signature_settings'] = array(
		'enable' => isset($sig_limits[0]) ? $sig_limits[0] : 0,
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
		'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
	);

	// Temporarily make each setting a modSetting!
	foreach ($context['signature_settings'] as $key => $value)
		$modSettings['signature_' . $key] = $value;

	// Make sure we check the right tags!
	$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Clean up the tag stuff!
		$bbcTags = array();
		foreach (parse_bbc(false) as $tag)
			$bbcTags[] = $tag['tag'];

		if (!isset($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = array();
		elseif (!is_array($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = array($_POST['signature_bbc_enabledTags']);

		$sig_limits = array();
		foreach ($context['signature_settings'] as $key => $value)
		{
			if ($key == 'allow_smileys')
				continue;
			elseif ($key == 'max_smileys' && empty($_POST['signature_allow_smileys']))
				$sig_limits[] = -1;
			else
				$sig_limits[] = !empty($_POST['signature_' . $key]) ? max(1, (int) $_POST['signature_' . $key]) : 0;
		}

		call_integration_hook('integrate_save_signature_settings', array(&$sig_limits, &$bbcTags));

		$_POST['signature_settings'] = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $_POST['signature_bbc_enabledTags']));

		// Even though we have practically no settings let's keep the convention going!
		$save_vars = array();
		$save_vars[] = array('text', 'signature_settings');

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=featuresettings;sa=sig');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=sig';
	$context['settings_title'] = $txt['signature_settings'];

	$context['settings_message'] = '<p class="centertext">' . sprintf($txt['signature_settings_warning'], $context['session_id'], $context['session_var']) . '</p>';

	prepareDBSettingContext($config_vars);
}

/**
 * Just pause the signature applying thing.
 */
function pauseSignatureApplySettings()
{
	global $context, $txt, $sig_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we exhausted all the time we allowed?
	if (time() - array_sum(explode(' ', $sig_start)) < 3)
		return;

	$context['continue_get_data'] = '?action=admin;area=featuresettings;sa=sig;apply;step=' . $_GET['step'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($_GET['step'] / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

/**
 * Show all the custom profile fields available to the user.
 */
function ShowCustomProfiles()
{
	global $txt, $scripturl, $context, $settings, $sc, $smcFunc;
	global $modSettings, $sourcedir;

	$context['page_title'] = $txt['custom_profile_title'];
	$context['sub_template'] = 'show_custom_profile';

	// What about standard fields they can tweak?
	$standard_fields = array('icq', 'msn', 'aim', 'yim', 'location', 'gender', 'website', 'posts', 'warning_status');
	// What fields can't you put on the registration page?
	$context['fields_no_registration'] = array('posts', 'warning_status');

	// Are we saving any standard field changes?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-scp');

		// Do the active ones first.
		$disable_fields = array_flip($standard_fields);
		if (!empty($_POST['active']))
		{
			foreach ($_POST['active'] as $value)
				if (isset($disable_fields[$value]))
					unset($disable_fields[$value]);
		}
		// What we have left!
		$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

		// Things we want to show on registration?
		$reg_fields = array();
		if (!empty($_POST['reg']))
		{
			foreach ($_POST['reg'] as $value)
				if (in_array($value, $standard_fields) && !isset($disable_fields[$value]))
					$reg_fields[] = $value;
		}
		// What we have left!
		$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

		if (!empty($changes))
			updateSettings($changes);
	}

	createToken('admin-scp');

	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'standard_profile_fields',
		'title' => $txt['standard_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'get_items' => array(
			'function' => 'list_getProfileFields',
			'params' => array(
				true,
			),
		),
		'columns' => array(
			'field' => array(
				'header' => array(
					'value' => $txt['standard_profile_field'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'db' => 'label',
					'style' => 'width: 60%;',
				),
			),
			'active' => array(
				'header' => array(
					'value' => $txt['custom_edit_active'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						$isChecked = $rowData[\'disabled\'] ? \'\' : \' checked="checked"\';
						$onClickHandler = $rowData[\'can_show_register\'] ? sprintf(\'onclick="document.getElementById(\\\'reg_%1$s\\\').disabled = !this.checked;"\', $rowData[\'id\']) : \'\';
						return sprintf(\'<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" class="input_check"%2$s%3$s />\', $rowData[\'id\'], $isChecked, $onClickHandler);
					'),
					'style' => 'width: 20%; text-align: center;',
				),
			),
			'show_on_registration' => array(
				'header' => array(
					'value' => $txt['custom_edit_registration'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						$isChecked = $rowData[\'on_register\'] && !$rowData[\'disabled\'] ? \' checked="checked"\' : \'\';
						$isDisabled = $rowData[\'can_show_register\'] ? \'\' : \' disabled="disabled"\';
						return sprintf(\'<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" class="input_check"%2$s%3$s />\', $rowData[\'id\'], $isChecked, $isDisabled);
					'),
					'style' => 'width: 20%; text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
			'name' => 'standardProfileFields',
			'token' => 'admin-scp',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);
	createList($listOptions);

	$listOptions = array(
		'id' => 'custom_profile_fields',
		'title' => $txt['custom_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'default_sort_col' => 'field_name',
		'no_items_label' => $txt['custom_profile_none'],
		'items_per_page' => 25,
		'get_items' => array(
			'function' => 'list_getProfileFields',
			'params' => array(
				false,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileFieldSize',
		),
		'columns' => array(
			'field_name' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldname'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl;

						return sprintf(\'<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>\', $scripturl, $rowData[\'id_field\'], $rowData[\'field_name\'], $rowData[\'field_desc\']);
					'),
					'style' => 'width: 62%;',
				),
				'sort' => array(
					'default' => 'field_name',
					'reverse' => 'field_name DESC',
				),
			),
			'field_type' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldtype'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						$textKey = sprintf(\'custom_profile_type_%1$s\', $rowData[\'field_type\']);
						return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
					'),
					'style' => 'width: 15%;',
				),
				'sort' => array(
					'default' => 'field_type',
					'reverse' => 'field_type DESC',
				),
			),
			'active' => array(
				'header' => array(
					'value' => $txt['custom_profile_active'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $rowData[\'active\'] ? $txt[\'yes\'] : $txt[\'no\'];
					'),
					'style' => 'width: 8%; text-align: center;',
				),
				'sort' => array(
					'default' => 'active DESC',
					'reverse' => 'active',
				),
			),
			'placement' => array(
				'header' => array(
					'value' => $txt['custom_profile_placement'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $txt[\'custom_profile_placement_\' . (empty($rowData[\'placement\']) ? \'standard\' : ($rowData[\'placement\'] == 1 ? \'withicons\' : \'abovesignature\'))];
					'),
					'style' => 'width: 8%; text-align: center;',
				),
				'sort' => array(
					'default' => 'placement DESC',
					'reverse' => 'placement',
				),
			),
			'show_on_registration' => array(
				'header' => array(
					'value' => $txt['modify'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_field' => false,
						),
					),
					'style' => 'width: 15%; text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=featuresettings;sa=profileedit',
			'name' => 'customProfileFields',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);
	createList($listOptions);
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 * @param $standardFields
 */
function list_getProfileFields($start, $items_per_page, $sort, $standardFields)
{
	global $txt, $modSettings, $smcFunc;

	$list = array();

	if ($standardFields)
	{
		$standard_fields = array('icq', 'msn', 'aim', 'yim', 'location', 'gender', 'website', 'posts', 'warning_status');
		$fields_no_registration = array('posts', 'warning_status');
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
		$registration_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();

		foreach ($standard_fields as $field)
			$list[] = array(
				'id' => $field,
				'label' => isset($txt['standard_profile_field_' . $field]) ? $txt['standard_profile_field_' . $field] : (isset($txt[$field]) ? $txt[$field] : $field),
				'disabled' => in_array($field, $disabled_fields),
				'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
				'can_show_register' => !in_array($field, $fields_no_registration),
			);
	}
	else
	{
		// Load all the fields.
		$request = $smcFunc['db_query']('', '
			SELECT id_field, col_name, field_name, field_desc, field_type, active, placement
			FROM {db_prefix}custom_fields
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items_per_page}',
			array(
				'sort' => $sort,
				'start' => $start,
				'items_per_page' => $items_per_page,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$list[] = $row;
		$smcFunc['db_free_result']($request);
	}

	return $list;
}

/**
 * Callback for createList().
 */
function list_getProfileFieldSize()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}custom_fields',
		array(
		)
	);

	list ($numProfileFields) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $numProfileFields;
}

/**
 * Edit some profile fields?
 */
function EditCustomProfiles()
{
	global $txt, $scripturl, $context, $settings, $sc, $smcFunc;

	// Sort out the context!
	$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
	$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
	$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
	$context['sub_template'] = 'edit_profile_field';

	// Load the profile language for section names.
	loadLanguage('Profile');

	if ($context['fid'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				id_field, col_name, field_name, field_desc, field_type, field_length, field_options,
				show_reg, show_display, show_profile, private, active, default_value, can_search,
				bbc, mask, enclose, placement
			FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			array(
				'current_field' => $context['fid'],
			)
		);
		$context['field'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['field_type'] == 'textarea')
				@list ($rows, $cols) = @explode(',', $row['default_value']);
			else
			{
				$rows = 3;
				$cols = 30;
			}

			$context['field'] = array(
				'name' => $row['field_name'],
				'desc' => $row['field_desc'],
				'colname' => $row['col_name'],
				'profile_area' => $row['show_profile'],
				'reg' => $row['show_reg'],
				'display' => $row['show_display'],
				'type' => $row['field_type'],
				'max_length' => $row['field_length'],
				'rows' => $rows,
				'cols' => $cols,
				'bbc' => $row['bbc'] ? true : false,
				'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
				'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
				'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : array('', '', ''),
				'active' => $row['active'],
				'private' => $row['private'],
				'can_search' => $row['can_search'],
				'mask' => $row['mask'],
				'regex' => substr($row['mask'], 0, 5) == 'regex' ? substr($row['mask'], 5) : '',
				'enclose' => $row['enclose'],
				'placement' => $row['placement'],
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Setup the default values as needed.
	if (empty($context['field']))
		$context['field'] = array(
			'name' => '',
			'colname' => '???',
			'desc' => '',
			'profile_area' => 'forumprofile',
			'reg' => false,
			'display' => false,
			'type' => 'text',
			'max_length' => 255,
			'rows' => 4,
			'cols' => 30,
			'bbc' => false,
			'default_check' => false,
			'default_select' => '',
			'options' => array('', '', ''),
			'active' => true,
			'private' => false,
			'can_search' => false,
			'mask' => 'nohtml',
			'regex' => '',
			'enclose' => '',
			'placement' => 0,
		);

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-ecp');

		// Everyone needs a name - even the (bracket) unknown...
		if (trim($_POST['field_name']) == '')
			redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=need_name');

		// Regex you say?  Do a very basic test to see if the pattern is valid
		if (!empty($_POST['regex']) && @preg_match($_POST['regex'], 'dummy') === false)
			redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=regex_error');

		$_POST['field_name'] = $smcFunc['htmlspecialchars']($_POST['field_name']);
		$_POST['field_desc'] = $smcFunc['htmlspecialchars']($_POST['field_desc']);

		// Checkboxes...
		$show_reg = isset($_POST['reg']) ? (int) $_POST['reg'] : 0;
		$show_display = isset($_POST['display']) ? 1 : 0;
		$bbc = isset($_POST['bbc']) ? 1 : 0;
		$show_profile = $_POST['profile_area'];
		$active = isset($_POST['active']) ? 1 : 0;
		$private = isset($_POST['private']) ? (int) $_POST['private'] : 0;
		$can_search = isset($_POST['can_search']) ? 1 : 0;

		// Some masking stuff...
		$mask = isset($_POST['mask']) ? $_POST['mask'] : '';
		if ($mask == 'regex' && isset($_POST['regex']))
			$mask .= $_POST['regex'];

		$field_length = isset($_POST['max_length']) ? (int) $_POST['max_length'] : 255;
		$enclose = isset($_POST['enclose']) ? $_POST['enclose'] : '';
		$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : 0;

		// Select options?
		$field_options = '';
		$newOptions = array();
		$default = isset($_POST['default_check']) && $_POST['field_type'] == 'check' ? 1 : '';
		if (!empty($_POST['select_option']) && ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio'))
		{
			foreach ($_POST['select_option'] as $k => $v)
			{
				// Clean, clean, clean...
				$v = $smcFunc['htmlspecialchars']($v);
				$v = strtr($v, array(',' => ''));

				// Nada, zip, etc...
				if (trim($v) == '')
					continue;

				// Otherwise, save it boy.
				$field_options .= $v . ',';
				// This is just for working out what happened with old options...
				$newOptions[$k] = $v;

				// Is it default?
				if (isset($_POST['default_select']) && $_POST['default_select'] == $k)
					$default = $v;
			}
			$field_options = substr($field_options, 0, -1);
		}

		// Text area has default has dimensions
		if ($_POST['field_type'] == 'textarea')
			$default = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];

		// Come up with the unique name?
		if (empty($context['fid']))
		{
			$colname = $smcFunc['substr'](strtr($_POST['field_name'], array(' ' => '')), 0, 6);
			preg_match('~([\w\d_-]+)~', $colname, $matches);

			// If there is nothing to the name, then let's start out own - for foreign languages etc.
			if (isset($matches[1]))
				$colname = $initial_colname = 'cust_' . strtolower($matches[1]);
			else
				$colname = $initial_colname = 'cust_' . mt_rand(1, 999);

			// Make sure this is unique.
			// @todo This may not be the most efficient way to do this.
			$unique = false;
			for ($i = 0; !$unique && $i < 9; $i ++)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_field
					FROM {db_prefix}custom_fields
					WHERE col_name = {string:current_column}',
					array(
						'current_column' => $colname,
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
					$unique = true;
				else
					$colname = $initial_colname . $i;
				$smcFunc['db_free_result']($request);
			}

			// Still not a unique colum name? Leave it up to the user, then.
			if (!$unique)
				fatal_lang_error('custom_option_not_unique');
		}
		// Work out what to do with the user data otherwise...
		else
		{
			// Anything going to check or select is pointless keeping - as is anything coming from check!
			if (($_POST['field_type'] == 'check' && $context['field']['type'] != 'check')
				|| (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && $context['field']['type'] != 'select' && $context['field']['type'] != 'radio')
				|| ($context['field']['type'] == 'check' && $_POST['field_type'] != 'check'))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:current_column}
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'current_column' => $context['field']['colname'],
					)
				);
			}
			// Otherwise - if the select is edited may need to adjust!
			elseif ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio')
			{
				$optionChanges = array();
				$takenKeys = array();
				// Work out what's changed!
				foreach ($context['field']['options'] as $k => $option)
				{
					if (trim($option) == '')
						continue;

					// Still exists?
					if (in_array($option, $newOptions))
					{
						$takenKeys[] = $k;
						continue;
					}
				}

				// Finally - have we renamed it - or is it really gone?
				foreach ($optionChanges as $k => $option)
				{
					// Just been renamed?
					if (!in_array($k, $takenKeys) && !empty($newOptions[$k]))
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}themes
							SET value = {string:new_value}
							WHERE variable = {string:current_column}
								AND value = {string:old_value}
								AND id_member > {int:no_member}',
							array(
								'no_member' => 0,
								'new_value' => $newOptions[$k],
								'current_column' => $context['field']['colname'],
								'old_value' => $option,
							)
						);
				}
			}
			// @todo Maybe we should adjust based on new text length limits?
		}

		// Do the insertion/updates.
		if ($context['fid'])
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}custom_fields
				SET
					field_name = {string:field_name}, field_desc = {string:field_desc},
					field_type = {string:field_type}, field_length = {int:field_length},
					field_options = {string:field_options}, show_reg = {int:show_reg},
					show_display = {int:show_display}, show_profile = {string:show_profile},
					private = {int:private}, active = {int:active}, default_value = {string:default_value},
					can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
					enclose = {string:enclose}, placement = {int:placement}
				WHERE id_field = {int:current_field}',
				array(
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'private' => $private,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $_POST['field_name'],
					'field_desc' => $_POST['field_desc'],
					'field_type' => $_POST['field_type'],
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
				)
			);

			// Just clean up any old selects - these are a pain!
			if (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && !empty($newOptions))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:current_column}
						AND value NOT IN ({array_string:new_option_values})
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'new_option_values' => $newOptions,
						'current_column' => $context['field']['colname'],
					)
				);
		}
		else
		{
			$smcFunc['db_insert']('',
				'{db_prefix}custom_fields',
				array(
					'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
					'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string',
					'show_reg' => 'int', 'show_display' => 'int', 'show_profile' => 'string',
					'private' => 'int', 'active' => 'int', 'default_value' => 'string', 'can_search' => 'int',
					'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int',
				),
				array(
					$colname, $_POST['field_name'], $_POST['field_desc'],
					$_POST['field_type'], $field_length, $field_options,
					$show_reg, $show_display, $show_profile,
					$private, $active, $default, $can_search,
					$bbc, $mask, $enclose, $placement,
				),
				array('id_field')
			);
		}

		// As there's currently no option to priorize certain fields over others, let's order them alphabetically.
		$smcFunc['db_query']('alter_table_boards', '
			ALTER TABLE {db_prefix}custom_fields
			ORDER BY field_name',
			array(
				'db_error_skip' => true,
			)
		);
	}
	// Deleting?
	elseif (isset($_POST['delete']) && $context['field']['colname'])
	{
		checkSession();
		validateToken('admin-ecp');

		// Delete the user data first.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}themes
			WHERE variable = {string:current_column}
				AND id_member > {int:no_member}',
			array(
				'no_member' => 0,
				'current_column' => $context['field']['colname'],
			)
		);
		// Finally - the field itself is gone!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			array(
				'current_field' => $context['fid'],
			)
		);
	}

	// Rebuild display cache etc.
	if (isset($_POST['delete']) || isset($_POST['save']))
	{
		checkSession();

		$request = $smcFunc['db_query']('', '
			SELECT col_name, field_name, field_type, bbc, enclose, placement
			FROM {db_prefix}custom_fields
			WHERE show_display = {int:is_displayed}
				AND active = {int:active}
				AND private != {int:not_owner_only}
				AND private != {int:not_admin_only}',
			array(
				'is_displayed' => 1,
				'active' => 1,
				'not_owner_only' => 2,
				'not_admin_only' => 3,
			)
		);

		$fields = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$fields[] = array(
				'colname' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
			);
		}
		$smcFunc['db_free_result']($request);

		updateSettings(array('displayFields' => serialize($fields)));
		redirectexit('action=admin;area=featuresettings;sa=profile');
	}

	createToken('admin-ecp');
}

/**
 * Allow to edit the settings on the pruning screen.
 * @param $return_config
 */
function ModifyPruningSettings($return_config = false)
{
	global $txt, $scripturl, $sourcedir, $context, $settings, $sc, $modSettings;

	// Make sure we understand what's going on.
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['pruning_title'];

	$config_vars = array(
			// Even do the pruning?
			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => array('check', 'pruningOptions'),
		'',
			// Various logs that could be pruned.
			array('int', 'pruneErrorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Error log.
			array('int', 'pruneModLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Moderation log.
			array('int', 'pruneBanLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Ban hit log.
			array('int', 'pruneReportLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Report to moderator log.
			array('int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Log of the scheduled tasks and how long they ran.
			array('int', 'pruneSpiderHitLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Log of the scheduled tasks and how long they ran.
			// If you add any additional logs make sure to add them after this point.  Additionally, make sure you add them to the weekly scheduled task.
			// Mod Developers: Do NOT use the pruningOptions master variable for this as SMF Core may overwrite your setting in the future!
	);

	call_integration_hook('integrate_prune_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// We'll need this in a bit.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$savevar = array(
			array('text', 'pruningOptions')
		);

		if (!empty($_POST['pruningOptions']))
		{
			$vals = array();
			foreach ($config_vars as $index => $dummy)
			{
				if (!is_array($dummy) || $index == 'pruningOptions')
					continue;

				$vals[] = empty($_POST[$dummy[1]]) || $_POST[$dummy[1]] < 0 ? 0 : (int) $_POST[$dummy[1]];
			}
			$_POST['pruningOptions'] = implode(',', $vals);
		}
		else
			$_POST['pruningOptions'] = '';

		saveDBSettings($savevar);
		redirectexit('action=admin;area=logs;sa=pruning');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=logs;save;sa=pruning';
	$context['settings_title'] = $txt['pruning_title'];
	$context['sub_template'] = 'show_settings';

	// Get the actual values
	if (!empty($modSettings['pruningOptions']))
		@list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = explode(',', $modSettings['pruningOptions']);
	else
		$modSettings['pruneErrorLog'] = $modSettings['pruneModLog'] = $modSettings['pruneBanLog'] = $modSettings['pruneReportLog'] = $modSettings['pruneScheduledTaskLog'] = $modSettings['pruneSpiderHitLog'] = 0;

	prepareDBSettingContext($config_vars);
}

/**
 * If you have a general mod setting to add stick it here.
 *
 * @param $return_config
 */
function ModifyGeneralModSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $sc, $modSettings;

	$config_vars = array(
		// Mod authors, add any settings UNDER this line. Include a comma at the end of the line and don't remove this statement!!
	);

	// Make it even easier to add new settings.
	call_integration_hook('integrate_general_mod_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=general';
	$context['settings_title'] = $txt['mods_cat_modifications_misc'];

	// No removing this line you, dirty unwashed mod authors. :p
	if (empty($config_vars))
	{
		$context['settings_save_dont_show'] = true;
		$context['settings_message'] = '<div class="centertext">' . $txt['modification_no_misc_settings'] . '</div>';

		return prepareDBSettingContext($config_vars);
	}

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$save_vars = $config_vars;

		call_integration_hook('integrate_save_general_mod_settings', array(&$save_vars));

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: FOOT TAPPING SUCKS!
		saveDBSettings($save_vars);

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: I LOVE TEA!
		redirectexit('action=admin;area=modsettings;sa=general');
	}

	// This line is to help mod authors do a search/add after if you want to add something here. Keyword: RED INK IS FOR TEACHERS AND THOSE WHO LIKE PAIN!
	prepareDBSettingContext($config_vars);
}

function list_integration_hooks()
{
	global $sourcedir, $scripturl, $context, $txt, $modSettings, $settings;

	$context['filter'] = '';
	$presentHooks = get_integration_hooks();
	if (isset($_GET['filter']) && in_array($_GET['filter'], array_keys($presentHooks)))
		$context['filter'] = ';filter=' . $_GET['filter'];

	if (!empty($_REQUEST['do']) && isset($_REQUEST['hook']) && isset($_REQUEST['function']))
	{
		checkSession('request');
		validateToken('admin-hook', 'request');

		if ($_REQUEST['do'] == 'remove')
			remove_integration_function($_REQUEST['hook'], $_REQUEST['function']);
		elseif ($_REQUEST['do'] == 'disable')
		{
			remove_integration_function($_REQUEST['hook'], $_REQUEST['function']);
			// It's a hack I know...but I'm way too lazy!!!
			add_integration_function($_REQUEST['hook'], $_REQUEST['function'] . ']');
		}
		elseif ($_REQUEST['do'] == 'enable')
		{
			remove_integration_function($_REQUEST['hook'], $_REQUEST['function'] . ']');
			// It's a hack I know...but I'm way too lazy!!!
			add_integration_function($_REQUEST['hook'], $_REQUEST['function']);
		}

		redirectexit('action=admin;area=modsettings;sa=hooks' . $context['filter']);
	}

	createToken('admin-hook', 'request');

	$list_options = array(
		'id' => 'list_integration_hooks',
		'title' => $txt['hooks_title_list'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=modsettings;sa=hooks' . $context['filter'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'hook_name',
		'get_items' => array(
			'function' => 'get_integration_hooks_data',
		),
		'get_count' => array(
			'function' => 'get_integration_hooks_count',
		),
		'no_items_label' => $txt['hooks_no_hooks'],
		'columns' => array(
			'hook_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_name'],
				),
				'data' => array(
					'db' => 'hook_name',
				),
				'sort' =>  array(
					'default' => 'hook_name',
					'reverse' => 'hook_name DESC',
				),
			),
			'function_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_function_name'],
				),
				'data' => array(
					'db' => 'function_name',
				),
				'sort' =>  array(
					'default' => 'function_name',
					'reverse' => 'function_name DESC',
				),
			),
			'file_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_file_name'],
				),
				'data' => array(
					'db' => 'file_name',
				),
				'sort' =>  array(
					'default' => 'file_name',
					'reverse' => 'file_name DESC',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_exists'],
					'style' => 'width:3%',
				),
				'data' => array(
					'function' => create_function('$data', '
						global $txt, $settings, $scripturl, $context;

						$change_status = array(\'before\' => \'\', \'after\' => \'\');
						if ($data[\'can_be_disabled\'] && $data[\'status\'] != \'deny\')
						{
							$change_status[\'before\'] = \'<a href="\' . $scripturl . \'?action=admin;area=modsettings;sa=hooks;do=\' . ($data[\'enabled\'] ? \'disable\' : \'enable\') . \';hook=\' . $data[\'hook_name\'] . \';function=\' . $data[\'function_name\'] . $context[\'filter\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">\';
							$change_status[\'after\'] = \'</a>\';
						}
						return $change_status[\'before\'] . \'<img src="\' . $settings[\'images_url\'] . \'/admin/post_moderation_\' . $data[\'status\'] . \'.png" alt="\' . $data[\'img_text\'] . \'" title="\' . $data[\'img_text\'] . \'" />\' . $change_status[\'after\'];
					'),
					'class' => 'centertext',
				),
				'sort' =>  array(
					'default' => 'status',
					'reverse' => 'status DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => $txt['hooks_button_remove'],
					'style' => 'width:3%',
				),
				'data' => array(
					'function' => create_function('$data', '
						global $txt, $settings, $scripturl, $context;

						if (!$data[\'hook_exists\'])
							return \'
							<a href="\' . $scripturl . \'?action=admin;area=modsettings;sa=hooks;do=remove;hook=\' . $data[\'hook_name\'] . \';function=\' . $data[\'function_name\'] . $context[\'filter\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">
								<img src="\' . $settings[\'images_url\'] . \'/icons/quick_remove.png" alt="\' . $txt[\'hooks_button_remove\'] . \'" title="\' . $txt[\'hooks_button_remove\'] . \'" />
							</a>\';
					'),
					'class' => 'centertext',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=modsettings;sa=hooks' . $context['filter'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'name' => 'list_integration_hooks',
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['hooks_disable_instructions'] . '<br />
					' . $txt['hooks_disable_legend'] . ':
									<ul style="list-style: none;">
					<li><img src="' . $settings['images_url'] . '/admin/post_moderation_allow.png" alt="' . $txt['hooks_active'] . '" title="' . $txt['hooks_active'] . '" /> ' . $txt['hooks_disable_legend_exists'] . '</li>
					<li><img src="' . $settings['images_url'] . '/admin/post_moderation_moderate.png" alt="' . $txt['hooks_disabled'] . '" title="' . $txt['hooks_disabled'] . '" /> ' . $txt['hooks_disable_legend_disabled'] . '</li>
					<li><img src="' . $settings['images_url'] . '/admin/post_moderation_deny.png" alt="' . $txt['hooks_missing'] . '" title="' . $txt['hooks_missing'] . '" /> ' . $txt['hooks_disable_legend_missing'] . '</li>
				</ul>'
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($list_options);

	$context['page_title'] = $txt['hooks_title_list'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'list_integration_hooks';
}

function get_files_recursive($dir_path)
{
	$files = array();

	if ($dh = opendir($dir_path))
	{
		while (($file = readdir($dh)) !== false)
		{
			if ($file != '.' && $file != '..')
			{
				if (is_dir($dir_path . '/' . $file))
					$files = array_merge($files, get_files_recursive($dir_path . '/' . $file));
				else
					$files[] = array('dir' => $dir_path, 'name' => $file);
			}
		}
	}
	closedir($dh);

	return $files;
}

function get_integration_hooks_data($start, $per_page, $sort)
{
	global $boarddir, $sourcedir, $settings, $txt, $context, $scripturl;

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = array();

	$files = get_files_recursive($sourcedir);
	if (!empty($files))
	{
		foreach ($files as $file)
		{
			if (is_file($file['dir'] . '/' . $file['name']) && substr($file['name'], -4) === '.php')
			{
				$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
				$fc = fread($fp, filesize($file['dir'] . '/' . $file['name']));
				fclose($fp);

				foreach ($temp_hooks as $hook => $functions)
				{
					foreach ($functions as $function_o)
					{
						$function = str_replace(']', '', $function_o);
						if (substr($hook, -8) === '_include')
						{
							$hook_status[$hook][$function]['exists'] = file_exists(strtr(trim($function), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'])));
							// I need to know if there is at least one function called in this file.
							$temp_data['include'][basename($function)] = array('hook' => $hook, 'function' => $function);
							unset($temp_hooks[$hook][$function_o]);
						}
						// @TODO replace with a preg_match? (the difference is the space before the open parentheses
						elseif (strpos($fc, 'function ' . trim($function) . '(') !== false || strpos($fc, 'function ' . trim($function) . ' (') !== false)
						{
							$hook_status[$hook][$function]['exists'] = true;
							$hook_status[$hook][$function]['in_file'] = $file['name'];
							// I want to remember all the functions called within this file (to check later if they are enabled or disabled and decide if the integrare_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook', SORT_ASC),
		'hook_name DESC' => array('hook', SORT_DESC),
		'function_name' => array('function', SORT_ASC),
		'function_name DESC' => array('function', SORT_DESC),
		'file_name' => array('file_name', SORT_ASC),
		'file_name DESC' => array('file_name', SORT_DESC),
		'status' => array('status', SORT_ASC),
		'status DESC' => array('status', SORT_DESC),
	);

	$sort_options = $sort_types[$sort];
	$sort = array();
	$context['hooks_filters'] = '';
	$hooks_filters = array();

	foreach ($hooks as $hook => $functions)
	{
		$hooks_filters[] = '<option onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks;filter=' . $hook . '\';">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$enabled = strstr($function, ']') === false;
			$function = str_replace(']', '', $function);

			// This is a not an include and the function is included in a certain file (if not it doesn't exists so don't care)
			if (substr($hook, -8) !== '_include' && isset($hook_status[$hook][$function]['in_file']))
			{
				$current_hook = isset($temp_data['include'][$hook_status[$hook][$function]['in_file']]) ? $temp_data['include'][$hook_status[$hook][$function]['in_file']] : '';
				$enabled = false;

				// Checking all the functions within this particular file
				// if any of them is enable then the file *must* be included and the integrate_*_include hook cannot be disabled
				foreach ($temp_data['function'][$hook_status[$hook][$function]['in_file']] as $func)
					$enabled = $enabled || strstr($func, ']') !== false;

				if (!$enabled &&  !empty($current_hook))
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
			}
		}
	}

	if (!empty($hooks_filters))
		$context['hooks_filters'] = '<select style="margin-left:15px;">' . '<option>---</option><option onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks\';">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '</select>';

	$temp_data = array();
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
		{
			foreach ($functions as $function)
			{
				$enabled = strstr($function, ']') === false;
				$function = str_replace(']', '', $function);
				$hook_exists = !empty($hook_status[$hook][$function]['exists']);
				$file_name = isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ((substr($hook, -8) === '_include') ? 'zzzzzzzzz' : 'zzzzzzzza');
				$status = $hook_exists ? ($enabled ? 'a' : 'b') : 'c';
				$sort[] = $$sort_options[0];
				$temp_data[] = array(
					'id' => 'hookid_' . $id++,
					'hook_name' => $hook,
					'function_name' => $function,
					'file_name' => (isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => !isset($hook_status[$hook][$function]['enabled']),
				);
			}
		}
	}

	array_multisort($sort, $sort_options[1], $temp_data);

	$counter = 0;
	$start++;

	foreach ($temp_data as $data)
	{
		if (++$counter < $start)
			continue;
		elseif ($counter == $start + $per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

function get_integration_hooks_count()
{
	global $context;

	$hooks = get_integration_hooks();
	$hooks_count = 0;

	$context['filter'] = false;
	if (isset($_GET['filter']))
		$context['filter'] = $_GET['filter'];

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks;

	if (!isset($integration_hooks))
	{
		$integration_hooks = array();
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && substr($key, 0, 10) === 'integrate_')
				$integration_hooks[$key] = explode(',', $value);
		}
	}

	return $integration_hooks;
}

?>