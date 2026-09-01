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

class BoardOrder extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating board order';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$catOrder = -1;
		$boardOrder = -1;
		$curCat = -1;

		$request = Db::$db->query(
			'SELECT c.ID_CAT, c.catOrder, b.ID_BOARD, b.boardOrder
			FROM {db_prefix}categories AS c
				LEFT JOIN {db_prefix}boards AS b ON (b.ID_CAT = c.ID_CAT)
			ORDER BY c.catOrder, b.childLevel, b.boardOrder, b.ID_BOARD',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($curCat != $row['ID_CAT']) {
				$curCat = $row['ID_CAT'];

				if (++$catOrder != $row['catOrder']) {
					Db::$db->query(
						'UPDATE {db_prefix}categories
						SET catOrder = {int:order}
						WHERE ID_CAT = {int:id}
						LIMIT 1',
						[
							'order' => $catOrder,
							'id' => $row['ID_CAT'],
						],
					);
				}
			}

			if (!empty($row['ID_BOARD']) && ++$boardOrder != $row['boardOrder']) {
				Db::$db->query(
					'UPDATE {db_prefix}boards
					SET boardOrder = {int:order}
					WHERE ID_BOARD = {int:id}
					LIMIT 1',
					[
						'order' => $boardOrder,
						'id' => $row['ID_BOARD'],
					],
				);
			}
		}

		Db::$db->free_result($request);

		$this->handleTimeout();

		return true;
	}
}
