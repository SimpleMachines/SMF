<?php

/**
 * This is the file where SMF gets initialized. It defines common constants,
 * loads the settings in Settings.php, ensures all the directory paths are
 * correct, and includes some essential source files.
 *
 * If this file is included by another file, the initialization is all that will
 * happen. But if this file is executed directly (the typical scenario), then it
 * will also instantiate and execute an SMF\Forum object.
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

/********************************************************
 * Initialize things that are common to all entry points.
 * (i.e. index.php, SSI.php, cron.php, proxy.php)
 ********************************************************/

/*
 * 1. Define some constants we need.
 */

if (!defined('SMF')) {
	define('SMF', 1);
}

if (!defined('SMF_VERSION')) {
	define('SMF_VERSION', '3.0 Alpha 1');
}

if (!defined('SMF_FULL_VERSION')) {
	define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
}

if (!defined('SMF_SOFTWARE_YEAR')) {
	define('SMF_SOFTWARE_YEAR', '2024');
}

if (!defined('JQUERY_VERSION')) {
	define('JQUERY_VERSION', '3.6.3');
}

if (!defined('POSTGRE_TITLE')) {
	define('POSTGRE_TITLE', 'PostgreSQL');
}

if (!defined('MYSQL_TITLE')) {
	define('MYSQL_TITLE', 'MySQL');
}

if (!defined('SMF_USER_AGENT')) {
	define('SMF_USER_AGENT', 'Mozilla/5.0 (' . php_uname('s') . ' ' . php_uname('m') . ') AppleWebKit/605.1.15 (KHTML, like Gecko)  SMF/' . strtr(SMF_VERSION, ' ', '.'));
}

if (!defined('TIME_START')) {
	define('TIME_START', microtime(true));
}

if (!defined('SMF_SETTINGS_FILE')) {
	define('SMF_SETTINGS_FILE', __DIR__ . '/Settings.php');
}

if (!defined('SMF_SETTINGS_BACKUP_FILE')) {
	define('SMF_SETTINGS_BACKUP_FILE', dirname(SMF_SETTINGS_FILE) . '/' . pathinfo(SMF_SETTINGS_FILE, PATHINFO_FILENAME) . '_bak.php');
}

/*
 * 2. Load the Settings.php file.
 */

if (!is_file(SMF_SETTINGS_FILE) || !is_readable(SMF_SETTINGS_FILE)) {
	die('File not readable: ' . basename(SMF_SETTINGS_FILE));
}

// Don't load it twice.
if (in_array(SMF_SETTINGS_FILE, get_included_files())) {
	return;
}

// If anything goes wrong loading Settings.php, make sure the admin knows it.
if (SMF === 1) {
	error_reporting(E_ALL);
	ob_start();
}

// This is wrapped in a closure to keep the global namespace clean.
call_user_func(function () {
	require_once SMF_SETTINGS_FILE;

	// Ensure $sourcedir is valid.
	$sourcedir = rtrim($sourcedir, '\\/');

	if ((empty($sourcedir) || !is_dir(realpath($sourcedir)))) {
		$boarddir = rtrim($boarddir, '\\/');

		if (empty($boarddir) || !is_dir(realpath($boarddir))) {
			$boarddir = __DIR__;
		}

		if (is_dir($boarddir . '/Sources')) {
			$sourcedir = $boarddir . '/Sources';
		}
	}

	// We need this class, or nothing works.
	if (!is_file($sourcedir . '/Config.php') || !is_readable($sourcedir . '/Config.php')) {
		die('File not readable: (Sources)/Config.php');
	}

	// Pass all the settings to SMF\Config.
	require_once $sourcedir . '/Config.php';
	SMF\Config::set(get_defined_vars());
});

// Devs want all error messages, but others don't.
if (SMF === 1) {
	error_reporting(!empty(SMF\Config::$db_show_debug) ? E_ALL : E_ALL & ~E_DEPRECATED);
}

/*
 * 3. Load some other essential includes.
 */

require_once SMF\Config::$sourcedir . '/Autoloader.php';

// Ensure we don't trip over disabled internal functions
require_once SMF\Config::$sourcedir . '/Subs-Compat.php';


/*********************************************************************
 * From this point forward, do stuff specific to normal forum loading.
 *********************************************************************/

if (SMF === 1) {
	(new SMF\Forum())->execute();
}

?>