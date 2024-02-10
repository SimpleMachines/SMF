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

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

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
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [];

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
			new Column(
				name: 'id_event',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'start_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			new Column(
				name: 'end_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'start_time',
				type: 'time',
			),
			new Column(
				name: 'end_time',
				type: 'time',
			),
			new Column(
				name: 'timezone',
				type: 'varchar',
				size: 80,
			),
			new Column(
				name: 'location',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_event',
				],
			),
			new Indices(
				name: 'idx_start_date',
				columns: [
					'start_date',
				],
			),
			new Indices(
				name: 'idx_end_date',
				columns: [
					'end_date',
				],
			),
			new Indices(
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