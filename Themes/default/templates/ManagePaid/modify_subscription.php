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
 * The template for adding or editing a subscription.
 */
?>

	<form action="<?= Config::$scripturl ?>?action=admin;area=paidsubscribe;sa=modify;sid=<?= Utils::$context['sub_id'] ?>" method="post">
<?php if (!empty(Utils::$context['disable_groups'])): ?>
		<div class="noticebox"><?= Lang::getTxt('paid_mod_edit_note', file: 'ManagePaid') ?></div>
<?php endif; ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('paid_' . Utils::$context['action_type'] . '_subscription', file: 'ManagePaid') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<?= Lang::getTxt('paid_mod_name', file: 'ManagePaid') ?>

				</dt>
				<dd>
					<input type="text" name="name" value="<?= Utils::$context['sub']['name'] ?>" size="30">
				</dd>
				<dt>
					<?= Lang::getTxt('paid_mod_desc', file: 'ManagePaid') ?>

				</dt>
				<dd>
					<textarea name="desc" rows="3" cols="40"><?= Utils::$context['sub']['desc'] ?></textarea>
				</dd>
				<dt>
					<label for="repeatable_check"><?= Lang::getTxt('paid_mod_repeatable', file: 'ManagePaid') ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="repeatable" id="repeatable_check"<?= empty(Utils::$context['sub']['repeatable']) ? '' : ' checked' ?>>
				</dd>
				<dt>
					<label for="activated_check"><?= Lang::getTxt('paid_mod_active', file: 'ManagePaid') ?></label><br><span class="smalltext"><?= Lang::getTxt('paid_mod_active_desc', file: 'ManagePaid') ?></span>
				</dt>
				<dd>
					<input type="checkbox" name="active" id="activated_check"<?= empty(Utils::$context['sub']['active']) ? '' : ' checked' ?>>
				</dd>
			</dl>
			<hr>
			<dl class="settings">
				<dt>
					<?= Lang::getTxt('paid_mod_prim_group', file: 'ManagePaid') ?><br>
					<span class="smalltext"><?= Lang::getTxt('paid_mod_prim_group_desc', file: 'ManagePaid') ?></span>
				</dt>
				<dd>
					<select name="prim_group"<?= !empty(Utils::$context['disable_groups']) ? ' disabled' : '' ?>>
						<option value="0"<?= Utils::$context['sub']['prim_group'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('paid_mod_no_group', file: 'ManagePaid') ?></option>
<?php /* Put each group into the box. */ ?>
<?php foreach (Utils::$context['groups'] as $id => $name): ?>
						<option value="<?= $id ?>"<?= Utils::$context['sub']['prim_group'] == $id ? ' selected' : '' ?>><?= $name ?></option>
<?php endforeach; ?>
					</select>
				</dd>
				<dt>
					<?= Lang::getTxt('paid_mod_add_groups', file: 'ManagePaid') ?>:<br>
					<span class="smalltext"><?= Lang::getTxt('paid_mod_add_groups_desc', file: 'ManagePaid') ?></span>
				</dt>
				<dd>
<?php /* Put a checkbox in for each group */ ?>
<?php foreach (Utils::$context['groups'] as $id => $name): ?>
					<label for="addgroup_<?= $id ?>">
						<input type="checkbox" id="addgroup_<?= $id ?>" name="addgroup[<?= $id ?>]"<?= in_array($id, Utils::$context['sub']['add_groups']) ? ' checked' : '' ?><?= !empty(Utils::$context['disable_groups']) ? ' disabled' : '' ?>>
						<span class="smalltext"><?= $name ?></span>
					</label><br>
<?php endforeach; ?>
				</dd>
				<dt>
					<?= Lang::getTxt('paid_mod_reminder', file: 'ManagePaid') ?><br>
					<span class="smalltext"><?= Lang::getTxt('paid_mod_reminder_desc', file: 'ManagePaid') ?> <?= Lang::getTxt('zero_to_disable', file: 'Admin') ?></span>
				</dt>
				<dd>
					<input type="number" name="reminder" value="<?= Utils::$context['sub']['reminder'] ?>" size="6">
				</dd>
				<dt>
					<?= Lang::getTxt('paid_mod_email', file: 'ManagePaid') ?><br>
					<span class="smalltext"><?= Lang::getTxt('paid_mod_email_desc', file: 'ManagePaid') ?></span>
				</dt>
				<dd>
					<textarea name="emailcomplete" rows="6" cols="40"><?= Utils::$context['sub']['email_complete'] ?></textarea>
				</dd>
			</dl>
			<hr>
			<input type="radio" name="duration_type" id="duration_type_fixed" value="fixed"<?= empty(Utils::$context['sub']['duration']) || Utils::$context['sub']['duration'] == 'fixed' ? ' checked' : '' ?> onclick="toggleDuration('fixed');">
			<strong><label for="duration_type_fixed"><?= Lang::getTxt('paid_mod_fixed_price', file: 'ManagePaid') ?></label></strong>
			<br>
			<div id="fixed_area" <?= empty(Utils::$context['sub']['duration']) || Utils::$context['sub']['duration'] == 'fixed' ? '' : 'style="display: none;"' ?>>
				<fieldset>
					<dl class="settings">
						<dt>
							<?= Lang::getTxt('paid_cost', file: 'ManagePaid') ?> (<?= str_replace('%1.2f', '', Config::$modSettings['paid_currency_symbol']) ?>)
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost" value="<?= empty(Utils::$context['sub']['cost']['fixed']) ? '' : Utils::$context['sub']['cost']['fixed'] ?>" placeholder="0.00" size="4">
						</dd>
						<dt>
							<?= Lang::getTxt('paid_mod_span', file: 'ManagePaid') ?>

						</dt>
						<dd>
							<input type="number" name="span_value" value="<?= Utils::$context['sub']['span']['value'] ?>" size="4">
							<select name="span_unit">
								<option value="D"<?= Utils::$context['sub']['span']['unit'] == 'D' ? ' selected' : '' ?>><?= Lang::getTxt('paid_mod_span_days', file: 'ManagePaid') ?></option>
								<option value="W"<?= Utils::$context['sub']['span']['unit'] == 'W' ? ' selected' : '' ?>><?= Lang::getTxt('paid_mod_span_weeks', file: 'ManagePaid') ?></option>
								<option value="M"<?= Utils::$context['sub']['span']['unit'] == 'M' ? ' selected' : '' ?>><?= Lang::getTxt('paid_mod_span_months', file: 'ManagePaid') ?></option>
								<option value="Y"<?= Utils::$context['sub']['span']['unit'] == 'Y' ? ' selected' : '' ?>><?= Lang::getTxt('paid_mod_span_years', file: 'ManagePaid') ?></option>
							</select>
						</dd>
					</dl>
				</fieldset>
			</div><!-- #fixed_area -->
			<input type="radio" name="duration_type" id="duration_type_flexible" value="flexible"<?= !empty(Utils::$context['sub']['duration']) && Utils::$context['sub']['duration'] == 'flexible' ? ' checked' : '' ?> onclick="toggleDuration('flexible');">
			<strong><label for="duration_type_flexible"><?= Lang::getTxt('paid_mod_flexible_price', file: 'ManagePaid') ?></label></strong>
			<br>
			<div id="flexible_area" <?= !empty(Utils::$context['sub']['duration']) && Utils::$context['sub']['duration'] == 'flexible' ? '' : 'style="display: none;"' ?>>
				<fieldset>
					<div class="information">
						<strong><?= Lang::getTxt('paid_mod_price_breakdown', file: 'ManagePaid') ?></strong><br>
						<?= Lang::getTxt('paid_mod_price_breakdown_desc', file: 'ManagePaid') ?>

					</div>
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('paid_duration', file: 'ManagePaid') ?></strong>
						</dt>
						<dd>
							<strong><?= Lang::getTxt('paid_cost', file: 'ManagePaid') ?> (<?= preg_replace('~%[df\.\d]+~', '', Config::$modSettings['paid_currency_symbol']) ?>)</strong>
						</dd>
						<dt>
							<?= Lang::getTxt('paid_per_day', file: 'ManagePaid') ?>

						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_day" value="<?= empty(Utils::$context['sub']['cost']['day']) ? '0' : Utils::$context['sub']['cost']['day'] ?>" size="5">
						</dd>
						<dt>
							<?= Lang::getTxt('paid_per_week', file: 'ManagePaid') ?>

						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_week" value="<?= empty(Utils::$context['sub']['cost']['week']) ? '0' : Utils::$context['sub']['cost']['week'] ?>" size="5">
						</dd>
						<dt>
							<?= Lang::getTxt('paid_per_month', file: 'ManagePaid') ?>

						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_month" value="<?= empty(Utils::$context['sub']['cost']['month']) ? '0' : Utils::$context['sub']['cost']['month'] ?>" size="5">
						</dd>
						<dt>
							<?= Lang::getTxt('paid_per_year', file: 'ManagePaid') ?>

						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_year" value="<?= empty(Utils::$context['sub']['cost']['year']) ? '0' : Utils::$context['sub']['cost']['year'] ?>" size="5">
						</dd>
					</dl>
				</fieldset>
			</div><!-- #flexible_area -->
			<input type="submit" name="save" value="<?= Lang::getTxt('paid_settings_save', file: 'ManagePaid') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-pms_token_var'] ?>" value="<?= Utils::$context['admin-pms_token'] ?>">
		</div><!-- .windowbg -->
	</form>