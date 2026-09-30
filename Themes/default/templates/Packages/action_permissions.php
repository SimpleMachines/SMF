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
 * A progress page showing what permissions changes are being applied
 */
?>

<?php $countDown = 3; ?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=packages;sa=perms;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" name="perm_submit" id="perm_submit" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('package_file_perms_applying', file: 'Packages') ?></h3>
			</div>
<?php if (!empty(Utils::$context['skip_ftp'])): ?>
			<div class="errorbox">
				<?= Lang::getTxt('package_file_perms_skipping_ftp', file: 'Packages') ?>
			</div>
<?php endif; ?>
<?php
// How many have we done?
$remaining_items = count(Utils::$context['method'] == 'individual' ? Utils::$context['to_process'] : Utils::$context['directory_list']);
$progress_message = Lang::getTxt(
	Utils::$context['method'] == 'individual' ? 'package_file_perms_items_done' : 'package_file_perms_dirs_done',
	[
		Utils::$context['total_items'] - $remaining_items,
		Utils::$context['total_items'],
	],
	file: 'Packages',
);
$progress_percent = round((Utils::$context['total_items'] - $remaining_items) / Utils::$context['total_items'] * 100, 1);
?>
			<div class="windowbg">
				<div>
					<strong><?= $progress_message ?></strong><br>
					<div class="progress_bar progress_blue">
						<span><?= $progress_percent ?>%</span>
						<div class="bar" style="width: <?= $progress_percent ?>%;"></div>
					</div>
				</div>
<?php /* Second progress bar for a specific directory? */ ?>
<?php if (Utils::$context['method'] != 'individual' && !empty(Utils::$context['total_files'])): ?>
<?php
$file_progress_message = Lang::getTxt(
			'package_file_perms_files_done',
			[
				Utils::$context['file_offset'],
				Utils::$context['total_files'],
			],
			file: 'Packages',
		);
$file_progress_percent = round(Utils::$context['file_offset'] / Utils::$context['total_files'] * 100, 1);
?>
				<br>
				<div>
					<strong><?= $file_progress_message ?></strong><br>
					<div class="progress_bar">
						<span><?= $file_progress_percent ?>%</span>
						<div class="bar" style="width: <?= $file_progress_percent ?>%;"></div>
					</div>
				</div>
<?php endif; ?>
				<br>
<?php /* Put out the right hidden data. */ ?>
<?php if (Utils::$context['method'] == 'individual'): ?>
				<input type="hidden" name="custom_value" value="<?= Utils::$context['custom_value'] ?>">
				<input type="hidden" name="totalItems" value="<?= Utils::$context['total_items'] ?>">
				<input type="hidden" name="toProcess" value="<?= Utils::$context['to_process_encode'] ?>">
<?php else: ?>
				<input type="hidden" name="predefined" value="<?= Utils::$context['predefined_type'] ?>">
				<input type="hidden" name="fileOffset" value="<?= Utils::$context['file_offset'] ?>">
				<input type="hidden" name="totalItems" value="<?= Utils::$context['total_items'] ?>">
				<input type="hidden" name="dirList" value="<?= Utils::$context['directory_list_encode'] ?>">
				<input type="hidden" name="specialFiles" value="<?= Utils::$context['special_files_encode'] ?>">
<?php endif; ?>
<?php /* Are we not using FTP for whatever reason. */ ?>
<?php if (!empty(Utils::$context['skip_ftp'])): ?>
				<input type="hidden" name="skip_ftp" value="1">
<?php endif; ?>
<?php /* Retain state. */ ?>
<?php foreach (Utils::$context['back_look_data'] as $path): ?>
				<input type="hidden" name="back_look[]" value="<?= $path ?>">
<?php endforeach; ?>
				<input type="hidden" name="method" value="<?= Utils::$context['method'] ?>">
				<input type="hidden" name="action_changes" value="1">
				<div class="righttext padding">
					<input type="submit" name="go" id="cont" value="<?= Lang::getTxt('not_done_continue', file: 'Admin') ?>" class="button">
				</div>
			</div><!-- .windowbg -->
		</form>
<?php /* Just the countdown stuff */ ?>
	<script>
		doAutoSubmit(<?= $countDown ?>, <?= Utils::escapeJavaScript(Lang::getTxt('not_done_continue', file: 'Admin')) ?>, "perm_submit", "go");
	</script>