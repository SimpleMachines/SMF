<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\Utils;
use SMF\User;

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
	// Show the news fader?  (assuming there are things to show...)
	if (!empty(Theme::$current->settings['show_newsfader']) && !empty(Utils::$context['news_lines']))
	{
		echo '
		<ul id="smf_slider" class="roundframe">';

		foreach (Utils::$context['news_lines'] as $news)
			echo '
			<li>', $news, '</li>';

		echo '
		</ul>
		<script>
			jQuery("#smf_slider").slippry({
				pause: ', Theme::$current->settings['newsfader_time'], ',
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
	echo '
	<div id="boardindex_table" class="boardindex_table">';

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	foreach (Utils::$context['categories'] as $category)
	{
		// If theres no parent boards we can see, avoid showing an empty category (unless its collapsed)
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
		<div class="main_container">
			<div class="cat_bar ', $category['is_collapsed'] ? 'collapsed' : '', '" id="category_', $category['id'], '">
				<h3 class="catbg">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
					<span id="category_', $category['id'], '_upshrink" class="', $category['is_collapsed'] ? 'toggle_down' : 'toggle_up', ' floatright" data-collapsed="', (int) $category['is_collapsed'], '" title="', !$category['is_collapsed'] ? Lang::$txt['hide_category'] : Lang::$txt['show_category'], '" style="display: none;"></span>';

		echo '
					', $category['link'], '
				</h3>', !empty($category['description']) ? '
				<div class="desc">' . $category['description'] . '</div>' : '', '
			</div>
			<div id="category_', $category['id'], '_boards" ', (!empty($category['css_class']) ? ('class="' . $category['css_class'] . '"') : ''), $category['is_collapsed'] ? ' style="display: none;"' : '', '>';

		/* Each board in each category's boards has:
		new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
		children (see below.), link_children (easier to use.), children_new (are they new?),
		topics (# of), posts (# of), link, href, and last_post. (see below.) */
		foreach ($category['boards'] as $board)
		{
			echo '
				<div id="board_', $board['id'], '" class="up_contain ', (!empty($board['css_class']) ? $board['css_class'] : ''), '">
					<div class="board_icon">
						', function_exists('template_bi_' . $board['type'] . '_icon') ? call_user_func('template_bi_' . $board['type'] . '_icon', $board) : template_bi_board_icon($board), '
					</div>
					<div class="info">
						', function_exists('template_bi_' . $board['type'] . '_info') ? call_user_func('template_bi_' . $board['type'] . '_info', $board) : template_bi_board_info($board), '
					</div><!-- .info -->';

			// Show some basic information about the number of posts, etc.
			echo '
					<div class="board_stats">
						', function_exists('template_bi_' . $board['type'] . '_stats') ? call_user_func('template_bi_' . $board['type'] . '_stats', $board) : template_bi_board_stats($board), '
					</div>';

			// Show the last post if there is one.
			echo'
					<div class="lastpost">
						', function_exists('template_bi_' . $board['type'] . '_lastpost') ? call_user_func('template_bi_' . $board['type'] . '_lastpost', $board) : template_bi_board_lastpost($board), '
					</div>';

			// Won't somebody think of the children!
			if (function_exists('template_bi_' . $board['type'] . '_children'))
				call_user_func('template_bi_' . $board['type'] . '_children', $board);
			else
				template_bi_board_children($board);

			echo '
				</div><!-- #board_[id] -->';
		}

		echo '
			</div><!-- #category_[id]_boards -->
		</div><!-- .main_container -->';
	}

	echo '
	</div><!-- #boardindex_table -->';

	// Show the mark all as read button?
	if (User::$me->is_logged && !empty(Utils::$context['categories']))
		echo '
	<div class="mark_read">
		', template_button_strip(Utils::$context['mark_read_button'], 'right'), '
	</div>';
}

/**
 * Outputs the board icon for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_icon($board)
{
	echo '
		<a href="', (User::$me->is_guest ? $board['href'] : Config::$scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board icon for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_icon($board)
{
	echo '
		<a href="', $board['href'], '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board info for a standard board or redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_board_info($board)
{
	echo '
		<a class="subject mobile_subject" href="', $board['href'], '" id="b', $board['id'], '">
			', $board['name'], '
		</a>';

	// Has it outstanding posts for approval?
	if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
		echo '
		<a href="', Config::$scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" title="', sprintf(Lang::$txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link amt">!</a>';

	echo '
		<div class="board_description">', $board['description'], '</div>';

	// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
	if (!empty($board['link_moderators']))
		echo '
		<p class="moderators">', count($board['link_moderators']) == 1 ? Lang::$txt['moderator'] : Lang::$txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';
}

/**
 * Outputs the board stats for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_stats($board)
{
	echo '
		<p>
			', Lang::$txt['posts'], ': ', Lang::numberFormat($board['posts']), '<br>', Lang::$txt['board_topics'], ': ', Lang::numberFormat($board['topics']), '
		</p>';
}

/**
 * Outputs the board stats for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_stats($board)
{
	echo '
		<p>
			', Lang::$txt['redirects'], ': ', Lang::numberFormat($board['posts']), '
		</p>';
}

/**
 * Outputs the board lastposts for a standard board or a redirect.
 * When on a mobile device, this may be hidden if no last post exists.
 *
 * @param array $board Current board information.
 */
function template_bi_board_lastpost($board)
{
	if (!empty($board['last_post']['id']))
		echo '
			<p>', $board['last_post']['last_post_message'], '</p>';
}

/**
 * Outputs the board children for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_children($board)
{
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
				$child['link'] = '' . ($child['new'] ? '<a href="' . Config::$scripturl . '?action=unread;board=' . $child['id'] . '" title="' . Lang::$txt['new_posts'] . ' (' . Lang::$txt['board_topics'] . ': ' . Lang::numberFormat($child['topics']) . ', ' . Lang::$txt['posts'] . ': ' . Lang::numberFormat($child['posts']) . ')" class="new_posts">' . Lang::$txt['new'] . '</a> ' : '') . '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? Lang::$txt['new_posts'] : Lang::$txt['old_posts']) . ' (' . Lang::$txt['board_topics'] . ': ' . Lang::numberFormat($child['topics']) . ', ' . Lang::$txt['posts'] . ': ' . Lang::numberFormat($child['posts']) . ')">' . $child['name'] . '</a>';
			else
				$child['link'] = '<a href="' . $child['href'] . '" title="' . Lang::numberFormat($child['posts']) . ' ' . Lang::$txt['redirects'] . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>';

			// Has it posts awaiting approval?
			if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
				$child['link'] .= ' <a href="' . Config::$scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" title="' . sprintf(Lang::$txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link amt">!</a>';

			$children[] = $child['new'] ? '<span class="strong">' . $child['link'] . '</span>' : '<span>' . $child['link'] . '</span>';
		}

		echo '
			<div id="board_', $board['id'], '_children" class="children">
				<p><strong id="child_list_', $board['id'], '">', Lang::$txt['sub_boards'], '</strong>', implode(' ', $children), '</p>
			</div>';
	}
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
	if (empty(Utils::$context['info_center']))
		return;

	// Here's where the "Info Center" starts...
	echo '
	<div class="roundframe" id="info_center">
		<div class="title_bar">
			<h3 class="titlebg">
				<span class="toggle_up floatright" id="upshrink_ic" title="', Lang::$txt['hide_infocenter'], '" style="display: none;"></span>
				<a href="#" id="upshrink_link">', sprintf(Lang::$txt['info_center_title'], Utils::$context['forum_name_html_safe']), '</a>
			</h3>
		</div>
		<div id="upshrink_stats"', empty(Theme::$current->options['collapse_header_ic']) ? '' : ' style="display: none;"', '>';

	foreach (Utils::$context['info_center'] as $block)
	{
		$func = 'template_ic_block_' . $block['tpl'];
		$func();
	}

	echo '
		</div><!-- #upshrink_stats -->
	</div><!-- #info_center -->';

	// Info center collapse object.
	echo '
	<script>
		var oInfoCenterToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty(Theme::$current->options['collapse_header_ic']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'upshrink_stats\'
			],
			aSwapImages: [
				{
					sId: \'upshrink_ic\',
					altExpanded: ', Utils::JavaScriptEscape(Lang::$txt['hide_infocenter']), ',
					altCollapsed: ', Utils::JavaScriptEscape(Lang::$txt['show_infocenter']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'upshrink_link\',
					msgExpanded: ', Utils::JavaScriptEscape(sprintf(Lang::$txt['info_center_title'], Utils::$context['forum_name_html_safe'])), ',
					msgCollapsed: ', Utils::JavaScriptEscape(sprintf(Lang::$txt['info_center_title'], Utils::$context['forum_name_html_safe'])), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', User::$me->is_guest ? 'false' : 'true', ',
				sOptionName: \'collapse_header_ic\',
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
			},
			oCookieOptions: {
				bUseCookie: ', User::$me->is_guest ? 'true' : 'false', ',
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
	// This is the "Recent Posts" bar.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', Config::$scripturl, '?action=recent"><span class="main_icons recent_posts"></span> ', Lang::$txt['recent_posts'], '</a>
				</h4>
			</div>
			<div id="recent_posts_content">';

	// Only show one post.
	if (Theme::$current->settings['number_recent_posts'] == 1)
	{
		// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
		echo '
				<p id="infocenter_onepost" class="inline">
					<a href="', Config::$scripturl, '?action=recent">', Lang::$txt['recent_view'], '</a> ', sprintf(Lang::$txt['is_recent_updated'], '&quot;' . Utils::$context['latest_post']['link'] . '&quot;'), ' (', Utils::$context['latest_post']['time'], ')<br>
				</p>';
	}
	// Show lots of posts.
	elseif (!empty(Utils::$context['latest_posts']))
	{
		echo '
				<table id="ic_recentposts">
					<tr class="windowbg">
						<th class="recentpost">', Lang::$txt['message'], '</th>
						<th class="recentposter">', Lang::$txt['author'], '</th>
						<th class="recentboard">', Lang::$txt['board'], '</th>
						<th class="recenttime">', Lang::$txt['date'], '</th>
					</tr>';

		/* Each post in latest_posts has:
			board (with an id, name, and link.), topic (the topic's id.), member (with id, name, and link.),
			subject, short_subject (shortened with...), time, link, and href. */
		foreach (Utils::$context['latest_posts'] as $post)
			echo '
					<tr class="windowbg">
						<td class="recentpost"><strong>', $post['link'], '</strong></td>
						<td class="recentposter">', $post['member']['link'], '</td>
						<td class="recentboard">', $post['board']['link'], '</td>
						<td class="recenttime">', $post['time'], '</td>
					</tr>';
		echo '
				</table>';
	}
	echo '
			</div><!-- #recent_posts_content -->';
}

/**
 * The calendar section of the info center
 */
function template_ic_block_calendar()
{
	// Show information about events, birthdays, and holidays on the calendar.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', Config::$scripturl, '?action=calendar' . '"><span class="main_icons calendar"></span> ', Utils::$context['calendar_only_today'] ? Lang::$txt['calendar_today'] : Lang::$txt['calendar_upcoming'], '</a>
				</h4>
			</div>';

	// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P
	if (!empty(Utils::$context['calendar_holidays']))
		echo '
			<p class="inline holiday">
				<span>', Lang::$txt['calendar_prompt'], '</span> ', implode(', ', Utils::$context['calendar_holidays']), '
			</p>';

	// People's birthdays. Like mine. And yours, I guess. Kidding.
	if (!empty(Utils::$context['calendar_birthdays']))
	{
		echo '
			<p class="inline">
				<span class="birthday">', Utils::$context['calendar_only_today'] ? Lang::$txt['birthdays'] : Lang::$txt['birthdays_upcoming'], '</span>';

		// Each member in calendar_birthdays has: id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?)
		foreach (Utils::$context['calendar_birthdays'] as $member)
			echo '
				<a href="', Config::$scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong class="fix_rtl_names">' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';

		echo '
			</p>';
	}

	// Events like community get-togethers.
	if (!empty(Utils::$context['calendar_events']))
	{
		echo '
			<p class="inline">
				<span class="event">', Utils::$context['calendar_only_today'] ? Lang::$txt['events'] : Lang::$txt['events_upcoming'], '</span> ';

		// Each event in calendar_events should have:
		//		title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
		foreach (Utils::$context['calendar_events'] as $event)
			echo '
				', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . Lang::$txt['calendar_edit'] . '"><span class="main_icons calendar_modify"></span></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br>' : ', ';
		echo '
			</p>';
	}
}

/**
 * The stats section of the info center
 */
function template_ic_block_stats()
{
	// Show statistical style information...
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					', Utils::$context['show_stats'] ? '<a href="' . Config::$scripturl . '?action=stats" title="' . Lang::$txt['more_stats'] . '">' : '', '<span class="main_icons stats"></span> ', Lang::$txt['forum_stats'], Utils::$context['show_stats'] ? '</a>' : '', '
				</h4>
			</div>
			<p class="inline">
				', Utils::$context['common_stats']['boardindex_total_posts'], '', !empty(Theme::$current->settings['show_latest_member']) ? ' - ' . Lang::$txt['latest_member'] . ': <strong> ' . Utils::$context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br>
				', (!empty(Utils::$context['latest_post']) ? Lang::$txt['latest_post'] . ': <strong>&quot;' . Utils::$context['latest_post']['link'] . '&quot;</strong>  (' . Utils::$context['latest_post']['time'] . ')<br>' : ''), '
				<a href="', Config::$scripturl, '?action=recent">', Lang::$txt['recent_view'], '</a>
			</p>';
}

/**
 * The who's online section of the info center
 */
function template_ic_block_online()
{
	// "Users online" - in order of activity.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					', Utils::$context['show_who'] ? '<a href="' . Config::$scripturl . '?action=who">' : '', '<span class="main_icons people"></span> ', Lang::$txt['online_users'], '', Utils::$context['show_who'] ? '</a>' : '', '
				</h4>
			</div>
			<p class="inline">
				', Utils::$context['show_who'] ? '<a href="' . Config::$scripturl . '?action=who">' : '', '<strong>', Lang::$txt['online'], ': </strong>', Lang::numberFormat(Utils::$context['num_guests']), ' ', Utils::$context['num_guests'] == 1 ? Lang::$txt['guest'] : Lang::$txt['guests'], ', ', Lang::numberFormat(Utils::$context['num_users_online']), ' ', Utils::$context['num_users_online'] == 1 ? Lang::$txt['user'] : Lang::$txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();

	if (Utils::$context['show_buddies'])
		$bracketList[] = Lang::numberFormat(Utils::$context['num_buddies']) . ' ' . (Utils::$context['num_buddies'] == 1 ? Lang::$txt['buddy'] : Lang::$txt['buddies']);

	if (!empty(Utils::$context['num_spiders']))
		$bracketList[] = Lang::numberFormat(Utils::$context['num_spiders']) . ' ' . (Utils::$context['num_spiders'] == 1 ? Lang::$txt['spider'] : Lang::$txt['spiders']);

	if (!empty(Utils::$context['num_users_hidden']))
		$bracketList[] = Lang::numberFormat(Utils::$context['num_users_hidden']) . ' ' . (Utils::$context['num_spiders'] == 1 ? Lang::$txt['hidden'] : Lang::$txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo Utils::$context['show_who'] ? '</a>' : '', '

				&nbsp;-&nbsp;', Lang::$txt['most_online_today'], ': <strong>', Lang::numberFormat(Config::$modSettings['mostOnlineToday']), '</strong>&nbsp;-&nbsp;
				', Lang::$txt['most_online_ever'], ': ', Lang::numberFormat(Config::$modSettings['mostOnline']), ' (', Time::create('@' . Config::$modSettings['mostDate'])->format(), ')<br>';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty(Utils::$context['users_online']))
	{
		echo '
				', sprintf(Lang::$txt['users_active'], Config::$modSettings['lastActive']), ': ', implode(', ', Utils::$context['list_users_online']);

		// Showing membergroups?
		if (!empty(Theme::$current->settings['show_group_key']) && !empty(Utils::$context['membergroups']))
			echo '
				<span class="membergroups">' . implode(', ', Utils::$context['membergroups']) . '</span>';
	}

	echo '
			</p>';
}

?>