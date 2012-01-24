<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// Template template wraps around the simple settings page to add javascript functionality.
function template_avatar_settings_above()
{
}

function template_avatar_settings_below()
{
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
	var fUpdateStatus = function ()
	{
		document.getElementById("avatar_max_width_external").disabled = document.getElementById("avatar_download_external").checked;
		document.getElementById("avatar_max_height_external").disabled = document.getElementById("avatar_download_external").checked;
		document.getElementById("avatar_action_too_large").disabled = document.getElementById("avatar_download_external").checked;
		document.getElementById("custom_avatar_dir").disabled = document.getElementById("custom_avatar_enabled").value == 0;
		document.getElementById("custom_avatar_url").disabled = document.getElementById("custom_avatar_enabled").value == 0;

	}
	addLoadEvent(fUpdateStatus);
// ]]></script>
';
}

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['attachment_manager_browse_files'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse">', $context['browse_type'] === 'attachments' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '', $txt['attachment_manager_attachments'], '</a> |
				<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse;avatars">', $context['browse_type'] === 'avatars' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '', $txt['attachment_manager_avatars'], '</a> |
				<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse;thumbs">', $context['browse_type'] === 'thumbs' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '', $txt['attachment_manager_thumbs'], '</a>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>';

	template_show_list('file_list');
	echo '
	<br class="clear" />';

}

function template_maintenance()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['attachment_stats'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="settings">
					<dt><strong>', $txt['attachment_total'], ':</strong></dt><dd>', $context['num_attachments'], '</dd>
					<dt><strong>', $txt['attachment_manager_total_avatars'], ':</strong></dt><dd>', $context['num_avatars'], '</dd>
					<dt><strong>', $txt['attachmentdir_size' . ($context['attach_multiple_dirs'] ? '_current' : '')], ':</strong></dt><dd>', $context['attachment_total_size'], ' ', $txt['kilobyte'], '</dd>
					<dt><strong>', $txt['attachment_space' . ($context['attach_multiple_dirs'] ? '_current' : '')], ':</strong></dt><dd>', isset($context['attachment_space']) ? $context['attachment_space'] . ' ' . $txt['kilobyte'] : $txt['attachmentdir_size_not_set'], '</dd>
				</dl>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['attachment_integrity_check'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['attachment_integrity_check_desc'], '</p>
					<input type="submit" name="submit" value="', $txt['attachment_check_now'], '" class="button_submit" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['attachment_pruning'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', $context['character_set'], '" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');" style="margin: 0 0 2ex 0;">
					', $txt['attachment_remove_old'], ' <input type="text" name="age" value="25" size="4" class="input_text" /> ', $txt['days_word'], '<br />
					', $txt['attachment_pruning_message'], ': <input type="text" name="notice" value="', $txt['attachment_delete_admin'], '" size="40" class="input_text" /><br />
					<input type="submit" name="submit" value="', $txt['remove'], '" class="button_submit" />
					<input type="hidden" name="type" value="attachments" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="byAge" />
				</form>
				<hr />
				<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', $context['character_set'], '" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');" style="margin: 0 0 2ex 0;">
					', $txt['attachment_remove_size'], ' <input type="text" name="size" id="size" value="100" size="4" class="input_text" /> ', $txt['kilobyte'], '<br />
					', $txt['attachment_pruning_message'], ': <input type="text" name="notice" value="', $txt['attachment_delete_admin'], '" size="40" class="input_text" /><br />
					<input type="submit" name="submit" value="', $txt['remove'], '" class="button_submit" />
					<input type="hidden" name="type" value="attachments" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="bySize" />
				</form>
				<hr />
				<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="', $context['character_set'], '" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');" style="margin: 0 0 2ex 0;">
					', $txt['attachment_manager_avatars_older'], ' <input type="text" name="age" value="45" size="4" class="input_text" /> ', $txt['days_word'], '<br />
					<input type="submit" name="submit" value="', $txt['remove'], '" class="button_submit" />
					<input type="hidden" name="type" value="avatars" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="byAge" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

function template_attachment_repair()
{
	global $context, $txt, $scripturl, $settings;

	// If we've completed just let them know!
	if ($context['completed'])
	{
		echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['repair_attachments_complete'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				', $txt['repair_attachments_complete_desc'], '
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
	}

	// What about if no errors were even found?
	elseif (!$context['errors_found'])
	{
		echo '
	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['repair_attachments_complete'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				', $txt['repair_attachments_no_errors'], '
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
	}
	// Otherwise, I'm sad to say, we have a problem!
	else
	{
		echo '
	<div id="manage_attachments">
		<form action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['repair_attachments'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['repair_attachments_error_desc'], '</p>';

		// Loop through each error reporting the status
		foreach ($context['repair_errors'] as $error => $number)
		{
			if (!empty($number))
			echo '
					<input type="checkbox" name="to_fix[]" id="', $error, '" value="', $error, '" class="input_check" />
					<label for="', $error, '">', sprintf($txt['attach_repair_' . $error], $number), '</label><br />';
		}

		echo '		<br />
					<input type="submit" value="', $txt['repair_attachments_continue'], '" class="button_submit" />
					<input type="submit" name="cancel" value="', $txt['repair_attachments_cancel'], '" class="button_submit" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
	}
}

function template_attachment_paths()
{
	template_show_list('attach_paths');
}

?>