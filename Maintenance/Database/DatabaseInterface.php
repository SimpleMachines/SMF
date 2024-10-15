<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database;

/**
 * Database Maintenance interface.  Additional database logic is performed and set here.
 */
interface DatabaseInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Fetch the database title.
	 * @return string
	 */
	public function getTitle(): string;

	/**
	 * The minimum version that SMF supports for the database.
	 * @return string
	 */
	public function getMinimumVersion(): string;

	/**
	 * Get the server version from the server, we must have Config:$db_* defined.
	 *
	 * @return string
	 * 		When false, the server connection failed or an error occurred.
	 * 		Otherwise a string is returned containing the server version.
	 */
	public function getServerVersion(): bool|string;

	/**
	 * Is this database supported.
	 *
	 * @return bool True if we can use this database, false otherwise.
	 */
	public function isSupported(): bool;

	/**
	 * Skip issuing a select database command.
	 *
	 * @return bool When true, we do not select a database.
	 */
	public function skipSelectDatabase(): bool;

	/**
	 * Default username for a database connection.
	 *
	 * @return string
	 */
	public function getDefaultUser(): string;

	/**
	 * Default password for a database connection.
	 *
	 * @return string
	 */
	public function getDefaultPassword(): string;

	/**
	 * Default host for a database connection.
	 *
	 * @return string
	 */
	public function getDefaultHost(): string;

	/**
	 * Default port for a database connection.
	 *
	 * @return int
	 */
	public function getDefaultPort(): int;

	/**
	 * Default database name for a database connection.
	 *
	 * @return string
	 */
	public function getDefaultName(): string;

	/**
	 * Performs checks to ensure the server is in a sane configuration.
	 *
	 * @return bool
	 */
	public function checkConfiguration(): bool;

	/**
	 * Performs checks to ensure we have proper permissions to the database in order to perform operations.
	 *
	 * @return bool
	 */
	public function hasPermissions(): bool;

	/**
	 * Validate a database prefix.
	 * When an error occurs, use throw new exception, this will be captured.
	 *
	 * @return bool
	 */
	public function validatePrefix(&$string): bool;

	/**
	 * Checks that the server has the proper support for UTF-8 content.
	 * When an error occurs, use throw new exception, this will be captured.
	 *
	 * @return bool
	 */
	public function utf8Configured(): bool;

	/**
	 * Perform additional changes to our SQL connection in order to perform commands that are not strict SQL.
	 *
	 * @param string $mode The SQL mode we wish to be in, either 'default' or 'strict'.
	 * @return bool
	 */
	public function setSqlMode(string $mode = 'default'): bool;

	/**
	 * When an error occurs with a query ran through a wrapper, we send errors here.
	 *
	 * @param string $error_msg as returend by the database interfaces call.
	 * @param string $query Query we ran
	 * @return mixed
	 * 				False if we should not do anything,
	 * 				True if we should stop for error.
	 * 				Result from a query can also be returned, if we are able to correct the query.
	 */
	public function processError(string $error_msg, string $query): mixed;
}

?>