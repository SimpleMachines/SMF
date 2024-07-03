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
class Qanda extends Table
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
		$this->name = 'qanda';

		$this->columns = [
			new Column(
				name: 'id_question',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'lngfile',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'answers',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_question',
				],
			),
			new DbIndex(
				name: 'idx_lngfile',
				columns: [
					'lngfile',
				],
			),
		];
	}
}

?>