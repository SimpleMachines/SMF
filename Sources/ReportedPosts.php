<?php

/**
 * Handles reported posts and moderation comments
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
 * Shows both open and closed reports as well as their respective comments
 * calls a function based on each subaction.
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

	// This comes under the umbrella of moderating posts.
	if ($user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	$sub_actions = array(
		'close' => 'CloseReport',
		'disregard' => 'DisregardReport',
		'details' => 'ReportDetails',
		'addcomment' => 'AddComment',
		'editcomment' => 'EditComment',
		'deletecomment' => 'DeleteComment',
	);
}
?>