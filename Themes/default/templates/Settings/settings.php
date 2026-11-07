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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This pseudo-template defines all the available theme settings (but not their actual values)
 */
?><?php
Utils::$context['theme_settings'] = [
	[
		'id' => 'header_logo_url',
		'label' => Lang::getTxt('header_logo_url', file: 'Themes'),
		'description' => Lang::getTxt('header_logo_url_desc', file: 'Themes'),
		'type' => 'text',
	],
	[
		'id' => 'site_slogan',
		'label' => Lang::getTxt('site_slogan', file: 'Themes'),
		'description' => Lang::getTxt('site_slogan_desc', file: 'Themes'),
		'type' => 'text',
	],
	[
		'id' => 'og_image',
		'label' => Lang::getTxt('og_image', file: 'Themes'),
		'description' => Lang::getTxt('og_image_desc', file: 'Themes'),
		'type' => 'url',
	],
	'',
	[
		'id' => 'smiley_sets_default',
		'label' => Lang::getTxt('smileys_default_set_for_theme', file: 'Admin'),
		'options' => Utils::$context['smiley_sets'],
		'type' => 'text',
	],
	'',
	[
		'id' => 'enable_news',
		'label' => Lang::getTxt('enable_random_news', file: 'Themes'),
	],
	[
		'id' => 'show_newsfader',
		'label' => Lang::getTxt('news_fader', file: 'Themes'),
	],
	[
		'id' => 'newsfader_time',
		'label' => Lang::getTxt('admin_fader_delay', file: 'Admin'),
		'type' => 'number',
	],
	'',
	[
		'id' => 'number_recent_posts',
		'label' => Lang::getTxt('number_recent_posts', file: 'Themes'),
		'description' => Lang::getTxt('zero_to_disable', file: 'Admin'),
		'type' => 'number',
	],
	[
		'id' => 'show_stats_index',
		'label' => Lang::getTxt('show_stats_index', file: 'Themes'),
	],
	[
		'id' => 'show_latest_member',
		'label' => Lang::getTxt('latest_members', file: 'Themes'),
	],
	[
		'id' => 'show_group_key',
		'label' => Lang::getTxt('show_group_key', file: 'Themes'),
	],
	[
		'id' => 'display_who_viewing',
		'label' => Lang::getTxt('who_display_viewing', file: 'Themes'),
		'options' => [
			0 => Lang::getTxt('who_display_viewing_off', file: 'Themes'),
			1 => Lang::getTxt('who_display_viewing_numbers', file: 'Themes'),
			2 => Lang::getTxt('who_display_viewing_names', file: 'Themes'),
		],
		'type' => 'list',
	],
];
?>
