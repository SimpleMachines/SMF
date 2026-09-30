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
 * Displays a clock
 */
?><?php $this->subTemplate('geek_clock', ['style' => 'bcd', 'title' => 'BCD Clock']); ?>

			<div class="centertext">
				<a href="<?= Config::$scripturl ?>?action=clock;rb" class="button">Are you hardcore?</a>
			</div>
		</div><!-- .roundframe -->