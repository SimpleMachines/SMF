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

class BoardAccess1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Fixing possible issues with board access, part 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'1.0.beta.5',
			'<=',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$all_groups = [];

		$result = $this->query(
			'SELECT ID_GROUP
			FROM {db_prefix}membergroups',
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$all_groups[] = $row['ID_GROUP'];
		}

		Db::$db->free_result($result);

		$result = $this->query(
			'SELECT ID_BOARD, memberGroups
			FROM {db_prefix}boards
			WHERE FIND_IN_SET(0, memberGroups)',
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$this->query(
				'UPDATE {db_prefix}boards
				SET memberGroups = {string:groups}
				WHERE ID_BOARD = {int:id}
				LIMIT 1',
				[
					'groups' => implode(',', array_unique(
						array_merge(
							array_map('trim', explode(',', $row['memberGroups'])),
							$all_groups,
						),
					)),
					'id' => $row['ID_BOARD'],
				],
			);
		}

		Db::$db->free_result($result);

		$this->handleTimeout();

		return true;
	}
}
