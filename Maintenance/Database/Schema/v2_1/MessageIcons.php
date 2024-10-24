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
			'icon_order' => '0',
		],
		[
			'filename' => 'thumbup',
			'title' => 'Thumb Up',
			'icon_order' => '1',
		],
		[
			'filename' => 'thumbdown',
			'title' => 'Thumb Down',
			'icon_order' => '2',
		],
		[
			'filename' => 'exclamation',
			'title' => 'Exclamation point',
			'icon_order' => '3',
		],
		[
			'filename' => 'question',
			'title' => 'Question mark',
			'icon_order' => '4',
		],
		[
			'filename' => 'lamp',
			'title' => 'Lamp',
			'icon_order' => '5',
		],
		[
			'filename' => 'smiley',
			'title' => 'Smiley',
			'icon_order' => '6',
		],
		[
			'filename' => 'angry',
			'title' => 'Angry',
			'icon_order' => '7',
		],
		[
			'filename' => 'cheesy',
			'title' => 'Cheesy',
			'icon_order' => '8',
		],
		[
			'filename' => 'grin',
			'title' => 'Grin',
			'icon_order' => '9',
		],
		[
			'filename' => 'sad',
			'title' => 'Sad',
			'icon_order' => '10',
		],
		[
			'filename' => 'wink',
			'title' => 'Wink',
			'icon_order' => '11',
		],
		[
			'filename' => 'poll',
			'title' => 'Poll',
			'icon_order' => '12',
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
			'id_icon' => new Column(
				name: 'id_icon',
				type: 'smallint',
				unsigned: true,
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
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'icon_order' => new Column(
				name: 'icon_order',
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
					'id_icon',
				],
			),
			'idx_id_board' => new DbIndex(
				name: 'idx_id_board',
				columns: [
					'id_board',
				],
			),
		];
	}
}

?>