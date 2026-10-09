<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Display a monthly calendar grid.
 *
 * @param string $grid_name The grid name
 * @param bool $is_mini Is this a mini grid?
 * @return void|bool Returns false if the grid doesn't exist.
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['is_mini' => false], EXTR_SKIP);
?><?php /* If the grid doesn't exist, no point in proceeding. */ ?><?php if (!isset(Utils::$context['calendar_grid_' . $grid_name])): ?><?php return false; ?><?php endif; ?><?php
// A handy little pointer variable.
$calendar_data = &Utils::$context['calendar_grid_' . $grid_name];
// Some conditions for whether or not we should show the week links *here*.
?><?php if (isset($calendar_data['show_week_links']) && ($calendar_data['show_week_links'] == 3 || (($calendar_data['show_week_links'] == 1 && $is_mini === true) || $calendar_data['show_week_links'] == 2 && $is_mini === false))): ?><?php $show_week_links = true; ?><?php else: ?><?php $show_week_links = false; ?><?php endif; ?><?php /* Assuming that we've not disabled it, show the title block! */ ?><?php if (empty($calendar_data['disable_title'])): ?>
			<div class="cat_bar">
				<h3 class="catbg centertext largetext"><?php /* Previous Link: If we're showing prev / next and it's not a mini-calendar. */ ?><?php if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false): ?>
					<span class="floatleft">
						<a href="<?= $calendar_data['previous_calendar']['href'] ?>">&#171;</a>
					</span><?php endif; ?><?php /* Next Link: if we're showing prev / next and it's not a mini-calendar. */ ?><?php if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false): ?>
					<span class="floatright">
						<a href="<?= $calendar_data['next_calendar']['href'] ?>">&#187;</a>
					</span><?php endif; ?><?php /* Arguably the most exciting part, the title! */ ?>
					<a href="<?= Config::$scripturl ?>?action=calendar;<?= Utils::$context['calendar_view'] ?>;year=<?= $calendar_data['current_year'] ?>;month=<?= $calendar_data['current_month'] ?>;day=<?= $calendar_data['current_day'] ?>"><?= Lang::getTxt('months_titles', file: 'General')[$calendar_data['current_month']] ?> <?= $calendar_data['current_year'] ?></a>
				</h3>
			</div><!-- .cat_bar --><?php endif; ?><?php /* Show the controls on main grids */ ?><?php if ($is_mini === false): ?><?php $this->subTemplate('calendar_top', ['calendar_data' => $calendar_data]); ?><?php endif; ?><?php /* Finally, the main calendar table. */ ?>
			<table class="calendar_table"><?php /* Show each day of the week. */ ?><?php if (empty($calendar_data['disable_day_titles'])): ?>
				<tr><?php /* If we're showing week links, there's an extra column ahead of the week links, so let's think ahead and be prepared! */ ?><?php if ($show_week_links === true): ?>
					<th></th><?php endif; ?><?php /* Now, loop through each actual day of the week. */ ?><?php foreach ($calendar_data['week_days'] as $day): ?>
					<th class="days" scope="col"><?= Lang::getTxt([!empty($calendar_data['short_day_titles']) || $is_mini === true ? 'days_short' : 'days', $day], file: 'General') ?></th><?php endforeach; ?>
				</tr><?php endif; ?><?php /* Our looping begins on a per-week basis. */ ?><?php foreach ($calendar_data['weeks'] as $week): ?><?php
// Some useful looping variables.
$current_month_started = false;
$count = 1;
$final_count = 1;
?>
				<tr class="days_wrapper"><?php /* This is where we add the actual week link, if enabled on this location. */ ?><?php if ($show_week_links === true): ?>
					<td class="windowbg weeks">
						<a href="<?= Config::$scripturl ?>?action=calendar;viewweek;year=<?= $calendar_data['current_year'] ?>;month=<?= $calendar_data['current_month'] ?>;day=<?= $week['days'][0]['day'] ?>" title="<?= Lang::getTxt('calendar_view_week', file: 'Calendar') ?>">&#187;</a>
					</td><?php endif; ?><?php /* Now loop through each day in the week we're on. */ ?><?php foreach ($week['days'] as $day): ?><?php
// What classes should each day inherit? Day is default.
$classes = ['days'];
?><?php if (!empty($day['day'])): ?><?php
$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';
// Additional classes are given for events, holidays, and birthdays.
?><?php foreach (['events', 'holidays', 'birthdays'] as $event_type): ?><?php if (!empty($day[$event_type])): ?><?php $classes[] = $event_type; ?><?php endif; ?><?php endforeach; ?><?php else: ?><?php $classes[] = 'disabled'; ?><?php endif; ?><?php /* Now, implode the classes for each day. */ ?>
					<td class="<?= implode(' ', $classes) ?>"><?php /* If it's within this current month, go ahead and begin. */ ?><?php if (!empty($day['day'])): ?><?php
// If it's the first day of this month and not a mini-calendar, we'll add the month title - whether short or full.
$title_prefix = !empty($day['is_first_of_month']) && Utils::$context['current_month'] == $calendar_data['current_month'] && $is_mini === false ? Lang::getTxt([!empty($calendar_data['short_month_titles']) ? 'months_short' : 'months_titles', $calendar_data['current_month']], file: 'General') . ' ' : '';
// The actual day number - be it a link, or just plain old text!
?><?php if (!empty(Config::$modSettings['cal_daysaslink']) && Utils::$context['can_post']): ?>
						<a href="<?= Config::$scripturl ?>?action=calendar;sa=post;year=<?= $calendar_data['current_year'] ?>;month=<?= $calendar_data['current_month'] ?>;day=<?= $day['day'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="day_text"><?= $title_prefix ?><?= $day['day'] ?></span></a><?php elseif ($is_mini): ?>
						<a href="<?= Config::$scripturl ?>?action=calendar;<?= Utils::$context['calendar_view'] ?>;year=<?= $calendar_data['current_year'] ?>;month=<?= $calendar_data['current_month'] ?>;day=<?= $day['day'] ?>"><span class="day_text"><?= $title_prefix ?><?= $day['day'] ?></span></a><?php else: ?>
						<span class="day_text"><?= $title_prefix ?><?= $day['day'] ?></span><?php endif; ?><?php /* A lot of stuff, we're not showing on mini-calendars to conserve space. */ ?><?php if ($is_mini === false): ?><?php /* Holidays are always fun, let's show them! */ ?><?php if (!empty($day['holidays'])): ?>
						<div class="smalltext holiday">
							<span class="label"><?= Lang::getTxt('calendar_prompt', file: 'Calendar') ?></span> <?php endif; ?><?php $holidays = []; ?><?php foreach ($day['holidays'] as $holiday): ?><span class="holiday_wrapper"><?php $holiday_string = $holiday->title; ?><?php if (empty($holiday->allday) && !empty($holiday->start_time_local) && $holiday->start_date == $day['date']): ?><?php $holiday_string .= ' <span class="event_time">' . trim(str_replace(':00 ', ' ', $holiday->start_time_local)) . '</span>'; ?><?php endif; ?><?php if (!empty($holiday->location)): ?><?php $holiday_string .= ' <span class="event_location">' . $holiday->location . '</span>'; ?><?php endif; ?><?php $holidays[] = $holiday_string; ?><?php endforeach; ?><?= implode('<span>, <span class="holiday_wrapper">', $holidays) ?></span>
						</div><?php /* Happy Birthday Dear Member! */ ?><?php if (!empty($day['birthdays'])): ?>
						<div class="smalltext">
							<span class="birthday"><?= Lang::getTxt('birthdays', file: 'Calendar') ?></span> <?php
/* Each of the birthdays has:
							id, name (person), age (if they have one set?), and is_last. (last in list?) */
$use_js_hide = empty(Utils::$context['show_all_birthdays']) && count($day['birthdays']) > 15;
$birthday_count = 0;
?><?php foreach ($day['birthdays'] as $bday): ?><a href="<?= Config::$scripturl ?>?action=profile;u=<?= $bday->member ?>"><span class="fix_rtl_names"><?= $bday->name ?></span><?= isset($bday->age) ? ' (' . $bday->age . ')' : '' ?></a><?= $bday->is_last || ($count == 10 && $use_js_hide) ? '' : ', ' ?><?php /* 9...10! Let's stop there. */ ?><?php if ($birthday_count == 10 && $use_js_hide): ?><?php /* !!TODO - Inline CSS and JavaScript should be moved. */ ?><span class="hidelink" id="bdhidelink_<?= $day['day'] ?>">...<br><a href="<?= Config::$scripturl ?>?action=calendar;month=<?= $calendar_data['current_month'] ?>;year=<?= $calendar_data['current_year'] ?>;showbd" onclick="document.getElementById('bdhide_<?= $day['day'] ?>').classList.remove('hidden'); document.getElementById('bdhidelink_<?= $day['day'] ?>').classList.add('hidden'); return false;">(<?= Lang::getTxt('calendar_click_all', file: 'Calendar') ?>)</a></span><span id="bdhide_<?= $day['day'] ?>" class="hidden">, <?php endif; ?><?php ++$birthday_count; ?><?php endforeach; ?><?php if ($use_js_hide): ?>
							</span><?php endif; ?>
						</div><!-- .smalltext --><?php endif; ?><?php /* Any special posted events? */ ?><?php if (!empty($day['events'])): ?><?php
// Sort events by start time (all day events will be listed first)
uasort(
	$day['events'],
	function ($a, $b) {
								if ($a['start_timestamp'] == $b['start_timestamp']) {
									return 0;
								}

								return ($a['start_timestamp'] < $b['start_timestamp']) ? -1 : 1;
							},
);
?>
						<div class="smalltext lefttext">
							<span class="event"><?= Lang::getTxt('events', file: 'Calendar') ?></span><br><?php
/* The events are made up of:
							title, href, is_last, can_edit (are they allowed to?), and modify_href. */
?><?php foreach ($day['events'] as $event): ?><?php $event_icons_needed = ($event->can_edit || $event->can_export) ? true : false; ?>
							<div class="event_wrapper<?= $event->start->date == $day['date'] ? ' event_starts_today' : '' ?><?= $event->end->date == $day['date'] ? ' event_ends_today' : '' ?><?= $event->allday == true ? ' allday' : '' ?><?= $event->is_selected ? ' sel_event' : '' ?>">
								<?= $event->link ?><br>
								<span class="event_time<?= empty($event_icons_needed) ? ' floatright' : '' ?>"><?php if (!empty($event->allday)): ?><?= Lang::getTxt('calendar_allday', file: 'Calendar') ?><?php elseif (!empty($event->start->time_local) && $event->start->date == $day['date']): ?><?= trim(str_replace(':00 ', ' ', $event->start->time_local)) ?><?php elseif (!empty($event->end->time_local) && $event->end->date == $day['date']): ?><?= strtolower(Lang::getTxt('ends', file: 'General')) ?> <?= trim(str_replace(':00 ', ' ', $event->end->time_local)) ?><?php endif; ?>
								</span><?php if (!empty($event->location)): ?>
								<br>
								<span class="event_location<?= empty($event_icons_needed) ? ' floatright' : '' ?>"><?= $event->location ?></span><?php endif; ?><?php if ($event->can_edit || $event->can_export): ?>
								<span class="modify_event_links"><?php /* If they can edit the event, show an icon they can click on.... */ ?><?php if ($event->can_edit): ?>
									<a class="modify_event" href="<?= $event->modify_href ?>">
										<span class="main_icons calendar_modify" title="<?= Lang::getTxt('calendar_edit', file: 'Calendar') ?>"></span>
									</a><?php endif; ?><?php /* Exporting! */ ?><?php if ($event->can_export): ?>
									<a class="modify_event" href="<?= $event->export_href ?>">
										<span class="main_icons calendar_export" title="<?= Lang::getTxt('calendar_export', file: 'Calendar') ?>"></span>
									</a><?php endif; ?>
								</span><br class="clear"><?php endif; ?>
							</div><!-- .event_wrapper --><?php endforeach; ?>
						</div><!-- .smalltext --><?php endif; ?><?php endif; ?><?php
$current_month_started = $count;
// Otherwise, assuming it's not a mini-calendar, we can show previous / next month days!
?><?php elseif ($is_mini === false): ?><?php if (empty($current_month_started) && !empty(Utils::$context['calendar_grid_prev'])): ?><a href="<?= Config::$scripturl ?>?action=calendar;viewmonth;year=<?= Utils::$context['calendar_grid_prev']['current_year'] ?>;month=<?= Utils::$context['calendar_grid_prev']['current_month'] ?>"><?= Utils::$context['calendar_grid_prev']['last_of_month'] - $calendar_data['shift']-- + 1 ?></a><?php elseif (!empty($current_month_started) && !empty(Utils::$context['calendar_grid_next'])): ?><a href="<?= Config::$scripturl ?>?action=calendar;viewmonth;year=<?= Utils::$context['calendar_grid_next']['current_year'] ?>;month=<?= Utils::$context['calendar_grid_next']['current_month'] ?>"><?= $current_month_started + 1 == $count ? Lang::getTxt([!empty($calendar_data['short_month_titles']) ? 'months_short' : 'months_titles', Utils::$context['calendar_grid_next']['current_month']], file: 'General') . ' ' : '' ?><?= $final_count++ ?></a><?php endif; ?><?php endif; ?><?php /* Close this day and increase var count. */ ?>
					</td><?php ++$count; ?><?php endforeach; ?>
				</tr><?php endforeach; ?><?php /* The end of our main table. */ ?>
			</table>