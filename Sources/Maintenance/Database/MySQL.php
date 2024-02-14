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

namespace SMF\Maintenance\Database;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\DatabaseInterface;

/**
 * Database Maintenance for MySQL and variants.
 */
class MySQL implements DatabaseInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function getTitle(): string
	{
		return MYSQL_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMinimumVersion(): string
	{
		return '8.0.35';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getServerVersion(): bool|string
	{
		if (!function_exists('mysqli_fetch_row')) {
			return false;
		}

		return mysqli_fetch_row(mysqli_query(Db::$db->connection, 'SELECT VERSION();'))[0];
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported(): bool
	{
		return function_exists('mysqli_connect');
	}

	/**
	 * {@inheritDoc}
	 */
	public function skipSelectDatabase(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultUser(): string
	{
		return ini_get('mysql.default_user') === false ? '' : ini_get('mysql.default_user');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultPassword(): string
	{
		return ini_get('mysql.default_password') === false ? '' : ini_get('mysql.default_password');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultHost(): string
	{
		return ini_get('mysql.default_host') === false ? '' : ini_get('mysql.default_host');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultPort(): int
	{
		return ini_get('mysql.default_port') === false ? 3306 : (int) ini_get('mysql.default_port');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultName(): string
	{
		return 'smf_';
	}

	/**
	 * {@inheritDoc}
	 */
	public function checkConfiguration(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasPermissions(): bool
	{
		// Find database user privileges.
		$privs = [];
		$get_privs = Db::$db->query('', 'SHOW PRIVILEGES', []);

		while ($row = Db::$db->fetch_assoc($get_privs)) {
			if ($row['Privilege'] == 'Alter') {
				$privs[] = $row['Privilege'];
			}
		}
		Db::$db->free_result($get_privs);

		// Check for the ALTER privilege.
		return !(!in_array('Alter', $privs));
	}

	/**
	 * {@inheritDoc}
	 */
	public function validatePrefix(&$value): bool
	{
		$value = preg_replace('~[^A-Za-z0-9_\$]~', '', $value);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function utf8Configured(): bool
	{
		return true;
	}
}

?>