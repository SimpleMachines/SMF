<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file has the important job of taking care of help messages and the
	help center.  It does this with two simple functions:

	void ShowHelp()
		- loads information needed for the help section.
		- accesed by ?action=help.
		- uses the Help template and Manual language file.

	void ShowAdminHelp()
		- shows a popup for administrative or user help.
		- uses the help parameter to decide what string to display and where
		  to get the string from. ($helptxt or $txt?)
		- loads the ManagePermissions language file if the help starts with
		  permissionhelp.
		- uses the Help template, popup sub template, no layers.
		- accessed via ?action=helpadmin;help=??.
*/

// Redirect to the user help ;).
function ShowHelp()
{
	global $scripturl, $context, $txt;

	loadTemplate('Help');
	loadLanguage('Manual');

	// We need to know where our wiki is.
	$context['wiki_url'] = 'http://wiki.simplemachines.org/smf';

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

// Show some of the more detailed help to give the admin an idea...
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