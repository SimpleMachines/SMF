<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class BanGroups extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

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
			'id_ban_group' => new Column(
				name: 'id_ban_group',
				type: 'mediumint',
				unsigned: true,
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
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
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
					'id_ban_group',
				],
			),
		];
	}
}

?>