<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_recent()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/post/xx.png" alt="" class="icon" />',$txt['recent_posts'],'
			</h3>
		</div>
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
						<span class="smalltext">', $txt['last_poster'], ' <strong>', $post['poster']['link'], ' </strong> - ', $post['time'], '</span>
					</div>
					<div class="list_posts">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
				<div class="quickbuttons_wrap">
					<ul class="reset smalltext quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '" class="reply_button"><span>', $txt['reply'], '</span></a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button"><span>', $txt['quote_action'], '</span></a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="notify_button"><span>', $txt['notify'], '</span></a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="remove_button"><span>', $txt['remove'], '</span></a></li>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					</ul>
				</div>';

		echo '
			</div>';

	}

	echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>
	</div>';
}

function template_unread()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<div id="recent" class="main_content">';

	if ($context['showCheckboxes'])
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">
				', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#bot" class="topbottom floatleft">' . $txt['go_down'] . '</a>' : '', '
				<div class="pagelinks floatleft">', $context['page_index'], '</div>
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
			</div>';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
			<div class="tborder topic_table" id="unread">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="1">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="smalltext last_th" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = 'windowbg';

			// Sticky topics should get a different color, too.
			if ($topic['is_sticky'])
				$color_class = 'sticky ' . $color_class;
			// Locked topics get special treatment as well.
			if ($topic['is_locked'])
				$color_class = 'locked ' . $color_class;

			$color_class2 = $color_class . '2';

			// [WIP] There is trial code here to hide the topic icon column. Hardly anyone will miss it.
			// [WIP] Markup can be cleaned up later. CSS can go in the CSS files later.
			echo '
						<tr>
							<td class="', $color_class, ' icon2">
								<div style="position: relative; width: 40px; margin: auto;">
									<img src="', $topic['first_post']['icon_url'], '" alt="" />
									', $topic['is_posted_in'] ? '<img src="'. $settings['images_url']. '/icons/profile_sm.png" alt="" style="position: absolute; z-index: 5; right: 4px; bottom: -3px;" />' : '','
								</div>
							</td>
							<td class="subject ', $color_class2, '">
								<div>';

			// Now we handle the icons
			echo '
							<div class="icons">';
			if ($topic['is_locked'])
				echo '
								<span class="generic_icons lock floatright"></span>';
			if ($topic['is_sticky'])
				echo '
								<span class="generic_icons sticky floatright"></span>';
			if ($topic['is_poll'])
				echo '
								<span class="generic_icons poll floatright"></span>';
			echo '
							</div>';

			// [WIP] MEthinks the orange icons look better if they aren't all over the page.
			echo '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><span class="new_posts">' . $txt['new'] . '</span></a>
									', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(empty($settings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
									<p>
										', $topic['first_post']['started_by'], '
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats">
								', $topic['replies'], ' ', $txt['replies'], '
								<br />
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost">
								<a href="', $topic['last_post']['href'], '"><span class="generic_icons last_post" title="', $txt['last_post'], '"></span></a>
								', sprintf($txt['last_post_topic'], $topic['last_post']['time'], $topic['last_post']['member']['link']), '
							</td>';

			if ($context['showCheckboxes'])
				echo '
							<td class="', $color_class2, ' moderation" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		if (empty($context['topics']))
			echo '
					<tr style="display: none;"><td></td></tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
				', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#recent" class="topbottom floatleft">' . $txt['go_up'] . '</a>' : '', '
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['topic_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
	</div>';

	if (empty($context['no_topic_listing']))
		template_topic_legend();
}

function template_replies()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<div id="recent">';

	if ($context['showCheckboxes'])
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unreadreplies', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">
				', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#bot" class="topbottom floatleft">' . $txt['go_down'] . '</a>' : '', '
				<div class="pagelinks floatleft">', $context['page_index'], '</div>
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
			</div>';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
			<div class="tborder topic_table" id="unreadreplies">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="1">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
				echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="last_th" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <span class="sort sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = 'windowbg';

			// Sticky topics should get a different color, too.
			if ($topic['is_sticky'])
				$color_class = 'sticky ' . $color_class;
			// Locked topics get special treatment as well.
			if ($topic['is_locked'])
				$color_class = 'locked ' . $color_class;

			$color_class2 = $color_class . '2';

			// [WIP] There is trial code here to hide the topic icon column. Hardly anyone will miss it.
			// [WIP] Markup can be cleaned up later. CSS can go in the CSS files later.
			echo '
						<tr>
							<td class="', $color_class, ' icon2">
								<div style="position: relative; width: 40px; margin: auto;">
									<img src="', $topic['first_post']['icon_url'], '" alt="" />
									', $topic['is_posted_in'] ? '<img class="posted" src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" />' : '','
								</div>
							</td>
							<td class="subject ', $color_class2, '">
								<div>';

			// Now we handle the icons
			echo '
							<div class="icons">';
			if ($topic['is_locked'])
				echo '
								<span class="generic_icons lock floatright"></span>';
			if ($topic['is_sticky'])
				echo '
								<span class="generic_icons sticky floatright"></span>';
			if ($topic['is_poll'])
				echo '
								<span class="generic_icons poll floatright"></span>';
			echo '
							</div>';

			// [WIP] MEthinks the orange icons look better if they aren't all over the page.
			echo '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><span class="new_posts">' . $txt['new'] . '</span></a>
									', $topic['is_sticky'] ? '<strong>' : '', '<span title="', $topic[(empty($settings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<p>
										', $topic['first_post']['started_by'], '
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats">
								', $topic['replies'], ' ', $txt['replies'], '
								<br />
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost">
								<a href="', $topic['last_post']['href'], '"><span class="generic_icons last_post" title="', $txt['last_post'], '"></span></a>
								', sprintf($txt['last_post_topic'], $topic['last_post']['time'], $topic['last_post']['member']['link']), '
							</td>';

			if ($context['showCheckboxes'])
				echo '
							<td class="', $color_class2, ' moderation" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
				', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#recent" class="topbottom floatleft">' . $txt['go_up'] . '</a>' : '', '
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['topic_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
	</div>';

	if (empty($context['no_topic_listing']))
		template_topic_legend();
}

?>