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

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Redirection topic options
 *
 * @param string $type What type of topic this is for - currently 'merge' or 'move'. Used to display appropriate text strings...
 */
?>
					<label for="postRedirect" class="block">
						<input type="checkbox" name="postRedirect" id="postRedirect"<?= Utils::$context['is_approved'] ? ' checked' : '' ?> onclick="<?= Utils::$context['is_approved'] ? '' : 'if (this.checked && !confirm(\'' . Lang::getTxt($type . '_topic_unapproved_js', file: 'General') . '\')) return false; ' ?>document.getElementById('reasonArea').classList.toggle('hidden');"> <?= Lang::getTxt('post_redirection', file: 'General') ?>
					</label>
					<fieldset id="reasonArea"<?= Utils::$context['is_approved'] ? '' : 'class="hidden"' ?>>
						<dl class="settings">
							<dt>
								<?= Lang::getTxt($type . '_why', file: 'General') ?>
							</dt>
							<dd>
								<textarea name="reason"><?= Lang::getTxt($type . 'topic_default', ['board_link' => Lang::getTxt('movetopic_auto_board', file: 'General'), 'topic_link' => Lang::getTxt('movetopic_auto_topic', file: 'General')]) ?></textarea>
							</dd>
							<dt>
								<label for="redirect_topic"><?= Lang::getTxt($type . 'topic_redirect', file: 'General') ?></label>
							</dt>
							<dd>
								<input type="checkbox" name="redirect_topic" id="redirect_topic" checked>
							</dd>
<?php if (!empty(Config::$modSettings['allow_expire_redirect'])): ?>
							<dt>
								<?= Lang::getTxt('redirect_topic_expires', file: 'General') ?>
							</dt>
							<dd>
								<select name="redirect_expires">
									<option value="0"><?= Lang::getTxt('never', file: 'General') ?></option>
									<option value="1440"><?= Lang::getTxt('one_day', file: 'General') ?></option>
									<option value="10080" selected><?= Lang::getTxt('one_week', file: 'General') ?></option>
									<option value="20160"><?= Lang::getTxt('two_weeks', file: 'General') ?></option>
									<option value="43200"><?= Lang::getTxt('one_month', file: 'General') ?></option>
									<option value="86400"><?= Lang::getTxt('two_months', file: 'General') ?></option>
								</select>
							</dd>
<?php else: ?>
							<input type="hidden" name="redirect_expires" value="0">
<?php endif; ?>
						</dl>
					</fieldset>