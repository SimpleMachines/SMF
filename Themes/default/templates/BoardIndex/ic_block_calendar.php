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
 * The calendar section of the info center
 */
?><?php /* Show information about events, birthdays, and holidays on the calendar. */ ?>
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="<?= Config::$scripturl ?>?action=calendar"><span class="main_icons calendar"></span> <?= Lang::getTxt(Utils::$context['calendar_only_today'] ? 'calendar_today' : 'calendar_upcoming', file: 'Calendar') ?></a>
				</h4>
			</div><?php /* Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P */ ?><?php if (!empty(Utils::$context['calendar_holidays'])): ?><?php $holidays = []; ?><?php foreach (Utils::$context['calendar_holidays'] as $holiday): ?><?php $holidays[] = $holiday->title . (!empty($holiday->location) ? ' (' . $holiday->location . ')' : ''); ?><?php endforeach; ?>
			<p class="inline holiday">
				<span class="label"><?= Lang::getTxt('calendar_prompt', file: 'Calendar') ?></span> <?= implode(', ', $holidays) ?>
			</p><?php endif; ?><?php /* People's birthdays. Like mine. And yours, I guess. Kidding. */ ?><?php if (!empty(Utils::$context['calendar_birthdays'])): ?>
			<p class="inline">
				<span class="birthday"><?= Lang::getTxt(Utils::$context['calendar_only_today'] ? 'birthdays' : 'birthdays_upcoming', file: 'Calendar') ?></span><?php /* Each member in calendar_birthdays has: id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?) */ ?><?php foreach (Utils::$context['calendar_birthdays'] as $bday): ?>
				<a href="<?= Config::$scripturl ?>?action=profile;u=<?= $bday->id ?>"><?= $bday->is_today ? '<strong class="fix_rtl_names">' : '' ?><?= $bday->name ?><?= $bday->is_today ? '</strong>' : '' ?><?= isset($bday->age) ? ' (' . $bday->age . ')' : '' ?></a><?= $bday->is_last ? '' : ', ' ?><?php endforeach; ?>
			</p><?php endif; ?><?php /* Events like community get-togethers. */ ?><?php if (!empty(Utils::$context['calendar_events'])): ?>
			<p class="inline">
				<span class="event"><?= Lang::getTxt(Utils::$context['calendar_only_today'] ? 'events' : 'events_upcoming', file: 'Calendar') ?></span> <?php
// Each event in calendar_events should have:
//		title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
?><?php foreach (Utils::$context['calendar_events'] as $event): ?>
				<?= $event->can_edit ? '<a href="' . $event->modify_href . '" title="' . Lang::getTxt('calendar_edit', file: 'Calendar') . '"><span class="main_icons calendar_modify"></span></a> ' : '' ?><?= $event->href == '' ? '' : '<a href="' . $event->href . '">' ?><?= $event->is_today ? '<strong>' . $event->title . '</strong>' : $event->title ?><?= $event->href == '' ? '' : '</a>' ?><?= $event->is_last ? '<br>' : ', ' ?><?php endforeach; ?>
			</p><?php endif; ?>
