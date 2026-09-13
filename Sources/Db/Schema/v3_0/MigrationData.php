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
class MigrationData extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'migration_data';

		$this->columns = [
			'id_entry' => new Column(
				name: 'id_entry',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_run' => new Column(
				name: 'id_run',
				type: 'varchar',
				size: 36,
				not_null: true,
				default: '',
			),
			'migration' => new Column(
				name: 'migration',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'data_type' => new Column(
				name: 'data_type',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'data_key' => new Column(
				name: 'data_key',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'data' => new Column(
				name: 'data',
				type: 'mediumtext',
				not_null: true,
			),
			'time_added' => new Column(
				name: 'time_added',
				type: 'bigint',
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
						'name' => 'id_entry',
					],
				],
			),
			'idx_run' => new DbIndex(
				name: 'idx_run',
				columns: [
					[
						'name' => 'id_run',
					],
					[
						'name' => 'data_type',
					],
				],
			),
		];
	}
}
