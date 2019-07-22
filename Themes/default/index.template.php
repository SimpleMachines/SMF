<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	https://www.simplemachines.org/
*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $settings, $txt;

	/* $context, $options and $txt may be available for use, but may not be fully populated yet. */

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	$settings['theme_version'] = '2.1';

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last and the first post on the message index and recent pages.
	$settings['avatars_on_indexes'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last post on the board index.
	$settings['avatars_on_boardIndex'] = false;

	// This defines the formatting for the page indexes used throughout the forum.
	$settings['page_index'] = array(
		'extra_before' => '<span class="pages">' . $txt['pages'] . '</span>',
		'previous_page' => '<span class="main_icons previous_page"></span>',
		'current_page' => '<span class="current_page">%1$d</span> ',
		'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
		'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
		'next_page' => '<span class="main_icons next_page"></span>',
		'extra_after' => '',
	);

	// Allow css/js files to be disabled for this specific theme.
	// Add the identifier as an array key. IE array('smf_script'); Some external files might not add identifiers, on those cases SMF uses its filename as reference.
	if (!isset($settings['disable_files']))
		$settings['disable_files'] = array();
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $scripturl, $txt, $modSettings;

	// Show right to left, the language code, and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', !empty($txt['lang_locale']) ? ' lang="' . str_replace("_", "-", substr($txt['lang_locale'], 0, strcspn($txt['lang_locale'], "."))) . '"' : '', '>
<head>
	<meta charset="', $context['character_set'], '">';

	/*
		You don't need to manually load index.css, this will be set up for you.
		Note that RTL will also be loaded for you.

		The most efficient way of writing multi themes is to use a master
		index.css plus variant.css files. If you've set them up properly
		(through $settings['theme_variants']), the variant files will be loaded
		for you automatically.

		If you want to load other CSS files, the best way is to use the
		'integrate_load_theme' integration hook and the loadCSSFile() function.
		This approach will let you take advantage of SMF's automatic CSS
		minimization and other benefits. You can, of course, manually add any
		other files you want after template_css() has been run.
	*/

	// load in any css from mods or themes so they can overwrite if wanted
	template_css();

	// load in any javascript files from mods and themes
	template_javascript();

	echo '
	<title>', $context['page_title_html_safe'], '</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">';

	// Content related meta tags, like description, keywords, Open Graph stuff, etc...
	foreach ($context['meta_tags'] as $meta_tag)
	{
		echo '
	<meta';

		foreach ($meta_tag as $meta_key => $meta_value)
			echo ' ', $meta_key, '="', $meta_value, '"';

		echo '>';
	}

	/*	What is your Lollipop's color?
		Theme Authors, you can change the color here to make sure your theme's main color gets visible on tab */
	echo '
	<meta name="theme-color" content="#557EA0">';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '">';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help">
	<link rel="contents" href="', $scripturl, '">', ($context['allow_search'] ? '
	<link rel="search" href="' . $scripturl . '?action=search">' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?action=.xml;type=rss2', !empty($context['current_board']) ? ';board=' . $context['current_board'] : '', '">
	<link rel="alternate" type="application/atom+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $scripturl, '?action=.xml;type=atom', !empty($context['current_board']) ? ';board=' . $context['current_board'] : '', '">';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['links']['next']))
		echo '
	<link rel="next" href="', $context['links']['next'], '">';

	if (!empty($context['links']['prev']))
		echo '
	<link rel="prev" href="', $context['links']['prev'], '">';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0">';

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body id="', $context['browser_body_id'], '" class="action_', !empty($context['current_action']) ? $context['current_action'] : (!empty($context['current_board']) ?
		'messageindex' : (!empty($context['current_topic']) ? 'display' : 'home')), !empty($context['current_board']) ? ' board_' . $context['current_board'] : '', '">
<div id="footerfix">';
}

/**
 * The upper part of the main template layer. This is the stuff that shows above the main forum content.
 */
function template_body_above()
{
	global $context, $settings, $scripturl, $txt, $modSettings, $maintenance;

	// Wrapper div now echoes permanently for better layout options. h1 a is now target for "Go up" links.
	echo '
	<div id="top_section">
		<div class="inner_wrap">';

	// If the user is logged in, display some things that might be useful.
	if ($context['user']['is_logged'])
	{
		// Firstly, the user's menu
		echo '
			<ul class="floatleft" id="top_info">
				<li>
					<a href="', $scripturl, '?action=profile"', !empty($context['self_profile']) ? ' class="active"' : '', ' id="profile_menu_top" onclick="return false;">';

		if (!empty($context['user']['avatar']))
			echo $context['user']['avatar']['image'];

		echo $context['user']['name'], '</a>
					<div id="profile_menu" class="top_menu"></div>
				</li>';

		// Secondly, PMs if we're doing them
		if ($context['allow_pm'])
			echo '
				<li>
					<a href="', $scripturl, '?action=pm"', !empty($context['self_pm']) ? ' class="active"' : '', ' id="pm_menu_top">', $txt['pm_short'], !empty($context['user']['unread_messages']) ? ' <span class="amt">' . $context['user']['unread_messages'] . '</span>' : '', '</a>
					<div id="pm_menu" class="top_menu scrollable"></div>
				</li>';

		// Thirdly, alerts
		echo '
				<li>
					<a href="', $scripturl, '?action=profile;area=showalerts;u=', $context['user']['id'], '"', !empty($context['self_alerts']) ? ' class="active"' : '', ' id="alerts_menu_top">', $txt['alerts'], !empty($context['user']['alerts']) ? ' <span class="amt">' . $context['user']['alerts'] . '</span>' : '', '</a>
					<div id="alerts_menu" class="top_menu scrollable"></div>
				</li>';

		// And now we're done.
		echo '
			</ul>';
	}
	// Otherwise they're a guest. Ask them to either register or login.
	elseif (empty($maintenance))
		echo '
			<ul class="floatleft welcome">
				<li>', sprintf($txt[$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest'], $txt['guest_title'], $context['forum_name_html_safe'], $scripturl . '?action=login', 'return reqOverlayDiv(this.href, ' . JavaScriptEscape($txt['login']) . ');', $scripturl . '?action=signup'), '</li>
			</ul>';
	else
		// In maintenance mode, only login is allowed and don't show OverlayDiv
		echo '
			<ul class="floatleft welcome">
				<li>', sprintf($txt['welcome_guest'], $txt['guest_title'], '', $scripturl . '?action=login', 'return true;'), '</li>
			</ul>';

	if (!empty($modSettings['userLanguage']) && !empty($context['languages']) && count($context['languages']) > 1)
	{
		echo '
			<form id="languages_form" method="get" class="floatright">
				<select id="language_select" name="language" onchange="this.form.submit()">';

		foreach ($context['languages'] as $language)
			echo '
					<option value="', $language['filename'], '"', isset($context['user']['language']) && $context['user']['language'] == $language['filename'] ? ' selected="selected"' : '', '>', str_replace('-utf8', '', $language['name']), '</option>';

		echo '
				</select>
				<noscript>
					<input type="submit" value="', $txt['quick_mod_go'], '">
				</noscript>
			</form>';
	}

	if ($context['allow_search'])
	{
		echo '
			<form id="search_form" class="floatright" action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<input type="search" name="search" value="">&nbsp;';

		// Using the quick search dropdown?
		$selected = !empty($context['current_topic']) ? 'current_topic' : (!empty($context['current_board']) ? 'current_board' : 'all');

		echo '
				<select name="search_selection">
					<option value="all"', ($selected == 'all' ? ' selected' : ''), '>', $txt['search_entireforum'], ' </option>';

		// Can't limit it to a specific topic if we are not in one
		if (!empty($context['current_topic']))
			echo '
					<option value="topic"', ($selected == 'current_topic' ? ' selected' : ''), '>', $txt['search_thistopic'], '</option>';

		// Can't limit it to a specific board if we are not in one
		if (!empty($context['current_board']))
			echo '
					<option value="board"', ($selected == 'current_board' ? ' selected' : ''), '>', $txt['search_thisboard'], '</option>';

		// Can't search for members if we can't see the memberlist
		if (!empty($context['allow_memberlist']))
			echo '
					<option value="members"', ($selected == 'members' ? ' selected' : ''), '>', $txt['search_members'], ' </option>';

		echo '
				</select>';

		// Search within current topic?
		if (!empty($context['current_topic']))
			echo '
				<input type="hidden" name="sd_topic" value="', $context['current_topic'], '">';

		// If we're on a certain board, limit it to this board ;).
		elseif (!empty($context['current_board']))
			echo '
				<input type="hidden" name="sd_brd" value="', $context['current_board'], '">';

		echo '
				<input type="submit" name="search2" value="', $txt['search'], '" class="button">
				<input type="hidden" name="advanced" value="0">
			</form>';
	}

	echo '
		</div><!-- .inner_wrap -->
	</div><!-- #top_section -->';

	echo '
	<div id="header">
		<h1 class="forumtitle">
			<a id="top" href="', $scripturl, '">', empty($context['header_logo_url_html_safe']) ? $context['forum_name_html_safe'] : '<img src="' . $context['header_logo_url_html_safe'] . '" alt="' . $context['forum_name_html_safe'] . '">', '</a>
		</h1>';

	echo '
		', empty($settings['site_slogan']) ? '<img id="smflogo" src="' . $settings['images_url'] . '/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">' : '<div id="siteslogan">' . $settings['site_slogan'] . '</div>', '';

	echo '
	</div>
	<div id="wrapper">
		<div id="upper_section">
			<div id="inner_section">
				<div id="inner_wrap">
					<div class="user">
						', $context['current_time'], '
					</div>';

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']) && !empty($context['random_news_line']))
		echo '
					<div class="news">
						<h2>', $txt['news'], ': </h2>
						<p>', $context['random_news_line'], '</p>
					</div>';

	echo '
					<hr class="clear">
				</div>';

	// Show the menu here, according to the menu sub template, followed by the navigation tree.
	// Load mobile menu here
	echo '
				<a class="menu_icon mobile_user_menu"></a>
				<div id="main_menu">
					<div id="mobile_user_menu" class="popup_container">
						<div class="popup_window description">
							<div class="popup_heading">', $txt['mobile_user_menu'], '
								<a href="javascript:void(0);" class="main_icons hide_popup"></a>
							</div>
							', template_menu(), '
						</div>
					</div>
				</div>';

	theme_linktree();

	echo '
			</div><!-- #inner_section -->
		</div><!-- #upper_section -->';

	// The main content should go here.
	echo '
		<div id="content_section">
			<div id="main_content_section">';
}

/**
 * The stuff shown immediately below the main content, including the footer
 */
function template_body_below()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
			</div><!-- #main_content_section -->
		</div><!-- #content_section -->
	</div><!-- #wrapper -->
</div><!-- #footerfix -->';

	// Show the footer with copyright, terms and help links.
	echo '
	<div id="footer">
		<div class="inner_wrap">';

	// There is now a global "Go to top" link at the right.
	echo '
		<ul>
			<li class="floatright"><a href="', $scripturl, '?action=help">', $txt['help'], '</a> ', (!empty($modSettings['requireAgreement'])) ? '| <a href="' . $scripturl . '?action=help;sa=rules">' . $txt['terms_and_rules'] . '</a>' : '', ' | <a href="#top_section">', $txt['go_up'], ' &#9650;</a></li>
			<li class="copyright">', theme_copyright(), '</li>
		</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
		<p>', sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']), '</p>';

	echo '
		</div>
	</div><!-- #footer -->';

}

/**
 * This shows any deferred JavaScript and closes out the HTML
 */
function template_html_below()
{
	// Load in any javascipt that could be deferred to the end of the page
	template_javascript(true);

	echo '
</body>
</html>';
}

/**
 * Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
 *
 * @param bool $force_show Whether to force showing it even if settings say otherwise
 */
function theme_linktree($force_show = false)
{
	global $context, $shown_linktree, $scripturl, $txt;

	// If linktree is empty, just return - also allow an override.
	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;
	echo '
				<div class="navigate_section">
					<ul>';

	if ($context['user']['is_logged'])
		echo '
						<li class="unread_links">
							<a href="', $scripturl, '?action=unread" title="', $txt['unread_since_visit'], '">', $txt['view_unread_category'], '</a>
							<a href="', $scripturl, '?action=unreadreplies" title="', $txt['show_unread_replies'], '">', $txt['unread_replies'], '</a>
						</li>';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach ($context['linktree'] as $link_num => $tree)
	{
		echo '
						<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Don't show a separator for the first one.
		// Better here. Always points to the next level when the linktree breaks to a second line.
		// Picked a better looking HTML entity, and added support for RTL plus a span for styling.
		if ($link_num != 0)
			echo '
							<span class="dividers">', $context['right_to_left'] ? ' &#9668; ' : ' &#9658; ', '</span>';

		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'], ' ';

		// Show the link, including a URL if it should have one.
		if (isset($tree['url']))
			echo '
							<a href="' . $tree['url'] . '"><span>' . $tree['name'] . '</span></a>';
		else
			echo '
							<span>' . $tree['name'] . '</span>';

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo ' ', $tree['extra_after'];

		echo '
						</li>';
	}

	echo '
					</ul>
				</div><!-- .navigate_section -->';

	$shown_linktree = true;
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	global $context;

	echo '
					<ul class="dropmenu menu_nav">';

	// Note: Menu markup has been cleaned up to remove unnecessary spans and classes.
	foreach ($context['menu_buttons'] as $act => $button)
	{
		echo '
						<li class="button_', $act, '', !empty($button['sub_buttons']) ? ' subsections"' : '"', '>
							<a', $button['active_button'] ? ' class="active"' : '', ' href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', '>
								', $button['icon'], '<span class="textmenu">', $button['title'], !empty($button['amt']) ? ' <span class="amt">' . $button['amt'] . '</span>' : '', '</span>
							</a>';

		// 2nd level menus
		if (!empty($button['sub_buttons']))
		{
			echo '
							<ul>';

			foreach ($button['sub_buttons'] as $childbutton)
			{
				echo '
								<li', !empty($childbutton['sub_buttons']) ? ' class="subsections"' : '', '>
									<a href="', $childbutton['href'], '"', isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', '>
										', $childbutton['title'], !empty($childbutton['amt']) ? ' <span class="amt">' . $childbutton['amt'] . '</span>' : '', '
									</a>';
				// 3rd level menus :)
				if (!empty($childbutton['sub_buttons']))
				{
					echo '
									<ul>';

					foreach ($childbutton['sub_buttons'] as $grandchildbutton)
						echo '
										<li>
											<a href="', $grandchildbutton['href'], '"', isset($grandchildbutton['target']) ? ' target="' . $grandchildbutton['target'] . '"' : '', '>
												', $grandchildbutton['title'], !empty($grandchildbutton['amt']) ? ' <span class="amt">' . $grandchildbutton['amt'] . '</span>' : '', '
											</a>
										</li>';

					echo '
									</ul>';
				}

				echo '
								</li>';
			}
			echo '
							</ul>';
		}
		echo '
						</li>';
	}

	echo '
					</ul><!-- .menu_nav -->';
}

/**
 * Generate a strip of buttons.
 *
 * @param array $button_strip An array with info for displaying the strip
 * @param string $direction The direction
 * @param array $strip_options Options for the button strip
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $context, $txt;

	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!)
		if (!isset($value['test']) || !empty($context[$value['test']]))
		{
			if (!isset($value['id']))
				$value['id'] = $key;

			$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>'.(!empty($value['icon']) ? '<span class="main_icons '.$value['icon'].'"></span>' : '').'' . $txt[$value['text']] . '</a>';

			if (!empty($value['sub_buttons']))
			{
				$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';
				foreach ($value['sub_buttons'] as $element)
				{
					if (isset($element['test']) && empty($context[$element['test']]))
						continue;

					$button .= '
								<a href="' . $element['url'] . '"><strong>' . $txt[$element['text']] . '</strong>';
					if (isset($txt[$element['text'] . '_desc']))
						$button .= '<br><span>' . $txt[$element['text'] . '_desc'] . '</span>';
					$button .= '</a>';
				}
				$button .= '
							</div><!-- .overview -->
						</div><!-- .viewport -->
					</div><!-- .top_menu -->';
			}

			$buttons[] = $button;
		}
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
			', implode('', $buttons), '
		</div>';
}

/**
 * Generate a list of quickbuttons.
 *
 * @param array $list_items An array with info for displaying the strip
 * @param string $list_id unique list id, used for integration hooks
 */
function template_quickbuttons($list_items, $list_id = null, $output_method = 'echo')
{
	global $txt;

	// Enable manipulation with hooks
	if(!empty($list_id))
		call_integration_hook('integrate_' . $list_id . '_quickbuttons', array(&$list_items));

	// Make sure the list has at least one shown item
	foreach ($list_items as $key => $li)
	{
		// Is there a sublist, and does it have any shown items
		if ($key == 'more')
		{
			foreach ($li as $subkey => $subli)
				if (isset($subli['show']) && !$subli['show'])
					unset($list_items[$key][$subkey]);

			if (empty($list_items[$key]))
				unset($list_items[$key]);
		}
		// A normal list item
		elseif (isset($li['show']) && !$li['show'])
			unset($list_items[$key]);
	}

	// Now check if there are any items left
	if (empty($list_items))
		return;

	// Print the quickbuttons
	$output = '
		<ul' . (!empty($list_id) ? ' id="quickbuttons_'.$list_id.'"' : '') . ' class="quickbuttons">';

	// This is used for a list item or a sublist item
	$list_item_format = function($li)
	{
		$html = '
			<li' . (!empty($li['class']) ? ' class="'.$li['class'].'"' : '') . (!empty($li['id']) ? ' id="'.$li['id'].'"' : '') . (!empty($li['custom']) ? $li['custom'] : '') . '>';

		if(isset($li['content']))
			$html .= $li['content'];
		else
			$html .= '
				<a' . (!empty($li['href']) ? ' href="'.$li['href'].'"' : '') . (!empty($li['javascript']) ? $li['javascript'] : '') . '>
					' . (!empty($li['icon']) ? '<span class="main_icons '.$li['icon'].'"></span>' : '') . (!empty($li['label']) ? $li['label'] : '') . '
				</a>';

		$html .= '
			</li>';

		return $html;
	};

	foreach ($list_items as $key => $li)
	{
		// Handle the sublist
		if($key == 'more')
		{
			$output .= '
			<li class="post_options">' . $txt['post_options'] . '
				<ul>';

			foreach ($li as $subli)
				$output .= $list_item_format($subli);

			$output .= '
				</ul>
			</li>';
		}
		// Ordinary list item
		else
			$output .= $list_item_format($li);
	}

	$output .= '
		</ul><!-- .quickbuttons -->';

	// There are a few spots where the result needs to be returned
	if($output_method == 'echo')
		echo $output;
	else
		return $output;
}

/**
 * The upper part of the maintenance warning box
 */
function template_maint_warning_above()
{
	global $txt, $context, $scripturl;

	echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', $txt['forum_in_maintenance'], '</strong>
			</dt>
			<dd class="error" id="error_list">
				', sprintf($txt['maintenance_page'], $scripturl . '?action=admin;area=serversettings;' . $context['session_var'] . '=' . $context['session_id']), '
			</dd>
		</dl>
	</div>';
}

/**
 * The lower part of the maintenance warning box.
 */
function template_maint_warning_below()
{

}

?>