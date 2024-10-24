<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
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
		Lang::$txt['theme_opt_display'],
		[
			'id' => 'show_children',
			'label' => Lang::$txt['show_children'],
			'default' => true,
		],
		[
			'id' => 'topics_per_page',
			'label' => Lang::$txt['topics_per_page'],
			'options' => [
				0 => Lang::$txt['per_page_default'],
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
			'label' => Lang::$txt['messages_per_page'],
			'options' => [
				0 => Lang::$txt['per_page_default'],
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
			'label' => Lang::$txt['recent_posts_at_top'],
			'default' => true,
		],
		[
			'id' => 'show_no_avatars',
			'label' => Lang::$txt['show_no_avatars'],
			'default' => true,
		],
		[
			'id' => 'show_no_signatures',
			'label' => Lang::$txt['show_no_signatures'],
			'default' => true,
		],
		[
			'id' => 'posts_apply_ignore_list',
			'label' => Lang::$txt['posts_apply_ignore_list'],
			'default' => false,
			'enabled' => !empty(Config::$modSettings['enable_buddylist'])
		],
		Lang::$txt['theme_opt_posting'],
		[
			'id' => 'return_to_post',
			'label' => Lang::$txt['return_to_post'],
			'default' => true,
		],
		[
			'id' => 'no_new_reply_warning',
			'label' => Lang::$txt['no_new_reply_warning'],
			'default' => true,
		],
		[
			'id' => 'auto_notify',
			'label' => Lang::$txt['auto_notify'],
			'default' => true,
		],
		[
			'id' => 'wysiwyg_default',
			'label' => Lang::$txt['wysiwyg_default'],
			'default' => false,
			'enabled' => empty(Config::$modSettings['disable_wysiwyg']),
		],
		[
			'id' => 'drafts_autosave_enabled',
			'label' => Lang::$txt['drafts_autosave_enabled'],
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_autosave_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		],
		[
			'id' => 'drafts_show_saved_enabled',
			'label' => Lang::$txt['drafts_show_saved_enabled'],
			'default' => true,
			'enabled' => !empty(Config::$modSettings['drafts_show_saved_enabled']) && (!empty(Config::$modSettings['drafts_post_enabled']) || !empty(Config::$modSettings['drafts_pm_enabled'])),
		],
		Lang::$txt['theme_opt_moderation'],
		[
			'id' => 'display_quick_mod',
			'label' => Lang::$txt['display_quick_mod'],
			'options' => [
				0 => Lang::$txt['display_quick_mod_none'],
				1 => Lang::$txt['display_quick_mod_check'],
				2 => Lang::$txt['display_quick_mod_image'],
			],
			'default' => true,
		],
		Lang::$txt['theme_opt_personal_messages'],
		[
			'id' => 'popup_messages',
			'label' => Lang::$txt['popup_messages'],
			'default' => true,
		],
		[
			'id' => 'view_newest_pm_first',
			'label' => Lang::$txt['recent_pms_at_top'],
			'default' => true,
		],
		[
			'id' => 'pm_remove_inbox_label',
			'label' => Lang::$txt['pm_remove_inbox_label'],
			'default' => true,
		],
		Lang::$txt['theme_opt_calendar'],
		[
			'id' => 'calendar_default_view',
			'label' => Lang::$txt['calendar_default_view'],
			'options' => [
				'viewlist' => Lang::$txt['calendar_viewlist'],
				'viewmonth' => Lang::$txt['calendar_viewmonth'],
				'viewweek' => Lang::$txt['calendar_viewweek']
			],
			'default' => true,
			'enabled' => !empty(Config::$modSettings['cal_enabled']),
		],
		[
			'id' => 'calendar_start_day',
			'label' => Lang::$txt['calendar_start_day'],
			'options' => [
				0 => Lang::$txt['days'][0],
				1 => Lang::$txt['days'][1],
				6 => Lang::$txt['days'][6],
			],
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
			'label' => Lang::$txt['header_logo_url'],
			'description' => Lang::$txt['header_logo_url_desc'],
			'type' => 'text',
		],
		[
			'id' => 'site_slogan',
			'label' => Lang::$txt['site_slogan'],
			'description' => Lang::$txt['site_slogan_desc'],
			'type' => 'text',
		],
		[
			'id' => 'og_image',
			'label' => Lang::$txt['og_image'],
			'description' => Lang::$txt['og_image_desc'],
			'type' => 'url',
		],
		'',
		[
			'id' => 'smiley_sets_default',
			'label' => Lang::$txt['smileys_default_set_for_theme'],
			'options' => Utils::$context['smiley_sets'],
			'type' => 'text',
		],
		'',
		[
			'id' => 'enable_news',
			'label' => Lang::$txt['enable_random_news'],
		],
		[
			'id' => 'show_newsfader',
			'label' => Lang::$txt['news_fader'],
		],
		[
			'id' => 'newsfader_time',
			'label' => Lang::$txt['admin_fader_delay'],
			'type' => 'number',
		],
		'',
		[
			'id' => 'number_recent_posts',
			'label' => Lang::$txt['number_recent_posts'],
			'description' => Lang::$txt['zero_to_disable'],
			'type' => 'number',
		],
		[
			'id' => 'show_stats_index',
			'label' => Lang::$txt['show_stats_index'],
		],
		[
			'id' => 'show_latest_member',
			'label' => Lang::$txt['latest_members'],
		],
		[
			'id' => 'show_group_key',
			'label' => Lang::$txt['show_group_key'],
		],
		[
			'id' => 'display_who_viewing',
			'label' => Lang::$txt['who_display_viewing'],
			'options' => [
				0 => Lang::$txt['who_display_viewing_off'],
				1 => Lang::$txt['who_display_viewing_numbers'],
				2 => Lang::$txt['who_display_viewing_names'],
			],
			'type' => 'list',
		],
	];
}

?>