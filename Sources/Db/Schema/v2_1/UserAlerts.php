<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class UserAlerts extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'user_alerts';

		$this->columns = [
			'id_alert' => new Column(
				name: 'id_alert',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'alert_time' => new Column(
				name: 'alert_time',
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
			'id_member_started' => new Column(
				name: 'id_member_started',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'member_name' => new Column(
				name: 'member_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'content_type' => new Column(
				name: 'content_type',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'content_id' => new Column(
				name: 'content_id',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'content_action' => new Column(
				name: 'content_action',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'is_read' => new Column(
				name: 'is_read',
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
					[
						'name' => 'id_alert',
					],
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
			'idx_alert_time' => new DbIndex(
				name: 'idx_alert_time',
				columns: [
					[
						'name' => 'alert_time',
					],
				],
			),
		];
	}
}
