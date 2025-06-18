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

class Messages7 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting messages, part 7';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD INDEX ID_BOARD (ID_BOARD)',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			DROP INDEX posterTime_2',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			DROP INDEX posterTime_3',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			DROP INDEX ID_MEMBER_2',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			DROP INDEX ID_MEMBER_3',
		);

		$this->handleTimeout();

		return true;
	}
}
