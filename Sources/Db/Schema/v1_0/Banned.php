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
class Banned extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'banned';

		$this->columns = [
			'ID_BAN' => new Column(
				name: 'ID_BAN',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'ban_type' => new Column(
				name: 'ban_type',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
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
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'email_address' => new Column(
				name: 'email_address',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
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
			'restriction_type' => new Column(
				name: 'restriction_type',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'reason' => new Column(
				name: 'reason',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'notes' => new Column(
				name: 'notes',
				type: 'text',
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_BAN',
					],
				],
			),
		];

		parent::__construct();
	}
}
