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

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Runs background tasks (a.k.a. cron jobs), including scheduled tasks.
 *
 * MOD AUTHORS:
 *
 * To add a new background task, do the following:
 *
 *  1. Create a class that extends SMF\Tasks\BackgroundTask. Put your task's
 *     code in its execute() method.
 *  2. Add your background task to the task queue on the fly wherever needed
 *     in the rest of your mod's code.
 *
 * To add a new scheduled task, do the following:
 *
 *  1. Create a class that extends SMF\Tasks\ScheduledTask. Put your task's
 *     code in its execute() method.
 *  2. Use the integrate_scheduled_tasks hook to add information about your
 *     scheduled task to SMF\TaskRunner::$scheduled_tasks.
 *  3. Add an entry for your scheduled task to the {db_prefix}scheduled_tasks
 *     table when your mod is being installed.
 */
class TaskRunner
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'calculateNextTrigger' => 'CalculateNextTrigger',
		],
	];

	/***********
	 * Constants
	 ***********/

	/**
	 * This setting is worth bearing in mind. If you are running this from
	 * proper cron, make sure you don't run this file any more frequently than
	 * indicated here. It might turn ugly if you do. But on proper cron you can
	 * always increase this value provided you don't go beyond max_limit.
	 */
	public const MAX_CRON_TIME = 10;

	/**
	 * If a task fails for whatever reason it will still be marked as claimed.
	 * This is the threshold by which if a task has not completed in this time,
	 * the task should become available again.
	 */
	public const MAX_CLAIM_THRESHOLD = 300;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Info about the classes to load for scheduled tasks.
	 */
	public static array $scheduled_tasks = [
		'daily_maintenance' => [
			'class' => 'SMF\\Tasks\\DailyMaintenance',
		],
		'weekly_maintenance' => [
			'class' => 'SMF\\Tasks\\WeekLyMaintenance',
		],
		'daily_digest' => [
			'class' => 'SMF\\Tasks\\SendDigests',
			'data' => ['is_weekly' => 0],
		],
		'weekly_digest' => [
			'class' => 'SMF\\Tasks\\SendDigests',
			'data' => ['is_weekly' => 1],
		],
		'fetchSMfiles' => [
			'class' => 'SMF\\Tasks\\FetchSMFiles',
		],
		'birthdayemails' => [
			'class' => 'SMF\\Tasks\\Birthday_Notify',
		],
		'paid_subscriptions' => [
			'class' => 'SMF\\Tasks\\PaidSubs',
		],
		'remove_temp_attachments' => [
			'class' => 'SMF\\Tasks\\RemoveTempAttachments',
		],
		'remove_topic_redirect' => [
			'class' => 'SMF\\Tasks\\RemoveTopicRedirects',
		],
		'remove_old_drafts' => [
			'class' => 'SMF\\Tasks\\RemoveOldDrafts',
		],
		'prune_log_topics' => [
			'class' => 'SMF\\Tasks\\PruneLogTopics',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// For backward compatibility.
		if (!defined('MAX_CRON_TIME')) {
			define('MAX_CRON_TIME', self::MAX_CRON_TIME);
		}

		if (!defined('MAX_CLAIM_THRESHOLD')) {
			define('MAX_CLAIM_THRESHOLD', self::MAX_CLAIM_THRESHOLD);
		}

		// Called from cron.php.
		if (SMF === 'BACKGROUND') {
			define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

			// Don't do john didley if the forum's been shut down completely.
			if (!empty(Config::$maintenance) &&  2 === Config::$maintenance) {
				ErrorHandler::displayMaintenanceMessage();
			}

			// Have we already turned this off? If so, exist gracefully.
			// @todo Remove this? It's a bad idea to ever disable background tasks.
			if (file_exists(Config::$cachedir . '/cron.lock')) {
				$this->obExit();
			}

			Security::frameOptionsHeader();

			// Before we go any further, if this is not a CLI request, we need to do some checking.
			if (!FROM_CLI) {
				// When using sub-domains with SSI and ssi_themes set, browsers will
				// receive a "Access-Control-Allow-Origin" error. '*' is not ideal,
				// but the best method to preventing this from occurring.
				header('Access-Control-Allow-Origin: *');

				// We will clean up $_GET shortly. But we want to this ASAP.
				$ts = isset($_GET['ts']) ? (int) $_GET['ts'] : 0;

				if ($ts <= 0 || $ts % 15 != 0 || time() - $ts < 0 || time() - $ts > 20) {
					$this->obExit();
				}
			} else {
				$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
			}

			Config::$db_show_debug = null;

			Db::load();

			Config::reloadModSettings();

			// Just in case there's a problem...
			set_error_handler(__CLASS__ . '::handleError');

			User::$sc = '';

			$_SERVER['QUERY_STRING'] = '';
			$_SERVER['REQUEST_URL'] = FROM_CLI ? 'CLI cron.php' : Config::$boardurl . '/cron.php';

			// Now 'clean the request' (or more accurately, ignore everything we're not going to use)
			$this->cleanRequest();

			// Load the basic Lang::$txt strings.
			Lang::load('index+Modifications');
		}
	}

	/**
	 * This is the one that gets stuff done.
	 *
	 * Internally, this calls $this->fetchTask() to get the details of a
	 * background task, then passes those details to $this->performTask(),
	 * then calls $this->obExit() to end execution.
	 */
	public function execute(): void
	{
		// If we have any scheduled tasks due, add them to the queue.
		if ((Config::$modSettings['next_task_time'] ?? 0) < time()) {
			$this->scheduleTask();
		}

		while ($task_details = $this->fetchTask()) {
			$result = $this->performTask($task_details);

			if ($result) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}background_tasks
					WHERE id_task = {int:task}',
					[
						'task' => $task_details['id_task'],
					],
				);
			}
		}

		// If we have time, work on the mail queue.
		if (time() - TIME_START < ceil(self::MAX_CRON_TIME / 2) && (Config::$modSettings['mail_next_send'] ?? INF) < time()) {
			Mail::reduceQueue();
		}

		$this->obExit();
	}

	/**
	 * Similar to execute(), but it only runs one task at most, and doesn't
	 * exit when finished.
	 */
	public function runOneTask(): void
	{
		// If we have any scheduled tasks due, add them to the queue.
		if ((Config::$modSettings['next_task_time'] ?? 0) < time()) {
			$this->scheduleTask();
		}

		// Do we have a task to run?
		if ($task_details = $this->fetchTask()) {
			$result = $this->performTask($task_details);

			if ($result) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}background_tasks
					WHERE id_task = {int:task}',
					[
						'task' => $task_details['id_task'],
					],
				);
			}
		}
		// What about mail to send?
		elseif ((Config::$modSettings['mail_next_send'] ?? INF) < time()) {
			Mail::reduceQueue();
		}
	}

	/**
	 * Runs the given scheduled tasks immediately.
	 *
	 * @param array $tasks The IDs or names of the scheduled tasks to run.
	 */
	public function runScheduledTasks(array $tasks): void
	{
		// Actually have something passed?
		if (!empty($tasks)) {
			$task_ids = [];
			$task_names = [];
			$task_query = [];

			foreach ($tasks as $task) {
				if (is_numeric($task)) {
					$task_ids[] = (int) $task;
				} else {
					$task_names[] = (string) $task;
				}
			}

			if (!empty($task_ids)) {
				$task_query[] = 'id_task IN ({array_int:task_ids})';
			}

			if (!empty($task_names)) {
				$task_query[] = 'task IN ({array_string:task_names})';
			}

			if (!empty($task_query)) {
				$task_query = implode(' OR ', $task_query);
			}
		}

		if (empty($task_query)) {
			return;
		}

		// Load up the tasks.
		ignore_user_abort(true);

		$request = Db::$db->query(
			'',
			'SELECT id_task, next_time, task, callable
			FROM {db_prefix}scheduled_tasks
			WHERE ' . $task_query . '
			LIMIT {int:limit}',
			[
				'task_ids' => $task_ids,
				'task_names' => $task_names,
				'limit' => count($task_ids) + count($task_names),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// What kind of task are we handling?
			if (!empty($row['callable'])) {
				$task_details = $this->getScheduledTaskDetails($row['id_task'], $row['callable'], true);
			} elseif (!empty($row['task'])) {
				$task_details = $this->getScheduledTaskDetails($row['id_task'], $row['task']);
			} else {
				continue;
			}

			// Does the class exist?
			if (!class_exists($task_details['task_class'])) {
				continue;
			}

			// Load an instance of the scheduled task.
			$bgtask = new $task_details['task_class']($task_details['task_data']);

			// If the instance isn't actually a scheduled task, skip it.
			if (!is_subclass_of($bgtask, 'SMF\\Tasks\\ScheduledTask')) {
				continue;
			}

			// Run the task and log that we did.
			$bgtask->execute();
			$bgtask->log();
		}
		Db::$db->free_result($request);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The error handling function
	 *
	 * @param int $error_level One of the PHP error level constants (see )
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line What line of the specified file the error occurred on
	 */
	public static function handleError($error_level, $error_string, $file, $line): void
	{
		// Ignore errors that should not be logged.
		if (error_reporting() == 0) {
			return;
		}

		$error_type = 'cron';

		ErrorHandler::log($error_level . ': ' . $error_string, $error_type, $file, $line);

		// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
		if ($error_level % 255 == E_ERROR) {
			die('No direct access...');
		}
	}

	/**
	 * Calculate the next time the passed tasks should be triggered.
	 *
	 * @param string|array $tasks IDs or names of one or more scheduled tasks.
	 * @param bool $force_update Whether to force the tasks to run now.
	 */
	public static function calculateNextTrigger(string|array $tasks = [], bool $force_update = false): void
	{
		$tasks = (array) $tasks;

		// Actually have something passed?
		if (!empty($tasks)) {
			$task_ids = [];
			$task_names = [];
			$task_query = [];

			foreach ($tasks as $task) {
				if (is_numeric($task)) {
					$task_ids[] = (int) $task;
				} else {
					$task_names[] = (string) $task;
				}
			}

			if (!empty($task_ids)) {
				$task_query[] = 'id_task IN ({array_int:task_ids})';
			}

			if (!empty($task_names)) {
				$task_query[] = 'task IN ({array_string:task_names})';
			}

			if (!empty($task_query)) {
				$task_query = 'AND (' . implode(' OR ', $task_query) . ')';
			}
		}

		if (empty($task_query)) {
			$task_query = '';
		}

		$next_task_time = empty($tasks) ? time() + 86400 : Config::$modSettings['next_task_time'];

		// Get the critical info for the tasks.
		$tasks = [];
		$request = Db::$db->query(
			'',
			'SELECT id_task, next_time, time_offset, time_regularity, time_unit
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				' . $task_query,
			[
				'not_disabled' => 0,
				'task_ids' => $task_ids ?? [],
				'task_names' => $task_names ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$next_time = self::getNextScheduledTime($row['time_regularity'], $row['time_unit'], $row['time_offset']);

			// Only bother moving the task if it's out of place or we're forcing it!
			if ($force_update || $next_time < $row['next_time'] || $row['next_time'] < time()) {
				$tasks[$row['id_task']] = $next_time;
			} else {
				$next_time = $row['next_time'];
			}

			// If this is sooner than the current next task, make this the next task.
			if ($next_time < $next_task_time) {
				$next_task_time = $next_time;
			}
		}
		Db::$db->free_result($request);

		// Now make the changes!
		foreach ($tasks as $id => $time) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}scheduled_tasks
				SET next_time = {int:next_time}
				WHERE id_task = {int:id_task}',
				[
					'next_time' => $time,
					'id_task' => $id,
				],
			);
		}

		// If the next task is now different, update.
		if (Config::$modSettings['next_task_time'] != $next_task_time) {
			Config::updateModSettings(['next_task_time' => $next_task_time]);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The heart of this cron handler...
	 *
	 * @return bool|array False if there's nothing to do or an array of info
	 *    about the task.
	 */
	protected function fetchTask(): bool|array
	{
		// Check we haven't run over our time limit.
		if (microtime(true) - TIME_START > self::MAX_CRON_TIME) {
			return false;
		}

		// Try to find a task. Specifically, try to find one that hasn't been
		// claimed previously, or failing that, a task that was claimed but
		// failed for whatever reason and failed long enough ago. We should not
		// care what task it is, merely that it is one in the queue; the order
		// is irrelevant.
		$request = Db::$db->query(
			'',
			'SELECT id_task, task_file, task_class, task_data, claimed_time
			FROM {db_prefix}background_tasks
			WHERE claimed_time < {int:claim_limit}
			LIMIT 1',
			[
				'claim_limit' => time() - self::MAX_CLAIM_THRESHOLD,
			],
		);

		if ($row = Db::$db->fetch_assoc($request)) {
			// We found one. Let's try and claim it immediately.
			Db::$db->free_result($request);
			Db::$db->query(
				'',
				'UPDATE {db_prefix}background_tasks
				SET claimed_time = {int:new_claimed}
				WHERE id_task = {int:task}
					AND claimed_time = {int:old_claimed}',
				[
					'new_claimed' => time(),
					'task' => $row['id_task'],
					'old_claimed' => $row['claimed_time'],
				],
			);

			// Could we claim it? If so, return it back.
			if (Db::$db->affected_rows() != 0) {
				// Update the time and go back.
				$row['claimed_time'] = time();

				return $row;
			}

			// Uh oh, we just missed it. Try to claim another one, and let
			// it fall through if there aren't any.
			return $this->fetchTask();
		}

		// No dice. Clean up and go home.
		Db::$db->free_result($request);

		return false;
	}

	/**
	 * This actually handles the task.
	 *
	 * @param array $task_details An array of info about the task.
	 * @return bool Whether the task should be cleared from the queue.
	 */
	protected function performTask($task_details): bool
	{
		// This indicates the file to load.
		// Only needed for tasks that don't use the SMF\Tasks\ namespace.
		if (!empty($task_details['task_file'])) {
			$include = strtr(trim($task_details['task_file']), ['$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir]);

			if (file_exists($include)) {
				require_once $include;
			}
		}

		// All background tasks need to be classes.
		if (empty($task_details['task_class'])) {
			// This would be nice to translate but the language files aren't
			// loaded for any specific language.
			ErrorHandler::log('Invalid background task specified: no class supplied');

			// So we clear it from the queue.
			return true;
		}

		// Normally, the class should be specified using its fully qualified name.
		if (class_exists($task_details['task_class']) && is_subclass_of($task_details['task_class'], 'SMF\\Tasks\\BackgroundTask')) {
			$details = empty($task_details['task_data']) ? [] : Utils::jsonDecode($task_details['task_data'], true);

			$bgtask = new $task_details['task_class']($details);

			$success = $bgtask->execute();
		}
		// Just in case a mod or something specified a task without giving the namespace.
		elseif (class_exists('SMF\\Tasks\\' . $task_details['task_class']) && is_subclass_of('SMF\\Tasks\\' . $task_details['task_class'], 'SMF\\Tasks\\BackgroundTask')) {
			$details = empty($task_details['task_data']) ? [] : Utils::jsonDecode($task_details['task_data'], true);

			$task_class = 'SMF\\Tasks\\' . $task_details['task_class'];

			$bgtask = new $task_class($details);

			$success = $bgtask->execute();
		}
		// Uh-oh...
		else {
			ErrorHandler::log('Invalid background task specified: class ' . $task_details['task_class'] . ' not found');

			// So we clear it from the queue.
			return true;
		}

		// For scheduled tasks, log it and update our next scheduled task time.
		if (is_subclass_of($bgtask, 'SMF\\Tasks\\ScheduledTask')) {
			$bgtask->log();
			Tasks\ScheduledTask::updateNextTaskTime();
		}

		return $success;
	}

	/**
	 * Checks whether there are any scheduled tasks to run, and if there are,
	 * adds them to the queue of background tasks.
	 */
	protected function scheduleTask(): void
	{
		// Select the next task to do.
		$request = Db::$db->query(
			'',
			'SELECT id_task, task, next_time, time_offset, time_regularity, time_unit, callable
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				AND next_time <= {int:current_time}
			ORDER BY next_time ASC
			LIMIT 1',
			[
				'not_disabled' => 0,
				'current_time' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// When should this next be run?
			$next_time = self::getNextScheduledTime($row['time_regularity'], $row['time_unit'], $row['time_offset']);

			// How long in seconds is the gap?
			$duration = $row['time_regularity'];

			switch ($row['time_unit']) {
				case 'm':
					$duration *= 60;
					break;

				case 'h':
					$duration *= 3600;
					break;

				case 'd':
					$duration *= 86400;
					break;

				case 'w':
					$duration *= 604800;
					break;

				default:
					break;
			}

			// If we were really late running this task, skip the next scheduled execution.
			if (time() + ($duration / 2) > $next_time) {
				$next_time += $duration;
			}

			// What kind of task are we handling?
			if (!empty($row['callable'])) {
				$task_details = $this->getScheduledTaskDetails($row['id_task'], $row['callable'], true);
			} elseif (!empty($row['task'])) {
				$task_details = $this->getScheduledTaskDetails($row['id_task'], $row['task']);
			}

			// If we have a valid background task, queue it up.
			if (isset($task_details['task_class']) && class_exists($task_details['task_class'])) {
				Db::$db->insert(
					'insert',
					'{db_prefix}background_tasks',
					[
						'task_file' => 'string-255',
						'task_class' => 'string-255',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					[
						'',
						$task_details['task_class'],
						json_encode($task_details['task_data']),
						0,
					],
					[],
				);

				// Updates next_time for this task so that no parallel processes run it.
				Db::$db->query(
					'',
					'UPDATE {db_prefix}scheduled_tasks
					SET next_time = {int:next_time}
					WHERE id_task = {int:id_task}',
					[
						'next_time' => $next_time,
						'id_task' => $row['id_task'],
					],
				);
			}
		}
		Db::$db->free_result($request);
	}

	/**
	 * Gets the necessary info to load the class for a scheduled task.
	 *
	 * @param int $id The ID of the task.
	 * @param string $task The name of the task, or a callable to call.
	 * @param bool $is_callable Whether $task is the name of a callable rather
	 *    than a task name.
	 * @return array The file, class name, and basic additional data for the task.
	 */
	protected function getScheduledTaskDetails(int $id, string $task, bool $is_callable = false): array
	{
		// Allow mods to easily add scheduled tasks.
		IntegrationHook::call('integrate_scheduled_tasks', [&self::$scheduled_tasks]);

		if ($is_callable) {
			$class = 'SMF\\Tasks\\GenericScheduledTask';
			$data = [
				'callable' => $task,
				'id_scheduled_task' => $id,
			];
		} elseif (isset(self::$scheduled_tasks[$task])) {
			$class = self::$scheduled_tasks[$task]['class'];
			$data = array_merge(
				[
					'task' => $task,
					'id_scheduled_task' => $id,
				],
				self::$scheduled_tasks[$task]['data'] ?? [],
			);
		} else {
			$class = 'SMF\\Tasks\\' . $task;
			$data = [
				'task' => $task,
				'id_scheduled_task' => $id,
			];
		}

		return [
			'task_class' => $class,
			'task_data' => $data,
		];
	}

	/* Helper functions that resemble their big brother counterparts. */

	/**
	 * Cleans up the request variables.
	 *
	 */
	protected function cleanRequest(): void
	{
		// These keys shouldn't be set...ever.
		if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS'])) {
			die('Invalid request variable.');
		}

		// Save some memory.. (since we don't use these anyway.)
		unset(
			$GLOBALS['HTTP_POST_VARS'],
			$GLOBALS['HTTP_POST_VARS'],
			$GLOBALS['HTTP_POST_FILES'],
			$GLOBALS['HTTP_POST_FILES'],
			$GLOBALS['_GET'],
			$GLOBALS['_POST'],
			$GLOBALS['_REQUEST'],
			$GLOBALS['_COOKIE'],
			$GLOBALS['_FILES']
		);
	}

	/**
	 * The exit function.
	 */
	protected function obExit(): void
	{
		if (FROM_CLI) {
			die(0);
		}

		header('content-type: image/gif');

		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Simply returns a time stamp of the next instance of these time parameters.
	 *
	 * @param int $regularity The regularity
	 * @param string $unit What unit are we using - 'm' for minutes, 'd' for days, 'w' for weeks or anything else for seconds
	 * @param int $offset The offset
	 * @return int The timestamp for the specified time
	 */
	protected static function getNextScheduledTime(int $regularity, string $unit, int $offset): int
	{
		// Just in case!
		if ($regularity == 0) {
			$regularity = 2;
		}

		$cur_min = date('i', time());

		// If the unit is minutes only check regularity in minutes.
		if ($unit == 'm') {
			$off = date('i', $offset);

			// If it's now just pretend it ain't,
			if ($off == $cur_min) {
				$next_time = time() + $regularity;
			} else {
				// Make sure that the offset is always in the past.
				$off = $off > $cur_min ? $off - 60 : $off;

				while ($off <= $cur_min) {
					$off += $regularity;
				}

				// Now we know when the time should be!
				$next_time = time() + 60 * ($off - $cur_min);
			}
		}
		// Otherwise, work out what the offset would be with today's date.
		else {
			$next_time = mktime(date('H', $offset), date('i', $offset), 0, date('m'), date('d'), date('Y'));

			// Make the time offset in the past!
			if ($next_time > time()) {
				$next_time -= 86400;
			}

			// Default we'll jump in hours.
			$apply_offset = 3600;

			// 24 hours = 1 day.
			if ($unit == 'd') {
				$apply_offset = 86400;
			}

			// Otherwise a week.
			if ($unit == 'w') {
				$apply_offset = 604800;
			}

			$apply_offset *= $regularity;

			// Just add on the offset.
			while ($next_time <= time()) {
				$next_time += $apply_offset;
			}
		}

		return $next_time;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TaskRunner::exportStatic')) {
	TaskRunner::exportStatic();
}

?>