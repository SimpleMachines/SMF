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

use ArrayIterator;
use Exception;
use SMF\Config;
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Logging;
use SMF\Maintenance;
use SMF\Maintenance\DatabaseInterface;
use SMF\Maintenance\Step;
use SMF\Maintenance\Template;
use SMF\Maintenance\ToolsBase;
use SMF\Maintenance\ToolsInterface;
use SMF\PackageManager\FtpConnection;
use SMF\Sapi;
use SMF\Security;
use SMF\TaskRunner;
use SMF\Time;
use SMF\Url;
use SMF\User;
use SMF\Utils;

/**
 * Installer tool.
 */
class Install extends ToolsBase implements ToolsInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * When true, we can continue, when false the continue button is removed.
	 */
	public bool $continue = true;

	/**
	 * @var bool
	 *
	 * When true, we can skip this step, otherwise false and no skip option.
	 */
	public bool $skip = false;

	/**
	 * @var string
	 *
	 * The name of the script this tool uses. This is used by various actions and links.
	 */
	public string $script_name = 'install.php';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var null|string
	 *
	 * Custom page title, otherwise we send the defaults.
	 */
	private ?string $page_title = null;

	/**
	 * @var string
	 *
	 * SMF Schema we have selected for this tool.
	 */
	private string $schema_version = 'v3_0';

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		Maintenance::$languages = $this->detectLanguages(['General', 'Maintenance']);

		if (empty(Maintenance::$languages)) {
			if (!Sapi::isCLI()) {
				Template::missingLanguages();
			}

			throw new Exception('This script was unable to find this tools\'s language file or files.');
		} else {
			$requested_lang = Maintenance::getRequestedLanguage();

			// Ensure SMF\Lang knows the path to the language directory.
			Lang::addDirs(Config::$languagesdir);

			// And now load the language file.
			Lang::load('Maintenance', $requested_lang);

			// Assume that the admin likes that language.
			if ($requested_lang !== 'en_US') {
				Config::$language = $requested_lang;
			}
		}
	}

	/**
	 * Gets our page title to be sent to the template.
	 * Selection is in the following order:
	 *  1. A custom page title.
	 *  2. Step has provided a title.
	 *  3. Default for the installer tool.
	 *
	 * @return string Page Title
	 */
	public function getPageTitle(): string
	{
		return $this->page_title ?? $this->getSteps()[Maintenance::getCurrentStep()]->getTitle() ?? Lang::$txt['smf_installer'];
	}

	/**
	 * If a tool does not contain steps, this should be false, true otherwise.
	 *
	 * @return bool Whether or not a tool has steps.
	 */
	public function hasSteps(): bool
	{
		return true;
	}

	/**
	 * Installer Steps
	 *
	 * @return \SMF\Maintenance\Step[]
	 */
	public function getSteps(): array
	{
		return [
			0 => new Step(
				id: 1,
				name: Lang::$txt['install_step_welcome'],
				title: Lang::$txt['install_welcome'],
				function: 'Welcome',
				progress: 0,
			),
			1 => new Step(
				id: 2,
				name: Lang::$txt['install_step_writable'],
				function: 'CheckFilesWritable',
				progress: 10,
			),
			2 => new Step(
				id: 3,
				name: Lang::$txt['install_step_databaseset'],
				title: Lang::$txt['db_settings'],
				function: 'DatabaseSettings',
				progress: 15,
			),
			3 => new Step(
				id: 4,
				name: Lang::$txt['install_step_forum'],
				title: Lang::$txt['install_settings'],
				function: 'ForumSettings',
				progress: 40,
			),
			4 => new Step(
				id: 5,
				name: Lang::$txt['install_step_databasechange'],
				title: Lang::$txt['db_populate'],
				function: 'DatabasePopulation',
				progress: 15,
			),
			5 => new Step(
				id: 6,
				name: Lang::$txt['install_step_admin'],
				title: Lang::$txt['user_settings'],
				function: 'AdminAccount',
				progress: 20,
			),
			6 => new Step(
				id: 7,
				name: Lang::$txt['install_step_delete'],
				function: 'DeleteInstall',
				progress: 0,
			),
		];
	}

	/**
	 * Gets the title for the step we are performing
	 *
	 * @return string
	 */
	public function getStepTitle(): string
	{
		return $this->getSteps()[Maintenance::getCurrentStep()]->getName();
	}

	/**
	 * Welcome action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function Welcome(): bool
	{
		// Done the submission?
		if (isset($_POST['contbutt'])) {
			return true;
		}

		if (Maintenance::isInstalled()) {
			Maintenance::$context['warning'] = Lang::$txt['error_already_installed'];
		}

		Maintenance::$context['supported_databases'] = $this->supportedDatabases();

		// Needs to at least meet our miniumn version.
		if ((version_compare(Maintenance::getRequiredVersionForPHP(), PHP_VERSION, '>='))) {
			Maintenance::$fatal_error = Lang::$txt['error_php_too_low'];

			return false;
		}

		// Make sure we have a supported database
		if (empty(Maintenance::$context['supported_databases'])) {
			Maintenance::$fatal_error = Lang::$txt['error_db_missing'];

			return false;
		}

		// How about session support?  Some crazy sysadmin remove it?
		if (!function_exists('session_start')) {
			Maintenance::$errors[] = Lang::$txt['error_session_missing'];
		}

		// Make sure they uploaded all the files.
		if (!file_exists(Config::$boarddir . '/index.php')) {
			Maintenance::$errors[] = Lang::$txt['error_missing_files'];
		}
		// Very simple check on the session.save_path for Windows.
		// @todo Move this down later if they don't use database-driven sessions?
		elseif (@ini_get('session.save_path') == '/tmp' && Sapi::isOS(Sapi::OS_WINDOWS)) {
			Maintenance::$errors[] = Lang::$txt['error_session_save_path'];
		}

		// Mod_security blocks everything that smells funny. Let SMF handle security.
		if (!$this->checkAndTryToFixModSecurity() && !isset($_GET['overmodsecurity'])) {
			Maintenance::$fatal_error = Lang::$txt['error_mod_security'] . '<br><br><a href="' . Maintenance::getSelf() . '?overmodsecurity=true">' . Lang::$txt['error_message_click'] . '</a> ' . Lang::$txt['error_message_bad_try_again'];
		}

		// Confirm mbstring is loaded...
		if (!extension_loaded('mbstring')) {
			Maintenance::$errors[] = Lang::$txt['install_no_mbstring'];
		}

		// Confirm fileinfo is loaded...
		if (!extension_loaded('fileinfo')) {
			Maintenance::$errors[] = Lang::$txt['install_no_fileinfo'];
		}

		// Check for https stream support.
		$supported_streams = stream_get_wrappers();

		if (!in_array('https', $supported_streams)) {
			Maintenance::$warnings[] = Lang::$txt['install_no_https'];
		}

		if (empty(Maintenance::$errors)) {
			Maintenance::$context['continue'] = true;
		}

		return false;
	}

	/**
	 * Check Files Writable action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function CheckFilesWritable(): bool
	{
		$writable_files = [
			'attachments',
			'avatars',
			'custom_avatar',
			'cache',
			'Packages',
			'Smileys',
			'Themes',
			'Languages/en_US/agreement.txt',
			'Settings.php',
			'Settings_bak.php',
			'cache/db_last_error.php',
		];

		foreach ($this->detectLanguages() as $lang => $temp) {
			$extra_files[] = 'Languages/' . $lang;
		}

		// With mod_security installed, we could attempt to fix it with .htaccess.
		if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules())) {
			$writable_files[] = file_exists(Config::$boarddir . '/.htaccess') ? '.htaccess' : '.';
		}

		$failed_files = [];

		// Windows is trickier.  Let's try opening for r+...
		if (Sapi::isOS(Sapi::OS_WINDOWS)) {
			foreach ($writable_files as $file) {
				// Folders can't be opened for write... but the index.php in them can ;)
				if (is_dir(Config::$boarddir . '/' . $file)) {
					$file .= '/index.php';
				}

				// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
				@chmod(Config::$boarddir . '/' . $file, 0777);
				$fp = @fopen(Config::$boarddir . '/' . $file, 'r+');

				// Hmm, okay, try just for write in that case...
				if (!is_resource($fp)) {
					$fp = @fopen(Config::$boarddir . '/' . $file, 'w');
				}

				if (!is_resource($fp)) {
					$failed_files[] = $file;
				}

				@fclose($fp);
			}

			foreach ($extra_files as $file) {
				@chmod(Config::$boarddir . (empty($file) ? '' : '/' . $file), 0777);
			}
		} else {
			// On linux, it's easy - just use is_writable!
			foreach ($writable_files as $file) {
				// Some files won't exist, try to address up front
				if (!file_exists(Config::$boarddir . '/' . $file)) {
					@touch(Config::$boarddir . '/' . $file);
				}

				// NOW do the writable check...
				if (!is_writable(Config::$boarddir . '/' . $file)) {
					@chmod(Config::$boarddir . '/' . $file, 0755);

					// Well, 755 hopefully worked... if not, try 777.
					if (!is_writable(Config::$boarddir . '/' . $file) && !@chmod(Config::$boarddir . '/' . $file, 0777)) {
						$failed_files[] = $file;
					}
				}
			}

			foreach ($extra_files as $file) {
				@chmod(Config::$boarddir . (empty($file) ? '' : '/' . $file), 0777);
			}
		}

		$failure = count($failed_files) >= 1;

		if (!isset($_SERVER)) {
			return !$failure;
		}

		// Put the list into context.
		Maintenance::$context['failed_files'] = $failed_files;

		// It's not going to be possible to use FTP on windows to solve the problem...
		if ($failure && Sapi::isOS(Sapi::OS_WINDOWS)) {
			Maintenance::$fatal_error = Lang::$txt['error_windows_chmod'] . '
                        <ul class="error_content">
                            <li>' . implode('</li>
                            <li>', $failed_files) . '</li>
                        </ul>';

			return false;
		}

		// We're going to have to use... FTP!
		if ($failure) {
			// Load any session data we might have...
			if (!isset($_POST['ftp']['username']) && isset($_SESSION['ftp'])) {
				$_POST['ftp']['server'] = $_SESSION['ftp']['server'];
				$_POST['ftp']['port'] = $_SESSION['ftp']['port'];
				$_POST['ftp']['username'] = $_SESSION['ftp']['username'];
				$_POST['ftp']['password'] = $_SESSION['ftp']['password'];
				$_POST['ftp']['path'] = $_SESSION['ftp']['path'];
			}

			Maintenance::$context['ftp_errors'] = [];

			if (isset($_POST['ftp_username'])) {
				$ftp = new FtpConnection($_POST['ftp']['server'], $_POST['ftp']['port'], $_POST['ftp']['username'], $_POST['ftp']['password']);

				if ($ftp->error === false) {
					// Try it without /home/abc just in case they messed up.
					if (!$ftp->chdir($_POST['ftp']['path'])) {
						Maintenance::$context['ftp_errors'][] = $ftp->last_message;
						$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp']['path']));
					}
				}
			}

			if (!isset($ftp) || $ftp->error !== false) {
				if (!isset($ftp)) {
					$ftp = new FtpConnection(null);
				}
				// Save the error so we can mess with listing...
				elseif ($ftp->error !== false && empty(Maintenance::$context['ftp_errors']) && !empty($ftp->last_message)) {
					Maintenance::$context['ftp_errors'][] = $ftp->last_message;
				}

				list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$boarddir);

				if (empty($_POST['ftp']['path']) && $found_path) {
					$_POST['ftp']['path'] = $detect_path;
				}

				if (!isset($_POST['ftp']['username'])) {
					$_POST['ftp']['username'] = $username;
				}

				// Set the username etc, into context.
				Maintenance::$context['ftp'] = [
					'server' => $_POST['ftp']['server'] ?? 'localhost',
					'port' => $_POST['ftp']['port'] ?? '21',
					'username' => $_POST['ftp']['username'] ?? '',
					'path' => $_POST['ftp']['path'] ?? '/',
					'path_msg' => !empty($found_path) ? Lang::$txt['ftp_path_found_info'] : Lang::$txt['ftp_path_info'],
				];

				return false;
			}


				$_SESSION['ftp'] = [
					'server' => $_POST['ftp']['server'],
					'port' => $_POST['ftp']['port'],
					'username' => $_POST['ftp']['username'],
					'password' => $_POST['ftp']['password'],
					'path' => $_POST['ftp']['path'],
				];

				$failed_files_updated = [];

				foreach ($failed_files as $file) {
					if (!is_writable(Config::$boarddir . '/' . $file)) {
						$ftp->chmod($file, 0755);
					}

					if (!is_writable(Config::$boarddir . '/' . $file)) {
						$ftp->chmod($file, 0777);
					}

					if (!is_writable(Config::$boarddir . '/' . $file)) {
						$failed_files_updated[] = $file;
						Maintenance::$context['ftp_errors'][] = rtrim($ftp->last_message) . ' -> ' . $file . "\n";
					}
				}

				$ftp->close();

				// Are there any errors left?
				if (count($failed_files_updated) >= 1) {
					// Guess there are...
					Maintenance::$context['failed_files'] = $failed_files_updated;

					// Set the username etc, into context.
					Maintenance::$context['ftp'] = $_SESSION['ftp'] += [
						'path_msg' => Lang::$txt['ftp_path_info'],
					];

					return false;
				}

		}

		return true;
	}

	/**
	 * Database Settings action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function DatabaseSettings()
	{
		Maintenance::$context['continue'] = true;
		Maintenance::$context['databases'] = [];
		$foundOne = false;

		/** @var \SMF\Maintenance\DatabaseInterface $db */
		foreach ($this->supportedDatabases() as $key => $db) {
			// Not supported, skip.
			if (!$db->isSupported()) {
				continue;
			}

			Maintenance::$context['databases'][$key] = $db;

			// If we have not found a one, set some defaults.
			if (!$foundOne) {
				Maintenance::$context['db'] = [
					'server' => $db->getDefaultHost(),
					'user' => $db->getDefaultUser(),
					'name' => $db->getDefaultName(),
					'pass' => $db->getDefaultPassword(),
					'port' => $db->getDefaultPort(),
					'prefix' => 'smf_',
					'type' => $key,
				];
			}
		}

		if (isset($_POST['db_user'])) {
			Maintenance::$context['db']['user'] = $_POST['db_user'];
			Maintenance::$context['db']['name'] = $_POST['db_name'];
			Maintenance::$context['db']['server'] = $_POST['db_server'];
			Maintenance::$context['db']['prefix'] = $_POST['db_prefix'];

			if (!empty($_POST['db_port'])) {
				Maintenance::$context['db']['port'] = (int) $_POST['db_port'];
			}
		}

		// Are we submitting?
		if (!isset($_POST['db_type'])) {
			return false;
		}

		// What type are they trying?
		$db_type = preg_replace('~[^A-Za-z0-9]~', '', $_POST['db_type']);
		$db_prefix = $_POST['db_prefix'];

		if (!isset(Maintenance::$context['databases'][$db_type])) {
			Maintenance::$fatal_error = Lang::$txt['upgrade_unknown_error'];

			return false;
		}

		// Validate the prefix.
		/** @var \SMF\Maintenance\DatabaseInterface $db */
		$db = Maintenance::$context['databases'][$db_type];

		// Use a try/catch here, so we can send specific details about the validation error.
		try {
			if (($db->validatePrefix($db_prefix)) !== true) {
				Maintenance::$fatal_error = Lang::$txt['upgrade_unknown_error'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = $exception->getMessage();

			return false;
		}

		// Take care of these variables...
		$vars = [
			'db_type' => $db_type,
			'db_name' => $_POST['db_name'],
			'db_user' => $_POST['db_user'],
			'db_passwd' => $_POST['db_passwd'] ?? '',
			'db_server' => $_POST['db_server'],
			'db_prefix' => $db_prefix,
			// The cookiename is special; we want it to be the same if it ever needs to be reinstalled with the same info.
			'cookiename' => $this->createCookieName($_POST['db_name'], $db_prefix),
		];

		// Only set the port if we're not using the default
		if (!empty($_POST['db_port']) && $db->getDefaultPort() !== (int) $_POST['db_port']) {
			$vars['db_port'] = (int) $_POST['db_port'];
		}

		// God I hope it saved!
		try {
			if (!$this->updateSettingsFile($vars)) {
				Maintenance::$fatal_error = Lang::$txt['settings_error'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = Lang::$txt['settings_error'];

			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// Better find the database file!
		if (!file_exists(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_file', ['Db/APIs/' . Db::getClass(Config::$db_type) . '.php']);

			return false;
		}

		// We need to make some queries, that would trip up our normal security checks.
		Config::$modSettings['disableQueryCheck'] = true;

		// Attempt a connection.
		$needsDB = !empty($databases[Config::$db_type]['always_has_db']);

		Db::load(['non_fatal' => true, 'dont_select_db' => !$needsDB]);

		// Still no connection?  Big fat error message :P.
		if (!Db::$db->connection) {
			// Get error info...  Recast just in case we get false or 0...
			$error_message = Db::$db->connect_error();

			if (empty($error_message)) {
				$error_message = '';
			}
			$error_number = Db::$db->connect_errno();

			if (empty($error_number)) {
				$error_number = '';
			}
			$db_error = (!empty($error_number) ? $error_number . ': ' : '') . $error_message;

			Maintenance::$fatal_error = Lang::$txt['error_db_connect'] . '<div class="error_content"><strong>' . $db_error . '</strong></div>';

			return false;
		}

		// Do they meet the install requirements?
		// @todo Old client, new server?
		if (($db_version = $db->getServerVersion()) === false || version_compare($db->getMinimumVersion(), preg_replace('~^\D*|\-.+?$~', '', $db_version = $db->getServerVersion())) > 0) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_too_low', ['name' => $db->getTitle()]);

			return false;
		}

		// Let's try that database on for size... assuming we haven't already lost the opportunity.
		if (Db::$db->name != '' && !$needsDB) {
			Db::$db->query(
				'',
				'CREATE DATABASE IF NOT EXISTS `' . Db::$db->name . '`',
				[
					'security_override' => true,
					'db_error_skip' => true,
				],
				Db::$db->connection,
			);

			// Okay, let's try the prefix if it didn't work...
			if (!Db::$db->select(Db::$db->name, Db::$db->connection) && Db::$db->name != '') {
				Db::$db->query(
					'',
					'CREATE DATABASE IF NOT EXISTS `' . Db::$db->prefix . Db::$db->name . '`',
					[
						'security_override' => true,
						'db_error_skip' => true,
					],
					Db::$db->connection,
				);

				if (Db::$db->select(Db::$db->prefix . Db::$db->name, Db::$db->connection)) {
					Db::$db->name = Db::$db->prefix . Db::$db->name;
					$this->updateSettingsFile(['db_name' => Db::$db->name]);
				}
			}

			// Okay, now let's try to connect...
			if (!Db::$db->select(Db::$db->name, Db::$db->connection)) {
				Maintenance::$fatal_error = Lang::getTxt('error_db_database', ['db_name' => Db::$db->name]);

				return false;
			}
		}

		// Everything looks good, lets get on with it.
		return true;
	}

	/**
	 * Forum Settings action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function ForumSettings()
	{
		// Let's see if we got the database type correct.
		if (isset($_POST['db_type'], $this->supportedDatabases()[$_POST['db_type']])) {
			Config::$db_type = $_POST['db_type'];

			try {
				if (!$this->updateSettingsFile(['db_type' => Config::$db_type])) {
					Maintenance::$fatal_error = Lang::$txt['settings_error'];

					return false;
				}
			} catch (Exception $exception) {
				Maintenance::$fatal_error = Lang::$txt['settings_error'];

				return false;
			}

			Config::load();
		} else {
			// Else we'd better be able to get the connection.
			$this->loadDatabase();
		}

		$host = $this->defaultHost();
		$secure = Sapi::httpsOn();

		// Now, to put what we've learned together... and add a path.
		Maintenance::$context['detected_url'] = 'http' . ($secure ? 's' : '') . '://' . $host . substr(Maintenance::getSelf(), 0, strrpos(Maintenance::getSelf(), '/'));

		// Check if the database sessions will even work.
		Maintenance::$context['test_dbsession'] = (ini_get('session.auto_start') != 1);

		Maintenance::$context['continue'] = true;

		$db = $this->getMaintenanceDatabase(Config::$db_type);

		// We have a failure of database configuration.
		try {
			if (!$db->checkConfiguration()) {
				Maintenance::$fatal_error = Lang::$txt['upgrade_unknown_error'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = $exception->getMessage();

			return false;
		}

		// Setup the SSL checkbox...
		Maintenance::$context['ssl_chkbx_protected'] = false;
		Maintenance::$context['ssl_chkbx_checked'] = false;

		// If redirect in effect, force SSL ON.
		$url = new Url(Maintenance::$context['detected_url']);

		if ($url->redirectsToHttps()) {
			Maintenance::$context['ssl_chkbx_protected'] = true;
			Maintenance::$context['ssl_chkbx_checked'] = true;
			$_POST['force_ssl'] = true;
		}

		// If no cert, make sure SSL stays OFF.
		if (!$url->hasSSL()) {
			Maintenance::$context['ssl_chkbx_protected'] = true;
			Maintenance::$context['ssl_chkbx_checked'] = false;
		}

		// Submitting?
		if (!isset($_POST['boardurl'])) {
			return false;
		}

		// Deal with different operating systems' directory structure...
		$path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', Maintenance::getBaseDir()), '/');

		// Save these variables.
		$vars = [
			'boardurl' => $this->cleanBoardUrl($_POST['boardurl']),
			'boarddir' => $path,
			'sourcedir' => $path . '/Sources',
			'cachedir' => $path . '/cache',
			'packagesdir' => $path . '/Packages',
			'languagesdir' => $path . '/Languages',
			'mbname' => strtr($_POST['mbname'], ['\"' => '"']),
			'language' => Maintenance::getRequestedLanguage(),
			'image_proxy_secret' => $this->createImageProxySecret(),
			'image_proxy_enabled' => !empty($_POST['force_ssl']),
			'auth_secret' => $this->createAuthSecret(),
		];

		try {
			if (!$this->updateSettingsFile($vars)) {
				Maintenance::$fatal_error = Lang::$txt['settings_error'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = Lang::$txt['settings_error'];

			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// UTF-8 requires a setting to override the language charset.
		try {
			if (!$db->utf8Configured()) {
				Maintenance::$fatal_error = Lang::$txt['error_utf8_support'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = $exception->getMessage();

			return false;
		}

		// Set the character set here.
		try {
			if (!$this->updateSettingsFile(['db_character_set' => 'utf8'], true)) {
				Maintenance::$fatal_error = Lang::$txt['settings_error'];

				return false;
			}
		} catch (Exception $exception) {
			Maintenance::$fatal_error = Lang::$txt['settings_error'];

			return false;
		}

		// Good, skip on.
		return true;
	}

	/**
	 * Database Population action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function DatabasePopulation(): bool
	{
		Maintenance::$context['continue'] = true;

		// Already done?
		if (isset($_POST['pop_done'])) {
			return true;
		}

		// Reload settings.
		Config::load();
		$this->loadDatabase();
		$newSettings = [];
		$path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', Maintenance::getBaseDir()), '/');

		// Before running any of the queries, let's make sure another version isn't already installed.
		$result = Db::$db->query(
			'',
			'SELECT variable, value
            FROM {db_prefix}settings',
			[
				'db_error_skip' => true,
			],
		);

		if ($result !== false) {
			while ($row = Db::$db->fetch_assoc($result)) {
				Config::$modSettings[$row['variable']] = $row['value'];
			}

			Db::$db->free_result($result);

			// Do they match?  If so, this is just a refresh so charge on!
			if (!isset(Config::$modSettings['smfVersion']) || Config::$modSettings['smfVersion'] != SMF_VERSION) {
				Maintenance::$fatal_error = Lang::$txt['error_versions_do_not_match'];

				return false;
			}
		}
		Config::$modSettings['disableQueryCheck'] = true;

		// Windows likes to leave the trailing slash, which yields to C:\path\to\SMF\/attachments...
		if (Sapi::isOS(Sapi::OS_WINDOWS)) {
			$attachdir = $path . 'attachments';
		} else {
			$attachdir = $path . '/attachments';
		}

		$replaces = [
			'{$db_prefix}' => Db::$db->prefix,
			'{$attachdir}' => json_encode([1 => Db::$db->escape_string($attachdir)]),
			'{$boarddir}' => Db::$db->escape_string(Config::$boarddir),
			'{$boardurl}' => Config::$boardurl,
			'{$enableCompressedOutput}' => isset($_POST['compress']) ? '1' : '0',
			'{$databaseSession_enable}' => isset($_POST['dbsession']) ? '1' : '0',
			'{$smf_version}' => SMF_VERSION,
			'{$current_time}' => time(),
			'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
			'{$registration_method}' => $_POST['reg_mode'] ?? 0,
		];

		foreach (Lang::$txt as $key => $value) {
			if (substr($key, 0, 8) == 'default_') {
				$replaces['{$' . $key . '}'] = Db::$db->escape_string($value);
			}
		}
		$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], ['\\\\n' => '\\n']);

		$existing_tables = Db::$db->list_tables(Config::$db_name, Config::$db_prefix);
		$install_tables = $this->getTables(Config::$sourcedir . '/Db/Schema/', $this->schema_version);
		Maintenance::$context['sql_results'] = [
			'tables' => 0,
			'inserts' => 0,
			'table_dups' => 0,
			'insert_dups' => 0,
		];

		// $tables->seek(Maintenance::getCurrentSubStep());
		foreach ($install_tables as $tbl) {
			if (in_array(Config::$db_prefix . $tbl->name, $existing_tables)) {
				continue;
			}

			$original_table = $tbl->name;
			$tbl->name = Config::$db_prefix . $tbl->name;

			try {
				$result = $tbl->create();

				if ($result) {
					Maintenance::$context['sql_results']['tables']++;
				} else {
					Maintenance::$context['failures'][] = trim(Db::$db->error(Db::$db->connection));
				}
			} catch (Exception $exception) {
				Maintenance::$context['failures'][] = trim($exception->getMessage());
			}

			try {
				if (!empty($tbl->initial_data)) {
					foreach ($tbl->initial_data as &$col) {
						foreach ($col as $key => &$value) {
							if (is_string($value)) {
								$value = strtr($value, $replaces);
							} elseif (isset($tbl->initial_columns[$key])) {
								switch ($tbl->initial_columns[$key]) {
									case 'int':
										$value = (int) $value;
										break;

									default:
										$value = (string) $value;
								}
							}
						}
					}

					$result = Db::$db->insert(
						'replace',
						$tbl->name,
						$tbl->initial_columns,
						$tbl->initial_data,
						array_keys($tbl->initial_columns),
					);

					if ($result || $result === null) {
						Maintenance::$context['sql_results']['tables']++;
					} else {
						Maintenance::$context['failures'][] = trim(Db::$db->error(Db::$db->connection));
					}
				}
			} catch (Exception $exception) {
				Maintenance::$context['failures'][] = trim($exception->getMessage());
			}

			// Wait, wait, I'm still working here!
			Sapi::setTimeLimit(60);
		}

		// Sort out the context for the SQL.
		foreach (Maintenance::$context['sql_results'] as $key => $number) {
			if ($number === 0) {
				unset(Maintenance::$context['sql_results'][$key]);
			} else {
				Maintenance::$context['sql_results'][$key] = Lang::getTxt('db_populate_' . $key, [$number]);
			}
		}

		// Make sure UTF will be used globally.
		$newSettings['global_character_set'] = 'UTF-8';

		$this->togglleSmStats($newSettings);

		// Are we enabling SSL?
		if (!empty($_POST['force_ssl'])) {
			$newSettings['force_ssl'] = 1;
		}

		// Setting a timezone is required.
		$newSettings['default_timezone'] = $this->determineTimezone() ?? 'UTC';

		if (!empty($newSettings)) {
			Config::updateModSettings($newSettings);
		}

		// Setup Smieys.
		$this->populateSmileys();

		// Let's optimize those new tables, but not on InnoDB, ok? (SMF will check this)
		$install_tables->rewind();

		foreach ($install_tables as $tbl) {
			$tbl->name = Config::$db_prefix . $tbl->name;

			try {
				if (!(Db::$db->optimize_table($tbl->name) > -1)) {
					Maintenance::$context['failures'][] = Db::$db->error(Db::$db->connection);
				}
			} catch (Exception $exception) {
				Maintenance::$context['failures'][] = $exception->getMessage();
			}
		}

		// Find out if we have permissions we didn't use, but will need for the future.
		// @@ TODO: This was at this location in the original code, it should come earlier.
		$db = $this->getMaintenanceDatabase(Config::$db_type);

		if (!$db->hasPermissions()) {
			Maintenance::$fatal_error = Lang::$txt['error_db_alter_priv'];
		}

		// Was this a refresh?
		if (count($existing_tables) > 0) {
			$this->page_title = Lang::$txt['user_refresh_install'];
			Maintenance::$context['was_refresh'] = true;
		}

		return false;
	}

	/**
	 * Admin Account action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function AdminAccount(): bool
	{
		Maintenance::$context['continue'] = true;

		// Skipping?
		if (!empty($_POST['skip'])) {
			return true;
		}

		// Need this to check whether we need the database password.
		Config::load();
		$this->loadDatabase();

		$settingsDefs = Config::getSettingsDefs();

		// Reload $modSettings.
		Config::reloadModSettings();

		Maintenance::$context['username'] = htmlspecialchars($_POST['username'] ?? '');
		Maintenance::$context['email'] = htmlspecialchars($_POST['email'] ?? '');
		Maintenance::$context['server_email'] = htmlspecialchars($_POST['server_email'] ?? '');

		Maintenance::$context['require_db_confirm'] = empty(Config::$db_type);

		// Only allow skipping if we think they already have an account setup.
		$request = Db::$db->query(
			'',
			'SELECT id_member
            FROM {db_prefix}members
            WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
            LIMIT 1',
			[
				'db_error_skip' => true,
				'admin_group' => 1,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			Maintenance::$context['skip'] = true;

			return false;
		}
		Db::$db->free_result($request);

		// Trying to create an account?
		if (!isset($_POST['password1'])  || empty($_POST['contbutt'])) {
			return false;
		}

		$_POST['username'] ??= '';
		$_POST['email'] ??= '';
		$_POST['password2'] ??= '';
		$_POST['password3'] ??= '';

		// Wrong password?
		if (Maintenance::$context['require_db_confirm'] && $_POST['password3'] != Config::$db_passwd) {
			Maintenance::$fatal_error = Lang::$txt['error_db_connect'];

			return false;
		}

		// Not matching passwords?
		if ($_POST['password1'] != $_POST['password2']) {
			Maintenance::$fatal_error = Lang::$txt['error_user_settings_again_match'];

			return false;
		}

		// No password?
		if (strlen($_POST['password1']) < 4) {
			Maintenance::$fatal_error = Lang::$txt['error_user_settings_no_password'];

			return false;
		}

		if (!file_exists(Config::$sourcedir . '/Utils.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_sourcefile_missing', ['file' => 'Utils.php']);

			return false;
		}

		// Update the webmaster's email?
		if (!empty($_POST['server_email']) && (empty(Config::$webmaster_email) || Config::$webmaster_email == $settingsDefs['webmaster_email']['default'])) {
			$this->updateSettingsFile(['webmaster_email' => (string) $_POST['server_email']]);
		}

		// Work out whether we're going to have dodgy characters and remove them.
		$invalid_characters = preg_match('~[<>&"\'=\\\]~', $_POST['username']) != 0;
		$_POST['username'] = preg_replace('~[<>&"\'=\\\]~', '', $_POST['username']);

		$result = Db::$db->query(
			'',
			'SELECT id_member, password_salt
			FROM {db_prefix}members
			WHERE member_name = {string:username} OR email_address = {string:email}
			LIMIT 1',
			[
				'username' => $_POST['username'],
				'email' => $_POST['email'],
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			list(Maintenance::$context['member_id'], Maintenance::$context['member_salt']) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			Maintenance::$context['account_existed'] = Lang::$txt['error_user_settings_taken'];
		} elseif ($_POST['username'] == '' || strlen($_POST['username']) > 25) {
			// Try the previous step again.
			Maintenance::$fatal_error = $_POST['username'] == '' ? Lang::$txt['error_username_left_empty'] : Lang::$txt['error_username_too_long'];

			return false;
		} elseif ($invalid_characters || $_POST['username'] == '_' || $_POST['username'] == '|' || strpos($_POST['username'], '[code') !== false || strpos($_POST['username'], '[/code') !== false) {
			// Try the previous step again.
			Maintenance::$fatal_error = Lang::$txt['error_invalid_characters_username'];

			return false;
		} elseif (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) || strlen($_POST['email']) > 255) {
			// One step back, this time fill out a proper admin email address.
			Maintenance::$fatal_error = Lang::$txt['error_valid_admin_email_needed'];

			return false;
		} elseif (empty($_POST['server_email']) || !filter_var($_POST['server_email'], FILTER_VALIDATE_EMAIL) || strlen($_POST['server_email']) > 255) {
			// One step back, this time fill out a proper admin email address.
			Maintenance::$fatal_error = Lang::$txt['error_valid_server_email_needed'];

			return false;
		} elseif ($_POST['username'] != '') {
			Maintenance::$context['member_salt'] = bin2hex(random_bytes(16));

			// Format the username properly.
			$_POST['username'] = preg_replace('~[\t\n\r\x0B\0\xA0]+~', ' ', $_POST['username']);
			$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 255) : '';

			$_POST['password1'] = Security::hashPassword($_POST['username'], $_POST['password1']);

			try {
				Maintenance::$context['member_id'] = Db::$db->insert(
					'',
					Db::$db->prefix . 'members',
					[
						'member_name' => 'string-25',
						'real_name' => 'string-25',
						'passwd' => 'string',
						'email_address' => 'string',
						'id_group' => 'int',
						'posts' => 'int',
						'date_registered' => 'int',
						'password_salt' => 'string',
						'lngfile' => 'string',
						'personal_text' => 'string',
						'avatar' => 'string',
						'member_ip' => 'inet',
						'member_ip2' => 'inet',
						'buddy_list' => 'string',
						'pm_ignore_list' => 'string',
						'website_title' => 'string',
						'website_url' => 'string',
						'signature' => 'string',
						'usertitle' => 'string',
						'secret_question' => 'string',
						'additional_groups' => 'string',
						'ignore_boards' => 'string',
					],
					[
						$_POST['username'],
						$_POST['username'],
						$_POST['password1'],
						$_POST['email'],
						1,
						0,
						time(),
						Maintenance::$context['member_salt'],
						'',
						'',
						'',
						$ip,
						$ip,
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
					],
					['id_member'],
					1,
				);

				if ((int) Maintenance::$context['member_id'] > 0) {
					return true;
				}
					Maintenance::$fatal_error = trim(Db::$db->error(Db::$db->connection));

					return false;

			} catch (Exception $exception) {
				Maintenance::$fatal_error = $exception->getMessage();
				var_dump(Maintenance::$fatal_error);
			}
		}

		return false;
	}

	/**
	 * Delete Install action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function DeleteInstall(): bool
	{
		Maintenance::$context['continue'] = false;

		Config::load();
		$this->loadDatabase();

		chdir(Config::$boarddir);

		// Reload $modSettings.
		Config::reloadModSettings();

		// Bring a warning over.
		if (!empty(Maintenance::$context['account_existed'])) {
			Maintenance::$warnings = Maintenance::$context['account_existed'];
		}

		Db::$db->query(
			'',
			'SET NAMES {string:db_character_set}',
			[
				'db_character_set' => Config::$db_character_set,
				'db_error_skip' => true,
			],
		);

		// As track stats is by default enabled let's add some activity.
		Db::$db->insert(
			'ignore',
			'{db_prefix}log_activity',
			['date' => 'date', 'topics' => 'int', 'posts' => 'int', 'registers' => 'int'],
			[Time::strftime('%Y-%m-%d', time()), 1, 1, (!empty(Maintenance::$context['member_id']) ? 1 : 0)],
			['date'],
		);

		// We're going to want our lovely Config::$modSettings now.
		$request = Db::$db->query(
			'',
			'SELECT variable, value
            FROM {db_prefix}settings',
			[
				'db_error_skip' => true,
			],
		);

		// Only proceed if we can load the data.
		if ($request) {
			while ($row = Db::$db->fetch_row($request)) {
				Config::$modSettings[$row[0]] = $row[1];
			}
			Db::$db->free_result($request);
		}


		// Automatically log them in ;)
		if (isset(Maintenance::$context['member_id'], Maintenance::$context['member_salt'])) {
			Cookie::setLoginCookie(3153600 * 60, Maintenance::$context['member_id'], Cookie::encrypt($_POST['password1'], Maintenance::$context['member_salt']));
		}

		$result = Db::$db->query(
			'',
			'SELECT value
            FROM {db_prefix}settings
            WHERE variable = {string:db_sessions}',
			[
				'db_sessions' => 'databaseSession_enable',
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			list($db_sessions) = Db::$db->fetch_row($result);
		}
		Db::$db->free_result($result);

		if (empty($db_sessions)) {
			$_SESSION['admin_time'] = time();
		} else {
			$_SERVER['HTTP_USER_AGENT'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 211);

			Db::$db->insert(
				'replace',
				'{db_prefix}sessions',
				[
					'session_id' => 'string', 'last_update' => 'int', 'data' => 'string',
				],
				[
					session_id(), time(), 'USER_AGENT|s:' . strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
				],
				['session_id'],
			);
		}


		Logging::updateStats('member');
		Logging::updateStats('message');
		Logging::updateStats('topic');

		$request = Db::$db->query(
			'',
			'SELECT id_msg
            FROM {db_prefix}messages
            WHERE id_msg = 1
                AND modified_time = 0
            LIMIT 1',
			[
				'db_error_skip' => true,
			],
		);
		Utils::$context['utf8'] = true;

		if (Db::$db->num_rows($request) > 0) {
			Logging::updateStats('subject', 1, htmlspecialchars(Lang::$txt['default_topic_subject']));
		}
		Db::$db->free_result($request);

		// Now is the perfect time to fetch the SM files.
		// Sanity check that they loaded earlier!
		if (isset(Config::$modSettings['recycle_board'])) {
			(new TaskRunner())->runScheduledTasks(['fetchSMfiles']); // Now go get those files!

			// We've just installed!
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];

			if (isset(Maintenance::$context['member_id'])) {
				User::setMe(Maintenance::$context['member_id']);
			} else {
				User::load();
			}

			User::$me->ip = $_SERVER['REMOTE_ADDR'];

			Logging::logAction('install', ['version' => SMF_FULL_VERSION], 'admin');
		}

		// Disable the legacy BBC by default for new installs
		Config::updateModSettings([
			'disabledBBC' => implode(',', Utils::$context['legacy_bbc']),
		]);

		// Some final context for the template.
		Maintenance::$context['dir_still_writable'] = is_writable(Config::$boarddir) && substr(__FILE__, 1, 2) != ':\\';
		Maintenance::$context['probably_delete_install'] = isset($_SESSION['installer_temp_ftp']) || is_writable(Config::$boarddir) || is_writable(__FILE__);

		// Update hash's cost to an appropriate setting
		Config::updateModSettings([
			'bcrypt_hash_cost' => Security::hashBenchmark(),
		]);

		return false;
	}

	/**
	 * Create an .htaccess file to prevent mod_security. SMF has filtering built-in.
	 *
	 * @return bool True if we could create the file or do not need to.  False if this failed.
	 */
	private function checkAndTryToFixModSecurity(): bool
	{
		$htaccess_addition = '
    <IfModule mod_security.c>
        # Turn off mod_security filtering.  SMF is a big boy, it doesn\'t need its hands held.
        SecFilterEngine Off

        # The below probably isn\'t needed, but better safe than sorry.
        SecFilterScanPOST Off
    </IfModule>';

		if (!function_exists('apache_get_modules') || !in_array('mod_security', apache_get_modules())) {
			return true;
		}

		if (file_exists(Config::$boarddir . '/.htaccess') && is_writable(Config::$boarddir . '/.htaccess')) {
			$current_htaccess = implode('', file(Config::$boarddir . '/.htaccess'));

			// Only change something if mod_security hasn't been addressed yet.
			if (strpos($current_htaccess, '<IfModule mod_security.c>') === false) {
				if ($ht_handle = fopen(Config::$boarddir . '/.htaccess', 'a')) {
					fwrite($ht_handle, $htaccess_addition);
					fclose($ht_handle);

					return true;
				}

					return false;
			}

				return true;
		}

		if (file_exists(Config::$boarddir . '/.htaccess')) {
			return strpos(implode('', file(Config::$boarddir . '/.htaccess')), '<IfModule mod_security.c>') !== false;
		}

		if (is_writable(Config::$boarddir)) {
			if ($ht_handle = fopen(Config::$boarddir . '/.htaccess', 'w')) {
				fwrite($ht_handle, $htaccess_addition);
				fclose($ht_handle);

				return true;
			}

				return false;
		}

			return false;
	}

	/**
	 * Creates a unique cookie name based on some inputs.
	 *
	 * @param string $db_name The database named provided by Config::$db_name.
	 * @param string $db_prefix The database prefix provided by Config::$db_prefix.
	 * @return string The cookie name.
	 */
	private function createCookieName(string $db_name, string $db_prefix): string
	{
		return 'SMFCookie' . abs(crc32($db_name . preg_replace('~[^A-Za-z0-9_$]~', '', $db_prefix)) % 1000);
	}

	/**
	 * Generates a Config::$auth_secret string.
	 *
	 * @return string a cryptographic string.
	 */
	private function createAuthSecret(): string
	{
		return bin2hex(random_bytes(32));
	}

	/**
	 * Generates a Config::$image_proxy_secret string.
	 *
	 * @return string a cryptographic string.
	 */
	private function createImageProxySecret(): string
	{
		return bin2hex(random_bytes(10));
	}

	/**
	 * Wrapper for SMF's Config::updateSettingsFile.
	 * SMF may not be ready for us to write yet or the config file may not be writable. Make it safe.
	 *
	 * @param array $vars An Key/Value array of all the settings we will update.
	 * @param bool $rebuild When true, we will force the settings file tor rebuild.
	 * @return bool True if we could update the settings file, false otherwise.
	 */
	private function updateSettingsFile(array $vars, bool $rebuild = false): bool
	{
		if (!is_writable(SMF_SETTINGS_FILE)) {
			@chmod(SMF_SETTINGS_FILE, 0777);

			if (!is_writable(SMF_SETTINGS_FILE)) {
				return false;
			}
		}

		return Config::updateSettingsFile($vars, false, $rebuild);
	}

	/**
	 * Wrapper for loading the database.
	 * If the database has already been loaded, we don't try again.
	 *
	 * @throws Exception
	 */
	private function loadDatabase(): void
	{
		// Connect the database.
		if (empty(Db::$db->connection)) {
			Db::load();
		}
	}

	/**
	 * Given a database type, loads the maintenance database object.
	 *
	 * @param string $db_type The database type, typically from Config::$db_type.
	 * @return DatabaseInterface The database object.
	 */
	private function getMaintenanceDatabase(string $db_type): DatabaseInterface
	{
		/** @var \SMF\Maintenance\DatabaseInterface $db_class */
		$db_class = '\\SMF\\Maintenance\\Database\\' . $db_type;

		require_once Config::$sourcedir . '/Maintenance/Database/' . $db_type . '.php';

		return new $db_class();
	}

	/**
	 * Determine the default host, used during install to populate Config::$boardurl.
	 *
	 * @return string The host we have determined to be on.
	 */
	private function defaultHost(): string
	{
		return empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
	}

	/**
	 * Given a board url, this will clean up some mistakes and other errors.
	 *
	 * @param string $boardurl Input boardurl
	 * @return string Returned board url.
	 */
	private function cleanBoardUrl(string $boardurl): string
	{
		if (substr($boardurl, -10) == '/index.php') {
			$boardurl = substr($boardurl, 0, -10);
		} elseif (substr($boardurl, -1) == '/') {
			$boardurl = substr($boardurl, 0, -1);
		}

		if (substr($boardurl, 0, 7) != 'http://' && substr($boardurl, 0, 7) != 'file://' && substr($boardurl, 0, 8) != 'https://') {
			$boardurl = 'http://' . $boardurl;
		}

		// Make sure boardurl is aligned with ssl setting
		if (empty($_POST['force_ssl'])) {
			$boardurl = strtr($boardurl, ['https://' => 'http://']);
		} else {
			$boardurl = strtr($boardurl, ['http://' => 'https://']);
		}

		// Make sure international domain names are normalized correctly.
		if (Lang::$txt['lang_character_set'] == 'UTF-8') {
			$boardurl = (string) new Url($boardurl, true);
		}

		return $boardurl;
	}

	/**
	 * Fetch al the tables for our schema.
	 *
	 * @param string $base_directory Root directory for all of our schemas.
	 * @param string $schema_version Schema we are loading.
	 * @return \ArrayIterator|\SMF\Db\Schema\Table[]
	 */
	private function getTables(string $base_directory, string $schema_version): ArrayIterator
	{
		$files = [];

		foreach (new \DirectoryIterator($base_directory . '/' . $schema_version) as $fileInfo) {
			if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getExtension() !== 'php' || $fileInfo->getFilename() == 'index.php') {
				continue;
			}
			$tbl = $fileInfo->getBasename('.' . $fileInfo->getExtension());

			/** @var \SMF\Db\Schema\Table $tbl_class */
			$tbl_class = '\\SMF\\Db\\Schema\\' . $schema_version . '\\' . $tbl;

			require_once Config::$sourcedir . '/Db/Schema/' . $schema_version . '/' . $fileInfo->getFilename();
			$files[$tbl] = new $tbl_class();
		}

		ksort($files);

		return new ArrayIterator($files);
	}

	/**
	 * Determine if we need to enable or disable (during upgrades) SMF stat collection.
	 *
	 * @param array $settings Settings array, passed by reference.
	 */
	private function togglleSmStats(array &$settings): void
	{
		if (!empty($_POST['stats']) && substr(Config::$boardurl, 0, 16) != 'http://localhost' && empty(Config::$modSettings['allow_sm_stats']) && empty(Config::$modSettings['enable_sm_stats'])) {
			Maintenance::$context['allow_sm_stats'] = true;

			// Attempt to register the site etc.
			$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);

			if (!$fp) {
				$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
			}

			if (!$fp) {
				return;
			}

			$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode(Config::$boardurl) . ' HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'Connection: Close' . "\r\n\r\n";
			fwrite($fp, $out);

			$return_data = '';

			while (!feof($fp)) {
				$return_data .= fgets($fp, 128);
			}

			fclose($fp);

			// Get the unique site ID.
			preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

			if (!empty($ID[1])) {
				$settings['sm_stats_key'] = $ID[1];
				$settings['enable_sm_stats'] = 1;
			}
		}
		// Don't remove stat collection unless we unchecked the box for real, not from the loop.
		elseif (empty($_POST['stats']) && empty(Maintenance::$context['allow_sm_stats'])) {
			$settings[] = ['enable_sm_stats', null];
		}
	}

	/**
	 * Attempt to determine what our time zone is.  If this can't be determined we return nothing.
	 *
	 * @return null|string A valid time zone or nothing.
	 */
	private function determineTimezone(): ?string
	{
		if (isset(Config::$modSettings['default_timezone']) || !function_exists('date_default_timezone_set')) {
			return null;
		}

		// Get PHP's default timezone, if set
		$ini_tz = ini_get('date.timezone');

		if (!empty($ini_tz)) {
			$timezone_id = $ini_tz;
		} else {
			$timezone_id = '';
		}

		// If date.timezone is unset, invalid, or just plain weird, make a best guess
		if (!in_array($timezone_id, timezone_identifiers_list())) {
			$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
			$timezone_id = timezone_name_from_abbr('', $server_offset, 0);

			if (empty($timezone_id)) {
				$timezone_id = 'UTC';
			}
		}

		if (date_default_timezone_set($timezone_id)) {
			return $timezone_id;
		}

		return null;
	}

	/**
	 * Populating smileys are a bit complicated, so its performed here rather than inline.
	 *
	 */
	private function populateSmileys(): void
	{
		// Populate the smiley_files table.
		// Can't just dump this data in the SQL file because we need to know the id for each smiley.
		$smiley_filenames = [
			':)' => 'smiley',
			';)' => 'wink',
			':D' => 'cheesy',
			';D' => 'grin',
			'>:(' => 'angry',
			':(' => 'sad',
			':o' => 'shocked',
			'8)' => 'cool',
			'???' => 'huh',
			'::)' => 'rolleyes',
			':P' => 'tongue',
			':-[' => 'embarrassed',
			':-X' => 'lipsrsealed',
			':-\\' => 'undecided',
			':-*' => 'kiss',
			':\'(' => 'cry',
			'>:D' => 'evil',
			'^-^' => 'azn',
			'O0' => 'afro',
			':))' => 'laugh',
			'C:-)' => 'police',
			'O:-)' => 'angel',
		];
		$smiley_set_extensions = ['fugue' => '.png', 'alienine' => '.png'];

		$smiley_inserts = [];
		$request = Db::$db->query(
			'',
			'SELECT id_smiley, code
            FROM {db_prefix}smileys',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			foreach ($smiley_set_extensions as $set => $ext) {
				$smiley_inserts[] = [$row['id_smiley'], $set, $smiley_filenames[$row['code']] . $ext];
			}
		}
		Db::$db->free_result($request);

		Db::$db->insert(
			'ignore',
			'{db_prefix}smiley_files',
			['id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48'],
			$smiley_inserts,
			['id_smiley', 'smiley_set'],
		);
	}
}

?>