function generateDays(offset = '') {
	// Get the DOM elements
	const dayElement = document.getElementById('day' + offset);
	const yearElement = document.getElementById('year' + offset);
	const monthElement = document.getElementById('month' + offset);

	// Validate the existence of elements
	if (!dayElement || !yearElement || !monthElement) {
		console.error('One or more elements are missing. Ensure the IDs are correct.');
		return;
	}

	// Month lengths (default February to 28 days)
	const monthLengths = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	// Adjust February for leap years
	const year = parseInt(yearElement.options[yearElement.selectedIndex]?.value || 0, 10);
	if ((year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0)) {
		monthLengths[1] = 29;
	}

	// Get selected day before updating options
	const selectedDay = dayElement.selectedIndex;

	// Clear current options
	dayElement.innerHTML = '';

	// Get the number of days in the selected month
	const daysInMonth = monthLengths[parseInt(monthElement.value, 10) - 1] || 31;

	// Populate day options
	for (let i = 1; i <= daysInMonth; i++) {
		const option = document.createElement('option');
		option.value = i;
		option.textContent = i;
		dayElement.appendChild(option);
	}

	// Restore the previously selected day if valid
	dayElement.selectedIndex = Math.min(selectedDay, daysInMonth - 1);
}
