<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 *
 * This file contains helper functions for upgrade.php
 */

if (!defined('SMF_VERSION'))
	die('No direct access!');

/**
 * Clean the cache using the SMF 2.1 CacheAPI.
 * If coming from SMF 2.0 and below it should wipe the cache using the SMF backend.
 */
function upgrade_clean_cache()
{
	global $cacheAPI, $sourcedir;

	// Initialize the cache API if it does not have an instance yet.
	require_once($sourcedir . '/Load.php');
	if (empty($cacheAPI))
	{
		loadCacheAccelerator();
	}

	// Just through back to Load.php's clean_cache function.
	clean_cache();
}

/**
 * Returns a list of member groups. Used to upgrade 1.0 and 1.1.
 *
 * @return array
 */
function getMemberGroups()
{
	global $smcFunc;
	static $member_groups = array();

	if (!empty($member_groups))
		return $member_groups;

	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group
		FROM {db_prefix}membergroups
		WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
		array(
			'admin_group' => 1,
			'old_group' => 7,
			'db_error_skip' => true,
		)
	);
	if ($request === false)
	{
		$request = $smcFunc['db_query']('', '
			SELECT membergroup, id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
			array(
				'admin_group' => 1,
				'old_group' => 7,
				'db_error_skip' => true,
			)
		);
	}
	while ($row = $smcFunc['db_fetch_row']($request))
		$member_groups[trim($row[0])] = $row[1];
	$smcFunc['db_free_result']($request);

	return $member_groups;
}

/**
 * Make files writable. First try to use regular chmod, but if that fails, try to use FTP.
 *
 * @param $files
 * @return bool
 */
function makeFilesWritable(&$files)
{
	global $upcontext, $boarddir, $sourcedir;

	if (empty($files))
		return true;

	$failure = false;
	// On linux, it's easy - just use is_writable!
	if (substr(__FILE__, 1, 2) != ':\\')
	{
		$upcontext['systemos'] = 'linux';

		foreach ($files as $k => $file)
		{
			// Some files won't exist, try to address up front
			if (!file_exists($file))
				@touch($file);
			// NOW do the writable check...
			if (!is_writable($file))
			{
				@chmod($file, 0755);

				// Well, 755 hopefully worked... if not, try 777.
				if (!is_writable($file) && !@chmod($file, 0777))
					$failure = true;
				// Otherwise remove it as it's good!
				else
					unset($files[$k]);
			}
			else
				unset($files[$k]);
		}
	}
	// Windows is trickier.  Let's try opening for r+...
	else
	{
		$upcontext['systemos'] = 'windows';

		foreach ($files as $k => $file)
		{
			// Folders can't be opened for write... but the index.php in them can ;).
			if (is_dir($file))
				$file .= '/index.php';

			// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
			@chmod($file, 0777);
			$fp = @fopen($file, 'r+');

			// Hmm, okay, try just for write in that case...
			if (!$fp)
				$fp = @fopen($file, 'w');

			if (!$fp)
				$failure = true;
			else
				unset($files[$k]);
			@fclose($fp);
		}
	}

	if (empty($files))
		return true;

	if (!isset($_SERVER))
		return !$failure;

	// What still needs to be done?
	$upcontext['chmod']['files'] = $files;

	// If it's windows it's a mess...
	if ($failure && substr(__FILE__, 1, 2) == ':\\')
	{
		$upcontext['chmod']['ftp_error'] = 'total_mess';

		return false;
	}
	// We're going to have to use... FTP!
	elseif ($failure)
	{
		// Load any session data we might have...
		if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
		{
			$upcontext['chmod']['server'] = $_SESSION['installer_temp_ftp']['server'];
			$upcontext['chmod']['port'] = $_SESSION['installer_temp_ftp']['port'];
			$upcontext['chmod']['username'] = $_SESSION['installer_temp_ftp']['username'];
			$upcontext['chmod']['password'] = $_SESSION['installer_temp_ftp']['password'];
			$upcontext['chmod']['path'] = $_SESSION['installer_temp_ftp']['path'];
		}
		// Or have we submitted?
		elseif (isset($_POST['ftp_username']))
		{
			$upcontext['chmod']['server'] = $_POST['ftp_server'];
			$upcontext['chmod']['port'] = $_POST['ftp_port'];
			$upcontext['chmod']['username'] = $_POST['ftp_username'];
			$upcontext['chmod']['password'] = $_POST['ftp_password'];
			$upcontext['chmod']['path'] = $_POST['ftp_path'];
		}

		require_once($sourcedir . '/Class-Package.php');
		if (isset($upcontext['chmod']['username']))
		{
			$ftp = new ftp_connection($upcontext['chmod']['server'], $upcontext['chmod']['port'], $upcontext['chmod']['username'], $upcontext['chmod']['password']);

			if ($ftp->error === false)
			{
				// Try it without /home/abc just in case they messed up.
				if (!$ftp->chdir($upcontext['chmod']['path']))
				{
					$upcontext['chmod']['ftp_error'] = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $upcontext['chmod']['path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
				$ftp = new ftp_connection(null);
			// Save the error so we can mess with listing...
			elseif ($ftp->error !== false && !isset($upcontext['chmod']['ftp_error']))
				$upcontext['chmod']['ftp_error'] = $ftp->last_message === null ? '' : $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path(dirname(__FILE__));

			if ($found_path || !isset($upcontext['chmod']['path']))
				$upcontext['chmod']['path'] = $detect_path;

			if (!isset($upcontext['chmod']['username']))
				$upcontext['chmod']['username'] = $username;

			// Don't forget the login token.
			$upcontext += createToken('login');

			return false;
		}
		else
		{
			// We want to do a relative path for FTP.
			if (!in_array($upcontext['chmod']['path'], array('', '/')))
			{
				$ftp_root = strtr($boarddir, array($upcontext['chmod']['path'] => ''));
				if (substr($ftp_root, -1) == '/' && ($upcontext['chmod']['path'] == '' || $upcontext['chmod']['path'][0] === '/'))
					$ftp_root = substr($ftp_root, 0, -1);
			}
			else
				$ftp_root = $boarddir;

			// Save the info for next time!
			$_SESSION['installer_temp_ftp'] = array(
				'server' => $upcontext['chmod']['server'],
				'port' => $upcontext['chmod']['port'],
				'username' => $upcontext['chmod']['username'],
				'password' => $upcontext['chmod']['password'],
				'path' => $upcontext['chmod']['path'],
				'root' => $ftp_root,
			);

			foreach ($files as $k => $file)
			{
				if (!is_writable($file))
					$ftp->chmod($file, 0755);
				if (!is_writable($file))
					$ftp->chmod($file, 0777);

				// Assuming that didn't work calculate the path without the boarddir.
				if (!is_writable($file))
				{
					if (strpos($file, $boarddir) === 0)
					{
						$ftp_file = strtr($file, array($_SESSION['installer_temp_ftp']['root'] => ''));
						$ftp->chmod($ftp_file, 0755);
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0777);
						// Sometimes an extra slash can help...
						$ftp_file = '/' . $ftp_file;
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0755);
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0777);
					}
				}

				if (is_writable($file))
					unset($files[$k]);
			}

			$ftp->close();
		}
	}

	// What remains?
	$upcontext['chmod']['files'] = $files;

	if (empty($files))
		return true;

	return false;
}

/**
 * The quick version of makeFilesWritable, which does not support FTP.
 *
 * @param string $file
 * @return bool
 */
function quickFileWritable($file)
{
	// Some files won't exist, try to address up front
	if (!file_exists($file))
		@touch($file);

	// NOW do the writable check...
	if (is_writable($file))
		return true;

	@chmod($file, 0755);

	// Try 755 and 775 first since 777 doesn't always work and could be a risk...
	$chmod_values = array(0755, 0775, 0777);

	foreach ($chmod_values as $val)
	{
		// If it's writable, break out of the loop
		if (is_writable($file))
			break;
		else
			@chmod($file, $val);
	}

	return is_writable($file);
}

/**
 * UTF-8 aware strtolower function.
 *
 * @param $string
 * @return string
 */
function smf_strtolower($string)
{
	return mb_strtolower($string, 'UTF-8');
}

/**
 * Prints an error to stderr.
 *
 * @param $message
 * @param bool $fatal
 */
function print_error($message, $fatal = false)
{
	static $fp = null;

	if ($fp === null)
		$fp = fopen('php://stderr', 'wb');

	fwrite($fp, $message . "\n");

	if ($fatal)
		exit;
}

/**
 * Throws a graphical error message.
 *
 * @param $message
 * @return bool
 */
function throw_error($message)
{
	global $upcontext;

	$upcontext['error_msg'] = $message;
	$upcontext['sub_template'] = 'error_message';

	return false;
}

/**
 * Database functions below here.
 */
/**
 * @param $rs
 * @return array|null
 */
function smf_mysql_fetch_assoc($rs)
{
	return mysqli_fetch_assoc($rs);
}

/**
 * @param $rs
 * @return array|null
 */
function smf_mysql_fetch_row($rs)
{
	return mysqli_fetch_row($rs);
}

/**
 * @param $rs
 */
function smf_mysql_free_result($rs)
{
	mysqli_free_result($rs);
}

/**
 * @param $rs Ignored
 * @return int|string
 */
function smf_mysql_insert_id($rs)
{
	global $db_connection;
	return mysqli_insert_id($db_connection);
}

/**
 * @param $rs
 * @return int
 */
function smf_mysql_num_rows($rs)
{
	return mysqli_num_rows($rs);
}

/**
 * @param $string
 */
function smf_mysql_real_escape_string($string)
{
	global $db_connection;
	mysqli_real_escape_string($db_connection, $string);
}

/**
 * Substitute for array_column() for use in php 5.4
 *
 * @param $array to search
 * @param $col to select
 * @param $index to use as index if specified
 * @return array of values of specified $col from $array
 */
if (!function_exists('array_column')) {
	function array_column($input, $column_key, $index_key = null) {
		$arr = array_map(function($d) use ($column_key, $index_key) {
			if (!isset($d[$column_key])) {
				return null;
			}
			if ($index_key !== null) {
				return array($d[$index_key] => $d[$column_key]);
			}
			return $d[$column_key];
		}, $input);

		if ($index_key !== null) {
			$tmp = array();
			foreach ($arr as $ar) {
				$tmp[key($ar)] = current($ar);
			}
			$arr = $tmp;
		}
		return $arr;
	}
}
