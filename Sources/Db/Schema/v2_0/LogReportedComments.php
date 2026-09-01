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

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogReportedComments extends Table
{
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
				not_null: true,
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
			'email_address' => new Column(
				name: 'email_address',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'member_ip' => new Column(
				name: 'member_ip',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
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
					[
						'name' => 'id_comment',
					],
				],
			),
			'id_report' => new DbIndex(
				name: 'id_report',
				columns: [
					[
						'name' => 'id_report',
					],
				],
			),
			'id_member' => new DbIndex(
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
			'time_sent' => new DbIndex(
				name: 'time_sent',
				columns: [
					[
						'name' => 'time_sent',
					],
				],
			),
		];

		parent::__construct();
	}
}
