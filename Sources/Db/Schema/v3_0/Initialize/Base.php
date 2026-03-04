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

namespace SMF\Db\Schema\v3_0\Initialize;

/**
 * Intiailization logic for any supported database in which we may need to add additional
 * functions, operators or other critical logic to the database.
 */
class Base
{
	/*********************
	 * Internal properties
	 *********************/

	protected ?string $version = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $version Version of the database engine.
	 */
	public function __construct(string $version)
	{
		$this->version = $version;
	}

	/**
	 * Gets queries that create custom SQL functions and operators.
	 *
	 * @return array SQL queries.
	 */
	public function getAll(): array
	{
		return $this->functions() + $this->operators();
	}

	/**
	 * Gets queries that create custom SQL functions.
	 *
	 * @return array SQL queries.
	 */
	public function functions(): array
	{
		return [];
	}

	/**
	 * Gets queries that create custom SQL operators.
	 *
	 * @return array SQL queries.
	 */
	public function operators(): array
	{
		return [];
	}
}
