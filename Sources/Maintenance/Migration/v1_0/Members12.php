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

class Members12 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 12';

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
			DROP INDEX lngfile',
		);

		$this->query(
			'ALTER TABLE {db_prefix}members
			CHANGE COLUMN websiteUrl websiteUrl tinytext NOT NULL default {empty},
			CHANGE COLUMN location location tinytext NOT NULL default {empty},
			CHANGE COLUMN avatar avatar tinytext NOT NULL default {empty},
			CHANGE COLUMN im_ignore_list im_ignore_list tinytext NOT NULL default {empty},
			CHANGE COLUMN usertitle usertitle tinytext NOT NULL default {empty},
			CHANGE COLUMN lngfile lngfile tinytext NOT NULL default {empty},
			CHANGE COLUMN MSN MSN tinytext NOT NULL default {empty},
			CHANGE COLUMN memberIP memberIP tinytext NOT NULL default {empty},
			ADD INDEX lngfile (lngfile(24))',
		);

		$this->handleTimeout();

		return true;
	}
}
