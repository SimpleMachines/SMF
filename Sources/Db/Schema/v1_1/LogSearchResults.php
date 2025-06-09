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
class LogSearchResults extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_search_results';

		$this->columns = [
			'ID_SEARCH' => new Column(
				name: 'ID_SEARCH',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_TOPIC' => new Column(
				name: 'ID_TOPIC',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MSG' => new Column(
				name: 'ID_MSG',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'relevance' => new Column(
				name: 'relevance',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'num_matches' => new Column(
				name: 'num_matches',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_SEARCH',
					],
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
		];

		parent::__construct();
	}
}
