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

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\BackwardCompatibility;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\ProvidesSubActionInterface;
use SMF\ProvidesSubActionTrait;
use SMF\SecurityToken;
use SMF\TaskRunner;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class concerns itself with scheduled tasks management.
 */
class Tasks implements ActionInterface, ProvidesSubActionInterface
{
	use ActionTrait;
	use ProvidesSubActionTrait;
	use BackwardCompatibility;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		IntegrationHook::call('integrate_manage_scheduled_tasks', [&$this->sub_actions]);

		$this->callSubAction($_REQUEST['sa'] ?? null);
	}

	/**
	 * List all the scheduled task in place on the forum.
	 */
	public function tasks(): void
	{
		// Mama, setup the template first - cause it's like the most important bit, like pickle in a sandwich.
		// ... ironically I don't like pickle. </grudge>
		Utils::$context['sub_template'] = 'view_scheduled_tasks';
		Utils::$context['page_title'] = Lang::$txt['maintain_tasks'];

		// Saving changes?
		if (isset($_REQUEST['save'], $_POST['enable_task'])) {
			User::$me->checkSession();

			// Enable and disable as required.
			$enablers = [0];

			foreach ($_POST['enable_task'] as $id => $enabled) {
				if ($enabled) {
					$enablers[] = (int) $id;
				}
			}

			// Do the update!
			Db::$db->query(
				'',
				'UPDATE {db_prefix}scheduled_tasks
				SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
				[
					'id_task_enable' => $enablers,
				],
			);

			// Update the "allow_expire_redirect" setting...
			$request = Db::$db->query(
				'',
				'SELECT disabled
				FROM {db_prefix}scheduled_tasks
				WHERE task = {string:remove_redirect}',
				[
					'remove_redirect' => 'remove_topic_redirect',
				],
			);
			$row = Db::$db->fetch_assoc($request);
			$task_disabled = !empty($row['disabled']) ? 0 : 1;
			Db::$db->free_result($request);

			Config::updateModSettings(['allow_expire_redirect' => $task_disabled]);

			// Pop along...
			TaskRunner::calculateNextTrigger();
		}

		// Want to run any of the tasks?
		if (isset($_REQUEST['run'], $_POST['run_task'])) {
			// Lets figure out which ones they want to run.
			$tasks = [];

			foreach ($_POST['run_task'] as $task => $dummy) {
				$tasks[] = (int) $task;
			}

			// Run them.
			(new TaskRunner())->runScheduledTasks($tasks);

			// If we had any errors, push them to session so we can pick them up next time to tell the user.
			if (!empty(Utils::$context['scheduled_errors'])) {
				$_SESSION['st_error'] = Utils::$context['scheduled_errors'];
			}

			Utils::redirectexit('action=admin;area=scheduledtasks;done');
		}

		if (isset($_SESSION['st_error'])) {
			Utils::$context['scheduled_errors'] = $_SESSION['st_error'];
			unset($_SESSION['st_error']);
		}

		$listOptions = [
			'id' => 'scheduled_tasks',
			'title' => Lang::$txt['maintain_tasks'],
			'base_href' => Config::$scripturl . '?action=admin;area=scheduledtasks',
			'get_items' => [
				'function' => __CLASS__ . '::list_getScheduledTasks',
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_name'],
						'style' => 'width: 40%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a href="' . Config::$scripturl . '?action=admin;area=scheduledtasks;sa=taskedit;tid=%1$d">%2$s</a><br><span class="smalltext">%3$s</span>',
							'params' => [
								'id' => false,
								'name' => false,
								'desc' => false,
							],
						],
					],
				],
				'next_due' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_next_time'],
					],
					'data' => [
						'db' => 'next_time',
						'class' => 'smalltext',
					],
				],
				'regularity' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_regularity'],
					],
					'data' => [
						'db' => 'regularity',
						'class' => 'smalltext',
					],
				],
				'run_now' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_run_now'],
						'style' => 'width: 12%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' =>
								'<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
				'enabled' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_enabled'],
						'style' => 'width: 6%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' =>
								'<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0"><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s>',
							'params' => [
								'id' => false,
								'checked_state' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=scheduledtasks',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="save" value="' . Lang::$txt['scheduled_tasks_save_changes'] . '" class="button">
						<input type="submit" name="run" value="' . Lang::$txt['scheduled_tasks_run_now'] . '" class="button">',
				],
				[
					'position' => 'after_title',
					'value' => Lang::$txt['scheduled_tasks_time_offset'],
				],
			],
		];

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'view_scheduled_tasks';

		Utils::$context['tasks_were_run'] = isset($_GET['done']);
	}

	/**
	 * Method for editing a task.
	 */
	public function edit(): void
	{
		// Just set up some lovely context stuff.
		Menu::$loaded['admin']['current_subsection'] = 'tasks';
		Utils::$context['sub_template'] = 'edit_scheduled_tasks';
		Utils::$context['page_title'] = Lang::$txt['scheduled_task_edit'];
		Utils::$context['server_time'] = Time::create('now', new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false);

		// Cleaning...
		if (!isset($_GET['tid'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$_GET['tid'] = (int) $_GET['tid'];

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-st');

			// Sanitize the offset. Prepend a '0' so that ':05' works as expected.
			list($h, $m) = array_pad(preg_split('/:/', preg_replace('/[^\d:]/', '', '0' . $_POST['offset']), -1, PREG_SPLIT_NO_EMPTY), 2, '00');

			// Now the offset is easy; easy peasy - except we need to offset by a few hours,
			// take account of DST at this time of year, and... okay, not so easy.
			$when = new \DateTime('today ' . sprintf('%1$02d:%2$02d', $h % 24, $m % 60) . ' ' . Config::$modSettings['default_timezone']);

			// Walk back until we find a month that wasn't using DST.
			// No sane environment will ever reach 1900, but just in case...
			while ($when->format('I') && $when->format('Y') > 1900) {
				$when->modify('-1 month');
			}

			$offset = $when->getTimestamp() % 86400;

			// The other time bits are simple!
			$interval = max((int) $_POST['regularity'], 1);
			$unit = in_array(substr($_POST['unit'], 0, 1), ['m', 'h', 'd', 'w']) ? substr($_POST['unit'], 0, 1) : 'd';

			// Don't allow one minute intervals.
			if ($interval == 1 && $unit == 'm') {
				$interval = 2;
			}

			// Is it disabled?
			$disabled = !isset($_POST['enabled']) ? 1 : 0;

			// Do the update!
			Db::$db->query(
				'',
				'UPDATE {db_prefix}scheduled_tasks
				SET disabled = {int:disabled}, time_offset = {int:time_offset}, time_unit = {string:time_unit},
					time_regularity = {int:time_regularity}
				WHERE id_task = {int:id_task}',
				[
					'disabled' => $disabled,
					'time_offset' => $offset,
					'time_regularity' => $interval,
					'id_task' => $_GET['tid'],
					'time_unit' => $unit,
				],
			);

			// Check the next event.
			TaskRunner::calculateNextTrigger((string) $_GET['tid'], true);

			// Return to the main list.
			Utils::redirectexit('action=admin;area=scheduledtasks');
		}

		// Load the task, understand? Que? Que?
		$request = Db::$db->query(
			'',
			'SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
			FROM {db_prefix}scheduled_tasks
			WHERE id_task = {int:id_task}',
			[
				'id_task' => $_GET['tid'],
			],
		);

		// Should never, ever, happen!
		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_access', false);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['task'] = [
				'id' => $row['id_task'],
				'function' => $row['task'],
				'name' => Lang::$txt['scheduled_task_' . $row['task']] ?? $row['task'],
				'desc' => Lang::getTxt('scheduled_task_desc_' . $row['task'], ['scripturl' => Config::$scripturl]),
				'next_time' => $row['disabled'] ? Lang::$txt['scheduled_tasks_na'] : Time::create($row['next_time'] == 0 ? 'now' : '@' . $row['next_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(),
				'disabled' => $row['disabled'],
				'offset' => $row['time_offset'],
				'regularity' => $row['time_regularity'],
				'offset_formatted' => date('H:i', (int) $row['time_offset']),
				'unit' => $row['time_unit'],
			];
		}
		Db::$db->free_result($request);

		SecurityToken::create('admin-st');
	}

	/**
	 * Show the log of all tasks that have taken place.
	 */
	public function log(): void
	{
		// Lets load the language just incase we are outside the Scheduled area.
		Lang::load('ManageScheduledTasks');

		// Empty the log?
		if (!empty($_POST['removeAll'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-tl');

			Db::$db->query(
				'truncate_table',
				'TRUNCATE {db_prefix}log_scheduled_tasks',
				[
				],
			);
		}

		// Setup the list.
		$listOptions = [
			'id' => 'task_log',
			'items_per_page' => 30,
			'title' => Lang::$txt['scheduled_log'],
			'no_items_label' => Lang::$txt['scheduled_log_empty'],
			'base_href' => Utils::$context['admin_area'] == 'scheduledtasks' ? Config::$scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : Config::$scripturl . '?action=admin;area=logs;sa=tasklog',
			'default_sort_col' => 'date',
			'get_items' => [
				'function' => __CLASS__ . '::list_getTaskLogEntries',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumTaskLogEntries',
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['scheduled_tasks_name'],
					],
					'data' => [
						'db' => 'name',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['scheduled_log_time_run'],
					],
					'data' => [
						'function' => function ($rowData) {
							return Time::create('@' . $rowData['time_run'])->format();
						},
					],
					'sort' => [
						'default' => 'lst.id_log DESC',
						'reverse' => 'lst.id_log',
					],
				],
				'time_taken' => [
					'header' => [
						'value' => Lang::$txt['scheduled_log_time_taken'],
					],
					'data' => [
						'getTxt' => [
							'format' => 'scheduled_log_time_taken_seconds',
							'params' => [
								0 => [
									'column' => 'time_taken',
									'htmlspecialchars' => false,
								],
							],
						],
					],
					'sort' => [
						'default' => 'lst.time_taken',
						'reverse' => 'lst.time_taken DESC',
					],
				],
			],
			'form' => [
				'href' => Utils::$context['admin_area'] == 'scheduledtasks' ? Config::$scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : Config::$scripturl . '?action=admin;area=logs;sa=tasklog',
				'token' => 'admin-tl',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="removeAll" value="' . Lang::$txt['scheduled_log_empty_log'] . '" data-confirm="' . Lang::$txt['scheduled_log_empty_log_confirm'] . '" class="button you_sure">',
				],
				[
					'position' => 'after_title',
					'value' => Lang::$txt['scheduled_tasks_time_offset'],
				],
			],
		];

		SecurityToken::create('admin-tl');

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'task_log';

		// Make it all look spiffy.
		Menu::$loaded['admin']['current_subsection'] = 'tasklog';
		Utils::$context['page_title'] = Lang::$txt['scheduled_log'];
	}

	/**
	 * This handles settings related to scheduled tasks
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Set up the template.
		Utils::$context['page_title'] = Lang::$txt['scheduled_tasks_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=scheduledtasks;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['scheduled_tasks_settings'];

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			$save_vars = $config_vars;

			IntegrationHook::call('integrate_save_scheduled_tasks_settings', [&$save_vars]);

			ACP::saveDBSettings($save_vars);

			$_SESSION['adm-save'] = true;

			Utils::redirectexit('action=admin;area=scheduledtasks;sa=settings');
		}

		ACP::prepareDBSettingContext($config_vars);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the scheduled tasks area.
	 */
	public static function getConfigVars(): array
	{
		Lang::load('Help+ManageScheduledTasks');

		$config_vars = [
			['check', 'cron_is_real_cron', 'subtext' => Lang::$txt['cron_is_real_cron_desc'], 'help' => 'cron_is_real_cron'],
		];

		IntegrationHook::call('integrate_scheduled_tasks_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Callback function for SMF\ItemList() in $this->tasks().
	 *
	 * @param int $start The item to start with (not used here)
	 * @param int $items_per_page The number of items to display per page (not used here)
	 * @param string $sort A string indicating how to sort things (not used here)
	 * @return array An array of information about available scheduled tasks
	 */
	public static function list_getScheduledTasks(int $start, int $items_per_page, string $sort): array
	{
		$known_tasks = [];

		$request = Db::$db->query(
			'',
			'SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
			FROM {db_prefix}scheduled_tasks',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Find the next for regularity - don't offset as it's always server time!
			$offset = Lang::getTxt('scheduled_task_reg_starting', ['time' => date('H:i', (int) $row['time_offset'])]);

			$repeating = Lang::getTxt('scheduled_task_reg_repeating', $row);

			$known_tasks[] = [
				'id' => $row['id_task'],
				'function' => $row['task'],
				'name' => Lang::$txt['scheduled_task_' . $row['task']] ?? $row['task'],
				'desc' => Lang::getTxt('scheduled_task_desc_' . $row['task'], ['scripturl' => Config::$scripturl]),
				'next_time' => $row['disabled'] ? Lang::$txt['scheduled_tasks_na'] : Time::create($row['next_time'] == 0 ? 'now' : '@' . $row['next_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(),
				'disabled' => $row['disabled'],
				'checked_state' => $row['disabled'] ? '' : 'checked',
				'regularity' => $offset . ', ' . $repeating,
			];
		}
		Db::$db->free_result($request);

		return $known_tasks;
	}

	/**
	 * Callback function for SMF\ItemList() in $this->log().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to display per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of info about task log entries
	 */
	public static function list_getTaskLogEntries(int $start, int $items_per_page, string $sort): array
	{
		$log_entries = [];

		$request = Db::$db->query(
			'',
			'SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
			FROM {db_prefix}log_scheduled_tasks AS lst
				INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items}',
			[
				'sort' => $sort,
				'start' => $start,
				'items' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$log_entries[] = [
				'id' => $row['id_log'],
				'name' => Lang::$txt['scheduled_task_' . $row['task']] ?? $row['task'],
				'time_run' => $row['time_run'],
				'time_taken' => $row['time_taken'],
			];
		}
		Db::$db->free_result($request);

		return $log_entries;
	}

	/**
	 * Callback function for SMF\ItemList() in $this->log().
	 *
	 * @return int The number of log entries
	 */
	public static function list_getNumTaskLogEntries(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_scheduled_tasks',
			[
			],
		);
		list($num_entries) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $num_entries;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->addSubAction('tasks', [$this, 'tasks']);
		$this->addSubAction('taskedit', [$this, 'edit']);
		$this->addSubAction('tasklog', [$this, 'log']);
		$this->addSubAction('settings', [$this, 'settings']);

		User::$me->isAllowedTo('admin_forum');

		Lang::load('ManageScheduledTasks');
		Theme::loadTemplate('ManageScheduledTasks');

		// Tab data might already be set if this was called from Logs::execute().
		if (empty(Menu::$loaded['admin']->tab_data)) {
			// Now for the lovely tabs. That we all love.
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::$txt['scheduled_tasks_title'],
				'help' => '',
				'description' => Lang::$txt['maintain_info'],
				'tabs' => [
					'tasks' => [
						'description' => Lang::$txt['maintain_tasks_desc'],
					],
					'tasklog' => [
						'description' => Lang::$txt['scheduled_log_desc'],
					],
					'settings' => [
						'description' => Lang::$txt['scheduled_tasks_settings_desc'],
					],
				],
			];
		}
	}
}

?>