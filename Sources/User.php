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

use SMF\Actions\Admin\ACP;
use SMF\Actions\Admin\Bans;
use SMF\Actions\Login2;
use SMF\Actions\Logout;
use SMF\Actions\Moderation\ReportedContent;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\PersonalMessage\PM;

/**
 * Represents a user, including both guests and registered members.
 *
 * All loaded users are available via User::$loaded[$id], where $id is the ID
 * number of a user.
 *
 * The current user is available as User::$me. For example, if you need to know
 * the current user's ID number, use User::$me->id.
 *
 * For the convenience of theme creators, User::$me is also available as
 * Utils::$context['user'], and its properties can be accessed as if they were
 * array elements. This means that Utils::$context['user']['id'] is
 * interchangable with User::$me->id.
 *
 * The data previously available in the deprecated global $user_profile array
 * is now available as User::$profiles. For example, where old code might have
 * used $user_profile[$id_member]['last_login'], the same information is now
 * available as User::profiles[$id_member]['last_login'].
 *
 * The data previously available in the deprecated $memberContext array is now
 * available via the $formatted property of a User object. For example, where
 * old code might have used $memberContext[$id_member], the same information is
 * now available via User::$loaded[$id_member]->formatted. Also note that, in
 * the same way that loadMemberContext($id_member) had to be called in order to
 * populate $memberContext[$id_member], User::$loaded[$id_member]->format() must
 * be called in order to populate User::$loaded[$id_member]->formatted.
 *
 * To faciliate backward compatibility, the deprecated global $user_info array
 * is still available, but it is simply a reference to User::$me.
 *
 * Similarly, the deprecated global $user_settings array is still available, but
 * it is simply a reference to User::$profiles[User::$me->id].
 *
 * Similarly, the deprecated global $cur_profile array is still available, but
 * it is simply a reference to User::$profiles[$id], where $id is the ID of the
 * user whose profile is being viewed.
 *
 * NOTE: It is STRONGLY RECOMMENDED that new and updated code use User::$me,
 * User::$loaded, and User::$profiles directly, rather than using any of the
 * deprecated global variables. A future version of SMF will remove backward
 * compatibility support for these deprecated globals.
 */
class User implements \ArrayAccess
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
			'buildQueryBoard' => 'build_query_board',
			'setAvatarData' => 'set_avatar_data',
			'updateMemberData' => 'updateMemberData',
			'getTimezone' => 'getUserTimezone',
			'delete' => 'deleteMembers',
			'validatePassword' => 'validatePassword',
			'validateUsername' => 'validateUsername',
			'isReservedName' => 'isReservedName',
			'isBannedEmail' => 'isBannedEmail',
			'find' => 'findMembers',
			'membersAllowedTo' => 'membersAllowedTo',
			'groupsAllowedTo' => 'groupsAllowedTo',
			'getGroupsWithPermissions' => 'getGroupsWithPermissions',
			'generateValidationCode' => 'generateValidationCode',
			'logSpider' => 'logSpider',
			'loadMemberData' => 'loadMemberData',
			'loadUserSettings' => 'loadUserSettings',
			'loadMyPermissions' => 'loadPermissions',
			'loadMemberContext' => 'loadMemberContext',
			'is_not_guest' => 'is_not_guest',
			'is_not_banned' => 'is_not_banned',
			'banPermissions' => 'banPermissions',
			'log_ban' => 'log_ban',
			'sessionValidate' => 'validateSession',
			'sessionCheck' => 'checkSession',
			'hasPermission' => 'allowedTo',
			'mustHavePermission' => 'isAllowedTo',
			'hasPermissionInBoards' => 'boardsAllowedTo',
		],
		'prop_names' => [
			'profiles' => 'user_profile',
			'settings' => 'user_settings',
			'info' => 'user_info',
			'sc' => 'sc',
			'memberContext' => 'memberContext',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	public const LOAD_BY_ID = 0;
	public const LOAD_BY_NAME = 1;
	public const LOAD_BY_EMAIL = 2;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The user's ID number.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * The user's member_name.
	 */
	public string $username;

	/**
	 * @var string
	 *
	 * The user's real_name, a.k.a display name.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * The user's email address.
	 */
	public string $email;

	/**
	 * @var string
	 *
	 * The user's password.
	 */
	public string $passwd;

	/**
	 * @var string
	 *
	 * The user's password salt.
	 */
	public string $password_salt;

	/**
	 * @var string
	 *
	 * The user's two factor authentication secret.
	 */
	public string $tfa_secret;

	/**
	 * @var string
	 *
	 * The user's two factor authentication backup code.
	 */
	public string $tfa_backup;

	/**
	 * @var string
	 *
	 * The user's secret question (used for password resets).
	 */
	public string $secret_question;

	/**
	 * @var string
	 *
	 * Answer to the user's secret question (used for password resets).
	 */
	public string $secret_answer;

	/**
	 * @var string
	 *
	 * The user's validation code (used for password resets).
	 */
	public string $validation_code;

	/**
	 * @var int
	 *
	 * ID of this user's primary group.
	 */
	public int $group_id;

	/**
	 * @var int
	 *
	 * ID of this user's post-count based group.
	 */
	public int $post_group_id;

	/**
	 * @var array
	 *
	 * IDs of any additional groups this user belongs to.
	 */
	public array $additional_groups = [];

	/**
	 * @var array
	 *
	 * IDs of all the groups this user belongs to.
	 */
	public array $groups = [];

	/**
	 * @var bool
	 *
	 * If true, probably a search engine spider.
	 */
	public bool $possibly_robot;

	/**
	 * @var bool
	 *
	 * Whether this user is a guest.
	 */
	public bool $is_guest;

	/**
	 * @var bool
	 *
	 * Whether this user is an admin.
	 */
	public bool $is_admin;

	/**
	 * @var bool
	 *
	 * Whether this user is a moderator on the current board.
	 */
	public bool $is_mod;

	/**
	 * @var int
	 *
	 * Activation status of this user's account.
	 */
	public int $is_activated;

	/**
	 * @var bool
	 *
	 * Whether this user has been banned.
	 */
	public bool $is_banned;

	/**
	 * @var bool
	 *
	 * Whether this user is currently browsing the forum.
	 */
	public bool $is_online;

	/**
	 * @var bool
	 *
	 * Whether to show that this user is currently browsing the forum.
	 */
	public bool $show_online;

	/**
	 * @var string
	 *
	 * JSON data about the URL this user is currently viewing.
	 */
	public string $url;

	/**
	 * @var int
	 *
	 * Unix timestamp of the last time the user logged in.
	 */
	public int $last_login;

	/**
	 * @var int
	 *
	 * ID of the latest message the last time they visited.
	 * All messages with higher IDs than this are new to this user.
	 */
	public int $id_msg_last_visit;

	/**
	 * @var int
	 *
	 * Total amount of time the user has been logged in, measured in seconds.
	 */
	public int $total_time_logged_in = 0;

	/**
	 * @var int
	 *
	 * Unix timestamp when this user registered.
	 */
	public int $date_registered;

	/**
	 * @var string
	 *
	 * The user's current IP address.
	 */
	public string $ip;

	/**
	 * @var string
	 *
	 * The user's previous known IP address, if any.
	 */
	public string $ip2;

	/**
	 * @var string
	 *
	 * The user's preferred language.
	 */
	public string $language;

	/**
	 * @var string
	 *
	 * The user's preferred time format.
	 */
	public string $time_format;

	/**
	 * @var string
	 *
	 * The user's time zone.
	 */
	public string $timezone;

	/**
	 * @var int
	 *
	 * The UTC offset of the user's time zone.
	 */
	public int $time_offset;

	/**
	 * @var int
	 *
	 * Number of posts the user has made.
	 */
	public int $posts;

	/**
	 * @var string
	 *
	 * The user's title.
	 */
	public string $title;

	/**
	 * @var string
	 *
	 * The user's signature.
	 */
	public string $signature;

	/**
	 * @var string
	 *
	 * The user's personal text blurb.
	 */
	public string $personal_text;

	/**
	 * @var string
	 *
	 * The user's birthdate.
	 */
	public string $birthdate;

	/**
	 * @var array
	 *
	 * Info about the user's website.
	 */
	public array $website = [
		'url' => null,
		'title' => null,
	];

	/**
	 * @var int
	 *
	 * The user's preferred theme.
	 */
	public int $theme;

	/**
	 * @var array
	 *
	 * The user's theme options.
	 */
	public array $options = [];

	/**
	 * @var string
	 *
	 * The user's preferred smiley set.
	 */
	public string $smiley_set;

	/**
	 * @var array
	 *
	 * IDs of users on this user's buddy list.
	 */
	public array $buddies = [];

	/**
	 * @var array
	 *
	 * IDs of users that this user is ignoring.
	 */
	public array $ignoreusers = [];

	/**
	 * @var int
	 *
	 * This user's preference about who to receive personal messages from.
	 */
	public int $pm_receive_from;

	/**
	 * @var int
	 *
	 * This user's display preferences for personal messages.
	 */
	public int $pm_prefs;

	/**
	 * @var int
	 *
	 * Total number of personal messages the user has.
	 */
	public int $messages;

	/**
	 * @var int
	 *
	 * Number of unread personal messages the user has.
	 */
	public int $unread_messages;

	/**
	 * @var int
	 *
	 * Whether the user has new personal messages.
	 */
	public int $new_pm;

	/**
	 * @var int
	 *
	 * Number of unread alerts the user has.
	 */
	public int $alerts;

	/**
	 * @var array
	 *
	 * IDs of boards that this user is ignoring.
	 */
	public array $ignoreboards = [];

	/**
	 * @var string
	 *
	 * Name of the user's group.
	 *
	 * Usually the same as $primary_group_name, but might change if the user
	 * is a moderator on the current board.
	 */
	public string $group_name;

	/**
	 * @var string
	 *
	 * Name of the user's primary group.
	 *
	 * Does not change even if the user is a moderator on the current board.
	 */
	public string $primary_group_name;

	/**
	 * @var string
	 *
	 * Name of the user's post-count based group.
	 */
	public string $post_group_name;

	/**
	 * @var string
	 *
	 * The color associated with this user's group.
	 */
	public string $group_color;

	/**
	 * @var string
	 *
	 * The color associated with this user's post group.
	 */
	public string $post_group_color;

	/**
	 * @var array
	 *
	 * Info about the icons associated with this user's group.
	 * (Exactly which group will depend on the situation.)
	 */
	public array $icons;

	/**
	 * @var array
	 *
	 * Info about the user's avatar.
	 */
	public array $avatar = [
		'original_url' => null,
		'url' => null,
		'href' => null,
		'name' => null,
		'filename' => null,
		'custom_dir' => null,
		'id_attach' => null,
		'width' => null,
		'height' => null,
		'image' => null,
	];

	/**
	 * @var array
	 *
	 * Permssions that this user has been granted.
	 */
	public array $permissions = [];

	/**
	 * @var int
	 *
	 * This user's warning level.
	 */
	public int $warning;

	/**
	 * @var array
	 *
	 * Moderator access info.
	 */
	public array $mod_cache = [];

	/**
	 * @var string
	 *
	 * Moderator preferences.
	 * @todo This doesn't appear to be used anywhere.
	 */
	public string $mod_prefs = '';

	/**
	 * @var bool
	 *
	 * Whether this user can access the moderation center.
	 */
	public bool $can_mod = false;

	/**
	 * @var bool
	 *
	 * Whether this user can manage boards.
	 */
	public bool $can_manage_boards = false;

	/**
	 * @var string
	 *
	 * SQL query string to get only boards this user can see.
	 */
	public string $query_see_board;

	/**
	 * @var string
	 *
	 * Variant of $query_see_board that checks against topics' id_board field.
	 */
	public string $query_see_topic_board;

	/**
	 * @var string
	 *
	 * Variant of $query_see_board that checks against posts' id_board field.
	 */
	public string $query_see_message_board;

	/**
	 * @var string
	 *
	 * SQL query string to get only boards this user can see and is not ignoring.
	 */
	public string $query_wanna_see_board;

	/**
	 * @var string
	 *
	 * Variant of $query_wanna_see_board that checks against topics' id_board field.
	 */
	public string $query_wanna_see_topic_board;

	/**
	 * @var string
	 *
	 * Variant of $query_wanna_see_board that checks against posts' id_board field.
	 */
	public string $query_wanna_see_message_board;

	/**
	 * @var array
	 *
	 * Formatted versions of this user's properties, suitable for display.
	 */
	public array $formatted = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/**
	 * @var object
	 *
	 * Instance of this class for the current user.
	 */
	public static object $me;

	/**
	 * @var int
	 *
	 * ID number of the current user.
	 *
	 * As a general rule, code outside this class should use User::$me->id
	 * rather than User::$my_id. The only exception to this rule is in code
	 * executed during the login and logout processes, because User::$me->id
	 * is not set at all points during those processes.
	 */
	public static int $my_id;

	/**
	 * @var string
	 *
	 * "Session check" value for the current user.
	 * Set by Session::load(). Used by checkSession().
	 */
	public static $sc;

	/**
	 * @var array
	 *
	 * Basic data from the database about all loaded users.
	 */
	public static array $profiles = [];

	/**
	 * @var array
	 *
	 * Basic data from the database about the current user.
	 * A reference to User::$profiles[User::$my_id].
	 * Only exists for backward compatibility reasons.
	 */
	public static $settings;

	/**
	 * @var object
	 *
	 * Processed data about the current user.
	 * This is set to a reference to User::$me once the latter exists.
	 * Only exists for backward compatibility reasons.
	 */
	public static $info;

	/**
	 * @var array
	 *
	 * Alternative way to get formatted data about users.
	 * A reference to User::$loaded[$id]->formatted (where $id is a user ID).
	 * Only exists for backward compatibility reasons.
	 */
	public static $memberContext;

	/**
	 * @var array
	 *
	 * Fields in the member table that take integers.
	 */
	public static array $knownInts = [
		'alerts',
		'date_registered',
		'gender',
		'id_group',
		'id_msg_last_visit',
		'id_post_group',
		'id_theme',
		'instant_messages',
		'is_activated',
		'last_login',
		'new_pm',
		'pm_prefs',
		'pm_receive_from',
		'posts',
		'show_online',
		'total_time_logged_in',
		'unread_messages',
		'warning',
	];

	/**
	 * @var array
	 *
	 * Fields in the member table that take floats.
	 */
	public static array $knownFloats = [
		'time_offset',
	];

	/**
	 * @var array
	 *
	 * Names of variables to pass to the integrate_change_member_data hook.
	 */
	public static array $integration_vars = [
		'avatar',
		'birthdate',
		'email_address',
		'gender',
		'id_group',
		'lngfile',
		'location',
		'member_name',
		'real_name',
		'time_format',
		'time_offset',
		'timezone',
		'website_title',
		'website_url',
	];

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

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Whether the integrate_verify_user hook verified this user for us.
	 */
	private bool $already_verified = false;

	/**
	 * @var array
	 *
	 * The dataset that was loaded for this user.
	 */
	private string $dataset;

	/**
	 * @var bool
	 *
	 * Whether custom profile fields are in the formatted data for this user.
	 */
	private bool $custom_fields_displayed = false;

	/**
	 * @var array
	 *
	 * Cache for the allowedTo() method.
	 */
	private array $perm_cache = [];

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_member' => 'id',
		'member_name' => 'username',
		'real_name' => 'name',
		'display_name' => 'name',
		'email_address' => 'email',
		'lngfile' => 'language',
		'member_group' => 'group_name',
		'primary_group' => 'primary_group_name',
		'member_group_color' => 'group_color',
		'member_ip' => 'ip',
		'member_ip2' => 'ip2',
		'usertitle' => 'title',
		'blurb' => 'title',
		'id_theme' => 'theme',
		'ignore_boards' => 'ignoreboards',
		'pm_ignore_list' => 'ignoreusers',
		'buddy_list' => 'buddies',
		'instant_messages' => 'messages',
		'birth_date' => 'birthdate',
		'last_login_timestamp' => 'last_login',

		// Square brackets are parsed to find array elements.
		'website_url' => 'website[url]',
		'website_title' => 'website[title]',

		// Initial exclamation mark means inverse of the property.
		'is_logged' => '!is_guest',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Maps names of dataset levels to numeric values.
	 */
	protected static array $dataset_levels = [
		'minimal' => 0,
		'basic' => 1,
		'normal' => 2,
		'profile' => 3,
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, mixed $value): void
	{
		if (in_array($this->prop_aliases[$prop] ?? $prop, ['additional_groups', 'buddies', 'ignoreusers', 'ignoreboards']) && is_string($value)) {
			$prop = $this->prop_aliases[$prop] ?? $prop;
			$value = array_map('intval', array_filter(explode(',', $value), 'strlen'));
		}

		$this->customPropertySet($prop, $value);
	}

	/**
	 * Load this user's permissions.
	 */
	public function loadPermissions(): void
	{
		if ($this->is_admin) {
			$this->can_mod = true;
			$this->can_manage_boards = true;

			$this->adjustPermissions();

			return;
		}

		if (!empty(CacheApi::$enable)) {
			$cache_groups = $this->groups;
			asort($cache_groups);
			$cache_groups = implode(',', $cache_groups);

			// If it's a spider then cache it separately.
			if ($this->possibly_robot) {
				$cache_groups .= '-spider';
			}

			if (
				CacheApi::$enable >= 2
				&& !empty(Board::$info->id)
				&& ($temp = CacheApi::get('permissions:' . $cache_groups . ':' . Board::$info->id, 240)) != null
				&& time() - 240 > Config::$modSettings['settings_updated']
			) {
				list($this->permissions) = $temp;
				$this->adjustPermissions();

				return;
			}

			if (
				($temp = CacheApi::get('permissions:' . $cache_groups, 240)) != null
				&& time() - 240 > Config::$modSettings['settings_updated']
			) {
				list($this->permissions, $removals) = $temp;
			}
		}

		// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
		$spider_restrict = $this->possibly_robot && !empty(Config::$modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

		if (empty($this->permissions)) {
			// Get the general permissions.
			$removals = [];
			$request = Db::$db->query(
				'',
				'SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:member_groups})
					' . $spider_restrict,
				[
					'member_groups' => $this->groups,
					'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (empty($row['add_deny'])) {
					$removals[] = $row['permission'];
				} else {
					$this->permissions[] = $row['permission'];
				}
			}
			Db::$db->free_result($request);

			if (isset($cache_groups)) {
				CacheApi::put('permissions:' . $cache_groups, [$this->permissions, $removals], 240);
			}
		}

		// Get the board permissions.
		if (!empty(Board::$info->id)) {
			// Make sure the board (if any) has been loaded by Board::load().
			if (!isset(Board::$info->profile)) {
				ErrorHandler::fatalLang('no_board');
			}

			$request = Db::$db->query(
				'',
				'SELECT permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE (id_group IN ({array_int:member_groups})
					' . $spider_restrict . ')
					AND id_profile = {int:id_profile}',
				[
					'member_groups' => $this->groups,
					'id_profile' => Board::$info->profile,
					'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (empty($row['add_deny'])) {
					$removals[] = $row['permission'];
				} else {
					$this->permissions[] = $row['permission'];
				}
			}
			Db::$db->free_result($request);
		}

		// Remove all the permissions they shouldn't have ;).
		if (!empty(Config::$modSettings['permission_enable_deny'])) {
			$this->permissions = array_diff($this->permissions, $removals);
		}

		if (isset($cache_groups) && !empty(Board::$info->id) && CacheApi::$enable >= 2) {
			CacheApi::put('permissions:' . $cache_groups . ':' . Board::$info->id, [$this->permissions, null], 240);
		}

		// Banned?  Watch, don't touch..
		$this->adjustPermissions();

		// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
		if (!$this->is_guest && $this->id === self::$my_id) {
			if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= Config::$modSettings['settings_updated']) {
				$this->rebuildModCache();
			} else {
				$this->mod_cache = $_SESSION['mc'];
			}

			// This is a useful phantom permission added to the current user, and only the current user while they are logged in.
			// For example this drastically simplifies certain changes to the profile area.
			$this->permissions[] = 'is_not_guest';

			// And now some backwards compatibility stuff for mods and whatnot that aren't expecting the new permissions.
			$this->permissions[] = 'profile_view_own';

			if (in_array('profile_view', $this->permissions)) {
				$this->permissions[] = 'profile_view_any';
			}

			// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
			$this->can_mod = in_array('access_mod_center', $this->permissions) || ($this->mod_cache['gq'] ?? '0=1') != '0=1' || ($this->mod_cache['bq'] ?? '0=1') != '0=1' || (Config::$modSettings['postmod_active'] && !empty($this->mod_cache['ap']));
		}
	}

	/**
	 * Sets the formatted versions of user data for use in themes and templates.
	 *
	 * @param bool $display_custom_fields Whether to get custom profile fields
	 *    ready for display.
	 * @return array A copy of $this->formatted.
	 */
	public function format(bool $display_custom_fields = false): array
	{
		static $loadedLanguages = [];

		Lang::load('index+Modifications');

		if (empty(Config::$modSettings['displayFields'])) {
			$display_custom_fields = false;
		}

		// If this user's data is already loaded, skip it.
		if (!empty($this->formatted) && $this->custom_fields_displayed >= $display_custom_fields) {
			return $this->formatted;
		}

		// The minimal values.
		$this->formatted = [
			'id' => $this->id,
			'username' => $this->is_guest ? Lang::$txt['guest_title'] : $this->username,
			'name' => $this->is_guest ? Lang::$txt['guest_title'] : $this->name,
			'href' => $this->is_guest ? '' : Config::$scripturl . '?action=profile;u=' . $this->id,
			'link' => $this->is_guest ? '' : '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->name) . '">' . $this->name . '</a>',
			'email' => $this->email,
			'show_email' => !self::$me->is_guest && (self::$me->id == $this->id || self::$me->allowedTo('moderate_forum')),
			'registered' => empty($this->date_registered) ? Lang::$txt['not_applicable'] : Time::create('@' . $this->date_registered)->format(),
			'registered_timestamp' => $this->date_registered,
		];

		// Basic, normal, and profile want the avatar.
		if (in_array($this->dataset, ['basic', 'normal', 'profile'])) {
			$this->formatted['avatar'] = $this->avatar;
		}

		// Normal and profile want lots more data.
		if (in_array($this->dataset, ['normal', 'profile'])) {
			// Go the extra mile and load the user's native language name.
			if (empty($loadedLanguages)) {
				$loadedLanguages = Lang::get(true);
			}

			// We need a little fallback for the membergroup icons. If the image
			// doesn't exist in the current theme, fall back to default theme.
			$group_icon_url = '';

			if (isset($this->icons[1])) {
				foreach (['actual_theme_dir' => 'images_url', 'default_theme_dir' => 'default_images_url'] as $dir => $url) {
					if (file_exists(Theme::$current->settings[$dir] . '/images/membericons/' . $this->icons[1])) {
						$group_icon_url = Theme::$current->settings[$url] . '/membericons/' . $this->icons[1];
					}
				}
			}

			// Is this user online, and if so, is their online status visible?
			$is_visibly_online = (!empty($this->show_online) || self::$me->allowedTo('moderate_forum')) && $this->is_online > 0;

			// Now append all the rest of the data.
			$this->formatted += [
				'username_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->username . '</span>',
				'name_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->name . '</span>',
				'link_color' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->name) . '" ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->name . '</a>',
				'is_buddy' => in_array($this->id, self::$me->buddies),
				'is_reverse_buddy' => in_array(self::$me->id, $this->buddies),
				'buddies' => $this->buddies,
				'title' => !empty(Config::$modSettings['titlesEnable']) ? $this->title : '',
				'blurb' => $this->personal_text,
				'website' => $this->website,
				'birth_date' => empty($this->birthdate) ? '1004-01-01' : (substr($this->birthdate, 0, 4) === '0004' ? '1004' . substr($this->birthdate, 4) : $this->birthdate),
				'signature' => $this->signature,
				'real_posts' => $this->posts,
				'posts' => $this->posts > 500000 ? Lang::$txt['geek'] : Lang::numberFormat($this->posts),
				'last_login' => empty($this->last_login) ? Lang::$txt['never'] : Time::create('@' . $this->last_login)->format(),
				'last_login_timestamp' => empty($this->last_login) ? 0 : $this->last_login,
				'ip' => Utils::htmlspecialchars($this->ip),
				'ip2' => Utils::htmlspecialchars($this->ip2),
				'online' => [
					'is_online' => $is_visibly_online,
					'text' => Utils::htmlspecialchars(Lang::$txt[$is_visibly_online ? 'online' : 'offline']),
					'member_online_text' => sprintf(Lang::$txt[$is_visibly_online ? 'member_is_online' : 'member_is_offline'], Utils::htmlspecialchars($this->name)),
					'href' => Config::$scripturl . '?action=pm;sa=send;u=' . $this->id,
					'link' => '<a href="' . Config::$scripturl . '?action=pm;sa=send;u=' . $this->id . '">' . Lang::$txt[$is_visibly_online ? 'online' : 'offline'] . '</a>',
					'label' => Lang::$txt[$is_visibly_online ? 'online' : 'offline'],
				],
				'language' => !empty($loadedLanguages[$this->language]) && !empty($loadedLanguages[$this->language]['name']) ? $loadedLanguages[$this->language]['name'] : Utils::ucwords(strtr($this->language, ['_' => ' ', '-utf8' => ''])),
				'is_activated' => $this->is_activated % 10 == 1,
				'is_banned' => $this->is_banned,
				'options' => $this->options,
				'is_guest' => $this->is_guest,
				'group_id' => $this->group_id,
				'group' => $this->is_guest ? Lang::$txt['guest_title'] : $this->group_name,
				'group_name' => $this->is_guest ? Lang::$txt['guest_title'] : $this->group_name,
				'group_color' => $this->group_color,
				'primary_group' => $this->is_guest ? Lang::$txt['guest_title'] : $this->primary_group_name,
				'primary_group_name' => $this->is_guest ? Lang::$txt['guest_title'] : $this->primary_group_name,
				'post_group_id' => $this->post_group_id,
				'post_group' => $this->is_guest ? Lang::$txt['guest_title'] : $this->post_group_name,
				'post_group_name' => $this->is_guest ? Lang::$txt['guest_title'] : $this->post_group_name,
				'post_group_color' => $this->is_guest ? '' : $this->post_group_color,
				'group_icons' => str_repeat('<img src="' . str_replace('$language', self::$me->language, isset($this->icons[1]) ? $group_icon_url : '') . '" alt="*">', empty($this->icons[0]) || empty($this->icons[1]) ? 0 : $this->icons[0]),
				'warning' => $this->warning,
				'warning_status' => !empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= $this->warning ? 'mute' : (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= $this->warning ? 'moderate' : (!empty(Config::$modSettings['warning_watch']) && Config::$modSettings['warning_watch'] <= $this->warning ? 'watch' : '')),
				'local_time' => Time::create('now', $this->timezone)->format(null, false),
				'custom_fields' => [],
			];

			Lang::censorText($this->formatted['blurb']);
			Lang::censorText($this->formatted['signature']);

			$this->formatted['signature'] = BBCodeParser::load()->parse(str_replace(["\n", "\r"], ['<br>', ''], $this->formatted['signature']), true, 'sig' . $this->id, BBCodeParser::getSigTags());
		}

		// Are we also loading the member's custom fields?
		if ($display_custom_fields) {
			$this->formatted['custom_fields'] = [];

			if (!isset(Utils::$context['display_fields'])) {
				Utils::$context['display_fields'] = Utils::jsonDecode(Config::$modSettings['displayFields'], true);
			}

			foreach (Utils::$context['display_fields'] as $custom) {
				if (!isset($custom['col_name']) || trim($custom['col_name']) == '' || empty($this->options[$custom['col_name']])) {
					continue;
				}

				$value = $this->options[$custom['col_name']];

				$fieldOptions = [];
				$currentKey = 0;

				// Create a key => value array for multiple options fields
				if (!empty($custom['options'])) {
					foreach ($custom['options'] as $k => $v) {
						$fieldOptions[] = $v;

						if (empty($currentKey)) {
							$currentKey = $v == $value ? $k : 0;
						}
					}
				}

				// BBC?
				if ($custom['bbc']) {
					$value = BBCodeParser::load()->parse($value);
				}
				// ... or checkbox?
				elseif (isset($custom['type']) && $custom['type'] == 'check') {
					$value = $value ? Lang::$txt['yes'] : Lang::$txt['no'];
				}

				// Enclosing the user input within some other text?
				$simple_value = $value;

				if (!empty($custom['enclose'])) {
					$value = strtr($custom['enclose'], [
						'{SCRIPTURL}' => Config::$scripturl,
						'{IMAGES_URL}' => Theme::$current->settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
						'{INPUT}' => Lang::tokenTxtReplace($value),
						'{KEY}' => $currentKey,
					]);
				}

				$this->formatted['custom_fields'][] = [
					'title' => Lang::tokenTxtReplace(!empty($custom['title']) ? $custom['title'] : $custom['col_name']),
					'col_name' => Lang::tokenTxtReplace($custom['col_name']),
					'value' => Utils::htmlspecialcharsDecode(Lang::tokenTxtReplace($value)),
					'simple' => Lang::tokenTxtReplace($simple_value),
					'raw' => $this->options[$custom['col_name']],
					'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
				];
			}
		}

		IntegrationHook::call('integrate_member_context', [&$this->formatted, $this->id, $display_custom_fields]);

		$this->custom_fields_displayed = !empty($this->custom_fields_displayed) | $display_custom_fields;

		// For backward compatibility.
		self::$memberContext[$this->id] = &$this->formatted;

		return $this->formatted;
	}

	/**
	 * Put this user in the online log.
	 *
	 * @param bool $force Whether to force logging the data
	 */
	public function logOnline(bool $force = false): void
	{
		// If we are showing who is viewing a topic, let's see if we are, and force an update if so - to make it accurate.
		if (!empty(Theme::$current->settings['display_who_viewing']) && (!empty(Topic::$topic_id) || !empty(Board::$info->id))) {
			// Take the opposite approach!
			$force = true;

			// Don't update for every page - this isn't wholly accurate but who cares.
			if (!empty(Topic::$topic_id)) {
				if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == Topic::$topic_id) {
					$force = false;
				}

				$_SESSION['last_topic_id'] = Topic::$topic_id;
			}
		}

		// Are they a spider we should be tracking? Mode = 1 gets tracked on its spider check...
		if (!empty($this->possibly_robot) && !empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] > 1) {
			self::logSpider();
		}

		// Don't mark them as online more than every so often.
		if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force) {
			return;
		}

		if (!empty(Config::$modSettings['who_enabled'])) {
			// In the case of a dlattach action, session_var may not be set.
			if (!isset(Utils::$context['session_var'])) {
				Utils::$context['session_var'] = $_SESSION['session_var'];
			}

			// Sometimes folks mess with USER_AGENT and $_GET, so we do this to
			// prevent 'data too long' errors.
			$num_elements = count($_GET, COUNT_RECURSIVE) + 1;
			$max_length = 2048;

			do {
				$encoded_get = $_GET + ['USER_AGENT' => mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 128)];

				unset($encoded_get['sesc'], $encoded_get[Utils::$context['session_var']]);

				$encoded_get = Utils::truncateArray($encoded_get, $max_length);
				$encoded_get = Utils::jsonEncode($encoded_get);

				// If too long, reduce $max_length by one byte per element and try again.
				$max_length -= $num_elements;
			} while (strlen($encoded_get) > 2048);
		} else {
			$encoded_get = '';
		}

		// Guests use their IP address, members use their session ID.
		$session_id = $this->is_guest ? 'ip' . $this->ip : session_id();

		// Grab the last all-of-SMF-specific log_online deletion time.
		$do_delete = CacheApi::get('log_online-update', 30) < time() - 30;

		// If the last click wasn't a long time ago, and there was a last click...
		if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - Config::$modSettings['lastActive'] * 20) {
			if ($do_delete) {
				Db::$db->query(
					'delete_log_online_interval',
					'DELETE FROM {db_prefix}log_online
					WHERE log_time < {int:log_time}
						AND session != {string:session}',
					[
						'log_time' => time() - Config::$modSettings['lastActive'] * 60,
						'session' => $session_id,
					],
				);

				// Cache when we did it last.
				CacheApi::put('log_online-update', time(), 30);
			}

			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_online
				SET log_time = {int:log_time}, ip = {inet:ip}, url = {string:url}
				WHERE session = {string:session}',
				[
					'log_time' => time(),
					'ip' => $this->ip,
					'url' => $encoded_get,
					'session' => $session_id,
				],
			);

			// Guess it got deleted.
			if (Db::$db->affected_rows() == 0) {
				$_SESSION['log_time'] = 0;
			}
		} else {
			$_SESSION['log_time'] = 0;
		}

		// Otherwise, we have to delete and insert.
		if (empty($_SESSION['log_time'])) {
			if ($do_delete || !empty($this->id)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_online
					WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty($this->id) ? ' OR ' : '') . (empty($this->id) ? '' : 'id_member = {int:current_member}'),
					[
						'current_member' => $this->id,
						'log_time' => time() - Config::$modSettings['lastActive'] * 60,
					],
				);
			}

			Db::$db->insert(
				$do_delete ? 'ignore' : 'replace',
				'{db_prefix}log_online',
				['session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'inet', 'url' => 'string'],
				[$session_id, $this->id, empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), $this->ip, $encoded_get],
				['session'],
			);
		}

		// Mark your session as being logged.
		$_SESSION['log_time'] = time();

		// Well, they are online now.
		if (empty($_SESSION['timeOnlineUpdated'])) {
			$_SESSION['timeOnlineUpdated'] = time();
		}

		// Set their login time, if not already done within the last minute.
		if (
			SMF != 'SSI'
			&& !empty($this->last_login)
			&& $this->last_login < time() - 60
			&& (
				!isset($_REQUEST['action'])
				|| !in_array($_REQUEST['action'], ['.xml', 'login2', 'logintfa'])
			)
		) {
			// Don't count longer than 15 minutes.
			if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15) {
				$_SESSION['timeOnlineUpdated'] = time();
			}

			$this->total_time_logged_in += (time() - $_SESSION['timeOnlineUpdated']);

			self::updateMemberData($this->id, ['last_login' => time(), 'member_ip' => $this->ip, 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => $this->total_time_logged_in]);

			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('user_settings-' . $this->id, self::$profiles[$this->id], 60);
			}

			$_SESSION['timeOnlineUpdated'] = time();
		}
	}

	/**
	 * Quickly find out what moderation authority the current user has
	 *
	 * Builds the moderator, group and board level querys for the user.
	 *
	 * Stores the information on the current users moderation powers in
	 * User::$me->mod_cache and $_SESSION['mc'].
	 */
	public function rebuildModCache(): void
	{
		if ($this->id !== User::$my_id) {
			return;
		}

		// What groups can they moderate?
		$group_query = $this->allowedTo('manage_membergroups') ? '1=1' : '0=1';

		if ($group_query == '0=1' && !$this->is_guest) {
			$groups = [];

			$request = Db::$db->query(
				'',
				'SELECT id_group
				FROM {db_prefix}group_moderators
				WHERE id_member = {int:current_member}',
				[
					'current_member' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$groups[] = $row['id_group'];
			}
			Db::$db->free_result($request);

			if (empty($groups)) {
				$group_query = '0=1';
			} else {
				$group_query = 'id_group IN (' . implode(',', $groups) . ')';
			}
		}

		// Then, same again, just the boards this time!
		$board_query = $this->allowedTo('moderate_forum') ? '1=1' : '0=1';

		if ($board_query == '0=1' && !$this->is_guest) {
			$boards = $this->boardsAllowedTo('moderate_board', true);

			if (empty($boards)) {
				$board_query = '0=1';
			} else {
				$board_query = 'id_board IN (' . implode(',', $boards) . ')';
			}
		}

		// What boards are they the moderator of?
		$boards_mod = [];

		if (!$this->is_guest) {
			$request = Db::$db->query(
				'',
				'SELECT id_board
				FROM {db_prefix}moderators
				WHERE id_member = {int:current_member}',
				[
					'current_member' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards_mod[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			// Can any of the groups they're in moderate any of the boards?
			$request = Db::$db->query(
				'',
				'SELECT id_board
				FROM {db_prefix}moderator_groups
				WHERE id_group IN({array_int:groups})',
				[
					'groups' => $this->groups,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards_mod[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			// Just in case we've got duplicates here...
			$boards_mod = array_unique($boards_mod);
		}

		$mod_query = empty($boards_mod) ? '0=1' : 'b.id_board IN (' . implode(',', $boards_mod) . ')';

		$_SESSION['mc'] = [
			'time' => time(),
			// This looks a bit funny but protects against the login redirect.
			'id' => $this->id && $this->name ? $this->id : 0,
			// If you change the format of 'gq' and/or 'bq' make sure to adjust 'can_mod' in SMF\User.
			'gq' => $group_query,
			'bq' => $board_query,
			'ap' => $this->boardsAllowedTo('approve_posts'),
			'mb' => $boards_mod,
			'mq' => $mod_query,
		];

		IntegrationHook::call('integrate_mod_cache');

		$this->mod_cache = $_SESSION['mc'];

		// Might as well clean up some tokens while we are at it.
		SecurityToken::clean();
	}

	/**
	 * Requires a user who is logged in (not a guest).
	 *
	 * Checks if the user is currently a guest, and if so asks them to login
	 * with a message telling them why. If $message is empty, a default message
	 * will be used.
	 *
	 * @param string $message The message to display to the guest.
	 * @param bool $log Whether to log what they were trying to do.
	 */
	public function kickIfGuest(?string $message = null, bool $log = true): void
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return;
		}

		// Luckily, this person isn't a guest.
		if (!$this->is_guest) {
			return;
		}

		// Log what they were trying to do that didn't work.
		if ($log) {
			if (!empty(Config::$modSettings['who_enabled'])) {
				$_GET['error'] = 'guest_login';
			}

			$this->logOnline(true);
		}

		// Just die.
		if (isset($_REQUEST['xml'])) {
			Utils::obExit(false);
		}

		// We need the theme if we're going to show anything.
		if (SMF != 'SSI' && empty(Utils::$context['theme_loaded'])) {
			Theme::load();
		}

		// Never redirect to an attachment
		if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false) {
			$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];
		}

		// Apparently we're not in a position to handle this now. Let's go to a safer location.
		if (empty(Utils::$context['template_layers'])) {
			$_SESSION['login_url'] = Config::$scripturl . '?' . $_SERVER['QUERY_STRING'];
			Utils::redirectexit('action=login');
		}

		// Load the Login template and language file.
		Theme::loadTemplate('Login');
		Lang::load('Login');

		// Create a login token.
		SecurityToken::create('login');

		// Use the kick_guest sub template...
		Utils::$context['sub_template'] = 'kick_guest';
		Utils::$context['page_title'] = Lang::$txt['login'];
		Utils::$context['kick_message'] = $message ?? Lang::$txt['only_members_can_access'];
		Utils::$context['robot_no_index'] = true;

		Utils::obExit();

		// We should never get to this point, but if we did we wouldn't know the user is a guest.
		trigger_error('No direct access...', E_USER_ERROR);
	}

	/**
	 * Does banning related stuff (i.e. disallowing access).
	 *
	 * Checks if the user is banned, and if so dies with an error.
	 * Caches this information for optimization purposes.
	 *
	 * @param bool $force_check Whether to force a recheck.
	 */
	public function kickIfBanned(bool $force_check = false): void
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return;
		}

		// You cannot be banned if you are an admin.
		if ($this->is_admin) {
			return;
		}

		// Only check the ban every so often. (to reduce load.)
		if (
			$force_check
			|| !isset($_SESSION['ban'])
			|| empty(Config::$modSettings['banLastUpdated'])
			|| $_SESSION['ban']['last_checked'] < Config::$modSettings['banLastUpdated']
			|| $_SESSION['ban']['id_member'] != $this->id
			|| $_SESSION['ban']['ip'] != $this->ip
			|| $_SESSION['ban']['ip2'] != $this->ip2
			|| (
				isset($this->email, $_SESSION['ban']['email'])
				&& $_SESSION['ban']['email'] != $this->email
			)
		) {
			// Innocent until proven guilty.  (but we know you are! :P)
			$_SESSION['ban'] = [
				'last_checked' => time(),
				'id_member' => $this->id,
				'ip' => $this->ip,
				'ip2' => $this->ip2,
				'email' => $this->email,
			];

			$ban_query = [];
			$ban_query_vars = ['current_time' => time()];
			$flag_is_activated = false;

			// Check both IP addresses.
			foreach (['ip', 'ip2'] as $ip_number) {
				if ($ip_number == 'ip2' && $this->ip2 == $this->ip) {
					continue;
				}

				$ban_query[] = ' {inet:' . $ip_number . '} BETWEEN bi.ip_low and bi.ip_high';
				$ban_query_vars[$ip_number] = $this->{$ip_number};

				// IP was valid, maybe there's also a hostname...
				if (empty(Config::$modSettings['disableHostnameLookup']) && $this->{$ip_number} != 'unknown') {
					$ip = new IP($this->{$ip_number});
					$hostname = $ip->getHost();

					if (strlen($hostname) > 0) {
						$ban_query[] = '({string:hostname' . $ip_number . '} LIKE bi.hostname)';
						$ban_query_vars['hostname' . $ip_number] = $hostname;
					}
				}
			}

			// Is their email address banned?
			if (strlen($this->email) != 0) {
				$ban_query[] = '({string:email} LIKE bi.email_address)';
				$ban_query_vars['email'] = $this->email;
			}

			// How about this user?
			if (!$this->is_guest && !empty($this->id)) {
				$ban_query[] = 'bi.id_member = {int:id_member}';
				$ban_query_vars['id_member'] = $this->id;
			}

			// Check the ban, if there's information.
			if (!empty($ban_query)) {
				$restrictions = [
					'cannot_access',
					'cannot_login',
					'cannot_post',
					'cannot_register',
				];

				// Store every type of ban that applies to you in your session.
				$request = Db::$db->query(
					'',
					'SELECT bi.id_ban, bi.email_address, bi.id_member, bg.cannot_access, bg.cannot_register,
						bg.cannot_post, bg.cannot_login, bg.reason, COALESCE(bg.expire_time, 0) AS expire_time
					FROM {db_prefix}ban_items AS bi
						INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
					WHERE
						(' . implode(' OR ', $ban_query) . ')',
					$ban_query_vars,
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					foreach ($restrictions as $restriction) {
						if (!empty($row[$restriction])) {
							$_SESSION['ban'][$restriction]['reason'] = $row['reason'];
							$_SESSION['ban'][$restriction]['ids'][] = $row['id_ban'];

							if (
								!isset($_SESSION['ban']['expire_time'])
								|| (
									$_SESSION['ban']['expire_time'] != 0
									&& (
										$row['expire_time'] == 0
										|| $row['expire_time'] > $_SESSION['ban']['expire_time']
									)
								)
							) {
								$_SESSION['ban']['expire_time'] = $row['expire_time'];
							}

							if (
								!$this->is_guest
								&& $restriction == 'cannot_access'
								&& (
									$row['id_member'] == $this->id
									|| $row['email_address'] == $this->email
								)
							) {
								$flag_is_activated = true;
							}
						}
					}
				}
				Db::$db->free_result($request);
			}

			// Mark the cannot_access and cannot_post bans as being 'hit'.
			if (isset($_SESSION['ban']['cannot_access'], $_SESSION['ban']['cannot_post'], $_SESSION['ban']['cannot_login'])) {
				$this->logBan(array_merge(
					isset($_SESSION['ban']['cannot_access']) ? $_SESSION['ban']['cannot_access']['ids'] : [],
					isset($_SESSION['ban']['cannot_post']) ? $_SESSION['ban']['cannot_post']['ids'] : [],
					isset($_SESSION['ban']['cannot_login']) ? $_SESSION['ban']['cannot_login']['ids'] : [],
				));
			}

			// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
			if (
				$this->id
				&& (
					(
						$this->is_activated >= 10
						&& !$flag_is_activated
					)
					|| (
						$this->is_activated < 10
						&& $flag_is_activated
					)
				)
			) {
				Bans::updateBanMembers();
			}
		}

		// Hey, I know you! You're ehm...
		if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[Config::$cookiename . '_'])) {
			$bans = explode(',', $_COOKIE[Config::$cookiename . '_']);

			foreach ($bans as $key => $value) {
				$bans[$key] = (int) $value;
			}

			$request = Db::$db->query(
				'',
				'SELECT bi.id_ban, bg.reason, COALESCE(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
				WHERE bi.id_ban IN ({array_int:ban_list})
					AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
					AND bg.cannot_access = {int:cannot_access}
				LIMIT {int:limit}',
				[
					'cannot_access' => 1,
					'ban_list' => $bans,
					'current_time' => time(),
					'limit' => count($bans),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
				$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
				$_SESSION['ban']['expire_time'] = $row['expire_time'];
			}
			Db::$db->free_result($request);

			// My mistake. Next time better.
			if (!isset($_SESSION['ban']['cannot_access'])) {
				$cookie = new Cookie(Config::$cookiename . '_', [], time() - 3600);
				$cookie->set();
			}
		}

		// If you're fully banned, it's end of the story for you.
		if (isset($_SESSION['ban']['cannot_access'])) {
			// We don't wanna see you!
			if (!$this->is_guest) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_online
					WHERE id_member = {int:current_member}',
					[
						'current_member' => $this->id,
					],
				);
			}

			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach') {
				die();
			}

			// 'Log' the user out.  Can't have any funny business... (save the name!)
			$old_name = isset($this->name) && $this->name != '' ? $this->name : Lang::$txt['guest_title'];

			User::setMe(0);

			// A goodbye present.
			$cookie = new Cookie(Config::$cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() - 3600);
			$cookie->set();

			// Don't scare anyone, now.
			$_GET['action'] = '';
			$_GET['board'] = '';
			$_GET['topic'] = '';
			$this->logOnline(true);
			Logout::call(true, false);

			// You banned, sucka!
			ErrorHandler::fatal(sprintf(Lang::$txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_access']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf(Lang::$txt['your_ban_expires'], Time::create('@' . $_SESSION['ban']['expire_time'])->format(null, false)) : Lang::$txt['your_ban_expires_never']), false, 403);

			// If we get here, something's gone wrong.... but let's try anyway.
			trigger_error('No direct access...', E_USER_ERROR);
		}
		// You're not allowed to log in but yet you are. Let's fix that.
		elseif (isset($_SESSION['ban']['cannot_login']) && !$this->is_guest) {
			// We don't wanna see you!
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_online
				WHERE id_member = {int:current_member}',
				[
					'current_member' => $this->id,
				],
			);

			// 'Log' the user out.  Can't have any funny business... (save the name!)
			$old_name = isset($this->name) && $this->name != '' ? $this->name : Lang::$txt['guest_title'];
			User::setMe(0);

			// SMF's Wipe 'n Clean(r) erases all traces.
			$_GET['action'] = '';
			$_GET['board'] = '';
			$_GET['topic'] = '';
			$this->logOnline(true);

			Logout::call(true, false);

			ErrorHandler::fatal(sprintf(Lang::$txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_login']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_login']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf(Lang::$txt['your_ban_expires'], Time::create('@' . $_SESSION['ban']['expire_time'])->format(null, false)) : Lang::$txt['your_ban_expires_never']) . '<br>' . Lang::$txt['ban_continue_browse'], false, 403);
		}

		// Fix up the banning permissions.
		if (isset($this->permissions)) {
			$this->adjustPermissions();
		}
	}

	/**
	 * Logs a ban in the database.
	 *
	 * Increments the hit counters for the specified ban ID's (if any).
	 *
	 * @param array $ban_ids The IDs of the bans.
	 * @param string $email The email address associated with the user that
	 *    triggered this hit. If not set, uses the current user's email address.
	 */
	public function logBan(array $ban_ids = [], ?string $email = null): void
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return;
		}

		// Don't log web accelerators, it's very confusing...
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
			return;
		}

		Db::$db->insert(
			'',
			'{db_prefix}log_banned',
			[
				'id_member' => 'int',
				'ip' => 'inet',
				'email' => 'string',
				'log_time' => 'int',
			],
			[
				$this->id,
				$this->ip,
				$email ?? $this->email,
				time(),
			],
			['id_ban_log'],
		);

		// One extra point for these bans.
		if (!empty($ban_ids)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}ban_items
				SET hits = hits + 1
				WHERE id_ban IN ({array_int:ban_ids})',
				[
					'ban_ids' => $ban_ids,
				],
			);
		}
	}

	/**
	 * Fix permissions according to ban and warning status.
	 *
	 * Applies any states of banning and/or warning moderation by removing
	 * permissions the user cannot have.
	 */
	public function adjustPermissions(): void
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return;
		}

		// Somehow they got here, at least take away all permissions...
		if (isset($_SESSION['ban']['cannot_access'])) {
			$this->permissions = [];
		}
		// Okay, well, you can watch, but don't touch a thing.
		elseif (isset($_SESSION['ban']['cannot_post']) || (!empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= $this->warning)) {
			IntegrationHook::call('integrate_post_ban_permissions', [&self::$post_ban_permissions]);

			$this->permissions = array_diff($this->permissions, self::$post_ban_permissions);
		}
		// Are they absolutely under moderation?
		elseif (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= $this->warning) {
			// Work out what permissions should change...
			IntegrationHook::call('integrate_warn_permissions', [&self::$warn_permissions]);

			foreach (self::$warn_permissions as $old => $new) {
				if (!in_array($old, $this->permissions)) {
					unset(self::$warn_permissions[$old]);
				} else {
					$this->permissions[] = $new;
				}
			}

			$this->permissions = array_diff($this->permissions, array_keys(self::$warn_permissions));
		}

		// @todo Find a better place to call this? Needs to be after permissions loaded!
		// Finally, some bits we cache in the session because it saves queries.
		if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > Config::$modSettings['settings_updated'] && $_SESSION['mc']['id'] == $this->id) {
			$this->mod_cache = $_SESSION['mc'];
		} else {
			$this->rebuildModCache();
		}

		// Now that we have the mod cache taken care of lets setup a cache for the number of mod reports still open
		if (isset($_SESSION['rc']['reports'], $_SESSION['rc']['member_reports'])   && $_SESSION['rc']['time'] > Config::$modSettings['last_mod_report_action'] && $_SESSION['rc']['id'] == $this->id) {
			Utils::$context['open_mod_reports'] = $_SESSION['rc']['reports'];
			Utils::$context['open_member_reports'] = $_SESSION['rc']['member_reports'];
		} elseif ($_SESSION['mc']['bq'] != '0=1') {
			Utils::$context['open_mod_reports'] = ReportedContent::recountOpenReports('posts');
			Utils::$context['open_member_reports'] = ReportedContent::recountOpenReports('members');
		} else {
			Utils::$context['open_mod_reports'] = 0;
			Utils::$context['open_member_reports'] = 0;
		}
	}

	/**
	 * Check if the user is who he/she says he is.
	 *
	 * Makes sure the user is who they claim to be by requiring a password to be
	 * typed in every hour.
	 *
	 * Is turned on and off by the securityDisable setting.
	 *
	 * Uses the SMF\Actions\Admin\ACP::adminLogin() method if they need to login,
	 * which saves all request (post and get) data.
	 *
	 * @param string $type What type of session this is.
	 * @param bool $force If true, require a password even if we normally wouldn't.
	 * @return string|null Returns 'session_verify_fail' if verification failed,
	 *    or null if it passed.
	 */
	public function validateSession(string $type = 'admin', bool $force = false): ?string
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return null;
		}

		// We don't care if the option is off, because guests should NEVER get past here.
		$this->kickIfGuest();

		// Validate what type of session check this is.
		$types = [];
		IntegrationHook::call('integrate_validateSession', [&$types]);
		$type = in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

		// If we're using XML give an additional ten minutes grace as an admin
		// can't log on in XML mode.
		$refreshTime = isset($_GET['xml']) ? 4200 : 3600;

		if (empty($force)) {
			// Is the security option off?
			if (!empty(Config::$modSettings['securityDisable' . ($type != 'admin' ? '_' . $type : '')])) {
				return null;
			}

			// Or are they already logged in? Moderator or admin session is need for this area.
			if (
				(
					!empty($_SESSION[$type . '_time'])
					&& $_SESSION[$type . '_time'] + $refreshTime >= time()
				)
				|| (
					!empty($_SESSION['admin_time'])
					&& $_SESSION['admin_time'] + $refreshTime >= time()
				)
			) {
				return null;
			}
		}

		// Posting the password... check it.
		if (isset($_POST[$type . '_pass'])) {
			// Check to ensure we're forcing SSL for authentication
			if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Config::httpsOn()) {
				ErrorHandler::fatalLang('login_ssl_required');
			}

			$this->checkSession();

			$good_password = in_array(true, IntegrationHook::call('integrate_verify_password', [$this->username, $_POST[$type . '_pass'], false]), true);

			// Password correct?
			if ($good_password || Security::hashVerifyPassword($this->username, $_POST[$type . '_pass'], $this->passwd)) {
				$_SESSION[$type . '_time'] = time();

				unset($_SESSION['request_referer']);

				return null;
			}
		}

		// Better be sure to remember the real referer
		if (empty($_SESSION['request_referer'])) {
			$_SESSION['request_referer'] = isset($_SERVER['HTTP_REFERER']) ? @Url::create($_SERVER['HTTP_REFERER'])->parse() : [];
		} elseif (empty($_POST)) {
			unset($_SESSION['request_referer']);
		}

		// Need to type in a password for that, man.
		if (!isset($_GET['xml'])) {
			ACP::adminLogin($type);
		}

		return 'session_verify_fail';
	}

	/**
	 * Make sure the user's correct session was passed, and they came from here.
	 *
	 * Checks the current session, verifying that the person is who he or she
	 * should be.
	 *
	 * Also checks the referrer to make sure they didn't get sent here, unless
	 * the disableCheckUA setting is present and true. (It's usually missing.)
	 *
	 * Will check $_GET, $_POST, or $_REQUEST, depending on the passed $type.
	 *
	 * Also optionally checks the referring action if $from_action is passed.
	 * (Note that the referring action must be in $_GET)
	 *
	 * @param string $type The type of check (post, get, request).
	 * @param string $from_action The action this is coming from.
	 * @param bool $is_fatal Whether to die with a fatal error if the check fails.
	 * @return string The error message, or '' if everything was fine.
	 */
	public function checkSession(string $type = 'post', string $from_action = '', bool $is_fatal = true): string
	{
		// Is it in as $_POST['sc']?
		if ($type == 'post') {
			$check = $_POST[$_SESSION['session_var']] ?? (empty(Config::$modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null);

			if ($check !== User::$sc) {
				$error = 'session_timeout';
			}
		}
		// How about $_GET['sesc']?
		elseif ($type == 'get') {
			$check = $_GET[$_SESSION['session_var']] ?? (empty(Config::$modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : null);

			if ($check !== User::$sc) {
				$error = 'session_verify_fail';
			}
		}
		// Or can it be in either?
		elseif ($type == 'request') {
			$check = $_GET[$_SESSION['session_var']] ?? (empty(Config::$modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : ($_POST[$_SESSION['session_var']] ?? (empty(Config::$modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null)));

			if ($check !== User::$sc) {
				$error = 'session_verify_fail';
			}
		}

		// Verify that they aren't changing user agents on us - that could be bad.
		if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $_SERVER['HTTP_USER_AGENT']) && empty(Config::$modSettings['disableCheckUA'])) {
			$error = 'session_verify_fail';
		}

		// Make sure a page with session check requirement is not being prefetched.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
			ob_end_clean();
			Utils::sendHttpStatus(403);

			die;
		}

		// Check the referring site - it should be the same server at least!
		$referrer = $_SESSION['request_referer'] ?? (isset($_SERVER['HTTP_REFERER']) ? @Url::create($_SERVER['HTTP_REFERER'])->parse() : []);

		// Check the refer but if we have CORS enabled and it came from a trusted source, we can skip this check.
		if (
			!empty($referrer['host'])
			&& (
				empty(Config::$modSettings['allow_cors'])
				|| empty(Utils::$context['valid_cors_found'])
				|| !in_array(Utils::$context['valid_cors_found'], ['same', 'subdomain'])
			)
		) {
			if (strpos($_SERVER['HTTP_HOST'], ':') !== false) {
				$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
			} else {
				$real_host = $_SERVER['HTTP_HOST'];
			}

			$board_host = Url::create(Config::$boardurl)->host;

			// Are global cookies on?  If so, let's check them ;).
			if (!empty(Config::$modSettings['globalCookies'])) {
				if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $board_host, $parts)) {
					$board_host = $parts[1];
				}

				if (preg_match('~(?:[^.]+\.)?([^.]{3,}\.+)\z~i', $referrer['host'], $parts)) {
					$referrer['host'] = $parts[1];
				}

				if (preg_match('~(?:[^.]+\.)?([^.]{3,}\.+)\z~i', $real_host, $parts)) {
					$real_host = $parts[1];
				}
			}

			// Okay: referrer must either match parsed_url or real_host.
			if (
				isset($board_host)
				&& strtolower($referrer['host']) != strtolower($board_host)
				&& strtolower($referrer['host']) != strtolower($real_host)
			) {
				$error = 'verify_url_fail';
				$log_error = true;
			}
		}

		// Well, first of all, if a from_action is specified you'd better have an old_url.
		if (
			!empty($from_action)
			&& (
				!isset($_SESSION['old_url'])
				|| !preg_match('~[?;&]action=' . $from_action . '([;&]|$)~', $_SESSION['old_url'])
			)
		) {
			$error = 'verify_url_fail';
			$log_error = true;
		}

		if (strtolower($_SERVER['HTTP_USER_AGENT']) == 'hacker') {
			ErrorHandler::fatal('Sound the alarm!  It\'s a hacker!  Close the castle gates!!', false);
		}

		// Everything is ok, return an empty string.
		if (!isset($error)) {
			return '';
		}

		// A non-fatal session error occurred.
		// Return the error to the calling function.
		if (!$is_fatal) {
			return $error;
		}

		// A fatal session error occurred.
		// Show the error and die
		if (isset($_GET['xml'])) {
			ob_end_clean();
			Utils::sendHttpStatus(403, 'Forbidden - Session timeout');

			die;
		}

		ErrorHandler::fatalLang($error, isset($log_error) ? 'user' : false);

		// We really should never fall through here, for very important reasons.  Let's make sure.
		trigger_error('No direct access...', E_USER_ERROR);
	}

	/**
	 * Checks whether the user has a given permissions (e.g. 'post_new').
	 *
	 * If $boards is specified, checks those boards instead of the current one.
	 *
	 * If $any is true, will return true if the user has the permission on any
	 * of the specified boards
	 *
	 * Always returns true if the user is an administrator.
	 *
	 * @param string|array $permission A single permission to check or an array
	 *    of permissions to check.
	 * @param int|array $boards The ID of a board or an array of board IDs if we
	 *    want to check board-level permissions
	 * @param bool $any Whether to check for permission on at least one board
	 *    instead of all the passed boards.
	 * @return bool Whether the user has the specified permission.
	 */
	public function allowedTo(string|array $permission, int|array|null $boards = null, bool $any = false): bool
	{
		// You're always allowed to do nothing. (Unless you're a working man, MR. LAZY :P!)
		if (empty($permission)) {
			return true;
		}

		// Administrators are supermen :P.
		if ($this->is_admin) {
			return true;
		}

		// Let's ensure this is an array.
		$permission = (array) $permission;

		// Are we checking the _current_ board, or some other boards?
		if ($boards === null || $boards === []) {
			$user_permissions = (array) $this->permissions;

			// Allow temporary overrides for general permissions?
			IntegrationHook::call('integrate_allowed_to_general', [&$user_permissions, $permission]);

			return array_intersect($permission, $user_permissions) != [];
		}

		$boards = (array) $boards;

		$cache_key = hash('md5', $this->id . '-' . implode(',', $permission) . '-' . implode(',', $boards) . '-' . (int) $any);

		if (isset($this->perm_cache[$cache_key])) {
			return $this->perm_cache[$cache_key];
		}

		$request = Db::$db->query(
			'',
			'SELECT MIN(bp.add_deny) AS add_deny
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}board_permissions AS bp ON (bp.id_profile = b.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))
			WHERE b.id_board IN ({array_int:board_list})
				AND bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({array_string:permission_list})
				AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})
			GROUP BY b.id_board',
			[
				'current_member' => $this->id,
				'board_list' => $boards,
				'group_list' => $this->groups,
				'moderator_group' => 3,
				'permission_list' => $permission,
			],
		);

		if ($any) {
			$result = false;

			while ($row = Db::$db->fetch_assoc($request)) {
				$result = !empty($row['add_deny']);

				if ($result == true) {
					break;
				}
			}
			Db::$db->free_result($request);

			$return = $result;
		}
		// Make sure they can do it on all of the boards.
		elseif (Db::$db->num_rows($request) != count($boards)) {
			Db::$db->free_result($request);

			$return = false;
		} else {
			$result = true;

			while ($row = Db::$db->fetch_assoc($request)) {
				$result &= !empty($row['add_deny']);
			}
			Db::$db->free_result($request);

			$return = $result;
		}

		// Allow temporary overrides for board permissions?
		IntegrationHook::call('integrate_allowed_to_board', [&$return, $permission, $boards, $any]);

		$this->perm_cache[$cache_key] = $return;

		// If the query returned 1, they can do it... otherwise, they can't.
		return (bool) $return;
	}

	/**
	 * Checks whether the user has the given permissions, and exits with a
	 * fatal error if not.
	 *
	 * Uses allowedTo() to check if the user is allowed to do permission.
	 *
	 * Checks the passed boards or current board for the permission.
	 *
	 * If $any is true, the user only needs permission on at least one of the
	 * boards to pass.
	 *
	 * If the user is not allowed, loads the Errors language file and shows an
	 * error using Lang::$txt['cannot_' . $permission].
	 *
	 * If the user is a guest and cannot do it, calls $this->kickIfGuest().
	 *
	 * @param string|array $permission A single permission to check or an array
	 *    of permissions to check.
	 * @param int|array $boards The ID of a board or an array of board IDs if we
	 *    want to check board-level permissions
	 * @param bool $any Whether to check for permission on at least one board
	 *    instead of all the passed boards.
	 */
	public function isAllowedTo(string|array $permission, int|array|null $boards = null, bool $any = false): void
	{
		// This only applies to the current user.
		if ($this->id !== User::$my_id) {
			return;
		}

		// Make it an array, even if a string was passed.
		$permission = (array) $permission;
		$boards = (array) $boards;

		IntegrationHook::call('integrate_heavy_permissions_session', [&self::$heavy_permissions]);

		// Check the permission and return an error...
		if (!$this->allowedTo($permission, $boards, $any)) {
			// Pick the last array entry as the permission shown as the error.
			$error_permission = array_shift($permission);

			// If they are a guest, show a login. (because the error might be gone if they do!)
			if ($this->is_guest) {
				Lang::load('Errors');
				$this->kickIfGuest(Lang::$txt['cannot_' . $error_permission]);
			}

			// Clear the action because they aren't really doing that!
			$_GET['action'] = '';
			$_GET['board'] = '';
			$_GET['topic'] = '';
			$this->logOnline(true);

			ErrorHandler::fatalLang('cannot_' . $error_permission, false);

			// Getting this far is a really big problem, but let's try our best to prevent any cases...
			trigger_error('No direct access...', E_USER_ERROR);
		}

		// If you're doing something on behalf of some "heavy" permissions,
		// validate your session. (Take out the heavy permissions, and if you
		// can't do anything but those, you need a validated session.)
		if (!$this->allowedTo(array_diff($permission, self::$heavy_permissions), $boards)) {
			$this->validateSession();
		}
	}

	/**
	 * Returns a list of boards in which the user is allowed to do the
	 * specified permission.
	 *
	 * Returns an array with only a 0 in it if the user has permission to do
	 * this on every board.
	 *
	 * Returns an empty array if he or she cannot do this on any board.
	 *
	 * If $check_access is true, will also make sure the group has proper access
	 * to that board.
	 *
	 * @param string|array $permissions A single permission to check or an array
	 *    of permissions to check.
	 * @param bool $check_access Whether to check only the boards the user has
	 *    access to.
	 * @param bool $simple Whether to return a simple array of board IDs or one
	 *    with permissions as the keys.
	 * @return array An array of board IDs if $simple is true. Otherwise, an
	 *    array containing 'permission' => array(id, id, id...) pairs.
	 */
	public function boardsAllowedTo(string|array $permissions, bool $check_access = true, bool $simple = true): array
	{
		$boards = [];
		$deny_boards = [];

		// Arrays are nice, most of the time.
		$permissions = (array) $permissions;

		// Administrators are all powerful.
		if ($this->is_admin) {
			if ($simple) {
				return [0];
			}

			foreach ($permissions as $permission) {
				$boards[$permission] = [0];
			}

			return $boards;
		}

		// All groups the user is in except 'moderator'.
		$groups = array_diff($this->groups, [3]);

		$request = Db::$db->query(
			'',
			'SELECT b.id_board, bp.add_deny' . ($simple ? '' : ', bp.permission') . '
			FROM {db_prefix}board_permissions AS bp
				INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))
			WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({array_string:permissions})
				AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})' .
				($check_access ? ' AND {query_see_board}' : ''),
			[
				'current_member' => $this->id,
				'group_list' => $groups,
				'moderator_group' => 3,
				'permissions' => $permissions,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($simple) {
				if (empty($row['add_deny'])) {
					$deny_boards[] = $row['id_board'];
				} else {
					$boards[] = $row['id_board'];
				}
			} else {
				if (empty($row['add_deny'])) {
					$deny_boards[$row['permission']][] = $row['id_board'];
				} else {
					$boards[$row['permission']][] = $row['id_board'];
				}
			}
		}
		Db::$db->free_result($request);

		if ($simple) {
			$boards = array_unique(array_values(array_diff($boards, $deny_boards)));
		} else {
			foreach ($permissions as $permission) {
				// Never had it to start with.
				if (empty($boards[$permission])) {
					$boards[$permission] = [];
				} else {
					// Or it may have been removed.
					$deny_boards[$permission] = $deny_boards[$permission] ?? [];

					$boards[$permission] = array_unique(array_values(array_diff($boards[$permission], $deny_boards[$permission])));
				}
			}
		}

		// Maybe a mod needs to tweak the list of allowed boards on the fly?
		IntegrationHook::call('integrate_boards_allowed_to', [&$boards, $deny_boards, $permissions, $check_access, $simple]);

		return $boards;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads an array of users by ID, member_name, or email_address.
	 *
	 * @param mixed $users Users specified by ID, name, or email address.
	 * @param int $type Whether $users contains IDs, names, or email addresses.
	 *    Possible values are this class's LOAD_BY_* constants.
	 * @param string $dataset What kind of data to load: 'profile', 'normal',
	 *    'basic', 'minimal'. Leave null for a dynamically determined default.
	 * @return array Instances of this class for the loaded users.
	 */
	public static function load($users = [], int $type = self::LOAD_BY_ID, ?string $dataset = null): array
	{
		$users = (array) $users;

		$loaded = [];

		// No ID? Just get the current user.
		if ($users === []) {
			if (!isset(self::$me)) {
				$loaded[] = new self(null, $dataset);
			}
		} else {
			$dataset = $dataset ?? 'normal';

			// Load members.
			foreach (($loaded_ids = self::loadUserData((array) $users, $type, $dataset)) as $id) {
				// Not yet loaded.
				if (!isset(self::$loaded[$id])) {
					new self($id, $dataset);
				}
				// Already loaded, so just update the properties.
				elseif (self::$dataset_levels[self::$loaded[$id]->dataset] < self::$dataset_levels[$dataset]) {
					self::$loaded[$id]->setProperties();
				}

				$loaded[] = self::$loaded[$id];
			}
		}

		return $loaded;
	}

	/**
	 * Reloads an array of users, specified by ID number.
	 *
	 * @param mixed $users One or more users specified by ID.
	 * @param string $dataset What kind of data to load: 'profile', 'normal',
	 *    'basic', 'minimal'. Leave null for a dynamically determined default.
	 * @return array The ids of the loaded members.
	 */
	public static function reload($users = [], ?string $dataset = null): array
	{
		$users = (array) $users;

		foreach ($users as $id) {
			unset(self::$loaded[$id], self::$profiles[$id]);
		}

		return self::load($users, self::LOAD_BY_ID, $dataset);
	}

	/**
	 * Sets User::$me to the loaded object for the given user.
	 *
	 * @param int $id The ID of a user.
	 */
	public static function setMe(int $id): void
	{
		if (!isset(self::$loaded[$id])) {
			self::load([$id]);
		}

		self::$my_id = $id;
		self::$me = self::$loaded[$id];
		self::$info = self::$loaded[$id];
		self::$settings = &self::$profiles[$id];
	}

	/**
	 * Figures out which users are moderators on the current board, and sets
	 * them as such.
	 */
	public static function setModerators(): void
	{
		if (isset(Board::$info) && ($moderator_group_info = CacheApi::get('moderator_group_info', 480)) == null) {
			$request = Db::$db->query(
				'',
				'SELECT group_name, online_color, icons
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				[
					'moderator_group' => 3,
				],
			);
			$moderator_group_info = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			CacheApi::put('moderator_group_info', $moderator_group_info, 480);
		}

		foreach (self::$profiles as $id => &$profile) {
			if (empty($id)) {
				continue;
			}

			if (!isset(self::$loaded[$id])) {
				new self($id);
			}

			$user = self::$loaded[$id];

			// Global moderators.
			$profile['is_mod'] = in_array(2, $user->groups);

			// Can't do much else without a board.
			if (!isset(Board::$info)) {
				continue;
			}

			if (!empty(Board::$info->moderators)) {
				$profile['is_mod'] |= isset(Board::$info->moderators[$id]);
			}

			if (!empty(Board::$info->moderator_groups)) {
				$profile['is_mod'] |= array_intersect($user->groups, array_keys(Board::$info->moderator_groups)) !== [];
			}

			// By popular demand, don't show admins or global moderators as moderators.
			if ($profile['is_mod'] && $user->group_id != 1 && $user->group_id != 2) {
				$profile['member_group'] = $moderator_group_info['group_name'];
			}

			// If the Moderator group has no color or icons, but their group does... don't overwrite.
			if ($profile['is_mod'] && !empty($moderator_group_info['icons'])) {
				$profile['icons'] = $moderator_group_info['icons'];
			}

			if ($profile['is_mod'] && !empty($moderator_group_info['online_color'])) {
				$profile['member_group_color'] = $moderator_group_info['online_color'];
			}

			// Update object properties.
			$user->setProperties();

			// Add this user to the moderators group if they're not already an admin or moderator.
			if ($user->is_mod && array_intersect([1, 2, 3], $user->groups) === []) {
				$user->groups[] = 3;
			}
		}
	}

	/**
	 * Builds query_see_board and query_wanna_see_board (plus variants) for the
	 * given user.
	 *
	 * Returns array with keys:
	 *  - query_see_board
	 *  - query_see_message_board
	 *  - query_see_topic_board
	 *  - query_wanna_see_board
	 *  - query_wanna_see_message_board
	 *  - query_wanna_see_topic_board
	 *
	 * @param int $id The ID of the user.
	 * @return array All board query variants.
	 */
	public static function buildQueryBoard(int $id): array
	{
		$query_part = [];

		if (isset(self::$loaded[$id])) {
			$groups = self::$loaded[$id]->groups;
			$can_see_all_boards = self::$loaded[$id]->is_admin || self::$loaded[$id]->can_manage_boards;
			$ignoreboards = !empty(self::$loaded[$id]->ignoreboards) ? self::$loaded[$id]->ignoreboards : null;
		} elseif ($id === 0) {
			$groups = [-1];
			$can_see_all_boards = false;
			$ignoreboards = [];
		} else {
			$request = Db::$db->query(
				'',
				'SELECT mem.ignore_boards, mem.id_group, mem.additional_groups, mem.id_post_group
				FROM {db_prefix}members AS mem
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				[
					'id_member' => $id,
				],
			);

			$row = Db::$db->fetch_assoc($request);

			if (empty($row['additional_groups'])) {
				$groups = [$row['id_group'], $row['id_post_group']];
			} else {
				$groups = array_merge(
					[$row['id_group'], $row['id_post_group']],
					explode(',', $row['additional_groups']),
				);
			}

			// Because history has proven that it is possible for groups to go bad - clean up in case.
			$groups = array_map('intval', $groups);

			$can_see_all_boards = in_array(1, $groups) || (!empty(Config::$modSettings['board_manager_groups']) && count(array_intersect($groups, explode(',', Config::$modSettings['board_manager_groups']))) > 0);

			$ignoreboards = !empty($row['ignore_boards']) && !empty(Config::$modSettings['allow_ignore_boards']) ? explode(',', $row['ignore_boards']) : [];
		}

		// Just build this here, it makes it easier to change/use - administrators can see all boards.
		if ($can_see_all_boards) {
			$query_part['query_see_board'] = '1=1';
		}
		// Otherwise only the boards that can be accessed by the groups this user belongs to.
		else {
			$query_part['query_see_board'] = '
				EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN (' . implode(',', $groups) . ')
						AND bpv.deny = 0
						AND bpv.id_board = b.id_board
				)';

			if (!empty(Config::$modSettings['deny_boards_access'])) {
				$query_part['query_see_board'] .= '
				AND NOT EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN ( ' . implode(',', $groups) . ')
						AND bpv.deny = 1
						AND bpv.id_board = b.id_board
				)';
			}
		}

		$query_part['query_see_message_board'] = str_replace('b.', 'm.', $query_part['query_see_board']);
		$query_part['query_see_topic_board'] = str_replace('b.', 't.', $query_part['query_see_board']);

		// Build the list of boards they WANT to see.
		// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

		// If they aren't ignoring any boards then they want to see all the boards they can see
		if (empty($ignoreboards)) {
			$query_part['query_wanna_see_board'] = $query_part['query_see_board'];
			$query_part['query_wanna_see_message_board'] = $query_part['query_see_message_board'];
			$query_part['query_wanna_see_topic_board'] = $query_part['query_see_topic_board'];
		}
		// Ok I guess they don't want to see all the boards
		else {
			$query_part['query_wanna_see_board'] = '(' . $query_part['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
			$query_part['query_wanna_see_message_board'] = '(' . $query_part['query_see_message_board'] . ' AND m.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
			$query_part['query_wanna_see_topic_board'] = '(' . $query_part['query_see_topic_board'] . ' AND t.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
		}

		return $query_part;
	}

	/**
	 * Helper function to set an array of data for a user's avatar.
	 *
	 * The following keys are required:
	 *  - avatar: The raw "avatar" column in members table.
	 *  - email: The user's email address. Used to get the gravatar info.
	 *  - filename: The attachment filename.
	 *
	 * @param array $data An array of raw info.
	 * @return array An array of avatar data.
	 */
	public static function setAvatarData(array $data = []): array
	{
		// Come on!
		if (empty($data)) {
			return [];
		}

		// Set a nice default var.
		$image = '';

		// Gravatar has been set as mandatory!
		if (!empty(Config::$modSettings['gravatarEnabled']) && !empty(Config::$modSettings['gravatarOverride'])) {
			if (!empty(Config::$modSettings['gravatarAllowExtraEmail']) && !empty($data['avatar']) && stristr($data['avatar'], 'gravatar://')) {
				$image = self::getGravatarUrl(Utils::entitySubstr($data['avatar'], 11));
			} elseif (!empty($data['email'])) {
				$image = self::getGravatarUrl($data['email']);
			}
		}
		// Look if the user has a gravatar field or has set an external url as avatar.
		else {
			// So it's stored in the member table?
			if (!empty($data['avatar'])) {
				// Gravatar.
				if (stristr($data['avatar'], 'gravatar://')) {
					if ($data['avatar'] == 'gravatar://') {
						$image = self::getGravatarUrl($data['email']);
					} elseif (!empty(Config::$modSettings['gravatarAllowExtraEmail'])) {
						$image = self::getGravatarUrl(Utils::entitySubstr($data['avatar'], 11));
					}
				}
				// External url.
				else {
					$url = new Url($data['avatar']);
					$image = $url->scheme !== null ? $url->proxied() : Config::$modSettings['avatar_url'] . '/' . $data['avatar'];
				}
			}
			// Perhaps this user has an attachment as avatar...
			elseif (!empty($data['filename'])) {
				$image = Config::$modSettings['custom_avatar_url'] . '/' . $data['filename'];
			}
			// Right... no avatar... use our default image.
			else {
				$image = Config::$modSettings['avatar_url'] . '/default.png';
			}
		}

		IntegrationHook::call('integrate_set_avatar_data', [&$image, &$data]);

		// At this point in time $image has to be filled unless you chose to force gravatar and the user doesn't have the needed data to retrieve it... thus a check for !empty() is still needed.
		if (!empty($image)) {
			return [
				'name' => !empty($data['avatar']) ? $data['avatar'] : '',
				'image' => '<img class="avatar" src="' . $image . '" alt="">',
				'href' => $image,
				'url' => $image,
			];
		}

		// Fallback to make life easier for everyone...
		return [
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => '',
		];
	}

	/**
	 * Updates the columns in the members table.
	 *
	 * Assumes the data has been htmlspecialchar'd.
	 *
	 * This function should be used whenever member data needs to be
	 * updated in place of an UPDATE query.
	 *
	 * $members is either an int or an array of ints to be updated.
	 *
	 * $data is an associative array of the columns to be updated and their
	 * respective values.
	 * Any string values updated should be quoted and slashed.
	 *
	 * The value of any column can be '+' or '-', which mean 'increment'
	 * and decrement, respectively.
	 *
	 * If a member's post count is updated, this method also updates their post
	 * groups.
	 *
	 * @param mixed $members An array of member IDs, the ID of a single member,
	 *    or null to update this for all members.
	 * @param array $data The info to update for the members.
	 */
	public static function updateMemberData($members, array $data): void
	{
		// An empty array means there's nobody to update.
		if ($members === []) {
			return;
		}

		// For loaded members, update the loaded objects with the new data.
		foreach ((array) ($members ?? array_keys(User::$loaded)) as $member) {
			if (!isset(User::$loaded[$member])) {
				continue;
			}

			foreach ($data as $var => $val) {
				if ($var === 'alerts' && ($val === '+' || $val === '-')) {
					$val = Alert::count($member, true);
				} elseif (in_array($var, self::$knownInts) && ($val === '+' || $val === '-')) {
					$val = User::$loaded[$member]->{$var} + ($val === '+' ? 1 : -1);
				}

				if (in_array($var, ['posts', 'instant_messages', 'unread_messages'])) {
					$val = max(0, $val);
				}

				User::$loaded[$member]->set([$var, $val]);
			}
		}

		$parameters = [];

		if (is_array($members)) {
			$condition = 'id_member IN ({array_int:members})';
			$parameters['members'] = $members;
		} elseif ($members === null) {
			$condition = '1=1';
		} else {
			$condition = 'id_member = {int:member}';
			$parameters['member'] = $members;
		}

		if (!empty(Config::$modSettings['integrate_change_member_data'])) {
			$vars_to_integrate = array_intersect(self::$integration_vars, array_keys($data));

			// Only proceed if there are any variables left to call the integration function.
			if (count($vars_to_integrate) != 0) {
				// Fetch a list of member_names if necessary
				if ((!is_array($members) && $members === self::$me->id) || (is_array($members) && count($members) == 1 && in_array(self::$me->id, $members))) {
					$member_names = [self::$me->username];
				} else {
					$member_names = [];

					$request = Db::$db->query(
						'',
						'SELECT member_name
						FROM {db_prefix}members
						WHERE ' . $condition,
						$parameters,
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						$member_names[] = $row['member_name'];
					}
					Db::$db->free_result($request);
				}

				if (!empty($member_names)) {
					foreach ($vars_to_integrate as $var) {
						IntegrationHook::call('integrate_change_member_data', [$member_names, $var, &$data[$var], &self::$knownInts, &self::$knownFloats]);
					}
				}
			}
		}

		$setString = '';

		foreach ($data as $var => $val) {
			switch ($var) {
				case  'birthdate':
					$type = 'date';
					break;

				case 'member_ip':
				case 'member_ip2':
					$type = 'inet';
					break;

				default:
					$type = 'string';
					break;
			}

			if (in_array($var, self::$knownInts)) {
				$type = 'int';
			} elseif (in_array($var, self::$knownFloats)) {
				$type = 'float';
			}

			// Doing an increment?
			if ($var == 'alerts' && ($val === '+' || $val === '-')) {
				if (is_array($members)) {
					$val = 'CASE ';

					foreach ($members as $k => $v) {
						$val .= 'WHEN id_member = ' . $v . ' THEN ' . Alert::count($v, true) . ' ';
					}

					$val = $val . ' END';

					$type = 'raw';
				} else {
					$val = Alert::count($members, true);
				}
			} elseif ($type == 'int' && ($val === '+' || $val === '-')) {
				$val = $var . ' ' . $val . ' 1';
				$type = 'raw';
			}

			// Ensure posts, instant_messages, and unread_messages don't overflow or underflow.
			if (in_array($var, ['posts', 'instant_messages', 'unread_messages'])) {
				if (preg_match('~^' . $var . ' (\+ |- |\+ -)(\d+)~', $val, $match)) {
					if ($match[1] != '+ ') {
						$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
					}

					$type = 'raw';
				}
			}

			$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
			$parameters['p_' . $var] = $val;
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}members
			SET' . substr($setString, 0, -1) . '
			WHERE ' . $condition,
			$parameters,
		);

		Logging::updateStats('postgroups', $members, array_keys($data));

		// Clear any caching?
		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2 && !empty($members)) {
			if (!is_array($members)) {
				$members = [$members];
			}

			foreach ($members as $member) {
				if (CacheApi::$enable >= 3) {
					CacheApi::put('member_data-profile-' . $member, null, 120);
					CacheApi::put('member_data-normal-' . $member, null, 120);
					CacheApi::put('member_data-basic-' . $member, null, 120);
					CacheApi::put('member_data-minimal-' . $member, null, 120);
				}

				CacheApi::put('user_settings-' . $member, null, 60);
			}
		}
	}

	/**
	 * Gets a member's selected time zone identifier
	 *
	 * @param int $id_member The member id to look up. If not provided, the current user's id will be used.
	 * @return string The time zone identifier string for the user's time zone.
	 */
	public static function getTimezone(?int $id_member = null): string
	{
		static $member_cache = [];

		if (is_null($id_member)) {
			$id_member = empty(self::$me->id) ? 0 : self::$me->id;
		} else {
			$id_member = (int) $id_member;
		}

		// Check if we already have this in self::$loaded.
		if (isset(self::$loaded[$id_member]) && !empty(self::$loaded[$id_member]->timezone)) {
			return self::$loaded[$id_member]->timezone;
		}

		// Did we already look this up?
		if (isset($member_cache[$id_member])) {
			return $member_cache[$id_member];
		}

		if (!empty($id_member)) {
			// Look it up in the database.
			$request = Db::$db->query(
				'',
				'SELECT timezone
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}',
				[
					'id_member' => $id_member,
				],
			);
			list($timezone) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// If it is invalid, fall back to the default.
		if (empty($timezone) || !in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
			$timezone = Config::$modSettings['default_timezone'] ?? date_default_timezone_get();
		}

		// Save for later.
		$member_cache[$id_member] = $timezone;

		return $timezone;
	}

	/**
	 * Delete one or more members.
	 *
	 * Requires profile_remove_own or profile_remove_any permission for
	 * respectively removing your own account or any account.
	 * Non-admins cannot delete admins.
	 * The function:
	 *   - changes author of messages, topics and polls to guest authors.
	 *   - removes all log entries concerning the deleted members, except the
	 *     error logs, ban logs and moderation logs.
	 *   - removes these members' personal messages (only the inbox), avatars,
	 *     ban entries, theme settings, moderator positions, poll and votes.
	 *   - updates member statistics afterwards.
	 *
	 * @param int|array $users The ID of a user or an array of user IDs
	 * @param bool $check_not_admin Whether to verify that the users aren't admins
	 */
	public static function delete(int|array $users, bool $check_not_admin = false): void
	{
		// Try give us a while to sort this out...
		@set_time_limit(600);

		// Try to get some more memory.
		Config::setMemoryLimit('128M');

		// If it's not an array, make it so!
		$users = array_unique((array) $users);

		// Make sure there's no void user in here.
		$users = array_diff($users, [0]);

		// How many are they deleting?
		if (empty($users)) {
			return;
		}

		if (count($users) == 1) {
			list($user) = $users;

			if ($user == self::$me->id) {
				self::$me->isAllowedTo('profile_remove_own');
			} else {
				self::$me->isAllowedTo('profile_remove_any');
			}
		} else {
			foreach ($users as $k => $v) {
				$users[$k] = (int) $v;
			}

			// Deleting more than one?  You can't have more than one account...
			self::$me->isAllowedTo('profile_remove_any');
		}

		// Get their names for logging purposes.
		$admins = [];
		$user_log_details = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:user_list})
			LIMIT {int:limit}',
			[
				'user_list' => $users,
				'admin_group' => 1,
				'limit' => count($users),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['is_admin']) {
				$admins[] = $row['id_member'];
			}

			$user_log_details[$row['id_member']] = [$row['id_member'], $row['member_name']];
		}
		Db::$db->free_result($request);

		if (empty($user_log_details)) {
			return;
		}

		// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
		if (!empty($admins) && ($check_not_admin || (!self::$me->allowedTo('admin_forum') && (count($users) != 1 || $users[0] != self::$me->id)))) {
			$users = array_diff($users, $admins);

			foreach ($admins as $id) {
				unset($user_log_details[$id]);
			}
		}

		// No one left?
		if (empty($users)) {
			return;
		}

		// Log the action - regardless of who is deleting it.
		$log_changes = [];

		foreach ($user_log_details as $user) {
			$log_changes[] = [
				'action' => 'delete_member',
				'log_type' => 'admin',
				'extra' => [
					'member' => $user[0],
					'name' => $user[1],
					'member_acted' => self::$me->name,
				],
			];

			// Remove any cached data if enabled.
			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('user_settings-' . $user[0], null, 60);
			}
		}

		// Make these peoples' posts guest posts.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_member = {int:guest_id}' . (!empty(Config::$modSettings['deleteMembersRemovesEmail']) ? ',
				poster_email = {string:blank_email}' : '') . '
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'blank_email' => '',
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}polls
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		// Make these peoples' posts guest first posts and last posts.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_member_started = {int:guest_id}
			WHERE id_member_started IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_member_updated = {int:guest_id}
			WHERE id_member_updated IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_actions
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_banned
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_errors
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		// Delete the member.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}members
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Delete any drafts...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_drafts
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Delete anything they liked.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_likes
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Delete their mentions
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}mentions
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $users,
			],
		);

		// Delete the logs...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:log_type}
				AND id_member IN ({array_int:users})',
			[
				'log_type' => 2,
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_boards
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_comments
			WHERE id_recipient IN ({array_int:users})
				AND comment_type = {string:warntpl}',
			[
				'users' => $users,
				'warntpl' => 'warntpl',
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_group_requests
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_mark_read
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_notify
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_online
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_subscribed
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Make their votes appear as guest votes - at least it keeps the totals right.
		// @todo Consider adding back in cookie protection.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_polls
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		// Delete personal messages.
		PM::delete(null, null, $users);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}personal_messages
			SET id_member_from = {int:guest_id}
			WHERE id_member_from IN ({array_int:users})',
			[
				'guest_id' => 0,
				'users' => $users,
			],
		);

		// They no longer exist, so we don't know who it was sent to.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Delete avatar.
		Attachment::remove(['id_member' => $users]);

		// It's over, no more moderation for you.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderators
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}group_moderators
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// If you don't exist we can't ban you.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}ban_items
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// Remove individual theme settings.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}themes
			WHERE id_member IN ({array_int:users})',
			[
				'users' => $users,
			],
		);

		// These users are nobody's buddy nomore.
		$request = Db::$db->query(
			'',
			'SELECT id_member, pm_ignore_list, buddy_list
			FROM {db_prefix}members
			WHERE FIND_IN_SET({raw:pm_ignore_list}, pm_ignore_list) != 0 OR FIND_IN_SET({raw:buddy_list}, buddy_list) != 0',
			[
				'pm_ignore_list' => implode(', pm_ignore_list) != 0 OR FIND_IN_SET(', $users),
				'buddy_list' => implode(', buddy_list) != 0 OR FIND_IN_SET(', $users),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET
					pm_ignore_list = {string:pm_ignore_list},
					buddy_list = {string:buddy_list}
				WHERE id_member = {int:id_member}',
				[
					'id_member' => $row['id_member'],
					'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), $users)),
					'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), $users)),
				],
			);
		}
		Db::$db->free_result($request);

		// Make sure no member's birthday is still sticking in the calendar...
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		// Integration rocks!
		IntegrationHook::call('integrate_delete_members', [$users]);

		Logging::updateStats('member');

		Logging::logActions($log_changes);
	}

	/**
	 * Checks whether a password meets the current forum rules.
	 *
	 * Called when registering and when choosing a new password in the profile.
	 *
	 * If password checking is enabled, will check that none of the words in
	 * $restrict_in appear in the password.
	 *
	 * Returns an error identifier if the password is invalid, or null if valid.
	 *
	 * @param string $password The desired password.
	 * @param string $username The username.
	 * @param array $restrict_in An array of restricted strings that cannot be
	 *    part of the password (email address, username, etc.)
	 * @return null|string Null if valid or a string indicating the problem.
	 */
	public static function validatePassword(string $password, string $username, array $restrict_in = []): ?string
	{
		// Perform basic requirements first.
		if (Utils::entityStrlen($password) < (empty(Config::$modSettings['password_strength']) ? 4 : 8)) {
			return 'short';
		}

		// Maybe we need some more fancy password checks.
		$pass_error = '';

		IntegrationHook::call('integrate_validatePassword', [$password, $username, $restrict_in, &$pass_error]);

		if (!empty($pass_error)) {
			return $pass_error;
		}

		// Is this enough?
		if (empty(Config::$modSettings['password_strength'])) {
			return null;
		}

		// Otherwise, perform the medium strength test - checking if password appears in the restricted string.
		if (!preg_match('~\b' . preg_quote($password, '~') . '\b~', implode(' ', $restrict_in))) {
			return 'restricted_words';
		}

		if (Utils::entityStrpos($password, $username) !== false) {
			return 'restricted_words';
		}

		// If just medium, we're done.
		if (Config::$modSettings['password_strength'] == 1) {
			return null;
		}

		// Check for both numbers and letters.
		$good = preg_match('~\p{N}~u', $password) && preg_match('~\p{L}~u', $password);

		// If there are any letters from bicameral scripts (Latin, Greek, etc.),
		// check that there are both lowercase and uppercase letters present.
		// Note: If the password only contains letters from a unicameral script
		// (Arabic, Thai, etc.), this requirement is not applicable.
		if (Utils::strtoupper($password) !== ($lower_password = Utils::strtolower($password))) {
			$good &= $password !== $lower_password;
		}

		return $good ? null : 'chars';
	}

	/**
	 * Checks whether a username obeys a load of rules.
	 *
	 * @param string $username The username to validate.
	 * @param bool $return_error Whether to return errors.
	 * @param bool $check_reserved_name Whether to check this against the list
	 *    of reserved names.
	 * @return array|null Null if there are no errors, otherwise an array of
	 *    errors if $return_error is true.
	 */
	public static function validateUsername(int $memID, string $username, bool $return_error = false, bool $check_reserved_name = true): ?array
	{
		$errors = [];

		// Don't use too long a name.
		if (Utils::entityStrlen($username) > 25) {
			$errors[] = ['lang', 'error_long_name'];
		}

		// No name?!  How can you register with no name?
		if ($username == '') {
			$errors[] = ['lang', 'need_username'];
		}

		// Only these characters are permitted.
		if (
			in_array($username, ['_', '|'])
			|| strpos($username, '[code') !== false
			|| strpos($username, '[/code') !== false
			|| preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username))
		) {
			$errors[] = ['lang', 'error_invalid_characters_username'];
		}

		if (stristr($username, Lang::$txt['guest_title']) !== false) {
			$errors[] = ['lang', 'username_reserved', 'general', [Lang::$txt['guest_title']]];
		}

		if ($check_reserved_name && User::isReservedName($username, $memID, false)) {
			$errors[] = ['done', '(' . Utils::htmlspecialchars($username) . ') ' . Lang::$txt['name_in_use']];
		}

		// Maybe a mod wants to perform more checks?
		IntegrationHook::call('integrate_validate_username', [$username, &$errors]);

		if ($return_error) {
			return $errors;
		}

		if (empty($errors)) {
			return null;
		}

		Lang::load('Errors');
		$error = $errors[0];

		$message = $error[0] == 'lang' ? (empty($error[3]) ? Lang::$txt[$error[1]] : vsprintf(Lang::$txt[$error[1]], (array) $error[3])) : $error[1];

		ErrorHandler::fatal($message, empty($error[2]) || self::$me->is_admin ? false : $error[2]);
	}

	/**
	 * Check if a name is in the reserved words list.
	 * (name, current member id, name/username?.)
	 * - checks if name is a reserved name or username.
	 * - if is_name is false, the name is assumed to be a username.
	 * - the id_member variable is used to ignore duplicate matches with the
	 * current member.
	 *
	 * @param string $name The name to check
	 * @param int $current_id_member The ID of the current member (to avoid false positives with the current member)
	 * @param bool $is_name Whether we're checking against reserved names or just usernames
	 * @param bool $fatal Whether to die with a fatal error if the name is reserved
	 * @return bool False if name is not reserved, otherwise true if $fatal is false or dies with a fatal_lang_error if $fatal is true
	 */
	public static function isReservedName(string $name, int $current_id_member = 0, bool $is_name = true, bool $fatal = true): bool
	{
		$name = Utils::entityDecode($name, true);
		$checkName = Utils::strtolower($name);

		// Administrators are never restricted ;).
		if (!self::$me->allowedTo('moderate_forum') && ((!empty(Config::$modSettings['reserveName']) && $is_name) || !empty(Config::$modSettings['reserveUser']) && !$is_name)) {
			$reservedNames = explode("\n", Config::$modSettings['reserveNames']);

			// Case sensitive check?
			$checkMe = empty(Config::$modSettings['reserveCase']) ? $checkName : $name;

			// Check each name in the list...
			foreach ($reservedNames as $reserved) {
				if ($reserved == '') {
					continue;
				}

				// The admin might've used entities too, level the playing field.
				$reservedCheck = Utils::entityDecode($reserved, true);

				// Case sensitive name?
				if (empty(Config::$modSettings['reserveCase'])) {
					$reservedCheck = Utils::strtolower($reservedCheck);
				}

				// If it's not just entire word, check for it in there somewhere...
				if ($checkMe == $reservedCheck || (Utils::entityStrpos($checkMe, $reservedCheck) !== false && empty(Config::$modSettings['reserveWord']))) {
					if ($fatal) {
						ErrorHandler::fatalLang('username_reserved', 'password', [$reserved]);
					}

					return true;
				}
			}

			$censor_name = $name;

			if (Lang::censorText($censor_name) != $name) {
				if ($fatal) {
					ErrorHandler::fatalLang('name_censored', 'password', [$name]);
				}

				return true;
			}
		}

		// Characters we just shouldn't allow, regardless.
		foreach (['*'] as $char) {
			if (strpos($checkName, $char) !== false) {
				if ($fatal) {
					ErrorHandler::fatalLang('username_reserved', 'password', [$char]);
				}

				return true;
			}
		}

		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}members
			WHERE ' . (empty($current_id_member) ? '' : 'id_member != {int:current_member}
				AND ') . '({raw:real_name} {raw:operator} LOWER({string:check_name}) OR {raw:member_name} {raw:operator} LOWER({string:check_name}))
			LIMIT 1',
			[
				'real_name' => Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name',
				'member_name' => Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name',
				'current_member' => $current_id_member,
				'check_name' => $checkName,
				'operator' => strpos($checkName, '%') || strpos($checkName, '_') ? 'LIKE' : '=',
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			Db::$db->free_result($request);

			return true;
		}
		Db::$db->free_result($request);

		// Does name case insensitive match a member group name?
		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}membergroups
			WHERE {raw:group_name} LIKE {string:check_name}
			LIMIT 1',
			[
				'group_name' => Db::$db->case_sensitive ? 'LOWER(group_name)' : 'group_name',
				'check_name' => $checkName,
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			Db::$db->free_result($request);

			return true;
		}
		Db::$db->free_result($request);

		// Okay, they passed.
		$is_reserved = false;

		// Maybe a mod wants to perform further checks?
		IntegrationHook::call('integrate_check_name', [$checkName, &$is_reserved, $current_id_member, $is_name]);

		return $is_reserved;
	}

	/**
	 * Checks whether a given email address is be banned.
	 * Performs an immediate ban if the check turns out positive.
	 *
	 * @param string $email The email to check.
	 * @param string $restriction What type of restriction to check for.
	 *    E.g.: cannot_post, cannot_register, etc.
	 * @param string $error The error message to display if they are banned.
	 */
	public static function isBannedEmail(string $email, string $restriction, string $error): void
	{
		// Can't ban an empty email
		if (empty($email) || trim($email) == '') {
			return;
		}

		// Let's start with the bans based on your IP/hostname/memberID...
		$ban_ids = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['ids'] : [];
		$ban_reason = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['reason'] : '';

		// ...and add to that the email address you're trying to register.
		$request = Db::$db->query(
			'',
			'SELECT bi.id_ban, bg.' . $restriction . ', bg.cannot_access, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE {string:email} LIKE bi.email_address
				AND (bg.' . $restriction . ' = {int:cannot_access} OR bg.cannot_access = {int:cannot_access})
				AND (bg.expire_time IS NULL OR bg.expire_time >= {int:now})',
			[
				'email' => $email,
				'cannot_access' => 1,
				'now' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!empty($row['cannot_access'])) {
				$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
				$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
			}

			if (!empty($row[$restriction])) {
				$ban_ids[] = $row['id_ban'];
				$ban_reason = $row['reason'];
			}
		}
		Db::$db->free_result($request);

		// You're in biiig trouble.  Banned for the rest of this session!
		if (isset($_SESSION['ban']['cannot_access'])) {
			self::$me->logBan($_SESSION['ban']['cannot_access']['ids']);

			$_SESSION['ban']['last_checked'] = time();

			ErrorHandler::fatal(sprintf(Lang::$txt['your_ban'], Lang::$txt['guest_title']) . $_SESSION['ban']['cannot_access']['reason'], false);
		}

		if (!empty($ban_ids)) {
			// Log this ban for future reference.
			self::$me->logBan($ban_ids, $email);

			ErrorHandler::fatal($error . $ban_reason, false);
		}
	}

	/**
	 * Finds members by email address, username, or real name.
	 *
	 * Searches for members whose username, display name, or e-mail address
	 * match the given pattern of array names.
	 *
	 * Searches only buddies if $buddies_only is set.
	 *
	 * @param array $names The names of members to search for.
	 * @param bool $use_wildcards Whether to use wildcards. Accepts wildcards
	 *    '?' and '*' in the pattern if true.
	 * @param bool $buddies_only Whether to only search for the user's buddies.
	 * @param int $max The maximum number of results.
	 * @return array Information about the matching members.
	 */
	public static function find($names, bool $use_wildcards = false, bool $buddies_only = false, int $max = 500): array
	{

		// If it's not already an array, make it one.
		if (!is_array($names)) {
			$names = explode(',', $names);
		}

		$maybe_email = false;
		$names_list = [];

		foreach (array_values($names) as $i => $name) {
			// Trim, and fix wildcards for each name.
			$names[$i] = trim(Utils::strtolower($name));

			$maybe_email |= strpos($name, '@') !== false;

			// Make it so standard wildcards will work. (* and ?)
			if ($use_wildcards) {
				$names[$i] = strtr($names[$i], ['%' => '\\%', '_' => '\\_', '*' => '%', '?' => '_', '\'' => '&#039;']);
			} else {
				$names[$i] = strtr($names[$i], ['\'' => '&#039;']);
			}

			$names_list[] = '{string:lookup_name_' . $i . '}';
			$where_params['lookup_name_' . $i] = $names[$i];
		}

		// What are we using to compare?
		$comparison = $use_wildcards ? 'LIKE' : '=';

		// Nothing found yet.
		$results = [];

		// This ensures you can't search someones email address if you can't see it.
		if (($use_wildcards || $maybe_email) && self::$me->allowedTo('moderate_forum')) {
			$email_condition = '
				OR (email_address ' . $comparison . ' \'' . implode('\') OR (email_address ' . $comparison . ' \'', $names) . '\')';
		} else {
			$email_condition = '';
		}

		// Get the case of the columns right - but only if we need to as things like MySQL will go slow needlessly otherwise.
		$member_name = Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name';
		$real_name = Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name';

		// Searches.
		$member_name_search = $member_name . ' ' . $comparison . ' ' . implode(' OR ' . $member_name . ' ' . $comparison . ' ', $names_list);

		$real_name_search = $real_name . ' ' . $comparison . ' ' . implode(' OR ' . $real_name . ' ' . $comparison . ' ', $names_list);

		// Search by username, display name, and email address.
		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, real_name, email_address
			FROM {db_prefix}members
			WHERE (' . $member_name_search . '
				OR ' . $real_name_search . ' ' . $email_condition . ')
				' . ($buddies_only ? 'AND id_member IN ({array_int:buddy_list})' : '') . '
				AND is_activated IN (1, 11)
			LIMIT {int:limit}',
			array_merge($where_params, [
				'buddy_list' => self::$me->buddies,
				'limit' => $max,
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$results[$row['id_member']] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'username' => $row['member_name'],
				'email' => self::$me->allowedTo('moderate_forum') ? $row['email_address'] : '',
				'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		// Return all the results.
		return $results;
	}

	/**
	 * Retrieves a list of members that have a given permission,
	 * either on a given board or in general.
	 *
	 * If $board_id is set, a board permission is assumed.
	 * Takes different permission settings into account.
	 * Takes possible moderators on the relevant board into account.
	 *
	 * @param string $permission The permission to check.
	 * @param int $board_id If set, checks permission for that specific board.
	 * @return array IDs of the members who have that permission.
	 */
	public static function membersAllowedTo(string $permission, ?int $board_id = null): array
	{
		$members = [];

		$member_groups = self::groupsAllowedTo($permission, $board_id);

		$all_groups = array_merge($member_groups['allowed'], $member_groups['denied']);

		$include_moderators = in_array(3, $member_groups['allowed']) && $board_id !== null;
		$member_groups['allowed'] = array_diff($member_groups['allowed'], [3]);

		$exclude_moderators = in_array(3, $member_groups['denied']) && $board_id !== null;
		$member_groups['denied'] = array_diff($member_groups['denied'], [3]);

		$request = Db::$db->query(
			'',
			'SELECT mem.id_member
			FROM {db_prefix}members AS mem' . ($include_moderators || $exclude_moderators ? '
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member AND mods.id_board = {int:board_id})' : '') . '
			WHERE (' . ($include_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_allowed}) OR FIND_IN_SET({raw:member_group_allowed_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_allowed}))' . (empty($member_groups['denied']) ? '' : '
				AND NOT (' . ($exclude_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_denied}) OR FIND_IN_SET({raw:member_group_denied_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_denied}))'),
			[
				'member_groups_allowed' => $member_groups['allowed'],
				'member_groups_denied' => $member_groups['denied'],
				'all_member_groups' => $all_groups,
				'board_id' => $board_id,
				'member_group_allowed_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['allowed']),
				'member_group_denied_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['denied']),
			],
		);

		// We only want the member IDs, not id_member
		$members = array_values(Db::$db->fetch_all($request));
		Db::$db->free_result($request);

		return $members;
	}

	/**
	 * Retrieves a list of membergroups that have the given permission(s),
	 * either on a given board or in general.
	 *
	 * If $board_id is set, a board permission is assumed.
	 *
	 * @param array|string $permissions The permission(s) to check.
	 * @param int $board_id If set, checks permissions for the specified board.
	 * @param bool $simple If true, and $permission contains a single permission
	 *    to check, the returned array will contain only the relevant sub-array
	 *    for that permission. Default: true.
	 * @param int $profile_id The permission profile for the board.
	 *    If not set, will be looked up automatically.
	 * @return array Multidimensional array where each key is a permission name
	 *    and each value is an array containing to sub-arrays: 'allowed', which
	 *    lists the groups that have the permission, and 'denied', which lists
	 *    the groups that are denied the permission. However, if $simple is true
	 *    and only one permission was asked for, the returned value will contain
	 *    only the relevant sub-array for that permission.
	 */
	public static function groupsAllowedTo(array|string $permissions, ?int $board_id = null, bool $simple = true, ?int $profile_id = null): array
	{
		$permissions = (array) $permissions;

		$group_permissions = [];
		$board_permissions = [];

		foreach ($permissions as $permission) {
			// Admins are allowed to do anything.
			$member_groups[$permission] = [
				'allowed' => [1],
				'denied' => [],
			];
		}

		// No board means we're dealing with general permissions.
		if (!isset($board_id)) {
			$request = Db::$db->query(
				'',
				'SELECT id_group, permission, add_deny
				FROM {db_prefix}permissions
				WHERE permission IN ({array_string:permissions})',
				[
					'permissions' => $permissions,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$group_permissions[] = $row['permission'];

				$member_groups[$row['permission']][$row['add_deny'] ? 'allowed' : 'denied'][] = $row['id_group'];
			}
			Db::$db->free_result($request);

			$group_permissions = array_unique($group_permissions);
		}

		// If given a board, we need its permission profile.
		if (!isset($profile_id) && isset($board_id)) {
			$board_id = (int) $board_id;

			// First get the profile of the given board.
			if (isset(Board::$info->id) && Board::$info->id == $board_id) {
				$profile_id = Board::$info->profile;
			} elseif ($board_id !== 0) {
				$request = Db::$db->query(
					'',
					'SELECT id_profile
					FROM {db_prefix}boards
					WHERE id_board = {int:id_board}
					LIMIT 1',
					[
						'id_board' => $board_id,
					],
				);

				if (Db::$db->num_rows($request) == 0) {
					Db::$db->free_result($request);
					ErrorHandler::fatalLang('no_board');
				}
				list($profile_id) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			} else {
				$profile_id = 1;
			}
		}

		if (isset($profile_id)) {
			$request = Db::$db->query(
				'',
				'SELECT id_group, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE permission IN ({array_string:permissions})
					AND id_profile = {int:profile_id}',
				[
					'profile_id' => $profile_id,
					'permissions' => $permissions,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$board_permissions[] = $row['permission'];

				$member_groups[$row['permission']][$row['add_deny'] ? 'allowed' : 'denied'][] = $row['id_group'];
			}
			Db::$db->free_result($request);

			$board_permissions = array_unique($board_permissions);

			// Inherit any moderator permissions as needed.
			$moderator_groups = [];

			if (isset(Board::$info->id, Board::$info->moderator_groups) && $board_id == Board::$info->id) {
				$moderator_groups = array_keys(Board::$info->moderator_groups);
			} elseif (isset($board_id) && $board_id !== 0) {
				// Get the groups that can moderate this board
				$request = Db::$db->query(
					'',
					'SELECT id_group
					FROM {db_prefix}moderator_groups
					WHERE id_board = {int:board_id}',
					[
						'board_id' => $board_id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$moderator_groups[] = $row['id_group'];
				}
				Db::$db->free_result($request);
			}

			// Inherit any additional permissions from the moderators group.
			foreach ($moderator_groups as $mod_group) {
				foreach ($board_permissions as $permission) {
					// If they're not specifically allowed, but the moderator group is,
					// then allow it.
					if (in_array(3, $member_groups[$permission]['allowed']) && !in_array($mod_group, $member_groups[$permission]['allowed'])) {
						$member_groups[$permission]['allowed'][] = $mod_group;
					}

					// They're not denied, but the moderator group is, so deny it.
					if (in_array(3, $member_groups[$permission]['denied']) && !in_array($mod_group, $member_groups[$permission]['denied'])) {
						$member_groups[$permission]['denied'][] = $mod_group;
					}
				}
			}
		}

		// Finalize the data.
		foreach ($permissions as $permission) {
			foreach (['allowed', 'denied'] as $k) {
				$member_groups[$permission][$k] = array_unique($member_groups[$permission][$k]);
			}

			// Maybe a mod needs to tweak the list of allowed groups on the fly?
			IntegrationHook::call('integrate_groups_allowed_to', [&$member_groups[$permission], $permission, $board_id]);

			// Denied is never allowed.
			$member_groups[$permission]['allowed'] = array_diff($member_groups[$permission]['allowed'], $member_groups[$permission]['denied']);
		}

		if ($simple && count($member_groups) === 1) {
			return reset($member_groups);
		}

		return $member_groups;
	}

	/**
	 * Similar to self::groupsAllowedTo, except that:
	 *
	 * 1. It allows looking up any arbitrary combination of general permissions
	 *    and board permissions in one call.
	 *
	 * 2. When looking up board permissions, the ID of a permission profile must
	 *    be provided, rather than the ID of a board.
	 *
	 * 3. There is no $simple option.
	 *
	 * @param array $general_permissions The general permissions to check.
	 * @param array $board_permissions The board permissions to check.
	 * @param int $profile_id The permission profile for the board permissions.
	 *    Default: 1
	 * @return array Multidimensional array where each key is a permission name
	 *    and each value is an array containing to sub-arrays: 'allowed', which
	 *    lists the groups that have the permission, and 'denied', which lists
	 *    the groups that are denied the permission.
	 */
	public static function getGroupsWithPermissions(array $general_permissions = [], array $board_permissions = [], int $profile_id = 1)
	{
		$member_groups = [];

		if (!empty($general_permissions)) {
			$member_groups = self::groupsAllowedTo($general_permissions, null, false);
		}

		if (!empty($board_permissions)) {
			$member_groups = array_merge($member_groups, self::groupsAllowedTo($board_permissions, null, false, $profile_id));
		}

		return $member_groups;
	}

	/**
	 * Generate a random validation code.
	 *
	 * @return string A random validation code
	 */
	public static function generateValidationCode(): string
	{
		return bin2hex(random_bytes(5));
	}

	/**
	 * Log the spider presence online.
	 */
	public static function logSpider(): void
	{
		if (empty(Config::$modSettings['spider_mode']) || empty($_SESSION['id_robot'])) {
			return;
		}

		// Attempt to update today's entry.
		if (Config::$modSettings['spider_mode'] == 1) {
			$date = Time::strftime('%Y-%m-%d', time());
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_spider_stats
				SET last_seen = {int:current_time}, page_hits = page_hits + 1
				WHERE id_spider = {int:current_spider}
					AND stat_date = {date:current_date}',
				[
					'current_date' => $date,
					'current_time' => time(),
					'current_spider' => $_SESSION['id_robot'],
				],
			);

			// Nothing updated?
			if (Db::$db->affected_rows() == 0) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}log_spider_stats',
					[
						'id_spider' => 'int', 'last_seen' => 'int', 'stat_date' => 'date', 'page_hits' => 'int',
					],
					[
						$_SESSION['id_robot'], time(), $date, 1,
					],
					['id_spider', 'stat_date'],
				);
			}
		}
		// If we're tracking better stats than track, better stats - we sort out the today thing later.
		else {
			if (Config::$modSettings['spider_mode'] > 2) {
				$url = $_GET + ['USER_AGENT' => $_SERVER['HTTP_USER_AGENT']];
				unset($url['sesc'], $url[Utils::$context['session_var']]);
				$url = Utils::jsonEncode($url);
			} else {
				$url = '';
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}log_spider_hits',
				['id_spider' => 'int', 'log_time' => 'int', 'url' => 'string'],
				[$_SESSION['id_robot'], time(), $url],
				[],
			);
		}
	}

	/**
	 * Wrapper around User::load() that returns the IDs of the loaded users.
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param mixed $users Users specified by ID, name, or email address.
	 * @param int $type Whether $users contains IDs, names, or email addresses.
	 *    Possible values are this class's LOAD_BY_* constants.
	 * @param string $dataset What kind of data to load: 'profile', 'normal',
	 *    'basic', 'minimal'. Leave null for a dynamically determined default.
	 * @return array The IDs of the loaded members.
	 */
	public static function loadMemberData($users = [], int $type = self::LOAD_BY_ID, ?string $dataset = null): array
	{
		$loaded = self::load($users, $type, $dataset);

		return array_map(fn ($user) => $user->id, $loaded);
	}

	/**
	 * Alias of User::load().
	 *
	 * This method exists only for backward compatibility purposes.
	 */
	public static function loadUserSettings(): void
	{
		self::load();
	}

	/**
	 * Static wrapper around User::$me->loadPermissions.
	 *
	 * This method exists only for backward compatibility purposes.
	 */
	public static function loadMyPermissions(): void
	{
		self::$me->loadPermissions();
	}

	/**
	 * Static wrapper around User::$loaded[$id]->format().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param int $id The ID of a user.
	 * @param bool $display_custom_fields Whether or not to display custom
	 *    profile fields.
	 * @return bool|array The loaded data, or false on error.
	 */
	public static function loadMemberContext(int $id, bool $display_custom_fields = false): bool|array
	{
		// The old procedural version of this function returned false if asked
		// to work on a guest. Since it is possible that old mods might rely on
		// that behaviour, we replicate it here.
		if (empty($id)) {
			return false;
		}

		// If the user's data is not already loaded, load it now.
		if (!isset(self::$loaded[$id])) {
			self::load((array) $id, self::LOAD_BY_ID, 'profile');
		}

		return self::$loaded[$id]->format($display_custom_fields);
	}

	/**
	 * Static wrapper around User::$me->kickIfGuest().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string $message The message to display to the guest.
	 */
	public static function is_not_guest(string $message = ''): void
	{
		self::$me->kickIfGuest($message);
	}

	/**
	 * Static wrapper around User::$me->kickIfBanned().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param bool $force_check Whether to force a recheck.
	 */
	public static function is_not_banned(bool $force_check = false): void
	{
		self::$me->kickIfBanned($force_check);
	}

	/**
	 * Static wrapper around User::$me->adjustPermissions().
	 *
	 * This method exists only for backward compatibility purposes.
	 */
	public static function banPermissions(): void
	{
		self::$me->adjustPermissions();
	}

	/**
	 * Static wrapper around User::$me->logBan().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param array $ban_ids The IDs of the bans.
	 * @param string $email The email address associated with the user that
	 *    triggered this hit. If not set, use the current user's email address.
	 */
	public static function log_ban(array $ban_ids = [], ?string $email = null): void
	{
		self::$me->logBan($ban_ids, $email);
	}

	/**
	 * Static wrapper around User::$me->validateSession().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string $type What type of session this is.
	 * @param bool $force If true, require a password even if we normally wouldn't.
	 * @return string|null Returns 'session_verify_fail' if verification failed,
	 *    or null if it passed.
	 */
	public static function sessionValidate(string $type = 'admin', bool $force = false): ?string
	{
		return self::$me->validateSession($type, $force);
	}

	/**
	 * Static wrapper around User::$me->checkSession().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string $type The type of check (post, get, request).
	 * @param string $from_action The action this is coming from.
	 * @param bool $is_fatal Whether to die with a fatal error if the check fails.
	 * @return string The error message, or '' if everything was fine.
	 */
	public static function sessionCheck(string $type = 'post', string $from_action = '', bool $is_fatal = true): string
	{
		return self::$me->checkSession($type, $from_action, $is_fatal);
	}

	/**
	 * Static wrapper around User::$me->allowedTo().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string|array $permission A single permission to check or an array
	 *    of permissions to check.
	 * @param int|array $boards The ID of a board or an array of board IDs if we
	 *    want to check board-level permissions
	 * @param bool $any Whether to check for permission on at least one board
	 *    instead of all the passed boards.
	 * @return bool Whether the user has the specified permission.
	 */
	public static function hasPermission(string|array $permission, int|array|null $boards = null, bool $any = false): bool
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(self::$me)) {
			return false;
		}

		return self::$me->allowedTo($permission, $boards, $any);
	}

	/**
	 * Static wrapper around User::$me->isAllowedTo().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string|array $permission A single permission to check or an array
	 *    of permissions to check.
	 * @param int|array $boards The ID of a board or an array of board IDs if we
	 *    want to check board-level permissions
	 * @param bool $any Whether to check for permission on at least one board
	 *    instead of all the passed boards.
	 * @return bool Whether the user has the specified permission.
	 */
	public static function mustHavePermission(string|array $permission, int|array|null $boards = null, bool $any = false): bool
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(self::$me)) {
			return false;
		}

		self::$me->isAllowedTo($permission, $boards, $any);

		// If we get here, the user is allowed.
		return true;
	}

	/**
	 * Static wrapper around User::$me->boardsAllowedTo().
	 *
	 * This method exists only for backward compatibility purposes.
	 *
	 * @param string|array $permissions A single permission to check or an array
	 *    of permissions to check.
	 * @param bool $check_access Whether to check only the boards the user has
	 *    access to.
	 * @param bool $simple Whether to return a simple array of board IDs or one
	 *    with permissions as the keys.
	 * @return array An array of board IDs if $simple is true. Otherwise, an
	 *    array containing 'permission' => array(id, id, id...) pairs.
	 */
	public static function hasPermissionInBoards(string|array $permission, int|array|null $boards = null, bool $any = false): array
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(self::$me)) {
			return false;
		}

		return self::$me->boardsAllowedTo($permission, $boards, $any);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected in order to force instantiation via User::load().
	 *
	 * @param int $id The ID number of the user, or null for current user.
	 * @param string|null $dataset What kind of data to load.
	 *    Can be one of 'profile', 'normal', 'basic', or 'minimal'.
	 *    If left null, the default depends on the value of $id:
	 *     - If $id is an integer, then $dataset will default to 'normal'.
	 *     - If $id is also null (i.e. we are loading the current user), then
	 *       $dataset will be determined automatically based on what the user is
	 *       doing on the forum.
	 */
	protected function __construct(?int $id = null, ?string $dataset = null)
	{
		// No ID given, so load current user.
		if (!isset($id)) {
			// Only do this once.
			if (!isset(self::$my_id)) {
				// This is the special $me instance.
				self::$me = $this;

				// Current user is a guest until proven otherwise.
				self::$my_id = 0;

				// Allow mods to do verification if they want.
				$this->integrateVerifyUser();

				// Load the user's data.
				$this->setMyId();
				self::loadUserData((array) self::$my_id, self::LOAD_BY_ID, $dataset ?? $this->chooseMyDataset());

				// Verify that the user is who they claim to be.
				// If verification fails, self::$my_id will be reset to 0.
				$this->verifyPassword();
				$this->verifyTfa();

				// At this point, we know the user ID for sure.
				$this->id = self::$my_id;

				// Also track this in our list of all loaded instances.
				self::$loaded[$this->id] = $this;

				// If the user is a guest, initialize all the critical user settings.
				if (empty($this->id)) {
					$this->initializeGuest();
				}
				// Otherwise, update the user's last visit time.
				else {
					$this->setLastVisit();
				}

				// Fix up the timezone and time_offset values.
				$this->fixTimezoneSetting();

				// Now set all the properties.
				$this->setProperties();

				// Backward compatibility.
				self::$info = $this;
				Utils::$context['user'] = $this;

				// MOD AUTHORS: If you use this hook, update your code to work
				// with SMF\User::$me instead of the deprecated $user_info.
				// Alternatively, consider the integrate_user_properties hook in
				// the setProperties() method, which lets you work with the
				// properties of any instance of this class.
				IntegrationHook::call('integrate_user_info');
			}
		}
		// Reloading the current user requires special handling.
		elseif (isset(self::$my_id) && $id == self::$my_id) {
			// Copy over the existing data.
			$this->set(get_object_vars(self::$me));

			$dataset = $dataset ?? $this->chooseMyDataset();

			if (self::$dataset_levels[self::$me->dataset] < self::$dataset_levels[$dataset]) {
				self::loadUserData((array) $id, self::LOAD_BY_ID, $dataset);
			}

			$this->fixTimezoneSetting();
			$this->setProperties();

			self::$loaded[$id] = $this;
			self::setMe($id);
		}
		// Load the specified member.
		else {
			$this->id = $id;

			self::$loaded[$id] = $this;

			if (!isset(self::$profiles[$id]) || self::$dataset_levels[self::$profiles[$id]['dataset']] < self::$dataset_levels[$dataset ?? 'normal']) {
				self::loadUserData((array) $id, self::LOAD_BY_ID, $dataset ?? 'normal');
			}

			$this->fixTimezoneSetting();
			$this->setProperties();
		}

		self::setModerators();
	}

	/**
	 * Sets object properties based on data in User::$profiles[$this->id].
	 */
	protected function setProperties(): void
	{
		// For developer convenience.
		$profile = &self::$profiles[$this->id];
		$is_me = $this->id === (self::$my_id ?? NAN);

		// Vital info.
		$this->username = $profile['member_name'] ?? '';
		$this->name = $profile['real_name'] ?? '';
		$this->email = $profile['email_address'] ?? '';
		$this->passwd = $profile['passwd'] ?? '';
		$this->password_salt = $profile['password_salt'] ?? '';
		$this->tfa_secret = $profile['tfa_secret'] ?? '';
		$this->tfa_backup = $profile['tfa_backup'] ?? '';
		$this->secret_question = $profile['secret_question'] ?? '';
		$this->secret_answer = $profile['secret_answer'] ?? '';
		$this->validation_code = $profile['validation_code'] ?? '';
		$this->passwd_flood = $profile['passwd_flood'] ?? '';

		// User status.
		$this->setGroups();
		$this->setPossiblyRobot();
		$this->is_guest = empty($this->id);
		$this->is_admin = in_array(1, $this->groups);
		$this->is_mod = in_array(3, $this->groups) || !empty($profile['is_mod']);
		$this->is_activated = $profile['is_activated'] ?? (int) (!$this->is_guest);
		$this->is_banned = $this->is_activated >= 10;
		$this->is_online = $profile['is_online'] ?? $is_me;

		// User activity and history.
		$this->show_online = $profile['show_online'] ?? false;
		$this->url = $profile['url'] ?? '';
		$this->last_login = $profile['last_login'] ?? 0;
		$this->id_msg_last_visit = $profile['id_msg_last_visit'] ?? 0;
		$this->total_time_logged_in = $profile['total_time_logged_in'] ?? 0;
		$this->date_registered = $profile['date_registered'] ?? 0;
		$this->ip = $is_me ? $_SERVER['REMOTE_ADDR'] : ($profile['member_ip'] ?? '');
		$this->ip2 = $is_me ? $_SERVER['BAN_CHECK_IP'] : ($profile['member_ip2'] ?? '');

		// Additional profile info.
		$this->posts = $profile['posts'] ?? 0;
		$this->title = $profile['usertitle'] ?? '';
		$this->signature = $profile['signature'] ?? '';
		$this->personal_text = $profile['personal_text'] ?? '';
		$this->birthdate = $profile['birthdate'] ?? '';
		$this->website['url'] = $profile['website_url'] ?? '';
		$this->website['title'] = $profile['website_title'] ?? '';

		// Presentation preferences.
		$this->theme = $profile['id_theme'] ?? 0;
		$this->options = $profile['options'] ?? [];
		$this->smiley_set = $profile['smiley_set'] ?? '';

		// Localization.
		$this->setLanguage();
		$this->time_format = empty($profile['time_format']) ? Config::$modSettings['time_format'] : $profile['time_format'];
		$this->timezone = $profile['timezone'] ?? Config::$modSettings['default_timezone'];
		$this->time_offset = $profile['time_offset'] ?? 0;

		// Buddies and personal messages.
		$this->buddies = !empty(Config::$modSettings['enable_buddylist']) && !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : [];
		$this->ignoreusers = !empty($profile['pm_ignore_list']) ? explode(',', $profile['pm_ignore_list']) : [];
		$this->pm_receive_from = $profile['pm_receive_from'] ?? 0;
		$this->pm_prefs = $profile['pm_prefs'] ?? 0;
		$this->messages = $profile['instant_messages'] ?? 0;
		$this->unread_messages = $profile['unread_messages'] ?? 0;
		$this->new_pm = $profile['new_pm'] ?? 0;

		// What does the user want to see or know about?
		$this->alerts = $profile['alerts'] ?? 0;
		$this->ignoreboards = !empty($profile['ignore_boards']) && !empty(Config::$modSettings['allow_ignore_boards']) ? explode(',', $profile['ignore_boards']) : [];

		// Extended membergroup info.
		$this->group_name = $profile['member_group'] ?? '';
		$this->primary_group_name = $profile['primary_group'] ?? '';
		$this->post_group_name = $profile['post_group'] ?? '';
		$this->group_color = $profile['member_group_color'] ?? '';
		$this->post_group_color = $profile['post_group_color'] ?? '';
		$this->icons = empty($profile['icons']) ? ['', ''] : explode('#', $profile['icons']);

		// The avatar is a complicated thing, and historically had multiple
		// representations in the code. This supports everything.
		$this->avatar = array_merge(
			[
				'original_url' => $profile['avatar_original'] ?? '',
				'url' => $profile['avatar'] ?? '',
				'filename' => $profile['filename'] ?? '',
				'custom_dir' => !empty($profile['attachment_type']) && $profile['attachment_type'] == 1,
				'id_attach' => $profile['id_attach'] ?? 0,
				'width' => $profile['attachment_width'] ?? null,
				'height' => $profile['attachment_height'] ?? null,
			],
			self::setAvatarData([
				'avatar' => $profile['avatar'] ?? '',
				'email' => $profile['email_address'] ?? '',
				'filename' => $profile['filename'] ?? '',
			]),
		);

		// Info about stuff related to permissions.
		// Note that we set $this->permissions elsewhere.
		$this->warning = $profile['warning'] ?? 0;
		$this->can_manage_boards = !empty($this->is_admin) || (!empty(Config::$modSettings['board_manager_groups']) && !empty($this->groups) && count(array_intersect($this->groups, explode(',', Config::$modSettings['board_manager_groups']))) > 0);

		foreach (self::buildQueryBoard($this->id) as $key => $value) {
			$this->{$key} = $value;
		}

		// What dataset did we load for this user?
		$this->dataset = $profile['dataset'];

		// An easy way for mods to add or adjust properties.
		IntegrationHook::call('integrate_user_properties', [$this]);
	}

	/**
	 * Wrapper for integrate_verify_user hook. Allows integrations to verify
	 * the current user's identity for us.
	 */
	protected function integrateVerifyUser(): void
	{
		if (count($integration_ids = IntegrationHook::call('integrate_verify_user')) === 0) {
			return;
		}

		foreach ($integration_ids as $integration_id) {
			if (intval($integration_id) > 0) {
				self::$my_id = (int) $integration_id;
				$this->already_verified = true;
				break;
			}
		}
	}

	/**
	 * Sets User::$my_id to the current user's ID from the login cookie.
	 *
	 * If no cookie was provided, checks $_SESSION to see if there is a match
	 * with an existing session.
	 *
	 * On failure, User::$my_id is set to 0.
	 */
	protected function setMyId(): void
	{
		// No need to check if this has already been set.
		if (!empty(self::$my_id)) {
			return;
		}

		if (isset($_COOKIE[Config::$cookiename])) {
			// First try 2.1 json-format cookie
			$cookie_data = Utils::jsonDecode($_COOKIE[Config::$cookiename], true, false);

			// Legacy format (for recent 2.0 --> 2.1 upgrades)
			if (empty($cookie_data)) {
				$cookie_data = Utils::safeUnserialize($_COOKIE[Config::$cookiename]);
			}

			list(self::$my_id, $this->passwd, $login_span, $cookie_domain, $cookie_path) = array_pad((array) $cookie_data, 5, '');

			self::$my_id = !empty(self::$my_id) && strlen($this->passwd) > 0 ? (int) self::$my_id : 0;

			// Make sure the cookie is set to the correct domain and path
			if ([$cookie_domain, $cookie_path] !== Cookie::urlParts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']))) {
				Cookie::setLoginCookie((int) $login_span - time(), self::$my_id);
			}
		} elseif (isset($_SESSION['login_' . Config::$cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty(Config::$modSettings['disableCheckUA']))) {
			// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
			$cookie_data = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename], true);

			if (empty($cookie_data)) {
				$cookie_data = Utils::safeUnserialize($_SESSION['login_' . Config::$cookiename]);
			}

			list(self::$my_id, $this->passwd, $login_span) = array_pad((array) $cookie_data, 3, '');

			self::$my_id = !empty(self::$my_id) && strlen($this->passwd) == 40 && (int) $login_span > time() ? (int) self::$my_id : 0;
		}
	}

	/**
	 * Figures out which dataset we want to load for the current user.
	 *
	 * @return string The name of a dataset to load.
	 */
	protected function chooseMyDataset(): string
	{
		// Board index, message index, or topic.
		if (!isset($_REQUEST['action'])) {
			$dataset = 'normal';
		}
		// Popups, AJAX, etc.
		elseif (QueryString::isFilteredRequest(Forum::$unlogged_actions, 'action')) {
			$dataset = 'basic';
		}
		// Profile and personal messages (except the popups)
		elseif (in_array($_REQUEST['action'], ['profile', 'pm'])) {
			$dataset = 'profile';
		}
		// Who's Online
		elseif (in_array($_REQUEST['action'], ['who'])) {
			$dataset = 'normal';
		}
		// Everything else.
		else {
			$dataset = 'basic';
		}

		return $dataset;
	}

	/**
	 * Verifies that the supplied password was correct.
	 *
	 * If not, User::$my_id is set to 0, and we take steps to prevent brute
	 * force hacking attempts.
	 */
	protected function verifyPassword(): void
	{
		if (empty(self::$my_id)) {
			return;
		}

		// Did we find 'im?  If not, junk it.
		if (!empty(self::$profiles[self::$my_id])) {
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($this->already_verified) && $this->already_verified === true) {
				$check = true;
			}
			// SHA-512 hash should be 128 characters long.
			elseif (strlen($this->passwd) == 128) {
				$check = hash_equals(Cookie::encrypt(self::$profiles[self::$my_id]['passwd'], self::$profiles[self::$my_id]['password_salt']), $this->passwd);
			} else {
				$check = false;
			}

			// Wrong password or not activated - either way, you're going nowhere.
			self::$my_id = $check && (self::$profiles[self::$my_id]['is_activated'] == 1 || self::$profiles[self::$my_id]['is_activated'] == 11) ? (int) self::$profiles[self::$my_id]['id_member'] : 0;
		} else {
			self::$my_id = 0;
		}

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (empty(self::$my_id)) {
			Login2::validatePasswordFlood(
				!empty(self::$profiles[self::$my_id]['id_member']) ? self::$profiles[self::$my_id]['id_member'] : self::$my_id,
				!empty(self::$profiles[self::$my_id]['member_name']) ? self::$profiles[self::$my_id]['member_name'] : '',
				!empty(self::$profiles[self::$my_id]['passwd_flood']) ? self::$profiles[self::$my_id]['passwd_flood'] : false,
				self::$my_id != 0,
			);
		}
	}

	/**
	 * If appropriate for this user, performs two factor authentication check.
	 */
	protected function verifyTfa(): void
	{
		if (empty(self::$my_id) || empty(Config::$modSettings['tfa_mode'])) {
			return;
		}

		// Check if we are forcing TFA
		$force_tfasetup = Config::$modSettings['tfa_mode'] >= 2 && empty(self::$profiles[self::$my_id]['tfa_secret']) && SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml');

		// Don't force TFA on popups
		if ($force_tfasetup) {
			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'profile' && isset($_REQUEST['area']) && in_array($_REQUEST['area'], ['popup', 'alerts_popup'])) {
				$force_tfasetup = false;
			} elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == 'pm' && (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'popup')) {
				$force_tfasetup = false;
			}

			IntegrationHook::call('integrate_force_tfasetup', [&$force_tfasetup]);
		}

		// Validate for Two Factor Authentication
		if (!empty(self::$profiles[self::$my_id]['tfa_secret']) && (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], ['login2', 'logintfa']))) {
			$tfacookie = Config::$cookiename . '_tfa';
			$tfasecret = null;

			$verified = IntegrationHook::call('integrate_verify_tfa', [self::$my_id, self::$profiles[self::$my_id]]);

			if (empty($verified) || !in_array(true, $verified)) {
				if (!empty($_COOKIE[$tfacookie])) {
					$tfa_data = Utils::jsonDecode($_COOKIE[$tfacookie], true);

					list($tfamember, $tfasecret) = array_pad((array) $tfa_data, 2, '');

					if (!isset($tfamember, $tfasecret) || (int) $tfamember != self::$my_id) {
						$tfasecret = null;
					}
				}

				// They didn't finish logging in before coming here? Then they're no one to us.
				if (empty($tfasecret) || !hash_equals(Cookie::encrypt(self::$profiles[self::$my_id]['tfa_backup'], self::$profiles[self::$my_id]['password_salt']), $tfasecret)) {
					Cookie::setLoginCookie(-3600, self::$my_id);
					self::$profiles[self::$my_id] = [];
					self::$my_id = 0;
				}
			}
		}
		// When authenticating their two factor code, make sure to reset their ID for security
		elseif (!empty(self::$profiles[self::$my_id]['tfa_secret']) && $_REQUEST['action'] == 'logintfa') {
			Utils::$context['tfa_member'] = self::$profiles[self::$my_id];
			self::$profiles[self::$my_id] = [];
			self::$my_id = 0;
		}
		// Are we forcing 2FA? Need to check if the user groups actually require 2FA
		elseif ($force_tfasetup) {
			// Only do this if we are just forcing SOME membergroups
			if (Config::$modSettings['tfa_mode'] == 2) {
				// Build an array of ALL user membergroups.
				$this->setGroups();

				// Find out if any group requires 2FA
				$request = Db::$db->query(
					'',
					'SELECT COUNT(id_group) AS total
					FROM {db_prefix}membergroups
					WHERE tfa_required = {int:tfa_required}
						AND id_group IN ({array_int:full_groups})',
					[
						'tfa_required' => 1,
						'full_groups' => $this->groups,
					],
				);
				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);
			}
			// Simplifies logic in the next "if"
			else {
				$row['total'] = 1;
			}

			$area = !empty($_REQUEST['area']) ? $_REQUEST['area'] : '';
			$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';

			if (
				$row['total'] > 0
				&& (
					!in_array($action, ['profile', 'logout'])
					|| (
						$action == 'profile'
						&& $area != 'tfasetup'
					)
				)
			) {
				Utils::redirectexit('action=profile;area=tfasetup;forced');
			}
		}
	}

	/**
	 * Determines the 'id_msg_last_visit' value, which is used to figure out
	 * what counts as new content for this user.
	 */
	protected function setLastVisit(): void
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. RSS feeds and XMLHTTP requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		// 5. We're still logging in or authenticating
		if (
			SMF != 'SSI'
			&& !isset($_REQUEST['xml'])
			&& (
				!isset($_REQUEST['action'])
				|| !in_array($_REQUEST['action'], ['.xml', 'login2', 'logintfa'])
			)
			&& empty($_SESSION['id_msg_last_visit'])
			&& (
				empty(CacheApi::$enable)
				|| ($_SESSION['id_msg_last_visit'] = CacheApi::get('user_last_visit-' . self::$my_id, 5 * 3600)) === null
			)
		) {
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = Db::$db->query(
				'',
				'SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				[
					'id_msg' => self::$profiles[self::$my_id]['id_msg_last_visit'],
				],
			);
			list($visitTime) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			$_SESSION['id_msg_last_visit'] = self::$profiles[self::$my_id]['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600) {
				self::updateMemberData(self::$my_id, ['id_msg_last_visit' => (int) Config::$modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']]);

				self::$profiles[self::$my_id]['last_login'] = time();

				if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
					CacheApi::put('user_settings-' . self::$my_id, self::$profiles[self::$my_id], 60);
				}

				if (!empty(CacheApi::$enable)) {
					CacheApi::put('user_last_visit-' . self::$my_id, $_SESSION['id_msg_last_visit'], 5 * 3600);
				}
			}
		} elseif (empty($_SESSION['id_msg_last_visit'])) {
			$_SESSION['id_msg_last_visit'] = self::$profiles[self::$my_id]['id_msg_last_visit'];
		}
	}

	/**
	 * Sets User::$profiles[0], cookie, etc., to appropriate values for a guest.
	 */
	protected function initializeGuest(): void
	{
		// This is what a guest's variables should be.
		self::$profiles[0] = [
			'dataset' => 'basic',
		];

		if (isset($_COOKIE[Config::$cookiename]) && empty(Utils::$context['tfa_member'])) {
			$_COOKIE[Config::$cookiename] = '';
		}

		// Expire the 2FA cookie
		if (isset($_COOKIE[Config::$cookiename . '_tfa']) && empty(Utils::$context['tfa_member'])) {
			$tfa_data = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);

			list(, , $exp) = array_pad((array) $tfa_data, 3, 0);

			if (time() > $exp) {
				$_COOKIE[Config::$cookiename . '_tfa'] = '';
				Cookie::setTFACookie(-3600, 0, '');
			}
		}

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login'])) {
			SecurityToken::create('login');
		} else {
			Utils::$context['login_token_var'] = $_SESSION['token']['post-login']->var;
			Utils::$context['login_token'] = $_SESSION['token']['post-login']->val;
		}
	}

	/**
	 * Ensures timezone and time_offset are both set to correct values.
	 */
	protected function fixTimezoneSetting(): void
	{
		if (!empty($this->id)) {
			// Figure out the new time offset.
			if (!empty(self::$profiles[$this->id]['timezone'])) {
				// Get the offsets from UTC for the server, then for the user.
				$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
				$tz_user = new \DateTimeZone(self::$profiles[$this->id]['timezone']);
				$time_system = new \DateTime('now', $tz_system);
				$time_user = new \DateTime('now', $tz_user);
				self::$profiles[$this->id]['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
			}
			// We need a time zone.
			else {
				if (!empty(self::$profiles[$this->id]['time_offset'])) {
					$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
					$time_system = new \DateTime('now', $tz_system);

					self::$profiles[$this->id]['timezone'] = @timezone_name_from_abbr('', $tz_system->getOffset($time_system) + self::$profiles[$this->id]['time_offset'] * 3600, (int) $time_system->format('I'));
				}

				if (empty(self::$profiles[$this->id]['timezone'])) {
					self::$profiles[$this->id]['timezone'] = Config::$modSettings['default_timezone'];
					self::$profiles[$this->id]['time_offset'] = 0;
				}
			}
		}
		// Guests use the forum default.
		else {
			self::$profiles[$this->id]['timezone'] = Config::$modSettings['default_timezone'];
			self::$profiles[$this->id]['time_offset'] = 0;
		}
	}

	/**
	 * Determines which membergroups the current user belongs to.
	 */
	protected function setGroups(): void
	{
		if (!empty($this->id)) {
			$this->group_id = (int) self::$profiles[$this->id]['id_group'];
			$this->post_group_id = (int) self::$profiles[$this->id]['id_post_group'];
			$this->additional_groups = array_map('intval', array_filter(explode(',', self::$profiles[$this->id]['additional_groups'])));
			$this->groups = array_merge([$this->group_id, $this->post_group_id], $this->additional_groups);
		}
		// Guests are only part of the guest group.
		else {
			$this->group_id = -1;
			$this->post_group_id = -1;
			$this->additional_groups = [];
			$this->groups = [-1];
		}
	}

	/**
	 * Do we perhaps think this is a search robot?
	 */
	protected function setPossiblyRobot(): void
	{
		// This is a logged in user, so definitely not a spider.
		if (!empty($this->id)) {
			$this->possibly_robot = false;
		}
		// A guest, so check further...
		else {
			// Check every five minutes just in case...
			if ((!empty(Config::$modSettings['spider_mode']) || !empty(Config::$modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300)) {
				if (isset($_SESSION['id_robot'])) {
					unset($_SESSION['id_robot']);
				}

				$_SESSION['robot_check'] = time();

				// We cache the spider data for ten minutes if we can.
				if (($spider_data = CacheApi::get('spider_search', 600)) === null) {
					$spider_data = [];

					$request = Db::$db->query(
						'',
						'SELECT id_spider, user_agent, ip_info
						FROM {db_prefix}spiders
						ORDER BY LENGTH(user_agent) DESC',
						[
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						$spider_data[] = $row;
					}
					Db::$db->free_result($request);

					CacheApi::put('spider_search', $spider_data, 600);
				}

				if (empty($spider_data)) {
					$this->possibly_robot = false;
				} else {
					// Only do these bits once.
					$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

					foreach ($spider_data as $spider) {
						// User agent is easy.
						if (!empty($spider['user_agent']) && strpos($ci_user_agent, strtolower($spider['user_agent'])) !== false) {
							$_SESSION['id_robot'] = $spider['id_spider'];
						}
						// IP stuff is harder.
						elseif ($_SERVER['REMOTE_ADDR']) {
							$ips = explode(',', $spider['ip_info']);

							foreach ($ips as $ip) {
								if ($ip === '') {
									continue;
								}

								$ip_range = IP::ip2range($ip);

								$remote_ip = new IP($_SERVER['REMOTE_ADDR']);

								if (!empty($ip_range)) {
									if ($ip_range['low']->toBinary() <= $remote_ip->toBinary() && $ip_range['high']->toBinary() >= $remote_ip->toBinary()) {
										$_SESSION['id_robot'] = $spider['id_spider'];
									}
								}
							}
						}

						if (isset($_SESSION['id_robot'])) {
							break;
						}
					}

					// If this is low server tracking then log the spider here as opposed to the main logging function.
					if (!empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] == 1 && !empty($_SESSION['id_robot'])) {
						self::logSpider();
					}

					$this->possibly_robot = !empty($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
				}
			} elseif (!empty(Config::$modSettings['spider_mode'])) {
				$this->possibly_robot = $_SESSION['id_robot'] ?? 0;
			}
			// If we haven't turned on proper spider hunts then have a guess!
			else {
				$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

				$this->possibly_robot = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false || strpos($ci_user_agent, 'bingbot') !== false || strpos($ci_user_agent, 'bingpreview') !== false || strpos($ci_user_agent, 'adidxbot') !== false || strpos($ci_user_agent, 'msnbot') !== false;
			}
		}
	}

	/**
	 * Sets the current user's preferred language.
	 *
	 * Uses their saved setting, unless they are requesting a different one.
	 */
	protected function setLanguage(): void
	{
		// Is everyone forced to use the default language?
		if (empty(Config::$modSettings['userLanguage'])) {
			$this->language = Config::$language;

			return;
		}

		// Which language does this user prefer?
		$this->language = empty(self::$profiles[$this->id]['lngfile']) ? Config::$language : self::$profiles[$this->id]['lngfile'];

		// Allow the user to change their language.
		$languages = Lang::get();

		// Change was requested in URL parameters.
		if (!empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')])) {
			$this->language = strtr($_GET['language'], './\\:', '____');

			// Make it permanent for members.
			if (!empty($this->id)) {
				self::updateMemberData($this->id, ['lngfile' => $this->language]);
			} else {
				$_SESSION['language'] = $this->language;
			}

			// Reload same URL with new language, if applicable.
			if (isset($_SESSION['old_url'])) {
				Utils::redirectexit($_SESSION['old_url']);
			}
		}
		// Carry forward the last language request in this session, if any.
		elseif (!empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')])) {
			$this->language = strtr($_SESSION['language'], './\\:', '____');
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Loads users' settings from the database.
	 *
	 * The retrieved information is stored in User::$profiles[$id].
	 *
	 * @param array $users Users specified by ID, name, or email address.
	 * @param int $type Whether $users contains IDs, names, or email addresses.
	 *    Possible values are this class's LOAD_BY_* constants.
	 * @param string $dataset The set of data to load.
	 * @return array The IDs of the loaded members.
	 */
	protected static function loadUserData(array $users, int $type = self::LOAD_BY_ID, string $dataset = 'normal'): array
	{
		if (!isset(self::$dataset_levels[$dataset])) {
			$dataset = 'normal';
		}

		// Keep track of which IDs we load during this run.
		$loaded_ids = [];

		// If $users is supposed to contain ID numbers, accept only integers.
		if ($type === self::LOAD_BY_ID) {
			$users = array_map('intval', $users);
		}

		// Avoid duplication.
		$users = array_unique($users);

		// For guests, there is no data to load, so just fake it.
		if (in_array(0, $users)) {
			self::$profiles[0] = ['dataset' => $dataset];
			$loaded_ids[] = 0;
			$users = array_filter($users);
		}

		// If there is no one to load, bail out now.
		if (empty($users)) {
			return $loaded_ids;
		}

		// Is the member data already loaded?
		if ($type === self::LOAD_BY_ID) {
			foreach ($users as $key => $id) {
				if (!isset(self::$profiles[$id])) {
					continue;
				}

				if (!isset(self::$profiles[$id]['dataset'])) {
					continue;
				}

				if (self::$dataset_levels[self::$profiles[$id]['dataset']] >= self::$dataset_levels[$dataset]) {
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Is the member data cached?
		if ($type === self::LOAD_BY_ID && !empty(CacheApi::$enable)) {
			foreach ($users as $key => $id) {
				if ($id === (self::$my_id ?? NAN)) {
					if (CacheApi::$enable < 2) {
						continue;
					}

					if (($data = CacheApi::get('user_settings-' . $id, 60) == null)) {
						continue;
					}
				} else {
					if (CacheApi::$enable < 3) {
						continue;
					}

					if (($data = CacheApi::get('member_data-' . $dataset . '-' . $id, 240)) == null) {
						continue;
					}
				}

				// Does the cached data have everything we need?
				if (is_array($data) && self::$dataset_levels[$data['dataset'] ?? 'minimal'] >= self::$dataset_levels[$dataset]) {
					self::$profiles[$id] = $data;
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Look up any un-cached member data.
		if (!empty($users)) {
			$select_columns = ['mem.*'];
			$select_tables = ['{db_prefix}members AS mem'];

			switch ($dataset) {
				case 'profile':
					$select_columns[] = 'lo.url';
					// no break

				case 'normal':
					$select_columns[] = 'COALESCE(lo.log_time, 0) AS is_online';
					$select_columns[] = 'mg.online_color AS member_group_color';
					$select_columns[] = 'COALESCE(mg.group_name, {string:blank_string}) AS member_group';
					$select_columns[] = 'pg.online_color AS post_group_color';
					$select_columns[] = 'COALESCE(pg.group_name, {string:blank_string}) AS post_group';
					$select_columns[] = 'CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons';

					$select_tables[] = 'LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)';
					$select_tables[] = 'LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)';
					$select_tables[] = 'LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
					// no break

				case 'basic':
					$select_columns[] = 'COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.width AS attachment_width, a.height AS attachment_height';

					$select_tables[] = 'LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)';
					// no break

				case 'minimal':
					break;

				default:
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['invalid_member_data_set'], $dataset), E_USER_WARNING);
					break;
			}

			switch ($type) {
				case self::LOAD_BY_EMAIL:
					$where = 'mem.email_address' . (count($users) > 1 ? ' IN ({array_string:users})' : ' = {string:users}');
					break;

				case self::LOAD_BY_NAME:
					if (Db::$db->case_sensitive) {
						$where = 'LOWER(mem.member_name)';
						$users = array_map('strtolower', $users);
					} else {
						$where = 'mem.member_name';
					}

					$where .= count($users) > 1 ? ' IN ({array_string:users})' : ' = {string:users}';

					break;

				default:
					$where = 'mem.id_member' . (count($users) > 1 ? ' IN ({array_int:users})' : ' = {int:users}');
					break;
			}

			// Allow mods to easily add to the selected member data
			IntegrationHook::call('integrate_load_member_data', [&$select_columns, &$select_tables, &$dataset]);

			// Load the members' data.
			$request = Db::$db->query(
				'',
				'SELECT ' . implode(",\n\t\t\t\t\t", $select_columns) . '
				FROM ' . implode("\n\t\t\t\t\t", $select_tables) . '
				WHERE ' . $where . (count($users) > 1 ? '' : '
				LIMIT 1'),
				[
					'blank_string' => '',
					'users' => count($users) > 1 ? $users : reset($users),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$row['id_member'] = (int) $row['id_member'];

				// If the image proxy is enabled, we still want the original URL when they're editing the profile...
				$row['avatar_original'] = $row['avatar'] ?? '';

				// Take care of proxying the avatar if required.
				if (!empty($row['avatar'])) {
					$row['avatar'] = Url::create($row['avatar'])->proxied();
				}

				// Keep track of the member's normal member group.
				$row['primary_group'] = $row['member_group'] ?? '';
				$row['member_group'] = $row['member_group'] ?? '';
				$row['post_group'] = $row['post_group'] ?? '';
				$row['member_group_color'] = $row['member_group_color'] ?? '';
				$row['post_group_color'] = $row['post_group_color'] ?? '';

				// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
				$row['ignore_boards'] = rtrim($row['ignore_boards'], ',');

				// Unpack the IP addresses.
				if (isset($row['member_ip'])) {
					$row['member_ip'] = new IP($row['member_ip']);
				}

				if (isset($row['member_ip2'])) {
					$row['member_ip2'] = new IP($row['member_ip2']);
				}

				$row['is_online'] = $row['is_online'] ?? $row['id_member'] === (self::$my_id ?? NAN);

				// Declare this for now. We'll fill it in later.
				$row['options'] = [];

				// Save it.
				if (!isset(self::$profiles[$row['id_member']])) {
					self::$profiles[$row['id_member']] = [];
				}

				// Use array_merge here to avoid data loss if we somehow call
				// this twice for the same member but with different datasets.
				self::$profiles[$row['id_member']] = array_merge(self::$profiles[$row['id_member']], $row);

				// If this is the current user's data, alias it to User::$settings.
				if ($row['id_member'] === (self::$my_id ?? NAN)) {
					self::$settings = &self::$profiles[$row['id_member']];
				}

				$loaded_ids[] = $row['id_member'];
			}
			Db::$db->free_result($request);

			if (!empty($loaded_ids) && $dataset !== 'minimal') {
				self::loadOptions($loaded_ids);
			}

			// This hook's name is due to historical reasons.
			IntegrationHook::call('integrate_load_min_user_settings', [&self::$profiles]);

			if ($type === self::LOAD_BY_ID && !empty(CacheApi::$enable)) {
				foreach ($users as $id) {
					if ($id === (self::$my_id ?? NAN)) {
						if (CacheApi::$enable >= 2) {
							CacheApi::put('user_settings-' . $id, self::$profiles[$id], 60);
						}
					} elseif (CacheApi::$enable >= 3) {
						CacheApi::put('member_data-' . $dataset . '-' . $id, self::$profiles[$id], 240);
					}
				}
			}
		}

		foreach ($loaded_ids as $id) {
			self::$profiles[$id]['dataset'] = $dataset;
		}

		return $loaded_ids;
	}

	/**
	 * Loads theme options for the given users.
	 *
	 * @param array|int $ids One or more user ID numbers.
	 */
	protected static function loadOptions(array|int $ids): void
	{
		$ids = (array) $ids;

		$request = Db::$db->query(
			'',
			'SELECT id_member, id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN ({array_int:ids})',
			[
				'ids' => $ids,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$profiles[$row['id_member']]['options'][$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);
	}

	/**
	 * Return a Gravatar URL based on
	 * - the supplied email address,
	 * - the global maximum rating,
	 * - the global default fallback,
	 * - maximum sizes as set in the admin panel.
	 *
	 * It is SSL aware, and caches most of the parameters.
	 *
	 * @param string $email_address The user's email address
	 * @return string The gravatar URL
	 */
	protected static function getGravatarUrl(string $email_address): string
	{
		static $url_params = null;

		if ($url_params === null) {
			$ratings = ['G', 'PG', 'R', 'X'];
			$defaults = ['mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank'];

			$url_params = [];

			if (!empty(Config::$modSettings['gravatarMaxRating']) && in_array(Config::$modSettings['gravatarMaxRating'], $ratings)) {
				$url_params[] = 'rating=' . Config::$modSettings['gravatarMaxRating'];
			}

			if (!empty(Config::$modSettings['gravatarDefault']) && in_array(Config::$modSettings['gravatarDefault'], $defaults)) {
				$url_params[] = 'default=' . Config::$modSettings['gravatarDefault'];
			}

			if (!empty(Config::$modSettings['avatar_max_width_external'])) {
				$size_string = (int) Config::$modSettings['avatar_max_width_external'];
			}

			if (
				!empty(Config::$modSettings['avatar_max_height_external'])
				&& !empty($size_string)
				&& (int) Config::$modSettings['avatar_max_height_external'] < $size_string
			) {
				$size_string = Config::$modSettings['avatar_max_height_external'];
			}

			if (!empty($size_string)) {
				$url_params[] = 's=' . $size_string;
			}
		}

		return 'https://secure.gravatar.com/avatar/' . md5(Utils::strtolower($email_address)) . '?' . implode('&', $url_params);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\User::exportStatic')) {
	User::exportStatic();
}

?>