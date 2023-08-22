<?php

/**
 * This file is automatically called and handles all manner of scheduled things.
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
use SMF\Config;
use SMF\Draft;
use SMF\ErrorHandler;
use SMF\ProxyServer;
use SMF\Lang;
use SMF\Mail;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Notify;
use SMF\Actions\Admin\SearchEngines;
use SMF\Actions\Admin\Subscriptions;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Mail');
class_exists('SMF\\Theme');

/**
 * This function works out what to do!
 */
function AutoTask()
{
	// We bail out of index.php too early for these to be called.
	Security::frameOptionsHeader();
	Security::corsPolicyHeader();

	// Requests from a CORS response may send a options to find if the request is valid.  Simply bail out here, the cors header have been sent already.
	if (isset($_SERVER['HTTP_X_SMF_AJAX']) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS')
	{
		send_http_status(204);
		die;
	}

	// Special case for doing the mail queue.
	if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
		Mail::reduceQueue();
	else
	{
		$task_string = '';

		// Select the next task to do.
		$request = Db::$db->query('', '
			SELECT id_task, task, next_time, time_offset, time_regularity, time_unit, callable
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
		if (Db::$db->num_rows($request) != 0)
		{
			// The two important things really...
			$row = Db::$db->fetch_assoc($request);

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
			Db::$db->query('', '
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
			$affected_rows = Db::$db->affected_rows();

			// What kind of task are we handling?
			if (!empty($row['callable']))
				$task_string = $row['callable'];

			// Default SMF task or old mods?
			elseif (function_exists('scheduled_' . $row['task']))
				$task_string = 'scheduled_' . $row['task'];

			// One last resource, the task name.
			elseif (!empty($row['task']))
				$task_string = $row['task'];

			// The function must exist or we are wasting our time, plus do some timestamp checking, and database check!
			if (!empty($task_string) && (!isset($_GET['ts']) || $_GET['ts'] == $row['next_time']) && $affected_rows)
			{
				ignore_user_abort(true);

				// Get the callable.
				$callable_task = call_helper($task_string, true);

				// Perform the task.
				if (!empty($callable_task))
					$completed = call_user_func($callable_task);

				else
					$completed = false;

				// Log that we did it ;)
				if ($completed)
				{
					$total_time = round(microtime(true) - TIME_START, 3);
					Db::$db->insert('',
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
		Db::$db->free_result($request);

		// Get the next timestamp right.
		$request = Db::$db->query('', '
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
		if (Db::$db->num_rows($request) === 0)
			$nextEvent = time() + 86400;
		else
			list ($nextEvent) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Config::updateModSettings(array('next_task_time' => $nextEvent));
	}

	// Shall we return?
	if (!isset($_GET['scheduled']))
		return true;

	// Finally, send some stuff...
	header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('content-type: image/gif');
	die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

/**
 * Do some daily cleaning up.
 */
function scheduled_daily_maintenance()
{
	// First clean out the cache.
	CacheApi::clean();

	// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
	list (, , Config::$modSettings['warning_decrement']) = explode(',', Config::$modSettings['warning_settings']);
	if (Config::$modSettings['warning_decrement'])
	{
		// Find every member who has a warning level...
		$request = Db::$db->query('', '
			SELECT id_member, warning
			FROM {db_prefix}members
			WHERE warning > {int:no_warning}',
			array(
				'no_warning' => 0,
			)
		);
		$members = array();
		while ($row = Db::$db->fetch_assoc($request))
			$members[$row['id_member']] = $row['warning'];
		Db::$db->free_result($request);

		// Have some members to check?
		if (!empty($members))
		{
			// Find out when they were last warned.
			$request = Db::$db->query('', '
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
			while ($row = Db::$db->fetch_assoc($request))
			{
				// More than 24 hours ago?
				if ($row['last_warning'] <= time() - 86400)
					$member_changes[] = array(
						'id' => $row['id_recipient'],
						'warning' => $members[$row['id_recipient']] >= Config::$modSettings['warning_decrement'] ? $members[$row['id_recipient']] - Config::$modSettings['warning_decrement'] : 0,
					);
			}
			Db::$db->free_result($request);

			// Have some members to change?
			if (!empty($member_changes))
				foreach ($member_changes as $change)
					Db::$db->query('', '
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
	if (!empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] > 1)
	{
		SearchEngines::consolidateSpiderStats();
	}

	// Clean up some old login history information.
	Db::$db->query('', '
		DELETE FROM {db_prefix}member_logins
		WHERE time < {int:oldLogins}',
		array(
			'oldLogins' => time() - (!empty(Config::$modSettings['loginHistoryDays']) ? 60 * 60 * 24 * Config::$modSettings['loginHistoryDays'] : 2592000),
		)
	);

	// Run Imageproxy housekeeping
	if (!empty(Config::$image_proxy_enabled))
	{
		$proxy = new ProxyServer();
		$proxy->housekeeping();
	}

	// Delete old profile exports
	if (!empty(Config::$modSettings['export_expiry']) && file_exists(Config::$modSettings['export_dir']) && is_dir(Config::$modSettings['export_dir']))
	{
		$expiry_date = round(TIME_START - Config::$modSettings['export_expiry'] * 86400);
		$export_files = glob(rtrim(Config::$modSettings['export_dir'], '/\\') . DIRECTORY_SEPARATOR . '*');

		foreach ($export_files as $export_file)
		{
			if (!in_array(basename($export_file), array('index.php', '.htaccess')) && filemtime($export_file) <= $expiry_date)
				@unlink($export_file);
		}
	}

	// Delete old alerts.
	if (!empty(Config::$modSettings['alerts_auto_purge']))
	{
		Alert::purge(-1, time() - 86400 * Config::$modSettings['alerts_auto_purge']);
	}

	// Anyone else have something to do?
	call_integration_hook('integrate_daily_maintenance');

	// Log we've done it...
	return true;
}

/**
 * Send out a daily email of all subscribed topics.
 */
function scheduled_daily_digest()
{
	global $is_weekly;

	Theme::loadEssential();

	$is_weekly = !empty($is_weekly) ? 1 : 0;

	// Right - get all the notification data FIRST.
	$request = Db::$db->query('', '
		SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name,
			mem.lngfile, mem.id_member
		FROM {db_prefix}log_notify AS ln
			JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
		WHERE mem.is_activated = {int:is_activated}',
		array(
			'empty_topic' => 0,
			'is_activated' => 1,
		)
	);
	$members = array();
	$langs = array();
	$notify = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		if (!isset($members[$row['id_member']]))
		{
			$members[$row['id_member']] = array(
				'email' => $row['email_address'],
				'name' => $row['member_name'],
				'id' => $row['id_member'],
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
	Db::$db->free_result($request);

	if (empty($boards))
		return true;

	// Just get the board names.
	$request = Db::$db->query('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $boards,
		)
	);
	$boards = array();
	while ($row = Db::$db->fetch_assoc($request))
		$boards[$row['id_board']] = $row['name'];
	Db::$db->free_result($request);

	if (empty($boards))
		return true;

	// Get the actual topics...
	$request = Db::$db->query('', '
		SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject,
			b.name AS board_name
		FROM {db_prefix}log_digest AS ld
			JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
				AND t.id_board IN ({array_int:board_list}))
			JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ' . ($is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
		array(
			'board_list' => array_keys($boards),
			'daily_value' => 2,
		)
	);
	$types = array();
	while ($row = Db::$db->fetch_assoc($request))
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
					'subject' => Utils::htmlspecialcharsDecode($row['subject']),
					'count' => 1,
				);
		}
		elseif ($row['note_type'] == 'topic')
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => Utils::htmlspecialcharsDecode($row['subject']),
				);
		}
		else
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => Utils::htmlspecialcharsDecode($row['subject']),
					'starter' => $row['id_member_started'],
				);
		}

		$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array();
		if (!empty($notify['topics'][$row['id_topic']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);
		if (!empty($notify['boards'][$row['id_board']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
	}
	Db::$db->free_result($request);

	if (empty($types))
		return true;

	// Let's load all the languages into a cache thingy.
	$langtxt = array();
	foreach ($langs as $lang)
	{
		Lang::load('Post', $lang);
		Lang::load('index', $lang);
		Lang::load('EmailTemplates', $lang);
		$langtxt[$lang] = array(
			'subject' => Lang::$txt['digest_subject_' . ($is_weekly ? 'weekly' : 'daily')],
			'char_set' => Lang::$txt['lang_character_set'],
			'intro' => sprintf(Lang::$txt['digest_intro_' . ($is_weekly ? 'weekly' : 'daily')], Config::$mbname),
			'new_topics' => Lang::$txt['digest_new_topics'],
			'topic_lines' => Lang::$txt['digest_new_topics_line'],
			'new_replies' => Lang::$txt['digest_new_replies'],
			'mod_actions' => Lang::$txt['digest_mod_actions'],
			'replies_one' => Lang::$txt['digest_new_replies_one'],
			'replies_many' => Lang::$txt['digest_new_replies_many'],
			'sticky' => Lang::$txt['digest_mod_act_sticky'],
			'lock' => Lang::$txt['digest_mod_act_lock'],
			'unlock' => Lang::$txt['digest_mod_act_unlock'],
			'remove' => Lang::$txt['digest_mod_act_remove'],
			'move' => Lang::$txt['digest_mod_act_move'],
			'merge' => Lang::$txt['digest_mod_act_merge'],
			'split' => Lang::$txt['digest_mod_act_split'],
			'bye' => sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']),
		);

		call_integration_hook('integrate_daily_digest_lang', array(&$langtxt, $lang));
	}

	// The preferred way...
	$prefs = Notify::getNotifyPrefs(array_keys($members), array('msg_notify_type', 'msg_notify_pref'), true);

	// Right - send out the silly things - this will take quite some space!
	$members_sent = array();
	foreach ($members as $mid => $member)
	{
		$frequency = isset($prefs[$mid]['msg_notify_pref']) ? $prefs[$mid]['msg_notify_pref'] : 0;
		$notify_types = !empty($prefs[$mid]['msg_notify_type']) ? $prefs[$mid]['msg_notify_type'] : 1;

		// Did they not elect to choose this?
		if ($frequency < 3 || $frequency == 4 && !$is_weekly || $frequency == 3 && $is_weekly || $notify_types == 4)
			continue;

		// Right character set!
		Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? $langtxt[$lang]['char_set'] : Config::$modSettings['global_character_set'];

		// Do the start stuff!
		$email = array(
			'subject' => Config::$mbname . ' - ' . $langtxt[$lang]['subject'],
			'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . Config::$scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
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
		if ($notify_types < 3)
		{
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
		}

		call_integration_hook('integrate_daily_digest_email', array(&$email, $types, $notify_types, $langtxt));

		if ($titled)
			$email['body'] .= "\n";

		// Then just say our goodbyes!
		$email['body'] .= "\n\n" . sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']);

		// Send it - low priority!
		Mail::send($email['email'], $email['subject'], $email['body'], null, 'digest', false, 4);

		$members_sent[] = $mid;
	}

	// Clean up...
	if ($is_weekly)
	{
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily != {int:not_daily}',
			array(
				'not_daily' => 0,
			)
		);
		Db::$db->query('', '
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
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily = {int:daily_value}',
			array(
				'daily_value' => 2,
			)
		);
		Db::$db->query('', '
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
	if (!empty($members_sent))
	{
		Db::$db->query('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $members_sent,
				'is_sent' => 1,
			)
		);
	}

	// Log we've done it...
	return true;
}

/**
 * Like the daily stuff - just seven times less regular ;)
 */
function scheduled_weekly_digest()
{
	global $is_weekly;

	// We just pass through to the daily function - avoid duplication!
	$is_weekly = true;
	return scheduled_daily_digest();
}

/**
 * Calculate the next time the passed tasks should be triggered.
 *
 * @param string|array $tasks The ID of a single task or an array of tasks
 * @param bool $forceUpdate Whether to force the tasks to run now
 */
function CalculateNextTrigger($tasks = array(), $forceUpdate = false)
{
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
	$nextTaskTime = empty($tasks) ? time() + 86400 : Config::$modSettings['next_task_time'];

	// Get the critical info for the tasks.
	$request = Db::$db->query('', '
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
	while ($row = Db::$db->fetch_assoc($request))
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
	Db::$db->free_result($request);

	// Now make the changes!
	foreach ($tasks as $id => $time)
		Db::$db->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET next_time = {int:next_time}
			WHERE id_task = {int:id_task}',
			array(
				'next_time' => $time,
				'id_task' => $id,
			)
		);

	// If the next task is now different update.
	if (Config::$modSettings['next_task_time'] != $nextTaskTime)
		Config::updateModSettings(array('next_task_time' => $nextTaskTime));
}

/**
 * Simply returns a time stamp of the next instance of these time parameters.
 *
 * @param int $regularity The regularity
 * @param string $unit What unit are we using - 'm' for minutes, 'd' for days, 'w' for weeks or anything else for seconds
 * @param int $offset The offset
 * @return int The timestamp for the specified time
 */
function next_time($regularity, $unit, $offset)
{
	// Just in case!
	if ($regularity == 0)
		$regularity = 2;

	$curMin = date('i', time());

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
	// Otherwise, work out what the offset would be with today's date.
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

/**
 * This retrieves data (e.g. last version of SMF) from sm.org
 */
function scheduled_fetchSMfiles()
{
	// What files do we want to get
	$request = Db::$db->query('', '
		SELECT id_file, filename, path, parameters
		FROM {db_prefix}admin_info_files',
		array(
		)
	);

	$js_files = array();

	while ($row = Db::$db->fetch_assoc($request))
	{
		$js_files[$row['id_file']] = array(
			'filename' => $row['filename'],
			'path' => $row['path'],
			'parameters' => sprintf($row['parameters'], Lang::$default, urlencode(Config::$modSettings['time_format']), urlencode(SMF_FULL_VERSION)),
		);
	}

	Db::$db->free_result($request);

	// Just in case we run into a problem.
	Theme::loadEssential();
	Lang::load('Errors', Lang::$default, false);

	foreach ($js_files as $ID_FILE => $file)
	{
		// Create the url
		$server = empty($file['path']) || (substr($file['path'], 0, 7) != 'http://' && substr($file['path'], 0, 8) != 'https://') ? 'https://www.simplemachines.org' : '';
		$url = $server . (!empty($file['path']) ? $file['path'] : $file['path']) . $file['filename'] . (!empty($file['parameters']) ? '?' . $file['parameters'] : '');

		// Get the file
		$file_data = fetch_web_data($url);

		// If we got an error - give up - the site might be down. And if we should happen to be coming from elsewhere, let's also make a note of it.
		if ($file_data === false)
		{
			Utils::$context['scheduled_errors']['fetchSMfiles'][] = sprintf(Lang::$txt['st_cannot_retrieve_file'], $url);
			ErrorHandler::log(sprintf(Lang::$txt['st_cannot_retrieve_file'], $url));
			return false;
		}

		// Save the file to the database.
		Db::$db->query('substring', '
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

/**
 * Happy birthday!!
 */
function scheduled_birthdayemails()
{
	Db::$db->insert('insert', '{db_prefix}background_tasks',
		array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/Birthday_Notify.php', 'SMF\Tasks\Birthday_Notify', '', 0),
		array()
	);

	return true;
}

/**
 * Weekly maintenance
 */
function scheduled_weekly_maintenance()
{
	// Delete some settings that needn't be set if they are otherwise empty.
	$emptySettings = array(
		'warning_mute', 'warning_moderate', 'warning_watch', 'warning_show', 'disableCustomPerPage', 'spider_mode', 'spider_group',
		'paid_currency_code', 'paid_currency_symbol', 'paid_email_to', 'paid_email', 'paid_enabled', 'paypal_email',
		'search_enable_captcha', 'search_floodcontrol_time', 'show_spider_online',
	);

	Db::$db->query('', '
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

	Db::$db->query('', '
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:setting_list})',
		array(
			'setting_list' => $deleteAnywaySettings,
		)
	);

	// Ok should we prune the logs?
	if (!empty(Config::$modSettings['pruningOptions']))
	{
		if (!empty(Config::$modSettings['pruningOptions']) && strpos(Config::$modSettings['pruningOptions'], ',') !== false)
			list (Config::$modSettings['pruneErrorLog'], Config::$modSettings['pruneModLog'], Config::$modSettings['pruneBanLog'], Config::$modSettings['pruneReportLog'], Config::$modSettings['pruneScheduledTaskLog'], Config::$modSettings['pruneSpiderHitLog']) = explode(',', Config::$modSettings['pruningOptions']);

		if (!empty(Config::$modSettings['pruneErrorLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneErrorLog'] * 86400;

			Db::$db->query('', '
				DELETE FROM {db_prefix}log_errors
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty(Config::$modSettings['pruneModLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneModLog'] * 86400;

			Db::$db->query('', '
				DELETE FROM {db_prefix}log_actions
				WHERE log_time < {int:log_time}
					AND id_log = {int:moderation_log}',
				array(
					'log_time' => $t,
					'moderation_log' => 1,
				)
			);
		}

		if (!empty(Config::$modSettings['pruneBanLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneBanLog'] * 86400;

			Db::$db->query('', '
				DELETE FROM {db_prefix}log_banned
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty(Config::$modSettings['pruneReportLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneReportLog'] * 86400;

			// This one is more complex then the other logs.  First we need to figure out which reports are too old.
			$reports = array();
			$result = Db::$db->query('', '
				SELECT id_report
				FROM {db_prefix}log_reported
				WHERE time_started < {int:time_started}
					AND closed = {int:closed}
					AND ignore_all = {int:not_ignored}',
				array(
					'time_started' => $t,
					'closed' => 1,
					'not_ignored' => 0,
				)
			);

			while ($row = Db::$db->fetch_row($result))
				$reports[] = $row[0];

			Db::$db->free_result($result);

			if (!empty($reports))
			{
				// Now delete the reports...
				Db::$db->query('', '
					DELETE FROM {db_prefix}log_reported
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
				// And delete the comments for those reports...
				Db::$db->query('', '
					DELETE FROM {db_prefix}log_reported_comments
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
			}
		}

		if (!empty(Config::$modSettings['pruneScheduledTaskLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneScheduledTaskLog'] * 86400;

			Db::$db->query('', '
				DELETE FROM {db_prefix}log_scheduled_tasks
				WHERE time_run < {int:time_run}',
				array(
					'time_run' => $t,
				)
			);
		}

		if (!empty(Config::$modSettings['pruneSpiderHitLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - Config::$modSettings['pruneSpiderHitLog'] * 86400;

			Db::$db->query('', '
				DELETE FROM {db_prefix}log_spider_hits
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}
	}

	// Get rid of any paid subscriptions that were never actioned.
	Db::$db->query('', '
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
	Db::$db->query('', '
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - 86400,
		)
	);

	// Update the regex of top level domains with the IANA's latest official list
	Db::$db->insert('insert', '{db_prefix}background_tasks',
		array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/UpdateTldRegex.php', 'SMF\\Tasks\\UpdateTldRegex', '', 0), array()
	);

	// Ensure Unicode data files are up to date
	Db::$db->insert('insert', '{db_prefix}background_tasks',
		array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/UpdateUnicode.php', 'SMF\\Tasks\\UpdateUnicode', '', 0), array()
	);

	// Run Cache housekeeping
	if (!empty(CacheApi::$enable) && !empty(CacheApi::$loadedApi))
		CacheApi::$loadedApi->housekeeping();

	// Prevent stale minimized CSS and JavaScript from cluttering up the theme directories
	Theme::deleteAllMinified();

	// Maybe there's more to do.
	call_integration_hook('integrate_weekly_maintenance');

	return true;
}

/**
 * Perform the standard checks on expiring/near expiring subscriptions.
 */
function scheduled_paid_subscriptions()
{
	// Start off by checking for removed subscriptions.
	$request = Db::$db->query('', '
		SELECT id_subscribe, id_member
		FROM {db_prefix}log_subscribed
		WHERE status = {int:is_active}
			AND end_time < {int:time_now}',
		array(
			'is_active' => 1,
			'time_now' => time(),
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		Subscriptions::remove($row['id_subscribe'], $row['id_member']);
	}
	Db::$db->free_result($request);

	// Get all those about to expire that have not had a reminder sent.
	$request = Db::$db->query('', '
		SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
		FROM {db_prefix}log_subscribed AS ls
			JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
			JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
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
	$members = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// If this is the first one load the important bits.
		if (empty($subs_reminded))
		{
			// Need the below for loadLanguage to work!
			Theme::loadEssential();
		}

		$subs_reminded[] = $row['id_sublog'];
		$members[$row['id_member']] = $row;
	}
	Db::$db->free_result($request);

	// Load alert preferences
	$notifyPrefs = Notify::getNotifyPrefs(array_keys($members), 'paidsubs_expiring', true);
	$alert_rows = array();
	foreach ($members as $row)
	{
		$replacements = array(
			'PROFILE_LINK' => Config::$scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
			'REALNAME' => $row['member_name'],
			'SUBSCRIPTION' => $row['name'],
			'END_DATE' => strip_tags(timeformat($row['end_time'])),
		);

		$emaildata = Mail::loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile']);

		// Send the actual email.
		if ($notifyPrefs[$row['id_member']] & 0x02)
			Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'paid_sub_remind', $emaildata['is_html'], 2);

		if ($notifyPrefs[$row['id_member']] & 0x01)
		{
			$alert_rows[] = array(
				'alert_time' => time(),
				'id_member' => $row['id_member'],
				'id_member_started' => $row['id_member'],
				'member_name' => $row['member_name'],
				'content_type' => 'paidsubs',
				'content_id' => $row['id_sublog'],
				'content_action' => 'expiring',
				'is_read' => 0,
				'extra' => Utils::jsonEncode(array(
					'subscription_name' => $row['name'],
					'end_time' => $row['end_time'],
				)),
			);
		}
	}

	// Insert the alerts if any
	if (!empty($alert_rows))
		Alert::createBatch($alert_rows);

	// Mark the reminder as sent.
	if (!empty($subs_reminded))
		Db::$db->query('', '
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

/**
 * Check for un-posted attachments is something we can do once in a while :P
 * This function uses opendir cycling through all the attachments
 */
function scheduled_remove_temp_attachments()
{
	// We need to know where this thing is going.
	if (!empty(Config::$modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array(Config::$modSettings['attachmentUploadDir']))
			Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);

		// Just use the current path for temp files.
		$attach_dirs = Config::$modSettings['attachmentUploadDir'];
	}
	else
	{
		$attach_dirs = array(Config::$modSettings['attachmentUploadDir']);
	}

	foreach ($attach_dirs as $attach_dir)
	{
		$dir = @opendir($attach_dir);
		if (!$dir)
		{
			Theme::loadEssential();
			Lang::load('Post');
			Utils::$context['scheduled_errors']['remove_temp_attachments'][] = Lang::$txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
			ErrorHandler::log(Lang::$txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');
			return false;
		}

		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..')
				continue;

			if (strpos($file, 'post_tmp_') !== false)
			{
				// Temp file is more than 5 hours old!
				if (filemtime($attach_dir . '/' . $file) < time() - 18000)
					@unlink($attach_dir . '/' . $file);
			}
		}
		closedir($dir);
	}

	return true;
}

/**
 * Check for move topic notices that have past their best by date
 */
function scheduled_remove_topic_redirect()
{
	// init
	$topics = array();

	// We will need this for language files
	Theme::loadEssential();

	// Find all of the old MOVE topic notices that were set to expire
	$request = Db::$db->query('', '
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE redirect_expires <= {int:redirect_expires}
			AND redirect_expires <> 0',
		array(
			'redirect_expires' => time(),
		)
	);

	while ($row = Db::$db->fetch_row($request))
		$topics[] = $row[0];
	Db::$db->free_result($request);

	// Zap, your gone
	if (count($topics) > 0)
	{
		Topic::remove($topics, false, true);
	}

	return true;
}

/**
 * Check for old drafts and remove them
 */
function scheduled_remove_old_drafts()
{
	if (empty(Config::$modSettings['drafts_keep_days']))
		return true;

	// init
	$drafts = array();

	// We need this for language items
	Theme::loadEssential();

	// Find all of the old drafts
	$request = Db::$db->query('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * Config::$modSettings['drafts_keep_days']),
		)
	);

	while ($row = Db::$db->fetch_row($request))
		$drafts[] = (int) $row[0];
	Db::$db->free_result($request);

	// If we have old one, remove them
	if (count($drafts) > 0)
	{
		Draft::delete($drafts, false);
	}

	return true;
}

/**
 * Prune log_topics, log_boards & log_mark_boards_read.
 * For users who haven't been active in a long time, purge these records.
 * For users who haven't been active in a shorter time, mark boards as read,
 * pruning log_topics.
 */
function scheduled_prune_log_topics()
{
	// If set to zero, bypass
	if (empty(Config::$modSettings['mark_read_max_users']) || (empty(Config::$modSettings['mark_read_beyond']) && empty(Config::$modSettings['mark_read_delete_beyond'])))
		return true;

	// Convert to timestamps for comparison
	if (empty(Config::$modSettings['mark_read_beyond']))
		$markReadCutoff = 0;
	else
		$markReadCutoff = time() - Config::$modSettings['mark_read_beyond'] * 86400;

	if (empty(Config::$modSettings['mark_read_delete_beyond']))
		$cleanupBeyond = 0;
	else
		$cleanupBeyond = time() - Config::$modSettings['mark_read_delete_beyond'] * 86400;

	$maxMembers = Config::$modSettings['mark_read_max_users'];

	// You're basically saying to just purge, so just purge
	if ($markReadCutoff < $cleanupBeyond)
		$markReadCutoff = $cleanupBeyond;

	// Try to prevent timeouts
	@set_time_limit(300);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Start off by finding the records in log_boards, log_topics & log_mark_read
	// for users who haven't been around the longest...
	$members = array();
	$sql = 'SELECT lb.id_member, m.last_login
			FROM {db_prefix}members m
			INNER JOIN
			(
				SELECT DISTINCT id_member
				FROM {db_prefix}log_boards
			) lb ON m.id_member = lb.id_member
			WHERE m.last_login <= {int:dcutoff}
		UNION
		SELECT lmr.id_member, m.last_login
			FROM {db_prefix}members m
			INNER JOIN
			(
				SELECT DISTINCT id_member
				FROM {db_prefix}log_mark_read
			) lmr ON m.id_member = lmr.id_member
			WHERE m.last_login <= {int:dcutoff}
		UNION
		SELECT lt.id_member, m.last_login
			FROM {db_prefix}members m
			INNER JOIN
			(
				SELECT DISTINCT id_member
				FROM {db_prefix}log_topics
				WHERE unwatched = {int:unwatched}
			) lt ON m.id_member = lt.id_member
			WHERE m.last_login <= {int:mrcutoff}
		ORDER BY last_login
		LIMIT {int:limit}';
	$result = Db::$db->query('', $sql,
		array(
			'limit' => $maxMembers,
			'dcutoff' => $cleanupBeyond,
			'mrcutoff' => $markReadCutoff,
			'unwatched' => 0,
		)
	);

	// Move to array...
	$members = Db::$db->fetch_all($result);
	Db::$db->free_result($result);

	// Nothing to do?
	if (empty($members))
		return true;

	// Determine action based on last_login...
	$purgeMembers = array();
	$markReadMembers = array();
	foreach($members as $member)
	{
		if ($member['last_login'] <= $cleanupBeyond)
			$purgeMembers[] = $member['id_member'];
		elseif ($member['last_login'] <= $markReadCutoff)
			$markReadMembers[] = $member['id_member'];
	}

	if (!empty($purgeMembers) && !empty(Config::$modSettings['mark_read_delete_beyond']))
	{
		// Delete rows from log_boards
		$sql = 'DELETE FROM {db_prefix}log_boards
			WHERE id_member IN ({array_int:members})';
		Db::$db->query('', $sql,
			array(
				'members' => $purgeMembers,
			)
		);
		// Delete rows from log_mark_read
		$sql = 'DELETE FROM {db_prefix}log_mark_read
			WHERE id_member IN ({array_int:members})';
		Db::$db->query('', $sql,
			array(
				'members' => $purgeMembers,
			)
		);
		// Delete rows from log_topics
		$sql = 'DELETE FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:members})
				AND unwatched = {int:unwatched}';
		Db::$db->query('', $sql,
			array(
				'members' => $purgeMembers,
				'unwatched' => 0,
			)
		);
	}

	// Nothing left to do?
	if (empty($markReadMembers) || empty(Config::$modSettings['mark_read_beyond']))
		return true;

	// Find board inserts to perform...
	// Get board info for each member from log_topics.
	// Note this user may have read many topics on that board,
	// but we just want one row each, & the ID of the last message read in each board.
	$boards = array();
	$sql = 'SELECT lt.id_member, t.id_board, MAX(lt.id_msg) AS id_last_message
		FROM {db_prefix}topics t
		INNER JOIN
		(
			SELECT id_member, id_topic, id_msg
			FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:members})
		) lt ON t.id_topic = lt.id_topic
		GROUP BY lt.id_member, t.id_board';
	$result = Db::$db->query('', $sql,
		array(
			'members' => $markReadMembers,
		)
	);
	$boards = Db::$db->fetch_all($result);
	Db::$db->free_result($result);

	// Create one SQL statement for this set of inserts
	if (!empty($boards))
	{
		Db::$db->insert('replace',
			'{db_prefix}log_mark_read',
			array('id_member' => 'int', 'id_board' => 'int', 'id_msg' => 'int'),
			$boards,
			array('id_member', 'id_board')
		);
	}

	// Finally, delete this set's rows from log_topics
	$sql = 'DELETE FROM {db_prefix}log_topics
		WHERE id_member IN ({array_int:members})
			AND unwatched = {int:unwatched}';
	Db::$db->query('', $sql,
		array(
			'members' => $markReadMembers,
			'unwatched' => 0,
		)
	);

	return true;
}

?>