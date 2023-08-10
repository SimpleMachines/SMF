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
class_exists('\\SMF\\Actions\\Profile\\BuddyIgnoreLists');
class_exists('\\SMF\\Actions\\Profile\\ForumProfile');
class_exists('\\SMF\\Actions\\Profile\\IgnoreBoards');
class_exists('\\SMF\\Actions\\Profile\\Notification');
class_exists('\\SMF\\Actions\\Profile\\TFADisable');
class_exists('\\SMF\\Actions\\Profile\\TFASetup');
class_exists('\\SMF\\Actions\\Profile\\ThemeOptions');

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