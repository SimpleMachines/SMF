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
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class MemberLogins extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [];

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
		$this->name = 'member_logins';

		$this->columns = [
			new Column(
				name: 'id_login',
				type: 'int',
				auto: true,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'time',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'ip2',
				type: 'inet',
				size: 16,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_login',
				],
			),
			new Indices(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new Indices(
				name: 'idx_time',
				columns: [
					'time',
				],
			),
		];
	}
}

?>