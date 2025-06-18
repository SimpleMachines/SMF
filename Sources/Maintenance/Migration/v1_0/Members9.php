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

class Members9 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 9';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$member_groups = $this->getMemberGroups();

		$result = $this->query(
			'ALTER TABLE {db_prefix}boards
			ADD memberGroups varchar(128) NOT NULL default {string:default}',
			[
				'default' => '-1,0',
			],
		);

		if ($result !== false) {
			$result = $this->query(
				'SELECT TRIM(memberGroups) AS memberGroups, ID_CAT
				FROM {db_prefix}categories',
			);

			while ($row = smf_mysql_fetch_assoc($result)) {
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

					$groups = implode(',', array_unique($groups));
				}

				$this->query(
					'UPDATE {db_prefix}boards
					SET memberGroups = {string:groups}, lastUpdated = {int:now}
					WHERE ID_CAT = {int:id_cat}',
					[
						'groups' => $groups,
						'now' => time(),
						'id_cat' => $row['ID_CAT'],
					],
				);
			}
		}

		$this->query(
			'UPDATE IGNORE {db_prefix}members
			SET ID_GROUP = {int:id}
			WHERE memberGroup = {string:name}',
			[
				'name' => 'Administrator',
				'id' => 1,
			],
		);

		$this->query(
			'UPDATE IGNORE {db_prefix}members
			SET ID_GROUP = {int:id}
			WHERE memberGroup = {string:name}',
			[
				'name' => 'Global Moderator',
				'id' => 2,
			],
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			DROP memberGroup',
		);

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
