<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file automatically updates version numbers and copyright years
 * in any SMF files that need it in order to prepare for a new release.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */

$release_branch = 'release-2.1';

$basedir = dirname(__DIR__);

// Do nothing if on wrong branch or working tree is dirty
if (trim(shell_exec('cd "' . $basedir . '"; if [[ "$(git rev-parse --abbrev-ref HEAD)" != "' . $release_branch . '" || -n "$(git status --porcelain)" ]]; then echo "abort"; else echo; fi')) !== '')
{
	die('Could not continue. Wrong branch or dirty working tree.');
}

$prev_version = ltrim(trim(shell_exec('cd "' . $basedir . '"; git checkout "' . $release_branch . '" &>/dev/null; git describe --tags --abbrev=0')), 'v');

$new_version = array_pad(explode('.', $prev_version), 3, 0);
$new_version[2]++;
$new_version = implode('.', $new_version);

$year = date_format(date_create(), 'Y');
$always_update = array('index.php', 'cron.php', 'proxy.php', 'SSI.php', 'other/install.php', 'other/upgrade.php', 'other/upgrade-helper.php', 'other/Settings.php', 'other/Settings_bak.php');

// Update SMF_VERSION and SMF_SOFTWARE_YEAR
foreach ($always_update as $file)
{
	$content = $original_content = file_get_contents("$basedir/$file");

	$content = preg_replace("~define\('SMF_VERSION', '\d+\.\d+\.\d+'\);~", "define('SMF_VERSION', '$new_version');", $content);
	$content = preg_replace("~define\('SMF_SOFTWARE_YEAR', '\d{4}'\);~", "define('SMF_SOFTWARE_YEAR', '$year');", $content);

	if ($content !== $original_content)
		file_put_contents("$basedir/$file", $content);
}

// Update license blocks
$license_pattern = '~(' . preg_quote('* @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright ') . ')\d{4}(' . preg_quote(' Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version ') . ')\d+\.\d+\.\d+~';

$lang_pattern = '~// Version: \K\d+\.\d+\.\d+~';

$files = array_unique(array_merge($always_update, array_filter(explode("\n", shell_exec('cd "' . $basedir . '"; git diff --name-only v' . $prev_version . '...HEAD | grep -E \'\.php$\' | sort')), 'strlen')));
foreach ($files as $file)
{
	$content = $original_content = file_get_contents("$basedir/$file");

	if (preg_match($license_pattern, $content))
	{
		$content = preg_replace($license_pattern, '${1}' . $year . '${2}' . $new_version, $content);
	}
	elseif (preg_match($lang_pattern, $content))
	{
		$content = preg_replace($lang_pattern, $new_version, $content);
	}
	else
	{
		echo "Could not update $file\n";
		continue;
	}

	if ($content !== $original_content)
		file_put_contents("$basedir/$file", $content);
}

// Update SMF_LANG_VERSION
preg_match($lang_pattern, file_get_contents("$basedir/Themes/default/languages/index.english.php"), $matches);

$lang_version = $matches[0];

$content = $original_content = file_get_contents("$basedir/other/upgrade.php");
$content = preg_replace("~define\('SMF_LANG_VERSION', '\d+\.\d+\.\d+'\);~", "define('SMF_LANG_VERSION', '$lang_version');", $content);

if ($content !== $original_content)
	file_put_contents("$basedir/other/upgrade.php", $content);

?>