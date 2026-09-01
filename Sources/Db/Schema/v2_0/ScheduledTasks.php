<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

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
			'id_task' => 1,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 2,
			'time_unit' => 'h',
			'disabled' => 0,
			'task' => 'approval_notification',
		],
		[
			'id_task' => 2,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 7,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'auto_optimize',
		],
		[
			'id_task' => 3,
			'next_time' => 0,
			'time_offset' => 60,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'daily_maintenance',
		],
		[
			'id_task' => 5,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'daily_digest',
		],
		[
			'id_task' => 6,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'w',
			'disabled' => 0,
			'task' => 'weekly_digest',
		],
		[
			'id_task' => 7,
			'next_time' => 0,
			'time_offset' => '{$sched_task_offset}',
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 0,
			'task' => 'fetchSMfiles',
		],
		[
			'id_task' => 8,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 1,
			'task' => 'birthdayemails',
		],
		[
			'id_task' => 9,
			'next_time' => 0,
			'time_offset' => 0,
			'time_regularity' => 1,
			'time_unit' => 'w',
			'disabled' => 0,
			'task' => 'weekly_maintenance',
		],
		[
			'id_task' => 10,
			'next_time' => 0,
			'time_offset' => 120,
			'time_regularity' => 1,
			'time_unit' => 'd',
			'disabled' => 1,
			'task' => 'paid_subscriptions',
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
			'id_task' => new Column(
				name: 'id_task',
				type: 'smallint',
				not_null: true,
				auto: true,
			),
			'next_time' => new Column(
				name: 'next_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'time_offset' => new Column(
				name: 'time_offset',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'time_regularity' => new Column(
				name: 'time_regularity',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'time_unit' => new Column(
				name: 'time_unit',
				type: 'varchar',
				size: 1,
				not_null: true,
				default: 'h',
			),
			'disabled' => new Column(
				name: 'disabled',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'task' => new Column(
				name: 'task',
				type: 'varchar',
				size: 24,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_task',
					],
				],
			),
			'next_time' => new DbIndex(
				name: 'next_time',
				columns: [
					[
						'name' => 'next_time',
					],
				],
			),
			'disabled' => new DbIndex(
				name: 'disabled',
				columns: [
					[
						'name' => 'disabled',
					],
				],
			),
			'task' => new DbIndex(
				type: 'unique',
				name: 'task',
				columns: [
					[
						'name' => 'task',
					],
				],
			),
		];

		parent::__construct();
	}
}
