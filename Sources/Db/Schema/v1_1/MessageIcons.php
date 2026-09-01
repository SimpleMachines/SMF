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
class MessageIcons extends Table
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
			'filename' => 'xx',
			'title' => 'Standard',
			'iconOrder' => 0,
		],
		[
			'filename' => 'thumbup',
			'title' => 'Thumb Up',
			'iconOrder' => 1,
		],
		[
			'filename' => 'thumbdown',
			'title' => 'Thumb Down',
			'iconOrder' => 2,
		],
		[
			'filename' => 'exclamation',
			'title' => 'Exclamation point',
			'iconOrder' => 3,
		],
		[
			'filename' => 'question',
			'title' => 'Question mark',
			'iconOrder' => 4,
		],
		[
			'filename' => 'lamp',
			'title' => 'Lamp',
			'iconOrder' => 5,
		],
		[
			'filename' => 'smiley',
			'title' => 'Smiley',
			'iconOrder' => 6,
		],
		[
			'filename' => 'angry',
			'title' => 'Angry',
			'iconOrder' => 7,
		],
		[
			'filename' => 'cheesy',
			'title' => 'Cheesy',
			'iconOrder' => 8,
		],
		[
			'filename' => 'grin',
			'title' => 'Grin',
			'iconOrder' => 9,
		],
		[
			'filename' => 'sad',
			'title' => 'Sad',
			'iconOrder' => 10,
		],
		[
			'filename' => 'wink',
			'title' => 'Wink',
			'iconOrder' => 11,
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
		$this->name = 'message_icons';

		$this->columns = [
			'ID_ICON' => new Column(
				name: 'ID_ICON',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'filename' => new Column(
				name: 'filename',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'ID_BOARD' => new Column(
				name: 'ID_BOARD',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'iconOrder' => new Column(
				name: 'iconOrder',
				type: 'smallint',
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
						'name' => 'ID_ICON',
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
