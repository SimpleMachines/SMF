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

namespace SMF\PersonalMessage;

use SMF\Actions\Notify;
use SMF\Actions\PersonalMessage as PMAction;
use SMF\ArrayAccessHelper;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Editor;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Mail;
use SMF\Menu;
use SMF\Msg;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Represents a single personal message.
 */
class PM implements \ArrayAccess
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
			'old' => 'old',
			'compose' => 'compose',
			'compose2' => 'compose2',
			'send' => 'sendpm',
			'delete' => 'deleteMessages',
			'markRead' => 'markMessages',
			'getLatest' => 'getLatest',
			'getRecent' => 'getRecent',
			'countSent' => 'countSent',
			'reportErrors' => 'messagePostError',
			'isAccessible' => 'isAccessiblePM',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This PM's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * ID number of the PM that started the conversation that this is part of.
	 */
	public int $head;

	/**
	 * @var int
	 *
	 * ID number of the author of this PM.
	 */
	public int $member_from;

	/**
	 * @var string
	 *
	 * Name of the author of this PM.
	 */
	public string $from_name;

	/**
	 * @var bool
	 *
	 * Whether the author of this PM has deleted it.
	 */
	public bool $deleted_by_sender;

	/**
	 * @var int
	 *
	 * UNIX timestamp when this PM was sent.
	 */
	public int $msgtime;

	/**
	 * @var string
	 *
	 * Subject line of this PM.
	 */
	public string $subject;

	/**
	 * @var string
	 *
	 * Body text of this PM.
	 */
	public string $body;

	/**
	 * @var array
	 *
	 * Data about received copies of this PM.
	 */
	public array $received = [];

	/**
	 * @var string
	 *
	 * Name of the folder that holds this PM for the current user.
	 * Either 'inbox' or 'sent'.
	 */
	public string $folder;

	/**
	 * @var array
	 *
	 * Formatted versions of this PM's properties, suitable for display.
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
	 * Variable to hold the PM::get() generator.
	 * If there are no messages, will be an empty array.
	 */
	public static $getter;

	/**
	 * @var object|array
	 *
	 * Variable to hold the PM::get() generator for the subject list.
	 * If there are no messages, will be an empty array.
	 */
	public static $subject_getter;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_pm' => 'id',
		'id_pm_head' => 'head',
		'id_member_from' => 'member_from',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * Database query used in PM::queryData().
	 */
	protected static $messages_request;

	/**
	 * @var bool
	 *
	 * If true, PM::get() will not destroy instances after yielding them.
	 * This is used internally by PM::load().
	 */
	protected static bool $keep_all = false;

	/**
	 * @var array
	 *
	 * Membergroup message limits.
	 */
	protected static $message_limit_cache = [];

	/**
	 * @var array
	 *
	 * Holds the results of PM::getRecent().
	 *
	 * The keys of this array are string representations of the parameters that
	 * PM::getRecent() was called with. The values are lists of PM IDs that
	 * match those parameters.
	 */
	protected static array $recent = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the personal message.
	 * @param array $props Properties to set for this message.
	 */
	public function __construct(int $id, array $props = [])
	{
		$this->id = $id;
		$this->set($props);
		$this->received = Received::loadByPm($this->id);
		$this->folder = $this->member_from !== User::$me->id ? 'inbox' : 'sent';
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
		// If these context vars haven't already been set, do it now.
		Utils::$context['start'] = (int) (Utils::$context['start'] ?? $_GET['start'] ?? 0);
		Utils::$context['current_label_id'] = Utils::$context['current_label_id'] ?? -1;
		Utils::$context['display_mode'] = Utils::$context['display_mode'] ?? User::$me->pm_prefs & 3;
		Utils::$context['can_send_pm'] = Utils::$context['can_send_pm'] ?? User::$me->allowedTo('pm_send');

		// Use '(no subject)' if none was specified.
		$this->subject = $this->subject == '' ? Lang::$txt['no_subject'] : $this->subject;

		if (!empty($this->member_from) && !isset(User::$loaded[$this->member_from])) {
			User::load($this->member_from);
		}

		// Load the author's information - if it's not there, load the guest information.
		if (!isset(User::$loaded[$this->member_from])) {
			$author['id'] = 0;
			$author['name'] = $this->from_name;

			// Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
			$author['group'] = $this->from_name == Utils::$context['forum_name_html_safe'] ? '' : Lang::$txt['guest_title'];
			$author['link'] = $this->from_name;
			$author['email'] = '';
			$author['show_email'] = false;
			$author['is_guest'] = true;
		} else {
			$author = User::$loaded[$this->member_from]->format(true);

			$author['can_view_profile'] = User::$me->allowedTo('profile_view') || ($this->member_from == User::$me->id && !User::$me->is_guest);

			$author['can_see_warning'] = !isset(Utils::$context['disabled_fields']['warning_status']) && $author['warning_status'] && (User::$me->can_mod || (!empty(Config::$modSettings['warning_show']) && (Config::$modSettings['warning_show'] > 1 || $this->member_from == User::$me->id)));

			// Show the email if it's your own PM
			$author['show_email'] |= $this->member_from == User::$me->id;
		}

		$author['show_profile_buttons'] = Config::$modSettings['show_profile_buttons'] && (!empty($author['can_view_profile']) || (!empty($author['website']['url']) && !isset(Utils::$context['disabled_fields']['website'])) || $author['show_email'] || Utils::$context['can_send_pm']);

		Label::load();

		// We need basic info about the recipients, too.
		$recipients = [
			'to' => [],
			'bcc' => [],
		];

		$labels = [];
		$is_unread = false;
		$is_replied_to = false;

		foreach ($this->received as $member => $received_copy) {
			if (empty($format_options['no_bcc']) || !$received_copy->bcc) {
				$recipients[$received_copy->bcc ? 'bcc' : 'to'][] = empty($received_copy->name) ? Lang::$txt['guest_title'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $received_copy->member . '">' . $received_copy->name . '</a>';
			}

			if ($received_copy->member === User::$me->id) {
				$is_unread = $received_copy->unread;
				$is_replied_to = $received_copy->replied;

				foreach ($received_copy->labels as $label_id) {
					$labels[$label_id] = Label::$loaded[$label_id];
				}
			}
		}

		// Sent to a member that no longer exists?
		if ($this->member_from === User::$me->id && empty($this->received)) {
			$recipients['to'] = [Lang::$txt['guest_title']];
		}

		// Censor all the important text...
		Lang::censorText($this->body);
		Lang::censorText($this->subject);

		// Any custom profile fields?
		$custom_fields = [];

		if (empty($format_options['no_custom_fields']) && !empty($author['custom_fields'])) {
			foreach ($author['custom_fields'] as $custom) {
				$custom_fields[Utils::$context['cust_profile_fields_placement'][$custom['placement']]][] = $custom;
			}
		}

		$label_ids = array_diff(array_keys($labels), [-1]);

		$href = Config::$scripturl . '?action=pm;f=' . $this->folder . (Utils::$context['current_label_id'] != -1 && !empty($label_ids) && in_array(Utils::$context['current_label_id'], $label_ids) ? ';l=' . Utils::$context['current_label_id'] : '') . ';pmid=' . $this->id . '#msg' . $this->id;

		$number_recipients = count($recipients['to']);

		$this->formatted = [
			'id' => $this->id,
			'member' => $author,
			'subject' => $this->subject,
			'body' => BBCodeParser::load()->parse($this->body, true, 'pm' . $this->id),
			'time' => Time::create('@' . $this->msgtime)->format(),
			'timestamp' => $this->msgtime,
			'counter' => $counter,
			'recipients' => $recipients,
			'number_recipients' => $number_recipients,
			'labels' => $labels,
			'fully_labeled' => count($labels) == count(Label::$loaded),
			'is_replied_to' => $is_replied_to,
			'is_unread' => $is_unread,
			'is_selected' => !empty($temp_pm_selected) && in_array($this->id, $temp_pm_selected),
			'is_message_author' => $this->member_from == User::$me->id,
			'is_head' => $this->id === $this->head,
			'href' => $href,
			'link' => '<a href="' . $href . '">' . $this->subject . '</a>',
			'can_report' => !empty(Config::$modSettings['enableReportPM']),
			'can_see_ip' => User::$me->allowedTo('moderate_forum') || ($this->member_from == User::$me->id && !empty(User::$me->id)),
			'custom_fields' => $custom_fields,
			'quickbuttons' => [
				'reply_to_all' => [
					'label' => Lang::$txt['reply_to_all'],
					'href' => Config::$scripturl . '?action=pm;sa=send;f=' . $this->folder . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '') . ';pmsg=' . $this->id . ($this->member_from != User::$me->id ? ';quote' : '') . ';u=all',
					'icon' => 'reply_all_button',
					'show' => Utils::$context['can_send_pm'] && !$author['is_guest'] && ($number_recipients > 1 || $this->member_from == User::$me->id),
				],
				'reply' => [
					'label' => Lang::$txt['reply'],
					'href' => Config::$scripturl . '?action=pm;sa=send;f=' . $this->folder . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '') . ';pmsg=' . $this->id . ';u=' . $this->member_from,
					'icon' => 'reply_button',
					'show' => Utils::$context['can_send_pm'] && !$author['is_guest'] && $this->member_from != User::$me->id,
				],
				'quote' => [
					'label' => Lang::$txt['quote_action'],
					'href' => Config::$scripturl . '?action=pm;sa=send;f=' . $this->folder . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '') . ';pmsg=' . $this->id . ';quote' . ($number_recipients > 1 || $this->member_from == User::$me->id ? ';u=all' : (!$author['is_guest'] ? ';u=' . $this->member_from : '')),
					'icon' => 'quote',
					'show' => Utils::$context['can_send_pm'],
				],
				'delete' => [
					'label' => Lang::$txt['delete'],
					'href' => Config::$scripturl . '?action=pm;sa=pmactions;pm_actions%5b' . $this->id . '%5D=delete;f=' . $this->folder . ';start=' . Utils::$context['start'] . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Utils::JavaScriptEscape(Lang::$txt['remove_message_question']) . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
				],
				'more' => [
					'report' => [
						'label' => Lang::$txt['pm_report_to_admin'],
						'href' => Config::$scripturl . '?action=pm;sa=report;l=' . Utils::$context['current_label_id'] . ';pmsg=' . $this->id,
						'icon' => 'error',
						'show' => !empty(Config::$modSettings['enableReportPM']),
					],
				],
				'quickmod' => [
					'class' => 'inline_mod_check',
					'content' => '<input type="checkbox" name="pms[]" id="deletedisplay' . $this->id . '" value="' . $this->id . '" onclick="document.getElementById(\'deletelisting' . $this->id . '\').checked = this.checked;">',
					'show' => Utils::$context['display_mode'] == PMAction::VIEW_ALL,
				],
			],
		];

		return $this->formatted;
	}

	/**
	 * Checks whether the current user can see this personal message.
	 *
	 * @return bool
	 */
	public function canAccess(string $folders = 'both'): bool
	{
		if ($folders === 'in_or_outbox') {
			$folders = 'both';
		}

		if ($folders === 'outbox') {
			$folders = 'sent';
		}

		$valid_for = [
			'inbox' => false,
			'sent' => false,
			'both' => false,
		];

		if ($this->member_from === User::$me->id) {
			$valid_for['sent'] = !$this->deleted_by_sender;
		} else {
			foreach ($this->received as $received) {
				if ($received->member === User::$me->id) {
					$valid_for['inbox'] = !$received->deleted;
					break;
				}
			}
		}

		$valid_for['both'] = $valid_for['inbox'] | $valid_for['sent'];

		return $valid_for[$folders];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads personal messages by ID number.
	 *
	 * Note: if you are loading a group of messages so that you can iterate over
	 * them, consider using PM::get() rather than PM::load().
	 *
	 * @param int|array $ids The ID numbers of one or more personal messages.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded messages.
	 */
	public static function load($ids, array $query_customizations = []): array
	{
		$loaded = [];

		$ids = (array) $ids;

		// Have we already loaded these?
		foreach ($ids as $key => $id) {
			if (isset(self::$loaded[$id])) {
				$loaded[$id] = self::$loaded[$id];
				unset($ids[$key]);
			}
		}

		if (empty($ids)) {
			return $loaded;
		}

		// Loading is similar to getting, except that we keep all instances and
		// then return them all at once.
		self::$keep_all = true;

		foreach (self::get($ids, $query_customizations) as $pm) {
			$loaded[$pm->id] = $pm;
		}

		self::$keep_all = false;

		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * Similar to PM::load(), except that this method progressively creates and
	 * destroys instances of this class for each message, so that only one
	 * instance ever exists at a time.
	 *
	 * @param int|array $ids The ID numbers of one or more personal messages.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<array> Iterating over result gives PM instances.
	 */
	public static function get($ids, array $query_customizations = [])
	{
		$ids = (array) $ids;

		// For efficiency, load all of the received statuses at once.
		Received::loadByPm($ids);

		// Asked for single PM that has already been loaded? Just yield and return.
		if (count($ids) === 1 && isset(self::$loaded[reset($ids)])) {
			yield self::$loaded[reset($ids)];

			return;
		}

		$selects = $query_customizations['selects'] ?? [
			'pm.*',
		];

		$joins = $query_customizations['joins'] ?? [];

		$where = $query_customizations['where'] ?? [
			'pm.id_pm IN ({array_int:ids})',
		];

		$order = $query_customizations['order'] ?? [];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? count($ids);
		$params = $query_customizations['params'] ?? [];

		// There will never be an ID 0, but SMF doesn't like empty arrays when you tell it to expect an array of integers...
		$params['ids'] = empty($ids) ? [0] : array_filter(array_unique(array_map('intval', $ids)));

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$id = (int) $row['id_pm'];

			yield (new self($id, $row));

			if (!self::$keep_all) {
				unset(self::$loaded[$id]);
			}
		}
	}

	/**
	 * Returns the IDs of personal messages sent before a given time.
	 *
	 * @param int $time A Unix timestamp.
	 * @return array The IDs of personal messages sent before $time.
	 */
	public static function old(int $time): array
	{
		$ids = [];

		$selects = [
			'pm.id_pm',
		];

		$joins = [];

		$where = [
			'pm.id_member_from = {int:me}',
			'pm.deleted_by_sender = {int:not_deleted}',
			'pm.msgtime < {int:time}',
		];

		$params = [
			'me' => User::$me->id,
			'not_deleted' => 0,
			'time' => $time,
		];

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$ids[] = $row['id_pm'];
		}

		return $ids;
	}

	/**
	 * Shows the form for composing a personal message.
	 */
	public static function compose(): void
	{
		User::$me->isAllowedTo('pm_send');

		Lang::load('PersonalMessage');

		// Just in case it was loaded from somewhere else.
		Theme::loadTemplate('PersonalMessage');
		Theme::loadJavaScriptFile('PersonalMessage.js', ['defer' => false, 'minimize' => true], 'smf_pms');
		Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');

		if (Utils::$context['drafts_autosave']) {
			Theme::loadJavaScriptFile('drafts.js', ['defer' => false, 'minimize' => true], 'smf_drafts');
		}

		Utils::$context['sub_template'] = 'send';

		// Extract out the spam settings - cause it's neat.
		list(Config::$modSettings['max_pm_recipients'], Config::$modSettings['pm_posts_verification'], Config::$modSettings['pm_posts_per_hour']) = explode(',', Config::$modSettings['pm_spam_settings']);

		// Set the title...
		Utils::$context['page_title'] = Lang::$txt['send_message'];

		Utils::$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);

		// Check whether we've gone over the limit of messages we can send per hour.
		if (!empty(Config::$modSettings['pm_posts_per_hour']) && !User::$me->allowedTo(['admin_forum', 'moderate_forum', 'send_mail']) && User::$me->mod_cache['bq'] == '0=1' && User::$me->mod_cache['gq'] == '0=1') {
			// How many messages have they sent this last hour?
			$request = Db::$db->query(
				'',
				'SELECT COUNT(pr.id_pm) AS post_count
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
				WHERE pm.id_member_from = {int:me}
					AND pm.msgtime > {int:msgtime}',
				[
					'me' => User::$me->id,
					'msgtime' => time() - 3600,
				],
			);
			list($postCount) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (!empty($postCount) && $postCount >= Config::$modSettings['pm_posts_per_hour']) {
				ErrorHandler::fatalLang('pm_too_many_per_hour', true, [Config::$modSettings['pm_posts_per_hour']]);
			}
		}

		// Quoting/Replying to a message?
		if (!empty($_REQUEST['pmsg'])) {
			$pmsg = (int) $_REQUEST['pmsg'];

			self::load($pmsg);

			$pm = self::$loaded[$pmsg];

			// Make sure this is yours.
			if (!$pm->canAccess('both')) {
				ErrorHandler::fatalLang('pm_not_yours', false);
			}

			// Format it all.
			$pm->format();

			// Add 'Re: ' to it....
			if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix'))) {
				if (Lang::$default === User::$me->language) {
					Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
				} else {
					Lang::load('index', Lang::$default, false);
					Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
					Lang::load('index');
				}

				CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
			}

			$form_subject = $pm->formatted['subject'];

			if (Utils::$context['reply'] && trim(Utils::$context['response_prefix']) != '' && Utils::entityStrpos($form_subject, trim(Utils::$context['response_prefix'])) !== 0) {
				$form_subject = Utils::$context['response_prefix'] . $form_subject;
			}

			if (isset($_REQUEST['quote'])) {
				// Remove any nested quotes and <br>...
				$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $pm->body);

				if (!empty(Config::$modSettings['removeNestedQuotes'])) {
					$form_message = preg_replace(['~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'], '', $form_message);
				}

				if (empty($pm->member_from)) {
					$form_message = '[quote author=&quot;' . $pm->formatted['member']['name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
				} else {
					$form_message = '[quote author=' . $pm->formatted['member']['name'] . ' link=action=profile;u=' . $pm->member_from . ' date=' . $pm->msgtime . ']' . "\n" . $form_message . "\n" . '[/quote]';
				}
			} else {
				$form_message = '';
			}

			// Set up the quoted message array.
			Utils::$context['quoted_message'] = $pm->formatted;
		} else {
			Utils::$context['quoted_message'] = false;
			$form_subject = '';
			$form_message = '';
		}

		Utils::$context['recipients'] = [
			'to' => [],
			'bcc' => [],
		];

		// Sending by ID?  Replying to all?  Fetch the real_name(s).
		if (isset($_REQUEST['u'])) {
			if ($_REQUEST['u'] != 'all') {
				$_REQUEST['u'] = array_unique(array_map('intval', explode(',', $_REQUEST['u'])));
			}

			if (isset($pm)) {
				if ($pm->member_from != User::$me->id && ($_REQUEST['u'] == 'all' || in_array($pm->member_from, $_REQUEST['u']))) {
					Utils::$context['recipients']['to'][$pm->member_from] = [
						'id' => $pm->member_from,
						'name' => Utils::htmlspecialchars($pm->from_name),
					];
				}

				foreach ($pm->received as $member => $received_copy) {
					if ($received_copy->member == User::$me->id) {
						continue;
					}

					if (!$received_copy->bcc) {
						Utils::$context['recipients']['to'][$received_copy->member] = [
							'id' => $received_copy->member,
							'name' => Utils::htmlspecialchars($received_copy->name),
						];
					}
				}
			} elseif ($_REQUEST['u'] != 'all') {
				foreach ($_REQUEST['u'] as $key => $member) {
					if (isset(User::$loaded[$member])) {
						Utils::$context['recipients']['to'][User::$loaded[$member]->id] = [
							'id' => User::$loaded[$member]->id,
							'name' => User::$loaded[$member]->name,
						];

						unset($_REQUEST['u'][$key]);
					}
				}

				if (!empty($_REQUEST['u'])) {
					$request = Db::$db->query(
						'',
						'SELECT id_member, real_name
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:member_list})
						LIMIT {int:limit}',
						[
							'member_list' => $_REQUEST['u'],
							'limit' => count($_REQUEST['u']),
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						Utils::$context['recipients']['to'][(int) $row['id_member']] = [
							'id' => (int) $row['id_member'],
							'name' => $row['real_name'],
						];
					}
					Db::$db->free_result($request);
				}
			}

			// Get a literal name list in case the user has JavaScript disabled.
			$names = [];

			foreach (Utils::$context['recipients']['to'] as $to) {
				$names[] = $to['name'];
			}

			Utils::$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
		} else {
			Utils::$context['to_value'] = '';
		}

		// Set the defaults...
		Utils::$context['subject'] = $form_subject;

		Utils::$context['message'] = str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], $form_message);

		Utils::$context['post_error'] = [];

		// And build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=send',
			'name' => Lang::$txt['new_message'],
		];

		// Generate a list of drafts that they can load in to the editor
		if (!empty(Utils::$context['drafts_save'])) {
			$reply_to = $_REQUEST['pmsg'] ?? ($_REQUEST['quote'] ?? 0);

			DraftPM::showInEditor(User::$me->id, $reply_to);

			// Has a specific draft has been selected?
			// Load its data if there is not a message already in the editor.
			if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message'])) {
				$draft = new DraftPM((int) $_REQUEST['id_draft'], true);
				$draft->prepare();
			}
		}

		// Now create the editor.
		new Editor([
			'id' => 'message',
			'value' => Utils::$context['message'],
			'height' => '175px',
			'width' => '100%',
			'labels' => [
				'post_button' => Lang::$txt['send_message'],
			],
			'preview_type' => Editor::PREVIEW_XML,
			'required' => true,
		]);

		Utils::$context['bcc_value'] = '';

		Utils::$context['require_verification'] = !User::$me->is_admin && !empty(Config::$modSettings['pm_posts_verification']) && User::$me->posts < Config::$modSettings['pm_posts_verification'];

		if (Utils::$context['require_verification']) {
			$verifier = new Verifier(['id' => 'pm']);
		}

		IntegrationHook::call('integrate_pm_post');

		// Register this form and get a sequence number in Utils::$context.
		Security::checkSubmitOnce('register');
	}

	/**
	 * Validates a composed personal message and then passes it to PM::send()
	 * if it is valid. If errors were found, or if the user requested a preview,
	 * will return false.
	 *
	 * @return bool Whether the PM could be sent.
	 */
	public static function compose2(): bool
	{
		User::$me->isAllowedTo('pm_send');

		// PM Drafts enabled and needed?
		if (Utils::$context['drafts_save'] && (isset($_POST['save_draft']) || isset($_POST['id_draft']))) {
			Utils::$context['id_draft'] = !empty($_POST['id_draft']) ? (int) $_POST['id_draft'] : 0;
		}

		Lang::load('PersonalMessage', '', false);

		// Extract out the spam settings - it saves database space!
		list(Config::$modSettings['max_pm_recipients'], Config::$modSettings['pm_posts_verification'], Config::$modSettings['pm_posts_per_hour']) = explode(',', Config::$modSettings['pm_spam_settings']);

		// Initialize the errors we're about to make.
		$post_errors = [];

		// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
		if (!empty(Config::$modSettings['pm_posts_per_hour']) && !User::$me->allowedTo(['admin_forum', 'moderate_forum', 'send_mail']) && User::$me->mod_cache['bq'] == '0=1' && User::$me->mod_cache['gq'] == '0=1') {
			// How many have they sent this last hour?
			$request = Db::$db->query(
				'',
				'SELECT COUNT(pr.id_pm) AS post_count
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
				WHERE pm.id_member_from = {int:me}
					AND pm.msgtime > {int:msgtime}',
				[
					'me' => User::$me->id,
					'msgtime' => time() - 3600,
				],
			);
			list($postCount) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (!empty($postCount) && $postCount >= Config::$modSettings['pm_posts_per_hour']) {
				if (!isset($_REQUEST['xml'])) {
					ErrorHandler::fatalLang('pm_too_many_per_hour', true, [Config::$modSettings['pm_posts_per_hour']]);
				} else {
					$post_errors[] = 'pm_too_many_per_hour';
				}
			}
		}

		// If your session timed out, show an error, but do allow to re-submit.
		if (!isset($_REQUEST['xml']) && User::$me->checkSession('post', '', false) != '') {
			$post_errors[] = 'session_timeout';
		}

		$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';

		$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];

		$_REQUEST['bcc'] = empty($_POST['bcc']) ? (empty($_GET['bcc']) ? '' : $_GET['bcc']) : $_POST['bcc'];

		// Route the input from the 'u' parameter to the 'to'-list.
		if (!empty($_POST['u'])) {
			$_POST['recipient_to'] = explode(',', $_POST['u']);
		}

		// Construct the list of recipients.
		$recipientList = [];
		$namedRecipientList = [];
		$namesNotFound = [];

		foreach (['to', 'bcc'] as $recipientType) {
			// First, let's see if there's user ID's given.
			$recipientList[$recipientType] = [];

			if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType])) {
				foreach ($_POST['recipient_' . $recipientType] as $recipient) {
					$recipientList[$recipientType][] = (int) $recipient;
				}
			}

			// Are there also literal names set?
			if (!empty($_REQUEST[$recipientType])) {
				// We're going to take out the "s anyway ;).
				$recipientString = strtr($_REQUEST[$recipientType], ['\\"' => '"']);

				preg_match_all('~"([^"]+)"~', $recipientString, $matches);

				$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

				foreach ($namedRecipientList[$recipientType] as $index => $recipient) {
					if (strlen(trim($recipient)) > 0) {
						$namedRecipientList[$recipientType][$index] = Utils::htmlspecialchars(Utils::strtolower(trim($recipient)));
					} else {
						unset($namedRecipientList[$recipientType][$index]);
					}
				}

				if (!empty($namedRecipientList[$recipientType])) {
					$foundMembers = User::find($namedRecipientList[$recipientType]);

					// Assume all are not found, until proven otherwise.
					$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

					foreach ($foundMembers as $member) {
						$testNames = [
							Utils::strtolower($member['username']),
							Utils::strtolower($member['name']),
							Utils::strtolower($member['email']),
						];

						if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0) {
							$recipientList[$recipientType][] = $member['id'];

							// Get rid of this username, since we found it.
							$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
						}
					}
				}
			}

			// Selected a recipient to be deleted? Remove them now.
			if (!empty($_POST['delete_recipient'])) {
				$recipientList[$recipientType] = array_diff($recipientList[$recipientType], [(int) $_POST['delete_recipient']]);
			}

			// Make sure we don't include the same name twice
			$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
		}

		// Are we changing the recipients some how?
		$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']) || !empty($_POST['bcc_submit']);

		// Check if there's at least one recipient.
		if (empty($recipientList['to']) && empty($recipientList['bcc'])) {
			$post_errors[] = 'no_to';
		}

		// Make sure that we remove the members who did get it from the screen.
		if (!$is_recipient_change) {
			foreach ($recipientList as $recipientType => $dummy) {
				if (!empty($namesNotFound[$recipientType])) {
					$post_errors[] = 'bad_' . $recipientType;

					// Since we already have a post error, remove the previous one.
					$post_errors = array_diff($post_errors, ['no_to']);

					foreach ($namesNotFound[$recipientType] as $name) {
						Utils::$context['send_log']['failed'][] = sprintf(Lang::$txt['pm_error_user_not_found'], $name);
					}
				}
			}
		}

		// Did they make any mistakes?
		if ($_REQUEST['subject'] == '') {
			$post_errors[] = 'no_subject';
		}

		if (!isset($_REQUEST['message']) || $_REQUEST['message'] == '') {
			$post_errors[] = 'no_message';
		} elseif (!empty(Config::$modSettings['max_messageLength']) && Utils::entityStrlen($_REQUEST['message']) > Config::$modSettings['max_messageLength']) {
			$post_errors[] = 'long_message';
		} else {
			// Preparse the message.
			$message = $_REQUEST['message'];
			Msg::preparsecode($message);

			// Make sure there's still some content left without the tags.
			if (Utils::htmlTrim(strip_tags(BBCodeParser::load()->parse(Utils::htmlspecialchars($message, ENT_QUOTES), false), '<img>')) === '' && (!User::$me->allowedTo('bbc_html') || strpos($message, '[html]') === false)) {
				$post_errors[] = 'no_message';
			}
		}

		// Wrong verification code?
		if (!User::$me->is_admin && !isset($_REQUEST['xml']) && !empty(Config::$modSettings['pm_posts_verification']) && User::$me->posts < Config::$modSettings['pm_posts_verification']) {
			$verifier = new Verifier(['id' => 'pm']);
			$post_errors = array_merge($post_errors, $verifier->errors);
		}

		// If they did, give a chance to make amends.
		if (!empty($post_errors) && !$is_recipient_change && !isset($_REQUEST['preview']) && !isset($_REQUEST['xml'])) {
			self::reportErrors($post_errors, $namedRecipientList, $recipientList);

			return false;
		}

		// Want to take a second glance before you send?
		if (isset($_REQUEST['preview'])) {
			// Set everything up to be displayed.
			Utils::$context['preview_subject'] = Utils::htmlspecialchars($_REQUEST['subject']);

			Utils::$context['preview_message'] = Utils::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);

			Msg::preparsecode(Utils::$context['preview_message'], true);

			// Parse out the BBC if it is enabled.
			Utils::$context['preview_message'] = BBCodeParser::load()->parse(Utils::$context['preview_message']);

			// Censor, as always.
			Lang::censorText(Utils::$context['preview_subject']);
			Lang::censorText(Utils::$context['preview_message']);

			// Set a descriptive title.
			Utils::$context['page_title'] = Lang::$txt['preview'] . ' - ' . Utils::$context['preview_subject'];

			// Pretend they messed up but don't ignore if they really did :P.
			self::reportErrors($post_errors, $namedRecipientList, $recipientList);

			return false;
		}

		// Adding a recipient cause javascript ain't working?
		if ($is_recipient_change) {
			// Maybe we couldn't find one?
			foreach ($namesNotFound as $recipientType => $names) {
				$post_errors[] = 'bad_' . $recipientType;

				foreach ($names as $name) {
					Utils::$context['send_log']['failed'][] = sprintf(Lang::$txt['pm_error_user_not_found'], $name);
				}
			}

			self::reportErrors([], $namedRecipientList, $recipientList);

			return false;
		}

		// Want to save this as a draft and think about it some more?
		if (Utils::$context['drafts_save'] && isset($_POST['save_draft'])) {
			$draft = new DraftPM((int) $_POST['id_draft'], true, $recipientList);
			$draft->save($post_errors);

			self::reportErrors($post_errors, $namedRecipientList, $recipientList);

			return false;
		}

		// Before we send the PM, let's make sure we don't have an abuse of numbers.
		if (!empty(Config::$modSettings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > Config::$modSettings['max_pm_recipients'] && !User::$me->allowedTo(['moderate_forum', 'send_mail', 'admin_forum'])) {
			Utils::$context['send_log'] = [
				'sent' => [],
				'failed' => [sprintf(Lang::$txt['pm_too_many_recipients'], Config::$modSettings['max_pm_recipients'])],
			];

			self::reportErrors($post_errors, $namedRecipientList, $recipientList);

			return false;
		}

		// Protect from message spamming.
		Security::spamProtection('pm');

		// Prevent double submission of this form.
		Security::checkSubmitOnce('check');

		// Do the actual sending of the PM.
		if (!empty($recipientList['to']) || !empty($recipientList['bcc'])) {
			Utils::$context['send_log'] = PM::send($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
		} else {
			Utils::$context['send_log'] = [
				'sent' => [],
				'failed' => [],
			];
		}

		// Mark the message as "replied to".
		if (!empty(Utils::$context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox') {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}pm_recipients
				SET is_read = is_read | 2
				WHERE id_pm = {int:replied_to}
					AND id_member = {int:me}',
				[
					'me' => User::$me->id,
					'replied_to' => (int) $_REQUEST['replied_to'],
				],
			);
		}

		// If one or more of the recipient were invalid, go back to the post screen with the failed usernames.
		if (!empty(Utils::$context['send_log']['failed'])) {
			self::reportErrors($post_errors, $namesNotFound, [
				'to' => array_intersect($recipientList['to'], Utils::$context['send_log']['failed']),
				'bcc' => array_intersect($recipientList['bcc'], Utils::$context['send_log']['failed']),
			]);

			return false;
		}

		// If we had a PM draft for this one, then its time to remove it since it was just sent
		if (Utils::$context['drafts_save'] && !empty($_POST['id_draft'])) {
			DraftPM::delete($_POST['id_draft']);
		}

		return true;
	}

	/**
	 * Sends an personal message from the specified person to the specified people
	 * ($from defaults to the user)
	 *
	 * @param array $recipients An array containing the arrays 'to' and 'bcc', both containing id_member's.
	 * @param string $subject Should have no slashes and no html entities
	 * @param string $message Should have no slashes and no html entities
	 * @param bool $store_outbox Whether to store it in the sender's outbox
	 * @param array $from An array with the id, name, and username of the member.
	 * @param int $pm_head The ID of the chain being replied to - if any.
	 * @return array An array with log entries telling how many recipients were successful and which recipients it failed to send to.
	 */
	public static function send($recipients, $subject, $message, $store_outbox = false, $from = null, $pm_head = 0): array
	{
		// Make sure the PM language file is loaded, we might need something out of it.
		Lang::load('PersonalMessage');

		// Initialize log array.
		$log = [
			'failed' => [],
			'sent' => [],
		];

		if ($from === null) {
			$from = [
				'id' => User::$me->id,
				'name' => User::$me->name,
				'username' => User::$me->username,
			];
		}

		// This is the one that will go in their inbox.
		$htmlmessage = Utils::htmlspecialchars($message, ENT_QUOTES);

		Msg::preparsecode($htmlmessage);

		$htmlsubject = strtr(Utils::htmlspecialchars($subject), ["\r" => '', "\n" => '', "\t" => '']);

		if (Utils::entityStrlen($htmlsubject) > 100) {
			$htmlsubject = Utils::entitySubstr($htmlsubject, 0, 100);
		}

		// Make sure is an array
		if (!is_array($recipients)) {
			$recipients = [$recipients];
		}

		// Integrated PMs
		IntegrationHook::call('integrate_personal_message', [&$recipients, &$from, &$subject, &$message]);

		// Get a list of usernames and convert them to IDs.
		$usernames = [];

		foreach ($recipients as $rec_type => $rec) {
			foreach ($rec as $id => $member) {
				if (!is_numeric($recipients[$rec_type][$id])) {
					$recipients[$rec_type][$id] = Utils::strtolower(trim(preg_replace('~[<>&"\'=\\\]~', '', $recipients[$rec_type][$id])));

					$usernames[$recipients[$rec_type][$id]] = 0;
				}
			}
		}

		if (!empty($usernames)) {
			$request = Db::$db->query(
				'pm_find_username',
				'SELECT id_member, member_name
				FROM {db_prefix}members
				WHERE ' . (Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name') . ' IN ({array_string:usernames})',
				[
					'usernames' => array_keys($usernames),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (isset($usernames[Utils::strtolower($row['member_name'])])) {
					$usernames[Utils::strtolower($row['member_name'])] = $row['id_member'];
				}
			}
			Db::$db->free_result($request);

			// Replace the usernames with IDs. Drop usernames that couldn't be found.
			foreach ($recipients as $rec_type => $rec) {
				foreach ($rec as $id => $member) {
					if (is_numeric($recipients[$rec_type][$id])) {
						continue;
					}

					if (!empty($usernames[$member])) {
						$recipients[$rec_type][$id] = $usernames[$member];
					} else {
						$log['failed'][$id] = sprintf(Lang::$txt['pm_error_user_not_found'], $recipients[$rec_type][$id]);

						unset($recipients[$rec_type][$id]);
					}
				}
			}
		}

		// Make sure there are no duplicate 'to' members.
		$recipients['to'] = array_unique($recipients['to']);

		// Only 'bcc' members that aren't already in 'to'.
		$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);

		// Combine 'to' and 'bcc' recipients.
		$all_to = array_merge($recipients['to'], $recipients['bcc']);

		// Check no-one will want it deleted right away!
		$deletes = [];
		$request = Db::$db->query(
			'',
			'SELECT
				id_member, criteria, is_or
			FROM {db_prefix}pm_rules
			WHERE id_member IN ({array_int:to_members})
				AND delete_pm = {int:delete_pm}',
			[
				'to_members' => $all_to,
				'delete_pm' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$criteria = Utils::jsonDecode($row['criteria'], true);

			// Note we don't check the buddy status, cause deletion from buddy = madness!
			$delete = false;

			foreach ($criteria as $criterium) {
				if (
					(
						$criterium['t'] == 'mid'
						&& $criterium['v'] == $from['id']
					)
					|| (
						$criterium['t'] == 'gid'
						&& in_array($criterium['v'], User::$me->groups)
					)
					|| (
						$criterium['t'] == 'sub'
						&& strpos($subject, $criterium['v']) !== false
					)
					|| (
						$criterium['t'] == 'msg'
						&& strpos($message, $criterium['v']) !== false
					)
				) {
					$delete = true;
				}
				// If we're adding and one criteria doesn't match then we stop!
				elseif (!$row['is_or']) {
					$delete = false;
					break;
				}
			}

			if ($delete) {
				$deletes[$row['id_member']] = 1;
			}
		}
		Db::$db->free_result($request);

		// Load the membergroup message limits.
		if (!User::$me->allowedTo('moderate_forum') && empty(self::$message_limit_cache)) {
			foreach (Group::load() as $group) {
				self::$message_limit_cache[$group->id] = $group->max_messages;
			}
		}

		// Load the groups that are allowed to read PMs.
		$pmReadGroups = User::groupsAllowedTo('pm_read');

		if (empty(Config::$modSettings['permission_enable_deny'])) {
			$pmReadGroups['denied'] = [];
		}

		// Load their alert preferences
		$notifyPrefs = Notify::getNotifyPrefs($all_to, ['pm_new', 'pm_reply', 'pm_notify'], true);

		$notifications = [];
		$request = Db::$db->query(
			'',
			'SELECT
				member_name, real_name, id_member, email_address, lngfile,
				instant_messages,' . (User::$me->allowedTo('moderate_forum') ? ' 0' : '
				(pm_receive_from = {int:admins_only}' . (empty(Config::$modSettings['enable_buddylist']) ? '' : ' OR
				(pm_receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
				(pm_receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
				FIND_IN_SET({string:from_id}, buddy_list) != 0 AS is_buddy, is_activated,
				additional_groups, id_group, id_post_group
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:recipients})
			ORDER BY lngfile
			LIMIT {int:count_recipients}',
			[
				'not_on_ignore_list' => 1,
				'buddies_only' => 2,
				'admins_only' => 3,
				'recipients' => $all_to,
				'count_recipients' => count($all_to),
				'from_id' => $from['id'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Don't do anything for members to be deleted!
			if (isset($deletes[$row['id_member']])) {
				continue;
			}

			// Load the preferences for this member (if any)
			$prefs = !empty($notifyPrefs[$row['id_member']]) ? $notifyPrefs[$row['id_member']] : [];
			$prefs = array_merge([
				'pm_new' => 0,
				'pm_reply' => 0,
				'pm_notify' => 0,
			], $prefs);

			// We need to know this members groups.
			$groups = explode(',', $row['additional_groups']);
			$groups[] = $row['id_group'];
			$groups[] = $row['id_post_group'];

			$message_limit = -1;

			// For each group see whether they've gone over their limit - assuming they're not an admin.
			if (!in_array(1, $groups)) {
				foreach ($groups as $id) {
					if (isset(self::$message_limit_cache[$id]) && $message_limit != 0 && $message_limit < self::$message_limit_cache[$id]) {
						$message_limit = self::$message_limit_cache[$id];
					}
				}

				if ($message_limit > 0 && $message_limit <= $row['instant_messages']) {
					$log['failed'][$row['id_member']] = sprintf(Lang::$txt['pm_error_data_limit_reached'], $row['real_name']);

					unset($all_to[array_search($row['id_member'], $all_to)]);

					continue;
				}

				// Do they have any of the allowed groups?
				if (count(array_intersect($pmReadGroups['allowed'], $groups)) == 0 || count(array_intersect($pmReadGroups['denied'], $groups)) != 0) {
					$log['failed'][$row['id_member']] = sprintf(Lang::$txt['pm_error_user_cannot_read'], $row['real_name']);

					unset($all_to[array_search($row['id_member'], $all_to)]);

					continue;
				}
			}

			// Note that PostgreSQL can return a lowercase t/f for FIND_IN_SET
			if (!empty($row['ignored']) && $row['ignored'] != 'f' && $row['id_member'] != $from['id']) {
				$log['failed'][$row['id_member']] = sprintf(Lang::$txt['pm_error_ignored_by_user'], $row['real_name']);

				unset($all_to[array_search($row['id_member'], $all_to)]);

				continue;
			}

			// If the receiving account is banned (>=10) or pending deletion (4), refuse to send the PM.
			if ($row['is_activated'] >= 10 || ($row['is_activated'] == 4 && !User::$me->is_admin)) {
				$log['failed'][$row['id_member']] = sprintf(Lang::$txt['pm_error_user_cannot_read'], $row['real_name']);

				unset($all_to[array_search($row['id_member'], $all_to)]);

				continue;
			}

			// Send a notification, if enabled - taking the buddy list into account.
			if (
				!empty($row['email_address'])
				&& $row['is_activated'] == 1
				&& (
					(
						empty($pm_head)
						&& $prefs['pm_new'] & 0x02
					)
					|| (
						!empty($pm_head)
						&& $prefs['pm_reply'] & 0x02
					)
				)
				&& (
					$prefs['pm_notify'] <= 1
					|| (
						$prefs['pm_notify'] > 1
						&& (
							!empty(Config::$modSettings['enable_buddylist'])
							&& $row['is_buddy']
						)
					)
				)
			) {
				$notifications[empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile']][] = $row['email_address'];
			}

			$log['sent'][$row['id_member']] = sprintf(Lang::$txt['pm_successfully_sent'] ?? '', $row['real_name']);
		}
		Db::$db->free_result($request);

		// Only 'send' the message if there are any recipients left.
		if (empty($all_to)) {
			return $log;
		}

		// Insert the message itself and then grab the last insert id.
		$id_pm = Db::$db->insert(
			'',
			'{db_prefix}personal_messages',
			[
				'id_pm_head' => 'int', 'id_member_from' => 'int', 'deleted_by_sender' => 'int',
				'from_name' => 'string-255', 'msgtime' => 'int', 'subject' => 'string-255', 'body' => 'string-65534',
			],
			[
				$pm_head, $from['id'], ($store_outbox ? 0 : 1),
				$from['username'], time(), $htmlsubject, $htmlmessage,
			],
			['id_pm'],
			1,
		);

		// Add the recipients.
		if (!empty($id_pm)) {
			// If this is new we need to set it part of its own conversation.
			if (empty($pm_head)) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}personal_messages
					SET id_pm_head = {int:id_pm_head}
					WHERE id_pm = {int:id_pm_head}',
					[
						'id_pm_head' => $id_pm,
					],
				);
			}

			// Some people think manually deleting personal_messages is fun... it's not. We protect against it though :)
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}pm_recipients
				WHERE id_pm = {int:id_pm}',
				[
					'id_pm' => $id_pm,
				],
			);

			$insertRows = [];
			$to_list = [];

			foreach ($all_to as $to) {
				$insertRows[] = [$id_pm, $to, in_array($to, $recipients['bcc']) ? 1 : 0, isset($deletes[$to]) ? 1 : 0, 1];

				if (!in_array($to, $recipients['bcc'])) {
					$to_list[] = $to;
				}
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}pm_recipients',
				[
					'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int',
				],
				$insertRows,
				['id_pm', 'id_member'],
			);
		}

		$to_names = [];

		if (count($to_list) > 1) {
			$request = Db::$db->query(
				'',
				'SELECT real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:to_members})
					AND id_member != {int:from}',
				[
					'to_members' => $to_list,
					'from' => $from['id'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$to_names[] = Utils::htmlspecialcharsDecode($row['real_name']);
			}
			Db::$db->free_result($request);
		}

		$replacements = [
			'SUBJECT' => $subject,
			'MESSAGE' => $message,
			'SENDER' => Utils::htmlspecialcharsDecode($from['name']),
			'READLINK' => Config::$scripturl . '?action=pm;pmsg=' . $id_pm . '#msg' . $id_pm,
			'REPLYLINK' => Config::$scripturl . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'],
			'TOLIST' => implode(', ', $to_names),
		];

		$email_template = 'new_pm' . (empty(Config::$modSettings['disallow_sendBody']) ? '_body' : '') . (!empty($to_names) ? '_tolist' : '');

		$notification_texts = [];

		foreach ($notifications as $lang => $notification_list) {
			// Censor and parse BBC in the receiver's language. Only do each language once.
			if (empty($notification_texts[$lang])) {
				if ($lang != User::$me->language) {
					Lang::load('index+Modifications', $lang, false);
				}

				$notification_texts[$lang]['subject'] = $subject;

				Lang::censorText($notification_texts[$lang]['subject']);

				if (empty(Config::$modSettings['disallow_sendBody'])) {
					$notification_texts[$lang]['body'] = $message;

					Lang::censorText($notification_texts[$lang]['body']);

					$notification_texts[$lang]['body'] = trim(Utils::htmlspecialcharsDecode(strip_tags(strtr(BBCodeParser::load()->parse(Utils::htmlspecialchars($notification_texts[$lang]['body']), false), ['<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']']))));
				} else {
					$notification_texts[$lang]['body'] = '';
				}

				if ($lang != User::$me->language) {
					Lang::load('index+Modifications', User::$me->language, false);
				}
			}

			$replacements['SUBJECT'] = $notification_texts[$lang]['subject'];
			$replacements['MESSAGE'] = $notification_texts[$lang]['body'];

			$emaildata = Mail::loadEmailTemplate($email_template, $replacements, $lang);

			// Off the notification email goes!
			Mail::send($notification_list, $emaildata['subject'], $emaildata['body'], null, 'p' . $id_pm, $emaildata['is_html'], 2, null, true);
		}

		// Integrated After PMs
		IntegrationHook::call('integrate_personal_message_after', [&$id_pm, &$log, &$recipients, &$from, &$subject, &$message]);

		// Back to what we were on before!
		Lang::load('index+PersonalMessage');

		// Add one to their unread and read message counts.
		foreach ($all_to as $k => $id) {
			if (isset($deletes[$id])) {
				unset($all_to[$k]);
			}
		}

		if (!empty($all_to)) {
			User::updateMemberData($all_to, ['instant_messages' => '+', 'unread_messages' => '+', 'new_pm' => 1]);
		}

		return $log;
	}

	/**
	 * Delete the specified personal messages.
	 *
	 * @param array|null $personal_messages An array containing the IDs of PMs to delete or null to delete all of them
	 * @param string|null $folder Which "folder" to delete PMs from - 'sent' to delete them from the outbox, null or anything else to delete from the inbox
	 * @param array|int|null $owner An array of IDs of users whose PMs are being deleted, the ID of a single user or null to use the current user's ID
	 */
	public static function delete($personal_messages, $folder = null, $owner = null): void
	{
		if ($owner === null) {
			$owner = [User::$me->id];
		} elseif (empty($owner)) {
			return;
		} elseif (!is_array($owner)) {
			$owner = [$owner];
		}

		if ($personal_messages !== null) {
			if (empty($personal_messages) || !is_array($personal_messages)) {
				return;
			}

			foreach ($personal_messages as $index => $delete_id) {
				$personal_messages[$index] = (int) $delete_id;
			}

			$where = '
					AND id_pm IN ({array_int:pm_list})';
		} else {
			$where = '';
		}

		if ($folder == 'sent' || $folder === null) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}personal_messages
				SET deleted_by_sender = {int:is_deleted}
				WHERE id_member_from IN ({array_int:member_list})
					AND deleted_by_sender = {int:not_deleted}' . $where,
				[
					'member_list' => $owner,
					'is_deleted' => 1,
					'not_deleted' => 0,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
				],
			);
		}

		if ($folder != 'sent' || $folder === null) {
			// Calculate the number of messages each member's gonna lose...
			$request = Db::$db->query(
				'',
				'SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
				FROM {db_prefix}pm_recipients
				WHERE id_member IN ({array_int:member_list})
					AND deleted = {int:not_deleted}' . $where . '
				GROUP BY id_member, is_read',
				[
					'member_list' => $owner,
					'not_deleted' => 0,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
				],
			);

			// ...And update the statistics accordingly - now including unread messages!.
			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['is_read']) {
					User::updateMemberData($row['id_member'], ['instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']]);
				} else {
					User::updateMemberData($row['id_member'], ['instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']]);
				}

				// If this is the current member we need to make their message count correct.
				if (User::$me->id == $row['id_member']) {
					User::$me->messages -= $row['num_deleted_messages'];

					if (!($row['is_read'])) {
						User::$me->unread_messages -= $row['num_deleted_messages'];
					}
				}
			}
			Db::$db->free_result($request);

			// Do the actual deletion.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}pm_recipients
				SET deleted = {int:is_deleted}
				WHERE id_member IN ({array_int:member_list})
					AND deleted = {int:not_deleted}' . $where,
				[
					'member_list' => $owner,
					'is_deleted' => 1,
					'not_deleted' => 0,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
				],
			);

			$labels = [];

			// Get any labels that the owner may have applied to this PM
			// The join is here to ensure we only get labels applied by the specified member(s)
			$get_labels = Db::$db->query(
				'',
				'SELECT pml.id_label
				FROM {db_prefix}pm_labels AS l
					INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
				WHERE l.id_member IN ({array_int:member_list})' . $where,
				[
					'member_list' => $owner,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
				],
			);

			while ($row = Db::$db->fetch_assoc($get_labels)) {
				$labels[] = $row['id_label'];
			}

			Db::$db->free_result($get_labels);

			if (!empty($labels)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}pm_labeled_messages
					WHERE id_label IN ({array_int:labels})' . $where,
					[
						'labels' => $labels,
						'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
					],
				);
			}
		}

		// If sender and recipients all have deleted their message, it can be removed.
		$remove_pms = [];
		$request = Db::$db->query(
			'',
			'SELECT pm.id_pm AS sender, pmr.id_pm
			FROM {db_prefix}personal_messages AS pm
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
			WHERE pm.deleted_by_sender = {int:is_deleted} AND pmr.id_pm is null
				' . str_replace('id_pm', 'pm.id_pm', $where),
			[
				'not_deleted' => 0,
				'is_deleted' => 1,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$remove_pms[] = $row['sender'];
		}
		Db::$db->free_result($request);

		if (!empty($remove_pms)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}personal_messages
				WHERE id_pm IN ({array_int:pm_list})',
				[
					'pm_list' => $remove_pms,
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}pm_recipients
				WHERE id_pm IN ({array_int:pm_list})',
				[
					'pm_list' => $remove_pms,
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}pm_labeled_messages
				WHERE id_pm IN ({array_int:pm_list})',
				[
					'pm_list' => $remove_pms,
				],
			);
		}

		// Any cached numbers may be wrong now.
		CacheApi::put('labelCounts:' . User::$me->id, null, 720);
	}

	/**
	 * Mark the specified personal messages as read.
	 *
	 * @param array|null $personal_messages An array of PM IDs to mark or null to mark all.
	 * @param int|null $label The ID of a label. If set, only messages with this label will be marked.
	 * @param int|null $owner If owner is set, marks messages owned by that member id.
	 */
	public static function markRead($personal_messages = null, $label = null, $owner = null): void
	{
		if ($owner === null) {
			$owner = User::$me->id;
		}

		$in_inbox = '';

		// Marking all messages with a specific label as read?
		// If we know which PMs we're marking read, then we don't need label info
		if ($personal_messages === null && $label !== null && $label != '-1') {
			$personal_messages = [];

			$get_messages = Db::$db->query(
				'',
				'SELECT id_pm
				FROM {db_prefix}pm_labeled_messages
				WHERE id_label = {int:current_label}',
				[
					'current_label' => $label,
				],
			);

			while ($row = Db::$db->fetch_assoc($get_messages)) {
				$personal_messages[] = $row['id_pm'];
			}
			Db::$db->free_result($get_messages);
		} elseif ($label = '-1') {
			// Marking all PMs in your inbox read
			$in_inbox = '
				AND in_inbox = {int:in_inbox}';
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}pm_recipients
			SET is_read = is_read | 1
			WHERE id_member = {int:id_member}
				AND NOT (is_read & 1 >= 1)' . ($personal_messages !== null ? '
				AND id_pm IN ({array_int:personal_messages})' : '') . $in_inbox,
			[
				'personal_messages' => $personal_messages,
				'id_member' => $owner,
				'in_inbox' => 1,
			],
		);

		// If something wasn't marked as read, get the number of unread messages remaining.
		if (Db::$db->affected_rows() > 0) {
			if ($owner == User::$me->id) {
				foreach (Utils::$context['labels'] as $label) {
					Utils::$context['labels'][(int) $label['id']]['unread_messages'] = 0;
				}
			}

			$total_unread = 0;
			$result = Db::$db->query(
				'',
				'SELECT id_pm, in_inbox, COUNT(*) AS num
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:id_member}
					AND NOT (is_read & 1 >= 1)
					AND deleted = {int:is_not_deleted}
				GROUP BY id_pm, in_inbox',
				[
					'id_member' => $owner,
					'is_not_deleted' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$total_unread += $row['num'];

				if ($owner != User::$me->id || empty($row['id_pm'])) {
					continue;
				}

				$this_labels = [];

				// Get all the labels
				$result2 = Db::$db->query(
					'',
					'SELECT pml.id_label
					FROM {db_prefix}pm_labels AS l
						INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
					WHERE l.id_member = {int:id_member}
						AND pml.id_pm = {int:current_pm}',
					[
						'id_member' => $owner,
						'current_pm' => $row['id_pm'],
					],
				);

				while ($row2 = Db::$db->fetch_assoc($result2)) {
					$this_labels[] = $row2['id_label'];
				}
				Db::$db->free_result($result2);

				foreach ($this_labels as $this_label) {
					Utils::$context['labels'][$this_label]['unread_messages'] += $row['num'];
				}

				if ($row['in_inbox'] == 1) {
					Utils::$context['labels'][-1]['unread_messages'] += $row['num'];
				}
			}
			Db::$db->free_result($result);

			// Need to store all this.
			CacheApi::put('labelCounts:' . $owner, Utils::$context['labels'], 720);
			User::updateMemberData($owner, ['unread_messages' => $total_unread]);

			// If it was for the current member, reflect this in User::$me as well.
			if ($owner == User::$me->id) {
				User::$me->unread_messages = $total_unread;
			}
		}
	}

	/**
	 * Gets the ID of the most recent personal message sent by the current user.
	 *
	 * @return int The ID of the mostly sent recent PM.
	 */
	public static function getLatest(): int
	{
		$latest = self::getRecent('pm.id_pm', true, 1);

		return reset($latest);
	}

	/**
	 * Gets the IDs of the most recent personal messages sent by the current user.
	 *
	 * @param string $sort The column to sort by in the SQL query.
	 *    Default: pmr.id_pm
	 * @param bool $descending Whether to sort descending or ascending.
	 *    Default: true.
	 * @param int $limit How many results to get. Zero for no limit.
	 *    Default: 0.
	 * @param int $offset How many results to skip before retrieving the rest.
	 *    Default: 0.
	 * @return array The IDs of the most recently sent PMs.
	 */
	public static function getRecent(string $sort = 'pm.id_pm', bool $descending = true, int $limit = 0, int $offset = 0): array
	{
		$paramskey = Utils::jsonEncode([$sort, $descending, $limit, $offset]);

		if (isset(self::$recent[$paramskey])) {
			return self::$recent[$paramskey];
		}

		$joins = [];

		if ($sort === 'COALESCE(mem.real_name, \'\')') {
			$joins[] = 'LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)';
			$joins[] = 'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)';
		}

		$request = Db::$db->query(
			'',
			'SELECT pm.id_pm
			FROM {db_prefix}personal_messages AS pm' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . '
			WHERE pm.id_member_from = {int:me}
				AND pm.deleted_by_sender = 0
			ORDER BY ' . $sort . ($descending ? ' DESC' : ' ASC') . (!empty($limit) ? '
			LIMIT ' . $offset . ', ' . $limit : ''),
			[
				'me' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$recent[$paramskey][] = $row['id_pm'];
		}
		Db::$db->free_result($request);

		return self::$recent[$paramskey];
	}

	/**
	 * Counts the personal messages sent by the current user, with optional limits.
	 */
	public static function countSent(int $boundary = 0, bool $greater_than = false): int
	{
		$num = 0;

		$selects = [
			'COUNT(*)',
		];

		$joins = [];

		$where = [
			'pm.id_member_from = {int:me}',
			'pm.deleted_by_sender = {int:not_deleted}',
		];

		$params = [
			'me' => User::$me->id,
			'not_deleted' => 0,
		];

		if (!empty($boundary)) {
			$where[] = 'pmr.id_pm ' . ($greater_than ? '>' : '<') . ' {int:boundary}';
			$params['boundary'] = $boundary;
		}

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$num += reset($row);
		}

		return $num;
	}

	/**
	 * Informs the user about an error in the message they wrote.
	 *
	 * @param array $error_types An array of strings indicating which type of errors occurred.
	 * @param array $named_recipients Names of recipients.
	 * @param array $recipient_ids IDs of recipients.
	 */
	public static function reportErrors(array $error_types, array $named_recipients, array $recipient_ids = []): void
	{
		if (!isset($_REQUEST['xml'])) {
			Menu::$loaded['pm']['current_area'] = 'send';
			Utils::$context['sub_template'] = 'send';
			Theme::loadJavaScriptFile('PersonalMessage.js', ['defer' => false, 'minimize' => true], 'smf_pms');
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
		} else {
			Utils::$context['sub_template'] = 'pm';
		}

		Utils::$context['page_title'] = Lang::$txt['send_message'];

		// Got some known members?
		Utils::$context['recipients'] = [
			'to' => [],
			'bcc' => [],
		];

		if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc'])) {
			$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

			$request = Db::$db->query(
				'',
				'SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})',
				[
					'member_list' => $allRecipients,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';

				Utils::$context['recipients'][$recipientType][] = [
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				];
			}
			Db::$db->free_result($request);
		}

		// Set everything up like before....
		Utils::$context['subject'] = isset($_REQUEST['subject']) ? Utils::htmlspecialchars($_REQUEST['subject']) : '';

		Utils::$context['message'] = isset($_REQUEST['message']) ? str_replace(['  '], ['&nbsp; '], Utils::htmlspecialchars($_REQUEST['message'])) : '';

		Utils::$context['reply'] = !empty($_REQUEST['replied_to']);

		if (Utils::$context['reply']) {
			$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

			$request = Db::$db->query(
				'',
				'SELECT
					pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
					pm.body, pm.subject, pm.msgtime, mem.member_name, COALESCE(mem.id_member, 0) AS id_member,
					COALESCE(mem.real_name, pm.from_name) AS real_name
				FROM {db_prefix}personal_messages AS pm' . (Utils::$context['folder'] == 'sent' ? '' : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm = {int:replied_to}' . (Utils::$context['folder'] == 'sent' ? '
					AND pm.id_member_from = {int:me}' : '
					AND pmr.id_member = {int:me}') . '
				LIMIT 1',
				[
					'me' => User::$me->id,
					'no_id_pm_head' => 0,
					'replied_to' => $_REQUEST['replied_to'],
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				if (!isset($_REQUEST['xml'])) {
					ErrorHandler::fatalLang('pm_not_yours', false);
				} else {
					$error_types[] = 'pm_not_yours';
				}
			}
			$row_quoted = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			Lang::censorText($row_quoted['subject']);
			Lang::censorText($row_quoted['body']);

			Utils::$context['quoted_message'] = [
				'id' => $row_quoted['id_pm'],
				'pm_head' => $row_quoted['pm_head'],
				'member' => [
					'name' => $row_quoted['real_name'],
					'username' => $row_quoted['member_name'],
					'id' => $row_quoted['id_member'],
					'href' => !empty($row_quoted['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
					'link' => !empty($row_quoted['id_member']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
				],
				'subject' => $row_quoted['subject'],
				'time' => Time::create('@' . $row_quoted['msgtime'])->format(),
				'timestamp' => $row_quoted['msgtime'],
				'body' => BBCodeParser::load()->parse($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']),
			];
		}

		// Build the link tree....
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=send',
			'name' => Lang::$txt['new_message'],
		];

		// Set each of the errors for the template.
		Lang::load('Errors');

		Utils::$context['error_type'] = 'minor';

		Utils::$context['post_error'] = [
			'messages' => [],
			// @todo error handling: maybe fatal errors can be error_type => serious
			'error_type' => '',
		];

		foreach ($error_types as $error_type) {
			Utils::$context['post_error'][$error_type] = true;

			if (isset(Lang::$txt['error_' . $error_type])) {
				if ($error_type == 'long_message') {
					Lang::$txt['error_' . $error_type] = sprintf(Lang::$txt['error_' . $error_type], Config::$modSettings['max_messageLength']);
				}

				Utils::$context['post_error']['messages'][] = Lang::$txt['error_' . $error_type];
			}

			// If it's not a minor error flag it as such.
			if (!in_array($error_type, ['new_reply', 'not_approved', 'new_replies', 'old_topic', 'need_qr_verification', 'no_subject'])) {
				Utils::$context['error_type'] = 'serious';
			}
		}

		// Create it...
		new Editor([
			'id' => 'message',
			'value' => Utils::$context['message'],
			'width' => '90%',
			'height' => '175px',
			'labels' => [
				'post_button' => Lang::$txt['send_message'],
			],
			'preview_type' => Editor::PREVIEW_XML,
		]);

		// Check whether we need to show the code again.
		Utils::$context['require_verification'] = !User::$me->is_admin && !empty(Config::$modSettings['pm_posts_verification']) && User::$me->posts < Config::$modSettings['pm_posts_verification'];

		if (Utils::$context['require_verification'] && !isset($_REQUEST['xml'])) {
			$verifier = new Verifier(['id' => 'pm']);
		}

		Utils::$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';

		Utils::$context['bcc_value'] = empty($named_recipients['bcc']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['bcc']) . '&quot;';

		IntegrationHook::call('integrate_pm_error');

		// No check for the previous submission is needed.
		Security::checkSubmitOnce('free');

		// Acquire a new form sequence number.
		Security::checkSubmitOnce('register');
	}

	/**
	 * Backward compatibilty wrapper around the non-static canAccess() method.
	 *
	 * Check if the PM is available to the current user.
	 *
	 * @param int $pmID The ID of the PM
	 * @param string $folders Which folders this is valid for - can be 'inbox', 'outbox' or 'in_or_outbox'
	 * @return bool Whether the PM is accessible in that folder for the current user
	 */
	public static function isAccessible($pmID, $folders = 'both'): bool
	{
		if ($folders === 'in_or_outbox') {
			$folders = 'both';
		}

		if ($folders === 'outbox') {
			$folders = 'sent';
		}

		if (!isset(self::$loaded[$pmID])) {
			self::load($pmID);
		}

		return self::$loaded[$pmID]->canAccess($folders);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Generator that runs queries about PM data and yields the result rows.
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
			FROM {db_prefix}personal_messages AS pm' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (empty($limit) ? '' : '
			LIMIT ' . $limit),
			$params,
		);

		while ($row = Db::$db->fetch_assoc(self::$messages_request)) {
			yield $row;
		}
		Db::$db->free_result(self::$messages_request);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\PM::exportStatic')) {
	PM::exportStatic();
}

?>