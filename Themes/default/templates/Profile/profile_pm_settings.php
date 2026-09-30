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
 * Personal Message settings.
 */
?>
					<dt>
						<label for="pm_prefs"><?= Lang::getTxt('pm_display_mode', file: 'Profile') ?></label>
					</dt>
					<dd>
						<select name="pm_prefs" id="pm_prefs">
							<option value="0"<?= Utils::$context['display_mode'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('pm_display_mode_all', file: 'Profile') ?></option>
							<option value="1"<?= Utils::$context['display_mode'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('pm_display_mode_one', file: 'Profile') ?></option>
							<option value="2"<?= Utils::$context['display_mode'] == 2 ? ' selected' : '' ?>><?= Lang::getTxt('pm_display_mode_linked', file: 'Profile') ?></option>
						</select>
					</dd>
					<dt>
						<label for="view_newest_pm_first"><?= Lang::getTxt('recent_pms_at_top', file: 'Profile') ?></label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[view_newest_pm_first]" value="0">
						<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"<?= !empty(Utils::$context['member']['options']['view_newest_pm_first']) ? ' checked' : '' ?>>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="pm_receive_from"><?= Lang::getTxt('pm_receive_from', file: 'Profile') ?></label>
					</dt>
					<dd>
						<select name="pm_receive_from" id="pm_receive_from">
							<option value="0"<?= empty(Utils::$context['receive_from']) || (empty(Config::$modSettings['enable_buddylist']) && Utils::$context['receive_from'] < 3) ? ' selected' : '' ?>><?= Lang::getTxt('pm_receive_from_everyone', file: 'Profile') ?></option>
<?php if (!empty(Config::$modSettings['enable_buddylist'])): ?>
							<option value="1"<?= !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('pm_receive_from_ignore', file: 'Profile') ?></option>
							<option value="2"<?= !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] == 2 ? ' selected' : '' ?>><?= Lang::getTxt('pm_receive_from_buddies', file: 'Profile') ?></option>
<?php endif; ?>
							<option value="3"<?= !empty(Utils::$context['receive_from']) && Utils::$context['receive_from'] > 2 ? ' selected' : '' ?>><?= Lang::getTxt('pm_receive_from_admins', file: 'Profile') ?></option>
						</select>
					</dd>
					<dt>
						<label for="popup_messages"><?= Lang::getTxt('popup_messages', file: 'Profile') ?></label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[popup_messages]" value="0">
						<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"<?= !empty(Utils::$context['member']['options']['popup_messages']) ? ' checked' : '' ?>>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="pm_remove_inbox_label"><?= Lang::getTxt('pm_remove_inbox_label', file: 'Profile') ?></label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
						<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"<?= !empty(Utils::$context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '' ?>>
					</dd>