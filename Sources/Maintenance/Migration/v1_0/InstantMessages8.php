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

class InstantMessages8 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Recounting instant messages';

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
		return \count(Db::$db->list_tables(false, Config::$db_prefix . 'instant_messages')) > 0;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT MAX(ID_MEMBER)
			FROM {db_prefix}members',
		);

		list($max) = Db::$db->fetch_row($request);
		Maintenance::$total_items = (int) $max;

		Db::$db->free_result($request);

		do {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout($start);

			Db::$db->update_from(
				table: [
					'name' => '{db_prefix}members',
					'alias' => 'mem',
				],
				from_tables: [
					[
						// Using a subquery as the table name is a bit cheeky, but it works!
						'name' => '(
							SELECT ID_MEMBER, COUNT(ID_PM) AS num_messages
							FROM {db_prefix}im_recipients
							WHERE deleted = 0
								AND id_member > {int:min}
								AND id_member <= {int:max}
							GROUP BY id_member
						)',
						'alias' => 'pmr',
						'condition' => 'mem.ID_MEMBER = pmr.ID_MEMBER',
					],
				],
				set: 'mem.instantMessages = COALESCE(pmr.num_messages, mem.instantMessages)',
				where: '',
				db_values: [
					'min' => Maintenance::getCurrentStart(),
					'max' => Maintenance::getCurrentStart() + $this->limit,
				],
			);

			Maintenance::setCurrentStart($start + $this->limit);
		} while (Maintenance::getCurrentStart() < Maintenance::$total_items);

		return true;
	}
}
