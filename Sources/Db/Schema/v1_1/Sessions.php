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
class Sessions extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'sessions';

		$this->columns = [
			'session_id' => new Column(
				name: 'session_id',
				type: 'char',
				size: 32,
				not_null: true,
			),
			'last_update' => new Column(
				name: 'last_update',
				type: 'int',
				unsigned: true,
				not_null: true,
			),
			'data' => new Column(
				name: 'data',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'session_id',
					],
				],
			),
		];

		parent::__construct();
	}
}
