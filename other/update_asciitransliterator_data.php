<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file exists to make it easy for developers to update the data
 * in ./Sources/Localization/data. Just run this file from the command
 * line in order to perform the update.
 *
 * Exceptions will be thrown if the \Transliterator does not exist or
 * if the installed version ICU library is older than the one that the
 * data files were previously generated from.
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

require_once 'Updaters/AsciiTransliteratorDataUpdater.php';

$updater = new Updaters\AsciiTransliteratorDataUpdater('update_asciitransliterator_data');
$updater->execute();
