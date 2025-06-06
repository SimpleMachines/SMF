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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Maintenance\Migration\MigrationBase;

class MembergroupTypes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adjusting group types';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}membergroups
			SET group_type = 1
			WHERE id_group = 1',
			[],
		);

		return true;
	}
}
