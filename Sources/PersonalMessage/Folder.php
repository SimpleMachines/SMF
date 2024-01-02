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

use SMF\Actions\PersonalMessage as PMAction;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Represents a personal message folder (i.e. "inbox" or "sent items")
 *
 * The main purpose of this class is to handle displaying personal messages.
 */
class Folder
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * Whether this is the inbox or the sent items folder.
	 */
	public bool $is_inbox;

	/**
	 * @var int
	 *
	 * The display mode.
	 * Value must be one of the PMAction::VIEW_* constants.
	 */
	public int $mode;

	/**
	 * @var int
	 *
	 * Items to show per page.
	 */
	public int $per_page;

	/**
	 * @var bool
	 *
	 * Whether to show results in descending or ascending order.
	 */
	public bool $descending = true;

	/**
	 * @var int
	 *
	 * ID number of the current label, or -1 for the main inbox folder.
	 */
	public int $current_label_id = -1;

	/**
	 * @var string
	 *
	 * Name of the current label.
	 */
	public string $current_label = '';

	/**
	 * @var object
	 *
	 * Instance of SMF\PersonalMessage\PM for a personal message that was
	 * requested via $_GET['pmid'] or $_GET['pmsg'].
	 */
	public object $requested_pm;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Instructions for sorting the personal messages.
	 */
	public static $sort_methods = [
		'date' => 'pm.id_pm',
		'name' => 'COALESCE(mem.real_name, \'\')',
		'subject' => 'pm.subject',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param bool $is_inbox Whether this is the inbox or the sent items folder.
	 */
	public function __construct(bool $is_inbox = true)
	{
		Lang::load('PersonalMessage');

		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('PersonalMessage');
		}

		$this->is_inbox = $is_inbox;

		$this->mode = User::$me->pm_prefs & 3;

		$this->per_page = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		Label::load();
		$this->current_label_id = isset($_REQUEST['l']) && isset(Label::$loaded[$_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
		$this->current_label = Label::$loaded[$this->current_label_id]['name'];

		// These context vars should already be set, but just in case...
		if (!isset(Utils::$context['display_mode'])) {
			Utils::$context['display_mode'] = &$this->mode;
		}

		if (!isset(Utils::$context['folder'])) {
			Utils::$context['folder'] = $this->is_inbox ? 'inbox' : 'sent';
		}

		if (!isset(Utils::$context['currently_using_labels'])) {
			Utils::$context['currently_using_labels'] = !empty(Label::$loaded);
		}

		if (!isset(Utils::$context['current_label_id'])) {
			Utils::$context['current_label_id'] = &$this->current_label_id;
		}

		if (!isset(Utils::$context['current_label'])) {
			Utils::$context['current_label'] = &$this->current_label;
		}
	}

	/**
	 * Shows the personal messages in a folder, ie. inbox/sent etc.
	 */
	public function show(): void
	{
		// Changing view?
		if (isset($_GET['view'])) {
			$this->changeDisplayMode();
		}

		// Sanitize and validate pmid and pmsg variables, if set.
		// @todo Can we consolidate these into a single variable?
		foreach (['pmid', 'pmsg'] as $var) {
			if (isset($_GET[$var])) {
				$this->requested_pm = current(PM::load((int) $_GET[$var]));

				// Make sure you have access to this PM.
				if (!$this->requested_pm->canAccess($this->is_inbox ? 'inbox' : 'sent')) {
					ErrorHandler::fatalLang('no_access', false);
				}
			}
		}

		// Set up some basic theme stuff.
		Utils::$context['from_or_to'] = $this->is_inbox ? 'from' : 'to';
		Utils::$context['signature_enabled'] = substr(Config::$modSettings['signature_settings'], 0, 1) == 1;
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];

		// Prevent signature images from going outside the box.
		if (Utils::$context['signature_enabled']) {
			list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
			$sig_limits = explode(',', $sig_limits);

			if (!empty($sig_limits[5]) || !empty($sig_limits[6])) {
				Theme::addInlineCss("\n\t" . '.signature img { ' . (!empty($sig_limits[5]) ? 'max-width: ' . (int) $sig_limits[5] . 'px; ' : '') . (!empty($sig_limits[6]) ? 'max-height: ' . (int) $sig_limits[6] . 'px; ' : '') . '}');
			}
		}

		Utils::$context['get_pmessage'] = [$this, 'prepareMessageContext'];

		$this->setPaginationAndLinks();

		$this->showSubjects();

		switch ($this->mode) {
			case PMAction::VIEW_CONV:
				$display_pms = $this->showConversation();
				break;

			case PMAction::VIEW_ONE:
				$display_pms = $this->showOne();
				break;

			default:
				$display_pms = $this->showAll();
				break;
		}

		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');
		Utils::$context['can_send_email'] = User::$me->allowedTo('moderate_forum');
		Utils::$context['sub_template'] = 'folder';
		Utils::$context['page_title'] = Lang::$txt['pm_inbox'];

		// Finally mark the relevant messages as read.
		if ($this->is_inbox && !empty(Label::$loaded[(int) $this->current_label_id]['unread_messages'])) {
			PM::markRead($display_pms, $this->current_label_id);
		}
	}

	/**
	 * Get a personal message for the theme.  (used to save memory.)
	 *
	 * @param string $type The type of message
	 * @param bool $check Checks whether we have some messages to show.
	 * @return bool|array False on failure, otherwise an array of info
	 */
	public function prepareMessageContext($type = 'subject', $check = false)
	{
		static $counter = null;
		static $temp_pm_selected = null;

		// Count the current message number....
		if ($counter === null || $check) {
			$counter = Utils::$context['start'];
		}

		if ($temp_pm_selected === null) {
			$temp_pm_selected = $_SESSION['pm_selected'] ?? [];
			$_SESSION['pm_selected'] = [];
		}

		if ($type == 'subject') {
			return $this->prepareSubjectContext();
		}

		if (!(PM::$getter instanceof \Generator)) {
			return false;
		}

		if ($check) {
			return PM::$getter->valid();
		}

		$message = PM::$getter->current();
		PM::$getter->next();

		if (!$message) {
			return false;
		}

		$format_options = [
			'no_bcc' => $this->is_inbox,
		];

		$output = $message->format($counter++, $format_options);

		IntegrationHook::call('integrate_prepare_pm_context', [&$output, &$message, $counter]);

		return $output;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Lets the user quickly cycle between display modes.
	 */
	protected function changeDisplayMode(): void
	{
		$this->mode = ($this->mode + 1) % 3;

		User::updateMemberData(User::$me->id, ['pm_prefs' => (User::$me->pm_prefs & 252) | $this->mode]);

		// Remove 'view' from the query string so that refreshing the browser
		// doesn't cause the view to change again.
		Utils::redirectexit(preg_replace('/\bview;\b/', '', $_SERVER['QUERY_STRING']));
	}

	/**
	 * Helper for prepareMessageContext() that handles subject-only display.
	 */
	protected function prepareSubjectContext(): array
	{
		if (!(PM::$subject_getter instanceof \Generator)) {
			return [];
		}

		$message = PM::$subject_getter->current();
		PM::$subject_getter->next();

		if (!$message) {
			return [];
		}

		return $message->format(0, ['no_custom_fields' => true]);
	}

	/**
	 * Constructs page index, sets next/prev/up links, etc.
	 */
	protected function setPaginationAndLinks()
	{
		// Make sure the starting location is valid.
		if (isset($_GET['start']) && $_GET['start'] != 'new') {
			$_GET['start'] = (int) $_GET['start'];
		} elseif (!isset($_GET['start']) && !empty(Theme::$current->options['view_newest_pm_first'])) {
			$_GET['start'] = 0;
		} else {
			$_GET['start'] = 'new';
		}

		// If they didn't pick a sort order, use the default.
		if (!isset($_GET['sort']) || !isset(self::$sort_methods[$_GET['sort']])) {
			Utils::$context['sort_by'] = 'date';
			$_GET['sort'] = 'pm.id_pm';

			// An overriding setting?
			$this->descending = !empty(Theme::$current->options['view_newest_pm_first']);
		} else {
			Utils::$context['sort_by'] = $_GET['sort'];
			$_GET['sort'] = self::$sort_methods[$_GET['sort']];
			$this->descending = isset($_GET['desc']);
		}

		Utils::$context['sort_direction'] = $this->descending ? 'down' : 'up';

		if ($this->mode == PMAction::VIEW_CONV) {
			$max_messages = Conversation::count($this->is_inbox, $this->current_label_id);
		} elseif (!$this->is_inbox) {
			$max_messages = PM::countSent();
		} else {
			$max_messages = Received::count($this->current_label_id);
		}

		// Start on the last page.
		if (!is_numeric($_GET['start']) || $_GET['start'] >= $max_messages) {
			$_GET['start'] = ($max_messages - 1) - (($max_messages - 1) % $this->per_page);
		} elseif ($_GET['start'] < 0) {
			$_GET['start'] = 0;
		}

		// ... but wait - what if we want to start from a specific message?
		if (isset($_GET['pmid'])) {
			Utils::$context['current_pm'] = $pmID = (int) $_GET['pmid'];

			// With only one page of PM's we're gonna want page 1.
			if ($max_messages <= $this->per_page) {
				$_GET['start'] = 0;
			}
			// If we pass kstart we assume we're in the right place.
			elseif (!isset($_GET['kstart'])) {
				if (!$this->is_inbox) {
					$_GET['start'] = PM::countSent($pmID, $this->descending);
				} else {
					$_GET['start'] = Received::count($this->current_label_id, $pmID, $this->descending);
				}

				// To stop the page index's being abnormal, start the page on the page the message would normally be located on...
				$_GET['start'] = $this->per_page * (int) ($_GET['start'] / $this->per_page);
			}
		}

		// Set up the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=pm;f=' . Utils::$context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . Utils::$context['sort_by'] . ($this->descending ? ';desc' : ''), $_GET['start'], $max_messages, $this->per_page);

		Utils::$context['start'] = $_GET['start'];

		// Determine the navigation context.
		Utils::$context['links'] = [
			'first' => $_GET['start'] >= $this->per_page ? Config::$scripturl . '?action=pm;start=0' : '',
			'prev' => $_GET['start'] >= $this->per_page ? Config::$scripturl . '?action=pm;start=' . ($_GET['start'] - $this->per_page) : '',
			'next' => $_GET['start'] + $this->per_page < $max_messages ? Config::$scripturl . '?action=pm;start=' . ($_GET['start'] + $this->per_page) : '',
			'last' => $_GET['start'] + $this->per_page < $max_messages ? Config::$scripturl . '?action=pm;start=' . (floor(($max_messages - 1) / $this->per_page) * $this->per_page) : '',
			'up' => Config::$scripturl,
		];

		Utils::$context['page_info'] = [
			'current_page' => $_GET['start'] / $this->per_page + 1,
			'num_pages' => floor(($max_messages - 1) / $this->per_page) + 1,
		];

		// Now, build the link tree!
		if ($this->current_label_id == -1) {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?action=pm;f=' . Utils::$context['folder'],
				'name' => $this->is_inbox ? Lang::$txt['inbox'] : Lang::$txt['sent_items'],
			];
		} else {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?action=pm;f=' . Utils::$context['folder'] . ';l=' . $this->current_label_id,
				'name' => Lang::$txt['pm_current_label'] . ': ' . $this->current_label,
			];
		}

		// Set the text to resemble the current folder.
		Lang::$txt['delete_all'] = str_replace('PMBOX', $this->is_inbox ? Lang::$txt['inbox'] : Lang::$txt['sent_items'], Lang::$txt['delete_all']);

		// Only show the button if there are messages to delete.
		Utils::$context['show_delete'] = $max_messages > 0;
	}

	/**
	 * Gets the list of subjects for display.
	 */
	protected function showSubjects(): void
	{
		$query_customizations = ['order' => []];

		if ($this->mode === PMAction::VIEW_CONV) {
			$subject_pms = Conversation::getRecent(
				$this->is_inbox,
				$this->current_label_id,
				$_GET['sort'] ?? 'pm.id_pm',
				true,
				$this->per_page,
				$_GET['start'] ?? 0,
			);

			asort($subject_pms);
		} elseif ($this->is_inbox) {
			$subject_pms = Received::getRecent(
				$this->current_label_id,
				$_GET['sort'] ?? 'pm.id_pm',
				true,
				$this->per_page,
				$_GET['start'] ?? 0,
			);

			sort($subject_pms);
		} else {
			$subject_pms = PM::getRecent(
				$_GET['sort'] ?? 'pm.id_pm',
				true,
				$this->per_page,
				$_GET['start'] ?? 0,
			);

			sort($subject_pms);
		}

		foreach ($subject_pms as $pm) {
			$query_customizations['order'][] = 'pm.id_pm = ' . $pm;
		}

		PM::$subject_getter = !empty($subject_pms) ? PM::get($subject_pms, $query_customizations) : [];
	}

	/**
	 * Gets a conversation for display.
	 *
	 * @return array The IDs of the personal messages to be shown.
	 */
	protected function showConversation(): array
	{
		$id = isset($this->requested_pm) ? $this->requested_pm->id : ($this->is_inbox ? Received::getLatest($this->current_label_id) : PM::getLatest());

		if (empty($id)) {
			PM::$getter = [];

			return [];
		}

		$conversation = new Conversation($id);

		Utils::$context['current_pm'] = $conversation->latest;

		// The templates need some profile data for the senders.
		User::load(array_map(fn ($pm) => $pm['sender'], $conversation->pms));

		// Get the PMs.
		$query_customizations = [
			'order' => ['pm.id_pm' . ($this->descending ? ' DESC' : ' ASC')],
			'limit' => count(array_keys($conversation->pms)),
		];

		if (!$this->is_inbox) {
			$query_customizations['group'] = [
				'pm.id_pm',
				'pm.subject',
				'pm.id_member_from',
				'pm.body',
				'pm.msgtime',
				'pm.from_name',
			];
		}

		PM::$getter = PM::get(array_keys($conversation->pms), $query_customizations);

		$head_pm = current(PM::load($conversation->head));
		$head_pm->format();
		Utils::$context['current_pm_subject'] = $head_pm->formatted['subject'];
		Utils::$context['current_pm_time'] = $head_pm->formatted['time'];
		Utils::$context['current_pm_author'] = $head_pm->formatted['member']['link'];

		// Build the conversation button array.
		Utils::$context['conversation_buttons'] = [
			'delete' => [
				'text' => 'delete_conversation',
				'image' => 'delete.png',
				'url' => Config::$scripturl . '?action=pm;sa=pmactions;pm_actions[' . $conversation->latest . ']=delete;conversation;f=' . Utils::$context['folder'] . ';start=' . Utils::$context['start'] . ($this->current_label_id != -1 ? ';l=' . $this->current_label_id : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'custom' => 'data-confirm="' . Lang::$txt['remove_conversation'] . '"',
				'class' => 'you_sure',
			],
		];

		// Allow mods to add additional buttons here
		IntegrationHook::call('integrate_conversation_buttons');

		return array_keys($conversation->pms);
	}

	/**
	 * Gets a single personal message for display.
	 *
	 * @return array The IDs of the personal messages to be shown.
	 */
	protected function showOne(): array
	{
		$id = isset($this->requested_pm) ? $this->requested_pm->id : ($this->is_inbox ? Received::getLatest($this->current_label_id) : PM::getLatest());

		if (empty($id)) {
			PM::$getter = [];

			return [];
		}

		PM::$getter = PM::get($id);

		Utils::$context['current_pm'] = $id;

		return [$id];
	}

	/**
	 * Gets as many personal messages for display as will fit on one page.
	 *
	 * @return array The IDs of the personal messages to be shown.
	 */
	protected function showAll(): array
	{
		if ($this->is_inbox) {
			$pms = Received::getRecent(
				$this->current_label_id,
				$_GET['sort'] ?? 'pm.id_pm',
				true,
				$this->per_page,
				$_GET['start'] ?? 0,
			);

			if ($this->descending) {
				sort($pms);
			} else {
				rsort($pms);
			}
		} else {
			$pms = PM::getRecent(
				$_GET['sort'] ?? 'pm.id_pm',
				true,
				$this->per_page,
				$_GET['start'] ?? 0,
			);

			if ($this->descending) {
				sort($pms);
			} else {
				rsort($pms);
			}
		}

		foreach ($pms as $pm) {
			$query_customizations['order'][] = 'pm.id_pm = ' . $pm;
		}

		PM::$getter = !empty($pms) ? PM::get($pms, $query_customizations) : [];

		Utils::$context['current_pm'] = reset($pms);

		return $pms;
	}
}

?>