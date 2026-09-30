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
 * The page for deleting a subscription.
 */
?>

	<form action="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modify;sid=<?= Utils::$context['sub_id'] ?>;delete" method="post">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('paid_delete_subscription', file: 'ManagePaid') ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('paid_mod_delete_warning', file: 'ManagePaid') ?></p>
			<input type="submit" name="delete_confirm" value="<?= Lang::getTxt('paid_delete_subscription', file: 'ManagePaid') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-pmsd_token_var'] ?>" value="<?= Utils::$context['admin-pmsd_token'] ?>">
		</div>
	</form>