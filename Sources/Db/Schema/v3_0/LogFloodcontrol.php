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
class LogFloodcontrol extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [];

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
		$this->name = 'log_floodcontrol';

		$this->columns = [
			new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'log_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'log_type',
				type: 'varchar',
				size: 30,
				default: 'post',
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'ip',
					'log_type',
				],
			),
		];
	}
}

?>