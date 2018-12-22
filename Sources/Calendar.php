<?php

/**
 * This file has only one real task, showing the calendar.
 * Original module by Aaron O'Neil - aaron@mud-master.com
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Show the calendar.
 * It loads the specified month's events, holidays, and birthdays.
 * It requires the calendar_view permission.
 * It depends on the cal_enabled setting, and many of the other cal_ settings.
 * It uses the calendar_start_day theme option. (Monday/Sunday)
 * It uses the main sub template in the Calendar template.
 * It goes to the month and year passed in 'month' and 'year' by get or post.
 * It is accessed through ?action=calendar.
 *
 * @return void
 */
function CalendarMain()
{
	global $txt, $context, $modSettings, $scripturl, $options, $sourcedir, $user_info, $smcFunc;

	// Permissions, permissions, permissions.
	isAllowedTo('calendar_view');

	// Some global template resources.
	$context['calendar_resources'] = array(
		'min_year' => $modSettings['cal_minyear'],
		'max_year' => $modSettings['cal_maxyear'],
	);

	// Doing something other than calendar viewing?
	$subActions = array(
		'ical' => 'iCalDownload',
		'post' => 'CalendarPost',
	);

	if (isset($_GET['sa']) && isset($subActions[$_GET['sa']]))
		return call_helper($subActions[$_GET['sa']]);

	// You can't do anything if the calendar is off.
	if (empty($modSettings['cal_enabled']))
		fatal_lang_error('calendar_off', false);

	// This is gonna be needed...
	loadTemplate('Calendar');
	loadCSSFile('calendar.css', array('force_current' => false, 'validate' => true, 'rtl' => 'calendar.rtl.css'), 'smf_calendar');

	// Did the specify an individual event ID? If so, let's splice the year/month in to what we would otherwise be doing.
	if (isset($_GET['event']))
	{
		$evid = (int) $_GET['event'];
		if ($evid > 0)
		{
			$request = $smcFunc['db_query']('', '
				SELECT start_date
				FROM {db_prefix}calendar
				WHERE id_event = {int:event_id}',
				array(
					'event_id' => $evid,
				)
			);
			if ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$_REQUEST['start_date'] = $row['start_date'];

				// We might use this later.
				$context['selected_event'] = $evid;
			}
			$smcFunc['db_free_result']($request);
		}
		unset ($_GET['event']);
	}

	// Set the page title to mention the calendar ;).
	$context['page_title'] = $txt['calendar'];

	// Ensure a default view is defined
	if (empty($modSettings['calendar_default_view']))
		$modSettings['calendar_default_view'] = 'viewlist';

	// What view do we want?
	if (isset($_GET['viewweek']))
		$context['calendar_view'] = 'viewweek';
	elseif (isset($_GET['viewmonth']))
		$context['calendar_view'] = 'viewmonth';
	elseif (isset($_GET['viewlist']))
		$context['calendar_view'] = 'viewlist';
	else
		$context['calendar_view'] = $modSettings['calendar_default_view'];

	// Don't let search engines index the non-default calendar pages
	if ($context['calendar_view'] !== $modSettings['calendar_default_view'])
		$context['robot_no_index'] = true;

	// Get the current day of month...
	require_once($sourcedir . '/Subs-Calendar.php');
	$today = getTodayInfo();

	// Need a start date for all views
	if (!empty($_REQUEST['start_date']))
	{
		$start_parsed = date_parse($_REQUEST['start_date']);
		if (empty($start_parsed['error_count']) && empty($start_parsed['warning_count']))
		{
			$_REQUEST['year'] = $start_parsed['year'];
			$_REQUEST['month'] = $start_parsed['month'];
			$_REQUEST['day'] = $start_parsed['day'];
		}
	}
	$year = !empty($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year'];
	$month = !empty($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['month'];
	$day = !empty($_REQUEST['day']) ? (int) $_REQUEST['day'] : (!empty($_REQUEST['month']) ? 1 : $today['day']);

	$start_object = checkdate($month, $day, $year) === true ? date_create(implode('-', array($year, $month, $day))) : date_create(implode('-', array($today['year'], $today['month'], $today['day'])));

	// Need an end date for the list view
	if (!empty($_REQUEST['end_date']))
	{
		$end_parsed = date_parse($_REQUEST['end_date']);
		if (empty($end_parsed['error_count']) && empty($end_parsed['warning_count']))
		{
			$_REQUEST['end_year'] = $end_parsed['year'];
			$_REQUEST['end_month'] = $end_parsed['month'];
			$_REQUEST['end_day'] = $end_parsed['day'];
		}
	}
	$end_year = !empty($_REQUEST['end_year']) ? (int) $_REQUEST['end_year'] : null;
	$end_month = !empty($_REQUEST['end_month']) ? (int) $_REQUEST['end_month'] : null;
	$end_day = !empty($_REQUEST['end_day']) ? (int) $_REQUEST['end_day'] : null;

	$end_object = checkdate($end_month, $end_day, $end_year) === true ? date_create(implode('-', array($end_year, $end_month, $end_day))) : null;

	if (empty($end_object) || $start_object >= $end_object)
	{
		$num_days_shown = empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'];

		$end_object = date_create(date_format($start_object, 'Y-m-d'));

		date_add($end_object, date_interval_create_from_date_string($num_days_shown . ' days'));
	}

	$curPage = array(
		'year' => date_format($start_object, 'Y'),
		'month' => date_format($start_object, 'n'),
		'day' => date_format($start_object, 'j'),
		'start_date' => date_format($start_object, 'Y-m-d'),
		'end_year' => date_format($end_object, 'Y'),
		'end_month' => date_format($end_object, 'n'),
		'end_day' => date_format($end_object, 'j'),
		'end_date' => date_format($end_object, 'Y-m-d'),
	);

	// Make sure the year and month are in valid ranges.
	if ($curPage['month'] < 1 || $curPage['month'] > 12)
		fatal_lang_error('invalid_month', false);
	if ($curPage['year'] < $modSettings['cal_minyear'] || $curPage['year'] > $modSettings['cal_maxyear'])
		fatal_lang_error('invalid_year', false);
	// If we have a day clean that too.
	if ($context['calendar_view'] != 'viewmonth')
	{
		$isValid = checkdate($curPage['month'], $curPage['day'], $curPage['year']);
		if (!$isValid)
			fatal_lang_error('invalid_day', false);
	}

	// Load all the context information needed to show the calendar grid.
	$calendarOptions = array(
		'start_day' => !empty($options['calendar_start_day']) ? $options['calendar_start_day'] : 0,
		'show_birthdays' => in_array($modSettings['cal_showbdays'], array(1, 2)),
		'show_events' => in_array($modSettings['cal_showevents'], array(1, 2)),
		'show_holidays' => in_array($modSettings['cal_showholidays'], array(1, 2)),
		'highlight' => array(
			'events' => isset($modSettings['cal_highlight_events']) ? $modSettings['cal_highlight_events'] : 0,
			'holidays' => isset($modSettings['cal_highlight_holidays']) ? $modSettings['cal_highlight_holidays'] : 0,
			'birthdays' => isset($modSettings['cal_highlight_birthdays']) ? $modSettings['cal_highlight_birthdays'] : 0,
		),
		'show_week_num' => true,
		'short_day_titles' => !empty($modSettings['cal_short_days']),
		'short_month_titles' => !empty($modSettings['cal_short_months']),
		'show_next_prev' => !empty($modSettings['cal_prev_next_links']),
		'show_week_links' => isset($modSettings['cal_week_links']) ? $modSettings['cal_week_links'] : 0,
	);

	// Load up the main view.
	if ($context['calendar_view'] == 'viewlist')
		$context['calendar_grid_main'] = getCalendarList($curPage['start_date'], $curPage['end_date'], $calendarOptions);
	elseif ($context['calendar_view'] == 'viewweek')
		$context['calendar_grid_main'] = getCalendarWeek($curPage['month'], $curPage['year'], $curPage['day'], $calendarOptions);
	else
		$context['calendar_grid_main'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

	// Load up the previous and next months.
	$context['calendar_grid_current'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

	// Only show previous month if it isn't pre-January of the min-year
	if ($context['calendar_grid_current']['previous_calendar']['year'] > $modSettings['cal_minyear'] || $curPage['month'] != 1)
		$context['calendar_grid_prev'] = getCalendarGrid($context['calendar_grid_current']['previous_calendar']['month'], $context['calendar_grid_current']['previous_calendar']['year'], $calendarOptions, true);

	// Only show next month if it isn't post-December of the max-year
	if ($context['calendar_grid_current']['next_calendar']['year'] < $modSettings['cal_maxyear'] || $curPage['month'] != 12)
		$context['calendar_grid_next'] = getCalendarGrid($context['calendar_grid_current']['next_calendar']['month'], $context['calendar_grid_current']['next_calendar']['year'], $calendarOptions);

	// Basic template stuff.
	$context['allow_calendar_event'] = allowedTo('calendar_post');

	// If you don't allow events not linked to posts and you're not an admin, we have more work to do...
	if ($context['allow_calendar_event'] && empty($modSettings['cal_allow_unlinked']) && !$user_info['is_admin'])
	{
		$boards_can_post = boardsAllowedTo('post_new');
		$context['allow_calendar_event'] &= !empty($boards_can_post);
	}

	$context['can_post'] = $context['allow_calendar_event'];
	$context['current_day'] = $curPage['day'];
	$context['current_month'] = $curPage['month'];
	$context['current_year'] = $curPage['year'];
	$context['show_all_birthdays'] = isset($_GET['showbd']);
	$context['blocks_disabled'] = !empty($modSettings['cal_disable_prev_next']) ? 1 : 0;

	// Set the page title to mention the month or week, too
	if ($context['calendar_view'] != 'viewlist')
		$context['page_title'] .= ' - ' . ($context['calendar_view'] == 'viewweek' ? $context['calendar_grid_main']['week_title'] : $txt['months'][$context['current_month']] . ' ' . $context['current_year']);

	// Load up the linktree!
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=calendar',
		'name' => $txt['calendar']
	);
	// Add the current month to the linktree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=calendar;year=' . $context['current_year'] . ';month=' . $context['current_month'],
		'name' => $txt['months'][$context['current_month']] . ' ' . $context['current_year']
	);
	// If applicable, add the current week to the linktree.
	if ($context['calendar_view'] == 'viewweek')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=calendar;viewweek;year=' . $context['current_year'] . ';month=' . $context['current_month'] . ';day=' . $context['current_day'],
			'name' => $context['calendar_grid_main']['week_title'],
		);

	// Build the calendar button array.
	$context['calendar_buttons'] = array();

	if ($context['can_post'])
		$context['calendar_buttons']['post_event'] = array('text' => 'calendar_post_event', 'image' => 'calendarpe.png', 'url' => $scripturl . '?action=calendar;sa=post;month=' . $context['current_month'] . ';year=' . $context['current_year'] . ';' . $context['session_var'] . '=' . $context['session_id']);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_calendar_buttons');
}

/**
 * This function processes posting/editing/deleting a calendar event.
 *
 * 	- calls {@link Post.php|Post() Post()} function if event is linked to a post.
 *  - calls {@link Subs-Calendar.php|insertEvent() insertEvent()} to insert the event if not linked to post.
 *
 * It requires the calendar_post permission to use.
 * It uses the event_post sub template in the Calendar template.
 * It is accessed with ?action=calendar;sa=post.
 */
function CalendarPost()
{
	global $context, $txt, $user_info, $sourcedir, $scripturl;
	global $modSettings, $topic, $smcFunc;

	// Well - can they?
	isAllowedTo('calendar_post');

	// We need these for all kinds of useful functions.
	require_once($sourcedir . '/Subs-Calendar.php');
	require_once($sourcedir . '/Subs.php');

	// Cast this for safety...
	if (isset($_REQUEST['eventid']))
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

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

	// Submitting?
	if (isset($_POST[$context['session_var']], $_REQUEST['eventid']))
	{
		checkSession();

		// Validate the post...
		if (!isset($_POST['link_to_board']))
			validateEventPost();

		// If you're not allowed to edit any events, you have to be the poster.
		if ($_REQUEST['eventid'] > 0 && !allowedTo('calendar_edit_any'))
			isAllowedTo('calendar_edit_' . (!empty($user_info['id']) && getEventPoster($_REQUEST['eventid']) == $user_info['id'] ? 'own' : 'any'));

		// New - and directing?
		if (isset($_POST['link_to_board']) || empty($modSettings['cal_allow_unlinked']))
		{
			$_REQUEST['calendar'] = 1;
			require_once($sourcedir . '/Post.php');
			return Post();
		}
		// New...
		elseif ($_REQUEST['eventid'] == -1)
		{
			$eventOptions = array(
				'board' => 0,
				'topic' => 0,
				'title' => $smcFunc['substr']($_REQUEST['evtitle'], 0, 100),
				'location' => $smcFunc['substr']($_REQUEST['event_location'], 0, 255),
				'member' => $user_info['id'],
			);
			insertEvent($eventOptions);
		}

		// Deleting...
		elseif (isset($_REQUEST['deleteevent']))
			removeEvent($_REQUEST['eventid']);

		// ... or just update it?
		else
		{
			$eventOptions = array(
				'title' => $smcFunc['substr']($_REQUEST['evtitle'], 0, 100),
				'location' => $smcFunc['substr']($_REQUEST['event_location'], 0, 255),
			);
			modifyEvent($_REQUEST['eventid'], $eventOptions);
		}

		updateSettings(array(
			'calendar_updated' => time(),
		));

		// No point hanging around here now...
		if (isset($_POST['start_date']))
		{
			$d = date_parse($_POST['start_date']);
			$year = $d['year'];
			$month = $d['month'];
			$day = $d['day'];
		}
		elseif (isset($_POST['start_datetime']))
		{
			$d = date_parse($_POST['start_datetime']);
			$year = $d['year'];
			$month = $d['month'];
			$day = $d['day'];
		}
		else
		{
			$today = getdate();
			$year = isset($_POST['year']) ? $_POST['year'] : $today['year'];
			$month = isset($_POST['month']) ? $_POST['month'] : $today['mon'];
			$day = isset($_POST['day']) ? $_POST['day'] : $today['mday'];
		}
		redirectexit($scripturl . '?action=calendar;month=' . $month . ';year=' . $year . ';day=' . $day);
	}

	// If we are not enabled... we are not enabled.
	if (empty($modSettings['cal_allow_unlinked']) && empty($_REQUEST['eventid']))
	{
		$_REQUEST['calendar'] = 1;
		require_once($sourcedir . '/Post.php');
		return Post();
	}

	// New?
	if (!isset($_REQUEST['eventid']))
	{
		$context['event'] = array(
			'boards' => array(),
			'board' => 0,
			'new' => 1,
			'eventid' => -1,
			'title' => '',
			'location' => '',
		);

		$eventDatetimes = getNewEventDatetimes();
		$context['event'] = array_merge($context['event'], $eventDatetimes);

		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));
	}
	else
	{
		$context['event'] = getEventProperties($_REQUEST['eventid']);

		if ($context['event'] === false)
			fatal_lang_error('no_access', false);

		// If it has a board, then they should be editing it within the topic.
		if (!empty($context['event']['topic']['id']) && !empty($context['event']['topic']['first_msg']))
		{
			// We load the board up, for a check on the board access rights...
			$topic = $context['event']['topic']['id'];
			loadBoard();
		}

		// Make sure the user is allowed to edit this event.
		if ($context['event']['member'] != $user_info['id'])
			isAllowedTo('calendar_edit_any');
		elseif (!allowedTo('calendar_edit_any'))
			isAllowedTo('calendar_edit_own');
	}

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

	// Get list of boards that can be posted in.
	$boards = boardsAllowedTo('post_new');
	if (empty($boards))
	{
		// You can post new events but can't link them to anything...
		$context['event']['categories'] = array();
	}
	else
	{
		// Load the list of boards and categories in the context.
		require_once($sourcedir . '/Subs-MessageIndex.php');
		$boardListOptions = array(
			'included_boards' => in_array(0, $boards) ? null : $boards,
			'not_redirection' => true,
			'use_permissions' => true,
			'selected_board' => $modSettings['cal_defaultboard'],
		);
		$context['event']['categories'] = getBoardList($boardListOptions);
	}

	// Template, sub template, etc.
	loadTemplate('Calendar');
	$context['sub_template'] = 'event_post';

	$context['page_title'] = isset($_REQUEST['eventid']) ? $txt['calendar_edit'] : $txt['calendar_post_event'];
	$context['linktree'][] = array(
		'name' => $context['page_title'],
	);

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
}

/**
 * This function offers up a download of an event in iCal 2.0 format.
 *
 * Follows the conventions in {@link https://tools.ietf.org/html/rfc5546 RFC5546}
 * Sets events as all day events since we don't have hourly events
 * Will honor and set multi day events
 * Sets a sequence number if the event has been modified
 *
 * @todo .... allow for week or month export files as well?
 */
function iCalDownload()
{
	global $smcFunc, $sourcedir, $forum_version, $modSettings, $webmaster_email, $mbname;

	// You can't export if the calendar export feature is off.
	if (empty($modSettings['cal_export']))
		fatal_lang_error('calendar_export_off', false);

	// Goes without saying that this is required.
	if (!isset($_REQUEST['eventid']))
		fatal_lang_error('no_access', false);

	// This is kinda wanted.
	require_once($sourcedir . '/Subs-Calendar.php');

	// Load up the event in question and check it exists.
	$event = getEventProperties($_REQUEST['eventid']);

	if ($event === false)
		fatal_lang_error('no_access', false);

	// Check the title isn't too long - iCal requires some formatting if so.
	$title = str_split($event['title'], 30);
	foreach ($title as $id => $line)
	{
		if ($id != 0)
			$title[$id] = ' ' . $title[$id];
		$title[$id] .= "\n";
	}

	// Format the dates.
	$datestamp = date('Ymd\THis\Z', time());
	$start_date = date_create($event['start_date'] . (isset($event['start_time']) ? ' ' . $event['start_time'] : '') . (isset($event['tz']) ? ' ' . $event['tz'] : ''));
	$end_date = date_create($event['end_date'] . (isset($event['end_time']) ? ' ' . $event['end_time'] : '') . (isset($event['tz']) ? ' ' . $event['tz'] : ''));

	if (!empty($event['start_time']))
	{
		$datestart = date_format($start_date, 'Ymd\THis');
		$dateend = date_format($end_date, 'Ymd\THis');
	}
	else
	{
		$datestart = date_format($start_date, 'Ymd');

		date_add($end_date, date_interval_create_from_date_string('1 day'));
		$dateend = date_format($end_date, 'Ymd');
	}

	// This is what we will be sending later
	$filecontents = '';
	$filecontents .= 'BEGIN:VCALENDAR' . "\n";
	$filecontents .= 'METHOD:PUBLISH' . "\n";
	$filecontents .= 'PRODID:-//SimpleMachines//SMF ' . (empty($forum_version) ? 2.1 : strtr($forum_version, array('SMF ' => ''))) . '//EN' . "\n";
	$filecontents .= 'VERSION:2.0' . "\n";
	$filecontents .= 'BEGIN:VEVENT' . "\n";
	// @TODO - Should be the members email who created the event rather than $webmaster_email.
	$filecontents .= 'ORGANIZER;CN="' . $event['realname'] . '":MAILTO:' . $webmaster_email . "\n";
	$filecontents .= 'DTSTAMP:' . $datestamp . "\n";
	$filecontents .= 'DTSTART' . (!empty($event['start_time']) ? ';TZID=' . $event['tz'] : ';VALUE=DATE') . ':' . $datestart . "\n";

	// event has a duration
	if ($event['start_iso_gmdate'] != $event['end_iso_gmdate'])
		$filecontents .= 'DTEND' . (!empty($event['end_time']) ? ';TZID=' . $event['tz'] : ';VALUE=DATE') . ':' . $dateend . "\n";

	// event has changed? advance the sequence for this UID
	if ($event['sequence'] > 0)
		$filecontents .= 'SEQUENCE:' . $event['sequence'] . "\n";

	if (!empty($event['location']))
		$filecontents .= 'LOCATION:' . str_replace(',', '\,', $event['location']) . "\n";

	$filecontents .= 'SUMMARY:' . implode('', $title);
	$filecontents .= 'UID:' . $event['eventid'] . '@' . str_replace(' ', '-', $mbname) . "\n";
	$filecontents .= 'END:VEVENT' . "\n";
	$filecontents .= 'END:VCALENDAR';

	// Send some standard headers.
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Send the file headers
	header('pragma: ');
	header('cache-control: no-cache');
	if (!isBrowser('gecko'))
		header('content-transfer-encoding: binary');
	header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s', time()) . 'GMT');
	header('accept-ranges: bytes');
	header('connection: close');
	header('content-disposition: attachment; filename="' . $event['title'] . '.ics"');
	if (empty($modSettings['enableCompressedOutput']))
		header('content-length: ' . $smcFunc['strlen']($filecontents));

	// This is a calendar item!
	header('content-type: text/calendar');

	// Chuck out the card.
	echo $filecontents;

	// Off we pop - lovely!
	obExit(false);
}

/**
 * Nothing to see here. Move along.
 */
function clock()
{
	global $smcFunc, $settings, $context, $scripturl;

	$context['onimg'] = $settings['images_url'] . '/bbc/bbc_hoverbg.png';
	$context['offimg'] = $settings['images_url'] . '/bbc/bbc_bg.png';

	$context['page_title'] = 'Anyone know what time it is?';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=clock',
		'name' => 'Clock',
	);
	$context['robot_no_index'] = true;

	$omfg = isset($_REQUEST['omfg']);
	$bcd = !isset($_REQUEST['rb']) && !isset($_REQUEST['omfg']) && !isset($_REQUEST['time']);

	loadTemplate('Calendar');

	if ($bcd)
	{
		$context['sub_template'] = 'bcd';
		$context['linktree'][] = array('url' => $scripturl . '?action=clock;bcd', 'name' => 'BCD');
		$context['clockicons'] = $smcFunc['json_decode'](base64_decode('eyJoMSI6WzIsMV0sImgyIjpbOCw0LDIsMV0sIm0xIjpbNCwyLDFdLCJtMiI6WzgsNCwyLDFdLCJzMSI6WzQsMiwxXSwiczIiOls4LDQsMiwxXX0='), true);
	}
	elseif (!$omfg && !isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'hms';
		$context['linktree'][] = array('url' => $scripturl . '?action=clock', 'name' => 'Binary');
		$context['clockicons'] = $smcFunc['json_decode'](base64_decode('eyJoIjpbMTYsOCw0LDIsMV0sIm0iOlszMiwxNiw4LDQsMiwxXSwicyI6WzMyLDE2LDgsNCwyLDFdfQ'), true);
	}
	elseif ($omfg)
	{
		$context['sub_template'] = 'omfg';
		$context['linktree'][] = array('url' => $scripturl . '?action=clock;omfg', 'name' => 'OMFG');
		$context['clockicons'] = $smcFunc['json_decode'](base64_decode('eyJ5ZWFyIjpbNjQsMzIsMTYsOCw0LDIsMV0sIm1vbnRoIjpbOCw0LDIsMV0sImRheSI6WzE2LDgsNCwyLDFdLCJob3VyIjpbMTYsOCw0LDIsMV0sIm1pbiI6WzMyLDE2LDgsNCwyLDFdLCJzZWMiOlszMiwxNiw4LDQsMiwxXX0='), true);
	}
	elseif (isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'thetime';
		$time = getdate($_REQUEST['time'] == 'now' ? time() : (int) $_REQUEST['time']);
		$context['linktree'][] = array('url' => $scripturl . '?action=clock;time=' . $_REQUEST['time'], 'name' => 'Requested Time');
		$context['clockicons'] = array(
			'year' => array(
				64 => false,
				32 => false,
				16 => false,
				8 => false,
				4 => false,
				2 => false,
				1 => false
			),
			'month' => array(
				8 => false,
				4 => false,
				2 => false,
				1 => false
			),
			'day' => array(
				16 => false,
				4 => false,
				8 => false,
				2 => false,
				1 => false
			),
			'hour' => array(
				32 => false,
				16 => false,
				8 => false,
				4 => false,
				2 => false,
				1 => false
			),
			'min' => array(
				32 => false,
				16 => false,
				8 => false,
				4 => false,
				2 => false,
				1 => false
			),
			'sec' => array(
				32 => false,
				16 => false,
				8 => false,
				4 => false,
				2 => false,
				1 => false
			),
		);

		foreach ($context['clockicons'] as $t => $vs)
			foreach ($vs as $v => $dumb)
			{
				if ($$t >= $v)
				{
					$$t -= $v;
					$context['clockicons'][$t][$v] = true;
				}
			}
	}
}

?>