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
class LogGroupRequests extends Table
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
		$this->name = 'log_group_requests';

		$this->columns = [
			'id_request' => new Column(
				name: 'id_request',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time_applied' => new Column(
				name: 'time_applied',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'reason' => new Column(
				name: 'reason',
				type: 'text',
				not_null: true,
			),
			'comment_type' => new Column(
				name: 'comment_type',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: 'warning',
			),
			'status' => new Column(
				name: 'status',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member_acted' => new Column(
				name: 'id_member_acted',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'member_name_acted' => new Column(
				name: 'member_name_acted',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: 0,
			),
			'time_acted' => new Column(
				name: 'time_acted',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'act_reason' => new Column(
				name: 'act_reason',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_request',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
					'id_group',
				],
			),
		];
	}
}

?>