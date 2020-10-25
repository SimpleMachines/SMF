<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

/**
 * This function displays all the stuff you get with a richedit box - BBC, smileys, etc.
 *
 * @param string $editor_id The editor ID
 * @param null|bool $smileyContainer If null, hides the smiley section regardless of settings
 * @param null|bool $bbcContainer If null, hides the bbcode buttons regardless of settings
 */
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $modSettings, $smcFunc;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	if ($smileyContainer === null)
		$editor_context['sce_options']['emoticonsEnabled'] = false;

	if ($bbcContainer === null)
		$editor_context['sce_options']['toolbar'] = '';

	echo '
		<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="width: ', $editor_context['width'], '; height: ', $editor_context['height'], ';', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '"', !empty($context['editor']['required']) ? ' required' : '', '>', $editor_context['value'], '</textarea>
		<div id="', $editor_id, '_resizer" class="richedit_resize"></div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0">
		<script>
			$(document).ready(function() {
				', !empty($context['bbcodes_handlers']) ? $context['bbcodes_handlers'] : '', '

				var textarea = $("#', $editor_id, '").get(0);
				sceditor.create(textarea, ', $smcFunc['json_encode']($editor_context['sce_options'], JSON_PRETTY_PRINT), ');';

	if ($editor_context['sce_options']['emoticonsEnabled'])
		echo '
				sceditor.instance(textarea).createPermanentDropDown();';

	if (empty($editor_context['rich_active']))
		echo '
				sceditor.instance(textarea).toggleSourceMode();';

	if (isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']))
		echo '
				$(".sceditor-container").find("textarea").each(function() {$(this).css({border: "1px solid red"})});
				$(".sceditor-container").find("iframe").each(function() {$(this).css({border: "1px solid red"})});';

	echo '
			});';

	// Now for backward compatibility let's collect few infos in the good ol' style
	echo '
			var oEditorHandle_', $editor_id, ' = new smc_Editor({
				sUniqueId: ', JavaScriptEscape($editor_id), ',
				sEditWidth: ', JavaScriptEscape($editor_context['width']), ',
				sEditHeight: ', JavaScriptEscape($editor_context['height']), ',
				bRichEditOff: ', empty($modSettings['disable_wysiwyg']) ? 'false' : 'true', ',
				oSmileyBox: null,
				oBBCBox: null
			});
			smf_editorArray[smf_editorArray.length] = oEditorHandle_', $editor_id, ';
		</script>';
}

/**
 * This template shows the form buttons at the bottom of the editor
 *
 * @param string $editor_id The editor ID
 */
function template_control_richedit_buttons($editor_id)
{
	global $context, $settings, $txt, $modSettings;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<span class="smalltext">
			', $context['shortcuts_text'], '
		</span>
		<span class="post_button_container">';

	$tempTab = $context['tabindex'];

	if (!empty($context['drafts_pm_save']))
		$tempTab++;
	elseif (!empty($context['drafts_save']))
		$tempTab++;
	elseif ($editor_context['preview_type'])
		$tempTab++;
	elseif ($context['show_spellchecking'])
		$tempTab++;

	$tempTab++;
	$context['tabindex'] = $tempTab;

	foreach ($context['richedit_buttons'] as $name => $button) {
		if ($name == 'spell_check') {
			$button['onclick'] = 'oEditorHandle_' . $editor_id . '.spellCheckStart();';
		}

		if ($name == 'preview') {
			$button['value'] = isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $button['value'];
			$button['onclick'] = $editor_context['preview_type'] == 2 ? '' : 'return submitThisOnce(this);';
			$button['show'] = $editor_context['preview_type'];
		}

		if ($button['show']) {
			echo '
		<input type="', $button['type'], '"', $button['type'] == 'hidden' ? ' id="' . $name . '"' : '', ' name="', $name, '" value="', $button['value'], '"', $button['type'] != 'hidden' ? ' tabindex="' . --$tempTab . '"' : '', !empty($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', !empty($button['accessKey']) ? ' accesskey="' . $button['accessKey'] . '"' : '', $button['type'] != 'hidden' ? ' class="button"' : '', '>';
		}
	}

	echo '
		<input type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" name="post" tabindex="', --$tempTab, '" onclick="return submitThisOnce(this);" accesskey="s" class="button">
		</span>';

	// Load in the PM autosaver if it's enabled
	if (!empty($context['drafts_pm_save']) && !empty($context['drafts_autosave']))
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon"></span>
			<span id="draft_lastautosave" ></span>
		</span>
		<script src="', $settings['default_theme_url'], '/scripts/drafts.js', $context['browser_cache'], '"></script>
		<script>
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_pm_draft\',
				sSceditorID: \'', $editor_id, '\',
				sType: \'post\',
				bPM: true,
				iBoard: 0,
				iFreq: ', (empty($modSettings['drafts_autosave_frequency']) ? 60000 : $modSettings['drafts_autosave_frequency'] * 1000), '
			});
		</script>';

	// Start an instance of the auto saver if its enabled
	if (!empty($context['drafts_save']) && !empty($context['drafts_autosave']))
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="', $settings['images_url'], '/loading_sm.gif" alt="" class="centericon"></span>
			<span id="draft_lastautosave" ></span>
		</span>
		<script src="', $settings['default_theme_url'], '/scripts/drafts.js', $context['browser_cache'], '"></script>
		<script>
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_draft\',
				sSceditorID: \'', $editor_id, '\',
				sType: \'post\',
				iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
				iFreq: ', $context['drafts_autosave_frequency'], '
			});
		</script>';
}

/**
 * This template displays a verification form
 *
 * @param int|string $verify_id The verification control ID
 * @param string $display_type What type to display. Can be 'single' to only show one verification option or 'all' to show all of them
 * @param bool $reset Whether to reset the internal tracking counter
 * @return bool False if there's nothing else to show, true if $display_type is 'single', nothing otherwise
 */
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	global $context, $txt;

	$verify_context = &$context['controls']['verification'][$verify_id];

	// Keep track of where we are.
	if (empty($verify_context['tracking']) || $reset)
		$verify_context['tracking'] = 0;

	// How many items are there to display in total.
	$total_items = count($verify_context['questions']) + ($verify_context['show_visual'] || $verify_context['can_recaptcha'] ? 1 : 0);

	// If we've gone too far, stop.
	if ($verify_context['tracking'] > $total_items)
		return false;

	// Loop through each item to show them.
	for ($i = 0; $i < $total_items; $i++)
	{
		// If we're after a single item only show it if we're in the right place.
		if ($display_type == 'single' && $verify_context['tracking'] != $i)
			continue;

		if ($display_type != 'single')
			echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		// Display empty field, but only if we have one, and it's the first time.
		if ($verify_context['empty_field'] && empty($i))
			echo '
				<div class="smalltext vv_special">
					', $txt['visual_verification_hidden'], ':
					<input type="text" name="', $_SESSION[$verify_id . '_vv']['empty_field'], '" autocomplete="off" size="30" value="">
				</div>';

		// Do the actual stuff
		if ($i == 0 && ($verify_context['show_visual'] || $verify_context['can_recaptcha']))
		{
			if ($verify_context['show_visual'])
			{
				if ($context['use_graphic_library'])
					echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '">';
				else
					echo '
				<img src="', $verify_context['image_href'], ';letter=1" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_1">
				<img src="', $verify_context['image_href'], ';letter=2" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_2">
				<img src="', $verify_context['image_href'], ';letter=3" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_3">
				<img src="', $verify_context['image_href'], ';letter=4" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_4">
				<img src="', $verify_context['image_href'], ';letter=5" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_5">
				<img src="', $verify_context['image_href'], ';letter=6" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_6">';

				echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', $display_type != 'quick_reply' ? '<br>' : '', '<br>
					', $txt['visual_verification_description'], ':', $display_type != 'quick_reply' ? '<br>' : '', '
					<input type="text" name="', $verify_id, '_vv[code]" value="" size="30" tabindex="', $context['tabindex']++, '" autocomplete="off" required>
				</div>';
			}

			if ($verify_context['can_recaptcha'])
			{
				$lang = (isset($txt['lang_recaptcha']) ? $txt['lang_recaptcha'] : $txt['lang_dictionary']);
				echo '
				<div class="g-recaptcha centertext" data-sitekey="' . $verify_context['recaptcha_site_key'] . '" data-theme="' . $verify_context['recaptcha_theme'] . '"></div>
				<br>
				<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=' . $lang . '"></script>';
			}
		}
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] || $verify_context['can_recaptcha'] ? $i - 1 : $i;

			if (isset($verify_context['questions'][$qIndex]))
				echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br>
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' tabindex="', $context['tabindex']++, '" required>
				</div>';
		}

		if ($display_type != 'single')
			echo '
			</div><!-- #verification_control_[i] -->';

		// If we were displaying just one and we did it, break.
		if ($display_type == 'single' && $verify_context['tracking'] == $i)
			break;
	}

	// Assume we found something, always.
	$verify_context['tracking']++;

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single')
		return true;
}

?>