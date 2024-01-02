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

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * Base class for scheduled tasks.
 */
abstract class ScheduledTask extends BackgroundTask
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * Whether to log that the task completed.
	 */
	public bool $should_log = true;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Records the start time and duration of this task.
	 */
	public function log(): void
	{
		if (!$this->should_log || !isset($this->_details['id_scheduled_task'])) {
			return;
		}

		Db::$db->insert(
			'',
			'{db_prefix}log_scheduled_tasks',
			[
				'id_task' => 'int',
				'time_run' => 'int',
				'time_taken' => 'float',
			],
			[
				$this->_details['id_scheduled_task'],
				time(),
				round(microtime(true) - TIME_START, 3),
			],
			[],
		);
	}

	/**
	 * Updates Config::$modSettings['next_task_time'].
	 */
	public static function updateNextTaskTime(): void
	{
		// Get the next timestamp right.
		$request = Db::$db->query(
			'',
			'SELECT next_time
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
			ORDER BY next_time ASC
			LIMIT 1',
			[
				'not_disabled' => 0,
			],
		);

		// No new task scheduled yet?
		if (Db::$db->num_rows($request) === 0) {
			$next_task_time = time() + 86400;
		} else {
			list($next_task_time) = Db::$db->fetch_row($request);
		}
		Db::$db->free_result($request);

		Config::updateModSettings(['next_task_time' => $next_task_time]);
	}
}

?>