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
	http://www.simplemachines.org/
*/

// Initialize the template... mainly little settings.
function template_init()
{
	global $context, $settings, $options, $txt;

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* What document type definition is being used? (for font size and other issues.)
		'xhtml' for an XHTML 1.0 document type definition.
		'html' for an HTML 4.01 document type definition. */
	$settings['doctype'] = 'xhtml';

	/* The version this template/theme is for.
		This should probably be the version of SMF it was created for. */
	$settings['theme_version'] = '2.0';

	/* Set a setting that tells the theme that it can render the tabs. */
	$settings['use_tabs'] = true;

	/* Use plain buttons - as opposed to text buttons? */
	$settings['use_buttons'] = true;

	/* Show sticky and lock status separate from topic icons? */
	$settings['separate_sticky_lock'] = true;

	/* Does this theme use the strict doctype? */
	$settings['strict_doctype'] = false;

	/* Does this theme use post previews on the message index? */
	$settings['message_index_preview'] = false;

	/* Set the following variable to true if this theme requires the optional theme strings file to be loaded. */
	$settings['require_theme_strings'] = false;
}

// The main sub template above the content.
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '><head>';

	// The ?fin20 part of this link is just here to make sure browsers don't cache it wrongly.
	echo '
	<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?fin20" />';

	// Some browsers need an extra stylesheet due to bugs/compatibility issues.
	foreach (array('ie7', 'ie6', 'webkit') as $cssfix)
		if ($context['browser']['is_' . $cssfix])
			echo '
	<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/', $cssfix, '.css" />';

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
		echo '
	<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/rtl.css" />';

	// Here comes the JavaScript bits!
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js?fin20"></script>
	<script type="text/javascript" src="', $settings['theme_url'], '/scripts/theme.js?fin20"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		var smf_theme_url = "', $settings['theme_url'], '";
		var smf_default_theme_url = "', $settings['default_theme_url'], '";
		var smf_images_url = "', $settings['images_url'], '";
		var smf_scripturl = "', $scripturl, '";
		var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
		var smf_charset = "', $context['character_set'], '";', $context['show_pm_popup'] ? '
		var fPmPopup = function ()
		{
			if (confirm("' . $txt['show_personal_messages'] . '"))
				window.open(smf_prepareScriptUrl(smf_scripturl) + "action=pm");
		}
		addLoadEvent(fPmPopup);' : '', '
		var ajax_notification_text = "', $txt['ajax_in_progress'], '";
		var ajax_notification_cancel_text = "', $txt['modify_cancel'], '";
	// ]]></script>';

	echo '
	<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '', '
	<title>', $context['page_title_html_safe'], '</title>';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help" />
	<link rel="search" href="' . $scripturl . '?action=search" />
	<link rel="contents" href="', $scripturl, '" />';

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?type=rss;action=.xml" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['current_topic']))
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />
	<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0" />';

	// We'll have to use the cookie to remember the header...
	if ($context['user']['is_guest'])
	{
		$options['collapse_header'] = !empty($_COOKIE['upshrink']);
		$options['collapse_header_ic'] = !empty($_COOKIE['upshrinkIC']);
	}

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body>';
}

function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
<div id="mainframe"', !empty($settings['forum_width']) ? ' style="width: ' . $settings['forum_width'] . '"' : '', '>
	<div class="tborder">
		<div class="catbg">
			<img class="floatright" id="smflogo" src="', $settings['images_url'], '/smflogo.gif" alt="Simple Machines Forum" />
			<h1 id="forum_name">';

	if (empty($context['header_logo_url_html_safe']))
		echo $context['forum_name_html_safe'];
	else
		echo '
				<img src="', $context['header_logo_url_html_safe'], '" alt="', $context['forum_name_html_safe'], '" />';

	echo '
			</h1>
		</div>';

	// Display user name and time.
	echo '
		<ul id="greeting_section" class="reset titlebg2">
			<li id="time" class="smalltext floatright">
				', $context['current_time'], '
				<img id="upshrink" src="', $settings['images_url'], '/upshrink.gif" alt="*" title="', $txt['upshrink_description'], '" align="bottom" style="display: none;" />
			</li>';

	if ($context['user']['is_logged'])
		echo '
			<li id="name">', $txt['hello_member_ndt'], ' <em>', $context['user']['name'], '</em></li>';
	else
		echo '
			<li id="name">', $txt['hello_guest'], ' <em>', $txt['guest'], '</em></li>';

	echo '
		</ul>';

	if ($context['user']['is_logged'] || !empty($context['show_login_bar']))
		echo '
		<div id="user_section" class="bordercolor"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
			<div class="windowbg2 clearfix">';

	if (!empty($context['user']['avatar']))
		echo '
				<div id="myavatar">', $context['user']['avatar']['image'], '</div>';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		echo '
				<ul class="reset">
					<li><a href="', $scripturl, '?action=unread">', $txt['unread_since_visit'], '</a></li>
					<li><a href="', $scripturl, '?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '
					<li class="notice">', $txt['maintain_mode_on'], '</li>';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
					<li>', $context['unapproved_members'] == 1 ? $txt['approve_thereis'] : $txt['approve_thereare'], ' <a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'] == 1 ? $txt['approve_member'] : $context['unapproved_members'] . ' ' . $txt['approve_members'], '</a> ', $txt['approve_members_waiting'], '</li>';

		// Show the total time logged in?
		if (!empty($context['user']['total_time_logged_in']))
		{
			echo '
					<li>', $txt['totalTimeLogged1'];

			// If days is just zero, don't bother to show it.
			if ($context['user']['total_time_logged_in']['days'] > 0)
				echo $context['user']['total_time_logged_in']['days'] . $txt['totalTimeLogged2'];

			// Same with hours - only show it if it's above zero.
			if ($context['user']['total_time_logged_in']['hours'] > 0)
				echo $context['user']['total_time_logged_in']['hours'] . $txt['totalTimeLogged3'];

			// But, let's always show minutes - Time wasted here: 0 minutes ;).
			echo $context['user']['total_time_logged_in']['minutes'], $txt['totalTimeLogged4'], '
					</li>';
		}

		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '
					<li><a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a></li>';
		echo '
				</ul>';
	}
	// Otherwise they're a guest - this time ask them to either register or login - lazy bums...
	elseif (!empty($context['show_login_bar']))
	{
		echo '
				<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
				<form class="windowbg" id="guest_form" action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
					', $txt['login_or_register'], '<br />
					<input type="text" name="user" size="10" class="input_text" />
					<input type="password" name="passwrd" size="10" class="input_password" />
					<select name="cookielength">
						<option value="60">', $txt['one_hour'], '</option>
						<option value="1440">', $txt['one_day'], '</option>
						<option value="10080">', $txt['one_week'], '</option>
						<option value="43200">', $txt['one_month'], '</option>
						<option value="-1" selected="selected">', $txt['forever'], '</option>
					</select>
					<input type="submit" value="', $txt['login'], '" class="button_submit" /><br />
					', $txt['quick_login_dec'];

		if (!empty($modSettings['enableOpenID']))
			echo '
					<br />
					<input type="text" name="openid_identifier" id="openid_url" size="25" class="input_text openid_login" />';

		echo '
					<input type="hidden" name="hash_passwrd" value="" />
				</form>';
	}

	if ($context['user']['is_logged'] || !empty($context['show_login_bar']))
		echo '
			</div>
		</div>';

	echo '
		<div id="news_section" class="titlebg2 clearfix"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
			<form class="floatright" id="search_form" action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<a href="', $scripturl, '?action=search;advanced" title="', $txt['search_advanced'], '"><img id="advsearch" src="'.$settings['images_url'].'/filter.gif" align="middle" alt="', $txt['search_advanced'], '" /></a>
				<input type="text" name="search" value="" style="width: 190px;" class="input_text" />&nbsp;
				<input type="submit" name="submit" value="', $txt['search'], '" style="width: 11ex;" class="button_submit" />
				<input type="hidden" name="advanced" value="0" />';

	// Search within current topic?
	if (!empty($context['current_topic']))
		echo '
				<input type="hidden" name="topic" value="', $context['current_topic'], '" />';
		// If we're on a certain board, limit it to this board ;).
	elseif (!empty($context['current_board']))
		echo '
				<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '" />';

	echo '
			</form>';

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']))
		echo '
			<div id="random_news"><h3>', $txt['news'], ':</h3><p>', $context['random_news_line'], '</p></div>';

	echo '
		</div>
	</div>';

	// Define the upper_section toggle in JavaScript.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var oMainHeaderToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($options['collapse_header']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'user_section\',
				\'news_section\'
			],
			aSwapImages: [
				{
					sId: \'upshrink\',
					srcExpanded: smf_images_url + \'/upshrink.gif\',
					altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
					srcCollapsed: smf_images_url + \'/upshrink2.gif\',
					altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'collapse_header\',
				sSessionVar: ', JavaScriptEscape($context['session_var']), ',
				sSessionId: ', JavaScriptEscape($context['session_id']), '
			},
			oCookieOptions: {
				bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
				sCookieName: \'upshrink\'
			}
		});
	// ]]></script>';

	// Show the menu here, according to the menu sub template.
	template_menu();

	// Show the navigation tree.
	theme_linktree();

	// The main content should go here.
	echo '
	<div id="bodyarea">';
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	</div>';

	// Show the "Powered by" and "Valid" logos, as well as the copyright. Remember, the copyright must be somewhere!
	echo '
	<div id="footerarea" class="headerpadding topmargin clearfix">
		<ul class="reset smalltext">
			<li class="copyright">', theme_copyright(), '</li>
			<li><a id="button_xhtml" href="http://validator.w3.org/check?uri=referer" target="_blank" class="new_win" title="', $txt['valid_xhtml'], '"><span>', $txt['xhtml'], '</span></a></li>
			', !empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']) ? '<li><a id="button_rss" href="' . $scripturl . '?action=.xml;type=rss" class="new_win"><span>' . $txt['rss'] . '</span></a></li>' : '', '
			<li class="last"><a id="button_wap2" href="', $scripturl , '?wap2" class="new_win"><span>', $txt['wap2'], '</span></a></li>
		</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
		<p class="smalltext" id="show_loadtime">', $txt['page_created'], $context['load_time'], $txt['seconds_with'], $context['load_queries'], $txt['queries'], '</p>';

	echo '
	</div>
</div>';
}

function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
</body></html>';
}

// Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
function theme_linktree($force_show = false)
{
	global $context, $settings, $options, $shown_linktree;

	// If linktree is empty, just return - also allow an override.
	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;

	echo '
	<ul class="linktree" id="linktree_', empty($shown_linktree) ? 'upper' : 'lower', '">';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach ($context['linktree'] as $link_num => $tree)
	{
		echo '
		<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		// Show the link, including a URL if it should have one.
		echo $settings['linktree_link'] && isset($tree['url']) ? '
			<a href="' . $tree['url'] . '"><span>' . $tree['name'] . '</span></a>' : '<span>' . $tree['name'] . '</span>';

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		// Don't show a separator for the last one.
		if ($link_num != count($context['linktree']) - 1)
			echo ' &gt;';

		echo '
		</li>';
	}
	echo '
	</ul>';

	$shown_linktree = true;
}

// Show the menu up top. Something like [home] [help] [profile] [logout]...
function template_menu()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div class="main_menu">
		<ul class="reset clearfix">';

	foreach ($context['menu_buttons'] as $act => $button)
	{
		$classes = array();
		if (!empty($button['active_button']))
			$classes[] = 'active';
		if (!empty($button['is_last']))
			$classes[] = 'last';
		/* IE6 can't do multiple class selectors */
		if ($context['browser']['is_ie6'] && !empty($button['active_button']) && !empty($button['is_last']))
			$classes[] = 'lastactive';

		$classes = implode(' ', $classes);

		echo '
			<li id="button_', $act, '"', !empty($classes) ? ' class="' . $classes . '"' : '', '>
				<a title="', !empty($button['alttitle']) ? $button['alttitle'] : $button['title'], '" href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', '>
					<span>', ($button['active_button'] ? '<em>' : ''), $button['title'], ($button['active_button'] ? '</em>' : ''), '</span>
				</a>
			</li>';
	}

	echo '
		</ul>
	</div>';
}

// Generate a strip of buttons.
function template_button_strip($button_strip, $direction = 'top', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	if (!is_array($strip_options))
		$strip_options = array();

	// Right to left menu should be in reverse order.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li' . (isset($value['active']) ? ' class="active"' : '') . '><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="button_strip_' . $key . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '><span>' . (isset($value['active']) ? '<em>' . $txt[$value['text']] . '</em>' : $txt[$value['text']]) . '</span></a></li>';

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	// Make the last one, as easy as possible.
	$list_item = array('<li>', '<li class="active">');
	$active_item = array('<li class="last">', '<li class="active last lastactive">');

	$buttons[count($buttons) - 1] = str_replace($list_item, $active_item, $buttons[count($buttons) - 1]);

	echo '
		<div class="buttonlist', $direction != 'top' ? '_bottom' : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>
			<ul class="reset clearfix">',
				implode('', $buttons), '
			</ul>
		</div>';
}

?>