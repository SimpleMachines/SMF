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
class BoardPermissionsView extends Table
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
		'id_group' => 'int',
		'id_board' => 'int',
		'deny' => 'int',
	];

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
			new Column(
				name: 'id_group',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
			),
			new Column(
				name: 'deny',
				type: 'smallint',
				not_null: true,
			),
		];

		$this->indices = [
			new Index(
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