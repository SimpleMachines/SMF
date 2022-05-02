<?php

/**
 * This file contains functions to export a member's profile data to a
 * downloadable file.
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
	global $context, $smcFunc, $txt, $modSettings, $sourcedir, $scripturl;
	global $query_this_board;

	if (!isset($context['token_check']))
		$context['token_check'] = 'profile-ex' . $uid;

	$context['export_formats'] = get_export_formats();

	if (!isset($_POST['format']) || !isset($context['export_formats'][$_POST['format']]))
		unset($_POST['format'], $_POST['delete'], $_POST['export_begin']);

	// This lists the types of data we can export and info for doing so.
	$context['export_datatypes'] = array(
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
			'label' => $txt['export_include_posts'],
			'total' => $context['member']['real_posts'],
			'latest' => function($uid)
			{
				global $smcFunc, $modSettings;

				static $latest_post;

				if (isset($latest_post))
					return $latest_post;

				$query_this_board = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? 'b.id_board != ' . $modSettings['recycle_board'] : '1=1';

				$request = $smcFunc['db_query']('', '
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
				list($latest_post) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

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
			'label' => $txt['export_include_personal_messages'],
			'total' => function($uid)
			{
				global $smcFunc;

				static $total_pms;

				if (isset($total_pms))
					return $total_pms;

				$request = $smcFunc['db_query']('', '
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
				list($total_pms) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				return $total_pms;
			},
			'latest' => function($uid)
			{
				global $smcFunc;

				static $latest_pm;

				if (isset($latest_pm))
					return $latest_pm;

				$request = $smcFunc['db_query']('', '
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
				list($latest_pm) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

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

	if (empty($modSettings['export_dir']) || !is_dir($modSettings['export_dir']) || !smf_chmod($modSettings['export_dir']))
		create_export_dir();

	$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $uid, get_auth_secret());
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());

	$query_this_board = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? 'b.id_board != ' . $modSettings['recycle_board'] : '1=1';

	$context['completed_exports'] = array();
	$context['active_exports'] = array();
	$existing_export_formats = array();
	$latest = array();

	foreach ($context['export_formats'] as $format => $format_settings)
	{
		$idhash_ext = $idhash . '.' . $format_settings['extension'];

		$done = null;
		$context['outdated_exports'][$idhash_ext] = array();

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
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}background_tasks
				WHERE task_class = {string:class}
					AND task_data LIKE {string:details}',
				array(
					'class' => 'ExportProfileData_Background',
					'details' => substr($smcFunc['json_encode'](array('format' => $format, 'uid' => $uid)), 0, -1) . ',%',
				)
			);

			foreach (glob($export_dir_slash . '*' . $idhash_ext . '*') as $fpath)
				@unlink($fpath);

			if (empty($_POST['export_begin']))
				redirectexit('action=profile;area=getprofiledata;u=' . $uid);
		}

		$progress = file_exists($progressfile) ? $smcFunc['json_decode'](file_get_contents($progressfile), true) : array();

		if (!empty($progress))
			$included = array_keys($progress);
		else
			$included = array_intersect(array_keys($context['export_datatypes']), array_keys($_POST));

		// If we're starting a new export in this format, we're done here.
		if (!empty($_POST['export_begin']) && isset($_POST['format']) && $_POST['format'] === $format)
			break;

		// The rest of this loop deals with current exports, if any.

		$included_desc = array();
		foreach ($included as $datatype)
			$included_desc[] = $txt[$datatype];

		$dlfilename = array_merge(array($context['forum_name'], $context['member']['username']), $included_desc);
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
			foreach ($context['export_datatypes'] as $datatype => $datatype_settings)
			{
				if (!isset($progress[$datatype]))
					continue;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($uid) : $datatype_settings['latest'];

				if ($latest[$datatype] > $progress[$datatype])
					$context['outdated_exports'][$idhash_ext][] = $datatype;
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

				$context['completed_exports'][$idhash_ext][$part] = array(
					'realname' => $exportbasename,
					'dlbasename' => $dlfilename . $suffix . '.' . $format_settings['extension'],
					'dltoken' => $dltoken,
					'included' => $included,
					'included_desc' => sentence_list($included_desc),
					'format' => $format,
					'mtime' => timeformat(filemtime($exportfilepath)),
					'size' => $size,
				);
			}

			ksort($context['completed_exports'][$idhash_ext], SORT_NUMERIC);

			$existing_export_formats[] = $format;
		}
		elseif ($done === false)
		{
			$context['active_exports'][$idhash_ext] = array(
				'dltoken' => $dltoken,
				'included' => $included,
				'included_desc' => sentence_list($included_desc),
				'format' => $format,
			);

			$existing_export_formats[] = $format;
		}
	}

	if (!empty($_POST['export_begin']))
	{
		checkSession();
		validateToken($context['token_check'], 'post');

		$format = isset($_POST['format']) && isset($context['export_formats'][$_POST['format']]) ? $_POST['format'] : 'XML';

		$included = array();
		$included_desc = array();
		foreach ($context['export_datatypes'] as $datatype => $datatype_settings)
		{
			if ($datatype == 'profile' || !empty($_POST[$datatype]))
			{
				$included[$datatype] = $datatype_settings[$format];
				$included_desc[] = $txt[$datatype];

				$start[$datatype] = !empty($start[$datatype]) ? $start[$datatype] : 0;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($uid) : $datatype_settings['latest'];

				if (!isset($total[$datatype]))
					$total[$datatype] = is_callable($datatype_settings['total']) ? $datatype_settings['total']($uid) : $datatype_settings['total'];
			}
		}

		$dlfilename = array_merge(array($context['forum_name'], $context['member']['username']), $included_desc);
		$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

		$last_page = ceil(array_sum($total) / $context['export_formats'][$format]['per_page']);

		$data = $smcFunc['json_encode'](array(
			'format' => $format,
			'uid' => $uid,
			'lang' => $context['member']['language'],
			'included' => $included,
			'start' => $start,
			'latest' => $latest,
			'datatype' => isset($current_datatype) ? $current_datatype : key($included),
			'format_settings' => $context['export_formats'][$format],
			'last_page' => $last_page,
			'dlfilename' => $dlfilename,
		));

		$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
			array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/ExportProfileData.php', 'ExportProfileData_Background', $data, 0),
			array()
		);

		// So the user can see that we've started.
		if (!file_exists($tempfile))
			touch($tempfile);
		if (!file_exists($progressfile))
			file_put_contents($progressfile, $smcFunc['json_encode'](array_fill_keys(array_keys($included), 0)));

		redirectexit('action=profile;area=getprofiledata;u=' . $uid);
	}

	createToken($context['token_check'], 'post');

	$context['page_title'] = $txt['export_profile_data'];

	if (empty($modSettings['export_expiry']))
		unset($txt['export_profile_data_desc_list']['expiry']);
	else
		$txt['export_profile_data_desc_list']['expiry'] = sprintf($txt['export_profile_data_desc_list']['expiry'], $modSettings['export_expiry']);

	$context['export_profile_data_desc'] = sprintf($txt['export_profile_data_desc'], '<li>' . implode('</li><li>', $txt['export_profile_data_desc_list']) . '</li>');

	addJavaScriptVar('completed_formats', '[\'' . implode('\', \'', array_unique($existing_export_formats)) . '\']', false);
}

/**
 * Downloads exported profile data file.
 *
 * @param int $uid The ID of the member whose data we're exporting.
 */
function download_export_file($uid)
{
	global $modSettings, $maintenance, $context, $txt, $smcFunc;

	$export_formats = get_export_formats();

	// This is done to clear any output that was made before now.
	ob_end_clean();

	if (!empty($modSettings['enableCompressedOutput']) && !headers_sent() && ob_get_length() == 0)
	{
		if (@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
			$modSettings['enableCompressedOutput'] = 0;

		else
			ob_start('ob_gzhandler');
	}

	if (empty($modSettings['enableCompressedOutput']))
	{
		ob_start();
		header('content-encoding: none');
	}

	// No access in strict maintenance mode.
	if (!empty($maintenance) && $maintenance == 2)
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

	$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $uid, get_auth_secret());
	$part = isset($_GET['part']) ? (int) $_GET['part'] : 1;
	$extension = $export_formats[$_GET['format']]['extension'];

	$filepath = $export_dir_slash . $part . '_' . $idhash . '.' . $extension;
	$progressfile = $export_dir_slash . $idhash . '.' . $extension . '.progress.json';

	// Make sure they gave the correct authentication token.
	// We use these tokens so the user can download without logging in, as required by the GDPR.
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());
	if ($_GET['t'] !== $dltoken)
	{
		send_http_status(403);
		exit;
	}

	// Obviously we can't give what we don't have.
	if (empty($modSettings['export_dir']) || !file_exists($filepath))
	{
		send_http_status(404);
		exit;
	}

	// Figure out the filename we'll tell the browser.
	$datatypes = file_exists($progressfile) ? array_keys($smcFunc['json_decode'](file_get_contents($progressfile), true)) : array('profile');
	$included_desc = array_map(
		function ($datatype) use ($txt)
		{
			return $txt[$datatype];
		},
		$datatypes
	);

	$dlfilename = array_merge(array($context['forum_name'], $context['member']['username']), $included_desc);
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

	if (!isBrowser('gecko'))
		header('content-transfer-encoding: binary');

	header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('accept-ranges: bytes');
	header('connection: close');
	header('etag: ' . $eTag);
	header('content-type: ' . $export_formats[$_GET['format']]['mime']);

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $dlbasename) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($dlbasename, 'UTF-8', $context['character_set']) : $dlbasename);

	// Different browsers like different standards...
	if (isBrowser('firefox'))
		header('content-disposition: attachment; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)));

	elseif (isBrowser('opera'))
		header('content-disposition: attachment; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name) . '"');

	elseif (isBrowser('ie'))
		header('content-disposition: attachment; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)) . '"');

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
	global $sourcedir, $context, $smcFunc;

	$idhash = hash_hmac('sha1', $uid, get_auth_secret());
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());
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
	$request = $smcFunc['db_query']('', '
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
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$smcFunc['db_free_result']($request);
		send_http_status(403);
		exit;
	}
	$smcFunc['db_free_result']($request);

	// This doesn't count as a normal download.
	$context['skip_downloads_increment'] = true;

	// Try to avoid collisons when attachment names are not unique.
	$context['prepend_attachment_id'] = true;

	// Allow access to their attachments even if they can't see the board.
	// This is just like what we do with posts during export.
	$context['attachment_allow_hidden_boards'] = true;

	// We should now have what we need to serve the file.
	require_once($sourcedir . DIRECTORY_SEPARATOR . 'ShowAttachments.php');
	showAttachment();
}

/**
 * Helper function that defines data export formats in a single location.
 *
 * @return array Information about supported data formats for profile exports.
 */
function get_export_formats()
{
	global $txt;

	$export_formats = array(
		'XML_XSLT' => array(
			'extension' => 'styled.xml',
			'mime' => 'text/xml',
			'description' => $txt['export_format_xml_xslt'],
			'per_page' => 500,
		),
		'HTML' => array(
			'extension' => 'html',
			'mime' => 'text/html',
			'description' => $txt['export_format_html'],
			'per_page' => 500,
		),
		'XML' => array(
			'extension' => 'xml',
			'mime' => 'text/xml',
			'description' => $txt['export_format_xml'],
			'per_page' => 2000,
		),
		// 'CSV' => array(
		// 	'extension' => 'csv',
		// 	'mime' => 'text/csv',
		// 	'description' => $txt['export_format_csv'],
		//	'per_page' => 2000,
		// ),
		// 'JSON' => array(
		// 	'extension' => 'json',
		// 	'mime' => 'application/json',
		// 	'description' => $txt['export_format_json'],
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
	global $boarddir, $modSettings, $txt;

	// No supplied fallback, so use the default location.
	if (empty($fallback))
		$fallback = $boarddir . DIRECTORY_SEPARATOR . 'exports';

	// Automatically set it to the fallback if it is missing.
	if (empty($modSettings['export_dir']))
		updateSettings(array('export_dir' => $fallback));

	// Make sure the directory exists.
	if (!file_exists($modSettings['export_dir']))
		@mkdir($modSettings['export_dir'], null, true);

	// Make sure the directory has the correct permissions.
	if (!is_dir($modSettings['export_dir']) || !smf_chmod($modSettings['export_dir']))
	{
		loadLanguage('Errors');

		// Try again at the fallback location.
		if ($modSettings['export_dir'] != $fallback)
		{
			log_error(sprintf($txt['export_dir_forced_change'], $modSettings['export_dir'], $fallback));
			updateSettings(array('export_dir' => $fallback));

			// Secondary fallback will be the default location, so no parameter this time.
			create_export_dir();
		}
		// Uh-oh. Even the default location failed.
		else
		{
			log_error($txt['export_dir_not_writable']);
			return false;
		}
	}

	return secureDirectory(array($modSettings['export_dir']), true);
}

/**
 * Provides an XSLT stylesheet to transform an XML-based profile export file
 * into the desired output format.
 *
 * @param string $format The desired output format. Currently accepts 'HTML' and 'XML_XSLT'.
 * @param int $uid The ID of the member whose data we're exporting.
 * @return array The XSLT stylesheet and a (possibly empty) DTD to insert into the XML document.
 */
function get_xslt_stylesheet($format, $uid)
{
	global $context, $txt, $settings, $modSettings, $sourcedir, $forum_copyright, $scripturl, $smcFunc;

	static $xslts = array();

	$doctype = '';
	$stylesheet = array();
	$xslt_variables = array();

	// Do not change any of these to HTTPS URLs. For explanation, see comments in the buildXmlFeed() function.
	$smf_ns = 'htt'.'p:/'.'/ww'.'w.simple'.'machines.o'.'rg/xml/profile';
	$xslt_ns = 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/XSL/Transform';
	$html_ns = 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/xhtml';

	require_once($sourcedir . DIRECTORY_SEPARATOR . 'News.php');

	if (in_array($format, array('HTML', 'XML_XSLT')))
	{
		if (!class_exists('DOMDocument') || !class_exists('XSLTProcessor'))
			$format = 'XML_XSLT';

		$export_formats = get_export_formats();

		/* Notes:
		 * 1. The 'value' can be one of the following:
		 *    - an integer or string
		 *    - an XPath expression
		 *    - raw XML, which may or not not include other XSLT statements.
		 *
		 * 2. Always set 'no_cdata_parse' to true when the value is raw XML.
		 *
		 * 3. Set 'xpath' to true if the value is an XPath expression. When this
		 *    is true, the value will be placed in the 'select' attribute of the
		 *    <xsl:variable> element rather than in a child node.
		 *
		 * 4. Set 'param' to true in order to create an <xsl:param> instead
		 *    of an <xsl:variable>.
		 *
		 * A word to PHP coders: Do not let the term "variable" mislead you.
		 * XSLT variables are roughly equivalent to PHP constants rather
		 * than PHP variables; once the value has been set, it is immutable.
		 * Keeping this in mind may spare you from some confusion and
		 * frustration while working with XSLT.
		 */
		$xslt_variables = array(
			'scripturl' => array(
				'value' => $scripturl,
			),
			'themeurl' => array(
				'value' => $settings['default_theme_url'],
			),
			'member_id' => array(
				'value' => $uid,
			),
			'last_page' => array(
				'param' => true,
				'value' => !empty($context['export_last_page']) ? $context['export_last_page'] : 1,
				'xpath' => true,
			),
			'dlfilename' => array(
				'param' => true,
				'value' => !empty($context['export_dlfilename']) ? $context['export_dlfilename'] : '',
			),
			'ext' => array(
				'value' => $export_formats[$format]['extension'],
			),
			'forum_copyright' => array(
				'value' => sprintf($forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, $scripturl),
			),
			'txt_summary_heading' => array(
				'value' => $txt['summary'],
			),
			'txt_posts_heading' => array(
				'value' => $txt['posts'],
			),
			'txt_personal_messages_heading' => array(
				'value' => $txt['personal_messages'],
			),
			'txt_view_source_button' => array(
				'value' => $txt['export_view_source_button'],
			),
			'txt_download_original' => array(
				'value' => $txt['export_download_original'],
			),
			'txt_help' => array(
				'value' => $txt['help'],
			),
			'txt_terms_rules' => array(
				'value' => $txt['terms_and_rules'],
			),
			'txt_go_up' => array(
				'value' => $txt['go_up'],
			),
			'txt_pages' => array(
				'value' => $txt['pages'],
			),
		);

		// Let mods adjust the XSLT variables.
		call_integration_hook('integrate_export_xslt_variables', array(&$xslt_variables, $format));

		$idhash = hash_hmac('sha1', $uid, get_auth_secret());
		$xslt_variables['dltoken'] = array(
			'value' => hash_hmac('sha1', $idhash, get_auth_secret())
		);

		// Efficiency = good.
		$xslt_key = $smcFunc['json_encode'](array($format, $uid, $xslt_variables));
		if (isset($xslts[$xslt_key]))
			return $xslts[$xslt_key];

		if ($format == 'XML_XSLT')
		{
			$doctype = implode("\n", array(
				'<!--',
				"\t" . $txt['export_open_in_browser'],
				'-->',
				'<?xml-stylesheet type="text/xsl" href="#stylesheet"?>',
				'<!DOCTYPE smf:xml-feed [',
				'<!ATTLIST xsl:stylesheet',
				'id ID #REQUIRED>',
				']>',
			));

			$stylesheet['header'] = "\n" . implode("\n", array(
				'',
				"\t" . '<xsl:stylesheet version="1.0" xmlns:xsl="' . $xslt_ns . '" xmlns:html="' . $html_ns . '" xmlns:smf="' . $smf_ns . '" exclude-result-prefixes="smf html" id="stylesheet">',
				'',
				"\t\t" . '<xsl:template match="xsl:stylesheet"/>',
				"\t\t" . '<xsl:template match="xsl:stylesheet" mode="detailedinfo"/>',
			));
		}
		else
		{
			$doctype = '';
			$stylesheet['header'] = implode("\n", array(
				'<?xml version="1.0" encoding="' . $context['character_set'] . '"?' . '>',
				'<xsl:stylesheet version="1.0" xmlns:xsl="' . $xslt_ns . '" xmlns:html="' . $html_ns . '" xmlns:smf="' . $smf_ns . '" exclude-result-prefixes="smf html">',
			));
		}

		// Output control settings.
		$stylesheet['output_control'] = '
		<xsl:output method="html" encoding="utf-8" indent="yes"/>
		<xsl:strip-space elements="*"/>';

		// Insert the XSLT variables.
		$stylesheet['variables'] = '';

		foreach ($xslt_variables as $name => $var)
		{
			$element = !empty($var['param']) ? 'param' : 'variable';

			$stylesheet['variables'] .= "\n\t\t" . '<xsl:' . $element . ' name="' . $name . '"';

			if (isset($var['xpath']))
				$stylesheet['variables'] .= ' select="' . $var['value'] . '"/>';
			else
				$stylesheet['variables'] .= '>' . (!empty($var['no_cdata_parse']) ? $var['value'] : cdata_parse($var['value'])) . '</xsl:' . $element . '>';
		}

		// The top-level template. Creates the shell of the HTML document.
		$stylesheet['html'] = '
		<xsl:template match="/*">
			<xsl:text disable-output-escaping="yes">&lt;!DOCTYPE html&gt;</xsl:text>
			<html>
				<head>
					<title>
						<xsl:value-of select="@title"/>
					</title>
					<xsl:call-template name="css_js"/>
				</head>
				<body>
					<div id="footerfix">
						<div id="header">
							<h1 class="forumtitle">
								<a id="top">
									<xsl:attribute name="href">
										<xsl:value-of select="$scripturl"/>
									</xsl:attribute>
									<xsl:value-of select="@forum-name"/>
								</a>
							</h1>
						</div>
						<div id="wrapper">
							<div id="upper_section">
								<div id="inner_section">
									<div id="inner_wrap">
										<div class="user">
											<time>
												<xsl:attribute name="datetime">
													<xsl:value-of select="@generated-date-UTC"/>
												</xsl:attribute>
												<xsl:value-of select="@generated-date-localized"/>
											</time>
										</div>
										<hr class="clear"/>
									</div>
								</div>
							</div>

							<xsl:call-template name="content_section"/>

						</div>
					</div>
					<div id="footer">
						<div class="inner_wrap">
							<ul>
								<li class="floatright">
									<a>
										<xsl:attribute name="href">
											<xsl:value-of select="concat($scripturl, \'?action=help\')"/>
										</xsl:attribute>
										<xsl:value-of select="$txt_help"/>
									</a>
									<xsl:text> | </xsl:text>
									<a>
										<xsl:attribute name="href">
											<xsl:value-of select="concat($scripturl, \'?action=help;sa=rules\')"/>
										</xsl:attribute>
										<xsl:value-of select="$txt_terms_rules"/>
									</a>
									<xsl:text> | </xsl:text>
									<a href="#top">
										<xsl:value-of select="$txt_go_up"/>
										<xsl:text> &#9650;</xsl:text>
									</a>
								</li>
								<li class="copyright">
									<xsl:value-of select="$forum_copyright" disable-output-escaping="yes"/>
								</li>
							</ul>
						</div>
					</div>
				</body>
			</html>
		</xsl:template>';

		// Template to show the content of the export file.
		$stylesheet['content_section'] = '
		<xsl:template name="content_section">
			<div id="content_section">
				<div id="main_content_section">

					<div class="cat_bar">
						<h3 class="catbg">
							<xsl:value-of select="@title"/>
						</h3>
					</div>
					<div class="information">
						<h2 class="display_title">
							<xsl:value-of select="@description"/>
						</h2>
					</div>

					<xsl:if test="username">
						<div class="cat_bar">
							<h3 class="catbg">
								<xsl:value-of select="$txt_summary_heading"/>
							</h3>
						</div>
						<div id="profileview" class="roundframe flow_auto noup">
							<xsl:call-template name="summary"/>
						</div>
					</xsl:if>

					<xsl:call-template name="page_index"/>

					<xsl:if test="member_post">
						<div class="cat_bar">
							<h3 class="catbg">
								<xsl:value-of select="$txt_posts_heading"/>
							</h3>
						</div>
						<div id="posts" class="roundframe flow_auto noup">
							<xsl:apply-templates select="member_post" mode="posts"/>
						</div>
					</xsl:if>

					<xsl:if test="personal_message">
						<div class="cat_bar">
							<h3 class="catbg">
								<xsl:value-of select="$txt_personal_messages_heading"/>
							</h3>
						</div>
						<div id="personal_messages" class="roundframe flow_auto noup">
							<xsl:apply-templates select="personal_message" mode="pms"/>
						</div>
					</xsl:if>

					<xsl:call-template name="page_index"/>

				</div>
			</div>
		</xsl:template>';

		// Template for user profile summary
		$stylesheet['summary'] = '
		<xsl:template name="summary">
			<div id="basicinfo">
				<div class="username clear">
					<h4>
						<a>
							<xsl:attribute name="href">
								<xsl:value-of select="link"/>
							</xsl:attribute>
							<xsl:value-of select="name"/>
						</a>
						<xsl:text> </xsl:text>
						<span class="position">
							<xsl:choose>
								<xsl:when test="position">
									<xsl:value-of select="position"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="post_group"/>
								</xsl:otherwise>
							</xsl:choose>
						</span>
					</h4>
				</div>
				<img class="avatar">
					<xsl:attribute name="src">
						<xsl:value-of select="avatar"/>
					</xsl:attribute>
				</img>
			</div>

			<div id="detailedinfo">
				<dl class="settings noborder">
					<xsl:apply-templates mode="detailedinfo"/>
				</dl>
			</div>
		</xsl:template>';

		// Some helper templates for details inside the summary.
		$stylesheet['detail_default'] = '
		<xsl:template match="*" mode="detailedinfo">
			<dt>
				<xsl:value-of select="concat(@label, \':\')"/>
			</dt>
			<dd>
				<xsl:value-of select="." disable-output-escaping="yes"/>
			</dd>
		</xsl:template>';

		$stylesheet['detail_email'] = '
		<xsl:template match="email" mode="detailedinfo">
			<dt>
				<xsl:value-of select="concat(@label, \':\')"/>
			</dt>
			<dd>
				<a>
					<xsl:attribute name="href">
						<xsl:text>mailto:</xsl:text>
						<xsl:value-of select="."/>
					</xsl:attribute>
					<xsl:value-of select="."/>
				</a>
			</dd>
		</xsl:template>';

		$stylesheet['detail_website'] = '
		<xsl:template match="website" mode="detailedinfo">
			<dt>
				<xsl:value-of select="concat(@label, \':\')"/>
			</dt>
			<dd>
				<a>
					<xsl:attribute name="href">
						<xsl:value-of select="link"/>
					</xsl:attribute>
					<xsl:value-of select="title"/>
				</a>
			</dd>
		</xsl:template>';

		$stylesheet['detail_ip'] = '
		<xsl:template match="ip_addresses" mode="detailedinfo">
			<dt>
				<xsl:value-of select="concat(@label, \':\')"/>
			</dt>
			<dd>
				<ul class="nolist">
					<xsl:apply-templates mode="ip_address"/>
				</ul>
			</dd>
		</xsl:template>
		<xsl:template match="*" mode="ip_address">
			<li>
				<xsl:value-of select="."/>
				<xsl:if test="@label and following-sibling">
					<xsl:text> </xsl:text>
					<span>(<xsl:value-of select="@label"/>)</span>
				</xsl:if>
			</li>
		</xsl:template>';

		$stylesheet['detail_not_included'] = '
		<xsl:template match="name|link|avatar|online|member_post|personal_message" mode="detailedinfo"/>';

		// Template for printing a single post
		$stylesheet['member_post'] = '
		<xsl:template match="member_post" mode="posts">
			<div>
				<xsl:attribute name="id">
					<xsl:value-of select="concat(\'member_post_\', id)"/>
				</xsl:attribute>
				<xsl:attribute name="class">
					<xsl:choose>
						<xsl:when test="approval_status = 1">
							<xsl:text>windowbg</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text>approvebg</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>

				<div class="post_wrapper">
					<div class="poster">
						<h4>
							<a>
								<xsl:attribute name="href">
									<xsl:value-of select="poster/link"/>
								</xsl:attribute>
								<xsl:value-of select="poster/name"/>
							</a>
						</h4>
						<ul class="user_info">
							<xsl:if test="poster/id = $member_id">
								<xsl:call-template name="own_user_info"/>
							</xsl:if>
							<li>
								<xsl:value-of select="poster/email"/>
							</li>
							<li class="poster_ip">
								<xsl:value-of select="concat(poster/ip/@label, \': \')"/>
								<xsl:value-of select="poster/ip"/>
							</li>
						</ul>
					</div>

					<div class="postarea">
						<div class="flow_hidden">

							<div class="keyinfo">
								<h5>
									<strong>
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="board/link"/>
											</xsl:attribute>
											<xsl:value-of select="board/name"/>
										</a>
										<xsl:text> / </xsl:text>
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="link"/>
											</xsl:attribute>
											<xsl:value-of select="subject"/>
										</a>
									</strong>
								</h5>
								<span class="smalltext"><xsl:value-of select="time"/></span>
								<xsl:if test="modified_time">
									<span class="smalltext modified floatright mvisible em">
										<xsl:attribute name="id">
											<xsl:value-of select="concat(\'modified_\', id)"/>
										</xsl:attribute>
										<span class="lastedit">
											<xsl:value-of select="modified_time/@label"/>
										</span>
										<xsl:text>: </xsl:text>
										<xsl:value-of select="modified_time"/>
										<xsl:text>. </xsl:text>
										<xsl:value-of select="modified_by/@label"/>
										<xsl:text>: </xsl:text>
										<xsl:value-of select="modified_by"/>
										<xsl:text>. </xsl:text>
									</span>
								</xsl:if>
							</div>

							<div class="post">
								<div class="inner">
									<xsl:value-of select="body_html" disable-output-escaping="yes"/>
								</div>
								<div class="inner monospace" style="display:none;">
									<xsl:choose>
										<xsl:when test="contains(body/text(), \'[html]\')">
											<xsl:call-template name="bbc_html_splitter">
												<xsl:with-param name="bbc_string" select="body/text()"/>
											</xsl:call-template>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="body" disable-output-escaping="yes"/>
										</xsl:otherwise>
									</xsl:choose>
								</div>
							</div>

							<xsl:apply-templates select="attachments">
								<xsl:with-param name="post_id" select="id"/>
							</xsl:apply-templates>

							<div class="under_message">
								<ul class="floatleft">
									<xsl:if test="likes > 0">
										<li class="smflikebutton">
											<xsl:attribute name="id">
												<xsl:value-of select="concat(\'msg_\', id, \'_likes\')"/>
											</xsl:attribute>
											<span><span class="main_icons like"></span> <xsl:value-of select="likes"/></span>
										</li>
									</xsl:if>
								</ul>
								<xsl:call-template name="quickbuttons">
									<xsl:with-param name="toggle_target" select="concat(\'member_post_\', id)"/>
								</xsl:call-template>
							</div>

						</div>
					</div>

					<div class="moderatorbar">
						<xsl:if test="poster/id = $member_id">
							<xsl:call-template name="signature"/>
						</xsl:if>
					</div>

				</div>
			</div>
		</xsl:template>';

		// Template for printing a single PM
		$stylesheet['personal_message'] = '
		<xsl:template match="personal_message" mode="pms">
			<div class="windowbg">
				<xsl:attribute name="id">
					<xsl:value-of select="concat(\'personal_message_\', id)"/>
				</xsl:attribute>

				<div class="post_wrapper">
					<div class="poster">
						<h4>
							<a>
								<xsl:attribute name="href">
									<xsl:value-of select="sender/link"/>
								</xsl:attribute>
								<xsl:value-of select="sender/name"/>
							</a>
						</h4>
						<ul class="user_info">
							<xsl:if test="sender/id = $member_id">
								<xsl:call-template name="own_user_info"/>
							</xsl:if>
						</ul>
					</div>

					<div class="postarea">
						<div class="flow_hidden">

							<div class="keyinfo">
								<h5>
									<xsl:attribute name="id">
										<xsl:value-of select="concat(\'subject_\', id)"/>
									</xsl:attribute>
									<xsl:value-of select="subject"/>
								</h5>
								<span class="smalltext">
									<strong>
										<xsl:value-of select="concat(recipient[1]/@label, \': \')"/>
									</strong>
									<xsl:apply-templates select="recipient"/>
								</span>
								<br/>
								<span class="smalltext">
									<strong>
										<xsl:value-of select="concat(sent_date/@label, \': \')"/>
									</strong>
									<time>
										<xsl:attribute name="datetime">
											<xsl:value-of select="sent_date/@UTC"/>
										</xsl:attribute>
										<xsl:value-of select="normalize-space(sent_date)"/>
									</time>
								</span>
							</div>

							<div class="post">
								<div class="inner">
									<xsl:value-of select="body_html" disable-output-escaping="yes"/>
								</div>
								<div class="inner monospace" style="display:none;">
									<xsl:call-template name="bbc_html_splitter">
										<xsl:with-param name="bbc_string" select="body/text()"/>
									</xsl:call-template>
								</div>
							</div>

							<div class="under_message">
								<xsl:call-template name="quickbuttons">
									<xsl:with-param name="toggle_target" select="concat(\'personal_message_\', id)"/>
								</xsl:call-template>
							</div>

						</div>
					</div>

					<div class="moderatorbar">
						<xsl:if test="sender/id = $member_id">
							<xsl:call-template name="signature"/>
						</xsl:if>
					</div>

				</div>
			</div>
		</xsl:template>';

		// A couple of templates to handle attachments
		$stylesheet['attachments'] = '
		<xsl:template match="attachments">
			<xsl:param name="post_id"/>
			<xsl:if test="attachment">
				<div class="attachments">
					<xsl:attribute name="id">
						<xsl:value-of select="concat(\'msg_\', $post_id, \'_footer\')"/>
					</xsl:attribute>
					<xsl:apply-templates/>
				</div>
			</xsl:if>
		</xsl:template>
		<xsl:template match="attachment">
			<div class="attached">
				<div class="attachments_bot">
					<a>
						<xsl:attribute name="href">
							<xsl:value-of select="concat(id, \' - \', name)"/>
						</xsl:attribute>
						<img class="centericon" alt="*">
							<xsl:attribute name="src">
								<xsl:value-of select="concat($themeurl, \'/images/icons/clip.png\')"/>
							</xsl:attribute>
						</img>
						<xsl:text> </xsl:text>
						<xsl:value-of select="name"/>
					</a>
					<br/>
					<xsl:text>(</xsl:text>
					<a class="bbc_link">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($scripturl, \'?action=profile;area=dlattach;u=\', $member_id, \';attach=\', id, \';t=\', $dltoken)"/>
						</xsl:attribute>
						<xsl:value-of select="$txt_download_original"/>
					</a>
					<xsl:text>)</xsl:text>
					<br/>
					<xsl:value-of select="size/@label"/>
					<xsl:text>: </xsl:text>
					<xsl:value-of select="size"/>
					<br/>
					<xsl:value-of select="downloads/@label"/>
					<xsl:text>: </xsl:text>
					<xsl:value-of select="downloads"/>
				</div>
			</div>
		</xsl:template>';

		// Helper template for printing the user's own info next to the post or personal message.
		$stylesheet['own_user_info'] = '
		<xsl:template name="own_user_info">
			<xsl:if test="/*/avatar">
				<li class="avatar">
					<a>
						<xsl:attribute name="href">
							<xsl:value-of select="/*/link"/>
						</xsl:attribute>
						<img class="avatar">
							<xsl:attribute name="src">
								<xsl:value-of select="/*/avatar"/>
							</xsl:attribute>
						</img>
					</a>
				</li>
			</xsl:if>
			<li class="membergroup">
				<xsl:value-of select="/*/position"/>
			</li>
			<xsl:if test="/*/title">
				<li class="title">
					<xsl:value-of select="/*/title"/>
				</li>
			</xsl:if>
			<li class="postgroup">
				<xsl:value-of select="/*/post_group"/>
			</li>
			<li class="postcount">
				<xsl:value-of select="concat(/*/posts/@label, \': \')"/>
				<xsl:value-of select="/*/posts"/>
			</li>
			<xsl:if test="/*/blurb">
				<li class="blurb">
					<xsl:value-of select="/*/blurb"/>
				</li>
			</xsl:if>
		</xsl:template>';

		// Helper template for printing the quickbuttons
		$stylesheet['quickbuttons'] = '
		<xsl:template name="quickbuttons">
			<xsl:param name="toggle_target"/>
			<ul class="quickbuttons quickbuttons_post sf-js-enabled sf-arrows" style="touch-action: pan-y;">
				<li>
					<a>
						<xsl:attribute name="onclick">
							<xsl:text>$(\'#</xsl:text>
							<xsl:value-of select="$toggle_target"/>
							<xsl:text> .inner\').toggle();</xsl:text>
						</xsl:attribute>
						<xsl:value-of select="$txt_view_source_button"/>
					</a>
				</li>
			</ul>
		</xsl:template>';

		// Helper template for printing a signature
		$stylesheet['signature'] = '
		<xsl:template name="signature">
			<xsl:if test="/*/signature">
				<div class="signature">
					<xsl:value-of select="/*/signature" disable-output-escaping="yes"/>
				</div>
			</xsl:if>
		</xsl:template>';

		// Helper template for printing a list of PM recipients
		$stylesheet['recipient'] = '
		<xsl:template match="recipient">
			<a>
				<xsl:attribute name="href">
					<xsl:value-of select="link"/>
				</xsl:attribute>
				<xsl:value-of select="name"/>
			</a>
			<xsl:choose>
				<xsl:when test="following-sibling::recipient">
					<xsl:text>, </xsl:text>
				</xsl:when>
				<xsl:otherwise>
					<xsl:text>. </xsl:text>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:template>';

		// Helper template for special handling of the contents of the [html] BBCode
		$stylesheet['bbc_html'] = '
		<xsl:template name="bbc_html_splitter">
			<xsl:param name="bbc_string"/>
			<xsl:param name="inside_outside" select="outside"/>
			<xsl:choose>
				<xsl:when test="$inside_outside = \'outside\'">
					<xsl:choose>
						<xsl:when test="contains($bbc_string, \'[html]\')">
							<xsl:variable name="following_string">
								<xsl:value-of select="substring-after($bbc_string, \'[html]\')" disable-output-escaping="yes"/>
							</xsl:variable>
							<xsl:value-of select="substring-before($bbc_string, \'[html]\')" disable-output-escaping="yes"/>
							<xsl:text>[html]</xsl:text>
							<xsl:call-template name="bbc_html_splitter">
								<xsl:with-param name="bbc_string" select="$following_string"/>
								<xsl:with-param name="inside_outside" select="inside"/>
							</xsl:call-template>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$bbc_string" disable-output-escaping="yes"/>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<xsl:choose>
						<xsl:when test="contains($bbc_string, \'[/html]\')">
							<xsl:variable name="following_string">
								<xsl:value-of select="substring-after($bbc_string, \'[/html]\')" disable-output-escaping="yes"/>
							</xsl:variable>
							<xsl:value-of select="substring-before($bbc_string, \'[/html]\')" disable-output-escaping="no"/>
							<xsl:text>[/html]</xsl:text>
							<xsl:call-template name="bbc_html_splitter">
								<xsl:with-param name="bbc_string" select="$following_string"/>
								<xsl:with-param name="inside_outside" select="outside"/>
							</xsl:call-template>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$bbc_string" disable-output-escaping="no"/>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:template>';

		// Helper templates to build a page index
		$stylesheet['page_index'] = '
		<xsl:template name="page_index">
			<xsl:variable name="current_page" select="/*/@page"/>
			<xsl:variable name="prev_page" select="/*/@page - 1"/>
			<xsl:variable name="next_page" select="/*/@page + 1"/>

			<div class="pagesection">
				<div class="pagelinks floatleft">

					<span class="pages">
						<xsl:value-of select="$txt_pages"/>
					</span>

					<xsl:if test="$current_page &gt; 1">
						<a class="nav_page">
							<xsl:attribute name="href">
								<xsl:value-of select="concat($dlfilename, \'_\', $prev_page, \'.\', $ext)"/>
							</xsl:attribute>
							<span class="main_icons previous_page"></span>
						</a>
					</xsl:if>

					<xsl:call-template name="page_links"/>

					<xsl:if test="$current_page &lt; $last_page">
						<a class="nav_page">
							<xsl:attribute name="href">
								<xsl:value-of select="concat($dlfilename, \'_\', $next_page, \'.\', $ext)"/>
							</xsl:attribute>
							<span class="main_icons next_page"></span>
						</a>
					</xsl:if>
				</div>
			</div>
		</xsl:template>

		<xsl:template name="page_links">
			<xsl:param name="page_num" select="1"/>
			<xsl:variable name="current_page" select="/*/@page"/>
			<xsl:variable name="prev_page" select="/*/@page - 1"/>
			<xsl:variable name="next_page" select="/*/@page + 1"/>

			<xsl:choose>
				<xsl:when test="$page_num = $current_page">
					<span class="current_page">
						<xsl:value-of select="$page_num"/>
					</span>
				</xsl:when>
				<xsl:when test="$page_num = 1 or $page_num = ($current_page - 1) or $page_num = ($current_page + 1) or $page_num = $last_page">
					<a class="nav_page">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
						</xsl:attribute>
						<xsl:value-of select="$page_num"/>
					</a>
				</xsl:when>
				<xsl:when test="$page_num = 2 or $page_num = ($current_page + 2)">
					<span class="expand_pages" onclick="$(\'.nav_page\').removeClass(\'hidden\'); $(\'.expand_pages\').hide();"> ... </span>
					<a class="nav_page hidden">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
						</xsl:attribute>
						<xsl:value-of select="$page_num"/>
					</a>
				</xsl:when>
				<xsl:otherwise>
					<a class="nav_page hidden">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
						</xsl:attribute>
						<xsl:value-of select="$page_num"/>
					</a>
				</xsl:otherwise>
			</xsl:choose>

			<xsl:text> </xsl:text>

			<xsl:if test="$page_num &lt; $last_page">
				<xsl:call-template name="page_links">
					<xsl:with-param name="page_num" select="$page_num + 1"/>
				</xsl:call-template>
			</xsl:if>
		</xsl:template>';

		// Template to insert CSS and JavaScript
		$stylesheet['css_js'] = '
		<xsl:template name="css_js">';

		export_load_css_js();

		if (!empty($context['export_css_files']))
		{
			foreach ($context['export_css_files'] as $css_file)
			{
				$stylesheet['css_js'] .= '
				<link rel="stylesheet">
					<xsl:attribute name="href">
						<xsl:text>' . $css_file['fileUrl'] . '</xsl:text>
					</xsl:attribute>';

				if (!empty($css_file['options']['attributes']))
				{
					foreach ($css_file['options']['attributes'] as $key => $value)
						$stylesheet['css_js'] .= '
					<xsl:attribute name="' . $key . '">
						<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
					</xsl:attribute>';
				}

				$stylesheet['css_js'] .= '
				</link>';
			}
		}

		if (!empty($context['export_css_header']))
		{
			$stylesheet['css_js'] .=  '
			<style><![CDATA[' . "\n" . implode("\n", $context['export_css_header']) . "\n" . ']]>
			</style>';
		}

		if (!empty($context['export_javascript_vars']))
		{
			$stylesheet['css_js'] .=  '
			<script><![CDATA[';

			foreach ($context['export_javascript_vars'] as $var => $val)
				$stylesheet['css_js'] .= "\nvar " . $var . (!empty($val) ? ' = ' . $val : '') . ';';

			$stylesheet['css_js'] .= "\n" . ']]>
			</script>';
		}

		if (!empty($context['export_javascript_files']))
		{
			foreach ($context['export_javascript_files'] as $js_file)
			{
				$stylesheet['css_js'] .= '
				<script>
					<xsl:attribute name="src">
						<xsl:text>' . $js_file['fileUrl'] . '</xsl:text>
					</xsl:attribute>';

				if (!empty($js_file['options']['attributes']))
				{
					foreach ($js_file['options']['attributes'] as $key => $value)
						$stylesheet['css_js'] .= '
					<xsl:attribute name="' . $key . '">
						<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
					</xsl:attribute>';
				}

				$stylesheet['css_js'] .= '
				</script>';
			}
		}

		if (!empty($context['export_javascript_inline']['standard']))
		{
			$stylesheet['css_js'] .=  '
			<script><![CDATA[' . "\n" . implode("\n", $context['export_javascript_inline']['standard']) . "\n" . ']]>
			</script>';
		}

		if (!empty($context['export_javascript_inline']['defer']))
		{
			$stylesheet['css_js'] .= '
			<script><![CDATA[' . "\n" . 'window.addEventListener("DOMContentLoaded", function() {';

			$stylesheet['css_js'] .= "\n\t" . str_replace("\n", "\n\t", implode("\n", $context['export_javascript_inline']['defer']));

			$stylesheet['css_js'] .= "\n" . '});'. "\n" . ']]>
			</script>';
		}

		$stylesheet['css_js'] .= '
		</xsl:template>';

		// End of the XSLT stylesheet
		$stylesheet['footer'] = ($format == 'XML_XSLT' ? "\t" : '') . '</xsl:stylesheet>';
	}

	// Let mods adjust the XSLT stylesheet.
	call_integration_hook('integrate_export_xslt_stylesheet', array(&$stylesheet, $format));

	// Remember for later.
	$xslt_key = isset($xslt_key) ? $xslt_key : $smcFunc['json_encode'](array($format, $uid, $xslt_variables));
	$xslts[$xslt_key] = array('stylesheet' => implode("\n", (array) $stylesheet), 'doctype' => $doctype);

	return $xslts[$xslt_key];
}

/**
 * Loads and prepares CSS and JavaScript for insertion into an XSLT stylesheet.
 */
function export_load_css_js()
{
	global $context, $modSettings, $sourcedir, $smcFunc, $user_info;

	// If we're not running a background task, we need to preserve any existing CSS and JavaScript.
	if (SMF != 'BACKGROUND')
	{
		foreach (array('css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline') as $var)
		{
			if (isset($context[$var]))
				$context['real_' . $var] = $context[$var];

			if ($var == 'javascript_inline')
			{
				foreach ($context[$var] as $key => $value)
					$context[$var][$key] = array();
			}
			else
				$context[$var] = array();
		}
	}
	// Autoloading is unavailable for background tasks, so we have to do things the hard way...
	else
	{
		if (!empty($modSettings['minimize_files']) && (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS')))
		{
			// Include, not require, because minimization is nice to have but not vital here.
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exception.php')));
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'BasicException.php')));
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'FileImportException.php')));
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'IOException.php')));

			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Minify.php')));
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'path-converter', 'src', 'Converter.php')));

			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'CSS.php')));
			include_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'JS.php')));

			if (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS'))
				$modSettings['minimize_files'] = false;
		}
	}

	// Load our standard CSS files.
	loadCSSFile('index.css', array('minimize' => true, 'order_pos' => 1), 'smf_index');
	loadCSSFile('responsive.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000), 'smf_responsive');

	if ($context['right_to_left'])
		loadCSSFile('rtl.css', array('order_pos' => 4000), 'smf_rtl');

	// In case any mods added relevant CSS.
	call_integration_hook('integrate_pre_css_output');

	// This next chunk mimics some of template_css()
	$css_to_minify = array();
	$normal_css_files = array();

	usort(
		$context['css_files'],
		function ($a, $b)
		{
			return $a['options']['order_pos'] < $b['options']['order_pos'] ? -1 : ($a['options']['order_pos'] > $b['options']['order_pos'] ? 1 : 0);
		}
	);

	foreach ($context['css_files'] as $css_file)
	{
		if (!isset($css_file['options']['minimize']))
			$css_file['options']['minimize'] = true;

		if (!empty($css_file['options']['minimize']) && !empty($modSettings['minimize_files']))
			$css_to_minify[] = $css_file;
		else
			$normal_css_files[] = $css_file;
	}

	$minified_css_files = !empty($css_to_minify) ? custMinify($css_to_minify, 'css') : array();

	$context['css_files'] = array();
	foreach (array_merge($minified_css_files, $normal_css_files) as $css_file)
	{
		// Embed the CSS in a <style> element if possible, since exports are supposed to be standalone files.
		if (file_exists($css_file['filePath']))
			$context['css_header'][] = file_get_contents($css_file['filePath']);

		elseif (!empty($css_file['fileUrl']))
			$context['css_files'][] = $css_file;
	}

	// Next, we need to do for JavaScript what we just did for CSS.
	loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js', array('external' => true, 'seed' => false), 'smf_jquery');

	// There might be JavaScript that we need to add in order to support custom BBC or something.
	call_integration_hook('integrate_pre_javascript_output', array(false));
	call_integration_hook('integrate_pre_javascript_output', array(true));

	$js_to_minify = array();
	$all_js_files = array();

	foreach ($context['javascript_files'] as $js_file)
	{
		if (!empty($js_file['options']['minimize']) && !empty($modSettings['minimize_files']))
		{
			if (!empty($js_file['options']['async']))
				$js_to_minify['async'][] = $js_file;

			elseif (!empty($js_file['options']['defer']))
				$js_to_minify['defer'][] = $js_file;

			else
				$js_to_minify['standard'][] = $js_file;
		}
		else
			$all_js_files[] = $js_file;
	}

	$context['javascript_files'] = array();
	foreach ($js_to_minify as $type => $js_files)
	{
		if (!empty($js_files))
		{
			$minified_js_files = custMinify($js_files, 'js');
			$all_js_files = array_merge($all_js_files, $minified_js_files);
		}
	}

	foreach ($all_js_files as $js_file)
	{
		// As with the CSS, embed whatever JavaScript we can.
		if (file_exists($js_file['filePath']))
			$context['javascript_inline'][(!empty($js_file['options']['defer']) ? 'defer' : 'standard')][] = file_get_contents($js_file['filePath']);

		elseif (!empty($js_file['fileUrl']))
			$context['javascript_files'][] = $js_file;
	}

	// We need to embed the smiley images, too. To save space, we store the image data in JS variables.
	$smiley_mimetypes = array(
		'gif' => 'image/gif',
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'tiff' => 'image/tiff',
		'svg' => 'image/svg+xml',
	);

	foreach (glob(implode(DIRECTORY_SEPARATOR, array($modSettings['smileys_dir'], $user_info['smiley_set'], '*.*'))) as $smiley_file)
	{
		$pathinfo = pathinfo($smiley_file);

		if (!isset($smiley_mimetypes[$pathinfo['extension']]))
			continue;

		$var = implode('_', array('smf', 'smiley', $pathinfo['filename'], $pathinfo['extension']));

		if (!isset($context['javascript_vars'][$var]))
			$context['javascript_vars'][$var] = '\'data:' . $smiley_mimetypes[$pathinfo['extension']] . ';base64,' . base64_encode(file_get_contents($smiley_file)) . '\'';
	}

	$context['javascript_inline']['defer'][] = implode("\n", array(
		'$("img.smiley").each(function() {',
		'	var data_uri_var = $(this).attr("src").replace(/.*\/(\w+)\.(\w+)$/, "smf_smiley_$1_$2");',
		'	$(this).attr("src", window[data_uri_var]);',
		'});',
	));

	// Now move everything to the special export version of these arrays.
	foreach (array('css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline') as $var)
	{
		if (isset($context[$var]))
			$context['export_' . $var] = $context[$var];

		unset($context[$var]);
	}

	// Finally, restore the real values.
	if (SMF !== 'BACKGROUND')
	{
		foreach (array('css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline') as $var)
		{
			if (isset($context['real_' . $var]))
				$context[$var] = $context['real_' . $var];

			unset($context['real_' . $var]);
		}
	}
}

?>