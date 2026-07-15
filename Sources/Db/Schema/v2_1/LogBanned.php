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
class LogBanned extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_banned';

		$this->columns = [
			'id_ban_log' => new Column(
				name: 'id_ban_log',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip' => new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
				default: null,
			),
			'email' => new Column(
				name: 'email',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'log_time' => new Column(
				name: 'log_time',
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
					[
						'name' => 'id_ban_log',
					],
				],
			),
			'idx_log_time' => new DbIndex(
				name: 'idx_log_time',
				columns: [
					[
						'name' => 'log_time',
					],
				],
			),
		];
	}
}
