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

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogSpiderHits extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_spider_hits';

		$this->columns = [
			'id_hit' => new Column(
				name: 'id_hit',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_spider' => new Column(
				name: 'id_spider',
				type: 'smallint',
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
			'url' => new Column(
				name: 'url',
				type: 'varchar',
				size: 1024,
				not_null: true,
				default: '',
			),
			'processed' => new Column(
				name: 'processed',
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
						'name' => 'id_hit',
					],
				],
			),
			'idx_id_spider' => new DbIndex(
				name: 'idx_id_spider',
				columns: [
					[
						'name' => 'id_spider',
					],
				],
			),
			'idx_log_time' => new DbIndex(
				name: 'idx_log_time',
				columns: [
					[
						'name' => 'log_time',
					],
				],
			),
			'idx_processed' => new DbIndex(
				name: 'idx_processed',
				columns: [
					[
						'name' => 'processed',
					],
				],
			),
		];

		parent::__construct();
	}
}
