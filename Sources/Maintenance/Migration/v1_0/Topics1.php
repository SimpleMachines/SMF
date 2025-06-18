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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Maintenance\Migration\MigrationBase;

class Topics1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting topics, part 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}topics
			DROP INDEX ID_FIRST_MSG',
		);

		$this->query(
			'ALTER TABLE {db_prefix}topics
			DROP INDEX ID_LAST_MSG',
		);

		$this->query(
			'ALTER TABLE {db_prefix}topics
			ADD INDEX isSticky (isSticky)',
		);

		$this->handleTimeout();

		return true;
	}
}
