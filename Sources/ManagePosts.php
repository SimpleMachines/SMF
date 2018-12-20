<?php

/**
 * This file contains all the administration settings for topics and posts.
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
 * The main entrance point for the 'Posts and topics' screen.
 * Like all others, it checks permissions, then forwards to the right function
 * based on the given sub-action.
 * Defaults to sub-action 'posts'.
 * Accessed from ?action=admin;area=postsettings.
 * Requires (and checks for) the admin_forum permission.
 */
function ManagePostSettings()
{
	global $context, $txt;

	// Make sure you can be here.
	isAllowedTo('admin_forum');
	loadLanguage('Drafts');

	$subActions = array(
		'posts' => 'ModifyPostSettings',
		'censor' => 'SetCensor',
		'topics' => 'ModifyTopicSettings',
		'drafts' => 'ModifyDraftSettings',
	);

	// Default the sub-action to 'posts'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'posts';

	$context['page_title'] = $txt['manageposts_title'];

	// Tabs for browsing the different post functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manageposts_title'],
		'help' => 'posts_and_topics',
		'description' => $txt['manageposts_description'],
		'tabs' => array(
			'posts' => array(
				'description' => $txt['manageposts_settings_description'],
			),
			'censor' => array(
				'description' => $txt['admin_censored_desc'],
			),
			'topics' => array(
				'description' => $txt['manageposts_topic_settings_description'],
			),
			'drafts' => array(
				'description' => $txt['managedrafts_settings_description'],
			),
		),
	);

	call_integration_hook('integrate_manage_posts', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Shows an interface to set and test censored words.
 * It uses the censor_vulgar, censor_proper, censorWholeWord, and censorIgnoreCase
 * settings.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=censor.
 *
 * @uses the Admin template and the edit_censored sub template.
 */
function SetCensor()
{
	global $txt, $modSettings, $context, $smcFunc, $sourcedir;

	if (!empty($_POST['save_censor']))
	{
		// Make sure censoring is something they can do.
		checkSession();
		validateToken('admin-censor');

		$censored_vulgar = array();
		$censored_proper = array();

		// Rip it apart, then split it into two arrays.
		if (isset($_POST['censortext']))
		{
			$_POST['censortext'] = explode("\n", strtr($_POST['censortext'], array("\r" => '')));

			foreach ($_POST['censortext'] as $c)
				list ($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
		}
		elseif (isset($_POST['censor_vulgar'], $_POST['censor_proper']))
		{
			if (is_array($_POST['censor_vulgar']))
			{
				foreach ($_POST['censor_vulgar'] as $i => $value)
				{
					if (trim(strtr($value, '*', ' ')) == '')
						unset($_POST['censor_vulgar'][$i], $_POST['censor_proper'][$i]);
				}

				$censored_vulgar = $_POST['censor_vulgar'];
				$censored_proper = $_POST['censor_proper'];
			}
			else
			{
				$censored_vulgar = explode("\n", strtr($_POST['censor_vulgar'], array("\r" => '')));
				$censored_proper = explode("\n", strtr($_POST['censor_proper'], array("\r" => '')));
			}
		}

		// Set the new arrays and settings in the database.
		$updates = array(
			'censor_vulgar' => implode("\n", $censored_vulgar),
			'censor_proper' => implode("\n", $censored_proper),
			'allow_no_censored' => empty($_POST['allow_no_censored']) ? '0' : '1',
			'censorWholeWord' => empty($_POST['censorWholeWord']) ? '0' : '1',
			'censorIgnoreCase' => empty($_POST['censorIgnoreCase']) ? '0' : '1',
		);

		call_integration_hook('integrate_save_censors', array(&$updates));

		$context['saved_successful'] = true;
		updateSettings($updates);
	}

	if (isset($_POST['censortest']))
	{
		require_once($sourcedir . '/Subs-Post.php');
		$censorText = $smcFunc['htmlspecialchars']($_POST['censortest'], ENT_QUOTES);
		preparsecode($censorText);
		$context['censor_test'] = strtr(censorText($censorText), array('"' => '&quot;'));
	}

	// Set everything up for the template to do its thang.
	$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
	$censor_proper = explode("\n", $modSettings['censor_proper']);

	$context['censored_words'] = array();
	for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
	{
		if (empty($censor_vulgar[$i]))
			continue;

		// Skip it, it's either spaces or stars only.
		if (trim(strtr($censor_vulgar[$i], '*', ' ')) == '')
			continue;

		$context['censored_words'][$smcFunc['htmlspecialchars'](trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? $smcFunc['htmlspecialchars']($censor_proper[$i]) : '';
	}

	call_integration_hook('integrate_censors');

	// Since the "Allow users to disable the word censor" stuff was moved from a theme setting to a global one, we need this...
	loadLanguage('Themes');

	$context['sub_template'] = 'edit_censored';
	$context['page_title'] = $txt['admin_censored_words'];

	createToken('admin-censor');
}

/**
 * Modify any setting related to posts and posting.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=posts.
 *
 * @param bool $return_config Whether or not to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 * @uses Admin template, edit_post_settings sub-template.
 */
function ModifyPostSettings($return_config = false)
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir, $smcFunc, $db_type;

	// Make an inline conditional a little shorter...
	$can_spell_check = false;
	if (function_exists('pspell_new'))
		$can_spell_check = true;
	elseif (function_exists('enchant_broker_init') && ($txt['lang_character_set'] == 'UTF-8' || function_exists('iconv')))
		$can_spell_check = true;

	// All the settings...
	$config_vars = array(
		// Simple post options...
		array('check', 'removeNestedQuotes'),
		array('check', 'enableSpellChecking', 'disabled' => !$can_spell_check),
		array('check', 'disable_wysiwyg'),
		array('check', 'additional_options_collapsable'),
		array('check', 'guest_post_no_email'),
		'',

		// Posting limits...
		array('int', 'max_messageLength', 'subtext' => $txt['max_messageLength_zero'], 'postinput' => $txt['manageposts_characters']),
		array('int', 'topicSummaryPosts', 'postinput' => $txt['manageposts_posts']),
		'',

		// Posting time limits...
		array('int', 'spamWaitTime', 'postinput' => $txt['manageposts_seconds']),
		array('int', 'edit_wait_time', 'postinput' => $txt['manageposts_seconds']),
		array('int', 'edit_disable_time', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['manageposts_minutes']),
		'',

		// Automagic image resizing.
		array('int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']),
		array('int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']),
		'',

		// First & Last message preview lengths
		array('int', 'preview_characters', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['preview_characters_units']),
	);

	call_integration_hook('integrate_modify_post_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// We'll want this for our easy save.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['manageposts_settings'];
	$context['sub_template'] = 'show_settings';

	// Are we saving them - are we??
	if (isset($_GET['save']))
	{
		checkSession();

		// If we're changing the message length (and we are using MySQL) let's check the column is big enough.
		if (isset($_POST['max_messageLength']) && $_POST['max_messageLength'] != $modSettings['max_messageLength'] && ($db_type == 'mysql'))
		{
			db_extend('packages');

			$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
			foreach ($colData as $column)
				if ($column['name'] == 'body')
					$body_type = $column['type'];

			if (isset($body_type) && ($_POST['max_messageLength'] > 65535 || $_POST['max_messageLength'] == 0) && $body_type == 'text')
				fatal_lang_error('convert_to_mediumtext', false, array($scripturl . '?action=admin;area=maintain;sa=database'));
		}

		// If we're changing the post preview length let's check its valid
		if (!empty($_POST['preview_characters']))
			$_POST['preview_characters'] = (int) min(max(0, $_POST['preview_characters']), 512);

		call_integration_hook('integrate_save_post_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=postsettings;sa=posts');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=posts';
	$context['settings_title'] = $txt['manageposts_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * Modify any setting related to topics.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=topics.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns $config_vars if $return_config is true
 * @uses Admin template, edit_topic_settings sub-template.
 */
function ModifyTopicSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl;

	// Here are all the topic settings.
	$config_vars = array(
		// Some simple bools...
		array('check', 'enableParticipation'),
		'',

		// Pagination etc...
		array('int', 'oldTopicDays', 'postinput' => $txt['manageposts_days'], 'subtext' => $txt['zero_to_disable']),
		array('int', 'defaultMaxTopics', 'postinput' => $txt['manageposts_topics']),
		array('int', 'defaultMaxMessages', 'postinput' => $txt['manageposts_posts']),
		array('check', 'disable_print_topic'),
		'',

		// All, next/prev...
		array('int', 'enableAllMessages', 'postinput' => $txt['manageposts_posts'], 'subtext' => $txt['enableAllMessages_zero']),
		array('check', 'disableCustomPerPage'),
		array('check', 'enablePreviousNext'),
		'',

		// Topic related settings (show gender icon/avatars etc...)
		array('check', 'subject_toggle'),
		array('check', 'show_modify'),
		array('check', 'show_profile_buttons'),
		array('check', 'show_user_images'),
		array('check', 'show_blurb'),
		array('check', 'hide_post_group', 'subtext' => $txt['hide_post_group_desc']),
		'',

		// First & Last message preview lengths
		array('int', 'preview_characters', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['preview_characters_units']),
		array('check', 'message_index_preview_first', 'subtext' => $txt['message_index_preview_first_desc']),
	);

	call_integration_hook('integrate_modify_topic_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Get the settings template ready.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['manageposts_topic_settings'];
	$context['sub_template'] = 'show_settings';

	// Are we saving them - are we??
	if (isset($_GET['save']))
	{
		checkSession();
		call_integration_hook('integrate_save_topic_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=postsettings;sa=topics');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
	$context['settings_title'] = $txt['manageposts_topic_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * Modify any setting related to drafts.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=drafts
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses Admin template, edit_topic_settings sub-template.
 */
function ModifyDraftSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc;

	// Here are all the draft settings, a bit lite for now, but we can add more :P
	$config_vars = array(
		// Draft settings ...
		array('check', 'drafts_post_enabled'),
		array('check', 'drafts_pm_enabled'),
		array('check', 'drafts_show_saved_enabled', 'subtext' => $txt['drafts_show_saved_enabled_subnote']),
		array('int', 'drafts_keep_days', 'postinput' => $txt['days_word'], 'subtext' => $txt['drafts_keep_days_subnote']),
		'',
		array('check', 'drafts_autosave_enabled', 'subtext' => $txt['drafts_autosave_enabled_subnote']),
		array('int', 'drafts_autosave_frequency', 'postinput' => $txt['manageposts_seconds'], 'subtext' => $txt['drafts_autosave_frequency_subnote']),
	);

	if ($return_config)
		return $config_vars;

	// Get the settings template ready.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['managedrafts_settings'];
	$context['sub_template'] = 'show_settings';

	// Saving them ?
	if (isset($_GET['save']))
	{
		checkSession();

		// Protect them from themselves.
		$_POST['drafts_autosave_frequency'] = !isset($_POST['drafts_autosave_frequency']) || $_POST['drafts_autosave_frequency'] < 30 ? 30 : $_POST['drafts_autosave_frequency'];

		// Also disable the scheduled task if we're not using it.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:disabled}
			WHERE task = {string:task}',
			array(
				'disabled' => !empty($_POST['drafts_keep_days']) ? 0 : 1,
				'task' => 'remove_old_drafts',
			)
		);
		require_once($sourcedir . '/ScheduledTasks.php');
		CalculateNextTrigger();

		// Save everything else and leave.
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=postsettings;sa=drafts');
	}

	// some javascript to enable / disable the frequency input box
	$context['settings_post_javascript'] = '
		function toggle()
		{
			$("#drafts_autosave_frequency").prop("disabled", !($("#drafts_autosave_enabled").prop("checked")));
		};
		toggle();

		$("#drafts_autosave_enabled").click(function() { toggle(); });
	';

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;sa=drafts;save';
	$context['settings_title'] = $txt['managedrafts_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

?>