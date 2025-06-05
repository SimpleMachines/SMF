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
			'stars' => '5#staradmin.gif',
			'group_type' => 1,
		],
		[
			'id_group' => 2,
			'group_name' => '{$default_global_moderator_group}',
			'description' => '',
			'online_color' => '#0000FF',
			'min_posts' => -1,
			'stars' => '5#stargmod.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 3,
			'group_name' => '{$default_moderator_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => -1,
			'stars' => '5#starmod.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 4,
			'group_name' => '{$default_newbie_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 0,
			'stars' => '1#star.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 5,
			'group_name' => '{$default_junior_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 50,
			'stars' => '2#star.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 6,
			'group_name' => '{$default_full_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 100,
			'stars' => '3#star.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 7,
			'group_name' => '{$default_senior_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 250,
			'stars' => '4#star.gif',
			'group_type' => 0,
		],
		[
			'id_group' => 8,
			'group_name' => '{$default_hero_group}',
			'description' => '',
			'online_color' => '',
			'min_posts' => 500,
			'stars' => '5#star.gif',
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
				not_null: true,
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
			'stars' => new Column(
				name: 'stars',
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
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_group',
					],
				],
			),
			'min_posts' => new DbIndex(
				name: 'min_posts',
				columns: [
					[
						'name' => 'min_posts',
					],
				],
			),
		];

		parent::__construct();
	}
}
