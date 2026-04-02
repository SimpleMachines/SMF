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
 * Interface for mod settings management service.
 *
 * This service handles database-based settings stored in the settings table.
 * These are runtime, user-configurable settings that can be modified through
 * the admin panel and by mods/extensions.
 */
interface ModSettingsServiceInterface
{
	/**
	 * Get a mod setting value.
	 *
	 * @param string $key The setting key
	 * @param mixed $default Default value if key doesn't exist
	 * @return mixed The setting value
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Get all mod settings.
	 *
	 * @return array All mod settings as key => value pairs
	 */
	public function getAll(): array;

	/**
	 * Check if a mod setting exists.
	 *
	 * @param string $key The setting key
	 * @return bool True if the setting exists
	 */
	public function has(string $key): bool;

	/**
	 * Set a mod setting value (in memory only).
	 *
	 * This does not persist to the database. Use update() to persist.
	 *
	 * @param string $key The setting key
	 * @param mixed $value The value to set
	 * @return void
	 */
	public function set(string $key, mixed $value): void;

	/**
	 * Update mod settings in the database.
	 *
	 * @param array $settings Array of setting key => value pairs
	 *                        Set value to null to delete a setting
	 * @param bool $update Whether to use UPDATE instead of REPLACE
	 *                     True: Use UPDATE (allows incrementing with true/false values)
	 *                     False: Use REPLACE (default, faster for bulk updates)
	 * @return void
	 */
	public function update(array $settings, bool $update = false): void;

	/**
	 * Delete one or more mod settings from the database.
	 *
	 * @param string|array $keys Setting key(s) to delete
	 * @return void
	 */
	public function delete(string|array $keys): void;

	/**
	 * Reload mod settings from the database.
	 *
	 * This clears the cache and reloads all settings from the database.
	 *
	 * @return void
	 */
	public function reload(): void;

	/**
	 * Clear the mod settings cache.
	 *
	 * @return void
	 */
	public function clearCache(): void;

	/**
	 * Get multiple settings at once.
	 *
	 * @param array $keys Array of setting keys
	 * @param mixed $default Default value for missing keys
	 * @return array Array of key => value pairs
	 */
	public function getMultiple(array $keys, mixed $default = null): array;

	/**
	 * Check if any of the specified settings exist.
	 *
	 * @param array $keys Array of setting keys
	 * @return bool True if at least one setting exists
	 */
	public function hasAny(array $keys): bool;

	/**
	 * Check if all the specified settings exist.
	 *
	 * @param array $keys Array of setting keys
	 * @return bool True if all settings exist
	 */
	public function hasAll(array $keys): bool;
}

