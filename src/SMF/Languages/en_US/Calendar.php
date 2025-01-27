<?php

// Version: 3.0 Alpha 2; General

$txt['birthdays'] = 'Birthdays:';
$txt['events'] = 'Events:';
$txt['birthdays_upcoming'] = 'Upcoming Birthdays:';
$txt['events_upcoming'] = 'Upcoming Events:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = 'Holidays:';
$txt['calendar_month'] = 'Month';
$txt['calendar_year'] = 'Year';
$txt['calendar_day'] = 'Day';
$txt['calendar_event_title'] = 'Title';
$txt['calendar_event_options'] = 'Event Options';
$txt['calendar_post_in'] = 'Post in';
$txt['calendar_edit'] = 'Edit Event';
$txt['calendar_export'] = 'Export Event';
$txt['calendar_subscribe'] = 'Subscribe';
$txt['calendar_subscribe_desc'] = 'Shows upcoming events in your calendar app.';
$txt['calendar_subscribe_url_copied'] = 'The calendar subscription URL has been copied to the clipboard. Paste it into your calendar app to subscribe.';
$txt['calendar_download'] = 'Download';
$txt['calendar_download_desc'] = 'Exports a copy of the currently visible events.';
$txt['calendar_view_week'] = 'View Week';
$txt['event_delete_confirm'] = 'Delete this event?';
$txt['event_delete'] = 'Delete Event';
$txt['calendar_post_event'] = 'Post Event';
$txt['calendar'] = 'Calendar';
$txt['calendar_link'] = 'Add Event';
$txt['calendar_unlink'] = 'Unlink event';
$txt['calendar_link_to'] = 'Link to';
$txt['calendar_only'] = 'Calendar only';
$txt['calendar_event_new'] = 'New event';
$txt['calendar_event_existing'] = 'Existing event';
$txt['calendar_topic_existing'] = 'Existing topic';
$txt['calendar_link_event_id'] = 'Event ID';
$txt['calendar_link_topic_id'] = 'Topic ID';
$txt['calendar_upcoming'] = 'Upcoming Calendar';
$txt['calendar_today'] = 'Today’s Calendar';
$txt['calendar_week'] = 'Week';
$txt['calendar_week_beginning'] = 'Week beginning {date}';
$txt['calendar_numb_days'] = 'Number of Days';
$txt['calendar_how_edit'] = 'how do you edit these events?';
$txt['calendar_link_event'] = 'Link Event To Post';
$txt['calendar_confirm_delete'] = 'Are you sure you want to delete this event?';
$txt['calendar_linked_events'] = 'Linked Events';
$txt['calendar_click_all'] = 'Show all';
$txt['calendar_allday'] = 'All day';
$txt['calendar_timezone'] = 'Time zone';
$txt['calendar_list'] = 'List';
$txt['calendar_empty'] = 'There are no events to display.';

$txt['calendar_repeat_recurrence_label'] = 'Repeats';
$txt['calendar_repeat_interval_label'] = 'Every';
$txt['calendar_repeat_bymonthday_label'] = 'On';
$txt['calendar_repeat_byday_label'] = 'On';
$txt['calendar_repeat_bymonth_label'] = 'In';
$txt['calendar_repeat_rrule_presets'] = ['never' => 'Never', 'FREQ=DAILY' => 'Every day', 'FREQ=WEEKLY' => 'Every week', 'FREQ=MONTHLY' => 'Every month', 'FREQ=YEARLY' => 'Every year', 'custom' => 'Custom...'];
$txt['calendar_repeat_frequency_units'] = ['YEARLY' => 'year(s)', 'MONTHLY' => 'month(s)', 'WEEKLY' => 'week(s)', 'DAILY' => 'day(s)', 'HOURLY' => 'hour(s)', 'MINUTELY' => 'minute(s)', 'SECONDLY' => 'second(s)'];
$txt['calendar_repeat_until_options'] = ['forever' => 'Forever', 'until' => 'Until', 'count' => 'Number of times'];
$txt['calendar_repeat_byday_num_options'] = [1 => 'the first', 2 => 'the second', 3 => 'the third', 4 => 'the fourth', 5 => 'the fifth', -1 => 'the last', -2 => 'the second last'];
$txt['calendar_repeat_weekday'] = 'weekday';
$txt['calendar_repeat_weekend_day'] = 'weekend day';
$txt['calendar_repeat_add_condition'] = 'Add Another';
$txt['calendar_repeat_advanced_options_label'] = 'More options...';
$txt['calendar_repeat_rdates_label'] = 'Additional dates';
$txt['calendar_repeat_exdates_label'] = 'Skipped dates';
$txt['calendar_repeat_easter_w'] = 'Easter (Western)';
$txt['calendar_repeat_easter_e'] = 'Easter (Eastern)';
$txt['calendar_repeat_vernal_equinox'] = 'Vernal Equinox';
$txt['calendar_repeat_summer_solstice'] = 'Summer Solstice';
$txt['calendar_repeat_autumnal_equinox'] = 'Autumnal Equinox';
$txt['calendar_repeat_winter_solstice'] = 'Winter Solstice';
$txt['calendar_repeat_special'] = 'Special';
$txt['calendar_repeat_special_rrule_modifier'] = 'Offset';
$txt['calendar_repeat_offset_examples'] = 'e.g.: +P1D, -PT2H';
$txt['calendar_repeat_adjustment_label'] = 'Apply changes to';
$txt['calendar_repeat_adjustment_this_only'] = 'Only this occurrence';
$txt['calendar_repeat_adjustment_this_and_future'] = 'This and future occurrences';
$txt['calendar_repeat_adjustment_confirm'] = 'Are you sure you want to apply these changes to all future occurrences?-n--n-WARNING: if you select &quot;This and future occurrences&quot; and the click the &quot;Delete&quot; button, you will delete this and all future occurrences.';
$txt['calendar_repeat_delete_label'] = 'Delete';
$txt['calendar_confirm_occurrence_delete'] = 'Are you sure you want to delete this occurrence of the event?-n--n-WARNING: if you selected &quot;This and future occurrences&quot; above, you will delete this and all future occurrences.';
$txt['calendar_repeat_adjustment_edit_first'] = 'Edit original event';

// Used to show a human-readable explanation of the recurrence rule for a repeating event.
$txt['calendar_rrule_desc'] = 'Repeats {rrule_description}{start_date, select,
	false {}
	other {, starting {start_date}}
}.';
// 'freq' will be one of YEARLY, MONTHLY, WEEKLY, DAILY, HOURLY, MINUTELY, or SECONDLY, or else a named month day (e.g. "Friday the 13th").
$txt['calendar_rrule_desc_frequency_interval'] = 'every {freq, select,
	YEARLY {{interval, plural,
		=1 {year}
		one {# year}
		other {# years}
	}}
	MONTHLY {{interval, plural,
		=1 {month}
		one {# month}
		other {# months}
	}}
	WEEKLY {{interval, plural,
		=1 {week}
		one {# week}
		other {# weeks}
	}}
	DAILY {{interval, plural,
		=1 {day}
		one {# day}
		other {# days}
	}}
	HOURLY {{interval, plural,
		=1 {hour}
		one {# hour}
		other {# hours}
	}}
	MINUTELY {{interval, plural,
		=1 {minute}
		one {# minute}
		other {# minutes}
	}}
	SECONDLY {{interval, plural,
		=1 {second}
		one {# second}
		other {# seconds}
	}}
	other {{freq}}
}';
// 'freq' will be one of YEARLY, MONTHLY, WEEKLY, DAILY, HOURLY, MINUTELY, or SECONDLY, or else a named month day (e.g. "Friday the 13th").
$txt['calendar_rrule_desc_frequency_interval_ordinal'] = 'every {freq, select,
	YEARLY {{interval, selectordinal,
		=1 {year}
		one {#st year}
		two {#nd year}
		few {#rd year}
		other {#th year}
	}}
	MONTHLY {{interval, selectordinal,
		=1 {month}
		one {#st month}
		two {#nd month}
		few {#rd month}
		other {#th month}
	}}
	WEEKLY {{interval, selectordinal,
		=1 {week}
		one {#st week}
		two {#nd week}
		few {#rd week}
		other {#th week}
	}}
	DAILY {{interval, selectordinal,
		=1 {day}
		one {#st day}
		two {#nd day}
		few {#rd day}
		other {#th day}
	}}
	HOURLY {{interval, selectordinal,
		=1 {hour}
		one {#st hour}
		two {#nd hour}
		few {#rd hour}
		other {#th hour}
	}}
	MINUTELY {{interval, selectordinal,
		=1 {minute}
		one {#st minute}
		two {#nd minute}
		few {#rd minute}
		other {#th minute}
	}}
	SECONDLY {{interval, selectordinal,
		=1 {second}
		one {#st second}
		two {#nd second}
		few {#rd second}
		other {#th second}
	}}
	other {{freq}}
}';
// 'months_titles' is a list of month names, using the forms from $txt['months_titles'].
$txt['calendar_rrule_desc_bymonth'] = 'in {months_titles}';
// 'ordinal_list' is a list of ordinal numbers (e.g. "1st, 2nd, and 3rd"). 'count' is the number of items in 'ordinal_list'.
$txt['calendar_rrule_desc_byweekno'] = '{count, plural,
	one {in the {ordinal_list} week of the year}
	other {in the {ordinal_list} weeks of the year}
}';
// 'ordinal_list' is a list of ordinal numbers (e.g. "1st, 2nd, and 3rd"). 'count' is the number of items in 'ordinal_list'.
$txt['calendar_rrule_desc_byyearday'] = '{count, plural,
	one {on the {ordinal_list} day of the year}
	other {on the {ordinal_list} days of the year}
}';
// 'ordinal_list' is a list of ordinal numbers (e.g. "1st, 2nd, and 3rd"). 'count' is the number of items in 'ordinal_list'.
$txt['calendar_rrule_desc_bymonthday'] = '{count, plural,
	one {on the {ordinal_list} day of the month}
	other {on the {ordinal_list} days of the month}
}';
// Translators can replace 'ordinal_month_day' with 'cardinal_month_day' in this string if the target language prefers cardinal numbers instead of ordinal numbers for this form. For example, '{day_name} the {ordinal_month_day}' will produce 'Friday the 13th', whereas '{cardinal_month_day} {day_name}' will produce '13 Friday'.
$txt['calendar_rrule_desc_named_monthday'] = '{day_name} the {ordinal_month_day}';
// 'ordinal_list' is a list of ordinal numbers (e.g. "1st, 2nd, or 3rd").
$txt['calendar_rrule_desc_named_monthdays'] = 'the first {day_name} that is the {ordinal_list} day of the month';
// E.g. "the 2nd Thursday"
$txt['calendar_rrule_desc_ordinal_day_name'] = 'the {ordinal} {day_name}';
// E.g. "on Monday", "on Tuesday and Thursday"
$txt['calendar_rrule_desc_byday'] = 'on {day_names}';
// E.g. "on every Monday", "on every Tuesday and Thursday"
$txt['calendar_rrule_desc_byday_every'] = 'on every {day_names}';
$txt['calendar_rrule_desc_byminute'] = '{minute_list} past the hour';
$txt['calendar_rrule_desc_bytime'] = 'at {times_list}';
$txt['calendar_rrule_desc_bygeneric'] = 'of {list}';
$txt['calendar_rrule_desc_between'] = 'between {min} and {max}';
$txt['calendar_rrule_desc_until'] = 'until {date}';
$txt['calendar_rrule_desc_count'] = '{count, plural,
	one {for # occurrence}
	other {for # occurrences}
}';
// 'ordinal_list' is a list of ordinal numbers (e.g. "1st, 2nd, and 3rd"). 'count' is the number of items in 'ordinal_list'.
$txt['calendar_rrule_desc_bysetpos'] = '{count, plural,
	one {on each {ordinal_list} occurrence}
	other {on each {ordinal_list} occurrences}
} of {rrule_description}';

?>