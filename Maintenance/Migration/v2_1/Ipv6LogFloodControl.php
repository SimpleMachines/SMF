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

class Ipv6LogFloodControl extends Ipv6Base
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update log_floodcontrol ip with ipv6 support without converting';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogFloodcontrol();
		$existing_structure = $table->getCurrentStructure();

		if (Config::$db_type === POSTGRE_TITLE) {
			return $existing_structure['columns']['ip']['type'] !== 'inet';
		}

		return $existing_structure['columns']['ip']['type'] !== 'varbinary';
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogFloodcontrol();

		$start = Maintenance::getCurrentStart();

		// Prep floodcontrol
		if ($start <= 0) {
			$this->query('', 'TRUNCATE TABLE {db_prefix}log_floodcontrol');

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			// Add the new floodcontrol ip column
			$table->dropIndex($table->indexes['primary']);

			// Modify log_type size
			$table->alterColumn($table->columns['ip']);
			$table->alterColumn($table->columns['log_type']);

			// Create primary key for floodcontrol
			$table->addIndex($table->indexes['primary']);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>