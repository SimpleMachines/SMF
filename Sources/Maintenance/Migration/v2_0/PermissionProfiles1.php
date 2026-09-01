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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PermissionProfiles1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding permission profiles for boards';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		// Don't need to clean up a non-existent table.
		if (empty(Db::$db->list_tables(false, Config::$db_prefix . 'permission_profiles'))) {
			return false;
		}

		$smfVersion = str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0'));

		return (
			version_compare($smfVersion, '2.0', '<')
			&& version_compare($smfVersion, '1.1.99', '>')
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table is structured correctly.
		$table = new Schema\v2_0\PermissionProfiles();
		$table->normalize();

		$request = $this->query(
			'SELECT id_profile
			FROM {db_prefix}permission_profiles
			WHERE profile_name = {string:empty}',
			[
				'empty' => '',
			],
		);

		$profiles = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$profiles[] = $row['id_profile'];
		}

		Db::$db->free_result($request);

		if (!empty($profiles)) {
			$request = $this->query(
				'SELECT id_profile, name
				FROM {db_prefix}boards
				WHERE id_profile IN ({array_int:profiles})',
				[
					'profiles' => $profiles,
				],
			);

			$done_ids = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				if (isset($done_ids[$row['id_profile']])) {
					continue;
				}

				$done_ids[$row['id_profile']] = true;

				$row['name'] = Db::$db->escape_string($row['name']);

				$this->query(
					'UPDATE {db_prefix}permission_profiles
					SET profile_name = {string:name}
					WHERE id_profile = {int:id_profile}',
					$row,
				);
			}

			Db::$db->free_result($request);
		}

		$this->handleTimeout();

		return true;
	}
}
