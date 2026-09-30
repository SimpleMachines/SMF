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
use SMF\Theme;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The main sub template above the content.
 */
?><?php /* Show right to left, the language code, and the character set for ease of translating. */ ?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?><?= !empty(Lang::getTxt('lang_locale', file: 'General')) ? ' lang="' . str_replace('_', '-', substr(Lang::getTxt('lang_locale', file: 'General'), 0, strcspn(Lang::getTxt('lang_locale', file: 'General'), '.'))) . '"' : '' ?><?= !empty(Theme::$current->settings['theme_variants']) ? ' data-variant=' . (Utils::$context['theme_variant'] ?: 'default') . '' : '' ?><?= !empty(Theme::$current->settings['has_dark_mode']) ? ' data-mode=' . (Utils::$context['theme_colormode'] ?? 'light') . '' : '' ?>>
<head>
	<meta charset="UTF-8"><?php
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
?><?php /* load in any css from mods or themes so they can overwrite if wanted */ ?><?php Theme::template_css(); ?><?php /* load in any javascript files from mods and themes */ ?><?php Theme::template_javascript(); ?>

	<title><?= Utils::$context['page_title_html_safe'] ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1"><?php /* Content related meta tags, like description, keywords, Open Graph stuff, etc... */ ?><?php foreach (Utils::$context['meta_tags'] as $meta_tag): ?>

	<meta<?php foreach ($meta_tag as $meta_key => $meta_value): ?> <?= $meta_key ?>="<?= $meta_value ?>"<?php endforeach; ?>><?php endforeach; ?><?php
/*	What is your Lollipop's color?
		Theme Authors, you can change the color here to make sure your theme's main color gets visible on tab */
?>

	<meta name="theme-color" content="#557EA0"><?php /* Please don't index these Mr Robot. */ ?><?php if (!empty(Utils::$context['robot_no_index'])): ?>

	<meta name="robots" content="noindex"><?php endif; ?><?php /* Present a canonical url for search engines to prevent duplicate content in their indices. */ ?><?php if (!empty(Utils::$context['canonical_url'])): ?>

	<link rel="canonical" href="<?= Utils::$context['canonical_url'] ?>"><?php endif; ?><?php /* Show all the relative links, such as help, search, contents, and the like. */ ?>

	<link rel="help" href="<?= Config::$scripturl ?>?action=help">
	<link rel="contents" href="<?= Config::$scripturl ?>"><?= (Utils::$context['allow_search'] ? '
	<link rel="search" href="' . Config::$scripturl . '?action=search">' : '') ?><?php /* If RSS feeds are enabled, advertise the presence of one. */ ?><?php if (!empty(Config::$modSettings['xmlnews_enable']) && (!empty(Config::$modSettings['allow_guestAccess']) || !User::$me->is_guest)): ?>

	<link rel="alternate" type="application/rss+xml" title="<?= Utils::$context['forum_name_html_safe'] ?> - <?= Lang::getTxt('rss', file: 'General') ?>" href="<?= Config::$scripturl ?>?action=feed;type=rss2<?= !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '' ?>">
	<link rel="alternate" type="application/atom+xml" title="<?= Utils::$context['forum_name_html_safe'] ?> - <?= Lang::getTxt('atom', file: 'General') ?>" href="<?= Config::$scripturl ?>?action=feed;type=atom<?= !empty(Utils::$context['current_board']) ? ';board=' . Utils::$context['current_board'] : '' ?>"><?php endif; ?><?php /* If we're viewing a topic, these should be the previous and next topics, respectively. */ ?><?php if (!empty(Utils::$context['links']['next'])): ?>

	<link rel="next" href="<?= Utils::$context['links']['next'] ?>"><?php endif; ?><?php if (!empty(Utils::$context['links']['prev'])): ?>

	<link rel="prev" href="<?= Utils::$context['links']['prev'] ?>"><?php endif; ?><?php /* If we're in a board, or a topic for that matter, the index will be the board's index. */ ?><?php if (!empty(Utils::$context['current_board'])): ?>

	<link rel="index" href="<?= Config::$scripturl ?>?board=<?= Utils::$context['current_board'] ?>.0"><?php endif; ?><?php /* Output any remaining HTML headers. (from mods, maybe?) */ ?><?= Utils::$context['html_headers'] ?>

</head>
<body id="<?= Utils::$context['browser_body_id'] ?>" class="action_<?= !empty(Utils::$context['current_action']) ? Utils::$context['current_action'] : (!empty(Utils::$context['current_board']) ?
		'messageindex' : (!empty(Utils::$context['current_topic']) ? 'display' : 'home')) ?><?= !empty(Utils::$context['current_board']) ? ' board_' . Utils::$context['current_board'] : '' ?>">
<div id="footerfix">