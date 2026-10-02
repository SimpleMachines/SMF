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
use SMF\Profile;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for disabling two-factor authentication.
 */
?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('tfadisable', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<form action="<?= Config::$scripturl ?>?action=profile;area=tfadisable" method="post">
<?php if (Profile::$member->is_me): ?>
					<div class="block">
						<strong<?= (isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '') ?>><?= Lang::getTxt('current_password', file: 'Profile') ?></strong><br>
						<input type="password" name="oldpasswrd" size="20">
					</div>
<?php else: ?>
					<div class="smalltext">
						<?= Lang::getTxt('tfa_disable_for_user', ['name' => User::$me->name], file: 'Profile') ?>
					</div>
<?php endif; ?>
					<input type="submit" name="save" value="<?= Lang::getTxt('tfa_disable', file: 'Profile') ?>" class="button floatright">
					<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
				</form>
			</div><!-- .roundframe -->