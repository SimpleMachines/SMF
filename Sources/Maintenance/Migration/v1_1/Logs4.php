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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Logs4 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading log tables, part 4';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_1\LogTopics();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'logTime') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT MAX(ID_MSG)
			FROM {db_prefix}messages',
		);
		list($max_msg) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (empty($max_msg)) {
			$max_msg = 0;
		}

		if (Maintenance::getCurrentStart() == 0) {
			// By default a message is modified when it was written.
			$this->query(
				'UPDATE {db_prefix}messages
				SET ID_MSG_MODIFIED = ID_MSG',
			);

			$request = $this->query(
				'SELECT posterTime
				FROM {db_prefix}messages
				WHERE ID_MSG = {int:max_msg}',
				[
					'max_msg' => $max_msg,
				],
			);
			list($max_poster_time) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (empty($max_poster_time)) {
				$max_poster_time = 0;
			}

			$this->query(
				'UPDATE {db_prefix}log_boards
				SET ID_MSG = {int:max_msg}
				WHERE logTime >= {int:max_poster_time}',
				[
					'max_msg' => $max_msg,
					'max_poster_time' => $max_poster_time,
				],
			);

			$this->query(
				'UPDATE {db_prefix}log_mark_read
				SET ID_MSG = {int:max_msg}
				WHERE logTime >= {int:max_poster_time}',
				[
					'max_msg' => $max_msg,
					'max_poster_time' => $max_poster_time,
				],
			);

			$this->query(
				'UPDATE {db_prefix}log_topics
				SET ID_MSG = {int:max_msg}
				WHERE logTime >= {int:max_poster_time}',
				[
					'max_msg' => $max_msg,
					'max_poster_time' => $max_poster_time,
				],
			);

			$this->query(
				'UPDATE {db_prefix}messages
				SET ID_MSG_MODIFIED = {int:max_msg}
				WHERE modifiedTime >= {int:max_poster_time}',
				[
					'max_msg' => $max_msg,
					'max_poster_time' => $max_poster_time,
				],
			);

			// Timestamp 1 is where it all starts.
			$lower_limit = 1;
		} else {
			// Determine the lower limit.
			$request = $this->query(
				'SELECT MAX(posterTime) + 1
				FROM {db_prefix}messages
				WHERE ID_MSG < {int:start}',
				[
					'start' => Maintenance::getCurrentStart(),
				],
			);
			list($lower_limit) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (empty($lower_limit)) {
				$lower_limit = 1;
			}

			if (empty($max_poster_time)) {
				$max_poster_time = 1;
			}
		}

		while (Maintenance::getCurrentStart() <= $max_msg) {
			$start = Maintenance::getCurrentStart();

			$condition = '';
			$lowest_limit = $lower_limit;

			$request = $this->query(
				'SELECT MAX(ID_MSG) AS ID_MSG, posterTime
				FROM {db_prefix}messages
				WHERE ID_MSG BETWEEN {int:start} AND {int:end}
				GROUP BY posterTime
				ORDER BY posterTime
				LIMIT 300',
				[
					'start' => $start,
					'end' => $start + 300,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($condition === '') {
					$condition = "IF(logTime BETWEEN {$lower_limit} AND {$row['posterTime']}, {$row['ID_MSG']}, %else%)";
				} else {
					$condition = strtr($condition, ['%else%' => "IF(logTime <= {$row['posterTime']}, {$row['ID_MSG']}, %else%)"]);
				}

				$lower_limit = $row['posterTime'] + 1;
			}

			Db::$db->free_result($request);

			if ($condition !== '') {
				$condition = strtr($condition, ['%else%' => '0']);
				$highest_limit = $lower_limit;

				$this->query(
					'UPDATE {db_prefix}log_boards
					SET ID_MSG = {raw:condition}
					WHERE logTime BETWEEN {int:lowest_limit} AND {int:highest_limit}
						AND ID_MSG = 0',
					[
						'condition' => $condition,
						'lowest_limit' => $lowest_limit,
						'highest_limit' => $highest_limit,
					],
				);

				$this->query(
					'UPDATE {db_prefix}log_mark_read
					SET ID_MSG = {raw:condition}
					WHERE logTime BETWEEN {int:lowest_limit} AND {int:highest_limit}
						AND ID_MSG = 0',
					[
						'condition' => $condition,
						'lowest_limit' => $lowest_limit,
						'highest_limit' => $highest_limit,
					],
				);

				$this->query(
					'UPDATE {db_prefix}log_topics
					SET ID_MSG = {raw:condition}
					WHERE logTime BETWEEN {int:lowest_limit} AND {int:highest_limit}
						AND ID_MSG = 0',
					[
						'condition' => $condition,
						'lowest_limit' => $lowest_limit,
						'highest_limit' => $highest_limit,
					],
				);

				$this->query(
					'UPDATE {db_prefix}messages
					SET ID_MSG_MODIFIED = {raw:condition}
					WHERE modifiedTime BETWEEN {int:lowest_limit} AND {int:highest_limit}
						AND modifiedTime > 0',
					[
						'condition' => strtr($condition, ['logTime' => 'modifiedTime']),
						'lowest_limit' => $lowest_limit,
						'highest_limit' => $highest_limit,
					],
				);
			}

			Maintenance::setCurrentStart($start + 300);
			$this->handleTimeout();
		}

		return true;
	}
}
