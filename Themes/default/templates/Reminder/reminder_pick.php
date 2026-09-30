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
 * The page to pick an option - secret question/answer (if set) or email
 */
?>

	<br>
	<form action="<?= Config::$scripturl ?>?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('authentication_reminder', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<p><strong><?= Lang::getTxt('authentication_options', file: 'Profile') ?></strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked></dt>
					<label for="reminder_type_email"><?= Lang::getTxt('authentication_password_email', file: 'Profile') ?></label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret">
					<label for="reminder_type_secret"><?= Lang::getTxt('authentication_password_secret', file: 'Profile') ?></label>
				</p>
				<div class="flow_auto">
					<input type="submit" value="<?= Lang::getTxt('reminder_continue', file: 'Profile') ?>" class="button">
					<input type="hidden" name="uid" value="<?= Utils::$context['current_member']['id'] ?>">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="<?= Utils::$context['remind_token_var'] ?>" value="<?= Utils::$context['remind_token'] ?>">
				</div>
			</div><!-- .roundframe -->
		</div><!-- .login -->
	</form>