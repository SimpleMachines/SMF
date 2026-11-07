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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Show the signature editing box?
 */
?>
							<dt id="current_signature" style="display:none">
								<strong><?= Lang::getTxt('current_signature', file: 'Profile') ?></strong>
							</dt>
							<dd id="current_signature_display" style="display:none">
								<hr>
							</dd>

							<dt id="preview_signature" style="display:none">
								<strong><?= Lang::getTxt('signature_preview', file: 'Profile') ?></strong>
							</dt>
							<dd id="preview_signature_display" style="display:none">
								<hr>
							</dd>

							<dt>
								<label for="signature"><strong><?= Lang::getTxt('signature', file: 'Profile') ?></strong></label><br>
								<span class="smalltext"><?= Lang::getTxt('sig_info', file: 'Profile') ?></span><br>
								<br>
<?php if (Utils::$context['show_spellchecking']): ?>
								<input type="button" value="<?= Lang::getTxt('spell_check', file: 'General') ?>" onclick="spellCheck('creator', 'signature');" class="button">
<?php endif; ?>
							</dt>
							<dd>
								<textarea class="editor" id="signature" name="signature" rows="5" cols="50"<?= empty(Utils::$context['signature_limits']['max_length']) ? '' : ' data-max-length="' . (int) Utils::$context['signature_limits']['max_length'] . '"' ?>><?= Utils::$context['member']['signature'] ?></textarea><br>
<?php /* If there is a limit at all! */ ?>
<?php if (!empty(Utils::$context['signature_limits']['max_length'])): ?>
								<span class="smalltext"><?= Lang::getTxt('max_sig_characters', Utils::$context['signature_limits'], file: 'Profile') ?></span><br>
<?php endif; ?>
<?php if (!empty(Utils::$context['show_preview_button'])): ?>
								<input type="button" name="preview_signature" id="preview_button" value="<?= Lang::getTxt('preview_signature', file: 'Profile') ?>" class="button floatright">
<?php endif; ?>
<?php if (Utils::$context['signature_warning']): ?>
								<span class="smalltext"><?= Utils::$context['signature_warning'] ?></span>
<?php endif; ?>
							</dd>