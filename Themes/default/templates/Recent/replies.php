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

use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing unread replies (eg new replies to topics you've posted in)
 */
?><?php /* User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device. */ ?><?php if (!empty(Utils::$context['recent_buttons'])): ?>
	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading">
				<?= Lang::getTxt('mobile_action', file: 'General') ?>
				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['recent_buttons']]); ?>
		</div>
	</div><?php endif; ?>
	<div id="recent">
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span><?= (!empty(Board::$info->name) ? Board::$info->name . ' - ' : '') ?><?= Utils::$context['page_title'] ?></span>
			</h2>
		</div><?php if (Utils::$context['showCheckboxes']): ?>
		<form action="<?= Config::$scripturl ?>?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unreadreplies<?= (!empty(Utils::$context['showing_all_topics']) ? ';all' : '') ?><?= Utils::$context['querystring_board_limits'] ?>"><?php endif; ?><?php if (!empty(Utils::$context['topics'])): ?>
			<div class="pagesection">
				<?= Utils::$context['menu_separator'] ?>
				<div class="pagelinks floatleft">
					<a href="#bot" class="button"><?= Lang::getTxt('go_down', file: 'General') ?></a>
					<?= Utils::$context['page_index'] ?>
				</div>
				<?= !empty(Utils::$context['recent_buttons']) ? $this->fetchSubTemplate('button_strip', ['button_strip' => Utils::$context['recent_buttons'], 'direction' => 'right']) : '' ?><?php /* Mobile action (top) */ ?><?php if (!empty(Utils::$context['recent_buttons'])): ?>
				<div class="mobile_buttons floatright">
					<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
				</div><?php endif; ?>
			</div>
			<div id="unreadreplies">
				<div id="topic_header" class="title_bar">
					<div class="board_icon"></div>
					<div class="info"><?= Utils::$context['topics_headers']['subject'] ?> / <?= Utils::$context['topics_headers']['starter'] ?></div>
					<div class="board_stats centertext"><?= Utils::$context['topics_headers']['replies'] ?> / <?= Utils::$context['topics_headers']['views'] ?></div>
					<div class="lastpost"><?= Utils::$context['topics_headers']['last_post'] ?></div><?php /* Show a "select all" box for quick moderation? */ ?><?php if (Utils::$context['showCheckboxes']): ?>
					<div class="moderation">
						<input type="checkbox" onclick="invertAll(this, this.form, 'topics[]');">
					</div><?php endif; ?>
				</div><!-- #topic_header -->
				<div id="topic_container"><?php foreach (Utils::$context['topics'] as $topic): ?>
					<div class="<?= $topic['css_class'] ?>">
						<div class="board_icon">
							<img src="<?= $topic['first_post']['icon_url'] ?>" alt="">
							<?= $topic['is_posted_in'] ? '<span class="main_icons profile_sm"></span>' : '' ?>
						</div>
						<div class="info"><?php /* Now we handle the icons */ ?>
							<div class="icons floatright"><?php if ($topic['is_locked']): ?>
								<span class="main_icons lock"></span><?php endif; ?><?php if ($topic['is_sticky']): ?>
								<span class="main_icons sticky"></span><?php endif; ?><?php if ($topic['is_poll']): ?>
								<span class="main_icons poll"></span><?php endif; ?>
							</div>
							<div class="recent_title">
								<a href="<?= $topic['new_href'] ?>" id="newicon<?= $topic['first_post']['id'] ?>" class="new_posts"><?= Lang::getTxt('new', file: 'General') ?></a>
								<?= $topic['is_sticky'] ? '<strong>' : '' ?><span title="<?= $topic[(empty(Config::$modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'] ?>"><span id="msg_<?= $topic['first_post']['id'] ?>"><?= $topic['first_post']['link'] ?></span><?= $topic['is_sticky'] ? '</strong>' : '' ?>
							</div>
							<p class="floatleft">
								<?= $topic['first_post']['started_by'] ?>
							</p>
							<?= !empty($topic['pages']) ? '<span id="pages' . $topic['first_post']['id'] . '" class="topic_pages">' . $topic['pages'] . '</span>' : '' ?>
						</div><!-- .info -->
						<div class="board_stats centertext">
							<p>
								<?= Lang::getTxt('number_of_replies', [$topic['replies']], file: 'General') ?>
								<br>
								<?= Lang::getTxt('number_of_views', [$topic['views']], file: 'General') ?>
							</p>
						</div>
						<div class="lastpost">
							<?= Lang::getTxt('last_post_topic', ['post_link' => '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', 'member_link' => $topic['last_post']['member']['link']], file: 'General') ?>
						</div><?php if (Utils::$context['showCheckboxes']): ?>
						<div class="moderation">
							<input type="checkbox" name="topics[]" value="<?= $topic['id'] ?>">
						</div><?php endif; ?>
					</div><!-- $topic[css_class] --><?php endforeach; ?>
				</div><!-- #topic_container -->
			</div><!-- #unreadreplies -->
			<div class="pagesection">
				<?= !empty(Utils::$context['recent_buttons']) ? $this->fetchSubTemplate('button_strip', ['button_strip' => Utils::$context['recent_buttons'], 'direction' => 'right']) : '' ?>
				<?= Utils::$context['menu_separator'] ?>
				<div class="pagelinks floatleft">
					<a href="#recent" class="button" id="bot"><?= Lang::getTxt('go_up', file: 'General') ?></a>
					<?= Utils::$context['page_index'] ?>
				</div><?php /* Mobile action (bottom) */ ?><?php if (!empty(Utils::$context['recent_buttons'])): ?>
				<div class="mobile_buttons floatright">
					<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
				</div><?php endif; ?>
			</div><?php else: ?>
			<div class="infobox">
				<p class="centertext">
					<?= Lang::getTxt(Utils::$context['showing_all_topics'] ? 'topic_alert_none' : 'updated_topics_visit_none', file: 'General') ?>
				</p>
			</div><?php endif; ?><?php if (Utils::$context['showCheckboxes']): ?>
		</form><?php endif; ?>
	</div><!-- #recent --><?php if (empty(Utils::$context['no_topic_listing'])): ?><?php $this->subTemplate('topic_legend'); ?><?php endif; ?>
