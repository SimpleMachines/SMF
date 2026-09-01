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

class TopicNotifications extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting topic notifications';

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
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Topics();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'notifies') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}topics
			WHERE notifies != {empty}',
		);

		list($num_notifies) = Db::$db->fetch_row($request);
		Maintenance::$total_items = (int) $num_notifies;

		Db::$db->free_result($request);

		do {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout($start);

			$this->query(
				'INSERT IGNORE INTO {db_prefix}log_notify
					(ID_MEMBER, ID_TOPIC)
				SELECT mem.ID_MEMBER, t.ID_TOPIC
				FROM ({db_prefix}topics AS t, {db_prefix}members AS mem)
				WHERE t.notifies != {empty}
					AND FIND_IN_SET(mem.ID_MEMBER, t.notifies)
				LIMIT {int:limit}
				OFFSET {int:start}',
				[
					'start' => $start,
					'limit' => $this->limit,
				],
			);

			Maintenance::setCurrentStart($start + $this->limit);
		} while (Maintenance::getCurrentStart() < Maintenance::$total_items);

		// Drop the obsolete column.
		$table = new Schema\v1_0\Topics();
		$table->dropColumn('notifies');

		return true;
	}
}
