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

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows popup help for the admin (and moderators sometimes) to describe
 * complex settings and such.
 */
class HelpAdmin implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ShowAdminHelp',
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
	 * Show some of the more detailed help to give the admin an idea...
	 * It shows a popup for administrative or user help.
	 * It uses the help parameter to decide what string to display and where to get
	 * the string from. (Lang::$helptxt or Lang::$txt?)
	 * It is accessed via ?action=helpadmin;help=?.
	 *
	 * Uses ManagePermissions language file, if the help starts with permissionhelp.
	 * @uses template_popup() with no layers.
	 */
	public function execute(): void
	{
		if (!isset($_GET['help']) || !is_string($_GET['help'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Load the admin help language file and template.
		Lang::load('Help');

		// Permission specific help?
		if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp') {
			Lang::load('ManagePermissions');
		}

		Theme::loadTemplate('Help');

		// Allow mods to load their own language file here
		IntegrationHook::call('integrate_helpadmin');

		// What help string should be used?
		if (isset(Lang::$helptxt[$_GET['help']])) {
			Utils::$context['help_text'] = Lang::$helptxt[$_GET['help']];
		} elseif (isset(Lang::$txt[$_GET['help']])) {
			Utils::$context['help_text'] = Lang::$txt[$_GET['help']];
		} else {
			ErrorHandler::fatalLang('not_found', false, [], 404);
		}

		switch ($_GET['help']) {
			case 'cal_short_months':
				Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Lang::$txt['months_short'][1], Lang::$txt['months_titles'][1]);
				break;

			case 'cal_short_days':
				Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Lang::$txt['days_short'][1], Lang::$txt['days'][1]);
				break;

			case 'cron_is_real_cron':
				Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], User::$me->allowedTo('admin_forum') ? Config::$boarddir : '[' . Lang::$txt['hidden'] . ']', Config::$boardurl);
				break;

			case 'queryless_urls':
				Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], (isset($_SERVER['SERVER_SOFTWARE']) && (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) ? Lang::$helptxt['queryless_urls_supported'] : Lang::$helptxt['queryless_urls_unsupported']));
				break;
		}

		// Does this text contain a link that we should fill in?
		if (preg_match('~%([0-9]+\$)?s\?~', Utils::$context['help_text'], $match)) {
			Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Config::$scripturl, Utils::$context['session_id'], Utils::$context['session_var']);
		}

		// Set the page title to something relevant.
		Utils::$context['page_title'] = Utils::$context['forum_name'] . ' - ' . Lang::$txt['help'];

		// Don't show any template layers, just the popup sub template.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'popup';
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
if (is_callable(__NAMESPACE__ . '\\HelpAdmin::exportStatic')) {
	HelpAdmin::exportStatic();
}

?>