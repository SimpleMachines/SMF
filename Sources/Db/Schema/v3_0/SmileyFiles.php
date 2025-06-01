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
class SmileyFiles extends Table
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
			'smiley_set' => 'fugue',
			'filename' => 'smiley.png',
		],
		[
			'id_smiley' => 1,
			'smiley_set' => 'alienine',
			'filename' => 'smiley.png',
		],
		[
			'id_smiley' => 2,
			'smiley_set' => 'fugue',
			'filename' => 'wink.png',
		],
		[
			'id_smiley' => 2,
			'smiley_set' => 'alienine',
			'filename' => 'wink.png',
		],
		[
			'id_smiley' => 3,
			'smiley_set' => 'fugue',
			'filename' => 'cheesy.png',
		],
		[
			'id_smiley' => 3,
			'smiley_set' => 'alienine',
			'filename' => 'cheesy.png',
		],
		[
			'id_smiley' => 4,
			'smiley_set' => 'fugue',
			'filename' => 'grin.png',
		],
		[
			'id_smiley' => 4,
			'smiley_set' => 'alienine',
			'filename' => 'grin.png',
		],
		[
			'id_smiley' => 5,
			'smiley_set' => 'fugue',
			'filename' => 'angry.png',
		],
		[
			'id_smiley' => 5,
			'smiley_set' => 'alienine',
			'filename' => 'angry.png',
		],
		[
			'id_smiley' => 6,
			'smiley_set' => 'fugue',
			'filename' => 'sad.png',
		],
		[
			'id_smiley' => 6,
			'smiley_set' => 'alienine',
			'filename' => 'sad.png',
		],
		[
			'id_smiley' => 7,
			'smiley_set' => 'fugue',
			'filename' => 'shocked.png',
		],
		[
			'id_smiley' => 7,
			'smiley_set' => 'alienine',
			'filename' => 'shocked.png',
		],
		[
			'id_smiley' => 8,
			'smiley_set' => 'fugue',
			'filename' => 'cool.png',
		],
		[
			'id_smiley' => 8,
			'smiley_set' => 'alienine',
			'filename' => 'cool.png',
		],
		[
			'id_smiley' => 9,
			'smiley_set' => 'fugue',
			'filename' => 'huh.png',
		],
		[
			'id_smiley' => 9,
			'smiley_set' => 'alienine',
			'filename' => 'huh.png',
		],
		[
			'id_smiley' => 10,
			'smiley_set' => 'fugue',
			'filename' => 'rolleyes.png',
		],
		[
			'id_smiley' => 10,
			'smiley_set' => 'alienine',
			'filename' => 'rolleyes.png',
		],
		[
			'id_smiley' => 11,
			'smiley_set' => 'fugue',
			'filename' => 'tongue.png',
		],
		[
			'id_smiley' => 11,
			'smiley_set' => 'alienine',
			'filename' => 'tongue.png',
		],
		[
			'id_smiley' => 12,
			'smiley_set' => 'fugue',
			'filename' => 'embarrassed.png',
		],
		[
			'id_smiley' => 12,
			'smiley_set' => 'alienine',
			'filename' => 'embarrassed.png',
		],
		[
			'id_smiley' => 13,
			'smiley_set' => 'fugue',
			'filename' => 'lipsrsealed.png',
		],
		[
			'id_smiley' => 13,
			'smiley_set' => 'alienine',
			'filename' => 'lipsrsealed.png',
		],
		[
			'id_smiley' => 14,
			'smiley_set' => 'fugue',
			'filename' => 'undecided.png',
		],
		[
			'id_smiley' => 14,
			'smiley_set' => 'alienine',
			'filename' => 'undecided.png',
		],
		[
			'id_smiley' => 15,
			'smiley_set' => 'fugue',
			'filename' => 'kiss.png',
		],
		[
			'id_smiley' => 15,
			'smiley_set' => 'alienine',
			'filename' => 'kiss.png',
		],
		[
			'id_smiley' => 16,
			'smiley_set' => 'fugue',
			'filename' => 'cry.png',
		],
		[
			'id_smiley' => 16,
			'smiley_set' => 'alienine',
			'filename' => 'cry.png',
		],
		[
			'id_smiley' => 17,
			'smiley_set' => 'fugue',
			'filename' => 'evil.png',
		],
		[
			'id_smiley' => 17,
			'smiley_set' => 'alienine',
			'filename' => 'evil.png',
		],
		[
			'id_smiley' => 18,
			'smiley_set' => 'fugue',
			'filename' => 'azn.png',
		],
		[
			'id_smiley' => 18,
			'smiley_set' => 'alienine',
			'filename' => 'azn.png',
		],
		[
			'id_smiley' => 19,
			'smiley_set' => 'fugue',
			'filename' => 'afro.png',
		],
		[
			'id_smiley' => 19,
			'smiley_set' => 'alienine',
			'filename' => 'afro.png',
		],
		[
			'id_smiley' => 20,
			'smiley_set' => 'fugue',
			'filename' => 'laugh.png',
		],
		[
			'id_smiley' => 20,
			'smiley_set' => 'alienine',
			'filename' => 'laugh.png',
		],
		[
			'id_smiley' => 21,
			'smiley_set' => 'fugue',
			'filename' => 'police.png',
		],
		[
			'id_smiley' => 21,
			'smiley_set' => 'alienine',
			'filename' => 'police.png',
		],
		[
			'id_smiley' => 22,
			'smiley_set' => 'fugue',
			'filename' => 'angel.png',
		],
		[
			'id_smiley' => 22,
			'smiley_set' => 'alienine',
			'filename' => 'angel.png',
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
		$this->name = 'smiley_files';

		$this->columns = [
			'id_smiley' => new Column(
				name: 'id_smiley',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'smiley_set' => new Column(
				name: 'smiley_set',
				type: 'varchar',
				size: 48,
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
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_smiley',
					'smiley_set',
				],
			),
		];

		parent::__construct();
	}
}
