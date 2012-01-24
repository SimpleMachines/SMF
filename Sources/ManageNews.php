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
	void ManageNews()
		- the entrance point for all News and Newsletter screens.
		- called by ?action=admin;area=news.
		- does the permission checks.
		- calls the appropriate function based on the requested sub-action.

	void EditNews()
		- changes the current news items for the forum.
		- uses the ManageNews template and edit_news sub template.
		- called by ?action=admin;area=news.
		- requires the edit_news permission.
		- writes an entry into the moderation log.
		- uses the edit_news administration area.
		- can be accessed with ?action=admin;sa=editnews.

	void SelectMailingMembers()
		- allows a user to select the membergroups to send their mailing to.
		- uses the ManageNews template and email_members sub template.
		- called by ?action=admin;area=news;sa=mailingmembers.
		- requires the send_mail permission.
		- form is submitted to ?action=admin;area=news;mailingcompose.

	void ComposeMailing()
		- shows a form to edit a forum mailing and its recipients.
		- uses the ManageNews template and email_members_compose sub template.
		- called by ?action=admin;area=news;sa=mailingcompose.
		- requires the send_mail permission.
		- form is submitted to ?action=admin;area=news;sa=mailingsend.

	void SendMailing(bool clean_only = false)
		- handles the sending of the forum mailing in batches.
		- uses the ManageNews template and email_members_send sub template.
		- called by ?action=admin;area=news;sa=mailingsend
		- requires the send_mail permission.
		- redirects to itself when more batches need to be sent.
		- redirects to ?action=admin after everything has been sent.
		- if clean_only is set will only clean the variables, put them in context, then return.

	void NewsSettings()
		- set general news and newsletter settings and permissions.
		- uses the ManageNews template and news_settings sub template.
		- called by ?action=admin;area=news;sa=settings.
		- requires the forum_admin permission.
*/

// The controller; doesn't do anything, just delegates.
function ManageNews()
{
	global $context, $txt, $scripturl;

	// First, let's do a quick permissions check for the best error message possible.
	isAllowedTo(array('edit_news', 'send_mail', 'admin_forum'));

	loadTemplate('ManageNews');

	// Format: 'sub-action' => array('function', 'permission')
	$subActions = array(
		'editnews' => array('EditNews', 'edit_news'),
		'mailingmembers' => array('SelectMailingMembers', 'send_mail'),
		'mailingcompose' => array('ComposeMailing', 'send_mail'),
		'mailingsend' => array('SendMailing', 'send_mail'),
		'settings' => array('ModifyNewsSettings', 'admin_forum'),
	);

	// Default to sub action 'main' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('edit_news') ? 'editnews' : (allowedTo('send_mail') ? 'mailingmembers' : 'settings'));

	// Have you got the proper permissions?
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['news_title'],
		'help' => 'edit_news',
		'description' => $txt['admin_news_desc'],
		'tabs' => array(
			'editnews' => array(
			),
			'mailingmembers' => array(
				'description' => $txt['news_mailing_desc'],
			),
			'settings' => array(
				'description' => $txt['news_settings_desc'],
			),
		),
	);

	// Force the right area...
	if (substr($_REQUEST['sa'], 0, 7) == 'mailing')
		$context[$context['admin_menu_name']]['current_subsection'] = 'mailingmembers';

	$subActions[$_REQUEST['sa']][0]();
}

// Let the administrator(s) edit the news.
function EditNews()
{
	global $txt, $modSettings, $context, $sourcedir, $user_info;
	global $smcFunc;

	require_once($sourcedir . '/Subs-Post.php');

	// The 'remove selected' button was pressed.
	if (!empty($_POST['delete_selection']) && !empty($_POST['remove']))
	{
		checkSession();

		// Store the news temporarily in this array.
		$temp_news = explode("\n", $modSettings['news']);

		// Remove the items that were selected.
		foreach ($temp_news as $i => $news)
			if (in_array($i, $_POST['remove']))
				unset($temp_news[$i]);

		// Update the database.
		updateSettings(array('news' => implode("\n", $temp_news)));

		logAction('news');
	}
	// The 'Save' button was pressed.
	elseif (!empty($_POST['save_items']))
	{
		checkSession();

		foreach ($_POST['news'] as $i => $news)
		{
			if (trim($news) == '')
				unset($_POST['news'][$i]);
			else
			{
				$_POST['news'][$i] = $smcFunc['htmlspecialchars']($_POST['news'][$i], ENT_QUOTES);
				preparsecode($_POST['news'][$i]);
			}
		}

		// Send the new news to the database.
		updateSettings(array('news' => implode("\n", $_POST['news'])));

		// Log this into the moderation log.
		logAction('news');
	}

	// Ready the current news.
	foreach (explode("\n", $modSettings['news']) as $id => $line)
		$context['admin_current_news'][$id] = array(
			'id' => $id,
			'unparsed' => un_preparsecode($line),
			'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc($line)),
		);

	$context['sub_template'] = 'edit_news';
	$context['page_title'] = $txt['admin_edit_news'];
}

function SelectMailingMembers()
{
	global $txt, $context, $modSettings, $smcFunc;

	$context['page_title'] = $txt['admin_newsletters'];

	$context['sub_template'] = 'email_members';

	$context['groups'] = array();
	$postGroups = array();
	$normalGroups = array();

	// If we have post groups disabled then we need to give a "ungrouped members" option.
	if (empty($modSettings['permission_enable_postgroups']))
	{
		$context['groups'][0] = array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'member_count' => 0,
		);
		$normalGroups[0] = 0;
	}

	// Get all the extra groups as well as Administrator and Global Moderator.
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.min_posts
		FROM {db_prefix}membergroups AS mg' . (empty($modSettings['permission_enable_postgroups']) ? '
		WHERE mg.min_posts = {int:min_posts}' : '') . '
		GROUP BY mg.id_group, mg.min_posts, mg.group_name
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'min_posts' => -1,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'member_count' => 0,
		);

		if ($row['min_posts'] == -1)
			$normalGroups[$row['id_group']] = $row['id_group'];
		else
			$postGroups[$row['id_group']] = $row['id_group'];
	}
	$smcFunc['db_free_result']($request);

	// If we have post groups, let's count the number of members...
	if (!empty($postGroups))
	{
		$query = $smcFunc['db_query']('', '
			SELECT mem.id_post_group AS id_group, COUNT(*) AS member_count
			FROM {db_prefix}members AS mem
			WHERE mem.id_post_group IN ({array_int:post_group_list})
			GROUP BY mem.id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		$smcFunc['db_free_result']($query);
	}

	if (!empty($normalGroups))
	{
		// Find people who are members of this group...
		$query = $smcFunc['db_query']('', '
			SELECT id_group, COUNT(*) AS member_count
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		$smcFunc['db_free_result']($query);

		// Also do those who have it as an additional membergroup - this ones more yucky...
		$query = $smcFunc['db_query']('', '
			SELECT mg.id_group, COUNT(*) AS member_count
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		$smcFunc['db_free_result']($query);
	}

	// Any moderators?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT id_member) AS num_distinct_mods
		FROM {db_prefix}moderators
		LIMIT 1',
		array(
		)
	);
	list ($context['groups'][3]['member_count']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['can_send_pm'] = allowedTo('pm_send');
}

// Email your members...
function ComposeMailing()
{
	global $txt, $sourcedir, $context, $smcFunc;

	// Start by finding any members!
	$toClean = array();
	if (!empty($_POST['members']))
		$toClean[] = 'members';
	if (!empty($_POST['exclude_members']))
		$toClean[] = 'exclude_members';
	if (!empty($toClean))
	{
		require_once($sourcedir . '/Subs-Auth.php');
		foreach ($toClean as $type)
		{
			// Remove the quotes.
			$_POST[$type] = strtr($_POST[$type], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $_POST[$type], $matches);
			$_POST[$type] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST[$type]))));

			foreach ($_POST[$type] as $index => $member)
				if (strlen(trim($member)) > 0)
					$_POST[$type][$index] = $smcFunc['htmlspecialchars']($smcFunc['strtolower'](trim($member)));
				else
					unset($_POST[$type][$index]);

			// Find the members
			$_POST[$type] = implode(',', array_keys(findMembers($_POST[$type])));
		}
	}

	if (isset($_POST['member_list']) && is_array($_POST['member_list']))
	{
		$members = array();
		foreach ($_POST['member_list'] as $member_id)
			$members[] = (int) $member_id;
		$_POST['members'] = implode(',', $members);
	}

	if (isset($_POST['exclude_member_list']) && is_array($_POST['exclude_member_list']))
	{
		$members = array();
		foreach ($_POST['exclude_member_list'] as $member_id)
			$members[] = (int) $member_id;
		$_POST['exclude_members'] = implode(',', $members);
	}

	// Clean the other vars.
	SendMailing(true);

	// We need a couple strings from the email template file
	loadLanguage('EmailTemplates');

	// Get a list of all full banned users.  Use their Username and email to find them.  Only get the ones that can't login to turn off notification.
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT mem.id_member
		FROM {db_prefix}ban_groups AS bg
			INNER JOIN {db_prefix}ban_items AS bi ON (bg.id_ban_group = bi.id_ban_group)
			INNER JOIN {db_prefix}members AS mem ON (bi.id_member = mem.id_member)
		WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
		array(
			'cannot_access' => 1,
			'cannot_login' => 1,
			'current_time' => time(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['recipients']['exclude_members'][] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT bi.email_address
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
			AND (COALESCE(bg.expire_time, 1=1) OR bg.expire_time > {int:current_time})
			AND bi.email_address != {string:blank_string}',
		array(
			'cannot_access' => 1,
			'cannot_login' => 1,
			'current_time' => time(),
			'blank_string' => '',
		)
	);
	$condition_array = array();
	$condition_array_params = array();
	$count = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$condition_array[] = '{string:email_' . $count . '}';
		$condition_array_params['email_' . $count++] = $row['email_address'];
	}

	if (!empty($condition_array))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address IN(' . implode(', ', $condition_array) .')',
			$condition_array_params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['recipients']['exclude_members'][] = $row['id_member'];
	}

	// Did they select moderators - if so add them as specific members...
	if ((!empty($context['recipients']['groups']) && in_array(3, $context['recipients']['groups'])) || (!empty($context['recipients']['exclude_groups']) && in_array(3, $context['recipients']['exclude_groups'])))
	{
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT mem.id_member AS identifier
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member)
			WHERE mem.is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (in_array(3, $context['recipients']))
				$context['recipients']['exclude_members'][] = $row['identifier'];
			else
				$context['recipients']['members'][] = $row['identifier'];
		}
		$smcFunc['db_free_result']($request);
	}

	// For progress bar!
	$context['total_emails'] = count($context['recipients']['emails']);
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_member)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($context['max_id_member']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Clean up the arrays.
	$context['recipients']['members'] = array_unique($context['recipients']['members']);
	$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);

	// Setup the template!
	$context['page_title'] = $txt['admin_newsletters'];
	$context['sub_template'] = 'email_members_compose';

	$context['default_subject'] = htmlspecialchars($context['forum_name'] . ': ' . $txt['subject']);
	$context['default_message'] = htmlspecialchars($txt['message'] . "\n\n" . $txt['regards_team'] . "\n\n" . '{$board_url}');
}

// Send out the mailing!
function SendMailing($clean_only = false)
{
	global $txt, $sourcedir, $context, $smcFunc;
	global $scripturl, $modSettings, $user_info;

	// How many to send at once? Quantity depends on whether we are queueing or not.
	$num_at_once = empty($modSettings['mail_queue']) ? 60 : 1000;

	// If by PM's I suggest we half the above number.
	if (!empty($_POST['send_pm']))
		$num_at_once /= 2;

	checkSession();

	// Where are we actually to?
	$context['start'] = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;
	$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
	$context['max_id_member'] = !empty($_POST['max_id_member']) ? (int) $_POST['max_id_member'] : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';
	$context['parse_html'] = !empty($_POST['parse_html']) ? '1' : '0';

	// Create our main context.
	$context['recipients'] = array(
		'groups' => array(),
		'exclude_groups' => array(),
		'members' => array(),
		'exclude_members' => array(),
		'emails' => array(),
	);

	// Have we any excluded members?
	if (!empty($_POST['exclude_members']))
	{
		$members = explode(',', $_POST['exclude_members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['exclude_members'][] = (int) $member;
	}

	// What about members we *must* do?
	if (!empty($_POST['members']))
	{
		$members = explode(',', $_POST['members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['members'][] = (int) $member;
	}
	// Cleaning groups is simple - although deal with both checkbox and commas.
	if (!empty($_POST['groups']))
	{
		if (is_array($_POST['groups']))
		{
			foreach ($_POST['groups'] as $group => $dummy)
				$context['recipients']['groups'][] = (int) $group;
		}
		else
		{
			$groups = explode(',', $_POST['groups']);
			foreach ($groups as $group)
				$context['recipients']['groups'][] = (int) $group;
		}
	}
	// Same for excluded groups
	if (!empty($_POST['exclude_groups']))
	{
		if (is_array($_POST['exclude_groups']))
		{
			foreach ($_POST['exclude_groups'] as $group => $dummy)
				$context['recipients']['exclude_groups'][] = (int) $group;
		}
		else
		{
			$groups = explode(',', $_POST['exclude_groups']);
			foreach ($groups as $group)
				$context['recipients']['exclude_groups'][] = (int) $group;
		}
	}
	// Finally - emails!
	if (!empty($_POST['emails']))
	{
		$addressed = array_unique(explode(';', strtr($_POST['emails'], array("\n" => ';', "\r" => ';', ',' => ';'))));
		foreach ($addressed as $curmem)
		{
			$curmem = trim($curmem);
			if ($curmem != '')
				$context['recipients']['emails'][$curmem] = $curmem;
		}
	}

	// If we're only cleaning drop out here.
	if ($clean_only)
		return;

	require_once($sourcedir . '/Subs-Post.php');

	// Save the message and its subject in $context
	$context['subject'] = htmlspecialchars($_POST['subject']);
	$context['message'] = htmlspecialchars($_POST['message']);

	// Prepare the message for sending it as HTML
	if (!$context['send_pm'] && !empty($_POST['send_html']))
	{
		// Prepare the message for HTML.
		if (!empty($_POST['parse_html']))
			$_POST['message'] = str_replace(array("\n", '  '), array('<br />' . "\n", '&nbsp; '), $_POST['message']);

		// This is here to prevent spam filters from tagging this as spam.
		if (preg_match('~\<html~i', $_POST['message']) == 0)
		{
			if (preg_match('~\<body~i', $_POST['message']) == 0)
				$_POST['message'] = '<html><head><title>' . $_POST['subject'] . '</title></head>' . "\n" . '<body>' . $_POST['message'] . '</body></html>';
			else
				$_POST['message'] = '<html>' . $_POST['message'] . '</html>';
		}
	}

	// Use the default time format.
	$user_info['time_format'] = $modSettings['time_format'];

	$variables = array(
		'{$board_url}',
		'{$current_time}',
		'{$latest_member.link}',
		'{$latest_member.id}',
		'{$latest_member.name}'
	);

	// We might need this in a bit
	$cleanLatestMember = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($modSettings['latestRealName']) : $modSettings['latestRealName'];

	// Replace in all the standard things.
	$_POST['message'] = str_replace($variables,
		array(
			!empty($_POST['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
			timeformat(forum_time(), false),
			!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
			$modSettings['latestMember'],
			$cleanLatestMember
		), $_POST['message']);
	$_POST['subject'] = str_replace($variables,
		array(
			$scripturl,
			timeformat(forum_time(), false),
			$modSettings['latestRealName'],
			$modSettings['latestMember'],
			$modSettings['latestRealName']
		), $_POST['subject']);

	$from_member = array(
		'{$member.email}',
		'{$member.link}',
		'{$member.id}',
		'{$member.name}'
	);

	// If we still have emails, do them first!
	$i = 0;
	foreach ($context['recipients']['emails'] as $k => $email)
	{
		// Done as many as we can?
		if ($i >= $num_at_once)
			break;

		// Don't sent it twice!
		unset($context['recipients']['emails'][$k]);

		// Dammit - can't PM emails!
		if ($context['send_pm'])
			continue;

		$to_member = array(
			$email,
			!empty($_POST['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
			'??',
			$email
		);

		sendmail($email, str_replace($from_member, $to_member, $_POST['subject']), str_replace($from_member, $to_member, $_POST['message']), null, null, !empty($_POST['send_html']), 5);

		// Done another...
		$i++;
	}

	// Got some more to send this batch?
	$last_id_member = 0;
	if ($i < $num_at_once)
	{
		// Need to build quite a query!
		$sendQuery = '(';
		$sendParams = array();
		if (!empty($context['recipients']['groups']))
		{
			// Take the long route...
			$queryBuild = array();
			foreach ($context['recipients']['groups'] as $group)
			{
				$sendParams['group_' . $group] = $group;
				$queryBuild[] = 'mem.id_group = {int:group_' . $group . '}';
				if (!empty($group))
				{
					$queryBuild[] = 'FIND_IN_SET({int:group_' . $group . '}, mem.additional_groups) != 0';
					$queryBuild[] = 'mem.id_post_group = {int:group_' . $group . '}';
				}
			}
			if (!empty($queryBuild))
			$sendQuery .= implode(' OR ', $queryBuild);
		}
		if (!empty($context['recipients']['members']))
		{
			$sendQuery .= ($sendQuery == '(' ? '' : ' OR ') . 'mem.id_member IN ({array_int:members})';
			$sendParams['members'] = $context['recipients']['members'];
		}

		$sendQuery .= ')';

		// If we've not got a query then we must be done!
		if ($sendQuery == '()')
			redirectexit('action=admin');

		// Anything to exclude?
		if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
			$sendQuery .= ' AND mem.id_group != {int:regular_group}';
		if (!empty($context['recipients']['exclude_members']))
		{
			$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
			$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
		}

		// Force them to have it?
		if (empty($context['email_force']))
			$sendQuery .= ' AND mem.notify_announcements = {int:notify_announcements}';

		// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
		$result = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.email_address, mem.real_name, mem.id_group, mem.additional_groups, mem.id_post_group
			FROM {db_prefix}members AS mem
			WHERE mem.id_member > {int:min_id_member}
				AND mem.id_member < {int:max_id_member}
				AND ' . $sendQuery . '
				AND mem.is_activated = {int:is_activated}
			ORDER BY mem.id_member ASC
			LIMIT {int:atonce}',
			array_merge($sendParams, array(
				'min_id_member' => $context['start'],
				'max_id_member' => $context['start'] + $num_at_once - $i,
				'atonce' => $num_at_once - $i,
				'regular_group' => 0,
				'notify_announcements' => 1,
				'is_activated' => 1,
			))
		);

		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$last_id_member = $row['id_member'];

			// What groups are we looking at here?
			if (empty($row['additional_groups']))
				$groups = array($row['id_group'], $row['id_post_group']);
			else
				$groups = array_merge(
					array($row['id_group'], $row['id_post_group']),
					explode(',', $row['additional_groups'])
				);

			// Excluded groups?
			if (array_intersect($groups, $context['recipients']['exclude_groups']))
				continue;

			// We might need this
			$cleanMemberName = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($row['real_name']) : $row['real_name'];

			// Replace the member-dependant variables
			$message = str_replace($from_member,
				array(
					$row['email_address'],
					!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $cleanMemberName . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $row['id_member'] . ']' . $cleanMemberName . '[/url]' : $cleanMemberName),
					$row['id_member'],
					$cleanMemberName,
				), $_POST['message']);

			$subject = str_replace($from_member,
				array(
					$row['email_address'],
					$row['real_name'],
					$row['id_member'],
					$row['real_name'],
				), $_POST['subject']);

			// Send the actual email - or a PM!
			if (!$context['send_pm'])
				sendmail($row['email_address'], $subject, $message, null, null, !empty($_POST['send_html']), 5);
			else
				sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message);
		}
		$smcFunc['db_free_result']($result);
	}

	// If used our batch assume we still have a member.
	if ($i >= $num_at_once)
		$last_id_member = $context['start'];
	// Or we didn't have one in range?
	elseif (empty($last_id_member) && $context['start'] + $num_at_once < $context['max_id_member'])
		$last_id_member = $context['start'] + $num_at_once;
	// If we have no id_member then we're done.
	elseif (empty($last_id_member) && empty($context['recipients']['emails']))
	{
		// Log this into the admin log.
		logAction('newsletter', array(), 'admin');

		redirectexit('action=admin');
	}

	$context['start'] = $last_id_member;

	// Working out progress is a black art of sorts.
	$percentEmails = $context['total_emails'] == 0 ? 0 : ((count($context['recipients']['emails']) / $context['total_emails']) * ($context['total_emails'] / ($context['total_emails'] + $context['max_id_member'])));
	$percentMembers = ($context['start'] / $context['max_id_member']) * ($context['max_id_member'] / ($context['total_emails'] + $context['max_id_member']));
	$context['percentage_done'] = round(($percentEmails + $percentMembers) * 100, 2);

	$context['page_title'] = $txt['admin_newsletters'];
	$context['sub_template'] = 'email_members_send';
}

function ModifyNewsSettings($return_config = false)
{
	global $context, $sourcedir, $modSettings, $txt, $scripturl;

	$config_vars = array(
		array('title', 'settings'),
			// Inline permissions.
			array('permissions', 'edit_news', 'help' => ''),
			array('permissions', 'send_mail'),
		'',
			// Just the remaining settings.
			array('check', 'xmlnews_enable', 'onclick' => 'document.getElementById(\'xmlnews_maxlen\').disabled = !this.checked;'),
			array('text', 'xmlnews_maxlen', 10),
	);

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['admin_edit_news'] . ' - ' . $txt['settings'];
	$context['sub_template'] = 'show_settings';

	// Needed for the inline permission functions, and the settings template.
	require_once($sourcedir . '/ManagePermissions.php');
	require_once($sourcedir . '/ManageServer.php');

	// Wrap it all up nice and warm...
	$context['post_url'] = $scripturl . '?action=admin;area=news;save;sa=settings';
	$context['permissions_excluded'] = array(-1);

	// Add some javascript at the bottom...
	$context['settings_insert_below'] = '
		<script type="text/javascript"><!-- // --><![CDATA[
			document.getElementById("xmlnews_maxlen").disabled = !document.getElementById("xmlnews_enable").checked;
		// ]]></script>';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=news;sa=settings');
	}

	prepareDBSettingContext($config_vars);
}

?>