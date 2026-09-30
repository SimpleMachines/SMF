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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Displays the hours, minutes and seconds for our clock
 */
?><?php $this->subTemplate('geek_clock', ['style' => 'hms', 'title' => 'Binary Clock']); ?>

			<div class="centertext">
				<a href="<?= Config::$scripturl ?>?action=clock" class="button">Too tough for you?</a>
			</div>
		</div><!-- .roundframe -->