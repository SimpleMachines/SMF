<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_moderation_center()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
	<div id="modcenter">';

	$alternate = true;
	// Show all the blocks they want to see.
	foreach ($context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
		<div class="modblock_', $alternate ? 'left' : 'right', '">', function_exists($block_function) ? $block_function() : '', '</div>';

		if (!$alternate)
			echo '
		<br class="clear" />';

		$alternate = !$alternate;
	}

	echo '
	</div>
	<br class="clear" />';
}

function template_latest_news()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=helpadmin;help=live_news" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics_hd.png" alt="', $txt['help'], '" class="icon" /></a> ', $txt['mc_latest_news'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<div id="smfAnnouncements" class="smalltext">', $txt['mc_cannot_connect_sm'], '</div>
			</div>
		</div>';

	// This requires a lot of javascript...
	// @todo Put this in it's own file!!
	echo '
		<script type="text/javascript" src="', $scripturl, '?action=viewsmfile;filename=current-version.js"></script>
		<script type="text/javascript" src="', $scripturl, '?action=viewsmfile;filename=latest-news.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var oAdminIndex = new smf_AdminIndex({
				sSelf: \'oAdminCenter\',

				bLoadAnnouncements: true,
				sAnnouncementTemplate: ', JavaScriptEscape('
					<dl>
						%content%
					</dl>
				'), ',
				sAnnouncementMessageTemplate: ', JavaScriptEscape('
					<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
					<dd>
						%message%
					</dd>
				'), ',
				sAnnouncementContainerId: \'smfAnnouncements\'
			});
		// ]]></script>';

}

// Show all the group requests the user can see.
function template_group_requests_block()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=groups;sa=requests">', $txt['mc_group_requests'], '</a>
			</h3>
		</div>
		<div class="windowbg">
			<div class="content modbox">
				<ul class="reset">';

		foreach ($context['group_requests'] as $request)
			echo '
				<li class="smalltext">
					<a href="', $request['request_href'], '">', $request['group']['name'], '</a> ', $txt['mc_groupr_by'], ' ', $request['member']['link'], '
				</li>';

		// Don't have any watched users right now?
		if (empty($context['group_requests']))
			echo '
				<li>
					<strong class="smalltext">', $txt['mc_group_requests_none'], '</strong>
				</li>';

		echo '
				</ul>
			</div>
		</div>';
}

// A block to show the current top reported posts.
function template_reported_posts_block()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=moderate;area=reports">', $txt['mc_recent_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg">
			<div class="content modbox">
				<ul class="reset">';

		foreach ($context['reported_posts'] as $report)
			echo '
					<li class="smalltext">
						<a href="', $report['report_href'], '">', $report['subject'], '</a> ', $txt['mc_reportedp_by'], ' ', $report['author']['link'], '
					</li>';

		// Don't have any watched users right now?
		if (empty($context['reported_posts']))
			echo '
					<li>
						<strong class="smalltext">', $txt['mc_recent_reports_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>';
}

function template_watched_users()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=moderate;area=userwatch">', $txt['mc_watched_users'], '</a>
			</h3>
		</div>
		<div class="windowbg">
			<div class="content modbox">
				<ul class="reset">';

		foreach ($context['watched_users'] as $user)
			echo '
					<li>
						<span class="smalltext">', sprintf(!empty($user['last_login']) ? $txt['mc_seen'] : $txt['mc_seen_never'], $user['link'], $user['last_login']), '</span>
					</li>';

		// Don't have any watched users right now?
		if (empty($context['watched_users']))
			echo '
					<li>
						<strong class="smalltext">', $txt['mc_watched_users_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>';
}

// Little section for making... notes.
function template_notes()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=moderate;area=index" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_notes'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content modbox">';

		if (!empty($context['notes']))
		{
			echo '
					<ul class="reset moderation_notes">';

			// Cycle through the notes.
			foreach ($context['notes'] as $note)
				echo '
						<li class="smalltext"><a href="', $note['delete_href'], '"><img src="', $settings['images_url'], '/pm_recipient_delete.png" alt="" /></a> <strong>', $note['author']['link'], ':</strong> ', $note['text'], '</li>';

			echo '
					</ul>
					<div class="pagesection notes">
						<span class="smalltext">', $context['page_index'], '</span>
					</div>';
		}

		echo '
					<div class="floatleft post_note">
						<input type="text" name="new_note" value="', $txt['mc_click_add_note'], '" style="width: 95%;" onclick="if (this.value == \'', $txt['mc_click_add_note'], '\') this.value = \'\';" class="input_text" />
					</div>
					<input type="submit" name="makenote" value="', $txt['mc_add_note'], '" class="button_submit" />
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
}

function template_reported_posts()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
	<form id="reported_posts" action="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';start=', $context['start'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
			</h3>
		</div>';

	if (!empty($context['reports']))
		echo '
		<div class="pagesection floatleft">
			', $context['page_index'], '
		</div>';

	foreach ($context['reports'] as $report)
	{
		echo '
		<div class="topic clear">
			<div class="', $report['alternate'] ? 'windowbg' : 'windowbg2', ' core_posts">
				<div class="content">
					<h5>
						<strong><a href="', $report['topic_href'], '">', $report['subject'], '</a></strong> ', $txt['mc_reportedp_by'], ' <strong>', $report['author']['link'], '</strong>
					</h5>
					<div class="smalltext">
						', $txt['mc_reportedp_last_reported'], ': ', $report['last_updated'], '&nbsp;-&nbsp;';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];

		echo '
						', $txt['mc_reportedp_reported_by'], ': ', implode(', ', $comments), '
					</div>
					<hr />
					', $report['body'], '

					<ul class="quickbuttons">
						<li>
							<a href="', $report['report_href'], '" class="details_button">', $txt['mc_reportedp_details'], '</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';ignore=', (int) !$report['ignore'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$report['ignore'] ? 'onclick="return confirm(\'' . $txt['mc_reportedp_ignore_confirm'] . '\');"' : '', ' class="ignore_button">', $report['ignore'] ? $txt['mc_reportedp_unignore'] : $txt['mc_reportedp_ignore'], '</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';close=', (int) !$report['closed'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" class="close_button">', $context['view_closed'] ? $txt['mc_reportedp_open'] : $txt['mc_reportedp_close'], '</a>
						</li>
						<li class="inline_mod_check">'
							, !$context['view_closed'] ? '<input type="checkbox" name="close[]" value="' . $report['id'] . '" />' : '', '
						</li>
					</ul>
				</div>
			</div>
		</div>';
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
		<div class="windowbg2">
			<div class="content">
				<p class="centertext">', $txt['mc_reportedp_none_found'], '</p>
			</div>
		</div>';
	else
		echo '
		<div class="pagesection">
			<div class="floatleft">
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>
			<div class="floatright">
				', !$context['view_closed'] ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="button_submit" />' : '', '
			</div>
		</div>';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

// Show a list of all the unapproved posts
function template_unapproved_posts()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Just a big table of it all really...
	echo '
	<div id="modcenter">
	<form action="', $scripturl, '?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['mc_unapproved_posts'], '</h3>
		</div>';

	// Make up some buttons
	$approve_button = create_button('approve.png', 'approve', 'approve', 'class="centericon"');
	$remove_button = create_button('delete.png', 'remove_message', 'remove', 'class="centericon"');

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
		<div class="windowbg2">
			<div class="content">
				<p class="centertext">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
			</div>
		</div>';
	else
		echo '
			<div class="pagesection floatleft">
				', $context['page_index'], '
			</div>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
		<div class="topic clear">
			<div class="', $item['alternate'] == 0 ? 'windowbg2' : 'windowbg', ' core_posts">
				<div class="content">
					<div class="counter">', $item['counter'], '</div>
					<div class="topic_details">
						<h5><strong>', $item['category']['link'], ' / ', $item['board']['link'], ' / ', $item['link'], '</strong></h5>
						<span class="smalltext"><strong>', $txt['mc_unapproved_by'], ' ', $item['poster']['link'], ' ', $txt['on'], ':</strong> ', $item['time'], '</span>
					</div>
					<div class="list_posts">
						<div class="post">', $item['body'], '</div>
					</div>
					<span class="floatright">
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';approve=', $item['id'], '">', $approve_button, '</a>';

			if ($item['can_delete'])
				echo '
					', $context['menu_separator'], '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';delete=', $item['id'], '">', $remove_button, '</a>';

			if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
						<input type="checkbox" name="item[]" value="', $item['id'], '" checked="checked" class="input_check" /> ';

			echo '
					</span>
				</div>
			</div>
		</div>';
	}

	echo '
		<div class="pagesection">';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
		echo '
			<div class="floatright">
				<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'', $txt['mc_unapproved_sure'], '\')) submit();">
					<option value="0">', $txt['with_selected'], ':</option>
					<option value="0">-------------------</option>
					<option value="approve">&nbsp;--&nbsp;', $txt['approve'], '</option>
					<option value="delete">&nbsp;--&nbsp;', $txt['delete'], '</option>
				</select>
				<noscript><input type="submit" name="mc_go" value="', $txt['go'], '" class="button_submit" /></noscript>
			</div>';

	if (!empty($context['unapproved_items']))
		echo '
			<div class="floatleft">
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';

	echo '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>
	</div>';
}

function template_viewmodreport()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reports;report=', $context['report']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf($txt['mc_viewmodreport'], $context['report']['message_link'], $context['report']['author']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf($txt['mc_modreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']), '
					</span>
					<span class="floatright">';

		// Make the buttons.
		$close_button = create_button('close.png', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', 'class="centericon"');
		$ignore_button = create_button('ignore.png', 'mc_reportedp_ignore', 'mc_reportedp_ignore', 'class="centericon"');
		$unignore_button = create_button('ignore.png', 'mc_reportedp_unignore', 'mc_reportedp_unignore', 'class="centericon"');

		echo '
						<a href="', $scripturl, '?action=moderate;area=reports;ignore=', (int) !$context['report']['ignore'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$context['report']['ignore'] ? 'onclick="return confirm(\'' . $txt['mc_reportedp_ignore_confirm'] . '\');"' : '', '>', $context['report']['ignore'] ? $unignore_button : $ignore_button, '</a>
						<a href="', $scripturl, '?action=moderate;area=reports;close=', (int) !$context['report']['closed'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $close_button, '</a>
					</span>
				</h3>
			</div>
			<div class="windowbg2">
				<div class="content">
					', $context['report']['body'], '
				</div>
			</div>
			<br />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_whoreported_title'], '</h3>
			</div>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<div class="content">
					<p class="smalltext">', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '</p>
					<p>', $comment['message'], '</p>
				</div>
			</div>';

	echo '
			<br />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div class="windowbg2">
				<div class="content">';

	if (empty($context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', $txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach ($context['report']['mod_comments'] as $comment)
		echo
					'<p>', $comment['member']['link'], ': ', $comment['message'], ' <em class="smalltext">(', $comment['time'], ')</em></p>';

	echo '
					<textarea rows="2" cols="60" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 60%; min-width: 60%' : 'width: 60%') . ';" name="mod_comment"></textarea>
					<div>
						<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button_submit" />
					</div>
				</div>
			</div>
			<br />';

	$alt = false;

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>';
}

// Callback function for showing a watched users post in the table.
function template_user_watch_post_callback($post)
{
	global $scripturl, $context, $txt, $delete_button;

	// We'll have a delete please bob.
	if (empty($delete_button))
		$delete_button = create_button('delete.png', 'remove_message', 'remove', 'class="centericon"');

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="' . $scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
						<div class="floatright">';

	if ($post['can_delete'])
		$output_html .= '
							<a href="' . $scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mc_watched_users_delete_post'] . '\');">' . $delete_button . '</a>
							<input type="checkbox" name="delete[]" value="' . $post['id'] . '" class="input_check" />';

	$output_html .= '
						</div>
					</div><br />
					<div class="smalltext">
						&#171; ' . $txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . ' &#187;
					</div>
					<hr />
					' . $post['body'];

	return $output_html;
}

// Moderation settings
function template_moderation_settings()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=settings" method="post" accept-charset="', $context['character_set'], '">
			<div class="windowbg2">
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['mc_prefs_homepage'], ':</strong>
						</dt>
						<dd>';

	foreach ($context['homepage_blocks'] as $k => $v)
		echo '
							<label for="mod_homepage_', $k, '"><input type="checkbox" id="mod_homepage_', $k, '" name="mod_homepage[', $k, ']"', in_array($k, $context['mod_settings']['user_blocks']) ? ' checked="checked"' : '', ' class="input_check" /> ', $v, '</label><br />';

	echo '
						</dd>';

	// If they can moderate boards they have more options!
	if ($context['can_moderate_boards'])
	{
		echo '
						<dt>
							<strong><label for="mod_show_reports">', $txt['mc_prefs_show_reports'], '</label>:</strong>
						</dt>
						<dd>
							<input type="checkbox" id="mod_show_reports" name="mod_show_reports" ', $context['mod_settings']['show_reports'] ? 'checked="checked"' : '', ' class="input_check" />
						</dd>
						<dt>
							<strong><label for="mod_notify_report">', $txt['mc_prefs_notify_report'], '</label>:</strong>
						</dt>
						<dd>
							<select id="mod_notify_report" name="mod_notify_report">
								<option value="0" ', $context['mod_settings']['notify_report'] == 0 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_never'], '</option>
								<option value="1" ', $context['mod_settings']['notify_report'] == 1 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_moderator'], '</option>
								<option value="2" ', $context['mod_settings']['notify_report'] == 2 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_always'], '</option>
							</select>
						</dd>';

	}

	if ($context['can_moderate_approvals'])
	{
		echo '

						<dt>
							<strong><label for="mod_notify_approval">', $txt['mc_prefs_notify_approval'], '</label>:</strong>
						</dt>
						<dd>
							<input type="checkbox" id="mod_notify_approval" name="mod_notify_approval" ', $context['mod_settings']['notify_approval'] ? 'checked="checked"' : '', ' class="input_check" />
						</dd>';
	}

	echo '
					</dl>
					<hr class="hrcolor" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['mod-set_token_var'], '" value="', $context['mod-set_token'], '" />
					<input type="submit" name="save" value="', $txt['save'], '" class="button_submit" />
				</div>
			</div>
		</form>
	</div>';
}

// Show a notice sent to a user.
function template_show_notice()
{
	global $txt, $settings, $options, $context;

	// We do all the HTML for this one!
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21" />
	</head>
	<body>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['show_notice'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', $txt['show_notice_subject'], ': ', $context['notice_subject'], '</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<dl>
					<dt>
						<strong>', $txt['show_notice_text'], ':</strong>
					</dt>
					<dd>
						', $context['notice_body'], '
					</dd>
				</dl>
			</div>
		</div>
	</body>
</html>';

}

// Add or edit a warning template.
function template_warn_template()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=', $context['id_template'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="information">
				', $txt['mc_warning_template_desc'], '
			</div>
			<div class="windowbg">
				<div class="content">
					<div class="errorbox"', empty($context['warning_errors']) ? ' style="display: none"' : '', ' id="errors">
						<dl>
							<dt>
								<strong id="error_serious">', $txt['error_while_submitting'] , '</strong>
							</dt>
							<dd class="error" id="error_list">
								', empty($context['warning_errors']) ? '' : implode('<br />', $context['warning_errors']), '
							</dd>
						</dl>
					</div>
					<div id="box_preview"', !empty($context['template_preview']) ? '' : ' style="display:none"', '>
						<dl class="settings">
							<dt>
								<strong>', $txt['preview'] , '</strong>
							</dt>
							<dd id="template_preview">
								', !empty($context['template_preview']) ? $context['template_preview'] : '', '
							</dd>
						</dl>
					</div>
					<dl class="settings">
						<dt>
							<strong><label for="template_title">', $txt['mc_warning_template_title'], '</label>:</strong>
						</dt>
						<dd>
							<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="30" class="input_text" />
						</dd>
						<dt>
							<strong><label for="template_body">', $txt['profile_warning_notify_body'], '</label>:</strong><br />
							<span class="smalltext">', $txt['mc_warning_template_body_desc'], '</span>
						</dt>
						<dd>
							<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', $context['template_data']['body'], '</textarea>
						</dd>
					</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
					<input type="checkbox" name="make_personal" id="make_personal" ', $context['template_data']['personal'] ? 'checked="checked"' : '', ' class="input_check" />
						<label for="make_personal">
							<strong>', $txt['mc_warning_template_personal'], '</strong>
						</label>
						<br />
						<span class="smalltext">', $txt['mc_warning_template_personal_desc'], '</span>
						<br />';

	echo '
					<hr class="hrcolor" />
					<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit" />
					<input type="submit" name="save" value="', $context['page_title'], '" class="button_submit" />
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['mod-wt_token_var'], '" value="', $context['mod-wt_token'], '" />
		</form>
	</div>

	<script type="text/javascript"><!-- // --><![CDATA[
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				url: "' . $scripturl . '?action=xmlhttp;sa=previews;xml",
				data: {item: "warning_preview", title: $("#template_title").val(), body: $("#template_body").val(), user: $(\'input[name="u"]\').attr("value")},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#template_preview").html($(request).find(\'body\').text());
					if ($(request).find("error").text() != \'\')
					{
						$("#errors").css({display:""});
						var errors_html = \'\';
						var errors = $(request).find(\'error\').each(function() {
							errors_html += $(this).text() + \'<br />\';
						});

						$(document).find("#error_list").html(errors_html);
					}
					else
					{
						$("#errors").css({display:"none"});
						$("#error_list").html(\'\');
					}
				return false;
				},
			});
			return false;
		}
	// ]]></script>';
}

?>