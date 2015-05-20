<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

// Template for the profile side bar - goes before any other profile template.
function template_profile_above()
{
	global $context;

	// Prevent Chrome from auto completing fields when viewing/editing other members profiles
	if (isBrowser('is_chrome') && !$context['user']['is_owner'])
		echo '
	<script><!-- // --><![CDATA[
		disableAutoComplete();
	// ]]></script>';

	// If an error occurred while trying to save previously, give the user a clue!
	echo '
					', template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
					<div class="infobox">
						', $context['profile_updated'], '
					</div>';
}

// Template for closing off table started in profile_above.
function template_profile_below()
{
}

// Template for showing off the spiffy popup of the menu
function template_profile_popup()
{
	global $context, $scripturl;

	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()

	echo '
		<div class="profile_user_avatar floatleft">
			<a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['member']['avatar']['image'],'</a>
		</div>
		<div class="profile_user_info floatleft">
			<span class="profile_username"><a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['user']['name'], '</a></span>
			<span class="profile_group">', $context['member']['group'], '</span>
		</div>
		<br class="clear">
		<div class="profile_user_links">
			<ol>';

	$menu_context = &$context[$context['profile_menu_name']];
	foreach ($context['profile_items'] as $item)
	{
		$area = &$menu_context['sections'][$item['menu']]['areas'][$item['area']];
		$item_url = (isset($item['url']) ? $item['url'] : (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $item['area'])) . $menu_context['extra_parameters'];
		echo '
				<li>
					', $area['icon'], '<a href="', $item_url, '">', !empty($item['title']) ? $item['title'] : $area['label'], '</a>
				</li>';
	}

	echo '
			</ol>
			<br class="clear">
		</div>';
}

function template_alerts_popup()
{
	global $context, $txt, $scripturl;

	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()
	echo '
		<div class="alert_bar">
			<div class="alerts_opts block">
				<a href="' . $scripturl . '?action=profile;area=notification;sa=markread;', $context['session_var'], '=', $context['session_id'], '" onclick="return markAlertsRead(this)">', $txt['mark_alerts_read'], '</a>
				<a href="', $scripturl, '?action=profile;area=notification;sa=alerts" class="floatright">', $txt['alert_settings'], '</a>
			</div>
			<div class="alerts_box centertext">
				<a href="', $scripturl, '?action=profile;area=showalerts" class="button">', $txt['all_alerts'], '</a>
			</div>
		</div>
		<div class="alerts_unread">';

	if (empty($context['unread_alerts']))
	{
		template_alerts_all_read();
	}
	else
	{
		foreach ($context['unread_alerts'] as $id_alert => $details)
		{
			echo '
			<div class="unread">
				<div class="avatar floatleft">', !empty($details['sender']) ? $details['sender']['avatar']['image'] : '', '</div>
				<div class="details floatleft">
					', !empty($details['icon']) ? $details['icon'] : '', '<span>', $details['text'], '</span> - ', $details['time'], '
				</div>
				<br class="clear">
			</div>';
		}
	}

	echo '
		</div>
		<script><!-- // --><![CDATA[
		function markAlertsRead(obj) {
			ajax_indicator(true);
			$.get(
				obj.href,
				function(data) {
					ajax_indicator(false);
					$("#alerts_menu_top span.amt").remove();
					$("#alerts_menu div.alerts_unread").html(data);
				}
			);
			return false;
		}
		// ]]></script>';
}

function template_alerts_all_read()
{
	global $txt;

	echo '<div class="no_unread">', $txt['alerts_no_unread'], '</div>';
}

// This template displays users details without any option to edit them.
function template_summary()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// Display the basic information about the user
	echo '
	<div id="profileview" class="roundframe flow_auto">
		<div id="basicinfo">';

	// Are there any custom profile fields for above the name?
	if (!empty($context['print_custom_fields']['above_member']))
	{
		echo '
			<div class="custom_fields_above_name">
				<ul >';

		foreach ($context['print_custom_fields']['above_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>
			<br>';
	}

	echo '
			<div class="username clear">
				<h4>', $context['member']['name'], '<span class="position">', (!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group']), '</span></h4>
			</div>
			', $context['member']['avatar']['image'];

	// Are there any custom profile fields for below the avatar?
	if (!empty($context['print_custom_fields']['below_avatar']))
	{
		echo '
			<div class="custom_fields_below_avatar">
				<ul >';

		foreach ($context['print_custom_fields']['below_avatar'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>
			<br>';
	}

		echo '
			<ul class="reset clear">';
	// Email is only visible if it's your profile or you have the moderate_forum permission
	if ($context['member']['show_email'])
		echo '
				<li><a href="mailto:', $context['member']['email'], '" title="', $context['member']['email'], '" rel="nofollow"><span class="generic_icons mail" title="' . $txt['email'] . '"></span></a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
				<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" class="new_win">', ($settings['use_image_buttons'] ? '<span class="generic_icons www" title="' . $context['member']['website']['title'] . '"></span>' : $txt['www']), '</a></li>';

	// Are there any custom profile fields as icons?
	if (!empty($context['print_custom_fields']['icons']))
	{
		foreach ($context['print_custom_fields']['icons'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li class="custom_field">', $field['output_html'], '</li>';
	}

	echo '
			</ul>
			<span id="userstatus">', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['text'] . '" rel="nofollow">' : '', $settings['use_image_buttons'] ? '<span class="' . ($context['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $context['member']['online']['text'] . '"></span>' : $context['member']['online']['label'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['label'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
		echo '
				<br><a href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">[', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], ']</a>';

	echo '
			</span>';

	if (!$context['user']['is_owner'] && $context['can_send_pm'])
		echo '
			<a href="', $scripturl, '?action=pm;sa=send;u=', $context['id_member'], '" class="infolinks">', $txt['profile_sendpm_short'], '</a>';

	echo '
			<a href="', $scripturl, '?action=profile;area=showposts;u=', $context['id_member'], '" class="infolinks">', $txt['showPosts'], '</a>';

	if ($context['user']['is_owner'] && !empty($modSettings['drafts_post_enabled']))
		echo '
			<a href="', $scripturl, '?action=profile;area=showdrafts;u=', $context['id_member'], '" class="infolinks">', $txt['drafts_show'], '</a>';

	echo '
			<a href="', $scripturl, '?action=profile;area=statistics;u=', $context['id_member'], '" class="infolinks">', $txt['statPanel'], '</a>';

	// Are there any custom profile fields for bottom?
	if (!empty($context['print_custom_fields']['bottom_poster']))
	{
		echo '
			<div class="custom_fields_bottom">
				<ul class="reset nolist">';

		foreach ($context['print_custom_fields']['bottom_poster'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>';
	}

	echo '
		</div>';

	echo '
		<div id="detailedinfo">
			<dl>';

	if ($context['user']['is_owner'] || $context['user']['is_admin'])
		echo '
				<dt>', $txt['username'], ': </dt>
				<dd>', $context['member']['username'], '</dd>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
				<dt>', $txt['profile_posts'], ': </dt>
				<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';

	if ($context['member']['show_email'])
	{
		echo '
				<dt>', $txt['email'], ': </dt>
				<dd><a href="mailto:', $context['member']['email'], '">', $context['member']['email'], '</a></dd>';
	}

	if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title']))
		echo '
				<dt>', $txt['custom_title'], ': </dt>
				<dd>', $context['member']['title'], '</dd>';

	if (!empty($context['member']['blurb']))
		echo '
				<dt>', $txt['personal_text'], ': </dt>
				<dd>', $context['member']['blurb'], '</dd>';

	echo '
				<dt>', $txt['age'], ':</dt>
				<dd>', $context['member']['age'] . ($context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . $settings['images_url'] . '/cake.png" alt="">' : ''), '</dd>';

	echo '
			</dl>';

	// Any custom fields for standard placement?
	if (!empty($context['print_custom_fields']['standard']))
	{
		echo '
				<dl>';

		foreach ($context['print_custom_fields']['standard'] as $field)
			if (!empty($field['output_html']))
				echo '
					<dt>', $field['name'], ':</dt>
					<dd>', $field['output_html'], '</dd>';

		echo '
				</dl>';
	}

	echo '
				<dl class="noborder">';

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
					<dt>', $txt['profile_warning_level'], ': </dt>
					<dd>
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', ($context['can_issue_warning'] && !$context['user']['is_owner'] ? 'issuewarning' : 'viewwarning') , '">', $context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty($context['warning_status']))
			echo '
						<span class="smalltext">(', $context['warning_status'], ')</span>';

		echo '
					</dd>';
	}

	// Is this member requiring activation and/or banned?
	if (!empty($context['activate_message']) || !empty($context['member']['bans']))
	{

		// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
		if (!empty($context['activate_message']))
			echo '
					<dt class="clear"><span class="alert">', $context['activate_message'], '</span>&nbsp;(<a href="', $context['activate_link'], '"', ($context['activate_type'] == 4 ? ' class="you_sure" data-confirm="'. $txt['profileConfirm'] .'"' : ''), '>', $context['activate_link_text'], '</a>)</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty($context['member']['bans']))
		{
			echo '
					<dt class="clear"><span class="alert">', $txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="document.getElementById(\'ban_info\').style.display = document.getElementById(\'ban_info\').style.display == \'none\' ? \'\' : \'none\';return false;">' . $txt['view_ban'] . '</a>]</dt>
					<dt class="clear" id="ban_info" style="display: none;">
						<strong>', $txt['user_banned_by_following'], ':</strong>';

			foreach ($context['member']['bans'] as $ban)
				echo '
						<br><span class="smalltext">', $ban['explanation'], '</span>';

			echo '
					</dt>';
		}
	}

	echo '
					<dt>', $txt['date_registered'], ': </dt>
					<dd>', $context['member']['registered'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if ($context['can_see_ip'])
	{
		if (!empty($context['member']['ip']))
		echo '
					<dt>', $txt['ip'], ': </dt>
					<dd><a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=', $context['member']['ip'], ';u=', $context['member']['id'], '">', $context['member']['ip'], '</a></dd>';

		if (empty($modSettings['disableHostnameLookup']) && !empty($context['member']['ip']))
			echo '
					<dt>', $txt['hostname'], ': </dt>
					<dd>', $context['member']['hostname'], '</dd>';
	}

	echo '
					<dt>', $txt['local_time'], ':</dt>
					<dd>', $context['member']['local_time'], '</dd>';

	if (!empty($modSettings['userLanguage']) && !empty($context['member']['language']))
		echo '
					<dt>', $txt['language'], ':</dt>
					<dd>', $context['member']['language'], '</dd>';

	if ($context['member']['show_last_login'])
		echo '
					<dt>', $txt['lastLoggedIn'], ': </dt>
					<dd>', $context['member']['last_login'], (!empty($context['member']['is_hidden']) ? ' (' . $txt['hidden'] . ')' : ''), '</dd>';

	echo '
				</dl>';

	// Are there any custom profile fields for above the signature?
	if (!empty($context['print_custom_fields']['above_signature']))
	{
		echo '
				<div class="custom_fields_above_signature">
					<ul class="reset nolist">';

		foreach ($context['print_custom_fields']['above_signature'] as $field)
			if (!empty($field['output_html']))
				echo '
						<li>', $field['output_html'], '</li>';

		echo '
					</ul>
				</div>';
	}

	// Show the users signature.
	if ($context['signature_enabled'] && !empty($context['member']['signature']))
		echo '
				<div class="signature">
					<h5>', $txt['signature'], ':</h5>
					', $context['member']['signature'], '
				</div>';

	// Are there any custom profile fields for below the signature?
	if (!empty($context['print_custom_fields']['below_signature']))
	{
		echo '
				<div class="custom_fields_below_signature">
					<ul class="reset nolist">';

		foreach ($context['print_custom_fields']['below_signature'] as $field)
			if (!empty($field['output_html']))
				echo '
						<li>', $field['output_html'], '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
		</div>
	</div>
<div class="clear"></div>';
}

// Template for showing all the posts of the user, in chronological order.
function template_showPosts()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', (!isset($context['attachments']) && empty($context['is_topics']) ? $txt['showMessages'] : (!empty($context['is_topics']) ? $txt['showTopics'] : $txt['showAttachments'])), ' - ', $context['member']['name'], '
			</h3>
		</div>', !empty($context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . $context['page_index'] . '</div>
		</div>' : '';

	// Are we displaying posts or attachments?
	if (!isset($context['attachments']))
	{
		// For every post to be displayed, give it its own div, and show the important details of the post.
		foreach ($context['posts'] as $post)
		{
			echo '
			<div class="', $post['css_class'] ,'">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5><strong><a href="', $scripturl, '?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="', $scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong></h5>
					<span class="smalltext">', $post['time'], '</span>
				</div>
				<div class="list_posts">';

			if (!$post['approved'])
				echo '
					<div class="approve_post">
						<em>', $txt['post_awaiting_approval'], '</em>
					</div>';

			echo '
					', $post['body'], '
				</div>';

			if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
				echo '
				<div class="floatright">
					<ul class="quickbuttons">';

			// If they *can* reply?
			if ($post['can_reply'])
				echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span class="generic_icons reply_button"></span>', $txt['reply'], '</a></li>';

			// If they *can* quote?
			if ($post['can_quote'])
				echo '
						<li><a href="', $scripturl . '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span class="generic_icons quote"></span>', $txt['quote_action'], '</a></li>';

			// How about... even... remove it entirely?!
			if ($post['can_delete'])
				echo '
						<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';profile;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['remove_message'] ,'" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['remove'], '</a></li>';

			if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
				echo '
					</ul>
				</div>';

			echo '
			</div>';
		}
	}
	else
		template_show_list('attachments');

	// No posts? Just end with a informative message.
	if ((isset($context['attachments']) && empty($context['attachments'])) || (!isset($context['attachments']) && empty($context['posts'])))
		echo '
			<div class="windowbg2">
				', isset($context['attachments']) ? $txt['show_attachments_none'] : ($context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none']), '
			</div>
		</div>';

	// Show more page numbers.
	if (!empty($context['page_index']))
		echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}

function template_showAlerts()
{
	global $context, $txt, $scripturl;

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
		<div class="infobox">
			', $context['update_message'], '.
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
			', $txt['alerts'], ' - ', $context['member']['name'], '
			</h3>
		</div>';

	if (empty($context['alerts']))
		echo '
		<div class="information">
			', $txt['alerts_none'], '
		</div>';

	else
	{
		// Start the form.
		echo '
		<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;save" method="post" accept-charset="', $context['character_set'], '" id="mark_all">
			<table id="alerts" class="table_grid">';

		foreach ($context['alerts'] as $id => $alert)
		{
			echo '
				<tr class="windowbg">
					<td>', $alert['text'], '</td>
					<td>', $alert['time'], '</td>
					<td>
						<ul class="quickbuttons">
							<li><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;do=remove;aid= ', $id ,';', $context['session_var'], '=', $context['session_id'], '" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['delete'] ,'</a></li>
							<li><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;do=', ($alert['is_read'] != 0 ? 'unread' : 'read') ,';aid= ', $id ,';', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons ', $alert['is_read'] != 0 ? 'unread_button' : 'read_button','"></span>', ($alert['is_read'] != 0 ? $txt['mark_unread'] : $txt['mark_read_short']),'</a></li>
							<li><input type="checkbox" name="mark[', $id ,']" value="', $id ,'"></li>
						</ul>
					</td>
				</tr>';
		}

		echo '
			</table>
			<div class="pagesection">
				<div class="floatleft">
					', $context['pagination'] ,'
				</div>
				<div class="floatright">
					', $txt['check_all'] ,': <input type="checkbox" name="select_all" id="select_all">
					<select name="mark_as">
						<option value="read">', $txt['quick_mod_markread'] ,'</option>
						<option value="unread">', $txt['quick_mod_markunread'] ,'</option>
						<option value="remove">', $txt['quick_mod_remove'] ,'</option>
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" name="req" value="', $txt['quick_mod_go'] ,'" class="button_submit you_sure">
				</div>
			</div>
		</form>';
	}
}

// Template for showing all the drafts of the user.
function template_showDrafts()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['drafts'], ' - ', $context['member']['name'], '
			</h3>
		</div>', !empty($context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . $context['page_index'] . '</div>
		</div>' : '';

	// No drafts? Just show an informative message.
	if (empty($context['drafts']))
		echo '
		<div class="tborder windowbg2 padding centertext">
			', $txt['draft_none'], '
		</div>';
	else
	{
		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach ($context['drafts'] as $draft)
		{
			echo '
				<div class="windowbg">
					<div class="counter">', $draft['counter'], '</div>
					<div class="topic_details">
						<h5><strong><a href="', $scripturl, '?board=', $draft['board']['id'], '.0">', $draft['board']['name'], '</a> / ', $draft['topic']['link'], '</strong> &nbsp; &nbsp;';

			if (!empty($draft['sticky']))
				echo '<span class="generic_icons sticky" title="', $txt['sticky_topic'], '"></span>';

			if (!empty($draft['locked']))
				echo '<span class="generic_icons lock" title="', $txt['locked_topic'], '"></span>';

			echo '
						</h5>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['on'], ':</strong> ', $draft['time'], '&nbsp;&#187;</span>
					</div>
					<div class="list_posts">
						', $draft['body'], '
					</div>
				<div class="floatright">
					<ul class="quickbuttons">
						<li><a href="', $scripturl, '?action=post;', (empty($draft['topic']['id']) ? 'board=' . $draft['board']['id'] : 'topic=' . $draft['topic']['id']), '.0;id_draft=', $draft['id_draft'], '"><span class="generic_icons reply_button"></span>', $txt['draft_edit'], '</a></li>
						<li><a href="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=showdrafts;delete=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['draft_remove'] ,'" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['draft_delete'], '</a></li>
					</ul>
				</div>
			</div>';
		}
	}

	// Show page numbers.
	echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}

// Template for showing all the buddies of the current user.
function template_editBuddies()
{
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $context['member']['name']), '</div>';
	elseif (!empty($context['saved_failed']))
		echo '
					<div class="errorbox">', $context['saved_failed'], '</div>';

	echo '
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="generic_icons people icon"></span> ', $txt['editBuddies'], '
			</h3>
		</div>
		<table class="table_grid">
			<tr class="title_bar">
				<th scope="col" width="15%">', $txt['name'], '</th>
				<th scope="col">', $txt['status'], '</th>';

	if (allowedTo('moderate_forum'))
		echo '
				<th scope="col">', $txt['email'], '</th>';

	if (!empty($context['custom_pf']))
		foreach ($context['custom_pf'] as $column)
				echo '<th scope="col">', $column['label'], '</th>';

	echo '
				<th scope="col">', $txt['remove'], '</th>
			</tr>';

	// If they don't have any buddies don't list them!
	if (empty($context['buddies']))
		echo '
			<tr class="windowbg">
				<td colspan="10"><strong>', $txt['no_buddies'], '</strong></td>
			</tr>';

		// Now loop through each buddy showing info on each.
	else
	{
		foreach ($context['buddies'] as $buddy)
		{
			echo '
				<tr class="windowbg">
					<td>', $buddy['link'], '</td>
					<td><a href="', $buddy['online']['href'], '"><span class="' . ($buddy['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $buddy['online']['text'] . '"></span></a></td>';

			if ($buddy['show_email'])
				echo '
					<td><a href="mailto:' . $buddy['email'] . '" rel="nofollow"><span class="generic_icons mail icon" title="' . $txt['email'] . ' ' . $buddy['name'] . '"></span></a></td>';

			// Show the custom profile fields for this user.
			if (!empty($context['custom_pf']))
				foreach ($context['custom_pf'] as $key => $column)
					echo '
						<td class="lefttext">', $buddy['options'][$key], '</td>';

			echo '
					<td><a href="', $scripturl, '?action=profile;area=lists;sa=buddies;u=', $context['id_member'], ';remove=', $buddy['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons delete" title="', $txt['buddy_remove'], '"></span></a></td>
				</tr>';
		}
	}

	echo '
		</table>
	</div>';

	// Add a new buddy?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['buddy_add'], '</h3>
		</div>
		<dl class="settings windowbg">
			<dt>
				<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
			</dt>
			<dd>
				<input type="text" name="new_buddy" id="new_buddy" size="30" class="input_text">
				<input type="submit" value="', $txt['buddy_add_button'], '" class="button_submit floatnone">
			</dd>
		</dl>';

	if (!empty($context['token_check']))
		echo '
			<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<script><!-- // --><![CDATA[
		var oAddBuddySuggest = new smc_AutoSuggest({
			sSelf: \'oAddBuddySuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'new_buddy\',
			sControlId: \'new_buddy\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

// Template for showing the ignore list of the current user.
function template_editIgnoreList()
{
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $context['member']['name']), '</div>';
	elseif (!empty($context['saved_failed']))
		echo '
					<div class="errorbox">', $context['saved_failed'], '</div>';

	echo '
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $txt['editIgnoreList'], '
			</h3>
		</div>
		<table class="table_grid">
			<tr class="title_bar">
				<th scope="col" width="20%">', $txt['name'], '</th>
				<th scope="col">', $txt['status'], '</th>';

	if (allowedTo('moderate_forum'))
		echo '
				<th scope="col">', $txt['email'], '</th>';

	echo '
				<th scope="col">', $txt['ignore_remove'] ,'</th>
			</tr>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
			<tr class="windowbg">
				<td colspan="8"><strong>', $txt['no_ignore'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	foreach ($context['ignore_list'] as $member)
	{
		echo '
			<tr class="windowbg">
				<td>', $member['link'], '</td>
				<td><a href="', $member['online']['href'], '"><span class="' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $member['online']['text'] . '"></span></a></td>';

		if ($member['show_email'])
			echo '
				<td><a href="mailto:' . $member['email'] . '" rel="nofollow"><span class="generic_icons mail icon" title="' . $txt['email'] . ' ' . $member['name'] . '"></span></a></td>';
		echo '
				<td><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons delete" title="', $txt['ignore_remove'], '"></span></a></td>
			</tr>';
	}

	echo '
		</table>
	</div>';

	// Add to the ignore list?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['ignore_add'], '</h3>
		</div>
		<dl class="settings windowbg">
			<dt>
				<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
			</dt>
			<dd>
				<input type="text" name="new_ignore" id="new_ignore" size="25" class="input_text">
			</dd>
		</dl>';

	if (!empty($context['token_check']))
		echo '
		<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="submit" value="', $txt['ignore_add_button'], '" class="button_submit">
	</form>
	<script><!-- // --><![CDATA[
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSelf: \'oAddIgnoreSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'new_ignore\',
			sControlId: \'new_ignore\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

// This template shows an admin information on a users IP addresses used and errors attributed to them.
function template_trackActivity()
{
	global $context, $scripturl, $txt;

	// The first table shows IP information about the user.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['view_ips_by'], ' ', $context['member']['name'], '</h3>
		</div>';

	// The last IP the user used.
	echo '
		<div id="tracking" class="windowbg2">
			<dl class="noborder">
				<dt>', $txt['most_recent_ip'], ':
					', (empty($context['last_ip2']) ? '' : '<br>
					<span class="smalltext">(<a href="' . $scripturl . '?action=helpadmin;help=whytwoip" onclick="return reqOverlayDiv(this.href);">' . $txt['why_two_ip_address'] . '</a>)</span>'), '
				</dt>
				<dd>
					<a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=', $context['last_ip'], ';u=', $context['member']['id'], '">', $context['last_ip'], '</a>';

	// Second address detected?
	if (!empty($context['last_ip2']))
		echo '
					, <a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=', $context['last_ip2'], ';u=', $context['member']['id'], '">', $context['last_ip2'], '</a>';

	echo '
				</dd>';

	// Lists of IP addresses used in messages / error messages.
	echo '
				<dt>', $txt['ips_in_messages'], ':</dt>
				<dd>
					', (count($context['ips']) > 0 ? implode(', ', $context['ips']) : '(' . $txt['none'] . ')'), '
				</dd>
				<dt>', $txt['ips_in_errors'], ':</dt>
				<dd>
					', (count($context['ips']) > 0 ? implode(', ', $context['error_ips']) : '(' . $txt['none'] . ')'), '
				</dd>';

	// List any members that have used the same IP addresses as the current member.
	echo '
				<dt>', $txt['members_in_range'], ':</dt>
				<dd>
					', (count($context['members_in_range']) > 0 ? implode(', ', $context['members_in_range']) : '(' . $txt['none'] . ')'), '
				</dd>
			</dl>
		</div>
		<br>';

	// Show the track user list.
	template_show_list('track_user_list');
}

// The template for trackIP, allowing the admin to see where/who a certain IP has been used.
function template_trackIP()
{
	global $context, $txt;

	// This function always defaults to the last IP used by a member but can be set to track any IP.
	// The first table in the template gives an input box to allow the admin to enter another IP to track.
	echo '
	<div class="tborder">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['trackIP'], '</h3>
		</div>
		<div class="windowbg2">
			<form action="', $context['base_url'], '" method="post" accept-charset="', $context['character_set'], '">
				<dl class="settings">
					<dt>
						<label for="searchip"><strong>', $txt['enter_ip'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="searchip" value="', $context['ip'], '" class="input_text">
					</dd>
				</dl>
				<input type="submit" value="', $txt['trackIP'], '" class="button_submit">
			</form>
		</div>
	</div>
	<br>';

	// The table inbetween the first and second table shows links to the whois server for every region.
	if ($context['single_ip'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['whois_title'], ' ', $context['ip'], '</h3>
			</div>
			<div class="windowbg2">';
			foreach ($context['whois_servers'] as $server)
			echo '
				<a href="', $server['url'], '" target="_blank" class="new_win"', isset($context['auto_whois_server']) && $context['auto_whois_server']['name'] == $server['name'] ? ' style="font-weight: bold;"' : '', '>', $server['name'], '</a><br>';
			echo '
			</div>
			<br>';
	}

	// The second table lists all the members who have been logged as using this IP address.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['members_from_ip'], ' ', $context['ip'], '</h3>
		</div>';
	if (empty($context['ips']))
		echo '
		<p class="windowbg2 description"><em>', $txt['no_members_from_ip'], '</em></p>';
	else
	{
		echo '
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col">', $txt['ip_address'], '</th>
					<th scope="col">', $txt['display_name'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Loop through each of the members and display them.
		foreach ($context['ips'] as $ip => $memberlist)
			echo '
				<tr class="windowbg">
					<td><a href="', $context['base_url'], ';searchip=', $ip, '">', $ip, '</a></td>
					<td>', implode(', ', $memberlist), '</td>
				</tr>';

		echo '
			</tbody>
		</table>';
	}

	echo '
	<br>';

	template_show_list('track_message_list');

	echo '<br>';

	template_show_list('track_user_list');
}

function template_showPermissions()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $txt['showPermissions'], '
			</h3>
		</div>';

	if ($context['member']['has_all_permissions'])
	{
		echo '
		<p class="information">', $txt['showPermissions_all'], '</p>';
	}
	else
	{
		echo '
		<p class="information">',$txt['showPermissions_help'],'</p>
		<div id="permissions" class="flow_hidden">';

		if (!empty($context['no_access_boards']))
		{
			echo '
				<div class="cat_bar">
					<h3 class="catbg">', $txt['showPermissions_restricted_boards'], '</h3>
				</div>
				<div class="windowbg smalltext">
					', $txt['showPermissions_restricted_boards_desc'], ':<br>';
				foreach ($context['no_access_boards'] as $no_access_board)
					echo '
						<a href="', $scripturl, '?board=', $no_access_board['id'], '.0">', $no_access_board['name'], '</a>', $no_access_board['is_last'] ? '' : ', ';
				echo '
				</div>';
		}

		// General Permissions section.
		echo '
				<div class="tborder">
					<div class="cat_bar">
						<h3 class="catbg">', $txt['showPermissions_general'], '</h3>
					</div>';
		if (!empty($context['member']['permissions']['general']))
		{
			echo '
					<table class="table_grid">
						<thead>
							<tr class="title_bar">
								<th class="lefttext" scope="col" width="50%">', $txt['showPermissions_permission'], '</th>
								<th class="lefttext" scope="col" width="50%">', $txt['showPermissions_status'], '</th>
							</tr>
						</thead>
						<tbody>';

			foreach ($context['member']['permissions']['general'] as $permission)
			{
				echo '
							<tr class="windowbg">
								<td title="', $permission['id'], '">
									', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
								</td>
								<td class="smalltext">';

				if ($permission['is_denied'])
					echo '
									<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']),'</span>';
				else
					echo '
									', $txt['showPermissions_given'], ':&nbsp;', implode(', ', $permission['groups']['allowed']);

					echo '
								</td>
							</tr>';
			}
			echo '
						</tbody>
					</table>
				</div><br>';
		}
		else
			echo '
			<p class="windowbg2 description">', $txt['showPermissions_none_general'], '</p>';

		// Board permission section.
		echo '
			<div class="tborder">
				<form action="' . $scripturl . '?action=profile;u=', $context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="', $context['character_set'], '">
					<div class="cat_bar">
						<h3 class="catbg">
							<a id="board_permissions"></a>', $txt['showPermissions_select'], ':
							<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
								<option value="0"', $context['board'] == 0 ? ' selected' : '', '>', $txt['showPermissions_global'], '&nbsp;</option>';
				if (!empty($context['boards']))
					echo '
								<option value="" disabled>---------------------------</option>';

				// Fill the box with any local permission boards.
				foreach ($context['boards'] as $board)
					echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['name'], ' (', $board['profile_name'], ')</option>';

				echo '
							</select>
						</h3>
					</div>
				</form>';
		if (!empty($context['member']['permissions']['board']))
		{
			echo '
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th class="lefttext" scope="col" width="50%">', $txt['showPermissions_permission'], '</th>
							<th class="lefttext" scope="col" width="50%">', $txt['showPermissions_status'], '</th>
						</tr>
					</thead>
					<tbody>';
			foreach ($context['member']['permissions']['board'] as $permission)
			{
				echo '
						<tr class="windowbg">
							<td title="', $permission['id'], '">
								', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
							</td>
							<td class="smalltext">';

				if ($permission['is_denied'])
				{
					echo '
								<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']), '</span>';
				}
				else
				{
					echo '
								', $txt['showPermissions_given'], ': &nbsp;', implode(', ', $permission['groups']['allowed']);
				}
				echo '
							</td>
						</tr>';
			}
			echo '
					</tbody>
				</table>';
		}
		else
			echo '
			<p class="windowbg2 description">', $txt['showPermissions_none_board'], '</p>';
	echo '
			</div>
		</div>';
	}
}

// Template for user statistics, showing graphs and the like.
function template_statPanel()
{
	global $context, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview" class="roundframe">
		<div id="generalstats">
			<dl class="stats">
				<dt>', $txt['statPanel_total_time_online'], ':</dt>
				<dd>', $context['time_logged_in'], '</dd>
				<dt>', $txt['statPanel_total_posts'], ':</dt>
				<dd>', $context['num_posts'], ' ', $txt['statPanel_posts'], '</dd>
				<dt>', $txt['statPanel_total_topics'], ':</dt>
				<dd>', $context['num_topics'], ' ', $txt['statPanel_topics'], '</dd>
				<dt>', $txt['statPanel_users_polls'], ':</dt>
				<dd>', $context['num_polls'], ' ', $txt['statPanel_polls'], '</dd>
				<dt>', $txt['statPanel_users_votes'], ':</dt>
				<dd>', $context['num_votes'], ' ', $txt['statPanel_votes'], '</dd>
			</dl>
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div id="activitytime" class="flow_hidden">
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="generic_icons history"></span> ', $txt['statPanel_activityTime'], '
				</h3>
			</div>';

	// If they haven't post at all, don't draw the graph.
	if (empty($context['posts_by_time']))
		echo '
			<span class="centertext">', $txt['statPanel_noPosts'], '</span>';
	// Otherwise do!
	else
	{
		echo '
			<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
		{
			echo '
				<li', $time_of_day['is_last'] ? ' class="last"' : '', '>
					<div class="bar" style="padding-top: ', ((int) (100 - $time_of_day['relative_percent'])), 'px;" title="', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '">
						<div style="height: ', (int) $time_of_day['relative_percent'], 'px;">
							<span>', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '</span>
						</div>
					</div>
					<span class="stats_hour">', $time_of_day['hour_format'], '</span>
				</li>';
		}

		echo '

			</ul>';
	}

	echo '
			<span class="clear">
		</div>';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="generic_icons replies"></span> ', $txt['statPanel_topBoards'], '
					</h3>
				</div>';

	if (empty($context['popular_boards']))
		echo '
				<span class="centertext">', $txt['statPanel_noPosts'], '</span>';

	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach ($context['popular_boards'] as $board)
		{
			echo '
					<dt>', $board['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($board['posts_percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '">
							', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '
						</div>
						', empty($context['hide_num_posts']) ? $board['posts'] : '', '
					</dd>';
		}

		echo '
				</dl>';
	}
	echo '
			</div>';
	echo '
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="generic_icons replies"></span> ', $txt['statPanel_topBoardsActivity'], '
					</h3>
				</div>';

	if (empty($context['board_activity']))
		echo '
				<span>', $txt['statPanel_noPosts'], '</span>';
	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach ($context['board_activity'] as $activity)
		{
			echo '
					<dt>', $activity['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($activity['percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
							', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '
						</div>
						', $activity['percent'], '%
					</dd>';
		}

		echo '
				</dl>';
	}
	echo '
			</div>
		</div>';

	echo '
	</div>';
}

// Template for editing profile options.
function template_edit_options()
{
	global $context, $scripturl, $txt, $modSettings;

	// The main header!
	// because some browsers ignore autocomplete=off and fill username in display name and/ or email field, fake them out.
	$url = !empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member'];
	$url = $context['require_password'] && !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($url, array('http://' => 'https://')) : $url;

	echo '
		<form action="', $url, '" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data"', ($context['menu_item_selected'] == 'account' ? ' autocomplete="off"' : ''), '>
			<div style="position:absolute; top:-100px;"><input type="text" id="autocompleteFakeName"/><input type="password" id="autocompleteFakePassword"/></div>
			<div class="cat_bar">
				<h3 class="catbg profile_hd">';

		// Don't say "Profile" if this isn't the profile...
		if (!empty($context['profile_header_text']))
			echo '
					', $context['profile_header_text'];
		else
			echo '
					', $txt['profile'];

		echo '
				</h3>
			</div>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<p class="information">', $context['page_desc'], '</p>';

	echo '
			<div class="roundframe">';

	// Any bits at the start?
	if (!empty($context['profile_prehtml']))
		echo '
				<div>', $context['profile_prehtml'], '</div>';

	if (!empty($context['profile_fields']))
		echo '
				<dl>';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach ($context['profile_fields'] as $key => $field)
	{
		// We add a little hack to be sure we never get more than one hr in a row!
		if ($lastItem == 'hr' && $field['type'] == 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] == 'hr')
		{
			echo '
				</dl>
				<hr width="100%" size="1" class="hrcolor clear">
				<dl>';
		}
		elseif ($field['type'] == 'callback')
		{
			if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
			{
				$callback_func = 'template_profile_' . $field['callback_func'];
				$callback_func();
			}
		}
		else
		{
			echo '
					<dt>
						<strong', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['type'] !== 'label' ? '<label for="' . $key . '">' : '', $field['label'], $field['type'] !== 'label' ? '</label>' : '', '</strong>';

			// Does it have any subtext to show?
			if (!empty($field['subtext']))
				echo '
						<br>
						<span class="smalltext">', $field['subtext'], '</span>';

			echo '
					</dt>
					<dd>';

			// Want to put something infront of the box?
			if (!empty($field['preinput']))
				echo '
						', $field['preinput'];

			// What type of data are we showing?
			if ($field['type'] == 'label')
				echo '
						', $field['value'];

			// Maybe it's a text box - very likely!
			elseif (in_array($field['type'], array('int', 'float', 'text', 'password', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number', 'time', 'url')))
			{
				if ($field['type'] == 'int' || $field['type'] == 'float')
					$type = 'number';
				else
					$type = $field['type'];
				$step = $field['type'] == 'float' ? ' step="0.1"' : '';


				echo '
						<input type="', $type, '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '"', $step, '>';
			}
			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
						<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" class="input_check" ', $field['input_attr'], '>';

			// Always fun - select boxes!
			elseif ($field['type'] == 'select')
			{
				echo '
						<select name="', $key, '" id="', $key, '">';

				if (isset($field['options']))
				{
					// Is this some code to generate the options?
					if (!is_array($field['options']))
						$field['options'] = $field['options']();
					// Assuming we now have some!
					if (is_array($field['options']))
						foreach ($field['options'] as $value => $name)
							echo '
								<option value="', $value, '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
				}

				echo '
						</select>';
			}

			// Something to end with?
			if (!empty($field['postinput']))
				echo '
							', $field['postinput'];

			echo '
					</dd>';
		}
	}

	if (!empty($context['profile_fields']))
		echo '
				</dl>';

	// Are there any custom profile fields - if so print them!
	if (!empty($context['custom_fields']))
	{
		if ($lastItem != 'hr')
			echo '
				<hr width="100%" size="1" class="hrcolor clear">';

		echo '
				<dl>';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
					<dt>
						<strong>', $field['name'], ': </strong><br>
						<span class="smalltext">', $field['desc'], '</span>
					</dt>
					<dd>
						', $field['input_html'], '
					</dd>';
		}

		echo '
					</dl>';

	}

	// Any closing HTML?
	if (!empty($context['profile_posthtml']))
		echo '
				<div>', $context['profile_posthtml'], '</div>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
				<dl>
					<dt>
						<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], ': </label></strong><br>
						<span class="smalltext">', $txt['required_security_reasons'], '</span>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" id="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password">
					</dd>
				</dl>';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
				<input type="submit" name="save" value="', $context['submit_button_text'], '" class="button_submit">';
	else
		echo '
				<input type="submit" name="save" value="', $txt['change_profile'], '" class="button_submit">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>';

	// Any final spellchecking stuff?
	if (!empty($context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="', $context['character_set'], '" target="spellWindow" action="', $scripturl, '?action=spellcheck"><input type="hidden" name="spellstring" value=""></form>';
}

// Personal Message settings.
function template_profile_pm_settings()
{
	global $context, $modSettings, $txt;

	echo '
								<dt>
									<label for="pm_prefs">', $txt['pm_display_mode'], ':</label>
								</dt>
								<dd>
									<select name="pm_prefs" id="pm_prefs">
										<option value="0"', $context['display_mode'] == 0 ? ' selected' : '', '>', $txt['pm_display_mode_all'], '</option>
										<option value="1"', $context['display_mode'] == 1 ? ' selected' : '', '>', $txt['pm_display_mode_one'], '</option>
										<option value="2"', $context['display_mode'] == 2 ? ' selected' : '', '>', $txt['pm_display_mode_linked'], '</option>
									</select>
								</dd>
								<dt>
									<label for="view_newest_pm_first">', $txt['recent_pms_at_top'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[view_newest_pm_first]" value="0">
										<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked' : '', ' class="input_check">
								</dd>
						</dl>
						<hr>
						<dl>
								<dt>
										<label for="pm_receive_from">', $txt['pm_receive_from'], '</label>
								</dt>
								<dd>
										<select name="pm_receive_from" id="pm_receive_from">
												<option value="0"', empty($context['receive_from']) || (empty($modSettings['enable_buddylist']) && $context['receive_from'] < 3) ? ' selected' : '', '>', $txt['pm_receive_from_everyone'], '</option>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
												<option value="1"', !empty($context['receive_from']) && $context['receive_from'] == 1 ? ' selected' : '', '>', $txt['pm_receive_from_ignore'], '</option>
												<option value="2"', !empty($context['receive_from']) && $context['receive_from'] == 2 ? ' selected' : '', '>', $txt['pm_receive_from_buddies'], '</option>';

	echo '
												<option value="3"', !empty($context['receive_from']) && $context['receive_from'] > 2 ? ' selected' : '', '>', $txt['pm_receive_from_admins'], '</option>
										</select>
								</dd>
								<dt>
										<label for="popup_messages">', $txt['popup_messages'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[popup_messages]" value="0">
										<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty($context['member']['options']['popup_messages']) ? ' checked' : '', ' class="input_check">
								</dd>
						</dl>
						<hr>
						<dl>
								<dt>
										<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
										<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '', ' class="input_check">
								</dd>';

}

// Template for showing theme settings. Note: template_options() actually adds the theme specific options.
function template_profile_theme_settings()
{
	global $context, $modSettings, $txt;

	echo '
							<dt>
								<label for="show_children">', $txt['show_children'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_children]" value="0">
								<input type="checkbox" name="default_options[show_children]" id="show_children" value="1"', !empty($context['member']['options']['show_children']) ? ' checked' : '', ' class="input_check">
							</dd>
							<dt>
								<label for="show_no_avatars">', $txt['show_no_avatars'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_avatars]" value="0">
								<input type="checkbox" name="default_options[show_no_avatars]" id="show_no_avatars" value="1"', !empty($context['member']['options']['show_no_avatars']) ? ' checked' : '', ' class="input_check">
							</dd>
							<dt>
								<label for="show_no_signatures">', $txt['show_no_signatures'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_signatures]" value="0">
								<input type="checkbox" name="default_options[show_no_signatures]" id="show_no_signatures" value="1"', !empty($context['member']['options']['show_no_signatures']) ? ' checked' : '', ' class="input_check">
							</dd>';

	if (!empty($modSettings['allow_no_censored']))
		echo '
							<dt>
								<label for="show_no_censored">' . $txt['show_no_censored'] . '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_censored]" value="0">
								<input type="checkbox" name="default_options[show_no_censored]" id="show_no_censored" value="1"' . (!empty($context['member']['options']['show_no_censored']) ? ' checked' : '') . ' class="input_check">
							</dd>';

	echo '
							<dt>
								<label for="return_to_post">', $txt['return_to_post'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[return_to_post]" value="0">
								<input type="checkbox" name="default_options[return_to_post]" id="return_to_post" value="1"', !empty($context['member']['options']['return_to_post']) ? ' checked' : '', ' class="input_check">
							</dd>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
							<dt>
								<label for="posts_apply_ignore_list">', $txt['posts_apply_ignore_list'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[posts_apply_ignore_list]" value="0">
								<input type="checkbox" name="default_options[posts_apply_ignore_list]" id="posts_apply_ignore_list" value="1"', !empty($context['member']['options']['posts_apply_ignore_list']) ? ' checked' : '', ' class="input_check">
							</dd>';

	echo '
							<dt>
								<label for="view_newest_first">', $txt['recent_posts_at_top'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[view_newest_first]" value="0">
								<input type="checkbox" name="default_options[view_newest_first]" id="view_newest_first" value="1"', !empty($context['member']['options']['view_newest_first']) ? ' checked' : '', ' class="input_check">
							</dd>';

	// Choose WYSIWYG settings?
	if (empty($modSettings['disable_wysiwyg']))
		echo '
							<dt>
								<label for="wysiwyg_default">', $txt['wysiwyg_default'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[wysiwyg_default]" value="0">
								<input type="checkbox" name="default_options[wysiwyg_default]" id="wysiwyg_default" value="1"', !empty($context['member']['options']['wysiwyg_default']) ? ' checked' : '', ' class="input_check">
							</dd>';

	if (empty($modSettings['disableCustomPerPage']))
	{
		echo '
							<dt>
								<label for="topics_per_page">', $txt['topics_per_page'], '</label>
							</dt>
							<dd>
								<select name="default_options[topics_per_page]" id="topics_per_page">
									<option value="0"', empty($context['member']['options']['topics_per_page']) ? ' selected' : '', '>', $txt['per_page_default'], ' (', $modSettings['defaultMaxTopics'], ')</option>
									<option value="5"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 5 ? ' selected' : '', '>5</option>
									<option value="10"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 10 ? ' selected' : '', '>10</option>
									<option value="25"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 25 ? ' selected' : '', '>25</option>
									<option value="50"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 50 ? ' selected' : '', '>50</option>
								</select>
							</dd>
							<dt>
								<label for="messages_per_page">', $txt['messages_per_page'], '</label>
							</dt>
							<dd>
								<select name="default_options[messages_per_page]" id="messages_per_page">
									<option value="0"', empty($context['member']['options']['messages_per_page']) ? ' selected' : '', '>', $txt['per_page_default'], ' (', $modSettings['defaultMaxMessages'], ')</option>
									<option value="5"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 5 ? ' selected' : '', '>5</option>
									<option value="10"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 10 ? ' selected' : '', '>10</option>
									<option value="25"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 25 ? ' selected' : '', '>25</option>
									<option value="50"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 50 ? ' selected' : '', '>50</option>
								</select>
							</dd>';
	}

	if (!empty($modSettings['cal_enabled']))
		echo '
							<dt>
								<label for="calendar_start_day">', $txt['calendar_start_day'], ':</label>
							</dt>
							<dd>
								<select name="default_options[calendar_start_day]" id="calendar_start_day">
									<option value="0"', empty($context['member']['options']['calendar_start_day']) ? ' selected' : '', '>', $txt['days'][0], '</option>
									<option value="1"', !empty($context['member']['options']['calendar_start_day']) && $context['member']['options']['calendar_start_day'] == 1 ? ' selected' : '', '>', $txt['days'][1], '</option>
									<option value="6"', !empty($context['member']['options']['calendar_start_day']) && $context['member']['options']['calendar_start_day'] == 6 ? ' selected' : '', '>', $txt['days'][6], '</option>
								</select>
							</dd>';

	if ((!empty($modSettings['drafts_post_enabled']) || !empty($modSettings['drafts_pm_enabled'])) && !empty($modSettings['drafts_autosave_enabled']))
		echo '
							<dt>
								<label for="drafts_autosave_enabled">', $txt['drafts_autosave_enabled'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[drafts_autosave_enabled]" value="0">
								<input type="checkbox" name="default_options[drafts_autosave_enabled]" id="drafts_autosave_enabled" value="1"', !empty($context['member']['options']['drafts_autosave_enabled']) ? ' checked' : '', ' class="input_check">
							</dd>';
	if ((!empty($modSettings['drafts_post_enabled']) || !empty($modSettings['drafts_pm_enabled'])) && !empty($modSettings['drafts_show_saved_enabled']))
		echo '
							<dt>
								<label for="drafts_show_saved_enabled">', $txt['drafts_show_saved_enabled'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[drafts_show_saved_enabled]" value="0">
								<input type="checkbox" name="default_options[drafts_show_saved_enabled]" id="drafts_show_saved_enabled" value="1"', !empty($context['member']['options']['drafts_show_saved_enabled']) ? ' checked' : '', ' class="input_check">
							</dd>';

	echo '
							<dt>
								<label for="use_editor_quick_reply">', $txt['use_editor_quick_reply'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_editor_quick_reply]" value="0">
								<input type="checkbox" name="default_options[use_editor_quick_reply]" id="use_editor_quick_reply" value="1"', !empty($context['member']['options']['use_editor_quick_reply']) ? ' checked' : '', ' class="input_check">
							</dd>
							<dt>
								<label for="display_quick_mod">', $txt['display_quick_mod'], ':</label>
							</dt>
							<dd>
								<select name="default_options[display_quick_mod]" id="display_quick_mod">
									<option value="0"', empty($context['member']['options']['display_quick_mod']) ? ' selected' : '', '>', $txt['display_quick_mod_none'], '</option>
									<option value="1"', !empty($context['member']['options']['display_quick_mod']) && $context['member']['options']['display_quick_mod'] == 1 ? ' selected' : '', '>', $txt['display_quick_mod_check'], '</option>
									<option value="2"', !empty($context['member']['options']['display_quick_mod']) && $context['member']['options']['display_quick_mod'] != 1 ? ' selected' : '', '>', $txt['display_quick_mod_image'], '</option>
								</select>
							</dd>';
}

function template_alert_configuration()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['alert_prefs'], '
			</h3>
		</div>
		<p class="information">', (empty($context['description']) ? $txt['alert_prefs_desc'] : $context['description']), '</p>
		<form action="', $scripturl, '?', $context['action'], '" id="admin_form_wrapper" method="post" accept-charset="', $context['character_set'], '" id="notify_options" class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notification_general'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
					<dt>
						<label for="notify_announcements">', $txt['notify_important_email'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0">
						<input type="checkbox" id="notify_announcements" name="notify_announcements"', !empty($context['member']['notify_announcements']) ? ' checked' : '', ' class="input_check">
					</dd>';

	if (!empty($modSettings['enable_ajax_alerts']))
		echo '
					<dt>
						<label for="notify_send_body">', $txt['notify_alert_timeout'], '</label>
					</dt>
					<dd>
						<input type="number" size="4" id="notify_alert_timeout" name="opt_alert_timeout" min="0" value="', $context['member']['alert_timeout'], '" class="input_text">
					</dd>
		';

	echo '
				</dl>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notify_what_how'], '
				</h3>
			</div>
			<table class="table_grid">
				<tr>
					<td></td>
					<td class="centercol">', $txt['receive_alert'], '</td>
					<td class="centercol">', $txt['receive_mail'], '</td>
				</tr>';

	foreach ($context['alert_types'] as $alert_group => $alerts)
	{
		echo '
				<tr class="title_bar">
					<th colspan="3">
						', $txt['alert_group_' . $alert_group];
		if (isset($context['alert_group_options'][$alert_group]))
		{
			foreach ($context['alert_group_options'][$alert_group] as $opts)
			{
				echo '
				<div class="smalltext">';
				$label = $txt['alert_opt_' . $opts[1]];
				$label_pos = isset($opts['label']) ? $opts['label'] : '';
				if ($label_pos == 'before')
					echo '
					<label for="opt_', $opts[1], '">', $label, '</label>';

				$this_value = isset($context['alert_prefs'][$opts[1]]) ? $context['alert_prefs'][$opts[1]] : 0;
				switch ($opts[0])
				{
					case 'check':
						echo '
						<input type="checkbox" name="opt_', $opts[1], '" id="opt_', $opts[1], '"', $this_value ? ' checked' : '', '>';
						break;
					case 'select':
						echo '
						<select name="opt_', $opts[1], '" id="opt_', $opts[1], '">';
						foreach ($opts['opts'] as $k => $v)
							echo '
							<option value="', $k, '"', $this_value == $k ? ' selected' : '', '>', $v, '</option>';
						echo '
						</select>';
						break;
				}

				if ($label_pos == 'after')
					echo '
					<label for="opt_', $opts[1], '">', $label, '</label>';

				echo '
				</div>';
			}
		}

		echo '
					</th>
				</tr>';
		foreach ($alerts as $alert_id => $alert_details)
		{
			echo '
				<tr class="windowbg">
					<td>', $txt['alert_' . $alert_id], isset($alert_details['help']) ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $alert_details['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help floatright"><span class="generic_icons help" title="'. $txt['help'].'"></span>' : '', '</td>';

			foreach ($context['alert_bits'] as $type => $bitmask)
			{
				echo '
					<td class="centercol">';
				$this_value = isset($context['alert_prefs'][$alert_id]) ? $context['alert_prefs'][$alert_id] : 0;
				switch ($alert_details[$type])
				{
					case 'always':
						echo '
						<input type="checkbox" checked disabled>';
						break;
					case 'yes':
						echo '
						<input type="checkbox" name="', $type, '_', $alert_id, '"', ($this_value & $bitmask) ? ' checked' : '', '>';
						break;
					case 'never':
						echo '
						<input type="checkbox" disabled>';
						break;
				}
				echo '
					</td>';
			}

			echo '
				</tr>';
		}
	}

	echo '
			</table>
			<br>
			<div>
				<input id="notify_submit" type="submit" name="notify_submit" value="', $txt['notify_save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', !empty($context['token_check']) ? '
				<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>
		<br>';
}

function template_alert_notifications_topics()
{
	global $txt;

	// The main containing header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['watched_topics'], '
			</h3>
		</div>
		<p class="information">', $txt['watched_topics_desc'], '</p>
		<br>';

	template_show_list('topic_notification_list');
}

function template_alert_notifications_boards()
{
	global $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['watched_boards'], '
			</h3>
		</div>
		<p class="information">', $txt['watched_boards_desc'], '</p>
		<br>';

	template_show_list('board_notification_list');
}

// Template for choosing group membership.
function template_groupMembership()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=groupmembership;save" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', $txt['profile'], '
				</h3>
			</div>
			<p class="information">', $txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
			<div class="infobox">
				', $context['update_message'], '.
			</div>';

	echo '
		<div id="groups">';

	// Requesting membership to a group?
	if (!empty($context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['request_group_membership'], '</h3>
				</div>
				<div class="roundframe">
					', $txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4" style="width: 99%;"></textarea>
					<div class="righttext" style="margin: 0.5em 0.5% 0 0.5%;">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '">
						<input type="submit" name="req" value="', $txt['submit_request'], '" class="button_submit">
					</div>
				</div>
			</div>';
	}
	else
	{
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['current_membergroups'], '</h3>
			</div>';

		foreach ($context['groups']['member'] as $group)
		{
			echo '
					<div class="windowbg" id="primdiv_', $group['id'], '">';

				if ($context['can_edit_primary'])
					echo '
						<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '"', $group['is_primary'] ? ' checked' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');"', $group['can_be_primary'] ? '' : ' disabled', ' class="input_radio">';

				echo '
						<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '</label>';

				// Can they leave their group?
				if ($group['can_leave'])
					echo '
						<a href="' . $scripturl . '?action=profile;save;u=' . $context['id_member'] . ';area=groupmembership;' . $context['session_var'] . '=' . $context['session_id'] . ';gid=' . $group['id'] . ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">' . $txt['leave_group'] . '</a>';

				echo '
					</div>';
		}

		if ($context['can_edit_primary'])
			echo '
			<div class="padding righttext">
				<input type="submit" value="', $txt['make_primary'], '" class="button_submit">
			</div>';

		// Any groups they can join?
		if (!empty($context['groups']['available']))
		{
			echo '
					<div class="title_bar">
						<h3 class="titlebg">', $txt['available_groups'], '</h3>
					</div>';

			foreach ($context['groups']['available'] as $group)
			{
				echo '
					<div class="windowbg">
						<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '';

				if ($group['type'] == 3)
					echo '
						<a href="', $scripturl, '?action=profile;save;u=', $context['id_member'], ';area=groupmembership;', $context['session_var'], '=', $context['session_id'], ';gid=', $group['id'], ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '" class="button floatright">', $txt['join_group'], '</a>';
				elseif ($group['type'] == 2 && $group['pending'])
					echo '
						<span class="floatright">', $txt['approval_pending'],'</span>';
				elseif ($group['type'] == 2)
					echo '
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], '" class="button floatright">', $txt['request_group'], '</a>';

				echo '
					</div>';
			}
		}

		// Javascript for the selector stuff.
		echo '
		<script><!-- // --><![CDATA[
		var prevClass = "";
		var prevDiv = "";
		function highlightSelected(box)
		{
			if (prevClass != "")
			{
				prevDiv.className = prevClass;
			}
			prevDiv = document.getElementById(box);
			prevClass = prevDiv.className;

			prevDiv.className = "windowbg";
		}';
		if (isset($context['groups']['member'][$context['primary_group']]))
			echo '
		highlightSelected("primdiv_' . $context['primary_group'] . '");';
		echo '
	// ]]></script>';
	}

	echo '
		</div>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="u" value="', $context['id_member'], '">
			</form>';
}

function template_ignoreboards()
{
	global $context, $txt, $scripturl;
	// The main containing header.
	echo '
	<form action="', $scripturl, '?action=profile;area=ignoreboards;save" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $txt['profile'], '
			</h3>
		</div>
		<p class="information">', $txt['ignoreboards_info'], '</p>
		<div class="windowbg2">
			<div class="flow_hidden">
				<ul class="ignoreboards floatleft">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);
	foreach ($context['categories'] as $category)
	{
		if ($i == $limit)
		{
			echo '
				</ul>
				<ul class="ignoreboards floatright">';

			$i++;
		}

		echo '
					<li class="category">
						<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'creator\'); return false;">', $category['name'], '</a>
						<ul>';

		foreach ($category['boards'] as $board)
		{
			if ($i == $limit)
				echo '
						</ul>
					</li>
				</ul>
				<ul class="ignoreboards floatright">
					<li class="category">
						<ul>';

			echo '
							<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
								<label for="ignore_brd', $board['id'], '"><input type="checkbox" id="brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', ' class="input_check"> ', $board['name'], '</label>
							</li>';

			$i++;
		}

		echo '
						</ul>
					</li>';
	}

	echo '
				</ul>';

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
			</div>
		</div>
	</form>
	<br>';
}

// Simple load some theme variables common to several warning templates.
function template_load_warning_variables()
{
	global $modSettings, $context;

	$context['warningBarWidth'] = 200;
	// Setup the colors - this is a little messy for theming.
	$context['colors'] = array(
		0 => 'green',
		$modSettings['warning_watch'] => 'darkgreen',
		$modSettings['warning_moderate'] => 'orange',
		$modSettings['warning_mute'] => 'red',
	);

	// Work out the starting color.
	$context['current_color'] = $context['colors'][0];
	foreach ($context['colors'] as $limit => $color)
		if ($context['member']['warning'] >= $limit)
			$context['current_color'] = $color;
}

// Show all warnings of a user?
function template_viewWarning()
{
	global $context, $txt;

	template_load_warning_variables();

	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', sprintf($txt['profile_viewwarning_for_user'], $context['member']['name']), '
			</h3>
		</div>
		<p class="information">', $txt['viewWarning_help'], '</p>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					', $context['member']['name'], '
				</dd>
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>
				</dt>
				<dd>
					<div>
						<div>
							<div style="font-size: 8pt; height: 12pt; width: ', $context['warningBarWidth'], 'px; border: 1px solid black; background-color: white; padding: 1px; position: relative;">
								<div id="warning_text" style="padding-top: 1pt; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold;">', $context['member']['warning'], '%</div>
								<div id="warning_progress" style="width: ', $context['member']['warning'], '%; height: 12pt; z-index: 1; background-color: ', $context['current_color'], ';">&nbsp;</div>
							</div>
						</div>
					</div>
				</dd>';

		// There's some impact of this?
		if (!empty($context['level_effects'][$context['current_level']]))
			echo '
				<dt>
					<strong>', $txt['profile_viewwarning_impact'], ':</strong>
				</dt>
				<dd>
					', $context['level_effects'][$context['current_level']], '
				</dd>';

		echo '
			</dl>
		</div>';

	template_show_list('view_warnings');
}

// Show a lovely interface for issuing warnings.
function template_issueWarning()
{
	global $context, $scripturl, $txt;

	template_load_warning_variables();

	echo '
	<script><!-- // --><![CDATA[
		// Disable notification boxes as required.
		function modifyWarnNotify()
		{
			disable = !document.getElementById(\'warn_notify\').checked;
			document.getElementById(\'warn_sub\').disabled = disable;
			document.getElementById(\'warn_body\').disabled = disable;
			document.getElementById(\'warn_temp\').disabled = disable;
			document.getElementById(\'new_template_link\').style.display = disable ? \'none\' : \'\';
			document.getElementById(\'preview_button\').style.display = disable ? \'none\' : \'\';
		}

		// Warn template.
		function populateNotifyTemplate()
		{
			index = document.getElementById(\'warn_temp\').value;
			if (index == -1)
				return false;

			// Otherwise see what we can do...';

	foreach ($context['notification_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";';

	echo '
		}

		function updateSlider(slideAmount)
		{
			// Also set the right effect.
			effectText = "";';

	foreach ($context['level_effects'] as $limit => $text)
		echo '
			if (slideAmount >= ', $limit, ')
				effectText = "', $text, '";';

	echo '
			setInnerHTML(document.getElementById(\'cur_level_div\'), slideAmount + \'% (\' + effectText + \')\');
		}
	// ]]></script>';

	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $context['user']['is_owner'] ? $txt['profile_warning_level'] : $txt['profile_issue_warning'], '
			</h3>
		</div>';

	if (!$context['user']['is_owner'])
		echo '
		<p class="information">', $txt['profile_warning_desc'], '</p>';

	echo '
		<div class="windowbg">
			<dl class="settings">';

	if (!$context['user']['is_owner'])
		echo '
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					<strong>', $context['member']['name'], '</strong>
				</dd>';

	echo '
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>';

	// Is there only so much they can apply?
	if ($context['warning_limit'])
		echo '
					<br><span class="smalltext">', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</span>';

	echo '
				</dt>
				<dd>
					0% <input name="warning_level" id="warning_level" type="range" min="0" max="100" step="5" value="', $context['member']['warning'], '" onchange="updateSlider(this.value)" /> 100%
					<div class="clear_left">', $txt['profile_warning_impact'], ': <span id="cur_level_div">', $context['member']['warning'], '% (', $context['level_effects'][$context['current_level']], ')</span></div>
				</dd>';

	if (!$context['user']['is_owner'])
	{
		echo '
				<dt>
					<strong>', $txt['profile_warning_reason'], ':</strong><br>
					<span class="smalltext">', $txt['profile_warning_reason_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="warn_reason" id="warn_reason" value="', $context['warning_data']['reason'], '" size="50" style="width: 80%;" class="input_text">
				</dd>
			</dl>
			<hr>
			<div id="box_preview"', !empty($context['warning_data']['body_preview']) ? '' : ' style="display:none"', '>
				<dl class="settings">
					<dt>
						<strong>', $txt['preview'] , '</strong>
					</dt>
					<dd id="body_preview">
						', !empty($context['warning_data']['body_preview']) ? $context['warning_data']['body_preview'] : '', '
					</dd>
				</dl>
				<hr>
			</div>
			<dl class="settings">
				<dt>
					<strong><label for="warn_notify">', $txt['profile_warning_notify'], ':</label></strong>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();"', $context['warning_data']['notify'] ? ' checked' : '', ' class="input_check">
				</dd>
				<dt>
					<strong><label for="warn_sub">', $txt['profile_warning_notify_subject'], ':</label></strong>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%;" class="input_text">
				</dd>
				<dt>
					<strong><label for="warn_temp">', $txt['profile_warning_notify_body'], ':</label></strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled onchange="populateNotifyTemplate();" style="font-size: x-small;">
						<option value="-1">', $txt['profile_warning_notify_template'], '</option>
						<option value="-1" disabled>------------------------------</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
					</select>
					<span class="smalltext" id="new_template_link" style="display: none;">[<a href="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" class="new_win">', $txt['profile_warning_new_template'], '</a>]</span><br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8" style="min-width: 50%; max-width: 99%;">', $context['warning_data']['notify_body'], '</textarea>
				</dd>';
	}
	echo '
			</dl>
			<div class="righttext">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="button" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit">
				<input type="submit" name="save" value="', $context['user']['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" class="button_submit">
			</div>
		</div>
	</form>';

	// Previous warnings?
	template_show_list('view_warnings');

	echo '
	<script><!-- // --><![CDATA[';

	if (!$context['user']['is_owner'])
		echo '
		modifyWarnNotify();
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
				data: {item: "warning_preview", title: $("#warn_sub").val(), body: $("#warn_body").val(), issuing: true},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#body_preview").html($(request).find(\'body\').text());
					if ($(request).find("error").text() != \'\')
					{
						$("#profile_error").css({display:""});
						var errors_html = \'<ul class="list_errors" class="reset">\';
						var errors = $(request).find(\'error\').each(function() {
							errors_html += \'<li>\' + $(this).text() + \'</li>\';
						});
						errors_html += \'</ul>\';

						$("#profile_error").html(errors_html);
					}
					else
					{
						$("#profile_error").css({display:"none"});
						$("#error_list").html(\'\');
					}
				return false;
				},
			});
			return false;
		}';

	echo '
	// ]]></script>';
}

// Template to show for deleting a users account - now with added delete post capability!
function template_deleteAccount()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=deleteaccount;save" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', $txt['deleteAccount'], '
				</h3>
			</div>';

	// If deleting another account give them a lovely info box.
	if (!$context['user']['is_owner'])
		echo '
			<p class="information">', $txt['deleteAccount_desc'], '</p>';
	echo '
			<div class="windowbg2">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
				<div class="errorbox">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if ($context['user']['is_owner'])
	{
		echo '
				<div class="alert">', $txt['own_profile_confirm'], '</div>
				<div>
					<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong>
					<input type="password" name="oldpasswrd" size="20" class="input_password">&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="submit" value="', $txt['yes'], '" class="button_submit">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
				<div class="alert">', $txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
			echo '
				<div>
					<label for="deleteVotes"><input type="checkbox" name="deleteVotes" id="deleteVotes" value="1" class="input_check"> ', $txt['deleteAccount_votes'], ':</label><br>
					<label for="deletePosts"><input type="checkbox" name="deletePosts" id="deletePosts" value="1" class="input_check"> ', $txt['deleteAccount_posts'], ':</label>
					<select name="remove_type">
						<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
						<option value="topics">', $txt['deleteAccount_topics'], '</option>
					</select>
				</div>';

		echo '
				<div>
					<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" class="input_check" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');"> ', $txt['deleteAccount_member'], '.</label>
				</div>
				<div>
					<input type="submit" value="', $txt['delete'], '" class="button_submit">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	echo '
			</div>
			<br>
		</form>';
}

// Template for the password box/save button stuck at the bottom of every profile page.
function template_profile_save()
{
	global $context, $txt;

	echo '

					<hr width="100%" size="1" class="hrcolor clear">';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br>
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password">
						</dd>
					</dl>';

	echo '
					<div class="righttext">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
						<input type="submit" value="', $txt['change_profile'], '" class="button_submit">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="u" value="', $context['id_member'], '">
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
					</div>';
}

// Small template for showing an error message upon a save problem in the profile.
function template_error_message()
{
	global $context, $txt;

	echo '
		<div class="errorbox" ', empty($context['post_errors']) ? 'style="display:none" ' : '', 'id="profile_error">';

	if (!empty($context['post_errors']))
	{
		echo '
			<span>', !empty($context['custom_error_title']) ? $context['custom_error_title'] : $txt['profile_errors_occurred'], ':</span>
			<ul id="list_errors">';

		// Cycle through each error and display an error message.
		foreach ($context['post_errors'] as $error)
			echo '
				<li>', isset($txt['profile_error_' . $error]) ? $txt['profile_error_' . $error] : $error, '</li>';

		echo '
			</ul>';
	}

	echo '
		</div>';
}

// Display a load of drop down selectors for allowing the user to change group.
function template_profile_group_manage()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<strong>', $txt['primary_membergroup'], ': </strong><br>
								<span class="smalltext">[<a href="', $scripturl, '?action=helpadmin;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);">', $txt['moderator_why_missing'], '</a>]</span>
							</dt>
							<dd>
								<select name="id_group" ', ($context['user']['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . $txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

		// Fill the select box with all primary member groups that can be assigned to a member.
		foreach ($context['member_groups'] as $member_group)
			if (!empty($member_group['can_be_primary']))
				echo '
									<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected' : '', '>
										', $member_group['name'], '
									</option>';
		echo '
								</select>
							</dd>
							<dt>
								<strong>', $txt['additional_membergroups'], ':</strong>
							</dt>
							<dd>
								<span id="additional_groupsList">
									<input type="hidden" name="additional_groups[]" value="0">';

		// For each membergroup show a checkbox so members can be assigned to more than one group.
		foreach ($context['member_groups'] as $member_group)
			if ($member_group['can_be_additional'])
				echo '
									<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', ' class="input_check"> ', $member_group['name'], '</label><br>';
		echo '
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down">', $txt['additional_membergroups_show'], '</a>
								<script><!-- // --><![CDATA[
									document.getElementById("additional_groupsList").style.display = "none";
									document.getElementById("additional_groupsLink").style.display = "";
								// ]]></script>
							</dd>';

}

// Callback function for entering a birthdate!
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
							<dt>
								<strong>', $txt['dob'], ':</strong><br>
								<span class="smalltext">', $txt['dob_year'], ' - ', $txt['dob_month'], ' - ', $txt['dob_day'], '</span>
							</dt>
							<dd>
								<input type="text" name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '" class="input_text"> -
								<input type="text" name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '" class="input_text"> -
								<input type="text" name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '" class="input_text">
							</dd>';
}

// Show the signature editing box?
function template_profile_signature_modify()
{
	global $txt, $context;

	echo '
							<dt id="current_signature" style="display:none">
								<strong>', $txt['current_signature'], ':</strong>
							</dt>
							<dd id="current_signature_display" style="display:none">
								<hr>
							</dd>';
	echo '
							<dt id="preview_signature" style="display:none">
								<strong>', $txt['signature_preview'], ':</strong>
							</dt>
							<dd id="preview_signature_display" style="display:none">
								<hr>
							</dd>';

	echo '
							<dt>
								<strong>', $txt['signature'], ':</strong><br>
								<span class="smalltext">', $txt['sig_info'], '</span><br>
								<br>';

	if ($context['show_spellchecking'])
		echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\');" class="button_submit">';

		echo '
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50" style="min-width: 50%; max-width: 99%;">', $context['member']['signature'], '</textarea><br>';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
								<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br>';

	if (!empty($context['show_preview_button']))
		echo '
						<input type="button" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '" class="button_submit">';

	if ($context['signature_warning'])
		echo '
								<span class="smalltext">', $context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script><!-- // --><![CDATA[
									var maxLength = ', $context['signature_limits']['max_length'], ';

									$(document).ready(function() {
										calcCharLeft();
										$("#preview_button").click(function() {
											return ajax_getSignaturePreview(true);
										});
									});
								// ]]></script>
							</dd>';
}

function template_profile_avatar_select()
{
	global $context, $txt, $modSettings;

	// Start with the upper menu
	echo '
							<dt>
								<strong id="personal_picture"><label for="avatar_upload_box">', $txt['personal_picture'], '</label></strong>
								', empty($modSettings['gravatarOverride']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['no_avatar'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_server_stored']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_server_stored"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['choose_avatar_gallery'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_external']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['my_own_pic'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_upload']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['avatar_will_upload'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_gravatar']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"'. ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_gravatar"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['use_gravatar'] . '</label>' : '', '
							</dt>
							<dd>';

	// If users are allowed to choose avatars stored on the server show selection boxes to choice them from.
	if (!empty($context['member']['avatar']['allow_server_stored']))
	{
		echo '
								<div id="avatar_server_stored">
									<div>
										<select name="cat" id="cat" size="10" onchange="changeSel(\'\');" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');">';
		// This lists all the file categories.
		foreach ($context['avatars'] as $avatar)
			echo '
											<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected' : ''), '>', $avatar['name'], '</option>';
		echo '
										</select>
									</div>
									<div>
										<select name="file" id="file" size="10" style="display: none;" onchange="showAvatar()" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');" disabled><option></option></select>
									</div>
									<div><img name="avatar" id="avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $modSettings['avatar_url'] . '/blank.png', '" alt="Do Nothing"></div>
									<script><!-- // --><![CDATA[
										var files = ["' . implode('", "', $context['avatar_list']) . '"];
										var avatar = document.getElementById("avatar");
										var cat = document.getElementById("cat");
										var selavatar = "' . $context['avatar_selected'] . '";
										var avatardir = "' . $modSettings['avatar_url'] . '/";
										var size = avatar.alt.substr(3, 2) + " " + avatar.alt.substr(0, 2) + String.fromCharCode(117, 98, 116);
										var file = document.getElementById("file");
										var maxHeight = ', !empty($modSettings['avatar_max_height_external']) ? $modSettings['avatar_max_height_external'] : 0, ';
										var maxWidth = ', !empty($modSettings['avatar_max_width_external']) ? $modSettings['avatar_max_width_external'] : 0, ';

										if (avatar.src.indexOf("blank.png") > -1)
											changeSel(selavatar);
										else
											previewExternalAvatar(avatar.src)

									// ]]></script>
								</div>';
	}

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
								<div id="avatar_external">
									<div class="smalltext">', $txt['avatar_by_url'], '</div>', !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_download_and_resize' ? template_max_size('external') : '', '
									<input type="text" name="userpicpersonal" size="45" value="', (!stristr($context['member']['avatar']['external'], 'gravatar://www.gravatar.com/avatar/') ? $context['member']['avatar']['external'] : 'http://'), '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value);" class="input_text" />
								</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
								<div id="avatar_upload">
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" class="input_file" accept="image/gif, image/jpeg, image/jpg, image/png">', template_max_size('upload'), '
									', (!empty($context['member']['avatar']['id_attach']) ? '<br><img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt=""><input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '">' : ''), '
								</div>';
	}

	// if the user is able to use Gravatar avatars show then the image preview
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									<img src="' . $context['member']['avatar']['href'] . '" alt="" />';

		if (empty($modSettings['gravatarAllowExtraEmail']))
			echo '
									<div class="smalltext">', $txt['gravatar_noAlternateEmail'], '</div>';
		else
		{
			// Depending on other stuff, the stored value here might have some odd things in it from other areas.
			if ($context['member']['avatar']['external'] == $context['member']['email'])
				$textbox_value = '';
			else
				$textbox_value = $context['member']['avatar']['external'];

			echo '
									<div class="smalltext">', $txt['gravatar_alternateEmail'], '</div>
									<input type="text" name="gravatarEmail" id="gravatarEmail" size="45" value="', $textbox_value, '" class="input_text" />';
		}
		echo '
								</div>';
	}

	echo '
								<script><!-- // --><![CDATA[
									', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "' . ($context['member']['avatar']['choice'] == 'server_stored' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "' . ($context['member']['avatar']['choice'] == 'external' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "' . ($context['member']['avatar']['choice'] == 'upload' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "' . ($context['member']['avatar']['choice'] == 'gravatar' ? '' : 'none') . '";' : '', '

									function swap_avatar(type)
									{
										switch(type.id)
										{
											case "avatar_choice_server_stored":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_external":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_upload":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_none":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_gravatar":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "";' : '', '
												', ($context['member']['avatar']['external'] == $context['member']['email'] || strstr($context['member']['avatar']['external'], 'http://')) ?
												'document.getElementById("gravatarEmail").value = "";' : '', '
												break;
										}
									}
								// ]]></script>
							</dd>';
}

// This is just a really little helper to avoid duplicating code unnecessarily
function template_max_size($type)
{
	global $modSettings, $txt;

	$w = !empty($modSettings['avatar_max_width_' . $type]) ? comma_format($modSettings['avatar_max_width_' . $type]) : 0;
	$h = !empty($modSettings['avatar_max_height_' . $type]) ? comma_format($modSettings['avatar_max_height_' . $type]) : 0;

	$suffix = (!empty($w) ? 'w' : '') . (!empty($h) ? 'h' : '');
	if (empty($suffix))
		return;

	echo '
									<div class="smalltext">', sprintf($txt['avatar_max_size_' . $suffix], $w, $h), '</div>';
}

// Select the time format!
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl, $settings;

	echo '
							<dt>
								<strong><label for="easyformat">', $txt['time_format'], ':</label></strong><br>
								<a href="', $scripturl, '?action=helpadmin;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>
								<span class="smalltext">&nbsp;<label for="time_format">', $txt['date_format'], '</label></span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;" style="margin-bottom: 4px;">';
	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected' : '', '>', $time_format['title'], '</option>';
	echo '
								</select><br>
								<input type="text" name="time_format" id="time_format" value="', $context['member']['time_format'], '" size="30" class="input_text">
							</dd>';
}

// Theme?
function template_profile_theme_pick()
{
	global $txt, $context, $scripturl;

	echo '
							<dt>
								<strong>', $txt['current_theme'], ':</strong>
							</dt>
							<dd>
								', $context['member']['theme']['name'], ' [<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['change'], '</a>]
							</dd>';
}

// Smiley set picker.
function template_profile_smiley_pick()
{
	global $txt, $context, $modSettings, $settings;

	echo '
							<dt>
								<strong><label for="smiley_set">', $txt['smileys_current'], ':</label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set" onchange="document.getElementById(\'smileypr\').src = this.selectedIndex == 0 ? \'', $settings['images_url'], '/blank.png\' : \'', $modSettings['smileys_url'], '/\' + (this.selectedIndex != 1 ? this.options[this.selectedIndex].value : \'', !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'], '\') + \'/smiley.gif\';">';
	foreach ($context['smiley_sets'] as $set)
		echo '
									<option value="', $set['id'], '"', $set['selected'] ? ' selected' : '', '>', $set['name'], '</option>';
	echo '
								</select> <img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'])) . '/smiley.gif' : $settings['images_url'] . '/blank.png', '" alt=":)"  style="padding-left: 20px;">
							</dd>';
}

function template_tfasetup()
{
	global $txt, $context, $scripturl, $modSettings;

	echo '
							<div class="cat_bar">
								<h3 class="catbg">', $txt['tfa_title'], '</h3>
							</div>
							<div class="roundframe">
								<div>
		', !empty($context['tfa_backup']) ? '
									<div class="smalltext error">' . $txt['tfa_backup_used_desc'] . '</div>' :
			($modSettings['tfa_mode'] == 2 ? '
									<div class="smalltext"><strong>' . $txt['tfa_forced_desc'] . '</strong></div>' : ''), '
									<div class="smalltext">', $txt['tfa_desc'], '</div>
									<div id="basicinfo" style="width: 60%">
										<form action="', $scripturl, '?action=profile;area=tfasetup" method="post">
											<div>
												<strong>', $txt['tfa_step1'], '</strong><br />
												', !empty($context['tfa_pass_error']) ? '<div class="error smalltext">' . $txt['tfa_pass_invalid'] . '</div>' : '', '
												<input type="password" name="passwd" style="width: 200px;"', !empty($context['tfa_pass_error']) ? ' class="error"' : '', !empty($context['tfa_pass_value']) ? ' value="' . $context['tfa_pass_value'] . '"' : '' ,'>
											</div>
											<div>
												<strong>', $txt['tfa_step2'], '</strong>
												<div class="smalltext">', $txt['tfa_step2_desc'], '</div>
												<div class="bbc_code" style="resize: none; border: none;">', $context['tfa_secret'], '</div>
											</div>
											<div style="margin-top: 10px;">
												<strong>', $txt['tfa_step3'] , '</strong><br />
												', !empty($context['tfa_error']) ? '<div class="error smalltext">' . $txt['tfa_code_invalid'] . '</div>' : '', '
												<input type="text" name="tfa_code" style="width: 200px;"', !empty($context['tfa_error']) ? ' class="error"' : '', !empty($context['tfa_value']) ? ' value="' . $context['tfa_value'] . '"' : '' ,'>
												<input type="submit" name="save" value="', $txt['tfa_enable'], '" class="button_submit" style="float: none;" />
											</div>
											<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />
											<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
										</form>
									</div>
									<div id="detailedinfo" style="width: 30%;">
										<img src="', $context['tfa_qr_url'], '" alt="" style="max-width: 120px;" />
									</div>
									<div class="clear"></div>';

	if (!empty($context['from_ajax']))
		echo '
									<br>
									<a href="javascript:self.close();"></a>';

	echo '
								</div>
							</div>';
}

function template_tfasetup_backup()
{
	global $context, $txt;

	echo '
							<div class="cat_bar">
								<h3 class="catbg">', $txt['tfa_backup_title'], '</h3>
							</div>
							<div class="roundframe">
								<div>
									<div class="smalltext">', $txt['tfa_backup_desc'], '</div>
									<div class="bbc_code" style="resize: none; border: none;">', $context['tfa_backup'], '</div>
								</div>
							</div>';
}

function template_profile_tfa()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
							<dt>
								<strong>', $txt['tfa_profile_label'], ':</strong>
								<br /><div class="smalltext">', $txt['tfa_profile_desc'], '</div>
							</dt>
							<dd>';
	if (!$context['tfa_enabled'] && $context['user']['is_owner'])
		echo '
								<a href="', !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($scripturl, array('http://' => 'https://')) : $scripturl, '?action=profile;area=tfasetup" id="enable_tfa">', $txt['tfa_profile_enable'], '</a>';
	elseif (!$context['tfa_enabled'])
		echo '
								', $txt['tfa_profile_disabled'];
	else
		echo '
							', sprintf($txt['tfa_profile_enabled'], $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=tfasetup;disable');
	echo '
							</dd>';
}
?>