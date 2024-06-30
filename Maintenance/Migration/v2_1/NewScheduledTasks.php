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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class NewScheduledTasks extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v3_0\ScheduledTasks();
		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name !== 'callable' || isset($existing_structure['columns'][$column->name])) {
				continue;
			}

			$table->addColumn($column);
		}

		foreach ($this->newTasks as $task) {
			$request = $this->query(
				'',
				'SELECT id_task
				FROM {db_prefix}scheduled_tasks
				WHERE task = {string:task}',
				[
					'task' => $task[5],
				],
			);

				//next_time, time_offset, time_regularity, time_unit, disabled, task, callable
			if (Db::$db->num_rows($request) === 0) {
				$result = Db::$db->insert(
					'replace',
					'{db_prefix}scheduled_tasks',
					['next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'],
					[$task],
					['id_task'],
				);
			}

			Db::$db->free_result($request);
		}

		return true;
	}
}

?>