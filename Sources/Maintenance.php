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

namespace SMF;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Template;
use SMF\Maintenance\TemplateInterface;
use SMF\Maintenance\ToolsInterface;

/**
 * Main class for all maintenance actions.
 */
class Maintenance
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The lowest PHP version we support.
	 * This can not be a int, as we have 2 decimals.
	 */
	public const PHP_MIN_VERSION = '8.0.1';

	/**
	 * Tool Types.
	 */
	public const INSTALL = 1;
	public const UPGRADE = 2;
	public const CONVERT = 3;
	public const TOOL = 4;
	public const SPECIAL = 99;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * General variables we pass between the logic and template.
	 */
	public static array $context = [];

	/**
	 * List of languages we have for ths tool.
	 *
	 * @var array
	 */
	public static array $languages = [];

	/**
	 * List of warnings to display on the page.
	 * @var array
	 */
	public static array $warnings = [];

	/**
	 * List of errors to display on the page.
	 * @var array
	 */
	public static array $errors = [];

	/**
	 * Fatal error, we display this error message and do not process anything further until the error has been corrected.
	 * This differs from $errors in that we should not continue operations.
	 *
	 * @var string
	 */
	public static string $fatal_error = '';

	/**
	 * Our Theme url, defaults if we can't find it.
	 *
	 * @var string
	 */
	public static string $theme_url = 'Themes/default';

	/**
	 * Our theme dir, this is set during construct.
	 * @var string
	 */
	public static string $theme_dir = '';

	/**
	 * Our Images url, defaults if we can't find it.
	 *
	 * @var string
	 */
	public static string $images_url = 'Themes/default/images';

	/**
	 * String to be appended to our urls.
	 *
	 * Upgrade logic will automatically maintain this.
	 *
	 * @var string
	 */
	public static string $query_string = '';

	/**
	 * Object containing the tool we are working with.
	 *
	 * @var \SMF\Maintenance\ToolsInterface&\SMF\Maintenance\ToolsBase
	 */
	public static ToolsInterface $tool;

	/**
	 * Object containing the tools template we are working with.
	 *
	 * @var \SMF\Maintenance\TemplateInterface
	 */
	public static TemplateInterface $template;

	/**
	 * Sub template to call during output.
	 *
	 * @var string
	 */
	public static string $sub_template = '';

	/**
	 * List of valid tools. The Special tool is a self contained logic, while other tools are located in the Tools directory.
	 *
	 * @var array
	 */
	private static array $valid_tools = [
		self::INSTALL => 'Install',
		self::UPGRADE => 'Upgrade',
		self::CONVERT => 'Convert',
		self::TOOL => 'Tool',
		self::SPECIAL => 'Special',
	];

	/**
	 * How far we have progressed.
	 *
	 * @var int
	 */
	public static int $overall_percent = 0;

	/**
	 * Disable security functions such as login checks.
	 *
	 * @var bool
	 */
	public static bool $disable_security = false;

	/**
	 * Total number of sub steps.
	 * When set, the sub-step progress bar is shown.  The current is obtain from getCurrentSubStep().
	 *
	 * @var null|int Null when we do not have any sub steps, int greater than 0 otherwise.
	 */
	public static ?int $total_substeps = null;

	/**
	 * Name of the sub step, used on templates and outputting progress.
	 *
	 * @var string
	 */
	public static string $substep_name = '';

	/**
	 * Total number of sub items.
	 * When set, the items progress bar is shown.  The current is obtain from getCurrentStart().
	 *
	 * @var null|int Null when we do not have any items, int greater than 0 otherwise.
	 */
	public static ?int $total_items = null;

	/**
	 * Only filled in during debugging, name of the item name.
	 *
	 * @var string
	 */
	public static string $item_name = '';

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		Security::frameOptionsHeader('SAMEORIGIN');
		self::$theme_dir = dirname(SMF_SETTINGS_FILE) . '/Themes/default';

		if (!defined('DISABLE_TOOL_SECURITY')) {
			define('DISABLE_TOOL_SECURITY', false);
		}
		self::$disable_security = DISABLE_TOOL_SECURITY;

		// This might be overwritten by the tool, but we need a default value.
		self::$context['started'] = (int) TIME_START;
	}

	/**
	 * This is the main call to get stuff done.
	 *
	 * @var int The tool type we are running.
	 */
	public function execute(int $type): void
	{
		if (!self::toolIsValid($type)) {
			die('Invalid Tool selected');
		}

		// Handle the CLI.
		if (Sapi::isCLI()) {
			self::parseCliArguments();
		}

		/** @var \SMF\Maintenance\ToolsInterface&\SMF\Maintenance\ToolsBase $tool_class */
		$tool_class = '\\SMF\\Maintenance\\Tools\\' . self::$valid_tools[$type];

		require_once Config::$sourcedir . '/Maintenance/Tools/' . self::$valid_tools[$type] . '.php';
		self::$tool = new $tool_class();

		/** @var \SMF\Maintenance\TemplateInterface $template_class */
		$template_class = '\\SMF\\Maintenance\\Template\\' . self::$valid_tools[$type];

		require_once Config::$sourcedir . '/Maintenance/Template/' . self::$valid_tools[$type] . '.php';
		self::$template = new $template_class();

		// This is really quite simple; if ?delete is on the URL, delete the installer...
		if (isset($_GET['delete'])) {
			self::$tool->deleteTool();

			exit;
		}

		/** @var \SMF\Maintenance\Step $step */
		foreach (self::$tool->getSteps() as $num => $step) {
			if ($num >= self::getCurrentStep()) {
				// The current weight of this step in terms of overall progress.
				self::$context['step_weight'] = $step->getProgress();

				// Make sure we reset the skip button.
				self::$context['skip'] = false;

				// Call the step and if it returns false that means pause!
				if (
					method_exists(self::$tool, $step->getFunction())
					&& self::$tool->{$step->getFunction()}() === false
				) {
					break;
				}

				// Time to move on.
				self::setCurrentStep();
				self::setCurrentSubStep(0);
				self::setCurrentStart(0);

				// No warnings pass on.
				self::$context['warning'] = '';
			}

			self::$overall_percent += (int) $step->getProgress();
		}

		// Last chance to set our template.
		if (self::$sub_template === '') {
			self::$sub_template = self::$tool->getSteps()[self::getCurrentStep()]->getFunction();
		}

		// Make a final call before we are done..
		self::$tool->preExit();

		self::exit();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * See if we think they have already installed it?
	 *
	 * @return bool Whether we believe SMF has been installed.
	 */
	public static function isInstalled(): bool
	{
		foreach (['image_proxy_secret', 'db_passwd', 'boardurl'] as $var) {
			if (Config::${$var} === Config::$settings_defs[$var]['default']) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The URL to the script.
	 *
	 * @return string The URL to the script.
	 */
	public static function getSelf(): string
	{
		return $_SERVER['PHP_SELF'];
	}

	/**
	 * Get the base directory in which the forum root is.
	 *
	 * @return string The directory name we are in.
	 */
	public static function getBaseDir(): string
	{
		if (class_exists('\\SMF\\Config')) {
			if (!isset(Config::$boarddir)) {
				Config::load();
			}

			if (isset(Config::$boarddir)) {
				return Config::$boarddir;
			}
		}

		// If SMF\Config::$boarddir was not available for some reason, try doing it manually.
		if (!in_array(SMF_SETTINGS_FILE, get_included_files())) {
			require SMF_SETTINGS_FILE;
		} else {
			$settingsText = trim(file_get_contents(SMF_SETTINGS_FILE));

			if (substr($settingsText, 0, 5) == '<' . '?php') {
				$settingsText = substr($settingsText, 5);
			}

			if (substr($settingsText, -2) == '?' . '>') {
				$settingsText = substr($settingsText, 0, -2);
			}

			// Since we're using eval, we need to manually replace these with strings.
			$settingsText = strtr($settingsText, [
				'__FILE__' => var_export(SMF_SETTINGS_FILE, true),
				'__DIR__' => var_export(dirname(SMF_SETTINGS_FILE), true),
			]);

			// Prevents warnings about constants that are already defined.
			$settingsText = preg_replace_callback(
				'~\bdefine\s*\(\s*(["\'])(\w+)\1~',
				function ($matches) {
					return 'define(\'' . bin2hex(random_bytes(16)) . '\'';
				},
				$settingsText,
			);

			// Handle eval errors gracefully in all PHP versions.
			try {
				if ($settingsText !== '' && @eval($settingsText) === false) {
					throw new \ErrorException('eval error');
				}
			} catch (\Throwable $e) {
			} catch (\ErrorException $e) {
			}
		}

		return $boarddir ?? dirname(__DIR__);
	}

	/**
	 * Fetch our current step.
	 *
	 * @return int Current Step.
	 */
	public static function getCurrentStep(): int
	{
		return isset($_GET['step']) ? (int) $_GET['step'] : 0;
	}

	/**
	 * Fetch our current sub-step.
	 *
	 * @return int Current Sub-Step
	 */
	public static function getCurrentSubStep(): int
	{
		return isset($_GET['substep']) ? (int) $_GET['substep'] : 0;
	}

	/**
	 * Set our current sub-step. This is public as our tool needs to update this.
	 *
	 * @param null|int $substep The sub-step we on.  If null is passed, we will auto increment from the current.
	 */
	public static function setCurrentSubStep(?int $substep = null): void
	{
		$_GET['substep'] = $substep ?? (self::getCurrentSubStep() + 1);
	}

	/**
	 * Returns a percent indicating the progression through our sub steps.
	 *
	 * @return int Int representing a percent out of 100 on completion of sub steps.
	 */
	public static function getSubStepProgress(): int
	{
		return (int) (self::getCurrentSubStep() / self::$total_substeps);
	}

	/**
	 * Fetch our current starting position. This is used for loops inside steps.
	 *
	 * @return int Current starting position.
	 */
	public static function getCurrentStart(): int
	{
		return isset($_GET['start']) ? (int) $_GET['start'] : 0;
	}

	/**
	 * Set our current start. This is public as our tool needs to update this.
	 *
	 * @param null|int $substep The starting position we on.  If null is passed, we will auto increment from the current.
	 */
	public static function setCurrentStart(?int $start = null): void
	{
		$_GET['start'] = $start ?? (self::getCurrentStart() + 1);
	}

	/**
	 * Returns a percent indicating the progression through our sub steps.
	 *
	 * @return int Int representing a percent out of 100 on completion of sub steps.
	 */
	public static function getItemsProgress(): int
	{
		return self::$total_items === null || self::$total_items === 0 ? 0 : (int) (self::getCurrentStart() / self::$total_items);
	}

	/**
	 * Determine the language file we want to load.
	 *
	 * This doesn't validate it exists, just that its a sane value to try.
	 *
	 * @return string Language we will load.
	 */
	public static function getRequestedLanguage(): string
	{
		if (isset($_GET['lang_file'])) {
			$_SESSION['lang_file'] = strtr((string) $_GET['lang_file'], './\\:', '____');

			return $_SESSION['lang_file'];
		}

		if (isset($_SESSION['lang_file'])) {
			return $_SESSION['lang_file'];
		}

		return 'en_US';
	}

	/**
	 * Sets the sub template we will use.
	 *
	 * A check is made to ensure that we can call it.
	 *
	 * @param string $tmpl Template to use.
	 */
	public static function setSubTemplate(string $tmpl): void
	{
		if (method_exists(self::$template, $tmpl)) {
			self::$sub_template = $tmpl;
		}
	}

	/**
	 * Safely start up a database for maintenance actions.
	 */
	public static function loadDatabase(): void
	{
		if (!class_exists('SMF\\Db\\APIs\\' . Db::getClass(Config::$db_type))) {
			throw new \Exception(Lang::$txt['error_db_missing']);
		}

		// Make the connection...
		if (empty(Db::$db_connection)) {
			Db::load(['non_fatal' => true]);
		} else {
			// If we've returned here, ping/reconnect to be safe
			Db::$db->ping(Db::$db_connection);
		}

		// Oh dear god!!
		if (Db::$db_connection === null) {
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

			die(Lang::$txt['error_db_connect_settings'] . '<br><br>' . $db_error);
		}

		if (
			Config::$db_type == 'mysql'
			&& isset(Config::$db_character_set)
			&& preg_match('~^\w+$~', Config::$db_character_set) === 1
		) {
			Db::$db->query(
				'',
				'SET NAMES {string:db_character_set}',
				[
					'db_error_skip' => true,
					'db_character_set' => Config::$db_character_set,
				],
			);
		}

		// Load the modSettings data...
		$request = Db::$db->query(
			'',
			'SELECT variable, value
			FROM {db_prefix}settings',
			[
				'db_error_skip' => true,
			],
		);
		Config::$modSettings = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			Config::$modSettings[$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);

		// We have a database, attempt to find our theme data now.
		self::setThemeData();
	}

	/**
	 * Attempts to login an administrator.
	 *
	 * If the account does not have admin_forum permission, they are rejected.
	 *
	 * This will attempt using the SMF 2.0 method if specified.
	 *
	 * @param string $username The admin's username
	 * @param string $password The admin's password.
	 *    As of PHP 8.2, this will not be included in any stack traces.
	 * @param bool $use_old_hashing Whether to allow SMF 2.0 hashing.
	 * @return int The id of the user if they are an admin, 0 otherwise.
	 */
	public static function loginAdmin(
		string $username,
		#[\SensitiveParameter]
		string $password,
		bool $use_old_hashing = false,
	): int {
		$id = 0;

		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, passwd, id_group, additional_groups, lngfile
			FROM {db_prefix}members
			WHERE member_name = {string:member_name}',
			[
				'member_name' => $username,
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			list($id_member, $name, $password, $id_group, $addGroups, $user_language) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$groups = explode(',', $addGroups);
			$groups[] = (int) $id_group;

			foreach ($groups as $k => $v) {
				$groups[$k] = (int) $v;
			}

			$sha_passwd = sha1(strtolower($name) . $_REQUEST['passwrd']);

			// SMF 2.1 + uses bcrypt, SMF 2.0 is sha1.
			if (Security::hashVerifyPassword((!empty($name) ? $name : ''), $_REQUEST['passwrd'], (!empty($password) ? $password : ''))) {
				$id = (int) $id_member;
			} elseif ($use_old_hashing && $password === $sha_passwd) {
				$id = (int) $id_member;
			}

			// We have a valid login.
			if ($id > 0 && !in_array(1, $groups)) {
				$request = Db::$db->query(
					'',
					'SELECT permission
					FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:groups})
						AND permission = {string:admin_forum}',
					[
						'groups' => $groups,
						'admin_forum' => 'admin_forum',
						'db_error_skip' => true,
					],
				);

				if (Db::$db->num_rows($request) == 0) {
					$id = 0;
				}
				Db::$db->free_result($request);
			}
		}

		if ($id > 0 && !empty($user_language)) {
			$_SESSION['lang_file'] = strtr((string) $user_language, './\\:', '____');
		}

		return $id;
	}

	/**
	 * Attempts to login using the database password.
	 *
	 * @param string $password The database password.
	 *    As of PHP 8.2, this will not be included in any stack traces.
	 * @return bool Whether this is valid.
	 */
	public static function loginWithDatabasePassword(
		#[\SensitiveParameter]
		string $password,
	): bool {
		return Config::$db_passwd === $password;
	}

	/**
	 * Returns a string formated for the current time elasped.
	 *
	 * @return string Formatted string.
	 */
	public static function getTimeElapsed(): string
	{
		// How long have we been running this?
		$elapsed = time() - (int) self::$context['started'];
		$mins = (int) ($elapsed / 60);
		$seconds = $elapsed - $mins * 60;

		return Lang::getTxt('mainteannce_time_elasped_ms', ['m' => $mins, 's' => $seconds]);
	}

	/**
	 * Check if we are out of time.  Try to buy some more.
	 * If this is CLI, returns true.
	 *
	 * @return bool Whether we need to exit the script soon.
	 */
	public static function isOutOfTime(): bool
	{
		if (Sapi::isCLI()) {
			if (time() - self::$context['started'] > 1 && !self::$tool->isDebug()) {
				echo '.';
			}

			return false;
		}

		Sapi::setTimeLimit(300);
		Sapi::resetTimeout();

		// Still have time left.
		return !(time() - self::$context['started'] <= 3);
	}

	/**
	 * Sets (and returns) the value of self::$query_string;
	 *
	 * @return string A copy of self::$query_string.
	 */
	public static function setQueryString(): string
	{
		// Always ensure this is updated.
		$_GET['step'] = self::getCurrentStep();

		self::$query_string = http_build_query($_GET, '', ';');

		return self::$query_string;
	}

	/**
	 * Exit the script. This will wrap the templates.
	 *
	 * @param bool $fallthrough If true, we just skip templates and do nothing.
	 * @return never All execution is stopped here.
	 */
	public static function exit(bool $fallthrough = false): void
	{
		// Send character set.
		header('content-type: text/html; charset=' . (Lang::$txt['lang_character_set'] ?? 'UTF-8'));

		// We usually dump our templates out.
		if (!$fallthrough) {
			// The top bit.
			Template::header();
			call_user_func([self::$template, 'upper']);

			// Call the template.
			if (self::$sub_template !== '') {
				self::$context['form_url'] = self::getSelf() . '?step=' . self::getCurrentStep();

				call_user_func([self::$template, self::$sub_template]);
			}

			// Show the footer.
			call_user_func([self::$template, 'lower']);
			Template::footer();
		}

		// Bang - gone!
		die();
	}

	/**
	 * Handle a response for our JavaScript logic.
	 *
	 * This always returns a success header, which is used to handle continues.
	 *
	 * @param mixed $data
	 * @param bool $success Whether the result was successful.
	 */
	public static function jsonResponse(mixed $data, bool $success = true): void
	{
		ob_end_clean();
		header('content-type: text/json; charset=UTF-8');

		echo json_encode([
			'success' => $success,
			'data' => $data,
		]);

		die;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks that the tool we requested is valid.
	 *
	 * @param int $type Tool we are trying to use.
	 * @return bool Whether it is valid.
	 */
	private static function toolIsValid(int $type): bool
	{
		return isset(self::$valid_tools[$type]);
	}

	/**
	 * Set the current step. Tools do not gain access to this and its proteted.
	 * @param null|int $step
	 */
	private static function setCurrentStep(?int $step = null): void
	{
		$_GET['step'] = $step ?? (self::getCurrentStep() + 1);
	}

	/**
	 * Handle parsing the CLI inputs.
	 * Nothing is returned, we push everything into $_REQUEST, which isn't pretty, but we don't handle the input any other way currently.
	 */
	protected static function parseCliArguments(): void
	{
		if (!Sapi::isCLI()) {
			return;
		}

		if (!empty($_SERVER['argv']) && Sapi::isCLI()) {
			for ($i = 1; $i < count($_SERVER['argv']); $i++) {
				if (preg_match('/^--([^=]+)=(.*)/', $_SERVER['argv'][$i], $match)) {
					$_REQUEST[$match[1]] = $match[2];
				}
			}
		}
	}

	/**
	 * Fetch the theme information for the default theme.  If this can't be loaded, we fall back to a guess.
	 */
	protected static function setThemeData(): void
	{
		$themesData = [
			'theme_url' => 'Themes/default',
			'theme_dir' => basename(SMF_SETTINGS_FILE) . '/Themes/default',
			'images_url' => 'Themes/default/images',
		];

		// This only exists if we're on SMF ;)
		if (isset(Config::$modSettings['smfVersion'])) {
			$request = Db::$db->query(
				'',
				'SELECT variable, value
				FROM {db_prefix}themes
				WHERE id_theme = {int:id_theme}
					AND variable IN ({string:theme_url}, {string:theme_dir}, {string:images_url})',
				[
					'id_theme' => 1,
					'theme_url' => 'theme_url',
					'theme_dir' => 'theme_dir',
					'images_url' => 'images_url',
					'db_error_skip' => true,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				self::${$row['variable']} = $row['value'];
			}
			Db::$db->free_result($request);

			if (Sapi::httpsOn()) {
				self::$theme_url = strtr(self::$theme_url, ['http://' => 'https://']);
				self::$images_url = strtr(self::$images_url, ['http://' => 'https://']);
			}
		}
	}
}

?>