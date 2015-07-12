<?php

/**
 * This file handles the uploading and creation of attachments
 * as well as the auto management of the attachment directories.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Check if the current directory is still valid or not.
 * If not creates the new directory
 *
 * @return (bool) false if any error occurred
 */
function automanage_attachments_check_directory()
{
	global $boarddir, $modSettings, $context;

	// Not pretty, but since we don't want folders created for every post. It'll do unless a better solution can be found.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'admin')
		$doit = true;
	elseif (empty($modSettings['automanage_attachments']))
		return;
	elseif (!isset($_FILES))
		return;
	elseif (isset($_FILES['attachment']))
		foreach ($_FILES['attachment']['tmp_name'] as $dummy)
			if (!empty($dummy))
			{
				$doit = true;
				break;
			}

	if (!isset($doit))
		return;

	$year = date('Y');
	$month = date('m');
	$day = date('d');

	$rand = md5(mt_rand());
	$rand1 = $rand[1];
	$rand = $rand[0];

	if (!empty($modSettings['attachment_basedirectories']) && !empty($modSettings['use_subdirectories_for_attachments']))
	{
			if (!is_array($modSettings['attachment_basedirectories']))
				$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);
			$base_dir = array_search($modSettings['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']);
	}
	else
		$base_dir = 0;

	if ($modSettings['automanage_attachments'] == 1)
	{
		if (!isset($modSettings['last_attachments_directory']))
			$modSettings['last_attachments_directory'] = array();
		if (!is_array($modSettings['last_attachments_directory']))
			$modSettings['last_attachments_directory'] = unserialize($modSettings['last_attachments_directory']);
		if (!isset($modSettings['last_attachments_directory'][$base_dir]))
			$modSettings['last_attachments_directory'][$base_dir] = 0;
	}

	$basedirectory = (!empty($modSettings['use_subdirectories_for_attachments']) ? ($modSettings['basedirectory_for_attachments']) : $boarddir);
	//Just to be sure: I don't want directory separators at the end
	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	switch ($modSettings['automanage_attachments'])
	{
		case 1:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . (isset($modSettings['last_attachments_directory'][$base_dir]) ? $modSettings['last_attachments_directory'][$base_dir] : 0);
			break;
		case 2:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
			break;
		case 3:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
			break;
		case 4:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand;
			break;
		case 5:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand . DIRECTORY_SEPARATOR . $rand1;
			break;
		default :
			$updir = '';
	}

	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
	if (!in_array($updir, $modSettings['attachmentUploadDir']) && !empty($updir))
		$outputCreation = automanage_attachments_create_directory($updir);
	elseif (in_array($updir, $modSettings['attachmentUploadDir']))
		$outputCreation = true;

	if ($outputCreation)
	{
		$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);
		$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

		updateSettings(array(
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		));
	}

	return $outputCreation;
}

/**
 * Creates a directory
 *
 * @param $updir: the directory to be created
 *
 * @return (bool) false on errors
 */
function automanage_attachments_create_directory($updir)
{
	global $modSettings, $initial_error, $context, $boarddir;

	$tree = get_directory_tree_elements($updir);
	$count = count($tree);

	$directory = attachments_init_dir($tree, $count);
	if ($directory === false)
	{
		// Maybe it's just the folder name
		$tree = get_directory_tree_elements($boarddir . DIRECTORY_SEPARATOR . $updir);
		$count = count($tree);

		$directory = attachments_init_dir($tree, $count);
		if ($directory === false)
			return false;
	}

	$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

	while (!@is_dir($directory) || $count != -1)
	{
		if (!@is_dir($directory))
		{
			if (!@mkdir($directory,0755))
			{
				$context['dir_creation_error'] = 'attachments_no_create';
				return false;
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
		$count--;
	}

	// @todo: chmod (especially with some servers) is usually bad
	if (!is_writable($directory))
	{
		chmod($directory, 0755);
		if (!is_writable($directory))
		{
			chmod($directory, 0775);
			if (!is_writable($directory))
			{
				chmod($directory, 0777);
				if (!is_writable($directory))
				{
					$context['dir_creation_error'] = 'attachments_no_write';
					return false;
				}
			}
		}
	}

	// Everything seems fine...let's create the .htaccess
	if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess'))
		secureDirectory($updir, true);

	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$updir = rtrim($updir, $sep);

	// Only update if it's a new directory
	if (!in_array($updir, $modSettings['attachmentUploadDir']))
	{
		$modSettings['currentAttachmentUploadDir'] = max(array_keys($modSettings['attachmentUploadDir'])) + 1;
		$modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']] = $updir;

		updateSettings(array(
			'attachmentUploadDir' => serialize($modSettings['attachmentUploadDir']),
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		), true);
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
	}

	$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
	return true;
}

/**
 * Called when a directory space limit is reached.
 * Creates a new directory and increments the directory suffix number.
 *
 * @return (bool) false on erros
 */
function automanage_attachments_by_space()
{
	global $modSettings, $boarddir;

	if (!isset($modSettings['automanage_attachments']) || (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] != 1))
		return;

	$basedirectory = !empty($modSettings['use_subdirectories_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : $boarddir;
	// Just to be sure: I don't want directory separators at the end
	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	// Get the current base directory
	if (!empty($modSettings['use_subdirectories_for_attachments']) && !empty($modSettings['attachment_basedirectories']))
	{
		$base_dir = array_search($modSettings['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']);
		$base_dir = !empty($modSettings['automanage_attachments']) ? $base_dir : 0;
	}
	else
		$base_dir = 0;

	// Get the last attachment directory for that base directory
	if (empty($modSettings['last_attachments_directory'][$base_dir]))
		$modSettings['last_attachments_directory'][$base_dir] = 0;
	// And increment it.
	$modSettings['last_attachments_directory'][$base_dir]++;

	$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . $modSettings['last_attachments_directory'][$base_dir];
	if (automanage_attachments_create_directory($updir))
	{
		$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);
		updateSettings(array(
			'last_attachments_directory' => serialize($modSettings['last_attachments_directory']),
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		));
		$modSettings['last_attachments_directory'] = unserialize($modSettings['last_attachments_directory']);

		return true;
	}
	else
		return false;
}

/**
 * Split a path into a list of all directories and subdirectories
 *
 * @param $directory a path
 *
 * @return (mixed) an array of all the directories and subdirectories or false on failure
 */
function get_directory_tree_elements ($directory)
{
	/*
		In Windows server both \ and / can be used as directory separators in paths
		In Linux (and presumably *nix) servers \ can be part of the name
		So for this reasons:
			* in Windows we need to explode for both \ and /
			* while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
	*/
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		$tree = preg_split('#[\\\/]#', $directory);
	else
	{
		if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR)
			return false;

		$tree = explode(DIRECTORY_SEPARATOR, trim($directory,DIRECTORY_SEPARATOR));
	}
	return $tree;
}

/**
 * Return the first part of a path (i.e. c:\ or / + the first directory), used by automanage_attachments_create_directory
 *
 * @param $tree an array
 * @param $count the number of elements in $tree
 *
 * @return (string)
 */
function attachments_init_dir (&$tree, &$count)
{
	$directory = '';
	// If on Windows servers the first part of the path is the drive (e.g. "C:")
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
	{
		 //Better be sure that the first part of the path is actually a drive letter...
		 //...even if, I should check this in the admin page...isn't it?
		 //...NHAAA Let's leave space for users' complains! :P
		if (preg_match('/^[a-z]:$/i',$tree[0]))
			$directory = array_shift($tree);
		else
			return false;

		$count--;
	}
	return $directory;
}

/**
 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
 */
function processAttachments()
{
	global $context, $modSettings, $smcFunc, $txt, $user_info;

	// Make sure we're uploading to the right place.
	if (!empty($modSettings['automanage_attachments']))
		automanage_attachments_check_directory();

	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

	$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

	// Is the attachments folder actualy there?
	if (!empty($context['dir_creation_error']))
		$initial_error = $context['dir_creation_error'];
	elseif (!is_dir($context['attach_dir']))
	{
		$initial_error = 'attach_folder_warning';
		log_error(sprintf($txt['attach_folder_admin_warning'], $context['attach_dir']), 'critical');
	}

	if (!isset($initial_error) && !isset($context['attachments']))
	{
		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $_REQUEST['msg'],
					'attachment_type' => 0,
				)
			);
			list ($context['attachments']['quantity'], $context['attachments']['total_size']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
			$context['attachments'] = array(
				'quantity' => 0,
				'total_size' => 0,
			);
	}

	// Hmm. There are still files in session.
	$ignore_temp = false;
	if (!empty($_SESSION['temp_attachments']['post']['files']) && count($_SESSION['temp_attachments']) > 1)
	{
		// Let's try to keep them. But...
		$ignore_temp = true;
		// If new files are being added. We can't ignore those
		foreach ($_FILES['attachment']['tmp_name'] as $dummy)
			if (!empty($dummy))
			{
				$ignore_temp = false;
				break;
			}

		// Need to make space for the new files. So, bye bye.
		if (!$ignore_temp)
		{
			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
				if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
					unlink($attachment['tmp_name']);

			$context['we_are_history'] = $txt['error_temp_attachments_flushed'];
			$_SESSION['temp_attachments'] = array();
		}
	}

	if (!isset($_FILES['attachment']['name']))
		$_FILES['attachment']['tmp_name'] = array();

	if (!isset($_SESSION['temp_attachments']))
		$_SESSION['temp_attachments'] = array();

	// Remember where we are at. If it's anywhere at all.
	if (!$ignore_temp)
		$_SESSION['temp_attachments']['post'] = array(
			'msg' => !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
			'last_msg' => !empty($_REQUEST['last_msg']) ? $_REQUEST['last_msg'] : 0,
			'topic' => !empty($topic) ? $topic : 0,
			'board' => !empty($board) ? $board : 0,
		);

	// If we have an initial error, lets just display it.
	if (!empty($initial_error))
	{
		$_SESSION['temp_attachments']['initial_error'] = $initial_error;

		// And delete the files 'cos they ain't going nowhere.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
			if (file_exists($_FILES['attachment']['tmp_name'][$n]))
				unlink($_FILES['attachment']['tmp_name'][$n]);

		$_FILES['attachment']['tmp_name'] = array();
	}

	// Loop through $_FILES['attachment'] array and move each file to the current attachments folder.
	foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
	{
		if ($_FILES['attachment']['name'][$n] == '')
			continue;

		// First, let's first check for PHP upload errors.
		$errors = array();
		if (!empty($_FILES['attachment']['error'][$n]))
		{
			if ($_FILES['attachment']['error'][$n] == 2)
				$errors[] = array('file_too_big', array($modSettings['attachmentSizeLimit']));
			elseif ($_FILES['attachment']['error'][$n] == 6)
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_6'], 'critical');
			else
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);
			if (empty($errors))
				$errors[] = 'attach_php_error';
		}

		// Try to move and rename the file before doing any more checks on it.
		$attachID = 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand());
		$destName = $context['attach_dir'] . '/' . $attachID;
		if (empty($errors))
		{
			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => $smcFunc['htmlspecialchars'](basename($_FILES['attachment']['name'][$n])),
				'tmp_name' => $destName,
				'size' => $_FILES['attachment']['size'][$n],
				'type' => $_FILES['attachment']['type'][$n],
				'id_folder' => $modSettings['currentAttachmentUploadDir'],
				'errors' => array(),
			);

			// Move the file to the attachments folder with a temp name for now.
			if (@move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName))
				@chmod($destName, 0644);
			else
			{
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_timeout';
				if (file_exists($_FILES['attachment']['tmp_name'][$n]))
					unlink($_FILES['attachment']['tmp_name'][$n]);
			}
		}
		else
		{
			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => $smcFunc['htmlspecialchars'](basename($_FILES['attachment']['name'][$n])),
				'tmp_name' => $destName,
				'errors' => $errors,
			);

			if (file_exists($_FILES['attachment']['tmp_name'][$n]))
				unlink($_FILES['attachment']['tmp_name'][$n]);
		}
		// If there's no errors to this point. We still do need to apply some additional checks before we are finished.
		if (empty($_SESSION['temp_attachments'][$attachID]['errors']))
			attachmentChecks($attachID);
	}
	// Mod authors, finally a hook to hang an alternate attachment upload system upon
	// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand())
	// Populate $_SESSION['temp_attachments'][$attachID] with the following:
	//   name => The file name
	//   tmp_name => Path to the temp file ($context['attach_dir'] . '/' . $attachID).
	//   size => File size (required).
	//   type => MIME type (optional if not available on upload).
	//   id_folder => $modSettings['currentAttachmentUploadDir']
	//   errors => An array of errors (use the index of the $txt variable for that error).
	// Template changes can be done using "integrate_upload_template".
	call_integration_hook('integrate_attachment_upload', array());
}

/**
 * Performs various checks on an uploaded file.
 * - Requires that $_SESSION['temp_attachments'][$attachID] be properly populated.
 *
 * @param $attachID
 */
function attachmentChecks($attachID)
{
	global $modSettings, $context, $sourcedir, $smcFunc;

	// No data or missing data .... Not necessarily needed, but in case a mod author missed something.
	if ( empty($_SESSION['temp_attachments'][$attachID]))
		$errror = '$_SESSION[\'temp_attachments\'][$attachID]';
	elseif (empty($attachID))
		$errror = '$attachID';
	elseif (empty($context['attachments']))
		$errror = '$context[\'attachments\']';
	elseif (empty($context['attach_dir']))
		$errror = '$context[\'attach_dir\']';

	// Let's get their attention.
	if (!empty($error))
		fatal_lang_error('attach_check_nag', 'debug', array($error));

	// These are the only valid image types for SMF.
	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	// Just in case this slipped by the first checks, we stop it here and now
	if ($_SESSION['temp_attachments'][$attachID]['size'] == 0)
	{
		$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_0_byte_file';
		return false;
	}

	// First, the dreaded security check. Sorry folks, but this should't be avoided
	$size = @getimagesize($_SESSION['temp_attachments'][$attachID]['tmp_name']);
	if (isset($validImageTypes[$size[2]]))
	{
		require_once($sourcedir . '/Subs-Graphics.php');
		if (!checkImageContents($_SESSION['temp_attachments'][$attachID]['tmp_name'], !empty($modSettings['attachment_image_paranoid'])))
		{
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty($modSettings['attachment_image_reencode']) || (!reencodeImage($_SESSION['temp_attachments'][$attachID]['tmp_name'], $size[2])))
			{
				// Nothing to do: not allowed or not successful re-encoding it.
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'bad_attachment';
				return false;
			}
			// Success! However, successes usually come for a price:
			// we might get a new format for our image...
			$old_format = $size[2];
			$size = @getimagesize($_SESSION['temp_attachments'][$attachID]['tmp_name']);
			if (!(empty($size)) && ($size[2] != $old_format))
			{
				if (isset($validImageTypes[$size[2]]))
					$_SESSION['temp_attachments'][$attachID]['type'] = 'image/' . $validImageTypes[$size[2]];
			}
		}
	}

	// Is there room for this sucker?
	if (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
	{
		// Check the folder size and count. If it hasn't been done already.
		if (empty($context['dir_size']) || empty($context['dir_files']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder_id}
					AND attachment_type != {int:type}',
				array(
					'folder_id' => $modSettings['currentAttachmentUploadDir'],
					'type' => 1,
				)
			);
			list ($context['dir_files'], $context['dir_size']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		$context['dir_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
		$context['dir_files']++;

		// Are we about to run out of room? Let's notify the admin then.
		if (empty($modSettings['attachment_full_notified']) && !empty($modSettings['attachmentDirSizeLimit']) && $modSettings['attachmentDirSizeLimit'] > 4000 && $context['dir_size'] > ($modSettings['attachmentDirSizeLimit'] - 2000) * 1024
			|| (!empty($modSettings['attachmentDirFileLimit']) && $modSettings['attachmentDirFileLimit'] * .95 < $context['dir_files'] && $modSettings['attachmentDirFileLimit'] > 500))
		{
			require_once($sourcedir . '/Subs-Admin.php');
			emailAdmins('admin_attachments_full');
			updateSettings(array('attachment_full_notified' => 1));
		}

		// // No room left.... What to do now???
		if (!empty($modSettings['attachmentDirFileLimit']) && $context['dir_files'] > $modSettings['attachmentDirFileLimit']
			|| (!empty($modSettings['attachmentDirSizeLimit']) && $context['dir_size'] > $modSettings['attachmentDirSizeLimit'] * 1024))
		{
			if (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] == 1)
			{
				// Move it to the new folder if we can.
				if (automanage_attachments_by_space())
				{
					rename($_SESSION['temp_attachments'][$attachID]['tmp_name'], $context['attach_dir'] . '/' . $attachID);
					$_SESSION['temp_attachments'][$attachID]['tmp_name'] = $context['attach_dir'] . '/' . $attachID;
					$_SESSION['temp_attachments'][$attachID]['id_folder'] = $modSettings['currentAttachmentUploadDir'];
					$context['dir_size'] = 0;
					$context['dir_files'] = 0;
				}
				// Or, let the user know that it ain't gonna happen.
				else
				{
					if (isset($context['dir_creation_error']))
						$_SESSION['temp_attachments'][$attachID]['errors'][] = $context['dir_creation_error'];
					else
						$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
				}
			}
			else
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
		}
	}

	// Is the file too big?
	$context['attachments']['total_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
	if (!empty($modSettings['attachmentSizeLimit']) && $_SESSION['temp_attachments'][$attachID]['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('file_too_big', array(comma_format($modSettings['attachmentSizeLimit'], 0)));

	// Check the total upload size for this post...
	if (!empty($modSettings['attachmentPostLimit']) && $context['attachments']['total_size'] > $modSettings['attachmentPostLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attach_max_total_file_size', array(comma_format($modSettings['attachmentPostLimit'], 0), comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $_SESSION['temp_attachments'][$attachID]['size']) / 1024), 0)));

	// Have we reached the maximum number of files we are allowed?
	$context['attachments']['quantity']++;

	// Set a max limit if none exists
	if (empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] >= 50)
		$modSettings['attachmentNumPerPostLimit'] = 50;

	if (!empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] > $modSettings['attachmentNumPerPostLimit'])
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attachments_limit_per_post', array($modSettings['attachmentNumPerPostLimit']));

	// File extension check
	if (!empty($modSettings['attachmentCheckExtensions']))
	{
		$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
		foreach ($allowed as $k => $dummy)
			$allowed[$k] = trim($dummy);

		if (!in_array(strtolower(substr(strrchr($_SESSION['temp_attachments'][$attachID]['name'], '.'), 1)), $allowed))
		{
			$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
			$_SESSION['temp_attachments'][$attachID]['errors'][] = array('cant_upload_type', array($allowed_extensions));
		}
	}

	// Undo the math if there's an error
	if (!empty($_SESSION['temp_attachments'][$attachID]['errors']))
	{
		if (isset($context['dir_size']))
			$context['dir_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		if (isset($context['dir_files']))
			$context['dir_files']--;
		$context['attachments']['total_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		$context['attachments']['quantity']--;
		return false;
	}

	return true;
}

/**
 * Create an attachment, with the given array of parameters.
 * - Adds any additional or missing parameters to $attachmentOptions.
 * - Renames the temporary file.
 * - Creates a thumbnail if the file is an image and the option enabled.
 *
 * @param array $attachmentOptions
 */
function createAttachment(&$attachmentOptions)
{
	global $modSettings, $sourcedir, $smcFunc, $context;

	require_once($sourcedir . '/Subs-Graphics.php');

	// These are the only valid image types for SMF.
	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	// If this is an image we need to set a few additional parameters.
	$size = @getimagesize($attachmentOptions['tmp_name']);
	list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;

	// If it's an image get the mime type right.
	if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
	{
		// Got a proper mime type?
		if (!empty($size['mime']))
			$attachmentOptions['mime_type'] = $size['mime'];
		// Otherwise a valid one?
		elseif (isset($validImageTypes[$size[2]]))
			$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
	}

	// It is possible we might have a MIME type that isn't actually an image but still have a size.
	// For example, Shockwave files will be able to return size but be 'application/shockwave' or similar.
	if (!empty($attachmentOptions['mime_type']) && strpos($attachmentOptions['mime_type'], 'image/') !== 0)
	{
		$attachmentOptions['width'] = 0;
		$attachmentOptions['height'] = 0;
	}

	// Get the hash if no hash has been given yet.
	if (empty($attachmentOptions['file_hash']))
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], false, null, true);

	// Assuming no-one set the extension let's take a look at it.
	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
			$attachmentOptions['fileext'] = '';
	}

	$smcFunc['db_insert']('',
		'{db_prefix}attachments',
		array(
			'id_folder' => 'int', 'id_msg' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
			'size' => 'int', 'width' => 'int', 'height' => 'int',
			'mime_type' => 'string-20', 'approved' => 'int',
		),
		array(
			(int) $attachmentOptions['id_folder'], (int) $attachmentOptions['post'], $attachmentOptions['name'], $attachmentOptions['file_hash'], $attachmentOptions['fileext'],
			(int) $attachmentOptions['size'], (empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']), (empty($attachmentOptions['height']) ? '0' : (int) $attachmentOptions['height']),
			(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''), (int) $attachmentOptions['approved'],
		),
		array('id_attach')
	);
	$attachmentOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');

	// @todo Add an error here maybe?
	if (empty($attachmentOptions['id']))
		return false;

	// Now that we have the attach id, let's rename this sucker and finish up.
	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $attachmentOptions['id_folder'], false, $attachmentOptions['file_hash']);
	rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

	// If it's not approved then add to the approval queue.
	if (!$attachmentOptions['approved'])
		$smcFunc['db_insert']('',
			'{db_prefix}approval_queue',
			array(
				'id_attach' => 'int', 'id_msg' => 'int',
			),
			array(
				$attachmentOptions['id'], (int) $attachmentOptions['post'],
			),
			array()
		);

	if (empty($modSettings['attachmentThumbnails']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
		return true;

	// Like thumbnails, do we?
	if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > $modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > $modSettings['attachmentThumbHeight']))
	{
		if (createThumbnail($attachmentOptions['destination'], $modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']))
		{
			// Figure out how big we actually made it.
			$size = @getimagesize($attachmentOptions['destination'] . '_thumb');
			list ($thumb_width, $thumb_height) = $size;

			if (!empty($size['mime']))
				$thumb_mime = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$thumb_mime = 'image/' . $validImageTypes[$size[2]];
			// Lord only knows how this happened...
			else
				$thumb_mime = '';

			$thumb_filename = $attachmentOptions['name'] . '_thumb';
			$thumb_size = filesize($attachmentOptions['destination'] . '_thumb');
			$thumb_file_hash = getAttachmentFilename($thumb_filename, false, null, true);
			$thumb_path = $attachmentOptions['destination'] . '_thumb';

			// We should check the file size and count here since thumbs are added to the existing totals.
			if (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] == 1 && !empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
			{
				$context['dir_size'] = isset($context['dir_size']) ? $context['dir_size'] += $thumb_size : $context['dir_size'] = 0;
				$context['dir_files'] = isset($context['dir_files']) ? $context['dir_files']++ : $context['dir_files'] = 0;

				// If the folder is full, try to create a new one and move the thumb to it.
				if ($context['dir_size'] > $modSettings['attachmentDirSizeLimit'] * 1024 || $context['dir_files'] + 2 > $modSettings['attachmentDirFileLimit'])
				{
					if (automanage_attachments_by_space())
					{
						rename($thumb_path, $context['attach_dir'] . '/' . $thumb_filename);
						$thumb_path = $context['attach_dir'] . '/' . $thumb_filename;
						$context['dir_size'] = 0;
						$context['dir_files'] = 0;
					}
				}
			}
			// If a new folder has been already created. Gotta move this thumb there then.
			if ($modSettings['currentAttachmentUploadDir'] != $attachmentOptions['id_folder'])
			{
				rename($thumb_path, $context['attach_dir'] . '/' . $thumb_filename);
				$thumb_path = $context['attach_dir'] . '/' . $thumb_filename;
			}

			// To the database we go!
			$smcFunc['db_insert']('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					$modSettings['currentAttachmentUploadDir'], (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash, $attachmentOptions['fileext'],
					$thumb_size, $thumb_width, $thumb_height, $thumb_mime, (int) $attachmentOptions['approved'],
				),
				array('id_attach')
			);
			$attachmentOptions['thumb'] = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');

			if (!empty($attachmentOptions['thumb']))
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					)
				);

				rename($thumb_path, getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], $modSettings['currentAttachmentUploadDir'], false, $thumb_file_hash));
			}
		}
	}
	return true;
}

?>