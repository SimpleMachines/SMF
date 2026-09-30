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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Our main calendar template, which encapsulates weeks and months.
 */
?>

<?php /* The main calendar wrapper. */ ?>
		<div id="calendar">
<?php /* Show the mini-blocks if they're enabled. */ ?>
<?php if (empty(Utils::$context['blocks_disabled'])): ?>
			<div id="month_grid">
				<?php $this->subTemplate('show_month_grid', ['grid_name' => 'prev', 'is_mini' => true]); ?>

				<?php $this->subTemplate('show_month_grid', ['grid_name' => 'current', 'is_mini' => true]); ?>

				<?php $this->subTemplate('show_month_grid', ['grid_name' => 'next', 'is_mini' => true]); ?>

			</div>
<?php endif; ?>
<?php /* What view are we showing? */ ?>
<?php if (Utils::$context['calendar_view'] == 'viewlist'): ?>
			<div id="main_grid">
				<?php $this->subTemplate('show_upcoming_list', ['grid_name' => 'main']); ?>

			</div>
<?php elseif (Utils::$context['calendar_view'] == 'viewweek'): ?>
			<div id="main_grid">
				<?php $this->subTemplate('show_week_grid', ['grid_name' => 'main']); ?>

			</div>
<?php else: ?>
			<div id="main_grid">
				<?php $this->subTemplate('show_month_grid', ['grid_name' => 'main']); ?>

			</div>
<?php endif; ?>
<?php /* Close our wrapper. */ ?>
		</div><!-- #calendar -->