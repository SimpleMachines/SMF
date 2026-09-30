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

use SMF\Lang;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Outputs the board stats for a redirect.
 *
 * @param array $board Current board information.
 */
?>

		<p>
			<?= Lang::getTxt('number_of_redirects', [$board->posts], file: 'General') ?>

		</p>