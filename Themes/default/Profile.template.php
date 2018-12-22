<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * Minor stuff shown above the main profile - mostly used for error messages and showing that the profile update was successful.
 */
function template_profile_above()
{
	global $context;

	// Prevent Chrome from auto completing fields when viewing/editing other members profiles
	if (isBrowser('is_chrome') && !$context['user']['is_owner'])
		echo '
			<script>
				disableAutoComplete();
			</script>';

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

/**
 * Template for any HTML needed below the profile (closing off divs/tables, etc.)
 */
function template_profile_below()
{
}

/**
 * Template for showing off the spiffy popup of the menu
 */
function template_profile_popup()
{
	global $context, $scripturl;

	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()

	echo '
		<div class="profile_user_avatar floatleft">
			<a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['member']['avatar']['image'], '</a>
		</div>
		<div class="profile_user_info floatleft">
			<span class="profile_username"><a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['user']['name'], '</a></span>
			<span class="profile_group">', $context['member']['group'], '</span>
		</div>
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
		</div><!-- .profile_user_links -->';
}

/**
 * The "popup" showing the user's alerts
 */
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
		template_alerts_all_read();

	else
	{
		foreach ($context['unread_alerts'] as $id_alert => $details)
			echo '
			<div class="unread">
				', !empty($details['sender']) ? $details['sender']['avatar']['image'] : '', '
				<div class="details">
					', !empty($details['icon']) ? $details['icon'] : '', '<span>', $details['text'], '</span> - ', $details['time'], '
				</div>
			</div>';
	}

	echo '
		</div><!-- .alerts_unread -->
		<script>
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
		</script>';
}

/**
 * A simple template to say "You don't have any unread alerts".
 */
function template_alerts_all_read()
{
	global $txt;

	echo '<div class="no_unread">', $txt['alerts_no_unread'], '</div>';
}

/**
 * This template displays a user's details without any option to edit them.
 */
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
		$fields = '';
		foreach ($context['print_custom_fields']['above_member'] as $field)
			if (!empty($field['output_html']))
				$fields .= '
					<li>' . $field['output_html'] . '</li>';

		if (!empty($fields))
			echo '
			<div class="custom_fields_above_name">
				<ul>', $fields, '
				</ul>
			</div>';
	}

	echo '
			<div class="username clear">
				<h4>';

	if (!empty($context['print_custom_fields']['before_member']))
		foreach ($context['print_custom_fields']['before_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<span>', $field['output_html'], '</span>';

	echo '
					', $context['member']['name'];

	if (!empty($context['print_custom_fields']['after_member']))
		foreach ($context['print_custom_fields']['after_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<span>', $field['output_html'], '</span>';

	echo '
					<span class="position">', (!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group']), '</span>
				</h4>
			</div>
			', $context['member']['avatar']['image'];

	// Are there any custom profile fields for below the avatar?
	if (!empty($context['print_custom_fields']['below_avatar']))
	{
		$fields = '';
		foreach ($context['print_custom_fields']['below_avatar'] as $field)
			if (!empty($field['output_html']))
				$fields .= '
					<li>' . $field['output_html'] . '</li>';

		if (!empty($fields))
			echo '
			<div class="custom_fields_below_avatar">
				<ul>', $fields, '
				</ul>
			</div>';
	}

	echo '
			<ul class="icon_fields clear">';

	// Email is only visible if it's your profile or you have the moderate_forum permission
	if ($context['member']['show_email'])
		echo '
				<li><a href="mailto:', $context['member']['email'], '" title="', $context['member']['email'], '" rel="nofollow"><span class="main_icons mail" title="' . $txt['email'] . '"></span></a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
				<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" rel="noopener">', ($settings['use_image_buttons'] ? '<span class="main_icons www" title="' . $context['member']['website']['title'] . '"></span>' : $txt['www']), '</a></li>';

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
			<span id="userstatus">
				', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['text'] . '" rel="nofollow">' : '', $settings['use_image_buttons'] ? '<span class="' . ($context['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $context['member']['online']['text'] . '"></span>' : $context['member']['online']['label'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['label'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
		echo '
				<br>
				<a href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], '</a>';

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
		$fields = '';
		foreach ($context['print_custom_fields']['bottom_poster'] as $field)
			if (!empty($field['output_html']))
				$fields .= '
					<li>' . $field['output_html'] . '</li>';

		if (!empty($fields))
			echo '
			<div class="custom_fields_bottom">
				<ul class="nolist">', $fields, '
				</ul>
			</div>';
	}

	echo '
		</div><!-- #basicinfo -->

		<div id="detailedinfo">
			<dl class="settings">';

	if ($context['user']['is_owner'] || $context['user']['is_admin'])
		echo '
				<dt>', $txt['username'], ': </dt>
				<dd>', $context['member']['username'], '</dd>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
				<dt>', $txt['profile_posts'], ': </dt>
				<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';

	if ($context['member']['show_email'])
		echo '
				<dt>', $txt['email'], ': </dt>
				<dd><a href="mailto:', $context['member']['email'], '">', $context['member']['email'], '</a></dd>';

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
		$fields = array();

		foreach ($context['print_custom_fields']['standard'] as $field)
			if (!empty($field['output_html']))
				$fields[] = $field;

		if (count($fields) > 0)
		{
			echo '
			<dl class="settings">';

			foreach ($fields as $field)
				echo '
				<dt>', $field['name'], ':</dt>
				<dd>', $field['output_html'], '</dd>';

			echo '
			</dl>';
		}
	}

	echo '
			<dl class="settings noborder">';

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
				<dt>', $txt['profile_warning_level'], ': </dt>
				<dd>
					<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', ($context['can_issue_warning'] && !$context['user']['is_owner'] ? 'issuewarning' : 'viewwarning'), '">', $context['member']['warning'], '%</a>';

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
				<dt class="clear">
					<span class="alert">', $context['activate_message'], '</span> (<a href="', $context['activate_link'], '"', ($context['activate_type'] == 4 ? ' class="you_sure" data-confirm="' . $txt['profileConfirm'] . '"' : ''), '>', $context['activate_link_text'], '</a>)
				</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty($context['member']['bans']))
		{
			echo '
				<dt class="clear">
					<span class="alert">', $txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="document.getElementById(\'ban_info\').classList.toggle(\'hidden\');return false;">' . $txt['view_ban'] . '</a>]
				</dt>
				<dt class="clear hidden" id="ban_info">
					<strong>', $txt['user_banned_by_following'], ':</strong>';

			foreach ($context['member']['bans'] as $ban)
				echo '
					<br>
					<span class="smalltext">', $ban['explanation'], '</span>';

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
		$fields = '';
		foreach ($context['print_custom_fields']['above_signature'] as $field)
			if (!empty($field['output_html']))
				$fields .= '
					<li>' . $field['output_html'] . '</li>';

		if (!empty($fields))
			echo '
			<div class="custom_fields_above_signature">
				<ul class="nolist">', $fields, '
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
		$fields = '';
		foreach ($context['print_custom_fields']['below_signature'] as $field)
			if (!empty($field['output_html']))
				$fields .= '
					<li>' . $field['output_html'] . '</li>';

		if (!empty($fields))
			echo '
			<div class="custom_fields_below_signature">
				<ul class="nolist">', $fields, '
				</ul>
			</div>';
	}

	echo '
		</div><!-- #detailedinfo -->
	</div><!-- #profileview -->';
}

/**
 * Template for showing all the posts of the user, in chronological order.
 */
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
		<div class="', $post['css_class'], '">
			<div class="counter">', $post['counter'], '</div>
			<div class="topic_details">
				<h5>
					<strong><a href="', $scripturl, '?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="', $scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong>
				</h5>
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
					<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span class="main_icons reply_button"></span>', $txt['reply'], '</a></li>';

			// If they *can* quote?
			if ($post['can_quote'])
				echo '
					<li><a href="', $scripturl . '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span class="main_icons quote"></span>', $txt['quote_action'], '</a></li>';

			// How about... even... remove it entirely?!
			if ($post['can_delete'])
				echo '
					<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';profile;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['remove_message'], '" class="you_sure"><span class="main_icons remove_button"></span>', $txt['remove'], '</a></li>';

			if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
				echo '
				</ul>
			</div><!-- .floatright -->';

			echo '
		</div><!-- $post[css_class] -->';
		}
	}
	else
		template_show_list('attachments');

	// No posts? Just end with a informative message.
	if ((isset($context['attachments']) && empty($context['attachments'])) || (!isset($context['attachments']) && empty($context['posts'])))
		echo '
		<div class="windowbg">
			', isset($context['attachments']) ? $txt['show_attachments_none'] : ($context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none']), '
		</div>';

	// Show more page numbers.
	if (!empty($context['page_index']))
		echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}

/**
 * Template for showing alerts within the alerts popup
 */
function template_showAlerts()
{
	global $context, $txt, $scripturl;

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
		<div class="infobox">
			', $context['update_message'], '
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
		// Start the form if checkboxes are in use
		if ($context['showCheckboxes'])
			echo '
		<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;save" method="post" accept-charset="', $context['character_set'], '" id="mark_all">';

		echo '
			<table id="alerts" class="table_grid">';

		foreach ($context['alerts'] as $id => $alert)
		{
			echo '
				<tr class="windowbg">
					<td class="alert_text">
						', $alert['text'], '
						<span class="alert_inline_time"><span class="main_icons time_online"></span> ', $alert['time'], '</span>
					</td>
					<td class="alert_time">', $alert['time'], '</td>
					<td class="alert_buttons">
						<ul class="quickbuttons">
							<li><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;do=remove;aid=', $id, ';', $context['session_var'], '=', $context['session_id'], '" class="you_sure"><span class="main_icons remove_button"></span>', $txt['delete'], '</a></li>
							<li><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=showalerts;do=', ($alert['is_read'] != 0 ? 'unread' : 'read'), ';aid=', $id, ';', $context['session_var'], '=', $context['session_id'], '"><span class="main_icons ', $alert['is_read'] != 0 ? 'unread_button' : 'read_button', '"></span>', ($alert['is_read'] != 0 ? $txt['mark_unread'] : $txt['mark_read_short']), '</a></li>';

			if ($context['showCheckboxes'])
				echo '
							<li><input type="checkbox" name="mark[', $id, ']" value="', $id, '"></li>';

			echo '
						</ul>
					</td>
				</tr>';
		}

		echo '
			</table>
			<div class="pagesection">
				<div class="floatleft">
					', $context['pagination'], '
				</div>';

		if ($context['showCheckboxes'])
			echo '
				<div class="floatright">
					', $txt['check_all'], ': <input type="checkbox" name="select_all" id="select_all">
					<select name="mark_as">
						<option value="read">', $txt['quick_mod_markread'], '</option>
						<option value="unread">', $txt['quick_mod_markunread'], '</option>
						<option value="remove">', $txt['quick_mod_remove'], '</option>
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" name="req" value="', $txt['quick_mod_go'], '" class="button you_sure">
				</div>';

		echo '
			</div>';

		if ($context['showCheckboxes'])
			echo '
		</form>';
	}
}

/**
 * Template for showing all of a user's drafts
 */
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
		<div class="windowbg centertext">
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
				<h5>
					<strong><a href="', $scripturl, '?board=', $draft['board']['id'], '.0">', $draft['board']['name'], '</a> / ', $draft['topic']['link'], '</strong> &nbsp; &nbsp;';

			if (!empty($draft['sticky']))
				echo '
					<span class="main_icons sticky" title="', $txt['sticky_topic'], '"></span>';

			if (!empty($draft['locked']))
				echo '
					<span class="main_icons lock" title="', $txt['locked_topic'], '"></span>';

			echo '
				</h5>
				<span class="smalltext">&#171;&nbsp;<strong>', $txt['on'], ':</strong> ', $draft['time'], '&nbsp;&#187;</span>
			</div><!-- .topic_details -->
			<div class="list_posts">
				', $draft['body'], '
			</div>
			<div class="floatright">
				<ul class="quickbuttons">
						<li><a href="', $scripturl, '?action=post;', (empty($draft['topic']['id']) ? 'board=' . $draft['board']['id'] : 'topic=' . $draft['topic']['id']), '.0;id_draft=', $draft['id_draft'], '"><span class="main_icons reply_button"></span>', $txt['draft_edit'], '</a></li>
						<li><a href="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=showdrafts;delete=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['draft_remove'], '" class="you_sure"><span class="main_icons remove_button"></span>', $txt['draft_delete'], '</a></li>
				</ul>
			</div><!-- .floatright -->
		</div><!-- .windowbg -->';
		}
	}

	// Show page numbers.
	echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}

/**
 * Template for showing and managing the buddy list.
 */
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
				<span class="main_icons people icon"></span> ', $txt['editBuddies'], '
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link">', $txt['name'], '</th>
					<th scope="col" class="buddy_status">', $txt['status'], '</th>';

	if ($context['can_moderate_forum'])
		echo '
					<th scope="col" class="buddy_email">', $txt['email'], '</th>';

	if (!empty($context['custom_pf']))
		foreach ($context['custom_pf'] as $column)
			echo '
					<th scope="col" class="buddy_custom_fields">', $column['label'], '</th>';

	echo '
					<th scope="col" class="buddy_remove">', $txt['remove'], '</th>
				</tr>
			</thead>
			<tbody>';

	// If they don't have any buddies don't list them!
	if (empty($context['buddies']))
		echo '
				<tr class="windowbg">
					<td colspan="', $context['can_moderate_forum'] ? '10' : '9', '">
						<strong>', $txt['no_buddies'], '</strong>
					</td>
				</tr>';

	// Now loop through each buddy showing info on each.
	else
	{
		foreach ($context['buddies'] as $buddy)
		{
			echo '
				<tr class="windowbg">
					<td class="buddy_link">', $buddy['link'], '</td>
					<td class="centertext buddy_status">
						<a href="', $buddy['online']['href'], '"><span class="' . ($buddy['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $buddy['online']['text'] . '"></span></a>
					</td>';

			if ($buddy['show_email'])
				echo '
					<td class="buddy_email centertext">
						<a href="mailto:' . $buddy['email'] . '" rel="nofollow"><span class="main_icons mail icon" title="' . $txt['email'] . ' ' . $buddy['name'] . '"></span></a>
					</td>';

			// Show the custom profile fields for this user.
			if (!empty($context['custom_pf']))
				foreach ($context['custom_pf'] as $key => $column)
					echo '
					<td class="lefttext buddy_custom_fields">', $buddy['options'][$key], '</td>';

			echo '
					<td class="centertext buddy_remove">
						<a href="', $scripturl, '?action=profile;area=lists;sa=buddies;u=', $context['id_member'], ';remove=', $buddy['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="main_icons delete" title="', $txt['buddy_remove'], '"></span></a>
					</td>
				</tr>';
		}
	}

	echo '
			</tbody>
		</table>
	</div><!-- #edit_buddies -->';

	// Add a new buddy?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['buddy_add'], '</h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_buddy" id="new_buddy" size="30">
					<input type="submit" value="', $txt['buddy_add_button'], '" class="button floatnone">
				</dd>
			</dl>
		</div>';

	if (!empty($context['token_check']))
		echo '
		<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<script>
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
	</script>';
}

/**
 * Template for showing the ignore list of the current user.
 */
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
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link">', $txt['name'], '</th>
					<th scope="col" class="buddy_status">', $txt['status'], '</th>';

	if ($context['can_moderate_forum'])
		echo '
					<th scope="col" class="buddy_email">', $txt['email'], '</th>';

	echo '
					<th scope="col" class="buddy_remove">', $txt['ignore_remove'], '</th>
				</tr>
			</thead>
			<tbody>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
				<tr class="windowbg">
					<td colspan="', $context['can_moderate_forum'] ? '4' : '3', '">
						<strong>', $txt['no_ignore'], '</strong>
					</td>
				</tr>';

	// Now loop through each buddy showing info on each.
	foreach ($context['ignore_list'] as $member)
	{
		echo '
				<tr class="windowbg">
					<td class="buddy_link">', $member['link'], '</td>
					<td class="centertext buddy_status">
						<a href="', $member['online']['href'], '"><span class="' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $member['online']['text'] . '"></span></a>
					</td>';

		if ($context['can_moderate_forum'])
			echo '
					<td class="centertext buddy_email">
						<a href="mailto:' . $member['email'] . '" rel="nofollow"><span class="main_icons mail icon" title="' . $txt['email'] . ' ' . $member['name'] . '"></span></a>
					</td>';
		echo '
					<td class="centertext buddy_remove">
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="main_icons delete" title="', $txt['ignore_remove'], '"></span></a>
					</td>
				</tr>';
	}

	echo '
			</tbody>
		</table>
	</div><!-- #edit_buddies -->';

	// Add to the ignore list?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['ignore_add'], '</h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_ignore" id="new_ignore" size="30">
					<input type="submit" value="', $txt['ignore_add_button'], '" class="button">
				</dd>
			</dl>
		</div>';

	if (!empty($context['token_check']))
		echo '
		<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<script>
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
	</script>';
}

/**
 * This template shows an admin information on a users IP addresses used and errors attributed to them.
 */
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
		<div id="tracking" class="windowbg">
			<dl class="settings noborder">
				<dt>
					', $txt['most_recent_ip'], ':
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
					', (count($context['error_ips']) > 0 ? implode(', ', $context['error_ips']) : '(' . $txt['none'] . ')'), '
				</dd>';

	// List any members that have used the same IP addresses as the current member.
	echo '
				<dt>', $txt['members_in_range'], ':</dt>
				<dd>
					', (count($context['members_in_range']) > 0 ? implode(', ', $context['members_in_range']) : '(' . $txt['none'] . ')'), '
				</dd>
			</dl>
		</div><!-- #tracking -->';

	// Show the track user list.
	template_show_list('track_user_list');
}

/**
 * The template for trackIP, allowing the admin to see where/who a certain IP has been used.
 */
function template_trackIP()
{
	global $context, $txt;

	// This function always defaults to the last IP used by a member but can be set to track any IP.
	// The first table in the template gives an input box to allow the admin to enter another IP to track.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['trackIP'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', $context['base_url'], '" method="post" accept-charset="', $context['character_set'], '">
				<dl class="settings">
					<dt>
						<label for="searchip"><strong>', $txt['enter_ip'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="searchip" value="', $context['ip'], '">
					</dd>
				</dl>
				<input type="submit" value="', $txt['trackIP'], '" class="button">
			</form>
		</div>
		<br>';

	// The table inbetween the first and second table shows links to the whois server for every region.
	if ($context['single_ip'])
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['whois_title'], ' ', $context['ip'], '</h3>
		</div>
		<div class="windowbg">';

		foreach ($context['whois_servers'] as $server)
			echo '
			<a href="', $server['url'], '" target="_blank" rel="noopener"', '>', $server['name'], '</a><br>';
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
		<p class="windowbg description">
			<em>', $txt['no_members_from_ip'], '</em>
		</p>';

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

	// 3rd party integrations may have added additional tracking.
	if (!empty($context['additional_track_lists']))
	{
		foreach ($context['additional_track_lists'] as $list)
		{
			echo '<br>';

			template_show_list($list);
		}
	}
}

/**
 * This template shows an admin which permissions a user have and which group(s) give them each permission.
 */
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
		echo '
		<div class="information">', $txt['showPermissions_all'], '</div>';

	else
	{
		echo '
		<div class="information">', $txt['showPermissions_help'], '</div>
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
							<th class="lefttext half_table">', $txt['showPermissions_permission'], '</th>
							<th class="lefttext half_table">', $txt['showPermissions_status'], '</th>
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
								<span class="alert">', $txt['showPermissions_denied'], ': ', implode(', ', $permission['groups']['denied']), '</span>';
				else
					echo '
								', $txt['showPermissions_given'], ': ', implode(', ', $permission['groups']['allowed']);

				echo '
							</td>
						</tr>';
			}
			echo '
					</tbody>
				</table>
			</div><!-- .tborder -->
			<br>';
		}
		else
			echo '
			<p class="windowbg">', $txt['showPermissions_none_general'], '</p>';

		// Board permission section.
		echo '
			<form action="' . $scripturl . '?action=profile;u=', $context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="', $context['character_set'], '">
				<div class="cat_bar">
					<h3 class="catbg">
						<a id="board_permissions"></a>', $txt['showPermissions_select'], ':
						<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
							<option value="0"', $context['board'] == 0 ? ' selected' : '', '>', $txt['showPermissions_global'], '</option>';

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
				</div><!-- .cat_bar -->
			</form>';

		if (!empty($context['member']['permissions']['board']))
		{
			echo '
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext half_table">', $txt['showPermissions_permission'], '</th>
						<th class="lefttext half_table">', $txt['showPermissions_status'], '</th>
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
					echo '
							<span class="alert">', $txt['showPermissions_denied'], ': ', implode(', ', $permission['groups']['denied']), '</span>';

				else
					echo '
							', $txt['showPermissions_given'], ': ', implode(', ', $permission['groups']['allowed']);

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
			<p class="windowbg">', $txt['showPermissions_none_board'], '</p>';
		echo '
		</div><!-- #permissions -->';
	}
}

/**
 * Template for user statistics, showing graphs and the like.
 */
function template_statPanel()
{
	global $context, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview" class="roundframe noup">
		<div id="generalstats">
			<dl class="stats">';

	foreach ($context['text_stats'] as $key => $stat)
	{
		echo '
				<dt>', $txt['statPanel_' . $key], '</dt>';

		if (!empty($stat['url']))
			echo '
				<dd><a href="', $stat['url'], '">', $stat['text'], '</a></dd>';
		else
			echo '
				<dd>', $stat['text'], '</dd>';
	}

	echo '
			</dl>
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div id="activitytime" class="flow_hidden">
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="main_icons history"></span> ', $txt['statPanel_activityTime'], '
				</h3>
			</div>';

	// If they haven't post at all, don't draw the graph.
	if (empty($context['posts_by_time']))
		echo '
			<p class="centertext padding">', $txt['statPanel_noPosts'], '</p>';

	// Otherwise do!
	else
	{
		echo '
			<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
			echo '
				<li>
					<div class="generic_bar vertical">
						<div class="bar" style="height: ', (int) $time_of_day['relative_percent'], '%;">
							<span>', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '</span>
						</div>
					</div>
					<span class="stats_hour">', $time_of_day['hour_format'], '</span>
				</li>';

		echo '
			</ul>';
	}

	echo '
		</div><!-- #activitytime -->';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="main_icons replies"></span> ', $txt['statPanel_topBoards'], '
					</h3>
				</div>';

	if (empty($context['popular_boards']))
		echo '
				<p class="centertext padding">', $txt['statPanel_noPosts'], '</p>';

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
			</div><!-- .half_content -->
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="main_icons replies"></span> ', $txt['statPanel_topBoardsActivity'], '
					</h3>
				</div>';

	if (empty($context['board_activity']))
		echo '
				<p class="centertext padding">', $txt['statPanel_noPosts'], '</p>';
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
			</div><!-- .half_content -->
		</div><!-- .flow_hidden -->';

	echo '
	</div><!-- #profileview -->';
}

/**
 * Template for editing profile options.
 */
function template_edit_options()
{
	global $context, $scripturl, $txt, $modSettings;

	// The main header!
	// because some browsers ignore autocomplete=off and fill username in display name and/ or email field, fake them out.
	$url = !empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member'];
	$url = $context['require_password'] && !empty($modSettings['force_ssl']) ? strtr($url, array('http://' => 'https://')) : $url;

	echo '
		<form action="', $url, '" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data"', ($context['menu_item_selected'] == 'account' ? ' autocomplete="off"' : ''), '>
			<div style="position:absolute; left:-3000px;">
				<input type="text" id="autocompleteFakeName">
				<input type="password" id="autocompleteFakePassword">
			</div>
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
				<dl class="settings">';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach ($context['profile_fields'] as $key => $field)
	{
		// We add a little hack to be sure we never get more than one hr in a row!
		if ($lastItem == 'hr' && $field['type'] == 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] == 'hr')
			echo '
				</dl>
				<hr>
				<dl class="settings">';

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
						<input type="', $type, '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' ', $step, '>';
			}
			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
						<input type="hidden" name="', $key, '" value="0">
						<input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" ', $field['input_attr'], '>';

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
							<option', (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ''), ' value="' . $value . '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
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
				<hr>';

		echo '
				<dl class="settings">';

		foreach ($context['custom_fields'] as $field)
			echo '
					<dt>
						<strong>', $field['name'], ': </strong><br>
						<span class="smalltext">', $field['desc'], '</span>
					</dt>
					<dd>
						', $field['input_html'], '
					</dd>';

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
				<dl class="settings">
					<dt>
						<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], ': </label></strong><br>
						<span class="smalltext">', $txt['required_security_reasons'], '</span>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" id="oldpasswrd" size="20">
					</dd>
				</dl>';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
				<input type="submit" name="save" value="', $context['submit_button_text'], '" class="button floatright">';
	else
		echo '
				<input type="submit" name="save" value="', $txt['change_profile'], '" class="button floatright">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div><!-- .roundframe -->
		</form>';

	// Any final spellchecking stuff?
	if (!empty($context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="', $context['character_set'], '" target="spellWindow" action="', $scripturl, '?action=spellcheck"><input type="hidden" name="spellstring" value=""></form>';
}

/**
 * Personal Message settings.
 */
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
						<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked' : '', '>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
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
						<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty($context['member']['options']['popup_messages']) ? ' checked' : '', '>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
						<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '', '>
					</dd>';

}

/**
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
function template_profile_theme_settings()
{
	global $context, $modSettings;

	$skeys = array_keys($context['theme_options']);
	$first_option_key = array_shift($skeys);
	$titled_section = false;

	foreach ($context['theme_options'] as $i => $setting)
	{
		// Just spit out separators and move on
		if (empty($setting) || !is_array($setting))
		{
			// Insert a separator (unless this is the first item in the list)
			if ($i !== $first_option_key)
				echo '
				</dl>
				<hr>
				<dl class="settings">';

			// Should we give a name to this section?
			if (is_string($setting) && !empty($setting))
			{
				$titled_section = true;
				echo '
					<dt><strong>' . $setting . '</strong></dt>
					<dd></dd>';
			}
			else
				$titled_section = false;

			continue;
		}

		// Is this disabled?
		if (isset($setting['enabled']) && $setting['enabled'] === false)
			continue;

		// Some of these may not be set...  Set to defaults here
		$opts = array('calendar_start_day', 'topics_per_page', 'messages_per_page', 'display_quick_mod');
		if (in_array($setting['id'], $opts) && !isset($context['member']['options'][$setting['id']]))
			$context['member']['options'][$setting['id']] = 0;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$setting['type'] = 'checkbox';

		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$setting['type'] = 'number';

		elseif ($setting['type'] == 'string')
			$setting['type'] = 'text';

		if (isset($setting['options']))
			$setting['type'] = 'list';

		echo '
					<dt>
						<label for="', $setting['id'], '">', !$titled_section ? '<strong>' : '', $setting['label'], !$titled_section ? '</strong>' : '', '</label>';

		if (isset($setting['description']))
			echo '
						<br>
						<span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>
					<dd>';

		// Display checkbox options
		if ($setting['type'] == 'checkbox')
			echo '
						<input type="hidden" name="default_options[' . $setting['id'] . ']" value="0">
						<input type="checkbox" name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($context['member']['options'][$setting['id']]) ? ' checked' : '', ' value="1">';

		// How about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
						<select name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', '>';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', $value == $context['member']['options'][$setting['id']] ? ' selected' : '', '>', $label, '</option>';

			echo '
						</select>';
		}
		// A textbox it is then
		else
		{
			if (isset($setting['type']) && $setting['type'] == 'number')
			{
				$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
				$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
				$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';

				echo '
						<input type="number"', $min . $max . $step;
			}
			elseif (isset($setting['type']) && $setting['type'] == 'url')
				echo '
						<input type="url"';

			else
				echo '
						<input type="text"';

			echo ' name="default_options[', $setting['id'], ']" id="', $setting['id'], '" value="', isset($context['member']['options'][$setting['id']]) ? $context['member']['options'][$setting['id']] : $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', '>';
		}

		// end of this defintion
		echo '
					</dd>';
	}
}

/**
 * The template for configuring alerts
 */
function template_alert_configuration()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['alert_prefs'], '
			</h3>
		</div>
		<p class="information">
			', (empty($context['description']) ? $txt['alert_prefs_desc'] : $context['description']), '
		</p>
		<form action="', $scripturl, '?', $context['action'], '" method="post" accept-charset="', $context['character_set'], '" id="notify_options" class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notification_general'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
					<dt>
						<label for="notify_announcements">', $txt['notify_important_email'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0">
						<input type="checkbox" id="notify_announcements" name="notify_announcements" value="1"', !empty($context['member']['notify_announcements']) ? ' checked' : '', '>
					</dd>';

	if (!empty($modSettings['enable_ajax_alerts']))
		echo '
					<dt>
						<label for="notify_send_body">', $txt['notify_alert_timeout'], '</label>
					</dt>
					<dd>
						<input type="number" size="4" id="notify_alert_timeout" name="opt_alert_timeout" min="0" value="', $context['member']['alert_timeout'], '">
					</dd>';

	echo '
				</dl>
			</div><!-- .windowbg -->
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notify_what_how'], '
				</h3>
			</div>
			<table class="table_grid">';

	foreach ($context['alert_types'] as $alert_group => $alerts)
	{
		echo '
				<tr class="title_bar">
					<th>', $txt['alert_group_' . $alert_group], '</th>
					<th>', $txt['receive_alert'], '</th>
					<th>', $txt['receive_mail'], '</th>
				</tr>
				<tr class="windowbg">';

		if (isset($context['alert_group_options'][$alert_group]))
		{
			foreach ($context['alert_group_options'][$alert_group] as $opts)
			{
				echo '
				<tr class="windowbg">
					<td colspan="3">';

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
					</td>
				</tr>';
			}
		}

		foreach ($alerts as $alert_id => $alert_details)
		{
			echo '
				<tr class="windowbg">
					<td>
						', $txt['alert_' . $alert_id], isset($alert_details['help']) ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $alert_details['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help floatright"><span class="main_icons help" title="' . $txt['help'] . '"></span></a>' : '', '
					</td>';

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
				<input id="notify_submit" type="submit" name="notify_submit" value="', $txt['notify_save'], '" class="button">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', !empty($context['token_check']) ? '
				<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>
		<br>';
}

/**
 * Template for showing which topics you're subscribed to
 */
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

/**
 * Template for showing which boards you're subscribed to
 */
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

/**
 * Template for choosing group membership.
 */
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
					<textarea name="reason" rows="4"></textarea>
					<div class="righttext">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '">
						<input type="submit" name="req" value="', $txt['submit_request'], '" class="button">
						</div>
					</div>
				</div><!-- .groupmembership -->';
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
					<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '"', $group['is_primary'] ? ' checked' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');"', $group['can_be_primary'] ? '' : ' disabled', '>';

			echo '
					<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '</label>';

			// Can they leave their group?
			if ($group['can_leave'])
				echo '
					<a href="' . $scripturl . '?action=profile;save;u=' . $context['id_member'] . ';area=groupmembership;' . $context['session_var'] . '=' . $context['session_id'] . ';gid=' . $group['id'] . ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">' . $txt['leave_group'] . '</a>';

			echo '
				</div><!-- .windowbg -->';
		}

		if ($context['can_edit_primary'])
			echo '
				<div class="padding righttext">
					<input type="submit" value="', $txt['make_primary'], '" class="button">
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
					<span class="floatright">', $txt['approval_pending'], '</span>';

				elseif ($group['type'] == 2)
					echo '
					<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], '" class="button floatright">', $txt['request_group'], '</a>';

				echo '
				</div><!-- .windowbg -->';
			}
		}

		// Javascript for the selector stuff.
		echo '
				<script>
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
				</script>';
	}

	echo '
			</div><!-- #groups -->';

	if (!empty($context['token_check']))
		echo '
			<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="u" value="', $context['id_member'], '">
		</form>';
}

/**
 * Template for managing ignored boards
 */
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
		<div class="windowbg">
			<div class="flow_hidden boardslist">
				<ul>';

	foreach ($context['categories'] as $category)
	{
		echo '
					<li>
						<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'creator\'); return false;">', $category['name'], '</a>
						<ul>';

		foreach ($category['boards'] as $board)
		{
			echo '
							<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
								<label for="ignore_brd', $board['id'], '"><input type="checkbox" id="brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '> ', $board['name'], '</label>
							</li>';
		}

		echo '
						</ul>
					</li>';
	}

	echo '
				</ul>
			</div><!-- .flow_hidden boardslist -->';

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
		</div><!-- .windowbg -->
	</form>
	<br>';
}

/**
 * Simply loads some theme variables common to several warning templates.
 */
function template_load_warning_variables()
{
	global $modSettings, $context;

	// Setup the warning mode
	$context['warning_mode'] = array(
		0 => 'none',
		$modSettings['warning_watch'] => 'watched',
		$modSettings['warning_moderate'] => 'moderated',
		$modSettings['warning_mute'] => 'muted',
	);

	// Work out the starting warning.
	$context['current_warning_mode'] = $context['warning_mode'][0];
	foreach ($context['warning_mode'] as $limit => $warning)
		if ($context['member']['warning'] >= $limit)
			$context['current_warning_mode'] = $warning;
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
					<div class="generic_bar warning_level ', $context['current_warning_mode'], '">
						<div class="bar" style="width: ', $context['member']['warning'], '%;"></div>
						<span>', $context['member']['warning'], '%</span>
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
		</div><!-- .windowbg -->';

	template_show_list('view_warnings');
}

// Show a lovely interface for issuing warnings.
function template_issueWarning()
{
	global $context, $scripturl, $txt;

	template_load_warning_variables();

	echo '
	<script>
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
	</script>';

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
					<br>
					<span class="smalltext">', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</span>';

	echo '
				</dt>
				<dd>
					0% <input name="warning_level" id="warning_level" type="range" min="0" max="100" step="5" value="', $context['member']['warning'], '" onchange="updateSlider(this.value)"> 100%
					<div class="clear_left">
						', $txt['profile_warning_impact'], ': <span id="cur_level_div">', $context['member']['warning'], '% (', $context['level_effects'][$context['current_level']], ')</span>
					</div>
				</dd>';

	if (!$context['user']['is_owner'])
	{
		echo '
				<dt>
					<strong>', $txt['profile_warning_reason'], ':</strong><br>
					<span class="smalltext">', $txt['profile_warning_reason_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="warn_reason" id="warn_reason" value="', $context['warning_data']['reason'], '" size="50">
				</dd>
			</dl>
			<hr>
			<div id="box_preview"', !empty($context['warning_data']['body_preview']) ? '' : ' style="display:none"', '>
				<dl class="settings">
					<dt>
						<strong>', $txt['preview'], '</strong>
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
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();"', $context['warning_data']['notify'] ? ' checked' : '', '>
				</dd>
				<dt>
					<strong><label for="warn_sub">', $txt['profile_warning_notify_subject'], ':</label></strong>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50">
				</dd>
				<dt>
					<strong><label for="warn_temp">', $txt['profile_warning_notify_body'], ':</label></strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled onchange="populateNotifyTemplate();">
						<option value="-1">', $txt['profile_warning_notify_template'], '</option>
						<option value="-1" disabled>------------------------------</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
					</select>
					<span class="smalltext" id="new_template_link" style="display: none;">[<a href="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" rel="noopener">', $txt['profile_warning_new_template'], '</a>]</span>
					<br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8">', $context['warning_data']['notify_body'], '</textarea>
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
				<input type="button" name="preview" id="preview_button" value="', $txt['preview'], '" class="button">
				<input type="submit" name="save" value="', $context['user']['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" class="button">
			</div><!-- .righttext -->
		</div><!-- .windowbg -->
	</form>';

	// Previous warnings?
	template_show_list('view_warnings');

	echo '
	<script>';

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
						var errors_html = \'<ul class="list_errors">\';
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
	</script>';
}

/**
 * Template to show for deleting a user's account - now with added delete post capability!
 */
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
			<div class="windowbg">';

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
					<input type="password" name="oldpasswrd" size="20">
					<input type="submit" value="', $txt['yes'], '" class="button">';

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
		{
			echo '
				<div>
					<label for="deleteVotes">
						<input type="checkbox" name="deleteVotes" id="deleteVotes" value="1"> ', $txt['deleteAccount_votes'], ':
					</label><br>
					<label for="deletePosts">
						<input type="checkbox" name="deletePosts" id="deletePosts" value="1"> ', $txt['deleteAccount_posts'], ':
					</label>
					<select name="remove_type">
						<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
						<option value="topics">', $txt['deleteAccount_topics'], '</option>
					</select>';

			if ($context['show_perma_delete'])
				echo '
					<br>
					<label for="perma_delete"><input type="checkbox" name="perma_delete" id="perma_delete" value="1">', $txt['deleteAccount_permanent'], ':</label>';

			echo '
				</div>';
		}

		echo '
				<div>
					<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');"> ', $txt['deleteAccount_member'], '.</label>
				</div>
				<div>
					<input type="submit" value="', $txt['delete'], '" class="button">';

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
			</div><!-- .windowbg -->
			<br>
		</form>';
}

/**
 * Template for the password box/save button stuck at the bottom of every profile page.
 */
function template_profile_save()
{
	global $context, $txt;

	echo '

					<hr>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl class="settings">
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br>
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20">
						</dd>
					</dl>';

	echo '
					<div class="righttext">';

	if (!empty($context['token_check']))
		echo '
						<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
						<input type="submit" value="', $txt['change_profile'], '" class="button">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="u" value="', $context['id_member'], '">
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
					</div>';
}

/**
 * Small template for showing an error message upon a save problem in the profile.
 */
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
		</div><!-- #profile_error -->';
}

/**
 * Display a load of drop down selectors for allowing the user to change group.
 */
function template_profile_group_manage()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<strong>', $txt['primary_membergroup'], ': </strong><br>
								<span class="smalltext"><a href="', $scripturl, '?action=helpadmin;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span> ', $txt['moderator_why_missing'], '</a></span>
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
									<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', '> ', $member_group['name'], '</label><br>';

	echo '
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down">', $txt['additional_membergroups_show'], '</a>
								<script>
									document.getElementById("additional_groupsList").style.display = "none";
									document.getElementById("additional_groupsLink").style.display = "";
								</script>
							</dd>';

}

/**
 * Callback function for entering a birthdate!
 */
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
								<input type="text" name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '"> -
								<input type="text" name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '"> -
								<input type="text" name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '">
							</dd>';
}

/**
 * Show the signature editing box?
 */
function template_profile_signature_modify()
{
	global $txt, $context;

	echo '
							<dt id="current_signature" style="display:none">
								<strong>', $txt['current_signature'], ':</strong>
							</dt>
							<dd id="current_signature_display" style="display:none">
								<hr>
							</dd>

							<dt id="preview_signature" style="display:none">
								<strong>', $txt['signature_preview'], ':</strong>
							</dt>
							<dd id="preview_signature_display" style="display:none">
								<hr>
							</dd>

							<dt>
								<strong>', $txt['signature'], ':</strong><br>
								<span class="smalltext">', $txt['sig_info'], '</span><br>
								<br>';

	if ($context['show_spellchecking'])
		echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\');" class="button">';

	echo '
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50">', $context['member']['signature'], '</textarea><br>';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
								<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br>';

	if (!empty($context['show_preview_button']))
		echo '
								<input type="button" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '" class="button floatright">';

	if ($context['signature_warning'])
		echo '
								<span class="smalltext">', $context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script>
									var maxLength = ', $context['signature_limits']['max_length'], ';

									$(document).ready(function() {
										calcCharLeft();
										$("#preview_button").click(function() {
											return ajax_getSignaturePreview(true);
										});
									});
								</script>
							</dd>';
}

/**
 * Template for selecting an avatar
 */
function template_profile_avatar_select()
{
	global $context, $txt, $modSettings;

	// Start with the upper menu
	echo '
							<dt>
								<strong id="personal_picture">
									<label for="avatar_upload_box">', $txt['personal_picture'], '</label>
								</strong>';

	if (empty($modSettings['gravatarOverride']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['no_avatar'] . '
								</label><br>';

	if (!empty($context['member']['avatar']['allow_server_stored']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_server_stored"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', $txt['choose_avatar_gallery'], '
								</label><br>';

	if (!empty($context['member']['avatar']['allow_external']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', $txt['my_own_pic'], '
								</label><br>';

	if (!empty($context['member']['avatar']['allow_upload']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', $txt['avatar_will_upload'], '
								</label><br>';

	if (!empty($context['member']['avatar']['allow_gravatar']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_gravatar"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['use_gravatar'] . '</label>';

	echo '
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
									<div>
										<img id="avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $modSettings['avatar_url'] . '/blank.png', '" alt="Do Nothing">
									</div>
									<script>
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

									</script>
								</div><!-- #avatar_server_stored -->';
	}

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
		echo '
								<div id="avatar_external">
									<div class="smalltext">', $txt['avatar_by_url'], '</div>', !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_download_and_resize' ? template_max_size('external') : '', '
									<input type="text" name="userpicpersonal" size="45" value="', ((stristr($context['member']['avatar']['external'], 'http://') || stristr($context['member']['avatar']['external'], 'https://')) ? $context['member']['avatar']['external'] : 'http://'), '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value);">
								</div>';

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
		echo '
								<div id="avatar_upload">
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onchange="readfromUpload(this)"  onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" accept="image/gif, image/jpeg, image/jpg, image/png">', template_max_size('upload'), '
									', (!empty($context['member']['avatar']['id_attach']) ? '<br><img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt="" id="attached_image"><input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '">' : ''), '
								</div>';

	// if the user is able to use Gravatar avatars show then the image preview
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									<img src="' . $context['member']['avatar']['href'] . '" alt="">';

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
									<input type="text" name="gravatarEmail" id="gravatarEmail" size="45" value="', $textbox_value, '">';
		}
		echo '
								</div><!-- #avatar_gravatar -->';
	}

	echo '
								<script>
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
								</script>
							</dd>';
}

/**
 * This is just a really little helper to avoid duplicating code unnecessarily
 *
 * @param string $type The type of avatar
 */
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

/**
 * Select the time format!
 */
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<strong><label for="easyformat">', $txt['time_format'], ':</label></strong><br>
								<a href="', $scripturl, '?action=helpadmin;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', $txt['help'], '"></span></a>
								<span class="smalltext">
									<label for="time_format">', $txt['date_format'], '</label>
								</span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;">';

	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected' : '', '>', $time_format['title'], '</option>';

	echo '
								</select>
								<input type="text" name="time_format" id="time_format" value="', $context['member']['time_format'], '" size="30">
							</dd>';
}

/**
 * Template for picking a theme
 */
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

/**
 * Smiley set picker.
 */
function template_profile_smiley_pick()
{
	global $txt, $context, $modSettings, $settings;

	echo '
							<dt>
								<strong><label for="smiley_set">', $txt['smileys_current'], ':</label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set">';

	foreach ($context['smiley_sets'] as $set)
		echo '
									<option value="', $set['id'], '"', $set['selected'] ? ' selected' : '', '>', $set['name'], '</option>';

	echo '
								</select>
								<img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] . '/smiley' . $context['user']['smiley_set_ext'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) . '/smiley' . $context['user']['smiley_set_default_ext']) : $settings['images_url'] . '/blank.png', '" alt=":)">
							</dd>';
}

/**
 * Template for setting up and managing Two-Factor Authentication.
 */
function template_tfasetup()
{
	global $txt, $context, $scripturl, $modSettings;

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['tfa_title'], '</h3>
			</div>
			<div class="roundframe">
				<div>';

	if (!empty($context['tfa_backup']))
		echo '
					<div class="smalltext error">
						', $txt['tfa_backup_used_desc'], '
					</div>';

	elseif ($modSettings['tfa_mode'] == 2)
		echo '
					<div class="smalltext">
						<strong>', $txt['tfa_forced_desc'], '</strong>
					</div>';

	echo '
					<div class="smalltext">
						', $txt['tfa_desc'], '
					</div>
					<div class="floatleft">
						<form action="', $scripturl, '?action=profile;area=tfasetup" method="post">
							<div class="block">
								<strong>', $txt['tfa_step1'], '</strong><br>';

	if (!empty($context['tfa_pass_error']))
		echo '
								<div class="error smalltext">
									', $txt['tfa_pass_invalid'], '
								</div>';

	echo '
								<input type="password" name="passwd" size="25"', !empty($context['tfa_pass_error']) ? ' class="error"' : '', !empty($context['tfa_pass_value']) ? ' value="' . $context['tfa_pass_value'] . '"' : '', '>
							</div>
							<div class="block">
								<strong>', $txt['tfa_step2'], '</strong>
								<div class="smalltext">', $txt['tfa_step2_desc'], '</div>
								<div class="tfacode">', $context['tfa_secret'], '</div>
							</div>
							<div class="block">
								<strong>', $txt['tfa_step3'], '</strong><br>';

	if (!empty($context['tfa_error']))
		echo '
								<div class="error smalltext">
									', $txt['tfa_code_invalid'], '
								</div>';

	echo '
								<input type="text" name="tfa_code" size="25"', !empty($context['tfa_error']) ? ' class="error"' : '', !empty($context['tfa_value']) ? ' value="' . $context['tfa_value'] . '"' : '', '>
								<input type="submit" name="save" value="', $txt['tfa_enable'], '" class="button">
							</div>
							<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</form>
					</div>
					<div class="floatright tfa_qrcode">
						<div id="qrcode"></div>
						<script type="text/javascript">
							new QRCode(document.getElementById("qrcode"), "', $context['tfa_qr_url'], '");
						</script>
					</div>';

	if (!empty($context['from_ajax']))
		echo '
					<br>
					<a href="javascript:self.close();"></a>';

	echo '
				</div>
			</div><!-- .roundframe -->';
}

/**
 * Template for disabling two-factor authentication.
 */
function template_tfadisable()
{
	global $txt, $context, $scripturl;

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['tfadisable'], '</h3>
			</div>
			<div class="roundframe">
				<form action="', $scripturl, '?action=profile;area=tfadisable" method="post">';

	if ($context['user']['is_owner'])
		echo '
					<div class="block">
						<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong><br>
						<input type="password" name="oldpasswrd" size="20">
					</div>';
	else
		echo '
					<div class="smalltext">
						', sprintf($txt['tfa_disable_for_user'], $context['user']['name']), '
					</div>';

	echo '
					<input type="submit" name="save" value="', $txt['tfa_disable'], '" class="button floatright">
					<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
				</form>
			</div><!-- .roundframe -->';
}

/**
 * Template for setting up 2FA backup code
 */
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

/**
 * Simple template for showing the 2FA area when editing a profile.
 */
function template_profile_tfa()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
							<dt>
								<strong>', $txt['tfa_profile_label'], ':</strong><br>
								<div class="smalltext">', $txt['tfa_profile_desc'], '</div>
							</dt>
							<dd>';

	if (!$context['tfa_enabled'] && $context['user']['is_owner'])
		echo '
								<a href="', !empty($modSettings['force_ssl']) ? strtr($scripturl, array('http://' => 'https://')) : $scripturl, '?action=profile;area=tfasetup" id="enable_tfa">', $txt['tfa_profile_enable'], '</a>';

	elseif (!$context['tfa_enabled'])
		echo '
								', $txt['tfa_profile_disabled'];

	else
		echo '
								', sprintf($txt['tfa_profile_enabled'], (!empty($modSettings['force_ssl']) ? strtr($scripturl, array('http://' => 'https://')) : $scripturl) . '?action=profile;u=' . $context['id_member'] . ';area=tfadisable');

	echo '
							</dd>';
}

?>