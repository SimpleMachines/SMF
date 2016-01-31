<?php

/**
 * This file handles avatar and attachment preview requests. The whole point of this file is to reduce the loaded stuff to show an image.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
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

	// We need a valid ID.
	if(empty($_GET['attach']) || (string)$_GET['attach'] != (string)(int)$_GET['attach'])
		die;

	// A thumbnail has been requested? madness! madness I say!
	$showThumb = isset($_GET['thumb']);

	// No access in strict maintenance mode.
	if(!empty($maintenance) && $maintenance == 2)
		die;

	// This is done to clear any output that was made before now.
	if(!empty($modSettings['enableCompressedOutput']) && !headers_sent() && ob_get_length() == 0)
	{
		if(@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
			$modSettings['enableCompressedOutput'] = 0;
		else
			ob_start('ob_gzhandler');
	}

	if(empty($modSettings['enableCompressedOutput']))
	{
		ob_start();
		header('Content-Encoding: none');
	}

	// Better handling.
	$id_attach = (int) $_GET['attach'];

	// Use cache when possible.
	if(($cache = cache_get_data('attachment_lookup_id-'. $id_attach)) != null)
		$file = $cache;

	// Get the info from the DB.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_folder, filename AS real_filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member, id_thumb
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
			LIMIT 1',
			array(
				'id_attach' => $id_attach,
				'blank_id_member' => 0,
			)
		);

		$file = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Update the download counter (unless it's a thumbnail).
		if ($file['attachment_type'] != 3)
			$smcFunc['db_query']('attach_download_increase', '
				UPDATE LOW_PRIORITY {db_prefix}attachments
				SET downloads = downloads + 1
				WHERE id_attach = {int:id_attach}',
				array(
					'id_attach' => $id_attach,
				)
			);

		$file['filename'] = getAttachmentFilename($file['real_filename'], $id_attach, $file['id_folder'], false, $file['file_hash']);

		// ETag time.
		$file['etag'] = '"'. function_exists('md5_file') ? md5_file($file['filename']) : md5(file_get_contents($file['filename'])). '"';

		// Cache it.
		cache_put_data('attachment_lookup_id-'. $id_attach, $file, mt_rand(850, 900));
	}

	// Replace the normal file with its thumbnail if it has one!
	if ($showThumb && $file['id_thumb'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_folder, filename AS real_filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member, id_thumb
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
			LIMIT 1',
			array(
				'id_attach' => $file['id_thumb'],
				'blank_id_member' => 0,
				'attachment_type' => 3,
			)
		);

		$thumbFile = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Got something! replace the $file var with the thumbnail info.
		if ($thumbFile)
		{
			$id_attach = $file['id_thumb'];
			$file = $thumbFile;
			$file['filename'] = getAttachmentFilename($file['real_filename'], $id_attach, $file['id_folder'], false, $file['file_hash']);

			// ETag time.
			$file['etag'] = '"'. function_exists('md5_file') ? md5_file($file['filename']) : md5(file_get_contents($file['filename'])). '"';
		}
	}

	// The file does not exists
	if(!file_exists($file['filename']))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($file['filename']))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	header('Pragma: ');
	header('Expires: '. gmdate('D, d M Y H:i:s', time() + 31536000). ' GMT');
	header('Last-Modified: '. gmdate('D, d M Y H:i:s', filemtime($file['filename'])). ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: '. $file['etag']);

	// Are we handling a file? This is just a quick way to force downloading, its not really designed to actually download an attachment properly. Doens't take into consideration which browser the user is using.
	if (isset($_GET['file']))
	{
		header('Content-Type: application/octet-stream');

		// Convert the file to UTF-8, cuz most browsers dig that.
		$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $file['real_filename']) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($file['real_filename'], 'UTF-8', $context['character_set']) : $file['real_filename']);

		header('Content-Disposition: attachment; filename="' . $utf8name . '"');
		header('Cache-Control: max-age=' . (525600 * 60) . ', private');
	}

	header('Content-Type: '. $file['mime_type']);

	// Since we don't do output compression for files this large...
	if (filesize($file['filename']) > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		$fp = fopen($file['filename'], 'rb');
		while (!feof($fp))
		{
			echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($file['filename']) === null)
		echo file_get_contents($file['filename']);

	die();
}
?>