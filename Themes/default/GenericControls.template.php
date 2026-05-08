<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Editor;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\Verifier;

/**
 * Renders a rich-text editor, including BBC buttons and smileys if enabled.
 *
 * This function sets up a textarea as a rich-text editor using SCEditor.
 * The `$smiley_container` and `$bbc_container` parameters control the visibility
 * and source of smiley and BBC containers, respectively.
 *
 * @param string $editor_id The unique ID of the editor.
 * @param null|bool|string $smiley_container Controls the smiley container:
 *   - `null`: Hides the smiley section.
 *   - `true`: Generates the container dynamically using JavaScript.
 *   - `string`: Specifies the HTML element ID for the smiley container.
 * @param null|bool|string $bbc_container Controls the BBC container:
 *   - `null`: Hides the BBC buttons.
 *   - `true`: Generates the container dynamically using JavaScript.
 *   - `string`: Specifies the HTML element ID for the BBC container.
 */
function template_control_richedit(string $editor_id, bool|string|null $smiley_container = null, bool|string|null $bbc_container = null): void
{
	$editor_context = Editor::$loaded[$editor_id];

	echo '
		<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" cols="600" style="width: ', $editor_context['width'], '; height: ', $editor_context['height'], ';', isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '"', !empty(Utils::$context['editor']['required']) ? ' required' : '', '>', $editor_context['value'], '</textarea>
		<script>
			document.addEventListener("DOMContentLoaded", function () {
				var textarea = document.getElementById("', $editor_id, '"), options = ', $editor_context, ';
				sceditor.create(textarea, options, ' . Utils::escapeJavaScript($bbc_container) . ', ' . Utils::escapeJavaScript($smiley_container) . ');
			});
		</script>';
}

/**
 * Renders the buttons section below a rich-text editor.
 *
 * Displays form buttons such as "Post" or "Preview" and integrates
 * functionality like auto-saving drafts if enabled.
 *
 * @param string $editor_id The unique ID of the editor for which buttons are displayed.
 *
 */
function template_control_richedit_buttons(string $editor_id): void
{
	$editor_context = Editor::$loaded[$editor_id];

	echo '
		<span class="smalltext">
			', Utils::$context['shortcuts_text'], '
		</span>
		<span class="post_button_container">';

	foreach (Utils::$context['richedit_buttons'] as $name => $button) {
		if ($name == 'preview') {
			$button['value'] = $editor_context['labels']['preview_button'] ?? $button['value'];
			$button['show'] = $editor_context['preview_type'];
		}

		if ($button['show']) {
			echo '
		<input type="', $button['type'], '"', $button['type'] == 'hidden' ? ' id="' . $name . '"' : '', ' name="', $name, '" value="', $button['value'], '"', !empty($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', !empty($button['accessKey']) ? ' accesskey="' . $button['accessKey'] . '"' : '', $button['type'] != 'hidden' ? ' class="button"' : '', '>';
		}
	}

	echo '
		<input type="submit" value="', $editor_context['labels']['post_button'] ?? Lang::getTxt('post', file: 'General'), '" name="post" accesskey="s" class="button">
		</span>';

	// Include auto-save feature if drafts are enabled.
	if (!empty(Utils::$context['drafts_save']) && !empty(Utils::$context['drafts_autosave'])) {
		echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="', Theme::$current->settings['images_url'], '/loading_sm.gif" alt="" class="centericon"></span>
			<span id="draft_lastautosave"></span>
		</span>';
	}
}

/**
 * Displays a verification form with CAPTCHA or question-based challenges.
 *
 * Used to validate user input for forms, supporting various verification
 * mechanisms such as CAPTCHA images, reCAPTCHA, and custom questions.
 *
 * @param int|string $verify_id The unique identifier for the verification control.
 * @param string $display_type Determines how to display items:
 *   - `'single'`: Displays one item at a time (e.g., in a loop).
 *   - `'all'`: Displays all items together.
 * @param bool $reset Whether to reset the internal tracking counter.
 *
 * @return ?bool Returns `false` if no items are left to display, `true` if displaying a single item, and `null` otherwise.
 */
function template_control_verification(int|string $verify_id, string $display_type = 'all', bool $reset = false): ?bool
{
	$verify_context = Verifier::$loaded[$verify_id];

	// Reset tracking if necessary.
	if (empty($verify_context->tracking) || $reset) {
		$verify_context->tracking = 0;
	}

	$total_items = count($verify_context->questions) + ($verify_context->show_visual || $verify_context->can_recaptcha ? 1 : 0);

	// Stop if all items are processed.
	if ($verify_context->tracking > $total_items) {
		return false;
	}

	// Loop through each item to show them.
	for ($i = 0; $i < $total_items; $i++) {
		// If we're after a single item only show it if we're in the right place.
		if ($display_type == 'single' && $verify_context->tracking != $i) {
			continue;
		}

		if ($display_type != 'single') {
			echo '
			<div id="verification_control_', $i, '" class="verification_control">';
		}

		// Display empty field, but only if we have one, and it's the first time.
		if ($verify_context->empty_field && empty($i)) {
			echo '
				<div class="smalltext vv_special">
					', Lang::getTxt('visual_verification_hidden', file: 'General'), '
					<input type="text" name="', $_SESSION[$verify_id . '_vv']['empty_field'], '" autocomplete="off" size="30" value="">
				</div>';
		}

		// Do the actual stuff
		if ($i == 0 && ($verify_context->show_visual || $verify_context->can_recaptcha)) {
			if ($verify_context->show_visual) {
				if (Utils::$context['use_graphic_library']) {
					echo '
				<img src="', $verify_context->image_href, '" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '">';
				} else {
				echo '
				<img src="', $verify_context->image_href, ';letter=1" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_1">
				<img src="', $verify_context->image_href, ';letter=2" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_2">
				<img src="', $verify_context->image_href, ';letter=3" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_3">
				<img src="', $verify_context->image_href, ';letter=4" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_4">
				<img src="', $verify_context->image_href, ';letter=5" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_5">
				<img src="', $verify_context->image_href, ';letter=6" alt="', Lang::getTxt('visual_verification_description', file: 'General'), '" id="verification_image_', $verify_id, '_6">';
				}

				echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context->image_href, ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', Lang::getTxt('visual_verification_sound', file: 'General'), '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', Lang::getTxt('visual_verification_request_new', file: 'General'), '</a>', $display_type != 'quick_reply' ? '<br>' : '', '<br>
					', Lang::getTxt('visual_verification_description', file: 'General'), $display_type != 'quick_reply' ? '<br>' : '', '
					<input type="text" name="', $verify_id, '_vv[code]" value="" size="30" autocomplete="off" required>
				</div>';
			}

			if ($verify_context->can_recaptcha) {
				$lang = Lang::getTxt(Lang::txtExists('lang_recaptcha', file: 'General') ? 'lang_recaptcha' : 'lang_dictionary', file: 'General');
				echo '
				<div class="g-recaptcha centertext" data-sitekey="' . $verify_context->recaptcha_site_key . '" data-theme="' . $verify_context->recaptcha_theme . '"></div>
				<br>
				<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=' . $lang . '"></script>';
			}
		} else {
			// Where in the question array is this question?
			$qIndex = $verify_context->show_visual || $verify_context->can_recaptcha ? $i - 1 : $i;

			if (isset($verify_context->questions[$qIndex])) {
				echo '
				<div class="smalltext">
					', $verify_context->questions[$qIndex]['q'], ':<br>
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context->questions[$qIndex]['id'], ']" size="30" value="', $verify_context->questions[$qIndex]['a'], '" ', $verify_context->questions[$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' required>
				</div>';
			}
		}

		if ($display_type != 'single') {
			echo '
			</div><!-- #verification_control_[i] -->';
		}

		// If we were displaying just one and we did it, break.
		if ($display_type == 'single' && $verify_context->tracking == $i) {
			break;
		}
	}

	// Assume we found something, always.
	$verify_context->tracking++;

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single') {
		return true;
	}

	return null;
}

/**
 * Renders a UI for choosing boards within categories.
 *
 * This function outputs a set of fieldsets representing categories,
 * each containing a nested list of boards. Boards can be selected using checkboxes.
 * The function also handles the display of child boards in a hierarchical structure
 * and adds JavaScript functionality to enable selecting or deselecting all boards within a category.
 *
 * @param array $categories An array of categories, each containing:
 *                          - 'name': The name of the category.
 *                          - 'boards': An array of boards with:
 *                              - 'id': The unique identifier for the board.
 *                              - 'name': The display name of the board.
 *                              - 'selected': Whether the board is selected (boolean).
 *                              - 'child_level': The hierarchical level of the board (integer).
 */
function template_choose_boards(array $categories): void
{
	foreach ($categories as $category) {
		echo '
				<fieldset>
					<legend>
						', $category['name'], '
					</legend>
						<ul>';

		for ($i = 0, $n = count($category['boards']); $i < $n; $i++) {
			echo '
							<li>
							<label>
							<input type="checkbox" name="brd[]" value="', $category['boards'][$i]['id'], '"', $category['boards'][$i]['selected'] ? ' checked' : '', '>
								', $category['boards'][$i]['name'], '
							</label>';

			// Nest child boards inside another list.
			$curr_child_level = $category['boards'][$i]['child_level'];
			$next_child_level = $category['boards'][$i + 1]['child_level'] ?? 0;

			if ($next_child_level > $curr_child_level) {
				echo '
								<ul>';
			} else {
				// Close child board lists until we reach a common level
				// with the next board.
				while ($next_child_level < $curr_child_level--) {
					echo '
									</li>
								</ul>';
				}

				echo '</li>';
			}
		}

		echo '
						</ul>
							</fieldset>';
	}

	echo '
			<script>
				for (const div of document.forms.searchform)
					if (div.nodeName == "FIELDSET")
					{
						let allChecked = true;
						for (let o of div.elements)
							if (o.nodeName == "INPUT" && o.type == "checkbox")
								allChecked &= o.checked;

						var
							a = document.createElement("legend"),
							b = document.createElement("input"),
							c = document.createElement("label");
						b.type = "checkbox";
						b.checked = allChecked;
						c.appendChild(b);
						c.appendChild(document.createTextNode(div.firstElementChild.textContent));
						a.appendChild(c);
						div.firstElementChild.replaceWith(a);
						b.addEventListener("click", function(els)
						{
							for (const o of els)
								if (o.nodeName == "INPUT" && o.type == "checkbox")
									o.checked = this.checked;
						}.bind(b, div.elements));
					}
			</script>';
}
