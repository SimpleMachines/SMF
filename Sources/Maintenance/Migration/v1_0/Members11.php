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

class Members11 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 11';

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
			DROP INDEX realName',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			CHANGE COLUMN AIM AIM varchar(16) NOT NULL default {empty},
			CHANGE COLUMN YIM YIM varchar(32) NOT NULL default {empty},
			CHANGE COLUMN ICQ ICQ tinytext NOT NULL default {empty},
			CHANGE COLUMN realName realName tinytext NOT NULL default {empty},
			CHANGE COLUMN emailAddress emailAddress tinytext NOT NULL default {empty},
			CHANGE COLUMN dateRegistered dateRegistered int(10) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN passwd passwd varchar(64) NOT NULL default {empty},
			CHANGE COLUMN personalText personalText tinytext NOT NULL default {empty},
			CHANGE COLUMN websiteTitle websiteTitle tinytext NOT NULL default {empty}',
		);

		$this->handleTimeout();

		return true;
	}
}
