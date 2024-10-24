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
class Categories extends Table
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
			'id_cat' => 1,
			'cat_order' => 0,
			'name' => '{$default_category_name}',
			'description' => '',
			'can_collapse' => 1,
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
		$this->name = 'categories';

		$this->columns = [
			'id_cat' => new Column(
				name: 'id_cat',
				type: 'tinyint',
				unsigned: true,
				auto: true,
			),
			'cat_order' => new Column(
				name: 'cat_order',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'name' => new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			'can_collapse' => new Column(
				name: 'can_collapse',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_cat',
				],
			),
		];
	}
}

?>