<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Editing or adding holidays.
 */
function template_edit_holiday()
{
	// Show a form for all the holiday information.
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=managecalendar;sa=editholiday" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['holidays_title_label'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="title" value="', Utils::$context['holiday']['title'], '" size="55" maxlength="60">
					</dd>
					<dt>
						<strong>', Lang::$txt['calendar_year'], '</strong>
					</dt>
					<dd>
						<select name="year" id="year" onchange="generateDays();">
							<option value="0000"', Utils::$context['holiday']['year'] == '0000' ? ' selected' : '', '>', Lang::$txt['every_year'], '</option>';

	// Show a list of all the years we allow...
	for ($year = Config::$modSettings['cal_minyear']; $year <= Config::$modSettings['cal_maxyear']; $year++)
		echo '
							<option value="', $year, '"', $year == Utils::$context['holiday']['year'] ? ' selected' : '', '>', $year, '</option>';

	echo '
						</select>
						<label for="month">', Lang::$txt['calendar_month'], '</label>
						<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == Utils::$context['holiday']['month'] ? ' selected' : '', '>', Lang::$txt['months'][$month], '</option>';

	echo '
						</select>
						<label for="day">', Lang::$txt['calendar_day'], '</label>
						<select name="day" id="day" onchange="generateDays();">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= Utils::$context['holiday']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == Utils::$context['holiday']['day'] ? ' selected' : '', '>', $day, '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	if (Utils::$context['is_new'])
		echo '
				<input type="submit" value="', Lang::$txt['holidays_button_add'], '" class="button">';
	else
		echo '
				<input type="submit" name="edit" value="', Lang::$txt['holidays_button_edit'], '" class="button">
				<input type="submit" name="delete" value="', Lang::$txt['holidays_button_remove'], '" class="button">
				<input type="hidden" name="holiday" value="', Utils::$context['holiday']['id'], '">';
	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="' . Utils::$context['admin-eh_token_var'] . '" value="' . Utils::$context['admin-eh_token'] . '">
			</div><!-- .windowbg -->
		</form>';
}

?>