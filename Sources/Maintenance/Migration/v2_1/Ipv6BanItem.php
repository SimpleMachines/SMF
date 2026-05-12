<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6BanItem extends MigrationBase
{
	use IPv6Converter;

	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating ban_items table with IPv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\BanItems();
		$existing_structure = $table->getCurrentStructure();

		return (
			isset($existing_structure['columns']['ip_low1'])
			|| isset($existing_structure['columns']['ip_low2'])
			|| isset($existing_structure['columns']['ip_low3'])
			|| isset($existing_structure['columns']['ip_low4'])
			|| isset($existing_structure['columns']['ip_high1'])
			|| isset($existing_structure['columns']['ip_high2'])
			|| isset($existing_structure['columns']['ip_high3'])
			|| isset($existing_structure['columns']['ip_high4'])
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\BanItems();
		$existing_structure = $table->getCurrentStructure();

		// Add columns to ban_items
		if ($start <= 0) {
			if (!isset($existing_structure['columns']['ip_low'])) {
				$table->addColumn($table->columns['ip_low']);
			}

			if (!isset($existing_structure['columns']['ip_high'])) {
				$table->addColumn($table->columns['ip_high']);
			}

			$this->handleTimeout(++$start);
		}

		// Convert data for ban_items
		if ($start <= 1) {
			// This query is performed differently for PostgreSQL
			if (Db::$db->title == POSTGRE_TITLE) {
				$this->query(
					'UPDATE {db_prefix}ban_items
					SET
						ip_low = (ip_low1||{literal:.}||ip_low2||{literal:.}||ip_low3||{literal:.}||ip_low4)::inet,
						ip_high = (ip_high1||{literal:.}||ip_high2||{literal:.}||ip_high3||{literal:.}||ip_high4)::inet
					WHERE ip_low1 > 0',
				);
			} else {
				$this->query(
					'UPDATE IGNORE {db_prefix}ban_items
					SET
						ip_low = INET6_ATON(CONCAT_WS({literal:.}, ip_low1, ip_low2, ip_low3, ip_low4)),
						ip_high = INET6_ATON(CONCAT_WS({literal:.}, ip_high1, ip_high2, ip_high3, ip_high4))
					WHERE ip_low1 > 0',
				);
			}

			$this->handleTimeout(++$start);
		}

		// Create new index on ban_items.
		if ($start <= 2) {
			if (!isset($existing_structure['indexes']['idx_id_ban_ip'])) {
				$table->addIndex($table->indexes['idx_id_ban_ip']);
			}

			$this->handleTimeout(++$start);
		}

		// Dropping columns from ban_items
		if ($start <= 3) {
			foreach (['ip_low1', 'ip_low2', 'ip_low3', 'ip_low4', 'ip_high1', 'ip_high2', 'ip_high3', 'ip_high4'] as $col_name) {
				if (isset($existing_structure['columns'][$col_name])) {
					$table->dropColumn($col_name);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
