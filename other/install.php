<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

use SMF\Maintenance;

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none;">

if (!defined('SMF')) {
	define('SMF', 'INSTALL');
}

define('SMF_INSTALLING', 1);

// In pre-release versions, report all errors.
if (strspn(SMF_VERSION, '1234567890.') !== strlen(SMF_VERSION)) {
	error_reporting(E_ALL);
}
// Otherwise, report all errors except for deprecation notices.
else {
	error_reporting(E_ALL & ~E_DEPRECATED);
}

ob_start();

// Initialize.
require_once __DIR__ . '/index.php';

(new SMF\Maintenance())->execute(Maintenance::INSTALL);