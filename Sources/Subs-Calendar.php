<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
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
 * Get all birthdays within the given time range.
 * finds all the birthdays in the specified range of days.
 * works with birthdays set for no year, or any other year, and respects month and year boundaries.
 *
 * @param string $low_date inclusive, YYYY-MM-DD
 * @param string $high_date inclusive, YYYY-MM-DD
 * @return array days, each of which an array of birthday information for the context
 */
function getBirthdayRange($low_date, $high_date)
{
	global $smcFunc;

	// We need to search for any birthday in this range, and whatever year that birthday is on.
	$year_low = (int) substr($low_date, 0, 4);
	$year_high = (int) substr($high_date, 0, 4);

	// Collect all of the birthdays for this month.  I know, it's a painful query.
	$result = $smcFunc['db_query']('birthday_array', '
		SELECT id_member, real_name, YEAR(birthdate) AS birth_year, birthdate
		FROM {db_prefix}members
		WHERE YEAR(birthdate) != {string:year_one}
			AND MONTH(birthdate) != {int:no_month}
			AND DAYOFMONTH(birthdate) != {int:no_day}
			AND YEAR(birthdate) <= {int:max_year}
			AND (
				DATE_FORMAT(birthdate, {string:year_low}) BETWEEN {date:low_date} AND {date:high_date}' . ($year_low == $year_high ? '' : '
				OR DATE_FORMAT(birthdate, {string:year_high}) BETWEEN {date:low_date} AND {date:high_date}') . '
			)
			AND is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
			'no_month' => 0,
			'no_day' => 0,
			'year_one' => '0001',
			'year_low' => $year_low . '-%m-%d',
			'year_high' => $year_high . '-%m-%d',
			'low_date' => $low_date,
			'high_date' => $high_date,
			'max_year' => $year_high,
		)
	);
	$bday = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if ($year_low != $year_high)
			$age_year = substr($row['birthdate'], 5) < substr($high_date, 5) ? $year_high : $year_low;
		else
			$age_year = $year_low;

		$bday[$age_year . substr($row['birthdate'], 4)][] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'age' => $row['birth_year'] > 4 && $row['birth_year'] <= $age_year ? $age_year - $row['birth_year'] : null,
			'is_last' => false
		);
	}
	$smcFunc['db_free_result']($result);

	// Set is_last, so the themes know when to stop placing separators.
	foreach ($bday as $mday => $array)
		$bday[$mday][count($array) - 1]['is_last'] = true;

	return $bday;
}

/**
 * Get all calendar events within the given time range.
 *
 * - finds all the posted calendar events within a date range.
 * - both the earliest_date and latest_date should be in the standard YYYY-MM-DD format.
 * - censors the posted event titles.
 * - uses the current user's permissions if use_permissions is true, otherwise it does nothing "permission specific"
 *
 * @param string $low_date
 * @param string $high_date
 * @param bool $use_permissions = true
 * @return array contextual information if use_permissions is true, and an array of the data needed to build that otherwise
 */
function getEventRange($low_date, $high_date, $use_permissions = true)
{
	global $scripturl, $modSettings, $user_info, $smcFunc, $context;

	$low_date_time = sscanf($low_date, '%04d-%02d-%02d');
	$low_date_time = mktime(0, 0, 0, $low_date_time[1], $low_date_time[2], $low_date_time[0]);
	$high_date_time = sscanf($high_date, '%04d-%02d-%02d');
	$high_date_time = mktime(0, 0, 0, $high_date_time[1], $high_date_time[2], $high_date_time[0]);

	// Find all the calendar info...
	$result = $smcFunc['db_query']('', '
		SELECT
			cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, cal.id_topic,
			cal.id_board, b.member_groups, t.id_first_msg, t.approved, b.id_board
		FROM {db_prefix}calendar AS cal
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
		WHERE cal.start_date <= {date:high_date}
			AND cal.end_date >= {date:low_date}' . ($use_permissions ? '
			AND (cal.id_board = {int:no_board_link} OR {query_wanna_see_board})' : ''),
		array(
			'high_date' => $high_date,
			'low_date' => $low_date,
			'no_board_link' => 0,
		)
	);
	$events = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// If the attached topic is not approved then for the moment pretend it doesn't exist
		if (!empty($row['id_first_msg']) && $modSettings['postmod_active'] && !$row['approved'])
			continue;

		// Force a censor of the title - as often these are used by others.
		censorText($row['title'], $use_permissions ? false : true);

		$start_date = sscanf($row['start_date'], '%04d-%02d-%02d');
		$start_date = max(mktime(0, 0, 0, $start_date[1], $start_date[2], $start_date[0]), $low_date_time);
		$end_date = sscanf($row['end_date'], '%04d-%02d-%02d');
		$end_date = min(mktime(0, 0, 0, $end_date[1], $end_date[2], $end_date[0]), $high_date_time);

		$lastDate = '';
		for ($date = $start_date; $date <= $end_date; $date += 86400)
		{
			// Attempt to avoid DST problems.
			// @todo Resolve this properly at some point.
			if (strftime('%Y-%m-%d', $date) == $lastDate)
				$date += 3601;
			$lastDate = strftime('%Y-%m-%d', $date);

			// If we're using permissions (calendar pages?) then just ouput normal contextual style information.
			if ($use_permissions)
				$events[strftime('%Y-%m-%d', $date)][] = array(
					'id' => $row['id_event'],
					'title' => $row['title'],
					'start_date' => $row['start_date'],
					'end_date' => $row['end_date'],
					'is_last' => false,
					'id_board' => $row['id_board'],
					'is_selected' => !empty($context['selected_event']) && $context['selected_event'] == $row['id_event'],
					'href' => $row['id_board'] == 0 ? '' : $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_board'] == 0 ? $row['title'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
					'modify_href' => $scripturl . '?action=' . ($row['id_board'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $row['id_first_msg'] . ';topic=' . $row['id_topic'] . '.0;calendar;') . 'eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'can_export' => !empty($modSettings['cal_export']) ? true : false,
					'export_href' => $scripturl . '?action=calendar;sa=ical;eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				);
			// Otherwise, this is going to be cached and the VIEWER'S permissions should apply... just put together some info.
			else
				$events[strftime('%Y-%m-%d', $date)][] = array(
					'id' => $row['id_event'],
					'title' => $row['title'],
					'start_date' => $row['start_date'],
					'end_date' => $row['end_date'],
					'is_last' => false,
					'id_board' => $row['id_board'],
					'is_selected' => !empty($context['selected_event']) && $context['selected_event'] == $row['id_event'],
					'href' => $row['id_topic'] == 0 ? '' : $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_topic'] == 0 ? $row['title'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'can_edit' => false,
					'can_export' => !empty($modSettings['cal_export']) ? true : false,
					'topic' => $row['id_topic'],
					'msg' => $row['id_first_msg'],
					'poster' => $row['id_member'],
					'allowed_groups' => explode(',', $row['member_groups']),
				);
		}
	}
	$smcFunc['db_free_result']($result);

	// If we're doing normal contextual data, go through and make things clear to the templates ;).
	if ($use_permissions)
	{
		foreach ($events as $mday => $array)
			$events[$mday][count($array) - 1]['is_last'] = true;
	}

	return $events;
}

/**
 * Get all holidays within the given time range.
 *
 * @param string $low_date YYYY-MM-DD
 * @param string $high_date YYYY-MM-DD
 * @return array an array of days, which are all arrays of holiday names.
 */
function getHolidayRange($low_date, $high_date)
{
	global $smcFunc;

	// Get the lowest and highest dates for "all years".
	if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
		$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_dec}
			OR event_date BETWEEN {date:all_year_jan} AND {date:all_year_high}';
	else
		$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_high}';

	// Find some holidays... ;).
	$result = $smcFunc['db_query']('', '
		SELECT event_date, YEAR(event_date) AS year, title
		FROM {db_prefix}calendar_holidays
		WHERE event_date BETWEEN {date:low_date} AND {date:high_date}
			OR ' . $allyear_part,
		array(
			'low_date' => $low_date,
			'high_date' => $high_date,
			'all_year_low' => '0004' . substr($low_date, 4),
			'all_year_high' => '0004' . substr($high_date, 4),
			'all_year_jan' => '0004-01-01',
			'all_year_dec' => '0004-12-31',
		)
	);
	$holidays = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
			$event_year = substr($row['event_date'], 5) < substr($high_date, 5) ? substr($high_date, 0, 4) : substr($low_date, 0, 4);
		else
			$event_year = substr($low_date, 0, 4);

		$holidays[$event_year . substr($row['event_date'], 4)][] = $row['title'];
	}
	$smcFunc['db_free_result']($result);

	return $holidays;
}

/**
 * Does permission checks to see if an event can be linked to a board/topic.
 * checks if the current user can link the current topic to the calendar, permissions et al.
 * this requires the calendar_post permission, a forum moderator, or a topic starter.
 * expects the $topic and $board variables to be set.
 * if the user doesn't have proper permissions, an error will be shown.
 */
function canLinkEvent()
{
	global $user_info, $topic, $board, $smcFunc;

	// If you can't post, you can't link.
	isAllowedTo('calendar_post');

	// No board?  No topic?!?
	if (empty($board))
		fatal_lang_error('missing_board_id', false);
	if (empty($topic))
		fatal_lang_error('missing_topic_id', false);

	// Administrator, Moderator, or owner.  Period.
	if (!allowedTo('admin_forum') && !allowedTo('moderate_board'))
	{
		// Not admin or a moderator of this board. You better be the owner - or else.
		$result = $smcFunc['db_query']('', '
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		if ($row = $smcFunc['db_fetch_assoc']($result))
		{
			// Not the owner of the topic.
			if ($row['id_member_started'] != $user_info['id'])
				fatal_lang_error('not_your_topic', 'user');
		}
		// Topic/Board doesn't exist.....
		else
			fatal_lang_error('calendar_no_topic', 'general');
		$smcFunc['db_free_result']($result);
	}
}

/**
 * Returns date information about 'today' relative to the users time offset.
 * returns an array with the current date, day, month, and year.
 * takes the users time offset into account.
 */
function getTodayInfo()
{
	return array(
		'day' => (int) strftime('%d', forum_time()),
		'month' => (int) strftime('%m', forum_time()),
		'year' => (int) strftime('%Y', forum_time()),
		'date' => strftime('%Y-%m-%d', forum_time()),
	);
}

/**
 * Provides information (link, month, year) about the previous and next month.
 * @param int $month
 * @param int $year
 * @param array $calendarOptions
 * @param int $is_previous
 * @return array containing all the information needed to show a calendar grid for the given month
 */
function getCalendarGrid($month, $year, $calendarOptions, $is_previous = false)
{
	global $scripturl, $modSettings;

	// Eventually this is what we'll be returning.
	$calendarGrid = array(
		'week_days' => array(),
		'weeks' => array(),
		'short_day_titles' => !empty($calendarOptions['short_day_titles']),
		'short_month_titles' => !empty($calendarOptions['short_month_titles']),
		'highlight' => array(
			'events' => !empty($calendarOptions['highlight']['events']) && !empty($calendarOptions['show_events']) ? $calendarOptions['highlight']['events'] : 0,
			'holidays' => !empty($calendarOptions['highlight']['holidays']) && !empty($calendarOptions['show_holidays']) ? $calendarOptions['highlight']['holidays'] : 0,
			'birthdays' => !empty($calendarOptions['highlight']['birthdays']) && !empty($calendarOptions['show_birthdays']) ? $calendarOptions['highlight']['birthdays'] : 0,
		),
		'current_month' => $month,
		'current_year' => $year,
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		'show_week_links' => isset($calendarOptions['show_week_links']) ? $calendarOptions['show_week_links'] : 0,
		'previous_calendar' => array(
			'year' => $month == 1 ? $year - 1 : $year,
			'month' => $month == 1 ? 12 : $month - 1,
			'disabled' => $modSettings['cal_minyear'] > ($month == 1 ? $year - 1 : $year),
		),
		'next_calendar' => array(
			'year' => $month == 12 ? $year + 1 : $year,
			'month' => $month == 12 ? 1 : $month + 1,
			'disabled' => $modSettings['cal_maxyear'] < ($month == 12 ? $year + 1 : $year),
		),
		'size' => empty($modSettings['cal_display_type']) ? 'large' : 'small',
	);

	// Get today's date.
	$today = getTodayInfo();

	// Get information about this month.
	$month_info = array(
		'first_day' => array(
			'day_of_week' => (int) strftime('%w', mktime(0, 0, 0, $month, 1, $year)),
			'week_num' => (int) strftime('%U', mktime(0, 0, 0, $month, 1, $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month, 1, $year)),
		),
		'last_day' => array(
			'day_of_month' => (int) strftime('%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
		),
		'first_day_of_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year)),
		'first_day_of_next_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year + 1)),
	);

	// The number of days the first row is shifted to the right for the starting day.
	$nShift = $month_info['first_day']['day_of_week'];

	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];

	// Starting any day other than Sunday means a shift...
	if (!empty($calendarOptions['start_day']))
	{
		$nShift -= $calendarOptions['start_day'];
		if ($nShift < 0)
			$nShift = 7 + $nShift;
	}

	// Number of rows required to fit the month.
	$nRows = floor(($month_info['last_day']['day_of_month'] + $nShift) / 7);
	if (($month_info['last_day']['day_of_month'] + $nShift) % 7)
		$nRows++;

	// Fetch the arrays for birthdays, posted events, and holidays.
	$bday = $calendarOptions['show_birthdays'] ? getBirthdayRange($month_info['first_day']['date'], $month_info['last_day']['date']) : array();
	$events = $calendarOptions['show_events'] ? getEventRange($month_info['first_day']['date'], $month_info['last_day']['date']) : array();
	$holidays = $calendarOptions['show_holidays'] ? getHolidayRange($month_info['first_day']['date'], $month_info['last_day']['date']) : array();

	// Days of the week taking into consideration that they may want it to start on any day.
	$count = $calendarOptions['start_day'];
	for ($i = 0; $i < 7; $i++)
	{
		$calendarGrid['week_days'][] = $count;
		$count++;
		if ($count == 7)
			$count = 0;
	}

	// Iterate through each week.
	$calendarGrid['weeks'] = array();
	for ($nRow = 0; $nRow < $nRows; $nRow++)
	{
		// Start off the week - and don't let it go above 52, since that's the number of weeks in a year.
		$calendarGrid['weeks'][$nRow] = array(
			'days' => array(),
		);

		// And figure out all the days.
		for ($nCol = 0; $nCol < 7; $nCol++)
		{
			$nDay = ($nRow * 7) + $nCol - $nShift + 1;

			if ($nDay < 1 || $nDay > $month_info['last_day']['day_of_month'])
				$nDay = 0;

			$date = sprintf('%04d-%02d-%02d', $year, $month, $nDay);

			$calendarGrid['weeks'][$nRow]['days'][$nCol] = array(
				'day' => $nDay,
				'date' => $date,
				'is_today' => $date == $today['date'],
				'is_first_day' => !empty($calendarOptions['show_week_num']) && (($month_info['first_day']['day_of_week'] + $nDay - 1) % 7 == $calendarOptions['start_day']),
				'is_first_of_month' => $nDay === 1,
				'holidays' => !empty($holidays[$date]) ? $holidays[$date] : array(),
				'events' => !empty($events[$date]) ? $events[$date] : array(),
				'birthdays' => !empty($bday[$date]) ? $bday[$date] : array(),
			);
		}
	}

	// What is the last day of the month?
	if ($is_previous === true)
		$calendarGrid['last_of_month'] = $month_info['last_day']['day_of_month'];

	// We'll use the shift in the template.
	$calendarGrid['shift'] = $nShift;

	// Set the previous and the next month's links.
	$calendarGrid['previous_calendar']['href'] = $scripturl . '?action=calendar;year=' . $calendarGrid['previous_calendar']['year'] . ';month=' . $calendarGrid['previous_calendar']['month'];
	$calendarGrid['next_calendar']['href'] = $scripturl . '?action=calendar;year=' . $calendarGrid['next_calendar']['year'] . ';month=' . $calendarGrid['next_calendar']['month'];

	return $calendarGrid;
}

/**
 * Returns the information needed to show a calendar for the given week.
 * @param int $month
 * @param int $year
 * @param int $day
 * @param array $calendarOptions
 * @return array
 */
function getCalendarWeek($month, $year, $day, $calendarOptions)
{
	global $scripturl, $modSettings, $txt;

	// Get today's date.
	$today = getTodayInfo();

	// What is the actual "start date" for the passed day.
	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];
	$day_of_week = (int) strftime('%w', mktime(0, 0, 0, $month, $day, $year));
	if ($day_of_week != $calendarOptions['start_day'])
	{
		// Here we offset accordingly to get things to the real start of a week.
		$date_diff = $day_of_week - $calendarOptions['start_day'];
		if ($date_diff < 0)
			$date_diff += 7;
		$new_timestamp = mktime(0, 0, 0, $month, $day, $year) - $date_diff * 86400;
		$day = (int) strftime('%d', $new_timestamp);
		$month = (int) strftime('%m', $new_timestamp);
		$year = (int) strftime('%Y', $new_timestamp);
	}

	// Now start filling in the calendar grid.
	$calendarGrid = array(
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		// Previous week is easy - just step back one day.
		'previous_week' => array(
			'year' => $day == 1 ? ($month == 1 ? $year - 1 : $year) : $year,
			'month' => $day == 1 ? ($month == 1 ? 12 : $month - 1) : $month,
			'day' => $day == 1 ? 28 : $day - 1,
			'disabled' => $day < 7 && $modSettings['cal_minyear'] > ($month == 1 ? $year - 1 : $year),
		),
		'next_week' => array(
			'disabled' => $day > 25 && $modSettings['cal_maxyear'] < ($month == 12 ? $year + 1 : $year),
		),
		'size' => empty($modSettings['cal_display_type']) ? 'large' : 'small',
	);

	// The next week calculation requires a bit more work.
	$curTimestamp = mktime(0, 0, 0, $month, $day, $year);
	$nextWeekTimestamp = $curTimestamp + 604800;
	$calendarGrid['next_week']['day'] = (int) strftime('%d', $nextWeekTimestamp);
	$calendarGrid['next_week']['month'] = (int) strftime('%m', $nextWeekTimestamp);
	$calendarGrid['next_week']['year'] = (int) strftime('%Y', $nextWeekTimestamp);

	// Fetch the arrays for birthdays, posted events, and holidays.
	$startDate = strftime('%Y-%m-%d', $curTimestamp);
	$endDate = strftime('%Y-%m-%d', $nextWeekTimestamp);
	$bday = $calendarOptions['show_birthdays'] ? getBirthdayRange($startDate, $endDate) : array();
	$events = $calendarOptions['show_events'] ? getEventRange($startDate, $endDate) : array();
	$holidays = $calendarOptions['show_holidays'] ? getHolidayRange($startDate, $endDate) : array();

	// An adjustment value to apply to all calculated week numbers.
	if (!empty($calendarOptions['show_week_num']))
	{
		$first_day_of_year = (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year));
		$first_day_of_next_year = (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year + 1));
		$last_day_of_last_year = (int) strftime('%w', mktime(0, 0, 0, 12, 31, $year - 1));

		$timestamp = mktime(0, 0, 0, $month, $day, $year);
		$calendarGrid['week_title'] = sprintf($txt['calendar_week_beginning'], date('F', $timestamp), date('j', $timestamp), date('Y', $timestamp));
	}

	// This holds all the main data - there is at least one month!
	$calendarGrid['months'] = array();
	$lastDay = 99;
	$curDay = $day;
	$curDayOfWeek = $calendarOptions['start_day'];
	for ($i = 0; $i < 7; $i++)
	{
		// Have we gone into a new month (Always happens first cycle too)
		if ($lastDay > $curDay)
		{
			$curMonth = $lastDay == 99 ? $month : ($month == 12 ? 1 : $month + 1);
			$curYear = $lastDay == 99 ? $year : ($curMonth == 1 && $month == 12 ? $year + 1 : $year);
			$calendarGrid['months'][$curMonth] = array(
				'current_month' => $curMonth,
				'current_year' => $curYear,
				'days' => array(),
			);
		}

		// Add todays information to the pile!
		$date = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $curDay);

		$calendarGrid['months'][$curMonth]['days'][$curDay] = array(
			'day' => $curDay,
			'day_of_week' => $curDayOfWeek,
			'date' => $date,
			'is_today' => $date == $today['date'],
			'holidays' => !empty($holidays[$date]) ? $holidays[$date] : array(),
			'events' => !empty($events[$date]) ? $events[$date] : array(),
			'birthdays' => !empty($bday[$date]) ? $bday[$date] : array()
		);

		// Make the last day what the current day is and work out what the next day is.
		$lastDay = $curDay;
		$curTimestamp += 86400;
		$curDay = (int) strftime('%d', $curTimestamp);

		// Also increment the current day of the week.
		$curDayOfWeek = $curDayOfWeek >= 6 ? 0 : ++$curDayOfWeek;
	}

	// Set the previous and the next week's links.
	$calendarGrid['previous_week']['href'] = $scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['previous_week']['year'] . ';month=' . $calendarGrid['previous_week']['month'] . ';day=' . $calendarGrid['previous_week']['day'];
	$calendarGrid['next_week']['href'] = $scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['next_week']['year'] . ';month=' . $calendarGrid['next_week']['month'] . ';day=' . $calendarGrid['next_week']['day'];

	return $calendarGrid;
}

/**
 * Retrieve all events for the given days, independently of the users offset.
 * cache callback function used to retrieve the birthdays, holidays, and events between now and now + days_to_index.
 * widens the search range by an extra 24 hours to support time offset shifts.
 * used by the cache_getRecentEvents function to get the information needed to calculate the events taking the users time offset into account.
 *
 * @param int $days_to_index
 * @return array
 */
function cache_getOffsetIndependentEvents($days_to_index)
{
	$low_date = strftime('%Y-%m-%d', forum_time(false) - 24 * 3600);
	$high_date = strftime('%Y-%m-%d', forum_time(false) + $days_to_index * 24 * 3600);

	return array(
		'data' => array(
			'holidays' => getHolidayRange($low_date, $high_date),
			'birthdays' => getBirthdayRange($low_date, $high_date),
			'events' => getEventRange($low_date, $high_date, false),
		),
		'refresh_eval' => 'return \'' . strftime('%Y%m%d', forum_time(false)) . '\' != strftime(\'%Y%m%d\', forum_time(false)) || (!empty($modSettings[\'calendar_updated\']) && ' . time() . ' < $modSettings[\'calendar_updated\']);',
		'expires' => time() + 3600,
	);
}

/**
 * cache callback function used to retrieve the upcoming birthdays, holidays, and events within the given period, taking into account the users time offset.
 * Called from the BoardIndex to display the current day's events on the board index
 * used by the board index and SSI to show the upcoming events.
 * @param array $eventOptions
 * @return array
 */
function cache_getRecentEvents($eventOptions)
{
	// With the 'static' cached data we can calculate the user-specific data.
	$cached_data = cache_quick_get('calendar_index', 'Subs-Calendar.php', 'cache_getOffsetIndependentEvents', array($eventOptions['num_days_shown']));

	// Get the information about today (from user perspective).
	$today = getTodayInfo();

	$return_data = array(
		'calendar_holidays' => array(),
		'calendar_birthdays' => array(),
		'calendar_events' => array(),
	);

	// Set the event span to be shown in seconds.
	$days_for_index = $eventOptions['num_days_shown'] * 86400;

	// Get the current member time/date.
	$now = forum_time();

	// Holidays between now and now + days.
	for ($i = $now; $i < $now + $days_for_index; $i += 86400)
	{
		if (isset($cached_data['holidays'][strftime('%Y-%m-%d', $i)]))
			$return_data['calendar_holidays'] = array_merge($return_data['calendar_holidays'], $cached_data['holidays'][strftime('%Y-%m-%d', $i)]);
	}

	// Happy Birthday, guys and gals!
	for ($i = $now; $i < $now + $days_for_index; $i += 86400)
	{
		$loop_date = strftime('%Y-%m-%d', $i);
		if (isset($cached_data['birthdays'][$loop_date]))
		{
			foreach ($cached_data['birthdays'][$loop_date] as $index => $dummy)
				$cached_data['birthdays'][strftime('%Y-%m-%d', $i)][$index]['is_today'] = $loop_date === $today['date'];
			$return_data['calendar_birthdays'] = array_merge($return_data['calendar_birthdays'], $cached_data['birthdays'][$loop_date]);
		}
	}

	$duplicates = array();
	for ($i = $now; $i < $now + $days_for_index; $i += 86400)
	{
		// Determine the date of the current loop step.
		$loop_date = strftime('%Y-%m-%d', $i);

		// No events today? Check the next day.
		if (empty($cached_data['events'][$loop_date]))
			continue;

		// Loop through all events to add a few last-minute values.
		foreach ($cached_data['events'][$loop_date] as $ev => $event)
		{
			// Create a shortcut variable for easier access.
			$this_event = &$cached_data['events'][$loop_date][$ev];

			// Skip duplicates.
			if (isset($duplicates[$this_event['topic'] . $this_event['title']]))
			{
				unset($cached_data['events'][$loop_date][$ev]);
				continue;
			}
			else
				$duplicates[$this_event['topic'] . $this_event['title']] = true;

			// Might be set to true afterwards, depending on the permissions.
			$this_event['can_edit'] = false;
			$this_event['is_today'] = $loop_date === $today['date'];
			$this_event['date'] = $loop_date;
		}

		if (!empty($cached_data['events'][$loop_date]))
			$return_data['calendar_events'] = array_merge($return_data['calendar_events'], $cached_data['events'][$loop_date]);
	}

	// Mark the last item so that a list separator can be used in the template.
	for ($i = 0, $n = count($return_data['calendar_birthdays']); $i < $n; $i++)
		$return_data['calendar_birthdays'][$i]['is_last'] = !isset($return_data['calendar_birthdays'][$i + 1]);
	for ($i = 0, $n = count($return_data['calendar_events']); $i < $n; $i++)
		$return_data['calendar_events'][$i]['is_last'] = !isset($return_data['calendar_events'][$i + 1]);

	return array(
		'data' => $return_data,
		'expires' => time() + 3600,
		'refresh_eval' => 'return \'' . strftime('%Y%m%d', forum_time(false)) . '\' != strftime(\'%Y%m%d\', forum_time(false)) || (!empty($modSettings[\'calendar_updated\']) && ' . time() . ' < $modSettings[\'calendar_updated\']);',
		'post_retri_eval' => '
			global $context, $scripturl, $user_info;

			foreach ($cache_block[\'data\'][\'calendar_events\'] as $k => $event)
			{
				// Remove events that the user may not see or wants to ignore.
				if ((count(array_intersect($user_info[\'groups\'], $event[\'allowed_groups\'])) === 0 && !allowedTo(\'admin_forum\') && !empty($event[\'id_board\'])) || in_array($event[\'id_board\'], $user_info[\'ignoreboards\']))
					unset($cache_block[\'data\'][\'calendar_events\'][$k]);
				else
				{
					// Whether the event can be edited depends on the permissions.
					$cache_block[\'data\'][\'calendar_events\'][$k][\'can_edit\'] = allowedTo(\'calendar_edit_any\') || ($event[\'poster\'] == $user_info[\'id\'] && allowedTo(\'calendar_edit_own\'));

					// The added session code makes this URL not cachable.
					$cache_block[\'data\'][\'calendar_events\'][$k][\'modify_href\'] = $scripturl . \'?action=\' . ($event[\'topic\'] == 0 ? \'calendar;sa=post;\' : \'post;msg=\' . $event[\'msg\'] . \';topic=\' . $event[\'topic\'] . \'.0;calendar;\') . \'eventid=\' . $event[\'id\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'];
				}
			}

			if (empty($params[0][\'include_holidays\']))
				$cache_block[\'data\'][\'calendar_holidays\'] = array();
			if (empty($params[0][\'include_birthdays\']))
				$cache_block[\'data\'][\'calendar_birthdays\'] = array();
			if (empty($params[0][\'include_events\']))
				$cache_block[\'data\'][\'calendar_events\'] = array();

			$cache_block[\'data\'][\'show_calendar\'] = !empty($cache_block[\'data\'][\'calendar_holidays\']) || !empty($cache_block[\'data\'][\'calendar_birthdays\']) || !empty($cache_block[\'data\'][\'calendar_events\']);',
	);
}

/**
 * Makes sure the calendar post is valid.
 */
function validateEventPost()
{
	global $modSettings, $smcFunc;

	if (!isset($_POST['deleteevent']))
	{
		// No month?  No year?
		if (!isset($_POST['month']))
			fatal_lang_error('event_month_missing', false);
		if (!isset($_POST['year']))
			fatal_lang_error('event_year_missing', false);

		// Check the month and year...
		if ($_POST['month'] < 1 || $_POST['month'] > 12)
			fatal_lang_error('invalid_month', false);
		if ($_POST['year'] < $modSettings['cal_minyear'] || $_POST['year'] > $modSettings['cal_maxyear'])
			fatal_lang_error('invalid_year', false);
	}

	// Make sure they're allowed to post...
	isAllowedTo('calendar_post');

	if (isset($_POST['span']))
	{
		// Make sure it's turned on and not some fool trying to trick it.
		if (empty($modSettings['cal_allowspan']))
			fatal_lang_error('no_span', false);
		if ($_POST['span'] < 1 || $_POST['span'] > $modSettings['cal_maxspan'])
			fatal_lang_error('invalid_days_numb', false);
	}

	// There is no need to validate the following values if we are just deleting the event.
	if (!isset($_POST['deleteevent']))
	{
		// No day?
		if (!isset($_POST['day']))
			fatal_lang_error('event_day_missing', false);
		if (!isset($_POST['evtitle']) && !isset($_POST['subject']))
			fatal_lang_error('event_title_missing', false);
		elseif (!isset($_POST['evtitle']))
			$_POST['evtitle'] = $_POST['subject'];

		// Bad day?
		if (!checkdate($_POST['month'], $_POST['day'], $_POST['year']))
			fatal_lang_error('invalid_date', false);

		// No title?
		if ($smcFunc['htmltrim']($_POST['evtitle']) === '')
			fatal_lang_error('no_event_title', false);
		if ($smcFunc['strlen']($_POST['evtitle']) > 100)
			$_POST['evtitle'] = $smcFunc['substr']($_POST['evtitle'], 0, 100);
		$_POST['evtitle'] = str_replace(';', '', $_POST['evtitle']);
	}
}

/**
 * Get the event's poster.
 *
 * @param int $event_id
 * @return int|bool the id of the poster or false if the event was not found
 */
function getEventPoster($event_id)
{
	global $smcFunc;

	// A simple database query, how hard can that be?
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}calendar
		WHERE id_event = {int:id_event}
		LIMIT 1',
		array(
			'id_event' => $event_id,
		)
	);

	// No results, return false.
	if ($smcFunc['db_num_rows'] === 0)
		return false;

	// Grab the results and return.
	list ($poster) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	return (int) $poster;
}

/**
 * Consolidating the various INSERT statements into this function.
 * inserts the passed event information into the calendar table.
 * allows to either set a time span (in days) or an end_date.
 * does not check any permissions of any sort.
 *
 * @param array $eventOptions
 */
function insertEvent(&$eventOptions)
{
	global $smcFunc, $context;

	// Add special chars to the title.
	$eventOptions['title'] = $smcFunc['htmlspecialchars']($eventOptions['title'], ENT_QUOTES);

	// Add some sanity checking to the span.
	$eventOptions['span'] = isset($eventOptions['span']) && $eventOptions['span'] > 0 ? (int) $eventOptions['span'] : 0;

	// Make sure the start date is in ISO order.
	// @todo $year, $month, and $day are not set
	if (($num_results = sscanf($eventOptions['start_date'], '%d-%d-%d', $year, $month, $day)) !== 3)
		trigger_error('modifyEvent(): invalid start date format given', E_USER_ERROR);

	// Set the end date (if not yet given)
	// @todo $year, $month, and $day are not set
	if (!isset($eventOptions['end_date']))
		$eventOptions['end_date'] = strftime('%Y-%m-%d', mktime(0, 0, 0, $month, $day, $year) + $eventOptions['span'] * 86400);

	// If no topic and board are given, they are not linked to a topic.
	$eventOptions['board'] = isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0;
	$eventOptions['topic'] = isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0;

	$event_columns = array(
		'id_board' => 'int', 'id_topic' => 'int', 'title' => 'string-60', 'id_member' => 'int',
		'start_date' => 'date', 'end_date' => 'date',
	);
	$event_parameters = array(
		$eventOptions['board'], $eventOptions['topic'], $eventOptions['title'], $eventOptions['member'],
		$eventOptions['start_date'], $eventOptions['end_date'],
	);

	call_integration_hook('integrate_create_event', array(&$eventOptions, &$event_columns, &$event_parameters));

	// Insert the event!
	$smcFunc['db_insert']('',
		'{db_prefix}calendar',
		$event_columns,
		$event_parameters,
		array('id_event')
	);

	// Store the just inserted id_event for future reference.
	$eventOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}calendar', 'id_event');

	// If this isn't tied to a topic, we need to notify people about it.
	if (empty($eventOptions['topic']))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/EventNew-Notify.php', 'EventNew_Notify_Background', serialize(array(
				'event_title' => $eventOptions['title'],
				'event_id' => $eventOptions['id'],
				'sender_id' => $eventOptions['member'],
				'sender_name' => $eventOptions['member'] == $context['user']['id'] ? $context['user']['name'] : '',
				'time' => time(),
			)), 0),
			array('id_task')
		);
	}

	// Update the settings to show something calendar-ish was updated.
	updateSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * modifies an event.
 * allows to either set a time span (in days) or an end_date.
 * does not check any permissions of any sort.
 *
 * @param int $event_id
 * @param array $eventOptions
 */
function modifyEvent($event_id, &$eventOptions)
{
	global $smcFunc;

	// Properly sanitize the title.
	$eventOptions['title'] = $smcFunc['htmlspecialchars']($eventOptions['title'], ENT_QUOTES);

	// Scan the start date for validity and get its components.
	if (($num_results = sscanf($eventOptions['start_date'], '%d-%d-%d', $year, $month, $day)) !== 3)
		trigger_error('modifyEvent(): invalid start date format given', E_USER_ERROR);

	// Default span to 0 days.
	$eventOptions['span'] = isset($eventOptions['span']) ? (int) $eventOptions['span'] : 0;

	// Set the end date to the start date + span (if the end date wasn't already given).
	if (!isset($eventOptions['end_date']))
		$eventOptions['end_date'] = strftime('%Y-%m-%d', mktime(0, 0, 0, $month, $day, $year) + $eventOptions['span'] * 86400);

	$event_columns = array(
		'start_date' => '{date:start_date}',
		'end_date' => '{date:end_date}',
		'title' => 'SUBSTRING({string:title}, 1, 60)',
		'id_board' => '{int:id_board}',
		'id_topic' => '{int:id_topic}'
	);
	$event_parameters = array(
		'start_date' => $eventOptions['start_date'],
		'end_date' => $eventOptions['end_date'],
		'title' => $eventOptions['title'],
		'id_board' => isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0,
		'id_topic' => isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0,
	);

	// This is to prevent hooks to modify the id of the event
	$real_event_id = $event_id;
	call_integration_hook('integrate_modify_event', array($event_id, &$eventOptions, &$event_columns, &$event_parameters));

	$column_clauses = array();
	foreach ($event_columns as $col => $crit)
		$column_clauses[] = $col . ' = ' . $crit;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}calendar
		SET
			' . implode(', ', $column_clauses) . '
		WHERE id_event = {int:id_event}',
		array_merge(
			$event_parameters,
			array(
				'id_event' => $real_event_id
			)
		)
	);

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * Remove an event
 * removes an event.
 * does no permission checks.
 *
 * @param int $event_id
 */
function removeEvent($event_id)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}calendar
		WHERE id_event = {int:id_event}',
		array(
			'id_event' => $event_id,
		)
	);

	call_integration_hook('integrate_remove_event', array($event_id));

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * Gets all the events properties
 *
 * @param int $event_id
 * @return array
 */
function getEventProperties($event_id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			c.id_event, c.id_board, c.id_topic, MONTH(c.start_date) AS month,
			DAYOFMONTH(c.start_date) AS day, YEAR(c.start_date) AS year,
			(TO_DAYS(c.end_date) - TO_DAYS(c.start_date)) AS span, c.id_member, c.title,
			t.id_first_msg, t.id_member_started,
			mb.real_name, m.modified_time
		FROM {db_prefix}calendar AS c
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = c.id_topic)
			LEFT JOIN {db_prefix}members AS mb ON (mb.id_member = t.id_member_started)
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg  = t.id_first_msg)
		WHERE c.id_event = {int:id_event}',
		array(
			'id_event' => $event_id,
		)
	);

	// If nothing returned, we are in poo, poo.
	if ($smcFunc['db_num_rows']($request) === 0)
		return false;

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$return_value = array(
		'boards' => array(),
		'board' => $row['id_board'],
		'new' => 0,
		'eventid' => $event_id,
		'year' => $row['year'],
		'month' => $row['month'],
		'day' => $row['day'],
		'title' => $row['title'],
		'span' => 1 + $row['span'],
		'member' => $row['id_member'],
		'realname' => $row['real_name'],
		'sequence' => $row['modified_time'],
		'topic' => array(
			'id' => $row['id_topic'],
			'member_started' => $row['id_member_started'],
			'first_msg' => $row['id_first_msg'],
		),
	);

	$return_value['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $return_value['month'] == 12 ? 1 : $return_value['month'] + 1, 0, $return_value['month'] == 12 ? $return_value['year'] + 1 : $return_value['year']));

	return $return_value;
}

/**
 * Gets all of the holidays for the listing
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @return array
 */
function list_getHolidays($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
		FROM {db_prefix}calendar_holidays
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'sort' => $sort,
		)
	);
	$holidays = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$holidays[] = $row;
	$smcFunc['db_free_result']($request);

	return $holidays;
}

/**
 * Helper function to get the total number of holidays
 *
 * @return int
 */
function list_getNumHolidays()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}calendar_holidays',
		array(
		)
	);
	list($num_items) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return (int) $num_items;
}

/**
 * Remove a holiday from the calendar
 *
 * @param array $holiday_ids An array of
 */
function removeHolidays($holiday_ids)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}calendar_holidays
		WHERE id_holiday IN ({array_int:id_holiday})',
		array(
			'id_holiday' => $holiday_ids,
		)
	);

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

?>