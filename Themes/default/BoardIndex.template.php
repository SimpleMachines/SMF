<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * The top part of the outer layer of the boardindex
 */
function template_boardindex_outer_above()
{
	template_newsfader();
}

/**
 * This shows the newsfader
 */
function template_newsfader()
{
	global $context, $settings, $options, $txt;

	// Show the news fader?  (assuming there are things to show...)
	if (!empty($settings['show_newsfader']) && !empty($context['news_lines']))
	{
		echo '
		<ul id="smf_slider" class="roundframe">';

		foreach ($context['news_lines'] as $news)
		{
			echo '
			<li>', $news, '</li>';
		}

		echo '
		</ul>
		<script>
			jQuery("#smf_slider").slippry({
				pause: ', $settings['newsfader_time'], ',
				adaptiveHeight: 0,
				captions: 0,
				controls: 0,
			});
		</script>';
	}
}

/**
 * This actually displays the board index
 */
function template_main()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="boardindex_table" class="boardindex_table">';

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	foreach ($context['categories'] as $category)
	{
		// If theres no parent boards we can see, avoid showing an empty category (unless its collapsed)
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
		<div class="main_container">
			<div class="cat_bar" id="category_', $category['id'], '">
				<h3 class="catbg">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
					<span id="category_', $category['id'], '_upshrink" class="', $category['is_collapsed'] ? 'toggle_down' : 'toggle_up', ' floatright" data-collapsed="', (int) $category['is_collapsed'], '" title="', !$category['is_collapsed'] ? $txt['hide_category'] : $txt['show_category'], '" style="display: none;"></span>';

		echo '
					', $category['link'], '
				</h3>', !empty($category['description']) ? '
				<div class="desc">' . $category['description'] . '</div>' : '', '
			</div>
			<div id="category_', $category['id'], '_boards" ', (!empty($category['css_class']) ? ('class="' . $category['css_class'] . '"') : ''), '>';

			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */
			foreach ($category['boards'] as $board)
			{
				echo '
				<div id="board_', $board['id'], '" class="up_contain ', (!empty($board['css_class']) ? $board['css_class'] : ''), '">
					<div class="board_icon">
						<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>
					</div>
					<div class="info">
						<a class="subject mobile_subject" href="', $board['href'], '" id="b', $board['id'], '">
							', $board['name'], '
							<p class="board_description mobile_display">', $board['description'], '</p>
						</a>';

				// Has it outstanding posts for approval?
				if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
					echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				echo '
						<p class="board_description">', $board['description'], '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['link_moderators']))
					echo '
						<p class="moderators">', count($board['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

				// Show some basic information about the number of posts, etc.
					echo '
					</div>
					<div class="board_stats">
						<p>', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], '
						', $board['is_redirect'] ? '' : '<br> ' . comma_format($board['topics']) . ' ' . $txt['board_topics'], '
						</p>
					</div>
					<div class="lastpost ',!empty($board['last_post']['id']) ? 'lpr_border' : 'hidden', '">';

				if (!empty($board['last_post']['id']))
					echo '
						<p>', $board['last_post']['last_post_message'], '</p>';
				echo '
					</div>';
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
							$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
						else
							$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>';

						// Has it posts awaiting approval?
						if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
							$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
					}

				echo '
					<div id="board_', $board['id'], '_children" class="children">
						<p><strong>', $txt['sub_boards'], '</strong>: ', implode($children), '</p>
					</div>';
				}

				echo '
					</div>';
			}

		echo '
			</div>
		</div>';
	}

	echo '
	</div>';

	// Show the mark all as read button?
	if ($context['user']['is_logged'] && !empty($context['categories']))
		echo '
		<div class="mark_read">', template_button_strip($context['mark_read_button'], 'right'), '</div>';
}

/**
 * The lower part of the outer layer of the board index
 */
function template_boardindex_outer_below()
{
	template_info_center();
}

/**
 * Displays the info center
 */
function template_info_center()
{
	global $context, $options, $txt;

	if (empty($context['info_center']))
		return;

	// Here's where the "Info Center" starts...
	echo '
	<div class="roundframe" id="info_center">
		<div class="title_bar">
			<h3 class="titlebg">
				<span class="toggle_up floatright" id="upshrink_ic" title="', $txt['hide_infocenter'], '" style="display: none;"></span>
				<a href="#" id="upshrink_link">', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '</a>
			</h3>
		</div>
		<div id="upshrinkHeaderIC"', empty($options['collapse_header_ic']) ? '' : ' style="display: none;"', '>';

	foreach ($context['info_center'] as $block)
	{
		$func = 'template_ic_block_' . $block['tpl'];
		$func();
	}

	echo '
		</div>
	</div>';

	// Info center collapse object.
	echo '
	<script>
		var oInfoCenterToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($options['collapse_header_ic']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'upshrinkHeaderIC\'
			],
			aSwapImages: [
				{
					sId: \'upshrink_ic\',
					altExpanded: ', JavaScriptEscape($txt['hide_infocenter']), ',
					altCollapsed: ', JavaScriptEscape($txt['show_infocenter']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'upshrink_link\',
					msgExpanded: ', JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])), ',
					msgCollapsed: ', JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'collapse_header_ic\',
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
			},
			oCookieOptions: {
				bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
				sCookieName: \'upshrinkIC\'
			}
		});
	</script>';
}

/**
 * The recent posts section of the info center
 */
function template_ic_block_recent()
{
	global $context, $scripturl, $settings, $txt;

	// This is the "Recent Posts" bar.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', $scripturl, '?action=recent"><span class="xx"></span>', $txt['recent_posts'], '</a>
				</h4>
			</div>
			<div id="recent_posts_content">';

	// Only show one post.
	if ($settings['number_recent_posts'] == 1)
	{
		// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
		echo '
				<p id="infocenter_onepost" class="inline">
					<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>&nbsp;&quot;', sprintf($txt['is_recent_updated'], '&quot;' . $context['latest_post']['link'], '&quot;'), ' (', $context['latest_post']['time'], ')<br>
				</p>';
	}
	// Show lots of posts.
	elseif (!empty($context['latest_posts']))
	{
		echo '
				<table id="ic_recentposts">
					<tr class="windowbg">
						<th class="recentpost">', $txt['message'], '</th>
						<th class="recentposter">', $txt['author'], '</th>
						<th class="recentboard">', $txt['board'], '</th>
						<th class="recenttime">', $txt['date'], '</th>
					</tr>';

		/* Each post in latest_posts has:
				board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
				subject, short_subject (shortened with...), time, link, and href. */
		foreach ($context['latest_posts'] as $post)
			echo '
					<tr class="windowbg">
						<td class="recentpost"><strong>', $post['link'], '</strong></td>
						<td class="recentposter">', $post['poster']['link'], '</td>
						<td class="recentboard">', $post['board']['link'], '</td>
						<td class="recenttime">', $post['time'], '</td>
					</tr>';
		echo '
				</table>';
	}
	echo '
			</div>';
}

/**
 * The calendar section of the info center
 */
function template_ic_block_calendar()
{
	global $context, $scripturl, $txt, $settings;

	// Show information about events, birthdays, and holidays on the calendar.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', $scripturl, '?action=calendar' . '"><span class="generic_icons calendar"></span> ', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '</a>
				</h4>
			</div>';

	// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P.
	if (!empty($context['calendar_holidays']))
		echo '
				<p class="inline holiday"><span>', $txt['calendar_prompt'], '</span> ', implode(', ', $context['calendar_holidays']), '</p>';

	// People's birthdays. Like mine. And yours, I guess. Kidding.
	if (!empty($context['calendar_birthdays']))
	{
		echo '
				<p class="inline">
					<span class="birthday">', $context['calendar_only_today'] ? $txt['birthdays'] : $txt['birthdays_upcoming'], '</span>';
		// Each member in calendar_birthdays has: id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?)
		foreach ($context['calendar_birthdays'] as $member)
			echo '
					<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong class="fix_rtl_names">' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';
		echo '
				</p>';
	}

	// Events like community get-togethers.
	if (!empty($context['calendar_events']))
	{
		echo '
				<p class="inline">
					<span class="event">', $context['calendar_only_today'] ? $txt['events'] : $txt['events_upcoming'], '</span> ';

		// Each event in calendar_events should have:
		//		title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
		foreach ($context['calendar_events'] as $event)
			echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '"><span class="generic_icons calendar_modify"></span></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br>' : ', ';
		echo '
				</p>';
	}
}

/**
 * The stats section of the info center
 */
function template_ic_block_stats()
{
	global $scripturl, $txt, $context, $settings;

	// Show statistical style information...
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', $scripturl, '?action=stats" title="', $txt['more_stats'], '"><span class="generic_icons stats"></span> ', $txt['forum_stats'], '</a>
				</h4>
			</div>
			<p class="inline">
				', $context['common_stats']['boardindex_total_posts'], '', !empty($settings['show_latest_member']) ? ' - ' . $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br>
				', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong>  (' . $context['latest_post']['time'] . ')<br>' : ''), '
				<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>
			</p>';
}

/**
 * The who's online section of the admin center
 */
function template_ic_block_online()
{
	global $context, $scripturl, $txt, $modSettings, $settings;
	// "Users online" - in order of activity.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<span class="generic_icons people"></span> ', $txt['online_users'], '', $context['show_who'] ? '</a>' : '', '
				</h4>
			</div>
			<p class="inline">
				', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<strong>', $txt['online'], ': </strong>', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . ($context['num_spiders'] == 1 ? $txt['hidden'] : $txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '

				&nbsp;-&nbsp;', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong>&nbsp;-&nbsp;
				', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (', timeformat($modSettings['mostDate']), ')<br>';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				', sprintf($txt['users_active'], $modSettings['lastActive']), ': ', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<span class="membergroups">' . implode(',&nbsp;', $context['membergroups']) . '</span>';
	}

	echo '
			</p>';
}

?>