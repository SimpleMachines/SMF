<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * To automatically increment the version number for the new release,
 * run the following command on the CLI:
 *
 *     php -f other/prepare_release.php
 *
 * To manually specify a version string, do this:
 *
 *     php -f other/prepare_release.php 'version_string_here'
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

foreach (
	[
		'UnicodeDataUpdater',
		'AsciiTransliteratorDataUpdater',
		'TimezoneDataUpdater',
		'VersionNumberUpdater',
	]
	as $class_name
) {
	$file_name = 'Updaters/' . $class_name . '.php';
	$fully_qualified_class_name = __NAMESPACE__ . '\\Updaters\\' . $class_name;

	require_once $file_name;

	$updater = new $fully_qualified_class_name('prepare_release');

	if ($class_name === 'VersionNumberUpdater') {
		$updater->execute($argv[1] ?? null);
	} else {
		$updater->execute();
	}

	if (shell_exec('git status --porcelain') !== null) {
		die('Commit current changes, then run this script again to continue.');
	}
}
