<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * This template wraps around the simple settings page to add javascript functionality.
 */
function template_avatar_settings_above()
{
}

/**
 * JavaScript to be output below the simple settings page
 */
function template_avatar_settings_below()
{
	echo '
	<script>
		var fUpdateStatus = function ()
		{
			document.getElementById("avatar_max_width_external").disabled = document.getElementById("avatar_download_external").checked;
			document.getElementById("avatar_max_height_external").disabled = document.getElementById("avatar_download_external").checked;
			document.getElementById("avatar_action_too_large").disabled = document.getElementById("avatar_download_external").checked;
		}
		addLoadEvent(fUpdateStatus);
	</script>';
}

/**
 * The attachment maintenance page
 */
function template_maintenance()
{
	echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('attachment_stats', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt><strong>', Lang::getTxt('attachment_total', file: 'Admin'), '</strong></dt>
				<dd>', Utils::$context['num_attachments'], '</dd>
				<dt><strong>', Lang::getTxt('attachment_manager_total_avatars', file: 'Admin'), '</strong></dt>
				<dd>', Utils::$context['num_avatars'], '</dd>
				<dt><strong>', Lang::getTxt('attachmentdir_size', file: 'Admin'), '</strong></dt>
				<dd>', Lang::getTxt('size_kilobyte', [Utils::$context['attachment_total_size']], file: 'General'), '</dd>
				<dt><strong>', Lang::getTxt('attach_current_dir', file: 'Admin'), '</strong></dt>
				<dd class="word_break">', Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']], '</dd>
				<dt><strong>', Lang::getTxt('attachmentdir_size_current', file: 'Admin'), '</strong></dt>
				<dd>', Lang::getTxt('size_kilobyte', [Utils::$context['attachment_current_size']], file: 'General'), '</dd>
				<dt><strong>', Lang::getTxt('attachment_space', file: 'Admin'), '</strong></dt>
				<dd>', isset(Utils::$context['attachment_space']) ? Lang::getTxt('size_kilobyte', [Utils::$context['attachment_space']], file: 'General') : Lang::getTxt('attachmentdir_size_not_set', file: 'Admin'), '</dd>
				<dt><strong>', Lang::getTxt('attachmentdir_files_current', file: 'Admin'), '</strong></dt>
				<dd>', Utils::$context['attachment_current_files'], '</dd>
				<dt><strong>', Lang::getTxt('attachment_files', file: 'Admin'), '</strong></dt>
				<dd>', isset(Utils::$context['attachment_files']) ? Utils::$context['attachment_files'] : Lang::getTxt('attachmentdir_files_not_set', file: 'Admin'), '</dd>
			</dl>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('attachment_integrity_check', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=repair;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
				<p>', Lang::getTxt('attachment_integrity_check_desc', file: 'Admin'), '</p>
				<input type="submit" name="repair" value="', Lang::getTxt('attachment_check_now', file: 'Admin'), '" class="button">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('attachment_pruning', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', Lang::getTxt('attachment_pruning_warning', file: 'Admin'), '\');">
				<dl class="settings">
					<dt>', Lang::getTxt('attachment_remove_old', file: 'Admin'), '</dt>
					<dd><input type="number" name="age" value="25" size="4"> ', str_replace('25', '', Lang::getTxt('number_of_days', [25], file: 'General')), '</dd>
					<dt>', Lang::getTxt('attachment_pruning_message', file: 'Admin'), '</dt>
					<dd><input type="text" name="notice" value="', Lang::getTxt('attachment_delete_admin', file: 'Admin'), '" size="40"></dd>
					<input type="submit" name="remove" value="', Lang::getTxt('remove', file: 'General'), '" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="byage">
				</dl>
			</form>
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', Lang::getTxt('attachment_pruning_warning', file: 'Admin'), '\');">
				<dl class="settings">
					<dt>', Lang::getTxt('attachment_remove_size', file: 'Admin'), '</dt>
					<dd><input type="number" name="size" id="size" value="100" size="4"> ', Lang::getTxt('kilobyte', file: 'General'), '</dd>
					<dt>', Lang::getTxt('attachment_pruning_message', file: 'Admin'), '</dt>
					<dd><input type="text" name="notice" value="', Lang::getTxt('attachment_delete_admin', file: 'Admin'), '" size="40"></dd>
					<input type="submit" name="remove" value="', Lang::getTxt('remove', file: 'General'), '" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="bysize">
				</dl>
			</form>
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', Lang::getTxt('attachment_pruning_warning', file: 'Admin'), '\');">
				<dl class="settings">
					<dt>', Lang::getTxt('attachment_manager_avatars_older', file: 'Admin'), '</dt>
					<dd><input type="number" name="age" value="45" size="4"> ', str_replace('45', '', Lang::getTxt('number_of_days', [45], file: 'General')), '</dd>
					<input type="submit" name="remove" value="', Lang::getTxt('remove', file: 'General'), '" class="button">
					<input type="hidden" name="type" value="avatars">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="byage">
				</dl>
			</form>
		</div><!-- .windowbg -->
	</div><!-- #manage_attachments -->';

	if (!empty(Utils::$context['results']))
		echo '
	<div class="noticebox">', Utils::$context['results'], '</div>';

	echo '
	<div id="transfer" class="cat_bar">
		<h3 class="catbg">', Lang::getTxt('attachment_transfer', file: 'Admin'), '</h3>
	</div>
	<div class="windowbg">
		<form action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=transfer" method="post" accept-charset="UTF-8">
			<p>', Lang::getTxt('attachment_transfer_desc', file: 'Admin'), '</p>
			<dl class="settings">
				<dt>', Lang::getTxt('attachment_transfer_from', file: 'Admin'), '</dt>
				<dd>
					<select name="from">
						<option value="0">', Lang::getTxt('attachment_transfer_select', file: 'Admin'), '</option>';

	foreach (Utils::$context['attach_dirs'] as $id => $dir)
		echo '
						<option value="', $id, '">', $dir, '</option>';

	echo '
					</select>
				</dd>
				<dt>', Lang::getTxt('attachment_transfer_auto', file: 'Admin'), '</dt>
				<dd>
					<select name="auto">
						<option value="0">', Lang::getTxt('attachment_transfer_auto_select', file: 'Admin'), '</option>
						<option value="-1">', Lang::getTxt('attachment_transfer_forum_root', file: 'Admin'), '</option>';

	if (!empty(Utils::$context['base_dirs']))
		foreach (Utils::$context['base_dirs'] as $id => $dir)
			echo '
						<option value="', $id, '">', $dir, '</option>';
	else
		echo '
						<option value="0" disabled>', Lang::getTxt('attachment_transfer_no_base', file: 'Admin'), '</option>';

	echo '
					</select>
				</dd>
				<dt>', Lang::getTxt('attachment_transfer_to', file: 'Admin'), '</dt>
				<dd>
					<select name="to">
						<option value="0">', Lang::getTxt('attachment_transfer_select', file: 'Admin'), '</option>';

	foreach (Utils::$context['attach_dirs'] as $id => $dir)
		echo '
						<option value="', $id, '">', $dir, '</option>';

	echo '
					</select>
				</dd>';

	if (!empty(Config::$modSettings['attachmentDirFileLimit']))
		echo '
				<dt>', Lang::getTxt('attachment_transfer_empty', file: 'Admin'), '</dt>
				<dd><input type="checkbox" name="empty_it"', Utils::$context['checked'] ? ' checked' : '', '></dd>';

	echo '
			</dl>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="submit" onclick="start_progress()" name="transfer" value="', Lang::getTxt('attachment_transfer_now', file: 'Admin'), '" class="button">
			<div id="progress_msg"></div>
			<div id="show_progress" class="padding"></div>
		</form>
		<script>
			function start_progress() {
				setTimeout(show_msg, 1000);
			}

			function show_msg() {
				$(\'#progress_msg\').html(\'<div><img src="', Theme::$current->settings['actual_images_url'], '/loading_sm.gif" alt="', Lang::getTxt('ajax_in_progress', file: 'General'), '" width="35" height="35"> ', Lang::getTxt('attachment_transfer_progress', file: 'Admin'), '<\/div>\');
				show_progress();
			}

			function show_progress() {
				$(\'#show_progress\').on("load", "progress.php");
				setTimeout(show_progress, 1500);
			}

		</script>
	</div><!-- .windowbg -->';
}

/**
 * The file repair page
 */
function template_attachment_repair()
{
	// If we've completed just let them know!
	if (Utils::$context['completed'])
		echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('repair_attachments_complete', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			', Lang::getTxt('repair_attachments_complete_desc', file: 'Admin'), '
		</div>
	</div>';

	// What about if no errors were even found?
	elseif (!Utils::$context['errors_found'])
		echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('repair_attachments_complete', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			', Lang::getTxt('repair_attachments_no_errors', file: 'Admin'), '
		</div>
	</div>';

	// Otherwise, I'm sad to say, we have a problem!
	else
	{
		echo '
	<div id="manage_attachments">
		<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('repair_attachments', file: 'Admin'), '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::getTxt('repair_attachments_error_desc', file: 'Admin'), '</p>';

		// Loop through each error reporting the status
		foreach (Utils::$context['repair_errors'] as $error => $number)
			if (!empty($number))
				echo '
				<input type="checkbox" name="to_fix[]" id="', $error, '" value="', $error, '">
				<label for="', $error, '">', Lang::getTxt('attach_repair_' . $error, [$number], file: 'Admin'), '</label><br>';

		echo '
				<br>
				<input type="submit" value="', Lang::getTxt('repair_attachments_continue', file: 'Admin'), '" class="button">
				<input type="submit" name="cancel" value="', Lang::getTxt('repair_attachments_cancel', file: 'Admin'), '" class="button">
			</div>
		</form>
	</div><!-- #manage_attachments -->';
	}
}

/**
 * The page that handles managing attachment paths.
 */
function template_attachment_paths()
{
	if (!empty(Config::$modSettings['attachment_basedirectories']))
		template_show_list('base_paths');

	template_show_list('attach_paths');
}
