<?php

/**
 * This file has the important job of taking care of help messages and the help center.
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

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 *
 * @uses Help template and Manual language file.
 */
function ShowHelp()
{
	loadTemplate('Help');
	loadLanguage('Manual');

	$subActions = array(
		'index' => 'HelpIndex',
		'rules' => 'HelpRules',
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
	global $scripturl, $context, $txt;

	// We need to know where our wiki is.
	$context['wiki_url'] = 'https://wiki.simplemachines.org/smf';
	$context['wiki_prefix'] = 'SMF2.1:';

	$context['canonical_url'] = $scripturl . '?action=help';

	// Sections were are going to link...
	$context['manual_sections'] = array(
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
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);

	// Lastly, some minor template stuff.
	$context['page_title'] = $txt['manual_smf_user_help'];
	$context['sub_template'] = 'manual';
}

/**
 * Displays forum rules
 */
function HelpRules()
{
	global $context, $txt, $boarddir, $user_info, $scripturl;

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help;sa=rules',
		'name' => $txt['terms_and_rules'],
	);

	// Have we got a localized one?
	if (file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt'))
		$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
	elseif (file_exists($boarddir . '/agreement.txt'))
		$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.txt'), true, 'agreement');
	else
		$context['agreement'] = '';

	// Nothing to show, so let's get out of here
	if (empty($context['agreement']))
	{
		// No file found or a blank file! Just leave...
		redirectexit();
	}

	$context['canonical_url'] = $scripturl . '?action=help;sa=rules';

	$context['page_title'] = $txt['terms_and_rules'];
	$context['sub_template'] = 'terms';
}

/**
 * Show some of the more detailed help to give the admin an idea...
 * It shows a popup for administrative or user help.
 * It uses the help parameter to decide what string to display and where to get
 * the string from. ($helptxt or $txt?)
 * It is accessed via ?action=helpadmin;help=?.
 *
 * @uses ManagePermissions language file, if the help starts with permissionhelp.
 * @uses Help template, popup sub template, no layers.
 */
function ShowAdminHelp()
{
	global $txt, $helptxt, $context, $scripturl;

	if (!isset($_GET['help']) || !is_string($_GET['help']))
		fatal_lang_error('no_access', false);

	if (!isset($helptxt))
		$helptxt = array();

	// Load the admin help language file and template.
	loadLanguage('Help');

	// Permission specific help?
	if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	loadTemplate('Help');

	// Allow mods to load their own language file here
	call_integration_hook('integrate_helpadmin');

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

	// Don't show any template layers, just the popup sub template.
	$context['template_layers'] = array();
	$context['sub_template'] = 'popup';

	// What help string should be used?
	if (isset($helptxt[$_GET['help']]))
		$context['help_text'] = $helptxt[$_GET['help']];
	elseif (isset($txt[$_GET['help']]))
		$context['help_text'] = $txt[$_GET['help']];
	else
		$context['help_text'] = $_GET['help'];

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}

?>