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
 * Draws the lamps of one of the geek clocks.
 *
 * The lamps start out unlit and carry the unit and the bit they stand for.
 * calendar.js lights the right ones twice a second, so nothing echoed here goes
 * stale the moment the page has finished loading. Leaves the .roundframe open
 * for the caller to add its own footer to.
 *
 * @param string $style Which clock this is. calendar.js reads the digits to show from it.
 * @param string $title Heading to put above the clock.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= $title ?></h3>
		</div>
		<div class="roundframe noup">
			<ul id="geek_clock" class="<?= $style ?>">
<?php foreach (Utils::$context['clockicons'] as $unit => $bits): ?>
				<li>
					<ul>
<?php foreach ($bits as $bit): ?>
						<li data-unit="<?= $unit ?>" data-bit="<?= $bit ?>"></li>
<?php endforeach; ?>
					</ul>
				</li>
<?php endforeach; ?>
			</ul>