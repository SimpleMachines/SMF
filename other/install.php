<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none;">

define('SMF', 'INSTALL');
define('SMF_INSTALLING', 1);
define('SMF_SETTINGS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'Settings.php');

// Ensure Settings.php exists. We can recover if it is blank, but it must exist.
if (!file_exists(SMF_SETTINGS_FILE)) {
	@touch(SMF_SETTINGS_FILE);
}

// Initialize.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'index.php';

(new SMF\Maintenance\Maintenance())->execute(SMF\Maintenance\Maintenance::INSTALL);
