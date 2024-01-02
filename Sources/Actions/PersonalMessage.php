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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\PersonalMessage\{
	Conversation,
	DraftPM,
	Folder,
	Label,
	PM,
	Popup,
	Received,
	Rule,
	Search,
	SearchResult,
};
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking personal
 * messages.
 */
class PersonalMessage implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'MessageMain',
			'messageFolder' => 'MessageFolder',
			'messagePopup' => 'MessagePopup',
			'manageLabels' => 'ManageLabels',
			'manageRules' => 'ManageRules',
			'messageActionsApply' => 'MessageActionsApply',
			'messagePrune' => 'MessagePrune',
			'messageKillAll' => 'MessageKillAll',
			'reportMessage' => 'ReportMessage',
			'messageSearch' => 'MessageSearch',
			'messageSearch2' => 'MessageSearch2',
			'messagePost' => 'MessagePost',
			'messagePost2' => 'MessagePost2',
			'messageSettings' => 'MessageSettings',
			'messageDrafts' => 'MessageDrafts',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Display mode to show all personal messages in a paginated list.
	 */
	public const VIEW_ALL = 0;

	/**
	 * Display mode to show one personal message at a time.
	 */
	public const VIEW_ONE = 1;

	/**
	 * Display mode to show personal messages in a conversation view.
	 */
	public const VIEW_CONV = 2;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Defines the menu structure for the personal message action.
	 * See {@link Menu.php Menu.php} for details!
	 *
	 * The values of all 'title' and 'label' elements are Lang::$txt keys, and
	 * will be replaced at runtime with the values of those Lang::$txt strings.
	 *
	 * All occurrences of '{scripturl}' and '{boardurl}' in value strings will
	 * be replaced at runtime with the real values of Config::$scripturl and
	 * Config::$boardurl.
	 *
	 * In this default definintion, all parts of the menu are set as enabled.
	 * At runtime, however, various parts may be turned on or off.
	 */
	public array $pm_areas = [
		'folders' => [
			'title' => 'pm_messages',
			'areas' => [
				'inbox' => [
					'label' => 'inbox',
					'custom_url' => '{scripturl}?action=pm',
					'amt' => 0,
				],
				'send' => [
					'label' => 'new_message',
					'custom_url' => '{scripturl}?action=pm;sa=send',
					'permission' => 'pm_send',
					'amt' => 0,
				],
				'sent' => [
					'label' => 'sent_items',
					'custom_url' => '{scripturl}?action=pm;f=sent',
					'amt' => 0,
				],
				'drafts' => [
					'label' => 'drafts_show',
					'custom_url' => '{scripturl}?action=pm;sa=showpmdrafts',
					'permission' => 'pm_draft',
					'enabled' => true,
					'amt' => 0,
				],
			],
			'amt' => 0,
		],
		'labels' => [
			'title' => 'pm_labels',
			'areas' => [],
			'amt' => 0,
		],
		'actions' => [
			'title' => 'pm_actions',
			'areas' => [
				'search' => [
					'label' => 'pm_search_bar_title',
					'custom_url' => '{scripturl}?action=pm;sa=search',
				],
				'prune' => [
					'label' => 'pm_prune',
					'custom_url' => '{scripturl}?action=pm;sa=prune',
				],
			],
		],
		'pref' => [
			'title' => 'pm_preferences',
			'areas' => [
				'manlabels' => [
					'label' => 'pm_manage_labels',
					'custom_url' => '{scripturl}?action=pm;sa=manlabels',
				],
				'manrules' => [
					'label' => 'pm_manage_rules',
					'custom_url' => '{scripturl}?action=pm;sa=manrules',
				],
				'settings' => [
					'label' => 'pm_settings',
					'custom_url' => '{scripturl}?action=pm;sa=settings',
				],
			],
		],
	];

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'show';

	/**
	 * @var string
	 *
	 * The folder being viewed. Either 'inbox' or 'sent'.
	 */
	public string $folder = 'inbox';

	/**
	 * @var int
	 *
	 * The display mode we are acutally using.
	 */
	public int $mode = self::VIEW_CONV;

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
	 * @var array
	 *
	 * Labels that have been applied to a collection of PMs.
	 *
	 * Keys are the IDs of some PMs. Values are arrays of label IDs.
	 */
	public array $labels_in_use = [];

	/**
	 * @var array
	 *
	 * Whether the current user has replied to a collection of PMs.
	 *
	 * Keys are the IDs of some PMs. Values are booleans.
	 */
	public array $replied = [];

	/**
	 * @var array
	 *
	 * Whether the current user has read a collection of PMs.
	 *
	 * Keys are the IDs of some PMs. Values are booleans.
	 */
	public array $unread = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'show' => 'show',
		'popup' => 'popup',
		'showpmdrafts' => 'drafts',
		'send' => 'send',
		'send2' => 'send2',
		'search' => 'search',
		'search2' => 'search2',
		'pmactions' => 'applyActions',
		'removeall2' => 'removeAll',
		'prune' => 'prune',
		'report' => 'report',
		'manlabels' => 'labels',
		'manrules' => 'rules',
		'settings' => 'settings',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * URL to redirect to for the current label.
	 */
	protected string $current_label_redirect;

	/****************************
	 * Internal static properties
	 ****************************/

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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// No guests!
		User::$me->kickIfGuest();

		// You're not supposed to be here at all, if you can't even read PMs.
		User::$me->isAllowedTo('pm_read');

		// If we have unsorted mail, apply our rules!
		if (User::$me->new_pm) {
			Rule::apply();
			Received::setNotNew();
		}

		// No menu in AJAX requests or the popup.
		if (!isset($_REQUEST['xml']) && $this->subaction !== 'popup') {
			if ($this->subaction === 'show') {
				$this->createMenu($this->current_label_id == -1 ? $this->folder : 'label' . $this->current_label_id);
			} else {
				$this->createMenu($this->subaction);
			}
		}

		// Now let's get on with the main job...
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Shows the personal messages in a folder (i.e. "inbox" or "sent items")
	 */
	public function show(): void
	{
		(new Folder($this->folder === 'inbox'))->show();
	}

	/**
	 * The popup for when we ask for the popup from the user.
	 */
	public function popup(): void
	{
		(new Popup())->show();
	}

	/**
	 * Allows the user to view their PM drafts.
	 */
	public function drafts(): void
	{
		if (empty(User::$me->id)) {
			ErrorHandler::fatalLang('not_a_user', false);
		}

		DraftPM::showInProfile(User::$me->id);
	}

	/**
	 * Send a new message?
	 */
	public function send(): void
	{
		PM::compose();
	}

	/**
	 * Send it!
	 */
	public function send2(): void
	{
		// Message sent successfully?
		if (PM::compose2()) {
			Utils::redirectexit($this->current_label_redirect . ';done=sent');
		}
	}

	/**
	 * Allows searching through personal messages.
	 */
	public function search(): void
	{
		(new Search($this->folder === 'inbox'))->showForm();
	}

	/**
	 * Actually do the search of personal messages.
	 */
	public function search2(): void
	{
		(new Search($this->folder === 'inbox'))->performSearch();
	}

	/**
	 * This method performs all additional stuff...
	 */
	public function applyActions(): void
	{
		User::$me->checkSession('request');

		if (isset($_REQUEST['del_selected'])) {
			$_REQUEST['pm_action'] = 'delete';
		}

		if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms'])) {
			foreach ($_REQUEST['pms'] as $pm) {
				$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
			}
		}

		if (empty($_REQUEST['pm_actions'])) {
			Utils::redirectexit($this->current_label_redirect);
		}

		// Don't act on a conversation unless the view mode and the $_REQUEST var match.
		if (($this->mode == self::VIEW_CONV) != (isset($_REQUEST['conversation']))) {
			Utils::redirectexit($this->current_label_redirect);
		}

		// Don't do labels unless we're in the inbox.
		if ($this->folder !== 'inbox') {
			$_REQUEST['pm_actions'] = array_filter($_REQUEST['pm_actions'], fn ($action) => $action === 'delete');
		}

		// If we are in conversation, we may need to apply this to every PM in the conversation.
		if ($this->mode == self::VIEW_CONV) {
			foreach (array_keys($_REQUEST['pm_actions']) as $pm) {
				$conversation = new Conversation($pm);

				foreach ($conversation->pms as $id => $info) {
					// We only label received PMs, not sent ones.
					if ($_REQUEST['pm_actions'][$pm] == 'delete' || $info['sender'] != User::$me->id) {
						$_REQUEST['pm_actions'][$id] = $_REQUEST['pm_actions'][$pm];
					}
				}
			}
		}

		$to_delete = [];
		$to_label = [];
		$label_type = [];

		foreach ($_REQUEST['pm_actions'] as $pm => $action) {
			// Deleting.
			if ($action === 'delete') {
				$to_delete[] = (int) $pm;
			}
			// Adding a label.
			elseif (substr($action, 0, 4) == 'add_') {
				$type = 'add';
				$action = substr($action, 4);
			}
			// Removing a label.
			elseif (substr($action, 0, 4) == 'rem_') {
				$type = 'rem';
				$action = substr($action, 4);
			}

			if (in_array($type, ['add', 'rem']) && ($action == '-1' || (int) $action > 0)) {
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}

		// Deleting, it looks like?
		if (!empty($to_delete)) {
			PM::delete($to_delete, $this->folder);
		}

		// Are we labeling anything?
		if (!empty($to_label) && $this->folder === 'inbox') {
			foreach (Received::loadByPm(array_keys($to_label)) as $received) {
				if ($label_type[$received->id] === 'add') {
					$received->addLabel($to_label[$received->id]);
				} elseif ($label_type[$received->id] === 'rem') {
					$received->removeLabel($to_label[$received->id]);
				}
			}
		}

		// Back to the folder.
		$_SESSION['pm_selected'] = array_keys($to_label);

		Utils::redirectexit($this->current_label_redirect . (count($to_label) == 1 ? '#msg' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && BrowserDetector::isBrowser('ie'));
	}

	/**
	 * Delete ALL the messages!
	 */
	public function removeAll(): void
	{
		User::$me->checkSession();

		PM::delete(null, null);

		// Done... all gone.
		Utils::redirectexit($this->current_label_redirect);
	}

	/**
	 * This function allows the user to delete all messages older than so many days.
	 */
	public function prune(): void
	{
		if (isset($_REQUEST['age'])) {
			User::$me->checkSession();

			// Calculate the time to delete before.
			$delete_time = max(0, time() - (86400 * (int) $_REQUEST['age']));

			// Delete the messages.
			PM::delete(array_merge(PM::old($delete_time), Received::old($delete_time)));

			// Go back to their inbox.
			Utils::redirectexit($this->current_label_redirect);
		}

		// Build the link tree elements.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=prune',
			'name' => Lang::$txt['pm_prune'],
		];

		Utils::$context['sub_template'] = 'prune';
		Utils::$context['page_title'] = Lang::$txt['pm_prune'];
	}

	/**
	 * Allows the user to report a personal message to an administrator.
	 *
	 * - In the first instance requires that the ID of the message to report is passed through $_GET.
	 * - It allows the user to report to either a particular administrator - or the whole admin team.
	 * - It will forward on a copy of the original message without allowing the reporter to make changes.
	 *
	 * @uses template_report_message()
	 */
	public function report(): void
	{
		// Check that this feature is even enabled!
		if (empty(Config::$modSettings['enableReportPM']) || empty($_REQUEST['pmsg'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$pm = current(PM::load((int) $_REQUEST['pmsg']));

		// Users are not allowed to report messages that they can't see.
		if (!$pm->canAccess('inbox')) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Utils::$context['pm_id'] = $pm->id;
		Utils::$context['page_title'] = Lang::$txt['pm_report_title'];

		// If we're here, just send the user to the template, with a few useful context bits.
		if (!isset($_POST['report'])) {
			Utils::$context['sub_template'] = 'report_message';

			// @todo I don't like being able to pick who to send it to.  Favoritism, etc. sucks.
			// Now, get all the administrators.
			Utils::$context['admins'] = [];
			$request = Db::$db->query(
				'',
				'SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
				ORDER BY real_name',
				[
					'admin_group' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['admins'][$row['id_member']] = $row['real_name'];
			}
			Db::$db->free_result($request);

			// How many admins in total?
			Utils::$context['admin_count'] = count(Utils::$context['admins']);
		}
		// Otherwise, let's get down to the sending stuff.
		else {
			// Check the session before proceeding any further!
			User::$me->checkSession();

			// Remove the line breaks...
			$body = preg_replace('~<br ?/?' . '>~i', "\n", $this->body);

			// Get any other recipients of the email.
			$recipients = [];
			$hidden_recipients = 0;

			foreach ($pm->received as $received) {
				if ($received->member === User::$me->id) {
					continue;
				}

				// If it's hidden still don't reveal their names - privacy after all ;)
				if (!empty($received->bcc)) {
					$hidden_recipients++;
				} else {
					$recipients[] = '[url=' . Config::$scripturl . '?action=profile;u=' . $received->member . ']' . $received->name . '[/url]';
				}
			}

			if ($hidden_recipients) {
				$recipients[] = sprintf(Lang::$txt['pm_report_pm_hidden'], $hidden_recipients);
			}

			// Prepare the message storage array.
			$messagesToSend = [];

			// Now let's get out and loop through the admins.
			$memberFromName = Utils::htmlspecialcharsDecode($this->from_name);
			$request = Db::$db->query(
				'',
				'SELECT id_member, real_name, lngfile
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_id} OR FIND_IN_SET({int:admin_id}, additional_groups) != 0)
					' . (empty($_POST['id_admin']) ? '' : 'AND id_member = {int:specific_admin}') . '
				ORDER BY lngfile',
				[
					'admin_id' => 1,
					'specific_admin' => isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0,
				],
			);

			// Maybe we shouldn't advertise this?
			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Loop through each admin, and add them to the right language pile...
			while ($row = Db::$db->fetch_assoc($request)) {
				// Need to send in the correct language!
				$cur_language = empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile'];

				if (!isset($messagesToSend[$cur_language])) {
					Lang::load('PersonalMessage', $cur_language, false);

					// Make the body.
					$report_body = str_replace(['{REPORTER}', '{SENDER}'], [Utils::htmlspecialcharsDecode(User::$me->name), $memberFromName], Lang::$txt['pm_report_pm_user_sent']);

					$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";

					if (!empty($recipients)) {
						$report_body .= Lang::$txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
					}

					$report_body .= Lang::$txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($this->member_from) ? '"' . $memberFromName . '"' : $memberFromName . ' link=action=profile;u=' . $this->member_from . ' date=' . $this->msgtime) . ']' . "\n" . Utils::htmlspecialcharsDecode($body) . '[/quote]';

					// Plonk it in the array ;)
					$messagesToSend[$cur_language] = [
						'subject' => (Utils::entityStrpos($this->subject, Lang::$txt['pm_report_pm_subject']) === false ? Lang::$txt['pm_report_pm_subject'] : '') . Utils::htmlspecialcharsDecode($this->subject),
						'body' => $report_body,
						'recipients' => [
							'to' => [],
							'bcc' => [],
						],
					];
				}

				// Add them to the list.
				$messagesToSend[$cur_language]['recipients']['to'][$row['id_member']] = $row['id_member'];
			}
			Db::$db->free_result($request);

			// Send a different email for each language.
			foreach ($messagesToSend as $lang => $message) {
				PM::send($message['recipients'], $message['subject'], $message['body']);
			}

			// Give the user their own language back!
			if (!empty(Config::$modSettings['userLanguage'])) {
				Lang::load('PersonalMessage', '', false);
			}

			// Leave them with a template.
			Utils::$context['sub_template'] = 'report_message_complete';
		}
	}

	/**
	 * Handles adding, deleting and editing labels on messages.
	 */
	public function labels(): void
	{
		Label::manage();
	}

	/**
	 * List all rules, and allow adding/entering etc...
	 */
	public function rules(): void
	{
		Rule::manage();
	}

	/**
	 * Allows to edit Personal Message Settings.
	 *
	 * Uses Actions/Profile/Main.php
	 * Uses Profile-Modify.php
	 * Uses Profile template.
	 * Uses Profile language file.
	 */
	public function settings(): void
	{
		// We want them to submit back to here.
		Utils::$context['profile_custom_submit_url'] = Config::$scripturl . '?action=pm;sa=settings;save';

		Profile::load(User::$me->id);

		Lang::load('Profile');
		Theme::loadTemplate('Profile');

		// Since this is internally handled with the profile code because that's how
		// it was done ages ago, we have to set everything up for handling this...
		Utils::$context['page_title'] = Lang::$txt['pm_settings'];
		User::$me->is_owner = true;
		Utils::$context['id_member'] = User::$me->id;
		Utils::$context['require_password'] = false;
		Utils::$context['menu_item_selected'] = 'settings';
		Utils::$context['submit_button_text'] = Lang::$txt['pm_settings'];
		Utils::$context['profile_header_text'] = Lang::$txt['personal_messages'];
		Utils::$context['sub_template'] = 'edit_options';
		Utils::$context['page_desc'] = Lang::$txt['pm_settings_desc'];

		Profile::$member->loadThemeOptions();
		Profile::$member->loadCustomFields('pmprefs');

		// Add our position to the linktree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=settings',
			'name' => Lang::$txt['pm_settings'],
		];

		// Are they saving?
		if (isset($_REQUEST['save'])) {
			User::$me->checkSession();
			Profile::$member->save();
		}

		Profile::$member->setupContext(['pm_prefs']);
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
	 * Backward compatibility wrapper for the show sub-action.
	 */
	public static function messageFolder(): void
	{
		self::load();
		self::$obj->subaction = 'show';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the popup sub-action.
	 */
	public static function messagePopup(): void
	{
		self::load();
		self::$obj->subaction = 'popup';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the manlabels sub-action.
	 */
	public static function manageLabels(): void
	{
		self::load();
		self::$obj->subaction = 'manlabels';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the manrules sub-action.
	 */
	public static function manageRules(): void
	{
		self::load();
		self::$obj->subaction = 'manrules';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the pmactions sub-action.
	 */
	public static function messageActionsApply(): void
	{
		self::load();
		self::$obj->subaction = 'pmactions';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the prune sub-action.
	 */
	public static function messagePrune(): void
	{
		self::load();
		self::$obj->subaction = 'prune';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the removeall2 sub-action.
	 */
	public static function messageKillAll(): void
	{
		self::load();
		self::$obj->subaction = 'removeall2';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the report sub-action.
	 */
	public static function reportMessage(): void
	{
		self::load();
		self::$obj->subaction = 'report';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the search sub-action.
	 */
	public static function messageSearch(): void
	{
		self::load();
		self::$obj->subaction = 'search';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the search2 sub-action.
	 */
	public static function messageSearch2(): void
	{
		self::load();
		self::$obj->subaction = 'search2';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the send sub-action.
	 */
	public static function messagePost(): void
	{
		self::load();
		self::$obj->subaction = 'send';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the send2 sub-action.
	 */
	public static function messagePost2(): void
	{
		self::load();
		self::$obj->subaction = 'send2';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 */
	public static function messageSettings(): void
	{
		self::load();
		self::$obj->subaction = 'settings';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the showpmdrafts sub-action.
	 */
	public static function messageDrafts(): void
	{
		self::load();
		self::$obj->subaction = 'showpmdrafts';
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
		Lang::load('PersonalMessage+Drafts');

		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('PersonalMessage');
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		if (isset($_REQUEST['f']) && $_REQUEST['f'] === 'sent') {
			$this->folder = 'sent';
		}

		$this->buildLimitBar();

		Label::load();

		// Some stuff for the labels...
		$this->current_label_id = isset($_REQUEST['l']) && isset(Label::$loaded[$_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
		$this->current_label = Label::$loaded[$this->current_label_id]['name'];

		// This is convenient.  Do you know how annoying it is to do this every time?!
		$this->current_label_redirect = 'action=pm;f=' . $this->folder . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . $_REQUEST['l'] : '');

		// Preferences...
		$this->mode = User::$me->pm_prefs & 3;

		// A previous message was sent successfully? Show a small indication.
		if (isset($_GET['done']) && ($_GET['done'] == 'sent')) {
			Utils::$context['pm_sent'] = true;
		}

		// Some context stuff for the templates.
		Utils::$context['display_mode'] = &$this->mode;
		Utils::$context['folder'] = &$this->folder;
		Utils::$context['currently_using_labels'] = !empty(Label::$loaded);
		Utils::$context['current_label_id'] = &$this->current_label_id;
		Utils::$context['current_label'] = &$this->current_label;
		Utils::$context['can_issue_warning'] = User::$me->allowedTo('issue_warning') && Config::$modSettings['warning_settings'][0] == 1;
		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');

		// Are PM drafts enabled?
		Utils::$context['drafts_type'] = 'pm';
		Utils::$context['drafts_save'] = !empty(Config::$modSettings['drafts_pm_enabled']) && User::$me->allowedTo('pm_draft');
		Utils::$context['drafts_autosave'] = !empty(Utils::$context['drafts_save']) && !empty(Config::$modSettings['drafts_autosave_enabled']) && !empty(Theme::$current->options['drafts_autosave_enabled']);

		// Build the linktree for all the actions...
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm',
			'name' => Lang::$txt['personal_messages'],
		];
	}

	/**
	 * A menu to easily access different areas of the PM section
	 *
	 * @param string $area The area we're currently in
	 */
	protected function createMenu($area): void
	{
		// Finalize string values in the menu.
		array_walk_recursive(
			$this->pm_areas,
			function (&$value, $key) {
				if (in_array($key, ['title', 'label'])) {
					$value = Lang::$txt[$value] ?? $value;
				}

				$value = strtr($value, [
					'{scripturl}' => Config::$scripturl,
					'{boardurl}' => Config::$boardurl,
				]);
			},
		);

		$this->pm_areas['folders']['areas']['drafts']['enabled'] = !empty(Config::$modSettings['drafts_pm_enabled']);

		// Give mods access to the menu.
		IntegrationHook::call('integrate_pm_areas', [&$this->pm_areas]);

		// Handle labels.
		if (empty(Label::$loaded)) {
			unset($this->pm_areas['labels']);
		} else {
			// Note we send labels by id as it will have less problems in the querystring.
			foreach (Label::$loaded as $label) {
				if ($label['id'] == -1) {
					continue;
				}

				// Count the amount of unread items in labels.
				$this->pm_areas['labels']['amt'] += $label['unread_messages'];

				// Add the label to the menu.
				$this->pm_areas['labels']['areas']['label' . $label['id']] = [
					'label' => $label['name'],
					'custom_url' => Config::$scripturl . '?action=pm;l=' . $label['id'],
					'amt' => $label['unread_messages'],
					'unread_messages' => $label['unread_messages'],
					'messages' => $label['messages'],
					'icon' => 'folder',
				];
			}
		}

		$this->pm_areas['folders']['areas']['inbox']['unread_messages'] = Label::$loaded[-1]['unread_messages'];

		$this->pm_areas['folders']['areas']['inbox']['messages'] = Label::$loaded[-1]['messages'];

		if (!empty(Label::$loaded[-1]['unread_messages'])) {
			$this->pm_areas['folders']['areas']['inbox']['amt'] = Label::$loaded[-1]['unread_messages'];

			$this->pm_areas['folders']['amt'] = Label::$loaded[-1]['unread_messages'];
		}

		// Set a few options for the menu.
		$menuOptions = [
			'current_area' => $area,
			'disable_url_session_check' => true,
		];

		// Actually create the menu!
		$menu = new Menu($this->pm_areas, $menuOptions);

		// No menu means no access.
		if (empty($menu->include_data) && (!User::$me->is_guest || User::$me->validateSession())) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Make a note of the Unique ID for this menu.
		Utils::$context['pm_menu_id'] = $menu->id;
		Utils::$context['pm_menu_name'] = $menu->name;

		// Set the selected item.
		Utils::$context['menu_item_selected'] = $menu->current_area;

		// Set the template for this area and add the profile layer.
		if (!isset($_REQUEST['xml'])) {
			Utils::$context['template_layers'][] = 'pm';
		}
	}

	/**
	 * Figures out the limit for how many PMs this user can have.
	 */
	protected function buildLimitBar()
	{
		if (User::$me->is_admin) {
			return;
		}

		if (($limit = CacheApi::get('msgLimit:' . User::$me->id, 360)) === null) {
			// @todo Why do we do this?  It seems like if they have any limit we should use it.
			$request = Db::$db->query(
				'',
				'SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
				FROM {db_prefix}membergroups
				WHERE id_group IN ({array_int:users_groups})',
				[
					'users_groups' => User::$me->groups,
				],
			);
			list($maxMessage, $minMessage) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$limit = $minMessage == 0 ? 0 : $maxMessage;

			// Save us doing it again!
			CacheApi::put('msgLimit:' . User::$me->id, $limit, 360);
		}

		// Prepare the context for the capacity bar.
		if (!empty($limit)) {
			$bar = round((User::$me->messages * 100) / $limit, 1);

			Utils::$context['limit_bar'] = [
				'messages' => User::$me->messages,
				'allowed' => $limit,
				'percent' => $bar,
				'bar' => min(100, (int) $bar),
				'text' => sprintf(Lang::$txt['pm_currently_using'], User::$me->messages, $bar),
			];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\PersonalMessage::exportStatic')) {
	PersonalMessage::exportStatic();
}

?>