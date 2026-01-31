<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
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
class Mentions extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'mentions';

		$this->columns = [
			'content_id' => new Column(
				name: 'content_id',
				type: 'int',
				default: 0,
			),
			'content_type' => new Column(
				name: 'content_type',
				type: 'varchar',
				size: 10,
				default: '',
			),
			'id_mentioned' => new Column(
				name: 'id_mentioned',
				type: 'int',
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'time' => new Column(
				name: 'time',
				type: 'int',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'content_id',
					],
					[
						'name' => 'content_type',
					],
					[
						'name' => 'id_mentioned',
					],
				],
			),
			'content' => new DbIndex(
				name: 'content',
				columns: [
					[
						'name' => 'content_id',
					],
					[
						'name' => 'content_type',
					],
				],
			),
			'mentionee' => new DbIndex(
				name: 'mentionee',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
		];

		parent::__construct();
	}
}
