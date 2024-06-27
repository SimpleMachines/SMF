<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogErrors extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

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
			new Column(
				name: 'id_error',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'log_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'url',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'message',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'session',
				type: 'varchar',
				size: 128,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'error_type',
				type: 'char',
				size: 15,
				not_null: true,
				default: 'general',
			),
			new Column(
				name: 'file',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'line',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'backtrace',
				type: 'varchar',
				size: 10000,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_error',
				],
			),
			new DbIndex(
				name: 'idx_log_time',
				columns: [
					'log_time',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new DbIndex(
				name: 'idx_ip',
				columns: [
					'ip',
				],
			),
		];
	}
}

?>