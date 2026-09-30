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
use SMF\Profile;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for a user to edit/pick their subscriptions.
 */
?>

	<div id="paid_subscription">
		<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=subscriptions;confirm" method="post">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('subscriptions', file: 'ManagePaid') ?></h3>
			</div>
<?php if (empty(Utils::$context['subscriptions'])): ?>
			<div class="information">
				<?= Lang::getTxt('paid_subs_none', file: 'ManagePaid') ?>

			</div>
<?php else: ?>
			<div class="information">
				<?= Lang::getTxt('paid_subs_desc', file: 'ManagePaid') ?>

			</div><?php /* Print out all the subscriptions. */ ?>

<?php foreach (Utils::$context['subscriptions'] as $id => $subscription): ?>
<?php /* Ignore the inactive ones... */ ?>
<?php if (empty($subscription['active'])): ?>
<?php continue; ?>
<?php endif; ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= $subscription['name'] ?></h3>
			</div>
			<div class="windowbg">
				<p><strong><?= $subscription['name'] ?></strong></p>
				<p class="smalltext"><?= $subscription['desc'] ?></p>
<?php if (!$subscription['flexible']): ?>
				<div><strong><?= Lang::getTxt('paid_duration', file: 'ManagePaid') ?></strong> <?= $subscription['length'] ?></div>
<?php endif; ?>
<?php if (Profile::$member->is_me): ?>
				<strong><?= Lang::getTxt('paid_cost', file: 'ManagePaid') ?></strong>
<?php if ($subscription['flexible']): ?>
				<select name="cur[<?= $subscription['id'] ?>]"><?php /* Print out the costs for this one. */ ?>

<?php foreach ($subscription['costs'] as $duration => $value): ?>
					<option value="<?= $duration ?>"><?= sprintf(Config::$modSettings['paid_currency_symbol'], $value) ?>/<?= Lang::getTxt($duration, file: 'ManagePaid') ?></option>
<?php endforeach; ?>
				</select>
<?php else: ?>
				<?= sprintf(Config::$modSettings['paid_currency_symbol'], $subscription['costs']['fixed']) ?>

<?php endif; ?>
				<hr>
				<input type="submit" name="sub_id[<?= $subscription['id'] ?>]" value="<?= Lang::getTxt('paid_order', file: 'ManagePaid') ?>" class="button">
<?php else: ?>
				<a href="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modifyuser;sid=<?= $subscription['id'] ?>;uid=<?= Utils::$context['member']['id'] ?><?= (empty(Utils::$context['current'][$subscription['id']]) ? '' : ';lid=' . Utils::$context['current'][$subscription['id']]['id']) ?>"><?= Lang::getTxt(empty(Utils::$context['current'][$subscription['id']]) ? 'paid_admin_add' : 'paid_edit_subscription', file: 'ManagePaid') ?></a>
<?php endif; ?>
			</div><!-- .windowbg -->
<?php endforeach; ?>
<?php endif; ?>
		</form>
		<br class="clear">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('paid_current', file: 'ManagePaid') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('paid_current_desc', file: 'ManagePaid') ?>

		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th style="width: 30%"><?= Lang::getTxt('paid_name', file: 'ManagePaid') ?></th>
					<th><?= Lang::getTxt('paid_status', file: 'ManagePaid') ?></th>
					<th><?= Lang::getTxt('start_date', file: 'ManagePaid') ?></th>
					<th><?= Lang::getTxt('end_date', file: 'ManagePaid') ?></th>
				</tr>
			</thead>
			<tbody>
<?php if (empty(Utils::$context['current'])): ?>
				<tr class="windowbg">
					<td colspan="4">
						<?= Lang::getTxt('paid_none_yet', file: 'ManagePaid') ?>

					</td>
				</tr>
<?php endif; ?>
<?php foreach (Utils::$context['current'] as $sub): ?>
<?php if (!$sub['hide']): ?>
				<tr class="windowbg">
					<td>
						<?= (User::$me->is_admin ? '<a href="' . Config::$scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $sub['id'] . '">' . $sub['name'] . '</a>' : $sub['name']) ?>

					</td>
					<td>
						<span style="color: <?= ($sub['status'] == 2 ? 'green' : ($sub['status'] == 1 ? 'red' : 'orange')) ?>"><strong><?= $sub['status_text'] ?></strong></span>
					</td>
					<td><?= $sub['start'] ?></td>
					<td><?= $sub['end'] ?></td>
				</tr>
<?php endif; ?>
<?php endforeach; ?>
			</tbody>
		</table>
	</div><!-- #paid_subscription -->