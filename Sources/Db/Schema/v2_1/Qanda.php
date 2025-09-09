<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Qanda extends Table
{
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
			'id_question' => new Column(
				name: 'id_question',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'lngfile' => new Column(
				name: 'lngfile',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'question' => new Column(
				name: 'question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'answers' => new Column(
				name: 'answers',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_question',
					],
				],
			),
			'idx_lngfile' => new DbIndex(
				name: 'idx_lngfile',
				columns: [
					[
						'name' => 'lngfile',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
		];

		parent::__construct();
	}
}
