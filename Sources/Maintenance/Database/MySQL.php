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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * Database Maintenance for MySQL and variants.
 */
class MySQL implements DatabaseInterface
{
	/****************
	 * Public methods
	 ****************/

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
		return 'smf';
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

	/**
	 * {@inheritDoc}
	 */
	public function setSqlMode(string $mode = 'default'): bool
	{
		$sql_mode = '';

		if ($mode === 'strict') {
			$sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT';
		}

		Db::$db->query('', 'SET SESSION sql_mode = {string:sql_mode}', [
			'sql_mode' => $sql_mode,
		]);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function processError(string $error_msg, string $query): mixed
	{
		$mysqli_errno = mysqli_errno(Db::$db_connection);

		$error_query = in_array(substr(trim($query), 0, 11), ['INSERT INTO', 'UPDATE IGNO', 'ALTER TABLE', 'DROP TABLE ', 'ALTER IGNOR', 'INSERT IGNO']);

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1050: Table already exists.
		//    1054: Unknown column name.
		//    1060: Duplicate column name.
		//    1061: Duplicate key name.
		//    1062: Duplicate entry for unique key.
		//    1068: Multiple primary keys.
		//    1072: Key column '%s' doesn't exist in table.
		//    1091: Can't drop key, doesn't exist.
		//    1146: Table doesn't exist.
		//    2013: Lost connection to server during query.

		if ($mysqli_errno == 1016) {
			if (preg_match('~\'([^\.\']+)~', $error_msg, $match) != 0 && !empty($match[1])) {
				mysqli_query(Db::$db_connection, 'REPAIR TABLE `' . $match[1] . '`');
				$result = mysqli_query(Db::$db_connection, $query);

				if ($result !== false) {
					return $result;
				}
			}
		} elseif ($mysqli_errno == 2013) {
			Db::$db_connection = mysqli_connect(Config::$db_server, Config::$db_user, Config::$db_passwd);
			mysqli_select_db(Db::$db_connection, Config::$db_name);

			if (Db::$db_connection) {
				$result = mysqli_query(Db::$db_connection, $query);

				if ($result !== false) {
					return $result;
				}
			}
		}
		// Duplicate column name... should be okay ;).
		elseif (in_array($mysqli_errno, [1060, 1061, 1068, 1091])) {
			return false;
		}
		// Duplicate insert... make sure it's the proper type of query ;).
		elseif (in_array($mysqli_errno, [1054, 1062, 1146]) && $error_query) {
			return false;
		}
		// Creating an index on a non-existent column.
		elseif ($mysqli_errno == 1072) {
			return false;
		} elseif ($mysqli_errno == 1050 && substr(trim($query), 0, 12) == 'RENAME TABLE') {
			return false;
		}
		// Testing for legacy tables or columns? Needed for 1.0 & 1.1 scripts.
		elseif (in_array($mysqli_errno, [1054, 1146]) && in_array(substr(trim($query), 0, 7), ['SELECT ', 'SHOW CO'])) {
			return false;
		}

		// If a table already exists don't go potty.
		if (in_array(substr(trim($query), 0, 8), ['CREATE T', 'CREATE S', 'DROP TABL', 'ALTER TA', 'CREATE I', 'CREATE U'])) {
			if (strpos($error_msg, 'exist') !== false) {
				return false;
			}
		} elseif (strpos(trim($query), 'INSERT ') !== false) {
			if (strpos($error_msg, 'duplicate') !== false) {
				return false;
			}
		}

		return true;
	}
}

?>