<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class ModLog extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading the moderation log';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * How many rows there are in the log_actions table.
	 */
	private int $num_actions = 0;

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 500;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}log_actions',
			[],
		);

		list($this->num_actions) = array_map('intval', Db::$db->fetch_row($request));

		Db::$db->free_result($request);

		return $this->num_actions > 0;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table is structured correctly.
		$table = new Schema\v2_0\LogActions();
		$table->normalize();

		$is_done = false;

		while (!$is_done) {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout();

			$mrequest = $this->query(
				'SELECT id_action, extra, id_board, id_topic, id_msg
				FROM {db_prefix}log_actions
				LIMIT {int:limit}
				OFFSET {int:start}',
				[
					'limit' => $this->limit,
					'start' => $start,
				],
			);

			while ($row = Db::$db->fetch_assoc($mrequest)) {
				Maintenance::setCurrentStart();

				if (
					!empty($row['id_board'])
					|| !empty($row['id_topic'])
					|| !empty($row['id_msg'])
				) {
					continue;
				}

				$row['extra'] = @unserialize($row['extra']);

				// Corrupt?
				$row['extra'] = \is_array($row['extra']) ? $row['extra'] : [];

				if (!empty($row['extra']['board'])) {
					$board_id = (int) $row['extra']['board'];
					unset($row['extra']['board']);
				} else {
					$board_id = 0;
				}

				if (!empty($row['extra']['board_to']) && empty($board_id)) {
					$board_id = (int) $row['extra']['board_to'];
					unset($row['extra']['board_to']);
				}

				if (!empty($row['extra']['topic'])) {
					$topic_id = (int) $row['extra']['topic'];

					unset($row['extra']['topic']);

					if (empty($board_id)) {
						$trequest = $this->query(
							'SELECT id_board
							FROM {db_prefix}topics
							WHERE id_topic = {int:id}',
							[
								'id' => $topic_id,
							],
						);

						if (Db::$db->num_rows($trequest) > 0) {
							list($board_id) = Db::$db->fetch_row($trequest);
						}

						Db::$db->free_result($trequest);
					}
				} else {
					$topic_id = 0;
				}

				if (!empty($row['extra']['message'])) {
					$msg_id = (int) $row['extra']['message'];

					unset($row['extra']['message']);

					if (empty($topic_id) || empty($board_id)) {
						$trequest = $this->query(
							'SELECT id_board, id_topic
							FROM {db_prefix}messages
							WHERE id_msg = {int:id}
							LIMIT 1',
							[
								'id' => $msg_id,
							],
						);

						if (Db::$db->num_rows($trequest)) {
							list($board_id, $topic_id) = Db::$db->fetch_row($trequest);
						}

						Db::$db->free_result($trequest);
					}
				} else {
					$msg_id = 0;
				}

				$row['extra'] = Db::$db->escape_string(serialize($row['extra']));

				$this->query(
					'UPDATE {db_prefix}log_actions
					SET
						id_board = {int:board_id},
						id_topic = {int:topic_id},
						id_msg = {int:msg_id},
						extra = {string:extra}
					WHERE id_action = {int:id_action}',
					[
						'board_id' => $board_id,
						'topic_id' => $topic_id,
						'msg_id' => $msg_id,
						'extra' => $row['extra'],
						'id_action' => $row['id_action'],
					],
				);

				$is_done = Maintenance::getCurrentStart() >= $this->num_actions;
			}
		}

		return true;
	}
}
