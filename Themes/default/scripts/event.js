window.addEventListener("DOMContentLoaded", updateEventUI);

for (const elem of document.querySelectorAll("#start_date, #start_time, #end_date, #end_time, #allday, #tz, #rrule, #freq, #end_option, #monthly_option_type_bymonthday, #monthly_option_type_byday, #byday_num_select_0, #byday_name_select_0, #weekly_options .rrule_input[name=\'BYDAY\[\]\'], #monthly_options .rrule_input[name=\'BYDAY_num\[\]\'], #monthly_options .rrule_input[name=\'BYDAY_name\[\]\'], #monthly_options .rrule_input[name=\'BYMONTHDAY\[\]\'], #event_link_to label, #event_link_to input, #topic_link_to label, #topic_link_to input")) {
	elem.addEventListener("change", updateEventUI);
}

for (const elem of document.querySelectorAll("#event_add_byday")) {
	elem.addEventListener("click", addByDayItem);
}

for (const elem of document.querySelectorAll("#event_add_rdate, #event_add_exdate")) {
	elem.addEventListener("click", addRDateOrExDate);
}

for (const elem of document.querySelectorAll("#rdate_list a, #exdate_list a")) {
	elem.addEventListener("click", removeRDateOrExDate);
}

let rdates_count = document.querySelectorAll("#rdate_list input[type='date']").length;
let exdates_count = document.querySelectorAll("#exdate_list input[type='date']").length;

let current_start_date = new Date(document.getElementById("start_date").value + 'T' + (document.getElementById("allday").checked ? '12:00:00' : document.getElementById("start_time").value));
let current_end_date = new Date(document.getElementById("end_date").value + 'T' + (document.getElementById("allday").checked ? '12:00:00' : document.getElementById("end_time").value));

const weekday_abbrevs = ["SU", "MO", "TU", "WE", "TH", "FR", "SA"];

// Update all the parts of the event editing UI.
function updateEventUI()
{
	toggleNewOrExistingEvent();
	toggleNewOrExistingTopic();

	let start_date = new Date(document.getElementById("start_date").value + 'T' + (document.getElementById("allday").checked ? '12:00:00' : document.getElementById("start_time").value));
	let end_date = new Date(document.getElementById("end_date").value + 'T' + (document.getElementById("allday").checked ? '12:00:00' : document.getElementById("end_time").value));

	let weekday = getWeekday(start_date);

	// Disable or enable the time-related fields as necessary.
	document.getElementById("start_time").disabled = document.getElementById("start_time").dataset.forceDisabled;
	document.getElementById("start_time").disabled = document.getElementById("allday").checked || document.getElementById("start_time").dataset.forceDisabled;
	document.getElementById("end_time").disabled = document.getElementById("allday").checked || document.getElementById("end_time").dataset.forceDisabled;
	document.getElementById("tz").disabled = document.getElementById("allday").checked || document.getElementById("tz").dataset.forceDisabled;

	// Reset the recurring event options to be hidden and disabled.
	// We'll turn the appropriate ones back on below.
	for (const elem of document.querySelectorAll(".rrule_input_wrapper")) {
		elem.style.display = "none";
	}

	for (const elem of document.querySelectorAll(".rrule_input")) {
		elem.disabled = true;
	}

	// If using a custom RRule, show the relevant options.
	if (document.getElementById("rrule") && document.getElementById("rrule").value === "custom") {
		// Show select menu for FREQ options.
		document.getElementById("freq_interval_options").style.display = "";

		for (const elem of document.querySelectorAll("#freq_interval_options .rrule_input")) {
			elem.disabled = false;
		}

		// Show additional options based on the selected frequency.
		switch (document.getElementById("freq").value) {
			case "YEARLY":
				for (const elem of document.querySelectorAll("#yearly_options .rrule_input")) {
					elem.disabled = false;
				}

				// If necessary, align date fields to BYMONTH inputs.
				if (this.name == "BYMONTH[]") {
					let checked_months = [];

					for (const elem of document.querySelectorAll("#yearly_options .rrule_input[name=\'BYMONTH\[\]\']")) {
						if (elem.checked) {
							checked_months.push(elem.id.substring(8));
						}
					}

					let found = false;

					let m;
					for (m of checked_months) {
						if (m >= (start_date.getMonth() + 1)) {
							found = true;
							break;
						}
					}

					if (!found) {
						checked_months.reverse();
						for (m of checked_months) {
							if (m < (start_date.getMonth() + 1)) {
								found = true;
								break;
							}
						}
					}

					if (found) {
						let temp_date = new Date(
							start_date.getFullYear(),
							m - 1,
							start_date.getDate(),
							start_date.getHours(),
							start_date.getMinutes(),
							start_date.getSeconds(),
							start_date.getMilliseconds()
						);

						start_date = updateStartDate(temp_date);
						end_date = updateEndDate(temp_date, end_date);
					}
				}

				// Ensure the yearly BYMONTH inputs align with start_date.
				if (!document.getElementById("bymonth_" + (start_date.getMonth() + 1)).checked) {
					for (const elem of document.querySelectorAll(".rrule_input[name=\'BYMONTH\[\]\']")) {
						elem.checked = false;
					}
				}
				document.getElementById("bymonth_" + (start_date.getMonth() + 1)).checked = true;

				document.getElementById("yearly_options").style.display = "";
				// no break

			case "MONTHLY":
				// Enable both radio buttons to allow choosing the type of montly recurrence.
				for (const elem of document.querySelectorAll("#monthly_options input[name=\'monthly_option_type\']")) {
					elem.disabled = false;
				}

				// Make sure one of the radio buttons is checked.
				if (
					!document.getElementById("monthly_option_type_bymonthday").checked
					&& !document.getElementById("monthly_option_type_byday").checked
				) {
					document.getElementById("monthly_option_type_bymonthday").checked = true;
				}

				// Enable either the BYMONTHDAY inputs or BYDAY_* select menus.
				for (const elem of document.querySelectorAll("#month_bymonthday_options .rrule_input")) {
					elem.disabled = document.getElementById("monthly_option_type_byday").checked;
				}

				for (const elem of document.querySelectorAll("#month_byday_options .rrule_input")) {
					elem.disabled = !document.getElementById("monthly_option_type_byday").checked;

					if (elem.classList.contains('button')) {
						elem.style.display = document.getElementById("monthly_option_type_byday").checked ? "" : "none";
					}
				}

				// Need to know the current values in the BYDAY_* select menus.
				let byday_num = document.getElementById("byday_num_select_0").value;
				let byday_name = document.getElementById("byday_name_select_0").value;

				// Need to know the last day of the month for stuff below.
				const end_of_month = new Date(
					start_date.getFullYear(),
					start_date.getMonth() + 1,
					0,
					start_date.getHours(),
					start_date.getMinutes(),
					start_date.getSeconds(),
					start_date.getMilliseconds()
				);

				let selected_days = [];

				for (const weekday_abbrev of weekday_abbrevs) {
					if (byday_name.includes(weekday_abbrev)) {
						selected_days.push(weekday_abbrevs.indexOf(weekday_abbrev));
					}
				}

				// If necessary, align date fields to BYDAY_* selection.
				if (this.id === "byday_num_select_0" || this.id === "byday_name_select_0") {
					let temp_date = getNewStartDateByDay(start_date, end_of_month, byday_num, selected_days);

					if (temp_date.getMonth() !== start_date.getMonth() && byday_num == 5) {
						enableOrDisableFifth();
						byday_num = document.getElementById("byday_num_select_0").value;
						byday_name = document.getElementById("byday_name_select_0").value;
						selected_days = [weekday_abbrevs.indexOf(byday_name)];

						temp_date = getNewStartDateByDay(start_date, end_of_month, byday_num, selected_days);
					}

					if (temp_date.getMonth() === start_date.getMonth()) {
						start_date = updateStartDate(temp_date);
						end_date = updateEndDate(start_date, end_date);
					}
				}

				// If necessary, align date fields to BYMONTHDAY inputs.
				if (this.name == "BYMONTHDAY[]" && !document.getElementById("bymonthday_" + start_date.getDate()).checked) {
					let checked_dates = [];

					for (const elem of document.querySelectorAll("#monthly_options .rrule_input[name=\'BYMONTHDAY\[\]\']")) {
						if (elem.checked) {
							checked_dates.push(elem.id.substring(11));
						}
					}

					let found = false;

					let d;
					for (d of checked_dates) {
						if (d >= start_date.getDate()) {
							found = true;
							break;
						}
					}

					if (!found) {
						checked_dates.reverse();
						for (d of checked_dates) {
							if (d < start_date.getDate()) {
								found = true;
								break;
							}
						}
					}

					if (found) {
						let temp_date = new Date(
							start_date.getFullYear(),
							start_date.getMonth(),
							d,
							start_date.getHours(),
							start_date.getMinutes(),
							start_date.getSeconds(),
							start_date.getMilliseconds()
						);

						start_date = updateStartDate(temp_date);
						end_date = updateEndDate(temp_date, end_date);
					}
				}

				// Update weekday in case it changed.
				weekday = getWeekday(start_date);

				// If necessary, reset the BYMONTHDAY inputs.
				if (!document.getElementById("bymonthday_" + start_date.getDate()).checked) {
					for (const elem of document.querySelectorAll("#monthly_options .rrule_input[name=\'BYMONTHDAY\[\]\']")) {
						elem.checked = false;
					}
				}

				// Ensure the BYMONTHDAY input for start_date is checked.
				document.getElementById("bymonthday_" + start_date.getDate()).checked = true;

				// If necessary, update the BYDAY_* select menus.
				if (!byday_name.includes(weekday)) {
					for (const elem of document.querySelectorAll("#byday_name_select_0 .byday_name_" + weekday)) {
						elem.selected = true;
					}

					byday_name = weekday;
					selected_days = [weekday_abbrevs.indexOf(weekday)];
				}

				if (byday_num < 0) {
					let temp_byday_num = 0;

					for (let d = end_of_month.getDate(); d > start_date.getDate(); d--) {
						let temp_date = new Date(
							start_date.getFullYear(),
							start_date.getMonth(),
							d,
							start_date.getHours(),
							start_date.getMinutes(),
							start_date.getSeconds(),
							start_date.getMilliseconds()
						);

						if (selected_days.includes(temp_date.getDay())) {
							++temp_byday_num;
						}
					}

					++temp_byday_num;

					if (temp_byday_num <= 2) {
						for (const elem of document.querySelectorAll("#byday_num_select_0 .byday_num_neg" + temp_byday_num)) {
							elem.selected = true;
						}
					} else {
						byday_num = 0;
						byday_name = weekday;
						selected_days = [weekday_abbrevs.indexOf(weekday)];
						for (const elem of document.querySelectorAll("#byday_name_select_0 .byday_name_" + weekday)) {
							elem.selected = true;
						}
					}
				}

				if (byday_num >= 0) {
					let temp_byday_num = 0;

					for (let d = 1; d < start_date.getDate(); d++) {
						let temp_date = new Date(
							start_date.getFullYear(),
							start_date.getMonth(),
							d,
							start_date.getHours(),
							start_date.getMinutes(),
							start_date.getSeconds(),
							start_date.getMilliseconds()
						);

						if (selected_days.includes(temp_date.getDay())) {
							++temp_byday_num;
						}
					}

					++temp_byday_num;

					for (const elem of document.querySelectorAll("#byday_num_select_0 .byday_num_" + temp_byday_num)) {
						elem.selected = true;
					}
				}

				document.getElementById("monthly_options").style.display = "";

				if (document.getElementById("freq").value === "YEARLY") {
					document.getElementById("dt_monthly_option_type_bymonthday").style.display = "none";
					document.getElementById("dd_monthly_option_type_bymonthday").style.display = "none";
					document.getElementById("monthly_option_type_bymonthday").disabled = true;
					document.getElementById("monthly_option_type_byday").type = "checkbox";
				} else {
					document.getElementById("dt_monthly_option_type_bymonthday").style.display = "";
					document.getElementById("dd_monthly_option_type_bymonthday").style.display = "";
					document.getElementById("monthly_option_type_byday").type = "radio";
				}
				break;

			case "WEEKLY":
				for (const elem of document.querySelectorAll("#weekly_options .rrule_input")) {
					elem.disabled = false;
				}

				if (this.name == "BYDAY[]" && !document.getElementById("byday_" + weekday).checked) {
					let checked_days = [];

					for (const elem of document.querySelectorAll("#weekly_options .rrule_input[name=\'BYDAY\[\]\']")) {
						if (elem.checked) {
							checked_days.push(elem.id.substring(6));
						}
					}

					let cd;
					for (cd of checked_days) {
						if (weekday_abbrevs.indexOf(cd) > weekday_abbrevs.indexOf(weekday)) {
							found = true;
							break;
						}
					}

					if (!found) {
						checked_days.reverse();
						for (cd of checked_days) {
							if (weekday_abbrevs.indexOf(cd) < weekday_abbrevs.indexOf(weekday)) {
								found = true;
								break;
							}
						}
					}

					if (found) {
						let temp_date = start_date;

						// Rewind to previous Sunday or first day of month.
						while (temp_date.getDay() > 0) {
							temp_date.setDate(temp_date.getDate() - 1);

							if (temp_date.getMonth() < start_date.getMonth()) {
								temp_date.setDate(temp_date.getDate() + 1);
								break;
							}
						}

						// Now step forward until we get to the day we need.
						while (temp_date.getDay() != weekday_abbrevs.indexOf(cd)) {
							temp_date.setDate(temp_date.getDate() + 1);
						}

						start_date = updateStartDate(temp_date);
						end_date = updateEndDate(start_date, end_date);
					}
				}

				// Update weekday in case it changed.
				weekday = getWeekday(start_date);

				// If necessary, reset the BYDAY values.
				if (!document.getElementById("byday_" + weekday).checked) {
					for (const elem of document.querySelectorAll("#weekly_options .rrule_input[name=\'BYDAY\[\]\']")) {
						elem.checked = false;
					}
				}

				document.getElementById("byday_" + weekday).checked = true;
				document.getElementById("weekly_options").style.display = "";
				break;

		}
	} else {
		if (document.getElementById("freq_interval_options")) {
			document.getElementById("freq_interval_options").style.display = "none";
			document.getElementById("freq").value = "DAILY";
		}

		for (const elem of document.querySelectorAll(".rrule_input_wrapper")) {
			elem.style.display = "none";
		}

		for (const elem of document.querySelectorAll(".rrule_input")) {
			elem.disabled = true;
		}

		for (const elem of document.querySelectorAll("#rrule_options .rrule_input")) {
			elem.disabled = false;
		}

		for (const elem of document.querySelectorAll(".rrule_input[name=\'BYMONTHDAY\[\]\']")) {
			elem.checked = false;
		}
	}

	if (
		document.getElementById("rrule")
		&& document.getElementById("special_rrule_modifier")
		&& typeof special_rrules !== "undefined"
		&& Array.isArray(special_rrules)
	) {
		if (special_rrules.includes(document.getElementById("rrule").value)) {
			document.getElementById("special_rrule_options").style.display = "";
			document.getElementById("special_rrule_modifier").disabled = false;
		} else {
			document.getElementById("special_rrule_options").style.display = "none";
			document.getElementById("special_rrule_modifier").disabled = true;
		}
	}

	end_date = updateEndDate(start_date, end_date);

	// Show the basic RRule select menu.
	for (const elem of document.querySelectorAll(".rrule_options")) {
		elem.style.display = "";
	}

	for (const elem of document.querySelectorAll("#rrule")) {
		elem.disabled = false;
	}

	// If necessary, show the options for RRule end.
	if (document.getElementById("rrule") && (document.getElementById("rrule").value === "custom" || document.getElementById("rrule").value.substring(0, 5) === "FREQ=")) {
		const end_option = document.getElementById("end_option");
		const until = document.getElementById("until");
		const count = document.getElementById("count");

		document.getElementById("rrule_end").style.display = "";

		end_option.disabled = false;
		end_option.style.display = "";

		until.disabled = (end_option.value !== "until");
		until.required = (end_option.value === "until");
		until.style.display = (end_option.value !== "until") ? "none" : "";

		count.disabled = (end_option.value !== "count");
		count.required = (end_option.value === "count");
		count.style.display = (end_option.value !== "count") ? "none" : "";
	}

	enableOrDisableFifth();

	// Show or hide the options for additional and excluded dates.
	if (
		document.getElementById("rrule")
		&& document.getElementById("rrule").value === "never"
		&& document.querySelector("#advanced_options input")
		&& document.querySelector("#advanced_options input").value === ""
	) {
		document.getElementById("advanced_options").style.display = "none";

		for (const elem of document.querySelectorAll("#advanced_options input")) {
			elem.disabled = true;
		}
	} else {
		document.getElementById("advanced_options").style.display = "";

		for (const elem of document.querySelectorAll("#advanced_options input")) {
			elem.disabled = false;
		}
	}
}

// Updates start_date and start_time elements to new values.
function updateStartDate(start_date)
{
	document.getElementById("start_date").value = start_date.getFullYear() + '-' + (start_date.getMonth() < 9 ? '0' : '') + (start_date.getMonth() + 1) + '-' + (start_date.getDate() < 10 ? '0' : '') + start_date.getDate();

	return start_date;
}

// If start_date or start_time elements changed, automatically updates
// end_date and end_time elements to preserve the event duration.
function updateEndDate(start_date, end_date)
{
	if (current_start_date.getTime() !== start_date.getTime()) {
		const start_diff = start_date.getTime() - current_start_date.getTime();

		end_date.setTime(end_date.getTime() + start_diff);

		document.getElementById("end_date").value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
		document.getElementById("end_time").value = end_date.toTimeString().substring(0, 5);

		document.getElementById("end_date").min = document.getElementById("start_date").value;

		if (document.getElementById("start_date").value === document.getElementById("end_date").value) {
			document.getElementById("end_time").min = document.getElementById("start_time").value;
		} else {
			document.getElementById("end_time").removeAttribute("min");
		}
	}

	// Ensure start and end have a sane relationship.
	if (start_date.getTime() > end_date.getTime()) {
		const current_duration = current_end_date.getTime() - current_start_date.getTime();

		end_date = start_date;
		end_date.setTime(end_date.getTime() + current_duration);

		document.getElementById("end_date").value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
		document.getElementById("end_time").value = end_date.toTimeString().substring(0, 5);
	}

	// If necessary, also update the UNTIL field.
	if (document.getElementById("until")) {
		let until = new Date(document.getElementById("until").value + "T23:59:59.999");

		document.getElementById("until").min = document.getElementById("start_date").value;

		if (start_date.getTime() > until.getTime()) {
			document.getElementById("until").value = document.getElementById("end_date").value;
		}
	}

	// Remember any changes to start and end dates.
	current_start_date = start_date;
	current_end_date = end_date;

	return end_date;
}

// Gets the weekday abbreviation of a date.
function getWeekday(date)
{
	return weekday_abbrevs[date.getDay()];
}

//
function getNewStartDateByDay(start_date, end_of_month, byday_num, selected_days)
{
	let temp_date = new Date(
		start_date.getFullYear(),
		start_date.getMonth(),
		byday_num > 0 ? 1 : end_of_month.getDate(),
		start_date.getHours(),
		start_date.getMinutes(),
		start_date.getSeconds(),
		start_date.getMilliseconds()
	);

	let offset = 0;

	while (
		(
			!selected_days.includes(temp_date.getDay())
			|| offset != byday_num
		)
		&& temp_date.getMonth() === start_date.getMonth()
	) {
		if (selected_days.includes(temp_date.getDay())) {
			offset = offset + (byday_num > 0 ? 1 : -1);

			if (offset == byday_num) {
				break;
			}
		}

		temp_date.setDate(temp_date.getDate() + (byday_num > 0 ? 1 : -1));
	}

	return temp_date;
}

// Determine whether the BYDAY_num select menu's "fifth" option should be enabled or not.
function enableOrDisableFifth()
{
	if (!document.getElementById("byday_name_select_0")) {
		return;
	}

	const start_date = new Date(document.getElementById("start_date").value + 'T' + document.getElementById("start_time").value);

	const end_of_month = new Date(
		start_date.getFullYear(),
		start_date.getMonth() + 1,
		0,
		start_date.getHours(),
		start_date.getMinutes(),
		start_date.getSeconds(),
		start_date.getMilliseconds()
	);

	const byday_name = document.getElementById("byday_name_select_0").value;

	let selected_days = [];

	for (const weekday_abbrev of weekday_abbrevs) {
		if (byday_name.includes(weekday_abbrev)) {
			selected_days.push(weekday_abbrevs.indexOf(weekday_abbrev));
		}
	}

	let enable_fifth = false;
	let temp_date = end_of_month;
	while (temp_date.getDate() > 28) {
		if (selected_days.includes(temp_date.getDay())) {
			enable_fifth = true;
		}

		temp_date.setDate(temp_date.getDate() - 1);
	}

	for (const elem_fifth of document.querySelectorAll("#byday_num_select_0 .byday_num_5")) {
		elem_fifth.disabled = !enable_fifth;
		// If "fifth" is selected but disabled, change selection to "last" instead.
		if (elem_fifth.disabled && elem_fifth.selected) {
			elem_fifth.selected = false;

			for (const elem_neg1 of document.querySelectorAll("#byday_num_select_0 .byday_num_neg1")) {
				elem_neg1.selected = true;
			}
		}
	}
}

function addByDayItem()
{
	if ("content" in document.createElement("template")) {
		const container = document.querySelector("#month_byday_options .rrule_input");
		const template = document.getElementById("byday_template");

		const clone = template.content.cloneNode(true);

		const byday_num_select = clone.querySelector(".byday_num_select");
		byday_num_select.id = "byday_num_select_" + (++monthly_byday_items);

		const byday_name_select = clone.querySelector(".byday_name_select");
		byday_name_select.id = "byday_name_select_" + monthly_byday_items;

		container.appendChild(clone);
	}
}

function addRDateOrExDate()
{
	if ("content" in document.createElement("template")) {
		const container = document.getElementById(this.dataset.container);
		const template = document.getElementById("additional_dates_template");

		let item_count;
		if (this.dataset.inputname === 'RDATE') {
			item_count = ++rdates_count;
		} else if (this.dataset.inputname === 'EXDATE') {
			item_count = ++exdates_count;
		} else {
			return;
		}

		const clone = template.content.cloneNode(true);

		const date_input = clone.querySelector('input[type="date"]');
		date_input.name = this.dataset.inputname + "_date[" + (item_count) + "]";

		const time_input = clone.querySelector('input[type="time"]');
		time_input.name = this.dataset.inputname + "_time[" + item_count + "]";

		const removeButton = clone.querySelector('a.delete');
		removeButton.addEventListener("click", removeRDateOrExDate);

		container.appendChild(clone);
	}
}

function removeRDateOrExDate()
{
	if (this.closest("dl").id === 'rdates') {
		--rdates_count;
	} else if (this.closest("dl").id === 'exdates') {
		--exdates_count;
	}

	this.parentElement.remove();
}

function toggleNewOrExistingEvent()
{
	if (!document.getElementById("event_link_to")) {
		return;
	}

	if (document.getElementById("event_link_to_new").checked === true) {
		document.getElementById("event_new").style.display = '';
		document.getElementById("event_id_to_link").style.display = 'none';
		document.querySelector("#event_id_to_link input").disabled = true;
	} else {
		document.getElementById("event_new").style.display = 'none';
		document.getElementById("event_id_to_link").style.display = '';
		document.querySelector("#event_id_to_link input").disabled = false;
	}
}

function toggleNewOrExistingTopic()
{
	if (!document.getElementById("topic_link_to")) {
		return;
	}

	if (document.getElementById("event_board")) {
		document.getElementById("event_board").style.display = 'none';
		document.querySelector("#event_board select").disabled = true;
	}

	if (document.getElementById("event_topic")) {
		document.getElementById("event_topic").style.display = 'none';
		document.querySelector("#event_topic input").disabled = true;
	}

	if (
		document.getElementById("event_board")
		&& document.getElementById("link_to_board")
		&& document.getElementById("link_to_board").checked === true
	) {
		document.getElementById("event_board").style.display = '';
		document.querySelector("#event_board select").disabled = false;
	} else if (
		document.getElementById("event_topic")
		&& document.getElementById("link_to_topic")
		&& document.getElementById("link_to_topic").checked === true
	) {
		document.getElementById("event_topic").style.display = '';
		document.querySelector("#event_topic input").disabled = false;
	}
}