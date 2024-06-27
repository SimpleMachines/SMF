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
			'id_group' => -1,
			'permission' => 'search_posts',
		],
		[
			'id_group' => -1,
			'permission' => 'calendar_view',
		],
		[
			'id_group' => -1,
			'permission' => 'view_stats',
		],
		[
			'id_group' => 0,
			'permission' => 'view_mlist',
		],
		[
			'id_group' => 0,
			'permission' => 'search_posts',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_view',
		],
		[
			'id_group' => 0,
			'permission' => 'pm_read',
		],
		[
			'id_group' => 0,
			'permission' => 'pm_send',
		],
		[
			'id_group' => 0,
			'permission' => 'pm_draft',
		],
		[
			'id_group' => 0,
			'permission' => 'calendar_view',
		],
		[
			'id_group' => 0,
			'permission' => 'view_stats',
		],
		[
			'id_group' => 0,
			'permission' => 'who_view',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_identity_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_password_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_blurb_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_displayed_name_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_signature_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_website_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_forum_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_extra_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_remove_own',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_server_avatar',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_upload_avatar',
		],
		[
			'id_group' => 0,
			'permission' => 'profile_remote_avatar',
		],
		[
			'id_group' => 0,
			'permission' => 'send_email_to_members',
		],
		[
			'id_group' => 2,
			'permission' => 'view_mlist',
		],
		[
			'id_group' => 2,
			'permission' => 'search_posts',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_view',
		],
		[
			'id_group' => 2,
			'permission' => 'pm_read',
		],
		[
			'id_group' => 2,
			'permission' => 'pm_send',
		],
		[
			'id_group' => 2,
			'permission' => 'pm_draft',
		],
		[
			'id_group' => 2,
			'permission' => 'calendar_view',
		],
		[
			'id_group' => 2,
			'permission' => 'view_stats',
		],
		[
			'id_group' => 2,
			'permission' => 'who_view',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_identity_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_password_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_blurb_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_displayed_name_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_signature_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_website_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_forum_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_extra_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_remove_own',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_server_avatar',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_upload_avatar',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_remote_avatar',
		],
		[
			'id_group' => 2,
			'permission' => 'send_email_to_members',
		],
		[
			'id_group' => 2,
			'permission' => 'profile_title_own',
		],
		[
			'id_group' => 2,
			'permission' => 'calendar_post',
		],
		[
			'id_group' => 2,
			'permission' => 'calendar_edit_any',
		],
		[
			'id_group' => 2,
			'permission' => 'access_mod_center',
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
			new Column(
				name: 'id_group',
				type: 'smallint',
				default: 0,
			),
			new Column(
				name: 'permission',
				type: 'varchar',
				size: 30,
				default: '',
			),
			new Column(
				name: 'add_deny',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_group',
					'permission',
				],
			),
		];
	}
}

?>