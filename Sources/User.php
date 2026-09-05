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

namespace SMF;

use SMF\Actions\Admin\ACP;
use SMF\Actions\Admin\Bans;
use SMF\Actions\Logout;
use SMF\Actions\Moderation\ReportedContent;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\v3_0\Members as MembersTable;
use SMF\Permissions\Permission;
use SMF\Permissions\PermissionProfile;
use SMF\Permissions\UserPermissionSet;
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
 * For the sake of backward compatibility, user data can be accessed in a number
 * of alternative formats:
 *
 * - The deprecated global $user_info is now simply a reference to User::$me.
 *   The properties of User::$me can be accessed as if they were array elements,
 *   so $user_info['id'] is interchangeable with User::$me->id.
 *
 * - Similarly, the deprecated global $context['user'] is now simply a reference
 *   to User::$me.
 *
 * - The deprecated global $user_profile is now a reference to User::$profiles.
 *   Note that accessing User::$profiles from outside this class is also
 *   deprecated; new code should work with User objects via User::$loaded rather
 *   than working with the User::profiles array.
 *
 * - Similarly, the deprecated global $user_settings array is now a reference to
 *   User::$profiles[User::$me->id].
 *
 * - Similarly, the deprecated global $cur_profile array is now a reference to
 *   User::$profiles[$id], where $id is the ID of the user whose profile is
 *   being viewed.
 *
 * - The data previously available in the deprecated $memberContext array is now
 *   available via the $formatted property of a User object. For example, where
 *   old code might have used $memberContext[$id_member], the same information
 *   is now available via User::$loaded[$id_member]->formatted. Note that, in
 *   the same way that loadMemberContext($id_member) had to be called in order
 *   to populate $memberContext[$id_member], User::$loaded[$id_member]->format()
 *   must be called in order to populate User::$loaded[$id_member]->formatted.
 *
 * NOTE: It is STRONGLY RECOMMENDED that new and updated code use User::$me and
 * User::$loaded directly, rather than using any of the deprecated global
 * variables. A future version of SMF will remove backward compatibility support
 * for these deprecated globals.
 */
class User implements \ArrayAccess
{
	use ArrayAccessHelper;
	use BackwardCompatibility;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Constants to define loading methods.
	 */
	public const LOAD_BY_ID = 0;
	public const LOAD_BY_NAME = 1;
	public const LOAD_BY_EMAIL = 2;

	/**
	 * Constants to define activation states.
	 */
	public const NOT_ACTIVATED = 0;
	public const ACTIVATED = 1;
	public const UNVALIDATED = 2;
	public const UNAPPROVED = 3;
	public const REQUESTED_DELETE = 4;
	public const NEED_COPPA = 5;
	public const REQUESTED_DELETE_ANONYMIZE = 6;
	public const BANNED = 10;
	public const ACTIVATED_BANNED = 11;
	public const UNVALIDATED_BANNED = 12;
	public const UNAPPROVED_BANNED = 13;
	public const REQUESTED_DELETE_BANNED = 14;
	public const NEED_COPPA_BANNED = 15;
	public const REQUESTED_DELETE_ANONYMIZE_BANNED = 16;

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
	public array $additional_groups;

	/**
	 * @var array
	 *
	 * IDs of all the groups this user belongs to.
	 */
	public array $groups;

	/**
	 * @var bool
	 *
	 * If true, probably a search engine spider.
	 */
	public bool $possibly_robot;

	/**
	 * @var string
	 *
	 * Info about how many times they recently entered the wrong password.
	 *
	 * This is used to prevent brute force attempts to find someone's password.
	 */
	public string $passwd_flood;

	/**
	 * @var bool
	 *
	 * Whether this user is a guest.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 */
	public bool $is_guest {
		// @todo Once \ArrayAccess compatibility is no longer required, change this hook to
		// `get => empty($this->id);`
		&get {
			$this->is_guest = empty($this->id);

			return $this->is_guest;
		}
	}

	/**
	 * @var bool
	 *
	 * Whether this user is an admin.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 */
	public bool $is_admin {
		// @todo Once \ArrayAccess compatibility is no longer required, change this hook to
		// `get => \in_array(Group::ADMIN, $this->groups ?? []);`
		&get {
			$this->is_admin = \in_array(Group::ADMIN, $this->groups ?? []);

			return $this->is_admin;
		}
	}

	/**
	 * @var bool
	 *
	 * Whether this user is a moderator on the current board.
	 */
	public bool $is_mod {
		// @todo Once \ArrayAccess compatibility is no longer required, change this hook to
		// `get => $this->isMod();`
		&get => $this->isMod();
		set {
			$this->isMod($value);
		}
	}

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
	 * Whether this is the current user.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 */
	public bool $is_me {
		// @todo Once \ArrayAccess compatibility is no longer required, change this hook to
		// `get => $this::class === self::class ? $this === (self::$me ?? null) : ($this->id ?? NAN) === (self::$my_id ?? NAN);`
		&get {
			$this->is_me = $this::class === self::class ? $this === (self::$me ?? null) : ($this->id ?? NAN) === (self::$my_id ?? NAN);

			return $this->is_me;
		}
	}

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
	public int $total_time_logged_in;

	/**
	 * @var bool
	 *
	 * Whether the user wants their login cookie not to expire.
	 */
	public bool $stay_logged_in;

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
	public string $time_format {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->time_format ??= !empty($this->real_time_format) ? $this->real_time_format : (Config::$modSettings['time_format'] ?? '%F %k:%M');

			return $this->time_format;
		}
	}

	/**
	 * @var string
	 *
	 * The user's preferred time format as recorded in the database.
	 *
	 * This exists because the theme might temporarily override the $time_format
	 * property, and we wouldn't want that temporary change to become permanent.
	 */
	public protected(set) string $real_time_format;

	/**
	 * @var string
	 *
	 * The user's time zone.
	 */
	public string $timezone;

	/**
	 * @var float
	 *
	 * How many hours the user's time zone is offset from the forum's default
	 * time zone.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 */
	public float $time_offset {
		&get {
			$this->time_offset = !isset($this->timezone) ? 0 : ((new \DateTimeZone($this->timezone))->getOffset(new \DateTime('now')) - (new \DateTimeZone(Config::$modSettings['default_timezone'] ?? date_default_timezone_get()))->getOffset(new \DateTime('now'))) / 3600;

			return $this->time_offset;
		}
	}

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
	 * @var string
	 *
	 * URL of this user's profile page. Will be an empty string for guests.
	 */
	public string $href {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->href ??= empty($this->id) ? '' : Config::$scripturl . '?action=profile;u=' . $this->id;

			return $this->href;
		}
	}

	/**
	 * @var string
	 *
	 * HTML link to this user's profile page. Will be an empty string for guests.
	 */
	public string $link {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->link ??= empty($this->id) || empty($this->name) ? '' : '<a href="' . $this->href . '" title="' . Lang::getTxt('view_profile_of_username', ['name' => $this->name], file: 'General') . '">' . $this->name . '</a>';

			return $this->link;
		}
	}

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
	public array $buddies;

	/**
	 * @var array
	 *
	 * IDs of users that this user is ignoring.
	 */
	public array $ignoreusers;

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
	public array $ignoreboards;

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
	 *
	 * (Exactly which group will depend on the situation.)
	 */
	public array $icons;

	/**
	 * @var int
	 *
	 * ID of the user's primary group.
	 *
	 * Does not change even if the user is a moderator on the current board.
	 */
	public int $primary_group_id;

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
	 * The color associated with this user's primary group.
	 *
	 * Does not change even if the user is a moderator on the current board.
	 */
	public string $primary_group_color;

	/**
	 * @var array
	 *
	 * The icons associated with this user's primary group.
	 *
	 * Does not change even if the user is a moderator on the current board.
	 */
	public array $primary_group_icons;

	/**
	 * @var Avatar
	 *
	 * The user's avatar.
	 */
	public Avatar $avatar;

	/**
	 * @var array
	 *
	 * A collection of UserPermissionSet instances, organized by board.
	 *
	 * The UserPermissionSet for this user's global (a.k.a. general) permissions
	 * is stored in $this->permission_sets[0].
	 *
	 * Otherwise, keys are board IDs and values are UserPermissionSet instances
	 * for those boards.
	 */
	public array $permission_sets;

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
	 * @var UserDataset
	 *
	 * The dataset that was loaded for this user.
	 *
	 * Initially set to UserDataset::None. Will change once this user's
	 * data has been loaded.
	 */
	public protected(set) UserDataset $dataset = UserDataset::None;

	/**
	 * @var array
	 *
	 * Formatted versions of this user's properties, suitable for display.
	 */
	public array $formatted = [];

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->id.
	 *
	 * @deprecated 3.0
	 */
	public int $id_member {
		&get => $this->id;
		set {
			$this->id = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->username.
	 *
	 * @deprecated 3.0
	 */
	public string $member_name {
		&get => $this->username;
		set {
			$this->username = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->name.
	 *
	 * @deprecated 3.0
	 */
	public string $real_name {
		&get => $this->name;
		set {
			$this->name = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->name.
	 *
	 * @deprecated 3.0
	 */
	public string $display_name {
		&get => $this->name;
		set {
			$this->name = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->email.
	 *
	 * @deprecated 3.0
	 */
	public string $email_address {
		&get => $this->email;
		set {
			$this->email = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->language.
	 *
	 * @deprecated 3.0
	 */
	public string $lngfile {
		&get => $this->language;
		set {
			$this->language = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->ip.
	 *
	 * @deprecated 3.0
	 */
	public string $member_ip {
		&get => $this->ip;
		set {
			$this->ip = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->ip2.
	 *
	 * @deprecated 3.0
	 */
	public string $member_ip2 {
		&get => $this->ip2;
		set {
			$this->ip2 = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->title.
	 *
	 * @deprecated 3.0
	 */
	public string $usertitle {
		&get => $this->title;
		set {
			$this->title = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->title.
	 *
	 * @deprecated 3.0
	 */
	public string $blurb {
		&get => $this->title;
		set {
			$this->title = $value;
		}
	}

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->theme.
	 *
	 * @deprecated 3.0
	 */
	public int $id_theme {
		&get => $this->theme;
		set {
			$this->theme = $value;
		}
	}

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->post_group_id.
	 *
	 * @deprecated 3.0
	 */
	public int $id_post_group {
		&get => $this->post_group_id;
		set {
			$this->post_group_id = $value;
		}
	}

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->group_id.
	 *
	 * @deprecated 3.0
	 */
	public int $id_group {
		&get => $this->group_id;
		set {
			$this->group_id = $value;
		}
	}

	/**
	 * @var array
	 *
	 * Backward compatibility alias of $this->ignoreusers.
	 *
	 * @deprecated 3.0
	 */
	public array $pm_ignore_list {
		&get => $this->ignoreusers;
		set {
			$this->ignoreusers = $value;
		}
	}

	/**
	 * @var array
	 *
	 * Backward compatibility alias of $this->buddies.
	 *
	 * @deprecated 3.0
	 */
	public array $buddy_list {
		&get => $this->buddies;
		set {
			$this->buddies = $value;
		}
	}

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->messages.
	 *
	 * @deprecated 3.0
	 */
	public int $instant_messages {
		&get => $this->messages;
		set {
			$this->messages = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->website['url'].
	 *
	 * @deprecated 3.0
	 */
	public string $website_url {
		&get => $this->website['url'];
		set {
			$this->website['url'] = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->website['title'].
	 *
	 * @deprecated 3.0
	 */
	public string $website_title {
		&get => $this->website['title'];
		set {
			$this->website['title'] = $value;
		}
	}

	/**
	 * @var array
	 *
	 * Backward compatibility alias of $this->ignoreboards.
	 *
	 * @deprecated 3.0
	 */
	public array $ignore_boards {
		&get => $this->ignoreboards;
		set {
			$this->ignoreboards = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->group_name.
	 *
	 * @deprecated 3.0
	 */
	public string $member_group {
		&get => $this->group_name;
		set {
			$this->group_name = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->primary_group_name.
	 *
	 * @deprecated 3.0
	 */
	public string $primary_group {
		&get => $this->primary_group_name;
		set {
			$this->primary_group_name = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->group_color.
	 *
	 * @deprecated 3.0
	 */
	public string $member_group_color {
		&get => $this->group_color;
		set {
			$this->group_color = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->birthdate.
	 *
	 * @deprecated 3.0
	 */
	public string $birth_date {
		&get => $this->birthdate;
		set {
			$this->birthdate = $value;
		}
	}

	/**
	 * @var int
	 *
	 * Backward compatibility alias of $this->last_login.
	 *
	 * @deprecated 3.0
	 */
	public int $last_login_timestamp {
		&get => $this->last_login;
		set {
			$this->last_login = $value;
		}
	}

	/**
	 * @var bool
	 *
	 * Backward compatibility alias of !$this->is_guest.
	 *
	 * @deprecated 3.0
	 */
	public bool $is_logged {
		&get {
			// Intentionally does not use $this->is_logged in order to keep this
			// property virtual rather than backed, because backed properties
			// cannot have both &get and set hooks.
			$is_logged = !($this->is_guest ?? true);

			return $is_logged;
		}
		set {
			$this->is_guest = !$value;
		}
	}

	/**
	 * @var bool
	 *
	 * Backward compatibility alias of Profile::$member->is_me.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 *
	 * @deprecated 3.0
	 */
	public bool $is_owner {
		&get {
			$this->is_owner = $this->is_me && (Profile::$member->is_me ?? false);

			return $this->is_owner;
		}
	}

	/**
	 * @var bool
	 *
	 * Backward compatibility alias of Topic::$info->started_by_me.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 *
	 * @deprecated 3.0
	 */
	public bool $started {
		&get {
			$this->started = $this->is_me && (Topic::$info->started_by_me ?? false);

			return $this->started;
		}
	}

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
	 * @var self
	 *
	 * Instance of this class for the current user.
	 */
	public static self $me;

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
	 *
	 * @deprecated 3.0 In future versions of SMF this will either become an
	 *    internal static property or be eliminated entirely. Either way, it
	 *    will not be available in the public scope.
	 */
	public static array $profiles = [];

	/**
	 * @var array
	 *
	 * Basic data from the database about the current user.
	 * A reference to User::$profiles[User::$my_id].
	 *
	 * @deprecated 3.0 Only exists for backward compatibility reasons.
	 */
	public static $settings;

	/**
	 * @var object
	 *
	 * Processed data about the current user.
	 * This is set to a reference to User::$me once the latter exists.
	 *
	 * @deprecated 3.0 Only exists for backward compatibility reasons.
	 */
	public static $info;

	/**
	 * @var array
	 *
	 * Alternative way to get formatted data about users.
	 * A reference to User::$loaded[$id]->formatted (where $id is a user ID).
	 *
	 * @deprecated 3.0 Only exists for backward compatibility reasons.
	 */
	public static $memberContext;

	/**
	 * @var array
	 *
	 * Known columns in the members table and their type indicators as used in
	 * SMF's database parameter substitution system.
	 *
	 * Additional columns may be added at runtime.
	 */
	public static array $column_types = [
		'id_member' => 'int',
		'member_name' => 'string',
		'date_registered' => 'int',
		'posts' => 'int',
		'id_group' => 'int',
		'lngfile' => 'string',
		'last_login' => 'int',
		'real_name' => 'string',
		'instant_messages' => 'int',
		'unread_messages' => 'int',
		'new_pm' => 'int',
		'alerts' => 'int',
		'buddy_list' => 'string',
		'pm_ignore_list' => 'string',
		'pm_prefs' => 'int',
		'passwd' => 'string',
		'email_address' => 'string',
		'personal_text' => 'string',
		'birthdate' => 'date',
		'website_title' => 'string',
		'website_url' => 'string',
		'show_online' => 'int',
		'time_format' => 'string',
		'signature' => 'string',
		'avatar' => 'string',
		'usertitle' => 'string',
		'member_ip' => 'inet',
		'member_ip2' => 'inet',
		'secret_question' => 'string',
		'secret_answer' => 'string',
		'id_theme' => 'int',
		'is_activated' => 'int',
		'validation_code' => 'string',
		'id_msg_last_visit' => 'int',
		'additional_groups' => 'string',
		'smiley_set' => 'string',
		'id_post_group' => 'int',
		'total_time_logged_in' => 'int',
		'password_salt' => 'string',
		'ignore_boards' => 'string',
		'warning' => 'int',
		'passwd_flood' => 'string',
		'pm_receive_from' => 'int',
		'timezone' => 'string',
		'tfa_secret' => 'string',
		'tfa_backup' => 'string',
		'spoofdetector_name' => 'string',
		'email_address_ci' => 'string',
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
	 * Cache for the boardsCanAccess() method.
	 */
	private array $accessible_boards;

	/**
	 * @var array
	 *
	 * Cache for the groupsCanModerate() method.
	 */
	private array $groups_can_moderate;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var int
	 *
	 * ID number of the current user.
	 *
	 * This is used during self::loadMe() to keep track of the ID while we are
	 * still in the process of validating the credentials.
	 */
	protected static int $my_id;

	/**
	 * @var int
	 *
	 * Permanent record of the ID number of the current user as determined when
	 * validating the login cookie.
	 *
	 * Normally the same as self::$my_id, but may differ if self::setMe() was
	 * used to change the value of self::$me. This is used by self:loadMe() to
	 * revert self::$me back to the real current user without having to re-parse
	 * the cookie.
	 */
	protected static int $cookie_id;

	/**
	 * @var string
	 *
	 * The encrypted password string provided in the cookie.
	 *
	 * This is used during self::loadMe().
	 */
	protected static string $cookie_password;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'prop_names' => [
			'profiles' => 'user_profile',
			'settings' => 'user_settings',
			'info' => 'user_info',
			'sc' => 'sc',
			'memberContext' => 'memberContext',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param ?int $id The ID number of the user. If null, no data will be
	 *    loaded into the object properties. Default: null.
	 * @param UserDataset $dataset The set of data to load. Ignored if $id is
	 *    null. If set to UserDataset::None, no data will be loaded into the
	 *    object properties apart from $this->id. Default: UserDataset::Normal.
	 */
	public function __construct(?int $id = null, UserDataset $dataset = UserDataset::Normal)
	{
		// No ID given, so we can't load any data.
		if (!isset($id)) {
			return;
		}

		$this->id = $id;

		// Reloading the current user requires special handling.
		if ($id == (self::$my_id ?? NAN)) {
			// Copy over the existing data.
			$this->set(get_object_vars(self::$me));

			// Must load at least the minimal data in this situation.
			if ($dataset === UserDataset::None) {
				unset($dataset);
			}

			$dataset ??= self::$me->chooseMyDataset();

			if (!self::$me->dataset->includes($dataset)) {
				self::loadUserData((array) $id, self::LOAD_BY_ID, $dataset);
				$this->setProperties();
			}

			self::$loaded[$id] = $this;
			self::setMe($id);

			return;
		}

		// Specifically told not to load any data.
		if ($dataset === UserDataset::None) {
			return;
		}

		// Load the specified member.
		self::$loaded[$id] = $this;

		if (
			empty(self::$profiles[$id])
			|| !self::$profiles[$id]['dataset']->includes($dataset)
		) {
			self::loadUserData((array) $id, self::LOAD_BY_ID, $dataset);
		}

		$this->setProperties();
	}

	/**
	 * Saves this user's data to the members table in the database.
	 *
	 * Does nothing if this is a guest.
	 *
	 * Note: If you are updating many members at once, it is more efficient
	 * to call User::saveBatch($members) than to call $member->save() for
	 * each member individually.
	 */
	public function save(): void
	{
		// Can't save guests.
		if (empty($this->id)) {
			return;
		}

		// Since self::saveBatch() already has the logic we need, use it.
		self::saveBatch([$this]);
	}

	/**
	 * Load this user's permissions.
	 *
	 * @param int|array|null $boards Boards to load permissions for.
	 *    Default: null
	 */
	public function loadPermissions(int|array|null $boards = null): void
	{
		if ($boards === []) {
			$boards = null;
		}

		if (empty($this->groups)) {
			$this->id ??= 0;
			self::loadUserData([$this->id], dataset: UserDataset::Minimal);
			$this->setProperties();
		}

		foreach (
			UserPermissionSet::load(
				$this,
				array_filter(
					array_unique(
						array_merge(
							[0],
							(array) ($boards ?? Board::$info->id ?? 0),
						),
					),
					fn($board) => !isset($this->permission_sets[$board]),
				),
			) as $set
		) {
			if ($set->profile->id === PermissionProfile::DEFAULT) {
				$this->permission_sets[0] = $set;
			}

			foreach ($set->profile->boards() as $id_board) {
				$this->permission_sets[$id_board] = $set;
			}
		}

		if (!isset($boards)) {
			$this->loadModCache();

			// Can this user approve group requests?
			if (($this->mod_cache['gq'] ?? '0=1') != '0=1') {
				$this->permission_sets[0]->grant('approve_group_requests');
			}

			// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
			$this->can_mod = (
				$this->is_admin
				|| $this->permission_sets[0]->allowedTo('access_mod_center')
				|| ($this->mod_cache['gq'] ?? '0=1') != '0=1'
				|| ($this->mod_cache['bq'] ?? '0=1') != '0=1'
				|| (
					Config::$modSettings['postmod_active']
					&& !empty($this->mod_cache['ap'])
				)
			);

			// This is a useful phantom permission added to the current user,
			// and only the current user while they are logged in. For example
			// this drastically simplifies certain changes to the profile area.
			if (!$this->is_guest && $this === self::$me) {
				$this->permission_sets[0]->grant('is_not_guest');
			} else {
				$this->permission_sets[0]->deny('is_not_guest');
			}
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

		if (empty(Config::$modSettings['displayFields'])) {
			$display_custom_fields = false;
		}

		// If this user's data is already loaded, skip it.
		if (!empty($this->formatted) && $this->custom_fields_displayed >= $display_custom_fields) {
			return $this->formatted;
		}

		if (!$this->dataset->includes(UserDataset::Minimal)) {
			$this->id ??= 0;
			self::loadUserData([$this->id], dataset: UserDataset::Minimal);
			$this->setProperties();
		}

		// The minimal values.
		$this->formatted = [
			'id' => $this->id,
			'username' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->username,
			'name' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->name,
			'href' => $this->href,
			'link' => $this->link,
			'email' => $this->email,
			'show_email' => !self::$me->is_guest && ($this->is_me || self::$me->allowedTo('moderate_forum')),
			'registered' => empty($this->date_registered) ? Lang::getTxt('not_applicable', file: 'General') : Time::create('@' . $this->date_registered)->format(),
			'registered_timestamp' => $this->date_registered,
		];

		// Basic, normal, and profile want the avatar.
		if ($this->dataset->exceeds(UserDataset::Minimal)) {
			$this->formatted['avatar'] = $this->avatar;
		}

		// Normal and profile want lots more data.
		if ($this->dataset->exceeds(UserDataset::Basic)) {
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
						break;
					}
				}
			}

			// Is this user online, and if so, is their online status visible?
			$is_visibly_online = (!empty($this->show_online) || self::$me->allowedTo('moderate_forum')) && $this->is_online > 0;

			// Now append all the rest of the data.
			$this->formatted += [
				'username_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->username . '</span>',
				'name_color' => '<span ' . (!empty($this->group_color) ? 'style="color:' . $this->group_color . ';"' : '') . '>' . $this->name . '</span>',
				'link_color' => strstr($this->link, '>', true) . ' style="color:' . $this->group_color . ';"' . strstr($this->link, '>'),
				'is_buddy' => !empty(Config::$modSettings['enable_buddylist']) && \in_array($this->id, self::$me->buddies),
				'is_reverse_buddy' => !empty(Config::$modSettings['enable_buddylist']) && \in_array(self::$me->id, $this->buddies),
				'buddies' => $this->buddies,
				'title' => !empty(Config::$modSettings['titlesEnable']) ? $this->title : '',
				'blurb' => $this->personal_text,
				'website' => $this->website,
				'birthdate' => empty($this->birthdate) || (int) substr($this->birthdate, 0, 4) <= 1004 ? '' : $this->birthdate,
				'signature' => $this->signature,
				'real_posts' => $this->posts,
				'posts' => $this->posts > 500000 ? Lang::getTxt('geek', file: 'General') : Lang::numberFormat($this->posts),
				'last_login' => empty($this->last_login) ? Lang::getTxt('never', file: 'General') : Time::create('@' . $this->last_login)->format(),
				'last_login_timestamp' => empty($this->last_login) ? 0 : $this->last_login,
				'ip' => Utils::htmlspecialchars($this->ip),
				'ip2' => Utils::htmlspecialchars($this->ip2),
				'online' => [
					'is_online' => $is_visibly_online,
					'text' => Utils::htmlspecialchars(Lang::getTxt($is_visibly_online ? 'online' : 'offline', file: 'General')),
					'member_online_text' => Lang::getTxt($is_visibly_online ? 'member_is_online' : 'member_is_offline', ['name' => Utils::htmlspecialchars($this->name)], file: 'General'),
					'href' => Config::$scripturl . '?action=pm;sa=send;u=' . $this->id,
					'link' => '<a href="' . Config::$scripturl . '?action=pm;sa=send;u=' . $this->id . '">' . Lang::getTxt($is_visibly_online ? 'online' : 'offline', file: 'General') . '</a>',
					'label' => Lang::getTxt($is_visibly_online ? 'online' : 'offline', file: 'General'),
				],
				'language' => !empty($loadedLanguages[$this->language]) && !empty($loadedLanguages[$this->language]['name']) ? $loadedLanguages[$this->language]['name'] : Utils::ucwords(strtr($this->language, ['_' => ' ', '-utf8' => ''])),
				'is_activated' => $this->is_activated % self::BANNED == self::ACTIVATED,
				'is_banned' => $this->is_banned,
				'options' => $this->options,
				'is_guest' => $this->is_guest,
				'group_id' => $this->group_id,
				'group' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->group_name,
				'group_name' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->group_name,
				'group_color' => $this->group_color,
				'primary_group' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->primary_group_name,
				'primary_group_name' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->primary_group_name,
				'post_group_id' => $this->post_group_id,
				'post_group' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->post_group_name,
				'post_group_name' => $this->is_guest ? Lang::getTxt('guest_title', file: 'General') : $this->post_group_name,
				'post_group_color' => $this->is_guest ? '' : $this->post_group_color,
				'group_icons' => str_repeat('<img src="' . str_replace('$language', self::$me->language, isset($this->icons[1]) ? $group_icon_url : '') . '" alt="*">', empty($this->icons[0]) || empty($this->icons[1]) ? 0 : (int) $this->icons[0]),
				'warning' => $this->warning,
				'warning_status' => !empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= $this->warning ? 'mute' : (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= $this->warning ? 'moderate' : (!empty(Config::$modSettings['warning_watch']) && Config::$modSettings['warning_watch'] <= $this->warning ? 'watch' : '')),
				'local_time' => Time::create('now', $this->timezone)->format(null, false),
				'custom_fields' => [],
			];

			Lang::censorText($this->formatted['blurb']);
			Lang::censorText($this->formatted['signature']);

			$this->formatted['signature'] = Parser::transform(
				string: str_replace(["\n", "\r"], ['<br>', ''], $this->formatted['signature']),
				options: [
					'cache_id' => 'sig' . $this->id,
					'parse_tags' => Parser::getSigTags(),
				],
			);

			$this->formatted['signature'] = Utils::adjustHeadingLevels($this->formatted['signature'], null);

			// For backward compatibility.
			$this->formatted['birth_date'] = $this->formatted['birthdate'];
		}

		// Are we also loading the member's custom fields?
		if ($display_custom_fields) {
			$this->formatted['custom_fields'] = [];

			if (!isset(Utils::$context['display_fields']) || !\is_array(Utils::$context['display_fields'])) {
				Utils::$context['display_fields'] = Utils::jsonDecode(Config::$modSettings['displayFields'] ?? '[]', true) ?? [];
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
					$value = Utils::adjustHeadingLevels(Parser::transform($value), null);
				}
				// ... or checkbox?
				elseif (isset($custom['type']) && $custom['type'] == 'check') {
					$value = Lang::getTxt($value ? 'yes' : 'no', file: 'General');
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

		$this->custom_fields_displayed = !empty($this->custom_fields_displayed) || $display_custom_fields;

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
		// This only applies to the current user.
		if (!$this->is_me) {
			// Quietly ignore this.
			return;
		}

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

		// Don't log guests anymore - helps during bot attacks.
		if (!empty(Config::$modSettings['no_guest_logging']) && !empty(User::$me->is_guest)) {
			return;
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
			$num_elements = \count($_GET, COUNT_RECURSIVE) + 1;
			$max_length = 2048;

			do {
				$encoded_get = $_GET + ['USER_AGENT' => mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 128)];

				unset($encoded_get['sesc'], $encoded_get[Utils::$context['session_var']]);

				$encoded_get = Utils::truncateArray($encoded_get, $max_length);
				$encoded_get = Utils::jsonEncode($encoded_get);

				// If too long, reduce $max_length by one byte per element and try again.
				$max_length -= $num_elements;
			} while (\strlen($encoded_get) > 2048);
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
					'DELETE FROM {db_prefix}log_online
					WHERE log_time < {int:log_time}
						AND session != {string:session}',
					[
						'log_time' => time() - Config::$modSettings['lastActive'] * 60,
						'session' => $session_id,
					],
					identifier: 'delete_log_online_interval',
				);

				// Cache when we did it last.
				CacheApi::put('log_online-update', time(), 30);
			}

			Db::$db->query(
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
				[
					'session' => 'string',
					'id_member' => 'int',
					'id_spider' => 'int',
					'log_time' => 'int',
					'ip' => 'inet',
					'url' => 'string',
				],
				[
					[
						$session_id,
						$this->id,
						empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'],
						time(),
						$this->ip,
						$encoded_get,
					],
				],
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
				|| !\in_array($_REQUEST['action'], ['feed', 'login2', 'logintfa'])
			)
		) {
			// Don't count longer than 15 minutes.
			if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15) {
				$_SESSION['timeOnlineUpdated'] = time();
			}

			$this->total_time_logged_in += (time() - $_SESSION['timeOnlineUpdated']);
			$this->last_login = time();
			$this->ip2 = IP::getUserIPAlternative();
			$this->save();


			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('user_settings-' . $this->id, self::$profiles[$this->id], 60);
			}

			$_SESSION['timeOnlineUpdated'] = time();
		}
	}

	/**
	 * Requires a user who is logged in (not a guest).
	 *
	 * Checks if the user is currently a guest, and if so asks them to login
	 * with a message telling them why. If $message is empty, a default message
	 * will be used.
	 *
	 * @param string|null $message The message to display to the guest.
	 * @param bool $log Whether to log what they were trying to do.
	 */
	public function kickIfGuest(?string $message = null, bool $log = true): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Quietly ignore this.
			return;
		}

		// Luckily, this person isn't a guest.
		if (!$this->is_guest) {
			return;
		}

		// Log what they were trying to do that didn't work.
		if ($log) {
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

		if (empty($_COOKIE)) {
			Cookie::setLoginCookie(Cookie::LENGTH_DEFAULT, 0, '');
		}

		// Create a login token.
		SecurityToken::create('login');

		// Use the kick_guest sub template...
		Utils::$context['sub_template'] = 'kick_guest';
		Utils::$context['page_title'] = Lang::getTxt('login', file: 'General');
		Utils::$context['kick_message'] = $message ?? Lang::getTxt('only_members_can_access', file: 'Login');
		Utils::$context['robot_no_index'] = true;

		Utils::obExit();

		// We should never reach this point, but just in case...
		die('No direct access...');
	}

	/**
	 * Does banning related stuff (i.e. disallowing access).
	 *
	 * Checks if the user is completely banned, and if so dies with an error.
	 *
	 * Otherwise, applies any permissions changes required to enforce partial
	 * bans or restrictions due to high warning levels.
	 *
	 * @param bool $force_check Whether to force a recheck.
	 *    Default: false.
	 * @param bool $post_kick If true, die if they are if banned from posting.
	 *    If false, merely applies permissions that prevent them from posting.
	 *    Default: false.
	 * @param bool $reg_kick If true, die if they are banned from registering.
	 *    Only applicable to guests. Default: false.
	 */
	public function enforceBans(bool $force_check = false, bool $post_kick = false, bool $reg_kick = false): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Quietly ignore this.
			return;
		}

		// You cannot be banned if you are an admin.
		if ($this->is_admin) {
			return;
		}

		// Check whether they have any bans recorded in the database.
		$bans = Security::checkBans($this, $force_check);

		// Do they have a cookie recording their bans?
		if (!isset($bans['cannot_access']) && !empty($_COOKIE[Config::$cookiename . '_'])) {
			$ban_ids = array_map('intval', explode(',', $_COOKIE[Config::$cookiename . '_']));

			$request = Db::$db->query(
				'SELECT bi.id_ban, bg.reason, COALESCE(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
				WHERE bi.id_ban IN ({array_int:ban_list})
					AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
					AND bg.cannot_access = {int:cannot_access}
				LIMIT {int:limit}',
				[
					'cannot_access' => 1,
					'ban_list' => $ban_ids,
					'current_time' => time(),
					'limit' => \count($ban_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$bans['cannot_access']['ids'][] = $row['id_ban'];
				$bans['cannot_access']['reason'] = $row['reason'];
				$bans['expire_time'] = $row['expire_time'];
			}

			Db::$db->free_result($request);

			// If the bans recorded in the cookie no longer apply, delete it.
			if (!isset($bans['cannot_access'])) {
				$cookie = new Cookie(Config::$cookiename . '_', [], time() - 3600);
				$cookie->set();
			}
		}

		// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
		if (
			!$this->is_guest
			&& isset($this->is_activated)
			&& !empty($bans['cannot_access'])
			&& ($this->is_activated >= self::BANNED) != ($bans['cannot_access']['email_address'] == $this->email || $bans['cannot_access']['id_member'] == $this->id)
		) {
			Bans::updateBanMembers();
		}

		// Who are we giving the boot?
		$name = ($this->name ?? '') !== '' ? $this->name : Lang::getTxt('guest_title', file: 'General');

		// Walk through the different ban types to see if we should kick this user out.
		$should_kick = false;

		$restrictions = [
			'cannot_access',
			'cannot_login',
			'cannot_post',
			'cannot_register',
		];

		foreach ($restrictions as $restriction) {
			$ban = $bans[$restriction] ?? [];

			if (empty($ban['ids'])) {
				continue;
			}

			switch ($restriction) {
				// If you're fully banned, it's end of the story for you.
				case 'cannot_access':
					$should_kick = true;
					$remove_from_log_online = !$this->is_guest;
					$die_silently = ($_REQUEST['action'] ?? null) == 'dlattach';
					$force_logout = true;
					$set_ban_cookie = true;
					$show_expiry = true;
					$message = Lang::getTxt('your_ban', ['name' => $name], file: 'General') . (!empty($ban['reason']) ? '<br>' . $ban['reason'] : '');
					break 2;

				case 'cannot_login':
					// You're not allowed to log in but yet you are. Let's fix that.
					if (!$this->is_guest) {
						$should_kick = true;
						$remove_from_log_online = true;
						$die_silently = false;
						$force_logout = true;
						$set_ban_cookie = true;
						$show_expiry = true;
						$message = Lang::getTxt('your_ban', ['name' => $name], file: 'General') . (!empty($ban['reason']) ? '<br>' . $ban['reason'] : '');
						break 2;
					}

					break;

				case 'cannot_post':
					if ($post_kick) {
						$should_kick = true;
						$remove_from_log_online = false;
						$die_silently = false;
						$force_logout = false;
						$set_ban_cookie = false;
						$show_expiry = !$this->is_guest;
						$message = Lang::getTxt('you_are_post_banned', ['name' => $name], file: 'General') . (!empty($ban['reason']) ? '<br>' . $ban['reason'] : '');
						break 2;
					}

					break;

				case 'cannot_register':
					// Registration bans only make sense for guests.
					if ($reg_kick && $this->is_guest) {
						$should_kick = true;
						$remove_from_log_online = false;
						$die_silently = false;
						$force_logout = false;
						$set_ban_cookie = false;
						$show_expiry = false;
						$message = Lang::getTxt('ban_register_prohibited', file: 'Login') . (!empty($ban['reason']) ? '<br>' . $ban['reason'] : '');
						break 2;
					}

					break;
			}
		}

		// You banned, sucka!
		if ($should_kick) {
			Logging::logBan($ban['ids']);

			// We don't wanna see you!
			if ($remove_from_log_online) {
				// Remove all traces of whatever they were doing.
				Db::$db->query(
					'DELETE FROM {db_prefix}log_online
					WHERE id_member = {int:current_member}',
					[
						'current_member' => $this->id,
					],
				);
			} else {
				// Show them as only hitting the board index.
				$_GET['action'] = '';
				$_GET['board'] = '';
				$_GET['topic'] = '';
				// These are static, so they have to be cleared, not unset.
				Topic::$topic_id = null;
				Topic::$info = null;
				Board::$board_id = null;
				Board::$info = null;

				$this->logOnline(true);
			}

			// A goodbye present.
			if ($set_ban_cookie) {
				$cookie = new Cookie(Config::$cookiename . '_', implode(',', $ban['ids']), time() - 3600);
				$cookie->set();
			}

			// Log the user out.
			if ($force_logout) {
				User::setMe(0);
				Logout::call(true, false);
			}

			// Show no message?
			if ($die_silently) {
				die();
			}

			// Let them know when this ban will expire?
			if ($show_expiry) {
				if (empty($bans['expire_time'])) {
					$message .= '<br>' . Lang::getTxt('your_ban_expires_never', file: 'General');
				} else {
					$message .= '<br>' . Lang::getTxt(
						'your_ban_expires',
						['datetime' => Time::create('@' . $bans['expire_time'])->format(null, false)],
						file: 'General',
					);
				}
			}

			ErrorHandler::fatal($message, false, 403);

			// We should never reach this point, but just in case...
			die('No direct access...');
		}

		// Fix up the banning permissions.
		if (!isset($this->permission_sets)) {
			$this->loadPermissions();
		}

		foreach ($this->permission_sets as $set) {
			$set->applyBansAndWarnings();
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
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// We don't care if the option is off, because guests should NEVER get past here.
		$this->kickIfGuest();

		// Validate what type of session check this is.
		$types = [];
		IntegrationHook::call('integrate_validateSession', [&$types]);
		$type = \in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

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
			if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
				ErrorHandler::fatalLang('login_ssl_required');
			}

			$this->checkSession();

			$good_password = \in_array(true, IntegrationHook::call('integrate_verify_password', [$this->username, $_POST[$type . '_pass'], false]), true);

			// Password correct?
			if ($good_password || Security::hashVerifyPassword($_POST[$type . '_pass'], $this->passwd)) {
				$_SESSION[$type . '_time'] = time();

				unset($_SESSION['request_referer']);

				return null;
			}
		}

		// Better be sure to remember the real referer
		if (empty($_SESSION['request_referer'])) {
			$_SESSION['request_referer'] = isset($_SERVER['HTTP_REFERER']) ? Url::create($_SERVER['HTTP_REFERER'])->parse() : [];
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
	 * @return ?string The error message, or '' if everything was fine.
	 */
	public function checkSession(string $type = 'post', string $from_action = '', bool $is_fatal = true): ?string
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

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
				|| !\in_array(Utils::$context['valid_cors_found'], ['same', 'subdomain'])
			)
		) {
			if (str_contains($_SERVER['HTTP_HOST'], ':')) {
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

		// We should never reach this point, but just in case...
		die('No direct access...');
	}

	/**
	 * Checks whether the user has the specified permissions (e.g. 'post_new').
	 *
	 * If $boards is specified, checks those boards instead of the current one.
	 *
	 * If $any is true, will return true if the user has any of the specified
	 * permissions on any of the specified boards.
	 *
	 * @param string|array $permissions One or more permissions to check.
	 * @param int|array|null $boards The IDs of one or more boards, or null for
	 *    the current board. Default: null.
	 * @param bool $any If true, will return true if the user has any of the
	 *    specified permissions on any of the specified boards. If false, will
	 *    return true only if the user has all of the specified permissions on
	 *    all of the specified boards. Default: false.
	 * @return bool Whether the user has the specified permission.
	 */
	public function allowedTo(string|array $permissions, int|array|null $boards = null, bool $any = false): bool
	{
		// You're always allowed to do nothing. (Unless you're a working man, MR. LAZY :P!)
		if (empty($permissions)) {
			return true;
		}

		// Let's ensure these are arrays.
		$permissions = (array) $permissions;
		$boards = array_filter(
			array_map(
				fn($b) => max(0, (int) $b),
				(array) ($boards ?? Board::$info->id ?? []),
			),
		);

		// Shortcut for a special case.
		if (
			$permissions === ['approve_posts']
			&& \count($boards) === 1
			&& !empty($this->mod_cache['ap'])
		) {
			return $this->mod_cache['ap'] == [0] || array_intersect($boards, $this->mod_cache['ap']) === $boards;
		}

		// If a permission doesn't exist, it can't be done.
		foreach ($permissions as $key => $permission) {
			if (!Permission::exists($permission)) {
				unset($permissions[$key]);

				if (!$any || empty($permissions)) {
					return false;
				}
			}
		}

		// Avoid unnecessary repetition.
		$cache_key = implode(',', $permissions) . '-' . implode(',', $boards) . '-' . (int) $any;

		if (isset($this->perm_cache[$cache_key])) {
			return !empty($this->perm_cache[$cache_key]);
		}

		// Separate the board permissions from the global permissions.
		$board_permissions = array_filter($permissions, fn($p) => Permission::get($p)->scope === 'board');
		$global_permissions = array_diff($permissions, $board_permissions);

		// Check any requested global permissions.
		if (!empty($global_permissions)) {
			$this->loadPermissions(0);
			$allowed = $this->permission_sets[0]->allowedTo($global_permissions, $any);
		}

		// Check any requested board permissions.
		if (!empty($board_permissions) && !empty($boards)) {
			if (!isset($allowed)) {
				$allowed = !$any;
			}

			$this->loadPermissions($boards);

			foreach ($boards as $board) {
				$allowed_here = isset($this->permission_sets[$board]) && $this->permission_sets[$board]->allowedTo($board_permissions, $any);
				$allowed = $any ? ($allowed || $allowed_here) : ($allowed && $allowed_here);
			}
		}

		$this->perm_cache[$cache_key] = !empty($allowed);

		return !empty($allowed);
	}

	/**
	 * Checks whether the user has the given permissions, and exits with a
	 * fatal error if not.
	 *
	 * Uses allowedTo() to check if the user has the permissions.
	 *
	 * Checks the passed boards or current board for the permissions.
	 *
	 * If $any is true, the user will pass if they have any of the specified
	 * permissions on any of the specified boards.
	 *
	 * If the user is not allowed, loads the Errors language file and shows an
	 * error using Lang::$txt['cannot_' . $permission].
	 *
	 * If the user is a guest and cannot do it, calls $this->kickIfGuest().
	 *
	 * @param string|array $permissions One or more permissions to check.
	 * @param int|array|null $boards The IDs of one or more boards, or null for
	 *    the current board. Default: null.
	 * @param bool $any If true, the user will pass if they have any of the
	 *    specified permissions on any of the specified boards. Default: false.
	 */
	public function isAllowedTo(string|array $permissions, int|array|null $boards = null, bool $any = false): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// Make it an array, even if a string was passed.
		$permissions = (array) $permissions;

		// Check the permission and return an error...
		if (!$this->allowedTo($permissions, $boards, $any)) {
			// Pick the last array entry as the permission shown as the error.
			$error_permission = array_shift($permissions);

			// If they are a guest, show a login. (because the error might be gone if they do!)
			if ($this->is_guest) {
				$this->kickIfGuest(Lang::getTxt('cannot_' . $error_permission, file: 'Errors'));
			}

			// Clear the action because they aren't really doing that!
			$_GET['action'] = '';
			$_GET['board'] = '';
			$_GET['topic'] = '';
			$this->logOnline(true);

			ErrorHandler::fatalLang('cannot_' . $error_permission, false);

			// We should never get to this point, but just in case...
			die('No direct access...');
		}

		// If you're doing something on behalf of some "heavy" permissions,
		// validate your session.
		foreach ($permissions as $permission) {
			if (Permission::get($permission)->heavy) {
				$this->validateSession();
				break;
			}
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
		$permissions = (array) $permissions;

		foreach ($permissions as $permission) {
			if (Permission::get($permission)->scope !== 'board') {
				// Not translated because it will only happen if a developer screwed up.
				throw new \ValueError('Global permission "' . $permission . '" passed to $permissions parameter of ' . __METHOD__);
			}
		}

		$boards = $deny_boards = array_fill_keys($permissions, []);

		if (empty($this->groups)) {
			$this->id ??= 0;
			self::loadUserData([$this->id], dataset: UserDataset::Minimal);
			$this->setProperties();
		}

		foreach (PermissionProfile::loadAll() as $profile) {
			if (empty($profile->boards())) {
				continue;
			}

			// No need to pass all the boards to UserPermissionSet::load(). One will do.
			$first_board = current($profile->boards());

			$set = current(UserPermissionSet::load($this, [$first_board]));

			foreach ($permissions as $permission) {
				if ($set->allowedTo($permission)) {
					$boards[$permission] = array_merge($boards[$permission], $profile->boards());
				} else {
					$deny_boards[$permission] = array_merge($deny_boards[$permission], $profile->boards());
				}
			}
		}

		$check_access = $check_access && !$this->can_manage_boards;

		if ($check_access) {
			foreach ($boards as $permission => $board_list) {
				$boards[$permission] = array_intersect($board_list, $this->boardsCanAccess());
			}
		}

		if ($simple) {
			$boards = array_reduce($boards, fn($carry, $item) => $carry = array_unique(array_merge($carry, $item)), []);
		}

		// Maybe a mod needs to tweak the list of allowed boards on the fly?
		IntegrationHook::call('integrate_boards_allowed_to', [&$boards, $deny_boards, $permissions, $check_access, $simple]);

		// Ensure there are no gaps in the keys.
		$boards = $simple ? array_values($boards) : array_map(fn($board_list) => array_values($board_list), $boards);

		return $boards;
	}

	/**
	 * Gets a list of boards that this user can access.
	 *
	 * @return array A list of board IDs.
	 */
	public function boardsCanAccess(): array
	{
		if (isset($this->accessible_boards)) {
			return $this->accessible_boards;
		}

		if (empty($this->query_see_board)) {
			$this->id ??= 0;
			self::loadUserData([$this->id], dataset: UserDataset::Minimal);
			$this->setProperties();
		}

		$request = Db::$db->query(
			'SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {raw:can_access}',
			[
				'can_access' => $this->query_see_board,
			],
		);

		$this->accessible_boards = array_map(fn($row) => $row['id_board'], Db::$db->fetch_all($request));

		Db::$db->free_result($request);

		return $this->accessible_boards;
	}

	/**
	 * Gets a list of membergroups that this user can moderate.
	 *
	 * @param bool $ignore_protected Whether to ignore the protected status of
	 *    protected groups. Only applicable when this user can manage groups but
	 *    is not an admin. Default: false.
	 * @return array A list of zero or more membergroup IDs.
	 */
	public function groupsCanModerate(bool $ignore_protected = false): array
	{
		if (empty($this->groups)) {
			$this->id ??= 0;
			self::loadUserData([$this->id], dataset: UserDataset::Minimal);
			$this->setProperties();
		}

		// $ignore_protected only ever matters in this one scenario.
		if (
			$ignore_protected
			&& !$this->allowedTo('admin_forum')
			&& $this->allowedTo('manage_membergroups')
		) {
			return Group::getAll();
		}

		if (isset($this->groups_can_moderate)) {
			return $this->groups_can_moderate;
		}

		if ($this->is_guest) {
			$this->groups_can_moderate = [];
		} elseif ($this->allowedTo('admin_forum')) {
			$this->groups_can_moderate = Group::getAll();
		} elseif ($this->allowedTo('manage_membergroups')) {
			$request = Db::$db->query(
				'SELECT id_group
				FROM {db_prefix}groups
				WHERE group_type != {int:protected}',
				[
					'protected' => Group::TYPE_PROTECTED,
				],
			);

			$this->groups_can_moderate = array_map(fn($row) => $row['id_group'], Db::$db->fetch_all($request));

			Db::$db->free_result($request);
		} else {
			$request = Db::$db->query(
				'SELECT id_group
				FROM {db_prefix}group_moderators
				WHERE id_member = {int:member}',
				[
					'member' => $this->id,
				],
			);

			$this->groups_can_moderate = array_map(fn($row) => $row['id_group'], Db::$db->fetch_all($request));

			Db::$db->free_result($request);
		}

		return $this->groups_can_moderate;
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
	 * @param UserDataset $dataset What kind of data to load.
	 *    Default: UserDataset::Normal
	 * @return array Instances of this class for the loaded users.
	 */
	public static function load(array|string|int $users = [], int $type = self::LOAD_BY_ID, UserDataset $dataset = UserDataset::Normal): array
	{
		$users = (array) $users;

		$loaded = [];

		if ($users === []) {
			return $loaded;
		}

		// If looking up by name or email, we need to load at least the minimal data.
		if ($dataset === UserDataset::None && $type !== self::LOAD_BY_ID) {
			$dataset = UserDataset::Minimal;
		}

		if ($dataset === UserDataset::None) {
			foreach ($users as $id) {
				if (!isset(self::$loaded[$id])) {
					self::$loaded[$id] = new self($id, $dataset);
				}

				$loaded[] = self::$loaded[$id];
			}
		} else {
			// Load members.
			foreach (self::loadUserData((array) $users, $type, $dataset) as $id) {
				// Not yet loaded.
				if (!isset(self::$loaded[$id])) {
					new self($id, $dataset);
				}
				// Already loaded, so just update the properties.
				elseif (!self::$loaded[$id]->dataset->includes($dataset)) {
					self::$loaded[$id]->setProperties();
				}

				$loaded[] = self::$loaded[$id];
			}
		}

		return $loaded;
	}

	/**
	 * Loads the current user based on cookie data.
	 *
	 * The loaded user is assigned to User::$me and also returned.
	 *
	 * Note that if User::setMe() was previously used to change the value of
	 * User::$me, calling this method will change it back to the original user.
	 *
	 * @return self An instance of this class for the current user.
	 */
	public static function loadMe(): self
	{
		// If we loaded the user earlier, but then self::setMe() changed
		// self::$me to something else, we can save ourselves some effort now
		// by simply reverting self::$me back to the original user.
		if (isset(self::$me, self::$cookie_id) && self::$me->id !== self::$cookie_id) {
			// Double check whether all required data was loaded.
			if (
				!isset(self::$loaded[self::$cookie_id])
				|| self::$me->chooseMyDataset()->exceeds(self::$loaded[self::$cookie_id]->dataset)
			) {
				self::reload(self::$cookie_id, self::$me->chooseMyDataset());
			}

			self::setMe(self::$cookie_id);
		}

		if (!isset(self::$me)) {
			self::$me = new self();

			// Current user is a guest until proven otherwise.
			self::$my_id = 0;

			// Allow mods to do verification if they want.
			self::$me->integrateVerifyUser();

			// Load the user's data.
			self::$me->setMyId();
			self::loadUserData((array) self::$my_id, self::LOAD_BY_ID, self::$me->chooseMyDataset());

			// Verify that the user is who they claim to be.
			// If verification fails, self::$my_id will be reset to 0.
			self::$me->verifyPassword();
			self::$me->verifyTfa();

			if (empty(self::$my_id) && !isset(self::$profiles[0])) {
				self::loadUserData([0], self::LOAD_BY_ID, UserDataset::Minimal);
			}

			// At this point, we know the user ID for sure.
			self::$me->id = self::$my_id;
			self::$cookie_id = self::$my_id;

			// Also track this in our list of all loaded instances.
			self::$loaded[self::$me->id] = self::$me;

			// If the user is a guest, initialize all the critical user settings.
			if (empty(self::$me->id)) {
				self::$me->initializeGuest();
			}
			// Otherwise, update the user's last visit time.
			else {
				self::$me->setLastVisit();
			}

			// Now set all the properties.
			self::$me->setProperties();

			// Backward compatibility.
			self::$info = self::$me;
			Utils::$context['user'] = self::$me;
			self::integrateUserInfo();
		}

		return self::$me;
	}

	/**
	 * Loads users according to arbitrary query criteria.
	 *
	 * @param array $query_customizations
	 * @param ?UserDataset $dataset What kind of data to load.
	 *    Default: UserDataset::Normal.
	 * @return array Instances of this class for the loaded users.
	 */
	public static function loadCustom(array $query_customizations, UserDataset $dataset = UserDataset::Normal): array
	{
		$loaded = [];

		$query_customizations['selects'] ??= ['mem.*'];
		$query_customizations['joins'] ??= [];
		$query_customizations['where'] ??= [];
		$query_customizations['order'] ??= [];
		$query_customizations['group'] ??= [];
		$query_customizations['limit'] ??= 0;
		$query_customizations['params'] ??= [];

		self::addQueryCustomizationsForDataset($query_customizations, $dataset);

		foreach (self::retrieveUserData($query_customizations, $dataset) as $id) {
			if (!isset(self::$loaded[$id])) {
				new self($id, $dataset);
			} else {
				self::$loaded[$id]->setProperties();
			}

			$loaded[] = self::$loaded[$id];
		}

		return $loaded;
	}

	/**
	 * Reloads an array of users, specified by ID number.
	 *
	 * @param int|array $users One or more users specified by ID.
	 * @param ?UserDataset $dataset What kind of data to load. Leave null to use
	 *    the same dataset as the old instances.
	 * @return array The ids of the loaded members.
	 */
	public static function reload(int|array $users = [], ?UserDataset $dataset = null): array
	{
		$users = (array) $users;

		$grouped_by_dataset = [];

		foreach ($users as $id) {
			if ($dataset === null) {
				$grouped_by_dataset[(self::$loaded[$id]->dataset ?? UserDataset::Normal)->value][] = $id;
			} else {
				$grouped_by_dataset[$dataset->value][] = $id;
			}

			unset(self::$loaded[$id]);
			self::$profiles[$id] = [];
		}

		$loaded = [];

		foreach ($grouped_by_dataset as $new_dataset => $ids) {
			$loaded += self::load($ids, self::LOAD_BY_ID, UserDataset::from($new_dataset));
		}

		return $loaded;
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
	 * Updates the columns in the members table.
	 *
	 * Assumes the data has been htmlspecialchar'd.
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
	 * @deprecated 3.0 Use $member->save() or User::saveBatch($members) instead
	 *    of this deprecated method.
	 *
	 * @param int|array|null $ids An array of member IDs, the ID of a single
	 *    member, or null to update this for all members.
	 * @param array $data The info to update for the members.
	 */
	final public static function updateMemberData(int|array|null $ids, array $data): void
	{
		if (empty($data) || $ids === [] || $ids === 0) {
			return;
		}

		// Null means all members.
		if (\is_null($ids)) {
			$request = Db::$db->query(
				'SELECT id_member
				FROM {db_prefix}members',
			);

			$ids = array_map(
				fn($row) => (int) $row['id_member'],
				Db::$db->fetch_all($request),
			);

			Db::$db->free_result($request);
		}

		// Clean up the IDs. We want no duplicates and no guests.
		$ids = array_unique(
			array_filter(
				array_map(
					'intval',
					array_filter((array) $ids, 'is_numeric'),
				),
				fn($id) => $id > 0,
			),
		);

		// If necessary, add custom column types to self::$column_types.
		self::setColumnTypes($data);

		// Which dataset do we need?
		foreach ($data as $var => $val) {
			// Incrementing/decrementing requires loading the current values.
			if ($var !== 'alerts' && self::$column_types[$var] === 'int' && preg_match('/[+\-]/', $val)) {
				$dataset = UserDataset::Minimal;
			} else {
				$dataset ??= UserDataset::None;
			}
		}

		// Load the members.
		$members = self::load($ids, dataset: $dataset);

		if (empty($members)) {
			return;
		}

		// Pre-process some data types.
		foreach ($members as $member) {
			foreach ($data as $var => $val) {
				switch ($var) {
					case 'avatar':
						$member->avatar = new Avatar(
							original_url: $val,
							email: $member->email,
							id_member: $member->id,
						);
						break;

					case 'birthdate':
					case 'birth_date':
						try {
							$member->birthdate = empty($val) ? '1004-01-01' : Time::create($val)->format('Y-m-d', false, false);
						} catch (\Throwable $e) {
							$member->birthdate = '1004-01-01';
						}
						break;

					case 'member_ip':
					case 'member_ip2':
						$val = IP::create($val);

						if ($val->isValid()) {
							$member->{$var} = (string) $val;
						}
						break;

					case 'alerts':
						$member->alerts = Alert::count($member->id);
						break;

					default:
						switch ((self::$column_types[$var] ?? null)) {
							case 'int':
								if (
									preg_match(
										'~^' . $var . '\s*(\+\s*|-\s*|\+\s*-)(\d+)~',
										(string) $val,
										$matches,
									)
								) {
									if (trim($matches[1]) === '+') {
										$member->{$var} += (int) $matches[2];
									} else {
										$member->{$var} = max(0, $member->{$var} - (int) $matches[2]);
									}
								} else {
									switch ($val) {
										case '+':
											$member->{$var}++;
											break;

										case '-':
											$member->{$var} = max(0, $member->{$var} - 1);
											break;

										default:
											$member->{$var} = max(0, (int) $val);
											break;
									}
								}
								break;

							case 'float':
								$member->{$var} = (float) $val;
								break;

							default:
								$member->{$var} = $val;
								break;
						}
						break;
				}
			}
		}

		self::saveBatch($members);
	}

	/**
	 * Writes data for the given members to the database.
	 *
	 * If a member's post count is updated, this method also updates their post
	 * groups.
	 *
	 * @param array $members Array of instances of this class.
	 */
	public static function saveBatch(array $members): void
	{
		// Filter out any guests.
		$members = array_filter($members, fn($member) => $member->id > 0);

		if (empty($members)) {
			return;
		}

		// Ensure self::$column_types includes all necessary columns.
		if ($p = array_find(self::$profiles, fn($p) => $p['dataset'] !== UserDataset::None)) {
			self::setColumnTypes($p);
		} else {
			foreach (self::queryData(selects: ['mem.*'], limit: 1) as $row) {
				self::setColumnTypes($row);
			}
		}

		// Call the deprecated integrate_change_member_data hook.
		self::integrateChangeMemberData($members);

		// Build the $set and $params lists.
		$set = [];
		$params = [
			'members' => array_map(fn($member) => $member->id, $members),
		];

		foreach (self::$column_types as $column => $type) {
			foreach ($members as $member) {
				// Build the $param value for this member.
				switch ($column) {
					case 'id_member':
						// Never change the ID.
						break;

					case 'id_post_group':
						// This one is special. We calculate it below.
						break;

					case 'id_group':
						if (!\in_array($member->group_id ?? Group::GUEST, [Group::MOD, Group::GUEST])) {
							$params[$column . '_' . $member->id] = $member->group_id;
						} elseif (!\in_array($member->primary_group_id ?? Group::GUEST, [Group::MOD, Group::GUEST])) {
							$params[$column . '_' . $member->id] = $member->primary_group_id;
						}

						break;

					case 'lngfile':
						if (!empty(Config::$modSettings['userLanguage']) && !empty($member->language)) {
							$params[$column . '_' . $member->id] = $member->language;
						}

						break;

					case 'website_title':
					case 'website_url':
						$key = substr($column, 8);

						if (isset($member->website[$key])) {
							$params[$column . '_' . $member->id] = $member->website[$key];
						}

						break;

					case 'avatar':
						if (isset($member->avatar)) {
							// If the avatar is a Gravatar, use 'gravatar://...'
							if ($member->avatar->url->isGravatar()) {
								if (!empty(Config::$modSettings['gravatarOverride'])) {
									$params[$column . '_' . $member->id] = 'gravatar://';
								} elseif (!empty($member->avatar->email)) {
									$params[$column . '_' . $member->id] = 'gravatar://' . $member->avatar->email;
								} else {
									$params[$column . '_' . $member->id] = 'gravatar://' . $member->email;
								}
							}
							// Clear the column if...
							elseif (
								// The avatar is a data URI.
								$member->avatar->url->isScheme('data')
								// The avatar is an attachment.
								|| !empty($member->avatar->id_attach)
								|| (
									!empty(Config::$modSettings['custom_avatar_url'])
									&& str_starts_with((string) $member->avatar->url, Config::$modSettings['custom_avatar_url'])
								)
								// The avatar is the default.
								|| (
									!empty(Config::$modSettings['avatar_url'])
									&& (string) $member->avatar->url === Config::$modSettings['avatar_url'] . '/default.png'
								)
							) {
								$params[$column . '_' . $member->id] = '';
							}
							// If the avatar is a prepackaged image, use the relative path.
							elseif (
								!empty(Config::$modSettings['avatar_url'])
								&& str_starts_with((string) $member->avatar->url, Config::$modSettings['avatar_url'])
							) {
								$params[$column . '_' . $member->id] = ltrim(substr((string) $member->avatar->url, \strlen(Config::$modSettings['avatar_url'])), '/');
							}
							// If $url is just the proxied version of $original_url,
							// then stick with $original_url.
							elseif (
								isset($member->avatar->original_url)
								&& (string) $member->avatar->url === (string) (Url::create($member->avatar->original_url)->proxied())
							) {
								$params[$column . '_' . $member->id] = $member->avatar->original_url;
							}
							// Otherwise, use the full $url.
							else {
								$params[$column . '_' . $member->id] = (string) $member->avatar->url;
							}
						}

						break;

					case 'email_address':
						if (isset($member->email)) {
							$email = new EmailAddress($member->email, true);

							// Don't update unless the email is valid.
							if ($email->isValid()) {
								$params[$column . '_' . $member->id] = (string) $email;
							}
						}

						break;

					case 'email_address_ci':
						if (isset($member->email)) {
							$email = new EmailAddress($member->email, true);

							// Don't update unless the email is valid.
							if ($email->isValid()) {
								$params[$column . '_' . $member->id] = $email->casefolded();
							}
						}

						break;

					case 'spoofdetector_name':
						if (isset($member->name)) {
							$params[$column . '_' . $member->id] = Utils::htmlspecialchars(Unicode\SpoofDetector::getSkeletonString(html_entity_decode($member->name, ENT_QUOTES)));
						}

						break;

					case 'time_format':
						if (isset($member->real_time_format)) {
							$params[$column . '_' . $member->id] = $member->real_time_format;
						}

						break;

					default:
						$prop = match ($column) {
							'member_name' => 'username',
							'real_name' => 'name',
							'usertitle' => 'title',
							'instant_messages' => 'messages',
							'id_theme' => 'theme',
							'member_ip' => 'ip',
							'member_ip2' => 'ip2',
							'buddy_list' => 'buddies',
							'pm_ignore_list' => 'ignoreusers',
							'ignore_boards' => 'ignoreboards',
							default => $column,
						};

						if (isset($member->{$prop})) {
							$value = \is_array($member->{$prop}) ? implode(',', $member->{$prop}) : $member->{$prop};

							if (\in_array($type, ['int', 'float'])) {
								settype($value, $type);
							}

							$params[$column . '_' . $member->id] = $value;
						}

						break;
				}

				// Build the $set value for this member.
				if (\array_key_exists($column . '_' . $member->id, $params)) {
					$set[$column][$member->id] = '{' . $type . ':' . $column . '_' . $member->id . '}';
				}

				// Special handling for the post group.
				if ($column === 'posts' && isset($set[$column][$member->id])) {
					// Load the post groups in ascending order.
					if (!isset($post_groups)) {
						$post_groups = Group::getPostGroups();
						asort($post_groups);
					}

					$set['id_post_group'][$member->id] = '{int:id_post_group_' . $member->id . '}';

					// Find the correct group.
					foreach ($post_groups as $group_id => $min_posts) {
						if ($min_posts <= $member->posts) {
							$params['id_post_group_' . $member->id] = $group_id;
						}
					}
				}
			}
		}

		/*
		 * Allow mods to adjust $set and $params for their custom columns.
		 *
		 * MOD AUTHORS: If you use this hook, you probably also want to use the
		 * integrate_user_properties hook to control how your custom columns are
		 * assigned to object properties when retrieved from the database.
		 */
		IntegrationHook::call('integrate_save_member_data', [$members, &$set, &$params]);

		// Build each column's complete SET statement.
		foreach ($set as $column => $to_set) {
			if (empty($to_set)) {
				unset($set[$column]);
				continue;
			}

			$statement = $column . ' = CASE';

			foreach ($to_set as $id => $value) {
				$statement .= "\n\t\t\t\t\t" . 'WHEN id_member = ' . $id . ' THEN ' . $value;
			}

			$statement .= "\n\t\t\t\t\t" . 'ELSE ' . $column;
			$statement .= "\n\t\t\t\t" . 'END';

			$set[$column] = $statement;
		}

		// Perform the update.
		Db::$db->query(
			'UPDATE {db_prefix}members
			SET
				' . implode(",\n\t\t\t\t", $set) . '
			WHERE id_member IN ({array_int:members})',
			$params,
		);

		// Clear any caching?
		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
			foreach ($members as $member) {
				if (CacheApi::$enable >= 3) {
					CacheApi::put('member_data-profile-' . $member->id, null, 120);
					CacheApi::put('member_data-normal-' . $member->id, null, 120);
					CacheApi::put('member_data-basic-' . $member->id, null, 120);
					CacheApi::put('member_data-minimal-' . $member->id, null, 120);
				}

				CacheApi::put('user_settings-' . $member->id, null, 60);
			}
		}

		// Ensure $member->groups is correct for each updated member.
		foreach ($members as $member) {
			if (isset($member->groups)) {
				$member->groups = array_unique(array_merge([0, $member->group_id ?? 0, $member->post_group_id ?? 0], $member->additional_groups ?? []));
			}
		}
	}

	/**
	 * Delete one or more members.
	 *
	 * Requires profile_remove_own or profile_remove_any permission for
	 * respectively removing your own account or any account.
	 *
	 * Non-admins cannot delete admins.
	 *
	 * This method:
	 *   - changes author of messages, topics and polls to guest authors.
	 *   - removes all log entries concerning the deleted members, except the
	 *     error logs, ban logs, and moderation logs.
	 *   - removes these members' personal messages (only the inbox), avatars,
	 *     ban entries, theme settings, moderator positions, poll and votes.
	 *   - updates member statistics afterwards.
	 *
	 * @param int|array $users The ID of a user or an array of user IDs.
	 * @param bool $protect_admins If true, will not delete administrators.
	 *    Even when this is false, it will be forced to true if the current user
	 *    is not an administrator or if they are the only administrator.
	 *    Default: false.
	 * @param bool $anonymize If true, force anonymization of all deleted users.
	 *    If false, deleted users will be anonymized only if they requested it
	 *    or Config::$modSettings['always_anonymize_deleted_accounts'] is true.
	 *    Default: false.
	 */
	public static function delete(int|array $users, bool $protect_admins = false, bool $anonymize = false): void
	{
		// If it's not an array, make it so!
		$users = array_unique((array) $users);

		// Make sure there's no void user in here.
		$users = array_values(array_filter(array_map('intval', $users), fn($user) => $user > 0));

		if (empty($users)) {
			return;
		}

		// Permission check.
		self::$me->isAllowedTo($users === [self::$me->id] ? 'profile_remove_own' : 'profile_remove_any');

		// Get their names for logging purposes.
		$admins = [];
		$user_log_details = [];
		$emails = [];

		$request = Db::$db->query(
			'SELECT
				id_member, member_name, is_activated, email_address,
				CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:user_list})
			LIMIT {int:limit}',
			[
				'user_list' => $users,
				'admin_group' => 1,
				'limit' => \count($users),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['is_admin']) {
				$admins[] = $row['id_member'];
			}

			$user_log_details[$row['id_member']] = $row;
			$emails[] = $row['email_address'];
		}
		Db::$db->free_result($request);

		if (empty($user_log_details)) {
			return;
		}

		// Only admins can delete admins, and there must always be at least one admin.
		$protect_admins = $protect_admins || !self::$me->is_admin || (!empty($admins) && current(Group::load(Group::ADMIN))->countMembers() <= \count($admins));

		if (!empty($admins) && $protect_admins) {
			$users = array_diff($users, $admins);

			foreach ($admins as $id) {
				unset($user_log_details[$id]);
			}
		}

		// No one left?
		if (empty($user_log_details)) {
			return;
		}

		// Once we start, don't stop.
		$previous_ignore_user_abort = (bool) ignore_user_abort(true);

		// Try to give us a while to sort this out...
		Sapi::setTimeLimit();

		// Try to get some more memory.
		Sapi::setMemoryLimit('128M');

		// Log the action - regardless of who is deleting it.
		$log_changes = [];

		foreach ($user_log_details as $user) {
			$log_changes[] = [
				'action' => 'delete_member',
				'log_type' => 'admin',
				'extra' => [
					'member' => $user['id_member'],
					'name' => $user['member_name'],
					'member_acted' => self::$me->name,
				],
			];

			// Remove any cached data if enabled.
			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('user_settings-' . $user['id_member'], null, 60);
			}
		}

		// Anonymize?
		foreach ($user_log_details as $user) {
			if (
				// Anonymize all users if we were told to do that.
				$anonymize
				// Anonymize all users if the global setting to do so is enabled.
				|| !empty(Config::$modSettings['always_anonymize_deleted_accounts'])
				// Otherwise, only anonymize users who requested it.
				|| $user['is_activated'] % User::BANNED === User::REQUESTED_DELETE_ANONYMIZE
			) {
				self::anonymize((int) $user['id_member']);
			}
		}

		$set_tables = [
			// Change these people's posts into guest posts.
			['table' => 'messages', 'col' => 'id_member'],
			['table' => 'polls', 'col' => 'id_member'],
			['table' => 'topics', 'col' => 'id_member_started'],
			['table' => 'topics', 'col' => 'id_member_updated'],
			// Change these people's admin and moderation log entries.
			[
				'table' => 'log_actions',
				'col' => 'id_member',
				'where' => ' AND id_log != {int:log_type}',
				'log_type' => 2,
			],
			// Change certain other log entries that shouldn't be deleted.
			['table' => 'log_banned', 'col' => 'id_member'],
			['table' => 'log_errors', 'col' => 'id_member'],
			['table' => 'log_reported', 'col' => 'id_member'],
			['table' => 'log_reported_comments', 'col' => 'id_member'],
			['table' => 'log_polls', 'col' => 'id_member'],
		];

		$delete_tables = [
			// Delete these members.
			['table' => 'members', 'col' => 'id_member'],
			['table' => 'member_logins', 'col' => 'id_member'],
			['table' => 'user_alerts', 'col' => 'id_member'],
			['table' => 'user_alerts', 'col' => 'id_member_started'],
			['table' => 'user_alerts_prefs', 'col' => 'id_member'],
			// Delete their drafts.
			['table' => 'user_drafts', 'col' => 'id_member'],
			// Delete the likes they made.
			['table' => 'user_likes', 'col' => 'id_member'],
			// Delete any mentions of them.
			['table' => 'mentions', 'col' => 'id_member'],
			// Delete their profile edit logs.
			[
				'table' => 'log_actions',
				'col' => 'id_member',
				'where' => ' AND id_log = {int:log_type}',
				'log_type' => 2,
			],
			// Delete their other log entries.
			[
				'table' => 'log_comments',
				'col' => 'id_recipient',
				'where' => ' AND comment_type = {literal:warntpl}',
			],
			['table' => 'log_boards', 'col' => 'id_member'],
			['table' => 'log_group_requests', 'col' => 'id_member'],
			['table' => 'log_mark_read', 'col' => 'id_member'],
			['table' => 'log_notify', 'col' => 'id_member'],
			['table' => 'log_online', 'col' => 'id_member'],
			['table' => 'log_subscribed', 'col' => 'id_member'],
			['table' => 'log_topics', 'col' => 'id_member'],
			// Delete their PM data.
			['table' => 'personal_messages', 'col' => 'id_member_from'],
			['table' => 'pm_rules', 'col' => 'id_member'],
			['table' => 'pm_recipients', 'col' => 'id_member'],
			// It's over, no more moderation for you.
			['table' => 'moderators', 'col' => 'id_member'],
			['table' => 'group_moderators', 'col' => 'id_member'],
			// If you don't exist we can't ban you.
			['table' => 'ban_items', 'col' => 'id_member'],
			// Delete their theme settings.
			['table' => 'themes', 'col' => 'id_member'],
		];

		// Change some of their data into guest data.
		foreach ($set_tables as $d) {
			$d['guest_id'] = 0;
			$d['users'] = $users;
			$where = $d['where'] ?? '';
			unset($d['where']);

			Db::$db->query(
				'UPDATE {db_prefix}{raw:table}
				SET {raw:col} = {int:guest_id}
				WHERE {raw:col} IN ({array_int:users})' . $where,
				$d,
			);

			Sapi::setTimeLimit();
		}

		// Delete their personal messages.
		PM::delete(null, null, $users);
		Sapi::setTimeLimit();

		// Delete other data for these members.
		foreach ($delete_tables as $d) {
			$d['guest_id'] = 0;
			$d['users'] = $users;
			$where = $d['where'] ?? '';
			unset($d['where']);

			Db::$db->query(
				'DELETE FROM {db_prefix}{raw:table}
				WHERE {raw:col} IN ({array_int:users})' . $where,
				$d,
			);

			Sapi::setTimeLimit();
		}

		// Delete avatar.
		Attachment::remove(['id_member' => $users]);
		Sapi::setTimeLimit();

		// These people are nobody's buddies anymore.
		$request = Db::$db->query(
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

			Sapi::setTimeLimit();
		}
		Db::$db->free_result($request);

		// Remove any emails we may have queued to send.
		Db::$db->query(
			'DELETE FROM {db_prefix}mail_queue
			WHERE recipient IN ({array_string:emails})',
			[
				'emails' => $emails,
			],
		);

		Sapi::setTimeLimit();

		// Make sure no member's birthday is still sticking in the calendar...
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		// Integration rocks!
		IntegrationHook::call('integrate_delete_members', [$users]);
		Sapi::setTimeLimit();

		Logging::updateStats('member');
		Logging::logActions($log_changes);

		// It is now safe to allow abort.
		ignore_user_abort($previous_ignore_user_abort);
	}

	/**
	 * Finds members by email address, username, or display name.
	 *
	 * Searches only buddies if $buddies_only is set.
	 *
	 * @param string|array $names The names of members to search for.
	 * @param bool $use_wildcards Whether to accept wildcards in the pattern.
	 *    Default: false.
	 * @param bool $buddies_only Whether to search only for this user's buddies.
	 *    Default: false.
	 * @param int $max The maximum number of results.
	 *    Default: 500.
	 * @param bool $ids_only If true, return just the IDs of the found members.
	 *    Default: false.
	 * @return array Information about the matching members.
	 */
	public static function find(
		string|array $names,
		bool $use_wildcards = false,
		bool $buddies_only = false,
		int $max = 500,
		bool $ids_only = false,
	): array {
		if ($use_wildcards) {
			$member_name_query_pattern = '{column_ci:member_name} LIKE {string_ci:%s}';
			$real_name_query_pattern = '{column_ci:real_name} LIKE {string_ci:%s}';
			$email_query_pattern = 'email_address_ci LIKE {string:%s}';
			$wildcard_replacements = [
				'%' => '\\%',
				'_' => '\\_',
				'*' => '%',
				'?' => '_',
				'\'' => '&#039;',
			];
		} else {
			$member_name_query_pattern = '{column_ci:member_name} = {string_ci:%s}';
			$real_name_query_pattern = '{column_ci:real_name} = {string_ci:%s}';
			$email_query_pattern = 'email_address_ci = {string:%s}';
			$wildcard_replacements = [
				'\'' => '&#039;',
			];
		}

		$where = [];

		$params = [
			'buddy_list' => !empty(Config::$modSettings['enable_buddylist']) ? self::$me->buddies : [],
			'limit' => $max,
			'activated' => [self::ACTIVATED, self::ACTIVATED_BANNED],
		];

		$names = array_values(
			array_filter(
				array_map(
					fn($name) => trim((string) $name),
					\is_array($names) ? $names : explode(',', $names),
				),
				fn($name) => \strlen($name) > 0,
			),
		);

		foreach ($names as $i => $name) {
			if (str_contains($name, '@')) {
				$email = new EmailAddress($name, true);

				// If it's a valid email, search for it as one.
				if ($email->isValid()) {
					$where[] = \sprintf($email_query_pattern, 'lookup_email_' . $i);

					$params['lookup_email_' . $i] = strtr(
						$email->casefolded(),
						$wildcard_replacements,
					);
				}
				// If it's invalid because a wildcard is in the domain part,
				// then manually add it to our email search.
				elseif (
					strpbrk($email->ascii_domain_part, '*?') !== false
					&& $email->local_part !== ''
				) {
					$where[] = \sprintf($email_query_pattern, 'lookup_email_' . $i);

					$params['lookup_email_' . $i] = strtr(
						Utils::convertCase($email->local_part, 'fold') . '@' . $email->ascii_domain_part,
						$wildcard_replacements,
					);
				}
			}

			$where[] = \sprintf($member_name_query_pattern, 'lookup_name_' . $i);
			$where[] = \sprintf($real_name_query_pattern, 'lookup_name_' . $i);

			$params['lookup_name_' . $i] = strtr($name, $wildcard_replacements);
		}

		$where = [
			'(' . implode(' OR ', $where) . ')',
			'is_activated IN ({array_int:activated})',
		];

		if ($buddies_only) {
			$where[] = 'id_member IN ({array_int:buddy_list})';
		}

		if ($ids_only) {
			$request = Db::$db->query(
				'SELECT id_member
				FROM {db_prefix}members
				WHERE ' . implode(' AND ', $where) . '
				LIMIT {int:limit}',
				$params,
			);
			$found = array_map(fn($row) => $row['id_member'], Db::$db->fetch_all($request));
			Db::$db->free_result($request);
		} else {
			$found = array_map(
				fn($member) => $member->format(),
				self::loadCustom(
					query_customizations: [
						'where' => $where,
						'params' => $params,
					],
					dataset: UserDataset::Minimal,
				),
			);

			foreach ($found as $k => $formatted) {
				if (!$formatted['show_email']) {
					$found[$k]['email'] = '';
				}
			}
		}

		return $found;
	}

	/**
	 * Retrieves a list of members that have a given permission, either on a
	 * given board or in general.
	 *
	 * Will check for a board permission if $board_id is set, and any moderators
	 * assigned to that board will be fetched in addition to global moderators.
	 * Pass in 0 as a special case to fetch moderators on all boards.
	 *
	 * @param string $permission The permission to check.
	 * @param int|null $board_id If set, checks permission for that specific board.
	 * @return array IDs of the members who have that permission.
	 */
	public static function getAllowedTo(string $permission, ?int $board_id = null): array
	{
		$member_groups = Group::getAllWithPermissions($permission, $board_id);

		$include_moderators = $member_groups[Group::MOD][$permission] === 1 && $board_id !== null;
		$include_groups = array_keys(array_filter(
			$member_groups,
			fn($permissions, $group) => $permissions[$permission] === 1 && $group !== Group::MOD,
			ARRAY_FILTER_USE_BOTH,
		));

		$exclude_moderators = $member_groups[Group::MOD][$permission] === 0 && $board_id !== null;
		$exclude_groups = array_keys(array_filter(
			$member_groups,
			fn($permissions, $group) => $permissions[$permission] === 0 && $group !== Group::MOD,
			ARRAY_FILTER_USE_BOTH,
		));

		$request = Db::$db->query(
			($include_moderators && !$exclude_moderators && $board_id !== null ? '
			SELECT id_member
			FROM {db_prefix}moderators' . ($board_id !== 0 ? '
			WHERE id_board = {int:board_id}' : '') . '
			UNION ALL
			SELECT id_member
			FROM {db_prefix}members AS mem
				JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group = mem.id_group OR FIND_IN_SET(modgs.id_group, mem.additional_groups) != 0)' . ($board_id !== 0 ? '
			WHERE id_board = {int:board_id}' : '') . '
			UNION ALL' : '') . '
			SELECT id_member
			FROM {db_prefix}members
			WHERE (id_group IN ({array_int:include_groups}) OR id_post_group IN ({array_int:include_groups}))' . ($exclude_groups == [] ? '' : '
				AND NOT (id_group IN ({array_int:exclude_groups}) OR id_post_group IN ({array_int:exclude_groups}))') . '
			UNION ALL
			SELECT id_member
			FROM {db_prefix}members
			WHERE
				additional_groups != \'\'
				AND (FIND_IN_SET({raw:include_groups_implode}, additional_groups) != 0)' . ($exclude_groups == [] ? '' : '
				AND NOT (FIND_IN_SET({raw:exclude_groups_implode}, additional_groups) != 0)'),
			[
				'include_groups' => $include_groups,
				'exclude_groups' => $exclude_groups,
				'board_id' => $board_id,
				'include_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $include_groups),
				'exclude_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $exclude_groups),
			],
		);

		$members = array_unique(array_column(Db::$db->fetch_all($request), 'id_member'));
		Db::$db->free_result($request);

		return $members;
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
						[
							$_SESSION['id_robot'], time(), $date, 1,
						],
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
				[
					'id_spider' => 'int',
					'log_time' => 'int',
					'url' => 'string',
				],
				[
					[
						$_SESSION['id_robot'],
						time(),
						$url,
					],
				],
				[],
			);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets object properties based on data in User::$profiles[$this->id].
	 *
	 * @param bool $reset If true, discards all property values and sets them
	 *    afresh.
	 */
	protected function setProperties(bool $reset = false): void
	{
		// For developer convenience.
		$profile = &self::$profiles[$this->id];

		// Vital info.
		if ($reset || !isset($this->username)) {
			$this->username = $profile['member_name'] ?? '';
		}

		if ($reset || !isset($this->name)) {
			$this->name = $profile['real_name'] ?? '';
		}

		if ($reset || !isset($this->email)) {
			$this->email = $profile['email_address'] ?? '';
		}

		if ($reset || !isset($this->passwd)) {
			$this->passwd = $profile['passwd'] ?? '';
		}

		if ($reset || !isset($this->password_salt)) {
			$this->password_salt = $profile['password_salt'] ?? '';
		}

		if ($reset || !isset($this->tfa_secret)) {
			$this->tfa_secret = $profile['tfa_secret'] ?? '';
		}

		if ($reset || !isset($this->tfa_backup)) {
			$this->tfa_backup = $profile['tfa_backup'] ?? '';
		}

		if ($reset || !isset($this->secret_question)) {
			$this->secret_question = $profile['secret_question'] ?? '';
		}

		if ($reset || !isset($this->secret_answer)) {
			$this->secret_answer = $profile['secret_answer'] ?? '';
		}

		if ($reset || !isset($this->validation_code)) {
			$this->validation_code = $profile['validation_code'] ?? '';
		}

		if ($reset || !isset($this->passwd_flood)) {
			$this->passwd_flood = $profile['passwd_flood'] ?? '';
		}

		// User status.
		$this->setGroups($reset);
		$this->setPossiblyRobot();

		if ($reset || !isset($this->is_activated)) {
			$this->is_activated = (int) ($profile['is_activated'] ?? !$this->is_guest);
		}

		if ($reset || !isset($this->is_banned)) {
			$this->is_banned = $this->is_activated >= self::BANNED;
		}

		if ($reset || !isset($this->is_online)) {
			$this->is_online = (bool) ($profile['is_online'] ?? $this->is_me);
		}

		// User activity and history.
		if ($reset || !isset($this->show_online)) {
			$this->show_online = (bool) ($profile['show_online'] ?? false);
		}

		if ($reset || !isset($this->url)) {
			$this->url = $profile['url'] ?? '';
		}

		if ($reset || !isset($this->last_login)) {
			$this->last_login = (int) ($profile['last_login'] ?? 0);
		}

		if ($reset || !isset($this->id_msg_last_visit)) {
			$this->id_msg_last_visit = (int) ($profile['id_msg_last_visit'] ?? 0);
		}

		if ($reset || !isset($this->total_time_logged_in)) {
			$this->total_time_logged_in = (int) ($profile['total_time_logged_in'] ?? 0);
		}

		if ($reset || !isset($this->date_registered)) {
			$this->date_registered = (int) ($profile['date_registered'] ?? 0);
		}

		if ($reset || !isset($this->ip)) {
			$this->ip = $this->is_me ? IP::getUserIP() : $profile['member_ip'] ?? '';
		}

		if ($reset || !isset($this->ip2)) {
			$this->ip2 = match (true) {
				// Current user is behind a proxy, so use the alternative IP.
				$this->is_me && !\in_array(IP::getUserIPAlternative(), [IP::getUserIP(), '']) => IP::getUserIPAlternative(),
				// Current user has a new IP, so use their previous IP.
				$this->is_me && $this->ip !== ($profile['member_ip'] ?? '') => $profile['member_ip'] ?? '',
				// Either not the current user, or current user hasn't changed IPs.
				default => $profile['member_ip2'] ?? '',
			};
		}

		// Additional profile info.
		if ($reset || !isset($this->posts)) {
			$this->posts = (int) ($profile['posts'] ?? 0);
		}

		if ($reset || !isset($this->title)) {
			$this->title = $profile['usertitle'] ?? '';
		}

		if ($reset || !isset($this->signature)) {
			$this->signature = $profile['signature'] ?? '';
		}

		if ($reset || !isset($this->personal_text)) {
			$this->personal_text = $profile['personal_text'] ?? '';
		}

		if ($reset || !isset($this->birthdate)) {
			$this->birthdate = $profile['birthdate'] ?? '';
		}

		if ($reset || !isset($this->website['url'])) {
			$this->website['url'] = $profile['website_url'] ?? '';
		}

		if ($reset || !isset($this->website['title'])) {
			$this->website['title'] = $profile['website_title'] ?? '';
		}

		// Presentation preferences.
		if ($reset || !isset($this->theme)) {
			$this->theme = (int) ($profile['id_theme'] ?? 0);
		}

		if ($reset || !isset($this->options)) {
			$this->options = (array) ($profile['options'] ?? []);
		}

		if ($reset || !isset($this->smiley_set)) {
			$this->smiley_set = $profile['smiley_set'] ?? '';
		}

		// Localization.
		$this->setLanguage($reset);

		if ($reset || !isset($this->real_time_format)) {
			$this->real_time_format = $profile['time_format'] ?? '';
		}

		if ($reset || !isset($this->timezone)) {
			$this->timezone = match (true) {
				empty($this->id) => Config::$modSettings['default_timezone'] ?? date_default_timezone_get(),
				!\in_array($profile['timezone'] ?? null, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC)) => Config::$modSettings['default_timezone'] ?? date_default_timezone_get(),
				default => $profile['timezone'],
			};
		}

		// Buddies and personal messages.
		if ($reset || !isset($this->buddies)) {
			$this->buddies = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : [];
		}

		if ($reset || !isset($this->ignoreusers)) {
			$this->ignoreusers = !empty($profile['pm_ignore_list']) ? explode(',', $profile['pm_ignore_list']) : [];
		}

		if ($reset || !isset($this->pm_receive_from)) {
			$this->pm_receive_from = (int) ($profile['pm_receive_from'] ?? 0);
		}

		if ($reset || !isset($this->pm_prefs)) {
			$this->pm_prefs = (int) ($profile['pm_prefs'] ?? 0);
		}

		if ($reset || !isset($this->messages)) {
			$this->messages = (int) ($profile['instant_messages'] ?? 0);
		}

		if ($reset || !isset($this->unread_messages)) {
			$this->unread_messages = (int) ($profile['unread_messages'] ?? 0);
		}

		if ($reset || !isset($this->new_pm)) {
			$this->new_pm = (int) ($profile['new_pm'] ?? 0);
		}

		// What does the user want to see or know about?
		if ($reset || !isset($this->alerts)) {
			$this->alerts = (int) ($profile['alerts'] ?? 0);
		}

		if ($reset || !isset($this->ignoreboards)) {
			$this->ignoreboards = !empty($profile['ignore_boards']) ? explode(',', $profile['ignore_boards']) : [];
		}

		// Extended membergroup info.
		if ($reset || !isset($this->group_name)) {
			$this->group_name = $profile['member_group'] ?? '';
		}

		if ($reset || !isset($this->post_group_name)) {
			$this->post_group_name = $profile['post_group'] ?? '';
		}

		if ($reset || !isset($this->group_color)) {
			$this->group_color = $profile['member_group_color'] ?? '';
		}

		if ($reset || !isset($this->post_group_color)) {
			$this->post_group_color = $profile['post_group_color'] ?? '';
		}

		if ($reset || !isset($this->icons)) {
			$this->icons = empty($profile['icons']) ? ['', ''] : explode('#', $profile['icons']);
		}

		if ($reset || !isset($this->primary_group_id)) {
			$this->primary_group_id = $this->group_id;
		}

		if ($reset || !isset($this->primary_group_name)) {
			$this->primary_group_name = $profile['primary_group'] ?? '';
		}

		if ($reset || !isset($this->primary_group_color)) {
			$this->primary_group_color = $this->group_color;
		}

		if ($reset || !isset($this->primary_group_icons)) {
			$this->primary_group_icons = $this->icons;
		}

		// The avatar is a complicated thing, and historically had multiple
		// representations in the code. This supports everything.
		if ($reset || empty($this->avatar->url)) {
			$this->avatar = new Avatar(
				url: $profile['avatar'] ?? null,
				original_url: $profile['avatar_original'] ?? null,
				filename: $profile['filename'] ?? null,
				id_attach: isset($profile['id_attach']) ? (int) $profile['id_attach'] : null,
				attachment_type: isset($profile['attachment_type']) ? (int) $profile['attachment_type'] : null,
				width: isset($profile['attachment_width']) ? (int) $profile['attachment_width'] : null,
				height: isset($profile['attachment_height']) ? (int) $profile['attachment_height'] : null,
				email: $this->email,
				id_member: $this->id,
			);
		}

		// Info about stuff related to permissions.
		// Note that we populate $this->permission_sets elsewhere.
		if ($reset || !isset($this->warning)) {
			$this->warning = (int) ($profile['warning'] ?? 0);
		}

		if ($reset || !isset($this->can_manage_boards)) {
			$this->can_manage_boards = (
				!empty($this->is_admin)
				|| (
					!empty(Config::$modSettings['board_manager_groups'])
					&& !empty($this->groups)
					&& array_intersect(
						$this->groups,
						explode(',', Config::$modSettings['board_manager_groups']),
					) !== []
				)
			);
		}

		if ($reset || !isset($this->query_see_board)) {
			$this->buildQueryBoard();
		}

		// What dataset did we load for this user?
		$this->dataset = $profile['dataset'];

		// Basic handling for any custom profile data. If mods want to do
		// anything more complicated, they can use the hook below.
		foreach ($profile as $key => $value) {
			if (
				!\in_array(
					$key,
					[
						// All the standard data.
						'additional_groups',
						'alerts',
						'attachment_height',
						'attachment_type',
						'attachment_width',
						'avatar',
						'avatar_original',
						'birthdate',
						'buddy_list',
						'dataset',
						'date_registered',
						'email_address',
						'email_address_ci',
						'filename',
						'icons',
						'id_attach',
						'id_group',
						'id_member',
						'id_msg_last_visit',
						'id_post_group',
						'id_theme',
						'ignore_boards',
						'instant_messages',
						'is_activated',
						'is_online',
						'last_login',
						'lngfile',
						'member_group',
						'member_group_color',
						'member_ip',
						'member_ip2',
						'member_name',
						'new_pm',
						'options',
						'passwd',
						'passwd_flood',
						'password_salt',
						'personal_text',
						'pm_ignore_list',
						'pm_prefs',
						'pm_receive_from',
						'post_group',
						'post_group_color',
						'posts',
						'primary_group',
						'real_name',
						'secret_answer',
						'secret_question',
						'show_online',
						'signature',
						'smiley_set',
						'spoofdetector_name',
						'tfa_backup',
						'tfa_secret',
						'time_format',
						'timezone',
						'total_time_logged_in',
						'unread_messages',
						'url',
						'usertitle',
						'validation_code',
						'warning',
						'website_title',
						'website_url',
						// Obsolete data. Ignore if present.
						'mod_prefs',
						'time_offset',
					],
				)
			) {
				if ($reset || !isset($this->{$key})) {
					$this->{$key} = is_numeric($value) ? $value + 0 : $value;
				}
			}
		}

		/*
		 * Allows mods to add or adjust properties.
		 *
		 * MOD AUTHORS: If you use this hook, you probably also want to use the
		 * integrate_save_member_data hook to control how your data is saved
		 * back to the database.
		 */
		IntegrationHook::call('integrate_user_properties', [$this, &$profile]);
	}

	/**
	 * Wrapper for integrate_verify_user hook. Allows integrations to verify
	 * the current user's identity for us.
	 */
	protected function integrateVerifyUser(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		if (\count($integration_ids = IntegrationHook::call('integrate_verify_user')) === 0) {
			return;
		}

		foreach ($integration_ids as $integration_id) {
			if (\intval($integration_id) > 0) {
				self::$my_id = (int) $integration_id;
				$this->already_verified = true;
				break;
			}
		}
	}

	/**
	 * Sets User::$my_id and User::$cookie_password to the current user's ID
	 * and encrypted password from the login cookie.
	 *
	 * If no cookie was provided, checks $_SESSION to see if there is a match
	 * with an existing session.
	 *
	 * On failure, User::$my_id is set to 0.
	 */
	protected function setMyId(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// Do nothing if an integration already did this job.
		if ($this->already_verified) {
			return;
		}

		// Did they give us a cookie?
		if (isset($_COOKIE[Config::$cookiename])) {
			// First try JSON format cookie
			$cookie_data = Utils::jsonDecode($_COOKIE[Config::$cookiename], true, 512, 0, false);

			// Legacy format (for recent upgrades from SMF 2.0)
			if (empty($cookie_data)) {
				$cookie_data = Utils::safeUnserialize($_COOKIE[Config::$cookiename]);
			}

			// Extract the cookie data.
			list($id, self::$cookie_password, $expires, $cookie_domain, $cookie_path) = array_pad((array) ($cookie_data ?? []), 5, '');

			// Make sure the cookie is set to the correct domain and path.
			if (
				isset($_COOKIE[Config::$cookiename])
				&& [$cookie_domain, $cookie_path] !== Cookie::urlParts(
					!empty(Config::$modSettings['localCookies']),
					!empty(Config::$modSettings['globalCookies']),
				)
			) {
				Cookie::setLoginCookie((int) $expires - time(), self::$my_id);
			}
		}
		// Can we recover it from session data?
		elseif (
			isset($_SESSION['login_' . Config::$cookiename])
			&& (
				$_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT']
				|| !empty(Config::$modSettings['disableCheckUA'])
			)
		) {
			// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
			$cookie_data = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename], true);

			// Extract the cookie data.
			list($id, self::$cookie_password, $expires) = array_pad((array) ($cookie_data ?? []), 3, '');
		}

		if (
			empty($id)
			|| \strlen(self::$cookie_password) === 0
			|| (int) $expires <= time()
		) {
			self::$my_id = 0;

			return;
		}

		// Found it.
		self::$my_id = (int) $id;

		// Do they want to stay logged in?
		$this->stay_logged_in = ((int) $expires - time()) > 86400;
	}

	/**
	 * Figures out which dataset we want to load for the current user.
	 *
	 * @return UserDataset The name of a dataset to load.
	 */
	protected function chooseMyDataset(): UserDataset
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// Board index, message index, or topic.
		if (
			!isset($_REQUEST['action'])
			|| \in_array($_REQUEST['action'], ['boardindex', 'messageindex', 'display'])
		) {
			return UserDataset::Normal;
		}

		// Profile.
		if ($_REQUEST['action'] === 'profile') {
			return \in_array($_GET['area'] ?? null, ['popup', 'alerts_popup', 'download', 'dlattach']) ? UserDataset::Basic : UserDataset::Profile;
		}

		// Personal messages.
		if ($_REQUEST['action'] === 'pm') {
			return ($_GET['sa'] ?? null) === 'popup' ? UserDataset::Basic : UserDataset::Profile;
		}

		// Who's Online.
		if ($_REQUEST['action'] === 'who') {
			return UserDataset::Normal;
		}

		// Everything else.
		return UserDataset::Basic;
	}

	/**
	 * Verifies that the supplied password was correct.
	 *
	 * If not, User::$my_id is set to 0, and we take steps to prevent brute
	 * force hacking attempts.
	 */
	protected function verifyPassword(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// Do nothing if this is a guest.
		if (empty(self::$my_id)) {
			return;
		}

		// Can't log into an account that doesn't exist.
		if (
			!isset(
				self::$profiles[self::$my_id]['member_name'],
				self::$profiles[self::$my_id]['passwd'],
				self::$profiles[self::$my_id]['password_salt'],
				self::$profiles[self::$my_id]['is_activated'],
			)
		) {
			self::$my_id = 0;

			return;
		}

		// Did they supply the correct password? (Assume true if already verified.)
		$password_correct = !empty($this->already_verified) ? true : hash_equals(
			Cookie::encrypt(
				self::$profiles[self::$my_id]['passwd'],
				self::$profiles[self::$my_id]['password_salt'],
			),
			self::$cookie_password,
		);

		// Wrong password or not activated - either way, you're going nowhere.
		if (
			!$password_correct
			|| self::$profiles[self::$my_id]['is_activated'] % self::BANNED !== self::ACTIVATED
		) {
			$id = self::$my_id;
			self::$my_id = 0;

			Security::validatePasswordFlood(
				$id,
				self::$profiles[$id]['member_name'],
				self::$profiles[$id]['passwd_flood'],
				$password_correct,
			);
		}
	}

	/**
	 * If appropriate for this user, performs two factor authentication check.
	 */
	protected function verifyTfa(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		if (
			// Do nothing if this is a guest.
			empty(self::$my_id)
			// Do nothing if two factor authentication is disabled.
			|| empty(Config::$modSettings['tfa_mode'])
		) {
			return;
		}

		// If they've set up Two Factor Authentication, validate it.
		if (!empty(self::$profiles[self::$my_id]['tfa_secret'])) {
			// If they are performing the TFA login action itself, make sure
			// to reset their ID for security, but otherwise leave it to the
			// action to verify the TFA credentials.
			if (($_REQUEST['action'] ?? '') === 'logintfa') {
				Utils::$context['tfa_member_id'] = self::$my_id;
				self::$my_id = 0;

				return;
			}

			// Don't get stuck in a loop.
			if (($_REQUEST['action'] ?? '') === 'login2') {
				return;
			}

			// Do any mods want to verify their TFA credentials for us?
			$verified = IntegrationHook::call('integrate_verify_tfa', [self::$my_id, self::$profiles[self::$my_id]]);

			if (\in_array(true, $verified)) {
				return;
			}

			// Verify their TFA credentials ourselves.
			$tfa_cookie = Config::$cookiename . '_tfa';
			$tfa_secret = '';

			if (!empty($_COOKIE[$tfa_cookie])) {
				$tfa_data = Utils::jsonDecode($_COOKIE[$tfa_cookie], true);

				list($tfa_member_id, $tfa_secret) = array_pad((array) $tfa_data, 2, '');

				if ((int) $tfa_member_id !== self::$my_id) {
					$tfa_secret = '';
				}
			}

			// If they didn't provide the correct TFA credentials, they're no one to us.
			if (
				!hash_equals(
					$tfa_secret,
					Cookie::encrypt(
						self::$profiles[self::$my_id]['tfa_backup'],
						self::$profiles[self::$my_id]['password_salt'],
					),
				)
			) {
				Cookie::setLoginCookie(-3600, self::$my_id);
				self::$profiles[self::$my_id] = [];
				self::$my_id = 0;
			}

			return;
		}

		// They don't have any TFA credentials. Do they need to create some?
		$force_tfasetup = (
			// 1. The TFA setting requires it.
			Config::$modSettings['tfa_mode'] >= 2
			// 2. This is happening within the forum itself (not SSI, cron, etc.)
			&& SMF === 1
			// 3. This is not an AJAX request.
			&& !isset($_REQUEST['xml'])
			// 4. The requested action does NOT meet any of the following criteria:
			&& !(
				// 4.a. Logging out.
				($_REQUEST['action'] ?? null) === 'logout'
				// 4.b. Getting an RSS feed.
				|| ($_REQUEST['action'] ?? null) === 'feed'
				// 4.c. Doing the TFA setup.
				|| (
					($_REQUEST['action'] ?? null) === 'profile'
					&& ($_REQUEST['area'] ?? null) === 'tfasetup'
				)
				// 4.d. Viewing one of the profile popups.
				|| (
					($_REQUEST['action'] ?? null) === 'profile'
					&& \in_array($_REQUEST['area'] ?? null, ['popup', 'alerts_popup'])
				)
				// 4.d. Viewing the personal messages popup.
				|| (
					($_REQUEST['action'] ?? null) === 'pm'
					&& ($_REQUEST['sa'] ?? null) == 'popup'
				)
			)
		);

		// Allow mods to turn off $force_tfasetup for their own actions.
		// Note: we don't let mods turn it on if we already turned it off.
		if ($force_tfasetup) {
			IntegrationHook::call('integrate_force_tfasetup', [&$force_tfasetup]);
		}

		// If we are only forcing SOME membergroups to use TFA, check whether
		// this member belongs to any of those groups.
		if ($force_tfasetup && Config::$modSettings['tfa_mode'] == 2) {
			$this->setGroups();

			if (empty(Group::load($this->groups, ['where' => ['tfa_required = 1']]))) {
				$force_tfasetup = false;
			}
		}

		// Are we forcing TFA?
		if ($force_tfasetup) {
			Utils::redirectexit('action=profile;area=tfasetup;forced');
		}
	}

	/**
	 * Determines the 'id_msg_last_visit' value, which is used to figure out
	 * what counts as new content for this user.
	 */
	protected function setLastVisit(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

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
				|| !\in_array($_REQUEST['action'], ['feed', 'login2', 'logintfa'])
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
				$this->id_msg_last_visit = (int) Config::$modSettings['maxMsgID'];
				$this->last_login = time();
				$this->ip = IP::getUserIP();
				$this->ip2 = IP::getUserIPAlternative();
				$this->save();

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
		// This only applies to the current user.
		if (!$this->is_me) {
			// Complain loudly about this programmer error.
			throw new \LogicException('Called ' . __METHOD__ . ' for a user that is not ' . __CLASS__ . '::$me');
		}

		// Ensure the guest profile has been loaded.
		if (!isset(self::$profiles[0])) {
			self::loadUserData([0]);
		}

		// If they gave us a bad cookie, discard it.
		if (isset($_COOKIE[Config::$cookiename]) && empty(Utils::$context['tfa_member_id'])) {
			$_COOKIE[Config::$cookiename] = '';
		}

		// Expire the 2FA cookie
		if (isset($_COOKIE[Config::$cookiename . '_tfa']) && empty(Utils::$context['tfa_member_id'])) {
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
	 * Determines which membergroups this user belongs to.
	 *
	 * @param bool $reset If true, discard current group info and set it afresh.
	 */
	protected function setGroups(bool $reset = false): void
	{
		$default_group = empty($this->id) ? Group::GUEST : Group::REGULAR;

		if ($reset || !isset($this->group_id)) {
			$this->group_id = (int) (self::$profiles[$this->id]['id_group'] ?? $default_group);
		}

		if ($reset || !isset($this->post_group_id)) {
			$this->post_group_id = (int) (self::$profiles[$this->id]['id_post_group'] ?? $default_group);
		}

		if ($reset || !isset($this->additional_groups)) {
			$this->additional_groups = array_map('intval', array_filter(explode(',', self::$profiles[$this->id]['additional_groups'] ?? '')));
		}

		$this->groups = array_unique(array_merge(
			[$default_group, $this->group_id, $this->post_group_id],
			$this->additional_groups,
		));
	}

	/**
	 * Do we perhaps think this is a search robot?
	 */
	protected function setPossiblyRobot(): void
	{
		// This check only applies to the current user.
		if (!$this->is_me) {
			$this->possibly_robot = false;

			return;
		}

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
						elseif (IP::getUserIP() !== '') {
							$ips = explode(',', $spider['ip_info']);

							foreach ($ips as $ip) {
								if ($ip === '') {
									continue;
								}

								$ip_range = IP::ip2range($ip);

								$remote_ip = new IP(IP::getUserIP());

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

					$this->possibly_robot = !empty($_SESSION['id_robot']);
				}
			} elseif (!empty(Config::$modSettings['spider_mode'])) {
				$this->possibly_robot = !empty($_SESSION['id_robot']);
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
	 *
	 * @param bool $reset If true, discard current value of $this->language and
	 *    set it afresh.
	 */
	protected function setLanguage(bool $reset = false): void
	{
		if (!$reset && isset($this->language)) {
			return;
		}

		// Is everyone forced to use the default language?
		if (empty(Config::$modSettings['userLanguage'])) {
			$this->language = Config::$language;

			return;
		}

		// Which language does this user prefer?
		$this->language = empty(self::$profiles[$this->id]['lngfile']) ? Config::$language : self::$profiles[$this->id]['lngfile'];

		// If this isn't the current user, we're done.
		if (!$this->is_me) {
			return;
		}

		// Allow the user to change their language.
		$languages = Lang::get();

		// Change was requested in URL parameters.
		if (!empty($_GET['language']) && \is_string($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')])) {
			$this->language = strtr($_GET['language'], './\\:', '____');

			// Make it permanent for members.
			if (!empty($this->id)) {
				$this->save();
				unset($_SESSION['language']);
			} else {
				$_SESSION['language'] = $this->language;
			}

			// Reload same URL with new language, if applicable.
			if (isset($_SESSION['old_url'])) {
				Utils::redirectexit(preg_replace('~language=[^;&$]+~i', '', $_SESSION['old_url']));
			}
		}
		// Carry forward the last language request in this session, if any.
		elseif (!empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')])) {
			$this->language = strtr($_SESSION['language'], './\\:', '____');
		}
		// Can we locate it in the accept language?
		elseif ($this->id === 0 && empty($_SESSION['language']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $match) {
				[$lang] = explode(';', $match);

				// The strtr fallback is a sad, weak substitute, but we might as well try.
				$lang = class_exists('\Locale') ? \Locale::canonicalize($lang) : strtr($lang, '-', '_');

				if (\is_null($lang)) {
					continue;
				}

				if (isset($languages[$lang])) {
					$this->language = $_SESSION['language'] = $lang;
				}
			}
		}
	}

	/**
	 * Callback for the property hooks of $this->is_mod that determines the
	 * correct value to use and also adjusts other properties as necessary to
	 * reflect this user's moderator status (or lack thereof).
	 *
	 * Guests can never be moderators, so if this method is called on a guest,
	 * the $is_mod argument will be ignored and overridden by false.
	 *
	 * @todo Returns by reference in order to make it easier for the property
	 *    to maintain compatibility with \ArrayAccess. Once \ArrayAccess
	 *    compatibility is no longer required, this method can be changed to
	 *    not return by reference.
	 *
	 * @param ?bool $is_mod Whether this user should be given moderator status.
	 *    If null, will be determined by whether this user's groups include the
	 *    global moderator and/or local moderator group. Default: null.
	 * @return bool Whether this user should have moderator status.
	 */
	protected function &isMod(?bool $is_mod = null): bool
	{
		if (!isset($this->groups)) {
			$this->setGroups();
		}

		// Guests can never be moderators.
		if ($this->is_guest) {
			$is_mod = false;
		}

		if (!isset($is_mod)) {
			$is_mod = array_intersect([Group::GLOBAL_MOD, Group::MOD], $this->groups) !== [];
		}

		if ($is_mod) {
			// By popular demand, don't show admins or global moderators as
			// local moderators.
			if (!\in_array($this->group_id ?? Group::REGULAR, [Group::ADMIN, Group::GLOBAL_MOD])) {
				$moderator_group = current(Group::load(Group::MOD));

				// Set this member's group name to Moderator.
				$this->group_name = $moderator_group->name;

				// Set this member's icons and color to those for a moderator
				// (unless the moderator group has no color or icons).
				if (!empty($moderator_group->icons)) {
					$this->icons = array_pad(explode('#', $moderator_group->icons), 2, '');
				}

				if (!empty($moderator_group->online_color)) {
					$this->group_color = $moderator_group->online_color;
				}
			}

			// Make this user a local moderator if they're not already a global
			// or local moderator.
			if (array_intersect([Group::GLOBAL_MOD, Group::MOD], $this->groups) === []) {
				$this->groups[] = Group::MOD;
			}
		} else {
			$this->group_id = $this->primary_group_id ?? $this->group_id ?? ($this->is_guest ? Group::GUEST : Group::REGULAR);
			$this->group_name = $this->primary_group_name ?? '';
			$this->group_color = $this->primary_group_color ?? '';
			$this->icons = $this->primary_group_icons ?? [];

			$this->groups = array_diff($this->groups, [Group::MOD]);
		}

		return $is_mod;
	}

	/**
	 * Builds query_see_board (and all its variants) for this user.
	 */
	protected function buildQueryBoard(): void
	{
		if (!isset($this->groups)) {
			return;
		}

		// Just build this here, it makes it easier to change/use - administrators can see all boards.
		if ($this->is_admin || $this->can_manage_boards) {
			$this->query_see_board = '1=1';
		}
		// Otherwise only the boards that can be accessed by the groups this user belongs to.
		else {
			$this->query_see_board = '
				EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN (' . implode(',', $this->groups) . ')
						AND bpv.deny = 0
						AND bpv.id_board = b.id_board
				)';

			if (!empty(Config::$modSettings['deny_boards_access'])) {
				$this->query_see_board .= '
				AND NOT EXISTS (
					SELECT bpv.id_board
					FROM ' . Db::$db->prefix . 'board_permissions_view AS bpv
					WHERE bpv.id_group IN ( ' . implode(',', $this->groups) . ')
						AND bpv.deny = 1
						AND bpv.id_board = b.id_board
				)';
			}
		}

		$this->query_see_message_board = str_replace('b.', 'm.', $this->query_see_board);
		$this->query_see_topic_board = str_replace('b.', 't.', $this->query_see_board);

		// Build the list of boards they WANT to see.
		// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

		// If they aren't ignoring any boards then they want to see all the boards they can see
		if (empty(Config::$modSettings['allow_ignore_boards']) || empty($this->ignoreboards)) {
			$this->query_wanna_see_board = $this->query_see_board;
			$this->query_wanna_see_message_board = $this->query_see_message_board;
			$this->query_wanna_see_topic_board = $this->query_see_topic_board;
		}
		// Ok I guess they don't want to see all the boards
		else {
			$this->query_wanna_see_board = '(' . $this->query_see_board . ' AND b.id_board NOT IN (' . implode(',', $this->ignoreboards) . '))';
			$this->query_wanna_see_message_board = '(' . $this->query_see_message_board . ' AND m.id_board NOT IN (' . implode(',', $this->ignoreboards) . '))';
			$this->query_wanna_see_topic_board = '(' . $this->query_see_topic_board . ' AND t.id_board NOT IN (' . implode(',', $this->ignoreboards) . '))';
		}
	}

	/**
	 * Loads the mod cache data.
	 *
	 * Stores the information on the current user's moderation powers in
	 * User::$me->mod_cache and $_SESSION['mc'].
	 */
	protected function loadModCache(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Quietly ignore this.
			return;
		}

		if (
			isset($_SESSION['mc'])
			&& $_SESSION['mc']['time'] > Config::$modSettings['settings_updated']
			&& $_SESSION['mc']['id'] == $this->id
		) {
			$this->mod_cache = $_SESSION['mc'];
		} else {
			$this->rebuildModCache();
		}

		// Now that we have the mod cache taken care of, let's setup a cache
		// for the number of mod reports still open.
		if (
			isset($_SESSION['rc']['reports'], $_SESSION['rc']['member_reports'])
			&& $_SESSION['rc']['time'] > Config::$modSettings['last_mod_report_action']
			&& $_SESSION['rc']['id'] == $this->id
		) {
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
	 * Quickly find out what moderation authority the current user has
	 *
	 * Builds the moderator, group and board level queries for the user.
	 *
	 * Stores the information on the current users moderation powers in
	 * User::$me->mod_cache and $_SESSION['mc'].
	 */
	protected function rebuildModCache(): void
	{
		// This only applies to the current user.
		if (!$this->is_me) {
			// Quietly ignore this.
			return;
		}

		// What groups can they moderate?
		if (!$this->is_guest) {
			$group_query = $this->allowedTo('manage_membergroups') ? '1=1' : '0=1';
		} else {
			$group_query = '0=1';
		}

		if ($group_query == '0=1' && !$this->is_guest) {
			$groups = [];

			$request = Db::$db->query(
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
		if (!$this->is_guest) {
			$board_query = $this->allowedTo('moderate_forum') ? '1=1' : '0=1';
		} else {
			$board_query = '0=1';
		}

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
			'ap' => !$this->is_guest ? $this->boardsAllowedTo('approve_posts') : [],
			'mb' => $boards_mod,
			'mq' => $mod_query,
		];

		IntegrationHook::call('integrate_mod_cache');

		$this->mod_cache = $_SESSION['mc'];

		// Might as well clean up some tokens while we are at it.
		SecurityToken::clean();
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
	 * @param UserDataset $dataset The set of data to load.
	 * @return array The IDs of the loaded members.
	 */
	protected static function loadUserData(array $users, int $type = self::LOAD_BY_ID, UserDataset $dataset = UserDataset::Normal): array
	{
		if (!$dataset->includes(UserDataset::Minimal)) {
			// Complain loudly about this programmer error.
			throw new \ValueError('Must load at least the minimal dataset for a user');
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
		if (\in_array(0, $users)) {
			foreach ((new MembersTable())->columns as $column) {
				self::$profiles[0][$column->name] = match (true) {
					$column->name === 'timezone' => Config::$modSettings['default_timezone'] ?? date_default_timezone_get(),
					$column->name === 'time_format' => Config::$modSettings['time_format'] ?? '%F %k:%M',
					$column->name === 'id_group' => Group::GUEST,
					$column->name === 'id_post_group' => Group::GUEST,
					$column->name === 'is_activated' => self::NOT_ACTIVATED,
					isset($column->default) => $column->default,
					str_contains($column->type, 'int') => 0,
					default => '',
				};
			}

			self::$profiles[0] = self::processRawUserData(self::$profiles[0]);
			self::$profiles[0]['dataset'] = UserDataset::Minimal;

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

				if (self::$profiles[$id]['dataset']->includes($dataset)) {
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Is the member data cached?
		if ($type === self::LOAD_BY_ID && !empty(CacheApi::$enable)) {
			foreach ($users as $key => $id) {
				unset($data);

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

					if (($data = CacheApi::get('member_data-' . $dataset->value . '-' . $id, 240)) == null) {
						continue;
					}
				}

				if (!\is_array($data)) {
					continue;
				}

				$data['dataset'] = UserDataset::tryFrom($data['dataset'] ?? 'none') ?? UserDataset::None;

				// Does the cached data have everything we need?
				if ($data['dataset']->includes($dataset)) {
					self::$profiles[$id] = $data;
					$loaded_ids[] = $id;
					unset($users[$key]);
				}
			}
		}

		// Look up any un-cached member data.
		if (!empty($users)) {
			$query_customizations = [
				'selects' => ['mem.*'],
				'joins' => [],
				'where' => [],
				'order' => [],
				'group' => [],
				'limit' => 0,
				'params' => [],
			];

			self::addQueryCustomizationsForLoadType($query_customizations, $users, $type);
			self::addQueryCustomizationsForDataset($query_customizations, $dataset);

			$loaded_ids = array_merge(
				$loaded_ids,
				self::retrieveUserData($query_customizations, $dataset),
			);

			if (!empty(CacheApi::$enable) && $dataset->includes(UserDataset::Minimal)) {
				foreach ($loaded_ids as $id) {
					if (CacheApi::$enable >= 2 && $id === (self::$my_id ?? NAN)) {
						CacheApi::put('user_settings-' . $id, self::$profiles[$id], 60);
					}

					if (CacheApi::$enable >= 3) {
						CacheApi::put('member_data-' . $dataset->value . '-' . $id, self::$profiles[$id], 240);
					}
				}
			}
		}

		return $loaded_ids;
	}

	/**
	 * Adds stuff to $query_customizations based on $users and $type.
	 *
	 * @param array &$query_customizations
	 * @param array $users Users specified by ID, name, or email address.
	 * @param int $type Whether $users contains IDs, names, or email addresses.
	 *    Possible values are this class's LOAD_BY_* constants.
	 */
	protected static function addQueryCustomizationsForLoadType(array &$query_customizations, array $users, int $type): void
	{
		switch ($type) {
			case self::LOAD_BY_EMAIL:
				$query_customizations['where'][] = 'mem.email_address_ci IN ({array_string:users})';
				$query_customizations['params']['users'] = array_filter(array_map(
					fn($email) => EmailAddress::create($email)->casefolded(),
					$users,
				));
				break;

			case self::LOAD_BY_NAME:
				$query_customizations['where'][] = '{column_ci:mem.member_name} IN ({array_string_ci:users})';
				$query_customizations['params']['users'] = $users;

				break;

			default:
				$query_customizations['where'][] = 'mem.id_member IN ({array_int:users})';
				$query_customizations['params']['users'] = $users;
				break;
		}
	}

	/**
	 * Adds stuff to $query_customizations based on $dataset.
	 *
	 * @param array &$query_customizations
	 * @param UserDataset $dataset
	 */
	protected static function addQueryCustomizationsForDataset(array &$query_customizations, UserDataset $dataset): void
	{
		switch ($dataset) {
			case UserDataset::Profile:
				$query_customizations['selects'][] = 'lo.url';
				// no break

			case UserDataset::Normal:
				$query_customizations['selects'] = array_merge(
					$query_customizations['selects'],
					[
						'COALESCE(lo.log_time, 0) AS is_online',
						'mg.online_color AS member_group_color',
						'COALESCE(mg.group_name, {empty}) AS member_group',
						'pg.online_color AS post_group_color',
						'COALESCE(pg.group_name, {empty}) AS post_group',
						'CASE WHEN mem.id_group = 0 OR mg.icons = {empty} THEN pg.icons ELSE mg.icons END AS icons',
					],
				);

				$query_customizations['joins'] = array_merge(
					$query_customizations['joins'],
					[
						'LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)',
						'LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)',
						'LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)',
					],
				);
				// no break

			case UserDataset::Basic:
				$query_customizations['selects'] = array_merge(
					$query_customizations['selects'],
					[
						'COALESCE(a.id_attach, 0) AS id_attach',
						'a.filename',
						'a.attachment_type',
						'a.width AS attachment_width',
						'a.height AS attachment_height',
					],
				);

				$query_customizations['joins'][] = 'LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)';
				// no break

			case UserDataset::Minimal:
				// We always want all the columns in the members table.
				if (!\in_array('mem.*', $query_customizations['selects'])) {
					array_unshift($query_customizations['selects'], 'mem.*');
				}

				// Try to avoid duplicate columns in the result rows.
				foreach ($query_customizations['selects'] as $sel_num => $select) {
					if (preg_match('/^mem\.\w+$/', $select)) {
						unset($query_customizations['selects'][$sel_num]);
					}
				}

				break;
		}

		$query_customizations['selects'] = array_unique($query_customizations['selects']);
		$query_customizations['joins'] = array_unique($query_customizations['joins']);

		// Allow mods to easily add to the selected member data
		IntegrationHook::call('integrate_load_member_data', [&$query_customizations['selects'], &$query_customizations['joins'], $dataset->value]);
	}

	/**
	 * Populates self::$profiles with data retrieved via self::queryData()
	 *
	 * @param array $query_customizations Customizations to the SQL query.
	 * @param UserDataset $dataset
	 * @return array The IDs of the loaded members.
	 */
	protected static function retrieveUserData(array $query_customizations, UserDataset $dataset): array
	{
		$loaded_ids = [];

		foreach (self::queryData(...$query_customizations) as $row) {
			$row = self::processRawUserData($row);

			// Save it.
			// Use array_merge here to avoid data loss if we call this multiple
			// times for the same member with different datasets.
			self::$profiles[$row['id_member']] = array_merge(
				self::$profiles[$row['id_member']] ?? [],
				$row,
			);

			// If this is the current user's data, alias it to User::$settings.
			if ($row['id_member'] === (self::$my_id ?? NAN)) {
				self::$settings = &self::$profiles[$row['id_member']];
			}

			$loaded_ids[] = $row['id_member'];
		}

		if (!empty($loaded_ids) && $dataset->exceeds(UserDataset::Minimal)) {
			self::loadOptions($loaded_ids);
		}

		foreach ($loaded_ids as $id) {
			self::$profiles[$id]['dataset'] = $dataset;
		}

		// This hook's name is due to historical reasons.
		IntegrationHook::call('integrate_load_min_user_settings', [&self::$profiles]);

		return $loaded_ids;
	}

	/**
	 * Generator that runs queries about user data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}members AS mem' is always part of the query.
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
	 * @return \Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0): \Generator
	{
		$request = Db::$db->query(
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}members AS mem' . (empty($joins) ? '' : '
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

	/**
	 * Helper for self::retrieveUserData() that does some basic processing of
	 * retrieved data records.
	 *
	 * @param array $row A row of data.
	 * @return array Updated $row.
	 */
	protected static function processRawUserData(array $row): array
	{
		$row['id_member'] = (int) $row['id_member'];

		// If the image proxy is enabled, we still want the original URL when they're editing the profile...
		$row['avatar_original'] = $row['avatar'] ??= '';

		// Take care of proxying the avatar if required.
		if (!empty($row['avatar'])) {
			$row['avatar'] = Url::create($row['avatar'])->proxied();
		}

		// Keep track of the member's normal member group.
		$row['primary_group'] = $row['member_group'] ??= '';
		$row['post_group'] ??= '';
		$row['member_group_color'] ??= '';
		$row['post_group_color'] ??= '';

		// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
		$row['ignore_boards'] = rtrim($row['ignore_boards'] ?? '', ',');

		// Unpack the IP addresses.
		foreach (['member_ip', 'member_ip2'] as $key) {
			$row[$key] = ($row[$key] ?? '') !== '' ? (string) new IP($row[$key]) : '';
		}

		$row['is_online'] = $row['is_online'] ?? $row['id_member'] === (self::$my_id ?? NAN);

		// Declare this for now. We'll fill it in later.
		$row['options'] = [];

		return $row;
	}

	/**
	 * Loads theme options for the given users.
	 *
	 * @param array|int $ids One or more user ID numbers.
	 * @param ?bool $default_only If true, only load options for the default
	 *    theme, where "default theme" means whichever theme is used for guests.
	 *    Default: false.
	 */
	protected static function loadOptions(array|int $ids, bool $default_only = false): void
	{
		$ids = (array) $ids;

		$request = Db::$db->query(
			'SELECT id_member, id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN ({array_int:ids})
			ORDER BY id_theme',
			[
				'ids' => $ids,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Which theme is used for guests?
			$guest_theme = (int) (Config::$modSettings['theme_guests'] ?? 1);

			// Which theme is this member using?
			$user_theme = $default_only ? $guest_theme : (int) (self::$profiles[$row['id_member']]['id_theme'] ?? $guest_theme);

			if (
				// Rows are returned in ascending order by id_theme, so we start
				// with theme 1's value and then overwrite as needed.
				$row['id_theme'] == 1
				// If the guest theme isn't theme 1, then overwrite the value
				// from theme 1 with the value from the guest theme.
				|| (
					$row['id_theme'] == $guest_theme
					// Special check needed here to ensure the guest theme does
					// not overwrite the user's preferred theme. For example, if
					// the guest theme is 5 but the user's theme is 2, then we
					// want to keep the value from theme 2.
					&& $guest_theme < $user_theme
				)
				// The value from the user's preferred theme takes precedence.
				|| $row['id_theme'] == $user_theme
			) {
				self::$profiles[$row['id_member']]['options'][$row['variable']] = $row['value'];
			}
		}

		Db::$db->free_result($request);
	}

	/**
	 * Anonymizes the specified member's personally identifying information.
	 *
	 * @param int $member The ID of the member to anonymize.
	 */
	protected static function anonymize(int $member): void
	{
		// This might take a while.
		Sapi::setTimeLimit();

		$anonymous_uuid = Uuid::create(5, 'member=' . $member)->getShortForm(true);
		$anonymous_name = 'u_' . substr($anonymous_uuid, 0, 8);

		// Anonymize the member's posts.
		Db::$db->query(
			'UPDATE {db_prefix}messages
			SET poster_name = {string:anonymous_name},
				poster_email = {string:anonymous_email},
				id_member = {int:guest_id}
			WHERE id_member = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'anonymous_email' => $anonymous_uuid . '@email.invalid',
				'guest_id' => 0,
				'member' => $member,
			],
		);

		// Anonymize their name in mentions within posts.
		Db::$db->query(
			'UPDATE {db_prefix}messages
			SET body = REGEXP_REPLACE(body, {string:regex}, {string:anonymous_name})
			WHERE id_msg IN (
				SELECT DISTINCT content_id
				FROM {db_prefix}mentions
				WHERE content_type = {string:msg}
					AND id_mentioned = {int:member}
			)',
			[
				'regex' => '\\[member=' . $member . '\\][^\\[]+\\[/member\\]',
				'anonymous_name' => $anonymous_name,
				'member' => $member,
				'msg' => 'msg',
			],
		);

		// Anonymize their log comments, whether sent or received.
		Db::$db->query(
			'UPDATE {db_prefix}log_comments
			SET member_name = {string:anonymous_name},
				id_member = {int:guest_id}
			WHERE id_member = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		Db::$db->query(
			'UPDATE {db_prefix}log_comments
			SET recipient_name = {string:anonymous_name},
				id_recipient = {int:guest_id}
			WHERE id_recipient = {int:member}
				AND comment_type != {string:warntpl}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
				'warntpl' => 'warntpl',
			],
		);

		// Anonymize their logged group request actions.
		Db::$db->query(
			'UPDATE {db_prefix}log_group_requests
			SET member_name_acted = {string:anonymous_name},
				id_member_acted = {int:guest_id}
			WHERE id_member_acted = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		// Anonymize their logged package actions.
		Db::$db->query(
			'UPDATE {db_prefix}log_packages
			SET member_installed = {string:anonymous_name},
				id_member_installed = {int:guest_id}
			WHERE id_member_installed = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		Db::$db->query(
			'UPDATE {db_prefix}log_packages
			SET member_removed = {string:anonymous_name},
				id_member_removed = {int:guest_id}
			WHERE id_member_removed = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		// Anonymize reports about them.
		Db::$db->query(
			'UPDATE {db_prefix}log_reported
			SET membername = {string:anonymous_name},
				id_member = {int:guest_id}
			WHERE id_member = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		// Anonymize their comments on reports.
		Db::$db->query(
			'UPDATE {db_prefix}log_reported_comments
			SET membername = {string:anonymous_name},
				id_member = {int:guest_id}
			WHERE id_member = {int:member}',
			[
				'anonymous_name' => $anonymous_name,
				'guest_id' => 0,
				'member' => $member,
			],
		);

		// Anonymize their data in the edit history of posts.
		// We do this via a background task because it might take a while.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				[
					'SMF\\Tasks\\AnonymizeEditHistory',
					json_encode(['id' => $member]),
					0,
				],
			],
			[],
		);

		// Do any mods want to anonymize some custom content?
		IntegrationHook::call('integrate_anonymize', [$member]);
	}

	/**
	 * Ensures self::$column_types contains all necessary column types.
	 *
	 * This is necessary because mods might add columns to the members table.
	 *
	 * @param array $data Array of columns and values.
	 */
	protected static function setColumnTypes(array $data = []): void
	{
		// Filter out profile data elements whose column types we already know
		// or that we know do not come from the members table at all.
		$data = array_diff_key(
			$data,
			self::$column_types,
			[
				'id_attach' => null,
				'filename' => null,
				'attachment_type' => null,
				'attachment_width' => null,
				'attachment_height' => null,
				'is_online' => null,
				'member_group_color' => null,
				'member_group' => null,
				'post_group_color' => null,
				'post_group' => null,
				'icons' => null,
				'url' => null,
				'dataset' => null,
			],
		);

		if (empty($data)) {
			return;
		}

		self::$column_types = array_merge(
			self::$column_types,
			Db::$db->getTypeIndicators('{db_prefix}members', $data),
		);
	}

	/**
	 * Calls the deprecated integrate_user_info hook.
	 *
	 * MOD AUTHORS: Update your code to use the integrate_user_properties hook,
	 * which can be found in SMF\User::setProperties()
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateUserInfo(): void
	{
		if (!empty(Config::$backward_compatibility) && !empty(Config::$modSettings['integrate_user_info'])) {
			IntegrationHook::call('integrate_user_info');
		}
	}

	/**
	 * Calls the deprecated integrate_change_member_data hook.
	 *
	 * MOD AUTHORS: Update your code to use the integrate_save_member_data hook,
	 * which can be found in SMF\User::saveBatch()
	 *
	 * @deprecated 3.0
	 *
	 * @param array $members Instances of this class.
	 */
	protected static function integrateChangeMemberData(array $members): void
	{
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_change_member_data'])) {
			return;
		}

		$known_ints = [];
		$known_floats = [];

		foreach (self::$column_types as $col => $type) {
			switch ($type) {
				case 'int':
					$known_ints[] = $col;
					break;

				case 'float':
					$known_floats[] = $col;
					break;
			}
		}

		// For this hook, we need at least the minimal data for all affected members.
		$members = self::load(array_map(fn($member) => $member->id, $members), dataset: UserDataset::Minimal);

		foreach ($members as $member) {
			$integration_vars = [
				'avatar' => &$member->avatar['original_url'],
				'birthdate' => &$member->birthdate,
				'email_address' => &$member->email,
				'id_group' => &$member->group_id,
				'lngfile' => &$member->language,
				'member_name' => &$member->username,
				'real_name' => &$member->name,
				'time_format' => &$member->time_format,
				'timezone' => &$member->timezone,
				'website_title' => &$member->website['title'],
				'website_url' => &$member->website['url'],
			];

			foreach ($integration_vars as $var => $value) {
				IntegrationHook::call(
					'integrate_change_member_data',
					[
						[$member->username],
						$var,
						&$value,
						$known_ints,
						$known_floats,
					],
				);
			}
		}
	}
}

// Export properties to global namespace for backward compatibility.
if (\is_callable([User::class, 'exportStatic'])) {
	User::exportStatic();
}
