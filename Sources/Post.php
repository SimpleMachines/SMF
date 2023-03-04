<?php

/**
 * The job of this file is to handle everything related to posting replies,
 * new topics, quotes, and modifications to existing posts.  It also handles
 * quoting posts by way of javascript.
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BrowserDetector;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Handles showing the post screen, loading the post to be modified, and loading any post quoted.
 *
 * - additionally handles previews of posts.
 * - Uses the Post template and language file, main sub template.
 * - requires different permissions depending on the actions, but most notably post_new, post_reply_own, and post_reply_any.
 * - shows options for the editing and posting of calendar events and attachments, as well as the posting of polls.
 * - accessed from ?action=post.
 *
 * @param array $post_errors Holds any errors found while tyring to post
 */
function Post($post_errors = array())
{
	global $settings;
	global $options;

	Lang::load('Post');
	if (!empty(Config::$modSettings['drafts_post_enabled']))
		Lang::load('Drafts');

	// You can't reply with a poll... hacker.
	if (isset($_REQUEST['poll']) && !empty(Topic::$topic_id) && !isset($_REQUEST['msg']))
		unset($_REQUEST['poll']);

	// Posting an event?
	Utils::$context['make_event'] = isset($_REQUEST['calendar']);
	Utils::$context['robot_no_index'] = true;

	call_integration_hook('integrate_post_start');

	// Get notification preferences for later
	require_once(Config::$sourcedir . '/Subs-Notify.php');
	// use $temp to get around "Only variables should be passed by reference"
	$temp = getNotifyPrefs(User::$me->id);
	Utils::$context['notify_prefs'] = (array) array_pop($temp);
	Utils::$context['auto_notify'] = !empty(Utils::$context['notify_prefs']['msg_auto_notify']);

	// Not in a board? Fine, but we'll make them pick one eventually.
	if (empty(Board::$info->id) || Utils::$context['make_event'])
	{
		// Get ids of all the boards they can post in.
		$post_permissions = array('post_new');
		if (Config::$modSettings['postmod_active'])
			$post_permissions[] = 'post_unapproved_topics';

		$boards = boardsAllowedTo($post_permissions);
		if (empty($boards))
			fatal_lang_error('cannot_post_new', false);

		// Get a list of boards for the select menu
		require_once($sourcedir . '/Subs-MessageIndex.php');
		$boardListOptions = array(
			'included_boards' => in_array(0, $boards) ? null : $boards,
			'not_redirection' => true,
			'use_permissions' => true,
			'selected_board' => !empty(Board::$info->id) ? Board::$info->id : (Utils::$context['make_event'] && !empty(Config::$modSettings['cal_defaultboard']) ? Config::$modSettings['cal_defaultboard'] : $boards[0]),
		);
		$board_list = getBoardList($boardListOptions);
	}
	// Let's keep things simple for ourselves below
	else
		$boards = array(Board::$info->id);

	require_once(Config::$sourcedir . '/Msg.php');

	if (isset($_REQUEST['xml']))
	{
		Utils::$context['sub_template'] = 'post';

		// Just in case of an earlier error...
		Utils::$context['preview_message'] = '';
		Utils::$context['preview_subject'] = '';
	}

	// No message is complete without a topic.
	if (empty(Topic::$topic_id) && !empty($_REQUEST['msg']))
	{
		$request = Db::$db->query('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => (int) $_REQUEST['msg'],
			)
		);
		if (Db::$db->num_rows($request) != 1)
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
		else
			list (Topic::$topic_id) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}

	// Check if it's locked. It isn't locked if no topic is specified.
	if (!empty(Topic::$topic_id))
	{
		$request = Db::$db->query('', '
			SELECT
				t.locked, t.approved, COALESCE(ln.id_topic, 0) AS notify, t.is_sticky, t.id_poll, t.id_last_msg, mf.id_member,
				t.id_first_msg, mf.subject, ml.modified_reason,
				CASE WHEN ml.poster_time > ml.modified_time THEN ml.poster_time ELSE ml.modified_time END AS last_post_time
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_topic = t.id_topic AND ln.id_member = {int:current_member})
				LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_member' => User::$me->id,
				'current_topic' => Topic::$topic_id,
			)
		);
		list ($locked, $topic_approved, Utils::$context['notify'], $sticky, $pollID, Utils::$context['topic_last_message'], $id_member_poster, $id_first_msg, $first_subject, $editReason, $lastPostTime) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// If this topic already has a poll, they sure can't add another.
		if (isset($_REQUEST['poll']) && $pollID > 0)
			unset($_REQUEST['poll']);

		if (empty($_REQUEST['msg']))
		{
			if (User::$me->is_guest && !allowedTo('post_reply_any') && (!Config::$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
				is_not_guest();

			// By default the reply will be approved...
			Utils::$context['becomes_approved'] = true;
			if ($id_member_poster != User::$me->id || User::$me->is_guest)
			{
				if (Config::$modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
					Utils::$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_any');
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if (Config::$modSettings['postmod_active'] && ((allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own')) || allowedTo('post_unapproved_replies_any')))
					Utils::$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_own');
			}
		}
		else
			Utils::$context['becomes_approved'] = true;

		Utils::$context['can_lock'] = allowedTo('lock_any') || (User::$me->id == $id_member_poster && allowedTo('lock_own'));
		Utils::$context['can_sticky'] = allowedTo('make_sticky');
		Utils::$context['can_move'] = allowedTo('move_any');
		// You can only announce topics that will get approved...
		Utils::$context['can_announce'] = allowedTo('announce_topic') && Utils::$context['becomes_approved'];
		Utils::$context['show_approval'] = !allowedTo('approve_posts') ? 0 : (Utils::$context['becomes_approved'] ? 2 : 1);

		// We don't always want the request vars to override what's in the db...
		Utils::$context['already_locked'] = $locked;
		Utils::$context['already_sticky'] = $sticky;
		Utils::$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : $sticky;

		// Check whether this is a really old post being bumped...
		if (!empty(Config::$modSettings['oldTopicDays']) && $lastPostTime + Config::$modSettings['oldTopicDays'] * 86400 < time() && empty($sticky) && !isset($_REQUEST['subject']))
			$post_errors[] = array('old_topic', array(Config::$modSettings['oldTopicDays']));
	}
	else
	{
		// @todo Should use JavaScript to hide and show the warning based on the selection in the board select menu
		Utils::$context['becomes_approved'] = true;
		if (Config::$modSettings['postmod_active'] && !allowedTo('post_new', $boards, true) && allowedTo('post_unapproved_topics', $boards, true))
			Utils::$context['becomes_approved'] = false;
		else
			isAllowedTo('post_new', $boards, true);

		$locked = 0;
		Utils::$context['already_locked'] = 0;
		Utils::$context['already_sticky'] = 0;
		Utils::$context['sticky'] = !empty($_REQUEST['sticky']);

		// What options should we show?
		Utils::$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'), $boards, true);
		Utils::$context['can_sticky'] = allowedTo('make_sticky', $boards, true);
		Utils::$context['can_move'] = allowedTo('move_any', $boards, true);
		Utils::$context['can_announce'] = allowedTo('announce_topic', $boards, true) && Utils::$context['becomes_approved'];
		Utils::$context['show_approval'] = !allowedTo('approve_posts', $boards, true) ? 0 : (Utils::$context['becomes_approved'] ? 2 : 1);
	}

	Utils::$context['notify'] = !empty(Utils::$context['notify']);

	Utils::$context['can_notify'] = !User::$me->is_guest;
	Utils::$context['move'] = !empty($_REQUEST['move']);
	Utils::$context['announce'] = !empty($_REQUEST['announce']);
	Utils::$context['locked'] = !empty($locked) || !empty($_REQUEST['lock']);
	Utils::$context['can_quote'] = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

	// An array to hold all the attachments for this topic.
	Utils::$context['current_attachments'] = array();

	// Clear out prior attachment activity when starting afresh
	if (empty($_REQUEST['message']) && empty($_REQUEST['preview']) && !empty($_SESSION['already_attached']))
	{
		require_once(Config::$sourcedir . '/ManageAttachments.php');
		foreach ($_SESSION['already_attached'] as $attachID => $attachment)
			removeAttachments(array('id_attach' => $attachID));

		unset($_SESSION['already_attached']);
	}

	// Don't allow a post if it's locked and you aren't all powerful.
	if ($locked && !allowedTo('moderate_board'))
		fatal_lang_error('topic_locked', false);
	// Check the users permissions - is the user allowed to add or post a poll?
	if (isset($_REQUEST['poll']) && Config::$modSettings['pollMode'] == '1')
	{
		// New topic, new poll.
		if (empty(Topic::$topic_id))
			isAllowedTo('poll_post');
		// This is an old topic - but it is yours!  Can you add to it?
		elseif (User::$me->id == $id_member_poster && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// If you're not the owner, can you add to any poll?
		else
			isAllowedTo('poll_add_any');

		if (!empty(Board::$info->id))
		{
			require_once(Config::$sourcedir . '/Subs-Members.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', Board::$info->id);
			$guest_vote_enabled = in_array(-1, $allowedVoteGroups['allowed']);
		}
		// No board, so we'll have to check this again in Post2
		else
			$guest_vote_enabled = true;

		// Set up the poll options.
		Utils::$context['poll_options'] = array(
			'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
			'hide' => empty($_POST['poll_hide']) ? 0 : $_POST['poll_hide'],
			'expire' => !isset($_POST['poll_expire']) ? '' : $_POST['poll_expire'],
			'change_vote' => isset($_POST['poll_change_vote']),
			'guest_vote' => isset($_POST['poll_guest_vote']),
			'guest_vote_enabled' => $guest_vote_enabled,
		);

		// Make all five poll choices empty.
		Utils::$context['choices'] = array(
			array('id' => 0, 'number' => 1, 'label' => '', 'is_last' => false),
			array('id' => 1, 'number' => 2, 'label' => '', 'is_last' => false),
			array('id' => 2, 'number' => 3, 'label' => '', 'is_last' => false),
			array('id' => 3, 'number' => 4, 'label' => '', 'is_last' => false),
			array('id' => 4, 'number' => 5, 'label' => '', 'is_last' => true)
		);
		Utils::$context['last_choice_id'] = 4;
	}

	if (Utils::$context['make_event'])
	{
		// They might want to pick a board.
		if (!isset(Utils::$context['current_board']))
			Utils::$context['current_board'] = 0;

		// Start loading up the event info.
		Utils::$context['event'] = array();
		Utils::$context['event']['title'] = isset($_REQUEST['evtitle']) ? Utils::htmlspecialchars(stripslashes($_REQUEST['evtitle'])) : '';
		Utils::$context['event']['location'] = isset($_REQUEST['event_location']) ? Utils::htmlspecialchars(stripslashes($_REQUEST['event_location'])) : '';

		Utils::$context['event']['id'] = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : -1;
		Utils::$context['event']['new'] = Utils::$context['event']['id'] == -1;

		// Permissions check!
		isAllowedTo('calendar_post');

		require_once(Config::$sourcedir . '/Subs-Calendar.php');

		// We want a fairly compact version of the time, but as close as possible to the user's settings.
		$time_string = strtr(get_date_or_time_format('time'), array(
			'%I' => '%l',
			'%H' => '%k',
			'%S' => '',
			'%r' => '%l:%M %p',
			'%R' => '%k:%M',
			'%T' => '%l:%M',
		));

		$time_string = preg_replace('~:(?=\s|$|%[pPzZ])~', '', $time_string);

		// Editing an event?  (but NOT previewing!?)
		if (empty(Utils::$context['event']['new']) && !isset($_REQUEST['subject']))
		{
			// If the user doesn't have permission to edit the post in this topic, redirect them.
			if ((empty($id_member_poster) || $id_member_poster != User::$me->id || !allowedTo('modify_own')) && !allowedTo('modify_any'))
			{
				require_once(Config::$sourcedir . '/Calendar.php');
				return CalendarPost();
			}

			// Get the current event information.
			$eventProperties = getEventProperties(Utils::$context['event']['id']);
			Utils::$context['event'] = array_merge(Utils::$context['event'], $eventProperties);
		}
		else
		{
			// Get the current event information.
			$eventProperties = getNewEventDatetimes();
			Utils::$context['event'] = array_merge(Utils::$context['event'], $eventProperties);

			// Make sure the year and month are in the valid range.
			if (Utils::$context['event']['month'] < 1 || Utils::$context['event']['month'] > 12)
				fatal_lang_error('invalid_month', false);
			if (Utils::$context['event']['year'] < Config::$modSettings['cal_minyear'] || Utils::$context['event']['year'] > Config::$modSettings['cal_maxyear'])
				fatal_lang_error('invalid_year', false);

			Utils::$context['event']['categories'] = $board_list;
		}

		// Find the last day of the month.
		Utils::$context['event']['last_day'] = (int) smf_strftime('%d', mktime(0, 0, 0, Utils::$context['event']['month'] == 12 ? 1 : Utils::$context['event']['month'] + 1, 0, Utils::$context['event']['month'] == 12 ? Utils::$context['event']['year'] + 1 : Utils::$context['event']['year']));

		// An all day event? Set up some nice defaults in case the user wants to change that
		if (Utils::$context['event']['allday'] == true)
		{
			Utils::$context['event']['tz'] = User::getTimezone();
			Utils::$context['event']['start_time'] = timeformat(time(), $time_string);
			Utils::$context['event']['end_time'] = timeformat(time() + 3600, $time_string);
		}
		// Otherwise, just adjust these to look nice on the input form
		else
		{
			Utils::$context['event']['start_time'] = Utils::$context['event']['start_time_orig'];
			Utils::$context['event']['end_time'] = Utils::$context['event']['end_time_orig'];
		}

		// Need this so the user can select a timezone for the event.
		Utils::$context['all_timezones'] = smf_list_timezones(Utils::$context['event']['start_date']);

		// If the event's timezone is not in SMF's standard list of time zones, try to fix it.
		if (!isset(Utils::$context['all_timezones'][Utils::$context['event']['tz']]))
		{
			$later = strtotime('@' . Utils::$context['event']['start_timestamp'] . ' + 1 year');
			$tzinfo = timezone_transitions_get(timezone_open(Utils::$context['event']['tz']), Utils::$context['event']['start_timestamp'], $later);

			$found = false;
			foreach (Utils::$context['all_timezones'] as $possible_tzid => $dummy)
			{
				$possible_tzinfo = timezone_transitions_get(timezone_open($possible_tzid), Utils::$context['event']['start_timestamp'], $later);

				if ($tzinfo === $possible_tzinfo)
				{
					Utils::$context['event']['tz'] = $possible_tzid;
					$found = true;
					break;
				}
			}

			// Hm. That's weird. Well, just prepend it to the list and let the user deal with it.
			if (!$found)
			{
				$d = date_create(Utils::$context['event']['start_datetime'] . ' ' . Utils::$context['event']['tz']);
				Utils::$context['all_timezones'] = array(Utils::$context['event']['tz'] => '[UTC' . date_format($d, 'P') . '] - ' . Utils::$context['event']['tz']) + Utils::$context['all_timezones'];
			}
		}

		loadDatePicker('#event_time_input .date_input');
		loadTimePicker('#event_time_input .time_input', $time_string);
		loadDatePair('#event_time_input', 'date_input', 'time_input');
		addInlineJavaScript('
	$("#allday").click(function(){
		$("#start_time").attr("disabled", this.checked);
		$("#end_time").attr("disabled", this.checked);
		$("#tz").attr("disabled", this.checked);
	});	', true);

		Utils::$context['event']['board'] = !empty(Board::$info->id) ? Board::$info->id : Config::$modSettings['cal_defaultboard'];
		Utils::$context['event']['topic'] = !empty(Topic::$topic_id) ? Topic::$topic_id : 0;
	}

	// See if any new replies have come along.
	// Huh, $_REQUEST['msg'] is set upon submit, so this doesn't get executed at submit
	// only at preview
	if (empty($_REQUEST['msg']) && !empty(Topic::$topic_id))
	{
		if (empty($options['no_new_reply_warning']) && isset($_REQUEST['last_msg']) && Utils::$context['topic_last_message'] > $_REQUEST['last_msg'])
		{
			$request = Db::$db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg > {int:last_msg}' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:approved}') . '
				LIMIT 1',
				array(
					'current_topic' => Topic::$topic_id,
					'last_msg' => (int) $_REQUEST['last_msg'],
					'approved' => 1,
				)
			);
			list (Utils::$context['new_replies']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (!empty(Utils::$context['new_replies']))
			{
				if (Utils::$context['new_replies'] == 1)
					Lang::$txt['error_new_replies'] = isset($_GET['last_msg']) ? Lang::$txt['error_new_reply_reading'] : Lang::$txt['error_new_reply'];
				else
					Lang::$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? Lang::$txt['error_new_replies_reading'] : Lang::$txt['error_new_replies'], Utils::$context['new_replies']);

				$post_errors[] = 'new_replies';

				Config::$modSettings['topicSummaryPosts'] = Utils::$context['new_replies'] > Config::$modSettings['topicSummaryPosts'] ? max(Config::$modSettings['topicSummaryPosts'], 5) : Config::$modSettings['topicSummaryPosts'];
			}
		}
	}

	// Get a response prefix (like 'Re:') in the default forum language.
	if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix')))
	{
		if (Lang::$default === User::$me->language)
			Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
		else
		{
			Lang::load('index', Lang::$default, false);
			Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
			Lang::load('index');
		}
		CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
	}

	// Previewing, modifying, or posting?
	// Do we have a body, but an error happened.
	if (isset($_REQUEST['message']) || isset($_REQUEST['quickReply']) || !empty(Utils::$context['post_error']))
	{
		if (isset($_REQUEST['quickReply']))
			$_REQUEST['message'] = $_REQUEST['quickReply'];

		// Validate inputs.
		if (empty(Utils::$context['post_error']))
		{
			// This means they didn't click Post and get an error.
			$really_previewing = true;
		}
		else
		{
			if (!isset($_REQUEST['subject']))
				$_REQUEST['subject'] = '';
			if (!isset($_REQUEST['message']))
				$_REQUEST['message'] = '';
			if (!isset($_REQUEST['icon']))
				$_REQUEST['icon'] = 'xx';

			// They are previewing if they asked to preview (i.e. came from quick reply).
			$really_previewing = !empty($_POST['preview']);
		}

		// In order to keep the approval status flowing through, we have to pass it through the form...
		Utils::$context['becomes_approved'] = empty($_REQUEST['not_approved']);
		Utils::$context['show_approval'] = isset($_REQUEST['approve']) ? ($_REQUEST['approve'] ? 2 : 1) : (allowedTo('approve_posts') ? 2 : 0);
		Utils::$context['can_announce'] &= Utils::$context['becomes_approved'];

		// Set up the inputs for the form.
		$form_subject = strtr(Utils::htmlspecialchars($_REQUEST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$form_message = Utils::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);

		// Make sure the subject isn't too long - taking into account special characters.
		if (Utils::entityStrlen($form_subject) > 100)
			$form_subject = Utils::entitySubstr($form_subject, 0, 100);

		if (isset($_REQUEST['poll']))
		{
			Utils::$context['question'] = isset($_REQUEST['question']) ? Utils::htmlspecialchars(trim($_REQUEST['question'])) : '';

			Utils::$context['choices'] = array();
			$choice_id = 0;

			$_POST['options'] = empty($_POST['options']) ? array() : htmlspecialchars__recursive($_POST['options']);
			foreach ($_POST['options'] as $option)
			{
				if (trim($option) == '')
					continue;

				Utils::$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => $option,
					'is_last' => false
				);
			}

			// One empty option for those with js disabled...I know are few... :P
			Utils::$context['choices'][] = array(
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => '',
				'is_last' => false
			);

			if (count(Utils::$context['choices']) < 2)
			{
				Utils::$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false
				);
			}
			Utils::$context['last_choice_id'] = $choice_id;
			Utils::$context['choices'][count(Utils::$context['choices']) - 1]['is_last'] = true;
		}

		// Are you... a guest?
		if (User::$me->is_guest)
		{
			$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
			$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

			$_REQUEST['guestname'] = Utils::htmlspecialchars($_REQUEST['guestname']);
			Utils::$context['name'] = $_REQUEST['guestname'];
			$_REQUEST['email'] = Utils::htmlspecialchars($_REQUEST['email']);
			Utils::$context['email'] = $_REQUEST['email'];

			User::$me->name = $_REQUEST['guestname'];
		}

		// Only show the preview stuff if they hit Preview.
		if (($really_previewing == true || isset($_REQUEST['xml'])) && !isset($_REQUEST['save_draft']))
		{
			// Set up the preview message and subject and censor them...
			Utils::$context['preview_message'] = $form_message;
			preparsecode($form_message, true);
			preparsecode(Utils::$context['preview_message']);

			// Do all bulletin board code tags, with or without smileys.
			Utils::$context['preview_message'] = BBCodeParser::load()->parse(Utils::$context['preview_message'], !isset($_REQUEST['ns']));
			Lang::censorText(Utils::$context['preview_message']);

			if ($form_subject != '')
			{
				Utils::$context['preview_subject'] = $form_subject;

				Lang::censorText(Utils::$context['preview_subject']);
			}
			else
				Utils::$context['preview_subject'] = '<em>' . Lang::$txt['no_subject'] . '</em>';

			call_integration_hook('integrate_preview_post', array(&$form_message, &$form_subject));

			// Protect any CDATA blocks.
			if (isset($_REQUEST['xml']))
				Utils::$context['preview_message'] = strtr(Utils::$context['preview_message'], array(']]>' => ']]]]><![CDATA[>'));
		}

		// Set up the checkboxes.
		Utils::$context['notify'] = !empty($_REQUEST['notify']);
		Utils::$context['use_smileys'] = !isset($_REQUEST['ns']);

		Utils::$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

		// Set the destination action for submission.
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');
		Utils::$context['submit_label'] = isset($_REQUEST['msg']) ? Lang::$txt['save'] : Lang::$txt['post'];

		// Previewing an edit?
		if (isset($_REQUEST['msg']) && !empty(Topic::$topic_id))
		{
			// Get the existing message. Previewing.
			$request = Db::$db->query('', '
				SELECT
					m.id_member, m.modified_time, m.smileys_enabled, m.body,
					m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
					COALESCE(a.size, -1) AS filesize, a.filename, a.id_attach,
					a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
					m.poster_time, log.id_action, t.id_first_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
					LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
				WHERE m.id_msg = {int:id_msg}
					AND m.id_topic = {int:current_topic}',
				array(
					'current_topic' => Topic::$topic_id,
					'attachment_type' => 0,
					'id_msg' => $_REQUEST['msg'],
					'announce_action' => 'announce_topic',
				)
			);
			// The message they were trying to edit was most likely deleted.
			// @todo Change this error message?
			if (Db::$db->num_rows($request) == 0)
				fatal_lang_error('no_board', false);
			$row = Db::$db->fetch_assoc($request);

			$attachment_stuff = array($row);
			while ($row2 = Db::$db->fetch_assoc($request))
				$attachment_stuff[] = $row2;
			Db::$db->free_result($request);

			if ($row['id_member'] == User::$me->id && !allowedTo('modify_any'))
			{
				// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
				if ($row['approved'] && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
				elseif ($row['id_member_poster'] == User::$me->id && !allowedTo('modify_own'))
					isAllowedTo('modify_replies');
				else
					isAllowedTo('modify_own');
			}
			elseif ($row['id_member_poster'] == User::$me->id && !allowedTo('modify_any'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_any');

			if (Utils::$context['can_announce'] && !empty($row['id_action']) && $row['id_first_msg'] == $_REQUEST['msg'])
			{
				Lang::load('Errors');
				Utils::$context['post_error']['already_announced'] = Lang::$txt['error_topic_already_announced'];
			}

			if (!empty(Config::$modSettings['attachmentEnable']))
			{
				$request = Db::$db->query('', '
					SELECT COALESCE(size, -1) AS filesize, filename, id_attach, approved, mime_type, id_thumb
					FROM {db_prefix}attachments
					WHERE id_msg = {int:id_msg}
						AND attachment_type = {int:attachment_type}
					ORDER BY id_attach',
					array(
						'id_msg' => (int) $_REQUEST['msg'],
						'attachment_type' => 0,
					)
				);

				while ($row = Db::$db->fetch_assoc($request))
				{
					if ($row['filesize'] <= 0)
						continue;
					Utils::$context['current_attachments'][$row['id_attach']] = array(
						'name' => Utils::htmlspecialchars($row['filename']),
						'size' => $row['filesize'],
						'attachID' => $row['id_attach'],
						'approved' => $row['approved'],
						'mime_type' => $row['mime_type'],
						'thumb' => $row['id_thumb'],
					);
				}
				Db::$db->free_result($request);
			}

			// Allow moderators to change names....
			if (allowedTo('moderate_forum') && !empty(Topic::$topic_id))
			{
				$request = Db::$db->query('', '
					SELECT id_member, poster_name, poster_email
					FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}
						AND id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_topic' => Topic::$topic_id,
						'id_msg' => (int) $_REQUEST['msg'],
					)
				);
				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);

				if (empty($row['id_member']))
				{
					Utils::$context['name'] = Utils::htmlspecialchars($row['poster_name']);
					Utils::$context['email'] = Utils::htmlspecialchars($row['poster_email']);
				}
			}
		}

		// No check is needed, since nothing is really posted.
		checkSubmitOnce('free');
	}
	// Editing a message...
	elseif (isset($_REQUEST['msg']) && !empty(Topic::$topic_id))
	{
		Utils::$context['editing'] = true;

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Get the existing message. Editing.
		$request = Db::$db->query('', '
			SELECT
				m.id_member, m.modified_time, m.modified_name, m.modified_reason, m.smileys_enabled, m.body,
				m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
				COALESCE(a.size, -1) AS filesize, a.filename, a.id_attach, a.mime_type, a.id_thumb,
				a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
				m.poster_time, log.id_action, t.id_first_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
					LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}',
			array(
				'current_topic' => Topic::$topic_id,
				'attachment_type' => 0,
				'id_msg' => $_REQUEST['msg'],
				'announce_action' => 'announce_topic',
			)
		);
		// The message they were trying to edit was most likely deleted.
		if (Db::$db->num_rows($request) == 0)
			fatal_lang_error('no_message', false);
		$row = Db::$db->fetch_assoc($request);

		$attachment_stuff = array($row);
		while ($row2 = Db::$db->fetch_assoc($request))
			$attachment_stuff[] = $row2;
		Db::$db->free_result($request);

		if ($row['id_member'] == User::$me->id && !allowedTo('modify_any'))
		{
			// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
			if ($row['approved'] && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_poster'] == User::$me->id && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($row['id_member_poster'] == User::$me->id && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		if (Utils::$context['can_announce'] && !empty($row['id_action']) && $row['id_first_msg'] == $_REQUEST['msg'])
		{
			Lang::load('Errors');
			Utils::$context['post_error']['already_announced'] = Lang::$txt['error_topic_already_announced'];
		}

		// When was it last modified?
		if (!empty($row['modified_time']))
		{
			$modified_reason = $row['modified_reason'];
			Utils::$context['last_modified'] = timeformat($row['modified_time']);
			Utils::$context['last_modified_reason'] = Lang::censorText($row['modified_reason']);
			Utils::$context['last_modified_name'] = $row['modified_name'];
			Utils::$context['last_modified_text'] = sprintf(Lang::$txt['last_edit_by'], Utils::$context['last_modified'], $row['modified_name']) . (empty($row['modified_reason']) ? '' : ' ' . sprintf(Lang::$txt['last_edit_reason'], $row['modified_reason']));
		}

		// Get the stuff ready for the form.
		$form_subject = $row['subject'];
		$form_message = un_preparsecode($row['body']);
		Lang::censorText($form_message);
		Lang::censorText($form_subject);

		// Check the boxes that should be checked.
		Utils::$context['use_smileys'] = !empty($row['smileys_enabled']);
		Utils::$context['icon'] = $row['icon'];

		// Leave the approval checkbox unchecked by default for unapproved messages.
		if (!$row['approved'] && !empty(Utils::$context['show_approval']))
			Utils::$context['show_approval'] = 1;

		// Sort the attachments so they are in the order saved
		$temp = array();
		foreach ($attachment_stuff as $attachment)
		{
			if ($attachment['filesize'] >= 0 && !empty(Config::$modSettings['attachmentEnable']))
				$temp[$attachment['id_attach']] = $attachment;
		}
		ksort($temp);

		// Load up 'em attachments!
		foreach ($temp as $attachment)
		{
			Utils::$context['current_attachments'][$attachment['id_attach']] = array(
				'name' => Utils::htmlspecialchars($attachment['filename']),
				'size' => $attachment['filesize'],
				'attachID' => $attachment['id_attach'],
				'href' => $scripturl . '?action=dlattach;attach=' . $attachment['id_attach'],
				'approved' => $attachment['attachment_approved'],
				'mime_type' => $attachment['mime_type'],
				'thumb' => $attachment['id_thumb'],
			);
		}

		// Allow moderators to change names....
		if (allowedTo('moderate_forum') && empty($row['id_member']))
		{
			Utils::$context['name'] = Utils::htmlspecialchars($row['poster_name']);
			Utils::$context['email'] = Utils::htmlspecialchars($row['poster_email']);
		}

		// Set the destination.
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . (isset($_REQUEST['poll']) ? ';poll' : '');
		Utils::$context['submit_label'] = Lang::$txt['save'];
	}
	// Posting...
	else
	{
		// By default....
		Utils::$context['use_smileys'] = true;
		Utils::$context['icon'] = 'xx';

		if (User::$me->is_guest)
		{
			Utils::$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
			Utils::$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
		}
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['poll']) ? ';poll' : '');

		Utils::$context['submit_label'] = Lang::$txt['post'];

		// Posting a quoted reply?
		if (!empty(Topic::$topic_id) && !empty($_REQUEST['quote']))
		{
			// Make sure they _can_ quote this post, and if so get it.
			$request = Db::$db->query('', '
				SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
				WHERE {query_see_message_board}
					AND m.id_msg = {int:id_msg}' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => (int) $_REQUEST['quote'],
					'is_approved' => 1,
				)
			);
			if (Db::$db->num_rows($request) == 0)
				fatal_lang_error('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim(Utils::$context['response_prefix']) != '' && Utils::entityStrpos($form_subject, trim(Utils::$context['response_prefix'])) !== 0)
				$form_subject = Utils::$context['response_prefix'] . $form_subject;

			// Censor the message and subject.
			Lang::censorText($form_message);
			Lang::censorText($form_subject);

			// But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
			if (strpos($form_message, '[html]') !== false)
			{
				$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $form_message, -1, PREG_SPLIT_DELIM_CAPTURE);
				for ($i = 0, $n = count($parts); $i < $n; $i++)
				{
					// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
					if ($i % 4 == 0)
						$parts[$i] = preg_replace_callback(
							'~\[html\](.+?)\[/html\]~is',
							function($m)
							{
								return '[html]' . preg_replace('~<br\s?/?' . '>~i', '&lt;br /&gt;<br>', "$m[1]") . '[/html]';
							},
							$parts[$i]
						);
				}
				$form_message = implode('', $parts);
			}

			$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $form_message);

			// Remove any nested quotes, if necessary.
			if (!empty(Config::$modSettings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty(Topic::$topic_id) && empty($_REQUEST['quote']))
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			if (trim(Utils::$context['response_prefix']) != '' && $form_subject != '' && Utils::entityStrpos($form_subject, trim(Utils::$context['response_prefix'])) !== 0)
				$form_subject = Utils::$context['response_prefix'] . $form_subject;

			// Censor the subject.
			Lang::censorText($form_subject);

			$form_message = '';
		}
		else
		{
			$form_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
			$form_message = '';
		}
	}

	Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment', $boards, true) || (Config::$modSettings['postmod_active'] && allowedTo('post_unapproved_attachments', $boards, true)));

	if (Utils::$context['can_post_attachment'])
	{
		// If there are attachments, calculate the total size and how many.
		Utils::$context['attachments']['total_size'] = 0;
		Utils::$context['attachments']['quantity'] = 0;

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			Utils::$context['attachments']['quantity'] = count(Utils::$context['current_attachments']);
			foreach (Utils::$context['current_attachments'] as $attachment)
				Utils::$context['attachments']['total_size'] += $attachment['size'];
		}

		// A bit of house keeping first.
		if (!empty($_SESSION['temp_attachments']) && count($_SESSION['temp_attachments']) == 1)
			unset($_SESSION['temp_attachments']);

		if (!empty($_SESSION['temp_attachments']))
		{
			// Is this a request to delete them?
			if (isset($_GET['delete_temp']))
			{
				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
				{
					if (strpos($attachID, 'post_tmp_' . User::$me->id) !== false)
						if (file_exists($attachment['tmp_name']))
							unlink($attachment['tmp_name']);
				}
				$post_errors[] = 'temp_attachments_gone';
				$_SESSION['temp_attachments'] = array();
			}
			// Hmm, coming in fresh and there are files in session.
			elseif (Utils::$context['current_action'] != 'post2' || !empty($_POST['from_qr']))
			{
				// Let's be nice and see if they belong here first.
				if ((empty($_REQUEST['msg']) && empty($_SESSION['temp_attachments']['post']['msg']) && $_SESSION['temp_attachments']['post']['board'] == (!empty(Board::$info->id) ? Board::$info->id : 0)) || (!empty($_REQUEST['msg']) && $_SESSION['temp_attachments']['post']['msg'] == $_REQUEST['msg']))
				{
					// See if any files still exist before showing the warning message and the files attached.
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
					{
						if (strpos($attachID, 'post_tmp_' . User::$me->id) === false)
							continue;

						if (file_exists($attachment['tmp_name']))
						{
							$post_errors[] = 'temp_attachments_new';
							Utils::$context['files_in_session_warning'] = Lang::$txt['attached_files_in_session'];
							unset($_SESSION['temp_attachments']['post']['files']);
							break;
						}
					}
				}
				else
				{
					// Since, they don't belong here. Let's inform the user that they exist..
					if (!empty(Topic::$topic_id))
						$delete_url = Config::$scripturl . '?action=post' . (!empty($_REQUEST['msg']) ? (';msg=' . $_REQUEST['msg']) : '') . (!empty($_REQUEST['last_msg']) ? (';last_msg=' . $_REQUEST['last_msg']) : '') . ';topic=' . Topic::$topic_id . ';delete_temp';
					else
						$delete_url = Config::$scripturl . '?action=post' . (!empty(Board::$info->id) ? ';board=' . Board::$info->id : '') . ';delete_temp';

					// Compile a list of the files to show the user.
					$file_list = array();
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
						if (strpos($attachID, 'post_tmp_' . User::$me->id) !== false)
							$file_list[] = $attachment['name'];

					$_SESSION['temp_attachments']['post']['files'] = $file_list;
					$file_list = '<div class="attachments">' . implode('<br>', $file_list) . '</div>';

					if (!empty($_SESSION['temp_attachments']['post']['msg']))
					{
						// We have a message id, so we can link back to the old topic they were trying to edit..
						$goback_url = Config::$scripturl . '?action=post' . (!empty($_SESSION['temp_attachments']['post']['msg']) ? (';msg=' . $_SESSION['temp_attachments']['post']['msg']) : '') . (!empty($_SESSION['temp_attachments']['post']['last_msg']) ? (';last_msg=' . $_SESSION['temp_attachments']['post']['last_msg']) : '') . ';topic=' . $_SESSION['temp_attachments']['post']['topic'] . ';additionalOptions';

						$post_errors[] = array('temp_attachments_found', array($delete_url, $goback_url, $file_list));
						Utils::$context['ignore_temp_attachments'] = true;
					}
					else
					{
						$post_errors[] = array('temp_attachments_lost', array($delete_url, $file_list));
						Utils::$context['ignore_temp_attachments'] = true;
					}
				}
			}

			if (!empty(Utils::$context['we_are_history']))
				$post_errors[] = Utils::$context['we_are_history'];

			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if (isset(Utils::$context['ignore_temp_attachments']) || isset($_SESSION['temp_attachments']['post']['files']))
					break;

				if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . User::$me->id) === false)
					continue;

				if ($attachID == 'initial_error')
				{
					Lang::$txt['error_attach_initial_error'] = Lang::$txt['attach_no_upload'] . '<div style="padding: 0 1em;">' . (is_array($attachment) ? vsprintf(Lang::$txt[$attachment[0]], (array) $attachment[1]) : Lang::$txt[$attachment]) . '</div>';
					$post_errors[] = 'attach_initial_error';
					unset($_SESSION['temp_attachments']);
					break;
				}

				// Show any errors which might have occurred.
				if (!empty($attachment['errors']))
				{
					Lang::$txt['error_attach_errors'] = empty(Lang::$txt['error_attach_errors']) ? '<br>' : '';
					Lang::$txt['error_attach_errors'] .= sprintf(Lang::$txt['attach_warning'], $attachment['name']) . '<div style="padding: 0 1em;">';
					foreach ($attachment['errors'] as $error)
						Lang::$txt['error_attach_errors'] .= (is_array($error) ? vsprintf(Lang::$txt[$error[0]], (array) $error[1]) : Lang::$txt[$error]) . '<br >';
					Lang::$txt['error_attach_errors'] .= '</div>';
					$post_errors[] = 'attach_errors';

					// Take out the trash.
					unset($_SESSION['temp_attachments'][$attachID]);
					if (file_exists($attachment['tmp_name']))
						unlink($attachment['tmp_name']);
					continue;
				}

				// More house keeping.
				if (!file_exists($attachment['tmp_name']))
				{
					unset($_SESSION['temp_attachments'][$attachID]);
					continue;
				}

				Utils::$context['attachments']['quantity']++;
				Utils::$context['attachments']['total_size'] += $attachment['size'];
				if (!isset(Utils::$context['files_in_session_warning']))
					Utils::$context['files_in_session_warning'] = Lang::$txt['attached_files_in_session'];

				Utils::$context['current_attachments'][$attachID] = array(
					'name' => Utils::htmlspecialchars($attachment['name']),
					'size' => $attachment['size'],
					'attachID' => $attachID,
					'unchecked' => false,
					'approved' => 1,
					'mime_type' => '',
					'thumb' => 0,
				);
			}
		}
	}

	// Allow user to see previews for all of this post's attachments, even if the post hasn't been submitted yet.
	if (!isset($_SESSION['attachments_can_preview']))
		$_SESSION['attachments_can_preview'] = array();

	if (!empty($_SESSION['already_attached']))
		$_SESSION['attachments_can_preview'] += array_fill_keys(array_keys($_SESSION['already_attached']), true);

	foreach (Utils::$context['current_attachments'] as $attachID => $attachment)
	{
		$_SESSION['attachments_can_preview'][$attachID] = true;

		if (!empty($attachment['thumb']))
			$_SESSION['attachments_can_preview'][$attachment['thumb']] = true;
	}

	// Previously uploaded attachments have 2 flavors:
	// - Existing post - at this point, now in Utils::$context['current_attachments']
	// - Just added, current session only - at this point, now in $_SESSION['already_attached']
	// We need to make sure *all* of these are in Utils::$context['current_attachments'], otherwise they won't show in dropzone during edits.
	if (!empty($_SESSION['already_attached']))
	{
		$request = Db::$db->query('', '
			SELECT
				a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.approved, a.mime_type, a.id_thumb
			FROM {db_prefix}attachments AS a
			WHERE a.attachment_type = {int:attachment_type}
				AND a.id_attach IN ({array_int:just_uploaded})',
			array(
				'attachment_type' => 0,
				'just_uploaded' => $_SESSION['already_attached']
			)
		);

		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['current_attachments'][$row['id_attach']] = array(
				'name' => Utils::htmlspecialchars($row['filename']),
				'size' => $row['filesize'],
				'attachID' => $row['id_attach'],
				'approved' => $row['approved'],
				'mime_type' => $row['mime_type'],
				'thumb' => $row['id_thumb'],
			);
		}
		Db::$db->free_result($request);
	}

	// Do we need to show the visual verification image?
	Utils::$context['require_verification'] = !User::$me->is_mod && !User::$me->is_admin && !empty(Config::$modSettings['posts_require_captcha']) && (User::$me->posts < Config::$modSettings['posts_require_captcha'] || (User::$me->is_guest && Config::$modSettings['posts_require_captcha'] == -1));
	if (Utils::$context['require_verification'])
	{
		require_once(Config::$sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		Utils::$context['require_verification'] = create_control_verification($verificationOptions);
		Utils::$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If they came from quick reply, and have to enter verification details, give them some notice.
	if (!empty($_REQUEST['from_qr']) && !empty(Utils::$context['require_verification']))
		$post_errors[] = 'need_qr_verification';

	/*
	 * There are two error types: serious and minor. Serious errors
	 * actually tell the user that a real error has occurred, while minor
	 * errors are like warnings that let them know that something with
	 * their post isn't right.
	 */
	$minor_errors = array('not_approved', 'new_replies', 'old_topic', 'need_qr_verification', 'no_subject', 'topic_locked', 'topic_unlocked', 'topic_stickied', 'topic_unstickied', 'cannot_post_attachment');

	call_integration_hook('integrate_post_errors', array(&$post_errors, &$minor_errors, $form_message, $form_subject));

	// Any errors occurred?
	if (!empty($post_errors))
	{
		Lang::load('Errors');
		Utils::$context['error_type'] = 'minor';
		foreach ($post_errors as $post_error)
			if (is_array($post_error))
			{
				$post_error_id = $post_error[0];
				Utils::$context['post_error'][$post_error_id] = vsprintf(Lang::$txt['error_' . $post_error_id], (array) $post_error[1]);

				// If it's not a minor error flag it as such.
				if (!in_array($post_error_id, $minor_errors))
					Utils::$context['error_type'] = 'serious';
			}
			else
			{
				Utils::$context['post_error'][$post_error] = Lang::$txt['error_' . $post_error];

				// If it's not a minor error flag it as such.
				if (!in_array($post_error, $minor_errors))
					Utils::$context['error_type'] = 'serious';
			}
	}

	// What are you doing? Posting a poll, modifying, previewing, new post, or reply...
	if (isset($_REQUEST['poll']))
		Utils::$context['page_title'] = Lang::$txt['new_poll'];
	elseif (Utils::$context['make_event'])
		Utils::$context['page_title'] = Utils::$context['event']['id'] == -1 ? Lang::$txt['calendar_post_event'] : Lang::$txt['calendar_edit'];
	elseif (isset($_REQUEST['msg']))
		Utils::$context['page_title'] = Lang::$txt['modify_msg'];
	elseif (isset($_REQUEST['subject'], Utils::$context['preview_subject']))
		Utils::$context['page_title'] = Lang::$txt['preview'] . ' - ' . strip_tags(Utils::$context['preview_subject']);
	elseif (empty(Topic::$topic_id))
		Utils::$context['page_title'] = Lang::$txt['start_new_topic'];
	else
		Utils::$context['page_title'] = Lang::$txt['post_reply'];

	// Build the link tree.
	if (empty(Topic::$topic_id))
		Utils::$context['linktree'][] = array(
			'name' => '<em>' . Lang::$txt['start_new_topic'] . '</em>'
		);
	else
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?topic=' . Topic::$topic_id . '.' . $_REQUEST['start'],
			'name' => $form_subject,
			'extra_before' => '<span><strong class="nav">' . Utils::$context['page_title'] . ' (</strong></span>',
			'extra_after' => '<span><strong class="nav">)</strong></span>'
		);

	Utils::$context['subject'] = addcslashes($form_subject, '"');
	Utils::$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

	// Are post drafts enabled?
	Utils::$context['drafts_save'] = !empty(Config::$modSettings['drafts_post_enabled']) && allowedTo('post_draft');
	Utils::$context['drafts_autosave'] = !empty(Utils::$context['drafts_save']) && !empty(Config::$modSettings['drafts_autosave_enabled']) && allowedTo('post_autosave_draft') && !empty($options['drafts_autosave_enabled']);

	// Build a list of drafts that they can load in to the editor
	if (!empty(Utils::$context['drafts_save']))
	{
		require_once(Config::$sourcedir . '/Drafts.php');
		ShowDrafts(User::$me->id, Topic::$topic_id);
	}

	// Needed for the editor and message icons.
	require_once(Config::$sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => Utils::$context['message'],
		'labels' => array(
			'post_button' => Utils::$context['submit_label'],
		),
		// add height and width for the editor
		'height' => '175px',
		'width' => '100%',
		// We do XML preview here.
		'preview_type' => 2,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	// Store the ID.
	Utils::$context['post_box_name'] = $editorOptions['id'];

	Utils::$context['attached'] = '';
	Utils::$context['make_poll'] = isset($_REQUEST['poll']);

	// Message icons - customized icons are off?
	Utils::$context['icons'] = getMessageIcons(!empty(Board::$info->id) ? Board::$info->id : 0);

	if (!empty(Utils::$context['icons']))
		Utils::$context['icons'][count(Utils::$context['icons']) - 1]['is_last'] = true;

	// Are we starting a poll? if set the poll icon as selected if its available
	if (isset($_REQUEST['poll']))
	{
		foreach (Utils::$context['icons'] as $icons)
		{
			if (isset($icons['value']) && $icons['value'] == 'poll')
			{
				// if found we are done
				Utils::$context['icon'] = 'poll';
				break;
			}
		}
	}

	Utils::$context['icon_url'] = '';
	for ($i = 0, $n = count(Utils::$context['icons']); $i < $n; $i++)
	{
		Utils::$context['icons'][$i]['selected'] = Utils::$context['icon'] == Utils::$context['icons'][$i]['value'];
		if (Utils::$context['icons'][$i]['selected'])
			Utils::$context['icon_url'] = Utils::$context['icons'][$i]['url'];
	}
	if (empty(Utils::$context['icon_url']))
	{
		Utils::$context['icon_url'] = $settings[file_exists($settings['theme_dir'] . '/images/post/' . Utils::$context['icon'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . Utils::$context['icon'] . '.png';
		array_unshift(Utils::$context['icons'], array(
			'value' => Utils::$context['icon'],
			'name' => Lang::$txt['current_icon'],
			'url' => Utils::$context['icon_url'],
			'is_last' => empty(Utils::$context['icons']),
			'selected' => true,
		));
	}

	if (!empty(Topic::$topic_id) && !empty(Config::$modSettings['topicSummaryPosts']))
		getTopic();

	// If the user can post attachments prepare the warning labels.
	if (Utils::$context['can_post_attachment'])
	{
		// If they've unchecked an attachment, they may still want to attach that many more files, but don't allow more than num_allowed_attachments.
		Utils::$context['num_allowed_attachments'] = empty(Config::$modSettings['attachmentNumPerPostLimit']) ? PHP_INT_MAX : Config::$modSettings['attachmentNumPerPostLimit'];
		Utils::$context['can_post_attachment_unapproved'] = allowedTo('post_attachment');
		Utils::$context['attachment_restrictions'] = array();
		Utils::$context['allowed_extensions'] = !empty(Config::$modSettings['attachmentCheckExtensions']) ? (strtr(strtolower(Config::$modSettings['attachmentExtensions']), array(',' => ', '))) : '';
		$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
		foreach ($attachmentRestrictionTypes as $type)
			if (!empty(Config::$modSettings[$type]))
			{
				Utils::$context['attachment_restrictions'][$type] = sprintf(Lang::$txt['attach_restrict_' . $type . (Config::$modSettings[$type] >= 1024 ? '_MB' : '')], Lang::numberFormat(Config::$modSettings[$type] >= 1024 ? Config::$modSettings[$type] / 1024 : Config::$modSettings[$type], 2));

				// Show the max number of attachments if not 0.
				if ($type == 'attachmentNumPerPostLimit')
				{
					Utils::$context['attachment_restrictions'][$type] .= ' (' . sprintf(Lang::$txt['attach_remaining'], max(Config::$modSettings['attachmentNumPerPostLimit'] - Utils::$context['attachments']['quantity'], 0)) . ')';
				}
				elseif ($type == 'attachmentPostLimit' && Utils::$context['attachments']['total_size'] > 0)
				{
 					Utils::$context['attachment_restrictions'][$type] .= '<span class="attach_available"> (' . sprintf(Lang::$txt['attach_available'], round(max(Config::$modSettings['attachmentPostLimit'] - (Utils::$context['attachments']['total_size'] / 1024), 0), 2)) . ')</span>';
				}

			}
	}

	Utils::$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
	Utils::$context['show_additional_options'] = !empty($_POST['additional_options']) || isset($_SESSION['temp_attachments']['post']) || isset($_GET['additionalOptions']);

	Utils::$context['is_new_topic'] = empty(Topic::$topic_id);
	Utils::$context['is_new_post'] = !isset($_REQUEST['msg']);
	Utils::$context['is_first_post'] = Utils::$context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $id_first_msg);

	// Register this form in the session variables.
	checkSubmitOnce('register');

	// Mentions
	if (!empty(Config::$modSettings['enable_mentions']) && allowedTo('mention'))
	{
		loadJavaScriptFile('jquery.caret.min.js', array('defer' => true), 'smf_caret');
		loadJavaScriptFile('jquery.atwho.min.js', array('defer' => true), 'smf_atwho');
		loadJavaScriptFile('mentions.js', array('defer' => true, 'minimize' => true), 'smf_mentions');
	}

	// Load the drafts js file
	if (Utils::$context['drafts_autosave'])
		loadJavaScriptFile('drafts.js', array('defer' => false, 'minimize' => true), 'smf_drafts');

	// quotedText.js
	loadJavaScriptFile('quotedText.js', array('defer' => true, 'minimize' => true), 'smf_quotedText');

	addInlineJavaScript('
	var current_attachments = [];');

	if (!empty(Utils::$context['current_attachments']))
	{
		// Mock files to show already attached files.
		foreach (Utils::$context['current_attachments'] as $key => $mock)
			addInlineJavaScript('
	current_attachments.push({
		name: ' . JavaScriptEscape($mock['name']) . ',
		size: ' . $mock['size'] . ',
		attachID: ' . $mock['attachID'] . ',
		approved: ' . $mock['approved'] . ',
		type: ' . JavaScriptEscape(!empty($mock['mime_type']) ? $mock['mime_type'] : '') . ',
		thumbID: ' . (!empty($mock['thumb']) ? $mock['thumb'] : 0) . '
	});');
	}

	// File Upload.
	if (Utils::$context['can_post_attachment'])
	{
		$acceptedFiles = empty(Utils::$context['allowed_extensions']) ? '' : implode(',', array_map(
			function ($val)
			{
				return !empty($val) ? ('.' . Utils::htmlTrim($val)) : '';
			},
			explode(',', Utils::$context['allowed_extensions'])
		));

		loadJavaScriptFile('dropzone.min.js', array('defer' => true), 'smf_dropzone');
		loadJavaScriptFile('smf_fileUpload.js', array('defer' => true, 'minimize' => true), 'smf_fileUpload');
		addInlineJavaScript('
	$(function() {
		smf_fileUpload({
			dictDefaultMessage : ' . JavaScriptEscape(Lang::$txt['attach_drop_zone']) . ',
			dictFallbackMessage : ' . JavaScriptEscape(Lang::$txt['attach_drop_zone_no']) . ',
			dictCancelUpload : ' . JavaScriptEscape(Lang::$txt['modify_cancel']) . ',
			genericError: ' . JavaScriptEscape(Lang::$txt['attach_php_error']) . ',
			text_attachDropzoneLabel: ' . JavaScriptEscape(Lang::$txt['attach_drop_zone']) . ',
			text_attachLimitNag: ' . JavaScriptEscape(Lang::$txt['attach_limit_nag']) . ',
			text_attachLeft: ' . JavaScriptEscape(Lang::$txt['attachments_left']) . ',
			text_deleteAttach: ' . JavaScriptEscape(Lang::$txt['attached_file_delete']) . ',
			text_attachDeleted: ' . JavaScriptEscape(Lang::$txt['attached_file_deleted']) . ',
			text_insertBBC: ' . JavaScriptEscape(Lang::$txt['attached_insert_bbc']) . ',
			text_attachUploaded: ' . JavaScriptEscape(Lang::$txt['attached_file_uploaded']) . ',
			text_attach_unlimited: ' . JavaScriptEscape(Lang::$txt['attach_drop_unlimited']) . ',
			text_totalMaxSize: ' . JavaScriptEscape(Lang::$txt['attach_max_total_file_size_current']) . ',
			text_max_size_progress: ' . JavaScriptEscape('{currentRemain} ' . (Config::$modSettings['attachmentPostLimit'] >= 1024 ? Lang::$txt['megabyte'] : Lang::$txt['kilobyte']) . ' / {currentTotal} ' . (Config::$modSettings['attachmentPostLimit'] >= 1024 ? Lang::$txt['megabyte'] : Lang::$txt['kilobyte'])) . ',
			dictMaxFilesExceeded: ' . JavaScriptEscape(Lang::$txt['more_attachments_error']) . ',
			dictInvalidFileType: ' . JavaScriptEscape(sprintf(Lang::$txt['cant_upload_type'], Utils::$context['allowed_extensions'])) . ',
			dictFileTooBig: ' . JavaScriptEscape(sprintf(Lang::$txt['file_too_big'], Lang::numberFormat(Config::$modSettings['attachmentSizeLimit'], 0))) . ',
			acceptedFiles: ' . JavaScriptEscape($acceptedFiles) . ',
			thumbnailWidth: ' . (!empty(Config::$modSettings['attachmentThumbWidth']) ? Config::$modSettings['attachmentThumbWidth'] : 'null') . ',
			thumbnailHeight: ' . (!empty(Config::$modSettings['attachmentThumbHeight']) ? Config::$modSettings['attachmentThumbHeight'] : 'null') . ',
			limitMultiFileUploadSize:' . round(max(Config::$modSettings['attachmentPostLimit'] - (Utils::$context['attachments']['total_size'] / 1024), 0)) * 1024 . ',
			maxFileAmount: ' . (!empty(Utils::$context['num_allowed_attachments']) ? Utils::$context['num_allowed_attachments'] : 'null') . ',
			maxTotalSize: ' . (!empty(Config::$modSettings['attachmentPostLimit']) ? Config::$modSettings['attachmentPostLimit'] : '0') . ',
			maxFilesize: ' . (!empty(Config::$modSettings['attachmentSizeLimit']) ? Config::$modSettings['attachmentSizeLimit'] : '0') . ',
		});
	});', true);
	}

	// Knowing the current board ID might be handy.
	addInlineJavaScript('
	var current_board = ' . (empty(Utils::$context['current_board']) ? 'null' : Utils::$context['current_board']) . ';', false);

	/* Now let's set up the fields for the posting form header...

		Each item in Utils::$context['posting_fields'] is an array similar to one of
		the following:

		Utils::$context['posting_fields']['foo'] = array(
			'label' => array(
				'text' => Lang::$txt['foo'], // required
				'class' => 'foo', // optional
			),
			'input' => array(
				'type' => 'text', // required
				'attributes' => array(
					'name' => 'foo', // optional, defaults to posting field's key
					'value' => $foo,
					'size' => 80,
				),
			),
		);

		Utils::$context['posting_fields']['bar'] = array(
			'label' => array(
				'text' => Lang::$txt['bar'], // required
				'class' => 'bar', // optional
			),
			'input' => array(
				'type' => 'select', // required
				'attributes' => array(
					'name' => 'bar', // optional, defaults to posting field's key
				),
				'options' => array(
					'option_1' => array(
						'label' => Lang::$txt['option_1'],
						'value' => '1',
						'selected' => true,
					),
					'option_2' => array(
						'label' => Lang::$txt['option_2'],
						'value' => '2',
						'selected' => false,
					),
					'opt_group_1' => array(
						'label' => Lang::$txt['opt_group_1'],
						'options' => array(
							'option_3' => array(
								'label' => Lang::$txt['option_3'],
								'value' => '3',
								'selected' => false,
							),
							'option_4' => array(
								'label' => Lang::$txt['option_4'],
								'value' => '4',
								'selected' => false,
							),
						),
					),
				),
			),
		);

		Utils::$context['posting_fields']['baz'] = array(
			'label' => array(
				'text' => Lang::$txt['baz'], // required
				'class' => 'baz', // optional
			),
			'input' => array(
				'type' => 'radio_select', // required
				'attributes' => array(
					'name' => 'baz', // optional, defaults to posting field's key
				),
				'options' => array(
					'option_1' => array(
						'label' => Lang::$txt['option_1'],
						'value' => '1',
						'selected' => true,
					),
					'option_2' => array(
						'label' => Lang::$txt['option_2'],
						'value' => '2',
						'selected' => false,
					),
				),
			),
		);

		The label and input elements are required. The label text and input
		type are also required. Other elements may be required or optional
		depending on the situation.

		The input type can be one of the following:

		- text, password, color, date, datetime-local, email, month, number,
		  range, tel, time, url, or week
		- textarea
		- checkbox
		- select
		- radio_select

		When the input type is text (etc.), textarea, or checkbox, the
		'attributes' element is used to specify the initial value and any
		other HTML attributes that might be necessary for the input field.

		When the input type is select or radio_select, the options element
		is required in order to list the options that the user can select.
		For the select type, these will be used to generate a typical select
		menu. For the radio_select type, they will be used to make a div with
		some radio buttons in it.

		Each option in the options array is itself an array of attributes. If
		an option contains a sub-array of more options, then it will be
		turned into an optgroup in the generated select menu. Note that the
		radio_select type only supports simple options, not grouped ones.

		Both the label and the input can have a 'before' and/or 'after'
		element. If used, these define literal HTML strings to be inserted
		before or after the rest of the content of the label or input.

		Finally, it is possible to define an 'html' element for the label
		and/or the input. If used, this will override the HTML that would
		normally be generated in the template file using the other
		information in the array. This should be avoided if at all possible.
	*/
	Utils::$context['posting_fields'] = array();

	// Guests must supply their name and email.
	if (isset(Utils::$context['name']) && isset(Utils::$context['email']))
	{
		Utils::$context['posting_fields']['guestname'] = array(
			'label' => array(
				'text' => Lang::$txt['name'],
				'class' => isset(Utils::$context['post_error']['long_name']) || isset(Utils::$context['post_error']['no_name']) || isset(Utils::$context['post_error']['bad_name']) ? 'error' : '',
			),
			'input' => array(
				'type' => 'text',
				'attributes' => array(
					'size' => 25,
					'maxlength' => 25,
					'value' => Utils::$context['name'],
					'required' => true,
				),
			),
		);

		if (empty(Config::$modSettings['guest_post_no_email']))
		{
			Utils::$context['posting_fields']['email'] = array(
				'label' => array(
					'text' => Lang::$txt['email'],
					'class' => isset(Utils::$context['post_error']['no_email']) || isset(Utils::$context['post_error']['bad_email']) ? 'error' : '',
				),
				'input' => array(
					'type' => 'email',
					'attributes' => array(
						'size' => 25,
						'value' => Utils::$context['email'],
						'required' => true,
					),
				),
			);
		}
	}

	// Gotta post it somewhere.
	if (empty(Board::$info->id))
	{
		Utils::$context['posting_fields']['board'] = array(
			'label' => array(
				'text' => Lang::$txt['calendar_post_in'],
			),
			'input' => array(
				'type' => 'select',
				'options' => array(),
			),
		);
		foreach ($board_list as $category)
		{
			Utils::$context['posting_fields']['board']['input']['options'][$category['name']] = array('options' => array());

			foreach ($category['boards'] as $brd)
				Utils::$context['posting_fields']['board']['input']['options'][$category['name']]['options'][$brd['name']] = array(
					'value' => $brd['id'],
					'selected' => (bool) $brd['selected'],
					'label' => ($brd['child_level'] > 0 ? str_repeat('==', $brd['child_level'] - 1) . '=&gt;' : '') . ' ' . $brd['name'],
				);
		}
	}

	// Gotta have a subject.
	Utils::$context['posting_fields']['subject'] = array(
		'label' => array(
			'text' => Lang::$txt['subject'],
			'class' => isset(Utils::$context['post_error']['no_subject']) ? 'error' : '',
		),
		'input' => array(
			'type' => 'text',
			'attributes' => array(
				'size' => 80,
				'maxlength' => 80 + (!empty(Topic::$topic_id) ? Utils::entityStrlen(Utils::$context['response_prefix']) : 0),
				'value' => Utils::$context['subject'],
				'required' => true,
			),
		),
	);

	// Icons are fun.
	Utils::$context['posting_fields']['icon'] = array(
		'label' => array(
			'text' => Lang::$txt['message_icon'],
		),
		'input' => array(
			'type' => 'select',
			'attributes' => array(
				'id' => 'icon',
				'onchange' => 'showimage();',
			),
			'options' => array(),
			'after' => ' <img id="icons" src="' . Utils::$context['icon_url'] . '">',
		),
	);
	foreach (Utils::$context['icons'] as $icon)
	{
		Utils::$context['posting_fields']['icon']['input']['options'][$icon['name']] = array(
			'value' => $icon['value'],
			'selected' => $icon['value'] == Utils::$context['icon'],
		);
	}

	// If we're editing and displaying edit details, show a box where they can say why.
	if (isset(Utils::$context['editing']) && Config::$modSettings['show_modify'])
	{
		Utils::$context['posting_fields']['modify_reason'] = array(
			'label' => array(
				'text' => Lang::$txt['reason_for_edit'],
			),
			'input' => array(
				'type' => 'text',
				'attributes' => array(
					'size' => 80,
					'maxlength' => 80,
					// If same user is editing again, keep the previous edit reason by default.
					'value' => isset($modified_reason) && isset(Utils::$context['last_modified_name']) && Utils::$context['last_modified_name'] === User::$me->name ? $modified_reason : '',
				),
				// If message has been edited before, show info about that.
				'after' => empty(Utils::$context['last_modified_text']) ? '' : '<div class="smalltext em">' . Utils::$context['last_modified_text'] . '</div>',
			),
		);

		// Prior to 2.1.4, the edit reason was not handled as a posting field,
		// but instead using a hardcoded input in the template file. We've fixed
		// that in the default theme, but to support any custom themes based on
		// the old verison, we do this to fix it for them.
		addInlineCss("\n\t" . '#caption_edit_reason, dl:not(#post_header) input[name="modify_reason"] { display: none; }');
		addInlineJavaScript("\n\t" . '$("#caption_edit_reason").remove(); $("dl:not(#post_header) input[name=\"modify_reason\"]").remove();', true);
	}

	// Finally, load the template.
	if (!isset($_REQUEST['xml']))
	{
		loadTemplate('Post');

		// These two lines are for the revamped attachments UI add in 2.1.4.
		loadCSSFile('attachments.css', array('minimize' => true, 'order_pos' => 450), 'smf_attachments');
		addInlineJavaScript("\n\t" . '$("#post_attachments_area #postAttachment").remove();', true);
	}

	call_integration_hook('integrate_post_end');
}

/**
 * Posts or saves the message composed with Post().
 *
 * requires various permissions depending on the action.
 * handles attachment, post, and calendar saving.
 * sends off notifications, and allows for announcements and moderation.
 * accessed from ?action=post2.
 */
function Post2()
{
	global $options, $settings;

	// Sneaking off, are we?
	if (empty($_POST) && empty(Topic::$topic_id))
	{
		if (empty($_SERVER['CONTENT_LENGTH']))
			redirectexit('action=post;board=' . Board::$info->id . '.0');
		else
			fatal_lang_error('post_upload_error', false);
	}
	elseif (empty($_POST) && !empty(Topic::$topic_id))
		redirectexit('action=post;topic=' . Topic::$topic_id . '.0');

	// No need!
	Utils::$context['robot_no_index'] = true;

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// No errors as yet.
	$post_errors = array();

	// If the session has timed out, let the user re-submit their form.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Wrong verification code?
	if (!User::$me->is_admin && !User::$me->is_mod && !empty(Config::$modSettings['posts_require_captcha']) && (User::$me->posts < Config::$modSettings['posts_require_captcha'] || (User::$me->is_guest && Config::$modSettings['posts_require_captcha'] == -1)))
	{
		require_once(Config::$sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		Utils::$context['require_verification'] = create_control_verification($verificationOptions, true);
		if (is_array(Utils::$context['require_verification']))
			$post_errors = array_merge($post_errors, Utils::$context['require_verification']);
	}

	require_once(Config::$sourcedir . '/Msg.php');
	Lang::load('Post');

	call_integration_hook('integrate_post2_start', array(&$post_errors));

	// Drafts enabled and needed?
	if (!empty(Config::$modSettings['drafts_post_enabled']) && (isset($_POST['save_draft']) || isset($_POST['id_draft'])))
		require_once(Config::$sourcedir . '/Drafts.php');

	// First check to see if they are trying to delete any current attachments.
	if (isset($_POST['attach_del']))
	{
		$keep_temp = array();
		$keep_ids = array();
		foreach ($_POST['attach_del'] as $dummy)
			if (strpos($dummy, 'post_tmp_' . User::$me->id) !== false)
				$keep_temp[] = $dummy;
			else
				$keep_ids[] = (int) $dummy;

		if (isset($_SESSION['temp_attachments']))
			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if ((isset($_SESSION['temp_attachments']['post']['files'], $attachment['name']) && in_array($attachment['name'], $_SESSION['temp_attachments']['post']['files'])) || in_array($attachID, $keep_temp) || strpos($attachID, 'post_tmp_' . User::$me->id) === false)
					continue;

				unset($_SESSION['temp_attachments'][$attachID]);
				unlink($attachment['tmp_name']);
			}

		if (!empty($_REQUEST['msg']))
		{
			require_once(Config::$sourcedir . '/ManageAttachments.php');
			$attachmentQuery = array(
				'attachment_type' => 0,
				'id_msg' => (int) $_REQUEST['msg'],
				'not_id_attach' => $keep_ids,
			);
			removeAttachments($attachmentQuery);
		}
	}

	// Then try to upload any attachments.
	Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || (Config::$modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
	if (Utils::$context['can_post_attachment'] && empty($_POST['from_qr']))
	{
		require_once(Config::$sourcedir . '/Subs-Attachments.php');
		processAttachments();
	}

	// They've already uploaded some attachments, but they don't have permission to post them
	// This can sometimes happen when they came from ?action=calendar;sa=post
	if (!Utils::$context['can_post_attachment'] && !empty($_SESSION['already_attached']))
	{
		require_once(Config::$sourcedir . '/ManageAttachments.php');

		foreach ($_SESSION['already_attached'] as $attachID => $attachment)
			removeAttachments(array('id_attach' => $attachID));

		unset($_SESSION['already_attached']);

		$post_errors[] = array('cannot_post_attachment', array(Board::$info->name));
	}

	$can_approve = allowedTo('approve_posts');

	// If this isn't a new topic load the topic info that we need.
	if (!empty(Topic::$topic_id))
	{
		$request = Db::$db->query('', '
			SELECT locked, is_sticky, id_poll, approved, id_first_msg, id_last_msg, id_member_started, id_board
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => Topic::$topic_id,
			)
		);
		$topic_info = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Though the topic should be there, it might have vanished.
		if (!is_array($topic_info))
			fatal_lang_error('topic_doesnt_exist', 404);

		// Did this topic suddenly move? Just checking...
		if ($topic_info['id_board'] != Board::$info->id)
			fatal_lang_error('not_a_topic');

		// Do the permissions and approval stuff...
		$becomesApproved = true;

		// Replies to unapproved topics are unapproved by default (but not for moderators)
		if (empty($topic_info['approved']) && !$can_approve)
		{
			$becomesApproved = false;

			// Set a nice session var...
			$_SESSION['becomesUnapproved'] = true;
		}
	}

	// Replying to a topic?
	if (!empty(Topic::$topic_id) && !isset($_REQUEST['msg']))
	{
		// Don't allow a post if it's locked.
		if ($topic_info['locked'] != 0 && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (isset($_REQUEST['poll']) && $topic_info['id_poll'] > 0)
			unset($_REQUEST['poll']);

		elseif ($topic_info['id_member_started'] != User::$me->id)
		{
			if (Config::$modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
				$becomesApproved = false;

			else
				isAllowedTo('post_reply_any');
		}
		elseif (!allowedTo('post_reply_any'))
		{
			if (Config::$modSettings['postmod_active'] && allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
				$becomesApproved = false;

			else
				isAllowedTo('post_reply_own');
		}

		if (isset($_POST['lock']))
		{
			// Nothing is changed to the lock.
			if (empty($topic_info['locked']) == empty($_POST['lock']))
				unset($_POST['lock']);

			// You're have no permission to lock this topic.
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && User::$me->id != $topic_info['id_member_started']))
				unset($_POST['lock']);

			// You are allowed to (un)lock your own topic only.
			elseif (!allowedTo('lock_any'))
			{
				// You cannot override a moderator lock.
				if ($topic_info['locked'] == 1)
					unset($_POST['lock']);

				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
			}
			// Hail mighty moderator, (un)lock this topic immediately.
			else
			{
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;

				// Did someone (un)lock this while you were posting?
				if (isset($_POST['already_locked']) && $_POST['already_locked'] != $topic_info['locked'])
					$post_errors[] = 'topic_' . (empty($topic_info['locked']) ? 'un' : '') . 'locked';
			}
		}

		// So you wanna (un)sticky this...let's see.
		if (isset($_POST['sticky']) && ($_POST['sticky'] == $topic_info['is_sticky'] || !allowedTo('make_sticky')))
			unset($_POST['sticky']);
		elseif (isset($_POST['sticky']))
		{
			// Did someone (un)sticky this while you were posting?
			if (isset($_POST['already_sticky']) && $_POST['already_sticky'] != $topic_info['is_sticky'])
				$post_errors[] = 'topic_' . (empty($topic_info['is_sticky']) ? 'un' : '') . 'sticky';
		}

		// If drafts are enabled, then pass this off
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		// If the number of replies has changed, if the setting is enabled, go back to Post() - which handles the error.
		if (empty($options['no_new_reply_warning']) && isset($_POST['last_msg']) && $topic_info['id_last_msg'] > $_POST['last_msg'])
		{
			$_REQUEST['preview'] = true;
			return Post();
		}

		$posterIsGuest = User::$me->is_guest;
		Utils::$context['is_own_post'] = true;
		Utils::$context['poster_id'] = User::$me->id;
	}
	// Posting a new topic.
	elseif (empty(Topic::$topic_id))
	{
		// Now don't be silly, new topics will get their own id_msg soon enough.
		unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

		// Do like, the permissions, for safety and stuff...
		$becomesApproved = true;
		if (Config::$modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
			$becomesApproved = false;
		else
			isAllowedTo('post_new');

		if (isset($_POST['lock']))
		{
			// New topics are by default not locked.
			if (empty($_POST['lock']))
				unset($_POST['lock']);
			// Besides, you need permission.
			elseif (!allowedTo(array('lock_any', 'lock_own')))
				unset($_POST['lock']);
			// A moderator-lock (1) can override a user-lock (2).
			else
				$_POST['lock'] = allowedTo('lock_any') ? 1 : 2;
		}

		if (isset($_POST['sticky']) && (empty($_POST['sticky']) || !allowedTo('make_sticky')))
			unset($_POST['sticky']);

		// Saving your new topic as a draft first?
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		$posterIsGuest = User::$me->is_guest;
		Utils::$context['is_own_post'] = true;
		Utils::$context['poster_id'] = User::$me->id;
	}
	// Modifying an existing message?
	elseif (isset($_REQUEST['msg']) && !empty(Topic::$topic_id))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		$request = Db::$db->query('', '
			SELECT id_member, poster_name, poster_email, poster_time, approved
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}
			LIMIT 1',
			array(
				'id_msg' => $_REQUEST['msg'],
			)
		);
		if (Db::$db->num_rows($request) == 0)
			fatal_lang_error('cant_find_messages', false);
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		if (!empty($topic_info['locked']) && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		if (isset($_POST['lock']))
		{
			// Nothing changes to the lock status.
			if ((empty($_POST['lock']) && empty($topic_info['locked'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
				unset($_POST['lock']);
			// You're simply not allowed to (un)lock this.
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && User::$me->id != $topic_info['id_member_started']))
				unset($_POST['lock']);
			// You're only allowed to lock your own topics.
			elseif (!allowedTo('lock_any'))
			{
				// You're not allowed to break a moderator's lock.
				if ($topic_info['locked'] == 1)
					unset($_POST['lock']);
				// Lock it with a soft lock or unlock it.
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
			}
			// You must be the moderator.
			else
			{
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;

				// Did someone (un)lock this while you were posting?
				if (isset($_POST['already_locked']) && $_POST['already_locked'] != $topic_info['locked'])
					$post_errors[] = 'topic_' . (empty($topic_info['locked']) ? 'un' : '') . 'locked';
			}
		}

		// Change the sticky status of this topic?
		if (isset($_POST['sticky']) && (!allowedTo('make_sticky') || $_POST['sticky'] == $topic_info['is_sticky']))
			unset($_POST['sticky']);
		elseif (isset($_POST['sticky']))
		{
			// Did someone (un)sticky this while you were posting?
			if (isset($_POST['already_sticky']) && $_POST['already_sticky'] != $topic_info['is_sticky'])
				$post_errors[] = 'topic_' . (empty($topic_info['locked']) ? 'un' : '') . 'stickied';
		}

		if ($row['id_member'] == User::$me->id && !allowedTo('modify_any'))
		{
			if ((!Config::$modSettings['postmod_active'] || $row['approved']) && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($topic_info['id_member_started'] == User::$me->id && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($topic_info['id_member_started'] == User::$me->id && !allowedTo('modify_any'))
		{
			isAllowedTo('modify_replies');

			// If you're modifying a reply, I say it better be logged...
			$moderationAction = true;
		}
		else
		{
			isAllowedTo('modify_any');

			// Log it, assuming you're not modifying your own post.
			if ($row['id_member'] != User::$me->id)
				$moderationAction = true;
		}

		// If drafts are enabled, then lets send this off to save
		if (!empty(Config::$modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		$posterIsGuest = empty($row['id_member']);
		Utils::$context['is_own_post'] = User::$me->id === (int) $row['id_member'];
		Utils::$context['poster_id'] = (int) $row['id_member'];

		// Can they approve it?
		$approve_checked = (!empty($REQUEST['approve']) ? 1 : 0);
		$becomesApproved = Config::$modSettings['postmod_active'] ? ($can_approve && !$row['approved'] ? $approve_checked : $row['approved']) : 1;
		$approve_has_changed = $row['approved'] != $becomesApproved;

		if (!allowedTo('moderate_forum') || !$posterIsGuest)
		{
			$_POST['guestname'] = $row['poster_name'];
			$_POST['email'] = $row['poster_email'];
		}

		// Update search api
		$searchAPI = SearchApi::load();

		if ($searchAPI->supportsMethod('postRemoved'))
			$searchAPI->postRemoved($_REQUEST['msg']);
	}

	// In case we have approval permissions and want to override.
	if ($can_approve && Config::$modSettings['postmod_active'])
	{
		$becomesApproved = isset($_POST['quickReply']) || !empty($_REQUEST['approve']) ? 1 : 0;
		$approve_has_changed = isset($row['approved']) ? $row['approved'] != $becomesApproved : false;
	}

	// If the poster is a guest evaluate the legality of name and email.
	if ($posterIsGuest)
	{
		$_POST['guestname'] = !isset($_POST['guestname']) ? '' : trim(Utils::normalizeSpaces(Utils::sanitizeChars($_POST['guestname'], 1, ' '), true, true, array('no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true)));
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

		if ($_POST['guestname'] == '' || $_POST['guestname'] == '_')
			$post_errors[] = 'no_name';
		if (Utils::entityStrlen($_POST['guestname']) > 25)
			$post_errors[] = 'long_name';

		if (empty(Config::$modSettings['guest_post_no_email']))
		{
			// Only check if they changed it!
			if (!isset($row) || $row['poster_email'] != $_POST['email'])
			{
				if (!allowedTo('moderate_forum') && (!isset($_POST['email']) || $_POST['email'] == ''))
					$post_errors[] = 'no_email';
				if (!allowedTo('moderate_forum') && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
					$post_errors[] = 'bad_email';
			}

			// Now make sure this email address is not banned from posting.
			isBannedEmail($_POST['email'], 'cannot_post', sprintf(Lang::$txt['you_are_post_banned'], Lang::$txt['guest_title']));
		}

		// In case they are making multiple posts this visit, help them along by storing their name.
		if (empty($post_errors))
		{
			$_SESSION['guest_name'] = $_POST['guestname'];
			$_SESSION['guest_email'] = $_POST['email'];
		}
	}

	// Coming from the quickReply?
	if (isset($_POST['quickReply']))
		$_POST['message'] = $_POST['quickReply'];

	// Check the subject and message.
	if (!isset($_POST['subject']) || Utils::htmlTrim(Utils::htmlspecialchars($_POST['subject'])) === '')
		$post_errors[] = 'no_subject';
	if (!isset($_POST['message']) || Utils::htmlTrim(Utils::htmlspecialchars($_POST['message']), ENT_QUOTES) === '')
		$post_errors[] = 'no_message';
	elseif (!empty(Config::$modSettings['max_messageLength']) && Utils::entityStrlen($_POST['message']) > Config::$modSettings['max_messageLength'])
		$post_errors[] = array('long_message', array(Config::$modSettings['max_messageLength']));
	else
	{
		// Prepare the message a bit for some additional testing.
		$_POST['message'] = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);

		// Preparse code. (Zef)
		if (User::$me->is_guest)
			User::$me->name = $_POST['guestname'];
		preparsecode($_POST['message']);

		// Let's see if there's still some content left without the tags.
		if (Utils::htmlTrim(strip_tags(BBCodeParser::load()->parse($_POST['message'], false), implode('', Utils::$context['allowed_html_tags']))) === '' && (!allowedTo('bbc_html') || strpos($_POST['message'], '[html]') === false))
			$post_errors[] = 'no_message';

	}
	if (isset($_POST['calendar']) && !isset($_REQUEST['deleteevent']) && Utils::htmlTrim($_POST['evtitle']) === '')
		$post_errors[] = 'no_event';
	// You are not!
	if (isset($_POST['message']) && strtolower($_POST['message']) == 'i am the administrator.' && !User::$me->is_admin)
		fatal_error('Knave! Masquerader! Charlatan!', false);

	// Validate the poll...
	if (isset($_REQUEST['poll']) && Config::$modSettings['pollMode'] == '1')
	{
		if (!empty(Topic::$topic_id) && !isset($_REQUEST['msg']))
			fatal_lang_error('no_access', false);

		// This is a new topic... so it's a new poll.
		if (empty(Topic::$topic_id))
			isAllowedTo('poll_post');
		// Can you add to your own topics?
		elseif (User::$me->id == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// Can you add polls to any topic, then?
		else
			isAllowedTo('poll_add_any');

		if (!isset($_POST['question']) || trim($_POST['question']) == '')
			$post_errors[] = 'no_question';

		$_POST['options'] = empty($_POST['options']) ? array() : htmltrim__recursive($_POST['options']);

		// Get rid of empty ones.
		foreach ($_POST['options'] as $k => $option)
			if ($option == '')
				unset($_POST['options'][$k], $_POST['options'][$k]);

		// What are you going to vote between with one choice?!?
		if (count($_POST['options']) < 2)
			$post_errors[] = 'poll_few';
		elseif (count($_POST['options']) > 256)
			$post_errors[] = 'poll_many';
	}

	if ($posterIsGuest)
	{
		// If user is a guest, make sure the chosen name isn't taken.
		if (User::isReservedName($_POST['guestname'], 0, true, false) && (!isset($row['poster_name']) || $_POST['guestname'] != $row['poster_name']))
			$post_errors[] = 'bad_name';
	}
	// If the user isn't a guest, get his or her name and email.
	elseif (!isset($_REQUEST['msg']))
	{
		$_POST['guestname'] = User::$me->username;
		$_POST['email'] = User::$me->email;
	}

 	call_integration_hook('integrate_post2_pre', array(&$post_errors));

	// Any mistakes?
	if (!empty($post_errors))
	{
		// Previewing.
		$_REQUEST['preview'] = true;

		return Post($post_errors);
	}

	// Previewing? Go back to start.
	if (isset($_REQUEST['preview']))
	{
		if (checkSession('post', '', false) != '')
		{
			Lang::load('Errors');
			$post_errors[] = 'session_timeout';
			unset ($_POST['preview'], $_REQUEST['xml']); // just in case
		}
		return Post($post_errors);
	}

	// Make sure the user isn't spamming the board.
	if (!isset($_REQUEST['msg']))
		spamProtection('post');

	// At about this point, we're posting and that's that.
	ignore_user_abort(true);
	@set_time_limit(300);

	// Add special html entities to the subject, name, and email.
	$_POST['subject'] = strtr(Utils::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$_POST['guestname'] = Utils::htmlspecialchars($_POST['guestname']);
	$_POST['email'] = Utils::htmlspecialchars($_POST['email']);
	$_POST['modify_reason'] = empty($_POST['modify_reason']) ? '' : strtr(Utils::htmlspecialchars($_POST['modify_reason']), array("\r" => '', "\n" => '', "\t" => ''));

	// At this point, we want to make sure the subject isn't too long.
	if (Utils::entityStrlen($_POST['subject']) > 100)
		$_POST['subject'] = Utils::entitySubstr($_POST['subject'], 0, 100);

	// Same with the "why did you edit this" text.
	if (Utils::entityStrlen($_POST['modify_reason']) > 100)
		$_POST['modify_reason'] = Utils::entitySubstr($_POST['modify_reason'], 0, 100);

	// Make the poll...
	if (isset($_REQUEST['poll']))
	{
		// Make sure that the user has not entered a ridiculous number of options..
		if (empty($_POST['poll_max_votes']) || $_POST['poll_max_votes'] <= 0)
			$_POST['poll_max_votes'] = 1;
		elseif ($_POST['poll_max_votes'] > count($_POST['options']))
			$_POST['poll_max_votes'] = count($_POST['options']);
		else
			$_POST['poll_max_votes'] = (int) $_POST['poll_max_votes'];

		$_POST['poll_expire'] = (int) $_POST['poll_expire'];
		$_POST['poll_expire'] = $_POST['poll_expire'] > 9999 ? 9999 : ($_POST['poll_expire'] < 0 ? 0 : $_POST['poll_expire']);

		// Just set it to zero if it's not there..
		if (!isset($_POST['poll_hide']))
			$_POST['poll_hide'] = 0;
		else
			$_POST['poll_hide'] = (int) $_POST['poll_hide'];
		$_POST['poll_change_vote'] = isset($_POST['poll_change_vote']) ? 1 : 0;

		$_POST['poll_guest_vote'] = isset($_POST['poll_guest_vote']) ? 1 : 0;
		// Make sure guests are actually allowed to vote generally.
		if ($_POST['poll_guest_vote'])
		{
			require_once(Config::$sourcedir . '/Subs-Members.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', Board::$info->id);
			if (!in_array(-1, $allowedVoteGroups['allowed']))
				$_POST['poll_guest_vote'] = 0;
		}

		// If the user tries to set the poll too far in advance, don't let them.
		if (!empty($_POST['poll_expire']) && $_POST['poll_expire'] < 1)
			fatal_lang_error('poll_range_error', false);
		// Don't allow them to select option 2 for hidden results if it's not time limited.
		elseif (empty($_POST['poll_expire']) && $_POST['poll_hide'] == 2)
			$_POST['poll_hide'] = 1;

		// Clean up the question and answers.
		$_POST['question'] = Utils::htmlspecialchars($_POST['question']);
		$_POST['question'] = Utils::truncate($_POST['question'], 255);
		$_POST['question'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $_POST['question']);
		$_POST['options'] = htmlspecialchars__recursive($_POST['options']);
	}

	// ...or attach a new file...
	if (Utils::$context['can_post_attachment'] && !empty($_SESSION['temp_attachments']) && empty($_POST['from_qr']))
	{
		$attachIDs = array();
		$attach_errors = array();
		if (!empty(Utils::$context['we_are_history']))
			$attach_errors[] = '<dd>' . Lang::$txt['error_temp_attachments_flushed'] . '<br><br></dd>';

		foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
		{
			if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . User::$me->id) === false)
				continue;

			// If there was an initial error just show that message.
			if ($attachID == 'initial_error')
			{
				$attach_errors[] = '<dt>' . Lang::$txt['attach_no_upload'] . '</dt>';
				$attach_errors[] = '<dd>' . (is_array($attachment) ? vsprintf(Lang::$txt[$attachment[0]], (array) $attachment[1]) : Lang::$txt[$attachment]) . '</dd>';

				unset($_SESSION['temp_attachments']);
				break;
			}

			$attachmentOptions = array(
				'post' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
				'poster' => User::$me->id,
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => isset($attachment['size']) ? $attachment['size'] : 0,
				'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
				'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : Config::$modSettings['currentAttachmentUploadDir'],
				'approved' => !Config::$modSettings['postmod_active'] || allowedTo('post_attachment'),
				'errors' => $attachment['errors'],
			);

			if (empty($attachment['errors']))
			{
				if (createAttachment($attachmentOptions))
				{
					$attachIDs[] = $attachmentOptions['id'];
					if (!empty($attachmentOptions['thumb']))
						$attachIDs[] = $attachmentOptions['thumb'];
				}
			}
			else
				$attach_errors[] = '<dt>&nbsp;</dt>';

			if (!empty($attachmentOptions['errors']))
			{
				// Sort out the errors for display and delete any associated files.
				$attach_errors[] = '<dt>' . sprintf(Lang::$txt['attach_warning'], $attachment['name']) . '</dt>';
				$log_these = array('attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file');
				foreach ($attachmentOptions['errors'] as $error)
				{
					if (!is_array($error))
					{
						$attach_errors[] = '<dd>' . Lang::$txt[$error] . '</dd>';
						if (in_array($error, $log_these))
							log_error($attachment['name'] . ': ' . Lang::$txt[$error], 'critical');
					}
					else
						$attach_errors[] = '<dd>' . vsprintf(Lang::$txt[$error[0]], (array) $error[1]) . '</dd>';
				}
				if (file_exists($attachment['tmp_name']))
					unlink($attachment['tmp_name']);
			}
		}
	}
	unset($_SESSION['temp_attachments']);

	// Make the poll...
	if (isset($_REQUEST['poll']))
	{
		// Create the poll.
		$id_poll = Db::$db->insert('',
			'{db_prefix}polls',
			array(
				'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
				'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
			),
			array(
				$_POST['question'], $_POST['poll_hide'], $_POST['poll_max_votes'], (empty($_POST['poll_expire']) ? 0 : time() + $_POST['poll_expire'] * 3600 * 24), User::$me->id,
				$_POST['guestname'], $_POST['poll_change_vote'], $_POST['poll_guest_vote'],
			),
			array('id_poll'),
			1
		);

		// Create each answer choice.
		$i = 0;
		$pollOptions = array();
		foreach ($_POST['options'] as $option)
		{
			$pollOptions[] = array($id_poll, $i, $option);
			$i++;
		}

		Db::$db->insert('insert',
			'{db_prefix}poll_choices',
			array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'),
			$pollOptions,
			array('id_poll', 'id_choice')
		);

		call_integration_hook('integrate_poll_add_edit', array($id_poll, false));
	}
	else
		$id_poll = 0;

	// Creating a new topic?
	$newTopic = empty($_REQUEST['msg']) && empty(Topic::$topic_id);

	// Check the icon.
	if (!isset($_POST['icon']))
		$_POST['icon'] = 'xx';

	else
	{
		$_POST['icon'] = Utils::htmlspecialchars($_POST['icon']);

		// Need to figure it out if this is a valid icon name.
		if ((!file_exists($settings['theme_dir'] . '/images/post/' . $_POST['icon'] . '.png')) && (!file_exists($settings['default_theme_dir'] . '/images/post/' . $_POST['icon'] . '.png')))
			$_POST['icon'] = 'xx';
	}

	// Collect all parameters for the creation or modification of a post.
	$msgOptions = array(
		'id' => empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg'],
		'subject' => $_POST['subject'],
		'body' => $_POST['message'],
		'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
		'smileys_enabled' => !isset($_POST['ns']),
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved,
	);
	$topicOptions = array(
		'id' => empty(Topic::$topic_id) ? 0 : Topic::$topic_id,
		'board' => Board::$info->id,
		'poll' => isset($_REQUEST['poll']) ? $id_poll : null,
		'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
		'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
		'mark_as_read' => true,
		'is_approved' => !Config::$modSettings['postmod_active'] || empty(Topic::$topic_id) || !empty(Board::$info->cur_topic_approved),
		'first_msg' => empty($topic_info['id_first_msg']) ? null : $topic_info['id_first_msg'],
		'last_msg' => empty($topic_info['id_last_msg']) ? null : $topic_info['id_last_msg'],
	);
	$posterOptions = array(
		'id' => User::$me->id,
		'name' => $_POST['guestname'],
		'email' => $_POST['email'],
		'update_post_count' => !User::$me->is_guest && !isset($_REQUEST['msg']) && Board::$info->posts_count,
	);

	// This is an already existing message. Edit it.
	if (!empty($_REQUEST['msg']))
	{
		// Have admins allowed people to hide their screwups?
		if (time() - $row['poster_time'] > Config::$modSettings['edit_wait_time'] || User::$me->id != $row['id_member'])
		{
			$msgOptions['modify_time'] = time();
			$msgOptions['modify_name'] = User::$me->name;
			$msgOptions['modify_reason'] = $_POST['modify_reason'];
			$msgOptions['poster_time'] = $row['poster_time'];
		}

		modifyPost($msgOptions, $topicOptions, $posterOptions);
	}
	// This is a new topic or an already existing one. Save it.
	else
	{
		createPost($msgOptions, $topicOptions, $posterOptions);

		if (isset($topicOptions['id']))
			Topic::$topic_id = $topicOptions['id'];
	}

	// Are there attachments already uploaded and waiting to be assigned?
	if (!empty($msgOptions['id']) && !empty($_SESSION['already_attached']))
	{
		require_once(Config::$sourcedir . '/Subs-Attachments.php');
		assignAttachments($_SESSION['already_attached'], $msgOptions['id']);
		unset($_SESSION['already_attached']);
	}

	// If we had a draft for this, its time to remove it since it was just posted
	if (!empty(Config::$modSettings['drafts_post_enabled']) && !empty($_POST['id_draft']))
		DeleteDraft($_POST['id_draft']);

	// Editing or posting an event?
	if (isset($_POST['calendar']) && (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1))
	{
		require_once(Config::$sourcedir . '/Subs-Calendar.php');

		// Make sure they can link an event to this post.
		canLinkEvent();

		// Insert the event.
		$eventOptions = array(
			'board' => Board::$info->id,
			'topic' => Topic::$topic_id,
			'title' => $_POST['evtitle'],
			'location' => $_POST['event_location'],
			'member' => User::$me->id,
		);
		insertEvent($eventOptions);
	}
	elseif (isset($_POST['calendar']))
	{
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

		// Validate the post...
		require_once(Config::$sourcedir . '/Subs-Calendar.php');
		validateEventPost();

		// If you're not allowed to edit any events, you have to be the poster.
		if (!allowedTo('calendar_edit_any'))
		{
			// Get the event's poster.
			$request = Db::$db->query('', '
				SELECT id_member
				FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}',
				array(
					'id_event' => $_REQUEST['eventid'],
				)
			);
			$row2 = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
			isAllowedTo('calendar_edit_' . ($row2['id_member'] == User::$me->id ? 'own' : 'any'));
		}

		// Delete it?
		if (isset($_REQUEST['deleteevent']))
			Db::$db->query('', '
				DELETE FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}',
				array(
					'id_event' => $_REQUEST['eventid'],
				)
			);
		// ... or just update it?
		else
		{
			// Set up our options
			$eventOptions = array(
				'board' => Board::$info->id,
				'topic' => Topic::$topic_id,
				'title' => $_POST['evtitle'],
				'location' => $_POST['event_location'],
				'member' => User::$me->id,
			);
			modifyEvent($_REQUEST['eventid'], $eventOptions);
		}
	}

	// Marking read should be done even for editing messages....
	// Mark all the parents read.  (since you just posted and they will be unread.)
	if (!User::$me->is_guest && !empty(Board::$info->parent_boards))
	{
		Db::$db->query('', '
			UPDATE {db_prefix}log_boards
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_board IN ({array_int:board_list})',
			array(
				'current_member' => User::$me->id,
				'board_list' => array_keys(Board::$info->parent_boards),
				'id_msg' => Config::$modSettings['maxMsgID'],
			)
		);
	}

	// Turn notification on or off.  (note this just blows smoke if it's already on or off.)
	if (!empty($_POST['notify']) && !User::$me->is_guest)
	{
		Db::$db->insert('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
			array(User::$me->id, Topic::$topic_id, 0),
			array('id_member', 'id_topic', 'id_board')
		);
	}
	elseif (!$newTopic)
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => User::$me->id,
				'current_topic' => Topic::$topic_id,
			)
		);

	// Log an act of moderation - modifying.
	if (!empty($moderationAction))
		logAction('modify', array('topic' => Topic::$topic_id, 'message' => (int) $_REQUEST['msg'], 'member' => $row['id_member'], 'board' => Board::$info->id));

	if (isset($_POST['lock']) && $_POST['lock'] != 2)
		logAction(empty($_POST['lock']) ? 'unlock' : 'lock', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	if (isset($_POST['sticky']))
		logAction(empty($_POST['sticky']) ? 'unsticky' : 'sticky', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	// Returning to the topic?
	if (!empty($_REQUEST['goback']))
	{
		// Mark the board as read.... because it might get confusing otherwise.
		Db::$db->query('', '
			UPDATE {db_prefix}log_boards
			SET id_msg = {int:maxMsgID}
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => Board::$info->id,
				'current_member' => User::$me->id,
				'maxMsgID' => Config::$modSettings['maxMsgID'],
			)
		);
	}

	if (Board::$info->num_topics == 0)
		CacheApi::put('board-' . Board::$info->id, null, 120);

	call_integration_hook('integrate_post2_end');

	if (!empty($_POST['announce_topic']) && allowedTo('announce_topic'))
		redirectexit('action=announce;sa=selectgroup;topic=' . Topic::$topic_id . (!empty($_POST['move']) && allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));

	if (!empty($_POST['move']) && allowedTo('move_any'))
		redirectexit('action=movetopic;topic=' . Topic::$topic_id . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));

	// Return to post if the mod is on.
	if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback']))
		redirectexit('topic=' . Topic::$topic_id . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'], BrowserDetector::isBrowser('ie'));
	elseif (!empty($_REQUEST['goback']))
		redirectexit('topic=' . Topic::$topic_id . '.new#new', BrowserDetector::isBrowser('ie'));
	// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
	else
		redirectexit('board=' . Board::$info->id . '.0');
}

/**
 * Handle the announce topic function (action=announce).
 *
 * checks the topic announcement permissions and loads the announcement template.
 * requires the announce_topic permission.
 * uses the ManageMembers template and Post language file.
 * call the right function based on the sub-action.
 */
function AnnounceTopic()
{
	isAllowedTo('announce_topic');

	validateSession();

	if (empty(Topic::$topic_id))
		fatal_lang_error('topic_gone', false);

	Lang::load('Post');
	loadTemplate('Post');

	$subActions = array(
		'selectgroup' => 'AnnouncementSelectMembergroup',
		'send' => 'AnnouncementSend',
	);

	Utils::$context['page_title'] = Lang::$txt['announce_topic'];

	// Call the function based on the sub-action.
	$call = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'selectgroup';
	call_helper($subActions[$call]);
}

/**
 * Allow a user to chose the membergroups to send the announcement to.
 *
 * lets the user select the membergroups that will receive the topic announcement.
 */
function AnnouncementSelectMembergroup()
{
	$groups = array_merge(Board::$info->groups, array(1));
	foreach ($groups as $id => $group)
		$groups[$id] = (int) $group;

	Utils::$context['groups'] = array();
	if (in_array(0, $groups))
	{
		Utils::$context['groups'][0] = array(
			'id' => 0,
			'name' => Lang::$txt['announce_regular_members'],
			'member_count' => 'n/a',
		);
	}

	// Get all membergroups that have access to the board the announcement was made on.
	$request = Db::$db->query('', '
		SELECT mg.id_group, COUNT(mem.id_member) AS num_members
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_group = mg.id_group OR FIND_IN_SET(mg.id_group, mem.additional_groups) != 0 OR mg.id_group = mem.id_post_group)
		WHERE mg.id_group IN ({array_int:group_list})
		GROUP BY mg.id_group',
		array(
			'group_list' => $groups,
			'newbie_id_group' => 4,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		Utils::$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => '',
			'member_count' => $row['num_members'],
		);
	}
	Db::$db->free_result($request);

	// Now get the membergroup names.
	$request = Db::$db->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context['groups'][$row['id_group']]['name'] = $row['group_name'];
	Db::$db->free_result($request);

	// Get the subject of the topic we're about to announce.
	$request = Db::$db->query('', '
		SELECT m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => Topic::$topic_id,
		)
	);
	list (Utils::$context['topic_subject']) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	Lang::censorText(Utils::$context['announce_topic']['subject']);

	Utils::$context['move'] = isset($_REQUEST['move']) ? 1 : 0;
	Utils::$context['go_back'] = isset($_REQUEST['goback']) ? 1 : 0;

	Utils::$context['sub_template'] = 'announce';
}

/**
 * Send the announcement in chunks.
 *
 * splits the members to be sent a topic announcement into chunks.
 * composes notification messages in all languages needed.
 * does the actual sending of the topic announcements in chunks.
 * calculates a rough estimate of the percentage items sent.
 */
function AnnouncementSend()
{
	checkSession();

	Utils::$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$groups = array_merge(Board::$info->groups, array(1));

	if (isset($_POST['membergroups']))
		$_POST['who'] = explode(',', $_POST['membergroups']);

	// Check whether at least one membergroup was selected.
	if (empty($_POST['who']))
		fatal_lang_error('no_membergroup_selected');

	// Make sure all membergroups are integers and can access the board of the announcement.
	foreach ($_POST['who'] as $id => $mg)
		$_POST['who'][$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

	// Get the topic subject and censor it.
	$request = Db::$db->query('', '
		SELECT m.id_msg, m.subject, m.body
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => Topic::$topic_id,
		)
	);
	list ($id_msg, Utils::$context['topic_subject'], $message) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	Lang::censorText(Utils::$context['topic_subject']);
	Lang::censorText($message);

	$message = trim(un_htmlspecialchars(strip_tags(strtr(BBCodeParser::load()->parse($message, false, $id_msg), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	// We need this in order to be able send emails.
	require_once(Config::$sourcedir . '/Msg.php');

	// Select the email addresses for this batch.
	$request = Db::$db->query('', '
		SELECT mem.id_member, mem.email_address, mem.lngfile
		FROM {db_prefix}members AS mem
		WHERE (mem.id_group IN ({array_int:group_list}) OR mem.id_post_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:additional_group_list}, mem.additional_groups) != 0)
			AND mem.is_activated = {int:is_activated}
			AND mem.id_member > {int:start}
		ORDER BY mem.id_member
		LIMIT {int:chunk_size}',
		array(
			'group_list' => $_POST['who'],
			'is_activated' => 1,
			'start' => Utils::$context['start'],
			'additional_group_list' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $_POST['who']),
			// @todo Might need an interface?
			'chunk_size' => 500,
		)
	);

	// All members have received a mail. Go to the next screen.
	if (Db::$db->num_rows($request) == 0)
	{
		logAction('announce_topic', array('topic' => Topic::$topic_id), 'user');
		if (!empty($_REQUEST['move']) && allowedTo('move_any'))
			redirectexit('action=movetopic;topic=' . Topic::$topic_id . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
		elseif (!empty($_REQUEST['goback']))
			redirectexit('topic=' . Topic::$topic_id . '.new;boardseen#new', BrowserDetector::isBrowser('ie'));
		else
			redirectexit('board=' . Board::$info->id . '.0');
	}

	$announcements = array();
	// Loop through all members that'll receive an announcement in this batch.
	$rows = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$rows[$row['id_member']] = $row;
	}
	Db::$db->free_result($request);

	// Load their alert preferences
	require_once(Config::$sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs(array_keys($rows), 'announcements', true);

	foreach ($rows as $row)
	{
		Utils::$context['start'] = $row['id_member'];
		// Force them to have it?
		if (empty($prefs[$row['id_member']]['announcements']))
			continue;

		$cur_language = empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile'];

		// If the language wasn't defined yet, load it and compose a notification message.
		if (!isset($announcements[$cur_language]))
		{
			$replacements = array(
				'TOPICSUBJECT' => Utils::$context['topic_subject'],
				'MESSAGE' => $message,
				'TOPICLINK' => Config::$scripturl . '?topic=' . Topic::$topic_id . '.0',
				'UNSUBSCRIBELINK' => Config::$scripturl . '?action=notifyannouncements;u={UNSUBSCRIBE_ID};token={UNSUBSCRIBE_TOKEN}',
			);

			$emaildata = loadEmailTemplate('new_announcement', $replacements, $cur_language);

			$announcements[$cur_language] = array(
				'subject' => $emaildata['subject'],
				'body' => $emaildata['body'],
				'is_html' => $emaildata['is_html'],
				'recipients' => array(),
			);
		}

		$announcements[$cur_language]['recipients'][$row['id_member']] = $row['email_address'];
	}

	// For each language send a different mail - low priority...
	foreach ($announcements as $lang => $mail)
	{
		foreach ($mail['recipients'] as $member_id => $member_email)
		{
			$token = createUnsubscribeToken($member_id, $member_email, 'announcements');

			$body = str_replace(array('{UNSUBSCRIBE_ID}', '{UNSUBSCRIBE_TOKEN}'), array($member_id, $token), $mail['body']);

			sendmail($member_email, $mail['subject'], $body, null, null, false, 5);
		}

	}

	Utils::$context['percentage_done'] = round(100 * Utils::$context['start'] / Config::$modSettings['latestMember'], 1);

	Utils::$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
	Utils::$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
	Utils::$context['membergroups'] = implode(',', $_POST['who']);
	Utils::$context['sub_template'] = 'announcement_send';

	// Go back to the correct language for the user ;).
	if (!empty(Config::$modSettings['userLanguage']))
		Lang::load('Post');
}

/**
 * Get the topic for display purposes.
 *
 * gets a summary of the most recent posts in a topic.
 * depends on the topicSummaryPosts setting.
 * if you are editing a post, only shows posts previous to that post.
 */
function getTopic()
{
	global $options;
	static $counter;

	if (isset($_REQUEST['xml']))
		$limit = '
		LIMIT ' . (empty(Utils::$context['new_replies']) ? '0' : Utils::$context['new_replies']);
	else
		$limit = empty(Config::$modSettings['topicSummaryPosts']) ? '' : '
		LIMIT ' . (int) Config::$modSettings['topicSummaryPosts'];

	// If you're modifying, get only those posts before the current one. (otherwise get all.)
	$request = Db::$db->query('', '
		SELECT
			COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			m.body, m.smileys_enabled, m.id_msg, m.id_member
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (isset($_REQUEST['msg']) ? '
			AND m.id_msg < {int:id_msg}' : '') . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = {int:approved}') . '
		ORDER BY m.id_msg DESC' . $limit,
		array(
			'current_topic' => Topic::$topic_id,
			'id_msg' => isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0,
			'approved' => 1,
		)
	);
	Utils::$context['previous_posts'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Censor, BBC, ...
		Lang::censorText($row['body']);
		$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

	 	call_integration_hook('integrate_getTopic_previous_post', array(&$row));

		// ...and store.
		Utils::$context['previous_posts'][] = array(
			'counter' => $counter++,
			'poster' => $row['poster_name'],
			'message' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'id' => $row['id_msg'],
			'is_new' => !empty(Utils::$context['new_replies']),
			'is_ignored' => !empty(Config::$modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($row['id_member'], User::$me->ignoreusers),
		);

		if (!empty(Utils::$context['new_replies']))
			Utils::$context['new_replies']--;
	}
	Db::$db->free_result($request);
}

/**
 * Loads a post an inserts it into the current editing text box.
 * uses the Post language file.
 * uses special (sadly browser dependent) javascript to parse entities for internationalization reasons.
 * accessed with ?action=quotefast.
 */
function QuoteFast()
{
	Lang::load('Post');
	if (!isset($_REQUEST['xml']))
		loadTemplate('Post');

	include_once(Config::$sourcedir . '/Msg.php');

	$moderate_boards = boardsAllowedTo('moderate_board');

	$request = Db::$db->query('', '
		SELECT COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body, m.id_topic, m.subject,
			m.id_board, m.id_member, m.approved, m.modified_time, m.modified_name, m.modified_reason
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_message_board}
			AND m.id_msg = {int:id_msg}' . (isset($_REQUEST['modify']) || (!empty($moderate_boards) && $moderate_boards[0] == 0) ? '' : '
			AND (t.locked = {int:not_locked}' . (empty($moderate_boards) ? '' : ' OR m.id_board IN ({array_int:moderation_board_list})') . ')') . '
		LIMIT 1',
		array(
			'current_member' => User::$me->id,
			'moderation_board_list' => $moderate_boards,
			'id_msg' => (int) $_REQUEST['quote'],
			'not_locked' => 0,
		)
	);
	Utils::$context['close_window'] = Db::$db->num_rows($request) == 0;
	$row = Db::$db->fetch_assoc($request);
	Db::$db->free_result($request);

	Utils::$context['sub_template'] = 'quotefast';
	if (!empty($row))
		$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == User::$me->id) || allowedTo('approve_posts', $row['id_board']);

	if (!empty($can_view_post))
	{
		// Remove special formatting we don't want anymore.
		$row['body'] = un_preparsecode($row['body']);

		// Censor the message!
		Lang::censorText($row['body']);

		// Want to modify a single message by double clicking it?
		if (isset($_REQUEST['modify']))
		{
			Lang::censorText($row['subject']);

			Utils::$context['sub_template'] = 'modifyfast';
			Utils::$context['message'] = array(
				'id' => $_REQUEST['quote'],
				'body' => $row['body'],
				'subject' => addcslashes($row['subject'], '"'),
				'reason' => array(
					'name' => $row['modified_name'],
					'text' => $row['modified_reason'],
					'time' => $row['modified_time'],
				),
			);

			return;
		}

		// Remove any nested quotes.
		if (!empty(Config::$modSettings['removeNestedQuotes']))
			$row['body'] = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $row['body']);

		$lb = "\n";

		// Add a quote string on the front and end.
		Utils::$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . ']' . $lb . $row['body'] . $lb . '[/quote]';
		Utils::$context['quote']['text'] = strtr(un_htmlspecialchars(Utils::$context['quote']['xml']), array('\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>'));
		Utils::$context['quote']['xml'] = strtr(Utils::$context['quote']['xml'], array('&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;'));

		Utils::$context['quote']['mozilla'] = strtr(Utils::htmlspecialchars(Utils::$context['quote']['text']), array('&quot;' => '"'));
	}
	//@todo Needs a nicer interface.
	// In case our message has been removed in the meantime.
	elseif (isset($_REQUEST['modify']))
	{
		Utils::$context['sub_template'] = 'modifyfast';
		Utils::$context['message'] = array(
			'id' => 0,
			'body' => '',
			'subject' => '',
			'reason' => array(
				'name' => '',
				'text' => '',
				'time' => '',
			),
		);
	}
	else
		Utils::$context['quote'] = array(
			'xml' => '',
			'mozilla' => '',
			'text' => '',
		);
}

/**
 * Used to edit the body or subject of a message inline
 * called from action=jsmodify from script and topic js
 */
function JavaScriptModify()
{
	// We have to have a topic!
	if (empty(Topic::$topic_id))
		obExit(false);

	checkSession('get');
	require_once(Config::$sourcedir . '/Msg.php');

	// Assume the first message if no message ID was given.
	$request = Db::$db->query('', '
		SELECT
			t.locked, t.num_replies, t.id_member_started, t.id_first_msg,
			m.id_msg, m.id_member, m.poster_time, m.subject, m.smileys_enabled, m.body, m.icon,
			m.modified_time, m.modified_name, m.modified_reason, m.approved,
			m.poster_name, m.poster_email
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {raw:id_msg}
			AND m.id_topic = {int:current_topic}' . (allowedTo('modify_any') || allowedTo('approve_posts') ? '' : (!Config::$modSettings['postmod_active'] ? '
			AND (m.id_member != {int:guest_id} AND m.id_member = {int:current_member})' : '
			AND (m.approved = {int:is_approved} OR (m.id_member != {int:guest_id} AND m.id_member = {int:current_member}))')),
		array(
			'current_member' => User::$me->id,
			'current_topic' => Topic::$topic_id,
			'id_msg' => empty($_REQUEST['msg']) ? 't.id_first_msg' : (int) $_REQUEST['msg'],
			'is_approved' => 1,
			'guest_id' => 0,
		)
	);
	if (Db::$db->num_rows($request) == 0)
		fatal_lang_error('no_board', false);
	$row = Db::$db->fetch_assoc($request);
	Db::$db->free_result($request);

	// Change either body or subject requires permissions to modify messages.
	if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon']))
	{
		if (!empty($row['locked']))
			isAllowedTo('moderate_board');

		if ($row['id_member'] == User::$me->id && !allowedTo('modify_any'))
		{
			if ((!Config::$modSettings['postmod_active'] || $row['approved']) && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_started'] == User::$me->id && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		// Otherwise, they're locked out; someone who can modify the replies is needed.
		elseif ($row['id_member_started'] == User::$me->id && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		// Only log this action if it wasn't your message.
		$moderationAction = $row['id_member'] != User::$me->id;
	}

	$post_errors = array();
	if (isset($_POST['subject']) && Utils::htmlTrim(Utils::htmlspecialchars($_POST['subject'])) !== '')
	{
		$_POST['subject'] = strtr(Utils::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));

		// Maximum number of characters.
		if (Utils::entityStrlen($_POST['subject']) > 100)
			$_POST['subject'] = Utils::entitySubstr($_POST['subject'], 0, 100);
	}
	elseif (isset($_POST['subject']))
	{
		$post_errors[] = 'no_subject';
		unset($_POST['subject']);
	}

	if (isset($_POST['message']))
	{
		if (Utils::htmlTrim(Utils::htmlspecialchars($_POST['message'])) === '')
		{
			$post_errors[] = 'no_message';
			unset($_POST['message']);
		}
		elseif (!empty(Config::$modSettings['max_messageLength']) && Utils::entityStrlen($_POST['message']) > Config::$modSettings['max_messageLength'])
		{
			$post_errors[] = 'long_message';
			unset($_POST['message']);
		}
		else
		{
			$_POST['message'] = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);

			preparsecode($_POST['message']);

			if (Utils::htmlTrim(strip_tags(BBCodeParser::load()->parse($_POST['message'], false), implode('', Utils::$context['allowed_html_tags']))) === '')
			{
				$post_errors[] = 'no_message';
				unset($_POST['message']);
			}
		}
	}

 	call_integration_hook('integrate_post_JavascriptModify', array(&$post_errors, $row));

	if (isset($_POST['lock']))
	{
		if (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && User::$me->id != $row['id_member']))
			unset($_POST['lock']);
		elseif (!allowedTo('lock_any'))
		{
			if ($row['locked'] == 1)
				unset($_POST['lock']);
			else
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
		}
		elseif (!empty($row['locked']) && !empty($_POST['lock']) || $_POST['lock'] == $row['locked'])
			unset($_POST['lock']);
		else
			$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
	}

	if (isset($_POST['sticky']) && !allowedTo('make_sticky'))
		unset($_POST['sticky']);

	if (isset($_POST['modify_reason']))
	{
		$_POST['modify_reason'] = strtr(Utils::htmlspecialchars($_POST['modify_reason']), array("\r" => '', "\n" => '', "\t" => ''));

		// Maximum number of characters.
		if (Utils::entityStrlen($_POST['modify_reason']) > 100)
			$_POST['modify_reason'] = Utils::entitySubstr($_POST['modify_reason'], 0, 100);
	}

	if (empty($post_errors))
	{
		$msgOptions = array(
			'id' => $row['id_msg'],
			'subject' => isset($_POST['subject']) ? $_POST['subject'] : null,
			'body' => isset($_POST['message']) ? $_POST['message'] : null,
			'icon' => isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
			'modify_reason' => (isset($_POST['modify_reason']) ? $_POST['modify_reason'] : ''),
			'approved' => (isset($row['approved']) ? $row['approved'] : null),
		);
		$topicOptions = array(
			'id' => Topic::$topic_id,
			'board' => Board::$info->id,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
		);
		$posterOptions = array(
			'id' => User::$me->id,
			'name' => $row['poster_name'],
			'email' => $row['poster_email'],
			'update_post_count' => !User::$me->is_guest && !isset($_REQUEST['msg']) && Board::$info->posts_count,
		);

		// Only consider marking as editing if they have edited the subject, message or icon.
		if ((isset($_POST['subject']) && $_POST['subject'] != $row['subject']) || (isset($_POST['message']) && $_POST['message'] != $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] != $row['icon']))
		{
			// And even then only if the time has passed...
			if (time() - $row['poster_time'] > Config::$modSettings['edit_wait_time'] || User::$me->id != $row['id_member'])
			{
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = User::$me->name;
			}
		}
		// If nothing was changed there's no need to add an entry to the moderation log.
		else
			$moderationAction = false;

		modifyPost($msgOptions, $topicOptions, $posterOptions);

		// If we didn't change anything this time but had before put back the old info.
		if (!isset($msgOptions['modify_time']) && !empty($row['modified_time']))
		{
			$msgOptions['modify_time'] = $row['modified_time'];
			$msgOptions['modify_name'] = $row['modified_name'];
			$msgOptions['modify_reason'] = $row['modified_reason'];
		}

		// Changing the first subject updates other subjects to 'Re: new_subject'.
		if (isset($_POST['subject']) && isset($_REQUEST['change_all_subjects']) && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (allowedTo('modify_any') || ($row['id_member_started'] == User::$me->id && allowedTo('modify_replies'))))
		{
			// Get the proper (default language) response prefix first.
			if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix')))
			{
				if (Lang::$default === User::$me->language)
					Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
				else
				{
					Lang::load('index', Lang::$default, false);
					Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
					Lang::load('index');
				}
				CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
			}

			Db::$db->query('', '
				UPDATE {db_prefix}messages
				SET subject = {string:subject}
				WHERE id_topic = {int:current_topic}
					AND id_msg != {int:id_first_msg}',
				array(
					'current_topic' => Topic::$topic_id,
					'id_first_msg' => $row['id_first_msg'],
					'subject' => Utils::$context['response_prefix'] . $_POST['subject'],
				)
			);
		}

		if (!empty($moderationAction))
			logAction('modify', array('topic' => Topic::$topic_id, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => Board::$info->id));
	}

	if (isset($_REQUEST['xml']))
	{
		Utils::$context['sub_template'] = 'modifydone';
		if (empty($post_errors) && isset($msgOptions['subject']) && isset($msgOptions['body']))
		{
			Utils::$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? $msgOptions['modify_time'] : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					'reason' => $msgOptions['modify_reason'],
				),
				'subject' => $msgOptions['subject'],
				'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
				'body' => strtr($msgOptions['body'], array(']]>' => ']]]]><![CDATA[>')),
			);

			Lang::censorText(Utils::$context['message']['subject']);
			Lang::censorText(Utils::$context['message']['body']);

			Utils::$context['message']['body'] = BBCodeParser::load()->parse(Utils::$context['message']['body'], $row['smileys_enabled'], $row['id_msg']);
		}
		// Topic?
		elseif (empty($post_errors))
		{
			Utils::$context['sub_template'] = 'modifytopicdone';
			Utils::$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? $msgOptions['modify_time'] : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
				),
				'subject' => isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
			);

			Lang::censorText(Utils::$context['message']['subject']);
		}
		else
		{
			Utils::$context['message'] = array(
				'id' => $row['id_msg'],
				'errors' => array(),
				'error_in_subject' => in_array('no_subject', $post_errors),
				'error_in_body' => in_array('no_message', $post_errors) || in_array('long_message', $post_errors),
			);

			Lang::load('Errors');
			foreach ($post_errors as $post_error)
			{
				if ($post_error == 'long_message')
					Utils::$context['message']['errors'][] = sprintf(Lang::$txt['error_' . $post_error], Config::$modSettings['max_messageLength']);
				else
					Utils::$context['message']['errors'][] = Lang::$txt['error_' . $post_error];
			}
		}

		// Allow mods to do something with Utils::$context before we return.
		call_integration_hook('integrate_jsmodify_xml');
	}
	else
		obExit(false);
}

?>