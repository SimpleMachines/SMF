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
class_exists('\\SMF\\Actions\\Profile\\Notification');
class_exists('\\SMF\\Actions\\Profile\\TFADisable');
class_exists('\\SMF\\Actions\\Profile\\TFASetup');
class_exists('\\SMF\\Actions\\Profile\\ThemeOptions');

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