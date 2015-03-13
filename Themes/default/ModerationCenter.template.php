<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

function template_moderation_center()
{
	global $context;

	// Show moderators notes.
	template_notes();

	// Show a welcome message to the user.
	echo '
	<div id="modcenter">';

	// Show all the blocks they want to see.
	foreach ($context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
		<div class="half_content">', function_exists($block_function) ? $block_function() : '', '</div>';
	}

	echo '
	</div>';
}

// Show all the group requests the user can see.
function template_group_requests_block()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="group_requests_toggle" class="', !empty($context['admin_prefs']['mcgr']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=groups;sa=requests" id="group_requests_link">', $txt['mc_group_requests'], '</a>
			</h3>
		</div>
		<div class="windowbg2" id="group_requests_panel">
			<div class="modbox">
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
		</div>

	<script><!-- // --><![CDATA[
		var oGroupRequestsPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', !empty($context['admin_prefs']['mcgr']) ? 'true' : 'false', ',
			aSwappableContainers: [
				\'group_requests_panel\'
			],
			aSwapImages: [
				{
					sId: \'group_requests_toggle\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'group_requests_link\',
					msgExpanded: ', JavaScriptEscape($txt['mc_group_requests']), ',
					msgCollapsed: ', JavaScriptEscape($txt['mc_group_requests']), '
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
	// ]]></script>';
}

function template_watched_users()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="watched_users_toggle" class="', !empty($context['admin_prefs']['mcwu']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=moderate;area=userwatch" id="watched_users_link">', $txt['mc_watched_users'], '</a>
			</h3>
		</div>
		<div class="windowbg2" id="watched_users_panel">
			<div class="modbox">
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
		</div>

	<script><!-- // --><![CDATA[
		var oWatchedUsersToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', !empty($context['admin_prefs']['mcwu']) ? 'true' : 'false', ',
			aSwappableContainers: [
				\'watched_users_panel\'
			],
			aSwapImages: [
				{
					sId: \'watched_users_toggle\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'watched_users_link\',
					msgExpanded: ', JavaScriptEscape($txt['mc_watched_users']), ',
					msgCollapsed: ', JavaScriptEscape($txt['mc_watched_users']), '
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
	// ]]></script>';
}

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
		<div class="windowbg2" id="reported_posts_panel">
			<div class="modbox">
				<ul class="reset">';

		foreach ($context['reported_posts'] as $post)
			echo '
					<li>
						<span class="smalltext">', sprintf($txt['mc_post_report'], $post['report_link'], $post['author']['link']), '</span>
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
		var oWatchedUsersToggle = new smc_Toggle({
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

function template_reported_users_block()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="reported_users_toggle" class="', !empty($context['admin_prefs']['mcur']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
				<a href="', $scripturl, '?action=moderate;area=userwatch" id="reported_users_link">', $txt['mc_recent_user_reports'], '</a>
			</h3>
		</div>
		<div class="windowbg2" id="reported_users_panel">
			<div class="modbox">
				<ul class="reset">';

		foreach ($context['reported_users'] as $user)
			echo '
					<li>
						<span class="smalltext">', $user['user']['link'], '</span>
					</li>';

		// Don't have any watched users right now?
		if (empty($context['reported_users']))
			echo '
					<li>
						<strong class="smalltext">', $txt['mc_reported_users_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>

	<script><!-- // --><![CDATA[
		var oWatchedUsersToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', !empty($context['admin_prefs']['mcur']) ? 'true' : 'false', ',
			aSwappableContainers: [
				\'reported_users_panel\'
			],
			aSwapImages: [
				{
					sId: \'reported_users_toggle\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'reported_users_link\',
					msgExpanded: ', JavaScriptEscape($txt['mc_recent_user_reports']), ',
					msgCollapsed: ', JavaScriptEscape($txt['mc_recent_user_reports']), '
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
	// ]]></script>';
}

// Little section for making... notes.
function template_notes()
{
	global $context, $txt, $scripturl;

	// Let them know the action was a success.
	if (!empty($context['report_post_action']))
	{
		echo '
			<div class="infobox">
				', $txt['report_action_'. $context['report_post_action']], '
			</div>';
	}

	echo '
		<div class="modnotes">
			<form action="', $scripturl, '?action=moderate;area=index;modnote" method="post">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['mc_notes'], '</h3>
				</div>
				<div class="windowbg2">
					<div class="modbox">';

		if (!empty($context['notes']))
		{
			echo '
						<ul class="reset moderation_notes">';

			// Cycle through the notes.
			foreach ($context['notes'] as $note)
				echo '
							<li class="smalltext">', ($note['can_delete'] ? '<a href="'. $note['delete_href'] .';'. $context['mod-modnote-del_token_var'] .'='. $context['mod-modnote-del_token'] .'" data-confirm="'. $txt['mc_reportedp_delete_confirm'] .'" class="you_sure"><span class="generic_icons delete"></span></a>' : ''), $note['time'] ,' <strong>', $note['author']['link'], ':</strong> ', $note['text'], '</li>';

			echo '
						</ul>
						<div class="pagesection notes">
							<span class="smalltext">', $context['page_index'], '</span>
						</div>';
		}

		echo '
						<div class="floatleft post_note">
						<input type="text" name="new_note" placeholder="', $txt['mc_click_add_note'], '" style="width: 95%;" class="input_text">
						</div>
						<input type="hidden" name="', $context['mod-modnote-add_token_var'], '" value="', $context['mod-modnote-add_token'], '">
						<input type="submit" name="makenote" value="', $txt['mc_add_note'], '" class="button_submit">
					</div>
				</div>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>';
}

// Show a list of all the unapproved posts
function template_unapproved_posts()
{
	global $options, $context, $txt, $scripturl;

	// Just a big table of it all really...
	echo '
	<div id="modcenter">
	<form action="', $scripturl, '?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['mc_unapproved_posts'], '</h3>
		</div>';

	// Make up some buttons
	$approve_button = create_button('approve', 'approve', 'approve');
	$remove_button = create_button('delete', 'remove_message', 'remove');

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
		<div class="windowbg2">
			<p class="centertext">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
		</div>';
	else
		echo '
			<div class="pagesection floatleft">
				', $context['page_index'], '
			</div>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
		<div class="windowbg clear">
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
				<input type="checkbox" name="item[]" value="', $item['id'], '" checked class="input_check"> ';

		echo '
			</span>
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
				<noscript><input type="submit" name="mc_go" value="', $txt['go'], '" class="button_submit"></noscript>
			</div>';

	if (!empty($context['unapproved_items']))
		echo '
			<div class="floatleft">
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';

	echo '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	</div>';
}

// Callback function for showing a watched users post in the table.
function template_user_watch_post_callback($post)
{
	global $scripturl, $context, $txt, $delete_button;

	// We'll have a delete please bob.
	if (empty($delete_button))
		$delete_button = create_button('delete', 'remove_message', 'remove', 'class="centericon"');

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="' . $scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
						<div class="floatright">';

	if ($post['can_delete'])
		$output_html .= '
							<a href="' . $scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="'. $txt['mc_watched_users_delete_post'] . '" class="you_sure">' . $delete_button . '</a>
							<input type="checkbox" name="delete[]" value="' . $post['id'] . '" class="input_check">';

	$output_html .= '
						</div>
					</div><br>
					<div class="smalltext">
						&#171; ' . $txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . ' &#187;
					</div>
					<hr>
					' . $post['body'];

	return $output_html;
}

// Moderation settings
function template_moderation_settings()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="modcenter">';

	if (!empty($context['can_moderate_approvals']))
	{
		echo '
		<form action="', $scripturl, '?action=moderate;area=settings" method="post" accept-charset="', $context['character_set'], '">
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><label for="mod_notify_approval">', $txt['mc_prefs_notify_approval'], '</label>:</strong>
					</dt>
					<dd>
						<input type="checkbox" id="mod_notify_approval" name="mod_notify_approval"', $context['mod_settings']['notify_approval'] ? ' checked' : '', ' class="input_check">
					</dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['mod-set_token_var'], '" value="', $context['mod-set_token'], '">
				<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">
			</div>
		</form>';
	}
	else
		echo '
		<div class="windowbg">
			<div class="centertext">', $txt['mc_no_settings'], '</div>
		</div>';

	echo '
	</div>';
}

// Show a notice sent to a user.
function template_show_notice()
{
	global $txt, $settings, $context, $modSettings;

	// We do all the HTML for this one!
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'] ,'">
	</head>
	<body>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['show_notice'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', $txt['show_notice_subject'], ': ', $context['notice_subject'], '</h3>
		</div>
		<div class="windowbg">
			<dl>
				<dt>
					<strong>', $txt['show_notice_text'], ':</strong>
				</dt>
				<dd>
					', $context['notice_body'], '
				</dd>
			</dl>
		</div>
	</body>
</html>';

}

// Add or edit a warning template.
function template_warn_template()
{
	global $context, $txt, $scripturl;

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
				<div class="errorbox"', empty($context['warning_errors']) ? ' style="display: none"' : '', ' id="errors">
					<dl>
						<dt>
							<strong id="error_serious">', $txt['error_while_submitting'] , '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty($context['warning_errors']) ? '' : implode('<br>', $context['warning_errors']), '
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
						<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="30" class="input_text">
					</dd>
					<dt>
						<strong><label for="template_body">', $txt['profile_warning_notify_body'], '</label>:</strong><br>
						<span class="smalltext">', $txt['mc_warning_template_body_desc'], '</span>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', $context['template_data']['body'], '</textarea>
					</dd>
				</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
				<input type="checkbox" name="make_personal" id="make_personal"', $context['template_data']['personal'] ? ' checked' : '', ' class="input_check">
					<label for="make_personal">
						<strong>', $txt['mc_warning_template_personal'], '</strong>
					</label>
					<br>
					<span class="smalltext">', $txt['mc_warning_template_personal_desc'], '</span>
					<br>';

	echo '
				<hr class="hrcolor">
				<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit">
				<input type="submit" name="save" value="', $context['page_title'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['mod-wt_token_var'], '" value="', $context['mod-wt_token'], '">
		</form>
	</div>

	<script><!-- // --><![CDATA[
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
	// ]]></script>';
}

?>