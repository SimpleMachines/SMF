<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Calendar\Event;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Template for the event options fieldset that is used when creating an event.
 *
 * Used by Calendar.template.php and Post.template.php
 */
function template_event_options()
{
	echo '
					<fieldset id="event_options">
						<legend>', Lang::$txt['calendar_event_options'], '</legend>
						<input type="hidden" name="calendar" value="1">';

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if (!empty(Utils::$context['event']->new) && !empty(Utils::$context['event']->categories)) {
		echo '
						<dl id="event_board">
							<dt class="clear">
								<label>', Lang::$txt['calendar_post_in'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="link_to_board"', (!empty(Utils::$context['event']->board) ? ' checked' : ''), ' onclick="toggleLinked(this.form);">
								<select name="board"', empty(Utils::$context['event']->board) ? ' disabled' : '', '>';

		foreach (Utils::$context['event']->categories as $category) {
			echo '
									<optgroup label="', $category['name'], '">';

			foreach ($category['boards'] as $board) {
				echo '
										<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
			}

			echo '
									</optgroup>';
		}

		echo '
								</select>
							</dd>
						</dl>';
	}

	// Basic event info
	echo '
						<dl id="event_basic_info">
							<dt class="clear">
								<label', isset(Utils::$context['post_error']['no_event']) ? ' class="error"' : '', '>', Lang::$txt['calendar_event_title'], '</label>
							</dt>
							<dd>
								<input type="text" id="evtitle" name="evtitle" maxlength="255" value="', Utils::$context['event']->selected_occurrence->title, '"', isset(Utils::$context['post_error']['no_event']) ? ' class="error"' : '', '>
							</dd>

							<dt class="clear">
								<label>', Lang::$txt['location'], '</label>
							</dt>
							<dd>
								<input type="text" name="event_location" id="event_location" maxlength="255" value="', Utils::$context['event']->selected_occurrence->location, '">
							</dd>
						</dl>';

	// Date and time info.
	echo '
						<hr class="clear">
						<dl id="event_date_and_time">
							<dt class="clear">
								<label for="allday">', Lang::$txt['calendar_allday'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="allday" id="allday"', !empty(Utils::$context['event']->allday) ? ' checked' : '', !empty(Utils::$context['event']->special_rrule) || (!Utils::$context['event']->new && !Utils::$context['event']->selected_occurrence->is_first) ? ' disabled' : '', '>

							</dd>

							<dt class="clear">
								<label>', Lang::$txt['start'], '</label>
							</dt>
							<dd>
								<input type="date" name="start_date" id="start_date" value="', Utils::$context['event']->selected_occurrence->start->format('Y-m-d'), '" class="date_input start"', !empty(Utils::$context['event']->special_rrule) ? ' disabled data-force-disabled' : '', '>
								<input type="time" name="start_time" id="start_time" value="', Utils::$context['event']->selected_occurrence->start->format('H:i'), '" class="time_input start"', !empty(Utils::$context['event']->allday) || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '', !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '', '>
							</dd>

							<dt class="clear">
								<label>', Lang::$txt['end'], '</label>
							</dt>
							<dd>
								<input type="date" name="end_date" id="end_date" value="', Utils::$context['event']->selected_occurrence->end->format('Y-m-d'), '" class="date_input end"', Config::$modSettings['cal_maxspan'] == 1 || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '', !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '', '>
								<input type="time" name="end_time" id="end_time" value="', Utils::$context['event']->selected_occurrence->end->format('H:i'), '" class="time_input end"', !empty(Utils::$context['event']->allday) || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '', !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '', '>
							</dd>

							<dt id="tz_dt" class="clear">
								<label>', Lang::$txt['calendar_timezone'], '</label>
							</dt>
							<dd id="tz_dd">';

	// Setting max-width on selects inside floating elements can be flaky,
	// so we need to calculate the width value manually.
	echo '
								<select name="tz" id="tz"', !empty(Utils::$context['event']->allday) || !empty(Utils::$context['event']->special_rrule) ? ' disabled' : '', !empty(Utils::$context['event']->special_rrule) ? ' data-force-disabled' : '', ' style="width:min(', max(array_map(fn ($tzname) => Utils::entityStrlen($tzname), Utils::$context['all_timezones'])) * 0.9, 'ch, 100%)">';

	foreach (Utils::$context['all_timezones'] as $tz => $tzname) {
		echo '
									<option', is_numeric($tz) ? ' value="" disabled' : ' value="' . $tz . '"', $tz === Utils::$context['event']->selected_occurrence->tz ? ' selected' : '', '>', $tzname, '</option>';
	}

	echo '
								</select>
							</dd>
						</dl>';

	// If this is a new event or the first occurrence of an existing event, show the RRULE stuff.
	if (Utils::$context['event']->new || Utils::$context['event']->selected_occurrence->is_first) {
		template_rrule();
	} else {
		template_occurrence_options();
	}

	echo '
					</fieldset>';
}

/**
 * Template used when editing a single occurrence of an event.
 */
function template_occurrence_options()
{
	if (Utils::$context['event']->selected_occurrence->can_affect_future) {
		echo '
						<dl id="occurrence_options">
							<dt class="clear">
								<label for="deleteevent">', Lang::$txt['calendar_repeat_delete_label'], '</label>
							</dt>
							<dd>
									<input type="checkbox" name="deleteevent" id="deleteevent" class="you_sure" data-confirm="' . Lang::$txt['calendar_confirm_occurrence_delete'] . '">
							</dd>

							<dt class="clear">
								', Lang::$txt['calendar_repeat_adjustment_label'], '
							</dt>
							<dd>
								<label>
									<input type="radio" name="affects_future" value="0" required checked>
									<span>', Lang::$txt['calendar_repeat_adjustment_this_only'], '</span>
								</label>
								<br>
								<label>
									<input type="radio" name="affects_future" value="1" required class="you_sure" data-confirm="' . Lang::$txt['calendar_repeat_adjustment_confirm'] . '">
									<span>', Lang::$txt['calendar_repeat_adjustment_this_and_future'], '</span>
								</label>
							</dd>
						</dl>';
	}

	echo '
						<dl>
							<dt class="clear"></dt>
							<dd>
								<a href="', Utils::$context['event']->modify_href, '" class="smalltext bbc_link">', Lang::$txt['calendar_repeat_adjustment_edit_first'], '</a>
							</dd>
						</dl>';
}

/**
 * Template for the recurrence rule options for events.
 */
function template_rrule()
{
	// Recurring event options.
	echo '
						<dl id="rrule_options">';

	// RRULE presets.
	echo '
							<dt class="clear">
								<label>', Lang::$txt['calendar_repeat_recurrence_label'], '</label>
							</dt>
							<dd>
								<select name="RRULE" id="rrule" class="rrule_input">';

	foreach (Utils::$context['event']->rrule_presets as $rrule => $description) {
		if (is_array($description)) {
			echo '
									<optgroup label="', $rrule, '">';

			foreach ($description as $special_rrule => $special_rrule_description) {
				echo '
											<option value="', $special_rrule, '"', $special_rrule === Utils::$context['event']->rrule_preset || $special_rrule === 'custom' && !isset(Utils::$context['event']->rrule_presets[Utils::$context['event']->rrule_preset]) ? ' selected' : '', '>', $special_rrule_description, '</option>';
			}

			echo '
									</optgroup>';
		} else {
			echo '
									<option value="', $rrule, '"', $rrule === Utils::$context['event']->rrule_preset || $rrule === 'custom' && !isset(Utils::$context['event']->rrule_presets[Utils::$context['event']->rrule_preset]) ? ' selected' : '', '>', $description, '</option>';
		}
	}

	echo '
								</select>';

	// When to end the recurrence.
	if (empty(Utils::$context['event']->special_rrule)) {
		echo '
								<span id="rrule_end" class="rrule_input_wrapper">
									<select id="end_option" class="rrule_input">
										<option value="forever">', Lang::$txt['calendar_repeat_until_options']['forever'], '</option>
										<option value="until"', !empty(Utils::$context['event']->recurrence_iterator->getRRule()->until) ? ' selected' : '', '>', Lang::$txt['calendar_repeat_until_options']['until'], '</option>
										<option value="count"', (Utils::$context['event']->recurrence_iterator->getRRule()->count ?? 0) > 1 ? ' selected' : '', '>', Lang::$txt['calendar_repeat_until_options']['count'], '</option>
									</select>
									<input type="date" name="UNTIL" id="until" class="rrule_input"', !empty(Utils::$context['event']->recurrence_iterator->getRRule()->until) ? ' value="' . Utils::$context['event']->recurrence_iterator->getRRule()->until->format('Y-m-d') . '"' : ' disabled', '>
									<input type="number" name="COUNT" id="count" class="rrule_input" min="1"', (Utils::$context['event']->recurrence_iterator->getRRule()->count ?? 0) > 1 ? ' value="' . Utils::$context['event']->recurrence_iterator->getRRule()->count . '"' : ' value="1" disabled', '>
								</span>';
	}

	echo '
							</dd>
						</dl>';


	if (!empty(Utils::$context['event']->special_rrule) || (Utils::$context['event']->new && Utils::$context['event']->type === Event::TYPE_HOLIDAY)) {
		echo '
						<dl id="special_rrule_options">
							<dt class="clear">
								<a id="special_rrule_modifier_help" href="https://stovell.noip.me/~jon/dev/index.php?action=helpadmin;help=special_rrule_modifier" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="Help"></span></a>
								<label>', Lang::$txt['calendar_repeat_special_rrule_modifier'], '</label>
							</dt>
							<dd>
								<input type="text" name="special_rrule_modifier" id="special_rrule_modifier" placeholder="', Lang::$txt['calendar_repeat_offset_examples'], '" value="' . (Utils::$context['event']->special_rrule['modifier'] ?? '') . '">
							</dd>
						</dl>';
	}


	if (empty(Utils::$context['event']->special_rrule)) {
		// Custom frequency and interval (e.g. "every 2 weeks")
		echo '
						<dl id="freq_interval_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label>', Lang::$txt['calendar_repeat_interval_label'], '</label>
							</dt>
							<dd>
								<input type="number" name="INTERVAL" value="', Utils::$context['event']->recurrence_iterator->getRRule()->interval, '" min="1" class="rrule_input" disabled>
								<select name="FREQ" id="freq" class="rrule_input">';

		foreach (Utils::$context['event']->frequency_units as $freq => $unit) {
			echo '
									<option value="', $freq, '"', Utils::$context['event']->recurrence_iterator->getRRule()->freq === $freq ? ' selected' : '', '>', $unit, '</option>';
		}

		echo '
								</select>
							</dd>
						</dl>';

		// Custom yearly options.
		echo '
						<dl id="yearly_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label>', Lang::$txt['calendar_repeat_bymonth_label'], '</label>
							</dt>
							<dd>
								<div class="rrule_input_row">';

		for ($i = 1; $i <= 12; $i++) {
			echo '
									<label class="bymonth_label">
										<input type="checkbox" name="BYMONTH[]" value="' . $i . '" id="bymonth_', $i,'" class="rrule_input" disabled>
										<span>' . Lang::$txt['months_short'][$i] . '</span>
									</label>';

			if ($i % 6 === 0) {
				echo '
								</div>
								<div class="rrule_input_row">';
			}
		}

		echo '
								</div>
							</dd>
						</dl>';

		// Custom monthly options.
		echo '
						<dl id="monthly_options" class="rrule_input_wrapper">';

		// Custom monthly: by day of month.
		echo '
							<dt class="clear" id="dt_monthly_option_type_bymonthday">
								<label>
									', Lang::$txt['calendar_repeat_bymonthday_label'], '
									<input type="radio" name="monthly_option_type" id="monthly_option_type_bymonthday"', !empty(Utils::$context['event']->recurrence_iterator->getRRule()->bymonthday) ? ' checked' : '', '>
								</label>
							</dt>
							<dd id="dd_monthly_option_type_bymonthday">
								<div id="month_bymonthday_options" class="rrule_input">
									<div class="rrule_input inline_block">
										<div class="rrule_input_row">';

		for ($i = 1; $i <= 31; $i++) {
			echo '
											<label class="bymonthday_label">
												<input type="checkbox" name="BYMONTHDAY[]" id="bymonthday_' . $i . '" value="' . $i . '"  class="rrule_input" disabled> <span>' . $i . '</span>
											</label>';

			if ($i % 7 === 0) {
				echo '
										</div>
										<div class="rrule_input_row">';
			}
		}

		echo '
										</div>
									</div>
								</div>
							</dd>';

		// Custom monthly: by weekday and offset (e.g. "the second Tuesday")
		echo '
							<dt class="clear">
								<label>
									', Lang::$txt['calendar_repeat_byday_label'], '
									<input type="radio" name="monthly_option_type" id="monthly_option_type_byday"', !empty(Utils::$context['event']->recurrence_iterator->getRRule()->byday) ? ' checked' : '', '>
								</label>
							</dt>
							<dd id="month_byday_options">
								<div class="rrule_input clear">';

		foreach (Utils::$context['event']->byday_items as $byday_item_key => $byday_item) {
			echo '
									<div>
										<select id="byday_num_select_', $byday_item_key, '" name="BYDAY_num[', $byday_item_key, ']" class="rrule_input byday_num_select" disabled>';

			foreach (Utils::$context['event']->byday_num_options as $num => $ordinal) {
				if (isset($prev_num) && ($num < 0) !== ($prev_num < 0)) {
					echo '
											<option disabled>------</option>';
			}

			echo '
											<option value="', $num, '" class="byday_num_', ($num < 0 ? 'neg' : '') . abs($num), '"', $num == $byday_item['num'] ? ' selected' : '', '>', $ordinal, '</option>';

				$prev_num = $num;
			}
			unset($prev_num);

			echo '
										</select>
										<select id="byday_name_select_', $byday_item_key, '" name="BYDAY_name[', $byday_item_key, ']" class="rrule_input byday_name_select" disabled>';

			foreach (Utils::$context['event']->sorted_weekdays as $weekday) {
				echo '
											<option value="', $weekday['abbrev'], '" class="byday_name_', $weekday['abbrev'], '"', $weekday['abbrev'] == $byday_item['name'] ? ' selected' : '', '>', $weekday['long'], '</option>';
			}

			echo '
											<option disabled>------</option>
											<option value="MO,TU,WE,TH,FR">', Lang::$txt['calendar_repeat_weekday'], '</option>
											<option value="SA,SU">', Lang::$txt['calendar_repeat_weekend_day'], '</option>
										</select>
									</div>';
		}

		echo '
								</div>
								<div>
									<a id="event_add_byday" class="rrule_input button floatnone">', Lang::$txt['calendar_repeat_add_condition'], '</a>
								</div>
								<template id="byday_template">
									<div>
										<select name="BYDAY_num[-1]" class="rrule_input byday_num_select">';

		foreach (Utils::$context['event']->byday_num_options as $num => $ordinal) {
			if (isset($prev_num) && ($num < 0) !== ($prev_num < 0)) {
				echo '
											<option disabled>------</option>';
		}

		echo '
											<option value="', $num, '" class="byday_num_', ($num < 0 ? 'neg' : '') . abs($num), '">', $ordinal, '</option>';

			$prev_num = $num;
		}
		unset($prev_num);

		echo '
										</select>
										<select name="BYDAY_name[-1]" class="rrule_input byday_name_select">';

		foreach (Utils::$context['event']->sorted_weekdays as $weekday) {
			echo '
											<option value="', $weekday['abbrev'], '" class="byday_name_', $weekday['abbrev'], '">', $weekday['long'], '</option>';
		}

		echo '
											<option disabled>------</option>
											<option value="MO,TU,WE,TH,FR">', Lang::$txt['calendar_repeat_weekday'], '</option>
											<option value="SA,SU">', Lang::$txt['calendar_repeat_weekend_day'], '</option>
										</select>
									</div>
								</template>
							</dd>
						</dl>';

		// Custom weekly options.
		echo '
						<dl id="weekly_options" class="rrule_input_wrapper">
							<dt class="clear">
								<label>', Lang::$txt['calendar_repeat_byday_label'], '</label>
							</dt>
							<dd class="rrule_input_row">';

		foreach (Utils::$context['event']->sorted_weekdays as $weekday) {
			echo '
									<label class="byday_label">
										<input type="checkbox" name="BYDAY[]" id="byday_', $weekday['abbrev'], '" value="', $weekday['abbrev'], '"  class="rrule_input"', in_array($weekday['abbrev'], Utils::$context['event']->recurrence_iterator->getRRule()->byday ?? []) ? ' checked' : '', ' disabled>
										<span>', $weekday['short'], '</span>
									</label>';
		}

		echo '
							</dd>
						</dl>';
	}

	// Advanced options.
	echo '
						<details id="advanced_options" class="rrule_input_wrapper"' . (!empty(Utils::$context['event']->recurrence_iterator->getRDates()) || !empty(Utils::$context['event']->recurrence_iterator->getEXDates()) ? ' open' : '') . '>
							<summary>', Lang::$txt['calendar_repeat_advanced_options_label'], '</summary>';

	// Arbitrary dates to add to the recurrence set.
	echo '
							<dl id="rdates">
								<dt class="clear">
									<label>', Lang::$txt['calendar_repeat_rdates_label'], '</label>
								</dt>
								<dd>
									<div id="rdate_list">';

	foreach (Utils::$context['event']->recurrence_iterator->getRDates() as $key => $rdate) {
		$rdate = new SMF\Time($rdate);
		$rdate->setTimezone(Utils::$context['event']->start->getTimezone());

		echo '
										<div>
											<input type="date" name="RDATE_date[', $key, ']" value="', $rdate->format('Y-m-d'), '" class="date_input">
											<input type="time" name="RDATE_time[', $key, ']" value="', $rdate->format('H:i'), '" class="time_input">
											<a class="main_icons delete"></a>
										</div>';
	}

	echo '
									</div>
									<div>
										<a id="event_add_rdate" data-container="rdate_list" data-inputname="RDATE" class="button floatnone">', Lang::$txt['calendar_repeat_add_condition'], '</a>
									</div>
								</dd>
							</dl>';

	// Dates to exclude from the recurrence set.
	echo '
							<dl id="exdates">
								<dt class="clear">
									<label>', Lang::$txt['calendar_repeat_exdates_label'], '</label>
								</dt>
								<dd>
									<div id="exdate_list">';

	foreach (Utils::$context['event']->recurrence_iterator->getExDates() as $key => $exdate) {
		$exdate = new SMF\Time($exdate);
		$exdate->setTimezone(Utils::$context['event']->start->getTimezone());

		echo '
										<div>
											<input type="date" name="EXDATE_date[', $key, ']" value="', $exdate->format('Y-m-d'), '" class="date_input">
											<input type="time" name="EXDATE_time[', $key, ']" value="', $exdate->format('H:i'), '" class="time_input">
											<a class="main_icons delete"></a>
										</div>';
	}

	echo '
									</div>
									<div>
										<a id="event_add_exdate" data-container="exdate_list" data-inputname="EXDATE" class="button floatnone">', Lang::$txt['calendar_repeat_add_condition'], '</a>
									</div>
									<template id="additional_dates_template">
										<div>
											<input type="date" name="" value="" class="date_input">
											<input type="time" name="" value="" class="time_input">
											<a class="main_icons delete"></a>
										</div>
									</template>
								</dd>
							</dl>';

	echo '
						</details>';
}

?>