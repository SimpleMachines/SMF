<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class AvatarPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting avatar permissions and settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Converting avatar permissions.
		if (
			!empty(Config::$modSettings['avatar_allow_server_stored'])
			|| !empty(Config::$modSettings['avatar_allow_upload'])
		) {
			$request = $this->query(
				'SELECT IF(ID_GROUP = 1, 0, ID_GROUP)
				FROM {db_prefix}membergroups
				WHERE ID_GROUP != 3
					AND minPosts = -1',
			);

			while ($row = Db::$db->fetch_row($request)) {
				if (!empty(Config::$modSettings['avatar_allow_server_stored'])) {
					$data[] = [$row[0], 'profile_server_avatar'];
				}

				if (!empty(Config::$modSettings['avatar_allow_upload'])) {
					$data[] = [$row[0], 'profile_upload_avatar'];
				}
			}

			Db::$db->free_result($request);

			Db::$db->insert(
				method: '',
				table: '{db_prefix}permissions',
				columns: [
					'ID_GROUP' => 'int',
					'permission' => 'string-30',
				],
				data: $data,
				keys: [],
			);
		}

		// Removing obsolete avatar settings.
		Config::updateModSettings([
			'avatar_allow_external_url' => null,
			'avatar_check_size' => null,
			'avatar_allow_upload' => null,
			'avatar_allow_server_stored' => null,
		]);

		$this->handleTimeout();

		return true;
	}
}
