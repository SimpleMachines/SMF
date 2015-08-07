<?php

/**
 * This file has only one real task, showing the calendar.
 * Original module by Aaron O'Neil - aaron@mud-master.com
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
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

	if (isset($_GET['sa']) && isset($subActions[$_GET['sa']]) && !WIRELESS)
		return call_helper($subActions[$_GET['sa']]);

	// You can't do anything if the calendar is off.
	if (empty($modSettings['cal_enabled']))
		fatal_lang_error('calendar_off', false);

	// This is gonna be needed...
	loadTemplate('Calendar');
	loadCSSFile('calendar.css', array('force_current' => false, 'validate' => true, 'rtl' => 'calendar.rtl.css'));

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
				// We know the format is going to be in yyyy-mm-dd from the database, so let's run with that.
				list($_REQUEST['year'], $_REQUEST['month']) = explode('-', $row['start_date']);
				$_REQUEST['year'] = (int) $_REQUEST['year'];
				$_REQUEST['month'] = (int) $_REQUEST['month'];

				// And we definitely don't want weekly view.
				unset ($_GET['viewweek']);

				// We might use this later.
				$context['selected_event'] = $evid;
			}
			$smcFunc['db_free_result']($request);
		}
		unset ($_GET['event']);
	}

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
	if ($context['view_week'])
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
	$context['page_title'] .= ' - ' . ($context['view_week'] ? $context['calendar_grid_main']['week_title'] : $txt['months'][$context['current_month']] . ' ' . $context['current_year']);

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
			'name' => $context['calendar_grid_main']['week_title'],
		);

	// Build the calendar button array.
	$context['calendar_buttons'] = array(
		'post_event' => array('test' => 'can_post', 'text' => 'calendar_post_event', 'image' => 'calendarpe.png', 'lang' => true, 'url' => $scripturl . '?action=calendar;sa=post;month=' . $context['current_month'] . ';year=' . $context['current_year'] . ';' . $context['session_var'] . '=' . $context['session_id']),
	);

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
				'title' => $smcFunc['substr']($_REQUEST['evtitle'], 0, 100),
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
}

/**
 * This function offers up a download of an event in iCal 2.0 format.
 *
 * follows the conventions in RFC5546 http://tools.ietf.org/html/rfc5546
 * sets events as all day events since we don't have hourly events
 * will honor and set multi day events
 * sets a sequence number if the event has been modified
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
	$datestart = $event['year'] . ($event['month'] < 10 ? '0' . $event['month'] : $event['month']) . ($event['day'] < 10 ? '0' . $event['day'] : $event['day']);

	// Do we have a event that spans several days?
	if ($event['span'] > 1)
	{
		$dateend = strtotime($event['year'] . '-' . ($event['month'] < 10 ? '0' . $event['month'] : $event['month']) . '-' . ($event['day'] < 10 ? '0' . $event['day'] : $event['day']));
		$dateend += ($event['span'] - 1) * 86400;
		$dateend = date('Ymd', $dateend);
	}

	// This is what we will be sending later
	$filecontents = '';
	$filecontents .= 'BEGIN:VCALENDAR' . "\n";
	$filecontents .= 'METHOD:PUBLISH' . "\n";
	$filecontents .= 'PRODID:-//SimpleMachines//SMF ' . (empty($forum_version) ? 2.0 : strtr($forum_version, array('SMF ' => ''))) . '//EN' . "\n";
	$filecontents .= 'VERSION:2.0' . "\n";
	$filecontents .= 'BEGIN:VEVENT' . "\n";
	// @TODO - Should be the members email who created the event rather than $webmaster_email.
	$filecontents .= 'ORGANIZER;CN="' . $event['realname'] . '":MAILTO:' . $webmaster_email . "\n";
	$filecontents .= 'DTSTAMP:' . $datestamp . "\n";
	$filecontents .= 'DTSTART;VALUE=DATE:' . $datestart . "\n";

	// more than one day
	if ($event['span'] > 1)
		$filecontents .= 'DTEND;VALUE=DATE:' . $dateend . "\n";

	// event has changed? advance the sequence for this UID
	if ($event['sequence'] > 0)
		$filecontents .= 'SEQUENCE:' . $event['sequence'] . "\n";

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
	header('Pragma: ');
	header('Cache-Control: no-cache');
	if (!isBrowser('gecko'))
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . 'GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('Content-Disposition: attachment; filename="' . $event['title'] . '.ics"');
	if (empty($modSettings['enableCompressedOutput']))
		header('Content-Length: ' . $smcFunc['strlen']($filecontents));

	// This is a calendar item!
	header('Content-Type: text/calendar');

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
	global $settings, $context, $scripturl;

	$context['onimg'] = $settings['images_url'] . '/bbc/bbc_bg.png';
	$context['offimg'] = $settings['images_url'] . '/bbc/bbc_hoverbg.png';

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
		$context['clockicons'] = unserialize(base64_decode('YTo2OntzOjI6ImgxIjthOjI6e2k6MDtpOjI7aToxO2k6MTt9czoyOiJoMiI7YTo0OntpOjA7aTo4O2k6MTtpOjQ7aToyO2k6MjtpOjM7aToxO31zOjI6Im0xIjthOjM6e2k6MDtpOjQ7aToxO2k6MjtpOjI7aToxO31zOjI6Im0yIjthOjQ6e2k6MDtpOjg7aToxO2k6NDtpOjI7aToyO2k6MztpOjE7fXM6MjoiczEiO2E6Mzp7aTowO2k6NDtpOjE7aToyO2k6MjtpOjE7fXM6MjoiczIiO2E6NDp7aTowO2k6ODtpOjE7aTo0O2k6MjtpOjI7aTozO2k6MTt9fQ=='));
	}
	elseif (!$omfg && !isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'hms';
		$context['linktree'][] = array('url' => $scripturl . '?action=clock', 'name' => 'Binary');
		$context['clockicons'] = unserialize(base64_decode('YTozOntzOjE6ImgiO2E6NTp7aTowO2k6MTY7aToxO2k6ODtpOjI7aTo0O2k6MztpOjI7aTo0O2k6MTt9czoxOiJtIjthOjY6e2k6MDtpOjMyO2k6MTtpOjE2O2k6MjtpOjg7aTozO2k6NDtpOjQ7aToyO2k6NTtpOjE7fXM6MToicyI7YTo2OntpOjA7aTozMjtpOjE7aToxNjtpOjI7aTo4O2k6MztpOjQ7aTo0O2k6MjtpOjU7aToxO319'));
	}
	elseif ($omfg)
	{
		$context['sub_template'] = 'omfg';
		$context['linktree'][] = array('url' => $scripturl . '?action=clock;omfg', 'name' => 'OMFG');
		$context['clockicons'] = unserialize(base64_decode('YTo2OntzOjQ6InllYXIiO2E6Nzp7aTowO2k6NjQ7aToxO2k6MzI7aToyO2k6MTY7aTozO2k6ODtpOjQ7aTo0O2k6NTtpOjI7aTo2O2k6MTt9czo1OiJtb250aCI7YTo0OntpOjA7aTo4O2k6MTtpOjQ7aToyO2k6MjtpOjM7aToxO31zOjM6ImRheSI7YTo1OntpOjA7aToxNjtpOjE7aTo4O2k6MjtpOjQ7aTozO2k6MjtpOjQ7aToxO31zOjQ6ImhvdXIiO2E6NTp7aTowO2k6MTY7aToxO2k6ODtpOjI7aTo0O2k6MztpOjI7aTo0O2k6MTt9czozOiJtaW4iO2E6Njp7aTowO2k6MzI7aToxO2k6MTY7aToyO2k6ODtpOjM7aTo0O2k6NDtpOjI7aTo1O2k6MTt9czozOiJzZWMiO2E6Njp7aTowO2k6MzI7aToxO2k6MTY7aToyO2k6ODtpOjM7aTo0O2k6NDtpOjI7aTo1O2k6MTt9fQ=='));
	}
	elseif (isset($_REQUEST['time']))
	{
		$context['sub_template'] = 'thetime';
		$time = getdate($_REQUEST['time'] == 'now' ? time() : (int) $_REQUEST['time']);
		$year = $time['year'] % 100;
		$month = $time['mon'];
		$day = $time['mday'];
		$hour = $time['hours'];
		$min = $time['minutes'];
		$sec = $time['seconds'];
		$context['linktree'][] = array('url' => $scripturl . '?action=clock;time=' . $_REQUEST['time'], 'name' => 'Requested Time');
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