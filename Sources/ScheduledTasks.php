<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file is automatically called and handles all manner of scheduled things.

	void AutoTask()
		//!!!

	void scheduled_approval_notification()
		// !!!

	void scheduled_daily_maintenance()
		// !!!

	void scheduled_auto_optimize()
		// !!!

	void scheduled_daily_digest()
		// !!!

	void scheduled_weekly_digest()
		// !!!

	void scheduled_paid_subscriptions()
		// !!!

	void ReduceMailQueue(int number, bool override)
		// !!!

	void CalculateNextTrigger(array tasks)
		// !!!

	int next_time(int regularity, char unit, int offset)
		// !!!

	void loadEssentialThemeData()
		// !!!

	void scheduled_fetchSMfiles()
		// !!!

	void scheduled_birthdayemails()
		// !!!
*/

// This function works out what to do!
function AutoTask()
{
	global $time_start, $modSettings, $smcFunc;

	// Special case for doing the mail queue.
	if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
		ReduceMailQueue();
	else
	{
		// Select the next task to do.
		$request = $smcFunc['db_query']('', '
			SELECT id_task, task, next_time, time_offset, time_regularity, time_unit
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				AND next_time <= {int:current_time}
			ORDER BY next_time ASC
			LIMIT 1',
			array(
				'not_disabled' => 0,
				'current_time' => time(),
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
		{
			// The two important things really...
			$row = $smcFunc['db_fetch_assoc']($request);

			// When should this next be run?
			$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

			// How long in seconds it the gap?
			$duration = $row['time_regularity'];
			if ($row['time_unit'] == 'm')
				$duration *= 60;
			elseif ($row['time_unit'] == 'h')
				$duration *= 3600;
			elseif ($row['time_unit'] == 'd')
				$duration *= 86400;
			elseif ($row['time_unit'] == 'w')
				$duration *= 604800;

			// If we were really late running this task actually skip the next one.
			if (time() + ($duration / 2) > $next_time)
				$next_time += $duration;

			// Update it now, so no others run this!
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}scheduled_tasks
				SET next_time = {int:next_time}
				WHERE id_task = {int:id_task}
					AND next_time = {int:current_next_time}',
				array(
					'next_time' => $next_time,
					'id_task' => $row['id_task'],
					'current_next_time' => $row['next_time'],
				)
			);
			$affected_rows = $smcFunc['db_affected_rows']();

			// The function must exist or we are wasting our time, plus do some timestamp checking, and database check!
			if (function_exists('scheduled_' . $row['task']) && (!isset($_GET['ts']) || $_GET['ts'] == $row['next_time']) && $affected_rows)
			{
				ignore_user_abort(true);

				// Do the task...
				$completed = call_user_func('scheduled_' . $row['task']);

				// Log that we did it ;)
				if ($completed)
				{
					$total_time = round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3);
					$smcFunc['db_insert']('',
						'{db_prefix}log_scheduled_tasks',
						array(
							'id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float',
						),
						array(
							$row['id_task'], time(), (int) $total_time,
						),
						array()
					);
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// Get the next timestamp right.
		$request = $smcFunc['db_query']('', '
			SELECT next_time
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
			ORDER BY next_time ASC
			LIMIT 1',
			array(
				'not_disabled' => 0,
			)
		);
		// No new task scheduled yet?
		if ($smcFunc['db_num_rows']($request) === 0)
			$nextEvent = time() + 86400;
		else
			list ($nextEvent) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		updateSettings(array('next_task_time' => $nextEvent));
	}

	// Shall we return?
	if (!isset($_GET['scheduled']))
		return true;

	// Finally, send some stuff...
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Content-Type: image/gif');
	die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

// Function to sending out approval notices to moderators etc.
function scheduled_approval_notification()
{
	global $scripturl, $modSettings, $mbname, $txt, $sourcedir, $smcFunc;

	// Grab all the items awaiting approval and sort type then board - clear up any things that are no longer relevant.
	$request = $smcFunc['db_query']('', '
		SELECT aq.id_msg, aq.id_attach, aq.id_event, m.id_topic, m.id_board, m.subject, t.id_first_msg,
			b.id_profile
		FROM {db_prefix}approval_queue AS aq
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = aq.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)',
		array(
		)
	);
	$notices = array();
	$profiles = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If this is no longer around we'll ignore it.
		if (empty($row['id_topic']))
			continue;

		// What type is it?
		if ($row['id_first_msg'] && $row['id_first_msg'] == $row['id_msg'])
			$type = 'topic';
		elseif ($row['id_attach'])
			$type = 'attach';
		else
			$type = 'msg';

		// Add it to the array otherwise.
		$notices[$row['id_board']][$type][] = array(
			'subject' => $row['subject'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
		);

		// Store the profile for a bit later.
		$profiles[$row['id_board']] = $row['id_profile'];
	}
	$smcFunc['db_free_result']($request);

	// Delete it all!
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}approval_queue',
		array(
		)
	);

	// If nothing quit now.
	if (empty($notices))
		return true;

	// Now we need to think about finding out *who* can approve - this is hard!

	// First off, get all the groups with this permission and sort by board.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, id_profile, add_deny
		FROM {db_prefix}board_permissions
		WHERE permission = {string:approve_posts}
			AND id_profile IN ({array_int:profile_list})',
		array(
			'profile_list' => $profiles,
			'approve_posts' => 'approve_posts',
		)
	);
	$perms = array();
	$addGroups = array(1);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Sorry guys, but we have to ignore guests AND members - it would be too many otherwise.
		if ($row['id_group'] < 2)
			continue;

		$perms[$row['id_profile']][$row['add_deny'] ? 'add' : 'deny'][] = $row['id_group'];

		// Anyone who can access has to be considered.
		if ($row['add_deny'])
			$addGroups[] = $row['id_group'];
	}
	$smcFunc['db_free_result']($request);

	// Grab the moderators if they have permission!
	$mods = array();
	$members = array();
	if (in_array(2, $addGroups))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, id_board
			FROM {db_prefix}moderators',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$mods[$row['id_member']][$row['id_board']] = true;
			// Make sure they get included in the big loop.
			$members[] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);
	}

	// Come along one and all... until we reject you ;)
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, email_address, lngfile, id_group, additional_groups, mod_prefs
		FROM {db_prefix}members
		WHERE id_group IN ({array_int:additional_group_list})
			OR FIND_IN_SET({raw:additional_group_list_implode}, additional_groups) != 0' . (empty($members) ? '' : '
			OR id_member IN ({array_int:member_list})') . '
		ORDER BY lngfile',
		array(
			'additional_group_list' => $addGroups,
			'member_list' => $members,
			'additional_group_list_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $addGroups),
		)
	);
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Check whether they are interested.
		if (!empty($row['mod_prefs']))
		{
			list(,, $pref_binary) = explode('|', $row['mod_prefs']);
			if (!($pref_binary & 4))
				continue;
		}

		$members[$row['id_member']] = array(
			'id' => $row['id_member'],
			'groups' => array_merge(explode(',', $row['additional_groups']), array($row['id_group'])),
			'language' => $row['lngfile'],
			'email' => $row['email_address'],
			'name' => $row['real_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Get the mailing stuff.
	require_once($sourcedir . '/Subs-Post.php');
	// Need the below for loadLanguage to work!
	loadEssentialThemeData();

	// Finally, loop through each member, work out what they can do, and send it.
	foreach ($members as $id => $member)
	{
		$emailbody = '';

		// Load the language file as required.
		if (empty($current_language) || $current_language != $member['language'])
			$current_language = loadLanguage('EmailTemplates', $member['language'], false);

		// Loop through each notice...
		foreach ($notices as $board => $notice)
		{
			$access = false;

			// Can they mod in this board?
			if (isset($mods[$id][$board]))
				$access = true;

			// Do the group check...
			if (!$access && isset($perms[$profiles[$board]]['add']))
			{
				// They can access?!
				if (array_intersect($perms[$profiles[$board]]['add'], $member['groups']))
					$access = true;

				// If they have deny rights don't consider them!
				if (isset($perms[$profiles[$board]]['deny']))
					if (array_intersect($perms[$profiles[$board]]['deny'], $member['groups']))
						$access = false;
			}

			// Finally, fix it for admins!
			if (in_array(1, $member['groups']))
				$access = true;

			// If they can't access it then give it a break!
			if (!$access)
				continue;

			foreach ($notice as $type => $items)
			{
				// Build up the top of this section.
				$emailbody .= $txt['scheduled_approval_email_' . $type] . "\n" .
					'------------------------------------------------------' . "\n";

				foreach ($items as $item)
					$emailbody .= $item['subject'] . ' - ' . $item['href'] . "\n";

				$emailbody .= "\n";
			}
		}

		if ($emailbody == '')
			continue;

		$replacements = array(
			'REALNAME' => $member['name'],
			'BODY' => $emailbody,
		);

		$emaildata = loadEmailTemplate('scheduled_approval', $replacements, $current_language);

		// Send the actual email.
		sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
	}

	// All went well!
	return true;
}

// Do some daily cleaning up.
function scheduled_daily_maintenance()
{
	global $smcFunc, $modSettings, $sourcedir, $db_type;

	// First clean out the cache.
	clean_cache();

	// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
	list (, , $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);
	if ($modSettings['warning_decrement'])
	{
		// Find every member who has a warning level...
		$request = $smcFunc['db_query']('', '
			SELECT id_member, warning
			FROM {db_prefix}members
			WHERE warning > {int:no_warning}',
			array(
				'no_warning' => 0,
			)
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[$row['id_member']] = $row['warning'];
		$smcFunc['db_free_result']($request);

		// Have some members to check?
		if (!empty($members))
		{
			// Find out when they were last warned.
			$request = $smcFunc['db_query']('', '
				SELECT id_recipient, MAX(log_time) AS last_warning
				FROM {db_prefix}log_comments
				WHERE id_recipient IN ({array_int:member_list})
					AND comment_type = {string:warning}
				GROUP BY id_recipient',
				array(
					'member_list' => array_keys($members),
					'warning' => 'warning',
				)
			);
			$member_changes = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// More than 24 hours ago?
				if ($row['last_warning'] <= time() - 86400)
					$member_changes[] = array(
						'id' => $row['id_recipient'],
						'warning' => $members[$row['id_recipient']] >= $modSettings['warning_decrement'] ? $members[$row['id_recipient']] - $modSettings['warning_decrement'] : 0,
					);
			}
			$smcFunc['db_free_result']($request);

			// Have some members to change?
			if (!empty($member_changes))
				foreach ($member_changes as $change)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}members
						SET warning = {int:warning}
						WHERE id_member = {int:id_member}',
						array(
							'warning' => $change['warning'],
							'id_member' => $change['id'],
						)
					);
		}
	}

	// Do any spider stuff.
	if (!empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
	{
		require_once($sourcedir . '/ManageSearchEngines.php');
		consolidateSpiderStats();
	}

	// Check the database version - for some buggy MySQL version.
	$server_version = $smcFunc['db_server_info']();
	if ($db_type == 'mysql' && in_array(substr($server_version, 0, 6), array('5.0.50', '5.0.51')))
		updateSettings(array('db_mysql_group_by_fix' => '1'));
	elseif (!empty($modSettings['db_mysql_group_by_fix']))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:mysql_fix}',
			array(
				'mysql_fix' => 'db_mysql_group_by_fix',
			)
		);

	// Regenerate the Diffie-Hellman keys if OpenID is enabled.
	if (!empty($modSettings['enableOpenID']))
	{
		require_once($sourcedir . '/Subs-OpenID.php');
		smf_openID_setup_DH(true);
	}
	elseif (!empty($modSettings['dh_keys']))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:dh_keys}',
			array(
				'dh_keys' => 'dh_keys',
			)
		);

	// Log we've done it...
	return true;
}

// Auto optimize the database?
function scheduled_auto_optimize()
{
	global $modSettings, $smcFunc, $db_prefix, $db_type;

	// By default do it now!
	$delay = false;

	// As a kind of hack, if the server load is too great delay, but only by a bit!
	if (!empty($modSettings['load_average']) && !empty($modSettings['loadavg_auto_opt']) && $modSettings['load_average'] >= $modSettings['loadavg_auto_opt'])
		$delay = true;

	// Otherwise are we restricting the number of people online for this?
	if (!empty($modSettings['autoOptMaxOnline']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_online',
			array(
			)
		);
		list ($dont_do_it) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ($dont_do_it > $modSettings['autoOptMaxOnline'])
			$delay = true;
	}

	// If we are gonna delay, do so now!
	if ($delay)
		return false;

	db_extend();

	// Get all the tables.
	$tables = $smcFunc['db_list_tables'](false, $db_prefix . '%');

	// Actually do the optimisation.
	if ($db_type == 'sqlite')
		$smcFunc['db_optimize_table']($table[0]);
	else
		foreach ($tables as $table)
			$smcFunc['db_optimize_table']($table);

	// Return for the log...
	return true;
}

// Send out a daily email of all subscribed topics.
function scheduled_daily_digest()
{
	global $is_weekly, $txt, $mbname, $scripturl, $sourcedir, $smcFunc, $context, $modSettings;

	// We'll want this...
	require_once($sourcedir . '/Subs-Post.php');
	loadEssentialThemeData();

	$is_weekly = !empty($is_weekly) ? 1 : 0;

	// Right - get all the notification data FIRST.
	$request = $smcFunc['db_query']('', '
		SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name, mem.notify_types,
			mem.lngfile, mem.id_member
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
		WHERE mem.notify_regularity = {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}',
		array(
			'empty_topic' => 0,
			'notify_regularity' => $is_weekly ? '3' : '2',
			'is_activated' => 1,
		)
	);
	$members = array();
	$langs = array();
	$notify = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($members[$row['id_member']]))
		{
			$members[$row['id_member']] = array(
				'email' => $row['email_address'],
				'name' => $row['member_name'],
				'id' => $row['id_member'],
				'notifyMod' => $row['notify_types'] < 3 ? true : false,
				'lang' => $row['lngfile'],
			);
			$langs[$row['lngfile']] = $row['lngfile'];
		}

		// Store this useful data!
		$boards[$row['id_board']] = $row['id_board'];
		if ($row['id_topic'])
			$notify['topics'][$row['id_topic']][] = $row['id_member'];
		else
			$notify['boards'][$row['id_board']][] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	if (empty($boards))
		return true;

	// Just get the board names.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $boards,
		)
	);
	$boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[$row['id_board']] = $row['name'];
	$smcFunc['db_free_result']($request);

	if (empty($boards))
		return true;

	// Get the actual topics...
	$request = $smcFunc['db_query']('', '
		SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject,
			b.name AS board_name
		FROM {db_prefix}log_digest AS ld
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
				AND t.id_board IN ({array_int:board_list}))
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ' . ($is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
		array(
			'board_list' => array_keys($boards),
			'daily_value' => 2,
		)
	);
	$types = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($types[$row['note_type']][$row['id_board']]))
			$types[$row['note_type']][$row['id_board']] = array(
				'lines' => array(),
				'name' => $row['board_name'],
				'id' => $row['id_board'],
			);

		if ($row['note_type'] == 'reply')
		{
			if (isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['count']++;
			else
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
					'count' => 1,
				);
		}
		elseif ($row['note_type'] == 'topic')
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
				);
		}
		else
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
					'starter' => $row['id_member_started'],
				);
		}

		$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array();
		if (!empty($notify['topics'][$row['id_topic']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);
		if (!empty($notify['boards'][$row['id_board']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
	}
	$smcFunc['db_free_result']($request);

	if (empty($types))
		return true;

	// Let's load all the languages into a cache thingy.
	$langtxt = array();
	foreach ($langs as $lang)
	{
		loadLanguage('Post', $lang);
		loadLanguage('index', $lang);
		loadLanguage('EmailTemplates', $lang);
		$langtxt[$lang] = array(
			'subject' => $txt['digest_subject_' . ($is_weekly ? 'weekly' : 'daily')],
			'char_set' => $txt['lang_character_set'],
			'intro' => sprintf($txt['digest_intro_' . ($is_weekly ? 'weekly' : 'daily')], $mbname),
			'new_topics' => $txt['digest_new_topics'],
			'topic_lines' => $txt['digest_new_topics_line'],
			'new_replies' => $txt['digest_new_replies'],
			'mod_actions' => $txt['digest_mod_actions'],
			'replies_one' => $txt['digest_new_replies_one'],
			'replies_many' => $txt['digest_new_replies_many'],
			'sticky' => $txt['digest_mod_act_sticky'],
			'lock' => $txt['digest_mod_act_lock'],
			'unlock' => $txt['digest_mod_act_unlock'],
			'remove' => $txt['digest_mod_act_remove'],
			'move' => $txt['digest_mod_act_move'],
			'merge' => $txt['digest_mod_act_merge'],
			'split' => $txt['digest_mod_act_split'],
			'bye' => $txt['regards_team'],
		);
	}

	// Right - send out the silly things - this will take quite some space!
	$emails = array();
	foreach ($members as $mid => $member)
	{
		// Right character set!
		$context['character_set'] = empty($modSettings['global_character_set']) ? $langtxt[$lang]['char_set'] : $modSettings['global_character_set'];

		// Do the start stuff!
		$email = array(
			'subject' => $mbname . ' - ' . $langtxt[$lang]['subject'],
			'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . $scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
			'email' => $member['email'],
		);

		// All new topics?
		if (isset($types['topic']))
		{
			$titled = false;
			foreach ($types['topic'] as $id => $board)
				foreach ($board['lines'] as $topic)
					if (in_array($mid, $topic['members']))
					{
						if (!$titled)
						{
							$email['body'] .= "\n" . $langtxt[$lang]['new_topics'] . ':' . "\n" . '-----------------------------------------------';
							$titled = true;
						}
						$email['body'] .= "\n" . sprintf($langtxt[$lang]['topic_lines'], $topic['subject'], $board['name']);
					}
			if ($titled)
				$email['body'] .= "\n";
		}

		// What about replies?
		if (isset($types['reply']))
		{
			$titled = false;
			foreach ($types['reply'] as $id => $board)
				foreach ($board['lines'] as $topic)
					if (in_array($mid, $topic['members']))
					{
						if (!$titled)
						{
							$email['body'] .= "\n" . $langtxt[$lang]['new_replies'] . ':' . "\n" . '-----------------------------------------------';
							$titled = true;
						}
						$email['body'] .= "\n" . ($topic['count'] == 1 ? sprintf($langtxt[$lang]['replies_one'], $topic['subject']) : sprintf($langtxt[$lang]['replies_many'], $topic['count'], $topic['subject']));
					}

			if ($titled)
				$email['body'] .= "\n";
		}

		// Finally, moderation actions!
		$titled = false;
		foreach ($types as $note_type => $type)
		{
			if ($note_type == 'topic' || $note_type == 'reply')
				continue;

			foreach ($type as $id => $board)
				foreach ($board['lines'] as $topic)
					if (in_array($mid, $topic['members']))
					{
						if (!$titled)
						{
							$email['body'] .= "\n" . $langtxt[$lang]['mod_actions'] . ':' . "\n" . '-----------------------------------------------';
							$titled = true;
						}
						$email['body'] .= "\n" . sprintf($langtxt[$lang][$note_type], $topic['subject']);
					}

		}
		if ($titled)
			$email['body'] .= "\n";

		// Then just say our goodbyes!
		$email['body'] .= "\n\n" . $txt['regards_team'];

		// Send it - low priority!
		sendmail($email['email'], $email['subject'], $email['body'], null, null, false, 4);
	}

	// Clean up...
	if ($is_weekly)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily != {int:not_daily}',
			array(
				'not_daily' => 0,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:daily_value}
			WHERE daily = {int:not_daily}',
			array(
				'daily_value' => 2,
				'not_daily' => 0,
			)
		);
	}
	else
	{
		// Clear any only weekly ones, and stop us from sending daily again.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily = {int:daily_value}',
			array(
				'daily_value' => 2,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:both_value}
			WHERE daily = {int:no_value}',
			array(
				'both_value' => 1,
				'no_value' => 0,
			)
		);
	}

	// Just in case the member changes their settings mark this as sent.
	$members = array_keys($members);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_member IN ({array_int:member_list})',
		array(
			'member_list' => $members,
			'is_sent' => 1,
		)
	);

	// Log we've done it...
	return true;
}

// Like the daily stuff - just seven times less regular ;)
function scheduled_weekly_digest()
{
	global $is_weekly;

	// We just pass through to the daily function - avoid duplication!
	$is_weekly = true;
	return scheduled_daily_digest();
}

// Send a bunch of emails from the mail queue.
function ReduceMailQueue($number = false, $override_limit = false, $force_send = false)
{
	global $modSettings, $smcFunc, $sourcedir;

	// Are we intending another script to be sending out the queue?
	if (!empty($modSettings['mail_queue_use_cron']) && empty($force_send))
		return false;

	// By default send 5 at once.
	if (!$number)
		$number = empty($modSettings['mail_quantity']) ? 5 : $modSettings['mail_quantity'];

	// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
	if (isset($_GET['ts']) && $_GET['ts'] != $modSettings['mail_next_send'] && empty($force_send))
		return false;

	// By default move the next sending on by 10 seconds, and require an affected row.
	if (!$override_limit)
	{
		$delay = !empty($modSettings['mail_queue_delay']) ? $modSettings['mail_queue_delay'] : (!empty($modSettings['mail_limit']) && $modSettings['mail_limit'] < 5 ? 10 : 5);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:next_mail_send}
			WHERE variable = {string:mail_next_send}
				AND value = {string:last_send}',
			array(
				'next_mail_send' => time() + $delay,
				'mail_next_send' => 'mail_next_send',
				'last_send' => $modSettings['mail_next_send'],
			)
		);
		if ($smcFunc['db_affected_rows']() == 0)
			return false;
		$modSettings['mail_next_send'] = time() + $delay;
	}

	// If we're not overriding how many are we allow to send?
	if (!$override_limit && !empty($modSettings['mail_limit']))
	{
		list ($mt, $mn) = @explode('|', $modSettings['mail_recent']);

		// Nothing worth noting...
		if (empty($mn) || $mt < time() - 60)
		{
			$mt = time();
			$mn = $number;
		}
		// Otherwise we have a few more we can spend?
		elseif ($mn < $modSettings['mail_limit'])
		{
			$mn += $number;
		}
		// No more I'm afraid, return!
		else
			return false;

		// Reflect that we're about to send some, do it now to be safe.
		updateSettings(array('mail_recent' => $mt . '|' . $mn));
	}

	// Now we know how many we're sending, let's send them.
	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_mail, recipient, body, subject, headers, send_html
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT ' . $number,
		array(
		)
	);
	$ids = array();
	$emails = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We want to delete these from the database ASAP, so just get the data and go.
		$ids[] = $row['id_mail'];
		$emails[] = array(
			'to' => $row['recipient'],
			'body' => $row['body'],
			'subject' => $row['subject'],
			'headers' => $row['headers'],
			'send_html' => $row['send_html'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Delete, delete, delete!!!
	if (!empty($ids))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_list})',
			array(
				'mail_list' => $ids,
			)
		);

	// Don't believe we have any left?
	if (count($ids) < $number)
	{
		// Only update the setting if no-one else has beaten us to it.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:no_send}
			WHERE variable = {string:mail_next_send}
				AND value = {string:last_mail_send}',
			array(
				'no_send' => '0',
				'mail_next_send' => 'mail_next_send',
				'last_mail_send' => $modSettings['mail_next_send'],
			)
		);
	}

	if (empty($ids))
		return false;

	if (!empty($modSettings['mail_type']) && $modSettings['smtp_host'] != '')
		require_once($sourcedir . '/Subs-Post.php');

	// Send each email, yea!
	$failed_emails = array();
	foreach ($emails as $key => $email)
	{
		if (empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '')
		{
			$email['subject'] = strtr($email['subject'], array("\r" => '', "\n" => ''));
			if (!empty($modSettings['mail_strip_carriage']))
			{
				$email['body'] = strtr($email['body'], array("\r" => ''));
				$email['headers'] = strtr($email['headers'], array("\r" => ''));
			}

			// No point logging a specific error here, as we have no language. PHP error is helpful anyway...
			$result = mail(strtr($email['to'], array("\r" => '', "\n" => '')), $email['subject'], $email['body'], $email['headers']);

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
		else
			$result = smtp_mail(array($email['to']), $email['subject'], $email['body'], $email['send_html'] ? $email['headers'] : 'Mime-Version: 1.0' . "\r\n" . $email['headers']);

		// Hopefully it sent?
		if (!$result)
			$failed_emails[] = array($email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html']);
	}

	// Any emails that didn't send?
	if (!empty($failed_emails))
	{
		// Update the failed attempts check.
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mail_failed_attempts', empty($modSettings['mail_failed_attempts']) ? 1 : ++$modSettings['mail_failed_attempts']),
			array('variable')
		);

		// If we have failed to many times, tell mail to wait a bit and try again.
		if ($modSettings['mail_failed_attempts'] > 5)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}settings
				SET value = {string:mail_next_send}
				WHERE variable = {string:next_mail_send}
					AND value = {string:last_send}',
				array(
					'next_mail_send' => time() + 60,
					'mail_next_send' => 'mail_next_send',
					'last_send' => $modSettings['mail_next_send'],
			));

		// Add our email back to the queue, manually.
		$smcFunc['db_insert']('insert',
			'{db_prefix}mail_queue',
			array('recipient' => 'string', 'body' => 'string', 'subject' => 'string', 'headers' => 'string', 'send_html' => 'string'),
			$failed_emails,
			array('id_mail')
		);

		return false;
	}
	// We where unable to send the email, clear our failed attempts.
	elseif (!empty($modSettings['mail_failed_attempts']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:zero}
			WHERE variable = {string:mail_failed_attempts}',
			array(
				'zero' => '0',
				'mail_failed_attempts' => 'mail_failed_attempts',
		));

	// Had something to send...
	return true;
}

// Calculate the next time the passed tasks should be triggered.
function CalculateNextTrigger($tasks = array(), $forceUpdate = false)
{
	global $modSettings, $smcFunc;

	$task_query = '';
	if (!is_array($tasks))
		$tasks = array($tasks);

	// Actually have something passed?
	if (!empty($tasks))
	{
		if (!isset($tasks[0]) || is_numeric($tasks[0]))
			$task_query = ' AND id_task IN ({array_int:tasks})';
		else
			$task_query = ' AND task IN ({array_string:tasks})';
	}
	$nextTaskTime = empty($tasks) ? time() + 86400 : $modSettings['next_task_time'];

	// Get the critical info for the tasks.
	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit
		FROM {db_prefix}scheduled_tasks
		WHERE disabled = {int:no_disabled}
			' . $task_query,
		array(
			'no_disabled' => 0,
			'tasks' => $tasks,
		)
	);
	$tasks = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

		// Only bother moving the task if it's out of place or we're forcing it!
		if ($forceUpdate || $next_time < $row['next_time'] || $row['next_time'] < time())
			$tasks[$row['id_task']] = $next_time;
		else
			$next_time = $row['next_time'];

		// If this is sooner than the current next task, make this the next task.
		if ($next_time < $nextTaskTime)
			$nextTaskTime = $next_time;
	}
	$smcFunc['db_free_result']($request);

	// Now make the changes!
	foreach ($tasks as $id => $time)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET next_time = {int:next_time}
			WHERE id_task = {int:id_task}',
			array(
				'next_time' => $time,
				'id_task' => $id,
			)
		);

	// If the next task is now different update.
	if ($modSettings['next_task_time'] != $nextTaskTime)
		updateSettings(array('next_task_time' => $nextTaskTime));
}

// Simply returns a time stamp of the next instance of these time parameters.
function next_time($regularity, $unit, $offset)
{
	// Just in case!
	if ($regularity == 0)
		$regularity = 2;

	$curHour = date('H', time());
	$curMin = date('i', time());
	$next_time = 9999999999;

	// If the unit is minutes only check regularity in minutes.
	if ($unit == 'm')
	{
		$off = date('i', $offset);

		// If it's now just pretend it ain't,
		if ($off == $curMin)
			$next_time = time() + $regularity;
		else
		{
			// Make sure that the offset is always in the past.
			$off = $off > $curMin ? $off - 60 : $off;

			while ($off <= $curMin)
				$off += $regularity;

			// Now we know when the time should be!
			$next_time = time() + 60 * ($off - $curMin);
		}
	}
	// Otherwise, work out what the offset would be with todays date.
	else
	{
		$next_time = mktime(date('H', $offset), date('i', $offset), 0, date('m'), date('d'), date('Y'));

		// Make the time offset in the past!
		if ($next_time > time())
		{
			$next_time -= 86400;
		}

		// Default we'll jump in hours.
		$applyOffset = 3600;
		// 24 hours = 1 day.
		if ($unit == 'd')
			$applyOffset = 86400;
		// Otherwise a week.
		if ($unit == 'w')
			$applyOffset = 604800;

		$applyOffset *= $regularity;

		// Just add on the offset.
		while ($next_time <= time())
		{
			$next_time += $applyOffset;
		}
	}

	return $next_time;
}

// This loads the bare minimum data to allow us to load language files!
function loadEssentialThemeData()
{
	global $settings, $modSettings, $smcFunc, $mbname, $context, $sourcedir;

	// Get all the default theme variables.
	$result = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND id_theme IN (1, {int:theme_guests})',
		array(
			'no_member' => 0,
			'theme_guests' => $modSettings['theme_guests'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$settings[$row['variable']] = $row['value'];

		// Is this the default theme?
		if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1')
			$settings['default_' . $row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($result);

	// Check we have some directories setup.
	if (empty($settings['template_dirs']))
	{
		$settings['template_dirs'] = array($settings['theme_dir']);

		// Based on theme (if there is one).
		if (!empty($settings['base_theme_dir']))
			$settings['template_dirs'][] = $settings['base_theme_dir'];

		// Lastly the default theme.
		if ($settings['theme_dir'] != $settings['default_theme_dir'])
			$settings['template_dirs'][] = $settings['default_theme_dir'];
	}

	// Assume we want this.
	$context['forum_name'] = $mbname;

	// Check loadLanguage actually exists!
	if (!function_exists('loadLanguage'))
	{
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/Subs.php');
	}

	loadLanguage('index+Modifications');
}

function scheduled_fetchSMfiles()
{
	global $sourcedir, $txt, $language, $settings, $forum_version, $modSettings, $smcFunc;

	// What files do we want to get
	$request = $smcFunc['db_query']('', '
		SELECT id_file, filename, path, parameters
		FROM {db_prefix}admin_info_files',
		array(
		)
	);

	$js_files = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$js_files[$row['id_file']] = array(
			'filename' => $row['filename'],
			'path' => $row['path'],
			'parameters' => sprintf($row['parameters'], $language, urlencode($modSettings['time_format']), urlencode($forum_version)),
		);
	}

	$smcFunc['db_free_result']($request);

	// We're gonna need fetch_web_data() to pull this off.
	require_once($sourcedir . '/Subs-Package.php');

	// Just in case we run into a problem.
	loadEssentialThemeData();
	loadLanguage('Errors', $language, false);

	foreach ($js_files as $ID_FILE => $file)
	{
		// Create the url
		$server = empty($file['path']) || substr($file['path'], 0, 7) != 'http://' ? 'http://www.simplemachines.org' : '';
		$url = $server . (!empty($file['path']) ? $file['path'] : $file['path']) . $file['filename'] . (!empty($file['parameters']) ? '?' . $file['parameters'] : '');

		// Get the file
		$file_data = fetch_web_data($url);

		// If we got an error - give up - the site might be down.
		if ($file_data === false)
		{
			log_error(sprintf($txt['st_cannot_retrieve_file'], $url));
			return false;
		}

		// Save the file to the database.
		$smcFunc['db_query']('substring', '
			UPDATE {db_prefix}admin_info_files
			SET data = SUBSTRING({string:file_data}, 1, 65534)
			WHERE id_file = {int:id_file}',
			array(
				'id_file' => $ID_FILE,
				'file_data' => $file_data,
			)
		);
	}
	return true;
}

function scheduled_birthdayemails()
{
	global $modSettings, $sourcedir, $mbname, $txt, $smcFunc, $birthdayEmails;

	// Need this in order to load the language files.
	loadEssentialThemeData();

	// Going to need this to send the emails.
	require_once($sourcedir . '/Subs-Post.php');

	$greeting = isset($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday';

	// Get the month and day of today.
	$month = date('n'); // Month without leading zeros.
	$day = date('j'); // Day without leading zeros.

	// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
	$result = $smcFunc['db_query']('', '
		SELECT id_member, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE is_activated < 10
			AND MONTH(birthdate) = {int:month}
			AND DAYOFMONTH(birthdate) = {int:day}
			AND notify_announcements = {int:notify_announcements}
			AND YEAR(birthdate) > {int:year}',
		array(
			'notify_announcements' => 1,
			'year' => 1,
			'month' => $month,
			'day' => $day,
		)
	);

	// Group them by languages.
	$birthdays = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!isset($birthdays[$row['lngfile']]))
			$birthdays[$row['lngfile']] = array();
		$birthdays[$row['lngfile']][$row['id_member']] = array(
			'name' => $row['real_name'],
			'email' => $row['email_address']
		);
	}
	$smcFunc['db_free_result']($result);

	// Send out the greetings!
	foreach ($birthdays as $lang => $recps)
	{
		// We need to do some shuffling to make this work properly.
		loadLanguage('EmailTemplates', $lang);
		$txt['emails']['happy_birthday'] = $birthdayEmails[$greeting];

		foreach ($recps as $recp)
		{
			$replacements = array(
				'REALNAME' => $recp['name'],
			);

			$emaildata = loadEmailTemplate('happy_birthday', $replacements, $lang, false);

			sendmail($recp['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 4);

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

		}
	}

	// Flush the mail queue, just in case.
	AddMailQueue(true);

	return true;
}

function scheduled_weekly_maintenance()
{
	global $modSettings, $smcFunc;

	// Delete some settings that needn't be set if they are otherwise empty.
	$emptySettings = array(
		'warning_mute', 'warning_moderate', 'warning_watch', 'warning_show', 'disableCustomPerPage', 'spider_mode', 'spider_group',
		'paid_currency_code', 'paid_currency_symbol', 'paid_email_to', 'paid_email', 'paid_enabled', 'paypal_email',
		'search_enable_captcha', 'search_floodcontrol_time', 'show_spider_online',
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:setting_list})
			AND (value = {string:zero_value} OR value = {string:blank_value})',
		array(
			'zero_value' => '0',
			'blank_value' => '',
			'setting_list' => $emptySettings,
		)
	);

	// Some settings we never want to keep - they are just there for temporary purposes.
	$deleteAnywaySettings = array(
		'attachment_full_notified',
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:setting_list})',
		array(
			'setting_list' => $deleteAnywaySettings,
		)
	);

	// Ok should we prune the logs?
	if (!empty($modSettings['pruningOptions']))
	{
		if (!empty($modSettings['pruningOptions']) && strpos($modSettings['pruningOptions'], ',') !== false)
			list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = explode(',', $modSettings['pruningOptions']);

		if (!empty($modSettings['pruneErrorLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneErrorLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_errors
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty($modSettings['pruneModLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneModLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_actions
				WHERE log_time < {int:log_time}
					AND id_log = {int:moderation_log}',
				array(
					'log_time' => $t,
					'moderation_log' => 1,
				)
			);
		}

		if (!empty($modSettings['pruneBanLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneBanLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_banned
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty($modSettings['pruneReportLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneReportLog'] * 86400;

			// This one is more complex then the other logs.  First we need to figure out which reports are too old.
			$reports = array();
			$result = $smcFunc['db_query']('', '
				SELECT id_report
				FROM {db_prefix}log_reported
				WHERE time_started < {int:time_started}',
				array(
					'time_started' => $t,
				)
			);

			while ($row = $smcFunc['db_fetch_row']($result))
				$reports[] = $row[0];

			$smcFunc['db_free_result']($result);

			if (!empty($reports))
			{
				// Now delete the reports...
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_reported
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
				// And delete the comments for those reports...
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_reported_comments
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
			}
		}

		if (!empty($modSettings['pruneScheduledTaskLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneScheduledTaskLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_scheduled_tasks
				WHERE time_run < {int:time_run}',
				array(
					'time_run' => $t,
				)
			);
		}

		if (!empty($modSettings['pruneSpiderHitLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneSpiderHitLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_spider_hits
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}
	}

	// Get rid of any paid subscriptions that were never actioned.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_subscribed
		WHERE end_time = {int:no_end_time}
			AND status = {int:not_active}
			AND start_time < {int:start_time}
			AND payments_pending < {int:payments_pending}',
		array(
			'no_end_time' => 0,
			'not_active' => 0,
			'start_time' => time() - 60,
			'payments_pending' => 1,
		)
	);

	// Some OS's don't seem to clean out their sessions.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - 86400,
		)
	);

	return true;
}

// Perform the standard checks on expiring/near expiring subscriptions.
function scheduled_paid_subscriptions()
{
	global $txt, $sourcedir, $scripturl, $smcFunc, $modSettings, $language;

	// Start off by checking for removed subscriptions.
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, id_member
		FROM {db_prefix}log_subscribed
		WHERE status = {int:is_active}
			AND end_time < {int:time_now}',
		array(
			'is_active' => 1,
			'time_now' => time(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		require_once($sourcedir . '/ManagePaid.php');
		removeSubscription($row['id_subscribe'], $row['id_member']);
	}
	$smcFunc['db_free_result']($request);

	// Get all those about to expire that have not had a reminder sent.
	$request = $smcFunc['db_query']('', '
		SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
		FROM {db_prefix}log_subscribed AS ls
			INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
			INNER JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
		WHERE ls.status = {int:is_active}
			AND ls.reminder_sent = {int:reminder_sent}
			AND s.reminder > {int:reminder_wanted}
			AND ls.end_time < ({int:time_now} + s.reminder * 86400)',
		array(
			'is_active' => 1,
			'reminder_sent' => 0,
			'reminder_wanted' => 0,
			'time_now' => time(),
		)
	);
	$subs_reminded = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If this is the first one load the important bits.
		if (empty($subs_reminded))
		{
			require_once($sourcedir . '/Subs-Post.php');
			// Need the below for loadLanguage to work!
			loadEssentialThemeData();
		}

		$subs_reminded[] = $row['id_sublog'];

		$replacements = array(
			'PROFILE_LINK' => $scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
			'REALNAME' => $row['member_name'],
			'SUBSCRIPTION' => $row['name'],
			'END_DATE' => strip_tags(timeformat($row['end_time'])),
		);

		$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
	}
	$smcFunc['db_free_result']($request);

	// Mark the reminder as sent.
	if (!empty($subs_reminded))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET reminder_sent = {int:reminder_sent}
			WHERE id_sublog IN ({array_int:subscription_list})',
			array(
				'subscription_list' => $subs_reminded,
				'reminder_sent' => 1,
			)
		);

	return true;
}

?>