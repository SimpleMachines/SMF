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
			'ID_BAN_LOG' => new Column(
				name: 'ID_BAN_LOG',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
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
				type: 'char',
				size: 16,
				not_null: true,
				default: '                ',
			),
			'email' => new Column(
				name: 'email',
				type: 'tinytext',
				not_null: true,
			),
			'logTime' => new Column(
				name: 'logTime',
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
						'name' => 'ID_BAN_LOG',
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
		];

		parent::__construct();
	}
}
