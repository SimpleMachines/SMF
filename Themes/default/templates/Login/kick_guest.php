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
 * Tell a guest to get lost or login!
 */
?><?php /* This isn't that much... just like normal login but with a message at the top. */ ?>
	<form action="<?= Utils::$context['login_url'] ?>" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin">
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('warning', file: 'Login') ?></h3>
			</div><?php /* Show the message or default message. */ ?>
			<p class="information centertext">
				<?= empty(Utils::$context['kick_message']) ? Lang::getTxt('only_members_can_access', file: 'Login') : Utils::$context['kick_message'] ?><br><?php if (Utils::$context['can_register']): ?><?= Lang::getTxt('login_below_or_register', ['url' => Config::$scripturl . '?action=signup', 'forum_name' => Utils::$context['forum_name_html_safe']], file: 'Login') ?><?php else: ?><?= Lang::getTxt('login_below', file: 'Login') ?><?php endif; ?><?php /* And now the login information. */ ?>
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> <?= Lang::getTxt('login', file: 'General') ?>
				</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt><?= Lang::getTxt('username', file: 'General') ?></dt>
					<dd><input type="text" name="user" size="20"></dd>
					<dt><?= Lang::getTxt('password', file: 'General') ?></dt>
					<dd><input type="password" name="passwrd" size="20"></dd>
					<dt></dt>
					<dd>
						<label>
							<input type="checkbox" name="cookieneverexp"<?= !empty(Utils::$context['never_expire']) ? ' checked' : '' ?>>
							<?= Lang::getTxt('remember_me', file: 'General') ?>
						</label>
					</dd>
				</dl>
				<p class="centertext">
					<input type="submit" value="<?= Lang::getTxt('login', file: 'General') ?>" class="button">
				</p>
				<p class="centertext smalltext">
					<a href="<?= Config::$scripturl ?>?action=reminder"><?= Lang::getTxt('forgot_your_password', file: 'General') ?></a>
				</p>
			</div>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['login_token_var'] ?>" value="<?= Utils::$context['login_token'] ?>">
		</div><!-- .login -->
	</form><?php /* Do the focus thing... */ ?>
	<script>
		document.forms.frmLogin.user.focus();
	</script>