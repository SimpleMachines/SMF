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
 * This template handles displaying a topic
 */
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Let them know, if their report was a success!
	if ($context['report_sent'])
		echo '
		<div class="infobox">
			', $txt['report_sent'], '
		</div>';

	// Let them know why their message became unapproved.
	if ($context['becomesUnapproved'])
		echo '
		<div class="noticebox">
			', $txt['post_becomes_unapproved'], '
		</div>';

	// Show new topic info here?
	echo '
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span id="top_subject">', $context['subject'], '</span>', ($context['is_locked']) ? ' <span class="main_icons lock"></span>' : '', ($context['is_sticky']) ? ' <span class="main_icons sticky"></span>' : '', '
			</h2>
			<p>', $txt['started_by'], ' ', $context['topic_poster_name'], ', ', $context['topic_started_time'], '</p>';

	// Next - Prev
	echo '
			<span class="nextlinks floatright">', $context['previous_next'], '</span>';

	if (!empty($settings['display_who_viewing']))
	{
		echo '
			<p>';

		// Show just numbers...?
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		// Or show the actual people viewing the topic?
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		// Now show how many guests are here too.
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
			</p>';
	}

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
		</div><!-- #display_head -->
		<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a id="new"></a>' : '';

	// Is this topic also a poll?
	if ($context['is_poll'])
	{
		echo '
		<div id="poll">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons poll"></span>', $context['poll']['is_locked'] ? '<span class="main_icons lock"></span>' : '', ' ', $context['poll']['question'], '
				</h3>
			</div>
			<div class="windowbg">
				<div id="poll_options">';

		// Are they not allowed to vote but allowed to view the options?
		if ($context['poll']['show_results'] || !$context['allow_vote'])
		{
			echo '
					<dl class="options">';

			// Show each option with its corresponding percentage bar.
			foreach ($context['poll']['options'] as $option)
			{
				echo '
						<dt class="', $option['voted_this'] ? ' voted' : '', '">', $option['option'], '</dt>
						<dd class="statsbar generic_bar', $option['voted_this'] ? ' voted' : '', '">';

				if ($context['allow_results_view'])
					echo '
							', $option['bar_ndt'], '
							<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

				echo '
						</dd>';
			}

			echo '
					</dl>';

			if ($context['allow_results_view'])
				echo '
					<p><strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '</p>';
		}
		// They are allowed to vote! Go to it!
		else
		{
			echo '
					<form action="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="', $context['character_set'], '">';

			// Show a warning if they are allowed more than one option.
			if ($context['poll']['allowed_warning'])
				echo '
						<p class="smallpadding">', $context['poll']['allowed_warning'], '</p>';

			echo '
						<ul class="options">';

			// Show each option with its button - a radio likely.
			foreach ($context['poll']['options'] as $option)
				echo '
							<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

			echo '
						</ul>
						<div class="submitbutton">
							<input type="submit" value="', $txt['poll_vote'], '" class="button">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</div>
					</form>';
		}

		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
					<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';

		echo '
				</div><!-- #poll_options -->
			</div><!-- .windowbg -->
		</div><!-- #poll -->
		<div id="pollmoderation">';

		template_button_strip($context['poll_buttons']);

		echo '
		</div>';
	}

	// Does this topic have some events linked to it?
	if (!empty($context['linked_calendar_events']))
	{
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', $txt['calendar_linked_events'], '</h3>
		</div>
		<div class="information">
			<ul>';

		foreach ($context['linked_calendar_events'] as $event)
		{
			echo '
				<li>
					<strong class="event_title"><a href="', $scripturl, '?action=calendar;event=', $event['id'], '">', $event['title'], '</a></strong>';

			if ($event['can_edit'])
				echo ' <a href="' . $event['modify_href'] . '"><span class="main_icons calendar_modify" title="', $txt['calendar_edit'], '"></span></a>';

			if ($event['can_export'])
				echo ' <a href="' . $event['export_href'] . '"><span class="main_icons calendar_export" title="', $txt['calendar_export'], '"></span></a>';

			echo '
					<br>';

			if (!empty($event['allday']))
			{
				echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), '</time>', ($event['start_date'] != $event['end_date']) ? ' &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">' . trim($event['end_date_local']) . '</time>' : '';
			}
			else
			{
				// Display event info relative to user's local timezone
				echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), ', ', trim($event['start_time_local']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

				if ($event['start_date_local'] != $event['end_date_local'])
					echo trim($event['end_date_local']) . ', ';

				echo trim($event['end_time_local']);

				// Display event info relative to original timezone
				if ($event['start_date_local'] . $event['start_time_local'] != $event['start_date_orig'] . $event['start_time_orig'])
				{
					echo '</time> (<time datetime="' . $event['start_iso_gmdate'] . '">';

					if ($event['start_date_orig'] != $event['start_date_local'] || $event['end_date_orig'] != $event['end_date_local'] || $event['start_date_orig'] != $event['end_date_orig'])
						echo trim($event['start_date_orig']), ', ';

					echo trim($event['start_time_orig']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

					if ($event['start_date_orig'] != $event['end_date_orig'])
						echo trim($event['end_date_orig']) . ', ';

					echo trim($event['end_time_orig']), ' ', $event['tz_abbrev'], '</time>)';
				}
				// Event is scheduled in the user's own timezone? Let 'em know, just to avoid confusion
				else
					echo ' ', $event['tz_abbrev'], '</time>';
			}

			if (!empty($event['location']))
				echo '
					<br>', $event['location'];

			echo '
				</li>';
		}
		echo '
			</ul>
		</div><!-- .information -->';
	}

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection top">
			', template_button_strip($context['normal_buttons'], 'right'), '
			', $context['menu_separator'], '
			<div class="pagelinks floatleft">
				<a href="#bot" class="button">', $txt['go_down'], '</a>
				', $context['page_index'], '
			</div>
		</div>';

	// Mobile action - moderation buttons (top)
	if (!empty($context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', $txt['mobile_action'], '</a>
			', !empty($context['mod_buttons']) ? '<a class="button mobile_mod">' . $txt['mobile_moderation'] . '</a>' : '', '
		</div>';

	// Show the topic information - icon, subject, etc.
	echo '
		<div id="forumposts">
			<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';

	$context['ignoredMsgs'] = array();
	$context['removableMessageIDs'] = array();

	// Get all the messages...
	while ($message = $context['get_message']())
		template_single_post($message);

	echo '
			</form>
		</div><!-- #forumposts -->';

	// Mobile action - moderation buttons (bottom)
	if (!empty($context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', $txt['mobile_action'], '</a>
			', !empty($context['mod_buttons']) ? '<a class="button mobile_mod">' . $txt['mobile_moderation'] . '</a>' : '', '
		</div>';

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection">
			', template_button_strip($context['normal_buttons'], 'right'), '
			', $context['menu_separator'], '
			<div class="pagelinks floatleft">
				<a href="#main_content_section" class="button" id="bot">', $txt['go_up'], '</a>
				', $context['page_index'], '
			</div>
		</div>';

	// Show the lower breadcrumbs.
	theme_linktree();

	// Moderation buttons
	echo '
		<div id="moderationbuttons">
			', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip')), '
		</div>';

	// Show the jumpto box, or actually...let Javascript do it.
	echo '
		<div id="display_jump_to"></div>';

	// Show quickreply
	if ($context['can_reply'])
		template_quickreply();

	// User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device.
	echo '
		<div id="mobile_action" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', $txt['mobile_action'], '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				', template_button_strip($context['normal_buttons']), '
			</div>
		</div>';

	// Show the moderation button & pop (if there is anything to show)
	if (!empty($context['mod_buttons']))
		echo '
		<div id="mobile_moderation" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', $txt['mobile_moderation'], '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<div id="moderationbuttons_mobile">
					', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip_mobile')), '
				</div>
			</div>
		</div>';

	echo '
		<script>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
	{
		echo '
			var oInTopicModeration = new InTopicModeration({
				sSelf: \'oInTopicModeration\',
				sCheckboxContainerMask: \'in_topic_mod_check_\',
				aMessageIds: [\'', implode('\', \'', $context['removableMessageIDs']), '\'],
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sButtonStrip: \'moderationbuttons\',
				sButtonStripDisplay: \'moderationbuttons_strip\',
				bUseImageButton: false,
				bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
				sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
				sRemoveButtonImage: \'delete_selected.png\',
				sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
				sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
				sRestoreButtonImage: \'restore_selected.png\',
				sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
				sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
				sSplitButtonImage: \'split_selected.png\',
				sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				sFormId: \'quickModForm\'
			});';

		// Add it to the mobile button strip as well
		echo '
			var oInTopicModerationMobile = new InTopicModeration({
				sSelf: \'oInTopicModerationMobile\',
				sCheckboxContainerMask: \'in_topic_mod_check_\',
				aMessageIds: [\'', implode('\', \'', $context['removableMessageIDs']), '\'],
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sButtonStrip: \'moderationbuttons_mobile\',
				sButtonStripDisplay: \'moderationbuttons_strip_mobile\',
				bUseImageButton: false,
				bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
				sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
				sRemoveButtonImage: \'delete_selected.png\',
				sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
				sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
				sRestoreButtonImage: \'restore_selected.png\',
				sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
				sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
				sSplitButtonImage: \'split_selected.png\',
				sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				sFormId: \'quickModForm\'
			});';
	}

	echo '
			if (\'XMLHttpRequest\' in window)
			{
				var oQuickModify = new QuickModify({
					sScriptUrl: smf_scripturl,
					sClassName: \'quick_edit\',
					bShowModify: ', $modSettings['show_modify'] ? 'true' : 'false', ',
					iTopicId: ', $context['current_topic'], ',
					sTemplateBodyEdit: ', JavaScriptEscape('
						<div id="quick_edit_body_container">
							<div id="error_box" class="error"></div>
							<textarea class="editor" name="message" rows="12" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br>
							<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
							<input type="hidden" name="topic" value="' . $context['current_topic'] . '">
							<input type="hidden" name="msg" value="%msg_id%">
							<div class="righttext quickModifyMargin">
								<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="button">' . ($context['show_spellchecking'] ? ' <input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="button">' : '') . ' <input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="button">
							</div>
						</div>'), ',
					sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '">'), ',
					sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
					sTemplateSubjectNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow">%subject%</a>'), ',
					sTemplateTopSubject: ', JavaScriptEscape('%subject%'), ',
					sTemplateReasonEdit: ', JavaScriptEscape($txt['reason_for_edit'] . ': <input type="text" name="modify_reason" value="%modify_reason%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="quickModifyMargin">'), ',
					sTemplateReasonNormal: ', JavaScriptEscape('%modify_text'), ',
					sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), ($context['can_reply']) ? ',
					sFormRemoveAccessKeys: \'postmodify\'' : '', '
				});

				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "display_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
					iCurBoardId: ', $context['current_board'], ',
					iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "', $txt['go'], '"
				});

				aIconLists[aIconLists.length] = new IconList({
					sBackReference: "aIconLists[" + aIconLists.length + "]",
					sIconIdPrefix: "msg_icon_",
					sScriptUrl: smf_scripturl,
					bShowModify: ', !empty($modSettings['show_modify']) ? 'true' : 'false', ',
					iBoardId: ', $context['current_board'], ',
					iTopicId: ', $context['current_topic'], ',
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sLabelIconList: "', $txt['message_icon'], '",
					sBoxBackground: "transparent",
					sBoxBackgroundHover: "#ffffff",
					iBoxBorderWidthHover: 1,
					sBoxBorderColorHover: "#adadad" ,
					sContainerBackground: "#ffffff",
					sContainerBorder: "1px solid #adadad",
					sItemBorder: "1px solid #ffffff",
					sItemBorderHover: "1px dotted gray",
					sItemBackground: "transparent",
					sItemBackgroundHover: "#e0e0f0"
				});
			}';

	if (!empty($context['ignoredMsgs']))
		echo '
			ignore_toggles([', implode(', ', $context['ignoredMsgs']), '], ', JavaScriptEscape($txt['show_ignore_user_post']), ');';

	echo '
		</script>';
}

/**
 * Template for displaying a single post.
 *
 * @param array $message An array of information about the message to display. Should have 'id' and 'member'. Can also have 'first_new', 'is_ignored' and 'css_class'.
 */
function template_single_post($message)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$ignoring = false;

	if ($message['can_remove'])
		$context['removableMessageIDs'][] = $message['id'];

	// Are we ignoring this message?
	if (!empty($message['is_ignored']))
	{
		$ignoring = true;
		$context['ignoredMsgs'][] = $message['id'];
	}

	// Show the message anchor and a "new" anchor if this message is new.
	echo '
				<div class="', $message['css_class'], '">
					', $message['id'] != $context['first_message'] ? '
					<a id="msg' . $message['id'] . '"></a>' . ($message['first_new'] ? '<a id="new"></a>' : '') : '', '
					<div class="post_wrapper">';

	// Show information about the poster of this message.
	echo '
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
	if (!empty($modSettings['onlineEnable']) && !$message['member']['is_guest'])
		echo '
								', $context['can_send_pm'] ? '<a href="' . $message['member']['online']['href'] . '" title="' . $message['member']['online']['label'] . '">' : '', '<span class="' . ($message['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $message['member']['online']['text'] . '"></span>', $context['can_send_pm'] ? '</a>' : '';

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

	// Begin display of user info
	echo '
							</h4>
							<ul class="user_info">';

	// Show the user's avatar.
	if (!empty($modSettings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
		echo '
								<li class="avatar">
									<a href="', $message['member']['href'], '">', $message['member']['avatar']['image'], '</a>
								</li>';

	// Are there any custom fields below the avatar?
	if (!empty($message['custom_fields']['below_avatar']))
		foreach ($message['custom_fields']['below_avatar'] as $custom)
			echo '
								<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

	// Show the post group icons, but not for guests.
	if (!$message['member']['is_guest'])
		echo '
								<li class="icons">', $message['member']['group_icons'], '</li>';

	// Show the member's primary group (like 'Administrator') if they have one.
	if (!empty($message['member']['group']))
		echo '
								<li class="membergroup">', $message['member']['group'], '</li>';

	// Show the member's custom title, if they have one.
	if (!empty($message['member']['title']))
		echo '
								<li class="title">', $message['member']['title'], '</li>';

	// Don't show these things for guests.
	if (!$message['member']['is_guest'])
	{
		// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
		if ((empty($modSettings['hide_post_group']) || empty($message['member']['group'])) && !empty($message['member']['post_group']))
			echo '
								<li class="postgroup">', $message['member']['post_group'], '</li>';

		// Show how many posts they have made.
		if (!isset($context['disabled_fields']['posts']))
			echo '
								<li class="postcount">', $txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

		// Show their personal text?
		if (!empty($modSettings['show_blurb']) && !empty($message['member']['blurb']))
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

		// Show the website and email address buttons.
		if ($message['member']['show_profile_buttons'])
		{
			echo '
								<li class="profile">
									<ol class="profile_icons">';

			// Don't show an icon if they haven't specified a website.
			if (!empty($message['member']['website']['url']) && !isset($context['disabled_fields']['website']))
				echo '
										<li><a href="', $message['member']['website']['url'], '" title="' . $message['member']['website']['title'] . '" target="_blank" rel="noopener">', ($settings['use_image_buttons'] ? '<span class="main_icons www centericon" title="' . $message['member']['website']['title'] . '"></span>' : $txt['www']), '</a></li>';

			// Since we know this person isn't a guest, you *can* message them.
			if ($context['can_send_pm'])
				echo '
										<li><a href="', $scripturl, '?action=pm;sa=send;u=', $message['member']['id'], '" title="', $message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline'], '">', $settings['use_image_buttons'] ? '<span class="main_icons im_' . ($message['member']['online']['is_online'] ? 'on' : 'off') . ' centericon" title="' . ($message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline']) . '"></span> ' : ($message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline']), '</a></li>';

			// Show the email if necessary
			if (!empty($message['member']['email']) && $message['member']['show_email'])
				echo '
										<li class="email"><a href="mailto:' . $message['member']['email'] . '" rel="nofollow">', ($settings['use_image_buttons'] ? '<span class="main_icons mail centericon" title="' . $txt['email'] . '"></span>' : $txt['email']), '</a></li>';

			echo '
									</ol>
								</li><!-- .profile -->';
		}

		// Any custom fields for standard placement?
		if (!empty($message['custom_fields']['standard']))
			foreach ($message['custom_fields']['standard'] as $custom)
				echo '
								<li class="custom ', $custom['col_name'], '">', $custom['title'], ': ', $custom['value'], '</li>';
	}
	// Otherwise, show the guest's email.
	elseif (!empty($message['member']['email']) && $message['member']['show_email'])
		echo '
								<li class="email">
									<a href="mailto:' . $message['member']['email'] . '" rel="nofollow">', ($settings['use_image_buttons'] ? '<span class="main_icons mail centericon" title="' . $txt['email'] . '"></span>' : $txt['email']), '</a>
								</li>';

	// Show the IP to this user for this post - because you can moderate?
	if (!empty($context['can_moderate_forum']) && !empty($message['member']['ip']))
		echo '
								<li class="poster_ip">
									<a href="', $scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '">', $message['member']['ip'], '</a> <a href="', $scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>
								</li>';

	// Or, should we show it because this is you?
	elseif ($message['can_see_ip'])
		echo '
								<li class="poster_ip">
									<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', $message['member']['ip'], '</a>
								</li>';

	// Okay, are you at least logged in? Then we can show something about why IPs are logged...
	elseif (!$context['user']['is_guest'])
		echo '
								<li class="poster_ip">
									<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', $txt['logged'], '</a>
								</li>';

	// Otherwise, you see NOTHING!
	else
		echo '
								<li class="poster_ip">', $txt['logged'], '</li>';

	// Are we showing the warning status?
	// Don't show these things for guests.
	if (!$message['member']['is_guest'] && $message['member']['can_see_warning'])
		echo '
								<li class="warning">
									', $context['can_issue_warning'] ? '<a href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '', '<span class="main_icons warning_', $message['member']['warning_status'], '"></span> ', $context['can_issue_warning'] ? '</a>' : '', '<span class="warn_', $message['member']['warning_status'], '">', $txt['warn_' . $message['member']['warning_status']], '</span>
								</li>';

	// Are there any custom fields to show at the bottom of the poster info?
	if (!empty($message['custom_fields']['bottom_poster']))
		foreach ($message['custom_fields']['bottom_poster'] as $custom)
			echo '
								<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

	// Poster info ends.
	echo '
							</ul>
						</div><!-- .poster -->
						<div class="postarea">
							<div class="keyinfo">';

	// Some people don't want subject... The div is still required or quick edit breaks.
	echo '
								<div id="subject_', $message['id'], '" class="subject_title', (empty($modSettings['subject_toggle']) ? ' subject_hidden' : ''), '">
									', $message['link'], '
								</div>';

	echo '
								<h5>
									<span class="messageicon" ', ($message['icon_url'] === $settings['images_url'] . '/post/xx.png' && !$message['can_modify']) ? ' style="position: absolute; z-index: -1;"' : '', '>
										<img src="', $message['icon_url'] . '" alt=""', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', '>
									</span>
									<a href="', $message['href'], '" rel="nofollow" title="', !empty($message['counter']) ? sprintf($txt['reply_number'], $message['counter'], ' - ') : '', $message['subject'], '" class="smalltext">', $message['time'], '</a>
									<span class="page_number floatright">
										', !empty($message['counter']) ? ' #' . $message['counter'] : '', ' ', '
									</span>';

	// Show "<< Last Edit: Time by Person >>" if this post was edited. But we need the div even if it wasn't modified!
	// Because we insert into it through AJAX and we don't want to stop themers moving it around if they so wish so they can put it where they want it.
	echo '
									<span class="smalltext modified floatright', !empty($modSettings['show_modify']) && !empty($message['modified']['name']) ? ' mvisible' : '', '" id="modified_', $message['id'], '">';

	if (!empty($modSettings['show_modify']) && !empty($message['modified']['name']))
		echo
										$message['modified']['last_edit_text'];

	echo '
									</span>
								</h5>
								<div id="msg_', $message['id'], '_quick_mod"', $ignoring ? ' style="display:none;"' : '', '></div>
							</div><!-- .keyinfo -->';

	// Ignoring this user? Hide the post.
	if ($ignoring)
		echo '
							<div id="msg_', $message['id'], '_ignored_prompt">
								', $txt['ignoring_user'], '
								<a href="#" id="msg_', $message['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
							</div>';

	// Show the post itself, finally!
	echo '
							<div class="post">';

	if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
		echo '
								<div class="noticebox">
									', $txt['post_awaiting_approval'], '
								</div>';
	echo '
								<div class="inner" data-msgid="', $message['id'], '" id="msg_', $message['id'], '"', $ignoring ? ' style="display:none;"' : '', '>
									', $message['body'], '
								</div>
							</div><!-- .post -->';

	// Assuming there are attachments...
	if (!empty($message['attachment']))
	{
		$last_approved_state = 1;
		$attachments_per_line = 5;
		$i = 0;
		// Don't output the div unless we actually have something to show...
		$div_output = false;

		foreach ($message['attachment'] as $attachment)
		{
			// Do we want this attachment to not be showed here?
			if (!empty($modSettings['dont_show_attach_under_post']) && !empty($context['show_attach_under_post'][$attachment['id']]))
				continue;
			elseif (!$div_output)
			{
				$div_output = true;

				echo '
							<div id="msg_', $message['id'], '_footer" class="attachments"', $ignoring ? ' style="display:none;"' : '', '>';
			}

			// Show a special box for unapproved attachments...
			if ($attachment['is_approved'] != $last_approved_state)
			{
				$last_approved_state = 0;
				echo '
								<fieldset>
									<legend>
										', $txt['attach_awaiting_approve'];

				if ($context['can_approve'])
					echo '
										&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>]';

				echo '
									</legend>';
			}

			echo '
									<div class="attached">';

			if ($attachment['is_image'])
			{
				echo '
										<div class="attachments_top">';

				if ($attachment['thumbnail']['has_thumb'])
					echo '
											<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" class="atc_img"></a>';
				else
					echo '
											<img src="' . $attachment['href'] . ';image" alt="" width="' . $attachment['width'] . '" height="' . $attachment['height'] . '" class="atc_img">';

				echo '
										</div><!-- .attachments_top -->';
			}

			echo '
										<div class="attachments_bot">
											<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.png" class="centericon" alt="*">&nbsp;' . $attachment['name'] . '</a> ';

			if (!$attachment['is_approved'] && $context['can_approve'])
				echo '
											[<a href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>] [<a href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>] ';
			echo '
											<br>', $attachment['size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . '<br>' . sprintf($txt['attach_viewed'], $attachment['downloads']) : '<br>' . sprintf($txt['attach_downloaded'], $attachment['downloads'])), '
										</div><!-- .attachments_bot -->';

			echo '
									</div><!-- .attached -->';

			// Next attachment line ?
			if (++$i % $attachments_per_line === 0)
				echo '
									<br>';
		}

		// If we had unapproved attachments clean up.
		if ($last_approved_state == 0)
			echo '
								</fieldset>';

		// Only do this if we output a div above - otherwise it'll break things
		if ($div_output)
			echo '
							</div><!-- #msg_[id]_footer -->';
	}

	echo '
							<div class="under_message">';

	// What about likes?
	if (!empty($modSettings['enable_likes']))
	{
		echo '
								<ul class="floatleft">';

		if (!empty($message['likes']['can_like']))
		{
			echo '
									<li class="smflikebutton" id="msg_', $message['id'], '_likes"', $ignoring ? ' style="display:none;"' : '', '>
										<a href="', $scripturl, '?action=likes;ltype=msg;sa=like;like=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="msg_like"><span class="main_icons ', $message['likes']['you'] ? 'unlike' : 'like', '"></span> ', $message['likes']['you'] ? $txt['unlike'] : $txt['like'], '</a>
									</li>';
		}

		if (!empty($message['likes']['count']))
		{
			$context['some_likes'] = true;
			$count = $message['likes']['count'];
			$base = 'likes_';

			if ($message['likes']['you'])
			{
				$base = 'you_' . $base;
				$count--;
			}

			$base .= (isset($txt[$base . $count])) ? $count : 'n';

			echo '
									<li class="like_count smalltext">
										', sprintf($txt[$base], $scripturl . '?action=likes;sa=view;ltype=msg;like=' . $message['id'] . ';' . $context['session_var'] . '=' . $context['session_id'], comma_format($count)), '
									</li>';
		}

		echo '
								</ul>';
	}

	// Show the quickbuttons, for various operations on posts.
	template_quickbuttons($message['quickbuttons'], 'post');

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
	if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		echo '
							<div class="signature" id="msg_', $message['id'], '_signature"', $ignoring ? ' style="display:none;"' : '', '>
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
				</div><!-- $message[css_class] -->
				<hr class="post_separator">';
}

/**
 * The template for displaying the quick reply box.
 */
function template_quickreply()
{
	global $context, $modSettings, $scripturl, $options, $txt;

	echo '
		<a id="quickreply_anchor"></a>
		<div class="tborder" id="quickreply">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['quick_reply'], '
				</h3>
			</div>
			<div id="quickreply_options">
				<div class="roundframe">';

	// Are we hiding the full editor?
	if (empty($options['use_editor_quick_reply']))
		echo '
					<p class="smalltext lefttext">', $txt['quick_reply_desc'], '</p>';

	// Is the topic locked?
	if ($context['is_locked'])
		echo '
					<p class="alert smalltext">', $txt['quick_reply_warning'], '</p>';

	// Show a warning if the topic is old
	if (!empty($context['oldTopicError']))
		echo '
					<p class="alert smalltext">', sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']), '</p>';

	// Does the post need approval?
	if (!$context['can_reply_approved'])
		echo '
					<p><em>', $txt['wait_for_approval'], '</em></p>';

	echo '
					<form action="', $scripturl, '?board=', $context['current_board'], ';action=post2" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" onsubmit="submitonce(this);">
						<input type="hidden" name="topic" value="', $context['current_topic'], '">
						<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '">
						<input type="hidden" name="icon" value="xx">
						<input type="hidden" name="from_qr" value="1">
						<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '">
						<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '">
						<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '">
						<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

	// Guests just need more.
	if ($context['user']['is_guest'])
		echo '
						<dl id="post_header">
							<dt>
								', $txt['name'], ':
							</dt>
							<dd>
								<input type="text" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '">
							</dd>
							<dt>
								', $txt['email'], ':
							</dt>
							<dd>
								<input type="email" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" required>
							</dd>
						</dl>';

	echo '
						', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
						<script>
							function insertQuoteFast(messageid)
							{
								var e = document.getElementById("', $context['post_box_name'], '");
								sceditor.instance(e).insertQuoteFast(messageid);

								return false;
							}
						</script>';

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
						<div class="post_verification">
							<strong>', $txt['verification'], ':</strong>
							', template_control_verification($context['visual_verification_id'], 'all'), '
						</div>';

	// Finally, the submit buttons.
	echo '
						<br class="clear_right">
						<span id="post_confirm_buttons" class="floatright">
							', template_control_richedit_buttons($context['post_box_name']), '
						</span>';
	echo '
					</form>
				</div><!-- .roundframe -->
			</div><!-- #quickreply_options -->
		</div><!-- #quickreply -->
		<br class="clear">';

	// Draft autosave available and the user has it enabled?
	if (!empty($context['drafts_autosave']))
		echo '
		<script>
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_draft\',', !empty($context['post_box_name']) ? '
				sSceditorID: \'' . $context['post_box_name'] . '\',' : '', '
				sType: \'', 'quick', '\',
				iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
				iFreq: ', (empty($modSettings['masterAutoSaveDraftsDelay']) ? 60000 : $modSettings['masterAutoSaveDraftsDelay'] * 1000), '
			});
		</script>';

	if ($context['show_spellchecking'])
		echo '
		<form action="', $scripturl, '?action=spellcheck" method="post" accept-charset="', $context['character_set'], '" name="spell_form" id="spell_form" target="spellWindow">
			<input type="hidden" name="spellstring" value="">
		</form>';

	echo '
		<script>
			var oQuickReply = new QuickReply({
				bDefaultCollapsed: false,
				iTopicId: ', $context['current_topic'], ',
				iStart: ', $context['start'], ',
				sScriptUrl: smf_scripturl,
				sImagesUrl: smf_images_url,
				sContainerId: "quickreply_options",
				sImageId: "quickReplyExpand",
				sClassCollapsed: "toggle_up",
				sClassExpanded: "toggle_down",
				sJumpAnchor: "quickreply_anchor",
				bIsFull: true
			});
			var oEditorID = "', $context['post_box_name'], '";
			var oEditorObject = oEditorHandle_', $context['post_box_name'], ';
			var oJumpAnchor = "quickreply_anchor";
		</script>';
}

?>