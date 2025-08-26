<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
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
		<form action="', Config::$scripturl, '?action=admin;area=managecalendar;sa=editholiday" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">';

	template_event_options();

	if (Utils::$context['is_new']) {
		echo '
				<input type="submit" value="', Lang::getTxt('holidays_button_add', file: 'ManageCalendar'), '" class="button">';
	} else {
		echo '
				<input type="submit" name="edit" value="', Lang::getTxt('holidays_button_edit', file: 'ManageCalendar'), '" class="button">
				<input type="submit" name="delete" value="', Lang::getTxt('holidays_button_remove', file: 'ManageCalendar'), '" class="button">
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
		<form action="', Config::$scripturl, '?action=admin;area=managecalendar;sa=import" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="">', Lang::getTxt('calendar_import_url', file: 'ManageCalendar'), '</label>
						<br>
						<span class="smalltext">', Lang::getTxt('calendar_import_url_desc', file: 'ManageCalendar'), '</span>
					</dt>
					<dd>
						<input type="url" name="ics_url" id="ics_url">
					</dd>
					<dt>
						<label>', Lang::getTxt('calendar_import_type', file: 'ManageCalendar'), '</label>
					</dt>
					<dd>
						<label>
							<input type="radio" name="type" value="holiday" checked>
							', Lang::getTxt('calendar_import_type_holiday', file: 'ManageCalendar'), '
						</label>
						<label>
							<input type="radio" name="type" value="event">
							', Lang::getTxt('calendar_import_type_event', file: 'ManageCalendar'), '
						</label>
					</dd>
					<dt>
						<label>', Lang::getTxt('calendar_import_subscribe', file: 'ManageCalendar'), '</label>
						<br>
						<span class="smalltext">', Lang::getTxt('calendar_import_subscribe_desc', file: 'ManageCalendar'), '</span>
					</dt>
					<dd>
						<input type="checkbox" name="subscribe" id="subscribe">
					</dd>
				</dl>
				<input type="submit" name="import" value="', Lang::getTxt('calendar_import_button', file: 'ManageCalendar'), '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="' . Utils::$context['admin-calendarimport_token_var'] . '" value="' . Utils::$context['admin-calendarimport_token'] . '">
			</div><!-- .windowbg -->
		</form>';

	if (!empty(Utils::$context['calendar_subscriptions'])) {
		template_show_list('calendar_subscriptions');
	}
}
