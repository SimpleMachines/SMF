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

namespace SMF\Maintenance;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Maintenance\Tools\ToolsInterface;
use SMF\Sapi;
use SMF\Security;
use SMF\Themes\default\MaintenanceTemplate;
use SMF\Utils;

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
	 */
	public const PHP_MIN_VERSION = '8.4.1';

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
	 * List of languages we have for ths tool.
	 */
	public static array $languages = [];

	/**
	 * @var array
	 *
	 * List of warnings to display on the page.
	 */
	public static array $warnings = [];

	/**
	 * @var array
	 *
	 * List of errors to display on the page.
	 */
	public static array $errors = [];

	/**
	 * @var string
	 *
	 * Fatal error, we display this error message and do not process anything
	 * further until the error has been corrected.
	 *
	 * This differs from $errors in that we should not continue operations.
	 */
	public static string $fatal_error = '';

	/**
	 * @var string
	 *
	 * Our theme url.
	 */
	public static string $theme_url = 'Themes/default';

	/**
	 * @var string
	 *
	 * Path to the theme directory.
	 */
	public static string $theme_dir = '';

	/**
	 * @var string
	 *
	 * Our images url.
	 */
	public static string $images_url = 'Themes/default/images';

	/**
	 * @var string
	 *
	 * String to be appended to our urls.
	 *
	 * Upgrade logic will automatically maintain this.
	 */
	public static string $query_string = '';

	/**
	 * @var \SMF\Maintenance\Tools\ToolsInterface|\SMF\Maintenance\Tools\Upgrade|\SMF\Maintenance\Tools\Install
	 *
	 * Object containing the tool we are working with.
	 */
	public static ToolsInterface $tool;

	/**
	 * @var \SMF\Themes\default\MaintenanceTemplate
	 *
	 * Object containing the tools template we are working with.
	 *
	 * Must be a class that extends the MaintenanceTemplate class.
	 */
	public static MaintenanceTemplate $template;

	/**
	 * @var string
	 *
	 * Sub template to call during output.
	 */
	public static string $sub_template = '';

	/**
	 * @var int
	 *
	 * How far we have progressed.
	 */
	public static int $overall_percent = 0;

	/**
	 * @var bool
	 *
	 * Disable security functions such as login checks.
	 */
	public static bool $disable_security = false;

	/**
	 * @var null|int
	 *
	 * Total number of sub steps.
	 *
	 * Null when we do not have any sub steps, int greater than 0 otherwise.
	 *
	 * When this is set, the sub-step progress bar is shown. The current step is
	 * obtained from getCurrentSubStep().
	 */
	public static ?int $total_substeps = null;

	/**
	 * @var string
	 *
	 * Name of the sub step, used on templates and outputting progress.
	 */
	public static string $substep_name = '';

	/**
	 * @var null|int
	 *
	 * Total number of sub items.
	 *
	 * Null when we do not have any items, int greater than 0 otherwise.
	 *
	 * When this issset, the items progress bar is shown. The current item is
	 * obtained from getCurrentStart().
	 */
	public static ?int $total_items = null;

	/**
	 * @var string
	 *
	 * Only filled in during debugging, name of the item name.
	 */
	public static string $item_name = '';

	/**
	 * @var int
	 *
	 *
	 */
	public static int $script_start = 0;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * List of valid tools.
	 *
	 * The Special tool is a self contained logic, while other tools are located
	 * in the Tools directory.
	 */
	private static array $valid_tools = [
		self::INSTALL => 'Install',
		self::UPGRADE => 'Upgrade',
		self::CONVERT => 'Convert',
		self::TOOL => 'Tool',
		self::SPECIAL => 'Special',
	];

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		Security::frameOptionsHeader('SAMEORIGIN');
		self::$theme_dir = self::getBaseDir() . '/Themes/default';

		// This might be overwritten by the tool, but we need a default value.
		Utils::$context['started'] = (int) TIME_START;
		self::$script_start = (int) TIME_START;

		// No integration hooks allowed during maintenance.
		IntegrationHook::$enabled = false;
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

		$tool_class = __NAMESPACE__ . '\\Tools\\' . self::$valid_tools[$type];
		self::$tool = new $tool_class();

		$template_class = '\\SMF\\Themes\\default\\' . self::$valid_tools[$type] . 'Template';
		self::$template = new $template_class();

		// This is really quite simple; if ?delete is on the URL, delete the
		// tool's entry point file (e.g. install.php for the installer).
		if (isset($_GET['delete'])) {
			self::$tool->deleteTool();

			exit;
		}

		foreach (self::$tool->getSteps() as $num => $step) {
			// Skip steps we have done, but count their progress.
			if ($num < self::getCurrentStep()) {
				self::$overall_percent += (int) $step->getProgress();
				continue;
			}

			if ($num === 0) {
				self::$tool->logProgress(date_create()->format('c'), reset: true);
			}

			// Inform the tool about which step we are performing.
			self::$tool->setStep($step);

			// The current weight of this step in terms of overall progress.
			Utils::$context['step_weight'] = $step->getProgress();

			// Make sure we reset the skip button.
			Utils::$context['skip'] = false;

			// What should we call for this step?
			if (($callable = Utils::getCallable($step->getFunction(), true)) === false) {
				$callable = Utils::getCallable([self::$tool, $step->getFunction()]);
			}

			// Call the step and if it returns false that means pause!
			if (\is_callable($callable) && \call_user_func($callable) === false) {
				break;
			}

			// Time to move on.
			self::setCurrentStep();
			self::setCurrentSubStep(0);
			self::setCurrentStart(0);

			// No warnings pass on.
			Utils::$context['warning'] = '';

			self::$overall_percent += (int) $step->getProgress();
		}

		// Last chance to set our template.
		if (
			\array_key_exists(self::getCurrentStep(), self::$tool->getSteps())
			&& self::$sub_template === ''
		) {
			self::$sub_template = self::$tool->getSteps()[self::getCurrentStep()]->getTemplate();
		}

		// Make a final call before we are done..
		self::$tool->preExit();

		if (
			Sapi::isCLI()
			&& !empty(self::$tool->script_file)
			&& self::getCurrentStep() > \count(self::$tool->getSteps())
		) {
			echo Lang::getTxt('cli_please_delete_file', ['file' => self::$tool->script_file], file: 'Maintenance') . PHP_EOL;
		}

		self::exit(Sapi::isCLI());
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * See if we think they have already installed SMF?
	 *
	 * @return bool Whether we believe SMF has been installed.
	 */
	public static function isInstalled(): bool
	{
		$settings_defs = Config::getSettingsDefs();

		foreach (['image_proxy_secret', 'db_passwd', 'boardurl'] as $var) {
			if (Config::${$var} === $settings_defs[$var]['default']) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Is this a json request?
	 *
	 * @return bool Whether we believe we are working with a json response.
	 */
	public static function isJson(): bool
	{
		return isset($_GET['json']);
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
	 * Get the forum's base directory.
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
		if (!\in_array(SMF_SETTINGS_FILE, get_included_files())) {
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
				'__DIR__' => var_export(\dirname(SMF_SETTINGS_FILE), true),
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

		return $boarddir ?? \dirname(__DIR__);
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
		return empty(self::$total_substeps) ? 100 : (int) (self::getCurrentSubStep() / self::$total_substeps);
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
			throw new \Exception(Lang::getTxt('error_sourcefile_missing', ['file' => 'Db/APIs/' . Db::getClass(Config::$db_type) . '.php'], file: 'Maintenance'));
		}

		// Make the connection...
		if (empty(Db::$db) || !(Db::$db instanceof Db)) {
			Db::load(['non_fatal' => true]);
		}

		// Oh dear god!!
		if (Db::$db->connection === null) {
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

			throw new \Exception(Lang::getTxt('error_db_connect_settings', file: 'Maintenance') . '<br><br>' . $db_error);
		}
	}

	/**
	 * Loads the modSettings data.
	 */
	public static function loadModSettings(): void
	{
		// Load the modSettings data...
		$request = Db::$db->query(
			'SELECT variable, value
			FROM {db_prefix}settings',
			[
				'db_error_skip' => true,
			],
		);

		if ($request === false) {
			throw new \Exception(Lang::getTxt('error_db_connect_settings', file: 'Maintenance'));
		}

		Config::$modSettings = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			Config::$modSettings[$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);
	}

	/**
	 * Fetch the theme information for the default theme.
	 *
	 * If this can't be loaded, we fall back to a guess.
	 */
	public static function setThemeData(): void
	{
		// This only exists if we're on SMF ;)
		if (isset(Config::$modSettings['smfVersion'])) {
			$request = Db::$db->query(
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
				if (!empty($row['value'])) {
					self::${$row['variable']} = $row['value'];
				}
			}
			Db::$db->free_result($request);

			if (Sapi::httpsOn()) {
				self::$theme_url = strtr(self::$theme_url, ['http://' => 'https://']);
				self::$images_url = strtr(self::$images_url, ['http://' => 'https://']);
			}
		}

		if (!isset(Config::$modSettings['theme_url'])) {
			Config::$modSettings['theme_dir'] = empty(self::$theme_dir) ? Config::$boarddir . '/Themes/default' : self::$theme_dir;
			Config::$modSettings['theme_url'] = empty(self::$theme_url) ? 'Themes/default' : self::$theme_url;
			Config::$modSettings['images_url'] = empty(self::$images_url) ? 'Themes/default/images' : self::$images_url;
		}
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
			'SELECT id_member, member_name, passwd, id_group, additional_groups, lngfile
			FROM {db_prefix}members
			WHERE member_name = {string:member_name}',
			[
				'member_name' => $username,
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			$row = Db::$db->fetch_row($request);
		}

		Db::$db->free_result($request);

		if (!empty($row)) {
			list($id_member, $name, $passwd, $id_group, $additional_groups, $user_language) = $row;

			$groups = explode(',', $additional_groups);
			$groups[] = (int) $id_group;

			foreach ($groups as $k => $v) {
				$groups[$k] = (int) $v;
			}

			if (
				// SMF 3.0+
				Security::hashVerifyPassword($password, $passwd)
				// SMF 2.1 prepended the username to the password.
				|| Security::hashVerifyPassword(Utils::strtolower($name) . $password, $passwd)
				// SMF 2.0 used sha1
				|| ($use_old_hashing && hash_equals($passwd, sha1(strtolower($name) . $password)))
			) {
				$id = (int) $id_member;
			}

			// We have a valid login, but are they really an admin?
			if ($id > 0 && !\in_array(1, $groups)) {
				$request = Db::$db->query(
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
		return hash_equals(Config::$db_passwd, $password);
	}

	/**
	 * Returns a string formated for the current time elasped.
	 *
	 * @return string Formatted string.
	 */
	public static function getTimeElapsed(): string
	{
		$duration = (new \DateTime('@' . Utils::$context['started']))->diff(new \DateTime());

		if ((int) $duration->format('%a') > 0) {
			return \strval((int) $duration->format('%h') + ((int) $duration->format('%a') * 24)) . $duration->format(':%I:%S');
		}

		if ((int) $duration->format('%h') > 0) {
			return $duration->format('%h:%I:%S');
		}

		return $duration->format('%i:%S');
	}

	/**
	 * Check if we are out of time, and try to buy some more.
	 *
	 * If this is CLI, always returns false.
	 *
	 * @return bool Whether we need to exit the script soon.
	 */
	public static function isOutOfTime(): bool
	{
		if (Sapi::isCLI()) {
			if (time() - Utils::$context['started'] > 1 && !self::$tool->isDebug()) {
				echo '.';
			}

			return false;
		}

		Sapi::setTimeLimit(300);
		Sapi::resetTimeout();

		// Still have time left.
		return !(time() - self::$script_start <= 3);
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
		// On the command line there is no template to render, so everything the
		// tool wanted to tell us has nowhere to go: a scripted install that died
		// on step three looks exactly like one that finished. Put the problems on
		// stderr and leave a non-zero status behind instead.
		//
		// A step that simply needs more input sets neither of these, so pausing
		// part way through is still a success -- the installer is meant to be
		// called more than once.
		if ($fallthrough && Sapi::isCLI()) {
			foreach (self::$warnings as $warning) {
				fwrite(STDERR, 'warning: ' . self::plainText($warning) . "\n");
			}

			$problems = self::$errors;

			if (self::$fatal_error !== '') {
				array_unshift($problems, self::$fatal_error);
			}

			if ($problems !== []) {
				foreach ($problems as $problem) {
					fwrite(STDERR, 'error: ' . self::plainText($problem) . "\n");
				}

				exit(1);
			}

			// Nothing went wrong, but we may not be finished either: a step can
			// stop because it wanted input it was not given. Say which one, so
			// a script that has to be run more than once can tell where it got
			// to. The step numbers its own id from one, which is what every
			// other line of output uses.
			//
			// The last step is excluded on purpose. Tools end by returning false
			// from it so that the web flow stops and renders its "all done"
			// template, which means reaching it is success, not a pause.
			$steps = isset(self::$tool) ? self::$tool->getSteps() : [];

			if (isset($steps[self::getCurrentStep()]) && self::getCurrentStep() < \count($steps) - 1) {
				$stopped = $steps[self::getCurrentStep()];

				fwrite(
					STDERR,
					'stopped at step ' . $stopped->getId()
					. ' of ' . \count($steps)
					. ' (' . $stopped->getName() . ")\n",
				);
			}
		}

		// We usually dump our templates out.
		if (!$fallthrough) {
			// Send character set.
			header('content-type: text/html; charset=UTF-8');

			// The top bit.
			\call_user_func([self::$template, 'header']);

			if (method_exists(self::$template, 'upper')) {
				\call_user_func([self::$template, 'upper']);
			}

			// Call the template.
			if (self::$sub_template !== '') {
				Utils::$context['form_url'] = self::getSelf() . '?step=' . self::getCurrentStep();

				\call_user_func([self::$template, self::$sub_template]);
			}

			// Show the footer.
			if (method_exists(self::$template, 'lower')) {
				\call_user_func([self::$template, 'lower']);
			}

			\call_user_func([self::$template, 'footer']);
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
		if (Sapi::isCLI()) {
			return;
		}

		if (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('content-type: text/json; charset=UTF-8');

		// TODO: Improve this, move debug to the root.
		$debug_data = [];

		if (Maintenance::$tool->isDebug()) {
			$debug_data = $data['debug'] ?? [];
		}
		unset($data['debug']);

		echo json_encode([
			'success' => $success,
			'data' => $data,
			'debug' => $debug_data,
		]);

		die;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Handle parsing the CLI inputs.
	 *
	 * We push everything into $_REQUEST, which isn't pretty, but we don't
	 * handle the input any other way currently.
	 */
	protected static function parseCliArguments(): void
	{
		if (!Sapi::isCLI()) {
			return;
		}

		if (!empty($_SERVER['argv']) && Sapi::isCLI()) {
			for ($i = 1; $i < \count($_SERVER['argv']); $i++) {
				if (preg_match('/^--([^=\s]+)(?:=(.*))?/', $_SERVER['argv'][$i], $match)) {
					$_POST[$match[1]] = $match[2] ?? true;
				}
			}
		}
	}

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
	 * Set the current step.
	 *
	 * Tools do not gain access to this and its protected.
	 *
	 * @param null|int $step
	 */
	private static function setCurrentStep(?int $step = null): void
	{
		$_GET['step'] = $step ?? (self::getCurrentStep() + 1);
	}

	/**
	 * Flattens one of our messages into something worth reading in a terminal.
	 *
	 * The steps build these for a browser, so they arrive carrying markup: the
	 * database errors in particular wrap the driver's own message in a div.
	 *
	 * @param string $message The message, as the step wrote it.
	 * @return string The same message, without the markup.
	 */
	private static function plainText(string $message): string
	{
		$message = preg_replace('~<br\s*/?>~i', "\n", $message) ?? $message;

		return trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
}
