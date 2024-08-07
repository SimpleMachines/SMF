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
			'id_comment' => new Column(
				name: 'id_comment',
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
			'member_name' => new Column(
				name: 'member_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'comment_type' => new Column(
				name: 'comment_type',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: 'warning',
			),
			'id_recipient' => new Column(
				name: 'id_recipient',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'recipient_name' => new Column(
				name: 'recipient_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'log_time' => new Column(
				name: 'log_time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'id_notice' => new Column(
				name: 'id_notice',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'counter' => new Column(
				name: 'counter',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'body' => new Column(
				name: 'body',
				type: 'text',
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
			'idx_id_recipient' => new DbIndex(
				name: 'idx_id_recipient',
				columns: [
					'id_recipient',
				],
			),
			'idx_log_time' => new DbIndex(
				name: 'idx_log_time',
				columns: [
					'log_time',
				],
			),
			'idx_comment_type' => new DbIndex(
				name: 'idx_comment_type',
				columns: [
					'comment_type(8)',
				],
			),
		];
	}
}

?>