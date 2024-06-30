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
class LogComments extends Table
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
		$this->name = 'log_comments';

		$this->columns = [
			new Column(
				name: 'id_comment',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'member_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'comment_type',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: 'warning',
			),
			new Column(
				name: 'id_recipient',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'recipient_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'log_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_notice',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'counter',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'body',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_comment',
				],
			),
			new DbIndex(
				name: 'idx_id_recipient',
				columns: [
					'id_recipient',
				],
			),
			new DbIndex(
				name: 'idx_log_time',
				columns: [
					'log_time',
				],
			),
			new DbIndex(
				name: 'idx_comment_type',
				columns: [
					'comment_type(8)',
				],
			),
		];
	}
}

?>