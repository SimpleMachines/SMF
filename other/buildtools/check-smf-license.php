<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

// Stuff we will ignore.
$ignoreFiles = array(
	// Index files.
	'\./attachments/index\.php',
	'\./avatars/index\.php',
	'\./avatars/[A-Za-z0-9]+/index\.php',
	'\./cache/index\.php',
	'\./custom_avatar/index\.php',
	'\./Packages/index\.php',
	'\./Packages/backups/index\.php',
	'\./Smileys/[A-Za-z0-9]+/index\.php',
	'\./Smileys/index\.php',
	'\./Sources/index\.php',
	'\./Sources/tasks/index\.php',
	'\./Themes/default/css/index\.php',
	'\./Themes/default/fonts/index\.php',
	'\./Themes/default/fonts/sound/index\.php',
	'\./Themes/default/images/[A-Za-z0-9]+/index\.php',
	'\./Themes/default/images/index\.php',
	'\./Themes/default/index\.php',
	'\./Themes/default/languages/index\.php',
	'\./Themes/default/scripts/index\.php',
	'\./Themes/index\.php',

	// Minify Stuff.
	'\./Sources/minify/[A-Za-z0-9/-]+\.php',

	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/[A-Za-z0-9]+\.php',
	'\./Sources/ReCaptcha/RequestMethod/[A-Za-z0-9]+\.php',

	// Language Files are ignored as they don't use the License format.
	'./Themes/default/languages/[A-Za-z0-9]+\.english\.php',

	// Cache and miscellaneous.
	'\./cache/data_[A-Za-z0-9-_]\.php',
	'\./other/db_last_error.php',
);

// No file? Thats bad.
if (!isset($_SERVER['argv'], $_SERVER['argv'][1]))
	die('Error: No File specified' . "\n");

// The file has to exist.
$currentFile = $_SERVER['argv'][1];
if (!file_exists($currentFile))
	die('Error: File does not exist' . "\n");

// Is this ignored?
foreach ($ignoreFiles as $if)
	if (preg_match('~' . $if . '~i', $currentFile))
		die;

// Lets get the main index.php for $forum_version and $software_year.
$indexFile = fopen('./index.php', 'r');
$indexContents = fread($indexFile, 850);

if (!preg_match('~\$forum_version = \'SMF ([^\']+)\';~i', $indexContents, $versionResults))
	die('Error: Could not locate $forum_version' . "\n");
$currentVersion = $versionResults[1];

if (!preg_match('~\$software_year = \'(\d{4})\';~i', $indexContents, $yearResults))
	die('Error: Could not locate $software_year' . "\n");
$currentSoftwareYear = (int) $yearResults[1];

$file = fopen($currentFile, 'r');

// Some files, *cough* ManageServer *cough* have lots of junk before the license, otherwise this could easily be 500.
$contents = fread($file, 4000);

// How the license file should look, in a regex type format.
$match = array(
	0 => '\* Simple Machines Forum \(SMF\)' . '[\r]?\n',
	1 => ' \*' . '[\r]?\n',
	2 => ' \* @package SMF' . '[\r]?\n',
	3 => ' \* @author Simple Machines http://www.simplemachines.org' . '[\r]?\n',
	4 => ' \* @copyright \d{4} Simple Machines and individual contributors' . '[\r]?\n',
	5 => ' \* @license http://www.simplemachines.org/about/smf/license.php BSD' . '[\r]?\n',
	6 => ' \*' . '[\r]?\n',
	7 => ' \* @version',
);

// Just see if the license is there.
if (!preg_match('~' . implode('', $match) . '~i', $contents))
	die('Error: License File is invalid or not found in ' . $currentFile . "\n");

// Check the year is correct.
$yearMatch = $match;
$yearMatch[4] = ' \* @copyright ' . $currentSoftwareYear . ' Simple Machines and individual contributors' . '[\r]?\n';
if (!preg_match('~' . implode('', $yearMatch) . '~i', $contents))
	die('Error: The software year is incorrect in ' . $currentFile . "\n");

// Check the version is correct.
$versionMatch = $match;
$versionMatch[7] = ' \* @version ' . $currentVersion . '[\r]?\n';
if (!preg_match('~' . implode('', $versionMatch) . '~i', $contents))
	die('Error: The version is incorrect in ' . $currentFile . "\n");