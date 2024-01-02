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
 * The main moderation center.
 */
function template_moderation_center()
{
	// Show moderators notes.
	template_notes();

	// Show a welcome message to the user.
	echo '
	<div id="modcenter">';

	// Show all the blocks they want to see.
	foreach (Utils::$context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
		<div class="half_content">', function_exists($block_function) ? $block_function() : '', '</div>';
	}

	echo '
	</div><!-- #modcenter -->';
}

/**
 * Show all the group requests the user can see.
 */
function template_group_requests_block()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="group_requests_toggle" class="', !empty(Utils::$context['admin_prefs']['mcgr']) ? 'toggle_down' : 'toggle_up', ' floatright" title="', empty(Utils::$context['admin_prefs']['mcgr']) ? Lang::$txt['hide'] : Lang::$txt['show'], '" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=groups;sa=requests" id="group_requests_link">', Lang::$txt['mc_group_requests'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="group_requests_panel">
			<ul>';

	foreach (Utils::$context['group_requests'] as $request)
		echo '
				<li class="smalltext">
					<a href="', $request['request_href'], '">', $request['group']['name'], '</a> ', Lang::$txt['mc_groupr_by'], ' ', $request['member']['link'], '
				</li>';

	// Don't have any watched users right now?
	if (empty(Utils::$context['group_requests']))
		echo '
				<li>
					<strong class="smalltext">', Lang::$txt['mc_group_requests_none'], '</strong>
				</li>';

	echo '
			</ul>
		</div><!-- #group_requests_panel -->

		<script>
			var oGroupRequestsPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty(Utils::$context['admin_prefs']['mcgr']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'group_requests_panel\'
				],
				aSwapImages: [
					{
						sId: \'group_requests_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'group_requests_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['mc_group_requests']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['mc_group_requests']), '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: \'admin_preferences\',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: \'1\',
					sAdditionalVars: \';admin_key=mcgr\'
				}
			});
		</script>';
}

/**
 * A list of watched users
 */
function template_watched_users()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="watched_users_toggle" class="', !empty(Utils::$context['admin_prefs']['mcwu']) ? 'toggle_down' : 'toggle_up', ' floatright" title="', empty(Utils::$context['admin_prefs']['mcwu']) ? Lang::$txt['hide'] : Lang::$txt['show'], '" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=moderate;area=userwatch" id="watched_users_link">', Lang::$txt['mc_watched_users'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="watched_users_panel">
			<ul>';

	foreach (Utils::$context['watched_users'] as $user)
		echo '
				<li>
					<span class="smalltext">', sprintf(!empty($user['last_login']) ? Lang::$txt['mc_seen'] : Lang::$txt['mc_seen_never'], $user['link'], $user['last_login']), '</span>
				</li>';

	// Don't have any watched users right now?
	if (empty(Utils::$context['watched_users']))
		echo '
				<li>
					<strong class="smalltext">', Lang::$txt['mc_watched_users_none'], '</strong>
				</li>';

	echo '
			</ul>
		</div><!-- #watched_users_panel -->

		<script>
			var oWatchedUsersToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty(Utils::$context['admin_prefs']['mcwu']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'watched_users_panel\'
				],
				aSwapImages: [
					{
						sId: \'watched_users_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'watched_users_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['mc_watched_users']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['mc_watched_users']), '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: \'admin_preferences\',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: \'1\',
					sAdditionalVars: \';admin_key=mcwu\'
				}
			});
		</script>';
}

/**
 * A list of reported posts
 */
function template_reported_posts_block()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_posts_toggle" class="', !empty(Utils::$context['admin_prefs']['mcrp']) ? 'toggle_down' : 'toggle_up', ' floatright" title="', empty(Utils::$context['admin_prefs']['mcrp']) ? Lang::$txt['hide'] : Lang::$txt['show'], '" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=moderate;area=reportedposts" id="reported_posts_link">', Lang::$txt['mc_recent_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_posts_panel">
			<ul>';

	foreach (Utils::$context['reported_posts'] as $post)
		echo '
				<li>
					<span class="smalltext">', sprintf(Lang::$txt['mc_post_report'], $post['report_link'], $post['author']['link']), '</span>
				</li>';

	// Don't have any watched users right now?
	if (empty(Utils::$context['reported_posts']))
		echo '
				<li>
					<strong class="smalltext">', Lang::$txt['mc_recent_reports_none'], '</strong>
				</li>';

	echo '
			</ul>
		</div><!-- #reported_posts_panel -->

		<script>
			var oWatchedUsersToggle = new smc_Toggle({
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
 * A list of reported users
 */
function template_reported_users_block()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_users_toggle" class="', !empty(Utils::$context['admin_prefs']['mcur']) ? 'toggle_down' : 'toggle_up', ' floatright" title="', empty(Utils::$context['admin_prefs']['mcur']) ? Lang::$txt['hide'] : Lang::$txt['show'], '" style="display: none;"></span>
				<a href="', Config::$scripturl, '?action=moderate;area=userwatch" id="reported_users_link">', Lang::$txt['mc_recent_user_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg" id="reported_users_panel">
			<ul>';

	foreach (Utils::$context['reported_users'] as $user)
		echo '
				<li>
					<span class="smalltext">', $user['user']['link'], '</span>
				</li>';

	// Don't have any watched users right now?
	if (empty(Utils::$context['reported_users']))
		echo '
				<li>
					<strong class="smalltext">', Lang::$txt['mc_reported_users_none'], '</strong>
				</li>';

	echo '
			</ul>
		</div><!-- #reported_users_panel -->

		<script>
			var oWatchedUsersToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', !empty(Utils::$context['admin_prefs']['mcur']) ? 'true' : 'false', ',
				aSwappableContainers: [
					\'reported_users_panel\'
				],
				aSwapImages: [
					{
						sId: \'reported_users_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'reported_users_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_user_reports']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['mc_recent_user_reports']), '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: \'admin_preferences\',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: \'1\',
					sAdditionalVars: \';admin_key=mcur\'
				}
			});
		</script>';
}

/**
 * Little section for making... notes.
 */
function template_notes()
{
	// Let them know the action was a success.
	if (!empty(Utils::$context['report_post_action']))
		echo '
		<div class="infobox">
			', Lang::$txt['report_action_' . Utils::$context['report_post_action']], '
		</div>';

	echo '
		<div id="modnotes">
			<form action="', Config::$scripturl, '?action=moderate;area=index;modnote" method="post">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['mc_notes'], '</h3>
				</div>
				<div class="windowbg">';

	if (!empty(Utils::$context['notes']))
	{
		echo '
					<ul class="moderation_notes">';

		// Cycle through the notes.
		foreach (Utils::$context['notes'] as $note)
			echo '
						<li class="smalltext">
							', ($note['can_delete'] ? '<a href="' . $note['delete_href'] . ';' . Utils::$context['mod-modnote-del_token_var'] . '=' . Utils::$context['mod-modnote-del_token'] . '" data-confirm="' . Lang::$txt['mc_reportedp_delete_confirm'] . '" class="you_sure"><span class="main_icons delete"></span></a>' : ''), $note['time'], ' <strong>', $note['author']['link'], ':</strong> ', $note['text'], '
						</li>';

		echo '
					</ul>
					<div class="pagesection notes">
						<div class="pagelinks">', Utils::$context['page_index'], '</div>
					</div>';
	}

	echo '
					<div class="floatleft post_note">
						<input type="text" name="new_note" placeholder="', Lang::$txt['mc_click_add_note'], '">
					</div>
					<input type="hidden" name="', Utils::$context['mod-modnote-add_token_var'], '" value="', Utils::$context['mod-modnote-add_token'], '">
					<input type="submit" name="makenote" value="', Lang::$txt['mc_add_note'], '" class="button">
				</div><!-- .windowbg -->
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>
		</div><!-- #modnotes -->';
}

/**
 * Show a list of all the unapproved posts
 */
function template_unapproved_posts()
{
	// Just a big table of it all really...
	echo '
	<div id="modcenter">
		<form action="', Config::$scripturl, '?action=moderate;area=postmod;start=', Utils::$context['start'], ';sa=', Utils::$context['current_view'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar', !empty(Utils::$context['unapproved_items']) ? ' cat_bar_round' : '', '">
				<h3 class="catbg">', Lang::$txt['mc_unapproved_posts'], '</h3>
			</div>';

	// No posts?
	if (empty(Utils::$context['unapproved_items']))
	{
		echo '
			<div class="windowbg">
				<p class="centertext">
					', Lang::$txt['mc_unapproved_' . Utils::$context['current_view'] . '_none_found'], '
				</p>
			</div>';
	}
	else
	{
		echo '
			<div class="pagesection">';

		if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
			echo '
				<ul class="buttonlist floatright">
					<li class="inline_mod_check">
						<input type="checkbox" onclick="invertAll(this, this.form, \'item[]\');" checked>
					</li>
				</ul>';

		echo '
				<div class="pagelinks">', Utils::$context['page_index'], '</div>
			</div>';

	}

	foreach (Utils::$context['unapproved_items'] as $item)
	{
		// The buttons
		$quickbuttons = array(
			'approve' => array(
				'label' => Lang::$txt['approve'],
				'href' => Config::$scripturl.'?action=moderate;area=postmod;sa='.Utils::$context['current_view'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';approve='.$item['id'],
				'icon' => 'approve',
			),
			'delete' => array(
				'label' => Lang::$txt['remove'],
				'href' => Config::$scripturl.'?action=moderate;area=postmod;sa='.Utils::$context['current_view'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';delete='.$item['id'],
				'icon' => 'remove_button',
				'show' => $item['can_delete']
			),
			'quickmod' => array(
				'class' => 'inline_mod_check',
				'content' => '<input type="checkbox" name="item[]" value="'.$item['id'].'" checked>',
				'show' => !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1
			),
		);
		echo '
			<div class="windowbg clear">
				<div class="page_number floatright"> #', $item['counter'], '</div>
				<div class="topic_details">
					<h5>
						<strong>', $item['category']['link'], ' / ', $item['board']['link'], ' / ', $item['link'], '</strong>
					</h5>
					<span class="smalltext">', sprintf(str_replace('<br>', ' ', Lang::$txt['last_post_topic']), $item['time'], '<strong>' . $item['poster']['link'] . '</strong>'), '</span>
				</div>
				<div class="list_posts">
					<div class="post">', $item['body'], '</div>
				</div>
				', template_quickbuttons($quickbuttons, 'unapproved_posts'), '
			</div><!-- .windowbg -->';
	}

	echo '
			<div class="pagesection">';

	if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
		echo '
				<div class="floatright">
					<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'', Lang::$txt['mc_unapproved_sure'], '\')) submit();">
						<option value="0">', Lang::$txt['with_selected'], ':</option>
						<option value="0" disabled>-------------------</option>
						<option value="approve">&nbsp;--&nbsp;', Lang::$txt['approve'], '</option>
						<option value="delete">&nbsp;--&nbsp;', Lang::$txt['delete'], '</option>
					</select>
					<noscript>
						<input type="submit" name="mc_go" value="', Lang::$txt['go'], '" class="button">
					</noscript>
				</div>';

	if (!empty(Utils::$context['unapproved_items']))
		echo '
				<div class="pagelinks">', Utils::$context['page_index'], '</div>';

	echo '
			</div><!-- .pagesection -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>
	</div><!-- #modcenter -->';
}

/**
 * Callback function for showing a watched users post in the table.
 *
 * @param array $post An array of data about the post.
 * @return string An array of HTML for showing the post info.
 */
function template_user_watch_post_callback($post)
{
	// We'll have a delete and a checkbox please bob.
	// @todo Discuss this with the team and rewrite if required.
	$quickbuttons = array(
		'delete' => array(
			'label' => Lang::$txt['remove_message'],
			'href' => Config::$scripturl.'?action=moderate;area=userwatch;sa=post;delete='.$post['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'],
			'javascript' => 'data-confirm="' . Lang::$txt['mc_watched_users_delete_post'] . '"',
			'class' => 'you_sure',
			'icon' => 'remove_button',
			'show' => $post['can_delete']
		),
		'quickmod' => array(
			'class' => 'inline_mod_check',
			'content' => '<input type="checkbox" name="delete[]" value="' . $post['id'] . '">',
			'show' => $post['can_delete']
		)
	);

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="' . Config::$scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . Lang::$txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
					</div>
					<br>
					<div class="smalltext">
						' . Lang::$txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . '
					</div>
					<div class="list_posts">
						' . $post['body'] . '
					</div>';

	$output_html .= template_quickbuttons($quickbuttons, 'user_watch_post', 'return');

	return $output_html;
}

/**
 * The moderation settings page.
 */
function template_moderation_settings()
{
	echo '
	<div id="modcenter">';

	echo '
		<div class="windowbg">
			<div class="centertext">', Lang::$txt['mc_no_settings'], '</div>
		</div>';

	echo '
	</div><!-- #modcenter -->';
}

/**
 * Show a notice sent to a user.
 */
function template_show_notice()
{
	// We do all the HTML for this one!
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<title>', Utils::$context['page_title'], '</title>
		', Theme::template_css(), '
	</head>
	<body>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['show_notice'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['show_notice_subject'], ': ', Utils::$context['notice_subject'], '</h3>
		</div>
		<div class="windowbg">
			<dl>
				<dt>
					<strong>', Lang::$txt['show_notice_text'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['notice_body'], '
				</dd>
			</dl>
		</div>
	</body>
</html>';

}

/**
 * Add or edit a warning template.
 */
function template_warn_template()
{
	echo '
	<div id="modcenter">
		<form action="', Config::$scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=', Utils::$context['id_template'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="information">
				', Lang::$txt['mc_warning_template_desc'], '
			</div>
			<div class="windowbg">
				<div class="errorbox"', empty(Utils::$context['warning_errors']) ? ' style="display: none"' : '', ' id="errors">
					<dl>
						<dt>
							<strong id="error_serious">', Lang::$txt['error_while_submitting'], '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty(Utils::$context['warning_errors']) ? '' : implode('<br>', Utils::$context['warning_errors']), '
						</dd>
					</dl>
				</div>
				<div id="box_preview"', !empty(Utils::$context['template_preview']) ? '' : ' style="display:none"', '>
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['preview'], '</strong>
						</dt>
						<dd id="template_preview">
							', !empty(Utils::$context['template_preview']) ? Utils::$context['template_preview'] : '', '
						</dd>
					</dl>
				</div>
				<dl class="settings">
					<dt>
						<strong><label for="template_title">', Lang::$txt['mc_warning_template_title'], '</label>:</strong>
					</dt>
					<dd>
						<input type="text" id="template_title" name="template_title" value="', Utils::$context['template_data']['title'], '" size="30">
					</dd>
					<dt>
						<strong><label for="template_body">', Lang::$txt['profile_warning_notify_body'], '</label>:</strong><br>
						<span class="smalltext">', Lang::$txt['mc_warning_template_body_desc'], '</span>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', Utils::$context['template_data']['body'], '</textarea>
					</dd>
				</dl>';

	if (Utils::$context['template_data']['can_edit_personal'])
		echo '
				<input type="checkbox" name="make_personal" id="make_personal"', Utils::$context['template_data']['personal'] ? ' checked' : '', '>
					<label for="make_personal">
						<strong>', Lang::$txt['mc_warning_template_personal'], '</strong>
					</label>
					<p class="smalltext">', Lang::$txt['mc_warning_template_personal_desc'], '</p>';

	echo '
				<input type="submit" name="preview" id="preview_button" value="', Lang::$txt['preview'], '" class="button">
				<input type="submit" name="save" value="', Utils::$context['page_title'], '" class="button">
			</div><!-- .windowbg -->
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['mod-wt_token_var'], '" value="', Utils::$context['mod-wt_token'], '">
		</form>
	</div><!-- #modcenter -->

	<script>
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				headers: {
					"X-SMF-AJAX": 1
				},
				xhrFields: {
					withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
				},
				url: "' . Config::$scripturl . '?action=xmlhttp;sa=previews;xml",
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
							errors_html += $(this).text() + \'<br>\';
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
	</script>';
}

?>