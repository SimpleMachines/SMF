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
class LogReportedComments extends Table
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
		$this->name = 'log_reported_comments';

		$this->columns = [
			'id_comment' => new Column(
				name: 'id_comment',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'id_report' => new Column(
				name: 'id_report',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				not_null: true,
			),
			'membername' => new Column(
				name: 'membername',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'member_ip' => new Column(
				name: 'member_ip',
				type: 'inet',
				size: 16,
			),
			'comment' => new Column(
				name: 'comment',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'time_sent' => new Column(
				name: 'time_sent',
				type: 'int',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_comment',
				],
			),
			'idx_id_report' => new DbIndex(
				name: 'idx_id_report',
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
			'idx_time_sent' => new DbIndex(
				name: 'idx_time_sent',
				columns: [
					'time_sent',
				],
			),
		];
	}
}

?>