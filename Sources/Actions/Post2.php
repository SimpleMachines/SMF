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

use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Draft;
use SMF\ErrorHandler;
use SMF\Event;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Msg;
use SMF\Poll;
use SMF\Search\SearchApi;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * This class handles posting and modifying replies and new topics.
 */
class Post2 extends Post
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Post2',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The sub-action to call.
	 */
	public string $subaction = 'submit';

	/**
	 * @var bool
	 *
	 * Whether the author of this post is a guest.
	 *
	 * This might not be the same as whether the current user is a guest,
	 * because moderators can edit other people's posts.
	 */
	public bool $authorIsGuest = true;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'submit' => 'submit',
		'show' => 'show',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * An instance of SMF\Msg for the existing post.
	 * Only used when editing a post.
	 */
	protected object $existing_msg;

	/**
	 * @var bool
	 *
	 * Whether this edit is a moderation action.
	 */
	protected bool $moderation_action;

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
		$this->can_approve = User::$me->allowedTo('approve_posts');

		// If there is an existing topic, load it.
		if ($this->intent !== self::INTENT_NEW_TOPIC) {
			$this->loadTopic();
		}

		// Allow mods to add new sub-actions.
		IntegrationHook::call('integrate_post2_subactions', [&self::$subactions]);

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Posts or saves the message composed with Post::show().
	 *
	 * Requires various permissions depending on the action.
	 * Handles attachment, post, and calendar saving.
	 * Sends off notifications, and allows for announcements and moderation.
	 * Accessed from ?action=post2.
	 */
	public function submit(): void
	{
		// Sneaking off, are we?
		if (empty($_POST)) {
			if (!empty(Topic::$topic_id)) {
				Utils::redirectexit('action=post;topic=' . Topic::$topic_id . '.0');
			} elseif (empty($_SERVER['CONTENT_LENGTH'])) {
				Utils::redirectexit('action=post;board=' . Board::$info->id . '.0');
			} else {
				ErrorHandler::fatalLang('post_upload_error', false);
			}
		}

		// No need!
		Utils::$context['robot_no_index'] = true;

		// Prevent double submission of this form.
		Security::checkSubmitOnce('check');

		// No errors as yet.
		$this->errors = [];

		// If the session has timed out, let the user re-submit their form.
		if (User::$me->checkSession('post', '', false) != '') {
			$this->errors[] = 'session_timeout';
		}

		// Wrong verification code?
		$this->checkVerification();

		Lang::load('Post');

		IntegrationHook::call('integrate_post2_start', [&$this->errors]);

		$this->submitAttachments();

		// Replies to unapproved topics are unapproved by default (but not for moderators)
		if (empty(Topic::$info->is_approved) && !$this->can_approve) {
			$this->becomes_approved = false;

			// Set a nice session var...
			$_SESSION['becomesUnapproved'] = true;
		}

		// Replying to a topic?
		if ($this->intent === self::INTENT_NEW_REPLY) {
			$this->prepareNewReply();
		}
		// Posting a new topic.
		elseif ($this->intent === self::INTENT_NEW_TOPIC) {
			$this->prepareNewTopic();
		}
		// Modifying an existing message?
		elseif ($this->intent === self::INTENT_EDIT_POST) {
			$this->prepareEdit();
		}

		// In case we have approval permissions and want to override.
		if ($this->can_approve && Config::$modSettings['postmod_active']) {
			$this->becomes_approved = isset($_POST['quickReply']) || !empty($_REQUEST['approve']) ? 1 : 0;

			$approve_has_changed = isset($this->existing_msg->approved) ? $this->existing_msg->approved != $this->becomes_approved : false;
		}

		// If the poster is a guest evaluate the legality of name and email.
		if ($this->authorIsGuest) {
			$_POST['guestname'] = !isset($_POST['guestname']) ? '' : trim(Utils::normalizeSpaces(Utils::sanitizeChars($_POST['guestname'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));

			$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

			if ($_POST['guestname'] == '' || $_POST['guestname'] == '_') {
				$this->errors[] = 'no_name';
			}

			if (Utils::entityStrlen($_POST['guestname']) > 25) {
				$this->errors[] = 'long_name';
			}

			if (empty(Config::$modSettings['guest_post_no_email'])) {
				// Only check if they changed it!
				if (!isset($this->existing_msg) || $this->existing_msg->poster_email != $_POST['email']) {
					if (!User::$me->allowedTo('moderate_forum') && (!isset($_POST['email']) || $_POST['email'] == '')) {
						$this->errors[] = 'no_email';
					}

					if (!User::$me->allowedTo('moderate_forum') && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
						$this->errors[] = 'bad_email';
					}
				}

				// Now make sure this email address is not banned from posting.
				User::isBannedEmail($_POST['email'], 'cannot_post', sprintf(Lang::$txt['you_are_post_banned'], Lang::$txt['guest_title']));
			}

			// In case they are making multiple posts this visit, help them along by storing their name.
			if (empty($this->errors)) {
				$_SESSION['guest_name'] = $_POST['guestname'];
				$_SESSION['guest_email'] = $_POST['email'];
			}
		}

		// Coming from the quickReply?
		if (isset($_POST['quickReply'])) {
			$_POST['message'] = $_POST['quickReply'];
		}

		// Check the subject and message.
		if (!isset($_POST['subject']) || Utils::htmlTrim(Utils::htmlspecialchars($_POST['subject'])) === '') {
			$this->errors[] = 'no_subject';
		}

		if (!isset($_POST['message']) || Utils::htmlTrim(Utils::htmlspecialchars($_POST['message']), ENT_QUOTES) === '') {
			$this->errors[] = 'no_message';
		} elseif (!empty(Config::$modSettings['max_messageLength']) && Utils::entityStrlen($_POST['message']) > Config::$modSettings['max_messageLength']) {
			$this->errors[] = ['long_message', [Config::$modSettings['max_messageLength']]];
		} else {
			// Prepare the message a bit for some additional testing.
			$_POST['message'] = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);

			// Preparse code. (Zef)
			if (User::$me->is_guest) {
				User::$me->name = $_POST['guestname'];
			}

			Msg::preparsecode($_POST['message']);

			// Let's see if there's still some content left without the tags.
			if (Utils::htmlTrim(strip_tags(BBCodeParser::load()->parse($_POST['message'], false), implode('', Utils::$context['allowed_html_tags']))) === '' && (!User::$me->allowedTo('bbc_html') || strpos($_POST['message'], '[html]') === false)) {
				$this->errors[] = 'no_message';
			}
		}

		if (isset($_POST['calendar']) && !isset($_REQUEST['deleteevent']) && Utils::htmlTrim($_POST['evtitle']) === '') {
			$this->errors[] = 'no_event';
		}

		// You are not!
		if (isset($_POST['message']) && strtolower($_POST['message']) == 'i am the administrator.' && !User::$me->is_admin) {
			ErrorHandler::fatal('Knave! Masquerader! Charlatan!', false);
		}

		// Build the poll...
		if (isset($_REQUEST['poll']) && Config::$modSettings['pollMode'] == '1') {
			if (!empty(Topic::$topic_id) && !isset($_REQUEST['msg'])) {
				ErrorHandler::fatalLang('no_access', false);
			}

			$poll = Poll::create($this->errors);
		}

		if ($this->authorIsGuest) {
			// If user is a guest, make sure the chosen name isn't taken.
			if (User::isReservedName($_POST['guestname'], 0, true, false) && (!isset($this->existing_msg->poster_name) || $_POST['guestname'] != $this->existing_msg->poster_name)) {
				$this->errors[] = 'bad_name';
			}
		}
		// If the user isn't a guest, get his or her name and email.
		elseif (!isset($_REQUEST['msg'])) {
			$_POST['guestname'] = User::$me->username;
			$_POST['email'] = User::$me->email;
		}

		IntegrationHook::call('integrate_post2_pre', [&$this->errors]);

		// Any mistakes?
		if (!empty($this->errors)) {
			// Previewing.
			$_REQUEST['preview'] = true;
			$this->show();

			return;
		}

		// Previewing? Go back to start.
		if (isset($_REQUEST['preview'])) {
			if (User::$me->checkSession('post', '', false) != '') {
				Lang::load('Errors');
				$this->errors[] = 'session_timeout';
				unset($_POST['preview'], $_REQUEST['xml']); // just in case
			}

			$this->show();

			return;
		}

		// Make sure the user isn't spamming the board.
		if (!isset($_REQUEST['msg'])) {
			Security::spamProtection('post');
		}

		// At about this point, we're posting and that's that.
		ignore_user_abort(true);
		@set_time_limit(300);

		// Add special html entities to the subject, name, and email.
		$_POST['subject'] = strtr(Utils::htmlspecialchars($_POST['subject']), ["\r" => '', "\n" => '', "\t" => '']);
		$_POST['guestname'] = Utils::htmlspecialchars($_POST['guestname']);
		$_POST['email'] = Utils::htmlspecialchars($_POST['email']);
		$_POST['modify_reason'] = empty($_POST['modify_reason']) ? '' : strtr(Utils::htmlspecialchars($_POST['modify_reason']), ["\r" => '', "\n" => '', "\t" => '']);

		// At this point, we want to make sure the subject isn't too long.
		if (Utils::entityStrlen($_POST['subject']) > 100) {
			$_POST['subject'] = Utils::entitySubstr($_POST['subject'], 0, 100);
		}

		// Same with the "why did you edit this" text.
		if (Utils::entityStrlen($_POST['modify_reason']) > 100) {
			$_POST['modify_reason'] = Utils::entitySubstr($_POST['modify_reason'], 0, 100);
		}

		// Attach any new files.
		if (Utils::$context['can_post_attachment'] && !empty($_SESSION['temp_attachments']) && empty($_POST['from_qr'])) {
			$attachIDs = [];
			$attach_errors = [];

			if (!empty(Utils::$context['we_are_history'])) {
				$attach_errors[] = '<dd>' . Lang::$txt['error_temp_attachments_flushed'] . '<br><br></dd>';
			}

			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
				if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . User::$me->id) === false) {
					continue;
				}

				// If there was an initial error just show that message.
				if ($attachID == 'initial_error') {
					$attach_errors[] = '<dt>' . Lang::$txt['attach_no_upload'] . '</dt>';
					$attach_errors[] = '<dd>' . (is_array($attachment) ? vsprintf(Lang::$txt[$attachment[0]], (array) $attachment[1]) : Lang::$txt[$attachment]) . '</dd>';

					unset($_SESSION['temp_attachments']);

					break;
				}

				$attachmentOptions = [
					'post' => $_REQUEST['msg'] ?? 0,
					'poster' => User::$me->id,
					'name' => $attachment['name'],
					'tmp_name' => $attachment['tmp_name'],
					'size' => $attachment['size'] ?? 0,
					'mime_type' => $attachment['type'] ?? '',
					'id_folder' => $attachment['id_folder'] ?? Config::$modSettings['currentAttachmentUploadDir'],
					'approved' => !Config::$modSettings['postmod_active'] || User::$me->allowedTo('post_attachment'),
					'errors' => $attachment['errors'],
				];

				if (empty($attachment['errors'])) {
					if (Attachment::create($attachmentOptions)) {
						$attachIDs[] = $attachmentOptions['id'];

						if (!empty($attachmentOptions['thumb'])) {
							$attachIDs[] = $attachmentOptions['thumb'];
						}
					}
				} else {
					$attach_errors[] = '<dt>&nbsp;</dt>';
				}

				if (!empty($attachmentOptions['errors'])) {
					// Sort out the errors for display and delete any associated files.
					$attach_errors[] = '<dt>' . sprintf(Lang::$txt['attach_warning'], $attachment['name']) . '</dt>';

					$log_these = ['attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file'];

					foreach ($attachmentOptions['errors'] as $error) {
						if (!is_array($error)) {
							$attach_errors[] = '<dd>' . Lang::$txt[$error] . '</dd>';

							if (in_array($error, $log_these)) {
								ErrorHandler::log($attachment['name'] . ': ' . Lang::$txt[$error], 'critical');
							}
						} else {
							$attach_errors[] = '<dd>' . vsprintf(Lang::$txt[$error[0]], (array) $error[1]) . '</dd>';
						}
					}

					if (file_exists($attachment['tmp_name'])) {
						unlink($attachment['tmp_name']);
					}
				}
			}
		}
		unset($_SESSION['temp_attachments']);

		// Save the poll to the database.
		if (isset($poll)) {
			$poll->save();
		}

		// Creating a new topic?
		$newTopic = empty($_REQUEST['msg']) && empty(Topic::$topic_id);

		// Check the icon.
		if (!isset($_POST['icon'])) {
			$_POST['icon'] = 'xx';
		} else {
			$_POST['icon'] = Utils::htmlspecialchars($_POST['icon']);

			// Need to figure it out if this is a valid icon name.
			if ((!file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $_POST['icon'] . '.png')) && (!file_exists(Theme::$current->settings['default_theme_dir'] . '/images/post/' . $_POST['icon'] . '.png'))) {
				$_POST['icon'] = 'xx';
			}
		}

		// Collect all parameters for the creation or modification of a post.
		$msgOptions = [
			'id' => empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg'],
			'subject' => $_POST['subject'],
			'body' => $_POST['message'],
			'icon' => preg_replace('~[./\\\\*:"\'<>]~', '', $_POST['icon']),
			'smileys_enabled' => !isset($_POST['ns']),
			'attachments' => empty($attachIDs) ? [] : $attachIDs,
			'approved' => $this->becomes_approved,
		];
		$topicOptions = [
			'id' => empty(Topic::$topic_id) ? 0 : Topic::$topic_id,
			'board' => Board::$info->id,
			'poll' => isset($poll) ? $poll->id : null,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
			'is_approved' => !Config::$modSettings['postmod_active'] || empty(Topic::$topic_id) || !empty(Board::$info->cur_topic_approved),
			'first_msg' => empty(Topic::$info->id_first_msg) ? null : Topic::$info->id_first_msg,
			'last_msg' => empty(Topic::$info->id_last_msg) ? null : Topic::$info->id_last_msg,
		];
		$posterOptions = [
			'id' => User::$me->id,
			'name' => $_POST['guestname'],
			'email' => $_POST['email'],
			'update_post_count' => !User::$me->is_guest && !isset($_REQUEST['msg']) && Board::$info->posts_count,
		];

		// This is an already existing message. Edit it.
		if (!empty($_REQUEST['msg'])) {
			// Have admins allowed people to hide their screwups?
			if (time() - $this->existing_msg->poster_time > Config::$modSettings['edit_wait_time'] || User::$me->id != $this->existing_msg->id_member) {
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = User::$me->name;
				$msgOptions['modify_reason'] = $_POST['modify_reason'];
				$msgOptions['poster_time'] = $this->existing_msg->poster_time;
			}

			Msg::modify($msgOptions, $topicOptions, $posterOptions);
		}
		// This is a new topic or an already existing one. Save it.
		else {
			Msg::create($msgOptions, $topicOptions, $posterOptions);

			if (isset($topicOptions['id'])) {
				Topic::$topic_id = $topicOptions['id'];
			}
		}

		// Are there attachments already uploaded and waiting to be assigned?
		if (!empty($msgOptions['id']) && !empty($_SESSION['already_attached'])) {
			Attachment::assign($_SESSION['already_attached'], $msgOptions['id']);
			unset($_SESSION['already_attached']);
		}

		// If we had a draft for this, its time to remove it since it was just posted
		if (!empty(Config::$modSettings['drafts_post_enabled']) && !empty($_POST['id_draft'])) {
			Draft::delete($_POST['id_draft']);
		}

		// Editing or posting an event?
		if (isset($_POST['calendar']) && (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1)) {
			// Make sure they can link an event to this post.
			Calendar::canLinkEvent();

			// Insert the event.
			$eventOptions = [
				'board' => Board::$info->id,
				'topic' => Topic::$topic_id,
				'title' => $_POST['evtitle'],
				'location' => $_POST['event_location'],
				'member' => User::$me->id,
			];
			Event::create($eventOptions);
		} elseif (isset($_POST['calendar'])) {
			$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

			// Validate the post...
			Calendar::validateEventPost();

			// If you're not allowed to edit any and all events, you have to be the poster.
			if (!User::$me->allowedTo('calendar_edit_any')) {
				User::$me->isAllowedTo('calendar_edit_' . (!empty(User::$me->id) && Calendar::getEventPoster($_REQUEST['eventid']) == User::$me->id ? 'own' : 'any'));
			}

			// Delete it?
			if (isset($_REQUEST['deleteevent'])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}calendar
					WHERE id_event = {int:id_event}',
					[
						'id_event' => $_REQUEST['eventid'],
					],
				);
			}
			// ... or just update it?
			else {
				// Set up our options
				$eventOptions = [
					'board' => Board::$info->id,
					'topic' => Topic::$topic_id,
					'title' => $_POST['evtitle'],
					'location' => $_POST['event_location'],
					'member' => User::$me->id,
				];
				Event::modify($_REQUEST['eventid'], $eventOptions);
			}
		}

		// Marking read should be done even for editing messages....
		// Mark all the parents read.  (since you just posted and they will be unread.)
		if (!User::$me->is_guest && !empty(Board::$info->parent_boards)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_boards
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_board IN ({array_int:board_list})',
				[
					'current_member' => User::$me->id,
					'board_list' => array_keys(Board::$info->parent_boards),
					'id_msg' => Config::$modSettings['maxMsgID'],
				],
			);
		}

		// Turn notification on or off.  (note this just blows smoke if it's already on or off.)
		if (!empty($_POST['notify']) && !User::$me->is_guest) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_notify',
				['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'],
				[User::$me->id, Topic::$topic_id, 0],
				['id_member', 'id_topic', 'id_board'],
			);
		} elseif (!$newTopic) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_notify
				WHERE id_member = {int:current_member}
					AND id_topic = {int:current_topic}',
				[
					'current_member' => User::$me->id,
					'current_topic' => Topic::$topic_id,
				],
			);
		}

		// Log an act of moderation - modifying.
		if (!empty($moderationAction)) {
			Logging::logAction('modify', ['topic' => $this->existing_msg->id_topic, 'message' => $this->existing_msg->id, 'member' => $this->existing_msg->id_member, 'board' => $this->existing_msg->id_board]);
		}

		if (isset($_POST['lock']) && $_POST['lock'] != 2) {
			Logging::logAction(empty($_POST['lock']) ? 'unlock' : 'lock', ['topic' => $topicOptions['id'], 'board' => $topicOptions['board']]);
		}

		if (isset($_POST['sticky'])) {
			Logging::logAction(empty($_POST['sticky']) ? 'unsticky' : 'sticky', ['topic' => $topicOptions['id'], 'board' => $topicOptions['board']]);
		}

		// Returning to the topic?
		if (!empty($_REQUEST['goback'])) {
			// Mark the board as read.... because it might get confusing otherwise.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_boards
				SET id_msg = {int:maxMsgID}
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				[
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'maxMsgID' => Config::$modSettings['maxMsgID'],
				],
			);
		}

		if (Board::$info->num_topics == 0) {
			CacheApi::put('board-' . Board::$info->id, null, 120);
		}

		IntegrationHook::call('integrate_post2_end');

		if (!empty($_POST['announce_topic']) && User::$me->allowedTo('announce_topic')) {
			Utils::redirectexit('action=announce;sa=selectgroup;topic=' . Topic::$topic_id . (!empty($_POST['move']) && User::$me->allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));
		}

		if (!empty($_POST['move']) && User::$me->allowedTo('move_any')) {
			Utils::redirectexit('action=movetopic;topic=' . Topic::$topic_id . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
		}

		// Return to post if the mod is on.
		if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback'])) {
			Utils::redirectexit('topic=' . Topic::$topic_id . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'], BrowserDetector::isBrowser('ie'));
		} elseif (!empty($_REQUEST['goback'])) {
			Utils::redirectexit('topic=' . Topic::$topic_id . '.new#new', BrowserDetector::isBrowser('ie'));
		}
		// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
		else {
			Utils::redirectexit('board=' . Board::$info->id . '.0');
		}
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 *
	 */
	protected function checkVerification(): void
	{
		if (
			!User::$me->is_admin
			&& !User::$me->is_mod
			&& !empty(Config::$modSettings['posts_require_captcha'])
			&& (
				User::$me->posts < Config::$modSettings['posts_require_captcha']
				|| (
					User::$me->is_guest
					&& Config::$modSettings['posts_require_captcha'] == -1
				)
			)
		) {
			$verifier = new Verifier(['id' => 'post']);
			$this->errors = array_merge($this->errors, $verifier->errors);
		}
	}

	/**
	 *
	 */
	protected function submitAttachments(): void
	{
		// First check to see if they are trying to delete any current attachments.
		if (isset($_POST['attach_del'])) {
			$keep_temp = [];
			$keep_ids = [];

			foreach ($_POST['attach_del'] as $dummy) {
				if (strpos($dummy, 'post_tmp_' . User::$me->id) !== false) {
					$keep_temp[] = $dummy;
				} else {
					$keep_ids[] = (int) $dummy;
				}
			}

			if (isset($_SESSION['temp_attachments'])) {
				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
					if (
						(
							isset($_SESSION['temp_attachments']['post']['files'], $attachment['name'])
							&& in_array($attachment['name'], $_SESSION['temp_attachments']['post']['files'])
						)
						|| in_array($attachID, $keep_temp)
						|| strpos($attachID, 'post_tmp_' . User::$me->id) === false
					) {
						continue;
					}

					unset($_SESSION['temp_attachments'][$attachID]);
					unlink($attachment['tmp_name']);
				}
			}

			if (!empty($_REQUEST['msg'])) {
				$attachmentQuery = [
					'attachment_type' => 0,
					'id_msg' => (int) $_REQUEST['msg'],
					'not_id_attach' => $keep_ids,
				];
				Attachment::remove($attachmentQuery);
			}
		}

		// Then try to upload any attachments.
		Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (User::$me->allowedTo('post_attachment') || (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_attachments')));

		if (Utils::$context['can_post_attachment'] && empty($_POST['from_qr'])) {
			Attachment::process();
		}

		// They've already uploaded some attachments, but they don't have permission to post them
		// This can sometimes happen when they came from ?action=calendar;sa=post
		if (!Utils::$context['can_post_attachment'] && !empty($_SESSION['already_attached'])) {
			foreach ($_SESSION['already_attached'] as $attachID => $attachment) {
				Attachment::remove(['id_attach' => $attachID]);
			}

			unset($_SESSION['already_attached']);

			$this->errors[] = ['cannot_post_attachment', [Board::$info->name]];
		}
	}

	/**
	 *
	 */
	protected function prepareNewReply(): void
	{
		// Don't allow a post if it's locked.
		if (Topic::$info->is_locked != 0 && !User::$me->allowedTo('moderate_board')) {
			ErrorHandler::fatalLang('topic_locked', false);
		}

		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (isset($_REQUEST['poll']) && Topic::$info->id_poll > 0) {
			unset($_REQUEST['poll']);
		}

		if (Topic::$info->id_member_started != User::$me->id) {
			if (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_replies_any') && !User::$me->allowedTo('post_reply_any')) {
				$this->becomes_approved = false;
			} else {
				User::$me->isAllowedTo('post_reply_any');
			}
		} elseif (!User::$me->allowedTo('post_reply_any')) {
			if (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_replies_own') && !User::$me->allowedTo('post_reply_own')) {
				$this->becomes_approved = false;
			} else {
				User::$me->isAllowedTo('post_reply_own');
			}
		}

		if (isset($_POST['lock'])) {
			// Nothing is changed to the lock.
			if (empty(Topic::$info->is_locked) == empty($_POST['lock'])) {
				unset($_POST['lock']);
			}
			// You have no permission to lock this topic.
			elseif (!User::$me->allowedTo(['lock_any', 'lock_own']) || (!User::$me->allowedTo('lock_any') && User::$me->id != Topic::$info->id_member_started)) {
				unset($_POST['lock']);
			}
			// You are allowed to (un)lock your own topic only.
			elseif (!User::$me->allowedTo('lock_any')) {
				// You cannot override a moderator lock.
				if (Topic::$info->is_locked == 1) {
					unset($_POST['lock']);
				} else {
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
				}
			}
			// Hail mighty moderator, (un)lock this topic immediately.
			else {
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;

				// Did someone (un)lock this while you were posting?
				if (isset($_POST['already_locked']) && $_POST['already_locked'] != Topic::$info->is_locked) {
					$this->errors[] = 'topic_' . (empty(Topic::$info->is_locked) ? 'un' : '') . 'locked';
				}
			}
		}

		// So you wanna (un)sticky this...let's see.
		if (isset($_POST['sticky']) && ($_POST['sticky'] == Topic::$info->is_sticky || !User::$me->allowedTo('make_sticky'))) {
			unset($_POST['sticky']);
		} elseif (isset($_POST['sticky'])) {
			// Did someone (un)sticky this while you were posting?
			if (isset($_POST['already_sticky']) && $_POST['already_sticky'] != Topic::$info->is_sticky) {
				$this->errors[] = 'topic_' . (empty(Topic::$info->is_sticky) ? 'un' : '') . 'sticky';
			}
		}

		// If drafts are enabled, then pass this off
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft'])) {
			$draft = new Draft((int) $_POST['id_draft']);
			$draft->save($this->errors);
			$this->show();

			return;
		}

		// If the number of replies has changed, if the setting is enabled, go back to Post() - which handles the error.
		if (empty(Theme::$current->options['no_new_reply_warning']) && isset($_POST['last_msg']) && Topic::$info->id_last_msg > $_POST['last_msg']) {
			$_REQUEST['preview'] = true;
			$this->show();

			return;
		}

		$this->authorIsGuest = User::$me->is_guest;
		Utils::$context['is_own_post'] = true;
		Utils::$context['poster_id'] = User::$me->id;
	}

	/**
	 *
	 */
	protected function prepareNewTopic(): void
	{
		// Now don't be silly, new topics will get their own id_msg soon enough.
		unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

		// Do like, the permissions, for safety and stuff...
		$this->becomes_approved = true;

		if (Config::$modSettings['postmod_active'] && !User::$me->allowedTo('post_new') && User::$me->allowedTo('post_unapproved_topics')) {
			$this->becomes_approved = false;
		} else {
			User::$me->isAllowedTo('post_new');
		}

		if (isset($_POST['lock'])) {
			// New topics are by default not locked.
			if (empty($_POST['lock'])) {
				unset($_POST['lock']);
			}
			// Besides, you need permission.
			elseif (!User::$me->allowedTo(['lock_any', 'lock_own'])) {
				unset($_POST['lock']);
			}
			// A moderator-lock (1) can override a user-lock (2).
			else {
				$_POST['lock'] = User::$me->allowedTo('lock_any') ? 1 : 2;
			}
		}

		if (isset($_POST['sticky']) && (empty($_POST['sticky']) || !User::$me->allowedTo('make_sticky'))) {
			unset($_POST['sticky']);
		}

		// Saving your new topic as a draft first?
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft'])) {
			$draft = new Draft((int) $_POST['id_draft']);
			$draft->save($this->errors);
			$this->show();

			return;
		}

		$this->authorIsGuest = User::$me->is_guest;
		Utils::$context['is_own_post'] = true;
		Utils::$context['poster_id'] = User::$me->id;
	}

	/**
	 *
	 */
	protected function prepareEdit(): void
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		$msgs = Msg::load($_REQUEST['msg']);

		if (empty($msgs)) {
			ErrorHandler::fatalLang('cant_find_messages', false);
		}

		$this->existing_msg = current($msgs);

		if (!empty(Topic::$info->is_locked) && !User::$me->allowedTo('moderate_board')) {
			ErrorHandler::fatalLang('topic_locked', false);
		}

		if (isset($_POST['lock'])) {
			// Nothing changes to the lock status.
			if ((empty($_POST['lock']) && empty(Topic::$info->is_locked)) || (!empty($_POST['lock']) && !empty(Topic::$info->is_locked))) {
				unset($_POST['lock']);
			}
			// You're simply not allowed to (un)lock this.
			elseif (!User::$me->allowedTo(['lock_any', 'lock_own']) || (!User::$me->allowedTo('lock_any') && User::$me->id != Topic::$info->id_member_started)) {
				unset($_POST['lock']);
			}
			// You're only allowed to lock your own topics.
			elseif (!User::$me->allowedTo('lock_any')) {
				// You're not allowed to break a moderator's lock.
				if (Topic::$info->is_locked == 1) {
					unset($_POST['lock']);
				}
				// Lock it with a soft lock or unlock it.
				else {
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
				}
			}
			// You must be the moderator.
			else {
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;

				// Did someone (un)lock this while you were posting?
				if (isset($_POST['already_locked']) && $_POST['already_locked'] != Topic::$info->is_locked) {
					$this->errors[] = 'topic_' . (empty(Topic::$info->is_locked) ? 'un' : '') . 'locked';
				}
			}
		}

		// Change the sticky status of this topic?
		if (isset($_POST['sticky']) && (!User::$me->allowedTo('make_sticky') || $_POST['sticky'] == Topic::$info->is_sticky)) {
			unset($_POST['sticky']);
		} elseif (isset($_POST['sticky'])) {
			// Did someone (un)sticky this while you were posting?
			if (isset($_POST['already_sticky']) && $_POST['already_sticky'] != Topic::$info->is_sticky) {
				$this->errors[] = 'topic_' . (empty(Topic::$info->is_locked) ? 'un' : '') . 'stickied';
			}
		}

		if ($this->existing_msg->id_member == User::$me->id && !User::$me->allowedTo('modify_any')) {
			if (
				(
					!Config::$modSettings['postmod_active']
					|| $this->existing_msg->approved
				)
				&& !empty(Config::$modSettings['edit_disable_time'])
				&& $this->existing_msg->poster_time + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time()
			) {
				ErrorHandler::fatalLang('modify_post_time_passed', false);
			} elseif (Topic::$info->id_member_started == User::$me->id && !User::$me->allowedTo('modify_own')) {
				User::$me->isAllowedTo('modify_replies');
			} else {
				User::$me->isAllowedTo('modify_own');
			}
		} elseif (Topic::$info->id_member_started == User::$me->id && !User::$me->allowedTo('modify_any')) {
			User::$me->isAllowedTo('modify_replies');

			// If you're modifying a reply, I say it better be logged...
			$moderationAction = true;
		} else {
			User::$me->isAllowedTo('modify_any');

			// Log it, assuming you're not modifying your own post.
			if ($this->existing_msg->id_member != User::$me->id) {
				$moderationAction = true;
			}
		}

		// If drafts are enabled, then lets send this off to save
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft'])) {
			$draft = new Draft((int) $_POST['id_draft']);
			$draft->save($this->errors);
			$this->show();

			return;
		}

		$this->authorIsGuest = empty($this->existing_msg->id_member);
		Utils::$context['is_own_post'] = User::$me->id === $this->existing_msg->id_member;
		Utils::$context['poster_id'] = $this->existing_msg->id_member;

		// Can they approve it?
		$approve_checked = (!empty($REQUEST['approve']) ? 1 : 0);
		$this->becomes_approved = Config::$modSettings['postmod_active'] ? ($this->can_approve && !$this->existing_msg->approved ? $approve_checked : $this->existing_msg->approved) : 1;

		if (!User::$me->allowedTo('moderate_forum') || !$this->authorIsGuest) {
			$_POST['guestname'] = $this->existing_msg->poster_name;
			$_POST['email'] = $this->existing_msg->poster_email;
		}

		// Update search api
		$searchAPI = SearchApi::load();

		if ($searchAPI->supportsMethod('postRemoved')) {
			$searchAPI->postRemoved($_REQUEST['msg']);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Post2::exportStatic')) {
	Post2::exportStatic();
}

?>