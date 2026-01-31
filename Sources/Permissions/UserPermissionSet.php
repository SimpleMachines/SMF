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

use SMF\Board;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\User;

/**
 * Keeps track of which permissions are allowed for a particular user in a
 * particular permission profile.
 */
class UserPermissionSet
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var User
	 *
	 * Instance of SMF\User that this permission set is for.
	 */
	public User $user;

	/**
	 * @var PermissionProfile
	 *
	 * Instance of SMF\Permissions\PermissionProfile that this permission set
	 * is for.
	 */
	public PermissionProfile $profile;

	/**
	 * @var array
	 *
	 * List of permission names and whether they are allowed.
	 *
	 * Keys are permission names.
	 * Values can be 1 for allowed, null for not allowed, or 0 for denied.
	 */
	public array $permissions = [];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class, keyed by profile.
	 */
	private static array $loaded = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param User $user The user that this permission set is for.
	 * @param ?Board $board The board where this permission set applies.
	 */
	public function __construct(User $user, ?Board $board = null)
	{
		$this->user = $user;
		$this->profile = PermissionProfile::load($board instanceof Board ? $board->profile : PermissionProfile::DEFAULT);

		$groups = $this->user->groups;

		// If this user is detected as a robot and we have special restrictions
		// for robots, then add the special robots group to the list.
		if ($this->user->possibly_robot && !empty(Config::$modSettings['spider_group'])) {
			$groups[] = (int) Config::$modSettings['spider_group'];
		}

		// If this user is a moderator for this board, load the moderator permissions.
		if (
			$board instanceof Board
			&& (
				\in_array($this->user->id, $board->moderators)
				|| array_intersect($board->moderator_groups, $this->user->groups) !== []
			)
		) {
			$groups[] = 3;
		}

		// Get the general permissions for this user.
		foreach (GroupPermissionSet::load(PermissionProfile::DEFAULT, $groups) as $group_set) {
			foreach ($group_set->permissions as $permission => $value) {
				if (Permission::get($permission)->scope !== 'global') {
					continue;
				}

				if (!isset($this->permissions[$permission]) || $value === 0) {
					$this->permissions[$permission] = $value;
				}
			}
		}

		// Get the board permissions for this user on the specified board.
		foreach (GroupPermissionSet::load($this->profile->id, $groups) as $group_set) {
			foreach ($group_set->permissions as $permission => $value) {
				if (Permission::get($permission)->scope === 'global') {
					continue;
				}

				if (!isset($this->permissions[$permission]) || $value === 0) {
					$this->permissions[$permission] = $value;
				}
			}
		}

		// Add to the list.
		self::$loaded[$this->user->id][$this->profile->id] = $this;
	}

	/**
	 * Checks whether the user has been granted the specified permissions.
	 *
	 * @param string|array $permission_names The names of the permissions to check.
	 * @param bool $any If true, will return true if the user has any of the
	 *    specified permissions. If false, will return true only if the user
	 *    has all of the specified permissions. Default: false.
	 * @return bool Whether the user has been granted the permissions.
	 */
	public function allowedTo(string|array $permission_names, bool $any = false): bool
	{
		$permission_names = (array) $permission_names;

		$this->integrateAllowedToGeneral($permission_names);

		if ($any) {
			$allowed = false;

			foreach ($permission_names as $permission_name) {
				if (!empty($this->permissions[$permission_name])) {
					$allowed = true;
					break;
				}
			}
		} else {
			$allowed = true;

			foreach ($permission_names as $permission_name) {
				if (empty($this->permissions[$permission_name])) {
					$allowed = false;
				}
			}
		}

		$allowed = $this->integrateAllowedToBoard($allowed, $permission_names, $any);

		return $allowed;
	}

	/**
	 * Temporarily grants one or more permissions to this user.
	 *
	 * These changes do not survive beyond the current script execution run.
	 *
	 * @param string|array $permission_names The name of the permission to grant.
	 */
	public function grant(string|array $permission_names): void
	{
		foreach ((array) $permission_names as $permission_name) {
			if (
				\is_string($permission_name)
				&& Permission::exists($permission_name)
			) {
				$this->permissions[$permission_name] = 1;
			}
		}
	}

	/**
	 * Temporarily removes one or more permissions from this user.
	 *
	 * These changes do not survive beyond the current script execution run.
	 *
	 * @param string $permission_names The name of the permission to deny.
	 */
	public function deny(string|array $permission_names): void
	{
		foreach ((array) $permission_names as $permission_name) {
			if (!empty($this->permissions[$permission_name])) {
				$this->permissions[$permission_name] = null;
			}
		}
	}

	/**
	 * Adjusts permissions according to ban and warning status.
	 */
	public function applyBansAndWarnings(): void
	{
		// This only applies to the current user.
		if ($this->user !== User::$me) {
			return;
		}

		// Somehow they got here, at least take away all permissions...
		if (isset($_SESSION['ban']['cannot_access'])) {
			foreach ($this->permissions as $permission => $value) {
				$this->deny($permission);
			}

			return;
		}

		// Okay, well, you can watch, but don't touch a thing.
		if (
			isset($_SESSION['ban']['cannot_post'])
			|| (
				!empty(Config::$modSettings['warning_mute'])
				&& Config::$modSettings['warning_mute'] <= $this->user->warning
			)
		) {
			$post_ban_permissions = [];

			foreach (Permission::getAll() as $permission) {
				if (!empty($permission->never_banned)) {
					$post_ban_permissions[] = $permission->name;
				}
			}

			IntegrationHook::call('integrate_post_ban_permissions', [&$post_ban_permissions]);

			foreach ($post_ban_permissions as $permission) {
				$this->deny($permission);
			}

			return;
		}

		// If user has a high warning level, some permissions should change...
		if (
			!empty(Config::$modSettings['warning_moderate'])
			&& Config::$modSettings['warning_moderate'] <= $this->user->warning
		) {
			foreach (Permission::getAll() as $permission) {
				if (isset($permission->when_warned)) {
					$warn_permissions[$permission->name] = $permission->when_warned;
				}
			}

			IntegrationHook::call('integrate_warn_permissions', [&$warn_permissions]);

			foreach ($warn_permissions as $old => $new) {
				if (!empty($this->permissions[$old])) {
					$this->grant($new);
				}

				$this->deny($old);
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads the permission sets for the given user on the given boards.
	 *
	 * @param User $user The user that this permission set is for.
	 * @param array $boards IDs of zero or more boards.
	 * @return array Instances of this class.
	 */
	public static function load(User $user, array $boards): array
	{
		$loaded = [];

		if (empty($boards) || \in_array(0, $boards)) {
			$loaded[PermissionProfile::DEFAULT] = self::$loaded[$user->id][PermissionProfile::DEFAULT] ?? new self($user);
		}

		if (!empty($boards)) {
			$boards = array_map('intval', $boards);

			// We need to temporarily load the boards, but we don't want to keep
			// any that weren't already loaded.
			$boards_to_unload = array_diff($boards, array_keys(Board::$loaded));

			foreach (Board::load($boards) as $board) {
				$loaded[$board->profile] = self::$loaded[$user->id][$board->profile] ?? new self($user, $board);
			}

			// Unload any boards that weren't already loaded.
			foreach ($boards_to_unload as $id) {
				Board::unload($id);
			}
		}

		return $loaded;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Calls the integrate_allowed_to_general hook so that mods can grant custom
	 * permissions.
	 *
	 * @param array $permission_names The names of the permissions to check.
	 */
	protected function integrateAllowedToGeneral(array $permission_names): void
	{
		$user_permissions = array_filter(
			array_keys(
				array_filter($this->permissions),
			),
			function (string $p) {
				try {
					return Permission::get($p)->scope === 'global';
				} catch (\ValueError $e) {
					return true;
				}
			},
		);

		IntegrationHook::call('integrate_allowed_to_general', [&$user_permissions, $permission_names]);

		foreach ($user_permissions as $permission_name) {
			$this->grant($permission_name);
		}
	}

	/**
	 * Calls the integrate_allowed_to_board hook so that mods can grant custom
	 * permissions.
	 *
	 * @param bool $allowed Whether the user is allowed based on checks so far.
	 * @param array $permission_names The names of the permissions to check.
	 * @param bool $any If true, should return true if the user has any of the
	 *    specified permissions. If false, should return true only if the user
	 *    has all of the specified permissions. Default: false.
	 */
	protected function integrateAllowedToBoard(bool $allowed, array $permission_names, bool $any): bool
	{
		if (empty($this->profile->boards())) {
			return $allowed;
		}

		$permission_names = array_filter($permission_names, fn($p) => Permission::get($p)->scope === 'board');

		IntegrationHook::call('integrate_allowed_to_board', [&$allowed, $permission_names, $this->profile->boards(), $any]);

		return $allowed;
	}
}
