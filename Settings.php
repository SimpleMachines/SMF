<?php

/**
 * The settings file contains all of the basic settings that need to be present when a database/cache is not available.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

########## Maintenance ##########
/**
 * The maintenance "mode"
 * Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you'll have to make it 0 again manually!)
 * 0 is default and disables maintenance mode.
 *
 * @var int 0, 1, 2
 * @global int $maintenance
 */
$maintenance = 0;
/**
 * Title for the Maintenance Mode message.
 *
 * @var string
 * @global int $mtitle
 */
$mtitle = 'Включен режим обслуживания';
/**
 * Description of why the forum is in maintenance mode.
 *
 * @var string
 * @global string $mmessage
 */
$mmessage = 'Убираем старые баги, добавляем новые... Скоро форум станет лучше!';

########## Forum Info ##########
/**
 * The name of your forum.
 *
 * @var string
 */
$mbname = 'Бигуди';
/**
 * The default language file set for the forum.
 *
 * @var string
 */
$language = 'russian';
/**
 * URL to your forum's folder. (without the trailing /!)
 *
 * @var string
 */
$boardurl = 'https://forum.wabisabi.by';
/**
 * Email address to send emails from. (like noreply@yourdomain.com.)
 *
 * @var string
 */
$webmaster_email = 'heisikk@wabisabi.by';
/**
 * Name of the cookie to set for authentication.
 *
 * @var string
 */
$cookiename = 'SMFCookie619';

########## Database Info ##########
/**
 * The database type
 * Default options: mysql, postgresql
 *
 * @var string
 */
$db_type = 'mysql';
/**
 * The server to connect to (or a Unix socket)
 *
 * @var string
 */
$db_server = 'localhost';
/**
 * The database name
 *
 * @var string
 */
$db_name = 'forum-ws';
/**
 * Database username
 *
 * @var string
 */
$db_user = 'forum-ws';
/**
 * Database password
 *
 * @var string
 */
$db_passwd = '9B5v7B7f';
/**
 * Database user for when connecting with SSI
 *
 * @var string
 */
$ssi_db_user = '';
/**
 * Database password for when connecting with SSI
 *
 * @var string
 */
$ssi_db_passwd = '';
/**
 * A prefix to put in front of your table names.
 * This helps to prevent conflicts
 *
 * @var string
 */
$db_prefix = 'smf_';
/**
 * Use a persistent database connection
 *
 * @var int|bool
 */
$db_persist = 0;
/**
 *
 * @var int|bool
 */
$db_error_send = 0;
/**
 * Override the default behavior of the database layer for mb4 handling
 * null keep the default behavior untouched
 *
 * @var null|bool
 */
$db_mb4 = null;

########## Cache Info ##########
/**
 * Select a cache system. You want to leave this up to the cache area of the admin panel for
 * proper detection of apc, memcached, output_cache, smf, or xcache
 * (you can add more with a mod).
 *
 * @var string
 */
$cache_accelerator = '';
/**
 * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).
 *
 * @var int
 */
$cache_enable = 0;
/**
 * This is only used for memcache / memcached. Should be a string of 'server:port,server:port'
 *
 * @var array
 */
$cache_memcached = '';
/**
 * This is only for the 'smf' file cache system. It is the path to the cache directory.
 * It is also recommended that you place this in /tmp/ if you are going to use this.
 *
 * @var string
 */
$cachedir = '/var/www/heisikkr/data/www/forum.wabisabi.by/cache';

########## Image Proxy ##########
# This is done entirely in Settings.php to avoid loading the DB while serving the images
/**
 * Whether the proxy is enabled or not
 *
 * @var bool
 */
$image_proxy_enabled = 1;

/**
 * Secret key to be used by the proxy
 *
 * @var string
 */
$image_proxy_secret = '596e3f883192f2c8fd38';

/**
 * Maximum file size (in KB) for individual files
 *
 * @var int
 */
$image_proxy_maxsize = 5192;

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
/**
 * The absolute path to the forum's folder. (not just '.'!)
 *
 * @var string
 */
$boarddir = '/var/www/heisikkr/data/www/forum.wabisabi.by';
/**
 * Path to the Sources directory.
 *
 * @var string
 */
$sourcedir = '/var/www/heisikkr/data/www/forum.wabisabi.by/Sources';
/**
 * Path to the Packages directory.
 *
 * @var string
 */
$packagesdir = '/var/www/heisikkr/data/www/forum.wabisabi.by/Packages';
/**
 * Path to the tasks directory.
 *
 * @var string
 */
$tasksdir = '/var/www/heisikkr/data/www/forum.wabisabi.by/Sources/tasks';

# Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/agreement.txt'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';
if (!file_exists($cachedir) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

######### Legacy Settings #########
# UTF-8 is now the only character set supported in 2.1.
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

?>