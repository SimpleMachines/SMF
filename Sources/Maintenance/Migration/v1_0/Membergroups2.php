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

class Membergroups2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting membergroups, part 2';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 512;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure members table has the necessary column and index.
		$table = new Schema\v1_0\Members();
		$table->addColumn($table->columns['ID_POST_GROUP'], if_exists: 'update');
		$table->addIndex($table->indexes['ID_POST_GROUP'], if_exists: 'update');

		// Get the minimum number of posts for each post-count group.
		$request = $this->query(
			'SELECT ID_GROUP, minPosts
			FROM {db_prefix}membergroups
			WHERE minPosts != -1
			ORDER BY minPosts DESC',
		);

		$post_groups = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$post_groups[$row['minPosts']] = $row['ID_GROUP'];
		}

		Db::$db->free_result($request);

		// Get the maximum member ID.
		$request = $this->query(
			'SELECT MAX(ID_MEMBER)
			FROM {db_prefix}members',
		);

		list($max) = Db::$db->fetch_row($request);
		Maintenance::$total_items = (int) $max;

		Db::$db->free_result($request);

		// Update the members table.
		do {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout($start);

			$request = $this->query(
				'SELECT ID_MEMBER, posts
				FROM {db_prefix}members
				WHERE ID_MEMBER > {int:min}
					AND ID_MEMBER <= {int:max}',
				[
					'min' => $start,
					'max' => $start + $this->limit,
				],
			);

			$updates = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$group = 4;

				foreach ($post_groups as $min_posts => $group_id) {
					if ($row['posts'] > $min_posts) {
						$group = $group_id;
						break;
					}
				}

				$updates[$group][] = $row['ID_MEMBER'];
			}

			Db::$db->free_result($request);

			foreach ($updates as $group_to => $update_members) {
				$this->query(
					'UPDATE {db_prefix}members
					SET ID_POST_GROUP = {string:group}
					WHERE ID_MEMBER IN ({array_int:members})',
					[
						'group' => $group_to,
						'members' => $update_members,
						'limit' => \count($update_members),
					],
				);
			}

			Maintenance::setCurrentStart($start + $this->limit);
		} while (Maintenance::getCurrentStart() < Maintenance::$total_items);

		return true;
	}
}
