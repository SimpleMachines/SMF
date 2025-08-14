<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Editor;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\AntiSpam\Verification;

/**
 * This function displays all the stuff you get with a richedit box - BBC, smileys, etc.
 *
 * @param string $editor_id The editor ID
 * @param null|bool $smileyContainer If null, hides the smiley section regardless of settings
 * @param null|bool $bbcContainer If null, hides the bbcode buttons regardless of settings
 */
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	$editor_context = Editor::$loaded[$editor_id];

	if ($smileyContainer === null)
		$editor_context['sce_options']['emoticonsEnabled'] = false;

	if ($bbcContainer === null)
		$editor_context['sce_options']['toolbar'] = '';

	echo '
		<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', Utils::$context['tabindex']++, '" style="width: ', $editor_context['width'], '; height: ', $editor_context['height'], ';', isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '"', !empty(Utils::$context['editor']['required']) ? ' required' : '', '>', $editor_context['value'], '</textarea>
		<div id="', $editor_id, '_resizer" class="richedit_resize"></div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0">
		<script>
			$(document).ready(function() {
				', !empty(Utils::$context['bbcodes_handlers']) ? Utils::$context['bbcodes_handlers'] : '', '

				var textarea = $("#', $editor_id, '").get(0);
				sceditor.create(textarea, ', Utils::jsonEncode($editor_context['sce_options'], JSON_PRETTY_PRINT), ');';

	if ($editor_context['sce_options']['emoticonsEnabled'])
		echo '
				sceditor.instance(textarea).createPermanentDropDown();';

	if (empty($editor_context['rich_active']))
		echo '
				sceditor.instance(textarea).toggleSourceMode();';

	if (isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']))
		echo '
				$(".sceditor-container").find("textarea").each(function() {$(this).css({border: "1px solid red"})});
				$(".sceditor-container").find("iframe").each(function() {$(this).css({border: "1px solid red"})});';

	echo '
			});';

	// Now for backward compatibility let's collect few infos in the good ol' style
	echo '
			var oEditorHandle_', $editor_id, ' = new smc_Editor({
				sUniqueId: ', Utils::escapeJavaScript($editor_id), ',
				sEditWidth: ', Utils::escapeJavaScript($editor_context['width']), ',
				sEditHeight: ', Utils::escapeJavaScript($editor_context['height']), ',
				bRichEditOff: ', empty(Config::$modSettings['disable_wysiwyg']) ? 'false' : 'true', ',
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
	$editor_context = Editor::$loaded[$editor_id];

	echo '
		<span class="smalltext">
			', Utils::$context['shortcuts_text'], '
		</span>
		<span class="post_button_container">';

	$tempTab = Utils::$context['tabindex'];

	if (!empty(Utils::$context['drafts_save']))
		$tempTab++;
	elseif ($editor_context['preview_type'])
		$tempTab++;
	elseif (Utils::$context['show_spellchecking'])
		$tempTab++;

	$tempTab++;
	Utils::$context['tabindex'] = $tempTab;

	foreach (Utils::$context['richedit_buttons'] as $name => $button) {
		if ($name == 'spell_check') {
			$button['onclick'] = 'oEditorHandle_' . $editor_id . '.spellCheckStart();';
		}

		if ($name == 'preview') {
			$button['value'] = isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $button['value'];
			$button['onclick'] = $editor_context['preview_type'] == Editor::PREVIEW_XML ? '' : 'return submitThisOnce(this);';
			$button['show'] = $editor_context['preview_type'];
		}

		if ($button['show']) {
			echo '
		<input type="', $button['type'], '"', $button['type'] == 'hidden' ? ' id="' . $name . '"' : '', ' name="', $name, '" value="', $button['value'], '"', $button['type'] != 'hidden' ? ' tabindex="' . --$tempTab . '"' : '', !empty($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', !empty($button['accessKey']) ? ' accesskey="' . $button['accessKey'] . '"' : '', $button['type'] != 'hidden' ? ' class="button"' : '', '>';
		}
	}

	echo '
		<input type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : Lang::getTxt('post', file: 'General'), '" name="post" tabindex="', --$tempTab, '" onclick="return submitThisOnce(this);" accesskey="s" class="button">
		</span>';

	// Start an instance of the auto saver if it's enabled
	if (!empty(Utils::$context['drafts_save']) && !empty(Utils::$context['drafts_autosave']))
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="', Theme::$current->settings['images_url'], '/loading_sm.gif" alt="" class="centericon"></span>
			<span id="draft_lastautosave" ></span>
		</span>
		<script>
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_draft\',
				sSceditorID: \'', $editor_id, '\',
				sType: \'post\',
				bPM: ', isset(Utils::$context['drafts_type']) && Utils::$context['drafts_type'] === 'pm' ? 'true' : 'false', ',
				iBoard: ', (empty(Utils::$context['current_board']) ? 0 : Utils::$context['current_board']), ',
				iFreq: ', Utils::$context['drafts_autosave_frequency'], '
			});
		</script>';
}

/**
 * This template displays a verification form
 *
 * @param int|string $verify_id The verification control ID
 * @param string $display_type What type to display. Can be 'single' to only show one verification option or 'all' to show all of them
 * @param bool $reset Whether to reset the internal tracking counter
 * @return void|bool False if there's nothing else to show, true if $display_type is 'single', nothing otherwise
 */
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	$i = 0;
	foreach (Verification::$loaded[$verify_id] as $agent => $callable) {
		if ($display_type != 'single') {
			echo '
			<div id="verification_control_', $i, '" class="verification_control">';
		}

		$callable();

		if ($display_type != 'single')
			echo '
			</div><!-- #verification_control_[', $i, '] -->';

		++$i;
	}

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single')
		return true;
}
