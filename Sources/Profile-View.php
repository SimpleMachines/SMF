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

/*

	void summary(int id_member)
		// !!!

	void showPosts(int id_member)
		// !!!

	void showAttachments(int id_member)
		// !!!

	void statPanel(int id_member)
		// !!!

	void tracking(int id_member)
		// !!!

	void trackUser(int id_member)
		// !!!

	int list_getUserErrorCount(string where)
		// !!!

	array list_getUserErrors(int start, int items_per_page, string sort, string where, array where_vars)
		// !!!

	int list_getIPMessageCount(string where)
		// !!!

	array list_getIPMessages(int start, int items_per_page, string sort, string where, array where_vars)
		// !!!

	void TrackIP(int id_member = none)
		// !!!

	void trackEdits(int id_member)
		// !!!

	int list_getProfileEditCount(int id_member)
		// !!!

	array list_getProfileEdits(int start, int items_per_page, string sort, int id_member)
		// !!!

	void showPermissions(int id_member)
		// !!!

	void viewWarning(int id_member)
		// !!!
*/

// View a summary.
function summary($memID)
{
	global $context, $memberContext, $txt, $modSettings, $user_info, $user_profile, $sourcedir, $scripturl, $smcFunc;

	// Attempt to load the member's profile data.
	if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
		fatal_lang_error('not_a_user', false);

	// Set up the stuff and load the user.
	$context += array(
		'page_title' => sprintf($txt['profile_of_username'], $memberContext[$memID]['name']),
		'can_send_pm' => allowedTo('pm_send'),
		'can_have_buddy' => allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']),
		'can_issue_warning' => in_array('w', $context['admin_features']) && allowedTo('issue_warning') && $modSettings['warning_settings'][0] == 1,
	);
	$context['member'] = &$memberContext[$memID];
	$context['can_view_warning'] = in_array('w', $context['admin_features']) && (allowedTo('issue_warning') && !$context['user']['is_owner']) || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $context['user']['is_owner']));

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?action=profile;u=' . $memID;

	// Are there things we don't show?
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	// See if they have broken any warning levels...
	list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);
	if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_muted'];
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_moderation'];
	elseif (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_watch'];

	// They haven't even been registered for a full day!?
	$days_registered = (int) ((time() - $user_profile[$memID]['date_registered']) / (3600 * 24));
	if (empty($user_profile[$memID]['date_registered']) || $days_registered < 1)
		$context['member']['posts_per_day'] = $txt['not_applicable'];
	else
		$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);

	// Set the age...
	if (empty($context['member']['birth_date']))
	{
		$context['member'] += array(
			'age' => $txt['not_applicable'],
			'today_is_birthday' => false
		);
	}
	else
	{
		list ($birth_year, $birth_month, $birth_day) = sscanf($context['member']['birth_date'], '%d-%d-%d');
		$datearray = getdate(forum_time());
		$context['member'] += array(
			'age' => $birth_year <= 4 ? $txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1),
			'today_is_birthday' => $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day
		);
	}

	if (allowedTo('moderate_forum'))
	{
		// Make sure it's a valid ip address; otherwise, don't bother...
		if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $memberContext[$memID]['ip']) == 1 && empty($modSettings['disableHostnameLookup']))
			$context['member']['hostname'] = host_from_ip($memberContext[$memID]['ip']);
		else
			$context['member']['hostname'] = '';

		$context['can_see_ip'] = true;
	}
	else
		$context['can_see_ip'] = false;

	if (!empty($modSettings['who_enabled']))
	{
		include_once($sourcedir . '/Who.php');
		$action = determineActions($user_profile[$memID]['url']);

		if ($action !== false)
			$context['member']['action'] = $action;
	}

	// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
	if ($context['member']['is_activated'] % 10 != 1 && allowedTo('moderate_forum'))
	{
		$context['activate_type'] = $context['member']['is_activated'];
		// What should the link text be?
		$context['activate_link_text'] = in_array($context['member']['is_activated'], array(3, 4, 5, 13, 14, 15)) ? $txt['account_approve'] : $txt['account_activate'];

		// Should we show a custom message?
		$context['activate_message'] = isset($txt['account_activate_method_' . $context['member']['is_activated'] % 10]) ? $txt['account_activate_method_' . $context['member']['is_activated'] % 10] : $txt['account_not_activated'];
	}

	// Is the signature even enabled on this forum?
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;

	// How about, are they banned?
	$context['member']['bans'] = array();
	if (allowedTo('moderate_forum'))
	{
		// Can they edit the ban?
		$context['can_edit_ban'] = allowedTo('manage_bans');

		$ban_query = array();
		$ban_query_vars = array(
			'time' => time(),
		);
		$ban_query[] = 'id_member = ' . $context['member']['id'];

		// Valid IP?
		if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $memberContext[$memID]['ip'], $ip_parts) == 1)
		{
			$ban_query[] = '((' . $ip_parts[1] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
						AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
						AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
						AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low4 AND bi.ip_high4))';

			// Do we have a hostname already?
			if (!empty($context['member']['hostname']))
			{
				$ban_query[] = '({string:hostname} LIKE hostname)';
				$ban_query_vars['hostname'] = $context['member']['hostname'];
			}
		}
		// Use '255.255.255.255' for 'unknown' - it's not valid anyway.
		elseif ($memberContext[$memID]['ip'] == 'unknown')
			$ban_query[] = '(bi.ip_low1 = 255 AND bi.ip_high1 = 255
						AND bi.ip_low2 = 255 AND bi.ip_high2 = 255
						AND bi.ip_low3 = 255 AND bi.ip_high3 = 255
						AND bi.ip_low4 = 255 AND bi.ip_high4 = 255)';

		// Check their email as well...
		if (strlen($context['member']['email']) != 0)
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $context['member']['email'];
		}

		// So... are they banned?  Dying to know!
		$request = $smcFunc['db_query']('', '
			SELECT bg.id_ban_group, bg.name, bg.cannot_access, bg.cannot_post, bg.cannot_register,
				bg.cannot_login, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:time}))
			WHERE (' . implode(' OR ', $ban_query) . ')',
			$ban_query_vars
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Work out what restrictions we actually have.
			$ban_restrictions = array();
			foreach (array('access', 'register', 'login', 'post') as $type)
				if ($row['cannot_' . $type])
					$ban_restrictions[] = $txt['ban_type_' . $type];

			// No actual ban in place?
			if (empty($ban_restrictions))
				continue;

			// Prepare the link for context.
			$ban_explanation = sprintf($txt['user_cannot_due_to'], implode(', ', $ban_restrictions), '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>');

			$context['member']['bans'][$row['id_ban_group']] = array(
				'reason' => empty($row['reason']) ? '' : '<br /><br /><strong>' . $txt['ban_reason'] . ':</strong> ' . $row['reason'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'register' => !empty($row['cannot_register']),
					'post' => !empty($row['cannot_post']),
					'login' => !empty($row['cannot_login']),
				),
				'explanation' => $ban_explanation,
			);
		}
		$smcFunc['db_free_result']($request);
	}

	loadCustomFields($memID);
}

// !!! This function needs to be split up properly.
// Show all posts by the current user
function showPosts($memID)
{
	global $txt, $user_info, $scripturl, $modSettings;
	global $context, $user_profile, $sourcedir, $smcFunc, $board;

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	$context['current_member'] = $memID;

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['showPosts'],
		'description' => $txt['showPosts_help'],
		'icon' => 'profile_sm.gif',
		'tabs' => array(
			'messages' => array(
			),
			'topics' => array(
			),
			'attach' => array(
			),
		),
	);

	// Set the page title
	$context['page_title'] = $txt['showPosts'] . ' - ' . $user_profile[$memID]['real_name'];

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_show_posts']) && $context['load_average'] >= $modSettings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// If we're specifically dealing with attachments use that function!
	if (isset($_GET['sa']) && $_GET['sa'] == 'attach')
		return showAttachments($memID);

	// Are we just viewing topics?
	$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// If just deleting a message, do it and then redirect back.
	if (isset($_GET['delete']) && !$context['is_topics'])
	{
		checkSession('get');

		// We need msg info for logging.
		$request = $smcFunc['db_query']('', '
			SELECT subject, id_member, id_topic, id_board
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => (int) $_GET['delete'],
			)
		);
		$info = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Trying to remove a message that doesn't exist.
		if (empty($info))
			redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);

		// We can be lazy, since removeMessage() will check the permissions for us.
		require_once($sourcedir . '/RemoveTopic.php');
		removeMessage((int) $_GET['delete']);

		// Add it to the mod log.
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
			logAction('delete', array('topic' => $info[2], 'subject' => $info[0], 'member' => $info[1], 'board' => $info[3]));

		// Back to... where we are now ;).
		redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);
	}

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	if ($context['is_topics'])
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
			WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);
	list ($msgCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($min_msg_member, $max_msg_member) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$reverse = false;
	$range_limit = '';
	$maxIndex = (int) $modSettings['defaultMaxMessages'];

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=showposts' . ($context['is_topics'] ? ';sa=topics' : '') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if ($context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if ($context['is_topics'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}

		// Make sure we quit this loop.
		if ($smcFunc['db_num_rows']($request) === $maxIndex || $looped)
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor....
		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
		);

		if ($user_info['id'] == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	$smcFunc['db_free_result']($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);

	// These are all the permissions that are different from board to board..
	if ($context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
				'delete_any' => 'can_delete',
			)
		);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = boardsAllowedTo($permission);

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}
}

// Show all the attachments of a user.
function showAttachments($memID)
{
	global $txt, $user_info, $scripturl, $modSettings, $board;
	global $context, $user_profile, $sourcedir, $smcFunc;

	// OBEY permissions!
	$boardsAllowed = boardsAllowedTo('view_attachments');
	// Make sure we can't actually see anything...
	if (empty($boardsAllowed))
		$boardsAllowed = array(-1);

	// Get the total number of attachments they have posted.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($attachCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$maxIndex = (int) $modSettings['defaultMaxMessages'];

	// What about ordering?
	$sortTypes = array(
		'filename' => 'a.filename',
		'downloads' => 'a.downloads',
		'subject' => 'm.subject',
		'posted' => 'm.poster_time',
	);
	$context['sort_order'] = isset($_GET['sort']) && isset($sortTypes[$_GET['sort']]) ? $_GET['sort'] : 'posted';
	$context['sort_direction'] = isset($_GET['asc']) ? 'up' : 'down';

	$sort = $sortTypes[$context['sort_order']];

	// Let's get ourselves a lovely page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=showposts;sa=attach;sort=' . $context['sort_order'] . ($context['sort_direction'] == 'up' ? ';asc' : ''), $context['start'], $attachCount, $maxIndex);

	// Retrieve some attachments.
	$request = $smcFunc['db_query']('', '
		SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, m.id_msg, m.id_topic,
			m.id_board, m.poster_time, m.subject, b.name
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
			'sort' => $sort . ' ' . ($context['sort_direction'] == 'down' ? 'DESC' : 'ASC'),
			'offset' => $context['start'],
			'limit' => $maxIndex,
		)
	);
	$context['attachments'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['subject'] = censorText($row['subject']);

		$context['attachments'][] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'downloads' => $row['downloads'],
			'subject' => $row['subject'],
			'posted' => timeformat($row['poster_time']),
			'msg' => $row['id_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'board_name' => $row['name'],
			'approved' => $row['approved'],
		);
	}
	$smcFunc['db_free_result']($request);
}

function statPanel($memID)
{
	global $txt, $scripturl, $context, $user_profile, $user_info, $modSettings, $smcFunc;

	$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $user_profile[$memID]['real_name'];

	// General user statistics.
	$timeDays = floor($user_profile[$memID]['total_time_logged_in'] / 86400);
	$timeHours = floor(($user_profile[$memID]['total_time_logged_in'] % 86400) / 3600);
	$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged2'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged3'] : '') . floor(($user_profile[$memID]['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged4'];
	$context['num_posts'] = comma_format($user_profile[$memID]['posts']);

	// Number of topics started.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : ''),
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list ($context['num_topics']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Number polls started.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : '') . '
			AND id_poll != {int:no_poll}',
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
			'no_poll' => 0,
		)
	);
	list ($context['num_polls']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Number polls voted in.
	$result = $smcFunc['db_query']('distinct_poll_votes', '
		SELECT COUNT(DISTINCT id_poll)
		FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $memID,
		)
	);
	list ($context['num_votes']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Format the numbers...
	$context['num_topics'] = comma_format($context['num_topics']);
	$context['num_polls'] = comma_format($context['num_polls']);
	$context['num_votes'] = comma_format($context['num_votes']);

	// Grab the board this member posted in most often.
	$result = $smcFunc['db_query']('', '
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
			'count_enabled' => 0,
		)
	);
	$context['popular_boards'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['popular_boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $user_profile[$memID]['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $user_profile[$memID]['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_member' => $user_profile[$memID]['posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Now get the 10 boards this user has most often participated in.
	$result = $smcFunc['db_query']('profile_board_stats', '
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
		)
	);
	$context['board_activity'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['board_activity'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Posting activity by time.
	$result = $smcFunc['db_query']('user_activity_by_time', '
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}' . ($modSettings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'current_member' => $memID,
			'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
			'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
		)
	);
	$maxPosts = $realPosts = 0;
	$context['posts_by_time'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		$context['posts_by_time'][$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	$smcFunc['db_free_result']($result);

	if ($maxPosts > 0)
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($context['posts_by_time'][$hour]))
				$context['posts_by_time'][$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
				$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
			}
		}

	// Put it in the right order.
	ksort($context['posts_by_time']);
}

function tracking($memID)
{
	global $sourcedir, $context, $txt, $scripturl, $modSettings, $user_profile;

	$subActions = array(
		'activity' => array('trackActivity', $txt['trackActivity']),
		'ip' => array('TrackIP', $txt['trackIP']),
		'edits' => array('trackEdits', $txt['trackEdits']),
	);

	$context['tracking_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'activity';

	if (isset($types[$context['tracking_area']][1]))
		require_once($sourcedir . '/' . $types[$context['tracking_area']][1]);

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['tracking'],
		'description' => $txt['tracking_description'],
		'icon' => 'profile_sm.gif',
		'tabs' => array(
			'activity' => array(),
			'ip' => array(),
			'edits' => array(),
		),
	);

	// Moderation must be on to track edits.
	if (empty($modSettings['modlog_enabled']))
		unset($context[$context['profile_menu_name']]['tab_data']['edits']);

	// Set a page title.
	$context['page_title'] = $txt['trackUser'] . ' - ' . $subActions[$context['tracking_area']][1] . ' - ' . $user_profile[$memID]['real_name'];

	// Pass on to the actual function.
	$context['sub_template'] = $subActions[$context['tracking_area']][0];
	$subActions[$context['tracking_area']][0]($memID);
}

function trackActivity($memID)
{
	global $scripturl, $txt, $modSettings, $sourcedir;
	global $user_profile, $context, $smcFunc;

	// Verify if the user has sufficient permissions.
	isAllowedTo('moderate_forum');

	$context['last_ip'] = $user_profile[$memID]['member_ip'];
	if ($context['last_ip'] != $user_profile[$memID]['member_ip2'])
		$context['last_ip2'] = $user_profile[$memID]['member_ip2'];
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => $txt['errors_by'] . ' ' . $context['member']['name'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_user'],
		'base_href' => $scripturl . '?action=profile;area=tracking;sa=user;u=' . $memID,
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;area=tracking;sa=ip;searchip=%1$s;u=' . $memID. '">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'le.ip',
					'reverse' => 'le.ip DESC',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br /><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the list for viewing.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// If this is a big forum, or a large posting user, let's limit the search.
	if ($modSettings['totalMessages'] > 50000 && $user_profile[$memID]['posts'] > 500)
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - $user_profile[$memID]['posts'] * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		$user_profile[$memID]['member_ip'],
		$user_profile[$memID]['member_ip2'],
	);

	// Get all IP addresses this user has used for his messages.
	$request = $smcFunc['db_query']('', '
		SELECT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}
		' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
		GROUP BY poster_ip',
		array(
			'current_member' => $memID,
			'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
			'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
		)
	);
	$context['ips'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['ips'][] = '<a href="' . $scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . $row['poster_ip'] . ';u=' . $memID . '">' . $row['poster_ip'] . '</a>';
		$ips[] = $row['poster_ip'];
	}
	$smcFunc['db_free_result']($request);

	// Now also get the IP addresses from the error messages.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS error_count, ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_member}
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	);
	$context['error_ips'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['error_ips'][] = '<a href="' . $scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . $row['ip'] . ';u=' . $memID . '">' . $row['ip'] . '</a>';
		$ips[] = $row['ip'];
	}
	$smcFunc['db_free_result']($request);

	// Find other users that might use the same IP.
	$ips = array_unique($ips);
	$context['members_in_range'] = array();
	if (!empty($ips))
	{
		// Get member ID's which are in messages...
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.poster_ip IN ({array_string:ip_list})
			GROUP BY mem.id_member
			HAVING mem.id_member != {int:current_member}',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		$message_members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$message_members[] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		// Fetch their names, cause of the GROUP BY doesn't like giving us that normally.
		if (!empty($message_members))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:message_members})',
				array(
					'message_members' => $message_members,
					'ip_list' => $ips,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			$smcFunc['db_free_result']($request);
		}

		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member != {int:current_member}
				AND member_ip IN ({array_string:ip_list})',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		$smcFunc['db_free_result']($request);
	}
}

function list_getUserErrorCount($where, $where_vars = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS error_count
		FROM {db_prefix}log_errors
		WHERE ' . $where,
		$where_vars
	);
	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $count;
}

function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $smcFunc, $txt, $scripturl;

	// Get a list of error messages from this ip (range).
	$request = $smcFunc['db_query']('', '
		SELECT
			le.log_time, le.ip, le.url, le.message, IFNULL(mem.id_member, 0) AS id_member,
			IFNULL(mem.real_name, {string:guest_title}) AS display_name, mem.member_name
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = le.id_member)
		WHERE ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
			'guest_title' => $txt['guest_title'],
		))
	);
	$error_messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$error_messages[] = array(
			'ip' => $row['ip'],
			'member_link' => $row['id_member'] > 0 ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>' : $row['display_name'],
			'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
			'url' => $row['url'],
			'time' => timeformat($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
		);
	$smcFunc['db_free_result']($request);

	return $error_messages;
}

function list_getIPMessageCount($where, $where_vars = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_see_board} AND ' . $where,
		$where_vars
	);
	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $count;
}

function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $smcFunc, $txt, $scripturl;

	// Get all the messages fitting this where clause.
	// !!!SLOW This query is using a filesort.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.poster_ip, IFNULL(mem.real_name, m.poster_name) AS display_name, mem.id_member,
			m.subject, m.poster_time, m.id_topic, m.id_board
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_board} AND ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
		))
	);
	$messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = array(
			'ip' => $row['poster_ip'],
			'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>',
			'board' => array(
				'id' => $row['id_board'],
				'href' => $scripturl . '?board=' . $row['id_board']
			),
			'topic' => $row['id_topic'],
			'id' => $row['id_msg'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time'])
		);
	$smcFunc['db_free_result']($request);

	return $messages;
}

function TrackIP($memID = 0)
{
	global $user_profile, $scripturl, $txt, $user_info, $modSettings, $sourcedir;
	global $context, $smcFunc;

	// Can the user do this?
	isAllowedTo('moderate_forum');

	if ($memID == 0)
	{
		$context['ip'] = $user_info['ip'];
		loadTemplate('Profile');
		loadLanguage('Profile');
		$context['sub_template'] = 'trackIP';
		$context['page_title'] = $txt['profile'];
		$context['base_url'] = $scripturl . '?action=trackip';
	}
	else
	{
		$context['ip'] = $user_profile[$memID]['member_ip'];
		$context['base_url'] = $scripturl . '?action=profile;area=tracking;sa=ip;u=' . $memID;
	}

	// Searching?
	if (isset($_REQUEST['searchip']))
		$context['ip'] = trim($_REQUEST['searchip']);

	if (preg_match('/^\d{1,3}\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$/', $context['ip']) == 0)
		fatal_lang_error('invalid_tracking_ip', false);

	$ip_var = str_replace('*', '%', $context['ip']);
	$ip_string = strpos($ip_var, '%') === false ? '= {string:ip_address}' : 'LIKE {string:ip_address}';

	if (empty($context['tracking_area']))
		$context['page_title'] = $txt['trackIP'] . ' - ' . $context['ip'];

	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name AS display_name, member_ip
		FROM {db_prefix}members
		WHERE member_ip ' . $ip_string,
		array(
			'ip_address' => $ip_var,
		)
	);
	$context['ips'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['ips'][$row['member_ip']][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
	$smcFunc['db_free_result']($request);

	ksort($context['ips']);

	// Gonna want this for the list.
	require_once($sourcedir . '/Subs-List.php');

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_message_list',
		'title' => $txt['messages_from_ip'] . ' ' . $context['ip'],
		'start_var_name' => 'messageStart',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_messages_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getIPMessages',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getIPMessageCount',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(m.poster_ip)',
					'reverse' => 'INET_ATON(m.poster_ip) DESC',
				),
			),
			'poster' => array(
				'header' => array(
					'value' => $txt['poster'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => $txt['subject'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?topic=%1$s.msg%2$s#msg%2$s" rel="nofollow">%3$s</a>',
						'params' => array(
							'topic' => false,
							'id' => false,
							'subject' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'm.id_msg DESC',
					'reverse' => 'm.id_msg',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['messages_from_ip_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the messages list.
	createList($listOptions);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => $txt['errors_from_ip'] . ' ' . $context['ip'],
		'start_var_name' => 'errorStart',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date2',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address2' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(le.ip)',
					'reverse' => 'INET_ATON(le.ip) DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['display_name'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br /><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date2' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_from_ip_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	$context['single_ip'] = strpos($context['ip'], '*') === false;
	if ($context['single_ip'])
	{
		$context['whois_servers'] = array(
			'afrinic' => array(
				'name' => $txt['whois_afrinic'],
				'url' => 'http://www.afrinic.net/cgi-bin/whois?searchtext=' . $context['ip'],
				'range' => array(41, 154, 196),
			),
			'apnic' => array(
				'name' => $txt['whois_apnic'],
				'url' => 'http://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . $context['ip'],
				'range' => array(58, 59, 60, 61, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124,
					125, 126, 133, 150, 153, 163, 171, 202, 203, 210, 211, 218, 219, 220, 221, 222),
			),
			'arin' => array(
				'name' => $txt['whois_arin'],
				'url' => 'http://whois.arin.net/rest/ip/' . $context['ip'],
				'range' => array(7, 24, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 96, 97, 98, 99,
					128, 129, 130, 131, 132, 134, 135, 136, 137, 138, 139, 140, 142, 143, 144, 146, 147, 148, 149,
					152, 155, 156, 157, 158, 159, 160, 161, 162, 164, 165, 166, 167, 168, 169, 170, 172, 173, 174,
					192, 198, 199, 204, 205, 206, 207, 208, 209, 216),
			),
			'lacnic' => array(
				'name' => $txt['whois_lacnic'],
				'url' => 'http://lacnic.net/cgi-bin/lacnic/whois?query=' . $context['ip'],
				'range' => array(186, 187, 189, 190, 191, 200, 201),
			),
			'ripe' => array(
				'name' => $txt['whois_ripe'],
				'url' => 'http://www.db.ripe.net/whois?searchtext=' . $context['ip'],
				'range' => array(62, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95,
					141, 145, 151, 188, 193, 194, 195, 212, 213, 217),
			),
		);

		foreach ($context['whois_servers'] as $whois)
		{
			// Strip off the "decimal point" and anything following...
			if (in_array((int) $context['ip'], $whois['range']))
				$context['auto_whois_server'] = $whois;
		}
	}
}

function trackEdits($memID)
{
	global $scripturl, $txt, $modSettings, $sourcedir, $context, $smcFunc;

	require_once($sourcedir . '/Subs-List.php');

	// Get the names of any custom fields.
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, bbc
		FROM {db_prefix}custom_fields',
		array(
		)
	);
	$context['custom_field_titles'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['custom_field_titles']['customfield_' . $row['col_name']] = array(
			'title' => $row['field_name'],
			'parse_bbc' => $row['bbc'],
		);
	$smcFunc['db_free_result']($request);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'edit_list',
		'title' => $txt['trackEdits'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['trackEdit_no_edits'],
		'base_href' => $scripturl . '?action=profile;area=tracking;sa=edits;u=' . $memID,
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getProfileEdits',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileEditCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['trackEdit_action'],
				),
				'data' => array(
					'db' => 'action_text',
				),
			),
			'before' => array(
				'header' => array(
					'value' => $txt['trackEdit_before'],
				),
				'data' => array(
					'db' => 'before',
				),
			),
			'after' => array(
				'header' => array(
					'value' => $txt['trackEdit_after'],
				),
				'data' => array(
					'db' => 'after',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'id_action DESC',
					'reverse' => 'id_action',
				),
			),
			'applicator' => array(
				'header' => array(
					'value' => $txt['trackEdit_applicator'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'edit_list';
}

// How many edits?
function list_getProfileEditCount($memID)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS edit_count
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}',
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	list ($edit_count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $edit_count;
}

function list_getProfileEdits($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $txt, $scripturl, $context;

	// Get a list of error messages from this ip (range).
	$request = $smcFunc['db_query']('', '
		SELECT
			id_action, id_member, ip, log_time, action, extra
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	$edits = array();
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$extra = @unserialize($row['extra']);
		if (!empty($extra['applicator']))
			$members[] = $extra['applicator'];

		// Work out what the name of the action is.
		if (isset($txt['trackEdit_action_' . $row['action']]))
			$action_text = $txt['trackEdit_action_' . $row['action']];
		elseif (isset($txt[$row['action']]))
			$action_text = $txt[$row['action']];
		// Custom field?
		elseif (isset($context['custom_field_titles'][$row['action']]))
			$action_text = $context['custom_field_titles'][$row['action']]['title'];
		else
			$action_text = $row['action'];

		// Parse BBC?
		$parse_bbc = isset($context['custom_field_titles'][$row['action']]) && $context['custom_field_titles'][$row['action']]['parse_bbc'] ? true : false;

		$edits[] = array(
			'id' => $row['id_action'],
			'ip' => $row['ip'],
			'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
			'member_link' => $txt['trackEdit_deleted_member'],
			'action' => $row['action'],
			'action_text' => $action_text,
			'before' => !empty($extra['previous']) ? ($parse_bbc ? parse_bbc($extra['previous']) : $extra['previous']) : '',
			'after' => !empty($extra['new']) ? ($parse_bbc ? parse_bbc($extra['new']) : $extra['new']) : '',
			'time' => timeformat($row['log_time']),
		);
	}
	$smcFunc['db_free_result']($request);

	// Get any member names.
	if (!empty($members))
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => $members,
			)
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[$row['id_member']] = $row['real_name'];
		$smcFunc['db_free_result']($request);

		foreach ($edits as $key => $value)
			if (isset($members[$value['id_member']]))
				$edits[$key]['member_link'] = '<a href="' . $scripturl . '?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
	}

	return $edits;
}

function showPermissions($memID)
{
	global $scripturl, $txt, $board, $modSettings;
	global $user_profile, $context, $user_info, $sourcedir, $smcFunc;

	// Verify if the user has sufficient permissions.
	isAllowedTo('manage_permissions');

	loadLanguage('ManagePermissions');
	loadLanguage('Admin');
	loadTemplate('ManageMembers');

	// Load all the permission profiles.
	require_once($sourcedir . '/ManagePermissions.php');
	loadPermissionProfiles();

	$context['member']['id'] = $memID;
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	$context['page_title'] = $txt['showPermissions'];
	$board = empty($board) ? 0 : (int) $board;
	$context['board'] = $board;

	// Determine which groups this user is in.
	if (empty($user_profile[$memID]['additional_groups']))
		$curGroups = array();
	else
		$curGroups = explode(',', $user_profile[$memID]['additional_groups']);
	$curGroups[] = $user_profile[$memID]['id_group'];
	$curGroups[] = $user_profile[$memID]['id_post_group'];

	// Load a list of boards for the jump box - except the defaults.
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name, b.id_profile, b.member_groups, IFNULL(mods.id_member, 0) AS is_mod
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE {query_see_board}',
		array(
			'current_member' => $memID,
		)
	);
	$context['boards'] = array();
	$context['no_access_boards'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
			$context['no_access_boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'is_last' => false,
			);
		elseif ($row['id_profile'] != 1 || $row['is_mod'])
			$context['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'selected' => $board == $row['id_board'],
				'profile' => $row['id_profile'],
				'profile_name' => $context['profiles'][$row['id_profile']]['name'],
			);
	}
	$smcFunc['db_free_result']($request);

	if (!empty($context['no_access_boards']))
		$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;

	$context['member']['permissions'] = array(
		'general' => array(),
		'board' => array()
	);

	// If you're an admin we know you can do everything, we might as well leave.
	$context['member']['has_all_permissions'] = in_array(1, $curGroups);
	if ($context['member']['has_all_permissions'])
		return;

	$denied = array();

	// Get all general permissions.
	$result = $smcFunc['db_query']('', '
		SELECT p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		if (empty($row['add_deny']))
			$denied[] = $row['permission'];

		// Permissions that end with _own or _any consist of two parts.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Add this permission if it doesn't exist yet.
		if (!isset($context['member']['permissions']['general'][$row['permission']]))
			$context['member']['permissions']['general'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => true,
			);

		// Add the membergroup to either the denied or the allowed groups.
		$context['member']['permissions']['general'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		// Once denied is always denied.
		$context['member']['permissions']['general'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$smcFunc['db_free_result']($result);

	$request = $smcFunc['db_query']('', '
		SELECT
			bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty($board) ? '' : ',
			b.id_profile, CASE WHEN mods.id_member IS NULL THEN 0 ELSE 1 END AS is_moderator') . '
		FROM {db_prefix}board_permissions AS bp' . (empty($board) ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
		WHERE bp.id_profile = {raw:current_profile}
			AND bp.id_group IN ({array_int:group_list}' . (empty($board) ? ')' : ', {int:moderator_group})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})'),
		array(
			'current_board' => $board,
			'group_list' => $curGroups,
			'current_member' => $memID,
			'current_profile' => empty($board) ? '1' : 'b.id_profile',
			'moderator_group' => 3,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Create the structure for this permission.
		if (!isset($context['member']['permissions']['board'][$row['permission']]))
			$context['member']['permissions']['board'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => empty($board),
			);

		$context['member']['permissions']['board'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		$context['member']['permissions']['board'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$smcFunc['db_free_result']($request);
}

// View a members warnings?
function viewWarning($memID)
{
	global $modSettings, $context, $sourcedir, $txt, $scripturl;

	// Firstly, can we actually even be here?
	if (!allowedTo('issue_warning') && (empty($modSettings['warning_show']) || ($modSettings['warning_show'] == 1 && !$context['user']['is_owner'])))
		fatal_lang_error('no_access', false);

	// Make sure things which are disabled stay disabled.
	$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
	$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
	$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

	// Let's use a generic list to get all the current warnings, and use the issue warnings grab-a-granny thing.
	require_once($sourcedir . '/Subs-List.php');
	require_once($sourcedir . '/Profile-Actions.php');

	$listOptions = array(
		'id' => 'view_warnings',
		'title' => $txt['profile_viewwarning_previous_warnings'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['profile_viewwarning_no_warnings'],
		'base_href' => $scripturl . '?action=profile;area=viewwarning;sa=user;u=' . $memID,
		'default_sort_col' => 'log_time',
		'get_items' => array(
			'function' => 'list_getUserWarnings',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getUserWarningCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'log_time' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_time'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_reason'],
					'style' => 'width: 50%',
				),
				'data' => array(
					'db' => 'reason',
				),
			),
			'level' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_level'],
				),
				'data' => array(
					'db' => 'counter',
				),
				'sort' => array(
					'default' => 'lc.counter DESC',
					'reverse' => 'lc.counter',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['profile_viewwarning_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the list for viewing.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// Create some common text bits for the template.
	$context['level_effects'] = array(
		0 => '',
		$modSettings['warning_watch'] => $txt['profile_warning_effect_own_watched'],
		$modSettings['warning_moderate'] => $txt['profile_warning_effect_own_moderated'],
		$modSettings['warning_mute'] => $txt['profile_warning_effect_own_muted'],
	);
	$context['current_level'] = 0;
	foreach ($context['level_effects'] as $limit => $dummy)
		if ($context['member']['warning'] >= $limit)
			$context['current_level'] = $limit;
}

?>