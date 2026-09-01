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
			'ID_CAT' => new Column(
				name: 'ID_CAT',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'catOrder' => new Column(
				name: 'catOrder',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'name' => new Column(
				name: 'name',
				type: 'tinytext',
				not_null: true,
			),
			'canCollapse' => new Column(
				name: 'canCollapse',
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
						'name' => 'ID_CAT',
					],
				],
			),
		];

		parent::__construct();
	}
}
