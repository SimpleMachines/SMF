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
 * This template handles displaying a topic
 */
?><?php /* Let them know, if their report was a success! */ ?><?php if (Utils::$context['report_sent']): ?>

		<div class="infobox">
			<?= Lang::getTxt('report_sent', file: 'General') ?>

		</div><?php endif; ?><?php /* Let them know why their message became unapproved. */ ?><?php if (Utils::$context['becomesUnapproved']): ?>

		<div class="noticebox">
			<?= Lang::getTxt('post_becomes_unapproved', file: 'General') ?>

		</div><?php endif; ?><?php /* Show new topic info here? */ ?>

		<div id="display_head" class="information">
			<h2 class="display_title">
				<span id="top_subject"><?= Utils::$context['subject'] ?></span><?= (Utils::$context['is_locked']) ? ' <span class="main_icons lock"></span>' : '' ?><?= (Utils::$context['is_sticky']) ? ' <span class="main_icons sticky"></span>' : '' ?>

			</h2>
			<p><?= Lang::getTxt('started_by_member_time', ['member' => Utils::$context['topic_poster_name'], 'time' => Utils::$context['topic_started_time']], file: 'General') ?></p><?php /* Next - Prev */ ?>

			<span class="nextlinks floatright"><?= Utils::$context['previous_next'] ?></span><?php if (!empty(Theme::$current->settings['display_who_viewing'])): ?><?php /* Show just numbers...? */ ?><?php if (Theme::$current->settings['display_who_viewing'] == 1 || empty(Utils::$context['view_members_list'])): ?><?php
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
			'who_viewing_topic',
			[
				'list_of_viewers' => Lang::sentenceList(array_values($list_of_viewers)),
				'num_viewing' => count(Utils::$context['view_members_list'] ?? []) + (int) (Utils::$context['view_num_guests'] ?? 0) + (int) (Utils::$context['view_num_hidden'] ?? 0),
			],
			file: 'General',
		) ?>

			</p><?php endif; ?><?php /* Show the anchor for the top and for the first message. If the first message is new, say so. */ ?>

		</div><!-- #display_head -->
		<?= Utils::$context['first_new_message'] ? '<a id="new"></a>' : '' ?><?php /* Is this topic also a poll? */ ?><?php if (Utils::$context['is_poll']): ?>

		<div id="poll">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons poll"></span><?= Utils::$context['poll']['is_locked'] ? '<span class="main_icons lock"></span>' : '' ?> <?= Utils::$context['poll']['question'] ?>

				</h3>
			</div>
			<div class="windowbg">
				<div id="poll_options"><?php /* Are they not allowed to vote but allowed to view the options? */ ?><?php if (Utils::$context['poll']['show_results'] || !Utils::$context['allow_vote']): ?>

					<dl class="options"><?php /* Show each option with its corresponding percentage bar. */ ?><?php foreach (Utils::$context['poll']['options'] as $option): ?>

						<dt class="<?= $option['voted_this'] ? ' voted' : '' ?>"><?= $option['option'] ?></dt>
						<dd class="statsbar generic_bar<?= $option['voted_this'] ? ' voted' : '' ?>"><?php if (Utils::$context['allow_results_view']): ?>

							<?= $option['bar_ndt'] ?>

							<span class="percentage"><?= $option['votes'] ?> (<?= $option['percent'] ?>%)</span><?php endif; ?>

						</dd><?php endforeach; ?>

					</dl><?php if (Utils::$context['allow_results_view']): ?>

					<p><?= Lang::getTxt('poll_total_voters', [Utils::$context['poll']['total_votes']], file: 'General') ?></p><?php endif; ?><?php /* They are allowed to vote! Go to it! */ ?><?php else: ?>

					<form action="<?= Config::$scripturl ?>?action=vote;topic=<?= Utils::$context['current_topic'] ?>.<?= Utils::$context['start'] ?>" method="post" accept-charset="UTF-8"><?php /* Show a warning if they are allowed more than one option. */ ?><?php if (Utils::$context['poll']['allowed_warning']): ?>

						<p class="smallpadding"><?= Utils::$context['poll']['allowed_warning'] ?></p><?php endif; ?>

						<ul class="options"><?php /* Show each option with its button - a radio likely. */ ?><?php foreach (Utils::$context['poll']['options'] as $option): ?>

							<li><?= $option['vote_button'] ?> <label for="options-<?= $option['id'] ?>"><?= $option['option'] ?></label></li><?php endforeach; ?>

						</ul>
						<div class="submitbutton">
							<input type="submit" value="<?= Lang::getTxt('poll_vote', file: 'General') ?>" class="button">
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						</div>
					</form><?php endif; ?><?php /* Is the clock ticking? */ ?><?php if (!empty(Utils::$context['poll']['expire_time'])): ?>

					<p><strong><?= Lang::getTxt(Utils::$context['poll']['is_expired'] ? 'poll_expired_on' : 'poll_expires_on', file: 'General') ?>:</strong> <?= Utils::$context['poll']['expire_time'] ?></p><?php endif; ?>

				</div><!-- #poll_options -->
			</div><!-- .windowbg -->
		</div><!-- #poll -->
		<div id="pollmoderation"><?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['poll_buttons']]); ?>

		</div><?php endif; ?><?php /* Does this topic have some events linked to it? */ ?><?php if (!empty(Utils::$context['linked_calendar_events'])): ?>

		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('calendar_linked_events', file: 'Calendar') ?></h3>
		</div><?php $this->subTemplate('linked_events'); ?><?php endif; ?><?php /* Show the page index... "Pages: [1]". */ ?>

		<div class="pagesection top">
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons'], 'direction' => 'right']); ?>

			<?= Utils::$context['menu_separator'] ?>

			<div class="pagelinks floatleft">
				<a href="#bot" class="button"><?= Lang::getTxt('go_down', file: 'General') ?></a>
				<?= Utils::$context['page_index'] ?>

			</div><?php /* Mobile action - moderation buttons (top) */ ?><?php if (!empty(Utils::$context['normal_buttons'])): ?>

		<div class="mobile_buttons floatright">
			<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
			<?= !empty(Utils::$context['mod_buttons']) ? '<a class="button mobile_mod">' . Lang::getTxt('mobile_moderation', file: 'General') . '</a>' : '' ?>

		</div><?php endif; ?>

		</div><?php /* Show the topic information - icon, subject, etc. */ ?>

		<div id="forumposts">
			<form action="<?= Config::$scripturl ?>?action=quickmod2;topic=<?= Utils::$context['current_topic'] ?>.<?= Utils::$context['start'] ?>" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm"><?php
Utils::$context['ignoredMsgs'] = [];
Utils::$context['removableMessageIDs'] = [];
// Get all the messages...
?><?php while ($message = Utils::$context['get_message']()): ?><?php $this->subTemplate('single_post', ['message' => $message]); ?><?php endwhile; ?>

			</form>
		</div><!-- #forumposts --><?php /* Show the page index... "Pages: [1]". */ ?>

		<div class="pagesection">
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons'], 'direction' => 'right']); ?>

			<?= Utils::$context['menu_separator'] ?>

			<div class="pagelinks floatleft">
				<a href="#main_content_section" class="button" id="bot"><?= Lang::getTxt('go_up', file: 'General') ?></a>
				<?= Utils::$context['page_index'] ?>

			</div><?php /* Mobile action - moderation buttons (bottom) */ ?><?php if (!empty(Utils::$context['normal_buttons'])): ?>

		<div class="mobile_buttons floatright">
			<a class="button mobile_act"><?= Lang::getTxt('mobile_action', file: 'General') ?></a>
			<?= !empty(Utils::$context['mod_buttons']) ? '<a class="button mobile_mod">' . Lang::getTxt('mobile_moderation', file: 'General') . '</a>' : '' ?>

		</div><?php endif; ?>

		</div><?php
// Show the lower breadcrumbs.
theme_linktree();
// Moderation buttons
?>

		<div id="moderationbuttons">
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['mod_buttons'], 'direction' => 'bottom', 'strip_options' => ['id' => 'moderationbuttons_strip']]); ?>

		</div><?php /* Show the jumpto box, or actually...let Javascript do it. */ ?>

		<div id="display_jump_to"></div><?php /* Show quickreply */ ?><?php if (Utils::$context['can_reply']): ?><?php $this->subTemplate('quickreply'); ?><?php endif; ?><?php /* User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device. */ ?>

		<div id="mobile_action" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					<?= Lang::getTxt('mobile_action', file: 'General') ?>

					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['normal_buttons']]); ?>

			</div>
		</div><?php /* Show the moderation button & pop (if there is anything to show) */ ?><?php if (!empty(Utils::$context['mod_buttons'])): ?>

		<div id="mobile_moderation" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					<?= Lang::getTxt('mobile_moderation', file: 'General') ?>

					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<div id="moderationbuttons_mobile">
					<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['mod_buttons'], 'direction' => 'bottom', 'strip_options' => ['id' => 'moderationbuttons_strip_mobile']]); ?>

				</div>
			</div>
		</div><?php endif; ?>

		<script>
			window.addEventListener("DOMContentLoaded", function() {<?php if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && Utils::$context['can_remove_post']): ?>

				const strips = [
					{ id: "moderationbuttons", display: "moderationbuttons_strip", varName: "oInTopicModeration" },
					{ id: "moderationbuttons_mobile", display: "moderationbuttons_strip_mobile", varName: "oInTopicModerationMobile" }
				];

				for (let i = 0; i < strips.length; i++) {
					const strip = strips[i];
					window[strip.varName] = new InTopicModeration({
						sCheckboxContainerMask: "in_topic_mod_check_",
						aMessageIds: ["<?= implode('", "', Utils::$context['removableMessageIDs']) ?>"],
						sSessionId: smf_session_id,
						sSessionVar: smf_session_var,
						sButtonStrip: strip.id,
						sButtonStripDisplay: strip.display,
						bUseImageButton: false,
						bCanRemove: <?= Utils::$context['can_remove_post'] ? 'true' : 'false' ?>,
						sRemoveButtonLabel: "<?= Lang::getTxt('quickmod_delete_selected', file: 'General') ?>",
						sRemoveButtonImage: "delete_selected.png",
						sRemoveButtonConfirm: "<?= Lang::getTxt('quickmod_confirm', file: 'General') ?>",
						bCanRestore: <?= Utils::$context['can_restore_msg'] ? 'true' : 'false' ?>,
						sRestoreButtonLabel: "<?= Lang::getTxt('quick_mod_restore', file: 'General') ?>",
						sRestoreButtonImage: "restore_selected.png",
						sRestoreButtonConfirm: "<?= Lang::getTxt('quickmod_confirm', file: 'General') ?>",
						bCanSplit: <?= Utils::$context['can_split'] ? 'true' : 'false' ?>,
						sSplitButtonLabel: "<?= Lang::getTxt('quickmod_split_selected', file: 'General') ?>",
						sSplitButtonImage: "split_selected.png",
						sSplitButtonConfirm: "<?= Lang::getTxt('quickmod_confirm', file: 'General') ?>",
						sFormId: "quickModForm"
					});
				}<?php endif; ?>

				new QuickModify({
					sScriptUrl: smf_scripturl,
					sFormName: 'quickModForm',
					sClassName: 'quick_edit',
					bShowModify: <?= Config::$modSettings['show_modify'] ? 'true' : 'false' ?>,
					iTopicId: <?= Utils::$context['current_topic'] ?>,
					sSaveButtonText: <?= Utils::escapeJavaScript(Lang::getTxt('save', file: 'General')) ?>,
					sCancelButtonText: <?= Utils::escapeJavaScript(Lang::getTxt('modify_cancel', file: 'General')) ?>,
					sEditReasonText: <?= Utils::escapeJavaScript(Lang::getTxt('reason_for_edit', file: 'General')) ?>,
					sErrorBorderStyle: <?= Utils::escapeJavaScript('1px solid red') ?>

				});

				new JumpTo({
					sContainerId: "display_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\"><?= Utils::$context['jump_to']['label'] ?><" + "/label> %dropdown_list%",
					iCurBoardId: <?= Utils::$context['current_board'] ?>,
					iCurBoardChildLevel: <?= Utils::$context['jump_to']['child_level'] ?>,
					sCurBoardName: "<?= Utils::$context['jump_to']['board_name'] ?>",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "<?= Lang::getTxt('go', file: 'General') ?>"
				});

				new IconList({
					sIconIdPrefix: "msg_icon_",
					sScriptUrl: smf_scripturl,
					bShowModify: <?= !empty(Config::$modSettings['show_modify']) ? 'true' : 'false' ?>,
					iBoardId: <?= Utils::$context['current_board'] ?>,
					iTopicId: <?= Utils::$context['current_topic'] ?>,
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sLabelIconList: "<?= Lang::getTxt('message_icon', file: 'General') ?>"
				});<?php if (!empty(Utils::$context['ignoredMsgs'])): ?>

				ignore_toggles([<?= implode(', ', Utils::$context['ignoredMsgs']) ?>], <?= Utils::escapeJavaScript(Lang::getTxt('show_ignore_user_post', file: 'General')) ?>);<?php endif; ?>

			});
		</script>