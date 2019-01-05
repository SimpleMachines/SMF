<?php

/**
 * This file is automatically called and handles all manner of scheduled things.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This function works out what to do!
 */
function AutoTask()
{
	global $time_start, $smcFunc;

	// Special case for doing the mail queue.
	if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
		ReduceMailQueue();
	else
	{
		$task_string = '';

		// Select the next task to do.
		$request = $smcFunc['db_query']('', '
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
					$total_time = round(microtime(true) - $time_start, 3);
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
	global $smcFunc, $modSettings, $sourcedir, $boarddir, $db_type, $image_proxy_enabled;

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

	// Clean up some old login history information.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}member_logins
		WHERE time < {int:oldLogins}',
		array(
			'oldLogins' => time() - (!empty($modSettings['loginHistoryDays']) ? 60 * 60 * 24 * $modSettings['loginHistoryDays'] : 2592000),
		)
	);

	// Run Imageproxy housekeeping
	if (!empty($image_proxy_enabled))
	{
		global $proxyhousekeeping;
		$proxyhousekeeping = true;

		require_once($boarddir . '/proxy.php');
		$proxy = new ProxyServer();
		$proxy->housekeeping();

		unset($proxyhousekeeping);
	}

	// Log we've done it...
	return true;
}

/**
 * Send out a daily email of all subscribed topics.
 */
function scheduled_daily_digest()
{
	global $is_weekly, $txt, $mbname, $scripturl, $sourcedir, $smcFunc, $context, $modSettings;

	// We'll want this...
	require_once($sourcedir . '/Subs-Post.php');
	loadEssentialThemeData();

	$is_weekly = !empty($is_weekly) ? 1 : 0;

	// Right - get all the notification data FIRST.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
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

	// The preferred way...
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs(array_keys($members), array('msg_notify_type', 'msg_notify_pref'), true);

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
		if ($titled)
			$email['body'] .= "\n";

		// Then just say our goodbyes!
		$email['body'] .= "\n\n" . $txt['regards_team'];

		// Send it - low priority!
		sendmail($email['email'], $email['subject'], $email['body'], null, 'digest', false, 4);

		$members_sent[] = $mid;
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
	if (!empty($members_sent))
	{
		$smcFunc['db_query']('', '
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
 * Send a group of emails from the mail queue.
 *
 * @param bool|int $number The number to send each loop through or false to use the standard limits
 * @param bool $override_limit Whether to bypass the limit
 * @param bool $force_send Whether to forcibly send the messages now (useful when using cron jobs)
 * @return bool Whether things were sent
 */
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
			WHERE variable = {literal:mail_next_send}
				AND value = {string:last_send}',
			array(
				'next_mail_send' => time() + $delay,
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
		SELECT /*!40001 SQL_NO_CACHE */ id_mail, recipient, body, subject, headers, send_html, time_sent, private
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT {int:limit}',
		array(
			'limit' => $number,
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
			'time_sent' => $row['time_sent'],
			'private' => $row['private'],
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
			WHERE variable = {literal:mail_next_send}
				AND value = {string:last_mail_send}',
			array(
				'no_send' => '0',
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
	foreach ($emails as $email)
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
			$result = smtp_mail(array($email['to']), $email['subject'], $email['body'], $email['headers']);

		// Hopefully it sent?
		if (!$result)
			$failed_emails[] = array($email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html'], $email['time_sent'], $email['private']);
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
				SET value = {string:next_mail_send}
				WHERE variable = {literal:mail_next_send}
					AND value = {string:last_send}',
				array(
					'next_mail_send' => time() + 60,
					'last_send' => $modSettings['mail_next_send'],
				)
			);

		// Add our email back to the queue, manually.
		$smcFunc['db_insert']('insert',
			'{db_prefix}mail_queue',
			array('recipient' => 'string', 'body' => 'string', 'subject' => 'string', 'headers' => 'string', 'send_html' => 'string', 'time_sent' => 'string', 'private' => 'int'),
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
			)
		);

	// Had something to send...
	return true;
}

/**
 * Calculate the next time the passed tasks should be triggered.
 *
 * @param string|array $tasks The ID of a single task or an array of tasks
 * @param bool $forceUpdate Whether to force the tasks to run now
 */
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
 * This loads the bare minimum data to allow us to load language files!
 */
function loadEssentialThemeData()
{
	global $settings, $modSettings, $smcFunc, $mbname, $context, $sourcedir, $txt;

	// Get all the default theme variables.
	$result = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND id_theme IN (1, {int:theme_guests})',
		array(
			'no_member' => 0,
			'theme_guests' => !empty($modSettings['theme_guests']) ? $modSettings['theme_guests'] : 1,
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

	// Just in case it wasn't already set elsewhere.
	$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Tell fatal_lang_error() to not reload the theme.
	$context['theme_loaded'] = true;
}

/**
 * This retieves data (e.g. last version of SMF) from sm.org
 */
function scheduled_fetchSMfiles()
{
	global $sourcedir, $txt, $language, $forum_version, $modSettings, $smcFunc, $context;

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

	// Just in case we run into a problem.
	loadEssentialThemeData();
	loadLanguage('Errors', $language, false);

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
			$context['scheduled_errors']['fetchSMfiles'][] = sprintf($txt['st_cannot_retrieve_file'], $url);
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

/**
 * Happy birthday!!
 */
function scheduled_birthdayemails()
{
	global $smcFunc;

	$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
		array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/Birthday-Notify.php', 'Birthday_Notify_Background', '', 0),
		array()
	);

	return true;
}

/**
 * Weekly maintenance
 */
function scheduled_weekly_maintenance()
{
	global $modSettings, $smcFunc, $cache_enable, $cacheAPI;

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
				WHERE time_started < {int:time_started}
					AND closed = {int:closed}
					AND ignore_all = {int:not_ignored}',
				array(
					'time_started' => $t,
					'closed' => 1,
					'not_ignored' => 0,
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

	// Update the regex of top level domains with the IANA's latest official list
	$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
		array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/UpdateTldRegex.php', 'Update_TLD_Regex', '', 0), array()
	);

	// Run Cache housekeeping
	if (!empty($cache_enable) && !empty($cacheAPI))
		$cacheAPI->housekeeping();

	// Prevent stale minimized CSS and JavaScript from cluttering up the theme directories
	deleteAllMinified();

	return true;
}

/**
 * Perform the standard checks on expiring/near expiring subscriptions.
 */
function scheduled_paid_subscriptions()
{
	global $sourcedir, $scripturl, $smcFunc, $modSettings, $language;

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
		$members[$row['id_member']] = $row;
	}
	$smcFunc['db_free_result']($request);

	// Load alert preferences
	require_once($sourcedir . '/Subs-Notify.php');
	$notifyPrefs = getNotifyPrefs(array_keys($members), 'paidsubs_expiring', true);
	$alert_rows = array();
	foreach ($members as $row)
	{
		$replacements = array(
			'PROFILE_LINK' => $scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
			'REALNAME' => $row['member_name'],
			'SUBSCRIPTION' => $row['name'],
			'END_DATE' => strip_tags(timeformat($row['end_time'])),
		);

		$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Send the actual email.
		if ($notifyPrefs[$row['id_member']] & 0x02)
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'paid_sub_remind', $emaildata['is_html'], 2);

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
				'extra' => $smcFunc['json_encode'](array(
					'subscription_name' => $row['name'],
					'end_time' => strip_tags(timeformat($row['end_time'])),
				)),
			);
			updateMemberData($row['id_member'], array('alerts' => '+'));
		}
	}

	// Insert the alerts if any
	if (!empty($alert_rows))
		$smcFunc['db_insert']('',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$alert_rows,
			array()
		);

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

/**
 * Check for un-posted attachments is something we can do once in a while :P
 * This function uses opendir cycling through all the attachments
 */
function scheduled_remove_temp_attachments()
{
	global $smcFunc, $modSettings, $context, $txt;

	// We need to know where this thing is going.
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = $smcFunc['json_decode']($modSettings['attachmentUploadDir'], true);

		// Just use the current path for temp files.
		$attach_dirs = $modSettings['attachmentUploadDir'];
	}
	else
	{
		$attach_dirs = array($modSettings['attachmentUploadDir']);
	}

	foreach ($attach_dirs as $attach_dir)
	{
		$dir = @opendir($attach_dir);
		if (!$dir)
		{
			loadEssentialThemeData();
			loadLanguage('Post');
			$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
			log_error($txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');
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
	global $smcFunc, $sourcedir;

	// init
	$topics = array();

	// We will need this for language files
	loadEssentialThemeData();

	// Find all of the old MOVE topic notices that were set to expire
	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE redirect_expires <= {int:redirect_expires}
			AND redirect_expires <> 0',
		array(
			'redirect_expires' => time(),
		)
	);

	while ($row = $smcFunc['db_fetch_row']($request))
		$topics[] = $row[0];
	$smcFunc['db_free_result']($request);

	// Zap, your gone
	if (count($topics) > 0)
	{
		require_once($sourcedir . '/RemoveTopic.php');
		removeTopics($topics, false, true);
	}

	return true;
}

/**
 * Check for old drafts and remove them
 */
function scheduled_remove_old_drafts()
{
	global $smcFunc, $sourcedir, $modSettings;

	if (empty($modSettings['drafts_keep_days']))
		return true;

	// init
	$drafts = array();

	// We need this for language items
	loadEssentialThemeData();

	// Find all of the old drafts
	$request = $smcFunc['db_query']('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $modSettings['drafts_keep_days']),
		)
	);

	while ($row = $smcFunc['db_fetch_row']($request))
		$drafts[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	// If we have old one, remove them
	if (count($drafts) > 0)
	{
		require_once($sourcedir . '/Drafts.php');
		DeleteDraft($drafts, false);
	}

	return true;
}

?>