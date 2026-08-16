let current_start_date, current_end_date;

window.addEventListener("DOMContentLoaded", function() {
	const start_el = document.getElementById("start_date");
	const end_el = document.getElementById("end_date");

	// The clock pages load this file as well, and they have no date pickers.
	if (start_el && end_el) {
		start_el.addEventListener("change", updateCalendarUI);
		end_el.addEventListener("change", updateCalendarUI);

		current_start_date = new Date(start_el.value + "T12:00:00");
		current_end_date = new Date(end_el.value + "T12:00:00");

		updateCalendarUI();
	}

	const clock = document.getElementById("geek_clock");

	if (clock) {
		const lamps = clock.querySelectorAll("[data-unit]");

		updateClock(lamps, clock.className);
		setInterval(updateClock, 500, lamps, clock.className);
	}
});

// Update the date pickers in the calendar UI.
function updateCalendarUI()
{
	let start_date = new Date(document.getElementById("start_date").value + "T12:00:00");
	let end_date = new Date(document.getElementById("end_date").value + "T12:00:00");

	if (this.id !== 'end_date') {
		if (current_start_date.getTime() !== start_date.getTime()) {
			const start_diff = start_date.getTime() - current_start_date.getTime();

			end_date.setTime(end_date.getTime() + start_diff);

			document.getElementById("end_date").value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
		}
	}

	// Ensure start and end have a sane relationship.
	if (start_date.getTime() > end_date.getTime()) {
		const current_duration = current_end_date.getTime() - current_start_date.getTime();

		end_date = start_date;
		end_date.setTime(end_date.getTime() + current_duration);

		document.getElementById("end_date").value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
	}

	document.getElementById("end_date").min = document.getElementById("start_date").value;

	// Remember any changes to start and end dates.
	current_start_date = start_date;
	current_end_date = end_date;
}

/* Light the lamps that add up to the current time.
 * Every lamp carries the unit it belongs to and the value of its own bit, so
 * working out whether it should be lit is a single bitwise test. Which digits
 * a clock shows is the only thing that varies between the three of them.
 */
function updateClock(lamps, style)
{
	const time = new Date();
	let digits;

	switch (style) {
		// Binary coded decimal: a column of lamps per decimal digit.
		case 'bcd':
			digits = {
				h1: Math.floor(time.getHours() / 10),
				h2: time.getHours() % 10,
				m1: Math.floor(time.getMinutes() / 10),
				m2: time.getMinutes() % 10,
				s1: Math.floor(time.getSeconds() / 10),
				s2: time.getSeconds() % 10
			};
			break;

		case 'hms':
			digits = {
				h: time.getHours(),
				m: time.getMinutes(),
				s: time.getSeconds()
			};
			break;

		case 'omfg':
			digits = {
				year: time.getFullYear() % 100,
				month: time.getMonth() + 1,
				day: time.getDate(),
				hour: time.getHours(),
				min: time.getMinutes(),
				sec: time.getSeconds()
			};
			break;
	}

	for (const lamp of lamps) {
		lamp.classList.toggle("lit", (digits[lamp.dataset.unit] & lamp.dataset.bit) !== 0);
	}
}
