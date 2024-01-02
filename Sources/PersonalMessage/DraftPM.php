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

use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Draft;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Represents a personal message draft.
 *
 * This class extends SMF\Draft for the special case of personal message drafts.
 */
class DraftPM extends Draft
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'showInEditor' => 'showInEditor',
			'showInProfile' => 'showPMDrafts',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The type of this draft.
	 * 0 = post; 1 = personal message.
	 */
	public int $type = 1;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Config::$modSettings setting that enables/disables this type of draft.
	 */
	protected string $enabled_setting = 'drafts_pm_enabled';

	/**
	 * @var string
	 *
	 * Permission that allows the user to save this type of draft.
	 */
	protected string $permission = 'pm_draft';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Prepares the draft data for use in the personal message editor.
	 *
	 */
	public function prepare(): void
	{
		$_REQUEST['subject'] = !empty($this->subject) ? stripslashes($this->subject) : '';
		$_REQUEST['message'] = !empty($this->body) ? str_replace('<br>', "\n", Utils::htmlspecialcharsDecode(stripslashes($this->body))) : '';
		$_REQUEST['replied_to'] = !empty($this->id_reply) ? $this->id_reply : 0;
		Utils::$context['id_draft'] = !empty($this->id) ? $this->id : 0;

		// In theory, we already did this, but just in case...
		$this->recipients['to'] = array_map('intval', $this->recipients['to']);
		$this->recipients['bcc'] = array_map('intval', $this->recipients['bcc']);

		// Pretend we messed up to populate the personal message editor.
		PM::reportErrors([], [], $this->recipients);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads a group of personal message drafts for the given user.
	 *
	 * Used in the posting screens to allow draft selection.
	 *
	 * @param int $member_id ID of the member to show drafts for
	 * @param bool|int $reply_to ID of the PM that is being replied to.
	 * @return bool Whether the drafts (if any) were loaded.
	 */
	public static function showInEditor(int $member_id, $reply_to = false): bool
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
			'SELECT subject, poster_time, id_draft
			FROM {db_prefix}user_drafts
			WHERE id_member = {int:id_member}' . (!empty($reply_to) ? '
				AND id_reply = {int:id_reply}' : '') . '
				AND type = {int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY poster_time DESC',
			[
				'id_member' => $member_id,
				'id_reply' => (int) $reply_to,
				'draft_type' => 1,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
			],
		);

		// Add them to the drafts array for display.
		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($row['subject'])) {
				$row['subject'] = Lang::$txt['drafts_none'];
			}

			$tmp_subject = Utils::shorten(stripslashes($row['subject']), 24);

			Utils::$context['drafts'][] = [
				'subject' => Lang::censorText($tmp_subject),
				'poster_time' => Time::create('@' . $row['poster_time'])->format(),
				'link' => '<a href="' . Config::$scripturl . '?action=pm;sa=send;id_draft=' . $row['id_draft'] . '">' . $row['subject'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		return true;
	}

	/**
	 * Show all personal message drafts that belong to the given user.
	 *
	 * Uses the showdraft template.
	 * The UI allows for deleting and loading/editing of drafts.
	 *
	 * @param int $memID ID of the user whose drafts should be loaded.
	 */
	public static function showInProfile(int $memID = -1): void
	{
		// init
		Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete'])) {
			User::$me->checkSession('get');
			$id_delete = (int) $_REQUEST['delete'];
			$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}user_drafts
				WHERE id_draft = {int:id_draft}
					AND id_member = {int:id_member}
					AND type = {int:draft_type}',
				[
					'id_draft' => $id_delete,
					'id_member' => $memID,
					'draft_type' => 1,
				],
			);

			// now redirect back to the list
			Utils::redirectexit('action=pm;sa=showpmdrafts;start=' . $start);
		}

		// perhaps a draft was selected for editing? if so pass this off
		if (!empty($_REQUEST['id_draft']) && !empty(Utils::$context['drafts_save']) && $memID == User::$me->id) {
			User::$me->checkSession('get');
			$id_draft = (int) $_REQUEST['id_draft'];
			Utils::redirectexit('action=pm;sa=send;id_draft=' . $id_draft);
		}

		Lang::load('Drafts');

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount'])) {
			$_REQUEST['viewscount'] = 10;
		}

		// Get the count of applicable drafts
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}user_drafts
			WHERE id_member = {int:id_member}
				AND type={int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : ''),
			[
				'id_member' => $memID,
				'draft_type' => 1,
				'time' => (!empty(Config::$modSettings['drafts_keep_days']) ? (time() - (Config::$modSettings['drafts_keep_days'] * 86400)) : 0),
			],
		);
		list($msgCount) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$maxPerPage = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];
		$maxIndex = $maxPerPage;

		// Make sure the starting place makes sense and construct our friend the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=pm;sa=showpmdrafts', Utils::$context['start'], $msgCount, $maxIndex);
		Utils::$context['current_page'] = Utils::$context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the total for better performance.
		$start = Utils::$context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;

		if ($reverse) {
			$maxIndex = $msgCount < Utils::$context['start'] + $maxPerPage + 1 && $msgCount > Utils::$context['start'] ? $msgCount - Utils::$context['start'] : $maxPerPage;

			$start = $msgCount < Utils::$context['start'] + $maxPerPage + 1 || $msgCount < Utils::$context['start'] + $maxPerPage ? 0 : $msgCount - Utils::$context['start'] - $maxPerPage;
		}

		// Load in this user's PM drafts
		$request = Db::$db->query(
			'',
			'SELECT
				ud.id_member, ud.id_draft, ud.body, ud.subject, ud.poster_time, ud.id_reply, ud.to_list
			FROM {db_prefix}user_drafts AS ud
			WHERE ud.id_member = {int:current_member}
				AND type = {int:draft_type}' . (!empty(Config::$modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY ud.id_draft ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT {int:start}, {int:max}',
			[
				'current_member' => $memID,
				'draft_type' => 1,
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
			$row['body'] = BBCodeParser::load()->parse($row['body'], true, 'draft' . $row['id_draft']);

			// Have they provide who this will go to?
			$recipients = [
				'to' => [],
				'bcc' => [],
			];

			$recipient_ids = (!empty($row['to_list'])) ? Utils::jsonDecode($row['to_list'], true) : [];

			// @todo ... this is a bit ugly since it runs an extra query for every message, do we want this?
			// at least its only for draft PM's and only the user can see them ... so not heavily used .. still
			if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc'])) {
				$recipient_ids['to'] = array_map('intval', $recipient_ids['to']);
				$recipient_ids['bcc'] = array_map('intval', $recipient_ids['bcc']);
				$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

				$request_2 = Db::$db->query(
					'',
					'SELECT id_member, real_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					[
						'member_list' => $allRecipients,
					],
				);

				while ($result = Db::$db->fetch_assoc($request_2)) {
					$recipientType = in_array($result['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
					$recipients[$recipientType][] = $result['real_name'];
				}
				Db::$db->free_result($request_2);
			}

			// Add the items to the array for template use
			Utils::$context['drafts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'subject' => $row['subject'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'id_draft' => $row['id_draft'],
				'recipients' => $recipients,
				'age' => floor((time() - $row['poster_time']) / 86400),
				'remaining' => (!empty(Config::$modSettings['drafts_keep_days']) ? floor(Config::$modSettings['drafts_keep_days'] - ((time() - $row['poster_time']) / 86400)) : 0),
				'quickbuttons' => [
					'edit' => [
						'label' => Lang::$txt['draft_edit'],
						'href' => Config::$scripturl . '?action=pm;sa=showpmdrafts;id_draft=' . $row['id_draft'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
						'icon' => 'modify_button',
					],
					'delete' => [
						'label' => Lang::$txt['draft_delete'],
						'href' => Config::$scripturl . '?action=pm;sa=showpmdrafts;delete=' . $row['id_draft'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
						'javascript' => 'data-confirm="' . Lang::$txt['draft_remove'] . '?"',
						'class' => 'you_sure',
						'icon' => 'remove_button',
					],
				],
			];
		}
		Db::$db->free_result($request);

		// if the drafts were retrieved in reverse order, then put them in the right order again.
		if ($reverse) {
			Utils::$context['drafts'] = array_reverse(Utils::$context['drafts'], true);
		}

		// off to the template we go
		Utils::$context['page_title'] = Lang::$txt['drafts'];
		Utils::$context['sub_template'] = 'showPMDrafts';
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=showpmdrafts',
			'name' => Lang::$txt['drafts'],
		];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\DraftPM::exportStatic')) {
	DraftPM::exportStatic();
}

?>