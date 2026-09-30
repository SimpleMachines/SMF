<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Importing iCalendar data.
 */
?><?php /* Show a form for all the holiday information. */ ?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=managecalendar;sa=import" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for=""><?= Lang::getTxt('calendar_import_url', file: 'ManageCalendar') ?></label>
						<br>
						<span class="smalltext"><?= Lang::getTxt('calendar_import_url_desc', file: 'ManageCalendar') ?></span>
					</dt>
					<dd>
						<input type="url" name="ics_url" id="ics_url">
					</dd>
					<dt>
						<label><?= Lang::getTxt('calendar_import_type', file: 'ManageCalendar') ?></label>
					</dt>
					<dd>
						<label>
							<input type="radio" name="type" value="holiday" checked>
							<?= Lang::getTxt('calendar_import_type_holiday', file: 'ManageCalendar') ?>

						</label>
						<label>
							<input type="radio" name="type" value="event">
							<?= Lang::getTxt('calendar_import_type_event', file: 'ManageCalendar') ?>

						</label>
					</dd>
					<dt>
						<label><?= Lang::getTxt('calendar_import_subscribe', file: 'ManageCalendar') ?></label>
						<br>
						<span class="smalltext"><?= Lang::getTxt('calendar_import_subscribe_desc', file: 'ManageCalendar') ?></span>
					</dt>
					<dd>
						<input type="checkbox" name="subscribe" id="subscribe">
					</dd>
				</dl>
				<input type="submit" name="import" value="<?= Lang::getTxt('calendar_import_button', file: 'ManageCalendar') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-calendarimport_token_var'] ?>" value="<?= Utils::$context['admin-calendarimport_token'] ?>">
			</div><!-- .windowbg -->
		</form><?php if (!empty(Utils::$context['calendar_subscriptions'])): ?><?php $this->subTemplate('show_list', ['list_id' => 'calendar_subscriptions']); ?><?php endif; ?>
