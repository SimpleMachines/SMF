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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Db\Schema\DbIndex;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxMembers extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Members)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\Members();
		$existing_structure = $table->getCurrentStructure();

		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['members_member_name_low'],
				'index',
				'members_member_name_low',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			$oldIdx = new DbIndex(
				['members_real_name_low'],
				'index',
				'members_real_name_low',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			$oldIdx = new DbIndex(
				['members_active_real_name'],
				'index',
				'members_active_real_name',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		if ($start <= 3) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$this->query(
					'CREATE INDEX {db_prefix}members_member_name_low ON {db_prefix}members (LOWER(member_name) varchar_pattern_ops)',
					[
						'security_override' => true,
					],
				);
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 4) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$this->query(
					'CREATE INDEX {db_prefix}members_real_name_low ON {db_prefix}members (LOWER(real_name) varchar_pattern_ops)',
					[
						'security_override' => true,
					],
				);
			}

			$this->handleTimeout(++$start);
		}

		// Updating members active_real_name (drop)
		if ($start <= 5) {
			if (isset($existing_structure['indexes']['idx_active_real_name'])) {
				$table->dropIndex($table->indexes['idx_active_real_name']);
			}

			$this->handleTimeout(++$start);
		}

		// Updating members active_real_name (add)
		if ($start <= 6) {
			$table->addIndex($table->indexes['idx_active_real_name']);

			$this->handleTimeout(++$start);
		}

		// Updating members email_address
		if ($start <= 7) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$idx = $table->indexes['idx_email_address'];
				$table->addIndex($idx);
			}

			$this->handleTimeout(++$start);
		}

		// Updating members drop memberName
		if ($start <= 8) {
			$oldIdx = new DbIndex(['member_name'], 'index', 'memberName');
			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		// Change index for table members
		if ($start <= 9) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$idx = $table->indexes['idx_lngfile'];
				$table->addIndex($idx);
			}

			$this->handleTimeout(++$start);
		}

		// Change index for table members
		if ($start <= 10) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$idx = $table->indexes['idx_member_name'];
				$table->addIndex($idx);
			}

			$this->handleTimeout(++$start);
		}

		// Change index for table members
		if ($start <= 11) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$idx = $table->indexes['idx_real_name'];
				$table->addIndex($idx);
			}

			$this->handleTimeout(++$start);
		}

		// Create help function for index
		if ($start <= 12 && Db::$db->title === POSTGRE_TITLE) {
			$this->query(
				'CREATE OR REPLACE FUNCTION indexable_month_day(date) RETURNS TEXT as \'
				SELECT to_char($1, \'\'MM-DD\'\');\'
				LANGUAGE \'sql\' IMMUTABLE STRICT',
			);

			$this->handleTimeout(++$start);
		}

		// Change index for table members
		if ($start <= 13 && Db::$db->title === POSTGRE_TITLE) {
			$idx = new DbIndex(
				['indexable_month_day(birthdate)'],
				'index',
				'members_birthdate2',
			);
			$table->addIndex($idx);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
