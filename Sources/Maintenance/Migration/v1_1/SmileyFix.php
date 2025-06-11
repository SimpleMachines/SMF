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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Maintenance\Migration\MigrationBase;

class SmileyFix extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating data in smileys table';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}smileys
			SET filename = {string:new}
			WHERE filename = {string:old}',
			[
				'old' => 'embarassed.gif',
				'new' => 'embarrassed.gif',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
