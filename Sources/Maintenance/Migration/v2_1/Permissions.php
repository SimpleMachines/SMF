<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Permissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Updating profile permissions';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $removedPermissions = [
		'profile_view_own',
		'post_autosave_draft',
		'pm_autosave_draft'
	];

		/**
	 *
	 */
	protected array $removedBoardPermissions = [
		'mark_notify',
		'mark_any_notify',
		'send_topic',
		'post_autosave_draft'
	];

	/**
	 *
	 */
	protected array $renamedPermissions = [
		'profile_view_any' => 'profile_view'
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$this->query('', '
		DELETE FROM {$db_prefix}permissions
		WHERE permission IN ({array_string:removedPermissions})',
		[
			'removedPermissions' => $this->removedPermissions
		]);

		$this->handleTimeout(++$start);

		$this->query('', '
		DELETE FROM {$db_prefix}board_permissions
		WHERE permission IN ({array_string:removedBoardPermissions})',
		[
			'removedBoardPermissions' => $this->removedBoardPermissions
		]);

		$this->handleTimeout(++$start);

		foreach ($this->renamedPermissions as $old => $new) {
			$this->query('', '
			UPDATE {$db_prefix}permissions
			SET permission = {string:new}
			WHERE permission = {string:old}',
			[
				'new' => $new,
				'old' => $old
			]);	
		}

		$this->handleTimeout(++$start);

		$inserts = array();

		// Adding "profile_password_own"
		$request = $this->query('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {literal:profile_identity_own}');
		
		while ($row = Db::$db->fetch_assoc($request))
		{
			$inserts[] = [
				(int) $row['id_group'],
				'profile_password_own',
				(int) $row['add_deny']
			];
		}
	
		Db::$db->free_result($request);
	
		if (!empty($inserts))
		{
			Db::$db->insert('',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$inserts,
				array('id_group', 'permission')
			);
		}
		
		$this->handleTimeout(++$start);

		// Adding "view_warning_own" and "view_warning_any" permissions.
		if (isset(Config::$modSettings['warning_show']))
		{
			$can_view_warning_own = array();
			$can_view_warning_any = array();
		
			if (Config::$modSettings['warning_show'] >= 1)
			{
				$can_view_warning_own[] = 0;
		
				$request = $this->query('', '
					SELECT id_group
					FROM {db_prefix}membergroups
					WHERE min_posts = {int:not_post_based}',
					array(
						'not_post_based' => -1,
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					if (in_array($row['id_group'], array(1, 3)))
						continue;
		
					$can_view_warning_own[] = $row['id_group'];
				}
				Db::$db->free_result($request);
			}
		
			if (Config::$modSettings['warning_show'] > 1)
				$can_view_warning_any = $can_view_warning_own;
			else
			{
				$request = $this->query('', '
					SELECT id_group, add_deny
					FROM {db_prefix}permissions
					WHERE permission = {string:perm}',
					array(
						'perm' => 'issue_warning',
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					if (in_array($row['id_group'], array(-1, 1, 3)) || $row['add_deny'] != 1)
						continue;
		
					$can_view_warning_any[] = $row['id_group'];
				}
				Db::$db->free_result($request);
			}
		
			$inserts = array();
		
			foreach ($can_view_warning_own as $id_group)
				$inserts[] = array($id_group, 'view_warning_own', 1);
		
			foreach ($can_view_warning_any as $id_group)
				$inserts[] = array($id_group, 'view_warning_any', 1);
		
			if (!empty($inserts))
			{
				Db::$db->insert('ignore',
					'{db_prefix}permissions',
					array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'permission')
				);
			}
		
			Db::$db->query('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {string:warning_show}',
				array(
					'warning_show' => 'warning_show',
				)
			);
		}
		
		$this->handleTimeout(++$start);

		$inserts = array();

		$request = $this->query('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {literal:profile_extra_own}');
		
		while ($row = Db::$db->fetch_assoc($request))
		{
			$inserts[] = array($row['id_group'], 'profile_blurb_own', $row['add_deny']);
			$inserts[] = array($row['id_group'], 'profile_displayed_name_own', $row['add_deny']);
			$inserts[] = array($row['id_group'], 'profile_forum_own', $row['add_deny']);
			$inserts[] = array($row['id_group'], 'profile_website_own', $row['add_deny']);
			$inserts[] = array($row['id_group'], 'profile_signature_own', $row['add_deny']);
		}
	
		Db::$db->free_result($request);
	
		if (!empty($inserts))
		{
			Db::$db->insert('ignore',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$inserts,
				array('id_group', 'permission')
			);
		}

		$this->handleTimeout(++$start);

		return true;
	}
}

?>