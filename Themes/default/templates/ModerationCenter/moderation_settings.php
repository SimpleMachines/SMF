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
 * The moderation settings page.
 */
?>
	<div id="modcenter">
		<div class="windowbg">
			<div class="centertext"><?= Lang::getTxt('mc_no_settings', file: 'ModerationCenter') ?></div>
		</div>
	</div><!-- #modcenter -->