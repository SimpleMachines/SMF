<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Index;
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
	public array $initial_data = [];

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
			new Column(
				name: 'id_smiley',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'smiley_set',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'filename',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_smiley',
					'smiley_set',
				],
			),
		];
	}
}

?>