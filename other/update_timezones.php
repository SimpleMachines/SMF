<?php

/**
 * This is an internal development file. It should NOT be included in any SMF
 * distribution packages.
 *
 *    WHEN YOU RUN THIS, MAKE SURE THAT YOU READ THE DOCUMENTATION IN THE
 *    TimezoneDataUpdater CLASS, AND FOLLOW ALL THE DIRECTIONS THERE!!!
 *
 *    This is very important, because after this updater runs, there are some
 *    things that a human must do before committing the changes.
 *
 *
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

namespace SMF\other;

require_once 'Updaters/UpdaterBase.php';

require_once 'Updaters/TimezoneDataUpdater.php';

$updater = new Updaters\TimezoneDataUpdater('update_timezones');
$updater->execute();
