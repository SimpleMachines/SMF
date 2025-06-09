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
class LogErrors extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_errors';

		$this->columns = [
			'ID_ERROR' => new Column(
				name: 'ID_ERROR',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'logTime' => new Column(
				name: 'logTime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'IP' => new Column(
				name: 'IP',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'url' => new Column(
				name: 'url',
				type: 'text',
				not_null: true,
				default: '',
			),
			'message' => new Column(
				name: 'message',
				type: 'text',
				not_null: true,
				default: '',
			),
			'session' => new Column(
				name: 'session',
				type: 'char',
				size: 32,
				not_null: true,
				default: '                                ',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_ERROR',
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
