<?php

/**
 * This file handles the uploading and creation of attachments
 * as well as the auto management of the attachment directories.
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

use SMF\Config;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Check if the current directory is still valid or not.
 * If not creates the new directory
 *
 * @return void|bool False if any error occurred
 */
function automanage_attachments_check_directory()
{
	// Not pretty, but since we don't want folders created for every post. It'll do unless a better solution can be found.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'admin')
		$doit = true;
	elseif (empty(Config::$modSettings['automanage_attachments']))
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

	$rand = md5(mt_rand());
	$rand1 = $rand[1];
	$rand = $rand[0];

	if (!empty(Config::$modSettings['attachment_basedirectories']) && !empty(Config::$modSettings['use_subdirectories_for_attachments']))
	{
		if (!is_array(Config::$modSettings['attachment_basedirectories']))
			Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
		$base_dir = array_search(Config::$modSettings['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories']);
	}
	else
		$base_dir = 0;

	if (Config::$modSettings['automanage_attachments'] == 1)
	{
		if (!isset(Config::$modSettings['last_attachments_directory']))
			Config::$modSettings['last_attachments_directory'] = array();
		if (!is_array(Config::$modSettings['last_attachments_directory']))
			Config::$modSettings['last_attachments_directory'] = Utils::jsonDecode(Config::$modSettings['last_attachments_directory'], true);
		if (!isset(Config::$modSettings['last_attachments_directory'][$base_dir]))
			Config::$modSettings['last_attachments_directory'][$base_dir] = 0;
	}

	$basedirectory = (!empty(Config::$modSettings['use_subdirectories_for_attachments']) ? (Config::$modSettings['basedirectory_for_attachments']) : Config::$boarddir);
	//Just to be sure: I don't want directory separators at the end
	$sep = (DIRECTORY_SEPARATOR === '\\') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	switch (Config::$modSettings['automanage_attachments'])
	{
		case 1:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . (isset(Config::$modSettings['last_attachments_directory'][$base_dir]) ? Config::$modSettings['last_attachments_directory'][$base_dir] : 0);
			break;
		case 2:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
			break;
		case 3:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
			break;
		case 4:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty(Config::$modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand;
			break;
		case 5:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty(Config::$modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand . DIRECTORY_SEPARATOR . $rand1;
			break;
		default :
			$updir = '';
	}

	if (!is_array(Config::$modSettings['attachmentUploadDir']))
		Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
	if (!in_array($updir, Config::$modSettings['attachmentUploadDir']) && !empty($updir))
		$outputCreation = automanage_attachments_create_directory($updir);
	elseif (in_array($updir, Config::$modSettings['attachmentUploadDir']))
		$outputCreation = true;

	if ($outputCreation)
	{
		Config::$modSettings['currentAttachmentUploadDir'] = array_search($updir, Config::$modSettings['attachmentUploadDir']);
		Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

		Config::updateModSettings(array(
			'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
		));
	}

	return $outputCreation;
}

/**
 * Creates a directory
 *
 * @param string $updir The directory to be created
 *
 * @return bool False on errors
 */
function automanage_attachments_create_directory($updir)
{
	$tree = get_directory_tree_elements($updir);
	$count = count($tree);

	$directory = attachments_init_dir($tree, $count);
	if ($directory === false)
	{
		// Maybe it's just the folder name
		$tree = get_directory_tree_elements(Config::$boarddir . DIRECTORY_SEPARATOR . $updir);
		$count = count($tree);

		$directory = attachments_init_dir($tree, $count);
		if ($directory === false)
			return false;
	}

	$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

	while ($count != -1)
	{
		if (is_path_allowed($directory) && !@is_dir($directory))
		{
			if (!@mkdir($directory, 0755))
			{
				Utils::$context['dir_creation_error'] = 'attachments_no_create';
				return false;
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
		$count--;
	}

	// Check if the dir is writable.
	if (!smf_chmod($directory))
	{
		Utils::$context['dir_creation_error'] = 'attachments_no_write';
		return false;
	}

	// Everything seems fine...let's create the .htaccess
	if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess'))
		secureDirectory($updir, true);

	$sep = (DIRECTORY_SEPARATOR === '\\') ? '\/' : DIRECTORY_SEPARATOR;
	$updir = rtrim($updir, $sep);

	// Only update if it's a new directory
	if (!in_array($updir, Config::$modSettings['attachmentUploadDir']))
	{
		Config::$modSettings['currentAttachmentUploadDir'] = max(array_keys(Config::$modSettings['attachmentUploadDir'])) + 1;
		Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']] = $updir;

		Config::updateModSettings(array(
			'attachmentUploadDir' => Utils::jsonEncode(Config::$modSettings['attachmentUploadDir']),
			'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
		), true);
		Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
	}

	Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];
	return true;
}

/**
 * Check if open_basedir restrictions are in effect.
 * If so check if the path is allowed.
 *
 * @param string $path The path to check
 *
 * @return bool True if the path is allowed, false otherwise.
 */
function is_path_allowed($path)
{
	$open_basedir = ini_get('open_basedir');

	if (empty($open_basedir))
		return true;

	$restricted_paths = explode(PATH_SEPARATOR, $open_basedir);

	foreach ($restricted_paths as $restricted_path)
	{
		if (mb_strpos($path, $restricted_path) === 0)
			return true;
	}

	return false;
}

/**
 * Called when a directory space limit is reached.
 * Creates a new directory and increments the directory suffix number.
 *
 * @return void|bool False on errors, true if successful, nothing if auto-management of attachments is disabled
 */
function automanage_attachments_by_space()
{
	if (!isset(Config::$modSettings['automanage_attachments']) || (!empty(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] != 1))
		return;

	$basedirectory = !empty(Config::$modSettings['use_subdirectories_for_attachments']) ? Config::$modSettings['basedirectory_for_attachments'] : Config::$boarddir;
	// Just to be sure: I don't want directory separators at the end
	$sep = (DIRECTORY_SEPARATOR === '\\') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	// Get the current base directory
	if (!empty(Config::$modSettings['use_subdirectories_for_attachments']) && !empty(Config::$modSettings['attachment_basedirectories']))
	{
		$base_dir = array_search(Config::$modSettings['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories']);
		$base_dir = !empty(Config::$modSettings['automanage_attachments']) ? $base_dir : 0;
	}
	else
		$base_dir = 0;

	// Get the last attachment directory for that base directory
	if (empty(Config::$modSettings['last_attachments_directory'][$base_dir]))
		Config::$modSettings['last_attachments_directory'][$base_dir] = 0;
	// And increment it.
	Config::$modSettings['last_attachments_directory'][$base_dir]++;

	$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . Config::$modSettings['last_attachments_directory'][$base_dir];
	if (automanage_attachments_create_directory($updir))
	{
		Config::$modSettings['currentAttachmentUploadDir'] = array_search($updir, Config::$modSettings['attachmentUploadDir']);
		Config::updateModSettings(array(
			'last_attachments_directory' => Utils::jsonEncode(Config::$modSettings['last_attachments_directory']),
			'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
		));
		Config::$modSettings['last_attachments_directory'] = Utils::jsonDecode(Config::$modSettings['last_attachments_directory'], true);

		return true;
	}
	else
		return false;
}

/**
 * Split a path into a list of all directories and subdirectories
 *
 * @param string $directory A path
 *
 * @return array|bool An array of all the directories and subdirectories or false on failure
 */
function get_directory_tree_elements($directory)
{
	/*
		In Windows server both \ and / can be used as directory separators in paths
		In Linux (and presumably *nix) servers \ can be part of the name
		So for this reasons:
			* in Windows we need to explode for both \ and /
			* while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
	*/
	if (DIRECTORY_SEPARATOR === '\\')
		$tree = preg_split('#[\\\/]#', $directory);
	else
	{
		if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR)
			return false;

		$tree = explode(DIRECTORY_SEPARATOR, trim($directory, DIRECTORY_SEPARATOR));
	}
	return $tree;
}

/**
 * Return the first part of a path (i.e. c:\ or / + the first directory), used by automanage_attachments_create_directory
 *
 * @param array $tree An array
 * @param int $count The number of elements in $tree
 *
 * @return string|bool The first part of the path or false on error
 */
function attachments_init_dir(&$tree, &$count)
{
	$directory = '';
	// If on Windows servers the first part of the path is the drive (e.g. "C:")
	if (DIRECTORY_SEPARATOR === '\\')
	{
		//Better be sure that the first part of the path is actually a drive letter...
		//...even if, I should check this in the admin page...isn't it?
		//...NHAAA Let's leave space for users' complains! :P
		if (preg_match('/^[a-z]:$/i', $tree[0]))
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
	global $txt, $user_info;

	// Make sure we're uploading to the right place.
	if (!empty(Config::$modSettings['automanage_attachments']))
		automanage_attachments_check_directory();

	if (!is_array(Config::$modSettings['attachmentUploadDir']))
		Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);

	Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

	// Is the attachments folder actually there?
	if (!empty(Utils::$context['dir_creation_error']))
		$initial_error = Utils::$context['dir_creation_error'];
	elseif (!is_dir(Utils::$context['attach_dir']))
	{
		$initial_error = 'attach_folder_warning';
		log_error(sprintf($txt['attach_folder_admin_warning'], Utils::$context['attach_dir']), 'critical');
	}

	if (!isset($initial_error) && !isset(Utils::$context['attachments']))
	{
		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$request = Db::$db->query('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $_REQUEST['msg'],
					'attachment_type' => 0,
				)
			);
			list (Utils::$context['attachments']['quantity'], Utils::$context['attachments']['total_size']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		else
			Utils::$context['attachments'] = array(
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

			Utils::$context['we_are_history'] = $txt['error_temp_attachments_flushed'];
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
				$errors[] = array('file_too_big', array(Config::$modSettings['attachmentSizeLimit']));
			elseif ($_FILES['attachment']['error'][$n] == 6)
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_6'], 'critical');
			else
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);
			if (empty($errors))
				$errors[] = 'attach_php_error';
		}

		// Try to move and rename the file before doing any more checks on it.
		$attachID = 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand());
		$destName = Utils::$context['attach_dir'] . '/' . $attachID;
		if (empty($errors))
		{
			// The reported MIME type of the attachment might not be reliable.
			$detected_mime_type = get_mime_type($_FILES['attachment']['tmp_name'][$n], true);
			if ($detected_mime_type !== false)
				$_FILES['attachment']['type'][$n] = $detected_mime_type;

			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
				'tmp_name' => $destName,
				'size' => $_FILES['attachment']['size'][$n],
				'type' => $_FILES['attachment']['type'][$n],
				'id_folder' => Config::$modSettings['currentAttachmentUploadDir'],
				'errors' => array(),
			);

			// Move the file to the attachments folder with a temp name for now.
			if (@move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName))
				smf_chmod($destName, 0644);
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
				'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
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
	//   tmp_name => Path to the temp file (Utils::$context['attach_dir'] . '/' . $attachID).
	//   size => File size (required).
	//   type => MIME type (optional if not available on upload).
	//   id_folder => Config::$modSettings['currentAttachmentUploadDir']
	//   errors => An array of errors (use the index of the $txt variable for that error).
	// Template changes can be done using "integrate_upload_template".
	call_integration_hook('integrate_attachment_upload', array());
}

/**
 * Performs various checks on an uploaded file.
 * - Requires that $_SESSION['temp_attachments'][$attachID] be properly populated.
 *
 * @param int $attachID The ID of the attachment
 * @return bool Whether the attachment is OK
 */
function attachmentChecks($attachID)
{
	// No data or missing data .... Not necessarily needed, but in case a mod author missed something.
	if (empty($_SESSION['temp_attachments'][$attachID]))
		$error = '$_SESSION[\'temp_attachments\'][$attachID]';

	elseif (empty($attachID))
		$error = '$attachID';

	elseif (empty(Utils::$context['attachments']))
		$error = 'Utils::$context[\'attachments\']';

	elseif (empty(Utils::$context['attach_dir']))
		$error = 'Utils::$context[\'attach_dir\']';

	// Let's get their attention.
	if (!empty($error))
		fatal_lang_error('attach_check_nag', 'debug', array($error));

	// Just in case this slipped by the first checks, we stop it here and now
	if ($_SESSION['temp_attachments'][$attachID]['size'] == 0)
	{
		$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_0_byte_file';
		return false;
	}

	// First, the dreaded security check. Sorry folks, but this shouldn't be avoided.
	$size = @getimagesize($_SESSION['temp_attachments'][$attachID]['tmp_name']);
	if (is_array($size) && isset($size[2], Utils::$context['valid_image_types'][$size[2]]))
	{
		require_once(Config::$sourcedir . '/Subs-Graphics.php');
		if (!checkImageContents($_SESSION['temp_attachments'][$attachID]['tmp_name'], !empty(Config::$modSettings['attachment_image_paranoid'])))
		{
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty(Config::$modSettings['attachment_image_reencode']) || (!reencodeImage($_SESSION['temp_attachments'][$attachID]['tmp_name'], $size[2])))
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
				$_SESSION['temp_attachments'][$attachID]['type'] = 'image/' . Utils::$context['valid_image_types'][$size[2]];
		}
	}
	// SVGs have their own set of security checks.
	elseif ($_SESSION['temp_attachments'][$attachID]['type'] === 'image/svg+xml')
	{
		require_once(Config::$sourcedir . '/Subs-Graphics.php');
		if (!checkSVGContents($_SESSION['temp_attachments'][$attachID]['tmp_name']))
		{
			$_SESSION['temp_attachments'][$attachID]['errors'][] = 'bad_attachment';
			return false;
		}
	}

	// Is there room for this sucker?
	if (!empty(Config::$modSettings['attachmentDirSizeLimit']) || !empty(Config::$modSettings['attachmentDirFileLimit']))
	{
		// Check the folder size and count. If it hasn't been done already.
		if (empty(Utils::$context['dir_size']) || empty(Utils::$context['dir_files']))
		{
			$request = Db::$db->query('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder_id}
					AND attachment_type != {int:type}',
				array(
					'folder_id' => Config::$modSettings['currentAttachmentUploadDir'],
					'type' => 1,
				)
			);
			list (Utils::$context['dir_files'], Utils::$context['dir_size']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		Utils::$context['dir_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
		Utils::$context['dir_files']++;

		// Are we about to run out of room? Let's notify the admin then.
		if (empty(Config::$modSettings['attachment_full_notified']) && !empty(Config::$modSettings['attachmentDirSizeLimit']) && Config::$modSettings['attachmentDirSizeLimit'] > 4000 && Utils::$context['dir_size'] > (Config::$modSettings['attachmentDirSizeLimit'] - 2000) * 1024
			|| (!empty(Config::$modSettings['attachmentDirFileLimit']) && Config::$modSettings['attachmentDirFileLimit'] * .95 < Utils::$context['dir_files'] && Config::$modSettings['attachmentDirFileLimit'] > 500))
		{
			require_once(Config::$sourcedir . '/Subs-Admin.php');
			emailAdmins('admin_attachments_full');
			Config::updateModSettings(array('attachment_full_notified' => 1));
		}

		// // No room left.... What to do now???
		if (!empty(Config::$modSettings['attachmentDirFileLimit']) && Utils::$context['dir_files'] > Config::$modSettings['attachmentDirFileLimit']
			|| (!empty(Config::$modSettings['attachmentDirSizeLimit']) && Utils::$context['dir_size'] > Config::$modSettings['attachmentDirSizeLimit'] * 1024))
		{
			if (!empty(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] == 1)
			{
				// Move it to the new folder if we can.
				if (automanage_attachments_by_space())
				{
					rename($_SESSION['temp_attachments'][$attachID]['tmp_name'], Utils::$context['attach_dir'] . '/' . $attachID);
					$_SESSION['temp_attachments'][$attachID]['tmp_name'] = Utils::$context['attach_dir'] . '/' . $attachID;
					$_SESSION['temp_attachments'][$attachID]['id_folder'] = Config::$modSettings['currentAttachmentUploadDir'];
					Utils::$context['dir_size'] = 0;
					Utils::$context['dir_files'] = 0;
				}
				// Or, let the user know that it ain't gonna happen.
				else
				{
					if (isset(Utils::$context['dir_creation_error']))
						$_SESSION['temp_attachments'][$attachID]['errors'][] = Utils::$context['dir_creation_error'];
					else
						$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
				}
			}
			else
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
		}
	}

	// Is the file too big?
	Utils::$context['attachments']['total_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
	if (!empty(Config::$modSettings['attachmentSizeLimit']) && $_SESSION['temp_attachments'][$attachID]['size'] > Config::$modSettings['attachmentSizeLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('file_too_big', array(comma_format(Config::$modSettings['attachmentSizeLimit'], 0)));

	// Check the total upload size for this post...
	if (!empty(Config::$modSettings['attachmentPostLimit']) && Utils::$context['attachments']['total_size'] > Config::$modSettings['attachmentPostLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attach_max_total_file_size', array(comma_format(Config::$modSettings['attachmentPostLimit'], 0), comma_format(Config::$modSettings['attachmentPostLimit'] - ((Utils::$context['attachments']['total_size'] - $_SESSION['temp_attachments'][$attachID]['size']) / 1024), 0)));

	// Have we reached the maximum number of files we are allowed?
	Utils::$context['attachments']['quantity']++;

	// Set a max limit if none exists
	if (empty(Config::$modSettings['attachmentNumPerPostLimit']) && Utils::$context['attachments']['quantity'] >= 50)
		Config::$modSettings['attachmentNumPerPostLimit'] = 50;

	if (!empty(Config::$modSettings['attachmentNumPerPostLimit']) && Utils::$context['attachments']['quantity'] > Config::$modSettings['attachmentNumPerPostLimit'])
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attachments_limit_per_post', array(Config::$modSettings['attachmentNumPerPostLimit']));

	// File extension check
	if (!empty(Config::$modSettings['attachmentCheckExtensions']))
	{
		$allowed = explode(',', strtolower(Config::$modSettings['attachmentExtensions']));
		foreach ($allowed as $k => $dummy)
			$allowed[$k] = trim($dummy);

		if (!in_array(strtolower(substr(strrchr($_SESSION['temp_attachments'][$attachID]['name'], '.'), 1)), $allowed))
		{
			$allowed_extensions = strtr(strtolower(Config::$modSettings['attachmentExtensions']), array(',' => ', '));
			$_SESSION['temp_attachments'][$attachID]['errors'][] = array('cant_upload_type', array($allowed_extensions));
		}
	}

	// Undo the math if there's an error
	if (!empty($_SESSION['temp_attachments'][$attachID]['errors']))
	{
		if (isset(Utils::$context['dir_size']))
			Utils::$context['dir_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		if (isset(Utils::$context['dir_files']))
			Utils::$context['dir_files']--;
		Utils::$context['attachments']['total_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		Utils::$context['attachments']['quantity']--;
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
 * @param array $attachmentOptions An array of attachment options
 * @return bool Whether the attachment was created successfully
 */
function createAttachment(&$attachmentOptions)
{
	global $txt;

	require_once(Config::$sourcedir . '/Subs-Graphics.php');

	// If this is an image we need to set a few additional parameters.
	$size = @getimagesize($attachmentOptions['tmp_name']);
	list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;

	if (!empty($attachmentOptions['mime_type']) && $attachmentOptions['mime_type'] === 'image/svg+xml')
	{
		foreach (getSvgSize($attachmentOptions['tmp_name']) as $key => $value)
			$attachmentOptions[$key] = $value === INF ? 0 : $value;
	}

	if (function_exists('exif_read_data') && ($exif_data = @exif_read_data($attachmentOptions['tmp_name'])) !== false && !empty($exif_data['Orientation']))
		if (in_array($exif_data['Orientation'], [5, 6, 7, 8]))
		{
			$new_width = $attachmentOptions['height'];
			$new_height = $attachmentOptions['width'];
			$attachmentOptions['width'] = $new_width;
			$attachmentOptions['height'] = $new_height;
		}

	// If it's an image get the mime type right.
	if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
	{
		// Got a proper mime type?
		if (!empty($size['mime']))
			$attachmentOptions['mime_type'] = $size['mime'];

		// Otherwise a valid one?
		elseif (isset(Utils::$context['valid_image_types'][$size[2]]))
			$attachmentOptions['mime_type'] = 'image/' . Utils::$context['valid_image_types'][$size[2]];
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

	// This defines which options to use for which columns in the insert query.
	// Mods using the hook can add columns and even change the properties of existing columns,
	// but if they delete one of these columns, it will be reset to the default defined here.
	$attachmentStandardInserts = $attachmentInserts = array(
		// Format: 'column' => array('type', 'option')
		'id_folder' => array('int', 'id_folder'),
		'id_msg' => array('int', 'post'),
		'filename' => array('string-255', 'name'),
		'file_hash' => array('string-40', 'file_hash'),
		'fileext' => array('string-8', 'fileext'),
		'size' => array('int', 'size'),
		'width' => array('int', 'width'),
		'height' => array('int', 'height'),
		'mime_type' => array('string-20', 'mime_type'),
		'approved' => array('int', 'approved'),
	);

	// Last chance to change stuff!
	call_integration_hook('integrate_createAttachment', array(&$attachmentOptions, &$attachmentInserts));

	// Make sure the folder is valid...
	$tmp = is_array(Config::$modSettings['attachmentUploadDir']) ? Config::$modSettings['attachmentUploadDir'] : Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
	$folders = array_keys($tmp);
	if (empty($attachmentOptions['id_folder']) || !in_array($attachmentOptions['id_folder'], $folders))
		$attachmentOptions['id_folder'] = Config::$modSettings['currentAttachmentUploadDir'];

	// Make sure all required columns are present, in case a mod screwed up.
	foreach ($attachmentStandardInserts as $column => $insert_info)
		if (!isset($attachmentInserts[$column]))
			$attachmentInserts[$column] = $insert_info;

	// Set up the columns and values to insert, in the correct order.
	$attachmentColumns = array();
	$attachmentValues = array();
	foreach ($attachmentInserts as $column => $insert_info)
	{
		$attachmentColumns[$column] = $insert_info[0];

		if (!empty($insert_info[0]) && $insert_info[0] == 'int')
			$attachmentValues[] = (int) $attachmentOptions[$insert_info[1]];
		else
			$attachmentValues[] = $attachmentOptions[$insert_info[1]];
	}

	// Create the attachment in the database.
	$attachmentOptions['id'] = Db::$db->insert('',
		'{db_prefix}attachments',
		$attachmentColumns,
		$attachmentValues,
		array('id_attach'),
		1
	);

	// Attachment couldn't be created.
	if (empty($attachmentOptions['id']))
	{
		loadLanguage('Errors');
		log_error($txt['attachment_not_created'], 'general');
		return false;
	}

	// Now that we have the attach id, let's rename this sucker and finish up.
	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $attachmentOptions['id_folder'], false, $attachmentOptions['file_hash']);
	rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

	// If it's not approved then add to the approval queue.
	if (!$attachmentOptions['approved'])
	{
		Db::$db->insert('',
			'{db_prefix}approval_queue',
			array(
				'id_attach' => 'int', 'id_msg' => 'int',
			),
			array(
				$attachmentOptions['id'], (int) $attachmentOptions['post'],
			),
			array()
		);

		// Queue background notification task.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			array(
				'task_file' => 'string',
				'task_class' => 'string',
				'task_data' => 'string',
				'claimed_time' => 'int'
			),
			array(
					'$sourcedir/tasks/CreateAttachment_Notify.php',
					'SMF\Tasks\CreateAttachment_Notify',
					Utils::jsonEncode(
						array(
							'id' => $attachmentOptions['id'],
						)
					),
				0
			),
			array(
				'id_task'
			)
		);
	}

	if (empty(Config::$modSettings['attachmentThumbnails']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
		return true;

	// Like thumbnails, do we?
	if (!empty(Config::$modSettings['attachmentThumbWidth']) && !empty(Config::$modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > Config::$modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > Config::$modSettings['attachmentThumbHeight']))
	{
		if (createThumbnail($attachmentOptions['destination'], Config::$modSettings['attachmentThumbWidth'], Config::$modSettings['attachmentThumbHeight']))
		{
			// Figure out how big we actually made it.
			$size = @getimagesize($attachmentOptions['destination'] . '_thumb');
			list ($thumb_width, $thumb_height) = $size;

			if (!empty($size['mime']))
				$thumb_mime = $size['mime'];
			elseif (isset(Utils::$context['valid_image_types'][$size[2]]))
				$thumb_mime = 'image/' . Utils::$context['valid_image_types'][$size[2]];
			// Lord only knows how this happened...
			else
				$thumb_mime = '';

			$thumb_filename = $attachmentOptions['name'] . '_thumb';
			$thumb_size = filesize($attachmentOptions['destination'] . '_thumb');
			$thumb_file_hash = getAttachmentFilename($thumb_filename, false, null, true);
			$thumb_path = $attachmentOptions['destination'] . '_thumb';

			// We should check the file size and count here since thumbs are added to the existing totals.
			if (!empty(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] == 1 && !empty(Config::$modSettings['attachmentDirSizeLimit']) || !empty(Config::$modSettings['attachmentDirFileLimit']))
			{
				Utils::$context['dir_size'] = isset(Utils::$context['dir_size']) ? Utils::$context['dir_size'] += $thumb_size : Utils::$context['dir_size'] = 0;
				Utils::$context['dir_files'] = isset(Utils::$context['dir_files']) ? Utils::$context['dir_files']++ : Utils::$context['dir_files'] = 0;

				// If the folder is full, try to create a new one and move the thumb to it.
				if (Utils::$context['dir_size'] > Config::$modSettings['attachmentDirSizeLimit'] * 1024 || Utils::$context['dir_files'] + 2 > Config::$modSettings['attachmentDirFileLimit'])
				{
					if (automanage_attachments_by_space())
					{
						rename($thumb_path, Utils::$context['attach_dir'] . '/' . $thumb_filename);
						$thumb_path = Utils::$context['attach_dir'] . '/' . $thumb_filename;
						Utils::$context['dir_size'] = 0;
						Utils::$context['dir_files'] = 0;
					}
				}
			}
			// If a new folder has been already created. Gotta move this thumb there then.
			if (Config::$modSettings['currentAttachmentUploadDir'] != $attachmentOptions['id_folder'])
			{
				rename($thumb_path, Utils::$context['attach_dir'] . '/' . $thumb_filename);
				$thumb_path = Utils::$context['attach_dir'] . '/' . $thumb_filename;
			}

			// To the database we go!
			$attachmentOptions['thumb'] = Db::$db->insert('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					Config::$modSettings['currentAttachmentUploadDir'], (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash, $attachmentOptions['fileext'],
					$thumb_size, $thumb_width, $thumb_height, $thumb_mime, (int) $attachmentOptions['approved'],
				),
				array('id_attach'),
				1
			);

			if (!empty($attachmentOptions['thumb']))
			{
				Db::$db->query('', '
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					)
				);

				rename($thumb_path, getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], Config::$modSettings['currentAttachmentUploadDir'], false, $thumb_file_hash));
			}
		}
	}

	return true;
}

/**
 * Assigns the given attachments to the given message ID.
 *
 * @param $attachIDs array of attachment IDs to assign.
 * @param $msgID integer the message ID.
 *
 * @return boolean false on error or missing params.
 */
function assignAttachments($attachIDs = array(), $msgID = 0)
{
	// Oh, come on!
	if (empty($attachIDs) || empty($msgID))
		return false;

	// "I see what is right and approve, but I do what is wrong."
	call_integration_hook('integrate_assign_attachments', array(&$attachIDs, &$msgID));

	// One last check
	if (empty($attachIDs))
		return false;

	// Perform.
	Db::$db->query('', '
		UPDATE {db_prefix}attachments
		SET id_msg = {int:id_msg}
		WHERE id_attach IN ({array_int:attach_ids})',
		array(
			'id_msg' => $msgID,
			'attach_ids' => $attachIDs,
		)
	);

	return true;
}

/**
 * Gets an attach ID and tries to load all its info.
 *
 * @param int $attachID the attachment ID to load info from.
 *
 * @return mixed If successful, it will return an array of loaded data. String, most likely a $txt key if there was some error.
 */
function parseAttachBBC($attachID = 0)
{
	global $board, $user_info;
	static $view_attachment_boards;

	if (!isset($view_attachment_boards))
		$view_attachment_boards = boardsAllowedTo('view_attachments');

	// Meh...
	if (empty($attachID))
		return 'attachments_no_data_loaded';

	// Make it easy.
	$msgID = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

	// Perhaps someone else wants to do the honors? Yes, this also includes dealing with previews ;)
	$externalParse = call_integration_hook('integrate_pre_parseAttachBBC', array($attachID, $msgID));

	// "I am innocent of the blood of this just person: see ye to it."
	if (!empty($externalParse) && (is_string($externalParse) || is_array($externalParse)))
		return $externalParse;

	// Are attachments enabled?
	if (empty(Config::$modSettings['attachmentEnable']))
		return 'attachments_not_enable';

	$check_board_perms = !isset($_SESSION['attachments_can_preview'][$attachID]) && $view_attachment_boards !== array(0);

	// There is always the chance someone else has already done our dirty work...
	// If so, all pertinent checks were already done. Hopefully...
	if (!empty(Utils::$context['current_attachments']) && !empty(Utils::$context['current_attachments'][$attachID]))
		return Utils::$context['current_attachments'][$attachID];

	// Can the user view attachments on this board?
	if ($check_board_perms && !empty($board) && !in_array($board, $view_attachment_boards))
		return 'attachments_not_allowed_to_see';

	// Get the message info associated with this particular attach ID.
	$attachInfo = getAttachMsgInfo($attachID);

	// There is always the chance this attachment no longer exists or isn't associated to a message anymore...
	if (empty($attachInfo))
		return 'attachments_no_data_loaded';

	if (empty($attachInfo['msg']) && empty(Utils::$context['preview_message']))
		return 'attachments_no_msg_associated';

	// Can the user view attachments on the board that holds the attachment's original post?
	// (This matters when one post quotes another on a different board.)
	if ($check_board_perms && !in_array($attachInfo['board'], $view_attachment_boards))
		return 'attachments_not_allowed_to_see';

	if (empty(Utils::$context['loaded_attachments'][$attachInfo['msg']]))
		prepareAttachsByMsg(array($attachInfo['msg']));

	if (isset(Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]))
		$attachContext = Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID];

	// In case the user manually typed the thumbnail's ID into the BBC
	elseif (!empty(Utils::$context['loaded_attachments'][$attachInfo['msg']]))
	{
		foreach (Utils::$context['loaded_attachments'][$attachInfo['msg']] as $foundAttachID => $foundAttach)
		{
			if (array_key_exists('id_thumb', $foundAttach) && $foundAttach['id_thumb'] == $attachID)
			{
				$attachContext = Utils::$context['loaded_attachments'][$attachInfo['msg']][$foundAttachID];
				$attachID = $foundAttachID;
				break;
			}
		}
	}

	// Load this particular attach's context.
	if (!empty($attachContext))
	{
		// Skip unapproved attachment, unless they belong to the user or the user can approve them.
		if (!Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]['approved'] &&
			Config::$modSettings['postmod_active'] && !allowedTo('approve_posts') &&
			Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]['id_member'] != $user_info['id'])
		{
			unset(Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]);
			return 'attachments_unapproved';
		}
		$attachLoaded = loadAttachmentContext($attachContext['id_msg'], Utils::$context['loaded_attachments']);
	}
	else
		return 'attachments_no_data_loaded';

	if (empty($attachLoaded))
		return 'attachments_no_data_loaded';

	else
		$attachContext = $attachLoaded[$attachID];

	// It's theoretically possible that prepareAttachsByMsg() changed the board id, so check again.
	if ($check_board_perms && !in_array($attachContext['board'], $view_attachment_boards))
		return 'attachments_not_allowed_to_see';

	// You may or may not want to show this under the post.
	if (!empty(Config::$modSettings['dont_show_attach_under_post']) && !isset(Utils::$context['show_attach_under_post'][$attachID]))
		Utils::$context['show_attach_under_post'][$attachID] = $attachID;

	// Last minute changes?
	call_integration_hook('integrate_post_parseAttachBBC', array(&$attachContext));

	// Don't do any logic with the loaded data, leave it to whoever called this function.
	return $attachContext;
}

/**
 * Gets raw info directly from the attachments table.
 *
 * @param array $attachIDs An array of attachments IDs.
 *
 * @return array
 */
function getRawAttachInfo($attachIDs)
{
	if (empty($attachIDs))
		return array();

	$return = array();

	$request = Db::$db->query('', '
		SELECT a.id_attach, a.id_msg, a.id_member, a.size, a.mime_type, a.id_folder, a.filename' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : ',
			COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
		FROM {db_prefix}attachments AS a' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
		WHERE a.id_attach IN ({array_int:attach_ids})
		LIMIT 1',
		array(
			'attach_ids' => (array) $attachIDs,
		)
	);

	if (Db::$db->num_rows($request) != 1)
		return array();

	while ($row = Db::$db->fetch_assoc($request))
		$return[$row['id_attach']] = array(
			'name' => Utils::htmlspecialchars($row['filename']),
			'size' => $row['size'],
			'attachID' => $row['id_attach'],
			'unchecked' => false,
			'approved' => 1,
			'mime_type' => $row['mime_type'],
			'thumb' => $row['id_thumb'],
		);
	Db::$db->free_result($request);

	return $return;
}

/**
 * Gets all needed message data associated with an attach ID
 *
 * @param int $attachID the attachment ID to load info from.
 *
 * @return array
 */
function getAttachMsgInfo($attachID)
{
	if (empty($attachID))
		return array();

	if (!isset(Utils::$context['loaded_attachments']))
		Utils::$context['loaded_attachments'] = array();

	foreach (Utils::$context['loaded_attachments'] as $msgRows)
	{
		if (empty($msgRows[$attachID]))
			continue;

		$row = array(
			'msg' => $msgRows[$attachID]['id_msg'],
			'topic' => $msgRows[$attachID]['topic'],
			'board' => $msgRows[$attachID]['board'],
		);

		return $row;
	}

	$request = Db::$db->query('', '
		SELECT a.id_msg AS msg, m.id_topic AS topic, m.id_board AS board
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
		WHERE id_attach = {int:id_attach}
		LIMIT 1',
		array(
			'id_attach' => (int) $attachID,
		)
	);

	if (Db::$db->num_rows($request) != 1)
		return array();

	$row = Db::$db->fetch_assoc($request);
	Db::$db->free_result($request);

	return $row;
}

/**
 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
 * It requires the view_attachments permission to calculate image size.
 * It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
 * the max_image_width and max_image_height settings.
 *
 * @param int $id_msg ID of the post to load attachments for
 * @param array $attachments  An array of already loaded attachments. This function no longer depends on having $topic declared, thus, you need to load the actual topic ID for each attachment.
 * @return array An array of attachment info
 */
function loadAttachmentContext($id_msg, $attachments)
{
	global $txt;

	if (empty($attachments) || empty($attachments[$id_msg]))
		return array();

	// Set up the attachment info - based on code by Meriadoc.
	$attachmentData = array();
	$have_unapproved = false;
	if (isset($attachments[$id_msg]) && !empty(Config::$modSettings['attachmentEnable']))
	{
		foreach ($attachments[$id_msg] as $i => $attachment)
		{
			$attachmentData[$i] = array(
				'id' => $attachment['id_attach'],
				'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Utils::htmlspecialchars(un_htmlspecialchars($attachment['filename']))),
				'downloads' => $attachment['downloads'],
				'size' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
				'byte_size' => $attachment['filesize'],
				'href' => Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_attach'],
				'link' => '<a href="' . Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_attach'] . '" class="bbc_link">' . Utils::htmlspecialchars(un_htmlspecialchars($attachment['filename'])) . '</a>',
				'is_image' => !empty($attachment['width']) && !empty($attachment['height']),
				'is_approved' => $attachment['approved'],
				'topic' => $attachment['topic'],
				'board' => $attachment['board'],
				'mime_type' => $attachment['mime_type'],
			);

			// If something is unapproved we'll note it so we can sort them.
			if (!$attachment['approved'])
				$have_unapproved = true;

			if (!$attachmentData[$i]['is_image'])
				continue;

			$attachmentData[$i]['real_width'] = $attachment['width'];
			$attachmentData[$i]['width'] = $attachment['width'];
			$attachmentData[$i]['real_height'] = $attachment['height'];
			$attachmentData[$i]['height'] = $attachment['height'];

			// Let's see, do we want thumbs?
			if (!empty(Config::$modSettings['attachmentShowImages']) && !empty(Config::$modSettings['attachmentThumbnails']) && !empty(Config::$modSettings['attachmentThumbWidth']) && !empty(Config::$modSettings['attachmentThumbHeight']) && ($attachment['width'] > Config::$modSettings['attachmentThumbWidth'] || $attachment['height'] > Config::$modSettings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
			{
				// A proper thumb doesn't exist yet? Create one!
				if (empty($attachment['id_thumb']) || $attachment['thumb_width'] > Config::$modSettings['attachmentThumbWidth'] || $attachment['thumb_height'] > Config::$modSettings['attachmentThumbHeight'] || ($attachment['thumb_width'] < Config::$modSettings['attachmentThumbWidth'] && $attachment['thumb_height'] < Config::$modSettings['attachmentThumbHeight']))
				{
					$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder']);

					require_once(Config::$sourcedir . '/Subs-Graphics.php');
					if (createThumbnail($filename, Config::$modSettings['attachmentThumbWidth'], Config::$modSettings['attachmentThumbHeight']))
					{
						// So what folder are we putting this image in?
						if (!empty(Config::$modSettings['currentAttachmentUploadDir']))
						{
							if (!is_array(Config::$modSettings['attachmentUploadDir']))
								Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
							$id_folder_thumb = Config::$modSettings['currentAttachmentUploadDir'];
						}
						else
						{
							$id_folder_thumb = 1;
						}

						// Calculate the size of the created thumbnail.
						$size = @getimagesize($filename . '_thumb');
						list ($attachment['thumb_width'], $attachment['thumb_height']) = $size;
						$thumb_size = filesize($filename . '_thumb');

						// What about the extension?
						$thumb_ext = isset(Utils::$context['valid_image_types'][$size[2]]) ? Utils::$context['valid_image_types'][$size[2]] : '';

						// Figure out the mime type.
						if (!empty($size['mime']))
							$thumb_mime = $size['mime'];
						else
							$thumb_mime = 'image/' . $thumb_ext;

						$thumb_filename = $attachment['filename'] . '_thumb';
						$thumb_hash = getAttachmentFilename($thumb_filename, false, null, true);
						$old_id_thumb = $attachment['id_thumb'];

						// Add this beauty to the database.
						$attachment['id_thumb'] = Db::$db->insert('',
							'{db_prefix}attachments',
							array('id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'fileext' => 'string', 'mime_type' => 'string'),
							array($id_folder_thumb, $id_msg, 3, $thumb_filename, $thumb_hash, (int) $thumb_size, (int) $attachment['thumb_width'], (int) $attachment['thumb_height'], $thumb_ext, $thumb_mime),
							array('id_attach'),
							1
						);

						if (!empty($attachment['id_thumb']))
						{
							Db::$db->query('', '
								UPDATE {db_prefix}attachments
								SET id_thumb = {int:id_thumb}
								WHERE id_attach = {int:id_attach}',
								array(
									'id_thumb' => $attachment['id_thumb'],
									'id_attach' => $attachment['id_attach'],
								)
							);

							$thumb_realname = getAttachmentFilename($thumb_filename, $attachment['id_thumb'], $id_folder_thumb, false, $thumb_hash);
							rename($filename . '_thumb', $thumb_realname);

							// Do we need to remove an old thumbnail?
							if (!empty($old_id_thumb))
							{
								require_once(Config::$sourcedir . '/ManageAttachments.php');
								removeAttachments(array('id_attach' => $old_id_thumb), '', false, false);
							}
						}
					}
				}

				// Only adjust dimensions on successful thumbnail creation.
				if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height']))
				{
					$attachmentData[$i]['width'] = $attachment['thumb_width'];
					$attachmentData[$i]['height'] = $attachment['thumb_height'];
				}
			}

			if (!empty($attachment['id_thumb']))
				$attachmentData[$i]['thumbnail'] = array(
					'id' => $attachment['id_thumb'],
					'href' => Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_thumb'] . ';image;thumb',
				);
			$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

			// If thumbnails are disabled, check the maximum size of the image.
			if (!$attachmentData[$i]['thumbnail']['has_thumb'] && ((!empty(Config::$modSettings['max_image_width']) && $attachment['width'] > Config::$modSettings['max_image_width']) || (!empty(Config::$modSettings['max_image_height']) && $attachment['height'] > Config::$modSettings['max_image_height'])))
			{
				if (!empty(Config::$modSettings['max_image_width']) && (empty(Config::$modSettings['max_image_height']) || $attachment['height'] * Config::$modSettings['max_image_width'] / $attachment['width'] <= Config::$modSettings['max_image_height']))
				{
					$attachmentData[$i]['width'] = Config::$modSettings['max_image_width'];
					$attachmentData[$i]['height'] = floor($attachment['height'] * Config::$modSettings['max_image_width'] / $attachment['width']);
				}
				elseif (!empty(Config::$modSettings['max_image_width']))
				{
					$attachmentData[$i]['width'] = floor($attachment['width'] * Config::$modSettings['max_image_height'] / $attachment['height']);
					$attachmentData[$i]['height'] = Config::$modSettings['max_image_height'];
				}
			}
			elseif ($attachmentData[$i]['thumbnail']['has_thumb'])
			{
				// If the image is too large to show inline, make it a popup.
				if (((!empty(Config::$modSettings['max_image_width']) && $attachmentData[$i]['real_width'] > Config::$modSettings['max_image_width']) || (!empty(Config::$modSettings['max_image_height']) && $attachmentData[$i]['real_height'] > Config::$modSettings['max_image_height'])))
					$attachmentData[$i]['thumbnail']['javascript'] = 'return reqWin(\'' . $attachmentData[$i]['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
				else
					$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
			}

			if (!$attachmentData[$i]['thumbnail']['has_thumb'])
				$attachmentData[$i]['downloads']++;

			// Describe undefined dimensions as "unknown".
			// This can happen if an uploaded SVG is missing some key data.
			foreach (array('real_width', 'real_height') as $key)
			{
				if (!isset($attachmentData[$i][$key]) || $attachmentData[$i][$key] === INF)
				{
					loadLanguage('Admin');
					$attachmentData[$i][$key] = ' (' . $txt['unknown'] . ') ';
				}
			}
		}
	}

	// Do we need to instigate a sort?
	if ($have_unapproved)
		uasort(
			$attachmentData,
			function($a, $b)
			{
				if ($a['is_approved'] == $b['is_approved'])
					return 0;

				return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
			}
		);

	return $attachmentData;
}

/**
 * prepare the Attachment api for all messages
 *
 * @param int array $msgIDs the message ID to load info from.
 *
 * @return void
 */
function prepareAttachsByMsg($msgIDs)
{
	if (empty(Utils::$context['loaded_attachments']))
		Utils::$context['loaded_attachments'] = array();
	// Remove all $msgIDs that we already processed
	else
		$msgIDs = array_diff($msgIDs, array_keys(Utils::$context['loaded_attachments']), array(0));

	// Ensure that $msgIDs doesn't contain zero or non-integers.
	$msgIDs = array_filter(array_map('intval', $msgIDs));

	if (!empty($msgIDs) || !empty($_SESSION['attachments_can_preview']))
	{
		// Where clause - there may or may not be msg ids, & may or may not be attachs to preview,
		// depending on post vs edit, inserted or not, preview or not, post error or not, etc.  
		// Either way, they may be needed in a display of a list of posts or in the dropzone 'mock' list of thumbnails.
		$msg_or_att = '';
		if (!empty($msgIDs))
			$msg_or_att .= 'a.id_msg IN ({array_int:message_id}) ';
		if (!empty($msgIDs) && !empty($_SESSION['attachments_can_preview']))
			$msg_or_att .= 'OR ';
		if (!empty($_SESSION['attachments_can_preview']))
			$msg_or_att .= 'a.id_attach IN ({array_int:preview_attachments})';

		// This tries to get all attachments for the page being displayed, to build a cache of attach info.
		// This is also being used by POST, so it may need to grab attachs known only in the session.
		$request = Db::$db->query('', '
			SELECT
				a.id_attach, a.id_folder, a.id_msg, a.filename, a.file_hash, COALESCE(a.size, 0) AS filesize, a.downloads, a.approved, m.id_topic AS topic, m.id_board AS board, m.id_member, a.mime_type,
				a.width, a.height' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : ',
				COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
			FROM {db_prefix}attachments AS a' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			WHERE a.attachment_type = {int:attachment_type}
				AND (' . $msg_or_att . ')',
			array(
				'message_id' => $msgIDs,
				'attachment_type' => 0,
				'preview_attachments' => !empty($_SESSION['attachments_can_preview']) ? array_keys(array_filter($_SESSION['attachments_can_preview'])) : array(0),
			)
		);
		$rows = Db::$db->fetch_all($request);
		Db::$db->free_result($request);

		foreach ($rows as $row)
		{
			// SVGs are special.
			if ($row['mime_type'] === 'image/svg+xml')
			{
				if (empty($row['width']) || empty($row['height']))
				{
					require_once(Config::$sourcedir . '/Subs-Graphics.php');

					$row = array_merge($row, getSvgSize(getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'])));
				}

				// SVG is its own thumbnail.
				if (isset($row['id_thumb']))
				{
					$row['id_thumb'] = $row['id_attach'];

					// For SVGs, we don't need to calculate thumbnail size precisely.
					$row['thumb_width'] = min($row['width'], !empty(Config::$modSettings['attachmentThumbWidth']) ? Config::$modSettings['attachmentThumbWidth'] : 1000);
					$row['thumb_height'] = min($row['height'], !empty(Config::$modSettings['attachmentThumbHeight']) ? Config::$modSettings['attachmentThumbHeight'] : 1000);

					// Must set the thumbnail's CSS dimensions manually.
					addInlineCss('img#thumb_' . $row['id_thumb'] . ':not(.original_size) {width: ' . $row['thumb_width'] . 'px; height: ' . $row['thumb_height'] . 'px;}');
				}
			}

			if (empty(Utils::$context['loaded_attachments'][$row['id_msg']]))
				Utils::$context['loaded_attachments'][$row['id_msg']] = array();

			Utils::$context['loaded_attachments'][$row['id_msg']][$row['id_attach']] = $row;

			// This is better than sorting it with the query...
			ksort(Utils::$context['loaded_attachments'][$row['id_msg']]);
		}
	}
}

?>