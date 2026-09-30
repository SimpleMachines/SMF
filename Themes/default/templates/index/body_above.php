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
use SMF\Time;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The upper part of the main template layer. This is the stuff that shows above the main forum content.
 */
?><?php /* Wrapper div now echoes permanently for better layout options. h1 a is now target for "Go up" links. */ ?>

	<div id="top_section">
		<div class="inner_wrap content_wrapper"><?php /* If the user is logged in, display some things that might be useful. */ ?><?php if (!User::$me->is_guest): ?><?php /* Firstly, the user's menu */ ?>

			<ul class="floatleft" id="top_info">
				<li>
					<a href="<?= Config::$scripturl ?>?action=profile"<?= !empty(Utils::$context['self_profile']) ? ' class="active"' : '' ?> id="profile_menu_top"><?php if (!empty(User::$me->avatar)): ?><?= User::$me->avatar['image'] ?><?php endif; ?><span class="textmenu"><?= User::$me->name ?></span></a>
					<div id="profile_menu" class="top_menu"></div>
				</li><?php /* Secondly, PMs if we're doing them */ ?><?php if (Utils::$context['allow_pm']): ?>

				<li>
					<a href="<?= Config::$scripturl ?>?action=pm"<?= !empty(Utils::$context['self_pm']) ? ' class="active"' : '' ?> id="pm_menu_top">
						<span class="main_icons inbox"></span>
						<span class="textmenu"><?= Lang::getTxt('pm_short', file: 'General') ?></span><?= !empty(User::$me->unread_messages) ? '
						<span class="amt">' . User::$me->unread_messages . '</span>' : '' ?>

					</a>
					<div id="pm_menu" class="top_menu scrollable"></div>
				</li><?php endif; ?><?php /* Thirdly, alerts */ ?>

				<li>
					<a href="<?= Config::$scripturl ?>?action=profile;area=showalerts;u=<?= User::$me->id ?>"<?= !empty(Utils::$context['self_alerts']) ? ' class="active"' : '' ?> id="alerts_menu_top">
						<span class="main_icons alerts"></span>
						<span class="textmenu"><?= Lang::getTxt('alerts', file: 'General') ?></span><?= !empty(User::$me->alerts) ? '
						<span class="amt">' . User::$me->alerts . '</span>' : '' ?>

					</a>
					<div id="alerts_menu" class="top_menu scrollable"></div>
				</li><?php /* A logout button for people without JavaScript. */ ?><?php if (empty(Theme::$current->settings['login_main_menu'])): ?>

				<li id="nojs_logout">
					<a href="<?= Config::$scripturl ?>?action=logout;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('logout', file: 'General') ?></a>
					<script>document.getElementById("nojs_logout").style.display = "none";</script>
				</li><?php endif; ?><?php /* And now we're done. */ ?>

			</ul><?php /* Otherwise they're a guest. Ask them to either register or login. */ ?><?php elseif (empty(Config::$maintenance)): ?><?php /* Some people like to do things the old-fashioned way. */ ?><?php if (!empty(Theme::$current->settings['login_main_menu'])): ?>

			<ul class="floatleft">
				<li class="welcome"><?= Lang::getTxt(
				Utils::$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest',
				[
					'forum_name' => Utils::$context['forum_name_html_safe'],
					'login_url' => Config::$scripturl . '?action=login',
					'onclick' => 'return reqOverlayDiv(this.href, ' . Utils::escapeJavaScript(Lang::getTxt('login', file: 'General')) . ', \'login\');',
					'register_url' => Config::$scripturl . '?action=signup',
				],
				file: 'General',
			) ?></li>
			</ul><?php else: ?>

			<ul class="floatleft" id="top_info">
				<li class="welcome">
					<?= Lang::getTxt('welcome_to_forum', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'General') ?>

				</li>
				<li class="button_login">
					<a href="<?= Config::$scripturl ?>?action=login" class="<?= Utils::$context['current_action'] == 'login' ? 'active' : 'open' ?>" onclick="return reqOverlayDiv(this.href, <?= Utils::escapeJavaScript(Lang::getTxt('login', file: 'General')) ?>, 'login');">
						<span class="main_icons login"></span>
						<span class="textmenu"><?= Lang::getTxt('login', file: 'General') ?></span>
					</a>
				</li><?php if (Utils::$context['can_register']): ?>

				<li class="button_signup">
					<a href="<?= Config::$scripturl ?>?action=signup" class="<?= Utils::$context['current_action'] == 'signup' ? 'active' : 'open' ?>">
						<span class="main_icons regcenter"></span>
						<span class="textmenu"><?= Lang::getTxt('register', file: 'General') ?></span>
					</a>
				</li><?php endif; ?>

			</ul><?php endif; ?><?php else: ?><?php /* In maintenance mode, only login is allowed and don't show OverlayDiv */ ?>

			<ul class="floatleft welcome">
				<li><?= Lang::getTxt(
			'welcome_guest',
			[
				'forum_name' => Utils::$context['forum_name_html_safe'],
				'login_url' => Config::$scripturl . '?action=login',
				'onclick' => 'return true;',
			],
			file: 'General',
		) ?></li>
			</ul><?php endif; ?><?php if (!empty(Config::$modSettings['userLanguage']) && !empty(Utils::$context['languages']) && count(Utils::$context['languages']) > 1): ?>

			<form id="languages_form" method="get" class="floatright">
				<select id="language_select" name="language" onchange="this.form.submit()"><?php foreach (Utils::$context['languages'] as $language): ?>

					<option value="<?= $language['filename'] ?>"<?= isset(User::$me->language) && User::$me->language == $language['filename'] ? ' selected="selected"' : '' ?>><?= str_replace('-utf8', '', $language['name']) ?></option><?php endforeach; ?>

				</select>
				<noscript>
					<input type="submit" value="<?= Lang::getTxt('quick_mod_go', file: 'General') ?>">
				</noscript>
			</form><?php endif; ?><?php if (Utils::$context['allow_search']): ?>

			<form id="search_form" class="floatright" action="<?= Config::$scripturl ?>?action=search2" method="post" accept-charset="UTF-8">
				<input type="search" name="search" value="">&nbsp;<?php
// Using the quick search dropdown?
$selected = !empty(Utils::$context['current_topic']) ? 'current_topic' : (!empty(Utils::$context['current_board']) ? 'current_board' : 'all');
?>

				<select name="search_selection">
					<option value="all"<?= ($selected == 'all' ? ' selected' : '') ?>><?= Lang::getTxt('search_entireforum', file: 'General') ?> </option><?php /* Can't limit it to a specific topic if we are not in one */ ?><?php if (!empty(Utils::$context['current_topic'])): ?>

					<option value="topic"<?= ($selected == 'current_topic' ? ' selected' : '') ?>><?= Lang::getTxt('search_thistopic', file: 'General') ?></option><?php endif; ?><?php /* Can't limit it to a specific board if we are not in one */ ?><?php if (!empty(Utils::$context['current_board'])): ?>

					<option value="board"<?= ($selected == 'current_board' ? ' selected' : '') ?>><?= Lang::getTxt('search_thisboard', file: 'General') ?></option><?php endif; ?><?php /* Can't search for members if we can't see the memberlist */ ?><?php if (!empty(Utils::$context['allow_memberlist'])): ?>

					<option value="members"<?= ($selected == 'members' ? ' selected' : '') ?>><?= Lang::getTxt('search_members', file: 'General') ?> </option><?php endif; ?>

				</select><?php /* Search within current topic? */ ?><?php if (!empty(Utils::$context['current_topic'])): ?>

				<input type="hidden" name="sd_topic" value="<?= Utils::$context['current_topic'] ?>"><?php /* If we're on a certain board, limit it to this board ;). */ ?><?php elseif (!empty(Utils::$context['current_board'])): ?>

				<input type="hidden" name="sd_brd" value="<?= Utils::$context['current_board'] ?>"><?php endif; ?>

				<input type="submit" name="search2" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button">
				<input type="hidden" name="advanced" value="0">
			</form><?php endif; ?>

		</div><!-- .inner_wrap -->
	</div><!-- #top_section -->
	<header id="header" class="content_wrapper">
		<h1 class="forumtitle">
			<a id="top" href="<?= Config::$scripturl ?>"><?= empty(Utils::$context['header_logo_url_html_safe']) ? Utils::$context['forum_name_html_safe'] : '<img src="' . Utils::$context['header_logo_url_html_safe'] . '" alt="' . Utils::$context['forum_name_html_safe'] . '">' ?></a>
		</h1>
		<?= empty(Theme::$current->settings['site_slogan']) ? '<img id="smflogo" src="' . Theme::$current->settings['images_url'] . '/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">' : '<div id="siteslogan">' . Theme::$current->settings['site_slogan'] . '</div>' ?>

	</header><?php
// The menu is a band of its own across the page, with its contents lined up
// with everything else by the wrapper inside it.
?>

	<nav id="main_menu" aria-label="<?= Lang::getTxt('mobile_user_menu', file: 'General') ?>">
		<div class="content_wrapper">
			<a class="mobile_user_menu">
				<span class="menu_icon"></span>
				<span class="text_menu"><?= Lang::getTxt('mobile_user_menu', file: 'General') ?></span>
			</a>
			<div id="mobile_user_menu" class="popup_container">
				<div class="popup_window description">
					<div class="popup_heading"><?= Lang::getTxt('mobile_user_menu', file: 'General') ?>

						<a href="javascript:void(0);" class="main_icons hide_popup"></a>
					</div>
					<?php $this->subTemplate('menu'); ?>

				</div>
			</div>
		</div>
	</nav>
	<div id="wrapper" class="content_wrapper">
		<div id="inner_wrap"<?= User::$me->is_guest ? ' class="hide_720"' : '' ?>>
			<div class="user">
				<time datetime="<?= Time::gmstrftime('%FT%TZ') ?>"><?= Utils::$context['current_time'] ?></time><?php if (!User::$me->is_guest): ?>

				<ul class="unread_links">
					<li>
						<a href="<?= Config::$scripturl ?>?action=unread" title="<?= Lang::getTxt('unread_since_visit', file: 'General') ?>"><?= Lang::getTxt('view_unread_category', file: 'General') ?></a>
					</li>
					<li>
						<a href="<?= Config::$scripturl ?>?action=unreadreplies" title="<?= Lang::getTxt('show_unread_replies', file: 'General') ?>"><?= Lang::getTxt('unread_replies', file: 'General') ?></a>
					</li>
				</ul><?php endif; ?>

			</div><?php /* Show a random news item? (or you could pick one from news_lines...) */ ?><?php if (!empty(Theme::$current->settings['enable_news']) && !empty(Utils::$context['random_news_line'])): ?>

			<div class="news">
				<h2><?= Lang::getTxt('news', file: 'General') ?>: </h2>
				<p><?= Utils::$context['random_news_line'] ?></p>
			</div><?php endif; ?>

		</div><!-- #inner_wrap --><?php $this->subTemplate('linktree'); ?><?php /* The main content should go here. */ ?>

		<div id="content_section">
			<main id="main_content_section">