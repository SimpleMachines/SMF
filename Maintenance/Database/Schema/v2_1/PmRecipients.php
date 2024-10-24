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
class PmRecipients extends Table
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
		$this->name = 'pm_recipients';

		$this->columns = [
			'id_pm' => new Column(
				name: 'id_pm',
				type: 'int',
				unsigned: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				default: 0,
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
			'in_inbox' => new Column(
				name: 'in_inbox',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_pm',
					'id_member',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					'id_member',
					'deleted',
					'id_pm',
				],
			),
		];
	}
}

?>