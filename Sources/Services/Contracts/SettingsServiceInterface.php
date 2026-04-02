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

namespace SMF\Services\Contracts;

/**
 * Interface for Settings.php configuration management service.
 *
 * This service handles file-based configuration stored in Settings.php.
 * These are system-level settings like database credentials, directory paths,
 * and core configuration that rarely changes.
 */
interface SettingsServiceInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Get a configuration value from Settings.php.
	 *
	 * @param string $key The configuration key
	 * @param mixed $default Default value if key doesn't exist
	 * @return mixed The configuration value
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Set a configuration value (in memory only).
	 *
	 * This does not persist to Settings.php. Use updateFile() to persist.
	 *
	 * @param string $key The configuration key
	 * @param mixed $value The value to set
	 */
	public function set(string $key, mixed $value): void;

	/**
	 * Update the Settings.php file.
	 *
	 * @param array $configVars Array of configuration variables to update
	 * @param bool $keepQuotes Whether to keep quotes around values
	 * @param bool $rebuild Whether to rebuild the file
	 * @return bool Success status
	 */
	public function updateFile(array $configVars, bool $keepQuotes = false, bool $rebuild = false): bool;

	/**
	 * Get the board URL.
	 *
	 * @return string The board URL
	 */
	public function getBoardUrl(): string;

	/**
	 * Get the script URL.
	 *
	 * @return string The script URL
	 */
	public function getScriptUrl(): string;

	/**
	 * Get the board directory.
	 *
	 * @return string The board directory path
	 */
	public function getBoardDir(): string;

	/**
	 * Get the sources directory.
	 *
	 * @return string The sources directory path
	 */
	public function getSourcesDir(): string;

	/**
	 * Get the cache directory.
	 *
	 * @return string The cache directory path
	 */
	public function getCacheDir(): string;

	/**
	 * Get the languages directory.
	 *
	 * @return string The languages directory path
	 */
	public function getLanguagesDir(): string;

	/**
	 * Check if maintenance mode is enabled.
	 *
	 * @return bool True if maintenance mode is enabled
	 */
	public function isMaintenanceMode(): bool;

	/**
	 * Get the maintenance mode level.
	 *
	 * @return int Maintenance mode level (0, 1, or 2)
	 */
	public function getMaintenanceLevel(): int;

	/**
	 * Get the forum name.
	 *
	 * @return string The forum name
	 */
	public function getForumName(): string;

	/**
	 * Get the database type.
	 *
	 * @return string The database type (mysql, postgresql, etc.)
	 */
	public function getDatabaseType(): string;

	/**
	 * Get the database server.
	 *
	 * @return string The database server
	 */
	public function getDatabaseServer(): string;

	/**
	 * Get the database name.
	 *
	 * @return string The database name
	 */
	public function getDatabaseName(): string;

	/**
	 * Get the database prefix.
	 *
	 * @return string The database table prefix
	 */
	public function getDatabasePrefix(): string;
}
