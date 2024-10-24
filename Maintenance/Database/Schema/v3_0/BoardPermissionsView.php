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
class BoardPermissionsView extends Table
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
			'id_group' => -1,
			'id_board' => 1,
			'deny' => 0,
		],
		[
			'id_group' => 0,
			'id_board' => 1,
			'deny' => 0,
		],
		[
			'id_group' => 2,
			'id_board' => 1,
			'deny' => 0,
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
		$this->name = 'board_permissions_view';

		$this->columns = [
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
			),
			'deny' => new Column(
				name: 'deny',
				type: 'smallint',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_group',
					'id_board',
					'deny',
				],
			),
		];
	}
}

?>