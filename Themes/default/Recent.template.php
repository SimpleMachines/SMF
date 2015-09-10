<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

function template_Recent_init()
{
	global $context, $settings, $txt, $scripturl;

	$context['can_approve_posts'] = false;
	$context['can_quick_mod'] = $context['showCheckboxes'];
	$txt['starter'] = $txt['started_by'];

	$sort_methods = array(
		'subject' => 'ms.subject',
		'starter' => 'IFNULL(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	foreach ($sort_methods as $key => $val)
		$context['topics_headers'][$key] = '<a href="' . $scripturl . '?action=' . $context['current_action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . ';sort=' . $key . ($context['sort_by'] == $key && $context['sort_direction'] == 'up' ? ';desc' : '') . '">' . $txt[$key] . ($context['sort_by'] == $key ? '<span class="sort sort_' . $context['sort_direction'] . '"></span>' : '') . '</a>';
}

/**
 * Template for showing recent posts
 */
function template_recent()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="xx"></span>',$txt['recent_posts'],'
			</h3>
		</div>
		<div class="pagesection">', $context['page_index'], '</div>';

	if (empty($context['posts']))
	{
		echo '
			<div class="windowbg">', $txt['no_messages'], '</div>';
	}

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['css_class'] ,'">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
						<span class="smalltext">', $txt['last_poster'], ' <strong>', $post['poster']['link'], ' </strong> - ', $post['time'], '</span>
					</div>
					<div class="list_posts">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
			echo '
					<ul class="quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span class="generic_icons reply_button"></span>', $txt['reply'], '</a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span class="generic_icons quote"></span>', $txt['quote_action'], '</a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['remove_message'] ,'" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['remove'], '</a></li>';

		if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
			echo '
					</ul>';

		echo '
			</div>';

	}

	echo '
		<div class="pagesection">', $context['page_index'], '</div>
	</div>';
}

/**
 * Template for showing unread posts
 */
function template_unread()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<div id="recent" class="main_content">';

	if ($context['showCheckboxes'])
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '">';

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">
				', $context['menu_separator'], '<a href="#bot" class="topbottom floatleft">', $txt['go_down'], '</a>
				<div class="pagelinks floatleft">', $context['page_index'], '</div>
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
			</div>';

		template_topic_list();

		echo '
			<div class="pagesection">
				', !empty($context['recent_buttons']) ? template_button_strip($context['recent_buttons'], 'right') : '', '
				', $context['menu_separator'], '<a href="#recent" class="topbottom floatleft">', $txt['go_up'], '</a>
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

/**
 * Template for showing unread replies (eg new replies to topics you've posted in)
 */
function template_replies()
{
	template_unread();
}