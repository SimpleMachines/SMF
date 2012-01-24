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

/*	This file currently only holds the function for showing a list of online
	users used by the board index and SSI. In the future it'll also contain
	functions used by the Who's online page.

	array getMembersOnlineStats(array membersOnlineOptions)
		- retrieve a list and several other statistics of the users currently
		  online on the forum.
		- used by the board index and SSI.
		- also returns the membergroups of the users that are currently online.
		- (optionally) hides members that chose to hide their online presense.
*/

// Retrieve a list and several other statistics of the users currently online.
function getMembersOnlineStats($membersOnlineOptions)
{
	global $smcFunc, $context, $scripturl, $user_info, $modSettings, $txt;

	// The list can be sorted in several ways.
	$allowed_sort_options = array(
		'log_time',
		'real_name',
		'show_online',
		'online_color',
		'group_name',
	);
	// Default the sorting method to 'most recent online members first'.
	if (!isset($membersOnlineOptions['sort']))
	{
		$membersOnlineOptions['sort'] = 'log_time';
		$membersOnlineOptions['reverse_sort'] = true;
	}

	// Not allowed sort method? Bang! Error!
	elseif (!in_array($membersOnlineOptions['sort'], $allowed_sort_options))
		trigger_error('Sort method for getMembersOnlineStats() function is not allowed', E_USER_NOTICE);

	// Initialize the array that'll be returned later on.
	$membersOnlineStats = array(
		'users_online' => array(),
		'list_users_online' => array(),
		'online_groups' => array(),
		'num_guests' => 0,
		'num_spiders' => 0,
		'num_buddies' => 0,
		'num_users_hidden' => 0,
		'num_users_online' => 0,
	);

	// Get any spiders if enabled.
	$spiders = array();
	$spider_finds = array();
	if (!empty($modSettings['show_spider_online']) && ($modSettings['show_spider_online'] < 3 || allowedTo('admin_forum')) && !empty($modSettings['spider_name_cache']))
		$spiders = unserialize($modSettings['spider_name_cache']);

	// Load the users online right now.
	$request = $smcFunc['db_query']('', '
		SELECT
			lo.id_member, lo.log_time, lo.id_spider, mem.real_name, mem.member_name, mem.show_online,
			mg.online_color, mg.id_group, mg.group_name
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_mem_group} THEN mem.id_post_group ELSE mem.id_group END)',
		array(
			'reg_mem_group' => 0,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($row['real_name']))
		{
			// Do we think it's a spider?
			if ($row['id_spider'] && isset($spiders[$row['id_spider']]))
			{
				$spider_finds[$row['id_spider']] = isset($spider_finds[$row['id_spider']]) ? $spider_finds[$row['id_spider']] + 1 : 1;
				$membersOnlineStats['num_spiders']++;
			}
			// Guests are only nice for statistics.
			$membersOnlineStats['num_guests']++;

			continue;
		}

		elseif (empty($row['show_online']) && empty($membersOnlineOptions['show_hidden']))
		{
			// Just increase the stats and don't add this hidden user to any list.
			$membersOnlineStats['num_users_hidden']++;
			continue;
		}

		// Some basic color coding...
		if (!empty($row['online_color']))
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
		else
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

		// Buddies get counted and highlighted.
		$is_buddy = in_array($row['id_member'], $user_info['buddies']);
		if ($is_buddy)
		{
			$membersOnlineStats['num_buddies']++;
			$link = '<strong>' . $link . '</strong>';
		}

		// A lot of useful information for each member.
		$membersOnlineStats['users_online'][$row[$membersOnlineOptions['sort']] . $row['member_name']] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'group' => $row['id_group'],
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => $link,
			'is_buddy' => $is_buddy,
			'hidden' => empty($row['show_online']),
			'is_last' => false,
		);

		// This is the compact version, simply implode it to show.
		$membersOnlineStats['list_users_online'][$row[$membersOnlineOptions['sort']] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;

		// Store all distinct (primary) membergroups that are shown.
		if (!isset($membersOnlineStats['online_groups'][$row['id_group']]))
			$membersOnlineStats['online_groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'color' => $row['online_color']
			);
	}
	$smcFunc['db_free_result']($request);

	// If there are spiders only and we're showing the detail, add them to the online list - at the bottom.
	if (!empty($spider_finds) && $modSettings['show_spider_online'] > 1)
		foreach ($spider_finds as $id => $count)
		{
			$link = $spiders[$id] . ($count > 1 ? ' (' . $count . ')' : '');
			$sort = $membersOnlineOptions['sort'] = 'log_time' && $membersOnlineOptions['reverse_sort'] ? 0 : 'zzz_';
			$membersOnlineStats['users_online'][$sort . $spiders[$id]] = array(
				'id' => 0,
				'username' => $spiders[$id],
				'name' => $link,
				'group' => $txt['spiders'],
				'href' => '',
				'link' => $link,
				'is_buddy' => false,
				'hidden' => false,
				'is_last' => false,
			);
			$membersOnlineStats['list_users_online'][$sort . $spiders[$id]] = $link;
		}

	// Time to sort the list a bit.
	if (!empty($membersOnlineStats['users_online']))
	{
		// Determine the sort direction.
		$sortFunction = empty($membersOnlineOptions['reverse_sort']) ? 'ksort' : 'krsort';

		// Sort the two lists.
		$sortFunction($membersOnlineStats['users_online']);
		$sortFunction($membersOnlineStats['list_users_online']);

		// Mark the last list item as 'is_last'.
		$userKeys = array_keys($membersOnlineStats['users_online']);
		$membersOnlineStats['users_online'][end($userKeys)]['is_last'] = true;
	}

	// Also sort the membergroups.
	ksort($membersOnlineStats['online_groups']);

	// Hidden and non-hidden members make up all online members.
	$membersOnlineStats['num_users_online'] = count($membersOnlineStats['users_online']) + $membersOnlineStats['num_users_hidden'] - (isset($modSettings['show_spider_online']) && $modSettings['show_spider_online'] > 1 ? count($spider_finds) : 0);

	return $membersOnlineStats;
}

// Check if the number of users online is a record and store it.
function trackStatsUsersOnline($total_users_online)
{
	global $modSettings, $smcFunc;

	$settingsToUpdate = array();

	// More members on now than ever were?  Update it!
	if (!isset($modSettings['mostOnline']) || $total_users_online >= $modSettings['mostOnline'])
		$settingsToUpdate = array(
			'mostOnline' => $total_users_online,
			'mostDate' => time()
		);

	$date = strftime('%Y-%m-%d', forum_time(false));

	// No entry exists for today yet?
	if (!isset($modSettings['mostOnlineUpdated']) || $modSettings['mostOnlineUpdated'] != $date)
	{
		$request = $smcFunc['db_query']('', '
			SELECT most_on
			FROM {db_prefix}log_activity
			WHERE date = {date:date}
			LIMIT 1',
			array(
				'date' => $date,
			)
		);

		// The log_activity hasn't got an entry for today?
		if ($smcFunc['db_num_rows']($request) === 0)
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_activity',
				array('date' => 'date', 'most_on' => 'int'),
				array($date, $total_users_online),
				array('date')
			);
		}
		// There's an entry in log_activity on today...
		else
		{
			list ($modSettings['mostOnlineToday']) = $smcFunc['db_fetch_row']($request);

			if ($total_users_online > $modSettings['mostOnlineToday'])
				trackStats(array('most_on' => $total_users_online));

			$total_users_online = max($total_users_online, $modSettings['mostOnlineToday']);
		}
		$smcFunc['db_free_result']($request);

		$settingsToUpdate['mostOnlineUpdated'] = $date;
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}

	// Highest number of users online today?
	elseif ($total_users_online > $modSettings['mostOnlineToday'])
	{
		trackStats(array('most_on' => $total_users_online));
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}

	if (!empty($settingsToUpdate))
		updateSettings($settingsToUpdate);
}

?>