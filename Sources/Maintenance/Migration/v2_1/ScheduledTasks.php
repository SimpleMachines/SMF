<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

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
	public string $name = 'Adding new scheduled tasks';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private array $newTasks = [
		[0, 120, 1, 'd', 0, 'remove_temp_attachments', ''],
		[0, 180, 1, 'd', 0, 'remove_topic_redirect', ''],
		[0, 240, 1, 'd', 0, 'remove_old_drafts', ''],
		[0, 0, 1, 'w', 1, 'prune_log_topics', ''],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\ScheduledTasks();
		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name !== 'callable' || isset($existing_structure['columns'][$column->name])) {
				continue;
			}

			$table->addColumn($column);
		}

		$inserts = [];

		foreach ($this->newTasks as $task) {
			$request = $this->query(
				'SELECT id_task
				FROM {db_prefix}scheduled_tasks
				WHERE task = {string:task}',
				[
					'task' => $task[5],
				],
			);

			if (Db::$db->num_rows($request) === 0) {
				$inserts[] = $task;
			}

			Db::$db->free_result($request);
		}

		if (!empty($inserts)) {
			Db::$db->insert(
				method: 'replace',
				table: '{db_prefix}scheduled_tasks',
				columns: [
					'next_time' => 'int',
					'time_offset' => 'int',
					'time_regularity' => 'int',
					'time_unit' => 'string',
					'disabled' => 'int',
					'task' => 'string',
					'callable' => 'string',
				],
				data: $inserts,
				keys: [
					'next_time',
					'time_offset',
					'time_regularity',
					'time_unit',
					'disabled',
					'task',
					'callable',
				],
			);

		}

		// Remove the old 'Auto Optimize' task.
		$this->query(
			'DELETE FROM {db_prefix}scheduled_tasks
			WHERE id_task = {int:AutoOptimizeTaskID}',
			[
				'AutoOptimizeTaskID' => 2,
			],
		);

		$this->query(
			'DELETE FROM {db_prefix}log_scheduled_tasks
			WHERE id_task = {int:AutoOptimizeTaskID}',
			[
				'AutoOptimizeTaskID' => 2,
			],
		);

		return true;
	}
}
