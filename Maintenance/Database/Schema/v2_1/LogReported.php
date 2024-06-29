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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogReported extends Table
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
		$this->name = 'log_reported';

		$this->columns = [
			new Column(
				name: 'id_report',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
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
				name: 'membername',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'body',
				type: 'mediumtext',
				not_null: true,
			),
			new Column(
				name: 'time_started',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'time_updated',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'num_reports',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'closed',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'ignore_all',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_report',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new DbIndex(
				name: 'idx_id_topic',
				columns: [
					'id_topic',
				],
			),
			new DbIndex(
				name: 'idx_closed',
				columns: [
					'closed',
				],
			),
			new DbIndex(
				name: 'idx_time_started',
				columns: [
					'time_started',
				],
			),
			new DbIndex(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
		];
	}
}

?>