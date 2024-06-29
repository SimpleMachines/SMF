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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class UserDrafts extends Table
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
		$this->name = 'user_drafts';

		$this->columns = [
			new Column(
				name: 'id_draft',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_reply',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'type',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'poster_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
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
				name: 'smileys_enabled',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'body',
				type: 'mediumtext',
				not_null: true,
			),
			new Column(
				name: 'icon',
				type: 'varchar',
				size: 16,
				not_null: true,
				default: 'xx',
			),
			new Column(
				name: 'locked',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'is_sticky',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'to_list',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_draft',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					'id_member',
					'id_draft',
					'type',
				],
			),
		];
	}
}

?>