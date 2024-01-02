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

namespace SMF\PackageManager;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\ItemList;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\Url;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * Various utility functions for working with packages.
 *
 * These are kept separate from the PackageManager class for memory purposes.
 */
class SubsPackage
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'read_tgz_file' => 'read_tgz_file',
			'read_tgz_data' => 'read_tgz_data',
			'read_zip_data' => 'read_zip_data',
			'url_exists' => 'url_exists',
			'loadInstalledPackages' => 'loadInstalledPackages',
			'getPackageInfo' => 'getPackageInfo',
			'create_chmod_control' => 'create_chmod_control',
			'list_restoreFiles' => 'list_restoreFiles',
			'packageRequireFTP' => 'packageRequireFTP',
			'parsePackageInfo' => 'parsePackageInfo',
			'matchHighestPackageVersion' => 'matchHighestPackageVersion',
			'matchPackageVersion' => 'matchPackageVersion',
			'compareVersions' => 'compareVersions',
			'parse_path' => 'parse_path',
			'deltree' => 'deltree',
			'mktree' => 'mktree',
			'copytree' => 'copytree',
			'listtree' => 'listtree',
			'parseModification' => 'parseModification',
			'parseBoardMod' => 'parseBoardMod',
			'package_get_contents' => 'package_get_contents',
			'package_put_contents' => 'package_put_contents',
			'package_flush_cache' => 'package_flush_cache',
			'package_chmod' => 'package_chmod',
			'package_crypt' => 'package_crypt',
			'package_unique_filename' => 'package_unique_filename',
			'package_create_backup' => 'package_create_backup',
			'package_validate_installtest' => 'package_validate_installtest',
			'package_validate' => 'package_validate',
			'package_validate_send' => 'package_validate_send',
		],
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var mixed
	 *
	 * An instance of SMF\PackageManger\FtpConnection.
	 */
	public static $package_ftp;

	/**
	 * @var mixed
	 *
	 * Holds temporary package data.
	 */
	public static $package_cache;

	/**
	 * @var string
	 *
	 * Path to a temporary copy of a package.
	 */
	public static $temp_path;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Reads an archive from either a remote location or from the local filesystem.
	 *
	 * @param string $gzfilename The path to the tar.gz file
	 * @param null|string $destination The path to the destination directory
	 * @param bool $single_file If true returns the contents of the file specified by destination if it exists
	 * @param bool $overwrite Whether to overwrite existing files
	 * @param null|array $files_to_extract Specific files to extract
	 * @return array|string|false An array of information about extracted files or false on failure
	 */
	public static function read_tgz_file(string $gzfilename, ?string $destination, bool $single_file = false, bool $overwrite = false, ?array $files_to_extract = null): array|string|bool
	{
		$data = substr($gzfilename, 0, 7) == 'http://' || substr($gzfilename, 0, 8) == 'https://'
			? WebFetchApi::fetch($gzfilename)
			: file_get_contents($gzfilename);

		if ($data === false) {
			return false;
		}

		// Too short for magic numbers? No fortune cookie for you!
		if (strlen($data) < 2) {
			return false;
		}

		if ($data[0] == "\x1f" && $data[1] == "\x8b") {
			return self::read_tgz_data($data, $destination, $single_file, $overwrite, $files_to_extract);
		}

		// Okay, this ain't no tar.gz, but maybe it's a zip file.
		if ($data[0] == 'P' && $data[1] == 'K') {
			return self::read_zip_data($data, $destination, $single_file, $overwrite, $files_to_extract);
		}

		return false;
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
	 * @param string $data The gzipped tarball
	 * @param null|string $destination The destination
	 * @param bool $single_file Whether to only extract a single file
	 * @param bool $overwrite Whether to overwrite existing data
	 * @param null|array $files_to_extract If set, only extracts the specified files
	 * @return array|false An array of information about the extracted files or false on failure
	 */
	public static function read_tgz_data(string $data, ?string $destination, bool $single_file = false, bool $overwrite = false, ?array $files_to_extract = null): array|bool
	{
		// Make sure we have this loaded.
		Lang::load('Packages');

		// This function sorta needs gzinflate!
		if (!function_exists('gzinflate')) {
			ErrorHandler::fatalLang('package_no_lib', 'critical', ['package_no_zlib', 'package_no_package_manager']);
		}

		umask(0);

		if (!$single_file && $destination !== null && !file_exists($destination)) {
			self::mktree($destination, 0777);
		}

		$flags = unpack('Ct/Cf', substr($data, 2, 2));

		// Not deflate!
		if ($flags['t'] != 8) {
			return false;
		}
		$flags = $flags['f'];

		$offset = 10;
		$octdec = ['mode', 'uid', 'gid', 'size', 'mtime', 'checksum'];

		// "Read" the filename and comment.
		// @todo Might be mussed.
		if ($flags & 12) {
			while ($flags & 8 && $data[$offset++] != "\0") {
				continue;
			}

			while ($flags & 4 && $data[$offset++] != "\0") {
				continue;
			}
		}

		$crc = unpack('Vcrc32/Visize', substr($data, strlen($data) - 8, 8));
		$data = @gzinflate(substr($data, $offset, strlen($data) - 8 - $offset));

		// smf_crc32 and crc32 may not return the same results, so we accept either.
		if ($crc['crc32'] != self::smf_crc32($data) && $crc['crc32'] != crc32($data)) {
			return false;
		}

		$blocks = strlen($data) / 512 - 1;
		$offset = 0;

		$return = [];

		while ($offset < $blocks) {
			$header = substr($data, $offset << 9, 512);
			$current = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155path', $header);

			// Blank record?  This is probably at the end of the file.
			if (empty($current['filename'])) {
				$offset += 512;

				continue;
			}

			foreach ($current as $k => $v) {
				if (in_array($k, $octdec)) {
					$current[$k] = octdec(trim($v));
				} else {
					$current[$k] = trim($v);
				}
			}

			if ($current['type'] == '5' && substr($current['filename'], -1) != '/') {
				$current['filename'] .= '/';
			}

			$checksum = 256;

			for ($i = 0; $i < 148; $i++) {
				$checksum += ord($header[$i]);
			}

			for ($i = 156; $i < 512; $i++) {
				$checksum += ord($header[$i]);
			}

			if ($current['checksum'] != $checksum) {
				break;
			}

			$size = ceil($current['size'] / 512);
			$current['data'] = substr($data, ++$offset << 9, $current['size']);
			$offset += $size;

			// If hunting for a file in subdirectories, pass to subsequent write test...
			if ($single_file && $destination !== null && (substr($destination, 0, 2) == '*/')) {
				$write_this = true;
			}
			// Not a directory and doesn't exist already...
			elseif (substr($current['filename'], -1, 1) != '/' && $destination !== null && !file_exists($destination . '/' . $current['filename'])) {
				$write_this = true;
			}
			// File exists... check if it is newer.
			elseif (substr($current['filename'], -1, 1) != '/') {
				$write_this = $overwrite || ($destination !== null && filemtime($destination . '/' . $current['filename']) < $current['mtime']);
			}
			// Folder... create.
			elseif ($destination !== null && !$single_file) {
				// Protect from accidental parent directory writing...
				$current['filename'] = strtr($current['filename'], ['../' => '', '/..' => '']);

				if (!file_exists($destination . '/' . $current['filename'])) {
					self::mktree($destination . '/' . $current['filename'], 0777);
				}
				$write_this = false;
			} else {
				$write_this = false;
			}

			if ($write_this && $destination !== null) {
				if (strpos($current['filename'], '/') !== false && !$single_file) {
					self::mktree($destination . '/' . dirname($current['filename']), 0777);
				}

				// Is this the file we're looking for?
				if ($single_file && ($destination == $current['filename'] || $destination == '*/' . basename($current['filename']))) {
					return $current['data'];
				}

				// If we're looking for another file, keep going.
				if ($single_file) {
					continue;
				}

				// Looking for restricted files?
				if ($files_to_extract !== null && !in_array($current['filename'], $files_to_extract)) {
					continue;
				}

				self::package_put_contents($destination . '/' . $current['filename'], $current['data']);
			}

			if (substr($current['filename'], -1, 1) != '/') {
				$return[] = [
					'filename' => $current['filename'],
					'md5' => md5($current['data']),
					'preview' => substr($current['data'], 0, 100),
					'size' => $current['size'],
					'skipped' => false,
				];
			}
		}

		if ($destination !== null && !$single_file) {
			self::package_flush_cache();
		}

		if ($single_file) {
			return false;
		}

		return $return;
	}

	/**
	 * Extract zip data.
	 *
	 * If single_file is true, destination can start with * and / to signify that the file may come from any directory.
	 * Destination should not begin with a / if single_file is true.
	 *
	 * @param string $data ZIP data
	 * @param string $destination Null to display a listing of files in the archive, the destination for the files in the archive or the name of a single file to display (if $single_file is true)
	 * @param bool $single_file If true, returns the contents of the file specified by destination or false if the file can't be found (default value is false).
	 * @param bool $overwrite If true, will overwrite files with newer modification times. Default is false.
	 * @param array $files_to_extract
	 * @return mixed If destination is null, return a short array of a few file details optionally delimited by $files_to_extract. If $single_file is true, return contents of a file as a string; false otherwise
	 */
	public static function read_zip_data(string $data, string $destination, bool $single_file = false, bool $overwrite = false, ?array $files_to_extract = null): mixed
	{
		umask(0);

		if ($destination !== null && (substr($destination, 0, 2) != '*/') && !file_exists($destination) && !$single_file) {
			self::mktree($destination, 0777);
		}

		// Search for the end of directory signature 0x06054b50.
		if (($data_ecr = strrpos($data, "\x50\x4b\x05\x06")) === false) {
			return false;
		}
		$return = [];

		// End of central directory record (EOCD)
		$cdir = unpack('vdisk/@4/vdisk_entries/ventries/@12/Voffset', substr($data, $data_ecr + 4, 16));

		// We only support a single disk.
		if ($cdir['disk_entries'] != $cdir['entries']) {
			return false;
		}

		// First central file directory
		$pos_entry = $cdir['offset'];

		for ($i = 0; $i < $cdir['entries']; $i++) {
			// Central directory file header
			$header = unpack('Vcompressed_size/@8/vlen1/vlen2/vlen3/vdisk/@22/Voffset', substr($data, $pos_entry + 20, 26));

			// Sanity check: same disk?
			if ($header['disk'] != $cdir['disk']) {
				continue;
			}

			// Next central file directory
			$pos_entry += 46 + $header['len1'] + $header['len2'] + $header['len3'];

			// Local file header (so called because it is in the same file as the data in multi-part archives)
			$file_info = unpack(
				'vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len',
				substr($data, $header['offset'] + 6, 24),
			);

			$file_info['filename'] = substr($data, $header['offset'] + 30, $file_info['filename_len']);
			$is_file = substr($file_info['filename'], -1) != '/';

			/*
			 * If the bit at offset 3 (0x08) of the general-purpose flags field
			 * is set, then the CRC-32 and file sizes are not known when the header
			 * is written. The fields in the local header are filled with zero, and
			 * the CRC-32 and size are appended in a 12-byte structure (optionally
			 * preceded by a 4-byte signature) immediately after the compressed data:
			 */
			if ($file_info['flag'] & 0x08) {
				$gplen = $header['offset'] + 30 + $file_info['filename_len'] + $file_info['extra_len'] + $header['compressed_size'];

				// The spec allows for an optional header in the general purpose record
				if (substr($data, $gplen, 4) === "\x50\x4b\x07\x08") {
					$gplen += 4;
				}

				if (($general_purpose = unpack('Vcrc/Vcompressed_size/Vsize', substr($data, $gplen, 12))) !== false) {
					$file_info = $general_purpose + $file_info;
				}
			}

			$write_this = false;

			if ($destination !== null) {
				// If hunting for a file in subdirectories, pass to subsequent write test...
				if ($single_file && $destination !== null && (substr($destination, 0, 2) == '*/')) {
					$write_this = true;
				}
				// If this is a file, and it doesn't exist.... happy days!
				elseif ($is_file) {
					$write_this = !file_exists($destination . '/' . $file_info['filename']) || $overwrite;
				}
				// This is a directory, so we're gonna want to create it. (probably...)
				elseif (!$single_file) {
					$file_info['filename'] = strtr($file_info['filename'], ['../' => '', '/..' => '']);

					if (!file_exists($destination . '/' . $file_info['filename'])) {
						self::mktree($destination . '/' . $file_info['filename'], 0777);
					}
				}
			}

			// Get the actual compressed data.
			$file_info['data'] = substr(
				$data,
				$header['offset'] + 30 + $file_info['filename_len'] + $file_info['extra_len'],
				$file_info['compressed_size'],
			);

			// Only for the deflate method (the most common)
			if ($file_info['compression'] == 8) {
				$file_info['data'] = gzinflate($file_info['data']);
			}
			// We do not support any other compression methods.
			elseif ($file_info['compression'] != 0) {
				continue;
			}

			// PKZip/ITU-T V.42 CRC-32
			if (hash('crc32b', $file_info['data']) !== sprintf('%08x', $file_info['crc'])) {
				continue;
			}

			// Okay! We can write this file, looks good from here...
			if ($write_this) {
				// If we're looking for a specific file, and this is it... ka-bam, baby.
				if ($single_file && ($destination == $file_info['filename'] || $destination == '*/' . basename($file_info['filename']))) {
					return $file_info['data'];
				}

				// Oh, another file? Fine. You don't like this file, do you?  I know how it is.  Yeah... just go away.  No, don't apologize. I know this file's just not *good enough* for you.
				if ($single_file || ($files_to_extract !== null && !in_array($file_info['filename'], $files_to_extract))) {
					continue;
				}

				if (!$single_file && strpos($file_info['filename'], '/') !== false) {
					self::mktree($destination . '/' . dirname($file_info['filename']), 0777);
				}

				self::package_put_contents($destination . '/' . $file_info['filename'], $file_info['data']);
			}

			if ($is_file) {
				$return[] = [
					'filename' => $file_info['filename'],
					'md5' => md5($file_info['data']),
					'preview' => substr($file_info['data'], 0, 100),
					'size' => $file_info['size'],
					'skipped' => false,
				];
			}
		}

		if ($destination !== null && !$single_file) {
			self::package_flush_cache();
		}

		return $single_file ? false : $return;
	}

	/**
	 * Checks the existence of a remote file since file_exists() does not do remote.
	 * will return false if the file is "moved permanently" or similar.
	 *
	 * @param string $url The URL to parse
	 * @return bool Whether the specified URL exists
	 */
	public static function url_exists(string $url): bool
	{
		$url = new Url($url);
		$url->toAscii();

		if (!isset($url->scheme)) {
			return false;
		}

		// Attempt to connect...
		$temp = '';

		$fid = fsockopen($url->host, !isset($url->port) ? 80 : $url->port, $temp, $temp, 8);

		if (!$fid) {
			return false;
		}

		fputs($fid, 'HEAD ' . $url->path . ' HTTP/1.0' . "\r\n" . 'Host: ' . $url->host . "\r\n\r\n");
		$head = fread($fid, 1024);
		fclose($fid);

		return preg_match('~^HTTP/.+\s+(20[01]|30[127])~i', $head) == 1;
	}

	/**
	 * Loads and returns an array of installed packages.
	 *
	 *  default sort order is package_installed time
	 *
	 * @return array An array of info about installed packages
	 */
	public static function loadInstalledPackages(): array
	{
		// Load the packages from the database - note this is ordered by install time to ensure latest package uninstalled first.
		$request = Db::$db->query(
			'',
			'SELECT id_install, package_id, filename, name, version, time_installed
			FROM {db_prefix}log_packages
			WHERE install_state != {int:not_installed}
			ORDER BY time_installed DESC',
			[
				'not_installed' => 0,
			],
		);
		$installed = [];
		$found = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Already found this? If so don't add it twice!
			if (in_array($row['package_id'], $found)) {
				continue;
			}

			$found[] = $row['package_id'];

			$row = Utils::htmlspecialcharsRecursive($row);

			$installed[] = [
				'id' => $row['id_install'],
				'name' => Utils::htmlspecialchars($row['name']),
				'filename' => $row['filename'],
				'package_id' => $row['package_id'],
				'version' => Utils::htmlspecialchars($row['version']),
				'time_installed' => !empty($row['time_installed']) ? $row['time_installed'] : 0,
			];
		}
		Db::$db->free_result($request);

		return $installed;
	}

	/**
	 * Loads a package's information and returns a representative array.
	 * - expects the file to be a package in Packages/.
	 * - returns a error string if the package-info is invalid.
	 * - otherwise returns a basic array of id, version, filename, and similar information.
	 * - an XmlArray is available in 'xml'.
	 *
	 * @param string $gzfilename The path to the file
	 * @return array|string An array of info about the file or a string indicating an error
	 */
	public static function getPackageInfo(string $gzfilename): array|string
	{
		// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
		if (strpos($gzfilename, 'http://') !== false || strpos($gzfilename, 'https://') !== false) {
			$packageInfo = self::read_tgz_data($gzfilename, 'package-info.xml', true);
		} else {
			if (!file_exists(Config::$packagesdir . '/' . $gzfilename)) {
				return 'package_get_error_not_found';
			}

			if (is_file(Config::$packagesdir . '/' . $gzfilename)) {
				$packageInfo = self::read_tgz_file(Config::$packagesdir . '/' . $gzfilename, '*/package-info.xml', true);
			} elseif (file_exists(Config::$packagesdir . '/' . $gzfilename . '/package-info.xml')) {
				$packageInfo = file_get_contents(Config::$packagesdir . '/' . $gzfilename . '/package-info.xml');
			} else {
				return 'package_get_error_missing_xml';
			}
		}

		// Nothing?
		if (empty($packageInfo)) {
			// Perhaps they are trying to install a theme, lets tell them nicely this is the wrong function
			$packageInfo = self::read_tgz_file(Config::$packagesdir . '/' . $gzfilename, '*/theme_info.xml', true);

			if (!empty($packageInfo)) {
				return 'package_get_error_is_theme';
			}

			return 'package_get_error_is_zero';
		}

		// Parse package-info.xml into an XmlArray.
		$packageInfo = new XmlArray($packageInfo);

		// @todo Error message of some sort?
		if (!$packageInfo->exists('package-info[0]')) {
			return 'package_get_error_packageinfo_corrupt';
		}

		$packageInfo = $packageInfo->path('package-info[0]');

		$package = $packageInfo->to_array();
		$package = Utils::htmlspecialcharsRecursive($package);
		$package['xml'] = $packageInfo;
		$package['filename'] = $gzfilename;

		// Don't want to mess with code...
		$types = ['install', 'uninstall', 'upgrade'];

		foreach ($types as $type) {
			if (isset($package[$type]['code'])) {
				$package[$type]['code'] = Utils::htmlspecialcharsDecode($package[$type]['code']);
			}
		}

		if (!isset($package['type'])) {
			$package['type'] = 'modification';
		}

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
	public static function create_chmod_control(array $chmodFiles = [], array $chmodOptions = [], bool $restore_write_status = false): array
	{
		// If we're restoring the status of existing files prepare the data.
		if ($restore_write_status && isset($_SESSION['pack_ftp']) && !empty($_SESSION['pack_ftp']['original_perms'])) {
			$listOptions = [
				'id' => 'restore_file_permissions',
				'title' => Lang::$txt['package_restore_permissions'],
				'get_items' => [
					'function' => __CLASS__ . '::list_restoreFiles',
					'params' => [
						!empty($_POST['restore_perms']),
					],
				],
				'columns' => [
					'path' => [
						'header' => [
							'value' => Lang::$txt['package_restore_permissions_filename'],
						],
						'data' => [
							'db' => 'path',
							'class' => 'smalltext',
						],
					],
					'old_perms' => [
						'header' => [
							'value' => Lang::$txt['package_restore_permissions_orig_status'],
						],
						'data' => [
							'db' => 'old_perms',
							'class' => 'smalltext',
						],
					],
					'cur_perms' => [
						'header' => [
							'value' => Lang::$txt['package_restore_permissions_cur_status'],
						],
						'data' => [
							'function' => function ($rowData) {
								$formatTxt = $rowData['result'] == '' || $rowData['result'] == 'skipped' ? Lang::$txt['package_restore_permissions_pre_change'] : Lang::$txt['package_restore_permissions_post_change'];

								return sprintf($formatTxt, $rowData['cur_perms'], $rowData['new_perms'], $rowData['writable_message']);
							},
							'class' => 'smalltext',
						],
					],
					'check' => [
						'header' => [
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
							'class' => 'centercol',
						],
						'data' => [
							'sprintf' => [
								'format' => '<input type="checkbox" name="restore_files[]" value="%1$s">',
								'params' => [
									'path' => false,
								],
							],
							'class' => 'centercol',
						],
					],
					'result' => [
						'header' => [
							'value' => Lang::$txt['package_restore_permissions_result'],
						],
						'data' => [
							'function' => function ($rowData) {
								return Lang::$txt['package_restore_permissions_action_' . $rowData['result']];
							},
							'class' => 'smalltext',
						],
					],
				],
				'form' => [
					'href' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : Config::$scripturl . '?action=admin;area=packages;sa=perms;restore;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				],
				'additional_rows' => [
					[
						'position' => 'below_table_data',
						'value' => '<input type="submit" name="restore_perms" value="' . Lang::$txt['package_restore_permissions_restore'] . '" class="button">',
						'class' => 'titlebg',
					],
					[
						'position' => 'after_title',
						'value' => '<span class="smalltext">' . Lang::$txt['package_restore_permissions_desc'] . '</span>',
						'class' => 'windowbg',
					],
				],
			];

			// Work out what columns and the like to show.
			if (!empty($_POST['restore_perms'])) {
				$listOptions['additional_rows'][1]['value'] = sprintf(Lang::$txt['package_restore_permissions_action_done'], Config::$scripturl . '?action=admin;area=packages;sa=perms;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
				unset($listOptions['columns']['check'], $listOptions['form'], $listOptions['additional_rows'][0]);

				Utils::$context['sub_template'] = 'show_list';
				Utils::$context['default_list'] = 'restore_file_permissions';
			} else {
				unset($listOptions['columns']['result']);
			}

			// Create the list for display.
			new ItemList($listOptions);

			// If we just restored permissions then whereever we are, we are now done and dusted.
			if (!empty($_POST['restore_perms'])) {
				Utils::obExit();
			}
		}

		// This is where we report what we got up to.
		$return_data = [
			'files' => [
				'writable' => [],
				'notwritable' => [],
			],
		];

		// Otherwise, it's entirely irrelevant?
		if ($restore_write_status) {
			return $$return_data;
		}

		// If we have some FTP information already, then let's assume it was required and try to get ourselves connected.
		if (!empty($_SESSION['pack_ftp']['connected'])) {
			self::$package_ftp = new FtpConnection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], self::package_crypt($_SESSION['pack_ftp']['password']));
		}

		// Just got a submission did we?
		if (empty(self::$package_ftp) && isset($_POST['ftp_username'])) {
			$ftp = new FtpConnection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

			// We're connected, jolly good!
			if ($ftp->error === false) {
				// Common mistake, so let's try to remedy it...
				if (!$ftp->chdir($_POST['ftp_path'])) {
					$ftp_error = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
				}

				if (!in_array($_POST['ftp_path'], ['', '/'])) {
					$ftp_root = strtr(Config::$boarddir, [$_POST['ftp_path'] => '']);

					if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || substr($_POST['ftp_path'], 0, 1) == '/')) {
						$ftp_root = substr($ftp_root, 0, -1);
					}
				} else {
					$ftp_root = Config::$boarddir;
				}

				$_SESSION['pack_ftp'] = [
					'server' => $_POST['ftp_server'],
					'port' => $_POST['ftp_port'],
					'username' => $_POST['ftp_username'],
					'password' => self::package_crypt($_POST['ftp_password']),
					'path' => $_POST['ftp_path'],
					'root' => $ftp_root,
					'connected' => true,
				];

				if (!isset(Config::$modSettings['package_path']) || Config::$modSettings['package_path'] != $_POST['ftp_path']) {
					Config::updateModSettings(['package_path' => $_POST['ftp_path']]);
				}

				// This is now the primary connection.
				self::$package_ftp = $ftp;
			}
		}

		// Now try to simply make the files writable, with whatever we might have.
		if (!empty($chmodFiles)) {
			foreach ($chmodFiles as $k => $file) {
				// Sometimes this can somehow happen maybe?
				if (empty($file)) {
					unset($chmodFiles[$k]);
				}
				// Already writable?
				elseif (@is_writable($file)) {
					$return_data['files']['writable'][] = $file;
				} else {
					// Now try to change that.
					$return_data['files'][self::package_chmod($file, 'writable', true) ? 'writable' : 'notwritable'][] = $file;
				}
			}
		}

		// Have we still got nasty files which ain't writable? Dear me we need more FTP good sir.
		if (empty(self::$package_ftp) && (!empty($return_data['files']['notwritable']) || !empty($chmodOptions['force_find_error']))) {
			if (!isset($ftp) || $ftp->error !== false) {
				if (!isset($ftp)) {
					$ftp = new FtpConnection(null);
				} elseif ($ftp->error !== false && !isset($ftp_error)) {
					$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;
				}

				list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$boarddir);

				if ($found_path) {
					$_POST['ftp_path'] = $detect_path;
				} elseif (!isset($_POST['ftp_path'])) {
					$_POST['ftp_path'] = Config::$modSettings['package_path'] ?? $detect_path;
				}

				if (!isset($_POST['ftp_username'])) {
					$_POST['ftp_username'] = $username;
				}
			}

			Utils::$context['package_ftp'] = [
				'server' => $_POST['ftp_server'] ?? (Config::$modSettings['package_server'] ?? 'localhost'),
				'port' => $_POST['ftp_port'] ?? (Config::$modSettings['package_port'] ?? '21'),
				'username' => $_POST['ftp_username'] ?? (Config::$modSettings['package_username'] ?? ''),
				'path' => $_POST['ftp_path'],
				'error' => empty($ftp_error) ? null : $ftp_error,
				'destination' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : '',
			];

			// Which files failed?
			if (!isset(Utils::$context['notwritable_files'])) {
				Utils::$context['notwritable_files'] = [];
			}
			Utils::$context['notwritable_files'] = array_merge(Utils::$context['notwritable_files'], $return_data['files']['notwritable']);

			// Sent here to die?
			if (!empty($chmodOptions['crash_on_error'])) {
				Utils::$context['page_title'] = Lang::$txt['package_ftp_necessary'];
				Utils::$context['sub_template'] = 'ftp_required';
				Utils::obExit();
			}
		}

		return $return_data;
	}
	/**
	 * Get a listing of files that will need to be set back to the original state
	 *
	 * @param mixed $dummy1
	 * @param mixed $dummy2
	 * @param mixed $dummy3
	 * @param bool $do_change
	 * @return array An array of info about the files that need to be restored back to their original state
	 */
	public static function list_restoreFiles(mixed $dummy1, mixed $dummy2, mixed $dummy3, bool $do_change): array
	{
		$restore_files = [];

		foreach ($_SESSION['pack_ftp']['original_perms'] as $file => $perms) {
			// Check the file still exists, and the permissions were indeed different than now.
			$file_permissions = @fileperms($file);

			if (!file_exists($file) || $file_permissions == $perms) {
				unset($_SESSION['pack_ftp']['original_perms'][$file]);

				continue;
			}

			// Are we wanting to change the permission?
			if ($do_change && isset($_POST['restore_files']) && in_array($file, $_POST['restore_files'])) {
				// Use FTP if we have it.
				if (!empty(self::$package_ftp)) {
					$ftp_file = strtr($file, [$_SESSION['pack_ftp']['root'] => '']);
					self::$package_ftp->chmod($ftp_file, $perms);
				} else {
					Utils::makeWritable($file, $perms);
				}

				$new_permissions = @fileperms($file);
				$result = $new_permissions == $perms ? 'success' : 'failure';
				unset($_SESSION['pack_ftp']['original_perms'][$file]);
			} elseif ($do_change) {
				$new_permissions = '';
				$result = 'skipped';
				unset($_SESSION['pack_ftp']['original_perms'][$file]);
			}

			// Record the results!
			$restore_files[] = [
				'path' => $file,
				'old_perms_raw' => $perms,
				'old_perms' => substr(sprintf('%o', $perms), -4),
				'cur_perms' => substr(sprintf('%o', $file_permissions), -4),
				'new_perms' => isset($new_permissions) ? substr(sprintf('%o', $new_permissions), -4) : '',
				'result' => $result ?? '',
				'writable_message' => '<span style="color: ' . (@is_writable($file) ? 'green' : 'red') . '">' . (@is_writable($file) ? Lang::$txt['package_file_perms_writable'] : Lang::$txt['package_file_perms_not_writable']) . '</span>',
			];
		}

		return $restore_files;
	}

	/**
	 * Use FTP functions to work with a package download/install
	 *
	 * @param string $destination_url The destination URL
	 * @param null|array $files The files to CHMOD
	 * @param bool $return Whether to return an array of file info if there's an error
	 * @return array An array of file info
	 */
	public static function packageRequireFTP(string $destination_url, ?array $files = null, bool $return = false): array
	{
		// Try to make them writable the manual way.
		if ($files !== null) {
			foreach ($files as $k => $file) {
				// If this file doesn't exist, then we actually want to look at the directory, no?
				if (!file_exists($file)) {
					$file = dirname($file);
				}

				Utils::makeWritable($file);

				$fp = is_dir($file) ? @opendir($file) : @fopen($file, 'rb');

				if (@is_writable($file) && $fp) {
					unset($files[$k]);

					if (!is_dir($file)) {
						fclose($fp);
					} else {
						closedir($fp);
					}
				}
			}

			// No FTP required!
			if (empty($files)) {
				return [];
			}
		}

		// They've opted to not use FTP, and try anyway.
		if (isset($_SESSION['pack_ftp']) && $_SESSION['pack_ftp'] == false) {
			if ($files === null) {
				return [];
			}

			foreach ($files as $k => $file) {
				// This looks odd, but it's an attempt to work around PHP suExec.
				if (!file_exists($file)) {
					self::mktree(dirname($file), 0755);
					@touch($file);
				}

				if (Utils::makeWritable($file)) {
					unset($files[$k]);
				}
			}

			return $files;
		}

		if (isset($_SESSION['pack_ftp'])) {
			self::$package_ftp = new FtpConnection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], self::package_crypt($_SESSION['pack_ftp']['password']));

			if ($files === null) {
				return [];
			}

			foreach ($files as $k => $file) {
				$ftp_file = strtr($file, [$_SESSION['pack_ftp']['root'] => '']);

				// This looks odd, but it's an attempt to work around PHP suExec.
				if (!file_exists($file)) {
					self::mktree(dirname($file), 0755);
					self::$package_ftp->create_file($ftp_file);
					self::$package_ftp->chmod($ftp_file, 0755);
				}

				if (!@is_writable($file)) {
					self::$package_ftp->chmod($ftp_file, 0777);
				}

				if (!@is_writable(dirname($file))) {
					self::$package_ftp->chmod(dirname($ftp_file), 0777);
				}

				if (@is_writable($file)) {
					unset($files[$k]);
				}
			}

			return $files;
		}

		if (isset($_POST['ftp_none'])) {
			$_SESSION['pack_ftp'] = false;

			$files = self::packageRequireFTP($destination_url, $files, $return);

			return $files;
		}

		if (isset($_POST['ftp_username'])) {
			$ftp = new FtpConnection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

			if ($ftp->error === false) {
				// Common mistake, so let's try to remedy it...
				if (!$ftp->chdir($_POST['ftp_path'])) {
					$ftp_error = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false) {
			if (!isset($ftp)) {
				$ftp = new FtpConnection(null);
			} elseif ($ftp->error !== false && !isset($ftp_error)) {
				$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;
			}

			list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$boarddir);

			if ($found_path) {
				$_POST['ftp_path'] = $detect_path;
			} elseif (!isset($_POST['ftp_path'])) {
				$_POST['ftp_path'] = Config::$modSettings['package_path'] ?? $detect_path;
			}

			if (!isset($_POST['ftp_username'])) {
				$_POST['ftp_username'] = $username;
			}

			Utils::$context['package_ftp'] = [
				'server' => $_POST['ftp_server'] ?? (Config::$modSettings['package_server'] ?? 'localhost'),
				'port' => $_POST['ftp_port'] ?? (Config::$modSettings['package_port'] ?? '21'),
				'username' => $_POST['ftp_username'] ?? (Config::$modSettings['package_username'] ?? ''),
				'path' => $_POST['ftp_path'],
				'error' => empty($ftp_error) ? null : $ftp_error,
				'destination' => $destination_url,
			];

			// If we're returning dump out here.
			if ($return) {
				return $files;
			}

			Utils::$context['page_title'] = Lang::$txt['package_ftp_necessary'];
			Utils::$context['sub_template'] = 'ftp_required';
			Utils::obExit();
		} else {
			if (!in_array($_POST['ftp_path'], ['', '/'])) {
				$ftp_root = strtr(Config::$boarddir, [$_POST['ftp_path'] => '']);

				if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || $_POST['ftp_path'][0] == '/')) {
					$ftp_root = substr($ftp_root, 0, -1);
				}
			} else {
				$ftp_root = Config::$boarddir;
			}

			$_SESSION['pack_ftp'] = [
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => self::package_crypt($_POST['ftp_password']),
				'path' => $_POST['ftp_path'],
				'root' => $ftp_root,
			];

			if (!isset(Config::$modSettings['package_path']) || Config::$modSettings['package_path'] != $_POST['ftp_path']) {
				Config::updateModSettings(['package_path' => $_POST['ftp_path']]);
			}

			$files = self::packageRequireFTP($destination_url, $files, $return);
		}

		return $files;
	}

	/**
	 * Parses the actions in package-info.xml file from packages.
	 *
	 * - package should be an XmlArray with package-info as its base.
	 * - testing_only should be true if the package should not actually be applied.
	 * - method can be upgrade, install, or uninstall.  Its default is install.
	 * - previous_version should be set to the previous installed version of this package, if any.
	 * - does not handle failure terribly well; testing first is always better.
	 *
	 * @param XmlArray &$packageXML The info from the package-info file
	 * @param bool $testing_only Whether we're only testing
	 * @param string $method The method ('install', 'upgrade', or 'uninstall')
	 * @param string $previous_version The previous version of the mod, if method is 'upgrade'
	 * @return array An array of those changes made.
	 */
	public static function parsePackageInfo(XmlArray &$packageXML, bool $testing_only = true, string $method = 'install', string $previous_version = ''): array
	{
		// Mayday!  That action doesn't exist!!
		if (empty($packageXML) || !$packageXML->exists($method)) {
			return [];
		}

		// We haven't found the package script yet...
		$script = false;
		$the_version = SMF_VERSION;

		// Emulation support...
		if (!empty($_SESSION['version_emulate'])) {
			$the_version = $_SESSION['version_emulate'];
		}

		// Single package emulation
		if (!empty($_REQUEST['ve']) && !empty($_REQUEST['package'])) {
			$the_version = $_REQUEST['ve'];
			$_SESSION['single_version_emulate'][$_REQUEST['package']] = $the_version;
		}

		if (!empty($_REQUEST['package']) && (!empty($_SESSION['single_version_emulate'][$_REQUEST['package']]))) {
			$the_version = $_SESSION['single_version_emulate'][$_REQUEST['package']];
		}

		// Get all the versions of this method and find the right one.
		$these_methods = $packageXML->set($method);

		foreach ($these_methods as $this_method) {
			// They specified certain versions this part is for.
			if ($this_method->exists('@for')) {
				// Don't keep going if this won't work for this version of SMF.
				if (!self::matchPackageVersion($the_version, $this_method->fetch('@for'))) {
					continue;
				}
			}

			// Upgrades may go from a certain old version of the mod.
			if ($method == 'upgrade' && $this_method->exists('@from')) {
				// Well, this is for the wrong old version...
				if (!self::matchPackageVersion($previous_version, $this_method->fetch('@from'))) {
					continue;
				}
			}

			// We've found it!
			$script = $this_method;

			break;
		}

		// Bad news, a matching script wasn't found!
		if (!($script instanceof XmlArray)) {
			return [];
		}

		// Find all the actions in this method - in theory, these should only be allowed actions. (* means all.)
		$actions = $script->set('*');
		$return = [];

		$temp_auto = 0;
		self::$temp_path = Config::$packagesdir . '/temp/' . (Utils::$context['base_path'] ?? '');

		Utils::$context['readmes'] = [];
		Utils::$context['licences'] = [];

		// This is the testing phase... nothing shall be done yet.
		foreach ($actions as $action) {
			$actionType = $action->name();

			if (in_array($actionType, ['readme', 'code', 'database', 'modification', 'redirect', 'license'])) {
				// Allow for translated readme and license files.
				if ($actionType == 'readme' || $actionType == 'license') {
					$type = $actionType . 's';

					if ($action->exists('@lang')) {
						// Auto-select the language based on either request variable or current language.
						if ((isset($_REQUEST['readme']) && $action->fetch('@lang') == $_REQUEST['readme']) || (isset($_REQUEST['license']) && $action->fetch('@lang') == $_REQUEST['license']) || (!isset($_REQUEST['readme']) && $action->fetch('@lang') == Config::$language) || (!isset($_REQUEST['license']) && $action->fetch('@lang') == Config::$language)) {
							// In case the user put the blocks in the wrong order.
							if (isset(Utils::$context[$type]['selected']) && Utils::$context[$type]['selected'] == 'default') {
								Utils::$context[$type][] = 'default';
							}

							Utils::$context[$type]['selected'] = Utils::htmlspecialchars($action->fetch('@lang'));
						} else {
							// We don't want this now, but we'll allow the user to select to read it.
							Utils::$context[$type][] = Utils::htmlspecialchars($action->fetch('@lang'));

							continue;
						}
					}
					// Fallback when we have no lang parameter.
					else {
						// Already selected one for use?
						if (isset(Utils::$context[$type]['selected'])) {
							Utils::$context[$type][] = 'default';

							continue;
						}

						Utils::$context[$type]['selected'] = 'default';
					}
				}

				// @todo Make sure the file actually exists?  Might not work when testing?
				if ($action->exists('@type') && $action->fetch('@type') == 'inline') {
					$filename = self::$temp_path . '$auto_' . $temp_auto++ . (in_array($actionType, ['readme', 'redirect', 'license']) ? '.txt' : ($actionType == 'code' || $actionType == 'database' ? '.php' : '.mod'));
					self::package_put_contents($filename, $action->fetch('.'));
					$filename = strtr($filename, [self::$temp_path => '']);
				} else {
					$filename = $action->fetch('.');
				}

				$return[] = [
					'type' => $actionType,
					'filename' => $filename,
					'description' => '',
					'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true',
					'boardmod' => $action->exists('@format') && $action->fetch('@format') == 'boardmod',
					'redirect_url' => $action->exists('@url') ? $action->fetch('@url') : '',
					'redirect_timeout' => $action->exists('@timeout') ? (int) $action->fetch('@timeout') : '',
					'parse_bbc' => $action->exists('@parsebbc') && $action->fetch('@parsebbc') == 'true',
					'language' => (($actionType == 'readme' || $actionType == 'license') && $action->exists('@lang') && $action->fetch('@lang') == Config::$language) ? Config::$language : '',
				];

				continue;
			}

			if ($actionType == 'hook') {
				$return[] = [
					'type' => $actionType,
					'function' => $action->exists('@function') ? $action->fetch('@function') : '',
					'hook' => $action->exists('@hook') ? $action->fetch('@hook') : $action->fetch('.'),
					'include_file' => $action->exists('@file') ? $action->fetch('@file') : '',
					'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true' ? true : false,
					'object' => $action->exists('@object') && $action->fetch('@object') == 'true' ? true : false,
					'description' => '',
				];

				continue;
			}

			if ($actionType == 'credits') {
				// quick check of any supplied url
				$url = $action->exists('@url') ? $action->fetch('@url') : '';

				if (strlen(trim($url)) > 0 && substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://') {
					$url = 'http://' . $url;

					if (strlen($url) < 8 || (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://')) {
						$url = '';
					}
				}

				$return[] = [
					'type' => $actionType,
					'url' => $url,
					'license' => $action->exists('@license') ? $action->fetch('@license') : '',
					'licenseurl' => $action->exists('@licenseurl') ? $action->fetch('@licenseurl') : '',
					'copyright' => $action->exists('@copyright') ? $action->fetch('@copyright') : '',
					'title' => $action->fetch('.'),
				];

				continue;
			}

			if ($actionType == 'requires') {
				$return[] = [
					'type' => $actionType,
					'id' => $action->exists('@id') ? $action->fetch('@id') : '',
					'version' => $action->exists('@version') ? $action->fetch('@version') : $action->fetch('.'),
					'description' => '',
				];

				continue;
			}

			if ($actionType == 'error') {
				$return[] = [
					'type' => 'error',
				];
			} elseif (in_array($actionType, ['require-file', 'remove-file', 'require-dir', 'remove-dir', 'move-file', 'move-dir', 'create-file', 'create-dir'])) {
				$this_action = &$return[];
				$this_action = [
					'type' => $actionType,
					'filename' => $action->fetch('@name'),
					'description' => $action->fetch('.'),
				];

				// If there is a destination, make sure it makes sense.
				if (substr($actionType, 0, 6) != 'remove') {
					$this_action['unparsed_destination'] = $action->fetch('@destination');
					$this_action['destination'] = self::parse_path($action->fetch('@destination')) . '/' . basename($this_action['filename']);
				} else {
					$this_action['unparsed_filename'] = $this_action['filename'];
					$this_action['filename'] = self::parse_path($this_action['filename']);
				}

				// If we're moving or requiring (copying) a file.
				if (substr($actionType, 0, 4) == 'move' || substr($actionType, 0, 7) == 'require') {
					if ($action->exists('@from')) {
						$this_action['source'] = self::parse_path($action->fetch('@from'));
					} else {
						$this_action['source'] = self::$temp_path . $this_action['filename'];
					}
				}

				// Check if these things can be done. (chmod's etc.)
				if ($actionType == 'create-dir') {
					if (!self::mktree($this_action['destination'], false)) {
						$temp = $this_action['destination'];

						while (!file_exists($temp) && strlen($temp) > 1) {
							$temp = dirname($temp);
						}

						$return[] = [
							'type' => 'chmod',
							'filename' => $temp,
						];
					}
				} elseif ($actionType == 'create-file') {
					if (!self::mktree(dirname($this_action['destination']), false)) {
						$temp = dirname($this_action['destination']);

						while (!file_exists($temp) && strlen($temp) > 1) {
							$temp = dirname($temp);
						}

						$return[] = [
							'type' => 'chmod',
							'filename' => $temp,
						];
					}

					if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination'])))) {
						$return[] = [
							'type' => 'chmod',
							'filename' => $this_action['destination'],
						];
					}
				} elseif ($actionType == 'require-dir') {
					if (!self::mktree($this_action['destination'], false)) {
						$temp = $this_action['destination'];

						while (!file_exists($temp) && strlen($temp) > 1) {
							$temp = dirname($temp);
						}

						$return[] = [
							'type' => 'chmod',
							'filename' => $temp,
						];
					}
				} elseif ($actionType == 'require-file') {
					if ($action->exists('@theme')) {
						$this_action['theme_action'] = $action->fetch('@theme');
					}

					if (!self::mktree(dirname($this_action['destination']), false)) {
						$temp = dirname($this_action['destination']);

						while (!file_exists($temp) && strlen($temp) > 1) {
							$temp = dirname($temp);
						}

						$return[] = [
							'type' => 'chmod',
							'filename' => $temp,
						];
					}

					if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination'])))) {
						$return[] = [
							'type' => 'chmod',
							'filename' => $this_action['destination'],
						];
					}
				} elseif ($actionType == 'move-dir' || $actionType == 'move-file') {
					if (!self::mktree(dirname($this_action['destination']), false)) {
						$temp = dirname($this_action['destination']);

						while (!file_exists($temp) && strlen($temp) > 1) {
							$temp = dirname($temp);
						}

						$return[] = [
							'type' => 'chmod',
							'filename' => $temp,
						];
					}

					if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination'])))) {
						$return[] = [
							'type' => 'chmod',
							'filename' => $this_action['destination'],
						];
					}
				} elseif ($actionType == 'remove-dir') {
					if (!is_writable($this_action['filename']) && file_exists($this_action['filename'])) {
						$return[] = [
							'type' => 'chmod',
							'filename' => $this_action['filename'],
						];
					}
				} elseif ($actionType == 'remove-file') {
					if (!is_writable($this_action['filename']) && file_exists($this_action['filename'])) {
						$return[] = [
							'type' => 'chmod',
							'filename' => $this_action['filename'],
						];
					}
				}
			} else {
				$return[] = [
					'type' => 'error',
					'error_msg' => 'unknown_action',
					'error_var' => $actionType,
				];
			}
		}

		// Only testing - just return a list of things to be done.
		if ($testing_only) {
			return $return;
		}

		umask(0);

		$failure = false;
		$not_done = [['type' => '!']];

		foreach ($return as $action) {
			if (in_array($action['type'], ['modification', 'code', 'database', 'redirect', 'hook', 'credits'])) {
				$not_done[] = $action;
			}

			if ($action['type'] == 'create-dir') {
				if (!self::mktree($action['destination'], 0755) || !is_writable($action['destination'])) {
					$failure |= !self::mktree($action['destination'], 0777);
				}
			} elseif ($action['type'] == 'create-file') {
				if (!self::mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination']))) {
					$failure |= !self::mktree(dirname($action['destination']), 0777);
				}

				// Create an empty file.
				self::package_put_contents($action['destination'], self::package_get_contents($action['source']), $testing_only);

				if (!file_exists($action['destination'])) {
					$failure = true;
				}
			} elseif ($action['type'] == 'require-dir') {
				self::copytree($action['source'], $action['destination']);

				// Any other theme folders?
				if (!empty(Utils::$context['theme_copies']) && !empty(Utils::$context['theme_copies'][$action['type']][$action['destination']])) {
					foreach (Utils::$context['theme_copies'][$action['type']][$action['destination']] as $theme_destination) {
						self::copytree($action['source'], $theme_destination);
					}
				}
			} elseif ($action['type'] == 'require-file') {
				if (!self::mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination']))) {
					$failure |= !self::mktree(dirname($action['destination']), 0777);
				}

				self::package_put_contents($action['destination'], self::package_get_contents($action['source']), $testing_only);

				$failure |= !copy($action['source'], $action['destination']);

				// Any other theme files?
				if (!empty(Utils::$context['theme_copies']) && !empty(Utils::$context['theme_copies'][$action['type']][$action['destination']])) {
					foreach (Utils::$context['theme_copies'][$action['type']][$action['destination']] as $theme_destination) {
						if (!self::mktree(dirname($theme_destination), 0755) || !is_writable(dirname($theme_destination))) {
							$failure |= !self::mktree(dirname($theme_destination), 0777);
						}

						self::package_put_contents($theme_destination, self::package_get_contents($action['source']), $testing_only);

						$failure |= !copy($action['source'], $theme_destination);
					}
				}
			} elseif ($action['type'] == 'move-file') {
				if (!self::mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination']))) {
					$failure |= !self::mktree(dirname($action['destination']), 0777);
				}

				$failure |= !rename($action['source'], $action['destination']);
			} elseif ($action['type'] == 'move-dir') {
				if (!self::mktree($action['destination'], 0755) || !is_writable($action['destination'])) {
					$failure |= !self::mktree($action['destination'], 0777);
				}

				$failure |= !rename($action['source'], $action['destination']);
			} elseif ($action['type'] == 'remove-dir') {
				self::deltree($action['filename']);

				// Any other theme folders?
				if (!empty(Utils::$context['theme_copies']) && !empty(Utils::$context['theme_copies'][$action['type']][$action['filename']])) {
					foreach (Utils::$context['theme_copies'][$action['type']][$action['filename']] as $theme_destination) {
						self::deltree($theme_destination);
					}
				}
			} elseif ($action['type'] == 'remove-file') {
				// Make sure the file exists before deleting it.
				if (file_exists($action['filename'])) {
					self::package_chmod($action['filename']);
					$failure |= !unlink($action['filename']);
				}
				// The file that was supposed to be deleted couldn't be found.
				else {
					$failure = true;
				}

				// Any other theme folders?
				if (!empty(Utils::$context['theme_copies']) && !empty(Utils::$context['theme_copies'][$action['type']][$action['filename']])) {
					foreach (Utils::$context['theme_copies'][$action['type']][$action['filename']] as $theme_destination) {
						if (file_exists($theme_destination)) {
							$failure |= !unlink($theme_destination);
						} else {
							$failure = true;
						}
					}
				}
			}
		}

		return $not_done;
	}

	/**
	 * Checks if version matches any of the versions in `$versions`.
	 *
	 * - supports comma separated version numbers, with or without whitespace.
	 * - supports lower and upper bounds. (1.0-1.2)
	 * - returns true if the version matched.
	 *
	 * @param string $versions The versions that this package will install on
	 * @param bool $reset Whether to reset $near_version
	 * @param string $the_version The forum version
	 * @return string|bool Highest install value string or false
	 */
	public static function matchHighestPackageVersion(string $versions, bool $reset, string $the_version): string|bool
	{
		static $near_version = 0;

		if ($reset) {
			$near_version = 0;
		}

		// Normalize the $versions while we remove our previous Doh!
		$versions = explode(',', str_replace([' ', '2.0rc1-1'], ['', '2.0rc1.1'], strtolower($versions)));

		// Loop through each version, save the highest we can find
		foreach ($versions as $for) {
			// Adjust for those wild cards
			if (strpos($for, '*') !== false) {
				$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);
			}

			// If we have a range, grab the lower value, done this way so it looks normal-er to the user e.g. 2.0 vs 2.0.99
			if (strpos($for, '-') !== false) {
				list($for, $higher) = explode('-', $for);
			}

			// Do the compare, if the for is greater, than what we have but not greater than what we are running .....
			if (self::compareVersions($near_version, $for) === -1 && self::compareVersions($for, $the_version) !== 1) {
				$near_version = $for;
			}
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
	public static function matchPackageVersion(string $version, string $versions): bool
	{
		// Make sure everything is lowercase and clean of spaces and unpleasant history.
		$version = str_replace([' ', '2.0rc1-1'], ['', '2.0rc1.1'], strtolower($version));
		$versions = explode(',', str_replace([' ', '2.0rc1-1'], ['', '2.0rc1.1'], strtolower($versions)));

		// Perhaps we do accept anything?
		if (in_array('all', $versions)) {
			return true;
		}

		// Loop through each version.
		foreach ($versions as $for) {
			// Wild card spotted?
			if (strpos($for, '*') !== false) {
				$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);
			}

			// Do we have a range?
			if (strpos($for, '-') !== false) {
				list($lower, $upper) = explode('-', $for);

				// Compare the version against lower and upper bounds.
				if (self::compareVersions($version, $lower) > -1 && self::compareVersions($version, $upper) < 1) {
					return true;
				}
			}
			// Otherwise check if they are equal...
			elseif (self::compareVersions($version, $for) === 0) {
				return true;
			}
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
	public static function compareVersions(string $version1, string $version2): int
	{
		static $categories;

		$versions = [];

		foreach ([1 => $version1, $version2] as $id => $version) {
			// Clean the version and extract the version parts.
			$clean = str_replace([' ', '2.0rc1-1'], ['', '2.0rc1.1'], strtolower($version));
			preg_match('~(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)~', $clean, $parts);

			// Build an array of parts.
			$versions[$id] = [
				'major' => !empty($parts[1]) ? (int) $parts[1] : 0,
				'minor' => !empty($parts[2]) ? (int) $parts[2] : 0,
				'patch' => !empty($parts[3]) ? (int) $parts[3] : 0,
				'type' => empty($parts[4]) ? 'stable' : $parts[4],
				'type_major' => !empty($parts[5]) ? (int) $parts[5] : 0,
				'type_minor' => !empty($parts[6]) ? (int) $parts[6] : 0,
				'dev' => !empty($parts[7]),
			];
		}

		// Are they the same, perhaps?
		if ($versions[1] === $versions[2]) {
			return 0;
		}

		// Get version numbering categories...
		if (!isset($categories)) {
			$categories = array_keys($versions[1]);
		}

		// Loop through each category.
		foreach ($categories as $category) {
			// Is there something for us to calculate?
			if ($versions[1][$category] !== $versions[2][$category]) {
				// Dev builds are a problematic exception.
				// (stable) dev < (stable) but (unstable) dev = (unstable)
				if ($category == 'type') {
					return $versions[1][$category] > $versions[2][$category] ? ($versions[1]['dev'] ? -1 : 1) : ($versions[2]['dev'] ? 1 : -1);
				}

				if ($category == 'dev') {
					return $versions[1]['dev'] ? ($versions[2]['type'] == 'stable' ? -1 : 0) : ($versions[1]['type'] == 'stable' ? 1 : 0);
				}

				// Otherwise a simple comparison.
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
	public static function parse_path(string $path): string
	{
		$dirs = [
			'\\' => '/',
			'$boarddir' => Config::$boarddir,
			'$sourcedir' => Config::$sourcedir,
			'$avatardir' => Config::$modSettings['avatar_directory'],
			'$avatars_dir' => Config::$modSettings['avatar_directory'],
			'$themedir' => Theme::$current->settings['default_theme_dir'],
			'$imagesdir' => Theme::$current->settings['default_theme_dir'] . '/' . basename(Theme::$current->settings['default_images_url']),
			'$themes_dir' => Config::$boarddir . '/Themes',
			'$languagedir' => Theme::$current->settings['default_theme_dir'] . '/languages',
			'$languages_dir' => Theme::$current->settings['default_theme_dir'] . '/languages',
			'$smileysdir' => Config::$modSettings['smileys_dir'],
			'$smileys_dir' => Config::$modSettings['smileys_dir'],
		];

		// do we parse in a package directory?
		if (!empty(self::$temp_path)) {
			$dirs['$package'] = self::$temp_path;
		}

		if (strlen($path) == 0) {
			Lang::load('Errors');
			trigger_error(Lang::$txt['parse_path_filename_required'], E_USER_ERROR);
		}

		return strtr($path, $dirs);
	}

	/**
	 * Deletes a directory, and all the files and directories inside it.
	 * requires access to delete these files.
	 *
	 * @param string $dir A directory
	 * @param bool $delete_dir If false, only deletes everything inside the directory but not the directory itself
	 */
	public static function deltree(string $dir, bool $delete_dir = true): void
	{
		if (!file_exists($dir)) {
			return;
		}

		$current_dir = @opendir($dir);

		if ($current_dir == false) {
			if ($delete_dir && isset(self::$package_ftp)) {
				$ftp_file = strtr($dir, [$_SESSION['pack_ftp']['root'] => '']);

				if (!is_dir($dir)) {
					self::$package_ftp->chmod($ftp_file, 0777);
				}
				self::$package_ftp->unlink($ftp_file);
			}

			return;
		}

		while ($entryname = readdir($current_dir)) {
			if (in_array($entryname, ['.', '..'])) {
				continue;
			}

			if (is_dir($dir . '/' . $entryname)) {
				self::deltree($dir . '/' . $entryname);
			} else {
				// Here, 755 doesn't really matter since we're deleting it anyway.
				if (isset(self::$package_ftp)) {
					$ftp_file = strtr($dir . '/' . $entryname, [$_SESSION['pack_ftp']['root'] => '']);

					if (!is_writable($dir . '/' . $entryname)) {
						self::$package_ftp->chmod($ftp_file, 0777);
					}
					self::$package_ftp->unlink($ftp_file);
				} else {
					Utils::makeWritable($dir . '/' . $entryname);
					unlink($dir . '/' . $entryname);
				}
			}
		}

		closedir($current_dir);

		if ($delete_dir) {
			if (isset(self::$package_ftp)) {
				$ftp_file = strtr($dir, [$_SESSION['pack_ftp']['root'] => '']);

				if (!is_writable($dir . '/' . $entryname)) {
					self::$package_ftp->chmod($ftp_file, 0777);
				}

				self::$package_ftp->unlink($ftp_file);
			} else {
				Utils::makeWritable($dir);
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
	public static function mktree(string $strPath, int $mode): bool
	{
		if (is_dir($strPath)) {
			if (!is_writable($strPath) && $mode !== false) {
				if (isset(self::$package_ftp)) {
					self::$package_ftp->chmod(strtr($strPath, [$_SESSION['pack_ftp']['root'] => '']), $mode);
				} else {
					Utils::makeWritable($strPath, $mode);
				}
			}

			$test = @opendir($strPath);

			if ($test) {
				closedir($test);

				return is_writable($strPath);
			}

			return false;
		}

		// Is this an invalid path and/or we can't make the directory?
		if ($strPath == dirname($strPath) || !self::mktree(dirname($strPath), $mode)) {
			return false;
		}

		if (!is_writable(dirname($strPath)) && $mode !== false) {
			if (isset(self::$package_ftp)) {
				self::$package_ftp->chmod(dirname(strtr($strPath, [$_SESSION['pack_ftp']['root'] => ''])), $mode);
			} else {
				Utils::makeWritable(dirname($strPath), $mode);
			}
		}

		if ($mode !== false && isset(self::$package_ftp)) {
			return self::$package_ftp->create_dir(strtr($strPath, [$_SESSION['pack_ftp']['root'] => '']));
		}

		if ($mode === false) {
			$test = @opendir(dirname($strPath));

			if ($test) {
				closedir($test);

				return true;
			}

			return false;
		}

		@mkdir($strPath, $mode);
		$test = @opendir($strPath);

		if ($test) {
			closedir($test);

			return true;
		}

		return false;
	}

	/**
	 * Copies one directory structure over to another.
	 * requires the destination to be writable.
	 *
	 * @param string $source The directory to copy
	 * @param string $destination The directory to copy $source to
	 */
	public static function copytree(string $source, string $destination): void
	{
		if (!file_exists($destination) || !is_writable($destination)) {
			self::mktree($destination, 0755);
		}

		if (!is_writable($destination)) {
			self::mktree($destination, 0777);
		}

		$current_dir = opendir($source);

		if ($current_dir == false) {
			return;
		}

		while ($entryname = readdir($current_dir)) {
			if (in_array($entryname, ['.', '..'])) {
				continue;
			}

			if (isset(self::$package_ftp)) {
				$ftp_file = strtr($destination . '/' . $entryname, [$_SESSION['pack_ftp']['root'] => '']);
			}

			if (is_file($source . '/' . $entryname)) {
				if (isset(self::$package_ftp) && !file_exists($destination . '/' . $entryname)) {
					self::$package_ftp->create_file($ftp_file);
				} elseif (!file_exists($destination . '/' . $entryname)) {
					@touch($destination . '/' . $entryname);
				}
			}

			self::package_chmod($destination . '/' . $entryname);

			if (is_dir($source . '/' . $entryname)) {
				self::copytree($source . '/' . $entryname, $destination . '/' . $entryname);
			} elseif (file_exists($destination . '/' . $entryname)) {
				self::package_put_contents($destination . '/' . $entryname, self::package_get_contents($source . '/' . $entryname));
			} else {
				copy($source . '/' . $entryname, $destination . '/' . $entryname);
			}
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
	public static function listtree(string $path, string $sub_path = ''): array
	{
		$data = [];

		$dir = @dir($path . $sub_path);

		if (!$dir) {
			return [];
		}

		while ($entry = $dir->read()) {
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			if (is_dir($path . $sub_path . '/' . $entry)) {
				$data = array_merge($data, self::listtree($path, $sub_path . '/' . $entry));
			} else {
				$data[] = [
					'filename' => $sub_path == '' ? $entry : $sub_path . '/' . $entry,
					'size' => filesize($path . $sub_path . '/' . $entry),
					'skipped' => false,
				];
			}
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
	public static function parseModification(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		@set_time_limit(600);
		$xml = new XmlArray(strtr($file, ["\r" => '']));
		$actions = [];
		$everything_found = true;

		if (!$xml->exists('modification') || !$xml->exists('modification/file')) {
			$actions[] = [
				'type' => 'error',
				'filename' => '-',
				'debug' => Lang::$txt['package_modification_malformed'],
			];

			return $actions;
		}

		// Get the XML data.
		$files = $xml->set('modification/file');

		// Use this for holding all the template changes in this mod.
		$template_changes = [];
		// This is needed to hold the long paths, as they can vary...
		$long_changes = [];

		// First, we need to build the list of all the files likely to get changed.
		foreach ($files as $file) {
			// What is the filename we're currently on?
			$filename = self::parse_path(trim($file->fetch('@name')));

			// Now, we need to work out whether this is even a template file...
			foreach ($theme_paths as $id => $theme) {
				// If this filename is relative, if so take a guess at what it should be.
				$real_filename = $filename;

				if (strpos($filename, 'Themes') === 0) {
					$real_filename = Config::$boarddir . '/' . $filename;
				}

				if (strpos($real_filename, $theme['theme_dir']) === 0) {
					$template_changes[$id][] = substr($real_filename, strlen($theme['theme_dir']) + 1);
					$long_changes[$id][] = $filename;
				}
			}
		}

		// Custom themes to add.
		$custom_themes_add = [];

		// If we have some template changes, we need to build a master link of what new ones are required for the custom themes.
		if (!empty($template_changes[1])) {
			foreach ($theme_paths as $id => $theme) {
				// Default is getting done anyway, so no need for involvement here.
				if ($id == 1) {
					continue;
				}

				// For every template, do we want it? Yea, no, maybe?
				foreach ($template_changes[1] as $index => $template_file) {
					// What, it exists and we haven't already got it?! Lordy, get it in!
					if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id]) || !in_array($template_file, $template_changes[$id]))) {
						// Now let's add it to the "todo" list.
						$custom_themes_add[$long_changes[1][$index]][$id] = $theme['theme_dir'] . '/' . $template_file;
					}
				}
			}
		}

		foreach ($files as $file) {
			// This is the actual file referred to in the XML document...
			$files_to_change = [
				1 => self::parse_path(trim($file->fetch('@name'))),
			];

			// Sometimes though, we have some additional files for other themes, if we have add them to the mix.
			if (isset($custom_themes_add[$files_to_change[1]])) {
				$files_to_change += $custom_themes_add[$files_to_change[1]];
			}

			// Now, loop through all the files we're changing, and, well, change them ;)
			foreach ($files_to_change as $theme => $working_file) {
				if ($working_file[0] != '/' && $working_file[1] != ':') {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['parse_modification_filename_not_full_path'], $working_file), E_USER_WARNING);

					$working_file = Config::$boarddir . '/' . $working_file;
				}

				// Doesn't exist - give an error or what?
				if (!file_exists($working_file) && (!$file->exists('@error') || !in_array(trim($file->fetch('@error')), ['ignore', 'skip']))) {
					$actions[] = [
						'type' => 'missing',
						'filename' => $working_file,
						'debug' => Lang::$txt['package_modification_missing'],
					];

					$everything_found = false;

					continue;
				}

				// Skip the file if it doesn't exist.
				if (!file_exists($working_file) && $file->exists('@error') && trim($file->fetch('@error')) == 'skip') {
					$actions[] = [
						'type' => 'skipping',
						'filename' => $working_file,
					];

					continue;
				}

				// Okay, we're creating this file then...?
				if (!file_exists($working_file)) {
					$working_data = '';
				}
				// Phew, it exists!  Load 'er up!
				else {
					$working_data = str_replace("\r", '', self::package_get_contents($working_file));
				}

				$actions[] = [
					'type' => 'opened',
					'filename' => $working_file,
				];

				$operations = $file->exists('operation') ? $file->set('operation') : [];

				foreach ($operations as $operation) {
					// Convert operation to an array.
					$actual_operation = [
						'searches' => [],
						'error' => $operation->exists('@error') && in_array(trim($operation->fetch('@error')), ['ignore', 'fatal', 'required']) ? trim($operation->fetch('@error')) : 'fatal',
					];

					// The 'add' parameter is used for all searches in this operation.
					$add = $operation->exists('add') ? $operation->fetch('add') : '';

					// Grab all search items of this operation (in most cases just 1).
					$searches = $operation->set('search');

					foreach ($searches as $i => $search) {
						$actual_operation['searches'][] = [
							'position' => $search->exists('@position') && in_array(trim($search->fetch('@position')), ['before', 'after', 'replace', 'end']) ? trim($search->fetch('@position')) : 'replace',
							'is_reg_exp' => $search->exists('@regexp') && trim($search->fetch('@regexp')) === 'true',
							'loose_whitespace' => $search->exists('@whitespace') && trim($search->fetch('@whitespace')) === 'loose',
							'search' => $search->fetch('.'),
							'add' => $add,
							'preg_search' => '',
							'preg_replace' => '',
						];
					}

					// At least one search should be defined.
					if (empty($actual_operation['searches'])) {
						$actions[] = [
							'type' => 'failure',
							'filename' => $working_file,
							'search' => $search['search'],
							'is_custom' => $theme > 1 ? $theme : 0,
						];

						// Skip to the next operation.
						continue;
					}

					// Reverse the operations in case of undoing stuff.
					if ($undo) {
						foreach ($actual_operation['searches'] as $i => $search) {
							// Reverse modification of regular expressions are not allowed.
							if ($search['is_reg_exp']) {
								if ($actual_operation['error'] === 'fatal') {
									$actions[] = [
										'type' => 'failure',
										'filename' => $working_file,
										'search' => $search['search'],
										'is_custom' => $theme > 1 ? $theme : 0,
									];
								}

								// Continue to the next operation.
								continue 2;
							}

							// The replacement is now the search subject...
							if ($search['position'] === 'replace' || $search['position'] === 'end') {
								$actual_operation['searches'][$i]['search'] = $search['add'];
							} else {
								// Reversing a before/after modification becomes a replacement.
								$actual_operation['searches'][$i]['position'] = 'replace';

								if ($search['position'] === 'before') {
									$actual_operation['searches'][$i]['search'] .= $search['add'];
								} elseif ($search['position'] === 'after') {
									$actual_operation['searches'][$i]['search'] = $search['add'] . $search['search'];
								}
							}

							// ...and the search subject is now the replacement.
							$actual_operation['searches'][$i]['add'] = $search['search'];
						}
					}

					// Sort the search list so the replaces come before the add before/after's.
					if (count($actual_operation['searches']) !== 1) {
						$replacements = [];

						foreach ($actual_operation['searches'] as $i => $search) {
							if ($search['position'] === 'replace') {
								$replacements[] = $search;
								unset($actual_operation['searches'][$i]);
							}
						}
						$actual_operation['searches'] = array_merge($replacements, $actual_operation['searches']);
					}

					// Create regular expression replacements from each search.
					foreach ($actual_operation['searches'] as $i => $search) {
						// Not much needed if the search subject is already a regexp.
						if ($search['is_reg_exp']) {
							$actual_operation['searches'][$i]['preg_search'] = $search['search'];
						} else {
							// Make the search subject fit into a regular expression.
							$actual_operation['searches'][$i]['preg_search'] = preg_quote($search['search'], '~');

							// Using 'loose', a random amount of tabs and spaces may be used.
							if ($search['loose_whitespace']) {
								$actual_operation['searches'][$i]['preg_search'] = preg_replace('~[ \t]+~', '[ \t]+', $actual_operation['searches'][$i]['preg_search']);
							}
						}

						// Shuzzup.  This is done so we can safely use a regular expression. ($0 is bad!!)
						$actual_operation['searches'][$i]['preg_replace'] = strtr($search['add'], ['$' => '[$PACK' . 'AGE1$]', '\\' => '[$PACK' . 'AGE2$]']);

						// Before, so the replacement comes after the search subject :P
						if ($search['position'] === 'before') {
							$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
							$actual_operation['searches'][$i]['preg_replace'] = '$1' . $actual_operation['searches'][$i]['preg_replace'];
						}

						// After, after what?
						elseif ($search['position'] === 'after') {
							$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
							$actual_operation['searches'][$i]['preg_replace'] .= '$1';
						}

						// Position the replacement at the end of the file (or just before the closing PHP tags).
						elseif ($search['position'] === 'end') {
							if ($undo) {
								$actual_operation['searches'][$i]['preg_replace'] = '';
							} else {
								$actual_operation['searches'][$i]['preg_search'] = '(\\n\\?\\>)?$';
								$actual_operation['searches'][$i]['preg_replace'] .= '$1';
							}
						}

						// Testing 1, 2, 3...
						$failed = preg_match('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $working_data) === 0;

						// Nope, search pattern not found.
						if ($failed && $actual_operation['error'] === 'fatal') {
							$actions[] = [
								'type' => 'failure',
								'filename' => $working_file,
								'search' => $actual_operation['searches'][$i]['preg_search'],
								'search_original' => $actual_operation['searches'][$i]['search'],
								'replace_original' => $actual_operation['searches'][$i]['add'],
								'position' => $search['position'],
								'is_custom' => $theme > 1 ? $theme : 0,
								'failed' => $failed,
							];

							$everything_found = false;

							continue;
						}

						// Found, but in this case, that means failure!
						if (!$failed && $actual_operation['error'] === 'required') {
							$actions[] = [
								'type' => 'failure',
								'filename' => $working_file,
								'search' => $actual_operation['searches'][$i]['preg_search'],
								'search_original' => $actual_operation['searches'][$i]['search'],
								'replace_original' => $actual_operation['searches'][$i]['add'],
								'position' => $search['position'],
								'is_custom' => $theme > 1 ? $theme : 0,
								'failed' => $failed,
							];

							$everything_found = false;

							continue;
						}

						// Replace it into nothing? That's not an option...unless it's an undoing end.
						if ($search['add'] === '' && ($search['position'] !== 'end' || !$undo)) {
							continue;
						}

						// Finally, we're doing some replacements.
						$working_data = preg_replace('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $actual_operation['searches'][$i]['preg_replace'], $working_data, 1);

						$actions[] = [
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
						];
					}
				}

				// Fix any little helper symbols ;).
				$working_data = strtr($working_data, ['[$PACK' . 'AGE1$]' => '$', '[$PACK' . 'AGE2$]' => '\\']);

				self::package_chmod($working_file);

				if ((file_exists($working_file) && !is_writable($working_file)) || (!file_exists($working_file) && !is_writable(dirname($working_file)))) {
					$actions[] = [
						'type' => 'chmod',
						'filename' => $working_file,
					];
				}

				if (basename($working_file) == basename(SMF_SETTINGS_BACKUP_FILE)) {
					continue;
				}

				if (!$testing && !empty(Config::$modSettings['package_make_backups']) && file_exists($working_file)) {
					// No, no, not Settings.php!
					if (basename($working_file) == basename(SMF_SETTINGS_FILE)) {
						@copy($working_file, dirname($working_file) . '/' . basename(SMF_SETTINGS_BACKUP_FILE));
					} else {
						@copy($working_file, $working_file . '~');
					}
				}

				// Always call this, even if in testing, because it won't really be written in testing mode.
				self::package_put_contents($working_file, $working_data, $testing);

				$actions[] = [
					'type' => 'saved',
					'filename' => $working_file,
					'is_custom' => $theme > 1 ? $theme : 0,
				];
			}
		}

		$actions[] = [
			'type' => 'result',
			'status' => $everything_found,
		];

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
	public static function parseBoardMod(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		@set_time_limit(600);
		$file = strtr($file, ["\r" => '']);

		$working_file = null;
		$working_search = null;
		$working_data = '';
		$replace_with = null;

		$actions = [];
		$everything_found = true;

		// This holds all the template changes in the standard mod file.
		$template_changes = [];
		// This is just the temporary file.
		$temp_file = $file;
		// This holds the actual changes on a step counter basis.
		$temp_changes = [];
		$counter = 0;
		$step_counter = 0;

		// Before we do *anything*, let's build a list of what we're editing, as it's going to be used for other theme edits.
		while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $temp_file, $code_match) != 0) {
			$counter++;

			// Get rid of the old stuff.
			$temp_file = substr_replace($temp_file, '', strpos($temp_file, $code_match[0]), strlen($code_match[0]));

			// No interest to us?
			if ($code_match[1] != 'edit file' && $code_match[1] != 'file') {
				// It's a step, let's add that to the current steps.
				if (isset($temp_changes[$step_counter])) {
					$temp_changes[$step_counter]['changes'][] = $code_match[0];
				}

				continue;
			}

			// We've found a new edit - let's make ourself heard, kind of.
			$step_counter = $counter;
			$temp_changes[$step_counter] = [
				'title' => $code_match[0],
				'changes' => [],
			];

			$filename = self::parse_path($code_match[2]);

			// Now, is this a template file, and if so, which?
			foreach ($theme_paths as $id => $theme) {
				// If this filename is relative, if so take a guess at what it should be.
				if (strpos($filename, 'Themes') === 0) {
					$filename = Config::$boarddir . '/' . $filename;
				}

				if (strpos($filename, $theme['theme_dir']) === 0) {
					$template_changes[$id][$counter] = substr($filename, strlen($theme['theme_dir']) + 1);
				}
			}
		}

		// Reference for what theme ID this action belongs to.
		$theme_id_ref = [];

		// Now we know what templates we need to touch, cycle through each theme and work out what we need to edit.
		if (!empty($template_changes[1])) {
			foreach ($theme_paths as $id => $theme) {
				// Don't do default, it means nothing to me.
				if ($id == 1) {
					continue;
				}

				// Now, for each file do we need to edit it?
				foreach ($template_changes[1] as $pos => $template_file) {
					// It does? Add it to the list darlin'.
					if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id][$pos]) || !in_array($template_file, (array) $template_changes[$id][$pos]))) {
						// Actually add it to the mod file too, so we can see that it will work ;)
						if (!empty($temp_changes[$pos]['changes'])) {
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

		while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $file, $code_match) != 0) {
			// This is for working out what we should be editing.
			$counter++;

			// Edit a specific file.
			if ($code_match[1] == 'file' || $code_match[1] == 'edit file') {
				// Backup the old file.
				if ($working_file !== null) {
					self::package_chmod($working_file);

					// Don't even dare.
					if (basename($working_file) == basename(SMF_SETTINGS_BACKUP_FILE)) {
						continue;
					}

					if (!is_writable($working_file)) {
						$actions[] = [
							'type' => 'chmod',
							'filename' => $working_file,
						];
					}

					if (!$testing && !empty(Config::$modSettings['package_make_backups']) && file_exists($working_file)) {
						if (basename($working_file) == basename(SMF_SETTINGS_FILE)) {
							@copy($working_file, dirname($working_file) . '/' . basename(SMF_SETTINGS_BACKUP_FILE));
						} else {
							@copy($working_file, $working_file . '~');
						}
					}

					self::package_put_contents($working_file, $working_data, $testing);
				}

				if ($working_file !== null) {
					$actions[] = [
						'type' => 'saved',
						'filename' => $working_file,
						'is_custom' => $is_custom,
					];
				}

				// Is this "now working on" file a theme specific one?
				$is_custom = $theme_id_ref[$counter - 1] ?? 0;

				// Make sure the file exists!
				$working_file = self::parse_path($code_match[2]);

				if ($working_file[0] != '/' && $working_file[1] != ':') {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['parse_boardmod_filename_not_full_path'], $working_file), E_USER_WARNING);

					$working_file = Config::$boarddir . '/' . $working_file;
				}

				if (!file_exists($working_file)) {
					$places_to_check = [Config::$boarddir, Config::$sourcedir, Theme::$current->settings['default_theme_dir'], Theme::$current->settings['default_theme_dir'] . '/languages'];

					foreach ($places_to_check as $place) {
						if (file_exists($place . '/' . $working_file)) {
							$working_file = $place . '/' . $working_file;
							break;
						}
					}
				}

				if (file_exists($working_file)) {
					// Load the new file.
					$working_data = str_replace("\r", '', self::package_get_contents($working_file));

					$actions[] = [
						'type' => 'opened',
						'filename' => $working_file,
					];
				} else {
					$actions[] = [
						'type' => 'missing',
						'filename' => $working_file,
					];

					$working_file = null;
					$everything_found = false;
				}

				// Can't be searching for something...
				$working_search = null;
			}
			// Search for a specific string.
			elseif (($code_match[1] == 'search' || $code_match[1] == 'search for') && $working_file !== null) {
				if ($working_search !== null) {
					$actions[] = [
						'type' => 'error',
						'filename' => $working_file,
					];

					$everything_found = false;
				}

				$working_search = $code_match[2];
			}
			// Must've already loaded a search string.
			elseif ($working_search !== null) {
				// This is the base string....
				$replace_with = $code_match[2];

				// Add this afterward...
				if ($code_match[1] == 'add' || $code_match[1] == 'add after') {
					$replace_with = $working_search . "\n" . $replace_with;
				}
				// Add this beforehand.
				elseif ($code_match[1] == 'before' || $code_match[1] == 'add before' || $code_match[1] == 'above' || $code_match[1] == 'add above') {
					$replace_with .= "\n" . $working_search;
				}
				// Otherwise.. replace with $replace_with ;).
			}

			// If we have a search string, replace string, and open file..
			if ($working_search !== null && $replace_with !== null && $working_file !== null) {
				// Make sure it's somewhere in the string.
				if ($undo) {
					$temp = $replace_with;
					$replace_with = $working_search;
					$working_search = $temp;
				}

				if (strpos($working_data, $working_search) !== false) {
					$working_data = str_replace($working_search, $replace_with, $working_data);

					$actions[] = [
						'type' => 'replace',
						'filename' => $working_file,
						'search' => $working_search,
						'replace' => $replace_with,
						'search_original' => $working_search,
						'replace_original' => $replace_with,
						'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
						'is_custom' => $is_custom,
						'failed' => false,
					];
				}
				// It wasn't found!
				else {
					$actions[] = [
						'type' => 'failure',
						'filename' => $working_file,
						'search' => $working_search,
						'is_custom' => $is_custom,
						'search_original' => $working_search,
						'replace_original' => $replace_with,
						'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
						'failed' => true,
					];

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
		if ($working_file !== null) {
			self::package_chmod($working_file);

			if (!is_writable($working_file)) {
				$actions[] = [
					'type' => 'chmod',
					'filename' => $working_file,
				];
			}

			if (!$testing && !empty(Config::$modSettings['package_make_backups']) && file_exists($working_file)) {
				if (basename($working_file) == basename(SMF_SETTINGS_FILE)) {
					@copy($working_file, dirname($working_file) . '/' . basename(SMF_SETTINGS_BACKUP_FILE));
				} else {
					@copy($working_file, $working_file . '~');
				}
			}

			self::package_put_contents($working_file, $working_data, $testing);
		}

		if ($working_file !== null) {
			$actions[] = [
				'type' => 'saved',
				'filename' => $working_file,
				'is_custom' => $is_custom,
			];
		}

		$actions[] = [
			'type' => 'result',
			'status' => $everything_found,
		];

		return $actions;
	}

	/**
	 * Get the physical contents of a packages file
	 *
	 * @param string $filename The package file
	 * @return string The contents of the specified file
	 */
	public static function package_get_contents(string $filename): string
	{
		if (!isset(self::$package_cache)) {
			$mem_check = Config::setMemoryLimit('128M');

			// Windows doesn't seem to care about the memory_limit.
			if (!empty(Config::$modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false) {
				self::$package_cache = [];
			} else {
				self::$package_cache = false;
			}
		}

		if (strpos($filename, 'Packages/') !== false || self::$package_cache === false || !isset(self::$package_cache[$filename])) {
			return file_get_contents($filename);
		}

		return self::$package_cache[$filename];
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
	public static function package_put_contents(string $filename, string $data, bool $testing = false): int
	{
		static $text_filetypes = ['php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm'];

		if (!isset(self::$package_cache)) {
			// Try to increase the memory limit - we don't want to run out of ram!
			$mem_check = Config::setMemoryLimit('128M');

			if (!empty(Config::$modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false) {
				self::$package_cache = [];
			} else {
				self::$package_cache = false;
			}
		}

		if (isset(self::$package_ftp)) {
			$ftp_file = strtr($filename, [$_SESSION['pack_ftp']['root'] => '']);
		}

		if (!file_exists($filename) && isset(self::$package_ftp)) {
			self::$package_ftp->create_file($ftp_file);
		} elseif (!file_exists($filename)) {
			@touch($filename);
		}

		self::package_chmod($filename);

		if (!$testing && (strpos($filename, 'Packages/') !== false || self::$package_cache === false)) {
			$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

			// We should show an error message or attempt a rollback, no?
			if (!$fp) {
				return false;
			}

			fwrite($fp, $data);
			fclose($fp);
		} elseif (strpos($filename, 'Packages/') !== false || self::$package_cache === false) {
			return strlen($data);
		} else {
			self::$package_cache[$filename] = $data;

			// Permission denied, eh?
			$fp = @fopen($filename, 'r+');

			if (!$fp) {
				return false;
			}
			fclose($fp);
		}

		return strlen($data);
	}

	/**
	 * Flushes the cache from memory to the filesystem
	 *
	 * @param bool $trash
	 */
	public static function package_flush_cache(bool $trash = false): void
	{
		static $text_filetypes = ['php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm'];

		if (empty(self::$package_cache)) {
			return;
		}

		// First, let's check permissions!
		foreach (self::$package_cache as $filename => $data) {
			if (isset(self::$package_ftp)) {
				$ftp_file = strtr($filename, [$_SESSION['pack_ftp']['root'] => '']);
			}

			if (!file_exists($filename) && isset(self::$package_ftp)) {
				self::$package_ftp->create_file($ftp_file);
			} elseif (!file_exists($filename)) {
				@touch($filename);
			}

			$result = self::package_chmod($filename);

			// if we are not doing our test pass, then lets do a full write check
			// bypass directories when doing this test
			if ((!$trash) && !is_dir($filename)) {
				// acid test, can we really open this file for writing?
				$fp = ($result) ? fopen($filename, 'r+') : $result;

				if (!$fp) {
					// We should have package_chmod()'d them before, no?!
					Lang::load('Errors');
					trigger_error(Lang::$txt['package_flush_cache_not_writable'], E_USER_WARNING);

					return;
				}
				fclose($fp);
			}
		}

		if ($trash) {
			self::$package_cache = [];

			return;
		}

		// Write the cache to disk here.
		// Bypass directories when doing so - no data to write & the fopen will crash.
		foreach (self::$package_cache as $filename => $data) {
			if (!is_dir($filename)) {
				$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
				fwrite($fp, $data);
				fclose($fp);
			}
		}

		self::$package_cache = [];
	}

	/**
	 * Try to make a file writable.
	 *
	 * @param string $filename The name of the file
	 * @param string $perm_state The permission state - can be either 'writable' or 'execute'
	 * @param bool $track_change Whether to track this change
	 * @return bool True if it worked, false if it didn't
	 */
	public static function package_chmod(string $filename, string $perm_state = 'writable', bool $track_change = false): bool
	{
		if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable') {
			return true;
		}

		// Start off checking without FTP.
		if (!isset(self::$package_ftp) || self::$package_ftp === false) {
			for ($i = 0; $i < 2; $i++) {
				$chmod_file = $filename;

				// Start off with a less aggressive test.
				if ($i == 0) {
					// If this file doesn't exist, then we actually want to look at whatever parent directory does.
					$subTraverseLimit = 2;

					while (!file_exists($chmod_file) && $subTraverseLimit) {
						$chmod_file = dirname($chmod_file);
						$subTraverseLimit--;
					}

					// Keep track of the writable status here.
					$file_permissions = @fileperms($chmod_file);
				} else {
					// This looks odd, but it's an attempt to work around PHP suExec.
					if (!file_exists($chmod_file) && $perm_state == 'writable') {
						$file_permissions = @fileperms(dirname($chmod_file));

						self::mktree(dirname($chmod_file), 0755);
						@touch($chmod_file);
						Utils::makeWritable($chmod_file, 0755);
					} else {
						$file_permissions = @fileperms($chmod_file);
					}
				}

				Utils::makeWritable($chmod_file);

				// The ultimate writable test.
				if ($perm_state == 'writable') {
					$fp = is_dir($chmod_file) ? @opendir($chmod_file) : @fopen($chmod_file, 'rb');

					if (@is_writable($chmod_file) && $fp) {
						if (!is_dir($chmod_file)) {
							fclose($fp);
						} else {
							closedir($fp);
						}

						// It worked!
						if ($track_change) {
							$_SESSION['pack_ftp']['original_perms'][$chmod_file] = $file_permissions;
						}

						return true;
					}
				} elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$chmod_file])) {
					unset($_SESSION['pack_ftp']['original_perms'][$chmod_file]);
				}
			}

			// If we're here we're a failure.
			return false;
		}

		// Otherwise we do have FTP?
		if (self::$package_ftp !== false && !empty($_SESSION['pack_ftp'])) {
			$ftp_file = strtr($filename, [$_SESSION['pack_ftp']['root'] => '']);

			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($filename) && $perm_state == 'writable') {
				$file_permissions = @fileperms(dirname($filename));

				self::mktree(dirname($filename), 0755);
				self::$package_ftp->create_file($ftp_file);
				self::$package_ftp->chmod($ftp_file, 0755);
			} else {
				$file_permissions = @fileperms($filename);
			}

			if ($perm_state != 'writable') {
				self::$package_ftp->chmod($ftp_file, $perm_state == 'execute' ? 0755 : 0644);
			} else {
				if (!@is_writable($filename)) {
					self::$package_ftp->chmod($ftp_file, 0777);
				}

				if (!@is_writable(dirname($filename))) {
					self::$package_ftp->chmod(dirname($ftp_file), 0777);
				}
			}

			if (@is_writable($filename)) {
				if ($track_change) {
					$_SESSION['pack_ftp']['original_perms'][$filename] = $file_permissions;
				}

				return true;
			}

			if ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$filename])) {
				unset($_SESSION['pack_ftp']['original_perms'][$filename]);
			}
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
	public static function package_crypt(
		#[\SensitiveParameter]
		string $pass,
	): string {
		$n = strlen($pass);

		$salt = session_id();

		while (strlen($salt) < $n) {
			$salt .= session_id();
		}

		for ($i = 0; $i < $n; $i++) {
			$pass[$i] = chr(ord($pass[$i]) ^ (ord($salt[$i]) - 32));
		}

		return $pass;
	}

	/**
	 * @param string $dir
	 * @param string $filename The filename without an extension
	 * @param string $ext
	 * @return string The filename with a number appended but no extension
	 * @since 2.1
	 */
	public static function package_unique_filename(string $dir, string $filename, string $ext): string
	{
		if (file_exists($dir . '/' . $filename . '.' . $ext)) {
			$i = 1;

			while (file_exists($dir . '/' . $filename . '_' . $i . '.' . $ext)) {
				$i++;
			}
			$filename .= '_' . $i;
		}

		return $filename;
	}

	/**
	 * Creates a backup of forum files prior to modifying them
	 *
	 * @param string $id The name of the backup
	 * @return bool True if it worked, false if it didn't
	 */
	public static function package_create_backup(string $id = 'backup'): bool
	{
		$files = [];

		$base_files = ['index.php', 'SSI.php', 'agreement.txt', 'cron.php', 'proxy.php', 'ssi_examples.php', 'ssi_examples.shtml', 'subscriptions.php'];

		foreach ($base_files as $file) {
			if (file_exists(Config::$boarddir . '/' . $file)) {
				$files[empty($_REQUEST['use_full_paths']) ? $file : Config::$boarddir . '/' . $file] = Config::$boarddir . '/' . $file;
			}
		}

		$dirs = [
			Config::$sourcedir => empty($_REQUEST['use_full_paths']) ? 'Sources/' : strtr(Config::$sourcedir . '/', '\\', '/'),
		];

		$request = Db::$db->query(
			'',
			'SELECT value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {string:theme_dir}',
			[
				'no_member' => 0,
				'theme_dir' => 'theme_dir',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$dirs[$row['value']] = empty($_REQUEST['use_full_paths']) ? 'Themes/' . basename($row['value']) . '/' : strtr($row['value'] . '/', '\\', '/');
		}
		Db::$db->free_result($request);

		try {
			foreach ($dirs as $dir => $dest) {
				$iter = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::CHILD_FIRST,
					\RecursiveIteratorIterator::CATCH_GET_CHILD, // Ignore "Permission denied"
				);

				foreach ($iter as $entry => $dir) {
					if ($dir->isDir()) {
						continue;
					}

					if (preg_match('~^(\.{1,2}|CVS|backup.*|help|images|.*\~|.*minified_[a-z0-9]{32}\.(js|css))$~', $entry) != 0) {
						continue;
					}

					$files[empty($_REQUEST['use_full_paths']) ? str_replace(realpath(Config::$boarddir), '', $entry) : $entry] = $entry;
				}
			}
			$obj = new \ArrayObject($files);
			$iterator = $obj->getIterator();

			if (!file_exists(Config::$packagesdir . '/backups')) {
				self::mktree(Config::$packagesdir . '/backups', 0777);
			}

			if (!is_writable(Config::$packagesdir . '/backups')) {
				self::package_chmod(Config::$packagesdir . '/backups');
			}
			$output_file = Config::$packagesdir . '/backups/' . Time::strftime('%Y-%m-%d_') . preg_replace('~[$\\\\/:<>|?*"\']~', '', $id);
			$output_ext = '.tar';
			$output_ext_target = '.tar.gz';

			if (file_exists($output_file . $output_ext_target)) {
				$i = 2;

				while (file_exists($output_file . '_' . $i . $output_ext_target)) {
					$i++;
				}
				$output_file = $output_file . '_' . $i . $output_ext;
			} else {
				$output_file .= $output_ext;
			}

			@set_time_limit(300);

			if (function_exists('apache_reset_timeout')) {
				@apache_reset_timeout();
			}

			// Phar doesn't handle open_basedir restrictions very well and throws a PHP Warning. Ignore that.
			set_error_handler(
				function ($errno, $errstr, $errfile, $errline) {
					// error was suppressed with the @-operator
					if (0 === error_reporting()) {
						return false;
					}

					if (strpos($errstr, 'PharData::__construct(): open_basedir') === false && strpos($errstr, 'PharData::compress(): open_basedir') === false) {
						ErrorHandler::log($errstr, 'general', $errfile, $errline);
					}

					return true;
				},
			);
			$a = new \PharData($output_file);
			$a->buildFromIterator($iterator);
			$a->compress(\Phar::GZ);
			restore_error_handler();

			/*
			 * Destroying the local var tells PharData to close its internal
			 * file pointer, enabling us to delete the uncompressed tarball.
			 */
			unset($a);
			unlink($output_file);
		} catch (\Exception $e) {
			ErrorHandler::log($e->getMessage(), 'backup');

			return false;
		}

		return true;
	}

	/**
	 * Validate a package during install
	 *
	 * @param array $package Package data
	 * @return array Results from the package validation.
	 */
	public static function package_validate_installtest(array $package): array
	{
		// Don't validate directories.
		Utils::$context['package_sha256_hash'] = is_dir($package['file_name']) ? null : hash_file('sha256', $package['file_name']);

		$sendData = [[
			'sha256_hash' => Utils::$context['package_sha256_hash'],
			'file_name' => basename($package['file_name']),
			'custom_id' => $package['custom_id'],
			'custom_type' => $package['custom_type'],
		]];

		return self::package_validate_send($sendData);
	}

	/**
	 * Validate multiple packages.
	 *
	 * @param array $packages Package data
	 * @return array Results from the package validation.
	 */
	public static function package_validate(array $packages): array
	{
		// Setup our send data.
		$sendData = [];

		// Go through all packages and get them ready to send up.
		foreach ($packages as $id_package => $package) {
			$sha256_hash = hash_file('sha256', $package);
			$packageInfo = self::getPackageInfo($package);

			$packageID = '';

			if (isset($packageInfo['id'])) {
				$packageID = $packageInfo['id'];
			}

			$packageType = 'modification';

			if (isset($package['type'])) {
				$packageType = $package['type'];
			}

			$sendData[] = [
				'sha256_hash' => $sha256_hash,
				'file_name' => basename($package),
				'custom_id' => $packageID,
				'custom_type' => $packageType,
			];
		}

		return self::package_validate_send($sendData);
	}

	/**
	 * Sending data off to validate packages.
	 *
	 * @param array $sendData Json encoded data to be sent to the validation servers.
	 * @return array Results from the package validation.
	 */
	public static function package_validate_send(array $sendData): array
	{
		// First lets get all package servers into here.
		if (empty(Utils::$context['package_servers'])) {

			$request = Db::$db->query(
				'',
				'SELECT id_server, name, validation_url, extra
				FROM {db_prefix}package_servers
				WHERE validation_url != {string:empty}',
				[
					'empty' => '',
				],
			);
			Utils::$context['package_servers'] = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['package_servers'][$row['id_server']] = $row;
			}
			Db::$db->free_result($request);
		}

		$the_version = SMF_VERSION;

		if (!empty($_SESSION['version_emulate'])) {
			$the_version = $_SESSION['version_emulate'];
		}

		// Test each server.
		$return_data = [];

		foreach (Utils::$context['package_servers'] as $id_server => $server) {
			$return_data[$id_server] = [];

			// Sub out any variables we support in the validation url.
			$validate_url = strtr($server['validation_url'], [
				'{SMF_VERSION}' => urlencode($the_version),
			]);

			$results = WebFetchApi::fetch($validate_url, 'data=' . json_encode($sendData));

			$parsed_data = Utils::jsonDecode($results, true);

			if (is_array($parsed_data) && isset($parsed_data['data']) && is_array($parsed_data['data'])) {
				foreach ($parsed_data['data'] as $sha256_hash => $status) {
					if ((string) $status === 'blacklist') {
						Utils::$context['package_blacklist_found'] = true;
					}

					$return_data[$id_server][(string) $sha256_hash] = 'package_validation_status_' . ((string) $status);
				}
			}
		}

		return $return_data;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks the permissions of all the areas that will be affected by the package
	 *
	 * @param string $path The path to the directory to check permissions for
	 * @param array $data An array of data about the directory
	 * @param int $level How far deep to go
	 */
	protected function fetchPerms__recursive(string $path, array &$data, int $level): void
	{
		$isLikelyPath = false;

		foreach (Utils::$context['look_for'] as $possiblePath) {
			if (substr($possiblePath, 0, strlen($path)) == $path) {
				$isLikelyPath = true;
			}
		}

		// Is this where we stop?
		if (isset($_GET['xml']) && !empty(Utils::$context['look_for']) && !$isLikelyPath) {
			return;
		}

		if ($level > Utils::$context['default_level'] && !$isLikelyPath) {
			return;
		}

		// Are we actually interested in saving this data?
		$save_data = empty(Utils::$context['only_find']) || Utils::$context['only_find'] == $path;

		// @todo Shouldn't happen - but better error message?
		if (!is_dir($path)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// This is where we put stuff we've found for sorting.
		$foundData = [
			'files' => [],
			'folders' => [],
		];

		$dh = opendir($path);

		while ($entry = readdir($dh)) {
			// Some kind of file?
			if (is_file($path . '/' . $entry)) {
				// Are we listing PHP files in this directory?
				if ($save_data && !empty($data['list_contents']) && substr($entry, -4) == '.php') {
					$foundData['files'][$entry] = true;
				}
				// A file we were looking for.
				elseif ($save_data && isset($data['contents'][$entry])) {
					$foundData['files'][$entry] = true;
				}
			}
			// It's a directory - we're interested one way or another, probably...
			elseif ($entry != '.' && $entry != '..') {
				// Going further?
				if ((!empty($data['type']) && $data['type'] == 'dir_recursive') || (isset($data['contents'][$entry]) && (!empty($data['contents'][$entry]['list_contents']) || (!empty($data['contents'][$entry]['type']) && $data['contents'][$entry]['type'] == 'dir_recursive')))) {
					if (!isset($data['contents'][$entry])) {
						$foundData['folders'][$entry] = 'dir_recursive';
					} else {
						$foundData['folders'][$entry] = true;
					}

					// If this wasn't expected inherit the recursiveness...
					if (!isset($data['contents'][$entry])) {
						// We need to do this as we will be going all recursive.
						$data['contents'][$entry] = [
							'type' => 'dir_recursive',
						];
					}

					// Actually do the recursive stuff...
					$this->fetchPerms__recursive($path . '/' . $entry, $data['contents'][$entry], $level + 1);
				}
				// Maybe it is a folder we are not descending into.
				elseif (isset($data['contents'][$entry])) {
					$foundData['folders'][$entry] = true;
				}
				// Otherwise we stop here.
			}
		}
		closedir($dh);

		// Nothing to see here?
		if (!$save_data) {
			return;
		}

		// Now actually add the data, starting with the folders.
		ksort($foundData['folders']);

		foreach ($foundData['folders'] as $folder => $type) {
			$additional_data = [
				'perms' => [
					'chmod' => @is_writable($path . '/' . $folder),
					'perms' => @fileperms($path . '/' . $folder),
				],
			];

			if ($type !== true) {
				$additional_data['type'] = $type;
			}

			// If there's an offset ignore any folders in XML mode.
			if (isset($_GET['xml']) && Utils::$context['file_offset'] == 0) {
				Utils::$context['xml_data']['folders']['children'][] = [
					'attributes' => [
						'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
						'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
						'folder' => 1,
						'path' => Utils::$context['only_find'],
						'level' => $level,
						'more' => 0,
						'offset' => Utils::$context['file_offset'],
						'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find'] . '/' . $folder),
						'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find']),
					],
					'value' => $folder,
				];
			} elseif (!isset($_GET['xml'])) {
				if (isset($data['contents'][$folder])) {
					$data['contents'][$folder] = array_merge($data['contents'][$folder], $additional_data);
				} else {
					$data['contents'][$folder] = $additional_data;
				}
			}
		}

		// Now we want to do a similar thing with files.
		ksort($foundData['files']);
		$counter = -1;

		foreach ($foundData['files'] as $file => $dummy) {
			$counter++;

			// Have we reached our offset?
			if (Utils::$context['file_offset'] > $counter) {
				continue;
			}

			// Gone too far?
			if ($counter > (Utils::$context['file_offset'] + Utils::$context['file_limit'])) {
				continue;
			}

			$additional_data = [
				'perms' => [
					'chmod' => @is_writable($path . '/' . $file),
					'perms' => @fileperms($path . '/' . $file),
				],
			];

			// XML?
			if (isset($_GET['xml'])) {
				Utils::$context['xml_data']['folders']['children'][] = [
					'attributes' => [
						'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
						'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
						'folder' => 0,
						'path' => Utils::$context['only_find'],
						'level' => $level,
						'more' => $counter == (Utils::$context['file_offset'] + Utils::$context['file_limit']) ? 1 : 0,
						'offset' => Utils::$context['file_offset'],
						'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find'] . '/' . $file),
						'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', Utils::$context['only_find']),
					],
					'value' => $file,
				];
			} elseif ($counter != (Utils::$context['file_offset'] + Utils::$context['file_limit'])) {
				if (isset($data['contents'][$file])) {
					$data['contents'][$file] = array_merge($data['contents'][$file], $additional_data);
				} else {
					$data['contents'][$file] = $additional_data;
				}
			}
		}
	}

	/**
	 * Counts all the directories under a given path
	 *
	 * @param string $dir
	 * @return int
	 */
	protected function count_directories__recursive(string $dir): int
	{
		$count = 0;
		$dh = @opendir($dir);

		while ($entry = readdir($dh)) {
			if ($entry != '.' && $entry != '..' && is_dir($dir . '/' . $entry)) {
				Utils::$context['directory_list'][$dir . '/' . $entry] = 1;
				$count++;
				$count += $this->count_directories__recursive($dir . '/' . $entry);
			}
		}
		closedir($dh);

		return $count;
	}

	/**
	 * Builds a list of special files recursively for a given path
	 *
	 * @param string $path
	 * @param array $data
	 */
	protected function build_special_files__recursive(string $path, array &$data): void
	{
		if (!empty($data['writable_on'])) {
			if (Utils::$context['predefined_type'] == 'standard' || $data['writable_on'] == 'restrictive') {
				Utils::$context['special_files'][$path] = 1;
			}
		}

		if (!empty($data['contents'])) {
			foreach ($data['contents'] as $name => $contents) {
				$this->build_special_files__recursive($path . '/' . $name, $contents);
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * crc32 doesn't work as expected on 64-bit functions - make our own.
	 * https://php.net/crc32#79567
	 *
	 * @param string $number
	 * @return string The crc32
	 */
	private static function smf_crc32(string $number): string
	{
		require_once Config::$sourcedir . '/Subs-Compat.php';

		return \smf_crc32($number);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SubsPackage::exportStatic')) {
	SubsPackage::exportStatic();
}

?>