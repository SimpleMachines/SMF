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

namespace SMF\Services;

use SMF\Db\DatabaseApi;

/**
 * Service wrapper for the DatabaseApi.
 */
class DatabaseService implements DatabaseServiceInterface
{
	/**
	 * @var DatabaseApi
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// In a real DI scenario, we might inject configuration here,
		// but for now we rely on DatabaseApi::load() which uses global config.
		if (!isset(DatabaseApi::$db)) {
			DatabaseApi::load();
		}
		$this->db = DatabaseApi::$db;
	}

	/**
	 * Magic method to delegate calls to the underlying DatabaseApi instance.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments)
	{
		return $this->db->$name(...$arguments);
	}

	/**
	 * Get the underlying DatabaseApi instance.
	 *
	 * @return DatabaseApi
	 */
	public function getApi(): DatabaseApi
	{
		return $this->db;
	}

	// Add other frequently used methods explicitly as needed or specific type hinting, otherwise __call handles them.
}
