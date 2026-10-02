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
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for entering info about a new event.
 */
?><?php /* If user cannot create new events, skip this. */ ?><?php if (!User::$me->allowedTo('calendar_post')): ?><?php return; ?><?php endif; ?><?php /* Basic event info */ ?>
					<div id="event_new">
						<dl id="event_basic_info">
							<dt class="clear">
								<label<?= isset(Utils::$context['post_error']['no_event']) ? ' class="error"' : '' ?>><?= Lang::getTxt('calendar_event_title', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<input type="text" id="evtitle" name="evtitle" maxlength="255" value="<?= Utils::$context['event']->selected_occurrence->title ?>"<?= isset(Utils::$context['post_error']['no_event']) ? ' class="error"' : '' ?>>
							</dd>

							<dt class="clear">
								<label><?= Lang::getTxt('location', file: 'General') ?></label>
							</dt>
							<dd>
								<input type="text" name="event_location" id="event_location" maxlength="255" value="<?= Utils::$context['event']->selected_occurrence->location ?>">
							</dd>
						</dl><?php /* Date and time info. */ ?>
						<hr class="clear">
						<dl id="event_date_and_time">
							<dt class="clear">
								<label for="allday"><?= Lang::getTxt('calendar_allday', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<input type="checkbox" name="allday" id="allday"<?= !empty(Utils::$context['event']->allday) ? ' checked' : '' ?><?= !empty(Utils::$context['event']->special_rrule) || (!Utils::$context['event']->new && !Utils::$context['event']->selected_occurrence->is_first) ? ' disabled' : '' ?>>

							</dd>

							<dt class="clear">
								<label><?= Lang::getTxt('start', file: 'General') ?></label>
							</dt>
							<dd>
								<input type="date" name="start_date" id="start_date" value="<?= Utils::$context['event']->selected_occurrence->start->format('Y-m-d') ?>" class="date_input start"<?= !empty(Utils::$context['event']->special_rrule) ? ' disabled data-force-disabled' : '' ?>>
								<input type="time" name="start_time" id="start_time" value="<?= Utils::$context['event']->selected_occurrence->start->format('H:i') ?>" class="time_input start"<?= !empty(Utils::$context['event']->allday) || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '' ?><?= !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '' ?>>
							</dd>

							<dt class="clear">
								<label><?= Lang::getTxt('end', file: 'General') ?></label>
							</dt>
							<dd>
								<input type="date" name="end_date" id="end_date" value="<?= Utils::$context['event']->selected_occurrence->end->format('Y-m-d') ?>" class="date_input end"<?= Config::$modSettings['cal_maxspan'] == 1 || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '' ?><?= !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '' ?>>
								<input type="time" name="end_time" id="end_time" value="<?= Utils::$context['event']->selected_occurrence->end->format('H:i') ?>" class="time_input end"<?= !empty(Utils::$context['event']->allday) || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '' ?><?= !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '' ?>>
							</dd>

							<dt id="tz_dt" class="clear">
								<label><?= Lang::getTxt('calendar_timezone', file: 'Calendar') ?></label>
							</dt>
							<dd id="tz_dd"><?php $this->subTemplate('timezone_select', ['name' => 'tz', 'selection' => Utils::$context['event']->selected_occurrence->tzid, 'disabled' => !empty(Utils::$context['event']->special_rrule) ? 2 : (int) Utils::$context['event']->allday]); ?>
							</dd>
						</dl><?php /* If this is a new event or the first occurrence of an existing event, show the RRULE stuff. */ ?><?php if (Utils::$context['event']->new || Utils::$context['event']->selected_occurrence->is_first): ?><?php $this->subTemplate('rrule'); ?><?php else: ?><?php $this->subTemplate('occurrence_options'); ?><?php endif; ?>
					</div>