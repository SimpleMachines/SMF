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
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Constants for webserver software names.
	 */
	public const SERVER_IIS = 'iis';
	public const SERVER_APACHE = 'apache';
	public const SERVER_LITESPEED = 'litespeed';
	public const SERVER_LIGHTTPD = 'lighttpd';
	public const SERVER_NGINX = 'nginx';

	/**
	 * Constants for operating system names.
	 */
	public const OS_WINDOWS = 'Windows';
	public const OS_MAC = 'Darwin';
	public const OS_LINUX = 'Linux';

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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string
	 *
	 * Path to a temporary directory.
	 */
	protected static string $temp_dir;

	/**
	 * @var float
	 *
	 * Current system load
	 */
	protected static ?float $current_load = null;

	/**
	 * @var int
	 *
	 * Number of CPUs detected
	 */
	protected static ?int $cpu_count = null;

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
		if (isset($_SERVER['SERVER_SOFTWARE'])) {
			foreach ((array) $server as $serv) {
				if (preg_match('~' . (self::$server_software[$serv] ?? $serv) . '~i', $_SERVER['SERVER_SOFTWARE'])) {
					return true;
				}
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
		// Already did this.
		if (!empty(self::$temp_dir)) {
			return self::$temp_dir;
		}

		// Temp Directory options order.
		$temp_dir_options = [
			0 => 'sys_get_temp_dir',
			1 => 'upload_tmp_dir',
			2 => 'session.save_path',
			3 => 'cachedir',
		];

		// Is Config::$cachedir a valid option?
		if (empty(Config::$cachedir) || !is_dir(Config::$cachedir) || !is_writable(Config::$cachedir)) {
			$temp_dir_options = array_diff($temp_dir_options, ['cachedir']);
		}

		// Determine if we should detect a restriction and what restrictions that may be.
		$open_base_dir = \ini_get('open_basedir');
		$restriction = !empty($open_base_dir) ? explode(':', $open_base_dir) : false;

		// Prevent any errors as we search.
		$old_error_reporting = error_reporting(0);

		// Search for a working temp directory.
		foreach ($temp_dir_options as $id_temp => $temp_option) {
			switch ($temp_option) {
				case 'cachedir':
					$possible_temp = rtrim(Config::$cachedir, '\\/');
					break;

				case 'session.save_path':
					$possible_temp = rtrim(\ini_get('session.save_path'), '\\/');
					break;

				case 'upload_tmp_dir':
					$possible_temp = rtrim(\ini_get('upload_tmp_dir'), '\\/');
					break;

				default:
					$possible_temp = sys_get_temp_dir();
					break;
			}

			// Check if we have a restriction preventing this from working.
			if ($restriction) {
				foreach ($restriction as $dir) {
					if (str_contains($possible_temp, $dir) && is_writable($possible_temp)) {
						self::$temp_dir = $possible_temp;
						break;
					}
				}
			}
			// No restrictions, but need to check for writable status.
			elseif (is_writable($possible_temp)) {
				self::$temp_dir = $possible_temp;
				break;
			}
		}

		// Fall back to sys_get_temp_dir even though it won't work, so we have something.
		if (empty(self::$temp_dir)) {
			self::$temp_dir = sys_get_temp_dir();
		}

		// Put things back.
		error_reporting($old_error_reporting);

		return self::$temp_dir;
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
		$memory_current = self::memoryReturnBytes(\ini_get('memory_limit'));
		$memory_needed = self::memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use) {
			$memory_needed += memory_get_usage();
		}

		// If more is needed, request it.
		if ($memory_current < $memory_needed) {
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = self::memoryReturnBytes(\ini_get('memory_limit'));
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
		if (\is_integer($val)) {
			return (int) $val;
		}

		// Separate the number from the designator.
		$val = trim($val);
		$num = \intval(substr($val, 0, \strlen($val) - 1));
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
	 * @return bool True on success, or false on failure.
	 */
	public static function setTimeLimit(int $limit = 600): bool
	{
		try {
			return set_time_limit($limit);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Makes call to the Server API (SAPI) to reset the timeout.
	 *
	 * @suppress PHP0417
	 */
	public static function resetTimeout()
	{
		if (self::isSoftware(self::SERVER_APACHE) && \function_exists('apache_reset_timeout')) {
			try {
				apache_reset_timeout();
			} catch (\Exception $e) {
			}
		}
	}

	/**
	 * Determiens the load average.
	 * On windows we return -0.01.
	 * On Linux we attempt to use sys_getloadavg, fall back to traditional reading of /proc/loadavg.
	 * If we can't load the load average, we return -0.01
	 * Linux Note: The returned value is a percent represented as a float. However, the percent
	 * 	can exceed 1.00 (100%) if more than 1 cpu is present. In a 4 cpu system, the max would
	 * 	be 400% or 4.00.
	 *
	 * @return float
	 */
	public static function getLoadAverage(): float
	{
		if (self::$current_load !== null) {
			return self::$current_load;
		}

		/*
		 * It is possible to get Windows load average using a method such as:
		 * 		wmic cpu get loadpercentage /all /format:value
		 * 		typeperf -sc 1 "\Processor(_Total)\% Processor Time"
		 * However this is slow to respond
		 */
		if (self::isOS(self::OS_WINDOWS)) {
			return self::$current_load = -0.01;
		}

		try {
			// False | array[0 => 1 minute, 1 => 5 minute, 2 => 15 minute].
			$current_load = sys_getloadavg();

			// sys_getloadavg returns false on failure.
			if ($current_load !== false) {
				return self::$current_load = (float) $current_load[0] / self::getCpuCount();
			}

			// Most Linux distros offer a nice file that we can read.
			$current_load = @file_get_contents('/proc/loadavg');

			if (!empty($current_load) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $current_load, $matches) !== 0) {
				return self::$current_load = (float) $matches[1] / self::getCpuCount();
			}

			// On both Linux and Unix (e.g. macOS), we can we can check shell_exec('uptime').
			if (($current_load = @shell_exec('uptime')) !== null && preg_match('~load averages?: (\d+\.\d+)~i', $current_load, $matches) !== 0) {
				return self::$current_load = (float) $matches[1] / self::getCpuCount();
			}
		} catch (\Exception $ex) {
		}

		// No sys_getloadavg, shell_exec('uptime') and no /proc/loadavg, so we can't check.
		return self::$current_load = -0.01;
	}

	/**
	 * Checks if the server load meets a threshold.
	 *
	 * @param null|int|float|string $threshold
	 */
	public static function isOverloaded(int|float|string|null $threshold): bool
	{
		if (empty($threshold)) {
			return false;
		}

		return self::getLoadAverage() >= (float) $threshold;
	}

	/**
	 * Returns the number of CPUs as reported by the system.
	 * On Windows we check getenv, do not use wmic as its slow.
	 * On linux we first attempt with nproc and then fall back to parsing /proc/cpuinfo.
	 * If all attempts fail, return 1.
	 * Always ensure we have at least 1 CPU, as this is used in math functions.
	 *
	 * @param bool $update Update the db backed cache value.
	 * @return int Number of CPUs detected.
	 */
	public static function getCpuCount(bool $update = false): int
	{
		if (self::$cpu_count !== null) {
			return self::$cpu_count;
		}

		if (!$update && isset(Config::$modSettings['cpu_count'])) {
			return (int) Config::$modSettings['cpu_count'];
		}

		try {
			// Avoid using wmic commands, otherwise this would work as a fallback: wmic computersystem get NumberOfLogicalProcessors
			if (self::isOS(self::OS_WINDOWS)) {
				self::$cpu_count = min((int) getenv('NUMBER_OF_PROCESSORS') ?? 1, 1);
			}
			// Apple is special, check sysctl
			elseif (self::isOS(self::OS_MAC)) {
				if (($cpu_count = @shell_exec('sysctl -n hw.physicalcpu')) !== null && preg_match('~\d~i', $cpu_count, $matches) !== 0) {
					self::$cpu_count = max((int) $cpu_count, 1);
				}
			}
			// On most Linux distros, we can runn nproc.
			elseif (($cpu_count = @shell_exec('nproc --all')) !== null && preg_match('~\d~i', $cpu_count, $matches) !== 0) {
				self::$cpu_count = max((int) $cpu_count, 1);
			}

			// This works for both Mac and Linux, however it actually reports online cpus, not total CPUs.
			// Could also use _NPROCESSORS_CONF which is processors configured.
			if (empty(self::$cpu_count) && !self::isOS(self::OS_WINDOWS) && ($cpu_count = @shell_exec('getconf _NPROCESSORS_ONLN')) !== null && preg_match('~\d~i', $cpu_count, $matches) !== 0) {
				self::$cpu_count = max((int) $cpu_count, 1);
			}

			// Borrowed from: https://www.php.net/manual/en/function.sys-getloadavg.php#129847
			// Maybe consider using awk to simplify this: grep -m 1 'cpu cores' /proc/cpuinfo | awk -F: '{print $2}'
			if (empty(self::$cpu_count) && !self::isOS(self::OS_WINDOWS) && file_exists('/proc/cpuinfo')) {
				preg_match_all('/^processor/m', file_get_contents('/proc/cpuinfo'), $matches);

				if (isset($matches[0])) {
					self::$cpu_count = max(\count($matches[0]), 1);
				}
			}
		} catch (\Exception $ex) {
		}

		// No CPUs found, I think we have at least one. Avoids divide by zero errors.
		if (empty(self::$cpu_count)) {
			self::$cpu_count = 1;
		}

		Config::updateModSettings(['cpu_count' => self::$cpu_count]);

		return self::$cpu_count;
	}

	/**
	 * Normalizes directory separators and resolves '.' and '..' in a file path.
	 *
	 * The $path does not need to point to an existing file.
	 *
	 * If $path does point to an existing file, or if an ancestor directory of
	 * $path exists, then \realpath() will be used to resolve that part of the
	 * path, unless the $real parameter is set to false.
	 *
	 * @param string $path The file path.
	 * @param string|bool $base_dir Base directory for relative paths.
	 *    - If a string, relative paths are prepended with the string and a
	 *      directory separator. Note that directory separators in this string
	 *      will be normalized just like in $path.
	 *    - If true, relative paths are prepended with the current working
	 *      directory and a directory separator.
	 *    - If false, relative paths are processed as given.
	 *    Default: false.
	 * @param bool $real Whether to get the real path for existing files. This
	 *    can be set to false if the caller wants to canonicalize a hypothetical
	 *    path without any possibility of the real file structure interfering
	 *    with the result.
	 *    Default: true.
	 * @return string The canonical file path.
	 */
	public static function canonicalPath(string $path, string|bool $base_dir = false, bool $real = true): string
	{
		// If $path points to a real file, this is all we need to do.
		if (!empty($real) && ($realpath = @realpath($path)) !== false) {
			return $realpath;
		}

		$base_dir = \is_string($base_dir) ? rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $base_dir), DIRECTORY_SEPARATOR) : (!empty($base_dir) ? getcwd() : false);

		$path = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path));

		// We need to know the path of the root directory.
		if (DIRECTORY_SEPARATOR === '/') {
			$root = '';
			$is_absolute = str_starts_with($path, DIRECTORY_SEPARATOR);
		} else {
			// Windows network shares and devices.
			if (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
				if (\in_array(substr($path, 2, 2), ['?' . DIRECTORY_SEPARATOR, '.' . DIRECTORY_SEPARATOR])) {
					$root = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR, 3));
				} else {
					$root = '';

					for ($i = 0; $i < 3; $i++) {
						$root = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR, \strlen($root) + 1));
					}
				}
			}
			// Windows absolute DOS-style path.
			elseif (strpos($path, ':') !== false && strpos($path, DIRECTORY_SEPARATOR) === strpos($path, ':') + 1) {
				$root = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR));
			}
			// Windows relative path.
			else {
				$root = substr(getcwd(), 0, strcspn(getcwd(), DIRECTORY_SEPARATOR));

				// If relative to current drive's root, make it absolute.
				if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
					$path = $root . $path;
				}
			}

			$is_absolute = str_starts_with($path, $root . DIRECTORY_SEPARATOR);
		}

		// Build canonical path.
		$canonical_path = '';

		if ($is_absolute) {
			$path = substr($path, \strlen($root . DIRECTORY_SEPARATOR));
			$path_parts = [$root];
		} elseif (\is_string($base_dir)) {
			$path_parts = explode(DIRECTORY_SEPARATOR, $base_dir);
		} else {
			$path_parts = [];
		}

		foreach (explode(DIRECTORY_SEPARATOR, $path) as $key => $part) {
			if (empty($part) || $part === '.') {
				continue;
			}

			if ($part === '..') {
				if ($is_absolute && $path_parts === [$root]) {
					continue;
				}

				if (empty($path_parts) || $path_parts[0] === '..') {
					$path_parts[] = $part;
				} else {
					array_pop($path_parts);
				}
			} else {
				$path_parts[] = $part;
			}

			$canonical_path = implode(DIRECTORY_SEPARATOR, $path_parts);

			if (empty($real) || \in_array($canonical_path, ['', '.', '..'])) {
				continue;
			}

			// Check for intermediate symlinks.
			$realpath = @realpath($canonical_path);

			if ($realpath !== false && $realpath !== $canonical_path) {
				$path_parts = explode(DIRECTORY_SEPARATOR, $realpath);
			}
		}

		// Ambiguity is bad.
		if ($canonical_path === '') {
			$canonical_path = $is_absolute ? $root . DIRECTORY_SEPARATOR : '.';
		}

		return $canonical_path;
	}
}
