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

class Ipv6Messages extends Ipv6Base
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update messages poster_ip with ipv6 support (May take a while)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\Messages();
		$existing_structure = $table->getCurrentStructure();

		if (Db::$db->title === POSTGRE_TITLE) {
			return $existing_structure['columns']['poster_ip']['type'] !== 'inet';
		}

		return isset($existing_structure['columns']['poster_ip_old'])
			|| $existing_structure['columns']['poster_ip']['type'] !== 'varbinary';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\Messages();

		// This will return true once its done, but we need to do a few more things.
		$this->migrateData($table, 'poster_ip');

		$start = Maintenance::getCurrentStart();

		if ($start <= 7) {
			$table->addIndex($table->indexes['idx_ip_index']);

			$this->handleTimeout(++$start);
		}

		if ($start <= 8) {
			$table->addIndex($table->indexes['idx_related_ip']);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
