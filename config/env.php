<?php

declare(strict_types=1);

/*
 * 1. Define some constants we need.
 */

 if (!defined('SMF')) {
	define('SMF', 1);
}

if (!defined('SMF_VERSION')) {
	define('SMF_VERSION', '3.0 Alpha 2');
}

if (!defined('SMF_FULL_VERSION')) {
	define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
}

if (!defined('SMF_SOFTWARE_YEAR')) {
	define('SMF_SOFTWARE_YEAR', '2025');
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
	define('SMF_SETTINGS_FILE', __DIR__ . '/../src/SMF/Settings.php');
}

if (!defined('SMF_SETTINGS_BACKUP_FILE')) {
	define('SMF_SETTINGS_BACKUP_FILE', dirname(SMF_SETTINGS_FILE) . '/' . pathinfo(SMF_SETTINGS_FILE, PATHINFO_FILENAME) . '_bak.php');
}