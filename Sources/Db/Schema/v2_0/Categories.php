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

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Categories extends Table
{
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
				not_null: true,
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
					[
						'name' => 'id_cat',
					],
				],
			),
		];

		parent::__construct();
	}
}
