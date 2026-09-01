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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class Messages6 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting messages, part 6';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 1400;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		while (true) {
			$this->handleTimeout();

			$request = $this->query(
				'SELECT DISTINCT t.ID_BOARD, t.ID_TOPIC
				FROM ({db_prefix}messages AS m, {db_prefix}topics AS t)
				WHERE t.ID_TOPIC = m.ID_TOPIC
					AND m.ID_BOARD = {int:zero}
				LIMIT {int:limit}',
				[
					'zero' => 0,
					'limit' => $this->limit,
				],
			);

			$boards = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards[$row['ID_BOARD']][] = $row['ID_TOPIC'];
			}

			foreach ($boards as $board => $topics) {
				$this->query(
					'UPDATE {db_prefix}messages
					SET ID_BOARD = {int:board}
					WHERE ID_TOPIC IN ({array_int:topics})',
					[
						'board' => $board,
						'topics' => $topics,
					],
				);
			}

			$num_rows = Db::$db->num_rows($request);

			Db::$db->free_result($request);

			if ($num_rows < $this->limit) {
				break;
			}
		}

		return true;
	}
}
