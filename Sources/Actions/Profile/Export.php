<?php

/**
 * This file contains functions to export a member's profile data to a
 * downloadable file.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Feed;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Initiates exports a member's profile, posts, and personal messages to a file.
 *
 * @todo Add CSV, JSON as other possible export formats besides XML and HTML?
 *
 * @param int $uid The ID of the member whose data we're exporting.
 */
function export_profile_data($uid)
{
	if (!isset(Utils::$context['token_check']))
		Utils::$context['token_check'] = 'profile-ex' . $uid;

	Utils::$context['export_formats'] = get_export_formats();

	if (!isset($_POST['format']) || !isset(Utils::$context['export_formats'][$_POST['format']]))
		unset($_POST['format'], $_POST['delete'], $_POST['export_begin']);

	// This lists the types of data we can export and info for doing so.
	Utils::$context['export_datatypes'] = array(
		'profile' => array(
			'label' => null,
			'total' => 0,
			'latest' => 1,
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlProfile',
				'langfile' => 'Profile',
			),
			'HTML' => array(
				'func' => 'getXmlProfile',
				'langfile' => 'Profile',
			),
			'XML_XSLT' => array(
				'func' => 'getXmlProfile',
				'langfile' => 'Profile',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
		'posts' => array(
			'label' => Lang::$txt['export_include_posts'],
			'total' => Utils::$context['member']['real_posts'],
			'latest' => function($uid)
			{
				static $latest_post;

				if (isset($latest_post))
					return $latest_post;

				$query_this_board = !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? 'b.id_board != ' . Config::$modSettings['recycle_board'] : '1=1';

				$request = Db::$db->query('', '
					SELECT m.id_msg
					FROM {db_prefix}messages as m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					WHERE id_member = {int:uid}
						AND ' . $query_this_board . '
					ORDER BY id_msg DESC
					LIMIT {int:limit}',
					array(
						'limit' => 1,
						'uid' => $uid,
					)
				);
				list($latest_post) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				return $latest_post;
			},
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlPosts',
				'langfile' => 'Post',
			),
			'HTML' => array(
				'func' => 'getXmlPosts',
				'langfile' => 'Post',
			),
			'XML_XSLT' => array(
				'func' => 'getXmlPosts',
				'langfile' => 'Post',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
		'personal_messages' => array(
			'label' => Lang::$txt['export_include_personal_messages'],
			'total' => function($uid)
			{
				static $total_pms;

				if (isset($total_pms))
					return $total_pms;

				$request = Db::$db->query('', '
					SELECT COUNT(*)
					FROM {db_prefix}personal_messages AS pm
						INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
					WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})',
					array(
						'uid' => $uid,
						'not_deleted' => 0,
					)
				);
				list($total_pms) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				return $total_pms;
			},
			'latest' => function($uid)
			{
				static $latest_pm;

				if (isset($latest_pm))
					return $latest_pm;

				$request = Db::$db->query('', '
					SELECT pm.id_pm
					FROM {db_prefix}personal_messages AS pm
						INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
					WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})
					ORDER BY pm.id_pm DESC
					LIMIT {int:limit}',
					array(
						'limit' => 1,
						'uid' => $uid,
						'not_deleted' => 0,
					)
				);
				list($latest_pm) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				return $latest_pm;
			},
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlPMs',
				'langfile' => 'PersonalMessage',
			),
			'HTML' => array(
				'func' => 'getXmlPMs',
				'langfile' => 'PersonalMessage',
			),
			'XML_XSLT' => array(
				'func' => 'getXmlPMs',
				'langfile' => 'PersonalMessage',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
	);

	if (empty(Config::$modSettings['export_dir']) || !is_dir(Config::$modSettings['export_dir']) || !smf_chmod(Config::$modSettings['export_dir']))
		create_export_dir();

	$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $uid, Config::getAuthSecret());
	$dltoken = hash_hmac('sha1', $idhash, Config::getAuthSecret());

	Utils::$context['completed_exports'] = array();
	Utils::$context['active_exports'] = array();
	$existing_export_formats = array();
	$latest = array();

	foreach (Utils::$context['export_formats'] as $format => $format_settings)
	{
		$idhash_ext = $idhash . '.' . $format_settings['extension'];

		$done = null;
		Utils::$context['outdated_exports'][$idhash_ext] = array();

		// $realfile needs to be the highest numbered one, or 1_*** if none exist.
		$filenum = 1;
		$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;
		while (file_exists($export_dir_slash . ($filenum + 1) . '_' . $idhash_ext))
			$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

		$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
		$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

		// If requested by the user, delete any existing export files and background tasks.
		if (isset($_POST['delete']) && isset($_POST['format']) && $_POST['format'] === $format && isset($_POST['t']) && $_POST['t'] === $dltoken)
		{
			Db::$db->query('', '
				DELETE FROM {db_prefix}background_tasks
				WHERE task_class = {string:class}
					AND task_data LIKE {string:details}',
				array(
					'class' => 'SMF\Tasks\ExportProfileData',
					'details' => substr(Utils::jsonEncode(array('format' => $format, 'uid' => $uid)), 0, -1) . ',%',
				)
			);

			foreach (glob($export_dir_slash . '*' . $idhash_ext . '*') as $fpath)
				@unlink($fpath);

			if (empty($_POST['export_begin']))
				redirectexit('action=profile;area=getprofiledata;u=' . $uid);
		}

		$progress = file_exists($progressfile) ? Utils::jsonDecode(file_get_contents($progressfile), true) : array();

		if (!empty($progress))
			$included = array_keys($progress);
		else
			$included = array_intersect(array_keys(Utils::$context['export_datatypes']), array_keys($_POST));

		// If we're starting a new export in this format, we're done here.
		if (!empty($_POST['export_begin']) && isset($_POST['format']) && $_POST['format'] === $format)
			break;

		// The rest of this loop deals with current exports, if any.

		$included_desc = array();
		foreach ($included as $datatype)
			$included_desc[] = Lang::$txt[$datatype];

		$dlfilename = array_merge(array(Utils::$context['forum_name'], Utils::$context['member']['username']), $included_desc);
		$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

		if (file_exists($tempfile) && file_exists($progressfile))
		{
			$done = false;
		}
		elseif (file_exists($realfile))
		{
			// It looks like we're done.
			$done = true;

			// But let's check whether it's outdated.
			foreach (Utils::$context['export_datatypes'] as $datatype => $datatype_settings)
			{
				if (!isset($progress[$datatype]))
					continue;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($uid) : $datatype_settings['latest'];

				if ($latest[$datatype] > $progress[$datatype])
					Utils::$context['outdated_exports'][$idhash_ext][] = $datatype;
			}
		}

		if ($done === true)
		{
			$exportfilepaths = glob($export_dir_slash . '*_' . $idhash_ext);

			foreach ($exportfilepaths as $exportfilepath)
			{
				$exportbasename = basename($exportfilepath);

				$part = substr($exportbasename, 0, strcspn($exportbasename, '_'));
				$suffix = count($exportfilepaths) == 1 ? '' : '_' . $part;

				$size = filesize($exportfilepath) / 1024;
				$units = array('KB', 'MB', 'GB', 'TB');
				$unitkey = 0;
				while ($size > 1024)
				{
					$size = $size / 1024;
					$unitkey++;
				}
				$size = round($size, 2) . $units[$unitkey];

				Utils::$context['completed_exports'][$idhash_ext][$part] = array(
					'realname' => $exportbasename,
					'dlbasename' => $dlfilename . $suffix . '.' . $format_settings['extension'],
					'dltoken' => $dltoken,
					'included' => $included,
					'included_desc' => Lang::sentenceList($included_desc),
					'format' => $format,
					'mtime' => timeformat(filemtime($exportfilepath)),
					'size' => $size,
				);
			}

			ksort(Utils::$context['completed_exports'][$idhash_ext], SORT_NUMERIC);

			$existing_export_formats[] = $format;
		}
		elseif ($done === false)
		{
			Utils::$context['active_exports'][$idhash_ext] = array(
				'dltoken' => $dltoken,
				'included' => $included,
				'included_desc' => Lang::sentenceList($included_desc),
				'format' => $format,
			);

			$existing_export_formats[] = $format;
		}
	}

	if (!empty($_POST['export_begin']))
	{
		checkSession();
		validateToken(Utils::$context['token_check'], 'post');

		$format = isset($_POST['format']) && isset(Utils::$context['export_formats'][$_POST['format']]) ? $_POST['format'] : 'XML';

		$included = array();
		$included_desc = array();
		foreach (Utils::$context['export_datatypes'] as $datatype => $datatype_settings)
		{
			if ($datatype == 'profile' || !empty($_POST[$datatype]))
			{
				$included[$datatype] = $datatype_settings[$format];
				$included_desc[] = Lang::$txt[$datatype];

				$start[$datatype] = !empty($start[$datatype]) ? $start[$datatype] : 0;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($uid) : $datatype_settings['latest'];

				if (!isset($total[$datatype]))
					$total[$datatype] = is_callable($datatype_settings['total']) ? $datatype_settings['total']($uid) : $datatype_settings['total'];
			}
		}

		$dlfilename = array_merge(array(Utils::$context['forum_name'], Utils::$context['member']['username']), $included_desc);
		$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

		$last_page = ceil(array_sum($total) / Utils::$context['export_formats'][$format]['per_page']);

		$data = Utils::jsonEncode(array(
			'format' => $format,
			'uid' => $uid,
			'lang' => Utils::$context['member']['language'],
			'included' => $included,
			'start' => $start,
			'latest' => $latest,
			'datatype' => isset($current_datatype) ? $current_datatype : key($included),
			'format_settings' => Utils::$context['export_formats'][$format],
			'last_page' => $last_page,
			'dlfilename' => $dlfilename,
		));

		Db::$db->insert('insert', '{db_prefix}background_tasks',
			array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/ExportProfileData.php', 'SMF\Tasks\ExportProfileData', $data, 0),
			array()
		);

		// So the user can see that we've started.
		if (!file_exists($tempfile))
			touch($tempfile);
		if (!file_exists($progressfile))
			file_put_contents($progressfile, Utils::jsonEncode(array_fill_keys(array_keys($included), 0)));

		redirectexit('action=profile;area=getprofiledata;u=' . $uid);
	}

	createToken(Utils::$context['token_check'], 'post');

	Utils::$context['page_title'] = Lang::$txt['export_profile_data'];

	if (empty(Config::$modSettings['export_expiry']))
		unset(Lang::$txt['export_profile_data_desc_list']['expiry']);
	else
		Lang::$txt['export_profile_data_desc_list']['expiry'] = sprintf(Lang::$txt['export_profile_data_desc_list']['expiry'], Config::$modSettings['export_expiry']);

	Utils::$context['export_profile_data_desc'] = sprintf(Lang::$txt['export_profile_data_desc'], '<li>' . implode('</li><li>', Lang::$txt['export_profile_data_desc_list']) . '</li>');

	Theme::addJavaScriptVar('completed_formats', '[\'' . implode('\', \'', array_unique($existing_export_formats)) . '\']', false);
}

/**
 * Downloads exported profile data file.
 *
 * @param int $uid The ID of the member whose data we're exporting.
 */
function download_export_file($uid)
{
	$export_formats = get_export_formats();

	// This is done to clear any output that was made before now.
	ob_end_clean();

	if (!empty(Config::$modSettings['enableCompressedOutput']) && !headers_sent() && ob_get_length() == 0)
	{
		if (@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
			Config::$modSettings['enableCompressedOutput'] = 0;

		else
			ob_start('ob_gzhandler');
	}

	if (empty(Config::$modSettings['enableCompressedOutput']))
	{
		ob_start();
		header('content-encoding: none');
	}

	// No access in strict maintenance mode.
	if (!empty(Config::$maintenance) && Config::$maintenance == 2)
	{
		send_http_status(404);
		exit;
	}

	// We can't give them anything without these.
	if (empty($_GET['t']) || empty($_GET['format']) || !isset($export_formats[$_GET['format']]))
	{
		send_http_status(400);
		exit;
	}

	$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $uid, Config::getAuthSecret());
	$part = isset($_GET['part']) ? (int) $_GET['part'] : 1;
	$extension = $export_formats[$_GET['format']]['extension'];

	$filepath = $export_dir_slash . $part . '_' . $idhash . '.' . $extension;
	$progressfile = $export_dir_slash . $idhash . '.' . $extension . '.progress.json';

	// Make sure they gave the correct authentication token.
	// We use these tokens so the user can download without logging in, as required by the GDPR.
	$dltoken = hash_hmac('sha1', $idhash, Config::getAuthSecret());
	if ($_GET['t'] !== $dltoken)
	{
		send_http_status(403);
		exit;
	}

	// Obviously we can't give what we don't have.
	if (empty(Config::$modSettings['export_dir']) || !file_exists($filepath))
	{
		send_http_status(404);
		exit;
	}

	// Figure out the filename we'll tell the browser.
	$datatypes = file_exists($progressfile) ? array_keys(Utils::jsonDecode(file_get_contents($progressfile), true)) : array('profile');
	$included_desc = array_map(
		function ($datatype)
		{
			return Lang::$txt[$datatype];
		},
		$datatypes
	);

	$dlfilename = array_merge(array(Utils::$context['forum_name'], Utils::$context['member']['username']), $included_desc);
	$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

	$suffix = ($part > 1 || file_exists($export_dir_slash . '2_' . $idhash . '.' . $extension)) ? '_' . $part : '';

	$dlbasename = $dlfilename . $suffix . '.' . $extension;

	$mtime = filemtime($filepath);
	$size = filesize($filepath);

	// If it hasn't been modified since the last time it was retrieved, there's no need to serve it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= $mtime)
		{
			ob_end_clean();
			header_remove('content-encoding');

			// Answer the question - no, it hasn't been modified ;).
			send_http_status(304);
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = md5(implode(' ', array($dlbasename, $size, $mtime)));
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();
		header_remove('content-encoding');

		send_http_status(304);
		exit;
	}

	// If this is a partial download, we need to determine what data range to send
	$range = 0;
	if (isset($_SERVER['HTTP_RANGE']))
	{
		list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
		list($range) = explode(",", $range, 2);
		list($range, $range_end) = explode("-", $range);
		$range = intval($range);
		$range_end = !$range_end ? $size - 1 : intval($range_end);
		$new_length = $range_end - $range + 1;
	}

	header('pragma: ');

	if (!BrowserDetector::isBrowser('gecko'))
		header('content-transfer-encoding: binary');

	header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('accept-ranges: bytes');
	header('connection: close');
	header('etag: ' . $eTag);
	header('content-type: ' . $export_formats[$_GET['format']]['mime']);

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !Utils::$context['utf8'] && function_exists('iconv') ? iconv(Utils::$context['character_set'], 'UTF-8', $dlbasename) : (!Utils::$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($dlbasename, 'UTF-8', Utils::$context['character_set']) : $dlbasename);

	// Different browsers like different standards...
	if (BrowserDetector::isBrowser('firefox'))
		header('content-disposition: attachment; filename*=UTF-8\'\'' . rawurlencode(Utils::entityDecode($utf8name, true)));

	elseif (BrowserDetector::isBrowser('opera'))
		header('content-disposition: attachment; filename="' . Utils::entityDecode($utf8name, true) . '"');

	elseif (BrowserDetector::isBrowser('ie'))
		header('content-disposition: attachment; filename="' . urlencode(Utils::entityDecode($utf8name, true)) . '"');

	else
		header('content-disposition: attachment; filename="' . $utf8name . '"');

	header('cache-control: max-age=' . (525600 * 60) . ', private');

	// Multipart and resuming support
	if (isset($_SERVER['HTTP_RANGE']))
	{
		send_http_status(206);
		header("content-length: $new_length");
		header("content-range: bytes $range-$range_end/$size");
	}
	else
		header("content-length: $size");

	// Try to buy some time...
	@set_time_limit(600);

	// For multipart/resumable downloads, send the requested chunk(s) of the file
	if (isset($_SERVER['HTTP_RANGE']))
	{
		while (@ob_get_level() > 0)
			@ob_end_clean();

		header_remove('content-encoding');

		// 40 kilobytes is a good-ish amount
		$chunksize = 40 * 1024;
		$bytes_sent = 0;

		$fp = fopen($filepath, 'rb');

		fseek($fp, $range);

		while (!feof($fp) && (!connection_aborted()) && ($bytes_sent < $new_length))
		{
			$buffer = fread($fp, $chunksize);
			echo($buffer);
			flush();
			$bytes_sent += strlen($buffer);
		}
		fclose($fp);
	}

	// Since we don't do output compression for files this large...
	elseif ($size > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		header_remove('content-encoding');

		$fp = fopen($filepath, 'rb');
		while (!feof($fp))
		{
			echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($filepath) === null)
		echo file_get_contents($filepath);

	exit;
}

/**
 * Allows a member to export their attachments.
 * Mostly just a wrapper for showAttachment() but with a few tweaks.
 *
 * @param int $uid The ID of the member whose data we're exporting.
 */
function export_attachment($uid)
{
	$idhash = hash_hmac('sha1', $uid, Config::getAuthSecret());
	$dltoken = hash_hmac('sha1', $idhash, Config::getAuthSecret());
	if (!isset($_GET['t']) || $_GET['t'] !== $dltoken)
	{
		send_http_status(403);
		exit;
	}

	$attachId = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;
	if (empty($attachId))
	{
		send_http_status(404, 'File Not Found');
		die('404 File Not Found');
	}

	// Does this attachment belong to this member?
	$request = Db::$db->query('', '
		SELECT m.id_topic
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}attachments AS a ON (m.id_msg = a.id_msg)
		WHERE m.id_member = {int:uid}
			AND a.id_attach = {int:attachId}',
		array(
			'uid' => $uid,
			'attachId' => $attachId,
		)
	);
	if (Db::$db->num_rows($request) == 0)
	{
		Db::$db->free_result($request);
		send_http_status(403);
		exit;
	}
	Db::$db->free_result($request);

	// This doesn't count as a normal download.
	Utils::$context['skip_downloads_increment'] = true;

	// Try to avoid collisions when attachment names are not unique.
	Utils::$context['prepend_attachment_id'] = true;

	// Allow access to their attachments even if they can't see the board.
	// This is just like what we do with posts during export.
	Utils::$context['attachment_allow_hidden_boards'] = true;

	// We should now have what we need to serve the file.
	AttachmentDownload::call();
}

/**
 * Helper function that defines data export formats in a single location.
 *
 * @return array Information about supported data formats for profile exports.
 */
function get_export_formats()
{
	Lang::load('Profile');

	$export_formats = array(
		'XML_XSLT' => array(
			'extension' => 'styled.xml',
			'mime' => 'text/xml',
			'description' => Lang::$txt['export_format_xml_xslt'],
			'per_page' => 500,
		),
		'HTML' => array(
			'extension' => 'html',
			'mime' => 'text/html',
			'description' => Lang::$txt['export_format_html'],
			'per_page' => 500,
		),
		'XML' => array(
			'extension' => 'xml',
			'mime' => 'text/xml',
			'description' => Lang::$txt['export_format_xml'],
			'per_page' => 2000,
		),
		// 'CSV' => array(
		// 	'extension' => 'csv',
		// 	'mime' => 'text/csv',
		// 	'description' => Lang::$txt['export_format_csv'],
		//	'per_page' => 2000,
		// ),
		// 'JSON' => array(
		// 	'extension' => 'json',
		// 	'mime' => 'application/json',
		// 	'description' => Lang::$txt['export_format_json'],
		//	'per_page' => 2000,
		// ),
	);

	// If these are missing, we can't transform the XML on the server.
	if (!class_exists('DOMDocument') || !class_exists('XSLTProcessor'))
		unset($export_formats['HTML']);

	return $export_formats;
}

/**
 * Returns the path to a secure directory for storing exported profile data.
 *
 * The directory is created if it does not yet exist, and is secured using the
 * same method that we use to secure attachment directories. Files in this
 * directory can only be downloaded via the download_export_file() function.
 *
 * @return string|bool The path to the directory, or false on error.
 */
function create_export_dir($fallback = '')
{
	// No supplied fallback, so use the default location.
	if (empty($fallback))
		$fallback = Config::$boarddir . DIRECTORY_SEPARATOR . 'exports';

	// Automatically set it to the fallback if it is missing.
	if (empty(Config::$modSettings['export_dir']))
		Config::updateModSettings(array('export_dir' => $fallback));

	// Make sure the directory exists.
	if (!file_exists(Config::$modSettings['export_dir']))
		@mkdir(Config::$modSettings['export_dir'], null, true);

	// Make sure the directory has the correct permissions.
	if (!is_dir(Config::$modSettings['export_dir']) || !smf_chmod(Config::$modSettings['export_dir']))
	{
		Lang::load('Errors');

		// Try again at the fallback location.
		if (Config::$modSettings['export_dir'] != $fallback)
		{
			log_error(sprintf(Lang::$txt['export_dir_forced_change'], Config::$modSettings['export_dir'], $fallback));
			Config::updateModSettings(array('export_dir' => $fallback));

			// Secondary fallback will be the default location, so no parameter this time.
			create_export_dir();
		}
		// Uh-oh. Even the default location failed.
		else
		{
			log_error(Lang::$txt['export_dir_not_writable']);
			return false;
		}
	}

	return secureDirectory(array(Config::$modSettings['export_dir']), true);
}

?>