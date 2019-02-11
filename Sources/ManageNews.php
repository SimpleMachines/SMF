<?php

/**
 * This file manages... the news. :P
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The news dispatcher; doesn't do anything, just delegates.
 * This is the entrance point for all News and Newsletter screens.
 * Called by ?action=admin;area=news.
 * It does the permission checks, and calls the appropriate function
 * based on the requested sub-action.
 */
function ManageNews()
{
	global $context, $txt;

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

	call_integration_hook('integrate_manage_news', array(&$subActions));

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

	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Let the administrator(s) edit the news items for the forum.
 * It writes an entry into the moderation log.
 * This function uses the edit_news administration area.
 * Called by ?action=admin;area=news.
 * Requires the edit_news permission.
 * Can be accessed with ?action=admin;sa=editnews.
 *
 * @uses ManageNews template, edit_news sub template.
 */
function EditNews()
{
	global $txt, $modSettings, $context, $sourcedir, $scripturl;
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

		$context['saved_successful'] = true;

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

		$context['saved_successful'] = true;

		// Log this into the moderation log.
		logAction('news');
	}

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$context['page_title'] = $txt['admin_edit_news'];

	// Use the standard templates for showing this.
	$listOptions = array(
		'id' => 'news_lists',
		'get_items' => array(
			'function' => 'list_getNews',
		),
		'columns' => array(
			'news' => array(
				'header' => array(
					'value' => $txt['admin_edit_news'],
					'class' => 'half_table',
				),
				'data' => array(
					'function' => function($news)
					{
						if (is_numeric($news['id']))
							return '
								<textarea id="data_' . $news['id'] . '" rows="3" cols="50" name="news[]" class="padding block">' . $news['unparsed'] . '</textarea>
								<div class="floatleft" id="preview_' . $news['id'] . '"></div>';
						else
							return $news['unparsed'];
					},
					'class' => 'half_table',
				),
			),
			'preview' => array(
				'header' => array(
					'value' => $txt['preview'],
					'class' => 'half_table',
				),
				'data' => array(
					'function' => function($news)
					{
						return '<div id="box_preview_' . $news['id'] . '" style="overflow: auto; width: 100%; height: 10ex;">' . $news['parsed'] . '</div>';
					},
					'class' => 'half_table',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol icon',
				),
				'data' => array(
					'function' => function($news)
					{
						if (is_numeric($news['id']))
							return '<input type="checkbox" name="remove[]" value="' . $news['id'] . '">';
						else
							return '';
					},
					'class' => 'centercol icon',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=news;sa=editnews',
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
				<span id="moreNewsItems_link" class="floatleft" style="display: none;">
					<a class="button" href="javascript:void(0);" onclick="addNewsItem(); return false;">' . $txt['editnews_clickadd'] . '</a>
				</span>
				<input type="submit" name="save_items" value="' . $txt['save'] . '" class="button">
				<input type="submit" name="delete_selection" value="' . $txt['editnews_remove_selected'] . '" data-confirm="' . $txt['editnews_remove_confirm'] . '" class="button you_sure">',
			),
		),
		'javascript' => '
					document.getElementById(\'list_news_lists_last\').style.display = "none";
					document.getElementById("moreNewsItems_link").style.display = "";
					var last_preview = 0;

					$(document).ready(function () {
						$("div[id ^= \'preview_\']").each(function () {
							var preview_id = $(this).attr(\'id\').split(\'_\')[1];
							if (last_preview < preview_id)
								last_preview = preview_id;
							make_preview_btn(preview_id);
						});
					});

					function make_preview_btn (preview_id)
					{
						$("#preview_" + preview_id).addClass("button");
						$("#preview_" + preview_id).text(\'' . $txt['preview'] . '\').click(function () {
							$.ajax({
								type: "POST",
								url: "' . $scripturl . '?action=xmlhttp;sa=previews;xml",
								data: {item: "newspreview", news: $("#data_" + preview_id).val()},
								context: document.body,
								success: function(request){
									if ($(request).find("error").text() == \'\')
										$(document).find("#box_preview_" + preview_id).html($(request).text());
									else
										$(document).find("#box_preview_" + preview_id).text(\'' . $txt['news_error_no_news'] . '\');
								},
							});
						});
					}

					function addNewsItem ()
					{
						last_preview++;
						$("#list_news_lists_last").before(' . javaScriptEscape('
						<tr class="windowbg') . ' + (last_preview % 2 == 0 ? \'\' : \'2\') + ' . javaScriptEscape('">
							<td style="width: 50%;">
									<textarea id="data_') . ' + last_preview + ' . javaScriptEscape('" rows="3" cols="65" name="news[]" style="width: 95%;"></textarea>
									<br>
									<div class="floatleft" id="preview_') . ' + last_preview + ' . javaScriptEscape('"></div>
							</td>
							<td style="width: 45%;">
								<div id="box_preview_') . ' + last_preview + ' . javaScriptEscape('" style="overflow: auto; width: 100%; height: 10ex;"></div>
							</td>
							<td></td>
						</tr>') . ');
						make_preview_btn(last_preview);
					}',
	);

	// Create the request list.
	createList($listOptions);

	// And go!
	loadTemplate('ManageNews');
	$context['sub_template'] = 'news_lists';
}

/**
 * Prepares an array of the forum news items for display in the template
 *
 * @return array An array of information about the news items
 */
function list_getNews()
{
	global $modSettings;

	$admin_current_news = array();
	// Ready the current news.
	foreach (explode("\n", $modSettings['news']) as $id => $line)
		$admin_current_news[$id] = array(
			'id' => $id,
			'unparsed' => un_preparsecode($line),
			'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc($line)),
		);

	$admin_current_news['last'] = array(
		'id' => 'last',
		'unparsed' => '<div id="moreNewsItems"></div>
		<noscript><textarea rows="3" cols="65" name="news[]" style="width: 85%;"></textarea></noscript>',
		'parsed' => '<div id="moreNewsItems_preview"></div>',
	);

	return $admin_current_news;
}

/**
 * This function allows a user to select the membergroups to send their
 * mailing to.
 * Called by ?action=admin;area=news;sa=mailingmembers.
 * Requires the send_mail permission.
 * Form is submitted to ?action=admin;area=news;mailingcompose.
 *
 * @uses the ManageNews template and email_members sub template.
 */
function SelectMailingMembers()
{
	global $txt, $context, $modSettings, $smcFunc;

	// Is there any confirm message?
	$context['newsletter_sent'] = isset($_SESSION['newsletter_sent']) ? $_SESSION['newsletter_sent'] : '';

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

	loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
}

/**
 * Prepare subject and message of an email for the preview box
 * Used in ComposeMailing and RetrievePreview (Xml.php)
 */
function prepareMailingForPreview()
{
	global $context, $modSettings, $scripturl, $user_info, $txt;
	loadLanguage('Errors');

	$processing = array('preview_subject' => 'subject', 'preview_message' => 'message');

	// Use the default time format.
	$user_info['time_format'] = $modSettings['time_format'];

	$variables = array(
		'{$board_url}',
		'{$current_time}',
		'{$latest_member.link}',
		'{$latest_member.id}',
		'{$latest_member.name}'
	);

	$html = $context['send_html'];

	// We might need this in a bit
	$cleanLatestMember = empty($context['send_html']) || $context['send_pm'] ? un_htmlspecialchars($modSettings['latestRealName']) : $modSettings['latestRealName'];

	foreach ($processing as $key => $post)
	{
		$context[$key] = !empty($_REQUEST[$post]) ? $_REQUEST[$post] : '';

		if (empty($context[$key]) && empty($_REQUEST['xml']))
			$context['post_error']['messages'][] = $txt['error_no_' . $post];
		elseif (!empty($_REQUEST['xml']))
			continue;

		preparsecode($context[$key]);
		if ($html)
		{
			$enablePostHTML = $modSettings['enablePostHTML'];
			$modSettings['enablePostHTML'] = $context['send_html'];
			$context[$key] = parse_bbc($context[$key]);
			$modSettings['enablePostHTML'] = $enablePostHTML;
		}

		// Replace in all the standard things.
		$context[$key] = str_replace($variables,
			array(
				!empty($context['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
				timeformat(forum_time(), false),
				!empty($context['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
				$modSettings['latestMember'],
				$cleanLatestMember
			), $context[$key]);
	}
}

/**
 * Shows a form to edit a forum mailing and its recipients.
 * Called by ?action=admin;area=news;sa=mailingcompose.
 * Requires the send_mail permission.
 * Form is submitted to ?action=admin;area=news;sa=mailingsend.
 *
 * @uses ManageNews template, email_members_compose sub-template.
 */
function ComposeMailing()
{
	global $txt, $sourcedir, $context, $smcFunc;

	// Setup the template!
	$context['page_title'] = $txt['admin_newsletters'];
	$context['sub_template'] = 'email_members_compose';

	$context['subject'] = !empty($_POST['subject']) ? $_POST['subject'] : $smcFunc['htmlspecialchars']($context['forum_name'] . ': ' . $txt['subject']);
	$context['message'] = !empty($_POST['message']) ? $_POST['message'] : $smcFunc['htmlspecialchars']($txt['message'] . "\n\n" . $txt['regards_team'] . "\n\n" . '{$board_url}');

	// Needed for the WYSIWYG editor.
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => $context['message'],
		'height' => '250px',
		'width' => '100%',
		'labels' => array(
			'post_button' => $txt['sendtopic_send'],
		),
		'preview_type' => 2,
		'required' => true,
	);
	create_control_richedit($editorOptions);
	// Store the ID for old compatibility.
	$context['post_box_name'] = $editorOptions['id'];

	if (isset($context['preview']))
	{
		require_once($sourcedir . '/Subs-Post.php');
		$context['recipients']['members'] = !empty($_POST['members']) ? explode(',', $_POST['members']) : array();
		$context['recipients']['exclude_members'] = !empty($_POST['exclude_members']) ? explode(',', $_POST['exclude_members']) : array();
		$context['recipients']['groups'] = !empty($_POST['groups']) ? explode(',', $_POST['groups']) : array();
		$context['recipients']['exclude_groups'] = !empty($_POST['exclude_groups']) ? explode(',', $_POST['exclude_groups']) : array();
		$context['recipients']['emails'] = !empty($_POST['emails']) ? explode(';', $_POST['emails']) : array();
		$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
		$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
		$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
		$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';

		return prepareMailingForPreview();
	}

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
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
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
	$smcFunc['db_free_result']($request);

	if (!empty($condition_array))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address IN(' . implode(', ', $condition_array) . ')',
			$condition_array_params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['recipients']['exclude_members'][] = $row['id_member'];
		$smcFunc['db_free_result']($request);
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
		SELECT COUNT(*)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($context['total_members']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Clean up the arrays.
	$context['recipients']['members'] = array_unique($context['recipients']['members']);
	$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);
}

/**
 * Handles the sending of the forum mailing in batches.
 * Called by ?action=admin;area=news;sa=mailingsend
 * Requires the send_mail permission.
 * Redirects to itself when more batches need to be sent.
 * Redirects to ?action=admin;area=news;sa=mailingmembers after everything has been sent.
 *
 * @param bool $clean_only If set, it will only clean the variables, put them in context, then return.
 * @uses the ManageNews template and email_members_send sub template.
 */
function SendMailing($clean_only = false)
{
	global $txt, $sourcedir, $context, $smcFunc;
	global $scripturl, $modSettings, $user_info;

	if (isset($_POST['preview']))
	{
		$context['preview'] = true;
		return ComposeMailing();
	}

	// How many to send at once? Quantity depends on whether we are queueing or not.
	// @todo Might need an interface? (used in Post.php too with different limits)
	$num_at_once = 1000;

	// If by PM's I suggest we half the above number.
	if (!empty($_POST['send_pm']))
		$num_at_once /= 2;

	checkSession();

	// Where are we actually to?
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';
	$context['parse_html'] = !empty($_POST['parse_html']) ? '1' : '0';

	//One can't simply nullify things around
	if (empty($_REQUEST['total_members']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members',
			array(
			)
		);
		list ($context['total_members']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
	{
		$context['total_members'] = (int) $_REQUEST['total_members'];
	}

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
	if (isset($_POST['groups']))
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
	if (isset($_POST['exclude_groups']))
	{
		if (is_array($_POST['exclude_groups']))
		{
			foreach ($_POST['exclude_groups'] as $group => $dummy)
				$context['recipients']['exclude_groups'][] = (int) $group;
		}
		// Ignore an empty string - we don't want to exclude "Regular Members" unless it's specifically selected
		elseif ($_POST['exclude_groups'] != '')
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
			if ($curmem != '' && filter_var($curmem, FILTER_VALIDATE_EMAIL))
				$context['recipients']['emails'][$curmem] = $curmem;
		}
	}

	// If we're only cleaning drop out here.
	if ($clean_only)
		return;

	require_once($sourcedir . '/Subs-Post.php');

	// We are relying too much on writing to superglobals...
	$_POST['subject'] = !empty($_POST['subject']) ? $_POST['subject'] : '';
	$_POST['message'] = !empty($_POST['message']) ? $_POST['message'] : '';

	// Save the message and its subject in $context
	$context['subject'] = $smcFunc['htmlspecialchars']($_POST['subject'], ENT_QUOTES);
	$context['message'] = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);

	// Prepare the message for sending it as HTML
	if (!$context['send_pm'] && !empty($_POST['send_html']))
	{
		// Prepare the message for HTML.
		if (!empty($_POST['parse_html']))
			$_POST['message'] = str_replace(array("\n", '  '), array('<br>' . "\n", '&nbsp; '), $_POST['message']);

		// This is here to prevent spam filters from tagging this as spam.
		if (preg_match('~\<html~i', $_POST['message']) == 0)
		{
			if (preg_match('~\<body~i', $_POST['message']) == 0)
				$_POST['message'] = '<html><head><title>' . $_POST['subject'] . '</title></head>' . "\n" . '<body>' . $_POST['message'] . '</body></html>';
			else
				$_POST['message'] = '<html>' . $_POST['message'] . '</html>';
		}
	}

	if (empty($_POST['message']) || empty($_POST['subject']))
	{
		$context['preview'] = true;
		return ComposeMailing();
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
			!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $scripturl . '?action=profile;u=' . $modSettings['latestMember']),
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

		sendmail($email, str_replace($from_member, $to_member, $_POST['subject']), str_replace($from_member, $to_member, $_POST['message']), null, 'news', !empty($_POST['send_html']), 5);

		// Done another...
		$i++;
	}

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
		{
			// Set a confirmation message.
			$_SESSION['newsletter_sent'] = 'queue_done';
			redirectexit('action=admin;area=news;sa=mailingmembers');
		}

		// Anything to exclude?
		if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
			$sendQuery .= ' AND mem.id_group != {int:regular_group}';
		if (!empty($context['recipients']['exclude_members']))
		{
			$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
			$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
		}

		// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
		$result = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.email_address, mem.real_name, mem.id_group, mem.additional_groups, mem.id_post_group
			FROM {db_prefix}members AS mem
			WHERE ' . $sendQuery . '
				AND mem.is_activated = {int:is_activated}
			ORDER BY mem.id_member ASC
			LIMIT {int:start}, {int:atonce}',
			array_merge($sendParams, array(
				'start' => $context['start'],
				'atonce' => $num_at_once,
				'regular_group' => 0,
				'is_activated' => 1,
			))
		);
		$rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$rows[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($result);

		// Load their alert preferences
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs(array_keys($rows), 'announcements', true);

		foreach ($rows as $row)
		{
			// Force them to have it?
			if (empty($context['email_force']) && empty($prefs[$row['id_member']]['announcements']))
				continue;

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
					!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $cleanMemberName . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $row['id_member'] . ']' . $cleanMemberName . '[/url]' : $scripturl . '?action=profile;u=' . $row['id_member']),
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
				sendmail($row['email_address'], $subject, $message, null, 'news', !empty($_POST['send_html']), 5);
			else
				sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message);
		}
	}

	$context['start'] = $context['start'] + $num_at_once;
	if (empty($context['recipients']['emails']) && ($context['start'] >= $context['total_members']))
	{
		// Log this into the admin log.
		logAction('newsletter', array(), 'admin');
		$_SESSION['newsletter_sent'] = 'queue_done';
		redirectexit('action=admin;area=news;sa=mailingmembers');
	}

	// Working out progress is a black art of sorts.
	$percentEmails = $context['total_emails'] == 0 ? 0 : ((count($context['recipients']['emails']) / $context['total_emails']) * ($context['total_emails'] / ($context['total_emails'] + $context['total_members'])));
	$percentMembers = ($context['start'] / $context['total_members']) * ($context['total_members'] / ($context['total_emails'] + $context['total_members']));
	$context['percentage_done'] = round(($percentEmails + $percentMembers) * 100, 2);

	$context['page_title'] = $txt['admin_newsletters'];
	$context['sub_template'] = 'email_members_send';
}

/**
 * Set general news and newsletter settings and permissions.
 * Called by ?action=admin;area=news;sa=settings.
 * Requires the forum_admin permission.
 *
 * @uses ManageNews template, news_settings sub-template.
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 */
function ModifyNewsSettings($return_config = false)
{
	global $context, $sourcedir, $txt, $scripturl;

	$config_vars = array(
		array('title', 'settings'),
		// Inline permissions.
		array('permissions', 'edit_news', 'help' => ''),
		array('permissions', 'send_mail'),
		'',

		// Just the remaining settings.
		array('check', 'xmlnews_enable', 'onclick' => 'document.getElementById(\'xmlnews_maxlen\').disabled = !this.checked;'),
		array('int', 'xmlnews_maxlen', 'subtext' => $txt['xmlnews_maxlen_note'], 10),
		array('check', 'xmlnews_attachments', 'subtext' => $txt['xmlnews_attachments_note']),
	);

	call_integration_hook('integrate_modify_news_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['admin_edit_news'] . ' - ' . $txt['settings'];
	$context['sub_template'] = 'show_settings';

	// Needed for the settings template.
	require_once($sourcedir . '/ManageServer.php');

	// Wrap it all up nice and warm...
	$context['post_url'] = $scripturl . '?action=admin;area=news;save;sa=settings';

	// Add some javascript at the bottom...
	addInlineJavaScript('
	document.getElementById("xmlnews_maxlen").disabled = !document.getElementById("xmlnews_enable").checked;', true);

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_news_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=news;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

?>