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
 * This is for stuff above the menu in the personal messages section
 */
function template_pm_above()
{
	echo '
	<div id="personal_messages">';

	// Show the capacity bar, if available.
	if (!empty(Utils::$context['limit_bar']))
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft">', Lang::$txt['pm_capacity'], ':</span>
				<span class="floatleft capacity_bar">
					<span class="', Utils::$context['limit_bar']['percent'] > 85 ? 'full' : (Utils::$context['limit_bar']['percent'] > 40 ? 'filled' : 'empty'), '" style="width: ', Utils::$context['limit_bar']['percent'] / 10, 'em;"></span>
				</span>
				<span class="floatright', Utils::$context['limit_bar']['percent'] > 90 ? ' alert' : '', '">', Utils::$context['limit_bar']['text'], '</span>
			</h3>
		</div>';

	// Message sent? Show a small indication.
	if (isset(Utils::$context['pm_sent']))
		echo '
		<div class="infobox">
			', Lang::$txt['pm_sent'], '
		</div>';
}

/**
 * Just the end of the index bar, nothing special.
 */
function template_pm_below()
{
	echo '
	</div><!-- #personal_messages -->';
}

/**
 * Displays a popup with information about your personal messages
 */
function template_pm_popup()
{
	// Unlike almost every other template, this is designed to be included into the HTML directly via $().load()
	echo '
		<div class="pm_bar">
			<div class="pm_sending block">
				', Utils::$context['can_send_pm'] ? '<a href="' . Config::$scripturl . '?action=pm;sa=send">' . Lang::$txt['pm_new_short'] . '</a>' : '', '
				', Utils::$context['can_draft'] ? ' | <a href="' . Config::$scripturl . '?action=pm;sa=showpmdrafts">' . Lang::$txt['pm_drafts_short'] . '</a>' : '', '
				<a href="', Config::$scripturl, '?action=pm;sa=settings" class="floatright">', Lang::$txt['pm_settings_short'], '</a>
			</div>
			<div class="pm_mailbox centertext">
				<a href="', Config::$scripturl, '?action=pm" class="button">', Lang::$txt['inbox'], '</a>
				<a href="', Config::$scripturl, '?action=pm;f=sent" class="button">', Lang::$txt['sent_items'], '</a>
			</div>
		</div>
		<div class="pm_unread">';

	if (empty(Utils::$context['unread_pms']))
		echo '
			<div class="no_unread">', Lang::$txt['pm_no_unread'], '</div>';
	else
	{
		foreach (Utils::$context['unread_pms'] as $id_pm => $pm_details)
			echo '
			<div class="unread_notify">
				<div class="unread_notify_image">
					', !empty($pm_details['member']) ? $pm_details['member']['avatar']['image'] : '', '
				</div>
				<div class="details">
					<div class="subject">', $pm_details['pm_link'], '</div>
					<div class="sender">
						', $pm_details['replied_to_you'] ? '<span class="main_icons replied centericon" style="margin-right: 4px" title="' . Lang::$txt['pm_you_were_replied_to'] . '"></span>' : '<span class="main_icons im_off centericon" style="margin-right: 4px" title="' . Lang::$txt['pm_was_sent_to_you'] . '"></span>',
						!empty($pm_details['member']) ? $pm_details['member']['link'] : $pm_details['member_from'], ' - ', $pm_details['time'], '
					</div>
				</div>
			</div>';
	}

	echo '
		</div><!-- #pm_unread -->';
}

/**
 * Shows a particular folder (eg inbox or outbox), all the PMs in it, etc.
 */
function template_folder()
{
	// The every helpful javascript!
	echo '
		<script>
			var allLabels = {};
			var currentLabels = {};
			function loadLabelChoices()
			{
				var listing = document.forms.pmFolder.elements;
				var theSelect = document.forms.pmFolder.pm_action;
				var add, remove, toAdd = {length: 0}, toRemove = {length: 0};

				if (theSelect.childNodes.length == 0)
					return;

				// This is done this way for internationalization reasons.
				if (!(\'-1\' in allLabels))
				{
					for (var o = 0; o < theSelect.options.length; o++)
						if (theSelect.options[o].value.substr(0, 4) == "rem_")
							allLabels[theSelect.options[o].value.substr(4)] = theSelect.options[o].text;
				}

				for (var i = 0; i < listing.length; i++)
				{
					if (listing[i].name != "pms[]" || !listing[i].checked)
						continue;

					var alreadyThere = [], x;
					for (x in currentLabels[listing[i].value])
					{
						if (!(x in toRemove))
						{
							toRemove[x] = allLabels[x];
							toRemove.length++;
						}
						alreadyThere[x] = allLabels[x];
					}

					for (x in allLabels)
					{
						if (!(x in alreadyThere))
						{
							toAdd[x] = allLabels[x];
							toAdd.length++;
						}
					}
				}

				while (theSelect.options.length > 2)
					theSelect.options[2] = null;

				if (toAdd.length != 0)
				{
					theSelect.options[theSelect.options.length] = new Option("', Lang::$txt['pm_msg_label_apply'], '", "");
					setInnerHTML(theSelect.options[theSelect.options.length - 1], "', Lang::$txt['pm_msg_label_apply'], '");
					theSelect.options[theSelect.options.length - 1].disabled = true;

					for (i in toAdd)
					{
						if (i != "length")
							theSelect.options[theSelect.options.length] = new Option(toAdd[i], "add_" + i);
					}
				}

				if (toRemove.length != 0)
				{
					theSelect.options[theSelect.options.length] = new Option("', Lang::$txt['pm_msg_label_remove'], '", "");
					setInnerHTML(theSelect.options[theSelect.options.length - 1], "', Lang::$txt['pm_msg_label_remove'], '");
					theSelect.options[theSelect.options.length - 1].disabled = true;

					for (i in toRemove)
					{
						if (i != "length")
							theSelect.options[theSelect.options.length] = new Option(toRemove[i], "rem_" + i);
					}
				}
			}
		</script>';

	echo '
		<form class="flow_hidden" action="', Config::$scripturl, '?action=pm;sa=pmactions;', Utils::$context['display_mode'] == 2 ? 'conversation;' : '', 'f=', Utils::$context['folder'], ';start=', Utils::$context['start'], Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '', '" method="post" accept-charset="', Utils::$context['character_set'], '" name="pmFolder" id="pmFolder">';

	// If we are not in single display mode show the subjects on the top!
	if (Utils::$context['display_mode'] != 1)
	{
		template_subject_list();

		echo '
			<div class="clear_right"><br></div>';
	}

	// Got some messages to display?
	if (Utils::$context['get_pmessage']('message', true))
	{
		// Show a few buttons if we are in conversation mode and outputting the first message.
		if (Utils::$context['display_mode'] == 2)
		{
			// This bit uses info set in template_subject_list, so it's wrapped
			// in an if just in case a mod or custom theme breaks it.
			if (!empty(Utils::$context['current_pm_subject']))
			{
				echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<span>', Lang::$txt['conversation'], '</span>
				</h3>
			</div>
			<div class="roundframe">
				<div class="display_title">', Utils::$context['current_pm_subject'], '</div>
				<p>', Lang::$txt['started_by'], ' ', Utils::$context['current_pm_author'], ', ', Utils::$context['current_pm_time'], '</p>';
			}
			else
			{
				echo '
			<div class="roundframe">';
			}

			// Show the conversation buttons.
			template_button_strip(Utils::$context['conversation_buttons'], 'right');

			echo '
			</div>';
		}

		while ($message = Utils::$context['get_pmessage']('message'))
			template_single_pm($message);

		if (empty(Utils::$context['display_mode']))
			echo '
			<div class="pagesection">
				<div class="pagelinks">', Utils::$context['page_index'], '</div>
				<div class="floatright">
					<input type="submit" name="del_selected" value="', Lang::$txt['quickmod_delete_selected'], '" onclick="if (!confirm(\'', Lang::$txt['delete_selected_confirm'], '\')) return false;" class="button">
				</div>
			</div>';

		// Show a few buttons if we are in conversation mode and outputting the first message.
		elseif (Utils::$context['display_mode'] == 2 && isset(Utils::$context['conversation_buttons']))
		{
			echo '
			<div class="pagesection">';

			template_button_strip(Utils::$context['conversation_buttons'], 'right');

			echo '
			</div>';
		}

		echo '
			<br>';
	}

	// Individual messages = buttom list!
	if (Utils::$context['display_mode'] == 1)
	{
		template_subject_list();
		echo '<br>';
	}

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>';
}

/**
 * Template for displaying a single personal message.
 *
 * @param array $message An array of information about the message to display.
 */
function template_single_pm($message)
{
	echo '
	<div class="windowbg" id="msg', $message['id'],'">
		<div class="post_wrapper">
			<div class="poster">';

	// Are there any custom fields above the member name?
	if (!empty($message['custom_fields']['above_member']))
	{
		echo '
				<div class="custom_fields_above_member">
					<ul class="nolist">';

		foreach ($message['custom_fields']['above_member'] as $custom)
			echo '
						<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
				<h4>';

	// Show online and offline buttons?
	if (!empty(Config::$modSettings['onlineEnable']) && !$message['member']['is_guest'])
		echo '
					<span class="' . ($message['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $message['member']['online']['text'] . '"></span>';

	// Custom fields BEFORE the username?
	if (!empty($message['custom_fields']['before_member']))
		foreach ($message['custom_fields']['before_member'] as $custom)
			echo '
					<span class="custom ', $custom['col_name'], '">', $custom['value'], '</span>';

	// Show a link to the member's profile.
	echo '
		', $message['member']['link'];

	// Custom fields AFTER the username?
	if (!empty($message['custom_fields']['after_member']))
		foreach ($message['custom_fields']['after_member'] as $custom)
			echo '
					<span class="custom ', $custom['col_name'], '">', $custom['value'], '</span>';

	echo '
				</h4>';

	echo '
				<ul class="user_info">';

	// Show the member's custom title, if they have one.
	if (isset($message['member']['title']) && $message['member']['title'] != '')
		echo '
					<li class="title">', $message['member']['title'], '</li>';

	// Show the member's primary group (like 'Administrator') if they have one.
	if (isset($message['member']['group']) && $message['member']['group'] != '')
		echo '
					<li class="membergroup">', $message['member']['group'], '</li>';

	// Show the user's avatar.
	if (!empty(Config::$modSettings['show_user_images']) && empty(Theme::$current->options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
		echo '
					<li class="avatar">
						<a href="', Config::$scripturl, '?action=profile;u=', $message['member']['id'], '">', $message['member']['avatar']['image'], '</a>
					</li>';

	// Are there any custom fields below the avatar?
	if (!empty($message['custom_fields']['below_avatar']))
		foreach ($message['custom_fields']['below_avatar'] as $custom)
			echo '
					<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

	// Don't show these things for guests.
	if (!$message['member']['is_guest'])
	{
		// Show the post group icons
		echo '
					<li class="icons">', $message['member']['group_icons'], '</li>';

		// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
		if ((empty(Config::$modSettings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != '')
			echo '
					<li class="postgroup">', $message['member']['post_group'], '</li>';

		// Show how many posts they have made.
		if (!isset(Utils::$context['disabled_fields']['posts']))
			echo '
					<li class="postcount">', Lang::$txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

		// Show their personal text?
		if (!empty(Config::$modSettings['show_blurb']) && $message['member']['blurb'] != '')
			echo '
					<li class="blurb">', $message['member']['blurb'], '</li>';

		// Any custom fields to show as icons?
		if (!empty($message['custom_fields']['icons']))
		{
			echo '
					<li class="im_icons">
						<ol>';

			foreach ($message['custom_fields']['icons'] as $custom)
				echo '
							<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

			echo '
						</ol>
					</li>';
		}

		// Show the IP to this user for this post - because you can moderate?
		if (!empty(Utils::$context['can_moderate_forum']) && !empty($message['member']['ip']))
			echo '
					<li class="poster_ip">
						<a href="', Config::$scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '">', $message['member']['ip'], '</a> <a href="', Config::$scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>
					</li>';

		// Or, should we show it because this is you?
		elseif ($message['can_see_ip'])
			echo '
					<li class="poster_ip">
						<a href="', Config::$scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', $message['member']['ip'], '</a>
					</li>';

		// Okay, you are logged in, then we can show something about why IPs are logged...
		else
			echo '
					<li class="poster_ip">
						<a href="', Config::$scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', Lang::$txt['logged'], '</a>
					</li>';

		// Show the profile, website, email address, and personal message buttons.
		if ($message['member']['show_profile_buttons'])
		{
			echo '
					<li class="profile">
						<ol class="profile_icons">';

			// Show the profile button
			if ($message['member']['can_view_profile'])
				echo '
							<li><a href="', $message['member']['href'], '" title="' . Lang::$txt['view_profile'] . '">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons members"></span>' : Lang::$txt['view_profile']), '</a></li>';

			// Don't show an icon if they haven't specified a website.
			if ($message['member']['website']['url'] != '' && !isset(Utils::$context['disabled_fields']['website']))
				echo '
							<li><a href="', $message['member']['website']['url'], '" title="' . $message['member']['website']['title'] . '" target="_blank" rel="noopener">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons www centericon" title="' . $message['member']['website']['title'] . '"></span>' : Lang::$txt['www']), '</a></li>';

			// Don't show the email address if they want it hidden.
			if ($message['member']['show_email'])
				echo '
							<li><a href="mailto:', $message['member']['email'], '" rel="nofollow">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons mail centericon" title="' . Lang::$txt['email'] . '"></span>' : Lang::$txt['email']), '</a></li>';

			// Since we know this person isn't a guest, you *can* message them.
			if (Utils::$context['can_send_pm'] && $message['member']['id'] != 0)
				echo '
							<li><a href="', Config::$scripturl, '?action=pm;sa=send;u=', $message['member']['id'], '" title="', $message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline'], '">', Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons im_' . ($message['member']['online']['is_online'] ? 'on' : 'off') . ' centericon" title="' . ($message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline']) . '"></span> ' : ($message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline']), '</a></li>';

			echo '
						</ol>
					</li>';
		}

		// Any custom fields for standard placement?
		if (!empty($message['custom_fields']['standard']))
			foreach ($message['custom_fields']['standard'] as $custom)
				echo '
					<li class="custom ', $custom['col_name'], '">', $custom['title'], ': ', $custom['value'], '</li>';

		// Are we showing the warning status?
		if ($message['member']['can_see_warning'])
			echo '
					<li class="warning">', Utils::$context['can_issue_warning'] ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '', '<span class="main_icons warning_', $message['member']['warning_status'], '"></span>', Utils::$context['can_issue_warning'] ? '</a>' : '', '<span class="warn_', $message['member']['warning_status'], '">', Lang::$txt['warn_' . $message['member']['warning_status']], '</span></li>';

		// Are there any custom fields to show at the bottom of the poster info?
		if (!empty($message['custom_fields']['bottom_poster']))
			foreach ($message['custom_fields']['bottom_poster'] as $custom)
				echo '
					<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';
	}

	// Done with the information about the poster... on to the post itself.
	echo '
				</ul>
			</div><!-- .poster -->
			<div class="postarea">
				<div class="keyinfo">
					<div id="subject_', $message['id'], '" class="subject_title">
						<h5>', $message['subject'], '</h5>
					</div>
					<div class="postinfo">';

	// Show who the message was sent to.
	echo '
						<span class="smalltext"><strong> ', Lang::$txt['sent_to'], ':</strong> ';

	// People it was sent directly to....
	if (!empty($message['recipients']['to']))
		echo implode(', ', $message['recipients']['to']);

	// Otherwise, we're just going to say "some people"...
	elseif (Utils::$context['folder'] != 'sent')
		echo '(', Lang::$txt['pm_undisclosed_recipients'], ')';

	echo '
							<strong> ', Lang::$txt['on'], ':</strong> ', $message['time'], '
						</span>';

	// If we're in the sent items, show who it was sent to besides the "To:" people.
	if (!empty($message['recipients']['bcc']))
		echo '
						<span class="smalltext"><strong> ', Lang::$txt['pm_bcc'], ':</strong> ', implode(', ', $message['recipients']['bcc']), ' </span>';

	if (!empty($message['is_replied_to']))
		echo '
						<span class="smalltext">', Utils::$context['folder'] == 'sent' ? Lang::$txt['pm_sent_is_replied_to'] : Lang::$txt['pm_is_replied_to'], ' </span>';

	echo '
					</div><!-- .postinfo -->
				</div><!-- .keyinfo -->
				<div class="post">
					<div class="inner" id="msg_', $message['id'], '"', '>
						', $message['body'], '
					</div>
				</div><!-- .post -->
				<div class="under_message">';

	// Add an extra line at the bottom if we have labels enabled.
	if (Utils::$context['folder'] != 'sent' && !empty(Utils::$context['currently_using_labels']) && Utils::$context['display_mode'] != 2)
	{
		echo '
				<div class="labels floatleft">';

		// Add the label drop down box.
		if (!empty(Utils::$context['currently_using_labels']))
		{
			echo '
					<select name="pm_actions[', $message['id'], ']" onchange="if (this.options[this.selectedIndex].value) form.submit();">
						<option value="">', Lang::$txt['pm_msg_label_title'], ':</option>
						<option value="" disabled>---------------</option>';

			// Are there any labels which can be added to this?
			if (!$message['fully_labeled'])
			{
				echo '
						<option value="" disabled>', Lang::$txt['pm_msg_label_apply'], ':</option>';

				foreach (Utils::$context['labels'] as $label)
					if (!isset($message['labels'][$label['id']]))
						echo '
						<option value="', $label['id'], '">', $label['name'], '</option>';
			}

			// ... and are there any that can be removed?
			if (!empty($message['labels']) && (count($message['labels']) > 1 || !isset($message['labels'][-1])))
			{
				echo '
						<option value="" disabled>', Lang::$txt['pm_msg_label_remove'], ':</option>';

				foreach ($message['labels'] as $label)
					echo '
						<option value="', $label['id'], '">&nbsp;', $label['name'], '</option>';
			}
			echo '
					</select>
					<noscript>
						<input type="submit" value="', Lang::$txt['pm_apply'], '" class="button">
					</noscript>';
		}
		echo '
				</div><!-- .labels -->';
	}

	// Message options
	template_quickbuttons($message['quickbuttons'], 'pm');

	echo '
				</div><!-- .under_message -->
			</div><!-- .postarea -->
			<div class="moderatorbar">';

	// Are there any custom profile fields for above the signature?
	if (!empty($message['custom_fields']['above_signature']))
	{
		echo '
				<div class="custom_fields_above_signature">
					<ul class="nolist">';

		foreach ($message['custom_fields']['above_signature'] as $custom)
			echo '
						<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

		echo '
					</ul>
				</div>';
	}

	// Show the member's signature?
	if (!empty($message['member']['signature']) && empty(Theme::$current->options['show_no_signatures']) && Utils::$context['signature_enabled'])
		echo '
				<div class="signature">
					', $message['member']['signature'], '
				</div>';

	// Are there any custom profile fields for below the signature?
	if (!empty($message['custom_fields']['below_signature']))
	{
		echo '
				<div class="custom_fields_below_signature">
					<ul class="nolist">';

		foreach ($message['custom_fields']['below_signature'] as $custom)
			echo '
						<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
			</div><!-- .moderatorbar -->
		</div><!-- .post_wrapper -->
	</div><!-- .windowbg -->';
}

/**
 * Just list all the personal message subjects - to make templates easier.
 */
function template_subject_list()
{
	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			', Utils::$context['folder'] == 'sent' ? Lang::$txt['sent_items'] : Utils::$context['current_label'], '
		</h3>
	</div>
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th class="centercol table_icon pm_icon">
					<a href="', Config::$scripturl, '?action=pm;view;f=', Utils::$context['folder'], ';start=', Utils::$context['start'], ';sort=', Utils::$context['sort_by'], (Utils::$context['sort_direction'] == 'up' ? '' : ';desc'), (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : ''), '"> <span class="main_icons switch" title="', Lang::$txt['pm_change_view'], '"></span></a>
				</th>
				<th class="lefttext quarter_table pm_time">
					<a href="', Config::$scripturl, '?action=pm;f=', Utils::$context['folder'], ';start=', Utils::$context['start'], ';sort=date', Utils::$context['sort_by'] == 'date' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '', '">', Lang::$txt['date'], Utils::$context['sort_by'] == 'date' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a>
				</th>
				<th class="lefttext half_table pm_subject">
					<a href="', Config::$scripturl, '?action=pm;f=', Utils::$context['folder'], ';start=', Utils::$context['start'], ';sort=subject', Utils::$context['sort_by'] == 'subject' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '', '">', Lang::$txt['subject'], Utils::$context['sort_by'] == 'subject' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a>
				</th>
				<th class="lefttext pm_from_to">
					<a href="', Config::$scripturl, '?action=pm;f=', Utils::$context['folder'], ';start=', Utils::$context['start'], ';sort=name', Utils::$context['sort_by'] == 'name' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '', '">', (Utils::$context['from_or_to'] == 'from' ? Lang::$txt['from'] : Lang::$txt['pm_to']), Utils::$context['sort_by'] == 'name' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a>
				</th>
				<th class="centercol table_icon pm_moderation">
					<input type="checkbox" onclick="invertAll(this, this.form);">
				</th>
			</tr>
		</thead>
		<tbody>';

	if (!Utils::$context['show_delete'])
		echo '
			<tr class="windowbg">
				<td colspan="5">', Lang::$txt['pm_alert_none'], '</td>
			</tr>';

	while ($message = Utils::$context['get_pmessage']('subject'))
	{
		echo '
			<tr class="windowbg', $message['is_unread'] ? ' unread_pm' : '', '">
				<td class="table_icon pm_icon">
					<script>
						currentLabels[', $message['id'], '] = {';

		if (!empty($message['labels']))
		{
			$first = true;
			foreach ($message['labels'] as $label)
			{
				echo $first ? '' : ',', '
				"', $label['id'], '": "', $label['name'], '"';
				$first = false;
			}
		}

		echo '
						};
					</script>
					', $message['is_replied_to'] ? '<span class="main_icons replied" title="' . Lang::$txt['pm_replied'] . '"></span>' : '<span class="main_icons im_off" title="' . Lang::$txt['pm_read'] . '"></span>', '
				</td>
				<td class="pm_time">', $message['time'], '</td>
				<td class="pm_subject">
					', (Utils::$context['display_mode'] != 0 && Utils::$context['current_pm'] == $message['id'] ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="*">' : ''), '<a href="', (Utils::$context['display_mode'] == 0 || Utils::$context['current_pm'] == $message['id'] ? '' : (Config::$scripturl . '?action=pm;pmid=' . $message['id'] . ';kstart;f=' . Utils::$context['folder'] . ';start=' . Utils::$context['start'] . ';sort=' . Utils::$context['sort_by'] . (Utils::$context['sort_direction'] == 'up' ? ';' : ';desc') . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : ''))), '#msg', $message['id'], '">', $message['subject'], $message['is_unread'] ? '&nbsp;<span class="new_posts">' . Lang::$txt['new'] . '</span>' : '', '</a>
				</td>
				<td class="pm_from_to">
					', (Utils::$context['from_or_to'] == 'from' ? $message['member']['link'] : (empty($message['recipients']['to']) ? '' : implode(', ', $message['recipients']['to']))), '
				</td>
				<td class="centercol table_icon pm_moderation">
					<input type="checkbox" name="pms[]" id="deletelisting', $message['id'], '" value="', $message['id'], '"', $message['is_selected'] ? ' checked' : '', ' onclick="if (document.getElementById(\'deletedisplay', $message['id'], '\')) document.getElementById(\'deletedisplay', $message['id'], '\').checked = this.checked;">
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>
	<div class="pagesection">
		<div class="pagelinks">', Utils::$context['page_index'], '</div>
		<div class="floatright">&nbsp;';

	if (Utils::$context['show_delete'])
	{
		if (!empty(Utils::$context['currently_using_labels']) && Utils::$context['folder'] != 'sent')
		{
			echo '
			<select name="pm_action" onchange="if (this.options[this.selectedIndex].value) this.form.submit();" onfocus="loadLabelChoices();">
				<option value="">', Lang::$txt['pm_sel_label_title'], ':</option>
				<option value="" disabled>---------------</option>
				<option value="" disabled>', Lang::$txt['pm_msg_label_apply'], ':</option>';

			foreach (Utils::$context['labels'] as $label)
			{
				if ($label['id'] != Utils::$context['current_label_id'])
					echo '
				<option value="add_', $label['id'], '">&nbsp;', $label['name'], '</option>';
			}

			echo '
				<option value="" disabled>', Lang::$txt['pm_msg_label_remove'], ':</option>';

			foreach (Utils::$context['labels'] as $label)
				echo '
				<option value="rem_', $label['id'], '">&nbsp;', $label['name'], '</option>';

			echo '
			</select>
			<noscript>
				<input type="submit" value="', Lang::$txt['pm_apply'], '" class="button">
			</noscript>';
		}

		echo '
			<input type="submit" name="del_selected" value="', Lang::$txt['quickmod_delete_selected'], '" onclick="if (!confirm(\'', Lang::$txt['delete_selected_confirm'], '\')) return false;" class="button">';
	}

	echo '
		</div><!-- .floatright -->
	</div><!-- .pagesection -->';
}

/**
 * The form for the PM search feature
 */
function template_search()
{
	if (!empty(Utils::$context['search_errors']))
		echo '
		<div class="errorbox">
			', implode('<br>', Utils::$context['search_errors']['messages']), '
		</div>';

	echo '
	<form action="', Config::$scripturl, '?action=pm;sa=search2" method="post" accept-charset="', Utils::$context['character_set'], '" name="searchform" id="searchform">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_search_title'], '</h3>
		</div>
		<div id="advanced_search" class="roundframe">
			<input type="hidden" name="advanced" value="1">
			<dl id="search_options" class="settings">
				<dt>
					<strong><label for="searchfor">', Lang::$txt['pm_search_text'], ':</label></strong>
				</dt>
				<dd>
					<input type="search" name="search"', !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '', ' size="40">
					<script>
						createEventListener(window);
						window.addEventListener("load", initSearch, false);
					</script>
				</dd>
				<dt>
					<label for="searchtype">', Lang::$txt['search_match'], ':</label>
				</dt>
				<dd>
					<select name="searchtype">
						<option value="1"', empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '', '>', Lang::$txt['pm_search_match_all'], '</option>
						<option value="2"', !empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '', '>', Lang::$txt['pm_search_match_any'], '</option>
					</select>
				</dd>
				<dt>
					<label for="userspec">', Lang::$txt['pm_search_user'], ':</label>
				</dt>
				<dd>
					<input type="text" name="userspec" value="', empty(Utils::$context['search_params']['userspec']) ? '*' : Utils::$context['search_params']['userspec'], '" size="40">
				</dd>
				<dt>
					<label for="sort">', Lang::$txt['pm_search_order'], ':</label>
				</dt>
				<dd>
					<select name="sort">
						<option value="relevance|desc">', Lang::$txt['pm_search_orderby_relevant_first'], '</option>
						<option value="id_pm|desc">', Lang::$txt['pm_search_orderby_recent_first'], '</option>
						<option value="id_pm|asc">', Lang::$txt['pm_search_orderby_old_first'], '</option>
					</select>
				</dd>
				<dt class="options">
					', Lang::$txt['pm_search_options'], ':
				</dt>
				<dd class="options">
					<label for="show_complete">
						<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty(Utils::$context['search_params']['show_complete']) ? ' checked' : '', '> ', Lang::$txt['pm_search_show_complete'], '
					</label><br>
					<label for="subject_only">
						<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty(Utils::$context['search_params']['subject_only']) ? ' checked' : '', '> ', Lang::$txt['pm_search_subject_only'], '
					</label>
				</dd>
				<dt class="between">
					', Lang::$txt['pm_search_post_age'], ':
				</dt>
				<dd>
					', Lang::$txt['pm_search_between'], '
					<input type="number" name="minage" value="', empty(Utils::$context['search_params']['minage']) ? '0' : Utils::$context['search_params']['minage'], '" size="5" maxlength="5" min="0" max="9999">
					', Lang::$txt['pm_search_between_and'], '
					<input type="number" name="maxage" value="', empty(Utils::$context['search_params']['maxage']) ? '9999' : Utils::$context['search_params']['maxage'], '" size="5" maxlength="5" min="0" max="9999">
					', Lang::$txt['pm_search_between_days'], '
				</dd>
			</dl>';

	if (!Utils::$context['currently_using_labels'])
		echo '
				<input type="submit" name="pm_search" value="', Lang::$txt['pm_search_go'], '" class="button floatright">';

	echo '
		</div><!-- .roundframe -->';

	// Do we have some labels setup? If so offer to search by them!
	if (Utils::$context['currently_using_labels'])
	{
		echo '
		<fieldset class="labels">
			<div class="roundframe alt">
				<div class="title_bar">
					<h3 class="titlebg">
						<span id="advanced_panel_toggle" class="toggle_up floatright" style="display: none;"></span><a href="#" id="advanced_panel_link">', Lang::$txt['pm_search_choose_label'], '</a>
					</h3>
				</div>
				<div id="advanced_panel_div">
					<ul id="search_labels">';

		foreach (Utils::$context['search_labels'] as $label)
			echo '
						<li>
							<label for="searchlabel_', $label['id'], '"><input type="checkbox" id="searchlabel_', $label['id'], '" name="searchlabel[', $label['id'], ']" value="', $label['id'], '"', $label['checked'] ? ' checked' : '', '>
							', $label['name'], '</label>
						</li>';

		echo '
					</ul>
				</div>
				<br class="clear">
				<div class="padding">
					<input type="checkbox" name="all" id="check_all" value=""', Utils::$context['check_all'] ? ' checked' : '', ' onclick="invertAll(this, this.form, \'searchlabel\');">
					<label for="check_all"><em>', Lang::$txt['check_all'], '</em></label>
					<input type="submit" name="pm_search" value="', Lang::$txt['pm_search_go'], '" class="button floatright">
				</div class="padding">
			</div><!-- .roundframe -->
		</fieldset>';

		// Some javascript for the advanced toggling
		echo '
		<script>
			var oAdvancedPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'advanced_panel_div\'
				],
				aSwapImages: [
					{
						sId: \'advanced_panel_toggle\',
						altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide']), ',
						altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'advanced_panel_link\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['pm_search_choose_label']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['pm_search_choose_label']), '
					}
				]
			});
		</script>';
	}

	echo '
	</form>';
}

/**
 * Displays results from a PM search
 */
function template_search_results()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_search_results'], '</h3>
		</div>
		<div class="roundframe noup">
			', sprintf(Lang::$txt['pm_search_results_info'], Utils::$context['num_results'], Lang::sentenceList(Utils::$context['search_in'])), '
		</div>
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';

	// Complete results?
	if (empty(Utils::$context['search_params']['show_complete']) && !empty(Utils::$context['personal_messages']))
		echo '
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext quarter_table">', Lang::$txt['date'], '</th>
					<th class="lefttext half_table">', Lang::$txt['subject'], '</th>
					<th class="lefttext quarter_table">', Lang::$txt['from'], '</th>
				</tr>
			</thead>
			<tbody>';

	// Print each message out...
	foreach (Utils::$context['personal_messages'] as $message)
	{
		// Are we showing it all?
		if (!empty(Utils::$context['search_params']['show_complete']))
			template_single_pm($message);

		// Otherwise just a simple list!
		else
			echo '
				<tr class="windowbg">
					<td>', $message['time'], '</td>
					<td>', $message['link'], '</td>
					<td>', $message['member']['link'], '</td>
				</tr>';
	}

	// Finish off the page...
	if (empty(Utils::$context['search_params']['show_complete']) && !empty(Utils::$context['personal_messages']))
		echo '
			</tbody>
		</table>';

	// No results?
	if (empty(Utils::$context['personal_messages']))
		echo '
		<div class="windowbg">
			<p class="centertext">', Lang::$txt['pm_search_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';

}

/**
 * The form for sending a new PM
 */
function template_send()
{
	// Show which messages were sent successfully and which failed.
	if (!empty(Utils::$context['send_log']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_send_report'], '</h3>
		</div>
		<div class="windowbg noup">';

		if (!empty(Utils::$context['send_log']['sent']))
			foreach (Utils::$context['send_log']['sent'] as $log_entry)
				echo '
			<span class="error">', $log_entry, '</span><br>';

		if (!empty(Utils::$context['send_log']['failed']))
			foreach (Utils::$context['send_log']['failed'] as $log_entry)
				echo '
			<span class="error">', $log_entry, '</span><br>';

		echo '
		</div>
		<br>';
	}

	// Show the preview of the personal message.
	echo '
		<div id="preview_section"', isset(Utils::$context['preview_message']) ? '' : ' class="hidden"', '>
			<div class="cat_bar">
				<h3 class="catbg">
					<span id="preview_subject">', empty(Utils::$context['preview_subject']) ? '' : Utils::$context['preview_subject'], '</span>
				</h3>
			</div>
			<div class="windowbg noup">
				<div class="post" id="preview_body">
					', empty(Utils::$context['preview_message']) ? '<br>' : Utils::$context['preview_message'], '
				</div>
			</div>
			<br class="clear">
		</div>';

	// Main message editing box.
	echo '
		<form action="', Config::$scripturl, '?action=pm;sa=send2" method="post" accept-charset="', Utils::$context['character_set'], '" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons inbox icon" title="', Lang::$txt['new_message'], '"></span> ', Lang::$txt['new_message'], '
				</h3>
			</div>
			<div class="roundframe noup">';

	// If there were errors for sending the PM, show them.
	echo '
				<div class="', empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? 'noticebox' : 'errorbox', '', empty(Utils::$context['post_error']['messages']) ? ' hidden' : '', '" id="errors">
					<dl>
						<dt>
							<strong id="error_serious">', Lang::$txt['error_while_submitting'], '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty(Utils::$context['post_error']['messages']) ? '' : implode('<br>', Utils::$context['post_error']['messages']), '
						</dd>
					</dl>
				</div>';

	if (!empty(Config::$modSettings['drafts_pm_enabled']))
		echo '
				<div id="draft_section" class="infobox"', isset(Utils::$context['draft_saved']) ? '' : ' style="display: none;"', '>',
					sprintf(Lang::$txt['draft_pm_saved'], Config::$scripturl . '?action=pm;sa=showpmdrafts'), '
					', (!empty(Config::$modSettings['drafts_keep_days']) ? ' <strong>' . sprintf(Lang::$txt['draft_save_warning'], Config::$modSettings['drafts_keep_days']) . '</strong>' : ''), '
				</div>';

	echo '
				<dl id="post_header">';

	// To and bcc. Include a button to search for members.
	echo '
					<dt>
						<span', (isset(Utils::$context['post_error']['no_to']) || isset(Utils::$context['post_error']['bad_to']) ? ' class="error"' : ''), ' id="caption_to">', Lang::$txt['pm_to'], ':</span>
					</dt>';

	// Autosuggest will be added by the JavaScript later on.
	echo '
					<dd id="pm_to" class="clear_right">
						<input type="text" name="to" id="to_control" value="', Utils::$context['to_value'], '" tabindex="', Utils::$context['tabindex']++, '" size="20">';

	// A link to add BCC, only visible with JavaScript enabled.
	echo '
						<span class="smalltext" id="bcc_link_container" style="display: none;"></span>';

	// A div that'll contain the items found by the autosuggest.
	echo '
						<div id="to_item_list_container"></div>';

	echo '
					</dd>';

	// This BCC row will be hidden by default if JavaScript is enabled.
	echo '
					<dt  class="clear_left" id="bcc_div">
						<span', (isset(Utils::$context['post_error']['no_to']) || isset(Utils::$context['post_error']['bad_bcc']) ? ' class="error"' : ''), ' id="caption_bbc">', Lang::$txt['pm_bcc'], ':</span>
					</dt>
					<dd id="bcc_div2">
						<input type="text" name="bcc" id="bcc_control" value="', Utils::$context['bcc_value'], '" tabindex="', Utils::$context['tabindex']++, '" size="20">
						<div id="bcc_item_list_container"></div>
					</dd>';

	// The subject of the PM.
	echo '
					<dt class="clear_left">
						<span', (isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : ''), ' id="caption_subject">', Lang::$txt['subject'], ':</span>
					</dt>
					<dd id="pm_subject">
						<input type="text" name="subject" value="', Utils::$context['subject'], '" tabindex="', Utils::$context['tabindex']++, '" size="80" maxlength="80"', isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : '', '>
					</dd>
				</dl>';

	// Show BBC buttons, smileys and textbox.
	echo '
				', template_control_richedit(Utils::$context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// If the admin enabled the pm drafts feature, show a draft selection box
	if (!empty(Utils::$context['drafts_save']) && !empty(Utils::$context['drafts']) && !empty(Theme::$current->options['drafts_show_saved_enabled']))
	{
		echo '
				<div id="post_draft_options_header" class="title_bar">
					<h4 class="titlebg">
						<span id="postDraftExpand" class="toggle_up floatright" style="display: none;"></span> <strong><a href="#" id="postDraftExpandLink">', Lang::$txt['drafts_show'], '</a></strong>
					</h4>
				</div>
				<div id="post_draft_options">
					<dl class="settings">
						<dt><strong>', Lang::$txt['subject'], '</strong></dt>
						<dd><strong>', Lang::$txt['draft_saved_on'], '</strong></dd>';

		foreach (Utils::$context['drafts'] as $draft)
			echo '
						<dt>', $draft['link'], '</dt>
						<dd>', $draft['poster_time'], '</dd>';
		echo '
					</dl>
				</div>';
	}

	// Require an image to be typed to save spamming?
	if (Utils::$context['require_verification'])
		echo '
				<div class="post_verification">
					<strong>', Lang::$txt['pm_visual_verification_label'], ':</strong>
					', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
				</div>';

	// Send, Preview, spellcheck buttons.
	echo '
				<span id="post_confirm_buttons">
					', template_control_richedit_buttons(Utils::$context['post_box_name']), '
				</span>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">
				<input type="hidden" name="replied_to" value="', !empty(Utils::$context['quoted_message']['id']) ? Utils::$context['quoted_message']['id'] : 0, '">
				<input type="hidden" name="pm_head" value="', !empty(Utils::$context['quoted_message']['pm_head']) ? Utils::$context['quoted_message']['pm_head'] : 0, '">
				<input type="hidden" name="f" value="', isset(Utils::$context['folder']) ? Utils::$context['folder'] : '', '">
				<input type="hidden" name="l" value="', isset(Utils::$context['current_label_id']) ? Utils::$context['current_label_id'] : -1, '">
				<br class="clear_right">
			</div><!-- .roundframe -->
		</form>';

	echo '
		<script>';

	// The functions used to preview a personal message without loading a new page.
	echo '
			var txt_preview_title = "', Lang::$txt['preview_title'], '";
			var txt_preview_fetch = "', Lang::$txt['preview_fetch'], '";
			function previewPost()
			{
				if (window.XMLHttpRequest)
				{
					// Opera didn\'t support setRequestHeader() before 8.01.
					// @todo Remove support for old browsers
					if (\'opera\' in window)
					{
						var test = new XMLHttpRequest();
						if (!(\'setRequestHeader\' in test))
							return submitThisOnce(document.forms.postmodify);
					}
					// @todo Currently not sending poll options and option checkboxes.
					var x = new Array();
					var textFields = [\'subject\', ', Utils::JavaScriptEscape(Utils::$context['post_box_name']), ', \'to\', \'bcc\'];
					var numericFields = [\'recipient_to[]\', \'recipient_bcc[]\'];
					var checkboxFields = [];

					for (var i = 0, n = textFields.length; i < n; i++)
						if (textFields[i] in document.forms.postmodify)
						{
							// Handle the WYSIWYG editor.
							if (textFields[i] == ', Utils::JavaScriptEscape(Utils::$context['post_box_name']), ' && ', Utils::JavaScriptEscape('oEditorHandle_' . Utils::$context['post_box_name']), ' in window && oEditorHandle_', Utils::$context['post_box_name'], '.bRichTextEnabled)
								x[x.length] = \'message_mode=1&\' + textFields[i] + \'=\' + oEditorHandle_', Utils::$context['post_box_name'], '.getText(false).php_to8bit().php_urlencode();
							else
								x[x.length] = textFields[i] + \'=\' + document.forms.postmodify[textFields[i]].value.php_to8bit().php_urlencode();
						}
					for (var i = 0, n = numericFields.length; i < n; i++)
						if (numericFields[i] in document.forms.postmodify && \'value\' in document.forms.postmodify[numericFields[i]])
							x[x.length] = numericFields[i] + \'=\' + parseInt(document.forms.postmodify.elements[numericFields[i]].value);
					for (var i = 0, n = checkboxFields.length; i < n; i++)
						if (checkboxFields[i] in document.forms.postmodify && document.forms.postmodify.elements[checkboxFields[i]].checked)
							x[x.length] = checkboxFields[i] + \'=\' + document.forms.postmodify.elements[checkboxFields[i]].value;

					sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=pm;sa=send2;preview;xml\', x.join(\'&\'), onDocSent);

					document.getElementById(\'preview_section\').style.display = \'\';
					setInnerHTML(document.getElementById(\'preview_subject\'), txt_preview_title);
					setInnerHTML(document.getElementById(\'preview_body\'), txt_preview_fetch);

					return false;
				}
				else
					return submitThisOnce(document.forms.postmodify);
			}
			function onDocSent(XMLDoc)
			{
				if (!XMLDoc)
				{
					document.forms.postmodify.preview.onclick = new function ()
					{
						return true;
					}
					document.forms.postmodify.preview.click();
				}

				// Show the preview section.
				var preview = XMLDoc.getElementsByTagName(\'smf\')[0].getElementsByTagName(\'preview\')[0];
				setInnerHTML(document.getElementById(\'preview_subject\'), preview.getElementsByTagName(\'subject\')[0].firstChild.nodeValue);

				var bodyText = \'\';
				for (var i = 0, n = preview.getElementsByTagName(\'body\')[0].childNodes.length; i < n; i++)
					bodyText += preview.getElementsByTagName(\'body\')[0].childNodes[i].nodeValue;

				setInnerHTML(document.getElementById(\'preview_body\'), bodyText);
				document.getElementById(\'preview_body\').className = \'post\';

				// Show a list of errors (if any).
				var errors = XMLDoc.getElementsByTagName(\'smf\')[0].getElementsByTagName(\'errors\')[0];
				var errorList = new Array();
				for (var i = 0, numErrors = errors.getElementsByTagName(\'error\').length; i < numErrors; i++)
					errorList[errorList.length] = errors.getElementsByTagName(\'error\')[i].firstChild.nodeValue;
				document.getElementById(\'errors\').style.display = numErrors == 0 ? \'none\' : \'\';
				setInnerHTML(document.getElementById(\'error_list\'), numErrors == 0 ? \'\' : errorList.join(\'<br>\'));

				// Adjust the color of captions if the given data is erroneous.
				var captions = errors.getElementsByTagName(\'caption\');
				for (var i = 0, numCaptions = errors.getElementsByTagName(\'caption\').length; i < numCaptions; i++)
					if (document.getElementById(\'caption_\' + captions[i].getAttribute(\'name\')))
						document.getElementById(\'caption_\' + captions[i].getAttribute(\'name\')).className = captions[i].getAttribute(\'class\');

				if (errors.getElementsByTagName(\'post_error\').length == 1)
					document.forms.postmodify.', Utils::$context['post_box_name'], '.style.border = \'1px solid red\';
				else if (document.forms.postmodify.', Utils::$context['post_box_name'], '.style.borderColor == \'red\' || document.forms.postmodify.', Utils::$context['post_box_name'], '.style.borderColor == \'red red red red\')
				{
					if (\'runtimeStyle\' in document.forms.postmodify.', Utils::$context['post_box_name'], ')
						document.forms.postmodify.', Utils::$context['post_box_name'], '.style.borderColor = \'\';
					else
						document.forms.postmodify.', Utils::$context['post_box_name'], '.style.border = null;
				}
				location.hash = \'#\' + \'preview_section\';
			}';

	// Code for showing and hiding drafts
	if (!empty(Utils::$context['drafts']))
		echo '
			var oSwapDraftOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'post_draft_options\',
				],
				aSwapImages: [
					{
						sId: \'postDraftExpand\',
						altExpanded: \'-\',
						altCollapsed: \'+\'
					}
				],
				aSwapLinks: [
					{
						sId: \'postDraftExpandLink\',
						msgExpanded: ', Utils::JavaScriptEscape(Lang::$txt['draft_hide']), ',
						msgCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['drafts_show']), '
					}
				]
			});';

	echo '
		</script>';

	// Show the message you're replying to.
	if (Utils::$context['reply'])
		echo '
		<br><br>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['subject'], ': ', Utils::$context['quoted_message']['subject'], '</h3>
		</div>
		<div class="windowbg">
			<div class="clear">
				<span class="smalltext floatright">', Lang::$txt['on'], ': ', Utils::$context['quoted_message']['time'], '</span>
				<strong>', Lang::$txt['from'], ': ', Utils::$context['quoted_message']['member']['name'], '</strong>
			</div>
			<hr>
			', Utils::$context['quoted_message']['body'], '
		</div>
		<br class="clear">';

	echo '
		<script>
			var oPersonalMessageSend = new smf_PersonalMessageSend({
				sSelf: \'oPersonalMessageSend\',
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
				sToControlId: \'to_control\',
				aToRecipients: [';

	foreach (Utils::$context['recipients']['to'] as $i => $member)
		echo '
					{
						sItemId: ', Utils::JavaScriptEscape($member['id']), ',
						sItemName: ', Utils::JavaScriptEscape($member['name']), '
					}', $i == count(Utils::$context['recipients']['to']) - 1 ? '' : ',';

	echo '
				],
				aBccRecipients: [';

	foreach (Utils::$context['recipients']['bcc'] as $i => $member)
		echo '
					{
						sItemId: ', Utils::JavaScriptEscape($member['id']), ',
						sItemName: ', Utils::JavaScriptEscape($member['name']), '
					}', $i == count(Utils::$context['recipients']['bcc']) - 1 ? '' : ',';

	echo '
				],
				sBccControlId: \'bcc_control\',
				sBccDivId: \'bcc_div\',
				sBccDivId2: \'bcc_div2\',
				sBccLinkId: \'bcc_link\',
				sBccLinkContainerId: \'bcc_link_container\',
				bBccShowByDefault: ', empty(Utils::$context['recipients']['bcc']) && empty(Utils::$context['bcc_value']) ? 'false' : 'true', ',
				sShowBccLinkTemplate: ', Utils::JavaScriptEscape('
					<a href="#" id="bcc_link">' . Lang::$txt['make_bcc'] . '</a> <a href="' . Config::$scripturl . '?action=helpadmin;help=pm_bcc" onclick="return reqOverlayDiv(this.href);">(?)</a>'
				), '
			});';

	echo '
		</script>';
}

/**
 * This template asks the user whether they wish to empty out their folder/messages.
 */
function template_ask_delete()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', (Utils::$context['delete_all'] ? Lang::$txt['delete_message'] : Lang::$txt['delete_all']), '
			</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['delete_all_confirm'], '</p>
			<br>
			<strong><a href="', Config::$scripturl, '?action=pm;sa=removeall2;f=', Utils::$context['folder'], ';', Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '', ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::$txt['yes'], '</a> - <a href="javascript:history.go(-1);">', Lang::$txt['no'], '</a></strong>
		</div>';
}

/**
 * This template asks the user what messages they want to prune.
 */
function template_prune()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_prune'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=pm;sa=prune" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="return confirm(\'', Lang::$txt['pm_prune_warning'], '\');">
				<p>', Lang::$txt['pm_prune_desc1'], ' <input type="text" name="age" size="3" value="14"> ', Lang::$txt['pm_prune_desc2'], '</p>
				<input type="submit" value="', Lang::$txt['delete'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=pm;sa=removeall2" method="post" onsubmit="return confirm(\'', Lang::$txt['pm_remove_all_warning'], '\');">
				<p>', Lang::$txt['pm_remove_all'], '</p>
				<input type="submit" value="', Lang::$txt['delete_all_prune'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>
		</div>';
}

/**
 * Here we allow the user to setup labels, remove labels and change rules for labels (i.e, do quite a bit)
 */
function template_labels()
{
	echo '
	<form action="', Config::$scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_manage_labels'], '</h3>
		</div>
		<div class="information">
			', Lang::$txt['pm_labels_desc'], '
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">
						', Lang::$txt['pm_label_name'], '
					</th>
					<th class="centertext table_icon">';

	if (count(Utils::$context['labels']) > 2)
		echo '
						<input type="checkbox" onclick="invertAll(this, this.form);">';

	echo '
					</th>
				</tr>
			</thead>
			<tbody>';
	if (count(Utils::$context['labels']) < 2)
		echo '
				<tr class="windowbg">
					<td colspan="2">', Lang::$txt['pm_labels_no_exist'], '</td>
				</tr>';
	else
	{
		foreach (Utils::$context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

			echo '
				<tr class="windowbg">
					<td>
						<input type="text" name="label_name[', $label['id'], ']" value="', $label['name'], '" size="30" maxlength="30">
					</td>
					<td class="table_icon"><input type="checkbox" name="delete_label[', $label['id'], ']"></td>
				</tr>';
		}
	}
	echo '
			</tbody>
		</table>';

	if (!count(Utils::$context['labels']) < 2)
		echo '
		<div class="block righttext">
			<input type="submit" name="save" value="', Lang::$txt['save'], '" class="button">
			<input type="submit" name="delete" value="', Lang::$txt['quickmod_delete_selected'], '" data-confirm="', Lang::$txt['pm_labels_delete'], '" class="button you_sure">
		</div>';

	echo '
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<form action="', Config::$scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_label_add_new'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="add_label">', Lang::$txt['pm_label_name'], '</label>:</strong>
				</dt>
				<dd>
					<input type="text" id="add_label" name="label" value="" size="30" maxlength="30">
				</dd>
			</dl>
			<input type="submit" name="add" value="', Lang::$txt['pm_label_add_new'], '" class="button floatright">
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<br>';
}

/**
 * Template for reporting a personal message.
 */
function template_report_message()
{
	echo '
	<form action="', Config::$scripturl, '?action=pm;sa=report;l=', Utils::$context['current_label_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<input type="hidden" name="pmsg" value="', Utils::$context['pm_id'], '">
		<div class="information">
			', Lang::$txt['pm_report_desc'], '
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_report_title'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">';

	// If there is more than one admin on the forum, allow the user to choose the one they want to direct to.
	// @todo Why?
	if (Utils::$context['admin_count'] > 1)
	{
		echo '
				<dt>
					<strong>', Lang::$txt['pm_report_admins'], ':</strong>
				</dt>
				<dd>
					<select name="id_admin">
						<option value="0">', Lang::$txt['pm_report_all_admins'], '</option>';

		foreach (Utils::$context['admins'] as $id => $name)
			echo '
						<option value="', $id, '">', $name, '</option>';

		echo '
					</select>
				</dd>';
	}

	echo '
				<dt>
					<strong>', Lang::$txt['pm_report_reason'], ':</strong>
				</dt>
				<dd>
					<textarea name="reason" rows="4" cols="70" style="width: 80%;"></textarea>
				</dd>
			</dl>
			<div class="righttext">
				<input type="submit" name="report" value="', Lang::$txt['pm_report_message'], '" class="button">
			</div>
		</div><!-- .windowbg -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>';
}

/**
 * Little template just to say "Yep, it's been submitted"
 */
function template_report_message_complete()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_report_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['pm_report_done'], '</p>
			<a href="', Config::$scripturl, '?action=pm;l=', Utils::$context['current_label_id'], '">', Lang::$txt['pm_report_return'], '</a>
		</div>';
}

/**
 * Manage rules.
 */
function template_rules()
{
	echo '
	<form action="', Config::$scripturl, '?action=pm;sa=manrules" method="post" accept-charset="', Utils::$context['character_set'], '" name="manRules" id="manrules">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['pm_manage_rules'], '</h3>
		</div>
		<div class="information">
			', Lang::$txt['pm_manage_rules_desc'], '
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">
						', Lang::$txt['pm_rule_title'], '
					</th>
					<th class="centertext table_icon">';

	if (!empty(Utils::$context['rules']))
		echo '
						<input type="checkbox" onclick="invertAll(this, this.form);">';

	echo '
					</th>
				</tr>
			</thead>
			<tbody>';

	if (empty(Utils::$context['rules']))
		echo '
				<tr class="windowbg">
					<td colspan="2">
						', Lang::$txt['pm_rules_none'], '
					</td>
				</tr>';

	foreach (Utils::$context['rules'] as $rule)
		echo '
				<tr class="windowbg">
					<td>
						<a href="', Config::$scripturl, '?action=pm;sa=manrules;add;rid=', $rule['id'], '">', $rule['name'], '</a>
					</td>
					<td class="table_icon">
						<input type="checkbox" name="delrule[', $rule['id'], ']">
					</td>
				</tr>';

	echo '
			</tbody>
		</table>
		<div class="righttext">
			<a class="button" href="', Config::$scripturl, '?action=pm;sa=manrules;add;rid=0">', Lang::$txt['pm_add_rule'], '</a>';

	if (!empty(Utils::$context['rules']))
		echo '
			[<a href="', Config::$scripturl, '?action=pm;sa=manrules;apply;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" onclick="return confirm(\'', Lang::$txt['pm_js_apply_rules_confirm'], '\');">', Lang::$txt['pm_apply_rules'], '</a>]';

	if (!empty(Utils::$context['rules']))
		echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="submit" name="delselected" value="', Lang::$txt['pm_delete_selected_rule'], '" data-confirm="', Lang::$txt['pm_js_delete_rule_confirm'], '" class="button smalltext you_sure">';

	echo '
		</div>
	</form>';

}

/**
 * Template for adding/editing a rule.
 */
function template_add_rule()
{
	echo '
	<script>
		var criteriaNum = 0;
		var actionNum = 0;
		var groups = new Array()
		var labels = new Array()';

	foreach (Utils::$context['groups'] as $id => $title)
		echo '
		groups[', $id, '] = "', addslashes($title), '";';

	foreach (Utils::$context['labels'] as $label)
		if ($label['id'] != -1)
			echo '
		labels[', ($label['id']), '] = "', addslashes($label['name']), '";';

	echo '
		function addCriteriaOption()
		{
			if (criteriaNum == 0)
			{
				for (var i = 0; i < document.forms.addrule.elements.length; i++)
					if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
						criteriaNum++;
			}

			if (criteriaNum++ >= ', Utils::$context['rule_limiters']['criteria'], ')
				return false;

			setOuterHTML(document.getElementById("criteriaAddHere"), \'<br><select name="ruletype[\' + criteriaNum + \']" id="ruletype\' + criteriaNum + \'" onchange="updateRuleDef(\' + criteriaNum + \'); rebuildRuleDesc();"><option value="">', addslashes(Lang::$txt['pm_rule_criteria_pick']), ':<\' + \'/option><option value="mid">', addslashes(Lang::$txt['pm_rule_mid']), '<\' + \'/option><option value="gid">', addslashes(Lang::$txt['pm_rule_gid']), '<\' + \'/option><option value="sub">', addslashes(Lang::$txt['pm_rule_sub']), '<\' + \'/option><option value="msg">', addslashes(Lang::$txt['pm_rule_msg']), '<\' + \'/option><option value="bud">', addslashes(Lang::$txt['pm_rule_bud']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="defdiv\' + criteriaNum + \'" style="display: none;"><input type="text" name="ruledef[\' + criteriaNum + \']" id="ruledef\' + criteriaNum + \'" onkeyup="rebuildRuleDesc();" value=""><\' + \'/span><span id="defseldiv\' + criteriaNum + \'" style="display: none;"><select name="ruledefgroup[\' + criteriaNum + \']" id="ruledefgroup\' + criteriaNum + \'" onchange="rebuildRuleDesc();"><option value="">', addslashes(Lang::$txt['pm_rule_sel_group']), '<\' + \'/option>';

	foreach (Utils::$context['groups'] as $id => $group)
		echo '<option value="', $id, '">', strtr($group, array("'" => "\'")), '<\' + \'/option>';

	echo '<\' + \'/select><\' + \'/span><span id="criteriaAddHere"><\' + \'/span>\');

				if (criteriaNum + 1 > ', Utils::$context['rule_limiters']['criteria'], ')
					document.getElementById(\'addonjs1\').style.display = \'none\';
			}

			function addActionOption()
			{
				if (actionNum == 0)
				{
					for (var i = 0; i < document.forms.addrule.elements.length; i++)
						if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
							actionNum++;
				}
				if (actionNum++ >= ', Utils::$context['rule_limiters']['actions'], ')
					return false;

				setOuterHTML(document.getElementById("actionAddHere"), \'<br><select name="acttype[\' + actionNum + \']" id="acttype\' + actionNum + \'" onchange="updateActionDef(\' + actionNum + \'); rebuildRuleDesc();"><option value="">', addslashes(Lang::$txt['pm_rule_sel_action']), ':<\' + \'/option><option value="lab">', addslashes(Lang::$txt['pm_rule_label']), '<\' + \'/option><option value="del">', addslashes(Lang::$txt['pm_rule_delete']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="labdiv\' + actionNum + \'" style="display: none;"><select name="labdef[\' + actionNum + \']" id="labdef\' + actionNum + \'" onchange="rebuildRuleDesc();"><option value="">', addslashes(Lang::$txt['pm_rule_sel_label']), '<\' + \'/option>';

	foreach (Utils::$context['labels'] as $label)
		if ($label['id'] != -1)
			echo '<option value="', ($label['id']), '">', addslashes($label['name']), '<\' + \'/option>';

	echo '<\' + \'/select><\' + \'/span><span id="actionAddHere"><\' + \'/span>\');

				if (actionNum + 1 > ', Utils::$context['rule_limiters']['actions'], ')
					document.getElementById(\'addonjs2\').style.display = \'none\';
			}

			// Rebuild the rule description!
			function rebuildRuleDesc()
			{
				// Start with nothing.
				var text = "";
				var joinText = "";
				var actionText = "";
				var hadBuddy = false;
				var foundCriteria = false;
				var foundAction = false;
				var curNum, curVal, curDef;

				for (var i = 0; i < document.forms.addrule.elements.length; i++)
				{
					if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
					{
						if (foundCriteria)
							joinText = document.getElementById("logic").value == \'and\' ? ', Utils::JavaScriptEscape(' <em>' . Lang::$txt['pm_readable_and'] . '</em> '), ' : ', Utils::JavaScriptEscape(' <em>' . Lang::$txt['pm_readable_or'] . '</em> '), ';
						else
							joinText = \'\';
						foundCriteria = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "gid")
							curDef = document.getElementById("ruledefgroup" + curNum).value.php_htmlspecialchars();
						else if (curVal != "bud")
							curDef = document.getElementById("ruledef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// What type of test is this?
						if (curVal == "mid" && curDef)
							text += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_member']), '.replace("{MEMBER}", curDef);
						else if (curVal == "gid" && curDef && groups[curDef])
							text += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_group']), '.replace("{GROUP}", groups[curDef]);
						else if (curVal == "sub" && curDef)
							text += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_subject']), '.replace("{SUBJECT}", curDef);
						else if (curVal == "msg" && curDef)
							text += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_body']), '.replace("{BODY}", curDef);
						else if (curVal == "bud" && !hadBuddy)
						{
							text += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_buddy']), ';
							hadBuddy = true;
						}
					}
					if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
					{
						if (foundAction)
							joinText = ', Utils::JavaScriptEscape(' <em>' . Lang::$txt['pm_readable_and'] . '</em> '), ';
						else
							joinText = "";
						foundAction = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "lab")
							curDef = document.getElementById("labdef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// Now pick the actions.
						if (curVal == "lab" && curDef && labels[curDef])
							actionText += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_label']), '.replace("{LABEL}", labels[curDef]);
						else if (curVal == "del")
							actionText += joinText + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_delete']), ';
					}
				}

				// If still nothing make it default!
				if (text == "" || !foundCriteria)
					text = "', Lang::$txt['pm_rule_not_defined'], '";
				else
				{
					if (actionText != "")
						text += ', Utils::JavaScriptEscape(' <strong>' . Lang::$txt['pm_readable_then'] . '</strong> '), ' + actionText;
					text = ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_start']), ' + text + ', Utils::JavaScriptEscape(Lang::$txt['pm_readable_end']), ';
				}

				// Set the actual HTML!
				setInnerHTML(document.getElementById("ruletext"), text);
			}
	</script>';

	echo '
	<form action="', Config::$scripturl, '?action=pm;sa=manrules;save;rid=', Utils::$context['rid'], '" method="post" accept-charset="', Utils::$context['character_set'], '" name="addrule" id="addrule" class="flow_hidden">
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['rid'] == 0 ? Lang::$txt['pm_add_rule'] : Lang::$txt['pm_edit_rule'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="addrules">
				<dt class="floatleft">
					<strong>', Lang::$txt['pm_rule_name'], ':</strong><br>
					<span class="smalltext">', Lang::$txt['pm_rule_name_desc'], '</span>
				</dt>
				<dd class="floatleft">
					<input type="text" name="rule_name" value="', empty(Utils::$context['rule']->name) ? Lang::$txt['pm_rule_name_default'] : Utils::$context['rule']->name, '" size="50">
				</dd>
			</dl>
			<fieldset>
				<legend>', Lang::$txt['pm_rule_criteria'], '</legend>';

	// Add a dummy criteria to allow expansion for none js users.
	Utils::$context['rule']->criteria[] = array('t' => '', 'v' => '');

	// For each criteria print it out.
	$isFirst = true;
	foreach (Utils::$context['rule']->criteria as $k => $criteria)
	{
		if (!$isFirst && $criteria['t'] == '')
			echo '<div id="removeonjs1">';

		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="ruletype[', $k, ']" id="ruletype', $k, '" onchange="updateRuleDef(', $k, '); rebuildRuleDesc();">
					<option value="">', Lang::$txt['pm_rule_criteria_pick'], ':</option>';

		foreach (array('mid', 'gid', 'sub', 'msg', 'bud') as $cr)
			echo '
					<option value="', $cr, '"', $criteria['t'] == $cr ? ' selected' : '', '>', Lang::$txt['pm_rule_' . $cr], '</option>';

		echo '
				</select>
				<span id="defdiv', $k, '" ', !in_array($criteria['t'], array('gid', 'bud')) ? '' : 'style="display: none;"', '>
					<input type="text" name="ruledef[', $k, ']" id="ruledef', $k, '" onkeyup="rebuildRuleDesc();" value="', in_array($criteria['t'], array('mid', 'sub', 'msg')) ? $criteria['v'] : '', '">
				</span>
				<span id="defseldiv', $k, '" ', $criteria['t'] == 'gid' ? '' : 'style="display: none;"', '>
					<select name="ruledefgroup[', $k, ']" id="ruledefgroup', $k, '" onchange="rebuildRuleDesc();">
						<option value="">', Lang::$txt['pm_rule_sel_group'], '</option>';

		foreach (Utils::$context['groups'] as $id => $group)
			echo '
						<option value="', $id, '"', $criteria['t'] == 'gid' && $criteria['v'] == $id ? ' selected' : '', '>', $group, '</option>';
		echo '
					</select>
				</span>';

		// If this is the dummy we add a means to hide for non js users.
		if ($isFirst)
			$isFirst = false;

		elseif ($criteria['t'] == '')
			echo '</div><!-- .removeonjs1 -->';
	}

	echo '
				<span id="criteriaAddHere"></span><br>
				<a href="#" onclick="addCriteriaOption(); return false;" id="addonjs1" style="display: none;">(', Lang::$txt['pm_rule_criteria_add'], ')</a>
				<br><br>
				', Lang::$txt['pm_rule_logic'], ':
				<select name="rule_logic" id="logic" onchange="rebuildRuleDesc();">
					<option value="and"', Utils::$context['rule']->logic == 'and' ? ' selected' : '', '>', Lang::$txt['pm_rule_logic_and'], '</option>
					<option value="or"', Utils::$context['rule']->logic == 'or' ? ' selected' : '', '>', Lang::$txt['pm_rule_logic_or'], '</option>
				</select>
			</fieldset>
			<fieldset>
				<legend>', Lang::$txt['pm_rule_actions'], '</legend>';

	// As with criteria - add a dummy action for "expansion".
	Utils::$context['rule']->actions[] = array('t' => '', 'v' => '');

	// Print each action.
	$isFirst = true;
	foreach (Utils::$context['rule']->actions as $k => $action)
	{
		if (!$isFirst && $action['t'] == '')
			echo '<div id="removeonjs2">';
		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="acttype[', $k, ']" id="acttype', $k, '" onchange="updateActionDef(', $k, '); rebuildRuleDesc();">
					<option value="">', Lang::$txt['pm_rule_sel_action'], ':</option>
					<option value="lab"', $action['t'] == 'lab' ? ' selected' : '', '>', Lang::$txt['pm_rule_label'], '</option>
					<option value="del"', $action['t'] == 'del' ? ' selected' : '', '>', Lang::$txt['pm_rule_delete'], '</option>
				</select>
				<span id="labdiv', $k, '">
					<select name="labdef[', $k, ']" id="labdef', $k, '" onchange="rebuildRuleDesc();">
						<option value="">', Lang::$txt['pm_rule_sel_label'], '</option>';

		foreach (Utils::$context['labels'] as $label)
			if ($label['id'] != -1)
				echo '
						<option value="', ($label['id']), '"', $action['t'] == 'lab' && $action['v'] == $label['id'] ? ' selected' : '', '>', $label['name'], '</option>';

		echo '
					</select>
				</span>';

		if ($isFirst)
			$isFirst = false;

		elseif ($action['t'] == '')
			echo '</div><!-- .removeonjs2 -->';
	}

	echo '
				<span id="actionAddHere"></span><br>
				<a href="#" onclick="addActionOption(); return false;" id="addonjs2" style="display: none;">(', Lang::$txt['pm_rule_add_action'], ')</a>
			</fieldset>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['pm_rule_description'], '</h3>
			</div>
			<div class="information">
				<div id="ruletext">', Lang::$txt['pm_rule_js_disabled'], '</div>
			</div>
			<div class="righttext">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="submit" name="save" value="', Lang::$txt['pm_rule_save'], '" class="button">
			</div>
		</div><!-- .windowbg -->
	</form>';

	// Now setup all the bits!
	echo '
	<script>';

	foreach (Utils::$context['rule']->criteria as $k => $c)
		echo '
			updateRuleDef(', $k, ');';

	foreach (Utils::$context['rule']->actions as $k => $c)
		echo '
			updateActionDef(', $k, ');';

	echo '
			rebuildRuleDesc();';

	// If this isn't a new rule and we have JS enabled remove the JS compatibility stuff.
	if (Utils::$context['rid'])
		echo '
			document.getElementById("removeonjs1").style.display = "none";
			document.getElementById("removeonjs2").style.display = "none";';

	if (count(Utils::$context['rule']->criteria) <= Utils::$context['rule_limiters']['criteria'])
		echo '
			document.getElementById("addonjs1").style.display = "";';

	if (count(Utils::$context['rule']->actions) <= Utils::$context['rule_limiters']['actions'])
		echo '
			document.getElementById("addonjs2").style.display = "";';

	echo '
		</script>';
}

/**
 * Template for showing all of a user's PM drafts.
 */
function template_showPMDrafts()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons inbox"></span> ', Lang::$txt['drafts_show'], '
			</h3>
		</div>
		<p class="information">
			', Lang::$txt['drafts_show_desc'], '
		</p>';

	// No drafts? Just show an informative message.
	if (empty(Utils::$context['drafts']))
		echo '
		<div class="windowbg centertext">
			', Lang::$txt['draft_none'], '
		</div>';
	else
	{
		echo '
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';

		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach (Utils::$context['drafts'] as $draft)
		{
			echo '
		<div class="windowbg">
			<div class="page_number floatright"> #', $draft['counter'], '</div>
			<div class="topic_details">
				<h5>
					<strong>', $draft['subject'], '</strong>
				</h5>
				<div class="smalltext">
					<div class="recipient_to"><strong>', Lang::$txt['pm_to'], ':</strong> ', implode(', ', $draft['recipients']['to']), '</div>';

			if(!empty($draft['recipients']['bcc']))
				echo'
					<div class="pm_bbc"><strong>', Lang::$txt['pm_bcc'], ':</strong> ', implode(', ', $draft['recipients']['bcc']), '</div>';

			echo '
				</div>
				<div class="smalltext">
					<strong>', Lang::$txt['draft_saved_on'], ':</strong> ', sprintf(Lang::$txt['draft_days_ago'], $draft['age']), (!empty($draft['remaining']) ? ', ' . sprintf(Lang::$txt['draft_retain'], $draft['remaining']) : ''), '
				</div>
			</div>
			<div class="list_posts">
				', $draft['body'], '
			</div>';

			// Draft buttons
			template_quickbuttons($draft['quickbuttons'], 'pm_drafts');

			echo '
		</div><!-- .windowbg -->';
		}

		// Show page numbers.
		echo '
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';
	}
}

?>