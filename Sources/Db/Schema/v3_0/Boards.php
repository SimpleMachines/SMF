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
class Boards extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [
		'id_board' => 'int',
		'id_cat' => 'int',
		'board_order' => 'int',
		'id_last_msg' => 'int',
		'id_msg_updated' => 'int',
		'name' => 'string',
		'description' => 'string',
		'num_topics' => 'int',
		'num_posts' => 'int',
		'member_groups' => 'string',
	];

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_board' => 1,
			'id_cat' => 1,
			'board_order' => 1,
			'id_last_msg' => 1,
			'id_msg_updated' => 1,
			'name' => '{$default_board_name}',
			'description' => '{$default_board_description}',
			'num_topics' => 1,
			'num_posts' => 1,
			'member_groups' => '-1,0,2',
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
		$this->name = 'boards';

		$this->columns = [
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_cat',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'child_level',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_parent',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'board_order',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_last_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_msg_updated',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'member_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '-1,0',
			),
			new Column(
				name: 'id_profile',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'num_topics',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'num_posts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'count_posts',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'override_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'unapproved_posts',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'unapproved_topics',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'redirect',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'deny_member_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_board',
				],
			),
			new Index(
				name: 'idx_categories',
				type: 'unique',
				columns: [
					'id_cat',
					'id_board',
				],
			),
			new Index(
				name: 'idx_id_parent',
				columns: [
					'id_parent',
				],
			),
			new Index(
				name: 'idx_id_msg_updated',
				columns: [
					'id_msg_updated',
				],
			),
			new Index(
				name: 'idx_member_groups',
				columns: [
					'member_groups(48)',
				],
			),
		];
	}
}

?>