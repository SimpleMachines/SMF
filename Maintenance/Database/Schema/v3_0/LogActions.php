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
class LogActions extends Table
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
		$this->name = 'log_actions';

		$this->columns = [
			'id_action' => new Column(
				name: 'id_action',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			'id_log' => new Column(
				name: 'id_log',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
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
			'action' => new Column(
				name: 'action',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
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
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'extra' => new Column(
				name: 'extra',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_action',
				],
			),
			'idx_id_log' => new DbIndex(
				name: 'idx_id_log',
				columns: [
					'id_log',
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
			'idx_id_board' => new DbIndex(
				name: 'idx_id_board',
				columns: [
					'id_board',
				],
			),
			'idx_id_msg' => new DbIndex(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
			'idx_id_topic_id_log' => new DbIndex(
				name: 'idx_id_topic_id_log',
				columns: [
					'id_topic',
					'id_log',
				],
			),
		];
	}
}

?>