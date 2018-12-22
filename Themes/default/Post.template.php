<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * The main template for the post page.
 */
function template_main()
{
	global $context, $options, $txt, $scripturl, $modSettings, $counter;

	// Start the javascript... and boy is there a lot.
	echo '
		<script>';

	// When using Go Back due to fatal_error, allow the form to be re-submitted with changes.
	if (isBrowser('is_firefox'))
		echo '
			window.addEventListener("pageshow", reActivate, false);';

	// Start with message icons - and any missing from this theme.
	echo '
			var icon_urls = {';

	foreach ($context['icons'] as $icon)
		echo '
				\'', $icon['value'], '\': \'', $icon['url'], '\'', $icon['is_last'] ? '' : ',';

	echo '
			};';

	// If this is a poll - use some javascript to ensure the user doesn't create a poll with illegal option combinations.
	if ($context['make_poll'])
		echo '
			var pollOptionNum = 0, pollTabIndex;
			var pollOptionId = ', $context['last_choice_id'], ';
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

				setOuterHTML(document.getElementById(\'pollMoreOptions\'), ', JavaScriptEscape('<dt><label for="options-'), ' + pollOptionId + ', JavaScriptEscape('">' . $txt['option'] . ' '), ' + pollOptionNum + ', JavaScriptEscape('</label>:</dt><dd><input type="text" name="options['), ' + pollOptionId + ', JavaScriptEscape(']" id="options-'), ' + pollOptionId + ', JavaScriptEscape('" value="" size="80" maxlength="255" tabindex="'), ' + pollTabIndex + ', JavaScriptEscape('"></dd><p id="pollMoreOptions"></p>'), ');
			}';

	// If we are making a calendar event we want to ensure we show the current days in a month etc... this is done here.
	if ($context['make_event'])
		echo '
			var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];';

	// End of the javascript, start the form and display the link tree.
	echo '
		</script>
		<form action="', $scripturl, '?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="', ($context['becomes_approved'] ? '' : 'alert(\'' . $txt['js_post_will_require_approval'] . '\');'), 'submitonce(this);" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
			<div id="preview_section"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="preview_subject">', empty($context['preview_subject']) ? '&nbsp;' : $context['preview_subject'], '</span>
					</h3>
				</div>
				<div id="preview_body" class="windowbg">
					', empty($context['preview_message']) ? '<br>' : $context['preview_message'], '
				</div>
			</div>
			<br>';

	if ($context['make_event'] && (!$context['event']['new'] || !empty($context['current_board'])))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['id'], '">';

	// Start the main table.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div id="post_area">
				<div class="roundframe">', isset($context['current_topic']) ? '
					<input type="hidden" name="topic" value="' . $context['current_topic'] . '">' : '';

	// If an error occurred, explain what happened.
	echo '
					<div class="', empty($context['error_type']) || $context['error_type'] != 'serious' ? 'noticebox' : 'errorbox', '"', empty($context['post_error']) ? ' style="display: none"' : '', ' id="errors">
						<dl>
							<dt>
								<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
							</dt>
							<dd class="error" id="error_list">
								', empty($context['post_error']) ? '' : implode('<br>', $context['post_error']), '
							</dd>
						</dl>
					</div>';

	// If this won't be approved let them know!
	if (!$context['becomes_approved'])
		echo '
					<div class="noticebox">
						<em>', $txt['wait_for_approval'], '</em>
						<input type="hidden" name="not_approved" value="1">
					</div>';

	// If it's locked, show a message to warn the replier.
	if (!empty($context['locked']))
		echo '
					<div class="errorbox">
						', $txt['topic_locked_no_reply'], '
					</div>';

	if (!empty($modSettings['drafts_post_enabled']))
		echo '
					<div id="draft_section" class="infobox"', isset($context['draft_saved']) ? '' : ' style="display: none;"', '>',
						sprintf($txt['draft_saved'], $scripturl . '?action=profile;u=' . $context['user']['id'] . ';area=showdrafts'), '
						', (!empty($modSettings['drafts_keep_days']) ? ' <strong>' . sprintf($txt['draft_save_warning'], $modSettings['drafts_keep_days']) . '</strong>' : ''), '
					</div>';

	// The post header... important stuff
	template_post_header();

	// Are you posting a calendar event?
	if ($context['make_event'])
	{
		echo '
					<hr class="clear">
					<div id="post_event">
						<fieldset id="event_main">
							<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', '>', $txt['calendar_event_title'], '</span></legend>
							<input type="hidden" name="calendar" value="1">
							<div class="event_options_left" id="event_title">
								<div>
									<input type="text" id="evtitle" name="evtitle" maxlength="255" size="55" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '">
								</div>
							</div>';

		// If this is a new event let the user specify which board they want the linked post to be put into.
		if ($context['event']['new'] && $context['is_new_post'])
		{
			echo '
							<div class="event_options_right" id="event_board">
								<div>
									<span class="label">', $txt['calendar_post_in'], '</span>
								<select name="board">';
			foreach ($context['event']['categories'] as $category)
			{
				echo '
										<optgroup label="', $category['name'], '">';

				foreach ($category['boards'] as $board)
					echo '
											<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
				echo '
										</optgroup>';
			}
			echo '
									</select>
								</div>
							</div><!-- #event_board -->';
		}

		// Note to theme writers: The JavaScripts expect the input fields for the start and end dates & times to be contained in a wrapper element with the id "event_time_input"
		echo '
						</fieldset>
						<fieldset id="event_options">
							<legend>', $txt['calendar_event_options'], '</legend>
							<div class="event_options_left" id="event_time_input">
								<div>
									<span class="label">', $txt['start'], '</span>
									<input type="text" name="start_date" id="start_date" maxlength="10" value="', $context['event']['start_date'], '" tabindex="', $context['tabindex']++, '" class="date_input start" data-type="date">
									<input type="text" name="start_time" id="start_time" maxlength="11" value="', $context['event']['start_time'], '" tabindex="', $context['tabindex']++, '" class="time_input start" data-type="time"', !empty($context['event']['allday']) ? ' disabled' : '', '>
								</div>
								<div>
									<span class="label">', $txt['end'], '</span>
									<input type="text" name="end_date" id="end_date" maxlength="10" value="', $context['event']['end_date'], '" tabindex="', $context['tabindex']++, '" class="date_input end" data-type="date"', $modSettings['cal_maxspan'] == 1 ? ' disabled' : '', '>
									<input type="text" name="end_time" id="end_time" maxlength="11" value="', $context['event']['end_time'], '" tabindex="', $context['tabindex']++, '" class="time_input end" data-type="time"', !empty($context['event']['allday']) ? ' disabled' : '', '>
								</div>
							</div>
							<div class="event_options_right" id="event_time_options">
								<div id="event_allday">
									<label for="allday"><span class="label">', $txt['calendar_allday'], '</span></label>
									<input type="checkbox" name="allday" id="allday"', !empty($context['event']['allday']) ? ' checked' : '', ' tabindex="', $context['tabindex']++, '">
								</div>
								<div id="event_timezone">
									<span class="label">', $txt['calendar_timezone'], '</span>
									<select name="tz" id="tz"', !empty($context['event']['allday']) ? ' disabled' : '', '>';

		foreach ($context['all_timezones'] as $tz => $tzname)
			echo '
										<option', is_numeric($tz) ? ' value="" disabled' : ' value="' . $tz . '"', $tz === $context['event']['tz'] ? ' selected' : '', '>', $tzname, '</option>';

		echo '
									</select>
								</div>
							</div><!-- #event_time_options -->
							<div>
								<span class="label">', $txt['location'], '</span>
								<input type="text" name="event_location" id="event_location" maxlength="255" value="', $context['event']['location'], '" tabindex="', $context['tabindex']++, '">
							</div>
						</fieldset>
					</div><!-- #post_event -->';
	}

	// If this is a poll then display all the poll options!
	if ($context['make_poll'])
	{
		echo '
					<hr class="clear">
					<div id="edit_poll">
						<fieldset id="poll_main">
							<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], '</span></legend>
							<dl class="settings poll_options">
								<dt>', $txt['poll_question'], '</dt>
								<dd>
									<input type="text" name="question" value="', isset($context['question']) ? $context['question'] : '', '" tabindex="', $context['tabindex']++, '" size="80">
								</dd>';

		// Loop through all the choices and print them out.
		foreach ($context['choices'] as $choice)
			echo '
								<dt>
									<label for="options-', $choice['id'], '">', $txt['option'], ' ', $choice['number'], '</label>:
								</dt>
								<dd>
									<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', $context['tabindex']++, '" size="80" maxlength="255">
								</dd>';

		echo '
								<p id="pollMoreOptions"></p>
							</dl>
							<strong><a href="javascript:addPollOption(); void(0);">(', $txt['poll_add_option'], ')</a></strong>
						</fieldset>
						<fieldset id="poll_options">
							<legend>', $txt['poll_options'], '</legend>
							<dl class="settings poll_options">
								<dt>
									<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
								</dt>
								<dd>
									<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll_options']['max_votes'], '">
								</dd>
								<dt>
									<label for="poll_expire">', $txt['poll_run'], ':</label><br>
									<em class="smalltext">', $txt['poll_run_limit'], '</em>
								</dt>
								<dd>
									<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll_options']['expire'], '" onchange="pollOptions();" maxlength="4"> ', $txt['days_word'], '
								</dd>
								<dt>
									<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked' : '', '>
								</dd>';

		if ($context['poll_options']['guest_vote_enabled'])
			echo '
								<dt>
									<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll_options']['guest_vote']) ? ' checked' : '', '>
								</dd>';

		echo '
								<dt>
									', $txt['poll_results_visibility'], ':
								</dt>
								<dd>
									<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll_options']['hide'] == 0 ? ' checked' : '', '> <label for="poll_results_anyone">', $txt['poll_results_anyone'], '</label><br>
									<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll_options']['hide'] == 1 ? ' checked' : '', '> <label for="poll_results_voted">', $txt['poll_results_voted'], '</label><br>
									<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll_options']['hide'] == 2 ? ' checked' : '', empty($context['poll_options']['expire']) ? ' disabled' : '', '> <label for="poll_results_expire">', $txt['poll_results_after'], '</label>
								</dd>
							</dl>
						</fieldset>
					</div><!-- #edit_poll -->';
	}

	// Show the actual posting area...
	echo '
					', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// If we're editing and displaying edit details, show a box where they can say why
	if (isset($context['editing']) && $modSettings['show_modify'])
		echo '
					<dl>
						<dt class="clear">
							<span id="caption_edit_reason">', $txt['reason_for_edit'], ':</span>
						</dt>
						<dd>
							<input type="text" name="modify_reason"', isset($context['last_modified_reason']) ? ' value="' . $context['last_modified_reason'] . '"' : '', ' tabindex="', $context['tabindex']++, '" size="80" maxlength="80">
						</dd>
					</dl>';

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
		echo '
					<div class="padding smalltext">
						', $context['last_modified_text'], '
					</div>';

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty($modSettings['additional_options_collapsable']))
		echo '
					<div id="post_additional_options_header">
						<strong><a href="#" id="postMoreExpandLink"> ', $context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt'], '</a></strong>
					</div>';

	echo '
					<div id="post_additional_options">';

	// Display the checkboxes for all the standard options - if they are available to the user!
	echo '
						<div id="post_settings" class="smalltext">
							<ul class="post_options">
								', $context['can_notify'] ? '<li><input type="hidden" name="notify" value="0"><label for="check_notify"><input type="checkbox" name="notify" id="check_notify"' . ($context['notify'] || !empty($options['auto_notify']) || $context['auto_notify'] ? ' checked' : '') . ' value="1"> ' . $txt['notify_replies'] . '</label></li>' : '', '
								', $context['can_lock'] ? '<li><input type="hidden" name="already_locked" value="' . $context['already_locked'] . '"><input type="hidden" name="lock" value="0"><label for="check_lock"><input type="checkbox" name="lock" id="check_lock"' . ($context['locked'] ? ' checked' : '') . ' value="1"> ' . $txt['lock_topic'] . '</label></li>' : '', '
								<li><label for="check_back"><input type="checkbox" name="goback" id="check_back"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' checked' : '') . ' value="1"> ' . $txt['back_to_topic'] . '</label></li>
								', $context['can_sticky'] ? '<li><input type="hidden" name="already_sticky" value="' . $context['already_sticky'] . '"><input type="hidden" name="sticky" value="0"><label for="check_sticky"><input type="checkbox" name="sticky" id="check_sticky"' . ($context['sticky'] ? ' checked' : '') . ' value="1"> ' . $txt['sticky_after_posting'] . '</label></li>' : '', '
								<li><label for="check_smileys"><input type="checkbox" name="ns" id="check_smileys"', $context['use_smileys'] ? '' : ' checked', ' value="NS"> ', $txt['dont_use_smileys'], '</label></li>', '
								', $context['can_move'] ? '<li><input type="hidden" name="move" value="0"><label for="check_move"><input type="checkbox" name="move" id="check_move" value="1"' . (!empty($context['move']) ? ' checked" ' : '') . '> ' . $txt['move_after_posting'] . '</label></li>' : '', '
								', $context['can_announce'] && $context['is_first_post'] ? '<li><label for="check_announce"><input type="checkbox" name="announce_topic" id="check_announce" value="1"' . (!empty($context['announce']) ? ' checked' : '') . '> ' . $txt['announce_topic'] . '</label></li>' : '', '
								', $context['show_approval'] ? '<li><label for="approve"><input type="checkbox" name="approve" id="approve" value="2"' . ($context['show_approval'] === 2 ? ' checked' : '') . '> ' . $txt['approve_this_post'] . '</label></li>' : '', '
							</ul>
						</div><!-- #post_settings -->';

	// If this post already has attachments on it - give information about them.
	if (!empty($context['current_attachments']))
	{
		echo '
						<dl id="postAttachment">
							<dt>
								', $txt['attached'], ':
							</dt>
							<dd class="smalltext" style="width: 100%;">
								<input type="hidden" name="attach_del[]" value="0">
								', $txt['uncheck_unwatchd_attach'], ':
							</dd>';

		foreach ($context['current_attachments'] as $attachment)
			echo '
							<dd class="smalltext">
								<label for="attachment_', $attachment['attachID'], '"><input type="checkbox" id="attachment_', $attachment['attachID'], '" name="attach_del[]" value="', $attachment['attachID'], '"', empty($attachment['unchecked']) ? ' checked' : '', '> ', $attachment['name'], (empty($attachment['approved']) ? ' (' . $txt['awaiting_approval'] . ')' : ''),
								!empty($modSettings['attachmentPostLimit']) || !empty($modSettings['attachmentSizeLimit']) ? sprintf($txt['attach_kb'], comma_format(round(max($attachment['size'], 1024) / 1024), 0)) : '', '</label>
							</dd>';

		echo '
						</dl>';

		if (!empty($context['files_in_session_warning']))
			echo '
						<div class="smalltext">', $context['files_in_session_warning'], '</div>';
	}

	// Is the user allowed to post any additional ones? If so give them the boxes to do it!
	if ($context['can_post_attachment'])
	{
		// Print dropzone UI.
		echo '
						<div class="files" id="attachment_previews">
							<div id="au-template">
								<div class="attach-preview">
									<img data-dz-thumbnail />
								</div>
								<div class="attachment_info">
									<div>
										<span class="name" data-dz-name></span>
										<span class="error" data-dz-errormessage></span>
										<span class="size" data-dz-size></span>
										<span class="message" data-dz-message></span>
									</div>
									<div class="attached_BBC">
										<input type="text" name="attachBBC" value="" readonly>
										<div class="attached_BBC_width_height">
											<div class="attached_BBC_width">
												<label for="attached_BBC_width">', $txt['attached_insert_width'], '</label>
												<input type="number" name="attached_BBC_width" min="0" value="" placeholder="auto">
											</div>
											<div class="attached_BBC_height">
												<label for="attached_BBC_height">', $txt['attached_insert_height'], '</label>
												<input type="number" name="attached_BBC_height" min="0" value="" placeholder="auto">
											</div>
										</div>
									</div><!-- .attached_BBC -->
									<div class="progress_bar" role="progressBar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
										<div class="bar"></div>
									</div>
									<div class="attach-ui">
										<a data-dz-remove class="button cancel">', $txt['modify_cancel'], '</a>
										<a class="button upload">', $txt['upload'], '</a>
									</div>
								</div><!-- .attachment_info -->
							</div><!-- #au-template -->
						</div><!-- #attachment_previews -->
						<div id ="max_files_progress" class="max_files_progress progress_bar">
							<div class="bar"></div>
						</div>
						<div id ="max_files_progress_text"></div>';

		echo '
						<dl id="postAttachment2">
							<dt>
								', $txt['attach'], ':
							</dt>
							<dd class="smalltext fallback">
								<div id="attachment_upload" class="descbox">
									<h5>', $txt['attach_drop_zone'], '</h5>
									<a class="button" id="attach_cancel_all">', $txt['attached_cancel_all'], '</a>
									<a class="button" id="attach_upload_all">', $txt['attached_upload_all'], '</a>
									<a class="button fileinput-button">', $txt['attach_add'], '</a>
									<div id="total_progress" class="progress_bar" role="progressBar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
										<div class="bar"></div>
									</div>
									<div class="fallback">
										<input type="file" multiple="multiple" name="attachment[]" id="attachment1" class="fallback"> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment1\');">', $txt['clean_attach'], '</a>)';

		if (!empty($modSettings['attachmentSizeLimit']))
			echo '
										<input type="hidden" name="MAX_FILE_SIZE" value="' . $modSettings['attachmentSizeLimit'] * 1024 . '">';

		// Show more boxes if they aren't approaching that limit.
		if ($context['num_allowed_attachments'] > 1)
			echo '
										<script>
											var allowed_attachments = ', $context['num_allowed_attachments'], ';
											var current_attachment = 1;

											function addAttachment()
											{
												allowed_attachments = allowed_attachments - 1;
												current_attachment = current_attachment + 1;
												if (allowed_attachments <= 0)
													return alert("', $txt['more_attachments_error'], '");

												setOuterHTML(document.getElementById("moreAttachments"), \'<dd class="smalltext"><input type="file" name="attachment[]" id="attachment\' + current_attachment + \'"> (<a href="javascript:void(0);" onclick="cleanFileInput(\\\'attachment\' + current_attachment + \'\\\');">', $txt['clean_attach'], '<\/a>)\' + \'<\/dd><dd class="smalltext" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')<\' + \'/a><\' + \'/dd>\');

												return true;
											}
										</script>
										<a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')</a>
									</div><!-- .fallback -->
								</div><!-- #attachment_upload -->';

		echo '
							</dd>';

		// Add any template changes for an alternative upload system here.
		call_integration_hook('integrate_upload_template');

		echo '
							<dd class="smalltext">';

		// Show some useful information such as allowed extensions, maximum size and amount of attachments allowed.
		if (!empty($modSettings['attachmentCheckExtensions']))
			echo '
								', $txt['allowed_types'], ': ', $context['allowed_extensions'], '<br>';

		if (!empty($context['attachment_restrictions']))
			echo '
								', $txt['attach_restrictions'], ' ', implode(', ', $context['attachment_restrictions']), '<br>';

		if ($context['num_allowed_attachments'] == 0)
			echo '
								', $txt['attach_limit_nag'], '<br>';

		if (!$context['can_post_attachment_unapproved'])
			echo '
								<span class="alert">', $txt['attachment_requires_approval'], '</span>', '<br>';

		echo '
							</dd>
						</dl>';
	}

	echo '
					</div><!-- #post_additional_options -->';

	// If the admin enabled the drafts feature, show a draft selection box
	if (!empty($modSettings['drafts_post_enabled']) && !empty($context['drafts']) && !empty($options['drafts_show_saved_enabled']))
	{
		echo '
					<div id="post_draft_options_header" class="title_bar">
						<h4 class="titlebg">
							<span id="postDraftExpand" class="toggle_up floatright" style="display: none;"></span> <strong><a href="#" id="postDraftExpandLink">', $txt['drafts_show'], '</a></strong>
						</h4>
					</div>
					<div id="post_draft_options">
						<dl class="settings">
							<dt><strong>', $txt['subject'], '</strong></dt>
							<dd><strong>', $txt['draft_saved_on'], '</strong></dd>';

		foreach ($context['drafts'] as $draft)
			echo '
							<dt>', $draft['link'], '</dt>
							<dd>', $draft['poster_time'], '</dd>';
		echo '
						</dl>
					</div>';
	}

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
					<div class="post_verification">
						<span', !empty($context['post_error']['need_qr_verification']) ? ' class="error"' : '', '>
							<strong>', $txt['verification'], ':</strong>
						</span>
						', template_control_verification($context['visual_verification_id'], 'all'), '
					</div>';

	// Finally, the submit buttons.
	echo '
					<br class="clear_right">
					<span id="post_confirm_buttons" class="floatright">
						', template_control_richedit_buttons($context['post_box_name']);

	// Option to delete an event if user is editing one.
	if ($context['make_event'] && !$context['event']['new'])
		echo '
						<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" data-confirm="', $txt['event_delete_confirm'], '" class="button you_sure">';

	echo '
					</span>
				</div><!-- .roundframe -->
			</div><!-- #post_area -->
			<br class="clear">';

	// Assuming this isn't a new topic pass across the last message id.
	if (isset($context['topic_last_message']))
		echo '
			<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '">';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
		</form>';

	echo '
		<script>';

	$newPostsHTML = '
		<span id="new_replies"></span>
		<div class="windowbg">
			<div id="msg%PostID%">
			<h5 class="floatleft">
				<span>' . $txt['posted_by'] . '</span>
				%PosterName%
			</h5>
			&nbsp;-&nbsp;%PostTime%&nbsp;&#187; <span class="new_posts" id="image_new_%PostID%">' . $txt['new'] . '</span>';

	if ($context['can_quote'])
		$newPostsHTML .= '
			<ul class="quickbuttons sf-js-enabled sf-arrows" id="msg_%PostID%_quote" style="touch-action: pan-y;">
				<li id="post_modify">
					<a href="#postmodify" onclick="return insertQuoteFast(%PostID%);" class="quote_button"><span class="main_icons quote"></span>' . $txt['quote'] . '</a>
				</li>
			</ul>';

	$newPostsHTML .= '
			<br class="clear">
			<div id="msg_%PostID%_ignored_prompt" class="smalltext" style="display: none;">' . $txt['ignoring_user'] . '<a href="#" id="msg_%PostID%_ignored_link" style="%IgnoredStyle%">' . $txt['show_ignore_user_post'] . '</a></div>
			<div class="list_posts smalltext" id="msg_%PostID%_body">%PostBody%</div>
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
				sPostBoxContainerID: ', JavaScriptEscape($context['post_box_name']), ',
				bMakePoll: ', $context['make_poll'] ? 'true' : 'false', ',
				sTxtPreviewTitle: ', JavaScriptEscape($txt['preview_title']), ',
				sTxtPreviewFetch: ', JavaScriptEscape($txt['preview_fetch']), ',
				sSessionVar: ', JavaScriptEscape($context['session_var']), ',
				newPostsTemplate:', JavaScriptEscape($newPostsHTML);

	if (!empty($context['current_board']))
		echo ',
				iCurrentBoard: ', $context['current_board'], '';

	echo '
			});';

	// Code for showing and hiding additional options.
	if (!empty($modSettings['additional_options_collapsable']))
		echo '
			var oSwapAdditionalOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', $context['show_additional_options'] ? 'false' : 'true', ',
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
						msgExpanded: ', JavaScriptEscape($context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']), ',
						msgCollapsed: ', JavaScriptEscape($context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']), '
					}
				]
			});';

	// Code for showing and hiding drafts
	if (!empty($context['drafts']))
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
						msgExpanded: ', JavaScriptEscape($txt['draft_hide']), ',
						msgCollapsed: ', JavaScriptEscape($txt['drafts_show']), '
					}
				]
			});';

	echo '
			var oEditorID = "', $context['post_box_name'], '";
			var oEditorObject = oEditorHandle_', $context['post_box_name'], ';
		</script>';

	// If the user is replying to a topic show the previous posts.
	if (isset($context['previous_posts']) && count($context['previous_posts']) > 0)
	{
		echo '
		<div id="recent" class="flow_hidden main_section">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['topic_summary'], '</h3>
			</div>
			<span id="new_replies"></span>';

		$ignored_posts = array();
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];

			echo '
			<div class="windowbg">
				<div id="msg', $post['id'], '">
					<h5 class="floatleft">
						<span>', $txt['posted_by'], '</span> ', $post['poster'], '
					</h5>
					&nbsp;-&nbsp;', $post['time'];

			if ($context['can_quote'])
				echo '
					<ul class="quickbuttons" id="msg_', $post['id'], '_quote">
						<li style="display:none;" id="quoteSelected_', $post['id'], '" data-msgid="', $post['id'], '"><a href="javascript:void(0)"><span class="main_icons quote_selected"></span>', $txt['quote_selected_action'], '</a></li>
						<li id="post_modify"><a href="#postmodify" onclick="return insertQuoteFast(', $post['id'], ');"><span class="main_icons quote"></span>', $txt['quote'], '</a></li>
					</ul>';

			echo '
					<br class="clear">';

			if ($ignoring)
				echo '
					<div id="msg_', $post['id'], '_ignored_prompt" class="smalltext">
						', $txt['ignoring_user'], '
						<a href="#" id="msg_', $post['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
					</div>';

			echo '
					<div class="list_posts smalltext" id="msg_', $post['id'], '_body" data-msgid="', $post['id'], '">', $post['message'], '</div>
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
						msgCollapsed: ', JavaScriptEscape($txt['show_ignore_user_post']), '
					}
				]
			});';
		}

		echo '
			function insertQuoteFast(messageid)
			{
				if (window.XMLHttpRequest)
					getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';xml;pb=', $context['post_box_name'], ';mode=0\', onDocReceived);
				else
					reqWin(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';pb=', $context['post_box_name'], ';mode=0\', 240, 90);

				return true;
			}
			function onDocReceived(XMLDoc)
			{
				var text = \'\';
				var e = $("#', $context['post_box_name'], '").get(0);

				for (var i = 0, n = XMLDoc.getElementsByTagName(\'quote\')[0].childNodes.length; i < n; i++)
					text += XMLDoc.getElementsByTagName(\'quote\')[0].childNodes[i].nodeValue;
				sceditor.instance(e).InsertText(text);
			}
			function onReceiveOpener(text)
			{
				sceditor.instance(e).InsertText(text);
			}
		</script>';
	}
}

/**
 * The template for the spellchecker.
 */
function template_spellcheck()
{
	global $context, $settings, $txt, $modSettings;

	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $txt['spell_check'], '</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'], '">
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
		<script src="', $settings['default_theme_url'], '/scripts/spellcheck.js', $modSettings['browser_cache'], '"></script>
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'], '"></script>
		<script>
			', $context['spell_js'], '
		</script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="', $context['character_set'], '" name="spellingForm" id="spellingForm" onsubmit="return false;">
			<div id="spellview">&nbsp;</div>
			<table width="100%">
				<tr class="windowbg">
					<td style="width: 50%; vertical-align: top">
						', $txt['spellcheck_change_to'], '<br>
						<input type="text" name="changeto" style="width: 98%;">
					</td>
					<td style="width: 50%">
						', $txt['spellcheck_suggest'], '<br>
						<select name="suggestions" style="width: 98%;" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
						</select>
					</td>
				</tr>
			</table>
			<div class="righttext" style="padding: 4px;">
				<input type="button" name="change" value="', $txt['spellcheck_change'], '" onclick="replaceWord();" class="button">
				<input type="button" name="changeall" value="', $txt['spellcheck_change_all'], '" onclick="replaceAll();" class="button">
				<input type="button" name="ignore" value="', $txt['spellcheck_ignore'], '" onclick="nextWord(false);" class="button">
				<input type="button" name="ignoreall" value="', $txt['spellcheck_ignore_all'], '" onclick="nextWord(true);" class="button">
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
	global $context, $settings, $txt, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $txt['retrieving_quote'], '</title>
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'], '"></script>
	</head>
	<body>
		', $txt['retrieving_quote'], '
		<div id="temporary_posting_area" style="display: none;"></div>
		<script>';

	if ($context['close_window'])
		echo '
			window.close();';
	else
	{
		// Lucky for us, Internet Explorer has an "innerText" feature which basically converts entities <--> text. Use it if possible ;)
		echo '
			var quote = \'', $context['quote']['text'], '\';
			var stage = \'createElement\' in document ? document.createElement("DIV") : document.getElementById("temporary_posting_area");

			if (\'DOMParser\' in window && !(\'opera\' in window))
			{
				var xmldoc = new DOMParser().parseFromString("<temp>" + \'', $context['quote']['mozilla'], '\'.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_") + "</temp>", "text/xml");
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
			setTimeout("window.close();", 400);';
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
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['announce_title'], '</h3>
			</div>
			<div class="information">
				', $txt['announce_desc'], '
			</div>
			<div class="windowbg">
				<p>
					', $txt['announce_this_topic'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0">', $context['topic_subject'], '</a>
				</p>
				<ul>';

	foreach ($context['groups'] as $group)
		echo '
					<li>
						<label for="who_', $group['id'], '"><input type="checkbox" name="who[', $group['id'], ']" id="who_', $group['id'], '" value="', $group['id'], '" checked> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
					</li>';

	echo '
					<li>
						<label for="checkall"><input type="checkbox" id="checkall" onclick="invertAll(this, this.form);" checked> <em>', $txt['check_all'], '</em></label>
					</li>
				</ul>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" value="', $txt['post'], '" class="button">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
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
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="', $context['character_set'], '" name="autoSubmit" id="autoSubmit">
			<div class="windowbg">
				<p>
					', $txt['announce_sending'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0" target="_blank" rel="noopener">', $context['topic_subject'], '</a>
				</p>
				<div class="progress_bar">
					<span>', $context['percentage_done'], '% ', $txt['announce_done'], '</span>
					<div class="bar" style="width: ', $context['percentage_done'], '%;"></div>
				</div>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" name="b" value="', $txt['announce_continue'], '" class="button">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
					<input type="hidden" name="start" value="', $context['start'], '">
					<input type="hidden" name="membergroups" value="', $context['membergroups'], '">
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

			document.forms.autoSubmit.b.value = "', $txt['announce_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	</script>';
}

/**
 * Prints the input fields in the form's header (subject, message icon, guest name & email, etc.)
 *
 * Mod authors can use the 'integrate_post_end' hook to modify or add to these (see Post.php).
 *
 * Theme authors can customize the output in a couple different ways:
 * 1. Change specific values in the $context['posting_fields'] array.
 * 2. Add an 'html' element to the 'label' and/or 'input' elements of the field they want to
 *    change. This should contain the literal HTML string to be printed.
 */
function template_post_header()
{
	global $context, $txt;

	// Sanity check: submitting the form won't work without at least a subject field
	if (empty($context['posting_fields']['subject']) || !is_array($context['posting_fields']['subject']))
	{
		$context['posting_fields']['subject'] = array(
			'label' => array('html' => '<label for="subject" id="caption_subject">' . $txt['subject'] . '</label>'),
			'input' => array('html' => '<input type="text" name="subject" value="' . $context['subject'] . '" size="80" maxlength="80" required>')
		);
	}

	// THEME AUTHORS: Above this line is a great place to make customizations to the posting_fields array

	// Start printing the header
	echo '
					<dl id="post_header">';

	foreach ($context['posting_fields'] as $pfid => $pf)
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
							<label for="', !empty($pf['input']['attributes']['name']) ? $pf['input']['attributes']['name'] : $pfid, '" id="caption_', $pfid, '"', !empty($pf['label']['class']) ? ' class="' . $pf['label']['class'] . '"' : '', '>', $pf['label']['text'], '</label>';

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

			echo ' tabindex="', $context['tabindex']++, '">';
		}
		// textarea
		elseif ($pf['input']['type'] === 'textarea')
		{
			echo '
							<textarea';

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

			echo ' tabindex="', $context['tabindex']++, '">', !empty($pf['input']['attributes']['value']) ? $pf['input']['attributes']['value'] : '', '</textarea>';
		}
		// Select menus are more complicated
		elseif ($pf['input']['type'] === 'select' && is_array($pf['input']['options']))
		{
			// The select element itself
			echo '
							<select';

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

			echo ' tabindex="', $context['tabindex']++, '">';

			// The options
			foreach ($pf['input']['options'] as $optlabel => $option)
			{
				// An option containing options is an optgroup
				if (!empty($option['options']) && is_array($option['options']))
				{
					echo '
								<optgroup';

					if (empty($option['attributes']['label']))
						echo ' label="', $optlabel, '"';

					if (!empty($option['attributes']) && is_array($option['attributes']))
					{
						foreach ($option['attributes'] as $attribute => $value)
						{
							if (is_bool($value))
								echo $value ? ' ' . $attribute : '';
							else
								echo ' ', $attribute, '="', $value, '"';
						}
					}

					echo '">';

					foreach ($option['options'] as $grouped_optlabel => $grouped_option)
					{
						echo '
									<option';

						foreach ($grouped_option['attributes'] as $attribute => $value)
						{
							if (is_bool($value))
								echo $value ? ' ' . $attribute : '';
							else
								echo ' ', $attribute, '="', $value, '"';
						}

						echo '>', $grouped_optlabel, '</option>';

					}

					echo '
								</optgroup>';
				}
				// Simple option
				else
				{
					echo '
								<option';

					foreach ($option['attributes'] as $attribute => $value)
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
							<input type="radio" name="', !empty($pf['input']['attributes']['name']) ? $pf['input']['attributes']['name'] : $pfid, '"';

				foreach ($option['attributes'] as $attribute => $value)
				{
					if (is_bool($value))
						echo $value ? ' ' . $attribute : '';
					else
						echo ' ', $attribute, '="', $value, '"';
				}

				echo ' tabindex="', $context['tabindex']++, '">', $optlabel, '</input>';
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

?>