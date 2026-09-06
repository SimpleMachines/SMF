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
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Table;
use SMF\EmailAddress;
use SMF\IP;
use SMF\Lang;
use SMF\Logging;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Step;
use SMF\Sapi;
use SMF\Security;
use SMF\TaskRunner;
use SMF\Themes\default\MaintenanceTemplate;
use SMF\Time;
use SMF\Unicode\SpoofDetector;
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
	 * Whether we can continue.
	 *
	 * When false the continue button is removed.
	 */
	public bool $continue = true;

	/**
	 * @var bool
	 *
	 * Whether we can skip the current step.
	 *
	 * If false, no skip option will be shown.
	 */
	public bool $skip = false;

	/**
	 * @var string
	 *
	 * The name of the script this tool uses.
	 *
	 * This is used by various actions and links.
	 */
	public string $script_file = 'install.php';

	/**
	 * @var string
	 *
	 * HTML element ID for the submission form in this tool's HTML templates.
	 */
	public string $form_id = 'install_form';

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

	/**
	 * @var int
	 *
	 * The time we started installing.
	 */
	private int $time_started = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		Maintenance::$languages = $this->detectLanguages(['General', 'Maintenance']);

		if (empty(Maintenance::$languages)) {
			if (!Sapi::isCLI()) {
				MaintenanceTemplate::missingLanguages();
			}

			throw new \Exception('This script was unable to find this tools\'s language file or files.');
		} else {
			$requested_lang = Maintenance::getRequestedLanguage();

			// Ensure SMF\Lang knows the path to the language directory.
			Lang::addDirs(Config::$languagesdir);

			// And now load the language file.
			Lang::load('General+Maintenance', $requested_lang);

			// Assume that the admin likes that language.
			if ($requested_lang !== 'en_US') {
				Config::$language = $requested_lang;
			}
		}

		$this->getProgress();

		// Template needs to know about this.
		Utils::$context['started'] = $this->time_started;
	}

	/**
	 *
	 */
	public function getScriptName(): string
	{
		return Lang::getTxt('smf_installer', file: 'Maintenance');
	}

	/**
	 * Gets our page title to be sent to the template.
	 *
	 * Selection is in the following order:
	 *  1. A custom page title.
	 *  2. Step has provided a title.
	 *  3. The value of $this->getScriptName().
	 *
	 * @return string The title for the page.
	 */
	public function getPageTitle(): string
	{
		return $this->page_title ?? $this->getStep()->getTitle() ?? $this->getScriptName();
	}

	/**
	 *
	 */
	public function hasSteps(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function getSteps(): array
	{
		return [
			0 => new Step(
				id: 1,
				name: Lang::getTxt('install_step_welcome', file: 'Maintenance'),
				title: Lang::getTxt('install_welcome', file: 'Maintenance'),
				function: 'welcome',
				template: 'welcome',
				progress: 0,
			),
			1 => new Step(
				id: 2,
				name: Lang::getTxt('install_step_writable', file: 'Maintenance'),
				function: 'checkFilesWritable',
				template: 'checkFilesWritable',
				progress: 10,
			),
			2 => new Step(
				id: 3,
				name: Lang::getTxt('install_step_databaseset', file: 'Maintenance'),
				title: Lang::getTxt('db_settings', file: 'Maintenance'),
				function: 'databaseSettings',
				template: 'databaseSettings',
				progress: 15,
			),
			3 => new Step(
				id: 4,
				name: Lang::getTxt('install_step_forum', file: 'Maintenance'),
				title: Lang::getTxt('install_settings', file: 'Maintenance'),
				function: 'forumSettings',
				template: 'forumSettings',
				progress: 40,
			),
			4 => new Step(
				id: 5,
				name: Lang::getTxt('install_step_databasechange', file: 'Maintenance'),
				title: Lang::getTxt('db_populate', file: 'Maintenance'),
				function: 'databasePopulation',
				template: 'databasePopulation',
				progress: 15,
			),
			5 => new Step(
				id: 6,
				name: Lang::getTxt('install_step_admin', file: 'Maintenance'),
				title: Lang::getTxt('user_settings', file: 'Maintenance'),
				function: 'adminAccount',
				template: 'adminAccount',
				progress: 20,
			),
			6 => new Step(
				id: 7,
				name: Lang::getTxt('install_step_finalize', file: 'Maintenance'),
				function: 'finalize',
				template: 'finalize',
				progress: 0,
			),
		];
	}

	/**
	 *
	 */
	public function getStepTitle(): string
	{
		return $this->getStep()->getName();
	}

	/**
	 * Welcome action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function welcome(): bool
	{
		// Done the submission?
		if (isset($_POST['contbutt'])) {
			return true;
		}

		$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));

		if (Maintenance::isInstalled()) {
			Utils::$context['warning'] = Lang::getTxt('error_already_installed', file: 'Maintenance');
			$this->logProgress(Utils::$context['warning']);
		}

		Utils::$context['supported_databases'] = $this->supportedDatabases();

		// Needs to at least meet our miniumn version.
		if ((version_compare(Maintenance::PHP_MIN_VERSION, PHP_VERSION, '>'))) {
			Maintenance::$fatal_error = Lang::getTxt('error_php_too_low', ['min_version' => Maintenance::PHP_MIN_VERSION], file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Only 64-bit builds are supported.
		if (PHP_INT_SIZE < 8) {
			Maintenance::$fatal_error = Lang::getTxt('error_php_32_bit', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Make sure we have a supported database
		if (empty(Utils::$context['supported_databases'])) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_missing', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// How about session support?  Some crazy sysadmin remove it?
		if (!\function_exists('session_start')) {
			Maintenance::$errors[] = Lang::getTxt('error_session_missing', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('error_session_missing', file: 'Maintenance'));
		}

		// Make sure they uploaded all the files.
		if (!file_exists(Config::$boarddir . '/index.php')) {
			Maintenance::$errors[] = Lang::getTxt('error_missing_files', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('error_missing_files', file: 'Maintenance'));
		}
		// Very simple check on the session.save_path for Windows.
		// @todo Move this down later if they don't use database-driven sessions?
		elseif (@\ini_get('session.save_path') == '/tmp' && Sapi::isOS(Sapi::OS_WINDOWS)) {
			Maintenance::$errors[] = Lang::getTxt('error_session_save_path', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('error_session_save_path', file: 'Maintenance'));
		}

		// Mod_security blocks everything that smells funny. Let SMF handle security.
		if (!$this->checkAndTryToFixModSecurity() && !isset($_GET['overmodsecurity'])) {
			Maintenance::$fatal_error = Lang::getTxt('error_mod_security', file: 'Maintenance') . '<br><br>' . Lang::getTxt('error_message_bad_try_again', ['url' => Maintenance::getSelf() . '?overmodsecurity=true'], file: 'Maintenance');
			$this->logProgress(Lang::getTxt('error_mod_security', file: 'Maintenance'));
		}

		// Confirm mbstring is loaded...
		if (!\extension_loaded('mbstring')) {
			Maintenance::$errors[] = Lang::getTxt('install_no_mbstring', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_mbstring', file: 'Maintenance'));
		}

		// Confirm fileinfo is loaded...
		if (!\extension_loaded('fileinfo')) {
			Maintenance::$errors[] = Lang::getTxt('install_no_fileinfo', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_fileinfo', file: 'Maintenance'));
		}

		// Check for https stream support.
		$supported_streams = stream_get_wrappers();

		if (!\in_array('https', $supported_streams)) {
			Maintenance::$warnings[] = Lang::getTxt('install_no_https', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_https', file: 'Maintenance'));
		}

		if (empty(Maintenance::$errors)) {
			Utils::$context['continue'] = true;
		}

		// Are we doing debug?
		if (isset($_REQUEST['debug'])) {
			$this->debug = true;
		}

		return false;
	}

	/**
	 * Check Files Writable action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function checkFilesWritable(): bool
	{
		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		$writable_files = [
			Config::$boarddir . '/attachments',
			Config::$boarddir . '/avatars',
			Config::$boarddir . '/custom_avatar',
			Config::$boarddir . '/cache',
			Config::$boarddir . '/Packages',
			Config::$boarddir . '/Smileys',
			Config::$boarddir . '/Themes',
			Config::$boarddir . '/Languages/en_US/agreement.txt',
			Config::$boarddir . '/Settings.php',
			Config::$boarddir . '/Settings_bak.php',
			Config::$boarddir . '/cache/db_last_error.php',
		];

		foreach ($this->detectLanguages() as $lang => $temp) {
			$writable_files[] = Config::$boarddir . '/Languages/' . $lang;
		}

		// With mod_security installed, we could attempt to fix it with .htaccess.
		if (\function_exists('apache_get_modules') && \in_array('mod_security', apache_get_modules())) {
			$writable_files[] = file_exists(Config::$boarddir . '/.htaccess') ? Config::$boarddir . '/.htaccess' : Config::$boarddir;
		}

		return $this->makeFilesWritable($writable_files);
	}

	/**
	 * Database Settings action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function databaseSettings(): bool
	{
		Utils::$context['continue'] = true;
		Utils::$context['databases'] = [];
		$foundOne = false;

		foreach ($this->supportedDatabases() as $db_type => $db) {
			// Not supported, skip.
			if (!$db->isSupported()) {
				continue;
			}

			Utils::$context['databases'][$db_type] = $db;

			// If we have not found a one, set some defaults.
			if (!$foundOne) {
				Utils::$context['db'] = [
					'server' => $db->getDefaultHost() === '' ? 'localhost' : $db->getDefaultHost(),
					'user' => $db->getDefaultUser(),
					'name' => $db->getDefaultName(),
					'pass' => $db->getDefaultPassword(),
					'port' => '',
					'prefix' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3) . '_',
					'type' => $db_type,
				];

				$foundOne = true;
			}
		}

		if (isset($_POST['db_user'])) {
			Utils::$context['db']['user'] = $_POST['db_user'];
			Utils::$context['db']['name'] = $_POST['db_name'];
			Utils::$context['db']['server'] = $_POST['db_server'];
			Utils::$context['db']['prefix'] = $_POST['db_prefix'];

			if (!empty($_POST['db_port'])) {
				Utils::$context['db']['port'] = (int) $_POST['db_port'];
			}
		}

		// Are we submitting?
		if (!isset($_POST['db_type'])) {
			return false;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		// What type are they trying?
		$db_type = preg_replace('~[^A-Za-z0-9]~', '', $_POST['db_type']);
		$db_prefix = $_POST['db_prefix'];

		if (!isset(Utils::$context['databases'][$db_type])) {
			Maintenance::$fatal_error = Lang::getTxt(
				'error_db_type_unknown',
				[
					'db_type' => $db_type,
					'supported' => Lang::sentenceList(array_keys(Utils::$context['databases'])),
				],
				file: 'Maintenance',
			);

			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Validate the prefix.
		$db = Utils::$context['databases'][$db_type];

		try {
			$db->validatePrefix($db_prefix);
		} catch (\Throwable $e) {
			Maintenance::$fatal_error = $e->getMessage();

			$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => Maintenance::$fatal_error], file: 'Maintenance'));

			return false;
		}

		// Database names can not have periods, just complicates things.
		if (strpos(Utils::$context['db']['name'], '.') !== false) {
			Maintenance::$fatal_error = Lang::getTxt('db_settings_database_invalid', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

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

		// Save the settings.
		if (!$this->updateSettingsFile($vars)) {
			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// Better find the database file!
		if (!file_exists(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_file', ['Db/APIs/' . Db::getClass(Config::$db_type) . '.php']);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// We need to make some queries that would trigger up our normal security checks.
		Config::$modSettings['disableQueryCheck'] = true;

		// Attempt a connection.
		Db::load([
			'non_fatal' => true,
			'dont_select_db' => !Utils::$context['databases'][$db_type]->alwaysHasDb(),
		]);

		// Still no connection?  Big fat error message :P.
		if (!isset(Db::$db->connection)) {
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

			Maintenance::$fatal_error = Lang::getTxt('error_db_connect', file: 'Maintenance') . '<div class="error_content"><strong>' . $db_error . '</strong></div>';
			$this->logProgress(Lang::getTxt('error_db_connect', file: 'Maintenance') . ': ' . $db_error);

			return false;
		}

		// Do they meet the install requirements?
		// @todo Old client, new server?
		if (
			version_compare(
				preg_replace('~^\D*|\-.+?$~', '', Db::$db->get_version()),
				Db::$db->getMinimumVersion(),
				'<',
			)
		) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_too_low', ['name' => Db::$db->title, 'min_version' => Db::$db->getMinimumVersion()]);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Let's try that database on for size... assuming we haven't already lost the opportunity.
		if (Db::$db->name != '' && !Utils::$context['databases'][$db_type]->alwaysHasDb()) {
			Db::$db->query(
				'CREATE DATABASE IF NOT EXISTS {identifier:name}',
				[
					'security_override' => true,
					'db_error_skip' => true,
					'name' => Db::$db->name,
				],
				Db::$db->connection,
			);

			// Okay, let's try the prefix if it didn't work...
			if (!Db::$db->select(Db::$db->name, Db::$db->connection)) {
				Db::$db->query(
					'CREATE DATABASE IF NOT EXISTS {identifier:name}',
					[
						'security_override' => true,
						'db_error_skip' => true,
						'name' => Db::$db->prefix . Db::$db->name,
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
				$this->logProgress(Maintenance::$fatal_error);

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
	public function forumSettings(): bool
	{
		// Let's see if we got the database type correct.
		if (isset($_POST['db_type'], $this->supportedDatabases()[$_POST['db_type']])) {
			Config::$db_type = $_POST['db_type'];

			if (!$this->updateSettingsFile(['db_type' => Config::$db_type])) {
				return false;
			}

			Config::load();
		}

		// We'd better be able to get the connection.
		Db::load();

		// Now, to put what we've learned together... and add a path.
		Utils::$context['detected_url'] = 'http' . (Sapi::httpsOn() ? 's' : '') . '://' . $this->defaultHost() . (!str_contains(Maintenance::getSelf(), '/') ? '' : substr(Maintenance::getSelf(), 0, strrpos(Maintenance::getSelf(), '/')));

		// Check if the database sessions will even work.
		Utils::$context['test_dbsession'] = (\ini_get('session.auto_start') != 1);

		Utils::$context['continue'] = true;

		// Do we have a failure of database configuration?
		try {
			Db::$db->checkConfiguration();
		} catch (\Throwable $e) {
			Maintenance::$fatal_error = $e->getMessage();
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Setup the SSL checkbox...
		Utils::$context['ssl_chkbx_protected'] = false;
		Utils::$context['ssl_chkbx_checked'] = false;

		// If redirect in effect, force SSL ON.
		$url = new Url(Utils::$context['detected_url']);

		if ($url->redirectsToHttps()) {
			Utils::$context['ssl_chkbx_protected'] = true;
			Utils::$context['ssl_chkbx_checked'] = true;
			$_POST['force_ssl'] = true;
		}

		// If no cert, make sure SSL stays OFF.
		if (!$url->hasSSL()) {
			Utils::$context['ssl_chkbx_protected'] = true;
			Utils::$context['ssl_chkbx_checked'] = false;
		}

		// Submitting?
		if (!isset($_POST['boardurl'])) {
			return false;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
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

		if (!$this->updateSettingsFile($vars)) {
			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// Good, skip on.
		return true;
	}

	/**
	 * Database Population action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function databasePopulation(): bool
	{
		Utils::$context['continue'] = true;

		// Already done?
		if (isset($_POST['pop_done'])) {
			return true;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		// Reload settings.
		Config::load();
		Db::load();
		$newSettings = [];

		Config::$modSettings['disableQueryCheck'] = true;

		$existing_tables = Db::$db->list_tables();

		$install_tables = Table::getAll($this->schema_version);

		// Before running any of the queries, let's make sure another version isn't already installed.
		if (\in_array(Config::$db_prefix . 'settings', $existing_tables)) {
			$result = Db::$db->query(
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
					Maintenance::$fatal_error = Lang::getTxt('error_versions_do_not_match', file: 'Maintenance');
					$this->logProgress(Maintenance::$fatal_error);

					return false;
				}
			}
		}

		Utils::$context['sql_results'] = [
			'tables' => 0,
			'inserts' => 0,
			'table_dups' => 0,
			'insert_dups' => 0,
		];

		// Some initialization may exist.
		Db::$db->disableQueryCheck = true;

		foreach (Table::getInitializers($this->schema_version) as $query) {
			Db::$db->query($query, [
				'security_override' => true,
			]);
		}
		Db::$db->disableQueryCheck = false;

		foreach ($install_tables as $table) {
			$this->logProgress(Lang::getTxt('log_table_create', ['table' => Config::$db_prefix . $table->name], file: 'Maintenance'), true);

			// Create the table, unless it already exists.
			if (!\in_array(Config::$db_prefix . $table->name, $existing_tables)) {
				try {
					if (!$table->create()) {
						throw new \Exception(Db::$db->error());
					}

					Utils::$context['sql_results']['tables']++;
					$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));
				} catch (\Throwable $e) {
					Utils::$context['failures'][] = trim($e->getMessage());
					$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => trim($e->getMessage())], file: 'Maintenance'));

					continue;
				}
			} else {
				Utils::$context['sql_results']['table_dups']++;
				$this->logProgress(Lang::getTxt('log_skipped', file: 'Maintenance'));
			}

			// If this table has some initial data to insert, do so.
			if (!empty($table->initial_data)) {
				$this->logProgress(Lang::getTxt('log_table_populate', ['table' => Config::$db_prefix . $table->name], file: 'Maintenance'), true);

				try {
					$num_inserts = $table->populate();

					Utils::$context['sql_results']['inserts'] += $num_inserts;
					Utils::$context['sql_results']['insert_dups'] += (\count($table->initial_data) - $num_inserts);

					$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));
				} catch (\Throwable $e) {
					Utils::$context['failures'][] = $table->name . ':' . $e->getMessage();

					$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));
				}
			}

			// Wait, wait, I'm still working here!
			Sapi::setTimeLimit(60);
		}

		// Sort out the context for the SQL.
		foreach (Utils::$context['sql_results'] as $key => $number) {
			if ($number === 0) {
				unset(Utils::$context['sql_results'][$key]);
			} else {
				Utils::$context['sql_results'][$key] = Lang::getTxt('db_populate_' . $key, [$number], file: 'Maintenance');

				$this->logProgress(Utils::$context['sql_results'][$key]);
			}
		}

		$this->toggleSmStats($newSettings);

		// Are we enabling SSL?
		if (!empty($_POST['force_ssl'])) {
			$newSettings['force_ssl'] = 1;
		}

		// Setting a timezone is required.
		$newSettings['default_timezone'] = $this->determineTimezone();

		if (!empty($newSettings)) {
			$this->updateModSettings($newSettings);
		}

		// Let's optimize those new tables, but not on InnoDB, ok? (SMF will check this)
		foreach ($install_tables as $table) {
			try {
				if (!(Db::$db->optimize_table(Config::$db_prefix . $table->name) > -1)) {
					Utils::$context['failures'][] = Db::$db->error();
					$this->logProgress(Db::$db->error());
				}
			} catch (\Throwable $e) {
				Utils::$context['failures'][] = $e->getMessage();
				$this->logProgress($e->getMessage());
			}
		}

		// Find out if we have permissions we didn't use, but will need for the future.
		// @@ TODO: This was at this location in the original code, it should come earlier.
		if (!Db::$db->hasPermissions()) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_alter_priv', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);
		}

		// Was this a refresh?
		if (\count($existing_tables) > 0) {
			$this->page_title = Lang::getTxt('user_refresh_install', file: 'Maintenance');
			Utils::$context['was_refresh'] = true;
		}

		return false;
	}

	/**
	 * Admin Account action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function adminAccount(): bool
	{
		Utils::$context['continue'] = true;

		// Skipping?
		if (!empty($_POST['skip'])) {
			return true;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		// Need this to check whether we need the database password.
		Config::load();
		Db::load();

		$settingsDefs = Config::getSettingsDefs();

		// Reload $modSettings.
		Config::reloadModSettings();

		Utils::$context['username'] = htmlspecialchars($_POST['username'] ?? '');
		Utils::$context['email'] = htmlspecialchars($_POST['email'] ?? '');
		Utils::$context['server_email'] = htmlspecialchars($_POST['server_email'] ?? (!empty(Config::$webmaster_email) && Config::$webmaster_email !== $settingsDefs['webmaster_email']['default'] ? Config::$webmaster_email : ''));

		Utils::$context['require_db_confirm'] = empty(Config::$db_type);

		// Only allow skipping if we think they already have an account setup.
		$request = Db::$db->query(
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
			Utils::$context['skip'] = true;

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
		if (Utils::$context['require_db_confirm'] && $_POST['password3'] != Config::$db_passwd) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_connect', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Not matching passwords?
		if ($_POST['password1'] != $_POST['password2']) {
			Maintenance::$fatal_error = Lang::getTxt('error_user_settings_again_match', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// No password?
		if (\strlen($_POST['password1']) < 4) {
			Maintenance::$fatal_error = Lang::getTxt('error_user_settings_no_password', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		if (!file_exists(Config::$sourcedir . '/Utils.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_sourcefile_missing', ['file' => 'Utils.php']);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Normalize Unicode characters.
		$_POST['username'] = Utils::normalize($_POST['username']);

		// Replace any kind of space or illegal character with a normal space, and then trim.
		$_POST['username'] = Utils::htmlTrim(Utils::normalizeSpaces(Utils::sanitizeChars($_POST['username'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));

		$username_errors = Security::validateUsername(0, $_POST['username'], true, false);

		if (!empty($username_errors)) {
			foreach ($username_errors as $error) {
				switch ($error[1]) {
					case 'error_long_name':
						Maintenance::$fatal_error = Lang::getTxt('error_username_too_long', file: 'Maintenance');
						break;

					case 'need_username':
						Maintenance::$fatal_error = Lang::getTxt('error_username_left_empty', file: 'Maintenance');
						break;

					case 'username_reserved':
						Maintenance::$fatal_error = Lang::getTxt('username_reserved', $error[3], file: 'Errors');
						break;

					default:
						Maintenance::$fatal_error = Lang::getTxt('error_invalid_characters_username', file: 'General');
						break;
				}
			}

			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Is this email address valid?
		$_POST['email'] = new EmailAddress($_POST['email']);

		if (!$_POST['email']->isValid() || \strlen((string) $_POST['email']) > 255) {
			// One step back, this time fill out a proper admin email address.
			Maintenance::$fatal_error = Lang::getTxt('error_valid_admin_email_needed', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Is this email address taken?
		$result = Db::$db->query(
			'SELECT id_member, password_salt
			FROM {db_prefix}members
			WHERE member_name = {string:username} OR email_address_ci = {string:email}
			LIMIT 1',
			[
				'username' => $_POST['username'],
				'email' => $_POST['email']->casefolded(),
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			Utils::$context += Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			Utils::$context['account_existed'] = Lang::getTxt('error_user_settings_taken', file: 'Maintenance');

			return false;
		}

		// Update the webmaster's email?
		$_POST['server_email'] = new EmailAddress($_POST['server_email'] ?? '');

		if ($_POST['server_email']->isValid() && \strlen((string) $_POST['server_email']) < 256) {
			$this->updateSettingsFile(['webmaster_email' => (string) $_POST['server_email']]);
		} else {
			// One step back, this time fill out a proper webmaster email address.
			Maintenance::$fatal_error = Lang::getTxt('error_valid_server_email_needed', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Create the admin account.
		if ($_POST['username'] != '') {
			Utils::$context['password_salt'] = bin2hex(random_bytes(16));

			$ip = IP::getUserIP();

			$_POST['password1'] = Security::hashPassword($_POST['password1']);

			try {
				Utils::$context['id_member'] = Db::$db->insert(
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
						'spoofdetector_name' => 'string',
						'email_address_ci' => 'string',
					],
					[
						[
							$_POST['username'],
							$_POST['username'],
							$_POST['password1'],
							(string) $_POST['email'],
							1,
							0,
							time(),
							Utils::$context['password_salt'],
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
							Utils::htmlspecialchars(SpoofDetector::getSkeletonString(html_entity_decode($_POST['username'], ENT_QUOTES))),
							$_POST['email']->casefolded(),
						],
					],
					['id_member'],
					Db::INSERT_RETURN_MODE_SINGLE,
				);

				if ((int) Utils::$context['id_member'] > 0) {
					return true;
				}

				Maintenance::$fatal_error = trim(Db::$db->error());
				$this->logProgress(Maintenance::$fatal_error);

				return false;

			} catch (\Throwable $e) {
				Maintenance::$fatal_error = $e->getMessage();
				$this->logProgress(Maintenance::$fatal_error);
			}
		}

		return false;
	}

	/**
	 * Delete Install action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function finalize(): bool
	{
		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		Utils::$context['continue'] = false;

		// Rebuild the settings file.
		$this->updateSettingsFile(['maintenance_tool_progress' => ''], false, true);

		Config::load();
		Db::load();

		chdir(Config::$boarddir);

		// Reload $modSettings.
		Config::reloadModSettings();

		// Everything below needs a current user: Time and Logging both read
		// User::$me to work out which time zone to record dates in.
		if (isset(Utils::$context['id_member'])) {
			User::setMe((int) Utils::$context['id_member']);
		} else {
			User::loadMe();
		}

		// Bring a warning over.
		if (!empty(Utils::$context['account_existed'])) {
			Maintenance::$warnings = Utils::$context['account_existed'];
		}

		// As track stats is by default enabled let's add some activity.
		Db::$db->insert(
			'ignore',
			'{db_prefix}log_activity',
			[
				'date' => 'date',
				'topics' => 'int',
				'posts' => 'int',
				'registers' => 'int',
			],
			[
				[
					Time::strftime('%Y-%m-%d', time()),
					1,
					1,
					!empty(Utils::$context['id_member']) ? 1 : 0,
				],
			],
			['date'],
		);

		// We're going to want our lovely Config::$modSettings now.
		$request = Db::$db->query(
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

		// Sign the new administrator in. (Not applicable on the command line.)
		if (!Sapi::isCLI()) {
			// Automatically log them in ;)
			if (isset(Utils::$context['id_member'], Utils::$context['password_salt'])) {
				Cookie::setLoginCookie(3153600 * 60, Utils::$context['id_member'], Cookie::encrypt($_POST['password1'], Utils::$context['password_salt']));
			}

			$result = Db::$db->query(
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
						'session_id' => 'string',
						'last_update' => 'int',
						'data' => 'string',
					],
					[
						[
							session_id(),
							time(),
							'USER_AGENT|s:' . \strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
						],
					],
					['session_id'],
				);
			}
		}

		Logging::updateStats('member');
		Logging::updateStats('message');
		Logging::updateStats('topic');

		$request = Db::$db->query(
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
			Logging::updateStats('subject', 1, htmlspecialchars(Lang::getTxt('default_topic_subject', file: 'Maintenance')));
		}
		Db::$db->free_result($request);

		// Now is the perfect time to fetch the SM files.
		// Sanity check that they loaded earlier!
		if (isset(Config::$modSettings['recycle_board'])) {
			(new TaskRunner())->runScheduledTasks(['fetchSMfiles']); // Now go get those files!

			User::$me->ip = IP::getUserIP();

			Logging::logAction('install', ['version' => SMF_FULL_VERSION], 'admin');
		}

		// Disable the legacy BBC by default for new installs
		$this->updateModSettings([
			'disabledBBC' => implode(',', Utils::$context['legacy_bbc']),
		]);

		// Some final context for the template.
		Utils::$context['dir_still_writable'] = is_writable(Config::$boarddir);
		Utils::$context['can_delete_script'] = $this->canDeleteTool();

		// Update hash's cost to an appropriate setting
		$this->updateModSettings([
			'bcrypt_hash_cost' => Security::hashBenchmark(),
		]);

		$this->logProgress(Lang::getTxt('log_install_complete', file: 'Maintenance'));

		if (!Sapi::isCLI() && $this->isDebug()) {
			Utils::$context['log_contents'] = file_get_contents($this->log_file);
		}

		$this->finalizeLog();

		return false;
	}

	/**
	 * Write out our current information to our settings file to track the upgrade progress.
	 */
	public function preExit(): void
	{
		$this->saveProgress();
	}

	/******************
	 * Internal methods
	 ******************/

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

		if (!\function_exists('apache_get_modules') || !\in_array('mod_security', apache_get_modules())) {
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
	 * Get our upgrade data.
	 */
	private function getProgress(): void
	{
		$defined_vars = Config::getCurrentSettings();

		$data = isset($defined_vars['maintenance_tool_progress']) ? Utils::jsonDecode($defined_vars['maintenance_tool_progress'], true) : [];

		$this->time_started = (int) ($data['started'] ?? time());
		$this->debug = !empty($data['debug']);
	}

	/**
	 * Save our data.
	 *
	 * @return bool True if we could update our settings file, false otherwise.
	 */
	private function saveProgress(): bool
	{
		// Once we are done there is no progress left to track, and leaving a
		// value here would tell SMF that an install is still in progress. That
		// would stop background tasks from ever running on the new forum.
		if (Maintenance::$overall_percent < 100) {
			$data = json_encode([
				'started' => $this->time_started,
				'debug' => $this->debug,
			]);
		} else {
			$data = '';
		}

		return $this->updateSettingsFile(['maintenance_tool_progress' => $data]);
	}

	/**
	 * Determine the default host, used during install to populate Config::$boardurl.
	 *
	 * @return string The host we have determined to be on.
	 */
	private function defaultHost(): string
	{
		if (!empty($_SERVER['HTTP_HOST'])) {
			return $_SERVER['HTTP_HOST'];
		}

		// On the command line there is no request to describe, so neither of
		// these is set. This value only seeds the suggested board URL on the
		// form, and a scripted install passes its own boardurl in, so a
		// placeholder is enough -- but reading the keys unguarded was a warning
		// on every CLI run.
		$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
		$port = $_SERVER['SERVER_PORT'] ?? '';

		return $host . (empty($port) || $port == '80' ? '' : ':' . $port);
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
		$boardurl = (string) new Url($boardurl, true);

		return $boardurl;
	}

	/**
	 * Determine if we need to enable or disable (during upgrades) SMF stat collection.
	 *
	 * @param array $settings Settings array, passed by reference.
	 */
	private function toggleSmStats(array &$settings): void
	{
		if (
			!empty($_POST['stats'])
			&& substr(Config::$boardurl, 0, 16) != 'http://localhost'
			&& empty(Config::$modSettings['allow_sm_stats'])
			&& empty(Config::$modSettings['enable_sm_stats'])
		) {
			Utils::$context['allow_sm_stats'] = true;

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
		elseif (empty($_POST['stats']) && empty(Utils::$context['allow_sm_stats'])) {
			$settings['enable_sm_stats'] = null;
		}
	}

	/**
	 * Attempt to determine what our time zone is.
	 *
	 * @return string A valid time zone identifier.
	 */
	private function determineTimezone(): string
	{
		if (isset(Config::$modSettings['default_timezone']) && \in_array(Config::$modSettings['default_timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
			return Config::$modSettings['default_timezone'];
		}

		// Get PHP's default timezone, if set
		$ini_tz = \ini_get('date.timezone');

		if (!empty($ini_tz)) {
			$timezone_id = $ini_tz;
		} else {
			$timezone_id = '';
		}

		// If date.timezone is unset, invalid, or just plain weird, make a best guess
		if (!\in_array($timezone_id, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
			$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
			$timezone_id = timezone_name_from_abbr('', $server_offset, 0);

			if (empty($timezone_id)) {
				$timezone_id = 'UTC';
			}
		}

		if (date_default_timezone_set($timezone_id)) {
			return $timezone_id;
		}

		date_default_timezone_set('UTC');

		return 'UTC';
	}
}
