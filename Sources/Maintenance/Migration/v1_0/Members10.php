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

class Members10 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 10';

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
			CHANGE COLUMN timeOffset timeOffset float NOT NULL default {literal:0},
			CHANGE COLUMN posts posts mediumint(8) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN timeFormat timeFormat varchar(80) NOT NULL default {empty},
			CHANGE COLUMN lastLogin lastLogin int(11) NOT NULL default {literal:0},
			CHANGE COLUMN karmaBad karmaBad smallint(5) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN karmaGood karmaGood smallint(5) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN gender gender tinyint(4) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN hideEmail hideEmail tinyint(4) NOT NULL default {literal:0}',
		);

		$this->handleTimeout();

		return true;
	}
}
