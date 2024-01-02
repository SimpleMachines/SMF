<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Actions\Notify;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Mail;
use SMF\Theme;
use SMF\Utils;

/**
 * Send out a daily or weekly email of all subscribed topics.
 */
class SendDigests extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		Theme::loadEssential();

		$is_weekly = !empty($this->_details['is_weekly']) ? 1 : 0;

		// Right - get all the notification data FIRST.
		$members = [];
		$langs = [];
		$notify = [];

		$request = Db::$db->query(
			'',
			'SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name,
				mem.lngfile, mem.id_member
			FROM {db_prefix}log_notify AS ln
				JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
				LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
			WHERE mem.is_activated = {int:is_activated}',
			[
				'empty_topic' => 0,
				'is_activated' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($members[$row['id_member']])) {
				$members[$row['id_member']] = [
					'email' => $row['email_address'],
					'name' => $row['member_name'],
					'id' => $row['id_member'],
					'lang' => $row['lngfile'],
				];

				$langs[$row['lngfile']] = $row['lngfile'];
			}

			// Store this useful data!
			$boards[$row['id_board']] = $row['id_board'];

			if ($row['id_topic']) {
				$notify['topics'][$row['id_topic']][] = $row['id_member'];
			} else {
				$notify['boards'][$row['id_board']][] = $row['id_member'];
			}
		}
		Db::$db->free_result($request);

		if (empty($boards)) {
			return true;
		}

		// Just get the board names.
		$request = Db::$db->query(
			'',
			'SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:board_list})',
			[
				'board_list' => $boards,
			],
		);
		$boards = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[$row['id_board']] = $row['name'];
		}
		Db::$db->free_result($request);

		if (empty($boards)) {
			return true;
		}

		// Get the actual topics...
		$types = [];
		$request = Db::$db->query(
			'',
			'SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject,
				b.name AS board_name
			FROM {db_prefix}log_digest AS ld
				JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
					AND t.id_board IN ({array_int:board_list}))
				JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . ($is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
			[
				'board_list' => array_keys($boards),
				'daily_value' => 2,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($types[$row['note_type']][$row['id_board']])) {
				$types[$row['note_type']][$row['id_board']] = [
					'lines' => [],
					'name' => $row['board_name'],
					'id' => $row['id_board'],
				];
			}

			if ($row['note_type'] == 'reply') {
				if (isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']])) {
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['count']++;
				} else {
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = [
						'id' => $row['id_topic'],
						'subject' => Utils::htmlspecialcharsDecode($row['subject']),
						'count' => 1,
					];
				}
			} elseif ($row['note_type'] == 'topic') {
				if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']])) {
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = [
						'id' => $row['id_topic'],
						'subject' => Utils::htmlspecialcharsDecode($row['subject']),
					];
				}
			} elseif (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']])) {
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = [
					'id' => $row['id_topic'],
					'subject' => Utils::htmlspecialcharsDecode($row['subject']),
					'starter' => $row['id_member_started'],
				];
			}

			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = [];

			if (!empty($notify['topics'][$row['id_topic']])) {
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);
			}

			if (!empty($notify['boards'][$row['id_board']])) {
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
			}
		}
		Db::$db->free_result($request);

		if (empty($types)) {
			return true;
		}

		// Let's load all the languages into a cache thingy.
		$langtxt = [];

		foreach ($langs as $lang) {
			Lang::load('Post', $lang);
			Lang::load('index', $lang);
			Lang::load('EmailTemplates', $lang);

			$langtxt[$lang] = [
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
			];

			IntegrationHook::call('integrate_daily_digest_lang', [&$langtxt, $lang]);
		}

		// The preferred way...
		$prefs = Notify::getNotifyPrefs(array_keys($members), ['msg_notify_type', 'msg_notify_pref'], true);

		// Right - send out the silly things - this will take quite some space!
		$members_sent = [];

		foreach ($members as $mid => $member) {
			$frequency = $prefs[$mid]['msg_notify_pref'] ?? 0;

			$notify_types = !empty($prefs[$mid]['msg_notify_type']) ? $prefs[$mid]['msg_notify_type'] : 1;

			// Did they not elect to choose this?
			if ($frequency < 3 || $frequency == 4 && !$is_weekly || $frequency == 3 && $is_weekly || $notify_types == 4) {
				continue;
			}

			// Right character set!
			Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? $langtxt[$lang]['char_set'] : Config::$modSettings['global_character_set'];

			// Do the start stuff!
			$email = [
				'subject' => Config::$mbname . ' - ' . $langtxt[$lang]['subject'],
				'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . Config::$scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
				'email' => $member['email'],
			];

			// All new topics?
			if (isset($types['topic'])) {
				$titled = false;

				foreach ($types['topic'] as $id => $board) {
					foreach ($board['lines'] as $topic) {
						if (in_array($mid, $topic['members'])) {
							if (!$titled) {
								$email['body'] .= "\n" . $langtxt[$lang]['new_topics'] . ':' . "\n" . '-----------------------------------------------';
								$titled = true;
							}

							$email['body'] .= "\n" . sprintf($langtxt[$lang]['topic_lines'], $topic['subject'], $board['name']);
						}
					}
				}

				if ($titled) {
					$email['body'] .= "\n";
				}
			}

			// What about replies?
			if (isset($types['reply'])) {
				$titled = false;

				foreach ($types['reply'] as $id => $board) {
					foreach ($board['lines'] as $topic) {
						if (in_array($mid, $topic['members'])) {
							if (!$titled) {
								$email['body'] .= "\n" . $langtxt[$lang]['new_replies'] . ':' . "\n" . '-----------------------------------------------';
								$titled = true;
							}

							$email['body'] .= "\n" . ($topic['count'] == 1 ? sprintf($langtxt[$lang]['replies_one'], $topic['subject']) : sprintf($langtxt[$lang]['replies_many'], $topic['count'], $topic['subject']));
						}
					}
				}

				if ($titled) {
					$email['body'] .= "\n";
				}
			}

			// Finally, moderation actions!
			if ($notify_types < 3) {
				$titled = false;

				foreach ($types as $note_type => $type) {
					if ($note_type == 'topic' || $note_type == 'reply') {
						continue;
					}

					foreach ($type as $id => $board) {
						foreach ($board['lines'] as $topic) {
							if (in_array($mid, $topic['members'])) {
								if (!$titled) {
									$email['body'] .= "\n" . $langtxt[$lang]['mod_actions'] . ':' . "\n" . '-----------------------------------------------';
									$titled = true;
								}

								$email['body'] .= "\n" . sprintf($langtxt[$lang][$note_type], $topic['subject']);
							}
						}
					}
				}
			}

			IntegrationHook::call('integrate_daily_digest_email', [&$email, $types, $notify_types, $langtxt]);

			if ($titled) {
				$email['body'] .= "\n";
			}

			// Then just say our goodbyes!
			$email['body'] .= "\n\n" . sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']);

			// Send it - low priority!
			Mail::send($email['email'], $email['subject'], $email['body'], null, 'digest', false, 4);

			$members_sent[] = $mid;
		}

		// Clean up...
		if ($is_weekly) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_digest
				WHERE daily != {int:not_daily}',
				[
					'not_daily' => 0,
				],
			);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_digest
				SET daily = {int:daily_value}
				WHERE daily = {int:not_daily}',
				[
					'daily_value' => 2,
					'not_daily' => 0,
				],
			);
		} else {
			// Clear any only weekly ones, and stop us from sending daily again.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_digest
				WHERE daily = {int:daily_value}',
				[
					'daily_value' => 2,
				],
			);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_digest
				SET daily = {int:both_value}
				WHERE daily = {int:no_value}',
				[
					'both_value' => 1,
					'no_value' => 0,
				],
			);
		}

		// Just in case the member changes their settings mark this as sent.
		if (!empty($members_sent)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_notify
				SET sent = {int:is_sent}
				WHERE id_member IN ({array_int:member_list})',
				[
					'member_list' => $members_sent,
					'is_sent' => 1,
				],
			);
		}

		return true;
	}
}

?>