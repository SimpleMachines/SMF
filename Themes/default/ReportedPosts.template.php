<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_reported_posts()
{
	global $context, $txt, $scripturl;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']) && !empty($txt['report_action_'. $context['report_post_action']]))
	{
		echo '
			<div class="infobox">
				', $txt['report_action_'. $context['report_post_action']], '
			</div>';
	}

	echo '
	<form id="reported_posts" action="', $scripturl, '?action=moderate;area=reports;sa=show', $context['view_closed'] ? ';closed' : '', ';start=', $context['start'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
			</h3>
		</div>
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';

	// Make the buttons.
	$close_button = create_button('close.png', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', 'class="centericon"');
	$details_button = create_button('details.png', 'mc_reportedp_details', 'mc_reportedp_details', 'class="centericon"');
	$ignore_button = create_button('ignore.png', 'mc_reportedp_ignore', 'mc_reportedp_ignore', 'class="centericon"');
	$unignore_button = create_button('ignore.png', 'mc_reportedp_unignore', 'mc_reportedp_unignore', 'class="centericon"');
	$ban_button = create_button('close.png', 'mc_reportedp_ban', 'mc_reportedp_ban', 'class="centericon"');
	$delete_button = create_button('delete.png', 'mc_reportedp_delete', 'mc_reportedp_delete', 'class="centericon"');

	foreach ($context['reports'] as $report)
	{
		echo '
		<div class="generic_list_wrapper ', $report['alternate'] ? 'windowbg' : 'windowbg2', '">
			<div class="content">
				<h5>
					<strong>', !empty($report['topic']['board_name']) ? '<a href="' . $scripturl . '?board=' . $report['topic']['id_board'] . '.0">' . $report['topic']['board_name'] . '</a>' : '??', ' / <a href="', $report['topic']['href'], '">', $report['subject'], '</a></strong> ', $txt['mc_reportedp_by'], ' <strong>', $report['author']['link'], '</strong>
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
				<hr>
				', $report['body'], '
				<br>
				<ul class="quickbuttons">
					<li><a href="', $report['report_href'], '">', $details_button, '</a></li>
					<li><a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';ignore=', (int) !$report['ignore'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$report['ignore'] ? 'onclick="return confirm(\'' . $txt['mc_reportedp_ignore_confirm'] . '\');"' : '', '>', $report['ignore'] ? $unignore_button : $ignore_button, '</a></li>
					<li><a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';close=', (int) !$report['closed'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $close_button, '</a></li>';

		// Delete message button.
		if (!$report['closed'] && (is_array($context['report_remove_any_boards']) && in_array($report['topic']['id_board'], $context['report_remove_any_boards'])))
			echo '
					<li><a href="', $scripturl, '?action=deletemsg;topic=', $report['topic']['id'] ,'.0;msg=', $report['topic']['id_msg'] ,';modcenter;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'' , $txt['mc_reportedp_delete_confirm'] , '\');">', $delete_button, '</a></li>';

		// Ban this user button.
		if (!$report['closed'] && !empty($context['report_manage_bans']))
			echo '
					<li><a href="', $scripturl, '?action=admin;area=ban;sa=add', (!empty($report['author']['id']) ? ';u='. $report['author']['id'] : ';msg='. $report['topic']['id_msg']) ,';', $context['session_var'], '=', $context['session_id'], '">', $ban_button, '</a></li>';

		echo '
					<li>', !$context['view_closed'] ? '<input type="checkbox" name="close[]" value="' . $report['id'] . '" class="input_check">' : '', '</li>
				</ul>
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

	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">', $context['page_index'], '</div>
			<div class="floatright">
				', !$context['view_closed'] ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="button_submit">' : '', '
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}


// A block to show the current top reported posts.
function template_reported_posts_block()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_posts_toggle" class="', !empty($context['admin_prefs']['mcrp']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=moderate;area=reports" id="reported_posts_link">', $txt['mc_recent_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_posts_panel">
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
		</div>

	<script><!-- // --><![CDATA[
		var oReportedPostsPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', !empty($context['admin_prefs']['mcrp']) ? 'true' : 'false', ',
			aSwappableContainers: [
				\'reported_posts_panel\'
			],
			aSwapImages: [
				{
					sId: \'reported_posts_toggle\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'reported_posts_link\',
					msgExpanded: ', JavaScriptEscape($txt['mc_recent_reports']), ',
					msgCollapsed: ', JavaScriptEscape($txt['mc_recent_reports']), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: true,
				sOptionName: \'admin_preferences\',
				sSessionVar: smf_session_var,
				sSessionId: smf_session_id,
				sThemeId: \'1\',
				sAdditionalVars: \';admin_key=mcrp\'
			}
		});
	// ]]></script>';
}


function template_viewmodreport()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reports;sa=handlecomment;rid=', $context['report']['id'], '" method="post" accept-charset="', $context['character_set'], '">
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
			<br>
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
			<br>
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
					'<p>', $comment['member']['link'], ': ', $comment['message'], ' <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . $scripturl . '?moderate;area=reports;rid='. $context['report']['id'] .';mid='. $comment['id'] .';sa=editcomment">'. $txt['mc_reportedp_comment_edit'] .'</a> | <a href="' . $scripturl . '?moderate;area=reports;rid='. $context['report']['id'] .';mid='. $comment['id'] .';sa=handlecomment;delete">'. $txt['mc_reportedp_comment_delete'] .'</a></span>' : '') ,'</p>';

	echo '
					<textarea rows="2" cols="60" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 60%; min-width: 60%' : 'width: 60%') . ';" name="mod_comment"></textarea>
					<div>
						<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button_submit">
					</div>
				</div>
			</div>
			<br>';

	$alt = false;

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

function template_editcomment()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reports;sa=editcomment;save;mid=', $context['comment_id'], ';rid=', $context['report_id'] ,'" method="post" accept-charset="', $context['character_set'], '">';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg"></h3>
			</div>
			<div class="windowbg2">
				<div class="content">';

	echo '
					<textarea rows="2" cols="60" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 60%; min-width: 60%' : 'width: 60%') . ';" name="mod_comment">', $context['comment']['body'] ,'</textarea>
					<div>
						<input type="submit" name="edit_comment" value="', $txt['mc_modreport_edit_mod_comment'], '" class="button_submit">
					</div>
				</div>
			</div>
			<br>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}
?>