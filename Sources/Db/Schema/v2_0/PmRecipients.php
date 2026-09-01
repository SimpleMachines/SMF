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
class PmRecipients extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'pm_recipients';

		$this->columns = [
			'id_pm' => new Column(
				name: 'id_pm',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'labels' => new Column(
				name: 'labels',
				type: 'varchar',
				size: 60,
				not_null: true,
				default: '-1',
			),
			'bcc' => new Column(
				name: 'bcc',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_read' => new Column(
				name: 'is_read',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_new' => new Column(
				name: 'is_new',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'deleted' => new Column(
				name: 'deleted',
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
					[
						'name' => 'id_pm',
					],
					[
						'name' => 'id_member',
					],
				],
			),
			'id_member' => new DbIndex(
				type: 'unique',
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'deleted',
					],
					[
						'name' => 'id_pm',
					],
				],
			),
		];

		parent::__construct();
	}
}
