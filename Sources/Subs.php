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

/*	This file has all the main functions in it that relate to, well,
	everything.  It provides all of the following functions:

	void updateStats(string statistic, string condition = '1')
		- statistic can be 'member', 'message', 'topic', or 'postgroups'.
		- parameter1 and parameter2 are optional, and are used to update only
		  those stats that need updating.
		- the 'member' statistic updates the latest member, the total member
		  count, and the number of unapproved members.
		- 'member' also only counts approved members when approval is on, but
		  is much more efficient with it off.
		- updating 'message' changes the total number of messages, and the
		  highest message id by id_msg - which can be parameters 1 and 2,
		  respectively.
		- 'topic' updates the total number of topics, or if parameter1 is true
		  simply increments them.
		- the 'postgroups' case updates those members who match condition's
		  post-based membergroups in the database (restricted by parameter1).

	void updateMemberData(int id_member, array data)
		- updates the columns in the members table.
		- id_member is either an int or an array of ints to be updated.
		- data is an associative array of the columns to be updated and their
		  respective values.
		- any string values updated should be quoted and slashed.
		- the value of any column can be '+' or '-', which mean 'increment'
		  and decrement, respectively.
		- if the member's post number is updated, updates their post groups.
		- this function should be used whenever member data needs to be
		  updated in place of an UPDATE query.

	void updateSettings(array changeArray, use_update = false)
		- updates both the settings table and $modSettings array.
		- all of changeArray's indexes and values are assumed to have escaped
		  apostrophes (')!
		- if a variable is already set to what you want to change it to, that
		  variable will be skipped over; it would be unnecessary to reset.
		- if use_update is true, UPDATEs will be used instead of REPLACE.
		- when use_update is true, the value can be true or false to increment
		  or decrement it, respectively.

	string constructPageIndex(string base_url, int &start, int max_value,
			int num_per_page, bool compact_start = false)
		- builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
		- compact_start caused it to use "url.page" instead of
		  "url;start=page".
		- handles any wireless settings (adding special things to URLs.)
		- very importantly, cleans up the start value passed, and forces it to
		  be a multiple of num_per_page.
		- also checks that start is not more than max_value.
		- base_url should be the URL without any start parameter on it.
		- uses the compactTopicPagesEnable and compactTopicPagesContiguous
		  settings to decide how to display the menu.
		- an example is available near the function definition.

	string comma_format(float number)
		- formats a number to display in the style of the admins' choosing.
		- uses the format of number_format to decide how to format the number.
		- for example, it might display "1 234,50".
		- caches the formatting data from the setting for optimization.

	string timeformat(int time, bool show_today = true, string offset_type = false)
		- returns a pretty formated version of time based on the user's format
		  in $user_info['time_format'].
		- applies all necessary time offsets to the timestamp, unless offset_type
		  is set.
		- if todayMod is set and show_today was not not specified or true, an
		  alternate format string is used to show the date with something to
		  show it is "today" or "yesterday".
		- performs localization (more than just strftime would do alone.)

	string un_htmlspecialchars(string text)
		- removes the base entities (&lt;, &quot;, etc.) from text.
		- should be used instead of html_entity_decode for PHP version
		  compatibility reasons.
		- additionally converts &nbsp; and &#039;.
		- returns the string without entities.

	string shorten_subject(string regular_subject, int length)
		- shortens a subject so that it is either shorter than length, or that
		  length plus an ellipsis.
		- respects internationalization characters and entities as one character.
		- avoids trailing entities.
		- returns the shortened string.

	int forum_time(bool use_user_offset = true)
		- returns the current time with offsets.
		- always applies the offset in the time_offset setting.
		- if use_user_offset is true, applies the user's offset as well.
		- returns seconds since the unix epoch.

	array permute(array input)
		- calculates all the possible permutations (orders) of array.
		- should not be called on huge arrays (bigger than like 10 elements.)
		- returns an array containing each permutation.

	string parse_bbc(string message, bool smileys = true, string cache_id = '', array parse_tags = null)
		- this very hefty function parses bbc in message.
		- only parses bbc tags which are not disabled in disabledBBC.
		- also handles basic HTML, if enablePostHTML is on.
		- caches the from/to replace regular expressions so as not to reload
		  them every time a string is parsed.
		- only parses smileys if smileys is true.
		- does nothing if the enableBBC setting is off.
		- applies the fixLongWords magic if the setting is set to on.
		- uses the cache_id as a unique identifier to facilitate any caching
		  it may do.
		- returns the modified message.

	void parsesmileys(string &message)
		- the smiley parsing function which makes pretty faces appear :).
		- if custom smiley sets are turned off by smiley_enable, the default
		  set of smileys will be used.
		- these are specifically not parsed in code tags [url=mailto:Dad@blah.com]
		- caches the smileys from the database or array in memory.
		- doesn't return anything, but rather modifies message directly.

	string highlight_php_code(string code)
		- Uses PHP's highlight_string() to highlight PHP syntax
		- does special handling to keep the tabs in the code available.
		- used to parse PHP code from inside [code] and [php] tags.
		- returns the code with highlighted HTML.

	void writeLog(bool force = false)
		// !!!

	void redirectexit(string setLocation = '', bool use_refresh = false)
		// !!!

	void obExit(bool do_header = true, bool do_footer = do_header)
		// !!!

	int logAction($action, $extra = array())
		// !!!

	void trackStats($stats = array())
		- caches statistics changes, and flushes them if you pass nothing.
		- if '+' is used as a value, it will be incremented.
		- does not actually commit the changes until the end of the page view.
		- depends on the trackStats setting.

	void spamProtection(string error_type)
		- attempts to protect from spammed messages and the like.
		- takes a $txt index. (not an actual string.)
		- time taken depends on error_type - generally uses the modSetting.

	array url_image_size(string url)
		- uses getimagesize() to determine the size of a file.
		- attempts to connect to the server first so it won't time out.
		- returns false on failure, otherwise the output of getimagesize().

	void determineTopicClass(array &topic_context)
		// !!!

	void setupThemeContext(bool force_reload = false)
		// !!!

	void template_rawdata()
		// !!!

	void template_header()
		// !!!

	void theme_copyright(bool get_it = false)
		// !!!

	void template_footer()
		// !!!

	void db_debug_junk()
		// !!!

	void getAttachmentFilename(string filename, int id_attach, bool new = true)
		// !!!

	array ip2range(string $fullip)
		- converts a given IP string to an array.
		- internal function used to convert a user-readable format to
		  a format suitable for the database.
		- returns 'unknown' if the ip in the input was '255.255.255.255'.

	string host_from_ip(string ip_address)
		// !!!

	string create_button(string filename, string alt, string label, bool custom = '')
		// !!!

	void clean_cache(type = '')
		- clean the cache directory ($cachedir, if any and in use)
		- it may only remove the files of a certain type
		(if the $type parameter is given)

	array call_integration_hook(string hook, array parameters = array())
		- calls all functions of the given hook.
		- supports static class method calls.
		- returns the results of the functions as an array.

	void add_integration_function(string hook, string function, bool permanent = true)
		- adds the given function to the given hook.
		- does nothing if the functions is already added.
		- if permanent parameter is true, updates the value in settings table.

	void remove_integration_function(string hook, string function)
		- removes the given function from the given hook.
		- does nothing if the functions is not available.
*/

// Update some basic statistics...
function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	global $sourcedir, $modSettings, $smcFunc;

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

			// Are we using registration approval?
			if ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion']))
			{
				// Update the amount of members awaiting approval - ignoring COPPA accounts, as you can't approve them until you get permission.
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

		if (($postgroups = cache_get_data('updateStats:postgroups', 360)) == null)
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

// Assumes the data has been htmlspecialchar'd.
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
			'hide_email',
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
					call_integration_hook('integrate_change_member_data', array($member_names, $var, $data[$var]));
		}
	}

	// Everything is assumed to be a string unless it's in the below.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'new_pm', 'pm_prefs', 'gender', 'hide_email', 'show_online', 'pm_email_notify', 'pm_receive_from', 'karma_good', 'karma_bad',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);

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

// Updates the settings table as well as $modSettings... only does one at a time if $update is true.
function updateSettings($changeArray, $update = false, $debug = false)
{
	global $modSettings, $smcFunc;

	if (empty($changeArray) || !is_array($changeArray))
		return;

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

// Constructs a page list.
// $pageindex = constructPageIndex($scripturl . '?board=' . $board, $_REQUEST['start'], $num_messages, $maxindex, true);
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false)
{
	global $modSettings;

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

	// Wireless will need the protocol on the URL somewhere.
	if (WIRELESS)
		$base_url .= ';' . WIRELESS_PROTOCOL;

	$base_link = '<a class="navPages" href="' . ($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d') . '">%2$s</a> ';

	// Compact pages is off or on?
	if (empty($modSettings['compactTopicPagesEnable']))
	{
		// Show the left arrow.
		$pageindex = $start == 0 ? ' ' : sprintf($base_link, $start - $num_per_page, '&#171;');

		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? '<strong>' . $display_page++ . '</strong> ' : sprintf($base_link, $counter, $display_page++);

		// Show the right arrow.
		$display_page = ($start + $num_per_page) > $max_value ? $max_value : ($start + $num_per_page);
		if ($start != $counter - $max_value && !$start_invalid)
			$pageindex .= $display_page > $counter - $num_per_page ? ' ' : sprintf($base_link, $display_page, '&#187;');
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$PageContiguous = (int) ($modSettings['compactTopicPagesContiguous'] - ($modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Show the first page. (>1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $PageContiguous)
			$pageindex = sprintf($base_link, 0, '1');
		else
			$pageindex = '';

		// Show the ... after the first page.  (1 >...< 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * ($PageContiguous + 1))
			$pageindex .= '<span style="font-weight: bold;" onclick="' . htmlspecialchars('expandPages(this, ' . JavaScriptEscape(($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d')) . ', ' . $num_per_page . ', ' . ($start - $num_per_page * $PageContiguous) . ', ' . $num_per_page . ');') . '" onmouseover="this.style.cursor = \'pointer\';"> ... </span>';

		// Show the pages before the current one. (1 ... >6 7< [8] 9 10 ... 15)
		for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
			if ($start >= $num_per_page * $nCont)
			{
				$tmpStart = $start - $num_per_page * $nCont;
				$pageindex.= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the current page. (1 ... 6 7 >[8]< 9 10 ... 15)
		if (!$start_invalid)
			$pageindex .= '[<strong>' . ($start / $num_per_page + 1) . '</strong>] ';
		else
			$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

		// Show the pages after the current one... (1 ... 6 7 [8] >9 10< ... 15)
		$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
		for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
			if ($start + $num_per_page * $nCont <= $tmpMaxPages)
			{
				$tmpStart = $start + $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the '...' part near the end. (1 ... 6 7 [8] 9 10 >...< 15)
		if ($start + $num_per_page * ($PageContiguous + 1) < $tmpMaxPages)
			$pageindex .= '<span style="font-weight: bold;" onclick="expandPages(this, \'' . ($flexible_start ? strtr($base_url, array('\'' => '\\\'')) : strtr($base_url, array('%' => '%%', '\'' => '\\\'')) . ';start=%1$d') . '\', ' . ($start + $num_per_page * ($PageContiguous + 1)) . ', ' . $tmpMaxPages . ', ' . $num_per_page . ');" onmouseover="this.style.cursor=\'pointer\';"> ... </span>';

		// Show the last number in the list. (1 ... 6 7 [8] 9 10 ... >15<)
		if ($start + $num_per_page * $PageContiguous < $tmpMaxPages)
			$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);
	}

	return $pageindex;
}

// Formats a number to display in the style of the admin's choosing.
function comma_format($number, $override_decimal_count = false)
{
	global $txt;
	static $thousands_separator = null, $decimal_separator = null, $decimal_count = null;

	// !!! Should, perhaps, this just be handled in the language files, and not a mod setting?
	// (French uses 1 234,00 for example... what about a multilingual forum?)

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
	return number_format($number, is_float($number) ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}

// Format a time to make it look purdy.
function timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $context, $user_info, $txt, $modSettings, $smcFunc;
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
				$str = str_replace($token, !empty($txt['lang_capitalize_dates']) ? $smcFunc['ucwords'](strftime($token, $time)) : strftime($token, $time), $str);
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

// Removes special entities from strings.  Compatibility...
function un_htmlspecialchars($string)
{
	static $translation;

	if (!isset($translation))
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' ');

	return strtr($string, $translation);
}

// Shorten a subject + internationalization concerns.
function shorten_subject($subject, $len)
{
	global $smcFunc;

	// It was already short enough!
	if ($smcFunc['strlen']($subject) <= $len)
		return $subject;

	// Shorten it by the length it was too long, and strip off junk from the end.
	return $smcFunc['substr']($subject, 0, $len) . '...';
}

// The current time with offset.
function forum_time($use_user_offset = true, $timestamp = null)
{
	global $user_info, $modSettings;

	if ($timestamp === null)
		$timestamp = time();
	elseif ($timestamp == 0)
		return 0;

	return $timestamp + ($modSettings['time_offset'] + ($use_user_offset ? $user_info['time_offset'] : 0)) * 3600;
}

// This gets all possible permutations of an array.
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

// Parse bulletin board code in a string, as well as smileys optionally.
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
	global $txt, $scripturl, $context, $modSettings, $user_info, $smcFunc;
	static $bbc_codes = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $disabled;

	// Don't waste cycles
	if ($message === '')
		return '';

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

	// Just in case it wasn't determined yet whether UTF-8 is enabled.
	if (!isset($context['utf8']))
		$context['utf8'] = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8';

	// If we are not doing every tag then we don't cache this run.
	if (!empty($parse_tags) && !empty($bbc_codes))
	{
		$temp_bbc = $bbc_codes;
		$bbc_codes = array();
	}

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
				'before' => '<acronym title="$1">',
				'after' => '</acronym>',
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
				'before' => '<strong>',
				'after' => '</strong>',
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
				'before' => '<span style="color: blue;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'br',
				'type' => 'closed',
				'content' => '<br />',
			),
			array(
				'tag' => 'center',
				'before' => '<div align="center">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_content',
				'content' => '<div class="codeheader">' . $txt['code'] . ': <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div>' . ($context['browser']['is_gecko'] || $context['browser']['is_opera'] ? '<pre style="margin: 0; padding: 0;">' : '') . '<code class="bbc_code">$1</code>' . ($context['browser']['is_gecko'] || $context['browser']['is_opera'] ? '</pre>' : ''),
				// !!! Maybe this can be simplified?
				'validate' => isset($disabled['code']) ? null : create_function('&$tag, &$data, $disabled', '
					global $context;

					if (!isset($disabled[\'code\']))
					{
						$php_parts = preg_split(\'~(&lt;\?php|\?&gt;)~\', $data, -1, PREG_SPLIT_DELIM_CAPTURE);

						for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
						{
							// Do PHP code coloring?
							if ($php_parts[$php_i] != \'&lt;?php\')
								continue;

							$php_string = \'\';
							while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != \'?&gt;\')
							{
								$php_string .= $php_parts[$php_i];
								$php_parts[$php_i++] = \'\';
							}
							$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
						}

						// Fix the PHP code stuff...
						$data = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode(\'\', $php_parts));

						// Older browsers are annoying, aren\'t they?
						if ($context[\'browser\'][\'is_ie4\'] || $context[\'browser\'][\'is_ie5\'] || $context[\'browser\'][\'is_ie5.5\'])
							$data = str_replace("\t", "<pre style=\"display: inline;\">\t</pre>", $data);
						else
							$data = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context[\'browser\'][\'is_opera\'])
							$data .= \'&nbsp;\';
					}'),
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'content' => '<div class="codeheader">' . $txt['code'] . ': ($2) <a href="#" onclick="return smfSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div>' . ($context['browser']['is_gecko'] || $context['browser']['is_opera'] ? '<pre style="margin: 0; padding: 0;">' : '') . '<code class="bbc_code">$1</code>' . ($context['browser']['is_gecko'] || $context['browser']['is_opera'] ? '</pre>' : ''),
				// !!! Maybe this can be simplified?
				'validate' => isset($disabled['code']) ? null : create_function('&$tag, &$data, $disabled', '
					global $context;

					if (!isset($disabled[\'code\']))
					{
						$php_parts = preg_split(\'~(&lt;\?php|\?&gt;)~\', $data[0], -1, PREG_SPLIT_DELIM_CAPTURE);

						for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
						{
							// Do PHP code coloring?
							if ($php_parts[$php_i] != \'&lt;?php\')
								continue;

							$php_string = \'\';
							while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != \'?&gt;\')
							{
								$php_string .= $php_parts[$php_i];
								$php_parts[$php_i++] = \'\';
							}
							$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
						}

						// Fix the PHP code stuff...
						$data[0] = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode(\'\', $php_parts));

						// Older browsers are annoying, aren\'t they?
						if ($context[\'browser\'][\'is_ie4\'] || $context[\'browser\'][\'is_ie5\'] || $context[\'browser\'][\'is_ie5.5\'])
							$data[0] = str_replace("\t", "<pre style=\"display: inline;\">\t</pre>", $data[0]);
						else
							$data[0] = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data[0]);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context[\'browser\'][\'is_opera\'])
							$data[0] .= \'&nbsp;\';
					}'),
				'block_level' => true,
			),
			array(
				'tag' => 'color',
				'type' => 'unparsed_equals',
				'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\]',
				'before' => '<span style="color: $1;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_content',
				'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
				// !!! Should this respect guest_hideContacts?
				'validate' => create_function('&$tag, &$data, $disabled', '$data = strtr($data, array(\'<br />\' => \'\'));'),
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_equals',
				'before' => '<a href="mailto:$1" class="bbc_email">',
				'after' => '</a>',
				// !!! Should this respect guest_hideContacts?
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'flash',
				'type' => 'unparsed_commas_content',
				'test' => '\d+,\d+\]',
				'content' => ($context['browser']['is_ie'] && !$context['browser']['is_mac_ie'] ? '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$2" height="$3"><param name="movie" value="$1" /><param name="play" value="true" /><param name="loop" value="true" /><param name="quality" value="high" /><param name="AllowScriptAccess" value="never" /><embed src="$1" width="$2" height="$3" play="true" loop="true" quality="high" AllowScriptAccess="never" /><noembed><a href="$1" target="_blank" class="new_win">$1</a></noembed></object>' : '<embed type="application/x-shockwave-flash" src="$1" width="$2" height="$3" play="true" loop="true" quality="high" AllowScriptAccess="never" /><noembed><a href="$1" target="_blank" class="new_win">$1</a></noembed>'),
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (isset($disabled[\'url\']))
						$tag[\'content\'] = \'$1\';
					elseif (strpos($data[0], \'http://\') !== 0 && strpos($data[0], \'https://\') !== 0)
						$data[0] = \'http://\' . $data[0];
				'),
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
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'ftp://\') !== 0 && strpos($data, \'ftps://\') !== 0)
						$data = \'ftp://\' . $data;
				'),
			),
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_ftp new_win" target="_blank">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (strpos($data, \'ftp://\') !== 0 && strpos($data, \'ftps://\') !== 0)
						$data = \'ftp://\' . $data;
				'),
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'glow',
				'type' => 'unparsed_commas',
				'test' => '[#0-9a-zA-Z\-]{3,12},([012]\d{1,2}|\d{1,2})(,[^]]+)?\]',
				'before' => $context['browser']['is_ie'] ? '<table border="0" cellpadding="0" cellspacing="0" style="display: inline; vertical-align: middle; font: inherit;"><tr><td style="filter: Glow(color=$1, strength=$2); font: inherit;">' : '<span style="text-shadow: $1 1px 1px 1px">',
				'after' => $context['browser']['is_ie'] ? '</td></tr></table> ' : '</span>',
			),
			array(
				'tag' => 'green',
				'before' => '<span style="color: green;" class="bbc_color">',
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
				'content' => '<hr />',
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
					'width' => array('optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'),
					'height' => array('optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'),
				),
				'content' => '<img src="$1" alt="{alt}"{width}{height} class="bbc_img resized" />',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'img',
				'type' => 'unparsed_content',
				'content' => '<img src="$1" alt="" class="bbc_img" />',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link">$1</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (substr($data, 0, 1) == \'#\')
						$data = \'#post_\' . substr($data, 1);
					elseif (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
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
				'disabled_after' => '<br />',
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
				'disabled_after' => '<br />',
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
				'validate' => isset($disabled['php']) ? null : create_function('&$tag, &$data, $disabled', '
					if (!isset($disabled[\'php\']))
					{
						$add_begin = substr(trim($data), 0, 5) != \'&lt;?\';
						$data = highlight_php_code($add_begin ? \'&lt;?php \' . $data . \'?&gt;\' : $data);
						if ($add_begin)
							$data = preg_replace(array(\'~^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)~\', \'~\?&gt;((?:</(font|span)>)*)$~\'), \'$1\', $data, 2);
					}'),
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
				'before' => '<div class="quoteheader"><div class="topslice_quote">' . $txt['quote'] . '</div></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"><div class="botslice_quote"></div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)', 'quoted' => true),
				),
				'before' => '<div class="quoteheader"><div class="topslice_quote">' . $txt['quote_from'] . ': {author}</div></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"><div class="botslice_quote"></div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'type' => 'parsed_equals',
				'before' => '<div class="quoteheader"><div class="topslice_quote">' . $txt['quote_from'] . ': $1</div></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"><div class="botslice_quote"></div></div>',
				'quoted' => 'optional',
				// Don't allow everything to be embedded with the author name.
				'parsed_tags_allowed' => array('url', 'iurl', 'ftp'),
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '([^<>]{1,192}?)'),
					'link' => array('match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|action=profile;u=\d+)'),
					'date' => array('match' => '(\d+)', 'validate' => 'timeformat'),
				),
				'before' => '<div class="quoteheader"><div class="topslice_quote"><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . $txt['search_on'] . ' {date}</a></div></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"><div class="botslice_quote"></div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)'),
				),
				'before' => '<div class="quoteheader"><div class="topslice_quote">' . $txt['quote_from'] . ': {author}</div></div><blockquote>',
				'after' => '</blockquote><div class="quotefooter"><div class="botslice_quote"></div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'red',
				'before' => '<span style="color: red;" class="bbc_color">',
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
				'before' => $context['browser']['is_ie'] ? '<span style="display: inline-block; filter: Shadow(color=$1, direction=$2); height: 1.2em;">' : '<span style="text-shadow: $1 $2">',
				'after' => '</span>',
				'validate' => $context['browser']['is_ie'] ? create_function('&$tag, &$data, $disabled', '
					if ($data[1] == \'left\')
						$data[1] = 270;
					elseif ($data[1] == \'right\')
						$data[1] = 90;
					elseif ($data[1] == \'top\')
						$data[1] = 0;
					elseif ($data[1] == \'bottom\')
						$data[1] = 180;
					else
						$data[1] = (int) $data[1];') : create_function('&$tag, &$data, $disabled', '
					if ($data[1] == \'top\' || (is_numeric($data[1]) && $data[1] < 50))
						$data[1] = \'0 -2px 1px\';
					elseif ($data[1] == \'right\' || (is_numeric($data[1]) && $data[1] < 100))
						$data[1] = \'2px 0 1px\';
					elseif ($data[1] == \'bottom\' || (is_numeric($data[1]) && $data[1] < 190))
						$data[1] = \'0 2px 1px\';
					elseif ($data[1] == \'left\' || (is_numeric($data[1]) && $data[1] < 280))
						$data[1] = \'-2px 0 1px\';
					else
						$data[1] = \'1px 1px 1px\';'),
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
				'validate' => create_function('&$tag, &$data, $disabled', '
					$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);
					$data = $sizes[$data] . \'em\';'
				),
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
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (is_numeric($data))
						$data = timeformat($data);
					else
						$tag[\'content\'] = \'[time]$1[/time]\';'),
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
				'before' => '<tt class="bbc_tt">',
				'after' => '</tt>',
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
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link" target="_blank">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'white',
				'before' => '<span style="color: white;" class="bbc_color">',
				'after' => '</span>',
			),
		);

		// Let mods add new BBC without hassle.
		call_integration_hook('integrate_bbc_codes', array(&$codes));

		// This is mainly for the bbc manager, so it's easy to add tags above.  Custom BBC should be added above this line.
		if ($message === false)
		{
			if (isset($temp_bbc))
				$bbc_codes = $temp_bbc;
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

		// Inside these tags autolink is not recommendable.
		$no_autolink_tags = array(
			'url',
			'iurl',
			'ftp',
			'email',
		);

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
	if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && strlen($message) > 1000) || strlen($message) > 2400) && empty($parse_tags))
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

		// !!! Change maybe?
		if (!isset($_GET['images']))
			$disabled['img'] = true;

		// !!! Interface/setting to add more?
	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br />'));

	// The non-breaking-space looks a bit different each time.
	$non_breaking_space = $context['utf8'] ? ($context['server']['complex_preg_chars'] ? '\x{A0}' : "\xC2\xA0") : '\xA0';

	// This saves time by doing our break long words checks here.
	if (!empty($modSettings['fixLongWords']) && $modSettings['fixLongWords'] > 5)
	{
		if ($context['browser']['is_gecko'] || $context['browser']['is_konqueror'])
			$breaker = '<span style="margin: 0 -0.5ex 0 0;"> </span>';
		// Opera...
		elseif ($context['browser']['is_opera'])
			$breaker = '<span style="margin: 0 -0.65ex 0 -1px;"> </span>';
		// Internet Explorer...
		else
			$breaker = '<span style="width: 0; margin: 0 -0.6ex 0 -1px;"> </span>';

		// PCRE will not be happy if we don't give it a short.
		$modSettings['fixLongWords'] = (int) min(65535, $modSettings['fixLongWords']);
	}

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

				// <br /> should be empty.
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

				// Do <img ... /> - with security... action= -> action-.
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
				//!!! Don't think is the real solution....
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
							'~(?<=[\s>\.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~i',
							'~(?<=[\s>\.(;\'"]|^)((?:ftp|ftps)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~i',
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
						$data = preg_replace('~(?<=[\?\s' . $non_breaking_space . '\[\]()*\\\;>]|^)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?,\s' . $non_breaking_space . '\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br />))~' . ($context['utf8'] ? 'u' : ''), '[email]$1[/email]', $data);
						$data = preg_replace('~(?<=<br />)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?\.,;\s' . $non_breaking_space . '\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;)~' . ($context['utf8'] ? 'u' : ''), '[email]$1[/email]', $data);
					}
				}
			}

			$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));

			if (!empty($modSettings['fixLongWords']) && $modSettings['fixLongWords'] > 5)
			{
				// The idea is, find words xx long, and then replace them with xx + space + more.
				if ($smcFunc['strlen']($data) > $modSettings['fixLongWords'])
				{
					// This is done in a roundabout way because $breaker has "long words" :P.
					$data = strtr($data, array($breaker => '< >', '&nbsp;' => $context['utf8'] ? "\xC2\xA0" : "\xA0"));
					$data = preg_replace(
						'~(?<=[>;:!? ' . $non_breaking_space . '\]()]|^)([\w' . ($context['utf8'] ? '\pL' : '') . '\.]{' . $modSettings['fixLongWords'] . ',})~e' . ($context['utf8'] ? 'u' : ''),
						'preg_replace(\'/(.{' . ($modSettings['fixLongWords'] - 1) . '})/' . ($context['utf8'] ? 'u' : '') . '\', \'\\$1< >\', \'$1\')',
						$data);
					$data = strtr($data, array('< >' => $breaker, $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));
				}
			}

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

		$tags = strtolower(substr($message, $pos + 1, 1));

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
				if (!empty($tag['block_level']) && substr($message, $pos, 6) == '<br />')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($tag['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
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
			// Not a match?
			if (strtolower(substr($message, $pos + 1, strlen($possible['tag']))) != $possible['tag'])
				continue;

			$next_c = substr($message, $pos + 1 + strlen($possible['tag']), 1);

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + strlen($possible['tag']) + 1)) == 0)
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
				if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + strlen($possible['tag']), 2) != '/]' && substr($message, $pos + 1 + strlen($possible['tag']), 3) != ' /]')
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

			$pos1 = $pos + 1 + strlen($possible['tag']) + 1;

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
		if ($smileys !== false && $tag === null && isset($itemcodes[substr($message, $pos + 1, 1)]) && substr($message, $pos + 2, 1) == ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if (substr($message, $pos + 1, 1) == '0' && !in_array(substr($message, $pos - 1, 1), array(';', ' ', "\t", '>')))
				continue;
			$tag = $itemcodes[substr($message, $pos + 1, 1)];

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
			$pos2 = strpos($message, '<br />', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br />|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . "\n" . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . "\n" . substr($message, $pos2);

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
				$pos += strlen($open_tags[$i]['after']) + 2;
				$pos1 += strlen($open_tags[$i]['after']) + 2;

				// Trim or eat trailing stuff... see comment at the end of the big loop.
				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 6) == '<br />')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($open_tags[$i]['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}

		// No type means 'parsed_content'.
		if (!isset($tag['type']))
		{
			// !!! Check for end tag first, so people can say "I like that [i] tag"?
			$open_tags[] = $tag;
			$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
			$pos += strlen($tag['before']) - 1 + 2;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 6) == '<br />')
				$data = substr($data, 6);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + strlen($tag['tag']));

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
			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 6) == '<br />')
				$data[0] = substr($data[0], 6);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + strlen($tag['tag']));
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
			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos2);
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
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + strlen($tag['tag']));
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
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 6) == '<br />')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 7);

		// Are we trimming outside this tag?
		if (!empty($tag['trim']) && $tag['trim'] != 'outside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
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

	if (substr($message, 0, 1) == ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br />', '<br /> ' => '<br />&nbsp;', '&#13;' => "\n"));

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

// Parse smileys in the passed message.
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info, $context, $smcFunc;
	static $smileyPregSearch = array(), $smileyPregReplacements = array();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none')
		return;

	// If the smiley array hasn't been set, do it now.
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
					FROM {db_prefix}smileys',
					array(
					)
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdescs = array();
				while ($row = $smcFunc['db_fetch_assoc']($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $row['filename'];
					$smileysdescs[] = $row['description'];
				}
				$smcFunc['db_free_result']($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;
		}

		// The non-breaking-space is a complex thing...
		$non_breaking_space = $context['utf8'] ? ($context['server']['complex_preg_chars'] ? '\x{A0}' : "\xC2\xA0") : '\xA0';

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$smileyPregReplacements = array();
		$searchParts = array();
		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$smileyCode = '<img src="' . htmlspecialchars($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/' . $smileysto[$i]) . '" alt="' . strtr(htmlspecialchars($smileysfrom[$i], ENT_QUOTES), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley" />';

			$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;
			$smileyPregReplacements[htmlspecialchars($smileysfrom[$i], ENT_QUOTES)] = $smileyCode;
			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			$searchParts[] = preg_quote(htmlspecialchars($smileysfrom[$i], ENT_QUOTES), '~');
		}

		$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~e' . ($context['utf8'] ? 'u' : '');
	}

	// Replace away!
	$message = preg_replace($smileyPregSearch, 'isset($smileyPregReplacements[\'$1\']) ? $smileyPregReplacements[\'$1\'] : \'\'', $message);
}

// Highlight any code...
function highlight_php_code($code)
{
	global $context;

	// Remove special characters.
	$code = un_htmlspecialchars(strtr($code, array('<br />' => "\n", "\t" => 'SMF_TAB();', '&#91;' => '[')));

	$oldlevel = error_reporting(0);

	// It's easier in 4.2.x+.
	if (@version_compare(PHP_VERSION, '4.2.0') == -1)
	{
		ob_start();
		@highlight_string($code);
		$buffer = str_replace(array("\n", "\r"), '', ob_get_contents());
		ob_end_clean();
	}
	else
		$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));

	error_reporting($oldlevel);

	// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P.
	$buffer = preg_replace('~SMF_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<pre style="display: inline;">' . "\t" . '</pre>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}

// Put this user in the online log.
function writeLog($force = false)
{
	global $user_info, $user_settings, $context, $modSettings, $settings, $topic, $board, $smcFunc, $sourcedir;

	// If we are showing who is viewing a topic, let's see if we are, and force an update if so - to make it accurate.
	if (!empty($settings['display_who_viewing']) && ($topic || $board))
	{
		// Take the opposite approach!
		$force = true;
		// Don't update for every page - this isn't wholly accurate but who cares.
		if ($topic)
		{
			if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == $topic)
				$force = false;
			$_SESSION['last_topic_id'] = $topic;
		}
	}

	// Are they a spider we should be tracking? Mode = 1 gets tracked on its spider check...
	if (!empty($user_info['possibly_robot']) && !empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
	{
		require_once($sourcedir . '/ManageSearchEngines.php');
		logSpider();
	}

	// Don't mark them as online more than every so often.
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty($modSettings['who_enabled']))
	{
		$serialized = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);

		// In the case of a dlattach action, session_var may not be set.
		if (!isset($context['session_var']))
			$context['session_var'] = $_SESSION['session_var'];

		unset($serialized['sesc'], $serialized[$context['session_var']]);
		$serialized = serialize($serialized);
	}
	else
		$serialized = '';

	// Guests use 0, members use their session ID.
	$session_id = $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id();

	// Grab the last all-of-SMF-specific log_online deletion time.
	$do_delete = cache_get_data('log_online-update', 30) < time() - 30;

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			$smcFunc['db_query']('delete_log_online_interval', '
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - $modSettings['lastActive'] * 60,
					'session' => $session_id,
				)
			);

			// Cache when we did it last.
			cache_put_data('log_online-update', time(), 30);
		}

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = IFNULL(INET_ATON({string:ip}), 0), url = {string:url}
			WHERE session = {string:session}',
			array(
				'log_time' => time(),
				'ip' => $user_info['ip'],
				'url' => $serialized,
				'session' => $session_id,
			)
		);

		// Guess it got deleted.
		if ($smcFunc['db_affected_rows']() == 0)
			$_SESSION['log_time'] = 0;
	}
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
	{
		if ($do_delete || !empty($user_info['id']))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_online
				WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty($user_info['id']) ? ' OR ' : '') . (empty($user_info['id']) ? '' : 'id_member = {int:current_member}'),
				array(
					'current_member' => $user_info['id'],
					'log_time' => time() - $modSettings['lastActive'] * 60,
				)
			);

		$smcFunc['db_insert']($do_delete ? 'ignore' : 'replace',
			'{db_prefix}log_online',
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'raw', 'url' => 'string'),
			array($session_id, $user_info['id'], empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), 'IFNULL(INET_ATON(\'' . $user_info['ip'] . '\'), 0)', $serialized),
			array('session')
		);
	}

	// Mark your session as being logged.
	$_SESSION['log_time'] = time();

	// Well, they are online now.
	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();

	// Set their login time, if not already done within the last minute.
	if (SMF != 'SSI' && !empty($user_info['last_login']) && $user_info['last_login'] < time() - 60)
	{
		// Don't count longer than 15 minutes.
		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		$user_settings['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . $user_info['id'], $user_settings, 60);

		$user_info['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}

// Make sure the browser doesn't come back and repost the form data.  Should be used whenever anything is posted.
function redirectexit($setLocation = '', $refresh = false)
{
	global $scripturl, $context, $modSettings, $db_show_debug, $db_cache;

	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty($context['flush_mail']))
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

	if (!empty($modSettings['queryless_urls']) && (empty($context['server']['is_cgi']) || @ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && (!empty($context['server']['is_apache']) || !empty($context['server']['is_lighttpd'])))
	{
		if (defined('SID') && SID != '')
			$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$/e', "\$scripturl . '/' . strtr('\$1', '&;=', '//,') . '.html\$2?' . SID", $setLocation);
		else
			$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$/e', "\$scripturl . '/' . strtr('\$1', '&;=', '//,') . '.html\$2'", $setLocation);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh));

	// We send a Refresh header only in special cases because Location looks better. (and is quicker...)
	if ($refresh && !WIRELESS)
		header('Refresh: 0; URL=' . strtr($setLocation, array(' ' => '%20')));
	else
		header('Location: ' . str_replace(' ', '%20', $setLocation));

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}

// Ends execution.  Takes care of template loading and remembering the previous URL.
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
		AddMailQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));

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
				$function = trim($function);
				$call = strpos($function, '::') !== false ? explode('::', $function) : $function;

				// Is it valid?
				if (is_callable($call))
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
				db_debug_junk();
		}
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewsmfile') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verfication.... don't switch browsers...
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

	if (!empty($settings['strict_doctype']))
	{
		// The theme author wants to use the STRICT doctype (only God knows why).
		$temp = ob_get_contents();
		if (function_exists('ob_clean'))
			ob_clean();
		else
		{
			ob_end_clean();
			ob_start('ob_sessrewrite');
		}

		echo strtr($temp, array(
			'var smf_iso_case_folding' => 'var target_blank = \'_blank\'; var smf_iso_case_folding',
			'target="_blank"' => 'onclick="this.target=target_blank"'));
	}

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer && !WIRELESS));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index || WIRELESS)
		exit;
}

// Usage: logAction('remove', array('starter' => $id_member_started));
function logAction($action, $extra = array(), $log_type = 'moderate')
{
	global $modSettings, $user_info, $smcFunc, $sourcedir;

	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);

	if (!is_array($extra))
		trigger_error('logAction(): data is not an array with action \'' . $action . '\'', E_USER_NOTICE);

	// Pull out the parts we want to store separately, but also make sure that the data is proper
	if (isset($extra['topic']))
	{
		if (!is_numeric($extra['topic']))
			trigger_error('logAction(): data\'s topic is not a number', E_USER_NOTICE);
		$topic_id = empty($extra['topic']) ? '0' : (int)$extra['topic'];
		unset($extra['topic']);
	}
	else
		$topic_id = '0';

	if (isset($extra['message']))
	{
		if (!is_numeric($extra['message']))
			trigger_error('logAction(): data\'s message is not a number', E_USER_NOTICE);
		$msg_id = empty($extra['message']) ? '0' : (int)$extra['message'];
		unset($extra['message']);
	}
	else
		$msg_id = '0';

	// Is there an associated report on this?
	if (in_array($action, array('move', 'remove', 'split', 'merge')))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_report
			FROM {db_prefix}log_reported
			WHERE {raw:column_name} = {int:reported}
			LIMIT 1',
			array(
				'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
				'reported' => !empty($msg_id) ? $msg_id : $topic_id,
		));

		// Alright, if we get any result back, update open reports.
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			require_once($sourcedir . '/ModerationCenter.php');
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
		$smcFunc['db_free_result']($request);
	}

	// No point in doing anything else, if the log isn't even enabled.
	if (empty($modSettings['modlog_enabled']) || !isset($log_types[$log_type]))
		return false;

	if (isset($extra['member']) && !is_numeric($extra['member']))
		trigger_error('logAction(): data\'s member is not a number', E_USER_NOTICE);

	if (isset($extra['board']))
	{
		if (!is_numeric($extra['board']))
			trigger_error('logAction(): data\'s board is not a number', E_USER_NOTICE);
		$board_id = empty($extra['board']) ? '0' : (int)$extra['board'];
		unset($extra['board']);
	}
	else
		$board_id = '0';

	if (isset($extra['board_to']))
	{
		if (!is_numeric($extra['board_to']))
			trigger_error('logAction(): data\'s board_to is not a number', E_USER_NOTICE);
		if (empty($board_id))
		{
			$board_id = empty($extra['board_to']) ? '0' : (int)$extra['board_to'];
			unset($extra['board_to']);
		}
	}

	$smcFunc['db_insert']('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), $log_types[$log_type], $user_info['id'], $user_info['ip'], $action,
			$board_id, $topic_id, $msg_id, serialize($extra),
		),
		array('id_action')
	);

	return $smcFunc['db_insert_id']('{db_prefix}log_actions', 'id_action');
}

// Track Statistics.
function trackStats($stats = array())
{
	global $modSettings, $smcFunc;
	static $cache_stats = array();

	if (empty($modSettings['trackStats']))
		return false;
	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = strftime('%Y-%m-%d', forum_time(false));
	$update_parameters = array(
		'current_date' => $date,
	);
	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate .= '
			' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;
		$insert_keys[$field] = 'int';
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
		WHERE date = {date:current_date}',
		$update_parameters
	);
	if ($smcFunc['db_affected_rows']() == 0)
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_activity',
			array_merge($insert_keys, array('date' => 'date')),
			array_merge($cache_stats, array($date)),
			array('date')
		);
	}

	// Don't do this again.
	$cache_stats = array();

	return true;
}

// Make sure the user isn't posting over and over again.
function spamProtection($error_type)
{
	global $modSettings, $txt, $user_info, $smcFunc;

	// Certain types take less/more time.
	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'sendtopc' => $modSettings['spamWaitTime'] * 4,
		'sendmail' => $modSettings['spamWaitTime'] * 5,
		'reporttm' => $modSettings['spamWaitTime'] * 4,
		'search' => !empty($modSettings['search_floodcontrol_time']) ? $modSettings['search_floodcontrol_time'] : 1,
	);

	// Moderators are free...
	if (!allowedTo('moderate_board'))
		$timeLimit = isset($timeOverrides[$error_type]) ? $timeOverrides[$error_type] : $modSettings['spamWaitTime'];
	else
		$timeLimit = 2;

	// Delete old entries...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);

	// Add a new entry, deleting the old if necessary.
	$smcFunc['db_insert']('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'string-16', 'log_time' => 'int', 'log_type' => 'string'),
		array($user_info['ip'], time(), $error_type),
		array('ip', 'log_type')
	);

	// If affected is 0 or 2, it was there already.
	if ($smcFunc['db_affected_rows']() != 1)
	{
		// Spammer!  You only have to wait a *few* seconds!
		fatal_lang_error($error_type . 'WaitTime_broken', false, array($timeLimit));
		return true;
	}

	// They haven't posted within the limit.
	return false;
}

// Get the size of a specified image with better error handling.
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

function determineTopicClass(&$topic_context)
{
	// Set topic class depending on locked status and number of replies.
	if ($topic_context['is_very_hot'])
		$topic_context['class'] = 'veryhot';
	elseif ($topic_context['is_hot'])
		$topic_context['class'] = 'hot';
	else
		$topic_context['class'] = 'normal';

	$topic_context['class'] .= $topic_context['is_poll'] ? '_poll' : '_post';

	if ($topic_context['is_locked'])
		$topic_context['class'] .= '_locked';

	if ($topic_context['is_sticky'])
		$topic_context['class'] .= '_sticky';

	// This is so old themes will still work.
	$topic_context['extended_class'] = &$topic_context['class'];
}

// Sets up the basic theme context stuff.
function setupThemeContext($forceload = false)
{
	global $modSettings, $user_info, $scripturl, $context, $settings, $options, $txt, $maintenance;
	global $user_settings, $smcFunc;
	static $loaded = false;

	// Under SSI this function can be called more then once.  That can cause some problems.
	//   So only run the function once unless we are forced to run it again.
	if ($loaded && !$forceload)
		return;

	$loaded = true;

	$context['in_maintenance'] = !empty($maintenance);
	$context['current_time'] = timeformat(time(), false);
	$context['current_action'] = isset($_GET['action']) ? $_GET['action'] : '';
	$context['show_quick_login'] = !empty($modSettings['enableVBStyleLogin']) && $user_info['is_guest'];

	// Get some news...
	$context['news_lines'] = explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news']))));
	$context['fader_news_lines'] = array();
	for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
	{
		if (trim($context['news_lines'][$i]) == '')
			continue;

		// Clean it up for presentation ;).
		$context['news_lines'][$i] = parse_bbc(stripslashes(trim($context['news_lines'][$i])), true, 'news' . $i);

		// Gotta be special for the javascript.
		$context['fader_news_lines'][$i] = strtr(addslashes($context['news_lines'][$i]), array('/' => '\/', '<a href=' => '<a hre" + "f='));
	}
	$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];

	if (!$user_info['is_guest'])
	{
		$context['user']['messages'] = &$user_info['messages'];
		$context['user']['unread_messages'] = &$user_info['unread_messages'];

		// Personal message popup...
		if ($user_info['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0))
			$context['user']['popup_messages'] = true;
		else
			$context['user']['popup_messages'] = false;
		$_SESSION['unread_messages'] = $user_info['unread_messages'];

		if (allowedTo('moderate_forum'))
			$context['unapproved_members'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion']) ? $modSettings['unapprovedMembers'] : 0;
		$context['show_open_reports'] = empty($user_settings['mod_prefs']) || $user_settings['mod_prefs'][0] == 1;

		$context['user']['avatar'] = array();

		// Figure out the avatar... uploaded?
		if ($user_info['avatar']['url'] == '' && !empty($user_info['avatar']['id_attach']))
			$context['user']['avatar']['href'] = $user_info['avatar']['custom_dir'] ? $modSettings['custom_avatar_url'] . '/' . $user_info['avatar']['filename'] : $scripturl . '?action=dlattach;attach=' . $user_info['avatar']['id_attach'] . ';type=avatar';
		// Full URL?
		elseif (substr($user_info['avatar']['url'], 0, 7) == 'http://')
		{
			$context['user']['avatar']['href'] = $user_info['avatar']['url'];

			if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
			{
				if (!empty($modSettings['avatar_max_width_external']))
					$context['user']['avatar']['width'] = $modSettings['avatar_max_width_external'];
				if (!empty($modSettings['avatar_max_height_external']))
					$context['user']['avatar']['height'] = $modSettings['avatar_max_height_external'];
			}
		}
		// Otherwise we assume it's server stored?
		elseif ($user_info['avatar']['url'] != '')
			$context['user']['avatar']['href'] = $modSettings['avatar_url'] . '/' . htmlspecialchars($user_info['avatar']['url']);

		if (!empty($context['user']['avatar']))
			$context['user']['avatar']['image'] = '<img src="' . $context['user']['avatar']['href'] . '"' . (isset($context['user']['avatar']['width']) ? ' width="' . $context['user']['avatar']['width'] . '"' : '') . (isset($context['user']['avatar']['height']) ? ' height="' . $context['user']['avatar']['height'] . '"' : '') . ' alt="" class="avatar" />';

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
		elseif ($context['browser']['is_ie5'] || $context['browser']['is_ie5.5'])
			$context['disable_login_hashing'] = true;
	}

	// Setup the main menu items.
	setupMenuContext();

	if (empty($settings['theme_version']))
		$context['show_vBlogin'] = $context['show_quick_login'];

	// This is here because old index templates might still use it.
	$context['show_news'] = !empty($settings['enable_news']);

	// This is done to allow theme authors to customize it as they want.
	$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'pm');

	// Resize avatars the fancy, but non-GD requiring way.
	if ($modSettings['avatar_action_too_large'] == 'option_js_resize' && (!empty($modSettings['avatar_max_width_external']) || !empty($modSettings['avatar_max_height_external'])))
	{
		$context['html_headers'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		var smf_avatarMaxWidth = ' . (int) $modSettings['avatar_max_width_external'] . ';
		var smf_avatarMaxHeight = ' . (int) $modSettings['avatar_max_height_external'] . ';';

		if (!$context['browser']['is_ie'] && !$context['browser']['is_mac_ie'])
			$context['html_headers'] .= '
	window.addEventListener("load", smf_avatarResize, false);';
		else
			$context['html_headers'] .= '
	var window_oldAvatarOnload = window.onload;
	window.onload = smf_avatarResize;';

		// !!! Move this over to script.js?
		$context['html_headers'] .= '
	// ]]></script>';
	}

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

	if (empty($settings['theme_version']))
		$context['html_headers'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		var smf_scripturl = "' . $scripturl . '";
	// ]]></script>';

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? $smcFunc['htmlspecialchars']($modSettings['meta_keywords']) : '';
}

// This is the only template included in the sources...
function template_rawdata()
{
	global $context;

	echo $context['raw_data'];
}

function template_header()
{
	global $txt, $modSettings, $context, $settings, $user_info, $boarddir, $cachedir;

	setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		// Are we debugging the template/html content?
		if (!isset($_REQUEST['xml']) && isset($_GET['debug']) && !$context['browser']['is_ie'] && !WIRELESS)
			header('Content-Type: application/xhtml+xml');
		elseif (!isset($_REQUEST['xml']) && !WIRELESS)
			header('Content-Type: text/html; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	}

	header('Content-Type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

	$checked_securityFiles = false;
	$showed_banned = false;
	foreach ($context['template_layers'] as $layer)
	{
		loadSubTemplate($layer . '_above', true);

		// May seem contrived, but this is done in case the body and main layer aren't there...
		if (in_array($layer, array('body', 'main')) && allowedTo('admin_forum') && !$user_info['is_guest'] && !$checked_securityFiles)
		{
			$checked_securityFiles = true;
			$securityFiles = array('install.php', 'webinstall.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');
			foreach ($securityFiles as $i => $securityFile)
			{
				if (!file_exists($boarddir . '/' . $securityFile))
					unset($securityFiles[$i]);
			}

			if (!empty($securityFiles) || (!empty($modSettings['cache_enable']) && !is_writable($cachedir)))
			{
				echo '
		<div class="errorbox">
			<p class="alert">!!</p>
			<h3>', empty($securityFiles) ? $txt['cache_writable_head'] : $txt['security_risk'], '</h3>
			<p>';

				foreach ($securityFiles as $securityFile)
				{
					echo '
				', $txt['not_removed'], '<strong>', $securityFile, '</strong>!<br />';

					if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
						echo '
				', sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br />';
				}

				if (!empty($modSettings['cache_enable']) && !is_writable($cachedir))
					echo '
				<strong>', $txt['cache_writable'], '</strong><br />';

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

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
}

// Show the copyright...
function theme_copyright($get_it = false)
{
	global $forum_copyright, $context, $boardurl, $forum_version, $txt, $modSettings;

	// Don't display copyright for things like SSI.
	if (!isset($forum_version))
		return;

	// Put in the version...
	$forum_copyright = sprintf($forum_copyright, $forum_version);

	echo '
			<span class="smalltext" style="display: inline; visibility: visible; font-family: Verdana, Arial, sans-serif;">' . $forum_copyright . '
			</span>';
}

function template_footer()
{
	global $context, $settings, $modSettings, $time_start, $db_count;

	// Show the load time?  (only makes sense for the footer.)
	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
	$context['load_time'] = round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3);
	$context['load_queries'] = $db_count;

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $settings['actual_theme_url'];
		$settings['images_url'] = $settings['actual_images_url'];
		$settings['theme_dir'] = $settings['actual_theme_dir'];
	}

	foreach (array_reverse($context['template_layers']) as $layer)
		loadSubTemplate($layer . '_below', true);

}

// Debugging.
function db_debug_junk()
{
	global $context, $scripturl, $boarddir, $modSettings, $boarddir;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits, $txt;

	// Add to Settings.php if you want to show the debugging information.
	if (!isset($db_show_debug) || $db_show_debug !== true || (isset($_GET['action']) && $_GET['action'] == 'viewquery') || WIRELESS)
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty($context['debug']['language_files']))
		$context['debug']['language_files'] = array();
	if (empty($context['debug']['sheets']))
		$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array($boarddir => '.'));
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
		{
			if (!empty($qq['w']))
				$warnings += count($qq['w']);
		}

		$_SESSION['debug'] = &$db_cache;
	}

	// Gotta have valid HTML ;).
	$temp = ob_get_contents();
	if (function_exists('ob_clean'))
		ob_clean();
	else
	{
		ob_end_clean();
		ob_start('ob_sessrewrite');
	}

	echo preg_replace('~</body>\s*</html>~', '', $temp), '
<div class="smalltext" style="text-align: left; margin: 1ex;">
	', $txt['debug_templates'], count($context['debug']['templates']), ': <em>', implode('</em>, <em>', $context['debug']['templates']), '</em>.<br />
	', $txt['debug_subtemplates'], count($context['debug']['sub_templates']), ': <em>', implode('</em>, <em>', $context['debug']['sub_templates']), '</em>.<br />
	', $txt['debug_language_files'], count($context['debug']['language_files']), ': <em>', implode('</em>, <em>', $context['debug']['language_files']), '</em>.<br />
	', $txt['debug_stylesheets'], count($context['debug']['sheets']), ': <em>', implode('</em>, <em>', $context['debug']['sheets']), '</em>.<br />
	', $txt['debug_files_included'], count($files), ' - ', round($total_size / 1024), $txt['debug_kb'], ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_include_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_include_info" style="display: none;"><em>', implode('</em>, <em>', $files), '</em></span>)<br />';

	if (!empty($modSettings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}

		echo '
	', $txt['debug_cache_hits'], $cache_count, ': ', sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)), ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_cache_info" style="display: none;"><em>', implode('</em>, <em>', $entries), '</em></span>)<br />';
	}

	echo '
	<a href="', $scripturl, '?action=viewquery" target="_blank" class="new_win">', $warnings == 0 ? sprintf($txt['debug_queries_used'], (int) $db_count) : sprintf($txt['debug_queries_used_and_warnings'], (int) $db_count, $warnings), '</a><br />
	<br />';

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
		foreach ($db_cache as $q => $qq)
		{
			$is_select = substr(trim($qq['q']), 0, 6) == 'SELECT' || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;
			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $qq['f']);

			echo '
	<strong>', $is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none;">' : '', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br />
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				echo sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t']) && isset($txt['debug_query_which_took_at']))
				echo sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br />';
			elseif (isset($qq['t']))
				echo sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br />';
			echo '
	<br />';
		}

	echo '
	<a href="' . $scripturl . '?action=viewquery;sa=hide">', $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>
</div></body></html>';
}

// Get an attachment's encrypted filename.  If $new is true, won't check for file existence.
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings, $smcFunc;

	// Just make up a nice hash...
	if ($new)
		return sha1(md5($filename . time()) . mt_rand());

	// Grab the file hash if it wasn't added.
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

	// In case of files from the old system, do a legacy call.
	if (empty($file_hash))
		return getLegacyAttachmentFilename($filename, $attachment_id, $dir, $new);

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = $modSettings['attachmentUploadDir'][$dir];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash;
}

// Older attachments may still use this function.
function getLegacyAttachmentFilename($filename, $attachment_id, $dir = null, $new = false)
{
	global $modSettings, $db_character_set;

	$clean_name = $filename;
	// Remove international characters (windows-1252)
	// These lines should never be needed again. Still, behave.
	if (empty($db_character_set) || $db_character_set != 'utf8')
	{
		$clean_name = strtr($filename,
			"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
			'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array("\xde" => 'TH', "\xfe" =>
			'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE',
			"\x9c" => 'oe', "\c6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u'));
	}
	// Sorry, no spaces, dots, or anything else but letters allowed.
	$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);

	$enc_name = $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
	$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

	if ($attachment_id == false || ($new && empty($modSettings['attachmentEncryptFilenames'])))
		return $clean_name;
	elseif ($new)
		return $enc_name;

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = $modSettings['attachmentUploadDir'][$dir];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	if (file_exists($path . '/' . $enc_name))
		$filename = $path . '/' . $enc_name;
	else
		$filename = $path . '/' . $clean_name;

	return $filename;
}

// Convert a single IP to a ranged IP.
function ip2range($fullip)
{
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

	return $ip_array;
}

// Lookup an IP; try shell_exec first because we can do a timeout on it.
function host_from_ip($ip)
{
	global $modSettings;

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime();

	// If we can't access nslookup/host, PHP 4.1.x might just crash.
	if (@version_compare(PHP_VERSION, '4.2.0') == -1)
		$host = false;

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
	if (!isset($host) && strpos(strtolower(PHP_OS), 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
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

// Chops a string into words and prepares them to be inserted into (or searched from) the database.
function text2words($text, $max_chars = 20, $encrypt = false)
{
	global $smcFunc, $context;

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0' . ($context['utf8'] ? ($context['server']['complex_preg_chars'] ? '\x{A0}' : "\xC2\xA0") : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . ($context['utf8'] ? 'u' : ''), ' ', strtr($text, array('<br />' => ' ')));

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

// Creates an image/text button
function create_button($name, $alt, $label = '', $custom = '', $force_use = false)
{
	global $settings, $txt, $context;

	// Does the current loaded theme have this and we are not forcing the usage of this function?
	if (function_exists('template_create_button') && !$force_use)
		return template_create_button($name, $alt, $label = '', $custom = '');

	if (!$settings['use_image_buttons'])
		return $txt[$alt];
	elseif (!empty($settings['use_buttons']))
		return '<img src="' . $settings['images_url'] . '/buttons/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . ' />' . ($label != '' ? '<strong>' . $txt[$label] . '</strong>' : '');
	else
		return '<img src="' . $settings['lang_images_url'] . '/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . ' />';
}

// Empty out the cache folder.
function clean_cache($type = '')
{
	global $cachedir, $sourcedir;

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

	// Invalidate cache, to be sure!
	// ... as long as Load.php can be modified, anyway.
	@touch($sourcedir . '/' . 'Load.php');
	clearstatcache();
}

// Load classes that are both (E_STRICT) PHP 4 and PHP 5 compatible.
function loadClassFile($filename)
{
	global $sourcedir;
	static $files_included = array();

	if (!file_exists($sourcedir . '/' . $filename))
		fatal_lang_error('error_bad_file', 'general', array($sourcedir . '/' . $filename));

	// Using a version below PHP 5.0? Do a compatibility conversion.
	if (@version_compare(PHP_VERSION, '5.0.0') != 1)
	{
		// Check if it was included before.
		if (in_array($filename, $files_included))
			return;

		// Make sure we don't include it again.
		$files_included[] = $filename;

		// Do some replacements to make it PHP 4 compatible.
		eval('?' . '>' . preg_replace(array(
			'~class\s+([\w-_]+)([^}]+)function\s+__construct\s*\(~',
			'~([\s\t]+)public\s+\$~',
			'~([\s\t]+)private\s+\$~',
			'~([\s\t]+)protected\s+\$~',
			'~([\s\t]+)public\s+function\s+~',
			'~([\s\t]+)private\s+function\s+~',
			'~([\s\t]+)protected\s+function\s+~',
		), array(
			'class $1$2function $1(',
			'$1var $',
			'$1var $',
			'$1var $',
			'$1function ',
			'$1function ',
			'$1function ',
		), rtrim(file_get_contents($sourcedir . '/' . $filename))));
	}
	else
		require_once($sourcedir . '/' . $filename);
}

function setupMenuContext()
{
	global $context, $modSettings, $user_info, $txt, $scripturl;

	// Set up the menu privileges.
	$context['allow_search'] = allowedTo('search_posts');
	$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
	$context['allow_edit_profile'] = !$user_info['is_guest'] && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
	$context['allow_memberlist'] = allowedTo('view_mlist');
	$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
	$context['allow_moderation_center'] = $context['user']['can_mod'];
	$context['allow_pm'] = allowedTo('pm_read');

	$cacheTime = $modSettings['lastActive'] * 60;

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
			'help' => array(
				'title' => $txt['help'],
				'href' => $scripturl . '?action=help',
				'show' => true,
				'sub_buttons' => array(
				),
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
						'href' => $scripturl . '?action=moderate;area=reports',
						'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
						'is_last' => true,
					),
				),
			),
			'profile' => array(
				'title' => $txt['profile'],
				'href' => $scripturl . '?action=profile',
				'show' => $context['allow_edit_profile'],
				'sub_buttons' => array(
					'summary' => array(
						'title' => $txt['summary'],
						'href' => $scripturl . '?action=profile',
						'show' => true,
					),
					'account' => array(
						'title' => $txt['account'],
						'href' => $scripturl . '?action=profile;area=account',
						'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
					),
					'profile' => array(
						'title' => $txt['forumprofile'],
						'href' => $scripturl . '?action=profile;area=forumprofile',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
						'is_last' => true,
					),
				),
			),
			'pm' => array(
				'title' => $txt['pm_short'],
				'href' => $scripturl . '?action=pm',
				'show' => $context['allow_pm'],
				'sub_buttons' => array(
					'pm_read' => array(
						'title' => $txt['pm_menu_read'],
						'href' => $scripturl . '?action=pm',
						'show' => allowedTo('pm_read'),
					),
					'pm_send' => array(
						'title' => $txt['pm_menu_send'],
						'href' => $scripturl . '?action=pm;sa=send',
						'show' => allowedTo('pm_send'),
						'is_last' => true,
					),
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
						'show' => allowedTo('calendar_post'),
					),
					'post' => array(
						'title' => $txt['calendar_post_event'],
						'href' => $scripturl . '?action=calendar;sa=post',
						'show' => allowedTo('calendar_post'),
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
			'login' => array(
				'title' => $txt['login'],
				'href' => $scripturl . '?action=login',
				'show' => $user_info['is_guest'],
				'sub_buttons' => array(
				),
			),
			'register' => array(
				'title' => $txt['register'],
				'href' => $scripturl . '?action=register',
				'show' => $user_info['is_guest'],
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

	$context['menu_buttons'][$current_action]['active_button'] = true;

	if (!$user_info['is_guest'] && $context['user']['unread_messages'] > 0 && isset($context['menu_buttons']['pm']))
	{
		$context['menu_buttons']['pm']['alttitle'] = $context['menu_buttons']['pm']['title'] . ' [' . $context['user']['unread_messages'] . ']';
		$context['menu_buttons']['pm']['title'] .= ' [<strong>' . $context['user']['unread_messages'] . '</strong>]';
	}
}

// Generate a random seed and ensure it's stored in settings.
function smf_seed_generator()
{
	global $modSettings;

	// Never existed?
	if (empty($modSettings['rand_seed']))
	{
		$modSettings['rand_seed'] = microtime() * 1000000;
		updateSettings(array('rand_seed' => $modSettings['rand_seed']));
	}

	if (@version_compare(PHP_VERSION, '4.2.0') == -1)
	{
		$seed = ($modSettings['rand_seed'] + ((double) microtime() * 1000003)) & 0x7fffffff;
		mt_srand($seed);
	}

	// Change the seed.
	updateSettings(array('rand_seed' => mt_rand()));
}

// Process functions of an integration hook.
function call_integration_hook($hook, $parameters = array())
{
	global $modSettings;

	$results = array();
	if (empty($modSettings[$hook]))
		return $results;

	$functions = explode(',', $modSettings[$hook]);

	// Loop through each function.
	foreach ($functions as $function)
	{
		$function = trim($function);
		$call = strpos($function, '::') !== false ? explode('::', $function) : $function;

		// Is it valid?
		if (is_callable($call))
			$results[$function] = call_user_func_array($call, $parameters);
	}

	return $results;
}

// Add a function for integration hook.
function add_integration_function($hook, $function, $permanent = true)
{
	global $smcFunc, $modSettings;

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
		list($current_functions) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);
			if (in_array($function, $current_functions))
				return;

			$permanent_functions = array_merge($current_functions, array($function));
		}
		else
			$permanent_functions = array($function);

		updateSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// Do nothing, if it's already there.
	if (in_array($function, $functions))
		return;

	$functions[] = $function;
	$modSettings[$hook] = implode(',', $functions);
}

// Remove an integration hook function.
function remove_integration_function($hook, $function)
{
	global $smcFunc, $modSettings;

	// Get the permanent functions.
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:variable}',
		array(
			'variable' => $hook,
		)
	);
	list($current_functions) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		if (in_array($function, $current_functions))
			updateSettings(array($hook => implode(',', array_diff($current_functions, array($function)))));
	}

	// Turn the function list into something usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// You can only remove it if it's available.
	if (!in_array($function, $functions))
		return;

	$functions = array_diff($functions, array($function));
	$modSettings[$hook] = implode(',', $functions);
}

?>