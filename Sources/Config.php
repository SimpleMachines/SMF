<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * Handles loading and saving SMF's settings, both in Settings.php and database.
 * Handles checking and modifying certain server and forum configuration values.
 */
class Config
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var bool
	 *
	 * Master switch to enable backward compatibility behaviours.
	 */
	public static bool $backward_compatibility = true;

	# ######### Maintenance ##########
	/**
	 * @var int 0, 1, 2
	 *
	 * The maintenance "mode":
	 * 0: Disable maintenance mode. This is the default.
	 * 1: Enable maintenance mode but allow admins to login normally.
	 * 2: Make the forum untouchable. You'll need to make it 0 again manually!
	 */
	public static $maintenance;
	/**
	 * @var string
	 *
	 * Title for the maintenance mode message.
	 */
	public static $mtitle;
	/**
	 * Description of why the forum is in maintenance mode.
	 *
	 * @var string
	 */
	public static $mmessage;

	# ######### Forum info ##########
	/**
	 * @var string
	 *
	 * The name of your forum.
	 */
	public static $mbname;
	/**
	 * @var string
	 *
	 * The default language file set for the forum.
	 */
	public static $language;
	/**
	 * @var string
	 *
	 * URL to your forum's folder. (without the trailing /!)
	 */
	public static $boardurl;
	/**
	 * @var string
	 *
	 * Email address to send emails from. (like noreply@yourdomain.com.)
	 */
	public static $webmaster_email;
	/**
	 * @var string
	 *
	 * Name of the cookie to set for authentication.
	 */
	public static $cookiename;

	# ######### Database info ##########
	/**
	 * @var string
	 *
	 * The database type.
	 * Default options: mysql, postgresql
	 */
	public static $db_type;
	/**
	 * @var int
	 *
	 * The database port.
	 * 0 to use default port for the database type.
	 */
	public static $db_port;
	/**
	 * @var string
	 *
	 * The server to connect to (or a Unix socket)
	 */
	public static $db_server;
	/**
	 * @var string
	 *
	 * The database name.
	 */
	public static $db_name;
	/**
	 * @var string
	 *
	 * Database username.
	 */
	public static $db_user;
	/**
	 * @var string
	 *
	 * Database password.
	 */
	public static $db_passwd;
	/**
	 * @var string
	 *
	 * Database user for when connecting with SSI.
	 */
	public static $ssi_db_user;
	/**
	 * @var string
	 *
	 * Database password for when connecting with SSI.
	 */
	public static $ssi_db_passwd;
	/**
	 * @var string
	 *
	 * A prefix to put in front of your table names.
	 * This helps to prevent conflicts.
	 */
	public static $db_prefix;
	/**
	 * @var bool
	 *
	 * Use a persistent database connection.
	 */
	public static $db_persist;
	/**
	 * @var bool
	 *
	 * Send emails on database connection error.
	 */
	public static $db_error_send;
	/**
	 * @var null|bool
	 *
	 * Override the default behavior of the database layer for mb4 handling.
	 * null keep the default behavior untouched.
	 */
	public static $db_mb4;

	# ######### Cache info ##########
	/**
	 * @var string
	 *
	 * Select a cache system. You should leave this up to the cache area of the
	 * admin panel for proper detection of the available options.
	 */
	public static $cache_accelerator;
	/**
	 * @var int
	 *
	 * The level at which you would like to cache.
	 * Between 0 (off) through 3 (cache a lot).
	 */
	public static $cache_enable;
	/**
	 * @var array
	 *
	 * This is only used for the memcache / memcached cache systems.
	 * Should be a string of 'server:port,server:port'
	 */
	public static $cache_memcached;
	/**
	 * @var string
	 *
	 * Path to the cache directory for the file-based cache system.
	 */
	public static $cachedir;
	/**
	 * @var string
	 *
	 * This is only used for the SQLite3 cache system.
	 * Path to the directory where the SQLite3 database file will be saved.
	 */
	public static $cachedir_sqlite;

	# ######### Image proxy ##########
	/**
	 * @var bool
	 *
	 * Whether the proxy is enabled or not.
	 */
	public static $image_proxy_enabled;
	/**
	 * @var string
	 *
	 * Secret key to be used by the proxy.
	 */
	public static $image_proxy_secret;
	/**
	 * @var int
	 *
	 * Maximum file size (in KB) for individual files.
	 */
	public static $image_proxy_maxsize;

	# ######### Directories/Files ##########
	# Note: These directories do not have to be changed unless you move things.
	/**
	 * @var string
	 *
	 * The absolute path to the forum's folder. (not just '.'!)
	 */
	public static $boarddir;
	/**
	 * @var string
	 *
	 * Path to the Sources directory.
	 */
	public static $sourcedir;
	/**
	 * Path to the Packages directory.
	 *
	 * @var string
	 */
	public static $packagesdir;
	/**
	 * @var string
	 *
	 * Path to the tasks directory.
	 */
	public static $tasksdir;

	# ######## Legacy settings #########
	/**
	 * @var string
	 *
	 * Database character set. Should always be utf8.
	 */
	public static $db_character_set;

	# ######## Developer settings #########
	/**
	 * @var bool
	 *
	 * Whether to show debug info.
	 */
	public static $db_show_debug;

	/**
	 * @var string
	 *
	 * Last database error.
	 */
	public static $db_last_error;

	# ######## Custom settings #########
	/**
	 * @var array
	 *
	 * Holds any custom settings found in Settings.php.
	 */
	public static $custom = [];

	# ######## Runtime configuration values #########
	/**
	 * @var array
	 *
	 * Holds settings loaded from the settings table in the database.
	 */
	public static $modSettings = [];

	/**
	 * @var string
	 *
	 * URL of SMF's main index.php. This is set in cleanRequest().
	 */
	public static $scripturl = null;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * A big, fat array to define properties of all the Settings.php variables
	 * and other content like code blocks.
	 *
	 * - String keys are used to identify actual variables.
	 *
	 * - Integer keys are used for content not connected to any particular
	 *   variable, such as code blocks or the license block.
	 *
	 * - The content of the 'text' element is simply printed out, if it is used
	 *   at all. Use it for comments or to insert code blocks, etc.
	 *
	 * - The 'default' element, not surprisingly, gives a default value for
	 *   the variable.
	 *
	 * - The 'type' element defines the expected variable type or types. If
	 *   more than one type is allowed, this should be an array listing them.
	 *   Types should match the possible types returned by gettype().
	 *
	 * - If 'raw_default' is true, the default should be printed directly,
	 *   rather than being handled as a string. Use it if the default contains
	 *   code, e.g. 'dirname(__FILE__)'
	 *
	 * - If 'required' is true and a value for the variable is undefined,
	 *   the update will be aborted. (The only exception is during the SMF
	 *   installation process.)
	 *
	 * - If 'auto_delete' is 1 or true and the variable is empty, the variable
	 *   will be deleted from Settings.php. If 'auto_delete' is 0/false/null,
	 *   the variable will never be deleted. If 'auto_delete' is 2, behaviour
	 *   depends on $rebuild: if $rebuild is true, 'auto_delete' == 2 behaves
	 *   like 'auto_delete' == 1; if $rebuild is false, 'auto_delete' == 2
	 *   behaves like 'auto_delete' == 0.
	 *
	 * - The 'is_password' element indicates that a value is a password. This
	 *   is used primarily to tell SMF how to interpret input when the value
	 *   is being set to a new value.
	 *
	 * - The optional 'search_pattern' element defines a custom regular
	 *   expression to search for the existing entry in the file. This is
	 *   primarily useful for code blocks rather than variables.
	 *
	 * - The optional 'replace_pattern' element defines a custom regular
	 *   expression to decide where the replacement entry should be inserted.
	 *   Note: 'replace_pattern' should be avoided unless ABSOLUTELY necessary.
	 */
	protected static $settings_defs = [
		[
			'text' =>
				"\n" .
				'/**' . "\n" .
				' * The settings file contains all of the basic settings that need to be present when a database/cache is not available.' . "\n" .
				' *' . "\n" .
				' * Simple Machines Forum (SMF)' . "\n" .
				' *' . "\n" .
				' * @package SMF' . "\n" .
				' * @author Simple Machines https://www.simplemachines.org' . "\n" .
				' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors' . "\n" .
				' * @license https://www.simplemachines.org/about/smf/license.php BSD' . "\n" .
				' *' . "\n" .
				' * @version ' . SMF_VERSION . "\n" .
				' */' . "\n" .
				'',
			'search_pattern' => '~/\*\*.*?@package\h+SMF\b.*?\*/\n{0,2}~s',
		],
		'maintenance' => [
			'text' => <<<'END'

				########## Maintenance ##########
				/**
				 * @var int 0, 1, 2
				 *
				 * The maintenance "mode":
				 * 0: Disable maintenance mode. This is the default.
				 * 1: Enable maintenance mode but allow admins to login normally.
				 * 2: Make the forum untouchable. You'll need to make it 0 again manually!
				 */
				END,
			'default' => 0,
			'type' => 'integer',
		],
		'mtitle' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Title for the Maintenance Mode message.
				 */
				END,
			'default' => 'Maintenance Mode',
			'type' => 'string',
		],
		'mmessage' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Description of why the forum is in maintenance mode.
				 */
				END,
			'default' => 'Okay faithful users...we\'re attempting to restore an older backup of the database...news will be posted once we\'re back!',
			'type' => 'string',
		],
		'mbname' => [
			'text' => <<<'END'

				########## Forum Info ##########
				/**
				 * @var string
				 *
				 * The name of your forum.
				 */
				END,
			'default' => 'My Community',
			'type' => 'string',
		],
		'language' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * The default language file set for the forum.
				 */
				END,
			'default' => 'english',
			'type' => 'string',
		],
		'boardurl' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * URL to your forum's folder. (without the trailing /!)
				 */
				END,
			'default' => 'http://127.0.0.1/smf',
			'type' => 'string',
		],
		'webmaster_email' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Email address to send emails from. (like noreply@yourdomain.com.)
				 */
				END,
			'default' => 'noreply@myserver.com',
			'type' => 'string',
		],
		'cookiename' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Name of the cookie to set for authentication.
				 */
				END,
			'default' => 'SMFCookie11',
			'type' => 'string',
		],
		'auth_secret' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Secret key used to create and verify cookies, tokens, etc.
				 * Do not change this unless absolutely necessary, and NEVER share it.
				 *
				 * Note: Changing this will immediately log out all members of your forum
				 * and break the token-based links in all previous email notifications,
				 * among other possible effects.
				 */
				END,
			'default' => null,
			'auto_delete' => 1,
			'type' => 'string',
		],
		'db_type' => [
			'text' => <<<'END'

				########## Database Info ##########
				/**
				 * @var string
				 *
				 * The database type.
				 * Default options: mysql, postgresql
				 */
				END,
			'default' => 'mysql',
			'type' => 'string',
		],
		'db_port' => [
			'text' => <<<'END'
				/**
				 * @var int
				 *
				 * The database port.
				 * 0 to use default port for the database type.
				 */
				END,
			'default' => 0,
			'type' => 'integer',
		],
		'db_server' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * The server to connect to (or a Unix socket)
				 */
				END,
			'default' => 'localhost',
			'required' => true,
			'type' => 'string',
		],
		'db_name' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * The database name.
				 */
				END,
			'default' => 'smf',
			'required' => true,
			'type' => 'string',
		],
		'db_user' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Database username.
				 */
				END,
			'default' => 'root',
			'required' => true,
			'type' => 'string',
		],
		'db_passwd' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Database password.
				 */
				END,
			'default' => '',
			'required' => true,
			'type' => 'string',
			'is_password' => true,
		],
		'ssi_db_user' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Database user for when connecting with SSI.
				 */
				END,
			'default' => '',
			'type' => 'string',
		],
		'ssi_db_passwd' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Database password for when connecting with SSI.
				 */
				END,
			'default' => '',
			'type' => 'string',
			'is_password' => true,
		],
		'db_prefix' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * A prefix to put in front of your table names.
				 * This helps to prevent conflicts.
				 */
				END,
			'default' => 'smf_',
			'required' => true,
			'type' => 'string',
		],
		'db_persist' => [
			'text' => <<<'END'
				/**
				 * @var bool
				 *
				 * Use a persistent database connection.
				 */
				END,
			'default' => false,
			'type' => 'boolean',
		],
		'db_error_send' => [
			'text' => <<<'END'
				/**
				 * @var bool
				 *
				 * Send emails on database connection error.
				 */
				END,
			'default' => false,
			'type' => 'boolean',
		],
		'db_mb4' => [
			'text' => <<<'END'
				/**
				 * @var null|bool
				 *
				 * Override the default behavior of the database layer for mb4 handling.
				 * null keep the default behavior untouched.
				 */
				END,
			'default' => null,
			'type' => ['NULL', 'boolean'],
		],
		'cache_accelerator' => [
			'text' => <<<'END'

				########## Cache Info ##########
				/**
				 * @var string
				 *
				 * Select a cache system. You should leave this up to the cache area of the
				 * admin panel for proper detection of the available options.
				 */
				END,
			'default' => '',
			'type' => 'string',
		],
		'cache_enable' => [
			'text' => <<<'END'
				/**
				 * @var int
				 *
				 * The level at which you would like to cache.
				 * Between 0 (off) through 3 (cache a lot).
				 */
				END,
			'default' => 0,
			'type' => 'integer',
		],
		'cache_memcached' => [
			'text' => <<<'END'
				/**
				 * @var array
				 *
				 * This is only used for memcache / memcached.
				 * Should be a string of 'server:port,server:port'
				 */
				END,
			'default' => '',
			'type' => 'string',
		],
		'cachedir' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Path to the cache directory for the file-based cache system.
				 */
				END,
			'default' => 'dirname(__FILE__) . \'/cache\'',
			'raw_default' => true,
			'type' => 'string',
		],
		'cachedir_sqlite' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * This is only used for the SQLite3 cache system.
				 * Path to the directory where the SQLite3 database file will be saved.
				 */
				END,
			'default' => '',
			'auto_delete' => 2,
			'type' => 'string',
		],
		'image_proxy_enabled' => [
			'text' => <<<'END'

				########## Image Proxy ##########
				/**
				 * @var bool
				 *
				 * Whether the proxy is enabled or not.
				 */
				END,
			'default' => true,
			'type' => 'boolean',
		],
		'image_proxy_secret' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Secret key to be used by the proxy.
				 */
				END,
			'default' => 'smfisawesome',
			'type' => 'string',
		],
		'image_proxy_maxsize' => [
			'text' => <<<'END'
				/**
				 * @var int
				 *
				 * Maximum file size (in KB) for individual files.
				 */
				END,
			'default' => 5192,
			'type' => 'integer',
		],
		'boarddir' => [
			'text' => <<<'END'

				########## Directories/Files ##########
				# Note: These directories do not have to be changed unless you move things.
				/**
				 * @var string
				 *
				 * The absolute path to the forum's folder. (not just '.'!)
				 */
				END,
			'default' => 'dirname(__FILE__)',
			'raw_default' => true,
			'type' => 'string',
		],
		'sourcedir' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Path to the Sources directory.
				 */
				END,
			'default' => 'dirname(__FILE__) . \'/Sources\'',
			'raw_default' => true,
			'type' => 'string',
		],
		'packagesdir' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Path to the Packages directory.
				 */
				END,
			'default' => 'dirname(__FILE__) . \'/Packages\'',
			'raw_default' => true,
			'type' => 'string',
		],
		'tasksdir' => [
			'text' => <<<'END'
				/**
				 * @var string
				 *
				 * Path to the tasks directory.
				 */
				END,
			'default' => '$sourcedir . \'/Tasks\'',
			'raw_default' => true,
			'type' => 'string',
		],
		[
			'text' => <<<'END'

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
				END,
			'search_pattern' => '~\n?(#[^\n]+)?(?:\n\h*if\s*\((?:\!file_exists\(\$(?' . '>boarddir|sourcedir|tasksdir|packagesdir|cachedir)\)|\!is_dir\(realpath\(\$(?' . '>boarddir|sourcedir|tasksdir|packagesdir|cachedir)\)\))[^;]+\n\h*\$(?' . '>boarddir|sourcedir|tasksdir|packagesdir|cachedir)[^\n]+;)+~sm',
		],
		'db_character_set' => [
			'text' => <<<'END'

				######### Legacy Settings #########
				/**
				 * @var string
				 *
				 * Database character set. Should always be utf8.
				 */
				END,
			'default' => 'utf8',
			'type' => 'string',
		],
		'db_show_debug' => [
			'text' => <<<'END'

				######### Developer Settings #########
				/**
				 * @var bool
				 *
				 * Whether to show debug info.
				 */
				END,
			'default' => false,
			'auto_delete' => 2,
			'type' => 'boolean',
		],
		[
			'text' => <<<'END'

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
				END,
			// Designed to match both 2.0 and 2.1 versions of this code.
			'search_pattern' => '~\n?#+ Error.Catching #+\n[^\n]*?settings\.\n(?:\$db_last_error = \d{1,11};|if \(file_exists.*?\$db_last_error = 0;(?' . '>\s*}))(?=\n|\?' . '>|$)~s',
		],
		// Temporary variable used during the upgrade process.
		'upgradeData' => [
			'default' => '',
			'auto_delete' => 1,
			'type' => 'string',
		],
		// This should be removed if found.
		'db_last_error' => [
			'default' => 0,
			'auto_delete' => 1,
			'type' => 'integer',
		],
	];

	/**
	 * @var string
	 *
	 * Authentication secret.
	 * This is protected in order to force access via Config::getAuthSecret()
	 */
	protected static $auth_secret;

	/**
	 * @var string
	 *
	 * Path to a temporary directory.
	 */
	protected static $temp_dir;

	/**
	 * @var string
	 *
	 * Tracks whether static variables and functions have been exported to
	 * global namespace.
	 */
	protected static bool $exported = false;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads properties directly from Settings.php.
	 */
	public static function load(): void
	{
		// Load Settings.php.
		if (!in_array(SMF_SETTINGS_FILE, get_included_files())) {
			require SMF_SETTINGS_FILE;
		}
		// If it has already been included, make sure to avoid possible problems
		// with already defined constants, etc.
		else {
			extract((array) self::getCurrentSettings(filemtime(SMF_SETTINGS_FILE), SMF_SETTINGS_FILE));
		}

		// Set this class's properties according to the values in Settings.php.
		self::set(get_defined_vars());
	}

	/**
	 * Sets the properties of this class to the specified values.
	 *
	 * @param array $settings The settings values to use.
	 */
	public static function set(array $settings): void
	{
		foreach ($settings as $var => $val) {
			if (property_exists(__CLASS__, $var)) {
				self::${$var} = $val;
			} else {
				self::$custom[$var] = $val;
			}
		}

		// Anything missing?
		$class_vars = get_class_vars(__CLASS__);

		foreach ($class_vars['settings_defs'] as $var => $def) {
			if (is_string($var) && property_exists(__CLASS__, $var) && !isset(self::${$var})) {
				if (!empty($def['raw_default'])) {
					$default = strtr($def['default'], [
						'__FILE__' => var_export(SMF_SETTINGS_FILE, true),
						'__DIR__' => var_export(dirname(SMF_SETTINGS_FILE), true),
					]);

					self::${$var} = eval($default);
				} else {
					self::${$var} = $def['default'];
				}

				// For convenience in the backward compatibility section below.
				$settings[$var] = self::${$var};
			}
		}

		// Ensure there are no trailing slashes in these settings.
		foreach (['boardurl', 'boarddir', 'sourcedir', 'packagesdir', 'tasksdir', 'cachedir'] as $var) {
			self::${$var} = rtrim(self::${$var}, '\\/');
		}

		// Make sure the paths are correct... at least try to fix them.
		// @todo Remove similar path correction code from Settings.php.
		if (empty(self::$boarddir) || !is_dir(realpath(self::$boarddir))) {
			self::$boarddir = !empty($_SERVER['SCRIPT_FILENAME']) ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : dirname(__DIR__);
		}

		if ((empty(self::$sourcedir) || !is_dir(realpath(self::$sourcedir))) && is_dir(self::$boarddir . '/Sources')) {
			self::$sourcedir = self::$boarddir . '/Sources';
		}

		if ((empty(self::$tasksdir) || !is_dir(realpath(self::$tasksdir))) && is_dir(self::$sourcedir . '/Tasks')) {
			self::$tasksdir = self::$sourcedir . '/Tasks';
		}

		if ((empty(self::$packagesdir) || !is_dir(realpath(self::$packagesdir))) && is_dir(self::$boarddir . '/Packages')) {
			self::$packagesdir = self::$boarddir . '/Packages';
		}

		// Make absolutely sure the cache directory is defined and writable.
		if (empty(self::$cachedir) || !is_dir(self::$cachedir) || !is_writable(self::$cachedir)) {
			if (is_dir(self::$boarddir . '/cache') && is_writable(self::$boarddir . '/cache')) {
				self::$cachedir = self::$boarddir . '/cache';
			} else {
				self::$cachedir = self::getTempDir() . '/smf_cache_' . md5(self::$boarddir);
				@mkdir(self::$cachedir, 0750);
			}
		}

		// Makes it easier to refer to things this way.
		self::$scripturl = self::$boardurl . '/index.php';

		// For backward compatibility, make settings available as global variables.
		// Must do this manually because SMF\BackwardCompatibility is not loaded yet.
		if (self::$backward_compatibility && !self::$exported) {
			foreach ($settings as $var => $val) {
				if (property_exists(__CLASS__, $var)) {
					$GLOBALS[$var] = &self::${$var};
				} else {
					$GLOBALS[$var] = &self::$custom[$var];
				}
			}

			$GLOBALS['modSettings'] = &self::$modSettings;
			$GLOBALS['scripturl'] = &self::$scripturl;

			eval('function reloadSettings() { return ' . __CLASS__ . '::reloadModSettings(); }');
			eval('function updateSettings(...$args) { return ' . __CLASS__ . '::updateModSettings(...$args); }');
			eval('function get_auth_secret() { return ' . __CLASS__ . '::getAuthSecret(); }');
			eval('function get_settings_defs() { return ' . __CLASS__ . '::getSettingsDefs(); }');
			eval('function updateSettingsFile(...$args) { return ' . __CLASS__ . '::updateSettingsFile(...$args); }');
			eval('function safe_file_write(...$args) { return ' . __CLASS__ . '::safeFileWrite(...$args); }');
			eval('function smf_var_export(...$args) { return ' . __CLASS__ . '::varExport(...$args); }');
			eval('function updateDbLastError(...$args) { return ' . __CLASS__ . '::updateDbLastError(...$args); }');
			eval('function sm_temp_dir() { return ' . __CLASS__ . '::getTempDir(); }');
			eval('function setMemoryLimit(...$args) { return ' . __CLASS__ . '::setMemoryLimit(...$args); }');
			eval('function memoryReturnBytes(...$args) { return ' . __CLASS__ . '::memoryReturnBytes(...$args); }');
			eval('function smf_seed_generator() { return ' . __CLASS__ . '::generateSeed(); }');
			eval('function check_cron() {return ' . __CLASS__ . '::checkCron(); }');

			self::$exported = true;
		}
	}

	/**
	 * Load the Config::$modSettings array.
	 */
	public static function reloadModSettings(): void
	{
		// We need some caching support, maybe.
		Cache\CacheApi::load();

		// Try to load it from the cache first; it'll never get cached if the setting is off.
		if ((self::$modSettings = Cache\CacheApi::get('modSettings', 90)) == null) {
			self::$modSettings = [];

			$request = Db\DatabaseApi::$db->query(
				'',
				'SELECT variable, value
				FROM {db_prefix}settings',
				[
				],
			);

			if (!$request) {
				ErrorHandler::displayDbError();
			}

			foreach (Db\DatabaseApi::$db->fetch_all($request) as $row) {
				self::$modSettings[$row['variable']] = $row['value'];
			}
			Db\DatabaseApi::$db->free_result($request);

			// Do a few things to protect against missing settings or settings with invalid values...
			if (empty(self::$modSettings['defaultMaxTopics']) || self::$modSettings['defaultMaxTopics'] <= 0 || self::$modSettings['defaultMaxTopics'] > 999) {
				self::$modSettings['defaultMaxTopics'] = 20;
			}

			if (empty(self::$modSettings['defaultMaxMessages']) || self::$modSettings['defaultMaxMessages'] <= 0 || self::$modSettings['defaultMaxMessages'] > 999) {
				self::$modSettings['defaultMaxMessages'] = 15;
			}

			if (empty(self::$modSettings['defaultMaxMembers']) || self::$modSettings['defaultMaxMembers'] <= 0 || self::$modSettings['defaultMaxMembers'] > 999) {
				self::$modSettings['defaultMaxMembers'] = 30;
			}

			if (empty(self::$modSettings['defaultMaxListItems']) || self::$modSettings['defaultMaxListItems'] <= 0 || self::$modSettings['defaultMaxListItems'] > 999) {
				self::$modSettings['defaultMaxListItems'] = 15;
			}

			if (!is_array(self::$modSettings['attachmentUploadDir'])) {
				$attachmentUploadDir = Utils::jsonDecode(self::$modSettings['attachmentUploadDir'], true, false);

				self::$modSettings['attachmentUploadDir'] = !empty($attachmentUploadDir) ? $attachmentUploadDir : self::$modSettings['attachmentUploadDir'];
			}

			if (!empty(Cache\CacheApi::$enable)) {
				Cache\CacheApi::put('modSettings', self::$modSettings, 90);
			}
		}

		// Going anything further when the files don't match the database can make nasty messes (unless we're actively installing or upgrading)
		if (
			!defined('SMF_INSTALLING')
			&& (
				!isset($_REQUEST['action'])
				|| $_REQUEST['action'] !== 'admin'
				|| !isset($_REQUEST['area'])
				|| $_REQUEST['area'] !== 'packages'
			)
			&& !empty(self::$modSettings['smfVersion'])
			&& version_compare(
				strtolower(strtr(self::$modSettings['smfVersion'], [' ' => '.'])),
				strtolower(strtr(SMF_VERSION, [' ' => '.'])),
				'!=',
			)
		) {
			// Wipe the cached self::$modSettings values so they don't interfere with anything later.
			Cache\CacheApi::put('modSettings', null);

			// Redirect to the upgrader if we can.
			if (file_exists(self::$boarddir . '/upgrade.php')) {
				header('location: ' . self::$boardurl . '/upgrade.php');
			}

			die('SMF file version (' . SMF_VERSION . ') does not match SMF database version (' . self::$modSettings['smfVersion'] . ').<br>Run the SMF upgrader to fix this.<br><a href="https://wiki.simplemachines.org/smf/Upgrading">More information</a>.');
		}

		self::$modSettings['cache_enable'] = Cache\CacheApi::$enable;

		// Used to force browsers to download fresh CSS and JavaScript when necessary
		self::$modSettings['browser_cache'] = !empty(self::$modSettings['browser_cache']) ? (int) self::$modSettings['browser_cache'] : 0;

		// Disable image proxy if we don't have SSL enabled
		if (empty(self::$modSettings['force_ssl'])) {
			self::$image_proxy_enabled = false;
		}

		// Setting the timezone is a requirement for some functions.
		if (isset(self::$modSettings['default_timezone']) && in_array(self::$modSettings['default_timezone'], timezone_identifiers_list())) {
			date_default_timezone_set(self::$modSettings['default_timezone']);
		} else {
			// Get PHP's default timezone, if set
			$ini_tz = ini_get('date.timezone');

			self::$modSettings['default_timezone'] = !empty($ini_tz) ? $ini_tz : '';

			// If date.timezone is unset, invalid, or just plain weird, make a best guess
			if (!in_array(self::$modSettings['default_timezone'], timezone_identifiers_list())) {
				$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
				self::$modSettings['default_timezone'] = timezone_name_from_abbr('', $server_offset, 0);

				if (empty(self::$modSettings['default_timezone'])) {
					self::$modSettings['default_timezone'] = 'UTC';
				}
			}

			date_default_timezone_set(self::$modSettings['default_timezone']);
		}

		// Check the load averages?
		if (!empty(self::$modSettings['loadavg_enable'])) {
			if ((self::$modSettings['load_average'] = Cache\CacheApi::get('loadavg', 90)) == null) {
				self::$modSettings['load_average'] = @file_get_contents('/proc/loadavg');

				if (!empty(self::$modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', self::$modSettings['load_average'], $matches) != 0) {
					self::$modSettings['load_average'] = (float) $matches[1];
				} elseif ((self::$modSettings['load_average'] = @shell_exec('uptime')) != null && preg_match('~load averages?: (\d+\.\d+)~i', self::$modSettings['load_average'], $matches) != 0) {
					self::$modSettings['load_average'] = (float) $matches[1];
				} else {
					unset(self::$modSettings['load_average']);
				}

				if (!empty(self::$modSettings['load_average']) || self::$modSettings['load_average'] === 0.0) {
					Cache\CacheApi::put('loadavg', self::$modSettings['load_average'], 90);
				}
			}

			if (!empty(self::$modSettings['load_average']) || self::$modSettings['load_average'] === 0.0) {
				IntegrationHook::call('integrate_load_average', [self::$modSettings['load_average']]);
			}

			if (!empty(self::$modSettings['loadavg_forum']) && !empty(self::$modSettings['load_average']) && self::$modSettings['load_average'] >= self::$modSettings['loadavg_forum']) {
				ErrorHandler::displayLoadAvgError();
			}
		}

		// Ensure we know who can manage boards.
		if (!isset(self::$modSettings['board_manager_groups'])) {
			$board_managers = User::groupsAllowedTo('manage_boards', null);
			$board_managers = implode(',', $board_managers['allowed']);
			Config::updateModSettings(['board_manager_groups' => $board_managers]);
		}

		// Is post moderation alive and well? Everywhere else assumes this has been defined, so let's make sure it is.
		self::$modSettings['postmod_active'] = !empty(self::$modSettings['postmod_active']);

		// Ensure the UUID for this forum has been set.
		if (!isset(self::$modSettings['forum_uuid'])) {
			Config::updateModSettings(['forum_uuid' => Uuid::getNamespace()]);
		}

		// Here to justify the name of this function. :P
		// It should be added to the install and upgrade scripts.
		// But since the converters need to be updated also. This is easier.
		if (empty(self::$modSettings['currentAttachmentUploadDir'])) {
			Config::updateModSettings([
				'attachmentUploadDir' => Utils::jsonEncode([1 => self::$modSettings['attachmentUploadDir']]),
				'currentAttachmentUploadDir' => 1,
			]);
		}

		// Respect PHP's limits.
		$post_max_kb = floor(self::memoryReturnBytes(ini_get('post_max_size')) / 1024);
		$file_max_kb = floor(self::memoryReturnBytes(ini_get('upload_max_filesize')) / 1024);
		self::$modSettings['attachmentPostLimit'] = empty(self::$modSettings['attachmentPostLimit']) ? $post_max_kb : min(self::$modSettings['attachmentPostLimit'], $post_max_kb);
		self::$modSettings['attachmentSizeLimit'] = empty(self::$modSettings['attachmentSizeLimit']) ? $file_max_kb : min(self::$modSettings['attachmentSizeLimit'], $file_max_kb);
		self::$modSettings['attachmentNumPerPostLimit'] = !isset(self::$modSettings['attachmentNumPerPostLimit']) ? 4 : self::$modSettings['attachmentNumPerPostLimit'];

		// Integration is cool.
		if (defined('SMF_INTEGRATION_SETTINGS')) {
			$integration_settings = Utils::jsonDecode(SMF_INTEGRATION_SETTINGS, true);

			foreach ($integration_settings as $hook => $function) {
				IntegrationHook::add($hook, $function, false);
			}
		}

		// Any files to pre include?
		if (!empty(self::$modSettings['integrate_pre_include'])) {
			$pre_includes = explode(',', self::$modSettings['integrate_pre_include']);

			foreach ($pre_includes as $include) {
				$include = strtr(trim($include), ['$boarddir' => self::$boarddir, '$sourcedir' => self::$sourcedir]);

				if (file_exists($include)) {
					require_once $include;
				}
			}
		}

		Utils::load();

		// Call pre load integration functions.
		IntegrationHook::call('integrate_pre_load');
	}

	/**
	 * Updates the settings table as well as Config::$modSettings...
	 *
	 * - Updates both the settings table and Config::$modSettings array.
	 * - All of change_array's indexes and values are assumed to have escaped
	 *   apostrophes.
	 * - If a variable is already set to what you want to change it to, that
	 *   variable will be skipped over; it would be unnecessary to reset.
	 * - When $update is true, UPDATEs will be used instead of REPLACE.
	 * - When $update is true, the value can be true or false to increment
	 *   or decrement it, respectively.
	 * - Only does one at a time if $update is true.
	 *
	 * @param array $change_array An array of info about what we're changing
	 *    in 'setting' => 'value' format.
	 * @param bool $update Whether to use an UPDATE query instead of a REPLACE
	 *    query.
	 */
	public static function updateModSettings(array $change_array, bool $update = false): void
	{
		if (empty($change_array) || !is_array($change_array)) {
			return;
		}

		$to_remove = [];

		// Go check if there is any setting to be removed.
		foreach ($change_array as $k => $v) {
			if ($v === null) {
				// Found some, remove them from the original array and add them to ours.
				unset($change_array[$k]);
				$to_remove[] = $k;
			}
		}

		// Proceed with the deletion.
		if (!empty($to_remove)) {
			Db\DatabaseApi::$db->query(
				'',
				'DELETE FROM {db_prefix}settings
				WHERE variable IN ({array_string:remove})',
				[
					'remove' => $to_remove,
				],
			);
		}

		// In some cases, this may be better and faster, but for large sets we don't want so many UPDATEs.
		if ($update) {
			foreach ($change_array as $variable => $value) {
				Db\DatabaseApi::$db->query(
					'',
					'UPDATE {db_prefix}settings
					SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
					WHERE variable = {string:variable}',
					[
						'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
						'variable' => $variable,
					],
				);

				self::$modSettings[$variable] = $value === true ? self::$modSettings[$variable] + 1 : ($value === false ? self::$modSettings[$variable] - 1 : $value);
			}

			// Clean out the cache and make sure the cobwebs are gone too.
			Cache\CacheApi::put('modSettings', null, 90);

			return;
		}

		$replace_array = [];

		foreach ($change_array as $variable => $value) {
			// Don't bother if it's already like that ;).
			if ((self::$modSettings[$variable] ?? null) == $value) {
				continue;
			}

			// If the variable isn't set, but would only be set to nothingness, then don't bother setting it.
			if (!isset(self::$modSettings[$variable]) && empty($value)) {
				continue;
			}

			$replace_array[] = [$variable, $value];

			self::$modSettings[$variable] = $value;
		}

		if (empty($replace_array)) {
			return;
		}

		Db\DatabaseApi::$db->insert(
			'replace',
			'{db_prefix}settings',
			['variable' => 'string-255', 'value' => 'string-65534'],
			$replace_array,
			['variable'],
		);

		// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
		Cache\CacheApi::put('modSettings', null, 90);
	}

	/**
	 * Gets, and if necessary creates, the authentication secret to use for
	 * cookies, tokens, etc.
	 *
	 * @return string The authentication secret.
	 */
	public static function getAuthSecret(): string
	{
		if (empty(self::$auth_secret)) {
			self::$auth_secret = bin2hex(random_bytes(32));

			// It is important to store this in Settings.php, not the database.
			// If saving fails, we should alert, log, and set a static value.
			if (!self::updateSettingsFile(['auth_secret' => self::$auth_secret])) {
				if (class_exists('SMF\\Utils', false)) {
					Utils::$context['auth_secret_missing'] = true;
				}

				self::$auth_secret = hash_file('sha256', SMF_SETTINGS_FILE);

				// Set the last error to now, but only every 15 minutes. Don't need to flood the logs.
				if (empty(self::$db_last_error) || (self::$db_last_error + 60 * 15) <= time()) {
					self::updateDbLastError(time());
					Lang::load('Errors');
					ErrorHandler::log(Lang::$txt['auth_secret_missing'], 'critical');
				}
			}
		}

		return self::$auth_secret;
	}

	/**
	 * Describes properties of all known Settings.php variables and other content.
	 * Helper for updateSettingsFile(); also called by saveSettings().
	 *
	 * @return array Descriptions of all known Settings.php content.
	 */
	public static function getSettingsDefs(): array
	{
		// Start with a clean set every time this method is called.
		$class_vars = get_class_vars(__CLASS__);
		self::$settings_defs = $class_vars['settings_defs'];

		// Allow mods the option to define comments, defaults, etc., for their settings.
		// Check if IntegrationHook exists, in case we are calling from installer or upgrader.
		if (class_exists('SMF\\IntegrationHook', false)) {
			IntegrationHook::call('integrate_update_settings_file', [&self::$settings_defs]);
		}

		// Return the setting definitions, including any added by mods.
		return self::$settings_defs;
	}

	/**
	 * Update the Settings.php file.
	 *
	 * MOD AUTHORS: If you add a setting to Settings.php, you should use the
	 * integrate_update_settings_file hook to define it in getSettingsDefs().
	 *
	 * - Updates the Settings.php file with the changes supplied in config_vars.
	 *
	 * - Expects config_vars to be an associative array, with the keys as the
	 *   variable names in Settings.php, and the values the variable values.
	 *
	 * - Correctly formats the values using self::varExport().
	 *
	 * - Restores standard formatting of the file, if $rebuild is true.
	 *
	 * - Checks for changes to db_last_error and passes those off to a separate
	 *   handler.
	 *
	 * - Creates a backup file and will use it should the writing of the
	 *   new settings file fail.
	 *
	 * - Tries to intelligently trim quotes and remove slashes from string
	 *   values. This is done for backwards compatibility purposes (old versions
	 *   of this function expected strings to have been manually escaped and
	 *   quoted). This behaviour can be controlled by the $keep_quotes param.
	 *
	 * @param array $config_vars An array of one or more variables to update.
	 * @param bool|null $keep_quotes Whether to strip slashes and trim quotes
	 *     from string values. Defaults to auto-detection.
	 * @param bool $rebuild If true, attempts to rebuild with standard format.
	 *     Default false.
	 * @return bool True on success, false on failure.
	 */
	public static function updateSettingsFile(array $config_vars, ?bool $keep_quotes = null, bool $rebuild = false): bool
	{
		static $mtime;

		// Should we try to unescape the strings?
		if (empty($keep_quotes)) {
			foreach ($config_vars as $var => $val) {
				if (is_string($val) && ($keep_quotes === false || strpos($val, '\'') === 0 && strrpos($val, '\'') === strlen($val) - 1)) {
					$config_vars[$var] = trim(stripcslashes($val), '\'');
				}
			}
		}

		// Updating the db_last_error, then don't mess around with Settings.php
		if (isset($config_vars['db_last_error'])) {
			self::updateDbLastError($config_vars['db_last_error']);

			if (count($config_vars) === 1 && empty($rebuild)) {
				return true;
			}

			// Make sure we delete this from Settings.php, if present.
			$config_vars['db_last_error'] = 0;
		}

		// Rebuilding should not be undertaken lightly, so we're picky about the parameter.
		if (!is_bool($rebuild)) {
			$rebuild = false;
		}

		$mtime = isset($mtime) ? (int) $mtime : (defined('TIME_START') ? TIME_START : $_SERVER['REQUEST_TIME']);

		/*****************
		 * PART 1: Setup *
		 *****************/

		// Is Settings.php where we expect it to be, or do we need to find it?
		if (defined('SMF_SETTINGS_FILE') && is_file(SMF_SETTINGS_FILE)) {
			$settingsFile = SMF_SETTINGS_FILE;

			$backupFile = defined('SMF_SETTINGS_BACKUP_FILE') ? SMF_SETTINGS_BACKUP_FILE : dirname(SMF_SETTINGS_FILE) . DIRECTORY_SEPARATOR . pathinfo(SMF_SETTINGS_FILE, PATHINFO_FILENAME) . '_bak.php';
		} else {
			foreach (get_included_files() as $settingsFile) {
				if (basename($settingsFile) === 'Settings.php') {
					break;
				}
			}

			// Fallback in case Settings.php isn't loaded (e.g. while installing)
			if (basename($settingsFile) !== 'Settings.php') {
				$settingsFile = (!empty(self::$boarddir) && @realpath(self::$boarddir) ? self::$boarddir : (!empty($_SERVER['SCRIPT_FILENAME']) ? dirname($_SERVER['SCRIPT_FILENAME']) : dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'Settings.php';
			}

			$backupFile = dirname($settingsFile) . DIRECTORY_SEPARATOR . pathinfo($settingsFile, PATHINFO_FILENAME) . '_bak.php';
		}

		// File not found? Attempt an emergency on-the-fly fix!
		if (!file_exists($settingsFile)) {
			@touch($settingsFile);
		}

		// When was Settings.php last changed?
		$last_settings_change = filemtime($settingsFile);

		// Get the current values of everything in Settings.php.
		$settings_vars = self::getCurrentSettings($mtime, $settingsFile);

		// If Settings.php is empty for some reason, see if we can use the backup.
		if (empty($settings_vars) && file_exists($backupFile)) {
			$settings_vars = self::getCurrentSettings($mtime, $backupFile);
		}

		// False means there was a problem with the file and we can't safely continue.
		if ($settings_vars === false) {
			return false;
		}

		// It works best to set everything afresh.
		$new_settings_vars = array_merge($settings_vars, $config_vars);

		// Are we using UTF-8?
		$utf8 = class_exists('SMF\\Utils', false) && isset(Utils::$context['utf8']) ? Utils::$context['utf8'] : (isset($settings_vars['db_character_set']) ? $settings_vars['db_character_set'] === 'utf8' : (isset(self::$db_character_set) ? self::$db_character_set === 'utf8' : true));

		// Get our definitions for all known Settings.php variables and other content.
		$settings_defs = self::getSettingsDefs();

		// If Settings.php is empty or invalid, try to recover using whatever we have now.
		if ($settings_vars === []) {
			foreach ($settings_defs as $var => $setting_def) {
				if (isset(self::${$var}) || isset(self::$custom[$var])) {
					$settings_vars[$var] = self::${$var} ?? self::$custom[$var];
				}
			}

			$new_settings_vars = array_merge($settings_vars, $config_vars);
		}

		// During install/upgrade, don't set anything until we're ready for it.
		if (defined('SMF_INSTALLING') && empty($rebuild)) {
			foreach ($settings_defs as $var => $setting_def) {
				if (!in_array($var, array_keys($new_settings_vars)) && !is_int($var)) {
					unset($settings_defs[$var]);
				}
			}
		}

		/*******************************
		 * PART 2: Build substitutions *
		 *******************************/

		$type_regex = [
			'string' =>
				'(?:' .
					// match the opening quotation mark...
					'(["\'])' .
					// then any number of other characters or escaped quotation marks...
					'(?:.(?!\\1)|\\\(?=\\1))*.?' .
					// then the closing quotation mark.
					'\\1' .
					// Maybe there's a second string concatenated to this one.
					'(?:\s*\.\s*)*' .
				')+',
			// Some numeric values might have been stored as strings.
			'integer' =>  '["\']?[+-]?\d+["\']?',
			'double' =>  '["\']?[+-]?\d+\.\d+([Ee][+-]\d+)?["\']?',
			// Some boolean values might have been stored as integers.
			'boolean' =>  '(?i:TRUE|FALSE|(["\']?)[01]\b\\1)',
			'NULL' =>  '(?i:NULL)',
			// These use a PCRE subroutine to match nested arrays.
			'array' =>  'array\s*(\((?' . '>[^()]|(?1))*\))',
			'object' =>  '\w+::__set_state\(array\s*(\((?' . '>[^()]|(?1))*\))\)',
		];

		/*
		 * The substitutions take place in one of two ways:
		 *
		 *  1: The search_pattern regex finds a string in Settings.php, which is
		 *     temporarily replaced by a placeholder. Once all the placeholders
		 *     have been inserted, each is replaced by the final replacement
		 *     string that we want to use. This is the standard method.
		 *
		 *  2: The search_pattern regex finds a string in Settings.php, which is
		 *     then deleted by replacing it with an empty placeholder. Then
		 *     after all the real placeholders have been dealt with, the
		 *     replace_pattern regex finds where to insert the final replacement
		 *     string that we want to use. This method is for special cases.
		 */
		$prefix = mt_rand() . '-';
		$neg_index = -1;
		$substitutions = [
			$neg_index-- => [
				'search_pattern' => '~^\s*<\?(php\b)?\n?~',
				'placeholder' => '',
				'replace_pattern' => '~^~',
				'replacement' => '<' . "?php\n",
			],
			$neg_index-- => [
				'search_pattern' => '~\S\K\s*(\?' . '>)?\s*$~',
				'placeholder' => "\n" . md5($prefix . '?' . '>'),
				'replacement' => "\n\n?" . '>',
			],
			// Remove the code that redirects to the installer.
			$neg_index-- => [
				'search_pattern' => '~^if\s*\(file_exists\(dirname\(__FILE__\)\s*\.\s*\'/install\.php\'\)\)\s*(?:({(?' . '>[^{}]|(?1))*})\h*|header(\((?' . '>[^()]|(?2))*\));\n)~m',
				'placeholder' => '',
			],
		];

		if (defined('SMF_INSTALLING')) {
			$substitutions[$neg_index--] = [
				'search_pattern' => '~/\*.*?SMF\s+1\.\d.*?\*/~s',
				'placeholder' => '',
			];
		}

		foreach ($settings_defs as $var => $setting_def) {
			$placeholder = md5($prefix . $var);
			$replacement = '';

			if (!empty($setting_def['text'])) {
				// Special handling for the license block: always at the beginning.
				if (strpos($setting_def['text'], "* @package SMF\n") !== false) {
					$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
					$substitutions[$var]['placeholder'] = '';
					$substitutions[-1]['replacement'] .= $setting_def['text'] . "\n";
				}
				// Special handling for the Error-Catching block: always at the end.
				elseif (strpos($setting_def['text'], 'Error-Catching') !== false) {
					$errcatch_var = $var;
					$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
					$substitutions[$var]['placeholder'] = '';
					$substitutions[-2]['replacement'] = "\n" . $setting_def['text'] . $substitutions[-2]['replacement'];
				}
				// The text is the whole thing (code blocks, etc.)
				elseif (is_int($var)) {
					// Remember the path correcting code for later.
					if (strpos($setting_def['text'], '# Make sure the paths are correct') !== false) {
						$pathcode_var = $var;
					}

					if (!empty($setting_def['search_pattern'])) {
						$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
					} else {
						$substitutions[$var]['search_pattern'] = '~' . preg_quote($setting_def['text'], '~') . '~';
					}

					$substitutions[$var]['placeholder'] = $placeholder;

					$replacement .= $setting_def['text'] . "\n";
				}
				// We only include comments when rebuilding.
				elseif (!empty($rebuild)) {
					$replacement .= $setting_def['text'] . "\n";
				}
			}

			if (is_string($var)) {
				// Ensure the value is good.
				if (in_array($var, array_keys($new_settings_vars))) {
					// Objects without a __set_state method need a fallback.
					if (is_object($new_settings_vars[$var]) && !method_exists($new_settings_vars[$var], '__set_state')) {
						if (method_exists($new_settings_vars[$var], '__toString')) {
							$new_settings_vars[$var] = (string) $new_settings_vars[$var];
						} else {
							$new_settings_vars[$var] = (array) $new_settings_vars[$var];
						}
					}

					// Normalize the type if necessary.
					if (isset($setting_def['type'])) {
						$expected_types = (array) $setting_def['type'];
						$var_type = gettype($new_settings_vars[$var]);

						// Variable is not of an expected type.
						if (!in_array($var_type, $expected_types)) {
							// Passed in an unexpected array.
							if ($var_type == 'array') {
								$temp = reset($new_settings_vars[$var]);

								// Use the first element if there's only one and it is a scalar.
								if (count($new_settings_vars[$var]) === 1 && is_scalar($temp)) {
									$new_settings_vars[$var] = $temp;
								}
								// Or keep the old value, if that is good.
								elseif (isset($settings_vars[$var]) && in_array(gettype($settings_vars[$var]), $expected_types)) {
									$new_settings_vars[$var] = $settings_vars[$var];
								}
								// Fall back to the default
								else {
									$new_settings_vars[$var] = $setting_def['default'];
								}
							}

							// Cast it to whatever type was expected.
							// Note: the order of the types in this loop matters.
							foreach (['boolean', 'integer', 'double', 'string', 'array'] as $to_type) {
								if (in_array($to_type, $expected_types)) {
									settype($new_settings_vars[$var], $to_type);
									break;
								}
							}
						}
					}
				}
				// Abort if a required one is undefined (unless we're installing).
				elseif (!empty($setting_def['required']) && !defined('SMF_INSTALLING')) {
					return false;
				}

				// Create the search pattern.
				if (!empty($setting_def['search_pattern'])) {
					$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
				} else {
					$var_pattern = [];

					if (isset($setting_def['type'])) {
						foreach ((array) $setting_def['type'] as $type) {
							$var_pattern[] = $type_regex[$type];
						}
					}

					if (in_array($var, array_keys($config_vars))) {
						$var_pattern[] = @$type_regex[gettype($config_vars[$var])];

						if (is_string($config_vars[$var]) && strpos($config_vars[$var], dirname($settingsFile)) === 0) {
							$var_pattern[] = '(?:__DIR__|dirname\(__FILE__\)) . \'' . (preg_quote(str_replace(dirname($settingsFile), '', $config_vars[$var]), '~')) . '\'';
						}
					}

					if (in_array($var, array_keys($settings_vars))) {
						$var_pattern[] = @$type_regex[gettype($settings_vars[$var])];

						if (is_string($settings_vars[$var]) && strpos($settings_vars[$var], dirname($settingsFile)) === 0) {
							$var_pattern[] = '(?:__DIR__|dirname\(__FILE__\)) . \'' . (preg_quote(str_replace(dirname($settingsFile), '', $settings_vars[$var]), '~')) . '\'';
						}
					}

					if (!empty($setting_def['raw_default']) && $setting_def['default'] !== '') {
						$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote($setting_def['default'], '~'));

						if (strpos($setting_def['default'], 'dirname(__FILE__)') !== false) {
							$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote(str_replace('dirname(__FILE__)', '__DIR__', $setting_def['default']), '~'));
						}

						if (strpos($setting_def['default'], '__DIR__') !== false) {
							$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote(str_replace('__DIR__', 'dirname(__FILE__)', $setting_def['default']), '~'));
						}
					}

					$var_pattern = array_unique($var_pattern);

					$var_pattern = count($var_pattern) > 1 ? '(?:' . (implode('|', $var_pattern)) . ')' : $var_pattern[0];

					$substitutions[$var]['search_pattern'] = '~(?<=^|\s)\h*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';~' . (!empty($utf8) ? 'u' : '');
				}

				// Next create the placeholder or replace_pattern.
				if (!empty($setting_def['replace_pattern'])) {
					$substitutions[$var]['replace_pattern'] = $setting_def['replace_pattern'];
				} else {
					$substitutions[$var]['placeholder'] = $placeholder;
				}

				// Now create the replacement.
				// A setting to delete.
				if (!empty($setting_def['auto_delete']) && empty($new_settings_vars[$var])) {
					if ($setting_def['auto_delete'] === 2 && empty($rebuild) && in_array($var, array_keys($new_settings_vars))) {
						$replacement .= '$' . $var . ' = ' . ($new_settings_vars[$var] === $setting_def['default'] && !empty($setting_def['raw_default']) ? sprintf($new_settings_vars[$var]) : self::varExport($new_settings_vars[$var], true)) . ';';
					} else {
						$replacement = '';
						$substitutions[$var]['placeholder'] = '';

						// This is just for cosmetic purposes. Removes the blank line.
						$substitutions[$var]['search_pattern'] = str_replace('(?<=^|\s)', '\n?', $substitutions[$var]['search_pattern']);
					}
				}
				// Add this setting's value.
				elseif (in_array($var, array_keys($new_settings_vars))) {
					$replacement .= '$' . $var . ' = ' . ($new_settings_vars[$var] === $setting_def['default'] && !empty($setting_def['raw_default']) ? sprintf($new_settings_vars[$var]) : self::varExport($new_settings_vars[$var], true)) . ';';
				}
				// Fall back to the default value.
				elseif (isset($setting_def['default'])) {
					$replacement .= '$' . $var . ' = ' . (!empty($setting_def['raw_default']) ? sprintf($setting_def['default']) : self::varExport($setting_def['default'], true)) . ';';
				}
				// This shouldn't happen, but we've got nothing.
				else {
					$replacement .= '$' . $var . ' = null;';
				}
			}

			$substitutions[$var]['replacement'] = $replacement;

			// We're done with this one.
			unset($new_settings_vars[$var]);
		}

		// Any leftovers to deal with?
		foreach ($new_settings_vars as $var => $val) {
			$var_pattern = [];

			if (in_array($var, array_keys($config_vars))) {
				$var_pattern[] = $type_regex[gettype($config_vars[$var])];
			}

			if (in_array($var, array_keys($settings_vars))) {
				$var_pattern[] = $type_regex[gettype($settings_vars[$var])];
			}

			$var_pattern = array_unique($var_pattern);

			$var_pattern = count($var_pattern) > 1 ? '(?:' . (implode('|', $var_pattern)) . ')' : $var_pattern[0];

			$placeholder = md5($prefix . $var);

			$substitutions[$var]['search_pattern'] = '~(?<=^|\s)\h*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';~' . (!empty($utf8) ? 'u' : '');
			$substitutions[$var]['placeholder'] = $placeholder;
			$substitutions[$var]['replacement'] = '$' . $var . ' = ' . self::varExport($val, true) . ';';
		}

		// During an upgrade, some of the path variables may not have been declared yet.
		if (defined('SMF_INSTALLING') && empty($rebuild)) {
			preg_match_all('~^\h*\$(\w+)\s*=\s*~m', $substitutions[$pathcode_var]['replacement'], $matches);

			$missing_pathvars = array_diff($matches[1], array_keys($substitutions));

			if (!empty($missing_pathvars)) {
				foreach ($missing_pathvars as $var) {
					$substitutions[$pathcode_var]['replacement'] = preg_replace('~\nif[^\n]+\$' . $var . '[^\n]+\n\h*\$' . $var . ' = [^\n]+~', '', $substitutions[$pathcode_var]['replacement']);
				}
			}
		}

		// It's important to do the numbered ones before the named ones, or messes happen.
		uksort(
			$substitutions,
			function ($a, $b) {
				if (is_int($a) && is_int($b)) {
					return $a > $b ? 1 : ($a < $b ? -1 : 0);
				}

				if (is_int($a)) {
					return -1;
				}

				if (is_int($b)) {
					return 1;
				}

				return strcasecmp($b, $a);
			},
		);

		/******************************
		 * PART 3: Content processing *
		 ******************************/

		/* 3.a: Get the content of Settings.php and make sure it is good. */

		// Retrieve the contents of Settings.php and normalize the line endings.
		$settingsText = trim(strtr(file_get_contents($settingsFile), ["\r\n" => "\n", "\r" => "\n"]));

		// If Settings.php is empty or corrupt for some reason, see if we can recover.
		if ($settingsText == '' || substr($settingsText, 0, 5) !== '<' . '?php') {
			// Try restoring from the backup.
			if (file_exists($backupFile)) {
				$settingsText = strtr(file_get_contents($backupFile), ["\r\n" => "\n", "\r" => "\n"]);
			}

			// Backup is bad too? Our only option is to create one from scratch.
			if ($settingsText == '' || substr($settingsText, 0, 5) !== '<' . '?php' || substr($settingsText, -2) !== '?' . '>') {
				$settingsText = '<' . "?php\n";

				foreach ($settings_defs as $var => $setting_def) {
					if (is_string($var) && !empty($setting_def['text']) && strpos($substitutions[$var]['replacement'], $setting_def['text']) === false) {
						$substitutions[$var]['replacement'] = $setting_def['text'] . "\n" . $substitutions[$var]['replacement'];
					}

					$settingsText .= $substitutions[$var]['replacement'] . "\n";
				}

				$settingsText .= "\n\n?" . '>';
				$rebuild = true;
			}
		}

		// Settings.php is unlikely to contain any heredocs, but just in case...
		if (preg_match_all('/<<<([\'"]?)(\w+)\1\R(.*?)\R\h*\2;$/ms', $settingsText, $matches)) {
			foreach ($matches[0] as $mkey => $heredoc) {
				if (!empty($matches[1][$mkey]) && $matches[1][$mkey] === '\'') {
					$heredoc_replacements[$heredoc] = var_export($matches[3][$mkey], true) . ';';
				} else {
					$heredoc_replacements[$heredoc] = '"' . strtr(substr(var_export($matches[3][$mkey], true), 1, -1), ["\\'" => "'", '"' => '\\"']) . '";';
				}
			}

			$settingsText = strtr($settingsText, $heredoc_replacements);
		}

		/* 3.b: Loop through all our substitutions to insert placeholders, etc. */

		$last_var = null;
		$bare_settingsText = $settingsText;
		$force_before_pathcode = [];

		foreach ($substitutions as $var => $substitution) {
			$placeholders[$var] = $substitution['placeholder'];

			if (!empty($substitution['placeholder'])) {
				$simple_replacements[$substitution['placeholder']] = $substitution['replacement'];
			} elseif (!empty($substitution['replace_pattern'])) {
				$replace_patterns[$var] = $substitution['replace_pattern'];
				$replace_strings[$var] = $substitution['replacement'];
			}

			if (strpos($substitutions[$pathcode_var]['replacement'], '$' . $var . ' = ') !== false) {
				$force_before_pathcode[] = $var;
			}

			// Look before you leap.
			preg_match_all($substitution['search_pattern'], $bare_settingsText, $matches);

			if ((is_string($var) || $var === $pathcode_var) && count($matches[0]) !== 1 && $substitution['replacement'] !== '') {
				// More than one instance of the variable = not good.
				if (count($matches[0]) > 1) {
					if (is_string($var)) {
						// Maybe we can try something more interesting?
						$sp = substr($substitution['search_pattern'], 1);

						if (strpos($sp, '(?<=^|\s)') === 0) {
							$sp = substr($sp, 9);
						}

						if (strpos($sp, '^') === 0 || strpos($sp, '(?<') === 0) {
							return false;
						}

						// See if we can exclude `if` blocks, etc., to narrow down the matches.
						// @todo Multiple layers of nested brackets might confuse this.
						$sp = '~(?:^|//[^\n]+c\n|\*/|[;}]|' . implode('|', array_filter($placeholders)) . ')\s*' . (strpos($sp, '\K') === false ? '\K' : '') . $sp;

						preg_match_all($sp, $settingsText, $matches);
					} else {
						$sp = $substitution['search_pattern'];
					}

					// Found at least some that are simple assignment statements.
					if (count($matches[0]) > 0) {
						// Remove any duplicates.
						if (count($matches[0]) > 1) {
							$settingsText = preg_replace($sp, '', $settingsText, count($matches[0]) - 1);
						}

						// Insert placeholder for the last one.
						$settingsText = preg_replace($sp, $substitution['placeholder'], $settingsText, 1);
					}

					// All instances are inside more complex code structures.
					else {
						// Only safe option at this point is to skip it.
						unset($substitutions[$var], $new_settings_vars[$var], $settings_defs[$var], $simple_replacements[$substitution['placeholder']], $replace_patterns[$var], $replace_strings[$var]);

						continue;
					}
				}
				// No matches found.
				elseif (count($matches[0]) === 0) {
					$found = false;
					$in_c = in_array($var, array_keys($config_vars));
					$in_s = in_array($var, array_keys($settings_vars));

					// Is it in there at all?
					if (!preg_match('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*~', $bare_settingsText)) {
						// It's defined by Settings.php, but not by code in the file.
						// Probably done via an include or something. Skip it.
						if ($in_s) {
							unset($substitutions[$var], $settings_defs[$var]);
						}
						// Admin is explicitly trying to set this one, so we'll handle
						// it as if it were a new custom setting being added.
						elseif ($in_c) {
							$new_settings_vars[$var] = $config_vars[$var];
						}

						continue;
					}

					// It's in there somewhere, so check if the value changed type.
					foreach (['scalar', 'object', 'array'] as $type) {
						// Try all the other scalar types first.
						if ($type == 'scalar') {
							$sp = '(?:' . (implode('|', array_diff_key($type_regex, [$in_c ? gettype($config_vars[$var]) : ($in_s ? gettype($settings_vars[$var]) : PHP_INT_MAX) => '', 'array' => '', 'object' => '']))) . ')';
						}
						// Maybe it's an object? (Probably not, but we should check.)
						elseif ($type == 'object') {
							if (strpos($settingsText, '__set_state') === false) {
								continue;
							}

							$sp = $type_regex['object'];
						}
						// Maybe it's an array?
						else {
							$sp = $type_regex['array'];
						}

						if (preg_match('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*' . $sp . '~', $bare_settingsText, $derp)) {
							$settingsText = preg_replace('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*' . $sp . '~', $substitution['placeholder'], $settingsText);

							$found = true;

							break;
						}
					}

					// Something weird is going on. Better just leave it alone.
					if (!$found) {
						// $var? What $var? Never heard of it.
						unset($substitutions[$var], $new_settings_vars[$var], $settings_defs[$var], $simple_replacements[$substitution['placeholder']], $replace_patterns[$var], $replace_strings[$var]);

						continue;
					}
				}
			}
			// Good to go, so insert our placeholder.
			else {
				$settingsText = preg_replace($substitution['search_pattern'], $substitution['placeholder'], $settingsText);
			}

			// Once the code blocks are done, we want to compare to a version without comments.
			if (is_int($last_var) && is_string($var)) {
				$bare_settingsText = self::stripPhpComments($settingsText);
			}

			$last_var = $var;
		}

		// Rebuilding requires more work.
		if (!empty($rebuild)) {
			// Strip out the leading and trailing placeholders to prevent duplication.
			$settingsText = str_replace([$substitutions[-1]['placeholder'], $substitutions[-2]['placeholder']], '', $settingsText);

			// Strip out all our standard comments.
			foreach ($settings_defs as $var => $setting_def) {
				if (isset($setting_def['text'])) {
					$settingsText = strtr($settingsText, [$setting_def['text'] . "\n" => '', $setting_def['text'] => '']);
				}
			}

			// We need to refresh $bare_settingsText at this point.
			$bare_settingsText = self::stripPhpComments($settingsText);

			// Fix up whitespace to make comparison easier.
			foreach ($placeholders as $placeholder) {
				$bare_settingsText = str_replace([$placeholder . "\n\n", $placeholder], $placeholder . "\n", $bare_settingsText);
			}

			$bare_settingsText = preg_replace('/\h+$/m', '', rtrim($bare_settingsText));

			/*
			 * Divide the existing content into sections.
			 * The idea here is to make sure we don't mess with the relative position
			 * of any code blocks in the file, since that could break things. Within
			 * each section, however, we'll reorganize the content to match the
			 * default layout as closely as we can.
			 */
			$sections = [[]];
			$section_num = 0;
			$trimmed_placeholders = array_filter(array_map('trim', $placeholders));
			$newsection_placeholders = [];
			$all_custom_content = '';

			foreach ($substitutions as $var => $substitution) {
				if (is_int($var) && ($var === -2 || $var > 0) && isset($trimmed_placeholders[$var]) && strpos($bare_settingsText, $trimmed_placeholders[$var]) !== false) {
					$newsection_placeholders[$var] = $trimmed_placeholders[$var];
				}
			}

			foreach (preg_split('~(?<=' . implode('|', $trimmed_placeholders) . ')|(?=' . implode('|', $trimmed_placeholders) . ')~', $bare_settingsText) as $part) {
				$part = trim($part);

				if (empty($part)) {
					continue;
				}

				// Build a list of placeholders for this section.
				if (in_array($part, $trimmed_placeholders) && !in_array($part, $newsection_placeholders)) {
					$sections[$section_num][] = $part;
				}
				// Custom content and newsection_placeholders get their own sections.
				else {
					if (!empty($sections[$section_num])) {
						++$section_num;
					}

					$sections[$section_num][] = $part;

					++$section_num;

					if (!in_array($part, $trimmed_placeholders)) {
						$all_custom_content .= "\n" . $part;
					}
				}
			}

			// And now, rebuild the content!
			$new_settingsText = '';
			$done_defs = [];
			$sectionkeys = array_keys($sections);

			foreach ($sections as $sectionkey => $section) {
				// Custom content needs to be preserved.
				if (count($section) === 1 && !in_array($section[0], $trimmed_placeholders)) {
					$prev_section_end = $sectionkey < 1 ? 0 : strpos($settingsText, end($sections[$sectionkey - 1])) + strlen(end($sections[$sectionkey - 1]));
					$next_section_start = $sectionkey == end($sectionkeys) ? strlen($settingsText) : strpos($settingsText, $sections[$sectionkey + 1][0]);

					$new_settingsText .= "\n" . substr($settingsText, $prev_section_end, $next_section_start - $prev_section_end) . "\n";
				}
				// Put the placeholders in this section into canonical order.
				else {
					$section_parts = array_flip($section);
					$pathcode_reached = false;

					foreach ($settings_defs as $var => $setting_def) {
						if ($var === $pathcode_var) {
							$pathcode_reached = true;
						}

						// Already did this setting, so move on to the next.
						if (in_array($var, $done_defs)) {
							continue;
						}

						// Stop when we hit a setting definition that will start a later section.
						if (isset($newsection_placeholders[$var]) && count($section) !== 1) {
							break;
						}

						// Stop when everything in this section is done, unless it's the last.
						// This helps maintain the relative position of any custom content.
						if (empty($section_parts) && $sectionkey < (count($sections) - 1)) {
							break;
						}

						$p = trim($substitutions[$var]['placeholder']);

						// Can't do anything with an empty placeholder.
						if ($p === '') {
							continue;
						}

						// Does this need to be inserted before the path correction code?
						if (strpos($new_settingsText, trim($substitutions[$pathcode_var]['placeholder'])) !== false && in_array($var, $force_before_pathcode)) {
							$new_settingsText = strtr($new_settingsText, [$substitutions[$pathcode_var]['placeholder'] => $p . "\n" . $substitutions[$pathcode_var]['placeholder']]);

							$bare_settingsText .= "\n" . $substitutions[$var]['placeholder'];
							$done_defs[] = $var;
							unset($section_parts[trim($substitutions[$var]['placeholder'])]);
						}
						// If it's in this section, add it to the new text now.
						elseif (in_array($p, $section)) {
							$new_settingsText .= "\n" . $substitutions[$var]['placeholder'];
							$done_defs[] = $var;
							unset($section_parts[trim($substitutions[$var]['placeholder'])]);
						}
						// Perhaps it is safe to reposition it anyway.
						elseif (is_string($var) && strpos($new_settingsText, $p) === false && strpos($all_custom_content, '$' . $var) === false) {
							$new_settingsText .= "\n" . $substitutions[$var]['placeholder'];
							$done_defs[] = $var;
							unset($section_parts[trim($substitutions[$var]['placeholder'])]);
						}
						// If this setting is missing entirely, fix it.
						elseif (strpos($bare_settingsText, $p) === false) {
							// Special case if the path code is missing. Put it near the end,
							// and also anything else that is missing that normally follows it.
							if (!isset($newsection_placeholders[$pathcode_var]) && $pathcode_reached === true && $sectionkey < (count($sections) - 1)) {
								break;
							}

							$new_settingsText .= "\n" . $substitutions[$var]['placeholder'];
							$bare_settingsText .= "\n" . $substitutions[$var]['placeholder'];
							$done_defs[] = $var;
							unset($section_parts[trim($substitutions[$var]['placeholder'])]);
						}
					}
				}
			}
			$settingsText = $new_settingsText;

			// Restore the leading and trailing placeholders as necessary.
			foreach ([-1, -2] as $var) {
				if (!empty($substitutions[$var]['placeholder']) && strpos($settingsText, $substitutions[$var]['placeholder']) === false);
				{
					$settingsText = ($var == -1 ? $substitutions[$var]['placeholder'] : '') . $settingsText . ($var == -2 ? $substitutions[$var]['placeholder'] : '');
				}
			}
		}
		// Even if not rebuilding, there are a few variables that may need to be moved around.
		else {
			$pathcode_pos = strpos($settingsText, $substitutions[$pathcode_var]['placeholder']);

			if ($pathcode_pos !== false) {
				foreach ($force_before_pathcode as $var) {
					if (!empty($substitutions[$var]['placeholder']) && strpos($settingsText, $substitutions[$var]['placeholder']) > $pathcode_pos) {
						$settingsText = strtr($settingsText, [
							$substitutions[$var]['placeholder'] => '',
							$substitutions[$pathcode_var]['placeholder'] => $substitutions[$var]['placeholder'] . "\n" . $substitutions[$pathcode_var]['placeholder'],
						]);
					}
				}
			}
		}

		/* 3.c: Replace the placeholders with the final values */

		// Where possible, perform simple substitutions.
		$settingsText = strtr($settingsText, $simple_replacements);

		// Deal with any complicated ones.
		if (!empty($replace_patterns)) {
			$settingsText = preg_replace($replace_patterns, $replace_strings, $settingsText);
		}

		// Make absolutely sure that the path correction code is included.
		if (strpos($settingsText, $substitutions[$pathcode_var]['replacement']) === false) {
			$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', "\n" . $substitutions[$pathcode_var]['replacement'] . "\n", $settingsText);
		}

		// If we did not rebuild, do just enough to make sure the thing is viable.
		if (empty($rebuild)) {
			// We need to refresh $bare_settingsText again, and remove the code blocks from it.
			$bare_settingsText = $settingsText;

			foreach ($substitutions as $var => $substitution) {
				if (!is_int($var)) {
					break;
				}

				if (isset($substitution['replacement'])) {
					$bare_settingsText = str_replace($substitution['replacement'], '', $bare_settingsText);
				}
			}

			$bare_settingsText = self::stripPhpComments($bare_settingsText);

			// Now insert any defined settings that are missing.
			$pathcode_reached = false;

			foreach ($settings_defs as $var => $setting_def) {
				if ($var === $pathcode_var) {
					$pathcode_reached = true;
				}

				if (is_int($var)) {
					continue;
				}

				// Do nothing if it is already in there.
				if (preg_match($substitutions[$var]['search_pattern'], $bare_settingsText)) {
					continue;
				}

				// Insert it either before or after the path correction code, whichever is appropriate.
				if (!$pathcode_reached || in_array($var, $force_before_pathcode)) {
					$settingsText = preg_replace($substitutions[$pathcode_var]['search_pattern'], $substitutions[$var]['replacement'] . "\n\n$0", $settingsText);
				} else {
					$settingsText = preg_replace($substitutions[$pathcode_var]['search_pattern'], "$0\n\n" . $substitutions[$var]['replacement'], $settingsText);
				}
			}
		} else {
			// If the comments for some variables have changed since the last
			// time we rebuilt, we could have orphan copies of the old comments
			// still laying around.
			$prev_var = null;

			foreach ($settings_defs as $var => $setting_def) {
				if (!isset($setting_def['text'])) {
					$prev_var = $var;

					continue;
				}

				$before = is_int($prev_var) ? preg_quote($settings_defs[$prev_var]['text'], '~') . '\s*\K' : '';

				// If this setting's comment is immediately preceded by another
				// DocBlock comment, remove the preceding one.
				$settingsText = preg_replace('~' . $before . '(#[^\n]*\s*)?/[*]{2}([^*]|[*](?!/))*[*]/\s*' . preg_quote($setting_def['text'], '~') . '~', $setting_def['text'], $settingsText);

				$prev_var = $var;
			}
		}

		// If we have any brand new settings to add, do so.
		foreach ($new_settings_vars as $var => $val) {
			if (isset($substitutions[$var]) && !preg_match($substitutions[$var]['search_pattern'], $settingsText)) {
				if (!isset($settings_defs[$var]) && strpos($settingsText, '# Custom Settings #') === false) {
					$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', "\n\n######### Custom Settings #########\n", $settingsText);
				}

				$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', $substitutions[$var]['replacement'] . "\n", $settingsText);
			}
		}

		// This is just cosmetic. Get rid of extra lines of whitespace.
		$settingsText = preg_replace('~\n\s*\n~', "\n\n", $settingsText);

		/**************************************
		 * PART 4: Check syntax before saving *
		 **************************************/

		$temp_sfile = tempnam(self::getTempDir() . DIRECTORY_SEPARATOR, md5($prefix . 'Settings.php'));
		file_put_contents($temp_sfile, $settingsText);

		$result = self::getCurrentSettings(filemtime($temp_sfile), $temp_sfile);

		unlink($temp_sfile);

		// If the syntax is borked, try rebuilding to see if that fixes it.
		if ($result === false) {
			return empty($rebuild) ? self::updateSettingsFile($config_vars, $keep_quotes, true) : false;
		}

		/******************************************
		 * PART 5: Write updated settings to file *
		 ******************************************/

		$success = self::safeFileWrite($settingsFile, $settingsText, $backupFile, $last_settings_change);

		// Remember this in case updateSettingsFile is called twice.
		$mtime = filemtime($settingsFile);

		return $success;
	}

	/**
	 * Retrieves a copy of the current values of all settings in Settings.php.
	 *
	 * Importantly, it does this without affecting our working settings at all,
	 * and it performs safety checks before acting. The result is an array of
	 * the values as recorded in the settings file.
	 *
	 * @param int|float $mtime Timestamp of last known good configuration.
	 *    Defaults to time SMF started.
	 * @param string $settingsFile The settings file.
	 *    Defaults to SMF's standard Settings.php.
	 * @return array|bool An array of name/value pairs for all settings in the
	 *    file, or false on error.
	 */
	public static function getCurrentSettings(int|float|null $mtime = null, string $settingsFile = SMF_SETTINGS_FILE): array|bool
	{
		$mtime = is_null($mtime) ? (defined('TIME_START') ? TIME_START : $_SERVER['REQUEST_TIME']) : (int) $mtime;

		if (!is_file($settingsFile)) {
			if ($settingsFile !== SMF_SETTINGS_FILE && is_file(SMF_SETTINGS_FILE)) {
				$settingsFile = SMF_SETTINGS_FILE;
			} else {
				foreach (get_included_files() as $settingsFile) {
					if (basename($settingsFile) === basename(SMF_SETTINGS_FILE)) {
						break;
					}
				}

				if (basename($settingsFile) !== basename(SMF_SETTINGS_FILE)) {
					return false;
				}
			}
		}

		// If the file has been changed since the last known good configuration, bail out.
		clearstatcache();

		if (filemtime($settingsFile) > $mtime) {
			return false;
		}

		// Strip out opening and closing PHP tags.
		$settingsText = trim(file_get_contents($settingsFile));

		if (substr($settingsText, 0, 5) == '<' . '?php') {
			$settingsText = substr($settingsText, 5);
		}

		if (substr($settingsText, -2) == '?' . '>') {
			$settingsText = substr($settingsText, 0, -2);
		}

		// Since we're using eval, we need to manually replace these with strings.
		$settingsText = strtr($settingsText, [
			'__FILE__' => var_export($settingsFile, true),
			'__DIR__' => var_export(dirname($settingsFile), true),
		]);

		// Prevents warnings about constants that are already defined.
		$settingsText = preg_replace_callback(
			'~\bdefine\s*\(\s*(["\'])(\w+)\1~',
			function ($matches) {
				return 'define(\'' . bin2hex(random_bytes(16)) . '\'';
			},
			$settingsText,
		);

		// Handle eval errors gracefully in all PHP versions.
		try {
			if ($settingsText !== '' && @eval($settingsText) === false) {
				throw new \ErrorException('eval error');
			}

			unset($mtime, $settingsFile, $settingsText);
			$defined_vars = get_defined_vars();
		} catch (\Throwable $e) {
		} catch (\ErrorException $e) {
		}

		if (isset($e)) {
			return false;
		}

		return $defined_vars;
	}

	/**
	 * Writes data to a file, optionally making a backup, while avoiding race
	 * conditions.
	 *
	 * @param string $file The filepath of the file where the data should be
	 *    written.
	 * @param string $data The data to be written to $file.
	 * @param string|null $backup_file Path where the backup should be saved.
	 *    Default null.
	 * @param int|null $mtime If modification time of $file is more recent than
	 *    this Unix timestamp, the write operation will abort. Defaults to time
	 *    that the script started execution.
	 * @param bool $append If true, the data will be appended instead of
	 *    overwriting the existing content of the file. Default false.
	 * @return bool Whether the write operation succeeded or not.
	 */
	public static function safeFileWrite(string $file, string $data, ?string $backup_file = null, ?int $mtime = null, bool $append = false): bool
	{
		// Sanity checks.
		if (!file_exists($file) && !is_dir(dirname($file))) {
			return false;
		}

		if (!is_int($mtime)) {
			$mtime = defined('TIME_START') ? (int) TIME_START : $_SERVER['REQUEST_TIME'];
		}

		if (!isset(self::$temp_dir)) {
			self::getTempDir();
		}

		// Our temp files.
		$temp_sfile = tempnam(self::$temp_dir . DIRECTORY_SEPARATOR, pathinfo($file, PATHINFO_FILENAME) . '.');

		if (!empty($backup_file)) {
			$temp_bfile = tempnam(self::$temp_dir, pathinfo($backup_file, PATHINFO_FILENAME) . '.');
		}

		// We need write permissions.
		$failed = false;

		foreach ([$file, $backup_file] as $sf) {
			if (empty($sf)) {
				continue;
			}

			if (!file_exists($sf)) {
				touch($sf);
			} elseif (!is_file($sf)) {
				$failed = true;
			}

			if (!$failed) {
				$failed = !is_writable($sf);
			}
		}

		// Now let's see if writing to a temp file succeeds.
		if (!$failed && file_put_contents($temp_sfile, $data, LOCK_EX) !== strlen($data)) {
			$failed = true;
		}

		// Tests passed, so it's time to do the job.
		if (!$failed) {
			// Back up the backup, just in case.
			if (!empty($backup_file) && file_exists($backup_file)) {
				$temp_bfile_saved = @copy($backup_file, $temp_bfile);
			}

			// Make sure no one changed the file while we weren't looking.
			clearstatcache();

			if ((int) filemtime($file) <= $mtime) {
				// Attempt to open the file.
				$sfhandle = @fopen($file, 'c');

				// Let's do this thing!
				if ($sfhandle !== false) {
					// Immediately get a lock.
					flock($sfhandle, LOCK_EX);

					// Make sure the backup works before we do anything more.
					$temp_sfile_saved = @copy($file, $temp_sfile);

					// Now write our data to the file.
					if ($temp_sfile_saved) {
						if (empty($append)) {
							ftruncate($sfhandle, 0);
							rewind($sfhandle);
						}

						$failed = fwrite($sfhandle, $data) !== strlen($data);
					} else {
						$failed = true;
					}

					// If writing failed, put everything back the way it was.
					if ($failed) {
						if (!empty($temp_sfile_saved)) {
							@rename($temp_sfile, $file);
						}

						if (!empty($temp_bfile_saved)) {
							@rename($temp_bfile, $backup_file);
						}
					}
					// It worked, so make our temp backup the new permanent backup.
					elseif (!empty($backup_file)) {
						@rename($temp_sfile, $backup_file);
					}

					// And we're done.
					flock($sfhandle, LOCK_UN);
					fclose($sfhandle);
				}
			}
		}

		// We're done with these.
		@unlink($temp_sfile);

		if (!empty($temp_bfile)) {
			@unlink($temp_bfile);
		}

		if ($failed) {
			return false;
		}

		// Even though on normal installations the filemtime should invalidate
		// any cached version, it seems that there are times it might not.
		// So let's MAKE it dump the cache.
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($file, true);
		}

		return true;
	}

	/**
	 * A wrapper around var_export whose output matches SMF coding conventions.
	 *
	 * @todo Add special handling for objects?
	 *
	 * @param mixed $var The variable to export
	 * @return string A PHP-parseable representation of the variable's value
	 */
	public static function varExport($var): string
	{
		/*
		 * Old versions of updateSettingsFile couldn't handle multi-line values.
		 * Even though technically we can now, we'll keep arrays on one line for
		 * the sake of backwards compatibility.
		 */
		if (is_array($var)) {
			$return = [];

			foreach ($var as $key => $value) {
				$return[] = var_export($key, true) . ' => ' . self::varExport($value);
			}

			return 'array(' . implode(', ', $return) . ')';
		}

		// For the same reason, replace literal returns and newlines with "\r" and "\n"
		if (is_string($var) && (strpos($var, "\n") !== false || strpos($var, "\r") !== false)) {
			return strtr(
				preg_replace_callback(
					'/[\r\n]+/',
					function ($m) {
						return '\' . "' . strtr($m[0], ["\r" => '\r', "\n" => '\n']) . '" . \'';
					},
					var_export($var, true),
				),
				["'' . " => '', " . ''" => ''],
			);
		}
		// We typically use lowercase true/false/null.
		elseif (in_array(gettype($var), ['boolean', 'NULL'])) {
			return strtolower(var_export($var, true));
		}
		// Nothing special.
		else {
			return var_export($var, true);
		}
	}

	/**
	 * Deletes all PHP comments from a string.
	 *
	 * @param string $code_str A string containing PHP code.
	 * @return string A string of PHP code with no comments in it.
	 */
	public static function stripPhpComments(string $code_str): string
	{
		// This is the faster, better way.
		if (is_callable('token_get_all')) {
			$tokens = token_get_all($code_str);

			$parts = [];

			foreach ($tokens as $token) {
				if (is_string($token)) {
					$parts[] = $token;
				} else {
					list($id, $text) = $token;

					switch ($id) {
						case T_COMMENT:
						case T_DOC_COMMENT:
							end($parts);
							$prev_part = key($parts);

							// For the sake of tider output, trim any horizontal
							// whitespace that immediately preceded the comment.
							$parts[$prev_part] = rtrim($parts[$prev_part], "\t ");

							// For 'C' style comments, also trim one preceding
							// line break, if present.
							if (strpos($text, '/*') === 0) {
								if (substr($parts[$prev_part], -2) === "\r\n") {
									$parts[$prev_part] = substr($parts[$prev_part], 0, -2);
								} elseif (in_array(substr($parts[$prev_part], -1), ["\r", "\n"])) {
									$parts[$prev_part] = substr($parts[$prev_part], 0, -1);
								}
							}

							break;

						default:
							$parts[] = $text;
							break;
					}
				}
			}

			$code_str = implode('', $parts);

			return $code_str;
		}

		// If the tokenizer extension has been disabled, do the job manually.

		// Leave any heredocs alone.
		if (preg_match_all('/<<<([\'"]?)(\w+)\1?\R(.*?)\R\h*\2;$/ms', $code_str, $matches)) {
			$heredoc_replacements = [];

			foreach ($matches[0] as $mkey => $heredoc) {
				$heredoc_replacements[$heredoc] = var_export(md5($matches[3][$mkey]), true) . ';';
			}

			$code_str = strtr($code_str, $heredoc_replacements);
		}

		// Split before everything that could possibly delimit a comment or a string.
		$parts = preg_split('~(?=#+|/(?=/|\*)|\*/|\R|(?<!\\\)[\'"])~m', $code_str);

		$in_string = 0;
		$in_comment = 0;

		foreach ($parts as $partkey => $part) {
			$one_char = substr($part, 0, 1);
			$two_char = substr($part, 0, 2);
			$to_remove = 0;

			/*
			 * Meaning of $in_string values:
			 *	0: not in a string
			 *	1: in a single quote string
			 *	2: in a double quote string
			 */
			if ($one_char == "'") {
				if (!empty($in_comment)) {
					$in_string = 0;
				} elseif (in_array($in_string, [0, 1])) {
					$in_string = ($in_string ^ 1);
				}
			} elseif ($one_char == '"') {
				if (!empty($in_comment)) {
					$in_string = 0;
				} elseif (in_array($in_string, [0, 2])) {
					$in_string = ($in_string ^ 2);
				}
			}

			/*
			 * Meaning of $in_comment values:
			 * 	0: not in a comment
			 *	1: in a single line comment
			 *	2: in a multi-line comment
			 */
			elseif ($one_char == '#' || $two_char == '//') {
				$in_comment = !empty($in_string) ? 0 : (empty($in_comment) ? 1 : $in_comment);

				if ($in_comment == 1) {
					$parts[$partkey - 1] = rtrim($parts[$partkey - 1], "\t ");

					if (substr($parts[$partkey - 1], -2) === "\r\n") {
						$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -2);
					} elseif (in_array(substr($parts[$partkey - 1], -1), ["\r", "\n"])) {
						$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -1);
					}
				}
			} elseif ($two_char === "\r\n" || $one_char === "\r" || $one_char === "\n") {
				if ($in_comment == 1) {
					$in_comment = 0;
				}
			} elseif ($two_char == '/*') {
				$in_comment = !empty($in_string) ? 0 : (empty($in_comment) ? 2 : $in_comment);

				if ($in_comment == 2) {
					$parts[$partkey - 1] = rtrim($parts[$partkey - 1], "\t ");

					if (substr($parts[$partkey - 1], -2) === "\r\n") {
						$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -2);
					} elseif (in_array(substr($parts[$partkey - 1], -1), ["\r", "\n"])) {
						$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -1);
					}
				}
			} elseif ($two_char == '*/') {
				if ($in_comment == 2) {
					$in_comment = 0;

					// Delete the comment closing.
					$to_remove = 2;
				}
			}

			if (empty($in_comment)) {
				$parts[$partkey] = strlen($part) > $to_remove ? substr($part, $to_remove) : '';
			} else {
				$parts[$partkey] = '';
			}
		}

		$code_str = implode('', $parts);

		if (!empty($heredoc_replacements)) {
			$code_str = strtr($code_str, array_flip($heredoc_replacements));
		}

		return $code_str;
	}

	/**
	 * Saves the time of the last db error for the error log.
	 *
	 * Done separately from updateSettingsFile to avoid race conditions that can
	 * occur during a db error.
	 *
	 * If it fails, Settings.php will assume 0.
	 *
	 * @param int $time The timestamp of the last DB error
	 * @param bool True If we should update the current db_last_error context as well.  This may be useful in cases where the current context needs to know a error was logged since the last check.
	 * @return bool True If we could succesfully put the file or not.
	 */
	public static function updateDbLastError(int $time, bool $update = true): bool
	{
		// Write out the db_last_error file with the error timestamp
		if (!empty(self::$cachedir) && is_writable(self::$cachedir)) {
			$errorfile = self::$cachedir . '/db_last_error.php';
		} elseif (file_exists(dirname(__DIR__) . '/cache')) {
			$errorfile = dirname(__DIR__) . '/cache/db_last_error.php';
		} else {
			$errorfile = dirname(__DIR__) . '/db_last_error.php';
		}

		$result = file_put_contents($errorfile, '<' . '?' . "php\n" . '$db_last_error = ' . $time . ';' . "\n" . '?' . '>', LOCK_EX);

		@touch(SMF_SETTINGS_FILE);

		// Unless requested, we should update self::$db_last_error as well.
		if ($update) {
			self::$db_last_error = $time;
		}

		// We  do a loose match here rather than strict (!==) as 0 is also false.
		return $result != false;
	}

	/**
	 * Locates the most appropriate temporary directory.
	 *
	 * Systems using `open_basedir` restrictions may receive errors with
	 * `sys_get_temp_dir()` due to misconfigurations on servers. Other
	 * cases sys_temp_dir may not be set to a safe value. Additionally
	 * `sys_get_temp_dir` may use a readonly directory. This attempts to
	 * find a working temp directory that is accessible under the
	 * restrictions and is writable to the web service account.
	 *
	 * Directories checked against `open_basedir`:
	 *
	 * - `sys_get_temp_dir()`
	 * - `upload_tmp_dir`
	 * - `session.save_path`
	 * - `cachedir`
	 *
	 * @return string Path to a temporary directory.
	 */
	public static function getTempDir(): string
	{
		// Already did this.
		if (!empty(self::$temp_dir)) {
			return self::$temp_dir;
		}

		// Temp Directory options order.
		$temp_dir_options = [
			0 => 'sys_get_temp_dir',
			1 => 'upload_tmp_dir',
			2 => 'session.save_path',
			3 => 'cachedir',
		];

		// Is self::$cachedir a valid option?
		if (empty(self::$cachedir) || !is_dir(self::$cachedir) || !is_writable(self::$cachedir)) {
			$temp_dir_options = array_diff($temp_dir_options, ['cachedir']);
		}

		// Determine if we should detect a restriction and what restrictions that may be.
		$open_base_dir = ini_get('open_basedir');
		$restriction = !empty($open_base_dir) ? explode(':', $open_base_dir) : false;

		// Prevent any errors as we search.
		$old_error_reporting = error_reporting(0);

		// Search for a working temp directory.
		foreach ($temp_dir_options as $id_temp => $temp_option) {
			switch ($temp_option) {
				case 'cachedir':
					$possible_temp = rtrim(self::$cachedir, '\\/');
					break;

				case 'session.save_path':
					$possible_temp = rtrim(ini_get('session.save_path'), '\\/');
					break;

				case 'upload_tmp_dir':
					$possible_temp = rtrim(ini_get('upload_tmp_dir'), '\\/');
					break;

				default:
					$possible_temp = sys_get_temp_dir();
					break;
			}

			// Check if we have a restriction preventing this from working.
			if ($restriction) {
				foreach ($restriction as $dir) {
					if (strpos($possible_temp, $dir) !== false && is_writable($possible_temp)) {
						self::$temp_dir = $possible_temp;
						break;
					}
				}
			}
			// No restrictions, but need to check for writable status.
			elseif (is_writable($possible_temp)) {
				self::$temp_dir = $possible_temp;
				break;
			}
		}

		// Fall back to sys_get_temp_dir even though it won't work, so we have something.
		if (empty(self::$temp_dir)) {
			self::$temp_dir = sys_get_temp_dir();
		}

		// Put things back.
		error_reporting($old_error_reporting);

		return self::$temp_dir;
	}

	/**
	 * Helper function to set the system memory to a needed value.
	 *
	 * - If the needed memory is greater than current, will attempt to get more.
	 * - If $in_use is set to true, will also try to take the current memory
	 *   usage in to account.
	 *
	 * @param string $needed The amount of memory to request. E.g.: '256M'.
	 * @param bool $in_use Set to true to account for current memory usage.
	 * @return bool Whether we have the needed amount memory.
	 */
	public static function setMemoryLimit(string $needed, bool $in_use = false): bool
	{
		// Everything in bytes.
		$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		$memory_needed = self::memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use) {
			$memory_needed += memory_get_usage();
		}

		// If more is needed, request it.
		if ($memory_current < $memory_needed) {
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		}

		$memory_current = max($memory_current, self::memoryReturnBytes(get_cfg_var('memory_limit')));

		// Return success or not.
		return (bool) ($memory_current >= $memory_needed);
	}

	/**
	 * Helper function to convert memory string settings to bytes
	 *
	 * @param string $val The byte string, like '256M' or '1G'.
	 * @return int The string converted to a proper integer in bytes.
	 */
	public static function memoryReturnBytes(string $val): int
	{
		if (is_integer($val)) {
			return $val;
		}

		// Separate the number from the designator.
		$val = trim($val);
		$num = intval(substr($val, 0, strlen($val) - 1));
		$last = strtolower(substr($val, -1));

		// Convert to bytes.
		switch ($last) {
			case 'g':
				$num *= 1024;
				// no break

			case 'm':
				$num *= 1024;
				// no break

			case 'k':
				$num *= 1024;
		}

		return $num;
	}

	/**
	 * Check if the connection is using HTTPS.
	 *
	 * @return bool Whether the connection is using HTTPS.
	 */
	public static function httpsOn(): bool
	{
		return ($_SERVER['HTTPS'] ?? null) == 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) == 'https' || ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? null) == 'on';
	}

	/**
	 * Generate a random seed and ensure it's stored in settings.
	 *
	 * @deprecated since 3.0
	 *
	 * This only exists for backward compatibility with mods that might use the
	 * generated value.
	 */
	public static function generateSeed(): void
	{
		self::updateModSettings(['rand_seed' => random_int(0, 2 ** 31 - 1)]);
	}

	/**
	 * Ensures SMF's scheduled tasks are being run as intended
	 *
	 * If the admin activated the cron_is_real_cron setting, but the cron job is
	 * not running things at least once per day, we need to go back to SMF's default
	 * behaviour using "web cron" JavaScript calls.
	 */
	public static function checkCron()
	{
		if (!empty(self::$modSettings['cron_is_real_cron']) && time() - @intval(self::$modSettings['cron_last_checked']) > 86400) {
			$request = Db\DatabaseApi::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}scheduled_tasks
				WHERE disabled = {int:not_disabled}
					AND next_time < {int:yesterday}',
				[
					'not_disabled' => 0,
					'yesterday' => time() - 86400,
				],
			);
			list($overdue) = Db\DatabaseApi::$db->fetch_row($request);
			Db\DatabaseApi::$db->free_result($request);

			// If we have tasks more than a day overdue, cron isn't doing its job.
			if (!empty($overdue)) {
				Lang::load('ManageScheduledTasks');
				ErrorHandler::log(Lang::$txt['cron_not_working']);
				self::updateModSettings(['cron_is_real_cron' => 0]);
			} else {
				self::updateModSettings(['cron_last_checked' => time()]);
			}
		}
	}
}

?>