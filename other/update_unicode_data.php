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
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.4
 */

// 1. Set a couple of variables that we'll need.
$boarddir = realpath(dirname(__DIR__));
$sourcedir = $boarddir . '/Sources';

// 2. Borrow a bit of stuff from cron.php.
$cron_php_start = file_get_contents($boarddir . '/cron.php', false, null, 0, 4096);

foreach (array('SMF', 'SMF_VERSION', 'SMF_SOFTWARE_YEAR') as $const)
{
	preg_match("/define\('$const', '([^)]+)'\);/", $cron_php_start, $matches);

	if (empty($matches[1]))
		die("Could not find value for $const in cron.php");

	define($const, $matches[1]);
}

define('SMF_USER_AGENT', 'SMF');
define('MAX_CLAIM_THRESHOLD', 300);
define('TIME_START', microtime(true));

abstract class SMF_BackgroundTask
{
	abstract public function execute();
}

// This should never be needed, but set it for completeness.
$smcFunc['db_insert'] = function($method, $table, $columns, $data, $keys, $returnmode = 0, $connection = null) {};

// 3. Do the job.
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/tasks/UpdateUnicode.php');

$unicode_updater = new Update_Unicode();
$unicode_updater->execute();

?>