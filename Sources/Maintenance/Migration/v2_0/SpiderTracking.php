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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class SpiderTracking extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding search engine tracking';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_0\Spiders();
		$table->normalize();
		$table->populate();

		$table = new Schema\v2_0\LogSpiderHits();
		$table->normalize();

		$table = new Schema\v2_0\LogSpiderStats();
		$table->normalize();

		$this->handleTimeout();

		return true;
	}
}
