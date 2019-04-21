<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get all birthdays within the given time range.
 * finds all the birthdays in the specified range of days.
 * works with birthdays set for no year, or any other year, and respects month and year boundaries.
 *
 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
 * @return array An array of days, each of which is an array of birthday information for the context
 */
function getBirthdayRange($low_date, $high_date)
{
	global $smcFunc;

	// We need to search for any birthday in this range, and whatever year that birthday is on.
	$year_low = (int) substr($low_date, 0, 4);
	$year_high = (int) substr($high_date, 0, 4);

	if ($smcFunc['db_title'] != "PostgreSQL")
	{
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
				'year_one' => '1004',
				'year_low' => $year_low . '-%m-%d',
				'year_high' => $year_high . '-%m-%d',
				'low_date' => $low_date,
				'high_date' => $high_date,
				'max_year' => $year_high,
			)
		);
	}
	else
	{
		$result = $smcFunc['db_query']('birthday_array', '
			SELECT id_member, real_name, YEAR(birthdate) AS birth_year, birthdate
			FROM {db_prefix}members
			WHERE YEAR(birthdate) != {string:year_one}
				AND MONTH(birthdate) != {int:no_month}
				AND DAYOFMONTH(birthdate) != {int:no_day}
				AND (
					indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_low_low_date}) AND indexable_month_day({date:year_low_high_date})' . ($year_low == $year_high ? '' : '
					OR  indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_high_low_date}) AND indexable_month_day({date:year_high_high_date})') . '
				)
				AND is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
				'no_month' => 0,
				'no_day' => 0,
				'year_one' => '1004',
				'year_low' => $year_low . '-%m-%d',
				'year_high' => $year_high . '-%m-%d',
				'year_low_low_date' => $low_date,
				'year_low_high_date' => ($year_low == $year_high ? $high_date : $year_low . '-12-31'),
				'year_high_low_date' => ($year_low == $year_high ? $low_date : $year_high . '-01-01'),
				'year_high_high_date' => $high_date,
			)
		);
	}
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
			'age' => $row['birth_year'] > 1004 && $row['birth_year'] <= $age_year ? $age_year - $row['birth_year'] : null,
			'is_last' => false
		);
	}
	$smcFunc['db_free_result']($result);

	ksort($bday);

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
 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
 * @param bool $use_permissions Whether to use permissions
 * @return array Contextual information if use_permissions is true, and an array of the data needed to build that otherwise
 */
function getEventRange($low_date, $high_date, $use_permissions = true)
{
	global $scripturl, $modSettings, $user_info, $smcFunc, $context, $sourcedir;
	static $timezone_array = array();
	require_once($sourcedir . '/Subs.php');

	if (empty($timezone_array['default']))
		$timezone_array['default'] = timezone_open(date_default_timezone_get());

	$low_object = date_create($low_date);
	$high_object = date_create($high_date);

	// Find all the calendar info...
	$result = $smcFunc['db_query']('calendar_get_events', '
		SELECT
			cal.id_event, cal.title, cal.id_member, cal.id_topic, cal.id_board,
			cal.start_date, cal.end_date, cal.start_time, cal.end_time, cal.timezone, cal.location,
			b.member_groups, t.id_first_msg, t.approved, b.id_board
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

		// Get the various time and date properties for this event
		list($start, $end, $allday, $span, $tz, $tz_abbrev) = buildEventDatetimes($row);

		if (empty($timezone_array[$tz]))
			$timezone_array[$tz] = timezone_open($tz);

		// Sanity check
		if (!empty($start['error_count']) || !empty($start['warning_count']) || !empty($end['error_count']) || !empty($end['warning_count']))
			continue;

		// Get set up for the loop
		$start_object = date_create($row['start_date'] . (!$allday ? ' ' . $row['start_time'] : ''), $timezone_array[$tz]);
		$end_object = date_create($row['end_date'] . (!$allday ? ' ' . $row['end_time'] : ''), $timezone_array[$tz]);
		date_timezone_set($start_object, $timezone_array['default']);
		date_timezone_set($end_object, $timezone_array['default']);
		date_time_set($start_object, 0, 0, 0);
		date_time_set($end_object, 0, 0, 0);
		$start_date_string = date_format($start_object, 'Y-m-d');
		$end_date_string = date_format($end_object, 'Y-m-d');

		$cal_date = ($start_object >= $low_object) ? $start_object : $low_object;
		while ($cal_date <= $end_object && $cal_date <= $high_object)
		{
			$starts_today = (date_format($cal_date, 'Y-m-d') == $start_date_string);
			$ends_today = (date_format($cal_date, 'Y-m-d') == $end_date_string);

			$eventProperties = array(
				'id' => $row['id_event'],
				'title' => $row['title'],
				'year' => $start['year'],
				'month' => $start['month'],
				'day' => $start['day'],
				'hour' => !$allday ? $start['hour'] : null,
				'minute' => !$allday ? $start['minute'] : null,
				'second' => !$allday ? $start['second'] : null,
				'start_date' => $row['start_date'],
				'start_date_local' => $start['date_local'],
				'start_date_orig' => $start['date_orig'],
				'start_time' => !$allday ? $row['start_time'] : null,
				'start_time_local' => !$allday ? $start['time_local'] : null,
				'start_time_orig' => !$allday ? $start['time_orig'] : null,
				'start_timestamp' => $start['timestamp'],
				'start_datetime' => $start['datetime'],
				'start_iso_gmdate' => $start['iso_gmdate'],
				'end_year' => $end['year'],
				'end_month' => $end['month'],
				'end_day' => $end['day'],
				'end_hour' => !$allday ? $end['hour'] : null,
				'end_minute' => !$allday ? $end['minute'] : null,
				'end_second' => !$allday ? $end['second'] : null,
				'end_date' => $row['end_date'],
				'end_date_local' => $end['date_local'],
				'end_date_orig' => $end['date_orig'],
				'end_time' => !$allday ? $row['end_time'] : null,
				'end_time_local' => !$allday ? $end['time_local'] : null,
				'end_time_orig' => !$allday ? $end['time_orig'] : null,
				'end_timestamp' => $end['timestamp'],
				'end_datetime' => $end['datetime'],
				'end_iso_gmdate' => $end['iso_gmdate'],
				'allday' => $allday,
				'tz' => !$allday ? $tz : null,
				'tz_abbrev' => !$allday ? $tz_abbrev : null,
				'span' => $span,
				'is_last' => false,
				'id_board' => $row['id_board'],
				'is_selected' => !empty($context['selected_event']) && $context['selected_event'] == $row['id_event'],
				'starts_today' => $starts_today,
				'ends_today' => $ends_today,
				'location' => $row['location'],
			);

			// If we're using permissions (calendar pages?) then just ouput normal contextual style information.
			if ($use_permissions)
				$events[date_format($cal_date, 'Y-m-d')][] = array_merge($eventProperties, array(
					'href' => $row['id_board'] == 0 ? '' : $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_board'] == 0 ? $row['title'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
					'modify_href' => $scripturl . '?action=' . ($row['id_board'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $row['id_first_msg'] . ';topic=' . $row['id_topic'] . '.0;calendar;') . 'eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'can_export' => !empty($modSettings['cal_export']) ? true : false,
					'export_href' => $scripturl . '?action=calendar;sa=ical;eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				));
			// Otherwise, this is going to be cached and the VIEWER'S permissions should apply... just put together some info.
			else
				$events[date_format($cal_date, 'Y-m-d')][] = array_merge($eventProperties, array(
					'href' => $row['id_topic'] == 0 ? '' : $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_topic'] == 0 ? $row['title'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'can_edit' => false,
					'can_export' => !empty($modSettings['cal_export']) ? true : false,
					'topic' => $row['id_topic'],
					'msg' => $row['id_first_msg'],
					'poster' => $row['id_member'],
					'allowed_groups' => explode(',', $row['member_groups']),
				));

			date_add($cal_date, date_interval_create_from_date_string('1 day'));
		}
	}
	$smcFunc['db_free_result']($result);

	// If we're doing normal contextual data, go through and make things clear to the templates ;).
	if ($use_permissions)
	{
		foreach ($events as $mday => $array)
			$events[$mday][count($array) - 1]['is_last'] = true;
	}

	ksort($events);

	return $events;
}

/**
 * Get all holidays within the given time range.
 *
 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
 * @return array An array of days, which are all arrays of holiday names.
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
			'all_year_low' => '1004' . substr($low_date, 4),
			'all_year_high' => '1004' . substr($high_date, 4),
			'all_year_jan' => '1004-01-01',
			'all_year_dec' => '1004-12-31',
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

	ksort($holidays);

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
 *
 * @return array An array of info about today, based on forum time. Has 'day', 'month', 'year' and 'date' (in YYYY-MM-DD format)
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
 *
 * @param string $selected_date A date in YYYY-MM-DD format
 * @param array $calendarOptions An array of calendar options
 * @param bool $is_previous Whether this is the previous month
 * @return array A large array containing all the information needed to show a calendar grid for the given month
 */
function getCalendarGrid($selected_date, $calendarOptions, $is_previous = false)
{
	global $scripturl, $modSettings;

	$selected_object = date_create($selected_date);

	$next_object = date_create($selected_date);
	date_add($next_object, date_interval_create_from_date_string('1 month'));

	$prev_object = date_create($selected_date);
	date_sub($prev_object, date_interval_create_from_date_string('1 month'));

	// Eventually this is what we'll be returning.
	$calendarGrid = array(
		'week_days' => array(),
		'weeks' => array(),
		'short_day_titles' => !empty($calendarOptions['short_day_titles']),
		'short_month_titles' => !empty($calendarOptions['short_month_titles']),
		'current_month' => date_format($selected_object, 'n'),
		'current_year' => date_format($selected_object, 'Y'),
		'current_day' => date_format($selected_object, 'd'),
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		'show_week_links' => isset($calendarOptions['show_week_links']) ? $calendarOptions['show_week_links'] : 0,
		'previous_calendar' => array(
			'year' => date_format($prev_object, 'Y'),
			'month' => date_format($prev_object, 'n'),
			'day' => date_format($prev_object, 'd'),
			'start_date' => date_format($prev_object, 'Y-m-d'),
			'disabled' => $modSettings['cal_minyear'] > date_format($prev_object, 'Y'),
		),
		'next_calendar' => array(
			'year' => date_format($next_object, 'Y'),
			'month' => date_format($next_object, 'n'),
			'day' => date_format($next_object, 'd'),
			'start_date' => date_format($next_object, 'Y-m-d'),
			'disabled' => $modSettings['cal_maxyear'] < date_format($next_object, 'Y'),
		),
		'start_date' => timeformat(date_format($selected_object, 'U'), get_date_or_time_format('date')),
	);

	// Get today's date.
	$today = getTodayInfo();

	$first_day_object = date_create(date_format($selected_object, 'Y-m-01'));
	$last_day_object = date_create(date_format($selected_object, 'Y-m-t'));

	// Get information about this month.
	$month_info = array(
		'first_day' => array(
			'day_of_week' => date_format($first_day_object, 'w'),
			'week_num' => date_format($first_day_object, 'W'),
			'date' => date_format($first_day_object, 'Y-m-d'),
		),
		'last_day' => array(
			'day_of_month' => date_format($last_day_object, 't'),
			'date' => date_format($last_day_object, 'Y-m-d'),
		),
		'first_day_of_year' => date_format(date_create(date_format($selected_object, 'Y-01-01')), 'w'),
		'first_day_of_next_year' => date_format(date_create((date_format($selected_object, 'Y') + 1) . '-01-01'), 'w'),
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

			$date = date_format($selected_object, 'Y-m-') . sprintf('%02d', $nDay);

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
	$calendarGrid['previous_calendar']['href'] = $scripturl . '?action=calendar;viewmonth;year=' . $calendarGrid['previous_calendar']['year'] . ';month=' . $calendarGrid['previous_calendar']['month'] . ';day=' . $calendarGrid['previous_calendar']['day'];
	$calendarGrid['next_calendar']['href'] = $scripturl . '?action=calendar;viewmonth;year=' . $calendarGrid['next_calendar']['year'] . ';month=' . $calendarGrid['next_calendar']['month'] . ';day=' . $calendarGrid['previous_calendar']['day'];

	loadDatePicker('#calendar_navigation .date_input');
	loadDatePair('#calendar_navigation', 'date_input');

	return $calendarGrid;
}

/**
 * Returns the information needed to show a calendar for the given week.
 *
 * @param string $selected_date A date in YYYY-MM-DD format
 * @param array $calendarOptions An array of calendar options
 * @return array An array of information needed to display the grid for a single week on the calendar
 */
function getCalendarWeek($selected_date, $calendarOptions)
{
	global $scripturl, $modSettings, $txt;

	$selected_object = date_create($selected_date);

	// Get today's date.
	$today = getTodayInfo();

	// What is the actual "start date" for the passed day.
	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];
	$day_of_week = date_format($selected_object, 'w');
	$first_day_object = date_create($selected_date);
	if ($day_of_week != $calendarOptions['start_day'])
	{
		// Here we offset accordingly to get things to the real start of a week.
		$date_diff = $day_of_week - $calendarOptions['start_day'];
		if ($date_diff < 0)
			$date_diff += 7;

		date_sub($first_day_object, date_interval_create_from_date_string($date_diff . ' days'));
	}

	$last_day_object = date_create(date_format($first_day_object, 'Y-m-d'));
	date_add($last_day_object, date_interval_create_from_date_string('1 week'));

	$month = date_format($first_day_object, 'n');
	$year = date_format($first_day_object, 'Y');
	$day = date_format($first_day_object, 'd');

	$next_object = date_create($selected_date);
	date_add($next_object, date_interval_create_from_date_string('1 week'));

	$prev_object = date_create($selected_date);
	date_sub($prev_object, date_interval_create_from_date_string('1 week'));

	// Now start filling in the calendar grid.
	$calendarGrid = array(
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		'previous_week' => array(
			'year' => date_format($prev_object, 'Y'),
			'month' => date_format($prev_object, 'n'),
			'day' => date_format($prev_object, 'd'),
			'start_date' => date_format($prev_object, 'Y-m-d'),
			'disabled' => $modSettings['cal_minyear'] > date_format($prev_object, 'Y'),
		),
		'next_week' => array(
			'year' => date_format($next_object, 'Y'),
			'month' => date_format($next_object, 'n'),
			'day' => date_format($next_object, 'd'),
			'start_date' => date_format($next_object, 'Y-m-d'),
			'disabled' => $modSettings['cal_maxyear'] < date_format($next_object, 'Y'),
		),
		'start_date' => timeformat(date_format($selected_object, 'U'), get_date_or_time_format('date')),
	);

	// Fetch the arrays for birthdays, posted events, and holidays.
	$bday = $calendarOptions['show_birthdays'] ? getBirthdayRange(date_format($first_day_object, 'Y-m-d'), date_format($last_day_object, 'Y-m-d')) : array();
	$events = $calendarOptions['show_events'] ? getEventRange(date_format($first_day_object, 'Y-m-d'), date_format($last_day_object, 'Y-m-d')) : array();
	$holidays = $calendarOptions['show_holidays'] ? getHolidayRange(date_format($first_day_object, 'Y-m-d'), date_format($last_day_object, 'Y-m-d')) : array();

	$calendarGrid['week_title'] = sprintf($txt['calendar_week_beginning'], $txt['months_titles'][date_format($first_day_object, 'n')], date_format($first_day_object, 'j'), date_format($first_day_object, 'Y'));

	// This holds all the main data - there is at least one month!
	$calendarGrid['months'] = array();
	$current_day_object = date_create(date_format($first_day_object, 'Y-m-d'));
	for ($i = 0; $i < 7; $i++)
	{
		$current_month = date_format($current_day_object, 'n');
		$current_day = date_format($current_day_object, 'j');
		$current_date = date_format($current_day_object, 'Y-m-d');

		if (!isset($calendarGrid['months'][$current_month]))
			$calendarGrid['months'][$current_month] = array(
				'current_month' => $current_month,
				'current_year' => date_format($current_day_object, 'Y'),
				'days' => array(),
			);

		$calendarGrid['months'][$current_month]['days'][$current_day] = array(
			'day' => $current_day,
			'day_of_week' => (date_format($current_day_object, 'w') + 7 - $calendarOptions['start_day']) % 7,
			'date' => $current_date,
			'is_today' => $current_date == $today['date'],
			'holidays' => !empty($holidays[$current_date]) ? $holidays[$current_date] : array(),
			'events' => !empty($events[$current_date]) ? $events[$current_date] : array(),
			'birthdays' => !empty($bday[$current_date]) ? $bday[$current_date] : array()
		);

		date_add($current_day_object, date_interval_create_from_date_string('1 day'));
	}

	// Set the previous and the next week's links.
	$calendarGrid['previous_week']['href'] = $scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['previous_week']['year'] . ';month=' . $calendarGrid['previous_week']['month'] . ';day=' . $calendarGrid['previous_week']['day'];
	$calendarGrid['next_week']['href'] = $scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['next_week']['year'] . ';month=' . $calendarGrid['next_week']['month'] . ';day=' . $calendarGrid['next_week']['day'];

	loadDatePicker('#calendar_navigation .date_input');
	loadDatePair('#calendar_navigation', 'date_input', '');

	return $calendarGrid;
}

/**
 * Returns the information needed to show a list of upcoming events, birthdays, and holidays on the calendar.
 *
 * @param string $start_date The start of a date range in YYYY-MM-DD format
 * @param string $end_date The end of a date range in YYYY-MM-DD format
 * @param array $calendarOptions An array of calendar options
 * @return array An array of information needed to display a list of upcoming events, etc., on the calendar
 */
function getCalendarList($start_date, $end_date, $calendarOptions)
{
	global $modSettings, $user_info, $txt, $context, $sourcedir;
	require_once($sourcedir . '/Subs.php');

	// DateTime objects make life easier
	$start_object = date_create($start_date);
	$end_object = date_create($end_date);

	$calendarGrid = array(
		'start_date' => timeformat(date_format($start_object, 'U'), get_date_or_time_format('date')),
		'start_year' => date_format($start_object, 'Y'),
		'start_month' => date_format($start_object, 'm'),
		'start_day' => date_format($start_object, 'd'),
		'end_date' => timeformat(date_format($end_object, 'U'), get_date_or_time_format('date')),
		'end_year' => date_format($end_object, 'Y'),
		'end_month' => date_format($end_object, 'm'),
		'end_day' => date_format($end_object, 'd'),
	);

	$calendarGrid['birthdays'] = $calendarOptions['show_birthdays'] ? getBirthdayRange($start_date, $end_date) : array();
	$calendarGrid['holidays'] = $calendarOptions['show_holidays'] ? getHolidayRange($start_date, $end_date) : array();
	$calendarGrid['events'] = $calendarOptions['show_events'] ? getEventRange($start_date, $end_date) : array();

	// Get rid of duplicate events
	$temp = array();
	foreach ($calendarGrid['events'] as $date => $date_events)
	{
		foreach ($date_events as $event_key => $event_val)
		{
			if (in_array($event_val['id'], $temp))
				unset($calendarGrid['events'][$date][$event_key]);
			else
				$temp[] = $event_val['id'];
		}
	}

	// Give birthdays and holidays a friendly format, without the year
	$date_format = str_replace(array('%Y', '%y', '%G', '%g', '%C', '%c', '%D'), array('', '', '', '', '', '%b %d', '%m/%d'), get_date_or_time_format('date'));

	foreach (array('birthdays', 'holidays') as $type)
	{
		foreach ($calendarGrid[$type] as $date => $date_content)
		{
			$date_local = preg_replace('~(?<=\s)0+(\d)~', '$1', trim(timeformat(strtotime($date), $date_format), " \t\n\r\0\x0B,./;:<>()[]{}\\|-_=+"));

			$calendarGrid[$type][$date]['date_local'] = $date_local;
		}
	}

	loadDatePicker('#calendar_range .date_input');
	loadDatePair('#calendar_range', 'date_input', '');

	return $calendarGrid;
}

/**
 * Loads the necessary JavaScript and CSS to create a datepicker.
 *
 * @param string $selector A CSS selector for the input field(s) that the datepicker should be attached to.
 * @param string $date_format The date format to use, in strftime() format.
 */
function loadDatePicker($selector = 'input.date_input', $date_format = '')
{
	global $modSettings, $txt, $context, $user_info;

	if (empty($date_format))
		$date_format = get_date_or_time_format('date');

	// Convert to format used by datepicker
	$date_format = strtr($date_format, array(
		// Day
		'%a' => 'D', '%A' => 'DD', '%e' => 'd', '%d' => 'dd', '%j' => 'oo', '%u' => '', '%w' => '',
		// Week
		'%U' => '', '%V' => '', '%W' => '',
		// Month
		'%b' => 'M', '%B' => 'MM', '%h' => 'M', '%m' => 'mm',
		// Year
		'%C' => '', '%g' => 'y', '%G' => 'yy', '%y' => 'y', '%Y' => 'yy',
		// Time (we remove all of these)
		'%H' => '', '%k' => '', '%I' => '', '%l' => '', '%M' => '', '%p' => '', '%P' => '',
		'%r' => '', '%R' => '', '%S' => '', '%T' => '', '%X' => '', '%z' => '', '%Z' => '',
		// Time and Date Stamps
		'%c' => 'D, d M yy', '%D' => 'mm/dd/y', '%F' => 'yy-mm-dd', '%s' => '@', '%x' => 'D, d M yy',
		// Miscellaneous
		'%n' => ' ', '%t' => ' ', '%%' => '%',
	));

	loadCSSFile('jquery-ui.datepicker.css', array(), 'smf_datepicker');
	loadJavaScriptFile('jquery-ui.datepicker.min.js', array('defer' => true), 'smf_datepicker');
	addInlineJavaScript('
	$("' . $selector . '").datepicker({
		dateFormat: "' . $date_format . '",
		autoSize: true,
		isRTL: ' . ($context['right_to_left'] ? 'true' : 'false') . ',
		constrainInput: true,
		showAnim: "",
		showButtonPanel: false,
		yearRange: "' . $modSettings['cal_minyear'] . ':' . $modSettings['cal_maxyear'] . '",
		hideIfNoPrevNext: true,
		monthNames: ["' . implode('", "', $txt['months_titles']) . '"],
		monthNamesShort: ["' . implode('", "', $txt['months_short']) . '"],
		dayNames: ["' . implode('", "', $txt['days']) . '"],
		dayNamesShort: ["' . implode('", "', $txt['days_short']) . '"],
		dayNamesMin: ["' . implode('", "', $txt['days_short']) . '"],
		prevText: "' . $txt['prev_month'] . '",
		nextText: "' . $txt['next_month'] . '",
	});', true);
}

/**
 * Loads the necessary JavaScript and CSS to create a timepicker.
 *
 * @param string $selector A CSS selector for the input field(s) that the timepicker should be attached to.
 * @param string $time_format A time format in strftime format
 */
function loadTimePicker($selector = 'input.time_input', $time_format = '')
{
	global $modSettings, $txt, $context;

	if (empty($time_format))
		$time_format = get_date_or_time_format('time');

	// Format used for timepicker
	$time_format = strtr($time_format, array(
		'%H' => 'H',
		'%k' => 'G',
		'%I' => 'h',
		'%l' => 'g',
		'%M' => 'i',
		'%p' => 'A',
		'%P' => 'a',
		'%r' => 'h:i:s A',
		'%R' => 'H:i',
		'%S' => 's',
		'%T' => 'H:i:s',
		'%X' => 'H:i:s',
	));

	loadCSSFile('jquery.timepicker.css', array(), 'smf_timepicker');
	loadJavaScriptFile('jquery.timepicker.min.js', array('defer' => true), 'smf_timepicker');
	addInlineJavaScript('
	$("' . $selector . '").timepicker({
		timeFormat: "' . $time_format . '",
		showDuration: true,
		maxTime: "23:59:59",
	});', true);
}

/**
 * Loads the necessary JavaScript for Datepair.js.
 *
 * Datepair.js helps to keep date ranges sane in the UI.
 *
 * @param string $container CSS selector for the containing element of the date/time inputs to be paired.
 * @param string $date_class The CSS class of the date inputs to be paired.
 * @param string $time_class The CSS class of the time inputs to be paired.
 */
function loadDatePair($container, $date_class = '', $time_class = '')
{
	global $modSettings, $txt, $context;

	$container = (string) $container;
	$date_class = (string) $date_class;
	$time_class = (string) $time_class;

	if ($container == '')
		return;

	loadJavaScriptFile('jquery.datepair.min.js', array('defer' => true), 'smf_datepair');

	$datepair_options = '';

	// If we're not using a date input, we might as well disable these.
	if ($date_class == '')
	{
		$datepair_options .= '
		parseDate: function (el) {},
		updateDate: function (el, v) {},';
	}
	else
	{
		$datepair_options .= '
		dateClass: "' . $date_class . '",';

		// Customize Datepair to work with jQuery UI's datepicker.
		$datepair_options .= '
		parseDate: function (el) {
			var val = $(el).datepicker("getDate");
			if (!val) {
				return null;
			}
			var utc = new Date(val);
			return utc && new Date(utc.getTime() + (utc.getTimezoneOffset() * 60000));
		},
		updateDate: function (el, v) {
			$(el).datepicker("setDate", new Date(v.getTime() - (v.getTimezoneOffset() * 60000)));
		},';
	}

	// If not using a time input, disable time functions.
	if ($time_class == '')
	{
		$datepair_options .= '
		parseTime: function(input){},
		updateTime: function(input, dateObj){},
		setMinTime: function(input, dateObj){},';
	}
	else
	{
		$datepair_options .= '
		timeClass: "' . $time_class . '",';
	}

	addInlineJavaScript('
	$("' . $container . '").datepair({' . $datepair_options . "\n\t});", true);

}

/**
 * Retrieve all events for the given days, independently of the users offset.
 * cache callback function used to retrieve the birthdays, holidays, and events between now and now + days_to_index.
 * widens the search range by an extra 24 hours to support time offset shifts.
 * used by the cache_getRecentEvents function to get the information needed to calculate the events taking the users time offset into account.
 *
 * @param array $eventOptions With the keys 'num_days_shown', 'include_holidays', 'include_birthdays' and 'include_events'
 * @return array An array containing the data that was cached as well as an expression to calculate whether the data should be refreshed and when it expires
 */
function cache_getOffsetIndependentEvents($eventOptions)
{
	$days_to_index = $eventOptions['num_days_shown'];

	$low_date = strftime('%Y-%m-%d', forum_time(false) - 24 * 3600);
	$high_date = strftime('%Y-%m-%d', forum_time(false) + $days_to_index * 24 * 3600);

	return array(
		'data' => array(
			'holidays' => (!empty($eventOptions['include_holidays']) ? getHolidayRange($low_date, $high_date) : array()),
			'birthdays' => (!empty($eventOptions['include_birthdays']) ? getBirthdayRange($low_date, $high_date) : array()),
			'events' => (!empty($eventOptions['include_events']) ? getEventRange($low_date, $high_date, false) : array()),
		),
		'refresh_eval' => 'return \'' . strftime('%Y%m%d', forum_time(false)) . '\' != strftime(\'%Y%m%d\', forum_time(false)) || (!empty($modSettings[\'calendar_updated\']) && ' . time() . ' < $modSettings[\'calendar_updated\']);',
		'expires' => time() + 3600,
	);
}

/**
 * cache callback function used to retrieve the upcoming birthdays, holidays, and events within the given period, taking into account the users time offset.
 * Called from the BoardIndex to display the current day's events on the board index
 * used by the board index and SSI to show the upcoming events.
 *
 * @param array $eventOptions An array of event options.
 * @return array An array containing the info that was cached as well as a few other relevant things
 */
function cache_getRecentEvents($eventOptions)
{
	// With the 'static' cached data we can calculate the user-specific data.
	$cached_data = cache_quick_get('calendar_index', 'Subs-Calendar.php', 'cache_getOffsetIndependentEvents', array($eventOptions));

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

	if (!empty($eventOptions['include_holidays']))
	{
		// Holidays between now and now + days.
		for ($i = $now; $i < $now + $days_for_index; $i += 86400)
		{
			if (isset($cached_data['holidays'][strftime('%Y-%m-%d', $i)]))
				$return_data['calendar_holidays'] = array_merge($return_data['calendar_holidays'], $cached_data['holidays'][strftime('%Y-%m-%d', $i)]);
		}
	}

	if (!empty($eventOptions['include_birthdays']))
	{
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
	}

	if (!empty($eventOptions['include_events']))
	{
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
		// The 2.1 way
		if (isset($_POST['start_date']))
		{
			$d = date_parse($_POST['start_date']);
			if (!empty($d['error_count']) || !empty($d['warning_count']))
				fatal_lang_error('invalid_date', false);
			if (empty($d['year']))
				fatal_lang_error('event_year_missing', false);
			if (empty($d['month']))
				fatal_lang_error('event_month_missing', false);
		}
		elseif (isset($_POST['start_datetime']))
		{
			$d = date_parse($_POST['start_datetime']);
			if (!empty($d['error_count']) || !empty($d['warning_count']))
				fatal_lang_error('invalid_date', false);
			if (empty($d['year']))
				fatal_lang_error('event_year_missing', false);
			if (empty($d['month']))
				fatal_lang_error('event_month_missing', false);
		}
		// The 2.0 way
		else
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
	}

	// Make sure they're allowed to post...
	isAllowedTo('calendar_post');

	// If they want to us to calculate an end date, make sure it will fit in an acceptable range.
	if (isset($_POST['span']))
	{
		if (($_POST['span'] < 1) || (!empty($modSettings['cal_maxspan']) && $_POST['span'] > $modSettings['cal_maxspan']))
			fatal_lang_error('invalid_days_numb', false);
	}

	// There is no need to validate the following values if we are just deleting the event.
	if (!isset($_POST['deleteevent']))
	{
		// If we're doing things the 2.0 way, check the day
		if (empty($_POST['start_date']) && empty($_POST['start_datetime']))
		{
			// No day?
			if (!isset($_POST['day']))
				fatal_lang_error('event_day_missing', false);

			// Bad day?
			if (!checkdate($_POST['month'], $_POST['day'], $_POST['year']))
				fatal_lang_error('invalid_date', false);
		}

		if (!isset($_POST['evtitle']) && !isset($_POST['subject']))
			fatal_lang_error('event_title_missing', false);
		elseif (!isset($_POST['evtitle']))
			$_POST['evtitle'] = $_POST['subject'];

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
 * @param int $event_id The ID of the event
 * @return int|bool The ID of the poster or false if the event was not found
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
 * Inserts the passed event information into the calendar table.
 * Allows to either set a time span (in days) or an end_date.
 * Does not check any permissions of any sort.
 *
 * @param array $eventOptions An array of event options ('title', 'span', 'start_date', 'end_date', etc.)
 */
function insertEvent(&$eventOptions)
{
	global $smcFunc, $context;

	// Add special chars to the title.
	$eventOptions['title'] = $smcFunc['htmlspecialchars']($eventOptions['title'], ENT_QUOTES);

	$eventOptions['location'] = isset($eventOptions['location']) ? $smcFunc['htmlspecialchars']($eventOptions['location'], ENT_QUOTES) : '';

	// Set the start and end dates and times
	list($start_date, $end_date, $start_time, $end_time, $tz) = setEventStartEnd($eventOptions);

	// If no topic and board are given, they are not linked to a topic.
	$eventOptions['board'] = isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0;
	$eventOptions['topic'] = isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0;

	$event_columns = array(
		'id_board' => 'int', 'id_topic' => 'int', 'title' => 'string-60', 'id_member' => 'int',
		'start_date' => 'date', 'end_date' => 'date', 'location' => 'string-255',
	);
	$event_parameters = array(
		$eventOptions['board'], $eventOptions['topic'], $eventOptions['title'], $eventOptions['member'],
		$start_date, $end_date, $eventOptions['location'],
	);
	if (!empty($start_time) && !empty($end_time) && !empty($tz) && in_array($tz, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
	{
		$event_columns['start_time'] = 'time';
		$event_parameters[] = $start_time;
		$event_columns['end_time'] = 'time';
		$event_parameters[] = $end_time;
		$event_columns['timezone'] = 'string';
		$event_parameters[] = $tz;
	}

	call_integration_hook('integrate_create_event', array(&$eventOptions, &$event_columns, &$event_parameters));

	// Insert the event!
	$eventOptions['id'] = $smcFunc['db_insert']('',
		'{db_prefix}calendar',
		$event_columns,
		$event_parameters,
		array('id_event'),
		1
	);

	// If this isn't tied to a topic, we need to notify people about it.
	if (empty($eventOptions['topic']))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/EventNew-Notify.php', 'EventNew_Notify_Background', $smcFunc['json_encode'](array(
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
 * @param int $event_id The ID of the event
 * @param array $eventOptions An array of event information
 */
function modifyEvent($event_id, &$eventOptions)
{
	global $smcFunc;

	// Properly sanitize the title and location
	$eventOptions['title'] = $smcFunc['htmlspecialchars']($eventOptions['title'], ENT_QUOTES);
	$eventOptions['location'] = $smcFunc['htmlspecialchars']($eventOptions['location'], ENT_QUOTES);

	// Set the new start and end dates and times
	list($start_date, $end_date, $start_time, $end_time, $tz) = setEventStartEnd($eventOptions);

	$event_columns = array(
		'start_date' => '{date:start_date}',
		'end_date' => '{date:end_date}',
		'title' => 'SUBSTRING({string:title}, 1, 60)',
		'id_board' => '{int:id_board}',
		'id_topic' => '{int:id_topic}',
		'location' => 'SUBSTRING({string:location}, 1, 255)',
	);
	$event_parameters = array(
		'start_date' => $start_date,
		'end_date' => $end_date,
		'title' => $eventOptions['title'],
		'location' => $eventOptions['location'],
		'id_board' => isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0,
		'id_topic' => isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0,
	);
	if (!empty($start_time) && !empty($end_time) && !empty($tz) && in_array($tz, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
	{
		$event_columns['start_time'] = '{time:start_time}';
		$event_parameters['start_time'] = $start_time;
		$event_columns['end_time'] = '{time:end_time}';
		$event_parameters['end_time'] = $end_time;
		$event_columns['timezone'] = '{string:timezone}';
		$event_parameters['timezone'] = $tz;
	}

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

	if (empty($start_time) || empty($end_time) || empty($tz) || !in_array($tz, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}calendar
			SET start_time = NULL, end_time = NULL, timezone = NULL
			WHERE id_event = {int:id_event}',
			array(
				'id_event' => $real_event_id
			)
		);
	}

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * Remove an event
 * removes an event.
 * does no permission checks.
 *
 * @param int $event_id The ID of the event to remove
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
 * @param int $event_id The ID of the event
 * @return array An array of event information
 */
function getEventProperties($event_id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			c.id_event, c.id_board, c.id_topic, c.id_member, c.title,
			c.start_date, c.end_date, c.start_time, c.end_time, c.timezone, c.location,
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

	list($start, $end, $allday, $span, $tz, $tz_abbrev) = buildEventDatetimes($row);

	// Sanity check
	if (!empty($start['error_count']) || !empty($start['warning_count']) || !empty($end['error_count']) || !empty($end['warning_count']))
		return false;

	$return_value = array(
		'boards' => array(),
		'board' => $row['id_board'],
		'new' => 0,
		'eventid' => $event_id,
		'year' => $start['year'],
		'month' => $start['month'],
		'day' => $start['day'],
		'hour' => !$allday ? $start['hour'] : null,
		'minute' => !$allday ? $start['minute'] : null,
		'second' => !$allday ? $start['second'] : null,
		'start_date' => $row['start_date'],
		'start_date_local' => $start['date_local'],
		'start_date_orig' => $start['date_orig'],
		'start_time' => !$allday ? $row['start_time'] : null,
		'start_time_local' => !$allday ? $start['time_local'] : null,
		'start_time_orig' => !$allday ? $start['time_orig'] : null,
		'start_timestamp' => $start['timestamp'],
		'start_datetime' => $start['datetime'],
		'start_iso_gmdate' => $start['iso_gmdate'],
		'end_year' => $end['year'],
		'end_month' => $end['month'],
		'end_day' => $end['day'],
		'end_hour' => !$allday ? $end['hour'] : null,
		'end_minute' => !$allday ? $end['minute'] : null,
		'end_second' => !$allday ? $end['second'] : null,
		'end_date' => $row['end_date'],
		'end_date_local' => $end['date_local'],
		'end_date_orig' => $end['date_orig'],
		'end_time' => !$allday ? $row['end_time'] : null,
		'end_time_local' => !$allday ? $end['time_local'] : null,
		'end_time_orig' => !$allday ? $end['time_orig'] : null,
		'end_timestamp' => $end['timestamp'],
		'end_datetime' => $end['datetime'],
		'end_iso_gmdate' => $end['iso_gmdate'],
		'allday' => $allday,
		'tz' => !$allday ? $tz : null,
		'tz_abbrev' => !$allday ? $tz_abbrev : null,
		'span' => $span,
		'title' => $row['title'],
		'location' => $row['location'],
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
 * Gets an initial set of date and time values for creating a new event.
 *
 * @return array An array containing an initial set of date and time values for an event.
 */
function getNewEventDatetimes()
{
	// Ensure setEventStartEnd() has something to work with
	$now = date_create();
	$_POST['year'] = !empty($_POST['year']) ? $_POST['year'] : date_format($now, 'Y');
	$_POST['month'] = !empty($_POST['month']) ? $_POST['month'] : date_format($now, 'm');
	$_POST['day'] = !empty($_POST['day']) ? $_POST['day'] : date_format($now, 'd');
	$_POST['hour'] = !empty($_POST['hour']) ? $_POST['hour'] : date_format($now, 'H');
	$_POST['minute'] = !empty($_POST['minute']) ? $_POST['minute'] : date_format($now, 'i');
	$_POST['second'] = !empty($_POST['second']) ? $_POST['second'] : date_format($now, 's');

	// Set the basic values for the new event
	$row_keys = array('start_date', 'end_date', 'start_time', 'end_time', 'timezone');
	$row = array_combine($row_keys, setEventStartEnd());

	// And now set the full suite of values
	list($start, $end, $allday, $span, $tz, $tz_abbrev) = buildEventDatetimes($row);

	// Default theme only uses some of this info, but others might want it all
	$eventProperties = array(
		'year' => $start['year'],
		'month' => $start['month'],
		'day' => $start['day'],
		'hour' => !$allday ? $start['hour'] : null,
		'minute' => !$allday ? $start['minute'] : null,
		'second' => !$allday ? $start['second'] : null,
		'start_date' => $row['start_date'],
		'start_date_local' => $start['date_local'],
		'start_date_orig' => $start['date_orig'],
		'start_time' => !$allday ? $row['start_time'] : null,
		'start_time_local' => !$allday ? $start['time_local'] : null,
		'start_time_orig' => !$allday ? $start['time_orig'] : null,
		'start_timestamp' => $start['timestamp'],
		'start_datetime' => $start['datetime'],
		'start_iso_gmdate' => $start['iso_gmdate'],
		'end_year' => $end['year'],
		'end_month' => $end['month'],
		'end_day' => $end['day'],
		'end_hour' => !$allday ? $end['hour'] : null,
		'end_minute' => !$allday ? $end['minute'] : null,
		'end_second' => !$allday ? $end['second'] : null,
		'end_date' => $row['end_date'],
		'end_date_local' => $end['date_local'],
		'end_date_orig' => $end['date_orig'],
		'end_time' => !$allday ? $row['end_time'] : null,
		'end_time_local' => !$allday ? $end['time_local'] : null,
		'end_time_orig' => !$allday ? $end['time_orig'] : null,
		'end_timestamp' => $end['timestamp'],
		'end_datetime' => $end['datetime'],
		'end_iso_gmdate' => $end['iso_gmdate'],
		'allday' => $allday,
		'tz' => !$allday ? $tz : null,
		'tz_abbrev' => !$allday ? $tz_abbrev : null,
		'span' => $span,
	);

	return $eventProperties;
}

/**
 * Set the start and end dates and times for a posted event for insertion into the database.
 * Validates all date and times given to it.
 * Makes sure events do not exceed the maximum allowed duration (if any).
 * If passed an array that defines any time or date parameters, they will be used. Otherwise, gets the values from $_POST.
 *
 * @param array $eventOptions An array of optional time and date parameters (span, start_year, end_month, etc., etc.)
 * @return array An array containing $start_date, $end_date, $start_time, $end_time
 */
function setEventStartEnd($eventOptions = array())
{
	global $modSettings;

	// Set $span, in case we need it
	$span = isset($eventOptions['span']) ? $eventOptions['span'] : (isset($_POST['span']) ? $_POST['span'] : 0);
	if ($span > 0)
		$span = !empty($modSettings['cal_maxspan']) ? min($modSettings['cal_maxspan'], $span - 1) : $span - 1;

	// Define the timezone for this event, falling back to the default if not provided
	if (!empty($eventOptions['tz']) && in_array($eventOptions['tz'], timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
		$tz = $eventOptions['tz'];
	elseif (!empty($_POST['tz']) && in_array($_POST['tz'], timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
		$tz = $_POST['tz'];
	else
		$tz = getUserTimezone();

	// Is this supposed to be an all day event, or should it have specific start and end times?
	if (isset($eventOptions['allday']))
		$allday = $eventOptions['allday'];
	elseif (empty($_POST['allday']))
		$allday = false;
	else
		$allday = true;

	// Input might come as individual parameters...
	$start_year = isset($eventOptions['year']) ? $eventOptions['year'] : (isset($_POST['year']) ? $_POST['year'] : null);
	$start_month = isset($eventOptions['month']) ? $eventOptions['month'] : (isset($_POST['month']) ? $_POST['month'] : null);
	$start_day = isset($eventOptions['day']) ? $eventOptions['day'] : (isset($_POST['day']) ? $_POST['day'] : null);
	$start_hour = isset($eventOptions['hour']) ? $eventOptions['hour'] : (isset($_POST['hour']) ? $_POST['hour'] : null);
	$start_minute = isset($eventOptions['minute']) ? $eventOptions['minute'] : (isset($_POST['minute']) ? $_POST['minute'] : null);
	$start_second = isset($eventOptions['second']) ? $eventOptions['second'] : (isset($_POST['second']) ? $_POST['second'] : null);
	$end_year = isset($eventOptions['end_year']) ? $eventOptions['end_year'] : (isset($_POST['end_year']) ? $_POST['end_year'] : null);
	$end_month = isset($eventOptions['end_month']) ? $eventOptions['end_month'] : (isset($_POST['end_month']) ? $_POST['end_month'] : null);
	$end_day = isset($eventOptions['end_day']) ? $eventOptions['end_day'] : (isset($_POST['end_day']) ? $_POST['end_day'] : null);
	$end_hour = isset($eventOptions['end_hour']) ? $eventOptions['end_hour'] : (isset($_POST['end_hour']) ? $_POST['end_hour'] : null);
	$end_minute = isset($eventOptions['end_minute']) ? $eventOptions['end_minute'] : (isset($_POST['end_minute']) ? $_POST['end_minute'] : null);
	$end_second = isset($eventOptions['end_second']) ? $eventOptions['end_second'] : (isset($_POST['end_second']) ? $_POST['end_second'] : null);

	// ... or as datetime strings ...
	$start_string = isset($eventOptions['start_datetime']) ? $eventOptions['start_datetime'] : (isset($_POST['start_datetime']) ? $_POST['start_datetime'] : null);
	$end_string = isset($eventOptions['end_datetime']) ? $eventOptions['end_datetime'] : (isset($_POST['end_datetime']) ? $_POST['end_datetime'] : null);

	// ... or as date strings and time strings.
	$start_date_string = isset($eventOptions['start_date']) ? $eventOptions['start_date'] : (isset($_POST['start_date']) ? $_POST['start_date'] : null);
	$start_time_string = isset($eventOptions['start_time']) ? $eventOptions['start_time'] : (isset($_POST['start_time']) ? $_POST['start_time'] : null);
	$end_date_string = isset($eventOptions['end_date']) ? $eventOptions['end_date'] : (isset($_POST['end_date']) ? $_POST['end_date'] : null);
	$end_time_string = isset($eventOptions['end_time']) ? $eventOptions['end_time'] : (isset($_POST['end_time']) ? $_POST['end_time'] : null);

	// If the date and time were given in separate strings, combine them
	if (empty($start_string) && isset($start_date_string))
		$start_string = $start_date_string . (isset($start_time_string) ? ' ' . $start_time_string : '');
	if (empty($end_string) && isset($end_date_string))
		$end_string = $end_date_string . (isset($end_time_string) ? ' ' . $end_time_string : '');

	// If some form of string input was given, override individually defined options with it
	if (isset($start_string))
	{
		$start_string_parsed = date_parse($start_string);
		if (empty($start_string_parsed['error_count']) && empty($start_string_parsed['warning_count']))
		{
			if ($start_string_parsed['year'] != false)
			{
				$start_year = $start_string_parsed['year'];
				$start_month = $start_string_parsed['month'];
				$start_day = $start_string_parsed['day'];
			}
			if ($start_string_parsed['hour'] != false)
			{
				$start_hour = $start_string_parsed['hour'];
				$start_minute = $start_string_parsed['minute'];
				$start_second = $start_string_parsed['second'];
			}
		}
	}
	if (isset($end_string))
	{
		$end_string_parsed = date_parse($end_string);
		if (empty($end_string_parsed['error_count']) && empty($end_string_parsed['warning_count']))
		{
			if ($end_string_parsed['year'] != false)
			{
				$end_year = $end_string_parsed['year'];
				$end_month = $end_string_parsed['month'];
				$end_day = $end_string_parsed['day'];
			}
			if ($end_string_parsed['hour'] != false)
			{
				$end_hour = $end_string_parsed['hour'];
				$end_minute = $end_string_parsed['minute'];
				$end_second = $end_string_parsed['second'];
			}
		}
	}

	// Validate input
	$start_date_isvalid = checkdate($start_month, $start_day, $start_year);
	$end_date_isvalid = checkdate($end_month, $end_day, $end_year);

	$start_time_isset = (isset($start_hour) && isset($start_minute) && isset($start_second));
	$d = date_parse(sprintf('%02d:%02d:%02d', $start_hour, $start_minute, $start_second));
	$start_time_isvalid = ($d['error_count'] == 0 && $d['warning_count'] == 0) ? true : false;

	$end_time_isset = (isset($end_hour) && isset($end_minute) && isset($end_second));
	$d = date_parse(sprintf('%02d:%02d:%02d', $end_hour, $end_minute, $end_second));
	$end_time_isvalid = ($d['error_count'] == 0 && $d['warning_count'] == 0) ? true : false;

	// Uh-oh...
	if ($start_date_isvalid === false)
	{
		fatal_lang_error('invalid_date', false);
	}

	// Make sure we use valid values for everything
	if ($end_date_isvalid === false)
	{
		$end_year = $start_year;
		$end_month = $start_month;
		$end_day = $start_day;
	}

	if ($allday === true || $start_time_isset === false || $start_time_isvalid === false)
	{
		$allday = true;
		$start_hour = 0;
		$start_minute = 0;
		$start_second = 0;
	}

	if ($allday === true || $end_time_isvalid === false || $end_time_isset === false)
	{
		$end_hour = $start_hour;
		$end_minute = $start_minute;
		$end_second = $start_second;
	}

	// Now create our datetime objects
	$start_object = date_create(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $start_year, $start_month, $start_day, $start_hour, $start_minute, $start_second) . ' ' . $tz);
	$end_object = date_create(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $end_year, $end_month, $end_day, $end_hour, $end_minute, $end_second) . ' ' . $tz);

	// Is $end_object too early?
	if ($start_object >= $end_object)
	{
		$end_object = date_create(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $start_year, $start_month, $start_day, $start_hour, $start_minute, $start_second) . ' ' . $tz);
		if ($span > 0)
			date_add($end_object, date_interval_create_from_date_string($span . ' days'));
		else
			date_add($end_object, date_interval_create_from_date_string('1 hour'));
	}

	// Is $end_object too late?
	if (!empty($modSettings['cal_maxspan']))
	{
		$date_diff = date_diff($start_object, $end_object);
		if ($date_diff->days > $modSettings['cal_maxspan'])
		{
			if ($modSettings['cal_maxspan'] > 1)
			{
				$end_object = date_create(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $start_year, $start_month, $start_day, $start_hour, $start_minute, $start_second) . ' ' . $tz);
				date_add($end_object, date_interval_create_from_date_string($modSettings['cal_maxspan'] . ' days'));
			}
			else
				$end_object = date_create(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $start_year, $start_month, $start_day, '11', '59', '59') . ' ' . $tz);
		}
	}

	// Finally, make our strings
	$start_date = date_format($start_object, 'Y-m-d');
	$end_date = date_format($end_object, 'Y-m-d');

	if ($allday == true)
	{
		$start_time = null;
		$end_time = null;
		$tz = null;
	}
	else
	{
		$start_time = date_format($start_object, 'H:i:s');
		$end_time = date_format($end_object, 'H:i:s');
	}

	return array($start_date, $end_date, $start_time, $end_time, $tz);
}

/**
 * Helper function for getEventRange, getEventProperties, getNewEventDatetimes, etc.
 *
 * @param array $row A database row representing an event from the calendar table
 * @return array An array containing the start and end date and time properties for the event
 */
function buildEventDatetimes($row)
{
	global $sourcedir, $user_info, $txt;
	static $date_format = '', $time_format = '';

	require_once($sourcedir . '/Subs.php');
	static $timezone_array = array();

	loadLanguage('Timezones');

	// First, try to create a better date format, ignoring the "time" elements.
	if (empty($date_format))
		$date_format = get_date_or_time_format('date');

	// We want a fairly compact version of the time, but as close as possible to the user's settings.
	if (empty($time_format))
		$time_format = strtr(get_date_or_time_format('time'), array(
			'%I' => '%l',
			'%H' => '%k',
			'%S' => '',
			'%r' => '%l:%M %p',
			'%R' => '%k:%M',
			'%T' => '%l:%M',
		));

	// Should this be an all day event?
	$allday = (empty($row['start_time']) || empty($row['end_time']) || empty($row['timezone']) || !in_array($row['timezone'], timezone_identifiers_list(DateTimeZone::ALL_WITH_BC))) ? true : false;

	// How many days does this event span?
	$span = 1 + date_interval_format(date_diff(date_create($row['start_date']), date_create($row['end_date'])), '%d');

	// We need to have a defined timezone in the steps below
	if (empty($row['timezone']))
		$row['timezone'] = getUserTimezone();

	if (empty($timezone_array[$row['timezone']]))
		$timezone_array[$row['timezone']] = timezone_open($row['timezone']);

	// Get most of the standard date information for the start and end datetimes
	$start = date_parse($row['start_date'] . (!$allday ? ' ' . $row['start_time'] : ''));
	$end = date_parse($row['end_date'] . (!$allday ? ' ' . $row['end_time'] : ''));

	// But we also want more info, so make some DateTime objects we can use
	$start_object = date_create($row['start_date'] . (!$allday ? ' ' . $row['start_time'] : ''), $timezone_array[$row['timezone']]);
	$end_object = date_create($row['end_date'] . (!$allday ? ' ' . $row['end_time'] : ''), $timezone_array[$row['timezone']]);

	// Unix timestamps are good
	$start['timestamp'] = date_format($start_object, 'U');
	$end['timestamp'] = date_format($end_object, 'U');

	// Datetime string without timezone  (e.g. '2016-12-28 22:45:30')
	$start['datetime'] = date_format($start_object, 'Y-m-d H:i:s');
	$end['datetime'] = date_format($start_object, 'Y-m-d H:i:s');

	// ISO formatted datetime string, relative to UTC (e.g. '2016-12-29T05:45:30+00:00')
	$start['iso_gmdate'] = gmdate('c', $start['timestamp']);
	$end['iso_gmdate'] = gmdate('c', $end['timestamp']);

	// Strings showing the datetimes in the user's preferred format, relative to the user's time zone
	list($start['date_local'], $start['time_local']) = explode(' § ', timeformat($start['timestamp'], $date_format . ' § ' . $time_format));
	list($end['date_local'], $end['time_local']) = explode(' § ', timeformat($end['timestamp'], $date_format . ' § ' . $time_format));

	// Strings showing the datetimes in the user's preferred format, relative to the event's time zone
	list($start['date_orig'], $start['time_orig']) = explode(' § ', timeformat(strtotime(date_format($start_object, 'Y-m-d H:i:s')), $date_format . ' § ' . $time_format, 'none'));
	list($end['date_orig'], $end['time_orig']) = explode(' § ', timeformat(strtotime(date_format($end_object, 'Y-m-d H:i:s')), $date_format . ' § ' . $time_format, 'none'));

	// The time zone identifier (e.g. 'Europe/London') and abbreviation (e.g. 'GMT')
	$tz = date_format($start_object, 'e');
	$tz_abbrev = date_format($start_object, 'T');

	// If the abbreviation is just a numerical offset from UTC, make that clear.
	if (strspn($tz_abbrev, '+-') > 0)
		$tz_abbrev = 'UTC' . $tz_abbrev;

	return array($start, $end, $allday, $span, $tz, $tz_abbrev);
}

/**
 * Gets a member's selected timezone identifier directly from the database
 *
 * @param int $id_member The member id to look up. If not provided, the current user's id will be used.
 * @return string The timezone identifier string for the user's timezone.
 */
function getUserTimezone($id_member = null)
{
	global $smcFunc, $context, $user_info, $modSettings, $user_settings;
	static $member_cache = array();

	if (is_null($id_member) && $user_info['is_guest'] == false)
		$id_member = $context['user']['id'];

	//check if the cache got the data
	if (isset($id_member) && isset($member_cache[$id_member]))
	{
		return $member_cache[$id_member];
	}

	//maybe the current user is the one
	if (isset($user_settings['id_member']) && $user_settings['id_member'] == $id_member && !empty($user_settings['timezone']))
	{
		$member_cache[$id_member] = $user_settings['timezone'];
		return $user_settings['timezone'];
	}

	if (isset($id_member))
	{
		$request = $smcFunc['db_query']('', '
			SELECT timezone
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $id_member,
			)
		);
		list($timezone) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	if (empty($timezone) || !in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
		$timezone = isset($modSettings['default_timezone']) ? $modSettings['default_timezone'] : date_default_timezone_get();

	if (isset($id_member))
		$member_cache[$id_member] = $timezone;

	return $timezone;
}

/**
 * Gets all of the holidays for the listing
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of holidays, each of which is an array containing the id, year, month, day and title of the holiday
 */
function list_getHolidays($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
		FROM {db_prefix}calendar_holidays
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
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
 * @return int The total number of holidays
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
 * @param array $holiday_ids An array of IDs of holidays to delete
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