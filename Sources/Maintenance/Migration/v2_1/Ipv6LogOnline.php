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

class Ipv6LogOnline extends MigrationBase
{
	use IPv6Converter;

	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update log_online ip with ipv6 support without converting';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\LogOnline();
		$existing_structure = $table->getCurrentStructure();

		return $existing_structure['columns']['ip']['type'] !== 'inet';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\LogOnline();

		$this->query('TRUNCATE TABLE {db_prefix}log_online');

		$this->convertIntegerColumnToInet($table, $table->columns['ip']);

		$table->normalize();

		$this->handleTimeout();

		return true;
	}
}
