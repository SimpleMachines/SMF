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
class LogSpiderStats extends Table
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
		$this->name = 'log_spider_stats';

		$this->columns = [
			new Column(
				name: 'id_spider',
				type: 'smallint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'page_hits',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'last_seen',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'stat_date',
				type: 'date',
				default: '1004-01-01',
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'stat_date',
					'id_spider',
				],
			),
		];
	}
}

?>