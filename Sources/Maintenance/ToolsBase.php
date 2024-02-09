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

namespace SMF\Maintenance;

use SMF\Config;
use SMF\PackageManager\FtpConnection;
use SMF\Sapi;
use SMF\Utils;

/**
 * Base class for all our tools. Includes commonly needed logic among all tools.
 */
abstract class ToolsBase
{
	/**
	 * @var string
	 *
	 * Script name of the tool we are running.
	 */
	public string $script_name;

	/**
	 * @var FtpConnection
	 *
	 * Object container for the FTP session.
	 */
	private FtpConnection $ftp;

	/**
	 * Detects languages installed in SMF's languages folder.
	 *
	 * @param array $key_files These language files must exist in order to be considered a valid language.
	 * @return array List of language that are valid in the format of $locale => $name
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
	 * Find all databases that are supported on this system.
	 *
	 * @return array An array of supported databases in the format of $db_key => (Object for DatabaseInterface) $db
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

			/** @var \SMF\Maintenance\DatabaseInterface $db_class */
			$db_class = '\\SMF\\Maintenance\\Database\\' . substr($entry, 0, -4);

			require_once Config::$sourcedir . '/Maintenance/Database/' . $entry;
			$db = new $db_class();

			if (!$db->isSupported()) {
				continue;
			}

			$dbs[substr($entry, 0, -4)] = $db;
		}

		return $dbs;
	}

	/**
	 * Delete the tool.  This is typically called with a ?delete.
	 * No output is returned.  Upon successful deletion, the browser is redirected to a blank file.
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
}

?>