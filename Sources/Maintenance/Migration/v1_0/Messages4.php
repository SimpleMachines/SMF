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

class Messages4 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting messages, part 4';

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
			CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN icon icon varchar(16) NOT NULL default {literal:xx}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD INDEX ID_MEMBER (ID_MEMBER)',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD UNIQUE INDEX topic (ID_TOPIC, ID_MSG)',
		);

		$this->handleTimeout();

		return true;
	}
}
