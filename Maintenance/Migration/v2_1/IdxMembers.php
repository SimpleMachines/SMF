<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxMembers extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'optimization of members';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Db\Schema\v3_0\Members();
		
		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['members_member_name_low'],
				'index',
				'members_member_name_low'
			);
			
			$table->dropIndex($oldIdx);
		}

		$this->handleTimeout(++$start);

		if ($start <= 1) {
			$oldIdx = new DbIndex(
				['members_real_name_low'],
				'index',
				'members_real_name_low'
			);
			
			$table->dropIndex($oldIdx);
		}

		$this->handleTimeout(++$start);

		if ($start <= 2) {
			$oldIdx = new DbIndex(
				['members_active_real_name'],
				'index',
				'members_active_real_name'
			);
			
			$table->dropIndex($oldIdx);
		}

		$this->handleTimeout(++$start);

		if ($start <= 3) {
			$this->query(
				'',
				'CREATE INDEX {db_prefix}members_member_name_low ON {db_prefix}members (LOWER(member_name) varchar_pattern_ops)',
				[
					'security_override' => true,
				],
			);
		}

		$this->handleTimeout(++$start);

		if ($start <= 4) {
			$this->query(
				'',
				'CREATE INDEX {db_prefix}members_real_name_low ON {db_prefix}members (LOWER(real_name) varchar_pattern_ops)',
				[
					'security_override' => true,
				],
			);
		}

		$this->handleTimeout(++$start);

		if ($start <= 4) {
			foreach ($table->indexes as $idx) {
				if ($idx['name'] === 'idx_active_real_name') {
					$table->addIndex($idx);
				}
			}
		}

		$this->handleTimeout(++$start);

		return true;
	}
}

?>