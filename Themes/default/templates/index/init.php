<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Lang;
use SMF\Theme;

if (!defined('SMF')) {
	die('No direct access...');
}

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
/*
 * Initialize the template... mainly little settings.
 */
?><?php
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
Theme::$current->settings['has_dark_mode'] = true;
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
?><?php if (!isset(Theme::$current->settings['disable_files'])): ?><?php Theme::$current->settings['disable_files'] = []; ?><?php endif; ?>
