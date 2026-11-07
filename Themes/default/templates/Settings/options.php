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

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This pseudo-template defines all the theme options
 */
?><?php
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
	[
		'id' => 'show_no_censored',
		'label' => Lang::getTxt('show_no_censored'),
		'default' => false,
		'enabled' => !empty(Config::$modSettings['allow_no_censored']),
	],
	[
		'id' => 'posts_apply_ignore_list',
		'label' => Lang::getTxt('posts_apply_ignore_list', file: 'Profile'),
		'default' => false,
		'enabled' => !empty(Config::$modSettings['enable_buddylist']),
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
			'viewweek' => Lang::getTxt('calendar_viewweek', file: 'Profile'),
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
?>
