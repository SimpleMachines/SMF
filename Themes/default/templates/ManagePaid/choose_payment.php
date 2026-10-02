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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The "choose payment" dialog.
 */
?>
	<div id="paid_subscription">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('paid_confirm_payment', file: 'ManagePaid') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('paid_confirm_desc', file: 'ManagePaid') ?>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><?= Lang::getTxt('subscription', file: 'ManagePaid') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['sub']['name'] ?>
				</dd>
				<dt>
					<strong><?= Lang::getTxt('paid_cost', file: 'ManagePaid') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['cost'] ?>
				</dd>
			</dl>
		</div>
<?php /* Do all the gateway options. */ ?>
<?php foreach (Utils::$context['gateways'] as $gateway): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= $gateway['title'] ?></h3>
		</div>
		<div class="windowbg">
			<?= $gateway['desc'] ?><br>
			<form action="<?= $gateway['form'] ?>" method="post">
<?php if (!empty($gateway['javascript'])): ?>
				<script>
					<?= $gateway['javascript'] ?>

				</script>
<?php endif; ?>
<?php foreach ($gateway['hidden'] as $name => $value): ?>
				<input type="hidden" id="<?= $gateway['id'] ?>_<?= $name ?>" name="<?= $name ?>" value="<?= $value ?>">
<?php endforeach; ?>
				<br>
				<input type="submit" value="<?= $gateway['submit'] ?>" class="button">
			</form>
		</div>
<?php endforeach; ?>
	</div><!-- #paid_subscription -->
	<br class="clear">