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

use SMF\Config;
use SMF\Services\Contracts\SettingsServiceInterface;

/**
 * Settings.php configuration management service.
 *
 * This service handles file-based configuration stored in Settings.php.
 * It loads settings independently from the Config class.
 */
class SettingsService implements SettingsServiceInterface
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * In-memory cache of configuration values loaded from Settings.php.
	 */
	protected array $settings = [];

	/**
	 * @var bool
	 *
	 * Whether settings have been loaded from Settings.php.
	 */
	protected bool $loaded = false;

	/**
	 * @var string
	 *
	 * Path to Settings.php file.
	 */
	protected string $settingsFile;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string|null $settingsFile Optional path to Settings.php file.
	 */
	public function __construct(?string $settingsFile = null)
	{
		$this->settingsFile = $settingsFile ?? (\defined('SMF_SETTINGS_FILE') ? SMF_SETTINGS_FILE : '');
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		$this->ensureLoaded();

		return $this->settings[$key] ?? $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(string $key, mixed $value): void
	{
		$this->ensureLoaded();

		$this->settings[$key] = $value;

		// Also update static Config for backward compatibility
		if (property_exists(Config::class, $key)) {
			Config::${$key} = $value;
		} else {
			Config::$custom[$key] = $value;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function updateFile(array $configVars, bool $keepQuotes = false, bool $rebuild = false): bool
	{
		// Delegate to static Config method
		return Config::updateSettingsFile($configVars, $keepQuotes, $rebuild);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBoardUrl(): string
	{
		$this->ensureLoaded();

		return $this->settings['boardurl'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getScriptUrl(): string
	{
		$this->ensureLoaded();

		// scripturl is derived from boardurl
		return ($this->settings['boardurl'] ?? '') . '/index.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBoardDir(): string
	{
		$this->ensureLoaded();

		return $this->settings['boarddir'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSourcesDir(): string
	{
		$this->ensureLoaded();

		return $this->settings['sourcedir'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCacheDir(): string
	{
		$this->ensureLoaded();

		return $this->settings['cachedir'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLanguagesDir(): string
	{
		$this->ensureLoaded();

		return $this->settings['languagesdir'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isMaintenanceMode(): bool
	{
		$this->ensureLoaded();

		return !empty($this->settings['maintenance']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMaintenanceLevel(): int
	{
		$this->ensureLoaded();

		return $this->settings['maintenance'] ?? 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getForumName(): string
	{
		$this->ensureLoaded();

		return $this->settings['mbname'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDatabaseType(): string
	{
		$this->ensureLoaded();

		return $this->settings['db_type'] ?? 'mysql';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDatabaseServer(): string
	{
		$this->ensureLoaded();

		return $this->settings['db_server'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDatabaseName(): string
	{
		$this->ensureLoaded();

		return $this->settings['db_name'] ?? '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDatabasePrefix(): string
	{
		$this->ensureLoaded();

		return $this->settings['db_prefix'] ?? '';
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Ensure settings are loaded from Settings.php.
	 *
	 */
	protected function ensureLoaded(): void
	{
		if (!$this->loaded) {
			$this->loadSettings();
		}
	}

	/**
	 * Load settings from Settings.php file.
	 *
	 * This method loads settings independently from Config class.
	 * If Config has already loaded settings, we use those for efficiency.
	 *
	 */
	protected function loadSettings(): void
	{
		// If Config has already loaded settings, use those for efficiency
		if (!empty(Config::$boardurl)) {
			$this->syncFromConfig();
			$this->loaded = true;

			return;
		}

		// Otherwise, load Settings.php ourselves
		if (empty($this->settingsFile) || !file_exists($this->settingsFile)) {
			// Try to find Settings.php
			foreach (get_included_files() as $file) {
				if (basename($file) === 'Settings.php') {
					$this->settingsFile = $file;
					break;
				}
			}

			if (empty($this->settingsFile) || !file_exists($this->settingsFile)) {
				$this->loaded = true;

				return;
			}
		}

		// Load Settings.php in isolated scope
		$this->settings = $this->loadSettingsFile($this->settingsFile);
		$this->loaded = true;
	}

	/**
	 * Load settings from a file in an isolated scope.
	 *
	 * @param string $file Path to Settings.php file.
	 * @return array Array of settings.
	 */
	protected function loadSettingsFile(string $file): array
	{
		// Create isolated scope to load Settings.php
		$loadSettings = function ($settingsFile) {
			// Suppress any output or errors from Settings.php
			ob_start();
			$result = @include $settingsFile;
			ob_end_clean();

			// Get all defined variables from the included file
			return get_defined_vars();
		};

		$vars = $loadSettings($file);

		// Remove the closure and file path from the variables
		unset($vars['settingsFile'], $vars['result']);

		return $vars;
	}

	/**
	 * Sync settings from static Config class.
	 *
	 * This is used when Config has already loaded settings for efficiency.
	 *
	 */
	protected function syncFromConfig(): void
	{
		// Get all public static properties from Config
		$reflection = new \ReflectionClass(Config::class);

		foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_STATIC) as $property) {
			$name = $property->getName();

			// Skip modSettings and other runtime properties
			if (\in_array($name, ['modSettings', 'scripturl', 'loader', 'custom'])) {
				continue;
			}

			if ($property->isInitialized()) {
				$this->settings[$name] = $property->getValue();
			}
		}

		// Also include custom settings
		if (!empty(Config::$custom)) {
			$this->settings = array_merge($this->settings, Config::$custom);
		}
	}
}
