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

class Messages3 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting messages, part 3';

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
			CHANGE COLUMN posterTime posterTime int(10) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN modifiedTime modifiedTime int(10) unsigned NOT NULL default {literal:0}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD INDEX participation (ID_MEMBER, ID_TOPIC)',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD INDEX ipIndex (posterIP(15), ID_TOPIC)',
		);

		$this->handleTimeout();

		return true;
	}
}
