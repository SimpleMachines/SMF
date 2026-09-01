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

class InstantMessages2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting instant messages, part 2';

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
			CHANGE COLUMN ID_MEMBER_FROM ID_MEMBER_FROM mediumint(8) unsigned NOT NULL default {int:zero},
			CHANGE COLUMN msgtime msgtime int(10) unsigned NOT NULL default {string:zero},
			CHANGE COLUMN subject subject tinytext NOT NULL',
			[
				'zero' => 0,
			],
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX fromName,
			DROP INDEX ID_MEMBER_FROM',
		);

		$this->handleTimeout();

		return true;
	}
}
