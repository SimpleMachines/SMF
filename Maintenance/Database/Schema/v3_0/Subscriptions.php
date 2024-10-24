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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Subscriptions extends Table
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
		$this->name = 'subscriptions';

		$this->columns = [
			'id_subscribe' => new Column(
				name: 'id_subscribe',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'name' => new Column(
				name: 'name',
				type: 'varchar',
				size: 60,
				not_null: true,
				default: '',
			),
			'description' => new Column(
				name: 'description',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'cost' => new Column(
				name: 'cost',
				type: 'text',
				not_null: true,
			),
			'length' => new Column(
				name: 'length',
				type: 'varchar',
				size: 6,
				not_null: true,
				default: '',
			),
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'add_groups' => new Column(
				name: 'add_groups',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			'active' => new Column(
				name: 'active',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'repeatable' => new Column(
				name: 'repeatable',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'allow_partial' => new Column(
				name: 'allow_partial',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'reminder' => new Column(
				name: 'reminder',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'email_complete' => new Column(
				name: 'email_complete',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_subscribe',
				],
			),
			'idx_active' => new DbIndex(
				name: 'idx_active',
				columns: [
					'active',
				],
			),
		];
	}
}

?>