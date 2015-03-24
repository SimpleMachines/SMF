<?php

/**
 * This file contains handling attachments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

if (!defined('SMF'))
	die('No direct access...');

class Attachments
{
	protected $_msg = 0;
	protected $_attachmentUploadDir = false;
	protected $_attchDir = '';
	protected $_currentAttachmentUploadDir;
	protected $_canPostAttachment;
	protected $_subActions = array(
		'add',
		'delete',
	);
	protected $_sa = false;

	public function __construct()
	{
		global $modSettings, $context;

		$this->_msg = (int) !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0;

		$this->_currentAttachmentUploadDir = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['currentAttachmentUploadDir'] : '';

		if (!is_array($modSettings['attachmentUploadDir']))
			$this->_attachmentUploadDir = unserialize($modSettings['attachmentUploadDir']);

		$this->_attchDir = $context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

		$context['can_post_attachment'] = $this->_canPostAttachment = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
	}

	public function call()
	{
		global $smcFunc, $sourcedir;

		require_once($sourcedir . '/Subs-Attachments.php');

		$this->_sa = !empty($_REQUEST['sa']) ? $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($_REQUEST['sa'])) : false;

		if ($this->_sa && in_array($this->_sa, $this->_subActions))
			$this->{$this->_sa}();
	}

	public function add()
	{
		// You gotta be able to post attachments.
		if (!$this->_canPostAttachment)
			return $this->_response = 'some error indicating you cannot upload attachments';

		// Process them at once!
		$this->processAttachments();
	}

	protected function createAtttach()
	{
		global $context, $txt;
		$attachIDs = array();
		$attach_errors = array();
		if (!empty($context['we_are_history']))
			$attach_errors[] = '<dd>' . $txt['error_temp_attachments_flushed'] . '<br><br></dd>';

		foreach ($_SESSION['temp_attachments'] as  $attachID => $attachment)
		{
			if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
				continue;

			// If there was an initial error just show that message.
			if ($attachID == 'initial_error')
			{
				$attach_errors[] = '<dt>' . $txt['attach_no_upload'] . '</dt>';
				$attach_errors[] = '<dd>' . (is_array($attachment) ? vsprintf($txt[$attachment[0]], $attachment[1]) : $txt[$attachment]) . '</dd>';

				unset($_SESSION['temp_attachments']);
				break;
			}

			$attachmentOptions = array(
				'post' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
				'poster' => $user_info['id'],
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => isset($attachment['size']) ? $attachment['size'] : 0,
				'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
				'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : $modSettings['currentAttachmentUploadDir'],
				'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
				'errors' => $attachment['errors'],
			);

			if (empty($attachment['errors']))
			{
				if (createAttachment($attachmentOptions))
				{
					$attachIDs[] = $attachmentOptions['id'];
					if (!empty($attachmentOptions['thumb']))
						$attachIDs[] = $attachmentOptions['thumb'];
				}
			}
			else
				$attach_errors[] = '<dt>&nbsp;</dt>';

			if (!empty($attachmentOptions['errors']))
			{
				// Sort out the errors for display and delete any associated files.
				$attach_errors[] = '<dt>' . vsprintf($txt['attach_warning'], $attachment['name']) . '</dt>';
				$log_these = array('attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file');
				foreach ($attachmentOptions['errors'] as $error)
				{
					if (!is_array($error))
					{
						$attach_errors[] = '<dd>' . $txt[$error] . '</dd>';
						if (in_array($error, $log_these))
							log_error($attachment['name'] . ': ' . $txt[$error], 'critical');
					}
					else
						$attach_errors[] = '<dd>' . vsprintf($txt[$error[0]], $error[1]) . '</dd>';
				}
				if (file_exists($attachment['tmp_name']))
					unlink($attachment['tmp_name']);
			}
		}
		unset($_SESSION['temp_attachments']);
	}

	/**
	 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
	 */
	protected function processAttachments()
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

	protected function setResponse()
	{
		global $modSettings;

		ob_end_clean();

		if (!empty($modSettings['CompressedOutput']))
			@ob_start('ob_gzhandler');

		else
			ob_start();

		// Set the header.
		header('Content-Type: application/json');

		echo json_encode($this->_response);

		// Done.
		obExit(false);
		die;
	}
}

?>
