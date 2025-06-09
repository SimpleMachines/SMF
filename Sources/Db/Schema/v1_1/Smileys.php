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
			'description' => '{$default_smiley_smiley}',
			'smileyOrder' => 0,
			'hidden' => 0,
		],
		[
			'code' => ';)',
			'filename' => 'wink.gif',
			'description' => '{$default_wink_smiley}',
			'smileyOrder' => 1,
			'hidden' => 0,
		],
		[
			'code' => ':D',
			'filename' => 'cheesy.gif',
			'description' => '{$default_cheesy_smiley}',
			'smileyOrder' => 2,
			'hidden' => 0,
		],
		[
			'code' => ';D',
			'filename' => 'grin.gif',
			'description' => '{$default_grin_smiley}',
			'smileyOrder' => 3,
			'hidden' => 0,
		],
		[
			'code' => '>:(',
			'filename' => 'angry.gif',
			'description' => '{$default_angry_smiley}',
			'smileyOrder' => 4,
			'hidden' => 0,
		],
		[
			'code' => ':(',
			'filename' => 'sad.gif',
			'description' => '{$default_sad_smiley}',
			'smileyOrder' => 5,
			'hidden' => 0,
		],
		[
			'code' => ':o',
			'filename' => 'shocked.gif',
			'description' => '{$default_shocked_smiley}',
			'smileyOrder' => 6,
			'hidden' => 0,
		],
		[
			'code' => '8)',
			'filename' => 'cool.gif',
			'description' => '{$default_cool_smiley}',
			'smileyOrder' => 7,
			'hidden' => 0,
		],
		[
			'code' => '???',
			'filename' => 'huh.gif',
			'description' => '{$default_huh_smiley}',
			'smileyOrder' => 8,
			'hidden' => 0,
		],
		[
			'code' => '::)',
			'filename' => 'rolleyes.gif',
			'description' => '{$default_roll_eyes_smiley}',
			'smileyOrder' => 9,
			'hidden' => 0,
		],
		[
			'code' => ':P',
			'filename' => 'tongue.gif',
			'description' => '{$default_tongue_smiley}',
			'smileyOrder' => 10,
			'hidden' => 0,
		],
		[
			'code' => ':-[',
			'filename' => 'embarrassed.gif',
			'description' => '{$default_embarrassed_smiley}',
			'smileyOrder' => 11,
			'hidden' => 0,
		],
		[
			'code' => ':-X',
			'filename' => 'lipsrsealed.gif',
			'description' => '{$default_lips_sealed_smiley}',
			'smileyOrder' => 12,
			'hidden' => 0,
		],
		[
			'code' => ':-\\',
			'filename' => 'undecided.gif',
			'description' => '{$default_undecided_smiley}',
			'smileyOrder' => 13,
			'hidden' => 0,
		],
		[
			'code' => ':-*',
			'filename' => 'kiss.gif',
			'description' => '{$default_kiss_smiley}',
			'smileyOrder' => 14,
			'hidden' => 0,
		],
		[
			'code' => ':\'(',
			'filename' => 'cry.gif',
			'description' => '{$default_cry_smiley}',
			'smileyOrder' => 15,
			'hidden' => 0,
		],
		[
			'code' => '>:D',
			'filename' => 'evil.gif',
			'description' => '{$default_evil_smiley}',
			'smileyOrder' => 16,
			'hidden' => 1,
		],
		[
			'code' => '^-^',
			'filename' => 'azn.gif',
			'description' => '{$default_azn_smiley}',
			'smileyOrder' => 17,
			'hidden' => 1,
		],
		[
			'code' => 'O0',
			'filename' => 'afro.gif',
			'description' => '{$default_afro_smiley}',
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
				type: 'smallint',
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
		];

		parent::__construct();
	}
}
