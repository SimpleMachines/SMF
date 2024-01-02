<?php

/**
 * This file is not designed to ever be run directly from within SMF's
 * conventional running, but called externally to facilitate background tasks.
 * It can be called either by URL or via cron (or whatever the equivalent is on
 * the server's operating system).
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

// Don't do anything if SMF is already loaded.
if (defined('SMF')) {
	return true;
}

define('SMF', 'BACKGROUND');

// Initialize.
require_once __DIR__ . '/index.php';

$task_runner = new SMF\TaskRunner();
$task_runner->execute();

?>