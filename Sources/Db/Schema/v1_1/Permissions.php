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

namespace SMF\Db\Schema\v1_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Permissions extends Table
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
			'ID_GROUP' => -1,
			'permission' => 'search_posts',
		],
		[
			'ID_GROUP' => -1,
			'permission' => 'calendar_view',
		],
		[
			'ID_GROUP' => -1,
			'permission' => 'view_stats',
		],
		[
			'ID_GROUP' => -1,
			'permission' => 'profile_view_any',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'view_mlist',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'search_posts',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_view_own',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_view_any',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'pm_read',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'pm_send',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'calendar_view',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'view_stats',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'who_view',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_identity_own',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_extra_own',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_remove_own',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_server_avatar',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_upload_avatar',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'profile_remote_avatar',
		],
		[
			'ID_GROUP' => 0,
			'permission' => 'karma_edit',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'view_mlist',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'search_posts',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_view_own',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_view_any',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'pm_read',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'pm_send',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'calendar_view',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'view_stats',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'who_view',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_identity_own',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_extra_own',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_remove_own',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_server_avatar',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_upload_avatar',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_remote_avatar',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'profile_title_own',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'calendar_post',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'calendar_edit_any',
		],
		[
			'ID_GROUP' => 2,
			'permission' => 'karma_edit',
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
		$this->name = 'permissions';

		$this->columns = [
			'ID_GROUP' => new Column(
				name: 'ID_GROUP',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'permission' => new Column(
				name: 'permission',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'addDeny' => new Column(
				name: 'addDeny',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_GROUP',
					],
					[
						'name' => 'permission',
					],
				],
			),
		];

		parent::__construct();
	}
}
