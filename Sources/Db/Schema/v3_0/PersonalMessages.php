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
			new Column(
				name: 'id_pm',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_pm_head',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member_from',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'deleted_by_sender',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'from_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'msgtime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'body',
				type: 'text',
				not_null: true,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_pm',
				],
			),
			new Index(
				name: 'idx_id_member',
				columns: [
					'id_member_from',
					'deleted_by_sender',
				],
			),
			new Index(
				name: 'idx_msgtime',
				columns: [
					'msgtime',
				],
			),
			new Index(
				name: 'idx_id_pm_head',
				columns: [
					'id_pm_head',
				],
			),
		];
	}
}

?>