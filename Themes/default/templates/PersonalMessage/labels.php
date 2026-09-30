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
 * Here we allow the user to setup labels, remove labels and change rules for labels (i.e, do quite a bit)
 */
?>
	<form action="<?= Config::$scripturl ?>?action=pm;sa=manlabels" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_manage_labels', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('pm_labels_desc', file: 'PersonalMessage') ?>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">
						<?= Lang::getTxt('pm_label_name', file: 'PersonalMessage') ?>
					</th>
					<th class="centertext table_icon">
<?php if (count(Utils::$context['labels']) > 2): ?>
						<input type="checkbox" onclick="invertAll(this, this.form);">
<?php endif; ?>
					</th>
				</tr>
			</thead>
			<tbody>
<?php if (count(Utils::$context['labels']) < 2): ?>
				<tr class="windowbg">
					<td colspan="2"><?= Lang::getTxt('pm_labels_no_exist', file: 'PersonalMessage') ?></td>
				</tr>
<?php else: ?>
<?php foreach (Utils::$context['labels'] as $label): ?>
<?php if ($label['id'] == -1): ?>
<?php continue; ?>
<?php endif; ?>
				<tr class="windowbg">
					<td>
						<input type="text" name="label_name[<?= $label['id'] ?>]" value="<?= $label['name'] ?>" size="30" maxlength="30">
					</td>
					<td class="table_icon"><input type="checkbox" name="delete_label[<?= $label['id'] ?>]"></td>
				</tr>
<?php endforeach; ?>
<?php endif; ?>
			</tbody>
		</table>
<?php if (!count(Utils::$context['labels']) < 2): ?>
		<div class="block righttext">
			<input type="submit" name="save" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
			<input type="submit" name="delete" value="<?= Lang::getTxt('quickmod_delete_selected', file: 'General') ?>" data-confirm="<?= Lang::getTxt('pm_labels_delete', file: 'PersonalMessage') ?>" class="button you_sure">
		</div>
<?php endif; ?>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<form action="<?= Config::$scripturl ?>?action=pm;sa=manlabels" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_label_add_new', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="add_label"><?= Lang::getTxt('pm_label_name', file: 'PersonalMessage') ?></label></strong>
				</dt>
				<dd>
					<input type="text" id="add_label" name="label" value="" size="30" maxlength="30">
				</dd>
			</dl>
			<input type="submit" name="add" value="<?= Lang::getTxt('pm_label_add_new', file: 'PersonalMessage') ?>" class="button floatright">
		</div>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<br>