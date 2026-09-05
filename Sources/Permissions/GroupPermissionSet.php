<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Permissions;

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Group;

/**
 * Keeps track of which permissions are allowed for a particular membergroup
 * in a particular permission profile.
 */
class GroupPermissionSet
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID of the permission profile that this permission set is for.
	 */
	public int $profile;

	/**
	 * @var int
	 *
	 * ID of the group that this permission set is for.
	 */
	public int $group;

	/**
	 * @var array
	 *
	 * List of permission names and whether they are allowed.
	 *
	 * Keys are permission names.
	 * Values can be 1 for allowed, null for not allowed, or 0 for denied.
	 *
	 * To update permissions in the database, simply change their values here
	 * and then call the save() method.
	 */
	public array $permissions = [];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	private static array $loaded = [];

	/**
	 * @var bool
	 *
	 * Whether to load permission info from the database during construction.
	 *
	 * This is always true except during one phase of PermissionSet::load()
	 * and should be regarded as an implementation detail.
	 */
	private static bool $query_during_construction = true;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * Loads permission data for the given profile and group.
	 *
	 * @param int $profile ID of the permission profile that this is for.
	 * @param int $group ID of the group that this is for.
	 */
	public function __construct(int $profile, int $group)
	{
		$this->group = $group;
		$this->profile = $profile;

		self::$loaded[$profile][$group] = $this;

		if (self::$query_during_construction) {
			if ($profile === PermissionProfile::DEFAULT) {
				self::loadGlobalPermissionData([$group]);
			}

			self::loadBoardPermissionData([$profile], [$group]);
		}
	}

	/**
	 * Update the permissions in the database for this profile and group (as
	 * well as for any groups that inherit from this one).
	 *
	 * Performs safety checks before saving and silently skips any permissions
	 * that cannot or should not be saved.
	 */
	public function save(): void
	{
		// There is no need to save for admin.
		if ($this->group === Group::ADMIN) {
			return;
		}

		$group = current(Group::load($this->group));

		// Inherited permissions cannot be edited.
		if ($group->parent != Group::NONE) {
			return;
		}

		// Do any groups inherit permissions from this group?
		$group_ids = array_merge([$this->group], array_keys($group->getChildren()));

		// Save global permissions.
		if ($this->profile === PermissionProfile::DEFAULT) {
			$illegal = [];
			$inserts = [];

			foreach (Permission::getAll() as $permission) {
				if ($permission->scope !== 'global') {
					continue;
				}

				if (!$permission->canAssign() || !$permission->canBeGrantedTo($this->group)) {
					$illegal[] = $permission->name;
					continue;
				}

				if (!isset($this->permissions[$permission->name])) {
					continue;
				}

				foreach ($group_ids as $group_id) {
					// This shouldn't happen anyway, but just in case...
					if ($group_id === Group::MOD) {
						continue;
					}

					$inserts[] = [
						$group_id,
						$permission->name,
						$this->permissions[$permission->name],
					];
				}
			}

			// First, delete all the existing permissions for this group and its children.
			Db::$db->query(
				'DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:groups})
					AND permission NOT IN ({array_string:illegal})',
				[
					'groups' => $group_ids,
					'illegal' => empty($illegal) ? [''] : $illegal,
				],
			);

			// Now grant this group and its children whichever permissions they can have.
			Db::$db->insert(
				'replace',
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

		// Save board permissions.
		$illegal = [];
		$inserts = [];

		foreach (Permission::getAll() as $permission) {
			if ($permission->scope !== 'board') {
				continue;
			}

			if (!$permission->canAssign() || !$permission->canBeGrantedTo($this->group)) {
				$illegal[] = $permission->name;
				continue;
			}

			if (!isset($this->permissions[$permission->name])) {
				continue;
			}

			foreach ($group_ids as $group_id) {
				$inserts[] = [
					$group_id,
					$permission->name,
					$this->permissions[$permission->name],
					$this->profile,
				];
			}
		}

		// Again, we start by clearing all the permissions for this group and its children.
		Db::$db->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:groups})
				AND id_profile = {int:profile}
				AND permission NOT IN ({array_string:illegal})',
			[
				'groups' => $group_ids,
				'profile' => $this->profile,
				'illegal' => empty($illegal) ? [''] : $illegal,
			],
		);

		// Grant them whichever permissions they are now allowed to have.
		Db::$db->insert(
			'replace',
			'{db_prefix}board_permissions',
			[
				'id_group' => 'int',
				'permission' => 'string',
				'add_deny' => 'int',
				'id_profile' => 'int',
			],
			$inserts,
			['id_group', 'permission', 'id_profile'],
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads permission sets for one or more groups in one or more profiles.
	 *
	 * When dealing with multiple groups, this is more efficient than
	 * individually constructing a new instance for each group and profile.
	 *
	 * @param array|int $profiles IDs of one or more permission profiles.
	 * @param array|int $groups IDs of one or more groups.
	 * @param bool $refresh Set to true to force a reload from the database.
	 * @return array Instances of this class.
	 */
	public static function load(array|int $profiles, array|int $groups, bool $refresh = false): array
	{
		$loaded = [];

		// Ensure all profile and group IDs are integers.
		$profiles = array_map('intval', array_filter((array) $profiles, 'is_numeric'));
		$groups = array_map('intval', array_filter((array) $groups, 'is_numeric'));

		$query_groups = [];

		foreach ($profiles as $profile) {
			foreach ($groups as $group) {
				// Skip any that have already been loaded, unless told to refresh.
				if (isset(self::$loaded[$profile][$group]) && !$refresh) {
					$loaded[] = self::$loaded[$profile][$group];

					continue;
				}

				$query_groups[] = $group;

				// Don't run queries during object construction because we are going
				// to query the database separately below.
				self::$query_during_construction = false;

				// Initialize the objects. The constructor registers each one in
				// self::$loaded, which is where they are collected from below.
				new self($profile, $group);

				// Restore this to its normal value.
				self::$query_during_construction = true;
			}
		}

		if (!empty($query_groups)) {
			// Global permissions.
			if (\in_array(PermissionProfile::DEFAULT, $profiles)) {
				self::loadGlobalPermissionData($query_groups);
			}

			// Board permissions.
			self::loadBoardPermissionData($profiles, $query_groups);

			// A set that came from the cache takes the place of the instance the
			// constructor registered, so the ones to hand back are whichever
			// ended up in self::$loaded.
			foreach ($profiles as $profile) {
				foreach ($query_groups as $group) {
					if (isset(self::$loaded[$profile][$group])) {
						$loaded[] = self::$loaded[$profile][$group];
					}
				}
			}
		}

		return $loaded;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Loads global permission data.
	 *
	 * @param array $groups IDs of one or more groups.
	 */
	protected static function loadGlobalPermissionData(array $groups): void
	{
		$groups = array_intersect($groups, array_keys(self::$loaded[PermissionProfile::DEFAULT]));

		// By definition, the moderator group cannot have global permissions.
		$groups = array_diff($groups, [Group::MOD]);

		// Admins can do everything.
		if (\in_array(Group::ADMIN, $groups)) {
			foreach (Permission::getAll() as $permission) {
				if ($permission->scope === 'global') {
					self::$loaded[PermissionProfile::DEFAULT][Group::ADMIN]->permissions[$permission->name] = 1;
				}
			}

			$groups = array_diff($groups, [Group::ADMIN]);
		}

		// Can we get the permissions from the cache?
		foreach ($groups as $g => $group) {
			if (($set = CacheApi::get('permissions_global:' . $group, 240)) !== null) {
				self::$loaded[PermissionProfile::DEFAULT][$group] = $set;
				unset($groups[$g]);
			}
		}

		// If there's nothing left to look up, we're done.
		if (empty($groups)) {
			return;
		}

		// Look up the permissions for each group.
		$request = Db::$db->query(
			'SELECT id_group, permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:groups})',
			[
				'groups' => $groups,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!Permission::exists($row['permission'])) {
				continue;
			}

			self::$loaded[PermissionProfile::DEFAULT][(int) $row['id_group']]->permissions[$row['permission']] = (int) $row['add_deny'];
		}

		Db::$db->free_result($request);

		// If the option to deny permissions is disabled, turn any denied ones into disallowed ones.
		if (empty(Config::$modSettings['permission_enable_deny'])) {
			foreach ($groups as $group) {
				self::$loaded[PermissionProfile::DEFAULT][$group]->permissions = array_map(
					fn($value) => $value === 0 ? null : $value,
					self::$loaded[PermissionProfile::DEFAULT][$group]->permissions,
				);
			}
		}

		// Fill any unlisted permissions with an explicit null value.
		foreach (Permission::getAll() as $permission) {
			if ($permission->scope !== 'global') {
				continue;
			}

			foreach ($groups as $group) {
				self::$loaded[PermissionProfile::DEFAULT][$group]->permissions[$permission->name] = self::$loaded[PermissionProfile::DEFAULT][$group]->permissions[$permission->name] ?? null;
			}
		}

		// Cache each group's permissions.
		foreach (self::$loaded[PermissionProfile::DEFAULT] as $group => $set) {
			CacheApi::put('permissions_global:' . $group, $set, 240);
		}
	}

	/**
	 * Loads boad permission data.
	 *
	 * @param array $profiles IDs of one or more permission profiles.
	 * @param array $groups IDs of one or more groups.
	 */
	protected static function loadBoardPermissionData(array $profiles, array $groups): void
	{
		// Admins can do everything.
		if (\in_array(Group::ADMIN, $groups)) {
			foreach (Permission::getAll() as $permission) {
				if ($permission->scope === 'board') {
					foreach ($profiles as $profile) {
						self::$loaded[$profile][Group::ADMIN]->permissions[$permission->name] = 1;
					}
				}
			}

			$groups = array_diff($groups, [Group::ADMIN]);
		}

		// Can we get the permissions from the cache?
		if (CacheApi::$enable >= 2) {
			foreach ($groups as $g => $group) {
				$hits = 0;

				foreach ($profiles as $p => $profile) {
					if (($set = CacheApi::get('permissions_board:' . $profile . '-' . $group, 240)) !== null) {
						self::$loaded[$profile][$group] = $set;
						$hits++;
					}
				}

				if ($hits === \count($profiles)) {
					unset($groups[$g]);
				}
			}
		}

		// If there's nothing left to look up, we're done.
		if (empty($groups)) {
			return;
		}

		// Look up the permissions for each group.
		$request = Db::$db->query(
			'SELECT id_group, id_profile, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:groups})
				AND id_profile IN ({array_int:profiles})',
			[
				'groups' => $groups,
				'profiles' => $profiles,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (
				!isset(self::$loaded[(int) $row['id_profile']][(int) $row['id_group']])
				|| !Permission::exists($row['permission'])
			) {
				continue;
			}

			self::$loaded[(int) $row['id_profile']][(int) $row['id_group']]->permissions[$row['permission']] = (int) $row['add_deny'];
		}

		Db::$db->free_result($request);

		// If the option to deny permissions is disabled, turn any denied ones into disallowed ones.
		if (empty(Config::$modSettings['permission_enable_deny'])) {
			foreach ($profiles as $profile) {
				foreach ($groups as $group) {
					self::$loaded[$profile][$group]->permissions = array_map(
						fn($value) => $value === 0 ? null : $value,
						self::$loaded[$profile][$group]->permissions,
					);
				}
			}
		}

		// Fill any unlisted permissions with an explicit null value.
		foreach (Permission::getAll() as $permission) {
			if ($permission->scope !== 'board') {
				continue;
			}

			foreach ($profiles as $profile) {
				foreach ($groups as $group) {
					if (!isset(self::$loaded[$profile][$group])) {
						continue;
					}

					self::$loaded[$profile][$group]->permissions[$permission->name] = self::$loaded[$profile][$group]->permissions[$permission->name] ?? null;
				}
			}
		}

		// Cache each group's permissions for each profile.
		if (CacheApi::$enable >= 2) {
			foreach ($profiles as $profile) {
				foreach (self::$loaded[$profile] as $group => $set) {
					CacheApi::put('permissions_board:' . $profile . '-' . $group, $set, 240);
				}
			}
		}
	}
}
