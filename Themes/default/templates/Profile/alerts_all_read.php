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
 * A simple template to say "You don't have any unread alerts".
 */
?><div class="no_unread"><?= Lang::getTxt('alerts_no_unread', file: 'Alerts') ?></div>