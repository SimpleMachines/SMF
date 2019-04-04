<?php

/**
 * This file's central purpose of existence is that of making the package
 * manager work nicely.  It contains functions for handling tar.gz and zip
 * files, as well as a simple xml parser to handle the xml package stuff.
 * Not to mention a few functions to make file handling easier.
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
 * Reads a .tar.gz file, filename, in and extracts file(s) from it.
 * essentially just a shortcut for read_tgz_data().
 *
 * @param string $gzfilename The path to the tar.gz file
 * @param string $destination The path to the desitnation directory
 * @param bool $single_file If true returns the contents of the file specified by destination if it exists
 * @param bool $overwrite Whether to overwrite existing files
 * @param null|array $files_to_extract Specific files to extract
 * @return array|false An array of information about extracted files or false on failure
 */
function read_tgz_file($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	return read_tgz_data($gzfilename, $destination, $single_file, $overwrite, $files_to_extract);
}

/**
 * Extracts a file or files from the .tar.gz contained in data.
 *
 * detects if the file is really a .zip file, and if so returns the result of read_zip_data
 *
 * if destination is null
 *	- returns a list of files in the archive.
 *
 * if single_file is true
 * - returns the contents of the file specified by destination, if it exists, or false.
 * - destination can start with * and / to signify that the file may come from any directory.
 * - destination should not begin with a / if single_file is true.
 *
 * overwrites existing files with newer modification times if and only if overwrite is true.
 * creates the destination directory if it doesn't exist, and is is specified.
 * requires zlib support be built into PHP.
 * returns an array of the files extracted.
 * if files_to_extract is not equal to null only extracts file within this array.
 *
 * @param string $gzfilename The name of the file
 * @param string $destination The destination
 * @param bool $single_file Whether to only extract a single file
 * @param bool $overwrite Whether to overwrite existing data
 * @param null|array $files_to_extract If set, only extracts the specified files
 * @return array|false An array of information about the extracted files or false on failure
 */
function read_tgz_data($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	// Make sure we have this loaded.
	loadLanguage('Packages');

	// This function sorta needs gzinflate!
	if (!function_exists('gzinflate'))
		fatal_lang_error('package_no_lib', 'critical', array('package_no_zlib', 'package_no_package_manager'));

	if (substr($gzfilename, 0, 7) == 'http://' || substr($gzfilename, 0, 8) == 'https://')
	{
		$data = fetch_web_data($gzfilename);

		if ($data === false)
			return false;
	}
	else
	{
		$data = @file_get_contents($gzfilename);

		if ($data === false)
			return false;
	}

	umask(0);
	if (!$single_file && $destination !== null && !file_exists($destination))
		mktree($destination, 0777);

	// No signature?
	if (strlen($data) < 2)
		return false;

	$id = unpack('H2a/H2b', substr($data, 0, 2));
	if (strtolower($id['a'] . $id['b']) != '1f8b')
	{
		// Okay, this ain't no tar.gz, but maybe it's a zip file.
		if (substr($data, 0, 2) == 'PK')
			return read_zip_file($gzfilename, $destination, $single_file, $overwrite, $files_to_extract);
		else
			return false;
	}

	$flags = unpack('Ct/Cf', substr($data, 2, 2));

	// Not deflate!
	if ($flags['t'] != 8)
		return false;
	$flags = $flags['f'];

	$offset = 10;
	$octdec = array('mode', 'uid', 'gid', 'size', 'mtime', 'checksum', 'type');

	// "Read" the filename and comment.
	// @todo Might be mussed.
	if ($flags & 12)
	{
		while ($flags & 8 && $data{$offset++} != "\0")
			continue;
		while ($flags & 4 && $data{$offset++} != "\0")
			continue;
	}

	$crc = unpack('Vcrc32/Visize', substr($data, strlen($data) - 8, 8));
	$data = @gzinflate(substr($data, $offset, strlen($data) - 8 - $offset));

	// smf_crc32 and crc32 may not return the same results, so we accept either.
	if ($crc['crc32'] != smf_crc32($data) && $crc['crc32'] != crc32($data))
		return false;

	$blocks = strlen($data) / 512 - 1;
	$offset = 0;

	$return = array();

	while ($offset < $blocks)
	{
		$header = substr($data, $offset << 9, 512);
		$current = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155path', $header);

		// Blank record?  This is probably at the end of the file.
		if (empty($current['filename']))
		{
			$offset += 512;
			continue;
		}

		foreach ($current as $k => $v)
		{
			if (in_array($k, $octdec))
				$current[$k] = octdec(trim($v));
			else
				$current[$k] = trim($v);
		}

		if ($current['type'] == 5 && substr($current['filename'], -1) != '/')
			$current['filename'] .= '/';

		$checksum = 256;
		for ($i = 0; $i < 148; $i++)
			$checksum += ord($header{$i});
		for ($i = 156; $i < 512; $i++)
			$checksum += ord($header{$i});

		if ($current['checksum'] != $checksum)
			break;

		$size = ceil($current['size'] / 512);
		$current['data'] = substr($data, ++$offset << 9, $current['size']);
		$offset += $size;

		// Not a directory and doesn't exist already...
		if (substr($current['filename'], -1, 1) != '/' && !file_exists($destination . '/' . $current['filename']))
			$write_this = true;
		// File exists... check if it is newer.
		elseif (substr($current['filename'], -1, 1) != '/')
			$write_this = $overwrite || filemtime($destination . '/' . $current['filename']) < $current['mtime'];
		// Folder... create.
		elseif ($destination !== null && !$single_file)
		{
			// Protect from accidental parent directory writing...
			$current['filename'] = strtr($current['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($destination . '/' . $current['filename']))
				mktree($destination . '/' . $current['filename'], 0777);
			$write_this = false;
		}
		else
			$write_this = false;

		if ($write_this && $destination !== null)
		{
			if (strpos($current['filename'], '/') !== false && !$single_file)
				mktree($destination . '/' . dirname($current['filename']), 0777);

			// Is this the file we're looking for?
			if ($single_file && ($destination == $current['filename'] || $destination == '*/' . basename($current['filename'])))
				return $current['data'];
			// If we're looking for another file, keep going.
			elseif ($single_file)
				continue;
			// Looking for restricted files?
			elseif ($files_to_extract !== null && !in_array($current['filename'], $files_to_extract))
				continue;

			package_put_contents($destination . '/' . $current['filename'], $current['data']);
		}

		if (substr($current['filename'], -1, 1) != '/')
			$return[] = array(
				'filename' => $current['filename'],
				'md5' => md5($current['data']),
				'preview' => substr($current['data'], 0, 100),
				'size' => $current['size'],
				'skipped' => false
			);
	}

	if ($destination !== null && !$single_file)
		package_flush_cache();

	if ($single_file)
		return false;
	else
		return $return;
}

/**
 * Extract zip data. A functional copy of {@list read_zip_data()}.
 *
 * @param string $file Input filename
 * @param string $destination Null to display a listing of files in the archive, the destination for the files in the archive or the name of a single file to display (if $single_file is true)
 * @param boolean $single_file If true, returns the contents of the file specified by destination or false if the file can't be found (default value is false).
 * @param boolean $overwrite If true, will overwrite files with newer modication times. Default is false.
 * @param array $files_to_extract Specific files to extract
 * @uses {@link PharData}
 * @return mixed If destination is null, return a short array of a few file details optionally delimited by $files_to_extract. If $single_file is true, return contents of a file as a string; false otherwise
 */

function read_zip_file($file, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	// This function sorta needs phar!
	if (!class_exists('PharData'))
		fatal_lang_error('package_no_lib', 'critical', array('package_no_phar', 'package_no_package_manager'));

	try
	{
		// This may not always be defined...
		$return = array();

		// Some hosted unix platforms require an extension; win may have .tmp & that works ok
		if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('zip', 'tmp')))
			if (@rename($file, $file . '.zip'))
				$file = $file . '.zip';

		// Phar doesn't handle open_basedir restrictions very well and throws a PHP Warning. Ignore that.
		set_error_handler(function($errno, $errstr, $errfile, $errline)
		{
			// error was suppressed with the @-operator
			if (0 === error_reporting())
			{
				return false;
			}
			if (strpos($errstr, 'PharData::__construct(): open_basedir') === false)
				log_error($errstr, 'general', $errfile, $errline);
			return true;
		}
		);
		$archive = new PharData($file, RecursiveIteratorIterator::SELF_FIRST, null, Phar::ZIP);
		restore_error_handler();

		$iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST);

		// go though each file in the archive
		foreach ($iterator as $file_info)
		{
			$i = $iterator->getSubPathname();
			// If this is a file, and it doesn't exist.... happy days!
			if (substr($i, -1) != '/' && !file_exists($destination . '/' . $i))
				$write_this = true;
			// If the file exists, we may not want to overwrite it.
			elseif (substr($i, -1) != '/')
				$write_this = $overwrite;
			else
				$write_this = false;

			// Get the actual compressed data.
			if (!$file_info->isDir())
				$file_data = file_get_contents($file_info);
			elseif ($destination !== null && !$single_file)
			{
				// Folder... create.
				if (!file_exists($destination . '/' . $i))
					mktree($destination . '/' . $i, 0777);
				$file_data = null;
			}
			else
				$file_data = null;

			// Okay!  We can write this file, looks good from here...
			if ($write_this && $destination !== null)
			{
				if (!$single_file && !is_dir($destination . '/' . dirname($i)))
					mktree($destination . '/' . dirname($i), 0777);

				// If we're looking for a specific file, and this is it... ka-bam, baby.
				if ($single_file && ($destination == $i || $destination == '*/' . basename($i)))
					return $file_data;
				// Oh?  Another file.  Fine.  You don't like this file, do you?  I know how it is.  Yeah... just go away.  No, don't apologize.  I know this file's just not *good enough* for you.
				elseif ($single_file)
					continue;
				// Don't really want this?
				elseif ($files_to_extract !== null && !in_array($i, $files_to_extract))
					continue;

				package_put_contents($destination . '/' . $i, $file_data);
			}

			if (substr($i, -1, 1) != '/')
				$return[] = array(
					'filename' => $i,
					'md5' => md5($file_data),
					'preview' => substr($file_data, 0, 100),
					'size' => strlen($file_data),
					'skipped' => false
				);
		}

		if ($destination !== null && !$single_file)
			package_flush_cache();

		if ($single_file)
			return false;
		else
			return $return;
	}
	catch (Exception $e)
	{
		log_error($e->getMessage(), 'general', $e->getFile(), $e->getLine());
		return false;
	}
}

/**
 * Extract zip data. .
 *
 * If single_file is true, destination can start with * and / to signify that the file may come from any directory.
 * Destination should not begin with a / if single_file is true.
 *
 * @param string $data ZIP data
 * @param string $destination Null to display a listing of files in the archive, the destination for the files in the archive or the name of a single file to display (if $single_file is true)
 * @param boolean $single_file If true, returns the contents of the file specified by destination or false if the file can't be found (default value is false).
 * @param boolean $overwrite If true, will overwrite files with newer modication times. Default is false.
 * @param array $files_to_extract
 * @return mixed If destination is null, return a short array of a few file details optionally delimited by $files_to_extract. If $single_file is true, return contents of a file as a string; false otherwise
 */
function read_zip_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	umask(0);
	if ($destination !== null && !file_exists($destination) && !$single_file)
		mktree($destination, 0777);

	// Look for the end of directory signature 0x06054b50
	$data_ecr = explode("\x50\x4b\x05\x06", $data);
	if (!isset($data_ecr[1]))
		return false;

	$return = array();

	// Get all the basic zip file info since we are here
	$zip_info = unpack('vdisks/vrecords/vfiles/Vsize/Voffset/vcomment_length/', $data_ecr[1]);

	// Cut file at the central directory file header signature -- 0x02014b50, use unpack if you want any of the data, we don't
	$file_sections = explode("\x50\x4b\x01\x02", $data);

	// Cut the result on each local file header -- 0x04034b50 so we have each file in the archive as an element.
	$file_sections = explode("\x50\x4b\x03\x04", $file_sections[0]);
	array_shift($file_sections);

	// sections and count from the signature must match or the zip file is bad
	if (count($file_sections) != $zip_info['files'])
		return false;

	// go though each file in the archive
	foreach ($file_sections as $data)
	{
		// Get all the important file information.
		$file_info = unpack("vversion/vgeneral_purpose/vcompress_method/vfile_time/vfile_date/Vcrc/Vcompressed_size/Vsize/vfilename_length/vextrafield_length", $data);
		$file_info['filename'] = substr($data, 26, $file_info['filename_length']);
		$file_info['dir'] = $destination . '/' . dirname($file_info['filename']);

		// If bit 3 (0x08) of the general-purpose flag is set, then the CRC and file size were not available when the header was written
		// In this case the CRC and size are instead appended in a 12-byte structure immediately after the compressed data
		if ($file_info['general_purpose'] & 0x0008)
		{
			$unzipped2 = unpack("Vcrc/Vcompressed_size/Vsize", substr($$data, -12));
			$file_info['crc'] = $unzipped2['crc'];
			$file_info['compressed_size'] = $unzipped2['compressed_size'];
			$file_info['size'] = $unzipped2['size'];
			unset($unzipped2);
		}

		// If this is a file, and it doesn't exist.... happy days!
		if (substr($file_info['filename'], -1) != '/' && !file_exists($destination . '/' . $file_info['filename']))
			$write_this = true;
		// If the file exists, we may not want to overwrite it.
		elseif (substr($file_info['filename'], -1) != '/')
			$write_this = $overwrite;
		// This is a directory, so we're gonna want to create it. (probably...)
		elseif ($destination !== null && !$single_file)
		{
			// Just a little accident prevention, don't mind me.
			$file_info['filename'] = strtr($file_info['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($destination . '/' . $file_info['filename']))
				mktree($destination . '/' . $file_info['filename'], 0777);
			$write_this = false;
		}
		else
			$write_this = false;

		// Get the actual compressed data.
		$file_info['data'] = substr($data, 26 + $file_info['filename_length'] + $file_info['extrafield_length']);

		// Only inflate it if we need to ;)
		if (!empty($file_info['compress_method']) || ($file_info['compressed_size'] != $file_info['size']))
			$file_info['data'] = gzinflate($file_info['data']);

		// Okay!  We can write this file, looks good from here...
		if ($write_this && $destination !== null)
		{
			if ((strpos($file_info['filename'], '/') !== false && !$single_file) || (!$single_file && !is_dir($file_info['dir'])))
				mktree($file_info['dir'], 0777);

			// If we're looking for a specific file, and this is it... ka-bam, baby.
			if ($single_file && ($destination == $file_info['filename'] || $destination == '*/' . basename($file_info['filename'])))
				return $file_info['data'];
			// Oh?  Another file.  Fine.  You don't like this file, do you?  I know how it is.  Yeah... just go away.  No, don't apologize.  I know this file's just not *good enough* for you.
			elseif ($single_file)
				continue;
			// Don't really want this?
			elseif ($files_to_extract !== null && !in_array($file_info['filename'], $files_to_extract))
				continue;

			package_put_contents($destination . '/' . $file_info['filename'], $file_info['data']);
		}

		if (substr($file_info['filename'], -1, 1) != '/')
			$return[] = array(
				'filename' => $file_info['filename'],
				'md5' => md5($file_info['data']),
				'preview' => substr($file_info['data'], 0, 100),
				'size' => $file_info['size'],
				'skipped' => false
			);
	}

	if ($destination !== null && !$single_file)
		package_flush_cache();

	if ($single_file)
		return false;
	else
		return $return;
}

/**
 * Checks the existence of a remote file since file_exists() does not do remote.
 * will return false if the file is "moved permanently" or similar.
 *
 * @param string $url The URL to parse
 * @return bool Whether the specified URL exists
 */
function url_exists($url)
{
	$a_url = parse_url($url);

	if (!isset($a_url['scheme']))
		return false;

	// Attempt to connect...
	$temp = '';
	$fid = fsockopen($a_url['host'], !isset($a_url['port']) ? 80 : $a_url['port'], $temp, $temp, 8);
	if (!$fid)
		return false;

	fputs($fid, 'HEAD ' . $a_url['path'] . ' HTTP/1.0' . "\r\n" . 'Host: ' . $a_url['host'] . "\r\n\r\n");
	$head = fread($fid, 1024);
	fclose($fid);

	return preg_match('~^HTTP/.+\s+(20[01]|30[127])~i', $head) == 1;
}

/**
 * Loads and returns an array of installed packages.
 * - returns the array of data.
 * - default sort order is package_installed time
 *
 * @return array An array of info about installed packages
 */
function loadInstalledPackages()
{
	global $smcFunc;

	// Load the packages from the database - note this is ordered by install time to ensure latest package uninstalled first.
	$request = $smcFunc['db_query']('', '
		SELECT id_install, package_id, filename, name, version, time_installed
		FROM {db_prefix}log_packages
		WHERE install_state != {int:not_installed}
		ORDER BY time_installed DESC',
		array(
			'not_installed' => 0,
		)
	);
	$installed = array();
	$found = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Already found this? If so don't add it twice!
		if (in_array($row['package_id'], $found))
			continue;

		$found[] = $row['package_id'];

		$row = htmlspecialchars__recursive($row);

		$installed[] = array(
			'id' => $row['id_install'],
			'name' => $smcFunc['htmlspecialchars']($row['name']),
			'filename' => $row['filename'],
			'package_id' => $row['package_id'],
			'version' => $smcFunc['htmlspecialchars']($row['version']),
			'time_installed' => !empty($row['time_installed']) ? $row['time_installed'] : 0,
		);
	}
	$smcFunc['db_free_result']($request);

	return $installed;
}

/**
 * Loads a package's information and returns a representative array.
 * - expects the file to be a package in Packages/.
 * - returns a error string if the package-info is invalid.
 * - otherwise returns a basic array of id, version, filename, and similar information.
 * - an xmlArray is available in 'xml'.
 *
 * @param string $gzfilename The path to the file
 * @return array|string An array of info about the file or a string indicating an error
 */
function getPackageInfo($gzfilename)
{
	global $sourcedir, $packagesdir;

	// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
	if (strpos($gzfilename, 'http://') !== false || strpos($gzfilename, 'https://') !== false)
		$packageInfo = read_tgz_data($gzfilename, 'package-info.xml', true);
	else
	{
		if (!file_exists($packagesdir . '/' . $gzfilename))
			return 'package_get_error_not_found';

		if (is_file($packagesdir . '/' . $gzfilename))
			$packageInfo = read_tgz_file($packagesdir . '/' . $gzfilename, '*/package-info.xml', true);
		elseif (file_exists($packagesdir . '/' . $gzfilename . '/package-info.xml'))
			$packageInfo = file_get_contents($packagesdir . '/' . $gzfilename . '/package-info.xml');
		else
			return 'package_get_error_missing_xml';
	}

	// Nothing?
	if (empty($packageInfo))
	{
		// Perhaps they are trying to install a theme, lets tell them nicely this is the wrong function
		$packageInfo = read_tgz_file($packagesdir . '/' . $gzfilename, '*/theme_info.xml', true);
		if (!empty($packageInfo))
			return 'package_get_error_is_theme';
		else
			return 'package_get_error_is_zero';
	}

	// Parse package-info.xml into an xmlArray.
	require_once($sourcedir . '/Class-Package.php');
	$packageInfo = new xmlArray($packageInfo);

	// @todo Error message of some sort?
	if (!$packageInfo->exists('package-info[0]'))
		return 'package_get_error_packageinfo_corrupt';

	$packageInfo = $packageInfo->path('package-info[0]');

	$package = $packageInfo->to_array();
	$package = htmlspecialchars__recursive($package);
	$package['xml'] = $packageInfo;
	$package['filename'] = $gzfilename;

	// Don't want to mess with code...
	$types = array('install', 'uninstall', 'upgrade');
	foreach ($types as $type)
	{
		if (isset($package[$type]['code']))
		{
			$package[$type]['code'] = un_htmlspecialchars($package[$type]['code']);
		}
	}

	if (!isset($package['type']))
		$package['type'] = 'modification';

	return $package;
}

/**
 * Create a chmod control for chmoding files.
 *
 * @param array $chmodFiles Which files to chmod
 * @param array $chmodOptions Options for chmod
 * @param bool $restore_write_status Whether to restore write status
 * @return array An array of file info
 */
function create_chmod_control($chmodFiles = array(), $chmodOptions = array(), $restore_write_status = false)
{
	global $context, $modSettings, $package_ftp, $boarddir, $txt, $sourcedir, $scripturl;

	// If we're restoring the status of existing files prepare the data.
	if ($restore_write_status && isset($_SESSION['pack_ftp']) && !empty($_SESSION['pack_ftp']['original_perms']))
	{
		/**
		 * Get a listing of files that will need to be set back to the original state
		 *
		 * @param null $dummy1
		 * @param null $dummy2
		 * @param null $dummy3
		 * @param bool $do_change
		 * @return array An array of info about the files that need to be restored back to their original state
		 */
		function list_restoreFiles($dummy1, $dummy2, $dummy3, $do_change)
		{
			global $txt;

			$restore_files = array();
			foreach ($_SESSION['pack_ftp']['original_perms'] as $file => $perms)
			{
				// Check the file still exists, and the permissions were indeed different than now.
				$file_permissions = @fileperms($file);
				if (!file_exists($file) || $file_permissions == $perms)
				{
					unset($_SESSION['pack_ftp']['original_perms'][$file]);
					continue;
				}

				// Are we wanting to change the permission?
				if ($do_change && isset($_POST['restore_files']) && in_array($file, $_POST['restore_files']))
				{
					// Use FTP if we have it.
					// @todo where does $package_ftp get set?
					if (!empty($package_ftp))
					{
						$ftp_file = strtr($file, array($_SESSION['pack_ftp']['root'] => ''));
						$package_ftp->chmod($ftp_file, $perms);
					}
					else
						smf_chmod($file, $perms);

					$new_permissions = @fileperms($file);
					$result = $new_permissions == $perms ? 'success' : 'failure';
					unset($_SESSION['pack_ftp']['original_perms'][$file]);
				}
				elseif ($do_change)
				{
					$new_permissions = '';
					$result = 'skipped';
					unset($_SESSION['pack_ftp']['original_perms'][$file]);
				}

				// Record the results!
				$restore_files[] = array(
					'path' => $file,
					'old_perms_raw' => $perms,
					'old_perms' => substr(sprintf('%o', $perms), -4),
					'cur_perms' => substr(sprintf('%o', $file_permissions), -4),
					'new_perms' => isset($new_permissions) ? substr(sprintf('%o', $new_permissions), -4) : '',
					'result' => isset($result) ? $result : '',
					'writable_message' => '<span style="color: ' . (@is_writable($file) ? 'green' : 'red') . '">' . (@is_writable($file) ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']) . '</span>',
				);
			}

			return $restore_files;
		}

		$listOptions = array(
			'id' => 'restore_file_permissions',
			'title' => $txt['package_restore_permissions'],
			'get_items' => array(
				'function' => 'list_restoreFiles',
				'params' => array(
					!empty($_POST['restore_perms']),
				),
			),
			'columns' => array(
				'path' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_filename'],
					),
					'data' => array(
						'db' => 'path',
						'class' => 'smalltext',
					),
				),
				'old_perms' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_orig_status'],
					),
					'data' => array(
						'db' => 'old_perms',
						'class' => 'smalltext',
					),
				),
				'cur_perms' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_cur_status'],
					),
					'data' => array(
						'function' => function($rowData) use ($txt)
						{
							$formatTxt = $rowData['result'] == '' || $rowData['result'] == 'skipped' ? $txt['package_restore_permissions_pre_change'] : $txt['package_restore_permissions_post_change'];
							return sprintf($formatTxt, $rowData['cur_perms'], $rowData['new_perms'], $rowData['writable_message']);
						},
						'class' => 'smalltext',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="restore_files[]" value="%1$s">',
							'params' => array(
								'path' => false,
							),
						),
						'class' => 'centercol',
					),
				),
				'result' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_result'],
					),
					'data' => array(
						'function' => function($rowData) use ($txt)
						{
							return $txt['package_restore_permissions_action_' . $rowData['result']];
						},
						'class' => 'smalltext',
					),
				),
			),
			'form' => array(
				'href' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : $scripturl . '?action=admin;area=packages;sa=perms;restore;' . $context['session_var'] . '=' . $context['session_id'],
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="restore_perms" value="' . $txt['package_restore_permissions_restore'] . '" class="button">',
					'class' => 'titlebg',
				),
				array(
					'position' => 'after_title',
					'value' => '<span class="smalltext">' . $txt['package_restore_permissions_desc'] . '</span>',
					'class' => 'windowbg',
				),
			),
		);

		// Work out what columns and the like to show.
		if (!empty($_POST['restore_perms']))
		{
			$listOptions['additional_rows'][1]['value'] = sprintf($txt['package_restore_permissions_action_done'], $scripturl . '?action=admin;area=packages;sa=perms;' . $context['session_var'] . '=' . $context['session_id']);
			unset($listOptions['columns']['check'], $listOptions['form'], $listOptions['additional_rows'][0]);

			$context['sub_template'] = 'show_list';
			$context['default_list'] = 'restore_file_permissions';
		}
		else
		{
			unset($listOptions['columns']['result']);
		}

		// Create the list for display.
		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		// If we just restored permissions then whereever we are, we are now done and dusted.
		if (!empty($_POST['restore_perms']))
			obExit();
	}
	// Otherwise, it's entirely irrelevant?
	elseif ($restore_write_status)
		return true;

	// This is where we report what we got up to.
	$return_data = array(
		'files' => array(
			'writable' => array(),
			'notwritable' => array(),
		),
	);

	// If we have some FTP information already, then let's assume it was required and try to get ourselves connected.
	if (!empty($_SESSION['pack_ftp']['connected']))
	{
		// Load the file containing the ftp_connection class.
		require_once($sourcedir . '/Class-Package.php');

		$package_ftp = new ftp_connection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], package_crypt($_SESSION['pack_ftp']['password']));
	}

	// Just got a submission did we?
	if (empty($package_ftp) && isset($_POST['ftp_username']))
	{
		require_once($sourcedir . '/Class-Package.php');
		$ftp = new ftp_connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

		// We're connected, jolly good!
		if ($ftp->error === false)
		{
			// Common mistake, so let's try to remedy it...
			if (!$ftp->chdir($_POST['ftp_path']))
			{
				$ftp_error = $ftp->last_message;
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
			}

			if (!in_array($_POST['ftp_path'], array('', '/')))
			{
				$ftp_root = strtr($boarddir, array($_POST['ftp_path'] => ''));
				if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || substr($_POST['ftp_path'], 0, 1) == '/'))
					$ftp_root = substr($ftp_root, 0, -1);
			}
			else
				$ftp_root = $boarddir;

			$_SESSION['pack_ftp'] = array(
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => package_crypt($_POST['ftp_password']),
				'path' => $_POST['ftp_path'],
				'root' => $ftp_root,
				'connected' => true,
			);

			if (!isset($modSettings['package_path']) || $modSettings['package_path'] != $_POST['ftp_path'])
				updateSettings(array('package_path' => $_POST['ftp_path']));

			// This is now the primary connection.
			$package_ftp = $ftp;
		}
	}

	// Now try to simply make the files writable, with whatever we might have.
	if (!empty($chmodFiles))
	{
		foreach ($chmodFiles as $k => $file)
		{
			// Sometimes this can somehow happen maybe?
			if (empty($file))
				unset($chmodFiles[$k]);
			// Already writable?
			elseif (@is_writable($file))
				$return_data['files']['writable'][] = $file;
			else
			{
				// Now try to change that.
				$return_data['files'][package_chmod($file, 'writable', true) ? 'writable' : 'notwritable'][] = $file;
			}
		}
	}

	// Have we still got nasty files which ain't writable? Dear me we need more FTP good sir.
	if (empty($package_ftp) && (!empty($return_data['files']['notwritable']) || !empty($chmodOptions['force_find_error'])))
	{
		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
			{
				require_once($sourcedir . '/Class-Package.php');
				$ftp = new ftp_connection(null);
			}
			elseif ($ftp->error !== false && !isset($ftp_error))
				$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path($boarddir);

			if ($found_path)
				$_POST['ftp_path'] = $detect_path;
			elseif (!isset($_POST['ftp_path']))
				$_POST['ftp_path'] = isset($modSettings['package_path']) ? $modSettings['package_path'] : $detect_path;

			if (!isset($_POST['ftp_username']))
				$_POST['ftp_username'] = $username;
		}

		$context['package_ftp'] = array(
			'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'),
			'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'),
			'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''),
			'path' => $_POST['ftp_path'],
			'error' => empty($ftp_error) ? null : $ftp_error,
			'destination' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : '',
		);

		// Which files failed?
		if (!isset($context['notwritable_files']))
			$context['notwritable_files'] = array();
		$context['notwritable_files'] = array_merge($context['notwritable_files'], $return_data['files']['notwritable']);

		// Sent here to die?
		if (!empty($chmodOptions['crash_on_error']))
		{
			$context['page_title'] = $txt['package_ftp_necessary'];
			$context['sub_template'] = 'ftp_required';
			obExit();
		}
	}

	return $return_data;
}

/**
 * Use FTP functions to work with a package download/install
 *
 * @param string $destination_url The destination URL
 * @param null|array $files The files to CHMOD
 * @param bool $return Whether to return an array of file info if there's an error
 * @return array An array of file info
 */
function packageRequireFTP($destination_url, $files = null, $return = false)
{
	global $context, $modSettings, $package_ftp, $boarddir, $txt, $sourcedir;

	// Try to make them writable the manual way.
	if ($files !== null)
	{
		foreach ($files as $k => $file)
		{
			// If this file doesn't exist, then we actually want to look at the directory, no?
			if (!file_exists($file))
				$file = dirname($file);

			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!@is_writable($file))
				smf_chmod($file, 0755);
			if (!@is_writable($file))
				smf_chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				smf_chmod($file, 0755);
			if (!@is_writable(dirname($file)))
				smf_chmod($file, 0777);

			$fp = is_dir($file) ? @opendir($file) : @fopen($file, 'rb');
			if (@is_writable($file) && $fp)
			{
				unset($files[$k]);
				if (!is_dir($file))
					fclose($fp);
				else
					closedir($fp);
			}
		}

		// No FTP required!
		if (empty($files))
			return array();
	}

	// They've opted to not use FTP, and try anyway.
	if (isset($_SESSION['pack_ftp']) && $_SESSION['pack_ftp'] == false)
	{
		if ($files === null)
			return array();

		foreach ($files as $k => $file)
		{
			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($file))
			{
				mktree(dirname($file), 0755);
				@touch($file);
				smf_chmod($file, 0755);
			}

			if (!@is_writable($file))
				smf_chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				smf_chmod(dirname($file), 0777);

			if (@is_writable($file))
				unset($files[$k]);
		}

		return $files;
	}
	elseif (isset($_SESSION['pack_ftp']))
	{
		// Load the file containing the ftp_connection class.
		require_once($sourcedir . '/Class-Package.php');

		$package_ftp = new ftp_connection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], package_crypt($_SESSION['pack_ftp']['password']));

		if ($files === null)
			return array();

		foreach ($files as $k => $file)
		{
			$ftp_file = strtr($file, array($_SESSION['pack_ftp']['root'] => ''));

			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($file))
			{
				mktree(dirname($file), 0755);
				$package_ftp->create_file($ftp_file);
				$package_ftp->chmod($ftp_file, 0755);
			}

			if (!@is_writable($file))
				$package_ftp->chmod($ftp_file, 0777);
			if (!@is_writable(dirname($file)))
				$package_ftp->chmod(dirname($ftp_file), 0777);

			if (@is_writable($file))
				unset($files[$k]);
		}

		return $files;
	}

	if (isset($_POST['ftp_none']))
	{
		$_SESSION['pack_ftp'] = false;

		$files = packageRequireFTP($destination_url, $files, $return);
		return $files;
	}
	elseif (isset($_POST['ftp_username']))
	{
		require_once($sourcedir . '/Class-Package.php');
		$ftp = new ftp_connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

		if ($ftp->error === false)
		{
			// Common mistake, so let's try to remedy it...
			if (!$ftp->chdir($_POST['ftp_path']))
			{
				$ftp_error = $ftp->last_message;
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
			}
		}
	}

	if (!isset($ftp) || $ftp->error !== false)
	{
		if (!isset($ftp))
		{
			require_once($sourcedir . '/Class-Package.php');
			$ftp = new ftp_connection(null);
		}
		elseif ($ftp->error !== false && !isset($ftp_error))
			$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;

		list ($username, $detect_path, $found_path) = $ftp->detect_path($boarddir);

		if ($found_path)
			$_POST['ftp_path'] = $detect_path;
		elseif (!isset($_POST['ftp_path']))
			$_POST['ftp_path'] = isset($modSettings['package_path']) ? $modSettings['package_path'] : $detect_path;

		if (!isset($_POST['ftp_username']))
			$_POST['ftp_username'] = $username;

		$context['package_ftp'] = array(
			'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'),
			'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'),
			'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''),
			'path' => $_POST['ftp_path'],
			'error' => empty($ftp_error) ? null : $ftp_error,
			'destination' => $destination_url,
		);

		// If we're returning dump out here.
		if ($return)
			return $files;

		$context['page_title'] = $txt['package_ftp_necessary'];
		$context['sub_template'] = 'ftp_required';
		obExit();
	}
	else
	{
		if (!in_array($_POST['ftp_path'], array('', '/')))
		{
			$ftp_root = strtr($boarddir, array($_POST['ftp_path'] => ''));
			if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || $_POST['ftp_path'][0] == '/'))
				$ftp_root = substr($ftp_root, 0, -1);
		}
		else
			$ftp_root = $boarddir;

		$_SESSION['pack_ftp'] = array(
			'server' => $_POST['ftp_server'],
			'port' => $_POST['ftp_port'],
			'username' => $_POST['ftp_username'],
			'password' => package_crypt($_POST['ftp_password']),
			'path' => $_POST['ftp_path'],
			'root' => $ftp_root,
		);

		if (!isset($modSettings['package_path']) || $modSettings['package_path'] != $_POST['ftp_path'])
			updateSettings(array('package_path' => $_POST['ftp_path']));

		$files = packageRequireFTP($destination_url, $files, $return);
	}

	return $files;
}

/**
 * Parses the actions in package-info.xml file from packages.
 *
 * - package should be an xmlArray with package-info as its base.
 * - testing_only should be true if the package should not actually be applied.
 * - method can be upgrade, install, or uninstall.  Its default is install.
 * - previous_version should be set to the previous installed version of this package, if any.
 * - does not handle failure terribly well; testing first is always better.
 *
 * @param xmlArray &$packageXML The info from the package-info file
 * @param bool $testing_only Whether we're only testing
 * @param string $method The method ('install', 'upgrade', or 'uninstall')
 * @param string $previous_version The previous version of the mod, if method is 'upgrade'
 * @return array An array of those changes made.
 */
function parsePackageInfo(&$packageXML, $testing_only = true, $method = 'install', $previous_version = '')
{
	global $packagesdir, $context, $temp_path, $language, $smcFunc;

	// Mayday!  That action doesn't exist!!
	if (empty($packageXML) || !$packageXML->exists($method))
		return array();

	// We haven't found the package script yet...
	$script = false;
	$the_version = SMF_VERSION;

	// Emulation support...
	if (!empty($_SESSION['version_emulate']))
		$the_version = $_SESSION['version_emulate'];

	// Single package emulation
	if (!empty($_REQUEST['ve']) && !empty($_REQUEST['package']))
	{
		$the_version = $_REQUEST['ve'];
		$_SESSION['single_version_emulate'][$_REQUEST['package']] = $the_version;
	}
	if (!empty($_REQUEST['package']) && (!empty($_SESSION['single_version_emulate'][$_REQUEST['package']])))
		$the_version = $_SESSION['single_version_emulate'][$_REQUEST['package']];

	// Get all the versions of this method and find the right one.
	$these_methods = $packageXML->set($method);
	foreach ($these_methods as $this_method)
	{
		// They specified certain versions this part is for.
		if ($this_method->exists('@for'))
		{
			// Don't keep going if this won't work for this version of SMF.
			if (!matchPackageVersion($the_version, $this_method->fetch('@for')))
				continue;
		}

		// Upgrades may go from a certain old version of the mod.
		if ($method == 'upgrade' && $this_method->exists('@from'))
		{
			// Well, this is for the wrong old version...
			if (!matchPackageVersion($previous_version, $this_method->fetch('@from')))
				continue;
		}

		// We've found it!
		$script = $this_method;
		break;
	}

	// Bad news, a matching script wasn't found!
	if (!($script instanceof xmlArray))
		return array();

	// Find all the actions in this method - in theory, these should only be allowed actions. (* means all.)
	$actions = $script->set('*');
	$return = array();

	$temp_auto = 0;
	$temp_path = $packagesdir . '/temp/' . (isset($context['base_path']) ? $context['base_path'] : '');

	$context['readmes'] = array();
	$context['licences'] = array();

	// This is the testing phase... nothing shall be done yet.
	foreach ($actions as $action)
	{
		$actionType = $action->name();

		if (in_array($actionType, array('readme', 'code', 'database', 'modification', 'redirect', 'license')))
		{
			// Allow for translated readme and license files.
			if ($actionType == 'readme' || $actionType == 'license')
			{
				$type = $actionType . 's';
				if ($action->exists('@lang'))
				{
					// Auto-select the language based on either request variable or current language.
					if ((isset($_REQUEST['readme']) && $action->fetch('@lang') == $_REQUEST['readme']) || (isset($_REQUEST['license']) && $action->fetch('@lang') == $_REQUEST['license']) || (!isset($_REQUEST['readme']) && $action->fetch('@lang') == $language) || (!isset($_REQUEST['license']) && $action->fetch('@lang') == $language))
					{
						// In case the user put the blocks in the wrong order.
						if (isset($context[$type]['selected']) && $context[$type]['selected'] == 'default')
							$context[$type][] = 'default';

						$context[$type]['selected'] = $smcFunc['htmlspecialchars']($action->fetch('@lang'));
					}
					else
					{
						// We don't want this now, but we'll allow the user to select to read it.
						$context[$type][] = $smcFunc['htmlspecialchars']($action->fetch('@lang'));
						continue;
					}
				}
				// Fallback when we have no lang parameter.
				else
				{
					// Already selected one for use?
					if (isset($context[$type]['selected']))
					{
						$context[$type][] = 'default';
						continue;
					}
					else
						$context[$type]['selected'] = 'default';
				}
			}

			// @todo Make sure the file actually exists?  Might not work when testing?
			if ($action->exists('@type') && $action->fetch('@type') == 'inline')
			{
				$filename = $temp_path . '$auto_' . $temp_auto++ . (in_array($actionType, array('readme', 'redirect', 'license')) ? '.txt' : ($actionType == 'code' || $actionType == 'database' ? '.php' : '.mod'));
				package_put_contents($filename, $action->fetch('.'));
				$filename = strtr($filename, array($temp_path => ''));
			}
			else
				$filename = $action->fetch('.');

			$return[] = array(
				'type' => $actionType,
				'filename' => $filename,
				'description' => '',
				'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true',
				'boardmod' => $action->exists('@format') && $action->fetch('@format') == 'boardmod',
				'redirect_url' => $action->exists('@url') ? $action->fetch('@url') : '',
				'redirect_timeout' => $action->exists('@timeout') ? (int) $action->fetch('@timeout') : '',
				'parse_bbc' => $action->exists('@parsebbc') && $action->fetch('@parsebbc') == 'true',
				'language' => (($actionType == 'readme' || $actionType == 'license') && $action->exists('@lang') && $action->fetch('@lang') == $language) ? $language : '',
			);

			continue;
		}
		elseif ($actionType == 'hook')
		{
			$return[] = array(
				'type' => $actionType,
				'function' => $action->exists('@function') ? $action->fetch('@function') : '',
				'hook' => $action->exists('@hook') ? $action->fetch('@hook') : $action->fetch('.'),
				'include_file' => $action->exists('@file') ? $action->fetch('@file') : '',
				'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true' ? true : false,
				'object' => $action->exists('@object') && $action->fetch('@object') == 'true' ? true : false,
				'description' => '',
			);
			continue;
		}
		elseif ($actionType == 'credits')
		{
			// quick check of any supplied url
			$url = $action->exists('@url') ? $action->fetch('@url') : '';
			if (strlen(trim($url)) > 0 && substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://')
			{
				$url = 'http://' . $url;
				if (strlen($url) < 8 || (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://'))
					$url = '';
			}

			$return[] = array(
				'type' => $actionType,
				'url' => $url,
				'license' => $action->exists('@license') ? $action->fetch('@license') : '',
				'licenseurl' => $action->exists('@licenseurl') ? $action->fetch('@licenseurl') : '',
				'copyright' => $action->exists('@copyright') ? $action->fetch('@copyright') : '',
				'title' => $action->fetch('.'),
			);
			continue;
		}
		elseif ($actionType == 'requires')
		{
			$return[] = array(
				'type' => $actionType,
				'id' => $action->exists('@id') ? $action->fetch('@id') : '',
				'version' => $action->exists('@version') ? $action->fetch('@version') : $action->fetch('.'),
				'description' => '',
			);
			continue;
		}
		elseif ($actionType == 'error')
		{
			$return[] = array(
				'type' => 'error',
			);
		}
		elseif (in_array($actionType, array('require-file', 'remove-file', 'require-dir', 'remove-dir', 'move-file', 'move-dir', 'create-file', 'create-dir')))
		{
			$this_action = &$return[];
			$this_action = array(
				'type' => $actionType,
				'filename' => $action->fetch('@name'),
				'description' => $action->fetch('.')
			);

			// If there is a destination, make sure it makes sense.
			if (substr($actionType, 0, 6) != 'remove')
			{
				$this_action['unparsed_destination'] = $action->fetch('@destination');
				$this_action['destination'] = parse_path($action->fetch('@destination')) . '/' . basename($this_action['filename']);
			}
			else
			{
				$this_action['unparsed_filename'] = $this_action['filename'];
				$this_action['filename'] = parse_path($this_action['filename']);
			}

			// If we're moving or requiring (copying) a file.
			if (substr($actionType, 0, 4) == 'move' || substr($actionType, 0, 7) == 'require')
			{
				if ($action->exists('@from'))
					$this_action['source'] = parse_path($action->fetch('@from'));
				else
					$this_action['source'] = $temp_path . $this_action['filename'];
			}

			// Check if these things can be done. (chmod's etc.)
			if ($actionType == 'create-dir')
			{
				if (!mktree($this_action['destination'], false))
				{
					$temp = $this_action['destination'];
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}
			}
			elseif ($actionType == 'create-file')
			{
				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'require-dir')
			{
				if (!mktree($this_action['destination'], false))
				{
					$temp = $this_action['destination'];
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}
			}
			elseif ($actionType == 'require-file')
			{
				if ($action->exists('@theme'))
					$this_action['theme_action'] = $action->fetch('@theme');

				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'move-dir' || $actionType == 'move-file')
			{
				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'remove-dir')
			{
				if (!is_writable($this_action['filename']) && file_exists($this_action['filename']))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['filename']
					);
			}
			elseif ($actionType == 'remove-file')
			{
				if (!is_writable($this_action['filename']) && file_exists($this_action['filename']))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['filename']
					);
			}
		}
		else
		{
			$return[] = array(
				'type' => 'error',
				'error_msg' => 'unknown_action',
				'error_var' => $actionType
			);
		}
	}

	// Only testing - just return a list of things to be done.
	if ($testing_only)
		return $return;

	umask(0);

	$failure = false;
	$not_done = array(array('type' => '!'));
	foreach ($return as $action)
	{
		if (in_array($action['type'], array('modification', 'code', 'database', 'redirect', 'hook', 'credits')))
			$not_done[] = $action;

		if ($action['type'] == 'create-dir')
		{
			if (!mktree($action['destination'], 0755) || !is_writable($action['destination']))
				$failure |= !mktree($action['destination'], 0777);
		}
		elseif ($action['type'] == 'create-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			// Create an empty file.
			package_put_contents($action['destination'], package_get_contents($action['source']), $testing_only);

			if (!file_exists($action['destination']))
				$failure = true;
		}
		elseif ($action['type'] == 'require-dir')
		{
			copytree($action['source'], $action['destination']);
			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['destination']]))
				foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
					copytree($action['source'], $theme_destination);
		}
		elseif ($action['type'] == 'require-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			package_put_contents($action['destination'], package_get_contents($action['source']), $testing_only);

			$failure |= !copy($action['source'], $action['destination']);

			// Any other theme files?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['destination']]))
				foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
				{
					if (!mktree(dirname($theme_destination), 0755) || !is_writable(dirname($theme_destination)))
						$failure |= !mktree(dirname($theme_destination), 0777);

					package_put_contents($theme_destination, package_get_contents($action['source']), $testing_only);

					$failure |= !copy($action['source'], $theme_destination);
				}
		}
		elseif ($action['type'] == 'move-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			$failure |= !rename($action['source'], $action['destination']);
		}
		elseif ($action['type'] == 'move-dir')
		{
			if (!mktree($action['destination'], 0755) || !is_writable($action['destination']))
				$failure |= !mktree($action['destination'], 0777);

			$failure |= !rename($action['source'], $action['destination']);
		}
		elseif ($action['type'] == 'remove-dir')
		{
			deltree($action['filename']);

			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['filename']]))
				foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
					deltree($theme_destination);
		}
		elseif ($action['type'] == 'remove-file')
		{
			// Make sure the file exists before deleting it.
			if (file_exists($action['filename']))
			{
				package_chmod($action['filename']);
				$failure |= !unlink($action['filename']);
			}
			// The file that was supposed to be deleted couldn't be found.
			else
				$failure = true;

			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['filename']]))
				foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
					if (file_exists($theme_destination))
						$failure |= !unlink($theme_destination);
					else
						$failure = true;
		}
	}

	return $not_done;
}

/**
 * Checks if version matches any of the versions in versions.
 * - supports comma separated version numbers, with or without whitespace.
 * - supports lower and upper bounds. (1.0-1.2)
 * - returns true if the version matched.
 *
 * @param string $versions The SMF versions
 * @param boolean $reset Whether to reset $near_version
 * @param string $the_version
 * @return string|bool Highest install value string or false
 */
function matchHighestPackageVersion($versions, $reset = false, $the_version)
{
	static $near_version = 0;

	if ($reset)
		$near_version = 0;

	// Normalize the $versions while we remove our previous Doh!
	$versions = explode(',', str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($versions)));

	// Loop through each version, save the highest we can find
	foreach ($versions as $for)
	{
		// Adjust for those wild cards
		if (strpos($for, '*') !== false)
			$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);

		// If we have a range, grab the lower value, done this way so it looks normal-er to the user e.g. 2.0 vs 2.0.99
		if (strpos($for, '-') !== false)
			list ($for, $higher) = explode('-', $for);

		// Do the compare, if the for is greater, than what we have but not greater than what we are running .....
		if (compareVersions($near_version, $for) === -1 && compareVersions($for, $the_version) !== 1)
			$near_version = $for;
	}

	return !empty($near_version) ? $near_version : false;
}

/**
 * Checks if the forum version matches any of the available versions from the package install xml.
 * - supports comma separated version numbers, with or without whitespace.
 * - supports lower and upper bounds. (1.0-1.2)
 * - returns true if the version matched.
 *
 * @param string $version The forum version
 * @param string $versions The versions that this package will install on
 * @return bool Whether the version matched
 */
function matchPackageVersion($version, $versions)
{
	// Make sure everything is lowercase and clean of spaces and unpleasant history.
	$version = str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($version));
	$versions = explode(',', str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($versions)));

	// Perhaps we do accept anything?
	if (in_array('all', $versions))
		return true;

	// Loop through each version.
	foreach ($versions as $for)
	{
		// Wild card spotted?
		if (strpos($for, '*') !== false)
			$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);

		// Do we have a range?
		if (strpos($for, '-') !== false)
		{
			list ($lower, $upper) = explode('-', $for);

			// Compare the version against lower and upper bounds.
			if (compareVersions($version, $lower) > -1 && compareVersions($version, $upper) < 1)
				return true;
		}
		// Otherwise check if they are equal...
		elseif (compareVersions($version, $for) === 0)
			return true;
	}

	return false;
}

/**
 * Compares two versions and determines if one is newer, older or the same, returns
 * - (-1) if version1 is lower than version2
 * - (0) if version1 is equal to version2
 * - (1) if version1 is higher than version2
 *
 * @param string $version1 The first version
 * @param string $version2 The second version
 * @return int -1 if version2 is greater than version1, 0 if they're equal, 1 if version1 is greater than version2
 */
function compareVersions($version1, $version2)
{
	static $categories;

	$versions = array();
	foreach (array(1 => $version1, $version2) as $id => $version)
	{
		// Clean the version and extract the version parts.
		$clean = str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($version));
		preg_match('~(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)~', $clean, $parts);

		// Build an array of parts.
		$versions[$id] = array(
			'major' => !empty($parts[1]) ? (int) $parts[1] : 0,
			'minor' => !empty($parts[2]) ? (int) $parts[2] : 0,
			'patch' => !empty($parts[3]) ? (int) $parts[3] : 0,
			'type' => empty($parts[4]) ? 'stable' : $parts[4],
			'type_major' => !empty($parts[5]) ? (int) $parts[5] : 0,
			'type_minor' => !empty($parts[6]) ? (int) $parts[6] : 0,
			'dev' => !empty($parts[7]),
		);
	}

	// Are they the same, perhaps?
	if ($versions[1] === $versions[2])
		return 0;

	// Get version numbering categories...
	if (!isset($categories))
		$categories = array_keys($versions[1]);

	// Loop through each category.
	foreach ($categories as $category)
	{
		// Is there something for us to calculate?
		if ($versions[1][$category] !== $versions[2][$category])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if ($category == 'type')
				return $versions[1][$category] > $versions[2][$category] ? ($versions[1]['dev'] ? -1 : 1) : ($versions[2]['dev'] ? 1 : -1);
			elseif ($category == 'dev')
				return $versions[1]['dev'] ? ($versions[2]['type'] == 'stable' ? -1 : 0) : ($versions[1]['type'] == 'stable' ? 1 : 0);
			// Otherwise a simple comparison.
			else
				return $versions[1][$category] > $versions[2][$category] ? 1 : -1;
		}
	}

	// They are the same!
	return 0;
}

/**
 * Parses special identifiers out of the specified path.
 *
 * @param string $path The path
 * @return string The parsed path
 */
function parse_path($path)
{
	global $modSettings, $boarddir, $sourcedir, $settings, $temp_path;

	$dirs = array(
		'\\' => '/',
		'$boarddir' => $boarddir,
		'$sourcedir' => $sourcedir,
		'$avatardir' => $modSettings['avatar_directory'],
		'$avatars_dir' => $modSettings['avatar_directory'],
		'$themedir' => $settings['default_theme_dir'],
		'$imagesdir' => $settings['default_theme_dir'] . '/' . basename($settings['default_images_url']),
		'$themes_dir' => $boarddir . '/Themes',
		'$languagedir' => $settings['default_theme_dir'] . '/languages',
		'$languages_dir' => $settings['default_theme_dir'] . '/languages',
		'$smileysdir' => $modSettings['smileys_dir'],
		'$smileys_dir' => $modSettings['smileys_dir'],
	);

	// do we parse in a package directory?
	if (!empty($temp_path))
		$dirs['$package'] = $temp_path;

	if (strlen($path) == 0)
		trigger_error('parse_path(): There should never be an empty filename', E_USER_ERROR);

	return strtr($path, $dirs);
}

/**
 * Deletes a directory, and all the files and direcories inside it.
 * requires access to delete these files.
 *
 * @param string $dir A directory
 * @param bool $delete_dir If false, only deletes everything inside the directory but not the directory itself
 */
function deltree($dir, $delete_dir = true)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp;

	if (!file_exists($dir))
		return;

	$current_dir = @opendir($dir);
	if ($current_dir == false)
	{
		if ($delete_dir && isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_dir($dir))
				$package_ftp->chmod($ftp_file, 0777);
			$package_ftp->unlink($ftp_file);
		}

		return;
	}

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		if (is_dir($dir . '/' . $entryname))
			deltree($dir . '/' . $entryname);
		else
		{
			// Here, 755 doesn't really matter since we're deleting it anyway.
			if (isset($package_ftp))
			{
				$ftp_file = strtr($dir . '/' . $entryname, array($_SESSION['pack_ftp']['root'] => ''));

				if (!is_writable($dir . '/' . $entryname))
					$package_ftp->chmod($ftp_file, 0777);
				$package_ftp->unlink($ftp_file);
			}
			else
			{
				if (!is_writable($dir . '/' . $entryname))
					smf_chmod($dir . '/' . $entryname, 0777);
				unlink($dir . '/' . $entryname);
			}
		}
	}

	closedir($current_dir);

	if ($delete_dir)
	{
		if (isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_writable($dir . '/' . $entryname))
				$package_ftp->chmod($ftp_file, 0777);
			$package_ftp->unlink($ftp_file);
		}
		else
		{
			if (!is_writable($dir))
				smf_chmod($dir, 0777);
			@rmdir($dir);
		}
	}
}

/**
 * Creates the specified tree structure with the mode specified.
 * creates every directory in path until it finds one that already exists.
 *
 * @param string $strPath The path
 * @param int $mode The permission mode for CHMOD (0666, etc.)
 * @return bool True if successful, false otherwise
 */
function mktree($strPath, $mode)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp;

	if (is_dir($strPath))
	{
		if (!is_writable($strPath) && $mode !== false)
		{
			if (isset($package_ftp))
				$package_ftp->chmod(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')), $mode);
			else
				smf_chmod($strPath, $mode);
		}

		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return is_writable($strPath);
		}
		else
			return false;
	}
	// Is this an invalid path and/or we can't make the directory?
	if ($strPath == dirname($strPath) || !mktree(dirname($strPath), $mode))
		return false;

	if (!is_writable(dirname($strPath)) && $mode !== false)
	{
		if (isset($package_ftp))
			$package_ftp->chmod(dirname(strtr($strPath, array($_SESSION['pack_ftp']['root'] => ''))), $mode);
		else
			smf_chmod(dirname($strPath), $mode);
	}

	if ($mode !== false && isset($package_ftp))
		return $package_ftp->create_dir(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')));
	elseif ($mode === false)
	{
		$test = @opendir(dirname($strPath));
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
	else
	{
		@mkdir($strPath, $mode);
		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
}

/**
 * Copies one directory structure over to another.
 * requires the destination to be writable.
 *
 * @param string $source The directory to copy
 * @param string $destination The directory to copy $source to
 */
function copytree($source, $destination)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp;

	if (!file_exists($destination) || !is_writable($destination))
		mktree($destination, 0755);
	if (!is_writable($destination))
		mktree($destination, 0777);

	$current_dir = opendir($source);
	if ($current_dir == false)
		return;

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		if (isset($package_ftp))
			$ftp_file = strtr($destination . '/' . $entryname, array($_SESSION['pack_ftp']['root'] => ''));

		if (is_file($source . '/' . $entryname))
		{
			if (isset($package_ftp) && !file_exists($destination . '/' . $entryname))
				$package_ftp->create_file($ftp_file);
			elseif (!file_exists($destination . '/' . $entryname))
				@touch($destination . '/' . $entryname);
		}

		package_chmod($destination . '/' . $entryname);

		if (is_dir($source . '/' . $entryname))
			copytree($source . '/' . $entryname, $destination . '/' . $entryname);
		elseif (file_exists($destination . '/' . $entryname))
			package_put_contents($destination . '/' . $entryname, package_get_contents($source . '/' . $entryname));
		else
			copy($source . '/' . $entryname, $destination . '/' . $entryname);
	}

	closedir($current_dir);
}

/**
 * Create a tree listing for a given directory path
 *
 * @param string $path The path
 * @param string $sub_path The sub-path
 * @return array An array of information about the files at the specified path/subpath
 */
function listtree($path, $sub_path = '')
{
	$data = array();

	$dir = @dir($path . $sub_path);
	if (!$dir)
		return array();
	while ($entry = $dir->read())
	{
		if ($entry == '.' || $entry == '..')
			continue;

		if (is_dir($path . $sub_path . '/' . $entry))
			$data = array_merge($data, listtree($path, $sub_path . '/' . $entry));
		else
			$data[] = array(
				'filename' => $sub_path == '' ? $entry : $sub_path . '/' . $entry,
				'size' => filesize($path . $sub_path . '/' . $entry),
				'skipped' => false,
			);
	}
	$dir->close();

	return $data;
}

/**
 * Parses a xml-style modification file (file).
 *
 * @param string $file The modification file to parse
 * @param bool $testing Whether we're just doing a test
 * @param bool $undo If true, specifies that the modifications should be undone. Used when uninstalling. Doesn't work with regex.
 * @param array $theme_paths An array of information about custom themes to apply the changes to
 * @return array An array of those changes made.
 */
function parseModification($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $boarddir, $sourcedir, $txt, $modSettings;

	@set_time_limit(600);
	require_once($sourcedir . '/Class-Package.php');
	$xml = new xmlArray(strtr($file, array("\r" => '')));
	$actions = array();
	$everything_found = true;

	if (!$xml->exists('modification') || !$xml->exists('modification/file'))
	{
		$actions[] = array(
			'type' => 'error',
			'filename' => '-',
			'debug' => $txt['package_modification_malformed']
		);
		return $actions;
	}

	// Get the XML data.
	$files = $xml->set('modification/file');

	// Use this for holding all the template changes in this mod.
	$template_changes = array();
	// This is needed to hold the long paths, as they can vary...
	$long_changes = array();

	// First, we need to build the list of all the files likely to get changed.
	foreach ($files as $file)
	{
		// What is the filename we're currently on?
		$filename = parse_path(trim($file->fetch('@name')));

		// Now, we need to work out whether this is even a template file...
		foreach ($theme_paths as $id => $theme)
		{
			// If this filename is relative, if so take a guess at what it should be.
			$real_filename = $filename;
			if (strpos($filename, 'Themes') === 0)
				$real_filename = $boarddir . '/' . $filename;

			if (strpos($real_filename, $theme['theme_dir']) === 0)
			{
				$template_changes[$id][] = substr($real_filename, strlen($theme['theme_dir']) + 1);
				$long_changes[$id][] = $filename;
			}
		}
	}

	// Custom themes to add.
	$custom_themes_add = array();

	// If we have some template changes, we need to build a master link of what new ones are required for the custom themes.
	if (!empty($template_changes[1]))
	{
		foreach ($theme_paths as $id => $theme)
		{
			// Default is getting done anyway, so no need for involvement here.
			if ($id == 1)
				continue;

			// For every template, do we want it? Yea, no, maybe?
			foreach ($template_changes[1] as $index => $template_file)
			{
				// What, it exists and we haven't already got it?! Lordy, get it in!
				if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id]) || !in_array($template_file, $template_changes[$id])))
				{
					// Now let's add it to the "todo" list.
					$custom_themes_add[$long_changes[1][$index]][$id] = $theme['theme_dir'] . '/' . $template_file;
				}
			}
		}
	}

	foreach ($files as $file)
	{
		// This is the actual file referred to in the XML document...
		$files_to_change = array(
			1 => parse_path(trim($file->fetch('@name'))),
		);

		// Sometimes though, we have some additional files for other themes, if we have add them to the mix.
		if (isset($custom_themes_add[$files_to_change[1]]))
			$files_to_change += $custom_themes_add[$files_to_change[1]];

		// Now, loop through all the files we're changing, and, well, change them ;)
		foreach ($files_to_change as $theme => $working_file)
		{
			if ($working_file[0] != '/' && $working_file[1] != ':')
			{
				trigger_error('parseModification(): The filename \'' . $working_file . '\' is not a full path!', E_USER_WARNING);

				$working_file = $boarddir . '/' . $working_file;
			}

			// Doesn't exist - give an error or what?
			if (!file_exists($working_file) && (!$file->exists('@error') || !in_array(trim($file->fetch('@error')), array('ignore', 'skip'))))
			{
				$actions[] = array(
					'type' => 'missing',
					'filename' => $working_file,
					'debug' => $txt['package_modification_missing']
				);

				$everything_found = false;
				continue;
			}
			// Skip the file if it doesn't exist.
			elseif (!file_exists($working_file) && $file->exists('@error') && trim($file->fetch('@error')) == 'skip')
			{
				$actions[] = array(
					'type' => 'skipping',
					'filename' => $working_file,
				);
				continue;
			}
			// Okay, we're creating this file then...?
			elseif (!file_exists($working_file))
				$working_data = '';
			// Phew, it exists!  Load 'er up!
			else
				$working_data = str_replace("\r", '', package_get_contents($working_file));

			$actions[] = array(
				'type' => 'opened',
				'filename' => $working_file
			);

			$operations = $file->exists('operation') ? $file->set('operation') : array();
			foreach ($operations as $operation)
			{
				// Convert operation to an array.
				$actual_operation = array(
					'searches' => array(),
					'error' => $operation->exists('@error') && in_array(trim($operation->fetch('@error')), array('ignore', 'fatal', 'required')) ? trim($operation->fetch('@error')) : 'fatal',
				);

				// The 'add' parameter is used for all searches in this operation.
				$add = $operation->exists('add') ? $operation->fetch('add') : '';

				// Grab all search items of this operation (in most cases just 1).
				$searches = $operation->set('search');
				foreach ($searches as $i => $search)
					$actual_operation['searches'][] = array(
						'position' => $search->exists('@position') && in_array(trim($search->fetch('@position')), array('before', 'after', 'replace', 'end')) ? trim($search->fetch('@position')) : 'replace',
						'is_reg_exp' => $search->exists('@regexp') && trim($search->fetch('@regexp')) === 'true',
						'loose_whitespace' => $search->exists('@whitespace') && trim($search->fetch('@whitespace')) === 'loose',
						'search' => $search->fetch('.'),
						'add' => $add,
						'preg_search' => '',
						'preg_replace' => '',
					);

				// At least one search should be defined.
				if (empty($actual_operation['searches']))
				{
					$actions[] = array(
						'type' => 'failure',
						'filename' => $working_file,
						'search' => $search['search'],
						'is_custom' => $theme > 1 ? $theme : 0,
					);

					// Skip to the next operation.
					continue;
				}

				// Reverse the operations in case of undoing stuff.
				if ($undo)
				{
					foreach ($actual_operation['searches'] as $i => $search)
					{
						// Reverse modification of regular expressions are not allowed.
						if ($search['is_reg_exp'])
						{
							if ($actual_operation['error'] === 'fatal')
								$actions[] = array(
									'type' => 'failure',
									'filename' => $working_file,
									'search' => $search['search'],
									'is_custom' => $theme > 1 ? $theme : 0,
								);

							// Continue to the next operation.
							continue 2;
						}

						// The replacement is now the search subject...
						if ($search['position'] === 'replace' || $search['position'] === 'end')
							$actual_operation['searches'][$i]['search'] = $search['add'];
						else
						{
							// Reversing a before/after modification becomes a replacement.
							$actual_operation['searches'][$i]['position'] = 'replace';

							if ($search['position'] === 'before')
								$actual_operation['searches'][$i]['search'] .= $search['add'];
							elseif ($search['position'] === 'after')
								$actual_operation['searches'][$i]['search'] = $search['add'] . $search['search'];
						}

						// ...and the search subject is now the replacement.
						$actual_operation['searches'][$i]['add'] = $search['search'];
					}
				}

				// Sort the search list so the replaces come before the add before/after's.
				if (count($actual_operation['searches']) !== 1)
				{
					$replacements = array();

					foreach ($actual_operation['searches'] as $i => $search)
					{
						if ($search['position'] === 'replace')
						{
							$replacements[] = $search;
							unset($actual_operation['searches'][$i]);
						}
					}
					$actual_operation['searches'] = array_merge($replacements, $actual_operation['searches']);
				}

				// Create regular expression replacements from each search.
				foreach ($actual_operation['searches'] as $i => $search)
				{
					// Not much needed if the search subject is already a regexp.
					if ($search['is_reg_exp'])
						$actual_operation['searches'][$i]['preg_search'] = $search['search'];
					else
					{
						// Make the search subject fit into a regular expression.
						$actual_operation['searches'][$i]['preg_search'] = preg_quote($search['search'], '~');

						// Using 'loose', a random amount of tabs and spaces may be used.
						if ($search['loose_whitespace'])
							$actual_operation['searches'][$i]['preg_search'] = preg_replace('~[ \t]+~', '[ \t]+', $actual_operation['searches'][$i]['preg_search']);
					}

					// Shuzzup.  This is done so we can safely use a regular expression. ($0 is bad!!)
					$actual_operation['searches'][$i]['preg_replace'] = strtr($search['add'], array('$' => '[$PACK' . 'AGE1$]', '\\' => '[$PACK' . 'AGE2$]'));

					// Before, so the replacement comes after the search subject :P
					if ($search['position'] === 'before')
					{
						$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
						$actual_operation['searches'][$i]['preg_replace'] = '$1' . $actual_operation['searches'][$i]['preg_replace'];
					}

					// After, after what?
					elseif ($search['position'] === 'after')
					{
						$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
						$actual_operation['searches'][$i]['preg_replace'] .= '$1';
					}

					// Position the replacement at the end of the file (or just before the closing PHP tags).
					elseif ($search['position'] === 'end')
					{
						if ($undo)
						{
							$actual_operation['searches'][$i]['preg_replace'] = '';
						}
						else
						{
							$actual_operation['searches'][$i]['preg_search'] = '(\\n\\?\\>)?$';
							$actual_operation['searches'][$i]['preg_replace'] .= '$1';
						}
					}

					// Testing 1, 2, 3...
					$failed = preg_match('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $working_data) === 0;

					// Nope, search pattern not found.
					if ($failed && $actual_operation['error'] === 'fatal')
					{
						$actions[] = array(
							'type' => 'failure',
							'filename' => $working_file,
							'search' => $actual_operation['searches'][$i]['preg_search'],
							'search_original' => $actual_operation['searches'][$i]['search'],
							'replace_original' => $actual_operation['searches'][$i]['add'],
							'position' => $search['position'],
							'is_custom' => $theme > 1 ? $theme : 0,
							'failed' => $failed,
						);

						$everything_found = false;
						continue;
					}

					// Found, but in this case, that means failure!
					elseif (!$failed && $actual_operation['error'] === 'required')
					{
						$actions[] = array(
							'type' => 'failure',
							'filename' => $working_file,
							'search' => $actual_operation['searches'][$i]['preg_search'],
							'search_original' => $actual_operation['searches'][$i]['search'],
							'replace_original' => $actual_operation['searches'][$i]['add'],
							'position' => $search['position'],
							'is_custom' => $theme > 1 ? $theme : 0,
							'failed' => $failed,
						);

						$everything_found = false;
						continue;
					}

					// Replace it into nothing? That's not an option...unless it's an undoing end.
					if ($search['add'] === '' && ($search['position'] !== 'end' || !$undo))
						continue;

					// Finally, we're doing some replacements.
					$working_data = preg_replace('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $actual_operation['searches'][$i]['preg_replace'], $working_data, 1);

					$actions[] = array(
						'type' => 'replace',
						'filename' => $working_file,
						'search' => $actual_operation['searches'][$i]['preg_search'],
						'replace' => $actual_operation['searches'][$i]['preg_replace'],
						'search_original' => $actual_operation['searches'][$i]['search'],
						'replace_original' => $actual_operation['searches'][$i]['add'],
						'position' => $search['position'],
						'failed' => $failed,
						'ignore_failure' => $failed && $actual_operation['error'] === 'ignore',
						'is_custom' => $theme > 1 ? $theme : 0,
					);
				}
			}

			// Fix any little helper symbols ;).
			$working_data = strtr($working_data, array('[$PACK' . 'AGE1$]' => '$', '[$PACK' . 'AGE2$]' => '\\'));

			package_chmod($working_file);

			if ((file_exists($working_file) && !is_writable($working_file)) || (!file_exists($working_file) && !is_writable(dirname($working_file))))
				$actions[] = array(
					'type' => 'chmod',
					'filename' => $working_file
				);

			if (basename($working_file) == 'Settings_bak.php')
				continue;

			if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
			{
				// No, no, not Settings.php!
				if (basename($working_file) == 'Settings.php')
					@copy($working_file, dirname($working_file) . '/Settings_bak.php');
				else
					@copy($working_file, $working_file . '~');
			}

			// Always call this, even if in testing, because it won't really be written in testing mode.
			package_put_contents($working_file, $working_data, $testing);

			$actions[] = array(
				'type' => 'saved',
				'filename' => $working_file,
				'is_custom' => $theme > 1 ? $theme : 0,
			);
		}
	}

	$actions[] = array(
		'type' => 'result',
		'status' => $everything_found
	);

	return $actions;
}

/**
 * Parses a boardmod-style (.mod) modification file
 *
 * @param string $file The modification file to parse
 * @param bool $testing Whether we're just doing a test
 * @param bool $undo If true, specifies that the modifications should be undone. Used when uninstalling.
 * @param array $theme_paths An array of information about custom themes to apply the changes to
 * @return array An array of those changes made.
 */
function parseBoardMod($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $boarddir, $sourcedir, $settings, $modSettings;

	@set_time_limit(600);
	$file = strtr($file, array("\r" => ''));

	$working_file = null;
	$working_search = null;
	$working_data = '';
	$replace_with = null;

	$actions = array();
	$everything_found = true;

	// This holds all the template changes in the standard mod file.
	$template_changes = array();
	// This is just the temporary file.
	$temp_file = $file;
	// This holds the actual changes on a step counter basis.
	$temp_changes = array();
	$counter = 0;
	$step_counter = 0;

	// Before we do *anything*, let's build a list of what we're editing, as it's going to be used for other theme edits.
	while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $temp_file, $code_match) != 0)
	{
		$counter++;

		// Get rid of the old stuff.
		$temp_file = substr_replace($temp_file, '', strpos($temp_file, $code_match[0]), strlen($code_match[0]));

		// No interest to us?
		if ($code_match[1] != 'edit file' && $code_match[1] != 'file')
		{
			// It's a step, let's add that to the current steps.
			if (isset($temp_changes[$step_counter]))
				$temp_changes[$step_counter]['changes'][] = $code_match[0];
			continue;
		}

		// We've found a new edit - let's make ourself heard, kind of.
		$step_counter = $counter;
		$temp_changes[$step_counter] = array(
			'title' => $code_match[0],
			'changes' => array(),
		);

		$filename = parse_path($code_match[2]);

		// Now, is this a template file, and if so, which?
		foreach ($theme_paths as $id => $theme)
		{
			// If this filename is relative, if so take a guess at what it should be.
			if (strpos($filename, 'Themes') === 0)
				$filename = $boarddir . '/' . $filename;

			if (strpos($filename, $theme['theme_dir']) === 0)
				$template_changes[$id][$counter] = substr($filename, strlen($theme['theme_dir']) + 1);
		}
	}

	// Reference for what theme ID this action belongs to.
	$theme_id_ref = array();

	// Now we know what templates we need to touch, cycle through each theme and work out what we need to edit.
	if (!empty($template_changes[1]))
	{
		foreach ($theme_paths as $id => $theme)
		{
			// Don't do default, it means nothing to me.
			if ($id == 1)
				continue;

			// Now, for each file do we need to edit it?
			foreach ($template_changes[1] as $pos => $template_file)
			{
				// It does? Add it to the list darlin'.
				if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id][$pos]) || !in_array($template_file, $template_changes[$id][$pos])))
				{
					// Actually add it to the mod file too, so we can see that it will work ;)
					if (!empty($temp_changes[$pos]['changes']))
					{
						$file .= "\n\n" . '<edit file>' . "\n" . $theme['theme_dir'] . '/' . $template_file . "\n" . '</edit file>' . "\n\n" . implode("\n\n", $temp_changes[$pos]['changes']);
						$theme_id_ref[$counter] = $id;
						$counter += 1 + count($temp_changes[$pos]['changes']);
					}
				}
			}
		}
	}

	$counter = 0;
	$is_custom = 0;
	while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $file, $code_match) != 0)
	{
		// This is for working out what we should be editing.
		$counter++;

		// Edit a specific file.
		if ($code_match[1] == 'file' || $code_match[1] == 'edit file')
		{
			// Backup the old file.
			if ($working_file !== null)
			{
				package_chmod($working_file);

				// Don't even dare.
				if (basename($working_file) == 'Settings_bak.php')
					continue;

				if (!is_writable($working_file))
					$actions[] = array(
						'type' => 'chmod',
						'filename' => $working_file
					);

				if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
				{
					if (basename($working_file) == 'Settings.php')
						@copy($working_file, dirname($working_file) . '/Settings_bak.php');
					else
						@copy($working_file, $working_file . '~');
				}

				package_put_contents($working_file, $working_data, $testing);
			}

			if ($working_file !== null)
				$actions[] = array(
					'type' => 'saved',
					'filename' => $working_file,
					'is_custom' => $is_custom,
				);

			// Is this "now working on" file a theme specific one?
			$is_custom = isset($theme_id_ref[$counter - 1]) ? $theme_id_ref[$counter - 1] : 0;

			// Make sure the file exists!
			$working_file = parse_path($code_match[2]);

			if ($working_file[0] != '/' && $working_file[1] != ':')
			{
				trigger_error('parseBoardMod(): The filename \'' . $working_file . '\' is not a full path!', E_USER_WARNING);

				$working_file = $boarddir . '/' . $working_file;
			}

			if (!file_exists($working_file))
			{
				$places_to_check = array($boarddir, $sourcedir, $settings['default_theme_dir'], $settings['default_theme_dir'] . '/languages');

				foreach ($places_to_check as $place)
					if (file_exists($place . '/' . $working_file))
					{
						$working_file = $place . '/' . $working_file;
						break;
					}
			}

			if (file_exists($working_file))
			{
				// Load the new file.
				$working_data = str_replace("\r", '', package_get_contents($working_file));

				$actions[] = array(
					'type' => 'opened',
					'filename' => $working_file
				);
			}
			else
			{
				$actions[] = array(
					'type' => 'missing',
					'filename' => $working_file
				);

				$working_file = null;
				$everything_found = false;
			}

			// Can't be searching for something...
			$working_search = null;
		}
		// Search for a specific string.
		elseif (($code_match[1] == 'search' || $code_match[1] == 'search for') && $working_file !== null)
		{
			if ($working_search !== null)
			{
				$actions[] = array(
					'type' => 'error',
					'filename' => $working_file
				);

				$everything_found = false;
			}

			$working_search = $code_match[2];
		}
		// Must've already loaded a search string.
		elseif ($working_search !== null)
		{
			// This is the base string....
			$replace_with = $code_match[2];

			// Add this afterward...
			if ($code_match[1] == 'add' || $code_match[1] == 'add after')
				$replace_with = $working_search . "\n" . $replace_with;
			// Add this beforehand.
			elseif ($code_match[1] == 'before' || $code_match[1] == 'add before' || $code_match[1] == 'above' || $code_match[1] == 'add above')
				$replace_with .= "\n" . $working_search;
			// Otherwise.. replace with $replace_with ;).
		}

		// If we have a search string, replace string, and open file..
		if ($working_search !== null && $replace_with !== null && $working_file !== null)
		{
			// Make sure it's somewhere in the string.
			if ($undo)
			{
				$temp = $replace_with;
				$replace_with = $working_search;
				$working_search = $temp;
			}

			if (strpos($working_data, $working_search) !== false)
			{
				$working_data = str_replace($working_search, $replace_with, $working_data);

				$actions[] = array(
					'type' => 'replace',
					'filename' => $working_file,
					'search' => $working_search,
					'replace' => $replace_with,
					'search_original' => $working_search,
					'replace_original' => $replace_with,
					'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
					'is_custom' => $is_custom,
					'failed' => false,
				);
			}
			// It wasn't found!
			else
			{
				$actions[] = array(
					'type' => 'failure',
					'filename' => $working_file,
					'search' => $working_search,
					'is_custom' => $is_custom,
					'search_original' => $working_search,
					'replace_original' => $replace_with,
					'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
					'is_custom' => $is_custom,
					'failed' => true,
				);

				$everything_found = false;
			}

			// These don't hold any meaning now.
			$working_search = null;
			$replace_with = null;
		}

		// Get rid of the old tag.
		$file = substr_replace($file, '', strpos($file, $code_match[0]), strlen($code_match[0]));
	}

	// Backup the old file.
	if ($working_file !== null)
	{
		package_chmod($working_file);

		if (!is_writable($working_file))
			$actions[] = array(
				'type' => 'chmod',
				'filename' => $working_file
			);

		if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
		{
			if (basename($working_file) == 'Settings.php')
				@copy($working_file, dirname($working_file) . '/Settings_bak.php');
			else
				@copy($working_file, $working_file . '~');
		}

		package_put_contents($working_file, $working_data, $testing);
	}

	if ($working_file !== null)
		$actions[] = array(
			'type' => 'saved',
			'filename' => $working_file,
			'is_custom' => $is_custom,
		);

	$actions[] = array(
		'type' => 'result',
		'status' => $everything_found
	);

	return $actions;
}

/**
 * Get the physical contents of a packages file
 *
 * @param string $filename The package file
 * @return string The contents of the specified file
 */
function package_get_contents($filename)
{
	global $package_cache, $modSettings;

	if (!isset($package_cache))
	{
		$mem_check = setMemoryLimit('128M');

		// Windows doesn't seem to care about the memory_limit.
		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (strpos($filename, 'Packages/') !== false || $package_cache === false || !isset($package_cache[$filename]))
		return file_get_contents($filename);
	else
		return $package_cache[$filename];
}

/**
 * Writes data to a file, almost exactly like the file_put_contents() function.
 * uses FTP to create/chmod the file when necessary and available.
 * uses text mode for text mode file extensions.
 * returns the number of bytes written.
 *
 * @param string $filename The name of the file
 * @param string $data The data to write to the file
 * @param bool $testing Whether we're just testing things
 * @return int The length of the data written (in bytes)
 */
function package_put_contents($filename, $data, $testing = false)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp, $package_cache, $modSettings;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (!isset($package_cache))
	{
		// Try to increase the memory limit - we don't want to run out of ram!
		$mem_check = setMemoryLimit('128M');

		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (isset($package_ftp))
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

	if (!file_exists($filename) && isset($package_ftp))
		$package_ftp->create_file($ftp_file);
	elseif (!file_exists($filename))
		@touch($filename);

	package_chmod($filename);

	if (!$testing && (strpos($filename, 'Packages/') !== false || $package_cache === false))
	{
		$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

		// We should show an error message or attempt a rollback, no?
		if (!$fp)
			return false;

		fwrite($fp, $data);
		fclose($fp);
	}
	elseif (strpos($filename, 'Packages/') !== false || $package_cache === false)
		return strlen($data);
	else
	{
		$package_cache[$filename] = $data;

		// Permission denied, eh?
		$fp = @fopen($filename, 'r+');
		if (!$fp)
			return false;
		fclose($fp);
	}

	return strlen($data);
}

/**
 * Flushes the cache from memory to the filesystem
 *
 * @param bool $trash
 */
function package_flush_cache($trash = false)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp, $package_cache;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (empty($package_cache))
		return;

	// First, let's check permissions!
	foreach ($package_cache as $filename => $data)
	{
		if (isset($package_ftp))
			$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		if (!file_exists($filename) && isset($package_ftp))
			$package_ftp->create_file($ftp_file);
		elseif (!file_exists($filename))
			@touch($filename);

		$result = package_chmod($filename);

		// if we are not doing our test pass, then lets do a full write check
		// bypass directories when doing this test
		if ((!$trash) && !is_dir($filename))
		{
			// acid test, can we really open this file for writing?
			$fp = ($result) ? fopen($filename, 'r+') : $result;
			if (!$fp)
			{
				// We should have package_chmod()'d them before, no?!
				trigger_error('package_flush_cache(): some files are still not writable', E_USER_WARNING);
				return;
			}
			fclose($fp);
		}
	}

	if ($trash)
	{
		$package_cache = array();
		return;
	}

	// Write the cache to disk here.
	// Bypass directories when doing so - no data to write & the fopen will crash.
	foreach ($package_cache as $filename => $data)
	{
		if (!is_dir($filename))
		{
			$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
			fwrite($fp, $data);
			fclose($fp);
		}
	}

	$package_cache = array();
}

/**
 * Try to make a file writable.
 *
 * @param string $filename The name of the file
 * @param string $perm_state The permission state - can be either 'writable' or 'execute'
 * @param bool $track_change Whether to track this change
 * @return boolean True if it worked, false if it didn't
 */
function package_chmod($filename, $perm_state = 'writable', $track_change = false)
{
	/** @var ftp_connection $package_ftp */
	global $package_ftp;

	if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable')
		return true;

	// Start off checking without FTP.
	if (!isset($package_ftp) || $package_ftp === false)
	{
		for ($i = 0; $i < 2; $i++)
		{
			$chmod_file = $filename;

			// Start off with a less aggressive test.
			if ($i == 0)
			{
				// If this file doesn't exist, then we actually want to look at whatever parent directory does.
				$subTraverseLimit = 2;
				while (!file_exists($chmod_file) && $subTraverseLimit)
				{
					$chmod_file = dirname($chmod_file);
					$subTraverseLimit--;
				}

				// Keep track of the writable status here.
				$file_permissions = @fileperms($chmod_file);
			}
			else
			{
				// This looks odd, but it's an attempt to work around PHP suExec.
				if (!file_exists($chmod_file) && $perm_state == 'writable')
				{
					$file_permissions = @fileperms(dirname($chmod_file));

					mktree(dirname($chmod_file), 0755);
					@touch($chmod_file);
					smf_chmod($chmod_file, 0755);
				}
				else
					$file_permissions = @fileperms($chmod_file);
			}

			// This looks odd, but it's another attempt to work around PHP suExec.
			if ($perm_state != 'writable')
				smf_chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
			else
			{
				if (!@is_writable($chmod_file))
					smf_chmod($chmod_file, 0755);
				if (!@is_writable($chmod_file))
					smf_chmod($chmod_file, 0777);
				if (!@is_writable(dirname($chmod_file)))
					smf_chmod($chmod_file, 0755);
				if (!@is_writable(dirname($chmod_file)))
					smf_chmod($chmod_file, 0777);
			}

			// The ultimate writable test.
			if ($perm_state == 'writable')
			{
				$fp = is_dir($chmod_file) ? @opendir($chmod_file) : @fopen($chmod_file, 'rb');
				if (@is_writable($chmod_file) && $fp)
				{
					if (!is_dir($chmod_file))
						fclose($fp);
					else
						closedir($fp);

					// It worked!
					if ($track_change)
						$_SESSION['pack_ftp']['original_perms'][$chmod_file] = $file_permissions;

					return true;
				}
			}
			elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$chmod_file]))
				unset($_SESSION['pack_ftp']['original_perms'][$chmod_file]);
		}

		// If we're here we're a failure.
		return false;
	}
	// Otherwise we do have FTP?
	elseif ($package_ftp !== false && !empty($_SESSION['pack_ftp']))
	{
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		// This looks odd, but it's an attempt to work around PHP suExec.
		if (!file_exists($filename) && $perm_state == 'writable')
		{
			$file_permissions = @fileperms(dirname($filename));

			mktree(dirname($filename), 0755);
			$package_ftp->create_file($ftp_file);
			$package_ftp->chmod($ftp_file, 0755);
		}
		else
			$file_permissions = @fileperms($filename);

		if ($perm_state != 'writable')
		{
			$package_ftp->chmod($ftp_file, $perm_state == 'execute' ? 0755 : 0644);
		}
		else
		{
			if (!@is_writable($filename))
				$package_ftp->chmod($ftp_file, 0777);
			if (!@is_writable(dirname($filename)))
				$package_ftp->chmod(dirname($ftp_file), 0777);
		}

		if (@is_writable($filename))
		{
			if ($track_change)
				$_SESSION['pack_ftp']['original_perms'][$filename] = $file_permissions;

			return true;
		}
		elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$filename]))
			unset($_SESSION['pack_ftp']['original_perms'][$filename]);
	}

	// Oh dear, we failed if we get here.
	return false;
}

/**
 * Used to crypt the supplied ftp password in this session
 *
 * @param string $pass The password
 * @return string The encrypted password
 */
function package_crypt($pass)
{
	$n = strlen($pass);

	$salt = session_id();
	while (strlen($salt) < $n)
		$salt .= session_id();

	for ($i = 0; $i < $n; $i++)
		$pass{$i} = chr(ord($pass{$i}) ^ (ord($salt{$i}) - 32));

	return $pass;
}

/**
 * Creates a backup of forum files prior to modifying them
 *
 * @param string $id The name of the backup
 * @return bool True if it worked, false if it didn't
 */
function package_create_backup($id = 'backup')
{
	global $sourcedir, $boarddir, $packagesdir, $smcFunc;

	$files = array();

	$base_files = array('index.php', 'SSI.php', 'agreement.txt', 'cron.php', 'ssi_examples.php', 'ssi_examples.shtml', 'subscriptions.php');
	foreach ($base_files as $file)
	{
		if (file_exists($boarddir . '/' . $file))
			$files[empty($_REQUEST['use_full_paths']) ? $file : $boarddir . '/' . $file] = $boarddir . '/' . $file;
	}

	$dirs = array(
		$sourcedir => empty($_REQUEST['use_full_paths']) ? 'Sources/' : strtr($sourcedir . '/', '\\', '/')
	);

	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}',
		array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$dirs[$row['value']] = empty($_REQUEST['use_full_paths']) ? 'Themes/' . basename($row['value']) . '/' : strtr($row['value'] . '/', '\\', '/');
	$smcFunc['db_free_result']($request);

	try
	{
		foreach ($dirs as $dir => $dest)
		{
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
			);

			foreach ($iter as $entry => $dir)
			{
				if ($dir->isDir())
					continue;

				if (preg_match('~^(\.{1,2}|CVS|backup.*|help|images|.*\~)$~', $entry) != 0)
					continue;

				$files[empty($_REQUEST['use_full_paths']) ? str_replace(realpath($boarddir), '', $entry) : $entry] = $entry;
			}
		}
		$obj = new ArrayObject($files);
		$iterator = $obj->getIterator();

		if (!file_exists($packagesdir . '/backups'))
			mktree($packagesdir . '/backups', 0777);
		if (!is_writable($packagesdir . '/backups'))
			package_chmod($packagesdir . '/backups');
		$output_file = $packagesdir . '/backups/' . strftime('%Y-%m-%d_') . preg_replace('~[$\\\\/:<>|?*"\']~', '', $id);
		$output_ext = '.tar';
		$output_ext_target = '.tar.gz';

		if (file_exists($output_file . $output_ext_target))
		{
			$i = 2;
			while (file_exists($output_file . '_' . $i . $output_ext_target))
				$i++;
			$output_file = $output_file . '_' . $i . $output_ext;
		}
		else
			$output_file .= $output_ext;

		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Phar doesn't handle open_basedir restrictions very well and throws a PHP Warning. Ignore that.
		set_error_handler(function($errno, $errstr, $errfile, $errline)
		{
			// error was suppressed with the @-operator
			if (0 === error_reporting())
			{
				return false;
			}
			if (strpos($errstr, 'PharData::__construct(): open_basedir') === false && strpos($errstr, 'PharData::compress(): open_basedir') === false)
				log_error($errstr, 'general', $errfile, $errline);
			return true;
		}
		);
		$a = new PharData($output_file);
		$a->buildFromIterator($iterator);
		$a->compress(Phar::GZ);
		restore_error_handler();

		/*
		 * Destroying the local var tells PharData to close its internal
		 * file pointer, enabling us to delete the uncompressed tarball.
		 */
		unset($a);
		unlink($output_file);
	}
	catch (Exception $e)
	{
		log_error($e->getMessage(), 'backup');

		return false;
	}

	return true;
}

if (!function_exists('smf_crc32'))
{
	/**
	 * crc32 doesn't work as expected on 64-bit functions - make our own.
	 * https://php.net/crc32#79567
	 *
	 * @param string $number
	 * @return string The crc32
	 */
	function smf_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000)
		{
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}

?>