<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines Forum contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/post/xx.png" alt="" class="icon" />',$txt['recent_posts'],'
			</h3>
		</div>
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
						<span class="smalltext">', $txt['last_post'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], ' </strong> - ', $post['time'], '</span>
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
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button"><span>', $txt['quote'], '</span></a></li>';

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
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>
	</div>';
}

function template_unread()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

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
			<div class="pagesection">';

		if (!empty($context['recent_buttons']) && !empty($settings['use_tabs']))
			template_button_strip($context['recent_buttons'], 'right');

		echo '
				<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . ' &nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a>' : '', '</div>
			</div>';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
			<div class="tborder topic_table" id="unread">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="1">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="smalltext last_th" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
			$color_class = '';
			if (strpos($topic['class'], 'sticky') !== false)
				$color_class = 'stickybg';
			if (strpos($topic['class'], 'locked') !== false)
				$color_class .= 'lockedbg';

			$color_class2 = !empty($color_class) ? $color_class . '2' : '';

			// [WIP] There is trial code here to hide the topic icon column. Hardly anyone will miss it.
			// [WIP] Markup can be cleaned up later. CSS can go in the CSS files later.
			echo '
						<tr>
							<td class="', $color_class, ' icon1 windowbg" style="display: none;">
								<img src="', $settings['images_url'], '/topic/', $topic['class'], '.png" alt="" />
							</td>
							<td class="', $color_class, ' icon2 windowbg">
								<div style="position: relative; width: 40px; margin: auto;">
									<img src="', $topic['first_post']['icon_url'], '" alt="" />
									', $topic['is_posted_in'] ? '<img src="'. $settings['images_url']. '/icons/profile_sm.png" alt="" style="position: absolute; z-index: 5; right: 4px; bottom: -3px;" />' : '','
								</div>
							</td>
							<td class="subject ', $color_class2, ' windowbg2">
								<div>';

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
							<td class="', $color_class, ' stats windowbg">
								', $topic['replies'], ' ', $txt['replies'], '
								<br />
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost windowbg2">
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" class="floatright" /></a>
								', $topic['last_post']['time'], '<br />
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($context['showCheckboxes'])
				echo '
							<td class="' . (!empty($color_class) ? $color_class : 'windowbg2') . ' moderation" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		if (empty($settings['use_tabs']) && !empty($context['recent_buttons']))
			echo '
						<tr class="catbg">
							<td colspan="', $context['showCheckboxes'] ? '6' : '5', '" align="right">
								', template_button_strip($context['recent_buttons'], 'top'), '
							</td>
						</tr>';

		if (empty($context['topics']))
			echo '
					<tr style="display: none;"><td></td></tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection" id="readbuttons">';

		if (!empty($settings['use_tabs']) && !empty($context['recent_buttons']))
			template_button_strip($context['recent_buttons'], 'right');

		echo '
				<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . ' &nbsp;&nbsp;<a href="#top"><strong>' . $txt['go_up'] . '</strong></a>' : '', '</div>
			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
		<div class="description " id="topic_icons">
			<p class="smalltext floatleft">
				', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] . '<br />' : '', '
				<img src="', $settings['images_url'], '/topic/normal_post.png" alt="" class="centericon" /> ', $txt['normal_topic'], '<br />
				<img src="', $settings['images_url'], '/topic/hot_post.png" alt="" class="centericon" /> ', sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']), '<br />
				<img src="', $settings['images_url'], '/topic/veryhot_post.png" alt="" class="centericon" /> ', sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']), '
			</p>
			<p class="smalltext para2">
				<img src="', $settings['images_url'], '/icons/quick_lock.png" alt="" class="centericon" /> ', $txt['locked_topic'], '<br />', ($modSettings['enableStickyTopics'] == '1' ? '
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : ''), ($modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : ''), '
			</p>
		</div>
	</div>';
}

function template_replies()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

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
			<div class="pagesection">';

		if (!empty($context['recent_buttons']) && !empty($settings['use_tabs']))
			template_button_strip($context['recent_buttons'], 'right');

		echo '
				<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . ' &nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a>' : '', '</div>
			</div>';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
			<div class="tborder topic_table" id="unreadreplies">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="1">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
				echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="last_th" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
			$color_class = '';
			if (strpos($topic['class'], 'sticky') !== false)
				$color_class = 'stickybg';
			if (strpos($topic['class'], 'locked') !== false)
				$color_class .= 'lockedbg';

			$color_class2 = !empty($color_class) ? $color_class . '2' : '';

			// [WIP] There is trial code here to hide the topic icon column. Hardly anyone will miss it.
			// [WIP] Markup can be cleaned up later. CSS can go in the CSS files later.
			echo '
						<tr>
							<td class="', $color_class, ' icon1 windowbg" style="display: none;">
								<img src="', $settings['images_url'], '/topic/', $topic['class'], '.png" alt="" />
							</td>
							<td class="', $color_class, ' icon2 windowbg">
								<div style="position: relative; width: 40px; margin: auto;">
									<img src="', $topic['first_post']['icon_url'], '" alt="" />
									', $topic['is_posted_in'] ? '<img src="'. $settings['images_url']. '/icons/profile_sm.png" alt="" style="position: absolute; z-index: 5; right: 4px; bottom: -3px;" />' : '','
								</div>
							</td>
							<td class="subject ', $color_class2, ' windowbg2">
								<div>';

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
							<td class="', $color_class, ' stats windowbg">
								', $topic['replies'], ' ', $txt['replies'], '
								<br />
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost windowbg2">
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" class="floatright" /></a>
								', $topic['last_post']['time'], '<br />
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($context['showCheckboxes'])
				echo '
							<td class="' . (!empty($color_class) ? $color_class : 'windowbg2') . ' moderation" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		if (empty($settings['use_tabs']) && !empty($context['recent_buttons']))
			echo '
						<tr class="catbg">
							<td colspan="', $context['showCheckboxes'] ? '6' : '5', '" align="right">
								', template_button_strip($context['recent_buttons'], 'top'), '
							</td>
						</tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">';

		if (!empty($settings['use_tabs']) && !empty($context['recent_buttons']))
			template_button_strip($context['recent_buttons'], 'right');

		echo '
				<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . ' &nbsp;&nbsp;<a href="#top"><strong>' . $txt['go_up'] . '</strong></a>' : '', '</div>
 			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
		<div class="description flow_auto" id="topic_icons">
			<p class="smalltext floatleft">
				', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] . '<br />' : '', '
				<img src="', $settings['images_url'], '/topic/normal_post.png" alt="" class="centericon" /> ', $txt['normal_topic'], '<br />
				<img src="', $settings['images_url'], '/topic/hot_post.png" alt="" class="centericon" /> ', sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']), '<br />
				<img src="', $settings['images_url'], '/topic/veryhot_post.png" alt="" class="centericon" /> ', sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']), '
			</p>
			<p class="smalltext para2">
				<img src="', $settings['images_url'], '/icons/quick_lock.png" alt="" class="centericon" /> ', $txt['locked_topic'], '<br />', ($modSettings['enableStickyTopics'] == '1' ? '
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . ($modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
			</p>
		</div>
	</div>';
}

?>