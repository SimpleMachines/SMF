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
 * The template for displaying the quick reply box.
 */
?>

		<a id="quickreply_anchor"></a>
		<div class="tborder" id="quickreply">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('quick_reply', file: 'General') ?>

				</h3>
			</div>
			<div id="quickreply_options">
				<div class="roundframe"><?php /* Is the topic locked? */ ?><?php if (Utils::$context['is_locked']): ?>

					<p class="alert smalltext"><?= Lang::getTxt('quick_reply_warning', file: 'General') ?></p><?php endif; ?><?php /* Show a warning if the topic is old */ ?><?php if (!empty(Utils::$context['oldTopicError'])): ?>

					<p class="alert smalltext"><?= Lang::getTxt('error_old_topic', [Config::$modSettings['oldTopicDays']], file: 'General') ?></p><?php endif; ?><?php /* Does the post need approval? */ ?><?php if (!Utils::$context['can_reply_approved']): ?>

					<p><em><?= Lang::getTxt('wait_for_approval', file: 'General') ?></em></p><?php endif; ?>

					<form action="<?= Config::$scripturl ?>?board=<?= Utils::$context['current_board'] ?>;action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);">
						<input type="hidden" name="topic" value="<?= Utils::$context['current_topic'] ?>">
						<input type="hidden" name="subject" value="<?= Utils::$context['response_prefix'] ?><?= Utils::$context['subject'] ?>">
						<input type="hidden" name="icon" value="xx">
						<input type="hidden" name="from_qr" value="1">
						<input type="hidden" name="notify" value="<?= Utils::$context['is_marked_notify'] || !empty(Theme::$current->options['auto_notify']) ? '1' : '0' ?>">
						<input type="hidden" name="not_approved" value="<?= !Utils::$context['can_reply_approved'] ?>">
						<input type="hidden" name="goback" value="<?= empty(Theme::$current->options['return_to_post']) ? '0' : '1' ?>">
						<input type="hidden" name="last_msg" value="<?= Utils::$context['topic_last_message'] ?>">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="seqnum" value="<?= Utils::$context['form_sequence_number'] ?>"><?php /* Guests just need more. */ ?><?php if (User::$me->is_guest): ?>

						<dl id="post_header">
							<dt>
								<?= Lang::getTxt('name', file: 'General') ?>

							</dt>
							<dd>
								<input type="text" name="guestname" size="25" value="<?= Utils::$context['name'] ?>" required>
							</dd><?php if (empty(Config::$modSettings['guest_post_no_email'])): ?>

							<dt>
								<?= Lang::getTxt('email', file: 'General') ?>

							</dt>
							<dd>
								<input type="email" name="email" size="25" value="<?= Utils::$context['email'] ?>" required>
							</dd><?php endif; ?>

						</dl><?php endif; ?><?php $this->subTemplate('control_richedit', ['editor_id' => 'quickReply', 'smiley_container' => true, 'bbc_container' => true]); ?><?php /* Is visual verification enabled? */ ?><?php if (Utils::$context['require_verification']): ?>

						<div class="post_verification">
							<strong><?= Lang::getTxt('verification', file: 'General') ?></strong>
							<?php $this->subTemplate('control_verification', ['verify_id' => Utils::$context['visual_verification_id'], 'display_type' => 'all']); ?>

						</div><?php endif; ?><?php /* Finally, the submit buttons. */ ?>

						<span id="post_confirm_buttons">
							<?php $this->subTemplate('control_richedit_buttons', ['editor_id' => 'quickReply']); ?>

						</span>
					</form>
				</div><!-- .roundframe -->
			</div><!-- #quickreply_options -->
		</div><!-- #quickreply -->
		<br class="clear"><?php if (Utils::$context['show_spellchecking']): ?>

		<form action="<?= Config::$scripturl ?>?action=spellcheck" method="post" accept-charset="UTF-8" name="spell_form" id="spell_form" target="spellWindow">
			<input type="hidden" name="spellstring" value="">
		</form><?php endif; ?>

		<script>
			window.addEventListener("DOMContentLoaded", function() {
				new QuickReply({
					bDefaultCollapsed: false,
					iTopicId: <?= Utils::$context['current_topic'] ?>,
					iStart: <?= Utils::$context['start'] ?>,
					sScriptUrl: smf_scripturl,
					sImagesUrl: smf_images_url,
					sContainerId: "quickreply_options",
					sImageId: "quickReplyExpand",
					sClassCollapsed: "toggle_up",
					sClassExpanded: "toggle_down",
					sJumpAnchor: "quickreply_anchor",
					bIsFull: true
				});
			});
			var oEditorID = "<?= Utils::$context['post_box_name'] ?>";
		</script>