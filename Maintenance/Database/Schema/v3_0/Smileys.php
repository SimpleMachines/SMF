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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

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
	public array $initial_data = [];

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
	}
}

?>