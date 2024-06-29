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

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class ScheduledTasks extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_task' => 3,
			'next_time' => 0,
			'time_offset' => 60,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'daily_maintenance',
			'callable' => '',
		],
		[
			'id_task' => 5,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'daily_digest',
			'callable' => '',
		],
		[
			'id_task' => 6,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'w',
			'disabled' => 0,
			'task' => 'weekly_digest',
			'callable' => '',
		],
		[
			'id_task' => 7,
			'next_time' => 0,
			'time_offset' => '{$sched_task_offset}',
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'fetchSMfiles',
			'callable' => '',
		],
		[
			'id_task' => 8,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 1,
			'task' => 'birthdayemails',
			'callable' => '',
		],
		[
			'id_task' => 9,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'w',
			'disabled' => 0,
			'task' => 'weekly_maintenance',
			'callable' => '',
		],
		[
			'id_task' => 10,
			'next_time' => 0,
			'time_offset' => 120,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 1,
			'task' => 'paid_subscriptions',
			'callable' => '',
		],
		[
			'id_task' => 11,
			'next_time' => 0,
			'time_offset' => 120,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'remove_temp_attachments',
			'callable' => '',
		],
		[
			'id_task' => 12,
			'next_time' => 0,
			'time_offset' => 180,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'remove_topic_redirect',
			'callable' => '',
		],
		[
			'id_task' => 13,
			'next_time' => 0,
			'time_offset' => 240,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'remove_old_drafts',
			'callable' => '',
		],
		[
			'id_task' => 14,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'w',
			'disabled' => 1,
			'task' => 'prune_log_topics',
			'callable' => '',
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
		$this->name = 'scheduled_tasks';

		$this->columns = [
			new Column(
				name: 'id_task',
				type: 'smallint',
				auto: true,
			),
			new Column(
				name: 'next_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'time_offset',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'time_regularity',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'time_unit',
				type: 'varchar',
				size: 1,
				not_null: true,
				default: 'h',
			),
			new Column(
				name: 'disabled',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'task',
				type: 'varchar',
				size: 24,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'callable',
				type: 'varchar',
				size: 60,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_task',
				],
			),
			new DbIndex(
				name: 'idx_next_time',
				columns: [
					'next_time',
				],
			),
			new DbIndex(
				name: 'idx_disabled',
				columns: [
					'disabled',
				],
			),
			new DbIndex(
				name: 'idx_task',
				type: 'unique',
				columns: [
					'task',
				],
			),
		];
	}
}

?>