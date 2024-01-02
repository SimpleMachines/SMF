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

namespace SMF;

use SMF\Actions\Admin\Permissions;
use SMF\Db\DatabaseApi as Db;

/**
 * Represents a member group.
 */
class Group implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'loadSimple' => 'loadSimple',
			'loadAssignable' => 'loadAssignable',
			'loadPermissionsBatch' => 'loadPermissionsBatch',
			'countPermissionsBatch' => 'countPermissionsBatch',
			'getPostGroups' => 'getPostGroups',
			'getUnassignable' => 'getUnassignable',
			'getCachedList' => 'cache_getMembergroupList',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// Reserved group IDs.
	public const NONE = -2;
	public const GUEST = -1;
	public const REGULAR = 0;
	public const ADMIN = 1;
	public const GLOBAL_MOD = 2;
	public const MOD = 3;
	public const NEWBIE = 4;

	// Group types.
	public const TYPE_PRIVATE = 0;
	public const TYPE_PROTECTED = 1;
	public const TYPE_REQUESTABLE = 2;
	public const TYPE_FREE = 3;

	// Group visibility levels.
	public const VISIBLE = 0;
	public const NO_GROUP_KEY = 1;
	public const INVISIBLE = 2;

	// Load types for the loadSimple() method.
	public const LOAD_NORMAL = 1;
	public const LOAD_POST = 2;
	public const LOAD_BOTH = 3;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The group's ID number.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * The group's name.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * The group's description.
	 */
	public string $description;

	/**
	 * @var string
	 *
	 * The group's color.
	 */
	public string $online_color = '';

	/**
	 * @var int
	 *
	 * How many posts are required to be in this group.
	 *
	 * Always -1 for groups that are not based on post count.
	 */
	public int $min_posts;

	/**
	 * @var int
	 *
	 * How many personal messages members of this group can have in their inbox.
	 */
	public int $max_messages = 0;

	/**
	 * @var string
	 *
	 * The group's icons, as stored in the database.
	 */
	public string $raw_icons = '';

	/**
	 * @var int
	 *
	 * The number of icons to show.
	 */
	public int $icon_count = 0;

	/**
	 * @var string
	 *
	 * The icon image.
	 */
	public string $icon_image = '';

	/**
	 * @var int
	 *
	 * Determines who can add members to this group.
	 *
	 * Possible values are one of this class's TYPE_* constants:
	 *    TYPE_PRIVATE     => Only users with the manage_membergroups permission
	 *                        can add members.
	 *    TYPE_PROTECTED   => Only administrators can add members.
	 *    TYPE_REQUESTABLE => Users may request to join, but the group moderator
	 *                        must approve the request before the user is added.
	 *    TYPE_FREE        => Users can join and leave at will.
	 *
	 * Note that post-based groups are internally stored as type 0, but in fact
	 * the type value is ignored for them.
	 */
	public int $type;

	/**
	 * @var int
	 *
	 * Determines this group's (lack of) visibility.
	 *
	 * Possible values are:
	 *    self::VISIBLE      => Visible.
	 *    self::NO_GROUP_KEY => Hidden in group key, but otherwise visible.
	 *    self::INVISIBLE    => Invisible.
	 */
	public int $hidden = self::VISIBLE;

	/**
	 * @var int
	 *
	 * ID of the group from which this group inherits permissions.
	 *
	 * self::NONE means it does not inherit any permissions.
	 */
	public int $parent = self::NONE;

	/**
	 * @var array
	 *
	 * Groups that inherit permissions from this group.
	 *
	 * Keys are IDs, values are names.
	 */
	public array $children;

	/**
	 * @var bool
	 *
	 * Whether members of this group are required to use two factor
	 * authentication.
	 */
	public bool $tfa_required = false;

	/**
	 * @var bool
	 *
	 * Whether the current user can moderate this group.
	 */
	public bool $can_moderate = false;

	/**
	 * @var array
	 *
	 * IDs of members who can moderate this group.
	 */
	public array $moderator_ids;

	/**
	 * @var array
	 *
	 * Members who can moderate this group, formatted for display.
	 * Items in this array take different forms in different cases.
	 */
	public array $moderators = [];

	/**
	 * @var array
	 *
	 * IDs of members who are in this group.
	 */
	public array $members;

	/**
	 * @var int
	 *
	 * How many members are in this group.
	 */
	public int $num_members;

	/**
	 * @var array
	 *
	 * IDs of boards that this group can moderate.
	 */
	public array $boards_can_moderate;

	/**
	 * @var array
	 *
	 * Permissions that this group has.
	 *
	 * Contains two sub-arrays, 'general' and 'board_profiles'.
	 *
	 * General permissions are listed as key-value pairs where the keys are
	 * permission names and values are integers.
	 *
	 * Board permissions are listed with the keys being permission profile IDs,
	 * the values being sub-arrays containing key-value pairs similar to what is
	 * used for the general permissions.
	 *
	 * As in the database table itself, 0 means denied and 1 means allowed.
	 * A permission that is not listed at all is neither granted nor denied.
	 */
	public array $permissions = [
		'general' => [],
		'board_profiles' => [],
	];

	/**
	 * @var array
	 *
	 * The numbers of allowed and denied permissions that this group has.
	 *
	 * Contains two sub-arrays, 'allowed' and 'denied'.
	 *
	 * This is typically only used by SMF\Actions\Admin\Permissions.
	 */
	public array $num_permissions = [
		'allowed' => 0,
		'denied' => 0,
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_group' => 'id',
		'group_name' => 'name',
		'editable_name' => 'name',
		'group_type' => 'type',
		'id_parent' => 'parent',
		'inherited_from' => 'parent',
		'desc' => 'description',
		'color' => 'online_color',
		'member_count' => 'num_members',
		'protected' => __CLASS__ . '::isProtected',
		'is_protected' => __CLASS__ . '::isProtected',
		'is_post_group' => __CLASS__ . '::isPostGroup',
		'is_postgroup' => __CLASS__ . '::isPostGroup',
		'is_moderator_group' => __CLASS__ . '::isModeratorGroup',
		'assignable' => __CLASS__ . '::isAssignable',
		'is_assignable' => __CLASS__ . '::isAssignable',
		'allow_post_group' => __CLASS__ . '::canBePostGroup',
		'allow_protected' => __CLASS__ . '::canBeProtected',
		'allow_delete' => __CLASS__ . '::canDelete',
		'allow_modify' => __CLASS__ . '::canChangePermissions',
		'can_be_post_group' => __CLASS__ . '::canBePostGroup',
		'can_be_protected' => __CLASS__ . '::canBeProtected',
		'can_be_additional' => __CLASS__ . '::canBeAdditional',
		'can_be_primary' => __CLASS__ . '::canBePrimary',
		'can_change_type' => __CLASS__ . '::canChangePermissions',
		'can_change_permissions' => __CLASS__ . '::canChangePermissions',
		'can_delete' => __CLASS__ . '::canDelete',
		'can_search' => __CLASS__ . '::canSearch',
		'can_leave' => __CLASS__ . '::canLeave',
		'icons' => __CLASS__ . '::formatIcons',
		'help' => __CLASS__ . '::getHelpTxt',
		'href' => __CLASS__ . '::getHref',
		'link' => __CLASS__ . '::getLink',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * IDs of groups that the current user cannot assign.
	 */
	protected static array $unassignable;

	/**
	 * @var array
	 *
	 * IDs of all post-count based groups.
	 */
	protected static array $post_groups;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the group.
	 * @param array $props Properties to set for this group. If empty, will be
	 *    loaded from the database automatically.
	 */
	public function __construct(int $id, array $props = [])
	{
		if ($id > self::REGULAR && empty($props)) {
			$request = Db::$db->query(
				'',
				'SELECT *
				FROM {db_prefix}membergroups
				WHERE id_group = {int:id}
				LIMIT 1',
				[
					'id' => $id,
				],
			);
			$props = Db::$db->fetch_all($request);
			Db::$db->free_result($request);
		}

		$this->id = $id;
		$this->set($props);
		self::$loaded[$this->id] = $this;

		// Some special cases.
		if (in_array($this->id, [self::GUEST, self::REGULAR])) {
			if (empty($this->name)) {
				if ($this->id === self::GUEST || !isset(Lang::$txt['announce_regular_members'])) {
					Lang::load('Admin');
				}

				$this->name = $this->id === self::GUEST ? Lang::$txt['membergroups_guests'] : (Lang::$txt['announce_regular_members'] ?? Lang::$txt['membergroups_members']);
			}

			if ($this->id === self::REGULAR && !isset($this->description)) {
				Lang::load('Profile');

				$this->description = Lang::$txt['regular_members_desc'];
			}
		}

		if (!isset($this->min_posts) && ($this->id < self::NEWBIE || !empty($this->type))) {
			$this->min_posts = -1;
		}

		// Set initial value for $this->can_moderate.
		// This might change when $this->loadModerators() is called.
		$this->can_moderate = User::$me->allowedTo('manage_membergroups');
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, mixed $value): void
	{
		// Special handling for the icons.
		if ($prop === 'icons' && is_string($value)) {
			$prop = 'raw_icons';

			if (preg_match('/^\d+#/', $value)) {
				list($this->icon_count, $this->icon_image) = explode('#', $value);
			} else {
				$this->icon_count = 0;
				$this->icon_image = '';
				$value = '';
			}
		}

		$this->customPropertySet($prop, $value);
	}

	/**
	 * Saves this group to the database.
	 */
	public function save(): void
	{
		User::$me->isAllowedTo('manage_membergroups');
		User::$me->checkSession();
		SecurityToken::validate('admin-mmg');

		// Saving a new group.
		if (empty($this->id)) {
			IntegrationHook::call('integrate_pre_add_membergroup', []);

			$columns = [
				'group_name' => 'string-80',
				'description' => 'string',
				'online_color' => 'string',
				'min_posts' => 'int',
				'max_messages' => 'int',
				'icons' => 'string',
				'group_type' => 'int',
				'hidden' => 'int',
				'id_parent' => 'int',
				'tfa_required' => 'int',
			];

			$params = [
				$this->name ?? '',
				$this->description ?? '',
				$this->online_color ?? '',
				$this->min_posts ?? -1,
				$this->max_messages ?? 0,
				$this->raw_icons ?? '',
				$this->type ?? self::TYPE_PRIVATE,
				$this->hidden ?? self::VISIBLE,
				$this->parent ?? self::NONE,
				(int) ($this->tfa_required ?? 0),
			];

			$this->id = Db::$db->insert(
				'',
				'{db_prefix}membergroups',
				$columns,
				$params,
				['id_group'],
				1,
			);

			self::$loaded[$this->id] = $this;

			IntegrationHook::call('integrate_add_membergroup', [$this->id, $this->min_posts > -1]);
		}
		// Updating an existing group.
		else {
			$set = [
				'group_name = {string:name}',
				'description = {string:description}',
				'online_color = {string:online_color}',
				'min_posts = {int:min_posts}',
				'max_messages = {int:max_messages}',
				'icons = {string:raw_icons}',
				'group_type = {int:type}',
				'hidden = {int:hidden}',
				'id_parent = {int:parent}',
				'tfa_required = {int:tfa_required}',
			];

			$params = [
				'id' => $this->id,
				'name' => $this->name ?? '',
				'description' => $this->description ?? '',
				'online_color' => $this->online_color ?? '',
				'min_posts' => $this->min_posts ?? -1,
				'max_messages' => $this->max_messages ?? 0,
				'raw_icons' => $this->raw_icons ?? '',
				'type' => $this->type ?? self::TYPE_PRIVATE,
				'hidden' => $this->hidden ?? self::VISIBLE,
				'parent' => $this->parent ?? self::NONE,
				'tfa_required' => (int) ($this->tfa_required ?? 0),
			];

			Db::$db->query(
				'',
				'UPDATE {db_prefix}membergroups
				SET ' . (implode(', ', $set)) . '
				WHERE id_group = {int:id}',
				$params,
			);

			IntegrationHook::call('integrate_save_membergroup', [$this->id]);
		}

		// Update membership for post groups, hidden groups, and the moderator group.
		$this->fixMembership();

		// Update the list of group moderators (i.e. people who can moderate this group)
		if (isset($this->moderator_ids)) {
			$this->moderator_ids = array_unique($this->moderator_ids);
			sort($this->moderator_ids);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}group_moderators
				WHERE id_group = {int:current_group}',
				[
					'current_group' => $this->id,
				],
			);

			foreach ($this->moderator_ids as $mod_id) {
				$inserts[] = [$this->id, $mod_id];
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}group_moderators',
				['id_group' => 'int', 'id_member' => 'int'],
				$inserts,
				['id_group', 'id_member'],
			);
		}

		// Update permissions of any groups that inherit from this group.
		if ($this->parent === self::NONE) {
			Permissions::updateChildPermissions($this->id);
		}

		// Did we make some post group changes?
		if ($this->min_posts > -1) {
			Logging::updateStats('postgroups');
		}

		// Rebuild the group cache.
		Config::updateModSettings([
			'settings_updated' => time(),
		]);

		// Log the edit.
		Logging::logAction('edited_group', ['group' => $this->name], 'admin');

		SecurityToken::create('admin-mmg');
	}

	/**
	 * Deletes this group.
	 *
	 * @return bool|string True for success, otherwise an identifier as to reason for failure
	 */
	public function delete(): bool|string
	{
		User::$me->isAllowedTo('manage_membergroups');
		User::$me->checkSession();
		SecurityToken::validate('admin-mmg');

		// Don't delete protected groups.
		if (!$this->can_delete) {
			return 'no_group_found';
		}

		// Make sure they don't try to delete a group attached to a paid subscription.
		$subscriptions = [];

		$request = Db::$db->query(
			'',
			'SELECT name
			FROM {db_prefix}subscriptions
			WHERE id_group = {int:this_group}
				OR FIND_IN_SET({int:this_group}, additional_groups) != 0
			ORDER BY name',
			[
				'this_group' => $this->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$subscriptions[] = $row['name'];
		}
		Db::$db->free_result($request);

		if (!empty($subscriptions)) {
			// Uh oh. But before we return, we need to update a language string because we want the names of the groups.
			Lang::load('ManageMembers');
			Lang::$txt['membergroups_cannot_delete_paid'] = sprintf(Lang::$txt['membergroups_cannot_delete_paid'], Lang::sentenceList($subscriptions));

			return 'group_cannot_delete_sub';
		}

		// Log the deletion.
		Logging::logAction('delete_group', ['group' => $this->name], 'admin');

		// Remove the group itself.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}membergroups
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		// Remove the permissions of the groups.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}group_moderators
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderator_groups
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		// Delete any outstanding requests.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_group_requests
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		// Update the primary groups of members.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}members
			SET id_group = {int:regular_group}
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
				'regular_group' => self::REGULAR,
			],
		);

		// Update any inherited groups (Lose inheritance).
		Db::$db->query(
			'',
			'UPDATE {db_prefix}membergroups
			SET id_parent = {int:uninherited}
			WHERE id_parent = {int:this_group}',
			[
				'this_group' => $this->id,
				'uninherited' => self::NONE,
			],
		);

		// Update the additional groups of members.
		$updates = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, additional_groups
			FROM {db_prefix}members
			WHERE FIND_IN_SET({int:this_group}, additional_groups) != 0',
			[
				'this_group' => $this->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$updates[$row['additional_groups']][] = $row['id_member'];
		}
		Db::$db->free_result($request);

		foreach ($updates as $additional_groups => $member_array) {
			User::updateMemberData($member_array, ['additional_groups' => implode(',', array_diff(explode(',', $additional_groups), [$this->id]))]);
		}

		// No boards can provide access to these groups anymore.
		$updates = [];

		$request = Db::$db->query(
			'',
			'SELECT id_board, member_groups
			FROM {db_prefix}boards
			WHERE FIND_IN_SET({int:this_group}, member_groups) != 0',
			[
				'this_group' => $this->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$updates[$row['member_groups']][] = $row['id_board'];
		}
		Db::$db->free_result($request);

		foreach ($updates as $member_groups => $board_array) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET member_groups = {string:member_groups}
				WHERE id_board IN ({array_int:board_lists})',
				[
					'board_lists' => $board_array,
					'member_groups' => implode(',', array_diff(explode(',', $member_groups), [$this->id])),
				],
			);
		}

		// Recalculate the post groups if this was one.
		if ($this->min_posts > -1) {
			Logging::updateStats('postgroups');
		}

		// Make a note of the fact that the cache may be wrong.
		$settings_update = ['settings_updated' => time()];

		// Have we deleted the spider group?
		if (isset(Config::$modSettings['spider_group']) && Config::$modSettings['spider_group'] == $this->id) {
			$settings_update['spider_group'] = 0;
		}

		Config::updateModSettings($settings_update);

		unset(self::$loaded[$this->id]);

		// It was a success.
		return true;
	}

	/**
	 * Loads the IDs of any boards that this group can moderate.
	 *
	 * Results are saved in $this->boards_can_moderate and also returned.
	 *
	 * @return array A copy of $this->boards_can_moderate.
	 */
	public function getBoardsCanModerate(): array
	{
		if (isset($this->boards_can_moderate)) {
			return $this->boards_can_moderate;
		}

		$request = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}moderator_groups
			WHERE id_group = {int:current_group}',
			[
				'current_group' => $this->id,
			],
		);
		$this->boards_can_moderate = array_values(Db::$db->fetch_all($request));
		Db::$db->free_result($request);

		return $this->boards_can_moderate;
	}

	/**
	 * Loads the IDs of the moderators of this group.
	 *
	 * Results are saved in $this->moderator_ids.
	 *
	 * @param bool $ignore_protected Whether to ignore the protected status of
	 *    protected groups.
	 */
	public function loadModerators(bool $ignore_protected = false): void
	{
		self::loadModeratorsBatch([$this->id], $ignore_protected);
	}

	/**
	 * Counts how many members are in this group.
	 *
	 * Results are saved in $this->num_members and also returned.
	 *
	 * @param bool $recount If true, force a recount.
	 * @return int Number of members in this group.
	 */
	public function countMembers(bool $recount = false): int
	{
		self::countMembersBatch([$this->id], $recount);

		return $this->num_members;
	}

	/**
	 * Loads the IDs of all the members of this group.
	 *
	 * Results are saved in $this->members and also returned.
	 *
	 * @return array IDs of members in this group.
	 */
	public function loadMembers(): array
	{
		if (isset($this->members)) {
			return $this->members;
		}

		$this->members = [];

		// Special case for the moderator group.
		if ($this->id === self::MOD) {
			// If we're in a board, only get the moderators for that board.
			if (isset(Board::$info)) {
				self::$loaded[self::MOD]->members = array_keys(Board::$info->moderators);
			}
			// Outside a board, get the moderators for all boards.
			else {
				$request = Db::$db->query(
					'',
					'SELECT DISTINCT id_member
					FROM {db_prefix}moderators',
					[],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					self::$loaded[self::MOD]->members[] = (int) $row['id_member'];
				}
				Db::$db->free_result($request);
			}
		}
		// Post-count based groups.
		elseif ($this->min_posts > -1) {
			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}members
				WHERE id_post_group = {int:group}',
				[
					'group' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->members[] = (int) $row['id_member'];
			}
			Db::$db->free_result($request);
		}
		// Regular groups.
		else {
			$this->loadModerators();

			if ($this->can_moderate) {
				$request = Db::$db->query(
					'',
					'SELECT id_member
					FROM {db_prefix}members
					WHERE id_group = {int:group}
						OR (
							additional_groups != {string:blank_string}
							AND FIND_IN_SET({int:group}, additional_groups) != 0
					)',
					[
						'group' => $this->id,
						'blank_string' => '',
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$this->members[] = (int) $row['id_member'];
				}
				Db::$db->free_result($request);
			} else {
				$request = Db::$db->query(
					'',
					'SELECT id_member
					FROM {db_prefix}members
					WHERE id_group = {int:group}',
					[
						'group' => $this->id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$this->members[] = (int) $row['id_member'];
				}
				Db::$db->free_result($request);
			}
		}

		return $this->members;
	}

	/**
	 * Adds members to this group.
	 *
	 * Requires the manage_membergroups permission.
	 * Function has protection against adding members to implicit groups.
	 * Non-admins are not able to add members to the admin group.
	 *
	 * @param int|array $members The IDs of one or more members.
	 * @param string $type Specifies whether the group is added as primary or as
	 *    an additional group.
	 *
	 *    Supported types:
	 *
	 * 	  only_primary     Assigns a group as primary group, but
	 *                     only if a member has not yet a primary group
	 *                     assigned, unless the member is already part of the
	 *                     group.
	 *
	 * 	  only_additional  Assigns a group to the additional groups,
	 *                     unless the member is already part of the group.
	 *
	 * 	  force_primary    Assigns a group as primary no matter what the
	 *                     previous primary group was.
	 *
	 * 	  auto             Assigns a group as primary if primary is still
	 *                     available. If not, assign it to the additional group.
	 *
	 * @param bool $perms_checked Whether we've already checked permissions.
	 * @param bool $ignore_protected Whether to ignore the protected status of
	 *    protected groups.
	 * @return bool Whether the operation was successful.
	 */
	public function addMembers(int|array $members, string $type = 'auto', bool $perms_checked = false, bool $ignore_protected = false): bool
	{
		// Show your licence, but only if it hasn't been done yet.
		if (!$perms_checked) {
			$this->loadModerators($ignore_protected);

			if (!$this->can_moderate) {
				User::$me->isAllowedTo(User::$me->allowedTo('manage_membergroups') ? 'admin_forum' : 'manage_membergroups');
			}
		}

		if (!in_array($type, ['auto', 'only_additional', 'only_primary', 'force_primary'])) {
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['add_members_to_group_invalid_type'], $type), E_USER_WARNING);
		}

		// Can this group be a primary group?
		$type = !$this->can_be_primary ? 'only_additional' : $type;

		// Requested only_additional, but the group can't be additional?
		if ($type == 'only_additional' && !$this->can_be_additional) {
			return false;
		}

		// Some groups just don't like explicitly having members.
		if (in_array($this->id, [self::GUEST, self::REGULAR, self::MOD])) {
			return false;
		}

		// Can't join a post-count based group.
		if ($this->min_posts != -1) {
			return false;
		}

		// Only admins can add admins...
		if ($this->id == self::ADMIN && !User::$me->allowedTo('admin_forum')) {
			return false;
		}

		// ... or assign protected groups!
		if (!$ignore_protected && $this->type == self::TYPE_PROTECTED && !User::$me->allowedTo('admin_forum')) {
			return false;
		}

		// Demand an admin password before adding new admins -- every time, no matter what.
		if ($this->id == self::ADMIN) {
			User::$me->validateSession('admin', true);
		}

		// Make sure all members are integers.
		$members = array_unique(array_map('intval', (array) $members));

		// There's nobody to add.
		if (empty($members)) {
			return false;
		}

		// Load the user info for the new members.
		$members = User::load($members, User::LOAD_BY_ID, 'minimal');

		if (empty($members)) {
			return false;
		}

		// Filter out members that are already in the group and figure out which
		// members get this as their new primary group and which get it as an
		// new additional group.
		$set_primary = [];
		$set_additional = [];

		foreach ($members as $key => $member) {
			// Forcing primary.
			if ($type === 'force_primary') {
				if (User::$loaded[$member]->group_id !== $this->id) {
					$set_primary[] = $member;
				}
			}
			// They're already in this group.
			elseif (in_array($this->id, User::$loaded[$member]->groups)) {
				continue;
			}
			// They have a different primary group.
			elseif (User::$loaded[$member]->group_id !== self::REGULAR) {
				// Skip if we only want to set their primary group.
				if ($type === 'only_primary') {
					continue;
				}

				// Skip if the group can't be additional.
				if (!$this->can_be_additional) {
					continue;
				}

				// Set this as an additional group.
				$set_additional[] = $member;
			}
			// This can only be an additional group.
			elseif ($type === 'only_additional') {
				$set_additional[] = $member;
			}
			// They have no primary group, so let's give them one.
			else {
				$set_primary[] = $member;
			}
		}

		// We need some special handling if we are forcing the primary.
		if ($type === 'force_primary') {
			if (empty($set_primary)) {
				return false;
			}

			Config::updateModSettings(['settings_updated' => time()]);

			$to_set = [];

			foreach ($set_primary as $member) {
				$new_additional_groups = array_diff(User::$loaded[$member]->groups, [$this->id, User::$loaded[$member]->post_group_id]);
				sort($new_additional_groups);

				$to_set[implode(',', $new_additional_groups)][] = $member->id;
			}

			foreach ($to_set as $new_additional_groups => $member_ids) {
				User::updateMemberData($member_ids, [
					'id_group' => $this->id,
					'additional_groups' => $new_additional_groups,
				]);
			}

			return true;
		}

		if (empty($set_primary) && empty($set_additional)) {
			return false;
		}

		Config::updateModSettings(['settings_updated' => time()]);

		if (!empty($set_primary)) {
			User::updateMemberData($set_primary, ['id_group' => $this->id]);
		}

		if (!empty($set_additional)) {
			$to_set = [];

			foreach ($set_additional as $member) {
				$new_additional_groups = array_unique(array_merge(User::$loaded[$member]->additional_groups, [$this->id]));
				sort($new_additional_groups);

				$to_set[implode(',', $new_additional_groups)][] = $member->id;
			}

			foreach ($to_set as $new_additional_groups => $member_ids) {
				User::updateMemberData($member_ids, ['additional_groups' => $new_additional_groups]);
			}
		}

		// For historical reasons, the hook expects an array rather than just the name string.
		$group_names = [$this->id => $this->name];

		IntegrationHook::call('integrate_add_members_to_group', [$members, $this->id, &$group_names]);

		// Update their postgroup statistics.
		Logging::updateStats('postgroups', $members);

		// Log the data.
		foreach ($members as $member) {
			Logging::logAction(
				'added_to_group',
				[
					'group' => $this->name,
					'member' => $member,
				],
				'admin',
			);
		}

		return true;
	}

	/**
	 * Remove one or more members from one or more membergroups.
	 *
	 * Requires the manage_membergroups permission.
	 * Function includes a protection against removing from implicit groups.
	 * Non-admins are not able to remove members from the admin group.
	 *
	 * @param int|array $members The ID of a member or an array of member IDs.
	 * @param bool $perms_checked Whether we've already checked permissions.
	 * @param bool $ignore_protected Whether to ignore the protected status of
	 *    protected groups.
	 * @param mixed The groups to remove the member(s) from. If null, the
	 *    specified members are stripped from all their membergroups.
	 * @return bool Whether the operation was successful.
	 */
	public function removeMembers(int|array $members, bool $perms_checked = false, bool $ignore_protected = false): bool
	{
		// You're getting nowhere without this permission, unless of course you are the group's moderator.
		if (!$perms_checked) {
			$this->loadModerators($ignore_protected);

			if (!$this->can_moderate) {
				User::$me->isAllowedTo(User::$me->allowedTo('manage_membergroups') ? 'admin_forum' : 'manage_membergroups');
			}
		}

		if (in_array($this->id, [self::GUEST, self::REGULAR, self::MOD])) {
			return false;
		}

		if ($this->min_posts != -1) {
			return false;
		}

		if ($this->id == self::ADMIN && !User::$me->allowedTo('admin_forum')) {
			return false;
		}

		if ($this->type == self::TYPE_PROTECTED && !User::$me->allowedTo('admin_forum') && !$ignore_protected) {
			return false;
		}

		// Only proven admins can remove admins.
		if ($this->id == self::ADMIN) {
			User::$me->validateSession('admin', true);
		}

		// Cleaning the input.
		$members = array_unique(array_map('intval', (array) $members));

		if (empty($members)) {
			return false;
		}

		// Load the IDs of the current members of this group.
		$this->loadMembers();

		// Before we get started, let's check we won't leave the admin group empty!
		if ($this->id === self::ADMIN && array_diff($this->members, $members) === []) {
			return false;
		}

		// Load the user info for the members being removed.
		$members = User::load($members, User::LOAD_BY_ID, 'minimal');

		// Figure out which members should have their primary group changed and
		// which should have their additional groups changed.
		$remove_primary = [];
		$remove_additional = [];

		foreach ($members as $member) {
			if (User::$loaded[$member]->group_id === $this->id) {
				$remove_primary[] = $member;
			}

			if (in_array($this->id, User::$loaded[$member]->additional_groups)) {
				$remove_additional[] = $member;
			}
		}

		if (empty($remove_primary) && empty($remove_additional)) {
			return false;
		}

		$members = array_unique(array_merge($remove_primary, $remove_additional));

		// First, reset those who have this as their primary group. This is the easy one.
		if (!empty($remove_primary)) {
			// Remove in database.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET id_group = {int:regular_member}
				WHERE id_group = {int:group}
					AND id_member IN ({array_int:member_list})',
				[
					'group' => $this->id,
					'member_list' => $remove_primary,
					'regular_member' => self::REGULAR,
				],
			);

			// Remove from current object.
			$this->members = array_diff($this->members, $remove_primary);

			// Log the change.
			foreach ($remove_primary as $member) {
				$log_inserts[] = ['group' => $this->name, 'member' => $member];
			}
		}

		// Now remove this group from the additional groups.
		if (!empty($remove_additional)) {
			foreach ($remove_additional as $member) {
				// Remove in database.
				Db::$db->query(
					'',
					'UPDATE {db_prefix}members
					SET additional_groups = {string:additional_groups}
					WHERE id_member = {int:member}',
					[
						'member' => $this->id,
						'additional_groups' => implode(',', array_diff(User::$loaded[$member]->additional_groups, [$this->id])),
					],
				);

				// Log the change.
				$log_inserts[] = ['group' => $this->name, 'member' => $member];
			}

			// Remove from current object.
			$this->members = array_diff($this->members, $remove_additional);
		}

		// Settings have been updated.
		Config::updateModSettings(['settings_updated' => time()]);

		// Their post groups may have changed now...
		Logging::updateStats('postgroups', $members);

		// Do the log.
		if (!empty($log_inserts) && !empty(Config::$modSettings['modlog_enabled'])) {
			foreach ($log_inserts as $extra) {
				Logging::logAction('removed_from_group', $extra, 'admin');
			}
		}

		// Mission successful.
		return true;
	}

	/**
	 * Ensures membership is correct for post groups, hidden groups, and the moderator group.
	 *
	 * Called by the save() method, but can also be called directly.
	 */
	public function fixMembership(): void
	{
		// Fix post-count based groups and moderator group.
		if ($this->min_posts > -1 || $this->id === self::MOD) {
			// Can't be primary groups.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET id_group = {int:regular_member}
				WHERE id_group = {int:current_group}',
				[
					'regular_member' => 0,
					'current_group' => $this->id,
				],
			);

			// Can't be additional groups.
			$updates = [];

			$request = Db::$db->query(
				'',
				'SELECT id_member, additional_groups
				FROM {db_prefix}members
				WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
				[
					'current_group' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$updates[$row['additional_groups']][] = $row['id_member'];
			}
			Db::$db->free_result($request);

			foreach ($updates as $additional_groups => $memberArray) {
				User::updateMemberData($memberArray, ['additional_groups' => implode(',', array_diff(explode(',', $additional_groups), [$this->id]))]);
			}

			// Post-count based groups can't be moderator groups, and the main moderator group already is one.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}moderator_groups
				WHERE id_group = {int:current_group}',
				[
					'current_group' => $this->id,
				],
			);
		}
		// A hidden group?.
		elseif ($this->hidden == self::INVISIBLE) {
			$updates = [];

			$request = Db::$db->query(
				'',
				'SELECT id_member, additional_groups
				FROM {db_prefix}members
				WHERE id_group = {int:current_group}
					AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
				[
					'current_group' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$updates[$row['additional_groups']][] = $row['id_member'];
			}
			Db::$db->free_result($request);

			foreach ($updates as $additional_groups => $memberArray) {
				$new_groups = (!empty($additional_groups) ? $additional_groups . ',' : '') . $this->id;

				User::updateMemberData($memberArray, ['additional_groups' => $new_groups]);
			}

			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET id_group = {int:regular_member}
				WHERE id_group = {int:current_group}',
				[
					'regular_member' => 0,
					'current_group' => $this->id,
				],
			);

			// Hidden groups can't moderate boards
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}moderator_groups
				WHERE id_group = {int:current_group}',
				[
					'current_group' => $this->id,
				],
			);
		}
	}

	/**
	 * Loads the IDs and names of groups that inherit permissions from this
	 * group.
	 *
	 * Results are saved in $this->children and also returned.
	 *
	 * @return array A copy of $this->children.
	 */
	public function getChildren(): array
	{
		if (isset($this->children)) {
			return $this->children;
		}

		$this->children = [];

		$selects = ['mg.id_group'];
		$where = ['mg.id_parent = {int:this_group}'];
		$order = ['mg.id_group'];
		$params = ['this_group' => $this->id];

		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_parent = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->children[(int) $row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		return $this->children;
	}

	/**
	 * Loads the permissions for this group.
	 *
	 * Results are saved in $this->permissions and also returned.
	 *
	 * @param int $profile Which permissions profile to get permissions for.
	 *    If set to 1 or higher, get permissions for that permissions profile.
	 *    If set to 0, get general permissions.
	 *    If null, get all permissions.
	 * @param bool $reload If true, force a reload from the database.
	 * @return array A copy of $this->permissions.
	 */
	public function loadPermissions(?int $profile = null, bool $reload = false): array
	{
		// General permissions.
		if (empty($profile)) {
			if (empty($this->permissions['general']) || $reload) {
				$request = Db::$db->query(
					'',
					'SELECT permission, add_deny
					FROM {db_prefix}permissions
					WHERE id_group = {int:this_group}',
					[
						'this_group' => $this->id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$this->permissions['general'][$row['permission']] = (int) $row['add_deny'];
				}
				Db::$db->free_result($request);
			}
		}

		// If profile is zero, we only wanted general permissions.
		if (isset($profile) && $profile === 0) {
			return $this->permissions;
		}

		// Don't reload unnecessarily.
		if (isset($profile, $this->permissions['board_profiles'][$profile])   && !$reload) {
			return $this->permissions;
		}

		// Have we already loaded some board permissions?
		if (!$reload && !empty($this->permissions['board_profiles'])) {
			$excluded_profiles = array_keys($this->permissions['board_profiles']);
		}

		// Get board permissions.
		$request = Db::$db->query(
			'',
			'SELECT id_profile, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:this_group}' . (isset($profile) ? '
				AND id_profile = {int:profile}' : '') . (isset($excluded_profiles) ? '
				AND id_profile NOT IN ({array_int:excluded_profiles})' : ''),
			[
				'this_group' => $this->id,
				'profile' => $profile ?? 0,
				'excluded_profiles' => $excluded_profiles ?? [0],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->permissions['board_profiles'][(int) $row['id_profile']][$row['permission']] = (int) $row['add_deny'];
		}
		Db::$db->free_result($request);

		return $this->permissions;
	}

	/**
	 * Counts the allowed and denied permissions for this group.
	 *
	 * Results are saved in $this->num_permissions and also returned.
	 *
	 * @param int $profile Which permissions profile to get permissions for.
	 *    If set to 1 or higher, get permissions for that permissions profile.
	 *    If set to 0, get general permissions.
	 *    If null, get all permissions.
	 * @return array A copy of $this->num_permissions.
	 */
	public function countPermissions(?int $profile = null): array
	{
		self::countPermissionsBatch([$this->id], $profile);

		return $this->num_permissions;
	}

	/**
	 * Updates the boards this group has access to.
	 *
	 * @param array $board_access
	 */
	public function updateBoardAccess(array $board_access): void
	{
		// This doesn't apply to administrators or moderators.
		if ($this->id === self::ADMIN || $this->id === self::MOD) {
			return;
		}

		// Nothing to change?
		if (empty($board_access)) {
			return;
		}

		// Reorganize the new access permissions for easier processing.
		$changed_boards = [
			'allow' => [],
			'deny' => [],
		];

		foreach ($board_access as $board_id => $access) {
			// The only supported access types are 'allow' and 'deny'.
			if (!isset($changed_boards[$access])) {
				continue;
			}

			$changed_boards[$access][] = (int) $board_id;
		}

		// This should never happen anyway, but a group can't be both allowed and denied.
		$changed_boards['allow'] = array_diff($changed_boards['allow'], $changed_boards['deny']);

		// Reset the group's existing access permssions.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions_view
			WHERE id_group = {int:this_group}',
			[
				'this_group' => $this->id,
			],
		);

		// This will hold the inserts for the group's new access permissions.
		$new_perms = [];

		// We're going to need all the boards, one way or another.
		Category::getTree();

		// Now loop through our changes and apply them.
		foreach ($changed_boards as $access => $board_ids) {
			$prop = $access == 'allow' ? 'member_groups' : 'deny_groups';

			foreach (Board::$loaded as $board) {
				if (in_array($board->id, $board_ids)) {
					$board->{$prop} = array_unique(array_merge($board->{$prop}, [$this->id]));
				} else {
					$board->{$prop} = array_diff($board->{$prop}, [$this->id]);
				}

				$board->save();
			}

			foreach ($board_ids as $board_id) {
				$new_perms[] = [$this->id, (int) $board_id, $access == 'allow' ? 0 : 1];
			}
		}

		if (!empty($new_perms)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}board_permissions_view',
				['id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'],
				$new_perms,
				['id_group', 'id_board', 'deny'],
			);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads groups by ID number and/or by custom query.
	 *
	 * @param array|int $ids The ID numbers of zero or more groups.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded groups.
	 */
	public static function load(array|int $ids = [], array $query_customizations = []): array
	{
		$loaded = [];

		$ids = array_unique(array_map('intval', (array) $ids));

		// The guest and regular member groups require special handling.
		$guest_and_reg = array_intersect([-1, 0], $ids);

		if (!empty($guest_and_reg)) {
			foreach ($guest_and_reg as $id) {
				$loaded[$id] = new self($id);
			}

			$ids = array_diff($ids, $guest_and_reg);

			if ($ids === []) {
				return $loaded;
			}
		}

		$selects = $query_customizations['selects'] ?? ['mg.*'];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [];
		$order = $query_customizations['order'] ?? [
			'min_posts',
			'CASE WHEN id_group < 4 THEN id_group ELSE 4 END',
			'group_name',
		];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [];

		if ($ids !== []) {
			$where[] = 'mg.id_group IN ({array_int:ids})';
			$params['ids'] = $ids;
		}

		foreach (self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$row['id_group'] = (int) $row['id_group'];

			if (isset(self::$loaded[$row['id_group']])) {
				self::$loaded[$row['id_group']]->set($row);
				$loaded[] = self::$loaded[$row['id_group']];
			} else {
				$loaded[] = new self($row['id_group'], $row);
			}
		}

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Convenience method to load specific sorts of groups.
	 *
	 * If given no arguments, will load all the normal groups (meaning, the ones
	 * that have an ID greater than zero, are not post-count based, and are not
	 * the special moderators group).
	 *
	 * @param int $include One of this class's LOAD_* constants.
	 * @param array $exclude IDs of groups to exclude.
	 * @return array Instances of this class for the loaded groups.
	 */
	public static function loadSimple(int $include = self::LOAD_NORMAL, array $exclude = [self::GUEST, self::REGULAR, self::MOD]): array
	{
		$loaded = [];

		// This is the typical sort order.
		$query_customizations = [
			'order' => [
				'min_posts',
				'CASE WHEN id_group < 4 THEN id_group ELSE 4 END',
				'group_name',
			],
		];

		// Are we excluding post-count based groups?
		if (!($include & self::LOAD_POST)) {
			$query_customizations['where'][] = 'min_posts = -1';
		}

		// Are we excluding normal groups?
		if (!($include & self::LOAD_NORMAL)) {
			$query_customizations['where'][] = 'min_posts > -1';
		}

		// If we are including normal groups, do we want guests and regular members?
		if ($include & self::LOAD_NORMAL) {
			// Do we want the guest group?
			if (!in_array(self::GUEST, $exclude)) {
				$loaded = array_merge($loaded, self::load(-1));
			}

			// Do we want the regular members group?
			if (!in_array(self::REGULAR, $exclude)) {
				$loaded = array_merge($loaded, self::load(0));
			}
		}

		// Finally, exclude any groups we don't want.
		foreach ($exclude as $id) {
			if (!is_int($id) || $id <= 0) {
				continue;
			}

			$query_customizations['where'][] = 'id_group != ' . $id;
		}

		$loaded = array_merge($loaded, self::load([], $query_customizations));

		// If we loaded all the groups, populate the $children properties now for efficiency.
		if ($include == self::LOAD_BOTH && ($exclude == [] || $exclude == [self::MOD])) {
			foreach (self::$loaded as $group) {
				if (!isset(self::$loaded[$group->parent])) {
					continue;
				}

				self::$loaded[$group->parent]->children[$group->id] = $group->name;
				ksort(self::$loaded[$group->parent]->children);
			}
		}

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Loads groups that the current user can assign people to.
	 *
	 * @return array Instances of this class for the loaded groups.
	 */
	public static function loadAssignable(): array
	{
		$loaded = [
			new self(0),
		];

		$query_customizations = [
			'where' => [
				'id_group != 3',
				'min_posts = -1',
				'id_group NOT IN ({array_int:unassignable})',
			],
			'order' => [
				'min_posts',
				'CASE WHEN id_group < 4 THEN id_group ELSE 4 END',
				'group_name',
			],
			'params' => [
				'unassignable' => self::getUnassignable(),
			],
		];

		$loaded = array_merge($loaded, self::load([], $query_customizations));

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Like $this->loadModerators(), except that this is more efficient when
	 * working on a batch of groups.
	 *
	 * Groups that have not already been loaded will be skipped.
	 *
	 * Results are saved in $this->moderator_ids for each group.
	 *
	 * @param array $group_ids IDs of some groups.
	 * @param bool $ignore_protected Whether to ignore the protected status of
	 *    protected groups.
	 */
	public static function loadModeratorsBatch(array $group_ids, bool $ignore_protected = false): void
	{
		$group_ids = array_intersect(array_filter(array_unique(array_map('intval', $group_ids))), array_keys(self::$loaded));
		$mod_ids = [];

		foreach ($group_ids as $key => $group_id) {
			// Avoid unnecessary repetition.
			if (isset(self::$loaded[$group_id]->moderator_ids)) {
				unset($group_ids[$key]);
			}
		}

		if (empty($group_ids)) {
			return;
		}

		foreach ($group_ids as $group_id) {
			self::$loaded[$group_id]->moderator_ids = [];

			// We'll check below whether the current user is explicitly designated
			// as a moderator for this group. But even if not, the current user
			// may still be able moderate this group if:
			// 1. the current user has the manage_membergroups permission, and
			// 2. either:
			//    a. this is not a protected group,
			//    b. we are ignoring protected status, or
			//    c. the current user is an admin.
			self::$loaded[$group_id]->can_moderate = User::$me->allowedTo('manage_membergroups') && ((self::$loaded[$group_id]->type ?? self::TYPE_PRIVATE) != self::TYPE_PROTECTED || $ignore_protected || User::$me->allowedTo('admin_forum'));
		}

		$request = Db::$db->query(
			'',
			'SELECT id_group, id_member
			FROM {db_prefix}group_moderators
			WHERE id_group IN ({array_int:groups})',
			[
				'groups' => $group_ids,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row = array_map('intval', $row);

			$group = self::$loaded[$row['id_group']];
			$group->moderator_ids[] = $row['id_member'];
			$group->can_moderate |= in_array(User::$me->id, $group->moderator_ids);

			$mod_ids[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		User::load($mod_ids, User::LOAD_BY_ID, 'minimal');
	}

	/**
	 * Like $this->countMembers(), except that this is more efficient when
	 * working on a batch of groups.
	 *
	 * Groups that have not already been loaded will be skipped.
	 *
	 * Results are saved in $this->num_members for each group and also returned.
	 *
	 * @param array $group_ids IDs of the groups to count.
	 * @param bool $recount If true, force a recount.
	 * @return array Numbers of members in each group.
	 */
	public static function countMembersBatch(array $group_ids, bool $recount = false): array
	{
		$counts = [];

		$post_groups = [];
		$regular_groups = [];
		$moderator_group = null;

		$group_ids = array_intersect(array_unique(array_map('intval', $group_ids)), array_keys(self::$loaded));

		foreach ($group_ids as $key => $group_id) {
			// Can't count guests.
			if ($group_id <= self::GUEST) {
				unset($group_ids[$key]);

				continue;
			}

			// Avoid unnecessary repetition.
			if (!$recount && isset(self::$loaded[$group_id]->num_members)) {
				$counts[$group_id] = self::$loaded[$group_id]->num_members;
				unset($group_ids[$key]);

				continue;
			}

			self::$loaded[$group_id]->num_members = 0;

			if ($group_id === self::MOD) {
				$moderator_group = $group_id;
			} elseif (self::$loaded[$group_id]->min_posts > -1) {
				$post_groups[] = $group_id;
			} else {
				$regular_groups[] = $group_id;
			}
		}

		if (empty($post_groups) && empty($regular_groups) && empty($moderator_group)) {
			return $counts;
		}

		// Counting moderators is tricky.
		if (!empty($moderator_group)) {
			// If we're in a board, only count the moderators for that board.
			if (isset(Board::$info)) {
				self::$loaded[self::MOD]->num_members = count(Board::$info->moderators);
			}
			// Outside a board, count the moderators for all boards.
			else {
				$request = Db::$db->query(
					'',
					'SELECT COUNT(DISTINCT id_member)
					FROM {db_prefix}moderators
					LIMIT 1',
					[],
				);
				list(self::$loaded[self::MOD]->num_members) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}
		}

		// Post-count based groups.
		if (!empty($post_groups)) {
			$request = Db::$db->query(
				'',
				'SELECT id_post_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_post_group IN ({array_int:group_list})
				GROUP BY id_post_group',
				[
					'group_list' => $post_groups,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				self::$loaded[(int) $row['id_post_group']]->num_members += (int) $row['num_members'];
			}
			Db::$db->free_result($request);
		}

		// Regular groups.
		if (!empty($regular_groups)) {
			$request = Db::$db->query(
				'',
				'SELECT id_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_group IN ({array_int:group_list})
				GROUP BY id_group',
				[
					'group_list' => $regular_groups,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				self::$loaded[(int) $row['id_group']]->num_members += (int) $row['num_members'];
			}
			Db::$db->free_result($request);

			// Count in additional groups if current user can moderate the group.
			self::loadModeratorsBatch($regular_groups);

			$groups_can_moderate = [];

			foreach ($regular_groups as $group_id) {
				if (self::$loaded[$group_id]->can_moderate) {
					$groups_can_moderate[] = $group_id;
				}
			}

			if (!empty($groups_can_moderate)) {
				$request = Db::$db->query(
					'',
					'SELECT mg.id_group, COUNT(*) AS num_members
					FROM {db_prefix}membergroups AS mg
						INNER JOIN {db_prefix}members AS mem ON (
							mem.additional_groups != {string:blank_string}
							AND mem.id_group != mg.id_group
							AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0
						)
					WHERE mg.id_group IN ({array_int:group_list})
					GROUP BY mg.id_group',
					[
						'group_list' => $groups_can_moderate,
						'blank_string' => '',
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					self::$loaded[(int) $row['id_group']]->num_members += (int) $row['num_members'];
				}
				Db::$db->free_result($request);
			}
			// If user can't moderate the group, but has it as an additional group, add 1.
			else {
				foreach (array_intersect($regular_groups, User::$me->additional_groups) as $id) {
					self::$loaded[$id]->num_members++;
				}
			}
		}

		foreach ($group_ids as $group_id) {
			$counts[$group_id] = self::$loaded[$group_id]->num_members;
		}

		return $counts;
	}

	/**
	 * Like $this->loadPermissions(), except that this is more efficient when
	 * working on a batch of groups.
	 *
	 * Groups that have not already been loaded will be skipped.
	 *
	 * Results are saved in $this->permissions for each group and also returned.
	 *
	 * @param array $group_ids IDs of the groups to get permissions for.
	 * @param int $profile Which permissions profile to get permissions for.
	 *    If set to 1 or higher, get permissions for that permissions profile.
	 *    If set to 0, get general permissions only.
	 *    If null, get all permissions.
	 * @param bool $reload If true, force a reload from the database.
	 * @return array Copies of $this->permissions for all the groups.
	 */
	public static function loadPermissionsBatch(array $group_ids, ?int $profile = null, bool $reload = false): array
	{
		$get_general = [];
		$get_board = [];

		$group_ids = array_intersect(array_unique(array_map('intval', $group_ids)), array_keys(self::$loaded));

		// Figure out which groups we need to get info for.
		foreach ($group_ids as $key => $group_id) {
			// Profile is 0 or null and general perms haven't been loaded or should be reloaded.
			if (empty($profile) && (empty(self::$loaded[$group_id]->permissions['general']) || $reload)) {
				$get_general[] = $group_id;
			}

			// Profile is null, or it's not 0 and either hasn't been loaded or should be reloaded.
			if (!isset($profile) || (!empty($profile) && (!isset(self::$loaded[$group_id]->permissions['board_profiles'][$profile]) || $reload))) {
				$get_board[] = $group_id;
			}
		}

		// General permissions.
		if (!empty($get_general)) {
			$request = Db::$db->query(
				'',
				'SELECT id_group, permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:groups})',
				[
					'groups' => $get_general,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				self::$loaded[(int) $row['id_group']]->permissions['general'][$row['permission']] = (int) $row['add_deny'];
			}
			Db::$db->free_result($request);
		}

		// Board permissions.
		if (!empty($get_board)) {
			// Get board permissions.
			$request = Db::$db->query(
				'',
				'SELECT id_profile, id_group, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:groups})' . (isset($profile) ? '
					AND id_profile = {int:profile}' : ''),
				[
					'groups' => $get_board,
					'profile' => $profile ?? 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$row['id_profile'] = (int) $row['id_profile'];
				$row['id_group'] = (int) $row['id_group'];
				$row['add_deny'] = (int) $row['add_deny'];

				// If we're loading all profiles, but not reloading, don't overwrite existing data.
				if (!isset($profile) && !$reload && isset(self::$loaded[$row['id_group']]->permissions['board_profiles'][$row['id_profile']])) {
					continue;
				}

				self::$loaded[$row['id_group']]->permissions['board_profiles'][$row['id_profile']][$row['permission']] = $row['add_deny'];
			}
			Db::$db->free_result($request);
		}

		$all_loaded_permissions = [];

		foreach ($group_ids as $group_id) {
			$all_loaded_permissions[$group_id] = self::$loaded[$group_id]->permissions;
		}

		return $all_loaded_permissions;
	}

	/**
	 * Like $this->countPermissions(), except that this is more efficient when
	 * working on a batch of groups.
	 *
	 * Groups that have not already been loaded will be skipped.
	 *
	 * Results are saved in $this->num_permissions for each group and also
	 * returned.
	 *
	 * @param array $group_ids IDs of the groups to count permissions for.
	 * @param int $profile Which permissions profile to count permissions for.
	 *    If set to 1 or higher, count permissions for that permissions profile.
	 *    If set to 0, count general permissions only.
	 *    If null, count general permissions and the default profile.
	 * @return array Copies of $this->num_permissions for all the groups.
	 */
	public static function countPermissionsBatch(array $group_ids, ?int $profile = null): array
	{
		if (!isset(Permissions::$hidden)) {
			Permissions::buildHidden();
		}

		// If null or 0, we want general permissions.
		if (empty($profile)) {
			$request = Db::$db->query(
				'',
				'SELECT id_group, COUNT(*) AS num_permissions, add_deny
				FROM {db_prefix}permissions
				' . (empty(Permissions::$hidden) ? '' : ' WHERE permission NOT IN ({array_string:hidden_permissions})') . '
				GROUP BY id_group, add_deny',
				[
					'hidden_permissions' => Permissions::$hidden,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$row['id_group'] = (int) $row['id_group'];

				if (!isset(self::$loaded[$row['id_group']])) {
					continue;
				}

				if (!empty($row['add_deny']) || $row['id_group'] != self::GUEST) {
					self::$loaded[$row['id_group']]->num_permissions[empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
				}
			}
			Db::$db->free_result($request);
		}

		// For board permissions, null means the same as default.
		if ($profile === null) {
			$profile = Permissions::PROFILE_DEFAULT;
		}

		if (!empty($profile)) {
			$request = Db::$db->query(
				'',
				'SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_profile = {int:current_profile}
				GROUP BY id_profile, id_group, add_deny',
				[
					'current_profile' => $profile,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$row['id_group'] = (int) $row['id_group'];

				if (!isset(self::$loaded[$row['id_group']])) {
					continue;
				}

				if (!empty($row['add_deny']) || $row['id_group'] != self::GUEST) {
					self::$loaded[$row['id_group']]->num_permissions[empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
				}
			}
			Db::$db->free_result($request);
		}

		// A few overrides.
		if (isset(self::$loaded[self::GUEST])) {
			self::$loaded[self::GUEST]->num_permissions['denied'] = '(' . Lang::$txt['permissions_none'] . ')';
		}

		if (isset(self::$loaded[self::ADMIN])) {
			self::$loaded[self::ADMIN]->num_permissions['allowed'] = '(' . Lang::$txt['permissions_all'] . ')';
			self::$loaded[self::ADMIN]->num_permissions['denied'] = '(' . Lang::$txt['permissions_none'] . ')';
		}

		$all_counted_permissions = [];

		foreach ($group_ids as $group_id) {
			$all_counted_permissions[$group_id] = self::$loaded[$group_id]->num_permissions;
		}

		return $all_counted_permissions;
	}

	/**
	 * Returns the IDs of all post-count based groups.
	 *
	 * @return array IDs of all post-count based groups
	 */
	public static function getPostGroups(): array
	{
		if (isset(self::$post_groups)) {
			return self::$post_groups;
		}

		self::$post_groups = [];

		// First, check any groups we have already loaded.
		foreach (self::$loaded as $group) {
			if ($group->min_posts !== -1) {
				self::$post_groups[$group->id] = $group->min_posts;
			}
		}

		// Now query the database to find any that haven't been loaded.
		$request = Db::$db->query(
			'',
			'SELECT id_group, min_posts
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({array_int:known_post_groups})
				AND min_posts != {int:min_posts}',
			[
				'min_posts' => -1,
				'known_post_groups' => !empty(self::$post_groups) ? self::$post_groups : [self::NONE],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$post_groups[(int) $row['id_group']] = (int) $row['min_posts'];
		}
		Db::$db->free_result($request);

		arsort(self::$post_groups);

		return self::$post_groups;
	}

	/**
	 * Populates self::$unassignable with the IDs of any groups that the
	 * current user cannot assign.
	 *
	 * @return array A copy of self::$unassignable
	 */
	public static function getUnassignable(): array
	{
		// No need to do this twice.
		if (isset(self::$unassignable)) {
			return self::$unassignable;
		}

		// Admins can assign any group they like, except the guest group and post-count based groups.
		if (User::$me->allowedTo('admin_forum')) {
			self::$unassignable = [self::GUEST];

			$request = Db::$db->query(
				'',
				'SELECT id_group
				FROM {db_prefix}membergroups
				WHERE min_posts > -1',
				[],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				self::$unassignable[] = (int) $row['id_group'];
			}
			Db::$db->free_result($request);

			return self::$unassignable;
		}

		// No one can assign the guest group, and only admins can assign the admin group.
		self::$unassignable = [self::GUEST, self::ADMIN];

		// Find any other groups that are designated as protected.
		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type IN ({array_int:is_protected})
				OR min_posts > -1',
			[
				'is_protected' => !User::$me->allowedTo('manage_membergroups') ? [self::REGULAR, self::ADMIN] : [self::ADMIN],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$unassignable[] = (int) $row['id_group'];
		}
		Db::$db->free_result($request);

		// Prevent privilege escalation.
		$protected_permissions = [];

		foreach (['admin_forum', 'manage_membergroups', 'manage_permissions'] as $permission) {
			if (!User::$me->allowedTo($permission)) {
				$protected_permissions[] = $permission;
			}
		}

		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}permissions
			WHERE permission IN ({array_string:protected})
				AND add_deny = {int:add}',
			[
				'protected' => $protected_permissions,
				'add' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$unassignable[] = (int) $row['id_group'];
		}
		Db::$db->free_result($request);

		self::$unassignable = array_unique(self::$unassignable);

		return self::$unassignable;
	}

	/**
	 * Retrieve a list of (visible) membergroups used by the cache.
	 *
	 * @return array An array of information about the cached value.
	 */
	public static function getCachedList()
	{
		$groupCache = [];
		$group = [];

		$selects = ['mg.*'];
		$joins = [];
		$where = [
			'min_posts = {int:min_posts}',
			'hidden = {int:not_hidden}',
			'id_group != {int:mod_group}',
		];
		$params = [
			'min_posts' => -1,
			'not_hidden' => self::VISIBLE,
			'mod_group' => self::MOD,
		];

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$group[$row['id_group']] = $row;

			$groupCache[$row['id_group']] = '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
		}

		IntegrationHook::call('integrate_getMembergroupList', [&$groupCache, $group]);

		return [
			'data' => $groupCache,
			'expires' => time() + 3600,
			'refresh_eval' => 'return \\SMF\\Config::$modSettings[\'settings_updated\'] > ' . time() . ';',
		];
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Returns whether the given group is a post-count based group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group is a post-count based group.
	 */
	protected static function isPostGroup(object $group): bool
	{
		return $group->min_posts > -1;
	}

	/**
	 * Returns whether the given group is a protected group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group is a protected group.
	 */
	protected static function isProtected(object $group): bool
	{
		return $group->type === self::TYPE_PROTECTED;
	}

	/**
	 * Returns whether the given group moderates any boards.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group moderates any boards.
	 */
	protected static function isModeratorGroup(object $group): bool
	{
		return count($group->getBoardsCanModerate()) > 0;
	}

	/**
	 * Returns whether the given group can be assigned to a member.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can be assigned to a member.
	 */
	protected static function isAssignable(object $group): bool
	{
		return $group->min_posts === -1 && $group->can_moderate && $group->id > self::GUEST;
	}

	/**
	 * Returns whether the given group can be changed to a post-count based
	 * group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can changed to a post-count based group.
	 */
	protected static function canBePostGroup(object $group): bool
	{
		return $group->id >= self::NEWBIE;
	}

	/**
	 * Returns whether the given group can be changed to a protected group.
	 *
	 * The answer is always no unless the current user is an admin.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can become a protected group.
	 */
	protected static function canBeProtected(object $group): bool
	{
		return User::$me->allowedTo('admin_forum');
	}

	/**
	 * Returns whether the given group can be a primary group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can become a primary group.
	 */
	protected static function canBePrimary(object $group): bool
	{
		return $group->id >= self::ADMIN && $group->min_posts === -1 && $group->hidden !== self::INVISIBLE;
	}

	/**
	 * Returns whether the given group can be an additional group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can become an additional group.
	 */
	protected static function canBeAdditional(object $group): bool
	{
		return $group->id > self::REGULAR && $group->min_posts === -1;
	}

	/**
	 * Returns whether the given group can be deleted.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can be deleted.
	 */
	protected static function canDelete(object $group): bool
	{
		if ($group->type === self::TYPE_PROTECTED && !User::$me->allowedTo('admin_forum')) {
			return false;
		}

		return $group->id == self::GLOBAL_MOD || $group->id > self::NEWBIE;
	}

	/**
	 * Returns whether the type of the given group can be changed.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can be deleted.
	 */
	protected static function canChangeType(object $group): bool
	{
		return $group->id > self::NEWBIE;
	}

	/**
	 * Returns whether the permissions of the given group can be changed.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can be deleted.
	 */
	protected static function canChangePermissions(object $group): bool
	{
		return $group->id !== self::ADMIN;
	}

	/**
	 * Returns whether people can search for members of this group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can be deleted.
	 */
	protected static function canSeach(object $group): bool
	{
		return $group->id > self::REGULAR && $group->id !== self::MOD;
	}

	/**
	 * Returns whether users can choose to leave the given group.
	 *
	 * Specifically, users can't leave a private or protected group.
	 * For example, if the admin added you to a group for miscreants with
	 * reduced privileges, you can't just decide to leave it.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group can become an additional group.
	 */
	protected static function canLeave(object $group): bool
	{
		return $group->id !== self::ADMIN && !in_array($group->id, self::getUnassignable());
	}

	/**
	 * Returns the icons formatted for display.
	 *
	 * @param object $group An instance of this class.
	 * @return bool Whether the group is a post-count based group.
	 */
	protected static function formatIcons(object $group): string
	{
		return !empty($group->icon_count) && !empty($group->icon_image) && isset(Theme::$current->settings) ? str_repeat('<img src="' . Theme::$current->settings['images_url'] . '/membericons/' . $group->icon_image . '" alt="*">', $group->icon_count) : '';
	}

	/**
	 * Returns the Lang::$helptxt key for the given group.
	 *
	 * @param object $group An instance of this class.
	 * @return bool The Lang::$helptxt key for this group.
	 */
	protected static function getHelpTxt(object $group): bool
	{
		switch ($group->id) {
			case self::GUEST:
				$help = 'membergroup_guests';
				break;

			case self::REGULAR:
				$help = 'membergroup_regular_members';
				break;

			case self::ADMIN:
				$help = 'membergroup_administrator';
				break;

			case self::MOD:
				$help = 'membergroup_moderator';
				break;

			default:
				$help = '';
				break;
		}

		return $help;
	}

	/**
	 * Returns the URL for an overview of the given group.
	 *
	 * @param object $group An instance of this class.
	 * @return string The URL for the group.
	 */
	protected static function getHref(object $group): string
	{
		if (User::$me->allowedTo('access_mod_center') && User::$me->allowedTo('manage_membergroups')) {
			$action_url = '?action=moderate;area=viewgroups';
		} elseif (User::$me->allowedTo('view_mlist')) {
			$action_url = '?action=groups';
		}

		return $group->id === self::GUEST || empty($action_url) ? '' : Config::$scripturl . $action_url . ';sa=members;group=' . $group->id;
	}

	/**
	 * Returns an HTML link to an overview of the given group.
	 *
	 * @param object $group An instance of this class.
	 * @return string The HTML link.
	 */
	protected static function getLink(object $group): string
	{
		$href = $group->getHref();

		if ($href === '') {
			return '';
		}

		if (!isset($group->num_members)) {
			$group->countMembers();
		}

		return '<a href="' . $href . '">' . $group->num_members . '</a>';
	}

	/**
	 * Generator that runs queries about group data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}membergroups AS mg' is always part of the query.
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param array $group Zero or more conditions for the GROUP BY clause.
	 *    If this is left empty, no GROUP BY clause will be used.
	 * @param int|string $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0)
	{
		$request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}membergroups AS mg' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (!empty($limit) ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			yield $row;
		}
		Db::$db->free_result($request);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Group::exportStatic')) {
	Group::exportStatic();
}

?>