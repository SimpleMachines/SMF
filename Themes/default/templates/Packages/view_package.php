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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * View package details when installing/uninstalling
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt((Utils::$context['uninstalling'] ? 'uninstall' : ('install_' . Utils::$context['extract_type'])), file: 'Packages') ?></h3>
		</div>
		<div class="information"><?php if (Utils::$context['is_installed']): ?>
			<strong><?= Lang::getTxt('package_installed_warning1', file: 'Packages') ?></strong><br>
			<br>
			<?= Lang::getTxt('package_installed_warning2', file: 'Packages') ?><br>
			<br><?php endif; ?><?= Lang::getTxt('package_installed_warning3', file: 'Packages') ?>
		</div>
		<br><?php if (!empty(Utils::$context['package_blacklist_found'])): ?>
		<div class="errorbox"><?= Lang::getTxt('package_validation_blacklist_found', file: 'Packages') ?>
		</div><?php endif; ?><?php /* Do errors exist in the install? If so light them up like a christmas tree. */ ?><?php if (Utils::$context['has_failure']): ?>
		<div class="errorbox">
			<?= Lang::getTxt(
			'package_will_fail_title',
			[
				Lang::getTxt(
					'package_' . (Utils::$context['uninstalling'] ? 'uninstall' : 'install'),
					file: 'Packages',
				),
			],
			file: 'Packages',
		) ?><br>
			<?= Lang::getTxt(
				'package_will_fail_warning',
				[
					Lang::getTxt(
						'package_' . (Utils::$context['uninstalling'] ? 'uninstall' : 'install'),
						file: 'Packages',
					),
				],
				file: 'Packages',
			) ?><?= !empty(Utils::$context['failure_details']) ? '<br><br><strong>' . Utils::$context['failure_details'] . '</strong>' : '' ?>
		</div><?php endif; ?><?php /* Validation info? */ ?><?php if (!empty(Utils::$context['validation_tests'])): ?>
		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('package_validaiton_results', file: 'Packages') ?></h3>
		</div>
		<div id="package_validation">
			<table class="table_grid"><?php foreach (Utils::$context['validation_tests'] as $id_server => $result): ?>
			<tr>
				<td><?= Utils::$context['package_servers'][$id_server]['name'] ?></td>
				<td><?= Lang::getTxt($result[Utils::$context['package_sha256_hash']] ?? 'package_validation_status_unknown', file: 'Packages') ?></td>
			</tr><?php endforeach; ?>
			</table>
		</div>
		<br><?php endif; ?><?php /* Display the package readme if one exists */ ?><?php if (isset(Utils::$context['package_readme'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_' . (Utils::$context['uninstalling'] ? 'un' : '') . 'install_readme', file: 'Search') ?></h3>
		</div>
		<div class="windowbg">
			<?= Utils::adjustHeadingLevels(Utils::$context['package_readme'], 3) ?>
			<span class="floatright"><?= Lang::getTxt('package_available_readme_language', file: 'Packages') ?>
				<select name="readme_language" id="readme_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + '?action=admin;area=packages;sa=<?= Utils::$context['uninstalling'] ? 'uninstall' : 'install' ?>;package=<?= Utils::$context['filename'] ?>;license=' + this.options[this.selectedIndex].value + ';readme=' + get_selected('readme_language'));"><?php foreach (Utils::$context['readmes'] as $a => $b): ?>
					<option value="<?= $b ?>"<?= $a === 'selected' ? ' selected' : '' ?>><?= $b == 'default' ? Lang::getTxt('package_readme_default', file: 'Packages') : ucfirst($b) ?></option><?php endforeach; ?>
				</select>
			</span>
		</div>
		<br><?php endif; ?><?php /* Did they specify a license to display? */ ?><?php if (isset(Utils::$context['package_license'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_install_license', file: 'Packages') ?></h3>
		</div>
		<div class="windowbg">
			<?= Utils::adjustHeadingLevels(Utils::$context['package_license'], 3) ?>
			<span class="floatright"><?= Lang::getTxt('package_available_license_language', file: 'Packages') ?>
				<select name="license_language" id="license_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + '?action=admin;area=packages;sa=install;package=<?= Utils::$context['filename'] ?>;readme=' + this.options[this.selectedIndex].value + ';license=' + get_selected('license_language'));"><?php foreach (Utils::$context['licenses'] as $a => $b): ?>
					<option value="<?= $b ?>"<?= $a === 'selected' ? ' selected' : '' ?>><?= $b == 'default' ? Lang::getTxt('package_license_default', file: 'Packages') : ucfirst($b) ?></option><?php endforeach; ?>
				</select>
			</span>
		</div>
		<br><?php endif; ?>
		<form action="<?= !empty(Utils::$context['post_url']) ? Utils::$context['post_url'] : '#' ?>" onsubmit="submitonce(this);" method="post" accept-charset="UTF-8" id="view_package">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt(Utils::$context['uninstalling'] ? 'package_uninstall_actions' : 'package_install_actions', Utils::$context, file: 'Packages') ?>
				</h3>
			</div><?php /* Are there data changes to be removed? */ ?><?php if (Utils::$context['uninstalling'] && !empty(Utils::$context['database_changes'])): ?><?php /* This is really a special case so we're adding style inline */ ?>
			<div class="windowbg" style="margin: 0; border-radius: 0;">
				<label><input type="checkbox" name="do_db_changes"><?= Lang::getTxt('package_db_uninstall', file: 'Packages') ?></label>
				<div id="db_changes_div">
					<?= Lang::getTxt('package_db_uninstall_actions', file: 'Packages') ?>
					<ul class="normallist smalltext"><?php foreach (Utils::$context['database_changes'] as $change): ?>
						<li><?= $change ?></li><?php endforeach; ?>
					</ul>
				</div>
			</div><?php endif; ?>
			<div class="information"><?php if (empty(Utils::$context['actions']) && empty(Utils::$context['database_changes'])): ?>
				<br>
				<div class="errorbox">
					<?= Lang::getTxt('corrupt_compatible', file: 'Packages') ?>
				</div>
			</div><!-- .information --><?php else: ?>
				<?= Lang::getTxt('perform_actions', file: 'Packages') ?>
			</div><!-- .information -->
			<br>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col"></th>
						<th scope="col" width="30"></th>
						<th scope="col" class="lefttext"><?= Lang::getTxt('package_install_type', file: 'Packages') ?></th>
						<th scope="col" class="lefttext" width="50%"><?= Lang::getTxt('package_install_action', file: 'Packages') ?></th>
						<th class="lefttext" scope="col" width="20%"><?= Lang::getTxt('package_install_desc', file: 'Packages') ?></th>
					</tr>
				</thead>
				<tbody><?php
$i = 1;
$j = 1;
$action_num = 1;
$js_operations = [];
?><?php foreach (Utils::$context['actions'] as $packageaction): ?><?php
// Did we pass or fail?  Need to now for later on.
$js_operations[$action_num] = $packageaction['failed'] ?? 0;
?>
					<tr class="bg <?= $i % 2 == 0 ? 'even' : 'odd' ?>">
						<td><?= isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . Theme::$current->settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '' ?></td>
						<td style="width: 30px;"><?= $i++ ?>.</td>
						<td style="width: 23%;"><?= $packageaction['type'] ?></td>
						<td style="width: 50%;"><?= $packageaction['action'] ?></td>
						<td style="width: 20%;"><strong<?= !empty($packageaction['failed']) ? ' class="error"' : '' ?>><?= $packageaction['description'] ?></strong></td>
					</tr><?php /* Is there water on the knee? Operation! */ ?><?php if (isset($packageaction['operations'])): ?>
					<tr id="operation_<?= $action_num ?>">
						<td colspan="5">
							<table class="full_width"><?php
// Show the operations.
$operation_num = 1;
?><?php foreach ($packageaction['operations'] as $operation): ?><?php
// Determine the position text.
$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');
?>
								<tr class="bg <?= $operation_num % 2 == 0 ? 'even' : 'odd' ?>">
									<td class="righttext">
										<a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=showoperations;operation_key=<?= $operation['operation_key'] ?><?= !empty(Utils::$context['install_id']) ? ';install_id=' . Utils::$context['install_id'] : '' ?>;package=<?= $_REQUEST['package'] ?>;filename=<?= $operation['filename'] ?><?= ($operation['is_diff'] ? ';diff' : '') ?><?= ($operation['is_boardmod'] ? ';boardmod' : '') ?><?= (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : '') ?>" onclick="return reqWin(this.href, 600, 400, false);">
											<span class="main_icons package_ops"></span>
										</a>
									</td>
									<td width="30"><?= $operation_num++ ?>.</td>
									<td width="23%"><?= Lang::getTxt($operation_text, file: 'Packages') ?></td>
									<td width="50%"><?= $operation['action'] ?></td>
									<td width="20%"><strong<?= !empty($operation['failed']) ? ' class="error"' : '' ?>><?= !empty($operation['ignore_failure']) ? Lang::getTxt('operation_description_ignore', ['desc' => $operation['description']], file: 'Packages') : $operation['description'] ?></strong></td>
								</tr><?php endforeach; ?>
							</table>
						</td>
					</tr><?php
// Increase it.
$action_num++;
?><?php endif; ?><?php endforeach; ?>
				</tbody>
			</table><?php /* What if we have custom themes we can install into? List them too! */ ?><?php if (!empty(Utils::$context['theme_actions'])): ?>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Utils::$context['uninstalling'] ? Lang::getTxt('package_other_themes_uninstall', file: 'Packages') : Lang::getTxt('package_other_themes', file: 'Packages') ?>
				</h3>
			</div>
			<div id="custom_changes">
				<div class="information">
					<?= Lang::getTxt('package_other_themes_desc', file: 'Packages') ?>
				</div>
				<table class="table_grid"><?php /* Loop through each theme and display its name, and then it's details. */ ?><?php foreach (Utils::$context['theme_actions'] as $id => $theme): ?><?php
// Pass?
$js_operations[$action_num] = !empty($theme['has_failure']);
?>
					<tr class="title_bar">
						<td class="righttext" colspan="2"><?php if (!empty(Utils::$context['themes_locked'])): ?>
							<input type="hidden" name="custom_theme[]" value="<?= $id ?>"><?php endif; ?>
							<input type="checkbox" name="custom_theme[]" id="custom_theme_<?= $id ?>" value="<?= $id ?>" onclick="<?= (!empty($theme['has_failure']) ? 'if (this.form.custom_theme_' . $id . '.checked && !confirm(\'' . Lang::getTxt('package_theme_failure_warning', file: 'Packages') . '\')) return false;' : '') ?>invertAll(this, this.form, 'dummy_theme_<?= $id ?>', true);"<?= !empty(Utils::$context['themes_locked']) ? ' disabled checked' : '' ?>>
						</td>
						<td colspan="3">
							<?= $theme['name'] ?>
						</td>
					</tr><?php foreach ($theme['actions'] as $action): ?>
					<tr class="bg <?= $j++ % 2 == 0 ? 'even' : 'odd' ?>">
						<td colspan="2"><?= isset($packageaction['operations']) ?
							'<img id="operation_img_' . $action_num . '" src="' . Theme::$current->settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '' ?>
							<input type="checkbox" name="theme_changes[]" value="<?= !empty($action['value']) ? $action['value'] : '' ?>" id="dummy_theme_<?= $id ?>"<?= (!empty($action['not_mod']) ? '' : ' disabled') ?><?= !empty(Utils::$context['themes_locked']) ? ' checked' : '' ?> class="floatright">
						</td>
						<td width="23%"><?= $action['type'] ?></td>
						<td width="50%"><?= $action['action'] ?></td>
						<td width="20%"><strong<?= !empty($action['failed']) ? ' class="error"' : '' ?>><?= $action['description'] ?></strong></td>
					</tr><?php /* Is there water on the knee? Operation! */ ?><?php if (isset($action['operations'])): ?>
					<tr id="operation_<?= $action_num ?>">
						<td colspan="5">
							<table class="full_width"><?php $operation_num = 1; ?><?php foreach ($action['operations'] as $operation): ?><?php
// Determine the position text.
$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');
?>
								<tr class="bg <?= $operation_num % 2 == 0 ? 'even' : 'odd' ?>">
									<td class="righttext">
										<a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=showoperations;operation_key=<?= $operation['operation_key'] ?><?= !empty(Utils::$context['install_id']) ? ';install_id=' . Utils::$context['install_id'] : '' ?>;package=<?= $_REQUEST['package'] ?>;filename=<?= $operation['filename'] ?><?= ($operation['is_diff'] ? ';diff' : '') ?><?= ($operation['is_boardmod'] ? ';boardmod' : '') ?><?= (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : '') ?>" onclick="return reqWin(this.href, 600, 400, false);">
											<span class="main_icons package_ops"></span>
										</a>
									</td>
									<td width="30"><?= $operation_num++ ?>.</td>
									<td width="23%"><?= Lang::getTxt($operation_text, file: 'Packages') ?></td>
									<td width="50%"><?= $operation['action'] ?></td>
									<td width="20%"><strong<?= !empty($operation['failed']) ? ' class="error"' : '' ?>><?= !empty($operation['ignore_failure']) ? Lang::getTxt('operation_description_ignore', ['desc' => $operation['description']], file: 'Packages') : $operation['description'] ?></strong></td>
								</tr><?php endforeach; ?>
							</table>
						</td>
					</tr><?php
// Increase it.
$action_num++;
?><?php endif; ?><?php endforeach; ?><?php endforeach; ?>
				</table>
			</div><!-- #custom_changes --><?php endif; ?><?php endif; ?><?php /* Are we effectively ready to install? */ ?><?php if (!Utils::$context['ftp_needed'] && (!empty(Utils::$context['actions']) || !empty(Utils::$context['database_changes']))): ?>
			<div class="righttext padding">
				<input type="submit" value="<?= Lang::getTxt(Utils::$context['uninstalling'] ? 'package_uninstall_now' : 'package_install_now', file: 'Packages') ?>" onclick="return <?= !empty(Utils::$context['has_failure']) ? '(submitThisOnce(this) &amp;&amp; confirm(\'' . Lang::getTxt(Utils::$context['uninstalling'] ? 'package_will_fail_popup_uninstall' : 'package_will_fail_popup', file: 'Packages') . '\'))' : 'submitThisOnce(this)' ?>;" class="button">
			</div><?php /* If we need ftp information then demand it! */ ?><?php elseif (Utils::$context['ftp_needed']): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('package_ftp_necessary', file: 'Packages') ?></h3>
			</div>
			<div>
				<?php $this->subTemplate('control_chmod'); ?>
			</div><?php endif; ?>


			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>"><?= (isset(Utils::$context['form_sequence_number']) && !Utils::$context['ftp_needed']) ? '
			<input type="hidden" name="seqnum" value="' . Utils::$context['form_sequence_number'] . '">' : '' ?>
		</form><?php /* Toggle options. */ ?>
	<script><?php /* Operations. */ ?><?php if (!empty($js_operations)): ?><?php foreach ($js_operations as $key => $operation): ?>

		new smc_Toggle({
			bToggleEnabled: true,
			bNoAnimate: true,
			bCurrentlyCollapsed: <?= $operation ? 'false' : 'true' ?>,
			aSwappableContainers: [
				'operation_<?= $key ?>'
			],
			aSwapImages: [
				{
					sId: 'operation_img_<?= $key ?>',
					srcExpanded: smf_images_url + '/selected_open.png',
					altExpanded: '*',
					srcCollapsed: smf_images_url + '/selected.png',
					altCollapsed: '*'
				}
			]
		});<?php endforeach; ?><?php endif; ?><?php /* Get the currently selected item from a select list */ ?>

		function get_selected(id)
		{
			var aSelected = document.getElementById(id);
			for (var i = 0; i < aSelected.options.length; i++)
			{
				if (aSelected.options[i].selected == true)
					return aSelected.options[i].value;
			}
			return aSelected.options[0];
		}<?php /* And a bit more for database changes. */ ?><?php if (Utils::$context['uninstalling'] && !empty(Utils::$context['database_changes'])): ?>

		makeToggle(document.getElementById('db_changes_div'), <?= Utils::escapeJavaScript(Lang::getTxt('package_db_uninstall_details', file: 'Packages')) ?>);<?php endif; ?>

	</script>