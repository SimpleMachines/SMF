<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

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
			'id_error' => new Column(
				name: 'id_error',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'log_time' => new Column(
				name: 'log_time',
				type: 'int',
				unsigned: true,
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
			'ip' => new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
			),
			'url' => new Column(
				name: 'url',
				type: 'text',
				not_null: true,
			),
			'message' => new Column(
				name: 'message',
				type: 'text',
				not_null: true,
			),
			'session' => new Column(
				name: 'session',
				type: 'varchar',
				size: 128,
				not_null: true,
				default: '',
			),
			'error_type' => new Column(
				name: 'error_type',
				type: 'char',
				size: 15,
				not_null: true,
				default: 'general',
			),
			'file' => new Column(
				name: 'file',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'line' => new Column(
				name: 'line',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'backtrace' => new Column(
				name: 'backtrace',
				type: 'varchar',
				size: 10000,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_error',
				],
			),
			'idx_log_time' => new DbIndex(
				name: 'idx_log_time',
				columns: [
					'log_time',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			'idx_ip' => new DbIndex(
				name: 'idx_ip',
				columns: [
					'ip',
				],
			),
		];
	}
}

?>