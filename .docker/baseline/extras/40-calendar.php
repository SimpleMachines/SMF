<?php

/**
 * Calendar events and holidays.
 *
 * SMF 3.0 rebuilds the calendar. Holidays stop being their own table and become
 * events; events gain UIDs and a recurrence rule; the all-day case becomes
 * explicit rather than implied by an empty time. All three migrations run
 * against tables that a fresh 2.1 install leaves almost empty -- there is a
 * default set of holidays, but no events at all, and nothing that spans more
 * than one day.
 *
 * So: four events covering the shapes the migration has to handle, and three
 * holidays covering both kinds of date 2.1 supports. A holiday dated in the sentinel year 1004
 * is 2.1's way of saying "every year", and that is exactly the case
 * HolidaysToEvents has to turn into a recurrence rule.
 *
 * Exercises: CalendarEvents, CalendarUpdates, v3_0\HolidaysToEvents,
 * v3_0\RecurringEvents, v3_0\EventUids.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '40-calendar';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc;

	$members = baseline_member_ids(5);
	$author = $members[0];

	// A topic to hang the linked event off, because an event attached to a
	// topic and a standalone event are different rows.
	$request = $smcFunc['db_query']('', '
		SELECT id_topic, id_board
		FROM {db_prefix}topics
		ORDER BY id_topic
		LIMIT {int:limit}',
		array('limit' => 1)
	);

	// Not named $topic: SMF uses that global for the topic currently being
	// viewed, and assigning an array to it at global scope is asking for
	// trouble in anything loaded later.
	$topic_row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$id_topic = empty($topic_row) ? 0 : (int) $topic_row['id_topic'];
	$id_board = empty($topic_row) ? 0 : (int) $topic_row['id_board'];

	// Fixed dates rather than dates relative to now, so a regenerated baseline
	// differs from its predecessor only where it has to.
	$events = array(
		// title, start_date, end_date, start_time, end_time, timezone, location, topic
		array('All-day maintenance window', '2026-03-14', '2026-03-14', '00:00:00', '00:00:00', '', 'Server room', 0),
		array('Release call', '2026-04-02', '2026-04-02', '14:00:00', '15:30:00', 'UTC', 'Somewhere online', 0),
		array('Topic-linked meetup', '2026-05-09', '2026-05-09', '18:00:00', '22:00:00', 'Europe/Berlin', 'The usual place', $id_topic),
		array('Multi-day conference', '2026-06-15', '2026-06-18', '09:00:00', '17:00:00', 'UTC', 'Conference centre', 0),
	);

	$event_rows = array();

	foreach ($events as $event)
	{
		list ($title, $start_date, $end_date, $start_time, $end_time, $timezone, $location, $event_topic) = $event;

		$event_rows[] = array(
			$start_date, $end_date, $event_topic === 0 ? 0 : $id_board, $event_topic,
			$title, $author, $start_time, $end_time, $timezone, $location,
		);
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}calendar',
		array(
			'start_date' => 'date', 'end_date' => 'date', 'id_board' => 'int', 'id_topic' => 'int',
			'title' => 'string-255', 'id_member' => 'int', 'start_time' => 'time', 'end_time' => 'time',
			'timezone' => 'string-80', 'location' => 'string-255',
		),
		$event_rows,
		array('id_event')
	);

	// An all-day event is one with no times at all, but the {time:...}
	// placeholder can only express a real time -- it has no way of saying NULL,
	// and the driver's isset() guard rejects a PHP null before the placeholder
	// ever sees it. So the row goes in with midnight and is emptied afterwards.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}calendar
		SET
			start_time = NULL,
			end_time = NULL
		WHERE title = {string:title}',
		array('title' => 'All-day maintenance window')
	);

	// Holidays. 2.1 marks a recurring holiday with the sentinel year 1004 --
	// getHolidayRange() rewrites the range bounds to that year to find them, and
	// it is the default for the column. Only the month and day are meaningful.
	// A holiday with a real year happens once, and SMF 3.0 has to tell the two
	// apart: turning the recurring kind into a yearly recurrence rule is the
	// whole of HolidaysToEvents.
	//
	// The year has to be exactly 1004. Any other placeholder (0004 looks just
	// as arbitrary) is simply a holiday in that year, which SMF never shows and
	// the migration would carry across as a one-off.
	$holidays = array(
		array('2026-11-27', 'Baseline one-off holiday'),
		array('1004-12-25', 'Baseline yearly holiday'),
		array('1004-01-01', 'Baseline new year'),
	);

	$smcFunc['db_insert']('insert',
		'{db_prefix}calendar_holidays',
		array('event_date' => 'date', 'title' => 'string-255'),
		$holidays,
		array('id_holiday')
	);

	// A fresh 2.1 install ships with the calendar switched off, which would
	// leave the baseline in a state no real forum is in: four events and three
	// holidays that nobody could have created, on a feature nobody can reach.
	// calendar_updated is the cache of the calendar's own date bounds; without
	// it the new rows stay invisible until something else happens to refresh it.
	updateSettings(array(
		'cal_enabled' => 1,
		'cal_showholidays' => 3,
		'cal_showevents' => 3,
		'calendar_updated' => time(),
	));

	baseline_say(sprintf(
		'%s: %d event(s), %d holiday(s)',
		$baseline_name,
		count($event_rows),
		count($holidays)
	));

	baseline_mark_applied($baseline_name);
}
