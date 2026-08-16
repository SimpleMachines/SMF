<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Attachment;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\OutputTypeInterface;
use SMF\OutputTypes;
use SMF\Routable;
use SMF\User;
use SMF\Utils;

/**
 * This class handles adding/deleting attachments
 */
class AttachmentUpload implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The ID of the message this attachment is associated with
	 */
	protected $msg = 0;

	/**
	 * @var int|null The ID of the board this attachment's post is in or null if it's not set
	 */
	protected $board = null;

	/**
	 * @var string|bool An array of info about attachment upload directories or false
	 */
	protected $attachmentUploadDir = false;

	/**
	 * @var string The path to the current attachment directory
	 */
	protected $attchDir = '';

	/**
	 * @var int ID of the current attachment directory
	 */
	protected $currentAttachmentUploadDir;

	/**
	 * @var bool Whether or not an attachment can be posted
	 */
	protected $canPostAttachment;

	/**
	 * @var array An array of information about any errors that occurred
	 */
	protected $generalErrors = [];

	/**
	 * @var mixed Not used?
	 */
	protected $initialError;

	/**
	 * @var array Not used?
	 */
	protected $attachments = [];

	/**
	 * @var array An array of information about the results of each file
	 */
	protected $attachResults = [];

	/**
	 * @var array An array of information about successful attachments
	 */
	protected $attachSuccess = [];

	/**
	 * @var array An array of response information. @used-by \sendResponse() when adding attachments
	 */
	protected $response = [
		'error' => true,
		'data' => [],
		'extra' => '',
	];

	/**
	 * @var array An array of all valid sub-actions
	 */
	protected $subActions = [
		'add',
		'delete',
	];

	/**
	 * @var string|bool The current sub-action, or false if there isn't one
	 */
	protected $sa = false;

	/****************
	 * Public methods
	 ****************/

	public function canBeLogged(): bool
	{
		return false;
	}

	public function isSimpleAction(): bool
	{
		return true;
	}

	public function getOutputType(): OutputTypeInterface
	{
		return new OutputTypes\Xml();
	}

	/**
	 * Handles calling the appropriate function based on the sub-action
	 */
	public function execute(): void
	{
		$this->sa = !empty($_REQUEST['sa']) ? Utils::htmlspecialchars(Utils::htmlTrim($_REQUEST['sa'])) : false;

		if ($this->canPostAttachment && $this->sa && \in_array($this->sa, $this->subActions)) {
			$this->{$this->sa}();
		}
		// Just send a generic message.
		else {
			$this->setResponse([
				'text' => $this->sa == 'add' ? 'attach_error_title' : 'attached_file_deleted_error',
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
		if (checkSession('get', '', false) !== '' || $attachID === 0) {
			$this->setResponse([
				'text' => 'attached_file_deleted_error',
				'type' => 'error',
				'data' => false,
			]);

			return;
		}

		$msgInfo = Attachment::getAttachMsgInfo($attachID);

		$can_modify = false;

		if ($msgInfo !== [] && $msgInfo['msg'] > 0) {
			$can_modify = $msgInfo !== [] && !User::$me->is_guest && (!$msgInfo['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $msgInfo['id_member_started'] == User::$me->id) || (allowedTo('modify_own') && $msgInfo['id_member'] == User::$me->id && (empty($modSettings['edit_disable_time']) || !$msgInfo['approved'] || $msgInfo['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 > time())));
		} elseif ($msgInfo !== [] && $msgInfo['msg'] == 0) {
			$can_modify = !empty($_SESSION['already_attached']) && isset($_SESSION['already_attached'][$attachID]);
		}

		// Need something to work with.
		if (!$can_modify) {
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
		if (!$this->canPostAttachment) {
			$this->setResponse([
				'text' => 'attached_file_cannot',
				'type' => 'error',
				'data' => false,
			]);

			return;
		}

		// Process them at once!
		$this->processAttachments();

		// The attachments was created and moved to the right folder, time to update the DB.
		if (!empty($_SESSION['temp_attachments'])) {
			$this->createAttach();
		}

		// Set the response.
		$this->setResponse();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Attachments constructor.
	 *
	 * Sets up some initial information - the message ID, board, current attachment upload dir, etc.
	 * Protected to force instantiation via load().
	 */
	protected function __construct()
	{
		$this->msg = (int) !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0;
		$this->board = (int) !empty($_REQUEST['board']) ? $_REQUEST['board'] : null;

		$this->currentAttachmentUploadDir = Config::$modSettings['currentAttachmentUploadDir'];

		$this->attachmentUploadDir = Config::$modSettings['attachmentUploadDir'];

		$this->attchDir = Utils::$context['attach_dir'] = $this->attachmentUploadDir[Config::$modSettings['currentAttachmentUploadDir']];

		$this->canPostAttachment = Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (User::$me->allowedTo('post_attachment', $this->board) || (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_attachments', $this->board)));
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
			Utils::$context['attachments']['quantity'] = \count(Utils::$context['current_attachments']);

			foreach (Utils::$context['current_attachments'] as $attachment) {
				Utils::$context['attachments']['total_size'] += $attachment['size'];
			}
		}

		// A bit of house keeping first.
		if (!empty($_SESSION['temp_attachments']) && \count($_SESSION['temp_attachments']) == 1) {
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
			$this->generalErrors[] = Utils::$context['dir_creation_error'];
		}
		// The current attach folder has some issues...
		elseif (!is_dir($this->attchDir)) {
			$this->generalErrors[] = 'attach_directory_warning';

			ErrorHandler::log(Lang::getTxt('attach_directory_admin_warning', ['attach_dir' => $this->attchDir], file: 'Post'), 'critical');
		}

		// If this isn't a new post, check the current attachments.
		if (empty($this->generalErrors) && $this->msg) {
			Utils::$context['attachments'] = [];

			$request = Db::$db->query(
				'SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				[
					'id_msg' => (int) $this->msg,
					'attachment_type' => Attachment::TYPE_STANDARD,
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
		if (!empty($this->generalErrors)) {
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
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::getTxt('php_upload_error_' . $_FILES['attachment']['error'][$n], file: 'Post'));
				}

				// Log this one, because...
				if ($_FILES['attachment']['error'][$n] == 6) {
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::getTxt('php_upload_error_6', file: 'Post'), 'critical');
				}

				// Weird, no errors were cached, still fill out a generic one.
				if (empty($errors)) {
					$errors[] = 'attach_php_error';
				}
			}

			// Try to move and rename the file before doing any more checks on it.
			$attachID = 'post_tmp_' . User::$me->id . '_' . bin2hex(random_bytes(16));
			$destName = $this->attchDir . '/' . $attachID;

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
		//   tmp_name => Path to the temp file ($this->attchDir . '/' . $attachID).
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
				'post' => $this->msg,
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

					if ($this->msg) {
						Attachment::assign($_SESSION['already_attached'], $this->msg);
					}
				}
			} else {
				// Sort out the errors for display and delete any associated files.
				$log_these = ['attachments_no_create', 'attachments_no_write', 'attach_timeout', 'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file'];

				foreach ($attachment['errors'] as $error) {
					$attachmentOptions['errors'][] = Lang::getTxt('attach_warning', $attachment, file: 'Post');

					if (!\is_array($error)) {
						$attachmentOptions['errors'][] = Lang::getTxt($error, file: 'Post');

						if (\in_array($error, $log_these)) {
							ErrorHandler::log($attachment['name'] . ': ' . Lang::getTxt($error, ['path' => User::$me->is_admin ? $this->attchDir : Lang::getTxt('hidden', file: 'General')], file: 'Post'), 'critical');
						}
					} else {
						$attachmentOptions['errors'][] = Lang::getTxt($error[0], (array) $error[1], file: 'Post');
					}
				}

				if (file_exists($attachment['tmp_name'])) {
					unlink($attachment['tmp_name']);
				}
			}

			// You don't need to know.
			unset($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

			// Regardless of errors, pass the results.
			$this->attachResults[] = $attachmentOptions;
		}

		// Temp save this on the db.
		if (!empty($_SESSION['already_attached'])) {
			$this->attachSuccess = $_SESSION['already_attached'];
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
	protected function setResponse(array $data = []): void
	{
		// Some default values in case something is missed or neglected :P
		$this->response = [
			'text' => 'attach_php_error',
			'type' => 'error',
			'data' => false,
		];

		// Adding needs some VIP treatment.
		if ($this->sa == 'add') {
			// Is there any generic errors? made some sense out of them!
			if ($this->generalErrors) {
				foreach ($this->generalErrors as $k => $v) {
					$this->generalErrors[$k] = \is_array($v) ? Lang::getTxt($v[0], (array) $v[1], file: 'Post') : Lang::getTxt($v, file: 'Post');
				}
			}

			// Gotta urlencode the filename.
			if ($this->attachResults) {
				foreach ($this->attachResults as $k => $v) {
					$this->attachResults[$k]['name'] = urlencode($this->attachResults[$k]['name']);
				}
			}

			$this->response = [
				'files' => $this->attachResults ? $this->attachResults : false,
				'generalErrors' => $this->generalErrors ? $this->generalErrors : false,
			];
		}
		// Rest of us mere mortals gets no special treatment...
		elseif (!empty($data)) {
			if (!empty($data['text']) && Lang::txtExists($data['text'], file: 'Post')) {
				$this->response['text'] = Lang::getTxt($data['text'], file: 'Post');
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
		header('content-type: application/json; charset=UTF-8');

		echo Utils::jsonEncode($this->response ? $this->response : []);

		// Done.
		Utils::obExit(false);

		die;
	}
}
