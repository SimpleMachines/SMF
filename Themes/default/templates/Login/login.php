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
 * This is just the basic "login" form.
 */
?>

		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> <?= Lang::getTxt('login', file: 'General') ?>

				</h3>
			</div>
			<div class="roundframe">
				<form class="login" action="<?= Utils::$context['login_url'] ?>" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8"<?php
// A plain submit is enough when the response is going to come back to the
// page it was sent from. Anywhere else, login.js has to take the form over,
// and this attribute is how it knows to.
?><?php if (!empty(Utils::$context['from_ajax']) && (empty(Config::$modSettings['allow_cors']) || empty(Config::$modSettings['allow_cors_credentials']) || empty(Utils::$context['valid_cors_found']) || !in_array(Utils::$context['valid_cors_found'], ['same', 'subdomain']))): ?> data-ajax-login="<?= Utils::$context['valid_cors_found'] ?? '' ?>"<?php endif; ?>><?php /* Did they make a mistake last time? */ ?><?php if (!empty(Utils::$context['login_errors'])): ?>

					<div class="errorbox"><?= implode('<br>', Utils::$context['login_errors']) ?></div>
					<br><?php endif; ?><?php /* Or perhaps there's some special description for this time? */ ?><?php if (isset(Utils::$context['description'])): ?>

					<div class="information"><?= Utils::$context['description'] ?></div><?php endif; ?><?php /* Now just get the basic information - username, password, etc. */ ?>

					<dl>
						<dt><?= Lang::getTxt('username', file: 'General') ?></dt>
						<dd>
							<input type="text" id="<?= !empty(Utils::$context['from_ajax']) ? 'ajax_' : '' ?>loginuser" name="user" size="20" value="<?= Utils::$context['default_username'] ?>" required>
						</dd>
						<dt><?= Lang::getTxt('password', file: 'General') ?></dt>
						<dd>
							<input type="password" id="<?= !empty(Utils::$context['from_ajax']) ? 'ajax_' : '' ?>loginpass" name="passwrd" value="<?= Utils::$context['default_password'] ?>" size="20" required>
						</dd>
					</dl>
					<dl>
						<dt></dt>
						<dd>
							<label>
								<input type="checkbox" name="cookieneverexp"<?= !empty(Utils::$context['never_expire']) ? ' checked' : '' ?>>
								<?= Lang::getTxt('remember_me', file: 'General') ?>

							</label>
						</dd><?php /* If they have deleted their account, give them a chance to change their mind. */ ?><?php if (isset(Utils::$context['login_show_undelete'])): ?>

						<dt class="alert"><?= Lang::getTxt('undelete_account', file: 'Login') ?></dt>
						<dd><input type="checkbox" name="undelete"></dd><?php endif; ?>

					</dl>
					<p>
						<input type="submit" value="<?= Lang::getTxt('login', file: 'General') ?>" class="button">
					</p>
					<p class="smalltext">
						<a href="<?= Config::$scripturl ?>?action=reminder"><?= Lang::getTxt('forgot_your_password', file: 'General') ?></a>
					</p><?php if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1): ?>

					<p class="smalltext">
						<?= Lang::getTxt('welcome_guest_activate', ['scripturl' => Config::$scripturl], file: 'General') ?>

					</p><?php endif; ?>

					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="<?= Utils::$context['login_token_var'] ?>" value="<?= Utils::$context['login_token'] ?>">
					<script>
						setTimeout(function() {
							document.getElementById("<?= !empty(Utils::$context['from_ajax']) ? 'ajax_' : '' ?><?= isset(Utils::$context['default_username']) && Utils::$context['default_username'] != '' ? 'loginpass' : 'loginuser' ?>").focus();
						}, 150);
					</script>
				</form><?php if (!empty(Utils::$context['can_register'])): ?>

				<hr>
				<div class="centertext">
					<?= Lang::getTxt('register_prompt', ['scripturl' => Config::$scripturl], file: 'General') ?>

				</div><?php endif; ?><?php /* It is a long story as to why we have this when we're clearly not going to use it. */ ?><?php if (!empty(Utils::$context['from_ajax'])): ?>

				<br>
				<a href="javascript:self.close();"></a><?php endif; ?>

			</div><!-- .roundframe -->
		</div><!-- .login -->