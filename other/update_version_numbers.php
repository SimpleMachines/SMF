<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * To automatically increment the version number, run the following
 * command on the CLI:
 *
 *     php -f other/update_version_numbers.php
 *
 * To automatically increment the version number and then append '-dev'
 * to it, run the following command on the CLI:
 *
 *     php -f other/update_version_numbers.php 'dev'
 *
 * To manually specify a version string, do this:
 *
 *     php -f other/update_version_numbers.php 'version_string_here'
 *
 * Note: manually specifying a version string should only be needed
 * when changing from alpha to beta, from beta to release candidate, or
 * from release candidate to release version.
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

require_once 'Updaters/VersionNumberUpdater.php';

$updater = new Updaters\VersionNumberUpdater(str_replace('release-', '', Updaters\UpdaterBase::MAIN_BRANCH) . '/update_version_numbers');
$updater->execute($argv[1] ?? null);

if ($updater->hasChanged()) {
	if (!$updater->ready_to_commit) {
		echo 'Changes are not ready to commit. Deal with them manually.' . PHP_EOL;
	} elseif ($updater->commit()) {
		echo 'Changes committed.' . PHP_EOL;
	}
}
