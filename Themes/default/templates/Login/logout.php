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
 * Confirm a logout.
 */
?>

<?php /* This isn't that much... just like normal login but with a message at the top. */ ?>
	<form action="<?= Config::$scripturl ?>?action=logout;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8" name="frmLogout" id="frmLogout">
		<div class="logout">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('logout_confirm', file: 'Login') ?></h3>
			</div>
			<div class="roundframe">
				<p class="information centertext">
					<?= Lang::getTxt('logout_notice', file: 'Login') ?>
				</p>

				<p class="centertext">
					<input type="submit" value="<?= Lang::getTxt('logout', file: 'General') ?>" class="button">
					<input type="submit" name="cancel" value="<?= Lang::getTxt('logout_return', file: 'Login') ?>" class="button">
				</p>
			</div>
		</div><!-- .logout -->
	</form>