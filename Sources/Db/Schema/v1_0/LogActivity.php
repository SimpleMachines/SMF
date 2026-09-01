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
class LogActivity extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_activity';

		$this->columns = [
			'date' => new Column(
				name: 'date',
				type: 'date',
				not_null: true,
				default: '0000-00-00',
			),
			'hits' => new Column(
				name: 'hits',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'topics' => new Column(
				name: 'topics',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'posts' => new Column(
				name: 'posts',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'registers' => new Column(
				name: 'registers',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'mostOn' => new Column(
				name: 'mostOn',
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
						'name' => 'date',
					],
				],
			),
			'hits' => new DbIndex(
				name: 'hits',
				columns: [
					[
						'name' => 'hits',
					],
				],
			),
			'mostOn' => new DbIndex(
				name: 'mostOn',
				columns: [
					[
						'name' => 'mostOn',
					],
				],
			),
		];

		parent::__construct();
	}
}
