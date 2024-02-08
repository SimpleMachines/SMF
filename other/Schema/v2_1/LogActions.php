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
use SMF\Db\Schema\Index;
use SMF\Db\Schema\Table;

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
			new Column(
				name: 'id_action',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_log',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
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
				name: 'action',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
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
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'extra',
				type: 'text',
				not_null: true,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_action',
				],
			),
			new Index(
				name: 'idx_id_log',
				columns: [
					'id_log',
				],
			),
			new Index(
				name: 'idx_log_time',
				columns: [
					'log_time',
				],
			),
			new Index(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new Index(
				name: 'idx_id_board',
				columns: [
					'id_board',
				],
			),
			new Index(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
			new Index(
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