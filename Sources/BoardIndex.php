<?php

/**
 * The single function this file contains is used to display the main
 * board index.
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
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;

if (!defined('SMF'))
	die('No direct access...');

/**
 * This function shows the board index.
 * It uses the BoardIndex template, and main sub template.
 * It updates the most online statistics.
 * It is accessed by ?action=boardindex.
 */
function BoardIndex()
{
	global $settings;

	loadTemplate('BoardIndex');
	Utils::$context['template_layers'][] = 'boardindex_outer';

	// Set a canonical URL for this page.
	Utils::$context['canonical_url'] = Config::$scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		Utils::$context['robot_no_index'] = true;

	// Retrieve the categories and boards.
	require_once(Config::$sourcedir . '/Subs-BoardIndex.php');
	$boardIndexOptions = array(
		'include_categories' => true,
		'base_level' => 0,
		'parent_id' => 0,
		'set_latest_post' => true,
		'countChildPosts' => !empty(Config::$modSettings['countChildPosts']),
	);
	Utils::$context['categories'] = getBoardIndex($boardIndexOptions);

	// Now set up for the info center.
	Utils::$context['info_center'] = array();

	// Retrieve the latest posts if the theme settings require it.
	if (!empty($settings['number_recent_posts']))
	{
		if ($settings['number_recent_posts'] > 1)
		{
			$latestPostOptions = array(
				'number_posts' => $settings['number_recent_posts'],
			);
			Utils::$context['latest_posts'] = CacheApi::quickGet('boardindex-latest_posts:' . md5(User::$me->query_wanna_see_board . User::$me->language), 'Subs-Recent.php', 'cache_getLastPosts', array($latestPostOptions));
		}

		if (!empty(Utils::$context['latest_posts']) || !empty(Utils::$context['latest_post']))
			Utils::$context['info_center'][] = array(
				'tpl' => 'recent',
				'txt' => 'recent_posts',
			);
	}

	// Load the calendar?
	if (!empty(Config::$modSettings['cal_enabled']) && allowedTo('calendar_view'))
	{
		// Retrieve the calendar data (events, birthdays, holidays).
		$eventOptions = array(
			'include_holidays' => Config::$modSettings['cal_showholidays'] > 1,
			'include_birthdays' => Config::$modSettings['cal_showbdays'] > 1,
			'include_events' => Config::$modSettings['cal_showevents'] > 1,
			'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
		);
		Utils::$context += CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

		// Whether one or multiple days are shown on the board index.
		Utils::$context['calendar_only_today'] = Config::$modSettings['cal_days_for_index'] == 1;

		// This is used to show the "how-do-I-edit" help.
		Utils::$context['calendar_can_edit'] = allowedTo('calendar_edit_any');

		if (!empty(Utils::$context['show_calendar']))
			Utils::$context['info_center'][] = array(
				'tpl' => 'calendar',
				'txt' => Utils::$context['calendar_only_today'] ? 'calendar_today' : 'calendar_upcoming',
			);
	}

	// And stats.
	Utils::$context['show_stats'] = allowedTo('view_stats') && !empty(Config::$modSettings['trackStats']);
	if ($settings['show_stats_index'])
		Utils::$context['info_center'][] = array(
			'tpl' => 'stats',
			'txt' => 'forum_stats',
		);

	// Now the online stuff
	require_once(Config::$sourcedir . '/Subs-MembersOnline.php');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	Utils::$context += getMembersOnlineStats($membersOnlineOptions);
	Utils::$context['show_buddies'] = !empty(User::$me->buddies);
	Utils::$context['show_who'] = allowedTo('who_view') && !empty(Config::$modSettings['who_enabled']);
	Utils::$context['info_center'][] = array(
		'tpl' => 'online',
		'txt' => 'online_users',
	);

	// Track most online statistics? (Subs-MembersOnline.php)
	if (!empty(Config::$modSettings['trackStats']))
		trackStatsUsersOnline(Utils::$context['num_guests'] + Utils::$context['num_users_online']);

	// Are we showing all membergroups on the board index?
	if (!empty($settings['show_group_key']))
		Utils::$context['membergroups'] = CacheApi::quickGet('membergroup_list', 'Subs-Membergroups.php', 'cache_getMembergroupList', array());

	// And back to normality.
	Utils::$context['page_title'] = sprintf(Lang::$txt['forum_index'], Utils::$context['forum_name']);

	// Mark read button
	Utils::$context['mark_read_button'] = array(
		'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=markasread;sa=all;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']),
	);

	// Replace the collapse and expand default alts.
	addJavaScriptVar('smf_expandAlt', Lang::$txt['show_category'], true);
	addJavaScriptVar('smf_collapseAlt', Lang::$txt['hide_category'], true);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_mark_read_button');

	if (!empty($settings['show_newsfader']))
	{
		loadJavaScriptFile('slippry.min.js', array(), 'smf_jquery_slippry');
		loadCSSFile('slider.min.css', array(), 'smf_jquery_slider');
	}
}

?>