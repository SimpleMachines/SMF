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

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Index;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class PmRules extends Table
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
		$this->name = 'pm_rules';

		$this->columns = [
			new Column(
				name: 'id_rule',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'rule_name',
				type: 'varchar',
				size: 60,
				not_null: true,
			),
			new Column(
				name: 'criteria',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'actions',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'delete_pm',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'is_or',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_rule',
				],
			),
			new Indices(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new Indices(
				name: 'idx_delete_pm',
				columns: [
					'delete_pm',
				],
			),
		];
	}
}

?>