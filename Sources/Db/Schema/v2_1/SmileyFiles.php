<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class SmileyFiles extends Table
{
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
					[
						'name' => 'id_smiley',
					],
					[
						'name' => 'smiley_set',
					],
				],
			),
		];
	}
}
