<?php

/**
 * This file contains all the administration settings for topics and posts.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

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
	global $context, $txt, $scripturl;

	// Make sure you can be here.
	isAllowedTo('admin_forum');

	$subActions = array(
		'posts' => 'ModifyPostSettings',
		'bbc' => 'ModifyBBCSettings',
		'censor' => 'SetCensor',
		'topics' => 'ModifyTopicSettings',
	);

	call_integration_hook('integrate_manage_posts', array(&$subActions));

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
			'bbc' => array(
				'description' => $txt['manageposts_bbc_settings_description'],
			),
			'censor' => array(
				'description' => $txt['admin_censored_desc'],
			),
			'topics' => array(
				'description' => $txt['manageposts_topic_settings_description'],
			),
		),
	);

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
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
			'censorWholeWord' => empty($_POST['censorWholeWord']) ? '0' : '1',
			'censorIgnoreCase' => empty($_POST['censorIgnoreCase']) ? '0' : '1',
		);

		call_integration_hook('integrate_save_censors', array(&$updates));

		updateSettings($updates);
	}

	if (isset($_POST['censortest']))
	{
		require_once($sourcedir . '/Subs-Post.php');
		$censorText = htmlspecialchars($_POST['censortest'], ENT_QUOTES);
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

		$context['censored_words'][htmlspecialchars(trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? htmlspecialchars($censor_proper[$i]) : '';
	}

	call_integration_hook('integrate_censors');

	$context['sub_template'] = 'edit_censored';
	$context['page_title'] = $txt['admin_censored_words'];

	createToken('admin-censor');
}

/**
 * Modify any setting related to posts and posting.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=posts.
 *
 * @param bool $return_config = false
 * @uses Admin template, edit_post_settings sub-template.
 */
function ModifyPostSettings($return_config = false)
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir, $smcFunc, $db_prefix, $db_type;

	// All the settings...
	$config_vars = array(
			// Simple post options...
			array('check', 'removeNestedQuotes'),
			array('check', 'enableEmbeddedFlash', 'subtext' => $txt['enableEmbeddedFlash_warning']),
			// Note show the warning as read if pspell not installed!
			array('check', 'enableSpellChecking', 'subtext' => (function_exists('pspell_new') ? $txt['enableSpellChecking_warning'] : ('<span class="alert">' . $txt['enableSpellChecking_warning'] . '</span>'))),
			array('check', 'disable_wysiwyg'),
		'',
			// Posting limits...
			array('int', 'max_messageLength', 'subtext' => $txt['max_messageLength_zero'], 'postinput' => $txt['manageposts_characters']),
			array('int', 'topicSummaryPosts', 'postinput' => $txt['manageposts_posts']),
		'',
			// Posting time limits...
			array('int', 'spamWaitTime', 'postinput' => $txt['manageposts_seconds']),
			array('int', 'edit_wait_time', 'postinput' => $txt['manageposts_seconds']),
			array('int', 'edit_disable_time', 'subtext' => $txt['edit_disable_time_zero'], 'postinput' => $txt['manageposts_minutes']),
		'',
			// First & Last message preview lengths
			array('int', 'preview_characters', 'subtext' => $txt['preview_characters_zero'], 'postinput' => $txt['preview_characters_units']),
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
		if (isset($_POST['max_messageLength']) && $_POST['max_messageLength'] != $modSettings['max_messageLength'] && $db_type == 'mysql')
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
		redirectexit('action=admin;area=postsettings;sa=posts');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=posts';
	$context['settings_title'] = $txt['manageposts_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * Set a few Bulletin Board Code settings. It loads a list of Bulletin Board Code tags to allow disabling tags.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=bbc.
 *
 * @param bool $return_config = false
 * @uses Admin template, edit_bbc_settings sub-template.
 */
function ModifyBBCSettings($return_config = false)
{
	global $context, $txt, $modSettings, $helptxt, $scripturl, $sourcedir;

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
		redirectexit('action=admin;area=postsettings;sa=bbc');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=bbc';
	$context['settings_title'] = $txt['manageposts_bbc_settings_title'];

	prepareDBSettingContext($config_vars);
}

/**
 * Modify any setting related to topics.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=topics.

 * @param bool $return_config = false
 * @uses Admin template, edit_topic_settings sub-template.
 */
function ModifyTopicSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sourcedir, $scripturl;

	// Here are all the topic settings.
	$config_vars = array(
			// Some simple bools...
			array('check', 'enableStickyTopics'),
			array('check', 'enableParticipation'),
		'',
			// Pagination etc...
			array('int', 'oldTopicDays', 'postinput' => $txt['manageposts_days'], 'subtext' => $txt['oldTopicDays_zero']),
			array('int', 'defaultMaxTopics', 'postinput' => $txt['manageposts_topics']),
			array('int', 'defaultMaxMessages', 'postinput' => $txt['manageposts_posts']),
			array('check', 'disable_print_topic'),
		'',
			// Hot topics (etc)...
			array('int', 'hotTopicPosts', 'postinput' => $txt['manageposts_posts']),
			array('int', 'hotTopicVeryPosts', 'postinput' => $txt['manageposts_posts']),
		'',
			// All, next/prev...
			array('int', 'enableAllMessages', 'postinput' => $txt['manageposts_posts'], 'subtext' => $txt['enableAllMessages_zero']),
			array('check', 'disableCustomPerPage'),
			array('check', 'enablePreviousNext'),

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
		redirectexit('action=admin;area=postsettings;sa=topics');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
	$context['settings_title'] = $txt['manageposts_topic_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

?>