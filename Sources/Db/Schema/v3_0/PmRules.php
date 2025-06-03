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
class PmRules extends Table
{
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
			'id_rule' => new Column(
				name: 'id_rule',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'rule_name' => new Column(
				name: 'rule_name',
				type: 'varchar',
				size: 60,
				not_null: true,
			),
			'criteria' => new Column(
				name: 'criteria',
				type: 'text',
				not_null: true,
			),
			'actions' => new Column(
				name: 'actions',
				type: 'text',
				not_null: true,
			),
			'delete_pm' => new Column(
				name: 'delete_pm',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_or' => new Column(
				name: 'is_or',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_rule',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			'idx_delete_pm' => new DbIndex(
				name: 'idx_delete_pm',
				columns: [
					'delete_pm',
				],
			),
		];

		parent::__construct();
	}
}
