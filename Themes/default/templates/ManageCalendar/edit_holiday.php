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
 * Editing or adding holidays.
 */
?>

<?php /* Show a form for all the holiday information. */ ?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=managecalendar;sa=editholiday" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="windowbg"><?php $this->subTemplate('event_options'); ?>

<?php if (Utils::$context['is_new']): ?>
				<input type="submit" value="<?= Lang::getTxt('holidays_button_add', file: 'ManageCalendar') ?>" class="button">
<?php else: ?>
				<input type="submit" name="edit" value="<?= Lang::getTxt('holidays_button_edit', file: 'ManageCalendar') ?>" class="button">
				<input type="submit" name="delete" value="<?= Lang::getTxt('holidays_button_remove', file: 'ManageCalendar') ?>" class="button">
				<input type="hidden" name="holiday" value="<?= Utils::$context['event']['id'] ?>">
<?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-eh_token_var'] ?>" value="<?= Utils::$context['admin-eh_token'] ?>">
			</div><!-- .windowbg -->
		</form>