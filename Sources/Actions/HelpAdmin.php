<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
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
	use ActionTrait;

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
		if (isset($_GET['help']) && str_starts_with($_GET['help'], 'permissionhelp')) {
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
				Utils::$context['help_text'] = Lang::formatText(
					Utils::$context['help_text'],
					[
						'short' => Lang::$txt['months_short'][1],
						'long' => Lang::$txt['months_titles'][1],
					],
				);
				break;

			case 'cal_short_days':
				Utils::$context['help_text'] = Lang::formatText(
					Utils::$context['help_text'],
					[
						'short' => Lang::$txt['days_short'][1],
						'long' => Lang::$txt['days'][1],
					],
				);
				break;

			case 'cron_is_real_cron':
				Utils::$context['help_text'] = Lang::formatText(
					Utils::$context['help_text'],
					[
						'boarddir' => User::$me->allowedTo('admin_forum') ? Config::$boarddir : '[' . Lang::$txt['hidden'] . ']',
						'boardurl' => Config::$boardurl,
					],
				);
				break;

			case 'queryless_urls':
				Utils::$context['help_text'] = Lang::formatText(
					Utils::$context['help_text'],
					[
						(isset($_SERVER['SERVER_SOFTWARE']) && (str_contains($_SERVER['SERVER_SOFTWARE'], 'Apache') || str_contains($_SERVER['SERVER_SOFTWARE'], 'lighttpd')) ? 'supported' : 'unsupported'),
					],
				);
				break;
		}

		// Does this text contain a link that we should fill in?
		if (preg_match('~%([0-9]+\$)?s\?~', Utils::$context['help_text'], $match)) {
			Utils::$context['help_text'] = Lang::formatText(
				Utils::$context['help_text'],
				[
					'scripturl' => Config::$scripturl,
					'session_id' => Utils::$context['session_id'],
					'session_var' => Utils::$context['session_var'],
				],
			);
		}

		// Set the page title to something relevant.
		Utils::$context['page_title'] = Utils::$context['forum_name'] . ' - ' . Lang::$txt['help'];

		// Don't show any template layers, just the popup sub template.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'popup';
	}
}

?>