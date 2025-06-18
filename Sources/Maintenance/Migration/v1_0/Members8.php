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

class Members8 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 8';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}members
			SET gender = CASE gender
				WHEN {literal:0} THEN 0
				WHEN {literal:Male} THEN 1
				WHEN {literal:Female} THEN 2
				ELSE 0 END, secretAnswer = IF(secretAnswer = {empty}, {empty}, MD5(secretAnswer))
			WHERE gender NOT IN ({literal:0}, {literal:1}, {literal:2})',
		);

		$this->handleTimeout();

		return true;
	}
}
