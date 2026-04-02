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

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Services\Contracts\ModSettingsServiceInterface;

/**
 * Mod settings management service.
 *
 * This service handles database-based settings stored in the settings table.
 * These are runtime, user-configurable settings that can be modified through
 * the admin panel and by mods/extensions.
 */
class ModSettingsService implements ModSettingsServiceInterface
{
	/**
	 * @var array
	 *
	 * In-memory cache of mod settings.
	 */
	protected array $settings = [];

	/**
	 * @var bool
	 *
	 * Whether settings have been loaded.
	 */
	protected bool $loaded = false;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
	}

	public function get(string $key, mixed $default = null): mixed
	{
		$this->ensureLoaded();

		return $this->settings[$key] ?? $default;
	}

	public function getAll(): array
	{
		$this->ensureLoaded();

		return $this->settings;
	}

	public function has(string $key): bool
	{
		$this->ensureLoaded();

		return isset($this->settings[$key]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(string $key, mixed $value): void
	{
		$this->ensureLoaded();

		$this->settings[$key] = $value;

		// Sync to static Config for backward compatibility
		Config::$modSettings[$key] = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update(array $settings, bool $update = false): void
	{
		if (empty($settings) || !\is_array($settings)) {
			return;
		}

		$this->ensureLoaded();

		$to_remove = [];

		// Check if there are any settings to be removed
		foreach ($settings as $k => $v) {
			if ($v === null) {
				unset($settings[$k]);
				$to_remove[] = $k;
			}
		}

		// Delete settings
		if (!empty($to_remove)) {
			Db::$db->query(
				'DELETE FROM {db_prefix}settings
				WHERE variable IN ({array_string:remove})',
				[
					'remove' => $to_remove,
				],
			);

			// Remove from our cache
			foreach ($to_remove as $key) {
				unset($this->settings[$key]);
			}
		}

		// Update mode: use UPDATE queries for increment/decrement
		if ($update) {
			foreach ($settings as $variable => $value) {
				Db::$db->query(
					'UPDATE {db_prefix}settings
					SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
					WHERE variable = {string:variable}',
					[
						'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
						'variable' => $variable,
					],
				);

				$this->settings[$variable] = $value === true ? ($this->settings[$variable] ?? 0) + 1 : ($value === false ? ($this->settings[$variable] ?? 0) - 1 : $value);
			}

			// Clear cache
			$this->clearCache();

			// Sync to Config for backward compatibility
			Config::$modSettings = $this->settings;

			return;
		}

		// Replace mode: use REPLACE queries
		$replace_array = [];

		foreach ($settings as $variable => $value) {
			// Don't bother if it's already like that
			if (($this->settings[$variable] ?? null) == $value) {
				continue;
			}

			// If the variable isn't set, but would only be set to nothingness, then don't bother setting it
			if (!isset($this->settings[$variable]) && empty($value)) {
				continue;
			}

			$replace_array[] = [$variable, $value];
			$this->settings[$variable] = $value;
		}

		if (empty($replace_array)) {
			return;
		}

		Db::$db->insert(
			'replace',
			'{db_prefix}settings',
			['variable' => 'string-255', 'value' => 'string-65534'],
			$replace_array,
			['variable'],
		);

		// Clear cache
		$this->clearCache();

		// Sync to Config for backward compatibility
		Config::$modSettings = $this->settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string|array $keys): void
	{
		$keys = (array) $keys;
		$deleteArray = array_fill_keys($keys, null);

		$this->update($deleteArray);
	}

	/**
	 * {@inheritDoc}
	 */
	public function reload(): void
	{
		$this->loaded = false;
		$this->settings = [];
		$this->loadFromDatabase();

		// Sync to Config for backward compatibility
		Config::$modSettings = $this->settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clearCache(): void
	{
		CacheApi::put('modSettings', null, 90);
	}

	/**
	 * {@inheritDoc}
	 */
	public function increment(string $key, int $amount = 1): void
	{
		$this->ensureLoaded();

		// Use the update method with true to trigger UPDATE query
		$this->update([$key => true], true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function decrement(string $key, int $amount = 1): void
	{
		$this->ensureLoaded();

		// Use the update method with false to trigger UPDATE query
		$this->update([$key => false], true);
	}

	public function getMultiple(array $keys, mixed $default = null): array
	{
		$this->ensureLoaded();

		$result = [];

		foreach ($keys as $key) {
			$result[$key] = $this->settings[$key] ?? $default;
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasAny(array $keys): bool
	{
		$this->ensureLoaded();

		foreach ($keys as $key) {
			if (isset($this->settings[$key])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasAll(array $keys): bool
	{
		$this->ensureLoaded();

		foreach ($keys as $key) {
			if (!isset($this->settings[$key])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Ensure settings are loaded from database.
	 *
	 * @return void
	 */
	protected function ensureLoaded(): void
	{
		if (!$this->loaded) {
			$this->loadFromDatabase();
		}
	}

	/**
	 * Load settings from database.
	 *
	 * @return void
	 */
	protected function loadFromDatabase(): void
	{
		// If Config has already loaded modSettings, use those for efficiency
		if (!empty(Config::$modSettings)) {
			$this->settings = Config::$modSettings;
			$this->loaded = true;

			return;
		}

		// Load cache API if not already loaded
		CacheApi::load();

		// Try to load from cache first
		if (\is_array($temp = CacheApi::get('modSettings', 90))) {
			$this->settings = $temp;
			$this->loaded = true;

			return;
		}

		// Load from database
		$this->settings = [];

		try {
			$request = Db::$db->query(
				'SELECT variable, value
				FROM {db_prefix}settings',
				[],
			);

			if (!$request) {
				ErrorHandler::displayDbError();
			}

			foreach (Db::$db->fetch_all($request) as $row) {
				$this->settings[$row['variable']] = $row['value'];
			}
			Db::$db->free_result($request);

			// Apply default values and validations
			$this->applyDefaults();

			// Cache the settings
			if (!empty(CacheApi::$enable)) {
				CacheApi::put('modSettings', $this->settings, 90);
			}

			$this->loaded = true;
		} catch (\Throwable $e) {
			// If database is not available, just mark as loaded with empty settings
			$this->loaded = true;
		}
	}

	/**
	 * Apply default values and validations to settings.
	 *
	 * This ensures critical settings have valid values.
	 *
	 * @return void
	 */
	protected function applyDefaults(): void
	{
		// Validate defaultMaxTopics
		if (empty($this->settings['defaultMaxTopics']) || $this->settings['defaultMaxTopics'] <= 0 || $this->settings['defaultMaxTopics'] > 999) {
			$this->settings['defaultMaxTopics'] = 20;
		}

		// Validate defaultMaxMessages
		if (empty($this->settings['defaultMaxMessages']) || $this->settings['defaultMaxMessages'] <= 0 || $this->settings['defaultMaxMessages'] > 999) {
			$this->settings['defaultMaxMessages'] = 15;
		}

		// Validate defaultMaxMembers
		if (empty($this->settings['defaultMaxMembers']) || $this->settings['defaultMaxMembers'] <= 0 || $this->settings['defaultMaxMembers'] > 999) {
			$this->settings['defaultMaxMembers'] = 30;
		}

		// Validate defaultMaxListItems
		if (empty($this->settings['defaultMaxListItems']) || $this->settings['defaultMaxListItems'] <= 0 || $this->settings['defaultMaxListItems'] > 999) {
			$this->settings['defaultMaxListItems'] = 15;
		}

		// Parse attachmentUploadDir if it's JSON
		if (isset($this->settings['attachmentUploadDir']) && !\is_array($this->settings['attachmentUploadDir'])) {
			$attachmentUploadDir = \SMF\Utils::jsonDecode($this->settings['attachmentUploadDir'], true, 512, 0, false);
			$this->settings['attachmentUploadDir'] = !empty($attachmentUploadDir) ? $attachmentUploadDir : $this->settings['attachmentUploadDir'];
		}
	}
}

