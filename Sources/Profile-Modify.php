<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * 	It also allows the user to change some of their or another's preferences,
 * 	and such things
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Alert;
use SMF\Attachment;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Category;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Msg;
use SMF\Mail;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Notify;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\TOTP\Auth as Tfa;

if (!defined('SMF'))
	die('No direct access...');

// Some functions that used to be in this file have been moved.
class_exists('\\SMF\\Alert');
class_exists('\\SMF\\Profile');
class_exists('\\SMF\\Actions\\Profile\\Account');
class_exists('\\SMF\\Actions\\Profile\\ForumProfile');
class_exists('\\SMF\\Actions\\Profile\\TFADisable');
class_exists('\\SMF\\Actions\\Profile\\TFASetup');

/**
 * Make any notification changes that need to be made.
 *
 * @param int $memID The ID of the member
 */
function makeNotificationChanges($memID)
{
	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards']))
	{
		// Make sure only integers are deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications.
		$_POST['notify_boards'] = array_diff($_POST['notify_boards'], array(0));

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:selected_member}',
			array(
				'board_list' => $_POST['notify_boards'],
				'selected_member' => $memID,
			)
		);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		foreach ($_POST['notify_topics'] as $index => $id)
			$_POST['notify_topics'][$index] = (int) $id;

		// Make sure there are no zeros left.
		$_POST['notify_topics'] = array_diff($_POST['notify_topics'], array(0));

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			array(
				'topic_list' => $_POST['notify_topics'],
				'selected_member' => $memID,
			)
		);
		foreach ($_POST['notify_topics'] as $topic)
			Notify::setNotifyPrefs((int) $memID, array('topic_notify_' . $topic => 0));
	}

	// We are removing topic preferences
	elseif (isset($_POST['remove_notify_topics']) && !empty($_POST['notify_topics']))
	{
		$prefs = array();
		foreach ($_POST['notify_topics'] as $topic)
			$prefs[] = 'topic_notify_' . $topic;
		Notify::deleteNotifyPrefs($memID, $prefs);
	}

	// We are removing board preferences
	elseif (isset($_POST['remove_notify_board']) && !empty($_POST['notify_boards']))
	{
		$prefs = array();
		foreach ($_POST['notify_boards'] as $board)
			$prefs[] = 'board_notify_' . $board;
		Notify::deleteNotifyPrefs($memID, $prefs);
	}
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddyIgnoreLists($memID)
{
	// Do a quick check to ensure people aren't getting here illegally!
	if (!User::$me->is_owner || empty(Config::$modSettings['enable_buddylist']))
		fatal_lang_error('no_access', false);

	// Can we email the user direct?
	Utils::$context['can_moderate_forum'] = allowedTo('moderate_forum');
	Utils::$context['can_send_email'] = allowedTo('moderate_forum');

	$subActions = array(
		'buddies' => array('editBuddies', Lang::$txt['editBuddies']),
		'ignore' => array('editIgnoreList', Lang::$txt['editIgnoreList']),
	);

	Utils::$context['list_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

	// Create the tabs for the template.
	Menu::$loaded['profile']->tab_data = array(
		'title' => Lang::$txt['editBuddyIgnoreLists'],
		'description' => Lang::$txt['buddy_ignore_desc'],
		'icon_class' => 'main_icons profile_hd',
		'tabs' => array(
			'buddies' => array(),
			'ignore' => array(),
		),
	);

	Theme::loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');

	// Pass on to the actual function.
	Utils::$context['sub_template'] = $subActions[Utils::$context['list_area']][0];
	$call = call_helper($subActions[Utils::$context['list_area']][0], true);

	if (!empty($call))
		call_user_func($call, $memID);
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddies($memID)
{
	// For making changes!
	$buddiesArray = explode(',', User::$profiles[$memID]['buddy_list']);
	foreach ($buddiesArray as $k => $dummy)
		if ($dummy == '')
			unset($buddiesArray[$k]);

	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		call_integration_hook('integrate_remove_buddy', array($memID));

		$_SESSION['prf-save'] = Lang::$txt['could_not_remove_person'];

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
			{
				unset($buddiesArray[$key]);
				$_SESSION['prf-save'] = true;
			}

		// Make the changes.
		User::$profiles[$memID]['buddy_list'] = implode(',', $buddiesArray);
		User::updateMemberData($memID, array('buddy_list' => User::$profiles[$memID]['buddy_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}
	elseif (isset($_POST['new_buddy']))
	{
		checkSession();

		// Prepare the string for extraction...
		$_POST['new_buddy'] = strtr(Utils::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
		$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

		foreach ($new_buddies as $k => $dummy)
		{
			$new_buddies[$k] = strtr(trim($new_buddies[$k]), array('\'' => '&#039;'));

			if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array(User::$profiles[$memID]['member_name'], User::$profiles[$memID]['real_name'])))
				unset($new_buddies[$k]);
		}

		call_integration_hook('integrate_add_buddies', array($memID, &$new_buddies));

		$_SESSION['prf-save'] = Lang::$txt['could_not_add_person'];
		if (!empty($new_buddies))
		{
			// Now find out the id_member of the buddy.
			$request = Db::$db->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
				LIMIT {int:count_new_buddies}',
				array(
					'new_buddies' => $new_buddies,
					'count_new_buddies' => count($new_buddies),
				)
			);

			if (Db::$db->num_rows($request) != 0)
				$_SESSION['prf-save'] = true;

			// Add the new member to the buddies array.
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (in_array($row['id_member'], $buddiesArray))
					continue;
				else
					$buddiesArray[] = (int) $row['id_member'];
			}
			Db::$db->free_result($request);

			// Now update the current users buddy list.
			User::$profiles[$memID]['buddy_list'] = implode(',', $buddiesArray);
			User::updateMemberData($memID, array('buddy_list' => User::$profiles[$memID]['buddy_list']));
		}

		// Back to the buddy list!
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}

	// Get all the users "buddies"...
	$buddies = array();

	// Gotta load the custom profile fields names.
	$request = Db::$db->query('', '
		SELECT col_name, field_name, field_desc, field_type, field_options, show_mlist, bbc, enclose
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND private < {int:private_level}',
		array(
			'active' => 1,
			'private_level' => 2,
		)
	);

	Utils::$context['custom_pf'] = array();
	$disabled_fields = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : array();
	while ($row = Db::$db->fetch_assoc($request))
		if (!isset($disabled_fields[$row['col_name']]) && !empty($row['show_mlist']))
			Utils::$context['custom_pf'][$row['col_name']] = array(
				'label' => Lang::tokenTxtReplace($row['field_name']),
				'type' => $row['field_type'],
				'options' => !empty($row['field_options']) ? explode(',', $row['field_options']) : array(),
				'bbc' => !empty($row['bbc']),
				'enclose' => $row['enclose'],
			);

	Db::$db->free_result($request);

	if (!empty($buddiesArray))
	{
		$result = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			array(
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count(User::$profiles[$memID]['buddy_list'], ',') + 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($result))
			$buddies[] = $row['id_member'];
		Db::$db->free_result($result);
	}

	Utils::$context['buddy_count'] = count($buddies);

	// Load all the members up.
	User::load($buddies, User::LOAD_BY_ID, 'profile');

	// Setup the context for each buddy.
	Utils::$context['buddies'] = array();
	foreach ($buddies as $buddy)
	{
		Utils::$context['buddies'][$buddy] = User::$loaded[$buddy]->format();

		// Make sure to load the appropriate fields for each user
		if (!empty(Utils::$context['custom_pf']))
		{
			foreach (Utils::$context['custom_pf'] as $key => $column)
			{
				// Don't show anything if there isn't anything to show.
				if (!isset(Utils::$context['buddies'][$buddy]['options'][$key]))
				{
					Utils::$context['buddies'][$buddy]['options'][$key] = '';
					continue;
				}

				$currentKey = 0;
				if (!empty($column['options']))
				{
					foreach ($column['options'] as $k => $v)
					{
						if (empty($currentKey))
							$currentKey = $v == Utils::$context['buddies'][$buddy]['options'][$key] ? $k : 0;
					}
				}

				if ($column['bbc'] && !empty(Utils::$context['buddies'][$buddy]['options'][$key]))
					Utils::$context['buddies'][$buddy]['options'][$key] = strip_tags(BBCodeParser::load()->parse(Utils::$context['buddies'][$buddy]['options'][$key]));

				elseif ($column['type'] == 'check')
					Utils::$context['buddies'][$buddy]['options'][$key] = Utils::$context['buddies'][$buddy]['options'][$key] == 0 ? Lang::$txt['no'] : Lang::$txt['yes'];

				// Enclosing the user input within some other text?
				if (!empty($column['enclose']) && !empty(Utils::$context['buddies'][$buddy]['options'][$key]))
					Utils::$context['buddies'][$buddy]['options'][$key] = strtr($column['enclose'], array(
						'{SCRIPTURL}' => Config::$scripturl,
						'{IMAGES_URL}' => Theme::$current->settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
						'{KEY}' => $currentKey,
						'{INPUT}' => Lang::tokenTxtReplace(Utils::$context['buddies'][$buddy]['options'][$key]),
					));
			}
		}
	}

	if (isset($_SESSION['prf-save']))
	{
		if ($_SESSION['prf-save'] === true)
			Utils::$context['saved_successful'] = true;
		else
			Utils::$context['saved_failed'] = $_SESSION['prf-save'];

		unset($_SESSION['prf-save']);
	}

	call_integration_hook('integrate_view_buddies', array($memID));
}

/**
 * Allows the user to view their ignore list, as well as the option to manage members on it.
 *
 * @param int $memID The ID of the member
 */
function editIgnoreList($memID)
{
	// For making changes!
	$ignoreArray = explode(',', User::$profiles[$memID]['pm_ignore_list']);
	foreach ($ignoreArray as $k => $dummy)
		if ($dummy == '')
			unset($ignoreArray[$k]);

	// Removing a member from the ignore list?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		$_SESSION['prf-save'] = Lang::$txt['could_not_remove_person'];

		// Heh, I'm lazy, do it the easy way...
		foreach ($ignoreArray as $key => $id_remove)
			if ($id_remove == (int) $_GET['remove'])
			{
				unset($ignoreArray[$key]);
				$_SESSION['prf-save'] = true;
			}

		// Make the changes.
		User::$profiles[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
		User::updateMemberData($memID, array('pm_ignore_list' => User::$profiles[$memID]['pm_ignore_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}
	elseif (isset($_POST['new_ignore']))
	{
		checkSession();
		// Prepare the string for extraction...
		$_POST['new_ignore'] = strtr(Utils::htmlspecialchars($_POST['new_ignore'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_ignore'], $matches);
		$new_entries = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_ignore']))));

		foreach ($new_entries as $k => $dummy)
		{
			$new_entries[$k] = strtr(trim($new_entries[$k]), array('\'' => '&#039;'));

			if (strlen($new_entries[$k]) == 0 || in_array($new_entries[$k], array(User::$profiles[$memID]['member_name'], User::$profiles[$memID]['real_name'])))
				unset($new_entries[$k]);
		}

		$_SESSION['prf-save'] = Lang::$txt['could_not_add_person'];
		if (!empty($new_entries))
		{
			// Now find out the id_member for the members in question.
			$request = Db::$db->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_entries}) OR real_name IN ({array_string:new_entries})
				LIMIT {int:count_new_entries}',
				array(
					'new_entries' => $new_entries,
					'count_new_entries' => count($new_entries),
				)
			);

			if (Db::$db->num_rows($request) != 0)
				$_SESSION['prf-save'] = true;

			// Add the new member to the buddies array.
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (in_array($row['id_member'], $ignoreArray))
					continue;
				else
					$ignoreArray[] = (int) $row['id_member'];
			}
			Db::$db->free_result($request);

			// Now update the current users buddy list.
			User::$profiles[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			User::updateMemberData($memID, array('pm_ignore_list' => User::$profiles[$memID]['pm_ignore_list']));
		}

		// Back to the list of pityful people!
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}

	// Initialise the list of members we're ignoring.
	$ignored = array();

	if (!empty($ignoreArray))
	{
		$result = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ignore_list})
			ORDER BY real_name
			LIMIT {int:ignore_list_count}',
			array(
				'ignore_list' => $ignoreArray,
				'ignore_list_count' => substr_count(User::$profiles[$memID]['pm_ignore_list'], ',') + 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($result))
			$ignored[] = $row['id_member'];
		Db::$db->free_result($result);
	}

	Utils::$context['ignore_count'] = count($ignored);

	// Load all the members up.
	User::load($ignored, User::LOAD_BY_ID, 'profile');

	// Setup the context for each buddy.
	Utils::$context['ignore_list'] = array();
	foreach ($ignored as $ignore_member)
	{
		Utils::$context['ignore_list'][$ignore_member] = User::$loaded[$ignore_member]->format();
	}

	if (isset($_SESSION['prf-save']))
	{
		if ($_SESSION['prf-save'] === true)
			Utils::$context['saved_successful'] = true;
		else
			Utils::$context['saved_failed'] = $_SESSION['prf-save'];

		unset($_SESSION['prf-save']);
	}
}

/**
 * Handles the "Look and Layout" section of the profile
 *
 * @param int $memID The ID of the member
 */
function theme($memID)
{
	Theme::loadTemplate('Settings');
	Theme::loadSubTemplate('options');

	// Let mods hook into the theme options.
	call_integration_hook('integrate_theme_options');

	Profile::$member->loadThemeOptions();

	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
	{
		Profile::$member->loadCustomFields('theme');
	}

	Utils::$context['sub_template'] = 'edit_options';
	Utils::$context['page_desc'] = Lang::$txt['theme_info'];

	Profile::$member->setupContext(
		array(
			'id_theme', 'smiley_set', 'hr',
			'time_format', 'timezone', 'hr',
			'theme_settings',
		),
	);
}

/**
 * Display the notifications and settings for changes.
 *
 * @param int $memID The ID of the member
 */
function notification($memID)
{
	// Going to want this for consistency.
	Theme::loadCSSFile('admin.css', array(), 'smf_admin');

	// This is just a bootstrap for everything else.
	$sa = array(
		'alerts' => 'alert_configuration',
		'markread' => 'alert_markread',
		'topics' => 'alert_notifications_topics',
		'boards' => 'alert_notifications_boards',
	);

	$subAction = !empty($_GET['sa']) && isset($sa[$_GET['sa']]) ? $_GET['sa'] : 'alerts';

	Utils::$context['sub_template'] = $sa[$subAction];
	Menu::$loaded['profile']->tab_data = array(
		'title' => Lang::$txt['notification'],
		'help' => '',
		'description' => Lang::$txt['notification_info'],
	);
	$sa[$subAction]($memID);
}

/**
 * Handles configuration of alert preferences
 *
 * @param int $memID The ID of the member
 * @param bool $defaultSettings If true, we are loading default options.
 */
function alert_configuration($memID, $defaultSettings = false)
{
	if (!isset(Utils::$context['token_check']))
		Utils::$context['token_check'] = 'profile-nt' . $memID;

	is_not_guest();
	if (!User::$me->is_owner)
		isAllowedTo('profile_extra_any');

	// Set the post action if we're coming from the profile...
	if (!isset(Utils::$context['action']))
		Utils::$context['action'] = 'action=profile;area=notification;sa=alerts;u=' . $memID;

	// What options are set?
	Profile::$member->loadThemeOptions($defaultSettings);

	Theme::loadJavaScriptFile('alertSettings.js', array('minimize' => true), 'smf_alertSettings');

	// Now load all the values for this user.
	$prefs = Notify::getNotifyPrefs($memID, '', $memID != 0);

	Utils::$context['alert_prefs'] = !empty($prefs[$memID]) ? $prefs[$memID] : array();

	Utils::$context['member'] += array(
		'alert_timeout' => isset(Utils::$context['alert_prefs']['alert_timeout']) ? Utils::$context['alert_prefs']['alert_timeout'] : 10,
		'notify_announcements' => isset(Utils::$context['alert_prefs']['announcements']) ? Utils::$context['alert_prefs']['announcements'] : 0,
	);

	// Now for the exciting stuff.
	// We have groups of items, each item has both an alert and an email key as well as an optional help string.
	// Valid values for these keys are 'always', 'yes', 'never'; if using always or never you should add a help string.
	$alert_types = array(
		'board' => array(
			'topic_notify' => array('alert' => 'yes', 'email' => 'yes'),
			'board_notify' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'msg' => array(
			'msg_mention' => array('alert' => 'yes', 'email' => 'yes'),
			'msg_quote' => array('alert' => 'yes', 'email' => 'yes'),
			'msg_like' => array('alert' => 'yes', 'email' => 'never'),
			'unapproved_reply' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'pm' => array(
			'pm_new' => array('alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => array('name' => 'pm_read', 'is_board' => false)),
			'pm_reply' => array('alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => array('name' => 'pm_send', 'is_board' => false)),
		),
		'groupr' => array(
			'groupr_approved' => array('alert' => 'yes', 'email' => 'yes'),
			'groupr_rejected' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'moderation' => array(
			'unapproved_attachment' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'approve_posts', 'is_board' => true)),
			'unapproved_post' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'approve_posts', 'is_board' => true)),
			'msg_report' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_board', 'is_board' => true)),
			'msg_report_reply' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_board', 'is_board' => true)),
			'member_report' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
			'member_report_reply' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
		),
		'members' => array(
			'member_register' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
			'request_group' => array('alert' => 'yes', 'email' => 'yes'),
			'warn_any' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'issue_warning', 'is_board' => false)),
			'buddy_request' => array('alert' => 'yes', 'email' => 'never'),
			'birthday' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'calendar' => array(
			'event_new' => array('alert' => 'yes', 'email' => 'yes', 'help' => 'alert_event_new'),
		),
		'paidsubs' => array(
			'paidsubs_expiring' => array('alert' => 'yes', 'email' => 'yes'),
		),
	);
	$group_options = array(
		'board' => array(
			array('check', 'msg_auto_notify', 'label' => 'after'),
			array(empty(Config::$modSettings['disallow_sendBody']) ? 'check' : 'hide', 'msg_receive_body', 'label' => 'after'),
			array('select', 'msg_notify_pref', 'label' => 'before', 'opts' => array(
				0 => Lang::$txt['alert_opt_msg_notify_pref_never'],
				1 => Lang::$txt['alert_opt_msg_notify_pref_instant'],
				2 => Lang::$txt['alert_opt_msg_notify_pref_first'],
				3 => Lang::$txt['alert_opt_msg_notify_pref_daily'],
				4 => Lang::$txt['alert_opt_msg_notify_pref_weekly'],
			)),
			array('select', 'msg_notify_type', 'label' => 'before', 'opts' => array(
				1 => Lang::$txt['notify_send_type_everything'],
				2 => Lang::$txt['notify_send_type_everything_own'],
				3 => Lang::$txt['notify_send_type_only_replies'],
				4 => Lang::$txt['notify_send_type_nothing'],
			)),
		),
		'pm' => array(
			array('select', 'pm_notify', 'label' => 'before', 'opts' => array(
				1 => Lang::$txt['email_notify_all'],
				2 => Lang::$txt['email_notify_buddies'],
			)),
		),
	);

	// There are certain things that are disabled at the group level.
	if (empty(Config::$modSettings['cal_enabled']))
		unset($alert_types['calendar']);

	// Disable paid subscriptions at group level if they're disabled
	if (empty(Config::$modSettings['paid_enabled']))
		unset($alert_types['paidsubs']);

	// Disable membergroup requests at group level if they're disabled
	if (empty(Config::$modSettings['show_group_membership']))
		unset($alert_types['groupr'], $alert_types['members']['request_group']);

	// Disable mentions if they're disabled
	if (empty(Config::$modSettings['enable_mentions']))
		unset($alert_types['msg']['msg_mention']);

	// Disable likes if they're disabled
	if (empty(Config::$modSettings['enable_likes']))
		unset($alert_types['msg']['msg_like']);

	// Disable buddy requests if they're disabled
	if (empty(Config::$modSettings['enable_buddylist']))
		unset($alert_types['members']['buddy_request']);

	// Now, now, we could pass this through global but we should really get into the habit of
	// passing content to hooks, not expecting hooks to splatter everything everywhere.
	call_integration_hook('integrate_alert_types', array(&$alert_types, &$group_options));

	// Now we have to do some permissions testing - but only if we're not loading this from the admin center
	if (!empty($memID))
	{
		require_once(Config::$sourcedir . '/Subs-Membergroups.php');
		$user_groups = explode(',', User::$profiles[$memID]['additional_groups']);
		$user_groups[] = User::$profiles[$memID]['id_group'];
		$user_groups[] = User::$profiles[$memID]['id_post_group'];
		$group_permissions = array('manage_membergroups');
		$board_permissions = array();

		foreach ($alert_types as $group => $items)
			foreach ($items as $alert_key => $alert_value)
				if (isset($alert_value['permission']))
				{
					if (empty($alert_value['permission']['is_board']))
						$group_permissions[] = $alert_value['permission']['name'];
					else
						$board_permissions[] = $alert_value['permission']['name'];
				}
		$member_groups = getGroupsWithPermissions($group_permissions, $board_permissions);

		if (empty($member_groups['manage_membergroups']['allowed']))
		{
			$request = Db::$db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}group_moderators
				WHERE id_member = {int:memID}',
				array(
					'memID' => $memID,
				)
			);

			list ($is_group_moderator) = Db::$db->fetch_row($request);

			if (empty($is_group_moderator))
				unset($alert_types['members']['request_group']);
		}

		foreach ($alert_types as $group => $items)
		{
			foreach ($items as $alert_key => $alert_value)
			{
				if (isset($alert_value['permission']))
				{
					$allowed = count(array_intersect($user_groups, $member_groups[$alert_value['permission']['name']]['allowed'])) != 0;

					if (!$allowed)
						unset($alert_types[$group][$alert_key]);
				}
			}

			if (empty($alert_types[$group]))
				unset($alert_types[$group]);
		}
	}

	// And finally, exporting it to be useful later.
	Utils::$context['alert_types'] = $alert_types;
	Utils::$context['alert_group_options'] = $group_options;

	Utils::$context['alert_bits'] = array(
		'alert' => 0x01,
		'email' => 0x02,
	);

	if (isset($_POST['notify_submit']))
	{
		checkSession();
		validateToken(Utils::$context['token_check'], 'post');

		// We need to step through the list of valid settings and figure out what the user has set.
		$update_prefs = array();

		// Now the group level options
		foreach (Utils::$context['alert_group_options'] as $opt_group => $group)
		{
			foreach ($group as $this_option)
			{
				switch ($this_option[0])
				{
					case 'check':
						$update_prefs[$this_option[1]] = !empty($_POST['opt_' . $this_option[1]]) ? 1 : 0;
						break;
					case 'select':
						if (isset($_POST['opt_' . $this_option[1]], $this_option['opts'][$_POST['opt_' . $this_option[1]]]))
							$update_prefs[$this_option[1]] = $_POST['opt_' . $this_option[1]];
						else
						{
							// We didn't have a sane value. Let's grab the first item from the possibles.
							$keys = array_keys($this_option['opts']);
							$first = array_shift($keys);
							$update_prefs[$this_option[1]] = $first;
						}
						break;
				}
			}
		}

		// Now the individual options
		foreach (Utils::$context['alert_types'] as $alert_group => $items)
		{
			foreach ($items as $item_key => $this_options)
			{
				$this_value = 0;
				foreach (Utils::$context['alert_bits'] as $type => $bitvalue)
				{
					if ($this_options[$type] == 'yes' && !empty($_POST[$type . '_' . $item_key]) || $this_options[$type] == 'always')
						$this_value |= $bitvalue;
				}

				$update_prefs[$item_key] = $this_value;
			}
		}

		if (isset($_POST['opt_alert_timeout']))
			$update_prefs['alert_timeout'] = Utils::$context['member']['alert_timeout'] = (int) $_POST['opt_alert_timeout'];
		else
			$update_prefs['alert_timeout'] = Utils::$context['alert_prefs']['alert_timeout'];

		if (isset($_POST['notify_announcements']))
			$update_prefs['announcements'] = Utils::$context['member']['notify_announcements'] = (int) $_POST['notify_announcements'];
		else
			$update_prefs['announcements'] = Utils::$context['alert_prefs']['announcements'];

		$update_prefs['announcements'] = !empty($update_prefs['announcements']) ? 2 : 0;

		Notify::setNotifyPrefs((int) $memID, $update_prefs);
		foreach ($update_prefs as $pref => $value)
			Utils::$context['alert_prefs'][$pref] = $value;

		makeNotificationChanges($memID);

		Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
	}

	createToken(Utils::$context['token_check'], 'post');
}

/**
 * Marks all alerts as read for the specified user
 *
 * @param int $memID The ID of the member
 */
function alert_markread($memID)
{
	// We do not want to output debug information here.
	Config::$db_show_debug = false;

	// We only want to output our little layer here.
	Utils::$context['template_layers'] = array();
	Utils::$context['sub_template'] = 'alerts_all_read';

	Lang::load('Alerts');

	// Now we're all set up.
	is_not_guest();

	if (!User::$me->is_owner)
		fatal_error('no_access');

	checkSession('get');

	Alert::markAll($memID, true);
}

/**
 * Handles alerts related to topics and posts
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_topics($memID)
{
	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_topics']) || isset($_POST['remove_notify_topics']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
	}

	// Now set up for the token check.
	Utils::$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken(Utils::$context['token_check'], 'post');

	// Do the topic notifications.
	$listOptions = array(
		'id' => 'topic_notification_list',
		'width' => '100%',
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['notifications_topics_none'] . '<br><br>' . Lang::$txt['notifications_topics_howto'],
		'no_items_align' => 'left',
		'base_href' => Config::$scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=topics',
		'default_sort_col' => 'last_post',
		'get_items' => array(
			'function' => 'list_getTopicNotifications',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getTopicNotificationCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => Lang::$txt['notifications_topics'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($topic)
					{
						$link = $topic['link'];

						if ($topic['new'])
							$link .= ' <a href="' . $topic['new_href'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>';

						$link .= '<br><span class="smalltext"><em>' . Lang::$txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

						return $link;
					},
				),
				'sort' => array(
					'default' => 'ms.subject',
					'reverse' => 'ms.subject DESC',
				),
			),
			'started_by' => array(
				'header' => array(
					'value' => Lang::$txt['started_by'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'poster_link',
				),
				'sort' => array(
					'default' => 'real_name_col',
					'reverse' => 'real_name_col DESC',
				),
			),
			'last_post' => array(
				'header' => array(
					'value' => Lang::$txt['last_post'],
					'class' => 'lefttext',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<span class="smalltext">%1$s<br>' . Lang::$txt['by'] . ' %2$s</span>',
						'params' => array(
							'updated' => false,
							'poster_updated_link' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'ml.id_msg DESC',
					'reverse' => 'ml.id_msg',
				),
			),
			'alert_pref' => array(
				'header' => array(
					'value' => Lang::$txt['notify_what_how'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($topic)
					{
						$pref = $topic['notify_pref'];
						$mode = !empty($topic['unwatched']) ? 0 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
						return Lang::$txt['notify_topic_' . $mode];
					},
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=profile;area=notification;sa=topics',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => Utils::$context['menu_item_selected'],
				Utils::$context['session_var'] => Utils::$context['session_id'],
			),
			'token' => Utils::$context['token_check'],
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_topics" value="' . Lang::$txt['notifications_update'] . '" class="button" />
							<input type="submit" name="remove_notify_topics" value="' . Lang::$txt['notification_remove_pref'] . '" class="button" />',
				'class' => 'floatright',
			),
		),
	);

	// Create the notification list.
	new ItemList($listOptions);
}

/**
 * Handles preferences related to board-level notifications
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_boards($memID)
{
	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_boards']) || isset($_POSt['remove_notify_boards']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
	}

	// Now set up for the token check.
	Utils::$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken(Utils::$context['token_check'], 'post');

	// Fine, start with the board list.
	$listOptions = array(
		'id' => 'board_notification_list',
		'width' => '100%',
		'no_items_label' => Lang::$txt['notifications_boards_none'] . '<br><br>' . Lang::$txt['notifications_boards_howto'],
		'no_items_align' => 'left',
		'base_href' => Config::$scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=boards',
		'default_sort_col' => 'board_name',
		'get_items' => array(
			'function' => 'list_getBoardNotifications',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'board_name' => array(
				'header' => array(
					'value' => Lang::$txt['notifications_boards'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($board)
					{
						$link = $board['link'];

						if ($board['new'])
							$link .= ' <a href="' . $board['href'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>';

						return $link;
					},
				),
				'sort' => array(
					'default' => 'name',
					'reverse' => 'name DESC',
				),
			),
			'alert_pref' => array(
				'header' => array(
					'value' => Lang::$txt['notify_what_how'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($board)
					{
						$pref = $board['notify_pref'];
						$mode = $pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1);
						return Lang::$txt['notify_board_' . $mode];
					},
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=profile;area=notification;sa=boards',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => Utils::$context['menu_item_selected'],
				Utils::$context['session_var'] => Utils::$context['session_id'],
			),
			'token' => Utils::$context['token_check'],
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_boards" value="' . Lang::$txt['notifications_update'] . '" class="button">
							<input type="submit" name="remove_notify_boards" value="' . Lang::$txt['notification_remove_pref'] . '" class="button" />',
				'class' => 'floatright',
			),
		),
	);

	// Create the board notification list.
	new ItemList($listOptions);
}

/**
 * Determines how many topics a user has requested notifications for
 *
 * @param int $memID The ID of the member
 * @return int The number of topic notifications for this user
 */
function list_getTopicNotificationCount($memID)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln' . (!Config::$modSettings['postmod_active'] && User::$me->query_see_board === '1=1' ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)') . '
		WHERE ln.id_member = {int:selected_member}' . (User::$me->query_see_topic_board === '1=1' ? '' : '
			AND {query_see_topic_board}') . (Config::$modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : ''),
		array(
			'selected_member' => $memID,
			'is_approved' => 1,
		)
	);
	list ($totalNotifications) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $totalNotifications;
}

/**
 * Gets information about all the topics a user has requested notifications for. Callback for the list in alert_notifications_topics
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to display on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the topics a user has subscribed to
 */
function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
{

	$prefs = Notify::getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : array();

	// All the topics with notification on...
	$request = Db::$db->query('', '
		SELECT
			COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from, b.id_board, b.name,
			t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
			ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
			COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
			lt.unwatched
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic' . (Config::$modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:items_per_page}',
		array(
			'current_member' => User::$me->id,
			'is_approved' => 1,
			'selected_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	$notification_topics = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		Lang::censorText($row['subject']);

		$notification_topics[] = array(
			'id' => $row['id_topic'],
			'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
			'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
			'subject' => $row['subject'],
			'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'updated' => timeformat($row['poster_time']),
			'new_href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'new_link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
			'board_link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'notify_pref' => isset($prefs['topic_notify_' . $row['id_topic']]) ? $prefs['topic_notify_' . $row['id_topic']] : (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
			'unwatched' => $row['unwatched'],
		);
	}
	Db::$db->free_result($request);

	return $notification_topics;
}

/**
 * Gets information about all the boards a user has requested notifications for. Callback for the list in alert_notifications_boards
 *
 * @param int $start Which item to start with (not used here)
 * @param int $items_per_page How many items to show on each page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about all the boards a user is subscribed to
 */
function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
{

	$prefs = Notify::getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : array();

	$request = Db::$db->query('', '
		SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY {raw:sort}',
		array(
			'current_member' => User::$me->id,
			'selected_member' => $memID,
			'sort' => $sort,
		)
	);
	$notification_boards = array();
	while ($row = Db::$db->fetch_assoc($request))
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'notify_pref' => isset($prefs['board_notify_' . $row['id_board']]) ? $prefs['board_notify_' . $row['id_board']] : (!empty($prefs['board_notify']) ? $prefs['board_notify'] : 0),
		);
	Db::$db->free_result($request);

	return $notification_boards;
}

/**
 * Handles the "ignored boards" section of the profile (if enabled)
 *
 * @param int $memID The ID of the member
 */
function ignoreboards($memID)
{
	// Have the admins enabled this option?
	if (empty(Config::$modSettings['allow_ignore_boards']))
		fatal_lang_error('ignoreboards_disallowed', 'user');

	// Find all the boards this user is allowed to see.
	$request = Db::$db->query('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			' . (!empty(User::$profiles[$memID]['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		array(
			'ignore_boards' => !empty(User::$profiles[$memID]['ignore_boards']) ? explode(',', User::$profiles[$memID]['ignore_boards']) : array(),
			'empty_string' => '',
		)
	);
	Utils::$context['num_boards'] = Db::$db->num_rows($request);
	Utils::$context['categories'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// This category hasn't been set up yet..
		if (!isset(Utils::$context['categories'][$row['id_cat']]))
			Utils::$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child.  (indent them..)
		Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => $row['is_ignored'],
		);
	}
	Db::$db->free_result($request);

	Category::sort(Utils::$context['categories']);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach (Utils::$context['categories'] as $category)
	{
		// Include a list of boards per category for easy toggling.
		Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	Utils::$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		Utils::$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			Utils::$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			Utils::$context['board_columns'][] = array();
	}

	Profile::$member->loadThemeOptions();
}

/**
 * Function to allow the user to choose group membership etc...
 *
 * @param int $memID The ID of the member
 */
function groupMembership($memID)
{
	$curMember = User::$profiles[$memID];
	Utils::$context['primary_group'] = $curMember['id_group'];

	// Can they manage groups?
	Utils::$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	Utils::$context['can_manage_protected'] = allowedTo('admin_forum');
	Utils::$context['can_edit_primary'] = Utils::$context['can_manage_protected'];
	Utils::$context['update_message'] = isset($_GET['msg']) && isset(Lang::$txt['group_membership_msg_' . $_GET['msg']]) ? Lang::$txt['group_membership_msg_' . $_GET['msg']] : '';

	// Get all the groups this user is a member of.
	$groups = explode(',', $curMember['additional_groups']);
	$groups[] = $curMember['id_group'];

	// Ensure the query doesn't croak!
	if (empty($groups))
		$groups = array(0);
	// Just to be sure...
	foreach ($groups as $k => $v)
		$groups[$k] = (int) $v;

	// Get all the membergroups they can join.
	$request = Db::$db->query('', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			COALESCE(lgr.id_member, 0) AS pending
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group AND lgr.status = {int:status_open})
		WHERE (mg.id_group IN ({array_int:group_list})
			OR mg.group_type > {int:nonjoin_group_id})
			AND mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
		ORDER BY group_name',
		array(
			'group_list' => $groups,
			'selected_member' => $memID,
			'status_open' => 0,
			'nonjoin_group_id' => 1,
			'min_posts' => -1,
			'moderator_group' => 3,
		)
	);
	// This beast will be our group holder.
	Utils::$context['groups'] = array(
		'member' => array(),
		'available' => array()
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Can they edit their primary group?
		if (($row['id_group'] == Utils::$context['primary_group'] && $row['group_type'] > 1) || ($row['hidden'] != 2 && Utils::$context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
			Utils::$context['can_edit_primary'] = true;

		// If they can't manage (protected) groups, and it's not publicly joinable or already assigned, they can't see it.
		if (((!Utils::$context['can_manage_protected'] && $row['group_type'] == 1) || (!Utils::$context['can_manage_membergroups'] && $row['group_type'] == 0)) && $row['id_group'] != Utils::$context['primary_group'])
			continue;

		Utils::$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'pending' => $row['pending'],
			'is_primary' => $row['id_group'] == Utils::$context['primary_group'],
			'can_be_primary' => $row['hidden'] != 2,
			// Anything more than this needs to be done through account settings for security.
			'can_leave' => $row['id_group'] != 1 && $row['group_type'] > 1 ? true : false,
		);
	}
	Db::$db->free_result($request);

	// Add registered members on the end.
	Utils::$context['groups']['member'][0] = array(
		'id' => 0,
		'name' => Lang::$txt['regular_members'],
		'desc' => Lang::$txt['regular_members_desc'],
		'type' => 0,
		'is_primary' => Utils::$context['primary_group'] == 0 ? true : false,
		'can_be_primary' => true,
		'can_leave' => 0,
	);

	// No changing primary one unless you have enough groups!
	if (count(Utils::$context['groups']['member']) < 2)
		Utils::$context['can_edit_primary'] = false;

	// In the special case that someone is requesting membership of a group, setup some special context vars.
	if (isset($_REQUEST['request']) && isset(Utils::$context['groups']['available'][(int) $_REQUEST['request']]) && Utils::$context['groups']['available'][(int) $_REQUEST['request']]['type'] == 2)
		Utils::$context['group_request'] = Utils::$context['groups']['available'][(int) $_REQUEST['request']];
}

/**
 * This function actually makes all the group changes
 *
 * @param array $profile_vars The profile variables
 * @param array $post_errors Any errors that have occurred
 * @param int $memID The ID of the member
 * @return string What type of change this is - 'primary' if changing the primary group, 'request' if requesting to join a group or 'free' if it's an open group
 */
function groupMembership2($profile_vars, $post_errors, $memID)
{
	// Let's be extra cautious...
	if (!User::$me->is_owner || empty(Config::$modSettings['show_group_membership']))
		isAllowedTo('manage_membergroups');
	if (!isset($_REQUEST['gid']) && !isset($_POST['primary']))
		fatal_lang_error('no_access', false);

	checkSession(isset($_GET['gid']) ? 'get' : 'post');

	Utils::$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	Utils::$context['can_manage_protected'] = allowedTo('admin_forum');

	// By default the new primary is the old one.
	$newPrimary = User::$profiles[$memID]['id_group'];
	$addGroups = array_flip(explode(',', User::$profiles[$memID]['additional_groups']));
	$canChangePrimary = User::$profiles[$memID]['id_group'] == 0 ? 1 : 0;
	$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

	// One way or another, we have a target group in mind...
	$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];
	$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

	// Sanity check!!
	if ($group_id == 1)
		isAllowedTo('admin_forum');
	// Protected groups too!
	else
	{
		$request = Db::$db->query('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group_id,
				'limit' => 1,
			)
		);
		list ($is_protected) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($is_protected == 1)
			isAllowedTo('admin_forum');
	}

	// What ever we are doing, we need to determine if changing primary is possible!
	$request = Db::$db->query('', '
		SELECT id_group, group_type, hidden, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({int:group_list}, {int:current_group})',
		array(
			'group_list' => $group_id,
			'current_group' => User::$profiles[$memID]['id_group'],
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Is this the new group?
		if ($row['id_group'] == $group_id)
		{
			$foundTarget = true;
			$group_name = $row['group_name'];

			// Does the group type match what we're doing - are we trying to request a non-requestable group?
			if ($changeType == 'request' && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);
			// What about leaving a requestable group we are not a member of?
			elseif ($changeType == 'free' && $row['group_type'] == 2 && User::$profiles[$memID]['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
				fatal_lang_error('no_access', false);
			elseif ($changeType == 'free' && $row['group_type'] != 3 && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);

			// We can't change the primary group if this is hidden!
			if ($row['hidden'] == 2)
				$canChangePrimary = false;
		}

		// If this is their old primary, can we change it?
		if ($row['id_group'] == User::$profiles[$memID]['id_group'] && ($row['group_type'] > 1 || Utils::$context['can_manage_membergroups']) && $canChangePrimary !== false)
			$canChangePrimary = 1;

		// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
		if ($changeType != 'primary' && User::$profiles[$memID]['id_group'] != 0)
			$canChangePrimary = false;

		// If this is the one we are acting on, can we even act?
		if ((!Utils::$context['can_manage_protected'] && $row['group_type'] == 1) || (!Utils::$context['can_manage_membergroups'] && $row['group_type'] == 0))
			$canChangePrimary = false;
	}
	Db::$db->free_result($request);

	// Didn't find the target?
	if (!$foundTarget)
		fatal_lang_error('no_access', false);

	// Final security check, don't allow users to promote themselves to admin.
	if (Utils::$context['can_manage_membergroups'] && !allowedTo('admin_forum'))
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}permissions
			WHERE id_group = {int:selected_group}
				AND permission = {string:admin_forum}
				AND add_deny = {int:not_denied}',
			array(
				'selected_group' => $group_id,
				'not_denied' => 1,
				'admin_forum' => 'admin_forum',
			)
		);
		list ($disallow) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($disallow)
			isAllowedTo('admin_forum');
	}

	// If we're requesting, add the note then return.
	if ($changeType == 'request')
	{
		$request = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND id_group = {int:selected_group}
				AND status = {int:status_open}',
			array(
				'selected_member' => $memID,
				'selected_group' => $group_id,
				'status_open' => 0,
			)
		);
		if (Db::$db->num_rows($request) != 0)
			fatal_lang_error('profile_error_already_requested_group');
		Db::$db->free_result($request);

		// Log the request.
		Db::$db->insert('',
			'{db_prefix}log_group_requests',
			array(
				'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
				'status' => 'int', 'id_member_acted' => 'int', 'member_name_acted' => 'string', 'time_acted' => 'int', 'act_reason' => 'string',
			),
			array(
				$memID, $group_id, time(), $_POST['reason'],
				0, 0, '', 0, '',
			),
			array('id_request')
		);

		// Set up some data for our background task...
		$data = Utils::jsonEncode(array('id_member' => $memID, 'member_name' => User::$me->name, 'id_group' => $group_id, 'group_name' => $group_name, 'reason' => $_POST['reason'], 'time' => time()));

		// Add a background task to handle notifying people of this request
		Db::$db->insert('insert', '{db_prefix}background_tasks',
			array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/GroupReq_Notify.php', 'SMF\Tasks\GroupReq_Notify', $data, 0), array()
		);

		return $changeType;
	}
	// Otherwise we are leaving/joining a group.
	elseif ($changeType == 'free')
	{
		// Are we leaving?
		if (User::$profiles[$memID]['id_group'] == $group_id || isset($addGroups[$group_id]))
		{
			if (User::$profiles[$memID]['id_group'] == $group_id)
				$newPrimary = 0;
			else
				unset($addGroups[$group_id]);
		}
		// ... if not, must be joining.
		else
		{
			// Can we change the primary, and do we want to?
			if ($canChangePrimary)
			{
				if (User::$profiles[$memID]['id_group'] != 0)
					$addGroups[User::$profiles[$memID]['id_group']] = -1;
				$newPrimary = $group_id;
			}
			// Otherwise it's an additional group...
			else
				$addGroups[$group_id] = -1;
		}
	}
	// Finally, we must be setting the primary.
	elseif ($canChangePrimary)
	{
		if (User::$profiles[$memID]['id_group'] != 0)
			$addGroups[User::$profiles[$memID]['id_group']] = -1;
		if (isset($addGroups[$group_id]))
			unset($addGroups[$group_id]);
		$newPrimary = $group_id;
	}

	// Finally, we can make the changes!
	foreach ($addGroups as $id => $dummy)
		if (empty($id))
			unset($addGroups[$id]);
	$addGroups = implode(',', array_flip($addGroups));

	// Ensure that we don't cache permissions if the group is changing.
	if (User::$me->is_owner)
		$_SESSION['mc']['time'] = 0;
	else
		Config::updateModSettings(array('settings_updated' => time()));

	User::updateMemberData($memID, array('id_group' => $newPrimary, 'additional_groups' => $addGroups));

	return $changeType;
}

?>