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
use SMF\BackwardCompatibility;
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

/**
 * Downloads an avatar or attachment based on $_GET['attach'], and increments the download count.
 * It requires the view_attachments permission.
 * It disables the session parser, and clears any previous output.
 * It depends on the attachmentUploadDir setting being correct.
 * It is accessed via the query string ?action=dlattach.
 * Views to attachments do not increase hits and are not logged in the "Who's Online" log.
 */
class AttachmentDownload implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'showAttachment',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID number of the requested attachment.
	 */
	public int $id;

	/**
	 * @var bool
	 *
	 * Whether to show the thumbnail image, if one is available.
	 */
	public int $showThumb;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// An early hook to set up global vars, clean cache and other early process.
		IntegrationHook::call('integrate_pre_download_request');

		// This is done to clear any output that was made before now.
		ob_end_clean();
		header_remove('content-encoding');

		// We need a valid ID.
		if (empty($this->id)) {
			Utils::sendHttpStatus(404, 'File Not Found');

			die('404 File Not Found');
		}

		// No access in strict maintenance mode.
		if (!empty(Config::$maintenance) && Config::$maintenance == 2) {
			Utils::sendHttpStatus(404, 'File Not Found');

			die('404 File Not Found');
		}

		// Use cache when possible.
		if (($cache = CacheApi::get('attachment_lookup_id-' . $this->id)) != null) {
			list($file, $thumbFile) = $cache;

			$file = @unserialize($file);
			$thumbFile = @unserialize($thumbFile);
		}

		// Get the info from the DB.
		if (empty($file) || empty($thumbFile) && !empty($file->thumb)) {
			/*
			 * Do we have a hook wanting to use our attachment system?
			 *
			 * NOTE TO MOD AUTHORS: This hook is deprecated. You should rewrite
			 * your code to use the integrate_attachment_load hook in the
			 * SMF\Attachment::load() method.
			 */
			$request = null;
			IntegrationHook::call('integrate_download_request', [&$request]);

			if (!is_null($request) && Db::$db->is_resource($request)) {
				// No attachment has been found.
				if (Db::$db->num_rows($request) == 0) {
					Utils::sendHttpStatus(404, 'File Not Found');

					die('404 File Not Found');
				}

				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);

				$file = new Attachment(0, $row);
				$file->setFileProperties();
			} else {
				Attachment::load($this->id);

				if (!isset(Attachment::$loaded[$this->id])) {
					Utils::sendHttpStatus(404, 'File Not Found');

					die('404 File Not Found');
				}

				$file = Attachment::$loaded[$this->id];
				$file->set(['source' => 'SMF']);
				$file->setFileProperties();
			}

			if (!empty($file->thumb)) {
				Attachment::load($file->thumb);
				$thumbFile = Attachment::$loaded[$file->thumb];
				$thumbFile->set(['source' => 'SMF']);
				$thumbFile->setFileProperties();
			}

			// Cache it.
			if (!empty($file) || !empty($thumbFile)) {
				CacheApi::put('attachment_lookup_id-' . $this->id, [serialize($file ?? null), serialize($thumbFile ?? null)], mt_rand(850, 900));
			}
		}

		// Can they see attachments on this board?
		if (!empty($file->msg)) {
			// Special case for profile exports.
			if (!empty(Utils::$context['attachment_allow_hidden_boards'])) {
				$boards_allowed = [0];
			}
			// Check permissions and board access.
			elseif (($boards_allowed = CacheApi::get('view_attachment_boards_id-' . User::$me->id)) == null) {
				$boards_allowed = User::$me->boardsAllowedTo('view_attachments');
				CacheApi::put('view_attachment_boards_id-' . User::$me->id, $boards_allowed, mt_rand(850, 900));
			}
		}

		// No access if you don't have permission to see this attachment.
		if
		(
			// This was from SMF or a hook didn't claim it.
			(
				empty($file->source)
				|| $file->source == 'SMF'
			)
			&& (
				// No id_msg and no id_member, so we don't know where its from.
				// Avatars will have id_msg = 0 and id_member > 0.
				(
					empty($file->msg)
					&& empty($file->member)
				)
				// When we have a message, we need a board and that board needs to
				// let us view the attachment.
				|| (
					!empty($file->msg)
					&& (
						empty($file->board)
						|| ($boards_allowed !== [0] && !in_array($file->board, $boards_allowed))
					)
				)
			)
			// We are not previewing an attachment.
			&& !isset($_SESSION['attachments_can_preview'][$this->id])
		) {
			Utils::sendHttpStatus(404, 'File Not Found');

			die('404 File Not Found');
		}

		// If attachment is unapproved, see if user is allowed to approve
		if (!$file->approved && Config::$modSettings['postmod_active'] && !User::$me->allowedTo('approve_posts')) {
			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				[
					'id_msg' => $file->msg,
				],
			);

			$id_member = Db::$db->fetch_assoc($request)['id_member'];
			Db::$db->free_result($request);

			// Let users see own unapproved attachments
			if ($id_member != User::$me->id) {
				Utils::sendHttpStatus(403, 'Forbidden');

				die('403 Forbidden');
			}
		}

		// Replace the normal file with its thumbnail if it has one!
		if (!empty($this->showThumb) && !empty($thumbFile)) {
			$file = $thumbFile;
		}

		// No point in a nicer message, because this is supposed to be an attachment anyway...
		if (empty($file->exists)) {
			Utils::sendHttpStatus(404, 'File Not Found');

			die('404 File Not Found');
		}

		// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);

			if (!empty($file->mtime) && strtotime($modified_since) >= $file->mtime) {
				ob_end_clean();
				header_remove('content-encoding');

				// Answer the question - no, it hasn't been modified ;).
				Utils::sendHttpStatus(304);

				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		if (!empty($file->etag) && !empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $file->etag) !== false) {
			ob_end_clean();
			header_remove('content-encoding');

			Utils::sendHttpStatus(304);

			exit;
		}

		// If this is a partial download, we need to determine what data range to send
		$range = 0;

		if (isset($_SERVER['HTTP_RANGE'])) {
			list($a, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			list($range) = explode(',', $range, 2);
			list($range, $range_end) = explode('-', $range);
			$range = intval($range);
			$range_end = !$range_end ? $file->size - 1 : intval($range_end);
			$length = $range_end - $range + 1;
		}

		// Update the download counter (unless it's a thumbnail or resuming an incomplete download).
		if ($file->type != 3 && empty($this->showThumb) && empty($_REQUEST['preview']) && $range === 0 && empty(Utils::$context['skip_downloads_increment'])) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}attachments
				SET downloads = downloads + 1
				WHERE id_attach = {int:id_attach}',
				[
					'id_attach' => $this->id,
				],
			);
		}

		// Make sure the mime type warrants an inline display.
		if (empty($file->mime_type)) {
			$file->mime_type = 'application/octet-stream';
		}

		if (BrowserDetector::isBrowser('ie') || BrowserDetector::isBrowser('opera')) {
			$file->mime_type = strtr($file->mime_type, ['application/octet-stream' => 'application/octetstream']);
		}

		if (strpos($file->mime_type, 'image/') !== 0) {
			unset($_REQUEST['image']);
		}

		// On mobile devices, audio and video should be served inline so the browser can play them.
		if (isset($_REQUEST['image']) || (BrowserDetector::isBrowser('is_mobile') && (strpos($file->mime_type, 'audio/') === 0 || strpos($file->mime_type, 'video/') === 0))) {
			$file->disposition = 'inline';
		} else {
			$file->disposition = 'attachment';
		}

		// If necessary, prepend the attachment ID to the file name.
		if (!empty(Utils::$context['prepend_attachment_id'])) {
			$file->filename = $_REQUEST['attach'] . ' - ' . $file->filename;
		}

		Utils::emitFile($file, $this->showThumb);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Some defaults that we need.
		if (!isset(Utils::$context['character_set'])) {
			Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? (empty(Lang::$txt['lang_character_set']) ? 'ISO-8859-1' : Lang::$txt['lang_character_set']) : Config::$modSettings['global_character_set'];
		}

		if (!isset(Utils::$context['utf8'])) {
			Utils::$context['utf8'] = Utils::$context['character_set'] === 'UTF-8';
		}

		// Which attachment was requested?
		$this->id = $_REQUEST['attach'] = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);

		// A thumbnail has been requested? madness! madness I say!
		$this->showThumb = isset($_REQUEST['thumb']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\AttachmentDownload::exportStatic')) {
	AttachmentDownload::exportStatic();
}

?>