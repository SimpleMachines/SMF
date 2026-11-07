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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Outputs the board lastposts for a standard board or a redirect.
 * When on a mobile device, this may be hidden if no last post exists.
 *
 * @param array $board Current board information.
 */
?><?php if (!empty($board['last_post']['id'])): ?>
			<p><?= $board['last_post']['last_post_message'] ?></p><?php endif; ?>
