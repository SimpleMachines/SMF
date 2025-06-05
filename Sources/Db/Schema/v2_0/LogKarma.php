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
class LogKarma extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_karma';

		$this->columns = [
			'id_target' => new Column(
				name: 'id_target',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_executor' => new Column(
				name: 'id_executor',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'log_time' => new Column(
				name: 'log_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'action' => new Column(
				name: 'action',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_target',
					],
					[
						'name' => 'id_executor',
					],
				],
			),
			'log_time' => new DbIndex(
				name: 'log_time',
				columns: [
					[
						'name' => 'log_time',
					],
				],
			),
		];

		parent::__construct();
	}
}
