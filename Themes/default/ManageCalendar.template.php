<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// Editing or adding holidays.
function template_edit_holiday()
{
	global $context, $scripturl, $txt, $modSettings;

	// Show a form for all the holiday information.
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managecalendar;sa=editholiday" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong>', $txt['holidays_title_label'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="title" value="', $context['holiday']['title'], '" size="55" maxlength="60">
					</dd>
					<dt>
						<strong>', $txt['calendar_year'], '</strong>
					</dt>
					<dd>
						<select name="year" id="year" onchange="generateDays();">
							<option value="0000"', $context['holiday']['year'] == '0000' ? ' selected' : '', '>', $txt['every_year'], '</option>';
	// Show a list of all the years we allow...
	for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
		echo '
							<option value="', $year, '"', $year == $context['holiday']['year'] ? ' selected' : '', '>', $year, '</option>';

	echo '
						</select>&nbsp;
						', $txt['calendar_month'], '&nbsp;
						<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['holiday']['month'] ? ' selected' : '', '>', $txt['months'][$month], '</option>';

	echo '
						</select>&nbsp;
						', $txt['calendar_day'], '&nbsp;
						<select name="day" id="day" onchange="generateDays();">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['holiday']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['holiday']['day'] ? ' selected' : '', '>', $day, '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	if ($context['is_new'])
		echo '
				<input type="submit" value="', $txt['holidays_button_add'], '" class="button_submit">';
	else
		echo '
				<input type="submit" name="edit" value="', $txt['holidays_button_edit'], '" class="button_submit">
				<input type="submit" name="delete" value="', $txt['holidays_button_remove'], '" class="button_submit">
				<input type="hidden" name="holiday" value="', $context['holiday']['id'], '">';
	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>';
}

?>