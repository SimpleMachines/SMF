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
class Calendar extends Table
{
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
				not_null: true,
				auto: true,
			),
			'start_date' => new Column(
				name: 'start_date',
				type: 'date',
				not_null: true,
				// SMF 2.0 actually used '0001-01-01', but modern versions
				// of MySQL don't like that.
				default: '1004-01-01',
			),
			'end_date' => new Column(
				name: 'end_date',
				type: 'date',
				not_null: true,
				// SMF 2.0 actually used '0001-01-01', but modern versions
				// of MySQL don't like that.
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
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_event',
					],
				],
			),
			'start_date' => new DbIndex(
				name: 'start_date',
				columns: [
					[
						'name' => 'start_date',
					],
				],
			),
			'end_date' => new DbIndex(
				name: 'end_date',
				columns: [
					[
						'name' => 'end_date',
					],
				],
			),
			'topic' => new DbIndex(
				name: 'topic',
				columns: [
					[
						'name' => 'id_topic',
					],
					[
						'name' => 'id_member',
					],
				],
			),
		];

		parent::__construct();
	}
}
