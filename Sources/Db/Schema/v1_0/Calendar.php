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

namespace SMF\Db\Schema\v1_0;

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
			'ID_EVENT' => new Column(
				name: 'ID_EVENT',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'eventDate' => new Column(
				name: 'eventDate',
				type: 'date',
				not_null: true,
				// SMF 1.0 actually used '0000-00-00', but modern versions
				// of MySQL don't like that.
				default: '1004-01-01',
			),
			'ID_BOARD' => new Column(
				name: 'ID_BOARD',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_TOPIC' => new Column(
				name: 'ID_TOPIC',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
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
						'name' => 'ID_EVENT',
					],
				],
			),
			'eventDate' => new DbIndex(
				name: 'eventDate',
				columns: [
					[
						'name' => 'eventDate',
					],
				],
			),
		];

		parent::__construct();
	}
}
