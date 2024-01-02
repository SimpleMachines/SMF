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
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\User;
use SMF\Utils;

/**
 * Allows the moderator to approve or reject attachments.
 */
class AttachmentApprove implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ApproveAttach',
		],
	];

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
		// Security is our primary concern...
		User::$me->checkSession('get');

		// If it approve or delete?
		$is_approve = !isset($_GET['sa']) || $_GET['sa'] != 'reject';

		$attachments = [];

		// If we are approving all ID's in a message, get the ID's.
		if ($_GET['sa'] == 'all' && !empty($_GET['mid'])) {
			$id_msg = (int) $_GET['mid'];

			$request = Db::$db->query(
				'',
				'SELECT id_attach
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND approved = {int:is_approved}
					AND attachment_type = {int:attachment_type}',
				[
					'id_msg' => $id_msg,
					'is_approved' => 0,
					'attachment_type' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$attachments[] = $row['id_attach'];
			}
			Db::$db->free_result($request);
		} elseif (!empty($_GET['aid'])) {
			$attachments[] = (int) $_GET['aid'];
		}

		if (empty($attachments)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Now we have some ID's cleaned and ready to approve, but first - let's check we have permission!
		$allowed_boards = User::$me->boardsAllowedTo('approve_posts');

		// Validate the attachments exist and are the right approval state.
		$request = Db::$db->query(
			'',
			'SELECT a.id_attach, m.id_board, m.id_msg, m.id_topic
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}
				AND a.approved = {int:is_approved}',
			[
				'attachments' => $attachments,
				'attachment_type' => 0,
				'is_approved' => 0,
			],
		);
		$attachments = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// We can only add it if we can approve in this board!
			if ($allowed_boards = [0] || in_array($row['id_board'], $allowed_boards)) {
				$attachments[] = $row['id_attach'];

				// Also come up with the redirection URL.
				$redirect = 'topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'];
			}
		}
		Db::$db->free_result($request);

		if (empty($attachments)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Finally, we are there. Follow through!
		if ($is_approve) {
			// Checked and deemed worthy.
			Attachment::approve($attachments);
		} else {
			Attachment::remove(['id_attach' => $attachments, 'do_logging' => true]);
		}

		// Return to the topic....
		Utils::redirectexit($redirect);
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\AttachmentApprove::exportStatic')) {
	AttachmentApprove::exportStatic();
}

?>