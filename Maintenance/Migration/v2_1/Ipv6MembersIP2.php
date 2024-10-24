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

class Ipv6MembersIP2 extends Ipv6Base
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update members with ipv6 support (IP2)';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Members();
		$existing_structure = $table->getCurrentStructure();

		if (Config::$db_type === POSTGRE_TITLE) {
			return $existing_structure['columns']['member_ip2']['type'] !== 'inet';
		}

		return isset($existing_structure['columns']['member_ip2_old'])
			|| $existing_structure['columns']['member_ip2']['type'] !== 'varbinary';
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Members();

		return $this->migrateData($table, 'member_ip2');
	}
}

?>