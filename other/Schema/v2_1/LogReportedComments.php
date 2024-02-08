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
			new Column(
				name: 'id_comment',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_report',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				not_null: true,
			),
			new Column(
				name: 'membername',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'member_ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'comment',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'time_sent',
				type: 'int',
				not_null: true,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_comment',
				],
			),
			new Index(
				name: 'idx_id_report',
				columns: [
					'id_report',
				],
			),
			new Index(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new Index(
				name: 'idx_time_sent',
				columns: [
					'time_sent',
				],
			),
		];
	}
}

?>