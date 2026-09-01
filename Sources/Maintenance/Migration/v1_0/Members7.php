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

class Members7 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 7';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}members
			CHANGE COLUMN ID_THEME ID_THEME tinyint(4) unsigned NOT NULL default 0',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			ADD showOnline tinyint(4) NOT NULL default {literal:1}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			ADD smileySet varchar(48) NOT NULL default {empty}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			ADD totalTimeLoggedIn int(10) unsigned NOT NULL default {literal:0}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			ADD passwordSalt varchar(5) NOT NULL default {empty}',
		);

		$this->handleTimeout();

		return true;
	}
}
