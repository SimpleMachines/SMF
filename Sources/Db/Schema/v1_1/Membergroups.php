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

namespace SMF\Db\Schema\v1_1;

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
			'ID_GROUP' => 1,
			'groupName' => '{$default_administrator_group}',
			'onlineColor' => '#FF0000',
			'minPosts' => -1,
			'stars' => '5#staradmin.gif',
		],
		[
			'ID_GROUP' => 2,
			'groupName' => '{$default_global_moderator_group}',
			'onlineColor' => '#0000FF',
			'minPosts' => -1,
			'stars' => '5#stargmod.gif',
		],
		[
			'ID_GROUP' => 3,
			'groupName' => '{$default_moderator_group}',
			'onlineColor' => '',
			'minPosts' => -1,
			'stars' => '5#starmod.gif',
		],
		[
			'ID_GROUP' => 4,
			'groupName' => '{$default_newbie_group}',
			'onlineColor' => '',
			'minPosts' => 0,
			'stars' => '1#star.gif',
		],
		[
			'ID_GROUP' => 5,
			'groupName' => '{$default_junior_group}',
			'onlineColor' => '',
			'minPosts' => 50,
			'stars' => '2#star.gif',
		],
		[
			'ID_GROUP' => 6,
			'groupName' => '{$default_full_group}',
			'onlineColor' => '',
			'minPosts' => 100,
			'stars' => '3#star.gif',
		],
		[
			'ID_GROUP' => 7,
			'groupName' => '{$default_senior_group}',
			'onlineColor' => '',
			'minPosts' => 250,
			'stars' => '4#star.gif',
		],
		[
			'ID_GROUP' => 8,
			'groupName' => '{$default_hero_group}',
			'onlineColor' => '',
			'minPosts' => 500,
			'stars' => '5#star.gif',
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
			'ID_GROUP' => new Column(
				name: 'ID_GROUP',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'groupName' => new Column(
				name: 'groupName',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'onlineColor' => new Column(
				name: 'onlineColor',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'minPosts' => new Column(
				name: 'minPosts',
				type: 'mediumint',
				not_null: true,
				default: -1,
			),
			'maxMessages' => new Column(
				name: 'maxMessages',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'stars' => new Column(
				name: 'stars',
				type: 'tinytext',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_GROUP',
					],
				],
			),
			'minPosts' => new DbIndex(
				name: 'minPosts',
				columns: [
					[
						'name' => 'minPosts',
					],
				],
			),
		];

		parent::__construct();
	}
}
