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

class BoardAccess2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Fixing possible issues with board access, part 2';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}boards
			SET memberGroups = SUBSTRING(memberGroups, 2)
			WHERE SUBSTRING(memberGroups, 1, 1) = {string:comma}',
			[
				'comma' => ',',
			],
		);

		$this->query(
			'UPDATE {db_prefix}boards
			SET memberGroups = SUBSTRING(memberGroups, 1, LENGTH(memberGroups) - 1)
			WHERE SUBSTRING(memberGroups, LENGTH(memberGroups)) = {string:comma}',
			[
				'comma' => ',',
			],
		);

		$this->query(
			'UPDATE {db_prefix}boards
			SET memberGroups = REPLACE({string:two_comma}, {string:one_comma}, REPLACE({string:two_comma}, {string:one_comma}, memberGroups))
			WHERE LOCATE({string:two_comma}, memberGroups)',
			[
				'one_comma' => ',',
				'two_comma' => ',',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
