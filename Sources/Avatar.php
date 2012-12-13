<?php

/**
 * This file handles the avatar requests.
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

/**
 * Handles attached avatars meant for template/theme usage.
 *
 * Downloads an aavatar, and increments the download count.
 * It disables the session parser, and clears any previous output.
 * It depends on the attachmentUploadDir setting being correct.
 * It is accessed via the query string ?action=dlavatar.
 * Views to avatars do not increase hits and are not logged in the "Who's Online" log.
 * @return null
 */
 function Download()
 {
	global $modSettings, $smcFunc, $context;

	// We need a valid ID
	if(empty($_GET['attach']) || (string)$_GET['attach'] != (string)(int)$_GET['attach'])
		die('Not found');

	// Require Load.php
	require($sourcedir. '/Load.php');

	// No access in maintenance mode
	if(!empty($maintenance) && $maintenance == 2)
		die;

	$smcFunc = array();

	// Load the database.
	loadDatabase();

	// Load the settings
	reloadSettings();

	// This is done to clear any output that was made before now.
	if(!empty($modSettings['enableCompressedOutput']) && !headers_sent() && ob_get_length() == 0)
	{
		if(@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
			$modSettings['enableCompressedOutput'] = '0';
		else
			ob_start('ob_gzhandler');
	}

	else
	{
		ob_start();
		header('Content-Encoding: none');
	}

	$id_attach = (int)$_GET['attach'];

	// Use cache when possible
	if(($cache = cache_get_data('avatar_lookup_id-'. $id_attach)) != null)
		$file = $cache;

	// Get the file data
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_folder, filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
				AND id_member > {int:blank_id_member}
			LIMIT 1',
			array(
				'id_attach' => $id_attach,
				'blank_id_member' => 0,
			)
		);

		$file = $smcFunc['db_fetch_assoc']($result);

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

		require($sourcedir. '/Subs.php');

		// Get the file info
		$file['filename'] = getAttachmentFilename($file['real_filename'], $id_attach, $file['id_folder'], false, $file['file_hash']);
		$file['etag'] = '"'. function_exists('md5_file') ? md5_file($file['filename']) : md5(file_get_contents($file['filename'])). '"';

		// Cache it... (Why do I randomly select a length at which to expire? Search around for RIP_JITTER :P)
		cache_put_data('avatar_lookup_id-'. $id_attach, $file, mt_rand(850, 900));
	}

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (!file_exists($file['filename']))
	{
		header('HTTP/1.0 404 File Not Found');

		// We need to die like this *before* we send any anti-caching headers as below.
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

	// Send the avatar headers.
	header('Expires: '. gmdate('D, d M Y H:i:s', time() + 31536000). ' GMT'); # Expire in a year. Hehe.
	header('Last-Modified: '. gmdate('D, d M Y H:i:s', filemtime($file['filename'])). ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: '. $file['etag']);
	header('Content-Type: '. $file['mime_type']);

	// Output the file
	$fp = fopen($file['filename'], 'rb');

	while(!feof($fp))
		echo fread($fp, 8192);

	fclose($fp);
	exit;
 }