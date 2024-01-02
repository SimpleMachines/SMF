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

namespace SMF\Actions\Moderation;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows a notice sent to a user.
 */
class ShowNotice implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ShowNotice',
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
		$id_notice = (int) $_GET['nid'];

		$request = Db::$db->query(
			'',
			'SELECT body, subject
			FROM {db_prefix}log_member_notices
			WHERE id_notice = {int:id_notice}',
			[
				'id_notice' => $id_notice,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_access', false);
		}
		list(Utils::$context['notice_body'], Utils::$context['notice_subject']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['notice_body'] = BBCodeParser::load()->parse(Utils::$context['notice_body'], false);
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
		// Before we get too excited, is the current user allowed to see this?
		User::$me->isAllowedTo(['issue_warning', 'view_warning_any']);

		Utils::$context['page_title'] = Lang::$txt['show_notice'];
		Utils::$context['sub_template'] = 'show_notice';
		Utils::$context['template_layers'] = [];

		Theme::loadTemplate('ModerationCenter');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ShowNotice::exportStatic')) {
	ShowNotice::exportStatic();
}

?>