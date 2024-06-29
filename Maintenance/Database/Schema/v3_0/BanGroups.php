<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

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
			new Column(
				name: 'id_ban_group',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'name',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'ban_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'expire_time',
				type: 'int',
				unsigned: true,
			),
			new Column(
				name: 'cannot_access',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'cannot_register',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'cannot_post',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'cannot_login',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'reason',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'notes',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_ban_group',
				],
			),
		];
	}
}

?>