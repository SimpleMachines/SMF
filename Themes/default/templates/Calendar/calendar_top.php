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
 * Calendar controls under the title
 *
 * Creates the view selector (list, month, week), the date selector (either a
 * select menu or a date range chooser, depending on the circumstances), and the
 * "Post Event" button.
 *
 * @param array $calendar_data The data for the calendar grid that this is for
 */
?>
		<div class="calendar_top roundframe<?= empty($calendar_data['disable_title']) ? ' noup' : '' ?>">
			<div id="calendar_viewselector" class="buttonrow floatleft">
				<a href="<?= Config::$scripturl ?>?action=calendar;viewlist;year=<?= Utils::$context['current_year'] ?>;month=<?= Utils::$context['current_month'] ?>;day=<?= Utils::$context['current_day'] ?>" class="button<?= Utils::$context['calendar_view'] == 'viewlist' ? ' active' : '' ?>"><?= Lang::getTxt('calendar_list', file: 'Calendar') ?></a>
				<a href="<?= Config::$scripturl ?>?action=calendar;viewmonth;year=<?= Utils::$context['current_year'] ?>;month=<?= Utils::$context['current_month'] ?>;day=<?= Utils::$context['current_day'] ?>" class="button<?= Utils::$context['calendar_view'] == 'viewmonth' ? ' active' : '' ?>"><?= Lang::getTxt('calendar_month', file: 'Calendar') ?></a>
				<a href="<?= Config::$scripturl ?>?action=calendar;viewweek;year=<?= Utils::$context['current_year'] ?>;month=<?= Utils::$context['current_month'] ?>;day=<?= Utils::$context['current_day'] ?>" class="button<?= Utils::$context['calendar_view'] == 'viewweek' ? ' active' : '' ?>"><?= Lang::getTxt('calendar_week', file: 'Calendar') ?></a>
			</div>
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['calendar_buttons'], 'direction' => 'right']); ?>
			<form action="<?= Config::$scripturl ?>?action=calendar;<?= Utils::$context['calendar_view'] ?>" id="<?= !empty($calendar_data['end_date']) ? 'calendar_range' : 'calendar_navigation' ?>" method="post" accept-charset="UTF-8">
				<input type="date" name="start_date" id="start_date" value="<?= $calendar_data['iso_start_date'] ?>" class="date_input start" data-type="date">
<?php if (!empty($calendar_data['end_date'])): ?>
				<span><?= Utils::strtolower(Lang::getTxt('to', file: 'General')) ?></span>
				<input type="date" name="end_date" id="end_date" value="<?= $calendar_data['iso_end_date'] ?>" class="date_input end" data-type="date">
<?php endif; ?>
				<input type="submit" class="button" style="float:none" id="view_button" value="<?= Lang::getTxt('view', file: 'General') ?>">
			</form>
		</div><!-- .calendar_top -->