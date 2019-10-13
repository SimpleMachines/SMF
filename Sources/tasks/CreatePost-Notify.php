<?php
/**
 * This file contains background notification code for any create post action
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Class CreatePost_Notify_Background
 */
class CreatePost_Notify_Background extends SMF_BackgroundTask
{
	/**
	 * Constants for reply types.
	*/
	const NOTIFY_TYPE_REPLY_AND_MODIFY = 1;
	const NOTIFY_TYPE_REPLY_AND_TOPIC_START_FOLLOWING = 2;
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
     * This handles notifications when a new post is created - new topic, reply, quotes and mentions.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $language, $modSettings, $user_info;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Mentions.php');
		require_once($sourcedir . '/Subs-Notify.php');
		require_once($sourcedir . '/Subs.php');
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();

		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];
		$type = $this->_details['type'];

		$members = array();
		$quotedMembers = array();
		$done_members = array();
		$alert_rows = array();
		$receiving_members = array();

		if ($type == 'reply' || $type == 'topic')
		{
			$quotedMembers = self::getQuotedMembers($msgOptions, $posterOptions);
			$members = array_keys($quotedMembers);
		}

		// Insert the post mentions
		if (!empty($msgOptions['mentioned_members']))
		{
			Mentions::insertMentions('msg', $msgOptions['id'], $msgOptions['mentioned_members'], $posterOptions['id']);
			$members = array_merge($members, array_keys($msgOptions['mentioned_members']));
		}

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
			WHERE ln.id_topic = {int:topic}
				OR ln.id_board = {int:board}',
			array(
				'topic' => $topicOptions['id'],
				'board' => $topicOptions['board'],
			)
		);

		$watched = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$groups = array_merge(array($row['id_group'], $row['id_post_group']), (empty($row['additional_groups']) ? array() : explode(',', $row['additional_groups'])));

			if (!in_array(1, $groups) && count(array_intersect($groups, explode(',', $row['member_groups']))) == 0)
				continue;
			else
			{
				$row['groups'] = $groups;
				unset($row['id_group'], $row['id_post_group'], $row['additional_groups']);
			}

			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}

		$smcFunc['db_free_result']($request);

		// Modified post
		if ($type == 'edit')
		{
			// Filter out members who have already been notified about this post's topic
			$unnotified = array_filter($watched, function ($member)
			{
				return empty($member['sent']);
			});
			$members = array_intersect($members, array_keys($unnotified));
			$quotedMembers = array_intersect_key($quotedMembers, $unnotified);
			$msgOptions['mentioned_members'] = array_intersect_key($msgOptions['mentioned_members'], $unnotified);

			// Notifications about modified posts only go to members who were mentioned or quoted
			$watched = array();
		}

		if (empty($members))
			return true;

		$members = array_unique($members);
		$prefs = getNotifyPrefs($members, '', true);

		// May as well disable these, since they'll be stripped out anyway.
		$disable = array('attach', 'img', 'iurl', 'url', 'youtube');
		if (!empty($modSettings['disabledBBC']))
		{
			$disabledBBC = $modSettings['disabledBBC'];
			$disable = array_unique(array_merge($disable, explode(',', $modSettings['disabledBBC'])));
		}
		$modSettings['disabledBBC'] = implode(',', $disable);

		// Do we have anyone to notify via mention? Handle them first and cross them off the list
		if (!empty($msgOptions['mentioned_members']))
		{
			$mentioned_members = Mentions::getMentionsByContent('msg', $msgOptions['id'], array_keys($msgOptions['mentioned_members']));
			self::handleMentionedNotifications($msgOptions, $mentioned_members, $prefs, $done_members, $alert_rows);
		}

		// Notify members which might've been quoted
		self::handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, $done_members, $alert_rows);

		// Save ourselves a bit of work in the big loop below
		foreach ($done_members as $done_member)
		{
			$receiving_members[] = $done_member;
			unset($watched[$done_member]);
		}

		$parsed_message = array();

		// Handle rest of the notifications for watched topics and boards
		foreach ($watched as $member => $data)
		{
			$frequency = isset($prefs[$member]['msg_notify_pref']) ? $prefs[$member]['msg_notify_pref'] : self::FREQUENCY_NOTHING;
			$notify_types = !empty($prefs[$member]['msg_notify_type']) ? $prefs[$member]['msg_notify_type'] : self::NOTIFY_TYPE_REPLY_AND_MODIFY;

			// Don't send a notification if the watching member ignored the member who made the action.
			if (!empty($data['pm_ignore_list']) && in_array($data['id_member_updated'], explode(',', $data['pm_ignore_list'])))
				continue;
			if (!in_array($type, array('reply', 'topic')) && $notify_types == self::NOTIFY_TYPE_REPLY_AND_TOPIC_START_FOLLOWING && $member != $data['id_member_started'])
				continue;
			elseif (in_array($type, array('reply', 'topic')) && $member == $posterOptions['id'])
				continue;
			elseif (!in_array($type, array('reply', 'topic')) && $notify_types == self::NOTIFY_TYPE_ONLY_REPLIES)
				continue;
			elseif ($notify_types == self::NOTIFY_TYPE_NOTHING)
				continue;

			// Don't send a notification if they don't want any...
			if (in_array($frequency, array(self::FREQUENCY_NOTHING, self::FREQUENCY_DAILY_DIGEST, self::FREQUENCY_WEEKLY_DIGEST)))
				continue;
			// ... or if we already sent one and they don't want more...
			elseif ($frequency == self::FREQUENCY_FIRST_UNREAD_MSG && $data['sent'])
				continue;
			// ... or if they aren't on the bouncer's list.
			elseif (!empty($this->_details['members_only']) && !in_array($member, $this->_details['members_only']))
				continue;

			// Watched topic?
			if (!empty($data['id_topic']) && $type != 'topic' && !empty($prefs[$member]))
			{
				$pref = !empty($prefs[$member]['topic_notify_' . $topicOptions['id']]) ? $prefs[$member]['topic_notify_' . $topicOptions['id']] : (!empty($prefs[$member]['topic_notify']) ? $prefs[$member]['topic_notify'] : 0);
				$message_type = 'notification_' . $type;

				if ($type == 'reply')
				{
					if (!empty($prefs[$member]['msg_receive_body']))
						$message_type .= '_body';
					if (!empty($frequency))
						$message_type .= '_once';
				}

				$content_type = 'topic';
			}
			// A new topic in a watched board then?
			elseif ($type == 'topic')
			{
				$pref = !empty($prefs[$member]['board_notify_' . $topicOptions['board']]) ? $prefs[$member]['board_notify_' . $topicOptions['board']] : (!empty($prefs[$member]['board_notify']) ? $prefs[$member]['board_notify'] : 0);

				$content_type = 'board';

				$message_type = !empty($frequency) ? 'notify_boards_once' : 'notify_boards';
				if (!empty($prefs[$member]['msg_receive_body']))
					$message_type .= '_body';
			}
			// If neither of the above, this might be a redundant row due to the OR clause in our SQL query, skip
			else
				continue;

			$receiver_lang = empty($data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $data['lngfile'];

			// We need to fake some of $user_info to make BBC parsing work correctly.
			if (isset($user_info))
				$real_user_info = $user_info;

			$user_info = array(
				'id' => $member,
				'language' => $receiver_lang,
				'groups' => $data['groups'],
				'is_guest' => false,
				'time_format' => empty($data['time_format']) ? $modSettings['time_format'] : $data['time_format'],
			);
			$user_info['is_admin'] = in_array(1, $user_info['groups']);

			if (!empty($data['timezone']))
			{
				// Get the offsets from UTC for the server, then for the user.
				$tz_system = new DateTimeZone(@date_default_timezone_get());
				$tz_user = new DateTimeZone($data['timezone']);
				$time_system = new DateTime('now', $tz_system);
				$time_user = new DateTime('now', $tz_user);
				$user_info['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
			}
			else
				$user_info['time_offset'] = empty($data['time_offset']) ? 0 : $data['time_offset'];

			// Censor and parse BBC in the receiver's localization. Don't repeat unnecessarily.
			$localization = implode('|', array($receiver_lang, $user_info['time_offset'], $user_info['time_format']));
			if (empty($parsed_message[$localization]))
			{
				loadLanguage('index+Modifications', $receiver_lang, false);

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

			// Bitwise check: Receiving a email notification?
			if ($pref & self::RECEIVE_NOTIFY_EMAIL)
			{
				$replacements = array(
					'TOPICSUBJECT' => $parsed_message[$localization]['subject'],
					'POSTERNAME' => un_htmlspecialchars($posterOptions['name']),
					'TOPICLINK' => $scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
					'MESSAGE' => $parsed_message[$localization]['body'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicOptions['board'] . '.0',
				);

				$emaildata = loadEmailTemplate($message_type, $replacements, $receiver_lang);
				$mail_result = sendmail($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id'], $emaildata['is_html']);

				// We failed, don't trigger a alert as we don't have a way to attempt to resend just the email currently.
				if ($mail_result === false)
					continue;
			}

			// Bitwise check: Receiving a alert?
			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member,
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
						'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
					)),
				);

				$receiving_members[] = $member;
			}

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_notify
				SET sent = {int:is_sent}
				WHERE (id_topic = {int:topic} OR id_board = {int:board})
					AND id_member = {int:member}',
				array(
					'topic' => $topicOptions['id'],
					'board' => $topicOptions['board'],
					'member' => $member,
					'is_sent' => 1,
				)
			);
		}

		// Put this back the way we found it.
		if (!empty($disabledBBC))
			$modSettings['disabledBBC'] = $disabledBBC;

		// Insert it into the digest for daily/weekly notifications
		$smcFunc['db_insert']('',
			'{db_prefix}log_digest',
			array(
				'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
			),
			array($topicOptions['id'], $msgOptions['id'], $type, $posterOptions['id']),
			array()
		);

		// Insert the alerts if any
		if (!empty($alert_rows))
			$smcFunc['db_insert']('',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$alert_rows,
				array()
			);

		if (!empty($receiving_members))
			updateMemberData($receiving_members, array('alerts' => '+'));

		return true;
	}

	protected static function handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, &$done_members, &$alert_rows)
	{
		global $smcFunc, $modSettings, $language, $scripturl;

		foreach ($quotedMembers as $id => $member)
		{
			if (!isset($prefs[$id]) || $id == $posterOptions['id'] || empty($prefs[$id]['msg_quote']))
				continue;

			$done_members[] = $id;

			// Bitwise check: Receiving a email notification?
			if ($prefs[$id]['msg_quote'] & self::RECEIVE_NOTIFY_EMAIL)
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'QUOTENAME' => $posterOptions['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_quote', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_quote_' . $msgOptions['id'], $emaildata['is_html'], 2);
			}

			// Bitwise check: Receiving a alert?
			if ($prefs[$id]['msg_quote'] & self::RECEIVE_NOTIFY_ALERT)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member['id_member'],
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
		}
	}

	protected static function getQuotedMembers($msgOptions, $posterOptions)
	{
		global $smcFunc;

		$blocks = preg_split('/(\[quote.*?\]|\[\/quote\])/i', $msgOptions['body'], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$quote_level = 0;
		$message = '';

		foreach ($blocks as $block)
		{
			if (preg_match('/\[quote(.*)?\]/i', $block, $matches))
			{
				if ($quote_level == 0)
					$message .= '[quote' . $matches[1] . ']';
				$quote_level++;
			}
			elseif (preg_match('/\[\/quote\]/i', $block))
			{
				if ($quote_level <= 1)
					$message .= '[/quote]';
				if ($quote_level >= 1)
				{
					$quote_level--;
					$message .= "\n";
				}
			}
			elseif ($quote_level <= 1)
				$message .= $block;
		}

		preg_match_all('/\[quote.*?link=msg=([0-9]+).*?\]/i', $message, $matches);

		$id_msgs = $matches[1];
		foreach ($id_msgs as $k => $id_msg)
			$id_msgs[$k] = (int) $id_msg;

		if (empty($id_msgs))
			return array();

		// Get the messages
		$request = $smcFunc['db_query']('', '
			SELECT m.id_member, mem.email_address, mem.lngfile, mem.real_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE id_msg IN ({array_int:msgs})
			LIMIT {int:count}',
			array(
				'msgs' => array_unique($id_msgs),
				'count' => count(array_unique($id_msgs)),
			)
		);

		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($posterOptions['id'] == $row['id_member'])
				continue;

			$members[$row['id_member']] = $row;
		}

		return $members;
	}

	protected static function handleMentionedNotifications($msgOptions, $members, $prefs, &$done_members, &$alert_rows)
	{
		global $smcFunc, $scripturl, $language, $modSettings;

		foreach ($members as $id => $member)
		{
			if (!empty($prefs[$id]['msg_mention']))
				$done_members[] = $id;
			else
				continue;

			// Alerts' emails are always instant
			if ($prefs[$id]['msg_mention'] & self::RECEIVE_NOTIFY_EMAIL)
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'MENTIONNAME' => $member['mentioned_by']['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_mention', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_mention_' . $msgOptions['id'], $emaildata['is_html'], 2);
			}

			if ($prefs[$id]['msg_mention'] & self::RECEIVE_NOTIFY_ALERT)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member['id'],
					'id_member_started' => $member['mentioned_by']['id'],
					'member_name' => $member['mentioned_by']['name'],
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
		}
	}
}

?>