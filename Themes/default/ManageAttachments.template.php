<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
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
			<h3 class="catbg">', Lang::$txt['attachment_stats'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt><strong>', Lang::$txt['attachment_total'], ':</strong></dt>
				<dd>', Utils::$context['num_attachments'], '</dd>
				<dt><strong>', Lang::$txt['attachment_manager_total_avatars'], ':</strong></dt>
				<dd>', Utils::$context['num_avatars'], '</dd>
				<dt><strong>', Lang::$txt['attachmentdir_size'], ':</strong></dt>
				<dd>', Utils::$context['attachment_total_size'], ' ', Lang::$txt['kilobyte'], '</dd>
				<dt><strong>', Lang::$txt['attach_current_dir'], ':</strong></dt>
				<dd class="word_break">', Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']], '</dd>
				<dt><strong>', Lang::$txt['attachmentdir_size_current'], ':</strong></dt>
				<dd>', Utils::$context['attachment_current_size'], ' ', Lang::$txt['kilobyte'], '</dd>
				<dt><strong>', Lang::$txt['attachment_space'], ':</strong></dt>
				<dd>', isset(Utils::$context['attachment_space']) ? Utils::$context['attachment_space'] . ' ' . Lang::$txt['kilobyte'] : Lang::$txt['attachmentdir_size_not_set'], '</dd>
				<dt><strong>', Lang::$txt['attachmentdir_files_current'], ':</strong></dt>
				<dd>', Utils::$context['attachment_current_files'], '</dd>
				<dt><strong>', Lang::$txt['attachment_files'], ':</strong></dt>
				<dd>', isset(Utils::$context['attachment_files']) ? Utils::$context['attachment_files'] : Lang::$txt['attachmentdir_files_not_set'], '</dd>
			</dl>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['attachment_integrity_check'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=repair;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>', Lang::$txt['attachment_integrity_check_desc'], '</p>
				<input type="submit" name="repair" value="', Lang::$txt['attachment_check_now'], '" class="button">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['attachment_pruning'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="return confirm(\'', Lang::$txt['attachment_pruning_warning'], '\');">
				<dl class="settings">
					<dt>', Lang::$txt['attachment_remove_old'], '</dt>
					<dd><input type="number" name="age" value="25" size="4"> ', Lang::$txt['days_word'], '</dd>
					<dt>', Lang::$txt['attachment_pruning_message'], '</dt>
					<dd><input type="text" name="notice" value="', Lang::$txt['attachment_delete_admin'], '" size="40"></dd>
					<input type="submit" name="remove" value="', Lang::$txt['remove'], '" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="byage">
				</dl>
			</form>
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="return confirm(\'', Lang::$txt['attachment_pruning_warning'], '\');">
				<dl class="settings">
					<dt>', Lang::$txt['attachment_remove_size'], '</dt>
					<dd><input type="number" name="size" id="size" value="100" size="4"> ', Lang::$txt['kilobyte'], '</dd>
					<dt>', Lang::$txt['attachment_pruning_message'], '</dt>
					<dd><input type="text" name="notice" value="', Lang::$txt['attachment_delete_admin'], '" size="40"></dd>
					<input type="submit" name="remove" value="', Lang::$txt['remove'], '" class="button">
					<input type="hidden" name="type" value="attachments">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="bysize">
				</dl>
			</form>
			<form action="', Config::$scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="return confirm(\'', Lang::$txt['attachment_pruning_warning'], '\');">
				<dl class="settings">
					<dt>', Lang::$txt['attachment_manager_avatars_older'], '</dt>
					<dd><input type="number" name="age" value="45" size="4"> ', Lang::$txt['days_word'], '</dd>
					<input type="submit" name="remove" value="', Lang::$txt['remove'], '" class="button">
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
		<h3 class="catbg">', Lang::$txt['attachment_transfer'], '</h3>
	</div>
	<div class="windowbg">
		<form action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=transfer" method="post" accept-charset="', Utils::$context['character_set'], '">
			<p>', Lang::$txt['attachment_transfer_desc'], '</p>
			<dl class="settings">
				<dt>', Lang::$txt['attachment_transfer_from'], '</dt>
				<dd>
					<select name="from">
						<option value="0">', Lang::$txt['attachment_transfer_select'], '</option>';

	foreach (Utils::$context['attach_dirs'] as $id => $dir)
		echo '
						<option value="', $id, '">', $dir, '</option>';

	echo '
					</select>
				</dd>
				<dt>', Lang::$txt['attachment_transfer_auto'], '</dt>
				<dd>
					<select name="auto">
						<option value="0">', Lang::$txt['attachment_transfer_auto_select'], '</option>
						<option value="-1">', Lang::$txt['attachment_transfer_forum_root'], '</option>';

	if (!empty(Utils::$context['base_dirs']))
		foreach (Utils::$context['base_dirs'] as $id => $dir)
			echo '
						<option value="', $id, '">', $dir, '</option>';
	else
		echo '
						<option value="0" disabled>', Lang::$txt['attachment_transfer_no_base'], '</option>';

	echo '
					</select>
				</dd>
				<dt>', Lang::$txt['attachment_transfer_to'], '</dt>
				<dd>
					<select name="to">
						<option value="0">', Lang::$txt['attachment_transfer_select'], '</option>';

	foreach (Utils::$context['attach_dirs'] as $id => $dir)
		echo '
						<option value="', $id, '">', $dir, '</option>';

	echo '
					</select>
				</dd>';

	if (!empty(Config::$modSettings['attachmentDirFileLimit']))
		echo '
				<dt>', Lang::$txt['attachment_transfer_empty'], '</dt>
				<dd><input type="checkbox" name="empty_it"', Utils::$context['checked'] ? ' checked' : '', '></dd>';

	echo '
			</dl>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="submit" onclick="start_progress()" name="transfer" value="', Lang::$txt['attachment_transfer_now'], '" class="button">
			<div id="progress_msg"></div>
			<div id="show_progress" class="padding"></div>
		</form>
		<script>
			function start_progress() {
				setTimeout(\'show_msg()\', 1000);
			}

			function show_msg() {
				$(\'#progress_msg\').html(\'<div><img src="', Theme::$current->settings['actual_images_url'], '/loading_sm.gif" alt="', Lang::$txt['ajax_in_progress'], '" width="35" height="35"> ', Lang::$txt['attachment_transfer_progress'], '<\/div>\');
				show_progress();
			}

			function show_progress() {
				$(\'#show_progress\').on("load", "progress.php");
				setTimeout(\'show_progress()\', 1500);
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
			<h3 class="catbg">', Lang::$txt['repair_attachments_complete'], '</h3>
		</div>
		<div class="windowbg">
			', Lang::$txt['repair_attachments_complete_desc'], '
		</div>
	</div>';

	// What about if no errors were even found?
	elseif (!Utils::$context['errors_found'])
		echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['repair_attachments_complete'], '</h3>
		</div>
		<div class="windowbg">
			', Lang::$txt['repair_attachments_no_errors'], '
		</div>
	</div>';

	// Otherwise, I'm sad to say, we have a problem!
	else
	{
		echo '
	<div id="manage_attachments">
		<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['repair_attachments'], '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::$txt['repair_attachments_error_desc'], '</p>';

		// Loop through each error reporting the status
		foreach (Utils::$context['repair_errors'] as $error => $number)
			if (!empty($number))
				echo '
				<input type="checkbox" name="to_fix[]" id="', $error, '" value="', $error, '">
				<label for="', $error, '">', sprintf(Lang::$txt['attach_repair_' . $error], $number), '</label><br>';

		echo '
				<br>
				<input type="submit" value="', Lang::$txt['repair_attachments_continue'], '" class="button">
				<input type="submit" name="cancel" value="', Lang::$txt['repair_attachments_cancel'], '" class="button">
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

?>