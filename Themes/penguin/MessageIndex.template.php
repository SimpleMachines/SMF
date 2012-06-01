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
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
	<a id="top"></a>';

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div class="tborder childboards" id="board_', $context['current_board'], '_childboards">
		<div class="cat_bar">
			<h3 class="catbg"><span class="floatleft">', $txt['parent_boards'], '</span><img class="toggle" id="Child_swap" src="', $settings['images_url'], '/collapse.png" alt="*" title="', $txt['upshrink_description'], '" /></h3>
		</div>
		<div class="table_frame">
			<table class="table_list">
				<tbody id="board_', $context['current_board'], '_children" class="content">';

		foreach ($context['boards'] as $board)
		{
			echo '
				<tr id="board_', $board['id'], '" class="windowbg2">
					<td class="icon"', !empty($board['children']) ? ' rowspan="2"' : '', '>
						<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

			// If the board or children is new, show an indicator.
			if ($board['new'] || $board['children_new'])
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" />';
			// Is it a redirection board?
			elseif ($board['is_redirect'])
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
			// No new posts at all! The agony!!
			else
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

			echo '
						</a>
					</td>
					<td class="info">
						<a class="subject" href="', $board['href'], '" name="b', $board['id'], '">', $board['name'], '</a>';

			// Has it outstanding posts for approval?
			if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
				echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

			echo '

						<p>', $board['description'] , '</p>';

			// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
			if (!empty($board['moderators']))
				echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

			// Show some basic information about the number of posts, etc.
			echo '
					</td>
					<td class="windowbg stats">
						<p>', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], ' <br />
						', $board['is_redirect'] ? '' : comma_format($board['topics']) . ' ' . $txt['board_topics'], '
						</p>
					</td>
					<td class="lastpost">';

			/* The board's and children's 'last_post's have:
			time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
			link, href, subject, start (where they should go for the first unread post.),
			and member. (which has id, name, link, href, username in it.) */
			if (!empty($board['last_post']['id']))
				echo '
						<p><strong>', $txt['last_post'], '</strong>  ', $txt['by'], ' ', $board['last_post']['member']['link'], '<br />
						', $txt['in'], ' ', $board['last_post']['link'], '<br />
						', $txt['on'], ' ', $board['last_post']['time'],'
						</p>';

			echo '
					</td>
				</tr>';

			// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
			if (!empty($board['children']))
			{
				// Sort the links into an array with new boards bold so it can be imploded.
				$children = array();
				/* Each child in each board's children has:
						id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
				foreach ($board['children'] as $child)
				{
					if (!$child['is_redirect'])
						$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a  ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
					else
						$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

					// Has it posts awaiting approval?
					if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
						$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

					$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
				}
				echo '
				<tr id="board_', $board['id'], '_children"><td colspan="3" class="children windowbg"><strong>', $txt['parent_boards'], '</strong>: ', implode(', ', $children), '</td></tr>';
			}
		}
		echo '
				</tbody>
			</table>
		</div>
	</div>';
	}

	if (!empty($options['show_board_desc']) && $context['description'] != '')
		echo '
	<p class="description_board">', $context['description'], '</p>';

	// Create the button set...
	$normal_buttons = array(
		'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'active' => true),
		'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
		'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : ''). 'notify.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'markread' => array('text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
	);

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'] || !$settings['show_mark_read'])
		unset($normal_buttons['markread']);

	// Allow adding new buttons easily.
	call_integration_hook('integrate_messageindex_buttons', array(&$normal_buttons));

	if (!$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">
		<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a>' : '', '</div>
		', template_button_strip($normal_buttons, 'right'), '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" class="clear" name="quickModForm" id="quickModForm">';

		echo '
	<div class="tborder topic_table" id="messageindex">
		<table class="table_grid" cellspacing="0">
			<thead>
				<tr class="catbg">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<th scope="col" class="first_th" ', !empty($modSettings['enableParticipation'])?'colspan="2"':'colspan="1"', '>&nbsp;</th>
					<th scope="col" class="lefttext" style="padding-left: 32px;"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a> / <a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'], $context['sort_by'] == 'starter' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a></th>
					<th scope="col" class="righttext ', (empty($context['can_quick_mod'])) ? 'last_th' : '', '" style="padding-right: 36px;">
						<a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a> / 
						<a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a> / 
						<a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
					</th>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
					<th scope="col" class="last_th lefttext" width="24"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" /></th>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['can_quick_mod']))
				echo '
					<th class="last_th" width="60">&nbsp;</th>';
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
					<th scope="col" class="first_th" width="8%">&nbsp;</th>
					<th colspan="3"><strong>', $txt['msg_alert_none'], '</strong></th>
					<th scope="col" class="last_th" width="8%">&nbsp;</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		if (!empty($settings['display_who_viewing']))
		{
			echo '
				<tr class="windowbg2 whos_viewing">
					<td colspan="', !empty($context['can_quick_mod']) ? '6' : '5', '" class="smalltext stickybg2" style="font-size: 0.917em; border-bottom: 2px solid #ddd;">';
			if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			else
				echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) or $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
			echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '
					</td>
				</tr>';
		}

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td class="stickybg2" colspan="', !empty($context['can_quick_mod']) ? '6' : '5', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		$stickybar = false;
		$normalbar = false;
		foreach($context['topics'] as $topic)
		{
		if($topic['is_sticky'] && !$stickybar)
			{
			echo'
				<tr class="windowbg">
					<td class="sticky_bar" style="display: none; border-bottom: 1px solid #ddd; line-height: 1.7em; font-size: 1.083em; padding-left: 49px;" colspan="', empty($options['display_quick_mod']) ? '7' : '8', '">
						<img src="', $settings['images_url'], '/icons/quick_sticky.png" style="vertical-align: middle;" alt="" />&nbsp;Stickied Topics
					</td>
				</tr>';
			$stickybar = true;
			}
		else if(!$topic['is_sticky'] && $stickybar && !$normalbar)
			{
			//echo'
			//	<tr class="windowbg">
			//		<td class="normal_bar" style="border-bottom: 1px solid #ddd; line-height: 1.7em; font-size: 1.083em; padding-left: 70px;" colspan="', empty($options['display_quick_mod']) ? '7' : '8', '">Normal Topics</td>
			//	</tr>';
			echo'
				<tr class="windowbg">
					<td class="normal_bar" style="border-bottom: 1px solid #ddd; height: 1.7em; padding-right: 14px;" colspan="3">
						<img class="icon floatright" id="Sticky_toggle" src="', $settings['images_url'], '/arrow.png" alt="*" title="', $txt['upshrink_description'], '" style="display: none;" />
					</td>
					', !empty($options['display_quick_mod']) ? '<td class="normal_bar">&nbsp;</td>':'','
				</tr>';
			$normalbar = true;
			}
			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = !$topic['approved'] ? 'approvetbg' : 'approvebg';
			// We start with locked and sticky topics.
			elseif ($topic['is_sticky'] && $topic['is_locked'])
				$color_class = 'stickybg locked_sticky';
			// Sticky topics should get a different color, too.
			elseif ($topic['is_sticky'])
				$color_class = 'stickybg';
			// Locked topics get special treatment as well.
			elseif ($topic['is_locked'])
				$color_class = 'lockedbg';
			// Last, but not least: regular topics.
			else
				$color_class = 'windowbg';

			// Some columns require a different shade of the color class.
			$alternate_class = $color_class . '2';

			echo '
				<tr class="windowbg2">';
			if (!empty($modSettings['enableParticipation']))
				echo '
					<td class="', $color_class, ' icon1" style="padding: 0 0 0 0; background: none; width: 36px; text-align: right;">
						<img src="', $settings['images_url'], '/topic/', $topic['class'], '.png" alt="" />
					</td>';
				echo '
					<td class="', $color_class, ' icon2" style="padding: 0 0 0 0; width: 0px; text-align: right;"><div style="position: relative; height: 24px;">';
			echo '
							<img src="', $topic['is_poll'] ? $settings['images_url']. '/post/poll.png': $topic['first_post']['icon_url'], '" alt="" style="position: absolute; top: -2px; right: -2.7em; z-index: 5;" />
							', ($topic['is_posted_in']) ? '<img src="'. $settings['images_url']. '/icons/user_sm.png" alt="" style="position: absolute; top: auto; bottom: -2px; right: -3.5em;" />' : '', '
					</div></td>
					<td width="60%" class="subject ', $alternate_class, '" ', (($topic['is_locked']) && (!$topic['is_sticky'])) ? 'style="opacity: 0.7;"' : '', '>

						<div style="margin-left: 3.7em;" ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" onmouseout="mouse_on_div = 0;" onmouseover="mouse_on_div = 1;" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
							', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '" class="thingy" style="line-height: 1.6em;"><a href="', ($topic['new'] && $context['user']['is_logged']) ? $topic['new_href']: $topic['first_post']['href'], '" style="display: block;" title="', ($topic['new'] && $context['user']['is_logged']) ? 'Go to first new post':'Go to first post', '">', ($topic['new'] && $context['user']['is_logged']) ? '<span class="new_posts">' . $txt['new'] . '</span>':'', '', $topic['first_post']['subject'], '', ($context['can_approve_posts'] && !$topic['approved']) ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '', '</a></span>', $topic['is_sticky'] ? '</strong>' : '';

			echo '
							<p style="line-height: 1.6em;"><a href="', $topic['first_post']['href'], '" style="font-weight: bold;">First post</a>&nbsp;by&nbsp;<span>', $topic['first_post']['member']['link'], '</span>';
					//echo '
					//			', $topic['first_post']['preview'];
					echo '
								<span style="font-size: 1em; padding: 0 10px;" id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</span>';
					echo '
							</p>
						</div>
					</td>';
					//<td class="stats ', $color_class, '">
					//	', $topic['replies'], ' ', $txt['replies'], '
					//	<br />
					//	', $topic['views'], ' ', $txt['views'], '
					//</td>
			echo '	<td width="40%" class="lastpost ', $alternate_class, ' righttext" ', (($topic['is_locked']) && (!$topic['is_sticky'])) ? 'style="opacity: 0.7;"' : '', '>
				<a href="', $topic['last_post']['href'], '" class="lastpost_icon"><img src="', $settings['images_url'], '/icons/', ($topic['is_sticky'] && $topic['is_locked'])? 'sticky_locked_last_post.png' : ($topic['is_locked'] ? 'locked_last_post.png': ($topic['is_sticky'] ? 'sticky_last_post.png' : 'last_post.png')), '" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
						<div class="lastpost_stuff">';

			echo '
							<span style="font-size: 1.083em; white-space: pre;">', $topic['replies'], ' ', $txt['replies'], '&nbsp;-&nbsp;', $topic['views'], ' ', $txt['views'], '</span><br />
							', $topic['last_post']['time'], '&nbsp;', $txt['by'], '&nbsp;<strong style="white-space: pre;">', $topic['last_post']['member']['link'], '</strong>
						</div>';
			//echo '
			//			<div style="float: left; width: 100%; height: 0.6em; clear: both; margin: 0 0 0 0; padding: 2px 10px; overflow: visible;"><small style="font-weight: bold; display: block; margin: -8px 0 0 0; padding-right: 56px;" id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small></div>';
			echo '
					</td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
					<td class="', $color_class, ' moderation ', $alternate_class, ' righttext" ', (($topic['is_locked']) && (!$topic['is_sticky'])) ? 'style="opacity: 0.7;"' : '', '>';
				if ($options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" style="margin-left: -6px;" />';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '<a style="margin: 2px;" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a style="margin: 2px;" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

					//if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
					//	echo '<br />';

					if ($topic['quick_mod']['sticky'])
						echo '<a style="margin: 2px;" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

					if ($topic['quick_mod']['move'])
						echo '<a style="margin: 2px;" href="', $scripturl, '?action=movetopic;board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
				}
				echo '
					</td>';
			}
			echo '
				</tr>';
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
				<tr class="windowbg">
					<td class="normal_bar" colspan="6" align="right">
						<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
							<option value="">--------</option>', $context['can_remove'] ? '
							<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', $context['can_lock'] ? '
							<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', $context['can_sticky'] ? '
							<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '', $context['can_move'] ? '
							<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', $context['can_merge'] ? '
							<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', $context['can_restore'] ? '
							<option value="restore">' . $txt['quick_mod_restore'] . '</option>' : '', $context['can_approve'] ? '
							<option value="approve">' . $txt['quick_mod_approve'] . '</option>' : '', $context['user']['is_logged'] ? '
							<option value="markread">' . $txt['quick_mod_markread'] . '</option>' : '', '
						</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
			{
					echo '
						<select class="qaction" id="moveItTo" name="move_to" disabled="disabled">';

					foreach ($context['move_to_boards'] as $category)
					{
						echo '
							<optgroup label="', $category['name'], '">';
						foreach ($category['boards'] as $board)
								echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
						echo '
							</optgroup>';
					}
					echo '
						</select>';
			}

			echo '
						<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit qaction" />
					</td>
				</tr>';
		}

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
	</form>';

		echo '
	<div class="pagesection">
		', template_button_strip($normal_buttons, 'right'), '
		<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#top"><strong>' . $txt['go_up'] . '</strong></a>' : '', '</div>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	echo '
	<div class="tborder" id="topic_icons">
		<div class="description">
			<p class="floatright" id="message_index_jump_to">&nbsp;</p>';

	if (!$context['no_topic_listing'])
		echo '
			<p>
				<span>', $context['user']['is_logged'] ? '<img src="'. $settings['images_url']. '/icons/user_sm.png" alt="" class="centericon" /> '. $txt['participation_caption'] : '', '</span>
				<span><img src="' . $settings['images_url'] . '/icons/locked_last_post.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '</span>' . ($modSettings['enableStickyTopics'] == '1' ? '
				<span><img src="' . $settings['images_url'] . '/icons/sticky_last_post.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '</span>' : '') . ($modSettings['pollMode'] == '1' ? '
				<span><img src="' . $settings['images_url'] . '/post/poll.png" alt="" class="centericon" /> ' . $txt['poll']. '</span>' : '') . '
			</p>';

	echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "', $txt['quick_mod_go'], '"
					});
			// ]]></script>
		</div>
	</div>';

	// Javascript for inline editing.
	// jQuery Beauty Tips override syntax:	$("span.thingy").bt(jQuery.bt.options = {positions: "bottom"});
	echo '
<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/topic.js"></script>
<script type="text/javascript"><!-- // --><![CDATA[

	// Hide certain bits during topic edit.
	hide_prefixes.push("lockicon", "stickyicon", "pages", "newicon");

	// Use it to detect when we\'ve stopped editing.
	document.onclick = modify_topic_click;

	var mouse_on_div;

	$(document).ready(function(){
		$(".table_list tbody tr").show();
		$("#Child_swap").show();

		$("#Child_swap").click(function(){
			// toggle the div on / off
			$(".table_list tbody tr").toggle();
			
			// swap the collapse / expand images
			var sCurrent_image = $("#Child_swap").attr("src");
			if ($("#Child_swap").attr("class") == "toggle") {
				$("#Child_swap").attr("src", sCurrent_image.replace("collapse", "expand"));
			} else {
				$("#Child_swap").attr("src", sCurrent_image.replace("expand", "collapse"));
			}
			
			// add/remove a class element to keep track of the image status
			$("#Child_swap").toggleClass("on");
		});
	});

	$(document).ready(function(){
		$("td.stickybg, td.stickybg2").show();
		$("#Sticky_toggle").show();

		$("#Sticky_toggle").click(function(){
			$("td.stickybg, td.stickybg2").toggle();
		});
	});
';
		//$(document).ready(function() { 
		//	$("span.thingy").bt();
		//});
echo '


// ]]></script>';
}

?>