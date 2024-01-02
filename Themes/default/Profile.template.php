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

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\User;

/**
 * Minor stuff shown above the main profile - mostly used for error messages and showing that the profile update was successful.
 */
function template_profile_above()
{
	// Prevent Chrome from auto completing fields when viewing/editing other members profiles
	if (BrowserDetector::isBrowser('is_chrome') && !User::$me->is_owner)
		echo '
			<script>
				disableAutoComplete();
			</script>';

	// If an error occurred while trying to save previously, give the user a clue!
	echo '
			', template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty(Utils::$context['profile_updated']))
		echo '
			<div class="infobox">
				', Utils::$context['profile_updated'], '
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
	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()

	echo '
		<div class="profile_user_avatar floatleft">
			<a href="', Config::$scripturl, '?action=profile;u=', User::$me->id, '">', Utils::$context['member']['avatar']['image'], '</a>
		</div>
		<div class="profile_user_info floatleft">
			<span class="profile_username"><a href="', Config::$scripturl, '?action=profile;u=', User::$me->id, '">', User::$me->name, '</a></span>
			<span class="profile_group">', Utils::$context['member']['group'], '</span>
		</div>
		<div class="profile_user_links">
			<ol>';

	$menu_context = &Utils::$context[Utils::$context['profile_menu_name']];
	foreach (Utils::$context['profile_items'] as $item)
	{
		$area = $menu_context['sections'][$item['menu']]['areas'][$item['area']];
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
	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()
	echo '
		<div class="alert_bar">
			<div class="alerts_opts block">
				<a href="' . Config::$scripturl . '?action=profile;area=notification;sa=markread;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" onclick="return markAlertsRead(this)">', Lang::$txt['mark_alerts_read'], '</a>
				<a href="', Config::$scripturl, '?action=profile;area=notification;sa=alerts" class="floatright">', Lang::$txt['alert_settings'], '</a>
			</div>
			<div class="alerts_box centertext">
				<a href="', Config::$scripturl, '?action=profile;area=showalerts" class="button">', Lang::$txt['all_alerts'], '</a>
			</div>
		</div>
		<div class="alerts_unread">';

	if (empty(Utils::$context['unread_alerts']))
		template_alerts_all_read();

	else
	{
		foreach (Utils::$context['unread_alerts'] as $id_alert => $details)
		{
			echo '
			<', !$details['show_links'] ? 'a href="' . Config::$scripturl . '?action=profile;area=showalerts;alert=' . $id_alert . '" onclick="this.classList.add(\'alert_read\')"' : 'div', ' class="unread_notify">
				<div class="unread_notify_image">
					', empty($details['sender']['avatar']['image']) ? '' : $details['sender']['avatar']['image'] . '
					', $details['icon'], '
				</div>
				<div class="details">
					<span class="alert_text">', $details['text'], '</span> - <span class="alert_time">', $details['time'], '</span>
				</div>
			</', !$details['show_links'] ? 'a' : 'div', '>';
		}
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
						if (typeof localStorage != "undefined")
							localStorage.setItem("alertsCounter", 0);
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
	echo '<div class="no_unread">', Lang::$txt['alerts_no_unread'], '</div>';
}

/**
 * This template displays a user's details without any option to edit them.
 */
function template_summary()
{
	// Display the basic information about the user
	echo '
	<div id="profileview" class="roundframe flow_auto noup">
		<div id="basicinfo">';

	// Are there any custom profile fields for above the name?
	if (!empty(Utils::$context['print_custom_fields']['above_member']))
	{
		$fields = '';
		foreach (Utils::$context['print_custom_fields']['above_member'] as $field)
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

	if (!empty(Utils::$context['print_custom_fields']['before_member']))
		foreach (Utils::$context['print_custom_fields']['before_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<span>', $field['output_html'], '</span>';

	echo '
					', Utils::$context['member']['name'];

	if (!empty(Utils::$context['print_custom_fields']['after_member']))
		foreach (Utils::$context['print_custom_fields']['after_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<span>', $field['output_html'], '</span>';

	echo '
					<span class="position">', (!empty(Utils::$context['member']['group']) ? Utils::$context['member']['group'] : Utils::$context['member']['post_group']), '</span>
				</h4>
			</div>
			', Utils::$context['member']['avatar']['image'];

	// Are there any custom profile fields for below the avatar?
	if (!empty(Utils::$context['print_custom_fields']['below_avatar']))
	{
		$fields = '';
		foreach (Utils::$context['print_custom_fields']['below_avatar'] as $field)
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
	if (Utils::$context['member']['show_email'])
		echo '
				<li><a href="mailto:', Utils::$context['member']['email'], '" title="', Utils::$context['member']['email'], '" rel="nofollow"><span class="main_icons mail" title="' . Lang::$txt['email'] . '"></span></a></li>';

	// Don't show an icon if they haven't specified a website.
	if (Utils::$context['member']['website']['url'] !== '' && !isset(Utils::$context['disabled_fields']['website']))
		echo '
				<li><a href="', Utils::$context['member']['website']['url'], '" title="' . Utils::$context['member']['website']['title'] . '" target="_blank" rel="noopener">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons www" title="' . Utils::$context['member']['website']['title'] . '"></span>' : Lang::$txt['www']), '</a></li>';

	// Are there any custom profile fields as icons?
	if (!empty(Utils::$context['print_custom_fields']['icons']))
	{
		foreach (Utils::$context['print_custom_fields']['icons'] as $field)
			if (!empty($field['output_html']))
				echo '
				<li class="custom_field">', $field['output_html'], '</li>';
	}

	echo '
			</ul>
			<span id="userstatus">
				', Utils::$context['can_send_pm'] ? '<a href="' . Utils::$context['member']['online']['href'] . '" title="' . Utils::$context['member']['online']['text'] . '" rel="nofollow">' : '', Theme::$current->settings['use_image_buttons'] ? '<span class="' . (Utils::$context['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . Utils::$context['member']['online']['text'] . '"></span>' : Utils::$context['member']['online']['label'], Utils::$context['can_send_pm'] ? '</a>' : '', Theme::$current->settings['use_image_buttons'] ? '<span class="smalltext"> ' . Utils::$context['member']['online']['label'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty(Utils::$context['can_have_buddy']) && !User::$me->is_owner)
		echo '
				<br>
				<a href="', Config::$scripturl, '?action=buddy;u=', Utils::$context['id_member'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::$txt['buddy_' . (Utils::$context['member']['is_buddy'] ? 'remove' : 'add')], '</a>';

	echo '
			</span>';

	if (!User::$me->is_owner && Utils::$context['can_send_pm'])
		echo '
			<a href="', Config::$scripturl, '?action=pm;sa=send;u=', Utils::$context['id_member'], '" class="infolinks">', Lang::$txt['profile_sendpm_short'], '</a>';

	echo '
			<a href="', Config::$scripturl, '?action=profile;area=showposts;u=', Utils::$context['id_member'], '" class="infolinks">', Lang::$txt['showPosts'], '</a>';

	if (User::$me->is_owner && !empty(Config::$modSettings['drafts_post_enabled']))
		echo '
			<a href="', Config::$scripturl, '?action=profile;area=showdrafts;u=', Utils::$context['id_member'], '" class="infolinks">', Lang::$txt['drafts_show'], '</a>';

	echo '
			<a href="', Config::$scripturl, '?action=profile;area=statistics;u=', Utils::$context['id_member'], '" class="infolinks">', Lang::$txt['statPanel'], '</a>';

	// Are there any custom profile fields for bottom?
	if (!empty(Utils::$context['print_custom_fields']['bottom_poster']))
	{
		$fields = '';
		foreach (Utils::$context['print_custom_fields']['bottom_poster'] as $field)
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

	if (User::$me->is_owner || User::$me->is_admin)
		echo '
				<dt>', Lang::$txt['username'], ': </dt>
				<dd>', Utils::$context['member']['username'], '</dd>';

	if (!isset(Utils::$context['disabled_fields']['posts']))
		echo '
				<dt>', Lang::$txt['profile_posts'], ': </dt>
				<dd>', Utils::$context['member']['posts'], ' (', Utils::$context['member']['posts_per_day'], ' ', Lang::$txt['posts_per_day'], ')</dd>';

	if (Utils::$context['member']['show_email'])
		echo '
				<dt>', Lang::$txt['email'], ': </dt>
				<dd><a href="mailto:', Utils::$context['member']['email'], '">', Utils::$context['member']['email'], '</a></dd>';

	if (!empty(Config::$modSettings['titlesEnable']) && !empty(Utils::$context['member']['title']))
		echo '
				<dt>', Lang::$txt['custom_title'], ': </dt>
				<dd>', Utils::$context['member']['title'], '</dd>';

	if (!empty(Utils::$context['member']['blurb']))
		echo '
				<dt>', Lang::$txt['personal_text'], ': </dt>
				<dd>', Utils::$context['member']['blurb'], '</dd>';

	echo '
				<dt>', Lang::$txt['age'], ':</dt>
				<dd>', Utils::$context['member']['age'] . (Utils::$context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . Theme::$current->settings['images_url'] . '/cake.png" alt="">' : ''), '</dd>';

	echo '
			</dl>';

	// Any custom fields for standard placement?
	if (!empty(Utils::$context['print_custom_fields']['standard']))
	{
		$fields = array();

		foreach (Utils::$context['print_custom_fields']['standard'] as $field)
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
	if (Utils::$context['can_view_warning'] && Utils::$context['member']['warning'])
	{
		echo '
				<dt>', Lang::$txt['profile_warning_level'], ': </dt>
				<dd>
					<a href="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=', (Utils::$context['can_issue_warning'] && !User::$me->is_owner ? 'issuewarning' : 'viewwarning'), '">', Utils::$context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty(Utils::$context['warning_status']))
			echo '
					<span class="smalltext">(', Utils::$context['warning_status'], ')</span>';

		echo '
				</dd>';
	}

	// Is this member requiring activation and/or banned?
	if (!empty(Utils::$context['activate_message']) || !empty(Utils::$context['member']['bans']))
	{
		// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
		if (!empty(Utils::$context['activate_message']))
			echo '
				<dt class="clear">
					<span class="alert">', Utils::$context['activate_message'], '</span> (<a href="', Utils::$context['activate_link'], '">', Utils::$context['activate_link_text'], '</a>)
				</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty(Utils::$context['member']['bans']))
		{
			echo '
				<dt class="clear">
					<span class="alert">', Lang::$txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="document.getElementById(\'ban_info\').classList.toggle(\'hidden\');return false;">' . Lang::$txt['view_ban'] . '</a>]
				</dt>
				<dt class="clear hidden" id="ban_info">
					<strong>', Lang::$txt['user_banned_by_following'], ':</strong>';

			foreach (Utils::$context['member']['bans'] as $ban)
				echo '
					<br>
					<span class="smalltext">', $ban['explanation'], '</span>';

			echo '
				</dt>';
		}
	}

	echo '
				<dt>', Lang::$txt['date_registered'], ': </dt>
				<dd>', Utils::$context['member']['registered'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if (Utils::$context['can_see_ip'])
	{
		if (!empty(Utils::$context['member']['ip']))
			echo '
				<dt>', Lang::$txt['ip'], ': </dt>
				<dd><a href="', Config::$scripturl, '?action=profile;area=tracking;sa=ip;searchip=', Utils::$context['member']['ip'], ';u=', Utils::$context['member']['id'], '">', Utils::$context['member']['ip'], '</a></dd>';

		if (!empty(Utils::$context['member']['hostname']))
			echo '
				<dt>', Lang::$txt['hostname'], ': </dt>
				<dd>', Utils::$context['member']['hostname'], '</dd>';
	}

	echo '
				<dt>', Lang::$txt['local_time'], ':</dt>
				<dd>', Utils::$context['member']['local_time'], '</dd>';

	if (!empty(Config::$modSettings['userLanguage']) && !empty(Utils::$context['member']['language']))
		echo '
				<dt>', Lang::$txt['language'], ':</dt>
				<dd>', Utils::$context['member']['language'], '</dd>';

	if (Utils::$context['member']['show_last_login'])
		echo '
				<dt>', Lang::$txt['lastLoggedIn'], ': </dt>
				<dd>', Utils::$context['member']['last_login'], (!empty(Utils::$context['member']['is_hidden']) ? ' (' . Lang::$txt['hidden'] . ')' : ''), '</dd>';

	echo '
			</dl>';

	// Are there any custom profile fields for above the signature?
	if (!empty(Utils::$context['print_custom_fields']['above_signature']))
	{
		$fields = '';
		foreach (Utils::$context['print_custom_fields']['above_signature'] as $field)
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
	if (Utils::$context['signature_enabled'] && !empty(Utils::$context['member']['signature']))
		echo '
			<div class="signature">
				<h5>', Lang::$txt['signature'], ':</h5>
				', Utils::$context['member']['signature'], '
			</div>';

	// Are there any custom profile fields for below the signature?
	if (!empty(Utils::$context['print_custom_fields']['below_signature']))
	{
		$fields = '';
		foreach (Utils::$context['print_custom_fields']['below_signature'] as $field)
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
	echo '
		<div class="cat_bar', !isset(Utils::$context['attachments']) ? ' cat_bar_round' : '', '">
			<h3 class="catbg">
				', (!isset(Utils::$context['attachments']) && empty(Utils::$context['is_topics']) ? Lang::$txt['showMessages'] : (!empty(Utils::$context['is_topics']) ? Lang::$txt['showTopics'] : Lang::$txt['showAttachments'])), !User::$me->is_owner ? ' - ' . Utils::$context['member']['name'] : '', '
			</h3>
		</div>', !empty(Utils::$context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . Utils::$context['page_index'] . '</div>
		</div>' : '';

	// Are we displaying posts or attachments?
	if (!isset(Utils::$context['attachments']))
	{
		// For every post to be displayed, give it its own div, and show the important details of the post.
		foreach (Utils::$context['posts'] as $post)
		{
			echo '
		<div class="', $post['css_class'], '">
			<div class="page_number floatright"> #', $post['counter'], '</div>
			<div class="topic_details">
				<h5>
					<strong><a href="', Config::$scripturl, '?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="', Config::$scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong>
				</h5>
				<span class="smalltext">', $post['time'], '</span>
			</div>';

			if (!$post['approved'])
				echo '
			<div class="noticebox">
				', Lang::$txt['post_awaiting_approval'], '
			</div>';

			echo '
			<div class="post">
				<div class="inner">
					', $post['body'], '
				</div>
			</div><!-- .post -->';

			// Post options
			template_quickbuttons($post['quickbuttons'], 'profile_showposts');

			echo '
		</div><!-- .', $post['css_class'], ' -->';
		}
	}
	else
		template_show_list('attachments');

	// No posts? Just end with a informative message.
	if ((isset(Utils::$context['attachments']) && empty(Utils::$context['attachments'])) || (!isset(Utils::$context['attachments']) && empty(Utils::$context['posts'])))
		echo '
		<div class="windowbg">
			', isset(Utils::$context['attachments']) ? Lang::$txt['show_attachments_none'] : (Utils::$context['is_topics'] ? Lang::$txt['show_topics_none'] : Lang::$txt['show_posts_none']), '
		</div>';

	// Show more page numbers.
	if (!empty(Utils::$context['page_index']))
		echo '
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';
}

/**
 * Template for showing all alerts
 */
function template_showAlerts()
{
	// Do we have an update message?
	if (!empty(Utils::$context['update_message']))
		echo '
		<div class="infobox">
			', Utils::$context['update_message'], '
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
			', Lang::$txt['alerts'], !User::$me->is_owner ? ' - ' . Utils::$context['member']['name'] : '', '
			</h3>
		</div>';

	if (empty(Utils::$context['alerts']))
		echo '
		<div class="information">
			', Lang::$txt['alerts_none'], '
		</div>';

	else
	{
		// Start the form if checkboxes are in use
		if (Utils::$context['showCheckboxes'])
			echo '
		<form action="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=showalerts;save" method="post" accept-charset="', Utils::$context['character_set'], '" id="mark_all">';

		echo '
			<table id="alerts" class="table_grid">';

		foreach (Utils::$context['alerts'] as $id => $alert)
		{
			echo '
				<tr class="windowbg">
					<td class="alert_image">
						<div>
							', empty($alert['sender']['avatar']['image']) ? '' : $alert['sender']['avatar']['image'] . '
							', $alert['icon'], '
						</div>
					</td>
					<td class="alert_text">
						<div>', $alert['text'], '</div>
						<time class="alert_inline_time" datetime="', $alert['alert_time'], '">', $alert['time'], '</time>
					</td>
					<td class="alert_time">
						<time datetime="', $alert['alert_time'], '">', $alert['time'], '</time>
					</td>
					<td class="alert_buttons">';

			// Alert options
			template_quickbuttons($alert['quickbuttons'], 'profile_alerts');

			echo '
					</td>
				</tr>';
		}

		echo '
			</table>
			<div class="pagesection">
				<div class="pagelinks">', Utils::$context['pagination'], '</div>
				<div class="floatright">';

		if (Utils::$context['showCheckboxes'])
			echo '
					', Lang::$txt['check_all'], ': <input type="checkbox" name="select_all" id="select_all">
					<select name="mark_as">
						<option value="read">', Lang::$txt['quick_mod_markread'], '</option>
						<option value="unread">', Lang::$txt['quick_mod_markunread'], '</option>
						<option value="remove">', Lang::$txt['quick_mod_remove'], '</option>
					</select>
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="start" value="', Utils::$context['start'], '">
					<input type="submit" name="req" value="', Lang::$txt['quick_mod_go'], '" class="button you_sure">';

		echo '
					<a href="', Utils::$context['alert_purge_link'], '" class="button you_sure">', Lang::$txt['alert_purge'], '</a>
				</div>
			</div>';

		if (Utils::$context['showCheckboxes'])
			echo '
		</form>';
	}
}

/**
 * Template for showing all of a user's drafts
 */
function template_showDrafts()
{
	echo '
		<div class="cat_bar cat_bar_round">
			<h3 class="catbg">
				', Lang::$txt['drafts'], !User::$me->is_owner ? ' - ' . Utils::$context['member']['name'] : '', '
			</h3>
		</div>', !empty(Utils::$context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . Utils::$context['page_index'] . '</div>
		</div>' : '';

	// No drafts? Just show an informative message.
	if (empty(Utils::$context['drafts']))
		echo '
		<div class="windowbg centertext">
			', Lang::$txt['draft_none'], '
		</div>';
	else
	{
		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach (Utils::$context['drafts'] as $draft)
		{
			echo '
		<div class="windowbg">
			<div class="page_number floatright"> #', $draft['counter'], '</div>
			<div class="topic_details">
				<h5>
					<strong><a href="', Config::$scripturl, '?board=', $draft['board']['id'], '.0">', $draft['board']['name'], '</a> / ', $draft['topic']['link'], '</strong> &nbsp; &nbsp;';

			if (!empty($draft['sticky']))
				echo '
					<span class="main_icons sticky" title="', Lang::$txt['sticky_topic'], '"></span>';

			if (!empty($draft['locked']))
				echo '
					<span class="main_icons lock" title="', Lang::$txt['locked_topic'], '"></span>';

			echo '
				</h5>
				<span class="smalltext"><strong>', Lang::$txt['draft_saved_on'], ':</strong> ', $draft['time'], '</span>
			</div><!-- .topic_details -->
			<div class="list_posts">
				', $draft['body'], '
			</div>
			<div class="floatright">';

			// Draft buttons
			template_quickbuttons($draft['quickbuttons'], 'profile_drafts');

			echo '
			</div><!-- .floatright -->
		</div><!-- .windowbg -->';
		}
	}

	// Show page numbers.
	echo '
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';
}

/**
 * Template for showing and managing the buddy list.
 */
function template_editBuddies()
{
	if (!empty(Utils::$context['saved_successful']))
		echo '
	<div class="infobox">', User::$me->is_owner ? Lang::$txt['profile_updated_own'] : sprintf(Lang::$txt['profile_updated_else'], Utils::$context['member']['name']), '</div>';

	elseif (!empty(Utils::$context['saved_failed']))
		echo '
	<div class="errorbox">', Utils::$context['saved_failed'], '</div>';

	echo '
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons people icon"></span> ', Lang::$txt['editBuddies'], '
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link">', Lang::$txt['name'], '</th>
					<th scope="col" class="buddy_status">', Lang::$txt['status'], '</th>';

	if (Utils::$context['can_moderate_forum'])
		echo '
					<th scope="col" class="buddy_email">', Lang::$txt['email'], '</th>';

	if (!empty(Utils::$context['custom_pf']))
		foreach (Utils::$context['custom_pf'] as $column)
			echo '
					<th scope="col" class="buddy_custom_fields">', $column['label'], '</th>';

	echo '
					<th scope="col" class="buddy_remove">', Lang::$txt['remove'], '</th>
				</tr>
			</thead>
			<tbody>';

	// If they don't have any buddies don't list them!
	if (empty(Utils::$context['buddies']))
		echo '
				<tr class="windowbg">
					<td colspan="', Utils::$context['can_moderate_forum'] ? '10' : '9', '">
						<strong>', Lang::$txt['no_buddies'], '</strong>
					</td>
				</tr>';

	// Now loop through each buddy showing info on each.
	else
	{
		foreach (Utils::$context['buddies'] as $buddy)
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
						<a href="mailto:' . $buddy['email'] . '" rel="nofollow"><span class="main_icons mail icon" title="' . Lang::$txt['email'] . ' ' . $buddy['name'] . '"></span></a>
					</td>';

			// Show the custom profile fields for this user.
			if (!empty(Utils::$context['custom_pf']))
				foreach (Utils::$context['custom_pf'] as $key => $column)
					echo '
					<td class="centertext buddy_custom_fields">', $buddy['options'][$key], '</td>';

			echo '
					<td class="centertext buddy_remove">
						<a href="', Config::$scripturl, '?action=profile;area=lists;sa=buddies;u=', Utils::$context['id_member'], ';remove=', $buddy['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons delete" title="', Lang::$txt['buddy_remove'], '"></span></a>
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
	<form action="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['buddy_add'], '</h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong>', Lang::$txt['who_member'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_buddy" id="new_buddy" size="30">
					<input type="submit" value="', Lang::$txt['buddy_add_button'], '" class="button floatnone">
				</dd>
			</dl>
		</div>';

	if (!empty(Utils::$context['token_check']))
		echo '
		<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<script>
		var oAddBuddySuggest = new smc_AutoSuggest({
			sSelf: \'oAddBuddySuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'new_buddy\',
			sControlId: \'new_buddy\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	</script>';
}

/**
 * Template for showing the ignore list of the current user.
 */
function template_editIgnoreList()
{
	if (!empty(Utils::$context['saved_successful']))
		echo '
	<div class="infobox">', User::$me->is_owner ? Lang::$txt['profile_updated_own'] : sprintf(Lang::$txt['profile_updated_else'], Utils::$context['member']['name']), '</div>';

	elseif (!empty(Utils::$context['saved_failed']))
		echo '
	<div class="errorbox">', Utils::$context['saved_failed'], '</div>';

	echo '
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', Lang::$txt['editIgnoreList'], '
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link">', Lang::$txt['name'], '</th>
					<th scope="col" class="buddy_status">', Lang::$txt['status'], '</th>';

	if (Utils::$context['can_moderate_forum'])
		echo '
					<th scope="col" class="buddy_email">', Lang::$txt['email'], '</th>';

	echo '
					<th scope="col" class="buddy_remove">', Lang::$txt['ignore_remove'], '</th>
				</tr>
			</thead>
			<tbody>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty(Utils::$context['ignore_list']))
		echo '
				<tr class="windowbg">
					<td colspan="', Utils::$context['can_moderate_forum'] ? '4' : '3', '">
						<strong>', Lang::$txt['no_ignore'], '</strong>
					</td>
				</tr>';

	// Now loop through each buddy showing info on each.
	foreach (Utils::$context['ignore_list'] as $member)
	{
		echo '
				<tr class="windowbg">
					<td class="buddy_link">', $member['link'], '</td>
					<td class="centertext buddy_status">
						<a href="', $member['online']['href'], '"><span class="' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $member['online']['text'] . '"></span></a>
					</td>';

		if (Utils::$context['can_moderate_forum'])
			echo '
					<td class="centertext buddy_email">
						<a href="mailto:' . $member['email'] . '" rel="nofollow"><span class="main_icons mail icon" title="' . Lang::$txt['email'] . ' ' . $member['name'] . '"></span></a>
					</td>';
		echo '
					<td class="centertext buddy_remove">
						<a href="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons delete" title="', Lang::$txt['ignore_remove'], '"></span></a>
					</td>
				</tr>';
	}

	echo '
			</tbody>
		</table>
	</div><!-- #edit_buddies -->';

	// Add to the ignore list?
	echo '
	<form action="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['ignore_add'], '</h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong>', Lang::$txt['who_member'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_ignore" id="new_ignore" size="30">
					<input type="submit" value="', Lang::$txt['ignore_add_button'], '" class="button">
				</dd>
			</dl>
		</div>';

	if (!empty(Utils::$context['token_check']))
		echo '
		<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<script>
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSelf: \'oAddIgnoreSuggest\',
			sSessionId: \'', Utils::$context['session_id'], '\',
			sSessionVar: \'', Utils::$context['session_var'], '\',
			sSuggestId: \'new_ignore\',
			sControlId: \'new_ignore\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	</script>';
}

/**
 * This template shows an admin information on a users IP addresses used and errors attributed to them.
 */
function template_trackActivity()
{
	// The first table shows IP information about the user.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['view_ips_by'], ' ', Utils::$context['member']['name'], '</h3>
		</div>';

	// The last IP the user used.
	echo '
		<div id="tracking" class="windowbg">
			<dl class="settings noborder">
				<dt>
					', Lang::$txt['most_recent_ip'], ':
					', (empty(Utils::$context['last_ip2']) ? '' : '<br>
					<span class="smalltext">(<a href="' . Config::$scripturl . '?action=helpadmin;help=whytwoip" onclick="return reqOverlayDiv(this.href);">' . Lang::$txt['why_two_ip_address'] . '</a>)</span>'), '
				</dt>
				<dd>
					<a href="', Config::$scripturl, '?action=profile;area=tracking;sa=ip;searchip=', Utils::$context['last_ip'], ';u=', Utils::$context['member']['id'], '">', Utils::$context['last_ip'], '</a>';

	// Second address detected?
	if (!empty(Utils::$context['last_ip2']))
		echo '
					, <a href="', Config::$scripturl, '?action=profile;area=tracking;sa=ip;searchip=', Utils::$context['last_ip2'], ';u=', Utils::$context['member']['id'], '">', Utils::$context['last_ip2'], '</a>';

	echo '
				</dd>';

	// Lists of IP addresses used in messages / error messages.
	echo '
				<dt>', Lang::$txt['ips_in_messages'], ':</dt>
				<dd>
					', (count(Utils::$context['ips']) > 0 ? implode(', ', Utils::$context['ips']) : '(' . Lang::$txt['none'] . ')'), '
				</dd>
				<dt>', Lang::$txt['ips_in_errors'], ':</dt>
				<dd>
					', (count(Utils::$context['error_ips']) > 0 ? implode(', ', Utils::$context['error_ips']) : '(' . Lang::$txt['none'] . ')'), '
				</dd>';

	// List any members that have used the same IP addresses as the current member.
	echo '
				<dt>', Lang::$txt['members_in_range'], ':</dt>
				<dd>
					', (count(Utils::$context['members_in_range']) > 0 ? implode(', ', Utils::$context['members_in_range']) : '(' . Lang::$txt['none'] . ')'), '
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
	// This function always defaults to the last IP used by a member but can be set to track any IP.
	// The first table in the template gives an input box to allow the admin to enter another IP to track.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['trackIP'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Utils::$context['base_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
				<dl class="settings">
					<dt>
						<label for="searchip"><strong>', Lang::$txt['enter_ip'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="searchip" value="', Utils::$context['ip'], '">
					</dd>
				</dl>
				<input type="submit" value="', Lang::$txt['trackIP'], '" class="button">
			</form>
		</div>
		<br>';

	// The table inbetween the first and second table shows links to the whois server for every region.
	if (Utils::$context['single_ip'])
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['whois_title'], ' ', Utils::$context['ip'], '</h3>
		</div>
		<div class="windowbg">';

		foreach (Utils::$context['whois_servers'] as $server)
			echo '
			<a href="', $server['url'], '" target="_blank" rel="noopener"', '>', $server['name'], '</a><br>';
		echo '
		</div>
		<br>';
	}

	// The second table lists all the members who have been logged as using this IP address.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['members_from_ip'], ' ', Utils::$context['ip'], '</h3>
		</div>';

	if (empty(Utils::$context['ips']))
		echo '
		<p class="windowbg description">
			<em>', Lang::$txt['no_members_from_ip'], '</em>
		</p>';

	else
	{
		echo '
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col">', Lang::$txt['ip_address'], '</th>
					<th scope="col">', Lang::$txt['display_name'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Loop through each of the members and display them.
		foreach (Utils::$context['ips'] as $ip => $memberlist)
			echo '
				<tr class="windowbg">
					<td><a href="', Utils::$context['base_url'], ';searchip=', $ip, '">', $ip, '</a></td>
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
	if (!empty(Utils::$context['additional_track_lists']))
	{
		foreach (Utils::$context['additional_track_lists'] as $list)
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
	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', Lang::$txt['showPermissions'], '
			</h3>
		</div>';

	if (Utils::$context['member']['has_all_permissions'])
		echo '
		<div class="information">', Lang::$txt['showPermissions_all'], '</div>';

	else
	{
		echo '
		<div class="information">', Lang::$txt['showPermissions_help'], '</div>
		<div id="permissions" class="flow_hidden">';

		if (!empty(Utils::$context['no_access_boards']))
		{
			echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['showPermissions_restricted_boards'], '</h3>
			</div>
			<div class="windowbg smalltext">
				', Lang::$txt['showPermissions_restricted_boards_desc'], ':<br>';

			foreach (Utils::$context['no_access_boards'] as $no_access_board)
				echo '
				<a href="', Config::$scripturl, '?board=', $no_access_board['id'], '.0">', $no_access_board['name'], '</a>', $no_access_board['is_last'] ? '' : ', ';
			echo '
			</div>';
		}

		// General Permissions section.
		echo '
			<div class="tborder">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['showPermissions_general'], '</h3>
				</div>';
		if (!empty(Utils::$context['member']['permissions']['general']))
		{
			echo '
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th class="lefttext half_table">', Lang::$txt['showPermissions_permission'], '</th>
							<th class="lefttext half_table">', Lang::$txt['showPermissions_status'], '</th>
						</tr>
					</thead>
					<tbody>';

			foreach (Utils::$context['member']['permissions']['general'] as $permission)
			{
				echo '
						<tr class="windowbg">
							<td title="', $permission['id'], '">
								', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
							</td>
							<td class="smalltext">';

				if ($permission['is_denied'])
					echo '
								<span class="alert">', Lang::$txt['showPermissions_denied'], ': ', implode(', ', $permission['groups']['denied']), '</span>';
				else
					echo '
								', Lang::$txt['showPermissions_given'], ': ', implode(', ', $permission['groups']['allowed']);

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
			<p class="windowbg">', Lang::$txt['showPermissions_none_general'], '</p>';

		// Board permission section.
		echo '
			<form action="' . Config::$scripturl . '?action=profile;u=', Utils::$context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="', Utils::$context['character_set'], '">
				<div class="cat_bar">
					<h3 class="catbg">
						<a id="board_permissions"></a>', Lang::$txt['showPermissions_select'], ':
						<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
							<option value="0"', Utils::$context['board'] == 0 ? ' selected' : '', '>', Lang::$txt['showPermissions_global'], '</option>';

		if (!empty(Utils::$context['boards']))
			echo '
							<option value="" disabled>---------------------------</option>';

		// Fill the box with any local permission boards.
		foreach (Utils::$context['boards'] as $board)
			echo '
							<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['name'], ' (', $board['profile_name'], ')</option>';

		echo '
						</select>
					</h3>
				</div><!-- .cat_bar -->
			</form>';

		if (!empty(Utils::$context['member']['permissions']['board']))
		{
			echo '
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext half_table">', Lang::$txt['showPermissions_permission'], '</th>
						<th class="lefttext half_table">', Lang::$txt['showPermissions_status'], '</th>
					</tr>
				</thead>
				<tbody>';

			foreach (Utils::$context['member']['permissions']['board'] as $permission)
			{
				echo '
					<tr class="windowbg">
						<td title="', $permission['id'], '">
							', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
						</td>
						<td class="smalltext">';

				if ($permission['is_denied'])
					echo '
							<span class="alert">', Lang::$txt['showPermissions_denied'], ': ', implode(', ', $permission['groups']['denied']), '</span>';

				else
					echo '
							', Lang::$txt['showPermissions_given'], ': ', implode(', ', $permission['groups']['allowed']);

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
			<p class="windowbg">', Lang::$txt['showPermissions_none_board'], '</p>';
		echo '
		</div><!-- #permissions -->';
	}
}

/**
 * Template for user statistics, showing graphs and the like.
 */
function template_statPanel()
{
	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview" class="roundframe noup">
		<div id="generalstats">
			<dl class="stats">';

	foreach (Utils::$context['text_stats'] as $key => $stat)
	{
		echo '
				<dt>', Lang::$txt['statPanel_' . $key], '</dt>';

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
					<span class="main_icons history"></span> ', Lang::$txt['statPanel_activityTime'], '
				</h3>
			</div>';

	// If they haven't post at all, don't draw the graph.
	if (empty(Utils::$context['posts_by_time']))
		echo '
			<p class="centertext padding">', Lang::$txt['statPanel_noPosts'], '</p>';

	// Otherwise do!
	else
	{
		echo '
			<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach (Utils::$context['posts_by_time'] as $time_of_day)
			echo '
				<li>
					<div class="generic_bar vertical">
						<div class="bar" style="height: ', (int) $time_of_day['relative_percent'], '%;">
							<span>', sprintf(Lang::$txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '</span>
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
						<span class="main_icons replies"></span> ', Lang::$txt['statPanel_topBoards'], '
					</h3>
				</div>';

	if (empty(Utils::$context['popular_boards']))
		echo '
				<p class="centertext padding">', Lang::$txt['statPanel_noPosts'], '</p>';

	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach (Utils::$context['popular_boards'] as $board)
		{
			echo '
					<dt>', $board['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($board['posts_percent'] / 5) * 20), 'px 0;" title="', sprintf(Lang::$txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '">
							', sprintf(Lang::$txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '
						</div>
						', empty(Utils::$context['hide_num_posts']) ? $board['posts'] : '', '
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
						<span class="main_icons replies"></span> ', Lang::$txt['statPanel_topBoardsActivity'], '
					</h3>
				</div>';

	if (empty(Utils::$context['board_activity']))
		echo '
				<p class="centertext padding">', Lang::$txt['statPanel_noPosts'], '</p>';
	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach (Utils::$context['board_activity'] as $activity)
		{
			echo '
					<dt>', $activity['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($activity['posts_percent'] / 5) * 20), 'px 0;" title="', sprintf(Lang::$txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
							', sprintf(Lang::$txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '
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
	// The main header!
	// because some browsers ignore autocomplete=off and fill username in display name and/ or email field, fake them out.
	$url = !empty(Utils::$context['profile_custom_submit_url']) ? Utils::$context['profile_custom_submit_url'] : Config::$scripturl . '?action=profile;area=' . Utils::$context['menu_item_selected'] . ';u=' . Utils::$context['id_member'];
	$url = Utils::$context['require_password'] && !empty(Config::$modSettings['force_ssl']) ? strtr($url, array('http://' => 'https://')) : $url;

	echo '
		<form action="', $url, '" method="post" accept-charset="', Utils::$context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data"', (Utils::$context['menu_item_selected'] == 'account' ? ' autocomplete="off"' : ''), '>
			<div style="height:0;overflow:hidden;">
				<input type="text" id="autocompleteFakeName">
				<input type="password" id="autocompleteFakePassword">
			</div>
			<div class="cat_bar">
				<h3 class="catbg profile_hd">';

	// Don't say "Profile" if this isn't the profile...
	if (!empty(Utils::$context['profile_header_text']))
		echo '
					', Utils::$context['profile_header_text'];
	else
		echo '
					', Lang::$txt['profile'];

	echo '
				</h3>
			</div>';

	// Have we some description?
	if (Utils::$context['page_desc'])
		echo '
			<p class="information">', Utils::$context['page_desc'], '</p>';

	echo '
			<div class="roundframe">';

	// Any bits at the start?
	if (!empty(Utils::$context['profile_prehtml']))
		echo '
				<div>', Utils::$context['profile_prehtml'], '</div>';

	if (!empty(Utils::$context['profile_fields']))
		echo '
				<dl class="settings">';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach (Utils::$context['profile_fields'] as $key => $field)
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
						<input type="', $type, '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '"', isset($field['min']) ? ' min="' . $field['min'] . '"' : '', isset($field['max']) ? ' max="' . $field['max'] . '"' : '', ' value="', $field['value'], '" ', $field['input_attr'], ' ', $step, '>';
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
							<option value="' . $value . '"', (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ($value == $field['value'] ? ' selected' : '')), '>', $name, '</option>';
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

	if (!empty(Utils::$context['profile_fields']))
		echo '
				</dl>';

	// Are there any custom profile fields - if so print them!
	if (!empty(Utils::$context['custom_fields']))
	{
		if ($lastItem != 'hr')
			echo '
				<hr>';

		echo '
				<dl class="settings">';

		foreach (Utils::$context['custom_fields'] as $field)
			echo '
					<dt>
						<strong>', $field['name'], '</strong><br>
						<span class="smalltext">', $field['desc'], '</span>
					</dt>
					<dd>
						', $field['input_html'], '
					</dd>';

		echo '
				</dl>';
	}

	// Any closing HTML?
	if (!empty(Utils::$context['profile_posthtml']))
		echo '
				<div>', Utils::$context['profile_posthtml'], '</div>';

	// Only show the password box if it's actually needed.
	if (Utils::$context['require_password'])
		echo '
				<dl class="settings">
					<dt>
						<strong', isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', Lang::$txt['current_password'], '</label></strong><br>
						<span class="smalltext">', Lang::$txt['required_security_reasons'], '</span>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" id="oldpasswrd" size="20">
					</dd>
				</dl>';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty(Utils::$context['submit_button_text']))
		echo '
				<input type="submit" name="save" value="', Utils::$context['submit_button_text'], '" class="button floatright">';
	else
		echo '
				<input type="submit" name="save" value="', Lang::$txt['change_profile'], '" class="button floatright">';

	if (!empty(Utils::$context['token_check']))
		echo '
				<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
				<input type="hidden" name="sa" value="', Utils::$context['menu_item_selected'], '">
			</div><!-- .roundframe -->
		</form>';

	// Any final spellchecking stuff?
	if (!empty(Utils::$context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="', Utils::$context['character_set'], '" target="spellWindow" action="', Config::$scripturl, '?action=spellcheck"><input type="hidden" name="spellstring" value=""></form>';
}

/**
 * Personal Message settings.
 */
function template_profile_pm_settings()
{
	echo '
					<dt>
						<label for="pm_prefs">', Lang::$txt['pm_display_mode'], ':</label>
					</dt>
					<dd>
						<select name="pm_prefs" id="pm_prefs">
							<option value="0"', Utils::$context['display_mode'] == 0 ? ' selected' : '', '>', Lang::$txt['pm_display_mode_all'], '</option>
							<option value="1"', Utils::$context['display_mode'] == 1 ? ' selected' : '', '>', Lang::$txt['pm_display_mode_one'], '</option>
							<option value="2"', Utils::$context['display_mode'] == 2 ? ' selected' : '', '>', Lang::$txt['pm_display_mode_linked'], '</option>
						</select>
					</dd>
					<dt>
						<label for="view_newest_pm_first">', Lang::$txt['recent_pms_at_top'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[view_newest_pm_first]" value="0">
						<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty(Utils::$context['member']['options']['view_newest_pm_first']) ? ' checked' : '', '>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="pm_receive_from">', Lang::$txt['pm_receive_from'], '</label>
					</dt>
					<dd>
						<select name="pm_receive_from" id="pm_receive_from">
							<option value="0"', empty(Utils::$context['receive_from']) || (empty(Config::$modSettings['enable_buddylist']) && Utils::$context['receive_from'] < 3) ? ' selected' : '', '>', Lang::$txt['pm_receive_from_everyone'], '</option>';

	if (!empty(Config::$modSettings['enable_buddylist']))
		echo '
							<option value="1"', !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] == 1 ? ' selected' : '', '>', Lang::$txt['pm_receive_from_ignore'], '</option>
							<option value="2"', !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] == 2 ? ' selected' : '', '>', Lang::$txt['pm_receive_from_buddies'], '</option>';

	echo '
							<option value="3"', !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] > 2 ? ' selected' : '', '>', Lang::$txt['pm_receive_from_admins'], '</option>
						</select>
					</dd>
					<dt>
						<label for="popup_messages">', Lang::$txt['popup_messages'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[popup_messages]" value="0">
						<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty(Utils::$context['member']['options']['popup_messages']) ? ' checked' : '', '>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="pm_remove_inbox_label">', Lang::$txt['pm_remove_inbox_label'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
						<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty(Utils::$context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '', '>
					</dd>';

}

/**
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
function template_profile_theme_settings()
{
	$skeys = array_keys(Utils::$context['theme_options']);
	$first_option_key = array_shift($skeys);
	$titled_section = false;

	foreach (Utils::$context['theme_options'] as $i => $setting)
	{
		// Just spit out separators and move on
		if (empty($setting) || !is_array($setting))
		{
			// Avoid double separators and empty titled sections
			$empty_section = true;
			for ($j=$i+1; $j < count(Utils::$context['theme_options']); $j++)
			{
				// Found another separator, so we're done
				if (!is_array(Utils::$context['theme_options'][$j]))
					break;

				// Once we know there's something to show in this section, we can stop
				if (!isset(Utils::$context['theme_options'][$j]['enabled']) || !empty(Utils::$context['theme_options'][$j]['enabled']))
				{
					$empty_section = false;
					break;
				}
			}
			if ($empty_section)
			{
				if ($i === $first_option_key)
					$first_option_key = array_shift($skeys);

				continue;
			}

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
		{
			if ($i === $first_option_key)
				$first_option_key = array_shift($skeys);

			continue;
		}

		// Some of these may not be set...  Set to defaults here
		$opts = array('calendar_start_day', 'topics_per_page', 'messages_per_page', 'display_quick_mod');
		if (in_array($setting['id'], $opts) && !isset(Utils::$context['member']['options'][$setting['id']]))
			Utils::$context['member']['options'][$setting['id']] = 0;

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
						<input type="checkbox" name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', !empty(Utils::$context['member']['options'][$setting['id']]) ? ' checked' : '', ' value="1">';

		// How about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
						<select name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', '>';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', isset(Utils::$context['member']['options'][$setting['id']]) && $value == Utils::$context['member']['options'][$setting['id']] ? ' selected' : '', '>', $label, '</option>';

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

			echo ' name="default_options[', $setting['id'], ']" id="', $setting['id'], '" value="', isset(Utils::$context['member']['options'][$setting['id']]) ? Utils::$context['member']['options'][$setting['id']] : $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', '>';
		}

		// end of this definition
		echo '
					</dd>';
	}
}

/**
 * The template for configuring alerts
 */
function template_alert_configuration()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::$txt['alert_prefs'], '
			</h3>
		</div>
		<p class="information">
			', (empty(Utils::$context['description']) ? Lang::$txt['alert_prefs_desc'] : Utils::$context['description']), '
		</p>
		<form action="', Config::$scripturl, '?', Utils::$context['action'], '" method="post" accept-charset="', Utils::$context['character_set'], '" id="notify_options" class="flow_auto">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['notification_general'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="notify_announcements">', Lang::$txt['notify_important_email'], '</label>', Utils::$context['id_member'] == 0 ? '
						<br>
						<span class="smalltext alert">' . Lang::$txt['notify_announcements_desc'] . '</span>' : '', '
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0">
						<input type="checkbox" id="notify_announcements" name="notify_announcements" value="1"', !empty(Utils::$context['member']['notify_announcements']) ? ' checked' : '', '>
					</dd>';

	if (!empty(Config::$modSettings['enable_ajax_alerts']))
		echo '
					<dt>
						<label for="notify_send_body">', Lang::$txt['notify_alert_timeout'], '</label>
					</dt>
					<dd>
						<input type="number" size="4" id="notify_alert_timeout" name="opt_alert_timeout" min="0" max="127" value="', Utils::$context['member']['alert_timeout'], '">
					</dd>';

	echo '
				</dl>
			</div><!-- .windowbg -->
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['notify_what_how'], '
				</h3>
			</div>
			<table class="table_grid">';

	foreach (Utils::$context['alert_types'] as $alert_group => $alerts)
	{
		echo '
				<tr class="title_bar">
					<th>', Lang::$txt['alert_group_' . $alert_group], '</th>
					<th>', Lang::$txt['receive_alert'], '</th>
					<th>', Lang::$txt['receive_mail'], '</th>
				</tr>
				<tr class="windowbg">';

		if (isset(Utils::$context['alert_group_options'][$alert_group]))
		{
			foreach (Utils::$context['alert_group_options'][$alert_group] as $opts)
			{
				if ($opts[0] == 'hide')
					continue;

				echo '
				<tr class="windowbg">
					<td colspan="3">';

				$label = Lang::$txt['alert_opt_' . $opts[1]];
				$label_pos = isset($opts['label']) ? $opts['label'] : '';
				if ($label_pos == 'before')
					echo '
						<label for="opt_', $opts[1], '">', $label, '</label>';

				$this_value = isset(Utils::$context['alert_prefs'][$opts[1]]) ? Utils::$context['alert_prefs'][$opts[1]] : 0;
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
						', Lang::$txt['alert_' . $alert_id], isset($alert_details['help']) ? '<a href="' . Config::$scripturl . '?action=helpadmin;help=' . $alert_details['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help floatright"><span class="main_icons help" title="' . Lang::$txt['help'] . '"></span></a>' : '', '
					</td>';

			foreach (Utils::$context['alert_bits'] as $type => $bitmask)
			{
				echo '
					<td class="centercol">';

				$this_value = isset(Utils::$context['alert_prefs'][$alert_id]) ? Utils::$context['alert_prefs'][$alert_id] : 0;
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
				<input id="notify_submit" type="submit" name="notify_submit" value="', Lang::$txt['notify_save'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">', !empty(Utils::$context['token_check']) ? '
				<input type="hidden" name="' . Utils::$context[Utils::$context['token_check'] . '_token_var'] . '" value="' . Utils::$context[Utils::$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
				<input type="hidden" name="sa" value="', Utils::$context['menu_item_selected'], '">
			</div>
		</form>
		<br>';
}

/**
 * Template for showing which topics you're subscribed to
 */
function template_alert_notifications_topics()
{
	// The main containing header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::$txt['watched_topics'], '
			</h3>
		</div>
		<p class="information">', Lang::$txt['watched_topics_desc'], '</p>
		<br>';

	template_show_list('topic_notification_list');
}

/**
 * Template for showing which boards you're subscribed to
 */
function template_alert_notifications_boards()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::$txt['watched_boards'], '
			</h3>
		</div>
		<p class="information">', Lang::$txt['watched_boards_desc'], '</p>
		<br>';

	template_show_list('board_notification_list');
}

/**
 * Template for choosing group membership.
 */
function template_groupMembership()
{
	// The main containing header.
	echo '
		<form action="', Config::$scripturl, '?action=profile;area=groupmembership;save" method="post" accept-charset="', Utils::$context['character_set'], '" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', Lang::$txt['profile'], '
				</h3>
			</div>
			<p class="information">', Lang::$txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty(Utils::$context['update_message']))
		echo '
			<div class="infobox">
				', Utils::$context['update_message'], '
			</div>';

	echo '
			<div id="groups">';

	// Requesting membership to a group?
	if (!empty(Utils::$context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['request_group_membership'], '</h3>
				</div>
				<div class="roundframe">
					', Lang::$txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4"></textarea>
					<div class="righttext">
						<input type="hidden" name="gid" value="', Utils::$context['group_request']['id'], '">
						<input type="submit" name="req" value="', Lang::$txt['submit_request'], '" class="button">
						</div>
					</div>
				</div><!-- .groupmembership -->';
	}
	else
	{
		echo '
				<div class="title_bar">
					<h3 class="titlebg">', Lang::$txt['current_membergroups'], '</h3>
				</div>';

		foreach (Utils::$context['groups']['member'] as $group)
		{
			echo '
				<div class="windowbg" id="primdiv_', $group['id'], '">';

			if (Utils::$context['can_edit_primary'])
				echo '
					<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '"', $group['is_primary'] ? ' checked' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');"', $group['can_be_primary'] ? '' : ' disabled', '>';

			echo '
					<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '</label>';

			// Can they leave their group?
			if ($group['can_leave'])
				echo '
					<a href="' . Config::$scripturl . '?action=profile;save;u=' . Utils::$context['id_member'] . ';area=groupmembership;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';gid=' . $group['id'] . ';', Utils::$context[Utils::$context['token_check'] . '_token_var'], '=', Utils::$context[Utils::$context['token_check'] . '_token'], '">' . Lang::$txt['leave_group'] . '</a>';

			echo '
				</div><!-- .windowbg -->';
		}

		if (Utils::$context['can_edit_primary'])
			echo '
				<div class="padding righttext">
					<input type="submit" value="', Lang::$txt['make_primary'], '" class="button">
				</div>';

		// Any groups they can join?
		if (!empty(Utils::$context['groups']['available']))
		{
			echo '
				<div class="title_bar">
					<h3 class="titlebg">', Lang::$txt['available_groups'], '</h3>
				</div>';

			foreach (Utils::$context['groups']['available'] as $group)
			{
				echo '
				<div class="windowbg">
					<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '';

				if ($group['type'] == 3)
					echo '
					<a href="', Config::$scripturl, '?action=profile;save;u=', Utils::$context['id_member'], ';area=groupmembership;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';gid=', $group['id'], ';', Utils::$context[Utils::$context['token_check'] . '_token_var'], '=', Utils::$context[Utils::$context['token_check'] . '_token'], '" class="button floatright">', Lang::$txt['join_group'], '</a>';

				elseif ($group['type'] == 2 && $group['pending'])
					echo '
					<span class="floatright">', Lang::$txt['approval_pending'], '</span>';

				elseif ($group['type'] == 2)
					echo '
					<a href="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=groupmembership;request=', $group['id'], '" class="button floatright">', Lang::$txt['request_group'], '</a>';

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
		if (isset(Utils::$context['groups']['member'][Utils::$context['primary_group']]))
			echo '
					highlightSelected("primdiv_' . Utils::$context['primary_group'] . '");';

		echo '
				</script>';
	}

	echo '
			</div><!-- #groups -->';

	if (!empty(Utils::$context['token_check']))
		echo '
			<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
		</form>';
}

/**
 * Template for managing ignored boards
 */
function template_ignoreboards()
{
	// The main containing header.
	echo '
	<form action="', Config::$scripturl, '?action=profile;area=ignoreboards;save" method="post" accept-charset="', Utils::$context['character_set'], '" name="creator" id="creator">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', Lang::$txt['profile'], '
			</h3>
		</div>
		<p class="information">', Lang::$txt['ignoreboards_info'], '</p>
		<div class="windowbg">
			<div class="flow_hidden boardslist">
				<ul>';

	foreach (Utils::$context['categories'] as $category)
	{
		echo '
					<li>
						<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'creator\'); return false;">', $category['name'], '</a>
						<ul>';

		$cat_boards = array_values($category['boards']);
		foreach ($cat_boards as $key => $board)
		{
			echo '
							<li>
								<label for="brd', $board['id'], '">
									<input type="checkbox" id="brd', $board['id'], '" name="brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '>
									', $board['name'], '
								</label>';

			// Nest child boards inside another list.
			$curr_child_level = $board['child_level'];
			$next_child_level = $cat_boards[$key + 1]['child_level'] ?? 0;

			if ($next_child_level > $curr_child_level)
			{
				echo '
								<ul style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': 2.5ch;">';
			}
			else
			{
				// Close child board lists until we reach a common level
				// with the next board.
				while ($next_child_level < $curr_child_level--)
				{
					echo '
									</li>
								</ul>';
				}

				echo '
							</li>';
			}
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
	// Setup the warning mode
	Utils::$context['warning_mode'] = array(
		0 => 'none',
		Config::$modSettings['warning_watch'] => 'watched',
		Config::$modSettings['warning_moderate'] => 'moderated',
		Config::$modSettings['warning_mute'] => 'muted',
	);

	// Work out the starting warning.
	Utils::$context['current_warning_mode'] = Utils::$context['warning_mode'][0];
	foreach (Utils::$context['warning_mode'] as $limit => $warning)
		if (Utils::$context['member']['warning'] >= $limit)
			Utils::$context['current_warning_mode'] = $warning;
}

/**
 * Template for viewing a user's warnings
 */
function template_viewWarning()
{
	template_load_warning_variables();

	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', sprintf(Lang::$txt['profile_viewwarning_for_user'], Utils::$context['member']['name']), '
			</h3>
		</div>
		<p class="information">', Lang::$txt['viewWarning_help'], '</p>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', Lang::$txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['member']['name'], '
				</dd>
				<dt>
					<strong>', Lang::$txt['profile_warning_level'], ':</strong>
				</dt>
				<dd>
					<div class="generic_bar warning_level ', Utils::$context['current_warning_mode'], '">
						<div class="bar" style="width: ', Utils::$context['member']['warning'], '%;"></div>
						<span>', Utils::$context['member']['warning'], '%</span>
					</div>
				</dd>';

	// There's some impact of this?
	if (!empty(Utils::$context['level_effects'][Utils::$context['current_level']]))
		echo '
				<dt>
					<strong>', Lang::$txt['profile_viewwarning_impact'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['level_effects'][Utils::$context['current_level']], '
				</dd>';

	echo '
			</dl>
		</div><!-- .windowbg -->';

	template_show_list('view_warnings');
}

/**
 * Template for issuing warnings
 */
function template_issueWarning()
{
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

	foreach (Utils::$context['notification_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";';

	echo '
		}

		function updateSlider(slideAmount)
		{
			// Also set the right effect.
			effectText = "";';

	foreach (Utils::$context['level_effects'] as $limit => $text)
		echo '
			if (slideAmount >= ', $limit, ')
				effectText = "', $text, '";';

	echo '
			setInnerHTML(document.getElementById(\'cur_level_div\'), slideAmount + \'% (\' + effectText + \')\');
		}
	</script>';

	echo '
	<form action="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', User::$me->is_owner ? Lang::$txt['profile_warning_level'] : Lang::$txt['profile_issue_warning'], '
			</h3>
		</div>';

	if (!User::$me->is_owner)
		echo '
		<p class="information">', Lang::$txt['profile_warning_desc'], '</p>';

	echo '
		<div class="windowbg">
			<dl class="settings">';

	if (!User::$me->is_owner)
		echo '
				<dt>
					<strong>', Lang::$txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					<strong>', Utils::$context['member']['name'], '</strong>
				</dd>';

	echo '
				<dt>
					<strong>', Lang::$txt['profile_warning_level'], ':</strong>';

	// Is there only so much they can apply?
	if (Utils::$context['warning_limit'])
		echo '
					<br>
					<span class="smalltext">', sprintf(Lang::$txt['profile_warning_limit_attribute'], Utils::$context['warning_limit']), '</span>';

	echo '
				</dt>
				<dd>
					0% <input name="warning_level" id="warning_level" type="range" min="0" max="100" step="5" value="', Utils::$context['member']['warning'], '" onchange="updateSlider(this.value)"> 100%
					<div class="clear_left">
						', Lang::$txt['profile_warning_impact'], ': <span id="cur_level_div">', Utils::$context['member']['warning'], '% (', Utils::$context['level_effects'][Utils::$context['current_level']], ')</span>
					</div>
				</dd>';

	if (!User::$me->is_owner)
	{
		echo '
				<dt>
					<strong>', Lang::$txt['profile_warning_reason'], ':</strong><br>
					<span class="smalltext">', Lang::$txt['profile_warning_reason_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="warn_reason" id="warn_reason" value="', Utils::$context['warning_data']['reason'], '" size="50">
				</dd>
			</dl>
			<hr>
			<div id="box_preview"', !empty(Utils::$context['warning_data']['body_preview']) ? '' : ' style="display:none"', '>
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['preview'], '</strong>
					</dt>
					<dd id="body_preview">
						', !empty(Utils::$context['warning_data']['body_preview']) ? Utils::$context['warning_data']['body_preview'] : '', '
					</dd>
				</dl>
				<hr>
			</div>
			<dl class="settings">
				<dt>
					<strong><label for="warn_notify">', Lang::$txt['profile_warning_notify'], ':</label></strong>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();"', Utils::$context['warning_data']['notify'] ? ' checked' : '', '>
				</dd>
				<dt>
					<strong><label for="warn_sub">', Lang::$txt['profile_warning_notify_subject'], ':</label></strong>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="', empty(Utils::$context['warning_data']['notify_subject']) ? Lang::$txt['profile_warning_notify_template_subject'] : Utils::$context['warning_data']['notify_subject'], '" size="50">
				</dd>
				<dt>
					<strong><label for="warn_temp">', Lang::$txt['profile_warning_notify_body'], ':</label></strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled onchange="populateNotifyTemplate();">
						<option value="-1">', Lang::$txt['profile_warning_notify_template'], '</option>
						<option value="-1" disabled>------------------------------</option>';

		foreach (Utils::$context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
					</select>
					<span class="smalltext" id="new_template_link" style="display: none;">[<a href="', Config::$scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" rel="noopener">', Lang::$txt['profile_warning_new_template'], '</a>]</span>
					<br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8">', Utils::$context['warning_data']['notify_body'], '</textarea>
				</dd>';
	}
	echo '
			</dl>
			<div class="righttext">';

	if (!empty(Utils::$context['token_check']))
		echo '
				<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="button" name="preview" id="preview_button" value="', Lang::$txt['preview'], '" class="button">
				<input type="submit" name="save" value="', User::$me->is_owner ? Lang::$txt['change_profile'] : Lang::$txt['profile_warning_issue'], '" class="button">
			</div><!-- .righttext -->
		</div><!-- .windowbg -->
	</form>';

	// Previous warnings?
	template_show_list('view_warnings');

	echo '
	<script>';

	if (!User::$me->is_owner)
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
				headers: {
					"X-SMF-AJAX": 1
				},
				xhrFields: {
					withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
				},
				url: "' . Config::$scripturl . '?action=xmlhttp;sa=previews;xml",
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
	// The main containing header.
	echo '
		<form action="', Config::$scripturl, '?action=profile;area=deleteaccount;save" method="post" accept-charset="', Utils::$context['character_set'], '" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', Lang::$txt['deleteAccount'], '
				</h3>
			</div>';

	// If deleting another account give them a lovely info box.
	if (!User::$me->is_owner)
		echo '
			<p class="information">', Lang::$txt['deleteAccount_desc'], '</p>';

	echo '
			<div class="windowbg">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if (Utils::$context['needs_approval'])
		echo '
				<div class="errorbox">', Lang::$txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if (User::$me->is_owner)
	{
		echo '
				<div class="alert">', Lang::$txt['own_profile_confirm'], '</div>
				<div>
					<strong', (isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : ''), '>', Lang::$txt['current_password'], ': </strong>
					<input type="password" name="oldpasswrd" size="20">
					<input type="submit" value="', Lang::$txt['yes'], '" class="button">';

		if (!empty(Utils::$context['token_check']))
			echo '
					<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
					<input type="hidden" name="sa" value="', Utils::$context['menu_item_selected'], '">
				</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
				<div class="alert">', Lang::$txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if (Utils::$context['can_delete_posts'])
		{
			echo '
				<div>
					<label for="deleteVotes">
						<input type="checkbox" name="deleteVotes" id="deleteVotes" value="1"> ', Lang::$txt['deleteAccount_votes'], ':
					</label><br>
					<label for="deletePosts">
						<input type="checkbox" name="deletePosts" id="deletePosts" value="1"> ', Lang::$txt['deleteAccount_posts'], ':
					</label>
					<select name="remove_type">
						<option value="posts">', Lang::$txt['deleteAccount_all_posts'], '</option>
						<option value="topics">', Lang::$txt['deleteAccount_topics'], '</option>
					</select>';

			if (Utils::$context['show_perma_delete'])
				echo '
					<br>
					<label for="perma_delete"><input type="checkbox" name="perma_delete" id="perma_delete" value="1">', Lang::$txt['deleteAccount_permanent'], '</label>';

			echo '
				</div>';
		}

		echo '
				<div>
					<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" onclick="if (this.checked) return confirm(\'', Lang::$txt['deleteAccount_confirm'], '\');"> ', Lang::$txt['deleteAccount_member'], '.</label>
				</div>
				<div>
					<input type="submit" value="', Lang::$txt['delete'], '" class="button">';

		if (!empty(Utils::$context['token_check']))
			echo '
				<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
					<input type="hidden" name="sa" value="', Utils::$context['menu_item_selected'], '">
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
	echo '

					<hr>';

	// Only show the password box if it's actually needed.
	if (Utils::$context['require_password'])
		echo '
					<dl class="settings">
						<dt>
							<strong', isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '', '>', Lang::$txt['current_password'], ': </strong><br>
							<span class="smalltext">', Lang::$txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20">
						</dd>
					</dl>';

	echo '
					<div class="righttext">';

	if (!empty(Utils::$context['token_check']))
		echo '
						<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	echo '
						<input type="submit" value="', Lang::$txt['change_profile'], '" class="button">
						<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
						<input type="hidden" name="sa" value="', Utils::$context['menu_item_selected'], '">
					</div>';
}

/**
 * Small template for showing an error message upon a save problem in the profile.
 */
function template_error_message()
{
	echo '
		<div class="errorbox" ', empty(Utils::$context['post_errors']) ? 'style="display:none" ' : '', 'id="profile_error">';

	if (!empty(Utils::$context['post_errors']))
	{
		echo '
			<span>', !empty(Utils::$context['custom_error_title']) ? Utils::$context['custom_error_title'] : Lang::$txt['profile_errors_occurred'], ':</span>
			<ul id="list_errors">';

		// Cycle through each error and display an error message.
		foreach (Utils::$context['post_errors'] as $error)
		{
			$text_key_error = $error == 'password_short' ?
				sprintf(Lang::$txt['profile_error_' . $error], (empty(Config::$modSettings['password_strength']) ? 4 : 8)) :
				(isset(Lang::$txt['profile_error_' . $error]) ? Lang::$txt['profile_error_' . $error] : '');

			echo '
				<li>', isset(Lang::$txt['profile_error_' . $error]) ? $text_key_error : $error, '</li>';
		}

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
	echo '
							<dt>
								<strong>', Lang::$txt['primary_membergroup'], '</strong><br>
								<span class="smalltext"><a href="', Config::$scripturl, '?action=helpadmin;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span> ', Lang::$txt['moderator_why_missing'], '</a></span>
							</dt>
							<dd>
								<select name="id_group" ', (User::$me->is_owner && Utils::$context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . Lang::$txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

	// Fill the select box with all primary member groups that can be assigned to a member.
	foreach (Utils::$context['member_groups'] as $member_group)
		if (!empty($member_group['can_be_primary']))
			echo '
									<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected' : '', '>
										', $member_group['name'], '
									</option>';

	echo '
								</select>
							</dd>
							<dt>
								<strong>', Lang::$txt['additional_membergroups'], '</strong>
							</dt>
							<dd>
								<span id="additional_groupsList">
									<input type="hidden" name="additional_groups[]" value="0">';

	// For each membergroup show a checkbox so members can be assigned to more than one group.
	foreach (Utils::$context['member_groups'] as $member_group)
		if ($member_group['can_be_additional'])
			echo '
									<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', '> ', $member_group['name'], '</label><br>';

	echo '
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down">', Lang::$txt['additional_membergroups_show'], '</a>
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
	// Just show the pretty box!
	echo '
							<dt>
								<strong>', Lang::$txt['dob'], '</strong><br>
								<span class="smalltext">', Lang::$txt['dob_year'], ' - ', Lang::$txt['dob_month'], ' - ', Lang::$txt['dob_day'], '</span>
							</dt>
							<dd>
								<input type="text" name="bday3" size="4" maxlength="4" value="', Utils::$context['member']['birth_date']['year'], '"> -
								<input type="text" name="bday1" size="2" maxlength="2" value="', Utils::$context['member']['birth_date']['month'], '"> -
								<input type="text" name="bday2" size="2" maxlength="2" value="', Utils::$context['member']['birth_date']['day'], '">
							</dd>';
}

/**
 * Show the signature editing box?
 */
function template_profile_signature_modify()
{
	echo '
							<dt id="current_signature" style="display:none">
								<strong>', Lang::$txt['current_signature'], '</strong>
							</dt>
							<dd id="current_signature_display" style="display:none">
								<hr>
							</dd>

							<dt id="preview_signature" style="display:none">
								<strong>', Lang::$txt['signature_preview'], '</strong>
							</dt>
							<dd id="preview_signature_display" style="display:none">
								<hr>
							</dd>

							<dt>
								<strong>', Lang::$txt['signature'], '</strong><br>
								<span class="smalltext">', Lang::$txt['sig_info'], '</span><br>
								<br>';

	if (Utils::$context['show_spellchecking'])
		echo '
								<input type="button" value="', Lang::$txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\');" class="button">';

	echo '
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50">', Utils::$context['member']['signature'], '</textarea><br>';

	// If there is a limit at all!
	if (!empty(Utils::$context['signature_limits']['max_length']))
		echo '
								<span class="smalltext">', sprintf(Lang::$txt['max_sig_characters'], Utils::$context['signature_limits']['max_length']), ' <span id="signatureLeft">', Utils::$context['signature_limits']['max_length'], '</span></span><br>';

	if (!empty(Utils::$context['show_preview_button']))
		echo '
								<input type="button" name="preview_signature" id="preview_button" value="', Lang::$txt['preview_signature'], '" class="button floatright">';

	if (Utils::$context['signature_warning'])
		echo '
								<span class="smalltext">', Utils::$context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script>
									var maxLength = ', Utils::$context['signature_limits']['max_length'], ';

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
	// Start with the upper menu
	echo '
							<dt>
								<strong id="personal_picture">
									<label for="avatar_upload_box">', Lang::$txt['personal_picture'], '</label>
								</strong>';

	if (empty(Config::$modSettings['gravatarEnabled']) || empty(Config::$modSettings['gravatarOverride']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . (Utils::$context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_none"' . (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . Lang::$txt['no_avatar'] . '
								</label><br>';

	if (!empty(Utils::$context['member']['avatar']['allow_server_stored']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . (Utils::$context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_server_stored"' . (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', Lang::$txt['choose_avatar_gallery'], '
								</label><br>';

	if (!empty(Utils::$context['member']['avatar']['allow_external']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . (Utils::$context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_external"' . (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', Lang::$txt['my_own_pic'], '
								</label><br>';

	if (!empty(Utils::$context['member']['avatar']['allow_upload']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . (Utils::$context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_upload"' . (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									', Lang::$txt['avatar_will_upload'], '
								</label><br>';

	if (!empty(Utils::$context['member']['avatar']['allow_gravatar']))
		echo '
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . (Utils::$context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . '>
								<label for="avatar_choice_gravatar"' . (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . Lang::$txt['use_gravatar'] . '</label>
								<span class="smalltext"><a href="', Config::$scripturl, '?action=helpadmin;help=gravatar" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span></a></span>';

	echo '
							</dt>
							<dd>';

	// If users are allowed to choose avatars stored on the server show selection boxes to choice them from.
	if (!empty(Utils::$context['member']['avatar']['allow_server_stored']))
	{
		echo '
								<div id="avatar_server_stored">
									<div>
										<select name="cat" id="cat" size="10" onchange="changeSel(\'\');" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');">';

		// This lists all the file categories.
		foreach (Utils::$context['avatars'] as $avatar)
			echo '
											<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected' : ''), '>', $avatar['name'], '</option>';

		echo '
										</select>
									</div>
									<div>
										<select name="file" id="file" size="10" style="display: none;" onchange="showAvatar()" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');" disabled><option></option></select>
									</div>
									<div class="edit_avatar_img">
										<img id="avatar" src="', Utils::$context['member']['avatar']['choice'] == 'server_stored' ? Utils::$context['member']['avatar']['href'] : Config::$modSettings['avatar_url'] . '/blank.png', '" alt="">
									</div>
									<script>
										var files = ["' . implode('", "', Utils::$context['avatar_list']) . '"];
										var avatar = document.getElementById("avatar");
										var cat = document.getElementById("cat");
										var selavatar = "' . Utils::$context['avatar_selected'] . '";
										var avatardir = "' . Config::$modSettings['avatar_url'] . '/";
										var size = avatar.alt.substr(3, 2) + " " + avatar.alt.substr(0, 2) + String.fromCharCode(117, 98, 116);
										var file = document.getElementById("file");

										if (avatar.src.indexOf("blank.png") > -1 || selavatar.indexOf("blank.png") == -1)
											changeSel(selavatar);
										else
											previewExternalAvatar(avatar.src)

									</script>
								</div><!-- #avatar_server_stored -->';
	}

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty(Utils::$context['member']['avatar']['allow_external']))
		echo '
								<div id="avatar_external">
									', Utils::$context['member']['avatar']['choice'] == 'external' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt="" class="avatar"></div>' : '', '
									<div class="smalltext">', Lang::$txt['avatar_by_url'], '</div>', !empty(Config::$modSettings['avatar_action_too_large']) && Config::$modSettings['avatar_action_too_large'] == 'option_download_and_resize' ? template_max_size('external') : '', '
									<input type="text" name="userpicpersonal" size="45" value="', ((stristr(Utils::$context['member']['avatar']['external'], 'http://') || stristr(Utils::$context['member']['avatar']['external'], 'https://')) ? Utils::$context['member']['avatar']['external'] : 'http://'), '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value);"><br>
								</div>';

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty(Utils::$context['member']['avatar']['allow_upload']))
		echo '
								<div id="avatar_upload">
									', Utils::$context['member']['avatar']['choice'] == 'upload' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt=""></div>' : '', '
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onchange="readfromUpload(this)"  onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" accept="image/gif, image/jpeg, image/jpg, image/png">', template_max_size('upload'), '
									', (!empty(Utils::$context['member']['avatar']['id_attach']) ? '<br><input type="hidden" name="id_attach" value="' . Utils::$context['member']['avatar']['id_attach'] . '">' : ''), '
								</div>';

	// if the user is able to use Gravatar avatars show then the image preview
	if (!empty(Utils::$context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									', Utils::$context['member']['avatar']['choice'] == 'gravatar' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt=""></div>' : '';

		if (empty(Config::$modSettings['gravatarAllowExtraEmail']))
			echo '
									<div class="smalltext">', Lang::$txt['gravatar_noAlternateEmail'], '</div>';
		else
		{
			// Depending on other stuff, the stored value here might have some odd things in it from other areas.
			if (Utils::$context['member']['avatar']['external'] == Utils::$context['member']['email'])
				$textbox_value = '';
			else
				$textbox_value = Utils::$context['member']['avatar']['external'];

			echo '
									<div class="smalltext">', Lang::$txt['gravatar_alternateEmail'], '</div>
									<input type="text" name="gravatarEmail" id="gravatarEmail" size="45" value="', $textbox_value, '">';
		}
		echo '
								</div><!-- #avatar_gravatar -->';
	}

	echo '
								<script>
									', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "' . (Utils::$context['member']['avatar']['choice'] == 'server_stored' ? '' : 'none') . '";' : '', '
									', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "' . (Utils::$context['member']['avatar']['choice'] == 'external' ? '' : 'none') . '";' : '', '
									', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "' . (Utils::$context['member']['avatar']['choice'] == 'upload' ? '' : 'none') . '";' : '', '
									', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "' . (Utils::$context['member']['avatar']['choice'] == 'gravatar' ? '' : 'none') . '";' : '', '

									function swap_avatar(type)
									{
										switch(type.id)
										{
											case "avatar_choice_server_stored":
												', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_external":
												', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_upload":
												', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_none":
												', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_gravatar":
												', !empty(Utils::$context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty(Utils::$context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "";' : '', '
												', !empty(Config::$modSettings['gravatarAllowExtraEmail']) && (Utils::$context['member']['avatar']['external'] == Utils::$context['member']['email'] || strstr(Utils::$context['member']['avatar']['external'], 'http://') || strstr(Utils::$context['member']['avatar']['external'], 'https://')) ?
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
	$w = !empty(Config::$modSettings['avatar_max_width_' . $type]) ? Lang::numberFormat(Config::$modSettings['avatar_max_width_' . $type]) : 0;
	$h = !empty(Config::$modSettings['avatar_max_height_' . $type]) ? Lang::numberFormat(Config::$modSettings['avatar_max_height_' . $type]) : 0;

	$suffix = (!empty($w) ? 'w' : '') . (!empty($h) ? 'h' : '');
	if (empty($suffix))
		return;

	echo '
								<div class="smalltext">', sprintf(Lang::$txt['avatar_max_size_' . $suffix], $w, $h), '</div>';
}

/**
 * Select the time format!
 */
function template_profile_timeformat_modify()
{
	echo '
							<dt>
								<strong><label for="easyformat">', Lang::$txt['time_format'], '</label></strong><br>
								<a href="', Config::$scripturl, '?action=helpadmin;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
								<span class="smalltext">
									<label for="time_format">', Lang::$txt['date_format'], '</label>
								</span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;">';

	// Help the user by showing a list of common time formats.
	foreach (Utils::$context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == Utils::$context['member']['time_format'] ? ' selected' : '', '>', $time_format['title'], '</option>';

	echo '
								</select>
								<input type="text" name="time_format" id="time_format" value="', Utils::$context['member']['time_format'], '" size="30">
							</dd>';
}

/**
 * Template for picking a theme
 */
function template_profile_theme_pick()
{
	echo '
							<dt>
								<strong>', Lang::$txt['current_theme'], '</strong>
							</dt>
							<dd>
								', Utils::$context['member']['theme']['name'], ' <a class="button" href="', Config::$scripturl, '?action=theme;sa=pick;u=', Utils::$context['id_member'], '">', Lang::$txt['change'], '</a>
							</dd>';
}

/**
 * Smiley set picker.
 */
function template_profile_smiley_pick()
{
	echo '
							<dt>
								<strong><label for="smiley_set">', Lang::$txt['smileys_current'], '</label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set">';

	foreach (Utils::$context['smiley_sets'] as $set)
		echo '
									<option data-preview="', $set['preview'], '" value="', $set['id'], '"', $set['selected'] ? ' selected' : '', '>', $set['name'], '</option>';

	echo '
								</select>
								<img id="smileypr" class="centericon" src="', Utils::$context['member']['smiley_set']['preview'], '" alt=":)">
							</dd>';
}

/**
 * Template for setting up and managing Two-Factor Authentication.
 */
function template_tfasetup()
{
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['tfa_title'], '</h3>
			</div>
			<div class="roundframe">
				<div>';

	if (!empty(Utils::$context['tfa_backup']))
		echo '
					<div class="smalltext error">
						', Lang::$txt['tfa_backup_used_desc'], '
					</div>';

	elseif (Config::$modSettings['tfa_mode'] == 2)
		echo '
					<div class="smalltext">
						<strong>', Lang::$txt['tfa_forced_desc'], '</strong>
					</div>';

	echo '
					<div class="smalltext">
						', Lang::$txt['tfa_desc'], '
					</div>
					<div class="floatleft">
						<form action="', Config::$scripturl, '?action=profile;area=tfasetup" method="post">
							<div class="block">
								<strong>', Lang::$txt['tfa_step1'], '</strong><br>';

	if (!empty(Utils::$context['tfa_pass_error']))
		echo '
								<div class="error smalltext">
									', Lang::$txt['tfa_pass_invalid'], '
								</div>';

	echo '
								<input type="password" name="oldpasswrd" size="25"', !empty(Utils::$context['password_auth_failed']) ? ' class="error"' : '', !empty(Utils::$context['tfa_pass_value']) ? ' value="' . Utils::$context['tfa_pass_value'] . '"' : '', '>
							</div>
							<div class="block">
								<strong>', Lang::$txt['tfa_step2'], '</strong>
								<div class="smalltext">', Lang::$txt['tfa_step2_desc'], '</div>
								<div class="tfacode">', Utils::$context['tfa_secret'], '</div>
							</div>
							<div class="block">
								<strong>', Lang::$txt['tfa_step3'], '</strong><br>';

	if (!empty(Utils::$context['tfa_error']))
		echo '
								<div class="error smalltext">
									', Lang::$txt['tfa_code_invalid'], '
								</div>';

	echo '
								<input type="text" name="tfa_code" size="25"', !empty(Utils::$context['tfa_error']) ? ' class="error"' : '', !empty(Utils::$context['tfa_value']) ? ' value="' . Utils::$context['tfa_value'] . '"' : '', '>
								<input type="submit" name="save" value="', Lang::$txt['tfa_enable'], '" class="button">
							</div>
							<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						</form>
					</div>
					<div class="floatright tfa_qrcode">
						<div id="qrcode"></div>
						<script type="text/javascript">
							new QRCode(document.getElementById("qrcode"), "', Utils::$context['tfa_qr_url'], '");
						</script>
					</div>';

	if (!empty(Utils::$context['from_ajax']))
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
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['tfadisable'], '</h3>
			</div>
			<div class="roundframe">
				<form action="', Config::$scripturl, '?action=profile;area=tfadisable" method="post">';

	if (User::$me->is_owner)
		echo '
					<div class="block">
						<strong', (isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : ''), '>', Lang::$txt['current_password'], '</strong><br>
						<input type="password" name="oldpasswrd" size="20">
					</div>';
	else
		echo '
					<div class="smalltext">
						', sprintf(Lang::$txt['tfa_disable_for_user'], User::$me->name), '
					</div>';

	echo '
					<input type="submit" name="save" value="', Lang::$txt['tfa_disable'], '" class="button floatright">
					<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="u" value="', Utils::$context['id_member'], '">
				</form>
			</div><!-- .roundframe -->';
}

/**
 * Template for setting up 2FA backup code
 */
function template_tfasetup_backup()
{
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['tfa_backup_title'], '</h3>
			</div>
			<div class="roundframe">
				<div>
					<div class="smalltext">', Lang::$txt['tfa_backup_desc'], '</div>
					<div class="bbc_code" style="resize: none; border: none;">', Utils::$context['tfa_backup'], '</div>
				</div>
			</div>';
}

/**
 * Simple template for showing the 2FA area when editing a profile.
 */
function template_profile_tfa()
{
	echo '
							<dt>
								<strong>', Lang::$txt['tfa_profile_label'], '</strong><br>
								<div class="smalltext">', Lang::$txt['tfa_profile_desc'], '</div>
							</dt>
							<dd>';

	if (!Utils::$context['tfa_enabled'] && User::$me->is_owner)
		echo '
								<a href="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, array('http://' => 'https://')) : Config::$scripturl, '?action=profile;area=tfasetup" id="enable_tfa">', Lang::$txt['tfa_profile_enable'], '</a>';

	elseif (!Utils::$context['tfa_enabled'])
		echo '
								', Lang::$txt['tfa_profile_disabled'];

	else
		echo '
								', sprintf(Lang::$txt['tfa_profile_enabled'], (!empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, array('http://' => 'https://')) : Config::$scripturl) . '?action=profile;u=' . Utils::$context['id_member'] . ';area=tfadisable');

	echo '
							</dd>';
}

/**
 * Template for initiating and retrieving profile data exports
 */
function template_export_profile_data()
{
	$default_settings = array('included' => array(), 'format' => '');
	$dltoken = '';

	// The main containing header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', Lang::$txt['export_profile_data'], '
			</h3>
		</div>
		<div class="information">', Utils::$context['export_profile_data_desc'], '</div>';

	if (!empty(Utils::$context['completed_exports']))
	{
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['completed_exports'], '</h3>
		</div>
		<div class="windowbg noup">';

		foreach (Utils::$context['completed_exports'] as $basehash_ext => $parts)
		{
			echo '
			<form action="', Config::$scripturl, '?action=profile;area=getprofiledata;u=', Utils::$context['id_member'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="', count(Utils::$context['completed_exports']) > 1 ? 'descbox' : 'padding', '">';

			if (!empty(Utils::$context['outdated_exports'][$basehash_ext]))
			{
				echo '
				<div class="noticebox">
					<p>', Lang::$txt['export_outdated_warning'], '</p>
					<ul class="bbc_list">';

				foreach (Utils::$context['outdated_exports'][$basehash_ext] as $datatype)
					echo '
						<li>', Lang::$txt[$datatype], '</li>';

				echo '
					</ul>
				</div>';
			}

			echo '
				<p>', sprintf(Lang::$txt['export_file_desc'], $parts[1]['included_desc'], Utils::$context['export_formats'][$parts[1]['format']]['description']), '</p>';

			if (count($parts) > 10)
				echo '
				<details>
					<summary>', sprintf(Lang::$txt['export_file_count'], count($parts)), '</summary>';

			echo '
				<ul class="bbc_list" id="', $parts[1]['format'], '_export_files">';

			foreach ($parts as $part => $file)
			{
				$dltoken = $file['dltoken'];
				if (empty($default_settings['included']))
					$default_settings['included'] = $file['included'];
				if (empty($default_settings['format']))
					$default_settings['format'] = $file['format'];

				echo '
					<li>
						<a href="', Config::$scripturl, '?action=profile;area=download;u=', Utils::$context['id_member'], ';format=', $file['format'], ';part=', $part, ';t=', $dltoken, '" class="bbc_link" download>', $file['dlbasename'], '</a> (', $file['size'], ', ', $file['mtime'], ')
					</li>';
			}

			echo '
				</ul>';

			if (count($parts) > 10)
				echo '
				</details>';

			echo '
				<div class="righttext">
					<input type="submit" name="delete" value="', Lang::$txt['delete'], '" class="button you_sure">
					<input type="hidden" name="format" value="', $parts[1]['format'], '">
					<input type="hidden" name="t" value="', $dltoken, '">
					<button type="button" class="button export_download_all" style="display:none" onclick="export_download_all(\'', $parts[1]['format'], '\');">', Lang::$txt['export_download_all'], '</button>
				</div>
			</form>';
		}

		echo '
		</div>';
	}

	if (!empty(Utils::$context['active_exports']))
	{
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['active_exports'], '</h3>
		</div>
		<div class="windowbg noup">';

		foreach (Utils::$context['active_exports'] as $file)
		{
			$dltoken = $file['dltoken'];
			if (empty($default_settings['included']))
				$default_settings['included'] = $file['included'];
			if (empty($default_settings['format']))
				$default_settings['format'] = $file['format'];

			echo '
			<form action="', Config::$scripturl, '?action=profile;area=getprofiledata;u=', Utils::$context['id_member'], '" method="post" accept-charset="', Utils::$context['character_set'], '"', count(Utils::$context['active_exports']) > 1 ? ' class="descbox"' : '', '>
				<p class="padding">', sprintf(Lang::$txt['export_file_desc'], $file['included_desc'], Utils::$context['export_formats'][$file['format']]['description']), '</p>
				<div class="righttext">
					<input type="submit" name="delete" value="', Lang::$txt['export_cancel'], '" class="button you_sure">
					<input type="hidden" name="format" value="', $file['format'], '">
					<input type="hidden" name="t" value="', $dltoken, '">
				</div>
			</form>';
		}

		echo '
		</div>';
	}

	echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['export_settings'], '</h3>
		</div>
		<div class="windowbg noup">
			<form action="', Config::$scripturl, '?action=profile;area=getprofiledata;u=', Utils::$context['id_member'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
				<dl class="settings">';

	foreach (Utils::$context['export_datatypes'] as $datatype => $datatype_settings)
	{
		if (!empty($datatype_settings['label']))
			echo '
					<dt>
						<strong><label for="', $datatype, '">', $datatype_settings['label'], '</label></strong>
					</dt>
					<dd>
						<input type="checkbox" id="', $datatype, '" name="', $datatype, '"', in_array($datatype, $default_settings['included']) ? ' checked' : '', '>
					</dd>';
	}

	echo '
				</dl>
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['export_format'], '</strong>
					</dt>
					<dd>
						<select id="export_format_select" name="format">';

	foreach (Utils::$context['export_formats'] as $format => $format_settings)
		echo '
							<option value="', $format, '"', $format == $default_settings['format'] ? ' selected' : '', '>', $format_settings['description'], '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<div class="righttext">';

	// At least one active or completed export exists.
	if (!empty($dltoken))
	{
		echo '
					<div id="export_begin" style="display:none">
						<input type="submit" name="export_begin" value="', Lang::$txt['export_begin'], '" class="button">
					</div>
					<div id="export_restart">
						<input type="submit" name="export_begin" value="', Lang::$txt['export_restart'], '" class="button you_sure" data-confirm="', Lang::$txt['export_restart_confirm'], '">
						<input type="hidden" name="delete">
						<input type="hidden" name="t" value="', $dltoken, '">
					</div>';
	}
	// No existing exports.
	else
	{
		echo '
					<input type="submit" name="export_begin" value="', Lang::$txt['export_begin'], '" class="button">';
	}

	echo '
					<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</div>
			</form>
		</div><!-- .windowbg -->';
}

?>