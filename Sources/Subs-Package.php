<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file's central purpose of existence is that of making the package
	manager work nicely.  It contains functions for handling tar.gz and zip
	files, as well as a simple xml parser to handle the xml package stuff.
	Not to mention a few functions to make file handling easier.

	array read_tgz_file(string filename, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- reads a .tar.gz file, filename, in and extracts file(s) from it.
		- essentially just a shortcut for read_tgz_data().

	array read_tgz_data(string data, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- extracts a file or files from the .tar.gz contained in data.
		- detects if the file is really a .zip file, and if so returns the
		  result of read_zip_data
		- if destination is null, returns a list of files in the archive.
		- if single_file is true, returns the contents of the file specified
		  by destination, if it exists, or false.
		- if single_file is true, destination can start with * and / to
		  signify that the file may come from any directory.
		- destination should not begin with a / if single_file is true.
		- overwrites existing files with newer modification times if and
		  only if overwrite is true.
		- creates the destination directory if it doesn't exist, and is
		  is specified.
		- requires zlib support be built into PHP.
		- returns an array of the files extracted.
		- if files_to_extract is not equal to null only extracts file within this array.

	array read_zip_data(string data, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- extracts a file or files from the .zip contained in data.
		- if destination is null, returns a list of files in the archive.
		- if single_file is true, returns the contents of the file specified
		  by destination, if it exists, or false.
		- if single_file is true, destination can start with * and / to
		  signify that the file may come from any directory.
		- destination should not begin with a / if single_file is true.
		- overwrites existing files with newer modification times if and
		  only if overwrite is true.
		- creates the destination directory if it doesn't exist, and is
		  is specified.
		- requires zlib support be built into PHP.
		- returns an array of the files extracted.
		- if files_to_extract is not equal to null only extracts file within this array.

	bool url_exists(string url)
		- checks to see if url is valid, and returns a 200 status code.
		- will return false if the file is "moved permanently" or similar.
		- returns true if the remote url exists.

	array loadInstalledPackages()
		- loads and returns an array of installed packages.
		- gets this information from Packages/installed.list.
		- returns the array of data.

	array getPackageInfo(string filename)
		- loads a package's information and returns a representative array.
		- expects the file to be a package in Packages/.
		- returns a error string if the package-info is invalid.
		- returns a basic array of id, version, filename, and similar
		  information.
		- in the array returned, an xmlArray is available in 'xml'.

	void packageRequireFTP(string destination_url, array files = none, bool return = false)
		// !!!

	array parsePackageInfo(xmlArray &package, bool testing_only = true,
			string method = 'install', string previous_version = '')
		- parses the actions in package-info.xml files from packages.
		- package should be an xmlArray with package-info as its base.
		- testing_only should be true if the package should not actually be
		   applied.
		- method is upgrade, install, or uninstall.  Its default is install.
		- previous_version should be set to the previous installed version
		   of this package, if any.
		- does not handle failure terribly well; testing first is always
		   better.
		- returns an array of those changes made.

	bool matchPackageVersion(string version, string versions)
		- checks if version matches any of the versions in versions.
		- supports comma separated version numbers, with or without
		  whitespace.
		- supports lower and upper bounds. (1.0-1.2)
		- returns true if the version matched.

	int compareVersions(string version1, string version2)
		- compares two versions.
		- returns 0 if version1 is equal to version2.
		- returns -1 if version1 is lower than version2.
		- returns 1 if version1 is higher than version2.

	string parse_path(string path)
		- parses special identifiers out of the specified path.
		- returns the parsed path.

	void deltree(string path, bool delete_directory = true)
		- deletes a directory, and all the files and direcories inside it.
		- requires access to delete these files.

	bool mktree(string path, int mode)
		- creates the specified tree structure with the mode specified.
		- creates every directory in path until it finds one that already
		  exists.
		- returns true if successful, false otherwise.

	void copytree(string source, string destination)
		- copies one directory structure over to another.
		- requires the destination to be writable.

	void listtree(string path, string sub_path = none)
		// !!!

	array parseModification(string file, bool testing = true, bool undo = false, array theme_paths = array())
		- parses a xml-style modification file (file).
		- testing tells it the modifications shouldn't actually be saved.
		- undo specifies that the modifications the file requests should be
		  undone; this doesn't work with everything (regular expressions.)
		- returns an array of those changes made.

	array parseBoardMod(string file, bool testing = true, bool undo = false, array theme_paths = array())
		- parses a boardmod-style modification file (file).
		- testing tells it the modifications shouldn't actually be saved.
		- undo specifies that the modifications the file requests should be
		  undone.
		- returns an array of those changes made.

	// !!!

	int package_put_contents(string filename, string data)
		- writes data to a file, almost exactly like the file_put_contents()
		  function.
		- uses FTP to create/chmod the file when necessary and available.
		- uses text mode for text mode file extensions.
		- returns the number of bytes written.

	void package_chmod(string filename)
		// !!!

	string package_crypt(string password)
		// !!!

	string fetch_web_data(string url, string post_data = '',
			bool keep_alive = false)
		// !!!

	Creating your own package server:
	---------------------------------------------------------------------------
		// !!!

	Creating your own package:
	---------------------------------------------------------------------------
		// !!!
*/

// Get the data from the file and extract it.
function read_tgz_file($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	if (substr($gzfilename, 0, 7) == 'http://')
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

	return read_tgz_data($data, $destination, $single_file, $overwrite, $files_to_extract);
}

// Extract tar.gz data.  If destination is null, return a listing.
function read_tgz_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	// Make sure we have this loaded.
	loadLanguage('Packages');

	// This function sorta needs gzinflate!
	if (!function_exists('gzinflate'))
		fatal_lang_error('package_no_zlib', 'critical');

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
			return read_zip_data($data, $destination, $single_file, $overwrite, $files_to_extract);
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

	// "Read" the filename and comment. // !!! Might be mussed.
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

		if ($current['type'] == 5 && substr($current['filename'], -1) != '/')
			$current['filename'] .= '/';

		foreach ($current as $k => $v)
		{
			if (in_array($k, $octdec))
				$current[$k] = octdec(trim($v));
			else
				$current[$k] = trim($v);
		}

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

// Extract zip data.  If destination is null, return a listing.
function read_zip_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	umask(0);
	if ($destination !== null && !file_exists($destination) && !$single_file)
		mktree($destination, 0777);

	// Look for the PK header...
	if (substr($data, 0, 2) != 'PK')
		return false;

	// Find the central whosamawhatsit at the end; if there's a comment it's a pain.
	if (substr($data, -22, 4) == 'PK' . chr(5) . chr(6))
		$p = -22;
	else
	{
		// Have to find where the comment begins, ugh.
		for ($p = -22; $p > -strlen($data); $p--)
		{
			if (substr($data, $p, 4) == 'PK' . chr(5) . chr(6))
				break;
		}
	}

	$return = array();

	// Get the basic zip file info.
	$zip_info = unpack('vfiles/Vsize/Voffset', substr($data, $p + 10, 10));

	$p = $zip_info['offset'];
	for ($i = 0; $i < $zip_info['files']; $i++)
	{
		// Make sure this is a file entry...
		if (substr($data, $p, 4) != 'PK' . chr(1) . chr(2))
			return false;

		// Get all the important file information.
		$file_info = unpack('Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', substr($data, $p + 16, 30));
		$file_info['filename'] = substr($data, $p + 46, $file_info['filename_len']);

		// Skip all the information we don't care about anyway.
		$p += 46 + $file_info['filename_len'] + $file_info['extra_len'] + $file_info['comment_len'];

		// If this is a file, and it doesn't exist.... happy days!
		if (substr($file_info['filename'], -1, 1) != '/' && !file_exists($destination . '/' . $file_info['filename']))
			$write_this = true;
		// If the file exists, we may not want to overwrite it.
		elseif (substr($file_info['filename'], -1, 1) != '/')
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

		// Check that the data is there and does exist.
		if (substr($data, $file_info['offset'], 4) != 'PK' . chr(3) . chr(4))
			return false;

		// Get the actual compressed data.
		$file_info['data'] = substr($data, $file_info['offset'] + 30 + $file_info['filename_len'], $file_info['compressed_size']);

		// Only inflate it if we need to ;).
		if ($file_info['compressed_size'] != $file_info['size'])
			$file_info['data'] = @gzinflate($file_info['data']);

		// Okay!  We can write this file, looks good from here...
		if ($write_this && $destination !== null)
		{
			if (strpos($file_info['filename'], '/') !== false && !$single_file)
				mktree($destination . '/' . dirname($file_info['filename']), 0777);

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

// Checks the existence of a remote file since file_exists() does not do remote.
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

// Load the installed packages.
function loadInstalledPackages()
{
	global $boarddir, $smcFunc;

	// First, check that the database is valid, installed.list is still king.
	$install_file = implode('', file($boarddir . '/Packages/installed.list'));
	if (trim($install_file) == '')
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_packages
			SET install_state = {int:not_installed}',
			array(
				'not_installed' => 0,
			)
		);

		// Don't have anything left, so send an empty array.
		return array();
	}

	// Load the packages from the database - note this is ordered by install time to ensure latest package uninstalled first.
	$request = $smcFunc['db_query']('', '
		SELECT id_install, package_id, filename, name, version
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

		$installed[] = array(
			'id' => $row['id_install'],
			'name' => $row['name'],
			'filename' => $row['filename'],
			'package_id' => $row['package_id'],
			'version' => $row['version'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $installed;
}

function getPackageInfo($gzfilename)
{
	global $boarddir;

	// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
	if (strpos($gzfilename, 'http://') !== false)
		$packageInfo = read_tgz_data(fetch_web_data($gzfilename, '', true), '*/package-info.xml', true);
	else
	{
		if (!file_exists($boarddir . '/Packages/' . $gzfilename))
			return 'package_get_error_not_found';

		if (is_file($boarddir . '/Packages/' . $gzfilename))
			$packageInfo = read_tgz_file($boarddir . '/Packages/' . $gzfilename, '*/package-info.xml', true);
		elseif (file_exists($boarddir . '/Packages/' . $gzfilename . '/package-info.xml'))
			$packageInfo = file_get_contents($boarddir . '/Packages/' . $gzfilename . '/package-info.xml');
		else
			return 'package_get_error_missing_xml';
	}

	// Nothing?
	if (empty($packageInfo))
		return 'package_get_error_is_zero';

	// Parse package-info.xml into an xmlArray.
	loadClassFile('Class-Package.php');
	$packageInfo = new xmlArray($packageInfo);

	// !!! Error message of some sort?
	if (!$packageInfo->exists('package-info[0]'))
		return 'package_get_error_packageinfo_corrupt';

	$packageInfo = $packageInfo->path('package-info[0]');

	$package = $packageInfo->to_array();
	$package['xml'] = $packageInfo;
	$package['filename'] = $gzfilename;

	if (!isset($package['type']))
		$package['type'] = 'modification';

	return $package;
}

// Create a chmod control for chmoding files.
function create_chmod_control($chmodFiles = array(), $chmodOptions = array(), $restore_write_status = false)
{
	global $context, $modSettings, $package_ftp, $boarddir, $txt, $sourcedir, $scripturl;

	// If we're restoring the status of existing files prepare the data.
	if ($restore_write_status && isset($_SESSION['pack_ftp']) && !empty($_SESSION['pack_ftp']['original_perms']))
	{
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
					if (!empty($package_ftp))
					{
						$ftp_file = strtr($file, array($_SESSION['pack_ftp']['root'] => ''));
						$package_ftp->chmod($ftp_file, $perms);
					}
					else
						@chmod($file, $perms);

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
						'function' => create_function('$rowData', '
							global $txt;

							$formatTxt = $rowData[\'result\'] == \'\' || $rowData[\'result\'] == \'skipped\' ? $txt[\'package_restore_permissions_pre_change\'] : $txt[\'package_restore_permissions_post_change\'];
							return sprintf($formatTxt, $rowData[\'cur_perms\'], $rowData[\'new_perms\'], $rowData[\'writable_message\']);
						'),
						'class' => 'smalltext',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="restore_files[]" value="%1$s" class="input_check" />',
							'params' => array(
								'path' => false,
							),
						),
						'style' => 'text-align: center',
					),
				),
				'result' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_result'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							return $txt[\'package_restore_permissions_action_\' . $rowData[\'result\']];
						'),
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
					'value' => '<input type="submit" name="restore_perms" value="' . $txt['package_restore_permissions_restore'] . '" class="button_submit" />',
					'class' => 'titlebg',
					'style' => 'text-align: right;',
				),
				array(
					'position' => 'after_title',
					'value' => '<span class="smalltext">' . $txt['package_restore_permissions_desc'] . '</span>',
					'class' => 'windowbg2',
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
		loadClassFile('Class-Package.php');

		$package_ftp = new ftp_connection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], package_crypt($_SESSION['pack_ftp']['password']));
	}

	// Just got a submission did we?
	if (empty($package_ftp) && isset($_POST['ftp_username']))
	{
		loadClassFile('Class-Package.php');
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
				loadClassFile('Class-Package.php');
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

function packageRequireFTP($destination_url, $files = null, $return = false)
{
	global $context, $modSettings, $package_ftp, $boarddir, $txt;

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
				@chmod($file, 0755);
			if (!@is_writable($file))
				@chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				@chmod($file, 0755);
			if (!@is_writable(dirname($file)))
				@chmod($file, 0777);

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
				@chmod($file, 0755);
			}

			if (!@is_writable($file))
				@chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				@chmod(dirname($file), 0777);

			if (@is_writable($file))
				unset($files[$k]);
		}

		return $files;
	}
	elseif (isset($_SESSION['pack_ftp']))
	{
		// Load the file containing the ftp_connection class.
		loadClassFile('Class-Package.php');

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
		loadClassFile('Class-Package.php');
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
			loadClassFile('Class-Package.php');
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
		);

		if (!isset($modSettings['package_path']) || $modSettings['package_path'] != $_POST['ftp_path'])
			updateSettings(array('package_path' => $_POST['ftp_path']));

		$files = packageRequireFTP($destination_url, $files, $return);
	}

	return $files;
}

// Parses a package-info.xml file - method can be 'install', 'upgrade', or 'uninstall'.
function parsePackageInfo(&$packageXML, $testing_only = true, $method = 'install', $previous_version = '')
{
	global $boarddir, $forum_version, $context, $temp_path, $language;

	// Mayday!  That action doesn't exist!!
	if (empty($packageXML) || !$packageXML->exists($method))
		return array();

	// We haven't found the package script yet...
	$script = false;
	$the_version = strtr($forum_version, array('SMF ' => ''));

	// Emulation support...
	if (!empty($_SESSION['version_emulate']))
		$the_version = $_SESSION['version_emulate'];

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
	if ($script === false)
		return array();

	// Find all the actions in this method - in theory, these should only be allowed actions. (* means all.)
	$actions = $script->set('*');
	$return = array();

	$temp_auto = 0;
	$temp_path = $boarddir . '/Packages/temp/' . (isset($context['base_path']) ? $context['base_path'] : '');

	$context['readmes'] = array();
	// This is the testing phase... nothing shall be done yet.
	foreach ($actions as $action)
	{
		$actionType = $action->name();

		if ($actionType == 'readme' || $actionType == 'code' || $actionType == 'database' || $actionType == 'modification' || $actionType == 'redirect')
		{
			// Allow for translated readme files.
			if ($actionType == 'readme')
			{
				if ($action->exists('@lang'))
				{
					// Auto-select a readme language based on either request variable or current language.
					if ((isset($_REQUEST['readme']) && $action->fetch('@lang') == $_REQUEST['readme']) || (!isset($_REQUEST['readme']) && $action->fetch('@lang') == $language))
					{
						// In case the user put the readme blocks in the wrong order.
						if (isset($context['readmes']['selected']) && $context['readmes']['selected'] == 'default')
							$context['readmes'][] = 'default';

						$context['readmes']['selected'] = htmlspecialchars($action->fetch('@lang'));
					}
					else
					{
						// We don't want this readme now, but we'll allow the user to select to read it.
						$context['readmes'][] = htmlspecialchars($action->fetch('@lang'));
						continue;
					}
				}
				// Fallback readme. Without lang parameter.
				else
				{

					// Already selected a readme.
					if (isset($context['readmes']['selected']))
					{
						$context['readmes'][] = 'default';
						continue;
					}
					else
						$context['readmes']['selected'] = 'default';
				}
			}

			// !!! TODO: Make sure the file actually exists?  Might not work when testing?
			if ($action->exists('@type') && $action->fetch('@type') == 'inline')
			{
				$filename = $temp_path . '$auto_' . $temp_auto++ . ($actionType == 'readme' || $actionType == 'redirect' ? '.txt' : ($actionType == 'code' || $actionType == 'database' ? '.php' : '.mod'));
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
				'language' => ($actionType == 'readme' && $action->exists('@lang') && $action->fetch('@lang') == $language) ? $language : '',
			);

			continue;
		}
		elseif ($actionType == 'error')
		{
			$return[] = array(
				'type' => 'error',
			);
		}

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
			if (!is_writable($this_action['filename']) && file_exists($this_action['destination']))
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

	// Only testing - just return a list of things to be done.
	if ($testing_only)
		return $return;

	umask(0);

	$failure = false;
	$not_done = array(array('type' => '!'));
	foreach ($return as $action)
	{
		if ($action['type'] == 'modification' || $action['type'] == 'code' || $action['type'] == 'database' || $action['type'] == 'redirect')
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

// This function tries to match $version into any of the ranges given in $versions
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

// The geek version of versioning checks for dummies, which basically compares two versions.
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
			'major' => (int) $parts[1],
			'minor' => (int) $parts[2],
			'patch' => (int) $parts[3],
			'type' => empty($parts[4]) ? 'stable' : $parts[4],
			'type_major' => (int) $parts[5],
			'type_minor' => (int) $parts[6],
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

function deltree($dir, $delete_dir = true)
{
	global $package_ftp;

	if (!file_exists($dir))
		return;

	$current_dir = @opendir($dir);
	if ($current_dir == false)
	{
		if ($delete_dir && isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_writable($dir . '/' . $entryname))
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
					@chmod($dir . '/' . $entryname, 0777);
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
				@chmod($dir, 0777);
			@rmdir($dir);
		}
	}
}

function mktree($strPath, $mode)
{
	global $package_ftp;

	if (is_dir($strPath))
	{
		if (!is_writable($strPath) && $mode !== false)
		{
			if (isset($package_ftp))
				$package_ftp->chmod(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')), $mode);
			else
				@chmod($strPath, $mode);
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
			@chmod(dirname($strPath), $mode);
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

function copytree($source, $destination)
{
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

// Parse an xml based modification file.
function parseModification($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $boarddir, $sourcedir, $settings, $txt, $modSettings, $package_ftp;

	@set_time_limit(600);
	loadClassFile('Class-Package.php');
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
						'replace' =>  $actual_operation['searches'][$i]['preg_replace'],
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

// Parses a BoardMod format mod file...
function parseBoardMod($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $boarddir, $sourcedir, $settings, $txt, $modSettings;

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

	// Anything above $counter must be for custom themes.
	$custom_template_begin = $counter;
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

function package_get_contents($filename)
{
	global $package_cache, $modSettings;

	if (!isset($package_cache))
	{
		// Windows doesn't seem to care about the memory_limit.
		if (!empty($modSettings['package_disable_cache']) || ini_set('memory_limit', '128M') !== false || strpos(strtolower(PHP_OS), 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (strpos($filename, 'Packages/') !== false || $package_cache === false || !isset($package_cache[$filename]))
		return file_get_contents($filename);
	else
		return $package_cache[$filename];
}

function package_put_contents($filename, $data, $testing = false)
{
	global $package_ftp, $package_cache, $modSettings;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (!isset($package_cache))
	{
		// Try to increase the memory limit - we don't want to run out of ram!
		if (!empty($modSettings['package_disable_cache']) || ini_set('memory_limit', '128M') !== false || strpos(strtolower(PHP_OS), 'win') !== false)
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

function package_flush_cache($trash = false)
{
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

		package_chmod($filename);

		$fp = fopen($filename, 'r+');
		if (!$fp && !$trash)
		{
			// We should have package_chmod()'d them before, no?!
			trigger_error('package_flush_cache(): some files are still not writable', E_USER_WARNING);
			return;
		}
		fclose($fp);
	}

	if ($trash)
	{
		$package_cache = array();
		return;
	}

	foreach ($package_cache as $filename => $data)
	{
		$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
		fwrite($fp, $data);
		fclose($fp);
	}

	$package_cache = array();
}

// Try to make a file writable. Return true if it worked, false if it didn't.
function package_chmod($filename, $perm_state = 'writable', $track_change = false)
{
	global $package_ftp;

	if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable')
		return true;

	// Start off checking without FTP.
	if (!isset($package_ftp) || $package_ftp === false)
	{
		for ($i = 0; $i < 2; $i++)
		{
			$chmod_file = $filename;

			// Start off with a less agressive test.
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
					@chmod($chmod_file, 0755);
				}
				else
					$file_permissions = @fileperms($chmod_file);
			}

			// This looks odd, but it's another attempt to work around PHP suExec.
			if ($perm_state != 'writable')
				@chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
			else
			{
				if (!@is_writable($chmod_file))
					@chmod($chmod_file, 0755);
				if (!@is_writable($chmod_file))
					@chmod($chmod_file, 0777);
				if (!@is_writable(dirname($chmod_file)))
					@chmod($chmod_file, 0755);
				if (!@is_writable(dirname($chmod_file)))
					@chmod($chmod_file, 0777);
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

function package_create_backup($id = 'backup')
{
	global $sourcedir, $boarddir, $smcFunc;

	$files = array();

	$base_files = array('index.php', 'SSI.php', 'agreement.txt', 'ssi_examples.php', 'ssi_examples.shtml');
	foreach ($base_files as $file)
	{
		if (file_exists($boarddir . '/' . $file))
			$files[realpath($boarddir . '/' . $file)] = array(
				empty($_REQUEST['use_full_paths']) ? $file : $boarddir . '/' . $file,
				stat($boarddir . '/' . $file)
			);
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

	while (!empty($dirs))
	{
		list ($dir, $dest) = each($dirs);
		unset($dirs[$dir]);

		$listing = @dir($dir);
		if (!$listing)
			continue;
		while ($entry = $listing->read())
		{
			if (preg_match('~^(\.{1,2}|CVS|backup.*|help|images|.*\~)$~', $entry) != 0)
				continue;

			$filepath = realpath($dir . '/' . $entry);
			if (isset($files[$filepath]))
				continue;

			$stat = stat($dir . '/' . $entry);
			if ($stat['mode'] & 040000)
			{
				$files[$filepath] = array($dest . $entry . '/', $stat);
				$dirs[$dir . '/' . $entry] = $dest . $entry . '/';
			}
			else
				$files[$filepath] = array($dest . $entry, $stat);
		}
		$listing->close();
	}

	if (!file_exists($boarddir . '/Packages/backups'))
		mktree($boarddir . '/Packages/backups', 0777);
	if (!is_writable($boarddir . '/Packages/backups'))
		package_chmod($boarddir . '/Packages/backups');
	$output_file = $boarddir . '/Packages/backups/' . strftime('%Y-%m-%d_') . preg_replace('~[$\\\\/:<>|?*"\']~', '', $id);
	$output_ext = '.tar' . (function_exists('gzopen') ? '.gz' : '');

	if (file_exists($output_file . $output_ext))
	{
		$i = 2;
		while (file_exists($output_file . '_' . $i . $output_ext))
			$i++;
		$output_file = $output_file . '_' . $i . $output_ext;
	}
	else
		$output_file .= $output_ext;

	@set_time_limit(300);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	if (function_exists('gzopen'))
	{
		$fwrite = 'gzwrite';
		$fclose = 'gzclose';
		$output = gzopen($output_file, 'wb');
	}
	else
	{
		$fwrite = 'fwrite';
		$fclose = 'fclose';
		$output = fopen($output_file, 'wb');
	}

	foreach ($files as $real_file => $file)
	{
		if (!file_exists($real_file))
			continue;

		$stat = $file[1];
		if (substr($file[0], -1) == '/')
			$stat['size'] = 0;

		$current = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12', $file[0], decoct($stat['mode']), sprintf('%06d', decoct($stat['uid'])), sprintf('%06d', decoct($stat['gid'])), decoct($stat['size']), decoct($stat['mtime']), '', 0, '', '', '', '', '', '', '', '', '');

		$checksum = 256;
		for ($i = 0; $i < 512; $i++)
			$checksum += ord($current{$i});

		$fwrite($output, substr($current, 0, 148) . pack('a8', decoct($checksum)) . substr($current, 156, 511));

		if ($stat['size'] == 0)
			continue;

		$fp = fopen($real_file, 'rb');
		while (!feof($fp))
			$fwrite($output, fread($fp, 16384));
		fclose($fp);

		$fwrite($output, pack('a' . (512 - $stat['size'] % 512), ''));
	}

	$fwrite($output, pack('a1024', ''));
	$fclose($output);
}

// Get the contents of a URL, irrespective of allow_url_fopen.
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 0)
{
	global $webmaster_email;
	static $keep_alive_dom = null, $keep_alive_fp = null;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', $url, $match);

	// An FTP url. We should try connecting and RETRieving it...
	if (empty($match[1]))
		return false;
	elseif ($match[1] == 'ftp')
	{
		// Include the file containing the ftp_connection class.
		loadClassFile('Class-Package.php');

		// Establish a connection and attempt to enable passive mode.
		$ftp = new ftp_connection(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', $webmaster_email);
		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}
	// This is more likely; a standard HTTP URL.
	elseif (isset($match[1]) && $match[1] == 'http')
	{
		if ($keep_alive && $match[3] == $keep_alive_dom)
			$fp = $keep_alive_fp;
		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
			if (!$fp)
				return false;
		}

		if ($keep_alive)
		{
			$keep_alive_dom = $match[3];
			$keep_alive_fp = $fp;
		}

		// I want this, from there, and I'm not going to be bothering you for more (probably.)
		if (empty($post_data))
		{
			fwrite($fp, 'GET ' . $match[6] . ' HTTP/1.0' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/SMF' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n\r\n");
		}
		else
		{
			fwrite($fp, 'POST ' . $match[6] . ' HTTP/1.0' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/SMF' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n");
			fwrite($fp, 'Content-Type: application/x-www-form-urlencoded' . "\r\n");
			fwrite($fp, 'Content-Length: ' . strlen($post_data) . "\r\n\r\n");
			fwrite($fp, $post_data);
		}

		$response = fgets($fp, 768);

		// Redirect in case this location is permanently or temporarily moved.
		if ($redirection_level < 3 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
		{
			$header = '';
			$location = '';
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
				if (strpos($header, 'Location:') !== false)
					$location = trim(substr($header, strpos($header, ':') + 1));

			if (empty($location))
				return false;
			else
			{
				if (!$keep_alive)
					fclose($fp);
				return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
			}
		}

		// Make sure we get a 200 OK.
		elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
			return false;

		// Skip the headers...
		while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
		{
			if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
				$content_length = $match[1];
			elseif (preg_match('~connection:\s*close~i', $header) != 0)
			{
				$keep_alive_dom = null;
				$keep_alive = false;
			}

			continue;
		}

		$data = '';
		if (isset($content_length))
		{
			while (!feof($fp) && strlen($data) < $content_length)
				$data .= fread($fp, $content_length - strlen($data));
		}
		else
		{
			while (!feof($fp))
				$data .= fread($fp, 4096);
		}

		if (!$keep_alive)
			fclose($fp);
	}
	else
	{
		// Umm, this shouldn't happen?
		trigger_error('fetch_web_data(): Bad URL', E_USER_NOTICE);
		$data = false;
	}

	return $data;
}

// crc32 doesn't work as expected on 64-bit functions - make our own.
// http://www.php.net/crc32#79567
if (!function_exists('smf_crc32'))
{
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