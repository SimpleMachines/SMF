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

use SMF\Actions\Moderation\ReportedContent;
use SMF\Actions\Notify;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

/**
 * Class for a single posted message.
 *
 * This class's static methods pertain to posting, and other such operations,
 * including sending emails, pms, blocking spam, preparsing posts, spell
 * checking, and the post box.
 */
class Msg implements \ArrayAccess
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
			'preparsecode' => 'preparsecode',
			'un_preparsecode' => 'un_preparsecode',
			'fixTags' => 'fixTags',
			'fixTag' => 'fixTag',
			'spellCheck' => 'SpellCheck',
			'create' => 'createPost',
			'modify' => 'modifyPost',
			'approve' => 'approvePosts',
			'clearApprovalAlerts' => 'clearApprovalAlerts',
			'updateLastMessages' => 'updateLastMessages',
			'remove' => 'removeMessage',
			'spell_init' => 'spell_init',
			'spell_check' => 'spell_check',
			'spell_suggest' => 'spell_suggest',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This message's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * ID number of the topic that this message is in.
	 */
	public int $id_topic;

	/**
	 * @var int
	 *
	 * ID number of the board that this message is in.
	 */
	public int $id_board;

	/**
	 * @var int
	 *
	 * Unix timestamp when this message was created.
	 */
	public int $poster_time;

	/**
	 * @var int
	 *
	 * Unix timestamp when this message was modified.
	 * Will be 0 if message has never been modifed.
	 */
	public int $modified_time = 0;

	/**
	 * @var int
	 *
	 * The ID number of whatever the latest message was when this message was
	 * last modified.
	 */
	public int $id_msg_modified = 0;

	/**
	 * @var int
	 *
	 * ID number of the author of this message.
	 * Will be 0 for messages authored by guests.
	 */
	public int $id_member = 0;

	/**
	 * @var string
	 *
	 * Name of the author of this message.
	 */
	public string $poster_name = '';

	/**
	 * @var string
	 *
	 * E-mail address of the author of this message.
	 */
	public string $poster_email = '';

	/**
	 * @var string
	 *
	 * IP address of the author of this message.
	 */
	public string $poster_ip = '';

	/**
	 * @var string
	 *
	 * Name of the member who last modified this message.
	 * Will be an empty string if the message has never been modified.
	 */
	public string $modified_name = '';

	/**
	 * @var string
	 *
	 * User-supplied explanation of why this message was modified.
	 */
	public string $modified_reason = '';

	/**
	 * @var string
	 *
	 * The subject line of this message.
	 */
	public string $subject = '';

	/**
	 * @var string
	 *
	 * The content of this message.
	 */
	public string $body = '';

	/**
	 * @var string
	 *
	 * The icon assigned to this message.
	 */
	public string $icon = '';

	/**
	 * @var bool
	 *
	 * Whether smileys should be parsed in this message.
	 */
	public bool $smileys_enabled = true;

	/**
	 * @var int
	 *
	 * The approval status of this message.
	 */
	public int $approved = 1;

	/**
	 * @var int
	 *
	 * The number of likes this message has received.
	 */
	public int $likes = 0;

	/**
	 * @var bool
	 *
	 * Whether the current user has read this message.
	 */
	public bool $is_read;

	/**
	 * @var array
	 *
	 * Formatted versions of this message's properties, suitable for display.
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
	 * @var object|array
	 *
	 * Variable to hold the Msg::get() generator.
	 * If there are no messages, will be an empty array.
	 */
	public static $getter;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_msg' => 'id',
		'timestamp' => 'poster_time',

		// Initial exclamation mark means inverse of the property.
		'new' => '!is_read',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * IDs of messages to load in Msg::get().
	 * Stored separately like this in order to allow resetting Msg::get().
	 */
	protected static array $messages_to_get;

	/**
	 * @var object
	 *
	 * Database query used in Msg::queryPMData().
	 */
	protected static $messages_request;

	/**
	 * @var bool
	 *
	 * If true, Msg::get() will not destroy instances after yielding them.
	 * This is used internally by Msg::load().
	 */
	protected static bool $keep_all = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the message.
	 * @param array $props Properties to set for this message.
	 */
	public function __construct(int $id, array $props = [])
	{
		$this->id = $id;
		$this->set($props);
		self::$loaded[$id] = $this;
	}

	/**
	 * Sets the formatted versions of message data for use in templates.
	 *
	 * @param int $counter The number of this message in a list of messages.
	 * @param array $format_options Options to control output.
	 * @return array A copy of $this->formatted.
	 */
	public function format(int $counter = 0, array $format_options = []): array
	{
		// These options are enabled by default.
		$format_options['do_permissions'] = $format_options['do_permissions'] ?? true;
		$format_options['do_icon'] = $format_options['do_icon'] ?? true;
		$format_options['load_author'] = $format_options['load_author'] ?? true;

		// These options are disabled by default.
		$format_options['load_board'] = !empty($format_options['load_board']);
		$format_options['make_preview'] = !empty($format_options['make_preview']);
		$format_options['shorten_subject'] = empty($format_options['shorten_subject']) ? 0 : (!is_numeric($format_options['shorten_subject']) ? 24 : (int) $format_options['shorten_subject']);
		$format_options['url_params'] = isset($format_options['url_params']) ? (array) $format_options['url_params'] : [];

		// Compose the memory eat- I mean message array.
		$this->formatted += [
			'attachment' => !empty(Utils::$context['loaded_attachments']) ? Attachment::loadAttachmentContext($this->id, Utils::$context['loaded_attachments']) : [],
			'id' => $this->id,
			'topic' => $this->id_topic,
			'board' => $format_options['load_board'] ? Board::init($this->id_board) : $this->id_board,
			'href' => Config::$scripturl . '?msg=' . $this->id . (!empty($format_options['url_params']) ? ';' . implode(';', $format_options['url_params']) : ''),
			'subject' => ($this->subject ?? '') != '' ? $this->subject : Lang::$txt['no_subject'],
			'time' => Time::create('@' . $this->poster_time)->format(),
			'timestamp' => $this->poster_time,
			'raw_timestamp' => $this->poster_time,
			'counter' => $counter,
			'modified' => [
				'time' => Time::create('@' . $this->modified_time)->format(),
				'timestamp' => $this->modified_time,
				'name' => $this->modified_name,
				'reason' => $this->modified_reason,
			],
			'body' => $this->body ?? '',
			'new' => empty($this->is_read),
			'first_new' => isset(Utils::$context['start_from']) && Utils::$context['start_from'] == $counter,
			'is_ignored' => !empty(Config::$modSettings['enable_buddylist']) && !empty(Theme::$current->options['posts_apply_ignore_list']) && in_array($this->id_member, User::$me->ignoreusers),
		];

		// Are we showing the icon?
		if (!empty($format_options['do_icon'])) {
			// Utils::$context['icon_sources'] says where each icon should come from - here we set up the ones which will always exist!
			if (empty(Utils::$context['icon_sources'])) {
				Utils::$context['icon_sources'] = [];

				foreach (Utils::$context['stable_icons'] as $icon) {
					Utils::$context['icon_sources'][$icon] = 'images_url';
				}
			}

			// Message Icon Management... check the images exist.
			if (!empty(Config::$modSettings['messageIconChecks_enable'])) {
				// If the current icon isn't known, then we need to do something...
				if (!isset(Utils::$context['icon_sources'][$this->icon])) {
					Utils::$context['icon_sources'][$this->icon] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $this->icon . '.png') ? 'images_url' : 'default_images_url';
				}
			} elseif (!isset(Utils::$context['icon_sources'][$this->icon])) {
				Utils::$context['icon_sources'][$this->icon] = 'images_url';
			}

			// Start by setting the icon to its normal value.
			$this->formatted['icon'] = $this->icon;

			// If it's in the recycle bin we need to override the icon.
			if (!empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) && Config::$modSettings['recycle_board'] == $this->id_board) {
				$this->formatted['icon'] = 'recycled';
			}

			// Does this message have any attachments? If so, change the icon.
			if (!empty($this->formatted['attachment'])) {
				$this->formatted['icon'] = 'clip';
			}

			// Set the full URL of the icon image.
			$this->formatted['icon_url'] = Theme::$current->settings[Utils::$context['icon_sources'][$this->formatted['icon']]] . '/post/' . $this->formatted['icon'] . '.png';
		}

		// What is the user allowed to do with this message?
		if (!empty($format_options['do_permissions'])) {
			$topic = Topic::load($this->id_topic);
			$topic->doPermissions();

			// Are you allowed to remove at least a single reply?
			$topic->permissions['can_remove_post'] |= User::$me->allowedTo('delete_own') && (empty(Config::$modSettings['edit_disable_time']) || $this->poster_time + Config::$modSettings['edit_disable_time'] * 60 >= time()) && $this->id_member == User::$me->id;

			// If the topic is locked, you might not be able to delete the post...
			if ($topic->is_locked) {
				$topic->permissions['can_remove_post'] &= (User::$me->started && $topic->is_locked == 1) || User::$me->allowedTo('lock_any');
			}

			$this->formatted += [
				'approved' => $this->approved,
				'can_approve' => !$this->approved && $topic->permissions['can_approve'],
				'can_unapprove' => !empty(Config::$modSettings['postmod_active']) && $topic->permissions['can_approve'] && $this->approved,
				'can_modify' => (!$topic->is_locked || User::$me->allowedTo('moderate_board')) && (User::$me->allowedTo('modify_any') || (User::$me->allowedTo('modify_replies') && User::$me->started) || (User::$me->allowedTo('modify_own') && $this->id_member == User::$me->id && (empty(Config::$modSettings['edit_disable_time']) || !$this->approved || $this->poster_time + Config::$modSettings['edit_disable_time'] * 60 > time()))),
				'can_remove' => User::$me->allowedTo('delete_any') || (User::$me->allowedTo('delete_replies') && User::$me->started) || (User::$me->allowedTo('delete_own') && $this->id_member == User::$me->id && (empty(Config::$modSettings['edit_disable_time']) || $this->poster_time + Config::$modSettings['edit_disable_time'] * 60 > time())),
				'can_see_ip' => User::$me->allowedTo('moderate_forum') || ($this->id_member == User::$me->id && !empty(User::$me->id)),
				'css_class' => $this->approved ? 'windowbg' : 'approvebg',
			];
		} else {
			$this->formatted['css_class'] = 'windowbg';
		}

		// Load the author's info from the database.
		if (!empty($format_options['load_author'])) {
			// Is this user the message author?
			$this->formatted['is_message_author'] = $this->id_member == User::$me->id && !User::$me->is_guest;

			// Load the author's data, if not already loaded.
			if (!empty($this->id_member) && !isset(User::$loaded[$this->id_member])) {
				User::load($this->id_member);
			}

			// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
			if (empty($this->id_member) || !isset(User::$loaded[$this->id_member])) {
				$this->formatted['member'] = [
					'name' => $this->poster_name,
					'username' => $this->poster_name,
					'id' => 0,
					'group' => Lang::$txt['guest_title'],
					'link' => $this->poster_name,
					'email' => $this->poster_email,
					'show_email' => User::$me->allowedTo('moderate_forum'),
					'is_guest' => true,
				];
			} else {
				$this->formatted['member'] = User::$loaded[$this->id_member]->format(true);

				// Define this here to make things a bit more readable
				$can_view_warning = User::$me->is_mod || User::$me->allowedTo('moderate_forum') || User::$me->allowedTo('view_warning_any') || ($this->id_member == User::$me->id && User::$me->allowedTo('view_warning_own'));

				$this->formatted['member']['can_view_profile'] = User::$me->allowedTo('profile_view') || ($this->id_member == User::$me->id && !User::$me->is_guest);

				if (isset(Utils::$context['topic_starter_id'])) {
					$this->formatted['member']['is_topic_starter'] = $this->id_member == Utils::$context['topic_starter_id'];
				}

				$this->formatted['member']['can_see_warning'] = !isset(Utils::$context['disabled_fields']['warning_status']) && $this->formatted['member']['warning_status'] && $can_view_warning;

				// Show the email if it's your post...
				$this->formatted['member']['show_email'] |= ($this->id_member == User::$me->id);
			}

			$this->formatted['member']['ip'] = new IP($this->poster_ip);
			$this->formatted['member']['show_profile_buttons'] = !empty(Config::$modSettings['show_profile_buttons']) && (!empty($this->formatted['member']['can_view_profile']) || (!empty($this->formatted['member']['website']['url']) && !isset(Utils::$context['disabled_fields']['website'])) || $this->formatted['member']['show_email'] || $topic->permissions['can_send_pm']);

			// Any custom profile fields?
			if (!empty($this->formatted['member']['custom_fields'])) {
				foreach ($this->formatted['member']['custom_fields'] as $custom) {
					$this->formatted['custom_fields'][Utils::$context['cust_profile_fields_placement'][$custom['placement']]][] = $custom;
				}
			}
		}
		// Didn't ask for the full author info, so do the bare minimum.
		else {
			$this->formatted['member'] = [
				'id' => $this->id_member,
				'name' => $this->poster_name,
				'username' => $this->poster_name,
				'href' => empty($this->id_member) ? '' : Config::$scripturl . '?action=profile;u=' . $this->id_member,
				'link' => empty($this->id_member) ? $this->poster_name : '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id_member . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->poster_name) . '">' . $this->poster_name . '</a>',
			];
		}

		// Make 'poster' an alias of 'member'.
		$this->formatted['poster'] = &$this->formatted['member'];

		// Do the censor thang.
		Lang::censorText($this->formatted['body']);
		Lang::censorText($this->formatted['subject']);

		// Run BBC interpreter on the message.
		$this->formatted['body'] = BBCodeParser::load()->parse($this->formatted['body'], $this->smileys_enabled, $this->id);

		$this->formatted['link'] = '<a href="' . $this->formatted['href'] . '" rel="nofollow">' . $this->formatted['subject'] . '</a>';

		// Make a preview?
		if (!empty($format_options['make_preview'])) {
			$this->formatted['preview'] = strip_tags(strtr($this->formatted['body'], ['<br>' => '&#10;']));

			if (Utils::entityStrlen($this->formatted['preview']) > 128) {
				$this->formatted['preview'] = Utils::entitySubstr($this->formatted['preview'], 0, 128) . (!empty(Utils::$context['utf8']) ? '…' : '...');
			}
		}

		// Make a short version of the subject?
		if (!empty($format_options['shorten_subject'])) {
			$this->formatted['short_subject'] = Utils::shorten($this->formatted['subject'], $format_options['shorten_subject']);
		}

		// Are likes enabled?
		if (!empty(Config::$modSettings['enable_likes'])) {
			$this->formatted['likes'] = [
				'count' => $this->likes,
				'you' => in_array($this->id, Utils::$context['my_likes'] ?? []),
			];

			if ($format_options['do_permissions']) {
				$this->formatted['likes']['can_like'] = !User::$me->is_guest && $this->id_member != User::$me->id && !empty($topic->permissions['can_like']);
			}
		}

		// Info about last modification to this message.
		if (!empty($this->formatted['modified']['name'])) {
			$this->formatted['modified']['last_edit_text'] = sprintf(Lang::$txt['last_edit_by'], $this->formatted['modified']['time'], $this->formatted['modified']['name']);

			// Did they give a reason for editing?
			if (!empty($this->formatted['modified']['reason'])) {
				$this->formatted['modified']['last_edit_text'] .= '&nbsp;' . sprintf(Lang::$txt['last_edit_reason'], $this->formatted['modified']['reason']);
			}
		}

		IntegrationHook::call('integrate_format_msg', [&$this->formatted, $this->id]);

		return $this->formatted;
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		if ($prop === 'poster_ip') {
			$value = new IP($value);
		}

		$this->customPropertySet($prop, $value);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads messages by ID number.
	 *
	 * Note: if you are loading a group of messages so that you can iterate over
	 * them, consider using Msg::get() rather than Msg::load().
	 *
	 * @param array $ids The ID numbers of one or more messages.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded messages.
	 */
	public static function load($ids, array $query_customizations = []): array
	{
		// Loading is similar to getting, except that we keep all instances and
		// then return them all at once.
		$loaded = [];

		self::$keep_all = true;

		foreach (self::get($ids, $query_customizations) as $msg) {
			$loaded[$msg->id] = $msg;
		}

		self::$keep_all = false;

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * Similar to Msg::load(), except that this method progressively creates and
	 * destroys instances of this class for each message, so that only one
	 * instance ever exists at a time.
	 *
	 * @param int|array $ids The ID numbers of the messages to load.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<array> Iterating over result gives Msg instances.
	 */
	public static function get($ids, array $query_customizations = [])
	{
		$selects = $query_customizations['selects'] ?? [
			'm.*',
			'm.id_msg_modified < {int:new_from} AS is_read',
		];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [
			'm.id_msg IN ({array_int:message_list})',
		];
		$order = $query_customizations['order'] ?? [
			'm.id_msg' . (empty(Theme::$current->options['view_newest_first']) ? '' : ' DESC'),
		];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [
			'new_from' => Topic::$info->new_from ?? Config::$modSettings['maxMsgID'] + 1,
		];

		if (!empty($ids)) {
			$params['message_list'] = self::$messages_to_get = array_filter(array_unique(array_map('intval', (array) $ids)));
		}

		// Just FYI, for historical reasons the order in which the arguments are
		// passed to this hook is different than the order in which they are
		// passed to the queryData() method.
		IntegrationHook::call('integrate_query_message', [&$selects, &$joins, &$params, &$where, &$order, &$group, &$limit]);

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$id = (int) $row['id_msg'];

			yield (new self($id, $row));

			if (!self::$keep_all) {
				unset(self::$loaded[$id]);
			}
		}

		// Reset this when done.
		self::$messages_to_get = [];
	}

	/**
	 * Takes a message and parses it, returning nothing.
	 * Cleans up links (javascript, etc.) and code/quote sections.
	 * Won't convert \n's and a few other things if previewing is true.
	 *
	 * @param string &$message The mesasge
	 * @param bool $previewing Whether we're previewing
	 */
	public static function preparsecode(&$message, $previewing = false): void
	{
		static $tags_regex, $disallowed_tags_regex;

		// Convert control characters (except \t, \r, and \n) to harmless Unicode symbols
		$control_replacements = [
			"\x00" => '&#x2400;', "\x01" => '&#x2401;', "\x02" => '&#x2402;', "\x03" => '&#x2403;',
			"\x04" => '&#x2404;', "\x05" => '&#x2405;', "\x06" => '&#x2406;', "\x07" => '&#x2407;',
			"\x08" => '&#x2408;', "\x0b" => '&#x240b;', "\x0c" => '&#x240c;', "\x0e" => '&#x240e;',
			"\x0f" => '&#x240f;', "\x10" => '&#x2410;', "\x11" => '&#x2411;', "\x12" => '&#x2412;',
			"\x13" => '&#x2413;', "\x14" => '&#x2414;', "\x15" => '&#x2415;', "\x16" => '&#x2416;',
			"\x17" => '&#x2417;', "\x18" => '&#x2418;', "\x19" => '&#x2419;', "\x1a" => '&#x241a;',
			"\x1b" => '&#x241b;', "\x1c" => '&#x241c;', "\x1d" => '&#x241d;', "\x1e" => '&#x241e;',
			"\x1f" => '&#x241f;',
		];
		$message = strtr($message, $control_replacements);

		// This line makes all languages *theoretically* work even with the wrong charset ;).
		if (empty(Utils::$context['utf8'])) {
			$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);
		}
		// Normalize Unicode characters for storage efficiency, better searching, etc.
		else {
			$message = Utils::normalize($message);
		}

		// Clean out any other funky stuff.
		$message = Utils::sanitizeChars($message, 0);

		// Clean up after nobbc ;).
		$message = preg_replace_callback(
			'~\[nobbc\](.+?)\[/nobbc\]~is',
			function ($a) {
				return '[nobbc]' . strtr($a[1], ['[' => '&#91;', ']' => '&#93;', ':' => '&#58;', '@' => '&#64;']) . '[/nobbc]';
			},
			$message,
		);

		// Remove \r's... they're evil!
		$message = strtr($message, ["\r\n" => "\n", "\r" => "\n"]);

		// You won't believe this - but too many periods upsets apache it seems!
		$message = preg_replace('~\.{100,}~', '...', $message);

		// Trim off trailing quotes - these often happen by accident.
		while (substr($message, -7) == '[quote]') {
			$message = substr($message, 0, -7);
		}

		while (substr($message, 0, 8) == '[/quote]') {
			$message = substr($message, 8);
		}

		if (strpos($message, '[cowsay') !== false && !User::$me->allowedTo('bbc_cowsay')) {
			$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);
		}

		// Find all code blocks, work out whether we'd be parsing them, then ensure they are all closed.
		$in_tag = false;
		$had_tag = false;
		$codeopen = 0;

		if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $message, $matches)) {
			foreach ($matches[0] as $index => $dummy) {
				// Closing?
				if (!empty($matches[2][$index])) {
					// If it's closing and we're not in a tag we need to open it...
					if (!$in_tag) {
						$codeopen = true;
					}

					// Either way we ain't in one any more.
					$in_tag = false;
				}
				// Opening tag...
				else {
					$had_tag = true;

					// If we're in a tag don't do nought!
					if (!$in_tag) {
						$in_tag = true;
					}
				}
			}
		}

		// If we have an open tag, close it.
		if ($in_tag) {
			$message .= '[/code]';
		}

		// Open any ones that need to be open, only if we've never had a tag.
		if ($codeopen && !$had_tag) {
			$message = '[code]' . $message;
		}

		// Replace code BBC with placeholders. We'll restore them at the end.
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $n = count($parts); $i < $n; $i++) {
			// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
			if ($i % 4 == 2) {
				$code_tag = $parts[$i - 1] . $parts[$i] . $parts[$i + 1];
				$substitute = $parts[$i - 1] . $i . $parts[$i + 1];
				$code_tags[$substitute] = $code_tag;
				$parts[$i] = $i;
			}
		}

		$message = implode('', $parts);

		// The regular expression non breaking space has many versions.
		$non_breaking_space = Utils::$context['utf8'] ? '\x{A0}' : '\xA0';

		// Now that we've fixed all the code tags, let's fix the img and url tags...
		self::fixTags($message);

		// Replace /me.+?\n with [me=name]dsf[/me]\n.
		if (strpos(User::$me->name, '[') !== false || strpos(User::$me->name, ']') !== false || strpos(User::$me->name, '\'') !== false || strpos(User::$me->name, '"') !== false) {
			$message = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=&quot;' . User::$me->name . '&quot;]$2[/me]', $message);
		} else {
			$message = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . User::$me->name . ']$2[/me]', $message);
		}

		if (!$previewing && strpos($message, '[html]') !== false) {
			if (User::$me->allowedTo('bbc_html')) {
				$message = preg_replace_callback(
					'~\[html\](.+?)\[/html\]~is',
					function ($m) {
						return '[html]' . strtr(Utils::htmlspecialcharsDecode($m[1]), ["\n" => '&#13;', '  ' => ' &#32;', '[' => '&#91;', ']' => '&#93;']) . '[/html]';
					},
					$message,
				);
			}
			// We should edit them out, or else if an admin edits the message they will get shown...
			else {
				while (strpos($message, '[html]') !== false) {
					$message = preg_replace('~\[[/]?html\]~i', '', $message);
				}
			}
		}

		// Let's look at the time tags...
		$message = preg_replace_callback(
			'~\[time(?:=(absolute))*\](.+?)\[/time\]~i',
			function ($m) {
				return '[time]' . (is_numeric("{$m[2]}") || @strtotime("{$m[2]}") == 0 ? "{$m[2]}" : strtotime("{$m[2]}") - ("{$m[1]}" == 'absolute' ? 0 : ((Config::$modSettings['time_offset'] + User::$me->time_offset) * 3600))) . '[/time]';
			},
			$message,
		);

		// Change the color specific tags to [color=the color].
		// First do the opening tags.
		$message = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $message);

		// And now do the closing tags
		$message = preg_replace('~\[/(black|blue|green|red|white)\]~', '[/color]', $message);

		// Neutralize any BBC tags this member isn't permitted to use.
		if (empty($disallowed_tags_regex)) {
			// Legacy BBC are only retained for historical reasons.
			// They're not for use in new posts.
			$disallowed_bbc = Utils::$context['legacy_bbc'];

			// Some BBC require permissions.
			foreach (Utils::$context['restricted_bbc'] as $bbc) {
				// Skip html, since we handled it separately above.
				if ($bbc === 'html') {
					continue;
				}

				if (!User::$me->allowedTo('bbc_' . $bbc)) {
					$disallowed_bbc[] = $bbc;
				}
			}

			$disallowed_tags_regex = Utils::buildRegex(array_unique($disallowed_bbc), '~');
		}

		if (!empty($disallowed_tags_regex)) {
			$message = preg_replace('~\[(?=/?' . $disallowed_tags_regex . '\b)~i', '&#91;', $message);
		}

		// Make sure all tags are lowercase.
		$message = preg_replace_callback(
			'~\[(/?)(list|li|table|tr|td)\b([^\]]*)\]~i',
			function ($m) {
				return "[{$m[1]}" . strtolower("{$m[2]}") . "{$m[3]}]";
			},
			$message,
		);

		$list_open = substr_count($message, '[list]') + substr_count($message, '[list ');
		$list_close = substr_count($message, '[/list]');

		if ($list_close - $list_open > 0) {
			$message = str_repeat('[list]', $list_close - $list_open) . $message;
		}

		if ($list_open - $list_close > 0) {
			$message = $message . str_repeat('[/list]', $list_open - $list_close);
		}

		$mistake_fixes = [
			// Find [table]s not followed by [tr].
			'~\[table\](?![\s' . $non_breaking_space . ']*\[tr\])~s' . (Utils::$context['utf8'] ? 'u' : '') => '[table][tr]',
			// Find [tr]s not followed by [td].
			'~\[tr\](?![\s' . $non_breaking_space . ']*\[td\])~s' . (Utils::$context['utf8'] ? 'u' : '') => '[tr][td]',
			// Find [/td]s not followed by something valid.
			'~\[/td\](?![\s' . $non_breaking_space . ']*(?:\[td\]|\[/tr\]|\[/table\]))~s' . (Utils::$context['utf8'] ? 'u' : '') => '[/td][/tr]',
			// Find [/tr]s not followed by something valid.
			'~\[/tr\](?![\s' . $non_breaking_space . ']*(?:\[tr\]|\[/table\]))~s' . (Utils::$context['utf8'] ? 'u' : '') => '[/tr][/table]',
			// Find [/td]s incorrectly followed by [/table].
			'~\[/td\][\s' . $non_breaking_space . ']*\[/table\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[/td][/tr][/table]',
			// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
			'~\[(table|tr|/td)\]([\s' . $non_breaking_space . ']*)\[td\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[$1]$2[_td_]',
			// Now, any [td]s left should have a [tr] before them.
			'~\[td\]~s' => '[tr][td]',
			// Look for [tr]s which are correctly placed.
			'~\[(table|/tr)\]([\s' . $non_breaking_space . ']*)\[tr\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[$1]$2[_tr_]',
			// Any remaining [tr]s should have a [table] before them.
			'~\[tr\]~s' => '[table][tr]',
			// Look for [/td]s followed by [/tr].
			'~\[/td\]([\s' . $non_breaking_space . ']*)\[/tr\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[/td]$1[_/tr_]',
			// Any remaining [/tr]s should have a [/td].
			'~\[/tr\]~s' => '[/td][/tr]',
			// Look for properly opened [li]s which aren't closed.
			'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
			'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
			'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
			// Lists - find correctly closed items/lists.
			'~\[/li\]([\s' . $non_breaking_space . ']*)\[/list\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[_/li_]$1[/list]',
			// Find list items closed and then opened.
			'~\[/li\]([\s' . $non_breaking_space . ']*)\[li\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[_/li_]$1[_li_]',
			// Now, find any [list]s or [/li]s followed by [li].
			'~\[(list(?: [^\]]*?)?|/li)\]([\s' . $non_breaking_space . ']*)\[li\]~s' . (Utils::$context['utf8'] ? 'u' : '') => '[$1]$2[_li_]',
			// Allow for sub lists.
			'~\[/li\]([\s' . $non_breaking_space . ']*)\[list\]~' . (Utils::$context['utf8'] ? 'u' : '') => '[_/li_]$1[list]',
			'~\[/list\]([\s' . $non_breaking_space . ']*)\[li\]~' . (Utils::$context['utf8'] ? 'u' : '') => '[/list]$1[_li_]',
			// Any remaining [li]s weren't inside a [list].
			'~\[li\]~' => '[list][li]',
			// Any remaining [/li]s weren't before a [/list].
			'~\[/li\]~' => '[/li][/list]',
			// Put the correct ones back how we found them.
			'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
			// Images with no real url.
			'~\[img\]https?://.{0,7}\[/img\]~' => '',
		];

		// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
		for ($j = 0; $j < 3; $j++) {
			$message = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $message);
		}

		// Remove empty bbc from the sections outside the code tags
		if (empty($tags_regex)) {
			$allowed_empty = ['anchor', 'td'];

			$tags = [];

			foreach (BBCodeParser::getCodes() as $code) {
				if (!in_array($code['tag'], $allowed_empty)) {
					$tags[] = $code['tag'];
				}
			}

			$tags_regex = Utils::buildRegex($tags, '~');
		}

		while (preg_match('~\[(' . $tags_regex . ')\b[^\]]*\]\s*\[/\1\]\s?~i', $message)) {
			$message = preg_replace('~\[(' . $tags_regex . ')[^\]]*\]\s*\[/\1\]\s?~i', '', $message);
		}

		// Restore code blocks
		if (!empty($code_tags)) {
			$message = str_replace(array_keys($code_tags), array_values($code_tags), $message);
		}

		// Restore white space entities
		if (!$previewing) {
			$message = strtr($message, ['  ' => '&nbsp; ', "\n" => '<br>', Utils::$context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;']);
		} else {
			$message = strtr($message, ['  ' => '&nbsp; ', Utils::$context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;']);
		}

		// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
		$message = strtr($message, ['[]' => '&#91;]', '[&#039;' => '&#91;&#039;']);

		// Any hooks want to work here?
		IntegrationHook::call('integrate_preparsecode', [&$message, $previewing]);
	}

	/**
	 * This is very simple, and just removes things done by preparsecode.
	 *
	 * @param string $message The message
	 * @return string The message with preparsecode changes reverted.
	 */
	public static function un_preparsecode($message): string
	{
		// Any hooks want to work here?
		IntegrationHook::call('integrate_unpreparsecode', [&$message]);

		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		// We're going to unparse only the stuff outside [code]...
		for ($i = 0, $n = count($parts); $i < $n; $i++) {
			// If $i is a multiple of four (0, 4, 8, ...) then it's not a code section...
			if ($i % 4 == 2) {
				$code_tag = $parts[$i - 1] . $parts[$i] . $parts[$i + 1];
				$substitute = $parts[$i - 1] . $i . $parts[$i + 1];
				$code_tags[$substitute] = $code_tag;
				$parts[$i] = $i;
			}
		}

		$message = implode('', $parts);

		$message = preg_replace_callback(
			'~\[html\](.+?)\[/html\]~i',
			function ($matches) {
				return '[html]' . strtr(Utils::htmlspecialchars($matches[1], ENT_QUOTES), ['\\&quot;' => '&quot;', '&amp;#13;' => '<br>', '&amp;#32;' => ' ', '&amp;#91;' => '[', '&amp;#93;' => ']']) . '[/html]';
			},
			$message,
		);

		if (strpos($message, '[cowsay') !== false && !User::$me->allowedTo('bbc_cowsay')) {
			$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);
		}

		// Attempt to un-parse the time to something less awful.
		$message = preg_replace_callback(
			'~\[time\](\d{0,10})\[/time\]~i',
			function ($matches) {
				return '[time]' . Time::create('@' . $matches[1])->setTimezone(new \DateTimeZone(User::getTimezone()))->format(null, false) . '[/time]';
			},
			$message,
		);

		if (!empty($code_tags)) {
			$message = strtr($message, $code_tags);
		}

		// Change breaks back to \n's and &nsbp; back to spaces.
		return preg_replace('~<br\s*/?' . '>~', "\n", str_replace('&nbsp;', ' ', $message));
	}

	/**
	 * Fix any URLs posted - ie. remove 'javascript:'.
	 * Used by preparsecode, fixes links in message and returns nothing.
	 *
	 * @param string &$message The message
	 */
	public static function fixTags(&$message): void
	{
		// WARNING: Editing the below can cause large security holes in your forum.
		// Edit only if you are sure you know what you are doing.

		$fixArray = [
			// [img]http://...[/img] or [img width=1]http://...[/img]
			[
				'tag' => 'img',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => false,
				'hasEqualSign' => false,
				'hasExtra' => true,
			],
			// [url]http://...[/url]
			[
				'tag' => 'url',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => false,
				'hasEqualSign' => false,
			],
			// [url=http://...]name[/url]
			[
				'tag' => 'url',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			],
			// [iurl]http://...[/iurl]
			[
				'tag' => 'iurl',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => false,
				'hasEqualSign' => false,
			],
			// [iurl=http://...]name[/iurl]
			[
				'tag' => 'iurl',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			],
			// The rest of these are deprecated.
			// [ftp]ftp://...[/ftp]
			[
				'tag' => 'ftp',
				'protocols' => ['ftp', 'ftps', 'sftp'],
				'embeddedUrl' => false,
				'hasEqualSign' => false,
			],
			// [ftp=ftp://...]name[/ftp]
			[
				'tag' => 'ftp',
				'protocols' => ['ftp', 'ftps', 'sftp'],
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			],
			// [flash]http://...[/flash]
			[
				'tag' => 'flash',
				'protocols' => ['http', 'https'],
				'embeddedUrl' => false,
				'hasEqualSign' => false,
				'hasExtra' => true,
			],
		];

		// Fix each type of tag.
		foreach ($fixArray as $param) {
			self::fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));
		}

		// Now fix possible security problems with images loading links automatically...
		$message = preg_replace_callback(
			'~(\[img.*?\])(.+?)\[/img\]~is',
			function ($m) {
				return "{$m[1]}" . preg_replace('~action(=|%3d)(?!dlattach)~i', 'action-', "{$m[2]}") . '[/img]';
			},
			$message,
		);
	}

	/**
	 * Fix a specific class of tag - ie. url with =.
	 * Used by fixTags, fixes a specific tag's links.
	 *
	 * @param string &$message The message
	 * @param string $myTag The tag
	 * @param array $protocols The protocols
	 * @param bool $embeddedUrl Whether it *can* be set to something
	 * @param bool $hasEqualSign Whether it *is* set to something
	 * @param bool $hasExtra Whether it can have extra cruft after the begin tag.
	 */
	public static function fixTag(&$message, $myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false): void
	{
		$forbidden_protocols = [
			// Poses security risks.
			'javascript',
			// Allows file data to be embedded, bypassing our attachment system.
			'data',
		];

		if (preg_match('~^([^:]+://[^/]+)~', Config::$boardurl, $match) != 0) {
			$domain_url = $match[1];
		} else {
			$domain_url = Config::$boardurl . '/';
		}

		$replaces = [];

		if ($hasEqualSign && $embeddedUrl) {
			$quoted = preg_match('~\[(' . $myTag . ')=&quot;~', $message);

			preg_match_all('~\[(' . $myTag . ')=' . ($quoted ? '&quot;(.*?)&quot;' : '([^\]]*?)') . '\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
		} elseif ($hasEqualSign) {
			preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
		} else {
			preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);
		}

		foreach ($matches[0] as $k => $dummy) {
			// Remove all leading and trailing whitespace.
			$replace = trim($matches[2][$k]);
			$this_tag = $matches[1][$k];
			$this_close = $hasEqualSign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

			$found = false;

			foreach ($protocols as $protocol) {
				$found = strncasecmp($replace, $protocol . '://', strlen($protocol) + 3) === 0;

				if ($found) {
					break;
				}
			}

			$current_protocol = strtolower(Url::create($replace)->scheme ?? '');

			if (in_array($current_protocol, $forbidden_protocols)) {
				$replace = 'about:invalid';
			} elseif (!$found && $protocols[0] == 'http') {
				// A path
				if (substr($replace, 0, 1) == '/' && substr($replace, 0, 2) != '//') {
					$replace = $domain_url . $replace;
				}
				// A query
				elseif (substr($replace, 0, 1) == '?') {
					$replace = Config::$scripturl . $replace;
				}
				// A fragment
				elseif (substr($replace, 0, 1) == '#' && $embeddedUrl) {
					$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
					$this_tag = 'iurl';
					$this_close = 'iurl';
				} elseif (substr($replace, 0, 2) != '//' && empty($current_protocol)) {
					$replace = $protocols[0] . '://' . $replace;
				}
			} elseif (!$found && $protocols[0] == 'ftp') {
				$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
			} elseif (!$found && empty($current_protocol)) {
				$replace = $protocols[0] . '://' . $replace;
			}

			if ($hasEqualSign && $embeddedUrl) {
				$replaces[$matches[0][$k]] = '[' . $this_tag . '=&quot;' . $replace . '&quot;]' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
			} elseif ($hasEqualSign) {
				$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
			} elseif ($embeddedUrl) {
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
			} else {
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
			}
		}

		foreach ($replaces as $k => $v) {
			if ($k == $v) {
				unset($replaces[$k]);
			}
		}

		if (!empty($replaces)) {
			$message = strtr($message, $replaces);
		}
	}

	/**
	 * Spell checks the post for typos ;).
	 * It uses the pspell or enchant library, one of which MUST be installed.
	 * It has problems with internationalization.
	 * It is accessed via ?action=spellcheck.
	 */
	public static function spellCheck(): void
	{
		// A list of "words" we know about but pspell doesn't.
		$known_words = ['smf', 'php', 'mysql', 'www', 'gif', 'jpeg', 'png', 'http', 'smfisawesome', 'grandia', 'terranigma', 'rpgs'];

		Lang::load('Post');
		Theme::loadTemplate('Post');

		// Create a pspell or enchant dictionary resource
		$dict = self::spell_init();

		if (!isset($_POST['spellstring']) || !$dict) {
			die;
		}

		// Construct a bit of Javascript code.
		Utils::$context['spell_js'] = '
			var txt = {"done": "' . Lang::$txt['spellcheck_done'] . '"};
			var mispstr = window.opener.spellCheckGetText(spell_fieldname);
			var misps = Array(';

		// Get all the words (Javascript already separated them).
		$alphas = explode("\n", strtr($_POST['spellstring'], ["\r" => '']));

		$found_words = false;

		for ($i = 0, $n = count($alphas); $i < $n; $i++) {
			// Words are sent like 'word|offset_begin|offset_end'.
			$check_word = explode('|', $alphas[$i]);

			// If the word is a known word, or spelled right...
			if (in_array(Utils::strtolower($check_word[0]), $known_words) || self::spell_check($dict, $check_word[0]) || !isset($check_word[2])) {
				continue;
			}

			// Find the word, and move up the "last occurrence" to here.
			$found_words = true;

			// Add on the javascript for this misspelling.
			Utils::$context['spell_js'] .= '
				new misp("' . strtr($check_word[0], ['\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '']) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

			// If there are suggestions, add them in...
			$suggestions = self::spell_suggest($dict, $check_word[0]);

			if (!empty($suggestions)) {
				// But first check they aren't going to be censored - no naughty words!
				foreach ($suggestions as $k => $word) {
					if ($suggestions[$k] != Lang::censorText($word)) {
						unset($suggestions[$k]);
					}
				}

				if (!empty($suggestions)) {
					Utils::$context['spell_js'] .= '"' . implode('", "', $suggestions) . '"';
				}
			}

			Utils::$context['spell_js'] .= ']),';
		}

		// If words were found, take off the last comma.
		if ($found_words) {
			Utils::$context['spell_js'] = substr(Utils::$context['spell_js'], 0, -1);
		}

		Utils::$context['spell_js'] .= '
			);';

		// And instruct the template system to just show the spellcheck sub template.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'spellcheck';

		// Free resources for enchant...
		if (isset(Utils::$context['enchant_broker'])) {
			unset($dict, Utils::$context['enchant_broker']);
		}
	}

	/**
	 * Create a post, either as new topic (id_topic = 0) or in an existing one.
	 * The input parameters of this function assume:
	 * - Strings have been escaped.
	 * - Integers have been cast to integer.
	 * - Mandatory parameters are set.
	 *
	 * @param array $msgOptions An array of information/options for the post
	 * @param array $topicOptions An array of information/options for the topic
	 * @param array $posterOptions An array of information/options for the poster
	 * @return bool Whether the operation was a success
	 */
	public static function create(&$msgOptions, &$topicOptions, &$posterOptions): bool
	{
		// Set optional parameters to the default value.
		$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
		$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
		$msgOptions['attachments'] = empty($msgOptions['attachments']) ? [] : $msgOptions['attachments'];
		$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
		$msgOptions['poster_time'] = isset($msgOptions['poster_time']) ? (int) $msgOptions['poster_time'] : time();
		$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
		$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
		$topicOptions['lock_mode'] = $topicOptions['lock_mode'] ?? null;
		$topicOptions['sticky_mode'] = $topicOptions['sticky_mode'] ?? null;
		$topicOptions['redirect_expires'] = $topicOptions['redirect_expires'] ?? null;
		$topicOptions['redirect_topic'] = $topicOptions['redirect_topic'] ?? null;
		$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
		$posterOptions['ip'] = empty($posterOptions['ip']) ? User::$me->ip : $posterOptions['ip'];

		// Not exactly a post option but it allows hooks and/or other sources to skip sending notifications if they don't want to
		$msgOptions['send_notifications'] = isset($msgOptions['send_notifications']) ? (bool) $msgOptions['send_notifications'] : true;

		// We need to know if the topic is approved. If we're told that's great - if not find out.
		if (!Config::$modSettings['postmod_active']) {
			$topicOptions['is_approved'] = true;
		} elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved'])) {
			$request = Db::$db->query(
				'',
				'SELECT approved
				FROM {db_prefix}topics
				WHERE id_topic = {int:id_topic}
				LIMIT 1',
				[
					'id_topic' => $topicOptions['id'],
				],
			);
			list($topicOptions['is_approved']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// If nothing was filled in as name/e-mail address, try the member table.
		if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id']))) {
			if (empty($posterOptions['id'])) {
				$posterOptions['id'] = 0;
				$posterOptions['name'] = Lang::$txt['guest_title'];
				$posterOptions['email'] = '';
			} elseif ($posterOptions['id'] != User::$me->id) {
				$request = Db::$db->query(
					'',
					'SELECT member_name, email_address
					FROM {db_prefix}members
					WHERE id_member = {int:id_member}
					LIMIT 1',
					[
						'id_member' => $posterOptions['id'],
					],
				);

				// Couldn't find the current poster?
				if (Db::$db->num_rows($request) == 0) {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['create_post_invalid_member_id'], $posterOptions['id']), E_USER_NOTICE);
					$posterOptions['id'] = 0;
					$posterOptions['name'] = Lang::$txt['guest_title'];
					$posterOptions['email'] = '';
				} else {
					list($posterOptions['name'], $posterOptions['email']) = Db::$db->fetch_row($request);
				}
				Db::$db->free_result($request);
			} else {
				$posterOptions['name'] = User::$me->name;
				$posterOptions['email'] = User::$me->email;
			}
		}

		// Get any members who were quoted in this post.
		$msgOptions['quoted_members'] = Mentions::getQuotedMembers($msgOptions['body'], $posterOptions['id']);

		if (!empty(Config::$modSettings['enable_mentions'])) {
			// Get any members who were possibly mentioned
			$msgOptions['mentioned_members'] = Mentions::getMentionedMembers($msgOptions['body']);

			if (!empty($msgOptions['mentioned_members'])) {
				// Replace @name with [member=id]name[/member]
				$msgOptions['body'] = Mentions::getBody($msgOptions['body'], $msgOptions['mentioned_members']);

				// Remove any members who weren't actually mentioned, to prevent bogus notifications
				$msgOptions['mentioned_members'] = Mentions::verifyMentionedMembers($msgOptions['body'], $msgOptions['mentioned_members']);
			}
		}

		// It's do or die time: forget any user aborts!
		$previous_ignore_user_abort = ignore_user_abort(true);

		$new_topic = empty($topicOptions['id']);

		$message_columns = [
			'id_board' => 'int', 'id_topic' => 'int', 'id_member' => 'int', 'subject' => 'string-255', 'body' => (!empty(Config::$modSettings['max_messageLength']) && Config::$modSettings['max_messageLength'] > 65534 ? 'string-' . Config::$modSettings['max_messageLength'] : (empty(Config::$modSettings['max_messageLength']) ? 'string' : 'string-65534')),
			'poster_name' => 'string-255', 'poster_email' => 'string-255', 'poster_time' => 'int', 'poster_ip' => 'inet',
			'smileys_enabled' => 'int', 'modified_name' => 'string', 'icon' => 'string-16', 'approved' => 'int',
		];

		$message_parameters = [
			$topicOptions['board'], $topicOptions['id'], $posterOptions['id'], $msgOptions['subject'], $msgOptions['body'],
			$posterOptions['name'], $posterOptions['email'], $msgOptions['poster_time'], $posterOptions['ip'],
			$msgOptions['smileys_enabled'] ? 1 : 0, '', $msgOptions['icon'], $msgOptions['approved'],
		];

		// What if we want to do anything with posts?
		IntegrationHook::call('integrate_create_post', [&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters]);

		// Insert the post.
		$msgOptions['id'] = Db::$db->insert(
			'',
			'{db_prefix}messages',
			$message_columns,
			$message_parameters,
			['id_msg'],
			1,
		);

		// Something went wrong creating the message...
		if (empty($msgOptions['id'])) {
			return false;
		}

		// Fix the attachments.
		if (!empty($msgOptions['attachments'])) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}attachments
				SET id_msg = {int:id_msg}
				WHERE id_attach IN ({array_int:attachment_list})',
				[
					'attachment_list' => $msgOptions['attachments'],
					'id_msg' => $msgOptions['id'],
				],
			);
		}

		// What if we want to export new posts out to a CMS?
		IntegrationHook::call('integrate_after_create_post', [$msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters]);

		// Insert a new topic (if the topicID was left empty.)
		if ($new_topic) {
			$topic_columns = [
				'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
				'id_last_msg' => 'int', 'locked' => 'int', 'is_sticky' => 'int', 'num_views' => 'int',
				'id_poll' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int',
				'redirect_expires' => 'int', 'id_redirect_topic' => 'int',
			];

			$topic_parameters = [
				$topicOptions['board'], $posterOptions['id'], $posterOptions['id'], $msgOptions['id'],
				$msgOptions['id'], $topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'], $topicOptions['sticky_mode'] === null ? 0 : $topicOptions['sticky_mode'], 0,
				$topicOptions['poll'] === null ? 0 : $topicOptions['poll'], $msgOptions['approved'] ? 0 : 1, $msgOptions['approved'],
				$topicOptions['redirect_expires'] === null ? 0 : $topicOptions['redirect_expires'], $topicOptions['redirect_topic'] === null ? 0 : $topicOptions['redirect_topic'],
			];

			IntegrationHook::call('integrate_before_create_topic', [&$msgOptions, &$topicOptions, &$posterOptions, &$topic_columns, &$topic_parameters]);

			$topicOptions['id'] = Db::$db->insert(
				'',
				'{db_prefix}topics',
				$topic_columns,
				$topic_parameters,
				['id_topic'],
				1,
			);

			// The topic couldn't be created for some reason.
			if (empty($topicOptions['id'])) {
				// We should delete the post that did work, though...
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}',
					[
						'id_msg' => $msgOptions['id'],
					],
				);

				return false;
			}

			// Fix the message with the topic.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET id_topic = {int:id_topic}
				WHERE id_msg = {int:id_msg}',
				[
					'id_topic' => $topicOptions['id'],
					'id_msg' => $msgOptions['id'],
				],
			);

			// There's been a new topic AND a new post today.
			Logging::trackStats(['topics' => '+', 'posts' => '+']);

			Logging::updateStats('topic', true);
			Logging::updateStats('subject', $topicOptions['id'], $msgOptions['subject']);

			// What if we want to export new topics out to a CMS?
			IntegrationHook::call('integrate_create_topic', [&$msgOptions, &$topicOptions, &$posterOptions]);
		}
		// The topic already exists, it only needs a little updating.
		else {
			$update_parameters = [
				'poster_id' => $posterOptions['id'],
				'id_msg' => $msgOptions['id'],
				'locked' => $topicOptions['lock_mode'],
				'is_sticky' => $topicOptions['sticky_mode'],
				'id_topic' => $topicOptions['id'],
				'counter_increment' => 1,
			];

			if ($msgOptions['approved']) {
				$topics_columns = [
					'id_member_updated = {int:poster_id}',
					'id_last_msg = {int:id_msg}',
					'num_replies = num_replies + {int:counter_increment}',
				];
			} else {
				$topics_columns = [
					'unapproved_posts = unapproved_posts + {int:counter_increment}',
				];
			}

			if ($topicOptions['lock_mode'] !== null) {
				$topics_columns[] = 'locked = {int:locked}';
			}

			if ($topicOptions['sticky_mode'] !== null) {
				$topics_columns[] = 'is_sticky = {int:is_sticky}';
			}

			IntegrationHook::call('integrate_modify_topic', [&$topics_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions]);

			// Update the number of replies and the lock/sticky status.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET
					' . implode(', ', $topics_columns) . '
				WHERE id_topic = {int:id_topic}',
				$update_parameters,
			);

			// One new post has been added today.
			Logging::trackStats(['posts' => '+']);
		}

		// Creating is modifying...in a way.
		// @todo Why not set id_msg_modified on the insert?
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_msg_modified = {int:id_msg}
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => $msgOptions['id'],
			],
		);

		// Increase the number of posts and topics on the board.
		if ($msgOptions['approved']) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
				WHERE id_board = {int:id_board}',
				[
					'id_board' => $topicOptions['board'],
				],
			);
		} else {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
				WHERE id_board = {int:id_board}',
				[
					'id_board' => $topicOptions['board'],
				],
			);

			// Add to the approval queue too.
			Db::$db->insert(
				'',
				'{db_prefix}approval_queue',
				[
					'id_msg' => 'int',
				],
				[
					$msgOptions['id'],
				],
				[],
			);

			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int'],
				[
					'SMF\\Tasks\\ApprovePost_Notify',
					Utils::jsonEncode([
						'msgOptions' => $msgOptions,
						'topicOptions' => $topicOptions,
						'posterOptions' => $posterOptions,
						'type' => $new_topic ? 'topic' : 'post',
					]),
					0,
				],
				['id_task'],
			);
		}

		// Mark inserted topic as read (only for the user calling this function).
		if (!empty($topicOptions['mark_as_read']) && !User::$me->is_guest) {
			// Since it's likely they *read* it before replying, let's try an UPDATE first.
			if (!$new_topic) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_topics
					SET id_msg = {int:id_msg}
					WHERE id_member = {int:current_member}
						AND id_topic = {int:id_topic}',
					[
						'current_member' => $posterOptions['id'],
						'id_msg' => $msgOptions['id'],
						'id_topic' => $topicOptions['id'],
					],
				);

				$flag = Db::$db->affected_rows() != 0;
			}

			if (empty($flag)) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}log_topics',
					['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'],
					[$topicOptions['id'], $posterOptions['id'], $msgOptions['id']],
					['id_topic', 'id_member'],
				);
			}
		}

		if ($msgOptions['approved'] && empty($topicOptions['is_approved']) && $posterOptions['id'] != User::$me->id) {
			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\ApproveReply_Notify',
					Utils::jsonEncode([
						'msgOptions' => $msgOptions,
						'topicOptions' => $topicOptions,
						'posterOptions' => $posterOptions,
					]),
					0,
				],
				['id_task'],
			);
		}

		// If there's a custom search index, it may need updating...
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'postCreated'])) {
			$searchAPI->postCreated($msgOptions, $topicOptions, $posterOptions);
		}

		// Increase the post counter for the user that created the post.
		if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved']) {
			// Are you the one that happened to create this post?
			if (User::$me->id == $posterOptions['id']) {
				User::$me->posts++;
			}

			User::updateMemberData($posterOptions['id'], ['posts' => '+']);
		}

		// They've posted, so they can make the view count go up one if they really want. (this is to keep views >= replies...)
		$_SESSION['last_read_topic'] = 0;

		// Better safe than sorry.
		if (isset($_SESSION['topicseen_cache'][$topicOptions['board']])) {
			$_SESSION['topicseen_cache'][$topicOptions['board']]--;
		}

		// Keep track of quotes and mentions.
		if (!empty($msgOptions['quoted_members'])) {
			Mentions::insertMentions('quote', $msgOptions['id'], $msgOptions['quoted_members'], $posterOptions['id']);
		}

		if (!empty($msgOptions['mentioned_members'])) {
			Mentions::insertMentions('msg', $msgOptions['id'], $msgOptions['mentioned_members'], $posterOptions['id']);
		}

		// Update all the stats so everyone knows about this new topic and message.
		Logging::updateStats('message', true, $msgOptions['id']);

		// Update the last message on the board assuming it's approved AND the topic is.
		if ($msgOptions['approved']) {
			self::updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);
		}

		// Queue createPost background notification
		if ($msgOptions['send_notifications'] && $msgOptions['approved']) {
			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\CreatePost_Notify',
					Utils::jsonEncode([
						'msgOptions' => $msgOptions,
						'topicOptions' => $topicOptions,
						'posterOptions' => $posterOptions,
						'type' => $new_topic ? 'topic' : 'reply',
					]),
					0,
				],
				['id_task'],
			);
		}

		// Alright, done now... we can abort now, I guess... at least this much is done.
		ignore_user_abort($previous_ignore_user_abort);

		// Success.
		return true;
	}

	/**
	 * Modifying a post...
	 *
	 * @param array &$msgOptions An array of information/options for the post
	 * @param array &$topicOptions An array of information/options for the topic
	 * @param array &$posterOptions An array of information/options for the poster
	 * @return bool Whether the post was modified successfully
	 */
	public static function modify(&$msgOptions, &$topicOptions, &$posterOptions): bool
	{
		$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
		$topicOptions['lock_mode'] = $topicOptions['lock_mode'] ?? null;
		$topicOptions['sticky_mode'] = $topicOptions['sticky_mode'] ?? null;

		// This is longer than it has to be, but makes it so we only set/change what we have to.
		$messages_columns = [];

		if (isset($posterOptions['name'])) {
			$messages_columns['poster_name'] = $posterOptions['name'];
		}

		if (isset($posterOptions['email'])) {
			$messages_columns['poster_email'] = $posterOptions['email'];
		}

		if (isset($msgOptions['icon'])) {
			$messages_columns['icon'] = $msgOptions['icon'];
		}

		if (isset($msgOptions['subject'])) {
			$messages_columns['subject'] = $msgOptions['subject'];
		}

		if (isset($msgOptions['body'])) {
			$messages_columns['body'] = $msgOptions['body'];

			// using a custom search index, then lets get the old message so we can update our index as needed
			if (!empty(Config::$modSettings['search_custom_index_config'])) {
				$request = Db::$db->query(
					'',
					'SELECT body
					FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}',
					[
						'id_msg' => $msgOptions['id'],
					],
				);
				list($msgOptions['old_body']) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}
		}

		if (!empty($msgOptions['modify_time'])) {
			$messages_columns['modified_time'] = $msgOptions['modify_time'];
			$messages_columns['modified_name'] = $msgOptions['modify_name'];
			$messages_columns['modified_reason'] = $msgOptions['modify_reason'];
			$messages_columns['id_msg_modified'] = Config::$modSettings['maxMsgID'];
		}

		if (isset($msgOptions['smileys_enabled'])) {
			$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;
		}

		// Which columns need to be ints?
		$messageInts = ['modified_time', 'id_msg_modified', 'smileys_enabled'];
		$update_parameters = [
			'id_msg' => $msgOptions['id'],
		];

		// Update search api
		$searchAPI = SearchApi::load();

		if ($searchAPI->supportsMethod('postRemoved')) {
			$searchAPI->postRemoved($msgOptions['id']);
		}

		// Anyone quoted or mentioned?
		$quoted_members = Mentions::getQuotedMembers($msgOptions['body'], $posterOptions['id']);
		$quoted_modifications = Mentions::modifyMentions('quote', $msgOptions['id'], $quoted_members, $posterOptions['id']);

		if (!empty($quoted_modifications['added'])) {
			$msgOptions['quoted_members'] = array_intersect_key($quoted_members, array_flip(array_keys($quoted_modifications['added'])));

			// You don't need a notification about quoting yourself.
			unset($msgOptions['quoted_members'][User::$me->id]);
		}

		if (!empty(Config::$modSettings['enable_mentions']) && isset($msgOptions['body'])) {
			$mentions = Mentions::getMentionedMembers($msgOptions['body']);
			$messages_columns['body'] = $msgOptions['body'] = Mentions::getBody($msgOptions['body'], $mentions);
			$mentions = Mentions::verifyMentionedMembers($msgOptions['body'], $mentions);

			// Update our records in the database.
			$mention_modifications = Mentions::modifyMentions('msg', $msgOptions['id'], $mentions, $posterOptions['id']);

			if (!empty($mention_modifications['added'])) {
				// Queue this for notification.
				$msgOptions['mentioned_members'] = array_intersect_key($mentions, array_flip(array_keys($mention_modifications['added'])));

				// Mentioning yourself is silly, and we aren't going to notify you about it.
				unset($msgOptions['mentioned_members'][User::$me->id]);
			}
		}

		// This allows mods to skip sending notifications if they don't want to.
		$msgOptions['send_notifications'] = isset($msgOptions['send_notifications']) ? (bool) $msgOptions['send_notifications'] : true;

		// Maybe a mod wants to make some changes?
		IntegrationHook::call('integrate_modify_post', [&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts]);

		foreach ($messages_columns as $var => $val) {
			$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
			$update_parameters['var_' . $var] = $val;
		}

		// Nothing to do?
		if (empty($messages_columns)) {
			return true;
		}

		// Change the post.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET ' . implode(', ', $messages_columns) . '
			WHERE id_msg = {int:id_msg}',
			$update_parameters,
		);

		// Lock and or sticky the post.
		if ($topicOptions['sticky_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET
					is_sticky = {raw:is_sticky},
					locked = {raw:locked},
					id_poll = {raw:id_poll}
				WHERE id_topic = {int:id_topic}',
				[
					'is_sticky' => $topicOptions['sticky_mode'] === null ? 'is_sticky' : (int) $topicOptions['sticky_mode'],
					'locked' => $topicOptions['lock_mode'] === null ? 'locked' : (int) $topicOptions['lock_mode'],
					'id_poll' => $topicOptions['poll'] === null ? 'id_poll' : (int) $topicOptions['poll'],
					'id_topic' => $topicOptions['id'],
				],
			);
		}

		// Mark the edited post as read.
		if (!empty($topicOptions['mark_as_read']) && !User::$me->is_guest) {
			// Since it's likely they *read* it before editing, let's try an UPDATE first.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				[
					'current_member' => User::$me->id,
					'id_msg' => Config::$modSettings['maxMsgID'],
					'id_topic' => $topicOptions['id'],
				],
			);

			$flag = Db::$db->affected_rows() != 0;

			if (empty($flag)) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}log_topics',
					['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'],
					[$topicOptions['id'], User::$me->id, Config::$modSettings['maxMsgID']],
					['id_topic', 'id_member'],
				);
			}
		}

		// If there's a custom search index, it needs to be modified...
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'postModified'])) {
			$searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);
		}

		// Send notifications about any new quotes or mentions.
		if (
			$msgOptions['send_notifications']
			&& !empty($msgOptions['approved'])
			&& (
				!empty($msgOptions['quoted_members'])
				|| !empty($msgOptions['mentioned_members'])
				|| !empty($mention_modifications['removed'])
				|| !empty($quoted_modifications['removed'])
			)
		) {
			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\CreatePost_Notify',
					Utils::jsonEncode([
						'msgOptions' => $msgOptions,
						'topicOptions' => $topicOptions,
						'posterOptions' => $posterOptions,
						'type' => 'edit',
					]),
					0,
				],
				['id_task'],
			);
		}

		if (isset($msgOptions['subject'])) {
			// Only update the subject if this was the first message in the topic.
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}topics
				WHERE id_first_msg = {int:id_first_msg}
				LIMIT 1',
				[
					'id_first_msg' => $msgOptions['id'],
				],
			);

			if (Db::$db->num_rows($request) == 1) {
				Logging::updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
			}
			Db::$db->free_result($request);
		}

		// Finally, if we are setting the approved state we need to do much more work :(
		if (Config::$modSettings['postmod_active'] && isset($msgOptions['approved'])) {
			Msg::approve($msgOptions['id'], $msgOptions['approved']);
		}

		return true;
	}

	/**
	 * Approve (or not) some posts... without permission checks...
	 *
	 * @param array $msgs Array of message ids
	 * @param bool $approve Whether to approve the posts (if false, posts are unapproved)
	 * @param bool $notify Whether to notify users
	 * @return bool Whether the operation was successful
	 */
	public static function approve($msgs, $approve = true, $notify = true): bool
	{
		if (!is_array($msgs)) {
			$msgs = [$msgs];
		}

		if (empty($msgs)) {
			return false;
		}

		// May as well start at the beginning, working out *what* we need to change.
		$message_list = $msgs;

		$msgs = [];
		$topics = [];
		$topic_changes = [];
		$board_changes = [];
		$notification_topics = [];
		$notification_posts = [];
		$member_post_changes = [];

		$request = Db::$db->query(
			'',
			'SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
				m.body, m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.id_member,
				t.approved AS topic_approved, b.count_posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg IN ({array_int:message_list})
				AND m.approved = {int:approved_state}',
			[
				'message_list' => $message_list,
				'approved_state' => $approve ? 0 : 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Easy...
			$msgs[] = $row['id_msg'];
			$topics[] = $row['id_topic'];

			// Ensure our change array exists already.
			if (!isset($topic_changes[$row['id_topic']])) {
				$topic_changes[$row['id_topic']] = [
					'id_last_msg' => $row['id_last_msg'],
					'approved' => $row['topic_approved'],
					'replies' => 0,
					'unapproved_posts' => 0,
				];
			}

			if (!isset($board_changes[$row['id_board']])) {
				$board_changes[$row['id_board']] = [
					'posts' => 0,
					'topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0,
				];
			}

			// If it's the first message then the topic state changes!
			if ($row['id_msg'] == $row['id_first_msg']) {
				$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

				$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
				$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;

				// Note we need to ensure we announce this topic!
				$notification_topics[] = [
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'board' => $row['id_board'],
					'topic' => $row['id_topic'],
					'msg' => $row['id_first_msg'],
					'poster' => $row['id_member'],
					'new_topic' => true,
				];
			} else {
				$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;

				// This will be a post... but don't notify unless it's not followed by approved ones.
				if ($row['id_msg'] > $row['id_last_msg']) {
					$notification_posts[$row['id_topic']] = [
						'id' => $row['id_msg'],
						'body' => $row['body'],
						'subject' => $row['subject'],
						'name' => $row['poster_name'],
						'topic' => $row['id_topic'],
						'board' => $row['id_board'],
						'poster' => $row['id_member'],
						'new_topic' => false,
						'msg' => $row['id_msg'],
					];
				}
			}

			// If this is being approved and id_msg is higher than the current id_last_msg then it changes.
			if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg']) {
				$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];
			}
			// If this is being unapproved, and it's equal to the id_last_msg we need to find a new one!
			elseif (!$approve) {
				// Default to the first message and then we'll override in a bit ;)
				$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];
			}

			$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;

			// Post count for the user?
			if ($row['id_member'] && empty($row['count_posts'])) {
				$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
			}
		}
		Db::$db->free_result($request);

		if (empty($msgs)) {
			return false;
		}

		// Now we have the differences make the changes, first the easy one.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET approved = {int:approved_state}
			WHERE id_msg IN ({array_int:message_list})',
			[
				'message_list' => $msgs,
				'approved_state' => $approve ? 1 : 0,
			],
		);

		// If we were unapproving find the last msg in the topics...
		if (!$approve) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic, MAX(id_msg) AS id_last_msg
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND approved = {int:approved}
				GROUP BY id_topic',
				[
					'topic_list' => $topics,
					'approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
			}
			Db::$db->free_result($request);
		}

		// ... next the topics...
		foreach ($topic_changes as $id => $changes) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET approved = {int:approved}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
					num_replies = num_replies + {int:num_replies}, id_last_msg = {int:id_last_msg}
				WHERE id_topic = {int:id_topic}',
				[
					'approved' => $changes['approved'],
					'unapproved_posts' => $changes['unapproved_posts'],
					'num_replies' => $changes['replies'],
					'id_last_msg' => $changes['id_last_msg'],
					'id_topic' => $id,
				],
			);
		}

		// ... finally the boards...
		foreach ($board_changes as $id => $changes) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET num_posts = num_posts + {int:num_posts}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
					num_topics = num_topics + {int:num_topics}, unapproved_topics = unapproved_topics + {int:unapproved_topics}
				WHERE id_board = {int:id_board}',
				[
					'num_posts' => $changes['posts'],
					'unapproved_posts' => $changes['unapproved_posts'],
					'num_topics' => $changes['topics'],
					'unapproved_topics' => $changes['unapproved_topics'],
					'id_board' => $id,
				],
			);
		}

		// Finally, least importantly, notifications!
		if ($approve) {
			$task_rows = [];

			foreach (array_merge($notification_topics, $notification_posts) as $topic) {
				$task_rows[] = [
					'SMF\\Tasks\\CreatePost_Notify',
					Utils::jsonEncode([
						'msgOptions' => [
							'id' => $topic['msg'],
							'body' => $topic['body'],
							'subject' => $topic['subject'],
						],
						'topicOptions' => [
							'id' => $topic['topic'],
							'board' => $topic['board'],
						],
						'posterOptions' => [
							'id' => $topic['poster'],
							'name' => $topic['name'],
						],
						'type' => $topic['new_topic'] ? 'topic' : 'reply',
					]),
					0,
				];
			}

			if ($notify) {
				Db::$db->insert(
					'',
					'{db_prefix}background_tasks',
					[
						'task_class' => 'string',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					$task_rows,
					['id_task'],
				);
			}

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}approval_queue
				WHERE id_msg IN ({array_int:message_list})
					AND id_attach = {int:id_attach}',
				[
					'message_list' => $msgs,
					'id_attach' => 0,
				],
			);

			// Clean up moderator alerts
			if (!empty($notification_topics)) {
				self::clearApprovalAlerts(array_column($notification_topics, 'topic'), 'unapproved_topic');
			}

			if (!empty($notification_posts)) {
				self::clearApprovalAlerts(array_column($notification_posts, 'id'), 'unapproved_post');
			}
		}
		// If unapproving add to the approval queue!
		else {
			$msgInserts = [];

			foreach ($msgs as $msg) {
				$msgInserts[] = [$msg];
			}

			Db::$db->insert(
				'ignore',
				'{db_prefix}approval_queue',
				['id_msg' => 'int'],
				$msgInserts,
				['id_msg'],
			);
		}

		// Update the last messages on the boards...
		self::updateLastMessages(array_keys($board_changes));

		// Post count for the members?
		if (!empty($member_post_changes)) {
			foreach ($member_post_changes as $id_member => $count_change) {
				User::updateMemberData($id_member, ['posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change]);
			}
		}

		// In case an external CMS needs to know about this approval/unapproval.
		IntegrationHook::call('integrate_after_approve_posts', [$approve, $msgs, $topic_changes, $member_post_changes]);

		return true;
	}

	/**
	 * Upon approval, clear unread alerts.
	 *
	 * @param int[] $content_ids either id_msgs or id_topics
	 * @param string $content_action will be either 'unapproved_post' or 'unapproved_topic'
	 */
	public static function clearApprovalAlerts($content_ids, $content_action): void
	{
		// Some data hygiene...
		if (!is_array($content_ids)) {
			return;
		}

		$content_ids = array_filter(array_map('intval', $content_ids));

		if (empty($content_ids)) {
			return;
		}

		if (!in_array($content_action, ['unapproved_post', 'unapproved_topic'])) {
			return;
		}

		// Check to see if there are unread alerts to delete...
		// Might be multiple alerts, for multiple moderators...
		Alert::deleteWhere(
			[
				'content_id IN ({array_int:content_ids})',
				'content_type = {string:content_type}',
				'content_action = {string:content_action}',
				'is_read = {int:unread}',
			],
			[
				'content_ids' => $content_ids,
				'content_type' => $content_action === 'unapproved_topic' ? 'topic' : 'msg',
				'content_action' => $content_action,
				'unread' => 0,
			],
			true,
		);
	}

	/**
	 * Takes an array of board IDs and updates their last messages.
	 * If the board has a parent, that parent board is also automatically
	 * updated.
	 * The columns updated are id_last_msg and last_updated.
	 * Note that id_last_msg should always be updated using this function,
	 * and is not automatically updated upon other changes.
	 *
	 * @param array $setboards An array of board IDs
	 * @param int $id_msg The ID of the message
	 * @return void|false Returns false if $setboards is empty for some reason
	 */
	public static function updateLastMessages($setboards, $id_msg = 0)
	{
		// Please - let's be sane.
		if (empty($setboards)) {
			return false;
		}

		if (!is_array($setboards)) {
			$setboards = [$setboards];
		}

		// Find the latest message on this board (highest id_msg.)
		$lastMsg = [];

		// If we don't know the id_msg we need to find it.
		if (!$id_msg) {

			$request = Db::$db->query(
				'',
				'SELECT id_board, MAX(id_last_msg) AS id_msg
				FROM {db_prefix}topics
				WHERE id_board IN ({array_int:board_list})
					AND approved = {int:approved}
				GROUP BY id_board',
				[
					'board_list' => $setboards,
					'approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$lastMsg[$row['id_board']] = $row['id_msg'];
			}
			Db::$db->free_result($request);
		} else {
			// Just to note - there should only be one board passed if we are doing this.
			foreach ($setboards as $id_board) {
				$lastMsg[$id_board] = $id_msg;
			}
		}

		$parent_boards = [];

		// Keep track of last modified dates.
		$lastModified = $lastMsg;

		// Get all the child boards for the parents, if they have some...
		foreach ($setboards as $id_board) {
			if (!isset($lastMsg[$id_board])) {
				$lastMsg[$id_board] = 0;
				$lastModified[$id_board] = 0;
			}

			if (!empty(Board::$info->id) && $id_board == Board::$info->id) {
				$parents = Board::$info->parent_boards;
			} else {
				$parents = Board::getParents($id_board);
			}

			// Ignore any parents on the top child level.
			// @todo Why?
			foreach ($parents as $id => $parent) {
				if ($parent['level'] != 0) {
					// If we're already doing this one as a board, is this a higher last modified?
					if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id]) {
						$lastModified[$id] = $lastModified[$id_board];
					} elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board])) {
						$parent_boards[$id] = $lastModified[$id_board];
					}
				}
			}
		}

		// Note to help understand what is happening here. For parents we update the timestamp of the last message for determining
		// whether there are child boards which have not been read. For the boards themselves we update both this and id_last_msg.

		$board_updates = [];
		$parent_updates = [];

		// Finally, to save on queries make the changes...
		foreach ($parent_boards as $id => $msg) {
			if (!isset($parent_updates[$msg])) {
				$parent_updates[$msg] = [$id];
			} else {
				$parent_updates[$msg][] = $id;
			}
		}

		foreach ($lastMsg as $id => $msg) {
			if (!isset($board_updates[$msg . '-' . $lastModified[$id]])) {
				$board_updates[$msg . '-' . $lastModified[$id]] = [
					'id' => $msg,
					'updated' => $lastModified[$id],
					'boards' => [$id],
				];
			} else {
				$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
			}
		}

		// Now commit the changes!
		foreach ($parent_updates as $id_msg => $boards) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET id_msg_updated = {int:id_msg_updated}
				WHERE id_board IN ({array_int:board_list})
					AND id_msg_updated < {int:id_msg_updated}',
				[
					'board_list' => $boards,
					'id_msg_updated' => $id_msg,
				],
			);
		}

		foreach ($board_updates as $board_data) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
				WHERE id_board IN ({array_int:board_list})',
				[
					'board_list' => $board_data['boards'],
					'id_last_msg' => $board_data['id'],
					'id_msg_updated' => $board_data['updated'],
				],
			);
		}
	}

	/**
	 * Remove a specific message (including permission checks).
	 *
	 * @param int $message The message id
	 * @param bool $decreasePostCount Whether to decrease users' post counts
	 * @return bool Whether the operation succeeded
	 */
	public static function remove($message, $decreasePostCount = true)
	{
		if (empty($message) || !is_numeric($message)) {
			return false;
		}

		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

		$request = Db::$db->query(
			'',
			'SELECT
				m.id_member, m.icon, m.poster_time, m.subject,' . (empty(Config::$modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
				m.approved, t.id_topic, t.id_first_msg, t.id_last_msg, t.num_replies, t.id_board,
				t.id_member_started AS id_member_poster,
				b.count_posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE m.id_msg = {int:id_msg}
			LIMIT 1',
			[
				'id_msg' => $message,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			Db::$db->free_result($request);

			return false;
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Give mods a heads-up before we do anything.
		IntegrationHook::call('integrate_pre_remove_message', [$message, $decreasePostCount, $row]);

		if (empty(Board::$info->id) || $row['id_board'] != Board::$info->id) {
			$delete_any = User::$me->boardsAllowedTo('delete_any');

			if (!in_array(0, $delete_any) && !in_array($row['id_board'], $delete_any)) {
				$delete_own = User::$me->boardsAllowedTo('delete_own');
				$delete_own = in_array(0, $delete_own) || in_array($row['id_board'], $delete_own);
				$delete_replies = User::$me->boardsAllowedTo('delete_replies');
				$delete_replies = in_array(0, $delete_replies) || in_array($row['id_board'], $delete_replies);

				if ($row['id_member'] == User::$me->id) {
					if (!$delete_own) {
						if ($row['id_member_poster'] == User::$me->id) {
							if (!$delete_replies) {
								ErrorHandler::fatalLang('cannot_delete_replies', 'permission');
							}
						} else {
							ErrorHandler::fatalLang('cannot_delete_own', 'permission');
						}
					} elseif (($row['id_member_poster'] != User::$me->id || !$delete_replies) && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 < time()) {
						ErrorHandler::fatalLang('modify_post_time_passed', false);
					}
				} elseif ($row['id_member_poster'] == User::$me->id) {
					if (!$delete_replies) {
						ErrorHandler::fatalLang('cannot_delete_replies', 'permission');
					}
				} else {
					ErrorHandler::fatalLang('cannot_delete_any', 'permission');
				}
			}

			// Can't delete an unapproved message, if you can't see it!
			if (Config::$modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != User::$me->id && !(in_array(0, $delete_any) || in_array($row['id_board'], $delete_any))) {
				$approve_posts = User::$me->boardsAllowedTo('approve_posts');

				if (!in_array(0, $approve_posts) && !in_array($row['id_board'], $approve_posts)) {
					return false;
				}
			}
		} else {
			// Check permissions to delete this message.
			if ($row['id_member'] == User::$me->id) {
				if (!User::$me->allowedTo('delete_own')) {
					if ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('delete_any')) {
						User::$me->isAllowedTo('delete_replies');
					} elseif (!User::$me->allowedTo('delete_any')) {
						User::$me->isAllowedTo('delete_own');
					}
				} elseif (!User::$me->allowedTo('delete_any') && ($row['id_member_poster'] != User::$me->id || !User::$me->allowedTo('delete_replies')) && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 < time()) {
					ErrorHandler::fatalLang('modify_post_time_passed', false);
				}
			} elseif ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('delete_any')) {
				User::$me->isAllowedTo('delete_replies');
			} else {
				User::$me->isAllowedTo('delete_any');
			}

			if (Config::$modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != User::$me->id && !User::$me->allowedTo('delete_own')) {
				User::$me->isAllowedTo('approve_posts');
			}
		}

		// Delete the *whole* topic, but only if the topic consists of one message.
		if ($row['id_first_msg'] == $message) {
			if (empty(Board::$info->id) || $row['id_board'] != Board::$info->id) {
				$remove_any = User::$me->boardsAllowedTo('remove_any');
				$remove_any = in_array(0, $remove_any) || in_array($row['id_board'], $remove_any);

				if (!$remove_any) {
					$remove_own = User::$me->boardsAllowedTo('remove_own');
					$remove_own = in_array(0, $remove_own) || in_array($row['id_board'], $remove_own);
				}

				if ($row['id_member'] != User::$me->id && !$remove_any) {
					ErrorHandler::fatalLang('cannot_remove_any', 'permission');
				} elseif (!$remove_any && !$remove_own) {
					ErrorHandler::fatalLang('cannot_remove_own', 'permission');
				}
			} else {
				// Check permissions to delete a whole topic.
				if ($row['id_member'] != User::$me->id) {
					User::$me->isAllowedTo('remove_any');
				} elseif (!User::$me->allowedTo('remove_any')) {
					User::$me->isAllowedTo('remove_own');
				}
			}

			// ...if there is only one post.
			if (!empty($row['num_replies'])) {
				ErrorHandler::fatalLang('delFirstPost', false);
			}

			Topic::remove($row['id_topic']);

			return true;
		}

		// Deleting a recycled message can not lower anyone's post count.
		if (!empty($recycle_board) && $row['id_board'] == $recycle_board) {
			$decreasePostCount = false;
		}

		// This is the last post, update the last post on the board.
		if ($row['id_last_msg'] == $message) {
			// Find the last message, set it, and decrease the post count.
			$request = Db::$db->query(
				'',
				'SELECT id_msg, id_member
				FROM {db_prefix}messages
				WHERE id_topic = {int:id_topic}
					AND id_msg != {int:id_msg}
				ORDER BY ' . (Config::$modSettings['postmod_active'] ? 'approved DESC, ' : '') . 'id_msg DESC
				LIMIT 1',
				[
					'id_topic' => $row['id_topic'],
					'id_msg' => $message,
				],
			);
			$row2 = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET
					id_last_msg = {int:id_last_msg},
					id_member_updated = {int:id_member_updated}' . (!Config::$modSettings['postmod_active'] || $row['approved'] ? ',
					num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : ',
					unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
				WHERE id_topic = {int:id_topic}',
				[
					'id_last_msg' => $row2['id_msg'],
					'id_member_updated' => $row2['id_member'],
					'no_replies' => 0,
					'no_unapproved' => 0,
					'id_topic' => $row['id_topic'],
				],
			);
		}
		// Only decrease post counts.
		else {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET ' . ($row['approved'] ? '
					num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : '
					unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
				WHERE id_topic = {int:id_topic}',
				[
					'no_replies' => 0,
					'no_unapproved' => 0,
					'id_topic' => $row['id_topic'],
				],
			);
		}

		// Default recycle to false.
		$recycle = false;

		// If recycle topics has been set, make a copy of this message in the recycle board.
		// Make sure we're not recycling messages that are already on the recycle board.
		if (!empty(Config::$modSettings['recycle_enable']) && $row['id_board'] != Config::$modSettings['recycle_board'] && $row['icon'] != 'recycled') {
			// Check if the recycle board exists and if so get the read status.
			$request = Db::$db->query(
				'',
				'SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS is_seen, id_last_msg
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
				WHERE b.id_board = {int:recycle_board}',
				[
					'current_member' => User::$me->id,
					'recycle_board' => Config::$modSettings['recycle_board'],
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('recycle_no_valid_board');
			}
			list($isRead, $last_board_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Is there an existing topic in the recycle board to group this post with?
			$request = Db::$db->query(
				'',
				'SELECT id_topic, id_first_msg, id_last_msg
				FROM {db_prefix}topics
				WHERE id_previous_topic = {int:id_previous_topic}
					AND id_board = {int:recycle_board}',
				[
					'id_previous_topic' => $row['id_topic'],
					'recycle_board' => Config::$modSettings['recycle_board'],
				],
			);
			list($id_recycle_topic, $first_topic_msg, $last_topic_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Insert a new topic in the recycle board if $id_recycle_topic is empty.
			if (empty($id_recycle_topic)) {
				$id_topic = Db::$db->insert(
					'',
					'{db_prefix}topics',
					[
						'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
						'id_last_msg' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int', 'id_previous_topic' => 'int',
					],
					[
						Config::$modSettings['recycle_board'], $row['id_member'], $row['id_member'], $message,
						$message, 0, 1, $row['id_topic'],
					],
					['id_topic'],
					1,
				);
			}

			// Capture the ID of the new topic...
			$topicID = empty($id_recycle_topic) ? $id_topic : $id_recycle_topic;

			// If the topic creation went successful, move the message.
			if ($topicID > 0) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}messages
					SET
						id_topic = {int:id_topic},
						id_board = {int:recycle_board},
						approved = {int:is_approved}
					WHERE id_msg = {int:id_msg}',
					[
						'id_topic' => $topicID,
						'recycle_board' => Config::$modSettings['recycle_board'],
						'id_msg' => $message,
						'is_approved' => 1,
					],
				);

				// Take any reported posts with us...
				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_reported
					SET
						id_topic = {int:id_topic},
						id_board = {int:recycle_board}
					WHERE id_msg = {int:id_msg}',
					[
						'id_topic' => $topicID,
						'recycle_board' => Config::$modSettings['recycle_board'],
						'id_msg' => $message,
					],
				);

				// Mark recycled topic as read.
				if (!User::$me->is_guest) {
					Db::$db->insert(
						'replace',
						'{db_prefix}log_topics',
						['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
						[$topicID, User::$me->id, Config::$modSettings['maxMsgID'], 0],
						['id_topic', 'id_member'],
					);
				}

				// Mark recycle board as seen, if it was marked as seen before.
				if (!empty($isRead) && !User::$me->is_guest) {
					Db::$db->insert(
						'replace',
						'{db_prefix}log_boards',
						['id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'],
						[Config::$modSettings['recycle_board'], User::$me->id, Config::$modSettings['maxMsgID']],
						['id_board', 'id_member'],
					);
				}

				// Add one topic and post to the recycle bin board.
				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET
						num_topics = num_topics + {int:num_topics_inc},
						num_posts = num_posts + 1' .
							($message > $last_board_msg ? ', id_last_msg = {int:id_merged_msg}' : '') . '
					WHERE id_board = {int:recycle_board}',
					[
						'num_topics_inc' => empty($id_recycle_topic) ? 1 : 0,
						'recycle_board' => Config::$modSettings['recycle_board'],
						'id_merged_msg' => $message,
					],
				);

				// Lets increase the num_replies, and the first/last message ID as appropriate.
				if (!empty($id_recycle_topic)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}topics
						SET num_replies = num_replies + 1' .
							($message > $last_topic_msg ? ', id_last_msg = {int:id_merged_msg}' : '') .
							($message < $first_topic_msg ? ', id_first_msg = {int:id_merged_msg}' : '') . '
						WHERE id_topic = {int:id_recycle_topic}',
						[
							'id_recycle_topic' => $id_recycle_topic,
							'id_merged_msg' => $message,
						],
					);
				}

				// Make sure this message isn't getting deleted later on.
				$recycle = true;

				// Make sure we update the search subject index.
				Logging::updateStats('subject', $topicID, $row['subject']);
			}

			// If it wasn't approved don't keep it in the queue.
			if (!$row['approved']) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}approval_queue
					WHERE id_msg = {int:id_msg}
						AND id_attach = {int:id_attach}',
					[
						'id_msg' => $message,
						'id_attach' => 0,
					],
				);
			}
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET ' . ($row['approved'] ? '
				num_posts = CASE WHEN num_posts = {int:no_posts} THEN 0 ELSE num_posts - 1 END' : '
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_board = {int:id_board}',
			[
				'no_posts' => 0,
				'no_unapproved' => 0,
				'id_board' => $row['id_board'],
			],
		);

		// If the poster was registered and the board this message was on incremented
		// the member's posts when it was posted, decrease his or her post count.
		if (!empty($row['id_member']) && $decreasePostCount && empty($row['count_posts']) && $row['approved']) {
			User::updateMemberData($row['id_member'], ['posts' => '-']);
		}

		// Only remove posts if they're not recycled.
		if (!$recycle) {
			// Callback for search APIs to do their thing
			$searchAPI = SearchApi::load();

			if ($searchAPI->supportsMethod('postRemoved')) {
				$searchAPI->postRemoved($message);
			}

			// Remove the message!
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				[
					'id_msg' => $message,
				],
			);

			if (!empty(Config::$modSettings['search_custom_index_config'])) {
				$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

				$words = Utils::text2words($row['body'], $customIndexSettings['bytes_per_word'], true);

				if (!empty($words)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}log_search_words
						WHERE id_word IN ({array_int:word_list})
							AND id_msg = {int:id_msg}',
						[
							'word_list' => $words,
							'id_msg' => $message,
						],
					);
				}
			}

			// Delete attachment(s) if they exist.
			$attachmentQuery = [
				'attachment_type' => 0,
				'id_msg' => $message,
			];

			Attachment::remove($attachmentQuery);
		}

		// Allow mods to remove message related data of their own (likes, maybe?)
		IntegrationHook::call('integrate_remove_message', [$message, $row, $recycle]);

		// Update the pesky statistics.
		Logging::updateStats('message');
		Logging::updateStats('topic');
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		// And now to update the last message of each board we messed with.
		if ($recycle) {
			Msg::updateLastMessages([$row['id_board'], Config::$modSettings['recycle_board']]);
		} else {
			Msg::updateLastMessages($row['id_board']);
		}

		// Close any moderation reports for this message.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_reported
			SET closed = {int:is_closed}
			WHERE id_msg = {int:id_msg}',
			[
				'is_closed' => 1,
				'id_msg' => $message,
			],
		);

		if (Db::$db->affected_rows() != 0) {
			Config::updateModSettings(['last_mod_report_action' => time()]);
			ReportedContent::recountOpenReports('posts');
		}

		return false;
	}

	/**
	 * spell_init()
	 *
	 * Sets up a dictionary resource handle. Tries enchant first then falls through to pspell.
	 *
	 * @return resource|bool An enchant or pspell dictionary resource handle or false if the dictionary couldn't be loaded
	 */
	public static function spell_init()
	{
		// Check for UTF-8 and strip ".utf8" off the lang_locale string for enchant
		Utils::$context['spell_utf8'] = (Lang::$txt['lang_character_set'] == 'UTF-8');
		$lang_locale = str_replace('.utf8', '', Lang::$txt['lang_locale']);

		// Try enchant first since PSpell is (supposedly) deprecated as of PHP 5.3
		// enchant only does UTF-8, so we need iconv if you aren't using UTF-8
		if (function_exists('enchant_broker_init') && (Utils::$context['spell_utf8'] || function_exists('iconv'))) {
			// We'll need this to free resources later...
			Utils::$context['enchant_broker'] = enchant_broker_init();

			// Try locale first, then general...
			if (!empty($lang_locale) && enchant_broker_dict_exists(Utils::$context['enchant_broker'], $lang_locale)) {
				$enchant_link = enchant_broker_request_dict(Utils::$context['enchant_broker'], $lang_locale);
			} elseif (enchant_broker_dict_exists(Utils::$context['enchant_broker'], Lang::$txt['lang_dictionary'])) {
				$enchant_link = enchant_broker_request_dict(Utils::$context['enchant_broker'], Lang::$txt['lang_dictionary']);
			}

			// Success
			if (!empty($enchant_link)) {
				Utils::$context['provider'] = 'enchant';

				return $enchant_link;
			}

			// Free up any resources used...
			unset(Utils::$context['enchant_broker']);
		}

		// Fall through to pspell if enchant didn't work
		if (function_exists('pspell_new')) {
			// Okay, this looks funny, but it actually fixes a weird bug.
			ob_start();
			$old = error_reporting(0);

			// See, first, some windows machines don't load pspell properly on the first try.  Dumb, but this is a workaround.
			pspell_new('en');

			// Next, the dictionary in question may not exist. So, we try it... but...
			$pspell_link = pspell_new(Lang::$txt['lang_dictionary'], '', '', strtr(Utils::$context['character_set'], ['iso-' => 'iso', 'ISO-' => 'iso']), PSPELL_FAST | PSPELL_RUN_TOGETHER);

			// Most people don't have anything but English installed... So we use English as a last resort.
			if (!$pspell_link) {
				$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);
			}

			error_reporting($old);
			ob_end_clean();

			// If we have pspell, exit now...
			if ($pspell_link) {
				Utils::$context['provider'] = 'pspell';

				return $pspell_link;
			}
		}

		// If we get this far, we're doomed
		return false;
	}

	/**
	 * spell_check()
	 *
	 * Determines whether or not the specified word is spelled correctly
	 *
	 * @param \PSpell\Dictionary $dict An enchant or pspell dictionary resource set up by {@link spell_init()}
	 * @param string $word A word to check the spelling of
	 * @return bool Whether or not the specified word is spelled properly
	 */
	public static function spell_check($dict, $word): bool
	{
		// Enchant or pspell?
		if (Utils::$context['provider'] == 'enchant') {
			// This is a bit tricky here...
			if (!Utils::$context['spell_utf8']) {
				// Convert the word to UTF-8 with iconv
				$word = iconv(Lang::$txt['lang_character_set'], 'UTF-8', $word);
			}

			return enchant_dict_check($dict, $word);
		}

		if (Utils::$context['provider'] == 'pspell') {
			return pspell_check($dict, $word);
		}
	}

	/**
	 * spell_suggest()
	 *
	 * Returns an array of suggested replacements for the specified word
	 *
	 * @param \PSpell\Dictionary $dict An enchant or pspell dictionary resource
	 * @param string $word A misspelled word
	 * @return array An array of suggested replacements for the misspelled word
	 */
	public static function spell_suggest($dict, $word): array
	{
		if (Utils::$context['provider'] == 'enchant') {
			// If we're not using UTF-8, we need iconv to handle some stuff...
			if (!Utils::$context['spell_utf8']) {
				// Convert the word to UTF-8 before getting suggestions
				$word = iconv(Lang::$txt['lang_character_set'], 'UTF-8', $word);
				$suggestions = enchant_dict_suggest($dict, $word);

				// Go through the suggestions and convert them back to the proper character set
				foreach ($suggestions as $index => $suggestion) {
					// //TRANSLIT makes it use similar-looking characters for incompatible ones...
					$suggestions[$index] = iconv('UTF-8', Lang::$txt['lang_character_set'] . '//TRANSLIT', $suggestion);
				}

				return $suggestions;
			}

			return enchant_dict_suggest($dict, $word);
		}

		if (Utils::$context['provider'] == 'pspell') {
			return pspell_suggest($dict, $word);
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Generator that runs queries about message data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}boards AS b' is always part of the query.
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
		self::$messages_request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}messages AS m' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (!empty($limit) ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc(self::$messages_request)) {
			yield $row;
		}
		Db::$db->free_result(self::$messages_request);
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Msg::exportStatic')) {
	Msg::exportStatic();
}

?>