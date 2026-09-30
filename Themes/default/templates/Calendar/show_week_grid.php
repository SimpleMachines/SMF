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
 * Shows a weekly grid
 *
 * @param string $grid_name The name of the grid
 * @return void|bool Returns false if the grid doesn't exist
 */
?><?php /* We might have no reason to proceed, if the variable isn't there. */ ?><?php if (!isset(Utils::$context['calendar_grid_' . $grid_name])): ?><?php return false; ?><?php endif; ?><?php
// Handy pointer.
$calendar_data = &Utils::$context['calendar_grid_' . $grid_name];
// At the very least, we have one month. Possibly two, though.
$iteration = 1;
?><?php foreach ($calendar_data['months'] as $month_data): ?><?php /* For our first iteration, we'll add a nice header! */ ?><?php if ($iteration == 1): ?>
				<div class="cat_bar">
					<h3 class="catbg centertext largetext"><?php /* Previous Week Link... */ ?><?php if (empty($calendar_data['previous_calendar']['disabled']) && !empty($calendar_data['show_next_prev'])): ?>
						<span class="floatleft">
							<a href="<?= $calendar_data['previous_week']['href'] ?>">&#171;</a>
						</span><?php endif; ?><?php /* Next Week Link... */ ?><?php if (empty($calendar_data['next_calendar']['disabled']) && !empty($calendar_data['show_next_prev'])): ?>
						<span class="floatright">
							<a href="<?= $calendar_data['next_week']['href'] ?>">&#187;</a>
						</span><?php endif; ?><?php /* "Week beginning <date>" */ ?><?php if (!empty($calendar_data['week_title'])): ?><?= $calendar_data['week_title'] ?><?php endif; ?>
					</h3>
				</div><!-- .cat_bar --><?php /* Show the controls */ ?><?php $this->subTemplate('calendar_top', ['calendar_data' => $calendar_data]); ?><?php endif; ?><?php /* Our actual month... */ ?>
				<div class="week_month_title">
					<a href="<?= Config::$scripturl ?>?action=calendar;viewmonth;month=<?= $month_data['current_month'] ?>">
						<?= Lang::getTxt(['months_titles', $month_data['current_month']], file: 'General') ?>
					</a>
				</div><?php /* The main table grid for $this week. */ ?>
				<table class="table_grid calendar_week">
					<tr>
						<th class="days" scope="col"><?= Lang::getTxt('calendar_day', file: 'Calendar') ?></th><?php if (!empty($calendar_data['show_events'])): ?>
						<th class="days" scope="col"><?= Lang::getTxt('events', file: 'Calendar') ?></th><?php endif; ?><?php if (!empty($calendar_data['show_holidays'])): ?>
						<th class="days" scope="col"><?= Lang::getTxt('calendar_prompt', file: 'Calendar') ?></th><?php endif; ?><?php if (!empty($calendar_data['show_birthdays'])): ?>
						<th class="days" scope="col"><?= Lang::getTxt('birthdays', file: 'Calendar') ?></th><?php endif; ?>
					</tr><?php /* Each day of the week. */ ?><?php foreach ($month_data['days'] as $day): ?><?php
// How should we be highlighted or otherwise not...?
$classes = ['days'];
$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';
?>
					<tr class="days_wrapper">
						<td class="<?= implode(' ', $classes) ?> act_day"><?php /* Should the day number be a link? */ ?><?php if (!empty(Config::$modSettings['cal_daysaslink']) && Utils::$context['can_post']): ?>
							<a href="<?= Config::$scripturl ?>?action=calendar;sa=post;month=<?= $month_data['current_month'] ?>;year=<?= $month_data['current_year'] ?>;day=<?= $day['day'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt(['days', $day['day_of_week']], file: 'General') ?> - <?= $day['day'] ?></a><?php else: ?><?= Lang::getTxt(['days', $day['day_of_week']], file: 'General') ?> - <?= $day['day'] ?><?php endif; ?>
						</td><?php if (!empty($calendar_data['show_events'])): ?>
						<td class="<?= implode(' ', $classes) ?><?= empty($day['events']) ? (' disabled' . (Utils::$context['can_post'] ? ' week_post' : '')) : ' events' ?> event_col" data-css-prefix="<?= Lang::getTxt('events', file: 'Calendar') ?> <?= (empty($day['events']) && empty(Utils::$context['can_post'])) ? Lang::getTxt('none', file: 'General') : '' ?>"><?php /* Show any events... */ ?><?php if (!empty($day['events'])): ?><?php
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
?><?php foreach ($day['events'] as $event): ?>
								<div class="event_wrapper"><?php $event_icons_needed = ($event->can_edit || $event->can_export) ? true : false; ?><?= $event->link ?><br>
									<span class="event_time<?= empty($event_icons_needed) ? ' floatright' : '' ?>"><?php if (!empty($event->start->time_local)): ?><?= trim($event->start->time_local) ?><?= !empty($event->end->time_local) ? ' &ndash; ' . trim($event->end->time_local) : '' ?><?php else: ?><?= Lang::getTxt('calendar_allday', file: 'Calendar') ?><?php endif; ?>
									</span><?php if (!empty($event->location)): ?><br>
									<span class="event_location<?= empty($event_icons_needed) ? ' floatright' : '' ?>"><?= $event->location ?></span><?php endif; ?><?php if (!empty($event_icons_needed)): ?> <span class="modify_event_links"><?php /* If they can edit the event, show a star they can click on.... */ ?><?php if (!empty($event->can_edit)): ?>
										<a class="modify_event" href="<?= $event->modify_href ?>">
											<span class="main_icons calendar_modify" title="<?= Lang::getTxt('calendar_edit', file: 'Calendar') ?>"></span>
										</a><?php endif; ?><?php /* Can we export? Sweet. */ ?><?php if (!empty($event->can_export)): ?>
										<a class="modify_event" href="<?= $event->export_href ?>">
											<span class="main_icons calendar_export" title="<?= Lang::getTxt('calendar_export', file: 'Calendar') ?>"></span>
										</a><?php endif; ?>
									</span><br class="clear"><?php endif; ?>
								</div><!-- .event_wrapper --><?php endforeach; ?><?php if (!empty(Utils::$context['can_post'])): ?>
								<div class="week_add_event">
									<a href="<?= Config::$scripturl ?>?action=calendar;sa=post;month=<?= $month_data['current_month'] ?>;year=<?= $month_data['current_year'] ?>;day=<?= $day['day'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('calendar_post_event', file: 'Calendar') ?></a>
								</div>
								<br class="clear"><?php endif; ?><?php else: ?><?php if (!empty(Utils::$context['can_post'])): ?>
								<div class="week_add_event">
									<a href="<?= Config::$scripturl ?>?action=calendar;sa=post;month=<?= $month_data['current_month'] ?>;year=<?= $month_data['current_year'] ?>;day=<?= $day['day'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('calendar_post_event', file: 'Calendar') ?></a>
								</div><?php endif; ?><?php endif; ?>
							</td><?php endif; ?><?php if (!empty($calendar_data['show_holidays'])): ?>
						<td class="<?= implode(' ', $classes) ?><?= !empty($day['holidays']) ? ' holidays' : ' disabled' ?> holiday_col" data-css-prefix="<?= Lang::getTxt('calendar_prompt', file: 'Calendar') ?> "><?php /* Show any holidays! */ ?><?php if (!empty($day['holidays'])): ?>
							<div class="holiday_wrapper"><?php $holidays = []; ?><?php foreach ($day['holidays'] as $holiday): ?><?php $holiday_string = $holiday->title; ?><?php if (empty($holiday->allday) && !empty($holiday->start_time_local) && $holiday->start_date == $day['date']): ?><?php $holiday_string .= ' <span class="event_time">' . trim(str_replace(':00 ', ' ', $holiday->start_time_local)) . '</span>'; ?><?php endif; ?><?php if (!empty($holiday->location)): ?><?php $holiday_string .= ' <span class="event_location">' . $holiday->location . '</span>'; ?><?php endif; ?><?php $holidays[] = $holiday_string; ?><?php endforeach; ?><?= implode('
							</div>
							<div class="holiday_wrapper">', $holidays) ?>
							</div><?php endif; ?>
						</td><?php endif; ?><?php if (!empty($calendar_data['show_birthdays'])): ?>
						<td class="<?= implode(' ', $classes) ?><?= !empty($day['birthdays']) ? ' birthdays' : ' disabled' ?> birthday_col" data-css-prefix="<?= Lang::getTxt('birthdays', file: 'Calendar') ?> "><?php /* Show any birthdays... */ ?><?php if (!empty($day['birthdays'])): ?><?php foreach ($day['birthdays'] as $member): ?>
								<a href="<?= Config::$scripturl ?>?action=profile;u=<?= $member['id'] ?>"><?= $member['name'] ?></a>
								<?= isset($member['age']) ? ' (' . $member['age'] . ')' : '' ?>
								<?= $member['is_last'] ? '' : '<br>' ?><?php endforeach; ?><?php endif; ?>
						</td><?php endif; ?>
					</tr><?php endforeach; ?><?php
// Increase iteration for loop counting.
++$iteration;
?>
				</table><?php endforeach; ?>
