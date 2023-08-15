<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Actions\Login2;
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
	use BackwardCompatibility, ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'reload' => false,
			'setMe' => false,
			'setModerators' => false,
			'buildQueryBoard' => 'build_query_board',
			'setAvatarData' => 'set_avatar_data',
			'getTimezone' => 'getUserTimezone',
			'delete' => 'deleteMembers',
			'loadMyPermissions' => 'loadPermissions',
			'setCurProfile' => false,
		),
		'prop_names' => array(
			'profiles' => 'user_profile',
			'settings' => 'user_settings',
			'info' => 'user_info',
			'memberContext' => 'memberContext',
		),
	);

	/*****************
	 * Class constants
	 *****************/

	const LOAD_BY_ID = 0;
	const LOAD_BY_NAME = 1;
	const LOAD_BY_EMAIL = 2;

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
	public array $additional_groups = array();

	/**
	 * @var array
	 *
	 * IDs of all the groups this user belongs to.
	 */
	public array $groups = array();

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
	public array $website = array(
		'url' => null,
		'title' => null,
	);

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
	public array $options = array();

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
	public array $buddies = array();

	/**
	 * @var array
	 *
	 * IDs of users that this user is ignoring.
	 */
	public array $ignoreusers = array();

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
	public array $ignoreboards = array();

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
	public array $avatar = array(
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
	);

	/**
	 * @var array
	 *
	 * Permssions that this user has been granted.
	 */
	public array $permissions = array();

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
	public array $mod_cache = array();

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
	public array $formatted = array();

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = array();

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
	 * Set by loadSession(). Used by checkSession().
	 */
	public static string $sc;

	/**
	 * @var array
	 *
	 * Basic data from the database about all loaded users.
	 */
	public static array $profiles = array();

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
	public static array $knownInts = array(
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
	);

	/**
	 * @var array
	 *
	 * Fields in the member table that take floats.
	 */
	public static array $knownFloats = array(
		'time_offset',
	);

	/**
	 * @var array
	 *
	 * Names of variables to pass to the integrate_change_member_data hook.
	 */
	public static array $integration_vars = array(
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
	);

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
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = array(
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
	);

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Maps names of dataset levels to numeric values.
	 */
	protected static array $dataset_levels = array(
		'minimal' => 0,
		'basic' => 1,
		'normal' => 2,
		'profile' => 3,
	);

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
		if (in_array($this->prop_aliases[$prop] ?? $prop, array('additional_groups', 'buddies', 'ignoreusers', 'ignoreboards')) && is_string($value))
		{
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
		if ($this->is_admin)
		{
			$this->can_mod = true;
			$this->can_manage_boards = true;

			banPermissions();
			return;
		}

		if (!empty(CacheApi::$enable))
		{
			$cache_groups = $this->groups;
			asort($cache_groups);
			$cache_groups = implode(',', $cache_groups);

			// If it's a spider then cache it separately.
			if ($this->possibly_robot)
				$cache_groups .= '-spider';

			if (
				CacheApi::$enable >= 2
				&& !empty(Board::$info->id)
				&& ($temp = CacheApi::get('permissions:' . $cache_groups . ':' . Board::$info->id, 240)) != null
				&& time() - 240 > Config::$modSettings['settings_updated']
			)
			{
				list($this->permissions) = $temp;
				banPermissions();

				return;
			}
			elseif (
				($temp = CacheApi::get('permissions:' . $cache_groups, 240)) != null
				&& time() - 240 > Config::$modSettings['settings_updated']
			)
			{
				list($this->permissions, $removals) = $temp;
			}
		}

		// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
		$spider_restrict = $this->possibly_robot && !empty(Config::$modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

		if (empty($this->permissions))
		{
			// Get the general permissions.
			$removals = array();
			$request = Db::$db->query('', '
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:member_groups})
					' . $spider_restrict,
				array(
					'member_groups' => $this->groups,
					'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (empty($row['add_deny']))
				{
					$removals[] = $row['permission'];
				}
				else
				{
					$this->permissions[] = $row['permission'];
				}
			}
			Db::$db->free_result($request);

			if (isset($cache_groups))
			{
				CacheApi::put('permissions:' . $cache_groups, array($this->permissions, $removals), 240);
			}
		}

		// Get the board permissions.
		if (!empty(Board::$info->id))
		{
			// Make sure the board (if any) has been loaded by Board::load().
			if (!isset(Board::$info->profile))
				ErrorHandler::fatalLang('no_board');

			$request = Db::$db->query('', '
				SELECT permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE (id_group IN ({array_int:member_groups})
					' . $spider_restrict . ')
					AND id_profile = {int:id_profile}',
				array(
					'member_groups' => $this->groups,
					'id_profile' => Board::$info->profile,
					'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (empty($row['add_deny']))
				{
					$removals[] = $row['permission'];
				}
				else
				{
					$this->permissions[] = $row['permission'];
				}
			}
			Db::$db->free_result($request);
		}

		// Remove all the permissions they shouldn't have ;).
		if (!empty(Config::$modSettings['permission_enable_deny']))
		{
			$this->permissions = array_diff($this->permissions, $removals);
		}

		if (isset($cache_groups) && !empty(Board::$info->id) && CacheApi::$enable >= 2)
		{
			CacheApi::put('permissions:' . $cache_groups . ':' . Board::$info->id, array($this->permissions, null), 240);
		}

		// Banned?  Watch, don't touch..
		banPermissions();

		// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
		if (!$this->is_guest && $this->id === self::$my_id)
		{
			if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= Config::$modSettings['settings_updated'])
			{
				require_once(Config::$sourcedir . '/Subs-Auth.php');
				rebuildModCache();
			}
			else
			{
				$this->mod_cache = $_SESSION['mc'];
			}

			// This is a useful phantom permission added to the current user, and only the current user while they are logged in.
			// For example this drastically simplifies certain changes to the profile area.
			$this->permissions[] = 'is_not_guest';

			// And now some backwards compatibility stuff for mods and whatnot that aren't expecting the new permissions.
			$this->permissions[] = 'profile_view_own';

			if (in_array('profile_view', $this->permissions))
				$this->permissions[] = 'profile_view_any';

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
		static $loadedLanguages = array();

		Lang::load('index+Modifications');

		if (empty(Config::$modSettings['displayFields']))
			$display_custom_fields = false;

		// If this user's data is already loaded, skip it.
		if (!empty($this->formatted) && $this->custom_fields_displayed >= $display_custom_fields)
		{
			return $this->formatted;
		}

		// The minimal values.
		$this->formatted = array(
			'id' => $this->id,
			'username' => $this->is_guest ? Lang::$txt['guest_title'] : $this->username,
			'name' => $this->is_guest ? Lang::$txt['guest_title'] : $this->name,
			'href' => $this->is_guest ? '' : Config::$scripturl . '?action=profile;u=' . $this->id,
			'link' => $this->is_guest ? '' : '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->name) . '">' . $this->name . '</a>',
			'email' => $this->email,
			'show_email' => !User::$me->is_guest && (User::$me->id == $this->id || allowedTo('moderate_forum')),
			'registered' => empty($this->date_registered) ? Lang::$txt['not_applicable'] : timeformat($this->date_registered),
			'registered_timestamp' => $this->date_registered,
		);

		// Basic, normal, and profile want the avatar.
		if (in_array($this->dataset, array('basic', 'normal', 'profile')))
			$this->formatted['avatar'] = $this->avatar;

		// Normal and profile want lots more data.
		if (in_array($this->dataset, array('normal', 'profile')))
		{
			// Go the extra mile and load the user's native language name.
			if (empty($loadedLanguages))
				$loadedLanguages = Lang::get(true);

			// We need a little fallback for the membergroup icons. If the image
			// doesn't exist in the current theme, fall back to default theme.
			$group_icon_url = '';

			if (isset($this->icons[1]))
			{
				foreach (array('actual_theme_dir' => 'images_url', 'default_theme_dir' => 'default_images_url') as $dir => $url)
				{
					if (file_exists(Theme::$current->settings[$dir] . '/images/membericons/' . $this->icons[1]))
						$group_icon_url = Theme::$current->settings[$url] . '/membericons/' . $this->icons[1];
				}
			}

			// Is this user online, and if so, is their online status visible?
			$is_visibly_online = (!empty($this->show_online) || allowedTo('moderate_forum')) && $this->is_online > 0;

			// Now append all the rest of the data.
			$this->formatted += array(
				'username_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->username . '</span>',
				'name_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->name . '</span>',
				'link_color' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->name) . '" ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->name . '</a>',
				'is_buddy' => in_array($this->id, User::$me->buddies),
				'is_reverse_buddy' => in_array(User::$me->id, $this->buddies),
				'buddies' => $this->buddies,
				'title' => !empty(Config::$modSettings['titlesEnable']) ? $this->title : '',
				'blurb' => $this->personal_text,
				'website' => $this->website,
				'birth_date' => empty($this->birthdate) ? '1004-01-01' : (substr($this->birthdate, 0, 4) === '0004' ? '1004' . substr($this->birthdate, 4) : $this->birthdate),
				'signature' => $this->signature,
				'real_posts' => $this->posts,
				'posts' => $this->posts > 500000 ? Lang::$txt['geek'] : Lang::numberFormat($this->posts),
				'last_login' => empty($this->last_login) ? Lang::$txt['never'] : timeformat($this->last_login),
				'last_login_timestamp' => empty($this->last_login) ? 0 : $this->last_login,
				'ip' => Utils::htmlspecialchars($this->ip),
				'ip2' => Utils::htmlspecialchars($this->ip2),
				'online' => array(
					'is_online' => $is_visibly_online,
					'text' => Utils::htmlspecialchars(Lang::$txt[$is_visibly_online ? 'online' : 'offline']),
					'member_online_text' => sprintf(Lang::$txt[$is_visibly_online ? 'member_is_online' : 'member_is_offline'], Utils::htmlspecialchars($this->name)),
					'href' => Config::$scripturl . '?action=pm;sa=send;u=' . $this->id,
					'link' => '<a href="' . Config::$scripturl . '?action=pm;sa=send;u=' . $this->id . '">' . Lang::$txt[$is_visibly_online ? 'online' : 'offline'] . '</a>',
					'label' => Lang::$txt[$is_visibly_online ? 'online' : 'offline'],
				),
				'language' => !empty($loadedLanguages[$this->language]) && !empty($loadedLanguages[$this->language]['name']) ? $loadedLanguages[$this->language]['name'] : Utils::ucwords(strtr($this->language, array('_' => ' ', '-utf8' => ''))),
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
				'group_icons' => str_repeat('<img src="' . str_replace('$language', User::$me->language, isset($this->icons[1]) ? $group_icon_url : '') . '" alt="*">', empty($this->icons[0]) || empty($this->icons[1]) ? 0 : $this->icons[0]),
				'warning' => $this->warning,
				'warning_status' => !empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= $this->warning ? 'mute' : (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= $this->warning ? 'moderate' : (!empty(Config::$modSettings['warning_watch']) && Config::$modSettings['warning_watch'] <= $this->warning ? 'watch' : '')),
				'local_time' => timeformat(time(), false, $this->timezone),
				'custom_fields' => array(),
			);

			Lang::censorText($this->formatted['blurb']);
			Lang::censorText($this->formatted['signature']);

			$this->formatted['signature'] = BBCodeParser::load()->parse(str_replace(array("\n", "\r"), array('<br>', ''), $this->formatted['signature']), true, 'sig' . $this->id, BBCodeParser::getSigTags());
		}

		// Are we also loading the member's custom fields?
		if ($display_custom_fields)
		{
			$this->formatted['custom_fields'] = array();

			if (!isset(Utils::$context['display_fields']))
			{
				Utils::$context['display_fields'] = Utils::jsonDecode(Config::$modSettings['displayFields'], true);
			}

			foreach (Utils::$context['display_fields'] as $custom)
			{
				if (!isset($custom['col_name']) || trim($custom['col_name']) == '' || empty($this->options[$custom['col_name']]))
				{
					continue;
				}

				$value = $this->options[$custom['col_name']];

				$fieldOptions = array();
				$currentKey = 0;

				// Create a key => value array for multiple options fields
				if (!empty($custom['options']))
				{
					foreach ($custom['options'] as $k => $v)
					{
						$fieldOptions[] = $v;

						if (empty($currentKey))
							$currentKey = $v == $value ? $k : 0;
					}
				}

				// BBC?
				if ($custom['bbc'])
				{
					$value = BBCodeParser::load()->parse($value);
				}
				// ... or checkbox?
				elseif (isset($custom['type']) && $custom['type'] == 'check')
				{
					$value = $value ? Lang::$txt['yes'] : Lang::$txt['no'];
				}

				// Enclosing the user input within some other text?
				$simple_value = $value;

				if (!empty($custom['enclose']))
				{
					$value = strtr($custom['enclose'], array(
						'{SCRIPTURL}' => Config::$scripturl,
						'{IMAGES_URL}' => Theme::$current->settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
						'{INPUT}' => Lang::tokenTxtReplace($value),
						'{KEY}' => $currentKey,
					));
				}

				$this->formatted['custom_fields'][] = array(
					'title' => Lang::tokenTxtReplace(!empty($custom['title']) ? $custom['title'] : $custom['col_name']),
					'col_name' => Lang::tokenTxtReplace($custom['col_name']),
					'value' => un_htmlspecialchars(Lang::tokenTxtReplace($value)),
					'simple' => Lang::tokenTxtReplace($simple_value),
					'raw' => $this->options[$custom['col_name']],
					'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
				);
			}
		}

		call_integration_hook('integrate_member_context', array(&$this->formatted, $this->id, $display_custom_fields));

		$this->custom_fields_displayed = !empty($this->custom_fields_displayed) | $display_custom_fields;

		// For backward compatibility.
		self::$memberContext[$this->id] = &$this->formatted;

		return $this->formatted;
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
	public static function load($users = array(), int $type = self::LOAD_BY_ID, string $dataset = null): array
	{
		$users = (array) $users;

		$loaded = array();

		// No ID? Just get the current user.
		if ($users === array())
		{
			if (!isset(self::$me))
				$loaded[] = new self(null, $dataset);
		}
		else
		{
			$dataset = $dataset ?? 'normal';

			// Load members.
			foreach (($loaded_ids = self::loadUserData((array) $users, $type, $dataset)) as $id)
			{
				// Not yet loaded.
				if (!isset(self::$loaded[$id]))
				{
					new self($id, $dataset);
				}
				// Already loaded, so just update the properties.
				elseif (self::$dataset_levels[self::$loaded[$id]->dataset] < self::$dataset_levels[$dataset])
				{
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
	public static function reload($users = array(), string $dataset = null): array
	{
		$users = (array) $users;

		foreach ($users as $id)
			unset(self::$loaded[$id], self::$profiles[$id]);

		return self::load($users, self::LOAD_BY_ID, $dataset);
	}

	/**
	 * Sets User::$me to the loaded object for the given user.
	 *
	 * @param int $id The ID of a user.
	 */
	public static function setMe(int $id): void
	{
		if (!isset(self::$loaded[$id]))
			self::load(array($id));

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
		if (isset(Board::$info) && ($moderator_group_info = CacheApi::get('moderator_group_info', 480)) == null)
		{
			$request = Db::$db->query('', '
				SELECT group_name, online_color, icons
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$moderator_group_info = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			CacheApi::put('moderator_group_info', $moderator_group_info, 480);
		}

		foreach (self::$profiles as $id => &$profile)
		{
			if (empty($id))
				continue;

			if (!isset(self::$loaded[$id]))
				new self($id);

			$user = self::$loaded[$id];

			// Global moderators.
			$profile['is_mod'] = in_array(2, $user->groups);

			// Can't do much else without a board.
			if (!isset(Board::$info))
				continue;

			if (!empty(Board::$info->moderators))
				$profile['is_mod'] |= isset(Board::$info->moderators[$id]);

			if (!empty(Board::$info->moderator_groups))
			{
				$profile['is_mod'] |= array_intersect($user->groups, array_keys(Board::$info->moderator_groups)) !== array();
			}

			// By popular demand, don't show admins or global moderators as moderators.
			if ($profile['is_mod'] && $user->group_id != 1 && $user->group_id != 2)
				$profile['member_group'] = $moderator_group_info['group_name'];

			// If the Moderator group has no color or icons, but their group does... don't overwrite.
			if ($profile['is_mod'] && !empty($moderator_group_info['icons']))
				$profile['icons'] = $moderator_group_info['icons'];

			if ($profile['is_mod'] && !empty($moderator_group_info['online_color']))
				$profile['member_group_color'] = $moderator_group_info['online_color'];

			// Update object properties.
			$user->setProperties();

			// Add this user to the moderators group if they're not already an admin or moderator.
			if ($user->is_mod && array_intersect(array(1, 2, 3), $user->groups) === array())
				$user->groups[] = 3;
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
		$query_part = array();

		if (isset(self::$loaded[$id]))
		{
			$groups = self::$loaded[$id]->groups;
			$can_see_all_boards = self::$loaded[$id]->is_admin || self::$loaded[$id]->can_manage_boards;
			$ignoreboards = !empty(self::$loaded[$id]->ignoreboards) ? self::$loaded[$id]->ignoreboards : null;
		}
		elseif ($id === 0)
		{
			$groups = array(-1);
			$can_see_all_boards = false;
			$ignoreboards = array();
		}
		else
		{
			$request = Db::$db->query('', '
				SELECT mem.ignore_boards, mem.id_group, mem.additional_groups, mem.id_post_group
				FROM {db_prefix}members AS mem
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id,
				)
			);

			$row = Db::$db->fetch_assoc($request);

			if (empty($row['additional_groups']))
			{
				$groups = array($row['id_group'], $row['id_post_group']);
			}
			else
			{
				$groups = array_merge(
					array($row['id_group'], $row['id_post_group']),
					explode(',', $row['additional_groups'])
				);
			}

			// Because history has proven that it is possible for groups to go bad - clean up in case.
			$groups = array_map('intval', $groups);

			$can_see_all_boards = in_array(1, $groups) || (!empty(Config::$modSettings['board_manager_groups']) && count(array_intersect($groups, explode(',', Config::$modSettings['board_manager_groups']))) > 0);

			$ignoreboards = !empty($row['ignore_boards']) && !empty(Config::$modSettings['allow_ignore_boards']) ? explode(',', $row['ignore_boards']) : array();
		}

		// Just build this here, it makes it easier to change/use - administrators can see all boards.
		if ($can_see_all_boards)
		{
			$query_part['query_see_board'] = '1=1';
		}
		// Otherwise only the boards that can be accessed by the groups this user belongs to.
		else
		{
			$query_part['query_see_board'] = '
				EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN ('. implode(',', $groups) .')
						AND bpv.deny = 0
						AND bpv.id_board = b.id_board
				)';

			if (!empty(Config::$modSettings['deny_boards_access']))
			{
				$query_part['query_see_board'] .= '
				AND NOT EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN ( '. implode(',', $groups) .')
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
		if (empty($ignoreboards))
		{
			$query_part['query_wanna_see_board'] = $query_part['query_see_board'];
			$query_part['query_wanna_see_message_board'] = $query_part['query_see_message_board'];
			$query_part['query_wanna_see_topic_board'] = $query_part['query_see_topic_board'];
		}
		// Ok I guess they don't want to see all the boards
		else
		{
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
	public static function setAvatarData(array $data = array()): array
	{
		// Come on!
		if (empty($data))
			return array();

		// Set a nice default var.
		$image = '';

		// Gravatar has been set as mandatory!
		if (!empty(Config::$modSettings['gravatarEnabled']) && !empty(Config::$modSettings['gravatarOverride']))
		{
			if (!empty(Config::$modSettings['gravatarAllowExtraEmail']) && !empty($data['avatar']) && stristr($data['avatar'], 'gravatar://'))
			{
				$image = get_gravatar_url(Utils::entitySubstr($data['avatar'], 11));
			}
			elseif (!empty($data['email']))
			{
				$image = get_gravatar_url($data['email']);
			}
		}
		// Look if the user has a gravatar field or has set an external url as avatar.
		else
		{
			// So it's stored in the member table?
			if (!empty($data['avatar']))
			{
				// Gravatar.
				if (stristr($data['avatar'], 'gravatar://'))
				{
					if ($data['avatar'] == 'gravatar://')
					{
						$image = get_gravatar_url($data['email']);
					}
					elseif (!empty(Config::$modSettings['gravatarAllowExtraEmail']))
					{
						$image = get_gravatar_url(Utils::entitySubstr($data['avatar'], 11));
					}
				}
				// External url.
				else
				{
					$image = parse_iri($data['avatar'], PHP_URL_SCHEME) !== null ? get_proxied_url($data['avatar']) : Config::$modSettings['avatar_url'] . '/' . $data['avatar'];
				}
			}
			// Perhaps this user has an attachment as avatar...
			elseif (!empty($data['filename']))
			{
				$image = Config::$modSettings['custom_avatar_url'] . '/' . $data['filename'];
			}
			// Right... no avatar... use our default image.
			else
			{
				$image = Config::$modSettings['avatar_url'] . '/default.png';
			}
		}

		call_integration_hook('integrate_set_avatar_data', array(&$image, &$data));

		// At this point in time $image has to be filled unless you chose to force gravatar and the user doesn't have the needed data to retrieve it... thus a check for !empty() is still needed.
		if (!empty($image))
		{
			return array(
				'name' => !empty($data['avatar']) ? $data['avatar'] : '',
				'image' => '<img class="avatar" src="' . $image . '" alt="">',
				'href' => $image,
				'url' => $image,
			);
		}
		// Fallback to make life easier for everyone...
		else
		{
			return array(
				'name' => '',
				'image' => '',
				'href' => '',
				'url' => '',
			);
		}
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
		if ($members === array())
			return;

		// For loaded members, update the loaded objects with the new data.
		foreach ((array) ($members ?? array_keys(User::$loaded)) as $member)
		{
			if (!isset(User::$loaded[$member]))
				continue;

			foreach ($data as $var => $val)
			{
				if ($var === 'alerts' && ($val === '+' || $val === '-'))
				{
					$val = Alert::count($member, true);
				}
				elseif (in_array($var, self::$knownInts) && ($val === '+' || $val === '-'))
				{
					$val = User::$loaded[$member]->{$var} + ($val === '+' ? 1 : -1);
				}

				if (in_array($var, array('posts', 'instant_messages', 'unread_messages')))
					$val = max(0, $val);

				User::$loaded[$member]->set(array($var, $val));
			}
		}

		$parameters = array();

		if (is_array($members))
		{
			$condition = 'id_member IN ({array_int:members})';
			$parameters['members'] = $members;
		}
		elseif ($members === null)
		{
			$condition = '1=1';
		}
		else
		{
			$condition = 'id_member = {int:member}';
			$parameters['member'] = $members;
		}

		if (!empty(Config::$modSettings['integrate_change_member_data']))
		{
			$vars_to_integrate = array_intersect(self::$integration_vars, array_keys($data));

			// Only proceed if there are any variables left to call the integration function.
			if (count($vars_to_integrate) != 0)
			{
				// Fetch a list of member_names if necessary
				if ((!is_array($members) && $members === self::$me->id) || (is_array($members) && count($members) == 1 && in_array(self::$me->id, $members)))
				{
					$member_names = array(self::$me->username);
				}
				else
				{
					$member_names = array();

					$request = Db::$db->query('', '
						SELECT member_name
						FROM {db_prefix}members
						WHERE ' . $condition,
						$parameters
					);
					while ($row = Db::$db->fetch_assoc($request))
					{
						$member_names[] = $row['member_name'];
					}
					Db::$db->free_result($request);
				}

				if (!empty($member_names))
				{
					foreach ($vars_to_integrate as $var)
					{
						call_integration_hook('integrate_change_member_data', array($member_names, $var, &$data[$var], &self::$knownInts, &self::$knownFloats));
					}
				}
			}
		}

		$setString = '';

		foreach ($data as $var => $val)
		{
			switch ($var)
			{
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

			if (in_array($var, self::$knownInts))
			{
				$type = 'int';
			}
			elseif (in_array($var, self::$knownFloats))
			{
				$type = 'float';
			}

			// Doing an increment?
			if ($var == 'alerts' && ($val === '+' || $val === '-'))
			{
				if (is_array($members))
				{
					$val = 'CASE ';

					foreach ($members as $k => $v)
					{
						$val .= 'WHEN id_member = ' . $v . ' THEN '. Alert::count($v, true) . ' ';
					}

					$val = $val . ' END';

					$type = 'raw';
				}
				else
				{
					$val = Alert::count($members, true);
				}
			}
			elseif ($type == 'int' && ($val === '+' || $val === '-'))
			{
				$val = $var . ' ' . $val . ' 1';
				$type = 'raw';
			}

			// Ensure posts, instant_messages, and unread_messages don't overflow or underflow.
			if (in_array($var, array('posts', 'instant_messages', 'unread_messages')))
			{
				if (preg_match('~^' . $var . ' (\+ |- |\+ -)(\d+)~', $val, $match))
				{
					if ($match[1] != '+ ')
					{
						$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
					}

					$type = 'raw';
				}
			}

			$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
			$parameters['p_' . $var] = $val;
		}

		Db::$db->query('', '
			UPDATE {db_prefix}members
			SET' . substr($setString, 0, -1) . '
			WHERE ' . $condition,
			$parameters
		);

		updateStats('postgroups', $members, array_keys($data));

		// Clear any caching?
		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2 && !empty($members))
		{
			if (!is_array($members))
				$members = array($members);

			foreach ($members as $member)
			{
				if (CacheApi::$enable >= 3)
				{
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
	public static function getTimezone(int $id_member = null): string
	{
		static $member_cache = array();

		if (is_null($id_member))
		{
			$id_member = empty(self::$me->id) ? 0 : self::$me->id;
		}
		else
		{
			$id_member = (int) $id_member;
		}

		// Check if we already have this in self::$loaded.
		if (isset(self::$loaded[$id_member]) && !empty(self::$loaded[$id_member]->timezone))
			return self::$loaded[$id_member]->timezone;

		// Did we already look this up?
		if (isset($member_cache[$id_member]))
			return $member_cache[$id_member];

		if (!empty($id_member))
		{
			// Look it up in the database.
			$request = Db::$db->query('', '
				SELECT timezone
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}',
				array(
					'id_member' => $id_member,
				)
			);
			list($timezone) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// If it is invalid, fall back to the default.
		if (empty($timezone) || !in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
		{
			$timezone = isset(Config::$modSettings['default_timezone']) ? Config::$modSettings['default_timezone'] : date_default_timezone_get();
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
		setMemoryLimit('128M');

		// If it's not an array, make it so!
		$users = array_unique((array) $users);

		// Make sure there's no void user in here.
		$users = array_diff($users, array(0));

		// How many are they deleting?
		if (empty($users))
			return;

		if (count($users) == 1)
		{
			list($user) = $users;

			if ($user == User::$me->id)
			{
				isAllowedTo('profile_remove_own');
			}
			else
			{
				isAllowedTo('profile_remove_any');
			}
		}
		else
		{
			foreach ($users as $k => $v)
				$users[$k] = (int) $v;

			// Deleting more than one?  You can't have more than one account...
			isAllowedTo('profile_remove_any');
		}

		// Get their names for logging purposes.
		$admins = array();
		$user_log_details = array();

		$request = Db::$db->query('', '
			SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:user_list})
			LIMIT {int:limit}',
			array(
				'user_list' => $users,
				'admin_group' => 1,
				'limit' => count($users),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if ($row['is_admin'])
				$admins[] = $row['id_member'];

			$user_log_details[$row['id_member']] = array($row['id_member'], $row['member_name']);
		}
		Db::$db->free_result($request);

		if (empty($user_log_details))
			return;

		// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
		if (!empty($admins) && ($check_not_admin || (!allowedTo('admin_forum') && (count($users) != 1 || $users[0] != self::$me->id))))
		{
			$users = array_diff($users, $admins);

			foreach ($admins as $id)
				unset($user_log_details[$id]);
		}

		// No one left?
		if (empty($users))
			return;

		// Log the action - regardless of who is deleting it.
		$log_changes = array();

		foreach ($user_log_details as $user)
		{
			$log_changes[] = array(
				'action' => 'delete_member',
				'log_type' => 'admin',
				'extra' => array(
					'member' => $user[0],
					'name' => $user[1],
					'member_acted' => self::$me->name,
				),
			);

			// Remove any cached data if enabled.
			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
				CacheApi::put('user_settings-' . $user[0], null, 60);
		}

		// Make these peoples' posts guest posts.
		Db::$db->query('', '
			UPDATE {db_prefix}messages
			SET id_member = {int:guest_id}' . (!empty(Config::$modSettings['deleteMembersRemovesEmail']) ? ',
				poster_email = {string:blank_email}' : '') . '
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'blank_email' => '',
				'users' => $users,
			)
		);

		Db::$db->query('', '
			UPDATE {db_prefix}polls
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		// Make these peoples' posts guest first posts and last posts.
		Db::$db->query('', '
			UPDATE {db_prefix}topics
			SET id_member_started = {int:guest_id}
			WHERE id_member_started IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		Db::$db->query('', '
			UPDATE {db_prefix}topics
			SET id_member_updated = {int:guest_id}
			WHERE id_member_updated IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		Db::$db->query('', '
			UPDATE {db_prefix}log_actions
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		Db::$db->query('', '
			UPDATE {db_prefix}log_banned
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		Db::$db->query('', '
			UPDATE {db_prefix}log_errors
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		// Delete the member.
		Db::$db->query('', '
			DELETE FROM {db_prefix}members
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Delete any drafts...
		Db::$db->query('', '
			DELETE FROM {db_prefix}user_drafts
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Delete anything they liked.
		Db::$db->query('', '
			DELETE FROM {db_prefix}user_likes
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Delete their mentions
		Db::$db->query('', '
			DELETE FROM {db_prefix}mentions
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => $users,
			)
		);

		// Delete the logs...
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:log_type}
				AND id_member IN ({array_int:users})',
			array(
				'log_type' => 2,
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_boards
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_recipient IN ({array_int:users})
				AND comment_type = {string:warntpl}',
			array(
				'users' => $users,
				'warntpl' => 'warntpl',
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_group_requests
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_mark_read
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Make their votes appear as guest votes - at least it keeps the totals right.
		// @todo Consider adding back in cookie protection.
		Db::$db->query('', '
			UPDATE {db_prefix}log_polls
			SET id_member = {int:guest_id}
			WHERE id_member IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		// Delete personal messages.
		PM::delete(null, null, $users);

		Db::$db->query('', '
			UPDATE {db_prefix}personal_messages
			SET id_member_from = {int:guest_id}
			WHERE id_member_from IN ({array_int:users})',
			array(
				'guest_id' => 0,
				'users' => $users,
			)
		);

		// They no longer exist, so we don't know who it was sent to.
		Db::$db->query('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Delete avatar.
		Attachment::remove(array('id_member' => $users));

		// It's over, no more moderation for you.
		Db::$db->query('', '
			DELETE FROM {db_prefix}moderators
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}group_moderators
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// If you don't exist we can't ban you.
		Db::$db->query('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// Remove individual theme settings.
		Db::$db->query('', '
			DELETE FROM {db_prefix}themes
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);

		// These users are nobody's buddy nomore.
		$request = Db::$db->query('', '
			SELECT id_member, pm_ignore_list, buddy_list
			FROM {db_prefix}members
			WHERE FIND_IN_SET({raw:pm_ignore_list}, pm_ignore_list) != 0 OR FIND_IN_SET({raw:buddy_list}, buddy_list) != 0',
			array(
				'pm_ignore_list' => implode(', pm_ignore_list) != 0 OR FIND_IN_SET(', $users),
				'buddy_list' => implode(', buddy_list) != 0 OR FIND_IN_SET(', $users),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET
					pm_ignore_list = {string:pm_ignore_list},
					buddy_list = {string:buddy_list}
				WHERE id_member = {int:id_member}',
				array(
					'id_member' => $row['id_member'],
					'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), $users)),
					'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), $users)),
				)
			);
		}
		Db::$db->free_result($request);

		// Make sure no member's birthday is still sticking in the calendar...
		Config::updateModSettings(array(
			'calendar_updated' => time(),
		));

		// Integration rocks!
		call_integration_hook('integrate_delete_members', array($users));

		updateStats('member');

		require_once(Config::$sourcedir . '/Logging.php');
		logActions($log_changes);
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
		if (!allowedTo('moderate_forum') && ((!empty(Config::$modSettings['reserveName']) && $is_name) || !empty(Config::$modSettings['reserveUser']) && !$is_name))
		{
			$reservedNames = explode("\n", Config::$modSettings['reserveNames']);

			// Case sensitive check?
			$checkMe = empty(Config::$modSettings['reserveCase']) ? $checkName : $name;

			// Check each name in the list...
			foreach ($reservedNames as $reserved)
			{
				if ($reserved == '')
					continue;

				// The admin might've used entities too, level the playing field.
				$reservedCheck = Utils::entityDecode($reserved, true);

				// Case sensitive name?
				if (empty(Config::$modSettings['reserveCase']))
					$reservedCheck = Utils::strtolower($reservedCheck);

				// If it's not just entire word, check for it in there somewhere...
				if ($checkMe == $reservedCheck || (Utils::entityStrpos($checkMe, $reservedCheck) !== false && empty(Config::$modSettings['reserveWord'])))
				{
					if ($fatal)
						ErrorHandler::fatalLang('username_reserved', 'password', array($reserved));

					return true;
				}
			}

			$censor_name = $name;
			if (Lang::censorText($censor_name) != $name)
			{
				if ($fatal)
					ErrorHandler::fatalLang('name_censored', 'password', array($name));

				return true;
			}
		}

		// Characters we just shouldn't allow, regardless.
		foreach (array('*') as $char)
		{
			if (strpos($checkName, $char) !== false)
			{
				if ($fatal)
					ErrorHandler::fatalLang('username_reserved', 'password', array($char));

				return true;
			}
		}

		$request = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE ' . (empty($current_id_member) ? '' : 'id_member != {int:current_member}
				AND ') . '({raw:real_name} {raw:operator} LOWER({string:check_name}) OR {raw:member_name} {raw:operator} LOWER({string:check_name}))
			LIMIT 1',
			array(
				'real_name' => Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name',
				'member_name' => Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name',
				'current_member' => $current_id_member,
				'check_name' => $checkName,
				'operator' => strpos($checkName, '%') || strpos($checkName, '_') ? 'LIKE' : '=',
			)
		);
		if (Db::$db->num_rows($request) > 0)
		{
			Db::$db->free_result($request);
			return true;
		}
		Db::$db->free_result($request);

		// Does name case insensitive match a member group name?
		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE {raw:group_name} LIKE {string:check_name}
			LIMIT 1',
			array(
				'group_name' => Db::$db->case_sensitive ? 'LOWER(group_name)' : 'group_name',
				'check_name' => $checkName,
			)
		);
		if (Db::$db->num_rows($request) > 0)
		{
			Db::$db->free_result($request);
			return true;
		}
		Db::$db->free_result($request);

		// Okay, they passed.
		$is_reserved = false;

		// Maybe a mod wants to perform further checks?
		call_integration_hook('integrate_check_name', array($checkName, &$is_reserved, $current_id_member, $is_name));

		return $is_reserved;
	}

	/**
	 * Generate a random validation code.
	 *
	 * @return string A random validation code
	 */
	public static function generateValidationCode(): string
	{
		return bin2hex(Utils::randomBytes(5));
	}

	/**
	 * Log the spider presence online.
	 */
	public static function logSpider(): void
	{
		if (empty(Config::$modSettings['spider_mode']) || empty($_SESSION['id_robot']))
			return;

		// Attempt to update today's entry.
		if (Config::$modSettings['spider_mode'] == 1)
		{
			$date = smf_strftime('%Y-%m-%d', time());
			Db::$db->query('', '
				UPDATE {db_prefix}log_spider_stats
				SET last_seen = {int:current_time}, page_hits = page_hits + 1
				WHERE id_spider = {int:current_spider}
					AND stat_date = {date:current_date}',
				array(
					'current_date' => $date,
					'current_time' => time(),
					'current_spider' => $_SESSION['id_robot'],
				)
			);

			// Nothing updated?
			if (Db::$db->affected_rows() == 0)
			{
				Db::$db->insert('ignore',
					'{db_prefix}log_spider_stats',
					array(
						'id_spider' => 'int', 'last_seen' => 'int', 'stat_date' => 'date', 'page_hits' => 'int',
					),
					array(
						$_SESSION['id_robot'], time(), $date, 1,
					),
					array('id_spider', 'stat_date')
				);
			}
		}
		// If we're tracking better stats than track, better stats - we sort out the today thing later.
		else
		{
			if (Config::$modSettings['spider_mode'] > 2)
			{
				$url = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);
				unset($url['sesc'], $url[Utils::$context['session_var']]);
				$url = Utils::jsonEncode($url);
			}
			else
				$url = '';

			Db::$db->insert('insert',
				'{db_prefix}log_spider_hits',
				array('id_spider' => 'int', 'log_time' => 'int', 'url' => 'string'),
				array($_SESSION['id_robot'], time(), $url),
				array()
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
	public static function loadMemberData($users = array(), int $type = self::LOAD_BY_ID, string $dataset = null): array
	{
		$loaded = self::load($users, $type, $dataset);
		return array_map(fn($user) => $user->id, $loaded);
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
		if (empty($id))
			return false;

		// If the user's data is not already loaded, load it now.
		if (!isset(self::$loaded[$id]))
			self::load((array) $id, self::LOAD_BY_ID, 'profile');

		return self::$loaded[$id]->format($display_custom_fields);
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
	protected function __construct(int $id = null, string|null $dataset = null)
	{
		// No ID given, so load current user.
		if (!isset($id))
		{
			// Only do this once.
			if (!isset(self::$my_id))
			{
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
				if (empty($this->id))
					$this->initializeGuest();
				// Otherwise, update the user's last visit time.
				else
					$this->setLastVisit();

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
				call_integration_hook('integrate_user_info');
			}
		}
		// Reloading the current user requires special handling.
		elseif (isset(self::$my_id) && $id == self::$my_id)
		{
			// Copy over the existing data.
			$this->set(get_object_vars(self::$me));

			$dataset = $dataset ?? $this->chooseMyDataset();

			if (self::$dataset_levels[self::$me->dataset] < self::$dataset_levels[$dataset])
			{
				self::loadUserData((array) $id, self::LOAD_BY_ID, $dataset);
			}

			$this->fixTimezoneSetting();
			$this->setProperties();

			self::$loaded[$id] = $this;
			self::setMe($id);
		}
		// Load the specified member.
		else
		{
			$this->id = $id;

			self::$loaded[$id] = $this;

			if (!isset(self::$profiles[$id]) || self::$dataset_levels[self::$profiles[$id]['dataset']] < self::$dataset_levels[$dataset ?? 'normal'])
			{
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
		$this->options = $profile['options'] ?? array();
		$this->smiley_set = $profile['smiley_set'] ?? '';

		// Localization.
		$this->setLanguage();
		$this->time_format = empty($profile['time_format']) ? Config::$modSettings['time_format'] : $profile['time_format'];
		$this->timezone = $profile['timezone'] ?? Config::$modSettings['default_timezone'];
		$this->time_offset = $profile['time_offset'] ?? 0;

		// Buddies and personal messages.
		$this->buddies = !empty(Config::$modSettings['enable_buddylist']) && !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();
		$this->ignoreusers = !empty($profile['pm_ignore_list']) ? explode(',', $profile['pm_ignore_list']) : array();
		$this->pm_receive_from = $profile['pm_receive_from'] ?? 0;
		$this->pm_prefs = $profile['pm_prefs'] ?? 0;
		$this->messages = $profile['instant_messages'] ?? 0;
		$this->unread_messages = $profile['unread_messages'] ?? 0;
		$this->new_pm = $profile['new_pm'] ?? 0;

		// What does the user want to see or know about?
		$this->alerts = $profile['alerts'] ?? 0;
		$this->ignoreboards = !empty($profile['ignore_boards']) && !empty(Config::$modSettings['allow_ignore_boards']) ? explode(',', $profile['ignore_boards']) : array();

		// Extended membergroup info.
		$this->group_name = $profile['member_group'] ?? '';
		$this->primary_group_name = $profile['primary_group'] ?? '';
		$this->post_group_name = $profile['post_group'] ?? '';
		$this->group_color = $profile['member_group_color'] ?? '';
		$this->post_group_color = $profile['post_group_color'] ?? '';
		$this->icons = empty($profile['icons']) ? array('', '') : explode('#', $profile['icons']);

		// The avatar is a complicated thing, and historically had multiple
		// representations in the code. This supports everything.
		$this->avatar = array_merge(
			array(
				'original_url' => $profile['avatar_original'] ?? '',
				'url' => $profile['avatar'] ?? '',
				'filename' => $profile['filename'] ?? '',
				'custom_dir' => !empty($profile['attachment_type']) && $profile['attachment_type'] == 1,
				'id_attach' => $profile['id_attach'] ?? 0,
				'width' => $profile['attachment_width'] ?? null,
				'height' => $profile['attachment_height'] ?? null,
			),
			self::setAvatarData(array(
				'avatar' => $profile['avatar'] ?? '',
				'email' => $profile['email_address'] ?? '',
				'filename' => $profile['filename'] ?? '',
			)),
		);

		// Info about stuff related to permissions.
		// Note that we set $this->permissions elsewhere.
		$this->warning = $profile['warning'] ?? 0;
		$this->can_manage_boards = !empty($this->is_admin) || (!empty(Config::$modSettings['board_manager_groups']) && !empty($this->groups) && count(array_intersect($this->groups, explode(',', Config::$modSettings['board_manager_groups']))) > 0);

		foreach (self::buildQueryBoard($this->id) as $key => $value)
			$this->{$key} = $value;

		// What dataset did we load for this user?
		$this->dataset = $profile['dataset'];

		// An easy way for mods to add or adjust properties.
		call_integration_hook('integrate_user_properties', array($this));
	}

	/**
	 * Wrapper for integrate_verify_user hook. Allows integrations to verify
	 * the current user's identity for us.
	 */
	protected function integrateVerifyUser(): void
	{
		if (count($integration_ids = call_integration_hook('integrate_verify_user')) === 0)
			return;

		foreach ($integration_ids as $integration_id)
		{
			if (intval($integration_id) > 0)
			{
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
		if (!empty(self::$my_id))
			return;

		if (isset($_COOKIE[Config::$cookiename]))
		{
			require_once(Config::$sourcedir . '/Subs-Auth.php');

			// First try 2.1 json-format cookie
			$cookie_data = Utils::jsonDecode($_COOKIE[Config::$cookiename], true, false);

			// Legacy format (for recent 2.0 --> 2.1 upgrades)
			if (empty($cookie_data))
				$cookie_data = safe_unserialize($_COOKIE[Config::$cookiename]);

			list(self::$my_id, $this->passwd, $login_span, $cookie_domain, $cookie_path) = array_pad((array) $cookie_data, 5, '');

			self::$my_id = !empty(self::$my_id) && strlen($this->passwd) > 0 ? (int) self::$my_id : 0;

			// Make sure the cookie is set to the correct domain and path
			if (array($cookie_domain, $cookie_path) !== url_parts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies'])))
			{
				setLoginCookie((int) $login_span - time(), self::$my_id);
			}
		}
		elseif (isset($_SESSION['login_' . Config::$cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty(Config::$modSettings['disableCheckUA'])))
		{
			// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
			$cookie_data = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename], true);

			if (empty($cookie_data))
				$cookie_data = safe_unserialize($_SESSION['login_' . Config::$cookiename]);

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
		if (!isset($_REQUEST['action']))
		{
			$dataset = 'normal';
		}
		// Popups, AJAX, etc.
		elseif (is_filtered_request(Forum::$unlogged_actions, 'action'))
		{
			$dataset = 'basic';
		}
		// Profile and personal messages (except the popups)
		elseif (in_array($_REQUEST['action'], array('profile', 'pm')))
		{
			$dataset = 'profile';
		}
		// Who's Online
		elseif (in_array($_REQUEST['action'], array('who')))
		{
			$dataset = 'normal';
		}
		// Everything else.
		else
		{
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
		if (empty(self::$my_id))
			return;

		// Did we find 'im?  If not, junk it.
		if (!empty(self::$profiles[self::$my_id]))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($this->already_verified) && $this->already_verified === true)
			{
				$check = true;
			}
			// SHA-512 hash should be 128 characters long.
			elseif (strlen($this->passwd) == 128)
			{
				require_once(Config::$sourcedir . '/Subs-Auth.php');

				$check = hash_equals(hash_salt(self::$profiles[self::$my_id]['passwd'], self::$profiles[self::$my_id]['password_salt']), $this->passwd);
			}
			else
			{
				$check = false;
			}

			// Wrong password or not activated - either way, you're going nowhere.
			self::$my_id = $check && (self::$profiles[self::$my_id]['is_activated'] == 1 || self::$profiles[self::$my_id]['is_activated'] == 11) ? (int) self::$profiles[self::$my_id]['id_member'] : 0;
		}
		else
		{
			self::$my_id = 0;
		}

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (empty(self::$my_id))
		{
			Login2::validatePasswordFlood(
				!empty(self::$profiles[self::$my_id]['id_member']) ? self::$profiles[self::$my_id]['id_member'] : self::$my_id,
				!empty(self::$profiles[self::$my_id]['member_name']) ? self::$profiles[self::$my_id]['member_name'] : '',
				!empty(self::$profiles[self::$my_id]['passwd_flood']) ? self::$profiles[self::$my_id]['passwd_flood'] : false,
				self::$my_id != 0
			);
		}
	}

	/**
	 * If appropriate for this user, performs two factor authentication check.
	 */
	protected function verifyTfa(): void
	{
		if (empty(self::$my_id) || empty(Config::$modSettings['tfa_mode']))
			return;

		// Check if we are forcing TFA
		$force_tfasetup = Config::$modSettings['tfa_mode'] >= 2 && empty(self::$profiles[self::$my_id]['tfa_secret']) && SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml');

		// Don't force TFA on popups
		if ($force_tfasetup)
		{
			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'profile' && isset($_REQUEST['area']) && in_array($_REQUEST['area'], array('popup', 'alerts_popup')))
			{
				$force_tfasetup = false;
			}
			elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == 'pm' && (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'popup'))
			{
				$force_tfasetup = false;
			}

			call_integration_hook('integrate_force_tfasetup', array(&$force_tfasetup));
		}

		// Validate for Two Factor Authentication
		if (!empty(self::$profiles[self::$my_id]['tfa_secret']) && (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], array('login2', 'logintfa'))))
		{
			$tfacookie = Config::$cookiename . '_tfa';
			$tfasecret = null;

			$verified = call_integration_hook('integrate_verify_tfa', array(self::$my_id, self::$profiles[self::$my_id]));

			if (empty($verified) || !in_array(true, $verified))
			{
				require_once(Config::$sourcedir . '/Subs-Auth.php');

				if (!empty($_COOKIE[$tfacookie]))
				{
					$tfa_data = Utils::jsonDecode($_COOKIE[$tfacookie], true);

					list($tfamember, $tfasecret) = array_pad((array) $tfa_data, 2, '');

					if (!isset($tfamember, $tfasecret) || (int) $tfamember != self::$my_id)
						$tfasecret = null;
				}

				// They didn't finish logging in before coming here? Then they're no one to us.
				if (empty($tfasecret) || !hash_equals(hash_salt(self::$profiles[self::$my_id]['tfa_backup'], self::$profiles[self::$my_id]['password_salt']), $tfasecret))
				{
					setLoginCookie(-3600, self::$my_id);
					self::$profiles[self::$my_id] = array();
					self::$my_id = 0;
				}
			}
		}
		// When authenticating their two factor code, make sure to reset their ID for security
		elseif (!empty(self::$profiles[self::$my_id]['tfa_secret']) && $_REQUEST['action'] == 'logintfa')
		{
			Utils::$context['tfa_member'] = self::$profiles[self::$my_id];
			self::$profiles[self::$my_id] = array();
			self::$my_id = 0;
		}
		// Are we forcing 2FA? Need to check if the user groups actually require 2FA
		elseif ($force_tfasetup)
		{
			// Only do this if we are just forcing SOME membergroups
			if (Config::$modSettings['tfa_mode'] == 2)
			{
				//Build an array of ALL user membergroups.
				$this->setGroups();

				//Find out if any group requires 2FA
				$request = Db::$db->query('', '
					SELECT COUNT(id_group) AS total
					FROM {db_prefix}membergroups
					WHERE tfa_required = {int:tfa_required}
						AND id_group IN ({array_int:full_groups})',
					array(
						'tfa_required' => 1,
						'full_groups' => $this->groups,
					)
				);
				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);
			}
			// Simplifies logic in the next "if"
			else
			{
				$row['total'] = 1;
			}

			$area = !empty($_REQUEST['area']) ? $_REQUEST['area'] : '';
			$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';

			if (
				$row['total'] > 0
				&& (
					!in_array($action, array('profile', 'logout'))
					|| (
						$action == 'profile'
						&& $area != 'tfasetup'
					)
				)
			)
			{
				redirectexit('action=profile;area=tfasetup;forced');
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
				|| !in_array($_REQUEST['action'], array('.xml', 'login2', 'logintfa'))
			)
			&& empty($_SESSION['id_msg_last_visit'])
			&& (
				empty(CacheApi::$enable)
				|| ($_SESSION['id_msg_last_visit'] = CacheApi::get('user_last_visit-' . self::$my_id, 5 * 3600)) === null
			)
		)
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = Db::$db->query('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => self::$profiles[self::$my_id]['id_msg_last_visit'],
				)
			);
			list($visitTime) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			$_SESSION['id_msg_last_visit'] = self::$profiles[self::$my_id]['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				self::updateMemberData(self::$my_id, array('id_msg_last_visit' => (int) Config::$modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));

				self::$profiles[self::$my_id]['last_login'] = time();

				if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
				{
					CacheApi::put('user_settings-' . self::$my_id, self::$profiles[self::$my_id], 60);
				}

				if (!empty(CacheApi::$enable))
				{
					CacheApi::put('user_last_visit-' . self::$my_id, $_SESSION['id_msg_last_visit'], 5 * 3600);
				}
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
		{
			$_SESSION['id_msg_last_visit'] = self::$profiles[self::$my_id]['id_msg_last_visit'];
		}
	}

	/**
	 * Sets User::$profiles[0], cookie, etc., to appropriate values for a guest.
	 */
	protected function initializeGuest(): void
	{
		// This is what a guest's variables should be.
		self::$profiles[0] = array(
			'dataset' => 'basic',
		);

		if (isset($_COOKIE[Config::$cookiename]) && empty(Utils::$context['tfa_member']))
			$_COOKIE[Config::$cookiename] = '';

		// Expire the 2FA cookie
		if (isset($_COOKIE[Config::$cookiename . '_tfa']) && empty(Utils::$context['tfa_member']))
		{
			$tfa_data = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);

			list(,, $exp) = array_pad((array) $tfa_data, 3, 0);

			if (time() > $exp)
			{
				require_once(Config::$sourcedir . '/Subs-Auth.php');

				$_COOKIE[Config::$cookiename . '_tfa'] = '';
				setTFACookie(-3600, 0, '');
			}
		}

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
		{
			createToken('login');
		}
		else
		{
			list(Utils::$context['login_token_var'],,, Utils::$context['login_token']) = $_SESSION['token']['post-login'];
		}
	}

	/**
	 * Ensures timezone and time_offset are both set to correct values.
	 */
	protected function fixTimezoneSetting(): void
	{
		if (!empty($this->id))
		{
			// Figure out the new time offset.
			if (!empty(self::$profiles[$this->id]['timezone']))
			{
				// Get the offsets from UTC for the server, then for the user.
				$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
				$tz_user = new \DateTimeZone(self::$profiles[$this->id]['timezone']);
				$time_system = new \DateTime('now', $tz_system);
				$time_user = new \DateTime('now', $tz_user);
				self::$profiles[$this->id]['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
			}
			// We need a time zone.
			else
			{
				if (!empty(self::$profiles[$this->id]['time_offset']))
				{
					$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
					$time_system = new \DateTime('now', $tz_system);

					self::$profiles[$this->id]['timezone'] = @timezone_name_from_abbr('', $tz_system->getOffset($time_system) + self::$profiles[$this->id]['time_offset'] * 3600, (int) $time_system->format('I'));
				}

				if (empty(self::$profiles[$this->id]['timezone']))
				{
					self::$profiles[$this->id]['timezone'] = Config::$modSettings['default_timezone'];
					self::$profiles[$this->id]['time_offset'] = 0;
				}
			}
		}
		// Guests use the forum default.
		else
		{
			self::$profiles[$this->id]['timezone'] = Config::$modSettings['default_timezone'];
			self::$profiles[$this->id]['time_offset'] = 0;
		}
	}

	/**
	 * Determines which membergroups the current user belongs to.
	 */
	protected function setGroups(): void
	{
		if (!empty($this->id))
		{
			$this->group_id = (int) self::$profiles[$this->id]['id_group'];
			$this->post_group_id = (int) self::$profiles[$this->id]['id_post_group'];
			$this->additional_groups = array_map('intval', array_filter(explode(',', self::$profiles[$this->id]['additional_groups'])));
			$this->groups = array_merge(array($this->group_id, $this->post_group_id), $this->additional_groups);
		}
		// Guests are only part of the guest group.
		else
		{
			$this->group_id = -1;
			$this->post_group_id = -1;
			$this->additional_groups = array();
			$this->groups = array(-1);
		}
	}

	/**
	 * Do we perhaps think this is a search robot?
	 */
	protected function setPossiblyRobot(): void
	{
		// This is a logged in user, so definitely not a spider.
		if (!empty($this->id))
		{
			$this->possibly_robot = false;
		}
		// A guest, so check further...
		else
		{
			// Check every five minutes just in case...
			if ((!empty(Config::$modSettings['spider_mode']) || !empty(Config::$modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
			{
				if (isset($_SESSION['id_robot']))
					unset($_SESSION['id_robot']);

				$_SESSION['robot_check'] = time();

				// We cache the spider data for ten minutes if we can.
				if (($spider_data = CacheApi::get('spider_search', 600)) === null)
				{
					$spider_data = array();

					$request = Db::$db->query('', '
						SELECT id_spider, user_agent, ip_info
						FROM {db_prefix}spiders
						ORDER BY LENGTH(user_agent) DESC',
						array(
						)
					);
					while ($row = Db::$db->fetch_assoc($request))
					{
						$spider_data[] = $row;
					}
					Db::$db->free_result($request);

					CacheApi::put('spider_search', $spider_data, 600);
				}

				if (empty($spider_data))
				{
					$this->possibly_robot = false;
				}
				else
				{
					// Only do these bits once.
					$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

					foreach ($spider_data as $spider)
					{
						// User agent is easy.
						if (!empty($spider['user_agent']) && strpos($ci_user_agent, strtolower($spider['user_agent'])) !== false)
						{
							$_SESSION['id_robot'] = $spider['id_spider'];
						}
						// IP stuff is harder.
						elseif ($_SERVER['REMOTE_ADDR'])
						{
							$ips = explode(',', $spider['ip_info']);

							foreach ($ips as $ip)
							{
								if ($ip === '')
									continue;

								$ip = ip2range($ip);

								if (!empty($ip))
								{
									if (inet_ptod($ip['low']) <= inet_ptod($_SERVER['REMOTE_ADDR']) && inet_ptod($ip['high']) >= inet_ptod($_SERVER['REMOTE_ADDR']))
									{
										$_SESSION['id_robot'] = $spider['id_spider'];
									}
								}
							}
						}

						if (isset($_SESSION['id_robot']))
							break;
					}

					// If this is low server tracking then log the spider here as opposed to the main logging function.
					if (!empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] == 1 && !empty($_SESSION['id_robot']))
					{
						self::logSpider();
					}

					$this->possibly_robot = !empty($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
				}
			}
			elseif (!empty(Config::$modSettings['spider_mode']))
			{
				$this->possibly_robot = $_SESSION['id_robot'] ?? 0;
			}
			// If we haven't turned on proper spider hunts then have a guess!
			else
			{
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
		if (empty(Config::$modSettings['userLanguage']))
		{
			$this->language = Config::$language;
			return;
		}

		// Which language does this user prefer?
		$this->language = empty(self::$profiles[$this->id]['lngfile']) ? Config::$language : self::$profiles[$this->id]['lngfile'];

		// Allow the user to change their language.
		$languages = Lang::get();

		// Change was requested in URL parameters.
		if (!empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
		{
			$this->language = strtr($_GET['language'], './\\:', '____');

			// Make it permanent for members.
			if (!empty($this->id))
			{
				self::updateMemberData($this->id, array('lngfile' => $this->language));
			}
			else
			{
				$_SESSION['language'] = $this->language;
			}

			// Reload same URL with new language, if applicable.
			if (isset($_SESSION['old_url']))
				redirectexit($_SESSION['old_url']);
		}
		// Carry forward the last language request in this session, if any.
		elseif (!empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		{
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
		// Keep track of which IDs we load during this run.
		$loaded_ids = array();

		// If $users is supposed to contain ID numbers, accept only integers.
		if ($type === self::LOAD_BY_ID)
			$users = array_map('intval', $users);

		// Avoid duplication.
		$users = array_unique($users);

		// For guests, there is no data to load, so just fake it.
		if (in_array(0, $users))
		{
			self::$profiles[0] = array('dataset' => $dataset);
			$loaded_ids[] = 0;
			$users = array_filter($users);
		}

		// If there is no one to load, bail out now.
		if (empty($users))
			return $loaded_ids;

		// Is the member data already loaded?
		if ($type === self::LOAD_BY_ID)
		{
			foreach ($users as $key => $id)
			{
				if (!isset(self::$profiles[$id]))
					continue;

				if (!isset(self::$profiles[$id]['dataset']))
					continue;

				if (self::$dataset_levels[self::$profiles[$id]['dataset']] >= self::$dataset_levels[$dataset])
				{
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Is the member data cached?
		if ($type === self::LOAD_BY_ID && !empty(CacheApi::$enable))
		{
			foreach ($users as $key => $id)
			{
				if ($id === (self::$my_id ?? NAN))
				{
					if (CacheApi::$enable < 2)
						continue;

					if (($data = CacheApi::get('user_settings-' . $id, 60) == null))
						continue;
				}
				else
				{
					if (CacheApi::$enable < 3)
						continue;

					if (($data = CacheApi::get('member_data-' . $dataset . '-' . $id, 240)) == null)
						continue;
				}

				// Does the cached data have everything we need?
				if (self::$dataset_levels[$data['dataset']] >= self::$dataset_levels[$dataset])
				{
					self::$profiles[$id] = $data;
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Look up any un-cached member data.
		if (!empty($users))
		{
			$select_columns = array('mem.*');
			$select_tables = array('{db_prefix}members AS mem');

			switch ($dataset)
			{
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

			switch ($type)
			{
				case self::LOAD_BY_EMAIL:
					$where = 'mem.email_address' . (count($users) > 1 ? ' IN ({array_string:users})' : ' = {string:users}');
					break;

				case self::LOAD_BY_NAME:
					if (Db::$db->case_sensitive)
					{
						$where = 'LOWER(mem.member_name)';
						$users = array_map('strtolower', $users);
					}
					else
					{
						$where = 'mem.member_name';
					}

					$where .= count($users) > 1 ? ' IN ({array_string:users})' : ' = {string:users}';
					break;

				default:
					$where = 'mem.id_member' . (count($users) > 1 ? ' IN ({array_int:users})' : ' = {int:users}');
					break;
			}

			// Allow mods to easily add to the selected member data
			call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables, &$dataset));

			// Load the members' data.
			$request = Db::$db->query('', '
				SELECT ' . implode(",\n\t\t\t\t\t", $select_columns) . '
				FROM ' . implode("\n\t\t\t\t\t", $select_tables) . '
				WHERE ' . $where . (count($users) > 1 ? '' : '
				LIMIT 1'),
				array(
					'blank_string' => '',
					'users' => count($users) > 1 ? $users : reset($users),
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$row['id_member'] = (int) $row['id_member'];

				// If the image proxy is enabled, we still want the original URL when they're editing the profile...
				$row['avatar_original'] = $row['avatar'] ?? '';

				// Take care of proxying the avatar if required.
				if (!empty($row['avatar']))
					$row['avatar'] = get_proxied_url($row['avatar']);

				// Keep track of the member's normal member group.
				$row['primary_group'] = $row['member_group'] ?? '';
				$row['member_group'] = $row['member_group'] ?? '';
				$row['post_group'] = $row['post_group'] ?? '';
				$row['member_group_color'] = $row['member_group_color'] ?? '';
				$row['post_group_color'] = $row['post_group_color'] ?? '';

				// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
				$row['ignore_boards'] = rtrim($row['ignore_boards'], ',');

				// Unpack the IP addresses.
				if (isset($row['member_ip']))
					$row['member_ip'] = inet_dtop($row['member_ip']);

				if (isset($row['member_ip2']))
					$row['member_ip2'] = inet_dtop($row['member_ip2']);

				$row['is_online'] = $row['is_online'] ?? $row['id_member'] === (self::$my_id ?? NAN);

				// Declare this for now. We'll fill it in later.
				$row['options'] = array();

				// Save it.
				if (!isset(self::$profiles[$row['id_member']]))
					self::$profiles[$row['id_member']] = array();

				// Use array_merge here to avoid data loss if we somehow call
				// this twice for the same member but with different datasets.
				self::$profiles[$row['id_member']] = array_merge(self::$profiles[$row['id_member']], $row);

				// If this is the current user's data, alias it to User::$settings.
				if ($row['id_member'] === (self::$my_id ?? NAN))
					self::$settings = &self::$profiles[$row['id_member']];

				$loaded_ids[] = $row['id_member'];
			}
			Db::$db->free_result($request);

			if (!empty($loaded_ids) && $dataset !== 'minimal')
				self::loadOptions($loaded_ids);

			// This hook's name is due to historical reasons.
			call_integration_hook('integrate_load_min_user_settings', array(&self::$profiles));

			if ($type === self::LOAD_BY_ID && !empty(CacheApi::$enable))
			{
				foreach ($users as $id)
				{
					if ($id === (self::$my_id ?? NAN))
					{
						if (CacheApi::$enable >= 2)
							CacheApi::put('user_settings-' . $id, self::$profiles[$id], 60);
					}
					elseif (CacheApi::$enable >= 3)
					{
						CacheApi::put('member_data-' . $dataset . '-' . $id, self::$profiles[$id], 240);
					}
				}
			}
		}

		foreach ($loaded_ids as $id)
			self::$profiles[$id]['dataset'] = $dataset;

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

		$request = Db::$db->query('', '
			SELECT id_member, id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN ({array_int:ids})',
			array(
				'ids' => $ids,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			self::$profiles[$row['id_member']]['options'][$row['variable']] = $row['value'];
		}
		Db::$db->free_result($request);
	}

}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\User::exportStatic'))
	User::exportStatic();

?>