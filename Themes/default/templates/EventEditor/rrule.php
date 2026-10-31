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
 * Template for the recurrence rule options for events.
 */
?>

<?php /* Recurring event options. */ ?>
						<dl id="rrule_options">
<?php /* RRULE presets. */ ?>
							<dt class="clear">
								<label><?= Lang::getTxt('calendar_repeat_recurrence_label', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<select name="RRULE" id="rrule" class="rrule_input">
<?php foreach (Utils::$context['event']->rrule_presets as $rrule => $description): ?>
<?php if (is_array($description)): ?>
									<optgroup label="<?= $rrule ?>">
<?php foreach ($description as $special_rrule => $special_rrule_description): ?>
											<option value="<?= $special_rrule ?>"<?= $special_rrule === Utils::$context['event']->rrule_preset || $special_rrule === 'custom' && !isset(Utils::$context['event']->rrule_presets[Utils::$context['event']->rrule_preset]) ? ' selected' : '' ?>><?= $special_rrule_description ?></option>
<?php endforeach; ?>
									</optgroup>
<?php else: ?>
									<option value="<?= $rrule ?>"<?= $rrule === Utils::$context['event']->rrule_preset || $rrule === 'custom' && !isset(Utils::$context['event']->rrule_presets[Utils::$context['event']->rrule_preset]) ? ' selected' : '' ?>><?= $description ?></option>
<?php endif; ?>
<?php endforeach; ?>
								</select>
<?php /* When to end the recurrence. */ ?>
<?php if (empty(Utils::$context['event']->special_rrule)): ?>
								<span id="rrule_end" class="rrule_input_wrapper">
									<select id="end_option" class="rrule_input">
										<option value="forever"><?= Lang::getTxt(['calendar_repeat_until_options', 'forever'], file: 'Calendar') ?></option>
										<option value="until"<?= !empty(Utils::$context['event']->recurrence_iterator->getRRule()->until) ? ' selected' : '' ?>><?= Lang::getTxt(['calendar_repeat_until_options', 'until'], file: 'Calendar') ?></option>
										<option value="count"<?= (Utils::$context['event']->recurrence_iterator->getRRule()->count ?? 0) > 1 ? ' selected' : '' ?>><?= Lang::getTxt(['calendar_repeat_until_options', 'count'], file: 'Calendar') ?></option>
									</select>
									<input type="date" name="UNTIL" id="until" class="rrule_input"<?= !empty(Utils::$context['event']->recurrence_iterator->getRRule()->until) ? ' value="' . Utils::$context['event']->recurrence_iterator->getRRule()->until->format('Y-m-d') . '"' : ' disabled' ?>>
									<input type="number" name="COUNT" id="count" class="rrule_input" min="1"<?= (Utils::$context['event']->recurrence_iterator->getRRule()->count ?? 0) > 1 ? ' value="' . Utils::$context['event']->recurrence_iterator->getRRule()->count . '"' : ' value="1" disabled' ?>>
								</span>
<?php endif; ?>
							</dd>
						</dl>
<?php if (!empty(Utils::$context['event']->special_rrule) || (Utils::$context['event']->new && Utils::$context['event']->type === Event::TYPE_HOLIDAY)): ?>
						<dl id="special_rrule_options">
							<dt class="clear">
								<a id="special_rrule_modifier_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=special_rrule_modifier" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="Help"></span></a>
								<label><?= Lang::getTxt('calendar_repeat_special_rrule_modifier', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<input type="text" name="special_rrule_modifier" id="special_rrule_modifier" placeholder="<?= Lang::getTxt('calendar_repeat_offset_examples', file: 'Calendar') ?>" value="<?= (Utils::$context['event']->special_rrule['modifier'] ?? '') ?>">
							</dd>
						</dl>
<?php endif; ?>
<?php if (empty(Utils::$context['event']->special_rrule)): ?>
<?php /* Custom frequency and interval (e.g. "every 2 weeks") */ ?>
						<dl id="freq_interval_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label><?= Lang::getTxt('calendar_repeat_interval_label', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<input type="number" name="INTERVAL" value="<?= Utils::$context['event']->recurrence_iterator->getRRule()->interval ?>" min="1" class="rrule_input" disabled>
								<select name="FREQ" id="freq" class="rrule_input">
<?php foreach (Utils::$context['event']->frequency_units as $freq => $unit): ?>
									<option value="<?= $freq ?>"<?= Utils::$context['event']->recurrence_iterator->getRRule()->freq === $freq ? ' selected' : '' ?>><?= $unit ?></option>
<?php endforeach; ?>
								</select>
							</dd>
						</dl>
<?php /* Custom yearly options. */ ?>
						<dl id="yearly_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label><?= Lang::getTxt('calendar_repeat_bymonth_label', file: 'Calendar') ?></label>
							</dt>
							<dd>
								<div class="rrule_input_row">
<?php for ($i = 1; $i <= 12; $i++): ?>
									<label class="bymonth_label">
										<input type="checkbox" name="BYMONTH[]" value="<?= $i ?>" id="bymonth_<?= $i ?>" class="rrule_input" disabled>
										<span><?= Lang::getTxt(['months_short', $i], file: 'General') ?></span>
									</label>
<?php if ($i % 6 === 0): ?>
								</div>
								<div class="rrule_input_row">
<?php endif; ?>
<?php endfor; ?>
								</div>
							</dd>
						</dl>
<?php /* Custom monthly options. */ ?>
						<dl id="monthly_options" class="rrule_input_wrapper">
<?php /* Custom monthly: by day of month. */ ?>
							<dt class="clear" id="dt_monthly_option_type_bymonthday">
								<label>
									<?= Lang::getTxt('calendar_repeat_bymonthday_label', file: 'Calendar') ?>
									<input type="radio" name="monthly_option_type" id="monthly_option_type_bymonthday"<?= !empty(Utils::$context['event']->recurrence_iterator->getRRule()->bymonthday) ? ' checked' : '' ?>>
								</label>
							</dt>
							<dd id="dd_monthly_option_type_bymonthday">
								<div id="month_bymonthday_options" class="rrule_input">
									<div class="rrule_input inline_block">
										<div class="rrule_input_row">
<?php for ($i = 1; $i <= 31; $i++): ?>
											<label class="bymonthday_label">
												<input type="checkbox" name="BYMONTHDAY[]" id="bymonthday_<?= $i ?>" value="<?= $i ?>"  class="rrule_input" disabled> <span><?= $i ?></span>
											</label>
<?php if ($i % 7 === 0): ?>
										</div>
										<div class="rrule_input_row">
<?php endif; ?>
<?php endfor; ?>
										</div>
									</div>
								</div>
							</dd>
<?php /* Custom monthly: by weekday and offset (e.g. "the second Tuesday") */ ?>
							<dt class="clear">
								<label>
									<?= Lang::getTxt('calendar_repeat_byday_label', file: 'Calendar') ?>
									<input type="radio" name="monthly_option_type" id="monthly_option_type_byday"<?= !empty(Utils::$context['event']->recurrence_iterator->getRRule()->byday) ? ' checked' : '' ?>>
								</label>
							</dt>
							<dd id="month_byday_options">
								<div class="rrule_input clear">
<?php foreach (Utils::$context['event']->byday_items as $byday_item_key => $byday_item): ?>
									<div>
										<select id="byday_num_select_<?= $byday_item_key ?>" name="BYDAY_num[<?= $byday_item_key ?>]" class="rrule_input byday_num_select" disabled>
<?php foreach (Utils::$context['event']->byday_num_options as $num => $ordinal): ?>
<?php if (isset($prev_num) && ($num < 0) !== ($prev_num < 0)): ?>
											<option disabled>------</option>
<?php endif; ?>
											<option value="<?= $num ?>" class="byday_num_<?= ($num < 0 ? 'neg' : '') ?><?= abs($num) ?>"<?= $num == $byday_item['num'] ? ' selected' : '' ?>><?= $ordinal ?></option>
<?php $prev_num = $num; ?>
<?php endforeach; ?>
<?php unset($prev_num); ?>
										</select>
										<select id="byday_name_select_<?= $byday_item_key ?>" name="BYDAY_name[<?= $byday_item_key ?>]" class="rrule_input byday_name_select" disabled>
<?php foreach (Utils::$context['event']->sorted_weekdays as $weekday): ?>
											<option value="<?= $weekday['abbrev'] ?>" class="byday_name_<?= $weekday['abbrev'] ?>"<?= $weekday['abbrev'] == $byday_item['name'] ? ' selected' : '' ?>><?= $weekday['long'] ?></option>
<?php endforeach; ?>
											<option disabled>------</option>
											<option value="MO,TU,WE,TH,FR"><?= Lang::getTxt('calendar_repeat_weekday', file: 'Calendar') ?></option>
											<option value="SA,SU"><?= Lang::getTxt('calendar_repeat_weekend_day', file: 'Calendar') ?></option>
										</select>
									</div>
<?php endforeach; ?>
								</div>
								<div>
									<a id="event_add_byday" class="rrule_input button floatnone"><?= Lang::getTxt('calendar_repeat_add_condition', file: 'Calendar') ?></a>
								</div>
								<template id="byday_template">
									<div>
										<select name="BYDAY_num[-1]" class="rrule_input byday_num_select">
<?php foreach (Utils::$context['event']->byday_num_options as $num => $ordinal): ?>
<?php if (isset($prev_num) && ($num < 0) !== ($prev_num < 0)): ?>
											<option disabled>------</option>
<?php endif; ?>
											<option value="<?= $num ?>" class="byday_num_<?= ($num < 0 ? 'neg' : '') ?><?= abs($num) ?>"><?= $ordinal ?></option>
<?php $prev_num = $num; ?>
<?php endforeach; ?>
<?php unset($prev_num); ?>
										</select>
										<select name="BYDAY_name[-1]" class="rrule_input byday_name_select">
<?php foreach (Utils::$context['event']->sorted_weekdays as $weekday): ?>
											<option value="<?= $weekday['abbrev'] ?>" class="byday_name_<?= $weekday['abbrev'] ?>"><?= $weekday['long'] ?></option>
<?php endforeach; ?>
											<option disabled>------</option>
											<option value="MO,TU,WE,TH,FR"><?= Lang::getTxt('calendar_repeat_weekday', file: 'Calendar') ?></option>
											<option value="SA,SU"><?= Lang::getTxt('calendar_repeat_weekend_day', file: 'Calendar') ?></option>
										</select>
									</div>
								</template>
							</dd>
						</dl>
<?php /* Custom weekly options. */ ?>
						<dl id="weekly_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label><?= Lang::getTxt('calendar_repeat_byday_label', file: 'Calendar') ?></label>
							</dt>
							<dd class="rrule_input_row">
<?php foreach (Utils::$context['event']->sorted_weekdays as $weekday): ?>
									<label class="byday_label">
										<input type="checkbox" name="BYDAY[]" id="byday_<?= $weekday['abbrev'] ?>" value="<?= $weekday['abbrev'] ?>"  class="rrule_input"<?= in_array($weekday['abbrev'], Utils::$context['event']->recurrence_iterator->getRRule()->byday ?? []) ? ' checked' : '' ?> disabled>
										<span><?= $weekday['short'] ?></span>
									</label>
<?php endforeach; ?>
							</dd>
						</dl>
<?php endif; ?>
<?php /* Advanced options. */ ?>
						<details id="advanced_options" class="rrule_input_wrapper"<?= (!empty(Utils::$context['event']->recurrence_iterator->getRDates()) || !empty(Utils::$context['event']->recurrence_iterator->getEXDates()) ? ' open' : '') ?>>
							<summary><?= Lang::getTxt('calendar_repeat_advanced_options_label', file: 'Calendar') ?></summary>
<?php /* Arbitrary dates to add to the recurrence set. */ ?>
							<dl id="rdates">
								<dt class="clear">
									<label><?= Lang::getTxt('calendar_repeat_rdates_label', file: 'Calendar') ?></label>
								</dt>
								<dd>
									<div id="rdate_list">
<?php foreach (Utils::$context['event']->recurrence_iterator->getRDates() as $key => $rdate): ?>
<?php
$rdate = new SMF\Time($rdate);
$rdate->setTimezone(Utils::$context['event']->start->getTimezone());
?>
										<div>
											<input type="date" name="RDATE_date[<?= $key ?>]" value="<?= $rdate->format('Y-m-d') ?>" class="date_input">
											<input type="time" name="RDATE_time[<?= $key ?>]" value="<?= $rdate->format('H:i') ?>" class="time_input">
											<a class="main_icons delete"></a>
										</div>
<?php endforeach; ?>
									</div>
									<div>
										<a id="event_add_rdate" data-container="rdate_list" data-inputname="RDATE" class="button floatnone"><?= Lang::getTxt('calendar_repeat_add_condition', file: 'Calendar') ?></a>
									</div>
								</dd>
							</dl>
<?php /* Dates to exclude from the recurrence set. */ ?>
							<dl id="exdates">
								<dt class="clear">
									<label><?= Lang::getTxt('calendar_repeat_exdates_label', file: 'Calendar') ?></label>
								</dt>
								<dd>
									<div id="exdate_list">
<?php foreach (Utils::$context['event']->recurrence_iterator->getExDates() as $key => $exdate): ?>
<?php
$exdate = new SMF\Time($exdate);
$exdate->setTimezone(Utils::$context['event']->start->getTimezone());
?>
										<div>
											<input type="date" name="EXDATE_date[<?= $key ?>]" value="<?= $exdate->format('Y-m-d') ?>" class="date_input">
											<input type="time" name="EXDATE_time[<?= $key ?>]" value="<?= $exdate->format('H:i') ?>" class="time_input">
											<a class="main_icons delete"></a>
										</div>
<?php endforeach; ?>
									</div>
									<div>
										<a id="event_add_exdate" data-container="exdate_list" data-inputname="EXDATE" class="button floatnone"><?= Lang::getTxt('calendar_repeat_add_condition', file: 'Calendar') ?></a>
									</div>
									<template id="additional_dates_template">
										<div>
											<input type="date" name="" value="" class="date_input">
											<input type="time" name="" value="" class="time_input">
											<a class="main_icons delete"></a>
										</div>
									</template>
								</dd>
							</dl>
						</details>