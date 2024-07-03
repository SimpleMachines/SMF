<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6BanItem extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update ban ip with ipv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\BanItems();
		$existing_structure = $table->getCurrentStructure();

		return !isset($existing_structure['columns']['ip_low']) || !isset($existing_structure['columns']['ip_high']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Maintenance\Database\Schema\v2_1\BanItems();
		$existing_structure = $table->getCurrentStructure();

		// Add columns to ban_items
		if ($start <= 0) {
			foreach ($table->columns as $column) {
				if (
					(
						$column->name === 'ip_low'
						|| $column->name === 'ip_high'
					)
					&& !isset($existing_structure['columns'][$column->name])
				) {
					$table->addColumn($column);
					continue;
				}
			}

			$this->handleTimeout(++$start);
		}

		// Convert data for ban_items
		if ($start <= 1) {
			// This query is performed differently for PostgreSQL
			if (Config::$db_type == POSTGRE_TITLE) {
				$this->query('', '
					UPDATE {db_prefix}ban_items
					SET ip_low = (ip_low1||{literal:.}||ip_low2||{literal:.}||ip_low3||{literal:.}||ip_low4)::inet,
						ip_high = (ip_high1||{literal:.}||ip_high2||{literal:.}||ip_high3||{literal:.}||ip_high4)::inet
					WHERE ip_low1 > 0;
				');
			} else {
				$this->query('', '
					UPDATE IGNORE {db_prefix}ban_items
					SET ip_low =
						UNHEX(
							hex(
								INET_ATON(concat(ip_low1,{literal:.},ip_low2,{literal:.},ip_low3,{literal:.},ip_low4))
							)
						),
					ip_high =
						UNHEX(
							hex(
								INET_ATON(concat(ip_high1,{literal:.},ip_high2,{literal:.},ip_high3,{literal:.},ip_high4))
							)
						)
					where ip_low1 > 0;
				');

			}

			$this->handleTimeout(++$start);
		}

		// Create new index on ban_items.
		if ($start <= 2) {
			foreach ($table->indexes as $idx) {
				if (
						$idx->name === 'idx_id_ban_ip'
					&& !isset($existing_structure['indexes'][$column->name])
				) {
					$table->addIndex($idx);
					continue;
				}
			}

			$this->handleTimeout(++$start);
		}

		// Dropping columns from ban_items
		if ($start <= 3) {
			foreach ($table->columns as $column) {
				if (
					(
						$column->name === 'ip_low1' || $column->name === 'ip_low2' || $column->name === 'ip_low3' || $column->name === 'ip_low4'
						|| $column->name === 'ip_high1' || $column->name === 'ip_high2' || $column->name === 'ip_high3' || $column->name === 'ip_high4'
					)
					&& !isset($existing_structure['columns'][$column->name])
				) {
					$table->dropColumn($column);
					continue;
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>