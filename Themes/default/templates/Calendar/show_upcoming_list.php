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
 * Display a list of upcoming events, birthdays, and holidays.
 *
 * @param string $grid_name The grid name
 * @return void|bool Returns false if the grid doesn't exist.
 */
?><?php /* Bail out if we have nothing to work with */ ?><?php if (!isset(Utils::$context['calendar_grid_' . $grid_name])): ?><?php return false; ?><?php endif; ?><?php
// Protect programmer sanity
$calendar_data = &Utils::$context['calendar_grid_' . $grid_name];
// Do we want a title?
?><?php if (empty($calendar_data['disable_title'])): ?>
			<div class="cat_bar">
				<h3 class="catbg centertext largetext">
					<a href="<?= Config::$scripturl ?>?action=calendar;viewlist;year=<?= $calendar_data['start_year'] ?>;month=<?= $calendar_data['start_month'] ?>;day=<?= $calendar_data['start_day'] ?>"><?= Lang::getTxt('calendar_upcoming', file: 'Calendar') ?></a>
				</h3>
			</div><?php endif; ?><?php /* Give the user some controls to work with */ ?><?php $this->subTemplate('calendar_top', ['calendar_data' => $calendar_data]); ?><?php /* Output something just so people know it's not broken */ ?><?php if (empty($calendar_data['events']) && empty($calendar_data['birthdays']) && empty($calendar_data['holidays'])): ?>
			<div class="descbox"><?= Lang::getTxt('calendar_empty', file: 'Calendar') ?></div><?php endif; ?><?php /* First, list any events */ ?><?php if (!empty($calendar_data['events'])): ?>
			<div>
				<div class="title_bar">
					<h3 class="titlebg"><?= str_replace(':', '', Lang::getTxt('events', file: 'Calendar')) ?></h3>
				</div>
				<ul><?php $first_shown = []; ?><?php foreach ($calendar_data['events'] as $date => $date_events): ?><?php foreach ($date_events as $event): ?>
					<li class="windowbg">
						<strong class="event_title"><?= $event->link ?></strong><?php if ($event->can_edit): ?> <a href="<?= $event->modify_href ?>"><span class="main_icons calendar_modify" title="<?= Lang::getTxt('calendar_edit', file: 'Calendar') ?>"></span></a><?php endif; ?><?php if ($event->can_export): ?> <a href="<?= $event->export_href ?>"><span class="main_icons calendar_export" title="<?= Lang::getTxt('calendar_export', file: 'Calendar') ?>"></span></a><?php endif; ?>
						<br><?php if (!empty($event->allday)): ?><time datetime="<?= $event->start->iso_gmdate ?>"><?= trim($event->start->date_local) ?></time><?= ($event->start->date_local < $event->last_date_local) ? ' &ndash; <time datetime="' . $event->last_iso_gmdate . '">' . trim($event->last_date_local) . '</time>' : '' ?><?php else: ?><?php /* Display event info relative to user's local timezone */ ?><time datetime="<?= $event->start->iso_gmdate ?>"><?= trim($event->start->date_local) ?>, <?= trim($event->start->time_local) ?></time> &ndash; <time datetime="<?= $event->end->iso_gmdate ?>"><?php if ($event->start->date_local != $event->end->date_local): ?><?= trim($event->end->date_local) ?>, <?php endif; ?><?= trim($event->end->time_local) ?><?php /* Display event info relative to original timezone */ ?><?php if ($event->start->date_local . $event->start->time_local != $event->start->date_orig . $event->start->time_orig): ?></time> (<time datetime="<?= $event->start->iso_gmdate ?>"><?php if ($event->start->date_orig != $event->start->date_local || $event->end->date_orig != $event->end->date_local || $event->start->date_orig != $event->end->date_orig): ?><?= trim($event->start->date_orig) ?>, <?php endif; ?><?= trim($event->start->time_orig) ?></time> &ndash; <time datetime="<?= $event->end->iso_gmdate ?>"><?php if ($event->start->date_orig != $event->end->date_orig): ?><?= trim($event->end->date_orig) ?>, <?php endif; ?><?= trim($event->end->time_orig) ?> <?= $event->tz_abbrev ?></time>)<?php /* Event is scheduled in the user's own timezone? Let 'em know, just to avoid confusion */ ?><?php else: ?> <?= $event->tz_abbrev ?></time><?php endif; ?><?php endif; ?><?php if (!empty($event->location)): ?><br><?= $event->location ?><?php endif; ?><?php
// If the first occurrence is not visible on the current page,
// we mention it in the RRULE description.
?><?php if ($event->is_first): ?><?php $first_shown[] = $event->id_event; ?><?php endif; ?><?php $rrule_description = $event->getParentEvent()->recurrence_iterator->getRRule()->getDescription($event, !in_array($event->id_event, $first_shown)); ?><?php if (!empty($rrule_description)): ?>
						<br><?= $rrule_description ?><?php endif; ?>
					</li><?php endforeach; ?><?php endforeach; ?>
				</ul>
			</div><?php endif; ?><?php /* Next, list any birthdays */ ?><?php if (!empty($calendar_data['birthdays'])): ?>
			<div>
				<div class="title_bar">
					<h3 class="titlebg"><?= str_replace(':', '', Lang::getTxt('birthdays', file: 'Calendar')) ?></h3>
				</div>
				<div class="windowbg"><?php foreach ($calendar_data['birthdays'] as $date): ?>
					<p class="inline">
						<strong><?= $date['date_local'] ?></strong>: <?php
unset($date['date_local']);
$birthdays = [];
?><?php foreach ($date as $bday): ?><?php $birthdays[] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $bday->member . '">' . $bday->name . (isset($bday->age) ? ' (' . $bday->age . ')' : '') . '</a>'; ?><?php endforeach; ?><?= implode(', ', $birthdays) ?>
					</p><?php endforeach; ?>
				</div><!-- .windowbg -->
			</div><?php endif; ?><?php /* Finally, list any holidays */ ?><?php if (!empty($calendar_data['holidays'])): ?>
			<div>
				<div class="title_bar">
					<h3 class="titlebg"><?= str_replace(':', '', Lang::getTxt('calendar_prompt', file: 'Calendar')) ?></h3>
				</div>
				<div class="windowbg">
					<p class="inline holidays"><?php foreach ($calendar_data['holidays'] as $date): ?>
						<span>
							<strong><?= $date['date_local'] ?></strong>: <?php
unset($date['date_local']);
$holidays = [];
?><?php foreach ($date as $holiday): ?><?php $holidays[] = $holiday->title . (!empty($holiday->location) ? ' (' . $holiday->location . ')' : ''); ?><?php endforeach; ?><?= implode(', ', $holidays) ?>.
						</span><?php endforeach; ?>
					</p>
				</div><!-- .windowbg -->
			</div><?php endif; ?>
