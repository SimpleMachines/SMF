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

namespace SMF\Db\Schema\v1_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
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
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'ID_BOARD' => 1,
			'ID_CAT' => 1,
			'boardOrder' => 1,
			'ID_LAST_MSG' => 1,
			'lastUpdated' => '{$current_time}',
			'name' => '{$default_board_name}',
			'description' => '{$default_board_description}',
			'numTopics' => 1,
			'numPosts' => 1,
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
			'ID_BOARD' => new Column(
				name: 'ID_BOARD',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'ID_CAT' => new Column(
				name: 'ID_CAT',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'childLevel' => new Column(
				name: 'childLevel',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_PARENT' => new Column(
				name: 'ID_PARENT',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'boardOrder' => new Column(
				name: 'boardOrder',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'ID_LAST_MSG' => new Column(
				name: 'ID_LAST_MSG',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'lastUpdated' => new Column(
				name: 'lastUpdated',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'memberGroups' => new Column(
				name: 'memberGroups',
				type: 'varchar',
				size: 128,
				not_null: true,
				default: '-1,0',
			),
			'name' => new Column(
				name: 'name',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'text',
				not_null: true,
				default: '',
			),
			'numTopics' => new Column(
				name: 'numTopics',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'numPosts' => new Column(
				name: 'numPosts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'countPosts' => new Column(
				name: 'countPosts',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'ID_THEME' => new Column(
				name: 'ID_THEME',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'use_local_permissions' => new Column(
				name: 'use_local_permissions',
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
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'categories' => new DbIndex(
				type: 'unique',
				name: 'categories',
				columns: [
					[
						'name' => 'ID_CAT',
					],
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'children' => new DbIndex(
				type: 'unique',
				name: 'children',
				columns: [
					[
						'name' => 'childLevel',
					],
					[
						'name' => 'ID_PARENT',
					],
					[
						'name' => 'boardOrder',
					],
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'boardOrder' => new DbIndex(
				name: 'boardOrder',
				columns: [
					[
						'name' => 'boardOrder',
					],
				],
			),
			'lastUpdated' => new DbIndex(
				name: 'lastUpdated',
				columns: [
					[
						'name' => 'lastUpdated',
					],
				],
			),
			'memberGroups' => new DbIndex(
				name: 'memberGroups',
				columns: [
					[
						'name' => 'memberGroups',
						'size' => 48,
					],
				],
			),
		];

		parent::__construct();
	}
}
