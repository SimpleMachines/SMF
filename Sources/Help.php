<?php

/**
 * This file has the important job of taking care of help messages and the help center.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Theme;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 *
 * Uses Help template and Manual language file.
 */
function ShowHelp()
{
	Theme::loadTemplate('Help');
	Lang::load('Manual');

	$subActions = array(
		'index' => 'HelpIndex',
	);

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_help', array(&$subActions));

	$sa = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'index';
	call_helper($subActions[$sa]);
}

/**
 * The main page for the Help section
 */
function HelpIndex()
{
	// We need to know where our wiki is.
	Utils::$context['wiki_url'] = 'https://wiki.simplemachines.org/smf';
	Utils::$context['wiki_prefix'] = 'SMF2.1:';

	Utils::$context['canonical_url'] = Config::$scripturl . '?action=help';

	// Sections were are going to link...
	Utils::$context['manual_sections'] = array(
		'registering' => 'Registering',
		'logging_in' => 'Logging_In',
		'profile' => 'Profile',
		'search' => 'Search',
		'posting' => 'Posting',
		'bbc' => 'Bulletin_board_code',
		'personal_messages' => 'Personal_messages',
		'memberlist' => 'Memberlist',
		'calendar' => 'Calendar',
		'features' => 'Features',
	);

	// Build the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=help',
		'name' => Lang::$txt['help'],
	);

	// Lastly, some minor template stuff.
	Utils::$context['page_title'] = Lang::$txt['manual_smf_user_help'];
	Utils::$context['sub_template'] = 'manual';
}

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
function ShowAdminHelp()
{
	if (!isset($_GET['help']) || !is_string($_GET['help']))
		fatal_lang_error('no_access', false);

	// Load the admin help language file and template.
	Lang::load('Help');

	// Permission specific help?
	if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
		Lang::load('ManagePermissions');

	Theme::loadTemplate('Help');

	// Allow mods to load their own language file here
	call_integration_hook('integrate_helpadmin');

	// What help string should be used?
	if (isset(Lang::$helptxt[$_GET['help']]))
		Utils::$context['help_text'] = Lang::$helptxt[$_GET['help']];
	elseif (isset(Lang::$txt[$_GET['help']]))
		Utils::$context['help_text'] = Lang::$txt[$_GET['help']];
	else
		fatal_lang_error('not_found', false, array(), 404);

	switch ($_GET['help']) {
		case 'cal_short_months':
			Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Lang::$txt['months_short'][1], Lang::$txt['months_titles'][1]);
			break;
		case 'cal_short_days':
			Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Lang::$txt['days_short'][1], Lang::$txt['days'][1]);
			break;
		case 'cron_is_real_cron':
			Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], allowedTo('admin_forum') ? Config::$boarddir : '[' . Lang::$txt['hidden'] . ']', Config::$boardurl);
			break;
		case 'queryless_urls':
			Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], (isset($_SERVER['SERVER_SOFTWARE']) && (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) ? Lang::$helptxt['queryless_urls_supported'] : Lang::$helptxt['queryless_urls_unsupported']));
			break;
	}

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', Utils::$context['help_text'], $match))
		Utils::$context['help_text'] = sprintf(Utils::$context['help_text'], Config::$scripturl, Utils::$context['session_id'], Utils::$context['session_var']);

	// Set the page title to something relevant.
	Utils::$context['page_title'] = Utils::$context['forum_name'] . ' - ' . Lang::$txt['help'];

	// Don't show any template layers, just the popup sub template.
	Utils::$context['template_layers'] = array();
	Utils::$context['sub_template'] = 'popup';
}

?>