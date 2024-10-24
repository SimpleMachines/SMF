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
class Boards extends Table
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
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			'id_cat' => new Column(
				name: 'id_cat',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'child_level' => new Column(
				name: 'child_level',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_parent' => new Column(
				name: 'id_parent',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'board_order' => new Column(
				name: 'board_order',
				type: 'smallint',
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
			'id_msg_updated' => new Column(
				name: 'id_msg_updated',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'member_groups' => new Column(
				name: 'member_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '-1,0',
			),
			'id_profile' => new Column(
				name: 'id_profile',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'name' => new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			'num_topics' => new Column(
				name: 'num_topics',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'num_posts' => new Column(
				name: 'num_posts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'count_posts' => new Column(
				name: 'count_posts',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'id_theme' => new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'override_theme' => new Column(
				name: 'override_theme',
				type: 'tinyint',
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
			'unapproved_topics' => new Column(
				name: 'unapproved_topics',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'redirect' => new Column(
				name: 'redirect',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'deny_member_groups' => new Column(
				name: 'deny_member_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_board',
				],
			),
			'idx_categories' => new DbIndex(
				name: 'idx_categories',
				type: 'unique',
				columns: [
					'id_cat',
					'id_board',
				],
			),
			'idx_id_parent' => new DbIndex(
				name: 'idx_id_parent',
				columns: [
					'id_parent',
				],
			),
			'idx_id_msg_updated' => new DbIndex(
				name: 'idx_id_msg_updated',
				columns: [
					'id_msg_updated',
				],
			),
			'idx_member_groups' => new DbIndex(
				name: 'idx_member_groups',
				columns: [
					'member_groups(48)',
				],
			),
		];
	}
}

?>