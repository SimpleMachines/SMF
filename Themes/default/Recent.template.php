<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

/**
 * Template for showing recent posts
 */
function template_recent()
{
	global $context, $txt;

	echo '
	<div id="recent" class="main_section">
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span id="top_subject">', $txt['recent_posts'], '</span>
			</h2>
		</div>';

	if (!empty($context['page_index']))
		echo '
		<div class="pagesection">
			<div class="pagelinks">' . $context['page_index'] . '</div>
		</div>';

	if (empty($context['posts']))
		echo '
		<div class="windowbg">', $txt['no_messages'], '</div>';

	foreach ($context['posts'] as $post)
	{
		echo '
		<div class="', $post['css_class'], '">
			<div class="page_number floatright"> #', $post['counter'], '</div>
			<div class="topic_details">
				<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
				<span class="smalltext">', $txt['last_poster'], ' <strong>', $post['poster']['link'], ' </strong> - ', $post['time'], '</span>
			</div>
			<div class="list_posts">', $post['message'], '</div>';

		// Post options
		template_quickbuttons($post['quickbuttons'], 'recent');

		echo '
		</div><!-- $post[css_class] -->';
	}

	echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>
	</div><!-- #recent -->';
}

/**
 * Template for showing unread posts
 */
function template_unread()
{
	global $context, $txt, $scripturl, $modSettings, $board_info;

	// User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device.
	if (!empty($context['recent_buttons']))
		echo '
	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading">
				', $txt['mobile_action'], '
				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			', template_button_strip($context['recent_buttons']), '
		</div>
	</div>';

	echo '
	<div id="recent" class="main_content">
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span>', (!empty($board_info['name']) ? $board_info['name'] . ' - ' : '') . $context['page_title'], '</span>
			</h2>
		</div>';

	if ($context['showCheckboxes'])
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '">';

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">
				', $context['menu_separator'], '
				<div class="pagelinks floatleft">
					<a href="#bot" class="button">', $txt['go_down'], '</a>
					', $context['page_index'], '
				</div>
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '';

		// Mobile action (top)
		if (!empty($context['recent_buttons']))
			echo '
				<div class="mobile_buttons floatright">
					<a class="button mobile_act">', $txt['mobile_action'], '</a>
				</div>';

		echo '
			</div>';

		echo '
			<div id="unread">
				<div id="topic_header" class="title_bar">
					<div class="board_icon"></div>
					<div class="info">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>
					<div class="board_stats centertext">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>
					<div class="lastpost">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
					<div class="moderation">
						<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
					</div>';

		echo '
				</div><!-- #topic_header -->
				<div id="topic_container">';

		foreach ($context['topics'] as $topic)
		{
			echo '
					<div class="', $topic['css_class'], '">
						<div class="board_icon">
							<img src="', $topic['first_post']['icon_url'], '" alt="">
							', $topic['is_posted_in'] ? '<span class="main_icons profile_sm"></span>' : '', '
						</div>
						<div class="info">';

			// Now we handle the icons
			echo '
							<div class="icons floatright">';

			if ($topic['is_locked'])
				echo '
								<span class="main_icons lock"></span>';

			if ($topic['is_sticky'])
				echo '
								<span class="main_icons sticky"></span>';

			if ($topic['is_poll'])
				echo '
								<span class="main_icons poll"></span>';

			echo '
							</div>';

			echo '
							<div class="recent_title">
								<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '" class="new_posts">' . $txt['new'] . '</a>
								', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(empty($modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
							</div>
							<p class="floatleft">
								', $topic['first_post']['started_by'], '
							</p>
							', !empty($topic['pages']) ? '<span id="pages' . $topic['first_post']['id'] . '" class="topic_pages">' . $topic['pages'] . '</span>' : '', '
						</div><!-- .info -->
						<div class="board_stats centertext">
							<p>
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</p>
						</div>
						<div class="lastpost">
							', sprintf($txt['last_post_topic'], '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', $topic['last_post']['member']['link']), '
						</div>';

			if ($context['showCheckboxes'])
				echo '
						<div class="moderation">
							<input type="checkbox" name="topics[]" value="', $topic['id'], '">
						</div>';

			echo '
					</div><!-- $topic[css_class] -->';
		}

		if (empty($context['topics']))
			echo '
					<div style="display: none;"></div>';

		echo '
				</div><!-- #topic_container -->
			</div><!-- #unread -->';

		echo '
			<div class="pagesection">
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
				', $context['menu_separator'], '
				<div class="pagelinks floatleft">
					<a href="#recent" class="button">', $txt['go_up'], '</a>
					', $context['page_index'], '
				</div>';

		// Mobile action (bottom)
		if (!empty($context['recent_buttons']))
		echo '
				<div class="mobile_buttons floatright">
					<a class="button mobile_act">', $txt['mobile_action'], '</a>
				</div>';

		echo '
			</div>';
	}
	else
		echo '
			<div class="infobox">
				<p class="centertext">
					', $context['showing_all_topics'] ? $txt['topic_alert_none'] : sprintf($txt['unread_topics_visit_none'], $scripturl), '
				</p>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
	</div><!-- #recent -->';

	if (empty($context['no_topic_listing']))
		template_topic_legend();
}

/**
 * Template for showing unread replies (eg new replies to topics you've posted in)
 */
function template_replies()
{
	global $context, $txt, $scripturl, $modSettings, $board_info;

	// User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device.
	if (!empty($context['recent_buttons']))
		echo '
	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading">
				', $txt['mobile_action'], '
				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			', template_button_strip($context['recent_buttons']), '
		</div>
	</div>';

	echo '
	<div id="recent">
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span>', (!empty($board_info['name']) ? $board_info['name'] . ' - ' : '') . $context['page_title'], '</span>
			</h2>
		</div>';

	if ($context['showCheckboxes'])
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unreadreplies', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '">';

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">
				', $context['menu_separator'], '
				<div class="pagelinks floatleft">
					<a href="#bot" class="button">', $txt['go_down'], '</a>
					', $context['page_index'], '
				</div>
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '';

		// Mobile action (top)
		if (!empty($context['recent_buttons']))
			echo '
				<div class="mobile_buttons floatright">
					<a class="button mobile_act">', $txt['mobile_action'], '</a>
				</div>';

		echo '
			</div>';

		echo '
			<div id="unreadreplies">
				<div id="topic_header" class="title_bar">
					<div class="board_icon"></div>
					<div class="info">
						<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>
					<div class="board_stats centertext">
						<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>
					<div class="lastpost">
						<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
					</div>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
					<div class="moderation">
						<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
					</div>';

		echo '
				</div><!-- #topic_header -->
				<div id="topic_container">';

		foreach ($context['topics'] as $topic)
		{
			echo '
					<div class="', $topic['css_class'], '">
						<div class="board_icon">
							<img src="', $topic['first_post']['icon_url'], '" alt="">
							', $topic['is_posted_in'] ? '<span class="main_icons profile_sm"></span>' : '', '
						</div>
						<div class="info">';

			// Now we handle the icons
			echo '
							<div class="icons floatright">';

			if ($topic['is_locked'])
				echo '
								<span class="main_icons lock"></span>';

			if ($topic['is_sticky'])
				echo '
								<span class="main_icons sticky"></span>';

			if ($topic['is_poll'])
				echo '
								<span class="main_icons poll"></span>';

			echo '
							</div>';

			echo '
							<div class="recent_title">
								<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '" class="new_posts">' . $txt['new'] . '</a>
								', $topic['is_sticky'] ? '<strong>' : '', '<span title="', $topic[(empty($modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
							</div>
							<p class="floatleft">
								', $topic['first_post']['started_by'], '
							</p>
							', !empty($topic['pages']) ? '<span id="pages' . $topic['first_post']['id'] . '" class="topic_pages">' . $topic['pages'] . '</span>' : '', '
						</div><!-- .info -->
						<div class="board_stats centertext">
							<p>
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</p>
						</div>
						<div class="lastpost">
							', sprintf($txt['last_post_topic'], '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', $topic['last_post']['member']['link']), '
						</div>';

			if ($context['showCheckboxes'])
				echo '
						<div class="moderation">
							<input type="checkbox" name="topics[]" value="', $topic['id'], '">
						</div>';

			echo '
					</div><!-- $topic[css_class] -->';
		}

		echo '
				</div><!-- #topic_container -->
			</div><!-- #unreadreplies -->
			<div class="pagesection">
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
				', $context['menu_separator'], '
				<div class="pagelinks floatleft">
					<a href="#recent" class="button">', $txt['go_up'], '</a>
					', $context['page_index'], '
				</div>';

		// Mobile action (bottom)
		if (!empty($context['recent_buttons']))
			echo '
				<div class="mobile_buttons floatright">
					<a class="button mobile_act">', $txt['mobile_action'], '</a>
				</div>';

		echo '
			</div>';
	}
	else
		echo '
			<div class="infobox">
				<p class="centertext">
					', $context['showing_all_topics'] ? $txt['topic_alert_none'] : $txt['updated_topics_visit_none'], '
				</p>
			</div>';

	if ($context['showCheckboxes'])
		echo '
		</form>';

	echo '
	</div><!-- #recent -->';

	if (empty($context['no_topic_listing']))
		template_topic_legend();
}

?>