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
 * @copyright 2023 Simple Machines and individual contributors
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

if (!defined('SMF'))
	define('SMF', 1);

if (!defined('SMF_VERSION'))
	define('SMF_VERSION', '3.0 Alpha 1');

if (!defined('SMF_FULL_VERSION'))
	define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);

if (!defined('SMF_SOFTWARE_YEAR'))
	define('SMF_SOFTWARE_YEAR', '2023');

if (!defined('JQUERY_VERSION'))
	define('JQUERY_VERSION', '3.6.3');

if (!defined('POSTGRE_TITLE'))
	define('POSTGRE_TITLE', 'PostgreSQL');

if (!defined('MYSQL_TITLE'))
	define('MYSQL_TITLE', 'MySQL');

if (!defined('SMF_USER_AGENT'))
	define('SMF_USER_AGENT', 'Mozilla/5.0 (' . php_uname('s') . ' ' . php_uname('m') . ') AppleWebKit/605.1.15 (KHTML, like Gecko)  SMF/' . strtr(SMF_VERSION, ' ', '.'));

if (!defined('TIME_START'))
	define('TIME_START', microtime(true));

/*
 * 2. Load the Settings.php file.
 */

// Don't load it twice.
if (in_array(dirname(__FILE__) . '/Settings.php', get_included_files()))
	return;

if (SMF === 1)
{
	// If anything goes wrong loading Settings.php, make sure the admin knows it.
	error_reporting(E_ALL);

	// This makes it so headers can be sent!
	ob_start();
}

// Do some cleaning, just in case.
foreach (array('db_character_set', 'cachedir') as $variable)
	$GLOBALS[$variable] = null;

// Load the settings...
require_once(dirname(__FILE__) . '/Settings.php');

if (SMF === 1)
{
	// Devs want all error messages, but others don't.
	error_reporting(!empty($db_show_debug) ? E_ALL : E_ALL & ~E_DEPRECATED);
}

// Ensure there are no trailing slashes in these variables.
foreach (array('boardurl', 'boarddir', 'sourcedir', 'packagesdir', 'tasksdir', 'cachedir') as $variable)
	$GLOBALS[$variable] = rtrim($GLOBALS[$variable], "\\/");

// Make sure the paths are correct... at least try to fix them.
// @todo Remove similar path correction code from Settings.php.
if (empty($boarddir) || !is_dir(realpath($boarddir)))
	$boarddir = __DIR__;
if ((empty($sourcedir) || !is_dir(realpath($sourcedir))) && is_dir($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';
if ((empty($tasksdir) || !is_dir(realpath($tasksdir))) && is_dir($sourcedir . '/tasks'))
	$tasksdir = $sourcedir . '/tasks';
if ((empty($packagesdir) || !is_dir(realpath($packagesdir))) && is_dir($boarddir . '/Packages'))
	$packagesdir = $boarddir . '/Packages';

// Make absolutely sure the cache directory is defined and writable.
if (empty($cachedir) || !is_dir($cachedir) || !is_writable($cachedir))
{
	if (is_dir($boarddir . '/cache') && is_writable($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';

	else
	{
		$cachedir = sys_get_temp_dir() . '/smf_cache_' . md5($boarddir);

		@mkdir($cachedir, 0750);
	}
}

/*
 * 3. Load some other essential includes.
 */

// Some entry points need more includes than others.
switch (SMF)
{
	case 1:
	case 'SSI':
		require_once($sourcedir . '/QueryString.php');
		require_once($sourcedir . '/Subs-Auth.php');
		require_once($sourcedir . '/Session.php');
		require_once($sourcedir . '/Logging.php');
		// no break

	case 'BACKGROUND':
		require_once($sourcedir . '/Security.php');
		// no break

	default:
		require_once($sourcedir . '/Subs.php');
		require_once($sourcedir . '/Errors.php');
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/Autoloader.php');
		break;
}

// Ensure we don't trip over disabled internal functions
require_once($sourcedir . '/Subs-Compat.php');


/*********************************************************************
 * From this point forward, do stuff specific to normal forum loading.
 *********************************************************************/

if (SMF === 1)
{
	(new SMF\Forum())->execute();
}

?>