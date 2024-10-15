<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class BackgroundTasks extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'background_tasks';

		$this->columns = [
			'id_task' => new Column(
				name: 'id_task',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			'task_file' => new Column(
				name: 'task_file',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'task_class' => new Column(
				name: 'task_class',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'task_data' => new Column(
				name: 'task_data',
				type: 'mediumtext',
				not_null: true,
			),
			'claimed_time' => new Column(
				name: 'claimed_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_task',
				],
			),
		];
	}
}

?>