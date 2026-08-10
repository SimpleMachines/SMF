/*
 * Keeps the day drop-downs on the paid subscription editor honest, so that a
 * month with fewer than 31 days does not offer days it has not got.
 *
 * Only ?action=admin;area=paidsubscribe;sa=modifyuser draws these, which is
 * why this lives in its own file rather than in script.js.
 */

function generateDays(offset)
{
	// Work around JavaScript's lack of support for default values...
	offset = typeof(offset) != 'undefined' ? offset : '';

	var days = 0, selected = 0;
	var dayElement = document.getElementById("day" + offset), yearElement = document.getElementById("year" + offset), monthElement = document.getElementById("month" + offset);

	var monthLength = [
		31, 28, 31, 30,
		31, 30, 31, 31,
		30, 31, 30, 31
	];
	if (yearElement.options[yearElement.selectedIndex].value % 4 == 0)
		monthLength[1] = 29;

	selected = dayElement.selectedIndex;
	while (dayElement.options.length)
		dayElement.options[0] = null;

	days = monthLength[monthElement.value - 1];

	for (var i = 1; i <= days; i++)
		dayElement.options[dayElement.length] = new Option(i, i);

	if (selected < days)
		dayElement.selectedIndex = selected;
}

/*
 * The start and end date each have their own trio of selects, told apart by
 * the "end" suffix on the second set's ids.
 */
document.addEventListener('DOMContentLoaded', function ()
{
	['', 'end'].forEach(function (offset)
	{
		['year', 'month'].forEach(function (part)
		{
			var element = document.getElementById(part + offset);

			if (element)
				element.addEventListener('change', function ()
				{
					generateDays(offset);
				});
		});
	});
});
