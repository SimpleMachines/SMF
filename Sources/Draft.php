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
 * Represents a post draft.
 *
 * This class contains all the static methods that allow for saving,
 * retrieving, and deleting drafts.
 */
class Draft
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'delete' => 'DeleteDraft',
			'showInEditor' => 'ShowDrafts',
			'showInProfile' => 'showProfileDrafts',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The ID number of this draft.
	 */
	public int $id = 0;

	/**
	 * @var int
	 *
	 * The type of this draft.
	 * 0 = post; 1 = personal message.
	 */
	public int $type = 0;

	/**
	 * @var int
	 *
	 * ID of the author of this draft.
	 */
	public int $member = 0;

	/**
	 * @var int
	 *
	 * When this draft was created.
	 */
	public int $poster_time = 0;

	/**
	 * @var string
	 *
	 * The subject of this draft.
	 */
	public string $subject = '';

	/**
	 * @var string
	 *
	 * The body of this draft.
	 */
	public string $body = '';

	/**
	 * @var bool
	 *
	 * Whether smileys are enabled in this draft.
	 */
	public bool $smileys_enabled = true;

	/**
	 * @var bool
	 *
	 * Whether this draft post is intended to be stickied when posted.
	 * Only applies to post drafts.
	 */
	public bool $sticky = false;

	/**
	 * @var bool
	 *
	 * Whether this draft post is intended to be locked when posted.
	 * Only applies to post drafts.
	 */
	public bool $locked = false;

	/**
	 * @var string
	 *
	 * The icon of this draft.
	 * Only applies to post drafts.
	 */
	public string $icon = 'xx';

	/**
	 * @var int
	 *
	 * ID of the topic that this draft is intended to be posted in.
	 * Only applies to post drafts.
	 */
	public int $topic = 0;

	/**
	 * @var int
	 *
	 * ID of the board that this draft is intended to be posted in.
	 * Only applies to post drafts.
	 */
	public int $board = 0;

	/**
	 * @var int
	 *
	 * ID of the personal message that this draft is replying to.
	 * Only applies to personal message drafts.
	 */
	public int $reply_to = 0;

	/**
	 * @var array
	 *
	 * Intended recipients for this draft.
	 * Only applies to personal message drafts.
	 */
	public array $recipients = [
		'to' => [],
		'bcc' => [],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Config::$modSettings setting that enables/disables this type of draft.
	 */
	protected string $enabled_setting = 'drafts_post_enabled';

	/**
	 * @var string
	 *
	 * Permission that allows the user to save this type of draft.
	 */
	protected string $permission = 'post_draft';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id_draft ID of the draft to load.
	 * @param bool $check Validate that this draft belongs to the current user.
	 * @param array $recipientList Only used by the DraftPM class.
	 */
	public function __construct(int $id_draft = 0, bool $check = true, array $recipientList = [])
	{
		$this->id = $id_draft;

		// Load any existing data for this draft.
		if (!empty($this->id)) {
			$draft_info = self::read($check);

			// Requested a draft that doesn't exist or belongs to someone else.
			if (empty($draft_info['id_draft'])) {
				$this->id = 0;
			} else {
				// Set the properties according to the existing data.
				foreach ($draft_info as $key => $value) {
					switch ($key) {
						case 'id_draft':
							$this->id = $value;
							break;

						case 'id_topic':
						case 'id_board':
						case 'id_member':
						case 'is_sticky':
							$this->{substr($key, 3)} = $value;
							break;

						case 'id_reply':
							$this->reply_to = $value;
							break;

						case 'to_list':
							$recipientsList = Utils::jsonDecode($draft_info['to_list'], true);
							$this->recipients['to'] = $recipientsList['to'] ?? [];
							$this->recipients['bcc'] = $recipientsList['bcc'] ?? [];
							break;

						default:
							$this->$key = $value;
							break;
					}
				}
			}
		}

		// Determine who this is being sent to.
		if (isset($_REQUEST['xml'])) {
			$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : [];
			$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : [];
		} elseif (!empty($recipientList)) {
			$recipientList['to'] = $recipientList['to'] ?? [];
			$recipientList['bcc'] = $recipientList['bcc'] ?? [];
		}

		$this->setProperties($recipientList);
	}

	/**
	 * Prepares the draft data for use in the editor.
	 *
	 */
	public function prepare(): void
	{
		Utils::$context['sticky'] = !empty($this->sticky) ? $this->sticky : '';
		Utils::$context['locked'] = !empty($this->locked) ? $this->locked : '';
		Utils::$context['use_smileys'] = !empty($this->smileys_enabled) ? true : false;
		Utils::$context['icon'] = !empty($this->icon) ? $this->icon : 'xx';
		Utils::$context['message'] = !empty($this->body) ? str_replace('<br>', "\n", Utils::htmlspecialcharsDecode(stripslashes($this->body))) : '';
		Utils::$context['subject'] = !empty($this->subject) ? stripslashes($this->subject) : '';
		Utils::$context['board'] = !empty($this->board) ? $this->board : '';
		Utils::$context['id_draft'] = !empty($this->id) ? $this->id : 0;
	}

	/**
	 * Saves a draft in the user_drafts table.
	 *
	 * Does nothing if this type of draft (i.e. post or PM) is disabled.
	 *
	 * If this is a new draft, creates a new database entry for it.
	 * If this is an existing draft, updates the current database entry.
	 *
	 * If necessary, updates $post_errors for display in the template.
	 *
	 * @param array &$post_errors Any errors encountered trying to save this draft.
	 * @return bool Whether the draft was saved successfully.
	 */
	public function save(&$post_errors): bool
	{
		// can you be, should you be ... here?
		if (empty(Config::$modSettings[$this->enabled_setting]) || !User::$me->allowedTo($this->permission) || !isset($_POST['save_draft'])) {
			return false;
		}

		if (in_array('session_timeout', $post_errors)) {
			return false;
		}

		// A draft has been saved less than 5 seconds ago, let's not do the autosave again.
		if (isset($_REQUEST['xml']) && !empty($this->poster_time) && time() < $this->poster_time + 5) {
			Utils::$context['draft_saved_on'] = $this->poster_time;

			// Since we were called from the autosave function, send something back.
			if (!empty($this->id)) {
				self::xml($this->id);
			}

			return true;
		}

		// Update the database entry.
		$saved = $this->saveToDatabase();

		if (!$saved) {
			$post_errors[] = 'draft_not_saved';
		}

		// If we were called from the autosave function, send something back.
		if (!empty($this->id) && isset($_REQUEST['xml'])) {
			Utils::$context['draft_saved_on'] = time();
			self::xml($this->id);
		}

		return $saved;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Deletes one or more drafts from the database.
	 *
	 * Optionally validates that the drafts belong to the current user.
	 *
	 * @param int|array $drafts The IDs of one or more drafts to delete.
	 * @param bool $check Whether or not to check that the drafts belong to the current user.
	 * @return bool Whether the drafts were deleted.
	 */
	public static function delete(int|array $drafts, bool $check = true): bool
	{
		$drafts = (array) $drafts;

		if (empty($drafts) || ($check && empty(User::$me->id))) {
			return false;
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_drafts
			WHERE id_draft IN ({array_int:drafts})' . ($check ? '
				AND id_member = {int:id_member}' : ''),
			[
				'drafts' => $drafts,
				'id_member' => empty(User::$me->id) ? -1 : User::$me->id,
			],
		);

		return true;
	}

	/**
	 * Loads in a group of post drafts for the given user.
	 *
	 * Used in the posting screens to allow draft selection.
	 *
	 * @param int $member_id ID of the member to show drafts for.
	 * @param int $topic ID of the topic that is being replied to.
	 * @return bool Whether the drafts (if any) were loaded.
	 */
	public static function showInEditor(int $member_id, int $topic = 0): bool
	{
		// Permissions
		if (empty(Utils::$context['drafts_save']) || empty($member_id)) {
			return false;
		}

		Lang::load('Drafts');

		Utils::$context['drafts'] = [];

		// Load the drafts this user has available.
		$request = Db::$db->query(
			'',
			'SELECT subject, poster_time, id_board, id_topic, id_draft
			FROM {db_prefix}user_drafts
			WHERE id_member = {int:id_member}' . (!empty($topic) ? '
				AND id_topic = {int:id_topic}' : '') . '
				AND type = {int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY poster_time DESC',
			[
				'id_member' => $member_id,
				'id_topic' => $topic,
				'draft_type' => 0,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
			],
		);

		// Add them to the drafts array for display.
		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($row['subject'])) {
				$row['subject'] = Lang::$txt['no_subject'];
			}

			$tmp_subject = Utils::shorten(stripslashes($row['subject']), 24);

			Utils::$context['drafts'][] = [
				'subject' => Lang::censorText($tmp_subject),
				'poster_time' => Time::create('@' . $row['poster_time'])->format(),
				'link' => '<a href="' . Config::$scripturl . '?action=post;board=' . $row['id_board'] . ';' . (!empty($row['id_topic']) ? 'topic=' . $row['id_topic'] . '.0;' : '') . 'id_draft=' . $row['id_draft'] . '">' . $row['subject'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		return true;
	}

	/**
	 * Show all post drafts that belong to the given user.
	 *
	 * Uses the showdraft template.
	 * The UI allows for deleting and loading/editing of drafts.
	 *
	 * @param int $memID ID of the user whose drafts should be loaded.
	 */
	public static function showInProfile(int $memID): void
	{
		Lang::load('Drafts');

		// Some initial context.
		Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		Utils::$context['current_member'] = $memID;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete'])) {
			User::$me->checkSession('get');
			$id_delete = (int) $_REQUEST['delete'];

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}user_drafts
				WHERE id_draft = {int:id_draft}
					AND id_member = {int:id_member}
					AND type = {int:draft_type}',
				[
					'id_draft' => $id_delete,
					'id_member' => $memID,
					'draft_type' => 0,
				],
			);

			Utils::redirectexit('action=profile;u=' . $memID . ';area=showdrafts;start=' . Utils::$context['start']);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount'])) {
			$_REQUEST['viewscount'] = 10;
		}

		// Get the count of applicable drafts on the boards they can (still) see ...
		// @todo .. should we just let them see their drafts even if they have lost board access ?
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}user_drafts AS ud
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board AND {query_see_board})
			WHERE id_member = {int:id_member}
				AND type={int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : ''),
			[
				'id_member' => $memID,
				'draft_type' => 0,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
			],
		);
		list($msgCount) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$maxPerPage = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];
		$maxIndex = $maxPerPage;

		// Make sure the starting place makes sense and construct our friend the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=profile;u=' . $memID . ';area=showdrafts', Utils::$context['start'], $msgCount, $maxIndex);
		Utils::$context['current_page'] = Utils::$context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = Utils::$context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;

		if ($reverse) {
			$maxIndex = $msgCount < Utils::$context['start'] + $maxPerPage + 1 && $msgCount > Utils::$context['start'] ? $msgCount - Utils::$context['start'] : $maxPerPage;
			$start = $msgCount < Utils::$context['start'] + $maxPerPage + 1 || $msgCount < Utils::$context['start'] + $maxPerPage ? 0 : $msgCount - Utils::$context['start'] - $maxPerPage;
		}

		// Find this user's drafts for the boards they can access
		// @todo ... do we want to do this?  If they were able to create a draft, do we remove their access to said draft if they loose
		//           access to the board or if the topic moves to a board they can not see?
		$request = Db::$db->query(
			'',
			'SELECT
				b.id_board, b.name AS bname,
				ud.id_member, ud.id_draft, ud.body, ud.smileys_enabled, ud.subject, ud.poster_time, ud.icon, ud.id_topic, ud.locked, ud.is_sticky
			FROM {db_prefix}user_drafts AS ud
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board AND {query_see_board})
			WHERE ud.id_member = {int:current_member}
				AND type = {int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY ud.id_draft ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT {int:start}, {int:max}',
			[
				'current_member' => $memID,
				'draft_type' => 0,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
				'start' => $start,
				'max' => $maxIndex,
			],
		);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? Utils::$context['start'] + $maxIndex + 1 : Utils::$context['start'];
		Utils::$context['posts'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Censor....
			if (empty($row['body'])) {
				$row['body'] = '';
			}

			$row['subject'] = Utils::htmlTrim($row['subject']);

			if (empty($row['subject'])) {
				$row['subject'] = Lang::$txt['no_subject'];
			}

			Lang::censorText($row['body']);
			Lang::censorText($row['subject']);

			// BBC-ilize the message.
			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], 'draft' . $row['id_draft']);

			// And the array...
			Utils::$context['drafts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'board' => [
					'name' => $row['bname'],
					'id' => $row['id_board'],
				],
				'topic' => [
					'id' => $row['id_topic'],
					'link' => empty($row['id']) ? $row['subject'] : '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				],
				'subject' => $row['subject'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'icon' => $row['icon'],
				'id_draft' => $row['id_draft'],
				'locked' => $row['locked'],
				'sticky' => $row['is_sticky'],
				'quickbuttons' => [
					'edit' => [
						'label' => Lang::$txt['draft_edit'],
						'href' => Config::$scripturl . '?action=post;' . (empty($row['id_topic']) ? 'board=' . $row['id_board'] : 'topic=' . $row['id_topic']) . '.0;id_draft=' . $row['id_draft'],
						'icon' => 'modify_button',
					],
					'delete' => [
						'label' => Lang::$txt['draft_delete'],
						'href' => Config::$scripturl . '?action=profile;u=' . Utils::$context['member']['id'] . ';area=showdrafts;delete=' . $row['id_draft'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
						'javascript' => 'data-confirm="' . Lang::$txt['draft_remove'] . '"',
						'class' => 'you_sure',
						'icon' => 'remove_button',
					],
				],
			];
		}
		Db::$db->free_result($request);

		// If the drafts were retrieved in reverse order, get them right again.
		if ($reverse) {
			Utils::$context['drafts'] = array_reverse(Utils::$context['drafts'], true);
		}

		// Menu tab
		Menu::$loaded['profile']->tab_data = [
			'title' => Lang::$txt['drafts_show'],
			'description' => Lang::$txt['drafts_show_desc'],
			'icon_class' => 'main_icons drafts',
		];
		Utils::$context['sub_template'] = 'showDrafts';
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Retrieves this drafts' data from the user_drafts table.
	 *
	 * Optionally validates that this draft belongs to the current user.
	 *
	 * @param bool $check Validate that this draft belongs to this user.
	 *     Default: true.
	 * @return array Data about the draft. Empty if draft was not found.
	 */
	protected function read($check = true): array
	{
		// Nothing to read, nothing to do.
		if (empty($this->id)) {
			return [];
		}

		// Fetch this draft's info from the database.
		$request = Db::$db->query(
			'',
			'SELECT *
			FROM {db_prefix}user_drafts
			WHERE id_draft = {int:id_draft}' . ($check ? '
				AND id_member = {int:id_member}' : '') . '
				AND type = {int:type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			LIMIT 1',
			[
				'id_member' => User::$me->id,
				'id_draft' => $this->id,
				'type' => $this->type,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
			],
		);

		// No results?
		if (empty(Db::$db->num_rows($request))) {
			$draft_info = [];
		}
		// Retrieve the data.
		else {
			$draft_info = Db::$db->fetch_assoc($request);
		}

		Db::$db->free_result($request);

		return $draft_info;
	}

	/**
	 * Sets draft properties based on submitted form data.
	 *
	 * @param array $recipientList ID numbers of members the PM will be sent to,
	 *     grouped into 'to' and 'bcc' sub-arrays. Only applies to PM drafts.
	 */
	protected function setProperties(array $recipientList = []): void
	{
		if (!isset($_POST['message']) && isset($_POST['quickReply'])) {
			$_POST['message'] = $_POST['quickReply'];
		}

		if (isset($_POST['message'])) {
			$body = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);
			Msg::preparsecode($body);
		}

		if (isset($body)) {
			$this->body = $body;
		}

		if (isset($_POST['subject'])) {
			$this->subject = Utils::entitySubstr(trim(preg_replace('/(\pZ|&nbsp;)+/u', ' ', Utils::htmlspecialchars($_POST['subject']))), 0, 100);
		}

		if (empty($this->member)) {
			$this->member = User::$me->id;
		}

		if (isset($_POST['ns'])) {
			$this->smileys_enabled = !empty($_POST['ns']);
		}

		if (isset($_POST['sticky'])) {
			$this->sticky = !empty($_POST['sticky']);
		}

		if (isset($_POST['lock'])) {
			$this->locked = !empty($_POST['lock']);
		}

		if (!empty($_POST['icon']) && preg_match('/^\w+$/', $_POST['icon'])) {
			$this->icon = $_POST['icon'];
		}

		if (!empty($_REQUEST['topic'])) {
			$this->topic = (int) $_REQUEST['topic'];
		}

		if (empty($this->board)) {
			$this->board = Board::$info->id ?? 0;
		}

		if (!empty($_POST['replied_to'])) {
			$this->reply_to = (int) $_POST['replied_to'];
		}

		if (!empty($recipientList['to'])) {
			$this->recipients['to'] = (array) $recipientList['to'];
		}

		if (!empty($recipientList['bcc'])) {
			$this->recipients['bcc'] = (array) $recipientList['bcc'];
		}

		// These arrays should contain only integers.
		$this->recipients['to'] = array_map('intval', $this->recipients['to']);
		$this->recipients['bcc'] = array_map('intval', $this->recipients['bcc']);
	}

	/**
	 * Saves this draft to the database.
	 *
	 * @return bool Whether the save operation was successful.
	 */
	protected function saveToDatabase(): bool
	{
		// Updating an existing draft.
		if (!empty($this->id)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}user_drafts
				SET
					id_topic = {int:id_topic},
					id_board = {int:id_board},
					id_reply = {int:id_reply},
					type = {int:type},
					poster_time = {int:poster_time},
					subject = {string:subject},
					smileys_enabled = {int:smileys_enabled},
					body = {string:body},
					icon = {string:icon},
					locked = {int:locked},
					is_sticky = {int:sticky},
					to_list = {string:to_list}
				WHERE id_draft = {int:id_draft}',
				[
					'id_draft' => $this->id,
					'id_topic' => $this->topic,
					'id_board' => $this->board,
					'id_reply' => $this->reply_to,
					'type' => $this->type,
					'poster_time' => time(),
					'subject' => $this->subject,
					'smileys_enabled' => (int) $this->smileys_enabled,
					'body' => $this->body,
					'icon' => $this->icon,
					'locked' => (int) $this->locked,
					'sticky' => (int) $this->sticky,
					'to_list' => Utils::jsonEncode($this->recipients),
				],
			);

			$this->poster_time = time();

			// Some items to return to the form.
			Utils::$context['draft_saved'] = true;
			Utils::$context['id' . ($this->type === 1 ? '_pm' : '') . '_draft'] = $this->id;
		}
		// Otherwise, creating a new draft.
		else {
			$this->id = Db::$db->insert(
				'',
				'{db_prefix}user_drafts',
				[
					'id_topic' => 'int',
					'id_board' => 'int',
					'id_reply' => 'int',
					'type' => 'int',
					'poster_time' => 'int',
					'id_member' => 'int',
					'subject' => 'string-255',
					'smileys_enabled' => 'int',
					'body' => (!empty(Config::$modSettings['max_messageLength']) && Config::$modSettings['max_messageLength'] > 65534 ? 'string-' . Config::$modSettings['max_messageLength'] : 'string-65534'),
					'icon' => 'string-16',
					'locked' => 'int',
					'is_sticky' => 'int',
					'to_list' => 'string-255',
				],
				[
					$this->topic,
					$this->board,
					$this->reply_to,
					$this->type,
					time(),
					$this->member,
					$this->subject,
					(int) $this->smileys_enabled,
					$this->body,
					$this->icon,
					(int) $this->locked,
					(int) $this->sticky,
					Utils::jsonEncode($this->recipients),
				],
				[
					'id_draft',
				],
				1,
			);

			// Did everything go as expected?
			if (!empty($this->id)) {
				$this->poster_time = time();
				Utils::$context['draft_saved'] = true;
				Utils::$context['id' . ($this->type === 1 ? '_pm' : '') . '_draft'] = $this->id;
			} else {
				Utils::$context['draft_saved'] = false;
			}
		}

		// Cleanup
		unset($_POST['save_draft']);

		return Utils::$context['draft_saved'];
	}

	/**
	 * Returns an XML response to an autosave AJAX request.
	 *
	 * Provides the ID of the draft saved and the time it was saved.
	 *
	 * @param int $id_draft
	 */
	protected static function xml(int $id_draft): void
	{
		Lang::load('Drafts');

		header('content-type: text/xml; charset=' . (empty(Utils::$context['character_set']) ? 'ISO-8859-1' : Utils::$context['character_set']));

		echo '<?xml version="1.0" encoding="', Utils::$context['character_set'], '"?>
		<drafts>
			<draft id="', $id_draft, '"><![CDATA[', Lang::$txt['draft_saved_on'], ': ', Time::create('@' . Utils::$context['draft_saved_on'])->format(), ']]></draft>
		</drafts>';

		Utils::obExit(false);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Draft::exportStatic')) {
	Draft::exportStatic();
}

?>