<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * Wraps every maintenance sub-action.
 */
function template_maintain_above()
{
	echo '
	<div id="manage_maintenance">';

	// If maintenance has finished tell the user.
	if (!empty(Utils::$context['maintenance_finished'])) {
		echo '
		<div class="infobox">
			', Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin'), '
		</div>';
	}
}

function template_maintain_below()
{
	echo '
	</div><!-- #manage_maintenance -->';
}

/**
 * Lists the tasks of a maintenance sub-action as a single pick-one form.
 *
 * Utils::$context['options'] is keyed by activity name. A task can override its
 * label and description with 'title' and 'info', and add markup after the
 * description with 'after'.
 */
function template_maintain_options()
{
	echo '
		<form action="', Utils::$context['post_url'], '" method="post" accept-charset="UTF-8" class="windowbg option_form">';

	foreach (Utils::$context['options'] as $activity => $option) {
		echo '
			<label>
				<input type="radio" name="activity" value="', $activity, '">
				<strong>', $option['title'] ?? Lang::getTxt('maintain_' . $activity, file: 'ManageMaintenance'), '</strong>
				<p>', $option['info'] ?? Lang::getTxt('maintain_' . $activity . '_info', file: 'ManageMaintenance'), $option['after'] ?? '', '</p>
			</label>';
	}

	echo '
			<input type="submit" value="', Lang::getTxt('maintain_run_now', file: 'ManageMaintenance'), '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
		</form>';
}

/**
 * Template for the member maintenance tasks.
 */
function template_maintain_members()
{
	echo '
	<script>
		var warningMessage = \'\';
		var membersSwap = false;

		function swapMembers()
		{
			membersSwap = !membersSwap;
			var membersForm = document.getElementById(\'membersForm\');

			$("#membersPanel").slideToggle(300);

			document.getElementById("membersIcon").src = smf_images_url + (membersSwap ? "/selected_open.png" : "/selected.png");
			setInnerHTML(document.getElementById("membersText"), membersSwap ? "', Lang::getTxt('maintain_members_choose', file: 'ManageMaintenance'), '" : "', Lang::getTxt('maintain_members_all', file: 'ManageMaintenance'), '");

			for (var i = 0; i < membersForm.length; i++)
			{
				if (membersForm.elements[i].type.toLowerCase() == "checkbox")
					membersForm.elements[i].checked = !membersSwap;
			}
		}

		function checkAttributeValidity()
		{
			origText = \'', Lang::getTxt('reattribute_confirm', file: 'ManageMaintenance'), '\';
			valid = true;

			// Do all the fields!
			if (!document.getElementById(\'to\').value)
				valid = false;
			warningMessage = origText.replace(/%member_to%/, document.getElementById(\'to\').value);

			if (document.getElementById(\'type_email\').checked)
			{
				if (!document.getElementById(\'from_email\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes(Lang::getTxt('reattribute_confirm_email', file: 'ManageMaintenance'), "'"), '\').replace(/%find%/, document.getElementById(\'from_email\').value);
			}
			else
			{
				if (!document.getElementById(\'from_name\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes(Lang::getTxt('reattribute_confirm_username', file: 'ManageMaintenance'), "'"), '\').replace(/%find%/, document.getElementById(\'from_name\').value);
			}

			document.getElementById(\'do_attribute\').disabled = valid ? \'\' : \'disabled\';

			setTimeout("checkAttributeValidity();", 500);
			return valid;
		}
		setTimeout("checkAttributeValidity();", 500);
	</script>
	<div id="manage_maintenance">';

	// If maintenance has finished, tell the user.
	if (!empty(Utils::$context['maintenance_finished'])) {
		echo '
		<div class="infobox">
			', Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin'), '
		</div>';
	}

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('maintain_reattribute_posts', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong>', Lang::getTxt('reattribute_guest_posts', file: 'ManageMaintenance'), '</strong></p>
				<dl class="settings">
					<dt>
						<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked>', Lang::getTxt('reattribute_email', file: 'ManageMaintenance'), '</label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById(\'type_email\').checked = \'checked\'; document.getElementById(\'from_name\').value = \'\';">
					</dd>
					<dt>
						<label for="type_name"><input type="radio" name="type" id="type_name" value="name">', Lang::getTxt('reattribute_username', file: 'ManageMaintenance'), '</label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById(\'type_name\').checked = \'checked\'; document.getElementById(\'from_email\').value = \'\';">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<label for="to"><strong>', Lang::getTxt('reattribute_current_member', file: 'ManageMaintenance'), '</strong></label>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="">
					</dd>
				</dl>
				<p class="maintain_members">
					<input type="checkbox" name="posts" id="posts" checked>
					<label for="posts">', Lang::getTxt('reattribute_increase_posts', file: 'ManageMaintenance'), '</label>
				</p>
				<input type="submit" id="do_attribute" value="', Lang::getTxt('reattribute', file: 'ManageMaintenance'), '" onclick="if (!checkAttributeValidity()) return false;
				return confirm(warningMessage);" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', Config::$scripturl, '?action=helpadmin;help=maintenance_members" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a> ', Lang::getTxt('maintain_members', file: 'ManageMaintenance'), '
			</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
				<div class="padding">
					<a id="membersLink"></a>',
					Lang::getTxt(
						'maintain_members_since',
						[
							'input_condition' => '<select name="del_type"><option value="activated" selected>' . Lang::getTxt('maintain_members_activated', file: 'ManageMaintenance') . '</option><option value="logged">' . Lang::getTxt('maintain_members_logged_in', file: 'ManageMaintenance') . '</option></select>',
							'input_number' => '<input type="number" name="maxdays" value="30" size="3">',
						],
						file: 'ManageMaintenance',
					), '
				</div>
				<div class="padding">';

	if (!empty(Config::$modSettings['always_anonymize_deleted_accounts'])) {
		echo '
					' . Lang::getTxt('deleteAccount_anonymize_forced', file: 'Profile');
	} else {
		echo '
					<label for="anonymize">
						<input type="checkbox" name="anonymize" id="anonymize" value="1"> ' . Lang::getTxt('deleteAccount_anonymize', file: 'Profile') . '
					</label>';
	}

	echo '
				</div>
				<div class="padding">
					<a href="#membersLink" onclick="swapMembers();"><img src="', Theme::$current->settings['images_url'], '/selected.png" alt="+" id="membersIcon"></a> <a href="#membersLink" onclick="swapMembers();" id="membersText" style="font-weight: bold;">', Lang::getTxt('maintain_members_all', file: 'ManageMaintenance'), '</a>
				</div>
				<div style="display: none;" id="membersPanel">';

	foreach (Utils::$context['membergroups'] as $group) {
		echo '
					<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked> ', $group['name'], '</label><br>';
	}

	echo '
				</div>
				<input type="submit" value="', Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance'), '" data-confirm="', Lang::getTxt('maintain_members_confirm', file: 'ManageMaintenance'), '" class="button you_sure">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('maintain_recountposts', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8" id="membersRecountForm">
				<p>', Lang::getTxt('maintain_recountposts_info', file: 'ManageMaintenance'), '</p>
				<input type="submit" value="', Lang::getTxt('maintain_run_now', file: 'ManageMaintenance'), '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>
	</div><!-- #manage_maintenance -->

	<script>
		var oAttributeMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAttributeMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'attributeMember\',
			sControlId: \'to\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::getTxt('autosuggest_delete_item', file: 'General'), '\',
			bItemList: false
		});
	</script>';
}

/**
 * Template for the topic maintenance tasks.
 */
function template_maintain_topics()
{
	// If maintenance has finished tell the user.
	if (!empty(Utils::$context['maintenance_finished'])) {
		echo '
	<div class="infobox">
		', Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin'), '
	</div>';
	}

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	echo '
	<script>
		var rotSwap = false;
		function swapRot()
		{
			rotSwap = !rotSwap;

			// Toggle icon
			document.getElementById("rotIcon").src = smf_images_url + (rotSwap ? "/selected_open.png" : "/selected.png");
			setInnerHTML(document.getElementById("rotText"), rotSwap ? ', Utils::escapeJavaScript(Lang::getTxt('maintain_old_choose', file: 'ManageMaintenance')), ' : ', Utils::escapeJavaScript(Lang::getTxt('maintain_old_all', file: 'ManageMaintenance')), ');

			// Toggle panel
			$("#rotPanel").slideToggle(300);

			// Toggle checkboxes
			var rotPanel = document.getElementById(\'rotPanel\');
			var oBoardCheckBoxes = rotPanel.getElementsByTagName(\'input\');
			for (var i = 0; i < oBoardCheckBoxes.length; i++)
			{
				if (oBoardCheckBoxes[i].type.toLowerCase() == "checkbox")
					oBoardCheckBoxes[i].checked = !rotSwap;
			}
		}
	</script>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('maintain_old', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<div class="flow_auto">
				<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', Lang::getTxt('maintain_old_since_days', ['input_number' => '<input type="number" name="maxdays" value="30" size="3">'], file: 'ManageMaintenance'), '
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing"> ', Lang::getTxt('maintain_old_nothing_else', file: 'ManageMaintenance'), '</label><br>
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" checked> ', Lang::getTxt('maintain_old_are_moved', file: 'ManageMaintenance'), '</label><br>
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked"> ', Lang::getTxt('maintain_old_are_locked', file: 'ManageMaintenance'), '</label><br>
					</p>
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" checked> ', Lang::getTxt('maintain_old_are_not_stickied', file: 'ManageMaintenance'), '</label><br>
					</p>
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', Theme::$current->settings['images_url'], '/selected.png" alt="+" id="rotIcon"></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;">', Lang::getTxt('maintain_old_all', file: 'ManageMaintenance'), '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count(Utils::$context['categories']) / 2);

	$i = 0;

	foreach (Utils::$context['categories'] as $category) {
		echo '
							<fieldset>
								<legend>', $category['name'], '</legend>
								<ul>';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board) {
			echo '
									<li style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;">
										<label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked>', $board['name'], '</label>
									</li>';
		}

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle) {
			echo '
						</div><!-- .floatleft -->
						<div class="floatright" style="width: 49%;">';
		}
	}

	echo '
						</div>
					</div><!-- #rotPanel -->
					<input type="submit" value="', Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance'), '" data-confirm="', Lang::getTxt('maintain_old_confirm', file: 'ManageMaintenance'), '" class="button you_sure">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</form>
			</div><!-- .flow_auto -->
		</div><!-- .windowbg -->

		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('maintain_old_drafts', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=olddrafts" method="post" accept-charset="UTF-8">
				<p>
					', Lang::getTxt('maintain_old_drafts_days', ['input_number' => '<input type="number" name="draftdays" value="' . (!empty(Config::$modSettings['drafts_keep_days']) ? Config::$modSettings['drafts_keep_days'] : 30) . '" size="3">'], file: 'ManageMaintenance'), '
				</p>
				<input type="submit" value="', Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance'), '" data-confirm="', Lang::getTxt('maintain_old_drafts_confirm', file: 'ManageMaintenance'), '" class="button you_sure">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('move_topics_maintenance', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
				<p>
					';

	$board_select = [
		'<option disabled selected>(' . Lang::getTxt('move_topics_select_board', file: 'ManageMaintenance') . ')</option>',
	];

	foreach (Utils::$context['categories'] as $category) {
		$board_select[] = '<optgroup label="' . $category['name'] . '">';

		foreach ($category['boards'] as $board) {
			$board_select[] = "\t" . '<option value="' . $board['id'] . '"> ' . str_repeat('==', $board['child_level']) . '=&gt;&nbsp;' . $board['name'] . '</option>';
		}

		$board_select[] = '</optgroup>';
	}


	echo Lang::getTxt(
		'move_topics_from',
		[
			'old' => '
						<select name="id_board_from" id="id_board_from">
							' . implode("\n\t\t\t\t\t\t", $board_select) . '
						</select>',
			'new' => '
						<select name="id_board_to" id="id_board_to">
							' . implode("\n\t\t\t\t\t\t", $board_select) . '
						</select>',
		],
		file: 'ManageMaintenance',
	);

	echo '
				</p>
				<p>
					', Lang::getTxt('move_topics_older_than', ['input_number' => '<input type="number" name="maxdays" value="30" size="3">'], file: 'ManageMaintenance'), ' (', Lang::getTxt('move_zero_all', file: 'ManageMaintenance'), ')
				</p>
				<p>
					<label for="move_type_locked"><input type="checkbox" name="move_type_locked" id="move_type_locked" checked> ', Lang::getTxt('move_type_locked', file: 'ManageMaintenance'), '</label><br>
					<label for="move_type_sticky"><input type="checkbox" name="move_type_sticky" id="move_type_sticky"> ', Lang::getTxt('move_type_sticky', file: 'ManageMaintenance'), '</label><br>
				</p>
				<input type="submit" value="', Lang::getTxt('move_topics_now', file: 'ManageMaintenance'), '" onclick="if (document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].disabled || document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_to\').selectedIndex].disabled) return false; var confirmText = \'', Lang::getTxt('move_topics_confirm', file: 'ManageMaintenance') . '\'; return confirm(confirmText.replace(/%board_from%/, document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')).replace(/%board_to%/, document.getElementById(\'id_board_to\').options[document.getElementById(\'id_board_to\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')));" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div><!-- .windowbg -->
	</div><!-- #manage_maintenance -->';
}

/**
 * Simple template for showing results of our optimization...
 */
function template_optimize()
{
	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('maintain_optimize', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<p>
				', Utils::$context['database_numb_tables'], '<br>
				', Lang::getTxt('database_optimize_attempt', file: 'ManageMaintenance'), '<br>';

	// List each table being optimized...
	foreach (Utils::$context['optimized_tables'] as $table) {
		echo '
				', Lang::getTxt('database_optimizing', [$table['name'], round($table['data_freed'], 2)], file: 'ManageMaintenance'), '<br>';
	}

	// How did we go?
	echo '
				<br>
				', Utils::$context['num_tables_optimized'] == 0 ? Lang::getTxt('database_already_optimized', file: 'ManageMaintenance') : Utils::$context['num_tables_optimized'] . ' ' . Lang::getTxt('database_optimized', file: 'ManageMaintenance');

	echo '
			</p>
			<p><a href="', Config::$scripturl, '?action=admin;area=maintain">', Lang::getTxt('maintain_return', file: 'ManageMaintenance'), '</a></p>
		</div><!-- .windowbg -->
	</div><!-- #manage_maintenance -->';
}

/**
 * Template for converting entities to UTF-8 characters
 */
function template_convert_entities()
{
	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('entity_convert_title', file: 'ManageMaintenance'), '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::getTxt('entity_convert_introduction', file: 'ManageMaintenance'), '</p>
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities;start=0;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
			<input type="submit" value="', Lang::getTxt('entity_convert_proceed', file: 'ManageMaintenance'), '" class="button">
			</form>
		</div>
	</div>';
}
