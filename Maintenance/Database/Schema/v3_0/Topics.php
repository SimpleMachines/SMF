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
class Topics extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_topic' => 1,
			'id_board' => 1,
			'id_first_msg' => 1,
			'id_last_msg' => 1,
			'id_member_started' => 0,
			'id_member_updated' => 0,
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'topics';

		$this->columns = [
			'id_topic' => new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'is_sticky' => new Column(
				name: 'is_sticky',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_first_msg' => new Column(
				name: 'id_first_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_last_msg' => new Column(
				name: 'id_last_msg',
				type: 'int',
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
			'id_member_updated' => new Column(
				name: 'id_member_updated',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_poll' => new Column(
				name: 'id_poll',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_previous_board' => new Column(
				name: 'id_previous_board',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'id_previous_topic' => new Column(
				name: 'id_previous_topic',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'num_replies' => new Column(
				name: 'num_replies',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'num_views' => new Column(
				name: 'num_views',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'locked' => new Column(
				name: 'locked',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'redirect_expires' => new Column(
				name: 'redirect_expires',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_redirect_topic' => new Column(
				name: 'id_redirect_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'unapproved_posts' => new Column(
				name: 'unapproved_posts',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'approved' => new Column(
				name: 'approved',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_topic',
				],
			),
			'idx_last_message' => new DbIndex(
				name: 'idx_last_message',
				type: 'unique',
				columns: [
					'id_last_msg',
					'id_board',
				],
			),
			'idx_first_message' => new DbIndex(
				name: 'idx_first_message',
				type: 'unique',
				columns: [
					'id_first_msg',
					'id_board',
				],
			),
			'idx_poll' => new DbIndex(
				name: 'idx_poll',
				type: 'unique',
				columns: [
					'id_poll',
					'id_topic',
				],
			),
			'idx_is_sticky' => new DbIndex(
				name: 'idx_is_sticky',
				columns: [
					'is_sticky',
				],
			),
			'idx_approved' => new DbIndex(
				name: 'idx_approved',
				columns: [
					'approved',
				],
			),
			'idx_member_started' => new DbIndex(
				name: 'idx_member_started',
				columns: [
					'id_member_started',
					'id_board',
				],
			),
			'idx_last_message_sticky' => new DbIndex(
				name: 'idx_last_message_sticky',
				columns: [
					'id_board',
					'is_sticky',
					'id_last_msg',
				],
			),
			'idx_board_news' => new DbIndex(
				name: 'idx_board_news',
				columns: [
					'id_board',
					'id_first_msg',
				],
			),
		];
	}
}

?>