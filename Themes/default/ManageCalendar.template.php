<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
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
			<div class="windowbg">';

	template_event_options();

	if (Utils::$context['is_new']) {
		echo '
				<input type="submit" value="', Lang::$txt['holidays_button_add'], '" class="button">';
	} else {
		echo '
				<input type="submit" name="edit" value="', Lang::$txt['holidays_button_edit'], '" class="button">
				<input type="submit" name="delete" value="', Lang::$txt['holidays_button_remove'], '" class="button">
				<input type="hidden" name="holiday" value="', Utils::$context['event']['id'], '">';
	}

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="' . Utils::$context['admin-eh_token_var'] . '" value="' . Utils::$context['admin-eh_token'] . '">
			</div><!-- .windowbg -->
		</form>';
}

/**
 * Importing iCalendar data.
 */
function template_import()
{
	// Show a form for all the holiday information.
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=managecalendar;sa=import" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="">', Lang::$txt['calendar_import_url'], '</label>
						<br>
						<span class="smalltext">', Lang::$txt['calendar_import_url_desc'], '</span>
					</dt>
					<dd>
						<input type="url" name="ics_url" id="ics_url">
					</dd>
					<dt>
						<label>', Lang::$txt['calendar_import_type'], '</label>
					</dt>
					<dd>
						<label>
							<input type="radio" name="type" value="holiday" checked>
							', Lang::$txt['calendar_import_type_holiday'], '
						</label>
						<label>
							<input type="radio" name="type" value="event">
							', Lang::$txt['calendar_import_type_event'], '
						</label>
					</dd>
					<dt>
						<label>', Lang::$txt['calendar_import_subscribe'], '</label>
						<br>
						<span class="smalltext">', Lang::$txt['calendar_import_subscribe_desc'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="subscribe" id="subscribe">
					</dd>
				</dl>
				<input type="submit" name="import" value="', Lang::$txt['calendar_import_button'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="' . Utils::$context['admin-calendarimport_token_var'] . '" value="' . Utils::$context['admin-calendarimport_token'] . '">
			</div><!-- .windowbg -->
		</form>';

	if (!empty(Utils::$context['calendar_subscriptions'])) {
		template_show_list('calendar_subscriptions');
	}
}

?>