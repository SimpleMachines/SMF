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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * Prunes log_topics, log_boards, and log_mark_boards_read.
 *
 * For users who haven't been active in a long time, purges these records.
 * For users who haven't been active in a shorter time, marks boards as read,
 * pruning log_topics.
 */
class PruneLogTopics extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// If set to zero, bypass.
		if (empty(Config::$modSettings['mark_read_max_users']) || (empty(Config::$modSettings['mark_read_beyond']) && empty(Config::$modSettings['mark_read_delete_beyond']))) {
			$this->should_log = false;

			return true;
		}

		// Convert to timestamps for comparison.
		$mark_read_cutoff = empty(Config::$modSettings['mark_read_beyond']) ? 0 : time() - Config::$modSettings['mark_read_beyond'] * 86400;

		$cleanup_beyond = empty(Config::$modSettings['mark_read_delete_beyond']) ? 0 : time() - Config::$modSettings['mark_read_delete_beyond'] * 86400;

		$max_members = Config::$modSettings['mark_read_max_users'];

		// You're basically saying to just purge, so just purge.
		if ($mark_read_cutoff < $cleanup_beyond) {
			$mark_read_cutoff = $cleanup_beyond;
		}

		// Try to prevent timeouts
		@set_time_limit(300);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		// Start off by finding the records in log_boards, log_topics & log_mark_read
		// for users who haven't been around the longest...
		$request = Db::$db->query(
			'',
			'SELECT lb.id_member, m.last_login
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
			LIMIT {int:limit}',
			[
				'limit' => $max_members,
				'dcutoff' => $cleanup_beyond,
				'mrcutoff' => $mark_read_cutoff,
				'unwatched' => 0,
			],
		);
		$members = Db::$db->fetch_all($request);
		Db::$db->free_result($request);

		// Nothing to do?
		if (empty($members)) {
			return true;
		}

		// Determine action based on last_login...
		$purge_members = [];
		$mark_read_members = [];

		foreach($members as $member) {
			if ($member['last_login'] <= $cleanup_beyond) {
				$purge_members[] = $member['id_member'];
			} elseif ($member['last_login'] <= $mark_read_cutoff) {
				$mark_read_members[] = $member['id_member'];
			}
		}

		if (!empty($purge_members) && !empty(Config::$modSettings['mark_read_delete_beyond'])) {
			// Delete rows from log_boards.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_boards
				WHERE id_member IN ({array_int:members})',
				[
					'members' => $purge_members,
				],
			);
			// Delete rows from log_mark_read.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_mark_read
				WHERE id_member IN ({array_int:members})',
				[
					'members' => $purge_members,
				],
			);

			// Delete rows from log_topics.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_topics
				WHERE id_member IN ({array_int:members})
					AND unwatched = {int:unwatched}',
				[
					'members' => $purge_members,
					'unwatched' => 0,
				],
			);
		}

		// Nothing left to do?
		if (empty($mark_read_members) || empty(Config::$modSettings['mark_read_beyond'])) {
			return true;
		}

		// Find board inserts to perform...
		// Get board info for each member from log_topics.
		// Note this user may have read many topics on that board, but we just
		// want one row each, and the ID of the last message read in each board.
		$result = Db::$db->query(
			'',
			'SELECT lt.id_member, t.id_board, MAX(lt.id_msg) AS id_last_message
			FROM {db_prefix}topics t
			INNER JOIN
			(
				SELECT id_member, id_topic, id_msg
				FROM {db_prefix}log_topics
				WHERE id_member IN ({array_int:members})
			) lt ON t.id_topic = lt.id_topic
			GROUP BY lt.id_member, t.id_board',
			[
				'members' => $mark_read_members,
			],
		);
		$boards = Db::$db->fetch_all($result);
		Db::$db->free_result($result);

		// Create one SQL statement for this set of inserts.
		if (!empty($boards)) {
			Db::$db->insert(
				'replace',
				'{db_prefix}log_mark_read',
				['id_member' => 'int', 'id_board' => 'int', 'id_msg' => 'int'],
				$boards,
				['id_member', 'id_board'],
			);
		}

		// Finally, delete this set's rows from log_topics.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:members})
				AND unwatched = {int:unwatched}',
			[
				'members' => $mark_read_members,
				'unwatched' => 0,
			],
		);

		return true;
	}
}

?>