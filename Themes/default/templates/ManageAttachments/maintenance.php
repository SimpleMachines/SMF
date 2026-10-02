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
 * The attachment maintenance page
 */
?>
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('attachment_stats', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt><strong><?= Lang::getTxt('attachment_total', file: 'Admin') ?></strong></dt>
				<dd><?= Utils::$context['num_attachments'] ?></dd>
				<dt><strong><?= Lang::getTxt('attachment_manager_total_avatars', file: 'Admin') ?></strong></dt>
				<dd><?= Utils::$context['num_avatars'] ?></dd>
				<dt><strong><?= Lang::getTxt('attachmentdir_size', file: 'Admin') ?></strong></dt>
				<dd><?= Lang::getTxt('size_kilobyte', [Utils::$context['attachment_total_size']], file: 'General') ?></dd>
				<dt><strong><?= Lang::getTxt('attach_current_dir', file: 'Admin') ?></strong></dt>
				<dd class="word_break"><?= Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']] ?></dd>
				<dt><strong><?= Lang::getTxt('attachmentdir_size_current', file: 'Admin') ?></strong></dt>
				<dd><?= Lang::getTxt('size_kilobyte', [Utils::$context['attachment_current_size']], file: 'General') ?></dd>
				<dt><strong><?= Lang::getTxt('attachment_space', file: 'Admin') ?></strong></dt>
				<dd><?= isset(Utils::$context['attachment_space']) ? Lang::getTxt('size_kilobyte', [Utils::$context['attachment_space']], file: 'General') : Lang::getTxt('attachmentdir_size_not_set', file: 'Admin') ?></dd>
				<dt><strong><?= Lang::getTxt('attachmentdir_files_current', file: 'Admin') ?></strong></dt>
				<dd><?= Utils::$context['attachment_current_files'] ?></dd>
				<dt><strong><?= Lang::getTxt('attachment_files', file: 'Admin') ?></strong></dt>
				<dd><?= Utils::$context['attachment_files'] ?? Lang::getTxt('attachmentdir_files_not_set', file: 'Admin') ?></dd>
			</dl>
		</div>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('attachment_integrity_check', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=manageattachments;sa=repair;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
				<p><?= Lang::getTxt('attachment_integrity_check_desc', file: 'Admin') ?></p>
				<input type="submit" name="repair" value="<?= Lang::getTxt('attachment_check_now', file: 'Admin') ?>" class="button">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('attachment_pruning', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm('<?= Lang::getTxt('attachment_pruning_warning', file: 'Admin') ?>');">
				<dl class="settings">
					<dt><?= Lang::getTxt('attachment_remove_old', file: 'Admin') ?></dt>
					<dd><input type="number" name="age" value="25" size="4"> <?= str_replace('25', '', Lang::getTxt('number_of_days', [25], file: 'General')) ?></dd>
					<dt><?= Lang::getTxt('attachment_pruning_message', file: 'Admin') ?></dt>
					<dd><input type="text" name="notice" value="<?= Lang::getTxt('attachment_delete_admin', file: 'Admin') ?>" size="40"></dd>
					<input type="submit" name="remove" value="<?= Lang::getTxt('remove', file: 'General') ?>" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="sa" value="byage">
				</dl>
			</form>
			<form action="<?= Config::$scripturl ?>?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm('<?= Lang::getTxt('attachment_pruning_warning', file: 'Admin') ?>');">
				<dl class="settings">
					<dt><?= Lang::getTxt('attachment_remove_size', file: 'Admin') ?></dt>
					<dd><input type="number" name="size" id="size" value="100" size="4"> <?= Lang::getTxt('kilobyte', file: 'General') ?></dd>
					<dt><?= Lang::getTxt('attachment_pruning_message', file: 'Admin') ?></dt>
					<dd><input type="text" name="notice" value="<?= Lang::getTxt('attachment_delete_admin', file: 'Admin') ?>" size="40"></dd>
					<input type="submit" name="remove" value="<?= Lang::getTxt('remove', file: 'General') ?>" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="sa" value="bysize">
				</dl>
			</form>
			<form action="<?= Config::$scripturl ?>?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm('<?= Lang::getTxt('attachment_pruning_warning', file: 'Admin') ?>');">
				<dl class="settings">
					<dt><?= Lang::getTxt('attachment_manager_avatars_older', file: 'Admin') ?></dt>
					<dd><input type="number" name="age" value="45" size="4"> <?= str_replace('45', '', Lang::getTxt('number_of_days', [45], file: 'General')) ?></dd>
					<input type="submit" name="remove" value="<?= Lang::getTxt('remove', file: 'General') ?>" class="button">
					<input type="hidden" name="type" value="avatars">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="sa" value="byage">
				</dl>
			</form>
		</div><!-- .windowbg -->
	</div><!-- #manage_attachments -->
<?php if (!empty(Utils::$context['results'])): ?>
	<div class="noticebox"><?= Utils::$context['results'] ?></div>
<?php endif; ?>
	<div id="transfer" class="cat_bar">
		<h3 class="catbg"><?= Lang::getTxt('attachment_transfer', file: 'Admin') ?></h3>
	</div>
	<div class="windowbg">
		<form action="<?= Config::$scripturl ?>?action=admin;area=manageattachments;sa=transfer" method="post" accept-charset="UTF-8">
			<p><?= Lang::getTxt('attachment_transfer_desc', file: 'Admin') ?></p>
			<dl class="settings">
				<dt><?= Lang::getTxt('attachment_transfer_from', file: 'Admin') ?></dt>
				<dd>
					<select name="from">
						<option value="0"><?= Lang::getTxt('attachment_transfer_select', file: 'Admin') ?></option>
<?php foreach (Utils::$context['attach_dirs'] as $id => $dir): ?>
						<option value="<?= $id ?>"><?= $dir ?></option>
<?php endforeach; ?>
					</select>
				</dd>
				<dt><?= Lang::getTxt('attachment_transfer_auto', file: 'Admin') ?></dt>
				<dd>
					<select name="auto">
						<option value="0"><?= Lang::getTxt('attachment_transfer_auto_select', file: 'Admin') ?></option>
						<option value="-1"><?= Lang::getTxt('attachment_transfer_forum_root', file: 'Admin') ?></option>
<?php if (!empty(Utils::$context['base_dirs'])): ?>
<?php foreach (Utils::$context['base_dirs'] as $id => $dir): ?>
						<option value="<?= $id ?>"><?= $dir ?></option>
<?php endforeach; ?>
<?php else: ?>
						<option value="0" disabled><?= Lang::getTxt('attachment_transfer_no_base', file: 'Admin') ?></option>
<?php endif; ?>
					</select>
				</dd>
				<dt><?= Lang::getTxt('attachment_transfer_to', file: 'Admin') ?></dt>
				<dd>
					<select name="to">
						<option value="0"><?= Lang::getTxt('attachment_transfer_select', file: 'Admin') ?></option>
<?php foreach (Utils::$context['attach_dirs'] as $id => $dir): ?>
						<option value="<?= $id ?>"><?= $dir ?></option>
<?php endforeach; ?>
					</select>
				</dd>
<?php if (!empty(Config::$modSettings['attachmentDirFileLimit'])): ?>
				<dt><?= Lang::getTxt('attachment_transfer_empty', file: 'Admin') ?></dt>
				<dd><input type="checkbox" name="empty_it"<?= Utils::$context['checked'] ? ' checked' : '' ?>></dd>
<?php endif; ?>
			</dl>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="submit" onclick="start_progress()" name="transfer" value="<?= Lang::getTxt('attachment_transfer_now', file: 'Admin') ?>" class="button">
			<div id="progress_msg"></div>
			<div id="show_progress" class="padding"></div>
		</form>
		<script>
			function start_progress() {
				setTimeout(show_msg, 1000);
			}

			function show_msg() {
				$('#progress_msg').html('<div><img src="<?= Theme::$current->settings['actual_images_url'] ?>/loading_sm.gif" alt="<?= Lang::getTxt('ajax_in_progress', file: 'General') ?>" width="35" height="35"> <?= Lang::getTxt('attachment_transfer_progress', file: 'Admin') ?><\/div>');
				show_progress();
			}

			function show_progress() {
				$('#show_progress').on("load", "progress.php");
				setTimeout(show_progress, 1500);
			}

		</script>
	</div><!-- .windowbg -->