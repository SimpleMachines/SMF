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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for the password box/save button stuck at the bottom of every profile page.
 */
?>


					<hr>
<?php /* Only show the password box if it's actually needed. */ ?>
<?php if (Utils::$context['require_password']): ?>
					<dl class="settings">
						<dt>
							<strong<?= isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '' ?>><?= Lang::getTxt('current_password', file: 'Profile') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('required_security_reasons', file: 'Profile') ?></span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20">
						</dd>
					</dl>
<?php endif; ?>
					<div class="righttext">
<?php if (!empty(Utils::$context['token_check'])): ?>
						<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
<?php endif; ?>
						<input type="submit" value="<?= Lang::getTxt('change_profile', file: 'Profile') ?>" class="button">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
						<input type="hidden" name="sa" value="<?= Utils::$context['menu_item_selected'] ?>">
					</div>