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
class LogOnline extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_online';

		$this->columns = [
			'session' => new Column(
				name: 'session',
				type: 'varchar',
				size: 32,
				not_null: true,
				default: '',
			),
			'log_time' => new Column(
				name: 'log_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_spider' => new Column(
				name: 'id_spider',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip' => new Column(
				name: 'ip',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'url' => new Column(
				name: 'url',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'session',
					],
				],
			),
			'log_time' => new DbIndex(
				name: 'log_time',
				columns: [
					[
						'name' => 'log_time',
					],
				],
			),
			'id_member' => new DbIndex(
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
		];

		parent::__construct();
	}
}
