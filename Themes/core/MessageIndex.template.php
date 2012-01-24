<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
		<a id="top"></a>';

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div class="tborder marginbottom" id="childboards">
		<table cellspacing="1" class="bordercolor boardsframe">
			<tr>
				<td colspan="4" class="catbg headerpadding">', $txt['parent_boards'], '</td>
			</tr>';

		foreach ($context['boards'] as $board)
		{
			echo '
			<tr>
				<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg icon">
					<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

			// If the board or children is new, show an indicator.
			if ($board['new'] || $board['children_new'])
				echo '
					<img src="', $settings['images_url'], '/on', $board['new'] ? '' : '2', '.gif" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" border="0" />';
			// Is it a redirection board?
			elseif ($board['is_redirect'])
				echo '
					<img src="', $settings['images_url'], '/redirect.gif" alt="*" title="*" border="0" />';
			// No new posts at all! The agony!!
			else
				echo '
					<img src="', $settings['images_url'], '/off.gif" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

			echo '
				</a>
			</td>
			<td class="windowbg2 info">
				<h4><a href="', $board['href'], '" name="b', $board['id'], '">', $board['name'], '</a>';

			// Has it outstanding posts for approval?
			if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
				echo '
					<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

			echo '
				</h4>
				<p>', $board['description'] , '</p>';

			// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
			if (!empty($board['moderators']))
				echo '
				<p class="moderators">', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

			// Show some basic information about the number of posts, etc.
			echo '
			</td>
			<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg stats smalltext">
				', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], ' <br />
				', $board['is_redirect'] ? '' : comma_format($board['topics']) . ' ' . $txt['board_topics'], '
			</td>
			<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg2 smalltext lastpost">';

			/* The board's and children's 'last_post's have:
			time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
			link, href, subject, start (where they should go for the first unread post.),
			and member. (which has id, name, link, href, username in it.) */
			if (!empty($board['last_post']['id']))
				echo '
				<strong>', $txt['last_post'], '</strong>  ', $txt['by'], ' ', $board['last_post']['member']['link'] , '<br />
				', $txt['in'], ' ', $board['last_post']['link'], '<br />
				', $txt['on'], ' ', $board['last_post']['time'];
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
						$child['link'] = '<a href="' . $child['href'] . '" title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . '</a>';
					else
						$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

					// Has it posts awaiting approval?
					if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
						$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'] . $child['unapproved_topics'] . $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

					$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
				}
				echo '
			<tr>
				<td class="windowbg3 smalltext largepadding"><strong>', $txt['parent_boards'], '</strong>: ', implode(', ', $children), '</td>
			</tr>';
			}
		}
		echo '
		</table>
	</div>';
	}

	if (!empty($options['show_board_desc']) && $context['description'] != '')
	{
		echo '
	<div id="description" class="tborder">
		<div class="titlebg2 largepadding smalltext">', $context['description'], '</div>
	</div>';
	}

	// Create the button set...
	$normal_buttons = array(
		'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'image' => 'new_topic.gif', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0'),
		'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'image' => 'new_poll.gif', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
		'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : '') . 'notify.gif', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'markread' => array('text' => 'mark_read_short', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
	);

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'] || !$settings['show_mark_read'])
		unset($normal_buttons['markread']);

	// Allow adding new buttons easily.
	call_integration_hook('integrate_messageindex_buttons', array(&$normal_buttons));

	if (!$context['no_topic_listing'])
	{
		echo '
		<div id="modbuttons_top" class="modbuttons clearfix margintop">
			<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a>' : '', '</div>
			', template_button_strip($normal_buttons, 'bottom'), '
		</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm">';

		echo '
			<div class="tborder" id="messageindex">
				<table cellspacing="1" class="bordercolor boardsframe">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<thead>
						<tr>
							<th width="9%" colspan="2" class="catbg3 headerpadding">&nbsp;</th>
							<th class="catbg3 headerpadding"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
							<th class="catbg3 headerpadding" width="11%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'], $context['sort_by'] == 'starter' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
							<th class="catbg3 headerpadding" width="4%" align="center"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
							<th class="catbg3 headerpadding" width="4%" align="center"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
							<th class="catbg3 headerpadding" width="22%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
							<th class="catbg3 headerpadding" width="24">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['can_quick_mod']))
				echo '
							<th class="catbg3 headerpadding" width="4%">&nbsp;</th>';
			echo '
						</tr>
					</thead>';
		}
		echo '
					<tbody>';

		if (!empty($settings['display_who_viewing']))
		{
			echo '
						<tr class="windowbg2">
							<td colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '" class="headerpadding smalltext">';
			if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
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
							<td colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '" class="smalltext headerpadding">
									<span class="alert">!</span> ', $context['unapproved_posts_message'], '
							</td>
						</tr>';
		}

		// No topics.... just say, "sorry bub".
		if (empty($context['topics']))
			echo '
						<tr class="windowbg2">
							<td class="catbg3" colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '"><strong>', $txt['msg_alert_none'], '</strong></td>
						</tr>';

		foreach ($context['topics'] as $topic)
		{
			// Do we want to separate the sticky and lock status out?
			if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'sticky') !== false)
				$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_sticky'));
			if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'locked') !== false)
				$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_locked'));

			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = !$topic['approved'] ? 'approvetbg' : 'approvebg';
			// Sticky topics should get a different color, too.
			elseif ($topic['is_sticky'] && !empty($settings['separate_sticky_lock']))
				$color_class = 'windowbg3';
			// Last, but not least: regular topics.
			else
				$color_class = 'windowbg';

			// Some columns require a different shade of the color class.
			$alternate_class = 'windowbg2';

			echo '
						<tr>
							<td class="', $alternate_class, ' icon1">
								<img src="', $settings['images_url'], '/topic/', $topic['class'], '.gif" alt="" />
							</td>
							<td class="', $alternate_class, ' icon2">
								<img src="', $topic['first_post']['icon_url'], '" alt="" />
							</td>
							<td class="subject ', $color_class, '" ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" onmouseout="mouse_on_div = 0;" onmouseover="mouse_on_div = 1;" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>';

			if (!empty($settings['separate_sticky_lock']))
				echo '
								', $topic['is_locked'] ? '<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" class="floatright" alt="" id="lockicon' . $topic['first_post']['id'] . '" style="margin: 0;" />' : '', '
								', $topic['is_sticky'] ? '<img src="' . $settings['images_url'] . '/icons/show_sticky.gif" class="floatright" alt="" id="stickyicon' . $topic['first_post']['id'] . '" style="margin: 0;" />' : '';

			echo '
								', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], (!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span>', $topic['is_sticky'] ? '</strong>' : '';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
					echo '
								<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a>';

			echo '
								<small id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small>
							</td>
							<td class="', $alternate_class, ' starter">
								', $topic['first_post']['member']['link'], '
							</td>
							<td class="', $color_class, ' replies">
								', $topic['replies'], '
							</td>
							<td class="', $color_class, ' views">
								', $topic['views'], '
							</td>
							<td class="', $alternate_class, ' lastpost">
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
								<span class="smalltext">
									', $topic['last_post']['time'], '<br />
									', $txt['by'], ' ', $topic['last_post']['member']['link'], '
								</span>
							</td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
							<td class="', $color_class, ' moderation">';
				if ($options['display_quick_mod'] == 1)
					echo '
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.gif" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.gif" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br />';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.gif" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.gif" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
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
						<tr class="catbg headerpadding">
							<td colspan="8" align="right">
								<select name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
									<option value="">--------</option>
									', $context['can_remove'] ? '<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', '
									', $context['can_lock'] ? '<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', '
									', $context['can_sticky'] ? '<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '', '
									', $context['can_move'] ? '<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', '
									', $context['can_merge'] ? '<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', '
									', $context['can_restore'] ? '<option value="restore">' . $txt['quick_mod_restore'] . '</option>' : '', '
									', $context['can_approve'] ? '<option value="approve">' . $txt['quick_mod_approve'] . '</option>' : '', '
									', $context['user']['is_logged'] ? '<option value="markread">' . $txt['quick_mod_markread'] . '</option>' : '', '
								</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
			{
					echo '
								<select id="moveItTo" name="move_to" disabled="disabled">';

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
								<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit" />
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
	<div id="modbuttons_bottom" class="modbuttons clearfix marginbottom">
		', template_button_strip($normal_buttons, 'top'), '
		<div class="floatleft middletext">' . $txt['pages'] . ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#top"><strong>' . $txt['go_up'] . '</strong></a>' : '', '</div>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	echo '
	<div class="marginbottom">', theme_linktree(), '</div>';

	echo '
	<div class="tborder clearfix" id="topic_icons">
		<div class="titlebg2 clearfix">
			<p class="floatright" id="message_index_jump_to">&nbsp;</p>';

	if (!$context['no_topic_listing'])
			echo '
			<div class="floatleft smalltext">
			<ul class="reset">
				', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<li><img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '</li>' : '', '
				<li><img src="' . $settings['images_url'] . '/topic/normal_post.gif" alt="" align="middle" /> ' . $txt['normal_topic'] . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/hot_post.gif" alt="" align="middle" /> ' . sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']) . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/veryhot_post.gif" alt="" align="middle" /> ' . sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']) . '</li>
			</ul>
			</div>
			<div class="floatleft smalltext">
			<ul class="reset">
				<li><img src="' . $settings['images_url'] . '/icons/quick_lock.gif" alt="" align="middle" /> ' . $txt['locked_topic'] . '</li>' . ($modSettings['enableStickyTopics'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '</li>' : '') . ($modSettings['pollMode'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : '') . '</li>
			</ul>
			</div>';

	echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				if (\'XMLHttpRequest\' in window)
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "', $txt['go'], '"
					});
			// ]]></script>
		</div>
	</div>';

	// Javascript for inline editing.
	echo '
<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/topic.js"></script>
<script type="text/javascript"><!-- // --><![CDATA[

	// Hide certain bits during topic edit.
	hide_prefixes.push("lockicon", "stickyicon", "pages", "newicon");

	// Use it to detect when we\'ve stopped editing.
	document.onclick = modify_topic_click;

	var mouse_on_div;
	function modify_topic_click()
	{
		if (in_edit_mode == 1 && mouse_on_div == 0)
			modify_topic_save("', $context['session_id'], '");
	}

	function modify_topic_keypress(oEvent)
	{
		if (\'keyCode\' in oEvent && oEvent.keyCode == 13)
		{
			modify_topic_save("', $context['session_id'], '");
			if (\'preventDefault\' in oEvent)
				oEvent.preventDefault();
			else
				oEvent.returnValue = false;
		}
	}

	// For templating, shown when an inline edit is made.
	function modify_topic_show_edit(subject)
	{
		// Just template the subject.
		setInnerHTML(cur_subject_div, \'<input type="text" name="subject" value="\' + subject + \'" size="60" style="width: 95%;" maxlength="80" onkeypress="modify_topic_keypress(event)" class="input_text" /><input type="hidden" name="topic" value="\' + cur_topic_id + \'" /><input type="hidden" name="msg" value="\' + cur_msg_id.substr(4) + \'" />\');
	}

	// And the reverse for hiding it.
	function modify_topic_hide_edit(subject)
	{
		// Re-template the subject!
		setInnerHTML(cur_subject_div, \'<a href="', $scripturl, '?topic=\' + cur_topic_id + \'.0">\' + subject + \'<\' +\'/a>\');
	}

// ]]></script>';
}

function theme_show_buttons()
{
	global $context, $settings, $options, $txt, $scripturl;

	$buttonArray = array();

	// If they are logged in, and the mark read buttons are enabled..
	if ($context['user']['is_logged'] && $settings['show_mark_read'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['mark_read_short'] . '</a>';

	// If the user has permission to show the notification button... ask them if they're sure, though.
	if ($context['can_mark_notify'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');">' . $txt[$context['is_marked_notify'] ? 'unnotify' : 'notify'] . '</a>';

	// Are they allowed to post new topics?
	if ($context['can_post_new'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=post;board=' . $context['current_board'] . '.0">' . $txt['new_topic'] . '</a>';

	// How about new polls, can the user post those?
	if ($context['can_post_poll'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll">' . $txt['new_poll'] . '</a>';

	// Right to left menu should be in reverse order.
	if ($context['right_to_left'])
		$buttonArray = array_reverse($buttonArray, true);

	return implode(' &nbsp;|&nbsp; ', $buttonArray);
}

?>