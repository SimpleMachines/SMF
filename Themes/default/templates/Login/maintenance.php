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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This is for maintenance mode.
 */
?>

<?php /* Display the administrator's message at the top. */ ?>
	<form action="<?= Utils::$context['login_url'] ?>" method="post" accept-charset="UTF-8">
		<div class="login" id="maintenance_mode">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['title'] ?></h3>
			</div>
			<div class="information">
				<img class="floatleft" src="<?= Theme::$current->settings['images_url'] ?>/construction.png" width="40" height="40" alt="<?= Lang::getTxt('in_maintain_mode', file: 'Login') ?>">
				<?= Utils::$context['description'] ?><br class="clear">
			</div>
			<div class="title_bar">
				<h4 class="titlebg"><?= Lang::getTxt('admin_login', file: 'General') ?></h4>
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
				<input type="submit" value="<?= Lang::getTxt('login', file: 'General') ?>" class="button">
				<br class="clear">
			</div>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['login_token_var'] ?>" value="<?= Utils::$context['login_token'] ?>">
		</div><!-- #maintenance_mode -->
	</form>