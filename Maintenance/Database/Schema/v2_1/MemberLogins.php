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
class MemberLogins extends Table
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
			new DbIndex(
				type: 'primary',
				columns: [
					'id_login',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
			new DbIndex(
				name: 'idx_time',
				columns: [
					'time',
				],
			),
		];
	}
}

?>