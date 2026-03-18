<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogScheduledTasks extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_scheduled_tasks';

		$this->columns = [
			'id_log' => new Column(
				name: 'id_log',
				type: 'mediumint',
				not_null: true,
				auto: true,
			),
			'id_task' => new Column(
				name: 'id_task',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'time_run' => new Column(
				name: 'time_run',
				type: 'bigint',
				not_null: true,
				default: 0,
			),
			'time_taken' => new Column(
				name: 'time_taken',
				type: 'float',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_log',
					],
				],
			),
		];

		parent::__construct();
	}
}
