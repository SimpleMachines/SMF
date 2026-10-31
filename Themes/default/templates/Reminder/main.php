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
 * The main "Here's how you can reset your password" page
 */
?>
	<br>
	<form action="<?= Config::$scripturl ?>?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('authentication_reminder', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<p class="smalltext centertext"><?= Lang::getTxt('password_reminder_desc', file: 'Profile') ?></p>
				<dl>
					<dt><label for="user"><?= Lang::getTxt('user_email', file: 'Profile') ?></label></dt>
					<dd><input type="text" name="user" id="user" size="30"></dd>
				</dl>
				<input type="submit" value="<?= Lang::getTxt('reminder_continue', file: 'Profile') ?>" class="button">
				<br class="clear">
			</div>
		</div>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		<input type="hidden" name="<?= Utils::$context['remind_token_var'] ?>" value="<?= Utils::$context['remind_token'] ?>">
	</form>