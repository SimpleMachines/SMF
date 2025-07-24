<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

namespace SMF\PackageManager\FileSystem;

use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PackageManager\FileSystem\APIs\Ftp;

abstract class FileSystem
{
	/*****************
	 * Class constants
	 *****************/

	public const CHMOD_FILE = [0644, 0664, 0666];
	public const CHMOD_DIR = [0750, 0755, 0775, 0777];

	/**
	 * @var string
	 *
	 * The directory containing our file systems we can use.
	 */
	public const APIS_FOLDER = __DIR__ . '/APIs';

	/**
	 * @var string
	 *
	 * The root namespace used by all our file systems.
	 */
	public const APIS_NAMESPACE = __NAMESPACE__ . '\\APIs\\';

	/**
	 * @var string
	 *
	 * Default File System to use or to fallback to if the file selected is not supported or configured correctly.
	 */
	public const APIS_DEFAULT = Ftp::class;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var string
	 *
	 * Name of the selected file system.
	 */
	public static string $filesystem;

	/**
	 * @var \SMF\PackageManager\FileSystem\FileSystemInterface|bool|null
	 *
	 * The loaded file system, or false on failure.
	 */
	public static FileSystemInterface|bool|null $loaded_api = null;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string Holds any errors
	 */
	protected ?string $error = null;

	/**
	 * @var string Holds the last message from the server
	 */
	protected ?string $last_message = null;

	protected ?string $forum_root = null;

	/**
	 * @var string The maximum SMF version that this will work with.
	 */
	protected string $version_compatible = '3.0.999';

	/**
	 * @var string The minimum SMF version that this will work with.
	 */
	protected string $min_smf_version = '3.0 Alpha 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the requirements for the file system are available.
	 *
	 * @return bool True if the file system is supported, false otherwise.
	 */
	public function isSupported(): bool
	{
		return true;
	}

	/**
	 * Checks if the file system has been configured for usage.
	 *
	 * @return bool True if the system is configured, false otherwise.
	 */
	public function isConfigured(): bool
	{
		return true;
	}

	/**
	 * Is our SMF version supported with this File System.
	 *
	 * @param string $smfVersion
	 * @return bool Whether the specified version is compatible
	 */
	public function isCompatible(string $smfVersion): bool
	{
		return version_compare($this->min_smf_version, $smfVersion, '<=') && version_compare($smfVersion, $this->version_compatible, '>=');
	}

	/**
	 * Provides additional settings for the settings page.
	 *
	 * @param array $config_vars Current configuration settings, passed by reference.  Append to add more.
	 */
	public function getConfigVars(array &$config_vars): void {}

	/**
	 * Gets the min version that we support.
	 *
	 * @return string the value of $key.
	 */
	public function getMinimumVersion(): string
	{
		return $this->min_smf_version;
	}

	/**
	 * Gets the max version that we support.
	 *
	 * @return string the value of $key.
	 */
	public function getVersion(): string
	{
		return $this->min_smf_version;
	}

	/**
	 * Gets the class identifier of the current file system implementation.
	 *
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string
	{
		$class_name = get_class($this);

		if ($position = strrpos($class_name, '\\')) {
			return substr($class_name, $position + 1);
		}

		return $class_name;
	}

	final public function setForumRoot(string $root): void
	{
		$this->forum_root = $root;
		var_dump(value: $this->forum_root);

		die;
	}

	final public function normalizeFilename(string $filename): string
	{
		if (empty($this->forum_root)) {
			return $filename;
		}

		$normalized = strtr($filename, [rtrim(Config::$boarddir, '/') => rtrim($this->forum_root, '/')]);

		if ($normalized === '') {
			return '.';
		}

		return $normalized;
	}

	/**
	 * Returns the last error message seen by the file system.
	 * This typically returns a string that is later prepended with "filesystem_error_" and looked up for a translation.
	 *
	 * @return string
	 */
	public function getLastError(): ?string
	{
		return $this->error;
	}

	/**
	 * Returns the last message seen by the file system, may be informational.
	 *
	 * @return string
	 */
	public function getLastMessage(): ?string
	{
		return $this->last_message;
	}

	/**
	 * Detects the current path
	 *
	 * @param string $directory The full path from the filesystem
	 * @param null|string $lookup_file The name of a file in the specified path
	 * @return array An array of detected info - username, path from FTP root and whether or not the current path was found
	 */
	public function detectForumPath(string $directory, ?string $lookup_file = null): array
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT'])) {
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match)) {
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], ['/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => '']);

				if (str_ends_with($path, '/')) {
					$path = substr($path, 0, -1);
				}

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1) {
					$path .= dirname($_SERVER['PHP_SELF']);
				}
			} elseif (str_starts_with($directory, '/var/www/')) {
				$path = substr($directory, 8);
			} else {
				$path = strtr(strtr($directory, ['\\' => '/']), [$_SERVER['DOCUMENT_ROOT'] => '']);
			}
		} else {
			$path = '';
		}

		return [$username, $path, false];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Try to load up a supported file system method.
	 *
	 * @param string|FileSystemInterface|null $api Load the specified API, otherwise the assigned one or default.
	 * @return FileSystemInterface&FileSystem|false An instance of a child class of this class, or false on failure.
	 */
	final public static function load(string|FileSystemInterface|null $api = null): bool|FileSystemInterface
	{
		// Already loaded the API from the default.
		if ($api == null && self::$loaded_api !== null && self::$loaded_api instanceof FileSystemInterface) {
			return self::$loaded_api;
		}

		// API to load.
		$api ??= Config::$modSettings['filesystem_type'] ?? Ftp::class;

		// What api we are going to try.
		if ($api instanceof FileSystemInterface) {
			$fully_qualified_class_name = get_class($api);
		} elseif (strpos($api, self::APIS_NAMESPACE) !== 0) {
			$fully_qualified_class_name = self::APIS_NAMESPACE . $api;
		} else {
			$fully_qualified_class_name = $api;
		}

		// Do some basic tests.
		$api = false;

		if (class_exists($fully_qualified_class_name)) {
			/* @var MailAgentInterface $agent_api */
			$api = new $fully_qualified_class_name();

			// There are rules you know...
			if (!($api instanceof FileSystemInterface) || !($api instanceof FileSystem)) {
				$api = false;
			}

			// No Support?  NEXT!
			if ($api && !$api->isSupported() && !$api->isConfigured()) {
				// Can we save ourselves?
				if ($fully_qualified_class_name !== self::APIS_DEFAULT) {
					return self::load(null);
				}

				$api = false;
			}
		}

		return $api;
	}

	final public static function getSelectOptions(): array
	{
		$detected_apis = self::detect();
		$apis_names = [];

		foreach ($detected_apis as $class_name => $agent) {
			$class_name_txt_key = strtolower($agent->getImplementationClassKeyName());

			$apis_names[$class_name] = Lang::txtExists($class_name_txt_key . '_filesystem', file: 'Packages') ? Lang::getTxt($class_name_txt_key . '_filesystem', file: 'PackageManager') : $class_name;
		}

		return $apis_names;
	}

	/**
	 * Get the installed File System implementations.
	 *
	 * @return FileSystemInterface[] An array of mail agents
	 */
	final public static function detect(): array
	{
		$loaded_apis = [];

		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_path => $file_info) {
			$class_name = $file_info->getBasename('.php');
			$fully_qualified_class_name = self::APIS_NAMESPACE . $class_name;

			if (!class_exists($fully_qualified_class_name)) {
				continue;
			}

			$agent_api = new $fully_qualified_class_name();

			// Deal with it!
			if (!($agent_api instanceof FileSystemInterface) || !($agent_api instanceof FileSystem)) {
				continue;
			}

			// No Support?  NEXT!
			if (!$agent_api->isSupported()) {
				continue;
			}

			$loaded_apis[$class_name] = $agent_api;
		}

		IntegrationHook::call('integrate_load_filesystems', [&$loaded_apis]);

		return $loaded_apis;
	}
}
