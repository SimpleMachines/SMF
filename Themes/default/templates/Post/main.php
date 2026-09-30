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

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The main template for the post page.
 */
?><?php /* Start the javascript... and boy is there a lot. */ ?>

		<script><?php /* When using Go Back due to fatal_error, allow the form to be re-submitted with changes. */ ?><?php if (BrowserDetector::isBrowser('is_firefox')): ?>

			window.addEventListener("pageshow", reActivate, false);<?php endif; ?><?php /* Start with message icons - and any missing from this theme. */ ?>

			var icon_urls = <?= json_encode(array_column(Utils::$context['icons'], 'url', 'value'), JSON_UNESCAPED_SLASHES) ?>;<?php /* If we are making a calendar event we want to ensure we show the current days in a month etc... this is done here. */ ?><?php if (Utils::$context['make_event']): ?>

			var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];<?php endif; ?><?php /* End of the javascript, start the form and display the link tree. */ ?>

		</script>
		<form action="<?= Config::$scripturl ?>?action=<?= Utils::$context['destination'] ?>;<?= empty(Utils::$context['current_board']) ? '' : 'board=' . Utils::$context['current_board'] ?>" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="<?= (Utils::$context['becomes_approved'] ? '' : 'alert(\'' . Lang::getTxt('js_post_will_require_approval', file: 'Post') . '\');') ?>submitonce(this);" enctype="multipart/form-data"><?php /* If the user wants to see how their message looks - the preview section is where it's at! */ ?>

			<div id="preview_section"<?= isset(Utils::$context['preview_message']) ? '' : ' style="display: none;"' ?>>
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="preview_subject"><?= empty(Utils::$context['preview_subject']) ? '&nbsp;' : Utils::$context['preview_subject'] ?></span>
					</h3>
				</div>
				<div id="preview_body" class="windowbg">
					<?= empty(Utils::$context['preview_message']) ? '<br>' : Utils::adjustHeadingLevels(Utils::$context['preview_message'], 4) ?>

				</div>
			</div>
			<br><?php if (Utils::$context['make_event'] && (!Utils::$context['event']->new || !empty(Utils::$context['current_board']))): ?>

			<input type="hidden" name="eventid" value="<?= Utils::$context['event']->id ?>">
			<input type="hidden" name="recurrenceid" value="<?= Utils::$context['event']->selected_occurrence->id ?>"><?php endif; ?><?php /* Start the main table. */ ?>

			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div id="post_area">
				<div class="roundframe noup"><?= isset(Utils::$context['current_topic']) ? '
					<input type="hidden" name="topic" value="' . Utils::$context['current_topic'] . '">' : '' ?><?php /* If an error occurred, explain what happened. */ ?>

					<div class="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? 'noticebox' : 'errorbox' ?>"<?= empty(Utils::$context['post_error']) ? ' style="display: none"' : '' ?> id="errors">
						<dl>
							<dt>
								<strong id="error_serious"><?= Lang::getTxt('error_while_submitting', file: 'General') ?></strong>
							</dt>
							<dd class="error" id="error_list">
								<?= empty(Utils::$context['post_error']) ? '' : implode('<br>', Utils::$context['post_error']) ?>

							</dd>
						</dl>
					</div><?php /* If this won't be approved let them know! */ ?><?php if (!Utils::$context['becomes_approved']): ?>

					<div class="noticebox">
						<em><?= Lang::getTxt('wait_for_approval', file: 'General') ?></em>
						<input type="hidden" name="not_approved" value="1">
					</div><?php endif; ?><?php /* If it's locked, show a message to warn the replier. */ ?><?php if (!empty(Utils::$context['locked'])): ?>

					<div class="errorbox">
						<?= Lang::getTxt('topic_locked_no_reply', file: 'Post') ?>

					</div><?php endif; ?><?php if (!empty(Config::$modSettings['drafts_post_enabled'])): ?>

					<div id="draft_section" class="infobox"<?= isset(Utils::$context['draft_saved']) ? '' : ' style="display: none;"' ?>><?= Lang::getTxt('draft_saved', ['url' => Config::$scripturl . '?action=profile;u=' . User::$me->id . ';area=showdrafts'], file: 'Drafts') ?>

						<?= (!empty(Config::$modSettings['drafts_keep_days']) ? ' <strong>' . Lang::getTxt('draft_save_warning', [Config::$modSettings['drafts_keep_days']], file: 'Drafts') . '</strong>' : '') ?>

					</div><?php endif; ?><?php /* The post header... important stuff */ ?><?php $this->subTemplate('post_header'); ?><?php /* Are you posting a calendar event? */ ?><?php if (Utils::$context['make_event']): ?>

					<div id="post_event"><?php $this->subTemplate('event_options'); ?><?php if (!empty(Utils::$context['linked_calendar_events'])): ?><?php $this->subTemplate('linked_events'); ?><?php endif; ?>

					</div><!-- #post_event --><?php endif; ?><?php /* If this is a poll then display all the poll options! */ ?><?php if (Utils::$context['make_poll']): ?>

					<hr class="clear">
					<div id="edit_poll">
						<fieldset id="poll_main">
							<legend><span <?= (isset(Utils::$context['poll_error']['no_question']) ? ' class="error"' : '') ?>><?= Lang::getTxt('poll_question', file: 'General') ?></span></legend>
							<dl class="settings poll_options" id="poll_choices" data-more-txt="<?= Lang::getTxt('poll_add_option', file: 'Post') ?>" data-option-txt="<?= Lang::getTxt('option_number', [999], file: 'Post') ?>">
								<dt><?= Lang::getTxt('poll_question', file: 'General') ?></dt>
								<dd>
									<input type="text" name="question" value="<?= Utils::$context['question'] ?? '' ?>" size="80">
								</dd><?php /* Loop through all the choices and print them out. */ ?><?php foreach (Utils::$context['choices'] as $choice): ?>

								<dt>
									<label for="options-<?= $choice['id'] ?>"><?= Lang::getTxt('option_number', [$choice['number']], file: 'Post') ?></label>
								</dt>
								<dd>
									<input type="text" name="options[<?= $choice['id'] ?>]" id="options-<?= $choice['id'] ?>" value="<?= $choice['label'] ?>" size="80" maxlength="255">
								</dd><?php endforeach; ?><?php /* poll.js puts the "add option" button here, after the list it appends to. */ ?>

							</dl>
						</fieldset>
						<fieldset id="poll_options">
							<legend><?= Lang::getTxt('poll_options', file: 'Post') ?></legend>
							<dl class="settings poll_options">
								<dt>
									<label for="poll_max_votes"><?= Lang::getTxt('poll_max_votes', file: 'Post') ?></label>
								</dt>
								<dd>
									<input type="number" name="poll_max_votes" id="poll_max_votes" min="1" value="<?= Utils::$context['poll_options']['max_votes'] ?>">
								</dd>
								<dt>
									<label for="poll_expire"><?= Lang::getTxt('poll_run', file: 'Post') ?></label><br>
									<em class="smalltext"><?= Lang::getTxt('poll_run_limit', file: 'Post') ?></em>
								</dt>
								<dd>
									<input type="number" name="poll_expire" id="poll_expire" min="0" max="9999" value="<?= intval(Utils::$context['poll_options']['expire'] ?? 0) ?>">
								</dd>
								<dt>
									<label for="poll_change_vote"><?= Lang::getTxt('poll_do_change_vote', file: 'Post') ?></label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_change_vote" name="poll_change_vote"<?= !empty(Utils::$context['poll']['change_vote']) ? ' checked' : '' ?>>
								</dd><?php if (Utils::$context['poll_options']['guest_vote_enabled']): ?>

								<dt>
									<label for="poll_guest_vote"><?= Lang::getTxt('poll_guest_vote', file: 'Post') ?></label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"<?= !empty(Utils::$context['poll_options']['guest_vote']) ? ' checked' : '' ?>>
								</dd><?php endif; ?>

								<dt>
									<?= Lang::getTxt('poll_results_visibility', file: 'Post') ?>

								</dt>
								<dd>
									<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"<?= Utils::$context['poll_options']['hide'] == 0 ? ' checked' : '' ?>> <label for="poll_results_anyone"><?= Lang::getTxt('poll_results_anyone', file: 'Post') ?></label><br>
									<input type="radio" name="poll_hide" id="poll_results_voted" value="1"<?= Utils::$context['poll_options']['hide'] == 1 ? ' checked' : '' ?>> <label for="poll_results_voted"><?= Lang::getTxt('poll_results_voted', file: 'Post') ?></label><br>
									<input type="radio" name="poll_hide" id="poll_results_expire" value="2"<?= Utils::$context['poll_options']['hide'] == 2 ? ' checked' : '' ?><?= empty(Utils::$context['poll_options']['expire']) ? ' disabled' : '' ?>> <label for="poll_results_expire"><?= Lang::getTxt('poll_results_after', file: 'Post') ?></label>
								</dd>
							</dl>
						</fieldset>
					</div><!-- #edit_poll --><?php endif; ?><?php /* Show the actual posting area... */ ?><?php $this->subTemplate('control_richedit', ['editor_id' => 'message', 'smiley_container' => true, 'bbc_container' => true]); ?><?php /* Show attachments. */ ?><?php if (!empty(Utils::$context['current_attachments']) || Utils::$context['can_post_attachment']): ?>

					<div id="post_attachments_area" class="roundframe noup"><?php /* The non-JavaScript UI. */ ?>

							<div id="postAttachment">
								<div class="padding">
									<div>
										<strong><?= Lang::getTxt('attachments', file: 'Post') ?></strong>:<?php if (Utils::$context['can_post_attachment']): ?>

										<input type="file" multiple="multiple" name="attachment[]"><?php endif; ?><?php if (!empty(Config::$modSettings['attachmentSizeLimit'])): ?>

										<input type="hidden" name="MAX_FILE_SIZE" value="<?= Config::$modSettings['attachmentSizeLimit'] * 1024 ?>"><?php endif; ?>

									</div><?php if (!empty(Utils::$context['attachment_restrictions'])): ?>

									<div class="smalltext"><?= Lang::getTxt('attach_restrictions', ['list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['attachment_restrictions'])]) ?></div><?php endif; ?>

									<div class="smalltext">
										<input type="hidden" name="attach_del[]" value="0">
										<?= Lang::getTxt('uncheck_unwatchd_attach', file: 'Post') ?>

									</div>
								</div>
								<div class="attachments"><?php /* If this post already has attachments on it - give information about them. */ ?><?php if (!empty(Utils::$context['current_attachments'])): ?><?php foreach (Utils::$context['current_attachments'] as $attachment): ?>

									<div class="attached">
										<input type="checkbox" id="attachment_<?= $attachment['attachID'] ?>" name="attach_del[]" value="<?= $attachment['attachID'] ?>"<?= empty($attachment['unchecked']) ? ' checked' : '' ?>><?php if (!empty(Config::$modSettings['attachmentShowImages'])): ?><?php if (str_starts_with($attachment['mime_type'], 'image')): ?><?php $src = Config::$scripturl . '?action=dlattach;attach=' . (!empty($attachment['thumb']) ? $attachment['thumb'] : $attachment['attachID']) . ';preview;image'; ?><?php else: ?><?php $src = Theme::$current->settings['images_url'] . '/generic_attach.png'; ?><?php endif; ?>

										<div class="attachments_top">
											<img src="<?= $src ?>" alt="" loading="lazy" class="atc_img">
										</div><?php endif; ?>

										<div class="attachments_bot">
											<span class="name"><?= $attachment['name'] ?></span><?= (empty($attachment['approved']) ? '
											<br>(' . Lang::getTxt('awaiting_approval', file: 'General') . ')' : '') ?>

											<br><?= $attachment['size'] < 1024000 ? Lang::getTxt('size_kilobyte', [round($attachment['size'] / 1024, 2)], file: 'General') : Lang::getTxt('size_megabyte', [round($attachment['size'] / 1024 / 1024, 2)], file: 'General') ?>

										</div>
									</div><?php endforeach; ?><?php endif; ?>

								</div>
							</div><?php if (!empty(Utils::$context['files_in_session_warning'])): ?>

							<div class="smalltext"><em><?= Utils::$context['files_in_session_warning'] ?></em></div><?php endif; ?><?php /* Is the user allowed to post any additional ones? If so give them the boxes to do it! */ ?><?php if (Utils::$context['can_post_attachment']): ?><?php /* Print dropzone UI. */ ?>

						<div id="attachment_upload">
							<div id="drop_zone_ui" class="centertext">
								<div class="attach_drop_zone_label">
									<?= Lang::getTxt(Utils::$context['num_allowed_attachments'] <= count(Utils::$context['current_attachments']) ? 'attach_limit_nag' : 'attach_drop_zone', file: 'Post') ?>

								</div>
							</div>
							<div class="files" id="attachment_previews">
								<div id="au-template">
									<div class="attachment_preview_wrapper">
										<div class="attach-ui roundframe">
											<a data-dz-remove class="main_icons delete floatright cancel"></a>
											<div class="attached_BBC_width_height">
												<div class="attached_BBC_width">
													<label><span><?= Lang::getTxt('attached_insert_width', file: 'Post') ?></span> <input type="number" name="attached_BBC_width" min="0" value="" placeholder="<?= Lang::getTxt('attached_insert_placeholder', file: 'Post') ?>"></label>
												</div>
												<div class="attached_BBC_height">
													<label><span><?= Lang::getTxt('attached_insert_height', file: 'Post') ?></span> <input type="number" name="attached_BBC_height" min="0" value="" placeholder="<?= Lang::getTxt('attached_insert_placeholder', file: 'Post') ?>"></label>
												</div>
											</div>
										</div>
										<div class="attach-preview">
											<img data-dz-thumbnail />
										</div>
										<div class="attachment_info">
											<span class="name" data-dz-name></span>
											<span class="error" data-dz-errormessage></span>
											<span class="size" data-dz-size></span>
											<span class="message" data-dz-message></span>
											<div class="progress_bar" role="progressBar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
												<div class="bar"></div>
											</div>
										</div><!-- .attachment_info -->
									</div>
								</div><!-- #au-template -->
								<div class="attachment_spacer">
									<div class="fallback">
											<input type="file" multiple="multiple" name="attachment[]" id="attachment1" class="fallback"> (<a href="javascript:void(0);" onclick="cleanFileInput('attachment1');"><?= Lang::getTxt('clean_attach', file: 'Post') ?></a>)<?php if (!empty(Config::$modSettings['attachmentSizeLimit'])): ?>

											<input type="hidden" name="MAX_FILE_SIZE" value="<?= Config::$modSettings['attachmentSizeLimit'] * 1024 ?>"><?php endif; ?>

									</div><!-- .fallback -->
								</div>
							</div><!-- #attachment_previews -->
						</div>
						<div id="max_files_progress" class="max_files_progress progress_bar" role="progressBar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
							<div class="bar"></div>
							<div id="max_files_progress_text"></div>
						</div><?php endif; ?>

					</div><?php endif; ?><?php
// If the admin has enabled the hiding of the additional options, fold them
// away behind a disclosure triangle. A details element remembers nothing by
// itself, so the script further down copies its state into the hidden
// additional_options field that gets posted back.
?><?php if (!empty(Config::$modSettings['additional_options_collapsable'])): ?>

					<details id="post_options_toggle"<?= Utils::$context['show_additional_options'] ? ' open' : '' ?>>
						<summary><?= Lang::getTxt('post_additionalopt', file: 'Post') ?></summary><?php endif; ?><?php /* Display the checkboxes for all the standard options - if they are available to the user! */ ?><?php $this->subTemplate('post_options', ['post_options' => Utils::$context['post_options']]); ?><?php if (!empty(Config::$modSettings['additional_options_collapsable'])): ?>

					</details><?php endif; ?><?php /* If the admin enabled the drafts feature, show a draft selection box */ ?><?php if (!empty(Config::$modSettings['drafts_post_enabled']) && !empty(Utils::$context['drafts']) && !empty(Config::$modSettings['drafts_show_saved_enabled']) && !empty(Theme::$current->options['drafts_show_saved_enabled'])): ?>

					<div id="post_draft_options_header" class="title_bar">
						<h4 class="titlebg">
							<strong><a href="#" id="postDraftExpandLink"><?= Lang::getTxt('drafts_show', file: 'Drafts') ?></a></strong>
						</h4>
						<span id="postDraftExpand" class="toggle_up" style="display: none;"></span>
					</div>
					<div id="post_draft_options">
						<dl class="settings">
							<dt><strong><?= Lang::getTxt('subject', file: 'General') ?></strong></dt>
							<dd><strong><?= trim(Lang::getTxt('draft_saved_on', ['date' => ''], file: 'Drafts')) ?></strong></dd><?php foreach (Utils::$context['drafts'] as $draft): ?>

							<dt><?= $draft['link'] ?></dt>
							<dd><?= $draft['poster_time'] ?></dd><?php endforeach; ?>

						</dl>
					</div><?php endif; ?><?php /* Is visual verification enabled? */ ?><?php if (Utils::$context['require_verification']): ?>

					<div class="post_verification">
						<span<?= !empty(Utils::$context['post_error']['need_qr_verification']) ? ' class="error"' : '' ?>>
							<strong><?= Lang::getTxt('verification', file: 'General') ?></strong>
						</span>
						<?php $this->subTemplate('control_verification', ['verify_id' => Utils::$context['visual_verification_id'], 'display_type' => 'all']); ?>

					</div><?php endif; ?><?php /* Finally, the submit buttons. */ ?>

					<span id="post_confirm_buttons">
						<?php $this->subTemplate('control_richedit_buttons', ['editor_id' => 'message']); ?>

					</span>
				</div><!-- .roundframe -->
			</div><!-- #post_area -->
			<br class="clear"><?php /* Assuming this isn't a new topic pass across the last message id. */ ?><?php if (isset(Utils::$context['topic_last_message'])): ?>

			<input type="hidden" name="last_msg" value="<?= Utils::$context['topic_last_message'] ?>"><?php endif; ?>

			<input type="hidden" name="additional_options" id="additional_options" value="<?= Utils::$context['show_additional_options'] ? '1' : '0' ?>">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="seqnum" value="<?= Utils::$context['form_sequence_number'] ?>">
		</form>
		<script><?php
$newPostsHTML = '
		<span id="new_replies"></span>
		<div class="windowbg">
			<div id="msg%PostID%">
			<h5 class="floatleft">
				' . Lang::getTxt('posted_by_member_time', ['member' => '%PosterName%', 'time' => '%PostTime%'], file: 'General') . '&nbsp;&#187; <span class="new_posts" id="image_new_%PostID%">' . Lang::getTxt('new', file: 'General') . '</span>
			</h5>
			<br class="clear">
			<div id="msg_%PostID%_ignored_prompt" class="smalltext" style="display: none;">' . Lang::getTxt('ignoring_user', file: 'General') . '<a href="#" id="msg_%PostID%_ignored_link" style="%IgnoredStyle%">' . Lang::getTxt('show_ignore_user_post', file: 'General') . '</a></div>
			<div class="list_posts smalltext" id="msg_%PostID%_body">%PostBody%</div>';
?><?php if (Utils::$context['can_quote']): ?><?php
$newPostsHTML .= '
			<ul class="quickbuttons sf-js-enabled sf-arrows" id="msg_%PostID%_quote" style="touch-action: pan-y;">
				<li class="post_modify">
					<a href="#postmodify" onclick="return insertQuoteFast(%PostID%);" class="quote_button"><span class="main_icons quote"></span>' . Lang::getTxt('quote', file: 'General') . '</a>
				</li>
			</ul>';
?><?php endif; ?><?php
$newPostsHTML .= '
		</div>';
// The functions used to preview a posts without loading a new page.
?>

			var oPreviewPost = new smc_preview_post({
				sPreviewSectionContainerID: "preview_section",
				sPreviewSubjectContainerID: "preview_subject",
				sPreviewBodyContainerID: "preview_body",
				sErrorsContainerID: "errors",
				sErrorsSeriousContainerID: "error_serious",
				sErrorsListContainerID: "error_list",
				sCaptionContainerID: "caption_%ID%",
				sNewImageContainerID: "image_new_%ID%",
				sPostBoxContainerID: <?= Utils::escapeJavaScript('message') ?>,
				bMakePoll: <?= Utils::$context['make_poll'] ? 'true' : 'false' ?>,
				sTxtPreviewTitle: <?= Utils::escapeJavaScript(Lang::getTxt('preview_title', file: 'General')) ?>,
				sTxtPreviewFetch: <?= Utils::escapeJavaScript(Lang::getTxt('preview_fetch', file: 'General')) ?>,
				sSessionVar: <?= Utils::escapeJavaScript(Utils::$context['session_var']) ?>,
				newPostsTemplate:<?= Utils::escapeJavaScript($newPostsHTML) ?><?php if (!empty(Utils::$context['current_board'])): ?>,
				iCurrentBoard: <?= Utils::$context['current_board'] ?><?php endif; ?>

			});<?php
// Remember whether the additional options were left open, so the next post
// form comes back the same way round.
?><?php if (!empty(Config::$modSettings['additional_options_collapsable'])): ?>

			document.getElementById('post_options_toggle').addEventListener('toggle', function () {
				document.getElementById('additional_options').value = this.open ? '1' : '0';
			});<?php endif; ?><?php /* Code for showing and hiding drafts */ ?><?php if (!empty(Utils::$context['drafts'])): ?>

			var oSwapDraftOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					'post_draft_options',
				],
				aSwapImages: [
					{
						sId: 'postDraftExpand',
						altExpanded: '-',
						altCollapsed: '+'
					}
				],
				aSwapLinks: [
					{
						sId: 'postDraftExpandLink',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('draft_hide', file: 'Drafts')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('drafts_show', file: 'Drafts')) ?>

					}
				]
			});<?php endif; ?>

			var oEditorID = "<?= Utils::$context['post_box_name'] ?>";
		</script><?php /* If the user is replying to a topic show the previous posts. */ ?><?php if (isset(Utils::$context['previous_posts']) && count(Utils::$context['previous_posts']) > 0): ?>

		<div id="recent" class="flow_hidden main_section">
			<div class="cat_bar cat_bar_round">
				<h3 class="catbg"><?= Lang::getTxt('topic_summary', file: 'General') ?></h3>
			</div>
			<span id="new_replies"></span><?php $ignored_posts = []; ?><?php foreach (Utils::$context['previous_posts'] as $post): ?><?php $ignoring = false; ?><?php if (!empty($post['is_ignored'])): ?><?php $ignored_posts[] = $ignoring = $post['id']; ?><?php endif; ?>

			<div class="windowbg">
				<div id="msg<?= $post['id'] ?>">
					<div>
						<h5 class="floatleft">
							<?= Lang::getTxt('posted_by_member_time', ['member' => $post['poster'], 'time' => $post['time']], file: 'General') ?>

						</h5>
					</div><?php if ($ignoring): ?>

					<div id="msg_<?= $post['id'] ?>_ignored_prompt" class="smalltext">
						<?= Lang::getTxt('ignoring_user', file: 'General') ?>

						<a href="#" id="msg_<?= $post['id'] ?>_ignored_link" style="display: none;"><?= Lang::getTxt('show_ignore_user_post', file: 'General') ?></a>
					</div><?php endif; ?>

					<div class="list_posts smalltext" id="msg_<?= $post['id'] ?>_body" data-msgid="<?= $post['id'] ?>"><?= Utils::adjustHeadingLevels($post['message'], 5) ?></div><?php if (Utils::$context['can_quote']): ?>

					<ul class="quickbuttons" id="msg_<?= $post['id'] ?>_quote">
						<li style="display:none;" id="quoteSelected_<?= $post['id'] ?>" data-msgid="<?= $post['id'] ?>"><a href="javascript:void(0)"><span class="main_icons quote_selected"></span><?= Lang::getTxt('quote_selected_action', file: 'General') ?></a></li>
						<li class="post_modify"><a href="#postmodify" onclick="return insertQuoteFast(<?= $post['id'] ?>);"><span class="main_icons quote"></span><?= Lang::getTxt('quote', file: 'General') ?></a></li>
					</ul><?php endif; ?>

				</div><!-- #msg[id] -->
			</div><!-- .windowbg --><?php endforeach; ?>

		</div><!-- #recent -->
		<script>
			var aIgnoreToggles = new Array();<?php foreach ($ignored_posts as $post_id): ?>

			aIgnoreToggles[<?= $post_id ?>] = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					'msg_<?= $post_id ?>_body',
					'msg_<?= $post_id ?>_quote',
				],
				aSwapLinks: [
					{
						sId: 'msg_<?= $post_id ?>_ignored_link',
						msgExpanded: '',
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show_ignore_user_post', file: 'General')) ?>

					}
				]
			});<?php endforeach; ?>

			function insertQuoteFast(messageid)
			{
				var e = document.getElementById("<?= Utils::$context['post_box_name'] ?>");
				sceditor.instance(e).insertQuoteFast(messageid);

				return true;
			}
			function onReceiveOpener(text)
			{
				var e = document.getElementById("<?= Utils::$context['post_box_name'] ?>");
				sceditor.instance(e).insert(text);
			}
		</script><?php endif; ?>
