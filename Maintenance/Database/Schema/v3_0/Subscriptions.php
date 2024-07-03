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
			new Column(
				name: 'id_subscribe',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'name',
				type: 'varchar',
				size: 60,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'description',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'cost',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'length',
				type: 'varchar',
				size: 6,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_group',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'add_groups',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'active',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'repeatable',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'allow_partial',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'reminder',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'email_complete',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_subscribe',
				],
			),
			new DbIndex(
				name: 'idx_active',
				columns: [
					'active',
				],
			),
		];
	}
}

?>