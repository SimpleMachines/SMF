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

use SMF\Db\DatabaseApi as Db;

/**
 * Represents an alert and provides methods for working with alerts.
 */
class Alert implements \ArrayAccess
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
			'fetch' => 'fetch_alerts',
			'count' => 'alert_count',
			'mark' => 'alert_mark',
			'delete' => 'alert_delete',
			'purge' => 'alert_purge',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This alert's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * UNIX timestamp when the alert was created.
	 */
	public int $timestamp;

	/**
	 * @var int
	 *
	 * ID of the member this alert is for.
	 */
	public int $member;

	/**
	 * @var int
	 *
	 * ID of the member that caused this alert to be sent.
	 */
	public int $member_started;

	/**
	 * @var string
	 *
	 * Name of the member that caused this alert to be sent.
	 */
	public string $member_name;

	/**
	 * @var string
	 *
	 * Type of content that this alert is about.
	 */
	public string $content_type;

	/**
	 * @var int
	 *
	 * ID of the content that this alert is about.
	 */
	public int $content_id;

	/**
	 * @var string
	 *
	 * The action taken upon the content that this alert is about.
	 */
	public string $content_action;

	/**
	 * @var int
	 *
	 * UNIX timestamp when the member read this alert, or zero if unread.
	 */
	public int $is_read;

	/**
	 * @var array
	 *
	 * More info about this alert.
	 * Content varies widely from case to case.
	 */
	public array $extra;

	/**
	 * @var string
	 *
	 * The icon for this alert.
	 */
	public string $icon;

	/**
	 * @var string
	 *
	 * The alert message.
	 */
	public string $text;

	/**
	 * @var bool
	 *
	 * Whether to show links in the constituent parts of the alert message.
	 */
	public bool $show_links;

	/**
	 * @var string
	 *
	 * Formatted date string based on $this->timestamp.
	 */
	public string $time;

	/**
	 * @var string
	 *
	 * URL that this alert should take the user to.
	 */
	public string $target_href;

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
	 * @var array
	 *
	 * Some sprintf formats for generating links/strings.
	 *
	 * 'required' is an array of keys in $this->extra that should be used
	 *     to generate the message, ordered to match the sprintf formats.
	 *
	 * 'link' and 'text' are the sprintf formats that will be used when
	 *     $this->show_links is true or false, respectively.
	 */
	public static $link_formats = [
		'msg_msg' => [
			'required' => ['content_subject', 'topic', 'msg'],
			'link' => '<a href="{scripturl}?topic=%2$d.msg%3$d#msg%3$d">%1$s</a>',
			'text' => '<strong>%1$s</strong>',
		],
		'topic_msg' => [
			'required' => ['content_subject', 'topic', 'topic_suffix'],
			'link' => '<a href="{scripturl}?topic=%2$d.%3$s">%1$s</a>',
			'text' => '<strong>%1$s</strong>',
		],
		'board_msg' => [
			'required' => ['board_name', 'board'],
			'link' => '<a href="{scripturl}?board=%2$d.0">%1$s</a>',
			'text' => '<strong>%1$s</strong>',
		],
		'profile_msg' => [
			'required' => ['user_name', 'user_id'],
			'link' => '<a href="{scripturl}?action=profile;u=%2$d">%1$s</a>',
			'text' => '<strong>%1$s</strong>',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_alert' => 'id',
		'alert_time' => 'timestamp',
		'id_member' => 'member',
		'id_member_started' => 'member_started',
		'sender_id' => 'member_started',
		'sender_name' => 'member_name',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * The query_see_board data to use when checking access to content.
	 */
	protected static array $qb = [];

	/**
	 * @var array
	 *
	 * Whether self::$link_formats has been finalized.
	 */
	protected static bool $formats_finalized = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the alert.
	 * @param array $props Properties to set for this alert.
	 */
	public function __construct(int $id = 0, array $props = [])
	{
		$this->set($props);
		$this->id = $id;

		$this->setIcon();
		$this->show_links = (bool) ($this->extra['show_links'] ?? false);
		$this->initial_is_read = $this->is_read;

		if ($this->id === 0) {
			while (isset(self::$loaded[$this->id])) {
				$this->id--;
			}
		}

		self::$loaded[$this->id] = $this;

		if (!isset($this->visible)) {
			switch ($this->content_type) {
				case 'msg':
					self::checkMsgAccess([$this->id => $this->content_id], $this->member);
					break;

				case 'topic':
				case 'board':
					self::checkTopicAccess([$this->id => $this->content_id], $this->member);
					break;

				default:
					$this->visible = true;
					break;
			}

			// If not visible, remove from our static array.
			if (!$this->visible) {
				unset(self::$loaded[$this->id]);
			}
		}
	}

	/**
	 * Perpares this alert for use in the templates.
	 *
	 * @param bool $with_avatar Whether to load the avatar of the alert sender.
	 * @param bool $show_links Whether to show links in the constituent parts of
	 *    the alert message.
	 */
	public function format(bool $with_avatar = false, bool $show_links = false): void
	{
		Utils::$context['avatar_url'] = Config::$modSettings['avatar_url'];
		Lang::load('Alerts');

		if (!$this->visible) {
			return;
		}

		// Did a mod already take care of this one?
		if (!empty($this->text)) {
			return;
		}

		self::finalizeLinkFormats();

		// Are we forcing show_links to be true?
		if ($show_links) {
			$this->show_links = true;
		}

		// Make a nicely formatted version of the time.
		$this->time = Time::create('@' . $this->timestamp)->format();

		// Load the users we need.
		User::load(
			array_filter([
				$this->member_started,
				$this->content_type === 'profile' ? $this->content_id : null,
			]),
			User::LOAD_BY_ID,
			$with_avatar ? 'basic' : 'minimal',
		);

		// The info in extra might outdated if the topic was moved, the message's subject was changed, etc.
		if (!empty($this->content_data)) {
			$data = $this->content_data;

			// Make sure msg, topic, and board info are correct.
			$patterns = [];
			$replacements = [];

			foreach (['msg', 'topic', 'board'] as $item) {
				if (isset($data['id_' . $item])) {
					$separator = $item == 'msg' ? '=?' : '=';

					if (isset($this->extra['content_link']) && strpos($this->extra['content_link'], $item . $separator) !== false && strpos($this->extra['content_link'], $item . $separator . $data['id_' . $item]) === false) {
						$patterns[] = '/\b' . $item . $separator . '\d+/';
						$replacements[] = $item . $separator . $data['id_' . $item];
					}

					$this->extra[$item] = $data['id_' . $item];
				}
			}

			if (!empty($patterns)) {
				$this->extra['content_link'] = preg_replace($patterns, $replacements, $this->extra['content_link']);
			}

			// Make sure the subject is correct.
			if (isset($data['subject'])) {
				$this->extra['content_subject'] = $data['subject'];
			}

			// Keep track of this so we can use it below.
			if (isset($data['board_name'])) {
				$this->extra['board_name'] = $data['board_name'];
			}

			unset($this->content_data);
		}

		// Do we want to link to the topic in general or the new messages specifically?
		if (in_array($this->content_type, ['topic', 'board']) && in_array($this->content_action, ['reply', 'topic', 'unapproved_reply'])) {
			$this->extra['topic_suffix'] = 'new;topicseen#new';
		} elseif (isset($this->extra['topic'])) {
			$this->extra['topic_suffix'] = '0';
		}

		// Make sure profile alerts have what they need.
		if ($this->content_type === 'profile') {
			if (empty($this->extra['user_id'])) {
				$this->extra['user_id'] = $this->content_id;
			}

			if (isset(User::$loaded[$this->extra['user_id']])) {
				$this->extra['user_name'] = User::$loaded[$this->content_id]->name;
			}
		}

		// If we loaded the sender's profile, we may as well use it.
		if (isset(User::$loaded[$this->member_started])) {
			$this->member_name = User::$loaded[$this->member_started]->name;

			// If requested, include the sender's avatar data.
			if ($with_avatar) {
				$this->sender = User::$loaded[$this->member_started];
			}
		}

		// Next, build the message strings.
		foreach (self::$link_formats as $msg_type => $format_info) {
			// Get the values to use in the formatted string, in the right order.
			$msg_values = array_replace(
				array_fill_keys($format_info['required'], ''),
				array_intersect_key($this->extra, array_flip($format_info['required'])),
			);

			// Assuming all required values are present, build the message.
			if (!in_array('', $msg_values)) {
				$this->extra[$msg_type] = vsprintf(self::$link_formats[$msg_type][$this->show_links ? 'link' : 'text'], $msg_values);
			} elseif (in_array($msg_type, ['msg_msg', 'topic_msg', 'board_msg'])) {
				$this->extra[$msg_type] = Lang::$txt[$msg_type == 'board_msg' ? 'board_na' : 'topic_na'];
			} else {
				$this->extra[$msg_type] = '(' . Lang::$txt['not_applicable'] . ')';
			}
		}

		// Show the formatted time in alerts about subscriptions.
		if ($this->content_type == 'paidsubs' && isset($this->extra['end_time'])) {
			// If the subscription already expired, say so.
			if ($this->extra['end_time'] < time()) {
				$this->content_action = 'expired';
			}

			// Present a nicely formatted date.
			$this->extra['end_time'] = Time::create('@' . $this->extra['end_time'])->format();
		}

		// Now set the main URL that this alert should take the user to.
		$this->target_href = '';

		// Priority goes to explicitly specified links.
		if (isset($this->extra['content_link'])) {
			$this->target_href = $this->extra['content_link'];
		} elseif (isset($this->extra['report_link'])) {
			$this->target_href = Config::$scripturl . $this->extra['report_link'];
		}

		// Next, try determining the link based on the content action.
		if (empty($this->target_href) && in_array($this->content_action, ['register_approval', 'group_request', 'buddy_request'])) {
			switch ($this->content_action) {
				case 'register_approval':
					$this->target_href = Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
					break;

				case 'group_request':
					$this->target_href = Config::$scripturl . '?action=moderate;area=groups;sa=requests';
					break;

				case 'buddy_request':
					if (!empty($this->member_started)) {
						$this->target_href = Config::$scripturl . '?action=profile;u=' . $this->member_started;
					}
					break;

				default:
					break;
			}
		}

		// Or maybe we can determine the link based on the content type.
		if (empty($this->target_href) && in_array($this->content_type, ['msg', 'member', 'event'])) {
			switch ($this->content_type) {
				case 'msg':
					if (!empty($this->content_id)) {
						$this->target_href = Config::$scripturl . '?msg=' . $this->content_id;
					}
					break;

				case 'member':
					if (!empty($this->member_started)) {
						$this->target_href = Config::$scripturl . '?action=profile;u=' . $this->member_started;
					}
					break;

				case 'event':
					if (!empty($this->extra['event_id'])) {
						$this->target_href = Config::$scripturl . '?action=calendar;event=' . $this->extra['event_id'];
					}
					break;

				default:
					break;
			}
		}

		// Finally, set this alert's text string.
		$txt_key = 'alert_' . $this->content_type . '_' . $this->content_action;

		if (isset(Lang::$txt[$txt_key])) {
			$substitutions = [
				'{scripturl}' => Config::$scripturl,
				'{member_link}' => !empty($this->member_started) && $this->show_links ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->member_started . '">' . $this->member_name . '</a>' : '<strong>' . $this->member_name . '</strong>',
			];

			if (is_array($this->extra)) {
				foreach ($this->extra as $k => $v) {
					$substitutions['{' . $k . '}'] = $v;
				}
			}

			$this->text = strtr(Lang::$txt[$txt_key], $substitutions);
		}
	}

	/**
	 * Save this alert to the database.
	 *
	 * @param bool $update_count Whether to update the member's alert count.
	 *    Default: true.
	 */
	public function save(bool $update_count = true): void
	{
		// Don't save it if the member can't see it.
		if (empty($this->visible)) {
			return;
		}

		// Saving a new alert.
		if ($this->id <= 0) {
			$this->id = Db::$db->insert(
				'',
				'{db_prefix}user_alerts',
				[
					'alert_time' => 'int',
					'id_member' => 'int',
					'id_member_started' => 'int',
					'member_name' => 'string',
					'content_type' => 'string',
					'content_id' => 'int',
					'content_action' => 'string',
					'is_read' => 'int',
					'extra' => 'string',
				],
				[
					$this->timestamp,
					$this->member,
					$this->member_started,
					$this->member_name,
					$this->content_type,
					$this->content_id,
					$this->content_action,
					0,
					Utils::jsonEncode($this->extra),
				],
				['id_alert'],
				1,
			);

			// Update the keys in self::$loaded.
			self::$loaded = array_combine(
				array_map(fn ($alert) => $alert->id, self::$loaded),
				self::$loaded,
			);

			if ($update_count) {
				User::updateMemberData($this->member, ['alerts' => '+']);
			}
		}
		// Updating an existing alert.
		else {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}user_alerts
				SET
					alert_time = {int:timestamp},
					id_member = {int:member},
					id_member_started = {int:member_started},
					member_name = {string:member_name},
					content_type = {string:content_type},
					content_id = {int:content_id},
					content_action = {string:content_action},
					is_read = {int:is_read},
					extra = {string:extra}
				WHERE id_alert = {int:id}',
				[
					'id' => $this->id,
					'timestamp' => $this->timestamp,
					'member' => $this->member,
					'member_started' => $this->member_started,
					'member_name' => $this->member_name,
					'content_type' => $this->content_type,
					'content_id' => $this->content_id,
					'content_action' => $this->content_action,
					'is_read' => (int) $this->is_read,
					'extra' => Utils::jsonEncode($this->extra),
				],
			);

			// Has the is_read value changed since we loaded this alert?
			if ($update_count && $this->is_read !== $this->initial_is_read) {
				User::updateMemberData($this->member, ['alerts' => '+']);
			}
		}
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		if ($prop === 'extra') {
			$value = (array) Utils::jsonDecode($value ?? '', true);
		}

		$this->customPropertySet($prop, $value);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a new alert, saves it, and updates the alert count.
	 *
	 * @param array $props Properties to set for this alert.
	 * @return object An instance of this class.
	 */
	public static function create(array $props = []): object
	{
		$alert = new self(0, $props);
		$alert->save();

		return $alert;
	}

	/**
	 * Creates a batch of new alerts, saves them, and updates the alert counts.
	 *
	 * This has the same end result as calling Alert::create() for each set of
	 * properties in $props_batch, but this is more efficient internally.
	 *
	 * @param array $props_batch Multiple sets of $props.
	 * @return array An array of instances of this class.
	 */
	public static function createBatch(array $props_batch = []): array
	{
		$created = [];
		$inserts = [];
		$members = [];
		$possible_msgs = [];
		$possible_topics = [];

		// First, weed out any alerts that wouldn't be visible.
		foreach ($props_batch as &$props) {
			$members[] = $props['id_member'];

			switch ($props['content_type']) {
				case 'msg':
					$props['visible'] = false;
					$props['simple_access_check'] = true;
					$possible_msgs[$props['id_member']][$props['id_alert']] = $props['content_id'];
					break;

				case 'topic':
				case 'board':
					$props['visible'] = false;
					$props['simple_access_check'] = true;
					$possible_topics[$props['id_member']][$props['id_alert']] = $props['content_id'];
					break;

				default:
					$props['visible'] = true;
					break;
			}
		}

		$members = array_unique($members);

		foreach ($members as $memID) {
			foreach (['checkMsgAccess' => 'possible_msgs', 'checkTopicAccess' => 'possible_topics'] as $method => $variable) {
				$visibility = self::$method(${$variable}[$memID] ?? [], $memID, true);

				if (!empty($visibility)) {
					foreach ($props_batch as &$props) {
						if (isset($visibility[$props['id_alert']])) {
							$props['visible'] = $visibility[$props['id_alert']];
						}
					}
				}
			}
		}

		// Now create the alert objects and populate the database insert rows.
		foreach ($props_batch as $props) {
			if (!$props['visible']) {
				continue;
			}

			$alert = new self(0, $props);

			$created[$alert->id] = $alert;

			$inserts[$alert->id] = [
				$alert->timestamp,
				$alert->member,
				$alert->member_started,
				$alert->member_name,
				$alert->content_type,
				$alert->content_id,
				$alert->content_action,
				0,
				Utils::jsonEncode($alert->extra),
			];
		}

		// Insert the data into the database.
		$ids = Db::$db->insert(
			'',
			'{db_prefix}user_alerts',
			[
				'alert_time' => 'int',
				'id_member' => 'int',
				'id_member_started' => 'int',
				'member_name' => 'string',
				'content_type' => 'string',
				'content_id' => 'int',
				'content_action' => 'string',
				'is_read' => 'int',
				'extra' => 'string',
			],
			$inserts,
			['id_alert'],
			2,
		);

		// Map our temp IDs to the real IDs.
		$ids = array_combine(array_keys($inserts), $ids);

		// Change the IDs in the alert objects themselves.
		foreach ($ids as $temp => $real) {
			self::$loaded[$temp]->id = $real;
		}

		// Update the keys in self::$loaded.
		self::$loaded = array_combine(
			array_map(fn ($alert) => $alert->id, self::$loaded),
			self::$loaded,
		);

		// Update the keys in $created.
		$created = array_combine(
			array_map(fn ($alert) => $alert->id, $created),
			$created,
		);

		// Update the alert counts for the members.
		User::updateMemberData($members, ['alerts' => '+']);

		return $created;
	}

	/**
	 * Loads an arbitrary set of alerts.
	 *
	 * @param int|array $ids The IDs zero or more alerts.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @param bool $simple_access_check If true, do the simple access check.
	 *    If false, also load some additional info during the access check.
	 *    Default: false;
	 * @return array An array of instances of this class.
	 */
	public static function load(int|array $ids = [], array $query_customizations = [], bool $simple_access_check = false): array
	{
		Lang::load('Alerts');

		$loaded = [];
		$possible_msgs = [];
		$possible_topics = [];
		$members = [];

		// Are we being asked for some specific alerts?
		$ids = array_filter(array_map('intval', (array) $ids));

		// Can't load anything without some sort of criteria.
		if ($ids === [] && $query_customizations === []) {
			return [];
		}

		// Get the basic alert info.
		$selects = $query_customizations['selects'] ?? ['a.*'];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [];
		$order = $query_customizations['order'] ?? ['a.id_alert DESC'];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? min(!empty(Config::$modSettings['alerts_per_page']) ? Config::$modSettings['alerts_per_page'] : 1000, 1000);
		$params = $query_customizations['params'] ?? [];

		if (!empty($ids)) {
			$where[] = 'a.id_alert IN ({array_int:ids})';
			$params['ids'] = $ids;
		}

		foreach (self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$row['id_alert'] = (int) $row['id_alert'];

			$members[] = (int) $row['id_member'];

			// For some types, we need to check whether they can actually see the content.
			switch ($row['content_type']) {
				case 'msg':
					$row['visible'] = false;
					$row['simple_access_check'] = $simple_access_check;
					$possible_msgs[$row['id_member']][$row['id_alert']] = $row['content_id'];
					break;

				case 'topic':
				case 'board':
					$row['visible'] = false;
					$row['simple_access_check'] = $simple_access_check;
					$possible_topics[$row['id_member']][$row['id_alert']] = $row['content_id'];
					break;

				default:
					$row['visible'] = true;
					break;
			}

			$loaded[$row['id_alert']] = new self($row['id_alert'], $row);
		}

		foreach (array_unique($members) as $memID) {
			self::checkMsgAccess($possible_msgs[$memID] ?? [], $memID, $simple_access_check);
			self::checkTopicAccess($possible_topics[$memID] ?? [], $memID, $simple_access_check);
			self::deleteInvisible($loaded, $memID);
		}

		// Return the alerts we just loaded.
		return $loaded;
	}

	/**
	 * Loads the alerts a member currently has.
	 *
	 * @param int $memID The ID of the member.
	 * @param bool|array $to_fetch Alerts to fetch: true/false for all/unread,
	 *    or a list of one or more alert IDs.
	 * @param array $limit Maximum number of alerts to fetch (0 for no limit).
	 * @param array $offset Number of alerts to skip for pagination.
	 *    Ignored if $to_fetch is a list of IDs.
	 * @return array An array of instances of this class.
	 */
	public static function loadForMember(int $memID, bool|array $to_fetch = false, int $limit = 0, int $offset = 0, bool $simple_access_check = false): array
	{
		if (empty($memID)) {
			return [];
		}

		$loaded = [];

		// We only want alerts for this member.
		$query_customizations = [
			'where' => [
				'a.id_member = {int:id_member}',
			],
			'limit' => min(!empty($limit) ? $limit : (!empty(Config::$modSettings['alerts_per_page']) ? Config::$modSettings['alerts_per_page'] : 1000), 1000),
			'params' => [
				'id_member' => $memID,
			],
		];

		// False means get only the unread alerts.
		if ($to_fetch === false) {
			$query_customizations['where'][] = 'a.is_read = 0';
		}

		// Are we being asked for some specific alerts?
		$ids = is_bool($to_fetch) ? [] : array_filter(array_map('intval', (array) $to_fetch));

		// Don't reload unnecessarily.
		foreach (self::$loaded as $alert) {
			// Ignore alerts that belong to a different member.
			if ($alert->member !== $memID) {
				continue;
			}

			// Reload this alert if we need to redo the access check.
			if ((int) $simple_access_check < (int) ($alert->simple_access_check ?? 0)) {
				continue;
			}

			// At this point, we can exclude this alert from the ones we need to load.
			$loaded[$alert->id] = $alert;
		}

		$ids = array_diff($ids, array_keys($loaded));

		if (!empty($loaded)) {
			$query_customizations['where'][] = 'a.id_alert NOT IN ({array_int:already_loaded})';
			$query_customizations['params']['already_loaded'] = array_keys($loaded);
		}

		// Figure out the limit and offset.
		$offset = !empty($ids) ? 0 : max(0, (int) $offset);

		if (!empty($offset)) {
			$query_customizations['limit'] = $offset . ', ' . $query_customizations['limit'];
		}

		// Load the alerts we need.
		$loaded += self::load($ids, $query_customizations);

		krsort($loaded);

		return $loaded;
	}

	/**
	 * Convenience method to load and format the alerts a member currently has.
	 *
	 * @param int $memID The ID of the member.
	 * @param bool|array $to_fetch Alerts to fetch: true/false for all/unread,
	 *    or a list of one or more IDs.
	 * @param array $limit Maximum number of alerts to fetch (0 for no limit).
	 * @param array $offset Number of alerts to skip for pagination. Ignored if
	 *    $to_fetch is a list of IDs.
	 * @param bool $with_avatar Whether to load the avatar of the alert sender.
	 * @param bool $show_links Whether to show links in the constituent parts of
	 *    the alert message.
	 * @return array An array of information about the fetched alerts.
	 */
	public static function fetch(int $memID, bool|array $to_fetch = false, int $limit = 0, int $offset = 0, bool $with_avatar = false, bool $show_links = false): array
	{
		$loaded = self::loadForMember($memID, $to_fetch, $limit, $offset);

		// Historically, the following hook always expected self::$link_formats
		// to be in its raw form, so we reset it before calling the hook.
		self::resetLinkFormats();

		// Remember which alerts we've loaded, in case a hook changes that.
		$loaded_ids = array_keys($loaded);

		// Hooks might want to do something snazzy with their own content types,
		// including enforcing permissions if appropriate.
		IntegrationHook::call('integrate_fetch_alerts', [&$loaded, &self::$link_formats]);

		// Did a hook remove some of the alerts we loaded?
		if ($loaded_ids !== array_keys($loaded)) {
			// Remove those alerts from our static array as well.
			foreach (array_diff($loaded_ids, array_keys($loaded)) as $unloaded) {
				unset(self::$loaded[$unloaded]);
			}
		}

		// Prepare them for the templates.
		foreach ($loaded as $alert) {
			$alert->format($with_avatar, $show_links);
		}

		return $loaded;
	}

	/**
	 * Counts how many alerts a user has, either unread or all.
	 *
	 * @param int $memID The user ID.
	 * @param bool $unread Whether to only count unread alerts.
	 * @return int The number of alerts.
	 */
	public static function count(int $memID, bool $unread = false): int
	{
		// We can't use db_num_rows here, as we have to determine which boards
		// the user can see. Because of that, counting requires doing all the
		// same queries and checks as loading.
		self::loadForMember($memID, !$unread, 0, 0, true);

		$count = 0;

		foreach (self::$loaded as $alert) {
			if ($alert->member !== $memID) {
				continue;
			}

			if ($unread && $alert->is_read > 0) {
				continue;
			}

			$count++;
		}

		return $count;
	}

	/**
	 * Marks a group of alerts as un/read.
	 *
	 * @param int|array $members Members whose alerts should be updated.
	 * @param array|int $to_mark The IDs of one or more alerts.
	 * @param bool $read To mark as read or unread. True = read, false = unread.
	 */
	public static function mark(int|array $members, array|int $to_mark, bool $read): void
	{
		$members = array_filter((array) $members);
		$to_mark = array_filter((array) $to_mark);

		if (empty($to_mark) || empty($members)) {
			return;
		}

		$time = $read ? time() : 0;

		Db::$db->query(
			'',
			'UPDATE {db_prefix}user_alerts
			SET is_read = {int:read}
			WHERE id_alert IN ({array_int:to_mark})',
			[
				'read' => $time,
				'to_mark' => $to_mark,
			],
		);

		// First, make sure that all the loaded alerts have the right value.
		foreach ($to_mark as $id) {
			if (isset(self::$loaded[$id])) {
				self::$loaded[$id]->is_read = $time;
			}
		}

		// Now update the members' alert counts in the database.
		User::updateMemberData($members, ['alerts' => $read ? '-' : '+']);
	}

	/**
	 * Marks all of a member's alerts as un/read.
	 *
	 * @param int|array $members Members whose alerts should be updated.
	 * @param bool $read To mark as read or unread. True = read, false = unread.
	 */
	public static function markAll(int|array $members, bool $read): void
	{
		$members = array_filter((array) $members);

		if (empty($members)) {
			return;
		}

		$time = $read ? time() : 0;

		Db::$db->query(
			'',
			'UPDATE {db_prefix}user_alerts
			SET is_read = {int:read}
			WHERE id_member IN ({array_int:members})
				AND {raw:condition}',
			[
				'read' => $time,
				'members' => $members,
				'condition' => $read ? 'is_read = 0' : 'is_read != 0',
			],
		);

		if (Db::$db->affected_rows() > 0) {
			// First, make sure that all the loaded alerts have the right value.
			foreach (self::$loaded as $alert) {
				if (in_array($alert->member, $members) && (!$read || empty($alert->is_read))) {
					$alert->is_read = $time;
				}
			}

			// Now update the members' alert counts in the database.
			User::updateMemberData($members, ['alerts' => $read ? 0 : '+']);
		}
	}

	/**
	 * Marks alerts as un/read based on a custom query.
	 *
	 * @param array $where Conditions for the WHERE clause of the SQL query.
	 * @param array $params Parameters to substitute into the SQL query.
	 * @param bool $read To mark as read or unread. True = read, false = unread.
	 */
	public static function markWhere(array $where, array $params, bool $read): void
	{
		$ids = [];
		$members = [];

		$selects = ['a.id_alert', 'a.id_member'];

		// Do we need to add the condition for is_read status?
		$has_read_condition = false;

		foreach ($where as &$condition) {
			$condition = trim($condition);

			if (strpos($condition, 'is_read ') === 0) {
				$has_read_condition = true;
			}
		}

		if (!$has_read_condition) {
			$where[] = 'is_read ' . ($read ? '' : '!') . '= 0';
		}

		// Find the alerts that match the conditions.
		foreach (self::queryData($selects, $params, [], $where) as $row) {
			$ids[] = (int) $row['id_alert'];
			$members[] = (int) $row['id_member'];
		}

		self::mark($members, $ids, $read);
	}

	/**
	 * Deletes alerts by ID.
	 *
	 * @param int|array $ids The IDs of one or more alerts.
	 * @param int|array $members Members whose alert counts should be updated.
	 */
	public static function delete(int|array $ids, int|array $members = []): void
	{
		if (empty($ids)) {
			return;
		}

		$ids = (array) $ids;
		$members = (array) $members;

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_alerts
			WHERE id_alert IN ({array_int:ids})',
			[
				'ids' => $ids,
			],
		);

		foreach ($ids as $id) {
			unset(self::$loaded[$id]);
		}

		// Gotta know how many unread alerts are left.
		User::updateMemberData($members, ['alerts' => '-']);
	}

	/**
	 * Deletes the alerts that a member has already read.
	 *
	 * @param int $memID The member ID. Defaults to the current user's ID.
	 *    If set to -1, will purge read alerts for all members.
	 * @param int $before Only purge alerts read before this UNIX timestamp.
	 *    If zero or negative, current time will be used. Default: zero.
	 */
	public static function purge(int $memID = 0, int $before = 0): void
	{
		$memID = !empty($memID) ? $memID : User::$me->id;
		$before = $before > 0 ? $before : time();

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_alerts
			WHERE is_read > 0
				AND is_read < {int:before}' . ($memID > 0 ? '
				AND id_member = {int:memID}' : ''),
			[
				'before' => $before,
				'memID' => $memID,
			],
		);
	}

	/**
	 * Deletes alerts based on a custom query.
	 *
	 * @param array $where Conditions for the WHERE clause of the SQL query.
	 * @param array $params Parameters to substitute into the SQL query.
	 * @param bool $read To mark as read or unread. True = read, false = unread.
	 */
	public static function deleteWhere(array $where, array $params): void
	{
		$ids = [];
		$members = [];

		$selects = ['a.id_alert', 'a.id_member'];

		// Find the alerts that match the conditions.
		foreach (self::queryData($selects, $params, [], $where) as $row) {
			$ids[] = (int) $row['id_alert'];
			$members[] = (int) $row['id_member'];
		}

		self::delete($ids, $members);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets the icon for this alert.
	 */
	protected function setIcon(): void
	{
		switch ($this->content_type) {
			case 'topic':
			case 'board':
				{
					switch ($this->content_action) {
						case 'reply':
						case 'topic':
							$class = 'main_icons posts';
							break;

						case 'move':
							$src = Theme::$current->settings['images_url'] . '/post/moved.png';
							break;

						case 'remove':
							$class = 'main_icons delete';
							break;

						case 'lock':
						case 'unlock':
							$class = 'main_icons lock';
							break;

						case 'sticky':
						case 'unsticky':
							$class = 'main_icons sticky';
							break;

						case 'split':
							$class = 'main_icons split_button';
							break;

						case 'merge':
							$class = 'main_icons merge';
							break;

						case 'unapproved_topic':
						case 'unapproved_post':
							$class = 'main_icons post_moderation_moderate';
							break;

						default:
							$class = 'main_icons posts';
							break;
					}
				}
				break;

			case 'msg':
				{
					switch ($this->content_action) {
						case 'like':
							$class = 'main_icons like';
							break;

						case 'mention':
							$class = 'main_icons im_on';
							break;

						case 'quote':
							$class = 'main_icons quote';
							break;

						case 'unapproved_attachment':
							$class = 'main_icons post_moderation_attach';
							break;

						case 'report':
						case 'report_reply':
							$class = 'main_icons post_moderation_moderate';
							break;

						default:
							$class = 'main_icons posts';
							break;
					}
				}
				break;

			case 'member':
				{
					switch ($this->content_action) {
						case 'register_standard':
						case 'register_approval':
						case 'register_activation':
							$class = 'main_icons members';
							break;

						case 'report':
						case 'report_reply':
							$class = 'main_icons members_watched';
							break;

						case 'buddy_request':
							$class = 'main_icons people';
							break;

						case 'group_request':
							$class = 'main_icons members_request';
							break;

						default:
							$class = 'main_icons members';
							break;
					}
				}
				break;

			case 'groupr':
				$class = 'main_icons members_request';
				break;

			case 'event':
				$class = 'main_icons calendar';
				break;

			case 'paidsubs':
				$class = 'main_icons paid';
				break;

			case 'birthday':
				$src = Theme::$current->settings['images_url'] . '/cake.png';
				break;

			default:
				$class = 'main_icons alerts';
				break;
		}

		if (isset($class)) {
			$this->icon = '<span class="alert_icon ' . $class . '"></span>';
		} elseif (isset($src)) {
			$this->icon = '<img class="alert_icon" src="' . $src . '">';
		} else {
			$this->icon = '';
		}

		IntegrationHook::call('integrate_alert_icon', [&$this->icon, (array) $this]);
	}
	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Substitute Config::$scripturl into the link formats.
	 */
	protected static function finalizeLinkFormats(): void
	{
		if (self::$formats_finalized) {
			return;
		}

		self::$link_formats = array_map(
			function ($format) {
				$format['link'] = str_replace('{scripturl}', Config::$scripturl, $format['link']);
				$format['text'] = str_replace('{scripturl}', Config::$scripturl, $format['text']);

				return $format;
			},
			self::$link_formats,
		);
	}

	/**
	 * Resets the link formats to their defaults.
	 */
	protected static function resetLinkFormats(): void
	{
		$class_vars = get_class_vars(__CLASS__);
		self::$link_formats = $class_vars['link_formats'];
		self::$formats_finalized = false;
	}

	/**
	 * Sets the query_see_board values to use when checking access to content.
	 */
	protected static function setQb(int $memID): void
	{
		if (!empty(self::$qb[$memID])) {
			return;
		}

		if ((User::$me->id ?? NAN) != $memID || !isset(User::$me->query_see_board)) {
			self::$qb[$memID] = User::buildQueryBoard($memID);
		} else {
			self::$qb[$memID]['query_see_board'] = '{query_see_board}';
			self::$qb[$memID]['query_see_topic_board'] = '{query_see_topic_board}';
			self::$qb[$memID]['query_see_message_board'] = '{query_see_message_board}';
		}
	}

	/**
	 * Checks whether a member can see the messages that some alerts refer to.
	 *
	 * @param array $possible_msgs Key-value pairs of alert IDs and message IDs.
	 * @param int $memID ID of the member.
	 * @param bool $simple If true, do nothing beyond checking the access.
	 *    If false, also get some info about the message in question.
	 *    Default: false.
	 * @return array Key-value pairs of alert IDs and visibility status.
	 */
	protected static function checkMsgAccess(array $possible_msgs, int $memID, bool $simple = false): array
	{
		if (empty($possible_msgs)) {
			return [];
		}

		self::setQb($memID);

		$visibility = [];
		$flipped_msgs = [];

		foreach ($possible_msgs as $id_alert => $id_msg) {
			$visibility[$id_alert] = false;

			if (!isset($flipped_msgs[$id_msg])) {
				$flipped_msgs[$id_msg] = [];
			}

			$flipped_msgs[$id_msg][] = $id_alert;
		}

		if ($simple) {
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg
				FROM {db_prefix}messages AS m
				WHERE ' . self::$qb[$memID]['query_see_message_board'] . '
					AND m.id_msg IN ({array_int:msgs})',
				[
					'msgs' => $possible_msgs,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg, m.id_topic, m.subject, b.id_board, b.name AS board_name
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE m.id_msg IN ({array_int:msgs})
					AND ' . self::$qb[$memID]['query_see_board'] . '
				ORDER BY m.id_msg',
				[
					'msgs' => $possible_msgs,
				],
			);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			foreach ($flipped_msgs[$row['id_msg']] as $id_alert) {
				$visibility[$id_alert] = true;

				if (isset(self::$loaded[$id_alert])) {
					if (!$simple) {
						self::$loaded[$id_alert]->content_data = $row;
					}

					self::$loaded[$id_alert]->visible = true;
				}
			}
		}
		Db::$db->free_result($request);

		return $visibility;
	}

	/**
	 * Checks whether a member can see the topics that some alerts refer to.
	 *
	 * @param int $memID ID of the member.
	 * @param bool $simple If true, do nothing beyond checking the access.
	 *    If false, also get some info about the topic in question.
	 *    Default: false.
	 * @param array $possible_msgs Key-value pairs of alert IDs and topic IDs.
	 * @return array Key-value pairs of alert IDs and visibility status.
	 */
	protected static function checkTopicAccess($possible_topics, int $memID, bool $simple = false): array
	{
		if (empty($possible_topics)) {
			return [];
		}

		self::setQb($memID);

		$visibility = [];
		$flipped_topics = [];

		foreach ($possible_topics as $id_alert => $id_topic) {
			$visibility[$id_alert] = false;

			if (!isset($flipped_topics[$id_topic])) {
				$flipped_topics[$id_topic] = [];
			}

			$flipped_topics[$id_topic][] = $id_alert;
		}

		if ($simple) {
			$request = Db::$db->query(
				'',
				'SELECT t.id_topic
				FROM {db_prefix}topics AS t
				WHERE ' . self::$qb[$memID]['query_see_topic_board'] . '
					AND t.id_topic IN ({array_int:topics})',
				[
					'topics' => $possible_topics,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg, t.id_topic, m.subject, b.id_board, b.name AS board_name
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
					INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				WHERE t.id_topic IN ({array_int:topics})
					AND ' . self::$qb[$memID]['query_see_board'],
				[
					'topics' => $possible_topics,
				],
			);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			foreach ($flipped_topics[$row['id_topic']] as $id_alert) {
				$visibility[$id_alert] = true;

				if (isset(self::$loaded[$id_alert])) {
					if (!$simple) {
						self::$loaded[$id_alert]->content_data = $row;
					}

					self::$loaded[$id_alert]->visible = true;
				}
			}
		}
		Db::$db->free_result($request);

		return $visibility;
	}

	/**
	 * Deletes alerts that a user cannot see.
	 *
	 * @param array &$alerts An array of instances of this class.
	 * @param int $memID The ID of the user whose alerts we should check.
	 *    Any alerts belonging to other users will be ignored.
	 */
	protected static function deleteInvisible(array &$alerts, int $memID): void
	{
		$deletes = [];
		$num_unread_deletes = 0;

		foreach ($alerts as $id_alert => $alert) {
			// Only act on the ones that belong to the given member.
			if ($memID !== $alert->member) {
				continue;
			}

			if (!$alert->visible) {
				if (!$alert->is_read) {
					$num_unread_deletes++;
				}

				unset($alerts[$id_alert], self::$loaded[$id_alert]);
				$deletes[] = $id_alert;
			}
		}

		// Delete these orphaned, invisible alerts, otherwise they might hang around forever.
		// This can happen if they are deleted or moved to a board this user cannot access.
		// Note that unread alerts are never purged.
		if (!empty($deletes)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}user_alerts
				WHERE id_alert IN ({array_int:alerts})',
				[
					'alerts' => $deletes,
				],
			);
		}

		// One last thing: tweak counter on member record.
		// Do it directly to avoid creating a loop in User::updateMemberData().
		if ($num_unread_deletes > 0) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET alerts = GREATEST({int:unread_deletes}, alerts) - {int:unread_deletes}
				WHERE id_member = {int:member}',
				[
					'unread_deletes' => $num_unread_deletes,
					'member' => $memID,
				],
			);
		}
	}

	/**
	 * Generator that runs queries about alert data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}members AS mem ON (a.id_member_started = mem.id_member)'
	 *    Note that 'FROM {db_prefix}user_alerts AS a' is always part of the query.
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
			FROM {db_prefix}user_alerts AS a' . (empty($joins) ? '' : '
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
if (is_callable(__NAMESPACE__ . '\\Alert::exportStatic')) {
	Alert::exportStatic();
}

?>