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
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft"><img src="', $settings['images_url'], '/post/xx.gif" alt="" class="icon" />',$txt['recent_posts'],'</span>
			</h3>
		</div>
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
				<span class="topslice"><span></span></span>
				<div class="content">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
						<span class="smalltext">&#171;&nbsp;', $txt['last_post'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], ' </strong> ', $txt['on'], '<em> ', $post['time'], '</em>&nbsp;&#187;</span>
					</div>
					<div class="list_posts">', $post['message'], '</div>
				</div>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
				<div class="quickbuttons_wrap">
					<ul class="reset smalltext quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li class="reply_button"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span>', $txt['reply'], '</span></a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li class="quote_button"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span>', $txt['quote'], '</span></a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li class="notify_button"><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '"><span>', $txt['notify'], '</span></a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li class="remove_button"><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');"><span>', $txt['remove'], '</span></a></li>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					</ul>
				</div>';

		echo '
				<span class="botslice clear"><span></span></span>
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

	$showCheckboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];

	if ($showCheckboxes)
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if ($settings['show_mark_read'])
	{
		// Generate the button strip.
		$mark_read = array(
			'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		if ($showCheckboxes)
			$mark_read['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.gif',
				'lang' => true,
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read) && !empty($settings['use_tabs']))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
			</div>';

		echo '
			<div class="tborder topic_table" id="unread">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="2">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($showCheckboxes)
			echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="smalltext last_th" width="22%">
								<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
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

			echo '
						<tr>
							<td class="', $color_class, ' icon1 windowbg">
								<img src="', $settings['images_url'], '/topic/', $topic['class'], '.gif" alt="" />
							</td>
							<td class="', $color_class, ' icon2 windowbg">
								<img src="', $topic['first_post']['icon_url'], '" alt="" />
							</td>
							<td class="subject ', $color_class2, ' windowbg2">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a>
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
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
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" style="float: right;" /></a>
								', $topic['last_post']['time'], '<br />
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($showCheckboxes)
				echo '
							<td class="windowbg2" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		if (!empty($context['topics']) && !$context['showing_all_topics'])
			$mark_read['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.gif', 'lang' => true, 'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'], 'active' => true);

		if (empty($settings['use_tabs']) && !empty($mark_read))
			echo '
						<tr class="catbg">
							<td colspan="', $showCheckboxes ? '6' : '5', '" align="right">
								', template_button_strip($mark_read, 'top'), '
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

		if (!empty($settings['use_tabs']) && !empty($mark_read))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($showCheckboxes)
		echo '
		</form>';

	echo '
		<div class="description " id="topic_icons">
			<p class="smalltext floatleft">
				', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '<br />' : '', '
				<img src="', $settings['images_url'], '/topic/normal_post.gif" alt="" align="middle" /> ', $txt['normal_topic'], '<br />
				<img src="', $settings['images_url'], '/topic/hot_post.gif" alt="" align="middle" /> ', sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']), '<br />
				<img src="', $settings['images_url'], '/topic/veryhot_post.gif" alt="" align="middle" /> ', sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']), '
			</p>
			<p class="smalltext para2">
				<img src="', $settings['images_url'], '/icons/quick_lock.gif" alt="" align="middle" /> ', $txt['locked_topic'], '<br />', ($modSettings['enableStickyTopics'] == '1' ? '
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '<br />' : ''), ($modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : ''), '
			</p>
		</div>
	</div>';
}

function template_replies()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<div id="recent">';

	$showCheckboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];

	if ($showCheckboxes)
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unreadreplies', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if (isset($context['topics_to_mark']) && !empty($settings['show_mark_read']))
	{
		// Generate the button strip.
		$mark_read = array(
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		if ($showCheckboxes)
			$mark_read['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.gif',
				'lang' => true,
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read) && !empty($settings['use_tabs']))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
			</div>';

		echo '
			<div class="tborder topic_table" id="unreadreplies">
				<table class="table_grid" cellspacing="0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" width="8%" colspan="2">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>
							<th scope="col" width="14%" align="center">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($showCheckboxes)
				echo '
							<th scope="col" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
							</th>';
		else
			echo '
							<th scope="col" class="last_th" width="22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a>
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

			echo '
						<tr>
							<td class="', $color_class, ' icon1 windowbg">
								<img src="', $settings['images_url'], '/topic/', $topic['class'], '.gif" alt="" />
							</td>
							<td class="', $color_class, ' icon2 windowbg">
								<img src="', $topic['first_post']['icon_url'], '" alt="" />
							</td>
							<td class="subject ', $color_class2, ' windowbg2">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a>
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
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
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" style="float: right;" /></a>
								', $topic['last_post']['time'], '<br />
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($showCheckboxes)
				echo '
							<td class="windowbg2" valign="middle" align="center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
							</td>';
			echo '
						</tr>';
		}

		if (empty($settings['use_tabs']) && !empty($mark_read))
			echo '
						<tr class="catbg">
							<td colspan="', $showCheckboxes ? '6' : '5', '" align="right">
								', template_button_strip($mark_read, 'top'), '
							</td>
						</tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">';

		if (!empty($settings['use_tabs']) && !empty($mark_read))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
			</div>';
	}
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext">
					', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
				</h3>
			</div>';

	if ($showCheckboxes)
		echo '
		</form>';

	echo '
		<div class="description flow_auto" id="topic_icons">
			<p class="smalltext floatleft">
				', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '<br />' : '', '
				<img src="', $settings['images_url'], '/topic/normal_post.gif" alt="" align="middle" /> ', $txt['normal_topic'], '<br />
				<img src="', $settings['images_url'], '/topic/hot_post.gif" alt="" align="middle" /> ', sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']), '<br />
				<img src="', $settings['images_url'], '/topic/veryhot_post.gif" alt="" align="middle" /> ', sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']), '
			</p>
			<p class="smalltext para2">
				<img src="', $settings['images_url'], '/icons/quick_lock.gif" alt="" align="middle" /> ', $txt['locked_topic'], '<br />', ($modSettings['enableStickyTopics'] == '1' ? '
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '<br />' : '') . ($modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : '') . '
			</p>
		</div>
	</div>';
}

?>