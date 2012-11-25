<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div id="board_', $context['current_board'], '_childboards" class="boardindex_table">
		<table class="table_list">
			<tbody class="header">
				<tr>
					<td colspan="4">
						<div class="cat_bar">
							<h3 class="catbg">', $txt['parent_boards'], '</h3>
						</div>
					</td>
				</tr>
			</tbody>
			<tbody id="board_', $context['current_board'], '_children" class="content">';

		foreach ($context['boards'] as $board)
		{
			echo '
				<tr id="board_', $board['id'], '" class="windowbg2">
					<td class="windowbg icon"', !empty($board['children']) ? ' rowspan="2"' : '', '>
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

			if (!empty($board['last_post']['id']))
				echo '
						<p>', $board['last_post']['last_post_message'], '</p>';
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
						$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a  ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
					else
						$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

					// Has it posts awaiting approval?
					if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
						$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

					$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
				}

			echo '
				<tr id="board_', $board['id'], '_children" class="windowbg2">
					<td colspan="3" class="windowbg children">
						<p><strong>', $txt['parent_boards'], '</strong>: ', implode(', ', $children), '</p>
					</td>
				</tr>';
			}
		}
		echo '
			</tbody>
		</table>
	</div>';
	}

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'] || !$settings['show_mark_read'])
		unset($context['normal_buttons']['markread']);

	if (!$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">
		', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#bot" class="topbottom floatleft">' . $txt['go_down'] . '</a>' : '', '
		<div class="pagelinks floatleft">', $context['page_index'], '</div>
		', template_button_strip($context['normal_buttons'], 'right'), '
	</div>';

	if (!empty($options['show_board_desc']) && $context['description'] != '')
		echo '
	<div class="cat_bar description_header">
		<h3 class="catbg">', $context['name'], '</h3>
	</div>
	<p class="description_board">
		', $context['description'], '<br/>';

	if (!empty($context['moderators']))
		echo '
		', count($context['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $context['link_moderators']), '.&nbsp;&nbsp;';

		if (!empty($settings['display_who_viewing']))
		{
			if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			else
				echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) or $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
			echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '';
		}
		echo '
	</p>';

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
		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		if (!empty($context['topics']))
		{
			echo '
					<th scope="col" class="first_th" width="6%" colspan="1">&nbsp;</th>
					<th scope="col" class="lefttext"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a> / <a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'],  $context['sort_by'] == 'starter' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a></th>
					<th scope="col" width="14%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a> / <a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a></th>';
			// Show a "select all" box for quick moderation?
			if (empty($context['can_quick_mod']))
				echo '
					<th scope="col" class="lefttext last_th" width="22%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a></th>';
			else
				echo '
					<th scope="col" class="lefttext" width="22%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a></th>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
					<th scope="col" class="last_th" width="24"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" /></th>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['can_quick_mod']))
				echo '
					<th class="last_th" width="4%">&nbsp;</th>';
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

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['can_quick_mod']) ? '6' : '5', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		foreach ($context['topics'] as $topic)
		{
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

			// [WIP] There is trial code here to hide the topic icon column. Hardly anyone will miss it.
			// [WIP] Markup can be cleaned up later. CSS can go in the CSS files later.
			echo '
				<tr>
					<td class="', $color_class, ' icon1" style="display: none;">
						<img src="', $settings['images_url'], '/topic/', $topic['class'], '.png" alt="" />
					</td>
					<td class="', $color_class, ' icon2">
						<div style="position: relative; width: 40px; margin: auto;">
							<img src="', $topic['first_post']['icon_url'], '" alt="" />
							', $topic['is_posted_in'] ? '<img src="'. $settings['images_url']. '/icons/profile_sm.png" alt="" style="position: absolute; z-index: 5; right: 4px; bottom: -3px;" />' : '','
						</div>
					</td>
					<td class="', $alternate_class, ' subject">
						<div ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" onmouseout="mouse_on_div = 0;" onmouseover="mouse_on_div = 1;" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>';

			// [WIP] Methinks the orange icons look better if they aren't all over the page.
			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
					echo '
							<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><span class="new_posts">' . $txt['new'] . '</span></a>';

			echo '
							', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(empty($settings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], ($context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '

							<p>', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '
								<small id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small>
							</p>
						</div>
					</td>
					<td class="', $color_class, ' stats">
						', $topic['replies'], ' ', $txt['replies'], '
						<br />
						', $topic['views'], ' ', $txt['views'], '
					</td>
					<td class="', $alternate_class, ' lastpost">
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
						', $topic['last_post']['time'], '<br />
						', $txt['by'], ' ', $topic['last_post']['member']['link'], '
					</td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
					<td class="', $color_class, ' moderation" align="center">';
				if ($options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br />';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;current_board=', $context['current_board'], ';board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
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
				<tr class="titlebg">
					<td colspan="6" align="right">
						<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
							<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
							<option value="' . $qmod_action . '">' . $txt['quick_mod_'  . $qmod_action] . '</option>';

			echo '
						</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
				echo '
			<span id="quick_mod_jump_to">&nbsp;</span>';

			echo '
						<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit qaction" />
					</td>
				</tr>';
		}

		echo '
			</tbody>
		</table>
	</div>';

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
	</form>';

		echo '
	<div class="pagesection">
		', template_button_strip($context['normal_buttons'], 'right'), '
		', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#top" class="topbottom floatleft">' . $txt['go_up'] . '</a>' : '', '
		<div class="pagelinks">', $context['page_index'], '</div>
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
			<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] . '<br />' : '', '
				<img src="' . $settings['images_url'] . '/topic/normal_post.png" alt="" class="centericon" /> ' . $txt['normal_topic'] . '<br />
				<img src="' . $settings['images_url'] . '/topic/hot_post.png" alt="" class="centericon" /> ' . sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']) . '<br />
				<img src="' . $settings['images_url'] . '/topic/veryhot_post.png" alt="" class="centericon" /> ' . sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']) . '
			</p>
			<p>
				<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . ($modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
			</p>';

	echo '
			<script type="text/javascript"><!-- // --><![CDATA[';

	if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		echo '
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "quick_mod_jump_to",
						sClassName: "qaction",
						sJumpToTemplate: "%dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						bNoRedirect: true,
						bDisabled: true,
						sCustomName: "move_to"
					});';

	echo '
				if (typeof(window.XMLHttpRequest) != "undefined")
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
						sGoButtonLabel: "', $txt['quick_mod_go'], '"
					});
			// ]]></script>
			<br class="clear" />
		</div>
	</div>';

	// Javascript for inline editing.
	echo '
<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/topic.js"></script>
<script type="text/javascript"><!-- // --><![CDATA[

	// Hide certain bits during topic edit.
	hide_prefixes.push("lockicon", "stickyicon", "pages", "newicon");

	// Use it to detect when we\'ve stopped editing.
	document.onclick = modify_topic_click;

	var mouse_on_div;
// ]]></script>';
}

?>