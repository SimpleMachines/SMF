<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Permissions;

use SMF\ArrayAccessHelper;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Parser;
use SMF\User;
use SMF\Utils;

/**
 * Represents a single permission
 */
class Permission implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Constants for group levels
	 */
	public const GROUP_LEVEL_RESTRICT = 0;
	public const GROUP_LEVEL_STANDARD = 1;
	public const GROUP_LEVEL_MODERATOR = 2;
	public const GROUP_LEVEL_MAINTENANCE = 3;

	/**
	 * Constants for board levels
	 */
	public const BOARD_LEVEL_STANDARD = 0;
	public const BOARD_LEVEL_LOCKED = 1;
	public const BOARD_LEVEL_PUBLISH = 2;
	public const BOARD_LEVEL_FREE = 3;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The name of this permission.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * Either 'board' for permissions that apply at the board level, or 'global'
	 * for permissions that apply everywhere.
	 */
	public string $scope;

	/**
	 * @var string
	 *
	 * This is used to group own/any variants together. For permissions that
	 * don't have own/any variants, this is the same as the permission name.
	 */
	public string $generic_name;

	/**
	 * @var ?string
	 *
	 * Indicates whether this is the "own" or the "any" variant of the generic
	 * permission. Not applicable for permissions that don't have own/any
	 * variants.
	 */
	public ?string $own_any = null;

	/**
	 * @var ?int
	 *
	 * Used by the predefined permission profiles to indicate the minimum group
	 * level that this permission should be granted at.
	 */
	public ?int $group_level = null;

	/**
	 * @var ?int
	 *
	 * Used by the predefined permission profiles to indicate the minimum board
	 * level that this permission should be granted at.
	 */
	public ?int $board_level = null;

	/**
	 * @var bool
	 *
	 * If true, this permission can never be granted to guests.
	 */
	public bool $never_guests = false;

	/**
	 * @var bool
	 *
	 * If true, this permission can never be granted to banned members.
	 */
	public bool $never_banned = false;

	/**
	 * @var array
	 *
	 * Permissions that someone must already have at least one of before they
	 * can be granted this permission.
	 *
	 * Only global permissions can be prerequisites.
	 */
	public array $assignee_prerequisites = [];

	/**
	 * @var array
	 *
	 * Permissions that someone must have all of before they can grant this
	 * permission to anyone.
	 *
	 * Only global permissions can be prerequisites.
	 *
	 * For obvious reasons, the 'manage_permissions' permission is always a
	 * prerequisite for assigning permissions, and therefore does not need to
	 * be listed in this property.
	 */
	public array $assigner_prerequisites = [];

	/**
	 * @var bool
	 *
	 * If true, permission should not be shown in the UI.
	 */
	public bool $hidden = false;

	/**
	 * @var bool
	 *
	 * If true, user's session should be validated before performing an action
	 * that requires this permission.
	 */
	public bool $heavy = false;

	/**
	 * @var ?string
	 *
	 * The name of an alternative permission to grant instead of this one for
	 * members with high warning levels.
	 */
	public ?string $when_warned = null;

	/**
	 * @var string
	 *
	 * Name of the group to show the permission within on the profile editing
	 * page.
	 */
	public string $view_group = '';

	/**
	 * @var string
	 *
	 * Indicates the Lang::$txt string to use as the generic label for this
	 * permission. Defaults to 'permissionname_' . generic_name.
	 */
	public string $label;

	/**
	 * @var array
	 *
	 * Arguments passed to Lang::formatText() at runtime to dynamically generate
	 * the finalized form of the label string.
	 *
	 * For example, the definition for the 'bbc_html' permission contains
	 * `'vsprintf' => ['permissionname_bbc', ['html']]`. This will cause the
	 * string Lang::$txt['permissionname_bbc_html'] to be created by inserting
	 * the string 'html' into Lang::$txt['permissionname_bbc'].
	 *
	 * FYI, this is called 'vsprintf' for historical reasons.
	 */
	public array $vsprintf = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Remembers query results from $this->canAssign().
	 */
	private bool $can_assign;

	/**
	 * @var array
	 *
	 * Remembers query results from $this->eligibleGroups().
	 */
	private array $eligibility;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Definitions of all known permissions and their properties.
	 *
	 * Protected to force access via Permission::get().
	 *
	 * Mods can add to this list using the integrate_permission_list hook.
	 */
	protected static array $permissions = [
		'access_mod_center' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_guests' => true,
		],
		'admin_forum' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'assigner_prerequisites' => ['admin_forum'],
			'heavy' => true,
		],
		'announce_topic' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'never_guests' => true,
		],
		'approve_group_requests' => [
			'scope' => 'global',
			'hidden' => true,
			'never_guests' => true,
			// This permission isn't permanently assignable.
			// It is granted to group moderators only while they are logged in.
			'can_assign' => false,
		],
		'approve_posts' => [
			'view_group' => 'general_board',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'bbc_cowsay' => [
			'view_group' => 'bbc',
			'scope' => 'global',
			'hidden' => true,
			'vsprintf' => ['permissionname_bbc', ['cowsay']],
		],
		'bbc_html' => [
			'view_group' => 'bbc',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'vsprintf' => ['permissionname_bbc', ['html']],
			'never_guests' => true,
			'assignee_prerequisites' => [
				'admin_forum',
				'manage_membergroups',
				'manage_permissions',
			],
			'assigner_prerequisites' => ['admin_forum'],
		],
		'calendar_edit_own' => [
			'generic_name' => 'calendar_edit',
			'own_any' => 'own',
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_guests' => true,
			'never_banned' => true,
		],
		'calendar_edit_any' => [
			'generic_name' => 'calendar_edit',
			'own_any' => 'any',
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'calendar_post' => [
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_banned' => true,
		],
		'calendar_view' => [
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
		],
		'delete_own' => [
			'generic_name' => 'delete',
			'own_any' => 'own',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'delete_any' => [
			'generic_name' => 'delete',
			'own_any' => 'any',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'delete_replies' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'edit_news' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'is_not_guest' => [
			'scope' => 'global',
			'hidden' => true,
			'never_guests' => true,
		],
		'issue_warning' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_guests' => true,
		],
		'likes_like' => [
			'view_group' => 'likes',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'lock_own' => [
			'generic_name' => 'lock',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'lock_any' => [
			'generic_name' => 'lock',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'make_sticky' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'manage_attachments' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'manage_bans' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'manage_boards' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'manage_membergroups' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'assigner_prerequisites' => ['manage_membergroups'],
			'heavy' => true,
		],
		'manage_permissions' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'manage_smileys' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'mention' => [
			'view_group' => 'mentions',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
		],
		'merge_any' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'moderate_board' => [
			'view_group' => 'general_board',
			'scope' => 'board',
			'never_guests' => true,
		],
		'moderate_forum' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
			'heavy' => true,
		],
		'modify_own' => [
			'generic_name' => 'modify',
			'own_any' => 'own',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'modify_any' => [
			'generic_name' => 'modify',
			'own_any' => 'any',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'modify_replies' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'move_own' => [
			'generic_name' => 'move',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'never_guests' => true,
			'never_banned' => true,
		],
		'move_any' => [
			'generic_name' => 'move',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_guests' => true,
			'never_banned' => true,
		],
		'pm_draft' => [
			'view_group' => 'pm',
			'scope' => 'global',
			'never_guests' => true,
			'never_banned' => true,
		],
		'pm_read' => [
			'view_group' => 'pm',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'pm_send' => [
			'view_group' => 'pm',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_add_own' => [
			'generic_name' => 'poll_add',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_add_any' => [
			'generic_name' => 'poll_add',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_edit_own' => [
			'generic_name' => 'poll_edit',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_edit_any' => [
			'generic_name' => 'poll_edit',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_lock_own' => [
			'generic_name' => 'poll_lock',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_lock_any' => [
			'generic_name' => 'poll_lock',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_post' => [
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_banned' => true,
		],
		'poll_remove_own' => [
			'generic_name' => 'poll_remove',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_remove_any' => [
			'generic_name' => 'poll_remove',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'poll_view' => [
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_LOCKED,
		],
		'poll_vote' => [
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
		],
		'post_attachment' => [
			'view_group' => 'attachment',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'when_warned' => 'post_unapproved_attachments',
		],
		'post_draft' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'never_guests' => true,
		],
		'post_new' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_banned' => true,
			'when_warned' => 'post_unapproved_topics',
		],
		'post_reply_own' => [
			'generic_name' => 'post_reply',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_banned' => true,
			'when_warned' => 'post_unapproved_replies_own',
		],
		'post_reply_any' => [
			'generic_name' => 'post_reply',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_banned' => true,
			'when_warned' => 'post_unapproved_replies_any',
		],
		'post_unapproved_attachments' => [
			'view_group' => 'attachment',
			'scope' => 'board',
		],
		'post_unapproved_replies_own' => [
			'generic_name' => 'post_unapproved_replies',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'never_banned' => true,
		],
		'post_unapproved_replies_any' => [
			'generic_name' => 'post_unapproved_replies',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'never_banned' => true,
		],
		'post_unapproved_topics' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'never_banned' => true,
		],
		'profile_blurb_own' => [
			'generic_name' => 'profile_blurb',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'never_guests' => true,
		],
		'profile_blurb_any' => [
			'generic_name' => 'profile_blurb',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'never_guests' => true,
		],
		'profile_displayed_name_own' => [
			'generic_name' => 'profile_displayed_name',
			'own_any' => 'own',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'never_guests' => true,
		],
		'profile_displayed_name_any' => [
			'generic_name' => 'profile_displayed_name',
			'own_any' => 'any',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'profile_extra_own' => [
			'generic_name' => 'profile_extra',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_extra_any' => [
			'generic_name' => 'profile_extra',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'profile_forum_own' => [
			'generic_name' => 'profile_forum',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_forum_any' => [
			'generic_name' => 'profile_forum',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'never_guests' => true,
			'never_banned' => true,
		],
		'profile_gravatar' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_identity_own' => [
			'generic_name' => 'profile_identity',
			'own_any' => 'own',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'never_guests' => true,
		],
		'profile_identity_any' => [
			'generic_name' => 'profile_identity',
			'own_any' => 'any',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'profile_password_own' => [
			'generic_name' => 'profile_password',
			'own_any' => 'own',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_password_any' => [
			'generic_name' => 'profile_password',
			'own_any' => 'any',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'profile_remote_avatar' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_remove_own' => [
			'generic_name' => 'profile_remove',
			'own_any' => 'own',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_remove_any' => [
			'generic_name' => 'profile_remove',
			'own_any' => 'any',
			'view_group' => 'profile_account',
			'scope' => 'global',
			'never_guests' => true,
		],
		'profile_server_avatar' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_signature_own' => [
			'generic_name' => 'profile_signature',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_signature_any' => [
			'generic_name' => 'profile_signature',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'profile_title_own' => [
			'generic_name' => 'profile_title',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'never_guests' => true,
		],
		'profile_title_any' => [
			'generic_name' => 'profile_title',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'profile_upload_avatar' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_view' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
		],
		'profile_website_own' => [
			'generic_name' => 'profile_website',
			'own_any' => 'own',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'profile_website_any' => [
			'generic_name' => 'profile_website',
			'own_any' => 'any',
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'remove_own' => [
			'generic_name' => 'remove',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
			'never_banned' => true,
		],
		'remove_any' => [
			'generic_name' => 'remove',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'report_any' => [
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_LOCKED,
			'never_guests' => true,
		],
		'report_user' => [
			'view_group' => 'profile',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'never_guests' => true,
		],
		'search_posts' => [
			'view_group' => 'general',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
		],
		'send_mail' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'never_guests' => true,
			'never_banned' => true,
		],
		'split_any' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
			'never_banned' => true,
		],
		'view_attachments' => [
			'view_group' => 'attachment',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_LOCKED,
		],
		'view_mlist' => [
			'view_group' => 'general',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_STANDARD,
		],
		'view_stats' => [
			'view_group' => 'general',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
		],
		'view_warning_own' => [
			'generic_name' => 'view_warning',
			'own_any' => 'own',
			'view_group' => 'profile_account',
			'scope' => 'global',
		],
		'view_warning_any' => [
			'generic_name' => 'view_warning',
			'own_any' => 'any',
			'view_group' => 'profile_account',
			'scope' => 'global',
		],
		'who_view' => [
			'view_group' => 'general',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
		],
	];

	/**
	 * @var array
	 *
	 * Aliases for a few permissions.
	 */
	protected static array $aliases = [
		'profile_view_own' => 'is_not_guest',
		'profile_view_any' => 'profile_view',
	];

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that guests may never have.
	 */
	protected static array $non_guest_permissions = [];

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that the current user can't assign.
	 */
	protected static array $unassignable = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name The name of the permission.
	 * @param array $props Other properties of the permission.
	 */
	public function __construct(string $name, array $props)
	{
		$this->name = $name;
		$this->set($props);

		// Finalize various values.
		$this->generic_name = $this->generic_name ?? $this->name;
		$this->scope = $this->scope ?? 'global';
		$this->hidden = !empty($this->hidden);
		$this->never_guests = !empty($this->never_guests);
		$this->label = $this->label ?? 'permissionname_' . $this->generic_name;

		// Do we need to dynamically generate the label string?
		if (!empty($this->vsprintf)) {
			if (Lang::txtExists($this->vsprintf[0], file: 'ManagePermissions')) {
				Lang::setTxt(
					$this->label,
					Lang::getTxt(
						$this->vsprintf[0],
						$this->vsprintf[1],
						file: 'ManagePermissions',
					),
				);
			} else {
				Lang::setTxt(
					$this->label,
					Lang::formatText(
						$this->vsprintf[0],
						$this->vsprintf[1],
					),
				);
			}
		}
	}

	/**
	 * Tells this instance to add itself to the list of known permissions.
	 *
	 * @return self Returns this instance for method chaining.
	 */
	public function addToKnownPermissions(): self
	{
		self::$permissions[$this->name] = $this;

		// Only global permissions can be prerequisites.
		if (!empty($this->assigner_prerequisites)) {
			$this->assigner_prerequisites = array_filter(
				$this->assigner_prerequisites,
				fn($prereq) => self::$permissions[$prereq]['scope'] === 'global',
			);
		}

		if (!empty($this->assignee_prerequisites)) {
			$this->assignee_prerequisites = array_filter(
				$this->assignee_prerequisites,
				fn($prereq) => self::$permissions[$prereq]['scope'] === 'global',
			);
		}

		return $this;
	}

	/**
	 * Checks whether the current user can assign this permission.
	 *
	 * To be able to assign a permission, the current user must have the
	 * 'manage_permissions' permission as well as all other permissions listed
	 * in $this->assigner_prerequisites.
	 *
	 * @return bool Whether the current user can assign this permission.
	 */
	public function canAssign(): bool
	{
		if (!isset($this->can_assign)) {
			$this->can_assign = User::$me->allowedTo(array_merge(['manage_permissions'], $this->assigner_prerequisites));
		}

		return $this->can_assign;
	}

	/**
	 * Checks whether this permission can be granted to the specified group.
	 *
	 * @param int $group The ID of a membergroup.
	 * @param bool $refresh Reload data from the database. Default: false.
	 * @return bool Whether the group can be granted this permission.
	 */
	public function canBeGrantedTo(int $group, bool $refresh = false): bool
	{
		if ($group <= Group::GUEST && $this->never_guests) {
			return false;
		}

		$this->eligibleGroups($refresh);

		return !empty($this->eligibility[$group]);
	}

	/**
	 * Gets the IDs of all the groups that can have this permission.
	 *
	 * @param bool $refresh Reload data from the database. Default: false.
	 * @return array All the groups that can have this permission.
	 */
	public function eligibleGroups(bool $refresh = false): array
	{
		// No one is eligible for a non-existent permission.
		if (!isset(self::$permissions[$this->name])) {
			$this->eligibility = array_fill_keys(Group::getAll(), false);
			$refresh = false;
		}

		if (!isset($this->eligibility) || $refresh) {
			if (empty($this->assignee_prerequisites)) {
				foreach (Group::getAll() as $group) {
					$this->eligibility[$group] = true;
				}
			} else {
				foreach (GroupPermissionSet::load(PermissionProfile::DEFAULT, Group::getAll()) as $set) {
					$this->eligibility[$set->group] = !empty(array_filter($this->assignee_prerequisites, fn($prereq) => !empty($set->permissions[$prereq])));
				}
			}

			// Obviously...
			$this->eligibility[Group::ADMIN] = true;

			if ($this->never_guests) {
				$this->eligibility[Group::GUEST] = false;
			}
		}

		return array_keys(array_filter($this->eligibility));
	}

	/**
	 * Gets the IDs of all the groups that cannot have this permission.
	 *
	 * @param bool $refresh Reload data from the database. Default: false.
	 * @return array All the groups that cannot have this permission.
	 */
	public function ineligibleGroups(bool $refresh = false): array
	{
		$this->eligibleGroups($refresh);

		return array_keys(array_filter($this->eligibility, fn($eligible) => !$eligible));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks whether the specified permission exists.
	 *
	 * @param string $name The name of the permission.
	 * @return bool Whether that permission exists.
	 */
	public static function exists(string $name): bool
	{
		return \array_key_exists($name, self::getAll());
	}

	/**
	 * Gets an instance of this class for the specified permission.
	 *
	 * If the permission does not exist, logs an error and then returns an
	 * instance of this class for the non-existent permission with its
	 * eligibility set so that nobody can have it -- not even admins!
	 *
	 * @param string $name The name of the permission.
	 * @return self An instance of this class.
	 */
	public static function get(string $name): self
	{
		// If the permission exists, return it.
		if (self::exists($name)) {
			return self::$permissions[$name];
		}

		// Otherwise, log an error and return a phantom permission that nobody can have.
		ErrorHandler::log(Lang::getTxt('unknown_permission', [$name], file: 'Errors'), 'general', __FILE__, __LINE__);

		return new self($name, [
			'hidden' => true,
			'never_guests' => true,
			'can_assign' => false,
			'eligibility' => array_fill_keys(Group::getAll(), false),
		]);
	}

	/**
	 * Gets instances of this class that have the specified generic name.
	 *
	 * Typically, the returned array will contain one instance for permissions
	 * that do not have own/any variants, two instances for permissions that
	 * do have own/any variants, or zero instances for permissions that do not
	 * exist.
	 *
	 * @param string $generic_name The generic name of the permissions.
	 * @return array Zero or more instances of this class.
	 */
	public static function getByGenericName(string $generic_name): array
	{
		return array_filter(
			self::getAll(),
			fn($permission) => $permission->generic_name === $generic_name,
		);
	}

	/**
	 * Gets the list of all known permissions.
	 *
	 * This method contains the integrate_permissions_list hook, which is the
	 * recommended way to add new permissions to SMF.
	 *
	 * @return array Instances of this class for all known permissions.
	 */
	public static function getAll(): array
	{
		// Avoid unnecessary repetition.
		if (reset(self::$permissions) instanceof self) {
			return self::$permissions;
		}

		IntegrationHook::call('integrate_permissions_list', [&self::$permissions]);

		self::integrateHeavyPermissionsSession();
		self::integrateLoadPermissionLevels();

		// In case a mod screwed things up...
		if (!\in_array('html', Utils::$context['restricted_bbc'])) {
			Utils::$context['restricted_bbc'][] = 'html';
		}

		// Add the permissions for the BBCodes.
		foreach (Parser::getBBCodes() as $bbc) {
			if (isset(self::$permissions['bbc_' . $bbc->tag])) {
				continue;
			}

			self::$permissions['bbc_' . $bbc->tag] = [
				'own_any' => null,
				'view_group' => 'bbc',
				'scope' => 'global',
				'hidden' => !\in_array($bbc->tag, Utils::$context['restricted_bbc']),
				'vsprintf' => ['permissionname_bbc', [$bbc->tag]],
			];
		}

		// If the calendar is disabled, disable the related permissions.
		if (empty(Config::$modSettings['cal_enabled'])) {
			self::$permissions['calendar_view']['hidden'] = true;
			self::$permissions['calendar_post']['hidden'] = true;
			self::$permissions['calendar_edit_own']['hidden'] = true;
			self::$permissions['calendar_edit_any']['hidden'] = true;
		}

		// If warnings are disabled, disable the related permissions.
		if (Config::$modSettings['warning_settings'][0] == 0) {
			self::$permissions['issue_warning']['hidden'] = true;
			self::$permissions['view_warning_own']['hidden'] = true;
			self::$permissions['view_warning_any']['hidden'] = true;
		}

		// If post moderation is disabled, disable the related permissions.
		if (empty(Config::$modSettings['postmod_active'])) {
			self::$permissions['approve_posts']['hidden'] = true;
			self::$permissions['post_unapproved_topics']['hidden'] = true;
			self::$permissions['post_unapproved_replies_own']['hidden'] = true;
			self::$permissions['post_unapproved_replies_any']['hidden'] = true;
			self::$permissions['post_unapproved_attachments']['hidden'] = true;
		}
		// If post moderation is enabled, these are named differently...
		else {
			// Relabel the topics permissions
			self::$permissions['post_new']['label'] = 'auto_approve_topics';

			// Relabel the reply permissions
			self::$permissions['post_reply_own']['label'] = 'auto_approve_replies';
			self::$permissions['post_reply_any']['label'] = 'auto_approve_replies';

			// Relabel the attachment permissions
			self::$permissions['post_attachment']['label'] = 'auto_approve_attachments';
		}

		// If attachments are disabled, disable the related permissions.
		if (empty(Config::$modSettings['attachmentEnable'])) {
			self::$permissions['manage_attachments']['hidden'] = true;
			self::$permissions['view_attachments']['hidden'] = true;
			self::$permissions['post_unapproved_attachments']['hidden'] = true;
			self::$permissions['post_attachment']['hidden'] = true;
		}

		// If likes are disabled, disable the related permission.
		if (empty(Config::$modSettings['enable_likes'])) {
			self::$permissions['likes_like']['hidden'] = true;
		}

		// If mentions are disabled, disable the related permission.
		if (empty(Config::$modSettings['enable_mentions'])) {
			self::$permissions['mention']['hidden'] = true;
		}

		// If Gravatars are disabled, disable the related permission.
		if (empty(Config::$modSettings['gravatarEnabled'])) {
			self::$permissions['profile_gravatar']['hidden'] = true;
		}

		foreach (self::$permissions as $name => $props) {
			self::$permissions[$name]['name'] = $name;
		}

		// Important: do the ones with prerequisites last.
		uasort(
			self::$permissions,
			function ($a, $b) {
				$a_prereqs = array_merge($a['assigner_prerequisites'] ?? [], $a['assignee_prerequisites'] ?? []);
				$b_prereqs = array_merge($b['assigner_prerequisites'] ?? [], $b['assignee_prerequisites'] ?? []);

				return \in_array($b['name'], $a_prereqs) ? 1 : (\in_array($a['name'], $b_prereqs) ? -1 : empty($b_prereqs) <=> empty($a_prereqs));
			},
		);

		// Now convert everything into instances of this class.
		foreach (self::$permissions as $name => $props) {
			(new self($name, $props))->addToKnownPermissions();
		}

		foreach (self::$aliases as $alias => $name) {
			$perm = clone self::$permissions[$name];
			$perm->name = $alias;
			$perm->addToKnownPermissions();
		}

		return self::$permissions;
	}

	/**
	 * Returns the names of permissions that the current user cannot assign.
	 *
	 * @return array Permissions that the current user cannot assign.
	 */
	public static function getUnassignable(): array
	{
		if (empty(self::$unassignable)) {
			foreach (self::getAll() as $perm) {
				if (!$perm->canAssign()) {
					self::$unassignable[] = $perm->name;
					self::$unassignable[] = $perm->generic_name;
				}
			}

			self::$unassignable = array_unique(self::$unassignable);

			// Call the deprecated integrate_load_illegal_permissions hook.
			self::integrateLoadIllegalPermissions();
		}

		return self::$unassignable;
	}

	/**
	 * Returns the names of hidden permissions.
	 *
	 * @return array Hidden permissions.
	 */
	public static function getHidden(): array
	{
		return array_map(
			fn($perm) => $perm->name,
			array_filter(
				self::getAll(),
				fn($perm) => $perm->hidden,
			),
		);
	}

	/**
	 * Returns the names of permissions that guests cannot have.
	 *
	 * @return array Names of permissions that guests cannot have.
	 */
	public static function getNonGuestPermissions(): array
	{
		if (empty(self::$non_guest_permissions)) {
			self::getAll();

			self::$non_guest_permissions = [];

			foreach (self::$permissions as $perm) {
				if ($perm->never_guests) {
					self::$non_guest_permissions[] = $perm->name;
					self::$non_guest_permissions[] = $perm->generic_name;
				}
			}

			self::$non_guest_permissions = array_unique(self::$non_guest_permissions);

			// Call the deprecated integrate_load_illegal_guest_permissions hook.
			self::integrateLoadIllegalGuestPermissions();
		}

		return self::$non_guest_permissions;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Calls the deprecated integrate_heavy_permissions_session hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list
	 * to set any permission's 'heavy' parameter.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateHeavyPermissionsSession(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_heavy_permissions_session'])) {
			return;
		}

		$heavy_permissions = array_keys(
			array_filter(
				self::$permissions,
				fn($permission) => !empty($permission['heavy']),
			),
		);

		IntegrationHook::call('integrate_heavy_permissions_session', [&$heavy_permissions]);

		foreach ($heavy_permissions as $permission) {
			self::$permissions[$permission]['heavy'] = true;
		}
	}

	/**
	 * Calls the deprecated integrate_load_illegal_guest_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list
	 * to set any permission's 'never_guests' parameter.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadIllegalGuestPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_load_illegal_guest_permissions'])) {
			return;
		}

		// This context variable exists only for the sake of the hook.
		Utils::$context['non_guest_permissions'] = self::$non_guest_permissions;

		// Track whether the hook makes any changes.
		$temp = Utils::jsonEncode(Utils::$context['non_guest_permissions']);

		// Give mods access to this list.
		IntegrationHook::call('integrate_load_illegal_guest_permissions');

		// If the hook changed anything, sync that back to our master list.
		if ($temp != Utils::jsonEncode(Utils::$context['non_guest_permissions'])) {
			// Did the hook add a permission to Utils::$context['non_guest_permissions']?
			foreach (Utils::$context['non_guest_permissions'] as $permission) {
				foreach (['', '_own', '_any'] as $suffix) {
					if (isset(self::$permissions[$permission . $suffix])) {
						self::$permissions[$permission . $suffix]->never_guests = true;
					}
				}
			}

			// Did the hook remove a permission from Utils::$context['non_guest_permissions']?
			foreach (self::getAll() as $permission => $perm) {
				if (!\in_array($perm->generic_name, Utils::$context['non_guest_permissions'])) {
					self::$permissions[$permission]->never_guests = false;
				}
			}

			// Now rebuild the list.
			foreach (self::$permissions as $perm) {
				if ($perm->never_guests) {
					self::$non_guest_permissions[] = $perm->name;
					self::$non_guest_permissions[] = $perm->generic_name;
				}
			}

			self::$non_guest_permissions = array_unique(self::$non_guest_permissions);
		}

		// We don't need this anymore.
		unset(Utils::$context['non_guest_permissions']);
	}

	/**
	 * Calls the deprecated integrate_load_illegal_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list
	 * to set any permission's 'assigner_prerequisites' parameter.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadIllegalPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_load_illegal_permissions'])) {
			return;
		}

		// This context variable exists only for the sake of the hook.
		Utils::$context['illegal_permissions'] = self::$unassignable;

		// Track whether the hook makes any changes.
		$temp = Utils::jsonEncode(self::$unassignable);

		// Give mods access to this list.
		IntegrationHook::call('integrate_load_illegal_permissions');

		// If the hook added anything, sync that back to our master list.
		// Because this hook can't tell us what the prerequisites are, we assume
		// that the permission can only be granted by admins.
		if ($temp != Utils::jsonEncode(self::$unassignable)) {
			foreach (Utils::$context['illegal_permissions'] as $permission) {
				foreach (['', '_own', '_any'] as $suffix) {
					if (isset(self::$permissions[$permission . $suffix])) {
						self::$permissions[$permission . $suffix]->assigner_prerequisites[] = 'admin_forum';
					}
				}
			}

			// Now rebuild the list.
			self::$unassignable = [];

			foreach (self::getAll() as $perm) {
				if (!empty($perm->assigner_prerequisites) && !User::$me->allowedTo($perm->assigner_prerequisites)) {
					self::$unassignable[] = $perm->name;
					self::$unassignable[] = $perm->generic_name;
				}
			}

			self::$unassignable = array_unique(self::$unassignable);
		}

		// We don't need this anymore.
		unset(Utils::$context['illegal_permissions']);
	}

	/**
	 * Calls the deprecated integrate_load_permission_levels hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list
	 * to set any permission's group_level or board_level parameter.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadPermissionLevels(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_load_permission_levels'])) {
			return;
		}

		// Levels by group... restrict, standard, moderator, maintenance.
		$group_levels = [
			'board' => ['inherit' => []],
			'group' => ['inherit' => []],
		];
		// Levels by board... standard, publish, free.
		$board_levels = ['inherit' => []];

		foreach (self::$permissions as $perm) {
			if (isset($perm['group_level'])) {
				switch ($perm['group_level']) {
					case self::GROUP_LEVEL_RESTRICT:
						$group_levels[$perm['scope']]['restrict'][] = $perm['name'];
						// no break

					case self::GROUP_LEVEL_STANDARD:
						$group_levels[$perm['scope']]['standard'][] = $perm['name'];
						// no break

					case self::GROUP_LEVEL_MODERATOR:
						$group_levels[$perm['scope']]['moderator'][] = $perm['name'];
						// no break

					case self::GROUP_LEVEL_MAINTENANCE:
						$group_levels[$perm['scope']]['maintenance'][] = $perm['name'];
						break;
				}
			}

			if (isset($perm['board_level'])) {
				switch ($perm['board_level']) {
					case self::BOARD_LEVEL_STANDARD:
						$board_levels['standard'][] = $perm['name'];
						// no break

					case self::BOARD_LEVEL_LOCKED:
						$board_levels['locked'][] = $perm['name'];
						// no break

					case self::BOARD_LEVEL_PUBLISH:
						$board_levels['publish'][] = $perm['name'];
						// no break

					case self::BOARD_LEVEL_FREE:
						$board_levels['free'][] = $perm['name'];
						break;
				}
			}
		}

		IntegrationHook::call('integrate_load_permission_levels', [&$group_levels, &$board_levels]);

		foreach ($group_levels as $scope => $levels) {
			foreach ($levels as $level => $permissions) {
				foreach ($permissions as $permission) {
					switch ($level) {
						case 'restrict':
							self::$permissions[$permission]['group_level'] = self::GROUP_LEVEL_RESTRICT;
							break;

						case 'standard':
							self::$permissions[$permission]['group_level'] = self::GROUP_LEVEL_STANDARD;
							break;

						case 'moderator':
							self::$permissions[$permission]['group_level'] = self::GROUP_LEVEL_MODERATOR;
							break;

						case 'maintenance':
							self::$permissions[$permission]['group_level'] = self::GROUP_LEVEL_MAINTENANCE;
							break;
					}
				}
			}
		}

		foreach ($board_levels as $level => $permissions) {
			foreach ($permissions as $permission) {
				switch ($level) {
					case 'standard':
						self::$permissions[$permission]['board_level'] = self::BOARD_LEVEL_STANDARD;
						break;

					case 'locked':
						self::$permissions[$permission]['board_level'] = self::BOARD_LEVEL_LOCKED;
						break;

					case 'publish':
						self::$permissions[$permission]['board_level'] = self::BOARD_LEVEL_PUBLISH;
						break;

					case 'free':
						self::$permissions[$permission]['board_level'] = self::BOARD_LEVEL_FREE;
						break;
				}
			}
		}
	}
}
