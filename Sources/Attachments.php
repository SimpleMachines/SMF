<?php

/**
 * This file contains handling attachments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

class Attachments
{
	protected $_msg = 0;
	protected $_board = null;
	protected $_attachmentUploadDir = false;
	protected $_attchDir = '';
	protected $_currentAttachmentUploadDir;
	protected $_canPostAttachment;
	protected $_generalErrors = array();
	protected $_initialError;
	protected $_attachments = array();
	protected $_attachResults = array();
	protected $_attachSuccess = array();
	protected $_response = array(
		'error' => true,
		'data' => array(),
		'extra' => '',
	);
	protected $_subActions = array(
		'add',
		'delete',
	);
	protected $_sa = false;

	public function __construct()
	{
		global $modSettings, $context;

		$this->_msg = (int) !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0;
		$this->_board = (int) !empty($_REQUEST['board']) ? $_REQUEST['board'] : null;

		$this->_currentAttachmentUploadDir = $modSettings['currentAttachmentUploadDir'];

		$this->_attachmentUploadDir = $modSettings['attachmentUploadDir'];

		$this->_attchDir = $context['attach_dir'] = $this->_attachmentUploadDir[$modSettings['currentAttachmentUploadDir']];

		$this->_canPostAttachment = $context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment', $this->_board) || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments', $this->_board)));
	}

	public function call()
	{
		global $smcFunc, $sourcedir;

		require_once($sourcedir . '/Subs-Attachments.php');

		// Guest aren't welcome, sorry.
		is_not_guest();

		// Need this. For reasons...
		loadLanguage('Post');

		$this->_sa = !empty($_REQUEST['sa']) ? $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($_REQUEST['sa'])) : false;

		if ($this->_canPostAttachment && $this->_sa && in_array($this->_sa, $this->_subActions))
			$this->{$this->_sa}();

		// Just send a generic message.
		else
			$this->setResponse(array(
				'text' => $this->_sa == 'add' ? 'attach_error_title' : 'attached_file_deleted_error',
				'type' => 'error',
				'data' => false,
			));

		// Back to the future, oh, to the browser!
		$this->sendResponse();
	}

	public function delete()
	{
		global $sourcedir;

		// Need this, don't ask why just nod your head.
		require_once($sourcedir . '/ManageAttachments.php');

		$attachID = !empty($_REQUEST['attach']) && is_numeric($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;

		// Need something to work with.
		if (!$attachID || (!empty($_SESSION['already_attached']) && !isset($_SESSION['already_attached'][$attachID])))
			return $this->setResponse(array(
				'text' => 'attached_file_deleted_error',
				'type' => 'error',
				'data' => false,
			));

		// Lets pass some params and see what happens :P
		$affectedMessage = removeAttachments(array('id_attach' => $attachID), '', true, true);

		// Gotta also remove the attachment from the session var.
		unset($_SESSION['already_attached'][$attachID]);

		// $affectedMessage returns an empty array array(0) which php treats as non empty... awesome...
		$this->setResponse(array(
			'text' => !empty($affectedMessage) ? 'attached_file_deleted' : 'attached_file_deleted_error',
			'type' => !empty($affectedMessage) ? 'info' : 'warning',
			'data' => $affectedMessage,
		));
	}

	public function add()
	{
		// You gotta be able to post attachments.
		if (!$this->_canPostAttachment)
			return $this->setResponse(array(
				'text' => 'attached_file_cannot',
				'type' => 'error',
				'data' => false,
			));

		// Process them at once!
		$this->processAttachments();

		// The attachments was created and moved the the right folder, time to update the DB.
		if (!empty($_SESSION['temp_attachments']))
			$this->createAttach();

		// Set the response.
		$this->setResponse();
	}

	/**
	 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
	 */
	protected function processAttachments()
	{
		global $context, $modSettings, $smcFunc, $user_info, $txt;

		if (!isset($_FILES['attachment']['name']))
			$_FILES['attachment']['tmp_name'] = array();

		// If there are attachments, calculate the total size and how many.
		$context['attachments']['total_size'] = 0;
		$context['attachments']['quantity'] = 0;

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$context['attachments']['quantity'] = count($context['current_attachments']);
			foreach ($context['current_attachments'] as $attachment)
				$context['attachments']['total_size'] += $attachment['size'];
		}

		// A bit of house keeping first.
		if (!empty($_SESSION['temp_attachments']) && count($_SESSION['temp_attachments']) == 1)
			unset($_SESSION['temp_attachments']);

		// Our infamous SESSION var, we are gonna have soo much fun with it!
		if (!isset($_SESSION['temp_attachments']))
			$_SESSION['temp_attachments'] = array();

		// Make sure we're uploading to the right place.
		if (!empty($modSettings['automanage_attachments']))
			automanage_attachments_check_directory();

		// Is the attachments folder actually there?
		if (!empty($context['dir_creation_error']))
			$this->_generalErrors[] = $context['dir_creation_error'];

		// The current attach folder ha some issues...
		elseif (!is_dir($this->_attchDir))
		{
			$this->_generalErrors[] = 'attach_folder_warning';
			log_error(sprintf($txt['attach_folder_admin_warning'], $this->_attchDir), 'critical');
		}

		// If this isn't a new post, check the current attachments.
		if (empty($this->_generalErrors) && $this->_msg)
		{
			$context['attachments'] = array();
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $this->_msg,
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

		// Check for other general errors here.

		// If we have an initial error, delete the files.
		if (!empty($this->_generalErrors))
		{
			// And delete the files 'cos they ain't going nowhere.
			foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
				if (file_exists($_FILES['attachment']['tmp_name'][$n]))
					unlink($_FILES['attachment']['tmp_name'][$n]);

			$_FILES['attachment']['tmp_name'] = array();

			// No point in going further with this.
			return;
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

				else
					log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);

				// Log this one, because...
				if ($_FILES['attachment']['error'][$n] == 6)
					log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_6'], 'critical');

				// Weird, no errors were cached, still fill out a generic one.
				if (empty($errors))
					$errors[] = 'attach_php_error';
			}

			// Try to move and rename the file before doing any more checks on it.
			$attachID = 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand());
			$destName = $this->_attchDir . '/' . $attachID;

			// No errors, YAY!
			if (empty($errors))
			{
				// The reported MIME type of the attachment might not be reliable.
				// Fortunately, PHP 5.3+ lets us easily verify the real MIME type.
				if (function_exists('mime_content_type'))
					$_FILES['attachment']['type'][$n] = mime_content_type($_FILES['attachment']['tmp_name'][$n]);

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
					smf_chmod($destName, 0644);

				// This is madness!!
				else
				{
					// File couldn't be moved.
					$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_timeout';
					if (file_exists($_FILES['attachment']['tmp_name'][$n]))
						unlink($_FILES['attachment']['tmp_name'][$n]);
				}
			}

			// Fill up a nice array with some data from the file and the errors encountered so far.
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
		//   tmp_name => Path to the temp file ($this->_attchDir . '/' . $attachID).
		//   size => File size (required).
		//   type => MIME type (optional if not available on upload).
		//   id_folder => $modSettings['currentAttachmentUploadDir']
		//   errors => An array of errors (use the index of the $txt variable for that error).
		// Template changes can be done using "integrate_upload_template".
		call_integration_hook('integrate_attachment_upload', array());
	}

	protected function createAttach()
	{
		global $txt, $user_info, $modSettings;

		// Create an empty session var to keep track of all the files we attached.
		$SESSION['already_attached'] = array();

		foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
		{
			$attachmentOptions = array(
				'post' => $this->_msg,
				'poster' => $user_info['id'],
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => isset($attachment['size']) ? $attachment['size'] : 0,
				'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
				'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : $modSettings['currentAttachmentUploadDir'],
				'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
				'errors' => array(),
			);

			if (empty($attachment['errors']))
			{
				if (createAttachment($attachmentOptions))
				{
					// Avoid JS getting confused.
					$attachmentOptions['attachID'] = $attachmentOptions['id'];
					unset($attachmentOptions['id']);

					$_SESSION['already_attached'][$attachmentOptions['attachID']] = $attachmentOptions['attachID'];

					if (!empty($attachmentOptions['thumb']))
						$_SESSION['already_attached'][$attachmentOptions['thumb']] = $attachmentOptions['thumb'];

					if ($this->_msg)
						assignAttachments($_SESSION['already_attached'], $this->_msg);
				}
			}
			else
			{
				// Sort out the errors for display and delete any associated files.
				$log_these = array('attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file');

				foreach ($attachment['errors'] as $error)
				{
					$attachmentOptions['errors'][] = vsprintf($txt['attach_warning'], $attachment['name']);

					if (!is_array($error))
					{
						$attachmentOptions['errors'][] = $txt[$error];
						if (in_array($error, $log_these))
							log_error($attachment['name'] . ': ' . $txt[$error], 'critical');
					}
					else
						$attachmentOptions['errors'][] = vsprintf($txt[$error[0]], $error[1]);
				}
				if (file_exists($attachment['tmp_name']))
					unlink($attachment['tmp_name']);
			}

			// You don't need to know.
			unset($attachmentOptions['tmp_name']);
			unset($attachmentOptions['destination']);

			// Regardless of errors, pass the results.
			$this->_attachResults[] = $attachmentOptions;
		}

		// Temp save this on the db.
		if (!empty($_SESSION['already_attached']))
			$this->_attachSuccess = $_SESSION['already_attached'];

		unset($_SESSION['temp_attachments']);
	}

	protected function setResponse($data = array())
	{
		global $txt;

		// Some default values in case something is missed or neglected :P
		$this->_response = array(
			'text' => 'attach_php_error',
			'type' => 'error',
			'data' => false,
		);

		// Adding needs some VIP treatment.
		if ($this->_sa == 'add')
		{
			// Is there any generic errors? made some sense out of them!
			if ($this->_generalErrors)
				foreach ($this->_generalErrors as $k => $v)
					$this->_generalErrors[$k] = (is_array($v) ? vsprintf($txt[$v[0]], $v[1]) : $txt[$v]);

			// Gotta urlencode the filename.
			if ($this->_attachResults)
				foreach ($this->_attachResults as $k => $v)
					$this->_attachResults[$k]['name'] = urlencode($this->_attachResults[$k]['name']);

			$this->_response = array(
				'files' => $this->_attachResults ? $this->_attachResults : false,
				'generalErrors' => $this->_generalErrors ? $this->_generalErrors : false,
			);
		}

		// Rest of us mere mortals gets no special treatment...
		elseif (!empty($data))
			if (!empty($data['text']) && !empty($txt[$data['text']]))
				$this->_response['text'] = $txt[$data['text']];
	}

	protected function sendResponse()
	{
		global $smcFunc, $modSettings, $context;

		ob_end_clean();

		if (!empty($modSettings['CompressedOutput']))
			@ob_start('ob_gzhandler');

		else
			ob_start();

		// Set the header.
		header('content-type: application/json; charset=' . $context['character_set'] . '');

		echo $smcFunc['json_encode']($this->_response ? $this->_response : array());

		// Done.
		obExit(false);
		die;
	}
}

?>