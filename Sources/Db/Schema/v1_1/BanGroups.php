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
class BanGroups extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'ban_groups';

		$this->columns = [
			'ID_BAN_GROUP' => new Column(
				name: 'ID_BAN_GROUP',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'name' => new Column(
				name: 'name',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'ban_time' => new Column(
				name: 'ban_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'expire_time' => new Column(
				name: 'expire_time',
				type: 'int',
				unsigned: true,
			),
			'cannot_access' => new Column(
				name: 'cannot_access',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'cannot_register' => new Column(
				name: 'cannot_register',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'cannot_post' => new Column(
				name: 'cannot_post',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'cannot_login' => new Column(
				name: 'cannot_login',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'reason' => new Column(
				name: 'reason',
				type: 'tinytext',
				not_null: true,
			),
			'notes' => new Column(
				name: 'notes',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_BAN_GROUP',
					],
				],
			),
		];

		parent::__construct();
	}
}
