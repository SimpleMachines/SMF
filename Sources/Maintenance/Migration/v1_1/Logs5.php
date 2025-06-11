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

class Logs5 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading log tables, part 5';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_1\Boards();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'lastUpdated') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT MAX(ID_BOARD)
			FROM {db_prefix}boards',
		);
		list($max_board) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// We need to get the last updated message.
		$request = $this->query(
			'SELECT ID_BOARD, lastUpdated
			FROM {db_prefix}boards',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Done this?
			if ($row['ID_BOARD'] < Maintenance::getCurrentStart()) {
				continue;
			}

			// Maybe we don't have any?
			if ($row['lastUpdated'] == 0) {
				$ID_MSG = 0;
			}
			// Otherwise need to query it?
			else {
				$request2 = $this->query(
					'SELECT MIN(ID_MSG)
					FROM {db_prefix}messages
					WHERE posterTime >= {int:lastUpdated}',
					[
						'lastUpdated' => $row['lastUpdated'],
					],
				);
				list($ID_MSG) = Db::$db->fetch_row($request2);
				Db::$db->free_result($request2);

				if (empty($ID_MSG)) {
					$ID_MSG = 0;
				}
			}

			$this->query(
				'UPDATE {db_prefix}boards
				SET ID_MSG_UPDATED = {int:id_msg}
				WHERE ID_BOARD = {int:id_board}',
				[
					'id_msg' => $ID_MSG,
					'id_board' => $row['ID_BOARD'],
				],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		Db::$db->free_result($request);

		return true;
	}
}
