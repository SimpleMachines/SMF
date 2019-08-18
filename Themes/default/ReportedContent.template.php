<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Displays all reported posts.
 */
function template_reported_posts()
{
	global $context, $txt, $scripturl;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']))
		echo '
	<div class="infobox">
		', $txt['report_action_' . $context['report_post_action']], '
	</div>';

	echo '
	<form id="reported_posts" action="', $scripturl, '?action=moderate;area=reportedposts;sa=show', $context['view_closed'] ? ';closed' : '', ';start=', $context['start'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
			</h3>
		</div>';

	foreach ($context['reports'] as $report)
	{
		echo '
		<div class="windowbg">
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
			<br>';

		// Reported post options
		template_quickbuttons($report['quickbuttons'], 'reported_posts');

		echo '
		</div><!-- .windowbg -->';
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
		<div class="windowbg">
			<p class="centertext">', $txt['mc_reportedp_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">';

	if (!empty($context['total_reports']) && $context['total_reports'] >= $context['reports_how_many'])
		echo '
			<div class="pagelinks floatleft">' . $context['page_index'] . '</div>';

	echo '
			<div class="floatright">', !$context['view_closed'] ? '
				<input type="hidden" name="' . $context['mod-report-close-all_token_var'] . '" value="' . $context['mod-report-close-all_token'] . '">
				<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="button">' : '', '
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

/**
 * A block to show the current top reported posts.
 */
function template_reported_posts_block()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_posts_toggle" class="', !empty($context['admin_prefs']['mcrp']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=moderate;area=reportedposts" id="reported_posts_link">', $txt['mc_recent_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_posts_panel">
			<div class="modbox">
				<ul>';

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
			</div><!-- .modbox -->
		</div><!-- #reported_posts_panel -->

		<script>
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
		</script>';
}

/**
 * Handles viewing details of and managing a specific report
 */
function template_viewmodreport()
{
	global $context, $scripturl, $txt;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']))
		echo '
	<div class="infobox">
		', $txt['report_action_' . $context['report_post_action']], '
	</div>';

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reportedposts;sa=handlecomment;rid=', $context['report']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf($txt['mc_viewmodreport'], $context['report']['message_link'], $context['report']['author']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf($txt['mc_modreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']), '
					</span>';

	$report_buttons = array(
		'ignore' => array(
			'text' => !$context['report']['ignore'] ? 'mc_reportedp_ignore' : 'mc_reportedp_unignore',
			'url' => $scripturl.'?action=moderate;area=reportedposts;sa=handle;ignore='.(int) !$context['report']['ignore'].';rid='.$context['report']['id'].';'.$context['session_var'].'='.$context['session_id'].';'.$context['mod-report-ignore_token_var'].'='.$context['mod-report-ignore_token'],
			'class' => !$context['report']['ignore'] ? ' you_sure' : '',
			'custom' => !$context['report']['ignore'] ? ' data-confirm="' . $txt['mc_reportedp_ignore_confirm'] . '"' : '',
			'icon' => 'ignore'
		),
		'close' => array(
			'text' => $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close',
			'url' => $scripturl.'?action=moderate;area=reportedposts;sa=handle;closed='.(int) !$context['report']['closed'].';rid='.$context['report']['id'].';'.$context['session_var'].'='.$context['session_id'].';'.$context['mod-report-closed_token_var'].'='.$context['mod-report-closed_token'],
			'icon' => 'close'
		)
	);

	// Report buttons 
	template_button_strip($report_buttons, 'right');

	echo '
				</h3>
			</div><!-- .title_bar -->
			<div class="windowbg">
				', $context['report']['body'], '
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_whoreported_title'], '</h3>
			</div>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<p class="smalltext">
					', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '
				</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div>';

	if (empty($context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', $txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach ($context['report']['mod_comments'] as $comment)
	{
		echo '
				<div class="title_bar">
					<h3 class="titlebg">
						', $comment['member']['link'], ':  <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . $scripturl . '?action=moderate;area=reportedposts;sa=editcomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"  class="button">' . $txt['mc_reportedp_comment_edit'] . '</a><a href="' . $scripturl . '?action=moderate;area=reportedposts;sa=handlecomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['mod-reportC-delete_token_var'] . '=' . $context['mod-reportC-delete_token'] . '"  class="button">' . $txt['mc_reportedp_comment_delete'] . '</a></span>' : ''), '
					</h3>
				</div>';

		echo '
				<div class="windowbg">
					<p>', $comment['message'], '</p>
				</div>';
	}

	echo '
				<div class="cat_bar">
					<h3 class="catbg">
						<span class="floatleft">
							', $txt['mc_reportedp_new_comment'], '
						</span>
					</h3>
				</div>
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div class="padding">
					<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button">
					<input type="hidden" name="', $context['mod-reportC-add_token_var'], '" value="', $context['mod-reportC-add_token'], '">
				</div>
			</div>
			<br>';

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

/**
 * Template for editing a mod comment.
 */
function template_edit_comment()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reported', $context['report_type'], ';sa=editcomment;mid=', $context['comment_id'], ';rid=', $context['report_id'], ';save" method="post" accept-charset="', $context['character_set'], '">
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_edit_mod_comment'], '</h3>
			</div>
			<div class="windowbg">
				<textarea rows="6" cols="60" style="width: 60%;" name="mod_comment">', $context['comment']['body'], '</textarea>
				<div>
					<input type="submit" name="edit_comment" value="', $txt['mc_modreport_edit_mod_comment'], '" class="button">
				</div>
			</div>
			<br>
			<input type="hidden" name="', $context['mod-reportC-edit_token_var'], '" value="', $context['mod-reportC-edit_token'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

/**
 * A block to show the current top reported member profiles.
 */
function template_reported_members_block()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_members_toggle" class="', !empty($context['admin_prefs']['mcru']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=moderate;area=reportedmembers" id="reported_members_link">', $txt['mc_recent_member_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_users_panel">
			<div class="modbox">
				<ul>';

	foreach ($context['reported_members'] as $report)
		echo '
					<li class="smalltext">
						<a href="', $report['report_href'], '">', $report['user_name'], '</a>
					</li>';

	// Don't have any reported members right now?
	if (empty($context['reported_members']))
		echo '
					<li>
						<strong class="smalltext">', $txt['mc_recent_reports_none'], '</strong>
					</li>';

	echo '
				</ul>
			</div><!-- .modbox -->
		</div><!-- #reported_users_panel -->

		<script>
			var oReportedPostsPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty($context['admin_prefs']['mcrm']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'reported_members_panel\'
				],
				aSwapImages: [
					{
						sId: \'reported_members_toggle\',
						altExpanded: ', JavaScriptEscape($txt['hide']), ',
						altCollapsed: ', JavaScriptEscape($txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'reported_members_link\',
						msgExpanded: ', JavaScriptEscape($txt['mc_recent_member_reports']), ',
						msgCollapsed: ', JavaScriptEscape($txt['mc_recent_member_reports']), '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: \'admin_preferences\',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: \'1\',
					sAdditionalVars: \';admin_key=mcrm\'
				}
			});
		</script>';
}

/**
 * Lists all reported members
 */
function template_reported_members()
{
	global $context, $txt, $scripturl;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']) && !empty($txt['report_action_' . $context['report_post_action']]))
		echo '
	<div class="infobox">
		', $txt['report_action_' . $context['report_post_action']], '
	</div>';

	echo '
	<form id="reported_members" action="', $scripturl, '?action=moderate;area=reportedmembers;sa=show', $context['view_closed'] ? ';closed' : '', ';start=', $context['start'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
			</h3>
		</div>
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';

	foreach ($context['reports'] as $report)
	{
		echo '
		<div class="generic_list_wrapper windowbg">
			<h5>
				<strong><a href="', $report['user']['href'], '">', $report['user']['name'], '</a></strong>
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
			', template_quickbuttons($report['quickbuttons'], 'reported_members'), '
		</div><!-- .generic_list_wrapper -->';
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
		<div class="windowbg">
			<p class="centertext">', $txt['mc_reportedp_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">', $context['page_index'], '</div>
			<div class="floatright">
				', !$context['view_closed'] ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="button">' : '', '
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

/**
 * Template for viewing and managing a specific report about a user's profile
 */
function template_viewmemberreport()
{
	global $context, $scripturl, $txt;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']))
		echo '
	<div class="infobox">
		', $txt['report_action_' . $context['report_post_action']], '
	</div>';

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reportedmembers;sa=handlecomment;rid=', $context['report']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf($txt['mc_viewmemberreport'], $context['report']['user']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf($txt['mc_memberreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']), '
					</span>';

	$report_buttons = array(
		'ignore' => array(
			'text' => !$context['report']['ignore'] ? 'mc_reportedp_ignore' : 'mc_reportedp_unignore',
			'url' => $scripturl.'?action=moderate;area=reportedmembers;sa=handle;ignore='.(int)!$context['report']['ignore'].';rid='.$context['report']['id'].';'.$context['session_var'].'='.$context['session_id'].';'.$context['mod-report-ignore_token_var'].'='.$context['mod-report-ignore_token'],
			'class' => !$context['report']['ignore'] ? ' you_sure' : '',
			'custom' => !$context['report']['ignore'] ? ' data-confirm="' . $txt['mc_reportedp_ignore_confirm'] . '"' : '',
			'icon' => 'ignore'
		),
		'close' => array(
			'text' => $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close',
			'url' => $scripturl.'?action=moderate;area=reportedmembers;sa=handle;closed='.(int)!$context['report']['closed'].';rid='.$context['report']['id'].';'.$context['session_var'].'='.$context['session_id'].';'.$context['mod-report-closed_token_var'].'='.$context['mod-report-closed_token'],
			'icon' => 'close'
		)
	);

	// Report buttons
	template_button_strip($report_buttons, 'right');

	echo '
				</h3>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_memberreport_whoreported_title'], '</h3>
			</div>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<p class="smalltext">
					', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '
				</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div>';

	if (empty($context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', $txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach ($context['report']['mod_comments'] as $comment)
	{
		echo '
				<div class="title_bar">
					<h3 class="titlebg">', $comment['member']['link'], ':  <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . $scripturl . '?action=moderate;area=reportedmembers;sa=editcomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"  class="button">' . $txt['mc_reportedp_comment_edit'] . '</a> <a href="' . $scripturl . '?action=moderate;area=reportedmembers;sa=handlecomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['mod-reportC-delete_token_var'] . '=' . $context['mod-reportC-delete_token'] . '"  class="button you_sure" data-confirm="' . $txt['mc_reportedp_delete_confirm'] . '">' . $txt['mc_reportedp_comment_delete'] . '</a></span>' : ''), '</h3>
				</div>';

		echo '
				<div class="windowbg">
					<p>', $comment['message'], '</p>
				</div>';
	}

	echo '
				<div class="cat_bar">
					<h3 class="catbg">
						<span class="floatleft">
							', $txt['mc_reportedp_new_comment'], '
						</span>
					</h3>
				</div>
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div class="padding">
					<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button">
					<input type="hidden" name="', $context['mod-reportC-add_token_var'], '" value="', $context['mod-reportC-add_token'], '">
				</div>
			</div>
			<br>';

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

?>