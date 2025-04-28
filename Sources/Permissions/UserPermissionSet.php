<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Permissions to deny to users who are banned from posting.
	 */
	public static array $post_ban_permissions = [
		'admin_forum',
		'calendar_edit_any',
		'calendar_edit_own',
		'calendar_post',
		'delete_any',
		'delete_own',
		'delete_replies',
		'edit_news',
		'lock_any',
		'lock_own',
		'make_sticky',
		'manage_attachments',
		'manage_bans',
		'manage_boards',
		'manage_membergroups',
		'manage_permissions',
		'manage_smileys',
		'merge_any',
		'moderate_forum',
		'modify_any',
		'modify_own',
		'modify_replies',
		'move_any',
		'pm_send',
		'poll_add_any',
		'poll_add_own',
		'poll_edit_any',
		'poll_edit_own',
		'poll_lock_any',
		'poll_lock_own',
		'poll_post',
		'poll_remove_any',
		'poll_remove_own',
		'post_new',
		'post_reply_any',
		'post_reply_own',
		'post_unapproved_replies_any',
		'post_unapproved_replies_own',
		'post_unapproved_topics',
		'profile_extra_any',
		'profile_forum_any',
		'profile_identity_any',
		'profile_other_any',
		'profile_signature_any',
		'profile_title_any',
		'remove_any',
		'remove_own',
		'send_mail',
		'split_any',
	];

	/**
	 * @var array
	 *
	 * Permissions to change for users with a high warning level.
	 */
	public static array $warn_permissions = [
		'post_new' => 'post_unapproved_topics',
		'post_reply_own' => 'post_unapproved_replies_own',
		'post_reply_any' => 'post_unapproved_replies_any',
		'post_attachment' => 'post_unapproved_attachments',
	];

	/**
	 * @var array
	 *
	 * Permissions that should only be given to highly trusted members.
	 */
	public static array $heavy_permissions = [
		'admin_forum',
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'edit_news',
		'moderate_forum',
		'manage_bans',
		'manage_membergroups',
		'manage_permissions',
	];

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
				in_array($this->user->id, $board->moderators)
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
	 * @param string $permission_names The names of the permissions to check.
	 * @param bool $any If true, will return true if the user has any of the
	 *    specified permissions. If false, will return true only if the user
	 *    has all of the specified permissions. Default: false.
	 * @return bool Whether the user has been granted the permissions.
	 */
	public function allowedTo(string|array $permission_names, bool $any = false): bool
	{
		IntegrationHook::call('integrate_heavy_permissions_session', [&self::$heavy_permissions]);

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
	 * @param string|array $permission_name The name of the permission to grant.
	 */
	public function grant(string|array $permission_names): void
	{
		foreach ((array) $permission_names as $permission_name) {
			if (
				is_string($permission_name)
				&& array_key_exists($permission_name, Permission::getAll())
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
	 * @param string $permission_name The name of the permission to deny.
	 */
	public function deny(string|array $permission_names): void
	{
		foreach ((array) $permission_names as $permission_name) {
			if (array_key_exists($permission_name, $this->permissions)) {
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
			IntegrationHook::call('integrate_post_ban_permissions', [&self::$post_ban_permissions]);

			foreach (self::$post_ban_permissions as $permission) {
				$this->deny($permission);
			}

			return;
		}

		// Are they absolutely under moderation?
		if (
			!empty(Config::$modSettings['warning_moderate'])
			&& Config::$modSettings['warning_moderate'] <= $this->user->warning
		) {
			// Work out what permissions should change...
			IntegrationHook::call('integrate_warn_permissions', [&self::$warn_permissions]);

			foreach (self::$warn_permissions as $old => $new) {
				$this->deny($old);
				$this->grant($new);
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

		if (empty($boards) || in_array(0, $boards)) {
			$loaded[PermissionProfile::DEFAULT] = self::$loaded[$user->id][PermissionProfile::DEFAULT] ?? new self($user);
		}

		if (!empty($boards)) {
			foreach (Board::load($boards) as $board) {
				$loaded[$board->profile] = self::$loaded[$user->id][$board->profile] ?? new self($user, $board);
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
	 * @param string $permission_names The names of the permissions to check.
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
