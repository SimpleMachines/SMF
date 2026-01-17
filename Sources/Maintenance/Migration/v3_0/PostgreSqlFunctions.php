<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Table;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSqlFunctions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Add any missing functions (PostgreSQL)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$schema_version = substr(__NAMESPACE__, strrpos(__NAMESPACE__, '\\', -1) + 1);

		$queries = Table::getInitializers($schema_version, POSTGRE_TITLE);

		foreach ($queries as $query) {
			// Use the upgrade query handler.
			$this->query($query, [
				'security_override' => true,
			]);
		}

		return true;
	}
}
