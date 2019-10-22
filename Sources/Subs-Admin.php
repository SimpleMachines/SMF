<?php

/**
 * This file contains functions that are specifically done by administrators.
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

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'xcache', 'apc', 'php' or 'server'
 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
 */
function getServerVersions($checkFor)
{
	global $txt, $db_connection, $_PHPA, $smcFunc, $cache_accelerator, $cache_memcached, $cacheAPI, $modSettings;

	loadLanguage('Admin');

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
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
		{
			$versions['db_engine'] = array('title' => sprintf($txt['support_versions_db_engine'], $smcFunc['db_title']), 'version' => '');
			$versions['db_engine']['version'] = $smcFunc['db_get_vendor']();

			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], $smcFunc['db_title']), 'version' => '');
			$versions['db_server']['version'] = $smcFunc['db_get_version']();
		}
	}

	// If we're using memcache we need the server info.
	$memcache_version = '???';
	if (!empty($cache_accelerator) && ($cache_accelerator == 'memcached' || $cache_accelerator == 'memcache') && !empty($cache_memcached) && !empty($cacheAPI))
		$memcache_version = $cacheAPI->getVersion();

	// Check to see if we have any accelerators installed...
	if (in_array('phpa', $checkFor) && isset($_PHPA))
		$versions['phpa'] = array('title' => 'ionCube PHP-Accelerator', 'version' => $_PHPA['VERSION']);
	if (in_array('apc', $checkFor) && extension_loaded('apc'))
		$versions['apc'] = array('title' => 'Alternative PHP Cache', 'version' => phpversion('apc'));
	if (in_array('memcache', $checkFor) && function_exists('memcache_set'))
		$versions['memcache'] = array('title' => 'Memcached', 'version' => $memcache_version);
	if (in_array('xcache', $checkFor) && function_exists('xcache_set'))
		$versions['xcache'] = array('title' => 'XCache', 'version' => XCACHE_VERSION);

	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION, 'more' => '?action=admin;area=serversettings;sa=phpinfo');

	if (in_array('server', $checkFor))
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']);

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
 * - Correctly formats the values using var_export().
 *
 * - Automatically restores standard formatting of the file.
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
 * @param array $config_vars An array of one or more variables to update.
 * @param bool|null $keep_quotes Whether to strip slashes & trim quotes from string values. Defaults to auto-detection.
 * @param bool $partial If true, does not try to make sure all variables are correct. Default false.
 * @return bool True on success, false on failure.
 */
function updateSettingsFile($config_vars, $keep_quotes = null, $partial = false)
{
	global $context;

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
	if (count($config_vars) === 1 && isset($config_vars['db_last_error']))
	{
		updateDbLastError($config_vars['db_last_error']);
		return true;
	}

	/*****************
	 * PART 1: Setup *
	 *****************/

	// Typically Settings.php is in $boarddir, but maybe this is a custom setup...
	foreach (get_included_files() as $settingsFile)
		if (basename($settingsFile) === 'Settings.php')
			break;

	// When was Settings.php last changed?
	$last_settings_change = filemtime($settingsFile);

	/**
	 * A big, fat array to define properties of all the Settings.php variables.
	 *
	 * - String keys are used to identify actual variables.
	 *
	 * - Integer keys are used for content not connected to any particular
	 *   variable, such as code blocks or the license block.
	 *
	 * - The content of the 'text' element is simply printed out. Use it for
	 *   comments or to insert code blocks, etc.
	 *
	 * - The 'default' element, not surprisingly, gives a default value for
	 *   the variable.
	 *
	 * - If 'raw_default' is true, the default should be printed directly,
	 *   rather than being handles as a string. Use it if the default contains
	 *   code, e.g. 'dirname(__FILE__)'
	 *
	 * - If 'required' is true and a value for the variable is undefined,
	 *   the update will be aborted. (The only exception is during the SMF
	 *   installation process.)
	 *
	 * - If 'null_delete' is true and a value for the variable is undefined or
	 *   null, the variable will be deleted from Settings.php.
	 */
	$settings_defs = array(
		0 => array(
			'text' => implode("\n", array(
				'',
				'/**',
				' * The settings file contains all of the basic settings that need to be present when a database/cache is not available.',
				' *',
				' * Simple Machines Forum (SMF)',
				' *',
				' * @package SMF',
				' * @author Simple Machines http://www.simplemachines.org',
				' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors',
				' * @license http://www.simplemachines.org/about/smf/license.php BSD',
				' *',
				' * @version ' . SMF_VERSION,
				' */',
			)),
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
		),
		'db_persist' => array(
			'text' => implode("\n", array(
				'/**',
				' * Use a persistent database connection',
				' *',
				' * @var int|bool',
				' */',
			)),
			'default' => 0,
		),
		'db_error_send' => array(
			'text' => implode("\n", array(
				'/**',
				' *',
				' * @var int|bool',
				' */',
			)),
			'default' => 0,
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
		),
		'cache_accelerator' => array(
			'text' => implode("\n", array(
				'',
				'########## Cache Info ##########',
				'/**',
				' * Select a cache system. You want to leave this up to the cache area of the admin panel for',
				' * proper detection of apc, memcached, output_cache, smf, or xcache',
				' * (you can add more with a mod).',
				' *',
				' * @var string',
				' */',
			)),
			'default' => '',
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
		),
		'image_proxy_enabled' => array(
			'text' => implode("\n", array(
				'',
				'########## Image Proxy ##########',
				'# This is done entirely in Settings.php to avoid loading the DB while serving the images',
				'/**',
				' * Whether the proxy is enabled or not',
				' *',
				' * @var int|bool',
				' */',
			)),
			'default' => 1,
		),
		'image_proxy_secret' => array(
			'text' => implode("\n", array(
				'',
				'/**',
				' * Secret key to be used by the proxy',
				' *',
				' * @var string',
				' */',
			)),
			'default' => 'smfisawesome',
		),
		'image_proxy_maxsize' => array(
			'text' => implode("\n", array(
				'',
				'/**',
				' * Maximum file size (in KB) for individual files',
				' *',
				' * @var int',
				' */',
			)),
			'default' => 5192,
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
		),
		1 => array(
			'text' => implode("\n", array(
				'',
				'# Make sure the paths are correct... at least try to fix them.',
				'if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . \'/agreement.txt\'))',
				'	$boarddir = dirname(__FILE__);',
				'if (!file_exists($sourcedir) && file_exists($boarddir . \'/Sources\'))',
				'	$sourcedir = $boarddir . \'/Sources\';',
				'if (!file_exists($cachedir) && file_exists($boarddir . \'/cache\'))',
				'	$cachedir = $boarddir . \'/cache\';',
			)),
		),
		'db_character_set' => array(
			'text' => implode("\n", array(
				'',
				'######### Legacy Settings #########',
				'# UTF-8 is now the only character set supported in 2.1.',
			)),
			'default' => 'utf8',
		),
		2 => array(
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
		),
		// Temporary variable used during the upgrade process.
		'upgradeData' => array(
			'default' => null,
			'null_delete' => true,
		),
	);

	// Allow mods the option to define comments, defaults, etc., for their settings.
	call_integration_hook('integrate_update_settings_file', array(&$settings_defs));

	// Just doing the bare minimum, eh? I guess someone's feeling lazy.
	if ($partial)
		$settings_defs = array_intersect_key($settings_defs, $config_vars);

	// Make sure we have values for everything.
	foreach ($settings_defs as $var => $setting_def)
	{
		if (is_string($var) && !isset($config_vars[$var]) && in_array($var, array_keys($GLOBALS)))
			$config_vars[$var] = $GLOBALS[$var];
	}


	/******************************
	 * PART 2: Content processing *
	 ******************************/

	// This little function gets the appropriate regex for the variable type.
	$gettype_regex = function($var)
	{
		$flags = '';
		switch (gettype($var))
		{
			case 'string':
				$regex = '(?(?=")"(?:[^"]|(?<=\\\)")*"|\'(?:[^\']|(?<=\\\)\')*\')';
				break;

			case 'integer':
				$regex = '\d+';
				break;

			case 'double':
				$regex = '\d+\.\d+';
				break;

			case 'boolean':
				$regex = '(?i:TRUE|FALSE)';
				break;

			case 'NULL':
				$regex = '(?i:NULL)';
				break;

			case 'array':
				// @todo This regex can probably be improved.
				$regex = 'array\s*\((?:[^;]|(?<=\\\);)*?\)';
				$flags = 'm';
				break;

			default:
				$regex = '';
				break;
		}

		return array($regex, $flags);
	};

	// Time to build our new Settings.php!
	$prefix = mt_rand() . '-';
	$substitutions = array(
		'^' => array(
			'search_pattern' => '~^\s*<\?(php\b)?\n?~',
			'placeholder' => '',
			'replace_pattern' => '~^~',
			'replacement' => '<' . "?php\n",
		),
		'$' => array(
			'search_pattern' => '~\s*\?' . '>\s*$~',
			'placeholder' => '',
			'replace_pattern' => '~$~',
			'replacement' => "\n\n?" . '>',
		),
	);

	foreach ($settings_defs as $var => $setting_def)
	{
		$placeholder = md5($prefix . $var);

		if (isset($setting_def['text']))
		{
			$text_pattern = preg_quote($setting_def['text'], '~');

			// Special handling for the license block.
			if (strpos($setting_def['text'], "* @package SMF\n") !== false)
			{
				$license_block = $var;
				$text_pattern = preg_replace(
					array(
						'~@copyright \d{4}~',
						'~@version \d+\.\d+(?>\.\d+| (?>RC|Beta |Alpha )\d+)?~',
					),
					array(
						'@copyright \d{4}',
						'@version \d+\.\d+(?>\.\d+| (?>RC|Beta |Alpha )\d+)?',
					),
					$text_pattern
				);

				$text_pattern = implode('\n[ \t]*', explode("\n", $text_pattern));

				$substitutions[$var]['search_pattern'] = '~' . $text_pattern . '\n?~';
				$substitutions[$var]['placeholder'] = '';
				$substitutions['^']['placeholder'] .= $placeholder . "\n";
				$substitutions['^']['replacement'] .= $setting_def['text'] . "\n";
			}

			// The text is the whole thing (code blocks, etc.)
			elseif (is_int($var))
			{
				$substitutions[$var]['search_pattern'] = '~' . $text_pattern . '\n?~';
				$substitutions[$var]['placeholder'] =  $var === $license_block ? '' : $placeholder . "\n";
			}
			// The text is just a comment.
			else
			{
				$text_pattern = implode('\n[ \t]*', explode("\n", $text_pattern));

				$substitutions['text_' . $var]['search_pattern'] = '~' . $text_pattern . '\n?~';
				$substitutions['text_' . $var]['placeholder'] = '';
			}
		}

		if (is_string($var))
		{
			// We don't save objects in Settings.php.
			if (is_object($config_vars[$var]))
			{
				if (method_exists($config_vars[$var], '__toString'))
					$config_vars[$var] = (string) $config_vars[$var];
				else
					$config_vars[$var] = (array) $config_vars[$var];
			}

			list($var_pattern, $flags) = $gettype_regex($config_vars[$var]);

			if (!empty($setting_def['raw_default']) && !empty($setting_def['default']))
				$var_pattern = '(?:' . $var_pattern . '|' . preg_quote($setting_def['default'], '~') . ')';

			$substitutions[$var]['search_pattern'] = '~[ \t]*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';\n?~' . $flags;
			$substitutions[$var]['placeholder'] = $placeholder . "\n";
		}

		$replacement = '';

		// Add our lovely text block.
		if (!empty($setting_def['text']))
			$replacement .= $setting_def['text'] . "\n";

		if (is_string($var))
		{
			// A setting to delete.
			if (!empty($setting_def['null_delete']) && !isset($config_vars[$var]))
			{
				$replacement = '';
			}
			// Add this setting's value.
			elseif (isset($config_vars[$var]) || is_null($config_vars[$var]))
			{
				$replacement .= '$' . $var . ' = ' . var_export($config_vars[$var], TRUE) . ";\n";
				unset($config_vars[$var]);
			}
			// Uh-oh! Something must have gone horribly, inconceivably wrong.
			elseif (!empty($setting_def['required']) && !defined('SMF_INSTALLING'))
			{
				$context['settings_message'] = 'settings_error';
				return false;
			}
			// Fall back to the default value.
			elseif (isset($setting_def['default']))
			{
				$replacement .= '$' . $var . ' = ' . (!empty($setting_def['raw_default']) ? sprintf($setting_def['default']) : var_export($setting_def['default'], true)) . ";\n";
			}
			// We've got nothing...
			else
			{
				$replacement .= '$' . $var . ' = null;' . "\n";
			}
		}

		$substitutions[$var]['replace_pattern'] = '~\b' . $placeholder . '\n~';
		$substitutions[$var]['replacement'] = $replacement;
	}

	// Any leftovers to deal with?
	foreach ($config_vars as $var => $val)
	{
		if ($var !== 'db_last_error')
		{
			if (is_object($config_vars[$var]))
			{
				if (method_exists($config_vars[$var], '__toString'))
					$config_vars[$var] = (string) $config_vars[$var];
				else
					$config_vars[$var] = (array) $config_vars[$var];
			}

			list($var_pattern, $flags) = $gettype_regex($config_vars[$var]);

			$placeholder = md5($prefix . $var);

			$substitutions[$var]['search_pattern'] = '~[ \t]*\$' . preg_quote($var, '~') . '\s*=\s*' . $var_pattern . ';\n?~' . $flags;
			$substitutions[$var]['placeholder'] = $placeholder;
			$substitutions[$var]['replace_pattern'] = '~\b' . $placeholder . '\n~';
			$substitutions[$var]['replacement'] = '$' . $var . ' = ' . var_export($val, true) . ";\n";
		}
	}

	// It's important to do the numbered ones before the named ones, or messes happen.
	uksort($substitutions, function($a, $b) {
		if (is_int($a) && is_int($b))
			return $a > $b;
		elseif (is_int($a))
			return -1;
		elseif (is_int($b))
			return 1;
		else
			return strcasecmp($a, $b) * -1;
	});

	foreach ($substitutions as $var => $substitution)
	{
		$search_patterns[] = $substitution['search_pattern'];
		$placeholders[] = $substitution['placeholder'];

		if (strpos($var, 'text_') !== 0)
		{
			if (!empty($substitution['placeholder']))
			{
				$simple_replacements[$substitution['placeholder']] = $substitution['replacement'];
			}
			else
			{
				$replace_patterns[] = $substitution['replace_pattern'];
				$replace_strings[] = $substitution['replacement'];
			}
		}
	}

	// Retrieve the contents of the settings file and fill in our placeholders.
	$settingsText = preg_replace($search_patterns, $placeholders, file_get_contents($settingsFile));

	// Where possible, perform simple substitutions.
	$settingsText = strtr($settingsText, $simple_replacements);

	// Deal with the complicated ones.
	$settingsText = preg_replace($replace_patterns, $replace_strings, $settingsText);

	// This one is just cosmetic. Get rid of extra lines of whitespace.
	$settingsText = preg_replace('~(\n[ \t]*)+\n~', "\n\n", $settingsText);

	/******************************************
	 * PART 3: Write updated settings to file *
	 ******************************************/

	$backupFile = dirname($settingsFile) . '/Settings_bak.php';
	$temp_sfile = tempnam($GLOBALS['cachedir'], 'Settings.');
	$temp_bfile = tempnam($GLOBALS['cachedir'], 'Settings_bak.');

	// We need write permissions.
	$failed = false;
	foreach (array($settingsFile, $backupFile) as $sf)
	{
		if (!file_exists($sf))
			touch($sf);
		elseif (!is_file($sf))
			$failed = true;

		if (!$failed)
			$failed = !smf_chmod($sf);
	}

	// Is there enough free space on the disk?
	if (!$failed && disk_free_space(dirname($settingsFile)) < (strlen($settingsText) + filesize($backupFile) + filesize($settingsFile)))
		$failed = true;

	// Now let's see if writing to a temp file succeeds.
	if (!$failed && file_put_contents($temp_bfile, $settingsText, LOCK_EX) !== strlen($settingsText))
		$failed = true;

	// Tests passed, so it's time to do the job.
	if (!$failed)
	{
		// Back up the backup, just in case.
		if (file_exists($backupFile))
			$temp_bfile_saved = @copy($backupFile, $temp_bfile);

		// Make sure no one changed Settings.php while we weren't looking.
		clearstatcache();
		if (filemtime($settingsFile) === $last_settings_change)
		{
			// Attempt to open Settings.php
			$sfhandle = @fopen($settingsFile, 'c');

			// Let's do this thing!
			if ($sfhandle !== false)
			{
				// Immediately get a lock.
				flock($sfhandle, LOCK_EX);

				// Make sure the backup works before we do anything more.
				$temp_sfile_saved = @copy($settingsFile, $temp_sfile);

				// Now write our new settings to the file.
				if ($temp_sfile_saved)
				{
					ftruncate($sfhandle, 0);
					rewind($sfhandle);
					$failed = fwrite($sfhandle, $settingsText) !== strlen($settingsText);

					// Hooray!
					if (!$failed)
						@rename($temp_sfile, $backupFile);
				}

				flock($sfhandle, LOCK_UN);
				fclose($sfhandle);

				// If writing failed, put everything back the way it was.
				if ($failed)
				{
					if (!empty($temp_sfile_saved))
						@rename($temp_sfile, $settingsFile);

					if (!empty($temp_bfile_saved))
						@rename($temp_bfile, $backupFile);
				}
			}
		}
	}

	// We're done with these.
	@unlink($temp_sfile, $temp_bfile);

	if ($failed)
	{
		$context['settings_message'] = 'settings_error';
		return false;
	}

	// Even though on normal installations the filemtime should invalidate any cached version
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($settingsFile, true);

	return true;
}

/**
 * Saves the time of the last db error for the error log
 * - Done separately from updateSettingsFile to avoid race conditions
 *   which can occur during a db error
 * - If it fails Settings.php will assume 0
 *
 * @param int $time The timestamp of the last DB error
 */
function updateDbLastError($time)
{
	global $boarddir, $cachedir;

	// Write out the db_last_error file with the error timestamp
	file_put_contents($cachedir . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = ' . $time . ';' . "\n" . '?' . '>', LOCK_EX);
	@touch($boarddir . '/' . 'Settings.php');
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

?>