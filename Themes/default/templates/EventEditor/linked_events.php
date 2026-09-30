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

use SMF\Calendar\Event;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template to show linked events.
 */
?><?php if (empty(Utils::$context['linked_calendar_events'])): ?><?php return; ?><?php endif; ?>
				<div class="information events">
					<ul><?php foreach (Utils::$context['linked_calendar_events'] as $event): ?>
						<li>
							<strong class="event_title"><a href="<?= Config::$scripturl ?>?action=calendar;event=<?= $event->id ?><?= (!$event->is_first ? ';start_date=' . $event->start->date : '') ?>"><?= $event->title ?></a></strong><?php if ($event->can_edit): ?> <a href="<?= $event->modify_href ?>"><span class="main_icons calendar_modify" title="<?= Lang::getTxt('calendar_edit', file: 'Calendar') ?>"></span></a><?php endif; ?><?php if ($event->can_export): ?> <a href="<?= $event->export_href ?>"><span class="main_icons calendar_export" title="<?= Lang::getTxt('calendar_export', file: 'Calendar') ?>"></span></a><?php endif; ?>
							<br><?php if (!empty($event->allday)): ?><time datetime="<?= $event->start->iso_gmdate ?>"><?= trim($event->start->date_local) ?></time><?= ($event->start->date != $event->end->date) ? ' &ndash; <time datetime="' . $event->end->iso_gmdate . '">' . trim($event->end->date_local) . '</time>' : '' ?><?php else: ?><?php /* Display event info relative to user's local timezone */ ?><time datetime="<?= $event->start->iso_gmdate ?>"><?= trim($event->start->date_local) ?>, <?= trim($event->start->time_local) ?></time> &ndash; <time datetime="<?= $event->end->iso_gmdate ?>"><?php if ($event->start->date_local != $event->end->date_local): ?><?= trim($event->end->date_local) ?>, <?php endif; ?><?= trim($event->end->time_local) ?><?php /* Display event info relative to original timezone */ ?><?php if ($event->start->date_local . $event->start->time_local != $event->start->date_orig . $event->start->time_orig): ?></time> (<time datetime="<?= $event->start->iso_gmdate ?>"><?php if ($event->start->date_orig != $event->start->date_local || $event->end->date_orig != $event->end->date_local || $event->start->date_orig != $event->end->date_orig): ?><?= trim($event->start->date_orig) ?>, <?php endif; ?><?= trim($event->start->time_orig) ?></time> &ndash; <time datetime="<?= $event->end->iso_gmdate ?>"><?php if ($event->start->date_orig != $event->end->date_orig): ?><?= trim($event->end->date_orig) ?>, <?php endif; ?><?= trim($event->end->time_orig) ?> <?= $event->start->tz_abbrev ?></time>)<?php /* Event is scheduled in the user's own timezone? Let 'em know, just to avoid confusion */ ?><?php else: ?> <?= $event->start->tz_abbrev ?></time><?php endif; ?><?php endif; ?><?php if (!empty($event->location)): ?>
							<br><?= $event->location ?><?php endif; ?><?php $rrule_description = $event->getParentEvent()->recurrence_iterator->getRRule()->getDescription($event); ?><?php if (!empty($rrule_description)): ?>
							<br><?= $rrule_description ?><?php endif; ?>
						</li><?php endforeach; ?>
					</ul>
				</div>