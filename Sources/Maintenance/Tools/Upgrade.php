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

use Exception;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Step;
use SMF\Maintenance\Template;
use SMF\Maintenance\ToolsBase;
use SMF\Maintenance\ToolsInterface;
use SMF\QueryString;
use SMF\Sapi;
use SMF\SecurityToken;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Upgrade tool.
 */
class Upgrade extends ToolsBase implements ToolsInterface
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
	public string $script_name = 'upgrade.php';

	/**
	 * @var int
	 *
	 * The time we last updated the upgrade, populated by upgrade itself.
	 */
	public int $time_updated = 0;

	/**
	 * @var bool
	 *
	 * Debugging the upgrade.
	 */
	public bool $debug = false;

	/**
	 * @var array
	 *
	 * User performing upgrade.
	 */
	public array $user = [
		'id' => 0,
		'name' => 'Guest',
		'step' => 0,
	];

	/**
	 * @var array
	 *
	 * Migrations we skipped.
	 */
	public array $skipped_migrations = [];

	/**
	 * @var int
	 *
	 * The amount of seconds allowed between logins.
	 * If the first user to login is inactive for this amount of seconds, a second login is allowed.
	 */
	public int $inactive_timeout = 10;

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
	 * Additional safety measures for timeout protection are done for large forums.
	 *
	 * @var bool
	 */
	private bool $is_large_forum = false;

	/**
	 * @var int
	 *
	 * The maximum time a single migration may take, in seconds.
	 */
	protected int $migration_time_limit = 3;

	/**
	 * @var array
	 *
	 * Upgrade data stored in our Settings.php as we progress through the upgrade.
	 */
	protected array $upgradeData = [];

	/**
	 * @var int
	 *
	 * The time we started the upgrade, populated by upgrade itself.
	 */
	protected int $time_started = 0;

	/**
	 * English is the default language.
	 *
	 * @var string
	 */
	protected string $default_language = 'en_US';

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
			 Lang::load('General+Maintenance', $requested_lang);

			 // Assume that the admin likes that language.
			 if ($requested_lang !== $this->default_language) {
				 Config::$language = $requested_lang;
			 }
		 }

		// Secure some resources.
		try {
			if (Config::$db_type == MYSQL_TITLE) {
				@ini_set('mysql.connect_timeout', '-1');
			}
			@ini_set('default_socket_timeout', '900');
			Sapi::setTimeLimit(600);
			Sapi::setMemoryLimit('512M');

			// Better to upgrade cleanly and fall apart than to screw everything up if things take too long.
			ignore_user_abort(true);
		} catch (Exception $e) {
		}

		// SMF\Config, and SMF\Utils.
		Config::load();
		Utils::load();

		$this->prepareUpgrade();

		try {
			Maintenance::loadDatabase();
		} catch (Exception $exception) {
			die(Lang::getTxt('error_sourcefile_missing', ['file' => 'Db/APIs/' . Db::getClass(Config::$db_type) . '.php']));
		}

		// If they don't have the file, they're going to get a warning anyway so we won't need to clean request vars.
		if (class_exists('SMF\\QueryString')) {
			QueryString::cleanRequest();
		}

		// Is this a large forum? We may do special logic then.
		Maintenance::$context['is_large_forum'] = $this->is_large_forum = (empty(Config::$modSettings['smfVersion']) || Config::$modSettings['smfVersion'] <= '1.1 RC1') && !empty(Config::$modSettings['totalMessages']) && Config::$modSettings['totalMessages'] > 75000;

		// Should we check that they are logged in?
		if (Maintenance::getCurrentSubStep() > 0 && !isset($_SESSION['is_logged'])) {
			Maintenance::setCurrentSubStep(0);
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
		return $this->page_title ?? $this->getSteps()[Maintenance::getCurrentStep()]->getTitle() ?? Lang::$txt['smf_upgrade'];
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
	 * Upgrade Steps
	 *
	 * @return \SMF\Maintenance\Step[]
	 */
	public function getSteps(): array
	{
		return [
			0 => new Step(
				id: 1,
				name: Lang::$txt['upgrade_step_login'],
				function: 'welcomeLogin',
				progress: 2,
			),
			1 => new Step(
				id: 2,
				name: Lang::$txt['upgrade_step_options'],
				function: 'upgradeOptions',
				progress: 3,
			),
			2 => new Step(
				id: 3,
				name: Lang::$txt['upgrade_step_backup'],
				function: 'backupDatabase',
				progress: 15,
			),
			3 => new Step(
				id: 4,
				name: Lang::$txt['upgrade_step_migration'],
				function: 'migrations',
				progress: 50,
			),
			4 => new Step(
				id: 5,
				name: Lang::$txt['upgrade_step_cleanup'],
				function: 'cleanup',
				progress: 30,
			),
			5 => new Step(
				id: 6,
				name: Lang::$txt['upgrade_step_delete'],
				function: 'deleteUpgrade',
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
	public function welcomeLogin(): bool
	{
		// Needs to at least meet our minium version.
		if ((version_compare(Maintenance::getRequiredVersionForPHP(), PHP_VERSION, '>='))) {
			Maintenance::$fatal_error = Lang::$txt['error_php_too_low'];

			return false;
		}

		// Form submitted, but no javascript support.
		if (isset($_POST['contbutt']) && !isset($_POST['js_support'])) {
			Maintenance::$fatal_error = Lang::$txt['error_no_javascript'];

			return false;
		}

		// Check for some key files - one template, one language, and a new and an old source file.
		$check = @file_exists(Maintenance::$theme_dir . '/index.template.php')
			&& @file_exists(Config::$sourcedir . '/QueryString.php')
			&& @file_exists(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php')
			&& @file_exists(Config::$sourcedir . '/Maintenance/Migration/v3_0/Migration0001.php');

		// Need legacy scripts?
		if (!isset(Config::$modSettings['smfVersion']) || Config::$modSettings['smfVersion'] < 3.0) {
			$check &= @file_exists(Config::$sourcedir . '/Maintenance/Migration/v2_1/Migration0001.php');
		}

		if (!$check) {
			// Don't tell them what files exactly because it's a spot check - just like teachers don't tell which problems they are spot checking, that's dumb.
			Maintenance::$fatal_error = Lang::$txt['error_upgrade_files_missing'];

			return false;
		}

		/** @var \SMF\Maintenance\DatabaseInterface $db */
		$db = $this->loadMaintenanceDatabase(Config::$db_type);

		if (($db_version = $db->getServerVersion()) === false || version_compare($db->getMinimumVersion(), preg_replace('~^\D*|\-.+?$~', '', $db_version = $db->getServerVersion())) > 0) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_too_low', ['name' => $db->getTitle()]);

			return false;
		}

		// Check that we have database permissions.
		// CREATE
		$create = Db::$db->create_table('{db_prefix}priv_check', [['name' => 'id_test', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true]], [['columns' => ['id_test'], 'type' => 'primary']], [], 'overwrite');

		// ALTER
		$alter = Db::$db->add_column('{db_prefix}priv_check', ['name' => 'txt', 'type' => 'varchar', 'size' => 4, 'null' => false, 'default' => '']);

		// DROP
		$drop = Db::$db->drop_table('{db_prefix}priv_check');

		// Sorry... we need CREATE, ALTER and DROP
		if (!$create || !$alter || !$drop) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_privileges', ['name' => Config::$db_type]);

			return false;
		}

		// Do a quick version spot check.
		$temp = substr(@implode('', @file(Config::$boarddir . '/index.php')), 0, 4096);
		preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);

		if (empty($match[1]) || (trim($match[1]) != SMF_VERSION)) {
			Maintenance::$fatal_error = Lang::$txt['error_upgrade_old_files'];

			return false;
		}

		// What absolutely needs to be writable?
		$writable_files = [
			SMF_SETTINGS_FILE,
			SMF_SETTINGS_BACKUP_FILE,
		];

		// Only check for minified writable files if we have it enabled or not set.
		if (!empty(Config::$modSettings['minimize_files']) || !isset(Config::$modSettings['minimize_files'])) {
			$writable_files += [
				Config::$modSettings['theme_dir'] . '/css/minified.css',
				Config::$modSettings['theme_dir'] . '/scripts/minified.js',
				Config::$modSettings['theme_dir'] . '/scripts/minified_deferred.js',
			];
		}

		// Do we need to add this setting?
		$need_settings_update = empty(Config::$modSettings['custom_avatar_dir']);

		$custom_av_dir = !empty(Config::$modSettings['custom_avatar_dir']) ? Config::$modSettings['custom_avatar_dir'] : Config::$boarddir . '/custom_avatar';
		$custom_av_url = !empty(Config::$modSettings['custom_avatar_url']) ? Config::$modSettings['custom_avatar_url'] : Config::$boardurl . '/custom_avatar';

		$this->quickFileWritable($custom_av_dir);

		// Are we good now?
		if (!is_writable($custom_av_dir)) {
			Maintenance::$fatal_error = Lang::getTxt('error_dir_not_writable', ['dir' => $custom_av_dir]);

			return false;
		}

		if ($need_settings_update) {
			if (!function_exists('cache_put_data')) {
				require_once Config::$sourcedir . '/Cache/CacheApi.php';
			}

			Config::updateModSettings(['custom_avatar_dir' => $custom_av_dir]);
			Config::updateModSettings(['custom_avatar_url' => $custom_av_url]);
		}

		// Check the cache directory.
		$cache_dir_temp = empty(Config::$cachedir) ? Config::$boarddir . '/cache' : Config::$cachedir;

		if (!file_exists($cache_dir_temp)) {
			@mkdir($cache_dir_temp);
		}

		if (!file_exists($cache_dir_temp)) {
			Maintenance::$fatal_error = Lang::$txt['error_cache_not_found'];

			return false;
		}

		$this->quickFileWritable($cache_dir_temp . '/db_last_error.php');

		if (!is_writable($cache_dir_temp . '/db_last_error.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_dir_not_writable', ['dir' => $cache_dir_temp]);

			return false;
		}

		// Do we need to update our Settings file with the new language locale?
		$current_language = Config::$language;
		$new_locale = Lang::getLocaleFromLanguageName($current_language);

		if ($new_locale !== null && $new_locale != Config::$language) {
			Config::updateSettingsFile(['language' => $new_locale]);
		}

		if (empty(Config::$languagesdir)) {
			Config::updateSettingsFile(['languagesdir' => Config::$boarddir . '/Languages']);
		}

		// Try to make all the files writable, if we can not, we will display a chmod page to attempt this with additional permissions.
		if ($this->makeFilesWritable($writable_files)) {
			Maintenance::$context['chmod']['files'] = $writable_files;

			return false;
		}

		// Check agreement.txt. (it may not exist, in which case $boarddir must be writable.)
		if (isset(Config::$modSettings['agreement']) && (!is_writable(Config::$languagesdir) || file_exists(Config::$languagesdir . '/' . $this->default_language . '/agreement.txt')) && !is_writable(Config::$languagesdir . '/' . $this->default_language . '/agreement.txt')) {
			Maintenance::$fatal_error = Lang::$txt['error_agreement_not_writable'];

			return false;
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

		// First, check the avatar directory...
		// Note it wasn't specified in YabbSE, but there was no smfVersion either.
		if (!empty(Config::$modSettings['smfVersion']) && !is_dir(Config::$modSettings['avatar_directory'])) {
			Maintenance::$warnings[] = Lang::$txt['warning_av_missing'];
		}

		// Next, check the custom avatar directory...  Note this is optional in 2.0.
		if (!empty(Config::$modSettings['custom_avatar_dir']) && !is_dir(Config::$modSettings['custom_avatar_dir'])) {
				Maintenance::$warnings[] = Lang::$txt['warning_custom_av_missing'];
		}

		// Ensure we have a valid attachment directory.
		if ($this->attachmentDirectoryIsValid()) {
			Maintenance::$warnings[] = Lang::$txt['warning_att_dir_missing'];
		}

		// Attempting to login.
		if (empty(Maintenance::$errors) && isset($_POST['contbutt']) && (!empty($_POST['db_pass']) || (!empty($_POST['user']) && !empty($_POST['pass'])))) {
			if (!SecurityToken::validate('login')) {
				return false;
			}

			// Let them login, if they know the database password.
			if (!empty($_POST['db_pass']) && Maintenance::loginWithDatabasePassword((string) $_POST['db_pass'])) {
				$this->user = [
					'id' => 0,
					'name' => 'Database Admin',
					'step' => Maintenance::getCurrentStep(),
				];
				$_SESSION['is_logged'] = true;

				return true;
			}

			if (($id = Maintenance::loginAdmin((string) $_POST['user'], (string) $_POST['pass'])) > 0) {
				$this->user = [
					'id' => $id,
					'name' => (string) $_POST['user'],
					'step' => Maintenance::getCurrentStep(),
				];
				$_SESSION['is_logged'] = true;

				return true;
			}
		} elseif (empty(Maintenance::$errors)) {
			Maintenance::$context['continue'] = true;
		}

		Maintenance::$context += SecurityToken::create('login');

		return false;
	}


	/**
	 * Prepare the configuration to handle support with some older installs .
	 *
	 */
	private function prepareUpgrade(): void
	{
		// SMF 2.1: We don't use "-utf8" anymore...  Tweak the entry that may have been loaded by Settings.php
		if (isset(Config::$language)) {
			Config::$language = str_ireplace('-utf8', '', basename(Config::$language, '.lng'));
		}

		// SMF 1.x didn't support multiple database types.
		// SMF 2.0 used 'mysqli' for a short time.
		if (empty(Config::$db_type) || Config::$db_type == 'mysqli') {
			Config::$db_type = 'mysql';
			// If overriding Config::$db_type, need to set its settings.php entry too
			Config::updateSettingsFile(['db_type' => 'mysql']);
		}

		$this->getUpgradeData();

		// Template needs to know about this.
		Maintenance::$context['started'] = $this->time_started;
		Maintenance::$context['updated'] = $this->time_updated;
		Maintenance::$context['user'] = $this->user;
	}

	/**
	 * Get our upgrade data.
	 *
	 * @return array Upgrade data.
	 */
	private function getUpgradeData(): void
	{
		$defined_vars = Config::getCurrentSettings();

		$data = $defined_vars['upgradeData'] ?? [];

		$this->time_started = isset($this->upgradeData['started']) ? (int) $this->upgradeData['started'] : time();
		$this->time_updated = isset($this->upgradeData['updated']) ? (int) $this->upgradeData['updated'] : time();
		$this->debug = !empty($this->upgradeData['debug']);
		$this->skipped_migrations = !empty($this->upgradeData['skipped']) && is_array($this->upgradeData['skipped']) ? $this->upgradeData['skipped'] : [];
		$this->user['id'] = isset($this->upgradeData['user_id']) ? (int) $this->upgradeData['user_id'] : 0;
		$this->user['name'] = isset($this->upgradeData['user_name']) ? (int) $this->upgradeData['user_name'] : 0;
		$this->user['step'] = isset($this->upgradeData['step']) ? (int) $this->upgradeData['step'] : 0;
	}

	/**
	 * Save our data.
	 *
	 * @return bool True if we could update our settings file, false otherwise.
	 */
	private function saveUpgradeData(): bool
	{
		return Config::updateSettingsFile(['upgradeData' => json_encode([
			'started' => $this->time_started,
			'updated' => $this->upgradeData['updated'],
			'debug' => $this->debug,
			'skipped' => $this->skipped_migrations,
			'user_id' => $this->user['id'],
			'user_name' => $this->user['name'],
		])]);
	}

	/**
	 * Verify that the attachment directory is valid during the upgrade.
	 *
	 * This function safely checks both a serialized and json encoded attachment directory information.
	 * When multiple attachment directories exist, all are checked.
	 *
	 * @return bool True if no errors found during attachment testing, false otherwise.
	 */
	private function attachmentDirectoryIsValid(): bool
	{
		// A bit more complex, since it may be json or serialized, and it may be an array or just a string...

		// PHP currently has a terrible handling with unserialize in which errors are fatal and not catch-able.  Lets borrow some code from the RFC that intends to fix this
		// https://wiki.php.net/rfc/improve_unserialize_error_handling
		try {
			set_error_handler(static function ($severity, $message, $file, $line) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			});
			$ser_test = @unserialize(Config::$modSettings['attachmentUploadDir']);
		} catch (\Throwable $e) {
			$ser_test = false;
		} finally {
			restore_error_handler();
		}

		// Json is simple, it can be caught.
		try {
			$json_test = @json_decode(Config::$modSettings['attachmentUploadDir'], true);
		} catch (\Throwable $e) {
			$json_test = null;
		}

		$string_test = !empty(Config::$modSettings['attachmentUploadDir']) && is_string(Config::$modSettings['attachmentUploadDir']) && is_dir(Config::$modSettings['attachmentUploadDir']);

		// String?
		$attach_directory_problem_found = false;

		if ($string_test === true) {
			// OK...
		}
		// An array already?
		elseif (is_array(Config::$modSettings['attachmentUploadDir'])) {
			foreach(Config::$modSettings['attachmentUploadDir'] as $dir) {
				if (!empty($dir) && !is_dir($dir)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// Serialized?
		elseif ($ser_test !== false) {
			if (is_array($ser_test)) {
				foreach($ser_test as $dir) {
					if (!empty($dir) && !is_dir($dir)) {
						$attach_directory_problem_found = true;
					}
				}
			} else {
				if (!empty($ser_test) && !is_dir($ser_test)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// Json?  Note the test returns null if encoding was unsuccessful
		elseif ($json_test !== null) {
			if (is_array($json_test)) {
				foreach($json_test as $dir) {
					if (!is_dir($dir)) {
						$attach_directory_problem_found = true;
					}
				}
			} else {
				if (!is_dir($json_test)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// Unclear, needs a look...
		else {
			$attach_directory_problem_found = true;
		}

		return $attach_directory_problem_found;
	}
}

?>