<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

// This function displays all the stuff you get with a richedit box - BBC, smileys etc.
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $options, $txt, $modSettings, $scripturl, $boardurl;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<div>
			<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" rows="', $editor_context['rows'], '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="height: ', $editor_context['height'], '; ', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '">', $editor_context['value'], '</textarea>
		</div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script type="text/javascript"><!-- // --><![CDATA[
			var bbc_quote_from = \'', $txt['quote_from'], '\'
			var bbc_quote = \'', $txt['quote'], '\'
			var bbc_search_on = \'', $txt['search_on'], '\';

			(function($) {
				var extensionMethods = {
					InsertText: function(text, bClear) {
						var bIsSource = this.inSourceMode();

						// @TODO make it put the quote close to the current selection

						if (!bIsSource)
							this.toggleTextMode();

						var current_value = bClear ? text + "\n" : this.getTextareaValue(false) + "\n" + text + "\n";
						this.setTextareaValue(current_value);

						if (!bIsSource)
							this.toggleTextMode();

					},
					getText: function() {
						if(this.inSourceMode())
							var current_value = this.getTextareaValue(false);
						else
							var current_value = this.getWysiwygEditorValue();

						return current_value;
					},
					appendEmoticon: function (code, emoticon) {
						if (code == \'\')
							line.append($(\'<br />\'));
						else
							line.append($(\'<img />\')
								.attr({
									src: emoticon,
									alt: code,
								})
								.click(function (e) {
									var	start = \'\', end = \'\';
									
									if (base.options.emoticonsCompat)
									{
										start = \'<span> \';
										end   = \' </span>\';
									}

									if (base.inSourceMode())
										base.textEditorInsertText(\' \' + $(this).attr(\'alt\') + \' \');
									else
										base.wysiwygEditorInsertHtml(start + \'<img src="\' + $(this).attr("src") +
											\'" data-sceditor-emoticon="\' + $(this).attr(\'alt\') + \'" />\' + end);

									e.preventDefault();
								})
							);

						if (line.children().length > 0)
							content.append(line);

						$(".sceditor-toolbar").append(content);
					},
					storeLastState: function (){
						this.wasSource = this.inSourceMode();
					},
					setTextMode: function () {
						if (!this.inSourceMode())
							this.toggleTextMode();
					},
					createPermanentDropDown: function() {
							var	emoticons	= $.extend({}, this.options.emoticons.dropdown);
							var popup_exists = false;
							content = $(\'<div />\').attr({class: "sceditor-insertemoticon"});
							line = $(\'<div />\');

							base = this;
							for (smiley_popup in this.options.emoticons.popup)
							{
								popup_exists = true;
								break;
							}
							if (popup_exists)
							{
								this.options.emoticons.more = this.options.emoticons.popup;
								moreButton = $(\'<div />\').attr({class: "sceditor-more"}).text(\'[\' + this._(\'More\') + \']\').click(function () {
									if ($(".sceditor-smileyPopup").length > 0)
									{
										$(".sceditor-smileyPopup").fadeIn(\'fast\');
									}
									else
									{
										var emoticons = $.extend({}, base.options.emoticons.popup);
										var basement = $(\'<div />\').attr({class: "sceditor-popup"});
											allowHide = true;
											popupContent = $(\'<div />\');
											line = $(\'<div />\');
											closeButton = $(\'<span />\').text(\'[\' + base._(\'Close\') + \']\').click(function () {
												$(".sceditor-smileyPopup").fadeOut(\'fast\');
											});

										$.each(emoticons, base.appendEmoticon);

										if (line.children().length > 0)
											popupContent.append(line);
										if (typeof closeButton !== "undefined")
											popupContent.append(closeButton);

										// IE needs unselectable attr to stop it from unselecting the text in the editor.
										// The editor can cope if IE does unselect the text it\'s just not nice.
										if(base.ieUnselectable !== false) {
											content = $(content);
											content.find(\':not(input,textarea)\').filter(function() { return this.nodeType===1; }).attr(\'unselectable\', \'on\');
										}

										$dropdown = $(\'<div class="sceditor-dropdown sceditor-smileyPopup" />\').append(popupContent);

										$dropdown.appendTo($(\'body\'));
										dropdownIgnoreLastClick = true;
										$dropdown.css({
											position: "fixed",
											top: $(window).height() * 0.2,
											left: $(window).width() * 0.5 - ($dropdown.width() / 2),
											"max-width": "50%"
										});

										// stop clicks within the dropdown from being handled
										$dropdown.click(function (e) {
											e.stopPropagation();
										});
									}
								});
							}
							$.each(emoticons, base.appendEmoticon);
							if (typeof moreButton !== "undefined")
								content.append(moreButton);
					}
				};

				$.extend(true, $[\'sceditor\'].prototype, extensionMethods);
			})(jQuery);

			$(document).ready(function() {
				$.sceditor.setCommand(
					\'ftp\',
					function (caller) {
						var	editor  = this,
							content = $(this._(\'<form><div><label for="link">{0}</label> <input type="text" id="link" value="ftp://" /></div>\' +
									\'<div><label for="des">{1}</label> <input type="text" id="des" value="" /></div></form>\',
								this._("URL:"),
								this._("Description (optional):")
							))
							.submit(function () {return false;});

						content.append($(
							this._(\'<div><input type="button" class="button" value="{0}" /></div>\',
								this._("Insert")
							)).click(function (e) {
							var val = $(this).parent("form").find("#link").val(),
								description = $(this).parent("form").find("#des").val();

							if(val !== "" && val !== "ftp://") {
								// needed for IE to reset the last range
								editor.focus();

								if(!editor.getRangeHelper().selectedHtml() || description)
								{
									if(!description)
										description = val;
									
									editor.wysiwygEditorInsertHtml(\'<a href="\' + val + \'">\' + description + \'</a>\');
								}
								else
									editor.execCommand("createlink", val);
							}

							editor.closeDropDown(true);
							e.preventDefault();
						}));

						editor.createDropDown(caller, "insertlink", content);
					},
					\'Insert FTP Link\'
				);
				$.sceditor.setCommand(
					\'glow\',
					function () {
						this.wysiwygEditorInsertHtml(\'[glow=red,2,300]\', \'[/glow]\');
					},
					\'Glow\'
				);
				$.sceditor.setCommand(
					\'shadow\',
					function () {
						this.wysiwygEditorInsertHtml(\'[shadow=red,left]\', \'[/shadow]\');
					},
					\'Shadow\'
				);
				$.sceditor.setCommand(
					\'tt\',
					function () {
						this.wysiwygEditorInsertHtml(\'<tt>\', \'</tt>\');
					},
					\'Teletype\'
				);
				', !empty($context['bbcodes_hanlders']) ? $context['bbcodes_hanlders'] : '', '

				$("#', $editor_id, '").sceditorBBCodePlugin({
					style: "', $settings['default_theme_url'], '/css/jquery.sceditor.default.css",
					emoticonsCompat: true,
					supportedWysiwyg: (((is_ie5up && !is_ie50) || is_ff || is_opera95up || is_safari || is_chrome) && !(is_iphone || is_android)),',
					!empty($txt['lang_locale']) && substr($txt['lang_locale'], 0, 5) != 'en_US' ? '
					locale: \'' . $txt['lang_locale'] . '\',' : '', '
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white"';

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
				foreach ($smileyRows as $smileyRow)
				{
					foreach ($smileyRow['smileys'] as $smiley)
					{
						echo '
								', JavaScriptEscape($smiley['code']), ': ', JavaScriptEscape(str_replace($boardurl . '/', '', $settings['smileys_url'] . '/' . $smiley['filename'])), empty($smiley['isLast']) ? ',' : '';
					}
					if (empty($smileyRow['isLast']) && $numRows != 1)
						echo ',
						\'\': \'\',';
				}
				echo '
						}', $countLocations != 0 ? ',' : '';
			}
			echo '
					}';
		}

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
					toolbar: "emoticon,source",';

		echo '
				});
				$("#', $editor_id, '").data("sceditor").createPermanentDropDown();
				$(".sceditor-container").width("100%").height("100%");', 
				$editor_context['rich_active'] ? '' : '
				$("#' . $editor_id . '").data("sceditor").setTextMode();', '
				if (!(((is_ie5up && !is_ie50) || is_ff || is_opera95up || is_safari || is_chrome) && !(is_iphone || is_android)))
				{
					$("#' . $editor_id . '").data("sceditor").setTextMode();
					$(".sceditor-button-source").hide();
				}
			});';

		// Now for backward compatibility let's collect few infos in the good ol' style
		echo '
				var oEditorHandle_', $editor_id, ' = new smc_Editor({
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sFormId: ', JavaScriptEscape($editor_context['form']), ',
					sUniqueId: ', JavaScriptEscape($editor_id), ',
					bRTL: ', $txt['lang_rtl'] ? 'true' : 'false', ',
					bWysiwyg: ', $editor_context['rich_active'] ? 'true' : 'false', ',
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
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', $display_type != 'quick_reply' ? '<br />' : '', '<br />
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