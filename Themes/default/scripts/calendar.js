window.addEventListener("DOMContentLoaded", function() {
	const start_el = document.getElementById("start_date");
	const end_el = document.getElementById("end_date");

	if (start_el && end_el) {
		let dates = {
			current: {
				start: new Date(start_el.value + "T12:00:00"),
				end: new Date(end_el.value + "T12:00:00")
			}
		};
		start_el.addEventListener("change", updateCalendarUI.bind(start_el, dates));
		end_el.addEventListener("change", updateCalendarUI.bind(end_el, dates));

		updateCalendarUI(dates);
	}

	const clock = document.getElementById("geek-clock");

	if (clock) {
		let els = [];

		for (const elem of clock.children) {
			for (const el of elem.children) {
				els.push(el);
			}
		}

		setInterval(updateClock, 500, els, clock.className);
		updateClock(els, clock.className);
	}
});

// Update the date pickers in the calendar UI.
function updateCalendarUI(dates)
{
	const start_el = document.getElementById("start_date");
	const end_el = document.getElementById("end_date");
	let start_date = new Date(start_el.value + "T12:00:00");
	let end_date = new Date(end_el.value + "T12:00:00");

	if (this.id !== 'end_date') {
		if (dates.current.start.getTime() !== start_date.getTime()) {
			const start_diff = start_date.getTime() - dates.current.start.getTime();

			end_date.setTime(end_date.getTime() + start_diff);

			end_el.value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
		}
	}

	// Ensure start and end have a sane relationship.
	if (start_date.getTime() > end_date.getTime()) {
		const current_duration = dates.current.end.getTime() - dates.current.start.getTime();

		end_date = start_date;
		end_date.setTime(end_date.getTime() + current_duration);

		end_el.value = end_date.getFullYear() + '-' + (end_date.getMonth() < 9 ? '0' : '') + (end_date.getMonth() + 1) + '-' + (end_date.getDate() < 10 ? '0' : '') + end_date.getDate();
	}

	end_el.min = start_el.value;

	// Remember any changes to start and end dates.
	dates.current.start = start_date;
	dates.current.end = end_date;
}

function updateClock(els, which)
{
	// Get the current time
	const time = new Date();
	const hour = time.getHours();
	const min = time.getMinutes();
	const sec = time.getSeconds();
	let digits = {};

	// Break it up into individual digits
	switch (which) {
		case 'bcd':
			digits = {
				h1: parseInt(hour / 10),
				h2: hour % 10,
				m1: parseInt(min / 10),
				m2: min % 10,
				s1: parseInt(sec / 10),
				s2: sec % 10,
			};
		break;

		case 'hms':
			digits = {
				h: hour,
				m: min,
				s: sec
			};
		break;

		case 'omfg':
			digits = {
				month: time.getMonth() + 1,
				day: time.getDate(),
				year: time.getFullYear() % 100,
				hour,
				min,
				sec
			};
		break;
	}

	for (const el of els) {
		el.style.background = digits[el.dataset.e] & el.dataset.v ? "currentColor" : "none";
	}
}