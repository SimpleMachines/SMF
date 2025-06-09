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
class PackageServers extends Table
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
			'name' => 'Simple Machines Third-party Mod Site',
			'url' => 'http://mods.simplemachines.org',
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
		$this->name = 'package_servers';

		$this->columns = [
			'ID_SERVER' => new Column(
				name: 'ID_SERVER',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'name' => new Column(
				name: 'name',
				type: 'tinytext',
				not_null: true,
			),
			'url' => new Column(
				name: 'url',
				type: 'tinytext',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_SERVER',
					],
				],
			),
		];

		parent::__construct();
	}
}
