<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\IntegrationHook;
use SMF\Theme;
use SMF\Time;
use SMF\Utils;
use SMF\User;

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

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
    Theme::$current->settings['require_theme_strings'] = false;

	// Define the Theme variants.
	Theme::$current->settings['theme_variants'] = array('light', 'dark');

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last and the first post on the message index and recent pages.
	Theme::$current->settings['avatars_on_indexes'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last post on the board index.
	Theme::$current->settings['avatars_on_boardIndex'] = true;

	// Set the following variable to true if this theme wants to display the login and register buttons in the main forum menu.
	Theme::$current->settings['login_main_menu'] = false;

	// This defines the formatting for the page indexes used throughout the forum.
	Theme::$current->settings['page_index'] = array(
		'extra_before' => '<span class="pages">' . Lang::$txt['pages'] . '</span>',
		'previous_page' => '<span class="main_icons previous_page"></span>',
		'current_page' => '<span class="current_page">%1$d</span> ',
		'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
		'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
		'next_page' => '<span class="main_icons next_page"></span>',
		'extra_after' => '',
	);

	// Allow css/js files to be disabled for this specific theme.
	// Add the identifier as an array key. IE array('smf_script'); Some external files might not add identifiers, on those cases SMF uses its filename as reference.
	if (!isset(Theme::$current->settings['disable_files']))
		Theme::$current->settings['disable_files'] = array();
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	Theme::loadCSSFile('https://use.fontawesome.com/releases/v6.1.2/css/all.css', array('external' => true));
	// Show right to left, the language code, and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', !empty(Lang::$txt['lang_locale']) ? ' lang="' . str_replace("_", "-", substr(Lang::$txt['lang_locale'], 0, strcspn(Lang::$txt['lang_locale'], "."))) . '"' : '', '>
<head>
	<meta charset="', Utils::$context['character_set'], '">';

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

	Theme::loadCSSFile('custom.css', array('minimize' => true));

	// load in any css from mods or themes so they can overwrite if wanted
	Theme::template_css();

	// load in any javascript files from mods and themes
	Theme::template_javascript();

	echo '
	<title>', Utils::$context['page_title_html_safe'], '</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">';

	// Content related meta tags, like description, keywords, Open Graph stuff, etc...
	foreach (Utils::$context['meta_tags'] as $meta_tag)
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
	if (!empty(Utils::$context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty(Utils::$context['canonical_url']))
		echo '
	<link rel="canonical" href="', Utils::$context['canonical_url'], '">';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', Config::$scripturl, '?action=help">
	<link rel="contents" href="', Config::$scripturl, '">', (Utils::$context['allow_search'] ? '
	<link rel="search" href="' . Config::$scripturl . '?action=search">' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty(Config::$modSettings['xmlnews_enable']) && (!empty(Config::$modSettings['allow_guestAccess']) || User::$me->is_logged))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', Utils::$context['forum_name_html_safe'], ' - ', Lang::$txt['rss'], '" href="', Config::$scripturl, '?action=.xml;type=rss2', !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '', '">
	<link rel="alternate" type="application/atom+xml" title="', Utils::$context['forum_name_html_safe'], ' - ', Lang::$txt['atom'], '" href="', Config::$scripturl, '?action=.xml;type=atom', !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '', '">';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty(Utils::$context['links']['next']))
		echo '
	<link rel="next" href="', Utils::$context['links']['next'], '">';

	if (!empty(Utils::$context['links']['prev']))
		echo '
	<link rel="prev" href="', Utils::$context['links']['prev'], '">';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty(Utils::$context['current_board']))
		echo '
	<link rel="index" href="', Config::$scripturl, '?board=', Utils::$context['current_board'], '.0">';

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

	// Header
	echo '
	<div id="header">
		<div class="inner_wrap">
			<h1 class="forumtitle">
				<a id="top" href="', Config::$scripturl, '">', empty(Utils::$context['header_logo_url_html_safe']) ? '<img id="smflogo" src="' . Theme::$current->settings['images_url'] . '/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">' : '<img src="' . Utils::$context['header_logo_url_html_safe'] . '" alt="' . Utils::$context['forum_name_html_safe'] . '">', '</a>
			</h1>';

    //User Panel
	// If the user is logged in, display some things that might be useful.
	echo '
	<div class="user_panel">';
	if (User::$me->is_logged)
	{
		// Firstly, the user's menu
		echo '
			<ul id="top_info">
				<li>
					<a href="', Config::$scripturl, '?action=profile"', !empty(Utils::$context['self_profile']) ? ' class="active"' : '', ' id="profile_menu_top">';

		if (!empty(User::$me->avatar))
			echo User::$me->avatar['image'];

		echo '</a>
					<div id="profile_menu" class="top_menu"></div>
				</li>';

		// Secondly, PMs if we're doing them
		if (Utils::$context['allow_pm'])
			echo '
				<li>
					<a href="', Config::$scripturl, '?action=pm"', !empty(Utils::$context['self_pm']) ? ' class="active"' : '', ' id="pm_menu_top">
						<span class="main_icons inbox"></span>
						', !empty(User::$me->unread_messages) ? '
						<span class="amt">' . User::$me->unread_messages . '</span>' : '', '
					</a>
					<div id="pm_menu" class="top_menu scrollable"></div>
				</li>';

		// Thirdly, alerts
		echo '
				<li>
					<a href="', Config::$scripturl, '?action=profile;area=showalerts;u=', User::$me->id, '"', !empty(Utils::$context['self_alerts']) ? ' class="active"' : '', ' id="alerts_menu_top">
						<span class="main_icons alerts"></span>
						', !empty(User::$me->alerts) ? '
						<span class="amt">' . User::$me->alerts . '</span>' : '', '
					</a>
					<div id="alerts_menu" class="top_menu scrollable"></div>
				</li>';

		// A logout button for people without JavaScript.
		if (empty(Theme::$current->settings['login_main_menu']))
			echo '
				<li id="nojs_logout">
					<a href="', Config::$scripturl, '?action=logout;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::$txt['logout'], '</a>
					<script>document.getElementById("nojs_logout").style.display = "none";</script>
				</li>';

		// And now we're done.
		echo '
			</ul>';
	}
	// Otherwise they're a guest. Ask them to either register or login.
	elseif (empty(Config::$maintenance))
	{
		// Some people like to do things the old-fashioned way.
		if (!empty(Theme::$current->settings['login_main_menu']))
		{
			echo '
			<ul>
				<li class="welcome">', sprintf(Lang::$txt[Utils::$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest'], Utils::$context['forum_name_html_safe'], Config::$scripturl . '?action=login', 'return reqOverlayDiv(this.href, ' . Utils::JavaScriptEscape(Lang::$txt['login']) . ', \'login\');', Config::$scripturl . '?action=signup'), '</li>
			</ul>';
		}
		else
		{
			echo '
			<ul id="top_info">
				<li class="welcome">
					', sprintf(Lang::$txt['welcome_to_forum'], Utils::$context['forum_name_html_safe']), '
				</li>
				<li class="button_login">
					<a href="', Config::$scripturl, '?action=login" class="', Utils::$context['current_action'] == 'login' ? 'active' : 'open','" onclick="return reqOverlayDiv(this.href, ' . Utils::JavaScriptEscape(Lang::$txt['login']) . ', \'login\');">
						<span class="main_icons login"></span>
						<span class="textmenu">', Lang::$txt['login'], '</span>
					</a>
				</li>';

			if (Utils::$context['can_register'])
				echo '
				<li class="button_signup">
					<a href="', Config::$scripturl, '?action=signup" class="', Utils::$context['current_action'] == 'signup' ? 'active' : 'open','">
						<span class="main_icons regcenter"></span>
						<span class="textmenu">', Lang::$txt['register'], '</span>
					</a>
				</li>';

			echo '
			</ul>';
		}
	}
	else
		// In maintenance mode, only login is allowed and don't show OverlayDiv
		echo '
			<ul class="floatleft welcome">
				<li>', sprintf(Lang::$txt['welcome_guest'], Utils::$context['forum_name_html_safe'], Config::$scripturl . '?action=login', 'return true;'), '</li>
			</ul>';

	if (!empty(Config::$modSettings['userLanguage']) && !empty(Utils::$context['languages']) && count(Utils::$context['languages']) > 1)
	{
		echo '
			<form id="languages_form" method="get" class="floatright">
				<select id="language_select" name="language" onchange="this.form.submit()">';

		foreach (Utils::$context['languages'] as $language)
			echo '
					<option value="', $language['filename'], '"', isset(User::$me->language) && User::$me->language == $language['filename'] ? ' selected="selected"' : '', '>', str_replace('-utf8', '', $language['name']), '</option>';

		echo '
				</select>
				<noscript>
					<input type="submit" value="', Lang::$txt['quick_mod_go'], '">
				</noscript>
			</form>';
	}
	    echo '
			</div>
		</div>
	</div>';

	// Show the menu here, according to the menu sub template, followed by the navigation tree.
	// Load mobile menu here
	echo '
			<a class="mobile_user_menu">
				<span class="menu_icon"></span>
					<span class="text_menu">', Lang::$txt['mobile_user_menu'], '</span>
				</a>
				<div id="main_menu">
				  <div class="inner_wrap">
					<div id="mobile_user_menu" class="popup_container">
						<div class="popup_window description">
							<div class="popup_heading">', Lang::$txt['mobile_user_menu'], '
								<a href="javascript:void(0);" class="main_icons hide_popup"></a>
							</div>';
							if (User::$me->is_logged)
							echo '
							    <div class="unread_links floatright">
									<ul class="unread_links">
										<li>
											<a href="', Config::$scripturl, '?action=unread" title="', Lang::$txt['unread_since_visit'], '">', Lang::$txt['view_unread_category'], '</a>
										</li>
										<li>
											<a href="', Config::$scripturl, '?action=unreadreplies" title="', Lang::$txt['show_unread_replies'], '">', Lang::$txt['unread_replies'], '</a>
										</li>
									</ul>
							    </div>';
                            echo '
							', template_menu(), '
						</div>
					</div>
		        </div>
			</div>';

	// Wrapper
	echo '
	<div id="wrapper">';

	theme_linktree();

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
			<li class="floatright"><a href="', Config::$scripturl, '?action=help">', Lang::$txt['help'], '<i class="fa-solid fa-circle-question"></i></a> ', (!empty(Config::$modSettings['requireAgreement'])) ? '| <a href="' . Config::$scripturl . '?action=agreement">' . Lang::$txt['terms_and_rules'] . '<i class="fa-solid fa-list-ul"></i></a>' : '', ' | <a href="#top_section">', Lang::$txt['go_up'], ' &#9650;</a></li>
			<li class="copyright">', Theme::copyright(), '</li>
		</ul>';

	// Show the load time?
	if (Utils::$context['show_load_time'])
		echo '
		<p>', sprintf(Lang::$txt['page_created_full'], Utils::$context['load_time'], Utils::$context['load_queries']), '</p>';

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
	if (empty(Utils::$context['linktree']) || (!empty(Utils::$context['dont_default_linktree']) && !$force_show))
		return;

	echo '
				<div class="navigate_section">
					<ul>';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach (Utils::$context['linktree'] as $link_num => $tree)
	{
		echo '
						<li', ($link_num == count(Utils::$context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Don't show a separator for the first one.
		// Better here. Always points to the next level when the linktree breaks to a second line.
		// Picked a better looking HTML entity, and added support for RTL plus a span for styling.
		if ($link_num != 0)
			echo '
					     <span class="dividers">', Utils::$context['right_to_left'] ? ' &#9668; ' : ' &#9658; ', '</span>';

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
	echo '
					<ul class="dropmenu menu_nav">';

	// Note: Menu markup has been cleaned up to remove unnecessary spans and classes.
	foreach (Utils::$context['menu_buttons'] as $act => $button)
	{
		echo '
						<li class="button_', $act, '', !empty($button['sub_buttons']) ? ' subsections"' : '"', '>
							<a', $button['active_button'] ? ' class="active"' : '', ' href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', isset($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', '>
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
									<a href="', $childbutton['href'], '"', isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', isset($childbutton['onclick']) ? ' onclick="' . $childbutton['onclick'] . '"' : '', '>
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
											<a href="', $grandchildbutton['href'], '"', isset($grandchildbutton['target']) ? ' target="' . $grandchildbutton['target'] . '"' : '', isset($grandchildbutton['onclick']) ? ' onclick="' . $grandchildbutton['onclick'] . '"' : '', '>
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
	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!)
		if (!isset($value['test']) || !empty(Utils::$context[$value['test']]))
		{
			if (!isset($value['id']))
				$value['id'] = $key;

			$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>'.(!empty($value['icon']) ? '<span class="main_icons '.$value['icon'].'"></span>' : '').'' . Lang::$txt[$value['text']] . '</a>';

			if (!empty($value['sub_buttons']))
			{
				$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';
				foreach ($value['sub_buttons'] as $element)
				{
					if (isset($element['test']) && empty(Utils::$context[$element['test']]))
						continue;

					$button .= '
								<a href="' . $element['url'] . '"><strong>' . Lang::$txt[$element['text']] . '</strong>';
					if (isset(Lang::$txt[$element['text'] . '_desc']))
						$button .= '<br><span>' . Lang::$txt[$element['text'] . '_desc'] . '</span>';
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
 * @param string $list_class Used for integration hooks and as a class name
 * @param string $output_method The output method. If 'echo', simply displays the buttons, otherwise returns the HTML for them
 * @return void|string Returns nothing unless output_method is something other than 'echo'
 */
function template_quickbuttons($list_items, $list_class = null, $output_method = 'echo')
{
	// Enable manipulation with hooks
	if (!empty($list_class))
		IntegrationHook::call('integrate_' . $list_class . '_quickbuttons', array(&$list_items));

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
		<ul class="quickbuttons' . (!empty($list_class) ? ' quickbuttons_' . $list_class : '') . '">';

	// This is used for a list item or a sublist item
	$list_item_format = function($li)
	{
		$html = '
			<li' . (!empty($li['class']) ? ' class="' . $li['class'] . '"' : '') . (!empty($li['id']) ? ' id="' . $li['id'] . '"' : '') . (!empty($li['custom']) ? ' ' . $li['custom'] : '') . '>';

		if (isset($li['content']))
			$html .= $li['content'];
		else
			$html .= '
				<a href="' . (!empty($li['href']) ? $li['href'] : 'javascript:void(0);') . '"' . (!empty($li['javascript']) ? ' ' . $li['javascript'] : '') . '>
					' . (!empty($li['icon']) ? '<span class="main_icons ' . $li['icon'] . '"></span>' : '') . (!empty($li['label']) ? $li['label'] : '') . '
				</a>';

		$html .= '
			</li>';

		return $html;
	};

	foreach ($list_items as $key => $li)
	{
		// Handle the sublist
		if ($key == 'more')
		{
			$output .= '
			<li class="post_options">
				<a href="javascript:void(0);">' . Lang::$txt['post_options'] . '</a>
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
	if ($output_method == 'echo')
		echo $output;
	else
		return $output;
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
				<strong id="error_serious">', Lang::$txt['forum_in_maintenance'], '</strong>
			</dt>
			<dd class="error" id="error_list">
				', sprintf(Lang::$txt['maintenance_page'], Config::$scripturl . '?action=admin;area=serversettings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '
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