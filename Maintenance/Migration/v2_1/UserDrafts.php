<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class UserDrafts extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for drafts';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$tables = Db::$db->list_tables();

		return !in_array(Config::$db_prefix . 'user_drafts', $tables) || Maintenance::getCurrentStart() > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$DraftsTable = new \SMF\Maintenance\Database\Schema\v2_1\UserDrafts();

		$tables = Db::$db->list_tables();

		// Creating draft table.
		if ($start <= 0 && !in_array(Config::$db_prefix . 'user_drafts', $tables)) {
			$DraftsTable->create();

			$this->handleTimeout(++$start);
		}

		// Adding draft permissions.
		if ($start <= 1 && version_compare(trim(strtolower(@Config::$modSettings['smfVersion'])), '2.1.foo', '<')) {
			// Anyone who can currently post unapproved topics we assume can create drafts as well ...
			$request = Db::$db->query(
				'',
				'SELECT id_group, id_board, add_deny, permission
				FROM {db_prefix}board_permissions
				WHERE permission = {literal:post_unapproved_topics}',
				[],
			);

			$inserts = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$inserts[] = [
					(int) $row['id_group'],
					(int) $row['id_board'],
					'post_draft',
					(int) $row['add_deny'],
				];
			}
			Db::$db->free_result($request);

			if (!empty($inserts)) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}board_permissions',
					[
						'id_group' => 'int',
						'id_board' => 'int',
						'permission' => 'string',
						'add_deny' => 'int',
					],
					$inserts,
					['id_member', 'alert_pref'],
				);
			}

			// Next we find people who can send PMs, and assume they can save pm_drafts as well
			$request = $this->query(
				'',
				'SELECT id_group, add_deny, permission
				FROM {db_prefix}permissions
				WHERE permission = {literal:pm_send}',
				[],
			);

			$inserts = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$inserts[] = [
					(int) $row['id_group'],
					'pm_draft',
					(int) $row['add_deny'],
				];
			}
			Db::$db->free_result($request);

			if (!empty($inserts)) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}permissions',
					[
						'id_group' => 'int',
						'permission' => 'string',
						'add_deny' => 'int',
					],
					$inserts,
					['id_group', 'permission'],
				);
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			Config::updateModSettings([
				'drafts_autosave_enabled' => Config::$modSettings['drafts_autosave_enabled'] ?? 1,
				'drafts_show_saved_enabled' => Config::$modSettings['drafts_show_saved_enabled'] ?? 1,
				'drafts_keep_days' => Config::$modSettings['drafts_keep_days'] ?? 7,
			]);

			Db::$db->insert(
				'ignore',
				'{db_prefix}themes',
				[
					'id_member' => 'int',
					'id_theme' => 'int',
					'variable' => 'string',
					'value' => 'string',
				],
				[
					[
						-1,
						1,
						'drafts_show_saved_enabled',
						'1',
					],
				],
				['id_member', 'id_theme', 'variable'],
			);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>