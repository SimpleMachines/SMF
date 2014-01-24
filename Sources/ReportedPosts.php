<?php

/**
 * Handles reported posts and moderation comments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Sets and call a function based on the given subaction.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function ReportedPosts()
{
	global $txt, $context, $scripturl, $user_info, $smcFunc;

	loadLanguage('ModerationCenter');
	loadTemplate('ModerationCenter');

	// Set an empty var for the server response.
	$context['report_post_action'] = '';

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_reported_posts'],
		'help' => '',
		'description' => $txt['mc_reported_posts_desc'],
	);

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	$context['sub_template'] = 'reported_posts';

	// This comes under the umbrella of moderating posts.
	if ($user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	$sub_actions = array(
		'open' => 'OpenReports',
		'closed' => 'ClosedReports',
		'handle' => 'HandleReport', // Deals with closing/opening reports.
		'disregard' => 'DisregardReport',
		'details' => 'ReportDetails', // Shows a single report and its comments.
		'addcomment' => 'AddComment',
		'editcomment' => 'EditComment',
		'deletecomment' => 'DeleteComment',
	);

	// Go ahead and add your own sub-actions.
	call_integration_hook('integrate_reported_posts', array(&$sub_actions));

	// By default we call the open sub-action.
	if (isset($_REQUEST['sa']) && isset($sub_actions[$_REQUEST['sa']]))
		$context['sub_action'] = $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_REQUEST['sa']), ENT_QUOTES);

	else
		$context['sub_action'] = 'open';

	// Call the function!
	$sub_actions[$context['sub_action']]();
}

/**
 * Shows all open reported posts.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function OpenReports
{

}
?>