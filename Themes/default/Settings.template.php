<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * This pseudo-template defines all the theme options
 */
function template_options()
{
	Utils::$context['theme_options'] = array(
		Lang::getTxt('theme_opt_display', file: 'Profile'),
		array(
			'id' => 'show_children',
			'label' => Lang::getTxt('show_children', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'topics_per_page',
			'label' => Lang::getTxt('topics_per_page', file: 'Profile'),
			'options' => array(
				0 => Lang::getTxt('per_page_default', file: 'Profile'),
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
			'enabled' => empty(Config::$modSettings['disableCustomPerPage']),
		),
		array(
			'id' => 'messages_per_page',
			'label' => Lang::getTxt('messages_per_page', file: 'Profile'),
			'options' => array(
				0 => Lang::getTxt('per_page_default', file: 'Profile'),
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
			'enabled' => empty(Config::$modSettings['disableCustomPerPage']),
		),
		array(
			'id' => 'view_newest_first',
			'label' => Lang::getTxt('recent_posts_at_top', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'show_no_avatars',
			'label' => Lang::getTxt('show_no_avatars', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'show_no_signatures',
			'label' => Lang::getTxt('show_no_signatures', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'posts_apply_ignore_list',
			'label' => Lang::getTxt('posts_apply_ignore_list', file: 'Profile'),
			'default' => false,
			'enabled' => !empty(Config::$modSettings['enable_buddylist'])
		),
		Lang::getTxt('theme_opt_posting', file: 'Profile'),
		array(
			'id' => 'return_to_post',
			'label' => Lang::getTxt('return_to_post', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'no_new_reply_warning',
			'label' => Lang::getTxt('no_new_reply_warning', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'auto_notify',
			'label' => Lang::getTxt('auto_notify', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'wysiwyg_default',
			'label' => Lang::getTxt('wysiwyg_default', file: 'Profile'),
			'default' => false,
			'enabled' => empty(Config::$modSettings['disable_wysiwyg']),
		),
		array(
			'id' => 'drafts_autosave_enabled',
			'label' => Lang::getTxt('drafts_autosave_enabled', file: 'Drafts'),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_autosave_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		),
		array(
			'id' => 'drafts_show_saved_enabled',
			'label' => Lang::getTxt('drafts_show_saved_enabled', file: 'Drafts'),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_show_saved_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		),
		Lang::getTxt('theme_opt_moderation', file: 'Profile'),
		array(
			'id' => 'display_quick_mod',
			'label' => Lang::getTxt('display_quick_mod', file: 'Profile'),
			'options' => array(
				0 => Lang::getTxt('display_quick_mod_none', file: 'Profile'),
				1 => Lang::getTxt('display_quick_mod_check', file: 'Profile'),
				2 => Lang::getTxt('display_quick_mod_image', file: 'Profile'),
			),
			'default' => true,
		),
		Lang::getTxt('theme_opt_personal_messages', file: 'Profile'),
		array(
			'id' => 'popup_messages',
			'label' => Lang::getTxt('popup_messages', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'view_newest_pm_first',
			'label' => Lang::getTxt('recent_pms_at_top', file: 'Profile'),
			'default' => true,
		),
		array(
			'id' => 'pm_remove_inbox_label',
			'label' => Lang::getTxt('pm_remove_inbox_label', file: 'Profile'),
			'default' => true,
		),
		!empty(Config::$modSettings['cal_enabled']) ? Lang::getTxt('theme_opt_calendar', file: 'Profile') : '',
		array(
			'id' => 'calendar_default_view',
			'label' => Lang::getTxt('calendar_default_view', file: 'Profile'),
			'options' => array(
				'viewlist' => Lang::getTxt('calendar_viewlist', file: 'Profile'),
				'viewmonth' => Lang::getTxt('calendar_viewmonth', file: 'Profile'),
				'viewweek' => Lang::getTxt('calendar_viewweek', file: 'Profile')
			),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['cal_enabled']),
		),
		array(
			'id' => 'calendar_start_day',
			'label' => Lang::getTxt('calendar_start_day', file: 'Profile'),
			'options' => array(
				0 => Lang::$txt['days'][0],
				1 => Lang::$txt['days'][1],
				6 => Lang::$txt['days'][6],
			),
			'default' => true,
			'enabled' => !empty(Config::$modSettings['cal_enabled']),
		),
	);
}

/**
 * This pseudo-template defines all the available theme settings (but not their actual values)
 */
function template_settings()
{
	Utils::$context['theme_settings'] = array(
		array(
			'id' => 'header_logo_url',
			'label' => Lang::getTxt('header_logo_url', file: 'Themes'),
			'description' => Lang::getTxt('header_logo_url_desc', file: 'Themes'),
			'type' => 'text',
		),
		array(
			'id' => 'site_slogan',
			'label' => Lang::getTxt('site_slogan', file: 'Themes'),
			'description' => Lang::getTxt('site_slogan_desc', file: 'Themes'),
			'type' => 'text',
		),
		array(
			'id' => 'og_image',
			'label' => Lang::getTxt('og_image', file: 'Themes'),
			'description' => Lang::getTxt('og_image_desc', file: 'Themes'),
			'type' => 'url',
		),
		'',
		array(
			'id' => 'smiley_sets_default',
			'label' => Lang::getTxt('smileys_default_set_for_theme', file: 'Admin'),
			'options' => Utils::$context['smiley_sets'],
			'type' => 'text',
		),
		'',
		array(
			'id' => 'enable_news',
			'label' => Lang::getTxt('enable_random_news', file: 'Themes'),
		),
		array(
			'id' => 'show_newsfader',
			'label' => Lang::getTxt('news_fader', file: 'Themes'),
		),
		array(
			'id' => 'newsfader_time',
			'label' => Lang::getTxt('admin_fader_delay', file: 'Admin'),
			'type' => 'number',
		),
		'',
		array(
			'id' => 'number_recent_posts',
			'label' => Lang::getTxt('number_recent_posts', file: 'Themes'),
			'description' => Lang::getTxt('zero_to_disable', file: 'Admin'),
			'type' => 'number',
		),
		array(
			'id' => 'show_stats_index',
			'label' => Lang::getTxt('show_stats_index', file: 'Themes'),
		),
		array(
			'id' => 'show_latest_member',
			'label' => Lang::getTxt('latest_members', file: 'Themes'),
		),
		array(
			'id' => 'show_group_key',
			'label' => Lang::getTxt('show_group_key', file: 'Themes'),
		),
		array(
			'id' => 'display_who_viewing',
			'label' => Lang::getTxt('who_display_viewing', file: 'Themes'),
			'options' => array(
				0 => Lang::getTxt('who_display_viewing_off', file: 'Themes'),
				1 => Lang::getTxt('who_display_viewing_numbers', file: 'Themes'),
				2 => Lang::getTxt('who_display_viewing_names', file: 'Themes'),
			),
			'type' => 'list',
		),
	);
}

?>