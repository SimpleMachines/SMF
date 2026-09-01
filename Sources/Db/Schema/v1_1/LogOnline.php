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

namespace SMF\Db\Schema\v1_1;

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
			'logTime' => new Column(
				name: 'logTime',
				type: 'timestamp',
				not_null: true,
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
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
			'logTime' => new DbIndex(
				name: 'logTime',
				columns: [
					[
						'name' => 'logTime',
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
				],
			),
		];

		parent::__construct();
	}
}
