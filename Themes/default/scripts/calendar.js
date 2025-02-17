window.addEventListener("DOMContentLoaded", function() {
	updateCalendarUI();
});

document.getElementById("start_date").addEventListener("change", updateCalendarUI);
document.getElementById("end_date").addEventListener("change", updateCalendarUI);

let current_start_date = new Date(document.getElementById("start_date").value + "T12:00:00");
let current_end_date = new Date(document.getElementById("end_date").value + "T12:00:00");

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