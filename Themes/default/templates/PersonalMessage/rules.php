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
 * Manage rules.
 */
?>

	<form action="<?= Config::$scripturl ?>?action=pm;sa=manrules" method="post" accept-charset="UTF-8" name="manRules" id="manrules">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_manage_rules', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('pm_manage_rules_desc', file: 'PersonalMessage') ?>

		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">
						<?= Lang::getTxt('pm_rule_title', file: 'PersonalMessage') ?>

					</th>
					<th class="centertext table_icon">
<?php if (!empty(Utils::$context['rules'])): ?>
						<input type="checkbox" onclick="invertAll(this, this.form);">
<?php endif; ?>
					</th>
				</tr>
			</thead>
			<tbody>
<?php if (empty(Utils::$context['rules'])): ?>
				<tr class="windowbg">
					<td colspan="2">
						<?= Lang::getTxt('pm_rules_none', file: 'PersonalMessage') ?>

					</td>
				</tr>
<?php endif; ?>
<?php foreach (Utils::$context['rules'] as $rule): ?>
				<tr class="windowbg">
					<td>
						<a href="<?= Config::$scripturl ?>?action=pm;sa=manrules;add;rid=<?= $rule['id'] ?>"><?= $rule['name'] ?></a>
					</td>
					<td class="table_icon">
						<input type="checkbox" name="delrule[<?= $rule['id'] ?>]">
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
		<div class="righttext">
			<a class="button" href="<?= Config::$scripturl ?>?action=pm;sa=manrules;add;rid=0"><?= Lang::getTxt('pm_add_rule', file: 'PersonalMessage') ?></a>
<?php if (!empty(Utils::$context['rules'])): ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="submit" name="delselected" value="<?= Lang::getTxt('pm_delete_selected_rule', file: 'PersonalMessage') ?>" data-confirm="<?= Lang::getTxt('pm_js_delete_rule_confirm', file: 'PersonalMessage') ?>" class="button smalltext you_sure">
<?php endif; ?>
<?php if (!empty(Utils::$context['rules'])): ?>
			<a href="<?= Config::$scripturl ?>?action=pm;sa=manrules;apply;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" data-confirm="<?= Lang::getTxt('pm_js_apply_rules_confirm', file: 'PersonalMessage') ?>" class="button you_sure"><?= Lang::getTxt('pm_apply_rules', file: 'PersonalMessage') ?></a>
<?php endif; ?>
		</div>
	</form>