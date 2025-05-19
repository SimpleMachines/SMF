<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

define('SMF', 'UPGRADE');
define('SMF_INSTALLING', 1);

// Initialize.
require_once __DIR__ . '/index.php';

SMF\Maintenance\Maintenance::$disable_security = false;

(new SMF\Maintenance\Maintenance())->execute(SMF\Maintenance\Maintenance::UPGRADE);
