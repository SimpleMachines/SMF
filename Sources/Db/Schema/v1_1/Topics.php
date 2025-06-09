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
			'ID_TOPIC' => 1,
			'ID_BOARD' => 1,
			'ID_FIRST_MSG' => 1,
			'ID_LAST_MSG' => 1,
			'ID_MEMBER_STARTED' => 0,
			'ID_MEMBER_UPDATED' => 0,
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
			'ID_TOPIC' => new Column(
				name: 'ID_TOPIC',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'isSticky' => new Column(
				name: 'isSticky',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'ID_BOARD' => new Column(
				name: 'ID_BOARD',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_FIRST_MSG' => new Column(
				name: 'ID_FIRST_MSG',
				type: 'int',
				unsigned: true,
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
			'ID_MEMBER_STARTED' => new Column(
				name: 'ID_MEMBER_STARTED',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MEMBER_UPDATED' => new Column(
				name: 'ID_MEMBER_UPDATED',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_POLL' => new Column(
				name: 'ID_POLL',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'numReplies' => new Column(
				name: 'numReplies',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'numViews' => new Column(
				name: 'numViews',
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
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
			'lastMessage' => new DbIndex(
				type: 'unique',
				name: 'lastMessage',
				columns: [
					[
						'name' => 'ID_LAST_MSG',
					],
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'firstMessage' => new DbIndex(
				type: 'unique',
				name: 'firstMessage',
				columns: [
					[
						'name' => 'ID_FIRST_MSG',
					],
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'poll' => new DbIndex(
				type: 'unique',
				name: 'poll',
				columns: [
					[
						'name' => 'ID_POLL',
					],
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
			'isSticky' => new DbIndex(
				name: 'isSticky',
				columns: [
					[
						'name' => 'isSticky',
					],
				],
			),
			'ID_BOARD' => new DbIndex(
				name: 'ID_BOARD',
				columns: [
					[
						'name' => 'ID_BOARD',
					],
				],
			),
		];

		parent::__construct();
	}
}
