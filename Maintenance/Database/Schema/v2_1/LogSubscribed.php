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

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogSubscribed extends Table
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
		$this->name = 'log_subscribed';

		$this->columns = [
			new Column(
				name: 'id_sublog',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_subscribe',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'old_id_group',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'start_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'end_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'status',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'payments_pending',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'pending_details',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'reminder_sent',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'vendor_ref',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_sublog',
				],
			),
			new DbIndex(
				name: 'idx_end_time',
				columns: [
					'end_time',
				],
			),
			new DbIndex(
				name: 'idx_reminder_sent',
				columns: [
					'reminder_sent',
				],
			),
			new DbIndex(
				name: 'idx_payments_pending',
				columns: [
					'payments_pending',
				],
			),
			new DbIndex(
				name: 'idx_status',
				columns: [
					'status',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
		];
	}
}

?>