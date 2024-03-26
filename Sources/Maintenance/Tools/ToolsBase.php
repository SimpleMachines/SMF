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

namespace SMF\Maintenance\Tools;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\PackageManager\FtpConnection;
use SMF\Sapi;
use SMF\SecurityToken;
use SMF\Utils;

/**
 * Base class for all our tools. Includes commonly needed logic among all tools.
 */
abstract class ToolsBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Script name of the tool we are running.
	 */
	public string $script_name;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var FtpConnection
	 *
	 * Object container for the FTP session.
	 */
	private FtpConnection $ftp;

	/**
	 * @var bool
	 *
	 * Debugging the upgrade.
	 */
	public bool $debug = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Find all databases that are supported on this system.
	 *
	 * @return array An array of supported databases in the format of
	 *    $db_key => (Object for DatabaseInterface) $db
	 */
	public function supportedDatabases(): array
	{
		static $dbs = [];

		if (count($dbs) > 0) {
			return $dbs;
		}

		if (!file_exists(Config::$sourcedir . '/Maintenance/Database/') || !file_exists(Config::$sourcedir . '/Db/APIs')) {
			return $dbs;
		}

		$dir = dir(Config::$sourcedir . '/Maintenance/Database/');

		while ($entry = $dir->read()) {
			if (is_dir(Config::$languagesdir . '/' . $entry) || $entry == 'index.php' || substr($entry, -3) !== 'php') {
				continue;
			}

			/** @var \SMF\Maintenance\Database\DatabaseInterface $db_class */
			$db_class = '\\SMF\\Maintenance\\Database\\' . substr($entry, 0, -4);

			require_once Config::$sourcedir . '/Maintenance/Database/' . $entry;
			$db = new $db_class();

			if (!$db->isSupported()) {
				continue;
			}

			$dbs[substr($entry, 0, -4)] = $db;
		}

		ksort($dbs);

		return $dbs;
	}

	/**
	 * Last chance to do anything before we exit.
	 *
	 * Some tools may call this to save their progress, etc.
	 */
	public function preExit(): void
	{
	}

	/**
	 * Given a database type, loads the maintenance database object.
	 *
	 * @param string $db_type The database type, typically from Config::$db_type.
	 * @return DatabaseInterface The database object.
	 */
	public function loadMaintenanceDatabase(string $db_type): DatabaseInterface
	{
		/** @var \SMF\Maintenance\Database\DatabaseInterface $db_class */
		$db_class = '\\SMF\\Maintenance\\Database\\' . Db::getClass(Config::$db_type);

		require_once Config::$sourcedir . '/Maintenance/Database/' . Db::getClass(Config::$db_type) . '.php';

		return new $db_class();
	}

	/**
	 * Used by various places to determine if the tool is in debug mode or not.
	 *
	 * @return bool
	 */
	public function isDebug(): bool
	{
		return $this->debug ?? false;
	}

	/**
	 * Delete the tool.
	 *
	 * This is typically called with a ?delete.
	 *
	 * No output is returned. Upon successful deletion, the browser is
	 * redirected to a blank file.
	 */
	final public function deleteTool(): void
	{
		if (!empty($this->script_name) && file_exists(Config::$boarddir . '/' . $this->script_name)) {
			if (!empty($_SESSION['ftp'])) {
				$ftp = new FtpConnection($_SESSION['ftp']['server'], $_SESSION['ftp']['port'], $_SESSION['ftp']['username'], $_SESSION['ftp']['password']);
				$ftp->chdir($_SESSION['ftp']['path']);
				$ftp->unlink($this->script_name);
				$ftp->close();

				unset($_SESSION['ftp']);
			} else {
				@unlink(Config::$boarddir . '/' . $this->script_name);
			}

			// Now just redirect to a blank.png...
			header('location: http' . (Sapi::httpsOn() ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');
		}
	}

	/**
	 * Make file writable. First try to use regular chmod, but if that fails, try to use FTP.
	 *
	 * @param string $file file to make writable.
	 * @return bool True if succesfull, false otherwise.
	 */
	final public function quickFileWritable(string $file): bool
	{
		$files = [$file];

		return $this->makeFilesWritable($files);
	}

	/**
	 * Make files writable. First try to use regular chmod, but if that fails, try to use FTP.
	 *
	 * @param array $files List of files to make writable.
	 * @return bool True if succesfull, false otherwise.
	 */
	final public function makeFilesWritable(array &$files): bool
	{
		if (empty($files)) {
			return true;
		}

		$failure = false;

		// On linux, it's easy - just use is_writable!
		// Windows is trickier.  Let's try opening for r+...
		if (Sapi::isOS(Sapi::OS_WINDOWS)) {
			foreach ($files as $k => $file) {
				// Folders can't be opened for write... but the index.php in them can ;).
				if (is_dir($file)) {
					$file .= '/index.php';
				}

				// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
				@chmod($file, 0777);
				$fp = @fopen($file, 'r+');

				// Hmm, okay, try just for write in that case...
				if (!$fp) {
					$fp = @fopen($file, 'w');
				}

				if (!$fp) {
					$failure = true;
				} else {
					unset($files[$k]);
				}
				@fclose($fp);
			}
		} else {
			foreach ($files as $k => $file) {
				// Some files won't exist, try to address up front
				if (!file_exists($file)) {
					@touch($file);
				}

				// NOW do the writable check...
				if (!is_writable($file)) {
					@chmod($file, 0755);

					// Well, 755 hopefully worked... if not, try 777.
					if (!is_writable($file) && !@chmod($file, 0777)) {
						$failure = true;
					}
					// Otherwise remove it as it's good!
					else {
						unset($files[$k]);
					}
				} else {
					unset($files[$k]);
				}
			}
		}

		if (empty($files)) {
			return true;
		}

		if (!isset($_SERVER)) {
			return !$failure;
		}

		// What still needs to be done?
		Maintenance::$context['chmod_files'] = $files;

		// If it's windows it's a mess...
		if ($failure && Sapi::isOS(Sapi::OS_WINDOWS)) {
			Maintenance::$context['chmod']['ftp_error'] = 'total_mess';

			return false;
		}

		// We're going to have to use... FTP!
		if ($failure) {
			// Load any session data we might have...
			if (!isset($_POST['ftp_username']) && isset($_SESSION['temp_ftp'])) {
				Maintenance::$context['chmod']['server'] = $_SESSION['temp_ftp']['server'];
				Maintenance::$context['chmod']['port'] = $_SESSION['temp_ftp']['port'];
				Maintenance::$context['chmod']['username'] = $_SESSION['temp_ftp']['username'];
				Maintenance::$context['chmod']['password'] = $_SESSION['temp_ftp']['password'];
				Maintenance::$context['chmod']['path'] = $_SESSION['temp_ftp']['path'];
			}
			// Or have we submitted?
			elseif (isset($_POST['ftp_username'])) {
				Maintenance::$context['chmod']['server'] = $_POST['ftp_server'];
				Maintenance::$context['chmod']['port'] = $_POST['ftp_port'];
				Maintenance::$context['chmod']['username'] = $_POST['ftp_username'];
				Maintenance::$context['chmod']['password'] = $_POST['ftp_password'];
				Maintenance::$context['chmod']['path'] = $_POST['ftp_path'];
			}

			if (isset(Maintenance::$context['chmod']['username'])) {
				$ftp = new FtpConnection(Maintenance::$context['chmod']['server'], Maintenance::$context['chmod']['port'], Maintenance::$context['chmod']['username'], Maintenance::$context['chmod']['password']);

				if ($ftp->error === false) {
					// Try it without /home/abc just in case they messed up.
					if (!$ftp->chdir(Maintenance::$context['chmod']['path'])) {
					Maintenance::$context['chmod']['ftp_error'] = $ftp->last_message;
						$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', Maintenance::$context['chmod']['path']));
					}
				}
			}

			if (!isset($ftp) || $ftp->error !== false) {
				if (!isset($ftp)) {
					$ftp = new FtpConnection(null);
				}
				// Save the error so we can mess with listing...
				elseif ($ftp->error !== false && !isset(Maintenance::$context['chmod']['ftp_error'])) {
				Maintenance::$context['chmod']['ftp_error'] = $ftp->last_message === null ? '' : $ftp->last_message;
				}

				list($username, $detect_path, $found_path) = $ftp->detect_path(dirname(__FILE__));

				if ($found_path || !isset(Maintenance::$context['chmod']['path'])) {
				Maintenance::$context['chmod']['path'] = $detect_path;
				}

				if (!isset(Maintenance::$context['chmod']['username'])) {
				Maintenance::$context['chmod']['username'] = $username;
				}

				// Don't forget the login token.
				Maintenance::$context += SecurityToken::create('login');

				return false;
			}

			// We want to do a relative path for FTP.
			if (!in_array(Maintenance::$context['chmod']['path'], ['', '/'])) {
				$ftp_root = strtr(Config::$boarddir, [Maintenance::$context['chmod']['path'] => '']);

				if (substr($ftp_root, -1) == '/' && (Maintenance::$context['chmod']['path'] == '' || Maintenance::$context['chmod']['path'][0] === '/')) {
					$ftp_root = substr($ftp_root, 0, -1);
				}
			} else {
				$ftp_root = Config::$boarddir;
			}

			// Save the info for next time!
			$_SESSION['temp_ftp'] = [
				'server' => Maintenance::$context['chmod']['server'],
				'port' => Maintenance::$context['chmod']['port'],
				'username' => Maintenance::$context['chmod']['username'],
				'password' => Maintenance::$context['chmod']['password'],
				'path' => Maintenance::$context['chmod']['path'],
				'root' => $ftp_root,
			];

			foreach ($files as $k => $file) {
				if (!is_writable($file)) {
					$ftp->chmod($file, 0755);
				}

				if (!is_writable($file)) {
					$ftp->chmod($file, 0777);
				}

				// Assuming that didn't work calculate the path without the boarddir.
				if (!is_writable($file)) {
					if (strpos($file, Config::$boarddir) === 0) {
						$ftp_file = strtr($file, [$_SESSION['installer_temp_ftp']['root'] => '']);
						$ftp->chmod($ftp_file, 0755);

						if (!is_writable($file)) {
							$ftp->chmod($ftp_file, 0777);
						}
						// Sometimes an extra slash can help...
						$ftp_file = '/' . $ftp_file;

						if (!is_writable($file)) {
							$ftp->chmod($ftp_file, 0755);
						}

						if (!is_writable($file)) {
							$ftp->chmod($ftp_file, 0777);
						}
					}
				}

				if (is_writable($file)) {
					unset($files[$k]);
				}
			}

			$ftp->close();
		}

		// What remains?
		Maintenance::$context['chmod']['files'] = $files;

		return (bool) (empty($files));
	}

	/**
	 * Takes a string in and cleans up issues with path entries.
	 *
	 * @param string $path Dirty path
	 * @return string Clean path
	 */
	final public function fixRelativePath(string $path): string
	{
		// Fix the . at the start, clear any duplicate slashes, and fix any trailing slash...
		return addslashes(preg_replace(['~^\.([/\\\]|$)~', '~[/]+~', '~[\\\]+~', '~[/\\\]$~'], [dirname(SMF_SETTINGS_FILE) . '$1', '/', '\\', ''], $path));
	}

	/**
	 * Detects languages installed in SMF's languages folder.
	 *
	 * @param array $key_files Language files that must exist in order to be
	 *	 considered a valid language.
	 * @return array List of valid languages in the format of $locale => $name
	 */
	final public function detectLanguages(array $key_files = ['General']): array
	{
		static $languages = [];

		if (count($languages) > 0) {
			return $languages;
		}

		if (!file_exists(Config::$languagesdir)) {
			return $languages;
		}

		$dir = dir(Config::$languagesdir);

		while ($entry = $dir->read()) {
			if (!is_dir(Config::$languagesdir . '/' . $entry)) {
				continue;
			}

			// Skip if we don't have all the key files.
			foreach ($key_files as $file) {
				if (!file_exists(Config::$languagesdir . '/' . $entry . '/' . $file . '.php')) {
					continue 2;
				}
			}

			$fp = @fopen(Config::$languagesdir . '/' . $entry . '/' . 'General.php', 'r');

			// Yay!
			if ($fp) {
				while (($line = fgets($fp)) !== false) {
					if (strpos($line, '$txt[\'native_name\']') === false) {
						continue;
					}

					preg_match('~\$txt\[\'native_name\'\]\s*=\s*\'([^\']+)\';~', $line, $matchNative);

					// Set the language's name.
					if (!empty($matchNative) && !empty($matchNative[1])) {
						// Don't mislabel the language if the translator missed this one.
						if ($entry !== 'en_US' && $matchNative[1] === 'English (US)') {
							break;
						}

						$langName = Utils::htmlspecialcharsDecode($matchNative[1]);
						break;
					}
				}

				fclose($fp);
			}

			$languages[$entry] = $langName ?? $entry;
		}

		return $languages;
	}

	/**
	 * This will check if we need to handle a timeout, if so, it sets up data for the next round.
	 *
	 * @throws \ValueError
	 * @throws \Exception
	 */
	public function checkAndHandleTimeout(): void
	{
		if (!Maintenance::isOutOfTime()) {
			return;
		}

		// If this is not json, we need to do a few things.
		if (!isset($_GET['json'])) {
			// We're going to pause after this!
			Maintenance::$context['pause'] = true;

			Maintenance::setQueryString();
		}

		Maintenance::exit();

		throw new \Exception('Zombies!');
	}
}

?>