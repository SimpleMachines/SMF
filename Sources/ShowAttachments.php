<?php

/**
 * This file handles avatar and attachment requests. The whole point of this file is to reduce the loaded stuff to show an image.
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
 * Downloads an avatar or attachment based on $_GET['attach'], and increments the download count.
 * It requires the view_attachments permission.
 * It disables the session parser, and clears any previous output.
 * It depends on the attachmentUploadDir setting being correct.
 * It is accessed via the query string ?action=dlattach.
 * Views to attachments do not increase hits and are not logged in the "Who's Online" log.
 */
function showAttachment()
{
	global $smcFunc, $modSettings, $maintenance, $context, $txt, $user_info;

	// Some defaults that we need.
	$context['character_set'] = empty($modSettings['global_character_set']) ? (empty($txt['lang_character_set']) ? 'ISO-8859-1' : $txt['lang_character_set']) : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';

	// An early hook to set up global vars, clean cache and other early process.
	call_integration_hook('integrate_pre_download_request');

	// This is done to clear any output that was made before now.
	ob_end_clean();
	header_remove('content-encoding');

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

	// Better handling.
	$attachId = $_REQUEST['attach'] = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);

	// We need a valid ID.
	if (empty($attachId))
	{
		send_http_status(404, 'File Not Found');
		die('404 File Not Found');
	}

	// A thumbnail has been requested? madness! madness I say!
	$showThumb = isset($_REQUEST['thumb']);

	// No access in strict maintenance mode.
	if (!empty($maintenance) && $maintenance == 2)
	{
		send_http_status(404, 'File Not Found');
		die('404 File Not Found');
	}

	// Use cache when possible.
	if (($cache = cache_get_data('attachment_lookup_id-' . $attachId)) != null)
		list ($file, $thumbFile) = $cache;

	// Get the info from the DB.
	if (empty($file) || empty($thumbFile) && !empty($file['id_thumb']))
	{
		// Do we have a hook wanting to use our attachment system? We use $attachRequest to prevent accidental usage of $request.
		$attachRequest = null;
		call_integration_hook('integrate_download_request', array(&$attachRequest));
		if (!is_null($attachRequest) && $smcFunc['db_is_resource']($attachRequest))
			$request = $attachRequest;
		else
		{
			// Make sure this attachment is on this board and load its info while we are at it.
			$request = $smcFunc['db_query']('', '
				SELECT
					{string:source} AS source,
					a.id_folder, a.filename, a.file_hash, a.fileext, a.id_attach,
					a.id_thumb, a.attachment_type, a.mime_type, a.approved, a.id_msg,
					m.id_board
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.id_attach = {int:attach}
				LIMIT 1',
				array(
					'source' => 'SMF',
					'attach' => $attachId,
				)
			);
		}

		// No attachment has been found.
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			send_http_status(404, 'File Not Found');
			die('404 File Not Found');
		}

		$file = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// set filePath and ETag time
		$file['filePath'] = getAttachmentFilename($file['filename'], $attachId, $file['id_folder'], false, $file['file_hash']);
		// ensure variant attachment compatibility
		$filePath = pathinfo($file['filePath']);

		$file['exists'] = file_exists($file['filePath']);
		$file['filePath'] = !$file['exists'] && isset($filePath['extension']) ? substr($file['filePath'], 0, -(strlen($filePath['extension']) + 1)) : $file['filePath'];
		$file['mtime'] = $file['exists'] ? filemtime($file['filePath']) : 0;
		$file['size'] = $file['exists'] ? filesize($file['filePath']) : 0;
		$file['etag'] = '"' . sha1_file($file['filePath']) . '"';

		// now get the thumbfile!
		$thumbFile = array();
		if (!empty($file['id_thumb']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_folder, filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member
				FROM {db_prefix}attachments
				WHERE id_attach = {int:thumb_id}
				LIMIT 1',
				array(
					'thumb_id' => $file['id_thumb'],
				)
			);

			$thumbFile = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			// Got something! replace the $file var with the thumbnail info.
			if ($thumbFile)
			{
				$thumbFile['filePath'] = getAttachmentFilename($thumbFile['filename'], $thumbFile['id_attach'], $thumbFile['id_folder'], false, $thumbFile['file_hash']);
				$thumbPath = pathinfo($thumbFile['filePath']);

				// set filePath and ETag time
				$thumbFile['exists'] = file_exists($thumbFile['filePath']);
				$thumbFile['filePath'] = !$thumbFile['exists'] && isset($thumbPath['extension']) ? substr($thumbFile['filePath'], 0, -(strlen($thumbPath['extension']) + 1)) : $thumbFile['filePath'];
				$thumbFile['mtime'] = $thumbFile['exists'] ? filemtime($thumbFile['filePath']) : 0;
				$thumbFile['size'] = $thumbFile['exists'] ? filesize($thumbFile['filePath']) : 0;
				$thumbFile['etag'] = '"' . sha1_file($thumbFile['filePath']) . '"';
			}
		}

		// Cache it.
		if (!empty($file) || !empty($thumbFile))
			cache_put_data('attachment_lookup_id-' . $file['id_attach'], array($file, $thumbFile), mt_rand(850, 900));
	}

	// Can they see attachments on this board?
	if (!empty($file['id_msg']))
	{
		// Special case for profile exports.
		if (!empty($context['attachment_allow_hidden_boards']))
		{
			$boards_allowed = array(0);
		}
		// Check permissions and board access.
		elseif (($boards_allowed = cache_get_data('view_attachment_boards_id-' . $user_info['id'])) == null)
		{
			$boards_allowed = boardsAllowedTo('view_attachments');
			cache_put_data('view_attachment_boards_id-' . $user_info['id'], $boards_allowed, mt_rand(850, 900));
		}
	}

	// No access if you don't have permission to see this attachment.
	if
	(
		// This was from SMF or a hook didn't claim it.
		(
			empty($file['source'])
			|| $file['source'] == 'SMF'
		)
		&& (
			// No id_msg and no id_member, so we don't know where its from.
			// Avatars will have id_msg = 0 and id_member > 0.
			(
				empty($file['id_msg'])
				&& empty($file['id_member'])
			)
			// When we have a message, we need a board and that board needs to
			// let us view the attachment.
			|| (
				!empty($file['id_msg'])
				&& (
					empty($file['id_board'])
					|| ($boards_allowed !== array(0) && !in_array($file['id_board'], $boards_allowed))
				)
			)
		)
		// We are not previewing an attachment.
		&& !isset($_SESSION['attachments_can_preview'][$attachId])
	)
	{
		send_http_status(404, 'File Not Found');
		die('404 File Not Found');
	}

	// If attachment is unapproved, see if user is allowed to approve
	if (!$file['approved'] && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}
			LIMIT 1',
			array(
				'id_msg' => $file['id_msg'],
			)
		);

		$id_member = $smcFunc['db_fetch_assoc']($request)['id_member'];
		$smcFunc['db_free_result']($request);

		// Let users see own unapproved attachments
		if ($id_member != $user_info['id'])
		{
			send_http_status(403, 'Forbidden');
			die('403 Forbidden');
		}
	}

	// Replace the normal file with its thumbnail if it has one!
	if (!empty($showThumb) && !empty($thumbFile))
		$file = $thumbFile;

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (empty($file['exists']))
	{
		send_http_status(404);
		header('content-type: text/plain; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// We need to die like this *before* we send any anti-caching headers as below.
		die('File not found.');
	}

	// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (!empty($file['mtime']) && strtotime($modified_since) >= $file['mtime'])
		{
			ob_end_clean();
			header_remove('content-encoding');

			// Answer the question - no, it hasn't been modified ;).
			send_http_status(304);
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	if (!empty($file['etag']) && !empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $file['etag']) !== false)
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
		$range_end = !$range_end ? $file['size'] - 1 : intval($range_end);
		$new_length = $range_end - $range + 1;
	}

	// Update the download counter (unless it's a thumbnail or resuming an incomplete download).
	if ($file['attachment_type'] != 3 && empty($showThumb) && $range === 0 && empty($context['skip_downloads_increment']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET downloads = downloads + 1
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attachId,
			)
		);

	// Send the attachment headers.
	header('pragma: ');

	if (!isBrowser('gecko'))
		header('content-transfer-encoding: binary');

	header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s', $file['mtime']) . ' GMT');
	header('accept-ranges: bytes');
	header('connection: close');
	header('etag: ' . $file['etag']);

	// Make sure the mime type warrants an inline display.
	if (isset($_REQUEST['image']) && !empty($file['mime_type']) && strpos($file['mime_type'], 'image/') !== 0)
		unset($_REQUEST['image']);

	// Does this have a mime type?
	elseif (!empty($file['mime_type']) && (isset($_REQUEST['image']) || !in_array($file['fileext'], array('jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'))))
		header('content-type: ' . strtr($file['mime_type'], array('image/bmp' => 'image/x-ms-bmp')));

	else
	{
		header('content-type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $file['filename']) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($file['filename'], 'UTF-8', $context['character_set']) : $file['filename']);

	if (!empty($context['prepend_attachment_id']))
		$utf8name = $_REQUEST['attach'] . ' - ' . $utf8name;

	// On mobile devices, audio and video should be served inline so the browser can play them.
	if (isset($_REQUEST['image']) || (isBrowser('is_mobile') && (strpos($file['mime_type'], 'audio/') !== 0 || strpos($file['mime_type'], 'video/') !== 0)))
		$disposition = 'inline';
	else
		$disposition = 'attachment';

	// Different browsers like different standards...
	if (isBrowser('firefox'))
		header('content-disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)));

	elseif (isBrowser('opera'))
		header('content-disposition: ' . $disposition . '; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name) . '"');

	elseif (isBrowser('ie'))
		header('content-disposition: ' . $disposition . '; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)) . '"');

	else
		header('content-disposition: ' . $disposition . '; filename="' . $utf8name . '"');

	// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
	if (!isset($_REQUEST['image']) && in_array($file['fileext'], array('gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff')))
		header('cache-control: no-cache');

	else
		header('cache-control: max-age=' . (525600 * 60) . ', private');

	// Multipart and resuming support
	if (isset($_SERVER['HTTP_RANGE']))
	{
		send_http_status(206);
		header("content-length: $new_length");
		header("content-range: bytes $range-$range_end/$file[size]");
	}
	else
		header("content-length: " . $file['size']);

	// Allow customizations to hook in here before we send anything to modify any headers needed.  Or to change the process of how we output.
	call_integration_hook('integrate_download_headers');

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

		$fp = fopen($file['filePath'], 'rb');

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
	elseif ($file['size'] > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		header_remove('content-encoding');

		$fp = fopen($file['filePath'], 'rb');
		while (!feof($fp))
		{
			echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($file['filePath']) === null)
		echo file_get_contents($file['filePath']);

	die();
}

?>