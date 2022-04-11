<?php

/**
 * This file contains functions that are specifically done by administrators.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */

use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'php' or 'server'
 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
 */
function getServerVersions($checkFor)
{
	global $txt, $db_connection, $sourcedir, $smcFunc, $modSettings;

	loadLanguage('Admin');
	loadLanguage('ManageSettings');

	$versions = array();

	// Is GD available?  If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array('title' => $txt['support_versions_gd'], 'version' => $temp['GD Version']);
	}

	// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
	if (in_array('imagemagick', $checkFor) && (class_exists('Imagick') || function_exists('MagickGetVersionString')))
	{
		if (class_exists('Imagick'))
		{
			$temp = New Imagick;
			$temp2 = $temp->getVersion();
			$im_version = $temp2['versionString'];
			$extension_version = 'Imagick ' . phpversion('Imagick');
		}
		else
		{
			$im_version = MagickGetVersionString();
			$extension_version = 'MagickWand ' . phpversion('MagickWand');
		}

		// We already know it's ImageMagick and the website isn't needed...
		$im_version = str_replace(array('ImageMagick ', ' https://www.imagemagick.org'), '', $im_version);
		$versions['imagemagick'] = array('title' => $txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')');
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		db_extend();
		if (!isset($db_connection) || $db_connection === false)
		{
			loadLanguage('Errors');
			trigger_error($txt['get_server_versions_no_database'], E_USER_NOTICE);
		}
		else
		{
			$versions['db_engine'] = array(
				'title' => sprintf($txt['support_versions_db_engine'], $smcFunc['db_title']),
				'version' => $smcFunc['db_get_vendor'](),
			);
			$versions['db_server'] = array(
				'title' => sprintf($txt['support_versions_db'], $smcFunc['db_title']),
				'version' => $smcFunc['db_get_version'](),
			);
		}
	}

	// Check to see if we have any accelerators installed.
	require_once($sourcedir . '/ManageServer.php');
	$detected = loadCacheAPIs();

	/* @var CacheApiInterface $cache_api */
	foreach ($detected as $class_name => $cache_api)
	{
		$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

		if (in_array($class_name_txt_key, $checkFor))
			$versions[$class_name_txt_key] = array(
				'title' => isset($txt[$class_name_txt_key . '_cache']) ?
					$txt[$class_name_txt_key . '_cache'] : $class_name,
				'version' => $cache_api->getVersion(),
			);
	}

	if (in_array('php', $checkFor))
		$versions['php'] = array(
			'title' => 'PHP',
			'version' => PHP_VERSION,
			'more' => '?action=admin;area=serversettings;sa=phpinfo',
		);

	if (in_array('server', $checkFor))
		$versions['server'] = array(
			'title' => $txt['support_versions_server'],
			'version' => $_SERVER['SERVER_SOFTWARE'],
		);

	return $versions;
}

/**
 * Search through source, theme and language files to determine their version.
 * Get detailed version information about the physical SMF files on the server.
 *
 * - the input parameter allows to set whether to include SSI.php and whether
 *   the results should be sorted.
 * - returns an array containing information on source files, templates and
 *   language files found in the default theme directory (grouped by language).
 *
 * @param array &$versionOptions An array of options. Can contain one or more of 'include_ssi', 'include_subscriptions', 'include_tasks' and 'sort_results'
 * @return array An array of file version info.
 */
function getFileVersions(&$versionOptions)
{
	global $boarddir, $sourcedir, $settings, $tasksdir;

	// Default place to find the languages would be the default theme dir.
	$lang_dir = $settings['default_theme_dir'] . '/languages';

	$version_info = array(
		'file_versions' => array(),
		'default_template_versions' => array(),
		'template_versions' => array(),
		'default_language_versions' => array(),
		'tasks_versions' => array(),
	);

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists($boarddir . '/SSI.php'))
	{
		$fp = fopen($boarddir . '/SSI.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// The comment looks rougly like... that.
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['SSI.php'] = $match[1];
		// Not found!  This is bad.
		else
			$version_info['file_versions']['SSI.php'] = '??';
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']) && file_exists($boarddir . '/subscriptions.php'))
	{
		$fp = fopen($boarddir . '/subscriptions.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// Found it?
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['subscriptions.php'] = $match[1];
		// If we haven't how do we all get paid?
		else
			$version_info['file_versions']['subscriptions.php'] = '??';
	}

	// Load all the files in the Sources directory, except this file and the redirect.
	$sources_dir = dir($sourcedir);
	while ($entry = $sources_dir->read())
	{
		if (substr($entry, -4) === '.php' && !is_dir($sourcedir . '/' . $entry) && $entry !== 'index.php')
		{
			// Read the first 4k from the file.... enough for the header.
			$fp = fopen($sourcedir . '/' . $entry, 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			// Look for the version comment in the file header.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$version_info['file_versions'][$entry] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['file_versions'][$entry] = '??';
		}
	}
	$sources_dir->close();

	// Load all the files in the tasks directory.
	if (!empty($versionOptions['include_tasks']))
	{
		$tasks_dir = dir($tasksdir);
		while ($entry = $tasks_dir->read())
		{
			if (substr($entry, -4) === '.php' && !is_dir($tasksdir . '/' . $entry) && $entry !== 'index.php')
			{
				// Read the first 4k from the file.... enough for the header.
				$fp = fopen($tasksdir . '/' . $entry, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				// Look for the version comment in the file header.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					$version_info['tasks_versions'][$entry] = $match[1];
				// It wasn't found, but the file was... show a '??'.
				else
					$version_info['tasks_versions'][$entry] = '??';
			}
		}
		$tasks_dir->close();
	}

	// Load all the files in the default template directory - and the current theme if applicable.
	$directories = array('default_template_versions' => $settings['default_theme_dir']);
	if ($settings['theme_id'] != 1)
		$directories += array('template_versions' => $settings['theme_dir']);

	foreach ($directories as $type => $dirname)
	{
		$this_dir = dir($dirname);
		while ($entry = $this_dir->read())
		{
			if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry))
			{
				// Read the first 768 bytes from the file.... enough for the header.
				$fp = fopen($dirname . '/' . $entry, 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Look for the version comment in the file header.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					$version_info[$type][$entry] = $match[1];
				// It wasn't found, but the file was... show a '??'.
				else
					$version_info[$type][$entry] = '??';
			}
		}
		$this_dir->close();
	}

	// Load up all the files in the default language directory and sort by language.
	$this_dir = dir($lang_dir);
	while ($entry = $this_dir->read())
	{
		if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry))
		{
			// Read the first 768 bytes from the file.... enough for the header.
			$fp = fopen($lang_dir . '/' . $entry, 'rb');
			$header = fread($fp, 768);
			fclose($fp);

			// Split the file name off into useful bits.
			list ($name, $language) = explode('.', $entry);

			// Look for the version comment in the file header.
			if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				$version_info['default_language_versions'][$language][$name] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['default_language_versions'][$language][$name] = '??';
		}
	}
	$this_dir->close();

	// Sort the file versions by filename.
	if (!empty($versionOptions['sort_results']))
	{
		ksort($version_info['file_versions']);
		ksort($version_info['default_template_versions']);
		ksort($version_info['template_versions']);
		ksort($version_info['default_language_versions']);
		ksort($version_info['tasks_versions']);

		// For languages sort each language too.
		foreach ($version_info['default_language_versions'] as $language => $dummy)
			ksort($version_info['default_language_versions'][$language]);
	}
	return $version_info;
}

/**
 * Describes properties of all known Settings.php variables and other content.
 * Helper for updateSettingsFile(); also called by saveSettings().
 *
 * @return array Descriptions of all known Settings.php content
 */
function get_settings_defs()
{
	/*
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
	$settings_defs = array(
		array(
			'text' => implode("\n", array(
				'',
				'/**',
				' * The settings file contains all of the basic settings that need to be present when a database/cache is not available.',
				' *',
				' * Simple Machines Forum (SMF)',
				' *',
				' * @package SMF',
				' * @author Simple Machines https://www.simplemachines.org',
				' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors',
				' * @license https://www.simplemachines.org/about/smf/license.php BSD',
				' *',
				' * @version ' . SMF_VERSION,
				' */',
				'',
			)),
			'search_pattern' => '~/\*\*.*?@package\h+SMF\b.*?\*/\n{0,2}~s',
		),
		'maintenance' => array(
			'text' => implode("\n", array(
				'',
				'########## Maintenance ##########',
				'/**',
				' * The maintenance "mode"',
				' * Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you\'ll have to make it 0 again manually!)',
				' * 0 is default and disables maintenance mode.',
				' *',
				' * @var int 0, 1, 2',
				' * @global int $maintenance',
				' */',
			)),
			'default' => 0,
			'type' => 'integer',
		),
		'mtitle' => array(
			'text' => implode("\n", array(
				'/**',
				' * Title for the Maintenance Mode message.',
				' *',
				' * @var string',
				' * @global int $mtitle',
				' */',
			)),
			'default' => 'Maintenance Mode',
			'type' => 'string',
		),
		'mmessage' => array(
			'text' => implode("\n", array(
				'/**',
				' * Description of why the forum is in maintenance mode.',
				' *',
				' * @var string',
				' * @global string $mmessage',
				' */',
			)),
			'default' => 'Okay faithful users...we\'re attempting to restore an older backup of the database...news will be posted once we\'re back!',
			'type' => 'string',
		),
		'mbname' => array(
			'text' => implode("\n", array(
				'',
				'########## Forum Info ##########',
				'/**',
				' * The name of your forum.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'My Community',
			'type' => 'string',
		),
		'language' => array(
			'text' => implode("\n", array(
				'/**',
				' * The default language file set for the forum.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'english',
			'type' => 'string',
		),
		'boardurl' => array(
			'text' => implode("\n", array(
				'/**',
				' * URL to your forum\'s folder. (without the trailing /!)',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'http://127.0.0.1/smf',
			'type' => 'string',
		),
		'webmaster_email' => array(
			'text' => implode("\n", array(
				'/**',
				' * Email address to send emails from. (like noreply@yourdomain.com.)',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'noreply@myserver.com',
			'type' => 'string',
		),
		'cookiename' => array(
			'text' => implode("\n", array(
				'/**',
				' * Name of the cookie to set for authentication.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'SMFCookie11',
			'type' => 'string',
		),
		'auth_secret' => array(
			'text' => implode("\n", array(
				'/**',
				' * Secret key used to create and verify cookies, tokens, etc.',
				' * Do not change this unless absolutely necessary, and NEVER share it.',
				' *',
				' * Note: Changing this will immediately log out all members of your forum',
				' * and break the token-based links in all previous email notifications,',
				' * among other possible effects.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => null,
			'auto_delete' => 1,
			'type' => 'string',
		),
		'db_type' => array(
			'text' => implode("\n", array(
				'',
				'########## Database Info ##########',
				'/**',
				' * The database type',
				' * Default options: mysql, postgresql',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'mysql',
			'type' => 'string',
		),
		'db_port' => array(
			'text' => implode("\n", array(
				'/**',
				' * The database port',
				' * 0 to use default port for the database type',
				' *',
				' * @var int',
				' */',
			)),
			'default' => 0,
			'type' => 'integer',
		),
		'db_server' => array(
			'text' => implode("\n", array(
				'/**',
				' * The server to connect to (or a Unix socket)',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'localhost',
			'required' => true,
			'type' => 'string',
		),
		'db_name' => array(
			'text' => implode("\n", array(
				'/**',
				' * The database name',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'smf',
			'required' => true,
			'type' => 'string',
		),
		'db_user' => array(
			'text' => implode("\n", array(
				'/**',
				' * Database username',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'root',
			'required' => true,
			'type' => 'string',
		),
		'db_passwd' => array(
			'text' => implode("\n", array(
				'/**',
				' * Database password',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
			'required' => true,
			'type' => 'string',
			'is_password' => true,
		),
		'ssi_db_user' => array(
			'text' => implode("\n", array(
				'/**',
				' * Database user for when connecting with SSI',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
			'type' => 'string',
		),
		'ssi_db_passwd' => array(
			'text' => implode("\n", array(
				'/**',
				' * Database password for when connecting with SSI',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
			'type' => 'string',
			'is_password' => true,
		),
		'db_prefix' => array(
			'text' => implode("\n", array(
				'/**',
				' * A prefix to put in front of your table names.',
				' * This helps to prevent conflicts',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'smf_',
			'required' => true,
			'type' => 'string',
		),
		'db_persist' => array(
			'text' => implode("\n", array(
				'/**',
				' * Use a persistent database connection',
				' *',
				' * @var bool',
				' */',
			)),
			'default' => false,
			'type' => 'boolean',
		),
		'db_error_send' => array(
			'text' => implode("\n", array(
				'/**',
				' * Send emails on database connection error',
				' *',
				' * @var bool',
				' */',
			)),
			'default' => false,
			'type' => 'boolean',
		),
		'db_mb4' => array(
			'text' => implode("\n", array(
				'/**',
				' * Override the default behavior of the database layer for mb4 handling',
				' * null keep the default behavior untouched',
				' *',
				' * @var null|bool',
				' */',
			)),
			'default' => null,
			'type' => array('NULL', 'boolean'),
		),
		'cache_accelerator' => array(
			'text' => implode("\n", array(
				'',
				'########## Cache Info ##########',
				'/**',
				' * Select a cache system. You want to leave this up to the cache area of the admin panel for',
				' * proper detection of memcached, output_cache, or smf file system',
				' * (you can add more with a mod).',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
			'type' => 'string',
		),
		'cache_enable' => array(
			'text' => implode("\n", array(
				'/**',
				' * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).',
				' *',
				' * @var int',
				' */',
			)),
			'default' => 0,
			'type' => 'integer',
		),
		'cache_memcached' => array(
			'text' => implode("\n", array(
				'/**',
				' * This is only used for memcache / memcached. Should be a string of \'server:port,server:port\'',
				' *',
				' * @var array',
				' */',
			)),
			'default' => '',
			'type' => 'string',
		),
		'cachedir' => array(
			'text' => implode("\n", array(
				'/**',
				' * This is only for the \'smf\' file cache system. It is the path to the cache directory.',
				' * It is also recommended that you place this in /tmp/ if you are going to use this.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'dirname(__FILE__) . \'/cache\'',
			'raw_default' => true,
			'type' => 'string',
		),
		'cachedir_sqlite' => array(
			'text' => implode("\n", array(
				'/**',
				' * This is only for SQLite3 cache system. It is the path to the directory where the SQLite3',
				' * database file will be saved.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
			'auto_delete' => 2,
			'type' => 'string',
		),
		'image_proxy_enabled' => array(
			'text' => implode("\n", array(
				'',
				'########## Image Proxy ##########',
				'# This is done entirely in Settings.php to avoid loading the DB while serving the images',
				'/**',
				' * Whether the proxy is enabled or not',
				' *',
				' * @var bool',
				' */',
			)),
			'default' => true,
			'type' => 'boolean',
		),
		'image_proxy_secret' => array(
			'text' => implode("\n", array(
				'/**',
				' * Secret key to be used by the proxy',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'smfisawesome',
			'type' => 'string',
		),
		'image_proxy_maxsize' => array(
			'text' => implode("\n", array(
				'/**',
				' * Maximum file size (in KB) for individual files',
				' *',
				' * @var int',
				' */',
			)),
			'default' => 5192,
			'type' => 'integer',
		),
		'boarddir' => array(
			'text' => implode("\n", array(
				'',
				'########## Directories/Files ##########',
				'# Note: These directories do not have to be changed unless you move things.',
				'/**',
				' * The absolute path to the forum\'s folder. (not just \'.\'!)',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'dirname(__FILE__)',
			'raw_default' => true,
			'type' => 'string',
		),
		'sourcedir' => array(
			'text' => implode("\n", array(
				'/**',
				' * Path to the Sources directory.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'dirname(__FILE__) . \'/Sources\'',
			'raw_default' => true,
			'type' => 'string',
		),
		'packagesdir' => array(
			'text' => implode("\n", array(
				'/**',
				' * Path to the Packages directory.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'dirname(__FILE__) . \'/Packages\'',
			'raw_default' => true,
			'type' => 'string',
		),
		'tasksdir' => array(
			'text' => implode("\n", array(
				'/**',
				' * Path to the tasks directory.',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '$sourcedir . \'/tasks\'',
			'raw_default' => true,
			'type' => 'string',
		),
		array(
			'text' => implode("\n", array(
				'',
				'# Make sure the paths are correct... at least try to fix them.',
				'if (!is_dir(realpath($boarddir)) && file_exists(dirname(__FILE__) . \'/agreement.txt\'))',
				'	$boarddir = dirname(__FILE__);',
				'if (!is_dir(realpath($sourcedir)) && is_dir($boarddir . \'/Sources\'))',
				'	$sourcedir = $boarddir . \'/Sources\';',
				'if (!is_dir(realpath($tasksdir)) && is_dir($sourcedir . \'/tasks\'))',
				'	$tasksdir = $sourcedir . \'/tasks\';',
				'if (!is_dir(realpath($packagesdir)) && is_dir($boarddir . \'/Packages\'))',
				'	$packagesdir = $boarddir . \'/Packages\';',
				'if (!is_dir(realpath($cachedir)) && is_dir($boarddir . \'/cache\'))',
				'	$cachedir = $boarddir . \'/cache\';',
			)),
			'search_pattern' => '~\n?(#[^\n]+)?(?:\n\h*if\s*\((?:\!file_exists\(\$(?'.'>boarddir|sourcedir|tasksdir|packagesdir|cachedir)\)|\!is_dir\(realpath\(\$(?'.'>boarddir|sourcedir|tasksdir|packagesdir|cachedir)\)\))[^;]+\n\h*\$(?'.'>boarddir|sourcedir|tasksdir|packagesdir|cachedir)[^\n]+;)+~sm',
		),
		'db_character_set' => array(
			'text' => implode("\n", array(
				'',
				'######### Legacy Settings #########',
				'# UTF-8 is now the only character set supported in 2.1.',
			)),
			'default' => 'utf8',
			'type' => 'string',
		),
		'db_show_debug' => array(
			'text' => implode("\n", array(
				'',
				'######### Developer Settings #########',
				'# Show debug info.',
			)),
			'default' => false,
			'auto_delete' => 2,
			'type' => 'boolean',
		),
		array(
			'text' => implode("\n", array(
				'',
				'########## Error-Catching ##########',
				'# Note: You shouldn\'t touch these settings.',
				'if (file_exists((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\'))',
				'	include((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\');',
				'',
				'if (!isset($db_last_error))',
				'{',
				'	// File does not exist so lets try to create it',
				'	file_put_contents((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\', \'<\' . \'?\' . "php\n" . \'$db_last_error = 0;\' . "\n" . \'?\' . \'>\');',
				'	$db_last_error = 0;',
				'}',
			)),
			// Designed to match both 2.0 and 2.1 versions of this code.
			'search_pattern' => '~\n?#+ Error.Catching #+\n[^\n]*?settings\.\n(?:\$db_last_error = \d{1,11};|if \(file_exists.*?\$db_last_error = 0;(?' . '>\s*}))(?=\n|\?' . '>|$)~s',
		),
		// Temporary variable used during the upgrade process.
		'upgradeData' => array(
			'default' => '',
			'auto_delete' => 1,
			'type' => 'string',
		),
		// This should be removed if found.
		'db_last_error' => array(
			'default' => 0,
			'auto_delete' => 1,
			'type' => 'integer',
		),
	);

	// Allow mods the option to define comments, defaults, etc., for their settings.
	// Check if function exists, in case we are calling from installer or upgrader.
	if (function_exists('call_integration_hook'))
		call_integration_hook('integrate_update_settings_file', array(&$settings_defs));

	return $settings_defs;
}

/**
 * Update the Settings.php file.
 *
 * The most important function in this file for mod makers happens to be the
 * updateSettingsFile() function, but it shouldn't be used often anyway.
 *
 * - Updates the Settings.php file with the changes supplied in config_vars.
 *
 * - Expects config_vars to be an associative array, with the keys as the
 *   variable names in Settings.php, and the values the variable values.
 *
 * - Correctly formats the values using smf_var_export().
 *
 * - Restores standard formatting of the file, if $rebuild is true.
 *
 * - Checks for changes to db_last_error and passes those off to a separate
 *   handler.
 *
 * - Creates a backup file and will use it should the writing of the
 *   new settings file fail.
 *
 * - Tries to intelligently trim quotes and remove slashes from string values.
 *   This is done for backwards compatibility purposes (old versions of this
 *   function expected strings to have been manually escaped and quoted). This
 *   behaviour can be controlled by the $keep_quotes parameter.
 *
 * MOD AUTHORS: If you are adding a setting to Settings.php, you should use the
 * integrate_update_settings_file hook to define it in get_settings_defs().
 *
 * @param array $config_vars An array of one or more variables to update.
 * @param bool|null $keep_quotes Whether to strip slashes & trim quotes from string values. Defaults to auto-detection.
 * @param bool $rebuild If true, attempts to rebuild with standard format. Default false.
 * @return bool True on success, false on failure.
 */
function updateSettingsFile($config_vars, $keep_quotes = null, $rebuild = false)
{
	// In this function we intentionally don't declare any global variables.
	// This allows us to work with everything cleanly.

	static $mtime;

	// Should we try to unescape the strings?
	if (empty($keep_quotes))
	{
		foreach ($config_vars as $var => $val)
		{
			if (is_string($val) && ($keep_quotes === false || strpos($val, '\'') === 0 && strrpos($val, '\'') === strlen($val) - 1))
				$config_vars[$var] = trim(stripcslashes($val), '\'');
		}
	}

	// Updating the db_last_error, then don't mess around with Settings.php
	if (isset($config_vars['db_last_error']))
	{
		updateDbLastError($config_vars['db_last_error']);

		if (count($config_vars) === 1 && empty($rebuild))
			return true;

		// Make sure we delete this from Settings.php, if present.
		$config_vars['db_last_error'] = 0;
	}

	// Rebuilding should not be undertaken lightly, so we're picky about the parameter.
	if (!is_bool($rebuild))
		$rebuild = false;

	$mtime = isset($mtime) ? (int) $mtime : (defined('TIME_START') ? TIME_START : $_SERVER['REQUEST_TIME']);

	/*****************
	 * PART 1: Setup *
	 *****************/

	// Typically Settings.php is in $boarddir, but maybe this is a custom setup...
	foreach (get_included_files() as $settingsFile)
		if (basename($settingsFile) === 'Settings.php')
			break;

	// Fallback in case Settings.php isn't loaded (e.g. while installing)
	if (basename($settingsFile) !== 'Settings.php')
		$settingsFile = (!empty($GLOBALS['boarddir']) && @realpath($GLOBALS['boarddir']) ? $GLOBALS['boarddir'] : (!empty($_SERVER['SCRIPT_FILENAME']) ? dirname($_SERVER['SCRIPT_FILENAME']) : dirname(__DIR__))) . '/Settings.php';

	// File not found? Attempt an emergency on-the-fly fix!
	if (!file_exists($settingsFile))
		@touch($settingsFile);

	// When was Settings.php last changed?
	$last_settings_change = filemtime($settingsFile);

	// Get the current values of everything in Settings.php.
	$settings_vars = get_current_settings($mtime, $settingsFile);

	// If Settings.php is empty for some reason, see if we can use the backup.
	if (empty($settings_vars) && file_exists(dirname($settingsFile) . '/Settings_bak.php'))
		$settings_vars = get_current_settings($mtime, dirname($settingsFile) . '/Settings_bak.php');

	// False means there was a problem with the file and we can't safely continue.
	if ($settings_vars === false)
		return false;

	// It works best to set everything afresh.
	$new_settings_vars = array_merge($settings_vars, $config_vars);

	// Are we using UTF-8?
	$utf8 = isset($GLOBALS['context']['utf8']) ? $GLOBALS['context']['utf8'] : (isset($GLOBALS['utf8']) ? $GLOBALS['utf8'] : (isset($settings_vars['db_character_set']) ? $settings_vars['db_character_set'] === 'utf8' : false));

	// Get our definitions for all known Settings.php variables and other content.
	$settings_defs = get_settings_defs();

	// If Settings.php is empty or invalid, try to recover using whatever is in $GLOBALS.
	if ($settings_vars === array())
	{
		foreach ($settings_defs as $var => $setting_def)
			if (isset($GLOBALS[$var]))
				$settings_vars[$var] = $GLOBALS[$var];

		$new_settings_vars = array_merge($settings_vars, $config_vars);
	}

	// During install/upgrade, don't set anything until we're ready for it.
	if (defined('SMF_INSTALLING') && empty($rebuild))
	{
		foreach ($settings_defs as $var => $setting_def)
			if (!in_array($var, array_keys($new_settings_vars)) && !is_int($var))
				unset($settings_defs[$var]);
	}

	/*******************************
	 * PART 2: Build substitutions *
	 *******************************/

	$type_regex = array(
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
		'array' =>  'array\s*(\((?'.'>[^()]|(?1))*\))',
		'object' =>  '\w+::__set_state\(array\s*(\((?'.'>[^()]|(?1))*\))\)',
	);

	/*
	 * The substitutions take place in one of two ways:
	 *
	 *  1: The search_pattern regex finds a string in Settings.php, which is
	 *     temporarily replaced by a placeholder. Once all the placeholders
	 *     have been inserted, each is replaced by the final replacement string
	 *     that we want to use. This is the standard method.
	 *
	 *  2: The search_pattern regex finds a string in Settings.php, which is
	 *     then deleted by replacing it with an empty placeholder. Then after
	 *     all the real placeholders have been dealt with, the replace_pattern
	 *     regex finds where to insert the final replacement string that we
	 *     want to use. This method is for special cases.
	 */
	$prefix = mt_rand() . '-';
	$neg_index = -1;
	$substitutions = array(
		$neg_index-- => array(
			'search_pattern' => '~^\s*<\?(php\b)?\n?~',
			'placeholder' => '',
			'replace_pattern' => '~^~',
			'replacement' => '<' . "?php\n",
		),
		$neg_index-- => array(
			'search_pattern' => '~\S\K\s*(\?' . '>)?\s*$~',
			'placeholder' => "\n" . md5($prefix . '?' . '>'),
			'replacement' => "\n\n?" . '>',
		),
		// Remove the code that redirects to the installer.
		$neg_index-- => array(
			'search_pattern' => '~^if\s*\(file_exists\(dirname\(__FILE__\)\s*\.\s*\'/install\.php\'\)\)\s*(?:({(?'.'>[^{}]|(?1))*})\h*|header(\((?' . '>[^()]|(?2))*\));\n)~m',
			'placeholder' => '',
		),
	);

	if (defined('SMF_INSTALLING'))
		$substitutions[$neg_index--] = array(
			'search_pattern' => '~/\*.*?SMF\s+1\.\d.*?\*/~s',
			'placeholder' => '',
		);

	foreach ($settings_defs as $var => $setting_def)
	{
		$placeholder = md5($prefix . $var);
		$replacement = '';

		if (!empty($setting_def['text']))
		{
			// Special handling for the license block: always at the beginning.
			if (strpos($setting_def['text'], "* @package SMF\n") !== false)
			{
				$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
				$substitutions[$var]['placeholder'] = '';
				$substitutions[-1]['replacement'] .= $setting_def['text'] . "\n";
			}
			// Special handling for the Error-Catching block: always at the end.
			elseif (strpos($setting_def['text'], 'Error-Catching') !== false)
			{
				$errcatch_var = $var;
				$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
				$substitutions[$var]['placeholder'] = '';
				$substitutions[-2]['replacement'] = "\n" . $setting_def['text'] . $substitutions[-2]['replacement'];
			}
			// The text is the whole thing (code blocks, etc.)
			elseif (is_int($var))
			{
				// Remember the path correcting code for later.
				if (strpos($setting_def['text'], '# Make sure the paths are correct') !== false)
					$pathcode_var = $var;

				if (!empty($setting_def['search_pattern']))
					$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
				else
					$substitutions[$var]['search_pattern'] = '~' . preg_quote($setting_def['text'], '~') . '~';

				$substitutions[$var]['placeholder'] = $placeholder;

				$replacement .= $setting_def['text'] . "\n";
			}
			// We only include comments when rebuilding.
			elseif (!empty($rebuild))
				$replacement .= $setting_def['text'] . "\n";
		}

		if (is_string($var))
		{
			// Ensure the value is good.
			if (in_array($var, array_keys($new_settings_vars)))
			{
				// Objects without a __set_state method need a fallback.
				if (is_object($new_settings_vars[$var]) && !method_exists($new_settings_vars[$var], '__set_state'))
				{
					if (method_exists($new_settings_vars[$var], '__toString'))
						$new_settings_vars[$var] = (string) $new_settings_vars[$var];
					else
						$new_settings_vars[$var] = (array) $new_settings_vars[$var];
				}

				// Normalize the type if necessary.
				if (isset($setting_def['type']))
				{
					$expected_types = (array) $setting_def['type'];
					$var_type = gettype($new_settings_vars[$var]);

					// Variable is not of an expected type.
					if (!in_array($var_type, $expected_types))
					{
						// Passed in an unexpected array.
						if ($var_type == 'array')
						{
							$temp = reset($new_settings_vars[$var]);

							// Use the first element if there's only one and it is a scalar.
							if (count($new_settings_vars[$var]) === 1 && is_scalar($temp))
								$new_settings_vars[$var] = $temp;

							// Or keep the old value, if that is good.
							elseif (isset($settings_vars[$var]) && in_array(gettype($settings_vars[$var]), $expected_types))
								$new_settings_vars[$var] = $settings_vars[$var];

							// Fall back to the default
							else
								$new_settings_vars[$var] = $setting_def['default'];
						}

						// Cast it to whatever type was expected.
						// Note: the order of the types in this loop matters.
						foreach (array('boolean', 'integer', 'double', 'string', 'array') as $to_type)
						{
							if (in_array($to_type, $expected_types))
							{
								settype($new_settings_vars[$var], $to_type);
								break;
							}
						}
					}
				}
			}
			// Abort if a required one is undefined (unless we're installing).
			elseif (!empty($setting_def['required']) && !defined('SMF_INSTALLING'))
				return false;

			// Create the search pattern.
			if (!empty($setting_def['search_pattern']))
				$substitutions[$var]['search_pattern'] = $setting_def['search_pattern'];
			else
			{
				$var_pattern = array();

				if (isset($setting_def['type']))
				{
					foreach ((array) $setting_def['type'] as $type)
						$var_pattern[] = $type_regex[$type];
				}

				if (in_array($var, array_keys($config_vars)))
				{
					$var_pattern[] = @$type_regex[gettype($config_vars[$var])];

					if (is_string($config_vars[$var]) && strpos($config_vars[$var], dirname($settingsFile)) === 0)
						$var_pattern[] = '(?:__DIR__|dirname\(__FILE__\)) . \'' . (preg_quote(str_replace(dirname($settingsFile), '', $config_vars[$var]), '~')) . '\'';
				}

				if (in_array($var, array_keys($settings_vars)))
				{
					$var_pattern[] = @$type_regex[gettype($settings_vars[$var])];

					if (is_string($settings_vars[$var]) && strpos($settings_vars[$var], dirname($settingsFile)) === 0)
						$var_pattern[] = '(?:__DIR__|dirname\(__FILE__\)) . \'' . (preg_quote(str_replace(dirname($settingsFile), '', $settings_vars[$var]), '~')) . '\'';
				}

				if (!empty($setting_def['raw_default']) && $setting_def['default'] !== '')
				{
					$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote($setting_def['default'], '~'));

					if (strpos($setting_def['default'], 'dirname(__FILE__)') !== false)
						$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote(str_replace('dirname(__FILE__)', '__DIR__', $setting_def['default']), '~'));

					if (strpos($setting_def['default'], '__DIR__') !== false)
						$var_pattern[] = preg_replace('/\s+/', '\s+', preg_quote(str_replace('__DIR__', 'dirname(__FILE__)', $setting_def['default']), '~'));
				}

				$var_pattern = array_unique($var_pattern);

				$var_pattern = count($var_pattern) > 1 ? '(?:' . (implode('|', $var_pattern)) . ')' : $var_pattern[0];

				$substitutions[$var]['search_pattern'] = '~(?<=^|\s)\h*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';~' . (!empty($utf8) ? 'u' : '');
			}

			// Next create the placeholder or replace_pattern.
			if (!empty($setting_def['replace_pattern']))
				$substitutions[$var]['replace_pattern'] = $setting_def['replace_pattern'];
			else
				$substitutions[$var]['placeholder'] = $placeholder;

			// Now create the replacement.
			// A setting to delete.
			if (!empty($setting_def['auto_delete']) && empty($new_settings_vars[$var]))
			{
				if ($setting_def['auto_delete'] === 2 && empty($rebuild) && in_array($var, array_keys($new_settings_vars)))
				{
					$replacement .= '$' . $var . ' = ' . ($new_settings_vars[$var] === $setting_def['default'] && !empty($setting_def['raw_default']) ? sprintf($new_settings_vars[$var]) : smf_var_export($new_settings_vars[$var], true)) . ";";
				}
				else
				{
					$replacement = '';
					$substitutions[$var]['placeholder'] = '';

					// This is just for cosmetic purposes. Removes the blank line.
					$substitutions[$var]['search_pattern'] = str_replace('(?<=^|\s)', '\n?', $substitutions[$var]['search_pattern']);
				}
			}
			// Add this setting's value.
			elseif (in_array($var, array_keys($new_settings_vars)))
			{
				$replacement .= '$' . $var . ' = ' . ($new_settings_vars[$var] === $setting_def['default'] && !empty($setting_def['raw_default']) ? sprintf($new_settings_vars[$var]) : smf_var_export($new_settings_vars[$var], true)) . ";";
			}
			// Fall back to the default value.
			elseif (isset($setting_def['default']))
			{
				$replacement .= '$' . $var . ' = ' . (!empty($setting_def['raw_default']) ? sprintf($setting_def['default']) : smf_var_export($setting_def['default'], true)) . ';';
			}
			// This shouldn't happen, but we've got nothing.
			else
				$replacement .= '$' . $var . ' = null;';
		}

		$substitutions[$var]['replacement'] = $replacement;

		// We're done with this one.
		unset($new_settings_vars[$var]);
	}

	// Any leftovers to deal with?
	foreach ($new_settings_vars as $var => $val)
	{
		$var_pattern = array();

		if (in_array($var, array_keys($config_vars)))
			$var_pattern[] = $type_regex[gettype($config_vars[$var])];

		if (in_array($var, array_keys($settings_vars)))
			$var_pattern[] = $type_regex[gettype($settings_vars[$var])];

		$var_pattern = array_unique($var_pattern);

		$var_pattern = count($var_pattern) > 1 ? '(?:' . (implode('|', $var_pattern)) . ')' : $var_pattern[0];

		$placeholder = md5($prefix . $var);

		$substitutions[$var]['search_pattern'] = '~(?<=^|\s)\h*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';~' . (!empty($utf8) ? 'u' : '');
		$substitutions[$var]['placeholder'] = $placeholder;
		$substitutions[$var]['replacement'] = '$' . $var . ' = ' . smf_var_export($val, true) . ";";
	}

	// During an upgrade, some of the path variables may not have been declared yet.
	if (defined('SMF_INSTALLING') && empty($rebuild))
	{
		preg_match_all('~^\h*\$(\w+)\s*=\s*~m', $substitutions[$pathcode_var]['replacement'], $matches);
		$missing_pathvars = array_diff($matches[1], array_keys($substitutions));

		if (!empty($missing_pathvars))
		{
			foreach ($missing_pathvars as $var)
			{
				$substitutions[$pathcode_var]['replacement'] = preg_replace('~\nif[^\n]+\$' . $var . '[^\n]+\n\h*\$' . $var . ' = [^\n]+~', '', $substitutions[$pathcode_var]['replacement']);
			}
		}
	}

	// It's important to do the numbered ones before the named ones, or messes happen.
	uksort(
		$substitutions,
		function($a, $b) {
			if (is_int($a) && is_int($b))
				return $a > $b ? 1 : ($a < $b ? -1 : 0);
			elseif (is_int($a))
				return -1;
			elseif (is_int($b))
				return 1;
			else
				return strcasecmp($b, $a);
		}
	);

	/******************************
	 * PART 3: Content processing *
	 ******************************/

	/* 3.a: Get the content of Settings.php and make sure it is good. */

	// Retrieve the contents of Settings.php and normalize the line endings.
	$settingsText = trim(strtr(file_get_contents($settingsFile), array("\r\n" => "\n", "\r" => "\n")));

	// If Settings.php is empty or corrupt for some reason, see if we can recover.
	if ($settingsText == '' || substr($settingsText, 0, 5) !== '<' . '?php')
	{
		// Try restoring from the backup.
		if (file_exists(dirname($settingsFile) . '/Settings_bak.php'))
			$settingsText = strtr(file_get_contents(dirname($settingsFile) . '/Settings_bak.php'), array("\r\n" => "\n", "\r" => "\n"));

		// Backup is bad too? Our only option is to create one from scratch.
		if ($settingsText == '' || substr($settingsText, 0, 5) !== '<' . '?php' || substr($settingsText, -2) !== '?' . '>')
		{
			$settingsText = '<' . "?php\n";
			foreach ($settings_defs as $var => $setting_def)
			{
				if (is_string($var) && !empty($setting_def['text']) && strpos($substitutions[$var]['replacement'], $setting_def['text']) === false)
					$substitutions[$var]['replacement'] = $setting_def['text'] . "\n" . $substitutions[$var]['replacement'];

				$settingsText .= $substitutions[$var]['replacement'] . "\n";
			}
			$settingsText .= "\n\n?" . '>';
			$rebuild = true;
		}
	}

	// Settings.php is unlikely to contain any heredocs, but just in case...
	if (preg_match_all('/<<<([\'"]?)(\w+)\1\R(.*?)\R\h*\2;$/ms', $settingsText, $matches))
	{
		foreach ($matches[0] as $mkey => $heredoc)
		{
			if (!empty($matches[1][$mkey]) && $matches[1][$mkey] === '\'')
				$heredoc_replacements[$heredoc] = var_export($matches[3][$mkey], true) . ';';
			else
				$heredoc_replacements[$heredoc] = '"' . strtr(substr(var_export($matches[3][$mkey], true), 1, -1), array("\\'" => "'", '"' => '\"')) . '";';
		}

		$settingsText = strtr($settingsText, $heredoc_replacements);
	}

	/* 3.b: Loop through all our substitutions to insert placeholders, etc. */

	$last_var = null;
	$bare_settingsText = $settingsText;
	$force_before_pathcode = array();
	foreach ($substitutions as $var => $substitution)
	{
		$placeholders[$var] = $substitution['placeholder'];

		if (!empty($substitution['placeholder']))
		{
			$simple_replacements[$substitution['placeholder']] = $substitution['replacement'];
		}
		elseif (!empty($substitution['replace_pattern']))
		{
			$replace_patterns[$var] = $substitution['replace_pattern'];
			$replace_strings[$var] = $substitution['replacement'];
		}

		if (strpos($substitutions[$pathcode_var]['replacement'], '$' . $var . ' = ') !== false)
			$force_before_pathcode[] = $var;

		// Look before you leap.
		preg_match_all($substitution['search_pattern'], $bare_settingsText, $matches);

		if ((is_string($var) || $var === $pathcode_var) && count($matches[0]) !== 1 && $substitution['replacement'] !== '')
		{
			// More than one instance of the variable = not good.
			if (count($matches[0]) > 1)
			{
				if (is_string($var))
				{
					// Maybe we can try something more interesting?
					$sp = substr($substitution['search_pattern'], 1);

					if (strpos($sp, '(?<=^|\s)') === 0)
						$sp = substr($sp, 9);

					if (strpos($sp, '^') === 0 || strpos($sp, '(?<') === 0)
						return false;

					// See if we can exclude `if` blocks, etc., to narrow down the matches.
					// @todo Multiple layers of nested brackets might confuse this.
					$sp = '~(?:^|//[^\n]+c\n|\*/|[;}]|' . implode('|', array_filter($placeholders)) . ')\s*' . (strpos($sp, '\K') === false ? '\K' : '') . $sp;

					preg_match_all($sp, $settingsText, $matches);
				}
				else
					$sp = $substitution['search_pattern'];

				// Found at least some that are simple assignment statements.
				if (count($matches[0]) > 0)
				{
					// Remove any duplicates.
					if (count($matches[0]) > 1)
						$settingsText = preg_replace($sp, '', $settingsText, count($matches[0]) - 1);

					// Insert placeholder for the last one.
					$settingsText = preg_replace($sp, $substitution['placeholder'], $settingsText, 1);
				}

				// All instances are inside more complex code structures.
				else
				{
					// Only safe option at this point is to skip it.
					unset($substitutions[$var], $new_settings_vars[$var], $settings_defs[$var], $simple_replacements[$substitution['placeholder']], $replace_patterns[$var], $replace_strings[$var]);

					continue;
				}
			}
			// No matches found.
			elseif (count($matches[0]) === 0)
			{
				$found = false;
				$in_c = in_array($var, array_keys($config_vars));
				$in_s = in_array($var, array_keys($settings_vars));

				// Is it in there at all?
				if (!preg_match('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*~', $bare_settingsText))
				{
					// It's defined by Settings.php, but not by code in the file.
					// Probably done via an include or something. Skip it.
					if ($in_s)
						unset($substitutions[$var], $settings_defs[$var]);

					// Admin is explicitly trying to set this one, so we'll handle
					// it as if it were a new custom setting being added.
					elseif ($in_c)
						$new_settings_vars[$var] = $config_vars[$var];

					continue;
				}

				// It's in there somewhere, so check if the value changed type.
				foreach (array('scalar', 'object', 'array') as $type)
				{
					// Try all the other scalar types first.
					if ($type == 'scalar')
						$sp = '(?:' . (implode('|', array_diff_key($type_regex, array($in_c ? gettype($config_vars[$var]) : ($in_s ? gettype($settings_vars[$var]) : PHP_INT_MAX) => '', 'array' => '', 'object' => '')))) . ')';

					// Maybe it's an object? (Probably not, but we should check.)
					elseif ($type == 'object')
					{
						if (strpos($settingsText, '__set_state') === false)
							continue;

						$sp = $type_regex['object'];
					}

					// Maybe it's an array?
					else
						$sp = $type_regex['array'];

					if (preg_match('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*' . $sp . '~', $bare_settingsText, $derp))
					{
						$settingsText = preg_replace('~(^|\s)\$' . preg_quote($var, '~') . '\s*=\s*' . $sp . '~', $substitution['placeholder'], $settingsText);
						$found = true;
						break;
					}
				}

				// Something weird is going on. Better just leave it alone.
				if (!$found)
				{
					// $var? What $var? Never heard of it.
					unset($substitutions[$var], $new_settings_vars[$var], $settings_defs[$var], $simple_replacements[$substitution['placeholder']], $replace_patterns[$var], $replace_strings[$var]);
					continue;
				}
			}
		}
		// Good to go, so insert our placeholder.
		else
			$settingsText = preg_replace($substitution['search_pattern'], $substitution['placeholder'], $settingsText);

		// Once the code blocks are done, we want to compare to a version without comments.
		if (is_int($last_var) && is_string($var))
			$bare_settingsText = strip_php_comments($settingsText);

		$last_var = $var;
	}

	// Rebuilding requires more work.
	if (!empty($rebuild))
	{
		// Strip out the leading and trailing placeholders to prevent duplication.
		$settingsText = str_replace(array($substitutions[-1]['placeholder'], $substitutions[-2]['placeholder']), '', $settingsText);

		// Strip out all our standard comments.
		foreach ($settings_defs as $var => $setting_def)
		{
			if (isset($setting_def['text']))
				$settingsText = strtr($settingsText, array($setting_def['text'] . "\n" => '', $setting_def['text'] => '',));
		}

		// We need to refresh $bare_settingsText at this point.
		$bare_settingsText = strip_php_comments($settingsText);

		// Fix up whitespace to make comparison easier.
		foreach ($placeholders as $placeholder)
		{
			$bare_settingsText = str_replace(array($placeholder . "\n\n", $placeholder), $placeholder . "\n", $bare_settingsText);
		}
		$bare_settingsText = preg_replace('/\h+$/m', '', rtrim($bare_settingsText));

		/*
		 * Divide the existing content into sections.
		 * The idea here is to make sure we don't mess with the relative position
		 * of any code blocks in the file, since that could break things. Within
		 * each section, however, we'll reorganize the content to match the
		 * default layout as closely as we can.
		 */
		$sections = array(array());
		$section_num = 0;
		$trimmed_placeholders = array_filter(array_map('trim', $placeholders));
		$newsection_placeholders = array();
		$all_custom_content = '';
		foreach ($substitutions as $var => $substitution)
		{
			if (is_int($var) && ($var === -2 || $var > 0) && isset($trimmed_placeholders[$var]) && strpos($bare_settingsText, $trimmed_placeholders[$var]) !== false)
				$newsection_placeholders[$var] = $trimmed_placeholders[$var];
		}
		foreach (preg_split('~(?<=' . implode('|', $trimmed_placeholders) . ')|(?=' . implode('|', $trimmed_placeholders) . ')~', $bare_settingsText) as $part)
		{
			$part = trim($part);

			if (empty($part))
				continue;

			// Build a list of placeholders for this section.
			if (in_array($part, $trimmed_placeholders) && !in_array($part, $newsection_placeholders))
			{
				$sections[$section_num][] = $part;
			}
			// Custom content and newsection_placeholders get their own sections.
			else
			{
				if (!empty($sections[$section_num]))
					++$section_num;

				$sections[$section_num][] = $part;

				++$section_num;

				if (!in_array($part, $trimmed_placeholders))
					$all_custom_content .= "\n" . $part;
			}
		}

		// And now, rebuild the content!
		$new_settingsText = '';
		$done_defs = array();
		$sectionkeys = array_keys($sections);
		foreach ($sections as $sectionkey => $section)
		{
			// Custom content needs to be preserved.
			if (count($section) === 1 && !in_array($section[0], $trimmed_placeholders))
			{
				$prev_section_end = $sectionkey < 1 ? 0 : strpos($settingsText, end($sections[$sectionkey - 1])) + strlen(end($sections[$sectionkey - 1]));
				$next_section_start = $sectionkey == end($sectionkeys) ? strlen($settingsText) : strpos($settingsText, $sections[$sectionkey + 1][0]);

				$new_settingsText .= "\n" . substr($settingsText, $prev_section_end, $next_section_start - $prev_section_end) . "\n";
			}
			// Put the placeholders in this section into canonical order.
			else
			{
				$section_parts = array_flip($section);
				$pathcode_reached = false;
				foreach ($settings_defs as $var => $setting_def)
				{
					if ($var === $pathcode_var)
						$pathcode_reached = true;

					// Already did this setting, so move on to the next.
					if (in_array($var, $done_defs))
						continue;

					// Stop when we hit a setting definition that will start a later section.
					if (isset($newsection_placeholders[$var]) && count($section) !== 1)
						break;

					// Stop when everything in this section is done, unless it's the last.
					// This helps maintain the relative position of any custom content.
					if (empty($section_parts) && $sectionkey < (count($sections) - 1))
						break;

					$p = trim($substitutions[$var]['placeholder']);

					// Can't do anything with an empty placeholder.
					if ($p === '')
						continue;

					// Does this need to be inserted before the path correction code?
					if (strpos($new_settingsText, trim($substitutions[$pathcode_var]['placeholder'])) !== false && in_array($var, $force_before_pathcode))
					{
						$new_settingsText = strtr($new_settingsText, array($substitutions[$pathcode_var]['placeholder'] => $p . "\n" . $substitutions[$pathcode_var]['placeholder']));

						$bare_settingsText .= "\n" . $substitutions[$var]['placeholder'];
						$done_defs[] = $var;
						unset($section_parts[trim($substitutions[$var]['placeholder'])]);
					}

					// If it's in this section, add it to the new text now.
					elseif (in_array($p, $section))
					{
						$new_settingsText .= "\n" . $substitutions[$var]['placeholder'];
						$done_defs[] = $var;
						unset($section_parts[trim($substitutions[$var]['placeholder'])]);
					}

					// Perhaps it is safe to reposition it anyway.
					elseif (is_string($var) && strpos($new_settingsText, $p) === false && strpos($all_custom_content, '$' . $var) === false)
					{
						$new_settingsText .= "\n" . $substitutions[$var]['placeholder'];
						$done_defs[] = $var;
						unset($section_parts[trim($substitutions[$var]['placeholder'])]);
					}

					// If this setting is missing entirely, fix it.
					elseif (strpos($bare_settingsText, $p) === false)
					{
						// Special case if the path code is missing. Put it near the end,
						// and also anything else that is missing that normally follows it.
						if (!isset($newsection_placeholders[$pathcode_var]) && $pathcode_reached === true && $sectionkey < (count($sections) - 1))
							break;

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
		foreach (array(-1, -2) as $var)
		{
			if (!empty($substitutions[$var]['placeholder']) && strpos($settingsText, $substitutions[$var]['placeholder']) === false);
			{
				$settingsText = ($var == -1 ? $substitutions[$var]['placeholder'] : '') . $settingsText . ($var == -2 ? $substitutions[$var]['placeholder'] : '');
			}
		}
	}
	// Even if not rebuilding, there are a few variables that may need to be moved around.
	else
	{
		$pathcode_pos = strpos($settingsText, $substitutions[$pathcode_var]['placeholder']);

		if ($pathcode_pos !== false)
		{
			foreach ($force_before_pathcode as $var)
			{
				if (!empty($substitutions[$var]['placeholder']) && strpos($settingsText, $substitutions[$var]['placeholder']) > $pathcode_pos)
				{
					$settingsText = strtr($settingsText, array(
						$substitutions[$var]['placeholder'] => '',
						$substitutions[$pathcode_var]['placeholder'] => $substitutions[$var]['placeholder'] . "\n" . $substitutions[$pathcode_var]['placeholder'],
					));
				}
			}
		}
	}

	/* 3.c: Replace the placeholders with the final values */

	// Where possible, perform simple substitutions.
	$settingsText = strtr($settingsText, $simple_replacements);

	// Deal with any complicated ones.
	if (!empty($replace_patterns))
		$settingsText = preg_replace($replace_patterns, $replace_strings, $settingsText);

	// Make absolutely sure that the path correction code is included.
	if (strpos($settingsText, $substitutions[$pathcode_var]['replacement']) === false)
		$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', "\n" . $substitutions[$pathcode_var]['replacement'] . "\n", $settingsText);

	// If we did not rebuild, do just enough to make sure the thing is viable.
	if (empty($rebuild))
	{
		// We need to refresh $bare_settingsText again, and remove the code blocks from it.
		$bare_settingsText = $settingsText;
		foreach ($substitutions as $var => $substitution)
		{
			if (!is_int($var))
				break;

			if (isset($substitution['replacement']))
				$bare_settingsText = str_replace($substitution['replacement'], '', $bare_settingsText);
		}
		$bare_settingsText = strip_php_comments($bare_settingsText);

		// Now insert any defined settings that are missing.
		$pathcode_reached = false;
		foreach ($settings_defs as $var => $setting_def)
		{
			if ($var === $pathcode_var)
				$pathcode_reached = true;

			if (is_int($var))
				continue;

			// Do nothing if it is already in there.
			if (preg_match($substitutions[$var]['search_pattern'], $bare_settingsText))
				continue;

			// Insert it either before or after the path correction code, whichever is appropriate.
			if (!$pathcode_reached || in_array($var, $force_before_pathcode))
			{
				$settingsText = preg_replace($substitutions[$pathcode_var]['search_pattern'], $substitutions[$var]['replacement'] . "\n\n$0", $settingsText);
			}
			else
			{
				$settingsText = preg_replace($substitutions[$pathcode_var]['search_pattern'], "$0\n\n" . $substitutions[$var]['replacement'], $settingsText);
			}
		}
	}

	// If we have any brand new settings to add, do so.
	foreach ($new_settings_vars as $var => $val)
	{
		if (isset($substitutions[$var]) && !preg_match($substitutions[$var]['search_pattern'], $settingsText))
		{
			if (!isset($settings_defs[$var]) && strpos($settingsText, '# Custom Settings #') === false)
				$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', "\n\n######### Custom Settings #########\n", $settingsText);

			$settingsText = preg_replace('~(?=\n#+ Error.Catching #+)~', $substitutions[$var]['replacement'] . "\n", $settingsText);
		}
	}

	// This is just cosmetic. Get rid of extra lines of whitespace.
	$settingsText = preg_replace('~\n\s*\n~', "\n\n", $settingsText);

	/**************************************
	 * PART 4: Check syntax before saving *
	 **************************************/

	$temp_sfile = tempnam(sm_temp_dir(), md5($prefix . 'Settings.php'));
	file_put_contents($temp_sfile, $settingsText);

	$result = get_current_settings(filemtime($temp_sfile), $temp_sfile);

	unlink($temp_sfile);

	// If the syntax is borked, try rebuilding to see if that fixes it.
	if ($result === false)
		return empty($rebuild) ? updateSettingsFile($config_vars, $keep_quotes, true) : false;

	/******************************************
	 * PART 5: Write updated settings to file *
	 ******************************************/

	$success = safe_file_write($settingsFile, $settingsText, dirname($settingsFile) . '/Settings_bak.php', $last_settings_change);

	// Remember this in case updateSettingsFile is called twice.
	$mtime = filemtime($settingsFile);

	return $success;
}

/**
 * Retrieves a copy of the current values of all settings defined in Settings.php.
 *
 * Importantly, it does this without affecting our actual global variables at all,
 * and it performs safety checks before acting. The result is an array of the
 * values as recorded in the settings file.
 *
 * @param int $mtime Timestamp of last known good configuration. Defaults to time SMF started.
 * @param string $settingsFile The settings file. Defaults to SMF's standard Settings.php.
 * @return array An array of name/value pairs for all the settings in the file.
 */
function get_current_settings($mtime = null, $settingsFile = null)
{
	$mtime = is_null($mtime) ? (defined('TIME_START') ? TIME_START : $_SERVER['REQUEST_TIME']) : (int) $mtime;

	if (!is_file($settingsFile))
	{
		foreach (get_included_files() as $settingsFile)
			if (basename($settingsFile) === 'Settings.php')
				break;

		if (basename($settingsFile) !== 'Settings.php')
			return false;
	}

	// If the file has been changed since the last known good configuration, bail out.
	clearstatcache();
	if (filemtime($settingsFile) > $mtime)
		return false;

	// Strip out opening and closing PHP tags.
	$settingsText = trim(file_get_contents($settingsFile));
	if (substr($settingsText, 0, 5) == '<' . '?php')
		$settingsText = substr($settingsText, 5);
	if (substr($settingsText, -2) == '?' . '>')
		$settingsText = substr($settingsText, 0, -2);

	// Since we're using eval, we need to manually replace these with strings.
	$settingsText = strtr($settingsText, array(
		'__FILE__' => var_export($settingsFile, true),
		'__DIR__' => var_export(dirname($settingsFile), true),
	));

	// Prevents warnings about constants that are already defined.
	$settingsText = preg_replace_callback(
		'~\bdefine\s*\(\s*(["\'])(\w+)\1~',
		function ($matches)
		{
			return 'define(\'' . md5(mt_rand()) . '\'';
		},
		$settingsText
	);

	// Handle eval errors gracefully in both PHP 5 and PHP 7
	try
	{
		if($settingsText !== '' && @eval($settingsText) === false)
			throw new ErrorException('eval error');

		unset($mtime, $settingsFile, $settingsText);
		$defined_vars = get_defined_vars();
	}
	catch (Throwable $e) {}
	catch (ErrorException $e) {}
	if (isset($e))
		return false;

	return $defined_vars;
}

/**
 * Writes data to a file, optionally making a backup, while avoiding race conditions.
 *
 * @param string $file The filepath of the file where the data should be written.
 * @param string $data The data to be written to $file.
 * @param string $backup_file The filepath where the backup should be saved. Default null.
 * @param int $mtime If modification time of $file is more recent than this Unix timestamp, the write operation will abort. Defaults to time that the script started execution.
 * @param bool $append If true, the data will be appended instead of overwriting the existing content of the file. Default false.
 * @return bool Whether the write operation succeeded or not.
 */
function safe_file_write($file, $data, $backup_file = null, $mtime = null, $append = false)
{
	// Sanity checks.
	if (!file_exists($file) && !is_dir(dirname($file)))
		return false;

	if (!is_int($mtime))
		$mtime = $_SERVER['REQUEST_TIME'];

	$temp_dir = sm_temp_dir();

	// Our temp files.
	$temp_sfile = tempnam($temp_dir, pathinfo($file, PATHINFO_FILENAME) . '.');

	if (!empty($backup_file))
		$temp_bfile = tempnam($temp_dir, pathinfo($backup_file, PATHINFO_FILENAME) . '.');

	// We need write permissions.
	$failed = false;
	foreach (array($file, $backup_file) as $sf)
	{
		if (empty($sf))
			continue;

		if (!file_exists($sf))
			touch($sf);
		elseif (!is_file($sf))
			$failed = true;

		if (!$failed)
			$failed = !smf_chmod($sf);
	}

	// Now let's see if writing to a temp file succeeds.
	if (!$failed && file_put_contents($temp_sfile, $data, LOCK_EX) !== strlen($data))
		$failed = true;

	// Tests passed, so it's time to do the job.
	if (!$failed)
	{
		// Back up the backup, just in case.
		if (file_exists($backup_file))
			$temp_bfile_saved = @copy($backup_file, $temp_bfile);

		// Make sure no one changed the file while we weren't looking.
		clearstatcache();
		if (filemtime($file) <= $mtime)
		{
			// Attempt to open the file.
			$sfhandle = @fopen($file, 'c');

			// Let's do this thing!
			if ($sfhandle !== false)
			{
				// Immediately get a lock.
				flock($sfhandle, LOCK_EX);

				// Make sure the backup works before we do anything more.
				$temp_sfile_saved = @copy($file, $temp_sfile);

				// Now write our data to the file.
				if ($temp_sfile_saved)
				{
					if (empty($append))
					{
						ftruncate($sfhandle, 0);
						rewind($sfhandle);
					}

					$failed = fwrite($sfhandle, $data) !== strlen($data);
				}
				else
					$failed = true;

				// If writing failed, put everything back the way it was.
				if ($failed)
				{
					if (!empty($temp_sfile_saved))
						@rename($temp_sfile, $file);

					if (!empty($temp_bfile_saved))
						@rename($temp_bfile, $backup_file);
				}
				// It worked, so make our temp backup the new permanent backup.
				elseif (!empty($backup_file))
					@rename($temp_sfile, $backup_file);

				// And we're done.
				flock($sfhandle, LOCK_UN);
				fclose($sfhandle);
			}
		}
	}

	// We're done with these.
	@unlink($temp_sfile);
	@unlink($temp_bfile);

	if ($failed)
		return false;

	// Even though on normal installations the filemtime should invalidate any cached version
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($file, true);

	return true;
}

/**
 * A wrapper around var_export whose output matches SMF coding conventions.
 *
 * @todo Add special handling for objects?
 *
 * @param mixed $var The variable to export
 * @return mixed A PHP-parseable representation of the variable's value
 */
function smf_var_export($var)
{
	/*
	 * Old versions of updateSettingsFile couldn't handle multi-line values.
	 * Even though technically we can now, we'll keep arrays on one line for
	 * the sake of backwards compatibility.
	 */
	if (is_array($var))
	{
		$return = array();

		foreach ($var as $key => $value)
			$return[] = var_export($key, true) . ' => ' . smf_var_export($value);

		return 'array(' . implode(', ', $return) . ')';
	}

	// For the same reason, replace literal returns and newlines with "\r" and "\n"
	elseif (is_string($var) && (strpos($var, "\n") !== false || strpos($var, "\r") !== false))
	{
		return strtr(
			preg_replace_callback(
				'/[\r\n]+/',
				function($m)
				{
					return '\' . "' . strtr($m[0], array("\r" => '\r', "\n" => '\n')) . '" . \'';
				},
				var_export($var, true)
			),
			array("'' . " => '', " . ''" => '')
		);
	}

	// We typically use lowercase true/false/null.
	elseif (in_array(gettype($var), array('boolean', 'NULL')))
		return strtolower(var_export($var, true));

	// Nothing special.
	else
		return var_export($var, true);
};

/**
 * Deletes all PHP comments from a string.
 *
 * @param string $code_str A string containing PHP code.
 * @return string A string of PHP code with no comments in it.
 */
function strip_php_comments($code_str)
{
	// This is the faster, better way.
	if (is_callable('token_get_all'))
	{
		$tokens = token_get_all($code_str);

		$parts = array();
		foreach ($tokens as $token)
		{
			if (is_string($token))
				$parts[] = $token;
			else
			{
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
						if (strpos($text, '/*') === 0)
						{
							if (substr($parts[$prev_part], -2) === "\r\n")
								$parts[$prev_part] = substr($parts[$prev_part], 0, -2);
							elseif (in_array(substr($parts[$prev_part], -1), array("\r", "\n")))
								$parts[$prev_part] = substr($parts[$prev_part], 0, -1);
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
	if (preg_match_all('/<<<([\'"]?)(\w+)\1?\R(.*?)\R\h*\2;$/ms', $code_str, $matches))
	{
		$heredoc_replacements = array();

		foreach ($matches[0] as $mkey => $heredoc)
			$heredoc_replacements[$heredoc] = var_export(md5($matches[3][$mkey]), true) . ';';

		$code_str = strtr($code_str, $heredoc_replacements);
	}

	// Split before everything that could possibly delimit a comment or a string.
	$parts = preg_split('~(?=#+|/(?=/|\*)|\*/|\R|(?<!\\\)[\'"])~m', $code_str);

	$in_string = 0;
	$in_comment = 0;
	foreach ($parts as $partkey => $part)
	{
		$one_char = substr($part, 0, 1);
		$two_char = substr($part, 0, 2);
		$to_remove = 0;

		/*
		 * Meaning of $in_string values:
		 *	0: not in a string
		 *	1: in a single quote string
		 *	2: in a double quote string
		 */
		if ($one_char == "'")
		{
			if (!empty($in_comment))
				$in_string = 0;
			elseif (in_array($in_string, array(0, 1)))
				$in_string = ($in_string ^ 1);
		}
		elseif ($one_char == '"')
		{
			if (!empty($in_comment))
				$in_string = 0;
			elseif (in_array($in_string, array(0, 2)))
				$in_string = ($in_string ^ 2);
		}

		/*
		 * Meaning of $in_comment values:
		 * 	0: not in a comment
		 *	1: in a single line comment
		 *	2: in a multi-line comment
		 */
		elseif ($one_char == '#' || $two_char == '//')
		{
			$in_comment = !empty($in_string) ? 0 : (empty($in_comment) ? 1 : $in_comment);

			if ($in_comment == 1)
			{
				$parts[$partkey - 1] = rtrim($parts[$partkey - 1], "\t ");

				if (substr($parts[$partkey - 1], -2) === "\r\n")
					$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -2);
				elseif (in_array(substr($parts[$partkey - 1], -1), array("\r", "\n")))
					$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -1);
			}
		}
		elseif ($two_char === "\r\n" || $one_char === "\r" || $one_char === "\n")
		{
			if ($in_comment == 1)
				$in_comment = 0;
		}
		elseif ($two_char == '/*')
		{
			$in_comment = !empty($in_string) ? 0 : (empty($in_comment) ? 2 : $in_comment);

			if ($in_comment == 2)
			{
				$parts[$partkey - 1] = rtrim($parts[$partkey - 1], "\t ");

				if (substr($parts[$partkey - 1], -2) === "\r\n")
					$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -2);
				elseif (in_array(substr($parts[$partkey - 1], -1), array("\r", "\n")))
					$parts[$partkey - 1] = substr($parts[$partkey - 1], 0, -1);
			}
		}
		elseif ($two_char == '*/')
		{
			if ($in_comment == 2)
			{
				$in_comment = 0;

				// Delete the comment closing.
				$to_remove = 2;
			}
		}

		if (empty($in_comment))
			$parts[$partkey] = strlen($part) > $to_remove ? substr($part, $to_remove) : '';
		else
			$parts[$partkey] = '';
	}

	$code_str = implode('', $parts);

	if (!empty($heredoc_replacements))
		$code_str = strtr($code_str, array_flip($heredoc_replacements));

	return $code_str;
}

/**
 * Saves the time of the last db error for the error log
 * - Done separately from updateSettingsFile to avoid race conditions
 *   which can occur during a db error
 * - If it fails Settings.php will assume 0
 *
 * @param int $time The timestamp of the last DB error
 * @param bool True If we should update the current db_last_error context as well.  This may be useful in cases where the current context needs to know a error was logged since the last check.
 * @return bool True If we could succesfully put the file or not.
 */
function updateDbLastError($time, $update = true)
{
	global $boarddir, $cachedir, $db_last_error;

	// Write out the db_last_error file with the error timestamp
	if (!empty($cachedir) && is_writable($cachedir))
		$errorfile = $cachedir . '/db_last_error.php';

	elseif (file_exists(dirname(__DIR__) . '/cache'))
		$errorfile = dirname(__DIR__) . '/cache/db_last_error.php';

	else
		$errorfile = dirname(__DIR__) . '/db_last_error.php';

	$result = file_put_contents($errorfile, '<' . '?' . "php\n" . '$db_last_error = ' . $time . ';' . "\n" . '?' . '>', LOCK_EX);

	@touch($boarddir . '/' . 'Settings.php');

	// Unless requested, we should update $db_last_error as well.
	if ($update)
		$db_last_error = $time;

	// We  do a loose match here rather than strict (!==) as 0 is also false.
	return $result != false;
}

/**
 * Saves the admin's current preferences to the database.
 */
function updateAdminPreferences()
{
	global $options, $context, $smcFunc, $settings, $user_info;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = $smcFunc['json_encode']($context['admin_preferences']);

	// Just check we haven't ended up with something theme exclusive somehow.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND variable = {string:admin_preferences}',
		array(
			'default_theme' => 1,
			'admin_preferences' => 'admin_preferences',
		)
	);

	// Update the themes table.
	$smcFunc['db_insert']('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($user_info['id'], 1, 'admin_preferences', $options['admin_preferences']),
		array('id_member', 'id_theme', 'variable')
	);

	// Make sure we invalidate any cache.
	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 0);
}

/**
 * Send all the administrators a lovely email.
 * - loads all users who are admins or have the admin forum permission.
 * - uses the email template and replacements passed in the parameters.
 * - sends them an email.
 *
 * @param string $template Which email template to use
 * @param array $replacements An array of items to replace the variables in the template
 * @param array $additional_recipients An array of arrays of info for additional recipients. Should have 'id', 'email' and 'name' for each.
 */
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $smcFunc, $sourcedir, $language, $modSettings;

	// We certainly want this.
	require_once($sourcedir . '/Subs-Post.php');

	// Load all members which are effectively admins.
	require_once($sourcedir . '/Subs-Members.php');
	$members = membersAllowedTo('admin_forum');

	// Load their alert preferences
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($members, 'announcements', true);

	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE id_member IN({array_int:members})',
		array(
			'members' => $members,
		)
	);
	$emails_sent = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($prefs[$row['id_member']]['announcements']))
			continue;

		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['member_name'];
		$replacements['USERNAME'] = $row['real_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Then send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);

		// Track who we emailed so we don't do it twice.
		$emails_sent[] = $row['email_address'];
	}
	$smcFunc['db_free_result']($request);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
		foreach ($additional_recipients as $recipient)
		{
			if (in_array($recipient['email'], $emails_sent))
				continue;

			$replacements['IDMEMBER'] = $recipient['id'];
			$replacements['REALNAME'] = $recipient['name'];
			$replacements['USERNAME'] = $recipient['name'];

			// Load the template again.
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty($modSettings['userLanguage']) ? $language : $recipient['lang']);

			// Send off the email.
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);
		}
}

/**
 * Locates the most appropriate temp directory.
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
 * @return string
*/
function sm_temp_dir()
{
	global $cachedir;

	static $temp_dir = null;

	// Already did this.
	if (!empty($temp_dir))
		return $temp_dir;

	// Temp Directory options order.
	$temp_dir_options = array(
		0 => 'sys_get_temp_dir',
		1 => 'upload_tmp_dir',
		2 => 'session.save_path',
		3 => 'cachedir'
	);

	// Determine if we should detect a restriction and what restrictions that may be.
	$open_base_dir = ini_get('open_basedir');
	$restriction = !empty($open_base_dir) ? explode(':', $open_base_dir) : false;

	// Prevent any errors as we search.
	$old_error_reporting = error_reporting(0);

	// Search for a working temp directory.
	foreach ($temp_dir_options as $id_temp => $temp_option)
	{
		switch ($temp_option) {
			case 'cachedir':
				$possible_temp = rtrim($cachedir, '/');
				break;

			case 'session.save_path':
				$possible_temp = rtrim(ini_get('session.save_path'), '/');
				break;

			case 'upload_tmp_dir':
				$possible_temp = rtrim(ini_get('upload_tmp_dir'), '/');
				break;

			default:
				$possible_temp = sys_get_temp_dir();
				break;
		}

		// Check if we have a restriction preventing this from working.
		if ($restriction)
		{
			foreach ($restriction as $dir)
			{
				if (strpos($possible_temp, $dir) !== false && is_writable($possible_temp))
				{
					$temp_dir = $possible_temp;
					break;
				}
			}
		}
		// No restrictions, but need to check for writable status.
		elseif (is_writable($possible_temp))
		{
			$temp_dir = $possible_temp;
			break;
		}
	}

	// Fall back to sys_get_temp_dir even though it won't work, so we have something.
	if (empty($temp_dir))
		$temp_dir = sys_get_temp_dir();

	// Fix the path.
	$temp_dir = substr($temp_dir, -1) === '/' ? $temp_dir : $temp_dir . '/';

	// Put things back.
	error_reporting($old_error_reporting);

	return $temp_dir;
}

?>