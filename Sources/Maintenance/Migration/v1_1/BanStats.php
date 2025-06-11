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
use SMF\Maintenance\Migration\MigrationBase;

class BanStats extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating ban statistics';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT mem.ID_MEMBER, mem.is_activated + 10 AS new_value
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (
					bg.id_ban_group = bi.id_ban_group
				)
				INNER JOIN {db_prefix}members AS mem ON (
					mem.ID_MEMBER = bi.ID_MEMBER
					OR mem.emailAddress LIKE bi.email_address
				)
			WHERE bg.cannot_access = 1
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:now})
				AND mem.is_activated < 10',
			[
				'now' => time(),
			],
		);

		$updates = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$updates[$row['new_value']][] = $row['ID_MEMBER'];
		}

		Db::$db->free_result($request);

		// Find members that are wrongfully marked as banned.
		$request = $this->query(
			'SELECT mem.ID_MEMBER, mem.is_activated - 10 AS new_value
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}ban_items AS bi ON (
					bi.ID_MEMBER = mem.ID_MEMBER
					OR mem.emailAddress LIKE bi.email_address
				)
				LEFT JOIN {db_prefix}ban_groups AS bg ON (
					bg.id_ban_group = bi.id_ban_group
					AND bg.cannot_access = 1
					AND (
						bg.expire_time IS NULL
						OR bg.expire_time > {int:now}
					)
				)
			WHERE (bi.id_ban IS NULL OR bg.id_ban_group IS NULL)
				AND mem.is_activated >= 10',
			[
				'now' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$updates[$row['new_value']][] = $row['ID_MEMBER'];
		}

		Db::$db->free_result($request);

		if (!empty($updates)) {
			foreach ($updates as $new_status => $members) {
				$this->query(
					'UPDATE {db_prefix}members
					SET is_activated = {string:status}
					WHERE ID_MEMBER IN ({array_int:members})
					LIMIT {int:limit}',
					[
						'status' => $new_status,
						'members' => $members,
						'limit' => \count($members),
					],
				);
			}
		}

		$this->handleTimeout();

		return true;
	}
}
