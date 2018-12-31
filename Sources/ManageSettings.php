<?php

/**
 * This file is here to make it easier for installed mods to have
 * settings and options.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This function makes sure the requested subaction does exists, if it doesn't, it sets a default action or.
 *
 * @param array $subActions An array containing all possible subactions.
 * @param string $defaultAction The default action to be called if no valid subaction was found.
 */
function loadGeneralSettingParameters($subActions = array(), $defaultAction = null)
{
	global $context, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	$context['sub_template'] = 'show_settings';

	// If no fallback was specified, use the first subaction.
	$defaultAction = $defaultAction ?: key($subActions);

	// I want...
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : $defaultAction;
	$context['sub_action'] = $_REQUEST['sa'];
}

/**
 * This function passes control through to the relevant tab.
 */
function ModifyFeatureSettings()
{
	global $context, $txt, $settings;

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['modSettings_title'];

	$subActions = array(
		'basic' => 'ModifyBasicSettings',
		'bbc' => 'ModifyBBCSettings',
		'layout' => 'ModifyLayoutSettings',
		'sig' => 'ModifySignatureSettings',
		'profile' => 'ShowCustomProfiles',
		'profileedit' => 'EditCustomProfiles',
		'likes' => 'ModifyLikesSettings',
		'mentions' => 'ModifyMentionsSettings',
		'alerts' => 'ModifyAlertsSettings',
	);

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['modSettings_title'],
		'help' => 'featuresettings',
		'description' => sprintf($txt['modSettings_desc'], $settings['theme_id'], $context['session_id'], $context['session_var']),
		'tabs' => array(
			'basic' => array(
			),
			'bbc' => array(
				'description' => $txt['manageposts_bbc_settings_description'],
			),
			'layout' => array(
			),
			'sig' => array(
				'description' => $txt['signature_settings_desc'],
			),
			'profile' => array(
				'description' => $txt['custom_profile_desc'],
			),
			'likes' => array(
			),
			'mentions' => array(
			),
			'alerts' => array(
				'description' => $txt['notifications_desc'],
			),
		),
	);

	call_integration_hook('integrate_modify_features', array(&$subActions));

	loadGeneralSettingParameters($subActions, 'basic');

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * This my friend, is for all the mod authors out there.
 */
function ModifyModSettings()
{
	global $context, $txt;

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['admin_modifications'];

	$subActions = array(
		'general' => 'ModifyGeneralModSettings',
		// Mod authors, once again, if you have a whole section to add do it AFTER this line, and keep a comma at the end.
	);

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_modifications'],
		'help' => 'modsettings',
		'description' => $txt['modification_settings_desc'],
		'tabs' => array(
			'general' => array(
			),
		),
	);

	// Make it easier for mods to add new areas.
	call_integration_hook('integrate_modify_modifications', array(&$subActions));

	loadGeneralSettingParameters($subActions, 'general');

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Config array for changing the basic forum settings
 * Accessed  from ?action=admin;area=featuresettings;sa=basic;
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyBasicSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings;

	// We need to know if personal text is enabled, and if it's in the registration fields option.
	// If admins have set it up as an on-registration thing, they can't set a default value (because it'll never be used)
	$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
	$reg_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();
	$can_personal_text = !in_array('personal_text', $disabled_fields) && !in_array('personal_text', $reg_fields);

	$config_vars = array(
		// Big Options... polls, sticky, bbc....
		array('select', 'pollMode', array($txt['disable_polls'], $txt['enable_polls'], $txt['polls_as_topics'])),
		'',

		// Basic stuff, titles, flash, permissions...
		array('check', 'allow_guestAccess'),
		array('check', 'enable_buddylist'),
		array('check', 'allow_hideOnline'),
		array('check', 'titlesEnable'),
		array('text', 'default_personal_text', 'subtext' => $txt['default_personal_text_note'], 'disabled' => !$can_personal_text),
		array('check', 'topic_move_any'),
		array('int', 'defaultMaxListItems', 'step' => 1, 'min' => 1, 'max' => 999),
		'',

		// Jquery source
		array(
			'select',
			'jquery_source',
			array(
				'auto' => $txt['jquery_auto'],
				'local' => $txt['jquery_local'],
				'cdn' => $txt['jquery_cdn'],
				'custom' => $txt['jquery_custom']
			),
			'onchange' => 'if (this.value == \'custom\'){document.getElementById(\'jquery_custom\').disabled = false; } else {document.getElementById(\'jquery_custom\').disabled = true;}'
		),
		array(
			'text',
			'jquery_custom',
			'disabled' => isset($modSettings['jquery_source']) && $modSettings['jquery_source'] != 'custom', 'size' => 75
		),
		'',

		// css and js minification.
		array('check', 'minimize_files'),
		'',

		// SEO stuff
		array('check', 'queryless_urls', 'subtext' => '<strong>' . $txt['queryless_urls_note'] . '</strong>'),
		array('text', 'meta_keywords', 'subtext' => $txt['meta_keywords_note'], 'size' => 50),
		'',

		// Number formatting, timezones.
		array('text', 'time_format'),
		array(
			'float',
			'time_offset',
			'subtext' => $txt['setting_time_offset_note'],
			6,
			'postinput' => $txt['hours'],
			'step' => 0.25,
			'min' => -23.5,
			'max' => 23.5
		),
		'default_timezone' => array('select', 'default_timezone', array()),
		array('text', 'timezone_priority_countries', 'subtext' => $txt['setting_timezone_priority_countries_note']),
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
		'',

		// Alerts stuff
		array('check', 'enable_ajax_alerts'),
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
		$_SESSION['adm-save'] = true;

		// Do a bit of housekeeping
		if (empty($_POST['minimize_files']))
			deleteAllMinified();

		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

/**
 * Set a few Bulletin Board Code settings. It loads a list of Bulletin Board Code tags to allow disabling tags.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=featuresettings;sa=bbc.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses Admin template, edit_bbc_settings sub-template.
 */
function ModifyBBCSettings($return_config = false)
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir;

	$config_vars = array(
		// Main tweaks
		array('check', 'enableBBC'),
		array('check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked);'),
		array('check', 'enablePostHTML'),
		array('check', 'autoLinkUrls'),
		'',

		array('bbc', 'disabledBBC'),
	);

	$context['settings_post_javascript'] = '
		toggleBBCDisabled(\'disabledBBC\', ' . (empty($modSettings['enableBBC']) ? 'true' : 'false') . ');';

	call_integration_hook('integrate_modify_bbc_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template.
	require_once($sourcedir . '/ManageServer.php');
	$context['sub_template'] = 'show_settings';
	$context['page_title'] = $txt['manageposts_bbc_settings_title'];

	// Make sure we check the right tags!
	$modSettings['bbc_disabled_disabledBBC'] = empty($modSettings['disabledBBC']) ? array() : explode(',', $modSettings['disabledBBC']);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Clean up the tags.
		$bbcTags = array();
		foreach (parse_bbc(false) as $tag)
			$bbcTags[] = $tag['tag'];

		if (!isset($_POST['disabledBBC_enabledTags']))
			$_POST['disabledBBC_enabledTags'] = array();
		elseif (!is_array($_POST['disabledBBC_enabledTags']))
			$_POST['disabledBBC_enabledTags'] = array($_POST['disabledBBC_enabledTags']);

		// Work out what is actually disabled!
		$_POST['disabledBBC'] = implode(',', array_diff($bbcTags, $_POST['disabledBBC_enabledTags']));

		call_integration_hook('integrate_save_bbc_settings', array($bbcTags));

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=featuresettings;sa=bbc');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=bbc';
	$context['settings_title'] = $txt['manageposts_bbc_settings_title'];

	prepareDBSettingContext($config_vars);
}

/**
 * Allows modifying the global layout settings in the forum
 * Accessed through ?action=admin;area=featuresettings;sa=layout;
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyLayoutSettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = array(
		// Pagination stuff.
		array('check', 'compactTopicPagesEnable'),
		array(
			'int',
			'compactTopicPagesContiguous',
			null,
			$txt['contiguous_page_display'] . '<div class="smalltext">' . str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br>' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>') . '</div>'
		),
		array('int', 'defaultMaxMembers'),
		'',

		// Stuff that just is everywhere - today, search, online, etc.
		array('select', 'todayMod', array($txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'])),
		array('check', 'onlineEnable'),
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
		$_SESSION['adm-save'] = true;
		writeLog();

		redirectexit('action=admin;area=featuresettings;sa=layout');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=layout';
	$context['settings_title'] = $txt['mods_cat_layout'];

	prepareDBSettingContext($config_vars);
}

/**
 * Config array for changing like settings
 * Accessed  from ?action=admin;area=featuresettings;sa=likes;
 *
 * @param bool $return_config Whether or not to return the config_vars array
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyLikesSettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = array(
		array('check', 'enable_likes'),
		array('permissions', 'likes_like'),
	);

	call_integration_hook('integrate_likes_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_likes_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=featuresettings;sa=likes');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=likes';
	$context['settings_title'] = $txt['likes'];

	prepareDBSettingContext($config_vars);
}

/**
 * Config array for changing like settings
 * Accessed  from ?action=admin;area=featuresettings;sa=mentions;
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyMentionsSettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = array(
		array('check', 'enable_mentions'),
		array('permissions', 'mention'),
	);

	call_integration_hook('integrate_mentions_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_mentions_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=featuresettings;sa=mentions');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=mentions';
	$context['settings_title'] = $txt['mentions'];

	prepareDBSettingContext($config_vars);
}

/**
 * Moderation type settings - although there are fewer than we have you believe ;)
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyWarningSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// We need the existing ones for this
	list ($currently_enabled, $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$config_vars = array(
		// Warning system?
		'enable' => array('check', 'warning_enable'),
	);

	if (!empty($modSettings['warning_settings']) && $currently_enabled)
		$config_vars += array(
			'',

			array(
				'int',
				'warning_watch',
				'subtext' => $txt['setting_warning_watch_note'] . ' ' . $txt['zero_to_disable']
			),
			'moderate' => array(
				'int',
				'warning_moderate',
				'subtext' => $txt['setting_warning_moderate_note'] . ' ' . $txt['zero_to_disable']
			),
			array(
				'int',
				'warning_mute',
				'subtext' => $txt['setting_warning_mute_note'] . ' ' . $txt['zero_to_disable']
			),
			'rem1' => array(
				'int',
				'user_limit',
				'subtext' => $txt['setting_user_limit_note']
			),
			'rem2' => array(
				'int',
				'warning_decrement',
				'subtext' => $txt['setting_warning_decrement_note'] . ' ' . $txt['zero_to_disable']
			),
			array('permissions', 'view_warning'),
		);

	call_integration_hook('integrate_warning_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Cannot use moderation if post moderation is not enabled.
	if (!$modSettings['postmod_active'])
		unset($config_vars['moderate']);

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Make sure these don't have an effect.
		if (!$currently_enabled && empty($_POST['warning_enable']))
		{
			$_POST['warning_watch'] = 0;
			$_POST['warning_moderate'] = 0;
			$_POST['warning_mute'] = 0;
		}
		// If it was disabled and we're enabling it now, set some sane defaults.
		elseif (!$currently_enabled && !empty($_POST['warning_enable']))
		{
			// Need to add these, these weren't there before...
			$vars = array(
				'warning_watch' => 10,
				'warning_mute' => 60,
			);
			if ($modSettings['postmod_active'])
				$vars['warning_moderate'] = 35;

			foreach ($vars as $var => $value)
			{
				$config_vars[] = array('int', $var);
				$_POST[$var] = $value;
			}
		}
		else
		{
			$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
			$_POST['warning_moderate'] = $modSettings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
			$_POST['warning_mute'] = min($_POST['warning_mute'], 100);
		}

		// We might not have these already depending on how we got here.
		$_POST['user_limit'] = isset($_POST['user_limit']) ? (int) $_POST['user_limit'] : $modSettings['user_limit'];
		$_POST['warning_decrement'] = isset($_POST['warning_decrement']) ? (int) $_POST['warning_decrement'] : $modSettings['warning_decrement'];

		// Fix the warning setting array!
		$_POST['warning_settings'] = (!empty($_POST['warning_enable']) ? 1 : 0) . ',' . min(100, $_POST['user_limit']) . ',' . min(100, $_POST['warning_decrement']);
		$save_vars = $config_vars;
		$save_vars[] = array('text', 'warning_settings');
		unset($save_vars['enable'], $save_vars['rem1'], $save_vars['rem2']);

		call_integration_hook('integrate_save_warning_settings', array(&$save_vars));

		saveDBSettings($save_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=warnings');
	}

	// We actually store lots of these together - for efficiency.
	list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$context['sub_template'] = 'show_settings';
	$context['post_url'] = $scripturl . '?action=admin;area=warnings;save';
	$context['settings_title'] = $txt['warnings'];
	$context['page_title'] = $txt['warnings'];

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['warnings'],
		'help' => '',
		'description' => $txt['warnings_desc'],
	);

	prepareDBSettingContext($config_vars);
}

/**
 * Let's try keep the spam to a minimum ah Thantos?
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyAntispamSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc, $language, $sourcedir;

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Generate a sample registration image.
	$context['use_graphic_library'] = in_array('gd', get_loaded_extensions());
	$context['verification_image_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

	$config_vars = array(
		array('check', 'reg_verification'),
		array('check', 'search_enable_captcha'),
		// This, my friend, is a cheat :p
		'guest_verify' => array(
			'check',
			'guests_require_captcha',
			'subtext' => $txt['setting_guests_require_captcha_desc']
		),
		array(
			'int',
			'posts_require_captcha',
			'subtext' => $txt['posts_require_captcha_desc'],
			'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}'
		),
		'',

		// PM Settings
		'pm1' => array('int', 'max_pm_recipients', 'subtext' => $txt['max_pm_recipients_note']),
		'pm2' => array('int', 'pm_posts_verification', 'subtext' => $txt['pm_posts_verification_note']),
		'pm3' => array('int', 'pm_posts_per_hour', 'subtext' => $txt['pm_posts_per_hour_note']),
		// Visual verification.
		array('title', 'configure_verification_means'),
		array('desc', 'configure_verification_means_desc'),
		'vv' => array(
			'select',
			'visual_verification_type',
			array(
				$txt['setting_image_verification_off'],
				$txt['setting_image_verification_vsimple'],
				$txt['setting_image_verification_simple'],
				$txt['setting_image_verification_medium'],
				$txt['setting_image_verification_high'],
				$txt['setting_image_verification_extreme']
			),
			'subtext' => $txt['setting_visual_verification_type_desc'],
			'onchange' => $context['use_graphic_library'] ? 'refreshImages();' : ''
		),
		// reCAPTCHA
		array('title', 'recaptcha_configure'),
		array('desc', 'recaptcha_configure_desc', 'class' => 'windowbg'),
		array('check', 'recaptcha_enabled', 'subtext' => $txt['recaptcha_enable_desc']),
		array('text', 'recaptcha_site_key', 'subtext' => $txt['recaptcha_site_key_desc']),
		array('text', 'recaptcha_secret_key', 'subtext' => $txt['recaptcha_secret_key_desc']),
		array('select', 'recaptcha_theme', array('light' => $txt['recaptcha_theme_light'], 'dark' => $txt['recaptcha_theme_dark'])),
		// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
		array('title', 'setup_verification_questions'),
		array('desc', 'setup_verification_questions_desc'),
		array('int', 'qa_verification_number', 'subtext' => $txt['setting_qa_verification_number_desc']),
		array('callback', 'question_answer_list'),
	);

	call_integration_hook('integrate_spam_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	// Firstly, figure out what languages we're dealing with, and do a little processing for the form's benefit.
	getLanguages();
	$context['qa_languages'] = array();
	foreach ($context['languages'] as $lang_id => $lang)
	{
		$lang_id = strtr($lang_id, array('-utf8' => ''));
		$lang['name'] = strtr($lang['name'], array('-utf8' => ''));
		$context['qa_languages'][$lang_id] = $lang;
	}

	// Secondly, load any questions we currently have.
	$context['question_answers'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_question, lngfile, question, answers
		FROM {db_prefix}qanda'
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$lang = strtr($row['lngfile'], array('-utf8' => ''));
		$context['question_answers'][$row['id_question']] = array(
			'lngfile' => $lang,
			'question' => $row['question'],
			'answers' => $smcFunc['json_decode']($row['answers'], true),
		);
		$context['qa_by_lang'][$lang][] = $row['id_question'];
	}

	if (empty($context['qa_by_lang'][strtr($language, array('-utf8' => ''))]) && !empty($context['question_answers']))
	{
		if (empty($context['settings_insert_above']))
			$context['settings_insert_above'] = '';

		$context['settings_insert_above'] .= '<div class="noticebox">' . sprintf($txt['question_not_defined'], $context['languages'][$language]['name']) . '</div>';
	}

	// Thirdly, push some JavaScript for the form to make it work.
	addInlineJavaScript('
	var nextrow = ' . (!empty($context['question_answers']) ? max(array_keys($context['question_answers'])) + 1 : 1) . ';
	$(".qa_link a").click(function() {
		var id = $(this).parent().attr("id").substring(6);
		$("#qa_fs_" + id).show();
		$(this).parent().hide();
	});
	$(".qa_fieldset legend a").click(function() {
		var id = $(this).closest("fieldset").attr("id").substring(6);
		$("#qa_dt_" + id).show();
		$(this).closest("fieldset").hide();
	});
	$(".qa_add_question a").click(function() {
		var id = $(this).closest("fieldset").attr("id").substring(6);
		$(\'<dt><input type="text" name="question[\' + id + \'][\' + nextrow + \']" value="" size="50" class="verification_question"></dt><dd><input type="text" name="answer[\' + id + \'][\' + nextrow + \'][]" value="" size="50" class="verification_answer" / ><div class="qa_add_answer"><a href="javascript:void(0);">[ \' + ' . JavaScriptEscape($txt['setup_verification_add_answer']) . ' + \' ]</a></div></dd>\').insertBefore($(this).parent());
		nextrow++;
	});
	$(".qa_fieldset ").on("click", ".qa_add_answer a", function() {
		var attr = $(this).closest("dd").find(".verification_answer:last").attr("name");
		$(\'<input type="text" name="\' + attr + \'" value="" size="50" class="verification_answer">\').insertBefore($(this).closest("div"));
		return false;
	});
	$("#qa_dt_' . strtr($language, array('-utf8' => '')) . ' a").click();', true);

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

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
		$changes = array(
			'insert' => array(),
			'replace' => array(),
			'delete' => array(),
		);
		$qs_per_lang = array();
		foreach ($context['qa_languages'] as $lang_id => $dummy)
		{
			// If we had some questions for this language before, but don't now, delete everything from that language.
			if ((!isset($_POST['question'][$lang_id]) || !is_array($_POST['question'][$lang_id])) && !empty($context['qa_by_lang'][$lang_id]))
				$changes['delete'] = array_merge($changes['delete'], $context['qa_by_lang'][$lang_id]);

			// Now step through and see if any existing questions no longer exist.
			if (!empty($context['qa_by_lang'][$lang_id]))
				foreach ($context['qa_by_lang'][$lang_id] as $q_id)
					if (empty($_POST['question'][$lang_id][$q_id]))
						$changes['delete'][] = $q_id;

			// Now let's see if there are new questions or ones that need updating.
			if (isset($_POST['question'][$lang_id]))
			{
				foreach ($_POST['question'][$lang_id] as $q_id => $question)
				{
					// Ignore junky ids.
					$q_id = (int) $q_id;
					if ($q_id <= 0)
						continue;

					// Check the question isn't empty (because they want to delete it?)
					if (empty($question) || trim($question) == '')
					{
						if (isset($context['question_answers'][$q_id]))
							$changes['delete'][] = $q_id;
						continue;
					}
					$question = $smcFunc['htmlspecialchars'](trim($question));

					// Get the answers. Firstly check there actually might be some.
					if (!isset($_POST['answer'][$lang_id][$q_id]) || !is_array($_POST['answer'][$lang_id][$q_id]))
					{
						if (isset($context['question_answers'][$q_id]))
							$changes['delete'][] = $q_id;
						continue;
					}
					// Now get them and check that they might be viable.
					$answers = array();
					foreach ($_POST['answer'][$lang_id][$q_id] as $answer)
						if (!empty($answer) && trim($answer) !== '')
							$answers[] = $smcFunc['htmlspecialchars'](trim($answer));
					if (empty($answers))
					{
						if (isset($context['question_answers'][$q_id]))
							$changes['delete'][] = $q_id;
						continue;
					}
					$answers = $smcFunc['json_encode']($answers);

					// At this point we know we have a question and some answers. What are we doing with it?
					if (!isset($context['question_answers'][$q_id]))
					{
						// New question. Now, we don't want to randomly consume ids, so we'll set those, rather than trusting the browser's supplied ids.
						$changes['insert'][] = array($lang_id, $question, $answers);
					}
					else
					{
						// It's an existing question. Let's see what's changed, if anything.
						if ($lang_id != $context['question_answers'][$q_id]['lngfile'] || $question != $context['question_answers'][$q_id]['question'] || $answers != $context['question_answers'][$q_id]['answers'])
							$changes['replace'][$q_id] = array('lngfile' => $lang_id, 'question' => $question, 'answers' => $answers);
					}

					if (!isset($qs_per_lang[$lang_id]))
						$qs_per_lang[$lang_id] = 0;
					$qs_per_lang[$lang_id]++;
				}
			}
		}

		// OK, so changes?
		if (!empty($changes['delete']))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}qanda
				WHERE id_question IN ({array_int:questions})',
				array(
					'questions' => $changes['delete'],
				)
			);
		}

		if (!empty($changes['replace']))
		{
			foreach ($changes['replace'] as $q_id => $question)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}qanda
					SET lngfile = {string:lngfile},
						question = {string:question},
						answers = {string:answers}
					WHERE id_question = {int:id_question}',
					array(
						'id_question' => $q_id,
						'lngfile' => $question['lngfile'],
						'question' => $question['question'],
						'answers' => $question['answers'],
					)
				);
			}
		}

		if (!empty($changes['insert']))
		{
			$smcFunc['db_insert']('insert',
				'{db_prefix}qanda',
				array('lngfile' => 'string-50', 'question' => 'string-255', 'answers' => 'string-65534'),
				$changes['insert'],
				array('id_question')
			);
		}

		// Lastly, the count of messages needs to be no more than the lowest number of questions for any one language.
		$count_questions = empty($qs_per_lang) ? 0 : min($qs_per_lang);
		if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
			$_POST['qa_verification_number'] = $count_questions;

		call_integration_hook('integrate_save_spam_settings', array(&$save_vars));

		// Now save.
		saveDBSettings($save_vars);
		$_SESSION['adm-save'] = true;

		cache_put_data('verificationQuestions', null, 300);

		redirectexit('action=admin;area=antispam');
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
		$config_vars['vv']['postinput'] = '<br><img src="' . $context['verification_image_href'] . ';type=' . (empty($modSettings['visual_verification_type']) ? 0 : $modSettings['visual_verification_type']) . '" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image"><br>';
	else
		$config_vars['vv']['postinput'] = '<br><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';

	// Hack for PM spam settings.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Hack for guests requiring verification.
	$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
	$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

	// Some minor javascript for the guest post setting.
	if ($modSettings['posts_require_captcha'])
		$context['settings_post_javascript'] .= '
		document.getElementById(\'guests_require_captcha\').disabled = true;';

	// And everything else.
	$context['post_url'] = $scripturl . '?action=admin;area=antispam;save';
	$context['settings_title'] = $txt['antispam_Settings'];
	$context['page_title'] = $txt['antispam_title'];
	$context['sub_template'] = 'show_settings';

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['antispam_title'],
		'description' => $txt['antispam_Settings_desc'],
	);

	prepareDBSettingContext($config_vars);
}

/**
 * You'll never guess what this function does...
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifySignatureSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sig_start, $smcFunc, $scripturl;

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
				WHERE id_member BETWEEN {int:step} AND {int:step} + 49
					AND id_group != {int:admin_group}
					AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
				array(
					'admin_group' => 1,
					'step' => $_GET['step'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Apply all the rules we can realistically do.
				$sig = strtr($row['signature'], array('<br>' => "\n"));

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
					preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $sig, $matches);
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
							$width = -1;
							$height = -1;
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

				$sig = strtr($sig, array("\n" => '<br>'));
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
		$settings_applied = true;
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
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=featuresettings;sa=sig');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=sig';
	$context['settings_title'] = $txt['signature_settings'];

	$context['settings_message'] = !empty($settings_applied) ? '<div class="infobox">' . $txt['signature_settings_applied'] . '</div>' : '<p class="centertext">' . sprintf($txt['signature_settings_warning'], $context['session_id'], $context['session_var']) . '</p>';

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
	global $txt, $scripturl, $context;
	global $sourcedir;

	$context['page_title'] = $txt['custom_profile_title'];
	$context['sub_template'] = 'show_custom_profile';

	// What about standard fields they can tweak?
	$standard_fields = array('website', 'personal_text', 'timezone', 'posts', 'warning_status');
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

		$_SESSION['adm-save'] = true;
		if (!empty($changes))
			updateSettings($changes);
	}

	createToken('admin-scp');

	// Need to know the max order for custom fields
	$context['custFieldsMaxOrder'] = custFieldsMaxOrder();

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
				),
				'data' => array(
					'db' => 'label',
					'style' => 'width: 60%;',
				),
			),
			'active' => array(
				'header' => array(
					'value' => $txt['custom_edit_active'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						$isChecked = $rowData['disabled'] ? '' : ' checked';
						$onClickHandler = $rowData['can_show_register'] ? sprintf(' onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';
						return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" %2$s%3$s>', $rowData['id'], $isChecked, $onClickHandler);
					},
					'style' => 'width: 20%;',
					'class' => 'centercol',
				),
			),
			'show_on_registration' => array(
				'header' => array(
					'value' => $txt['custom_edit_registration'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked' : '';
						$isDisabled = $rowData['can_show_register'] ? '' : ' disabled';
						return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" %2$s%3$s>', $rowData['id'], $isChecked, $isDisabled);
					},
					'style' => 'width: 20%;',
					'class' => 'centercol',
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
				'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="button">',
			),
		),
	);
	createList($listOptions);

	$listOptions = array(
		'id' => 'custom_profile_fields',
		'title' => $txt['custom_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'default_sort_col' => 'field_order',
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
			'field_order' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldorder'],
				),
				'data' => array(
					'function' => function($rowData) use ($context, $txt, $scripturl)
					{
						$return = '<p class="centertext bold_text">' . $rowData['field_order'] . '<br>';

						if ($rowData['field_order'] > 1)
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=up"><span class="toggle_up" title="' . $txt['custom_edit_order_move'] . ' ' . $txt['custom_edit_order_up'] . '"></span></a>';

						if ($rowData['field_order'] < $context['custFieldsMaxOrder'])
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=down"><span class="toggle_down" title="' . $txt['custom_edit_order_move'] . ' ' . $txt['custom_edit_order_down'] . '"></span></a>';

						$return .= '</p>';

						return $return;
					},
					'style' => 'width: 12%;',
				),
				'sort' => array(
					'default' => 'field_order',
					'reverse' => 'field_order DESC',
				),
			),
			'field_name' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldname'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>', $scripturl, $rowData['id_field'], $rowData['field_name'], $rowData['field_desc']);
					},
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
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);
						return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
					},
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
					'function' => function($rowData) use ($txt)
					{
						return $rowData['active'] ? $txt['yes'] : $txt['no'];
					},
					'style' => 'width: 8%;',
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
					'function' => function($rowData)
					{
						global $txt, $context;

						return $txt['custom_profile_placement_' . (empty($rowData['placement']) ? 'standard' : $context['cust_profile_fields_placement'][$rowData['placement']])];
					},
					'style' => 'width: 8%;',
				),
				'sort' => array(
					'default' => 'placement DESC',
					'reverse' => 'placement',
				),
			),
			'show_on_registration' => array(
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_field' => false,
						),
					),
					'style' => 'width: 15%;',
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
				'value' => '<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" class="button">',
			),
		),
	);
	createList($listOptions);

	// There are two different ways we could get to this point. To keep it simple, they both do
	// the same basic thing.
	if (isset($_SESSION['adm-save']))
	{
		$context['saved_successful'] = true;
		unset ($_SESSION['adm-save']);
	}
}

/**
 * Callback for createList().
 *
 * @param int $start The item to start with (used for pagination purposes)
 * @param int $items_per_page The number of items to display per page
 * @param string $sort A string indicating how to sort the results
 * @param bool $standardFields Whether or not to include standard fields as well
 * @return array An array of info about the various profile fields
 */
function list_getProfileFields($start, $items_per_page, $sort, $standardFields)
{
	global $txt, $modSettings, $smcFunc;

	$list = array();

	if ($standardFields)
	{
		$standard_fields = array('website', 'personal_text', 'timezone', 'posts', 'warning_status');
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
			SELECT id_field, col_name, field_name, field_desc, field_type, field_order, active, placement
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
 *
 * @return int The total number of custom profile fields
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
	global $txt, $scripturl, $context, $smcFunc;

	// Sort out the context!
	$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
	$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
	$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
	$context['sub_template'] = 'edit_profile_field';

	// Load the profile language for section names.
	loadLanguage('Profile');

	// There's really only a few places we can go...
	$move_to = array('up', 'down');

	// We need this for both moving and saving so put it right here.
	$order_count = custFieldsMaxOrder();

	if ($context['fid'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				id_field, col_name, field_name, field_desc, field_type, field_order, field_length, field_options,
				show_reg, show_display, show_mlist, show_profile, private, active, default_value, can_search,
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
				'col_name' => $row['col_name'],
				'profile_area' => $row['show_profile'],
				'reg' => $row['show_reg'],
				'display' => $row['show_display'],
				'mlist' => $row['show_mlist'],
				'type' => $row['field_type'],
				'order' => $row['field_order'],
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
			'col_name' => '???',
			'desc' => '',
			'profile_area' => 'forumprofile',
			'reg' => false,
			'display' => false,
			'mlist' => false,
			'type' => 'text',
			'order' => 0,
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

	// Are we moving it?
	if (isset($_GET['move']) && in_array($smcFunc['htmlspecialchars']($_GET['move']), $move_to))
	{
		// Down is the new up.
		$new_order = ($_GET['move'] == 'up' ? ($context['field']['order'] - 1) : ($context['field']['order'] + 1));

		// Is this a valid position?
		if ($new_order <= 0 || $new_order > $order_count)
			redirectexit('action=admin;area=featuresettings;sa=profile'); // @todo implement an error handler

		// All good, proceed.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = {int:old_order}
			WHERE field_order = {int:new_order}',
			array(
				'new_order' => $new_order,
				'old_order' => $context['field']['order'],
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = {int:new_order}
			WHERE id_field = {int:id_field}',
			array(
				'new_order' => $new_order,
				'id_field' => $context['fid'],
			)
		);
		redirectexit('action=admin;area=featuresettings;sa=profile'); // @todo perhaps a nice confirmation message, dunno.
	}

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
		$show_mlist = isset($_POST['mlist']) ? 1 : 0;
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
			$col_name = $smcFunc['substr'](strtr($_POST['field_name'], array(' ' => '')), 0, 6);
			preg_match('~([\w\d_-]+)~', $col_name, $matches);

			// If there is nothing to the name, then let's start out own - for foreign languages etc.
			if (isset($matches[1]))
				$col_name = $initial_col_name = 'cust_' . strtolower($matches[1]);
			else
				$col_name = $initial_col_name = 'cust_' . mt_rand(1, 9999);

			// Make sure this is unique.
			$current_fields = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_field, col_name
				FROM {db_prefix}custom_fields'
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$current_fields[$row['id_field']] = $row['col_name'];

			$smcFunc['db_free_result']($request);

			$unique = false;
			for ($i = 0; !$unique && $i < 9; $i++)
			{
				if (!in_array($col_name, $current_fields))
					$unique = true;
				else
					$col_name = $initial_col_name . $i;
			}

			// Still not a unique column name? Leave it up to the user, then.
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
						'current_column' => $context['field']['col_name'],
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
								'current_column' => $context['field']['col_name'],
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
					show_display = {int:show_display}, show_mlist = {int:show_mlist}, show_profile = {string:show_profile},
					private = {int:private}, active = {int:active}, default_value = {string:default_value},
					can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
					enclose = {string:enclose}, placement = {int:placement}
				WHERE id_field = {int:current_field}',
				array(
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_mlist' => $show_mlist,
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
						'current_column' => $context['field']['col_name'],
					)
				);
		}
		else
		{
			// Gotta figure it out the order.
			$new_order = $order_count > 1 ? ($order_count + 1) : 1;

			$smcFunc['db_insert']('',
				'{db_prefix}custom_fields',
				array(
					'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
					'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string', 'field_order' => 'int',
					'show_reg' => 'int', 'show_display' => 'int', 'show_mlist' => 'int', 'show_profile' => 'string',
					'private' => 'int', 'active' => 'int', 'default_value' => 'string', 'can_search' => 'int',
					'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int',
				),
				array(
					$col_name, $_POST['field_name'], $_POST['field_desc'],
					$_POST['field_type'], $field_length, $field_options, $new_order,
					$show_reg, $show_display, $show_mlist, $show_profile,
					$private, $active, $default, $can_search,
					$bbc, $mask, $enclose, $placement,
				),
				array('id_field')
			);
		}
	}
	// Deleting?
	elseif (isset($_POST['delete']) && $context['field']['col_name'])
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
				'current_column' => $context['field']['col_name'],
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

		// Re-arrange the order.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = field_order - 1
			WHERE field_order > {int:current_order}',
			array(
				'current_order' => $context['field']['order'],
			)
		);
	}

	// Rebuild display cache etc.
	if (isset($_POST['delete']) || isset($_POST['save']))
	{
		checkSession();

		$request = $smcFunc['db_query']('', '
			SELECT col_name, field_name, field_type, field_order, bbc, enclose, placement, show_mlist, field_options
			FROM {db_prefix}custom_fields
			WHERE show_display = {int:is_displayed}
				AND active = {int:active}
				AND private != {int:not_owner_only}
				AND private != {int:not_admin_only}
			ORDER BY field_order',
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
				'col_name' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'order' => $row['field_order'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
				'mlist' => $row['show_mlist'],
				'options' => (!empty($row['field_options']) ? explode(',', $row['field_options']) : array()),
			);
		}
		$smcFunc['db_free_result']($request);

		updateSettings(array('displayFields' => $smcFunc['json_encode']($fields)));
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=featuresettings;sa=profile');
	}

	createToken('admin-ecp');
}

/**
 * Returns the maximum field_order value for the custom fields
 *
 * @return int The maximum value of field_order from the custom_fields table
 */
function custFieldsMaxOrder()
{
	global $smcFunc;

	// Gotta know the order limit
	$result = $smcFunc['db_query']('', '
		SELECT MAX(field_order)
		FROM {db_prefix}custom_fields',
		array()
	);

	list ($order_count) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return (int) $order_count;
}

/**
 * Allow to edit the settings on the pruning screen.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyLogSettings($return_config = false)
{
	global $txt, $scripturl, $sourcedir, $context, $modSettings;

	// Make sure we understand what's going on.
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['log_settings'];

	$config_vars = array(
		array('check', 'modlog_enabled', 'help' => 'modlog'),
		array('check', 'adminlog_enabled', 'help' => 'adminlog'),
		array('check', 'userlog_enabled', 'help' => 'userlog'),
		// The error log is a wonderful thing.
		array('title', 'errlog'),
		array('desc', 'error_log_desc'),
		array('check', 'enableErrorLogging'),
		array('check', 'enableErrorQueryLogging'),
		array('check', 'log_ban_hits'),
		// Even do the pruning?
		array('title', 'pruning_title'),
		array('desc', 'pruning_desc'),
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

	// We want to be toggling some of these for a nice user experience. If you want to add yours to the list of those magically hidden when the 'pruning' option is off, add to this.
	$prune_toggle = array('pruneErrorLog', 'pruneModLog', 'pruneBanLog', 'pruneReportLog', 'pruneScheduledTaskLog', 'pruneSpiderHitLog');

	call_integration_hook('integrate_prune_settings', array(&$config_vars, &$prune_toggle, false));

	$prune_toggle_dt = array();
	foreach ($prune_toggle as $item)
		$prune_toggle_dt[] = 'setting_' . $item;

	if ($return_config)
		return $config_vars;

	addInlineJavaScript('
	function togglePruned()
	{
		var newval = $("#pruningOptions").prop("checked");
		$("#' . implode(', #', $prune_toggle) . '").closest("dd").toggle(newval);
		$("#' . implode(', #', $prune_toggle_dt) . '").closest("dt").toggle(newval);
	};
	togglePruned();
	$("#pruningOptions").click(function() { togglePruned(); });', true);

	// We'll need this in a bit.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Because of the excitement attached to combining pruning log items, we need to duplicate everything here.
		$savevar = array(
			array('check', 'modlog_enabled'),
			array('check', 'adminlog_enabled'),
			array('check', 'userlog_enabled'),
			array('check', 'enableErrorLogging'),
			array('check', 'enableErrorQueryLogging'),
			array('check', 'log_ban_hits'),
			array('text', 'pruningOptions')
		);

		call_integration_hook('integrate_prune_settings', array(&$savevar, &$prune_toggle, true));

		if (!empty($_POST['pruningOptions']))
		{
			$vals = array();
			foreach ($config_vars as $index => $dummy)
			{
				if (!is_array($dummy) || $index == 'pruningOptions' || !in_array($dummy[1], $prune_toggle))
					continue;

				$vals[] = empty($_POST[$dummy[1]]) || $_POST[$dummy[1]] < 0 ? 0 : (int) $_POST[$dummy[1]];
			}
			$_POST['pruningOptions'] = implode(',', $vals);
		}
		else
			$_POST['pruningOptions'] = '';

		saveDBSettings($savevar);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=logs;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=logs;save;sa=settings';
	$context['settings_title'] = $txt['log_settings'];
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
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyGeneralModSettings($return_config = false)
{
	global $txt, $scripturl, $context;

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

		// This line is to remind mod authors that it's nice to let the users know when something has been saved.
		$_SESSION['adm-save'] = true;

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: I LOVE TEA!
		redirectexit('action=admin;area=modsettings;sa=general');
	}

	// This line is to help mod authors do a search/add after if you want to add something here. Keyword: RED INK IS FOR TEACHERS AND THOSE WHO LIKE PAIN!
	prepareDBSettingContext($config_vars);
}

/**
 * Handles modifying the alerts settings
 */
function ModifyAlertsSettings()
{
	global $context, $modSettings, $sourcedir, $txt;

	// Dummy settings for the template...
	$modSettings['allow_disableAnnounce'] = false;
	$context['user']['is_owner'] = false;
	$context['member'] = array();
	$context['id_member'] = 0;
	$context['menu_item_selected'] = 'alerts';
	$context['token_check'] = 'noti-admin';

	// Specify our action since we'll want to post back here instead of the profile
	$context['action'] = 'action=admin;area=featuresettings;sa=alerts;' . $context['session_var'] . '=' . $context['session_id'];

	loadTemplate('Profile');
	loadLanguage('Profile');

	include_once($sourcedir . '/Profile-Modify.php');
	alert_configuration(0);

	$context['page_title'] = $txt['notify_settings'];

	// Override the description
	$context['description'] = $txt['notifications_desc'];
	$context['sub_template'] = 'alert_configuration';
}

?>