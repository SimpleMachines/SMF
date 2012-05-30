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
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Show some statistics if stat info is off.
//	if (!$settings['show_stats_index'])
//		echo '
//	<div id="index_common_stats">
//		', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics'], ': ', $context['common_stats']['total_topics'], '<br />
//		', ($settings['show_latest_member'] ? ' ' . $txt['welcome_member'] . ' <strong>' . $context['common_stats']['latest_member']['link'] . '</strong>' . $txt['newest_member'] : '') , '
//	</div>';

	// Show the news fader?  (assuming there are things to show...)
	if ($settings['show_newsfader'] && !empty($context['fader_news_lines']))
	{
		echo '
	<div id="newsfader">
		<div class="cat_bar">
			<h3 class="catbg">
				<img id="newsupshrink" src="', $settings['images_url'], '/collapse.png" alt="*" title="', $txt['upshrink_description'], '" align="bottom" style="display: none;" />
				', $txt['news'], '
			</h3>
		</div>
		<ul class="reset" id="smfFadeScroller"', empty($options['collapse_news_fader']) ? '' : ' style="display: none;"', '>';

			foreach ($context['news_lines'] as $news)
				echo '
			<li>', $news, '</li>';

	echo '
		</ul>
	</div>
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/fader.js"></script>
	<script type="text/javascript"><!-- // --><![CDATA[

		// Create a news fader object.
		var oNewsFader = new smf_NewsFader({
			sSelf: \'oNewsFader\',
			sFaderControlId: \'smfFadeScroller\',
			sItemTemplate: ', JavaScriptEscape('<strong>%1$s</strong>'), ',
			iFadeDelay: ', empty($settings['newsfader_time']) ? 5000 : $settings['newsfader_time'], '
		});

		// Create the news fader toggle.
		var smfNewsFadeToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($options['collapse_news_fader']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'smfFadeScroller\'
			],
			aSwapImages: [
				{
					sId: \'newsupshrink\',
					srcExpanded: smf_images_url + \'/collapse.png\',
					altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
					srcCollapsed: smf_images_url + \'/expand.png\',
					altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'collapse_news_fader\',
				sSessionVar: smf_session_var,
				sSessionId: smf_session_id
			},
			oCookieOptions: {
				bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
				sCookieName: \'newsupshrink\'
			}
		});
	// ]]></script>';
	}

	echo ' <a href="#" id="skipnav_target"></a>';

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
		<div class="category_wrapper">
		<div id="category_', $category['id'], '" class="cat_bar">
			<h3 class="catbg">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
				<a class="collapse" href="', $category['collapse_href'], '" title="' ,$category['is_collapsed'] ? $txt['show'] : $txt['hide'] ,'">', $category['collapse_image'], '</a>';

		if (!$context['user']['is_guest'] && !empty($category['show_unread']))
			echo '
				<a class="cat_unread" href="', $scripturl, '?action=unread;c=', $category['id'], '" title="', $txt['view_unread_category'], '">', $category['name'], '</a>';
		else
			echo '
				', $category['name'];
			echo '
				<span style="margin-left: -999em;">', $category['link'], '</span>
			</h3>
		</div>';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
		{

		echo '
			<ul class="forum_category" id="category_', $category['id'], '_boards">
				<li class="titlebg subheader">
					<div class="icon">&nbsp;</div>
					<div class="info">', $txt['board_name'], '</div>
					<div class="stats">
						<ul>
							<li class="stats">', $txt['forum_stats'], '</li>
							<li class="posts">', $txt['posts'], '</li>
							<li class="topics">', $txt['topics'], '</li>
						</ul>
					</div>
					<div class="lastpost">', $txt['latest_post'], '</div>
				</li>';
			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */
			foreach ($category['boards'] as $board)
			{
				echo '
				<li id="board_', $board['id'], '">
					<div class="windowbg icon">
						<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

				// If the board or children is new, show an indicator.
				if ($board['new'] || $board['children_new'])
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], ' ', $txt['in'], ' ', $board['name'], '" />';
				// Is it a redirection board?
				elseif ($board['is_redirect'])
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
				// No new posts at all! The agony!!
				else
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

				echo '
						</a>
					</div>
					<div class="windowbg2 info">
						<h4><a class="subject" href="', $board['href'], '" name="b', $board['id'], '">', $board['name'], '</a></h4>';

				// Has it outstanding posts for approval?
				if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
					echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				echo '
						<p>', $board['description'] , '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['moderators']))
					echo '
						<div class="moderators">
							<h5>', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ':</h5>&nbsp; ', implode(', ', $board['link_moderators']), '
						</div>';


				// Show some basic information about the number of posts, etc.
					echo '
					</div>
					<div class="windowbg stats">
						<ul>
							<li class="posts" style="display: none;">', comma_format($board['posts']), ' <span>', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], '</span></li>
							<li class="topics" style="font-size: 1em; color: #444; text-align: right;">', $board['last_post']['time'], '</li>
						</ul>
					</div>
					<div class="windowbg2 lastpost">';

				/* The board's and children's 'last_post's have:
				time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
				link, href, subject, start (where they should go for the first unread post.),
				and member. (which has id, name, link, href, username in it.) */
				if (!empty($board['last_post']['id']))
					echo '
						<ul>
							<li><h5>', $txt['last_post'], ' &nbsp; </h5><span>', $txt['by'], '</span>', $board['last_post']['member']['link'] , '</li>
							<li><span>', $txt['in'], '</span>', $board['last_post']['link'], '</li>
							<li><span>', $txt['on'], '</span>', $board['last_post']['time'], '</li>
						</ul>';
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
							$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><img src="' . $settings['lang_images_url'] . '/new.png" class="new_posts" alt="" />' : '') . '</a>';
						else
							$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

						// Has it posts awaiting approval?
						if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
							$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<li><strong>' . $child['link'] . '</strong></li>' : '<li> ' .$child['link'] . '</li>';
					}
					echo '
					<div id="board_', $board['id'], '_children" class="windowbg children">
							<h5>', $txt['parent_boards'], ':</h5><ul class="reset">', implode($children), '</ul>
					</div>';
				}
			echo '
				</li>';
			}
		echo '
				<li class="divider"></li>
			</ul>';
		}
	echo '
		</div>';
	}

	if ($context['user']['is_logged'])
	{
		echo '
	<div id="posting_icons" class="floatleft">';

		// Mark read button.
		$mark_read_button = array(
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']),
		);

		echo '
		<ul class="reset">
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_some.png" alt="" /> ', $txt['new_posts'], '</li>
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_none.png" alt="" /> ', $txt['old_posts'], '</li>
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_redirect.png" alt="" /> ', $txt['redirect_board'], '</li>
		</ul>
	</div>';


	}
	else
	{
		echo '
	<div id="posting_icons" class="flow_hidden">
		<ul class="reset">
			<li class="floatleft"><img src="', $settings['images_url'], '/new_none.png" alt="" /> ', $txt['old_posts'], '</li>
			<li class="floatleft"><img src="', $settings['images_url'], '/new_redirect.png" alt="" /> ', $txt['redirect_board'], '</li>
		</ul>
	</div>';
	}

	template_info_center();
}

function template_info_center()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
	<span class="clear upperframe"><span></span></span>
	<div id="info_center" class="roundframe"><div class="innerframe">
		<div class="cat_bar">
			<h3 class="catbg">
				<img class="icon" id="upshrink_ic" src="', $settings['images_url'], '/go_up.png" alt="*" title="', $txt['upshrink_description'], '" style="display: none;" />
				', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '
			</h3>
		</div>
		<div id="upshrinkHeaderIC"', empty($options['collapse_header_ic']) ? '' : ' style="display: none;"', '>';

	// This is the "Recent Posts" bar.
	if (!empty($settings['number_recent_posts']) && (!empty($context['latest_posts']) || !empty($context['latest_post'])))
	{
		echo '
			<div class="title_barIC">
				<h4 class="titlebg">
						<a href="', $scripturl, '?action=recent"><img class="icon" src="', $settings['images_url'], '/icons/recent.png" alt="" />', $txt['recent_posts'], '</a>
				</h4>
			</div>
			<div class="hslice" id="recent_posts_content">
				<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
				<div class="entry-content" style="display: none;">
					<a rel="feedurl" href="', $scripturl, '?action=.xml;type=webslice">', $txt['subscribe_webslice'], '</a>
				</div>';

		// Only show one post.
		if ($settings['number_recent_posts'] == 1)
		{
			// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
			echo '
				<p id="infocenter_onepost" class="middletext">
					&quot;', $context['latest_post']['link'], '&quot; ', $txt['recent_updated'], ' <span class="floatright">(', $context['latest_post']['time'], ')</span>
				</p>';
		}
		// Show lots of posts.
		elseif (!empty($context['latest_posts']))
		{
			//echo '
				//<dl id="ic_recentposts" class="middletext">';
echo '			<table style="width: 100%; max-width: 96em; padding: 0 0.5em 0.2em 0.5em; font-size: 0.917em;" cellspacing="0">';
			/* Each post in latest_posts has:
					board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
					subject, short_subject (shortened with...), time, link, and href. */
			foreach ($context['latest_posts'] as $post)
				echo '
					<tr>
						<td style="width: 35%; border-top: 1px solid #eee;"><strong>', $post['link'], '</strong></td>
						<td style="width: 15%; border-top: 1px solid #eee;"> ', $txt['by'], ' ', $post['poster']['link'], ' </td>
						<td style="width: 25%; border-top: 1px solid #eee;">(', $post['board']['link'], ')</td>
						<td style="width: 25%; border-top: 1px solid #eee; text-align: right;">', $post['time'], '</td>
					</tr>';
//			echo '
//				</dl>';
echo '			</table>';
		}
		echo '
			</div>';
	}

	// Show information about events, birthdays, and holidays on the calendar.
	if ($context['show_calendar'])
	{
		echo '
			<div class="title_barIC">
				<h4 class="titlebg">
						<a href="', $scripturl, '?action=calendar' . '"><img class="icon" src="', $settings['images_url'], '/icons/calendar.png', '" alt="" />', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '</a>
				</h4>
			</div>
			<div id="calendar_IC" style="line-height: 1.7em;">';

		// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P.
		if (!empty($context['calendar_holidays']))
				echo '
				<p class="smalltext" style="border-bottom: 1px solid #eee; margin: 0; padding: 0.2em 0.5em;"><span class="holiday">', $txt['calendar_prompt'], ' ', implode(', ', $context['calendar_holidays']), '</span></p>';

		// People's birthdays. Like mine. And yours, I guess. Kidding.
		if (!empty($context['calendar_birthdays']))
		{
				echo '
				<p class="smalltext" style="border-bottom: 1px solid #eee; margin: 0; padding: 0.2em 0.5em;"><span class="birthday">', $context['calendar_only_today'] ? $txt['birthdays'] : $txt['birthdays_upcoming'], '</span>';
		/* Each member in calendar_birthdays has:
				id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?) */
		foreach ($context['calendar_birthdays'] as $member)
				echo '
					<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong class="fix_rtl_names>' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '<br />' : ', ';
				echo '
				</p>';
		}
		// Events like community get-togethers.
		if (!empty($context['calendar_events']))
		{
			echo '
				<p class="smalltext" style="margin: 0; padding: 0.2em 0.5em;"><span class="event">', $context['calendar_only_today'] ? $txt['events'] : $txt['events_upcoming'], '</span>';
			/* Each event in calendar_events should have:
					title, href, is_last, can_edit (are they allowed?), modify_href, and is_today. */
			foreach ($context['calendar_events'] as $event)
				echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '"><img src="' . $settings['images_url'] . '/icons/modify_small.png" alt="*" class="centericon" /></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br />' : ', ';
			echo '
				</p>';
		}

		echo '
			</div>';
	}

	// Show statistical style information...
	if ($settings['show_stats_index'])
	{
		echo '
			<div class="title_barIC">
				<h4 class="titlebg">
						<a href="', $scripturl, '?action=stats"><img class="icon" src="', $settings['images_url'], '/icons/info.png" alt="" />', $txt['forum_stats'], '</a>
				</h4>
			</div>
			<ul id="stats_IC" style="max-width: 96em; margin: 0; padding: 0; font-size: 0.917em; line-height: 2em; overflow: auto; list-style: none;">
				', !empty($settings['show_latest_member']) ? '<li style="float: left; width: 49%; padding: 0 0.5%;">'. $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong></li>' : '', '
				', ($context['allow_memberlist']) ? '<li style="float: left; width: 49%; padding: 0 0.5%; text-align: right;"><a href="'. $scripturl. '?action=mlist;sort=registered;start=0;desc">New members</a></li>' : '', '

				<li style="float: left; width: 49%; padding: 0 0.5%; border-top: 1px solid #eee;">', $context['common_stats']['total_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', $context['common_stats']['total_topics'], ' ', $txt['topics'], ' ', $txt['by'], ' ', $context['common_stats']['total_members'], ' ', $txt['members'], '.</li>
				', $context['show_stats'] ? '<li style="float: left; width: 49%; padding: 0 0.5%; border-top: 1px solid #eee; text-align: right;"><a href="' . $scripturl . '?action=stats">Statistics Center</a></li>' : '', '

				<li style="float: left; width: 49%; padding: 0 0.5%; border-top: 1px solid #eee;">', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>' . $context['latest_post']['link'] . '</strong>&nbsp;(' . $context['latest_post']['time'] . ')</li>' : ''), '
				<li style="float: left; width: 49%; padding: 0 0.5%; border-top: 1px solid #eee; text-align: right;"><a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a></li>
			</ul>';
	}

	// "Users online" - in order of activity.
	echo '
			<div class="title_barIC" style="clear: both;">
				<h4 class="titlebg">

					<span class="inline stats" style="margin: 0 0 0 0; padding: 0 0 0 0; "><img class="icon" src="', $settings['images_url'], '/icons/online.png', '" alt="" />', $txt['online'], '&nbsp;Now:&nbsp; 
					', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ' . comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . $txt['hidden'];

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '
					</span>
				</h4>
			</div>
			<p id="online_IC" class="inline smalltext">';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				<span class="floatleft" style="display: block;">', sprintf($txt['users_active'], $modSettings['lastActive']), ':&nbsp;</span><span class="floatleft" style="display: block;>', implode(', ', $context['list_users_online']). '</span>';

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<span class="clear" style="display: block;">' . implode('&nbsp;|&nbsp;', $context['membergroups']) . '</span>';
	}

	echo '
			</p>
			<p class="last smalltext" style="max-width: 95em;">
				<span class="floatright">', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong></span>
				<br />', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (', timeformat($modSettings['mostDate']), ')
			</p>';

	echo '
		</div>
	</div></div>';

	// Info center collapse object.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var oInfoCenterToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($options['collapse_header_ic']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'upshrinkHeaderIC\'
			],
			aSwapImages: [
				{
					sId: \'upshrink_ic\',
					srcExpanded: smf_images_url + \'/go_up.png\',
					altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
					srcCollapsed: smf_images_url + \'/go_down.png\',
					altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
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

		$(document).ready(function() { 
			$("a.collapse, a.cat_unread").bt();
		});

	// ]]></script>';
}
?>