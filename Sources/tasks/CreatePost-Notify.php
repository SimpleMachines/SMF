<?php
/**
 * This file contains background notification code for any create post action
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

class CreatePost_Notify_Background extends SMF_BackgroundTask
{
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $language, $modSettings, $language;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Mentions.php');
		require_once($sourcedir . '/Subs-Notify.php');

		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];
		$type = $this->_details['type'];

		$members = array();
		$quotedMembers = array();
		$done_members = array();
		$alert_rows = array();

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
			SELECT mem.id_member, ln.id_topic, ln.id_board, ln.sent, mem.email_address, b.member_groups,
				mem.id_group, mem.id_post_group, mem.additional_groups, t.id_member_started
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
			$groups = array_merge(array($row['id_group'], $row['id_post_group']), explode(',', $row['additional_groups']));
			if (!in_array(1, $groups) && count(array_intersect($groups, explode(',', $row['member_groups']))) == 0)
				continue;

			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}

		$smcFunc['db_free_result']($request);

		if (empty($members))
			return true;

		$members = array_unique($members);
		$prefs = getNotifyPrefs($members);

		// Do we have anyone to notify via mention? Handle them first and cross them off the list
		if (!empty($msgOptions['mentioned_members']))
		{
			$mentioned_members = Mentions::getMentionsByContent('msg', $msgOptions['id'], array_keys($msgOptions['mentioned_members']));
			self::handleMentionedNotifications($msgOptions, $mentioned_members, $prefs, $done_members, $alert_rows);
		}

		// Notify members which might've been quoted
		self::handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, $done_members, $alert_rows);

		// Handle rest of the notifications for watched topics and boards
		foreach ($watched as $member => $data)
		{
			$frequency = !empty($prefs[$member]['msg_notify_type']) ? $prefs[$member]['msg_notify_pref'] : 1;
			$notify_types = !empty($prefs[$member]['msg_notify_type']) ? $prefs[$member]['msg_notify_type'] : 1;

			if (!in_array($type, array('reply', 'topic')) && $notify_types == 2 && $member != $data['id_member_started'])
				continue;
			elseif (in_array($type, array('reply', 'topic')) && $member == $posterOptions['id'])
				continue;
			elseif (!in_array($type, array('reply', 'topic')) && $notify_types == 3)
				continue;
			elseif ($notify_types == 4)
				continue;

			if ($frequency > 2 || (!empty($frequency) && $data['sent']) || in_array($member, $done_members)
				|| (!empty($this->_details['members_only']) && !in_array($member, $this->_details['members_only'])))
				continue;

			// Watched topic?
			if (!empty($data['id_topic']) && $type != 'topic')
			{
				$pref = !empty($prefs[$member]['topic_notify_' . $topicOptions['id']]) ? $prefs[$member]['topic_notify_' . $topicOptions['id']] : $prefs[$member]['topic_notify'];
				$message_type = 'notification_' . $type;

				if (!empty($frequency) && $type == 'reply')
					$message_type .= '_once';

				$content_type = 'topic';
			}
			// A new topic in a watched board then?
			elseif ($type == 'topic')
			{
				$pref = !empty($prefs[$member]['board_notify_' . $topicOptions['board']]) ? $prefs[$member]['board_notify_' . $topicOptions['board']] : $prefs[$member]['board_notify'];

				$content_type = 'board';

				$message_type = !empty($frequency) ? 'notify_boards_once' : 'notify_boards';
			}
			// If neither of the above, this might be a redundent row due to the OR clause in our SQL query, skip
			else
				continue;

			if (!empty($prefs[$member]['msg_receive_body']) && in_array($type, array('topic', 'reply')))
				$message_type .= '_body';

			if ($pref & 0x02)
			{
				$replacements = array(
					'TOPICSUBJECT' => $msgOptions['subject'],
					'POSTERNAME' => un_htmlspecialchars($posterOptions['name']),
					'TOPICLINK' => $scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
					'MESSAGE' => $msgOptions['body'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicOptions['board'] . '.0',
				);

				$emaildata = loadEmailTemplate($message_type, $replacements, empty($data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $data['lngfile']);
				sendmail($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id']);
			}

			if ($pref & 0x01)
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
					'extra' => serialize(array(
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
					)),
				);
				updateMemberData($member, array('alerts' => '+'));
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

		return true;
	}

	protected static function handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, &$done_members, &$alert_rows)
	{
		global $modSettings, $language, $scripturl;

		foreach ($quotedMembers as $id => $member)
		{
			if (!isset($prefs[$id]) || $id == $posterOptions['id'])
				continue;

			if (!empty($prefs[$id]['msg_quote']))
				$done_members[] = $id;

			if ($prefs[$id]['msg_quote'] & 0x02)
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'QUOTENAME' => $posterOptions['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_quote', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_quote_' . $msgOptions['id'], false, 2);
			}

			if ($prefs[$id]['msg_quote'] & 0x01)
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
					'extra' => serialize(array(
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
					)),
				);

				updateMemberData($member['id_member'], array('alerts' => '+'));
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
		global $scripturl, $language, $modSettings;

		foreach ($members as $id => $member)
		{
			if (!empty($prefs[$id]['msg_mention']))
				$done_members[] = $id;
			else
				continue;

			// Alerts' emails are always instant
			if ($prefs[$id]['msg_mention'] & 0x02)
			{
				$replacements = array(
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'MENTIONNAME' => $member['mentioned_by']['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				);

				$emaildata = loadEmailTemplate('msg_mention', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_mention_' . $msgOptions['id'], false, 2);
			}

			if ($prefs[$id]['msg_mention'] & 0x01)
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
					'extra' => serialize(array(
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
					)),
				);

				updateMemberData($member['id'], array('alerts' => '+'));
			}
		}
	}
}
?>