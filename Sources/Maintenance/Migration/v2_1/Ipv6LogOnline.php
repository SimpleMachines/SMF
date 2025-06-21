<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;

class Ipv6LogOnline extends Ipv6Base
{
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

		if (Db::$db->title === POSTGRE_TITLE) {
			return $existing_structure['columns']['ip']['type'] !== 'inet';
		}

		return $existing_structure['columns']['ip']['type'] !== 'varbinary';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\LogOnline();
		$existing_structure = $table->getCurrentStructure();

		$start = Maintenance::getCurrentStart();

		return $this->truncateAndConvert($table, 'ip', true);
	}
}
