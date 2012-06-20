<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Let them know, if their report was a success!
	if ($context['report_sent'])
	{
		echo '
			<div class="infobox">
				', $txt['report_sent'], '
			</div>';
	}

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
			<a id="top"></a>
			<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a id="new"></a>' : '';

	// Is this topic also a poll?
	if ($context['is_poll'])
	{
		echo '
			<div id="poll">
				<div class="cat_bar">
					<h3 class="catbg">
						<span class="ie6_header floatleft"><img src="', $settings['images_url'], '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.png" alt="" class="icon" /> ', $txt['poll'], '</span>
					</h3>
				</div>
				<div class="windowbg">
					<span class="topslice"><span></span></span>
					<div class="content" id="poll_options">
						<h4 id="pollquestion">
							', $context['poll']['question'], '
						</h4>';

		// Are they not allowed to vote but allowed to view the options?
		if ($context['poll']['show_results'] || !$context['allow_vote'])
		{
			echo '
					<dl class="options">';

			// Show each option with its corresponding percentage bar.
			foreach ($context['poll']['options'] as $option)
			{
				echo '
						<dt class="middletext', $option['voted_this'] ? ' voted' : '', '">', $option['option'], '</dt>
						<dd class="middletext statsbar', $option['voted_this'] ? ' voted' : '', '">';

				if ($context['allow_poll_view'])
					echo '
							', $option['bar_ndt'], '
							<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

				echo '
						</dd>';
			}

			echo '
					</dl>';

			if ($context['allow_poll_view'])
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
							<ul class="reset options">';

			// Show each option with its button - a radio likely.
			foreach ($context['poll']['options'] as $option)
				echo '
								<li class="middletext">', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

			echo '
							</ul>
							<div class="submitbutton">
								<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							</div>
						</form>';
		}

		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
						<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';

		echo '
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
			<div id="pollmoderation">';

		// Build the poll moderation button array.
		$poll_buttons = array(
			'vote' => array('test' => 'allow_return_vote', 'text' => 'poll_return_vote', 'image' => 'poll_options.png', 'lang' => true, 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start']),
			'results' => array('test' => 'show_view_results_button', 'text' => 'poll_results', 'image' => 'poll_results.png', 'lang' => true, 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start'] . ';viewresults'),
			'change_vote' => array('test' => 'allow_change_vote', 'text' => 'poll_change_vote', 'image' => 'poll_change_vote.png', 'lang' => true, 'url' => $scripturl . '?action=vote;topic=' . $context['current_topic'] . '.' . $context['start'] . ';poll=' . $context['poll']['id'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			'lock' => array('test' => 'allow_lock_poll', 'text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'image' => 'poll_lock.png', 'lang' => true, 'url' => $scripturl . '?action=lockvoting;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			'edit' => array('test' => 'allow_edit_poll', 'text' => 'poll_edit', 'image' => 'poll_edit.png', 'lang' => true, 'url' => $scripturl . '?action=editpoll;topic=' . $context['current_topic'] . '.' . $context['start']),
			'remove_poll' => array('test' => 'can_remove_poll', 'text' => 'poll_remove', 'image' => 'admin_remove_poll.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . $txt['poll_remove_warn'] . '\');"', 'url' => $scripturl . '?action=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		template_button_strip($poll_buttons);

		echo '
			</div>';
	}

	// Does this topic have some events linked to it?
	if (!empty($context['linked_calendar_events']))
	{
		echo '
			<div class="linked_events">
				<div class="title_bar">
					<h3 class="titlebg headerpadding">', $txt['calendar_linked_events'], '</h3>
				</div>
				<div class="windowbg">
					<span class="topslice"><span></span></span>
					<div class="content">
						<ul class="reset">';

		foreach ($context['linked_calendar_events'] as $event)
			echo '
							<li>
								', ($event['can_edit'] ? '<a href="' . $event['modify_href'] . '"> <img src="' . $settings['images_url'] . '/icons/modify_small.png" alt="" title="' . $txt['modify'] . '" class="edit_event" /></a> ' : ''), '<strong>', $event['title'], '</strong>: ', $event['start_date'], ($event['start_date'] != $event['end_date'] ? ' - ' . $event['end_date'] : ''), '
							</li>';

		echo '
						</ul>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>';
	}

	// Build the normal button array.
	$normal_buttons = array(
		'print' => array('test' => 'can_print', 'text' => 'print', 'image' => 'print.png', 'lang' => true, 'custom' => 'rel="new_win nofollow"', 'url' => $scripturl . '?action=printpage;topic=' . $context['current_topic'] . '.0'),
		'send' => array('test' => 'can_send_topic', 'text' => 'send_topic', 'image' => 'sendtopic.png', 'lang' => true, 'url' => $scripturl . '?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.0'),
		'mark_unread' => array('test' => 'can_mark_unread', 'text' => 'mark_unread', 'image' => 'markunread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=topic;t=' . $context['mark_unread_time'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : '') . 'notify.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']) . '\');"', 'url' => $scripturl . '?action=notify;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'add_poll' => array('test' => 'can_add_poll', 'text' => 'add_poll', 'image' => 'add_poll.png', 'lang' => true, 'url' => $scripturl . '?action=editpoll;add;topic=' . $context['current_topic'] . '.' . $context['start']),
	);
	
	// Build the reply button array.
	$reply_button = array(
		'reply' => array('test' => 'can_reply', 'text' => 'reply', 'image' => 'reply.png', 'lang' => true, 'url' => $scripturl . '?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';last_msg=' . $context['topic_last_message'], 'active' => true),
	);

	// Allow adding new buttons easily.
	call_integration_hook('integrate_display_buttons', array(&$normal_buttons));

	// Show the topic information - icon, subject, etc.
	echo '
			<div id="forumposts">
				<div class="cat_bar">
					<h3 class="catbg">
						<img src="', $settings['images_url'], '/topic/', $context['class'], '.png" align="bottom" alt="" />', $txt['topic'], ':&nbsp;', $context['subject'], ' &nbsp;(', $context['num_views'], ' ', $txt['views'], ')
					</h3>
				</div>
				<div class="windowbg">
					<div class="title_bar" style="margin: 0 11px 10px 11px; border-radius: 4px; overflow: auto;">
						<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'],'</div>
						<div class="nextlinks_bottom floatright" style="padding: 4px 8px;">', $context['previous_next'], '</div>
					</div>
						', template_button_strip($normal_buttons, 'left'), '', template_button_strip($reply_button, 'right'), '
				</div><a href="#" id="skipnav_target"></a>';

	if (!empty($settings['display_who_viewing']))
	{
		echo '
				<p id="whoisviewing" class="smalltext flow_auto">';

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

	echo '
				<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';

	$ignoredMsgs = array();
	$removableMessageIDs = array();
	$alternate = false;

	// Get all the messages...
	while ($message = $context['get_message']())
	{
		$ignoring = false;
		$alternate = !$alternate;
		if ($message['can_remove'])
			$removableMessageIDs[] = $message['id'];

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
		{
			$ignoring = true;
			$ignoredMsgs[] = $message['id'];
		}

		// Show the message anchor and a "new" anchor if this message is new.
		if ($message['id'] != $context['first_message'])
			echo '
				<a id="msg', $message['id'], '"></a>', $message['first_new'] ? '<a id="new"></a>' : '';

		echo '
				<div class="', $message['approved'] ? ($message['alternate'] == 0 ? 'windowbg' : 'windowbg2') : 'approvebg', '">';
		// Ignore user?
		//if($ignoring)
		//	echo '
		//			<a href="', $scripturl, '?action=ignoreuser;u=', $message['member']['id'], ';rt=', $context['current_topic'], ';rm=', $message['id'], '" class="ignore_button floatleft" title="Don\'t do it. You\'ll be sorry.">Resume viewing</a>
		//			<div style="padding: 1em 1.7em 1em 15em;">You really do not want to see this post. This person drives you crazy.</div>';

		echo '
					<div class="post_wrapper"', $ignoring ? 'style="display:none;"' : '', '>';

		// Show information about the poster of this message.
		echo '
						<div class="poster">';

		// Show a link to the member's profile.
		echo '
								
							<ul class="reset smalltext" id="msg_', $message['id'], '_extra_info">';

		// Show the member's primary group (like 'Administrator') if they have one.
		//if (!empty($message['member']['group']))
		//	echo '
		//						<li class="membergroup">', $message['member']['group'], '</li>';

//			echo '
//								<li class="icons">', $message['member']['group_icons'], '</li>';

			// Show avatars, images, etc.?
			if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
			{
				echo '
								<li class="avatar">
									<a href="', $scripturl, '?action=profile;u=', $message['member']['id'], '" class="', $message['member']['online']['is_online'] ?'online':'offline','">
										', $message['member']['avatar']['image'], '
									</a>
									<ul class="reset">';
			// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
			if ((empty($settings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != '')
				echo '
										<li class="postgroup">', $message['member']['post_group'], '</li>';

			// Show how many posts they have made.
			if (!isset($context['disabled_fields']['posts']))
				echo '
										<li class="postcount">', $txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

			// Are we showing the warning status?
			if ($message['member']['can_see_warning'])
				echo '
										<li class="warning">', $context['can_issue_warning'] ? '<a href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '', '<img src="', $settings['images_url'], '/warning_', $message['member']['warning_status'], '.png" alt="', $txt['user_warn_' . $message['member']['warning_status']], '" /><span class="warn_', $message['member']['warning_status'], '">', $txt['warn_' . $message['member']['warning_status']], '</span>', $context['can_issue_warning'] ? '</a>' : '', '</li>';

			// Show the member's signature?
			if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
				echo '
										<li class="signature" id="msg_', $message['id'], '_signature"><hr />', $message['member']['signature'], '</li>';

				echo '
									</ul>
								</li>';
			}

		// Don't show these things for guests.
		if (!$message['member']['is_guest'])
		{
			// Show online and offline buttons?
			if (!empty($modSettings['onlineEnable']) && ($context['can_send_pm']))
			{
				if(!$message['is_message_author'])
				{				
				echo '
								<li class="online_button"><a href="', $scripturl,'?action=pm;sa=send;u=', $message['member']['id'], '" title="', $message['member']['online']['is_online'] ? $message['member']['name']. ' is online' : $message['member']['name']. ' is offline', '"><img src="', $message['member']['online']['image_href'], '" style="vertical-align: middle; margin-top: -0.1em;" alt="', $message['member']['online']['text'], '" />&nbsp;Send message</a></li>';
				}
				else
				{
				echo '
								<li class="online_button"><a href="', $scripturl,'?action=pm" title="Check my inbox">', $txt['pm_short'], ' ', $context['user']['unread_messages'] > 0 ? '[<strong>'. $context['user']['unread_messages'] . '</strong>]' : '' , '</a></li>';
				}
			}
		}

			echo '
								<li class="icons">', $message['member']['group_icons'], '</li>';

		// Show the member's primary group (like 'Administrator') if they have one.
		if (!empty($message['member']['group']))
			echo '
								<li class="membergroup">', $message['member']['group'], '</li>';
		// Show the post group if they have no other group or the option is on, and they are in a post group.
		elseif (($message['member']['group'] == '') && $message['member']['post_group'] != '')
				echo '
								<li class="membergroup">', $message['member']['post_group'], '</li>';

		// Show the member's custom title, if they have one.
		//if (!empty($message['member']['title']))
		//	echo '
		//						<li class="title">', $message['member']['title'], '</li>';

//		if(!$message['is_message_author'] && $context['user']['is_logged'])
//		{
//			echo'
//								<li class="floatleft" style="width: 58px; border-radius: 4px; margin: 0 0 0.4em 1.1em; padding: 0.2em;"><a href="', $scripturl, '?action=ignoreuser;u=', $message['member']['id'], ';rt=', $context['current_topic'], ';rm=', $message['id'], '">', $txt['ignore_link'], '</a></li>
//								<li class="floatright" style="width: 58px; border-radius: 4px; margin: 0 1.1em 0.4em 0; padding: 0.2em;"><a href="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $message['counter'], ';msg=', $message['id'], '" title="', $txt['report_to_mod'], '">Report</a></li>';
//		}

		// Done with the information about the poster... on to the post itself.
		echo '
							</ul>
						</div><!--/poster-->
						<div class="postarea">
								<div class="keyinfo">
									<div class="messageicon">
										<img src="', $message['icon_url'] . '" alt=""', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', ' />
									</div>
									<h5 id="subject_', $message['id'], '" class="smalltext">
										<a href="', $message['href'], '" rel="nofollow" class="topic_reply_title" title="', $context['subject'], '">', !empty($message['counter']) ? $txt['reply_noun'] . ' ' . $message['counter'] : 'Opening Post', '</a>&nbsp;by <strong>', $message['member']['link'], '</strong>', (!empty($message['member']['title'])) ? '&nbsp;('. $message['member']['title']. ')' : '','&nbsp;-&nbsp;<time>', $message['time'], '</time>
									</h5>
									<a href="#top_most" class="go_up"><img src="', $settings['images_url'], '/go_up.png" alt="', $txt['go_up'], '" /></a>
									<a href="#bottom_most" class="go_down"><img src="', $settings['images_url'], '/go_down.png" alt="', $txt['go_down'], '" /></a>
										<div id="msg_', $message['id'], '_quick_mod"></div>
								</div>';
						// Show "� Last Edit: Time by Person �" if this post was edited.
						if ($settings['show_modify'] && !empty($message['modified']['name']))
							echo '
								<div class="smalltext" style="padding: 2px 0 0 10px; margin: 0 0 -4px 125px;">
									<em>', $txt['last_edit'], ': ', $message['modified']['time'], ' ', $txt['by'], ' ', $message['modified']['name'], '</em>
								</div>';

		// Show the post itself, finally!
		echo '
							<div class="post" style="',$ignoring?'display:none;':'','"><hr />';

		if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
			echo '
								<div class="approve_post">
									', $txt['post_awaiting_approval'], '
								</div>';
		echo '
								<div class="inner" id="msg_', $message['id'], '"', ' style="padding-bottom: 0.6em;">', $message['body'], '</div>';

		// Assuming there are attachments...
		if (!empty($message['attachment']))
		{
			echo '
							<div id="msg_', $message['id'], '_footer" class="attachments smalltext">
								<div style="overflow: ', isBrowser('is_firefox') ? 'visible' : 'auto', ';">';

			$last_approved_state = 1;
			$attachments_per_line = 4;
			$i = 0;
			
			foreach ($message['attachment'] as $attachment)
			{
				// Show a special box for unapproved attachments...
				if ($attachment['is_approved'] != $last_approved_state)
				{
					$last_approved_state = 0;
					echo '
									<fieldset>
										<legend>', $txt['attach_awaiting_approve'];

					if ($context['can_approve'])
						echo '
										&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>]';

					echo '
										</legend>';
				}
				
				echo '
									<div class="floatleft padding">
										<div class="attachments_top">';

				if ($attachment['is_image'])
				{
					if ($attachment['thumbnail']['has_thumb'])
						echo '
										<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" /></a><br />';
					else
						echo '
										<img src="' . $attachment['href'] . ';image" alt="" width="' . $attachment['width'] . '" height="' . $attachment['height'] . '"/><br />';
				}
				
				echo '
										</div>
										<div class="attachments_bot">
											<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.png" class="centericon" alt="*" />&nbsp;' . $attachment['name'] . '</a> ';

				if (!$attachment['is_approved'] && $context['can_approve'])
					echo '
										[<a href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>]&nbsp;|&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>] ';
				echo '
											<br />', $attachment['size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . '<br />' . $txt['attach_viewed'] : '<br />' . $txt['attach_downloaded']) . ' ' . $attachment['downloads'] . ' ' . $txt['attach_times'] . '
										</div>';
				
				echo '
									</div>';
				
				// Next attachment line ?
				if (++$i % $attachments_per_line === 0)
					echo '
									<br class="clear" />';
			}

			// no more attachments, clear the float if its open
			if ($i % $attachments_per_line !== 0)
				echo '
									<br class="clear" />';
			
			// If we had unapproved attachments clean up.
			if ($last_approved_state == 0)
				echo '
									</fieldset>';

			echo '
								</div>
							</div>';
		}
		
		echo '
			</div>';

		if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_restore_msg'])
			echo '
								<ul class="reset quickbuttons">';

			// Show their personal text?
			if (!empty($settings['show_blurb']) && $message['member']['blurb'] != '')
				echo '
									<li class="blurb">', $message['member']['blurb'], '</li>';

		// Can they reply? Have they turned on quick reply?
		//if ($context['can_quote'] && !empty($options['display_quick_reply']))
		//	echo '
		//							<li><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" title="', $txt['quote'], '&nbsp;', $message['member']['name'], '\'s post" onclick="return oQuickReply.quote(', $message['id'], ');" class="quote_button">', $txt['quote'], '</a></li>';

		// So... quick reply is off, but they *can* reply?
		//elseif ($context['can_quote'])
		//	echo '
		//							<li><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" title="', $txt['quote'], '&nbsp;', $message['member']['name'], '\'s post" class="quote_button">', $txt['quote'], '</a></li>';

		// Can the user modify the contents of this post?
		if ($message['can_modify'])
			echo '
									<li><a href="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" title="', $txt['fulledit'], '" class="modify_button">', $txt['fulledit'], '</a></li>';

		// Can the user modify the contents of this post?  Show the modify inline image.
		if ($message['can_modify'])
			echo '
									<li><a href="#" class="modify_inline" title="', $txt['quick_edit'], '">', $txt['edit'], '<img src="', $settings['images_url'], '/icons/modify_inline.png" alt="', $txt['modify_msg'], '" title="', $txt['quick_edit'] ,'" id="modify_button_', $message['id'], '" style="cursor: pointer; display: none;" onclick="oQuickModify.modifyMsg(\'', $message['id'], '\')" /></a></li>';

		if ($context['can_moderate_forum'])
		{
			echo '
									<li class="moderation_button"><a href="#">', $txt['post_options'], '</a>
										<ul>';

									// Maybe they want to report this post to the moderator(s)?
									if ($context['can_report_moderator'])
										echo '
											<li><a href="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $message['counter'], ';msg=', $message['id'], '">', $txt['report_to_mod'], '</a></li>';
										echo '
											<li><a href="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="remove_button">', $txt['remove'], '</a></li>
											<li><a href="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '" class="split_button">', $txt['split'], '</a></li>';
									
									// Maybe we can approve it, maybe we should?
									if ($message['can_approve'])
										echo '
											<li><a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="approve_button">', $txt['approve'], '</a></li>';
									
									// Can we restore topics?
									if ($context['can_restore_msg'])
										echo '
											<li><a href="', $scripturl, '?action=restoretopic;msgs=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="restore_button">', $txt['restore_message'], '</a></li>';
									
									// Can we issue a warning because of this post?  Remember, we can't give guests warnings.
									if ($context['can_issue_warning'] && !$message['is_message_author'] && !$message['member']['is_guest'])
										echo '
											<li><a href="', $scripturl, '?action=profile;area=issuewarning;u=', $message['member']['id'], ';msg=', $message['id'], '" title="', $txt['issue_warning_post'], '" class="issue_warning">Issue warning</a></li>';
									
									// Show a checkbox for quick moderation?
									if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $message['can_remove'])
										echo '
											<li class="inline_mod_check" style="display: none;" id="in_topic_mod_check_', $message['id'], '"><label for="in_topic_mod_check_', $message['id'], '">Select post&nbsp;</label></li>';
										echo '
											<li><a href="', $scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '" class="ip_link">', $message['member']['ip'], '</a></li>
										</ul>
									</li>';
		}

		// Can they reply? Have they turned on quick reply?
		if ($context['can_quote'] && !empty($options['display_quick_reply']))
			echo '
									<li><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" title="', $txt['quote'], '&nbsp;', $message['member']['name'], '\'s post" onclick="return oQuickReply.quote(', $message['id'], ');" class="quote_button">', $txt['quote'], '</a></li>';

		// So... quick reply is off, but they *can* reply?
		elseif ($context['can_quote'])
			echo '
									<li><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" title="', $txt['quote'], '&nbsp;', $message['member']['name'], '\'s post" class="quote_button">', $txt['quote'], '</a></li>';

		if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_restore_msg'])
			echo '
								</ul>';

//		// Show the member's signature?
//		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
//			echo '
//							<div class="signature" id="msg_', $message['id'], '_signature"><hr />', $message['member']['signature'], '</div>';

		echo '
						</div><!--/postarea-->
					</div><!--/post_wrapper-->
				</div>
				<hr class="post_separator" />';
	}

	echo '
				</form>
			</div>
			<a id="lastPost"></a>';

	$mod_buttons = array(
		'move' => array('test' => 'can_move', 'text' => 'move_topic', 'image' => 'admin_move.png', 'lang' => true, 'url' => $scripturl . '?action=movetopic;current_board=' . $context['current_board'] . ';topic=' . $context['current_topic'] . '.0'),
		'delete' => array('test' => 'can_delete', 'text' => 'remove_topic', 'image' => 'admin_rem.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . $txt['are_sure_remove_topic'] . '\');"', 'url' => $scripturl . '?action=removetopic2;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
		'lock' => array('test' => 'can_lock', 'text' => empty($context['is_locked']) ? 'set_lock' : 'set_unlock', 'image' => 'admin_lock.png', 'lang' => true, 'url' => $scripturl . '?action=lock;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'sticky' => array('test' => 'can_sticky', 'text' => empty($context['is_sticky']) ? 'set_sticky' : 'set_nonsticky', 'image' => 'admin_sticky.png', 'lang' => true, 'url' => $scripturl . '?action=sticky;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'merge' => array('test' => 'can_merge', 'text' => 'merge', 'image' => 'merge.png', 'lang' => true, 'url' => $scripturl . '?action=mergetopics;board=' . $context['current_board'] . '.0;from=' . $context['current_topic']),
		'calendar' => array('test' => 'calendar_post', 'text' => 'calendar_link', 'image' => 'linktocal.png', 'lang' => true, 'url' => $scripturl . '?action=post;calendar;msg=' . $context['topic_first_message'] . ';topic=' . $context['current_topic'] . '.0'),
	);

	// Restore topic. eh?  No monkey business.
	if ($context['can_restore_topic'])
		$mod_buttons[] = array('text' => 'restore_topic', 'image' => '', 'lang' => true, 'url' => $scripturl . '?action=restoretopic;topics=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id']);

	// Allow adding new mod buttons easily.
	call_integration_hook('integrate_mod_buttons', array(&$mod_buttons));

	echo '
			<div class="windowbg" id="display_lower_buttons">
				', template_button_strip($normal_buttons, 'left'), '', template_button_strip($reply_button, 'right'), '
				<div id="moderationbuttons">', template_button_strip($mod_buttons, 'bottom', array('id' => 'moderationbuttons_strip')), '</div>
				<div class="title_bar" style="margin: 10px 11px 10px 11px; border-radius: 4px; clear: both;">
					<div class="pagelinks floatleft" style="font-weight: bold;">', $txt['pages'], ': ', $context['page_index'],'</div>
					<div class="nextlinks_bottom floatright" style="padding: 4px 8px;">', $context['previous_next'], '</div>
				</div>';

	// Show the page index... "Pages: [1]".

	// Show the jumpto box, or actually...let Javascript do it.
	echo '
				<div class="navigate_section">',theme_linktree(),'</div>
				<div class="title_bar" style="margin: 10px 11px 0 11px; border-radius: 4px;">
					<div class="nextlinks_bottom floatleft" style="display: none; margin-bottom: 0; clear: none; padding: 4px 8px;">', $context['previous_next'], '</div>
					<div class="floatright" style="margin-top: 0; clear: none;" id="display_jump_to">&nbsp;</div>
				</div>
			</div><br class="clear" />';
			
	// Show the lower breadcrumbs.

	if ($context['can_reply'] && !empty($options['display_quick_reply']))
	{
		echo '
			<a id="quickreply"></a><br />
			<div class="tborder" id="quickreplybox">
				<div class="cat_bar">
					<h3 class="catbg">
						<a href="javascript:oQuickReply.swap();" id="QuickReply_swap">
							<img src="', $settings['images_url'], '/', $options['display_quick_reply'] > 1 ? 'upshrink' : 'upshrink2', '.png" alt="+" id="quickReplyExpand" class="icon floatright" />
						</a>
						<a href="javascript:oQuickReply.swap();">', $txt['quick_reply'], '</a>
					</h3>
				</div>
				<div id="quickReplyOptions"', $options['display_quick_reply'] > 1 ? '' : ' style="display: none"', '>
					<div class="roundframe">
						<p class="smalltext lefttext">', $txt['quick_reply_desc'], '</p>
						', $context['is_locked'] ? '<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '',
						$context['oldTopicError'] ? '<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']) . '</p>' : '', '
						', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
						', !$context['can_reply_approved'] && $context['require_verification'] ? '<br />' : '', '
						<form action="', $scripturl, '?board=', $context['current_board'], ';action=post2" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" class="flow_auto" onsubmit="submitonce(this);">
							<input type="hidden" name="topic" value="', $context['current_topic'], '" />
							<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '" />
							<input type="hidden" name="icon" value="xx" />
							<input type="hidden" name="from_qr" value="1" />
							<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
							<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '" />
							<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '" />
							<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />';

		// Guests just need more.
		if ($context['user']['is_guest'])
			echo '
							<strong>', $txt['name'], ':</strong> <input type="text" name="guestname" value="', $context['name'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
							<strong>', $txt['email'], ':</strong> <input type="text" name="email" value="', $context['email'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" /><br />';

		// Is visual verification enabled?
		if ($context['require_verification'])
			echo '
							<strong>', $txt['verification'], ':</strong>', template_control_verification($context['visual_verification_id'], 'quick_reply'), '<br />';

		if ($options['display_quick_reply'] < 3)
		{
			echo '
							<div class="quickReplyContent">
								<textarea class ="expand_test" cols="" rows="" name="message" tabindex="', $context['tabindex']++, '"></textarea>
							</div>';
		}
		else
		{
			// Show the actual posting area...
			if ($context['show_bbc'])
			{
				echo '
								<div id="bbcBox_message"></div>';
			}

			// What about smileys?
			if (!empty($context['smileys']['postform']) || !empty($context['smileys']['popup']))
				echo '
								<div id="smileyBox_message"></div>';

			echo '
							', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
								<script type="text/javascript"><!-- // --><![CDATA[
									function insertQuoteFast(messageid)
									{
										if (window.XMLHttpRequest)
											getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';xml;pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), onDocReceived);
										else
											reqWin(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), 240, 90);
										return false;
									}
									function onDocReceived(XMLDoc)
									{
										var text = \'\';
										for (var i = 0, n = XMLDoc.getElementsByTagName(\'quote\')[0].childNodes.length; i < n; i++)
											text += XMLDoc.getElementsByTagName(\'quote\')[0].childNodes[i].nodeValue;
										oEditorHandle_', $context['post_box_name'], '.insertText(text, false, true);

										ajax_indicator(false);
									}
								// ]]></script>';

		}
		echo '
							<div class="padding">
								<input type="submit" name="post" value="', $txt['post'], '" onclick="return submitThisOnce(this);" accesskey="s" tabindex="', $context['tabindex']++, '" class="button_submit" />
								<input type="submit" name="preview" value="', $txt['preview'], '" onclick="return submitThisOnce(this);" accesskey="p" tabindex="', $context['tabindex']++, '" class="button_submit" />
							</div>';

			if ($context['show_spellchecking'])
				echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'postmodify\', \'message\');" tabindex="', $context['tabindex']++, '" class="button_submit" />';

			echo '
						</form>
					</div>
				</div>
			</div>';
	}
	else
		echo '
		<br class="clear" />';

	if ($context['show_spellchecking'])
		echo '
			<form action="', $scripturl, '?action=spellcheck" method="post" accept-charset="', $context['character_set'], '" name="spell_form" id="spell_form" target="spellWindow"><input type="hidden" name="spellstring" value="" /></form>
				<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';

	echo '
				<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/topic.js"></script>
				<script type="text/javascript"><!-- // --><![CDATA[';

	if (!empty($options['display_quick_reply']))
		echo '
					var oQuickReply = new QuickReply({
						bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] > 1 ? 'false' : 'true', ',
						iTopicId: ', $context['current_topic'], ',
						iStart: ', $context['start'], ',
						sScriptUrl: smf_scripturl,
						sImagesUrl: smf_images_url,
						sContainerId: "quickReplyOptions",
						sImageId: "quickReplyExpand",
						sImageCollapsed: "upshrink.png",
						sImageExpanded: "upshrink2.png",
						sJumpAnchor: "quickreply",
						bIsFull: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] > 2 ? 'true' : 'false', '
					});';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
		echo '
					var oInTopicModeration = new InTopicModeration({
						sSelf: \'oInTopicModeration\',
						sCheckboxContainerMask: \'in_topic_mod_check_\',
						aMessageIds: [\'', implode('\', \'', $removableMessageIDs), '\'],
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

	echo '
					if (\'XMLHttpRequest\' in window)
					{
						var oQuickModify = new QuickModify({
							sScriptUrl: smf_scripturl,
							bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
							iTopicId: ', $context['current_topic'], ',
							sTemplateBodyEdit: ', JavaScriptEscape('
								<div id="quick_edit_body_container" style="width: 90%">
									<div id="error_box" style="padding: 4px;" class="error"></div>
									<textarea class="editor" name="message" rows="12" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 100%; min-width: 100%' : 'width: 100%') . '; margin-bottom: 10px;" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br />
									<input type="hidden" name="\' + smf_session_var + \'" value="\' + smf_session_id + \'" />
									<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
									<input type="hidden" name="msg" value="%msg_id%" />
									<div class="righttext">
										<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="button_submit" />&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="button_submit" />&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="button_submit" />
									</div>
								</div>'), ',
							sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" style="width: 90%;" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="input_text" />'), ',
							sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
							sTemplateSubjectNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow" class="topic_reply_title" title="%subject%"><strong>', !empty($message['counter']) ? $txt['reply_noun'] . ' ' . $message['counter'] : 'Opening Post', ':</a></strong><time> ', $message['time'], '</time>'), ',
							sTemplateTopSubject: ', JavaScriptEscape($txt['topic'] . ':&nbsp; %subject% &nbsp;(', $context['num_views'], ' ', $txt['views'], ')'), ',
							sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), '
						});

						aJumpTo[aJumpTo.length] = new JumpTo({
							sContainerId: "display_jump_to",
							sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
							iCurBoardId: ', $context['current_board'], ',
							iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
							sCurBoardName: "', $context['jump_to']['board_name'], '",
							sBoardChildLevelIndicator: "->",
							sBoardPrefix: "",
							sCatSeparator: "-----------------------------",
							sCatPrefix: "",
							sGoButtonLabel: "', $txt['go'], '"
						});

						aIconLists[aIconLists.length] = new IconList({
							sBackReference: "aIconLists[" + aIconLists.length + "]",
							sIconIdPrefix: "msg_icon_",
							sScriptUrl: smf_scripturl,
							bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
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

	if (!empty($ignoredMsgs))
	{
		echo '
					var aIgnoreToggles = new Array();';

		foreach ($ignoredMsgs as $msgid)
		{
			echo '
					aIgnoreToggles[', $msgid, '] = new smc_Toggle({
						bToggleEnabled: true,
						bCurrentlyCollapsed: true,
						aSwappableContainers: [
							\'msg_', $msgid, '_extra_info\',
							\'msg_', $msgid, '\',
							\'msg_', $msgid, '_footer\',
							\'msg_', $msgid, '_quick_mod\',
							\'modify_button_', $msgid, '\',
							\'msg_', $msgid, '_signature\'
						],
						aSwapLinks: [
							{
								sId: \'msg_', $msgid, '_ignored_link\',
								msgExpanded: \'\',
								msgCollapsed: ', JavaScriptEscape($txt['show_ignore_user_post']), '
							}
						]
					});';
		}
	}

	echo '
		$(document).ready(function(){
			$("li a.quote_button, li a.modify_button, li a.modify_inline, a.topic_reply_title").bt(jQuery.bt.options = {positions: "top, bottom"});
		});

		// ]]></script>';
}

?>