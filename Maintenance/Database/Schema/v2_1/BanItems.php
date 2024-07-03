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
class BanItems extends Table
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
		$this->name = 'ban_items';

		$this->columns = [
			new Column(
				name: 'id_ban',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_ban_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'ip_low',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'ip_high',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'hostname',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'email_address',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'hits',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_ban',
				],
			),
			new DbIndex(
				name: 'idx_id_ban_group',
				columns: [
					'id_ban_group',
				],
			),
			new DbIndex(
				name: 'idx_id_ban_ip',
				columns: [
					'ip_low',
					'ip_high',
				],
			),
		];
	}
}

?>