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
	 * Fatal error, we display this error message and do not process anything further until the error has been corrrected.
	 * This differs from $errors in that we should not continue operations.
	 *
	 * @var string
	 */
	public static string $fatal_error = '';


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

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		Security::frameOptionsHeader('SAMEORIGIN');
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

		/** @var \SMF\Maintenance\ToolsInterface&\SMF\Maintenance\ToolsBase $tool_class */
		$tool_class = '\\SMF\\Maintenance\\Tools\\' . self::$valid_tools[$type];

		require_once Config::$sourcedir . '/Maintenance/Tools/' . self::$valid_tools[$type] . '.php';
		self::$tool = new $tool_class();


		/** @var \SMF\Maintenance\TemplateInterface $templ_class */
		$templ_class = '\\SMF\\Maintenance\\Template\\' . self::$valid_tools[$type];

		require_once Config::$sourcedir . '/Maintenance/Template/' . self::$valid_tools[$type] . '.php';
		self::$template = new $templ_class();

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
				if (method_exists(self::$tool, $step->getFunction()) && self::$tool->{$step->getFunction()}() === false) {
					break;
				}

				if (method_exists(self::$tool, $step->getFunction())) {
					self::setCurrentStep();
				}

				// No warnings pass on.
				self::$context['warning'] = '';
			}
			self::$overall_percent += (int) $step->getProgress();
		}

		// Last chance to set our template.
		if (self::$sub_template === '') {
			self::$sub_template = self::$tool->getSteps()[self::getCurrentStep()]->getFunction();
		}

		self::exit();
	}

	/**
	 * The lowest PHP version we support.
	 * This can not be a int, as we have 2 decimals.
	 *
	 * @return string The lowest PHP version we support.
	 */
	public static function getRequiredVersionForPHP(): string
	{
		return '8.0.1';
	}

	/**
	 * See if we think they have already installed it?
	 *
	 * @return bool True if we believe SMF has been installed, false otherwise.
	 */
	public static function isInstalled(): bool
	{
		return !(Config::$image_proxy_secret === 'smfisawesome' && Config::$db_passwd === '' && Config::$boardurl === 'http://127.0.0.1/smf');
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
	 * Determine the language file we want to load. This doesn't validate it exists, just that its a sane value to try.
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
	 * Set's the sub template we will use. A check is made to ensure that we can call it.
	 *
	 * @param string $tmpl Template to use.
	 */
	public static function setSubTemplate(string $tmpl): void
	{
		if (method_exists(self::$template, $tmpl)) {
			self::$sub_template = $tmpl;
		}
	}

	/******************
	 * Internal methods
	 ******************/


	/**
	 * Checks that the tool we requested is valid.
	 *
	 * @param int $type Tool we are trying to use.
	 * @return bool True if it is valid, false otherwise.
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
	 * Exit the script. This will wrap the templates.
	 *
	 * @param bool $fallThrough If true, we just skip templates and do nothing.
	 * @return never All execution is stopped here.
	 */
	private static function exit(bool $fallThrough = false): void
	{
		// Send character set.
		header('content-type: text/html; charset=' . (Lang::$txt['lang_character_set'] ?? 'UTF-8'));

		// We usually dump our templates out.
		if (!$fallThrough) {
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
}

?>