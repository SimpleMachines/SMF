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
class MigrationRuns extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'migration_runs';

		$this->columns = [
			'id_run' => new Column(
				name: 'id_run',
				type: 'varchar',
				size: 36,
				not_null: true,
				default: '',
			),
			'version_from' => new Column(
				name: 'version_from',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'version_to' => new Column(
				name: 'version_to',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'step' => new Column(
				name: 'step',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'substep' => new Column(
				name: 'substep',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'substep_start' => new Column(
				name: 'substep_start',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time_started' => new Column(
				name: 'time_started',
				type: 'bigint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time_updated' => new Column(
				name: 'time_updated',
				type: 'bigint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time_finished' => new Column(
				name: 'time_finished',
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
						'name' => 'id_run',
					],
				],
			),
			'idx_time_finished' => new DbIndex(
				name: 'idx_time_finished',
				columns: [
					[
						'name' => 'time_finished',
					],
				],
			),
		];
	}
}
