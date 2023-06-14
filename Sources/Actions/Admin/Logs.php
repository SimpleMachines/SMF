<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Admin;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\Lang;
use SMF\Menu;

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
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'AdminLogs',
		),
	);

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
	public static array $subactions = array(
		'errorlog' => array(
			'ManageErrors.php',
			'ViewErrorLog',
			// At runtime, will be set to empty(Config::$modSettings['enableErrorLogging'])
			'disabled' => 'enableErrorLogging',
		),
		'adminlog' => array(
			'Modlog.php',
			'ViewModlog',
			// At runtime, will be set to empty(Config::$modSettings['adminlog_enabled'])
			'disabled' => 'adminlog_enabled',
		),
		'modlog' => array(
			'Modlog.php',
			'ViewModlog',
			// At runtime, will be set to empty(Config::$modSettings['modlog_enabled'])
			'disabled' => 'modlog_enabled',
		),
		'banlog' => array(
			'ManageBans.php',
			'BanLog',
		),
		'spiderlog' => array(
			'ManageSearchEngines.php',
			'SpiderLogs',
			// At runtime, will be set to empty(Config::$modSettings['spider_mode'])
			'disabled' => 'spider_mode',
		),
		'tasklog' => array(
			'ManageScheduledTasks.php',
			'TaskLog',
		),
		'settings' => array(
			'Actions/Admin/ManageSettings.php',
			'ModifyLogSettings',
		),
	);

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
		Menu::$loaded['admin']->tab_data = array(
			'title' => Lang::$txt['logs'],
			'help' => '',
			'description' => Lang::$txt['maintain_info'],
			'tabs' => array(
				'errorlog' => array(
					'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc',
					'description' => sprintf(Lang::$txt['errorlog_desc'], Lang::$txt['remove']),
				),
				'adminlog' => array(
					'description' => Lang::$txt['admin_log_desc'],
				),
				'modlog' => array(
					'description' => Lang::$txt['moderation_log_desc'],
				),
				'banlog' => array(
					'description' => Lang::$txt['ban_log_description'],
				),
				'spiderlog' => array(
					'description' => Lang::$txt['spider_log_desc'],
				),
				'tasklog' => array(
					'description' => Lang::$txt['scheduled_log_desc'],
				),
				'settings' => array(
					'description' => Lang::$txt['log_settings_desc'],
				),
			),
		);

		require_once(Config::$sourcedir . '/' . self::$subactions[$this->subaction][0]);

		call_helper(self::$subactions[$this->subaction][1]);
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
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		foreach (self::$subactions as &$subaction)
		{
			if (isset($subaction['disabled']))
				$subaction['disabled'] = empty(Config::$modSettings[$subaction['disabled']]);
		}

		call_integration_hook('integrate_manage_logs', array(&self::$subactions));

		// By default, error log should be shown in descending order.
		if (!isset($_REQUEST['sa']))
			$_REQUEST['desc'] = true;

		$this->subaction = isset($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]) && empty(self::$subactions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : 'errorlog';

	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Logs::exportStatic'))
	Logs::exportStatic();

?>