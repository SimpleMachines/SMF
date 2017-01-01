<?php

/**
 * This file handles avatar and attachment preview requests. The whole point of this file is to reduce the loaded stuff to show an image.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Shows an avatar based on $_GET['attach']
 */
function showAttachment()
{
	global $smcFunc, $modSettings, $maintenance, $context;

	// Some defaults that we need.
	$context['character_set'] = empty($modSettings['global_character_set']) ? (empty($txt['lang_character_set']) ? 'ISO-8859-1' : $txt['lang_character_set']) : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';

	// An early hook to set up global vars, clean cache and other early process.
	call_integration_hook('integrate_pre_download_request');

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
		header('Content-Encoding: none');
	}

	// Better handling.
	$attachId = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);

	// We need a valid ID.
	if (empty($attachId))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// A thumbnail has been requested? madness! madness I say!
	$preview = isset($_REQUEST['preview']) ? $_REQUEST['preview'] : (isset($_REQUEST['type']) && $_REQUEST['type'] == 'preview' ? $_REQUEST['type'] : 0);
	$showThumb = isset($_REQUEST['thumb']) || !empty($preview);
	$attachTopic = isset($_REQUEST['topic']) ? (int) $_REQUEST['topic'] : 0;

	// No access in strict maintenance mode or you don't have permission to see attachments.
	if ((!empty($maintenance) && $maintenance == 2) || !allowedTo('view_attachments'))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// Use cache when possible.
	if (($cache = cache_get_data('attachment_lookup_id-' . $attachId)) != null)
		list($file, $thumbFile) = $cache;

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
				SELECT id_folder, filename, file_hash, fileext, id_attach, id_thumb, attachment_type, mime_type, approved, id_msg
				FROM {db_prefix}attachments
				WHERE id_attach = {int:attach}
				LIMIT 1',
				array(
					'attach' => $attachId,
				)
			);
		}

		// No attachment has been found.
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			header('HTTP/1.0 404 File Not Found');
			die('404 File Not Found');
		}

		$file = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// If theres a message ID stored, we NEED a topic ID.
		if (!empty($file['id_msg']) && empty($attachTopic) && empty($preview))
		{
			header('HTTP/1.0 404 File Not Found');
			die('404 File Not Found');
		}

		// Previews doesn't have this info.
		if (empty($preview))
		{
			$request2 = $smcFunc['db_query']('', '
				SELECT a.id_msg
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
				WHERE a.id_attach = {int:attach}
				LIMIT 1',
				array(
					'attach' => $attachId,
					'current_topic' => $attachTopic,
				)
			);

			// The provided topic must match the one stored in the DB for this particular attachment, also.
			if ($smcFunc['db_num_rows']($request2) == 0)
			{
				header('HTTP/1.0 404 File Not Found');
				die('404 File Not Found');
			}

			$smcFunc['db_free_result']($request2);
		}

		// set filePath and ETag time
		$file['filePath'] = getAttachmentFilename($file['filename'], $attachId, $file['id_folder'], false, $file['file_hash']);
		// ensure variant attachment compatibility
		$filePath = pathinfo($file['filePath']);
		$file['filePath'] = !file_exists($file['filePath']) ? substr($file['filePath'], 0, -(strlen($filePath['extension']) + 1)) : $file['filePath'];
		$file['etag'] = '"' . md5_file($file['filePath']) . '"';

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
				$attachId = $thumbFile['id_attach'];

				// set filePath and ETag time
				$thumbFile['filePath'] = getAttachmentFilename($thumbFile['filename'], $attachId, $thumbFile['id_folder'], false, $thumbFile['file_hash']);
				$thumbFile['etag'] = '"' . md5_file($thumbFile['filePath']) . '"';
			}
		}

		// Cache it.
		if (!empty($file) || !empty($thumbFile))
			cache_put_data('attachment_lookup_id-' . $file['id_attach'], array($file, $thumbFile), mt_rand(850, 900));
	}

	// Update the download counter (unless it's a thumbnail).
	if ($file['attachment_type'] != 3 && empty($showThumb))
		$smcFunc['db_query']('attach_download_increase', '
			UPDATE LOW_PRIORITY {db_prefix}attachments
			SET downloads = downloads + 1
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attachId,
			)
		);

	// Replace the normal file with its thumbnail if it has one!
	if (!empty($showThumb) && !empty($thumbFile))
		$file = $thumbFile;

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (!file_exists($file['filePath']))
	{
		header((preg_match('~HTTP/1\.[01]~i', $_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 404 Not Found');
		header('Content-Type: text/plain; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// We need to die like this *before* we send any anti-caching headers as below.
		die('File not found.');
	}

	// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($file['filePath']))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = '"' . substr($_REQUEST['attach'] . $file['filePath'] . filemtime($file['filePath']), 0, 64) . '"';
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();

		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	// Send the attachment headers.
	header('Pragma: ');

	if (!isBrowser('gecko'))
		header('Content-Transfer-Encoding: binary');

	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file['filePath'])) . ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: ' . $eTag);

	// Make sure the mime type warrants an inline display.
	if (isset($_REQUEST['image']) && !empty($file['mime_type']) && strpos($file['mime_type'], 'image/') !== 0)
		unset($_REQUEST['image']);

	// Does this have a mime type?
	elseif (!empty($file['mime_type']) && (isset($_REQUEST['image']) || !in_array($file['fileext'], array('jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'))))
		header('Content-Type: ' . strtr($file['mime_type'], array('image/bmp' => 'image/x-ms-bmp')));

	else
	{
		header('Content-Type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $file['filename']) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($file['filename'], 'UTF-8', $context['character_set']) : $file['filename']);
	$disposition = !isset($_REQUEST['image']) ? 'attachment' : 'inline';

	// Different browsers like different standards...
	if (isBrowser('firefox'))
		header('Content-Disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)));

	elseif (isBrowser('opera'))
		header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name) . '"');

	elseif (isBrowser('ie'))
		header('Content-Disposition: ' . $disposition . '; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)) . '"');

	else
		header('Content-Disposition: ' . $disposition . '; filename="' . $utf8name . '"');

	// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
	if (!isset($_REQUEST['image']) && in_array($file['fileext'], array('gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff')))
		header('Cache-Control: no-cache');

	else
		header('Cache-Control: max-age=' . (525600 * 60) . ', private');

	header('Content-Length: ' . filesize($file['filePath']));

	// Try to buy some time...
	@set_time_limit(600);

	// Since we don't do output compression for files this large...
	if (filesize($file['filePath']) > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

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