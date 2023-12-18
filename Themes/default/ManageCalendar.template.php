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

?>