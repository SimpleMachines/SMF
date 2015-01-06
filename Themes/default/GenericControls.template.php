<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// This function displays all the stuff you get with a richedit box - BBC, smileys etc.
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $modSettings;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="width: ', $editor_context['width'], '; height: ', $editor_context['height'], ';', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '"', !empty($context['editor']['required']) ? ' required' : '', '>', $editor_context['value'], '</textarea>
		<div id="', $editor_id, '_resizer" class="richedit_resize"></div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0">
		<script><!-- // --><![CDATA[
			$(document).ready(function() {
				', !empty($context['bbcodes_handlers']) ? $context['bbcodes_handlers'] : '', '

				$("#', $editor_id, '").sceditor({
					',( $editor_id != 'quickReply' ? 'autofocus : true,' : '' ),'
					style: "', $settings['default_theme_url'], '/css/jquery.sceditor.default.css",
					emoticonsCompat: true,',
					!empty($editor_context['locale']) ? '
					locale: \'' . $editor_context['locale'] . '\',' : '', '
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white",
					plugins: "bbcode",
					parserOptions: {
						quoteType: $.sceditor.BBCodeParser.QuoteType.auto
					}';

		// Show the smileys.
		if ((!empty($context['smileys']['postform']) || !empty($context['smileys']['popup'])) && !$editor_context['disable_smiley_box'] && $smileyContainer !== null)
		{
			echo ',
					emoticons:
					{';
			$countLocations = count($context['smileys']);
			foreach ($context['smileys'] as $location => $smileyRows)
			{
				$countLocations--;
				if ($location == 'postform')
					echo '
						dropdown:
						{';
				elseif ($location == 'popup')
					echo '
						popup:
						{';

				$numRows = count($smileyRows);
				// This is needed because otherwise the editor will remove all the duplicate (empty) keys and leave only 1 additional line
				$emptyPlaceholder = 0;
				foreach ($smileyRows as $smileyRow)
				{
					foreach ($smileyRow['smileys'] as $smiley)
					{
						echo '
								', JavaScriptEscape($smiley['code']), ': ', JavaScriptEscape($settings['smileys_url'] . '/' . $smiley['filename']), empty($smiley['isLast']) ? ',' : '';
					}
					if (empty($smileyRow['isLast']) && $numRows != 1)
						echo ',
						\'-', $emptyPlaceholder++, '\': \'\',';
				}
				echo '
						}', $countLocations != 0 ? ',' : '';
			}
			echo '
					}';
		}
		else
			echo ',
					emoticons:
					{}';

		if ($context['show_bbc'] && $bbcContainer !== null)
		{
			echo ',
					toolbar: "emoticon,';
			$count_tags = count($context['bbc_tags']);
			foreach ($context['bbc_toolbar'] as $i => $buttonRow)
			{
				echo implode('|', $buttonRow);
				$count_tags--;
				if (!empty($count_tags))
					echo '||';
			}

			echo '",';
		}
		else
			echo ',
					toolbar: "",';

		echo '
				});
				$("#', $editor_id, '").data("sceditor").createPermanentDropDown();',
				$editor_context['rich_active'] ? '' : '
				$("#' . $editor_id . '").data("sceditor").setTextMode();', '
				if (!(is_ie || is_ie11 || is_ff || is_opera || is_safari || is_chrome))
				{
					$("#' . $editor_id . '").data("sceditor").setTextMode();
					$(".sceditor-button-source").hide();
				}', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
				$(".sceditor-container").find("textarea").each(function() {$(this).css({border: "1px solid red"})});
				$(".sceditor-container").find("iframe").each(function() {$(this).css({border: "1px solid red"})});' : '', '
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
			// ]]></script>';
}

function template_control_richedit_buttons($editor_id)
{
	global $context, $settings, $txt, $modSettings;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<span class="smalltext">
			', $context['shortcuts_text'], '
		</span>';

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

	if (!empty($context['drafts_pm_save']))
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="',  --$tempTab, '" onclick="submitThisOnce(this);" accesskey="d" class="button_submit">
		<input type="hidden" id="id_pm_draft" name="id_pm_draft" value="', empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'], '">';

	if (!empty($context['drafts_save']))
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="', --$tempTab, '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" class="button_submit">
		<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '">';

	if ($context['show_spellchecking'])
		echo '
		<input type="button" value="', $txt['spell_check'], '" tabindex="', --$tempTab, '" onclick="oEditorHandle_', $editor_id, '.spellCheckStart();" class="button_submit">';

	if ($editor_context['preview_type'])
		echo '
		<input type="submit" name="preview" value="', isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $txt['preview'], '" tabindex="', --$tempTab, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewPost();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit">';


	echo '
		<input type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" tabindex="', --$tempTab, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit">';

	// Load in the PM autosaver if it's enabled
	if (!empty($context['drafts_pm_save']) && !empty($context['drafts_autosave']))
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon">&nbsp;</span>
			<span id="draft_lastautosave" ></span>
		</span>
		<script src="', $settings['default_theme_url'], '/scripts/drafts.js', $modSettings['browser_cache'] ,'"></script>
		<script><!-- // --><![CDATA[
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
		// ]]></script>';

	// Start an instance of the auto saver if its enabled
	if (!empty($context['drafts_save']) && !empty($context['drafts_autosave']))
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon">&nbsp;</span>
			<span id="draft_lastautosave" ></span>
		</span>
		<script src="', $settings['default_theme_url'], '/scripts/drafts.js', $modSettings['browser_cache'] ,'"></script>
		<script><!-- // --><![CDATA[
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_draft\',
				sSceditorID: \'', $editor_id, '\',
				sType: \'post\',
				iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
				iFreq: ', $context['drafts_autosave_frequency'], '
			});
		// ]]></script>';
}

// What's this, verification?!
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	global $context, $txt;

	$verify_context = &$context['controls']['verification'][$verify_id];

	// Keep track of where we are.
	if (empty($verify_context['tracking']) || $reset)
		$verify_context['tracking'] = 0;

	// How many items are there to display in total.
	$total_items = count($verify_context['questions']) + ($verify_context['show_visual'] ? 1 : 0);

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
				</div>
				<br>';

		// Do the actual stuff - image first?
		if ($i == 0 && $verify_context['show_visual'])
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

			if (WIRELESS)
				echo '<br>
				<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" required>';
			else
				echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', $display_type != 'quick_reply' ? '<br>' : '', '<br>
					', $txt['visual_verification_description'], ':', $display_type != 'quick_reply' ? '<br>' : '', '
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" required>
				</div>';
		}
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] ? $i - 1 : $i;

			echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br>
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" required>
				</div>';
		}

		if ($display_type != 'single')
			echo '
			</div>';

		// If we were displaying just one and we did it, break.
		if ($display_type == 'single' && $verify_context['tracking'] == $i)
			break;
	}

	// Assume we found something, always,
	$verify_context['tracking']++;

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single')
		return true;
}

?>