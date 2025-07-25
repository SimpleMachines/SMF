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

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Hooks extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'hooks';

		$this->columns = [
			'id_hook' => new Column(
				name: 'id_hook',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'is_enabled' => new Column(
				name: 'is_enabled',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hook_name' => new Column(
				name: 'hook_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'func' => new Column(
				name: 'func',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'file' => new Column(
				name: 'file',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'class' => new Column(
				name: 'class',
				type: 'varchar',
				size: 255,
				not_null: false,
			),
			'is_object' => new Column(
				name: 'is_object',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_package' => new Column(
				name: 'id_package',
				type: 'int',
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
						'name' => 'id_hook',
					],
				],
			),
			'idx_hook_name' => new DbIndex(
				name: 'idx_hook_name',
				columns: [
					[
						'name' => 'hook_name',
					],
					[
						'name' => 'is_enabled',
					],
				],
			),
		];

		parent::__construct();
	}
}
