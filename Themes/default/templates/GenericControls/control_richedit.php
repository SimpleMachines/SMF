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

use SMF\Editor;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
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

// The parameters that were not passed get the defaults they had as arguments.
extract(['smiley_container' => null, 'bbc_container' => null], EXTR_SKIP);
?>

<?php $editor_context = Editor::$loaded[$editor_id]; ?>
		<textarea class="editor" name="<?= $editor_id ?>" id="<?= $editor_id ?>" cols="600" style="width: <?= $editor_context['width'] ?>; height: <?= $editor_context['height'] ?>;<?= isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? 'border: 1px solid red;' : '' ?>"<?= !empty(Utils::$context['editor']['required']) ? ' required' : '' ?>><?= $editor_context['value'] ?></textarea>
		<input type="hidden" name="<?= $editor_id ?>_mode" id="<?= $editor_id ?>_mode" value="0">
		<script>
			document.addEventListener("DOMContentLoaded", function () {
				var textarea = document.getElementById("<?= $editor_id ?>"), options = <?= json_encode($editor_context['sce_options'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
				sceditor.create(textarea, options, <?= Utils::escapeJavaScript($bbc_container) ?>, <?= Utils::escapeJavaScript($smiley_container) ?>);
			});
		</script>