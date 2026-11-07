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
 * Template for setting the new password
 */
?>
	<br>
	<form action="<?= Config::$scripturl ?>?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt><?= Lang::getTxt('choose_pass', file: 'General') ?></dt>
					<dd>
						<input type="password" name="passwrd1" data-autov="pwmain" size="22">
					</dd>
					<dt><?= Lang::getTxt('verify_pass', file: 'General') ?></dt>
					<dd>
						<input type="password" name="passwrd2" data-autov="pwverify" size="22">
					</dd>
				</dl>
				<p class="align_center">
					<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
				</p>
			</div><!-- .roundframe -->
		</div><!-- .login -->
		<input type="hidden" name="code" value="<?= Utils::$context['code'] ?>">
		<input type="hidden" name="u" value="<?= Utils::$context['memID'] ?>">
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		<input type="hidden" name="<?= Utils::$context['remind-sp_token_var'] ?>" value="<?= Utils::$context['remind-sp_token'] ?>">
	</form>
	<script>
		var regTextStrings = {
			"password_short": "<?= Lang::getTxt('registration_password_short', file: 'Login') ?>",
			"password_reserved": "<?= Lang::getTxt('registration_password_reserved', file: 'Login') ?>",
			"password_numbercase": "<?= Lang::getTxt('registration_password_numbercase', file: 'Login') ?>",
			"password_no_match": "<?= Lang::getTxt('registration_password_no_match', file: 'Login') ?>",
			"password_valid": "<?= Lang::getTxt('registration_password_valid', file: 'Login') ?>"
		};
		var verificationHandle = new smfRegister("reminder_form", <?= empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'] ?>, regTextStrings);
	</script>