<?php

/**
 * This file handles the avatar requests. The whole point of this file is to reduce the loaded stuff to show an image
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 *
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function showAvatar()
{
	global $smcFunc, $modSettings, $sourcedir, $maintenance;

	// We need a valid ID
	if(empty($_GET['attach']) || (string)$_GET['attach'] != (string)(int)$_GET['attach'])
		die;

	// No access in strict maintenance mode
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

	// Better handling
	$id_attach = (int) $_GET['attach'];

	// Use cache when possible
	if(($cache = cache_get_data('avatar_lookup_id-'. $id_attach)) != null)
		$file = $cache;

	// Get the info from the DB
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_folder, filename AS real_filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
				AND id_member > {int:blank_id_member}
			LIMIT 1',
			array(
				'id_attach' => $id_attach,
				'blank_id_member' => 0,
			)
		);

		$file = $smcFunc['db_fetch_assoc']($request);

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

		// ETag time
		$file['etag'] = '"'. function_exists('md5_file') ? md5_file($file['filename']) : md5(file_get_contents($file['filename'])). '"';

		// Cache it... (Why do I randomly select a length at which to expire? Search around for RIP_JITTER :P)
		cache_put_data('avatar_lookup_id-'. $id_attach, $file, mt_rand(850, 900));
	}

	// The file does not exists
	if(!file_exists($file['filename']))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// If it hasn't been modified since the last time this attachement was retrieved, there's no need to display it again.
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
			print fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($file['filename']) === null)
		print file_get_contents($file['filename']);

	die();
}