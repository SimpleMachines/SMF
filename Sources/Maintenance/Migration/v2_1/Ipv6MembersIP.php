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

class Ipv6MembersIP extends MigrationBase
{
	use IPv6Converter;

	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating members table with IPv6 support (part 1)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\Members();
		$existing_structure = $table->getCurrentStructure();

		return $existing_structure['columns']['member_ip']['type'] !== 'inet';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\Members();

		$this->convertStringColumnToInet($table, $table->columns['member_ip']);
		$this->handleTimeout();

		return true;
	}
}
