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

class Members6 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 6';

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
			DROP PRIMARY KEY,
			CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
			ADD instantMessages smallint(5) NOT NULL default 0,
			ADD unreadMessages smallint(5) NOT NULL default 0,
			ADD ID_THEME tinyint(4) unsigned NOT NULL default 0,
			ADD ID_GROUP smallint(5) unsigned NOT NULL default 0,
			ADD is_activated tinyint(3) unsigned NOT NULL default {literal:1},
			ADD validation_code varchar(10) NOT NULL default {empty},
			ADD ID_MSG_LAST_VISIT int(10) unsigned NOT NULL default {literal:0},
			ADD additionalGroups tinytext NOT NULL default {empty}',
		);

		$this->handleTimeout();

		return true;
	}
}
