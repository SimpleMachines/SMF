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
 * The "thank you" bit...
 */
?>
	<div id="paid_subscription">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('paid_done', file: 'ManagePaid') ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('paid_done_desc', file: 'ManagePaid') ?></p>
			<br>
			<a href="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['member']['id'] ?>;area=subscriptions"><?= Lang::getTxt('paid_sub_return', file: 'ManagePaid') ?></a>
		</div>
	</div>