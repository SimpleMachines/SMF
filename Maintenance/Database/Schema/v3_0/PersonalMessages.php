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
class PersonalMessages extends Table
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
		$this->name = 'personal_messages';

		$this->columns = [
			'id_pm' => new Column(
				name: 'id_pm',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			'id_pm_head' => new Column(
				name: 'id_pm_head',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member_from' => new Column(
				name: 'id_member_from',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'deleted_by_sender' => new Column(
				name: 'deleted_by_sender',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'from_name' => new Column(
				name: 'from_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'msgtime' => new Column(
				name: 'msgtime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'subject' => new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'body' => new Column(
				name: 'body',
				type: 'text',
				not_null: true,
			),
			'version' => new Column(
				name: 'version',
				type: 'varchar',
				size: 5,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_pm',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member_from',
					'deleted_by_sender',
				],
			),
			'idx_msgtime' => new DbIndex(
				name: 'idx_msgtime',
				columns: [
					'msgtime',
				],
			),
			'idx_id_pm_head' => new DbIndex(
				name: 'idx_id_pm_head',
				columns: [
					'id_pm_head',
				],
			),
		];
	}
}

?>