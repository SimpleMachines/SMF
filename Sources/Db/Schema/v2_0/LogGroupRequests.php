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

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogGroupRequests extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_group_requests';

		$this->columns = [
			'id_request' => new Column(
				name: 'id_request',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time_applied' => new Column(
				name: 'time_applied',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'reason' => new Column(
				name: 'reason',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_request',
					],
				],
			),
			'id_member' => new DbIndex(
				type: 'unique',
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_group',
					],
				],
			),
		];

		parent::__construct();
	}
}
