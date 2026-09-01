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

class Topics4 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting topics, part 4';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}topics
			CHANGE COLUMN ID_MEMBER_STARTED ID_MEMBER_STARTED mediumint(8) unsigned NOT NULL default {literal:0},
			CHANGE COLUMN ID_MEMBER_UPDATED ID_MEMBER_UPDATED mediumint(8) unsigned NOT NULL default {literal:0}',
		);

		$this->handleTimeout();

		return true;
	}
}
