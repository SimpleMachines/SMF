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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\Actions\AttachmentDownload;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Profile;
use SMF\Utils;

/**
 * Downloads an attachment that was linked from a profile export.
 */
class ExportAttachment implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'export_attachment',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The requested attachment ID.
	 */
	public int $attach;

	/**
	 * @var string
	 *
	 * Unique hash to anonymously identify the member whose profile this is.
	 */
	public string $idhash;

	/**
	 * @var string
	 *
	 * Unique download token for the member whose profile this is.
	 */
	public string $dltoken;

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
		if (!isset($_GET['t']) || $_GET['t'] !== $this->dltoken) {
			Utils::sendHttpStatus(403);

			exit;
		}

		if (empty($this->attach)) {
			Utils::sendHttpStatus(404);

			die('404 File Not Found');
		}

		// Does this attachment belong to this member?
		$request = Db::$db->query(
			'',
			'SELECT m.id_topic
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}attachments AS a ON (m.id_msg = a.id_msg)
			WHERE m.id_member = {int:uid}
				AND a.id_attach = {int:attach}',
			[
				'uid' => Profile::$member->id,
				'attach' => $this->attach,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			Db::$db->free_result($request);
			Utils::sendHttpStatus(403);

			exit;
		}
		Db::$db->free_result($request);

		// This doesn't count as a normal download.
		Utils::$context['skip_downloads_increment'] = true;

		// Try to avoid collisions when attachment names are not unique.
		Utils::$context['prepend_attachment_id'] = true;

		// Allow access to their attachments even if they can't see the board.
		// This is just like what we do with posts during export.
		Utils::$context['attachment_allow_hidden_boards'] = true;

		// We should now have what we need to serve the file.
		AttachmentDownload::call();
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
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		$this->idhash = hash_hmac('sha1', Profile::$member->id, Config::getAuthSecret());
		$this->dltoken = hash_hmac('sha1', $this->idhash, Config::getAuthSecret());

		$this->attach = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ExportAttachment::exportStatic')) {
	ExportAttachment::exportStatic();
}

?>