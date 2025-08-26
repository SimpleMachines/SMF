<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\User;

/**
 * The main template for the post page.
 */
function template_main()
{
	// Start the javascript... and boy is there a lot.
	echo '
		<script>';

	// When using Go Back due to fatal_error, allow the form to be re-submitted with changes.
	if (BrowserDetector::isBrowser('is_firefox'))
		echo '
			window.addEventListener("pageshow", reActivate, false);';

	// Start with message icons - and any missing from this theme.
	echo '
			var icon_urls = {';

	foreach (Utils::$context['icons'] as $icon)
		echo '
				\'', $icon['value'], '\': \'', $icon['url'], '\'', $icon['is_last'] ? '' : ',';

	echo '
			};';

	// If this is a poll - use some javascript to ensure the user doesn't create a poll with illegal option combinations.
	if (Utils::$context['make_poll'])
		echo '
			var pollOptionNum = 0, pollTabIndex;
			var pollOptionId = ', Utils::$context['last_choice_id'], ';
			function addPollOption()
			{
				if (pollOptionNum == 0)
				{
					for (var i = 0, n = document.forms.postmodify.elements.length; i < n; i++)
						if (document.forms.postmodify.elements[i].id.substr(0, 8) == \'options-\')
						{
							pollOptionNum++;
							pollTabIndex = document.forms.postmodify.elements[i].tabIndex;
						}
				}
				pollOptionNum++
				pollOptionId++

				setOuterHTML(document.getElementById("pollMoreOptions"), \'<dt><label for="options-\' + pollOptionId + \'" >', strtr(Lang::getTxt('option_number', [999], file: 'Post'), ['999' => '\' + pollOptionNum + \'']), '</label></dt><dd><input type="text" name="options[\' + (pollOptionId) + \']" id="options-\' + (pollOptionId) + \'" value="" size="80" maxlength="255"></dd><p id="pollMoreOptions"></p>\');
			}';

	// If we are making a calendar event we want to ensure we show the current days in a month etc... this is done here.
	if (Utils::$context['make_event'])
		echo '
			var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];';

	// End of the javascript, start the form and display the link tree.
	echo '
		</script>
		<form action="', Config::$scripturl, '?action=', Utils::$context['destination'], ';', empty(Utils::$context['current_board']) ? '' : 'board=' . Utils::$context['current_board'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="', (Utils::$context['becomes_approved'] ? '' : 'alert(\'' . Lang::getTxt('js_post_will_require_approval', file: 'Post') . '\');'), 'submitonce(this);" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
			<div id="preview_section"', isset(Utils::$context['preview_message']) ? '' : ' style="display: none;"', '>
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="preview_subject">', empty(Utils::$context['preview_subject']) ? '&nbsp;' : Utils::$context['preview_subject'], '</span>
					</h3>
				</div>
				<div id="preview_body" class="windowbg">
					', empty(Utils::$context['preview_message']) ? '<br>' : Utils::adjustHeadingLevels(Utils::$context['preview_message'], 4), '
				</div>
			</div>
			<br>';

	if (Utils::$context['make_event'] && (!Utils::$context['event']->new || !empty(Utils::$context['current_board'])))
		echo '
			<input type="hidden" name="eventid" value="', Utils::$context['event']->id, '">
			<input type="hidden" name="recurrenceid" value="', Utils::$context['event']->selected_occurrence->id, '">';

	// Start the main table.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div id="post_area">
				<div class="roundframe noup">', isset(Utils::$context['current_topic']) ? '
					<input type="hidden" name="topic" value="' . Utils::$context['current_topic'] . '">' : '';

	// If an error occurred, explain what happened.
	echo '
					<div class="', empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? 'noticebox' : 'errorbox', '"', empty(Utils::$context['post_error']) ? ' style="display: none"' : '', ' id="errors">
						<dl>
							<dt>
								<strong id="error_serious">', Lang::getTxt('error_while_submitting', file: 'General'), '</strong>
							</dt>
							<dd class="error" id="error_list">
								', empty(Utils::$context['post_error']) ? '' : implode('<br>', Utils::$context['post_error']), '
							</dd>
						</dl>
					</div>';

	// If this won't be approved let them know!
	if (!Utils::$context['becomes_approved'])
		echo '
					<div class="noticebox">
						<em>', Lang::getTxt('wait_for_approval', file: 'General'), '</em>
						<input type="hidden" name="not_approved" value="1">
					</div>';

	// If it's locked, show a message to warn the replier.
	if (!empty(Utils::$context['locked']))
		echo '
					<div class="errorbox">
						', Lang::getTxt('topic_locked_no_reply', file: 'Post'), '
					</div>';

	if (!empty(Config::$modSettings['drafts_post_enabled']))
		echo '
					<div id="draft_section" class="infobox"', isset(Utils::$context['draft_saved']) ? '' : ' style="display: none;"', '>',
						Lang::getTxt('draft_saved', ['url' => Config::$scripturl . '?action=profile;u=' . User::$me->id . ';area=showdrafts'], file: 'Drafts'), '
						', (!empty(Config::$modSettings['drafts_keep_days']) ? ' <strong>' . Lang::getTxt('draft_save_warning', [Config::$modSettings['drafts_keep_days']], file: 'Drafts') . '</strong>' : ''), '
					</div>';

	// The post header... important stuff
	template_post_header();

	// Are you posting a calendar event?
	if (Utils::$context['make_event'])
	{
		echo '
					<div id="post_event">';

		template_event_options();

		if (!empty(Utils::$context['linked_calendar_events'])) {
			template_linked_events();
		}

		echo '
					</div><!-- #post_event -->';
	}

	// If this is a poll then display all the poll options!
	if (Utils::$context['make_poll'])
	{
		echo '
					<hr class="clear">
					<div id="edit_poll">
						<fieldset id="poll_main">
							<legend><span ', (isset(Utils::$context['poll_error']['no_question']) ? ' class="error"' : ''), '>', Lang::getTxt('poll_question', file: 'General'), '</span></legend>
							<dl class="settings poll_options">
								<dt>', Lang::getTxt('poll_question', file: 'General'), '</dt>
								<dd>
									<input type="text" name="question" value="', isset(Utils::$context['question']) ? Utils::$context['question'] : '', '" tabindex="', Utils::$context['tabindex']++, '" size="80">
								</dd>';

		// Loop through all the choices and print them out.
		foreach (Utils::$context['choices'] as $choice)
			echo '
								<dt>
									<label for="options-', $choice['id'], '">', Lang::getTxt('option_number', [$choice['number']], file: 'Post'), '</label>
								</dt>
								<dd>
									<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', Utils::$context['tabindex']++, '" size="80" maxlength="255">
								</dd>';

		echo '
								<p id="pollMoreOptions"></p>
							</dl>
							<strong><a href="javascript:addPollOption(); void(0);">(', Lang::getTxt('poll_add_option', file: 'Post'), ')</a></strong>
						</fieldset>
						<fieldset id="poll_options">
							<legend>', Lang::getTxt('poll_options', file: 'Post'), '</legend>
							<dl class="settings poll_options">
								<dt>
									<label for="poll_max_votes">', Lang::getTxt('poll_max_votes', file: 'Post'), '</label>
								</dt>
								<dd>
									<input type="number" name="poll_max_votes" id="poll_max_votes" min="1" value="', Utils::$context['poll_options']['max_votes'], '">
								</dd>
								<dt>
									<label for="poll_expire">', Lang::getTxt('poll_run', file: 'Post'), '</label><br>
									<em class="smalltext">', Lang::getTxt('poll_run_limit', file: 'Post'), '</em>
								</dt>
								<dd>
									<input type="number" name="poll_expire" id="poll_expire" min="0" max="9999" value="', intval(Utils::$context['poll_options']['expire'] ?? 0), '" onchange="pollOptions();">
								</dd>
								<dt>
									<label for="poll_change_vote">', Lang::getTxt('poll_do_change_vote', file: 'Post'), '</label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty(Utils::$context['poll']['change_vote']) ? ' checked' : '', '>
								</dd>';

		if (Utils::$context['poll_options']['guest_vote_enabled'])
			echo '
								<dt>
									<label for="poll_guest_vote">', Lang::getTxt('poll_guest_vote', file: 'Post'), '</label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty(Utils::$context['poll_options']['guest_vote']) ? ' checked' : '', '>
								</dd>';

		echo '
								<dt>
									', Lang::getTxt('poll_results_visibility', file: 'Post'), '
								</dt>
								<dd>
									<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', Utils::$context['poll_options']['hide'] == 0 ? ' checked' : '', '> <label for="poll_results_anyone">', Lang::getTxt('poll_results_anyone', file: 'Post'), '</label><br>
									<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', Utils::$context['poll_options']['hide'] == 1 ? ' checked' : '', '> <label for="poll_results_voted">', Lang::getTxt('poll_results_voted', file: 'Post'), '</label><br>
									<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', Utils::$context['poll_options']['hide'] == 2 ? ' checked' : '', empty(Utils::$context['poll_options']['expire']) ? ' disabled' : '', '> <label for="poll_results_expire">', Lang::getTxt('poll_results_after', file: 'Post'), '</label>
								</dd>
							</dl>
						</fieldset>
					</div><!-- #edit_poll -->';
	}

	// Show the actual posting area...
	echo '
					', template_control_richedit(Utils::$context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// Show attachments.
	if (!empty(Utils::$context['current_attachments']) || Utils::$context['can_post_attachment'])
	{
		echo '
					<div id="post_attachments_area" class="roundframe noup">';

		// The non-JavaScript UI.
		echo '
							<div id="postAttachment">
								<div class="padding">
									<div>
										<strong>', Lang::getTxt('attachments', file: 'Post'), '</strong>:';

		if (Utils::$context['can_post_attachment'])
			echo '
										<input type="file" multiple="multiple" name="attachment[]" id="attachment1">';

		if (!empty(Config::$modSettings['attachmentSizeLimit']))
			echo '
										<input type="hidden" name="MAX_FILE_SIZE" value="' . Config::$modSettings['attachmentSizeLimit'] * 1024 . '">';

		echo '
									</div>';

		if (!empty(Utils::$context['attachment_restrictions']))
			echo '
									<div class="smalltext">', Lang::getTxt('attach_restrictions', ['list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['attachment_restrictions'])]), '</div>';

		echo '
									<div class="smalltext">
										<input type="hidden" name="attach_del[]" value="0">
										', Lang::getTxt('uncheck_unwatchd_attach', file: 'Post'), '
									</div>
								</div>
								<div class="attachments">';

		// If this post already has attachments on it - give information about them.
		if (!empty(Utils::$context['current_attachments']))
		{
			foreach (Utils::$context['current_attachments'] as $attachment)
			{
				echo '
									<div class="attached">
										<input type="checkbox" id="attachment_', $attachment['attachID'], '" name="attach_del[]" value="', $attachment['attachID'], '"', empty($attachment['unchecked']) ? ' checked' : '', '>';

				if (!empty(Config::$modSettings['attachmentShowImages']))
				{
					if (str_starts_with($attachment['mime_type'], 'image'))
						$src = Config::$scripturl . '?action=dlattach;attach=' . (!empty($attachment['thumb']) ? $attachment['thumb'] : $attachment['attachID']) . ';preview;image';
					else
						$src = Theme::$current->settings['images_url'] . '/generic_attach.png';

					echo '
										<div class="attachments_top">
											<img src="', $src, '" alt="" loading="lazy" class="atc_img">
										</div>';
				}

				echo '
										<div class="attachments_bot">
											<span class="name">' . $attachment['name'] . '</span>', (empty($attachment['approved']) ? '
											<br>(' . Lang::getTxt('awaiting_approval', file: 'General') . ')' : ''), '
											<br>', $attachment['size'] < 1024000 ? Lang::getTxt('size_kilobyte', [round($attachment['size'] / 1024, 2)], file: 'General') : Lang::getTxt('size_megabyte', [round($attachment['size'] / 1024 / 1024, 2)], file: 'General'), '
										</div>
									</div>';
			}
		}

		echo '
								</div>
							</div>';

		if (!empty(Utils::$context['files_in_session_warning']))
			echo '
							<div class="smalltext"><em>', Utils::$context['files_in_session_warning'], '</em></div>';

		// Is the user allowed to post any additional ones? If so give them the boxes to do it!
		if (Utils::$context['can_post_attachment'])
		{
			// Print dropzone UI.
			echo '
						<div id="attachment_upload">
							<div id="drop_zone_ui" class="centertext">
								<div class="attach_drop_zone_label">
									', Lang::getTxt(Utils::$context['num_allowed_attachments'] <= count(Utils::$context['current_attachments']) ? 'attach_limit_nag' : 'attach_drop_zone', file: 'Post'), '
								</div>
							</div>
							<div class="files" id="attachment_previews">
								<div id="au-template">
									<div class="attachment_preview_wrapper">
										<div class="attach-ui roundframe">
											<a data-dz-remove class="main_icons delete floatright cancel"></a>
											<div class="attached_BBC_width_height">
												<div class="attached_BBC_width">
													<label for="attached_BBC_width">', Lang::getTxt('attached_insert_width', file: 'Post'), '</label>
													<input type="number" name="attached_BBC_width" min="0" value="" placeholder="', Lang::getTxt('attached_insert_placeholder', file: 'Post'), '">
												</div>
												<div class="attached_BBC_height">
													<label for="attached_BBC_height">', Lang::getTxt('attached_insert_height', file: 'Post'), '</label>
													<input type="number" name="attached_BBC_height" min="0" value="" placeholder="', Lang::getTxt('attached_insert_placeholder', file: 'Post'), '">
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
											<input type="file" multiple="multiple" name="attachment[]" id="attachment1" class="fallback"> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment1\');">', Lang::getTxt('clean_attach', file: 'Post'), '</a>)';

			if (!empty(Config::$modSettings['attachmentSizeLimit']))
				echo '
											<input type="hidden" name="MAX_FILE_SIZE" value="' . Config::$modSettings['attachmentSizeLimit'] * 1024 . '">';

			echo '
									</div><!-- .fallback -->
								</div>
							</div><!-- #attachment_previews -->
						</div>
						<div id="max_files_progress" class="max_files_progress progress_bar" role="progressBar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
							<div class="bar"></div>
							<div id="max_files_progress_text"></div>
						</div>';
		}

		echo '
					</div>';
	}

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty(Config::$modSettings['additional_options_collapsable']))
		echo '
					<div id="post_additional_options_header">
						<strong><a href="#" id="postMoreExpandLink"> ', Lang::getTxt('post_additionalopt', file: 'Post'), '</a></strong>
					</div>';

	echo '
					<div id="post_additional_options">';

	// Display the checkboxes for all the standard options - if they are available to the user!
	echo '
						<div id="post_settings" class="smalltext">
							<ul class="post_options">
								', Utils::$context['can_notify'] ? '<li><input type="hidden" name="notify" value="0"><label for="check_notify"><input type="checkbox" name="notify" id="check_notify"' . (Utils::$context['notify'] || !empty(Theme::$current->options['auto_notify']) || Utils::$context['auto_notify'] ? ' checked' : '') . ' value="1"> ' . Lang::getTxt('notify_replies', file: 'Post') . '</label></li>' : '', '
								', Utils::$context['can_lock'] ? '<li><input type="hidden" name="already_locked" value="' . Utils::$context['already_locked'] . '"><input type="hidden" name="lock" value="0"><label for="check_lock"><input type="checkbox" name="lock" id="check_lock"' . (Utils::$context['locked'] ? ' checked' : '') . ' value="1"> ' . Lang::getTxt('lock_topic', file: 'Post') . '</label></li>' : '', '
								<li><label for="check_back"><input type="checkbox" name="goback" id="check_back"' . (Utils::$context['back_to_topic'] || !empty(Theme::$current->options['return_to_post']) ? ' checked' : '') . ' value="1"> ' . Lang::getTxt('back_to_topic', file: 'Post') . '</label></li>
								', Utils::$context['can_sticky'] ? '<li><input type="hidden" name="already_sticky" value="' . Utils::$context['already_sticky'] . '"><input type="hidden" name="sticky" value="0"><label for="check_sticky"><input type="checkbox" name="sticky" id="check_sticky"' . (Utils::$context['sticky'] ? ' checked' : '') . ' value="1"> ' . Lang::getTxt('sticky_after_posting', file: 'Post') . '</label></li>' : '', '
								<li><label for="check_smileys"><input type="checkbox" name="ns" id="check_smileys"', Utils::$context['use_smileys'] ? '' : ' checked', ' value="NS"> ', Lang::getTxt('dont_use_smileys', file: 'Post'), '</label></li>', '
								', Utils::$context['can_move'] ? '<li><input type="hidden" name="move" value="0"><label for="check_move"><input type="checkbox" name="move" id="check_move" value="1"' . (!empty(Utils::$context['move']) ? ' checked" ' : '') . '> ' . Lang::getTxt('move_after_posting', file: 'Post') . '</label></li>' : '', '
								', Utils::$context['can_announce'] && Utils::$context['is_first_post'] ? '<li><label for="check_announce"><input type="checkbox" name="announce_topic" id="check_announce" value="1"' . (!empty(Utils::$context['announce']) ? ' checked' : '') . '> ' . Lang::getTxt('announce_topic', file: 'Post') . '</label></li>' : '', '
								', Utils::$context['show_approval'] ? '<li><label for="approve"><input type="checkbox" name="approve" id="approve" value="2"' . (Utils::$context['show_approval'] === 2 ? ' checked' : '') . '> ' . Lang::getTxt('approve_this_post', file: 'Post') . '</label></li>' : '', '
							</ul>
						</div><!-- #post_settings -->';

	echo '
					</div><!-- #post_additional_options -->';

	// If the admin enabled the drafts feature, show a draft selection box
	if (!empty(Config::$modSettings['drafts_post_enabled']) && !empty(Utils::$context['drafts']) && !empty(Config::$modSettings['drafts_show_saved_enabled']) && !empty(Theme::$current->options['drafts_show_saved_enabled']))
	{
		echo '
					<div id="post_draft_options_header" class="title_bar">
						<h4 class="titlebg">
							<span id="postDraftExpand" class="toggle_up floatright" style="display: none;"></span> <strong><a href="#" id="postDraftExpandLink">', Lang::getTxt('drafts_show', file: 'Drafts'), '</a></strong>
						</h4>
					</div>
					<div id="post_draft_options">
						<dl class="settings">
							<dt><strong>', Lang::getTxt('subject', file: 'General'), '</strong></dt>
							<dd><strong>', trim(Lang::getTxt('draft_saved_on', ['date' => ''], file: 'Drafts')), '</strong></dd>';

		foreach (Utils::$context['drafts'] as $draft)
			echo '
							<dt>', $draft['link'], '</dt>
							<dd>', $draft['poster_time'], '</dd>';
		echo '
						</dl>
					</div>';
	}

	// Is visual verification enabled?
	if (Utils::$context['require_verification'])
		echo '
					<div class="post_verification">
						<span', !empty(Utils::$context['post_error']['need_qr_verification']) ? ' class="error"' : '', '>
							<strong>', Lang::getTxt('verification', file: 'General'), '</strong>
						</span>
						', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
					</div>';

	// Finally, the submit buttons.
	echo '
					<span id="post_confirm_buttons">
						', template_control_richedit_buttons(Utils::$context['post_box_name']);

	echo '
					</span>
				</div><!-- .roundframe -->
			</div><!-- #post_area -->
			<br class="clear">';

	// Assuming this isn't a new topic pass across the last message id.
	if (isset(Utils::$context['topic_last_message']))
		echo '
			<input type="hidden" name="last_msg" value="', Utils::$context['topic_last_message'], '">';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', Utils::$context['show_additional_options'] ? '1' : '0', '">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">
		</form>';

	echo '
		<script>';

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

	if (Utils::$context['can_quote'])
		$newPostsHTML .= '
			<ul class="quickbuttons sf-js-enabled sf-arrows" id="msg_%PostID%_quote" style="touch-action: pan-y;">
				<li id="post_modify">
					<a href="#postmodify" onclick="return insertQuoteFast(%PostID%);" class="quote_button"><span class="main_icons quote"></span>' . Lang::getTxt('quote', file: 'General') . '</a>
				</li>
			</ul>';

	$newPostsHTML .= '
		</div>';

	// The functions used to preview a posts without loading a new page.
	echo '
			var oPreviewPost = new smc_preview_post({
				sPreviewSectionContainerID: "preview_section",
				sPreviewSubjectContainerID: "preview_subject",
				sPreviewBodyContainerID: "preview_body",
				sErrorsContainerID: "errors",
				sErrorsSeriousContainerID: "error_serious",
				sErrorsListContainerID: "error_list",
				sCaptionContainerID: "caption_%ID%",
				sNewImageContainerID: "image_new_%ID%",
				sPostBoxContainerID: ', Utils::escapeJavaScript(Utils::$context['post_box_name']), ',
				bMakePoll: ', Utils::$context['make_poll'] ? 'true' : 'false', ',
				sTxtPreviewTitle: ', Utils::escapeJavaScript(Lang::getTxt('preview_title', file: 'General')), ',
				sTxtPreviewFetch: ', Utils::escapeJavaScript(Lang::getTxt('preview_fetch', file: 'General')), ',
				sSessionVar: ', Utils::escapeJavaScript(Utils::$context['session_var']), ',
				newPostsTemplate:', Utils::escapeJavaScript($newPostsHTML);

	if (!empty(Utils::$context['current_board']))
		echo ',
				iCurrentBoard: ', Utils::$context['current_board'], '';

	echo '
			});';

	// Code for showing and hiding additional options.
	if (!empty(Config::$modSettings['additional_options_collapsable']))
		echo '
			var oSwapAdditionalOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', Utils::$context['show_additional_options'] ? 'false' : 'true', ',
				funcOnBeforeCollapse: function () {
					document.getElementById(\'additional_options\').value = \'0\';
				},
				funcOnBeforeExpand: function () {
					document.getElementById(\'additional_options\').value = \'1\';
				},
				aSwappableContainers: [
					\'post_additional_options\',
				],
				aSwapImages: [
					{
						sId: \'postMoreExpandLink\',
						altExpanded: \'-\',
						altCollapsed: \'+\'
					}
				],
				aSwapLinks: [
					{
						sId: \'postMoreExpandLink\',
						msgExpanded: ', Utils::escapeJavaScript(Lang::getTxt('post_additionalopt', file: 'Post')), ',
						msgCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('post_additionalopt', file: 'Post')), '
					}
				]
			});';

	// Code for showing and hiding drafts
	if (!empty(Utils::$context['drafts']))
		echo '
			var oSwapDraftOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'post_draft_options\',
				],
				aSwapImages: [
					{
						sId: \'postDraftExpand\',
						altExpanded: \'-\',
						altCollapsed: \'+\'
					}
				],
				aSwapLinks: [
					{
						sId: \'postDraftExpandLink\',
						msgExpanded: ', Utils::escapeJavaScript(Lang::getTxt('draft_hide', file: 'Drafts')), ',
						msgCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('drafts_show', file: 'Drafts')), '
					}
				]
			});';

	echo '
			var oEditorID = "', Utils::$context['post_box_name'], '";
			var oEditorObject = oEditorHandle_', Utils::$context['post_box_name'], ';
		</script>';

	// If the user is replying to a topic show the previous posts.
	if (isset(Utils::$context['previous_posts']) && count(Utils::$context['previous_posts']) > 0)
	{
		echo '
		<div id="recent" class="flow_hidden main_section">
			<div class="cat_bar cat_bar_round">
				<h3 class="catbg">', Lang::getTxt('topic_summary', file: 'General'), '</h3>
			</div>
			<span id="new_replies"></span>';

		$ignored_posts = array();
		foreach (Utils::$context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];

			echo '
			<div class="windowbg">
				<div id="msg', $post['id'], '">
					<div>
						<h5 class="floatleft">
							', Lang::getTxt('posted_by_member_time', ['member' => $post['poster'], 'time' => $post['time']], file: 'General'), '
						</h5>
					</div>';

			if ($ignoring)
				echo '
					<div id="msg_', $post['id'], '_ignored_prompt" class="smalltext">
						', Lang::getTxt('ignoring_user', file: 'General'), '
						<a href="#" id="msg_', $post['id'], '_ignored_link" style="display: none;">', Lang::getTxt('show_ignore_user_post', file: 'General'), '</a>
					</div>';

			echo '
					<div class="list_posts smalltext" id="msg_', $post['id'], '_body" data-msgid="', $post['id'], '">', Utils::adjustHeadingLevels($post['message'], 5), '</div>';

			if (Utils::$context['can_quote'])
				echo '
					<ul class="quickbuttons" id="msg_', $post['id'], '_quote">
						<li style="display:none;" id="quoteSelected_', $post['id'], '" data-msgid="', $post['id'], '"><a href="javascript:void(0)"><span class="main_icons quote_selected"></span>', Lang::getTxt('quote_selected_action', file: 'General'), '</a></li>
						<li id="post_modify"><a href="#postmodify" onclick="return insertQuoteFast(', $post['id'], ');"><span class="main_icons quote"></span>', Lang::getTxt('quote', file: 'General'), '</a></li>
					</ul>';

			echo '
				</div><!-- #msg[id] -->
			</div><!-- .windowbg -->';
		}

		echo '
		</div><!-- #recent -->
		<script>
			var aIgnoreToggles = new Array();';

		foreach ($ignored_posts as $post_id)
		{
			echo '
			aIgnoreToggles[', $post_id, '] = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'msg_', $post_id, '_body\',
					\'msg_', $post_id, '_quote\',
				],
				aSwapLinks: [
					{
						sId: \'msg_', $post_id, '_ignored_link\',
						msgExpanded: \'\',
						msgCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('show_ignore_user_post', file: 'General')), '
					}
				]
			});';
		}

		echo '
			function insertQuoteFast(messageid)
			{
				var e = document.getElementById("', Utils::$context['post_box_name'], '");
				sceditor.instance(e).insertQuoteFast(messageid);

				return true;
			}
			function onReceiveOpener(text)
			{
				var e = document.getElementById("', Utils::$context['post_box_name'], '");
				sceditor.instance(e).insert(text);
			}
		</script>';
	}
}

/**
 * The template for the spellchecker.
 */
function template_spellcheck()
{
	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', Lang::getTxt('spell_check', file: 'General'), '</title>
		<link rel="stylesheet" href="', Theme::$current->settings['theme_url'], '/css/index', Utils::$context['theme_variant'], '.css', Utils::$context['browser_cache'], '">
		<style>
			body, td {
				font-size: small;
				margin: 0;
				background: #f0f0f0;
				color: #000;
				padding: 10px;
			}
			.highlight {
				color: red;
				font-weight: bold;
			}
			#spellview {
				border-style: outset;
				border: 1px solid black;
				padding: 5px;
				width: 95%;
				height: 314px;
				overflow: auto;
				background: #ffffff;
			}';

	// As you may expect - we need a lot of javascript for this... load it from the separate files.
	echo '
		</style>
		<script>
			var spell_formname = window.opener.spell_formname;
			var spell_fieldname = window.opener.spell_fieldname;
		</script>
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/spellcheck.js', Utils::$context['browser_cache'], '"></script>
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
		<script>
			', Utils::$context['spell_js'], '
		</script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="UTF-8" name="spellingForm" id="spellingForm" onsubmit="return false;">
			<div id="spellview">&nbsp;</div>
			<table width="100%">
				<tr class="windowbg">
					<td style="width: 50%; vertical-align: top">
						', Lang::getTxt('spellcheck_change_to', file: 'Post'), '<br>
						<input type="text" name="changeto" style="width: 98%;">
					</td>
					<td style="width: 50%">
						', Lang::getTxt('spellcheck_suggest', file: 'Post'), '<br>
						<select name="suggestions" style="width: 98%;" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
						</select>
					</td>
				</tr>
			</table>
			<div class="righttext" style="padding: 4px;">
				<input type="button" name="change" value="', Lang::getTxt('spellcheck_change', file: 'Post'), '" onclick="replaceWord();" class="button">
				<input type="button" name="changeall" value="', Lang::getTxt('spellcheck_change_all', file: 'Post'), '" onclick="replaceAll();" class="button">
				<input type="button" name="ignore" value="', Lang::getTxt('spellcheck_ignore', file: 'Post'), '" onclick="nextWord(false);" class="button">
				<input type="button" name="ignoreall" value="', Lang::getTxt('spellcheck_ignore_all', file: 'Post'), '" onclick="nextWord(true);" class="button">
			</div>
		</form>
	</body>
</html>';
}

/**
 * The template for the AJAX quote feature
 */
function template_quotefast()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', Lang::getTxt('retrieving_quote', file: 'Post'), '</title>
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
	</head>
	<body>
		', Lang::getTxt('retrieving_quote', file: 'Post'), '
		<div id="temporary_posting_area" style="display: none;"></div>
		<script>';

	if (Utils::$context['close_window'])
		echo '
			window.close();';
	else
	{
		// Lucky for us, Internet Explorer has an "innerText" feature which basically converts entities <--> text. Use it if possible ;)
		echo '
			var quote = \'', Utils::$context['quote']['text'], '\';
			var stage = \'createElement\' in document ? document.createElement("DIV") : document.getElementById("temporary_posting_area");

			if (\'DOMParser\' in window && !(\'opera\' in window))
			{
				var xmldoc = new DOMParser().parseFromString("<temp>" + \'', Utils::$context['quote']['mozilla'], '\'.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_") + "</temp>", "text/xml");
				quote = xmldoc.childNodes[0].textContent.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}
			else if (\'innerText\' in stage)
			{
				setInnerHTML(stage, quote.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_").replace(/</g, "&lt;").replace(/>/g, "&gt;"));
				quote = stage.innerText.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}

			if (\'opera\' in window)
				quote = quote.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, \'"\').replace(/&amp;/g, "&");

			window.opener.onReceiveOpener(quote);

			window.focus();
			setTimeout(function() { window.close(); }, 400);';
	}
	echo '
		</script>
	</body>
</html>';
}

/**
 * The form for sending out an announcement
 */
function template_announce()
{
	echo '
	<div id="announcement">
		<form action="', Config::$scripturl, '?action=announce;sa=send" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('announce_title', file: 'Post'), '</h3>
			</div>
			<div class="information">
				', Lang::getTxt('announce_desc', file: 'Post'), '
			</div>
			<div class="windowbg">
				<p>
					', Lang::getTxt('announce_this_topic', ['subject' => '<a href="' . Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.0">' . Utils::$context['topic_subject'] . '</a>'], file: 'Post'), '
				</p>
				<ul>';

	foreach (Utils::$context['groups'] as $group)
		echo '
					<li>
						<label for="who_', $group['id'], '"><input type="checkbox" name="who[', $group['id'], ']" id="who_', $group['id'], '" value="', $group['id'], '" checked> ', $group['name'], '</label> <em>(', $group['member_count'] ?? Lang::getTxt('not_applicable', file: 'General'), ')</em>
					</li>';

	echo '
					<li>
						<label for="checkall"><input type="checkbox" id="checkall" onclick="invertAll(this, this.form);" checked> <em>', Lang::getTxt('check_all', file: 'General'), '</em></label>
					</li>
				</ul>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" value="', Lang::getTxt('post', file: 'General'), '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="topic" value="', Utils::$context['current_topic'], '">
					<input type="hidden" name="move" value="', Utils::$context['move'], '">
					<input type="hidden" name="goback" value="', Utils::$context['go_back'], '">
				</div>
				<br class="clear_right">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #announcement -->
	<br>';
}

/**
 * The confirmation/progress page, displayed after the admin has clicked the button to send the announcement.
 */
function template_announcement_send()
{
	echo '
	<div id="announcement">
		<form action="', Config::$scripturl, '?action=announce;sa=send" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="windowbg">
				<p>
					', Lang::getTxt('announce_sending', ['subject' => '<a href="' . Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.0" target="_blank" rel="noopener">' . Utils::$context['topic_subject'] . '</a>'], file: 'Post'),'
				</p>
				<div class="progress_bar">
					<span>', Lang::getTxt('announce_done', Utils::$context, file: 'Post'), '</span>
					<div class="bar" style="width: ', Utils::$context['percentage_done'], '%;"></div>
				</div>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" name="b" value="', Lang::getTxt('announce_continue', file: 'Post'), '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="topic" value="', Utils::$context['current_topic'], '">
					<input type="hidden" name="move" value="', Utils::$context['move'], '">
					<input type="hidden" name="goback" value="', Utils::$context['go_back'], '">
					<input type="hidden" name="start" value="', Utils::$context['start'], '">
					<input type="hidden" name="membergroups" value="', Utils::$context['membergroups'], '">
				</div>
				<br class="clear_right">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #announcement -->
	<br>
	<script>
		var countdown = 2;
		doAutoSubmit();

		function doAutoSubmit()
		{
			if (countdown == 0)
				document.forms.autoSubmit.submit();
			else if (countdown == -1)
				return;

			document.forms.autoSubmit.b.value = "', Lang::getTxt('announce_continue', file: 'Post'), ' (" + countdown + ")";
			countdown--;

			setTimeout(doAutoSubmit, 1000);
		}
	</script>';
}

/**
 * Prints the input fields in the form's header (subject, message icon, guest name & email, etc.)
 *
 * Mod authors can use the 'integrate_post_end' hook to modify or add to these (see Post.php).
 *
 * Theme authors can customize the output in a couple different ways:
 * 1. Change specific values in the Utils::$context['posting_fields'] array.
 * 2. Add an 'html' element to the 'label' and/or 'input' elements of the field they want to
 *    change. This should contain the literal HTML string to be printed.
 *
 * See the documentation in Post.php for more info on the Utils::$context['posting_fields'] array.
 */
function template_post_header()
{
	// Sanity check: submitting the form won't work without at least a subject field
	if (empty(Utils::$context['posting_fields']['subject']) || !is_array(Utils::$context['posting_fields']['subject']))
	{
		Utils::$context['posting_fields']['subject'] = array(
			'label' => array('html' => '<label for="subject" id="caption_subject">' . Lang::getTxt('subject', file: 'General') . '</label>'),
			'input' => array('html' => '<input type="text" id="subject" name="subject" value="' . Utils::$context['subject'] . '" size="80" maxlength="80" required>')
		);
	}

	// THEME AUTHORS: Above this line is a great place to make customizations to the posting_fields array

	// Start printing the header
	echo '
					<dl id="post_header">';

	foreach (Utils::$context['posting_fields'] as $pfid => $pf)
	{
		// We need both a label and an input
		if (empty($pf['label']) || empty($pf['input']))
			continue;

		// The labels are pretty simple...
		echo '
						<dt class="clear pf_', $pfid, '">';

		// Any leading HTML before the label
		if (!empty($pf['label']['before']))
			echo '
							', $pf['label']['before'];

		if (!empty($pf['label']['html']))
			echo $pf['label']['html'];
		else
			echo '
							<label', ($pf['input']['type'] === 'radio_select' ? '' : ' for="' . (!empty($pf['input']['attributes']['id']) ? $pf['input']['attributes']['id'] : $pfid) . '"'), ' id="caption_', $pfid, '"', !empty($pf['label']['class']) ? ' class="' . $pf['label']['class'] . '"' : '', '>', $pf['label']['text'], '</label>';

		// Any trailing HTML after the label
		if (!empty($pf['label']['after']))
			echo '
							', $pf['label']['after'];

		echo '
						</dt>';

		// Here's where the fun begins...
		echo '
						<dd class="pf_', $pfid, '">';

		// Any leading HTML before the main input
		if (!empty($pf['input']['before']))
			echo '
							', $pf['input']['before'];

		// If there is a literal HTML string already defined, just print it.
		if (!empty($pf['input']['html']))
		{
			echo $pf['input']['html'];
		}
		// Simple text inputs and checkboxes
		elseif (in_array($pf['input']['type'], array('text', 'password', 'color', 'date', 'datetime-local', 'email', 'month', 'number', 'range', 'tel', 'time', 'url', 'week', 'checkbox')))
		{
			echo '
							<input type="', $pf['input']['type'], '"';

			if (empty($pf['input']['attributes']['id']))
				echo ' id="', $pfid, '"';

			if (empty($pf['input']['attributes']['name']))
				echo ' name="', $pfid, '"';

			if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes']))
			{
				foreach ($pf['input']['attributes'] as $attribute => $value)
				{
					if (is_bool($value))
						echo $value ? ' ' . $attribute : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}
			}

			echo ' tabindex="', Utils::$context['tabindex']++, '">';
		}
		// textarea
		elseif ($pf['input']['type'] === 'textarea')
		{
			echo '
							<textarea';

			if (empty($pf['input']['attributes']['id']))
				echo ' id="', $pfid, '"';

			if (empty($pf['input']['attributes']['name']))
				echo ' name="', $pfid, '"';

			if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes']))
			{
				foreach ($pf['input']['attributes'] as $attribute => $value)
				{
					if ($attribute === 'value')
						continue;
					elseif (is_bool($value))
						echo $value ? ' ' . $attribute : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}
			}

			echo ' tabindex="', Utils::$context['tabindex']++, '">', !empty($pf['input']['attributes']['value']) ? $pf['input']['attributes']['value'] : '', '</textarea>';
		}
		// Select menus are more complicated
		elseif ($pf['input']['type'] === 'select' && is_array($pf['input']['options']))
		{
			// The select element itself
			echo '
							<select';

			if (empty($pf['input']['attributes']['id']))
				echo ' id="', $pfid, '"';

			if (empty($pf['input']['attributes']['name']))
				echo ' name="', $pfid, !empty($pf['input']['attributes']['multiple']) ? '[]' : '', '"';

			if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes']))
			{
				foreach ($pf['input']['attributes'] as $attribute => $value)
				{
					if (is_bool($value))
						echo $value ? ' ' . $attribute : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}
			}

			echo ' tabindex="', Utils::$context['tabindex']++, '">';

			// The options
			foreach ($pf['input']['options'] as $optlabel => $option)
			{
				// An option containing options is an optgroup
				if (!empty($option['options']) && is_array($option['options']))
				{
					echo '
								<optgroup';

					if (empty($option['label']))
						echo ' label="', $optlabel, '"';

					if (!empty($option) && is_array($option))
					{
						foreach ($option as $attribute => $value)
						{
							if ($attribute === 'options')
								continue;
							elseif (is_bool($value))
								echo $value ? ' ' . $attribute : '';
							else
								echo ' ', $attribute, '="', $value, '"';
						}
					}

					echo '>';

					foreach ($option['options'] as $grouped_optlabel => $grouped_option)
					{
						echo '
									<option';

						foreach ($grouped_option as $attribute => $value)
						{
							if (is_bool($value))
								echo $value ? ' ' . $attribute : '';
							else
								echo ' ', $attribute, '="', $value, '"';
						}

						echo '>', $grouped_option['label'], '</option>';

					}

					echo '
								</optgroup>';
				}
				// Simple option
				else
				{
					echo '
								<option';

					foreach ($option as $attribute => $value)
					{
						if (is_bool($value))
							echo $value ? ' ' . $attribute : '';
						else
							echo ' ', $attribute, '="', $value, '"';
					}

					echo '>', $optlabel, '</option>';
				}
			}

			// Close the select element
			echo '
							</select>';
		}
		// Radio_select makes a div with some radio buttons in it
		elseif ($pf['input']['type'] === 'radio_select' && is_array($pf['input']['options']))
		{
			echo '
							<div';

			if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes']))
			{
				foreach ($pf['input']['attributes'] as $attribute => $value)
				{
					if ($attribute === 'name')
						continue;
					elseif (is_bool($value))
						echo $value ? ' ' . $attribute : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}
			}

			echo '>';

			foreach ($pf['input']['options'] as $optlabel => $option)
			{
				echo '
							<label style="margin-right:2ch"><input type="radio" name="', !empty($pf['input']['attributes']['name']) ? $pf['input']['attributes']['name'] : $pfid, '"';

				foreach ($option as $attribute => $value)
				{
					if ($attribute === 'label')
						continue;
					elseif (is_bool($value))
						echo $value ? ' ' . ($attribute === 'selected' ? 'checked' : $attribute) : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}

				echo ' tabindex="', Utils::$context['tabindex']++, '"> ', isset($option['label']) ? $option['label'] : $optlabel, '</label>';
			}

			echo '
							</div>';
		}

		// Any trailing HTML after the main input
		if (!empty($pf['input']['after']))
			echo '
							', $pf['input']['after'];

		echo '
						</dd>';
	}

	echo '
					</dl>';
}
