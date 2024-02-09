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
class Membergroups extends Table
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
		'id_group' => 'int',
		'group_name' => 'string',
		'description' => 'string',
		'online_color' => 'string',
		'min_posts' => 'int',
		'icons' => 'string',
		'group_type' => 'int',
	];

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_group' => 1,
			'group_name' => '{$default_administrator_group}',
			'description' => '',
			'online_color' => '#FF0000',
			'min_posts' => -1,
			'icons' => '5#iconadmin.png',
			'group_type' => 1,
		],
		[
			'id_group' => 2,
			'group_name' => '{$default_global_moderator_group}',
			'description' => '',
			'online_color' => '#0000FF',
			'min_posts' => -1,
			'icons' => '5#icongmod.png',
			'group_type' => 0,
		],
		[
			'id_group' => 3,
			'group_name' => '{$default_moderator_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => -1,
			'icons' => '5#iconmod.png',
			'group_type' => 0,
		],
		[
			'id_group' => 4,
			'group_name' => '{$default_newbie_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 0,
			'icons' => '1#icon.png',
			'group_type' => 0,
		],
		[
			'id_group' => 5,
			'group_name' => '{$default_junior_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 50,
			'icons' => '2#icon.png',
			'group_type' => 0,
		],
		[
			'id_group' => 6,
			'group_name' => '{$default_full_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 100,
			'icons' => '3#icon.png',
			'group_type' => 0,
		],
		[
			'id_group' => 7,
			'group_name' => '{$default_senior_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 250,
			'icons' => '4#icon.png',
			'group_type' => 0,
		],
		[
			'id_group' => 8,
			'group_name' => '{$default_hero_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 500,
			'icons' => '5#icon.png',
			'group_type' => 0,
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
		$this->name = 'membergroups';

		$this->columns = [
			new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'group_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'online_color',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'min_posts',
				type: 'mediumint',
				not_null: true,
				default: -1,
			),
			new Column(
				name: 'max_messages',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'icons',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'group_type',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'hidden',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_parent',
				type: 'smallint',
				not_null: true,
				default: -2,
			),
			new Column(
				name: 'tfa_required',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_group',
				],
			),
			new Index(
				name: 'idx_min_posts',
				columns: [
					'min_posts',
				],
			),
		];
	}
}

?>