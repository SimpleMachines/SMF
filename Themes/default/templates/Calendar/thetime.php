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
 * Displays the time
 */
?>

		<div class="cat_bar">
			<h3 class="catbg">The time you requested</h3>
		</div>
		<div class="roundframe noup">
			<ul id="geek_time">
<?php
// Unlike the clocks, this one never changes, so which lamps are lit is
// settled here rather than in calendar.js.
?>
<?php foreach (Utils::$context['clockicons'] as $v): ?>
				<li>
					<ul>
<?php foreach ($v as $lit): ?>
						<li<?= $lit ? ' class="lit"' : '' ?>></li>
<?php endforeach; ?>
					</ul>
				</li>
<?php endforeach; ?>
			</ul>
		</div><!-- .roundframe -->