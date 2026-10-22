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
 * After registration... all done ;).
 */
?>

<?php /* Not much to see here, just a quick... "you're now registered!" or what have you. */ ?>
		<div id="registration_success">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['title'] ?></h3>
			</div>
			<div class="windowbg">
				<p><?= Utils::$context['description'] ?></p>
			</div>
		</div>