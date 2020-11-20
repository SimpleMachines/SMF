<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
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

	// Language Files are ignored as they don't use the License format.
	'./Themes/default/languages/[A-Za-z0-9]+\.english\.php',

	// Cache and miscellaneous.
	'\./cache/',
	'\./other/',
	'\./tests/',
	'\./vendor/',

	// Minify Stuff.
	'\./Sources/minify/',

	// random_compat().
	'\./Sources/random_compat/',

	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/',

	// We will ignore Settings.php if this is a live dev site.
	'\./Settings\.php',
	'\./Settings_bak\.php',
	'\./db_last_error\.php',
);

try
{
	if (($indexFile = fopen('./index.php', 'r')) !== false)
	{
		$indexContents = fread($indexFile, 1250);

		if (!preg_match('~define\(\'SMF_VERSION\', \'([^\']+)\'\);~i', $indexContents, $versionResults))
			throw new Exception('Could not locate SMF_VERSION');
		$currentVersion = $versionResults[1];

		if (!preg_match('~define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);~i', $indexContents, $yearResults))
			throw new Exception('Could not locate SMF_SOFTWARE_YEAR');
		$currentSoftwareYear = (int) $yearResults[1];

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::UNIX_PATHS)) as $currentFile => $fileInfo)
		{
			if ($fileInfo->getExtension() == 'php')
			{
				foreach ($ignoreFiles as $if)
					if (preg_match('~' . $if . '~i', $currentFile))
						continue 2;

				if (($file = fopen($currentFile, 'r')) !== false)
				{
					// Some files, *cough* ManageServer *cough* have lots of junk before the license, otherwise this could easily be 500.
					$contents = fread($file, 4000);

					// How the license file should look, in a regex type format.
					$match = array(
						0 => '\* Simple Machines Forum \(SMF\)' . '[\r]?\n',
						1 => ' \*' . '[\r]?\n',
						2 => ' \* @package SMF' . '[\r]?\n',
						3 => ' \* @author Simple Machines https?://www.simplemachines.org' . '[\r]?\n',
						4 => ' \* @copyright \d{4} Simple Machines and individual contributors' . '[\r]?\n',
						5 => ' \* @license https?://www.simplemachines.org/about/smf/license.php BSD' . '[\r]?\n',
						6 => ' \*' . '[\r]?\n',
						7 => ' \* @version',
					);

					// Just see if the license is there.
					if (!preg_match('~' . implode('', $match) . '~i', $contents))
						throw new Exception('License File is invalid or not found in ' . $currentFile);

					// Check the year is correct.
					$yearMatch = $match;
					$yearMatch[4] = ' \* @copyright ' . $currentSoftwareYear . ' Simple Machines and individual contributors' . '[\r]?\n';
					if (!preg_match('~' . implode('', $yearMatch) . '~i', $contents))
						throw new Exception('The software year is incorrect in ' . $currentFile);

					// Check the version is correct.
					$versionMatch = $match;
					$versionMatch[7] = ' \* @version ' . $currentVersion . '[\r]?\n';
					if (!preg_match('~' . implode('', $versionMatch) . '~i', $contents))
						throw new Exception('The version is incorrect in ' . $currentFile);
				}
				else
					throw new Exception('Unable to open file ' . $currentFile);
			}
		}
	}
	else
		throw new Exception('Unable to open file ./index.php');
}
catch (Exception $e)
{
	fwrite(STDERR, $e->getMessage());
	exit(1);
}