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
class Membergroups extends Table
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
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			'group_name' => new Column(
				name: 'group_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			'online_color' => new Column(
				name: 'online_color',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'min_posts' => new Column(
				name: 'min_posts',
				type: 'mediumint',
				not_null: true,
				default: -1,
			),
			'max_messages' => new Column(
				name: 'max_messages',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'icons' => new Column(
				name: 'icons',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'group_type' => new Column(
				name: 'group_type',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'hidden' => new Column(
				name: 'hidden',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'id_parent' => new Column(
				name: 'id_parent',
				type: 'smallint',
				not_null: true,
				default: -2,
			),
			'tfa_required' => new Column(
				name: 'tfa_required',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_group',
				],
			),
			'idx_min_posts' => new DbIndex(
				name: 'idx_min_posts',
				columns: [
					'min_posts',
				],
			),
		];
	}
}

?>