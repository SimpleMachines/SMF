<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Update some basic statistics.
 *
 * 'member' statistic updates the latest member, the total member
 *  count, and the number of unapproved members.
 * 'member' also only counts approved members when approval is on, but
 *  is much more efficient with it off.
 *
 * 'message' changes the total number of messages, and the
 *  highest message id by id_msg - which can be parameters 1 and 2,
 *  respectively.
 *
 * 'topic' updates the total number of topics, or if parameter1 is true
 *  simply increments them.
 *
 * 'subject' updates the log_search_subjects in the event of a topic being
 *  moved, removed or split.  parameter1 is the topicid, parameter2 is the new subject
 *
 * 'postgroups' case updates those members who match condition's
 *  post-based membergroups in the database (restricted by parameter1).
 *
 * @param string $type Stat type - can be 'member', 'message', 'topic', 'subject' or 'postgroups'
 * @param mixed $parameter1 = null
 * @param mixed $parameter2 = null
 */
function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	global $modSettings, $smcFunc;

	switch ($type)
	{
	case 'member':
		$changes = array(
			'memberlist_updated' => time(),
		);

		// #1 latest member ID, #2 the real name for a new registration.
		if (is_numeric($parameter1))
		{
			$changes['latestMember'] = $parameter1;
			$changes['latestRealName'] = $parameter2;

			updateSettings(array('totalMembers' => true), true);
		}

		// We need to calculate the totals.
		else
		{
			// Update the latest activated member (highest id_member) and count.
			$result = $smcFunc['db_query']('', '
				SELECT COUNT(*), MAX(id_member)
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}',
				array(
					'is_activated' => 1,
				)
			);
			list ($changes['totalMembers'], $changes['latestMember']) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			// Get the latest activated member's display name.
			$result = $smcFunc['db_query']('', '
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => (int) $changes['latestMember'],
				)
			);
			list ($changes['latestRealName']) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			if (!empty($modSettings['registration_method']))
			{
				// Are we using registration approval?
				if ($modSettings['registration_method'] == 2 || !empty($modSettings['approveAccountDeletion']))
				{
					// Update the amount of members awaiting approval
					$result = $smcFunc['db_query']('', '
						SELECT COUNT(*)
						FROM {db_prefix}members
						WHERE is_activated IN ({array_int:activation_status})',
						array(
							'activation_status' => array(3, 4),
						)
					);
					list ($changes['unapprovedMembers']) = $smcFunc['db_fetch_row']($result);
					$smcFunc['db_free_result']($result);
				}

				// What about unapproved COPPA registrations?
				if (!empty($modSettings['coppaType']) && $modSettings['coppaType'] != 1)
				{
					$result = $smcFunc['db_query']('', '
						SELECT COUNT(*)
						FROM {db_prefix}members
						WHERE is_activated = {int:coppa_approval}',
						array(
							'coppa_approval' => 5,
						)
					);
					list ($coppa_approvals) = $smcFunc['db_fetch_row']($result);
					$smcFunc['db_free_result']($result);

					// Add this to the number of unapproved members
					if (!empty($changes['unapprovedMembers']))
						$changes['unapprovedMembers'] += $coppa_approvals;
					else
						$changes['unapprovedMembers'] = $coppa_approvals;
				}
			}
		}
		updateSettings($changes);
		break;

	case 'message':
		if ($parameter1 === true && $parameter2 !== null)
			updateSettings(array('totalMessages' => true, 'maxMsgID' => $parameter2), true);
		else
		{
			// SUM and MAX on a smaller table is better for InnoDB tables.
			$result = $smcFunc['db_query']('', '
				SELECT SUM(num_posts + unapproved_posts) AS total_messages, MAX(id_last_msg) AS max_msg_id
				FROM {db_prefix}boards
				WHERE redirect = {string:blank_redirect}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => isset($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
					'blank_redirect' => '',
				)
			);
			$row = $smcFunc['db_fetch_assoc']($result);
			$smcFunc['db_free_result']($result);

			updateSettings(array(
				'totalMessages' => $row['total_messages'] === null ? 0 : $row['total_messages'],
				'maxMsgID' => $row['max_msg_id'] === null ? 0 : $row['max_msg_id']
			));
		}
		break;

	case 'subject':
		// Remove the previous subject (if any).
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic = {int:id_topic}',
			array(
				'id_topic' => (int) $parameter1,
			)
		);

		// Insert the new subject.
		if ($parameter2 !== null)
		{
			$parameter1 = (int) $parameter1;
			$parameter2 = text2words($parameter2);

			$inserts = array();
			foreach ($parameter2 as $word)
				$inserts[] = array($word, $parameter1);

			if (!empty($inserts))
				$smcFunc['db_insert']('ignore',
					'{db_prefix}log_search_subjects',
					array('word' => 'string', 'id_topic' => 'int'),
					$inserts,
					array('word', 'id_topic')
				);
		}
		break;

	case 'topic':
		if ($parameter1 === true)
			updateSettings(array('totalTopics' => true), true);
		else
		{
			// Get the number of topics - a SUM is better for InnoDB tables.
			// We also ignore the recycle bin here because there will probably be a bunch of one-post topics there.
			$result = $smcFunc['db_query']('', '
				SELECT SUM(num_topics + unapproved_topics) AS total_topics
				FROM {db_prefix}boards' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				WHERE id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				)
			);
			$row = $smcFunc['db_fetch_assoc']($result);
			$smcFunc['db_free_result']($result);

			updateSettings(array('totalTopics' => $row['total_topics'] === null ? 0 : $row['total_topics']));
		}
		break;

	case 'postgroups':
		// Parameter two is the updated columns: we should check to see if we base groups off any of these.
		if ($parameter2 !== null && !in_array('posts', $parameter2))
			return;

		$postgroups = cache_get_data('updateStats:postgroups', 360);
		if ($postgroups == null || $parameter1 == null)
		{
			// Fetch the postgroups!
			$request = $smcFunc['db_query']('', '
				SELECT id_group, min_posts
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			$postgroups = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$postgroups[$row['id_group']] = $row['min_posts'];
			$smcFunc['db_free_result']($request);

			// Sort them this way because if it's done with MySQL it causes a filesort :(.
			arsort($postgroups);

			cache_put_data('updateStats:postgroups', $postgroups, 360);
		}

		// Oh great, they've screwed their post groups.
		if (empty($postgroups))
			return;

		// Set all membergroups from most posts to least posts.
		$conditions = '';
		$lastMin = 0;
		foreach ($postgroups as $id => $min_posts)
		{
			$conditions .= '
					WHEN posts >= ' . $min_posts . (!empty($lastMin) ? ' AND posts <= ' . $lastMin : '') . ' THEN ' . $id;
			$lastMin = $min_posts;
		}

		// A big fat CASE WHEN... END is faster than a zillion UPDATE's ;).
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET id_post_group = CASE ' . $conditions . '
					ELSE 0
				END' . ($parameter1 != null ? '
			WHERE ' . (is_array($parameter1) ? 'id_member IN ({array_int:members})' : 'id_member = {int:members}') : ''),
			array(
				'members' => $parameter1,
			)
		);
		break;

		default:
			trigger_error('updateStats(): Invalid statistic type \'' . $type . '\'', E_USER_NOTICE);
	}
}

/**
 * Updates the columns in the members table.
 * Assumes the data has been htmlspecialchar'd.
 * this function should be used whenever member data needs to be
 * updated in place of an UPDATE query.
 *
 * id_member is either an int or an array of ints to be updated.
 *
 * data is an associative array of the columns to be updated and their respective values.
 * any string values updated should be quoted and slashed.
 *
 * the value of any column can be '+' or '-', which mean 'increment'
 * and decrement, respectively.
 *
 * if the member's post number is updated, updates their post groups.
 *
 * @param mixed $members An array of integers
 * @param array $data
 */
function updateMemberData($members, $data)
{
	global $modSettings, $user_info, $smcFunc;

	$parameters = array();
	if (is_array($members))
	{
		$condition = 'id_member IN ({array_int:members})';
		$parameters['members'] = $members;
	}
	elseif ($members === null)
		$condition = '1=1';
	else
	{
		$condition = 'id_member = {int:member}';
		$parameters['member'] = $members;
	}

	// Everything is assumed to be a string unless it's in the below.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'new_pm', 'pm_prefs', 'gender', 'show_online', 'pm_receive_from',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types', 'alerts',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);

	if (!empty($modSettings['integrate_change_member_data']))
	{
		// Only a few member variables are really interesting for integration.
		$integration_vars = array(
			'member_name',
			'real_name',
			'email_address',
			'id_group',
			'gender',
			'birthdate',
			'website_title',
			'website_url',
			'location',
			'time_format',
			'time_offset',
			'avatar',
			'lngfile',
		);
		$vars_to_integrate = array_intersect($integration_vars, array_keys($data));

		// Only proceed if there are any variables left to call the integration function.
		if (count($vars_to_integrate) != 0)
		{
			// Fetch a list of member_names if necessary
			if ((!is_array($members) && $members === $user_info['id']) || (is_array($members) && count($members) == 1 && in_array($user_info['id'], $members)))
				$member_names = array($user_info['username']);
			else
			{
				$member_names = array();
				$request = $smcFunc['db_query']('', '
					SELECT member_name
					FROM {db_prefix}members
					WHERE ' . $condition,
					$parameters
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$member_names[] = $row['member_name'];
				$smcFunc['db_free_result']($request);
			}

			if (!empty($member_names))
				foreach ($vars_to_integrate as $var)
					call_integration_hook('integrate_change_member_data', array($member_names, $var, &$data[$var], &$knownInts, &$knownFloats));
		}
	}

	$setString = '';
	foreach ($data as $var => $val)
	{
		$type = 'string';
		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif ($var == 'birthdate')
			$type = 'date';

		// Doing an increment?
		if ($type == 'int' && ($val === '+' || $val === '-'))
		{
			$val = $var . ' ' . $val . ' 1';
			$type = 'raw';
		}

		// Ensure posts, instant_messages, and unread_messages don't overflow or underflow.
		if (in_array($var, array('posts', 'instant_messages', 'unread_messages')))
		{
			if (preg_match('~^' . $var . ' (\+ |- |\+ -)([\d]+)~', $val, $match))
			{
				if ($match[1] != '+ ')
					$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
				$type = 'raw';
			}
		}

		$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
		$parameters['p_' . $var] = $val;
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET' . substr($setString, 0, -1) . '
		WHERE ' . $condition,
		$parameters
	);

	updateStats('postgroups', $members, array_keys($data));

	// Clear any caching?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($modSettings['cache_enable'] >= 3)
			{
				cache_put_data('member_data-profile-' . $member, null, 120);
				cache_put_data('member_data-normal-' . $member, null, 120);
				cache_put_data('member_data-minimal-' . $member, null, 120);
			}
			cache_put_data('user_settings-' . $member, null, 60);
		}
	}
}

/**
 * Updates the settings table as well as $modSettings... only does one at a time if $update is true.
 *
 * - updates both the settings table and $modSettings array.
 * - all of changeArray's indexes and values are assumed to have escaped apostrophes (')!
 * - if a variable is already set to what you want to change it to, that
 *   variable will be skipped over; it would be unnecessary to reset.
 * - When use_update is true, UPDATEs will be used instead of REPLACE.
 * - when use_update is true, the value can be true or false to increment
 *  or decrement it, respectively.
 *
 * @param array $changeArray
 * @param bool $update = false
 */
function updateSettings($changeArray, $update = false)
{
	global $modSettings, $smcFunc;

	if (empty($changeArray) || !is_array($changeArray))
		return;

	$toRemove = array();

	// Go check if there is any setting to be removed.
	foreach ($changeArray as $k => $v)
		if ($v === null)
		{
			// Found some, remove them from the original array and add them to ours.
			unset($changeArray[$k]);
			$toRemove[] = $k;
		}

	// Proceed with the deletion.
	if (!empty($toRemove))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:remove})',
			array(
				'remove' => $toRemove,
			)
		);

	// In some cases, this may be better and faster, but for large sets we don't want so many UPDATEs.
	if ($update)
	{
		foreach ($changeArray as $variable => $value)
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}settings
				SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
				WHERE variable = {string:variable}',
				array(
					'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
					'variable' => $variable,
				)
			);
			$modSettings[$variable] = $value === true ? $modSettings[$variable] + 1 : ($value === false ? $modSettings[$variable] - 1 : $value);
		}

		// Clean out the cache and make sure the cobwebs are gone too.
		cache_put_data('modSettings', null, 90);

		return;
	}

	$replaceArray = array();
	foreach ($changeArray as $variable => $value)
	{
		// Don't bother if it's already like that ;).
		if (isset($modSettings[$variable]) && $modSettings[$variable] == $value)
			continue;
		// If the variable isn't set, but would only be set to nothing'ness, then don't bother setting it.
		elseif (!isset($modSettings[$variable]) && empty($value))
			continue;

		$replaceArray[] = array($variable, $value);

		$modSettings[$variable] = $value;
	}

	if (empty($replaceArray))
		return;

	$smcFunc['db_insert']('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		$replaceArray,
		array('variable')
	);

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	cache_put_data('modSettings', null, 90);
}

/**
 * Constructs a page list.
 *
 * - builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 * - flexible_start causes it to use "url.page" instead of "url;start=page".
 * - handles any wireless settings (adding special things to URLs.)
 * - very importantly, cleans up the start value passed, and forces it to
 *   be a multiple of num_per_page.
 * - checks that start is not more than max_value.
 * - base_url should be the URL without any start parameter on it.
 * - uses the compactTopicPagesEnable and compactTopicPagesContiguous
 *   settings to decide how to display the menu.
 *
 * an example is available near the function definition.
 * $pageindex = constructPageIndex($scripturl . '?board=' . $board, $_REQUEST['start'], $num_messages, $maxindex, true);
 *
 * @param string $base_url The basic URL to be used for each link.
 * @param int &$start The start position, by reference. If this is not a multiple of the number of items per page, it is sanitized to be so and the value will persist upon the function's return.
 * @param int $max_value The total number of items you are paginating for.
 * @param int $num_per_page The number of items to be displayed on a given page. $start will be forced to be a multiple of this value.
 * @param bool $flexible_start Whether a ;start=x component should be introduced into the URL automatically (see above)
 * @param bool $show_prevnext Whether the Previous and Next links should be shown (should be on only when navigating the list)
 *
 * @return string The complete HTML of the page index that was requested, formatted by the template.
 */
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show_prevnext = true)
{
	global $modSettings, $context, $smcFunc, $settings, $txt;

	// Save whether $start was less than 0 or not.
	$start = (int) $start;
	$start_invalid = $start < 0;

	// Make sure $start is a proper variable - not less than 0.
	if ($start_invalid)
		$start = 0;
	// Not greater than the upper bound.
	elseif ($start >= $max_value)
		$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));
	// And it has to be a multiple of $num_per_page!
	else
		$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

	$context['current_page'] = $start / $num_per_page;

	// Wireless will need the protocol on the URL somewhere.
	if (WIRELESS)
		$base_url .= ';' . WIRELESS_PROTOCOL;

	// Define some default page index settings if we don't already have it...
	if (!isset($settings['page_index']))
	{
		// This defines the formatting for the page indexes used throughout the forum.
		$settings['page_index'] = array(
			'extra_before' => '<span class="pages">' . $txt['pages'] . ': </span>',
			'previous_page' => '<span class="generic_icons previous_page"></span>',
			'current_page' => '<span class="current_page">[%1$d]</span> ',
			'page' => '<a class="navPages" href="{URL}">%2$s</a> ',
			'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
			'next_page' => '<span class="generic_icons next_page"></span>',
			'extra_after' => '',
		);
	}

	$base_link = strtr($settings['page_index']['page'], array('{URL}' => $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d'));
	$pageindex = $settings['page_index']['extra_before'];

	// Compact pages is off or on?
	if (empty($modSettings['compactTopicPagesEnable']))
	{
		// Show the left arrow.
		$pageindex .= $start == 0 ? ' ' : sprintf($base_link, $start - $num_per_page, $settings['page_index']['previous_page']);

		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? sprintf($settings['page_index']['current_page'], $display_page++) : sprintf($base_link, $counter, $display_page++);

		// Show the right arrow.
		$display_page = ($start + $num_per_page) > $max_value ? $max_value : ($start + $num_per_page);
		if ($start != $counter - $max_value && !$start_invalid)
			$pageindex .= $display_page > $counter - $num_per_page ? ' ' : sprintf($base_link, $display_page, $settings['page_index']['next_page']);
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$PageContiguous = (int) ($modSettings['compactTopicPagesContiguous'] - ($modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Show the "prev page" link. (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
		if (!empty($start) && $show_prevnext)
			$pageindex .= sprintf($base_link, $start - $num_per_page, $settings['page_index']['previous_page']);
		else
			$pageindex .= '';

		// Show the first page. (prev page >1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $PageContiguous)
			$pageindex .= sprintf($base_link, 0, '1');

		// Show the ... after the first page.  (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
		if ($start > $num_per_page * ($PageContiguous + 1))
			$pageindex .= strtr($settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape($smcFunc['htmlspecialchars']($base_link)),
				'{FIRST_PAGE}' => $num_per_page,
				'{LAST_PAGE}' => $start - $num_per_page * $PageContiguous,
				'{PER_PAGE}' => $num_per_page,
			));

		// Show the pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
		for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
			if ($start >= $num_per_page * $nCont)
			{
				$tmpStart = $start - $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
		if (!$start_invalid)
			$pageindex .= sprintf($settings['page_index']['current_page'], $start / $num_per_page + 1);
		else
			$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

		// Show the pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
		$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
		for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
			if ($start + $num_per_page * $nCont <= $tmpMaxPages)
			{
				$tmpStart = $start + $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the '...' part near the end. (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
		if ($start + $num_per_page * ($PageContiguous + 1) < $tmpMaxPages)
			$pageindex .= strtr($settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape($smcFunc['htmlspecialchars']($base_link)),
				'{FIRST_PAGE}' => $start + $num_per_page * ($PageContiguous + 1),
				'{LAST_PAGE}' => $tmpMaxPages,
				'{PER_PAGE}' => $num_per_page,
			));

		// Show the last number in the list. (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
		if ($start + $num_per_page * $PageContiguous < $tmpMaxPages)
			$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);

		// Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
		if ($start != $tmpMaxPages && $show_prevnext)
			$pageindex .= sprintf($base_link, $start + $num_per_page, $settings['page_index']['next_page']);
	}
	$pageindex .= $settings['page_index']['extra_after'];

	return $pageindex;
}

/**
 * - Formats a number.
 * - uses the format of number_format to decide how to format the number.
 *   for example, it might display "1 234,50".
 * - caches the formatting data from the setting for optimization.
 *
 * @param float $number
 * @param bool $override_decimal_count = false
 */
function comma_format($number, $override_decimal_count = false)
{
	global $txt;
	static $thousands_separator = null, $decimal_separator = null, $decimal_count = null;

	// Cache these values...
	if ($decimal_separator === null)
	{
		// Not set for whatever reason?
		if (empty($txt['number_format']) || preg_match('~^1([^\d]*)?234([^\d]*)(0*?)$~', $txt['number_format'], $matches) != 1)
			return $number;

		// Cache these each load...
		$thousands_separator = $matches[1];
		$decimal_separator = $matches[2];
		$decimal_count = strlen($matches[3]);
	}

	// Format the string with our friend, number_format.
	return number_format($number, (float) $number === $number ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}

/**
 * Format a time to make it look purdy.
 *
 * - returns a pretty formatted version of time based on the user's format in $user_info['time_format'].
 * - applies all necessary time offsets to the timestamp, unless offset_type is set.
 * - if todayMod is set and show_today was not not specified or true, an
 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
 * - performs localization (more than just strftime would do alone.)
 *
 * @param int $log_time
 * @param bool $show_today = true
 * @param string $offset_type = false
 */
function timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $context, $user_info, $txt, $modSettings;
	static $non_twelve_hour;

	// Offset the time.
	if (!$offset_type)
		$time = $log_time + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
	// Just the forum offset?
	elseif ($offset_type == 'forum')
		$time = $log_time + $modSettings['time_offset'] * 3600;
	else
		$time = $log_time;

	// We can't have a negative date (on Windows, at least.)
	if ($log_time < 0)
		$log_time = 0;

	// Today and Yesterday?
	if ($modSettings['todayMod'] >= 1 && $show_today === true)
	{
		// Get the current time.
		$nowtime = forum_time();

		$then = @getdate($time);
		$now = @getdate($nowtime);

		// Try to make something of a time format string...
		$s = strpos($user_info['time_format'], '%S') === false ? '' : ':%S';
		if (strpos($user_info['time_format'], '%H') === false && strpos($user_info['time_format'], '%T') === false)
		{
			$h = strpos($user_info['time_format'], '%l') === false ? '%I' : '%l';
			$today_fmt = $h . ':%M' . $s . ' %p';
		}
		else
			$today_fmt = '%H:%M' . $s;

		// Same day of the year, same year.... Today!
		if ($then['yday'] == $now['yday'] && $then['year'] == $now['year'])
			return $txt['today'] . timeformat($log_time, $today_fmt, $offset_type);

		// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
		if ($modSettings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
			return $txt['yesterday'] . timeformat($log_time, $today_fmt, $offset_type);
	}

	$str = !is_bool($show_today) ? $show_today : $user_info['time_format'];

	if (setlocale(LC_TIME, $txt['lang_locale']))
	{
		if (!isset($non_twelve_hour))
			$non_twelve_hour = trim(strftime('%p')) === '';
		if ($non_twelve_hour && strpos($str, '%p') !== false)
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);

		foreach (array('%a', '%A', '%b', '%B') as $token)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, strftime($token, $time), $str);
	}
	else
	{
		// Do-it-yourself time localization.  Fun.
		foreach (array('%a' => 'days_short', '%A' => 'days', '%b' => 'months_short', '%B' => 'months') as $token => $text_label)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, $txt[$text_label][(int) strftime($token === '%a' || $token === '%A' ? '%w' : '%m', $time)], $str);

		if (strpos($str, '%p') !== false)
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);
	}

	// Windows doesn't support %e; on some versions, strftime fails altogether if used, so let's prevent that.
	if ($context['server']['is_windows'] && strpos($str, '%e') !== false)
		$str = str_replace('%e', ltrim(strftime('%d', $time), '0'), $str);

	// Format any other characters..
	return strftime($str, $time);
}

/**
 * Removes special entities from strings.  Compatibility...
 * Should be used instead of html_entity_decode for PHP version compatibility reasons.
 *
 * - removes the base entities (&lt;, &quot;, etc.) from text.
 * - additionally converts &nbsp; and &#039;.
 *
 * @param string $string
 * @return the string without entities
 */
function un_htmlspecialchars($string)
{
	global $context;
	static $translation = array();

	// Determine the character set... Default to UTF-8
	if (empty($context['character_set']))
		$charset = 'UTF-8';
	// Use ISO-8859-1 in place of non-suppported ISO-8859 charsets...
	elseif (strpos($context['character_set'], 'ISO-8859-') !== false && !in_array($context['character_set'], array('ISO-8859-5', 'ISO-8859-15')))
		$charset = 'ISO-8859-1';
	else
		$charset = $context['character_set'];

	if (empty($translation))
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES, $charset)) + array('&#039;' => '\'', '&#39;' => '\'', '&nbsp;' => ' ');

	return strtr($string, $translation);
}

/**
 * Shorten a subject + internationalization concerns.
 *
 * - shortens a subject so that it is either shorter than length, or that length plus an ellipsis.
 * - respects internationalization characters and entities as one character.
 * - avoids trailing entities.
 * - returns the shortened string.
 *
 * @param string $subject
 * @param int $len
 */
function shorten_subject($subject, $len)
{
	global $smcFunc;

	// It was already short enough!
	if ($smcFunc['strlen']($subject) <= $len)
		return $subject;

	// Shorten it by the length it was too long, and strip off junk from the end.
	return $smcFunc['substr']($subject, 0, $len) . '...';
}

/**
 * Gets the current time with offset.
 *
 * - always applies the offset in the time_offset setting.
 *
 * @param bool $use_user_offset = true if use_user_offset is true, applies the user's offset as well
 * @param int $timestamp = null
 * @return int seconds since the unix epoch
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	global $user_info, $modSettings;

	if ($timestamp === null)
		$timestamp = time();
	elseif ($timestamp == 0)
		return 0;

	return $timestamp + ($modSettings['time_offset'] + ($use_user_offset ? $user_info['time_offset'] : 0)) * 3600;
}

/**
 * Calculates all the possible permutations (orders) of array.
 * should not be called on huge arrays (bigger than like 10 elements.)
 * returns an array containing each permutation.
 *
 * @param array $array
 * @return array
 */
function permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Parse bulletin board code in a string, as well as smileys optionally.
 *
 * - only parses bbc tags which are not disabled in disabledBBC.
 * - handles basic HTML, if enablePostHTML is on.
 * - caches the from/to replace regular expressions so as not to reload them every time a string is parsed.
 * - only parses smileys if smileys is true.
 * - does nothing if the enableBBC setting is off.
 * - uses the cache_id as a unique identifier to facilitate any caching it may do.
 *  -returns the modified message.
 *
 * @param string $message
 * @param bool $smileys = true
 * @param string $cache_id = ''
 * @param array $parse_tags = null
 * @return string
 */
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
	global $txt, $scripturl, $context, $modSettings, $user_info;
	static $bbc_codes = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $disabled;

	// Don't waste cycles
	if ($message === '')
		return '';

	// Just in case it wasn't determined yet whether UTF-8 is enabled.
	if (!isset($context['utf8']))
		$context['utf8'] = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8';

	// Clean up any cut/paste issues we may have
	$message = sanitizeMSCutPaste($message);

	// If the load average is too high, don't parse the BBC.
	if (!empty($context['load_average']) && !empty($modSettings['bbc']) && $context['load_average'] >= $modSettings['bbc'])
	{
		$context['disabled_parse_bbc'] = true;
		return $message;
	}

	// Never show smileys for wireless clients.  More bytes, can't see it anyway :P.
	if (WIRELESS)
		$smileys = false;
	elseif ($smileys !== null && ($smileys == '1' || $smileys == '0'))
		$smileys = (bool) $smileys;

	if (empty($modSettings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	// If we are not doing every tag then we don't cache this run.
	if (!empty($parse_tags) && !empty($bbc_codes))
	{
		$temp_bbc = $bbc_codes;
		$bbc_codes = array();
	}

	// Allow mods access before entering the main parse_bbc loop
	call_integration_hook('integrate_pre_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Sift out the bbc for a performance improvement.
	if (empty($bbc_codes) || $message === false || !empty($parse_tags))
	{
		if (!empty($modSettings['disabledBBC']))
		{
			$temp = explode(',', strtolower($modSettings['disabledBBC']));

			foreach ($temp as $tag)
				$disabled[trim($tag)] = true;
		}

		if (empty($modSettings['enableEmbeddedFlash']))
			$disabled['flash'] = true;

		/* The following bbc are formatted as an array, with keys as follows:

			tag: the tag's name - should be lowercase!

			type: one of...
				- (missing): [tag]parsed content[/tag]
				- unparsed_equals: [tag=xyz]parsed content[/tag]
				- parsed_equals: [tag=parsed data]parsed content[/tag]
				- unparsed_content: [tag]unparsed content[/tag]
				- closed: [tag], [tag/], [tag /]
				- unparsed_commas: [tag=1,2,3]parsed content[/tag]
				- unparsed_commas_content: [tag=1,2,3]unparsed content[/tag]
				- unparsed_equals_content: [tag=...]unparsed content[/tag]

			parameters: an optional array of parameters, for the form
			  [tag abc=123]content[/tag].  The array is an associative array
			  where the keys are the parameter names, and the values are an
			  array which may contain the following:
				- match: a regular expression to validate and match the value.
				- quoted: true if the value should be quoted.
				- validate: callback to evaluate on the data, which is $data.
				- value: a string in which to replace $1 with the data.
				  either it or validate may be used, not both.
				- optional: true if the parameter is optional.

			test: a regular expression to test immediately after the tag's
			  '=', ' ' or ']'.  Typically, should have a \] at the end.
			  Optional.

			content: only available for unparsed_content, closed,
			  unparsed_commas_content, and unparsed_equals_content.
			  $1 is replaced with the content of the tag.  Parameters
			  are replaced in the form {param}.  For unparsed_commas_content,
			  $2, $3, ..., $n are replaced.

			before: only when content is not used, to go before any
			  content.  For unparsed_equals, $1 is replaced with the value.
			  For unparsed_commas, $1, $2, ..., $n are replaced.

			after: similar to before in every way, except that it is used
			  when the tag is closed.

			disabled_content: used in place of content when the tag is
			  disabled.  For closed, default is '', otherwise it is '$1' if
			  block_level is false, '<div>$1</div>' elsewise.

			disabled_before: used in place of before when disabled.  Defaults
			  to '<div>' if block_level, '' if not.

			disabled_after: used in place of after when disabled.  Defaults
			  to '</div>' if block_level, '' if not.

			block_level: set to true the tag is a "block level" tag, similar
			  to HTML.  Block level tags cannot be nested inside tags that are
			  not block level, and will not be implicitly closed as easily.
			  One break following a block level tag may also be removed.

			trim: if set, and 'inside' whitespace after the begin tag will be
			  removed.  If set to 'outside', whitespace after the end tag will
			  meet the same fate.

			validate: except when type is missing or 'closed', a callback to
			  validate the data as $data.  Depending on the tag's type, $data
			  may be a string or an array of strings (corresponding to the
			  replacement.)

			quoted: when type is 'unparsed_equals' or 'parsed_equals' only,
			  may be not set, 'optional', or 'required' corresponding to if
			  the content may be quoted.  This allows the parser to read
			  [tag="abc]def[esdf]"] properly.

			require_parents: an array of tag names, or not set.  If set, the
			  enclosing tag *must* be one of the listed tags, or parsing won't
			  occur.

			require_children: similar to require_parents, if set children
			  won't be parsed if they are not in the list.

			disallow_children: similar to, but very different from,
			  require_children, if it is set the listed tags will not be
			  parsed inside the tag.

			parsed_tags_allowed: an array restricting what BBC can be in the
			  parsed_equals parameter, if desired.
		*/

		$codes = array(
			array(
				'tag' => 'abbr',
				'type' => 'unparsed_equals',
				'before' => '<abbr title="$1">',
				'after' => '</abbr>',
				'quoted' => 'optional',
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'acronym',
				'type' => 'unparsed_equals',
				'before' => '<abbr title="$1">',
				'after' => '</abbr>',
				'quoted' => 'optional',
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'anchor',
				'type' => 'unparsed_equals',
				'test' => '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]',
				'before' => '<span id="post_$1">',
				'after' => '</span>',
			),
			array(
				'tag' => 'b',
				'before' => '<span class="bbc_bold">',
				'after' => '</span>',
			),
			array(
				'tag' => 'bdo',
				'type' => 'unparsed_equals',
				'before' => '<bdo dir="$1">',
				'after' => '</bdo>',
				'test' => '(rtl|ltr)\]',
				'block_level' => true,
			),
			array(
				'tag' => 'black',
				'before' => '<span style="color: black;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'blue',
				'before' => '<span class="bbc_color blue">',
				'after' => '</span>',
			),
			array(
				'tag' => 'br',
				'type' => 'closed',
				'content' => '<br>',
			),
			array(
				'tag' => 'center',
				'before' => '<div class="centertext">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_content',
				'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div>' . (isBrowser('gecko') || isBrowser('opera') ? '<pre style="margin: 0; padding: 0;">' : '') . '<code class="bbc_code">$1</code>' . (isBrowser('gecko') || isBrowser('opera') ? '</pre>' : ''),
				// @todo Maybe this can be simplified?
				'validate' => isset($disabled['code']) ? null : function (&$tag, &$data, $disabled) use ($context)
				{
					if (!isset($disabled['code']))
					{
						$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $data, -1, PREG_SPLIT_DELIM_CAPTURE);

						for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
						{
							// Do PHP code coloring?
							if ($php_parts[$php_i] != '&lt;?php')
								continue;

							$php_string = '';
							while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != '?&gt;')
							{
								$php_string .= $php_parts[$php_i];
								$php_parts[$php_i++] = '';
							}
							$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
						}

						// Fix the PHP code stuff...
						$data = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode('', $php_parts));
						$data = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context['browser']['is_opera'])
							$data .= '&nbsp;';
					}
				},
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> ($2) <a href="#" onclick="return smfSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div>' . (isBrowser('gecko') || isBrowser('opera') ? '<pre style="margin: 0; padding: 0;">' : '') . '<code class="bbc_code">$1</code>' . (isBrowser('gecko') || isBrowser('opera') ? '</pre>' : ''),
				// @todo Maybe this can be simplified?
				'validate' => isset($disabled['code']) ? null : function (&$tag, &$data, $disabled) use ($context)
				{
					if (!isset($disabled['code']))
					{
						$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $data[0], -1, PREG_SPLIT_DELIM_CAPTURE);

						for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
						{
							// Do PHP code coloring?
							if ($php_parts[$php_i] != '&lt;?php')
								continue;

							$php_string = '';
							while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != '?&gt;')
							{
								$php_string .= $php_parts[$php_i];
								$php_parts[$php_i++] = '';
							}
							$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
						}

						// Fix the PHP code stuff...
						$data[0] = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode('', $php_parts));
						$data[0] = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data[0]);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context['browser']['is_opera'])
							$data[0] .= '&nbsp;';
					}
				},
				'block_level' => true,
			),
			array(
				'tag' => 'color',
				'type' => 'unparsed_equals',
				'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s?,\s?){2}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\))\]',
				'before' => '<span style="color: $1;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_content',
				'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
				// @todo Should this respect guest_hideContacts?
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, array('<br>' => ''));
				},
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_equals',
				'before' => '<a href="mailto:$1" class="bbc_email">',
				'after' => '</a>',
				// @todo Should this respect guest_hideContacts?
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'flash',
				'type' => 'unparsed_commas_content',
				'test' => '\d+,\d+\]',
				'content' => (isBrowser('ie') ? '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$2" height="$3"><param name="movie" value="$1"><param name="play" value="true"><param name="loop" value="true"><param name="quality" value="high"><param name="AllowScriptAccess" value="never"><embed src="$1" width="$2" height="$3" play="true" loop="true" quality="high" AllowScriptAccess="never"><noembed><a href="$1" target="_blank" class="new_win">$1</a></noembed></object>' : '<embed type="application/x-shockwave-flash" src="$1" width="$2" height="$3" play="true" loop="true" quality="high" AllowScriptAccess="never"><noembed><a href="$1" target="_blank" class="new_win">$1</a></noembed>'),
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (isset($disabled['url']))
						$tag['content'] = '$1';
					elseif (strpos($data[0], 'http://') !== 0 && strpos($data[0], 'https://') !== 0)
						$data[0] = 'http://' . $data[0];
				},
				'disabled_content' => '<a href="$1" target="_blank" class="new_win">$1</a>',
			),
			array(
				'tag' => 'font',
				'type' => 'unparsed_equals',
				'test' => '[A-Za-z0-9_,\-\s]+?\]',
				'before' => '<span style="font-family: $1;" class="bbc_font">',
				'after' => '</span>',
			),
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_ftp new_win" target="_blank">$1</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, array('<br>' => ''));
					if (strpos($data, 'ftp://') !== 0 && strpos($data, 'ftps://') !== 0)
						$data = 'ftp://' . $data;
				},
			),
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_ftp new_win" target="_blank">',
				'after' => '</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (strpos($data, 'ftp://') !== 0 && strpos($data, 'ftps://') !== 0)
						$data = 'ftp://' . $data;
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'glow',
				'type' => 'unparsed_commas',
				'test' => '[#0-9a-zA-Z\-]{3,12},([012]\d{1,2}|\d{1,2})(,[^]]+)?\]',
				'before' => isBrowser('ie') ? '<table style="border: 0; border-spacing: 0; padding: 0; display: inline; vertical-align: middle; font: inherit;"><tr><td style="filter: Glow(color=$1, strength=$2); font: inherit;">' : '<span style="text-shadow: $1 0 0 3px">',
				'after' => isBrowser('ie') ? '</td></tr></table> ' : '</span>',
			),
			array(
				'tag' => 'green',
				'before' => '<span class="bbc_color green">',
				'after' => '</span>',
			),
			array(
				'tag' => 'html',
				'type' => 'unparsed_content',
				'content' => '$1',
				'block_level' => true,
				'disabled_content' => '$1',
			),
			array(
				'tag' => 'hr',
				'type' => 'closed',
				'content' => '<hr>',
				'block_level' => true,
			),
			array(
				'tag' => 'i',
				'before' => '<em>',
				'after' => '</em>',
			),
			array(
				'tag' => 'img',
				'type' => 'unparsed_content',
				'parameters' => array(
					'alt' => array('optional' => true),
					'title' => array('optional' => true),
					'width' => array('optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'),
					'height' => array('optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'),
				),
				'content' => '<img src="$1" alt="{alt}" title="{title}"{width}{height} class="bbc_img resized">',
				'validate' => function (&$tag, &$data, $disabled)
				{
					global $image_proxy_enabled, $image_proxy_secret, $boardurl;

					$data = strtr($data, array('<br>' => ''));
					if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;

					if (substr($data, 0, 8) != 'https://' && $image_proxy_enabled)
						$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
				},
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'img',
				'type' => 'unparsed_content',
				'content' => '<img src="$1" alt="" class="bbc_img">',
				'validate' => function (&$tag, &$data, $disabled)
				{
					global $image_proxy_enabled, $image_proxy_secret, $boardurl;

					$data = strtr($data, array('<br>' => ''));
					if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;

					if (substr($data, 0, 8) != 'https://' && $image_proxy_enabled)
						$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
				},
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link">$1</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, array('<br>' => ''));
					if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;
				},
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link">',
				'after' => '</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (substr($data, 0, 1) == '#')
						$data = '#post_' . substr($data, 1);
					elseif (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'left',
				'before' => '<div style="text-align: left;">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'li',
				'before' => '<li>',
				'after' => '</li>',
				'trim' => 'outside',
				'require_parents' => array('list'),
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '<br>',
			),
			array(
				'tag' => 'list',
				'before' => '<ul class="bbc_list">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => array('li', 'list'),
				'block_level' => true,
			),
			array(
				'tag' => 'list',
				'parameters' => array(
					'type' => array('match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'),
				),
				'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => array('li'),
				'block_level' => true,
			),
			array(
				'tag' => 'ltr',
				'before' => '<div dir="ltr">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'me',
				'type' => 'unparsed_equals',
				'before' => '<div class="meaction">* $1 ',
				'after' => '</div>',
				'quoted' => 'optional',
				'block_level' => true,
				'disabled_before' => '/me ',
				'disabled_after' => '<br>',
			),
			array(
				'tag' => 'member',
				'type' => 'unparsed_equals',
				'before' => '<a href="' . $scripturl . '?action=profile;u=$1" class="mention">@',
				'after' => '</a>',
			),
			array(
				'tag' => 'move',
				'before' => '<marquee>',
				'after' => '</marquee>',
				'block_level' => true,
				'disallow_children' => array('move'),
			),
			array(
				'tag' => 'nobbc',
				'type' => 'unparsed_content',
				'content' => '$1',
			),
			array(
				'tag' => 'php',
				'type' => 'unparsed_content',
				'content' => '<span class="phpcode">$1</span>',
				'validate' => isset($disabled['php']) ? null : function (&$tag, &$data, $disabled)
				{
					if (!isset($disabled['php']))
					{
						$add_begin = substr(trim($data), 0, 5) != '&lt;?';
						$data = highlight_php_code($add_begin ? '&lt;?php ' . $data . '?&gt;' : $data);
						if ($add_begin)
							$data = preg_replace(array('~^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)~', '~\?&gt;((?:</(font|span)>)*)$~'), '$1', $data, 2);
					}
				},
				'block_level' => false,
				'disabled_content' => '$1',
			),
			array(
				'tag' => 'pre',
				'before' => '<pre>',
				'after' => '</pre>',
			),
			array(
				'tag' => 'quote',
				'before' => '<div class="quoteheader">' . $txt['quote'] . '</div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"></div>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)', 'quoted' => true),
				),
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': {author}</div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"></div>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'type' => 'parsed_equals',
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': $1</div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"></div>',
				'trim' => 'both',
				'quoted' => 'optional',
				// Don't allow everything to be embedded with the author name.
				'parsed_tags_allowed' => array('url', 'iurl', 'ftp'),
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '([^<>]{1,192}?)'),
					'link' => array('match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d+?|action=profile;u=\d+)'),
					'date' => array('match' => '(\d+)', 'validate' => 'timeformat'),
				),
				'before' => '<div class="quoteheader"><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . $txt['search_on'] . ' {date}</a></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"></div>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)'),
				),
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': {author}</div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"></div>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'red',
				'before' => '<span class="bbc_color red">',
				'after' => '</span>',
			),
			array(
				'tag' => 'right',
				'before' => '<div style="text-align: right;">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'rtl',
				'before' => '<div dir="rtl">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 's',
				'before' => '<del>',
				'after' => '</del>',
			),
			array(
				'tag' => 'shadow',
				'type' => 'unparsed_commas',
				'test' => '[#0-9a-zA-Z\-]{3,12},(left|right|top|bottom|[0123]\d{0,2})\]',
				'before' => isBrowser('ie') ? '<span style="display: inline-block; filter: Shadow(color=$1, direction=$2); height: 1.2em;">' : '<span style="text-shadow: $1 $2">',
				'after' => '</span>',
				'validate' => isBrowser('ie') ? function (&$tag, &$data, $disabled)
				{
					if ($data[1] == 'left')
						$data[1] = 270;
					elseif ($data[1] == 'right')
						$data[1] = 90;
					elseif ($data[1] == 'top')
						$data[1] = 0;
					elseif ($data[1] == 'bottom')
						$data[1] = 180;
					else
						$data[1] = (int) $data[1];
				} : function (&$tag, &$data, $disabled)
				{
					if ($data[1] == 'top' || (is_numeric($data[1]) && $data[1] < 50))
						$data[1] = '0 -2px 1px';
					elseif ($data[1] == 'right' || (is_numeric($data[1]) && $data[1] < 100))
						$data[1] = '2px 0 1px';
					elseif ($data[1] == 'bottom' || (is_numeric($data[1]) && $data[1] < 190))
						$data[1] = '0 2px 1px';
					elseif ($data[1] == 'left' || (is_numeric($data[1]) && $data[1] < 280))
						$data[1] = '-2px 0 1px';
					else
						$data[1] = '1px 1px 1px';
				},
			),
			array(
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '([1-9][\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.[\d][\d]?)?)?em)\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
			),
			array(
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '[1-7]\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);
					$data = $sizes[$data] . 'em';
				},
			),
			array(
				'tag' => 'sub',
				'before' => '<sub>',
				'after' => '</sub>',
			),
			array(
				'tag' => 'sup',
				'before' => '<sup>',
				'after' => '</sup>',
			),
			array(
				'tag' => 'table',
				'before' => '<table class="bbc_table">',
				'after' => '</table>',
				'trim' => 'inside',
				'require_children' => array('tr'),
				'block_level' => true,
			),
			array(
				'tag' => 'td',
				'before' => '<td>',
				'after' => '</td>',
				'require_parents' => array('tr'),
				'trim' => 'outside',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			),
			array(
				'tag' => 'time',
				'type' => 'unparsed_content',
				'content' => '$1',
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (is_numeric($data))
						$data = timeformat($data);
					else
						$tag['content'] = '[time]$1[/time]';
				},
			),
			array(
				'tag' => 'tr',
				'before' => '<tr>',
				'after' => '</tr>',
				'require_parents' => array('table'),
				'require_children' => array('td'),
				'trim' => 'both',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			),
			array(
				'tag' => 'tt',
				'before' => '<span class="bbc_tt">',
				'after' => '</span>',
			),
			array(
				'tag' => 'u',
				'before' => '<span class="bbc_u">',
				'after' => '</span>',
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link" target="_blank">$1</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, array('<br>' => ''));
					if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;
				},
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link" target="_blank">',
				'after' => '</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
						$data = 'http://' . $data;
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'white',
				'before' => '<span style="color: white;" class="bbc_color">',
				'after' => '</span>',
			),
		);

		// Inside these tags autolink is not recommendable.
		$no_autolink_tags = array(
			'url',
			'iurl',
			'ftp',
			'email',
		);

		// Let mods add new BBC without hassle.
		call_integration_hook('integrate_bbc_codes', array(&$codes, &$no_autolink_tags));

		// This is mainly for the bbc manager, so it's easy to add tags above.  Custom BBC should be added above this line.
		if ($message === false)
		{
			if (isset($temp_bbc))
				$bbc_codes = $temp_bbc;
			usort($codes, 'sort_bbc_tags');
			return $codes;
		}

		// So the parser won't skip them.
		$itemcodes = array(
			'*' => 'disc',
			'@' => 'disc',
			'+' => 'square',
			'x' => 'square',
			'#' => 'square',
			'o' => 'circle',
			'O' => 'circle',
			'0' => 'circle',
		);
		if (!isset($disabled['li']) && !isset($disabled['list']))
		{
			foreach ($itemcodes as $c => $dummy)
				$bbc_codes[$c] = array();
		}

		// Shhhh!
		if (!isset($disabled['color']))
		{
			$codes[] = array(
				'tag' => 'chrissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			);
			$codes[] = array(
				'tag' => 'kissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			);
		}

		foreach ($codes as $code)
		{
			// If we are not doing every tag only do ones we are interested in.
			if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
				$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
		}
		$codes = null;
	}

	// Shall we take the time to cache this?
	if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && isset($message[1000])) || isset($message[2400])) && empty($parse_tags))
	{
		// It's likely this will change if the message is modified.
		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . serialize($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

		if (($temp = cache_get_data($cache_key, 240)) != null)
			return $temp;

		$cache_t = microtime();
	}

	if ($smileys === 'print')
	{
		// [glow], [shadow], and [move] can't really be printed.
		$disabled['glow'] = true;
		$disabled['shadow'] = true;
		$disabled['move'] = true;

		// Colors can't well be displayed... supposed to be black and white.
		$disabled['color'] = true;
		$disabled['black'] = true;
		$disabled['blue'] = true;
		$disabled['white'] = true;
		$disabled['red'] = true;
		$disabled['green'] = true;
		$disabled['me'] = true;

		// Color coding doesn't make sense.
		$disabled['php'] = true;

		// Links are useless on paper... just show the link.
		$disabled['ftp'] = true;
		$disabled['url'] = true;
		$disabled['iurl'] = true;
		$disabled['email'] = true;
		$disabled['flash'] = true;

		// @todo Change maybe?
		if (!isset($_GET['images']))
			$disabled['img'] = true;

		// @todo Interface/setting to add more?
	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br>'));

	// The non-breaking-space looks a bit different each time.
	$non_breaking_space = $context['utf8'] ? '\x{A0}' : '\xA0';

	$pos = -1;
	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		$pos = strpos($message, '[', $pos + 1);

		// Failsafe.
		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;

		// Can't have a one letter smiley, URL, or email! (sorry.)
		if ($last_pos < $pos - 1)
		{
			// Make sure the $last_pos is not negative.
			$last_pos = max($last_pos, 0);

			// Pick a block of data to do some raw fixing on.
			$data = substr($message, $last_pos, $pos - $last_pos);

			// Take care of some HTML!
			if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
				$data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);

				// <br> should be empty.
				$empty_tags = array('br', 'hr');
				foreach ($empty_tags as $tag)
					$data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '[' . $tag . ' /]', $data);

				// b, u, i, s, pre... basic tags.
				$closable_tags = array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote');
				foreach ($closable_tags as $tag)
				{
					$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
					$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

					if ($diff > 0)
						$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
				}

				// Do <img ...> - with security... action= -> action-.
				preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
				if (!empty($matches[0]))
				{
					$replaces = array();
					foreach ($matches[2] as $match => $imgtag)
					{
						$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

						// Remove action= from the URL - no funny business, now.
						if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) != 0)
							$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

						// Check if the image is larger than allowed.
						if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
						{
							list ($width, $height) = url_image_size($imgtag);

							if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
							{
								$height = (int) (($modSettings['max_image_width'] * $height) / $width);
								$width = $modSettings['max_image_width'];
							}

							if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
							{
								$width = (int) (($modSettings['max_image_height'] * $width) / $height);
								$height = $modSettings['max_image_height'];
							}

							// Set the new image tag.
							$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
						}
						else
							$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
					}

					$data = strtr($data, $replaces);
				}
			}

			if (!empty($modSettings['autoLinkUrls']))
			{
				// Are we inside tags that should be auto linked?
				$no_autolink_area = false;
				if (!empty($open_tags))
				{
					foreach ($open_tags as $open_tag)
						if (in_array($open_tag['tag'], $no_autolink_tags))
							$no_autolink_area = true;
				}

				// Don't go backwards.
				// @todo Don't think is the real solution....
				$lastAutoPos = isset($lastAutoPos) ? $lastAutoPos : 0;
				if ($pos < $lastAutoPos)
					$no_autolink_area = true;
				$lastAutoPos = $pos;

				if (!$no_autolink_area)
				{
					// Parse any URLs.... have to get rid of the @ problems some things cause... stupid email addresses.
					if (!isset($disabled['url']) && (strpos($data, '://') !== false || strpos($data, 'www.') !== false) && strpos($data, '[url') === false)
					{
						// Switch out quotes really quick because they can cause problems.
						$data = strtr($data, array('&#039;' => '\'', '&nbsp;' => $context['utf8'] ? "\xC2\xA0" : "\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));

						// Only do this if the preg survives.
						if (is_string($result = preg_replace(array(
							'~(?<=[\s>\.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\]?)~i',
							'~(?<=[\s>\.(;\'"]|^)((?:ftp|ftps)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\]?)~i',
							'~(?<=[\s>(\'<]|^)(www(?:\.[\w\-_]+)+(?::\d+)?(?:/[\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~i'
						), array(
							'[url]$1[/url]',
							'[ftp]$1[/ftp]',
							'[url=http://$1]$1[/url]'
						), $data)))
							$data = $result;

						$data = strtr($data, array('\'' => '&#039;', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
					}

					// Next, emails...
					if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false)
					{
						$data = preg_replace('~(?<=[\?\s' . $non_breaking_space . '\[\]()*\\\;>]|^)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?,\s' . $non_breaking_space . '\[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br>))~' . ($context['utf8'] ? 'u' : ''), '[email]$1[/email]', $data);
						$data = preg_replace('~(?<=<br>)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?\.,;\s' . $non_breaking_space . '\[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;)~' . ($context['utf8'] ? 'u' : ''), '[email]$1[/email]', $data);
					}
				}
			}

			$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));

			// If it wasn't changed, no copying or other boring stuff has to happen!
			if ($data != substr($message, $last_pos, $pos - $last_pos))
			{
				$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);

				// Since we changed it, look again in case we added or removed a tag.  But we don't want to skip any.
				$old_pos = strlen($data) + $last_pos;
				$pos = strpos($message, '[', $last_pos);
				$pos = $pos === false ? $old_pos : min($pos, $old_pos);
			}
		}

		// Are we there yet?  Are we there yet?
		if ($pos >= strlen($message) - 1)
			break;

		$tags = strtolower($message[$pos + 1]);

		if ($tags == '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 == $pos + 2)
				continue;

			$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

			$to_close = array();
			$block_level = null;

			do
			{
				$tag = array_pop($open_tags);
				if (!$tag)
					break;

				if (!empty($tag['block_level']))
				{
					// Only find out if we need to.
					if ($block_level === false)
					{
						array_push($open_tags, $tag);
						break;
					}

					// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
					if (strlen($look_for) > 0 && isset($bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] == $look_for)
							{
								$block_level = !empty($temp['block_level']);
								break;
							}
					}

					if ($block_level !== true)
					{
						$block_level = false;
						array_push($open_tags, $tag);
						break;
					}
				}

				$to_close[] = $tag;
			}
			while ($tag['tag'] != $look_for);

			// Did we just eat through everything and not find it?
			if ((empty($open_tags) && (empty($tag) || $tag['tag'] != $look_for)))
			{
				$open_tags = $to_close;
				continue;
			}
			elseif (!empty($to_close) && $tag['tag'] != $look_for)
			{
				if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
				{
					foreach ($bbc_codes[$look_for[0]] as $temp)
						if ($temp['tag'] == $look_for)
						{
							$block_level = !empty($temp['block_level']);
							break;
						}
				}

				// We're not looking for a block level tag (or maybe even a tag that exists...)
				if (!$block_level)
				{
					foreach ($to_close as $tag)
						array_push($open_tags, $tag);
					continue;
				}
			}

			foreach ($to_close as $tag)
			{
				$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['after']) + 2;
				$pos2 = $pos - 1;

				// See the comment at the end of the big loop - just eating whitespace ;).
				if (!empty($tag['block_level']) && substr($message, $pos, 6) == '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($tag['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
			}

			if (!empty($to_close))
			{
				$to_close = array();
				$pos--;
			}

			continue;
		}

		// No tags for this character, so just keep going (fastest possible course.)
		if (!isset($bbc_codes[$tags]))
			continue;

		$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
		$tag = null;
		foreach ($bbc_codes[$tags] as $possible)
		{
			$pt_strlen = strlen($possible['tag']);

			// Not a match?
			if (strtolower(substr($message, $pos + 1, $pt_strlen)) != $possible['tag'])
				continue;

			$next_c = $message[$pos + 1 + $pt_strlen];

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + $pt_strlen + 1)) === 0)
				continue;
			// Do we want parameters?
			elseif (!empty($possible['parameters']))
			{
				if ($next_c != ' ')
					continue;
			}
			elseif (isset($possible['type']))
			{
				// Do we need an equal sign?
				if (in_array($possible['type'], array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals')) && $next_c != '=')
					continue;
				// Maybe we just want a /...
				if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + $pt_strlen, 2) != '/]' && substr($message, $pos + 1 + $pt_strlen, 3) != ' /]')
					continue;
				// An immediate ]?
				if ($possible['type'] == 'unparsed_content' && $next_c != ']')
					continue;
			}
			// No type means 'parsed_content', which demands an immediate ] without parameters!
			elseif ($next_c != ']')
				continue;

			// Check allowed tree?
			if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
				continue;
			elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
				continue;
			// If this is in the list of disallowed child tags, don't parse it.
			elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
				continue;

			$pos1 = $pos + 1 + $pt_strlen + 1;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible['tag'] == 'quote')
			{
				// Start with standard
				$quote_alt = false;
				foreach ($open_tags as $open_quote)
				{
					// Every parent quote this quote has flips the styling
					if ($open_quote['tag'] == 'quote')
						$quote_alt = !$quote_alt;
				}
				// Add a class to the quote to style alternating blockquotes
				$possible['before'] = strtr($possible['before'], array('<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">'));
			}

			// This is long, but it makes things much easier and cleaner.
			if (!empty($possible['parameters']))
			{
				$preg = array();
				foreach ($possible['parameters'] as $p => $info)
					$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . ')' . (empty($info['optional']) ? '' : '?');

				// Okay, this may look ugly and it is, but it's not going to happen much and it is the best way of allowing any order of parameters but still parsing them right.
				$match = false;
				$orders = permute($preg);
				foreach ($orders as $p)
					if (preg_match('~^' . implode('', $p) . '\]~i', substr($message, $pos1 - 1), $matches) != 0)
					{
						$match = true;
						break;
					}

				// Didn't match our parameter list, try the next possible.
				if (!$match)
					continue;

				$params = array();
				for ($i = 1, $n = count($matches); $i < $n; $i += 2)
				{
					$key = strtok(ltrim($matches[$i]), '=');
					if (isset($possible['parameters'][$key]['value']))
						$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], array('$1' => $matches[$i + 1]));
					elseif (isset($possible['parameters'][$key]['validate']))
						$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
					else
						$params['{' . $key . '}'] = $matches[$i + 1];

					// Just to make sure: replace any $ or { so they can't interpolate wrongly.
					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
				}

				foreach ($possible['parameters'] as $p => $info)
				{
					if (!isset($params['{' . $p . '}']))
						$params['{' . $p . '}'] = '';
				}

				$tag = $possible;

				// Put the parameters into the string.
				if (isset($tag['before']))
					$tag['before'] = strtr($tag['before'], $params);
				if (isset($tag['after']))
					$tag['after'] = strtr($tag['after'], $params);
				if (isset($tag['content']))
					$tag['content'] = strtr($tag['content'], $params);

				$pos1 += strlen($matches[0]) - 1;
			}
			else
				$tag = $possible;
			break;
		}

		// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
		if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] == ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if ($message[$pos + 1] == '0' && !in_array($message[$pos - 1], array(';', ' ', "\t", "\n", '>')))
				continue;

			$tag = $itemcodes[$message[$pos + 1]];

			// First let's set up the tree: it needs to be in a list, or after an li.
			if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
			{
				$open_tags[] = array(
					'tag' => 'list',
					'after' => '</ul>',
					'block_level' => true,
					'require_children' => array('li'),
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
				);
				$code = '<ul class="bbc_list">';
			}
			// We're in a list item already: another itemcode?  Close it first.
			elseif ($inside['tag'] == 'li')
			{
				array_pop($open_tags);
				$code = '</li>';
			}
			else
				$code = '';

			// Now we open a new tag.
			$open_tags[] = array(
				'tag' => 'li',
				'after' => '</li>',
				'trim' => 'outside',
				'block_level' => true,
				'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
			);

			// First, open the tag...
			$code .= '<li' . ($tag == '' ? '' : ' type="' . $tag . '"') . '>';
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
			$pos += strlen($code) - 1 + 2;

			// Next, find the next break (if any.)  If there's more itemcode after it, keep it going - otherwise close!
			$pos2 = strpos($message, '<br>', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . substr($message, $pos2);

				$open_tags[count($open_tags) - 2]['after'] = '</ul>';
			}
			// Tell the [list] that it needs to close specially.
			else
			{
				// Move the li over, because we're not sure what we'll hit.
				$open_tags[count($open_tags) - 1]['after'] = '';
				$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
			}

			continue;
		}

		// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
		if ($tag === null && $inside !== null && !empty($inside['require_children']))
		{
			array_pop($open_tags);

			$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
			$pos += strlen($inside['after']) - 1 + 2;
		}

		// No tag?  Keep looking, then.  Silly people using brackets without actual tags.
		if ($tag === null)
			continue;

		// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
		if (isset($inside['disallow_children']))
			$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];

		// Is this tag disabled?
		if (isset($disabled[$tag['tag']]))
		{
			if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
			{
				$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
				$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
				$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
			}
			elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
			{
				$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
				$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
			}
			else
				$tag['content'] = $tag['disabled_content'];
		}

		// we use this a lot
		$tag_strlen = strlen($tag['tag']);

		// The only special case is 'html', which doesn't need to close things.
		if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
		{
			$n = count($open_tags) - 1;
			while (empty($open_tags[$n]['block_level']) && $n >= 0)
				$n--;

			// Close all the non block level tags so this tag isn't surrounded by them.
			for ($i = count($open_tags) - 1; $i > $n; $i--)
			{
				$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
				$ot_strlen = strlen($open_tags[$i]['after']);
				$pos += $ot_strlen + 2;
				$pos1 += $ot_strlen + 2;

				// Trim or eat trailing stuff... see comment at the end of the big loop.
				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 6) == '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($open_tags[$i]['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}

		// No type means 'parsed_content'.
		if (!isset($tag['type']))
		{
			// @todo Check for end tag first, so people can say "I like that [i] tag"?
			$open_tags[] = $tag;
			$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
			$pos += strlen($tag['before']) - 1 + 2;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 6) == '<br>')
				$data = substr($data, 6);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag_strlen);

			$pos += strlen($code) - 1 + 2;
			$last_pos = $pos + 1;

		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_equals_content')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 6) == '<br>')
				$data[0] = substr($data[0], 6);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
			$pos += strlen($code) - 1 + 2;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] == 'closed')
		{
			$pos2 = strpos($message, ']', $pos);
			$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($tag['content']) - 1 + 2;
		}
		// This one is sorta ugly... :/.  Unfortunately, it's needed for flash.
		elseif ($tag['type'] == 'unparsed_commas_content')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
			if ($pos3 === false)
				continue;

			// We want $1 to be the content, and the rest to be csv.
			$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
			$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = $tag['content'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
			$pos += strlen($code) - 1 + 2;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($tag['type'] == 'unparsed_commas')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// Fix after, for disabled code mainly.
			foreach ($data as $k => $d)
				$tag['after'] = strtr($tag['after'], array('$' . ($k + 1) => trim($d)));

			$open_tags[] = $tag;

			// Replace them out, $1, $2, $3, $4, etc.
			$code = $tag['before'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($code) - 1 + 2;
		}
		// A tag set to a value, parsed or not.
		elseif ($tag['type'] == 'unparsed_equals' || $tag['type'] == 'parsed_equals')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// For parsed content, we must recurse to avoid security problems.
			if ($tag['type'] != 'unparsed_equals')
				$data = parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : array());

			$tag['after'] = strtr($tag['after'], array('$1' => $data));

			$open_tags[] = $tag;

			$code = strtr($tag['before'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted == false ? 1 : 7));
			$pos += strlen($code) - 1 + 2;
		}

		// If this is block level, eat any breaks after it.
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 6) == '<br>')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 7);

		// Are we trimming outside this tag?
		if (!empty($tag['trim']) && $tag['trim'] != 'outside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
	}

	// Close any remaining tags.
	while ($tag = array_pop($open_tags))
		$message .= "\n" . $tag['after'] . "\n";

	// Parse the smileys within the parts where it can be done safely.
	if ($smileys === true)
	{
		$message_parts = explode("\n", $message);
		for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			parsesmileys($message_parts[$i]);

		$message = implode('', $message_parts);
	}

	// No smileys, just get rid of the markers.
	else
		$message = strtr($message, array("\n" => ''));

	if ($message[0] === ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

	// Allow mods access to what parse_bbc created
	call_integration_hook('integrate_post_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Cache the output if it took some time...
	if (isset($cache_key, $cache_t) && array_sum(explode(' ', microtime())) - array_sum(explode(' ', $cache_t)) > 0.05)
		cache_put_data($cache_key, $message, 240);

	// If this was a force parse revert if needed.
	if (!empty($parse_tags))
	{
		if (empty($temp_bbc))
			$bbc_codes = array();
		else
		{
			$bbc_codes = $temp_bbc;
			unset($temp_bbc);
		}
	}

	return $message;
}

/**
 * Helper function for usort(), used in parse_bbc().
 */
function sort_bbc_tags($a, $b)
{
	return strcmp($a['tag'], $b['tag']);
}

/**
 * Parse smileys in the passed message.
 *
 * The smiley parsing function which makes pretty faces appear :).
 * If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
 * Caches the smileys from the database or array in memory.
 * Doesn't return anything, but rather modifies message directly.
 *
 * @param string &$message
 */
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info, $context, $smcFunc;
	static $smileyPregSearch = null, $smileyPregReplacements = array();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none' || trim($message) == '')
		return;

	// If smileyPregSearch hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		// Use the default smileys if it is disabled. (better for "portability" of smileys.)
		if (empty($modSettings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', '0:)');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');
			$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], '', $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', '');
		}
		else
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$result = $smcFunc['db_query']('', '
					SELECT code, filename, description
					FROM {db_prefix}smileys
					ORDER BY LENGTH(code) DESC',
					array(
					)
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdescs = array();
				while ($row = $smcFunc['db_fetch_assoc']($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $smcFunc['htmlspecialchars']($row['filename']);
					$smileysdescs[] = $row['description'];
				}
				$smcFunc['db_free_result']($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;
		}

		// The non-breaking-space is a complex thing...
		$non_breaking_space = $context['utf8'] ? '\x{A0}' : '\xA0';

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$smileyPregReplacements = array();
		$searchParts = array();
		$smileys_path = $smcFunc['htmlspecialchars']($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/');

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = $smcFunc['htmlspecialchars']($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr($smcFunc['htmlspecialchars']($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley">';

			$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			if ($smileysfrom[$i] != $specialChars)
			{
				$smileyPregReplacements[$specialChars] = $smileyCode;
				$searchParts[] = preg_quote($specialChars, '~');
			}
		}

		$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~' . ($context['utf8'] ? 'u' : '');
	}

	// Replace away!
	$message = preg_replace_callback($smileyPregSearch,
		function ($matches) use ($smileyPregReplacements)
		{
			return $smileyPregReplacements[$matches[1]];
		}, $message);
}

/**
 * Highlight any code.
 *
 * Uses PHP's highlight_string() to highlight PHP syntax
 * does special handling to keep the tabs in the code available.
 * used to parse PHP code from inside [code] and [php] tags.
 *
 * @param string $code
 * @return string the code with highlighted HTML.
 */
function highlight_php_code($code)
{
	// Remove special characters.
	$code = un_htmlspecialchars(strtr($code, array('<br />' => "\n", '<br>' => "\n", "\t" => 'SMF_TAB();', '&#91;' => '[')));

	$oldlevel = error_reporting(0);

	$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));

	error_reporting($oldlevel);

	// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P.
	$buffer = preg_replace('~SMF_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<pre style="display: inline;">' . "\t" . '</pre>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}

/**
 * Make sure the browser doesn't come back and repost the form data.
 * Should be used whenever anything is posted.
 *
 * @param string $setLocation = ''
 * @param bool $refresh = false
 */
function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	global $scripturl, $context, $modSettings, $db_show_debug, $db_cache;

	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty($context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if (WIRELESS)
	{
		// Add the scripturl on if needed.
		if ($add)
			$setLocation = $scripturl . '?' . $setLocation;

		$char = strpos($setLocation, '?') === false ? '?' : ';';

		if (strpos($setLocation, '#') !== false)
			$setLocation = strtr($setLocation, array('#' => $char . WIRELESS_PROTOCOL . '#'));
		else
			$setLocation .= $char . WIRELESS_PROTOCOL;
	}
	elseif ($add)
		$setLocation = $scripturl . ($setLocation != '' ? '?' . $setLocation : '');

	// Put the session ID in.
	if (defined('SID') && SID != '')
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', $scripturl . '?' . SID . ';', $setLocation);
	// Keep that debug in their for template debugging!
	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\\??/', $scripturl . '?debug;', $setLocation);

	if (!empty($modSettings['queryless_urls']) && (empty($context['server']['is_cgi']) || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && (!empty($context['server']['is_apache']) || !empty($context['server']['is_lighttpd']) || !empty($context['server']['is_litespeed'])))
	{
		if (defined('SID') && SID != '')
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '/') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~',
				function ($m) use ($scripturl)
				{
					return $scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html?' . SID. (isset($m[2]) ? "$m[2]" : "");
				}, $setLocation);
		else
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~',
				function ($m) use ($scripturl)
				{
					return $scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html' . (isset($m[2]) ? "$m[2]" : "");
				}, $setLocation);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh, &$permanent));

	// We send a Refresh header only in special cases because Location looks better. (and is quicker...)
	if ($refresh && !WIRELESS)
		header('Refresh: 0; URL=' . strtr($setLocation, array(' ' => '%20')), $permanent ? 301 : 302);
	else
		header('Location: ' . str_replace(' ', '%20', $setLocation), true, $permanent ? 301 : 302);

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}

/**
 * Ends execution.  Takes care of template loading and remembering the previous URL.
 * @param bool $header = null
 * @param bool $do_footer = null
 * @param bool $from_index = false
 * @param bool $from_fatal_error = false
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $settings, $modSettings, $txt, $smcFunc;
	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	trackStats();

	// If we have mail to send, send it.
	if (!empty($context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');

		// Start up the session URL fixer.
		ob_start('ob_sessrewrite');

		if (!empty($settings['output_buffers']) && is_string($settings['output_buffers']))
			$buffers = explode(',', $settings['output_buffers']);
		elseif (!empty($settings['output_buffers']))
			$buffers = $settings['output_buffers'];
		else
			$buffers = array();

		if (isset($modSettings['integrate_buffer']))
			$buffers = array_merge(explode(',', $modSettings['integrate_buffer']), $buffers);

		if (!empty($buffers))
			foreach ($buffers as $function)
			{
				$call = call_helper($function, true);

				// Is it valid?
				if (!empty($call))
					ob_start($call);
			}

		// Display the screen in the logical order.
		template_header();
		$header_done = true;
	}
	if ($do_footer)
	{
		if (WIRELESS && !isset($context['sub_template']))
			fatal_lang_error('wireless_error_notyet', false);

		// Just show the footer, then.
		loadSubTemplate(isset($context['sub_template']) ? $context['sub_template'] : 'main');

		// Anything special to put out?
		if (!empty($context['insert_after_template']) && !isset($_REQUEST['xml']))
			echo $context['insert_after_template'];

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			template_footer();

			// (since this is just debugging... it's okay that it's after </html>.)
			if (!isset($_REQUEST['xml']))
				displayDebug();
		}
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewsmfile') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer && !WIRELESS));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index || WIRELESS)
		exit;
}

/**
 * Get the size of a specified image with better error handling.
 * @todo see if it's better in Subs-Graphics, but one step at the time.
 * Uses getimagesize() to determine the size of a file.
 * Attempts to connect to the server first so it won't time out.
 *
 * @param string $url
 * @return array or false, the image size as array (width, height), or false on failure
 */
function url_image_size($url)
{
	global $sourcedir;

	// Make sure it is a proper URL.
	$url = str_replace(' ', '%20', $url);

	// Can we pull this from the cache... please please?
	if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime();

	// Get the host to pester...
	preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);

	// Can't figure it out, just try the image size.
	if ($url == '' || $url == 'http://' || $url == 'https://')
	{
		return false;
	}
	elseif (!isset($match[1]))
	{
		$size = @getimagesize($url);
	}
	else
	{
		// Try to connect to the server... give it half a second.
		$temp = 0;
		$fp = @fsockopen($match[1], 80, $temp, $temp, 0.5);

		// Successful?  Continue...
		if ($fp != false)
		{
			// Send the HEAD request (since we don't have to worry about chunked, HTTP/1.1 is fine here.)
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'User-Agent: PHP/SMF' . "\r\n" . 'Connection: close' . "\r\n\r\n");

			// Read in the HTTP/1.1 or whatever.
			$test = substr(fgets($fp, 11), -1);
			fclose($fp);

			// See if it returned a 404/403 or something.
			if ($test < 4)
			{
				$size = @getimagesize($url);

				// This probably means allow_url_fopen is off, let's try GD.
				if ($size === false && function_exists('imagecreatefromstring'))
				{
					include_once($sourcedir . '/Subs-Package.php');

					// It's going to hate us for doing this, but another request...
					$image = @imagecreatefromstring(fetch_web_data($url));
					if ($image !== false)
					{
						$size = array(imagesx($image), imagesy($image));
						imagedestroy($image);
					}
				}
			}
		}
	}

	// If we didn't get it, we failed.
	if (!isset($size))
		$size = false;

	// If this took a long time, we may never have to do it again, but then again we might...
	if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $t)) > 0.8)
		cache_put_data('url_image_size-' . md5($url), $size, 240);

	// Didn't work.
	return $size;
}

/**
 * Sets up the basic theme context stuff.
 * @param bool $forceload = false
 */
function setupThemeContext($forceload = false)
{
	global $modSettings, $user_info, $scripturl, $context, $settings, $options, $txt, $maintenance;
	global $smcFunc;
	static $loaded = false;

	// Under SSI this function can be called more then once.  That can cause some problems.
	//   So only run the function once unless we are forced to run it again.
	if ($loaded && !$forceload)
		return;

	$loaded = true;

	$context['in_maintenance'] = !empty($maintenance);
	$context['current_time'] = timeformat(time(), false);
	$context['current_action'] = isset($_GET['action']) ? $smcFunc['htmlspecialchars']($_GET['action']) : '';

	// Get some news...
	$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
	for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
	{
		if (trim($context['news_lines'][$i]) == '')
			continue;

		// Clean it up for presentation ;).
		$context['news_lines'][$i] = parse_bbc(stripslashes(trim($context['news_lines'][$i])), true, 'news' . $i);
	}
	if (!empty($context['news_lines']))
		$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];

	if (!$user_info['is_guest'])
	{
		$context['user']['messages'] = &$user_info['messages'];
		$context['user']['unread_messages'] = &$user_info['unread_messages'];
		$context['user']['alerts'] = &$user_info['alerts'];

		// Personal message popup...
		if ($user_info['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0))
			$context['user']['popup_messages'] = true;
		else
			$context['user']['popup_messages'] = false;
		$_SESSION['unread_messages'] = $user_info['unread_messages'];

		if (allowedTo('moderate_forum'))
			$context['unapproved_members'] = (!empty($modSettings['registration_method']) && ($modSettings['registration_method'] == 2 || (!empty($modSettings['coppaType']) && $modSettings['coppaType'] == 2))) || !empty($modSettings['approveAccountDeletion']) ? $modSettings['unapprovedMembers'] : 0;

		$context['user']['avatar'] = array();

		// Check for gravatar first since we might be forcing them...
		if (($modSettings['gravatarEnabled'] && substr($user_info['avatar']['url'], 0, 11) == 'gravatar://') || !empty($modSettings['gravatarOverride']))
		{
			if (!empty($modSettings['gravatarAllowExtraEmail']) && stristr($user_info['avatar']['url'], 'gravatar://') && strlen($user_info['avatar']['url']) > 11)
				$context['user']['avatar']['href'] = get_gravatar_url($smcFunc['substr']($user_info['avatar']['url'], 11));
			else
				$context['user']['avatar']['href'] = get_gravatar_url($user_info['email']);
		}
		// Uploaded?
		elseif ($user_info['avatar']['url'] == '' && !empty($user_info['avatar']['id_attach']))
			$context['user']['avatar']['href'] = $user_info['avatar']['custom_dir'] ? $modSettings['custom_avatar_url'] . '/' . $user_info['avatar']['filename'] : $scripturl . '?action=dlattach;attach=' . $user_info['avatar']['id_attach'] . ';type=avatar';
		// Full URL?
		elseif (strpos($user_info['avatar']['url'], 'http://') === 0 || strpos($user_info['avatar']['url'], 'https://') === 0)
			$context['user']['avatar']['href'] = $user_info['avatar']['url'];
		// Otherwise we assume it's server stored.
		elseif ($user_info['avatar']['url'] != '')
			$context['user']['avatar']['href'] = $modSettings['avatar_url'] . '/' . $smcFunc['htmlspecialchars']($user_info['avatar']['url']);
		// No avatar at all? Fine, we have a big fat default avatar ;)
		else
			$context['user']['avatar']['href'] = $modSettings['avatar_url'] . '/default.png';

		if (!empty($context['user']['avatar']))
			$context['user']['avatar']['image'] = '<img src="' . $context['user']['avatar']['href'] . '" alt="" class="avatar">';

		// Figure out how long they've been logged in.
		$context['user']['total_time_logged_in'] = array(
			'days' => floor($user_info['total_time_logged_in'] / 86400),
			'hours' => floor(($user_info['total_time_logged_in'] % 86400) / 3600),
			'minutes' => floor(($user_info['total_time_logged_in'] % 3600) / 60)
		);
	}
	else
	{
		$context['user']['messages'] = 0;
		$context['user']['unread_messages'] = 0;
		$context['user']['avatar'] = array();
		$context['user']['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
		$context['user']['popup_messages'] = false;

		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1)
			$txt['welcome_guest'] .= $txt['welcome_guest_activate'];

		// If we've upgraded recently, go easy on the passwords.
		if (!empty($modSettings['disableHashTime']) && ($modSettings['disableHashTime'] == 1 || time() < $modSettings['disableHashTime']))
			$context['disable_login_hashing'] = true;
	}

	// Setup the main menu items.
	setupMenuContext();

	// This is here because old index templates might still use it.
	$context['show_news'] = !empty($settings['enable_news']);

	// This is done to allow theme authors to customize it as they want.
	$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'pm');

	// 2.1+: Add the PM popup here instead. Theme authors can still override it simply by editing/removing the 'fPmPopup' in the array.
	if ($context['show_pm_popup'])
		addInlineJavascript('
		jQuery(document).ready(function($) {
			new smc_Popup({
				heading: ' . JavaScriptEscape($txt['show_personal_messages_heading']) . ',
				content: ' . JavaScriptEscape(sprintf($txt['show_personal_messages'], $context['user']['unread_messages'], $scripturl . '?action=pm')) . ',
				icon_class: \'generic_icons mail_new\'
			});
		});');

	// Add a generic "Are you sure?" confirmation message.
	addInlineJavascript('
	var smf_you_sure =' . JavaScriptEscape($txt['quickmod_confirm']) .';');

	// Now add the capping code for avatars.
	if (!empty($modSettings['avatar_max_width_external']) && !empty($modSettings['avatar_max_height_external']) && !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_css_resize')
		addInlineCss('
img.avatar { max-width: ' . $modSettings['avatar_max_width_external'] . 'px; max-height: ' . $modSettings['avatar_max_height_external'] . 'px; }');

	// This looks weird, but it's because BoardIndex.php references the variable.
	$context['common_stats']['latest_member'] = array(
		'id' => $modSettings['latestMember'],
		'name' => $modSettings['latestRealName'],
		'href' => $scripturl . '?action=profile;u=' . $modSettings['latestMember'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $modSettings['latestRealName'] . '</a>',
	);
	$context['common_stats'] = array(
		'total_posts' => comma_format($modSettings['totalMessages']),
		'total_topics' => comma_format($modSettings['totalTopics']),
		'total_members' => comma_format($modSettings['totalMembers']),
		'latest_member' => $context['common_stats']['latest_member'],
	);
	$context['common_stats']['boardindex_total_posts'] = sprintf($txt['boardindex_total_posts'], $context['common_stats']['total_posts'], $context['common_stats']['total_topics'], $context['common_stats']['total_members']);

	if (empty($settings['theme_version']))
		addJavascriptVar('smf_scripturl', $scripturl);

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? $smcFunc['htmlspecialchars']($modSettings['meta_keywords']) : '';
}

/**
 * Helper function to set the system memory to a needed value
 * - If the needed memory is greater than current, will attempt to get more
 * - if in_use is set to true, will also try to take the current memory usage in to account
 *
 * @param string $needed The amount of memory to request, if needed, like 256M
 * @param bool $in_use Set to true to account for current memory usage of the script
 * @return boolean, true if we have at least the needed memory
 */
function setMemoryLimit($needed, $in_use = false)
{
	// everything in bytes
	$memory_used = 0;
	$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	$memory_needed = memoryReturnBytes($needed);

	// should we account for how much is currently being used?
	if ($in_use)
		$memory_needed += function_exists('memory_get_usage') ? memory_get_usage() : (2 * 1048576);

	// if more is needed, request it
	if ($memory_current < $memory_needed)
	{
		@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
		$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	}

	$memory_current = max($memory_current, memoryReturnBytes(get_cfg_var('memory_limit')));

	// return success or not
	return (bool) ($memory_current >= $memory_needed);
}

/**
 * Helper function to convert memory string settings to bytes
 *
 * @param string $val The byte string, like 256M or 1G
 * @return integer The string converted to a proper integer in bytes
 */
function memoryReturnBytes($val)
{
	if (is_integer($val))
		return $val;

	// Separate the number from the designator
	$val = trim($val);
	$num = intval(substr($val, 0, strlen($val) - 1));
	$last = strtolower(substr($val, -1));

	// convert to bytes
	switch ($last)
	{
		case 'g':
			$num *= 1024;
		case 'm':
			$num *= 1024;
		case 'k':
			$num *= 1024;
	}
	return $num;
}

/**
 * The header template
 */
function template_header()
{
	global $txt, $modSettings, $context, $user_info, $boarddir, $cachedir;

	setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		// Are we debugging the template/html content?
		if (!isset($_REQUEST['xml']) && isset($_GET['debug']) && !isBrowser('ie') && !WIRELESS)
			header('Content-Type: application/xhtml+xml');
		elseif (!isset($_REQUEST['xml']) && !WIRELESS)
			header('Content-Type: text/html; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	}

	if (!WIRELESS || WIRELESS_PROTOCOL != 'wap')
		header('Content-Type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

	// We need to splice this in after the body layer, or after the main layer for older stuff.
	if ($context['in_maintenance'] && $context['user']['is_admin'])
	{
		$position = array_search('body', $context['template_layers']);
		if ($position === false)
			$position = array_search('main', $context['template_layers']);

		if ($position !== false)
		{
			$before = array_slice($context['template_layers'], 0, $position + 1);
			$after = array_slice($context['template_layers'], $position + 1);
			$context['template_layers'] = array_merge($before, array('maint_warning'), $after);
		}
	}

	$checked_securityFiles = false;
	$showed_banned = false;
	foreach ($context['template_layers'] as $layer)
	{
		loadSubTemplate($layer . '_above', true);

		// May seem contrived, but this is done in case the body and main layer aren't there...
		if (in_array($layer, array('body', 'main')) && allowedTo('admin_forum') && !$user_info['is_guest'] && !$checked_securityFiles)
		{
			$checked_securityFiles = true;

			$securityFiles = array('install.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');

			// Add your own files.
			call_integration_hook('integrate_security_files', array(&$securityFiles));

			foreach ($securityFiles as $i => $securityFile)
			{
				if (!file_exists($boarddir . '/' . $securityFile))
					unset($securityFiles[$i]);
			}

			// We are already checking so many files...just few more doesn't make any difference! :P
			if (!empty($modSettings['currentAttachmentUploadDir']))
			{
				if (!is_array($modSettings['attachmentUploadDir']))
					$modSettings['attachmentUploadDir'] = @unserialize($modSettings['attachmentUploadDir']);
				$path = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
			}
			else
			{
				$path = $modSettings['attachmentUploadDir'];
				$id_folder_thumb = 1;
			}
			secureDirectory($path, true);
			secureDirectory($cachedir);

			// If agreement is enabled, at least the english version shall exists
			if ($modSettings['requireAgreement'])
				$agreement = !file_exists($boarddir . '/agreement.txt');

			if (!empty($securityFiles) || (!empty($modSettings['cache_enable']) && !is_writable($cachedir)) || !empty($agreement))
			{
				echo '
		<div class="errorbox">
			<p class="alert">!!</p>
			<h3>', empty($securityFiles) ? $txt['generic_warning'] : $txt['security_risk'], '</h3>
			<p>';

				foreach ($securityFiles as $securityFile)
				{
					echo '
				', $txt['not_removed'], '<strong>', $securityFile, '</strong>!<br>';

					if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
						echo '
				', sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br>';
				}

				if (!empty($modSettings['cache_enable']) && !is_writable($cachedir))
					echo '
				<strong>', $txt['cache_writable'], '</strong><br>';

				if (!empty($agreement))
					echo '
				<strong>', $txt['agreement_missing'], '</strong><br>';

				echo '
			</p>
		</div>';
			}
		}
		// If the user is banned from posting inform them of it.
		elseif (in_array($layer, array('main', 'body')) && isset($_SESSION['ban']['cannot_post']) && !$showed_banned)
		{
			$showed_banned = true;
			echo '
				<div class="windowbg alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red;">
					', sprintf($txt['you_are_post_banned'], $user_info['is_guest'] ? $txt['guest_title'] : $user_info['name']);

			if (!empty($_SESSION['ban']['cannot_post']['reason']))
				echo '
					<div style="padding-left: 4ex; padding-top: 1ex;">', $_SESSION['ban']['cannot_post']['reason'], '</div>';

			if (!empty($_SESSION['ban']['expire_time']))
				echo '
					<div>', sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)), '</div>';
			else
				echo '
					<div>', $txt['your_ban_expires_never'], '</div>';

			echo '
				</div>';
		}
	}
}

/**
 * Show the copyright.
 */
function theme_copyright()
{
	global $forum_copyright, $software_year, $forum_version;

	// Don't display copyright for things like SSI.
	if (!isset($forum_version) || !isset($software_year))
		return;

	// Put in the version...
	$forum_copyright = sprintf($forum_copyright, $forum_version, $software_year);

	echo '
			<span class="smalltext" style="display: inline; visibility: visible; font-family: Verdana, Arial, sans-serif;">' . $forum_copyright . '
			</span>';
}

/**
 * The template footer
 */
function template_footer()
{
	global $context, $modSettings, $time_start, $db_count;

	// Show the load time?  (only makes sense for the footer.)
	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
	$context['load_time'] = comma_format(round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3));
	$context['load_queries'] = $db_count;

	foreach (array_reverse($context['template_layers']) as $layer)
		loadSubTemplate($layer . '_below', true);
}

/**
 * Output the Javascript files
 * 	- tabbing in this function is to make the HTML source look good proper
 *  - if defered is set function will output all JS (source & inline) set to load at page end
 *
 * @param bool $do_defered = false
 */
function template_javascript($do_defered = false)
{
	global $context, $modSettings, $settings;

	// Use this hook to minify/optimize Javascript files and vars
	call_integration_hook('integrate_pre_javascript_output', array(&$do_defered));

	// Ouput the declared Javascript variables.
	if (!empty($context['javascript_vars']) && !$do_defered)
	{
		echo '
	<script><!-- // --><![CDATA[';

		foreach ($context['javascript_vars'] as $key => $value)
		{
			if (empty($value))
			{
				echo '
		var ', $key, ';';
			}
			else
			{
				echo '
		var ', $key, ' = ', $value, ';';
			}
		}

		echo '
	// ]]></script>';
	}

	// While we have Javascript files to place in the template
	foreach ($context['javascript_files'] as $id => $js_file)
	{
		if ((!$do_defered && empty($js_file['options']['defer'])) || ($do_defered && !empty($js_file['options']['defer'])))
			echo '
	<script src="', $js_file['filename'], '"', !empty($js_file['options']['async']) ? ' async="async"' : '', '></script>';

		// If we are loading JQuery and we are set to 'auto' load, put in our remote success or load local check
		if ($id == 'jquery' && (!isset($modSettings['jquery_source']) || !in_array($modSettings['jquery_source'], array('local', 'cdn'))))
		echo '
	<script><!-- // --><![CDATA[
		window.jQuery || document.write(\'<script src="' . $settings['default_theme_url'] . '/scripts/jquery-1.11.0.min.js"><\/script>\');
	// ]]></script>';

	}

	// Inline JavaScript - Actually useful some times!
	if (!empty($context['javascript_inline']))
	{
		if (!empty($context['javascript_inline']['defer']) && $do_defered)
		{
			echo '
<script><!-- // --><![CDATA[';

			foreach ($context['javascript_inline']['defer'] as $js_code)
				echo $js_code;

			echo '
// ]]></script>';
		}

		if (!empty($context['javascript_inline']['standard']) && !$do_defered)
		{
			echo '
	<script><!-- // --><![CDATA[';

			foreach ($context['javascript_inline']['standard'] as $js_code)
				echo $js_code;

			echo '
	// ]]></script>';
		}
	}
}

/**
 * Output the CSS files
 */
function template_css()
{
	global $context, $db_show_debug, $boardurl;

	// Use this hook to minify/optimize CSS files
	call_integration_hook('integrate_pre_css_output');

	foreach ($context['css_files'] as $id => $file)
		echo '
	<link rel="stylesheet" type="text/css" href="', $file['filename'], '">';

	if ($db_show_debug === true)
	{
		// Try to keep only what's useful.
		$repl = array($boardurl . '/Themes/' => '', $boardurl . '/' => '');
		foreach ($context['css_files'] as $file)
			$context['debug']['sheets'][] = strtr($file['filename'], $repl);
	}

	if (!empty($context['css_header']))
	{
		echo '
	<style>';

		foreach ($context['css_header'] as $css)
			echo $css .'
	';

		echo'
	</style>';
	}
}

/**
 * Get an attachment's encrypted filename. If $new is true, won't check for file existence.
 * @todo this currently returns the hash if new, and the full filename otherwise.
 * Something messy like that.
 * @todo and of course everything relies on this behavior and work around it. :P.
 * Converters included.
 *
 * @param $filename
 * @param $attachment_id
 * @param $dir
 * @param $new
 * @param $file_hash
 */
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings, $smcFunc;

	// Just make up a nice hash...
	if ($new)
		return sha1(md5($filename . time()) . mt_rand());

	// Grab the file hash if it wasn't added.
	// Left this for legacy.
	if ($file_hash === '')
	{
		$request = $smcFunc['db_query']('', '
			SELECT file_hash
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attachment_id,
		));

		if ($smcFunc['db_num_rows']($request) === 0)
			return false;

		list ($file_hash) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Still no hash? mmm...
	if (empty($file_hash))
		$file_hash = sha1(md5($filename . time()) . mt_rand());

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = $modSettings['attachmentUploadDir'][$dir];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash .'.dat';
}

/**
 * Convert a single IP to a ranged IP.
 * internal function used to convert a user-readable format to a format suitable for the database.
 *
 * @param string $fullip
 * @return array|string 'unknown' if the ip in the input was '255.255.255.255'
 */
function ip2range($fullip)
{
	// If its IPv6, validate it first.
	if (isValidIPv6($fullip) !== false)
	{
		$ip_parts = explode(':', expandIPv6($fullip, false));
		$ip_array = array();

		if (count($ip_parts) != 8)
			return array();

		for ($i = 0; $i < 8; $i++)
		{
			if ($ip_parts[$i] == '*')
				$ip_array[$i] = array('low' => '0', 'high' => hexdec('ffff'));
			elseif (preg_match('/^([0-9A-Fa-f]{1,4})\-([0-9A-Fa-f]{1,4})$/', $ip_parts[$i], $range) == 1)
				$ip_array[$i] = array('low' => hexdec($range[1]), 'high' => hexdec($range[2]));
			elseif (is_numeric(hexdec($ip_parts[$i])))
				$ip_array[$i] = array('low' => hexdec($ip_parts[$i]), 'high' => hexdec($ip_parts[$i]));
		}

		return $ip_array;
	}

	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('.', $fullip);
	$ip_array = array();

	if (count($ip_parts) != 4)
		return array();

	for ($i = 0; $i < 4; $i++)
	{
		if ($ip_parts[$i] == '*')
			$ip_array[$i] = array('low' => '0', 'high' => '255');
		elseif (preg_match('/^(\d{1,3})\-(\d{1,3})$/', $ip_parts[$i], $range) == 1)
			$ip_array[$i] = array('low' => $range[1], 'high' => $range[2]);
		elseif (is_numeric($ip_parts[$i]))
			$ip_array[$i] = array('low' => $ip_parts[$i], 'high' => $ip_parts[$i]);
	}

	// Makes it simpiler to work with.
	$ip_array[4] = array('low' => 0, 'high' => 0);
	$ip_array[5] = array('low' => 0, 'high' => 0);
	$ip_array[6] = array('low' => 0, 'high' => 0);
	$ip_array[7] = array('low' => 0, 'high' => 0);

	return $ip_array;
}

/**
 * Lookup an IP; try shell_exec first because we can do a timeout on it.
 *
 * @param string $ip
 */
function host_from_ip($ip)
{
	global $modSettings;

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime();

	// Try the Linux host command, perhaps?
	if (!isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
	{
		if (!isset($modSettings['host_to_dis']))
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		else
			$test = @shell_exec('host ' . @escapeshellarg($ip));

		// Did host say it didn't find anything?
		if (strpos($test, 'not found') !== false)
			$host = '';
		// Invalid server option?
		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($modSettings['host_to_dis']))
			updateSettings(array('host_to_dis' => 1));
		// Maybe it found something, after all?
		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is nslookup; usually only Windows, but possibly some Unix?
	if (!isset($host) && stripos(PHP_OS, 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));
		if (strpos($test, 'Non-existent domain') !== false)
			$host = '';
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is the last try :/.
	if (!isset($host) || $host === false)
		$host = @gethostbyaddr($ip);

	// It took a long time, so let's cache it!
	if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $t)) > 0.5)
		cache_put_data('hostlookup-' . $ip, $host, 600);

	return $host;
}

/**
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text
 * @param int $max_chars = 20
 * @param bool $encrypt = false
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	global $smcFunc, $context;

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0' . ($context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . ($context['utf8'] ? 'u' : ''), ' ', strtr($text, array('<br>' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = un_htmlspecialchars($smcFunc['strtolower']($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted{$i})] * pow(63, $i);
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
}

/**
 * Creates an image/text button
 *
 * @param string $name
 * @param string $alt
 * @param string $label = ''
 * @param boolean $custom = ''
 * @param boolean $force_use = false
 * @return string
 */
function create_button($name, $alt, $label = '', $custom = '', $force_use = false)
{
	global $settings, $txt;

	// Does the current loaded theme have this and we are not forcing the usage of this function?
	if (function_exists('template_create_button') && !$force_use)
		return template_create_button($name, $alt, $label = '', $custom = '');

	if (!$settings['use_image_buttons'])
		return $txt[$alt];
	elseif (!empty($settings['use_buttons']))
		return '<span class="generic_icons ' . $name . '" alt="' . $txt[$alt] . '"></span>' . ($label != '' ? '&nbsp;<strong>' . $txt[$label] . '</strong>' : '');
	else
		return '<img src="' . $settings['lang_images_url'] . '/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . '>';
}

/**
 * Empty out the cache in use as best it can
 *
 * It may only remove the files of a certain type (if the $type parameter is given)
 * Type can be user, data or left blank
 * 	- user clears out user data
 *  - data clears out system / opcode data
 *  - If no type is specified will perform a complete cache clearing
 * For cache engines that do not distinguish on types, a full cache flush will be done
 *
 * @param string $type = ''
 */
function clean_cache($type = '')
{
	global $cachedir, $sourcedir, $cache_accelerator, $modSettings, $memcached;

	switch ($cache_accelerator)
	{
		case 'memcached':
			if (function_exists('memcache_flush') || function_exists('memcached_flush') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return;

				// clear it out
				if (function_exists('memcache_flush'))
					memcache_flush($memcached);
				else
					memcached_flush($memcached);
			}
			break;
		case 'apc':
			if (function_exists('apc_clear_cache'))
			{
				// if passed a type, clear that type out
				if ($type === '' || $type === 'data')
				{
					apc_clear_cache('user');
					apc_clear_cache('system');
				}
				elseif ($type === 'user')
					apc_clear_cache('user');
			}
			break;
		case 'zend':
			if (function_exists('zend_shm_cache_clear'))
				zend_shm_cache_clear('SMF');
			break;
		case 'xcache':
			if (function_exists('xcache_clear_cache'))
			{
				//
				if ($type === '')
				{
					xcache_clear_cache(XC_TYPE_VAR, 0);
					xcache_clear_cache(XC_TYPE_PHP, 0);
				}
				if ($type === 'user')
					xcache_clear_cache(XC_TYPE_VAR, 0);
				if ($type === 'data')
					xcache_clear_cache(XC_TYPE_PHP, 0);
			}
			break;
		default:
			// No directory = no game.
			if (!is_dir($cachedir))
				return;

			// Remove the files in SMF's own disk cache, if any
			$dh = opendir($cachedir);
			while ($file = readdir($dh))
			{
				if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
					@unlink($cachedir . '/' . $file);
			}
			closedir($dh);
			break;
	}

	// Invalidate cache, to be sure!
	// ... as long as index.php can be modified, anyway.
	@touch($cachedir . '/' . 'index.php');
	call_integration_hook('integrate_clean_cache');
	clearstatcache();
}

/**
 * Sets up all of the top menu buttons
 * Saves them in the cache if it is available and on
 * Places the results in $context
 *
 */
function setupMenuContext()
{
	global $context, $modSettings, $user_info, $txt, $scripturl, $sourcedir, $settings;

	// Set up the menu privileges.
	$context['allow_search'] = !empty($modSettings['allow_guestAccess']) ? allowedTo('search_posts') : (!$user_info['is_guest'] && allowedTo('search_posts'));
	$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));

	$context['allow_memberlist'] = allowedTo('view_mlist');
	$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
	$context['allow_moderation_center'] = $context['user']['can_mod'];
	$context['allow_pm'] = allowedTo('pm_read');

	$cacheTime = $modSettings['lastActive'] * 60;

	// Initial "can you post an event in the calendar" option - but this might have been set in the calendar already.
	if (!isset($context['allow_calendar_event']))
	{
		$context['allow_calendar_event'] = $context['allow_calendar'] && allowedTo('calendar_post');

		// If you don't allow events not linked to posts and you're not an admin, we have more work to do...
		if ($context['allow_calendar'] && $context['allow_calendar_event'] && empty($modSettings['cal_allow_unlinked']) && !$user_info['is_admin'])
		{
			$boards_can_post = boardsAllowedTo('post_new');
			$context['allow_calendar_event'] &= !empty($boards_can_post);
		}
	}

	// There is some menu stuff we need to do if we're coming at this from a non-guest perspective.
	if (!$context['user']['is_guest'])
	{
		addInlineJavascript('
	var user_menus = new smc_PopupMenu();
	user_menus.add("profile", "' . $scripturl . '?action=profile;area=popup");
	user_menus.add("alerts", "' . $scripturl . '?action=profile;area=alerts_popup;u='. $context['user']['id'] .'");', true);
		if ($context['allow_pm'])
			addInlineJavascript('
	user_menus.add("pm", "' . $scripturl . '?action=pm;sa=popup");', true);

		if (!empty($modSettings['enable_ajax_alerts']))
		{
			require_once($sourcedir . '/Subs-Notify.php');

			$timeout = getNotifyPrefs($context['user']['id'], 'alert_timeout');
			$timeout = empty($timeout) ? 10000 : $timeout[$context['user']['id']]['alert_timeout'] * 1000;

			addInlineJavascript('
	var new_alert_title = "' . $context['forum_name'] . '";
	var alert_timeout = ' . $timeout . ';');
			loadJavascriptFile('alerts.js', array('default_theme' => true), 'smf_alerts');
		}
	}

	// All the buttons we can possible want and then some, try pulling the final list of buttons from cache first.
	if (($menu_buttons = cache_get_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $cacheTime)) === null || time() - $cacheTime <= $modSettings['settings_updated'])
	{
		$buttons = array(
			'home' => array(
				'title' => $txt['home'],
				'href' => $scripturl,
				'show' => true,
				'sub_buttons' => array(
				),
				'is_last' => $context['right_to_left'],
			),
			'search' => array(
				'title' => $txt['search'],
				'href' => $scripturl . '?action=search',
				'show' => $context['allow_search'],
				'sub_buttons' => array(
				),
			),
			'admin' => array(
				'title' => $txt['admin'],
				'href' => $scripturl . '?action=admin',
				'show' => $context['allow_admin'],
				'sub_buttons' => array(
					'featuresettings' => array(
						'title' => $txt['modSettings_title'],
						'href' => $scripturl . '?action=admin;area=featuresettings',
						'show' => allowedTo('admin_forum'),
					),
					'packages' => array(
						'title' => $txt['package'],
						'href' => $scripturl . '?action=admin;area=packages',
						'show' => allowedTo('admin_forum'),
					),
					'errorlog' => array(
						'title' => $txt['errlog'],
						'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
						'show' => allowedTo('admin_forum') && !empty($modSettings['enableErrorLogging']),
					),
					'permissions' => array(
						'title' => $txt['edit_permissions'],
						'href' => $scripturl . '?action=admin;area=permissions',
						'show' => allowedTo('manage_permissions'),
					),
					'memberapprove' => array(
						'title' => $txt['approve_members_waiting'],
						'href' => $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve',
						'show' => !empty($context['unapproved_members']),
						'is_last' => true,
					),
				),
			),
			'moderate' => array(
				'title' => $txt['moderate'],
				'href' => $scripturl . '?action=moderate',
				'show' => $context['allow_moderation_center'],
				'sub_buttons' => array(
					'modlog' => array(
						'title' => $txt['modlog_view'],
						'href' => $scripturl . '?action=moderate;area=modlog',
						'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'poststopics' => array(
						'title' => $txt['mc_unapproved_poststopics'],
						'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
					'attachments' => array(
						'title' => $txt['mc_unapproved_attachments'],
						'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
					'reports' => array(
						'title' => $txt['mc_reported_posts'],
						'href' => $scripturl . '?action=moderate;area=reportedposts',
						'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'reported_members' => array(
						'title' => $txt['mc_reported_members'],
						'href' => $scripturl . '?action=moderate;area=reportedmembers',
						'show' => allowedTo('moderate_forum'),
						'is_last' => true,
					)
				),
			),
			'calendar' => array(
				'title' => $txt['calendar'],
				'href' => $scripturl . '?action=calendar',
				'show' => $context['allow_calendar'],
				'sub_buttons' => array(
					'view' => array(
						'title' => $txt['calendar_menu'],
						'href' => $scripturl . '?action=calendar',
						'show' => $context['allow_calendar_event'],
					),
					'post' => array(
						'title' => $txt['calendar_post_event'],
						'href' => $scripturl . '?action=calendar;sa=post',
						'show' => $context['allow_calendar_event'],
						'is_last' => true,
					),
				),
			),
			'mlist' => array(
				'title' => $txt['members_title'],
				'href' => $scripturl . '?action=mlist',
				'show' => $context['allow_memberlist'],
				'sub_buttons' => array(
					'mlist_view' => array(
						'title' => $txt['mlist_menu_view'],
						'href' => $scripturl . '?action=mlist',
						'show' => true,
					),
					'mlist_search' => array(
						'title' => $txt['mlist_search'],
						'href' => $scripturl . '?action=mlist;sa=search',
						'show' => true,
						'is_last' => true,
					),
				),
			),
			'signup' => array(
				'title' => $txt['register'],
				'href' => $scripturl . '?action=signup',
				'show' => $user_info['is_guest'] && $context['can_register'],
				'sub_buttons' => array(
				),
				'is_last' => !$context['right_to_left'],
			),
			'logout' => array(
				'title' => $txt['logout'],
				'href' => $scripturl . '?action=logout;%1$s=%2$s',
				'show' => !$user_info['is_guest'],
				'sub_buttons' => array(
				),
				'is_last' => !$context['right_to_left'],
			),
		);

		// Allow editing menu buttons easily.
		call_integration_hook('integrate_menu_buttons', array(&$buttons));

		// Now we put the buttons in the context so the theme can use them.
		$menu_buttons = array();
		foreach ($buttons as $act => $button)
			if (!empty($button['show']))
			{
				$button['active_button'] = false;

				// This button needs some action.
				if (isset($button['action_hook']))
					$needs_action_hook = true;

				// Make sure the last button truely is the last button.
				if (!empty($button['is_last']))
				{
					if (isset($last_button))
						unset($menu_buttons[$last_button]['is_last']);
					$last_button = $act;
				}

				// Go through the sub buttons if there are any.
				if (!empty($button['sub_buttons']))
					foreach ($button['sub_buttons'] as $key => $subbutton)
					{
						if (empty($subbutton['show']))
							unset($button['sub_buttons'][$key]);

						// 2nd level sub buttons next...
						if (!empty($subbutton['sub_buttons']))
						{
							foreach ($subbutton['sub_buttons'] as $key2 => $sub_button2)
							{
								if (empty($sub_button2['show']))
									unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
							}
						}
					}

					// Does this button have its own icon?
					if (isset($button['icon']) && file_exists($settings['theme_dir'] . '/images/' . $button['icon']))
						$button['icon'] = '<img src="' . $settings['images_url'] . '/' . $button['icon'] . '" alt="">';
					elseif (isset($button['icon']) && file_exists($settings['default_theme_dir'] . '/images/' . $button['icon']))
						$button['icon'] = '<img src="' . $settings['default_images_url'] . '/' . $button['icon'] . '" alt="">';
					elseif (isset($button['icon']))
						$button['icon'] = '<span class="generic_icons ' . $button['icon'] . '"></span>';
					else
						$button['icon'] = '<span class="generic_icons ' . $act . '"></span>';

				$menu_buttons[$act] = $button;
			}

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $menu_buttons, $cacheTime);
	}

	$context['menu_buttons'] = $menu_buttons;

	// Logging out requires the session id in the url.
	if (isset($context['menu_buttons']['logout']))
		$context['menu_buttons']['logout']['href'] = sprintf($context['menu_buttons']['logout']['href'], $context['session_var'], $context['session_id']);

	// Figure out which action we are doing so we can set the active tab.
	// Default to home.
	$current_action = 'home';

	if (isset($context['menu_buttons'][$context['current_action']]))
		$current_action = $context['current_action'];
	elseif ($context['current_action'] == 'search2')
		$current_action = 'search';
	elseif ($context['current_action'] == 'theme')
		$current_action = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? 'profile' : 'admin';
	elseif ($context['current_action'] == 'register2')
		$current_action = 'register';
	elseif ($context['current_action'] == 'login2' || ($user_info['is_guest'] && $context['current_action'] == 'reminder'))
		$current_action = 'login';
	elseif ($context['current_action'] == 'groups' && $context['allow_moderation_center'])
		$current_action = 'moderate';

	// There are certain exceptions to the above where we don't want anything on the menu highlighted.
	if ($context['current_action'] == 'profile' && !empty($context['user']['is_owner']))
	{
		$current_action = !empty($_GET['area']) && $_GET['area'] == 'showalerts' ? 'self_alerts' : 'self_profile';
		$context[$current_action] = true;
	}
	elseif ($context['current_action'] == 'pm')
	{
		$current_action = 'self_pm';
		$context['self_pm'] = true;
	}

	// Not all actions are simple.
	if (!empty($needs_action_hook))
		call_integration_hook('integrate_current_action', array(&$current_action));

	if (isset($context['menu_buttons'][$current_action]))
		$context['menu_buttons'][$current_action]['active_button'] = true;

	$total_mod_reports = 0;

	if (!empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1' && !empty($context['open_mod_reports']))
	{
		$total_mod_reports = $context['open_mod_reports'];
		$context['menu_buttons']['moderate']['sub_buttons']['reports']['title'] .= ' <span class="amt">' . $context['open_mod_reports'] . '</span>';
	}

	// Show how many errors there are
	if (allowedTo('admin_forum') && !empty($context['num_errors']))
	{
		$context['menu_buttons']['admin']['title'] .= ' <span class="amt">' . $context['num_errors'] . '</span>';
		$context['menu_buttons']['admin']['sub_buttons']['errorlog']['title'] .= ' <span class="amt">' . $context['num_errors'] . '</span>';
	}

	/**
	 * @todo For some reason, $context['open_member_reports'] isn't getting set
	 */
	if (allowedTo('moderate_forum') && !empty($context['open_member_reports']))
	{
		$total_mod_reports += $context['open_member_reports'];
		$context['menu_buttons']['moderate']['sub_buttons']['reported_members']['title'] .= ' <span class="amt">' . $context['open_member_reports'] . '</span>';
	}

	if (!empty($context['unapproved_members']))
	{
		$context['menu_buttons']['admin']['sub_buttons']['memberapprove']['title'] .= ' <span class="amt">' . $context['unapproved_members'] . '</span>';
		$context['menu_buttons']['admin']['title'] .= ' <span class="amt">' . $context['unapproved_members'] . '</span>';
	}

	// Do we have any open reports?
	if ($total_mod_reports > 0)
	{
		$context['menu_buttons']['moderate']['title'] .= ' <span class="amt">' . $total_mod_reports . '</span>';
	}

}

/**
 * Generate a random seed and ensure it's stored in settings.
 */
function smf_seed_generator()
{
	updateSettings(array('rand_seed' => microtime() * 1000000));
}

/**
 * Process functions of an integration hook.
 * calls all functions of the given hook.
 * supports static class method calls.
 *
 * @param string $hook The hook name
 * @param array $parameters An array of parameters this hook implements
 * @return array the results of the functions
 */
function call_integration_hook($hook, $parameters = array())
{
	global $modSettings, $settings, $boarddir, $sourcedir, $db_show_debug;
	global $context, $txt;

	if ($db_show_debug === true)
		$context['debug']['hooks'][] = $hook;

	// Need to have some control.
	if (!isset($context['instances']))
		$context['instances'] = array();

	$results = array();
	if (empty($modSettings[$hook]))
		return $results;

	// Define some needed vars.
	$function = false;

	$functions = explode(',', $modSettings[$hook]);
	// Loop through each function.
	foreach ($functions as $function)
	{
		$function = trim($function);
		$call = '';

		// Did we find a file to load?
		$function = load_file($function);

		if (!empty($function))
			$call = call_helper($function, true);

		// Is it valid?
		if (!empty($call))
			$results[$function] = call_user_func_array($call, $parameters);

		// Whatever it was suppose to call, it failed :(
		elseif (!empty($function) && !empty($absPath))
		{
			loadLanguage('Errors');
			log_error(sprintf($txt['hook_fail_call_to'], $function, $absPath), 'general');
		}
	}

	return $results;
}

/**
 * Add a function for integration hook.
 * does nothing if the function is already added.
 *
 * @param string $hook The complete hook name.
 * @param string $function Function name, can be a call to a method via Class::method.
 * @param bool $permanent = true if true, updates the value in settings table.
 * @param string $file Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param bool $object Boolean Indicates if your class will be instantiated when its respective hook is called, your function must be a method.
 */
function add_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	global $smcFunc, $modSettings;

	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . '|' . $function;

	// Get the correct string.
	$integration_call = $function;

	// Is it going to be permanent?
	if ($permanent)
	{
		$request = $smcFunc['db_query']('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			array(
				'variable' => $hook,
			)
		);
		list ($current_functions) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);
			if (in_array($integration_call, $current_functions))
				return;

			$permanent_functions = array_merge($current_functions, array($integration_call));
		}
		else
			$permanent_functions = array($integration_call);

		updateSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// Do nothing, if it's already there.
	if (in_array($integration_call, $functions))
		return;

	$functions[] = $integration_call;
	$modSettings[$hook] = implode(',', $functions);
}

/**
 * Remove an integration hook function.
 * Removes the given function from the given hook.
 * Does nothing if the function is not available.
 *
 * @param string $hook The complete hook name.
 * @param string $function Function name, can be a call to a method via Class::method.
 * @params boolean $permanent Irrelevant for the function itself but need to declare it to match
 * @param string $file Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
add_integration_function
 * @param boolean $object
 * @see add_integration_function
 */
function remove_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	global $smcFunc, $modSettings;

	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . '|' . $function;

	// Get the correct string.
	$integration_call = $function;

	// Get the permanent functions.
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:variable}',
		array(
			'variable' => $hook,
		)
	);
	list ($current_functions) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		if (in_array($integration_call, $current_functions))
			updateSettings(array($hook => implode(',', array_diff($current_functions, array($integration_call)))));
	}

	// Turn the function list into something usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// You can only remove it if it's available.
	if (!in_array($integration_call, $functions))
		return;

	$functions = array_diff($functions, array($integration_call));
	$modSettings[$hook] = implode(',', $functions);
}

/**
 * Receives a string and tries to figure it out if its a method or a function.
 * If a method is found, it looks for a "#" which indicates SMF should create a new instance of the given class.
 * Checks the string/array for is_callable() and return false/fatal_lang_error is the given value results in a non callable string/array.
 * Prepare and returns a callable depending on the type of method/function found.
 *
 * @param string $string The string containing a function name or a static call.
 * @param boolean $return If true, the function will not call the function/method but instead will return the formatted string.
 * @return string|array|boolean Either a string or an array that contains a callable function name or an array with a class and method to call. Boolean false if the given string cannot produce a callable var.
 */
function call_helper($string, $return = false)
{
	global $context, $smcFunc, $txt, $db_show_debug;

	// Really?
	if (empty($string))
		return false;

	// Is this a closure?
	if ($string instanceof Closure)
		return $string;

	// Stay vitaminized my friends...
	$string = $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($string));

	// The soon to be populated var.
	$func = false;

	// Is there a file to load?
	$string = load_file($string);

	// Loaded file failed
	if (empty($string))
		return false;

	// Found a method.
	if (strpos($string, '::') !== false)
	{
		list ($class, $method) = explode('::', $string);

		// Check if a new object will be created.
		if (strpos($method, '#') !== false)
		{
			// Need to remove the # thing.
			$method = str_replace('#', '', $method);

			// Don't need to create a new instance for every method.
			if (empty($context['instances'][$class]) || !($context['instances'][$class] instanceof $class))
			{
				$context['instances'][$class] = new $class;

				// Add another one to the list.
				if ($db_show_debug === true)
				{
					if (!isset($context['debug']['instances']))
						$context['debug']['instances'] = array();

					$context['debug']['instances'][$class] = $class;
				}
			}

			$func = array($context['instances'][$class], $method);
		}

		// Right then. This is a call to a static method.
		else
			$func = array($class, $method);
	}

	// Nope! just a plain regular function.
	else
		$func = $string;

	// Right, we got what we need, time to do some checks.
	if (!is_callable($func, false, $callable_name))
	{
		loadLanguage('Errors');
		log_error(sprintf($txt['subAction_fail'], $callable_name), 'general');

		// Gotta tell everybody.
		return false;
	}

	// Everything went better than expected.
	else
	{
		// What are we gonna do about it?
		if ($return)
			return $func;

		// If this is a plain function, avoid the heat of calling call_user_func().
		else
		{
			if (is_array($func))
				call_user_func($func);

			else
				$func();
		}
	}
}

/**
 * Receives a string and tries to figure it out if it contains info to load a file.
 * Checks for a | (pipe) symbol and tries to load a file with the info given.
 * The string should be format as follows File.php|. You can use the following wildcards: $boarddir, $sourcedir and if available at the moment of execution, $themedir.
 *
 * @param string $string The string containing a valid format.
 * @return string|boolean The given string with the pipe and file info removed. Boolean false if the file couldn't be loaded.
 */
function load_file($string)
{
	global $sourcedir, $txt, $boarddir, $settings;

	if (empty($string))
		return false;

	if (strpos($string, '|') !== false)
	{
		list ($file, $string) = explode('|', $string);

		// Match the wildcards to their regular vars.
		if (empty($settings['theme_dir']))
			$absPath = strtr(trim($file), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));

		else
			$absPath = strtr(trim($file), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));

		// Load the file if it can be loaded.
		if (file_exists($absPath))
			require_once($absPath);

		// No? try a fallback to $sourcedir
		else
		{
			$absPath = $sourcedir .'/'. $file;

			if (file_exists($absPath))
				require_once($absPath);

			// Sorry, can't do much for you at this point.
			else
			{
				loadLanguage('Errors');
				log_error(sprintf($txt['hook_fail_loading_file'], $absPath), 'general');

				// File couldn't be loaded.
				return false;
			}
		}
	}

	return $string;
}

/**
 * Prepares an array of "likes" info for the topic specified by $topic
 * @param Integer $topic The topic ID to fetch the info from.
 * @return Array an array of IDs of messages in the specified topic that the current user likes
 */
function prepareLikesContext($topic)
{
	global $user_info, $smcFunc;

	// Make sure we have something to work with.
	if (empty($topic))
		return array();


	// We already know the number of likes per message, we just want to know whether the current user liked it or not.
	$user = $user_info['id'];
	$cache_key = 'likes_topic_' . $topic . '_' . $user;
	$ttl = 180;

	if (($temp = cache_get_data($cache_key, $ttl)) === null)
	{
		$temp = array();
		$request = $smcFunc['db_query']('', '
			SELECT content_id
			FROM {db_prefix}user_likes AS l
				INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
			WHERE l.id_member = {int:current_user}
				AND l.content_type = {literal:msg}
				AND m.id_topic = {int:topic}',
			array(
				'current_user' => $user,
				'topic' => $topic,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$temp[] = (int) $row['content_id'];

		cache_put_data($cache_key, $temp, $ttl);
	}

	return $temp;
}

/**
 * Microsoft uses their own character set Code Page 1252 (CP1252), which is a
 * superset of ISO 8859-1, defining several characters between DEC 128 and 159
 * that are not normally displayable.  This converts the popular ones that
 * appear from a cut and paste from windows.
 *
 * @param string $string
 * @return string $string
*/
function sanitizeMSCutPaste($string)
{
	global $context;

	if (empty($string))
		return $string;

	// UTF-8 occurences of MS special characters
	$findchars_utf8 = array(
		"\xe2\80\x9a",	// single low-9 quotation mark
		"\xe2\80\x9e",	// double low-9 quotation mark
		"\xe2\80\xa6",	// horizontal ellipsis
		"\xe2\x80\x98",	// left single curly quote
		"\xe2\x80\x99",	// right single curly quote
		"\xe2\x80\x9c",	// left double curly quote
		"\xe2\x80\x9d",	// right double curly quote
		"\xe2\x80\x93",	// en dash
		"\xe2\x80\x94",	// em dash
	);

	// windows 1252 / iso equivalents
	$findchars_iso = array(
		chr(130),
		chr(132),
		chr(133),
		chr(145),
		chr(146),
		chr(147),
		chr(148),
		chr(150),
		chr(151),
	);

	// safe replacements
	$replacechars = array(
		',',	// &sbquo;
		',,',	// &bdquo;
		'...',	// &hellip;
		"'",	// &lsquo;
		"'",	// &rsquo;
		'"',	// &ldquo;
		'"',	// &rdquo;
		'-',	// &ndash;
		'--',	// &mdash;
	);

	if ($context['utf8'])
		$string = str_replace($findchars_utf8, $replacechars, $string);
	else
		$string = str_replace($findchars_iso, $replacechars, $string);

	return $string;
}

/**
 * Decode numeric html entities to their ascii or UTF8 equivalent character.
 *
 * Callback function for preg_replace_callback in subs-members
 * Uses capture group 2 in the supplied array
 * Does basic scan to ensure characters are inside a valid range
 *
 * @param array $matches
 * @return string $string
*/
function replaceEntities__callback($matches)
{
	global $context;

	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// remove left to right / right to left overrides
	if ($num === 0x202D || $num === 0x202E)
		return '';

	// Quote, Ampersand, Apostrophe, Less/Greater Than get html replaced
	if (in_array($num, array(0x22, 0x26, 0x27, 0x3C, 0x3E)))
		return '&#' . $num . ';';

	if (empty($context['utf8']))
	{
		// no control characters
		if ($num < 0x20)
			return '';
		// text is text
		elseif ($num < 0x80)
			return chr($num);
		// all others get html-ised
		else
			return '&#' . $matches[2] . ';';
	}
	else
	{
		// <0x20 are control characters, 0x20 is a space, > 0x10FFFF is past the end of the utf8 character set
		// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text)
		if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF))
			return '';
		// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and punctuation
		elseif ($num < 0x80)
			return chr($num);
		// <0x800 (2048)
		elseif ($num < 0x800)
			return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
		// < 0x10000 (65536)
		elseif ($num < 0x10000)
			return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
		// <= 0x10FFFF (1114111)
		else
			return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	}
}

/**
 * Converts html entities to utf8 equivalents
 *
 * Callback function for preg_replace_callback
 * Uses capture group 1 in the supplied array
 * Does basic checks to keep characters inside a viewable range.
 *
 * @param array $matches
 * @return string $string
*/
function fixchar__callback($matches)
{
	if (!isset($matches[1]))
		return '';

	$num = $matches[1][0] === 'x' ? hexdec(substr($matches[1], 1)) : (int) $matches[1];

	// <0x20 are control characters, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text), 0x202D-E are left to right overrides
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202D || $num === 0x202E)
		return '';
	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and puncuation
	elseif ($num < 0x80)
		return chr($num);
	// <0x800 (2048)
	elseif ($num < 0x800)
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	// < 0x10000 (65536)
	elseif ($num < 0x10000)
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	// <= 0x10FFFF (1114111)
	else
		return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
}

/**
 * Strips out invalid html entities, replaces others with html style &#123; codes
 *
 * Callback function used of preg_replace_callback in smcFunc $ent_checks, for example
 * strpos, strlen, substr etc
 *
 * @param array $matches
 * @return string $string
*/
function entity_fix__callback($matches)
{
	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// we don't allow control characters, characters out of range, byte markers, etc
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
		return '';
	else
		return '&#' . $num . ';';
}

/**
 * Return a Gravatar URL based on
 * - the supplied email address,
 * - the global maximum rating,
 * - the global default fallback,
 * - maximum sizes as set in the admin panel.
 *
 * It is SSL aware, and caches most of the parameters.
 *
 * @param string $email_address
 * @return string
 */
function get_gravatar_url($email_address)
{
	global $modSettings, $smcFunc;
	static $url_params = null;

	if ($url_params === null)
	{
		$ratings = array('G', 'PG', 'R', 'X');
		$defaults = array('mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank');
		$url_params = array();
		if (!empty($modSettings['gravatarMaxRating']) && in_array($modSettings['gravatarMaxRating'], $ratings))
			$url_params[] = 'rating=' . $modSettings['gravatarMaxRating'];
		if (!empty($modSettings['gravatarDefault']) && in_array($modSettings['gravatarDefault'], $defaults))
			$url_params[] = 'default=' . $modSettings['gravatarDefault'];
		if (!empty($modSettings['avatar_max_width_external']))
			$size_string = (int) $modSettings['avatar_max_width_external'];
		if (!empty($modSettings['avatar_max_height_external']) && !empty($size_string))
			if ((int) $modSettings['avatar_max_height_external'] < $size_string)
				$size_string = $modSettings['avatar_max_height_external'];

		if (!empty($size_string))
			$url_params[] = 's=' . $size_string;
	}
	$http_method = !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] == 2 ? 'https://secure' : 'http://www';

	return $http_method . '.gravatar.com/avatar/' . md5($smcFunc['strtolower']($email_address)) . '?' . implode('&', $url_params);
}

/**
* Get a list of timezoned.
*
* @return array
*/
function smf_list_timezones()
{
	return array(
		'' => '(Forum Default)',
		'UTC' => '[UTC] UTC',
		'Pacific/Midway' => '[UTC-11:00] Midway Island, Samoa',
		'America/Adak' => '[UTC-10:00] Hawaii-Aleutian',
		'Pacific/Honolulu' => '[UTC-10:00] Hawaii',
		'Pacific/Marquesas' => '[UTC-09:30] Marquesas Islands',
		'Pacific/Gambier' => '[UTC-09:00] Gambier Islands',
		'America/Anchorage' => '[UTC-09:00] Alaska',
		'America/Ensenada' => '[UTC-08:00] Tijuana, Baja California',
		'Pacific/Pitcairn' => '[UTC-08:00] Pitcairn Islands',
		'America/Los_Angeles' => '[UTC-08:00] Pacific Time (USA, Canada)',
		'America/Denver' => '[UTC-07:00] Mountain Time (USA, Canada)',
		'America/Phoenix' => '[UTC-07:00] Arizona',
		'America/Chihuahua' => '[UTC-07:00] Chihuahua, Mazatlan',
		'America/Belize' => '[UTC-06:00] Saskatchewan, Central America',
		'America/Cancun' => '[UTC-06:00] Guadalajara, Mexico City, Monterrey',
		'Chile/EasterIsland' => '[UTC-06:00] Easter Island',
		'America/Chicago' => '[UTC-06:00] Central Time (USA, Canada)',
		'America/New_York' => '[UTC-05:00] Eastern Time (USA, Canada)',
		'America/Havana' => '[UTC-05:00] Cuba',
		'America/Bogota' => '[UTC-05:00] Bogota, Lima, Quito',
		'America/Caracas' => '[UTC-04:30] Caracas',
		'America/Santiago' => '[UTC-04:00] Santiago',
		'America/La_Paz' => '[UTC-04:00] La Paz, San Juan, Manaus',
		'Atlantic/Stanley' => '[UTC-04:00] Falkland Islands',
		'America/Cuiaba' => '[UTC-04:00] Cuiaba',
		'America/Goose_Bay' => '[UTC-04:00] Atlantic Time (Goose Bay)',
		'America/Glace_Bay' => '[UTC-04:00] Atlantic Time (Canada)',
		'America/St_Johns' => '[UTC-03:30] Newfoundland',
		'America/Araguaina' => '[UTC-03:00] Araguaina',
		'America/Montevideo' => '[UTC-03:00] Montevideo',
		'America/Miquelon' => '[UTC-03:00] Saint Pierre and Miquelon',
		'America/Argentina/Buenos_Aires' => '[UTC-03:00] Buenos Aires',
		'America/Sao_Paulo' => '[UTC-03:00] Brasilia',
		'America/Godthab' => '[UTC-02:00] Greenland',
		'America/Noronha' => '[UTC-02:00] Fernando de Noronha',
		'Atlantic/Cape_Verde' => '[UTC-01:00] Cape Verde',
		'Atlantic/Azores' => '[UTC-01:00] Azores',
		'Africa/Abidjan' => '[UTC] Monrovia, Reykjavik',
		'Europe/London' => '[UTC] London, Edinburgh, Dublin, Lisbon (Greenwich Mean Time)',
		'Europe/Brussels' => '[UTC+01:00] Central European Time',
		'Africa/Algiers' => '[UTC+01:00] West Central Africa',
		'Africa/Windhoek' => '[UTC+01:00] Windhoek',
		'Africa/Cairo' => '[UTC+02:00] Cairo',
		'Africa/Blantyre' => '[UTC+02:00] Harare, Maputo, Pretoria',
		'Asia/Jerusalem' => '[UTC+02:00] Jerusalem',
		'Europe/Minsk' => '[UTC+02:00] Minsk',
		'Asia/Damascus' => '[UTC+02:00] Damascus, Nicosia, Gaza, Beirut',
		'Africa/Addis_Ababa' => '[UTC+03:00] Addis Ababa, Nairobi',
		'Asia/Tehran' => '[UTC+03:30] Tehran',
		'Europe/Moscow' => '[UTC+04:00] Moscow, St. Petersburg, Volgograd',
		'Asia/Dubai' => '[UTC+04:00] Abu Dhabi, Muscat',
		'Asia/Baku' => '[UTC+04:00] Baku',
		'Asia/Yerevan' => '[UTC+04:00] Yerevan',
		'Asia/Kabul' => '[UTC+04:30] Kabul',
		'Asia/Tashkent' => '[UTC+05:00] Tashkent',
		'Asia/Kolkata' => '[UTC+05:30] Chennai, Kolkata, Mumbai, New Delhi',
		'Asia/Katmandu' => '[UTC+05:45] Kathmandu',
		'Asia/Yekaterinburg' => '[UTC+06:00] Yekaterinburg, Tyumen',
		'Asia/Dhaka' => '[UTC+06:00] Astana, Thimphu, Dhaka',
		'Asia/Novosibirsk' => '[UTC+06:00] Omsk, Novosibirsk',
		'Asia/Rangoon' => '[UTC+06:30] Yangon Rangoon',
		'Asia/Bangkok' => '[UTC+07:00] Bangkok, Hanoi, Jakarta',
		'Asia/Krasnoyarsk' => '[UTC+08:00] Krasnoyarsk',
		'Asia/Hong_Kong' => '[UTC+08:00] Beijing, Chongqing, Hong Kong, Urumqi',
		'Asia/Ulaanbaatar' => '[UTC+08:00] Ulaan Bataar',
		'Asia/Irkutsk' => '[UTC+09:00] Irkutsk',
		'Australia/Perth' => '[UTC+08:00] Perth',
		'Australia/Eucla' => '[UTC+08:45] Eucla',
		'Asia/Tokyo' => '[UTC+09:00] Tokyo, Osaka, Sapporo',
		'Asia/Seoul' => '[UTC+09:00] Seoul',
		'Australia/Adelaide' => '[UTC+09:30] Adelaide',
		'Australia/Darwin' => '[UTC+09:30] Darwin',
		'Australia/Brisbane' => '[UTC+10:00] Brisbane, Guam',
		'Australia/Sydney' => '[UTC+10:00] Sydney, Hobart',
		'Asia/Yakutsk' => '[UTC+10:00] Yakutsk',
		'Australia/Lord_Howe' => '[UTC+10:30] Lord Howe Island',
		'Asia/Vladivostok' => '[UTC+11:00] Vladivostok',
		'Pacific/Noumea' => '[UTC+11:00] Solomon Islands, New Caledonia',
		'Pacific/Norfolk' => '[UTC+11:30] Norfolk Island',
		'Pacific/Auckland' => '[UTC+12:00] Auckland, Wellington',
		'Asia/Magadan' => '[UTC+12:00] Magadan, Kamchatka, Anadyr',
		'Pacific/Fiji' => '[UTC+12:00] Fiji',
		'Pacific/Majuro' => '[UTC+12:00] Marshall Islands',
		'Pacific/Chatham' => '[UTC+12:45] Chatham Islands',
		'Pacific/Tongatapu' => '[UTC+13:00] Nuku\'alofa',
		'Pacific/Kiritimati' => '[UTC+14:00] Kiritimati',
	);
}

?>
