<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

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
	in the Utils::$context['linktree'] variable.

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
	/* $context, $options and $txt may be available for use, but may not be fully populated yet. */

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	Theme::$current->settings['theme_version'] = '2.1';

	/*
	 * Whether this theme supports a dark mode.
	 *
	 * Set this to `false` to disable.
	 *
	 * A not so trivial note:
	 * A 'dark' theme with dark mode is exactly the same as a 'light'
	 * theme with dark mode. This means the index.css file should
	 * always contain the light colors.
	 */
	Theme::$current->settings['has_dark_mode'] = false;

	/*
	 * Define the theme variants. Each variant has its own CSS file.
	 *
	 * Example:
	 * - index_red.css is loaded when the user selects the `red` variant.
	 *
	 * Additionally, a variants.css file is always loaded as well, in
	 * case you'd rather keep the styles in a single file or they're minimal.
	 */
	Theme::$current->settings['theme_variants'] = [];

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last and the first post on the message index and recent pages.
	Theme::$current->settings['avatars_on_indexes'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last post on the board index.
	Theme::$current->settings['avatars_on_boardIndex'] = false;

	// Set the following variable to true if this theme wants to display the login and register buttons in the main forum menu.
	Theme::$current->settings['login_main_menu'] = false;

	// This defines the formatting for the page indexes used throughout the forum.
	Theme::$current->settings['page_index'] = [
		'extra_before' => '<span class="pages">' . Lang::getTxt('pages', file: 'General') . '</span>',
		'previous_page' => '<span class="main_icons previous_page"></span>',
		'current_page' => '<span class="current_page">%1$d</span> ',
		'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
		'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
		'next_page' => '<span class="main_icons next_page"></span>',
		'extra_after' => '',
	];

	// Allow css/js files to be disabled for this specific theme.
	// Add the identifier as an array key. IE array('smf_script'); Some external files might not add identifiers, on those cases SMF uses its filename as reference.
	if (!isset(Theme::$current->settings['disable_files'])) {
		Theme::$current->settings['disable_files'] = [];
	}
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	// Show right to left, the language code, and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', !empty(Lang::getTxt('lang_locale', file: 'General')) ? ' lang="' . str_replace('_', '-', substr(Lang::getTxt('lang_locale', file: 'General'), 0, strcspn(Lang::getTxt('lang_locale', file: 'General'), '.'))) . '"' : '',!empty(Theme::$current->settings['theme_variants']) ? ' data-variant=' . (Utils::$context['theme_variant'] ?: 'default') . '' : '', !empty(Theme::$current->settings['has_dark_mode']) ? ' data-mode=' . (Utils::$context['theme_colormode'] ?? 'light') . '' : '', '>
<head>
	<meta charset="UTF-8">';

	/*
		You don't need to manually load index.css, this will be set up for you.
		Note that RTL will also be loaded for you.
		To load other CSS and JS files you should use the functions
		Theme::loadCSSFile() and Theme::loadJavaScriptFile() respectively.
		This approach will let you take advantage of SMF's automatic CSS
		minimization and other benefits. You can, of course, manually add any
		other files you want after Theme::template_css() has been run.

	*	Short example:
			- CSS: Theme::loadCSSFile('filename.css', array('minimize' => true));
			- JS:  Theme::loadJavaScriptFile('filename.js', array('minimize' => true));
			You can also read more detailed usages of the parameters for these
			functions on the SMF wiki.

	*	Themes:
			The most efficient way of writing multi themes is to use a master
			index.css plus variant.css files. If you've set them up properly
			(through Theme::$current->settings['theme_variants']), the variant files will be loaded
			for you automatically.
			Additionally, tweaking the CSS for the editor requires you to include
			a custom 'jquery.sceditor.theme.css' file in the css folder if you need it.

	*	MODs:
			If you want to load CSS or JS files in here, the best way is to use the
			'integrate_load_theme' hook for adding multiple files, or using
			'integrate_pre_css_output', 'integrate_pre_javascript_output' for a single file.
	*/

	// load in any css from mods or themes so they can overwrite if wanted
	Theme::template_css();

	// load in any javascript files from mods and themes
	Theme::template_javascript();

	echo '
	<title>', Utils::$context['page_title_html_safe'], '</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">';

	// Content related meta tags, like description, keywords, Open Graph stuff, etc...
	foreach (Utils::$context['meta_tags'] as $meta_tag) {
		echo '
	<meta';

		foreach ($meta_tag as $meta_key => $meta_value) {
			echo ' ', $meta_key, '="', $meta_value, '"';
		}

		echo '>';
	}

	/*	What is your Lollipop's color?
		Theme Authors, you can change the color here to make sure your theme's main color gets visible on tab */
	echo '
	<meta name="theme-color" content="#557EA0">';

	// Please don't index these Mr Robot.
	if (!empty(Utils::$context['robot_no_index'])) {
		echo '
	<meta name="robots" content="noindex">';
	}

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty(Utils::$context['canonical_url'])) {
		echo '
	<link rel="canonical" href="', Utils::$context['canonical_url'], '">';
	}

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', Config::$scripturl, '?action=help">
	<link rel="contents" href="', Config::$scripturl, '">', (Utils::$context['allow_search'] ? '
	<link rel="search" href="' . Config::$scripturl . '?action=search">' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty(Config::$modSettings['xmlnews_enable']) && (!empty(Config::$modSettings['allow_guestAccess']) || !User::$me->is_guest)) {
		echo '
	<link rel="alternate" type="application/rss+xml" title="', Utils::$context['forum_name_html_safe'], ' - ', Lang::getTxt('rss', file: 'General'), '" href="', Config::$scripturl, '?action=feed;type=rss2', !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '', '">
	<link rel="alternate" type="application/atom+xml" title="', Utils::$context['forum_name_html_safe'], ' - ', Lang::getTxt('atom', file: 'General'), '" href="', Config::$scripturl, '?action=feed;type=atom', !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '', '">';
	}

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty(Utils::$context['links']['next'])) {
		echo '
	<link rel="next" href="', Utils::$context['links']['next'], '">';
	}

	if (!empty(Utils::$context['links']['prev'])) {
		echo '
	<link rel="prev" href="', Utils::$context['links']['prev'], '">';
	}

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty(Utils::$context['current_board'])) {
		echo '
	<link rel="index" href="', Config::$scripturl, '?board=', Utils::$context['current_board'], '.0">';
	}

	// Output any remaining HTML headers. (from mods, maybe?)
	echo Utils::$context['html_headers'];

	echo '
</head>
<body id="', Utils::$context['browser_body_id'], '" class="action_', !empty(Utils::$context['current_action']) ? Utils::$context['current_action'] : (!empty(Utils::$context['current_board']) ?
		'messageindex' : (!empty(Utils::$context['current_topic']) ? 'display' : 'home')), !empty(Utils::$context['current_board']) ? ' board_' . Utils::$context['current_board'] : '', '">
<div id="footerfix">';
}

/**
 * The upper part of the main template layer. This is the stuff that shows above the main forum content.
 */
function template_body_above()
{
	// Wrapper div now echoes permanently for better layout options. h1 a is now target for "Go up" links.
	echo '
	<div id="top_section">
		<div class="inner_wrap">';

	// If the user is logged in, display some things that might be useful.
	if (!User::$me->is_guest) {
		// Firstly, the user's menu
		echo '
			<ul class="floatleft" id="top_info">
				<li>
					<a href="', Config::$scripturl, '?action=profile"', !empty(Utils::$context['self_profile']) ? ' class="active"' : '', ' id="profile_menu_top">';

		if (!empty(User::$me->avatar)) {
			echo User::$me->avatar['image'];
		}

		echo '<span class="textmenu">', User::$me->name, '</span></a>
					<div id="profile_menu" class="top_menu"></div>
				</li>';

		// Secondly, PMs if we're doing them
		if (Utils::$context['allow_pm']) {
			echo '
				<li>
					<a href="', Config::$scripturl, '?action=pm"', !empty(Utils::$context['self_pm']) ? ' class="active"' : '', ' id="pm_menu_top">
						<span class="main_icons inbox"></span>
						<span class="textmenu">', Lang::getTxt('pm_short', file: 'General'), '</span>', !empty(User::$me->unread_messages) ? '
						<span class="amt">' . User::$me->unread_messages . '</span>' : '', '
					</a>
					<div id="pm_menu" class="top_menu scrollable"></div>
				</li>';
		}

		// Thirdly, alerts
		echo '
				<li>
					<a href="', Config::$scripturl, '?action=profile;area=showalerts;u=', User::$me->id, '"', !empty(Utils::$context['self_alerts']) ? ' class="active"' : '', ' id="alerts_menu_top">
						<span class="main_icons alerts"></span>
						<span class="textmenu">', Lang::getTxt('alerts', file: 'General'), '</span>', !empty(User::$me->alerts) ? '
						<span class="amt">' . User::$me->alerts . '</span>' : '', '
					</a>
					<div id="alerts_menu" class="top_menu scrollable"></div>
				</li>';

		// A logout button for people without JavaScript.
		if (empty(Theme::$current->settings['login_main_menu'])) {
			echo '
				<li id="nojs_logout">
					<a href="', Config::$scripturl, '?action=logout;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('logout', file: 'General'), '</a>
					<script>document.getElementById("nojs_logout").style.display = "none";</script>
				</li>';
		}

		// And now we're done.
		echo '
			</ul>';
	}
	// Otherwise they're a guest. Ask them to either register or login.
	elseif (empty(Config::$maintenance)) {
		// Some people like to do things the old-fashioned way.
		if (!empty(Theme::$current->settings['login_main_menu'])) {
			echo '
			<ul class="floatleft">
				<li class="welcome">', Lang::getTxt(
				Utils::$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest',
				[
					'forum_name' => Utils::$context['forum_name_html_safe'],
					'login_url' => Config::$scripturl . '?action=login',
					'onclick' => 'return reqOverlayDiv(this.href, ' . Utils::escapeJavaScript(Lang::getTxt('login', file: 'General')) . ', \'login\');',
					'register_url' => Config::$scripturl . '?action=signup',
				],
				file: 'General',
			), '</li>
			</ul>';
		} else {
			echo '
			<ul class="floatleft" id="top_info">
				<li class="welcome">
					', Lang::getTxt('welcome_to_forum', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'General'), '
				</li>
				<li class="button_login">
					<a href="', Config::$scripturl, '?action=login" class="', Utils::$context['current_action'] == 'login' ? 'active' : 'open','" onclick="return reqOverlayDiv(this.href, ' . Utils::escapeJavaScript(Lang::getTxt('login', file: 'General')) . ', \'login\');">
						<span class="main_icons login"></span>
						<span class="textmenu">', Lang::getTxt('login', file: 'General'), '</span>
					</a>
				</li>';

			if (Utils::$context['can_register']) {
				echo '
				<li class="button_signup">
					<a href="', Config::$scripturl, '?action=signup" class="', Utils::$context['current_action'] == 'signup' ? 'active' : 'open','">
						<span class="main_icons regcenter"></span>
						<span class="textmenu">', Lang::getTxt('register', file: 'General'), '</span>
					</a>
				</li>';
			}

			echo '
			</ul>';
		}
	} else { // In maintenance mode, only login is allowed and don't show OverlayDiv
		echo '
			<ul class="floatleft welcome">
				<li>', Lang::getTxt(
			'welcome_guest',
			[
				'forum_name' => Utils::$context['forum_name_html_safe'],
				'login_url' => Config::$scripturl . '?action=login',
				'onclick' => 'return true;',
			],
			file: 'General',
		), '</li>
			</ul>';
	}

	if (!empty(Config::$modSettings['userLanguage']) && !empty(Utils::$context['languages']) && count(Utils::$context['languages']) > 1) {
		echo '
			<form id="languages_form" method="get" class="floatright">
				<select id="language_select" name="language" onchange="this.form.submit()">';

		foreach (Utils::$context['languages'] as $language) {
			echo '
					<option value="', $language['filename'], '"', isset(User::$me->language) && User::$me->language == $language['filename'] ? ' selected="selected"' : '', '>', str_replace('-utf8', '', $language['name']), '</option>';
		}

		echo '
				</select>
				<noscript>
					<input type="submit" value="', Lang::getTxt('quick_mod_go', file: 'General'), '">
				</noscript>
			</form>';
	}

	if (Utils::$context['allow_search']) {
		echo '
			<form id="search_form" class="floatright" action="', Config::$scripturl, '?action=search2" method="post" accept-charset="UTF-8">
				<input type="search" name="search" value="">&nbsp;';

		// Using the quick search dropdown?
		$selected = !empty(Utils::$context['current_topic']) ? 'current_topic' : (!empty(Utils::$context['current_board']) ? 'current_board' : 'all');

		echo '
				<select name="search_selection">
					<option value="all"', ($selected == 'all' ? ' selected' : ''), '>', Lang::getTxt('search_entireforum', file: 'General'), ' </option>';

		// Can't limit it to a specific topic if we are not in one
		if (!empty(Utils::$context['current_topic'])) {
			echo '
					<option value="topic"', ($selected == 'current_topic' ? ' selected' : ''), '>', Lang::getTxt('search_thistopic', file: 'General'), '</option>';
		}

		// Can't limit it to a specific board if we are not in one
		if (!empty(Utils::$context['current_board'])) {
			echo '
					<option value="board"', ($selected == 'current_board' ? ' selected' : ''), '>', Lang::getTxt('search_thisboard', file: 'General'), '</option>';
		}

		// Can't search for members if we can't see the memberlist
		if (!empty(Utils::$context['allow_memberlist'])) {
			echo '
					<option value="members"', ($selected == 'members' ? ' selected' : ''), '>', Lang::getTxt('search_members', file: 'General'), ' </option>';
		}

		echo '
				</select>';

		// Search within current topic?
		if (!empty(Utils::$context['current_topic'])) {
			echo '
				<input type="hidden" name="sd_topic" value="', Utils::$context['current_topic'], '">';
		}

		// If we're on a certain board, limit it to this board ;).
		elseif (!empty(Utils::$context['current_board'])) {
			echo '
				<input type="hidden" name="sd_brd" value="', Utils::$context['current_board'], '">';
		}

		echo '
				<input type="submit" name="search2" value="', Lang::getTxt('search', file: 'General'), '" class="button">
				<input type="hidden" name="advanced" value="0">
			</form>';
	}

	echo '
		</div><!-- .inner_wrap -->
	</div><!-- #top_section -->';

	echo '
	<div id="header">
		<h1 class="forumtitle">
			<a id="top" href="', Config::$scripturl, '">', empty(Utils::$context['header_logo_url_html_safe']) ? Utils::$context['forum_name_html_safe'] : '<img src="' . Utils::$context['header_logo_url_html_safe'] . '" alt="' . Utils::$context['forum_name_html_safe'] . '">', '</a>
		</h1>';

	echo '
		', empty(Theme::$current->settings['site_slogan']) ? '<img id="smflogo" src="' . Theme::$current->settings['images_url'] . '/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">' : '<div id="siteslogan">' . Theme::$current->settings['site_slogan'] . '</div>', '';

	echo '
	</div>
	<div id="wrapper">
		<div id="upper_section">
			<div id="inner_section">
				<div id="inner_wrap"', User::$me->is_guest ? ' class="hide_720"' : '', '>
					<div class="user">
						<time datetime="', Time::gmstrftime('%FT%TZ'), '">', Utils::$context['current_time'], '</time>';

	if (!User::$me->is_guest) {
		echo '
						<ul class="unread_links">
							<li>
								<a href="', Config::$scripturl, '?action=unread" title="', Lang::getTxt('unread_since_visit', file: 'General'), '">', Lang::getTxt('view_unread_category', file: 'General'), '</a>
							</li>
							<li>
								<a href="', Config::$scripturl, '?action=unreadreplies" title="', Lang::getTxt('show_unread_replies', file: 'General'), '">', Lang::getTxt('unread_replies', file: 'General'), '</a>
							</li>
						</ul>';
	}

	echo '
					</div>';

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty(Theme::$current->settings['enable_news']) && !empty(Utils::$context['random_news_line'])) {
		echo '
					<div class="news">
						<h2>', Lang::getTxt('news', file: 'General'), ': </h2>
						<p>', Utils::$context['random_news_line'], '</p>
					</div>';
	}

	echo '
				</div>';

	// Show the menu here, according to the menu sub template, followed by the navigation tree.
	// Load mobile menu here
	echo '
				<a class="mobile_user_menu">
					<span class="menu_icon"></span>
					<span class="text_menu">', Lang::getTxt('mobile_user_menu', file: 'General'), '</span>
				</a>
				<div id="main_menu">
					<div id="mobile_user_menu" class="popup_container">
						<div class="popup_window description">
							<div class="popup_heading">', Lang::getTxt('mobile_user_menu', file: 'General'), '
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
			<li class="floatright"><a href="', Config::$scripturl, '?action=help">', Lang::getTxt('help', file: 'General'), '</a> ', (!empty(Config::$modSettings['requireAgreement'])) ? '| <a href="' . Config::$scripturl . '?action=agreement">' . Lang::getTxt('terms_and_rules', file: 'General') . '</a>' : '', ' | <a href="#top_section">', Lang::getTxt('go_up', file: 'General'), ' &#9650;</a></li>
			<li class="copyright">', Theme::copyright(), '</li>
		</ul>';

	// Show the load time?
	if (Utils::$context['show_load_time']) {
		echo '
		<p>', Lang::getTxt(
			'page_created_full',
			[
				Utils::$context['load_time'],
				Utils::$context['load_queries'],
			],
			file: 'General',
		), '</p>';
	}

	echo '
		</div>
	</div><!-- #footer -->';

}

/**
 * This shows any deferred JavaScript and closes out the HTML
 */
function template_html_below()
{
	// Load in any javascript that could be deferred to the end of the page
	Theme::template_javascript(true);

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
	global $shown_linktree;

	// If linktree is empty, just return - also allow an override.
	if (empty(Utils::$context['linktree']) || (!empty(Utils::$context['dont_default_linktree']) && !$force_show)) {
		return;
	}

	// A breadcrumb trail is a landmark, and it is an ordered list rather than an
	// unordered one - "General Category" comes before "General Discussion".
	echo '
				<nav class="navigate_section" aria-label="', Lang::getTxt('breadcrumb', file: 'General'), '">
					<ol itemscope itemtype="https://schema.org/BreadcrumbList">';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach (Utils::$context['linktree'] as $link_num => $tree) {
		echo '
						<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"', ($link_num == count(Utils::$context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Don't show a separator for the first one.
		// Better here. Always points to the next level when the linktree breaks to a second line.
		// Picked a better looking HTML entity, and added support for RTL plus a span for styling.
		if ($link_num != 0) {
			echo '
							<span class="dividers">', Utils::$context['right_to_left'] ? ' &#9668; ' : ' &#9658; ', '</span>';
		}

		// Show something before the link?
		if (isset($tree['extra_before'])) {
			echo $tree['extra_before'], ' ';
		}

		// Show the link, including a URL if it should have one. The itemprop
		// attributes let a search engine read the trail as a breadcrumb.
		if (isset($tree['url'])) {
			echo '
							<a itemprop="item" href="' . $tree['url'] . '"><span itemprop="name">' . $tree['name'] . '</span></a>';
		} else {
			echo '
							<span itemprop="name">' . $tree['name'] . '</span>';
		}

		// Where this one sits in the trail. Positions are 1 based.
		echo '
							<meta itemprop="position" content="', $link_num + 1, '">';

		// Show something after the link...?
		if (isset($tree['extra_after'])) {
			echo ' ', $tree['extra_after'];
		}

		echo '
						</li>';
	}

	echo '
					</ol>
				</nav><!-- .navigate_section -->';

	$shown_linktree = true;
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	echo '
					<ul class="dropmenu menu_nav">';

	// Note: Menu markup has been cleaned up to remove unnecessary spans and classes.
	foreach (Utils::$context['menu_buttons'] as $act => $button) {
		echo '
						<li class="button_', $act, '', !empty($button['sub_buttons']) ? ' subsections"' : '"', '>
							<a', $button['active_button'] ? ' class="active"' : '', ' href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', isset($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', '>
								', $button['icon'], '<span class="textmenu">', $button['title'], !empty($button['amt']) ? ' <span class="amt">' . $button['amt'] . '</span>' : '', '</span>
							</a>';

		// 2nd level menus
		if (!empty($button['sub_buttons'])) {
			echo '
							<ul>';

			foreach ($button['sub_buttons'] as $childbutton) {
				echo '
								<li', !empty($childbutton['sub_buttons']) ? ' class="subsections"' : '', '>
									<a href="', $childbutton['href'], '"', isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', isset($childbutton['onclick']) ? ' onclick="' . $childbutton['onclick'] . '"' : '', '>
										', $childbutton['title'], !empty($childbutton['amt']) ? ' <span class="amt">' . $childbutton['amt'] . '</span>' : '', '
									</a>';

				// 3rd level menus :)
				if (!empty($childbutton['sub_buttons'])) {
					echo '
									<ul>';

					foreach ($childbutton['sub_buttons'] as $grandchildbutton) {
						echo '
										<li>
											<a href="', $grandchildbutton['href'], '"', isset($grandchildbutton['target']) ? ' target="' . $grandchildbutton['target'] . '"' : '', isset($grandchildbutton['onclick']) ? ' onclick="' . $grandchildbutton['onclick'] . '"' : '', '>
												', $grandchildbutton['title'], !empty($grandchildbutton['amt']) ? ' <span class="amt">' . $grandchildbutton['amt'] . '</span>' : '', '
											</a>
										</li>';
					}

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
function template_button_strip($button_strip, $direction = '', $strip_options = [])
{
	if (!is_array($strip_options)) {
		$strip_options = [];
	}

	// Create the buttons...
	$buttons = [];

	foreach ($button_strip as $key => $value) {
		// As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!)
		if (!isset($value['test']) || !empty(Utils::$context[$value['test']])) {
			if (!isset($value['id'])) {
				$value['id'] = $key;
			}

			$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . (!empty($value['icon']) ? '<span class="main_icons ' . $value['icon'] . '"></span>' : '') . Lang::getTxt($value['text']) . '</a>';

			if (!empty($value['sub_buttons'])) {
				$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';

				foreach ($value['sub_buttons'] as $element) {
					if (isset($element['test']) && empty(Utils::$context[$element['test']])) {
						continue;
					}

					$button .= '
								<a href="' . $element['url'] . '"><strong>' . Lang::getTxt($element['text']) . '</strong>';

					if (Lang::txtExists($element['text'] . '_desc')) {
						$button .= '<br><span>' . Lang::getTxt($element['text'] . '_desc') . '</span>';
					}

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
	if (empty($buttons)) {
		return;
	}

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
			', implode('', $buttons), '
		</div>';
}

/**
 * Generate a list of quickbuttons.
 *
 * @param array $list_items An array with info for displaying the strip
 * @param string $list_class Used for integration hooks and as a class name
 * @param string $output_method The output method. If 'echo', simply displays the buttons, otherwise returns the HTML for them
 * @return void|string Returns nothing unless output_method is something other than 'echo'
 */
function template_quickbuttons($list_items, $list_class = null, $output_method = 'echo')
{
	// Enable manipulation with hooks
	if (!empty($list_class)) {
		IntegrationHook::call('integrate_' . $list_class . '_quickbuttons', [&$list_items]);
	}

	// Make sure the list has at least one shown item
	foreach ($list_items as $key => $li) {
		// Is there a sublist, and does it have any shown items
		if ($key == 'more') {
			foreach ($li as $subkey => $subli) {
				if (isset($subli['show']) && !$subli['show']) {
					unset($list_items[$key][$subkey]);
				}
			}

			if (empty($list_items[$key])) {
				unset($list_items[$key]);
			}
		}
		// A normal list item
		elseif (isset($li['show']) && !$li['show']) {
			unset($list_items[$key]);
		}
	}

	// Now check if there are any items left
	if (empty($list_items)) {
		return;
	}

	// Print the quickbuttons
	$output = '
		<ul class="quickbuttons' . (!empty($list_class) ? ' quickbuttons_' . $list_class : '') . '">';

	// This is used for a list item or a sublist item
	$list_item_format = function ($li) {
		$html = '
			<li' . (!empty($li['class']) ? ' class="' . $li['class'] . '"' : '') . (!empty($li['id']) ? ' id="' . $li['id'] . '"' : '') . (!empty($li['custom']) ? ' ' . $li['custom'] : '') . '>';

		if (isset($li['content'])) {
			$html .= $li['content'];
		} else {
		$html .= '
				<a href="' . (!empty($li['href']) ? $li['href'] : 'javascript:void(0);') . '"' . (!empty($li['javascript']) ? ' ' . $li['javascript'] : '') . '>
					' . (!empty($li['icon']) ? '<span class="main_icons ' . $li['icon'] . '"></span>' : '') . (!empty($li['label']) ? $li['label'] : '') . '
				</a>';
		}

		$html .= '
			</li>';

		return $html;
	};

	foreach ($list_items as $key => $li) {
		// Handle the sublist
		if ($key == 'more') {
			$output .= '
			<li class="post_options">
				<a href="javascript:void(0);">' . Lang::getTxt('post_options', file: 'General') . '</a>
				<ul>';

			foreach ($li as $subli) {
				$output .= $list_item_format($subli);
			}

			$output .= '
				</ul>
			</li>';
		}
		// Ordinary list item
		else {
		$output .= $list_item_format($li);
		}
	}

	$output .= '
		</ul><!-- .quickbuttons -->';

	// There are a few spots where the result needs to be returned
	if ($output_method == 'echo') {
		echo $output;
	} else {
	return $output;
	}
}

/**
 * The upper part of the maintenance warning box
 */
function template_maint_warning_above()
{
	echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', Lang::getTxt('forum_in_maintenance', file: 'General'), '</strong>
			</dt>
			<dd class="error" id="error_list">
				', Lang::getTxt('maintenance_page', ['url' => Config::$scripturl . '?action=admin;area=serversettings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']], file: 'General'), '
			</dd>
		</dl>
	</div>';
}

/**
 * The lower part of the maintenance warning box.
 */
function template_maint_warning_below() {}

/**
 * The upper part of the security warning box
 */
function template_security_warning_above()
{
	echo '
	<div class="errorbox">
		<p class="alert">!!</p>
		<h3>', Lang::getTxt(!isset(Utils::$context['warnings']['file']) && empty(Utils::$context['auth_secret_missing']) ? 'generic_warning' : 'security_risk', file: 'General'), '</h3>';

	foreach ((Utils::$context['warnings']['file'] ?? []) as $security_file) {
		echo '
		<p>', Lang::getTxt($security_file[0], $security_file[1], file: 'General'), '</p>';
	}

	for ($i = 0, $n = count(Utils::$context['warnings']) - 1; $i < $n; $i++) {
		if (is_string(Utils::$context['warnings'][$i])) {
			echo '
		<p>' . Utils::$context['warnings'][$i] . '</p>';
		} else {
			echo '
		<p>', Lang::getTxt(Utils::$context['warnings'][$i][0], Utils::$context['warnings'][$i][1] ?? [], file: 'General'), '</p>';
		}
	}

	echo '
	</div>';
}

/**
 * The lower part of the security warning box.
 */
function template_security_warning_below() {}

/**
 * The upper part of the ban warning box
 */
function template_banned_warning_above()
{
	echo '
	<div class="noticebox">';

	echo '
		<p>', Lang::getTxt('you_are_post_banned', ['name' => User::$me->is_guest ? Lang::getTxt('guest_title', file: 'General') : User::$me->name]), '</p>';

	if (!empty($_SESSION['ban']['cannot_post']['reason'])) {
		echo '
		<p>', $_SESSION['ban']['cannot_post']['reason'], '</p>';
	}

	if (!empty($_SESSION['ban']['expire_time'])) {
		echo '
		<p>', Lang::getTxt('your_ban_expires', ['datetime' => Time::create('@' . $_SESSION['ban']['expire_time'])->format(null, false)], file: 'General'), '</p>';
	} else {
		echo '
		<p>', Lang::getTxt('your_ban_expires_never', file: 'General'), '</p>';
	}

	echo '
	</div>';
}

/**
 * The lower part of the ban warning box.
 */
function template_banned_warning_below() {}
