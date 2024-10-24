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

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Calendar extends Table
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
		$this->name = 'calendar';

		$this->columns = [
			'id_event' => new Column(
				name: 'id_event',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			'start_date' => new Column(
				name: 'start_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			'end_date' => new Column(
				name: 'end_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_topic' => new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'start_time' => new Column(
				name: 'start_time',
				type: 'time',
			),
			'end_time' => new Column(
				name: 'end_time',
				type: 'time',
			),
			'timezone' => new Column(
				name: 'timezone',
				type: 'varchar',
				size: 80,
			),
			'location' => new Column(
				name: 'location',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_event',
				],
			),
			'idx_start_date' => new DbIndex(
				name: 'idx_start_date',
				columns: [
					'start_date',
				],
			),
			'idx_end_date' => new DbIndex(
				name: 'idx_end_date',
				columns: [
					'end_date',
				],
			),
			'idx_topic' => new DbIndex(
				name: 'idx_topic',
				columns: [
					'id_topic',
					'id_member',
				],
			),
		];
	}
}

?>