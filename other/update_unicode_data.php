<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file exists to make it easy for developers to update the
 * Unicode data in $sourcedir/Unicode whenever a new version of the
 * Unicode Character Database is released. Just run this file from the
 * command line in order to perform the update.
 *
 * Note:
 *
 *  1. Any updates to the Unicode data files SHOULD be included in the
 *     install and large upgrade packages.
 *
 * 	2. Any updates to the Unicode data files SHOULD NOT be included in
 *     the patch packages. The Update_Unicode background task will take
 *     care of that on existing forums.
 *
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

// 1. Set a couple of variables that we'll need.
$boarddir = realpath(dirname(__DIR__));
$sourcedir = $boarddir . '/Sources';

// 2. Impersonate cron.php
define('SMF', 'BACKGROUND');
define('SMF_USER_AGENT', 'SMF');
define('TIME_START', microtime(true));

// 3. Borrow a bit of stuff from index.php.
$index_php_start = file_get_contents($boarddir . '/index.php', false, null, 0, 4096);

foreach (array('SMF_VERSION', 'SMF_SOFTWARE_YEAR') as $const)
{
	preg_match("/define\('$const', '([^)]+)'\);/", $index_php_start, $matches);

	if (empty($matches[1]))
		die("Could not find value for $const in index.php");

	define($const, $matches[1]);
}

// 4. Get some more stuff we need.
require_once($sourcedir . '/Autoloader.php');
SMF\Config::$boarddir = $boarddir;
SMF\Config::$sourcedir = $sourcedir;

// 5. Do the job.
$unicode_updater = new SMF\Tasks\UpdateUnicode(array());
$unicode_updater->execute();

?>