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
 * The main messageindex.
 */
?><div id="display_head" class="information">
			<h2 class="display_title"><?= Utils::$context['name'] ?></h2><?php if (isset(Utils::$context['description']) && Utils::$context['description'] != ''): ?>

			<p><?= Utils::$context['description'] ?></p><?php endif; ?><?php if (!empty(Utils::$context['moderators'])): ?>

			<p><?= Lang::getTxt('moderators_list', ['num' => count(Utils::$context['link_moderators']), 'list' => Lang::sentenceList(Utils::$context['link_moderators'])], file: 'General') ?>.</p><?php endif; ?><?php if (!empty(Theme::$current->settings['display_who_viewing'])): ?><?php /* Show just numbers...? */ ?><?php if (Theme::$current->settings['display_who_viewing'] == 1 || empty(Utils::$context['view_members_list'])): ?><?php
// Count the ones this member is allowed to see. Any hidden ones get
// their own mention below, so counting those here as well would say
// the same people twice.
$list_of_viewers = [
	Lang::getTxt('number_of_members', [count(Utils::$context['view_members_list'])], file: 'General'),
];
// Or show the actual people viewing the topic?
?><?php else: ?><?php $list_of_viewers = Utils::$context['view_members_list']; ?><?php endif; ?><?php if (!empty(Utils::$context['view_num_hidden']) && !Utils::$context['can_moderate_forum']): ?><?php $list_of_viewers[] = Lang::getTxt('number_of_hidden_members', [Utils::$context['view_num_hidden']], file: 'General'); ?><?php endif; ?><?php /* Now show how many guests are here too. */ ?><?php if (!empty(Utils::$context['view_num_guests'])): ?><?php $list_of_viewers[] = Lang::getTxt('guest_plural', [Utils::$context['view_num_guests']], file: 'General'); ?><?php endif; ?>

			<p>
				<?= Lang::getTxt(
			'who_viewing_board',
			[
				'list_of_viewers' => Lang::sentenceList(array_values($list_of_viewers)),
				'num_viewing' => count(Utils::$context['view_members_list'] ?? []) + (int) (Utils::$context['view_num_guests'] ?? 0) + (int) (Utils::$context['view_num_hidden'] ?? 0),
			],
			file: 'General',
		) ?>

			</p><?php endif; ?>

		</div><?php if (!empty(Utils::$context['boards']) && (!empty(Theme::$current->options['show_children']) || Utils::$context['start'] == 0)): ?>

	<div id="board_<?= Utils::$context['current_board'] ?>_childboards" class="boardindex_table main_container">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('sub_boards', file: 'General') ?></h3>
		</div><?php $this->subTemplate('bi_board_list', ['boards' => Utils::$context['boards']]); ?>

	</div><!-- #board_[current_board]_childboards --><?php endif; ?><?php /* Let them know why their message became unapproved. */ ?><?php if (Utils::$context['becomesUnapproved']): ?>

	<div class="noticebox">
		<?= Lang::getTxt('post_becomes_unapproved', file: 'General') ?>

	</div><?php endif; ?><?php /* If this person can approve items and we have some awaiting approval tell them. */ ?><?php if (!empty(Utils::$context['unapproved_posts_message'])): ?>

	<div class="noticebox">
		<?= Utils::$context['unapproved_posts_message'] ?>

	</div><?php endif; ?><?php if (!Utils::$context['no_topic_listing']): ?>

	<div class="pagesection">
		<?= Utils::$context['menu_separator'] ?>

		<div class="pagelinks floatleft">
			<a href="#bot" class="button"><?= Lang::getTxt('go_down', file: 'General') ?></a>
			<?= Utils::$context['page_index'] ?>

		</div>
		<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons'], 'direction' => 'right']); ?><?php /* Mobile action buttons (top) */ ?><?php if (!empty(Utils::$context['normal_buttons'])): ?>

		<div class="mobile_buttons floatright">
			<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
		</div><?php endif; ?>

	</div><?php /* If Quick Moderation is enabled start the form. */ ?><?php if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics'])): ?>

	<form action="<?= Config::$scripturl ?>?action=quickmod;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm"><?php endif; ?>

		<div id="messageindex">
			<div class="title_bar" id="topic_header"><?php /* Are there actually any topics to show? */ ?><?php if (!empty(Utils::$context['topics'])): ?>

				<div class="board_icon"></div>
				<div class="info"><?= Utils::$context['topics_headers']['subject'] ?> / <?= Utils::$context['topics_headers']['starter'] ?></div>
				<div class="board_stats centertext"><?= Utils::$context['topics_headers']['replies'] ?> / <?= Utils::$context['topics_headers']['views'] ?></div>
				<div class="lastpost"><?= Utils::$context['topics_headers']['last_post'] ?></div><?php /* Show a "select all" box for quick moderation? */ ?><?php if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1): ?>

				<div class="moderation">
					<input type="checkbox" onclick="invertAll(this, this.form, 'topics[]');">
				</div><?php /* If it's on in "image" mode, don't show anything but the column. */ ?><?php elseif (!empty(Utils::$context['can_quick_mod'])): ?>

				<div class="moderation"></div><?php endif; ?><?php /* No topics... just say, "sorry bub". */ ?><?php else: ?>

				<h3 class="titlebg"><?= Lang::getTxt('topic_alert_none', file: 'General') ?></h3><?php endif; ?>

			</div><!-- #topic_header --><?php /* Contain the topic list */ ?>

			<div id="topic_container"><?php foreach (Utils::$context['topics'] as $topic): ?>

				<div class="<?= $topic['css_class'] ?>">
					<div class="board_icon">
						<img src="<?= $topic['first_post']['icon_url'] ?>" alt="">
						<?= $topic['is_posted_in'] ? '<span class="main_icons profile_sm"></span>' : '' ?>

					</div>
					<div class="info<?= !empty(Utils::$context['can_quick_mod']) ? '' : ' info_block' ?>">
						<div<?= !empty($topic['quick_mod']['modify']) ? ' data-msg-id="' . $topic['first_post']['id'] . '"' : '' ?>><?php /* Now we handle the icons */ ?>

							<div id="icons<?= $topic['first_post']['id'] ?>" class="icons floatright"><?php if ($topic['is_watched']): ?>

								<span class="main_icons watch" title="<?= Lang::getTxt('watching_this_topic', file: 'General') ?>"></span><?php endif; ?><?php if ($topic['is_locked']): ?>

								<span class="main_icons lock"></span><?php endif; ?><?php if ($topic['is_sticky']): ?>

								<span class="main_icons sticky"></span><?php endif; ?><?php if ($topic['is_redirect']): ?>

								<span class="main_icons move"></span><?php endif; ?><?php if ($topic['is_poll']): ?>

								<span class="main_icons poll"></span><?php endif; ?>

							</div>
							<div class="message_index_title">
								<?= $topic['new'] && !User::$me->is_guest ? '<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '" class="new_posts">' . Lang::getTxt('new', file: 'General') . '</a>' : '' ?>

								<span class="preview<?= $topic['is_sticky'] ? ' bold_text' : '' ?>" title="<?= $topic[(empty(Config::$modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'] ?>">
									<span id="msg<?= $topic['first_post']['id'] ?>"><?= $topic['first_post']['link'] ?><?= (!$topic['approved'] ? '&nbsp;<em>(' . Lang::getTxt('awaiting_approval', file: 'General') . ')</em>' : '') ?></span>
								</span>
							</div>
							<p class="floatleft">
								<?= Lang::getTxt('started_by_member', ['member' => $topic['first_post']['member']['link']], file: 'General') ?>

							</p>
							<?= !empty($topic['pages']) ? '<span id="pages' . $topic['first_post']['id'] . '" class="topic_pages">' . $topic['pages'] . '</span>' : '' ?>

						</div><!-- #topic_[first_post][id] -->
					</div><!-- .info -->
					<div class="board_stats centertext">
						<p><?= Lang::getTxt('number_of_replies', [$topic['replies']], file: 'General') ?><br><?= Lang::getTxt('number_of_views', [$topic['views']], file: 'General') ?></p>
					</div>
					<div class="lastpost">
						<p><?= Lang::getTxt('last_post_topic', ['post_link' => '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', 'member_link' => $topic['last_post']['member']['link']], file: 'General') ?></p>
					</div><?php /* Show the quick moderation options? */ ?><?php if (!empty(Utils::$context['can_quick_mod'])): ?>

					<div class="moderation"><?php if (Theme::$current->options['display_quick_mod'] == 1): ?>

						<input type="checkbox" name="topics[]" value="<?= $topic['id'] ?>"><?php else: ?><?php /* Check permissions on each and show only the ones they are allowed to use. */ ?><?php if ($topic['quick_mod']['remove']): ?><a href="<?= Config::$scripturl ?>?action=quickmod;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;actions%5B<?= $topic['id'] ?>%5D=remove;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="you_sure"><span class="main_icons delete" title="<?= Lang::getTxt('remove_topic', file: 'General') ?>"></span></a><?php endif; ?><?php if ($topic['quick_mod']['lock']): ?><a href="<?= Config::$scripturl ?>?action=quickmod;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;actions%5B<?= $topic['id'] ?>%5D=lock;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="you_sure"><span class="main_icons lock" title="<?= Lang::getTxt($topic['is_locked'] ? 'set_unlock' : 'set_lock', file: 'General') ?>"></span></a><?php endif; ?><?php if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove']): ?><br><?php endif; ?><?php if ($topic['quick_mod']['sticky']): ?><a href="<?= Config::$scripturl ?>?action=quickmod;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;actions%5B<?= $topic['id'] ?>%5D=sticky;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="you_sure"><span class="main_icons sticky" title="<?= Lang::getTxt($topic['is_sticky'] ? 'set_nonsticky' : 'set_sticky', file: 'General') ?>"></span></a><?php endif; ?><?php if ($topic['quick_mod']['move']): ?><a href="<?= Config::$scripturl ?>?action=movetopic;current_board=<?= Utils::$context['current_board'] ?>;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;topic=<?= $topic['id'] ?>.0"><span class="main_icons move" title="<?= Lang::getTxt('move_topic', file: 'General') ?>"></span></a><?php endif; ?><?php endif; ?>

					</div><!-- .moderation --><?php endif; ?>

				</div><!-- $topic[css_class] --><?php endforeach; ?>

			</div><!-- #topic_container --><?php if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics'])): ?>

			<div class="righttext" id="quick_actions">
				<select class="qaction" name="qaction"<?= Utils::$context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '' ?>>
					<option value="">--------</option><?php foreach (Utils::$context['qmod_actions'] as $qmod_action): ?><?php if (Utils::$context['can_' . $qmod_action]): ?>

					<option value="<?= $qmod_action ?>"><?= Lang::getTxt('quick_mod_' . $qmod_action, file: 'General') ?></option><?php endif; ?><?php endforeach; ?>

				</select><?php /* Show a list of boards they can move the topic to. */ ?><?php if (Utils::$context['can_move']): ?>

				<span id="quick_mod_jump_to"></span><?php endif; ?>

				<input type="submit" value="<?= Lang::getTxt('quick_mod_go', file: 'General') ?>" onclick="return document.forms.quickModForm.qaction.value != '' &amp;&amp; confirm('<?= Lang::getTxt('quickmod_confirm', file: 'General') ?>');" class="button qaction">
			</div><!-- #quick_actions --><?php endif; ?>

		</div><!-- #messageindex --><?php /* Finish off the form - again. */ ?><?php if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics'])): ?>

		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form><?php endif; ?>

	<div class="pagesection">
		<?= Utils::$context['menu_separator'] ?>

		<div class="pagelinks floatleft">
			<a href="#main_content_section" class="button" id="bot"><?= Lang::getTxt('go_up', file: 'General') ?></a>
			<?= Utils::$context['page_index'] ?>

		</div>
		<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons'], 'direction' => 'right']); ?><?php /* Mobile action buttons (bottom) */ ?><?php if (!empty(Utils::$context['normal_buttons'])): ?>

			<div class="mobile_buttons floatright">
				<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
			</div><?php endif; ?>

	</div><?php endif; ?><?php /* Show breadcrumbs at the bottom too. */ ?><?php $this->subTemplate('linktree'); ?>

	<script>
		window.addEventListener("DOMContentLoaded", function() {<?php if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']) && Utils::$context['can_move']): ?>

			new JumpTo({
				sContainerId: "quick_mod_jump_to",
				sClassName: "qaction",
				sJumpToTemplate: "%dropdown_list%",
				iCurBoardId: <?= Utils::$context['current_board'] ?>,
				iCurBoardChildLevel: <?= Utils::$context['jump_to']['child_level'] ?>,
				sCurBoardName: "<?= Utils::$context['jump_to']['board_name'] ?>",
				sBoardChildLevelIndicator: "==",
				sBoardPrefix: "=> ",
				sCatSeparator: "-----------------------------",
				sCatPrefix: "",
				bNoRedirect: true,
				bDisabled: true,
				sCustomName: "move_to"
			});<?php endif; ?><?php /* Javascript for inline editing. */ ?>

			new QuickModifyTopic({
				aHidePrefixes: ["icons", "msg", "pages", "newicon"],
				sTopicContainer: "topic_container",
			});
		});
	</script><?php $this->subTemplate('topic_legend'); ?><?php /* Lets pop the... */ ?>

	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading"><?= Lang::getTxt('mobile_action', file: 'General') ?>

				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons']]); ?>

		</div>
	</div>