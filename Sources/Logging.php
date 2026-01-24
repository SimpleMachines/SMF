<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use SMF\Actions\Moderation\ReportedContent;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * This class concerns itself with logging and tracking statistics.
 *
 * Honestly, this is just a collection of a few static methods. They're only
 * grouped into a class to take better advantage of namespacing and autoloading.
 */
class Logging
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * This function logs an action to the database. It is a
	 * thin wrapper around Logging::logActions().
	 *
	 * @example Logging::logAction('remove', array('starter' => $id_member_started));
	 *
	 * @param string $action A code for the report; a list of such strings
	 * can be found in Modlog.{language}.php (modlog_ac_ strings)
	 * @param array $extra An associated array of parameters for the
	 * item being logged. Typically this will include 'topic' for the topic's id.
	 * @param string $log_type A string reflecting the type of log.
	 *
	 * @return int The ID of the row containing the logged data
	 */
	public static function logAction(string $action, array $extra = [], string $log_type = 'moderate'): int
	{
		return self::logActions([[
			'action' => $action,
			'log_type' => $log_type,
			'extra' => $extra,
		]]);
	}

	/**
	 * Log changes to the forum, such as moderation events or administrative
	 * changes.
	 *
	 * SMF uses three log types:
	 *
	 * - `user` for actions executed that aren't related to
	 *    moderation (e.g. signature or other changes from the profile);
	 * - `moderate` for moderation actions (e.g. topic changes);
	 * - `admin` for administrative actions.
	 *
	 * @param array $logs An array of log data
	 *
	 * @return int The last logged ID.
	 */
	public static function logActions(array $logs): int
	{
		$inserts = [];
		$log_types = [
			'moderate' => 1,
			'user' => 2,
			'admin' => 3,
		];
		$always_log = ['agreement_accepted', 'policy_accepted', 'agreement_updated', 'policy_updated'];

		IntegrationHook::call('integrate_log_types', [&$log_types, &$always_log]);

		foreach ($logs as $log) {
			if (!isset($log_types[$log['log_type']]) && (empty(Config::$modSettings[$log['log_type'] . 'log_enabled']) || !\in_array($log['action'], $always_log))) {
				continue;
			}

			if (!\is_array($log['extra'])) {
				throw new \TypeError(Lang::getTxt('logActions_not_array', [$log['action']], file: 'Errors'));
			}

			// Pull out the parts we want to store separately, but also make sure that the data is proper
			if (isset($log['extra']['topic'])) {
				if (!is_numeric($log['extra']['topic'])) {
					throw new \TypeError('logActions_topic_not_numeric');
				}
				$topic_id = empty($log['extra']['topic']) ? 0 : (int) $log['extra']['topic'];
				unset($log['extra']['topic']);
			} else {
				$topic_id = 0;
			}

			if (isset($log['extra']['message'])) {
				if (!is_numeric($log['extra']['message'])) {
					throw new \TypeError('logActions_message_not_numeric');
				}
				$msg_id = empty($log['extra']['message']) ? 0 : (int) $log['extra']['message'];
				unset($log['extra']['message']);
			} else {
				$msg_id = 0;
			}

			// @todo cache this?
			// Is there an associated report on this?
			if (\in_array($log['action'], ['move', 'remove', 'split', 'merge'])) {
				$request = Db::$db->query(
					'SELECT id_report
					FROM {db_prefix}log_reported
					WHERE {raw:column_name} = {int:reported}
					LIMIT 1',
					[
						'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
						'reported' => !empty($msg_id) ? $msg_id : $topic_id,
					],
				);

				// Alright, if we get any result back, update open reports.
				if (Db::$db->num_rows($request) > 0) {
					Config::updateModSettings(['last_mod_report_action' => time()]);
					ReportedContent::recountOpenReports('posts');
				}
				Db::$db->free_result($request);
			}

			if (isset($log['extra']['member']) && !is_numeric($log['extra']['member'])) {
				throw new \TypeError('logActions_member_not_numeric');
			}

			if (isset($log['extra']['board'])) {
				if (!is_numeric($log['extra']['board'])) {
					throw new \TypeError('logActions_board_not_numeric');
				}
				$board_id = empty($log['extra']['board']) ? 0 : (int) $log['extra']['board'];
				unset($log['extra']['board']);
			} else {
				$board_id = 0;
			}

			if (isset($log['extra']['board_to'])) {
				if (!is_numeric($log['extra']['board_to'])) {
					throw new \TypeError('logActions_board_to_not_numeric');
				}

				if (empty($board_id)) {
					$board_id = empty($log['extra']['board_to']) ? 0 : (int) $log['extra']['board_to'];
					unset($log['extra']['board_to']);
				}
			}

			if (isset($log['extra']['member_affected'])) {
				$memID = $log['extra']['member_affected'];
			} else {
				$memID = User::$me->id ?? $log['extra']['member'] ?? 0;
			}

			if (isset(User::$me->ip)) {
				$memIP = User::$me->ip;
			} else {
				$memIP = 'null';
			}

			$inserts[] = [
				time(), $log_types[$log['log_type']], $memID, $memIP, $log['action'],
				$board_id, $topic_id, $msg_id, Utils::jsonEncode($log['extra']),
			];
		}

		$id_action = Db::$db->insert(
			'',
			'{db_prefix}log_actions',
			[
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'inet', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			],
			$inserts,
			['id_action'],
			Db::INSERT_RETURN_MODE_SINGLE,
		);

		return $id_action;
	}

	/**
	 * Update some basic statistics.
	 *
	 * 'member' statistic updates the latest member, the total member
	 *  count, and the number of unapproved members.
	 * 'member' also only counts approved members when approval is on, but
	 *  is much more efficient with it off.
	 *
	 * 'message' changes the total number of messages, and the
	 *  highest message id by id_msg - which can be parameters 1 and 2,
	 *  respectively.
	 *
	 * 'topic' updates the total number of topics, or if parameter1 is true
	 *  simply increments them.
	 *
	 * 'subject' updates the log_search_subjects in the event of a topic being
	 *  moved, removed or split.  parameter1 is the topicid, parameter2 is the new subject
	 *
	 * 'postgroups' case updates those members who match condition's
	 *  post-based membergroups in the database (restricted by parameter1).
	 *
	 * @param string $type Stat type - can be 'member', 'message', 'topic', 'subject' or 'postgroups'
	 * @param mixed $parameter1 A parameter for updating the stats
	 * @param mixed $parameter2 A 2nd parameter for updating the stats
	 */
	public static function updateStats(string $type, mixed $parameter1 = null, mixed $parameter2 = null): void
	{
		switch ($type) {
			case 'member':
				$changes = [
					'memberlist_updated' => time(),
				];

				// #1 latest member ID, #2 the real name for a new registration.
				if (is_numeric($parameter1)) {
					$changes['latestMember'] = $parameter1;
					$changes['latestRealName'] = $parameter2;

					Config::updateModSettings(['totalMembers' => true], true);
				}
				// We need to calculate the totals.
				else {
					// Update the latest activated member (highest id_member) and count.
					$result = Db::$db->query(
						'SELECT COUNT(*), MAX(id_member)
						FROM {db_prefix}members
						WHERE is_activated = {int:is_activated}',
						[
							'is_activated' => User::ACTIVATED,
						],
					);
					list($changes['totalMembers'], $changes['latestMember']) = Db::$db->fetch_row($result);
					Db::$db->free_result($result);

					// Get the latest activated member's display name.
					$result = Db::$db->query(
						'SELECT real_name
						FROM {db_prefix}members
						WHERE id_member = {int:id_member}
						LIMIT 1',
						[
							'id_member' => (int) $changes['latestMember'],
						],
					);
					list($changes['latestRealName']) = Db::$db->fetch_row($result);
					Db::$db->free_result($result);

					// Update the amount of members awaiting approval
					$result = Db::$db->query(
						'SELECT COUNT(*)
						FROM {db_prefix}members
						WHERE is_activated IN ({array_int:activation_status})',
						[
							'activation_status' => [User::UNAPPROVED, User::REQUESTED_DELETE, User::REQUESTED_DELETE_ANONYMIZE, User::NEED_COPPA],
						],
					);

					list($changes['unapprovedMembers']) = Db::$db->fetch_row($result);
					Db::$db->free_result($result);
				}
				Config::updateModSettings($changes);

				break;

			case 'message':
				if ($parameter1 === true && $parameter2 !== null) {
					Config::updateModSettings(['totalMessages' => true, 'maxMsgID' => $parameter2], true);
				} else {
					// SUM and MAX on a smaller table is better for InnoDB tables.
					$result = Db::$db->query(
						'SELECT SUM(num_posts + unapproved_posts) AS total_messages, MAX(id_last_msg) AS max_msg_id
						FROM {db_prefix}boards
						WHERE redirect = {string:blank_redirect}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
							AND id_board != {int:recycle_board}' : ''),
						[
							'recycle_board' => Config::$modSettings['recycle_board'] ?? 0,
							'blank_redirect' => '',
						],
					);
					$row = Db::$db->fetch_assoc($result);
					Db::$db->free_result($result);

					Config::updateModSettings([
						'totalMessages' => $row['total_messages'] === null ? 0 : $row['total_messages'],
						'maxMsgID' => $row['max_msg_id'] === null ? 0 : $row['max_msg_id'],
					]);
				}

				break;

			case 'subject':
				// Remove the previous subject (if any).
				Db::$db->query(
					'DELETE FROM {db_prefix}log_search_subjects
					WHERE id_topic = {int:id_topic}',
					[
						'id_topic' => (int) $parameter1,
					],
				);

				// Insert the new subject.
				if ($parameter2 !== null) {
					$parameter1 = (int) $parameter1;
					$parameter2 = array_map(
						fn($word) => Utils::truncate($word, 20),
						Utils::extractWords($parameter2, 2),
					);

					$inserts = [];

					foreach ($parameter2 as $word) {
						$inserts[] = [$word, $parameter1];
					}

					if (!empty($inserts)) {
						Db::$db->insert(
							'ignore',
							'{db_prefix}log_search_subjects',
							['word' => 'string', 'id_topic' => 'int'],
							$inserts,
							['word', 'id_topic'],
						);
					}
				}

				break;

			case 'topic':
				if ($parameter1 === true) {
					Config::updateModSettings(['totalTopics' => true], true);
				} else {
					// Get the number of topics - a SUM is better for InnoDB tables.
					// We also ignore the recycle bin here because there will probably be a bunch of one-post topics there.
					$result = Db::$db->query(
						'SELECT SUM(num_topics + unapproved_topics) AS total_topics
						FROM {db_prefix}boards' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
						WHERE id_board != {int:recycle_board}' : ''),
						[
							'recycle_board' => !empty(Config::$modSettings['recycle_board']) ? Config::$modSettings['recycle_board'] : 0,
						],
					);
					$row = Db::$db->fetch_assoc($result);
					Db::$db->free_result($result);

					Config::updateModSettings(['totalTopics' => $row['total_topics'] === null ? 0 : $row['total_topics']]);
				}

				break;

			case 'postgroups':
				// Parameter two is the updated columns: we should check to see if we base groups off any of these.
				if ($parameter2 !== null && !\in_array('posts', $parameter2)) {
					return;
				}

				$postgroups = CacheApi::get('updateStats:postgroups', 360);

				if ($postgroups == null || $parameter1 == null) {
					// Fetch the postgroups!
					$postgroups = Group::getPostGroups();

					CacheApi::put('updateStats:postgroups', $postgroups, 360);
				}

				// Oh great, they've screwed their post groups.
				if (empty($postgroups)) {
					return;
				}

				// Set all membergroups from most posts to least posts.
				$conditions = '';
				$last_min = 0;

				foreach ($postgroups as $id => $min_posts) {
					foreach (User::$loaded as $member) {
						if ($member->posts < $min_posts) {
							continue;
						}

						if (empty($last_min) || $member->posts <= $last_min) {
							$member->post_group_id = $id;
						}
					}

					$conditions .= '
						WHEN posts >= ' . $min_posts . (!empty($last_min) ? ' AND posts <= ' . $last_min : '') . ' THEN ' . $id;

					$last_min = $min_posts;
				}

				// A big fat CASE WHEN... END is faster than a zillion UPDATE's ;).
				Db::$db->query(
					'UPDATE {db_prefix}members
					SET id_post_group = CASE ' . $conditions . '
					ELSE 0
					END' . ($parameter1 != null ? '
					WHERE ' . (\is_array($parameter1) ? 'id_member IN ({array_int:members})' : 'id_member = {int:members}') : ''),
					[
						'members' => $parameter1,
					],
				);

				break;

			default:
				throw new \ValueError(Lang::getTxt('invalid_statistic_type', [$type], file: 'Errors'));
		}
	}

	/**
	 * Track Statistics.
	 *
	 * Caches statistics changes, and flushes them if you pass nothing.
	 * If '+' is used as a value, it will be incremented.
	 * It does not actually commit the changes until the end of the page view.
	 * It depends on the trackStats setting.
	 *
	 * @param array $stats An array of data
	 * @return bool Whether or not the info was updated successfully
	 */
	public static function trackStats(array $stats = []): bool
	{
		static $cache_stats = [];

		if (empty(Config::$modSettings['trackStats'])) {
			return false;
		}

		if (!empty($stats)) {
			$cache_stats = array_merge($cache_stats, $stats);

			return true;
		}

		if (empty($cache_stats)) {
			return false;
		}

		$setStringUpdate = '';
		$insert_keys = [];
		$date = Time::strftime('%Y-%m-%d', time());
		$update_parameters = [
			'current_date' => $date,
		];

		foreach ($cache_stats as $field => $change) {
			$setStringUpdate .= '
				' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

			if ($change === '+') {
				$cache_stats[$field] = 1;
			} else {
				$update_parameters[$field] = $change;
			}

			$insert_keys[$field] = 'int';
		}

		Db::$db->query(
			'UPDATE {db_prefix}log_activity
			SET' . substr($setStringUpdate, 0, -1) . '
			WHERE date = {date:current_date}',
			$update_parameters,
		);

		if (Db::$db->affected_rows() == 0) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_activity',
				array_merge($insert_keys, ['date' => 'date']),
				array_merge($cache_stats, [$date]),
				['date'],
			);
		}

		// Don't do this again.
		$cache_stats = [];

		return true;
	}

	/**
	 * Check if the number of users online is a record and store it.
	 *
	 * @param int $total_users_online The total number of members online
	 */
	public static function trackStatsUsersOnline(int $total_users_online): void
	{
		$settingsToUpdate = [];

		// More members on now than ever were?  Update it!
		if (!isset(Config::$modSettings['mostOnline']) || $total_users_online >= Config::$modSettings['mostOnline']) {
			$settingsToUpdate = [
				'mostOnline' => $total_users_online,
				'mostDate' => time(),
			];
		}

		$date = (new \DateTime('now', TimeZone::create(Config::$modSettings['default_timezone'])))->format('Y-m-d');

		// No entry exists for today yet?
		if (!isset(Config::$modSettings['mostOnlineUpdated']) || Config::$modSettings['mostOnlineUpdated'] != $date) {
			$request = Db::$db->query(
				'SELECT most_on
				FROM {db_prefix}log_activity
				WHERE date = {date:date}
				LIMIT 1',
				[
					'date' => $date,
				],
			);

			// The log_activity hasn't got an entry for today?
			if (Db::$db->num_rows($request) === 0) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}log_activity',
					[
						'date' => 'date',
						'most_on' => 'int',
					],
					[
						[
							$date,
							$total_users_online,
						],
					],
					['date'],
				);
			}
			// There's an entry in log_activity on today...
			else {
				list(Config::$modSettings['mostOnlineToday']) = Db::$db->fetch_row($request);

				if ($total_users_online > Config::$modSettings['mostOnlineToday']) {
					self::trackStats(['most_on' => $total_users_online]);
				}

				$total_users_online = max($total_users_online, Config::$modSettings['mostOnlineToday']);
			}
			Db::$db->free_result($request);

			$settingsToUpdate['mostOnlineUpdated'] = $date;
			$settingsToUpdate['mostOnlineToday'] = $total_users_online;
		}
		// Highest number of users online today?
		elseif ($total_users_online > Config::$modSettings['mostOnlineToday']) {
			self::trackStats(['most_on' => $total_users_online]);
			$settingsToUpdate['mostOnlineToday'] = $total_users_online;
		}

		if (!empty($settingsToUpdate)) {
			Config::updateModSettings($settingsToUpdate);
		}
	}

	/**
	 * Retrieve a list and several other statistics of the users currently online.
	 * Used by the board index and SSI.
	 * Also returns the membergroups of the users that are currently online.
	 * (optionally) hides members that chose to hide their online presence.
	 *
	 * @param array $membersOnlineOptions An array of options for the list
	 * @return array An array of information about the online users
	 */
	public static function getMembersOnlineStats(array $membersOnlineOptions): array
	{
		// The list can be sorted in several ways.
		$allowed_sort_options = [
			'', // No sorting.
			'log_time',
			'real_name',
			'show_online',
			'online_color',
			'group_name',
		];

		// Default the sorting method to 'most recent online members first'.
		if (!isset($membersOnlineOptions['sort'])) {
			$membersOnlineOptions['sort'] = 'log_time';
			$membersOnlineOptions['reverse_sort'] = true;
		}

		// Not allowed sort method? Bang! Error!
		if (!\in_array($membersOnlineOptions['sort'], $allowed_sort_options)) {
			throw new \ValueError('get_members_online_stats_invalid_sort');
		}

		// Initialize the array that'll be returned later on.
		$membersOnlineStats = [
			'users_online' => [],
			'list_users_online' => [],
			'online_groups' => [],
			'num_guests' => 0,
			'num_spiders' => 0,
			'num_buddies' => 0,
			'num_users_hidden' => 0,
			'num_users_online' => 0,
		];

		// Get any spiders if enabled.
		$spiders = [];
		$spider_finds = [];

		if (
			!empty(Config::$modSettings['show_spider_online'])
			&& !empty(Config::$modSettings['spider_name_cache'])
			&& (
				Config::$modSettings['show_spider_online'] < 3
				|| User::$me->allowedTo('admin_forum')
			)
		) {
			$spiders = Utils::jsonDecode(Config::$modSettings['spider_name_cache'], true);
		}

		// Load the users online right now.
		$request = Db::$db->query(
			'SELECT
				lo.id_member, lo.log_time, lo.id_spider, mem.real_name, mem.member_name, mem.show_online,
				mg.online_color, mg.id_group, mg.group_name, mg.hidden, mg.group_type, mg.id_parent
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_mem_group} THEN mem.id_post_group ELSE mem.id_group END)',
			[
				'reg_mem_group' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($row['real_name'])) {
				// Do we think it's a spider?
				if ($row['id_spider'] && isset($spiders[$row['id_spider']])) {
					$spider_finds[$row['id_spider']] = isset($spider_finds[$row['id_spider']]) ? $spider_finds[$row['id_spider']] + 1 : 1;

					$membersOnlineStats['num_spiders']++;
				}

				// Guests are only nice for statistics.
				$membersOnlineStats['num_guests']++;

				continue;
			}

			if (empty($row['show_online']) && empty($membersOnlineOptions['show_hidden'])) {
				// Just increase the stats and don't add this hidden user to any list.
				$membersOnlineStats['num_users_hidden']++;

				continue;
			}

			// Some basic color coding...
			if (!empty($row['online_color'])) {
				$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
			} else {
				$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			}

			// Buddies get counted and highlighted.
			$is_buddy = \in_array($row['id_member'], User::$me->buddies);

			if ($is_buddy) {
				$membersOnlineStats['num_buddies']++;
				$link = '<strong>' . $link . '</strong>';
			}

			// A lot of useful information for each member.
			$membersOnlineStats['users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = [
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'group' => $row['id_group'],
				'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => $link,
				'is_buddy' => $is_buddy,
				'hidden' => empty($row['show_online']),
				'is_last' => false,
			];

			// This is the compact version, simply implode it to show.
			$membersOnlineStats['list_users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;

			// Store all distinct (primary) membergroups that are shown.
			if (!isset($membersOnlineStats['online_groups'][$row['id_group']])) {
				$membersOnlineStats['online_groups'][$row['id_group']] = [
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'color' => $row['online_color'],
					'hidden' => $row['hidden'],
					'type' => $row['group_type'],
					'parent' => $row['id_parent'],
				];
			}
		}
		Db::$db->free_result($request);

		// If there are spiders only and we're showing the detail, add them to the online list - at the bottom.
		if (!empty($spider_finds) && Config::$modSettings['show_spider_online'] > 1) {
			$sort = $membersOnlineOptions['sort'] === 'log_time' && $membersOnlineOptions['reverse_sort'] ? 0 : 'zzz_';

			foreach ($spider_finds as $id => $count) {
				$link = $spiders[$id] . ($count > 1 ? ' (' . $count . ')' : '');

				$membersOnlineStats['users_online'][$sort . '_' . $spiders[$id]] = [
					'id' => 0,
					'username' => $spiders[$id],
					'name' => $link,
					'group' => Lang::getTxt('spiders', file: 'General'),
					'href' => '',
					'link' => $link,
					'is_buddy' => false,
					'hidden' => false,
					'is_last' => false,
				];

				$membersOnlineStats['list_users_online'][$sort . '_' . $spiders[$id]] = $link;
			}
		}

		// Time to sort the list a bit.
		if (!empty($membersOnlineStats['users_online'])) {
			// Determine the sort direction.
			$sortFunction = empty($membersOnlineOptions['reverse_sort']) ? 'ksort' : 'krsort';

			// Sort the two lists.
			$sortFunction($membersOnlineStats['users_online']);
			$sortFunction($membersOnlineStats['list_users_online']);

			// Mark the last list item as 'is_last'.
			$userKeys = array_keys($membersOnlineStats['users_online']);
			$membersOnlineStats['users_online'][end($userKeys)]['is_last'] = true;
		}

		// Also sort the membergroups.
		ksort($membersOnlineStats['online_groups']);

		// Hidden and non-hidden members make up all online members.
		$membersOnlineStats['num_users_online'] = \count($membersOnlineStats['users_online']) + $membersOnlineStats['num_users_hidden'] - (isset(Config::$modSettings['show_spider_online']) && Config::$modSettings['show_spider_online'] > 1 ? \count($spider_finds) : 0);

		IntegrationHook::call('integrate_online_stats', [&$membersOnlineStats]);

		return $membersOnlineStats;
	}
}
