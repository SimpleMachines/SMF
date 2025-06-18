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

class InstantMessages3 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting instant messages, part 3';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP PRIMARY KEY,
			CHANGE COLUMN ID_IM ID_PM int(10) unsigned NOT NULL auto_increment PRIMARY KEY',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			ADD INDEX msgtime (msgtime)',
		);

		$this->handleTimeout();

		return true;
	}
}
