<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * Builds a manifest of files that must exist for SMF to function.
 * The manifest is saved to Sources/Maintenance/manifest.php.
 *
 * To also commit the manifest to the current branch, pass 'commit':
 *
 *     php -f other/update_manifest.php commit
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\other;

require_once 'Updaters/UpdaterBase.php';

require_once 'Updaters/ManifestUpdater.php';

// We intentionally don't create a new branch when making a standalone update to the manifest.
$updater = new Updaters\ManifestUpdater();
$updater->execute();

// Committing is left to the 'commit' argument: this also regenerates the
// manifest for local install and upgrade testing, which should leave Git alone.
if (($argv[1] ?? null) === 'commit' && $updater->hasChanged()) {
	if (!$updater->ready_to_commit) {
		echo 'Changes are not ready to commit. Deal with them manually.' . PHP_EOL;
	} elseif ($updater->commit()) {
		echo 'Changes committed.' . PHP_EOL;
	}
}
