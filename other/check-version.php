<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

try
{
	$versions = array();
	$years = array();
	foreach (array('./index.php', './SSI.php', './cron.php', './proxy.php', './other/install.php', './other/upgrade.php') as $path)
	{
		$contents = file_get_contents($path, false, null, 0, 1250);

		if (!preg_match('/define\(\'SMF_VERSION\', \'([^\']+)\'\);/i', $contents, $versionResults))
			throw new Exception('Error: Could not locate SMF_VERSION in ' . $path);
		$versions[$versionResults[1]][] = $path;

		if (!preg_match('/define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);/i', $contents, $yearResults))
			throw new Exception('Error: Could not locate SMF_SOFTWARE_YEAR in ' . $path);
		$years[$yearResults[1]][] = $path;
	}

	if (count($versions) != 1)
	{
		$errmsg = 'Error: SMF_VERSION differs between files.';
		foreach ($versions as $version => $paths)
			$errmsg .= ' "' . $version . '" in ' . implode(', ', $paths) . '.';
		throw new Exception($errmsg);
	}

	if (count($years) != 1)
	{
		$errmsg = 'Error: SMF_SOFTWARE_YEAR differs between files.';
		foreach ($years as $year => $paths)
			$errmsg .= ' "' . $year . '" in ' . implode(', ', $paths) . '.';
		throw new Exception($errmsg);
	}

	if (!preg_match('~^((\d+)\.(\d+)[. ]?((?:(?<= )(?>RC|Beta |Alpha ))?\d+)?)$~', key($versions)))
		throw new Exception('Error: SMF_VERSION string is invalid: "' . key($versions) . '"');

	if (($headyear = (int) substr(shell_exec('git show -s --format=%ci HEAD'), 0, 4)) > (int) key($years))
		throw new Exception('Error: SMF_SOFTWARE_YEAR is ' . (int) key($years) . ', should be ' . $headyear . '.');
}
catch (Exception $e)
{
	fwrite(STDERR, $e->getMessage());
	exit(1);
}