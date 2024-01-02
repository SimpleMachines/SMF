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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Moderation\Logs as Modlog;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Dispatcher to show various kinds of logs.
 */
class Logs implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'adminLogs' => 'AdminLogs',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'errorlog';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * These are the logs they can load.
	 *
	 * Format: 'sa' => array('file', 'function', 'disabled' => 'setting_to_check')
	 */
	public static array $subactions = [
		'errorlog' => [
			'',
			'errorlog',
			// At runtime, will be set to empty(Config::$modSettings['enableErrorLogging'])
			'disabled' => 'enableErrorLogging',
		],
		'adminlog' => [
			'',
			'adminlog',
			// At runtime, will be set to empty(Config::$modSettings['adminlog_enabled'])
			'disabled' => 'adminlog_enabled',
		],
		'modlog' => [
			'',
			'modlog',
			// At runtime, will be set to empty(Config::$modSettings['modlog_enabled'])
			'disabled' => 'modlog_enabled',
		],
		'banlog' => [
			'',
			'banlog',
		],
		'spiderlog' => [
			'',
			'spiderlog',
			// At runtime, will be set to empty(Config::$modSettings['spider_mode'])
			'disabled' => 'spider_mode',
		],
		'tasklog' => [
			'',
			'tasklog',
		],
		'settings' => [
			'',
			'settings',
		],
	];

	/**
	 * @var array
	 *
	 * Logs that can be toggled on or off for a nice user experience.
	 *
	 * MOD AUTHORS: If you want to your log to be magically hidden when the
	 * 'pruning' option is off, use the integrate_prune_settings hook to add it
	 * to this list.
	 */
	public static array $prune_toggle = [
		'pruneErrorLog',
		'pruneModLog',
		'pruneBanLog',
		'pruneReportLog',
		'pruneScheduledTaskLog',
		'pruneSpiderHitLog',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * This function decides which log to load.
	 */
	public function execute(): void
	{
		// Set up some tab stuff.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['logs'],
			'help' => '',
			'description' => Lang::$txt['maintain_info'],
			'tabs' => [
				'errorlog' => [
					'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc',
					'description' => sprintf(Lang::$txt['errorlog_desc'], Lang::$txt['remove']),
				],
				'adminlog' => [
					'description' => Lang::$txt['admin_log_desc'],
				],
				'modlog' => [
					'description' => Lang::$txt['moderation_log_desc'],
				],
				'banlog' => [
					'description' => Lang::$txt['ban_log_description'],
				],
				'spiderlog' => [
					'description' => Lang::$txt['spider_log_desc'],
				],
				'tasklog' => [
					'description' => Lang::$txt['scheduled_log_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['log_settings_desc'],
				],
			],
		];

		if (!empty(self::$subactions[$this->subaction][0])) {
			require_once Config::$sourcedir . '/' . self::$subactions[$this->subaction][0];
		}

		$call = method_exists($this, self::$subactions[$this->subaction][1]) ? [$this, self::$subactions[$this->subaction][1]] : Utils::getCallable(self::$subactions[$this->subaction][1]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Hands execution over to ErrorLog::view().
	 */
	public function errorlog()
	{
		ErrorLog::call();
	}

	/**
	 * Hands execution over to Modlog::call().
	 */
	public function adminlog()
	{
		ModLog::call();
	}

	/**
	 * Hands execution over to Modlog::call().
	 */
	public function modlog()
	{
		ModLog::call();
	}

	/**
	 * Hands execution over to Bans::log().
	 */
	public function banlog()
	{
		$_REQUEST['sa'] = 'log';
		Bans::call();
	}

	/**
	 * Hands execution over to SearchEngines::logs().
	 */
	public function spiderlog()
	{
		$_REQUEST['sa'] = 'logs';
		SearchEngines::call();
	}

	/**
	 * Hands execution over to Tasks::log().
	 */
	public function tasklog()
	{
		$_REQUEST['sa'] = 'tasklog';
		Tasks::call();
	}

	/**
	 * Allow to edit the settings on the pruning screen.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Make sure we understand what's going on.
		Lang::load('ManageSettings');

		Utils::$context['page_title'] = Lang::$txt['log_settings'];

		Theme::addInlineJavaScript('
		function togglePruned()
		{
			var newval = $("#pruningOptions").prop("checked");
			$("#' . implode(', #', self::$prune_toggle) . '").closest("dd").toggle(newval);
			$("#setting_' . implode(', #setting_', self::$prune_toggle) . '").closest("dt").toggle(newval);
		};
		togglePruned();
		$("#pruningOptions").click(function() { togglePruned(); });', true);

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Although the UI presents pruningOptions as a checkbox followed by
			// several input fields, we save all that data as a single string.
			$config_vars['pruningOptions'][0] = 'text';

			if (!empty($_POST['pruningOptions'])) {
				$vals = [];

				foreach ($config_vars as $config_var) {
					if (!is_array($config_var) || !in_array($config_var[1], self::$prune_toggle)) {
						continue;
					}

					// Just in case a mod did something stupid...
					if ($config_var[1] === 'pruningOptions') {
						continue;
					}

					$vals[] = max(0, (int) ($_POST[$config_var[1]] ?? 0));
				}

				$_POST['pruningOptions'] = implode(',', $vals);
			} else {
				$_POST['pruningOptions'] = '';
			}

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=logs;sa=settings');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=logs;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['log_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Get the actual values
		if (!empty(Config::$modSettings['pruningOptions'])) {
			foreach (explode(',', Config::$modSettings['pruningOptions']) as $key => $value) {
				Config::$modSettings[self::$prune_toggle[$key]] = $value;
			}
		} else {
			$defaults = array_pad([30, 180, 180, 180, 30, 0], count(self::$prune_toggle), 0);

			foreach (array_combine(self::$prune_toggle, $defaults) as $setting => $default) {
				Config::$modSettings[$setting] = $default;
			}
		}

		ACP::prepareDBSettingContext($config_vars);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the logs area.
	 */
	public static function getConfigVars(): array
	{
		$config_vars = [
			['check', 'modlog_enabled', 'help' => 'modlog'],
			['check', 'adminlog_enabled', 'help' => 'adminlog'],
			['check', 'userlog_enabled', 'help' => 'userlog'],

			// The error log is a wonderful thing.
			['title', 'errorlog', 'force_div_id' => 'errorlog'],
			['desc', 'error_log_desc'],
			['check', 'enableErrorLogging'],
			['check', 'enableErrorQueryLogging'],

			// The 'mark read' log settings.
			['title', 'markread_title', 'force_div_id' => 'markread_title'],
			['desc', 'mark_read_desc'],
			['int', 'mark_read_beyond', 'step' => 1, 'min' => 0, 'max' => 18000, 'subtext' => Lang::$txt['zero_to_disable']],
			['int', 'mark_read_delete_beyond', 'step' => 1, 'min' => 0, 'max' => 18000, 'subtext' => Lang::$txt['zero_to_disable']],
			['int', 'mark_read_max_users', 'step' => 1, 'min' => 0, 'max' => 20000, 'subtext' => Lang::$txt['zero_to_disable']],

			// Even do the pruning?
			['title', 'pruning_title', 'force_div_id' => 'pruning_title'],
			['desc', 'pruning_desc'],

			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => ['check', 'pruningOptions'],
			'',

			// Various logs that could be pruned.

			// Error log.
			['int', 'pruneErrorLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],

			// Moderation log.
			['int', 'pruneModLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],

			// Ban hit log.
			['int', 'pruneBanLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],

			// Report to moderator log.
			['int', 'pruneReportLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],

			// Log of the scheduled tasks and how long they ran.
			['int', 'pruneScheduledTaskLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],

			// Log recording when search engines have crawled the forum.
			['int', 'pruneSpiderHitLog', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_to_disable']],
		];

		// MOD AUTHORS: If you want to add your own logs, use this hook.
		IntegrationHook::call('integrate_prune_settings', [&$config_vars, &self::$prune_toggle, false]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function adminLogs($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		foreach (self::$subactions as &$subaction) {
			if (isset($subaction['disabled'])) {
				$subaction['disabled'] = empty(Config::$modSettings[$subaction['disabled']]);
			}
		}

		IntegrationHook::call('integrate_manage_logs', [&self::$subactions]);

		// By default, error log should be shown in descending order.
		if (!isset($_REQUEST['sa'])) {
			$_REQUEST['desc'] = true;
		}

		$this->subaction = isset($_REQUEST['sa'], self::$subactions[$_REQUEST['sa']])   && empty(self::$subactions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : 'errorlog';
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Logs::exportStatic')) {
	Logs::exportStatic();
}

?>