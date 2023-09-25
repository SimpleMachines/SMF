<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.4
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
 * @param mixed $parameter1 A parameter for updating the stats
 * @param mixed $parameter2 A 2nd parameter for updating the stats
 */
function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	global $modSettings, $smcFunc, $txt;

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

				// Update the amount of members awaiting approval
				$result = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}members
					WHERE is_activated IN ({array_int:activation_status})',
					array(
						'activation_status' => array(3, 4, 5),
					)
				);

				list ($changes['unapprovedMembers']) = $smcFunc['db_fetch_row']($result);
				$smcFunc['db_free_result']($result);
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
			loadLanguage('Errors');
			trigger_error(sprintf($txt['invalid_statistic_type'], $type), E_USER_NOTICE);
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
 * @param mixed $members An array of member IDs, the ID of a single member, or null to update this for all members
 * @param array $data The info to update for the members
 */
function updateMemberData($members, $data)
{
	global $modSettings, $user_info, $smcFunc, $sourcedir, $cache_enable;

	// An empty array means there's nobody to update.
	if ($members === array())
		return;

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
		'new_pm', 'pm_prefs', 'gender', 'show_online', 'pm_receive_from', 'alerts',
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
			'timezone',
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
		switch ($var)
		{
			case  'birthdate':
				$type = 'date';
				break;

			case 'member_ip':
			case 'member_ip2':
				$type = 'inet';
				break;

			default:
				$type = 'string';
		}

		if (in_array($var, $knownInts))
			$type = 'int';

		elseif (in_array($var, $knownFloats))
			$type = 'float';

		// Doing an increment?
		if ($var == 'alerts' && ($val === '+' || $val === '-'))
		{
			include_once($sourcedir . '/Profile-Modify.php');
			if (is_array($members))
			{
				$val = 'CASE ';
				foreach ($members as $k => $v)
					$val .= 'WHEN id_member = ' . $v . ' THEN '. alert_count($v, true) . ' ';

				$val = $val . ' END';
				$type = 'raw';
			}

			else
				$val = alert_count($members, true);
		}

		elseif ($type == 'int' && ($val === '+' || $val === '-'))
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
	if (!empty($cache_enable) && $cache_enable >= 2 && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($cache_enable >= 3)
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
 * @param array $changeArray An array of info about what we're changing in 'setting' => 'value' format
 * @param bool $update Whether to use an UPDATE query instead of a REPLACE query
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

	// $start must be within bounds and be a multiple of $num_per_page.
	$start = min(max(0, $start), $max_value);
	$start = $start - ($start % $num_per_page);

	if (!isset($context['current_page']))
		$context['current_page'] = $start / $num_per_page;

	// Define some default page index settings for compatibility with old themes.
	// !!! Should this be moved to loadTheme()?
	if (!isset($settings['page_index']))
		$settings['page_index'] = array(
			'extra_before' => '<span class="pages">' . $txt['pages'] . '</span>',
			'previous_page' => '<span class="main_icons previous_page"></span>',
			'current_page' => '<span class="current_page">%1$d</span> ',
			'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
			'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
			'next_page' => '<span class="main_icons next_page"></span>',
			'extra_after' => '',
		);

	$last_page_value = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
	$base_link = strtr($settings['page_index']['page'], array('{URL}' => $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d'));
	$pageindex = $settings['page_index']['extra_before'];

	// Show the "prev page" link. (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
	if ($start != 0 && !$start_invalid && $show_prevnext)
		$pageindex .= sprintf($base_link, $start - $num_per_page, $settings['page_index']['previous_page']);

	// Compact pages is off or on?
	if (empty($modSettings['compactTopicPagesEnable']))
	{
		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? sprintf($settings['page_index']['current_page'], $display_page++) : sprintf($base_link, $counter, $display_page++);
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$page_contiguous = (int) ($modSettings['compactTopicPagesContiguous'] - ($modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Show the first page. (prev page >1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $page_contiguous)
			$pageindex .= sprintf($base_link, 0, '1');

		// Show the ... after the first page.  (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
		if ($start > $num_per_page * ($page_contiguous + 1))
			$pageindex .= strtr($settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape($smcFunc['htmlspecialchars']($base_link)),
				'{FIRST_PAGE}' => $num_per_page,
				'{LAST_PAGE}' => $start - $num_per_page * $page_contiguous,
				'{PER_PAGE}' => $num_per_page,
			));

		for ($nCont = -$page_contiguous; $nCont <= $page_contiguous; $nCont++)
		{
			$tmpStart = $start + $num_per_page * $nCont;
			if ($nCont == 0)
			{
				// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
				if (!$start_invalid)
					$pageindex .= sprintf($settings['page_index']['current_page'], $start / $num_per_page + 1);
				else
					$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);
			}
			// Show the pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
			// ... or ...
			// Show the pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
			elseif (($nCont < 0 && $start >= $num_per_page * -$nCont) || ($nCont > 0 && $tmpStart <= $last_page_value))
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}

		// Show the '...' part near the end. (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
		if ($start + $num_per_page * ($page_contiguous + 1) < $last_page_value)
			$pageindex .= strtr($settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape($smcFunc['htmlspecialchars']($base_link)),
				'{FIRST_PAGE}' => $start + $num_per_page * ($page_contiguous + 1),
				'{LAST_PAGE}' => $last_page_value,
				'{PER_PAGE}' => $num_per_page,
			));

		// Show the last number in the list. (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
		if ($start + $num_per_page * $page_contiguous < $last_page_value)
			$pageindex .= sprintf($base_link, $last_page_value, $last_page_value / $num_per_page + 1);
	}

	// Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
	if ($start != $last_page_value && !$start_invalid && $show_prevnext)
		$pageindex .= sprintf($base_link, $start + $num_per_page, $settings['page_index']['next_page']);

	$pageindex .= $settings['page_index']['extra_after'];

	return $pageindex;
}

/**
 * - Formats a number.
 * - uses the format of number_format to decide how to format the number.
 *   for example, it might display "1 234,50".
 * - caches the formatting data from the setting for optimization.
 *
 * @param float $number A number
 * @param bool|int $override_decimal_count If set, will use the specified number of decimal places. Otherwise it's automatically determined
 * @return string A formatted number
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
 * @param int $log_time A timestamp
 * @param bool|string $show_today Whether to show "Today"/"Yesterday" or just a date.
 *     If a string is specified, that is used to temporarily override the date format.
 * @param null|string $tzid Time zone to use when generating the formatted string.
 *     If empty, the user's time zone will be used.
 *     If set to 'forum', the value of $modSettings['default_timezone'] will be used.
 *     If set to a valid time zone identifier, that will be used.
 *     Otherwise, the value of date_default_timezone_get() will be used.
 * @return string A formatted time string
 */
function timeformat($log_time, $show_today = true, $tzid = null)
{
	global $context, $user_info, $txt, $modSettings;
	static $today;

	// Ensure required values are set
	$user_info['time_format'] = !empty($user_info['time_format']) ? $user_info['time_format'] : (!empty($modSettings['time_format']) ? $modSettings['time_format'] : '%F %H:%M');

	// For backward compatibility, replace empty values with user's time zone
	// and replace 'forum' with forum's default time zone.
	$tzid = empty($tzid) ? getUserTimezone() : (($tzid === 'forum' || @timezone_open((string) $tzid) === false) ? $modSettings['default_timezone'] : (string) $tzid);

	// Today and Yesterday?
	$prefix = '';
	if ($modSettings['todayMod'] >= 1 && $show_today === true)
	{
		if (!isset($today[$tzid]))
			$today[$tzid] = date_format(date_create('today ' . $tzid), 'U');

		// Tomorrow? We don't support the future. ;)
		if ($log_time >= $today[$tzid] + 86400)
		{
			$prefix = '';
		}
		// Today.
		elseif ($log_time >= $today[$tzid])
		{
			$prefix = $txt['today'];
		}
		// Yesterday.
		elseif ($modSettings['todayMod'] > 1 && $log_time >= $today[$tzid] - 86400)
		{
			$prefix = $txt['yesterday'];
		}
	}

	// If $show_today is not a bool, use it as the date format & don't use $user_info. Allows for temp override of the format.
	$format = !is_bool($show_today) ? $show_today : $user_info['time_format'];

	$format = !empty($prefix) ? get_date_or_time_format('time', $format) : $format;

	// And now, the moment we've all be waiting for...
	return $prefix . smf_strftime($format, $log_time, $tzid);
}

/**
 * Gets a version of a strftime() format that only shows the date or time components
 *
 * @param string $type Either 'date' or 'time'.
 * @param string $format A strftime() format to process. Defaults to $user_info['time_format'].
 * @return string A strftime() format string
 */
function get_date_or_time_format($type = '', $format = '')
{
	global $user_info, $modSettings;
	static $formats;

	// If the format is invalid, fall back to defaults.
	if (strpos($format, '%') === false)
		$format = !empty($user_info['time_format']) ? $user_info['time_format'] : (!empty($modSettings['time_format']) ? $modSettings['time_format'] : '%F %k:%M');

	$orig_format = $format;

	// Have we already done this?
	if (isset($formats[$orig_format][$type]))
		return $formats[$orig_format][$type];

	if ($type === 'date')
	{
		$specifications = array(
			// Day
			'%a' => '%a', '%A' => '%A', '%e' => '%e', '%d' => '%d', '%j' => '%j', '%u' => '%u', '%w' => '%w',
			// Week
			'%U' => '%U', '%V' => '%V', '%W' => '%W',
			// Month
			'%b' => '%b', '%B' => '%B', '%h' => '%h', '%m' => '%m',
			// Year
			'%C' => '%C', '%g' => '%g', '%G' => '%G', '%y' => '%y', '%Y' => '%Y',
			// Time
			'%H' => '', '%k' => '', '%I' => '', '%l' => '', '%M' => '', '%p' => '', '%P' => '',
			'%r' => '', '%R' => '', '%S' => '', '%T' => '', '%X' => '', '%z' => '', '%Z' => '',
			// Time and Date Stamps
			'%c' => '%x', '%D' => '%D', '%F' => '%F', '%s' => '%s', '%x' => '%x',
			// Miscellaneous
			'%n' => '', '%t' => '', '%%' => '%%',
		);

		$default_format = '%F';
	}
	elseif ($type === 'time')
	{
		$specifications = array(
			// Day
			'%a' => '', '%A' => '', '%e' => '', '%d' => '', '%j' => '', '%u' => '', '%w' => '',
			// Week
			'%U' => '', '%V' => '', '%W' => '',
			// Month
			'%b' => '', '%B' => '', '%h' => '', '%m' => '',
			// Year
			'%C' => '', '%g' => '', '%G' => '', '%y' => '', '%Y' => '',
			// Time
			'%H' => '%H', '%k' => '%k', '%I' => '%I', '%l' => '%l', '%M' => '%M', '%p' => '%p', '%P' => '%P',
			'%r' => '%r', '%R' => '%R', '%S' => '%S', '%T' => '%T', '%X' => '%X', '%z' => '%z', '%Z' => '%Z',
			// Time and Date Stamps
			'%c' => '%X', '%D' => '', '%F' => '', '%s' => '%s', '%x' => '',
			// Miscellaneous
			'%n' => '', '%t' => '', '%%' => '%%',
		);

		$default_format = '%k:%M';
	}
	// Invalid type requests just get the full format string.
	else
		return $format;

	// Separate the specifications we want from the ones we don't.
	$wanted = array_filter($specifications);
	$unwanted = array_diff(array_keys($specifications), $wanted);

	// First, make any necessary substitutions in the format.
	$format = strtr($format, $wanted);

	// Next, strip out any specifications and literal text that we don't want.
	$format_parts = preg_split('~%[' . (strtr(implode('', $unwanted), array('%' => ''))) . ']~u', $format);

	foreach ($format_parts as $p => $f)
	{
		if (strpos($f, '%') === false)
			unset($format_parts[$p]);
	}

	$format = implode('', $format_parts);

	// Finally, strip out any unwanted leftovers.
	// For info on the charcter classes used here, see https://www.php.net/manual/en/regexp.reference.unicode.php and https://www.regular-expressions.info/unicode.html
	$format = preg_replace(
		array(
			// Anything that isn't a specification, punctuation mark, or whitespace.
			'~(?<!%)\p{L}|[^\p{L}\p{P}\s]~u',
			// Repeated punctuation marks (except %), possibly separated by whitespace.
			'~(?'.'>([^%\P{P}])\s*(?=\1))*~u',
			'~([^%\P{P}])(?'.'>\1(?!$))*~u',
			// Unwanted trailing punctuation and whitespace.
			'~(?'.'>([\p{Pd}\p{Ps}\p{Pi}\p{Pc}]|[^%\P{Po}])\s*)*$~u',
			// Unwanted opening punctuation and whitespace.
			'~^\s*(?'.'>([\p{Pd}\p{Pe}\p{Pf}\p{Pc}]|[^%\P{Po}])\s*)*~u',
			// Runs of horizontal whitespace.
			'~\s+~',
		),
		array(
			'',
			'$1',
			'$1$2',
			'',
			'',
			' ',
		),
		$format
	);

	// Gotta have something...
	if (empty($format))
		$format = $default_format;

	// Remember what we've done.
	$formats[$orig_format][$type] = trim($format);

	return $formats[$orig_format][$type];
}

/**
 * Replacement for strftime() that is compatible with PHP 8.1+.
 *
 * This does not use the system's strftime library or locale setting,
 * so results may vary in a few cases from the results of strftime():
 *
 *  - %a, %A, %b, %B, %p, %P: Output will use SMF's language strings
 *    to localize these values. If SMF's language strings have not
 *    been loaded, PHP's default English strings will be used.
 *
 *  - %c, %x, %X: Output will always use ISO format.
 *
 * @param string $format A strftime() format string.
 * @param int|null $timestamp A Unix timestamp.
 *     If null, defaults to the current time.
 * @param string|null $tzid Time zone identifier.
 *     If null, uses default time zone.
 * @return string The formatted datetime string.
 */
function smf_strftime(string $format, int $timestamp = null, string $tzid = null)
{
	global $txt, $smcFunc, $sourcedir;

	static $dates = array();

	// Set default values as necessary.
	if (!isset($timestamp))
		$timestamp = time();

	if (!isset($tzid))
		$tzid = date_default_timezone_get();

	// A few substitutions to make life easier.
	$format = strtr($format, array(
		'%h' => '%b',
		'%r' => '%I:%M:%S %p',
		'%R' => '%H:%M',
		'%T' => '%H:%M:%S',
		'%X' => '%H:%M:%S',
		'%D' => '%m/%d/%y',
		'%F' => '%Y-%m-%d',
		'%x' => '%Y-%m-%d',
	));

	// Avoid unnecessary repetition.
	if (isset($dates[$tzid . '_' . $timestamp]['results'][$format]))
		return $dates[$tzid . '_' . $timestamp]['results'][$format];

	// Ensure the TZID is valid.
	if (($tz = @timezone_open($tzid)) === false)
	{
		$tzid = date_default_timezone_get();

		// Check again now that we have a valid TZID.
		if (isset($dates[$tzid . '_' . $timestamp]['results'][$format]))
			return $dates[$tzid . '_' . $timestamp]['results'][$format];

		$tz = timezone_open($tzid);
	}

	// Create the DateTime object and set its time zone.
	if (!isset($dates[$tzid . '_' . $timestamp]['object']))
	{
		$dates[$tzid . '_' . $timestamp]['object'] = date_create('@' . $timestamp);
		date_timezone_set($dates[$tzid . '_' . $timestamp]['object'], $tz);
	}

	// In case this function is called before reloadSettings().
	if (!isset($smcFunc['strtoupper']))
	{
		if (isset($sourcedir))
		{
			require_once($sourcedir . '/Subs-Charset.php');
			$smcFunc['strtoupper'] = 'utf8_strtoupper';
			$smcFunc['strtolower'] = 'utf8_strtolower';
		}
		elseif (function_exists('mb_strtoupper'))
		{
			$smcFunc['strtoupper'] = 'mb_strtoupper';
			$smcFunc['strtolower'] = 'mb_strtolower';
		}
		else
		{
			$smcFunc['strtoupper'] = 'strtoupper';
			$smcFunc['strtolower'] = 'strtolower';
		}
	}

	$format_equivalents = array(
		// Day
		'a' => 'D', // Complex: prefer $txt strings if available.
		'A' => 'l', // Complex: prefer $txt strings if available.
		'e' => 'j', // Complex: sprintf to prepend whitespace.
		'd' => 'd',
		'j' => 'z', // Complex: must add one and then sprintf to prepend zeros.
		'u' => 'N',
		'w' => 'w',
		// Week
		'U' => 'z_w_0', // Complex: calculated from these other values.
		'V' => 'W',
		'W' => 'z_w_1', // Complex: calculated from these other values.
		// Month
		'b' => 'M', // Complex: prefer $txt strings if available.
		'B' => 'F', // Complex: prefer $txt strings if available.
		'm' => 'm',
		// Year
		'C' => 'Y', // Complex: Get 'Y' then truncate to first two digits.
		'g' => 'o', // Complex: Get 'o' then truncate to last two digits.
		'G' => 'o', // Complex: Get 'o' then sprintf to ensure four digits.
		'y' => 'y',
		'Y' => 'Y',
		// Time
		'H' => 'H',
		'k' => 'G',
		'I' => 'h',
		'l' => 'g', // Complex: sprintf to prepend whitespace.
		'M' => 'i',
		'p' => 'A', // Complex: prefer $txt strings if available.
		'P' => 'a', // Complex: prefer $txt strings if available.
		'S' => 's',
		'z' => 'O',
		'Z' => 'T',
		// Time and Date Stamps
		'c' => 'c',
		's' => 'U',
		// Miscellaneous
		'n' => "\n",
		't' => "\t",
		'%' => '%',
	);

	// Translate from strftime format to DateTime format.
	$parts = preg_split('/%(' . implode('|', array_keys($format_equivalents)) . ')/', $format, 0, PREG_SPLIT_DELIM_CAPTURE);

	$placeholders = array();
	$complex = false;

	for ($i = 0; $i < count($parts); $i++)
	{
		// Parts that are not strftime formats.
		if ($i % 2 === 0 || !isset($format_equivalents[$parts[$i]]))
		{
			if ($parts[$i] === '')
				continue;

			$placeholder = "\xEE\x84\x80" . $i . "\xEE\x84\x81";

			$placeholders[$placeholder] = $parts[$i];
			$parts[$i] = $placeholder;
		}
		// Parts that need localized strings.
		elseif (in_array($parts[$i], array('a', 'A', 'b', 'B')))
		{
			switch ($parts[$i])
			{
				case 'a':
					$min = 0;
					$max = 6;
					$key = 'days_short';
					$f = 'w';
					$placeholder_end = "\xEE\x84\x83";

					break;

				case 'A':
					$min = 0;
					$max = 6;
					$key = 'days';
					$f = 'w';
					$placeholder_end = "\xEE\x84\x82";

					break;

				case 'b':
					$min = 1;
					$max = 12;
					$key = 'months_short';
					$f = 'n';
					$placeholder_end = "\xEE\x84\x85";

					break;

				case 'B':
					$min = 1;
					$max = 12;
					$key = 'months';
					$f = 'n';
					$placeholder_end = "\xEE\x84\x84";

					break;
			}

			$placeholder = "\xEE\x84\x80" . $f . $placeholder_end;

			// Check whether $txt contains all expected strings.
			// If not, use English default.
			$txt_strings_exist = true;
			for ($num = $min; $num <= $max; $num++)
			{
				if (!isset($txt[$key][$num]))
				{
					$txt_strings_exist = false;
					break;
				}
				else
					$placeholders[str_replace($f, $num, $placeholder)] = $txt[$key][$num];
			}

			$parts[$i] = $txt_strings_exist ? $placeholder : $format_equivalents[$parts[$i]];
		}
		elseif (in_array($parts[$i], array('p', 'P')))
		{
			if (!isset($txt['time_am']) || !isset($txt['time_pm']))
				continue;

			$placeholder = "\xEE\x84\x90" . $format_equivalents[$parts[$i]] . "\xEE\x84\x91";

			switch ($parts[$i])
			{
				// Lower case
				case 'p':
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'AM', $placeholder)] = $smcFunc['strtoupper']($txt['time_am']);
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'PM', $placeholder)] = $smcFunc['strtoupper']($txt['time_pm']);
					break;

				// Upper case
				case 'P':
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'am', $placeholder)] = $smcFunc['strtolower']($txt['time_am']);
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'pm', $placeholder)] = $smcFunc['strtolower']($txt['time_pm']);
					break;
			}

			$parts[$i] = $placeholder;
		}
		// Parts that will need further processing.
		elseif (in_array($parts[$i], array('j', 'C', 'U', 'W', 'G', 'g', 'e', 'l')))
		{
			$complex = true;

			switch ($parts[$i])
			{
				case 'j':
					$placeholder_end = "\xEE\x84\xA1";
					break;

				case 'C':
					$placeholder_end = "\xEE\x84\xA2";
					break;

				case 'U':
				case 'W':
					$placeholder_end = "\xEE\x84\xA3";
					break;

				case 'G':
					$placeholder_end = "\xEE\x84\xA4";
					break;

				case 'g':
					$placeholder_end = "\xEE\x84\xA5";
					break;

				case 'e':
				case 'l':
					$placeholder_end = "\xEE\x84\xA6";
			}

			$parts[$i] = "\xEE\x84\xA0" . $format_equivalents[$parts[$i]] . $placeholder_end;
		}
		// Parts with simple equivalents.
		else
			$parts[$i] = $format_equivalents[$parts[$i]];
	}

	// The main event.
	$dates[$tzid . '_' . $timestamp]['results'][$format] = strtr(date_format($dates[$tzid . '_' . $timestamp]['object'], implode('', $parts)), $placeholders);

	// Deal with the complicated ones.
	if ($complex)
	{
		$dates[$tzid . '_' . $timestamp]['results'][$format] = preg_replace_callback(
			'/\xEE\x84\xA0([\d_]+)(\xEE\x84(?:[\xA1-\xAF]))/',
			function ($matches)
			{
				switch ($matches[2])
				{
					// %j
					case "\xEE\x84\xA1":
						$replacement = sprintf('%03d', (int) $matches[1] + 1);
						break;

					// %C
					case "\xEE\x84\xA2":
						$replacement = substr(sprintf('%04d', $matches[1]), 0, 2);
						break;

					// %U and %W
					case "\xEE\x84\xA3":
						list($day_of_year, $day_of_week, $first_day) = explode('_', $matches[1]);
						$replacement = sprintf('%02d', floor(((int) $day_of_year - (int) $day_of_week + (int) $first_day) / 7) + 1);
						break;

					// %G
					case "\xEE\x84\xA4":
						$replacement = sprintf('%04d', $matches[1]);
						break;

					// %g
					case "\xEE\x84\xA5":
						$replacement = substr(sprintf('%04d', $matches[1]), -2);
						break;

					// %e and %l
					case "\xEE\x84\xA6":
						$replacement = sprintf('%2d', $matches[1]);
						break;

					// Shouldn't happen, but just in case...
					default:
						$replacement = $matches[1];
						break;
				}

				return $replacement;
			},
			$dates[$tzid . '_' . $timestamp]['results'][$format]
		);
	}

	return $dates[$tzid . '_' . $timestamp]['results'][$format];
}

/**
 * Replacement for gmstrftime() that is compatible with PHP 8.1+.
 *
 * Calls smf_strftime() with the $tzid parameter set to 'UTC'.
 *
 * @param string $format A strftime() format string.
 * @param int|null $timestamp A Unix timestamp.
 *     If null, defaults to the current time.
 * @return string The formatted datetime string.
 */
function smf_gmstrftime(string $format, int $timestamp = null)
{
	return smf_strftime($format, $timestamp, 'UTC');
}

/**
 * Replaces special entities in strings with the real characters.
 *
 * Functionally equivalent to htmlspecialchars_decode(), except that this also
 * replaces '&nbsp;' with a simple space character.
 *
 * @param string $string A string
 * @return string The string without entities
 */
function un_htmlspecialchars($string)
{
	global $context;
	static $translation = array();

	// Determine the character set... Default to UTF-8
	if (empty($context['character_set']))
		$charset = 'UTF-8';
	// Use ISO-8859-1 in place of non-supported ISO-8859 charsets...
	elseif (strpos($context['character_set'], 'ISO-8859-') !== false && !in_array($context['character_set'], array('ISO-8859-5', 'ISO-8859-15')))
		$charset = 'ISO-8859-1';
	else
		$charset = $context['character_set'];

	if (empty($translation))
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES, $charset)) + array('&#039;' => '\'', '&#39;' => '\'', '&nbsp;' => ' ');

	return strtr($string, $translation);
}

/**
 * Replaces invalid characters with a substitute.
 *
 * !!! Warning !!! Setting $substitute to '' in order to delete invalid
 * characters from the string can create unexpected security problems. See
 * https://www.unicode.org/reports/tr36/#Deletion_of_Noncharacters for an
 * explanation.
 *
 * @param string $string The string to sanitize.
 * @param int $level Controls filtering of invisible formatting characters.
 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
 *      2: Disallow all formatting characters. Use for internal comparisons
 *         only, such as in the word censor, search contexts, etc.
 *      Default: 0.
 * @param string|null $substitute Replacement string for the invalid characters.
 *      If not set, the Unicode replacement character (U+FFFD) will be used
 *      (or a fallback like "?" if necessary).
 * @return string The sanitized string.
 */
function sanitize_chars($string, $level = 0, $substitute = null)
{
	global $context, $sourcedir;

	$string = (string) $string;
	$level = min(max((int) $level, 0), 2);

	// What substitute character should we use?
	if (isset($substitute))
	{
		$substitute = strval($substitute);
	}
	elseif (!empty($context['utf8']))
	{
		// Raw UTF-8 bytes for U+FFFD.
		$substitute = "\xEF\xBF\xBD";
	}
	elseif (!empty($context['character_set']) && is_callable('mb_decode_numericentity'))
	{
		// Get whatever the default replacement character is for this encoding.
		$substitute = mb_decode_numericentity('&#xFFFD;', array(0xFFFD,0xFFFD,0,0xFFFF), $context['character_set']);
	}
	else
		$substitute = '?';

	// Fix any invalid byte sequences.
	if (!empty($context['character_set']))
	{
		// For UTF-8, this preg_match test is much faster than mb_check_encoding.
		$malformed = !empty($context['utf8']) ? @preg_match('//u', $string) === false && preg_last_error() === PREG_BAD_UTF8_ERROR : (!is_callable('mb_check_encoding') || !mb_check_encoding($string, $context['character_set']));

		if ($malformed)
		{
			// mb_convert_encoding will replace invalid byte sequences with our substitute.
			if (is_callable('mb_convert_encoding'))
			{
				if (!is_callable('mb_ord'))
					require_once($sourcedir . '/Subs-Compat.php');

				$substitute_ord = $substitute === '' ? 'none' : mb_ord($substitute, $context['character_set']);

				$mb_substitute_character = mb_substitute_character();
				mb_substitute_character($substitute_ord);

				$string = mb_convert_encoding($string, $context['character_set'], $context['character_set']);

				mb_substitute_character($mb_substitute_character);
			}
			else
				return false;
		}
	}

	// Fix any weird vertical space characters.
	$string = normalize_spaces($string, true);

	// Deal with unwanted control characters, invisible formatting characters, and other creepy-crawlies.
	if (!empty($context['utf8']))
	{
		require_once($sourcedir . '/Subs-Charset.php');
		$string = utf8_sanitize_invisibles($string, $level, $substitute);
	}
	else
		$string = preg_replace('/[^\P{Cc}\t\r\n]/', $substitute, $string);

	return $string;
}

/**
 * Normalizes space characters and line breaks.
 *
 * @param string $string The string to sanitize.
 * @param bool $vspace If true, replaces all line breaks and vertical space
 *      characters with "\n". Default: true.
 * @param bool $hspace If true, replaces horizontal space characters with a
 *      plain " " character. (Note: tabs are not replaced unless the
 *      'replace_tabs' option is supplied.) Default: false.
 * @param array $options An array of boolean options. Possible values are:
 *      - no_breaks: Vertical spaces are replaced by " " instead of "\n".
 *      - replace_tabs: If true, tabs are are replaced by " " chars.
 *      - collapse_hspace: If true, removes extra horizontal spaces.
 * @return string The sanitized string.
 */
function normalize_spaces($string, $vspace = true, $hspace = false, $options = array())
{
	global $context;

	$string = (string) $string;
	$vspace = !empty($vspace);
	$hspace = !empty($hspace);

	if (!$vspace && !$hspace)
		return $string;

	$options['no_breaks'] = !empty($options['no_breaks']);
	$options['collapse_hspace'] = !empty($options['collapse_hspace']);
	$options['replace_tabs'] = !empty($options['replace_tabs']);

	$patterns = array();
	$replacements = array();

	if ($vspace)
	{
		// \R is like \v, except it handles "\r\n" as a single unit.
		$patterns[] = '/\R/' . ($context['utf8'] ? 'u' : '');
		$replacements[] = $options['no_breaks'] ? ' ' : "\n";
	}

	if ($hspace)
	{
		// Interesting fact: Unicode properties like \p{Zs} work even when not in UTF-8 mode.
		$patterns[] = '/' . ($options['replace_tabs'] ? '\h' : '\p{Zs}') . ($options['collapse_hspace'] ? '+' : '') . '/' . ($context['utf8'] ? 'u' : '');
		$replacements[] = ' ';
	}

	return preg_replace($patterns, $replacements, $string);
}

/**
 * Shorten a subject + internationalization concerns.
 *
 * - shortens a subject so that it is either shorter than length, or that length plus an ellipsis.
 * - respects internationalization characters and entities as one character.
 * - avoids trailing entities.
 * - returns the shortened string.
 *
 * @param string $subject The subject
 * @param int $len How many characters to limit it to
 * @return string The shortened subject - either the entire subject (if it's <= $len) or the subject shortened to $len characters with "..." appended
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
 * Deprecated function that formerly applied manual offsets to Unix timestamps
 * in order to provide a fake version of time zone support on ancient versions
 * of PHP. It now simply returns an unaltered timestamp.
 *
 * @deprecated since 2.1
 * @param bool $use_user_offset This parameter is deprecated and nonfunctional
 * @param int $timestamp A timestamp (null to use current time)
 * @return int Seconds since the Unix epoch
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	return !isset($timestamp) ? time() : (int) $timestamp;
}

/**
 * Calculates all the possible permutations (orders) of array.
 * should not be called on huge arrays (bigger than like 10 elements.)
 * returns an array containing each permutation.
 *
 * @deprecated since 2.1
 * @param array $array An array
 * @return array An array containing each permutation
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
 * Return an array with allowed bbc tags for signatures, that can be passed to parse_bbc().
 *
 * @return array An array containing allowed tags for signatures, or an empty array if all tags are allowed.
 */
function get_signature_allowed_bbc_tags()
{
	global $modSettings;

	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	if (empty($sig_bbc))
		return array();
	$disabledTags = explode(',', $sig_bbc);

	// Get all available bbc tags
	$temp = parse_bbc(false);
	$allowedTags = array();
	foreach ($temp as $tag)
		if (!in_array($tag['tag'], $disabledTags))
			$allowedTags[] = $tag['tag'];

	$allowedTags = array_unique($allowedTags);
	if (empty($allowedTags))
		// An empty array means that all bbc tags are allowed. So if all tags are disabled we need to add a dummy tag.
		$allowedTags[] = 'nonexisting';

	return $allowedTags;
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
 * - returns the modified message.
 *
 * @param string|bool $message The message.
 *		When a empty string, nothing is done.
 *		When false we provide a list of BBC codes available.
 *		When a string, the message is parsed and bbc handled.
 * @param bool $smileys Whether to parse smileys as well
 * @param string $cache_id The cache ID
 * @param array $parse_tags If set, only parses these tags rather than all of them
 * @return string The parsed message
 */
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
	global $smcFunc, $txt, $scripturl, $context, $modSettings, $user_info, $sourcedir, $cache_enable;
	static $bbc_lang_locales = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $disabled, $alltags_regex = '', $param_regexes = array(), $url_regex = '';

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

	if ($smileys !== null && ($smileys == '1' || $smileys == '0'))
		$smileys = (bool) $smileys;

	if (empty($modSettings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	// If we already have a version of the BBCodes for the current language, use that. Otherwise, make one.
	if (!empty($bbc_lang_locales[$txt['lang_locale']]))
		$bbc_codes = $bbc_lang_locales[$txt['lang_locale']];
	else
		$bbc_codes = array();

	// If we are not doing every tag then we don't cache this run.
	if (!empty($parse_tags))
		$bbc_codes = array();

	// Ensure $modSettings['tld_regex'] contains a valid regex for the autolinker
	if (!empty($modSettings['autoLinkUrls']))
		set_tld_regex();

	// Allow mods access before entering the main parse_bbc loop
	if ($message !== false)
		call_integration_hook('integrate_pre_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Sift out the bbc for a performance improvement.
	if (empty($bbc_codes) || $message === false || !empty($parse_tags))
	{
		if (!empty($modSettings['disabledBBC']))
		{
			$disabled = array();

			$temp = explode(',', strtolower($modSettings['disabledBBC']));

			foreach ($temp as $tag)
				$disabled[trim($tag)] = true;

			if (in_array('color', $disabled))
				$disabled = array_merge($disabled, array(
					'black' => true,
					'white' => true,
					'red' => true,
					'green' => true,
					'blue' => true,
					)
				);
		}

		if (!empty($parse_tags) && $message === false)
		{
			if (!in_array('email', $parse_tags))
				$disabled['email'] = true;
			if (!in_array('url', $parse_tags))
				$disabled['url'] = true;
			if (!in_array('iurl', $parse_tags))
				$disabled['iurl'] = true;
		}

		// The YouTube bbc needs this for its origin parameter
		$scripturl_parts = parse_iri($scripturl);
		$hosturl = $scripturl_parts['scheme'] . '://' . $scripturl_parts['host'];

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
					Either value or validate may be used, not both.
				- optional: true if the parameter is optional.
				- default: a default value for missing optional parameters.

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
			// Legacy (and just an alias for [abbr] even when enabled)
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
				'tag' => 'attach',
				'type' => 'unparsed_content',
				'parameters' => array(
					'id' => array('match' => '(\d+)'),
					'alt' => array('optional' => true),
					'width' => array('optional' => true, 'match' => '(\d+)'),
					'height' => array('optional' => true, 'match' => '(\d+)'),
					'display' => array('optional' => true, 'match' => '(link|embed)'),
				),
				'content' => '$1',
				'validate' => function(&$tag, &$data, $disabled, $params) use ($modSettings, $context, $sourcedir, $txt, $smcFunc)
				{
					$returnContext = '';

					// BBC or the entire attachments feature is disabled
					if (empty($modSettings['attachmentEnable']) || !empty($disabled['attach']))
						return $data;

					// Save the attach ID.
					$attachID = $params['{id}'];

					// Kinda need this.
					require_once($sourcedir . '/Subs-Attachments.php');

					$currentAttachment = parseAttachBBC($attachID);

					// parseAttachBBC will return a string ($txt key) rather than dying with a fatal_error. Up to you to decide what to do.
					if (is_string($currentAttachment))
						return $data = '<span style="display:inline-block" class="errorbox">' . (!empty($txt[$currentAttachment]) ? $txt[$currentAttachment] : $currentAttachment)  . '</span>';

					// We need a display mode.
					if (empty($params['{display}']))
					{
						// Images, video, and audio are embedded by default.
						if (!empty($currentAttachment['is_image']) || strpos($currentAttachment['mime_type'], 'video/') === 0 || strpos($currentAttachment['mime_type'], 'audio/') === 0)
							$params['{display}'] = 'embed';
						// Anything else shows a link by default.
						else
							$params['{display}'] = 'link';
					}

					// Embedded file.
					if ($params['{display}'] == 'embed')
					{
						$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
						$title = !empty($data) ? ' title="' . $smcFunc['htmlspecialchars']($data) . '"' : '';

						// Image.
						if (!empty($currentAttachment['is_image']))
						{
							// Just viewing the page shouldn't increase the download count for embedded images.
							$currentAttachment['href'] .= ';preview';

							if (empty($params['{width}']) && empty($params['{height}']))
								$returnContext .= '<img src="' . $currentAttachment['href'] . '"' . $alt . $title . ' class="bbc_img">';
							else
							{
								$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"': '';
								$height = !empty($params['{height}']) ? 'height="' . $params['{height}'] . '"' : '';
								$returnContext .= '<img src="' . $currentAttachment['href'] . ';image"' . $alt . $title . $width . $height . ' class="bbc_img resized"/>';
							}
						}
						// Video.
						elseif (strpos($currentAttachment['mime_type'], 'video/') === 0)
						{
							$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
							$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

							$returnContext .= '<div class="videocontainer"><video controls preload="metadata" src="'. $currentAttachment['href'] . '" playsinline' . $width . $height . '><a href="' . $currentAttachment['href'] . '" class="bbc_link">' . $smcFunc['htmlspecialchars'](!empty($data) ? $data : $currentAttachment['name']) . '</a></video></div>' . (!empty($data) && $data != $currentAttachment['name'] ? '<div class="smalltext">' . $data . '</div>' : '');
						}
						// Audio.
						elseif (strpos($currentAttachment['mime_type'], 'audio/') === 0)
						{
							$width = 'max-width:100%; width: ' . (!empty($params['{width}']) ? $params['{width}'] : '400') . 'px;';
							$height = !empty($params['{height}']) ? 'height: ' . $params['{height}'] . 'px;' : '';

							$returnContext .= (!empty($data) && $data != $currentAttachment['name'] ? $data . ' ' : '') . '<audio controls preload="none" src="'. $currentAttachment['href'] . '" class="bbc_audio" style="vertical-align:middle;' . $width . $height . '"><a href="' . $currentAttachment['href'] . '" class="bbc_link">' . $smcFunc['htmlspecialchars'](!empty($data) ? $data : $currentAttachment['name']) . '</a></audio>';
						}
						// Anything else.
						else
						{
							$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
							$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

							$returnContext .= '<object type="' . $currentAttachment['mime_type'] . '" data="' . $currentAttachment['href'] . '"' . $width . $height . ' typemustmatch><a href="' . $currentAttachment['href'] . '" class="bbc_link">' . $smcFunc['htmlspecialchars'](!empty($data) ? $data : $currentAttachment['name']) . '</a></object>';
						}
					}

					// No image. Show a link.
					else
						$returnContext .= '<a href="' . $currentAttachment['href'] . '" class="bbc_link">' . $smcFunc['htmlspecialchars'](!empty($data) ? $data : $currentAttachment['name']) . '</a>';

					// Use this hook to adjust the HTML output of the attach BBCode.
					// If you want to work with the attachment data itself, use one of these:
					// - integrate_pre_parseAttachBBC
					// - integrate_post_parseAttachBBC
					call_integration_hook('integrate_attach_bbc_validate', array(&$returnContext, $currentAttachment, $tag, $data, $disabled, $params));

					// Gotta append what we just did.
					$data = $returnContext;
				},
			),
			array(
				'tag' => 'b',
				'before' => '<b>',
				'after' => '</b>',
			),
			// Legacy (equivalent to [ltr] or [rtl])
			array(
				'tag' => 'bdo',
				'type' => 'unparsed_equals',
				'before' => '<bdo dir="$1">',
				'after' => '</bdo>',
				'test' => '(rtl|ltr)\]',
				'block_level' => true,
			),
			// Legacy (alias of [color=black])
			array(
				'tag' => 'black',
				'before' => '<span style="color: black;" class="bbc_color">',
				'after' => '</span>',
			),
			// Legacy (alias of [color=blue])
			array(
				'tag' => 'blue',
				'before' => '<span style="color: blue;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'br',
				'type' => 'closed',
				'content' => '<br>',
			),
			array(
				'tag' => 'center',
				'before' => '<div class="centertext"><div class="inline_block">',
				'after' => '</div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_content',,
				'content' => '<div class="codeheader">' . $txt['code'] . '</div><pre data-select-txt="' . $txt['code_select'] . '" data-shrink-txt="' . $txt['code_shrink'] . '" data-expand-txt="' . $txt['code_expand'] . '" class="bbc_code"><code>$1</code></pre>',
				'validate' => isset($disabled['code']) ? null : function(&$tag, &$data)
				{
					$parts = preg_split('~(&lt;\?php|\?&gt;)~', $data, -1, PREG_SPLIT_DELIM_CAPTURE);

					for ($i = 0, $n = count($parts); $i < $n; $i++)
					{
						// Do PHP code coloring?
						if ($parts[$i] != '&lt;?php')
							continue;

						$string = '';
						while ($i + 1 < $n && $parts[$i] != '?&gt;')
						{
							$string .= $parts[$i];
							$parts[$i++] = '';
						}
						$parts[$i] = highlight_php_code($string . $parts[$i]);
					}

					$data = implode('', $parts);
				},
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'content' => '<div class="codeheader">' . $txt['code'] . ' ($2)</div><pre data-select-txt="' . $txt['code_select'] . '" data-shrink-txt="' . $txt['code_shrink'] . '" data-expand-txt="' . $txt['code_expand'] . '" class="bbc_code"><code>$1</code></pre>',
				'validate' => isset($disabled['code']) ? null : function(&$tag, &$data)
				{
					$parts = preg_split('~(&lt;\?php|\?&gt;)~', $data[0], -1, PREG_SPLIT_DELIM_CAPTURE);

					for ($i = 0, $n = count($parts); $i < $n; $i++)
					{
						// Do PHP code coloring?
						if ($parts[$i] != '&lt;?php')
							continue;

						$string = '';
						while ($i + 1 < $n && $parts[$i] != '?&gt;')
						{
							$string .= $parts[$i];
							$parts[$i++] = '';
						}
						$parts[$i] = highlight_php_code($string . $parts[$i]);
					}

					$data[0] = implode('', $parts);
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
				'validate' => function(&$tag, &$data, $disabled)
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
			// Legacy (and just a link even when not disabled)
			array(
				'tag' => 'flash',
				'type' => 'unparsed_commas_content',
				'test' => '\d+,\d+\]',
				'content' => '<a href="$1" target="_blank" rel="noopener">$1</a>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data[0] = normalize_iri(strtr(trim($data[0]), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data[0], PHP_URL_SCHEME);
					if (empty($scheme))
						$data[0] = '//' . ltrim($data[0], ':/');

					$ascii_url = iri_to_url($data[0]);
					if ($ascii_url !== $data[0])
						$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
				},
			),
			array(
				'tag' => 'float',
				'type' => 'unparsed_equals',
				'test' => '(left|right)(\s+max=\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)?\]',
				'before' => '<div $1>',
				'after' => '</div>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$class = 'class="bbc_float float' . (strpos($data, 'left') === 0 ? 'left' : 'right') . '"';

					if (preg_match('~\bmax=(\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)~', $data, $matches))
						$css = ' style="max-width:' . $matches[1] . (is_numeric($matches[1]) ? 'px' : '') . '"';
					else
						$css = '';

					$data = $class . $css;
				},
				'trim' => 'outside',
				'block_level' => true,
			),
			// Legacy (alias of [url] with an FTP URL)
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$data = normalize_iri(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = 'ftp://' . ltrim($data, ':/');

					$ascii_url = iri_to_url($data);
					if ($ascii_url !== $data)
						$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
				},
			),
			// Legacy (alias of [url] with an FTP URL)
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
				'after' => '</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$data = iri_to_url(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = 'ftp://' . ltrim($data, ':/');
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'font',
				'type' => 'unparsed_equals',
				'test' => '[A-Za-z0-9_,\-\s]+?\]',
				'before' => '<span style="font-family: $1;" class="bbc_font">',
				'after' => '</span>',
			),
			// Legacy (one of those things that should not be done)
			array(
				'tag' => 'glow',
				'type' => 'unparsed_commas',
				'test' => '[#0-9a-zA-Z\-]{3,12},([012]\d{1,2}|\d{1,2})(,[^]]+)?\]',
				'before' => '<span style="text-shadow: $1 1px 1px 1px">',
				'after' => '</span>',
			),
			// Legacy (alias of [color=green])
			array(
				'tag' => 'green',
				'before' => '<span style="color: green;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'html',
				'type' => 'unparsed_content',
				'content' => '<div>$1</div>',
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
				'before' => '<i>',
				'after' => '</i>',
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
				'content' => '$1',
				'validate' => function(&$tag, &$data, $disabled, $params)
				{
					$url = iri_to_url(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					if (parse_iri($url, PHP_URL_SCHEME) === null)
						$url = '//' . ltrim($url, ':/');
					else
						$url = get_proxied_url($url);

					$alt = !empty($params['{alt}']) ? ' alt="' . $params['{alt}']. '"' : ' alt=""';
					$title = !empty($params['{title}']) ? ' title="' . $params['{title}']. '"' : '';

					$data = isset($disabled[$tag['tag']]) ? $url : '<img src="' . $url . '"' . $alt . $title . $params['{width}'] . $params['{height}'] . ' class="bbc_img' . (!empty($params['{width}']) || !empty($params['{height}']) ? ' resized' : '') . '" loading="lazy">';
				},
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link">$1</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$data = normalize_iri(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');

					$ascii_url = iri_to_url($data);
					if ($ascii_url !== $data)
						$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
				},
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_equals',
				'quoted' => 'optional',
				'before' => '<a href="$1" class="bbc_link">',
				'after' => '</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					if (substr($data, 0, 1) == '#')
						$data = '#post_' . substr($data, 1);
					else
					{
						$data = iri_to_url(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

						$scheme = parse_iri($data, PHP_URL_SCHEME);
						if (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					}
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'justify',
				'before' => '<div class="justifytext">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'left',
				'before' => '<div class="lefttext">',
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
					'type' => array('match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|upper-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'),
				),
				'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => array('li'),
				'block_level' => true,
			),
			array(
				'tag' => 'ltr',
				'before' => '<bdo dir="ltr">',
				'after' => '</bdo>',
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
				'before' => '<a href="' . $scripturl . '?action=profile;u=$1" class="mention" data-mention="$1">@',
				'after' => '</a>',
			),
			// Legacy (horrible memories of the 1990s)
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
				'validate' => isset($disabled['php']) ? null : function(&$tag, &$data, $disabled)
				{
					if (!isset($disabled['php']))
					{
						$add_begin = substr(trim($data), 0, 5) != '&lt;?';
						$data = highlight_php_code($add_begin ? '&lt;?php ' . $data . '?&gt;' : $data);
						if ($add_begin)
							$data = preg_replace(array('/&lt;\?php(?:&nbsp;|\s)/', '/<span class="highlight-default"><\/span>/'), '', $data, 2);
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
				'before' => '<blockquote><cite>' . $txt['quote'] . '</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)', 'quoted' => true),
				),
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'type' => 'parsed_equals',
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': $1</cite>',
				'after' => '</blockquote>',
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
				'before' => '<blockquote><cite><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . $txt['search_on'] . ' {date}</a></cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)'),
				),
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			),
			// Legacy (alias of [color=red])
			array(
				'tag' => 'red',
				'before' => '<span style="color: red;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'right',
				'before' => '<div class="righttext"><div class="inline_block">',
				'after' => '</div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'rtl',
				'before' => '<bdo dir="rtl">',
				'after' => '</bdo>',
				'block_level' => true,
			),
			array(
				'tag' => 's',
				'before' => '<s>',
				'after' => '</s>',
			),
			// Legacy (never a good idea)
			array(
				'tag' => 'shadow',
				'type' => 'unparsed_commas',
				'test' => '[#0-9a-zA-Z\-]{3,12},(left|right|top|bottom|[0123]\d{0,2})\]',
				'before' => '<span style="text-shadow: $1 $2">',
				'after' => '</span>',
				'validate' => function(&$tag, &$data, $disabled)
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
				'validate' => function(&$tag, &$data, $disabled)
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
				'validate' => function(&$tag, &$data, $disabled)
				{
					if (is_numeric($data))
						$data = timeformat($data);

					$tag['content'] = '<span class="bbc_time">$1</span>';
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
			// Legacy (the <tt> element is dead)
			array(
				'tag' => 'tt',
				'before' => '<span class="monospace">',
				'after' => '</span>',
			),
			array(
				'tag' => 'u',
				'before' => '<u>',
				'after' => '</u>',
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$data = normalize_iri(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');

					$ascii_url = iri_to_url($data);
					if ($ascii_url !== $data)
						$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
				},
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_equals',
				'quoted' => 'optional',
				'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
				'after' => '</a>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					$data = iri_to_url(strtr(trim($data), array('<br>' => '', ' ' => '%20')));

					$scheme = parse_iri($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			// Legacy (alias of [color=white])
			array(
				'tag' => 'white',
				'before' => '<span style="color: white;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'youtube',
				'type' => 'unparsed_content',
				'content' => '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/$1?origin=' . $hosturl . '&wmode=opaque" data-youtube-id="$1" allowfullscreen loading="lazy"></iframe></div></div>',
				'disabled_content' => '<a href="https://www.youtube.com/watch?v=$1" target="_blank" rel="noopener">https://www.youtube.com/watch?v=$1</a>',
				'block_level' => true,
			),
		);

		// Inside these tags autolink is not recommendable.
		$no_autolink_tags = array(
			'url',
			'iurl',
			'email',
			'img',
			'html',
			'attach',
			'ftp',
			'flash',
			'member',
			'code',
			'php',
			'nobbc',
		);

		// Let mods add new BBC without hassle.
		call_integration_hook('integrate_bbc_codes', array(&$codes, &$no_autolink_tags));

		// This is mainly for the bbc manager, so it's easy to add tags above.  Custom BBC should be added above this line.
		if ($message === false)
		{
			usort(
				$codes,
				function($a, $b)
				{
					return strcmp($a['tag'], $b['tag']);
				}
			);
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
		$codes[] = array(
			'tag' => 'cowsay',
			'parameters' => array(
				'e' => array('optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => 'oo', 'validate' => function ($eyes) use ($smcFunc)
					{
						return $smcFunc['substr']($eyes . 'oo', 0, 2);
					},
				),
				't' => array('optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => '  ', 'validate' => function ($tongue) use ($smcFunc)
					{
						return $smcFunc['substr']($tongue . '  ', 0, 2);
					},
				),
			),
			'before' => '<pre data-e="{e}" data-t="{t}"><div>',
			'after' => '</div></pre>',
			'block_level' => true,
			'validate' => function(&$tag, &$data, $disabled, $params)
			{
				static $moo = true;

				if ($moo)
				{
					addInlineJavaScript("\n\t" . base64_decode(
						'aWYoZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoImJvdmluZV9vcmFjbGU
						iKT09PW51bGwpe2xldCBzdHlsZU5vZGU9ZG9jdW1lbnQuY3JlYXRlRWx
						lbWVudCgic3R5bGUiKTtzdHlsZU5vZGUuaWQ9ImJvdmluZV9vcmFjbGU
						iO3N0eWxlTm9kZS5pbm5lckhUTUw9J3ByZVtkYXRhLWVdW2RhdGEtdF1
						7d2hpdGUtc3BhY2U6cHJlLXdyYXA7bGluZS1oZWlnaHQ6aW5pdGlhbDt
						9cHJlW2RhdGEtZV1bZGF0YS10XSA+IGRpdntkaXNwbGF5OnRhYmxlO2J
						vcmRlcjoxcHggc29saWQ7Ym9yZGVyLXJhZGl1czowLjVlbTtwYWRkaW5
						nOjFjaDttYXgtd2lkdGg6ODBjaDttaW4td2lkdGg6MTJjaDt9cHJlW2R
						hdGEtZV1bZGF0YS10XTo6YWZ0ZXJ7ZGlzcGxheTppbmxpbmUtYmxvY2s
						7bWFyZ2luLWxlZnQ6OGNoO21pbi13aWR0aDoyMGNoO2RpcmVjdGlvbjp
						sdHI7Y29udGVudDpcJ1xcNUMgXCdcJyBcJ1wnIF5fX15cXEEgXCdcJyB
						cXDVDIFwnXCcgKFwnIGF0dHIoZGF0YS1lKSBcJylcXDVDX19fX19fX1x
						cQSBcJ1wnIFwnXCcgXCdcJyAoX18pXFw1QyBcJ1wnIFwnXCcgXCdcJyB
						cJ1wnIFwnXCcgXCdcJyBcJ1wnIClcXDVDL1xcNUNcXEEgXCdcJyBcJ1w
						nIFwnXCcgXCdcJyBcJyBhdHRyKGRhdGEtdCkgXCcgfHwtLS0tdyB8XFx
						BIFwnXCcgXCdcJyBcJ1wnIFwnXCcgXCdcJyBcJ1wnIFwnXCcgfHwgXCd
						cJyBcJ1wnIFwnXCcgXCdcJyB8fFwnO30nO2RvY3VtZW50LmdldEVsZW1
						lbnRzQnlUYWdOYW1lKCJoZWFkIilbMF0uYXBwZW5kQ2hpbGQoc3R5bGV
						Ob2RlKTt9'
					), true);

					$moo = false;
				}
			}
		);

		foreach ($codes as $code)
		{
			// Make it easier to process parameters later
			if (!empty($code['parameters']))
				ksort($code['parameters'], SORT_STRING);

			// If we are not doing every tag only do ones we are interested in.
			if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
				$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
		}
		$codes = null;
	}

	// Shall we take the time to cache this?
	if ($cache_id != '' && !empty($cache_enable) && (($cache_enable >= 2 && isset($message[1000])) || isset($message[2400])) && empty($parse_tags))
	{
		// It's likely this will change if the message is modified.
		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . $smcFunc['json_encode']($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

		if (($temp = cache_get_data($cache_key, 240)) != null)
			return $temp;

		$cache_t = microtime(true);
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
		{
			$disabled['img'] = true;
			$disabled['attach'] = true;
		}

		// Maybe some custom BBC need to be disabled for printing.
		call_integration_hook('integrate_bbc_print', array(&$disabled));
	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br>'));

	if (!empty($parse_tags))
	{
		$real_alltags_regex = $alltags_regex;
		$alltags_regex = '';
	}
	if (empty($alltags_regex))
	{
		$alltags = array();
		foreach ($bbc_codes as $section)
		{
			foreach ($section as $code)
				$alltags[] = $code['tag'];
		}
		$alltags_regex = '(?' . '>\b' . build_regex(array_unique($alltags)) . '\b|' . build_regex(array_keys($itemcodes)) . ')';
	}

	$pos = -1;
	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		preg_match('~\[/?(?=' . $alltags_regex . ')~i', $message, $matches, PREG_OFFSET_CAPTURE, $pos + 1);
		$pos = isset($matches[0][1]) ? $matches[0][1] : false;

		// Failsafe.
		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;

		// Can't have a one letter smiley, URL, or email! (Sorry.)
		if ($last_pos < $pos - 1)
		{
			// Make sure the $last_pos is not negative.
			$last_pos = max($last_pos, 0);

			// Pick a block of data to do some raw fixing on.
			$data = substr($message, $last_pos, $pos - $last_pos);

			$placeholders = array();
			$placeholders_counter = 0;
			// Wrap in "private use" Unicode characters to ensure there will be no conflicts.
			$placeholder_template = html_entity_decode('&#xE03C;') . '%1$s' . html_entity_decode('&#xE03E;');

			// Take care of some HTML!
			if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:|tel:)\S+?)\\1&gt;(.*?)&lt;/a&gt;~i', '[url=&quot;$2&quot;]$3[/url]', $data);

				// <br> should be empty.
				$empty_tags = array('br', 'hr');
				foreach ($empty_tags as $tag)
					$data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '<' . $tag . '>', $data);

				// b, u, i, s, pre... basic tags.
				$closable_tags = array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote', 'strong');
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
						// @todo Testing this preg_match seems pointless
						if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) != 0)
							$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

						$placeholder = sprintf($placeholder_template, ++$placeholders_counter);
						$placeholders[$placeholder] = '[img' . $alt . ']' . $imgtag . '[/img]';

						$replaces[$matches[0][$match]] = $placeholder;
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
					// An &nbsp; right after a URL can break the autolinker
					if (strpos($data, '&nbsp;') !== false)
					{
						$placeholders[html_entity_decode('&nbsp;', 0, $context['character_set'])] = '&nbsp;';
						$data = strtr($data, array('&nbsp;' => html_entity_decode('&nbsp;', 0, $context['character_set'])));
					}

					// Some reusable character classes
					$excluded_trailing_chars = '!;:.,?';
					$domain_label_chars = '0-9A-Za-z\-' . ($context['utf8'] ? implode('', array(
						'\x{A0}-\x{D7FF}', '\x{F900}-\x{FDCF}', '\x{FDF0}-\x{FFEF}',
						'\x{10000}-\x{1FFFD}', '\x{20000}-\x{2FFFD}', '\x{30000}-\x{3FFFD}',
						'\x{40000}-\x{4FFFD}', '\x{50000}-\x{5FFFD}', '\x{60000}-\x{6FFFD}',
						'\x{70000}-\x{7FFFD}', '\x{80000}-\x{8FFFD}', '\x{90000}-\x{9FFFD}',
						'\x{A0000}-\x{AFFFD}', '\x{B0000}-\x{BFFFD}', '\x{C0000}-\x{CFFFD}',
						'\x{D0000}-\x{DFFFD}', '\x{E1000}-\x{EFFFD}',
					)) : '');

					// Parse any URLs
					if (!isset($disabled['url']) && strpos($data, '[url') === false)
					{
						// URI schemes that require some sort of special handling.
						$schemes = array(
							// Schemes whose URI definitions require a domain name in the
							// authority (or whatever the next part of the URI is).
							'need_domain' => array(
								'aaa', 'aaas', 'acap', 'acct', 'afp', 'cap', 'cid', 'coap',
								'coap+tcp', 'coap+ws', 'coaps', 'coaps+tcp', 'coaps+ws', 'crid',
								'cvs', 'dict', 'dns', 'feed', 'fish', 'ftp', 'git', 'go',
								'gopher', 'h323', 'http', 'https', 'iax', 'icap', 'im', 'imap',
								'ipp', 'ipps', 'irc', 'irc6', 'ircs', 'ldap', 'ldaps', 'mailto',
								'mid', 'mupdate', 'nfs', 'nntp', 'pop', 'pres', 'reload',
								'rsync', 'rtsp', 'sftp', 'sieve', 'sip', 'sips', 'smb', 'snmp',
								'soap.beep', 'soap.beeps', 'ssh', 'svn', 'stun', 'stuns',
								'telnet', 'tftp', 'tip', 'tn3270', 'turn', 'turns', 'tv', 'udp',
								'vemmi', 'vnc', 'webcal', 'ws', 'wss', 'xmlrpc.beep',
								'xmlrpc.beeps', 'xmpp', 'z39.50', 'z39.50r', 'z39.50s',
							),
							// Schemes that allow an empty authority ("://" followed by "/")
							'empty_authority' => array(
								'file', 'ni', 'nih',
							),
							// Schemes that do not use an authority but still have a reasonable
							// chance of working as clickable links.
							'no_authority' => array(
								'about', 'callto', 'geo', 'gg', 'leaptofrogans', 'magnet',
								'mailto', 'maps', 'news', 'ni', 'nih', 'service', 'skype',
								'sms', 'tel', 'tv',
							),
							// Schemes that we should never link.
							'forbidden' => array(
								'javascript', 'data',
							),
						);

						// In case a mod wants to control behaviour for a special URI scheme.
						call_integration_hook('integrate_autolinker_schemes', array(&$schemes));

						// Don't repeat this unnecessarily.
						if (empty($url_regex))
						{
							// PCRE subroutines for efficiency.
							$pcre_subroutines = array(
								'tlds' => $modSettings['tld_regex'],
								'pct' => '%[0-9A-Fa-f]{2}',
								'domain_label_char' => '[' . $domain_label_chars . ']',
								'not_domain_label_char' => '[^' . $domain_label_chars . ']',
								'domain' => '(?:(?P>domain_label_char)+\.)+(?P>tlds)(?!\.(?P>domain_label_char))',
								'no_domain' => '(?:(?P>domain_label_char)|[._\~!$&\'()*+,;=:@]|(?P>pct))+',
								'scheme_need_domain' => build_regex($schemes['need_domain'], '~'),
								'scheme_empty_authority' => build_regex($schemes['empty_authority'], '~'),
								'scheme_no_authority' => build_regex($schemes['no_authority'], '~'),
								'scheme_any' => '[A-Za-z][0-9A-Za-z+\-.]*',
								'user_info' => '(?:(?P>domain_label_char)|[._\~!$&\'()*+,;=:]|(?P>pct))+',
								'dec_octet' => '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)',
								'h16' => '[0-9A-Fa-f]{1,4}',
								'ipv4' => '(?:\b(?:(?P>dec_octet)\.){3}(?P>dec_octet)\b)',
								'ipv6' => '\[(?:' . implode('|', array(
									'(?:(?P>h16):){7}(?P>h16)',
									'(?:(?P>h16):){1,7}:',
									'(?:(?P>h16):){1,6}(?::(?P>h16))',
									'(?:(?P>h16):){1,5}(?::(?P>h16)){1,2}',
									'(?:(?P>h16):){1,4}(?::(?P>h16)){1,3}',
									'(?:(?P>h16):){1,3}(?::(?P>h16)){1,4}',
									'(?:(?P>h16):){1,2}(?::(?P>h16)){1,5}',
									'(?P>h16):(?::(?P>h16)){1,6}',
									':(?:(?::(?P>h16)){1,7}|:)',
									'fe80:(?::(?P>h16)){0,4}%[0-9A-Za-z]+',
									'::(ffff(:0{1,4})?:)?(?P>ipv4)',
									'(?:(?P>h16):){1,4}:(?P>ipv4)',
								)) . ')\]',
								'host' => '(?:' . implode('|', array(
									'localhost',
									'(?P>domain)',
									'(?P>ipv4)',
									'(?P>ipv6)',
								)) . ')',
								'authority' => '(?:(?P>user_info)@)?(?P>host)(?::\d+)?',
							);

							// Brackets and quotation marks are problematic at the end of an IRI.
							// E.g.: `http://foo.com/baz(qux)` vs. `(http://foo.com/baz_qux)`
							// In the first case, the user probably intended the `)` as part of the
							// IRI, but not in the second case. To account for this, we test for
							// balanced pairs within the IRI.
							$balanced_pairs = array(
								// Brackets and parentheses
								'(' => ')', '[' => ']', '{' => '}',
								// Double quotation marks
								'"' => '"',
								html_entity_decode('&#x201C;', 0, $context['character_set']) => html_entity_decode('&#x201D;', 0, $context['character_set']),
								html_entity_decode('&#x201E;', 0, $context['character_set']) => html_entity_decode('&#x201D;', 0, $context['character_set']),
								html_entity_decode('&#x201F;', 0, $context['character_set']) => html_entity_decode('&#x201D;', 0, $context['character_set']),
								html_entity_decode('&#x00AB;', 0, $context['character_set']) => html_entity_decode('&#x00BB;', 0, $context['character_set']),
								// Single quotation marks
								'\'' => '\'',
								html_entity_decode('&#x2018;', 0, $context['character_set']) => html_entity_decode('&#x2019;', 0, $context['character_set']),
								html_entity_decode('&#x201A;', 0, $context['character_set']) => html_entity_decode('&#x2019;', 0, $context['character_set']),
								html_entity_decode('&#x201B;', 0, $context['character_set']) => html_entity_decode('&#x2019;', 0, $context['character_set']),
								html_entity_decode('&#x2039;', 0, $context['character_set']) => html_entity_decode('&#x203A;', 0, $context['character_set']),
							);
							foreach ($balanced_pairs as $pair_opener => $pair_closer)
								$balanced_pairs[$smcFunc['htmlspecialchars']($pair_opener)] = $smcFunc['htmlspecialchars']($pair_closer);

							$bracket_quote_chars = '';
							$bracket_quote_entities = array();
							foreach ($balanced_pairs as $pair_opener => $pair_closer)
							{
								if ($pair_opener == $pair_closer)
									$pair_closer = '';

								foreach (array($pair_opener, $pair_closer) as $bracket_quote)
								{
									if (strpos($bracket_quote, '&') === false)
										$bracket_quote_chars .= $bracket_quote;
									else
										$bracket_quote_entities[] = substr($bracket_quote, 1);
								}
							}
							$bracket_quote_chars = str_replace(array('[', ']'), array('\[', '\]'), $bracket_quote_chars);

							$pcre_subroutines['bracket_quote'] = '[' . $bracket_quote_chars . ']|&' . build_regex($bracket_quote_entities, '~');
							$pcre_subroutines['allowed_entities'] = '&(?!' . build_regex(array_merge($bracket_quote_entities, array('lt;', 'gt;')), '~') . ')';
							$pcre_subroutines['excluded_lookahead'] = '(?![' . $excluded_trailing_chars . ']*(?' . '>[\h\v]|<br>|$))';

							foreach (array('path', 'query', 'fragment') as $part)
							{
								switch ($part) {
									case 'path':
										$part_disallowed_chars = '\h\v<>' . $bracket_quote_chars . $excluded_trailing_chars . '/#&';
										$part_excluded_trailing_chars = str_replace('?', '', $excluded_trailing_chars);
										break;

									case 'query':
										$part_disallowed_chars = '\h\v<>' . $bracket_quote_chars . $excluded_trailing_chars . '#&';
										$part_excluded_trailing_chars = $excluded_trailing_chars;
										break;

									default:
										$part_disallowed_chars = '\h\v<>' . $bracket_quote_chars . $excluded_trailing_chars . '&';
										$part_excluded_trailing_chars = $excluded_trailing_chars;
										break;
								}
								$pcre_subroutines[$part . '_allowed'] = '[^' . $part_disallowed_chars . ']|(?P>allowed_entities)|[' . $part_excluded_trailing_chars . '](?P>excluded_lookahead)';

								$balanced_construct_regex = array();

								foreach ($balanced_pairs as $pair_opener => $pair_closer)
									$balanced_construct_regex[] = preg_quote($pair_opener) . '(?P>' . $part . '_recursive)*+' . preg_quote($pair_closer);

								$pcre_subroutines[$part . '_balanced'] = '(?:' . implode('|', $balanced_construct_regex) . ')(?P>' . $part . '_allowed)*+';
								$pcre_subroutines[$part . '_recursive'] = '(?' . '>(?P>' . $part . '_allowed)|(?P>' . $part . '_balanced))';

								$pcre_subroutines[$part . '_segment'] =
									// Allowed characters besides brackets and quotation marks
									'(?P>' . $part . '_allowed)*+' .
									// Brackets and quotation marks that are either...
									'(?:' .
										// part of a balanced construct
										'(?P>' . $part . '_balanced)' .
										// or
										'|' .
										// unpaired but not at the end
										'(?P>bracket_quote)(?=(?P>' . $part . '_allowed))' .
									')*+';
							}

							// Time to build this monster!
							$url_regex =
							// 1. IRI scheme and domain components
							'(?:' .
								// 1a. IRIs with a scheme, or at least an opening "//"
								'(?:' .

									// URI scheme (or lack thereof for schemeless URLs)
									'(?' . '>' .
										// URI scheme and colon
										'\b' .
										'(?:' .
											// Either a scheme that need a domain in the authority
											// (Remember for later that we need a domain)
											'(?P<need_domain>(?P>scheme_need_domain)):' .
											// or
											'|' .
											// a scheme that allows an empty authority
											// (Remember for later that the authority can be empty)
											'(?P<empty_authority>(?P>scheme_empty_authority)):' .
											// or
											'|' .
											// a scheme that uses no authority
											'(?P>scheme_no_authority):(?!//)' .
											// or
											'|' .
											// another scheme, but only if it is followed by "://"
											'(?P>scheme_any):(?=//)' .
										')' .

										// or
										'|' .

										// An empty string followed by "//" for schemeless URLs
										'(?P<schemeless>(?=//))' .
									')' .

									// IRI authority chunk (maybe)
									'(?:' .
										// (Keep track of whether we find a valid authority or not)
										'(?P<has_authority>' .
											// 2 slashes before the authority itself
											'//' .
											'(?:' .
												// If there was no scheme...
												'(?(<schemeless>)' .
													// require an authority that contains a domain.
													'(?P>authority)' .

													// Else if a domain is needed...
													'|(?(<need_domain>)' .
														// require an authority with a domain.
														'(?P>authority)' .

														// Else if an empty authority is allowed...
														'|(?(<empty_authority>)' .
															// then require either
															'(?:' .
																// empty string, followed by a "/"
																'(?=/)' .
																// or
																'|' .
																// an authority with a domain.
																'(?P>authority)' .
															')' .

															// Else just a run of IRI characters.
															'|(?P>no_domain)' .
														')' .
													')' .
												')' .
											')' .
											// Followed by a non-domain character or end of line
											'(?=(?P>not_domain_label_char)|$)' .
										')' .

										// or, if there is a scheme but no authority
										// (e.g. "mailto:" URLs)...
										'|' .

										// A run of IRI characters
										'(?P>no_domain)' .
										// If scheme needs a domain, require a dot and a TLD
										'(?(<need_domain>)\.(?P>tlds))' .
										// Followed by a non-domain character or end of line
										'(?=(?P>not_domain_label_char)|$)' .
									')' .
								')' .

								// Or, if there is neither a scheme nor an authority...
								'|' .

								// 1b. Naked domains
								// (e.g. "example.com" in "Go to example.com for an example.")
								'(?P<naked_domain>' .
									// Preceded by start of line or a space
									'(?<=^|<br>|[\h\v])' .
									// A domain name
									'(?P>domain)' .
									// Followed by a non-domain character or end of line
									'(?=(?P>not_domain_label_char)|$)' .
								')' .
							')' .

							// 2. IRI path, query, and fragment components (if present)
							'(?:' .
								// If the IRI has an authority or is a naked domain and any of these
								// components exist, the path must start with a single "/".
								// Note: technically, it is valid to append a query or fragment
								// directly to the authority chunk without a "/", but supporting
								// that in the autolinker would produce a lot of false positives,
								// so we don't.
								'(?=' .
									// If we found an authority above...
									'(?(<has_authority>)' .
										// require a "/"
										'/' .
										// Else if we found a naked domain above...
										'|(?(<naked_domain>)' .
											// require a "/"
											'/' .
										')' .
									')' .
								')' .

								// 2.a. Path component, if any.
								'(?:' .
									// Can have one or more segments
									'(?:' .
										// Not preceded by a "/", except in the special case of an
										// empty authority immediately before the path.
										'(?(<empty_authority>)' .
											'(?:(?<=://)|(?<!/))' .
											'|' .
											'(?<!/)' .
										')' .
										// Initial "/"
										'/' .
										// Then a run of allowed path segment characters
										'(?P>path_segment)*+' .
									')*+' .
								')' .

								// 2.b. Query component, if any.
								'(?:' .
									// Initial "?" that is not last character.
									'\?' . '(?=(?P>bracket_quote)*(?P>query_allowed))' .
									// Then a run of allowed query characters
									'(?P>query_segment)*+' .
								')?' .

								// 2.c. Fragment component, if any.
								'(?:' .
									// Initial "#" that is not last character.
									'#' . '(?=(?P>bracket_quote)*(?P>fragment_allowed))' .
									// Then a run of allowed fragment characters
									'(?P>fragment_segment)*+' .
								')?' .
							')?+';

							// Finally, define the PCRE subroutines in the regex.
							$url_regex .= '(?(DEFINE)';

							foreach ($pcre_subroutines as $name => $subroutine)
								$url_regex .= '(?<' . $name . '>' . $subroutine . ')';

							$url_regex .= ')';
						}

						$tmp_data = preg_replace_callback(
							'~' . $url_regex . '~i' . ($context['utf8'] ? 'u' : ''),
							function($matches) use ($schemes)
							{
								$url = array_shift($matches);

								// If this isn't a clean URL, bail out
								if ($url != sanitize_iri($url))
									return $url;

								// Ensure the host name is in its canonical form.
								$url = normalize_iri($url);

								$parsedurl = parse_iri($url);

								if (!isset($parsedurl['scheme']))
									$parsedurl['scheme'] = '';

								if ($parsedurl['scheme'] == 'mailto')
								{
									if (isset($disabled['email']))
										return $url;

									// Is this version of PHP capable of validating this email address?
									$can_validate = defined('FILTER_FLAG_EMAIL_UNICODE') || strlen($parsedurl['path']) == strspn(strtolower($parsedurl['path']), 'abcdefghijklmnopqrstuvwxyz0123456789!#$%&\'*+-/=?^_`{|}~.@');

									$flags = defined('FILTER_FLAG_EMAIL_UNICODE') ? FILTER_FLAG_EMAIL_UNICODE : null;

									if (!$can_validate || filter_var($parsedurl['path'], FILTER_VALIDATE_EMAIL, $flags) !== false)
										return '[email=' . str_replace('mailto:', '', $url) . ']' . $url . '[/email]';
									else
										return $url;
								}

								// Are we linking a schemeless URL or naked domain name (e.g. "example.com")?
								if (empty($parsedurl['scheme']))
									$fullUrl = '//' . ltrim($url, ':/');
								else
									$fullUrl = $url;

								// Make sure that $fullUrl really is valid
								if (in_array($parsedurl['scheme'], $schemes['forbidden']) || (!in_array($parsedurl['scheme'], $schemes['no_authority']) && validate_iri((strpos($fullUrl, '//') === 0 ? 'http:' : '') . $fullUrl) === false))
									return $url;

								return '[url=&quot;' . str_replace(array('[', ']'), array('&#91;', '&#93;'), iri_to_url($fullUrl)) . '&quot;]' . $url . '[/url]';
							},
							$data
						);

						if (!is_null($tmp_data))
							$data = $tmp_data;
					}

					// Next, emails...  Must be careful not to step on enablePostHTML logic above...
					if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false && stripos($data, 'mailto:') === false)
					{
						// Preceded by a space or start of line
						$email_regex = '(?<=^|<br>|[\h\v])' .

						// An email address
						'[' . $domain_label_chars . '_.]{1,80}' .
						'@' .
						'[' . $domain_label_chars . '.]+' .
						'\.' . $modSettings['tld_regex'] .

						// Followed by a non-domain character or end of line
						'(?=[^' . $domain_label_chars . ']|$)';

						$tmp_data = preg_replace('~' . $email_regex . '~i' . ($context['utf8'] ? 'u' : ''), '[email]$0[/email]', $data);

						if (!is_null($tmp_data))
							$data = $tmp_data;
					}

					// Save a little memory.
					unset($tmp_data);
				}
			}

			// Restore any placeholders
			$data = strtr($data, $placeholders);

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

		$tag_character = strtolower($message[$pos + 1]);

		if ($tag_character == '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 == $pos + 2)
				continue;

			$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

			// A closing tag that doesn't match any open tags? Skip it.
			if (!in_array($look_for, array_map(function($code) { return $code['tag']; }, $open_tags)))
				continue;

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
				$whitespace_regex = '';
				if (!empty($tag['block_level']))
					$whitespace_regex .= '(&nbsp;|\s)*(<br\s*/?' . '>)?';
				// Trim one line of whitespace after unnested tags, but all of it after nested ones
				if (!empty($tag['trim']) && $tag['trim'] != 'inside')
					$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';

				if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
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
		if (!isset($bbc_codes[$tag_character]))
			continue;

		$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
		$tag = null;
		foreach ($bbc_codes[$tag_character] as $possible)
		{
			$pt_strlen = strlen($possible['tag']);

			// Not a match?
			if (strtolower(substr($message, $pos + 1, $pt_strlen)) != $possible['tag'])
				continue;

			$next_c = isset($message[$pos + 1 + $pt_strlen]) ? $message[$pos + 1 + $pt_strlen] : '';

			// A tag is the last char maybe
			if ($next_c == '')
				break;

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + $pt_strlen + 1)) === 0)
				continue;
			// Do we want parameters?
			elseif (!empty($possible['parameters']))
			{
				// Are all the parameters optional?
				$param_required = false;
				foreach ($possible['parameters'] as $param)
				{
					if (empty($param['optional']))
					{
						$param_required = true;
						break;
					}
				}

				if ($param_required && $next_c != ' ')
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
				// Build a regular expression for each parameter for the current tag.
				$regex_key = $smcFunc['json_encode']($possible['parameters']);
				if (!isset($params_regexes[$regex_key]))
				{
					$params_regexes[$regex_key] = '';

					foreach ($possible['parameters'] as $p => $info)
						$params_regexes[$regex_key] .= '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . '\s*)' . (empty($info['optional']) ? '' : '?');
				}

				// Extract the string that potentially holds our parameters.
				$blob = preg_split('~\[/?(?:' . $alltags_regex . ')~i', substr($message, $pos));
				$blobs = preg_split('~\]~i', $blob[1]);

				$splitters = implode('=|', array_keys($possible['parameters'])) . '=';

				// Progressively append more blobs until we find our parameters or run out of blobs
				$blob_counter = 1;
				while ($blob_counter <= count($blobs))
				{
					$given_param_string = implode(']', array_slice($blobs, 0, $blob_counter++));

					$given_params = preg_split('~\s(?=(' . $splitters . '))~i', $given_param_string);
					sort($given_params, SORT_STRING);

					$match = preg_match('~^' . $params_regexes[$regex_key] . '$~i', implode(' ', $given_params), $matches) !== 0;

					if ($match)
						break;
				}

				// Didn't match our parameter list, try the next possible.
				if (!$match)
					continue;

				$params = array();
				for ($i = 1, $n = count($matches); $i < $n; $i += 2)
				{
					$key = strtok(ltrim($matches[$i]), '=');
					if ($key === false)
						continue;
					elseif (isset($possible['parameters'][$key]['value']))
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
					{
						if (!isset($info['default']))
							$params['{' . $p . '}'] = '';
						elseif (isset($possible['parameters'][$p]['value']))
							$params['{' . $p . '}'] = strtr($possible['parameters'][$p]['value'], array('$1' => $info['default']));
						elseif (isset($possible['parameters'][$p]['validate']))
							$params['{' . $p . '}'] = $possible['parameters'][$p]['validate']($info['default']);
						else
							$params['{' . $p . '}'] = $info['default'];
					}
				}

				$tag = $possible;

				// Put the parameters into the string.
				if (isset($tag['before']))
					$tag['before'] = strtr($tag['before'], $params);
				if (isset($tag['after']))
					$tag['after'] = strtr($tag['after'], $params);
				if (isset($tag['content']))
					$tag['content'] = strtr($tag['content'], $params);

				$pos1 += strlen($given_param_string);
			}
			else
			{
				$tag = $possible;
				$params = array();
			}
			break;
		}

		// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
		if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]], $message[$pos + 2]) && $message[$pos + 2] == ']' && !isset($disabled['list']) && !isset($disabled['li']))
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
				preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 4), $matches);
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
				$whitespace_regex = '';
				if (!empty($tag['block_level']))
					$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
				if (!empty($tag['trim']) && $tag['trim'] != 'inside')
					$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
				if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}

		// Can't read past the end of the message
		$pos1 = min(strlen($message), $pos1);

		// No type means 'parsed_content'.
		if (!isset($tag['type']))
		{
			$open_tags[] = $tag;

			// There's no data to change, but maybe do something based on params?
			$data = null;
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled, $params);

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

			if (!empty($tag['block_level']) && substr($data, 0, 4) == '<br>')
				$data = substr($data, 4);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled, $params);

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

			if (!empty($tag['block_level']) && substr($data[0], 0, 4) == '<br>')
				$data[0] = substr($data[0], 4);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled, $params);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
			$pos += strlen($code) - 1 + 2;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] == 'closed')
		{
			$pos2 = strpos($message, ']', $pos);

			// Maybe a custom BBC wants to do something special?
			$data = null;
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled, $params);

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
				$tag['validate']($tag, $data, $disabled, $params);

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
				$tag['validate']($tag, $data, $disabled, $params);

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

			if ($quoted)
			{
				$end_of_value = strpos($message, '&quot;]', $pos1);
				$nested_tag = strpos($message, '=&quot;', $pos1);
				// Check so this is not just an quoted url ending with a =
				if ($nested_tag && substr($message, $nested_tag, 8) == '=&quot;]')
					$nested_tag = false;
				if ($nested_tag && $nested_tag < $end_of_value)
					// Nested tag with quoted value detected, use next end tag
					$nested_tag_pos = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1) + 6;
			}

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', isset($nested_tag_pos) ? $nested_tag_pos : $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled, $params);

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
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) == '<br>')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);

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

	if ($message !== '' && $message[0] === ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

	// Allow mods access to what parse_bbc created
	call_integration_hook('integrate_post_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Cache the output if it took some time...
	if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > 0.05)
		cache_put_data($cache_key, $message, 240);

	// If this was a force parse revert if needed.
	if (!empty($parse_tags))
	{
		$alltags_regex = empty($real_alltags_regex) ? '' : $real_alltags_regex;
		unset($real_alltags_regex);
	}
	elseif (!empty($bbc_codes))
		$bbc_lang_locales[$txt['lang_locale']] = $bbc_codes;

	return $message;
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
 * @param string &$message The message to parse smileys in
 */
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info, $context, $smcFunc;
	static $smileyPregSearch = null, $smileyPregReplacements = array();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none' || trim($message) == '')
		return;

	// Maybe a mod wants to implement an alternative method (e.g. emojis instead of images)
	call_integration_hook('integrate_smileys', array(&$smileyPregSearch, &$smileyPregReplacements));

	// If smileyPregSearch hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		// Cache for longer when customized smiley codes aren't enabled
		$cache_time = empty($modSettings['smiley_enable']) ? 7200 : 480;

		// Load the smileys in reverse order by length so they don't get parsed incorrectly.
		if (($temp = cache_get_data('parsing_smileys_' . $user_info['smiley_set'], $cache_time)) == null)
		{
			$result = $smcFunc['db_query']('', '
				SELECT s.code, f.filename, s.description
				FROM {db_prefix}smileys AS s
					JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
				WHERE f.smiley_set = {string:smiley_set}' . (empty($modSettings['smiley_enable']) ? '
					AND s.code IN ({array_string:default_codes})' : '') . '
				ORDER BY LENGTH(s.code) DESC',
				array(
					'default_codes' => array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)'),
					'smiley_set' => $user_info['smiley_set'],
				)
			);
			$smileysfrom = array();
			$smileysto = array();
			$smileysdescs = array();
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$smileysfrom[] = $row['code'];
				$smileysto[] = $smcFunc['htmlspecialchars']($row['filename']);
				$smileysdescs[] = !empty($txt['icon_' . strtolower($row['description'])]) ? $txt['icon_' . strtolower($row['description'])] : $row['description'];
			}
			$smcFunc['db_free_result']($result);

			cache_put_data('parsing_smileys_' . $user_info['smiley_set'], array($smileysfrom, $smileysto, $smileysdescs), $cache_time);
		}
		else
			list ($smileysfrom, $smileysto, $smileysdescs) = $temp;

		// The non-breaking-space is a complex thing...
		$non_breaking_space = $context['utf8'] ? '\x{A0}' : '\xA0';

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$smileyPregReplacements = array();
		$searchParts = array();
		$smileys_path = $smcFunc['htmlspecialchars']($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/');

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = $smcFunc['htmlspecialchars']($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" title="' . strtr($smcFunc['htmlspecialchars']($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley">';

			$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = $smileysfrom[$i];
			if ($smileysfrom[$i] != $specialChars)
			{
				$smileyPregReplacements[$specialChars] = $smileyCode;
				$searchParts[] = $specialChars;

				// Some 2.0 hex htmlchars are in there as 3 digits; allow for finding leading 0 or not
				$specialChars2 = preg_replace('/&#(\d{2});/', '&#0$1;', $specialChars);
				if ($specialChars2 != $specialChars)
				{
					$smileyPregReplacements[$specialChars2] = $smileyCode;
					$searchParts[] = $specialChars2;
				}
			}
		}

		$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|(?<![a-zA-Z0-9])\(|^)(' . build_regex($searchParts, '~') . ')(?=[^[:alpha:]0-9]|$)~' . ($context['utf8'] ? 'u' : '');
	}

	// If there are no smileys defined, no need to replace anything
	if (empty($smileyPregReplacements))
		return;

	// Replace away!
	$message = preg_replace_callback(
		$smileyPregSearch,
		function($matches) use ($smileyPregReplacements)
		{
			return $smileyPregReplacements[$matches[1]];
		},
		$message
	);
}

/**
 * Highlight any code.
 *
 * Uses PHP's highlight_string() to highlight PHP syntax
 * does special handling to keep the tabs in the code available.
 * used to parse PHP code from inside [code] and [php] tags.
 *
 * @param string $code The code
 * @return string The code with highlighted HTML.
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
 * Gets the appropriate URL to use for images (or whatever) when using SSL
 *
 * The returned URL may or may not be a proxied URL, depending on the situation.
 * Mods can implement alternative proxies using the 'integrate_proxy' hook.
 *
 * @param string $url The original URL of the requested resource
 * @return string The URL to use
 */
function get_proxied_url($url)
{
	global $boardurl, $image_proxy_enabled, $image_proxy_secret, $user_info;

	// Only use the proxy if enabled, and never for robots
	if (empty($image_proxy_enabled) || !empty($user_info['possibly_robot']))
		return $url;

	$parsedurl = parse_iri($url);

	// Don't bother with HTTPS URLs, schemeless URLs, or obviously invalid URLs
	if (empty($parsedurl['scheme']) || empty($parsedurl['host']) || empty($parsedurl['path']) || $parsedurl['scheme'] === 'https')
		return $url;

	// We don't need to proxy our own resources
	if ($parsedurl['host'] === parse_iri($boardurl, PHP_URL_HOST))
		return strtr($url, array('http://' => 'https://'));

	// By default, use SMF's own image proxy script
	$proxied_url = strtr($boardurl, array('http://' => 'https://')) . '/proxy.php?request=' . urlencode($url) . '&hash=' . hash_hmac('sha1', $url, $image_proxy_secret);

	// Allow mods to easily implement an alternative proxy
	// MOD AUTHORS: To add settings UI for your proxy, use the integrate_general_settings hook.
	call_integration_hook('integrate_proxy', array($url, &$proxied_url));

	return $proxied_url;
}

/**
 * Make sure the browser doesn't come back and repost the form data.
 * Should be used whenever anything is posted.
 *
 * @param string $setLocation The URL to redirect them to
 * @param bool $refresh Whether to use a meta refresh instead
 * @param bool $permanent Whether to send a 301 Moved Permanently instead of a 302 Moved Temporarily
 */
function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	global $scripturl, $context, $modSettings, $db_show_debug, $db_cache;

	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty($context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if ($add)
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
			$setLocation = preg_replace_callback(
				'~^' . preg_quote($scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~',
				function($m) use ($scripturl)
				{
					return $scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html?' . SID . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
		else
			$setLocation = preg_replace_callback(
				'~^' . preg_quote($scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~',
				function($m) use ($scripturl)
				{
					return $scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html' . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh, &$permanent));

	// Set the header.
	header('location: ' . str_replace(' ', '%20', $setLocation), true, $permanent ? 301 : 302);

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}

/**
 * Ends execution.  Takes care of template loading and remembering the previous URL.
 *
 * @param bool $header Whether to do the header
 * @param bool $do_footer Whether to do the footer
 * @param bool $from_index Whether we're coming from the board index
 * @param bool $from_fatal_error Whether we're coming from a fatal error
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $settings, $modSettings, $txt, $smcFunc, $should_log;
	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	if (function_exists('trackStats'))
		trackStats();

	// If we have mail to send, send it.
	if (function_exists('AddMailQueue') && !empty($context['flush_mail']))
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
			$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](html_entity_decode($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');

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
	if ($should_log)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
		exit;
}

/**
 * Get the size of a specified image with better error handling.
 *
 * @todo see if it's better in Subs-Graphics, but one step at the time.
 * Uses getimagesize() to determine the size of a file.
 * Attempts to connect to the server first so it won't time out.
 *
 * @param string $url The URL of the image
 * @return array|false The image size as array (width, height), or false on failure
 */
function url_image_size($url)
{
	global $sourcedir;

	// Make sure it is a proper URL.
	$url = str_replace(' ', '%20', $url);

	// Can we pull this from the cache... please please?
	if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime(true);

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
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'user-agent: '. SMF_USER_AGENT . "\r\n" . 'Connection: close' . "\r\n\r\n");

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
	if (microtime(true) - $t > 0.8)
		cache_put_data('url_image_size-' . md5($url), $size, 240);

	// Didn't work.
	return $size;
}

/**
 * Sets up the basic theme context stuff.
 *
 * @param bool $forceload Whether to load the theme even if it's already loaded
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
	$context['random_news_line'] = array();

	// Get some news...
	$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
	for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
	{
		if (trim($context['news_lines'][$i]) == '')
			continue;

		// Clean it up for presentation ;).
		$context['news_lines'][$i] = parse_bbc(stripslashes(trim($context['news_lines'][$i])), true, 'news' . $i);
	}

	if (!empty($context['news_lines']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
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
			$context['unapproved_members'] = !empty($modSettings['unapprovedMembers']) ? $modSettings['unapprovedMembers'] : 0;

		$context['user']['avatar'] = set_avatar_data(array(
			'filename' => $user_info['avatar']['filename'],
			'avatar' => $user_info['avatar']['url'],
			'email' => $user_info['email'],
		));

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
		addInlineJavaScript('
		jQuery(document).ready(function($) {
			new smc_Popup({
				heading: ' . JavaScriptEscape($txt['show_personal_messages_heading']) . ',
				content: ' . JavaScriptEscape(sprintf($txt['show_personal_messages'], $context['user']['unread_messages'], $scripturl . '?action=pm')) . ',
				icon_class: \'main_icons mail_new\'
			});
		});');

	// Add a generic "Are you sure?" confirmation message.
	addInlineJavaScript('
	var smf_you_sure =' . JavaScriptEscape($txt['quickmod_confirm']) . ';');

	// Now add the capping code for avatars.
	if (!empty($modSettings['avatar_max_width_external']) && !empty($modSettings['avatar_max_height_external']) && !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_css_resize')
		addInlineCss('
	img.avatar { max-width: ' . $modSettings['avatar_max_width_external'] . 'px !important; max-height: ' . $modSettings['avatar_max_height_external'] . 'px !important; }');

	// Add max image limits
	if (!empty($modSettings['max_image_width']))
		addInlineCss('
	.postarea .bbc_img, .list_posts .bbc_img, .post .inner .bbc_img, form#reported_posts .bbc_img, #preview_body .bbc_img { max-width: min(100%,' . $modSettings['max_image_width'] . 'px); }');

	if (!empty($modSettings['max_image_height']))
		addInlineCss('
	.postarea .bbc_img, .list_posts .bbc_img, .post .inner .bbc_img, form#reported_posts .bbc_img, #preview_body .bbc_img { max-height: ' . $modSettings['max_image_height'] . 'px; }');

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
		addJavaScriptVar('smf_scripturl', $scripturl);

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](html_entity_decode($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? $smcFunc['htmlspecialchars']($modSettings['meta_keywords']) : '';

	// Content related meta tags, including Open Graph
	$context['meta_tags'][] = array('property' => 'og:site_name', 'content' => $context['forum_name']);
	$context['meta_tags'][] = array('property' => 'og:title', 'content' => $context['page_title_html_safe']);

	if (!empty($context['meta_keywords']))
		$context['meta_tags'][] = array('name' => 'keywords', 'content' => $context['meta_keywords']);

	if (!empty($context['canonical_url']))
		$context['meta_tags'][] = array('property' => 'og:url', 'content' => $context['canonical_url']);

	if (!empty($settings['og_image']))
		$context['meta_tags'][] = array('property' => 'og:image', 'content' => $settings['og_image']);

	if (!empty($context['meta_description']))
	{
		$context['meta_tags'][] = array('property' => 'og:description', 'content' => $context['meta_description']);
		$context['meta_tags'][] = array('name' => 'description', 'content' => $context['meta_description']);
	}
	else
	{
		$context['meta_tags'][] = array('property' => 'og:description', 'content' => $context['page_title_html_safe']);
		$context['meta_tags'][] = array('name' => 'description', 'content' => $context['page_title_html_safe']);
	}

	call_integration_hook('integrate_theme_context');
}

/**
 * Helper function to set the system memory to a needed value
 * - If the needed memory is greater than current, will attempt to get more
 * - if in_use is set to true, will also try to take the current memory usage in to account
 *
 * @param string $needed The amount of memory to request, if needed, like 256M
 * @param bool $in_use Set to true to account for current memory usage of the script
 * @return boolean True if we have at least the needed memory
 */
function setMemoryLimit($needed, $in_use = false)
{
	// everything in bytes
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
	global $txt, $modSettings, $context, $user_info, $boarddir, $cachedir, $cache_enable, $language;

	setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		// Are we debugging the template/html content?
		if (!isset($_REQUEST['xml']) && isset($_GET['debug']) && !isBrowser('ie'))
			header('content-type: application/xhtml+xml');
		elseif (!isset($_REQUEST['xml']))
			header('content-type: text/html; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	}

	header('content-type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

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
				$path = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

			else
				$path = $modSettings['attachmentUploadDir'];

			secureDirectory($path, true);
			secureDirectory($cachedir);

			// If agreement is enabled, at least the english version shall exist
			if (!empty($modSettings['requireAgreement']))
				$agreement = !file_exists($boarddir . '/agreement.txt');

			// If privacy policy is enabled, at least the default language version shall exist
			if (!empty($modSettings['requirePolicyAgreement']))
				$policy_agreement = empty($modSettings['policy_' . $language]);

			if (!empty($securityFiles) ||
				(!empty($cache_enable) && !is_writable($cachedir)) ||
				!empty($agreement) ||
				!empty($policy_agreement) ||
				!empty($context['auth_secret_missing']))
			{
				echo '
		<div class="errorbox">
			<p class="alert">!!</p>
			<h3>', empty($securityFiles) && empty($context['auth_secret_missing']) ? $txt['generic_warning'] : $txt['security_risk'], '</h3>
			<p>';

				foreach ($securityFiles as $securityFile)
				{
					echo '
				', $txt['not_removed'], '<strong>', $securityFile, '</strong>!<br>';

					if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
						echo '
				', sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br>';
				}

				if (!empty($cache_enable) && !is_writable($cachedir))
					echo '
				<strong>', $txt['cache_writable'], '</strong><br>';

				if (!empty($agreement))
					echo '
				<strong>', $txt['agreement_missing'], '</strong><br>';

				if (!empty($policy_agreement))
					echo '
				<strong>', $txt['policy_agreement_missing'], '</strong><br>';

				if (!empty($context['auth_secret_missing']))
					echo '
				<strong>', $txt['auth_secret_missing'], '</strong><br>';

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
	global $forum_copyright, $scripturl;

	// Don't display copyright for things like SSI.
	if (SMF !== 1)
		return;

	// Put in the version...
	printf($forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, $scripturl);
}

/**
 * The template footer
 */
function template_footer()
{
	global $context, $modSettings, $db_count;

	// Show the load time?  (only makes sense for the footer.)
	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
	$context['load_time'] = round(microtime(true) - TIME_START, 3);
	$context['load_queries'] = $db_count;

	if (!empty($context['template_layers']) && is_array($context['template_layers']))
		foreach (array_reverse($context['template_layers']) as $layer)
			loadSubTemplate($layer . '_below', true);
}

/**
 * Output the Javascript files
 * 	- tabbing in this function is to make the HTML source look good and proper
 *  - if deferred is set function will output all JS set to load at page end
 *
 * @param bool $do_deferred If true will only output the deferred JS (the stuff that goes right before the closing body tag)
 */
function template_javascript($do_deferred = false)
{
	global $context, $modSettings, $settings;

	// Use this hook to minify/optimize Javascript files and vars
	call_integration_hook('integrate_pre_javascript_output', array(&$do_deferred));

	$toMinify = array(
		'standard' => array(),
		'defer' => array(),
		'async' => array(),
	);

	// Output the declared Javascript variables.
	if (!empty($context['javascript_vars']) && !$do_deferred)
	{
		echo '
	<script>';

		foreach ($context['javascript_vars'] as $key => $value)
		{
			if (!is_string($key) || is_numeric($key))
				continue;

			if (!is_string($value) && !is_numeric($value))
				$value = null;

			echo "\n\t\t", 'var ', $key, isset($value) ? ' = ' . $value : '', ';';
		}

		echo '
	</script>';
	}

	// In the dark days before HTML5, deferred JS files needed to be loaded at the end of the body.
	// Now we load them in the head and use 'async' and/or 'defer' attributes. Much better performance.
	if (!$do_deferred)
	{
		// While we have JavaScript files to place in the template.
		foreach ($context['javascript_files'] as $id => $js_file)
		{
			// Last minute call! allow theme authors to disable single files.
			if (!empty($settings['disable_files']) && in_array($id, $settings['disable_files']))
				continue;

			// By default files don't get minimized unless the file explicitly says so!
			if (!empty($js_file['options']['minimize']) && !empty($modSettings['minimize_files']))
			{
				if (!empty($js_file['options']['async']))
					$toMinify['async'][] = $js_file;

				elseif (!empty($js_file['options']['defer']))
					$toMinify['defer'][] = $js_file;

				else
					$toMinify['standard'][] = $js_file;

				// Grab a random seed.
				if (!isset($minSeed) && isset($js_file['options']['seed']))
					$minSeed = $js_file['options']['seed'];
			}

			else
			{
				echo '
	<script src="', $js_file['fileUrl'], isset($js_file['options']['seed']) ? $js_file['options']['seed'] : '', '"', !empty($js_file['options']['async']) ? ' async' : '', !empty($js_file['options']['defer']) ? ' defer' : '';

				if (!empty($js_file['options']['attributes']))
					foreach ($js_file['options']['attributes'] as $key => $value)
					{
						if (is_bool($value))
							echo !empty($value) ? ' ' . $key : '';

						else
							echo ' ', $key, '="', $value, '"';
					}

				echo '></script>';
			}
		}

		foreach ($toMinify as $js_files)
		{
			if (!empty($js_files))
			{
				$result = custMinify($js_files, 'js');

				$minSuccessful = array_keys($result) === array('smf_minified');

				foreach ($result as $minFile)
					echo '
	<script src="', $minFile['fileUrl'], $minSuccessful && isset($minSeed) ? $minSeed : '', '"', !empty($minFile['options']['async']) ? ' async' : '', !empty($minFile['options']['defer']) ? ' defer' : '', '></script>';
			}
		}
	}

	// Inline JavaScript - Actually useful some times!
	if (!empty($context['javascript_inline']))
	{
		if (!empty($context['javascript_inline']['defer']) && $do_deferred)
		{
			echo '
<script>
window.addEventListener("DOMContentLoaded", function() {';

			foreach ($context['javascript_inline']['defer'] as $js_code)
				echo $js_code;

			echo '
});
</script>';
		}

		if (!empty($context['javascript_inline']['standard']) && !$do_deferred)
		{
			echo '
	<script>';

			foreach ($context['javascript_inline']['standard'] as $js_code)
				echo $js_code;

			echo '
	</script>';
		}
	}
}

/**
 * Output the CSS files
 */
function template_css()
{
	global $context, $db_show_debug, $boardurl, $settings, $modSettings;

	// Use this hook to minify/optimize CSS files
	call_integration_hook('integrate_pre_css_output');

	$toMinify = array();
	$normal = array();

	uasort(
		$context['css_files'],
		function ($a, $b)
		{
			return $a['options']['order_pos'] < $b['options']['order_pos'] ? -1 : ($a['options']['order_pos'] > $b['options']['order_pos'] ? 1 : 0);
		}
	);

	foreach ($context['css_files'] as $id => $file)
	{
		// Last minute call! allow theme authors to disable single files.
		if (!empty($settings['disable_files']) && in_array($id, $settings['disable_files']))
			continue;

		// Files are minimized unless they explicitly opt out.
		if (!isset($file['options']['minimize']))
			$file['options']['minimize'] = true;

		if (!empty($file['options']['minimize']) && !empty($modSettings['minimize_files']) && !isset($_REQUEST['normalcss']))
		{
			$toMinify[] = $file;

			// Grab a random seed.
			if (!isset($minSeed) && isset($file['options']['seed']))
				$minSeed = $file['options']['seed'];
		}
		else
			$normal[] = array(
				'url' => $file['fileUrl'] . (isset($file['options']['seed']) ? $file['options']['seed'] : ''),
				'attributes' => !empty($file['options']['attributes']) ? $file['options']['attributes'] : array()
			);
	}

	if (!empty($toMinify))
	{
		$result = custMinify($toMinify, 'css');

		$minSuccessful = array_keys($result) === array('smf_minified');

		foreach ($result as $minFile)
			echo '
	<link rel="stylesheet" href="', $minFile['fileUrl'], $minSuccessful && isset($minSeed) ? $minSeed : '', '">';
	}

	// Print the rest after the minified files.
	if (!empty($normal))
		foreach ($normal as $nf)
		{
			echo '
	<link rel="stylesheet" href="', $nf['url'], '"';

			if (!empty($nf['attributes']))
				foreach ($nf['attributes'] as $key => $value)
				{
					if (is_bool($value))
						echo !empty($value) ? ' ' . $key : '';
					else
						echo ' ', $key, '="', $value, '"';
				}

			echo '>';
		}

	if ($db_show_debug === true)
	{
		// Try to keep only what's useful.
		$repl = array($boardurl . '/Themes/' => '', $boardurl . '/' => '');
		foreach ($context['css_files'] as $file)
			$context['debug']['sheets'][] = strtr($file['fileName'], $repl);
	}

	if (!empty($context['css_header']))
	{
		echo '
	<style>';

		foreach ($context['css_header'] as $css)
			echo $css . '
	';

		echo '
	</style>';
	}
}

/**
 * Get an array of previously defined files and adds them to our main minified files.
 * Sets a one day cache to avoid re-creating a file on every request.
 *
 * @param array $data The files to minify.
 * @param string $type either css or js.
 * @return array Info about the minified file, or about the original files if the minify process failed.
 */
function custMinify($data, $type)
{
	global $settings, $txt;

	$types = array('css', 'js');
	$type = !empty($type) && in_array($type, $types) ? $type : false;
	$data = is_array($data) ? $data : array();

	if (empty($type) || empty($data))
		return $data;

	// Different pages include different files, so we use a hash to label the different combinations
	$hash = md5(implode(' ', array_map(
		function($file)
		{
			return $file['filePath'] . '-' . $file['mtime'];
		},
		$data
	)));

	// Is this a deferred or asynchronous JavaScript file?
	$async = $type === 'js';
	$defer = $type === 'js';
	if ($type === 'js')
	{
		foreach ($data as $id => $file)
		{
			// A minified script should only be loaded asynchronously if all its components wanted to be.
			if (empty($file['options']['async']))
				$async = false;

			// A minified script should only be deferred if all its components wanted to be.
			if (empty($file['options']['defer']))
				$defer = false;
		}
	}

	// Did we already do this?
	$minified_file = $settings['theme_dir'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/minified_' . $hash . '.' . $type;
	$already_exists = file_exists($minified_file);

	// Already done?
	if ($already_exists)
	{
		return array('smf_minified' => array(
			'fileUrl' => $settings['theme_url'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/' . basename($minified_file),
			'filePath' => $minified_file,
			'fileName' => basename($minified_file),
			'options' => array('async' => !empty($async), 'defer' => !empty($defer)),
		));
	}
	// File has to exist. If it doesn't, try to create it.
	elseif (@fopen($minified_file, 'w') === false || !smf_chmod($minified_file))
	{
		loadLanguage('Errors');
		log_error(sprintf($txt['file_not_created'], $minified_file), 'general');

		// The process failed, so roll back to print each individual file.
		return $data;
	}

	// No namespaces, sorry!
	$classType = 'MatthiasMullie\\Minify\\' . strtoupper($type);

	$minifier = new $classType();

	foreach ($data as $id => $file)
	{
		$toAdd = !empty($file['filePath']) && file_exists($file['filePath']) ? $file['filePath'] : false;

		// The file couldn't be located so it won't be added. Log this error.
		if (empty($toAdd))
		{
			loadLanguage('Errors');
			log_error(sprintf($txt['file_minimize_fail'], !empty($file['fileName']) ? $file['fileName'] : $id), 'general');
			continue;
		}

		// Add this file to the list.
		$minifier->add($toAdd);
	}

	// Create the file.
	$minifier->minify($minified_file);
	unset($minifier);
	clearstatcache();

	// Minify process failed.
	if (!filesize($minified_file))
	{
		loadLanguage('Errors');
		log_error(sprintf($txt['file_not_created'], $minified_file), 'general');

		// The process failed so roll back to print each individual file.
		return $data;
	}

	return array('smf_minified' => array(
		'fileUrl' => $settings['theme_url'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/' . basename($minified_file),
		'filePath' => $minified_file,
		'fileName' => basename($minified_file),
		'options' => array('async' => $async, 'defer' => $defer),
	));
}

/**
 * Clears out old minimized CSS and JavaScript files and ensures $modSettings['browser_cache'] is up to date
 */
function deleteAllMinified()
{
	global $smcFunc, $txt, $modSettings;

	$not_deleted = array();
	$most_recent = 0;

	// Kinda sucks that we need to do another query to get all the theme dirs, but c'est la vie.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme AS id, value AS dir
		FROM {db_prefix}themes
		WHERE variable = {string:var}',
		array(
			'var' => 'theme_dir',
		)
	);
	while ($theme = $smcFunc['db_fetch_assoc']($request))
	{
		foreach (array('css', 'js') as $type)
		{
			foreach (glob(rtrim($theme['dir'], '/') . '/' . ($type == 'css' ? 'css' : 'scripts') . '/*.' . $type) as $filename)
			{
				// We want to find the most recent mtime of non-minified files
				if (strpos(pathinfo($filename, PATHINFO_BASENAME), 'minified') === false)
					$most_recent = max($most_recent, (int) @filemtime($filename));

				// Try to delete minified files. Add them to our error list if that fails.
				elseif (!@unlink($filename))
					$not_deleted[] = $filename;
			}
		}
	}
	$smcFunc['db_free_result']($request);

	// This setting tracks the most recent modification time of any of our CSS and JS files
	if ($most_recent != $modSettings['browser_cache'])
		updateSettings(array('browser_cache' => $most_recent));

	// If any of the files could not be deleted, log an error about it.
	if (!empty($not_deleted))
	{
		loadLanguage('Errors');
		log_error(sprintf($txt['unlink_minimized_fail'], implode('<br>', $not_deleted)), 'general');
	}
}

/**
 * Get an attachment's encrypted filename. If $new is true, won't check for file existence.
 *
 * @todo this currently returns the hash if new, and the full filename otherwise.
 * Something messy like that.
 * @todo and of course everything relies on this behavior and work around it. :P.
 * Converters included.
 *
 * @param string $filename The name of the file
 * @param int $attachment_id The ID of the attachment
 * @param string|null $dir Which directory it should be in (null to use current one)
 * @param bool $new Whether this is a new attachment
 * @param string $file_hash The file hash
 * @return string The path to the file
 */
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings, $smcFunc;

	// Just make up a nice hash...
	if ($new)
		return sha1(md5($filename . time()) . mt_rand());

	// Just make sure that attachment id is only a int
	$attachment_id = (int) $attachment_id;

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
			)
		);

		if ($smcFunc['db_num_rows']($request) === 0)
			return false;

		list ($file_hash) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Still no hash? mmm...
	if (empty($file_hash))
		$file_hash = sha1(md5($filename . time()) . mt_rand());

	// Are we using multiple directories?
	if (is_array($modSettings['attachmentUploadDir']))
		$path = $modSettings['attachmentUploadDir'][$dir];

	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash . '.dat';
}

/**
 * Convert a single IP to a ranged IP.
 * internal function used to convert a user-readable format to a format suitable for the database.
 *
 * @param string $fullip The full IP
 * @return array An array of IP parts
 */
function ip2range($fullip)
{
	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('-', $fullip);
	$ip_array = array();

	// if ip 22.12.31.21
	if (count($ip_parts) == 1 && isValidIP($fullip))
	{
		$ip_array['low'] = $fullip;
		$ip_array['high'] = $fullip;
		return $ip_array;
	} // if ip 22.12.* -> 22.12.*-22.12.*
	elseif (count($ip_parts) == 1)
	{
		$ip_parts[0] = $fullip;
		$ip_parts[1] = $fullip;
	}

	// if ip 22.12.31.21-12.21.31.21
	if (count($ip_parts) == 2 && isValidIP($ip_parts[0]) && isValidIP($ip_parts[1]))
	{
		$ip_array['low'] = $ip_parts[0];
		$ip_array['high'] = $ip_parts[1];
		return $ip_array;
	}
	elseif (count($ip_parts) == 2) // if ip 22.22.*-22.22.*
	{
		$valid_low = isValidIP($ip_parts[0]);
		$valid_high = isValidIP($ip_parts[1]);
		$count = 0;
		$mode = (preg_match('/:/', $ip_parts[0]) > 0 ? ':' : '.');
		$max = ($mode == ':' ? 'ffff' : '255');
		$min = 0;
		if (!$valid_low)
		{
			$ip_parts[0] = preg_replace('/\*/', '0', $ip_parts[0]);
			$valid_low = isValidIP($ip_parts[0]);
			while (!$valid_low)
			{
				$ip_parts[0] .= $mode . $min;
				$valid_low = isValidIP($ip_parts[0]);
				$count++;
				if ($count > 9) break;
			}
		}

		$count = 0;
		if (!$valid_high)
		{
			$ip_parts[1] = preg_replace('/\*/', $max, $ip_parts[1]);
			$valid_high = isValidIP($ip_parts[1]);
			while (!$valid_high)
			{
				$ip_parts[1] .= $mode . $max;
				$valid_high = isValidIP($ip_parts[1]);
				$count++;
				if ($count > 9) break;
			}
		}

		if ($valid_high && $valid_low)
		{
			$ip_array['low'] = $ip_parts[0];
			$ip_array['high'] = $ip_parts[1];
		}
	}

	return $ip_array;
}

/**
 * Lookup an IP; try shell_exec first because we can do a timeout on it.
 *
 * @param string $ip The IP to get the hostname from
 * @return string The hostname
 */
function host_from_ip($ip)
{
	global $modSettings;

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime(true);

	$exists = function_exists('shell_exec');

	// Try the Linux host command, perhaps?
	if ($exists && !isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
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
	if ($exists && !isset($host) && stripos(PHP_OS, 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
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
	if (microtime(true) - $t > 0.5)
		cache_put_data('hostlookup-' . $ip, $host, 600);

	return $host;
}

/**
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text The text to split into words
 * @param int $max_chars The maximum number of characters per word
 * @param bool $encrypt Whether to encrypt the results
 * @return array An array of ints or words depending on $encrypt
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	global $smcFunc, $context;

	// Upgrader may be working on old DBs...
	if (!isset($context['utf8']))
		$context['utf8'] = false;

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
					$total += $possible_chars[ord($encrypted[$i])] * pow(63, $i);
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
 * @deprecated since 2.1
 * @param string $name The name of the button (should be a main_icons class or the name of an image)
 * @param string $alt The alt text
 * @param string $label The $txt string to use as the label
 * @param string $custom Custom text/html to add to the img tag (only when using an actual image)
 * @param boolean $force_use Whether to force use of this when template_create_button is available
 * @return string The HTML to display the button
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
		return '<span class="main_icons ' . $name . '" alt="' . $txt[$alt] . '"></span>' . ($label != '' ? '&nbsp;<strong>' . $txt[$label] . '</strong>' : '');
	else
		return '<img src="' . $settings['lang_images_url'] . '/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . '>';
}

/**
 * Sets up all of the top menu buttons
 * Saves them in the cache if it is available and on
 * Places the results in $context
 */
function setupMenuContext()
{
	global $context, $modSettings, $user_info, $txt, $scripturl, $sourcedir, $settings, $smcFunc, $cache_enable;

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
		addInlineJavaScript('
	var user_menus = new smc_PopupMenu();
	user_menus.add("profile", "' . $scripturl . '?action=profile;area=popup");
	user_menus.add("alerts", "' . $scripturl . '?action=profile;area=alerts_popup;u=' . $context['user']['id'] . '");', true);
		if ($context['allow_pm'])
			addInlineJavaScript('
	user_menus.add("pm", "' . $scripturl . '?action=pm;sa=popup");', true);

		if (!empty($modSettings['enable_ajax_alerts']))
		{
			require_once($sourcedir . '/Subs-Notify.php');

			$timeout = getNotifyPrefs($context['user']['id'], 'alert_timeout', true);
			$timeout = empty($timeout) ? 10000 : $timeout[$context['user']['id']]['alert_timeout'] * 1000;

			addInlineJavaScript('
	var new_alert_title = "' . $context['forum_name_html_safe'] . '";
	var alert_timeout = ' . $timeout . ';');
			loadJavaScriptFile('alerts.js', array('minimize' => true), 'smf_alerts');
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
						'title' => $txt['errorlog'],
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
				'is_last' => !$context['right_to_left'] && empty($settings['login_main_menu']),
			),
			// Theme authors: If you want the login and register buttons to appear in
			// the main forum menu on your theme, set $settings['login_main_menu'] to
			// true in your theme's template_init() function in index.template.php.
			'login' => array(
				'title' => $txt['login'],
				'href' => $scripturl . '?action=login',
				'onclick' => 'return reqOverlayDiv(this.href, ' . JavaScriptEscape($txt['login']) . ', \'login\');',
				'show' => $user_info['is_guest'] && !empty($settings['login_main_menu']),
				'sub_buttons' => array(
				),
				'is_last' => !$context['right_to_left'],
			),
			'logout' => array(
				'title' => $txt['logout'],
				'href' => $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id'],
				'show' => !$user_info['is_guest'] && !empty($settings['login_main_menu']),
				'sub_buttons' => array(
				),
				'is_last' => !$context['right_to_left'],
			),
			'signup' => array(
				'title' => $txt['register'],
				'href' => $scripturl . '?action=signup',
				'icon' => 'regcenter',
				'show' => $user_info['is_guest'] && $context['can_register'] && !empty($settings['login_main_menu']),
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

				// Make sure the last button truly is the last button.
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
					$button['icon'] = '<span class="main_icons ' . $button['icon'] . '"></span>';
				else
					$button['icon'] = '<span class="main_icons ' . $act . '"></span>';

				$menu_buttons[$act] = $button;
			}

		if (!empty($cache_enable) && $cache_enable >= 2)
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
	elseif ($context['current_action'] == 'signup2')
		$current_action = 'signup';
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

	$context['total_mod_reports'] = 0;
	$context['total_admin_reports'] = 0;

	if (!empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1' && !empty($context['open_mod_reports']) && !empty($context['menu_buttons']['moderate']['sub_buttons']['reports']))
	{
		$context['total_mod_reports'] = $context['open_mod_reports'];
		$context['menu_buttons']['moderate']['sub_buttons']['reports']['amt'] = $context['open_mod_reports'];
	}

	// Show how many errors there are
	if (!empty($context['menu_buttons']['admin']['sub_buttons']['errorlog']))
	{
		// Get an error count, if necessary
		if (!isset($context['num_errors']))
		{
			$query = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}log_errors',
				array()
			);

			list($context['num_errors']) = $smcFunc['db_fetch_row']($query);
			$smcFunc['db_free_result']($query);
		}

		if (!empty($context['num_errors']))
		{
			$context['total_admin_reports'] += $context['num_errors'];
			$context['menu_buttons']['admin']['sub_buttons']['errorlog']['amt'] = $context['num_errors'];
		}
	}

	// Show number of reported members
	if (!empty($context['open_member_reports']) && !empty($context['menu_buttons']['moderate']['sub_buttons']['reported_members']))
	{
		$context['total_mod_reports'] += $context['open_member_reports'];
		$context['menu_buttons']['moderate']['sub_buttons']['reported_members']['amt'] = $context['open_member_reports'];
	}

	if (!empty($context['unapproved_members']) && !empty($context['menu_buttons']['admin']))
	{
		$context['menu_buttons']['admin']['sub_buttons']['memberapprove']['amt'] = $context['unapproved_members'];
		$context['total_admin_reports'] += $context['unapproved_members'];
	}

	if ($context['total_admin_reports'] > 0 && !empty($context['menu_buttons']['admin']))
	{
		$context['menu_buttons']['admin']['amt'] = $context['total_admin_reports'];
	}

	// Do we have any open reports?
	if ($context['total_mod_reports'] > 0 && !empty($context['menu_buttons']['moderate']))
	{
		$context['menu_buttons']['moderate']['amt'] = $context['total_mod_reports'];
	}

	// Not all actions are simple.
	call_integration_hook('integrate_current_action', array(&$current_action));

	if (isset($context['menu_buttons'][$current_action]))
		$context['menu_buttons'][$current_action]['active_button'] = true;
}

/**
 * Generate a random seed and ensure it's stored in settings.
 */
function smf_seed_generator()
{
	updateSettings(array('rand_seed' => microtime(true)));
}

/**
 * Process functions of an integration hook.
 * calls all functions of the given hook.
 * supports static class method calls.
 *
 * @param string $hook The hook name
 * @param array $parameters An array of parameters this hook implements
 * @return array The results of the functions
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

	$functions = explode(',', $modSettings[$hook]);
	// Loop through each function.
	foreach ($functions as $function)
	{
		// Hook has been marked as "disabled". Skip it!
		if (strpos($function, '!') !== false)
			continue;

		$call = call_helper($function, true);

		// Is it valid?
		if (!empty($call))
			$results[$function] = call_user_func_array($call, $parameters);
		// This failed, but we want to do so silently.
		elseif (!empty($function) && !empty($context['ignore_hook_errors']))
			return $results;
		// Whatever it was suppose to call, it failed :(
		elseif (!empty($function))
		{
			loadLanguage('Errors');

			// Get a full path to show on error.
			if (strpos($function, '|') !== false)
			{
				list ($file, $string) = explode('|', $function);
				$absPath = empty($settings['theme_dir']) ? (strtr(trim($file), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir))) : (strtr(trim($file), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'])));
				log_error(sprintf($txt['hook_fail_call_to'], $string, $absPath), 'general');
			}
			// "Assume" the file resides on $boarddir somewhere...
			else
				log_error(sprintf($txt['hook_fail_call_to'], $function, $boarddir), 'general');
		}
	}

	return $results;
}

/**
 * Add a function for integration hook.
 * Does nothing if the function is already added.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param bool $permanent If true, updates the value in settings table.
 * @param string $file The file. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param bool $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
 */
function add_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	global $smcFunc, $modSettings, $context;

	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . (!empty($function) ? '|' . $function : '');

	// Get the correct string.
	$integration_call = $function;
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

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

			// Cleanup enabled/disabled variants before taking action.
			$current_functions = array_diff($current_functions, array($enabled_call, $disabled_call));
	
			$permanent_functions = array_unique(array_merge($current_functions, array($integration_call)));
		}
		else
			$permanent_functions = array($integration_call);

		updateSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// Cleanup enabled/disabled variants before taking action.
	$functions = array_diff($functions, array($enabled_call, $disabled_call));

	$functions = array_unique(array_merge($functions, array($integration_call)));
	$modSettings[$hook] = implode(',', $functions);

	// It is handy to be able to know which hooks are temporary...
	if ($permanent !== true)
	{
		if (!isset($context['integration_hooks_temporary']))
			$context['integration_hooks_temporary'] = array();
		$context['integration_hooks_temporary'][$hook][$function] = true;
	}
}

/**
 * Remove an integration hook function.
 * Removes the given function from the given hook.
 * Does nothing if the function is not available.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param boolean $permanent Irrelevant for the function itself but need to declare it to match
 * @param string $file The filename. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param boolean $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
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
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

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

		// Cleanup enabled and disabled variants.
		$current_functions = array_unique(array_diff($current_functions, array($enabled_call, $disabled_call)));

		updateSettings(array($hook => implode(',', $current_functions)));
	}

	// Turn the function list into something usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// Cleanup enabled and disabled variants.
	$functions = array_unique(array_diff($functions, array($enabled_call, $disabled_call)));

	$modSettings[$hook] = implode(',', $functions);
}

/**
 * Receives a string and tries to figure it out if it's a method or a function.
 * If a method is found, it looks for a "#" which indicates SMF should create a new instance of the given class.
 * Checks the string/array for is_callable() and return false/fatal_lang_error is the given value results in a non callable string/array.
 * Prepare and returns a callable depending on the type of method/function found.
 *
 * @param mixed $string The string containing a function name or a static call. The function can also accept a closure, object or a callable array (object/class, valid_callable)
 * @param boolean $return If true, the function will not call the function/method but instead will return the formatted string.
 * @return string|array|boolean Either a string or an array that contains a callable function name or an array with a class and method to call. Boolean false if the given string cannot produce a callable var.
 */
function call_helper($string, $return = false)
{
	global $context, $smcFunc, $txt, $db_show_debug;

	// Really?
	if (empty($string))
		return false;

	// An array? should be a "callable" array IE array(object/class, valid_callable).
	// A closure? should be a callable one.
	if (is_array($string) || $string instanceof Closure)
		return $return ? $string : (is_callable($string) ? call_user_func($string) : false);

	// No full objects, sorry! pass a method or a property instead!
	if (is_object($string))
		return false;

	// Stay vitaminized my friends...
	$string = $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($string));

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

	// We can't call this helper, but we want to silently ignore this.
	if (!is_callable($func, false, $callable_name) && !empty($context['ignore_hook_errors']))
		return false;

	// Right, we got what we need, time to do some checks.
	elseif (!is_callable($func, false, $callable_name))
	{
		loadLanguage('Errors');
		log_error(sprintf($txt['sub_action_fail'], $callable_name), 'general');

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
	global $sourcedir, $txt, $boarddir, $settings, $context;

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
			$absPath = $sourcedir . '/' . $file;

			if (file_exists($absPath))
				require_once($absPath);

			// Sorry, can't do much for you at this point.
			elseif (empty($context['uninstalling']))
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
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of an http or ftp address and returns the page in a string
 * - will accept up to 3 page redirections (redirectio_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @param string $url The URL
 * @param string $post_data The data to post to the given URL
 * @param bool $keep_alive Whether to send keepalive info
 * @param int $redirection_level How many levels of redirection
 * @return string|false The fetched data or false on failure
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 0)
{
	global $webmaster_email, $sourcedir, $txt;
	static $keep_alive_dom = null, $keep_alive_fp = null;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', iri_to_url($url), $match);

	// No scheme? No data for you!
	if (empty($match[1]))
		return false;

	// An FTP url. We should try connecting and RETRieving it...
	elseif ($match[1] == 'ftp')
	{
		// Include the file containing the ftp_connection class.
		require_once($sourcedir . '/Class-Package.php');

		// Establish a connection and attempt to enable passive mode.
		$ftp = new ftp_connection(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', $webmaster_email);
		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}

	// This is more likely; a standard HTTP URL.
	elseif (isset($match[1]) && $match[1] == 'http')
	{
		// First try to use fsockopen, because it is fastest.
		if ($keep_alive && $match[3] == $keep_alive_dom)
			$fp = $keep_alive_fp;
		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
		}
		if (!empty($fp))
		{
			if ($keep_alive)
			{
				$keep_alive_dom = $match[3];
				$keep_alive_fp = $fp;
			}

			// I want this, from there, and I'm not going to be bothering you for more (probably.)
			if (empty($post_data))
			{
				fwrite($fp, 'GET ' . ($match[6] !== '/' ? str_replace(' ', '%20', $match[6]) : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n\r\n");
			}
			else
			{
				fwrite($fp, 'POST ' . ($match[6] !== '/' ? $match[6] : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n");
				fwrite($fp, 'content-type: application/x-www-form-urlencoded' . "\r\n");
				fwrite($fp, 'content-length: ' . strlen($post_data) . "\r\n\r\n");
				fwrite($fp, $post_data);
			}

			$response = fgets($fp, 768);

			// Redirect in case this location is permanently or temporarily moved.
			if ($redirection_level < 3 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
			{
				$header = '';
				$location = '';
				while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
					if (stripos($header, 'location:') !== false)
						$location = trim(substr($header, strpos($header, ':') + 1));

				if (empty($location))
					return false;
				else
				{
					if (!$keep_alive)
						fclose($fp);
					return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
				}
			}

			// Make sure we get a 200 OK.
			elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
				return false;

			// Skip the headers...
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
			{
				if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
					$content_length = $match[1];
				elseif (preg_match('~connection:\s*close~i', $header) != 0)
				{
					$keep_alive_dom = null;
					$keep_alive = false;
				}

				continue;
			}

			$data = '';
			if (isset($content_length))
			{
				while (!feof($fp) && strlen($data) < $content_length)
					$data .= fread($fp, $content_length - strlen($data));
			}
			else
			{
				while (!feof($fp))
					$data .= fread($fp, 4096);
			}

			if (!$keep_alive)
				fclose($fp);
		}

		// If using fsockopen didn't work, try to use cURL if available.
		elseif (function_exists('curl_init'))
		{
			// Include the file containing the curl_fetch_web_data class.
			require_once($sourcedir . '/Class-CurlFetchWeb.php');

			$fetch_data = new curl_fetch_web_data();
			$fetch_data->get_url_data($url, $post_data);

			// no errors and a 200 result, then we have a good dataset, well we at least have data. ;)
			if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
				$data = $fetch_data->result('body');
			else
				return false;
		}

		// Neither fsockopen nor curl are available. Well, phooey.
		else
			return false;
	}
	else
	{
		// Umm, this shouldn't happen?
		loadLanguage('Errors');
		trigger_error($txt['fetch_web_data_bad_url'], E_USER_NOTICE);
		$data = false;
	}

	return $data;
}

/**
 * Attempts to determine the MIME type of some data or a file.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return string|bool A MIME type, or false if we cannot determine it.
 */
function get_mime_type($data, $is_path = false)
{
	global $cachedir;

	$finfo_loaded = extension_loaded('fileinfo');
	$exif_loaded = extension_loaded('exif') && function_exists('image_type_to_mime_type');

	// Oh well. We tried.
	if (!$finfo_loaded && !$exif_loaded)
		return false;

	// Start with the 'empty' MIME type.
	$mime_type = 'application/x-empty';

	if ($finfo_loaded)
	{
		// Just some nice, simple data to analyze.
		if (empty($is_path))
			$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);

		// A file, or maybe a URL?
		else
		{
			// Local file.
			if (file_exists($data))
				$mime_type = mime_content_type($data);

			// URL.
			elseif ($data = fetch_web_data($data))
				$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
		}
	}
	// Workaround using Exif requires a local file.
	else
	{
		// If $data is a URL to fetch, do so.
		if (!empty($is_path) && !file_exists($data) && url_exists($data))
		{
			$data = fetch_web_data($data);
			$is_path = false;
		}

		// If we don't have a local file, create one and use it.
		if (empty($is_path))
		{
			$temp_file = tempnam($cachedir, md5($data));
			file_put_contents($temp_file, $data);
			$is_path = true;
			$data = $temp_file;
		}

		$imagetype = @exif_imagetype($data);

		if (isset($temp_file))
			unlink($temp_file);

		// Unfortunately, this workaround only works for image files.
		if ($imagetype !== false)
			$mime_type = image_type_to_mime_type($imagetype);
	}

	return $mime_type;
}

/**
 * Checks whether a file or data has the expected MIME type.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $type_pattern A regex pattern to match the acceptable MIME types.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return int 1 if the detected MIME type matches the pattern, 0 if it doesn't, or 2 if we can't check.
 */
function check_mime_type($data, $type_pattern, $is_path = false)
{
	// Get the MIME type.
	$mime_type = get_mime_type($data, $is_path);

	// Couldn't determine it.
	if ($mime_type === false)
		return 2;

	// Check whether the MIME type matches expectations.
	return (int) @preg_match('~' . $type_pattern . '~', $mime_type);
}

/**
 * Prepares an array of "likes" info for the topic specified by $topic
 *
 * @param integer $topic The topic ID to fetch the info from.
 * @return array An array of IDs of messages in the specified topic that the current user likes
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
 * @param string $string The string
 * @return string The sanitized string
 */
function sanitizeMSCutPaste($string)
{
	global $context;

	if (empty($string))
		return $string;

	// UTF-8 occurrences of MS special characters
	$findchars_utf8 = array(
		"\xe2\x80\x9a",	// single low-9 quotation mark
		"\xe2\x80\x9e",	// double low-9 quotation mark
		"\xe2\x80\xa6",	// horizontal ellipsis
		"\xe2\x80\x98",	// left single curly quote
		"\xe2\x80\x99",	// right single curly quote
		"\xe2\x80\x9c",	// left double curly quote
		"\xe2\x80\x9d",	// right double curly quote
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
 * @param array $matches An array of matches (relevant info should be the 3rd item)
 * @return string A fixed string
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
 * @param array $matches An array of matches (relevant info should be the 2nd item in the array)
 * @return string The fixed string
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

/**
 * Strips out invalid html entities, replaces others with html style &#123; codes
 *
 * Callback function used of preg_replace_callback in smcFunc $ent_checks, for example
 * strpos, strlen, substr etc
 *
 * @param array $matches An array of matches (relevant info should be the 3rd item in the array)
 * @return string The fixed string
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
 * @param string $email_address The user's email address
 * @return string The gravatar URL
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
	$http_method = !empty($modSettings['force_ssl']) ? 'https://secure' : 'http://www';

	return $http_method . '.gravatar.com/avatar/' . md5($smcFunc['strtolower']($email_address)) . '?' . implode('&', $url_params);
}

/**
 * Get a list of time zones.
 *
 * @param string $when The date/time for which to calculate the time zone values.
 *		May be a Unix timestamp or any string that strtotime() can understand.
 *		Defaults to 'now'.
 * @return array An array of time zone identifiers and label text.
 */
function smf_list_timezones($when = 'now')
{
	global $modSettings, $tztxt, $txt, $context, $cur_profile, $sourcedir;
	static $timezones_when = array();

	require_once($sourcedir . '/Subs-Timezones.php');

	// Parseable datetime string?
	if (is_int($timestamp = strtotime($when)))
		$when = $timestamp;

	// A Unix timestamp?
	elseif (is_numeric($when))
		$when = intval($when);

	// Invalid value? Just get current Unix timestamp.
	else
		$when = time();

	// No point doing this over if we already did it once
	if (isset($timezones_when[$when]))
		return $timezones_when[$when];

	// We'll need these too
	$date_when = date_create('@' . $when);
	$later = strtotime('@' . $when . ' + 1 year');

	// Load up any custom time zone descriptions we might have
	loadLanguage('Timezones');

	$tzid_metazones = get_tzid_metazones($later);

	// Should we put time zones from certain countries at the top of the list?
	$priority_countries = !empty($modSettings['timezone_priority_countries']) ? explode(',', $modSettings['timezone_priority_countries']) : array();

	$priority_tzids = array();
	foreach ($priority_countries as $country)
	{
		$country_tzids = get_sorted_tzids_for_country($country);

		if (!empty($country_tzids))
			$priority_tzids = array_merge($priority_tzids, $country_tzids);
	}

	// Antarctic research stations should be listed last, unless you're running a penguin forum
	$low_priority_tzids = !in_array('AQ', $priority_countries) ? timezone_identifiers_list(DateTimeZone::ANTARCTICA) : array();

	$normal_priority_tzids = array_diff(array_unique(array_merge(array_keys($tzid_metazones), timezone_identifiers_list())), $priority_tzids, $low_priority_tzids);

	// Process them in order of importance.
	$tzids = array_merge($priority_tzids, $normal_priority_tzids, $low_priority_tzids);

	// Idea here is to get exactly one representative identifier for each and every unique set of time zone rules.
	$dst_types = array();
	$labels = array();
	$offsets = array();
	foreach ($tzids as $tzid)
	{
		// We don't want UTC right now
		if ($tzid == 'UTC')
			continue;

		$tz = @timezone_open($tzid);

		if ($tz == null)
			continue;

		// First, get the set of transition rules for this tzid
		$tzinfo = timezone_transitions_get($tz, $when, $later);

		// Use the entire set of transition rules as the array *key* so we can avoid duplicates
		$tzkey = serialize($tzinfo);

		// ...But make sure to include all explicitly defined meta-zones.
		if (isset($zones[$tzkey]['metazone']) && isset($tzid_metazones[$tzid]))
			$tzkey = serialize(array_merge($tzinfo, array('metazone' => $tzid_metazones[$tzid])));

		// Don't overwrite our preferred tzids
		if (empty($zones[$tzkey]['tzid']))
		{
			$zones[$tzkey]['tzid'] = $tzid;
			$zones[$tzkey]['dst_type'] = count($tzinfo) > 1 ? 1 : ($tzinfo[0]['isdst'] ? 2 : 0);

			foreach ($tzinfo as $transition) {
				$zones[$tzkey]['abbrs'][] = $transition['abbr'];
			}

			if (isset($tzid_metazones[$tzid]))
				$zones[$tzkey]['metazone'] = $tzid_metazones[$tzid];
			else
			{
				$tzgeo = timezone_location_get($tz);
				$country_tzids = get_sorted_tzids_for_country($tzgeo['country_code']);

				if (count($country_tzids) === 1)
					$zones[$tzkey]['metazone'] = $txt['iso3166'][$tzgeo['country_code']];
			}
		}

		// A time zone from a prioritized country?
		if (in_array($tzid, $priority_tzids))
			$priority_zones[$tzkey] = true;

		// Keep track of the location for this tzid.
		if (!empty($txt[$tzid]))
			$zones[$tzkey]['locations'][] = $txt[$tzid];
		else
		{
			$tzid_parts = explode('/', $tzid);
			$zones[$tzkey]['locations'][] = str_replace(array('St_', '_'), array('St. ', ' '), array_pop($tzid_parts));
		}

		// Keep track of the current offset for this tzid.
		$offsets[$tzkey] = $tzinfo[0]['offset'];

		// Keep track of the Standard Time offset for this tzid.
		foreach ($tzinfo as $transition)
		{
			if (!$transition['isdst'])
			{
				$std_offsets[$tzkey] = $transition['offset'];
				break;
			}
		}
		if (!isset($std_offsets[$tzkey]))
			$std_offsets[$tzkey] = $tzinfo[0]['offset'];

		// Figure out the "meta-zone" info for the label
		if (empty($zones[$tzkey]['metazone']) && isset($tzid_metazones[$tzid]))
		{
			$zones[$tzkey]['metazone'] = $tzid_metazones[$tzid];
			$zones[$tzkey]['dst_type'] = count($tzinfo) > 1 ? 1 : ($tzinfo[0]['isdst'] ? 2 : 0);
		}
		$dst_types[$tzkey] = count($tzinfo) > 1 ? 'c' : ($tzinfo[0]['isdst'] ? 't' : 'f');
		$labels[$tzkey] = !empty($zones[$tzkey]['metazone']) && !empty($tztxt[$zones[$tzkey]['metazone']]) ? $tztxt[$zones[$tzkey]['metazone']] : '';

		// Remember this for later
		if (isset($cur_profile['timezone']) && $cur_profile['timezone'] == $tzid)
			$member_tzkey = $tzkey;
		if (isset($context['event']['tz']) && $context['event']['tz'] == $tzid)
			$event_tzkey = $tzkey;
		if ($modSettings['default_timezone'] == $tzid)
			$default_tzkey = $tzkey;
	}

	// Sort by current offset, then standard offset, then DST type, then label.
	array_multisort($offsets, SORT_DESC, SORT_NUMERIC, $std_offsets, SORT_DESC, SORT_NUMERIC, $dst_types, SORT_ASC, $labels, SORT_ASC, $zones);

	// Build the final array of formatted values
	$priority_timezones = array();
	$timezones = array();
	foreach ($zones as $tzkey => $tzvalue)
	{
		date_timezone_set($date_when, timezone_open($tzvalue['tzid']));

		// Use the human friendly time zone name, if there is one.
		$desc = '';
		if (!empty($tzvalue['metazone']))
		{
			if (!empty($tztxt[$tzvalue['metazone']]))
				$metazone = $tztxt[$tzvalue['metazone']];
			else
				$metazone = sprintf($tztxt['generic_timezone'], $tzvalue['metazone'], '%1$s');

			switch ($tzvalue['dst_type'])
			{
				case 0:
					$desc = sprintf($metazone, $tztxt['daylight_saving_time_false']);
					break;

				case 1:
					$desc = sprintf($metazone, '');
					break;

				case 2:
					$desc = sprintf($metazone, $tztxt['daylight_saving_time_true']);
					break;
			}
		}
		// Otherwise, use the list of locations (max 5, so things don't get silly)
		else
			$desc = implode(', ', array_slice(array_unique($tzvalue['locations']), 0, 5)) . (count($tzvalue['locations']) > 5 ? ', ' . $txt['etc'] : '');

		// We don't want abbreviations like '+03' or '-11'.
		$abbrs = array_filter(
			$tzvalue['abbrs'],
			function ($abbr)
			{
				return !strspn($abbr, '+-');
			}
		);
		$abbrs = count($abbrs) == count($tzvalue['abbrs']) ? array_unique($abbrs) : array();

		// Show the UTC offset and abbreviation(s).
		$desc = '[UTC' . date_format($date_when, 'P') . '] - ' . str_replace('  ', ' ', $desc) . (!empty($abbrs) ? ' (' . implode('/', $abbrs) . ')' : '');

		if (isset($priority_zones[$tzkey]))
			$priority_timezones[$tzvalue['tzid']] = $desc;
		else
			$timezones[$tzvalue['tzid']] = $desc;

		// Automatically fix orphaned time zones.
		if (isset($member_tzkey) && $member_tzkey == $tzkey)
			$cur_profile['timezone'] = $tzvalue['tzid'];
		if (isset($event_tzkey) && $event_tzkey == $tzkey)
			$context['event']['tz'] = $tzvalue['tzid'];
		if (isset($default_tzkey) && $default_tzkey == $tzkey && $modSettings['default_timezone'] != $tzvalue['tzid'])
			updateSettings(array('default_timezone' => $tzvalue['tzid']));
	}

	if (!empty($priority_timezones))
		$priority_timezones[] = '-----';

	$timezones = array_merge(
		$priority_timezones,
		array('UTC' => 'UTC' . (!empty($tztxt['UTC']) ? ' - ' . $tztxt['UTC'] : ''), '-----'),
		$timezones
	);

	$timezones_when[$when] = $timezones;

	return $timezones_when[$when];
}

/**
 * Gets a member's selected time zone identifier
 *
 * @param int $id_member The member id to look up. If not provided, the current user's id will be used.
 * @return string The time zone identifier string for the user's time zone.
 */
function getUserTimezone($id_member = null)
{
	global $smcFunc, $user_info, $modSettings, $user_settings;
	static $member_cache = array();

	if (is_null($id_member))
		$id_member = empty($user_info['id']) ? 0 : (int) $user_info['id'];
	else
		$id_member = (int) $id_member;

	// Did we already look this up?
	if (isset($member_cache[$id_member]))
		return $member_cache[$id_member];

	// Check if we already have this in $user_settings.
	if (isset($user_settings['id_member']) && $user_settings['id_member'] == $id_member && !empty($user_settings['timezone']))
	{
		$member_cache[$id_member] = $user_settings['timezone'];
		return $user_settings['timezone'];
	}

	if (!empty($id_member))
	{
		// Look it up in the database.
		$request = $smcFunc['db_query']('', '
			SELECT timezone
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $id_member,
			)
		);
		list($timezone) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// If it is invalid, fall back to the default.
	if (empty($timezone) || !in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)))
		$timezone = isset($modSettings['default_timezone']) ? $modSettings['default_timezone'] : date_default_timezone_get();

	// Save for later.
	$member_cache[$id_member] = $timezone;

	return $timezone;
}

/**
 * Converts an IP address into binary
 *
 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
 * @return string|false The IP address in binary or false
 */
function inet_ptod($ip_address)
{
	if (!isValidIP($ip_address))
		return $ip_address;

	$bin = inet_pton($ip_address);
	return $bin;
}

/**
 * Converts a binary version of an IP address into a readable format
 *
 * @param string $bin An IP address in IPv4, IPv6 (Either string (postgresql) or binary (other databases))
 * @return string|false The IP address in presentation format or false on error
 */
function inet_dtop($bin)
{
	global $db_type;

	if (empty($bin))
		return '';
	elseif ($db_type == 'postgresql')
		return $bin;
	// Already a String?
	elseif (isValidIP($bin))
		return $bin;
	return inet_ntop($bin);
}

/**
 * Safe serialize() and unserialize() replacements
 *
 * @license Public Domain
 *
 * @author anthon (dot) pang (at) gmail (dot) com
 */

/**
 * Safe serialize() replacement. Recursive
 * - output a strict subset of PHP's native serialized representation
 * - does not serialize objects
 *
 * @param mixed $value
 * @return string
 */
function _safe_serialize($value)
{
	if (is_null($value))
		return 'N;';

	if (is_bool($value))
		return 'b:' . (int) $value . ';';

	if (is_int($value))
		return 'i:' . $value . ';';

	if (is_float($value))
		return 'd:' . str_replace(',', '.', $value) . ';';

	if (is_string($value))
		return 's:' . strlen($value) . ':"' . $value . '";';

	if (is_array($value))
	{
		// Check for nested objects or resources.
		$contains_invalid = false;
		array_walk_recursive(
			$value,
			function($v) use (&$contains_invalid)
			{
				if (is_object($v) || is_resource($v))
					$contains_invalid = true;
			}
		);
		if ($contains_invalid)
			return false;

		$out = '';
		foreach ($value as $k => $v)
			$out .= _safe_serialize($k) . _safe_serialize($v);

		return 'a:' . count($value) . ':{' . $out . '}';
	}

	// safe_serialize cannot serialize resources or objects.
	return false;
}

/**
 * Wrapper for _safe_serialize() that handles exceptions and multibyte encoding issues.
 *
 * @param mixed $value
 * @return string
 */
function safe_serialize($value)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Safe unserialize() replacement
 * - accepts a strict subset of PHP's native serialized representation
 * - does not unserialize objects
 *
 * @param string $str
 * @return mixed
 * @throw Exception if $str is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_unserialize($str)
{
	// Input  is not a string.
	if (empty($str) || !is_string($str))
		return false;

	// The substring 'O:' is used to serialize objects.
	// If it is not present, then there are none in the serialized data.
	if (strpos($str, 'O:') === false)
		return unserialize($str);

	$stack = array();
	$expected = array();

	/*
	 * states:
	 *   0 - initial state, expecting a single value or array
	 *   1 - terminal state
	 *   2 - in array, expecting end of array or a key
	 *   3 - in array, expecting value or another array
	 */
	$state = 0;
	while ($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';
		if ($type == '}')
			$str = substr($str, 1);

		elseif ($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		elseif ($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		elseif ($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int) $matches[1];
			$str = $matches[2];
		}
		elseif ($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float) $matches[1];
			$str = $matches[3];
		}
		elseif ($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int) $matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int) $matches[1]);
			$str = substr($matches[2], (int) $matches[1] + 2);
		}
		elseif ($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches))
		{
			$expectedLength = (int) $matches[1];
			$str = $matches[2];
		}

		// Object or unknown/malformed type.
		else
			return false;

		switch ($state)
		{
			case 3: // In array, expecting value or another array.
				if ($type == 'a')
				{
					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if ($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				// Missing array value.
				return false;

			case 2: // in array, expecting end of array or a key
				if ($type == '}')
				{
					// Array size is less than expected.
					if (count($list) < end($expected))
						return false;

					unset($list);
					$list = &$stack[count($stack) - 1];
					array_pop($stack);

					// Go to terminal state if we're at the end of the root array.
					array_pop($expected);

					if (count($expected) == 0)
						$state = 1;

					break;
				}

				if ($type == 'i' || $type == 's')
				{
					// Array size exceeds expected length.
					if (count($list) >= end($expected))
						return false;

					$key = $value;
					$state = 3;
					break;
				}

				// Illegal array index type.
				return false;

			// Expecting array or value.
			case 0:
				if ($type == 'a')
				{
					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}

				if ($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				// Not in array.
				return false;
		}
	}

	// Trailing data in input.
	if (!empty($str))
		return false;

	return $data;
}

/**
 * Wrapper for _safe_unserialize() that handles exceptions and multibyte encoding issue
 *
 * @param string $str
 * @return mixed
 */
function safe_unserialize($str)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 0x02))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_unserialize($str);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Tries different modes to make file/dirs writable. Wrapper function for chmod()
 *
 * @param string $file The file/dir full path.
 * @param int $value Not needed, added for legacy reasons.
 * @return boolean  true if the file/dir is already writable or the function was able to make it writable, false if the function couldn't make the file/dir writable.
 */
function smf_chmod($file, $value = 0)
{
	// No file? no checks!
	if (empty($file))
		return false;

	// Already writable?
	if (is_writable($file))
		return true;

	// Do we have a file or a dir?
	$isDir = is_dir($file);
	$isWritable = false;

	// Set different modes.
	$chmodValues = $isDir ? array(0750, 0755, 0775, 0777) : array(0644, 0664, 0666);

	foreach ($chmodValues as $val)
	{
		// If it's writable, break out of the loop.
		if (is_writable($file))
		{
			$isWritable = true;
			break;
		}

		else
			@chmod($file, $val);
	}

	return $isWritable;
}

/**
 * Wrapper function for json_decode() with error handling.
 *
 * @param string $json The string to decode.
 * @param bool $returnAsArray To return the decoded string as an array or an object, SMF only uses Arrays but to keep on compatibility with json_decode its set to false as default.
 * @param bool $logIt To specify if the error will be logged if theres any.
 * @return array Either an empty array or the decoded data as an array.
 */
function smf_json_decode($json, $returnAsArray = false, $logIt = true)
{
	global $txt;

	// Come on...
	if (empty($json) || !is_string($json))
		return array();

	$returnArray = @json_decode($json, $returnAsArray);

	// PHP 5.3 so no json_last_error_msg()
	switch (json_last_error())
	{
		case JSON_ERROR_NONE:
			$jsonError = false;
			break;
		case JSON_ERROR_DEPTH:
			$jsonError = 'JSON_ERROR_DEPTH';
			break;
		case JSON_ERROR_STATE_MISMATCH:
			$jsonError = 'JSON_ERROR_STATE_MISMATCH';
			break;
		case JSON_ERROR_CTRL_CHAR:
			$jsonError = 'JSON_ERROR_CTRL_CHAR';
			break;
		case JSON_ERROR_SYNTAX:
			$jsonError = 'JSON_ERROR_SYNTAX';
			break;
		case JSON_ERROR_UTF8:
			$jsonError = 'JSON_ERROR_UTF8';
			break;
		default:
			$jsonError = 'unknown';
			break;
	}

	// Something went wrong!
	if (!empty($jsonError) && $logIt)
	{
		// Being a wrapper means we lost our smf_error_handler() privileges :(
		$jsonDebug = debug_backtrace();
		$jsonDebug = $jsonDebug[0];
		loadLanguage('Errors');

		if (!empty($jsonDebug))
			log_error($txt['json_' . $jsonError], 'critical', $jsonDebug['file'], $jsonDebug['line']);

		else
			log_error($txt['json_' . $jsonError], 'critical');

		// Everyone expects an array.
		return array();
	}

	return $returnArray;
}

/**
 * Check the given String if he is a valid IPv4 or IPv6
 * return true or false
 *
 * @param string $IPString
 *
 * @return bool
 */
function isValidIP($IPString)
{
	return filter_var($IPString, FILTER_VALIDATE_IP) !== false;
}

/**
 * Outputs a response.
 * It assumes the data is already a string.
 *
 * @param string $data The data to print
 * @param string $type The content type. Defaults to Json.
 * @return void
 */
function smf_serverResponse($data = '', $type = 'content-type: application/json')
{
	global $db_show_debug, $modSettings;

	// Defensive programming anyone?
	if (empty($data))
		return false;

	// Don't need extra stuff...
	$db_show_debug = false;

	// Kill anything else.
	ob_end_clean();

	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Set the header.
	header($type);

	// Echo!
	echo $data;

	// Done.
	obExit(false);
}

/**
 * Creates an optimized regex to match all known top level domains.
 *
 * The optimized regex is stored in $modSettings['tld_regex'].
 *
 * To update the stored version of the regex to use the latest list of valid
 * TLDs from iana.org, set the $update parameter to true. Updating can take some
 * time, based on network connectivity, so it should normally only be done by
 * calling this function from a background or scheduled task.
 *
 * If $update is not true, but the regex is missing or invalid, the regex will
 * be regenerated from a hard-coded list of TLDs. This regenerated regex will be
 * overwritten on the next scheduled update.
 *
 * @param bool $update If true, fetch and process the latest official list of TLDs from iana.org.
 */
function set_tld_regex($update = false)
{
	global $sourcedir, $smcFunc, $modSettings;
	static $done = false;

	// If we don't need to do anything, don't
	if (!$update && $done)
		return;

	// Should we get a new copy of the official list of TLDs?
	if ($update)
	{
		$tlds = fetch_web_data('https://data.iana.org/TLD/tlds-alpha-by-domain.txt');
		$tlds_md5 = fetch_web_data('https://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5');

		/**
		 * If the Internet Assigned Numbers Authority can't be reached, the Internet is GONE!
		 * We're probably running on a server hidden in a bunker deep underground to protect
		 * it from marauding bandits roaming on the surface. We don't want to waste precious
		 * electricity on pointlessly repeating background tasks, so we'll wait until the next
		 * regularly scheduled update to see if civilization has been restored.
		 */
		if ($tlds === false || $tlds_md5 === false)
			$postapocalypticNightmare = true;

		// Make sure nothing went horribly wrong along the way.
		if (md5($tlds) != substr($tlds_md5, 0, 32))
			$tlds = array();
	}
	// If we aren't updating and the regex is valid, we're done
	elseif (!empty($modSettings['tld_regex']) && @preg_match('~' . $modSettings['tld_regex'] . '~', '') !== false)
	{
		$done = true;
		return;
	}

	// If we successfully got an update, process the list into an array
	if (!empty($tlds))
	{
		// Clean $tlds and convert it to an array
		$tlds = array_filter(
			explode("\n", strtolower($tlds)),
			function($line)
			{
				$line = trim($line);
				if (empty($line) || strlen($line) != strspn($line, 'abcdefghijklmnopqrstuvwxyz0123456789-'))
					return false;
				else
					return true;
			}
		);

		// Convert Punycode to Unicode
		if (!function_exists('idn_to_utf8'))
			require_once($sourcedir . '/Subs-Compat.php');

		foreach ($tlds as &$tld)
			$tld = idn_to_utf8($tld, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
	}
	// Otherwise, use the 2012 list of gTLDs and ccTLDs for now and schedule a background update
	else
	{
		$tlds = array('com', 'net', 'org', 'edu', 'gov', 'mil', 'aero', 'asia', 'biz',
			'cat', 'coop', 'info', 'int', 'jobs', 'mobi', 'museum', 'name', 'post',
			'pro', 'tel', 'travel', 'xxx', 'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al',
			'am', 'ao', 'aq', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd',
			'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv',
			'bw', 'by', 'bz', 'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm',
			'cn', 'co', 'cr', 'cu', 'cv', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do',
			'dz', 'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo',
			'fr', 'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp',
			'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu',
			'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm', 'jo',
			'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz', 'la',
			'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'md',
			'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt',
			'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl',
			'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl',
			'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw',
			'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn',
			'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg',
			'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua',
			'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf',
			'ws', 'ye', 'yt', 'za', 'zm', 'zw',
		);

		// Schedule a background update, unless civilization has collapsed and/or we are having connectivity issues.
		if (empty($postapocalypticNightmare))
		{
			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/UpdateTldRegex.php', 'Update_TLD_Regex', '', 0), array()
			);
		}
	}

	// Tack on some "special use domain names" that aren't in DNS but may possibly resolve.
	// See https://www.iana.org/assignments/special-use-domain-names/ for more info.
	$tlds = array_merge($tlds, array('local', 'onion', 'test'));

	// Get an optimized regex to match all the TLDs
	$tld_regex = build_regex($tlds);

	// Remember the new regex in $modSettings
	updateSettings(array('tld_regex' => $tld_regex));

	// Redundant repetition is redundant
	$done = true;
}

/**
 * Creates optimized regular expressions from an array of strings.
 *
 * An optimized regex built using this function will be much faster than a
 * simple regex built using `implode('|', $strings)` --- anywhere from several
 * times to several orders of magnitude faster.
 *
 * However, the time required to build the optimized regex is approximately
 * equal to the time it takes to execute the simple regex. Therefore, it is only
 * worth calling this function if the resulting regex will be used more than
 * once.
 *
 * Because PHP places an upper limit on the allowed length of a regex, very
 * large arrays of $strings may not fit in a single regex. Normally, the excess
 * strings will simply be dropped. However, if the $returnArray parameter is set
 * to true, this function will build as many regexes as necessary to accommodate
 * everything in $strings and return them in an array. You will need to iterate
 * through all elements of the returned array in order to test all possible
 * matches.
 *
 * @param array $strings An array of strings to make a regex for.
 * @param string $delim An optional delimiter character to pass to preg_quote().
 * @param bool $returnArray If true, returns an array of regexes.
 * @return string|array One or more regular expressions to match any of the input strings.
 */
function build_regex($strings, $delim = null, $returnArray = false)
{
	global $smcFunc;
	static $regexes = array();

	// If it's not an array, there's not much to do. ;)
	if (!is_array($strings))
		return preg_quote(@strval($strings), $delim);

	$regex_key = md5(json_encode(array($strings, $delim, $returnArray)));

	if (isset($regexes[$regex_key]))
		return $regexes[$regex_key];

	// The mb_* functions are faster than the $smcFunc ones, but may not be available
	if (function_exists('mb_internal_encoding') && function_exists('mb_detect_encoding') && function_exists('mb_strlen') && function_exists('mb_substr'))
	{
		if (($string_encoding = mb_detect_encoding(implode(' ', $strings))) !== false)
		{
			$current_encoding = mb_internal_encoding();
			mb_internal_encoding($string_encoding);
		}

		$strlen = 'mb_strlen';
		$substr = 'mb_substr';
	}
	else
	{
		$strlen = $smcFunc['strlen'];
		$substr = $smcFunc['substr'];
	}

	// This recursive function creates the index array from the strings
	$add_string_to_index = function($string, $index) use (&$strlen, &$substr, &$add_string_to_index)
	{
		static $depth = 0;
		$depth++;

		$first = (string) @$substr($string, 0, 1);

		// No first character? That's no good.
		if ($first === '')
		{
			// A nested array? Really? Ugh. Fine.
			if (is_array($string) && $depth < 20)
			{
				foreach ($string as $str)
					$index = $add_string_to_index($str, $index);
			}

			$depth--;
			return $index;
		}

		if (empty($index[$first]))
			$index[$first] = array();

		if ($strlen($string) > 1)
		{
			// Sanity check on recursion
			if ($depth > 99)
				$index[$first][$substr($string, 1)] = '';

			else
				$index[$first] = $add_string_to_index($substr($string, 1), $index[$first]);
		}
		else
			$index[$first][''] = '';

		$depth--;
		return $index;
	};

	// This recursive function turns the index array into a regular expression
	$index_to_regex = function(&$index, $delim) use (&$strlen, &$index_to_regex)
	{
		static $depth = 0;
		$depth++;

		// Absolute max length for a regex is 32768, but we might need wiggle room
		$max_length = 30000;

		$regex = array();
		$length = 0;

		foreach ($index as $key => $value)
		{
			$key_regex = preg_quote($key, $delim);
			$new_key = $key;

			if (empty($value))
				$sub_regex = '';
			else
			{
				$sub_regex = $index_to_regex($value, $delim);

				if (count(array_keys($value)) == 1)
				{
					$new_key_array = explode('(?' . '>', $sub_regex);
					$new_key .= $new_key_array[0];
				}
				else
					$sub_regex = '(?' . '>' . $sub_regex . ')';
			}

			if ($depth > 1)
				$regex[$new_key] = $key_regex . $sub_regex;
			else
			{
				if (($length += strlen($key_regex . $sub_regex) + 1) < $max_length || empty($regex))
				{
					$regex[$new_key] = $key_regex . $sub_regex;
					unset($index[$key]);
				}
				else
					break;
			}
		}

		// Sort by key length and then alphabetically
		uksort(
			$regex,
			function($k1, $k2) use (&$strlen)
			{
				$l1 = $strlen($k1);
				$l2 = $strlen($k2);

				if ($l1 == $l2)
					return strcmp($k1, $k2) > 0 ? 1 : -1;
				else
					return $l1 > $l2 ? -1 : 1;
			}
		);

		$depth--;
		return implode('|', $regex);
	};

	// Now that the functions are defined, let's do this thing
	$index = array();
	$regex = '';

	foreach ($strings as $string)
		$index = $add_string_to_index($string, $index);

	if ($returnArray === true)
	{
		$regex = array();
		while (!empty($index))
			$regex[] = '(?' . '>' . $index_to_regex($index, $delim) . ')';
	}
	else
		$regex = '(?' . '>' . $index_to_regex($index, $delim) . ')';

	// Restore PHP's internal character encoding to whatever it was originally
	if (!empty($current_encoding))
		mb_internal_encoding($current_encoding);

	$regexes[$regex_key] = $regex;
	return $regex;
}

/**
 * Check if the passed url has an SSL certificate.
 *
 * Returns true if a cert was found & false if not.
 *
 * @param string $url to check, in $boardurl format (no trailing slash).
 */
function ssl_cert_found($url)
{
	// This check won't work without OpenSSL
	if (!extension_loaded('openssl'))
		return true;

	// First, strip the subfolder from the passed url, if any
	$parsedurl = parse_iri($url);
	$url = 'ssl://' . $parsedurl['host'] . ':443';

	// Next, check the ssl stream context for certificate info
	if (version_compare(PHP_VERSION, '5.6.0', '<'))
		$ssloptions = array("capture_peer_cert" => true);
	else
		$ssloptions = array("capture_peer_cert" => true, "verify_peer" => true, "allow_self_signed" => true);

	$result = false;
	$context = stream_context_create(array("ssl" => $ssloptions));
	$stream = @stream_socket_client($url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
	if ($stream !== false)
	{
		$params = stream_context_get_params($stream);
		$result = isset($params["options"]["ssl"]["peer_certificate"]) ? true : false;
	}
	return $result;
}

/**
 * Check if the passed url has a redirect to https:// by querying headers.
 *
 * Returns true if a redirect was found & false if not.
 * Note that when force_ssl = 2, SMF issues its own redirect...  So if this
 * returns true, it may be caused by SMF, not necessarily an .htaccess redirect.
 *
 * @param string $url to check, in $boardurl format (no trailing slash).
 */
function https_redirect_active($url)
{
	// Ask for the headers for the passed url, but via http...
	// Need to add the trailing slash, or it puts it there & thinks there's a redirect when there isn't...
	$url = str_ireplace('https://', 'http://', $url) . '/';
	$headers = @get_headers($url);
	if ($headers === false)
		return false;

	// Now to see if it came back https...
	// First check for a redirect status code in first row (301, 302, 307)
	if (strstr($headers[0], '301') === false && strstr($headers[0], '302') === false && strstr($headers[0], '307') === false)
		return false;

	// Search for the location entry to confirm https
	$result = false;
	foreach ($headers as $header)
	{
		if (stristr($header, 'Location: https://') !== false)
		{
			$result = true;
			break;
		}
	}
	return $result;
}

/**
 * Build query_wanna_see_board and query_see_board for a userid
 *
 * Returns array with keys query_wanna_see_board and query_see_board
 *
 * @param int $userid of the user
 */
function build_query_board($userid)
{
	global $user_info, $modSettings, $smcFunc, $db_prefix;

	$query_part = array();

	// If we come from cron, we can't have a $user_info.
	if (isset($user_info['id']) && $user_info['id'] == $userid && SMF != 'BACKGROUND')
	{
		$groups = $user_info['groups'];
		$can_see_all_boards = $user_info['is_admin'] || $user_info['can_manage_boards'];
		$ignoreboards = !empty($user_info['ignoreboards']) ? $user_info['ignoreboards'] : null;
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT mem.ignore_boards, mem.id_group, mem.additional_groups, mem.id_post_group
			FROM {db_prefix}members AS mem
			WHERE mem.id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $userid,
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);

		if (empty($row['additional_groups']))
			$groups = array($row['id_group'], $row['id_post_group']);
		else
			$groups = array_merge(
				array($row['id_group'], $row['id_post_group']),
				explode(',', $row['additional_groups'])
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		foreach ($groups as $k => $v)
			$groups[$k] = (int) $v;

		$can_see_all_boards = in_array(1, $groups) || (!empty($modSettings['board_manager_groups']) && count(array_intersect($groups, explode(',', $modSettings['board_manager_groups']))) > 0);

		$ignoreboards = !empty($row['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $row['ignore_boards']) : array();
	}

	// Just build this here, it makes it easier to change/use - administrators can see all boards.
	if ($can_see_all_boards)
		$query_part['query_see_board'] = '1=1';
	// Otherwise just the groups in $user_info['groups'].
	else
	{
		$query_part['query_see_board'] = '
			EXISTS (
				SELECT bpv.id_board
				FROM ' . $db_prefix . 'board_permissions_view AS bpv
				WHERE bpv.id_group IN ('. implode(',', $groups) .')
					AND bpv.deny = 0
					AND bpv.id_board = b.id_board
			)';

		if (!empty($modSettings['deny_boards_access']))
			$query_part['query_see_board'] .= '
			AND NOT EXISTS (
				SELECT bpv.id_board
				FROM ' . $db_prefix . 'board_permissions_view AS bpv
				WHERE bpv.id_group IN ( '. implode(',', $groups) .')
					AND bpv.deny = 1
					AND bpv.id_board = b.id_board
			)';
	}

	$query_part['query_see_message_board'] = str_replace('b.', 'm.', $query_part['query_see_board']);
	$query_part['query_see_topic_board'] = str_replace('b.', 't.', $query_part['query_see_board']);

	// Build the list of boards they WANT to see.
	// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

	// If they aren't ignoring any boards then they want to see all the boards they can see
	if (empty($ignoreboards))
	{
		$query_part['query_wanna_see_board'] = $query_part['query_see_board'];
		$query_part['query_wanna_see_message_board'] = $query_part['query_see_message_board'];
		$query_part['query_wanna_see_topic_board'] = $query_part['query_see_topic_board'];
	}
	// Ok I guess they don't want to see all the boards
	else
	{
		$query_part['query_wanna_see_board'] = '(' . $query_part['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
		$query_part['query_wanna_see_message_board'] = '(' . $query_part['query_see_message_board'] . ' AND m.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
		$query_part['query_wanna_see_topic_board'] = '(' . $query_part['query_see_topic_board'] . ' AND t.id_board NOT IN (' . implode(',', $ignoreboards) . '))';
	}

	return $query_part;
}

/**
 * Check if the connection is using https.
 *
 * @return boolean true if connection used https
 */
function httpsOn()
{
	$secure = false;

	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
		$secure = true;
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
		$secure = true;

	return $secure;
}

/**
 * A wrapper for `parse_url($url)` that can handle URLs with international
 * characters (a.k.a. IRIs)
 *
 * @param string $iri The IRI to parse.
 * @param int $component Optional parameter to pass to parse_url().
 * @return mixed Same as parse_url(), but with unmangled Unicode.
 */
function parse_iri($iri, $component = -1)
{
	$iri = preg_replace_callback(
		'~[^\x00-\x7F\pZ\pC]|%~u',
		function($matches)
		{
			return rawurlencode($matches[0]);
		},
		$iri
	);

	$parsed = parse_url($iri, $component);

	if (is_array($parsed))
	{
		foreach ($parsed as &$part)
			$part = rawurldecode($part);
	}
	elseif (is_string($parsed))
		$parsed = rawurldecode($parsed);

	return $parsed;
}

/**
 * A wrapper for `filter_var($url, FILTER_VALIDATE_URL)` that can handle URLs
 * with international characters (a.k.a. IRIs)
 *
 * @param string $iri The IRI to test.
 * @param int $flags Optional flags to pass to filter_var()
 * @return string|bool Either the original IRI, or false if the IRI was invalid.
 */
function validate_iri($iri, $flags = 0)
{
	$url = iri_to_url($iri);

	// PHP 5 doesn't recognize IPv6 addresses in the URL host.
	if (version_compare(phpversion(), '7.0.0', '<'))
	{
		$host = parse_url((strpos($url, '//') === 0 ? 'http:' : '') . $url, PHP_URL_HOST);

		if (strpos($host, '[') === 0 && strpos($host, ']') === strlen($host) - 1 && strpos($host, ':') !== false)
			$url = str_replace($host, '127.0.0.1', $url);
	}

	if (filter_var($url, FILTER_VALIDATE_URL, $flags) !== false)
		return $iri;
	else
		return false;
}

/**
 * A wrapper for `filter_var($url, FILTER_SANITIZE_URL)` that can handle URLs
 * with international characters (a.k.a. IRIs)
 *
 * Note: The returned value will still be an IRI, not a URL. To convert to URL,
 * feed the result of this function to iri_to_url()
 *
 * @param string $iri The IRI to sanitize.
 * @return string|bool The sanitized version of the IRI
 */
function sanitize_iri($iri)
{
	// Encode any non-ASCII characters (but not space or control characters of any sort)
	// Also encode '%' in order to preserve anything that is already percent-encoded.
	$iri = preg_replace_callback(
		'~[^\x00-\x7F\pZ\pC]|%~u',
		function($matches)
		{
			return rawurlencode($matches[0]);
		},
		$iri
	);

	// Perform normal sanitization
	$iri = filter_var($iri, FILTER_SANITIZE_URL);

	// Decode the non-ASCII characters
	$iri = rawurldecode($iri);

	return $iri;
}

/**
 * Performs Unicode normalization on IRIs.
 *
 * Internally calls sanitize_iri(), then performs Unicode normalization on the
 * IRI as a whole, using NFKC normalization for the domain name (see RFC 3491)
 * and NFC normalization for the rest.
 *
 * @param string $iri The IRI to normalize.
 * @return string|bool The normalized version of the IRI.
 */
function normalize_iri($iri)
{
	global $sourcedir, $context, $txt, $db_character_set;

	// If we are not using UTF-8, just sanitize and return.
	if (isset($context['utf8']) ? !$context['utf8'] : (isset($txt['lang_character_set']) ? $txt['lang_character_set'] != 'UTF-8' : (isset($db_character_set) && $db_character_set != 'utf8')))
		return sanitize_iri($iri);

	require_once($sourcedir . '/Subs-Charset.php');

	$iri = sanitize_iri(utf8_normalize_c($iri));

	$host = parse_iri((strpos($iri, '//') === 0 ? 'http:' : '') . $iri, PHP_URL_HOST);

	if (!empty($host))
	{
		$normalized_host = utf8_normalize_kc_casefold($host);
		$pos = strpos($iri, $host);
	}
	else
	{
		$host = '';
		$normalized_host = '';
		$pos = 0;
	}

	$before_host = substr($iri, 0, $pos);
	$after_host = substr($iri, $pos + strlen($host));

	return $before_host . $normalized_host . $after_host;
}

/**
 * Converts a URL with international characters (an IRI) into a pure ASCII URL
 *
 * Uses Punycode to encode any non-ASCII characters in the domain name, and uses
 * standard URL encoding on the rest.
 *
 * @param string $iri A IRI that may or may not contain non-ASCII characters.
 * @return string|bool The URL version of the IRI.
 */
function iri_to_url($iri)
{
	global $sourcedir, $context, $txt, $db_character_set;

	// Sanity check: must be using UTF-8 to do this.
	if (isset($context['utf8']) ? !$context['utf8'] : (isset($txt['lang_character_set']) ? $txt['lang_character_set'] != 'UTF-8' : (isset($db_character_set) && $db_character_set != 'utf8')))
		return $iri;

	require_once($sourcedir . '/Subs-Charset.php');

	$iri = sanitize_iri(utf8_normalize_c($iri));

	$host = parse_iri((strpos($iri, '//') === 0 ? 'http:' : '') . $iri, PHP_URL_HOST);

	if (!empty($host))
	{
		if (!function_exists('idn_to_ascii'))
			require_once($sourcedir . '/Subs-Compat.php');

		// Convert the host using the Punycode algorithm
		$encoded_host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

		$pos = strpos($iri, $host);
	}
	else
	{
		$host = '';
		$encoded_host = '';
		$pos = 0;
	}

	$before_host = substr($iri, 0, $pos);
	$after_host = substr($iri, $pos + strlen($host));

	// Encode any disallowed characters in the rest of the URL
	$unescaped = array(
		'%21' => '!', '%23' => '#', '%24' => '$', '%26' => '&',
		'%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*',
		'%2B' => '+', '%2C' => ',', '%2F' => '/', '%3A' => ':',
		'%3B' => ';', '%3D' => '=', '%3F' => '?', '%40' => '@',
		'%25' => '%',
	);

	$before_host = strtr(rawurlencode($before_host), $unescaped);
	$after_host = strtr(rawurlencode($after_host), $unescaped);

	return $before_host . $encoded_host . $after_host;
}

/**
 * Decodes a URL containing encoded international characters to UTF-8
 *
 * Decodes any Punycode encoded characters in the domain name, then uses
 * standard URL decoding on the rest.
 *
 * @param string $url The pure ASCII version of a URL.
 * @return string|bool The UTF-8 version of the URL.
 */
function url_to_iri($url)
{
	global $sourcedir, $context, $txt, $db_character_set;

	// Sanity check: must be using UTF-8 to do this.
	if (isset($context['utf8']) ? !$context['utf8'] : (isset($txt['lang_character_set']) ? $txt['lang_character_set'] != 'UTF-8' : (isset($db_character_set) && $db_character_set != 'utf8')))
		return $url;

	$host = parse_iri((strpos($url, '//') === 0 ? 'http:' : '') . $url, PHP_URL_HOST);

	if (!empty($host))
	{
		if (!function_exists('idn_to_utf8'))
			require_once($sourcedir . '/Subs-Compat.php');

		// Decode the domain from Punycode
		$decoded_host = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

		$pos = strpos($url, $host);
	}
	else
	{
		$decoded_host = '';
		$pos = 0;
	}

	$before_host = substr($url, 0, $pos);
	$after_host = substr($url, $pos + strlen($host));

	// Decode the rest of the URL, but preserve escaped URL syntax characters.
	$double_escaped = array(
		'%21' => '%2521', '%23' => '%2523', '%24' => '%2524', '%26' => '%2526',
		'%27' => '%2527', '%28' => '%2528', '%29' => '%2529', '%2A' => '%252A',
		'%2B' => '%252B', '%2C' => '%252C', '%2F' => '%252F', '%3A' => '%253A',
		'%3B' => '%253B', '%3D' => '%253D', '%3F' => '%253F', '%40' => '%2540',
		'%25' => '%2525',
	);

	$before_host = rawurldecode(strtr($before_host, $double_escaped));
	$after_host = rawurldecode(strtr($after_host, $double_escaped));

	return $before_host . $decoded_host . $after_host;
}

/**
 * Ensures SMF's scheduled tasks are being run as intended
 *
 * If the admin activated the cron_is_real_cron setting, but the cron job is
 * not running things at least once per day, we need to go back to SMF's default
 * behaviour using "web cron" JavaScript calls.
 */
function check_cron()
{
	global $modSettings, $smcFunc, $txt;

	if (!empty($modSettings['cron_is_real_cron']) && time() - @intval($modSettings['cron_last_checked']) > 84600)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				AND next_time < {int:yesterday}',
			array(
				'not_disabled' => 0,
				'yesterday' => time() - 84600,
			)
		);
		list($overdue) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// If we have tasks more than a day overdue, cron isn't doing its job.
		if (!empty($overdue))
		{
			loadLanguage('ManageScheduledTasks');
			log_error($txt['cron_not_working']);
			updateSettings(array('cron_is_real_cron' => 0));
		}
		else
			updateSettings(array('cron_last_checked' => time()));
	}
}

/**
 * Sends an appropriate HTTP status header based on a given status code
 *
 * @param int $code The status code
 * @param string $status The string for the status. Set automatically if not provided.
 */
function send_http_status($code, $status = '')
{
	global $sourcedir;

	// This will fail anyways if headers have been sent.
	if (headers_sent())
		return;

	$statuses = array(
		204 => 'No Content',
		206 => 'Partial Content',
		304 => 'Not Modified',
		400 => 'Bad Request',
		403 => 'Forbidden',
		404 => 'Not Found',
		410 => 'Gone',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable',
	);

	$protocol = !empty($_SERVER['SERVER_PROTOCOL']) && preg_match('~^\s*(HTTP/[12]\.\d)\s*$~i', $_SERVER['SERVER_PROTOCOL'], $matches) ? $matches[1] : 'HTTP/1.0';

	// Typically during these requests, we have cleaned the response (ob_*clean), ensure these headers exist.
	require_once($sourcedir . '/Security.php');
	frameOptionsHeader();
	corsPolicyHeader();

	if (!isset($statuses[$code]) && empty($status))
		header($protocol . ' 500 Internal Server Error');
	else
		header($protocol . ' ' . $code . ' ' . (!empty($status) ? $status : $statuses[$code]));
}

/**
 * Concatenates an array of strings into a grammatically correct sentence list
 *
 * Uses formats defined in the language files to build the list appropriately
 * for the currently loaded language.
 *
 * @param array $list An array of strings to concatenate.
 * @return string The localized sentence list.
 */
function sentence_list($list)
{
	global $txt;

	// Make sure the bare necessities are defined
	if (empty($txt['sentence_list_format']['n']))
		$txt['sentence_list_format']['n'] = '{series}';
	if (!isset($txt['sentence_list_separator']))
		$txt['sentence_list_separator'] = ', ';
	if (!isset($txt['sentence_list_separator_alt']))
		$txt['sentence_list_separator_alt'] = '; ';

	// Which format should we use?
	if (isset($txt['sentence_list_format'][count($list)]))
		$format = $txt['sentence_list_format'][count($list)];
	else
		$format = $txt['sentence_list_format']['n'];

	// Do we want the normal separator or the alternate?
	$separator = $txt['sentence_list_separator'];
	foreach ($list as $item)
	{
		if (strpos($item, $separator) !== false)
		{
			$separator = $txt['sentence_list_separator_alt'];
			$format = strtr($format, trim($txt['sentence_list_separator']), trim($separator));
			break;
		}
	}

	$replacements = array();

	// Special handling for the last items on the list
	$i = 0;
	while (empty($done))
	{
		if (strpos($format, '{'. --$i . '}') !== false)
			$replacements['{'. $i . '}'] = array_pop($list);
		else
			$done = true;
	}
	unset($done);

	// Special handling for the first items on the list
	$i = 0;
	while (empty($done))
	{
		if (strpos($format, '{'. ++$i . '}') !== false)
			$replacements['{'. $i . '}'] = array_shift($list);
		else
			$done = true;
	}
	unset($done);

	// Whatever is left
	$replacements['{series}'] = implode($separator, $list);

	// Do the deed
	return strtr($format, $replacements);
}

/**
 * Truncate an array to a specified length
 *
 * @param array $array The array to truncate
 * @param int $max_length The upperbound on the length
 * @param int $deep How levels in an multidimensional array should the function take into account.
 * @return array The truncated array
 */
function truncate_array($array, $max_length = 1900, $deep = 3)
{
	$array = (array) $array;

	$curr_length = array_length($array, $deep);

	if ($curr_length <= $max_length)
		return $array;

	else
	{
		// Truncate each element's value to a reasonable length
		$param_max = floor($max_length / count($array));

		$current_deep = $deep - 1;

		foreach ($array as $key => &$value)
		{
			if (is_array($value))
				if ($current_deep > 0)
					$value = truncate_array($value, $current_deep);

			else
				$value = substr($value, 0, $param_max - strlen($key) - 5);
		}

		return $array;
	}
}

/**
 * array_length Recursive
 * @param array $array
 * @param int $deep How many levels should the function
 * @return int
 */
function array_length($array, $deep = 3)
{
	// Work with arrays
	$array = (array) $array;
	$length = 0;

	$deep_count = $deep - 1;

	foreach ($array as $value)
	{
		// Recursive?
		if (is_array($value))
		{
			// No can't do
			if ($deep_count <= 0)
				continue;

			$length += array_length($value, $deep_count);
		}
		else
			$length += strlen($value);
	}

	return $length;
}

/**
 * Compares existance request variables against an array.
 *
 * The input array is associative, where keys denote accepted values
 * in a request variable denoted by `$req_val`. Values can be:
 *
 * - another associative array where at least one key must be found
 *   in the request and their values are accepted request values.
 * - A scalar value, in which case no furthur checks are done.
 *
 * @param array $array
 * @param string $req_var request variable
 *
 * @return bool whether any of the criteria was satisfied
 */
function is_filtered_request(array $array, $req_var)
{
	$matched = false;
	if (isset($_REQUEST[$req_var], $array[$_REQUEST[$req_var]]))
	{
		if (is_array($array[$_REQUEST[$req_var]]))
		{
			foreach ($array[$_REQUEST[$req_var]] as $subtype => $subnames)
				$matched |= isset($_REQUEST[$subtype]) && in_array($_REQUEST[$subtype], $subnames);
		}
		else
			$matched = true;
	}

	return (bool) $matched;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * See https://www.w3.org/TR/xml/#charsets
 *
 * @param string $string The string to clean
 * @return string The cleaned string
 */
function cleanXml($string)
{
	global $context;

	$illegal_chars = array(
		// Remove all ASCII control characters except \t, \n, and \r.
		"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08",
		"\x0B", "\x0C", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14",
		"\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
		"\x1E", "\x1F",
		// Remove \xFFFE and \xFFFF
		"\xEF\xBF\xBE", "\xEF\xBF\xBF",
	);

	$string = str_replace($illegal_chars, '', $string);

	// The Unicode surrogate pair code points should never be present in our
	// strings to begin with, but if any snuck in, they need to be removed.
	if (!empty($context['utf8']) && strpos($string, "\xED") !== false)
		$string = preg_replace('/\xED[\xA0-\xBF][\x80-\xBF]/', '', $string);

	return $string;
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
 *
 * @param string $string The string to escape
 * @param bool $as_json If true, escape as double-quoted string. Default false.
 * @return string The escaped string
 */
function JavaScriptEscape($string, $as_json = false)
{
	global $scripturl;

	$q = !empty($as_json) ? '"' : '\'';

	return $q . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		$q => addslashes($q),
		'</' => '<' . $q . ' + ' . $q . '/',
		'<script' => '<scri' . $q . '+' . $q . 'pt',
		'<body>' => '<bo' . $q . '+' . $q . 'dy>',
		'<a href' => '<a hr' . $q . '+' . $q . 'ef',
		$scripturl => $q . ' + smf_scripturl + ' . $q,
	)) . $q;
}

function tokenTxtReplace($stringSubject = '')
{
	global $txt;

	if (empty($stringSubject))
		return '';

	$translatable_tokens = preg_match_all('/{(.*?)}/' , $stringSubject, $matches);
	$toFind = array();
	$replaceWith = array();

	if (!empty($matches[1]))
		foreach ($matches[1] as $token) {
			$toFind[] = '{' . $token . '}';
			$replaceWith[] = isset($txt[$token]) ? $txt[$token] : $token;
		}

	return str_replace($toFind, $replaceWith, $stringSubject);
}

?>