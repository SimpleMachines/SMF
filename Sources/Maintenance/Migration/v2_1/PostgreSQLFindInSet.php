<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;

class PostgreSQLFindInSet extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Add find_in_set function (PostgreSQL)';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type == POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$this->query(
			'',
			"
				CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS '
					SELECT i AS result
					FROM generate_series(1, array_upper(string_to_array($2,'',''), 1)) AS g(i)
					WHERE  (string_to_array($2,'',''))[i] = $1
						UNION ALL
					SELECT 0
					LIMIT 1'
				LANGUAGE 'sql';
		",
		);

		return true;
	}
}

?>