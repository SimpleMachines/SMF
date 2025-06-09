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
class Smileys extends Table
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
			'code' => ':)',
			'filename' => 'smiley.gif',
			'description' => 'Smiley',
			'smileyOrder' => 0,
			'hidden' => 0,
		],
		[
			'code' => ';)',
			'filename' => 'wink.gif',
			'description' => 'Wink',
			'smileyOrder' => 1,
			'hidden' => 0,
		],
		[
			'code' => ':D',
			'filename' => 'cheesy.gif',
			'description' => 'Cheesy',
			'smileyOrder' => 2,
			'hidden' => 0,
		],
		[
			'code' => ';D',
			'filename' => 'grin.gif',
			'description' => 'Grin',
			'smileyOrder' => 3,
			'hidden' => 0,
		],
		[
			'code' => '>:(',
			'filename' => 'angry.gif',
			'description' => 'Angry',
			'smileyOrder' => 4,
			'hidden' => 0,
		],
		[
			'code' => ':(',
			'filename' => 'sad.gif',
			'description' => 'Sad',
			'smileyOrder' => 5,
			'hidden' => 0,
		],
		[
			'code' => ':o',
			'filename' => 'shocked.gif',
			'description' => 'Shocked',
			'smileyOrder' => 6,
			'hidden' => 0,
		],
		[
			'code' => '8)',
			'filename' => 'cool.gif',
			'description' => 'Cool',
			'smileyOrder' => 7,
			'hidden' => 0,
		],
		[
			'code' => '???',
			'filename' => 'huh.gif',
			'description' => 'Huh',
			'smileyOrder' => 8,
			'hidden' => 0,
		],
		[
			'code' => '::)',
			'filename' => 'rolleyes.gif',
			'description' => 'Roll Eyes',
			'smileyOrder' => 9,
			'hidden' => 0,
		],
		[
			'code' => ':P',
			'filename' => 'tongue.gif',
			'description' => 'Tongue',
			'smileyOrder' => 10,
			'hidden' => 0,
		],
		[
			'code' => ':-[',
			'filename' => 'embarassed.gif',
			'description' => 'Embarrassed',
			'smileyOrder' => 11,
			'hidden' => 0,
		],
		[
			'code' => ':-X',
			'filename' => 'lipsrsealed.gif',
			'description' => 'Lips Sealed',
			'smileyOrder' => 12,
			'hidden' => 0,
		],
		[
			'code' => ':-\\',
			'filename' => 'undecided.gif',
			'description' => 'Undecided',
			'smileyOrder' => 13,
			'hidden' => 0,
		],
		[
			'code' => ':-*',
			'filename' => 'kiss.gif',
			'description' => 'Kiss',
			'smileyOrder' => 14,
			'hidden' => 0,
		],
		[
			'code' => ':\'(',
			'filename' => 'cry.gif',
			'description' => 'Cry',
			'smileyOrder' => 15,
			'hidden' => 0,
		],
		[
			'code' => '>:D',
			'filename' => 'evil.gif',
			'description' => 'Evil',
			'smileyOrder' => 16,
			'hidden' => 1,
		],
		[
			'code' => '^-^',
			'filename' => 'azn.gif',
			'description' => 'Azn',
			'smileyOrder' => 17,
			'hidden' => 1,
		],
		[
			'code' => 'O0',
			'filename' => 'afro.gif',
			'description' => 'Afro',
			'smileyOrder' => 18,
			'hidden' => 1,
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
		$this->name = 'smileys';

		$this->columns = [
			'ID_SMILEY' => new Column(
				name: 'ID_SMILEY',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'code' => new Column(
				name: 'code',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'filename' => new Column(
				name: 'filename',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'smileyRow' => new Column(
				name: 'smileyRow',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'smileyOrder' => new Column(
				name: 'smileyOrder',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hidden' => new Column(
				name: 'hidden',
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
						'name' => 'ID_SMILEY',
					],
				],
			),
			'smileyOrder' => new DbIndex(
				name: 'smileyOrder',
				columns: [
					[
						'name' => 'smileyOrder',
					],
				],
			),
		];

		parent::__construct();
	}
}
