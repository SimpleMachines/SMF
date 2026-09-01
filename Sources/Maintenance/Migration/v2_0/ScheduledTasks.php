<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class ScheduledTasks extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding scheduled tasks data';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table exists and is structured correctly.
		// Note: the log_scheduled_tasks table also needs to be created, but the
		// table normalization substeps will take care of that part.
		$table = new Schema\v2_0\ScheduledTasks();
		$table->normalize();

		// Populate scheduled task table.
		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}scheduled_tasks',
			columns: [
				'next_time' => 'int',
				'time_offset' => 'int',
				'time_regularity' => 'int',
				'time_unit' => 'string-2',
				'disabled' => 'int',
				'task' => 'string-24',
			],
			data: [
				[0, 0, 2, 'h', 0, 'approval_notification'],
				[0, 60, 1, 'd', 0, 'daily_maintenance'],
				[0, 0, 1, 'd', 0, 'daily_digest'],
				[0, 0, 1, 'w', 0, 'weekly_digest'],
				[0, 0, 1, 'd', 1, 'birthdayemails'],
				[0, 120, 1, 'd', 0, 'paid_subscriptions'],
				[0, 82800 + mt_rand(0, 86399), 1, 'd', 0, 'fetchSMfiles'],
			],
			keys: [],
		);

		// Moving auto optimise settings to scheduled task.
		if (!isset(Config::$modSettings['next_task_time']) && isset(Config::$modSettings['autoOptLastOpt'])) {
			// Try to move over the regularity...
			if (isset(Config::$modSettings['autoOptDatabase'])) {
				$this->query(
					'UPDATE {db_prefix}scheduled_tasks
					SET
						disabled = {int:disabled},
						time_regularity = {int:regularity},
						next_time = {int:next_time}
					WHERE task = {string:task}',
					[
						'task' => 'auto_optimize',
						'disabled' => empty(Config::$modSettings['autoOptDatabase']) ? 1 : 0,
						'regularity' => $disabled ? 7 : Config::$modSettings['autoOptDatabase'],
						'next_time' => Config::$modSettings['autoOptLastOpt'] + 3600 * 24 * Config::$modSettings['autoOptDatabase'],
					],
				);
			}

			// Delete the old settings.
			Config::updateModSettings([
				'autoOptLastOpt' => null,
				'autoOptDatabase' => null,
			]);
		}

		// Add some new settings.
		Config::updateModSettings([
			'next_task_time' => Config::$modSettings['next_task_time'] ?? 0,
			'birthday_email' => Config::$modSettings['birthday_email'] ?? 'happy_birthday',
		]);

		$this->handleTimeout();

		return true;
	}
}
