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

class BoardStats extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting board statistics';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$result = $this->query(
			'SELECT MAX(m.ID_MSG) AS ID_LAST_MSG, t.ID_BOARD
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (m.ID_MSG = t.ID_LAST_MSG)
			GROUP BY t.ID_BOARD',
		);

		$last_msgs = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			$last_msgs[] = $row['ID_LAST_MSG'];
		}

		Db::$db->free_result($result);

		if (!empty($last_msgs)) {
			$result = $this->query(
				'SELECT m.ID_MSG, m.posterTime, t.ID_BOARD
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (
						m.ID_MSG = t.ID_LAST_MSG
						AND m.ID_MSG IN ({array_int:last_msgs})
					)
				LIMIT {int:limit}',
				[
					'last_msgs' => $last_msgs,
					'limit' => \count($last_msgs),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$this->query(
					'UPDATE {db_prefix}boards
					SET ID_LAST_MSG = {int:ID_MSG}, lastUpdated = {int:posterTime}
					WHERE ID_BOARD = {int:ID_BOARD}
					LIMIT 1',
					$row,
				);
			}

			Db::$db->free_result($result);
		}

		$this->handleTimeout();

		return true;
	}
}
