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
 * Template for the database maintenance tasks.
 */
function template_maintain_database()
{
	// If maintenance has finished tell the user.
	if (!empty(Utils::$context['maintenance_finished']))
		echo '
	<div class="infobox">
		', sprintf(Lang::$txt['maintain_done'], Utils::$context['maintenance_finished']), '
	</div>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>', Lang::$txt['maintain_optimize_info'], '</p>
				<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>';

	// Show an option to convert the body column of the post table to MEDIUMTEXT or TEXT
	if (isset(Utils::$context['convert_to']))
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt[Utils::$context['convert_to'] . '_title'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=convertmsgbody" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>', Lang::$txt['mediumtext_introduction'], '</p>',
				Utils::$context['convert_to_suggest'] ? '<p class="infobox">' . Lang::$txt['convert_to_suggest_text'] . '</p>' : '', '
				<input type="submit" name="evaluate_conversion" value="', Lang::$txt['maintain_run_now'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>';

	// We might want to convert entities if we're on UTF-8.
	if (Utils::$context['convert_entities'])
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['entity_convert_title'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>', Lang::$txt['entity_convert_introduction'], '</p>
				<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>';

	echo '
	</div><!-- #manage_maintenance -->';
}

/**
 * Template for the routine maintenance tasks.
 */
function template_maintain_routine()
{
	// Starts off with general maintenance procedures.
	echo '
	<div id="manage_maintenance">';

	// If maintenance has finished tell the user.
	if (!empty(Utils::$context['maintenance_finished']))
		echo '
		<div class="infobox">
			', sprintf(Lang::$txt['maintain_done'], Utils::$context['maintenance_finished']), '
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_version'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=routine;activity=version" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_version_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</p>
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_errors'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=repairboards" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_errors_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</p>
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_recount'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_recount_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</p>
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_rebuild_settings'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=routine;activity=rebuild_settings" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_rebuild_settings_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</p>
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_logs'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=routine;activity=logs" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_logs_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</p>
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_cache'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_cache_info'], '
					<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</p>
			</form>
		</div>
	</div><!-- #manage_maintenance -->';
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
			setInnerHTML(document.getElementById("membersText"), membersSwap ? "', Lang::$txt['maintain_members_choose'], '" : "', Lang::$txt['maintain_members_all'], '");

			for (var i = 0; i < membersForm.length; i++)
			{
				if (membersForm.elements[i].type.toLowerCase() == "checkbox")
					membersForm.elements[i].checked = !membersSwap;
			}
		}

		function checkAttributeValidity()
		{
			origText = \'', Lang::$txt['reattribute_confirm'], '\';
			valid = true;

			// Do all the fields!
			if (!document.getElementById(\'to\').value)
				valid = false;
			warningMessage = origText.replace(/%member_to%/, document.getElementById(\'to\').value);

			if (document.getElementById(\'type_email\').checked)
			{
				if (!document.getElementById(\'from_email\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes(Lang::$txt['reattribute_confirm_email'], "'"), '\').replace(/%find%/, document.getElementById(\'from_email\').value);
			}
			else
			{
				if (!document.getElementById(\'from_name\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes(Lang::$txt['reattribute_confirm_username'], "'"), '\').replace(/%find%/, document.getElementById(\'from_name\').value);
			}

			document.getElementById(\'do_attribute\').disabled = valid ? \'\' : \'disabled\';

			setTimeout("checkAttributeValidity();", 500);
			return valid;
		}
		setTimeout("checkAttributeValidity();", 500);
	</script>
	<div id="manage_maintenance">';

	// If maintenance has finished, tell the user.
	if (!empty(Utils::$context['maintenance_finished']))
		echo '
		<div class="infobox">
			', sprintf(Lang::$txt['maintain_done'], Utils::$context['maintenance_finished']), '
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_reattribute_posts'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p><strong>', Lang::$txt['reattribute_guest_posts'], '</strong></p>
				<dl class="settings">
					<dt>
						<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked>', Lang::$txt['reattribute_email'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById(\'type_email\').checked = \'checked\'; document.getElementById(\'from_name\').value = \'\';">
					</dd>
					<dt>
						<label for="type_name"><input type="radio" name="type" id="type_name" value="name">', Lang::$txt['reattribute_username'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById(\'type_name\').checked = \'checked\'; document.getElementById(\'from_email\').value = \'\';">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<label for="to"><strong>', Lang::$txt['reattribute_current_member'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="">
					</dd>
				</dl>
				<p class="maintain_members">
					<input type="checkbox" name="posts" id="posts" checked>
					<label for="posts">', Lang::$txt['reattribute_increase_posts'], '</label>
				</p>
				<input type="submit" id="do_attribute" value="', Lang::$txt['reattribute'], '" onclick="if (!checkAttributeValidity()) return false;
				return confirm(warningMessage);" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', Config::$scripturl, '?action=helpadmin;help=maintenance_members" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a> ', Lang::$txt['maintain_members'], '
			</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="', Utils::$context['character_set'], '" id="membersForm">
				<p>
					<a id="membersLink"></a>', Lang::$txt['maintain_members_since1'], '
					<select name="del_type">
						<option value="activated" selected>', Lang::$txt['maintain_members_activated'], '</option>
						<option value="logged">', Lang::$txt['maintain_members_logged_in'], '</option>
					</select>
					', Lang::$txt['maintain_members_since2'], '
					<input type="number" name="maxdays" value="30" size="3">', Lang::$txt['maintain_members_since3'], '
				</p>
				<p>
					<a href="#membersLink" onclick="swapMembers();"><img src="', Theme::$current->settings['images_url'], '/selected.png" alt="+" id="membersIcon"></a> <a href="#membersLink" onclick="swapMembers();" id="membersText" style="font-weight: bold;">', Lang::$txt['maintain_members_all'], '</a>
				</p>
				<div style="display: none;" id="membersPanel">';

	foreach (Utils::$context['membergroups'] as $group)
		echo '
					<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked> ', $group['name'], '</label><br>';

	echo '
				</div>
				<input type="submit" value="', Lang::$txt['maintain_old_remove'], '" data-confirm="', Lang::$txt['maintain_members_confirm'], '" class="button you_sure">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_recountposts'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="', Utils::$context['character_set'], '" id="membersRecountForm">
				<p>', Lang::$txt['maintain_recountposts_info'], '</p>
				<input type="submit" value="', Lang::$txt['maintain_run_now'], '" class="button">
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
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
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
	if (!empty(Utils::$context['maintenance_finished']))
		echo '
	<div class="infobox">
		', sprintf(Lang::$txt['maintain_done'], Utils::$context['maintenance_finished']), '
	</div>';

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	echo '
	<script>
		var rotSwap = false;
		function swapRot()
		{
			rotSwap = !rotSwap;

			// Toggle icon
			document.getElementById("rotIcon").src = smf_images_url + (rotSwap ? "/selected_open.png" : "/selected.png");
			setInnerHTML(document.getElementById("rotText"), rotSwap ? ', Utils::JavaScriptEscape(Lang::$txt['maintain_old_choose']), ' : ', Utils::JavaScriptEscape(Lang::$txt['maintain_old_all']), ');

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
			<h3 class="catbg">', Lang::$txt['maintain_old'], '</h3>
		</div>
		<div class="windowbg">
			<div class="flow_auto">
				<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="', Utils::$context['character_set'], '">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', Lang::$txt['maintain_old_since_days1'], '<input type="number" name="maxdays" value="30" size="3">', Lang::$txt['maintain_old_since_days2'], '
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing"> ', Lang::$txt['maintain_old_nothing_else'], '</label><br>
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" checked> ', Lang::$txt['maintain_old_are_moved'], '</label><br>
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked"> ', Lang::$txt['maintain_old_are_locked'], '</label><br>
					</p>
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" checked> ', Lang::$txt['maintain_old_are_not_stickied'], '</label><br>
					</p>
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', Theme::$current->settings['images_url'], '/selected.png" alt="+" id="rotIcon"></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;">', Lang::$txt['maintain_old_all'], '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count(Utils::$context['categories']) / 2);

	$i = 0;
	foreach (Utils::$context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend>', $category['name'], '</legend>
								<ul>';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;">
										<label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked>', $board['name'], '</label>
									</li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div><!-- .floatleft -->
						<div class="floatright" style="width: 49%;">';
	}

	echo '
						</div>
					</div><!-- #rotPanel -->
					<input type="submit" value="', Lang::$txt['maintain_old_remove'], '" data-confirm="', Lang::$txt['maintain_old_confirm'], '" class="button you_sure">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
				</form>
			</div><!-- .flow_auto -->
		</div><!-- .windowbg -->

		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['maintain_old_drafts'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=olddrafts" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					', Lang::$txt['maintain_old_drafts_days'], ' <input type="number" name="draftdays" value="', (!empty(Config::$modSettings['drafts_keep_days']) ? Config::$modSettings['drafts_keep_days'] : 30), '" size="3"> ', Lang::$txt['days_word'], '
				</p>
				<input type="submit" value="', Lang::$txt['maintain_old_remove'], '" data-confirm="', Lang::$txt['maintain_old_drafts_confirm'], '" class="button you_sure">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['move_topics_maintenance'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="', Utils::$context['character_set'], '">
				<p>
					<label for="id_board_from">', Lang::$txt['move_topics_from'], ' </label>
					<select name="id_board_from" id="id_board_from">
						<option disabled>(', Lang::$txt['move_topics_select_board'], ')</option>';

	// From board
	foreach (Utils::$context['categories'] as $category)
	{
		echo '
						<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
							<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';

		echo '
						</optgroup>';
	}

	echo '
					</select>
					<label for="id_board_to">', Lang::$txt['move_topics_to'], '</label>
					<select name="id_board_to" id="id_board_to">
						<option disabled>(', Lang::$txt['move_topics_select_board'], ')</option>';

	// To board
	foreach (Utils::$context['categories'] as $category)
	{
		echo '
						<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
							<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';

		echo '
						</optgroup>';
	}
	echo '
					</select>
				</p>
				<p>
					', Lang::$txt['move_topics_older_than'], '
					<input type="number" name="maxdays" value="30" size="3">
					', Lang::$txt['manageposts_days'], ' (', Lang::$txt['move_zero_all'], ')
				</p>
				<p>
					<label for="move_type_locked"><input type="checkbox" name="move_type_locked" id="move_type_locked" checked> ', Lang::$txt['move_type_locked'], '</label><br>
					<label for="move_type_sticky"><input type="checkbox" name="move_type_sticky" id="move_type_sticky"> ', Lang::$txt['move_type_sticky'], '</label><br>
				</p>
				<input type="submit" value="', Lang::$txt['move_topics_now'], '" onclick="if (document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].disabled || document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_to\').selectedIndex].disabled) return false; var confirmText = \'', Lang::$txt['move_topics_confirm'] . '\'; return confirm(confirmText.replace(/%board_from%/, document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')).replace(/%board_to%/, document.getElementById(\'id_board_to\').options[document.getElementById(\'id_board_to\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')));" class="button">
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
			<h3 class="catbg">', Lang::$txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg">
			<p>
				', Lang::$txt['database_numb_tables'], '<br>
				', Lang::$txt['database_optimize_attempt'], '<br>';

	// List each table being optimized...
	foreach (Utils::$context['optimized_tables'] as $table)
		echo '
				', sprintf(Lang::$txt['database_optimizing'], $table['name'], $table['data_freed']), '<br>';

	// How did we go?
	echo '
				<br>
				', Utils::$context['num_tables_optimized'] == 0 ? Lang::$txt['database_already_optimized'] : Utils::$context['num_tables_optimized'] . ' ' . Lang::$txt['database_optimized'];

	echo '
			</p>
			<p><a href="', Config::$scripturl, '?action=admin;area=maintain">', Lang::$txt['maintain_return'], '</a></p>
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
			<h3 class="catbg">', Lang::$txt['entity_convert_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['entity_convert_introduction'], '</p>
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities;start=0;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="submit" value="', Lang::$txt['entity_convert_proceed'], '" class="button">
			</form>
		</div>
	</div>';
}

/**
 * Template for converting posts to UTF-8.
 */
function template_convert_msgbody()
{
	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt[Utils::$context['convert_to'] . '_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['body_checking_introduction'], '</p>';

	if (!empty(Utils::$context['exceeding_messages']))
	{
		echo '
			<p class="noticebox">', Lang::$txt['exceeding_messages'], '</p>
			<ul>
				<li>
				', implode('</li><li>', Utils::$context['exceeding_messages']), '
				</li>
			</ul>';

		if (!empty(Utils::$context['exceeding_messages_morethan']))
			echo '
			<p>', Utils::$context['exceeding_messages_morethan'], '</p>';
	}
	else
		echo '
			<p class="infobox">', Lang::$txt['convert_to_text'], '</p>';

	echo '
			<form action="', Config::$scripturl, '?action=admin;area=maintain;sa=database;activity=convertmsgbody" method="post" accept-charset="', Utils::$context['character_set'], '">
			<hr>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-maint_token_var'], '" value="', Utils::$context['admin-maint_token'], '">
			<input type="submit" name="do_conversion" value="', Lang::$txt['entity_convert_proceed'], '" class="button">
			</form>
		</div><!-- .windowbg -->
	</div><!-- #manage_maintenance -->';
}

?>