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
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

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
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [
		'id_cat' => 'int',
		'cat_order' => 'int',
		'name' => 'string',
		'description' => 'string',
		'can_collapse' => 'int',
	];

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
			new Column(
				name: 'id_cat',
				type: 'tinyint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'cat_order',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'description',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'can_collapse',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_cat',
				],
			),
		];
	}
}

?>