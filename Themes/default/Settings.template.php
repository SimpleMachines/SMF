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
use SMF\Lang;
use SMF\Utils;

/**
 * This pseudo-template defines all the theme options
 */
function template_options()
{
	Utils::$context['theme_options'] = [
		Lang::getTxt('theme_opt_display', file: 'Profile'),
		[
			'id' => 'show_children',
			'label' => Lang::getTxt('show_children', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'topics_per_page',
			'label' => Lang::getTxt('topics_per_page', file: 'Profile'),
			'options' => [
				0 => Lang::getTxt('per_page_default', file: 'Profile'),
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			],
			'default' => true,
			'enabled' => empty(Config::$modSettings['disableCustomPerPage']),
		],
		[
			'id' => 'messages_per_page',
			'label' => Lang::getTxt('messages_per_page', file: 'Profile'),
			'options' => [
				0 => Lang::getTxt('per_page_default', file: 'Profile'),
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			],
			'default' => true,
			'enabled' => empty(Config::$modSettings['disableCustomPerPage']),
		],
		[
			'id' => 'view_newest_first',
			'label' => Lang::getTxt('recent_posts_at_top', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'show_no_avatars',
			'label' => Lang::getTxt('show_no_avatars', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'show_no_signatures',
			'label' => Lang::getTxt('show_no_signatures', file: 'Profile'),
			'default' => true,
		],
		array(
			'id' => 'show_no_censored',
			'label' => Lang::getTxt('show_no_censored'),
			'default' => false,
			'enabled' => !empty(Config::$modSettings['allow_no_censored']),
		),
		[
			'id' => 'posts_apply_ignore_list',
			'label' => Lang::getTxt('posts_apply_ignore_list', file: 'Profile'),
			'default' => false,
			'enabled' => !empty(Config::$modSettings['enable_buddylist'])
		],
		Lang::getTxt('theme_opt_posting', file: 'Profile'),
		[
			'id' => 'return_to_post',
			'label' => Lang::getTxt('return_to_post', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'no_new_reply_warning',
			'label' => Lang::getTxt('no_new_reply_warning', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'wysiwyg_default',
			'label' => Lang::getTxt('wysiwyg_default', file: 'Profile'),
			'default' => false,
			'enabled' => empty(Config::$modSettings['disable_wysiwyg']),
		],
		[
			'id' => 'drafts_autosave_enabled',
			'label' => Lang::getTxt('drafts_autosave_enabled', file: 'Drafts'),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_autosave_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		],
		[
			'id' => 'drafts_show_saved_enabled',
			'label' => Lang::getTxt('drafts_show_saved_enabled', file: 'Drafts'),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_show_saved_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		],
		Lang::getTxt('theme_opt_moderation', file: 'Profile'),
		[
			'id' => 'display_quick_mod',
			'label' => Lang::getTxt('display_quick_mod', file: 'Profile'),
			'options' => [
				0 => Lang::getTxt('display_quick_mod_none', file: 'Profile'),
				1 => Lang::getTxt('display_quick_mod_check', file: 'Profile'),
				2 => Lang::getTxt('display_quick_mod_image', file: 'Profile'),
			],
			'default' => true,
		],
		Lang::getTxt('theme_opt_personal_messages', file: 'Profile'),
		[
			'id' => 'popup_messages',
			'label' => Lang::getTxt('popup_messages', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'view_newest_pm_first',
			'label' => Lang::getTxt('recent_pms_at_top', file: 'Profile'),
			'default' => true,
		],
		[
			'id' => 'pm_remove_inbox_label',
			'label' => Lang::getTxt('pm_remove_inbox_label', file: 'Profile'),
			'default' => true,
		],
		!empty(Config::$modSettings['cal_enabled']) ? Lang::getTxt('theme_opt_calendar', file: 'Profile') : '',
		[
			'id' => 'calendar_default_view',
			'label' => Lang::getTxt('calendar_default_view', file: 'Profile'),
			'options' => [
				'viewlist' => Lang::getTxt('calendar_viewlist', file: 'Profile'),
				'viewmonth' => Lang::getTxt('calendar_viewmonth', file: 'Profile'),
				'viewweek' => Lang::getTxt('calendar_viewweek', file: 'Profile')
			],
			'default' => true,
			'enabled' => !empty(Config::$modSettings['cal_enabled']),
		],
		[
			'id' => 'calendar_start_day',
			'label' => Lang::getTxt('calendar_start_day', file: 'Profile'),
			'options' => array_filter(
				Lang::getTxt('days', file: 'General'),
				fn($key) => in_array($key, [0, 1, 5, 6]),
				ARRAY_FILTER_USE_KEY,
			),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['cal_enabled']),
		],
	];
}

/**
 * This pseudo-template defines all the available theme settings (but not their actual values)
 */
function template_settings()
{
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
}
