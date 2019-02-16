<?php

/**
 * The job of this file is to handle everything related to posting replies,
 * new topics, quotes, and modifications to existing posts.  It also handles
 * quoting posts by way of javascript.
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Handles showing the post screen, loading the post to be modified, and loading any post quoted.
 *
 * - additionally handles previews of posts.
 * - @uses the Post template and language file, main sub template.
 * - requires different permissions depending on the actions, but most notably post_new, post_reply_own, and post_reply_any.
 * - shows options for the editing and posting of calendar events and attachments, as well as the posting of polls.
 * - accessed from ?action=post.
 *
 * @param array $post_errors Holds any errors found while tyring to post
 */
function Post($post_errors = array())
{
	global $txt, $scripturl, $topic, $modSettings, $board;
	global $user_info, $context, $settings;
	global $sourcedir, $smcFunc, $language;

	loadLanguage('Post');
	if (!empty($modSettings['drafts_post_enabled']))
		loadLanguage('Drafts');

	// You can't reply with a poll... hacker.
	if (isset($_REQUEST['poll']) && !empty($topic) && !isset($_REQUEST['msg']))
		unset($_REQUEST['poll']);

	// Posting an event?
	$context['make_event'] = isset($_REQUEST['calendar']);
	$context['robot_no_index'] = true;

	call_integration_hook('integrate_post_start');

	// Get notification preferences for later
	require_once($sourcedir . '/Subs-Notify.php');
	// use $temp to get around "Only variables should be passed by reference"
	$temp = getNotifyPrefs($user_info['id']);
	$context['notify_prefs'] = (array) array_pop($temp);
	$context['auto_notify'] = !empty($context['notify_prefs']['msg_auto_notify']);

	// Not in a board? Fine, but we'll make them pick one eventually.
	if (empty($board) || $context['make_event'])
	{
		// Get ids of all the boards they can post in.
		$post_permissions = array('post_new');
		if ($modSettings['postmod_active'])
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
			'selected_board' => !empty($board) ? $board : ($context['make_event'] && !empty($modSettings['cal_defaultboard']) ? $modSettings['cal_defaultboard'] : $boards[0]),
		);
		$board_list = getBoardList($boardListOptions);
	}
	// Let's keep things simple for ourselves below
	else
		$boards = array($board);

	require_once($sourcedir . '/Subs-Post.php');

	if (isset($_REQUEST['xml']))
	{
		$context['sub_template'] = 'post';

		// Just in case of an earlier error...
		$context['preview_message'] = '';
		$context['preview_subject'] = '';
	}

	// No message is complete without a topic.
	if (empty($topic) && !empty($_REQUEST['msg']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => (int) $_REQUEST['msg'],
			)
		);
		if ($smcFunc['db_num_rows']($request) != 1)
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
		else
			list ($topic) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Check if it's locked. It isn't locked if no topic is specified.
	if (!empty($topic))
	{
		$request = $smcFunc['db_query']('', '
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
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		list ($locked, $topic_approved, $context['notify'], $sticky, $pollID, $context['topic_last_message'], $id_member_poster, $id_first_msg, $first_subject, $editReason, $lastPostTime) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// If this topic already has a poll, they sure can't add another.
		if (isset($_REQUEST['poll']) && $pollID > 0)
			unset($_REQUEST['poll']);

		if (empty($_REQUEST['msg']))
		{
			if ($user_info['is_guest'] && !allowedTo('post_reply_any') && (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
				is_not_guest();

			// By default the reply will be approved...
			$context['becomes_approved'] = true;
			if ($id_member_poster != $user_info['id'] || $user_info['is_guest'])
			{
				if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_any');
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if ($modSettings['postmod_active'] && ((allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own')) || allowedTo('post_unapproved_replies_any')))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_own');
			}
		}
		else
			$context['becomes_approved'] = true;

		$context['can_lock'] = allowedTo('lock_any') || ($user_info['id'] == $id_member_poster && allowedTo('lock_own'));
		$context['can_sticky'] = allowedTo('make_sticky');
		$context['can_move'] = allowedTo('move_any');
		// You can only announce topics that will get approved...
		$context['can_announce'] = allowedTo('announce_topic') && $context['becomes_approved'];
		$context['show_approval'] = !allowedTo('approve_posts') ? 0 : ($context['becomes_approved'] && !empty($topic_approved) ? 2 : 1);

		// We don't always want the request vars to override what's in the db...
		$context['already_locked'] = $locked;
		$context['already_sticky'] = $sticky;
		$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : $sticky;

		// Check whether this is a really old post being bumped...
		if (!empty($modSettings['oldTopicDays']) && $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time() && empty($sticky) && !isset($_REQUEST['subject']))
			$post_errors[] = array('old_topic', array($modSettings['oldTopicDays']));
	}
	else
	{
		// @todo Should use JavaScript to hide and show the warning based on the selection in the board select menu
		$context['becomes_approved'] = true;
		if ($modSettings['postmod_active'] && !allowedTo('post_new', $boards, true) && allowedTo('post_unapproved_topics', $boards, true))
			$context['becomes_approved'] = false;
		else
			isAllowedTo('post_new', $boards, true);

		$locked = 0;
		$context['already_locked'] = 0;
		$context['already_sticky'] = 0;
		$context['sticky'] = !empty($_REQUEST['sticky']);

		// What options should we show?
		$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'), $boards, true);
		$context['can_sticky'] = allowedTo('make_sticky', $boards, true);
		$context['can_move'] = allowedTo('move_any', $boards, true);
		$context['can_announce'] = allowedTo('announce_topic', $boards, true) && $context['becomes_approved'];
		$context['show_approval'] = !allowedTo('approve_posts', $boards, true) ? 0 : ($context['becomes_approved'] ? 2 : 1);
	}

	$context['notify'] = !empty($context['notify']);

	$context['can_notify'] = !$context['user']['is_guest'];
	$context['move'] = !empty($_REQUEST['move']);
	$context['announce'] = !empty($_REQUEST['announce']);
	$context['locked'] = !empty($locked) || !empty($_REQUEST['lock']);
	$context['can_quote'] = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

	// An array to hold all the attachments for this topic.
	$context['current_attachments'] = array();

	// Clear out prior attachment activity when starting afresh
	if (empty($_REQUEST['message']) && empty($_REQUEST['preview']) && !empty($_SESSION['already_attached']))
	{
		require_once($sourcedir . '/ManageAttachments.php');
		foreach ($_SESSION['already_attached'] as $attachID => $attachment)
			removeAttachments(array('id_attach' => $attachID));

		unset($_SESSION['already_attached']);
	}

	// Don't allow a post if it's locked and you aren't all powerful.
	if ($locked && !allowedTo('moderate_board'))
		fatal_lang_error('topic_locked', false);
	// Check the users permissions - is the user allowed to add or post a poll?
	if (isset($_REQUEST['poll']) && $modSettings['pollMode'] == '1')
	{
		// New topic, new poll.
		if (empty($topic))
			isAllowedTo('poll_post');
		// This is an old topic - but it is yours!  Can you add to it?
		elseif ($user_info['id'] == $id_member_poster && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// If you're not the owner, can you add to any poll?
		else
			isAllowedTo('poll_add_any');

		if (!empty($board))
		{
			require_once($sourcedir . '/Subs-Members.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);
			$guest_vote_enabled = in_array(-1, $allowedVoteGroups['allowed']);
		}
		// No board, so we'll have to check this again in Post2
		else
			$guest_vote_enabled = true;

		// Set up the poll options.
		$context['poll_options'] = array(
			'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
			'hide' => empty($_POST['poll_hide']) ? 0 : $_POST['poll_hide'],
			'expire' => !isset($_POST['poll_expire']) ? '' : $_POST['poll_expire'],
			'change_vote' => isset($_POST['poll_change_vote']),
			'guest_vote' => isset($_POST['poll_guest_vote']),
			'guest_vote_enabled' => $guest_vote_enabled,
		);

		// Make all five poll choices empty.
		$context['choices'] = array(
			array('id' => 0, 'number' => 1, 'label' => '', 'is_last' => false),
			array('id' => 1, 'number' => 2, 'label' => '', 'is_last' => false),
			array('id' => 2, 'number' => 3, 'label' => '', 'is_last' => false),
			array('id' => 3, 'number' => 4, 'label' => '', 'is_last' => false),
			array('id' => 4, 'number' => 5, 'label' => '', 'is_last' => true)
		);
		$context['last_choice_id'] = 4;
	}

	if ($context['make_event'])
	{
		// They might want to pick a board.
		if (!isset($context['current_board']))
			$context['current_board'] = 0;

		// Start loading up the event info.
		$context['event'] = array();
		$context['event']['title'] = isset($_REQUEST['evtitle']) ? $smcFunc['htmlspecialchars'](stripslashes($_REQUEST['evtitle'])) : '';
		$context['event']['location'] = isset($_REQUEST['event_location']) ? $smcFunc['htmlspecialchars'](stripslashes($_REQUEST['event_location'])) : '';

		$context['event']['id'] = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : -1;
		$context['event']['new'] = $context['event']['id'] == -1;

		// Permissions check!
		isAllowedTo('calendar_post');

		// We want a fairly compact version of the time, but as close as possible to the user's settings.
		if (preg_match('~%[HkIlMpPrRSTX](?:[^%]*%[HkIlMpPrRSTX])*~', $user_info['time_format'], $matches) == 0 || empty($matches[0]))
			$time_string = '%k:%M';
		else
			$time_string = str_replace(array('%I', '%H', '%S', '%r', '%R', '%T'), array('%l', '%k', '', '%l:%M %p', '%k:%M', '%l:%M'), $matches[0]);

		$js_time_string = str_replace(
			array('%H', '%k', '%I', '%l', '%M', '%p', '%P', '%r', '%R', '%S', '%T', '%X'),
			array('H', 'G', 'h', 'g', 'i', 'A', 'a', 'h:i:s A', 'H:i', 's', 'H:i:s', 'H:i:s'),
			$time_string
		);

		// Editing an event?  (but NOT previewing!?)
		if (empty($context['event']['new']) && !isset($_REQUEST['subject']))
		{
			// If the user doesn't have permission to edit the post in this topic, redirect them.
			if ((empty($id_member_poster) || $id_member_poster != $user_info['id'] || !allowedTo('modify_own')) && !allowedTo('modify_any'))
			{
				require_once($sourcedir . '/Calendar.php');
				return CalendarPost();
			}

			// Get the current event information.
			require_once($sourcedir . '/Subs-Calendar.php');
			$eventProperties = getEventProperties($context['event']['id']);
			$context['event'] = array_merge($context['event'], $eventProperties);
		}
		else
		{
			// Get the current event information.
			require_once($sourcedir . '/Subs-Calendar.php');
			$eventProperties = getNewEventDatetimes();
			$context['event'] = array_merge($context['event'], $eventProperties);

			// Make sure the year and month are in the valid range.
			if ($context['event']['month'] < 1 || $context['event']['month'] > 12)
				fatal_lang_error('invalid_month', false);
			if ($context['event']['year'] < $modSettings['cal_minyear'] || $context['event']['year'] > $modSettings['cal_maxyear'])
				fatal_lang_error('invalid_year', false);

			$context['event']['categories'] = $board_list;
		}

		// Find the last day of the month.
		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		// An all day event? Set up some nice defaults in case the user wants to change that
		if ($context['event']['allday'] == true)
		{
			$context['event']['tz'] = getUserTimezone();
			$context['event']['start_time'] = timeformat(time(), $time_string);
			$context['event']['end_time'] = timeformat(time() + 3600, $time_string);
		}
		// Otherwise, just adjust these to look nice on the input form
		else
		{
			$context['event']['start_time'] = $context['event']['start_time_orig'];
			$context['event']['end_time'] = $context['event']['end_time_orig'];
		}

		// Need this so the user can select a timezone for the event.
		$context['all_timezones'] = smf_list_timezones($context['event']['start_date']);
		unset($context['all_timezones']['']);

		// If the event's timezone is not in SMF's standard list of time zones, prepend it to the list
		if (!in_array($context['event']['tz'], array_keys($context['all_timezones'])))
		{
			$d = date_create($context['event']['start_datetime'] . ' ' . $context['event']['tz']);
			$context['all_timezones'] = array($context['event']['tz'] => '[UTC' . date_format($d, 'P') . '] - ' . $context['event']['tz']) + $context['all_timezones'];
		}

		loadCSSFile('jquery-ui.datepicker.css', array(), 'smf_datepicker');
		loadCSSFile('jquery.timepicker.css', array(), 'smf_timepicker');
		loadJavaScriptFile('jquery-ui.datepicker.min.js', array('defer' => true), 'smf_datepicker');
		loadJavaScriptFile('jquery.timepicker.min.js', array('defer' => true), 'smf_timepicker');
		loadJavaScriptFile('datepair.min.js', array('defer' => true), 'smf_datepair');
		addInlineJavaScript('
	$("#allday").click(function(){
		$("#start_time").attr("disabled", this.checked);
		$("#end_time").attr("disabled", this.checked);
		$("#tz").attr("disabled", this.checked);
	});
	$("#event_time_input .date_input").datepicker({
		dateFormat: "yy-mm-dd",
		autoSize: true,
		isRTL: ' . ($context['right_to_left'] ? 'true' : 'false') . ',
		constrainInput: true,
		showAnim: "",
		showButtonPanel: false,
		minDate: "' . $modSettings['cal_minyear'] . '-01-01",
		maxDate: "' . $modSettings['cal_maxyear'] . '-12-31",
		yearRange: "' . $modSettings['cal_minyear'] . ':' . $modSettings['cal_maxyear'] . '",
		hideIfNoPrevNext: true,
		monthNames: ["' . implode('", "', $txt['months_titles']) . '"],
		monthNamesShort: ["' . implode('", "', $txt['months_short']) . '"],
		dayNames: ["' . implode('", "', $txt['days']) . '"],
		dayNamesShort: ["' . implode('", "', $txt['days_short']) . '"],
		dayNamesMin: ["' . implode('", "', $txt['days_short']) . '"],
		prevText: "' . $txt['prev_month'] . '",
		nextText: "' . $txt['next_month'] . '",
	});
	$(".time_input").timepicker({
		timeFormat: "' . $js_time_string . '",
		showDuration: true,
		maxTime: "23:59:59",
	});
	var date_entry = document.getElementById("event_time_input");
	var date_entry_pair = new Datepair(date_entry, {
		timeClass: "time_input",
		dateClass: "date_input",
		parseDate: function (el) {
			var utc = new Date($(el).datepicker("getDate"));
			return utc && new Date(utc.getTime() + (utc.getTimezoneOffset() * 60000));
		},
		updateDate: function (el, v) {
			$(el).datepicker("setDate", new Date(v.getTime() - (v.getTimezoneOffset() * 60000)));
		}
	});
	', true);

		$context['event']['board'] = !empty($board) ? $board : $modSettings['cal_defaultboard'];
		$context['event']['topic'] = !empty($topic) ? $topic : 0;
	}

	// See if any new replies have come along.
	// Huh, $_REQUEST['msg'] is set upon submit, so this doesn't get executed at submit
	// only at preview
	if (empty($_REQUEST['msg']) && !empty($topic))
	{
		if (isset($_REQUEST['last_msg']) && $context['topic_last_message'] > $_REQUEST['last_msg'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg > {int:last_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:approved}') . '
				LIMIT 1',
				array(
					'current_topic' => $topic,
					'last_msg' => (int) $_REQUEST['last_msg'],
					'approved' => 1,
				)
			);
			list ($context['new_replies']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($context['new_replies']))
			{
				if ($context['new_replies'] == 1)
					$txt['error_new_replies'] = isset($_GET['last_msg']) ? $txt['error_new_reply_reading'] : $txt['error_new_reply'];
				else
					$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? $txt['error_new_replies_reading'] : $txt['error_new_replies'], $context['new_replies']);

				$post_errors[] = 'new_replies';

				$modSettings['topicSummaryPosts'] = $context['new_replies'] > $modSettings['topicSummaryPosts'] ? max($modSettings['topicSummaryPosts'], 5) : $modSettings['topicSummaryPosts'];
			}
		}
	}

	// Get a response prefix (like 'Re:') in the default forum language.
	if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
	{
		if ($language === $user_info['language'])
			$context['response_prefix'] = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$context['response_prefix'] = $txt['response_prefix'];
			loadLanguage('index');
		}
		cache_put_data('response_prefix', $context['response_prefix'], 600);
	}

	// Previewing, modifying, or posting?
	// Do we have a body, but an error happened.
	if (isset($_REQUEST['message']) || isset($_REQUEST['quickReply']) || !empty($context['post_error']))
	{
		if (isset($_REQUEST['quickReply']))
			$_REQUEST['message'] = $_REQUEST['quickReply'];

		// Validate inputs.
		if (empty($context['post_error']))
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
		$context['becomes_approved'] = empty($_REQUEST['not_approved']);
		$context['show_approval'] = isset($_REQUEST['approve']) ? ($_REQUEST['approve'] ? 2 : 1) : 0;
		$context['can_announce'] &= $context['becomes_approved'];

		// Set up the inputs for the form.
		$form_subject = strtr($smcFunc['htmlspecialchars']($_REQUEST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$form_message = $smcFunc['htmlspecialchars']($_REQUEST['message'], ENT_QUOTES);

		// Make sure the subject isn't too long - taking into account special characters.
		if ($smcFunc['strlen']($form_subject) > 100)
			$form_subject = $smcFunc['substr']($form_subject, 0, 100);

		if (isset($_REQUEST['poll']))
		{
			$context['question'] = isset($_REQUEST['question']) ? $smcFunc['htmlspecialchars'](trim($_REQUEST['question'])) : '';

			$context['choices'] = array();
			$choice_id = 0;

			$_POST['options'] = empty($_POST['options']) ? array() : htmlspecialchars__recursive($_POST['options']);
			foreach ($_POST['options'] as $option)
			{
				if (trim($option) == '')
					continue;

				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => $option,
					'is_last' => false
				);
			}

			// One empty option for those with js disabled...I know are few... :P
			$context['choices'][] = array(
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => '',
				'is_last' => false
			);

			if (count($context['choices']) < 2)
			{
				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false
				);
			}
			$context['last_choice_id'] = $choice_id;
			$context['choices'][count($context['choices']) - 1]['is_last'] = true;
		}

		// Are you... a guest?
		if ($user_info['is_guest'])
		{
			$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
			$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

			$_REQUEST['guestname'] = $smcFunc['htmlspecialchars']($_REQUEST['guestname']);
			$context['name'] = $_REQUEST['guestname'];
			$_REQUEST['email'] = $smcFunc['htmlspecialchars']($_REQUEST['email']);
			$context['email'] = $_REQUEST['email'];

			$user_info['name'] = $_REQUEST['guestname'];
		}

		// Only show the preview stuff if they hit Preview.
		if (($really_previewing == true || isset($_REQUEST['xml'])) && !isset($_REQUEST['save_draft']))
		{
			// Set up the preview message and subject and censor them...
			$context['preview_message'] = $form_message;
			preparsecode($form_message, true);
			preparsecode($context['preview_message']);

			// Do all bulletin board code tags, with or without smileys.
			$context['preview_message'] = parse_bbc($context['preview_message'], isset($_REQUEST['ns']) ? 0 : 1);
			censorText($context['preview_message']);

			if ($form_subject != '')
			{
				$context['preview_subject'] = $form_subject;

				censorText($context['preview_subject']);
			}
			else
				$context['preview_subject'] = '<em>' . $txt['no_subject'] . '</em>';

			// Protect any CDATA blocks.
			if (isset($_REQUEST['xml']))
				$context['preview_message'] = strtr($context['preview_message'], array(']]>' => ']]]]><![CDATA[>'));
		}

		// Set up the checkboxes.
		$context['notify'] = !empty($_REQUEST['notify']);
		$context['use_smileys'] = !isset($_REQUEST['ns']);

		$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

		// Set the destination action for submission.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');
		$context['submit_label'] = isset($_REQUEST['msg']) ? $txt['save'] : $txt['post'];

		// Previewing an edit?
		if (isset($_REQUEST['msg']) && !empty($topic))
		{
			// Get the existing message. Previewing.
			$request = $smcFunc['db_query']('', '
				SELECT
					m.id_member, m.modified_time, m.smileys_enabled, m.body,
					m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
					COALESCE(a.size, -1) AS filesize, a.filename, a.id_attach,
					a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
					m.poster_time, log.id_action
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
					LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
				WHERE m.id_msg = {int:id_msg}
					AND m.id_topic = {int:current_topic}',
				array(
					'current_topic' => $topic,
					'attachment_type' => 0,
					'id_msg' => $_REQUEST['msg'],
					'announce_action' => 'announce_topic',
				)
			);
			// The message they were trying to edit was most likely deleted.
			// @todo Change this error message?
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('no_board', false);
			$row = $smcFunc['db_fetch_assoc']($request);

			$attachment_stuff = array($row);
			while ($row2 = $smcFunc['db_fetch_assoc']($request))
				$attachment_stuff[] = $row2;
			$smcFunc['db_free_result']($request);

			if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
				if ($row['approved'] && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
				elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
					isAllowedTo('modify_replies');
				else
					isAllowedTo('modify_own');
			}
			elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_any');

			if ($context['can_announce'] && !empty($row['id_action']))
			{
				loadLanguage('Errors');
				$context['post_error']['messages'][] = $txt['error_topic_already_announced'];
			}

			if (!empty($modSettings['attachmentEnable']))
			{
				$request = $smcFunc['db_query']('', '
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

				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					if ($row['filesize'] <= 0)
						continue;
					$context['current_attachments'][$row['id_attach']] = array(
						'name' => $smcFunc['htmlspecialchars']($row['filename']),
						'size' => $row['filesize'],
						'attachID' => $row['id_attach'],
						'approved' => $row['approved'],
						'mime_type' => $row['mime_type'],
						'thumb' => $row['id_thumb'],
					);
				}
				$smcFunc['db_free_result']($request);
			}

			// Allow moderators to change names....
			if (allowedTo('moderate_forum') && !empty($topic))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member, poster_name, poster_email
					FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}
						AND id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_topic' => $topic,
						'id_msg' => (int) $_REQUEST['msg'],
					)
				);
				$row = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);

				if (empty($row['id_member']))
				{
					$context['name'] = $smcFunc['htmlspecialchars']($row['poster_name']);
					$context['email'] = $smcFunc['htmlspecialchars']($row['poster_email']);
				}
			}
		}

		// No check is needed, since nothing is really posted.
		checkSubmitOnce('free');
	}
	// Editing a message...
	elseif (isset($_REQUEST['msg']) && !empty($topic))
	{
		$context['editing'] = true;

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Get the existing message. Editing.
		$request = $smcFunc['db_query']('', '
			SELECT
				m.id_member, m.modified_time, m.modified_name, m.modified_reason, m.smileys_enabled, m.body,
				m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
				COALESCE(a.size, -1) AS filesize, a.filename, a.id_attach, a.mime_type, a.id_thumb,
				a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
				m.poster_time, log.id_action
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
					LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
				'attachment_type' => 0,
				'id_msg' => $_REQUEST['msg'],
				'announce_action' => 'announce_topic',
			)
		);
		// The message they were trying to edit was most likely deleted.
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_message', false);
		$row = $smcFunc['db_fetch_assoc']($request);

		$attachment_stuff = array($row);
		while ($row2 = $smcFunc['db_fetch_assoc']($request))
			$attachment_stuff[] = $row2;
		$smcFunc['db_free_result']($request);

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
			if ($row['approved'] && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		if ($context['can_announce'] && !empty($row['id_action']))
		{
			loadLanguage('Errors');
			$context['post_error']['messages'][] = $txt['error_topic_already_announced'];
		}

		// When was it last modified?
		if (!empty($row['modified_time']))
		{
			$context['last_modified'] = timeformat($row['modified_time']);
			$context['last_modified_reason'] = censorText($row['modified_reason']);
			$context['last_modified_text'] = sprintf($txt['last_edit_by'], $context['last_modified'], $row['modified_name']) . empty($row['modified_reason']) ? '' : '&nbsp;' . $txt['last_edit_reason'] . ':&nbsp;' . $row['modified_reason'];
		}

		// Get the stuff ready for the form.
		$form_subject = $row['subject'];
		$form_message = un_preparsecode($row['body']);
		censorText($form_message);
		censorText($form_subject);

		// Check the boxes that should be checked.
		$context['use_smileys'] = !empty($row['smileys_enabled']);
		$context['icon'] = $row['icon'];

		// Show an "approve" box if the user can approve it, and the message isn't approved.
		if (!$row['approved'] && !$context['show_approval'])
			$context['show_approval'] = allowedTo('approve_posts');

		// Sort the attachments so they are in the order saved
		$temp = array();
		foreach ($attachment_stuff as $attachment)
		{
			if ($attachment['filesize'] >= 0 && !empty($modSettings['attachmentEnable']))
				$temp[$attachment['id_attach']] = $attachment;
		}
		ksort($temp);

		// Load up 'em attachments!
		foreach ($temp as $attachment)
		{
			$context['current_attachments'][$attachment['id_attach']] = array(
				'name' => $smcFunc['htmlspecialchars']($attachment['filename']),
				'size' => $attachment['filesize'],
				'attachID' => $attachment['id_attach'],
				'approved' => $attachment['attachment_approved'],
				'mime_type' => $attachment['mime_type'],
				'thumb' => $attachment['id_thumb'],
			);
		}

		// Allow moderators to change names....
		if (allowedTo('moderate_forum') && empty($row['id_member']))
		{
			$context['name'] = $smcFunc['htmlspecialchars']($row['poster_name']);
			$context['email'] = $smcFunc['htmlspecialchars']($row['poster_email']);
		}

		// Set the destination.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . (isset($_REQUEST['poll']) ? ';poll' : '');
		$context['submit_label'] = $txt['save'];
	}
	// Posting...
	else
	{
		// By default....
		$context['use_smileys'] = true;
		$context['icon'] = 'xx';

		if ($user_info['is_guest'])
		{
			$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
			$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
		}
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['poll']) ? ';poll' : '');

		$context['submit_label'] = $txt['post'];

		// Posting a quoted reply?
		if (!empty($topic) && !empty($_REQUEST['quote']))
		{
			// Make sure they _can_ quote this post, and if so get it.
			$request = $smcFunc['db_query']('', '
				SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_msg = {int:id_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => (int) $_REQUEST['quote'],
					'is_approved' => 1,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim($context['response_prefix']) != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the message and subject.
			censorText($form_message);
			censorText($form_subject);

			// But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
			if (strpos($form_message, '[html]') !== false)
			{
				$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $form_message, -1, PREG_SPLIT_DELIM_CAPTURE);
				for ($i = 0, $n = count($parts); $i < $n; $i++)
				{
					// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
					if ($i % 4 == 0)
						$parts[$i] = preg_replace_callback('~\[html\](.+?)\[/html\]~is', function($m)
						{
							return '[html]' . preg_replace('~<br\s?/?' . '>~i', '&lt;br /&gt;<br>', "$m[1]") . '[/html]';
						}, $parts[$i]);
				}
				$form_message = implode('', $parts);
			}

			$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $form_message);

			// Remove any nested quotes, if necessary.
			if (!empty($modSettings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty($topic) && empty($_REQUEST['quote']))
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			if (trim($context['response_prefix']) != '' && $form_subject != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the subject.
			censorText($form_subject);

			$form_message = '';
		}
		else
		{
			$form_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
			$form_message = '';
		}
	}

	$context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment', $boards, true) || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments', $boards, true)));

	if ($context['can_post_attachment'])
	{
		// If there are attachments, calculate the total size and how many.
		$context['attachments']['total_size'] = 0;
		$context['attachments']['quantity'] = 0;

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$context['attachments']['quantity'] = count($context['current_attachments']);
			foreach ($context['current_attachments'] as $attachment)
				$context['attachments']['total_size'] += $attachment['size'];
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
					if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
						if (file_exists($attachment['tmp_name']))
							unlink($attachment['tmp_name']);
				}
				$post_errors[] = 'temp_attachments_gone';
				$_SESSION['temp_attachments'] = array();
			}
			// Hmm, coming in fresh and there are files in session.
			elseif ($context['current_action'] != 'post2' || !empty($_POST['from_qr']))
			{
				// Let's be nice and see if they belong here first.
				if ((empty($_REQUEST['msg']) && empty($_SESSION['temp_attachments']['post']['msg']) && $_SESSION['temp_attachments']['post']['board'] == (!empty($board) ? $board : 0)) || (!empty($_REQUEST['msg']) && $_SESSION['temp_attachments']['post']['msg'] == $_REQUEST['msg']))
				{
					// See if any files still exist before showing the warning message and the files attached.
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
					{
						if (strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
							continue;

						if (file_exists($attachment['tmp_name']))
						{
							$post_errors[] = 'temp_attachments_new';
							$context['files_in_session_warning'] = $txt['attached_files_in_session'];
							unset($_SESSION['temp_attachments']['post']['files']);
							break;
						}
					}
				}
				else
				{
					// Since, they don't belong here. Let's inform the user that they exist..
					if (!empty($topic))
						$delete_url = $scripturl . '?action=post' . (!empty($_REQUEST['msg']) ? (';msg=' . $_REQUEST['msg']) : '') . (!empty($_REQUEST['last_msg']) ? (';last_msg=' . $_REQUEST['last_msg']) : '') . ';topic=' . $topic . ';delete_temp';
					else
						$delete_url = $scripturl . '?action=post' . (!empty($board) ? ';board=' . $board : '') . ';delete_temp';

					// Compile a list of the files to show the user.
					$file_list = array();
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
						if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
							$file_list[] = $attachment['name'];

					$_SESSION['temp_attachments']['post']['files'] = $file_list;
					$file_list = '<div class="attachments">' . implode('<br>', $file_list) . '</div>';

					if (!empty($_SESSION['temp_attachments']['post']['msg']))
					{
						// We have a message id, so we can link back to the old topic they were trying to edit..
						$goback_url = $scripturl . '?action=post' . (!empty($_SESSION['temp_attachments']['post']['msg']) ? (';msg=' . $_SESSION['temp_attachments']['post']['msg']) : '') . (!empty($_SESSION['temp_attachments']['post']['last_msg']) ? (';last_msg=' . $_SESSION['temp_attachments']['post']['last_msg']) : '') . ';topic=' . $_SESSION['temp_attachments']['post']['topic'] . ';additionalOptions';

						$post_errors[] = array('temp_attachments_found', array($delete_url, $goback_url, $file_list));
						$context['ignore_temp_attachments'] = true;
					}
					else
					{
						$post_errors[] = array('temp_attachments_lost', array($delete_url, $file_list));
						$context['ignore_temp_attachments'] = true;
					}
				}
			}

			if (!empty($context['we_are_history']))
				$post_errors[] = $context['we_are_history'];

			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if (isset($context['ignore_temp_attachments']) || isset($_SESSION['temp_attachments']['post']['files']))
					break;

				if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
					continue;

				if ($attachID == 'initial_error')
				{
					$txt['error_attach_initial_error'] = $txt['attach_no_upload'] . '<div style="padding: 0 1em;">' . (is_array($attachment) ? vsprintf($txt[$attachment[0]], $attachment[1]) : $txt[$attachment]) . '</div>';
					$post_errors[] = 'attach_initial_error';
					unset($_SESSION['temp_attachments']);
					break;
				}

				// Show any errors which might have occurred.
				if (!empty($attachment['errors']))
				{
					$txt['error_attach_errors'] = empty($txt['error_attach_errors']) ? '<br>' : '';
					$txt['error_attach_errors'] .= vsprintf($txt['attach_warning'], $attachment['name']) . '<div style="padding: 0 1em;">';
					foreach ($attachment['errors'] as $error)
						$txt['error_attach_errors'] .= (is_array($error) ? vsprintf($txt[$error[0]], $error[1]) : $txt[$error]) . '<br >';
					$txt['error_attach_errors'] .= '</div>';
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

				$context['attachments']['quantity']++;
				$context['attachments']['total_size'] += $attachment['size'];
				if (!isset($context['files_in_session_warning']))
					$context['files_in_session_warning'] = $txt['attached_files_in_session'];

				$context['current_attachments'][$attachID] = array(
					'name' => $smcFunc['htmlspecialchars']($attachment['name']),
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

	// Do we need to show the visual verification image?
	$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
	if ($context['require_verification'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If they came from quick reply, and have to enter verification details, give them some notice.
	if (!empty($_REQUEST['from_qr']) && !empty($context['require_verification']))
		$post_errors[] = 'need_qr_verification';

	/*
	 * There are two error types: serious and minor. Serious errors
	 * actually tell the user that a real error has occurred, while minor
	 * errors are like warnings that let them know that something with
	 * their post isn't right.
	 */
	$minor_errors = array('not_approved', 'new_replies', 'old_topic', 'need_qr_verification', 'no_subject', 'topic_locked', 'topic_unlocked', 'topic_stickied', 'topic_unstickied', 'cannot_post_attachment');

	call_integration_hook('integrate_post_errors', array(&$post_errors, &$minor_errors));

	// Any errors occurred?
	if (!empty($post_errors))
	{
		loadLanguage('Errors');
		$context['error_type'] = 'minor';
		foreach ($post_errors as $post_error)
			if (is_array($post_error))
			{
				$post_error_id = $post_error[0];
				$context['post_error'][$post_error_id] = vsprintf($txt['error_' . $post_error_id], $post_error[1]);

				// If it's not a minor error flag it as such.
				if (!in_array($post_error_id, $minor_errors))
					$context['error_type'] = 'serious';
			}
			else
			{
				$context['post_error'][$post_error] = $txt['error_' . $post_error];

				// If it's not a minor error flag it as such.
				if (!in_array($post_error, $minor_errors))
					$context['error_type'] = 'serious';
			}
	}

	// What are you doing? Posting a poll, modifying, previewing, new post, or reply...
	if (isset($_REQUEST['poll']))
		$context['page_title'] = $txt['new_poll'];
	elseif ($context['make_event'])
		$context['page_title'] = $context['event']['id'] == -1 ? $txt['calendar_post_event'] : $txt['calendar_edit'];
	elseif (isset($_REQUEST['msg']))
		$context['page_title'] = $txt['modify_msg'];
	elseif (isset($_REQUEST['subject'], $context['preview_subject']))
		$context['page_title'] = $txt['preview'] . ' - ' . strip_tags($context['preview_subject']);
	elseif (empty($topic))
		$context['page_title'] = $txt['start_new_topic'];
	else
		$context['page_title'] = $txt['post_reply'];

	// Build the link tree.
	if (empty($topic))
		$context['linktree'][] = array(
			'name' => '<em>' . $txt['start_new_topic'] . '</em>'
		);
	else
		$context['linktree'][] = array(
			'url' => $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'],
			'name' => $form_subject,
			'extra_before' => '<span><strong class="nav">' . $context['page_title'] . ' (</strong></span>',
			'extra_after' => '<span><strong class="nav">)</strong></span>'
		);

	$context['subject'] = addcslashes($form_subject, '"');
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

	// Are post drafts enabled?
	$context['drafts_save'] = !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft');
	$context['drafts_autosave'] = !empty($context['drafts_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('post_autosave_draft');

	// Build a list of drafts that they can load in to the editor
	if (!empty($context['drafts_save']))
	{
		require_once($sourcedir . '/Drafts.php');
		ShowDrafts($user_info['id'], $topic);
	}

	// Needed for the editor and message icons.
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => $context['message'],
		'labels' => array(
			'post_button' => $context['submit_label'],
		),
		// add height and width for the editor
		'height' => '275px',
		'width' => '100%',
		// We do XML preview here.
		'preview_type' => 2,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	// Store the ID.
	$context['post_box_name'] = $editorOptions['id'];

	$context['attached'] = '';
	$context['make_poll'] = isset($_REQUEST['poll']);

	// Message icons - customized icons are off?
	$context['icons'] = getMessageIcons(!empty($board) ? $board : 0);

	if (!empty($context['icons']))
		$context['icons'][count($context['icons']) - 1]['is_last'] = true;

	// Are we starting a poll? if set the poll icon as selected if its available
	if (isset($_REQUEST['poll']))
	{
		foreach ($context['icons'] as $icons)
		{
			if (isset($icons['value']) && $icons['value'] == 'poll')
			{
				// if found we are done
				$context['icon'] = 'poll';
				break;
			}
		}
	}

	$context['icon_url'] = '';
	for ($i = 0, $n = count($context['icons']); $i < $n; $i++)
	{
		$context['icons'][$i]['selected'] = $context['icon'] == $context['icons'][$i]['value'];
		if ($context['icons'][$i]['selected'])
			$context['icon_url'] = $context['icons'][$i]['url'];
	}
	if (empty($context['icon_url']))
	{
		$context['icon_url'] = $settings[file_exists($settings['theme_dir'] . '/images/post/' . $context['icon'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . $context['icon'] . '.png';
		array_unshift($context['icons'], array(
			'value' => $context['icon'],
			'name' => $txt['current_icon'],
			'url' => $context['icon_url'],
			'is_last' => empty($context['icons']),
			'selected' => true,
		));
	}

	if (!empty($topic) && !empty($modSettings['topicSummaryPosts']))
		getTopic();

	// If the user can post attachments prepare the warning labels.
	if ($context['can_post_attachment'])
	{
		// If they've unchecked an attachment, they may still want to attach that many more files, but don't allow more than num_allowed_attachments.
		$context['num_allowed_attachments'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : min($modSettings['attachmentNumPerPostLimit'] - count($context['current_attachments']), $modSettings['attachmentNumPerPostLimit']);
		$context['can_post_attachment_unapproved'] = allowedTo('post_attachment');
		$context['attachment_restrictions'] = array();
		$context['allowed_extensions'] = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
		$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
		foreach ($attachmentRestrictionTypes as $type)
			if (!empty($modSettings[$type]))
			{
				// Show the max number of attachments if not 0.
				if ($type == 'attachmentNumPerPostLimit')
					$context['attachment_restrictions'][] = sprintf($txt['attach_remaining'], $modSettings['attachmentNumPerPostLimit'] - $context['attachments']['quantity']);
			}
	}

	$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
	$context['show_additional_options'] = !empty($_POST['additional_options']) || isset($_SESSION['temp_attachments']['post']) || isset($_GET['additionalOptions']);

	$context['is_new_topic'] = empty($topic);
	$context['is_new_post'] = !isset($_REQUEST['msg']);
	$context['is_first_post'] = $context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $id_first_msg);

	// WYSIWYG only works if BBC is enabled
	$modSettings['disable_wysiwyg'] = !empty($modSettings['disable_wysiwyg']) || empty($modSettings['enableBBC']);

	// Register this form in the session variables.
	checkSubmitOnce('register');

	// Mentions
	if (!empty($modSettings['enable_mentions']) && allowedTo('mention'))
	{
		loadJavaScriptFile('jquery.caret.min.js', array('defer' => true), 'smf_caret');
		loadJavaScriptFile('jquery.atwho.min.js', array('defer' => true), 'smf_atwho');
		loadJavaScriptFile('mentions.js', array('defer' => true, 'minimize' => true), 'smf_mentions');
	}

	// quotedText.js
	loadJavaScriptFile('quotedText.js', array('defer' => true, 'minimize' => true), 'smf_quotedText');

	// Mock files to show already attached files.
	addInlineJavaScript('
	var current_attachments = [];');

	if (!empty($context['current_attachments']))
	{
		foreach ($context['current_attachments'] as $key => $mock)
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
	if ($context['can_post_attachment'])
	{
		$acceptedFiles = implode(',', array_map(function($val) use ($smcFunc)
		{
			return '.' . $smcFunc['htmltrim']($val);
		}, explode(',', $context['allowed_extensions'])));

		loadJavaScriptFile('dropzone.min.js', array('defer' => true), 'smf_dropzone');
		loadJavaScriptFile('smf_fileUpload.js', array('defer' => true, 'minimize' => true), 'smf_fileUpload');
		addInlineJavaScript('
	$(function() {
		smf_fileUpload({
			dictDefaultMessage : ' . JavaScriptEscape($txt['attach_drop_zone']) . ',
			dictFallbackMessage : ' . JavaScriptEscape($txt['attach_drop_zone_no']) . ',
			dictCancelUpload : ' . JavaScriptEscape($txt['modify_cancel']) . ',
			genericError: ' . JavaScriptEscape($txt['attach_php_error']) . ',
			text_attachLeft: ' . JavaScriptEscape($txt['attachments_left']) . ',
			text_deleteAttach: ' . JavaScriptEscape($txt['attached_file_delete']) . ',
			text_attachDeleted: ' . JavaScriptEscape($txt['attached_file_deleted']) . ',
			text_insertBBC: ' . JavaScriptEscape($txt['attached_insert_bbc']) . ',
			text_attachUploaded: ' . JavaScriptEscape($txt['attached_file_uploaded']) . ',
			text_attach_unlimited: ' . JavaScriptEscape($txt['attach_drop_unlimited']) . ',
			text_totalMaxSize: ' . JavaScriptEscape($txt['attach_max_total_file_size_current']) . ',
			text_max_size_progress: ' . JavaScriptEscape($txt['attach_max_size_progress']) . ',
			dictMaxFilesExceeded: ' . JavaScriptEscape($txt['more_attachments_error']) . ',
			dictInvalidFileType: ' . JavaScriptEscape(sprintf($txt['cant_upload_type'], $context['allowed_extensions'])) . ',
			dictFileTooBig: ' . JavaScriptEscape(sprintf($txt['file_too_big'], comma_format($modSettings['attachmentSizeLimit'], 0))) . ',
			acceptedFiles: ' . JavaScriptEscape($acceptedFiles) . ',
			thumbnailWidth: ' . (!empty($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 'null') . ',
			thumbnailHeight: ' . (!empty($modSettings['attachmentThumbHeight']) ? $modSettings['attachmentThumbHeight'] : 'null') . ',
			limitMultiFileUploadSize:' . round(max($modSettings['attachmentPostLimit'] - ($context['attachments']['total_size'] / 1024), 0)) * 1024 . ',
			maxFileAmount: ' . (!empty($context['num_allowed_attachments']) ? $context['num_allowed_attachments'] : 'null') . ',
			maxTotalSize: ' . (!empty($modSettings['attachmentPostLimit']) ? $modSettings['attachmentPostLimit'] : '0') . ',
			maxFileSize: ' . (!empty($modSettings['attachmentSizeLimit']) ? $modSettings['attachmentSizeLimit'] : '0') . ',
		});
	});', true);
	}

	// Knowing the current board ID might be handy.
	addInlineJavaScript('
	var current_board = ' . (empty($context['current_board']) ? 'null' : $context['current_board']) . ';', false);

	// Now let's set up the fields for the posting form header...
	$context['posting_fields'] = array();

	// Guests must supply their name and email.
	if (isset($context['name']) && isset($context['email']))
	{
		$context['posting_fields']['guestname'] = array(
			'label' => array(
				'text' => $txt['name'],
				'class' => isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '',
			),
			'input' => array(
				'type' => 'text',
				'attributes' => array(
					'size' => 25,
					'value' => $context['name'],
					'required' => true,
				),
			),
		);

		if (empty($modSettings['guest_post_no_email']))
		{
			$context['posting_fields']['email'] = array(
				'label' => array(
					'text' => $txt['email'],
					'class' => isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '',
				),
				'input' => array(
					'type' => 'email',
					'attributes' => array(
						'size' => 25,
						'value' => $context['email'],
						'required' => true,
					),
				),
			);
		}
	}

	// Gotta post it somewhere.
	if (empty($board) && !$context['make_event'])
	{
		$context['posting_fields']['board'] = array(
			'label' => array(
				'text' => $txt['calendar_post_in'],
			),
			'input' => array(
				'type' => 'select',
				'options' => array(),
			),
		);
		foreach ($board_list as $category)
		{
			$context['posting_fields']['board']['input']['options'][$category['name']] = array('options' => array());

			foreach ($category['boards'] as $brd)
				$context['posting_fields']['board']['input']['options'][$category['name']]['options'][$brd['name']]['attributes'] = array(
					'value' => $brd['id'],
					'selected' => (bool) $brd['selected'],
					'label' => ($brd['child_level'] > 0 ? str_repeat('==', $brd['child_level'] - 1) . '=&gt;' : '') . ' ' . $brd['name'],
				);
		}
	}

	// Gotta have a subject.
	$context['posting_fields']['subject'] = array(
		'label' => array(
			'text' => $txt['subject'],
			'class' => isset($context['post_error']['no_subject']) ? 'error' : '',
		),
		'input' => array(
			'type' => 'text',
			'attributes' => array(
				'size' => 80,
				'maxlength' => !empty($topic) ? 84 : 80,
				'value' => $context['subject'],
				'required' => true,
			),
		),
	);

	// Icons are fun.
	$context['posting_fields']['icon'] = array(
		'label' => array(
			'text' => $txt['message_icon'],
		),
		'input' => array(
			'type' => 'select',
			'attributes' => array(
				'id' => 'icon',
				'onchange' => 'showimage();',
			),
			'options' => array(),
			'after' => ' <img id="icons" src="' . $context['icon_url'] . '">',
		),
	);
	foreach ($context['icons'] as $icon)
	{
		$context['posting_fields']['icon']['input']['options'][$icon['name']]['attributes'] = array(
			'value' => $icon['value'],
			'selected' => $icon['value'] == $context['icon'],
		);
	}

	// Finally, load the template.
	if (!isset($_REQUEST['xml']))
		loadTemplate('Post');

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
	global $board, $topic, $txt, $modSettings, $sourcedir, $context;
	global $user_info, $board_info, $smcFunc, $settings;

	// Sneaking off, are we?
	if (empty($_POST) && empty($topic))
	{
		if (empty($_SERVER['CONTENT_LENGTH']))
			redirectexit('action=post;board=' . $board . '.0');
		else
			fatal_lang_error('post_upload_error', false);
	}
	elseif (empty($_POST) && !empty($topic))
		redirectexit('action=post;topic=' . $topic . '.0');

	// No need!
	$context['robot_no_index'] = true;

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// No errors as yet.
	$post_errors = array();

	// If the session has timed out, let the user re-submit their form.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Wrong verification code?
	if (!$user_info['is_admin'] && !$user_info['is_mod'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1)))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);
		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	require_once($sourcedir . '/Subs-Post.php');
	loadLanguage('Post');

	call_integration_hook('integrate_post2_start');

	// Drafts enabled and needed?
	if (!empty($modSettings['drafts_post_enabled']) && (isset($_POST['save_draft']) || isset($_POST['id_draft'])))
		require_once($sourcedir . '/Drafts.php');

	// First check to see if they are trying to delete any current attachments.
	if (isset($_POST['attach_del']))
	{
		$keep_temp = array();
		$keep_ids = array();
		foreach ($_POST['attach_del'] as $dummy)
			if (strpos($dummy, 'post_tmp_' . $user_info['id']) !== false)
				$keep_temp[] = $dummy;
			else
				$keep_ids[] = (int) $dummy;

		if (isset($_SESSION['temp_attachments']))
			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if ((isset($_SESSION['temp_attachments']['post']['files'], $attachment['name']) && in_array($attachment['name'], $_SESSION['temp_attachments']['post']['files'])) || in_array($attachID, $keep_temp) || strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
					continue;

				unset($_SESSION['temp_attachments'][$attachID]);
				unlink($attachment['tmp_name']);
			}

		if (!empty($_REQUEST['msg']))
		{
			require_once($sourcedir . '/ManageAttachments.php');
			$attachmentQuery = array(
				'attachment_type' => 0,
				'id_msg' => (int) $_REQUEST['msg'],
				'not_id_attach' => $keep_ids,
			);
			removeAttachments($attachmentQuery);
		}
	}

	// Then try to upload any attachments.
	$context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
	if ($context['can_post_attachment'] && empty($_POST['from_qr']))
	{
		require_once($sourcedir . '/Subs-Attachments.php');
		processAttachments();
	}

	// They've already uploaded some attachments, but they don't have permission to post them
	// This can sometimes happen when they came from ?action=calendar;sa=post
	if (!$context['can_post_attachment'] && !empty($_SESSION['already_attached']))
	{
		require_once($sourcedir . '/ManageAttachments.php');

		foreach ($_SESSION['already_attached'] as $attachID => $attachment)
			removeAttachments(array('id_attach' => $attachID));

		unset($_SESSION['already_attached']);

		$post_errors[] = array('cannot_post_attachment', array($board_info['name']));
	}

	// If this isn't a new topic load the topic info that we need.
	if (!empty($topic))
	{
		$request = $smcFunc['db_query']('', '
			SELECT locked, is_sticky, id_poll, approved, id_first_msg, id_last_msg, id_member_started, id_board
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		$topic_info = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Though the topic should be there, it might have vanished.
		if (!is_array($topic_info))
			fatal_lang_error('topic_doesnt_exist', 404);

		// Did this topic suddenly move? Just checking...
		if ($topic_info['id_board'] != $board)
			fatal_lang_error('not_a_topic');

		// Do the permissions and approval stuff...
		$becomesApproved = true;
		$topicAndMessageBothUnapproved = false;

		// If the topic is unapproved the message automatically becomes unapproved too.
		if (empty($topic_info['approved']))
		{
			$becomesApproved = false;

			// camelCase fan much? :P
			$topicAndMessageBothUnapproved = true;

			// Set a nice session var...
			$_SESSION['becomesUnapproved'] = true;
		}
	}

	// Replying to a topic?
	if (!empty($topic) && !isset($_REQUEST['msg']))
	{
		// Don't allow a post if it's locked.
		if ($topic_info['locked'] != 0 && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (isset($_REQUEST['poll']) && $topic_info['id_poll'] > 0)
			unset($_REQUEST['poll']);

		elseif ($topic_info['id_member_started'] != $user_info['id'])
		{
			if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
				$becomesApproved = false;

			else
				isAllowedTo('post_reply_any');
		}
		elseif (!allowedTo('post_reply_any'))
		{
			if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
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
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
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
		if (!empty($modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		// If the number of replies has changed, if the setting is enabled, go back to Post() - which handles the error.
		if (isset($_POST['last_msg']) && $topic_info['id_last_msg'] > $_POST['last_msg'])
		{
			$_REQUEST['preview'] = true;
			return Post();
		}

		$posterIsGuest = $user_info['is_guest'];
	}
	// Posting a new topic.
	elseif (empty($topic))
	{
		// Now don't be silly, new topics will get their own id_msg soon enough.
		unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

		// Do like, the permissions, for safety and stuff...
		$becomesApproved = true;
		if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
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
		if (!empty($modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		$posterIsGuest = $user_info['is_guest'];
	}
	// Modifying an existing message?
	elseif (isset($_REQUEST['msg']) && !empty($topic))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		$request = $smcFunc['db_query']('', '
			SELECT id_member, poster_name, poster_email, poster_time, approved
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}
			LIMIT 1',
			array(
				'id_msg' => $_REQUEST['msg'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('cant_find_messages', false);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($topic_info['locked']) && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		if (isset($_POST['lock']))
		{
			// Nothing changes to the lock status.
			if ((empty($_POST['lock']) && empty($topic_info['locked'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
				unset($_POST['lock']);
			// You're simply not allowed to (un)lock this.
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
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

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			isAllowedTo('modify_replies');

			// If you're modifying a reply, I say it better be logged...
			$moderationAction = true;
		}
		else
		{
			isAllowedTo('modify_any');

			// Log it, assuming you're not modifying your own post.
			if ($row['id_member'] != $user_info['id'])
				$moderationAction = true;
		}

		// If drafts are enabled, then lets send this off to save
		if (!empty($modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))
		{
			SaveDraft($post_errors);
			return Post();
		}

		$posterIsGuest = empty($row['id_member']);

		// Can they approve it?
		$can_approve = allowedTo('approve_posts');
		$approve_checked = (!empty($REQUEST['approve']) ? 1 : 0);
		$becomesApproved = $modSettings['postmod_active'] ? ($can_approve && !$row['approved'] ? $approve_checked : $row['approved']) : 1;
		$approve_has_changed = $row['approved'] != $becomesApproved;

		if (!allowedTo('moderate_forum') || !$posterIsGuest)
		{
			$_POST['guestname'] = $row['poster_name'];
			$_POST['email'] = $row['poster_email'];
		}

		// Update search api
		require_once($sourcedir . '/Search.php');
		$searchAPI = findSearchAPI();
		if ($searchAPI->supportsMethod('postRemoved'))
			$searchAPI->postRemoved($_REQUEST['msg']);

	}

	// In case we have approval permissions and want to override.
	if (allowedTo('approve_posts') && $modSettings['postmod_active'])
	{
		// If 'approve' wasn't specified, assume true for these users
		$becomesApproved = !isset($_REQUEST['approve']) || !empty($_REQUEST['approve']) ? 1 : 0;
		$approve_has_changed = isset($row['approved']) ? $row['approved'] != $becomesApproved : false;
	}

	// If the poster is a guest evaluate the legality of name and email.
	if ($posterIsGuest)
	{
		$_POST['guestname'] = !isset($_POST['guestname']) ? '' : trim($_POST['guestname']);
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

		if ($_POST['guestname'] == '' || $_POST['guestname'] == '_')
			$post_errors[] = 'no_name';
		if ($smcFunc['strlen']($_POST['guestname']) > 25)
			$post_errors[] = 'long_name';

		if (empty($modSettings['guest_post_no_email']))
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
			isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));
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
	if (!isset($_POST['subject']) || $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['subject'])) === '')
		$post_errors[] = 'no_subject';
	if (!isset($_POST['message']) || $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['message']), ENT_QUOTES) === '')
		$post_errors[] = 'no_message';
	elseif (!empty($modSettings['max_messageLength']) && $smcFunc['strlen']($_POST['message']) > $modSettings['max_messageLength'])
		$post_errors[] = array('long_message', array($modSettings['max_messageLength']));
	else
	{
		// Prepare the message a bit for some additional testing.
		$_POST['message'] = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);

		// Preparse code. (Zef)
		if ($user_info['is_guest'])
			$user_info['name'] = $_POST['guestname'];
		preparsecode($_POST['message']);

		// Let's see if there's still some content left without the tags.
		if ($smcFunc['htmltrim'](strip_tags(parse_bbc($_POST['message'], false), implode('', $context['allowed_html_tags']))) === '' && (!allowedTo('bbc_html') || strpos($_POST['message'], '[html]') === false))
			$post_errors[] = 'no_message';

	}
	if (isset($_POST['calendar']) && !isset($_REQUEST['deleteevent']) && $smcFunc['htmltrim']($_POST['evtitle']) === '')
		$post_errors[] = 'no_event';
	// You are not!
	if (isset($_POST['message']) && strtolower($_POST['message']) == 'i am the administrator.' && !$user_info['is_admin'])
		fatal_error('Knave! Masquerader! Charlatan!', false);

	// Validate the poll...
	if (isset($_REQUEST['poll']) && $modSettings['pollMode'] == '1')
	{
		if (!empty($topic) && !isset($_REQUEST['msg']))
			fatal_lang_error('no_access', false);

		// This is a new topic... so it's a new poll.
		if (empty($topic))
			isAllowedTo('poll_post');
		// Can you add to your own topics?
		elseif ($user_info['id'] == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
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
		require_once($sourcedir . '/Subs-Members.php');
		if (isReservedName($_POST['guestname'], 0, true, false) && (!isset($row['poster_name']) || $_POST['guestname'] != $row['poster_name']))
			$post_errors[] = 'bad_name';
	}
	// If the user isn't a guest, get his or her name and email.
	elseif (!isset($_REQUEST['msg']))
	{
		$_POST['guestname'] = $user_info['username'];
		$_POST['email'] = $user_info['email'];
	}

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
			loadLanguage('Errors');
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
	$_POST['subject'] = strtr($smcFunc['htmlspecialchars']($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$_POST['guestname'] = $smcFunc['htmlspecialchars']($_POST['guestname']);
	$_POST['email'] = $smcFunc['htmlspecialchars']($_POST['email']);
	$_POST['modify_reason'] = empty($_POST['modify_reason']) ? '' : strtr($smcFunc['htmlspecialchars']($_POST['modify_reason']), array("\r" => '', "\n" => '', "\t" => ''));

	// At this point, we want to make sure the subject isn't too long.
	if ($smcFunc['strlen']($_POST['subject']) > 100)
		$_POST['subject'] = $smcFunc['substr']($_POST['subject'], 0, 100);

	// Same with the "why did you edit this" text.
	if ($smcFunc['strlen']($_POST['modify_reason']) > 100)
		$_POST['modify_reason'] = $smcFunc['substr']($_POST['modify_reason'], 0, 100);

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
			require_once($sourcedir . '/Subs-Members.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);
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
		$_POST['question'] = $smcFunc['htmlspecialchars']($_POST['question']);
		$_POST['question'] = $smcFunc['truncate']($_POST['question'], 255);
		$_POST['question'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $_POST['question']);
		$_POST['options'] = htmlspecialchars__recursive($_POST['options']);
	}

	// ...or attach a new file...
	if ($context['can_post_attachment'] && !empty($_SESSION['temp_attachments']) && empty($_POST['from_qr']))
	{
		$attachIDs = array();
		$attach_errors = array();
		if (!empty($context['we_are_history']))
			$attach_errors[] = '<dd>' . $txt['error_temp_attachments_flushed'] . '<br><br></dd>';

		foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
		{
			if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
				continue;

			// If there was an initial error just show that message.
			if ($attachID == 'initial_error')
			{
				$attach_errors[] = '<dt>' . $txt['attach_no_upload'] . '</dt>';
				$attach_errors[] = '<dd>' . (is_array($attachment) ? vsprintf($txt[$attachment[0]], $attachment[1]) : $txt[$attachment]) . '</dd>';

				unset($_SESSION['temp_attachments']);
				break;
			}

			$attachmentOptions = array(
				'post' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
				'poster' => $user_info['id'],
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => isset($attachment['size']) ? $attachment['size'] : 0,
				'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
				'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : $modSettings['currentAttachmentUploadDir'],
				'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
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
				$attach_errors[] = '<dt>' . vsprintf($txt['attach_warning'], $attachment['name']) . '</dt>';
				$log_these = array('attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file');
				foreach ($attachmentOptions['errors'] as $error)
				{
					if (!is_array($error))
					{
						$attach_errors[] = '<dd>' . $txt[$error] . '</dd>';
						if (in_array($error, $log_these))
							log_error($attachment['name'] . ': ' . $txt[$error], 'critical');
					}
					else
						$attach_errors[] = '<dd>' . vsprintf($txt[$error[0]], $error[1]) . '</dd>';
				}
				if (file_exists($attachment['tmp_name']))
					unlink($attachment['tmp_name']);
			}
		}
		unset($_SESSION['temp_attachments']);
	}

	// Make the poll...
	if (isset($_REQUEST['poll']))
	{
		// Create the poll.
		$id_poll = $smcFunc['db_insert']('',
			'{db_prefix}polls',
			array(
				'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
				'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
			),
			array(
				$_POST['question'], $_POST['poll_hide'], $_POST['poll_max_votes'], (empty($_POST['poll_expire']) ? 0 : time() + $_POST['poll_expire'] * 3600 * 24), $user_info['id'],
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

		$smcFunc['db_insert']('insert',
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
	$newTopic = empty($_REQUEST['msg']) && empty($topic);

	// Check the icon.
	if (!isset($_POST['icon']))
		$_POST['icon'] = 'xx';

	else
	{
		$_POST['icon'] = $smcFunc['htmlspecialchars']($_POST['icon']);

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
		'id' => empty($topic) ? 0 : $topic,
		'board' => $board,
		'poll' => isset($_REQUEST['poll']) ? $id_poll : null,
		'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
		'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
	);
	$posterOptions = array(
		'id' => $user_info['id'],
		'name' => $_POST['guestname'],
		'email' => $_POST['email'],
		'update_post_count' => !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'],
	);

	// This is an already existing message. Edit it.
	if (!empty($_REQUEST['msg']))
	{
		// Have admins allowed people to hide their screwups?
		if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $row['id_member'])
		{
			$msgOptions['modify_time'] = time();
			$msgOptions['modify_name'] = $user_info['name'];
			$msgOptions['modify_reason'] = $_POST['modify_reason'];
		}

		// This will save some time...
		if (empty($approve_has_changed))
			unset($msgOptions['approved']);

		modifyPost($msgOptions, $topicOptions, $posterOptions);
	}
	// This is a new topic or an already existing one. Save it.
	else
	{
		createPost($msgOptions, $topicOptions, $posterOptions);

		if (isset($topicOptions['id']))
			$topic = $topicOptions['id'];
	}

	// Are there attachments already uploaded and waiting to be assigned?
	if (!empty($msgOptions['id']) && !empty($_SESSION['already_attached']))
	{
		require_once($sourcedir . '/Subs-Attachments.php');
		assignAttachments($_SESSION['already_attached'], $msgOptions['id']);
		unset($_SESSION['already_attached']);
	}

	// If we had a draft for this, its time to remove it since it was just posted
	if (!empty($modSettings['drafts_post_enabled']) && !empty($_POST['id_draft']))
		DeleteDraft($_POST['id_draft']);

	// Editing or posting an event?
	if (isset($_POST['calendar']) && (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1))
	{
		require_once($sourcedir . '/Subs-Calendar.php');

		// Make sure they can link an event to this post.
		canLinkEvent();

		// Insert the event.
		$eventOptions = array(
			'board' => $board,
			'topic' => $topic,
			'title' => $_POST['evtitle'],
			'location' => $_POST['event_location'],
			'member' => $user_info['id'],
		);
		insertEvent($eventOptions);
	}
	elseif (isset($_POST['calendar']))
	{
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

		// Validate the post...
		require_once($sourcedir . '/Subs-Calendar.php');
		validateEventPost();

		// If you're not allowed to edit any events, you have to be the poster.
		if (!allowedTo('calendar_edit_any'))
		{
			// Get the event's poster.
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}',
				array(
					'id_event' => $_REQUEST['eventid'],
				)
			);
			$row2 = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
			isAllowedTo('calendar_edit_' . ($row2['id_member'] == $user_info['id'] ? 'own' : 'any'));
		}

		// Delete it?
		if (isset($_REQUEST['deleteevent']))
			$smcFunc['db_query']('', '
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
				'board' => $board,
				'topic' => $topic,
				'title' => $_POST['evtitle'],
				'location' => $_POST['event_location'],
				'member' => $user_info['id'],
			);
			modifyEvent($_REQUEST['eventid'], $eventOptions);
		}
	}

	// Marking read should be done even for editing messages....
	// Mark all the parents read.  (since you just posted and they will be unread.)
	if (!$user_info['is_guest'] && !empty($board_info['parent_boards']))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_boards
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_board IN ({array_int:board_list})',
			array(
				'current_member' => $user_info['id'],
				'board_list' => array_keys($board_info['parent_boards']),
				'id_msg' => $modSettings['maxMsgID'],
			)
		);
	}

	// Turn notification on or off.  (note this just blows smoke if it's already on or off.)
	if (!empty($_POST['notify']) && !$context['user']['is_guest'])
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
			array($user_info['id'], $topic, 0),
			array('id_member', 'id_topic', 'id_board')
		);
	}
	elseif (!$newTopic)
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);

	// Log an act of moderation - modifying.
	if (!empty($moderationAction))
		logAction('modify', array('topic' => $topic, 'message' => (int) $_REQUEST['msg'], 'member' => $row['id_member'], 'board' => $board));

	if (isset($_POST['lock']) && $_POST['lock'] != 2)
		logAction(empty($_POST['lock']) ? 'unlock' : 'lock', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	if (isset($_POST['sticky']))
		logAction(empty($_POST['sticky']) ? 'unsticky' : 'sticky', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	// Returning to the topic?
	if (!empty($_REQUEST['goback']))
	{
		// Mark the board as read.... because it might get confusing otherwise.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_boards
			SET id_msg = {int:maxMsgID}
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'maxMsgID' => $modSettings['maxMsgID'],
			)
		);
	}

	if ($board_info['num_topics'] == 0)
		cache_put_data('board-' . $board, null, 120);

	call_integration_hook('integrate_post2_end');

	if (!empty($_POST['announce_topic']) && allowedTo('announce_topic'))
		redirectexit('action=announce;sa=selectgroup;topic=' . $topic . (!empty($_POST['move']) && allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));

	if (!empty($_POST['move']) && allowedTo('move_any'))
		redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));

	// Return to post if the mod is on.
	if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback']))
		redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'], isBrowser('ie'));
	elseif (!empty($_REQUEST['goback']))
		redirectexit('topic=' . $topic . '.new#new', isBrowser('ie'));
	// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
	else
		redirectexit('board=' . $board . '.0');
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
	global $context, $txt, $topic;

	isAllowedTo('announce_topic');

	validateSession();

	if (empty($topic))
		fatal_lang_error('topic_gone', false);

	loadLanguage('Post');
	loadTemplate('Post');

	$subActions = array(
		'selectgroup' => 'AnnouncementSelectMembergroup',
		'send' => 'AnnouncementSend',
	);

	$context['page_title'] = $txt['announce_topic'];

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
	global $txt, $context, $topic, $board_info, $smcFunc;

	$groups = array_merge($board_info['groups'], array(1));
	foreach ($groups as $id => $group)
		$groups[$id] = (int) $group;

	$context['groups'] = array();
	if (in_array(0, $groups))
	{
		$context['groups'][0] = array(
			'id' => 0,
			'name' => $txt['announce_regular_members'],
			'member_count' => 'n/a',
		);
	}

	// Get all membergroups that have access to the board the announcement was made on.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => '',
			'member_count' => $row['num_members'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Now get the membergroup names.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['groups'][$row['id_group']]['name'] = $row['group_name'];
	$smcFunc['db_free_result']($request);

	// Get the subject of the topic we're about to announce.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
		)
	);
	list ($context['topic_subject']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	censorText($context['announce_topic']['subject']);

	$context['move'] = isset($_REQUEST['move']) ? 1 : 0;
	$context['go_back'] = isset($_REQUEST['goback']) ? 1 : 0;

	$context['sub_template'] = 'announce';
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
	global $topic, $board, $board_info, $context, $modSettings;
	global $language, $scripturl, $sourcedir, $smcFunc;

	checkSession();

	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$groups = array_merge($board_info['groups'], array(1));

	if (isset($_POST['membergroups']))
		$_POST['who'] = explode(',', $_POST['membergroups']);

	// Check whether at least one membergroup was selected.
	if (empty($_POST['who']))
		fatal_lang_error('no_membergroup_selected');

	// Make sure all membergroups are integers and can access the board of the announcement.
	foreach ($_POST['who'] as $id => $mg)
		$_POST['who'][$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

	// Get the topic subject and censor it.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.subject, m.body
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_msg, $context['topic_subject'], $message) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	censorText($context['topic_subject']);
	censorText($message);

	$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($message, false, $id_msg), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	// We need this in order to be able send emails.
	require_once($sourcedir . '/Subs-Post.php');

	// Select the email addresses for this batch.
	$request = $smcFunc['db_query']('', '
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
			'start' => $context['start'],
			'additional_group_list' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $_POST['who']),
			// @todo Might need an interface?
			'chunk_size' => 500,
		)
	);

	// All members have received a mail. Go to the next screen.
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		logAction('announce_topic', array('topic' => $topic), 'user');
		if (!empty($_REQUEST['move']) && allowedTo('move_any'))
			redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
		elseif (!empty($_REQUEST['goback']))
			redirectexit('topic=' . $topic . '.new;boardseen#new', isBrowser('ie'));
		else
			redirectexit('board=' . $board . '.0');
	}

	$announcements = array();
	// Loop through all members that'll receive an announcement in this batch.
	$rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$rows[$row['id_member']] = $row;
	}
	$smcFunc['db_free_result']($request);

	// Load their alert preferences
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs(array_keys($rows), 'announcements', true);

	foreach ($rows as $row)
	{
		// Force them to have it?
		if (empty($prefs[$row['id_member']]['announcements']))
			continue;

		$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

		// If the language wasn't defined yet, load it and compose a notification message.
		if (!isset($announcements[$cur_language]))
		{
			$replacements = array(
				'TOPICSUBJECT' => $context['topic_subject'],
				'MESSAGE' => $message,
				'TOPICLINK' => $scripturl . '?topic=' . $topic . '.0',
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
		$context['start'] = $row['id_member'];
	}

	// For each language send a different mail - low priority...
	foreach ($announcements as $lang => $mail)
		sendmail($mail['recipients'], $mail['subject'], $mail['body'], null, 'ann-' . $lang, $mail['is_html'], 5);

	$context['percentage_done'] = round(100 * $context['start'] / $modSettings['latestMember'], 1);

	$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
	$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
	$context['membergroups'] = implode(',', $_POST['who']);
	$context['sub_template'] = 'announcement_send';

	// Go back to the correct language for the user ;).
	if (!empty($modSettings['userLanguage']))
		loadLanguage('Post');
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
	global $topic, $modSettings, $context, $smcFunc, $counter, $options;

	if (isset($_REQUEST['xml']))
		$limit = '
		LIMIT ' . (empty($context['new_replies']) ? '0' : $context['new_replies']);
	else
		$limit = empty($modSettings['topicSummaryPosts']) ? '' : '
		LIMIT ' . (int) $modSettings['topicSummaryPosts'];

	// If you're modifying, get only those posts before the current one. (otherwise get all.)
	$request = $smcFunc['db_query']('', '
		SELECT
			COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			m.body, m.smileys_enabled, m.id_msg, m.id_member
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (isset($_REQUEST['msg']) ? '
			AND m.id_msg < {int:id_msg}' : '') . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = {int:approved}') . '
		ORDER BY m.id_msg DESC' . $limit,
		array(
			'current_topic' => $topic,
			'id_msg' => isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0,
			'approved' => 1,
		)
	);
	$context['previous_posts'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor, BBC, ...
		censorText($row['body']);
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// ...and store.
		$context['previous_posts'][] = array(
			'counter' => $counter++,
			'poster' => $row['poster_name'],
			'message' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'is_new' => !empty($context['new_replies']),
			'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($row['id_member'], $context['user']['ignoreusers']),
		);

		if (!empty($context['new_replies']))
			$context['new_replies']--;
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Loads a post an inserts it into the current editing text box.
 * uses the Post language file.
 * uses special (sadly browser dependent) javascript to parse entities for internationalization reasons.
 * accessed with ?action=quotefast.
 */
function QuoteFast()
{
	global $modSettings, $user_info, $context;
	global $sourcedir, $smcFunc;

	loadLanguage('Post');
	if (!isset($_REQUEST['xml']))
		loadTemplate('Post');

	include_once($sourcedir . '/Subs-Post.php');

	$moderate_boards = boardsAllowedTo('moderate_board');

	// Where we going if we need to?
	$context['post_box_name'] = isset($_GET['pb']) ? $_GET['pb'] : '';

	$request = $smcFunc['db_query']('', '
		SELECT COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body, m.id_topic, m.subject,
			m.id_board, m.id_member, m.approved, m.modified_time, m.modified_name, m.modified_reason
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:id_msg}' . (isset($_REQUEST['modify']) || (!empty($moderate_boards) && $moderate_boards[0] == 0) ? '' : '
			AND (t.locked = {int:not_locked}' . (empty($moderate_boards) ? '' : ' OR b.id_board IN ({array_int:moderation_board_list})') . ')') . '
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'moderation_board_list' => $moderate_boards,
			'id_msg' => (int) $_REQUEST['quote'],
			'not_locked' => 0,
		)
	);
	$context['close_window'] = $smcFunc['db_num_rows']($request) == 0;
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$context['sub_template'] = 'quotefast';
	if (!empty($row))
		$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == $user_info['id']) || allowedTo('approve_posts', $row['id_board']);

	if (!empty($can_view_post))
	{
		// Remove special formatting we don't want anymore.
		$row['body'] = un_preparsecode($row['body']);

		// Censor the message!
		censorText($row['body']);

		// Want to modify a single message by double clicking it?
		if (isset($_REQUEST['modify']))
		{
			censorText($row['subject']);

			$context['sub_template'] = 'modifyfast';
			$context['message'] = array(
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
		if (!empty($modSettings['removeNestedQuotes']))
			$row['body'] = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $row['body']);

		$lb = "\n";

		// Add a quote string on the front and end.
		$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . ']' . $lb . $row['body'] . $lb . '[/quote]';
		$context['quote']['text'] = strtr(un_htmlspecialchars($context['quote']['xml']), array('\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>'));
		$context['quote']['xml'] = strtr($context['quote']['xml'], array('&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;'));

		$context['quote']['mozilla'] = strtr($smcFunc['htmlspecialchars']($context['quote']['text']), array('&quot;' => '"'));
	}
	//@todo Needs a nicer interface.
	// In case our message has been removed in the meantime.
	elseif (isset($_REQUEST['modify']))
	{
		$context['sub_template'] = 'modifyfast';
		$context['message'] = array(
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
		$context['quote'] = array(
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
	global $sourcedir, $modSettings, $board, $topic, $txt;
	global $user_info, $context, $smcFunc, $language, $board_info;

	// We have to have a topic!
	if (empty($topic))
		obExit(false);

	checkSession('get');
	require_once($sourcedir . '/Subs-Post.php');

	// Assume the first message if no message ID was given.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.locked, t.num_replies, t.id_member_started, t.id_first_msg,
			m.id_msg, m.id_member, m.poster_time, m.subject, m.smileys_enabled, m.body, m.icon,
			m.modified_time, m.modified_name, m.modified_reason, m.approved,
			m.poster_name, m.poster_email
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {raw:id_msg}
			AND m.id_topic = {int:current_topic}' . (allowedTo('modify_any') || allowedTo('approve_posts') ? '' : (!$modSettings['postmod_active'] ? '
			AND (m.id_member != {int:guest_id} AND m.id_member = {int:current_member})' : '
			AND (m.approved = {int:is_approved} OR (m.id_member != {int:guest_id} AND m.id_member = {int:current_member}))')),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'id_msg' => empty($_REQUEST['msg']) ? 't.id_first_msg' : (int) $_REQUEST['msg'],
			'is_approved' => 1,
			'guest_id' => 0,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board', false);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Change either body or subject requires permissions to modify messages.
	if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon']))
	{
		if (!empty($row['locked']))
			isAllowedTo('moderate_board');

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		// Otherwise, they're locked out; someone who can modify the replies is needed.
		elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		// Only log this action if it wasn't your message.
		$moderationAction = $row['id_member'] != $user_info['id'];
	}

	$post_errors = array();
	if (isset($_POST['subject']) && $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['subject'])) !== '')
	{
		$_POST['subject'] = strtr($smcFunc['htmlspecialchars']($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));

		// Maximum number of characters.
		if ($smcFunc['strlen']($_POST['subject']) > 100)
			$_POST['subject'] = $smcFunc['substr']($_POST['subject'], 0, 100);
	}
	elseif (isset($_POST['subject']))
	{
		$post_errors[] = 'no_subject';
		unset($_POST['subject']);
	}

	if (isset($_POST['message']))
	{
		if ($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['message'])) === '')
		{
			$post_errors[] = 'no_message';
			unset($_POST['message']);
		}
		elseif (!empty($modSettings['max_messageLength']) && $smcFunc['strlen']($_POST['message']) > $modSettings['max_messageLength'])
		{
			$post_errors[] = 'long_message';
			unset($_POST['message']);
		}
		else
		{
			$_POST['message'] = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);

			preparsecode($_POST['message']);

			if ($smcFunc['htmltrim'](strip_tags(parse_bbc($_POST['message'], false), implode('', $context['allowed_html_tags']))) === '')
			{
				$post_errors[] = 'no_message';
				unset($_POST['message']);
			}
		}
	}

	if (isset($_POST['lock']))
	{
		if (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $row['id_member']))
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
		$_POST['modify_reason'] = strtr($smcFunc['htmlspecialchars']($_POST['modify_reason']), array("\r" => '', "\n" => '', "\t" => ''));

		// Maximum number of characters.
		if ($smcFunc['strlen']($_POST['modify_reason']) > 100)
			$_POST['modify_reason'] = $smcFunc['substr']($_POST['modify_reason'], 0, 100);
	}

	if (empty($post_errors))
	{
		$msgOptions = array(
			'id' => $row['id_msg'],
			'subject' => isset($_POST['subject']) ? $_POST['subject'] : null,
			'body' => isset($_POST['message']) ? $_POST['message'] : null,
			'icon' => isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
			'modify_reason' => (isset($_POST['modify_reason']) ? $_POST['modify_reason'] : ''),
		);
		$topicOptions = array(
			'id' => $topic,
			'board' => $board,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
		);
		$posterOptions = array(
			'id' => $user_info['id'],
			'name' => $row['poster_name'],
			'email' => $row['poster_email'],
			'update_post_count' => !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'],
		);

		// Only consider marking as editing if they have edited the subject, message or icon.
		if ((isset($_POST['subject']) && $_POST['subject'] != $row['subject']) || (isset($_POST['message']) && $_POST['message'] != $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] != $row['icon']))
		{
			// And even then only if the time has passed...
			if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $row['id_member'])
			{
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = $user_info['name'];
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
		if (isset($_POST['subject']) && isset($_REQUEST['change_all_subjects']) && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (allowedTo('modify_any') || ($row['id_member_started'] == $user_info['id'] && allowedTo('modify_replies'))))
		{
			// Get the proper (default language) response prefix first.
			if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
			{
				if ($language === $user_info['language'])
					$context['response_prefix'] = $txt['response_prefix'];
				else
				{
					loadLanguage('index', $language, false);
					$context['response_prefix'] = $txt['response_prefix'];
					loadLanguage('index');
				}
				cache_put_data('response_prefix', $context['response_prefix'], 600);
			}

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET subject = {string:subject}
				WHERE id_topic = {int:current_topic}
					AND id_msg != {int:id_first_msg}',
				array(
					'current_topic' => $topic,
					'id_first_msg' => $row['id_first_msg'],
					'subject' => $context['response_prefix'] . $_POST['subject'],
				)
			);
		}

		if (!empty($moderationAction))
			logAction('modify', array('topic' => $topic, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => $board));
	}

	if (isset($_REQUEST['xml']))
	{
		$context['sub_template'] = 'modifydone';
		if (empty($post_errors) && isset($msgOptions['subject']) && isset($msgOptions['body']))
		{
			$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					'reason' => $msgOptions['modify_reason'],
				),
				'subject' => $msgOptions['subject'],
				'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
				'body' => strtr($msgOptions['body'], array(']]>' => ']]]]><![CDATA[>')),
			);

			censorText($context['message']['subject']);
			censorText($context['message']['body']);

			$context['message']['body'] = parse_bbc($context['message']['body'], $row['smileys_enabled'], $row['id_msg']);
		}
		// Topic?
		elseif (empty($post_errors))
		{
			$context['sub_template'] = 'modifytopicdone';
			$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
				),
				'subject' => isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
			);

			censorText($context['message']['subject']);
		}
		else
		{
			$context['message'] = array(
				'id' => $row['id_msg'],
				'errors' => array(),
				'error_in_subject' => in_array('no_subject', $post_errors),
				'error_in_body' => in_array('no_message', $post_errors) || in_array('long_message', $post_errors),
			);

			loadLanguage('Errors');
			foreach ($post_errors as $post_error)
			{
				if ($post_error == 'long_message')
					$context['message']['errors'][] = sprintf($txt['error_' . $post_error], $modSettings['max_messageLength']);
				else
					$context['message']['errors'][] = $txt['error_' . $post_error];
			}
		}
	}
	else
		obExit(false);
}

?>