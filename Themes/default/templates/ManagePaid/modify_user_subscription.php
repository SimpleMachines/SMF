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
 * Add or edit an existing subscriber.
 */
?>

<?php
// The year lists below have to reach whatever dates this subscription
// already carries, however far out they are, and leave room to pick
// something new either side of today.
$years = [
	Utils::$context['sub']['start']['year'],
	Utils::$context['sub']['end']['year'],
	(int) date('Y'),
];
$first_year = min($years) - 10;
$last_year = max($years) + 10;
?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modifyuser;sid=<?= Utils::$context['sub_id'] ?>;lid=<?= Utils::$context['log_id'] ?>" method="post">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt(
					'paid_' . Utils::$context['action_type'] . '_subscription_name' . (empty(Utils::$context['sub']['username']) ? '' : '_for_member'),
					[
						'name' => Utils::$context['current_subscription']['name'],
						'member' => Utils::$context['sub']['username'] ?? '',
					],
					file: 'ManagePaid',
				) ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
<?php /* Do we need a username? */ ?>
<?php if (Utils::$context['action_type'] == 'add'): ?>
				<dt>
					<strong><?= Lang::getTxt('paid_username', file: 'ManagePaid') ?></strong><br>
					<span class="smalltext"><?= Lang::getTxt('one_username', file: 'ManagePaid') ?></span>
				</dt>
				<dd>
					<input type="text" name="name" id="name_control" value="<?= Utils::$context['sub']['username'] ?>" size="30">
				</dd>
<?php endif; ?>
				<dt>
					<strong><?= Lang::getTxt('paid_status', file: 'ManagePaid') ?></strong>
				</dt>
				<dd>
					<select name="status">
						<option value="0"<?= Utils::$context['sub']['status'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('paid_finished', file: 'ManagePaid') ?></option>
						<option value="1"<?= Utils::$context['sub']['status'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('paid_active', file: 'ManagePaid') ?></option>
					</select>
				</dd>
			</dl>
			<fieldset>
				<legend><?= Lang::getTxt('start_date_and_time', file: 'ManagePaid') ?></legend>
				<select name="year" id="year">
<?php /* Show a list of all the years we allow... */ ?>
<?php for ($year = $first_year; $year <= $last_year; $year++): ?>
					<option value="<?= $year ?>"<?= $year == Utils::$context['sub']['start']['year'] ? ' selected' : '' ?>><?= $year ?></option>
<?php endfor; ?>
				</select>
				<select name="month" id="month">
<?php /* There are 12 months per year - ensure that they all get listed. */ ?>
<?php for ($month = 1; $month <= 12; $month++): ?>
					<option value="<?= $month ?>"<?= $month == Utils::$context['sub']['start']['month'] ? ' selected' : '' ?>><?= Lang::getTxt(['months', $month], file: 'General') ?></option>
<?php endfor; ?>
				</select>
				<select name="day" id="day">
<?php /* This prints out all the days in the current month - this changes dynamically as we switch months. */ ?>
<?php for ($day = 1; $day <= Utils::$context['sub']['start']['last_day']; $day++): ?>
					<option value="<?= $day ?>"<?= $day == Utils::$context['sub']['start']['day'] ? ' selected' : '' ?>><?= $day ?></option>
<?php endfor; ?>
				</select>
				&nbsp;&nbsp;&nbsp;
				<input type="number" name="hour" value="<?= Utils::$context['sub']['start']['hour'] ?>" size="2" min="0" max="23">
				:
				<input type="number" name="minute" value="<?= Utils::$context['sub']['start']['min'] ?>" size="2" min="0" max="59">
			</fieldset>
			<fieldset>
				<legend><?= Lang::getTxt('end_date_and_time', file: 'ManagePaid') ?></legend>
				<select name="yearend" id="yearend">
<?php /* Show a list of all the years we allow... */ ?>
<?php for ($year = $first_year; $year <= $last_year; $year++): ?>
					<option value="<?= $year ?>"<?= $year == Utils::$context['sub']['end']['year'] ? ' selected' : '' ?>><?= $year ?></option>
<?php endfor; ?>
				</select>
				<select name="monthend" id="monthend">
<?php /* There are 12 months per year - ensure that they all get listed. */ ?>
<?php for ($month = 1; $month <= 12; $month++): ?>
					<option value="<?= $month ?>"<?= $month == Utils::$context['sub']['end']['month'] ? ' selected' : '' ?>><?= Lang::getTxt(['months', $month], file: 'General') ?></option>
<?php endfor; ?>
				</select>
				<select name="dayend" id="dayend">
<?php /* This prints out all the days in the current month - this changes dynamically as we switch months. */ ?>
<?php for ($day = 1; $day <= Utils::$context['sub']['end']['last_day']; $day++): ?>
					<option value="<?= $day ?>"<?= $day == Utils::$context['sub']['end']['day'] ? ' selected' : '' ?>><?= $day ?></option>
<?php endfor; ?>
				</select>
				&nbsp;&nbsp;&nbsp;
				<input type="number" name="hourend" value="<?= Utils::$context['sub']['end']['hour'] ?>" size="2" min="0" max="23">
				:
				<input type="number" name="minuteend" value="<?= Utils::$context['sub']['end']['min'] ?>" size="2" min="0" max="59">
			</fieldset>
			<input type="submit" name="save_sub" value="<?= Lang::getTxt('paid_settings_save', file: 'ManagePaid') ?>" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'name_subscriber',
			sControlId: 'name_control',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
		});
	</script>
<?php if (!empty(Utils::$context['pending_payments'])): ?>
	<div class="cat_bar">
		<h3 class="catbg"><?= Lang::getTxt('pending_payments', file: 'ManagePaid') ?></h3>
	</div>
	<div class="information">
		<?= Lang::getTxt('pending_payments_desc', file: 'ManagePaid') ?>

	</div>
	<div class="cat_bar">
		<h3 class="catbg"><?= Lang::getTxt('pending_payments_value', file: 'ManagePaid') ?></h3>
	</div>
	<div class="windowbg">
		<ul>
<?php foreach (Utils::$context['pending_payments'] as $id => $payment): ?>
			<li>
				<?= $payment['desc'] ?>

				<span class="floatleft">
					<a href="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modifyuser;lid=<?= Utils::$context['log_id'] ?>;pending=<?= $id ?>;accept"><?= Lang::getTxt('pending_payments_accept', file: 'ManagePaid') ?></a>
				</span>
				<span class="floatright">
					<a href="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modifyuser;lid=<?= Utils::$context['log_id'] ?>;pending=<?= $id ?>;remove"><?= Lang::getTxt('pending_payments_remove', file: 'ManagePaid') ?></a>
				</span>
			</li>
<?php endforeach; ?>
		</ul>
<?php endif; ?>
	</div>