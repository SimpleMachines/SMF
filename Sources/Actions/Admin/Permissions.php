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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Moderation\Posts as PostMod;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Permissions handles all possible permission stuff.
 */
class Permissions implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ModifyPermissions',
			'getPermissions' => 'getPermissions',
			'setPermissionLevel' => 'setPermissionLevel',
			'init_inline_permissions' => 'init_inline_permissions',
			'theme_inline_permissions' => 'theme_inline_permissions',
			'save_inline_permissions' => 'save_inline_permissions',
			'loadPermissionProfiles' => 'loadPermissionProfiles',
			'updateChildPermissions' => 'updateChildPermissions',
			'loadIllegalPermissions' => 'loadIllegalPermissions',
			'buildHidden' => 'buildHidden',
			'permissionIndex' => 'PermissionIndex',
			'permissionByBoard' => 'PermissionByBoard',
			'modifyMembergroup' => 'ModifyMembergroup',
			'modifyMembergroup2' => 'ModifyMembergroup2',
			'setQuickGroups' => 'SetQuickGroups',
			'modifyPostModeration' => 'ModifyPostModeration',
			'editPermissionProfiles' => 'EditPermissionProfiles',
			'generalPermissionSettings' => 'GeneralPermissionSettings',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	public const GROUP_LEVEL_RESTRICT = 0;
	public const GROUP_LEVEL_STANDARD = 1;
	public const GROUP_LEVEL_MODERATOR = 2;
	public const GROUP_LEVEL_MAINTENANCE = 3;

	public const BOARD_LEVEL_STANDARD = 0;
	public const BOARD_LEVEL_LOCKED = 1;
	public const BOARD_LEVEL_PUBLISH = 2;
	public const BOARD_LEVEL_FREE = 3;

	public const PROFILE_DEFAULT = 1;
	public const PROFILE_NO_POLLS = 2;
	public const PROFILE_REPLY_ONLY = 3;
	public const PROFILE_READ_ONLY = 4;
	public const PROFILE_PREDEFINED = [1, 2, 3, 4];
	public const PROFILE_UNMODIFIABLE = [2, 3, 4];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'index';

	/**
	 * @var array
	 *
	 * Maps the permission groups used in the post moderation permissions UI
	 * to real permissions.
	 *
	 * Format: permission_group => array(can_do_moderated, can_do_all)
	 */
	public array $postmod_maps = [
		'new_topic' => ['post_new', 'post_unapproved_topics'],
		'replies_own' => ['post_reply_own', 'post_unapproved_replies_own'],
		'replies_any' => ['post_reply_any', 'post_unapproved_replies_any'],
		'attachment' => ['post_attachment', 'post_unapproved_attachments'],
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sub-action' => array('method_to_call', 'permission_needed')
	 */
	public static array $subactions = [
		'index' => ['index', 'manage_permissions'],
		'board' => ['board', 'manage_permissions'],
		'modify' => ['modify', 'manage_permissions'],
		'modify2' => ['modify2', 'manage_permissions'],
		'quick' => ['quick', 'manage_permissions'],
		'postmod' => ['postmod', 'manage_permissions'],
		'profiles' => ['profiles', 'manage_permissions'],
		'settings' => ['settings', 'admin_forum'],
	];

	/**
	 * @var array
	 *
	 * Organized list of permission view_groups.
	 * This ensures that permissions are presented in a stable order in the UI.
	 *
	 * Keys are permission scopes, values are lists of view_groups.
	 */
	public static array $permission_groups = [
		'global' => [
			'general',
			'pm',
			'calendar',
			'maintenance',
			'member_admin',
			'profile',
			'profile_account',
			'likes',
			'mentions',
			'bbc',
		],
		'board' => [
			'general_board',
			'topic',
			'post',
			'poll',
			'attachment',
		],
	];

	/**
	 * @var array
	 *
	 * Permission view_groups that should be shown in the left column of the UI.
	 */
	public static array $left_permission_groups = [
		'general',
		'calendar',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	];

	/**
	 * @var array
	 *
	 * Convenience array listing hidden permissions.
	 */
	public static array $hidden;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Permissions that are allowed or denied for the relevant membergroup.
	 * Used by the modify() method.
	 */
	protected array $allowed_denied = [
		'global' => [
			'allowed' => [],
			'denied' => [],
		],
		'board' => [
			'allowed' => [],
			'denied' => [],
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * List of all known permissions.
	 * Protected to force access via getPermissions().
	 *
	 * Mods can add to this list using the integrate_permission_list hook.
	 *
	 * For each permission, the available keys and their meaning are as follows:
	 *
	 *  - generic_name: This is used to group own/any variants together. For
	 *        permissions that don't have own/any variants, this is can be left
	 *        unset. The default is the same as the permission name.
	 *
	 *  - own_any: Indicates whether this is the "own" or the "any" variant of
	 *        the generic permission. Not applicable for permissions that don't
	 *        have own/any variants.
	 *
	 *  - view_group: Name of the group to show the permission within on the
	 *        profile profile editing page.
	 *
	 *  - scope: Either 'board' for permissions that apply at the board level,
	 *        or 'global' for permissions that apply everywhere.
	 *
	 *  - group_level: Used by the predefined permission profiles to indicate
	 *        the minimum group level that this permission should be granted at.
	 *
	 *  - board_level: Used by the predefined permission profiles to indicate
	 *        the minimum board level that this permission should be granted at.
	 *
	 *  - hidden: If true, permission should not be shown in the UI.
	 *
	 *  - label: Indicates the Lang::$txt string to use as the generic label for
	 *         this permission. Defaults to 'permissionname_' . generic_name.
	 *
	 *  - vsprintf: Arguments passed to vsprintf() at runtime to generate the
	 *        finalized form of the label string.
	 *
	 *  - never_guests: If true, this permission can never be granted to guests.
	 *
	 *  - assignee_prerequisites: Permissions that someone must already have at
	 *        least one of before they can be granted this permission.
	 *
	 *  - assigner_prerequisites: Permissions that someone must have at least
	 *        one of before they can grant this permission to anyone.
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
			'assigner_prerequisites' => ['admin_forum'],
		],
		'announce_topic' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'never_guests' => true,
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
		],
		'calendar_edit_any' => [
			'generic_name' => 'calendar_edit',
			'own_any' => 'any',
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'calendar_post' => [
			'view_group' => 'calendar',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
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
		],
		'delete_any' => [
			'generic_name' => 'delete',
			'own_any' => 'any',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'delete_replies' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'edit_news' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
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
		],
		'lock_any' => [
			'generic_name' => 'lock',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'make_sticky' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'manage_attachments' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'manage_bans' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'manage_boards' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
		],
		'manage_membergroups' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'assigner_prerequisites' => ['manage_membergroups'],
		],
		'manage_permissions' => [
			'view_group' => 'member_admin',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
			'assigner_prerequisites' => ['manage_permissions'],
		],
		'manage_smileys' => [
			'view_group' => 'maintenance',
			'scope' => 'global',
			'group_level' => self::GROUP_LEVEL_MAINTENANCE,
			'never_guests' => true,
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
		],
		'modify_own' => [
			'generic_name' => 'modify',
			'own_any' => 'own',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'modify_any' => [
			'generic_name' => 'modify',
			'own_any' => 'any',
			'view_group' => 'post',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'modify_replies' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'move_own' => [
			'generic_name' => 'move',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'never_guests' => true,
		],
		'move_any' => [
			'generic_name' => 'move',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'never_guests' => true,
		],
		'pm_draft' => [
			'view_group' => 'pm',
			'scope' => 'global',
			'never_guests' => true,
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
		],
		'poll_add_own' => [
			'generic_name' => 'poll_add',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'poll_add_any' => [
			'generic_name' => 'poll_add',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'poll_edit_own' => [
			'generic_name' => 'poll_edit',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'poll_edit_any' => [
			'generic_name' => 'poll_edit',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'poll_lock_own' => [
			'generic_name' => 'poll_lock',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'never_guests' => true,
		],
		'poll_lock_any' => [
			'generic_name' => 'poll_lock',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
		],
		'poll_post' => [
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_STANDARD,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
		],
		'poll_remove_own' => [
			'generic_name' => 'poll_remove',
			'own_any' => 'own',
			'view_group' => 'poll',
			'scope' => 'board',
			'board_level' => self::BOARD_LEVEL_PUBLISH,
			'never_guests' => true,
		],
		'poll_remove_any' => [
			'generic_name' => 'poll_remove',
			'own_any' => 'any',
			'view_group' => 'poll',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
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
		],
		'post_reply_own' => [
			'generic_name' => 'post_reply',
			'own_any' => 'own',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
		],
		'post_reply_any' => [
			'generic_name' => 'post_reply',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_RESTRICT,
			'board_level' => self::BOARD_LEVEL_PUBLISH,
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
		],
		'post_unapproved_replies_any' => [
			'generic_name' => 'post_unapproved_replies',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
		],
		'post_unapproved_topics' => [
			'view_group' => 'topic',
			'scope' => 'board',
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
		],
		'remove_any' => [
			'generic_name' => 'remove',
			'own_any' => 'any',
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
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
		],
		'split_any' => [
			'view_group' => 'topic',
			'scope' => 'board',
			'group_level' => self::GROUP_LEVEL_MODERATOR,
			'board_level' => self::BOARD_LEVEL_FREE,
			'never_guests' => true,
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
	 * @var bool
	 *
	 * Whether self::$permissions has already be processed by getPermissions().
	 */
	protected static bool $processed = false;

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that guests may never have.
	 */
	protected static array $never_guests = [];

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that certain groups may not have.
	 */
	protected static array $excluded = [];

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that the current user can't change.
	 */
	protected static array $illegal = [];

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatches to the right method based on the given sub-action.
	 *
	 * Checks the permissions, based on the sub-action.
	 * Called by ?action=admin;area=permissions.
	 *
	 * Uses ManagePermissions language file.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Sets up the permissions by membergroup index page.
	 *
	 * Called by ?action=admin;area=permissions;sa=index
	 * Creates an array of all the groups with the number of members and permissions.
	 *
	 * Uses ManagePermissions language file.
	 * Uses ManagePermissions template file.
	 * @uses template_permission_index()
	 */
	public function index(): void
	{
		Utils::$context['page_title'] = Lang::$txt['permissions_title'];

		// Load all the permissions. We'll need them for the advanced options.
		self::loadAllPermissions();

		// Also load profiles, we may want to reset.
		self::loadPermissionProfiles();

		// Expand or collapse the advanced options?
		Utils::$context['show_advanced_options'] = empty(Utils::$context['admin_preferences']['app']);

		$this->setGroupsContext();

		// We can modify any permission set, except for the ones we can't.
		Utils::$context['can_modify'] = empty($_REQUEST['pid']) || !in_array((int) $_REQUEST['pid'], self::PROFILE_UNMODIFIABLE);

		// Load the proper template.
		Utils::$context['sub_template'] = 'permission_index';
		SecurityToken::create('admin-mpq');
	}

	/**
	 * Handle permissions by board... more or less. :P
	 */
	public function board(): void
	{
		Utils::$context['page_title'] = Lang::$txt['permissions_boards'];
		Utils::$context['edit_all'] = isset($_GET['edit']);

		// Saving?
		if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile'])) {
			User::$me->checkSession('request');
			SecurityToken::validate('admin-mpb');

			$changes = [];

			foreach ($_POST['boardprofile'] as $p_board => $profile) {
				$changes[(int) $profile][] = (int) $p_board;
			}

			if (!empty($changes)) {
				foreach ($changes as $profile => $boards) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET id_profile = {int:current_profile}
						WHERE id_board IN ({array_int:board_list})',
						[
							'board_list' => $boards,
							'current_profile' => $profile,
						],
					);
				}
			}

			Utils::$context['edit_all'] = false;
		}

		// Load all permission profiles.
		self::loadPermissionProfiles();

		// Get the board tree.
		Category::getTree();

		// Build the list of the boards.
		Utils::$context['categories'] = [];

		foreach (Category::$loaded as $catid => $tree) {
			Utils::$context['categories'][$catid] = [
				'name' => &$tree->name,
				'id' => &$tree->id,
				'boards' => [],
			];

			foreach (Category::$boardList[$catid] as $boardid) {
				if (!isset(Utils::$context['profiles'][Board::$loaded[$boardid]->profile])) {
					Board::$loaded[$boardid]->profile = self::PROFILE_DEFAULT;
				}

				Utils::$context['categories'][$catid]['boards'][$boardid] = [
					'id' => &Board::$loaded[$boardid]->id,
					'name' => &Board::$loaded[$boardid]->name,
					'description' => &Board::$loaded[$boardid]->description,
					'child_level' => &Board::$loaded[$boardid]->child_level,
					'profile' => &Board::$loaded[$boardid]->profile,
					'profile_name' => Utils::$context['profiles'][Board::$loaded[$boardid]->profile]['name'],
				];
			}
		}

		Utils::$context['sub_template'] = 'by_board';
		SecurityToken::create('admin-mpb');
	}

	/**
	 * Handles permission modification actions from the upper part of the
	 * permission manager index.
	 */
	public function quick(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpq', 'quick');

		if ($_POST['copy_from'] === 'empty') {
			$_POST['copy_from'] = 0;
		}

		// Make sure only one of the quick options was selected.
		if (!empty($_POST['predefined']) + !empty($_POST['permissions']) + !empty($_POST['copy_from']) > 1) {
			ErrorHandler::fatalLang('permissions_only_one_option', false);
		}

		// Only accept numeric values for selected membergroups.
		$_POST['group'] = array_unique(array_map('intval', (array) ($_POST['group'] ?? [])));

		// No groups were selected.
		if (empty($_POST['group'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		// Profile ID must be an integer.
		$_REQUEST['pid'] = (int) ($_REQUEST['pid'] ?? 0);

		// Sorry, but that one can't be modified.
		if (in_array($_REQUEST['pid'], self::PROFILE_UNMODIFIABLE)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Clear out any cached authority.
		Config::updateModSettings(['settings_updated' => time()]);

		self::loadIllegalPermissions();
		self::loadIllegalGuestPermissions();
		self::loadIllegalBBCHtmlGroups();

		// Set a predefined permission profile.
		if (!empty($_POST['predefined'])) {
			$this->quickSetPredefined();
		}
		// Set a permission profile based on the permissions of a selected group.
		elseif (!empty($_POST['copy_from'])) {
			$this->quickCopyFrom();
		}
		// Set or unset a certain permission for the selected groups.
		elseif (!empty($_POST['permissions'])) {
			$this->quickSetPermission();
		}

		self::updateBoardManagers();

		Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
	}

	/**
	 * Initializes the necessary stuff to modify a membergroup's permissions.
	 */
	public function modify(): void
	{
		// First, which membergroup are we working on?
		$this->setGroupContext();

		// Next, which permission profile are we working with?
		$this->setProfileContext();

		$this->setAllowedDenied(Utils::$context['group']['id'], Utils::$context['permission_type'], Utils::$context['profile']['id']);

		$this->setOnOff();

		Utils::$context['sub_template'] = 'modify_group';
		Utils::$context['page_title'] = Lang::$txt['permissions_modify_group'];

		SecurityToken::create('admin-mp');
	}

	/**
	 * This method actually saves modifications to a membergroup's board permissions.
	 */
	public function modify2(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mp');

		// Can't do anything without these.
		if (!isset($_GET['group'], $_GET['pid'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$_GET['group'] = (int) $_GET['group'];
		$_GET['pid'] = (int) $_GET['pid'];

		// Group needs to be valid.
		if ($_GET['group'] < -1) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// No, you can't modify this permission profile.
		if (in_array($_GET['pid'], self::PROFILE_UNMODIFIABLE)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Verify this isn't inherited.
		if ($this->getParentGroup($_GET['group']) != Group::NONE) {
			ErrorHandler::fatalLang('cannot_edit_permissions_inherited');
		}

		$illegal_permissions = array_merge(
			self::loadIllegalPermissions(),
			$_GET['group'] == -1 ? self::loadIllegalGuestPermissions() : [],
		);

		$give_perms = [
			'global' => [],
			'board' => [],
		];

		// Prepare all permissions that were set or denied for addition to the DB.
		if (isset($_POST['perm']) && is_array($_POST['perm'])) {
			foreach ($_POST['perm'] as $scope => $perm_array) {
				if (!is_array($perm_array)) {
					continue;
				}

				foreach ($perm_array as $permission => $value) {
					if ($value != 'on' && $value != 'deny') {
						continue;
					}

					// Don't allow people to escalate themselves!
					if (in_array($permission, $illegal_permissions)) {
						continue;
					}

					$give_perms[$scope][] = [
						$_GET['group'],
						$permission,
						(int) ($value == 'on'),
					];
				}
			}
		}

		// Insert the general permissions.
		if ($_GET['group'] != 3 && empty($_GET['pid'])) {
			$this->updateGlobalPermissions($_GET['group'], $give_perms, $illegal_permissions);
		}

		// Insert the board permissions.
		$this->updateBoardPermissions($_GET['group'], $give_perms, $illegal_permissions, $_GET['pid']);

		// Update any inherited permissions as required.
		self::updateChildPermissions($_GET['group'], $_GET['pid']);

		// Ensure that no one has bbc_html permission who shouldn't.
		self::removeIllegalBBCHtmlPermission();

		// Ensure Config::$modSettings['board_manager_groups'] is up to date.
		if (!in_array('manage_boards', $illegal_permissions)) {
			self::updateBoardManagers();
		}

		// Clear cached permissions.
		Config::updateModSettings(['settings_updated' => time()]);

		Utils::redirectexit('action=admin;area=permissions;pid=' . $_GET['pid']);
	}

	/**
	 * A screen to set some general settings for permissions.
	 */
	public function settings(): void
	{
		// All the setting variables
		$config_vars = self::getConfigVars();

		Utils::$context['page_title'] = Lang::$txt['permission_settings_title'];
		Utils::$context['sub_template'] = 'show_settings';

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=permissions;save;sa=settings';

		// Saving the settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_permission_settings');

			ACP::saveDBSettings($config_vars);

			// Clear all deny permissions... if we want that.
			if (empty(Config::$modSettings['permission_enable_deny'])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE add_deny = {int:denied}',
					[
						'denied' => 0,
					],
				);
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE add_deny = {int:denied}',
					[
						'denied' => 0,
					],
				);
			}

			// Make sure there are no postgroup based permissions left.
			if (empty(Config::$modSettings['permission_enable_postgroups'])) {
				// Get a list of postgroups.
				$post_groups = [];

				$request = Db::$db->query(
					'',
					'SELECT id_group
					FROM {db_prefix}membergroups
					WHERE min_posts != {int:min_posts}',
					[
						'min_posts' => -1,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$post_groups[] = $row['id_group'];
				}
				Db::$db->free_result($request);

				// Remove'em.
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
					],
				);

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
					],
				);

				Db::$db->query(
					'',
					'UPDATE {db_prefix}membergroups
					SET id_parent = {int:not_inherited}
					WHERE id_parent IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
						'not_inherited' => -2,
					],
				);
			}

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=permissions;sa=settings');
		}

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Add/Edit/Delete profiles.
	 */
	public function profiles(): void
	{
		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['permissions_profile_edit'];
		Utils::$context['sub_template'] = 'edit_profiles';

		// If we're creating a new one do it first.
		if (isset($_POST['create']) && trim($_POST['profile_name']) != '') {
			$this->createProfile();
		}
		// Renaming?
		elseif (isset($_POST['rename'])) {
			$this->renameProfile();
		}
		// Deleting?
		elseif (isset($_POST['delete']) && !empty($_POST['delete_profile'])) {
			$this->deleteProfile();
		}

		// Clearly, we'll need this!
		self::loadPermissionProfiles();

		// Work out what ones are in use.
		$request = Db::$db->query(
			'',
			'SELECT id_profile, COUNT(*) AS board_count
			FROM {db_prefix}boards
			GROUP BY id_profile',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (isset(Utils::$context['profiles'][$row['id_profile']])) {
				Utils::$context['profiles'][$row['id_profile']]['in_use'] = true;
				Utils::$context['profiles'][$row['id_profile']]['boards'] = $row['board_count'];
				Utils::$context['profiles'][$row['id_profile']]['boards_text'] = $row['board_count'] > 1 ? sprintf(Lang::$txt['permissions_profile_used_by_many'], $row['board_count']) : Lang::$txt['permissions_profile_used_by_' . ($row['board_count'] ? 'one' : 'none')];
			}
		}
		Db::$db->free_result($request);

		// What can we do with these?
		Utils::$context['can_rename_something'] = false;

		foreach (Utils::$context['profiles'] as $id => $profile) {
			// Can't rename the special ones.
			Utils::$context['profiles'][$id]['can_rename'] = !in_array($id, self::PROFILE_PREDEFINED);

			if (Utils::$context['profiles'][$id]['can_rename']) {
				Utils::$context['can_rename_something'] = true;
			}

			// You can only delete it if you can rename it AND it's not in use.
			Utils::$context['profiles'][$id]['can_delete'] = !in_array($id, self::PROFILE_PREDEFINED) && empty($profile['in_use']);
		}

		SecurityToken::create('admin-mpp');
	}

	/**
	 * Present a nice way of applying post moderation.
	 */
	public function postmod(): void
	{
		// Just in case.
		User::$me->checkSession('get');

		Utils::$context['page_title'] = Lang::$txt['permissions_post_moderation'];
		Utils::$context['sub_template'] = 'postmod_permissions';
		Utils::$context['current_profile'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 1;

		// Load all the permission profiles.
		self::loadPermissionProfiles();

		IntegrationHook::call('integrate_post_moderation_mapping', [&$this->postmod_maps]);

		// Start this with the guests/members.
		Utils::$context['profile_groups'] = [
			Group::GUEST => new Group(Group::GUEST, [
				'name' => Lang::$txt['membergroups_guests'],
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
			]),
			Group::REGULAR => new Group(Group::REGULAR, [
				'name' => Lang::$txt['membergroups_members'],
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
			]),
		];

		// Load the groups.
		$query_customizations = [
			'where' => [
				'id_group != {int:admin_group}',
				'id_parent = {int:no_parent}',
			],
			'order' => ['id_parent ASC'],
			'params' => [
				'admin_group' => Group::ADMIN,
				'no_parent' => Group::NONE,
			],
		];

		if (empty(Config::$modSettings['permission_enable_postgroups'])) {
			$query_customizations['where'][] = 'min_posts = {int:min_posts}';
			$query_customizations['params']['min_posts'] = -1;
		}

		foreach (Group::load([], $query_customizations) as $group) {
			// Get a list of the child groups as well.
			$group->getChildren();

			// Add some custom properties.
			$group->new_topic = 'disallow';
			$group->replies_own = 'disallow';
			$group->replies_any = 'disallow';
			$group->attachment = 'disallow';

			Utils::$context['profile_groups'][$group->id] = $group;
		}

		// What are the permissions we are querying?
		$all_permissions = [];

		foreach ($this->postmod_maps as $perm_set) {
			$all_permissions = array_merge($all_permissions, $perm_set);
		}

		// If we're saving the changes then do just that - save them.
		if (!empty($_POST['save_changes']) && !in_array(Utils::$context['current_profile'], self::PROFILE_UNMODIFIABLE)) {
			SecurityToken::validate('admin-mppm');

			// First, are we saving a new value for enabled post moderation?
			$new_setting = !empty($_POST['postmod_active']);

			if ($new_setting != Config::$modSettings['postmod_active']) {
				if ($new_setting) {
					// Turning it on. This seems easy enough.
					Config::updateModSettings(['postmod_active' => 1]);
				} else {
					// Turning it off. Not so straightforward. We have to turn off warnings to moderation level, and make everything approved.
					Config::updateModSettings([
						'postmod_active' => 0,
						'warning_moderate' => 0,
					]);

					PostMod::approveAllData();
				}
			} elseif (Config::$modSettings['postmod_active']) {
				// We're not saving a new setting - and if it's still enabled we have more work to do.

				// Start by deleting all the permissions relevant.
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_profile = {int:current_profile}
						AND permission IN ({array_string:permissions})
						AND id_group IN ({array_int:profile_group_list})',
					[
						'profile_group_list' => array_keys(Utils::$context['profile_groups']),
						'current_profile' => Utils::$context['current_profile'],
						'permissions' => $all_permissions,
					],
				);

				// Do it group by group.
				$new_permissions = [];

				foreach (Utils::$context['profile_groups'] as $id => $group) {
					foreach ($this->postmod_maps as $index => $data) {
						if (isset($_POST[$index][$group->id])) {
							if ($_POST[$index][$group->id] == 'allow') {
								// Give them both sets for fun.
								$new_permissions[] = [Utils::$context['current_profile'], $group->id, $data[0], 1];

								$new_permissions[] = [Utils::$context['current_profile'], $group->id, $data[1], 1];
							} elseif ($_POST[$index][$group->id] == 'moderate') {
								$new_permissions[] = [Utils::$context['current_profile'], $group->id, $data[1], 1];
							}
						}
					}
				}

				// Insert new permissions.
				if (!empty($new_permissions)) {
					Db::$db->insert(
						'',
						'{db_prefix}board_permissions',
						['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
						$new_permissions,
						['id_profile', 'id_group', 'permission'],
					);
				}
			}
		}

		// Now get all the permissions!
		$request = Db::$db->query(
			'',
			'SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}
				AND permission IN ({array_string:permissions})
				AND id_group IN ({array_int:profile_group_list})',
			[
				'profile_group_list' => array_keys(Utils::$context['profile_groups']),
				'current_profile' => Utils::$context['current_profile'],
				'permissions' => $all_permissions,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			foreach ($this->postmod_maps as $key => $data) {
				foreach ($data as $index => $perm) {
					if ($perm == $row['permission']) {
						// Only bother if it's not denied.
						if ($row['add_deny']) {
							// Full allowance?
							if ($index == 0) {
								Utils::$context['profile_groups'][$row['id_group']][$key] = 'allow';
							}
							// Otherwise only bother with moderate if not on allow.
							elseif (Utils::$context['profile_groups'][$row['id_group']][$key] != 'allow') {
								Utils::$context['profile_groups'][$row['id_group']][$key] = 'moderate';
							}
						}
					}
				}
			}
		}
		Db::$db->free_result($request);

		SecurityToken::create('admin-mppm');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the permissions area.
	 */
	public static function getConfigVars(): array
	{
		// All the setting variables
		$config_vars = [
			['title', 'settings'],
			// Inline permissions.
			['permissions', 'manage_permissions'],
			'',

			// A few useful settings
			['check', 'permission_enable_deny', 0, Lang::$txt['permission_settings_enable_deny'], 'help' => 'permissions_deny'],
			['check', 'permission_enable_postgroups', 0, Lang::$txt['permission_settings_enable_postgroups'], 'help' => 'permissions_postgroups'],
		];

		IntegrationHook::call('integrate_modify_permission_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the list of all known permissions.
	 *
	 * This method contains the integrate_permissions_list hook, which is the
	 * recommended way to add new permissions to SMF.
	 *
	 * @return array Finalized version of self::$permissions
	 */
	public static function getPermissions(): array
	{
		if (!empty(self::$processed)) {
			return self::$permissions;
		}

		IntegrationHook::call('integrate_permissions_list', [&self::$permissions]);

		// In case a mod screwed things up...
		if (!in_array('html', Utils::$context['restricted_bbc'])) {
			Utils::$context['restricted_bbc'][] = 'html';
		}

		// Add the permissions for the restricted BBCodes
		foreach (Utils::$context['restricted_bbc'] as $bbc) {
			if (isset(self::$permissions['bbc_' . $bbc])) {
				continue;
			}

			self::$permissions['bbc_' . $bbc] = [
				'has_own_any' => false,
				'view_group' => 'bbc',
				'scope' => 'global',
				'vsprintf' => ['permissionname_bbc', [$bbc]],
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
		if (!Config::$modSettings['postmod_active']) {
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

		// Finalize various values.
		foreach (self::$permissions as $permission => &$perm_info) {
			$perm_info['generic_name'] = $perm_info['generic_name'] ?? $permission;
			$perm_info['hidden'] = !empty($perm_info['hidden']);
			$perm_info['never_guests'] = !empty($perm_info['never_guests']);
			$perm_info['assignee_prerequisites'] = $perm_info['assignee_prerequisites'] ?? [];
			$perm_info['assigner_prerequisites'] = $perm_info['assigner_prerequisites'] ?? [];

			$perm_info['label'] = $perm_info['label'] ?? 'permissionname_' . $perm_info['generic_name'];

			// Do we need to dynamically generate the label string?
			if (!empty($perm_info['vsprintf'])) {
				Lang::$txt[$perm_info['label']] = vsprintf(Lang::$txt[$perm_info['vsprintf'][0]] ?? $perm_info['vsprintf'][0], $perm_info['vsprintf'][1]);
			}
		}

		return self::$permissions;
	}

	/**
	 * Set the permission level for a specific profile, group, or group for a profile.
	 *
	 * @param string $level The level ('restrict', 'standard', etc.)
	 * @param int $group The group to set the permission for
	 * @param string|int $profile The ID of the permissions profile or 'null' if we're setting it for a group
	 */
	public static function setPermissionLevel($level, $group, $profile = 'null'): void
	{
		self::loadIllegalPermissions();
		self::loadIllegalGuestPermissions();
		self::loadIllegalBBCHtmlGroups();

		// Levels by group... restrict, standard, moderator, maintenance.
		$group_levels = [
			'board' => ['inherit' => []],
			'group' => ['inherit' => []],
		];
		// Levels by board... standard, publish, free.
		$board_levels = ['inherit' => []];

		foreach (self::getPermissions() as $permission => $perm_info) {
			if (isset($perm_info['group_level'])) {
				switch ($perm_info['group_level']) {
					case self::GROUP_LEVEL_RESTRICT:
						$group_levels[$perm_info['scope']]['restrict'][] = $permission;
						// no break

					case self::GROUP_LEVEL_STANDARD:
						$group_levels[$perm_info['scope']]['standard'][] = $permission;
						// no break

					case self::GROUP_LEVEL_MODERATOR:
						$group_levels[$perm_info['scope']]['moderator'][] = $permission;
						// no break

					case self::GROUP_LEVEL_MAINTENANCE:
						$group_levels[$perm_info['scope']]['maintenance'][] = $permission;
						break;
				}
			}

			if (isset($perm_info['board_level'])) {
				switch ($perm_info['board_level']) {
					case self::BOARD_LEVEL_STANDARD:
						$group_levels[$perm_info['scope']]['standard'][] = $permission;
						// no break

					case self::BOARD_LEVEL_LOCKED:
						$group_levels[$perm_info['scope']]['locked'][] = $permission;
						// no break

					case self::BOARD_LEVEL_PUBLISH:
						$group_levels[$perm_info['scope']]['publish'][] = $permission;
						// no break

					case self::BOARD_LEVEL_FREE:
						$group_levels[$perm_info['scope']]['free'][] = $permission;
						break;
				}
			}
		}

		IntegrationHook::call('integrate_load_permission_levels', [&$group_levels, &$board_levels]);

		// Make sure we're not granting someone too many permissions!
		foreach (['global', 'board'] as $scope) {
			foreach ($group_levels[$scope][$level] as $k => $permission) {
				if (in_array($permission, self::$illegal ?? [])) {
					unset($group_levels[$scope][$level][$k]);
				}

				if (in_array($group, self::$excluded[$permission] ?? [])) {
					unset($group_levels[$scope][$level][$k]);
				}
			}
		}

		// Reset all cached permissions.
		Config::updateModSettings(['settings_updated' => time()]);

		// Setting group permissions.
		if ($profile === 'null' && $group !== 'null') {
			$group = (int) $group;

			if (empty($group_levels['global'][$level])) {
				return;
			}

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}permissions
				WHERE id_group = {int:current_group}
				' . (empty(self::$illegal) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
				[
					'current_group' => $group,
					'illegal_permissions' => !empty(self::$illegal) ? self::$illegal : [],
				],
			);
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:default_profile}',
				[
					'current_group' => $group,
					'default_profile' => 1,
				],
			);

			$group_inserts = [];

			foreach ($group_levels['global'][$level] as $permission) {
				$group_inserts[] = [$group, $permission];
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}permissions',
				['id_group' => 'int', 'permission' => 'string'],
				$group_inserts,
				['id_group'],
			);

			$board_inserts = [];

			foreach ($group_levels['board'][$level] as $permission) {
				$board_inserts[] = [1, $group, $permission];
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$board_inserts,
				['id_profile', 'id_group'],
			);

			self::removeIllegalBBCHtmlPermission();
		}
		// Setting profile permissions for a specific group.
		elseif ($profile !== 'null' && $group !== 'null' && !in_array($profile, self::PROFILE_UNMODIFIABLE)) {
			$group = (int) $group;
			$profile = (int) $profile;

			if (!empty($group_levels['global'][$level])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_group = {int:current_group}
						AND id_profile = {int:current_profile}',
					[
						'current_group' => $group,
						'current_profile' => $profile,
					],
				);
			}

			if (!empty($group_levels['board'][$level])) {
				$board_inserts = [];

				foreach ($group_levels['board'][$level] as $permission) {
					$board_inserts[] = [$profile, $group, $permission];
				}

				Db::$db->insert(
					'insert',
					'{db_prefix}board_permissions',
					['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
					$board_inserts,
					['id_profile', 'id_group'],
				);
			}
		}
		// Setting profile permissions for all groups.
		elseif ($profile !== 'null' && $group === 'null' && !in_array($profile, self::PROFILE_UNMODIFIABLE)) {
			$profile = (int) $profile;

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}board_permissions
				WHERE id_profile = {int:current_profile}',
				[
					'current_profile' => $profile,
				],
			);

			if (empty($board_levels[$level])) {
				return;
			}

			// Get all the groups...
			$request = Db::$db->query(
				'',
				'SELECT id_group
				FROM {db_prefix}membergroups
				WHERE id_group > {int:moderator_group}
				ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE {int:newbie_group} END, group_name',
				[
					'moderator_group' => Group::MOD,
					'newbie_group' => Group::NEWBIE,
				],
			);

			while ($row = Db::$db->fetch_row($request)) {
				$group = $row[0];

				$board_inserts = [];

				foreach ($board_levels[$level] as $permission) {
					$board_inserts[] = [$profile, $group, $permission];
				}

				Db::$db->insert(
					'insert',
					'{db_prefix}board_permissions',
					['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
					$board_inserts,
					['id_profile', 'id_group'],
				);
			}
			Db::$db->free_result($request);

			// Add permissions for ungrouped members.
			$board_inserts = [];

			foreach ($board_levels[$level] as $permission) {
				$board_inserts[] = [$profile, 0, $permission];
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$board_inserts,
				['id_profile', 'id_group'],
			);
		}
		// $profile and $group are both null!
		else {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Make sure Config::$modSettings['board_manager_groups'] is up to date.
		if (!in_array('manage_boards', self::$illegal)) {
			self::updateBoardManagers();
		}
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variable for each permission.
	 * This method is used by several settings screens to set specific permissions.
	 *
	 * To exclude groups from the form for a given permission, add the group IDs as
	 * an array to Utils::$context['excluded_permissions'][$permission]. For backwards
	 * compatibility, it is also possible to pass group IDs in via the
	 * $excluded_groups parameter, which will exclude the groups from the forms for
	 * all of the permissions passed in via $permissions.
	 *
	 * @param array $permissions The permissions to display inline
	 * @param array $excluded_groups The IDs of one or more groups to exclude
	 *
	 * Uses ManagePermissions language
	 * Uses ManagePermissions template
	 */
	public static function init_inline_permissions($permissions, $excluded_groups = []): void
	{
		Lang::load('ManagePermissions');
		Theme::loadTemplate('ManagePermissions');
		Utils::$context['can_change_permissions'] = User::$me->allowedTo('manage_permissions');

		// Nothing to initialize here.
		if (!Utils::$context['can_change_permissions']) {
			return;
		}

		$query_customizations = [
			'where' => [
				'mg.id_group NOT IN ({array_int:excluded_groups})',
				'mg.id_parent = {int:not_inherited}',
				empty(Config::$modSettings['permission_enable_postgroups']) ? 'mg.min_posts = {int:min_posts}' : '1=1',
			],
			'order' => [
				'mg.min_posts',
				'CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE {int:newbie_group} END',
				'mg.group_name',
			],
			'params' => [
				'min_posts' => -1,
				'excluded_groups' => [Group::ADMIN, Group::MOD],
				'not_inherited' => Group::NONE,
				'newbie_group' => Group::NEWBIE,
			],
		];

		$groups = array_merge(
			Group::load([Group::GUEST, Group::REGULAR]),
			Group::load([], $query_customizations),
		);

		Group::loadPermissionsBatch(array_map(fn ($group) => $group->id, $groups), 0);

		foreach ($permissions as $permission) {
			foreach ($groups as $group) {
				Utils::$context[$permission][$group->id] = [
					'id' => $group->id,
					'name' => $group->name,
					'is_postgroup' => $group->min_posts > -1,
					'status' => !isset($group->permissions['general'][$permission]) ? 'off' : ($group->permissions['general'][$permission] === 1 ? 'on' : 'deny'),
				];
			}
		}

		// Make sure we honor the "illegal guest permissions"
		self::loadIllegalGuestPermissions();

		// Only special people can have this permission
		if (in_array('bbc_html', $permissions)) {
			self::loadIllegalBBCHtmlGroups();
		}

		// Are any of these permissions that guests can't have?
		$non_guest_perms = array_intersect(str_replace(['_any', '_own'], '', $permissions), self::$never_guests);

		foreach ($non_guest_perms as $permission) {
			if (!isset(self::$excluded[$permission]) || !in_array(-1, self::$excluded[$permission])) {
				self::$excluded[$permission][] = -1;
			}
		}

		// Any explicitly excluded groups for this call?
		if (!empty($excluded_groups)) {
			// Make sure this is an array of integers
			$excluded_groups = array_filter(
				(array) $excluded_groups,
				function ($v) {
					return is_int($v) || is_string($v) && (string) intval($v) === $v;
				},
			);

			foreach ($permissions as $permission) {
				self::$excluded[$permission] = array_unique(array_merge(self::$excluded[$permission], $excluded_groups));
			}
		}

		// Some permissions cannot be given to certain groups. Remove the groups.
		foreach ($permissions as $permission) {
			if (!isset(self::$excluded[$permission])) {
				continue;
			}

			foreach (self::$excluded[$permission] as $group_id) {
				if (isset(Utils::$context[$permission][$group_id])) {
					unset(Utils::$context[$permission][$group_id]);
				}
			}

			// There's no point showing a form with nobody in it
			if (empty(Utils::$context[$permission])) {
				unset(Utils::$context['config_vars'][$permission], Utils::$context[$permission]);
			}
		}

		// Create the token for the separate inline permission verification.
		SecurityToken::create('admin-mp');
	}

	/**
	 * Show a collapsible box to set a specific permission.
	 * The method is called by templates to show a list of permissions settings.
	 * Calls the template function template_inline_permissions().
	 *
	 * @param string $permission The permission to display inline
	 */
	public static function theme_inline_permissions($permission): void
	{
		Utils::$context['current_permission'] = $permission;
		Utils::$context['member_groups'] = Utils::$context[$permission];

		template_inline_permissions();
	}

	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @param array $permissions The permissions to save
	 */
	public static function save_inline_permissions($permissions): void
	{
		// No permissions? Not a great deal to do here.
		if (!User::$me->allowedTo('manage_permissions')) {
			return;
		}

		// Almighty session check, verify our ways.
		User::$me->checkSession();
		SecurityToken::validate('admin-mp');

		// Check they can't do certain things.
		self::loadIllegalPermissions();

		if (in_array('bbc_html', $permissions)) {
			self::loadIllegalBBCHtmlGroups();
		}

		$insert_rows = [];

		foreach ($permissions as $permission) {
			if (!isset($_POST[$permission])) {
				continue;
			}

			foreach ($_POST[$permission] as $id_group => $value) {
				if ($value == 'on' && !empty(Utils::$context['excluded_permissions'][$permission]) && in_array($id_group, Utils::$context['excluded_permissions'][$permission])) {
					continue;
				}

				if (in_array($value, ['on', 'deny']) && (empty(self::$illegal) || !in_array($permission, self::$illegal))) {
					$insert_rows[] = [(int) $id_group, $permission, $value == 'on' ? 1 : 0];
				}
			}
		}

		// Remove the old permissions...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}permissions
			WHERE permission IN ({array_string:permissions})
				' . (empty(self::$illegal) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			[
				'illegal_permissions' => !empty(self::$illegal) ? self::$illegal : [],
				'permissions' => $permissions,
			],
		);

		// ...and replace them with new ones.
		if (!empty($insert_rows)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}permissions',
				['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$insert_rows,
				['id_group', 'permission'],
			);
		}

		// Do a full child update.
		self::updateChildPermissions([], -1);

		// Make sure Config::$modSettings['board_manager_groups'] is up to date.
		if (!in_array('manage_boards', self::$illegal)) {
			self::updateBoardManagers();
		}

		Config::updateModSettings(['settings_updated' => time()]);
	}

	/**
	 * Load permissions profiles.
	 */
	public static function loadPermissionProfiles(): void
	{
		Utils::$context['profiles'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_profile, profile_name
			FROM {db_prefix}permission_profiles
			ORDER BY id_profile',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['id_profile'] = (int) $row['id_profile'];

			Utils::$context['profiles'][$row['id_profile']] = [
				'id' => $row['id_profile'],
				'name' => Lang::$txt['permissions_profile_' . $row['profile_name']] ?? $row['profile_name'],
				'can_modify' => !in_array($row['id_profile'], self::PROFILE_UNMODIFIABLE),
				'unformatted_name' => $row['profile_name'],
			];
		}
		Db::$db->free_result($request);
	}

	/**
	 * This function updates the permissions of any groups based off this group.
	 *
	 * @param int|array $parents The parent groups.
	 * @param int $profile The ID of a permissions profile to update
	 * @return void|false Returns nothing if successful or false if there are no
	 *    child groups to update.
	 */
	public static function updateChildPermissions(int|array|null $parents = null, ?int $profile = null)
	{
		// All the parent groups to sort out.
		$parents = array_unique(array_map('intval', (array) $parents));

		$parent_groups = Group::load($parents);

		$children = [];
		$child_groups = [];

		foreach ($parent_groups as $parent_group) {
			$parent_group->getChildren();

			$children[$parent_group->id] = array_keys($parent_group->children);
			$child_groups = array_merge($child_groups, array_keys($parent_group->children));
		}

		$parents = array_map(fn ($parent_group) => $parent_group->id, $parent_groups);

		// Not a sausage, or a child?
		if (empty($children)) {
			return false;
		}

		// First off, are we doing general permissions?
		if ($profile < 1 || $profile === null) {
			// Fetch all the parent permissions.
			$permissions = [];
			$request = Db::$db->query(
				'',
				'SELECT id_group, permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:parent_list})',
				[
					'parent_list' => $parents,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($children[$row['id_group']] as $child) {
					$permissions[] = [$child, $row['permission'], $row['add_deny']];
				}
			}
			Db::$db->free_result($request);

			if (!empty($child_groups)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:child_groups})',
					[
						'child_groups' => $child_groups,
					],
				);
			}

			// Finally insert.
			if (!empty($permissions)) {
				Db::$db->insert(
					'insert',
					'{db_prefix}permissions',
					['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
					$permissions,
					['id_group', 'permission'],
				);
			}
		}

		// Then, what about board profiles?
		if ($profile != -1) {
			$profile_query = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

			// Again, get all the parent permissions.
			$permissions = [];
			$request = Db::$db->query(
				'',
				'SELECT id_profile, id_group, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:parent_groups})
					' . $profile_query,
				[
					'parent_groups' => $parents,
					'current_profile' => $profile !== null && $profile ? $profile : 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($children[$row['id_group']] as $child) {
					$permissions[] = [$child, $row['id_profile'], $row['permission'], $row['add_deny']];
				}
			}
			Db::$db->free_result($request);

			if (!empty($child_groups)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:child_groups})
						' . $profile_query,
					[
						'child_groups' => $child_groups,
						'current_profile' => $profile !== null && $profile ? $profile : 1,
					],
				);
			}

			// Do the insert.
			if (!empty($permissions)) {
				Db::$db->insert(
					'insert',
					'{db_prefix}board_permissions',
					['id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
					$permissions,
					['id_group', 'id_profile', 'permission'],
				);
			}
		}
	}

	/**
	 * Loads a list of permissions that the current user cannot grant.
	 *
	 * @return array Permissions that the current user cannot grant.
	 */
	public static function loadIllegalPermissions(): array
	{
		foreach (self::getPermissions() as $permission => $perm_info) {
			if (!empty($perm_info['assigner_prerequisites']) && !User::$me->allowedTo($perm_info['assigner_prerequisites'])) {
				self::$illegal[] = $permission;
				self::$illegal[] = $perm_info['generic_name'];
			}
		}

		self::$illegal = array_unique(self::$illegal);

		self::integrateLoadIllegalPermissions();

		return self::$illegal;
	}

	/**
	 * Populates self::$hidden with a list of hidden permissions.
	 */
	public static function buildHidden(): void
	{
		if (isset(self::$hidden)) {
			return;
		}

		foreach (self::getPermissions() as $permission => $perm_info) {
			if (!empty($perm_info['hidden'])) {
				self::$hidden[] = $permission;
			}
		}

		// Backward compatibility.
		Utils::$context['hidden_permissions'] = self::$hidden;
	}

	/**
	 * Backward compatibility wrapper for the index sub-action.
	 */
	public static function permissionIndex(): void
	{
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the board sub-action.
	 */
	public static function permissionByBoard(): void
	{
		self::load();
		self::$obj->subaction = 'board';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the modify sub-action.
	 */
	public static function modifyMembergroup(): void
	{
		self::load();
		self::$obj->subaction = 'modify';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the modify2 sub-action.
	 */
	public static function modifyMembergroup2(): void
	{
		self::load();
		self::$obj->subaction = 'modify2';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the quick sub-action.
	 */
	public static function setQuickGroups(): void
	{
		self::load();
		self::$obj->subaction = 'quick';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the postmod sub-action.
	 */
	public static function modifyPostModeration(): void
	{
		self::load();
		self::$obj->subaction = 'postmod';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the profiles sub-action.
	 */
	public static function editPermissionProfiles(): void
	{
		self::load();
		self::$obj->subaction = 'profiles';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function generalPermissionSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Lang::load('ManagePermissions+ManageMembers');
		Theme::loadTemplate('ManagePermissions');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['permissions_title'],
			'help' => 'permissions',
			'description' => '',
			'tabs' => [
				'index' => [
					'description' => Lang::$txt['permissions_groups'],
				],
				'board' => [
					'description' => Lang::$txt['permission_by_board_desc'],
				],
				'profiles' => [
					'description' => Lang::$txt['permissions_profiles_desc'],
				],
				'postmod' => [
					'description' => Lang::$txt['permissions_post_moderation_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['permission_settings_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_permissions', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Populates Utils::$context['groups'].
	 *
	 * Helper method called from index().
	 */
	protected function setGroupsContext(): void
	{
		Utils::$context['groups'] = [];

		foreach (Group::loadSimple(Group::LOAD_BOTH, []) as $group) {
			if (
				// Skip child groups.
				$group->parent !== Group::NONE
				// Skip post groups if post group permissions are disabled.
				|| (
					empty(Config::$modSettings['permission_enable_postgroups'])
					&& $group->min_posts > -1
				)
			) {
				continue;
			}

			Utils::$context['groups'][$group->id] = $group;
		}

		// Count the members that each group has (except moderators).
		Group::countMembersBatch(array_diff(array_keys(Utils::$context['groups']), [Group::MOD]));

		// Count the permissions that each group has.
		if (!empty($_REQUEST['pid'])) {
			$_REQUEST['pid'] = (int) $_REQUEST['pid'];

			if (!isset(Utils::$context['profiles'][$_REQUEST['pid']])) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Change the selected tab to better reflect that this really is a board profile.
			Menu::$loaded['admin']['current_subsection'] = 'profiles';

			Utils::$context['profile'] = [
				'id' => $_REQUEST['pid'],
				'name' => Utils::$context['profiles'][$_REQUEST['pid']]['name'],
			];
		}

		Group::countPermissionsBatch(array_keys(Utils::$context['groups']), $_REQUEST['pid'] ?? null);
	}

	/**
	 * Sets a predefined permission profile.
	 *
	 * Helper method called from quick().
	 */
	protected function quickSetPredefined(): void
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], ['restrict', 'standard', 'moderator', 'maintenance'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		foreach ($_POST['group'] as $group_id) {
			if (!empty($_REQUEST['pid'])) {
				self::setPermissionLevel($_POST['predefined'], $group_id, $_REQUEST['pid']);
			} else {
				self::setPermissionLevel($_POST['predefined'], $group_id);
			}
		}
	}

	/**
	 * Sets a permission profile based on the permissions of a selected group.
	 *
	 * Helper method called from quick().
	 */
	protected function quickCopyFrom(): void
	{
		$pid = max(1, $_REQUEST['pid']);

		// Just checking the input.
		if (!is_numeric($_POST['copy_from'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff($_POST['group'], [$_POST['copy_from']]);

		// No groups left? Too bad.
		if (empty($_POST['group'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		if (empty($_REQUEST['pid'])) {
			// Retrieve current permissions of group.
			$target_perm = [];
			$request = Db::$db->query(
				'',
				'SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				[
					'copy_from' => $_POST['copy_from'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$target_perm[$row['permission']] = $row['add_deny'];
			}
			Db::$db->free_result($request);

			$inserts = [];

			foreach ($_POST['group'] as $group_id) {
				foreach ($target_perm as $perm => $add_deny) {
					// No dodgy permissions please!
					if (in_array($perm, self::$illegal)) {
						continue;
					}

					if (in_array($group_id, self::$excluded[$perm] ?? [])) {
						continue;
					}

					if ($group_id != 1 && $group_id != 3) {
						$inserts[] = [$perm, $group_id, $add_deny];
					}
				}
			}

			// Delete the previous permissions...
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:group_list})
					' . (empty(self::$illegal) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
				[
					'group_list' => $_POST['group'],
					'illegal_permissions' => self::$illegal,
				],
			);

			if (!empty($inserts)) {
				// ..and insert the new ones.
				Db::$db->insert(
					'',
					'{db_prefix}permissions',
					[
						'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
					],
					$inserts,
					['permission', 'id_group'],
				);
			}
		}

		// Now do the same for the board permissions.
		$target_perm = [];
		$request = Db::$db->query(
			'',
			'SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:copy_from}
				AND id_profile = {int:current_profile}',
			[
				'copy_from' => $_POST['copy_from'],
				'current_profile' => $pid,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$target_perm[$row['permission']] = $row['add_deny'];
		}
		Db::$db->free_result($request);

		$inserts = [];

		foreach ($_POST['group'] as $group_id) {
			foreach ($target_perm as $perm => $add_deny) {
				// Are these for guests?
				if ($group_id == -1 && in_array($perm, self::$never_guests)) {
					continue;
				}

				$inserts[] = [$perm, $group_id, $pid, $add_deny];
			}
		}

		// Delete the previous global board permissions...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:current_group_list})
				AND id_profile = {int:current_profile}',
			[
				'current_group_list' => $_POST['group'],
				'current_profile' => $pid,
			],
		);

		// And insert the copied permissions.
		if (!empty($inserts)) {
			// ..and insert the new ones.
			Db::$db->insert(
				'',
				'{db_prefix}board_permissions',
				['permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'],
				$inserts,
				['permission', 'id_group', 'id_profile'],
			);
		}

		// Update any children out there!
		self::updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}

	/**
	 * Sets or unsets a certain permission for the selected groups.
	 *
	 * Helper method called from quick().
	 */
	protected function quickSetPermission(): void
	{
		$pid = max(1, $_REQUEST['pid']);

		// Unpack two variables that were transported.
		list($scope, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (!in_array($_POST['add_remove'], ['add', 'clear', 'deny']) || !in_array($scope, ['global', 'board'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		if ($_POST['add_remove'] == 'clear') {
			if ($scope == 'global') {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND permission = {string:current_permission}
						' . (empty(self::$illegal) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
					[
						'current_group_list' => $_POST['group'],
						'current_permission' => $permission,
						'illegal_permissions' => self::$illegal,
					],
				);

				// Did these changes make anyone lose eligibility for the bbc_html permission?
				$bbc_html_groups = array_diff($_POST['group'], self::$excluded['bbc_html']);

				if (!empty($bbc_html_groups)) {
					self::removeIllegalBBCHtmlPermission(true);
				}
			} else {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND id_profile = {int:current_profile}
						AND permission = {string:current_permission}',
					[
						'current_group_list' => $_POST['group'],
						'current_profile' => $pid,
						'current_permission' => $permission,
					],
				);
			}
		}
		// Add a permission (either 'set' or 'deny').
		else {
			$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
			$perm_change = [];

			foreach ($_POST['group'] as $groupID) {
				if (isset(self::$excluded[$permission]) && in_array($groupID, self::$excluded[$permission])) {
					continue;
				}

				if ($scope == 'global' && $groupID != 1 && $groupID != 3 && !in_array($permission, self::$illegal)) {
					$perm_change[] = [$permission, $groupID, $add_deny];
				} elseif ($scope != 'global') {
					$perm_change[] = [$permission, $groupID, $pid, $add_deny];
				}
			}

			if (!empty($perm_change)) {
				if ($scope == 'global') {
					Db::$db->insert(
						'replace',
						'{db_prefix}permissions',
						['permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'],
						$perm_change,
						['permission', 'id_group'],
					);
				}
				// Board permissions go into the other table.
				else {
					Db::$db->insert(
						'replace',
						'{db_prefix}board_permissions',
						['permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'],
						$perm_change,
						['permission', 'id_group', 'id_profile'],
					);
				}
			}
		}

		// Another child update!
		self::updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}

	/**
	 * Populates Utils::$context['group'].
	 *
	 * Helper method called from modify().
	 */
	protected function setGroupContext(): void
	{
		if (!isset($_GET['group']) || (int) $_GET['group'] < -1) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Utils::$context['group']['id'] = (int) $_GET['group'];

		switch (Utils::$context['group']['id']) {
			case -1:
				Utils::$context['group']['name'] = Lang::$txt['membergroups_guests'];
				break;

			case 0:
				Utils::$context['group']['name'] = Lang::$txt['membergroups_members'];
				break;

			// Can't set permissions for admins.
			case 1:
				Utils::redirectexit('action=admin;area=permissions');
				break;

			default:
				$result = Db::$db->query(
					'',
					'SELECT group_name, id_parent
					FROM {db_prefix}membergroups
					WHERE id_group = {int:current_group}
					LIMIT 1',
					[
						'current_group' => Utils::$context['group']['id'],
					],
				);
				list(Utils::$context['group']['name'], $parent) = Db::$db->fetch_row($result);
				Db::$db->free_result($result);

				// Cannot edit an inherited group!
				if ($parent != -2) {
					ErrorHandler::fatalLang('cannot_edit_permissions_inherited');
				}
				break;
		}
	}

	/**
	 * Sets Utils::$context['profile'] and Utils::$context['permission_type'].
	 *
	 * Helper method called from modify().
	 */
	protected function setProfileContext(): void
	{
		self::loadPermissionProfiles();

		Utils::$context['profile']['id'] = (int) ($_GET['pid'] ?? 0);

		// If this is a moderator and they are editing "no profile" then we only do boards.
		if (Utils::$context['group']['id'] == 3 && empty(Utils::$context['profile']['id'])) {
			// For sanity just check they have no general permissions.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}permissions
				WHERE id_group = {int:moderator_group}',
				[
					'moderator_group' => 3,
				],
			);

			Utils::$context['profile']['id'] = self::PROFILE_DEFAULT;
		}

		Utils::$context['permission_type'] = empty(Utils::$context['profile']['id']) ? 'global' : 'board';

		Utils::$context['profile']['can_modify'] = !Utils::$context['profile']['id'] || Utils::$context['profiles'][Utils::$context['profile']['id']]['can_modify'];

		// Set up things a little nicer for board related stuff...
		if (Utils::$context['permission_type'] == 'board') {
			Utils::$context['profile']['name'] = Utils::$context['profiles'][Utils::$context['profile']['id']]['name'];

			Menu::$loaded['admin']['current_subsection'] = 'profiles';
		}
	}

	/**
	 * Fetches the current allowed or denied values stored in the database for
	 * each permission, and populates $this->allowed_denied with those values.
	 *
	 * Helper method called from modify().
	 *
	 * @param int $group ID number of a membergroup.
	 * @param string $scope Either 'global' or 'board'. If this is 'global', the
	 *    $profile param will always be treated as 1.
	 * @param int $profile Permission profile to use. Only applicable when the
	 *    $scope param is set to 'board'.
	 */
	protected function setAllowedDenied(int $group, string $scope = 'global', int $profile = 1): void
	{
		// General permissions?
		if ($scope == 'global') {
			$profile = 1;

			$result = Db::$db->query(
				'',
				'SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:current_group}',
				[
					'current_group' => $group,
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$this->allowed_denied['global'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
			}
			Db::$db->free_result($result);
		}

		// Fetch current board permissions...
		$result = Db::$db->query(
			'',
			'SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:current_profile}',
			[
				'current_group' => $group,
				'current_profile' => $profile,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$this->allowed_denied['board'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		}
		Db::$db->free_result($result);
	}

	/**
	 * Sets 'select' for each permission in Utils::$context['permissions'].
	 * Also populates Utils::$context['hidden_perms'].
	 *
	 * Helper method called from modify().
	 */
	protected function setOnOff(): void
	{
		self::loadAllPermissions();
		Utils::$context['hidden_perms'] = [];

		// Loop through each permission and set whether it's on, off, or denied.
		foreach (Utils::$context['permissions'] as $scope => $tmp) {
			foreach ($tmp['columns'] as $position => $permission_groups) {
				foreach ($permission_groups as $group_name => $group) {
					foreach ($group['permissions'] as $perm) {
						// Create a shortcut for the current permission.
						$cur_perm = &Utils::$context['permissions'][$scope]['columns'][$position][$group_name]['permissions'][$perm['id']];

						if ($perm['has_own_any']) {
							$cur_perm['any']['select'] = in_array($perm['id'] . '_any', $this->allowed_denied[$scope]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $this->allowed_denied[$scope]['denied']) ? 'deny' : 'off');

							$cur_perm['own']['select'] = in_array($perm['id'] . '_own', $this->allowed_denied[$scope]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $this->allowed_denied[$scope]['denied']) ? 'deny' : 'off');
						} else {
							$cur_perm['select'] = in_array($perm['id'], $this->allowed_denied[$scope]['denied']) ? 'deny' : (in_array($perm['id'], $this->allowed_denied[$scope]['allowed']) ? 'on' : 'off');
						}

						// Keep the last value if it's hidden.
						if (!empty($perm['hidden']) || !empty($group['hidden'])) {
							if ($perm['has_own_any']) {
								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['own']['id'],
									$cur_perm['own']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['own']['select'],
								];

								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['any']['id'],
									$cur_perm['any']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['any']['select'],
								];
							} else {
								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['id'],
									$cur_perm['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['select'],
								];
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Gets the parent membergroup of the given membergroup.
	 *
	 * This is used to determine permission inheritance.
	 *
	 * @param int $group ID of a membergroup.
	 * @return int The ID of the parent membergroup, or -2 if it has no parent.
	 */
	protected function getParentGroup(int $group): int
	{
		if ($group == -1 || $group == 0) {
			return -2;
		}

		$request = Db::$db->query(
			'',
			'SELECT id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			[
				'current_group' => $group,
			],
		);

		if (Db::$db->num_rows($request) === 0) {
			ErrorHandler::fatalLang('no_access', false);
		}

		list($parent) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		return $parent;
	}

	/**
	 * Saves global permissions to the database for the given membergroup.
	 *
	 * @param int $group ID of a membergroup.
	 * @param array $give_perms The permissions this group has been granted.
	 * @param array $illegal_permissions Permissions that cannot be changed for
	 *    this group.
	 */
	protected function updateGlobalPermissions(int $group, array $give_perms, array $illegal_permissions): void
	{
		// First, delete all the existing permissions for this group.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			[
				'current_group' => $group,
				'illegal_permissions' => $illegal_permissions,
			],
		);

		// This should already have been done, but just in case...
		foreach ($give_perms['global'] as $k => $v) {
			if (in_array($v[1], $illegal_permissions)) {
				unset($give_perms['global'][$k]);
			}
		}

		// Now grant this group whichever permissions it can have.
		if (!empty($give_perms['global'])) {
			Db::$db->insert(
				'replace',
				'{db_prefix}permissions',
				['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$give_perms['global'],
				['id_group', 'permission'],
			);
		}
	}

	/**
	 * Saves board permissions to the database for the given membergroup.
	 *
	 * @param int $group ID of a membergroup.
	 * @param array $give_perms The permissions this group has been granted.
	 * @param array $illegal_permissions Permissions that cannot be changed for
	 *    this group.
	 * @param int $profileid ID of a permission profile.
	 */
	protected function updateBoardPermissions(int $group, array $give_perms, array $illegal_permissions, int $profileid): void
	{
		$profileid = max(1, $profileid);

		// Again, we start by clearing all the permissions for this group.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:current_profile}',
			[
				'current_group' => $group,
				'current_profile' => $profileid,
			],
		);

		// This should already have been done, but just in case...
		foreach ($give_perms['board'] as $k => $v) {
			if (in_array($v[1], $illegal_permissions)) {
				unset($give_perms['board'][$k]);
			}
		}

		// Grant them whichever permissions they are now allowed to have.
		if (!empty($give_perms['board'])) {
			foreach ($give_perms['board'] as $k => $v) {
				$give_perms['board'][$k][] = $profileid;
			}

			Db::$db->insert(
				'replace',
				'{db_prefix}board_permissions',
				['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int', 'id_profile' => 'int'],
				$give_perms['board'],
				['id_group', 'permission', 'id_profile'],
			);
		}
	}

	/**
	 * Creates a new permission profile by copying an existing one.
	 *
	 * The ID of the profile to copy must be given in $_POST['copy_from'].
	 * The name for the new profile must be given in $_POST['profile_name'].
	 */
	protected function createProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		$_POST['copy_from'] = (int) $_POST['copy_from'];
		$_POST['profile_name'] = Utils::htmlspecialchars($_POST['profile_name']);

		// Insert the profile itself.
		$profile_id = Db::$db->insert(
			'',
			'{db_prefix}permission_profiles',
			[
				'profile_name' => 'string',
			],
			[
				$_POST['profile_name'],
			],
			['id_profile'],
			1,
		);

		// Load the permissions from the one it's being copied from.
		$inserts = [];

		$request = Db::$db->query(
			'',
			'SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:copy_from}',
			[
				'copy_from' => $_POST['copy_from'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [$profile_id, $row['id_group'], $row['permission'], $row['add_deny']];
		}
		Db::$db->free_result($request);

		if (!empty($inserts)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$inserts,
				['id_profile', 'id_group', 'permission'],
			);
		}
	}

	/**
	 * Renames one or more permission profiles.
	 *
	 * Acts on the profiles listed in $_POST['rename_profile'], where keys are
	 * ID numbers of existing profiles, and values are the new names.
	 *
	 * If $_POST['rename_profile'] is not set, this method will instead instruct
	 * the UI to show input fields to allow the admin to rename the profiles.
	 */
	protected function renameProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		// Just showing the input fields?
		if (!isset($_POST['rename_profile'])) {
			Utils::$context['show_rename_boxes'] = true;

			return;
		}

		foreach ($_POST['rename_profile'] as $id => $value) {
			if (($id = (int) $id) <= 0) {
				continue;
			}

			$value = Utils::htmlspecialchars($value);

			if (trim($value) != '' && !in_array($id, self::PROFILE_PREDEFINED)) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}permission_profiles
					SET profile_name = {string:profile_name}
					WHERE id_profile = {int:current_profile}',
					[
						'current_profile' => $id,
						'profile_name' => $value,
					],
				);
			}
		}
	}

	/**
	 * Deletes one or more permission profiles.
	 *
	 * Acts on profiles listed in $_POST['delete_profile'], which must be an
	 * array of profile ID numbers.
	 *
	 * Attempts to delete predefined profiles will be silently rejected.
	 *
	 * Attempts to delete profiles that are in use will abort with an error.
	 */
	protected function deleteProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		$profiles = [];

		foreach (array_map('intval', $_POST['delete_profile']) as $profile) {
			if ($profile > 0 && !in_array($profile, self::PROFILE_PREDEFINED)) {
				$profiles[] = $profile;
			}
		}

		// Verify it's not in use...
		$request = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}boards
			WHERE id_profile IN ({array_int:profile_list})
			LIMIT 1',
			[
				'profile_list' => $profiles,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			ErrorHandler::fatalLang('no_access', false);
		}
		Db::$db->free_result($request);

		// Oh well, delete.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}permission_profiles
			WHERE id_profile IN ({array_int:profile_list})',
			[
				'profile_list' => $profiles,
			],
		);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Load permissions into Utils::$context['permissions'].
	 */
	protected static function loadAllPermissions(): void
	{
		// We need to know what permissions we can't give to guests.
		self::loadIllegalGuestPermissions();

		// We also need to know which groups can't be given the bbc_html permission.
		self::loadIllegalBBCHtmlGroups();

		// Backward compatibility with the integrate_load_permissions hook.
		self::integrateLoadPermissions();

		// Figure out which permissions should be hidden.
		self::buildHidden();

		Utils::$context['permissions'] = [];

		foreach (self::$permission_groups as $scope => $groups) {
			if (!isset(Utils::$context['permissions'][$scope])) {
				Utils::$context['permissions'][$scope] = [
					'id' => $scope,
					'columns' => [
						[],
						[],
					],
				];
			}

			foreach ($groups as $group) {
				$position = (int) (!in_array($group, self::$left_permission_groups));

				Utils::$context['permissions'][$scope]['columns'][$position][$group] = [
					'type' => $scope,
					'id' => $group,
					'name' => Lang::$txt['permissiongroup_' . $group],
					'icon' => Lang::$txt['permissionicon_' . $group] ?? Lang::$txt['permissionicon'],
					'help' => Lang::$txt['permissionhelp_' . $group] ?? '',
					'hidden' => false,
					'permissions' => [],
				];
			}
		}

		foreach (self::getPermissions() as $permission => $perm_info) {
			// If this permission shouldn't be given to certain groups (e.g. guests), don't.
			foreach ([$permission, $perm_info['generic_name']] as $perm) {
				if (isset(Utils::$context['group']['id']) && in_array(Utils::$context['group']['id'], self::$excluded[$perm] ?? [])) {
					continue 2;
				}
			}

			// What column should this be located in?
			$position = (int) (!in_array($perm_info['view_group'], self::$left_permission_groups));

			// For legibility reasons...
			$view_group_perms = &Utils::$context['permissions'][$perm_info['scope']]['columns'][$position][$perm_info['view_group']]['permissions'];

			if (!isset($view_group_perms[$perm_info['generic_name']])) {
				$view_group_perms[$perm_info['generic_name']] = [
					'id' => $perm_info['generic_name'],
					'name' => Lang::$txt[$perm_info['label']],
					'show_help' => isset(Lang::$txt['permissionhelp_' . $perm_info['generic_name']]),
					'note' => Lang::$txt['permissionnote_' . $perm_info['generic_name']] ?? '',
					'hidden' => !empty($perm_info['hidden']),
				];
			}

			$view_group_perms[$perm_info['generic_name']]['has_own_any'] = isset($perm_info['own_any']);

			if (isset($perm_info['own_any'])) {
				$view_group_perms[$perm_info['generic_name']][$perm_info['own_any']] = [
					'id' => $permission,
					'name' => Lang::$txt['permissionname_' . $permission],
				];
			}
		}

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach (Utils::$context['permissions'] as $scope => $section) {
			foreach ($section['columns'] as $column => $groups) {
				foreach ($groups as $id => $group) {
					if (empty($group['permissions'])) {
						unset(Utils::$context['permissions'][$scope]['columns'][$column][$id]);
					} else {
						$show_this_group = false;

						foreach ($group['permissions'] as $permission) {
							if (empty($permission['hidden'])) {
								$show_this_group = true;
							}
						}

						if (!$show_this_group) {
							Utils::$context['permissions'][$scope]['columns'][$column][$id]['hidden'] = true;
						}
					}
				}
			}
		}
	}

	/**
	 * Loads the permissions that cannot be given to guests.
	 *
	 * Stores the permissions in self::$never_guests.
	 * Also populates self::$excluded with the info.
	 *
	 * @return array A copy of self::$never_guests.
	 */
	protected static function loadIllegalGuestPermissions(): array
	{
		// Find the permissions that guests may never have.
		foreach (self::getPermissions() as $permission => $perm_info) {
			if (!empty($perm_info['never_guests'])) {
				self::$never_guests[] = $perm_info['generic_name'];
			}
		}

		self::$never_guests = array_unique(self::$never_guests);

		// Call the deprecated integrate_load_illegal_guest_permissions hook.
		self::integrateLoadIllegalGuestPermissions();

		// Also add this info to self::$excluded to make life easier for everyone
		foreach (self::$never_guests as $permission) {
			if (empty(self::$excluded[$permission]) || !in_array($permission, self::$excluded[$permission])) {
				self::$excluded[$permission][] = -1;
			}
		}

		return self::$never_guests;
	}

	/**
	 * Loads a list of membergroups who cannot be granted the bbc_html permission.
	 * Stores the groups in self::$excluded['bbc_html'].
	 *
	 * @return array A copy of self::$excluded['bbc_html'].
	 */
	protected static function loadIllegalBBCHtmlGroups(): array
	{
		self::$excluded['bbc_html'] = [-1, 0];

		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group != {int:admin} AND id_group NOT IN (
				SELECT DISTINCT id_group
				FROM {db_prefix}permissions
				WHERE permission IN ({array_string:permissions})
					AND add_deny = {int:add}
			)',
			[
				'permissions' => self::$permissions['bbc_html']['assignee_prerequisites'],
				'add' => 1,
				'admin' => Group::ADMIN,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$excluded['bbc_html'][] = $row['id_group'];
		}
		Db::$db->free_result($request);

		self::$excluded['bbc_html'] = array_unique(self::$excluded['bbc_html']);

		return self::$excluded['bbc_html'];
	}

	/**
	 * Removes the bbc_html permission from anyone who shouldn't have it.
	 *
	 * @param bool $reload Before acting, refresh the list of membergroups who
	 *    cannot be granted the bbc_html permission
	 */
	protected static function removeIllegalBBCHtmlPermission($reload = false): void
	{
		if (empty(self::$excluded['bbc_html']) || $reload) {
			self::loadIllegalBBCHtmlGroups();
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:current_group_list})
				AND permission = {string:current_permission}
				AND add_deny = {int:add}',
			[
				'current_group_list' => self::$excluded['bbc_html'],
				'current_permission' => 'bbc_html',
				'add' => 1,
			],
		);
	}

	/**
	 * Makes sure Config::$modSettings['board_manager_groups'] is up to date.
	 */
	protected static function updateBoardManagers(): void
	{
		$board_managers = User::groupsAllowedTo('manage_boards', null);
		$board_managers = implode(',', $board_managers['allowed']);

		Config::updateModSettings(['board_manager_groups' => $board_managers], true);
	}

	/**
	 * Calls the deprecated integrate_load_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$modSettings['integrate_load_permissions'])) {
			return;
		}

		$permissions_by_scope = [
			'global' => [],
			'board' => [],
		];
		$hidden_permissions = [];
		$relabel_permissions = [];

		foreach (self::getPermissions() as $permission => $perm_info) {
			$permissions_by_scope[$perm_info['scope']][$perm_info['generic_name']] = [
				!empty($perm_info['own_any']),
				$perm_info['view_group'],
			];

			if (!empty($perm_info['hidden'])) {
				$hidden_permissions[] = $perm_info['generic_name'];
			}
		}

		// Provide a practical way to modify permissions.
		IntegrationHook::call('integrate_load_permissions', [&self::$permission_groups, &$permissions_by_scope, &self::$left_permission_groups, &$hidden_permissions, &$relabel_permissions]);

		// If the hook made changes, sync them back to our master list.
		foreach ($permissions_by_scope as $scope => $permissions) {
			foreach ($permissions as $permission => $perm_info) {
				$is_new = true;

				foreach (['', '_own', '_any'] as $suffix) {
					if (isset(self::$permissions[$permission . $suffix])) {
						$is_new = false;

						self::$permissions[$permission . $suffix]['view_group'] = $perm_info[1];
					}
				}

				if ($is_new) {
					$new_ids = $perm_info[0] ? [$permission . '_own', $permission . '_any'] : [$permission];

					foreach ($new_ids as $id) {
						self::$permissions[$id] = [
							'generic_name' => $permission,
							'own_any' => $perm_info[0] ? substr($id, -3) : null,
							'view_group' => $perm_info[1],
							'scope' => $scope === 'board' ? 'board' : 'global',
							'hidden' => in_array($permission, $hidden_permissions),
							'label' => 'permissionname_' . $permission,
							'never_guests' => in_array($permission, self::$never_guests),
						];
					}
				}
			}
		}

		foreach ($hidden_permissions as $permission) {
			foreach (['', '_own', '_any'] as $suffix) {
				if (isset(self::$permissions[$permission . $suffix])) {
					self::$permissions[$permission . $suffix]['hidden'] = true;
				}
			}
		}

		foreach ($relabel_permissions as $permission => $label) {
			foreach (['', '_own', '_any'] as $suffix) {
				if (isset(self::$permissions[$permission . $suffix])) {
					self::$permissions[$permission . $suffix]['label'] = $label;
				}
			}
		}
	}

	/**
	 * Calls the deprecated integrate_load_illegal_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadIllegalPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$modSettings['integrate_load_illegal_permissions'])) {
			return;
		}

		// This context variable exists only for the sake of the hook.
		Utils::$context['illegal_permissions'] = &self::$illegal;

		// Track whether the hook makes any changes.
		$temp = Utils::jsonEncode(self::$illegal);

		// Give mods access to this list.
		IntegrationHook::call('integrate_load_illegal_permissions');

		// If the hook added anything, sync that back to our master list.
		// Because this hook can't tell us what the prerequistes are, we assume
		// that the permission can only be granted by admins.
		if ($temp != Utils::jsonEncode(self::$illegal)) {
			foreach (self::$illegal as $permission) {
				foreach (['', '_own', '_any'] as $suffix) {
					if (isset(self::$permissions[$permission . $suffix]) && !isset(self::$permissions[$permission . $suffix]['assigner_prerequisites'])) {
						self::$permissions[$permission . $suffix]['assigner_prerequisites'] = ['admin_forum'];
					}
				}
			}
		}

		// We don't need this anymore.
		unset(Utils::$context['illegal_permissions']);
	}

	/**
	 * Calls the deprecated integrate_load_illegal_guest_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list.
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadIllegalGuestPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$modSettings['integrate_load_illegal_guest_permissions'])) {
			return;
		}

		// This context variable exists only for the sake of the hook.
		Utils::$context['non_guest_permissions'] = &self::$never_guests;

		// Track whether the hook makes any changes.
		$temp = Utils::jsonEncode(self::$never_guests);

		// Give mods access to this list.
		IntegrationHook::call('integrate_load_illegal_guest_permissions');

		// If the hook changed anything, sync that back to our master list.
		if ($temp != Utils::jsonEncode(self::$never_guests)) {
			// Did the hook add a permission to self::$never_guests?
			foreach (self::$never_guests as $permission) {
				foreach (['', '_own', '_any'] as $suffix) {
					if (isset(self::$permissions[$permission . $suffix])) {
						self::$permissions[$permission . $suffix]['never_guests'] = true;
					}
				}
			}

			// Did the hook remove a permission from self::$never_guests?
			foreach (self::$permissions as $permission => $perm_info) {
				if (!in_array($perm_info['generic_name'], self::$never_guests)) {
					self::$permissions[$permission]['never_guests'] = false;
				}
			}
		}

		// We don't need this anymore.
		unset(Utils::$context['non_guest_permissions']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Permissions::exportStatic')) {
	Permissions::exportStatic();
}

?>