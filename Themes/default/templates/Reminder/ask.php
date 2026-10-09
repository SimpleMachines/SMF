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
 * The page that asks a user to answer their secret question
 */
?>
	<br>
	<form action="<?= Config::$scripturl ?>?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('authentication_reminder', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<p class="smalltext"><?= Lang::getTxt('enter_new_password', file: 'Profile') ?></p>
				<dl>
					<dt><?= Lang::getTxt('secret_question', file: 'Profile') ?></dt>
					<dd><?= Utils::$context['secret_question'] ?></dd>
					<dt><?= Lang::getTxt('secret_answer', file: 'Profile') ?></dt>
					<dd><input type="text" name="secret_answer" size="22"></dd>
					<dt><?= Lang::getTxt('choose_pass', file: 'General') ?></dt>
					<dd>
						<input type="password" name="passwrd1" data-autov="pwmain" size="22">
					</dd>
					<dt><?= Lang::getTxt('verify_pass', file: 'General') ?></dt>
					<dd>
						<input type="password" name="passwrd2" data-autov="pwverify" size="22">
					</dd>
				</dl>
				<div class="auto_flow">
					<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
					<input type="hidden" name="uid" value="<?= Utils::$context['remind_user'] ?>">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="<?= Utils::$context['remind-sai_token_var'] ?>" value="<?= Utils::$context['remind-sai_token'] ?>">
				</div>
			</div><!-- .roundframe -->
		</div><!-- .login -->
	</form>
	<script>
		var regTextStrings = {
			"password_short": "<?= Lang::getTxt('registration_password_short', file: 'Login') ?>",
			"password_reserved": "<?= Lang::getTxt('registration_password_reserved', file: 'Login') ?>",
			"password_numbercase": "<?= Lang::getTxt('registration_password_numbercase', file: 'Login') ?>",
			"password_no_match": "<?= Lang::getTxt('registration_password_no_match', file: 'Login') ?>",
			"password_valid": "<?= Lang::getTxt('registration_password_valid', file: 'Login') ?>"
		};
		var verificationHandle = new smfRegister("creator", <?= empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'] ?>, regTextStrings);
	</script>