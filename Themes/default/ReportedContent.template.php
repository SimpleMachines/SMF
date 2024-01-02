<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * Displays all reported posts.
 */
function template_reported_posts()
{
	// Let them know the action was a success.
	if (!empty(Utils::$context['report_post_action']))
		echo '
	<div class="infobox">
		', Lang::$txt['report_action_' . Utils::$context['report_post_action']], '
	</div>';

	echo '
	<form id="reported_posts" action="', Config::$scripturl, '?action=moderate;area=reportedposts;sa=show', Utils::$context['view_closed'] ? ';closed' : '', ';start=', Utils::$context['start'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', Utils::$context['view_closed'] ? Lang::$txt['mc_reportedp_closed'] : Lang::$txt['mc_reportedp_active'], '
			</h3>
		</div>
		<div class="pagesection">';

	if (!empty(Utils::$context['reports']) && !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
		echo '
			<ul class="buttonlist floatright">
				<li class="inline_mod_check">
					<input type="checkbox" onclick="invertAll(this, this.form, \'close[]\');">
				</li>
			</ul>';

	echo '
			<div class="pagelinks floatleft">' . Utils::$context['page_index'] . '</div>
		</div>';

	foreach (Utils::$context['reports'] as $report)
	{
		echo '
		<div class="windowbg">
			<h5>
				<strong>', !empty($report['topic']['board_name']) ? '<a href="' . Config::$scripturl . '?board=' . $report['topic']['id_board'] . '.0">' . $report['topic']['board_name'] . '</a>' : '??', ' / <a href="', $report['topic']['href'], '">', $report['subject'], '</a></strong> ', Lang::$txt['mc_reportedp_by'], ' <strong>', $report['author']['link'], '</strong>
			</h5>
			<div class="smalltext">
				', Lang::$txt['mc_reportedp_last_reported'], ': ', $report['last_updated'], '&nbsp;-&nbsp;';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];

		echo '
				', Lang::$txt['mc_reportedp_reported_by'], ': ', implode(', ', $comments), '
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
	if (empty(Utils::$context['reports']))
		echo '
		<div class="windowbg">
			<p class="centertext">', Lang::$txt['mc_reportedp_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">' . Utils::$context['page_index'] . '</div>';

	if (!empty(Utils::$context['reports']) && !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
		echo '
			<div class="floatright">
				<input type="hidden" name="' . Utils::$context['mod-report-close-all_token_var'] . '" value="' . Utils::$context['mod-report-close-all_token'] . '">
				<input type="submit" name="close_selected" value="' . Lang::$txt['mc_reportedp_close_selected'] . '" class="button">
			</div>';

	echo '
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>';
}

/**
 * A block to show the current top reported posts.
 */
function template_reported_posts_block()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_posts_toggle" class="', !empty(Utils::$context['admin_prefs']['mcrp']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=moderate;area=reportedposts" id="reported_posts_link">', Lang::$txt['mc_recent_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_posts_panel">
			<div class="modbox">
				<ul>';

	foreach (Utils::$context['reported_posts'] as $report)
		echo '
					<li class="smalltext">
						<a href="', $report['report_href'], '">', $report['subject'], '</a> ', Lang::$txt['mc_reportedp_by'], ' ', $report['author']['link'], '
					</li>';

	// Don't have any watched users right now?
	if (empty(Utils::$context['reported_posts']))
		echo '
					<li>
						<strong class="smalltext">', Lang::$txt['mc_recent_reports_none'], '</strong>
					</li>';

	echo '
				</ul>
			</div><!-- .modbox -->
		</div><!-- #reported_posts_panel -->

		<script>
			var oReportedPostsPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty(Utils::$context['admin_prefs']['mcrp']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'reported_posts_panel\'
				],
				aSwapImages: [
					{
						sId: \'reported_posts_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'reported_posts_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_reports']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_reports']), '
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
	// Let them know the action was a success.
	if (!empty(Utils::$context['report_post_action']))
		echo '
	<div class="infobox">
		', Lang::$txt['report_action_' . Utils::$context['report_post_action']], '
	</div>';

	echo '
	<div id="modcenter">
		<form action="', Config::$scripturl, '?action=moderate;area=reportedposts;sa=handlecomment;rid=', Utils::$context['report']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf(Lang::$txt['mc_viewmodreport'], Utils::$context['report']['message_link'], Utils::$context['report']['author']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf(Lang::$txt['mc_modreport_summary'], Utils::$context['report']['num_reports'], Utils::$context['report']['last_updated']), '
					</span>';

	$report_buttons = array(
		'ignore' => array(
			'text' => !Utils::$context['report']['ignore'] ? 'mc_reportedp_ignore' : 'mc_reportedp_unignore',
			'url' => Config::$scripturl.'?action=moderate;area=reportedposts;sa=handle;ignore='.(int) !Utils::$context['report']['ignore'].';rid='.Utils::$context['report']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-ignore_token_var'].'='.Utils::$context['mod-report-ignore_token'],
			'class' => !Utils::$context['report']['ignore'] ? ' you_sure' : '',
			'custom' => !Utils::$context['report']['ignore'] ? ' data-confirm="' . Lang::$txt['mc_reportedp_ignore_confirm'] . '"' : '',
			'icon' => 'ignore'
		),
		'close' => array(
			'text' => Utils::$context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close',
			'url' => Config::$scripturl.'?action=moderate;area=reportedposts;sa=handle;closed='.(int) !Utils::$context['report']['closed'].';rid='.Utils::$context['report']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-closed_token_var'].'='.Utils::$context['mod-report-closed_token'],
			'icon' => 'close'
		)
	);

	// Report buttons
	template_button_strip($report_buttons, 'right');

	echo '
				</h3>
			</div><!-- .title_bar -->
			<div class="windowbg">
				', Utils::$context['report']['body'], '
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mc_modreport_whoreported_title'], '</h3>
			</div>';

	foreach (Utils::$context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<p class="smalltext">
					', sprintf(Lang::$txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '
				</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div>';

	if (empty(Utils::$context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', Lang::$txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach (Utils::$context['report']['mod_comments'] as $comment)
	{
		echo '
				<div class="title_bar">
					<h3 class="titlebg">
						', $comment['member']['link'], ':  <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . Config::$scripturl . '?action=moderate;area=reportedposts;sa=editcomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '"  class="button">' . Lang::$txt['mc_reportedp_comment_edit'] . '</a><a href="' . Config::$scripturl . '?action=moderate;area=reportedposts;sa=handlecomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['mod-reportC-delete_token_var'] . '=' . Utils::$context['mod-reportC-delete_token'] . '"  class="button">' . Lang::$txt['mc_reportedp_comment_delete'] . '</a></span>' : ''), '
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
							', Lang::$txt['mc_reportedp_new_comment'], '
						</span>
					</h3>
				</div>
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div class="padding">
					<input type="submit" name="add_comment" value="', Lang::$txt['mc_modreport_add_mod_comment'], '" class="button">
					<input type="hidden" name="', Utils::$context['mod-reportC-add_token_var'], '" value="', Utils::$context['mod-reportC-add_token'], '">
				</div>
			</div>
			<br>';

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

/**
 * Template for editing a mod comment.
 */
function template_edit_comment()
{
	echo '
	<div id="modcenter">
		<form action="', Config::$scripturl, '?action=moderate;area=reported', Utils::$context['report_type'], ';sa=editcomment;mid=', Utils::$context['comment_id'], ';rid=', Utils::$context['report_id'], ';save" method="post" accept-charset="', Utils::$context['character_set'], '">
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mc_modreport_edit_mod_comment'], '</h3>
			</div>
			<div class="windowbg">
				<textarea rows="6" cols="60" style="width: 60%;" name="mod_comment">', Utils::$context['comment']['body'], '</textarea>
				<div>
					<input type="submit" name="edit_comment" value="', Lang::$txt['mc_modreport_edit_mod_comment'], '" class="button">
				</div>
			</div>
			<br>
			<input type="hidden" name="', Utils::$context['mod-reportC-edit_token_var'], '" value="', Utils::$context['mod-reportC-edit_token'], '">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

/**
 * A block to show the current top reported member profiles.
 */
function template_reported_members_block()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_members_toggle" class="', !empty(Utils::$context['admin_prefs']['mcru']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=moderate;area=reportedmembers" id="reported_members_link">', Lang::$txt['mc_recent_member_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_users_panel">
			<div class="modbox">
				<ul>';

	foreach (Utils::$context['reported_members'] as $report)
		echo '
					<li class="smalltext">
						<a href="', $report['report_href'], '">', $report['user_name'], '</a>
					</li>';

	// Don't have any reported members right now?
	if (empty(Utils::$context['reported_members']))
		echo '
					<li>
						<strong class="smalltext">', Lang::$txt['mc_recent_reports_none'], '</strong>
					</li>';

	echo '
				</ul>
			</div><!-- .modbox -->
		</div><!-- #reported_users_panel -->

		<script>
			var oReportedPostsPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty(Utils::$context['admin_prefs']['mcrm']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'reported_members_panel\'
				],
				aSwapImages: [
					{
						sId: \'reported_members_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'reported_members_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_member_reports']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_member_reports']), '
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
	// Let them know the action was a success.
	if (!empty(Utils::$context['report_post_action']) && !empty(Lang::$txt['report_action_' . Utils::$context['report_post_action']]))
		echo '
	<div class="infobox">
		', Lang::$txt['report_action_' . Utils::$context['report_post_action']], '
	</div>';

	echo '
	<form id="reported_members" action="', Config::$scripturl, '?action=moderate;area=reportedmembers;sa=show', Utils::$context['view_closed'] ? ';closed' : '', ';start=', Utils::$context['start'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar cat_bar_round">
			<h3 class="catbg">
				', Utils::$context['view_closed'] ? Lang::$txt['mc_reportedp_closed'] : Lang::$txt['mc_reportedp_active'], '
			</h3>
		</div>
		<div class="pagesection">';

	if (!empty(Utils::$context['reports']) && !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
		echo '
			<ul class="buttonlist floatright">
				<li class="inline_mod_check">
					<input type="checkbox" onclick="invertAll(this, this.form, \'close[]\');">
				</li>
			</ul>';

		echo '
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';

	foreach (Utils::$context['reports'] as $report)
	{
		echo '
		<div class="generic_list_wrapper windowbg">
			<h5>
				<strong><a href="', $report['user']['href'], '">', $report['user']['name'], '</a></strong>
			</h5>
			<div class="smalltext">
				', Lang::$txt['mc_reportedp_last_reported'], ': ', $report['last_updated'], '&nbsp;-&nbsp;';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];

		echo '
				', Lang::$txt['mc_reportedp_reported_by'], ': ', implode(', ', $comments), '
			</div>
			<hr>
			', template_quickbuttons($report['quickbuttons'], 'reported_members'), '
		</div><!-- .generic_list_wrapper -->';
	}

	// Were none found?
	if (empty(Utils::$context['reports']))
		echo '
		<div class="windowbg">
			<p class="centertext">', Lang::$txt['mc_reportedp_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">', Utils::$context['page_index'], '</div>
			<div class="floatright">
				', (!Utils::$context['view_closed'] && !empty(Utils::$context['reports'])) ? '<input type="submit" name="close_selected" value="' . Lang::$txt['mc_reportedp_close_selected'] . '" class="button">' : '', '
			</div>
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>';
}

/**
 * Template for viewing and managing a specific report about a user's profile
 */
function template_viewmemberreport()
{
	// Let them know the action was a success.
	if (!empty(Utils::$context['report_post_action']))
		echo '
	<div class="infobox">
		', Lang::$txt['report_action_' . Utils::$context['report_post_action']], '
	</div>';

	echo '
	<div id="modcenter">
		<form action="', Config::$scripturl, '?action=moderate;area=reportedmembers;sa=handlecomment;rid=', Utils::$context['report']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf(Lang::$txt['mc_viewmemberreport'], Utils::$context['report']['user']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf(Lang::$txt['mc_memberreport_summary'], Utils::$context['report']['num_reports'], Utils::$context['report']['last_updated']), '
					</span>';

	$report_buttons = array(
		'ignore' => array(
			'text' => !Utils::$context['report']['ignore'] ? 'mc_reportedp_ignore' : 'mc_reportedp_unignore',
			'url' => Config::$scripturl.'?action=moderate;area=reportedmembers;sa=handle;ignore='.(int)!Utils::$context['report']['ignore'].';rid='.Utils::$context['report']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-ignore_token_var'].'='.Utils::$context['mod-report-ignore_token'],
			'class' => !Utils::$context['report']['ignore'] ? ' you_sure' : '',
			'custom' => !Utils::$context['report']['ignore'] ? ' data-confirm="' . Lang::$txt['mc_reportedp_ignore_confirm'] . '"' : '',
			'icon' => 'ignore'
		),
		'close' => array(
			'text' => Utils::$context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close',
			'url' => Config::$scripturl.'?action=moderate;area=reportedmembers;sa=handle;closed='.(int)!Utils::$context['report']['closed'].';rid='.Utils::$context['report']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-closed_token_var'].'='.Utils::$context['mod-report-closed_token'],
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
				<h3 class="catbg">', Lang::$txt['mc_memberreport_whoreported_title'], '</h3>
			</div>';

	foreach (Utils::$context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<p class="smalltext">
					', sprintf(Lang::$txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '
				</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div>';

	if (empty(Utils::$context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', Lang::$txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach (Utils::$context['report']['mod_comments'] as $comment)
	{
		echo '
				<div class="title_bar">
					<h3 class="titlebg">', $comment['member']['link'], ':  <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . Config::$scripturl . '?action=moderate;area=reportedmembers;sa=editcomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '"  class="button">' . Lang::$txt['mc_reportedp_comment_edit'] . '</a> <a href="' . Config::$scripturl . '?action=moderate;area=reportedmembers;sa=handlecomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['mod-reportC-delete_token_var'] . '=' . Utils::$context['mod-reportC-delete_token'] . '"  class="button you_sure" data-confirm="' . Lang::$txt['mc_reportedp_delete_confirm'] . '">' . Lang::$txt['mc_reportedp_comment_delete'] . '</a></span>' : ''), '</h3>
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
							', Lang::$txt['mc_reportedp_new_comment'], '
						</span>
					</h3>
				</div>
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div class="padding">
					<input type="submit" name="add_comment" value="', Lang::$txt['mc_modreport_add_mod_comment'], '" class="button">
					<input type="hidden" name="', Utils::$context['mod-reportC-add_token_var'], '" value="', Utils::$context['mod-reportC-add_token'], '">
				</div>
			</div>
			<br>';

	template_show_list('moderation_actions_list');

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

?>