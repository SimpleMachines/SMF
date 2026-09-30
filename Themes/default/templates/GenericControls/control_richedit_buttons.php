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
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Renders the buttons section below a rich-text editor.
 *
 * Displays form buttons such as "Post" or "Preview" and integrates
 * functionality like auto-saving drafts if enabled.
 *
 * @param string $editor_id The unique ID of the editor for which buttons are displayed.
 */
?><?php $editor_context = Editor::$loaded[$editor_id]; ?>

		<span class="smalltext">
			<?= Utils::$context['shortcuts_text'] ?>

		</span>
		<span class="post_button_container"><?php foreach (Utils::$context['richedit_buttons'] as $name => $button): ?><?php if ($name == 'preview'): ?><?php
$button['value'] = $editor_context['labels']['preview_button'] ?? $button['value'];
$button['show'] = $editor_context['preview_type'];
?><?php endif; ?><?php if ($button['show']): ?>

		<input type="<?= $button['type'] ?>"<?= $button['type'] == 'hidden' ? ' id="' . $name . '"' : '' ?> name="<?= $name ?>" value="<?= $button['value'] ?>"<?= !empty($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '' ?><?= !empty($button['accessKey']) ? ' accesskey="' . $button['accessKey'] . '"' : '' ?><?= $button['type'] != 'hidden' ? ' class="button"' : '' ?>><?php endif; ?><?php endforeach; ?>

		<input type="submit" value="<?= $editor_context['labels']['post_button'] ?? Lang::getTxt('post', file: 'General') ?>" name="post" onclick="return submitThisOnce(this);" accesskey="s" class="button">
		</span><?php /* Include auto-save feature if drafts are enabled. */ ?><?php if (!empty(Utils::$context['drafts_save']) && !empty(Utils::$context['drafts_autosave'])): ?>

		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="<?= Theme::$current->settings['images_url'] ?>/loading_sm.gif" alt="" class="centericon"></span>
			<span id="draft_lastautosave"></span>
		</span><?php endif; ?>
