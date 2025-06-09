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
class LogSearchSubjects extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_search_subjects';

		$this->columns = [
			'word' => new Column(
				name: 'word',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'ID_TOPIC' => new Column(
				name: 'ID_TOPIC',
				type: 'mediumint',
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
						'name' => 'word',
					],
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
			'ID_TOPIC' => new DbIndex(
				name: 'ID_TOPIC',
				columns: [
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
		];

		parent::__construct();
	}
}
