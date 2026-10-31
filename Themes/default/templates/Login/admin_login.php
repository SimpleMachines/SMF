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
 * This is for the security stuff - makes administrators login every so often.
 */
?><?php /* Since this should redirect to whatever they were doing, send all the get data. */ ?>
	<form action="<?= !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl ?><?= Utils::$context['get_data'] ?>" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin">
		<div class="login" id="admin_login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> <?= Lang::getTxt('login', file: 'General') ?>
				</h3>
			</div>
			<div class="roundframe centertext"><?php if (!empty(Utils::$context['incorrect_password'])): ?>
				<div class="error"><?= Lang::getTxt('admin_incorrect_password', file: 'Admin') ?></div><?php endif; ?>
				<strong><?= Lang::getTxt('password', file: 'General') ?></strong>
				<input type="password" name="<?= Utils::$context['sessionCheckType'] ?>_pass" size="24">
				<a href="<?= Config::$scripturl ?>?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a><br>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-login_token_var'] ?>" value="<?= Utils::$context['admin-login_token'] ?>">
				<input type="submit" value="<?= Lang::getTxt('login', file: 'General') ?>" class="button"><?php /* Make sure to output all the old post data. */ ?><?= Utils::$context['post_data'] ?>
			</div><!-- .roundframe -->
		</div><!-- #admin_login -->
		<input type="hidden" name="<?= Utils::$context['sessionCheckType'] ?>_hash_pass" value="">
	</form><?php /* Focus on the password box. */ ?>
	<script>
		document.forms.frmLogin.<?= Utils::$context['sessionCheckType'] ?>_pass.focus();
	</script>