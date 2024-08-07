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

namespace SMF\Maintenance\Database\Schema\v2_1;

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
			'id_report' => new Column(
				name: 'id_report',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_topic' => new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
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
			'membername' => new Column(
				name: 'membername',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'subject' => new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'body' => new Column(
				name: 'body',
				type: 'mediumtext',
				not_null: true,
			),
			'time_started' => new Column(
				name: 'time_started',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'time_updated' => new Column(
				name: 'time_updated',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'num_reports' => new Column(
				name: 'num_reports',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'closed' => new Column(
				name: 'closed',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'ignore_all' => new Column(
				name: 'ignore_all',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_report',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			'idx_id_topic' => new DbIndex(
				name: 'idx_id_topic',
				columns: [
					'id_topic',
				],
			),
			'idx_closed' => new DbIndex(
				name: 'idx_closed',
				columns: [
					'closed',
				],
			),
			'idx_time_started' => new DbIndex(
				name: 'idx_time_started',
				columns: [
					'time_started',
				],
			),
			'idx_id_msg' => new DbIndex(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
		];
	}
}

?>