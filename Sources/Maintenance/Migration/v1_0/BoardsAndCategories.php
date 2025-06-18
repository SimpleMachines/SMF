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

class BoardsAndCategories extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting boards and categories';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Updating the structure of the boards table.
		$boards_table = new Schema\v1_0\Boards();
		$boards_table->alterColumn($boards_table->columns['countPosts'], 'count');
		$boards_table->dropColumn('isAnnouncement');
		$boards_table->dropColumn('ID_LAST_TOPIC');
		$boards_table->dropIndex('ID_CAT');
		$boards_table->dropIndex('memberGroups');
		$boards_table->normalize();

		// Updating access permissions.
		$categories_table = new Schema\v1_0\Categories();
		$cat_structure = $categories_table->getCurrectStructure();

		if (array_filter($cat_structure['columns'], fn($c) => $c['name'] === 'memberGroups') !== []) {
			$member_groups = $this->getMemberGroups();

			$request = $this->query(
				'SELECT memberGroups, ID_CAT
				FROM {db_prefix}categories',
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (trim($row['memberGroups']) == '') {
					$groups = '-1,0,2';
				} else {
					$memberGroups = array_unique(explode(',', $row['memberGroups']));

					$groups = [2];

					foreach ($memberGroups as $k => $check) {
						$memberGroups[$k] = trim($memberGroups[$k]);

						if (
							$memberGroups[$k] == ''
							|| !isset($member_groups[$memberGroups[$k]])
							|| $member_groups[$memberGroups[$k]] == 8
						) {
							continue;
						}

						$groups[] = $member_groups[$memberGroups[$k]];
					}

					$groups = array_unique($groups);

					sort($groups);

					$groups = implode(',', $groups);
				}

				$this->query(
					'UPDATE {db_prefix}boards
					SET memberGroups = {string:groups}, lastUpdated = {int:now}
					WHERE ID_CAT = {int:cat}',
					[
						'groups' => $groups,
						'now' => time(),
						'cat' => $row['ID_CAT'],
					],
				);
			}

			Db::$db->free_result($request);
		}

		// Converting categories table.
		$categories_table->dropColumn('memberGroups');
		$categories_table->normalize();

		$this->handleTimeout();

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 *
	 */
	private function getMemberGroups(): array
	{
		$request = $this->query(
			'SELECT groupName, ID_GROUP
			FROM {db_prefix}membergroups
			WHERE ID_GROUP = 1 OR ID_GROUP > 7',
		);

		if (!$request || Db::$db->num_rows($request) === 0) {
			$request = $this->query(
				'SELECT membergroup, ID_GROUP
				FROM {db_prefix}membergroups
				WHERE ID_GROUP = 1 OR ID_GROUP > 7',
			);
		}

		while ($row = Db::$db->fetch_row($request)) {
			$member_groups[trim($row[0])] = (int) $row[1];
		}

		Db::$db->free_result($request);

		return $member_groups;
	}
}
