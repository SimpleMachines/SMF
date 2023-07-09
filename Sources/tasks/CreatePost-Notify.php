<?php

/**
 * This file contains code used to notify people when a new post is created that
 * is relevant to them in some way: new topics in boards they watch, replies to
 * topics they watch, posts that mention them, and/or posts that quote them.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.4
 */

/**
 * Class CreatePost_Notify_Background
 */
class CreatePost_Notify_Background extends SMF_BackgroundTask
{
	/**
	 * Constants for reply types.
	 */
	const NOTIFY_TYPE_REPLIES_AND_MODERATION = 1;
	const NOTIFY_TYPE_REPLIES_AND_OWN_TOPIC_MODERATION = 2;
	const NOTIFY_TYPE_ONLY_REPLIES = 3;
	const NOTIFY_TYPE_NOTHING = 4;

	/**
	 * Constants for frequencies.
	 */
	const FREQUENCY_NOTHING = 0;
	const FREQUENCY_EVERYTHING = 1;
	const FREQUENCY_FIRST_UNREAD_MSG = 2;
	const FREQUENCY_DAILY_DIGEST = 3;
	const FREQUENCY_WEEKLY_DIGEST = 4;

	/**
	 * Minutes to wait before sending notifications about about mentions
	 * and quotes in unwatched and/or edited posts.
	 */
	const MENTION_DELAY = 5;

	/**
	 * @var array Info about members to be notified.
	 */
	private $members = array(
		// These three contain nested arrays of member info.
		'mentioned' => array(),
		'quoted' => array(),
		'watching' => array(),

		// These ones just contain member IDs.
		'all' => array(),
		'emailed' => array(),
		'alerted' => array(),
		'done' => array(),
	);

	/**
	 * @var array Alerts to be inserted into the alerts table.
	 */
	private $alert_rows = array();

	/**
	 * @var array Members' notification and alert preferences.
	 */
	private $prefs = array();

	/**
	 * @var int Timestamp after which email notifications should be sent about
	 *			mentions and quotes in unwatched and/or edited posts.
	 */
	private $mention_mail_time = 0;

	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 * @throws Exception
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $language, $modSettings, $user_info, $txt;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Mentions.php');
		require_once($sourcedir . '/Subs-Notify.php');
		require_once($sourcedir . '/Subs.php');
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();

		$msgOptions = &$this->_details['msgOptions'];
		$topicOptions = &$this->_details['topicOptions'];
		$posterOptions = &$this->_details['posterOptions'];
		$type = &$this->_details['type'];

		// Board id is required; if missing, log an error and return
		if (!isset($topicOptions['board']))
		{
			require_once($sourcedir . '/Errors.php');
			loadLanguage('Errors');
			log_error($txt['missing_board_id'], 'general', __FILE__, __LINE__);
			return true;
		}

		// poster_time not always supplied, but used throughout
		if (empty($msgOptions['poster_time']))
			$msgOptions['poster_time'] = 0;

		$this->mention_mail_time = $msgOptions['poster_time'] + self::MENTION_DELAY * 60;

		// We need some more info about the quoted and mentioned members.
		if (!empty($msgOptions['quoted_members']))
			$this->members['quoted'] = Mentions::getMentionsByContent('quote', $msgOptions['id'], array_keys($msgOptions['quoted_members']));
		if (!empty($msgOptions['mentioned_members']))
			$this->members['mentioned'] = Mentions::getMentionsByContent('msg', $msgOptions['id'], array_keys($msgOptions['mentioned_members']));

		// Find the people interested in receiving notifications for this topic
		$request = $smcFunc['db_query']('', '
			SELECT
				ln.id_member, ln.id_board, ln.id_topic, ln.sent,
				mem.email_address, mem.lngfile, mem.pm_ignore_list,
				mem.id_group, mem.id_post_group, mem.additional_groups,
				mem.time_format, mem.time_offset, mem.timezone,
				b.member_groups, t.id_member_started, t.id_member_updated
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}members AS mem ON (ln.id_member = mem.id_member)
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board OR b.id_board = t.id_board)
			WHERE ln.id_member != {int:member}
				AND (ln.id_topic = {int:topic} OR ln.id_board = {int:board})',
			array(
				'member' => $posterOptions['id'],
				'topic' => $topicOptions['id'],
				'board' => $topicOptions['board'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Skip members who aren't allowed to see this board
			$groups = array_merge(array($row['id_group'], $row['id_post_group']), (empty($row['additional_groups']) ? array() : explode(',', $row['additional_groups'])));

			$allowed_groups = explode(',', $row['member_groups']);

			if (!in_array(1, $groups) && count(array_intersect($groups, $allowed_groups)) == 0)
				continue;
			else
			{
				$row['groups'] = $groups;
				unset($row['id_group'], $row['id_post_group'], $row['additional_groups']);
			}

			$this->members['watching'][$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Filter out mentioned and quoted members who can't see this board.
		if (!empty($this->members['mentioned']) || !empty($this->members['quoted']))
		{
			// This won't be set yet if no one is watching this board or topic.
			if (!isset($allowed_groups))
			{
				$request = $smcFunc['db_query']('', '
					SELECT member_groups
					FROM {db_prefix}boards
					WHERE id_board = {int:board}',
					array(
						'board' => $topicOptions['board'],
					)
				);
				list($allowed_groups) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				$allowed_groups = explode(',', $allowed_groups);
			}

			foreach (array('mentioned', 'quoted') as $member_type)
			{
				foreach ($this->members[$member_type] as $member_id => $member_data)
				{
					if (!in_array(1, $member_data['groups']) && count(array_intersect($member_data['groups'], $allowed_groups)) == 0)
						unset($this->members[$member_type][$member_id], $msgOptions[$member_type . '_members'][$member_id]);
				}
			}
		}

		$unnotified = array_filter($this->members['watching'], function ($member) {
			return empty($member['sent']);
		});


		// Modified post, or dealing with delayed mention and quote notifications.
		if ($type == 'edit' || !empty($this->_details['respawns']))
		{
			// Notifications about modified posts only go to members who were mentioned or quoted
			$this->members['watching'] = $type == 'edit' ? array(): $unnotified;

			// If this post has no quotes or mentions, just delete any obsolete alerts and bail out.
			if (empty($this->members['quoted']) && empty($this->members['mentioned']))
			{
				$this->updateAlerts($msgOptions['id']);
				return true;
			}

			// Never notify about edits to ancient posts.
			if (!empty($modSettings['oldTopicDays']) && time() > $msgOptions['poster_time'] + $modSettings['oldTopicDays'] * 86400)
				return true;

			// If editing is only allowed for a brief time, send after editing becomes disabled.
			if (!empty($modSettings['edit_disable_time']) && $modSettings['edit_disable_time'] <= self::MENTION_DELAY)
			{
				$this->mention_mail_time = $msgOptions['poster_time'] + $modSettings['edit_disable_time'] * 60;
			}
			// Otherwise, impose a delay before sending notifications about edited posts.
			else
			{
				if (!empty($this->_details['respawns']))
				{
					$request = $smcFunc['db_query']('', '
						SELECT modified_time
						FROM {db_prefix}messages
						WHERE id_msg = {int:msg}
						LIMIT 1',
						array(
							'msg' => $msgOptions['id'],
						)
					);
					list($real_modified_time) = $smcFunc['db_fetch_row']($request);
					$smcFunc['db_free_result']($request);

					// If it was modified again while we weren't looking, bail out.
					// A future instance of this task will take care of it instead.
					if ((!empty($msgOptions['modify_time']) ? $msgOptions['modify_time'] : $msgOptions['poster_time']) < $real_modified_time)
						return true;
				}

				$this->mention_mail_time = (!empty($msgOptions['modify_time']) ? $msgOptions['modify_time'] : $msgOptions['poster_time']) + self::MENTION_DELAY * 60;
			}
		}

		$this->members['all'] = array_unique(array_merge(array_keys($this->members['watching']), array_keys($this->members['quoted']), array_keys($this->members['mentioned'])));

		if (empty($this->members['all']))
			return true;

		$this->prefs = getNotifyPrefs($this->members['all'], '', true);

		// May as well disable these, since they'll be stripped out anyway.
		$disable = array('attach', 'img', 'iurl', 'url', 'youtube');
		if (!empty($modSettings['disabledBBC']))
		{
			$disabledBBC = $modSettings['disabledBBC'];
			$disable = array_unique(array_merge($disable, explode(',', $modSettings['disabledBBC'])));
		}
		$modSettings['disabledBBC'] = implode(',', $disable);

		// Notify any members who were mentioned.
		if (!empty($this->members['mentioned']))
			$this->handleMentionedNotifications();

		// Notify any members who were quoted.
		if (!empty($this->members['quoted']))
			$this->handleQuoteNotifications();

		// Handle rest of the notifications for watched topics and boards
		if (!empty($this->members['watching']))
			$this->handleWatchedNotifications();

		// Put this back the way we found it.
		if (!empty($disabledBBC))
			$modSettings['disabledBBC'] = $disabledBBC;

		// Track what we sent.
		$members_to_log = array_intersect($this->members['emailed'], array_keys($this->members['watching']));
		if (!empty($members_to_log))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_notify
				SET sent = {int:is_sent}
				WHERE ' . ($type == 'topic' ? 'id_board = {int:board}' : 'id_topic = {int:topic}') . '
					AND id_member IN ({array_int:members})',
				array(
					'topic' => $topicOptions['id'],
					'board' => $topicOptions['board'],
					// 'members' => $this->members['emailed'],
					'members' => $members_to_log,
					'is_sent' => 1,
				)
			);
		}

		// Insert it into the digest for daily/weekly notifications
		if ($type != 'edit' && empty($this->_details['respawns']))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}log_digest',
				array(
					'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
				),
				array($topicOptions['id'], $msgOptions['id'], $type, $posterOptions['id']),
				array()
			);
		}

		// Insert the alerts if any
		$this->updateAlerts($msgOptions['id']);

		// If there is anyone still to notify via email, create a new task later.
		$unnotified = array_diff_key($unnotified, array_flip($this->members['emailed']));
		if (!empty($unnotified) || !empty($msgOptions['mentioned_members']) || !empty($msgOptions['quoted_members']))
		{
			$new_details = $this->_details;

			if (empty($new_details['respawns']))
				$new_details['respawns'] = 0;

			if ($new_details['respawns']++ < 10)
			{
				$smcFunc['db_insert']('',
					'{db_prefix}background_tasks',
					array(
						'task_file' => 'string',
						'task_class' => 'string',
						'task_data' => 'string',
						'claimed_time' => 'int',
					),
					array(
						'$sourcedir/tasks/CreatePost-Notify.php',
						'CreatePost_Notify_Background',
						$smcFunc['json_encode']($new_details),
						max(0, $this->mention_mail_time - MAX_CLAIM_THRESHOLD),
					),
					array('id_task')
				);
			}
		}

		return true;
	}

	private function updateAlerts($msg_id)
	{
		global $smcFunc;

		// We send alerts only on the first iteration of this task.
		if (!empty($this->_details['respawns']))
			return;

		// Delete alerts about any mentions and quotes that no longer exist.
		if ($this->_details['type'] == 'edit')
		{
			$old_alerts = array();

			$request = $smcFunc['db_query']('', '
				SELECT content_action, id_member
				FROM {db_prefix}user_alerts
				WHERE content_id = {int:msg_id}
					AND content_type = {literal:msg}
					AND (content_action = {literal:quote} OR content_action = {literal:mention})',
				array(
					'msg_id' => $msg_id,
				)
			);
			if ($smcFunc['db_num_rows']($request) != 0)
			{
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$old_alerts[$row['content_action']][$row['id_member']] = $row['id_member'];
			}
			$smcFunc['db_free_result']($request);

			if (!empty($old_alerts))
			{
				$request = $smcFunc['db_query']('', '
					SELECT content_type, id_mentioned
					FROM {db_prefix}mentions
					WHERE content_id = {int:msg_id}
						AND (content_type = {literal:quote} OR content_type = {literal:msg})',
					array(
						'msg_id' => $msg_id,
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$content_action = $row['content_type'] == 'quote' ? 'quote' : 'mention';
					unset($old_alerts[$content_action][$row['id_mentioned']]);
				}
				$smcFunc['db_free_result']($request);

				$conditions = array();

				if (!empty($old_alerts['quote']))
					$conditions[] = '(content_action = {literal:quote} AND id_member IN ({array_int:members_not_quoted}))';

				if (!empty($old_alerts['mention']))
					$conditions[] = '(content_action = {literal:mention} AND id_member IN ({array_int:members_not_mentioned}))';

				if (!empty($conditions))
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}user_alerts
						WHERE content_id = {int:msg_id}
							AND content_type = {literal:msg}
							AND (' . implode(' OR ', $conditions) . ')',
						array(
							'msg_id' => $msg_id,
							'members_not_quoted' => empty($old_alerts['quote']) ? array(0) : $old_alerts['quote'],
							'members_not_mentioned' => empty($old_alerts['mention']) ? array(0) : $old_alerts['mention'],
						)
					);

					foreach ($old_alerts as $member_ids)
						updateMemberData($member_ids, array('alerts' => '-'));
				}
			}
		}

		// Insert the new alerts.
		if (!empty($this->alert_rows))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}user_alerts',
				array(
					'alert_time' => 'int',
					'id_member' => 'int',
					'id_member_started' => 'int',
					'member_name' => 'string',
					'content_type' => 'string',
					'content_id' => 'int',
					'content_action' => 'string',
					'is_read' => 'int',
					'extra' => 'string',
				),
				$this->alert_rows,
				array()
			);

			updateMemberData(array_column($this->alert_rows, 'id_member'), array('alerts' => '+'));
		}
	}

	/**
	 * Notifies members about new posts in topics they are watching
	 * and new topics in boards they are watching.
	 */
	protected function handleWatchedNotifications()
	{
		global $smcFunc, $scripturl, $modSettings, $user_info;

		$msgOptions = &$this->_details['msgOptions'];
		$topicOptions = &$this->_details['topicOptions'];
		$posterOptions = &$this->_details['posterOptions'];
		$type = &$this->_details['type'];

		$user_ids = array_keys($this->members['watching']);
		if (!in_array($posterOptions['id'], $user_ids))
			$user_ids[] = $posterOptions['id'];
		$members_info = $this->getMinUserInfo($user_ids);

		$parsed_message = array();
		foreach ($this->members['watching'] as $member_id => $member_data)
		{
			if (in_array($member_id, $this->members['done']))
				continue;

			$frequency = isset($this->prefs[$member_id]['msg_notify_pref']) ? $this->prefs[$member_id]['msg_notify_pref'] : self::FREQUENCY_NOTHING;
			$notify_types = !empty($this->prefs[$member_id]['msg_notify_type']) ? $this->prefs[$member_id]['msg_notify_type'] : self::NOTIFY_TYPE_REPLIES_AND_MODERATION;

			// Don't send a notification if:
			// 1. The watching member ignored the member who did the action.
			if (!empty($member_data['pm_ignore_list']) && in_array($member_data['id_member_updated'], explode(',', $member_data['pm_ignore_list'])))
				continue;

			// 2. The watching member is not interested in moderation on this topic.
			if (!in_array($type, array('reply', 'topic')) && ($notify_types == self::NOTIFY_TYPE_ONLY_REPLIES || ($notify_types == self::NOTIFY_TYPE_REPLIES_AND_OWN_TOPIC_MODERATION && $member_id != $member_data['id_member_started'])))
				continue;

			// 3. This is the watching member's own post.
			if (in_array($type, array('reply', 'topic')) && $member_id == $posterOptions['id'])
				continue;

			// 4. The watching member doesn't want any notifications at all.
			if ($notify_types == self::NOTIFY_TYPE_NOTHING)
				continue;

			// 5. The watching member doesn't want notifications until later.
			if (in_array($frequency, array(
				self::FREQUENCY_NOTHING,
				self::FREQUENCY_DAILY_DIGEST,
				self::FREQUENCY_WEEKLY_DIGEST)))
				continue;

			// 6. We already sent one and the watching member doesn't want more.
			if ($frequency == self::FREQUENCY_FIRST_UNREAD_MSG && $member_data['sent'])
				continue;

			// 7. The watching member isn't on club security's VIP list.
			if (!empty($this->_details['members_only']) && !in_array($member_id, $this->_details['members_only']))
				continue;

			// Watched topic?
			if (!empty($member_data['id_topic']) && $type != 'topic' && !empty($this->prefs[$member_id]))
			{
				$pref = !empty($this->prefs[$member_id]['topic_notify_' . $topicOptions['id']]) ?
					$this->prefs[$member_id]['topic_notify_' . $topicOptions['id']] :
					(!empty($this->prefs[$member_id]['topic_notify']) ? $this->prefs[$member_id]['topic_notify'] : 0);

				$message_type = 'notification_' . $type;

				if ($type == 'reply')
				{
					if (empty($modSettings['disallow_sendBody']) && !empty($this->prefs[$member_id]['msg_receive_body']))
						$message_type .= '_body';

					if (!empty($frequency))
						$message_type .= '_once';
				}

				$content_type = 'topic';
			}
			// A new topic in a watched board then?
			elseif ($type == 'topic')
			{
				$pref = !empty($this->prefs[$member_id]['board_notify_' . $topicOptions['board']]) ?
					$this->prefs[$member_id]['board_notify_' . $topicOptions['board']] :
					(!empty($this->prefs[$member_id]['board_notify']) ? $this->prefs[$member_id]['board_notify'] : 0);

				$content_type = 'board';

				$message_type = !empty($frequency) ? 'notify_boards_once' : 'notify_boards';

				if (empty($modSettings['disallow_sendBody']) && !empty($this->prefs[$member_id]['msg_receive_body']))
					$message_type .= '_body';
			}

			// If neither of the above, this might be a redundant row due to the OR clause in our SQL query, skip
			else
				continue;

			// We need to fake some of $user_info to make BBC parsing work correctly.
			if (isset($user_info))
				$real_user_info = $user_info;

			$user_info = $members_info[$member_id];

			loadLanguage('index+Modifications', $member_data['lngfile'], false);

			// Censor and parse BBC in the receiver's localization. Don't repeat unnecessarily.
			$localization = implode('|', array($member_data['lngfile'], $user_info['time_offset'], $user_info['time_format']));
			if (empty($parsed_message[$localization]))
			{
				$parsed_message[$localization]['subject'] = $msgOptions['subject'];
				$parsed_message[$localization]['body'] = $msgOptions['body'];

				censorText($parsed_message[$localization]['subject']);
				censorText($parsed_message[$localization]['body']);

				$parsed_message[$localization]['subject'] = un_htmlspecialchars($parsed_message[$localization]['subject']);
				$parsed_message[$localization]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($parsed_message[$localization]['body'], false), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']', '&#39;' => '\'', '</tr>' => "\n", '</td>' => "\t", '<hr>' => "\n---------------------------------------------------------------\n")))));
			}

			// Put $user_info back the way we found it.
			if (isset($real_user_info))
			{
				$user_info = $real_user_info;
				unset($real_user_info);
			}
			else
				$user_info = null;

			// Bitwise check: Receiving a alert?
			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$this->alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member_id,
					// Only tell sender's information for new topics and replies
					'id_member_started' => in_array($type, array('topic', 'reply')) ? $posterOptions['id'] : 0,
					'member_name' => in_array($type, array('topic', 'reply')) ? $posterOptions['name'] : '',
					'content_type' => $content_type,
					'content_id' => $topicOptions['id'],
					'content_action' => $type,
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](array(
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $parsed_message[$localization]['subject'],
						'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . (in_array($type, array('reply', 'topic')) ? '.new;topicseen#new' : '.0'),
					)),
				);
			}

			// Bitwise check: Receiving a email notification?
			if ($pref & self::RECEIVE_NOTIFY_EMAIL)
			{
				$itemID = $content_type == 'board' ? $topicOptions['board'] : $topicOptions['id'];

				$token = createUnsubscribeToken($member_data['id_member'], $member_data['email_address'], $content_type, $itemID);

				$replacements = array(
					'TOPICSUBJECT' => $parsed_message[$localization]['subject'],
					'POSTERNAME' => un_htmlspecialchars(isset($members_info[$posterOptions['id']]['name']) ? $members_info[$posterOptions['id']]['name'] : $posterOptions['name']),
					'TOPICLINK' => $scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
					'MESSAGE' => $parsed_message[$localization]['body'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notify' . $content_type . ';' . $content_type . '=' . $itemID . ';sa=off;u=' . $member_data['id_member'] . ';token=' . $token,
				);

				$emaildata = loadEmailTemplate($message_type, $replacements, $member_data['lngfile']);
				$mail_result = sendmail($member_data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id'], $emaildata['is_html']);

				if ($mail_result !== false)
					$this->members['emailed'][] = $member_id;
			}

			$this->members['done'][] = $member_id;
		}
	}

	/**
	 * Notifies members when their posts are quoted in other posts.
	 */
	protected function handleQuoteNotifications()
	{
		global $smcFunc, $modSettings, $language, $scripturl;

		$msgOptions = &$this->_details['msgOptions'];
		$posterOptions = &$this->_details['posterOptions'];

		foreach ($this->members['quoted'] as $member_id => $member_data)
		{
			if (in_array($member_id, $this->members['done']))
				continue;

			if (!isset($this->prefs[$member_id]) || empty($this->prefs[$member_id]['msg_quote']))
				continue;
			else
				$pref = $this->prefs[$member_id]['msg_quote'];

			// You don't need to be notified about quoting yourself.
			if ($member_id == $posterOptions['id'])
				continue;

			// Bitwise check: Receiving an alert?
			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$this->alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member_data['id'],
					'id_member_started' => $posterOptions['id'],
					'member_name' => $posterOptions['name'],
					'content_type' => 'msg',
					'content_id' => $msgOptions['id'],
					'content_action' => 'quote',
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](array(
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
					)),
				);
			}

			// Bitwise check: Receiving a email notification?
			if (!($pref & self::RECEIVE_NOTIFY_EMAIL))
			{
				// Don't want an email, so forget this member in any respawned tasks.
				unset($msgOptions['quoted_members'][$member_id]);
			}
			elseif (TIME_START >= $this->mention_mail_time || in_array($member_id, $this->members['watching']))
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'QUOTENAME' => $posterOptions['name'],
					'MEMBERNAME' => $member_data['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_quote', $replacements, empty($member_data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member_data['lngfile']);
				$mail_result = sendmail($member_data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_quote_' . $msgOptions['id'], $emaildata['is_html'], 2);

				if ($mail_result !== false)
				{
					// Don't send multiple notifications about the same post.
					$this->members['emailed'][] = $member_id;

					// Ensure respawned tasks don't send this again.
					unset($msgOptions['quoted_members'][$member_id]);
				}
			}

			$this->members['done'][] = $member_id;
		}
	}

	/**
	 * Notifies members when they are mentioned in other members' posts.
	 */
	protected function handleMentionedNotifications()
	{
		global $smcFunc, $scripturl, $language, $modSettings;

		$msgOptions = &$this->_details['msgOptions'];

		foreach ($this->members['mentioned'] as $member_id => $member_data)
		{
			if (in_array($member_id, $this->members['done']))
				continue;

			if (empty($this->prefs[$member_id]) || empty($this->prefs[$member_id]['msg_mention']))
				continue;
			else
				$pref = $this->prefs[$member_id]['msg_mention'];

			// Mentioning yourself is silly, and we aren't going to notify you about it.
			if ($member_id == $member_data['mentioned_by']['id'])
				continue;

			// Bitwise check: Receiving an alert?
			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$this->alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member_data['id'],
					'id_member_started' => $member_data['mentioned_by']['id'],
					'member_name' => $member_data['mentioned_by']['name'],
					'content_type' => 'msg',
					'content_id' => $msgOptions['id'],
					'content_action' => 'mention',
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](array(
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
					)),
				);
			}


			// Bitwise check: Receiving a email notification?
			if (!($pref & self::RECEIVE_NOTIFY_EMAIL))
			{
				// Don't want an email, so forget this member in any respawned tasks.
				unset($msgOptions['mentioned_members'][$member_id]);
			}
			elseif (TIME_START >= $this->mention_mail_time || in_array($member_id, $this->members['watching']))
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'MENTIONNAME' => $member_data['mentioned_by']['name'],
					'MEMBERNAME' => $member_data['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_mention', $replacements, empty($member_data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member_data['lngfile']);
				$mail_result = sendmail($member_data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_mention_' . $msgOptions['id'], $emaildata['is_html'], 2);

				if ($mail_result !== false)
				{
					// Don't send multiple notifications about the same post.
					$this->members['emailed'][] = $member_id;

					// Ensure respawned tasks don't send this again.
					unset($msgOptions['mentioned_members'][$member_id]);
				}
			}

			$this->members['done'][] = $member_id;
		}
	}
}

?>