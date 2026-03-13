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

namespace SMF\Maintenance\Tools;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Step;
use SMF\PackageManager\FtpConnection;
use SMF\Sapi;
use SMF\Security;
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
	public string $script_file;

	/**
	 * @var bool
	 *
	 * Debugging the upgrade.
	 */
	public bool $debug = false;

	/**
	 * @var string
	 *
	 * Path to a log file.
	 */
	public string $log_file;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var ?Step
	 *
	 * Which step is currently being performed.
	 *
	 * This is set by $this->setStep() and retrieved by $this->getStep().
	 */
	private ?Step $current_step;

	/**
	 * @var FtpConnection
	 *
	 * Object container for the FTP session.
	 */
	private FtpConnection $ftp;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets $this->current_step.
	 *
	 * @return ?Step The current step or null if no step is being performed.
	 */
	public function setStep(?Step $step = null): void
	{
		$this->current_step = $step;
	}

	/**
	 * Gets $this->current_step.
	 *
	 * @return ?Step The value of $this->current_step.
	 */
	public function getStep(): ?Step
	{
		return $this->current_step ?? null;
	}

	/**
	 * Updates the tool's log with new info.
	 *
	 * If using the CLI interface, the message is also printed to STDOUT.
	 *
	 * @param mixed $message The message to append to the log.
	 *    If not a string, will be converted into one using print_r().
	 * @param bool $ongoing Whether this message indicates an incomplete action.
	 *    Default: false.
	 * @param bool $reset If true, wipes out the old contents of the log file.
	 *    Default: false.
	 */
	public function logProgress(mixed $message, bool $ongoing = false, bool $reset = false): void
	{
		if (!\is_string($message)) {
			$message = print_r($message, true);
		}

		$message = preg_replace('/(<br\b[^>]*>)+|\R/', PHP_EOL, $message);

		$message .= $ongoing ? '... ' : PHP_EOL;

		if (Sapi::isCLI()) {
			echo $message;
		}

		if (!isset($this->log_file)) {
			$name = isset($this->script_file) ? pathinfo($this->script_file, PATHINFO_FILENAME) : substr($this::class, strrpos($this::class, '\\') + 1);

			foreach (
				[
					Config::$boarddir . DIRECTORY_SEPARATOR . 'logs',
					Config::$boarddir,
					Sapi::getTempDir(),
				] as $dir
			) {
				if (!file_exists($dir)) {
					Utils::makeWritable(\dirname($dir));
					@mkdir($dir, 0750);
				}

				if (is_dir($dir) && Utils::makeWritable($dir)) {
					break;
				}
			}

			$this->log_file = $dir . DIRECTORY_SEPARATOR . $name . '.log';

			// Try to make the file the writable.
			if (file_exists($this->log_file) && !is_writable($this->log_file)) {
				chmod($this->log_file, 0664);
			}
		}

		// If we fail to write, be quiet about it.
		@file_put_contents($this->log_file, $message, $reset ? 0 : FILE_APPEND);
	}

	/**
	 * Stores the log in a secure directory, or deletes it on failure.
	 *
	 * The saved log file is named after the tool plus a UTC timestamp, with a
	 * '.log' file extension.
	 *
	 * @return string Path to the saved log file, or null if log was deleted.
	 */
	public function finalizeLog(): ?string
	{
		if (!isset($this->log_file) || !file_exists($this->log_file)) {
			return null;
		}

		$dir = Config::$boarddir . DIRECTORY_SEPARATOR . 'logs';

		$new_name = $dir . DIRECTORY_SEPARATOR . pathinfo($this->log_file, PATHINFO_FILENAME) . '_' . date_create('now UTC')->format('YmdHis') . '.log';

		if (!file_exists($dir)) {
			Utils::makeWritable(Config::$boarddir);
			@mkdir($dir, 0750);
		}

		if (
			!is_dir($dir)
			|| !Utils::makeWritable($dir)
			|| Security::secureDirectory($dir) !== true
			|| !@rename($this->log_file, $new_name)
		) {
			@unlink($this->log_file);

			return null;
		}

		return $new_name;
	}

	/**
	 * Find all databases that are supported on this system.
	 *
	 * @return array An array of supported databases in the format of
	 *    $db_key => (Object for DatabaseApi) $db
	 */
	public function supportedDatabases(): array
	{
		static $dbs = [];

		if (\count($dbs) > 0) {
			return $dbs;
		}

		if (!file_exists(Config::$sourcedir . '/Db/APIs')) {
			return $dbs;
		}

		$dir = dir(Config::$sourcedir . '/Db/APIs');

		while ($entry = $dir->read()) {
			if ($entry == 'index.php' || substr($entry, -4) !== '.php') {
				continue;
			}

			$db_class = '\\SMF\\Db\\APIs\\' . substr($entry, 0, -4);
			$db = new $db_class();

			if (!($db instanceof Db) || !$db->isSupported()) {
				continue;
			}

			$dbs[$db->title] = $db;
		}

		ksort($dbs);

		return $dbs;
	}

	/**
	 * Last chance to do anything before we exit.
	 *
	 * Some tools may call this to save their progress, etc.
	 */
	public function preExit(): void {}

	/**
	 * Given a database type, loads the maintenance database object.
	 *
	 * @param string $db_type The database type, typically from Config::$db_type.
	 * @return Db The database object.
	 */
	public function loadMaintenanceDatabase(string $db_type): Db
	{
		$db_class = '\\SMF\\Db\\APIs\\' . Db::getClass(Config::$db_type);

		require_once Sapi::canonicalPath(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php');

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
	 * Checks whether we can the tool's script file.
	 *
	 * @return bool
	 */
	public function canDeleteTool(): bool
	{
		return (
			!empty($this->script_file)
			&& file_exists(Config::$boarddir . '/' . $this->script_file)
			&& (
				!empty($_SESSION['ftp'])
				|| is_writable(Config::$boarddir)
				|| is_writable(Config::$boarddir . '/' . $this->script_file)
			)
		);
	}

	/**
	 * Delete the tool.
	 *
	 * This is typically called with a ?delete.
	 *
	 * No output is returned. Upon successful deletion, the browser is
	 * redirected to a blank file.
	 */
	public function deleteTool(): void
	{
		if ($this->canDeleteTool()) {
			if (!empty($_SESSION['ftp'])) {
				$ftp = new FtpConnection($_SESSION['ftp']['server'], $_SESSION['ftp']['port'], $_SESSION['ftp']['username'], $_SESSION['ftp']['password']);
				$ftp->chdir($_SESSION['ftp']['path']);
			}

			if (isset($ftp)) {
				$ftp->unlink($this->script_file);
			} else {
				@unlink(Config::$boarddir . '/' . $this->script_file);
			}

			$this->deleteOldSchemaAndMaintenanceFiles($ftp ?? null);

			if (isset($ftp)) {
				unset($_SESSION['ftp']);
				$ftp->close();
			}

			// Now just redirect to a blank.png...
			header('location: http' . (Sapi::httpsOn() ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . \dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');
		}
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

		foreach ($files as $k => $file) {
			$this->logProgress(Lang::getTxt('log_ensuring_file_writable', ['file' => $file], file: 'Maintenance'), true);

			// Some files won't exist, try to address up front
			if (!file_exists($file)) {
				if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
					@touch($file);
				} else {
					mkdir($file, recursive: true);
				}
			}

			// Folders can't be opened for write on Windows... but the index.php in them can ;)
			if (Sapi::isOS(Sapi::OS_WINDOWS) && is_dir($file)) {
				$file .= '/index.php';

				if (!file_exists($file)) {
					@touch($file);
				}
			}

			// NOW do the writable check...
			if (Utils::makeWritable($file)) {
				$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));
				unset($files[$k]);
			} else {
				$this->logProgress(Lang::getTxt('log_failed', file: 'Maintenance'));
			}
		}

		if (Sapi::isCLI()) {
			return empty($files);
		}

		// What still needs to be done?
		Maintenance::$context['chmod_files'] = $files;

		// If it's windows it's a mess...
		if (!empty($files) && Sapi::isOS(Sapi::OS_WINDOWS)) {
			Maintenance::$fatal_error = Lang::getTxt('error_windows_chmod', file: 'Maintenance') . '
				<ul class="error_content">
					<li>' . implode('</li>
					<li>', $files) . '</li>
				</ul>';

			$this->logProgress(Lang::getTxt('error_windows_chmod', file: 'Maintenance') . "\n\t" . implode("\n\t", $files));

			return false;
		}

		// We're going to have to use... FTP!
		if (!empty($files)) {
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
				elseif (
					$ftp->error !== false
					&& !isset(Maintenance::$context['chmod']['ftp_error'])
				) {
					Maintenance::$context['chmod']['ftp_error'] = $ftp->last_message === null ? '' : $ftp->last_message;
				}

				list($username, $detect_path, $found_path) = $ftp->detect_path(\dirname(__FILE__));

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
			if (!\in_array(Maintenance::$context['chmod']['path'], ['', '/'])) {
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
				$this->logProgress(Lang::getTxt('log_ensuring_file_writable_ftp', ['file' => $file], file: 'Maintenance'), true);

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
					$this->logProgress(Lang::getTxt('done', file: 'Maintenance'));
				} else {
					$this->logProgress(Lang::getTxt('failed', file: 'Maintenance'));
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
		return addslashes(preg_replace(['~^\.([/\\\]|$)~', '~[/]+~', '~[\\\]+~', '~[/\\\]$~'], [\dirname(SMF_SETTINGS_FILE) . '$1', '/', '\\', ''], $path));
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
		foreach (Lang::get(false) as $locale => $lang_info) {
			$languages[$locale] = $lang_info['name'];
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
		if (!Maintenance::isJson()) {
			// We're going to pause after this!
			Maintenance::$context['pause'] = true;

			Maintenance::setQueryString();
		}

		Maintenance::exit(Maintenance::isJson());

		throw new \Exception('Zombies!');
	}

	/**
	 * Wrapper for Config::updateSettingsFile() with special error handling.
	 *
	 * @param array $config_vars An array of one or more variables to update.
	 * @param bool|null $keep_quotes Whether to strip slashes and trim quotes
	 *     from string values. Defaults to auto-detection.
	 * @param bool $rebuild If true, attempts to rebuild with standard format.
	 *     Default false.
	 * @return bool True on success, false on failure.
	 */
	public function updateSettingsFile(array $config_vars, ?bool $keep_quotes = null, bool $rebuild = false): bool
	{
		// A whole lot of not saving anything going on.
		if ($config_vars === []) {
			return true;
		}

		if (array_keys($config_vars) !== ['maintenance_tool_progress']) {
			$this->logProgress(Lang::getTxt('log_settings_file_save', ['setting_names' => Lang::sentenceList(array_keys($config_vars))], file: 'Maintenance'), true);
		}

		if ($rebuild) {
			// Remove all the existing comments to make the rebuild nice and clean.
			Config::safeFileWrite(
				file: SMF_SETTINGS_FILE,
				data: Config::stripPhpComments(file_get_contents(SMF_SETTINGS_FILE)),
				mtime: time(),
			);
		}

		if (!Config::updateSettingsFile($config_vars, $keep_quotes, $rebuild)) {
			$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => Lang::getTxt('settings_error', file: 'Maintenance')], file: 'Maintenance'));

			if (Sapi::isCLI()) {
				die();
			}

			Maintenance::$fatal_error = Lang::getTxt('settings_error', file: 'Maintenance');

			return false;
		}

		if (array_keys($config_vars) !== ['maintenance_tool_progress']) {
			$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));
		}

		return true;
	}

	/**
	 * Wrapper for Config::updateSettingsFile() with special error handling.
	 *
	 * @param array $change_array An array of info about what we're changing
	 *    in 'setting' => 'value' format.
	 * @param bool $update Whether to use an UPDATE query instead of a REPLACE
	 *    query.
	 * @return bool True on success, false on failure.
	 */
	public function updateModSettings(array $change_array, bool $update = false): bool
	{
		$this->logProgress(Lang::getTxt('log_modsettings_save', ['setting_names' => Lang::sentenceList(array_keys($change_array))], file: 'Maintenance'), true);

		try {
			Config::updateModSettings($change_array, $update);
		} catch (\Exception $e) {
			$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));
		}

		$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Attempts to delete SMF\Maintenance\Migration, SMF\Maintenance\Cleanup,
	 * and SMF\Db\Schema files that will not be needed again.
	 *
	 * This should be done only when the install or upgrade process is complete.
	 */
	protected function deleteOldSchemaAndMaintenanceFiles(?FtpConnection $ftp): void
	{
		if (!isset(Config::$modSettings['smf_version'])) {
			Config::reloadModSettings();
		}

		$this_ns = preg_replace('/^(\d+)\.(\d+).*/', 'v$1_$2', Config::$modSettings['smf_version'] ?? '0.0');

		$base_dirs = [
			Config::$sourcedir . '/Maintenance/Migration',
			Config::$sourcedir . '/Maintenance/Cleanup',
			Config::$sourcedir . '/Db/Schema',
		];

		foreach ($base_dirs as $base_dir) {
			$dir_list = new \GlobIterator($base_dir . '/v*', \FilesystemIterator::NEW_CURRENT_AND_KEY);

			foreach ($dir_list as $dir) {
				// Just in case...
				if (!$dir->isDir()) {
					continue;
				}

				if ($dir->getBasename() < $this_ns) {
					Utils::makeWritable($dir->getPathname());

					$file_list = new \GlobIterator($dir->getPathname() . '/*', \FilesystemIterator::NEW_CURRENT_AND_KEY);

					foreach ($file_list as $file) {
						if (isset($ftp)) {
							$ftp->unlink(str_replace(Config::$boarddir . '/', '', $file->getPathname()));
						} else {
							Utils::makeWritable($file->getPathname());
							@unlink($file->getPathname());
						}
					}

					if (isset($ftp)) {
						$ftp->unlink(str_replace(Config::$boarddir . '/', '', $dir->getPathname()));
					} else {
						@rmdir($dir->getPathname());
					}
				}
			}
		}
	}
}
