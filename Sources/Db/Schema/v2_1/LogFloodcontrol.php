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

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogFloodcontrol extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_floodcontrol';

		$this->columns = [
			'ip' => new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
				not_null: true,
			),
			'log_time' => new Column(
				name: 'log_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'log_type' => new Column(
				name: 'log_type',
				type: 'varchar',
				size: 30,
				default: 'post',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ip',
					],
					[
						'name' => 'log_type',
					],
				],
			),
		];
	}
}
