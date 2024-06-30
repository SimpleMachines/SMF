<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class LogSpiderHitsURL extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Changing url column size in log_spider_hits from 255 to 1024';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Db\Schema\v3_0\LogSpiderHits();
		$existing_structure = $table->getStructure();

		if ((int) $existing_structure['columns']['url']['size'] === 512) {
			$table->alterColumn(
				$table->columns['url'],
				'url'
			);
		}

		return true;
	}
}

?>