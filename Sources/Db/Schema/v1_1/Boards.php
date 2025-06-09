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
			'ID_MSG_UPDATED' => 1,
			'name' => '{$default_board_name}',
			'description' => '{$default_board_description}',
			'numTopics' => 1,
			'numPosts' => 1,
			'memberGroups' => '-1,0',
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
			'ID_MSG_UPDATED' => new Column(
				name: 'ID_MSG_UPDATED',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'memberGroups' => new Column(
				name: 'memberGroups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '-1,0',
			),
			'name' => new Column(
				name: 'name',
				type: 'tinytext',
				not_null: true,
			),
			'description' => new Column(
				name: 'description',
				type: 'text',
				not_null: true,
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
			'permission_mode' => new Column(
				name: 'permission_mode',
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
			'ID_PARENT' => new DbIndex(
				name: 'ID_PARENT',
				columns: [
					[
						'name' => 'ID_PARENT',
					],
				],
			),
			'ID_MSG_UPDATED' => new DbIndex(
				name: 'ID_MSG_UPDATED',
				columns: [
					[
						'name' => 'ID_MSG_UPDATED',
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
