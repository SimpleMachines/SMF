<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\Attachment;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

/**
 * This class handles adding/deleting attachments
 */
class AttachmentUpload implements ActionInterface
{
	/**
	 * @var int The ID of the message this attachment is associated with
	 */
	protected $_msg = 0;

	/**
	 * @var int|null The ID of the board this attachment's post is in or null if it's not set
	 */
	protected $_board = null;

	/**
	 * @var string|bool An array of info about attachment upload directories or false
	 */
	protected $_attachmentUploadDir = false;

	/**
	 * @var string The path to the current attachment directory
	 */
	protected $_attchDir = '';

	/**
	 * @var int ID of the current attachment directory
	 */
	protected $_currentAttachmentUploadDir;

	/**
	 * @var bool Whether or not an attachment can be posted
	 */
	protected $_canPostAttachment;

	/**
	 * @var array An array of information about any errors that occurred
	 */
	protected $_generalErrors = [];

	/**
	 * @var mixed Not used?
	 */
	protected $_initialError;

	/**
	 * @var array Not used?
	 */
	protected $_attachments = [];

	/**
	 * @var array An array of information about the results of each file
	 */
	protected $_attachResults = [];

	/**
	 * @var array An array of information about successful attachments
	 */
	protected $_attachSuccess = [];

	/**
	 * @var array An array of response information. @used-by \sendResponse() when adding attachments
	 */
	protected $_response = [
		'error' => true,
		'data' => [],
		'extra' => '',
	];

	/**
	 * @var array An array of all valid sub-actions
	 */
	protected $_subActions = [
		'add',
		'delete',
	];

	/**
	 * @var string|bool The current sub-action, or false if there isn't one
	 */
	protected $_sa = false;

	/**
	 * @var object
	 *
	 * An instance of this class.
	 */
	protected static $obj;

	/**
	 * Wrapper for constructor. Ensures only one instance is created.
	 *
	 * @todo Add a reference to Utils::$context['instances'] as well?
	 *
	 * @return An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Attachments constructor.
	 *
	 * Sets up some initial information - the message ID, board, current attachment upload dir, etc.
	 * Protected to force instantiation via load().
	 */
	protected function __construct()
	{
		$this->_msg = (int) !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0;
		$this->_board = (int) !empty($_REQUEST['board']) ? $_REQUEST['board'] : null;

		$this->_currentAttachmentUploadDir = Config::$modSettings['currentAttachmentUploadDir'];

		$this->_attachmentUploadDir = Config::$modSettings['attachmentUploadDir'];

		$this->_attchDir = Utils::$context['attach_dir'] = $this->_attachmentUploadDir[Config::$modSettings['currentAttachmentUploadDir']];

		$this->_canPostAttachment = Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (User::$me->allowedTo('post_attachment', $this->_board) || (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_attachments', $this->_board)));
	}

	/**
	 * Handles calling the appropriate function based on the sub-action
	 */
	public function execute(): void
	{
		// Need this. For reasons...
		Lang::load('Post');

		$this->_sa = !empty($_REQUEST['sa']) ? Utils::htmlspecialchars(Utils::htmlTrim($_REQUEST['sa'])) : false;

		if ($this->_canPostAttachment && $this->_sa && in_array($this->_sa, $this->_subActions)) {
			$this->{$this->_sa}();
		}
		// Just send a generic message.
		else {
			$this->setResponse([
				'text' => $this->_sa == 'add' ? 'attach_error_title' : 'attached_file_deleted_error',
				'type' => 'error',
				'data' => false,
			]);
		}

		// Back to the future, oh, to the browser!
		$this->sendResponse();
	}

	/**
	 * Handles deleting the attachment
	 */
	public function delete(): void
	{
		$attachID = !empty($_REQUEST['attach']) && is_numeric($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;

		// Need something to work with.
		if (!$attachID || (!empty($_SESSION['already_attached']) && !isset($_SESSION['already_attached'][$attachID]))) {
			$this->setResponse([
				'text' => 'attached_file_deleted_error',
				'type' => 'error',
				'data' => false,
			]);

			return;
		}

		// Lets pass some params and see what happens :P
		$affectedMessage = Attachment::remove(['id_attach' => $attachID], '', true, true);

		// Gotta also remove the attachment from the session var.
		unset($_SESSION['already_attached'][$attachID]);

		// $affectedMessage returns an empty array array(0) which php treats as non empty... awesome...
		$this->setResponse([
			'text' => !empty($affectedMessage) ? 'attached_file_deleted' : 'attached_file_deleted_error',
			'type' => !empty($affectedMessage) ? 'info' : 'warning',
			'data' => $affectedMessage,
		]);
	}

	/**
	 * Handles adding an attachment
	 */
	public function add(): void
	{
		// You gotta be able to post attachments.
		if (!$this->_canPostAttachment) {
			$this->setResponse([
				'text' => 'attached_file_cannot',
				'type' => 'error',
				'data' => false,
			]);

			return;
		}

		// Process them at once!
		$this->processAttachments();

		// The attachments was created and moved the the right folder, time to update the DB.
		if (!empty($_SESSION['temp_attachments'])) {
			$this->createAttach();
		}

		// Set the response.
		$this->setResponse();
	}

	/**
	 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
	 */
	protected function processAttachments(): void
	{
		if (!isset($_FILES['attachment']['name'])) {
			$_FILES['attachment']['tmp_name'] = [];
		}

		// If there are attachments, calculate the total size and how many.
		Utils::$context['attachments']['total_size'] = 0;
		Utils::$context['attachments']['quantity'] = 0;

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg'])) {
			Utils::$context['attachments']['quantity'] = count(Utils::$context['current_attachments']);

			foreach (Utils::$context['current_attachments'] as $attachment) {
				Utils::$context['attachments']['total_size'] += $attachment['size'];
			}
		}

		// A bit of house keeping first.
		if (!empty($_SESSION['temp_attachments']) && count($_SESSION['temp_attachments']) == 1) {
			unset($_SESSION['temp_attachments']);
		}

		// Our infamous SESSION var, we are gonna have soo much fun with it!
		if (!isset($_SESSION['temp_attachments'])) {
			$_SESSION['temp_attachments'] = [];
		}

		// Make sure we're uploading to the right place.
		if (!empty(Config::$modSettings['automanage_attachments'])) {
			Attachment::automanageCheckDirectory();
		}

		// Is the attachments folder actually there?
		if (!empty(Utils::$context['dir_creation_error'])) {
			$this->_generalErrors[] = Utils::$context['dir_creation_error'];
		}
		// The current attach folder has some issues...
		elseif (!is_dir($this->_attchDir)) {
			$this->_generalErrors[] = 'attach_folder_warning';

			ErrorHandler::log(sprintf(Lang::$txt['attach_folder_admin_warning'], $this->_attchDir), 'critical');
		}

		// If this isn't a new post, check the current attachments.
		if (empty($this->_generalErrors) && $this->_msg) {
			Utils::$context['attachments'] = [];

			$request = Db::$db->query(
				'',
				'SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				[
					'id_msg' => (int) $this->_msg,
					'attachment_type' => 0,
				],
			);
			list(Utils::$context['attachments']['quantity'], Utils::$context['attachments']['total_size']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			Utils::$context['attachments'] = [
				'quantity' => 0,
				'total_size' => 0,
			];
		}

		// Check for other general errors here.

		// If we have an initial error, delete the files.
		if (!empty($this->_generalErrors)) {
			// And delete the files 'cos they ain't going nowhere.
			foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy) {
				if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
					unlink($_FILES['attachment']['tmp_name'][$n]);
				}
			}

			$_FILES['attachment']['tmp_name'] = [];

			// No point in going further with this.
			return;
		}

		// Loop through $_FILES['attachment'] array and move each file to the current attachments folder.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy) {
			if ($_FILES['attachment']['name'][$n] == '') {
				continue;
			}

			// First, let's first check for PHP upload errors.
			$errors = [];

			if (!empty($_FILES['attachment']['error'][$n])) {
				if ($_FILES['attachment']['error'][$n] == 2) {
					$errors[] = ['file_too_big', [Config::$modSettings['attachmentSizeLimit']]];
				} else {
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::$txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);
				}

				// Log this one, because...
				if ($_FILES['attachment']['error'][$n] == 6) {
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::$txt['php_upload_error_6'], 'critical');
				}

				// Weird, no errors were cached, still fill out a generic one.
				if (empty($errors)) {
					$errors[] = 'attach_php_error';
				}
			}

			// Try to move and rename the file before doing any more checks on it.
			$attachID = 'post_tmp_' . User::$me->id . '_' . bin2hex(random_bytes(16));
			$destName = $this->_attchDir . '/' . $attachID;

			// No errors, YAY!
			if (empty($errors)) {
				// The reported MIME type of the attachment might not be reliable.
				$detected_mime_type = Utils::getMimeType($_FILES['attachment']['tmp_name'][$n], true);

				if ($detected_mime_type !== false) {
					$_FILES['attachment']['type'][$n] = $detected_mime_type;
				}

				$_SESSION['temp_attachments'][$attachID] = [
					'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
					'tmp_name' => $destName,
					'size' => $_FILES['attachment']['size'][$n],
					'type' => $_FILES['attachment']['type'][$n],
					'id_folder' => Config::$modSettings['currentAttachmentUploadDir'],
					'errors' => [],
				];

				// Move the file to the attachments folder with a temp name for now.
				if (@move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName)) {
					Utils::makeWritable($destName, 0644);
				}
				// This is madness!!
				else {
					// File couldn't be moved.
					$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_timeout';

					if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
						unlink($_FILES['attachment']['tmp_name'][$n]);
					}
				}
			}
			// Fill up a nice array with some data from the file and the errors encountered so far.
			else {
				$_SESSION['temp_attachments'][$attachID] = [
					'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
					'tmp_name' => $destName,
					'errors' => $errors,
				];

				if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
					unlink($_FILES['attachment']['tmp_name'][$n]);
				}
			}

			// If there's no errors to this point. We still do need to apply some additional checks before we are finished.
			if (empty($_SESSION['temp_attachments'][$attachID]['errors'])) {
				Attachment::check($attachID);
			}
		}

		// Mod authors, finally a hook to hang an alternate attachment upload system upon
		// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . User::$me->id . '_' . bin2hex(random_bytes(16))
		// Populate $_SESSION['temp_attachments'][$attachID] with the following:
		//   name => The file name
		//   tmp_name => Path to the temp file ($this->_attchDir . '/' . $attachID).
		//   size => File size (required).
		//   type => MIME type (optional if not available on upload).
		//   id_folder => Config::$modSettings['currentAttachmentUploadDir']
		//   errors => An array of errors (use the index of the Lang::$txt variable for that error).
		// Template changes can be done using "integrate_upload_template".
		IntegrationHook::call('integrate_attachment_upload', []);
	}

	/**
	 * Actually attaches the file
	 */
	protected function createAttach(): void
	{
		// Create an empty session var to keep track of all the files we attached.
		if (!isset($_SESSION['already_attached'])) {
			$_SESSION['already_attached'] = [];
		}

		foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
			$attachmentOptions = [
				'post' => $this->_msg,
				'poster' => User::$me->id,
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => $attachment['size'] ?? 0,
				'mime_type' => $attachment['type'] ?? '',
				'id_folder' => $attachment['id_folder'] ?? Config::$modSettings['currentAttachmentUploadDir'],
				'approved' => !Config::$modSettings['postmod_active'] || User::$me->allowedTo('post_attachment'),
				'errors' => [],
			];

			if (empty($attachment['errors'])) {
				if (Attachment::create($attachmentOptions)) {
					// Avoid JS getting confused.
					$attachmentOptions['attachID'] = $attachmentOptions['id'];
					unset($attachmentOptions['id']);

					$_SESSION['already_attached'][$attachmentOptions['attachID']] = $attachmentOptions['attachID'];

					if (!empty($attachmentOptions['thumb'])) {
						$_SESSION['already_attached'][$attachmentOptions['thumb']] = $attachmentOptions['thumb'];
					}

					if ($this->_msg) {
						Attachment::assign($_SESSION['already_attached'], $this->_msg);
					}
				}
			} else {
				// Sort out the errors for display and delete any associated files.
				$log_these = ['attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file'];

				foreach ($attachment['errors'] as $error) {
					$attachmentOptions['errors'][] = sprintf(Lang::$txt['attach_warning'], $attachment['name']);

					if (!is_array($error)) {
						$attachmentOptions['errors'][] = Lang::$txt[$error];

						if (in_array($error, $log_these)) {
							ErrorHandler::log($attachment['name'] . ': ' . Lang::$txt[$error], 'critical');
						}
					} else {
						$attachmentOptions['errors'][] = vsprintf(Lang::$txt[$error[0]], (array) $error[1]);
					}
				}

				if (file_exists($attachment['tmp_name'])) {
					unlink($attachment['tmp_name']);
				}
			}

			// You don't need to know.
			unset($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

			// Regardless of errors, pass the results.
			$this->_attachResults[] = $attachmentOptions;
		}

		// Temp save this on the db.
		if (!empty($_SESSION['already_attached'])) {
			$this->_attachSuccess = $_SESSION['already_attached'];
		}

		unset($_SESSION['temp_attachments']);

		// Allow user to see previews for all of this post's attachments, even if the post hasn't been submitted yet.
		if (!isset($_SESSION['attachments_can_preview'])) {
			$_SESSION['attachments_can_preview'] = [];
		}

		if (!empty($_SESSION['already_attached'])) {
			$_SESSION['attachments_can_preview'] += array_fill_keys(array_keys($_SESSION['already_attached']), true);
		}
	}

	/**
	 * Sets up the response information
	 *
	 * @param array $data Data for the response if we're not adding an attachment
	 */
	protected function setResponse($data = []): void
	{
		// Some default values in case something is missed or neglected :P
		$this->_response = [
			'text' => 'attach_php_error',
			'type' => 'error',
			'data' => false,
		];

		// Adding needs some VIP treatment.
		if ($this->_sa == 'add') {
			// Is there any generic errors? made some sense out of them!
			if ($this->_generalErrors) {
				foreach ($this->_generalErrors as $k => $v) {
					$this->_generalErrors[$k] = (is_array($v) ? vsprintf(Lang::$txt[$v[0]], (array) $v[1]) : Lang::$txt[$v]);
				}
			}

			// Gotta urlencode the filename.
			if ($this->_attachResults) {
				foreach ($this->_attachResults as $k => $v) {
					$this->_attachResults[$k]['name'] = urlencode($this->_attachResults[$k]['name']);
				}
			}

			$this->_response = [
				'files' => $this->_attachResults ? $this->_attachResults : false,
				'generalErrors' => $this->_generalErrors ? $this->_generalErrors : false,
			];
		}
		// Rest of us mere mortals gets no special treatment...
		elseif (!empty($data)) {
			if (!empty($data['text']) && !empty(Lang::$txt[$data['text']])) {
				$this->_response['text'] = Lang::$txt[$data['text']];
			}
		}
	}

	/**
	 * Sends the response data
	 */
	protected function sendResponse(): void
	{
		ob_end_clean();

		if (!empty(Config::$modSettings['enableCompressedOutput'])) {
			@ob_start('ob_gzhandler');
		} else {
			ob_start();
		}

		// Set the header.
		header('content-type: application/json; charset=' . Utils::$context['character_set'] . '');

		echo Utils::jsonEncode($this->_response ? $this->_response : []);

		// Done.
		Utils::obExit(false);

		die;
	}
}

?>