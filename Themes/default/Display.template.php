<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\User;

/**
 * This template handles displaying a topic
 */
function template_main()
{
	// Let them know, if their report was a success!
	if (Utils::$context['report_sent'])
		echo '
		<div class="infobox">
			', Lang::getTxt('report_sent', file: 'General'), '
		</div>';

	// Let them know why their message became unapproved.
	if (Utils::$context['becomesUnapproved'])
		echo '
		<div class="noticebox">
			', Lang::getTxt('post_becomes_unapproved', file: 'General'), '
		</div>';

	// Show new topic info here?
	echo '
		<div id="display_head">
			<h2 class="display_title">
				<span id="top_subject">', Utils::$context['subject'], '</span>', (Utils::$context['is_locked']) ? ' <span class="main_icons lock"></span>' : '', (Utils::$context['is_sticky']) ? ' <span class="main_icons sticky"></span>' : '', '
			</h2>
			<p>', Lang::getTxt('started_by_member_time', ['member' => Utils::$context['topic_poster_name'], 'time' => Utils::$context['topic_started_time']], file: 'General'), '</p>';

	// Next - Prev
	echo '
			<span class="nextlinks">', Utils::$context['previous_next'], '</span>';

	if (!empty(Theme::$current->settings['display_who_viewing']))
	{
		// Show just numbers...?
		if (Theme::$current->settings['display_who_viewing'] == 1 || empty(Utils::$context['view_members_list'])) {
			$list_of_viewers = [
				Lang::getTxt('number_of_members', [0], file: 'General'),
			];
		}
		// Or show the actual people viewing the topic?
		else {
			$list_of_viewers = Utils::$context['view_members_list'];
		}

		if (!empty(Utils::$context['view_num_hidden']) && !Utils::$context['can_moderate_forum']) {
			$list_of_viewers[] = Lang::getTxt('number_of_hidden_members', [Utils::$context['view_num_hidden']], file: 'General');
		}

		// Now show how many guests are here too.
		if (!empty(Utils::$context['view_num_guests'])) {
			$list_of_viewers[] = Lang::getTxt('guest_plural', [Utils::$context['view_num_guests']], file: 'General');
		}

		echo '
			<p>
				', Lang::getTxt(
					'who_viewing_topic',
					[
						'list_of_viewers' => Lang::sentenceList(array_values($list_of_viewers)),
						'num_viewing' => count(Utils::$context['view_members_list'] ?? []) + (int) (Utils::$context['view_num_guests'] ?? 0) + (int) (Utils::$context['view_num_hidden'] ?? 0),
					],
					file: 'General',
				), '
			</p>';
	}

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
		</div><!-- #display_head -->
		', Utils::$context['first_new_message'] ? '<a id="new"></a>' : '';

	// Is this topic also a poll?
	if (Utils::$context['is_poll'])
	{
		echo '
		<div id="poll">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons poll"></span>', Utils::$context['poll']['is_locked'] ? '<span class="main_icons lock"></span>' : '', ' ', Utils::$context['poll']['question'], '
				</h3>
			</div>
			<div class="windowbg">
				<div id="poll_options">';

		// Are they not allowed to vote but allowed to view the options?
		if (Utils::$context['poll']['show_results'] || !Utils::$context['allow_vote'])
		{
			echo '
					<dl class="options">';

			// Show each option with its corresponding percentage bar.
			foreach (Utils::$context['poll']['options'] as $option)
			{
				echo '
						<dt class="', $option['voted_this'] ? ' voted' : '', '">', $option['option'], '</dt>
						<dd class="statsbar generic_bar', $option['voted_this'] ? ' voted' : '', '">';

				if (Utils::$context['allow_results_view'])
					echo '
							', $option['bar_ndt'], '
							<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

				echo '
						</dd>';
			}

			echo '
					</dl>';

			if (Utils::$context['allow_results_view'])
				echo '
					<p>', Lang::getTxt('poll_total_voters', [Utils::$context['poll']['total_votes']], file: 'General'), '</p>';
		}
		// They are allowed to vote! Go to it!
		else
		{
			echo '
					<form action="', Config::$scripturl, '?action=vote;topic=', Utils::$context['current_topic'], '.', Utils::$context['start'], '" method="post" accept-charset="UTF-8">';

			// Show a warning if they are allowed more than one option.
			if (Utils::$context['poll']['allowed_warning'])
				echo '
						<p class="smallpadding">', Utils::$context['poll']['allowed_warning'], '</p>';

			echo '
						<ul class="options">';

			// Show each option with its button - a radio likely.
			foreach (Utils::$context['poll']['options'] as $option)
				echo '
							<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

			echo '
						</ul>
						<div class="submitbutton">
							<input type="submit" value="', Lang::getTxt('poll_vote', file: 'General'), '" class="button">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						</div>
					</form>';
		}

		// Is the clock ticking?
		if (!empty(Utils::$context['poll']['expire_time']))
			echo '
					<p><strong>', Lang::getTxt(Utils::$context['poll']['is_expired'] ? 'poll_expired_on' : 'poll_expires_on', file: 'General'), ':</strong> ', Utils::$context['poll']['expire_time'], '</p>';

		echo '
				</div><!-- #poll_options -->
			</div><!-- .windowbg -->
		</div><!-- #poll -->
		<div id="pollmoderation">';

		template_button_strip(Utils::$context['poll_buttons']);

		echo '
		</div>';
	}

	// Does this topic have some events linked to it?
	if (!empty(Utils::$context['linked_calendar_events'])) {
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::getTxt('calendar_linked_events', file: 'Calendar'), '</h3>
		</div>';

		template_linked_events();
	}

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection top">
			<div class="pagelinks floatleft">
				<a href="#bot" class="button">', Lang::getTxt('go_down', file: 'General'), '</a>
				', Utils::$context['page_index'], '
			</div>
			', Utils::$context['menu_separator'], '
			', template_button_strip(Utils::$context['normal_buttons'], 'right'), '';

	// Mobile action - moderation buttons (top)
	if (!empty(Utils::$context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
			', !empty(Utils::$context['mod_buttons']) ? '<a class="button mobile_mod">' . Lang::getTxt('mobile_moderation', file: 'General') . '</a>' : '', '
		</div>';

	echo '
		</div>';

	// Show the topic information - icon, subject, etc.
	echo '
		<form action="', Config::$scripturl, '?action=quickmod2;topic=', Utils::$context['current_topic'], '.', Utils::$context['start'], '" method="post" accept-charset="UTF-8" id="quickModForm">
			<div id="forumposts">';

	Utils::$context['ignoredMsgs'] = array();
	Utils::$context['removableMessageIDs'] = array();

	// Get all the messages...
	while ($message = Utils::$context['get_message']())
		template_single_post($message);

	echo '
			</div><!-- #forumposts -->
		</form>';

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">
				<a href="#main_content_section" class="button" id="bot">', Lang::getTxt('go_up', file: 'General'), '</a>
				', Utils::$context['page_index'], '
			</div>
			', Utils::$context['menu_separator'], '
			', template_button_strip(Utils::$context['normal_buttons'], 'right'), '';

	// Mobile action - moderation buttons (bottom)
	if (!empty(Utils::$context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
			', !empty(Utils::$context['mod_buttons']) ? '<a class="button mobile_mod">' . Lang::getTxt('mobile_moderation', file: 'General') . '</a>' : '', '
		</div>';

	echo '
		</div>';

	// Show the lower breadcrumbs.
	theme_linktree();

	// Moderation buttons
	echo '
		<div id="moderationbuttons">
			', template_button_strip(Utils::$context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip')), '
		</div>';

	// Show the jumpto box, or actually...let Javascript do it.
	echo '
		<div id="display_jump_to"></div>';

	// Show quickreply
	if (Utils::$context['can_reply'])
		template_quickreply();

	// User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device.
	echo '
		<div id="mobile_action" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', Lang::getTxt('mobile_action', file: 'General'), '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				', template_button_strip(Utils::$context['normal_buttons']), '
			</div>
		</div>';

	// Show the moderation button & pop (if there is anything to show)
	if (!empty(Utils::$context['mod_buttons']))
		echo '
		<div id="mobile_moderation" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', Lang::getTxt('mobile_moderation', file: 'General'), '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<div id="moderationbuttons_mobile">
					', template_button_strip(Utils::$context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip_mobile')), '
				</div>
			</div>
		</div>';

	echo '
		<script>
			window.addEventListener("DOMContentLoaded", function() {';

	if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && Utils::$context['can_remove_post'])
	{
		echo '
				var oInTopicModeration = new InTopicModeration({
					sCheckboxContainerMask: \'in_topic_mod_check_\',
					aMessageIds: [\'', implode('\', \'', Utils::$context['removableMessageIDs']), '\'],
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sButtonStrip: \'moderationbuttons\',
					sButtonStripDisplay: \'moderationbuttons_strip\',
					bUseImageButton: false,
					bCanRemove: ', Utils::$context['can_remove_post'] ? 'true' : 'false', ',
					sRemoveButtonLabel: \'', Lang::$txt['quickmod_delete_selected'], '\',
					sRemoveButtonImage: \'delete_selected.png\',
					sRemoveButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					bCanRestore: ', Utils::$context['can_restore_msg'] ? 'true' : 'false', ',
					sRestoreButtonLabel: \'', Lang::$txt['quick_mod_restore'], '\',
					sRestoreButtonImage: \'restore_selected.png\',
					sRestoreButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					bCanSplit: ', Utils::$context['can_split'] ? 'true' : 'false', ',
					sSplitButtonLabel: \'', Lang::$txt['quickmod_split_selected'], '\',
					sSplitButtonImage: \'split_selected.png\',
					sSplitButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					sFormId: \'quickModForm\'
				});';

		// Add it to the mobile button strip as well
		echo '
				var oInTopicModerationMobile = new InTopicModeration({
					sCheckboxContainerMask: \'in_topic_mod_check_\',
					aMessageIds: [\'', implode('\', \'', Utils::$context['removableMessageIDs']), '\'],
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sButtonStrip: \'moderationbuttons_mobile\',
					sButtonStripDisplay: \'moderationbuttons_strip_mobile\',
					bUseImageButton: false,
					bCanRemove: ', Utils::$context['can_remove_post'] ? 'true' : 'false', ',
					sRemoveButtonLabel: \'', Lang::$txt['quickmod_delete_selected'], '\',
					sRemoveButtonImage: \'delete_selected.png\',
					sRemoveButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					bCanRestore: ', Utils::$context['can_restore_msg'] ? 'true' : 'false', ',
					sRestoreButtonLabel: \'', Lang::$txt['quick_mod_restore'], '\',
					sRestoreButtonImage: \'restore_selected.png\',
					sRestoreButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					bCanSplit: ', Utils::$context['can_split'] ? 'true' : 'false', ',
					sSplitButtonLabel: \'', Lang::$txt['quickmod_split_selected'], '\',
					sSplitButtonImage: \'split_selected.png\',
					sSplitButtonConfirm: \'', Lang::$txt['quickmod_confirm'], '\',
					sFormId: \'quickModForm\'
				});';
	}

	echo '
				var oQuickModify = new QuickModify({
					sScriptUrl: smf_scripturl,
					sClassName: \'quick_edit\',
					bShowModify: ', Config::$modSettings['show_modify'] ? 'true' : 'false', ',
					iTopicId: ', Utils::$context['current_topic'], ',
					sSaveButtonText: ', Utils::escapeJavaScript(Lang::$txt['save']), ',
					sCancelButtonText: ', Utils::escapeJavaScript(Lang::$txt['modify_cancel']), ',
					sTemplateReasonEdit: ', Utils::escapeJavaScript(Lang::$txt['reason_for_edit']) . ',
					sErrorBorderStyle: ', Utils::escapeJavaScript('1px solid red'), '
				});

				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "display_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', Utils::$context['jump_to']['label'], '<" + "/label> %dropdown_list%",
					iCurBoardId: ', Utils::$context['current_board'], ',
					iCurBoardChildLevel: ', Utils::$context['jump_to']['child_level'], ',
					sCurBoardName: "', Utils::$context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "', Lang::getTxt('go', file: 'General'), '"
				});

				aIconLists[aIconLists.length] = new IconList({
					sBackReference: "aIconLists[" + aIconLists.length + "]",
					sIconIdPrefix: "msg_icon_",
					sScriptUrl: smf_scripturl,
					bShowModify: ', !empty(Config::$modSettings['show_modify']) ? 'true' : 'false', ',
					iBoardId: ', Utils::$context['current_board'], ',
					iTopicId: ', Utils::$context['current_topic'], ',
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sLabelIconList: "', Lang::getTxt('message_icon', file: 'General'), '",
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
				});';

	if (!empty(Utils::$context['ignoredMsgs']))
		echo '
				ignore_toggles([', implode(', ', Utils::$context['ignoredMsgs']), '], ', Utils::escapeJavaScript(Lang::$txt['show_ignore_user_post']), ');';

	echo '
			});
		</script>';
}

/**
 * Template for displaying a single post.
 *
 * @param array $message An array of information about the message to display. Should have 'id' and 'member'. Can also have 'first_new', 'is_ignored' and 'css_class'.
 */
function template_single_post($message)
{
	$ignoring = false;
	$show_subject = !empty(Config::$modSettings['subject_toggle']);
	$is_first_post = $message['id'] == Utils::$context['first_message'];

	if ($message['can_remove'])
		Utils::$context['removableMessageIDs'][] = $message['id'];

	// Are we ignoring this message?
	if (!empty($message['is_ignored']))
	{
		$ignoring = true;
		Utils::$context['ignoredMsgs'][] = $message['id'];
	}

	// Show a "new" anchor if this message is new.
	if (!empty($message['first_new']) && $message['id'] != Utils::$context['first_message']) {
		echo '
				<a id="new"></a>';
	}

	// Inform the reader if this message bumped an old topic.
	if (!empty($message['bump_notice'])) {
		echo '
				' . $message['bump_notice'];
	}

	// Show the message.
	echo '
				<article class="post_wrapper ', $message['css_class'], '" id="msg' . $message['id'] . '" aria-labelledby="msg_num_' . $message['id'] . '">
					<header class="keyinfo">
					', !$is_first_post ? '
					' . ($message['first_new'] ? '<a id="new"></a>' : '') : '';

	echo '
						<hgroup>';

	echo '
							<', $show_subject ? 'p' : 'h3', ' class="page_number" id="msg_num_', $message['id'], '">', $is_first_post ? Lang::$txt['first_post'] : (!empty($message['counter']) ? Lang::getTxt('reply_number_sr', [$message['counter']]) : ''), '</', $show_subject ? 'p' : 'h3', '>';

	// Some people don't want subject... The div is still required or quick edit breaks.
	echo '
							<div id="subject_', $message['id'], '" class="subject_title', (!$show_subject ? ' subject_hidden' : ''), '">
								<', $show_subject ? 'h3' : 'p', '><a href="', $message['href'], '" rel="bookmark nofollow">', $message['subject'], '</a></', $show_subject ? 'h3' : 'p', '>
							</div>
						</hgroup>';

	echo '
						<p class="postinfo">
							<span class="messageicon" aria-hidden="true"', ($message['icon_url'] === Theme::$current->settings['images_url'] . '/post/xx.png' && !$message['can_modify']) ? ' style="position: absolute; z-index: -1;"' : '', '>
								<img src="', $message['icon_url'], '" alt=""', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', '>
							</span>
							<span class="visually_hidden">', Lang::$txt['posted_on'], '</span>
							<a href="', $message['href'], '" rel="bookmark nofollow" title="', !empty($message['counter']) ? Lang::getTxt('reply_number', [$message['counter']]) : '', ' - ', $message['subject'], '"><time datetime="', date('Y-m-d\TH:i:s\Z', $message['timestamp']), '">', $message['time'], '</time></a>
						</p>';

	// Show "<< Last Edit: Time by Person >>" if this post was edited. But we need the div even if it wasn't modified!
	// Because we insert into it through AJAX and we don't want to stop themers moving it around if they so wish so they can put it where they want it.
	echo '
						<p class="smalltext modified', !empty(Config::$modSettings['show_modify']) && !empty($message['modified']['name']) ? ' mvisible' : '', '" id="modified_', $message['id'], '">';

	if (!empty(Config::$modSettings['show_modify']) && !empty($message['modified']['name']))
		echo
							$message['modified']['last_edit_text'];

	echo '
						</p>
						<div id="msg_', $message['id'], '_quick_mod"', $ignoring ? ' style="display:none;"' : '', '></div>';

	if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == User::$me->id)
		echo '
						<p class="noticebox">
							', Lang::$txt['post_awaiting_approval'], '
						</p>';

	echo '
					</header><!-- .keyinfo -->';

	// Show information about the poster of this message.
	echo '
						<footer class="poster">';

	// Are there any custom fields above the member name?
	if (!empty($message['custom_fields']['above_member']))
	{
		echo '
							<il class="custom_fields_above_member">';

		foreach ($message['custom_fields']['above_member'] as $custom)
			echo '
								<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

		echo '
							</ul>';
	}

	echo '
							<strong>';

	// Show online and offline buttons?
	if (!empty(Config::$modSettings['onlineEnable']) && !$message['member']['is_guest'])
		echo '
								', Utils::$context['can_send_pm'] ? '<a href="' . $message['member']['online']['href'] . '" title="' . $message['member']['online']['label'] . '">' : '', '<span class="' . ($message['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $message['member']['online']['text'] . '"></span>', Utils::$context['can_send_pm'] ? '</a>' : '';

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
							</strong>
							<ul class="user_info">';

	// Show the member's custom title, if they have one.
	if (!empty($message['member']['title']))
		echo '
							<div class="title">', $message['member']['title'], '</div>';

	// Show the member's primary group (like 'Administrator') if they have one.
	if (!empty($message['member']['group']))
		echo '
							<div class="membergroup">', $message['member']['group'], '</div>';

	// Show the user's avatar.
	if (!empty(Config::$modSettings['show_user_images']) && empty(Theme::$current->options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
		echo '
							<div class="avatar">
								<a href="', $message['member']['href'], '">', $message['member']['avatar']['image'], '</a>
							</div>';

	// Are there any custom fields below the avatar?
	if (!empty($message['custom_fields']['below_avatar']))
		foreach ($message['custom_fields']['below_avatar'] as $custom)
			echo '
							<div class="custom ', $custom['col_name'], '">', $custom['value'], '</div>';

	// Don't show these things for guests.
	if (!$message['member']['is_guest'])
	{
		// Show the post group icons
		echo '
							<div class="icons">', $message['member']['group_icons'], '</div>';

		// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
		if ((empty(Config::$modSettings['hide_post_group']) || empty($message['member']['group'])) && !empty($message['member']['post_group']))
			echo '
							<div class="postgroup">', $message['member']['post_group'], '</div>';

		// Show how many posts they have made.
		if (!isset(Utils::$context['disabled_fields']['posts']))
			echo '
							<div class="postcount">', Lang::getTxt('member_postcount_num', [$message['member']['posts']]), '</div>';

		// Show their personal text?
		if (!empty(Config::$modSettings['show_blurb']) && !empty($message['member']['blurb']))
			echo '
							<div class="blurb">', $message['member']['blurb'], '</div>';

		// Any custom fields to show as icons?
		if (!empty($message['custom_fields']['icons']))
		{
			echo '
							<ol class="im_icons">';

			foreach ($message['custom_fields']['icons'] as $custom)
				echo '
								<li class="custom ', $custom['col_name'], '">', $custom['value'], '</li>';

			echo '
							</ol>';
		}

		// Show the website and email address buttons.
		if ($message['member']['show_profile_buttons'])
		{
			echo '
							<ol class="profile_icons">';

			// Don't show an icon if they haven't specified a website.
			if (!empty($message['member']['website']['url']) && !isset(Utils::$context['disabled_fields']['website']))
				echo '
								<li><a href="', $message['member']['website']['url'], '" title="' . $message['member']['website']['title'] . '" target="_blank" rel="noopener">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons www centericon" title="' . $message['member']['website']['title'] . '"></span>' : Lang::$txt['www']), '</a></li>';

			// Since we know this person isn't a guest, you *can* message them.
			if (Utils::$context['can_send_pm'])
				echo '
								<li><a href="', Config::$scripturl, '?action=pm;sa=send;u=', $message['member']['id'], '" title="', $message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline'], '">', Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons im_' . ($message['member']['online']['is_online'] ? 'on' : 'off') . ' centericon" title="' . ($message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline']) . '"></span> ' : ($message['member']['online']['is_online'] ? Lang::$txt['pm_online'] : Lang::$txt['pm_offline']), '</a></li>';

			// Show the email if necessary
			if (!empty($message['member']['email']) && $message['member']['show_email'])
				echo '
								<li class="email"><a href="mailto:' . $message['member']['email'] . '" rel="nofollow">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons mail centericon" title="' . Lang::$txt['email'] . '"></span>' : Lang::$txt['email']), '</a></li>';

			echo '
							</ol><!-- .profile_icons -->';
		}

		// Any custom fields for standard placement?
		if (!empty($message['custom_fields']['standard']))
			foreach ($message['custom_fields']['standard'] as $custom)
				echo '
							<div class="custom ', $custom['col_name'], '">', $custom['title'], ': ', $custom['value'], '</div>';
	}
	// Otherwise, show the guest's email.
	elseif (!empty($message['member']['email']) && $message['member']['show_email'])
		echo '
							<div class="email">
								<a href="mailto:' . $message['member']['email'] . '" rel="nofollow">', (Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons mail centericon" title="' . Lang::$txt['email'] . '"></span>' : Lang::$txt['email']), '</a>
							</div>';

	// Show the IP to this user for this post - because you can moderate?
	if (!empty(Utils::$context['can_moderate_forum']) && !empty($message['member']['ip']))
		echo '
							<div class="poster_ip">
								<a href="', Config::$scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '" data-hover="', $message['member']['ip'], '" class="show_on_hover"><span>', Lang::$txt['show_ip'], '</span></a> <a href="', Config::$scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>
							</div>';

	// Or, should we show it because this is you?
	elseif ($message['can_see_ip'])
		echo '
							<div class="poster_ip">
								<a href="', Config::$scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help show_on_hover" data-hover="', $message['member']['ip'], '"><span>', Lang::$txt['show_ip'], '</span></a>
							</div>';

	// Okay, are you at least logged in? Then we can show something about why IPs are logged...
	elseif (!User::$me->is_guest)
		echo '
							<div class="poster_ip">
									<a href="', Config::$scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', Lang::$txt['logged'], '</a>
							</div>';

	// Otherwise, you see NOTHING!
	else
		echo '
							<div class="poster_ip">', Lang::$txt['logged'], '</div>';

	// Are we showing the warning status?
	// Don't show these things for guests.
	if (!$message['member']['is_guest'] && $message['member']['can_see_warning'])
		echo '
							<div class="warning">
								', Utils::$context['can_issue_warning'] ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '', '<span class="main_icons warning_', $message['member']['warning_status'], '"></span> ', Utils::$context['can_issue_warning'] ? '</a>' : '', '<span class="warn_', $message['member']['warning_status'], '">', Lang::$txt['warn_' . $message['member']['warning_status']], '</span>
							</div>';

	// Are there any custom fields to show at the bottom of the poster info?
	if (!empty($message['custom_fields']['bottom_poster']))
		foreach ($message['custom_fields']['bottom_poster'] as $custom)
			echo '
							<div class="custom ', $custom['col_name'], '">', $custom['value'], '</div>';

	// Poster info ends.
	echo '
							</ul>
						</footer><!-- .poster -->
						<div class="postarea">';

	// Ignoring this user? Hide the post.
	if ($ignoring)
		echo '
							<aside id="msg_', $message['id'], '_ignored_prompt" class="noticebox">
								', Lang::$txt['ignoring_user'], '
								<a href="#" id="msg_', $message['id'], '_ignored_link" style="display: none;" role="button">', Lang::$txt['show_ignore_user_post'], '</a>
							</aside>';

	// Show the post itself, finally!
	echo '
							<div class="post">';

	echo '
								<div class="inner" data-msgid="', $message['id'], '" id="msg_', $message['id'], '"', $ignoring ? ' style="display:none;"' : '', '>
									', Utils::adjustHeadingLevels($message['body'], 4), '
								</div>
							</div><!-- .post -->';

	// Assuming there are attachments...
	if (!empty($message['attachment']))
	{
		$last_approved_state = 1;
		// Don't output the div unless we actually have something to show...
		$div_output = false;

		foreach ($message['attachment'] as $attachment)
		{
			// Do we want this attachment to not be showed here?
			if ($attachment['is_approved'] && !empty(Config::$modSettings['dont_show_attach_under_post']) && !empty(Utils::$context['show_attach_under_post'][$attachment['id']]))
				continue;
			elseif (!$div_output)
			{
				$div_output = true;

				echo '
							<section id="msg_', $message['id'], '_attachments" class="attachments"', $ignoring ? ' style="display:none;"' : '', ' aria-label="', Lang::$txt['attachments'], '">';
			}

			// Show a special box for unapproved attachments...
			if ($attachment['is_approved'] != $last_approved_state)
			{
				$last_approved_state = 0;
				echo '
								<fieldset>
									<legend>
										', Lang::getTxt('attach_awaiting_approve', file: 'General');

				if (Utils::$context['can_approve'])
					echo '
										&nbsp;[<a href="', Config::$scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('approve_all', file: 'General'), '</a>]';

				echo '
									</legend>';
			}

			echo '
									<div class="attached">';

			if ($attachment['is_image'] && !empty(Config::$modSettings['attachmentShowImages']))
			{
				echo '
										<div class="attachments_top">';

				if ($attachment['thumbnail']['has_thumb'])
					echo '
											<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" class="atc_img"></a>';
				else
					echo '
											<img src="' . $attachment['href'] . ';image" alt="" loading="lazy" class="atc_img">';

				echo '
										</div><!-- .attachments_top -->';
			}

			echo '
										<div class="attachments_bot">
											<a href="' . $attachment['href'] . '"><img src="' . Theme::$current->settings['images_url'] . '/icons/clip.png" class="centericon" alt="*">&nbsp;' . $attachment['name'] . '</a> ';

			if (!$attachment['is_approved'] && Utils::$context['can_approve'])
				echo '
											[<a href="', Config::$scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('approve', file: 'General'), '</a>] [<a href="', Config::$scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('delete', file: 'General'), '</a>] ';
			echo '
											<br>', $attachment['formatted_size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . '<br>' . Lang::getTxt('attach_viewed', [$attachment['downloads']], file: 'General') : '<br>' . Lang::getTxt('attach_downloaded', [$attachment['downloads']], file: 'General')), '
										</div><!-- .attachments_bot -->';

			echo '
									</div><!-- .attached -->';
		}

		// If we had unapproved attachments clean up.
		if ($last_approved_state == 0)
			echo '
								</fieldset>';

		// Only do this if we output a div above - otherwise it'll break things
		if ($div_output)
			echo '
							</section><!-- #msg_[id]_footer -->';
	}

	echo '
						</div><!-- .postarea -->
						<footer id="msg_', $message['id'], '_footer" class="post_footer">
							<div class="under_message">';

	// What about likes?
	if (!empty(Config::$modSettings['enable_likes']))
	{
		echo '
							<ul class="likes">';

		if (!empty($message['likes']['can_like']))
		{
			echo '
								<li class="smflikebutton" id="msg_', $message['id'], '_likes"', $ignoring ? ' style="display:none;"' : '', '>
									<a href="', Config::$scripturl, '?action=likes;ltype=msg;sa=like;like=', $message['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="msg_like"><span class="main_icons ', $message['likes']['you'] ? 'unlike' : 'like', '"></span> ', $message['likes']['you'] ? Lang::$txt['unlike'] : Lang::$txt['like'], '</a>
								</li>';
		}

		if (!empty($message['likes']['count']))
		{
			Utils::$context['some_likes'] = true;
			$count = $message['likes']['count'];
			$base = 'likes_count';

			if ($message['likes']['you'])
			{
				$base = 'you_' . $base;
				$count--;
			}

			echo '
								<li class="like_count smalltext">
									', Lang::getTxt($base, ['url' => Config::$scripturl . '?action=likes;sa=view;ltype=msg;like=' . $message['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], 'num' => $count]), '
								</li>';
		}

		echo '
							</ul>';
	}

	// Show the quickbuttons, for various operations on posts.
	template_quickbuttons($message['quickbuttons'], 'post');

	echo '
						</div><!-- .under_message -->
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
					</footer><!-- #msg_$message[id]_footer -->
				</article><!-- .post_wrapper $message[css_class] -->
				<hr class="post_separator">';
}

/**
 * The template for displaying the quick reply box.
 */
function template_quickreply()
{
	echo '
		<a id="quickreply_anchor"></a>
		<div class="tborder" id="quickreply">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('quick_reply', file: 'General'), '
				</h3>
			</div>
			<div id="quickreply_options">
				<div class="roundframe">';

	// Is the topic locked?
	if (Utils::$context['is_locked'])
		echo '
					<p class="alert smalltext">', Lang::getTxt('quick_reply_warning', file: 'General'), '</p>';

	// Show a warning if the topic is old
	if (!empty(Utils::$context['oldTopicError']))
		echo '
					<p class="alert smalltext">', Lang::getTxt('error_old_topic', [Config::$modSettings['oldTopicDays']], file: 'General'), '</p>';

	// Does the post need approval?
	if (!Utils::$context['can_reply_approved'])
		echo '
					<p><em>', Lang::getTxt('wait_for_approval', file: 'General'), '</em></p>';

	echo '
					<form action="', Config::$scripturl, '?board=', Utils::$context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);">
						<input type="hidden" name="topic" value="', Utils::$context['current_topic'], '">
						<input type="hidden" name="subject" value="', Utils::$context['response_prefix'], Utils::$context['subject'], '">
						<input type="hidden" name="icon" value="xx">
						<input type="hidden" name="from_qr" value="1">
						<input type="hidden" name="notify" value="', Utils::$context['is_marked_notify'] || !empty(Theme::$current->options['auto_notify']) ? '1' : '0', '">
						<input type="hidden" name="not_approved" value="', !Utils::$context['can_reply_approved'], '">
						<input type="hidden" name="goback" value="', empty(Theme::$current->options['return_to_post']) ? '0' : '1', '">
						<input type="hidden" name="last_msg" value="', Utils::$context['topic_last_message'], '">
						<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">';

	// Guests just need more.
	if (User::$me->is_guest)
	{
		echo '
						<dl id="post_header">
							<dt>
								', Lang::getTxt('name', file: 'General'), '
							</dt>
							<dd>
								<input type="text" name="guestname" size="25" value="', Utils::$context['name'], '" required>
							</dd>';

		if (empty(Config::$modSettings['guest_post_no_email']))
		{
			echo '
							<dt>
								', Lang::getTxt('email', file: 'General'), '
							</dt>
							<dd>
								<input type="email" name="email" size="25" value="', Utils::$context['email'], '" required>
							</dd>';
		}

		echo '
						</dl>';
	}

	template_control_richedit('quickReply', 'smileyBox_message', 'bbcBox_message');

	// Is visual verification enabled?
	if (Utils::$context['require_verification'])
		echo '
						<div class="post_verification">
							<strong>', Lang::getTxt('verification', file: 'General'), '</strong>
							', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
						</div>';

	// Finally, the submit buttons.
	echo '
						<span id="post_confirm_buttons">
							', template_control_richedit_buttons('quickReply'), '
						</span>';
	echo '
					</form>
				</div><!-- .roundframe -->
			</div><!-- #quickreply_options -->
		</div><!-- #quickreply -->
		<br class="clear">';

	if (Utils::$context['show_spellchecking'])
		echo '
		<form action="', Config::$scripturl, '?action=spellcheck" method="post" accept-charset="UTF-8" name="spell_form" id="spell_form" target="spellWindow">
			<input type="hidden" name="spellstring" value="">
		</form>';
}

?>