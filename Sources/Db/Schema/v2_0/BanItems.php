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
class BanItems extends Table
{
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
			'id_ban' => new Column(
				name: 'id_ban',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_ban_group' => new Column(
				name: 'id_ban_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_low1' => new Column(
				name: 'ip_low1',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_high1' => new Column(
				name: 'ip_high1',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_low2' => new Column(
				name: 'ip_low2',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_high2' => new Column(
				name: 'ip_high2',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_low3' => new Column(
				name: 'ip_low3',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_high3' => new Column(
				name: 'ip_high3',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_low4' => new Column(
				name: 'ip_low4',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ip_high4' => new Column(
				name: 'ip_high4',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hostname' => new Column(
				name: 'hostname',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'email_address' => new Column(
				name: 'email_address',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hits' => new Column(
				name: 'hits',
				type: 'mediumint',
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
						'name' => 'id_ban',
					],
				],
			),
			'id_ban_group' => new DbIndex(
				name: 'id_ban_group',
				columns: [
					[
						'name' => 'id_ban_group',
					],
				],
			),
		];

		parent::__construct();
	}
}
