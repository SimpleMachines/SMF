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

class Ipv6Messages extends Ipv6Base
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update messages poster_ip with ipv6 support (May take a while)';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Messages();
		$existing_structure = $table->getCurrentStructure();

		if (Config::$db_type === POSTGRE_TITLE) {
			return $existing_structure['columns']['poster_ip']['type'] !== 'inet';
		}

		return isset($existing_structure['columns']['poster_ip_old'])
			|| $existing_structure['columns']['poster_ip']['type'] !== 'varbinary';
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Messages();

		// This will return true once its done, but we need to do a few more things.
		$this->migrateData($table, 'poster_ip');

		$start = Maintenance::getCurrentStart();

		if ($start <= 7) {
			$existing_structure = $table->getCurrentStructure();

			foreach ($table->indexes as $idx) {
				if ($idx->name === 'idx_ip_index') {
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 8) {
			$existing_structure = $table->getCurrentStructure();

			foreach ($table->indexes as $idx) {
				if ($idx->name === 'idx_related_ip') {
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>