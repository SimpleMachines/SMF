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

namespace SMF\Db\Schema\v1_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogPolls extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_polls';

		$this->columns = [
			'ID_POLL' => new Column(
				name: 'ID_POLL',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_CHOICE' => new Column(
				name: 'ID_CHOICE',
				type: 'tinyint',
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
						'name' => 'ID_POLL',
					],
					[
						'name' => 'ID_MEMBER',
					],
					[
						'name' => 'ID_CHOICE',
					],
				],
			),
		];

		parent::__construct();
	}
}
