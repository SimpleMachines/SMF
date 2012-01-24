<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// Original module by Aaron O'Neil - aaron@mud-master.com

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file has only one real task... showing the calendar.  Posting is done
	in Post.php - this just has the following functions:

	void CalendarMain()
		- loads the specified month's events, holidays, and birthdays.
		- requires the calendar_view permission.
		- depends on the cal_enabled setting, and many of the other cal_
		  settings.
		- uses the calendar_start_day theme option. (Monday/Sunday)
		- uses the main sub template in the Calendar template.
		- goes to the month and year passed in 'month' and 'year' by
		  get or post.
		- accessed through ?action=calendar.

	void CalendarPost()
		- processes posting/editing/deleting a calendar event.
		- calls Post() function if event is linked to a post.
		- calls insertEvent() to insert the event if not linked to post.
		- requires the calendar_post permission to use.
		- uses the event_post sub template in the Calendar template.
		- is accessed with ?action=calendar;sa=post.

	void iCalDownload()
		- offers up a download of an event in iCal 2.0 format.
*/

// Show the calendar.
function CalendarMain()
{
	global $txt, $context, $modSettings, $scripturl, $options, $sourcedir;

	// Permissions, permissions, permissions.
	isAllowedTo('calendar_view');

	// Doing something other than calendar viewing?
	$subActions = array(
		'ical' => 'iCalDownload',
		'post' => 'CalendarPost',
	);

	if (isset($_GET['sa']) && isset($subActions[$_GET['sa']]) && !WIRELESS)
		return $subActions[$_GET['sa']]();

	// This is gonna be needed...
	loadTemplate('Calendar');

	// You can't do anything if the calendar is off.
	if (empty($modSettings['cal_enabled']))
		fatal_lang_error('calendar_off', false);

	// Set the page title to mention the calendar ;).
	$context['page_title'] = $txt['calendar'];

	// Is this a week view?
	$context['view_week'] = isset($_GET['viewweek']);

	// Don't let search engines index weekly calendar pages.
	if ($context['view_week'])
		$context['robot_no_index'] = true;

	// Get the current day of month...
	require_once($sourcedir . '/Subs-Calendar.php');
	$today = getTodayInfo();

	// If the month and year are not passed in, use today's date as a starting point.
	$curPage = array(
		'day' => isset($_REQUEST['day']) ? (int) $_REQUEST['day'] : $today['day'],
		'month' => isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['month'],
		'year' => isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year']
	);

	// Make sure the year and month are in valid ranges.
	if ($curPage['month'] < 1 || $curPage['month'] > 12)
		fatal_lang_error('invalid_month', false);
	if ($curPage['year'] < $modSettings['cal_minyear'] || $curPage['year'] > $modSettings['cal_maxyear'])
		fatal_lang_error('invalid_year', false);
	// If we have a day clean that too.
	if ($context['view_week'])
	{
		// Note $isValid is -1 < PHP 5.1
		$isValid = mktime(0, 0, 0, $curPage['month'], $curPage['day'], $curPage['year']);
		if ($curPage['day'] > 31 || !$isValid || $isValid == -1)
			fatal_lang_error('invalid_day', false);
	}

	// Load all the context information needed to show the calendar grid.
	$calendarOptions = array(
		'start_day' => !empty($options['calendar_start_day']) ? $options['calendar_start_day'] : 0,
		'show_birthdays' => in_array($modSettings['cal_showbdays'], array(1, 2)),
		'show_events' => in_array($modSettings['cal_showevents'], array(1, 2)),
		'show_holidays' => in_array($modSettings['cal_showholidays'], array(1, 2)),
		'show_week_num' => true,
		'short_day_titles' => false,
		'show_next_prev' => true,
		'show_week_links' => true,
		'size' => 'large',
	);

	// Load up the main view.
	if ($context['view_week'])
		$context['calendar_grid_main'] = getCalendarWeek($curPage['month'], $curPage['year'], $curPage['day'], $calendarOptions);
	else
		$context['calendar_grid_main'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

	// Load up the previous and next months.
	$calendarOptions['show_birthdays'] = $calendarOptions['show_events'] = $calendarOptions['show_holidays'] = false;
	$calendarOptions['short_day_titles'] = true;
	$calendarOptions['show_next_prev'] = false;
	$calendarOptions['show_week_links'] = false;
	$calendarOptions['size'] = 'small';
	$context['calendar_grid_current'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);
	// Only show previous month if it isn't pre-January of the min-year
	if ($context['calendar_grid_current']['previous_calendar']['year'] > $modSettings['cal_minyear'] || $curPage['month'] != 1)
		$context['calendar_grid_prev'] = getCalendarGrid($context['calendar_grid_current']['previous_calendar']['month'], $context['calendar_grid_current']['previous_calendar']['year'], $calendarOptions);
	// Only show next month if it isn't post-December of the max-year
	if ($context['calendar_grid_current']['next_calendar']['year'] < $modSettings['cal_maxyear'] || $curPage['month'] != 12)
		$context['calendar_grid_next'] = getCalendarGrid($context['calendar_grid_current']['next_calendar']['month'], $context['calendar_grid_current']['next_calendar']['year'], $calendarOptions);

	// Basic template stuff.
	$context['can_post'] = allowedTo('calendar_post');
	$context['current_day'] = $curPage['day'];
	$context['current_month'] = $curPage['month'];
	$context['current_year'] = $curPage['year'];
	$context['show_all_birthdays'] = isset($_GET['showbd']);

	// Set the page title to mention the month or week, too
	$context['page_title'] .= ' - ' . ($context['view_week'] ? sprintf($txt['calendar_week_title'], $context['calendar_grid_main']['week_number'], ($context['calendar_grid_main']['week_number'] == 53 ? $context['current_year'] - 1 : $context['current_year'])) : $txt['months'][$context['current_month']] . ' ' . $context['current_year']);

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
	if ($context['view_week'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=calendar;viewweek;year=' . $context['current_year'] . ';month=' . $context['current_month'] . ';day=' . $context['current_day'],
			'name' => $txt['calendar_week'] . ' ' . $context['calendar_grid_main']['week_number']
		);
}

function CalendarPost()
{
	global $context, $txt, $user_info, $sourcedir, $scripturl;
	global $modSettings, $topic, $smcFunc;

	// Well - can they?
	isAllowedTo('calendar_post');

	// We need this for all kinds of useful functions.
	require_once($sourcedir . '/Subs-Calendar.php');

	// Cast this for safety...
	if (isset($_REQUEST['eventid']))
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

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
		if ($_REQUEST['eventid'] == -1 && isset($_POST['link_to_board']))
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
				'title' => substr($_REQUEST['evtitle'], 0, 60),
				'member' => $user_info['id'],
				'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
				'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
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
				'title' => substr($_REQUEST['evtitle'], 0, 60),
				'span' => empty($modSettings['cal_allowspan']) || empty($_POST['span']) || $_POST['span'] == 1 || empty($modSettings['cal_maxspan']) || $_POST['span'] > $modSettings['cal_maxspan'] ? 0 : min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1),
				'start_date' => strftime('%Y-%m-%d', mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year'])),
			);

			modifyEvent($_REQUEST['eventid'], $eventOptions);
		}

		updateSettings(array(
			'calendar_updated' => time(),
		));

		// No point hanging around here now...
		redirectexit($scripturl . '?action=calendar;month=' . $_POST['month'] . ';year=' . $_POST['year']);
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
		$today = getdate();

		$context['event'] = array(
			'boards' => array(),
			'board' => 0,
			'new' => 1,
			'eventid' => -1,
			'year' => isset($_REQUEST['year']) ? $_REQUEST['year'] : $today['year'],
			'month' => isset($_REQUEST['month']) ? $_REQUEST['month'] : $today['mon'],
			'day' => isset($_REQUEST['day']) ? $_REQUEST['day'] : $today['mday'],
			'title' => '',
			'span' => 1,
		);
		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		// Get list of boards that can be posted in.
		$boards = boardsAllowedTo('post_new');
		if (empty($boards))
			fatal_lang_error('cannot_post_new', 'permission');

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

	// Template, sub template, etc.
	loadTemplate('Calendar');
	$context['sub_template'] = 'event_post';

	$context['page_title'] = isset($_REQUEST['eventid']) ? $txt['calendar_edit'] : $txt['calendar_post_event'];
	$context['linktree'][] = array(
		'name' => $context['page_title'],
	);
}

function iCalDownload()
{
	global $smcFunc, $sourcedir, $forum_version, $context, $modSettings;

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

	// Format the date.
	$date = $event['year'] . '-' . ($event['month'] < 10 ? '0' . $event['month'] : $event['month']) . '-' . ($event['day'] < 10 ? '0' . $event['day'] : $event['day']) . 'T';
	$date .= '1200:00:00Z';

	// This is what we will be sending later.
	$filecontents = '';
	$filecontents .= 'BEGIN:VCALENDAR' . "\n";
	$filecontents .= 'VERSION:2.0' . "\n";
	$filecontents .= 'PRODID:-//SimpleMachines//SMF ' . (empty($forum_version) ? 1.0 : strtr($forum_version, array('SMF ' => ''))) . '//EN' . "\n";
	$filecontents .= 'BEGIN:VEVENT' . "\n";
	$filecontents .= 'DTSTART:' . $date . "\n";
	$filecontents .= 'DTEND:' . $date . "\n";
	$filecontents .= 'SUMMARY:' . implode('', $title);
	$filecontents .= 'END:VEVENT' . "\n";
	$filecontents .= 'END:VCALENDAR';

	// Send some standard headers.
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Send the file headers
	header('Pragma: ');
	header('Cache-Control: no-cache');
	if (!$context['browser']['is_gecko'])
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . 'GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('Content-Disposition: attachment; filename=' . $event['title'] . '.ics');

	// How big is it?
	if (empty($modSettings['enableCompressedOutput']))
		header('Content-Length: ' . $smcFunc['strlen']($filecontents));

	// This is a calendar item!
	header('Content-Type: text/calendar');

	// Chuck out the card.
	echo $filecontents;

	// Off we pop - lovely!
	obExit(false);
}

// This is not the code you are looking for.
function clock()
{
	global $settings, $context;
	$context['onimg'] = $settings['images_url'] . '/bbc/bbc_bg.gif';
	$context['offimg'] = $settings['images_url'] . '/bbc/bbc_hoverbg.gif';

	$context['page_title'] = 'Anyone know what time it is?';
	$context['robot_no_index'] = true;

	$omfg = isset($_REQUEST['omfg']);
	$bcd = !isset($_REQUEST['rb']) && !isset($_REQUEST['omfg']) && !isset($_REQUEST['time']);

	loadTemplate('Calendar');

	if ($bcd && !$omfg)
	{
		$context['sub_template'] = 'bcd';
		$context['clockicons'] = unserialize(base64_decode('YTo2OntzOjI6ImgxIjthOjI6e2k6MDtpOjI7aToxO2k6MTt9czoyOiJoMiI7YTo0OntpOjA7aTo4O2k6MTtpOjQ7aToyO2k6MjtpOjM7aToxO31zOjI6Im0xIjthOjM6e2k6MDtpOjQ7aToxO2k6MjtpOjI7aToxO31zOjI6Im0yIjthOjQ6e2k6MDtpOjg7aToxO2k6NDtpOjI7aToyO2k6MztpOjE7fXM6MjoiczEiO2E6Mzp7aTowO2k6NDtpOjE7aToyO2k6MjtpOjE7fXM6MjoiczIiO2E6NDp7aTowO2k6ODtpOjE7aTo0O2k6MjtpOjI7aTozO2k6MTt9fQ=='));
	}
	elseif (!$omfg && !isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'hms';
		$context['clockicons'] = unserialize(base64_decode('YTozOntzOjE6ImgiO2E6NTp7aTowO2k6MTY7aToxO2k6ODtpOjI7aTo0O2k6MztpOjI7aTo0O2k6MTt9czoxOiJtIjthOjY6e2k6MDtpOjMyO2k6MTtpOjE2O2k6MjtpOjg7aTozO2k6NDtpOjQ7aToyO2k6NTtpOjE7fXM6MToicyI7YTo2OntpOjA7aTozMjtpOjE7aToxNjtpOjI7aTo4O2k6MztpOjQ7aTo0O2k6MjtpOjU7aToxO319'));
	}
	elseif ($omfg)
	{
		$context['sub_template'] = 'omfg';
		$context['clockicons'] = unserialize(base64_decode('YTo2OntzOjQ6InllYXIiO2E6Nzp7aTowO2k6NjQ7aToxO2k6MzI7aToyO2k6MTY7aTozO2k6ODtpOjQ7aTo0O2k6NTtpOjI7aTo2O2k6MTt9czo1OiJtb250aCI7YTo0OntpOjA7aTo4O2k6MTtpOjQ7aToyO2k6MjtpOjM7aToxO31zOjM6ImRheSI7YTo1OntpOjA7aToxNjtpOjE7aTo4O2k6MjtpOjQ7aTozO2k6MjtpOjQ7aToxO31zOjQ6ImhvdXIiO2E6NTp7aTowO2k6MTY7aToxO2k6ODtpOjI7aTo0O2k6MztpOjI7aTo0O2k6MTt9czozOiJtaW4iO2E6Njp7aTowO2k6MzI7aToxO2k6MTY7aToyO2k6ODtpOjM7aTo0O2k6NDtpOjI7aTo1O2k6MTt9czozOiJzZWMiO2E6Njp7aTowO2k6MzI7aToxO2k6MTY7aToyO2k6ODtpOjM7aTo0O2k6NDtpOjI7aTo1O2k6MTt9fQ=='));
	}
	elseif (isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'thetime';
		$time = getdate($_REQUEST['time'] == 'now' ? time() : (int) $_REQUEST['time']);

		$context['clockicons'] = array(
			'year' => array(
				64 => false,
				32 => false,
				16 => false,
				8  => false,
				4  => false,
				2  => false,
				1  => false
			),
			'month' => array(
				8  => false,
				4  => false,
				2  => false,
				1  => false
			),
			'day' => array(
				16 => false,
				4  => false,
				8  => false,
				2  => false,
				1  => false
			),
			'hour' => array(
				32 => false,
				16 => false,
				8  => false,
				4  => false,
				2  => false,
				1  => false
			),
			'min' => array(
				32 => false,
				16 => false,
				8  => false,
				4  => false,
				2  => false,
				1  => false
			),
			'sec' => array(
				32 => false,
				16 => false,
				8  => false,
				4  => false,
				2  => false,
				1  => false
			),
		);

		$year = $time['year'] % 100;
		$month = $time['mon'];
		$day = $time['mday'];
		$hour = $time['hours'];
		$min = $time['minutes'];
		$sec = $time['seconds'];

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