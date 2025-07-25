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

namespace SMF\Maintenance\Migration;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * This should always be performed and move with our upgrade steps.
 */
class CleanHooks extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clearing existing hooks';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'DELETE FROM {db_prefix}settings
			WHERE variable LIKE {string:integration}',
			[
				'integration' => 'integration_%',
			],
		);

		$tables = Db::$db->list_tables();

		if (in_array(Config::$db_prefix . 'hooks', $tables)) {
			$this->query(
				'TRUNCATE TABLE {db_prefix}hooks',
			);
		}

		return true;
	}
}
