<?php

/**
 * The settings file contains all of the basic settings that need to be present when a database/cache is not available.
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

########## Maintenance ##########
/**
 * @var int 0, 1, 2
 *
 * The maintenance "mode":
 * 0: Disable maintenance mode. This is the default.
 * 1: Enable maintenance mode but allow admins to login normally.
 * 2: Make the forum untouchable. You'll need to make it 0 again manually!
 */
$maintenance = 0;
/**
 * @var string
 *
 * Title for the Maintenance Mode message.
 */
$mtitle = 'Maintenance Mode';
/**
 * @var string
 *
 * Description of why the forum is in maintenance mode.
 */
$mmessage = 'Okay faithful users...we\'re attempting to restore an older backup of the database...news will be posted once we\'re back!';

########## Forum Info ##########
/**
 * @var string
 *
 * The name of your forum.
 */
$mbname = 'My Community';
/**
 * @var string
 *
 * The default language file set for the forum.
 */
$language = 'english';
/**
 * @var string
 *
 * URL to your forum's folder. (without the trailing /!)
 */
$boardurl = 'http://127.0.0.1/smf';
/**
 * @var string
 *
 * Email address to send emails from. (like noreply@yourdomain.com.)
 */
$webmaster_email = 'noreply@myserver.com';
/**
 * @var string
 *
 * Name of the cookie to set for authentication.
 */
$cookiename = 'SMFCookie11';

########## Database Info ##########
/**
 * @var string
 *
 * The database type.
 * Default options: mysql, postgresql
 */
$db_type = 'mysql';
/**
 * @var int
 *
 * The database port.
 * 0 to use default port for the database type.
 */
$db_port = 0;
/**
 * @var string
 *
 * The server to connect to (or a Unix socket)
 */
$db_server = 'localhost';
/**
 * @var string
 *
 * The database name.
 */
$db_name = 'smf';
/**
 * @var string
 *
 * Database username.
 */
$db_user = 'root';
/**
 * @var string
 *
 * Database password.
 */
$db_passwd = '';
/**
 * @var string
 *
 * Database user for when connecting with SSI.
 */
$ssi_db_user = '';
/**
 * @var string
 *
 * Database password for when connecting with SSI.
 */
$ssi_db_passwd = '';
/**
 * @var string
 *
 * A prefix to put in front of your table names.
 * This helps to prevent conflicts.
 */
$db_prefix = 'smf_';
/**
 * @var bool
 *
 * Use a persistent database connection.
 */
$db_persist = false;
/**
 * @var bool
 *
 * Send emails on database connection error.
 */
$db_error_send = false;
/**
 * @var null|bool
 *
 * Override the default behavior of the database layer for mb4 handling.
 * null keep the default behavior untouched.
 */
$db_mb4 = null;

########## Cache Info ##########
/**
 * @var string
 *
 * Select a cache system. You should leave this up to the cache area of the
 * admin panel for proper detection of the available options.
 */
$cache_accelerator = '';
/**
 * @var int
 *
 * The level at which you would like to cache.
 * Between 0 (off) through 3 (cache a lot).
 */
$cache_enable = 0;
/**
 * @var array
 *
 * This is only used for memcache / memcached.
 * Should be a string of 'server:port,server:port'
 */
$cache_memcached = '';
/**
 * @var string
 *
 * Path to the cache directory for the file-based cache system.
 */
$cachedir = dirname(__FILE__) . '/cache';

########## Image Proxy ##########
/**
 * @var bool
 *
 * Whether the proxy is enabled or not.
 */
$image_proxy_enabled = true;
/**
 * @var string
 *
 * Secret key to be used by the proxy.
 */
$image_proxy_secret = 'smfisawesome';
/**
 * @var int
 *
 * Maximum file size (in KB) for individual files.
 */
$image_proxy_maxsize = 5192;

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
/**
 * @var string
 *
 * The absolute path to the forum's folder. (not just '.'!)
 */
$boarddir = dirname(__FILE__);
/**
 * @var string
 *
 * Path to the Sources directory.
 */
$sourcedir = dirname(__FILE__) . '/Sources';
/**
 * @var string
 *
 * Path to the Packages directory.
 */
$packagesdir = dirname(__FILE__) . '/Packages';
/**
 * @var string
 *
 * Path to the tasks directory.
 */
$tasksdir = $sourcedir . '/Tasks';

# Make sure the paths are correct... at least try to fix them.
if (!is_dir(realpath($boarddir)) && file_exists(dirname(__FILE__) . '/agreement.txt'))
	$boarddir = dirname(__FILE__);
if (!is_dir(realpath($sourcedir)) && is_dir($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';
if (!is_dir(realpath($tasksdir)) && is_dir($sourcedir . '/Tasks'))
	$tasksdir = $sourcedir . '/Tasks';
if (!is_dir(realpath($packagesdir)) && is_dir($boarddir . '/Packages'))
	$packagesdir = $boarddir . '/Packages';
if (!is_dir(realpath($cachedir)) && is_dir($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

######### Legacy Settings #########
/**
 * @var string
 *
 * Database character set. Should always be utf8.
 */
$db_character_set = 'utf8';

########## Error-Catching ##########
# Note: You shouldn't touch these settings.
if (file_exists((isset($cachedir) ? $cachedir : dirname(__FILE__)) . '/db_last_error.php'))
	include((isset($cachedir) ? $cachedir : dirname(__FILE__)) . '/db_last_error.php');

if (!isset($db_last_error))
{
	// File does not exist so lets try to create it
	file_put_contents((isset($cachedir) ? $cachedir : dirname(__FILE__)) . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = 0;' . "\n" . '?' . '>');
	$db_last_error = 0;
}

if (file_exists(dirname(__FILE__) . '/install.php'))
{
	$secure = false;
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
		$secure = true;
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
		$secure = true;

	if (basename($_SERVER['PHP_SELF']) != 'install.php')
	{
		header('location: http' . ($secure ? 's' : '') . '://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/install.php');
		exit;
	}
}

?>