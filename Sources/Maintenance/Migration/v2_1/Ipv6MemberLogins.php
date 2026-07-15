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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6MemberLogins extends MigrationBase
{
	use IPv6Converter;

	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating member_logins table with IPv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\MemberLogins();
		$existing_structure = $table->getCurrentStructure();

		return (
			$existing_structure['columns']['ip']['type'] !== 'inet'
			|| $existing_structure['columns']['ip2']['type'] !== 'inet'
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\MemberLogins();
		$existing_structure = $table->getCurrentStructure();

		foreach (['ip', 'ip2'] as $col) {
			if ($existing_structure['columns'][$col]['type'] !== 'inet') {
				// This table was added in 2.1 and never had the 'CHAR' type. So if
				// these columns are somehow the wrong type, their data is useless.
				$this->query(
					'UPDATE {db_prefix}{raw:table}
					SET {identifier:column} = {empty}',
					[
						'table' => $table->name,
						'column' => $col,
					],
				);

				$table->alterColumn($table->columns[$col]);
				$this->handleTimeout();
			}
		}

		return true;
	}
}
