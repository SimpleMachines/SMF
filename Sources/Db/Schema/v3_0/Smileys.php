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

namespace SMF\Db\Schema\v3_0;

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
			'id_smiley' => 1,
			'code' => ':)',
			'description' => '{$default_smiley_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 0,
			'hidden' => 0,
		],
		[
			'id_smiley' => 2,
			'code' => ';)',
			'description' => '{$default_wink_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 1,
			'hidden' => 0,
		],
		[
			'id_smiley' => 3,
			'code' => ':D',
			'description' => '{$default_cheesy_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 2,
			'hidden' => 0,
		],
		[
			'id_smiley' => 4,
			'code' => ';D',
			'description' => '{$default_grin_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 3,
			'hidden' => 0,
		],
		[
			'id_smiley' => 5,
			'code' => '>:(',
			'description' => '{$default_angry_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 4,
			'hidden' => 0,
		],
		[
			'id_smiley' => 6,
			'code' => ':(',
			'description' => '{$default_sad_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 5,
			'hidden' => 0,
		],
		[
			'id_smiley' => 7,
			'code' => ':o',
			'description' => '{$default_shocked_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 6,
			'hidden' => 0,
		],
		[
			'id_smiley' => 8,
			'code' => '8)',
			'description' => '{$default_cool_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 7,
			'hidden' => 0,
		],
		[
			'id_smiley' => 9,
			'code' => '???',
			'description' => '{$default_huh_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 8,
			'hidden' => 0,
		],
		[
			'id_smiley' => 10,
			'code' => '::)',
			'description' => '{$default_roll_eyes_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 9,
			'hidden' => 0,
		],
		[
			'id_smiley' => 11,
			'code' => ':P',
			'description' => '{$default_tongue_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 10,
			'hidden' => 0,
		],
		[
			'id_smiley' => 12,
			'code' => ':-[',
			'description' => '{$default_embarrassed_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 11,
			'hidden' => 0,
		],
		[
			'id_smiley' => 13,
			'code' => ':-X',
			'description' => '{$default_lips_sealed_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 12,
			'hidden' => 0,
		],
		[
			'id_smiley' => 14,
			'code' => ':-\\',
			'description' => '{$default_undecided_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 13,
			'hidden' => 0,
		],
		[
			'id_smiley' => 15,
			'code' => ':-*',
			'description' => '{$default_kiss_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 14,
			'hidden' => 0,
		],
		[
			'id_smiley' => 16,
			'code' => ':\'(',
			'description' => '{$default_cry_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 15,
			'hidden' => 0,
		],
		[
			'id_smiley' => 17,
			'code' => '>:D',
			'description' => '{$default_evil_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 16,
			'hidden' => 1,
		],
		[
			'id_smiley' => 18,
			'code' => '^-^',
			'description' => '{$default_azn_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 17,
			'hidden' => 1,
		],
		[
			'id_smiley' => 19,
			'code' => 'O0',
			'description' => '{$default_afro_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 18,
			'hidden' => 1,
		],
		[
			'id_smiley' => 20,
			'code' => ':))',
			'description' => '{$default_laugh_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 19,
			'hidden' => 1,
		],
		[
			'id_smiley' => 21,
			'code' => 'C:-)',
			'description' => '{$default_police_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 20,
			'hidden' => 1,
		],
		[
			'id_smiley' => 22,
			'code' => 'O:-)',
			'description' => '{$default_angel_smiley}',
			'smiley_row' => 0,
			'smiley_order' => 21,
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
			'id_smiley' => new Column(
				name: 'id_smiley',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			'code' => new Column(
				name: 'code',
				type: 'varchar',
				size: 30,
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
			'smiley_row' => new Column(
				name: 'smiley_row',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'smiley_order' => new Column(
				name: 'smiley_order',
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
					'id_smiley',
				],
			),
		];

		parent::__construct();
	}
}
