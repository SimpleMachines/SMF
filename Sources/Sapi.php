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

namespace SMF;

/**
 * Server Application Programming Interface.
 *
 * This file handles all interaction between the application (SMF) and the server.
 *
 * Not all APIs have are functional on all server interfaces.  If this is the case
 * the API call will do nothing.
 *
 * Some functions may not be defined or definable in the documentation as they are
 * bundled by 3rd party processes, such as apache_* functions.  To suppress IDE
 * warnings, we use 'suppress PHP0417'.
 */
class Sapi
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Server Map.
	 */
	protected array $server_software = [
		self::SERVER_IIS => 'Microsoft-IIS',
		self::SERVER_APACHE => 'Apache',
		self::SERVER_LITESPEED => 'LiteSpeed',
		self::SERVER_LIGHTTPD => 'lighttpd',
		self::SERVER_NGINX => 'nginx',
	];

	/*****************
	 * Class constants
	 *****************/

	public const SERVER_IIS = 'iis';
	public const SERVER_APACHE = 'apache';
	public const SERVER_LITESPEED = 'litespeed';
	public const SERVER_LIGHTTPD = 'lighttpd';
	public const SERVER_NGINX = 'nginx';

	public const OS_WINDOWS = 'Windows';
	public const OS_MAC = 'Darwin';
	public const OS_LINUX = 'Linux';

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks if the server is running a specific server software.
	 *
	 * @param string|string[] $server The server software as defined in our constants.
	 * @return bool True if we are running the requested software, false otherwise.
	 */
	public static function isSoftware(string|array $server): bool
	{
		$servers = (array) $server;

		foreach ($servers as $server) {
			if (
				isset($_SERVER['SERVER_SOFTWARE'])
				&& strpos($_SERVER['SERVER_SOFTWARE'], self::$server_software[$server] ?? $server) !== false
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the server is running a specific operating system.
	 *
	 * @param string|string[] $os The os as defined in our constants
	 * @return bool True if we are running the requested os, false otherwise.
	 */
	public static function isOS(string|array $os): bool
	{
		$oses = (array) $os;

		/*
		 * Technically we could simplify this down using PHP_OS_FAMILY,
		 * but to ensure backwards compatibility, we won't yet.
		 */
		foreach ($oses as $os) {
			$is_os = false;

			switch ($os) {
				case self::OS_WINDOWS:
					$is_os = PHP_OS_FAMILY === self::OS_WINDOWS || DIRECTORY_SEPARATOR === '\\';
					break;

				case self::OS_MAC:
					$is_os = PHP_OS_FAMILY === self::OS_MAC;
					break;

				// This may result in false positives because 'linux' is very broad.
				case self::OS_LINUX:
					$is_os = PHP_OS_FAMILY === self::OS_LINUX;
					break;
			}

			if ($is_os) {
				return true;
			}
		}

		// Last ditch effort.
		return PHP_OS_FAMILY === $os;
	}

	/**
	 * Checks If we are running a CGI instance.
	 *
	 * @return bool True if we are running under CGI, false otherwise.
	 */
	public static function isCGI(): bool
	{
		return isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false;
	}

	/**
	 * Checks If we are running a CLI (shell/cron) instance.
	 *
	 * @return bool True if we are running under CLI, false otherwise.
	 */
	public static function isCLI(): bool
	{
		return empty($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Checks if the server is able to support case folding.
	 *
	 * @see https://www.w3.org/TR/charmod-norm/#definitionCaseFolding
	 * @return bool True if it does, false otherwise
	 */
	public static function supportsIsoCaseFolding(): bool
	{
		return ord(strtolower(chr(138))) === 154;
	}

	/**
	 * A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	 *
	 * @return bool True if it does, false otherwise
	 */
	public static function needsLoginFix(): bool
	{
		return self::isCGI() && self::isSoftware(self::SERVER_IIS);
	}

	/**
	 * (Re)initializes some $context values that need to be set dynamically.
	 */
	public static function load(): void
	{
		// This determines the server... not used in many places, except for login fixing.
		Utils::$context['server'] = [
			'is_iis' => self::isSoftware(self::SERVER_IIS),
			'is_apache' => self::isSoftware(self::SERVER_APACHE),
			'is_litespeed' => self::isSoftware(self::SERVER_LITESPEED),
			'is_lighttpd' => self::isSoftware(self::SERVER_LIGHTTPD),
			'is_nginx' => self::isSoftware(self::SERVER_NGINX),
			'is_cgi' => self::isCGI(),
			'is_windows' => self::isOS(self::OS_WINDOWS),
			'is_mac' => self::isOS(self::OS_MAC),
			'iso_case_folding' => self::supportsIsoCaseFolding(),
			'needs_login_fix' => self::needsLoginFix(),
		];
	}

	/**
	 * Locates the most appropriate temporary directory.
	 *
	 * Systems using `open_basedir` restrictions may receive errors with
	 * `sys_get_temp_dir()` due to misconfigurations on servers. Other
	 * cases sys_temp_dir may not be set to a safe value. Additionally
	 * `sys_get_temp_dir` may use a readonly directory. This attempts to
	 * find a working temp directory that is accessible under the
	 * restrictions and is writable to the web service account.
	 *
	 * Directories checked against `open_basedir`:
	 *
	 * - `sys_get_temp_dir()`
	 * - `upload_tmp_dir`
	 * - `session.save_path`
	 * - `cachedir`
	 *
	 * @return string Path to a temporary directory.
	 */
	public static function getTempDir(): string
	{
		return Config::getTempDir();
	}

	/**
	 * Helper function to set the system memory to a needed value.
	 *
	 * - If the needed memory is greater than current, will attempt to get more.
	 * - If $in_use is set to true, will also try to take the current memory
	 *   usage in to account.
	 *
	 * @param string $needed The amount of memory to request. E.g.: '256M'.
	 * @param bool $in_use Set to true to account for current memory usage.
	 * @return bool Whether we have the needed amount memory.
	 */
	public static function setMemoryLimit(string $needed, bool $in_use = false): bool
	{
		// Everything in bytes.
		$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		$memory_needed = self::memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use) {
			$memory_needed += memory_get_usage();
		}

		// If more is needed, request it.
		if ($memory_current < $memory_needed) {
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		}

		$memory_current = max($memory_current, self::memoryReturnBytes(get_cfg_var('memory_limit')));

		// Return success or not.
		return (bool) ($memory_current >= $memory_needed);
	}

	/**
	 * Helper function to convert memory string settings to bytes
	 *
	 * @param string $val The byte string, like '256M' or '1G'.
	 * @return int The string converted to a proper integer in bytes.
	 */
	public static function memoryReturnBytes(string $val): int
	{
		if (is_integer($val)) {
			return (int) $val;
		}

		// Separate the number from the designator.
		$val = trim($val);
		$num = intval(substr($val, 0, strlen($val) - 1));
		$last = strtolower(substr($val, -1));

		// Convert to bytes.
		switch ($last) {
			case 'g':
				$num *= 1024;
				// no break

			case 'm':
				$num *= 1024;
				// no break

			case 'k':
				$num *= 1024;
		}

		return $num;
	}

	/**
	 * Check if the connection is using HTTPS.
	 *
	 * @return bool Whether the connection is using HTTPS.
	 */
	public static function httpsOn(): bool
	{
		return ($_SERVER['HTTPS'] ?? null) == 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) == 'https' || ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? null) == 'on';
	}

	/**
	 * Makes call to the Server API (SAPI) to increase the time limit.
	 *
	 * @param int $limit Requested amount of time, defaults to 600 seconds.
	 */
	public static function setTimeLimit(int $limit = 600)
	{
		try {
			set_time_limit($limit);
		} catch (\Exception $e) {
		}
	}

	/**
	 * Makes call to the Server API (SAPI) to reset the timeout.
	 *
	 * @suppress PHP0417
	 */
	public static function resetTimeout()
	{
		if (self::isSoftware(self::SERVER_APACHE) && function_exists('apache_reset_timeout')) {
			try {
				apache_reset_timeout();
			} catch (\Exception $e) {
			}
		}
	}
}

?>