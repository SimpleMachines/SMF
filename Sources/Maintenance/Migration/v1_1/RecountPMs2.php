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
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class RecountPMs2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Recounting personal messages, part 2';

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
			$request = $this->query(
				'SELECT COUNT(*)
				FROM {db_prefix}members',
			);

			list($maxMembers) = Db::$db->fetch_row($request);
			Maintenance::$total_items = (int) $maxMembers;

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
								FROM {db_prefix}pm_recipients
								WHERE deleted = 0
									AND is_read = 0
									AND id_member > {int:min_id}
									AND id_member <= {int:max_id}
								GROUP BY id_member
							)',
							'alias' => 'pmr',
							'condition' => 'mem.ID_MEMBER = pmr.ID_MEMBER',
						],
					],
					set: 'mem.instantMessages = COALESCE(pmr.num_messages, mem.instantMessages)',
					where: '',
					db_values: [
						'min_id' => Maintenance::getCurrentStart(),
						'max_id' => Maintenance::getCurrentStart() + $this->limit,
					],
				);

				Maintenance::setCurrentStart($start + $this->limit);
			} while (Maintenance::getCurrentStart() < Maintenance::$total_items);

			return true;
		}
	}
