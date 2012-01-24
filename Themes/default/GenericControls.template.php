<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// This function displays all the stuff you get with a richedit box - BBC, smileys etc.
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $options, $txt, $modSettings, $scripturl;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<div>
			<div style="width: 98.8%;">
				<div>
					<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" rows="', $editor_context['rows'], '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="height: ', $editor_context['height'], '; ', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '">', $editor_context['value'], '</textarea>
				</div>
				<div id="', $editor_id, '_resizer" class="richedit_resize"></div>
			</div>
		</div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script type="text/javascript"><!-- // --><![CDATA[';

		// Show the smileys.
		if ((!empty($context['smileys']['postform']) || !empty($context['smileys']['popup'])) && !$editor_context['disable_smiley_box'] && $smileyContainer !== null)
		{
			echo '
				var oSmileyBox_', $editor_id, ' = new smc_SmileyBox({
					sUniqueId: ', JavaScriptEscape('smileyBox_' . $editor_id), ',
					sContainerDiv: ', JavaScriptEscape($smileyContainer), ',
					sClickHandler: ', JavaScriptEscape('oEditorHandle_' . $editor_id . '.insertSmiley'), ',
					oSmileyLocations: {';

			foreach ($context['smileys'] as $location => $smileyRows)
			{
				echo '
						', $location, ': [';
				foreach ($smileyRows as $smileyRow)
				{
					echo '
							[';
					foreach ($smileyRow['smileys'] as $smiley)
						echo '
								{
									sCode: ', JavaScriptEscape($smiley['code']), ',
									sSrc: ', JavaScriptEscape($settings['smileys_url'] . '/' . $smiley['filename']), ',
									sDescription: ', JavaScriptEscape($smiley['description']), '
								}', empty($smiley['isLast']) ? ',' : '';

				echo '
							]', empty($smileyRow['isLast']) ? ',' : '';
				}
				echo '
						]', $location === 'postform' ? ',' : '';
			}
			echo '
					},
					sSmileyBoxTemplate: ', JavaScriptEscape('
						%smileyRows% %moreSmileys%
					'), ',
					sSmileyRowTemplate: ', JavaScriptEscape('
						<div>%smileyRow%</div>
					'), ',
					sSmileyTemplate: ', JavaScriptEscape('
						<img src="%smileySource%" align="bottom" alt="%smileyDescription%" title="%smileyDescription%" id="%smileyId%" />
					'), ',
					sMoreSmileysTemplate: ', JavaScriptEscape('
						<a href="#" id="%moreSmileysId%">[' . (!empty($context['smileys']['postform']) ? $txt['more_smileys'] : $txt['more_smileys_pick']) . ']</a>
					'), ',
					sMoreSmileysLinkId: ', JavaScriptEscape('moreSmileys_' . $editor_id), ',
					sMoreSmileysPopupTemplate: ', JavaScriptEscape('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
						<html>
							<head>
								<title>' . $txt['more_smileys_title'] . '</title>
								<link rel="stylesheet" type="text/css" href="' . $settings['theme_url'] . '/css/index' . $context['theme_variant'] . '.css?fin20" />
							</head>
							<body id="help_popup">
								<div class="padding windowbg">
									<div class="cat_bar">
										<h3 class="catbg">
											' . $txt['more_smileys_pick'] . '
										</h3>
									</div>
									<div class="padding">
										%smileyRows%
									</div>
									<div class="smalltext centertext">
										<a href="#" id="%moreSmileysCloseLinkId%">' . $txt['more_smileys_close_window'] . '</a>
									</div>
								</div>
							</body>
						</html>'), '
				});';
		}

		if ($context['show_bbc'] && $bbcContainer !== null)
		{
			echo '
				var oBBCBox_', $editor_id, ' = new smc_BBCButtonBox({
					sUniqueId: ', JavaScriptEscape('BBCBox_' . $editor_id), ',
					sContainerDiv: ', JavaScriptEscape($bbcContainer), ',
					sButtonClickHandler: ', JavaScriptEscape('oEditorHandle_' . $editor_id . '.handleButtonClick'), ',
					sSelectChangeHandler: ', JavaScriptEscape('oEditorHandle_' . $editor_id . '.handleSelectChange'), ',
					aButtonRows: [';

			// Here loop through the array, printing the images/rows/separators!
			foreach ($context['bbc_tags'] as $i => $buttonRow)
			{
				echo '
						[';
				foreach ($buttonRow as $tag)
				{
					// Is there a "before" part for this bbc button? If not, it can't be a button!!
					if (isset($tag['before']))
						echo '
							{
								sType: \'button\',
								bEnabled: ', empty($context['disabled_tags'][$tag['code']]) ? 'true' : 'false', ',
								sImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/' . $tag['image'] . '.gif'), ',
								sCode: ', JavaScriptEscape($tag['code']), ',
								sBefore: ', JavaScriptEscape($tag['before']), ',
								sAfter: ', isset($tag['after']) ? JavaScriptEscape($tag['after']) : 'null', ',
								sDescription: ', JavaScriptEscape($tag['description']), '
							}', empty($tag['isLast']) ? ',' : '';

					// Must be a divider then.
					else
						echo '
							{
								sType: \'divider\'
							}', empty($tag['isLast']) ? ',' : '';
				}

				// Add the select boxes to the first row.
				if ($i == 0)
				{
					// Show the font drop down...
					if (!isset($context['disabled_tags']['font']))
						echo ',
							{
								sType: \'select\',
								sName: \'sel_face\',
								oOptions: {
									\'\': ', JavaScriptEscape($txt['font_face']), ',
									\'courier\': \'Courier\',
									\'arial\': \'Arial\',
									\'arial black\': \'Arial Black\',
									\'impact\': \'Impact\',
									\'verdana\': \'Verdana\',
									\'times new roman\': \'Times New Roman\',
									\'georgia\': \'Georgia\',
									\'andale mono\': \'Andale Mono\',
									\'trebuchet ms\': \'Trebuchet MS\',
									\'comic sans ms\': \'Comic Sans MS\'
								}
							}';

					// Font sizes anyone?
					if (!isset($context['disabled_tags']['size']))
						echo ',
							{
								sType: \'select\',
								sName: \'sel_size\',
								oOptions: {
									\'\': ', JavaScriptEscape($txt['font_size']), ',
									\'1\': \'8pt\',
									\'2\': \'10pt\',
									\'3\': \'12pt\',
									\'4\': \'14pt\',
									\'5\': \'18pt\',
									\'6\': \'24pt\',
									\'7\': \'36pt\'
								}
							}';

					// Print a drop down list for all the colors we allow!
					if (!isset($context['disabled_tags']['color']))
						echo ',
							{
								sType: \'select\',
								sName: \'sel_color\',
								oOptions: {
									\'\': ', JavaScriptEscape($txt['change_color']), ',
									\'black\': ', JavaScriptEscape($txt['black']), ',
									\'red\': ', JavaScriptEscape($txt['red']), ',
									\'yellow\': ', JavaScriptEscape($txt['yellow']), ',
									\'pink\': ', JavaScriptEscape($txt['pink']), ',
									\'green\': ', JavaScriptEscape($txt['green']), ',
									\'orange\': ', JavaScriptEscape($txt['orange']), ',
									\'purple\': ', JavaScriptEscape($txt['purple']), ',
									\'blue\': ', JavaScriptEscape($txt['blue']), ',
									\'beige\': ', JavaScriptEscape($txt['beige']), ',
									\'brown\': ', JavaScriptEscape($txt['brown']), ',
									\'teal\': ', JavaScriptEscape($txt['teal']), ',
									\'navy\': ', JavaScriptEscape($txt['navy']), ',
									\'maroon\': ', JavaScriptEscape($txt['maroon']), ',
									\'limegreen\': ', JavaScriptEscape($txt['lime_green']), ',
									\'white\': ', JavaScriptEscape($txt['white']), '
								}
							}';
				}
				echo '
						]', $i == count($context['bbc_tags']) - 1 ? '' : ',';
			}
			echo '
					],
					sButtonTemplate: ', JavaScriptEscape('
						<img id="%buttonId%" src="%buttonSrc%" align="bottom" width="23" height="22" alt="%buttonDescription%" title="%buttonDescription%" />
					'), ',
					sButtonBackgroundImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_bg.gif'), ',
					sButtonBackgroundImageHover: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_hoverbg.gif'), ',
					sActiveButtonBackgroundImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_hoverbg.gif'), ',
					sDividerTemplate: ', JavaScriptEscape('
						<img src="' . $settings['images_url'] . '/bbc/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />
					'), ',
					sSelectTemplate: ', JavaScriptEscape('
						<select name="%selectName%" id="%selectId%" style="margin-bottom: 1ex; font-size: x-small;">
							%selectOptions%
						</select>
					'), ',
					sButtonRowTemplate: ', JavaScriptEscape('
						<div>%buttonRow%</div>
					'), '
				});';
		}

		// Now it's all drawn out we'll actually setup the box.
		echo '
				var oEditorHandle_', $editor_id, ' = new smc_Editor({
					sSessionId: ', JavaScriptEscape($context['session_id']), ',
					sSessionVar: ', JavaScriptEscape($context['session_var']), ',
					sFormId: ', JavaScriptEscape($editor_context['form']), ',
					sUniqueId: ', JavaScriptEscape($editor_id), ',
					bRTL: ', $txt['lang_rtl'] ? 'true' : 'false', ',
					bWysiwyg: ', $editor_context['rich_active'] ? 'true' : 'false', ',
					sText: ', JavaScriptEscape($editor_context['rich_active'] ? $editor_context['rich_value'] : ''), ',
					sEditWidth: ', JavaScriptEscape($editor_context['width']), ',
					sEditHeight: ', JavaScriptEscape($editor_context['height']), ',
					bRichEditOff: ', empty($modSettings['disable_wysiwyg']) ? 'false' : 'true', ',
					oSmileyBox: ', !empty($context['smileys']['postform']) && !$editor_context['disable_smiley_box'] && $smileyContainer !== null ? 'oSmileyBox_' . $editor_id : 'null', ',
					oBBCBox: ', $context['show_bbc'] && $bbcContainer !== null ? 'oBBCBox_' . $editor_id : 'null', '
				});
				smf_editorArray[smf_editorArray.length] = oEditorHandle_', $editor_id, ';';

		echo '
			// ]]></script>';
}

function template_control_richedit_buttons($editor_id)
{
	global $context, $settings, $options, $txt, $modSettings, $scripturl;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<input type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />';

	if ($editor_context['preview_type'])
		echo '
		<input type="submit" name="preview" value="', isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewPost();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit" />';

	if ($context['show_spellchecking'])
		echo '
		<input type="button" value="', $txt['spell_check'], '" tabindex="', $context['tabindex']++, '" onclick="oEditorHandle_', $editor_id, '.spellCheckStart();" class="button_submit" />';
}

// What's this, verification?!
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	global $context, $settings, $options, $txt, $modSettings;

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

		// Do the actual stuff - image first?
		if ($i == 0 && $verify_context['show_visual'])
		{
			if ($context['use_graphic_library'])
				echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '" />';
			else
				echo '
				<img src="', $verify_context['image_href'], ';letter=1" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_1" />
				<img src="', $verify_context['image_href'], ';letter=2" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_2" />
				<img src="', $verify_context['image_href'], ';letter=3" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_3" />
				<img src="', $verify_context['image_href'], ';letter=4" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_4" />
				<img src="', $verify_context['image_href'], ';letter=5" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_5" />
				<img src="', $verify_context['image_href'], ';letter=6" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_6" />';

			if (WIRELESS)
				echo '<br />
				<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" />';
			else
				echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', $display_type != 'quick_reply' ? '<br />' : '', '<br />
					', $txt['visual_verification_description'], ':', $display_type != 'quick_reply' ? '<br />' : '', '
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" />
				</div>';
		}
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] ? $i - 1 : $i;

			echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br />
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" />
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