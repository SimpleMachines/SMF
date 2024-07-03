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
class PermissionProfiles extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_profile' => 1,
			'profile_name' => 'default',
		],
		[
			'id_profile' => 2,
			'profile_name' => 'no_polls',
		],
		[
			'id_profile' => 3,
			'profile_name' => 'reply_only',
		],
		[
			'id_profile' => 4,
			'profile_name' => 'read_only',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'permission_profiles';

		$this->columns = [
			new Column(
				name: 'id_profile',
				type: 'smallint',
				auto: true,
			),
			new Column(
				name: 'profile_name',
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
					'id_profile',
				],
			),
		];
	}
}

?>