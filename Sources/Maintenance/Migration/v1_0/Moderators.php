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
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Moderators extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting moderators';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Boards();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'moderators') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$result = $this->query(
			'SELECT moderators, ID_BOARD
			FROM {db_prefix}boards
			WHERE TRIM(moderators) != {string:empty}',
			[
				'empty' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$names = array_filter(
				array_unique(array_map('trim', explode(',', $row['moderators']))),
				'strlen',
			);

			if (!empty($names)) {
				$inserts = [];

				$request2 = $this->query(
					'SELECT ID_MEMBER
					FROM {db_prefix}members
					WHERE memberName IN ({array_string:names})
					LIMIT {int:limit}',
					[
						'names' => $names,
						'limit' => \count($names),
					],
				);

				while ($row = Db::$db->fetch_row($request2)) {
					$inserts[] = [$row['ID_BOARD'], $row[0]];
				}

				Db::$db->free_result($request2);

				Db::$db->insert(
					method: 'ignore',
					table: '{db_prefix}moderators',
					columns: [
						'ID_BOARD' => 'int',
						'ID_MEMBER' => 'int',
					],
					data: $inserts,
					keys: [],
				);
			}
		}

		Db::$db->free_result($request);

		$table = new Schema\v1_0\Boards();
		$table->dropColumn('moderators');

		$this->handleTimeout();

		return true;
	}
}
