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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Wraps every maintenance sub-action.
 */
?>

	<div id="manage_maintenance"><?php /* If maintenance has finished tell the user. */ ?><?php if (!empty(Utils::$context['maintenance_finished'])): ?>

		<div class="infobox">
			<?= Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin') ?>

		</div><?php endif; ?>
