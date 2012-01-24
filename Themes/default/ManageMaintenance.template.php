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

// Template for the database maintenance tasks.
function template_maintain_database()
{
	global $context, $settings, $options, $txt, $scripturl, $db_type, $modSettings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_optimize_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>

		<div class="cat_bar">
			<h3 class="catbg">
			<span class="ie6_header floatleft"><a href="', $scripturl, '?action=helpadmin;help=maintenance_backup" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a> ', $txt['maintain_backup'], '</span>
			</h3>
		</div>

		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=backup" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_backup_info'], '</p>';

	if ($db_type == 'sqlite')
		echo '
					<p><input type="submit" value="', $txt['maintain_backup_save'], '" id="submitDump" class="button_submit" /></p>';
	else
		echo '
					<p><label for="struct"><input type="checkbox" name="struct" id="struct" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" class="input_check" checked="checked" /> ', $txt['maintain_backup_struct'], '</label><br />
					<label for="data"><input type="checkbox" name="data" id="data" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" checked="checked" class="input_check" /> ', $txt['maintain_backup_data'], '</label><br />
					<label for="compress"><input type="checkbox" name="compress" id="compress" value="gzip" checked="checked" class="input_check" /> ', $txt['maintain_backup_gz'], '</label></p>
					<p><input type="submit" value="', $txt['maintain_backup_save'], '" id="submitDump" onclick="return document.getElementById(\'struct\').checked || document.getElementById(\'data\').checked;" class="button_submit" /></p>';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>';

	// Show an option to convert to UTF-8 if we're not on UTF-8 yet.
	if ($context['convert_utf8'])
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['utf8_title'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertutf8" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['utf8_introduction'], '</p>
					', !empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext' ? '<p class="error">' . $txt['utf8_cannot_convert_fulltext'] . '</p>' : '', '
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" ', !empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext' ? 'disabled="disabled"' : '', '/></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>';
	}

	// We might want to convert entities if we're on UTF-8.
	if ($context['convert_entities'])
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['entity_convert_title'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['entity_convert_introduction'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>';
	}

	echo '
	</div>
	<br class="clear" />';
}

// Template for the routine maintenance tasks.
function template_maintain_routine()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	// Starts off with general maintenance procedures.
	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_version'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=version" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_version_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_errors'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=repairboards" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_errors_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_recount'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_recount_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_logs'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=logs" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_logs_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_cache'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['maintain_cache_info'], '</p>
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

// Template for the member maintenance tasks.
function template_maintain_members()
{
	global $context, $settings, $options, $txt, $scripturl;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var warningMessage = \'\';
		var membersSwap = false;

		function swapMembers()
		{
			membersSwap = !membersSwap;
			var membersForm = document.getElementById(\'membersForm\');

			document.getElementById("membersIcon").src = smf_images_url + (membersSwap ? "/collapse.gif" : "/expand.gif");
			setInnerHTML(document.getElementById("membersText"), membersSwap ? "', $txt['maintain_members_choose'], '" : "', $txt['maintain_members_all'], '");
			document.getElementById("membersPanel").style.display = (membersSwap ? "block" : "none");

			for (var i = 0; i < membersForm.length; i++)
			{
				if (membersForm.elements[i].type.toLowerCase() == "checkbox")
					membersForm.elements[i].checked = !membersSwap;
			}
		}

		function checkAttributeValidity()
		{
			origText = \'', $txt['reattribute_confirm'], '\';
			valid = true;

			// Do all the fields!
			if (!document.getElementById(\'to\').value)
				valid = false;
			warningMessage = origText.replace(/%member_to%/, document.getElementById(\'to\').value);

			if (document.getElementById(\'type_email\').checked)
			{
				if (!document.getElementById(\'from_email\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes($txt['reattribute_confirm_email'], "'"), '\').replace(/%find%/, document.getElementById(\'from_email\').value);
			}
			else
			{
				if (!document.getElementById(\'from_name\').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, \'', addcslashes($txt['reattribute_confirm_username'], "'"), '\').replace(/%find%/, document.getElementById(\'from_name\').value);
			}

			document.getElementById(\'do_attribute\').disabled = valid ? \'\' : \'disabled\';

			setTimeout("checkAttributeValidity();", 500);
			return valid;
		}
		setTimeout("checkAttributeValidity();", 500);
	// ]]></script>
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_reattribute_posts'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="', $context['character_set'], '">
					<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
					<dl class="settings">
						<dt>
							<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked="checked" class="input_radio" />', $txt['reattribute_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById(\'type_email\').checked = \'checked\'; document.getElementById(\'from_name\').value = \'\';" />
						</dd>
						<dt>
							<label for="type_name"><input type="radio" name="type" id="type_name" value="name" class="input_radio" />', $txt['reattribute_username'], '</label>
						</dt>
						<dd>
							<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById(\'type_name\').checked = \'checked\'; document.getElementById(\'from_email\').value = \'\';" class="input_text" />
						</dd>
					</dl>
					<dl class="settings">
						<dt>
							<label for="to"><strong>', $txt['reattribute_current_member'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="to" id="to" value="" class="input_text" />
						</dd>
					</dl>
					<p class="maintain_members">
						<input type="checkbox" name="posts" id="posts" checked="checked" class="input_check" />
						<label for="posts">', $txt['reattribute_increase_posts'], '</label>
					</p>
					<span><input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="if (!checkAttributeValidity()) return false; return confirm(warningMessage);" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft">
					<a href="', $scripturl, '?action=helpadmin;help=maintenance_members" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a> ', $txt['maintain_members'], '
				</span>
			</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="', $context['character_set'], '" id="membersForm">
					<p><a id="membersLink"></a>', $txt['maintain_members_since1'], '
					<select name="del_type">
						<option value="activated" selected="selected">', $txt['maintain_members_activated'], '</option>
						<option value="logged">', $txt['maintain_members_logged_in'], '</option>
					</select> ', $txt['maintain_members_since2'], ' <input type="text" name="maxdays" value="30" size="3" class="input_text" />', $txt['maintain_members_since3'], '</p>';

	echo '
					<p><a href="#membersLink" onclick="swapMembers();"><img src="', $settings['images_url'], '/expand.gif" alt="+" id="membersIcon" /></a> <a href="#membersLink" onclick="swapMembers();" id="membersText" style="font-weight: bold;">', $txt['maintain_members_all'], '</a></p>
					<div style="display: none; padding: 3px" id="membersPanel">';

	foreach ($context['membergroups'] as $group)
		echo '
						<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked="checked" class="input_check" /> ', $group['name'], '</label><br />';

	echo '
					</div>
					<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_members_confirm'], '\');" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />

	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		var oAttributeMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAttributeMemberSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'attributeMember\',
			sControlId: \'to\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

// Template for the topic maintenance tasks.
function template_maintain_topics()
{
	global $scripturl, $txt, $context, $settings, $modSettings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var rotSwap = false;
			function swapRot()
			{
				rotSwap = !rotSwap;

				// Toggle icon
				document.getElementById("rotIcon").src = smf_images_url + (rotSwap ? "/collapse.gif" : "/expand.gif");
				setInnerHTML(document.getElementById("rotText"), rotSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');

				// Toggle panel
				document.getElementById("rotPanel").style.display = !rotSwap ? "none" : "";

				// Toggle checkboxes
				var rotPanel = document.getElementById(\'rotPanel\');
				var oBoardCheckBoxes = rotPanel.getElementsByTagName(\'input\');
				for (var i = 0; i < oBoardCheckBoxes.length; i++)
				{
					if (oBoardCheckBoxes[i].type.toLowerCase() == "checkbox")
						oBoardCheckBoxes[i].checked = !rotSwap;
				}
			}
		// ]]></script>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_old'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content flow_auto">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="', $context['character_set'], '">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', $txt['maintain_old_since_days1'], '<input type="text" name="maxdays" value="30" size="3" />', $txt['maintain_old_since_days2'], '
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing" class="input_radio" /> ', $txt['maintain_old_nothing_else'], '</label><br />
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" class="input_radio" checked="checked" /> ', $txt['maintain_old_are_moved'], '</label><br />
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked" class="input_radio" /> ', $txt['maintain_old_are_locked'], '</label><br />
					</p>';

	if (!empty($modSettings['enableStickyTopics']))
		echo '
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" class="input_check" checked="checked" /> ', $txt['maintain_old_are_not_stickied'], '</label><br />
					</p>';

		echo '
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', $settings['images_url'], '/expand.gif" alt="+" id="rotIcon" /></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;">', $txt['maintain_old_all'], '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count($context['categories']) / 2);

	$i = 0;
	foreach ($context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend>', $category['name'], '</legend>
								<ul class="reset">';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;"><label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked="checked" class="input_check" />', $board['name'], '</label></li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright" style="width: 49%;">';
	}

	echo '
						</div>
					</div>
					<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_old_confirm'], '\');" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['move_topics_maintenance'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="', $context['character_set'], '">
					<p><label for="id_board_from">', $txt['move_topics_from'], ' </label>
					<select name="id_board_from" id="id_board_from">
						<option disabled="disabled">(', $txt['move_topics_select_board'], ')</option>';

	// From board
	foreach ($context['categories'] as $category)
	{
		echo '
						<option disabled="disabled">--------------------------------------</option>
						<option disabled="disabled">', $category['name'], '</option>
						<option disabled="disabled">--------------------------------------</option>';

		foreach ($category['boards'] as $board)
			echo '
						<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';
	}

	echo '
					</select>
					<label for="id_board_to">', $txt['move_topics_to'], '</label>
					<select name="id_board_to" id="id_board_to">
						<option disabled="disabled">(', $txt['move_topics_select_board'], ')</option>';

	// To board
	foreach ($context['categories'] as $category)
	{
		echo '
						<option disabled="disabled">--------------------------------------</option>
						<option disabled="disabled">', $category['name'], '</option>
						<option disabled="disabled">--------------------------------------</option>';

		foreach ($category['boards'] as $board)
			echo '
						<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';
	}
	echo '
					</select></p>
					<span><input type="submit" value="', $txt['move_topics_now'], '" onclick="if (document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].disabled || document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_to\').selectedIndex].disabled) return false; var confirmText = \'', $txt['move_topics_confirm'] . '\'; return confirm(confirmText.replace(/%board_from%/, document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')).replace(/%board_to%/, document.getElementById(\'id_board_to\').options[document.getElementById(\'id_board_to\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')));" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

// Simple template for showing results of our optimization...
function template_optimize()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<p>
					', $txt['database_numb_tables'], '<br />
					', $txt['database_optimize_attempt'], '<br />';

	// List each table being optimized...
	foreach ($context['optimized_tables'] as $table)
		echo '
					', sprintf($txt['database_optimizing'], $table['name'], $table['data_freed']), '<br />';

	// How did we go?
	echo '
					<br />', $context['num_tables_optimized'] == 0 ? $txt['database_already_optimized'] : $context['num_tables_optimized'] . ' ' . $txt['database_optimized'];

	echo '
				</p>
				<p><a href="', $scripturl, '?action=admin;area=maintain">', $txt['maintain_return'], '</a></p>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

function template_convert_utf8()
{
	global $context, $txt, $settings, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['utf8_title'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertutf8" method="post" accept-charset="', $context['character_set'], '">
					<p>', $txt['utf8_introduction'], '</p>
					<div>', $txt['utf8_warning'], '</div>

					<dl class="settings">
						<dt><strong>', $txt['utf8_source_charset'], ':</strong></dt>
						<dd><select name="src_charset">';
	foreach ($context['charset_list'] as $charset)
		echo '
							<option value="', $charset, '"', $charset === $context['charset_detected'] ? ' selected="selected"' : '', '>', $charset, '</option>';
	echo '
							</select></dd>
						<dt><strong>', $txt['utf8_database_charset'], ':</strong></dt>
						<dd>', $context['database_charset'], '</dd>
						<dt><strong>', $txt['utf8_target_charset'], ': </strong></dt>
						<dd>', $txt['utf8_utf8'], '</dd>
					</dl>
					<input type="submit" value="', $txt['utf8_proceed'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="proceed" value="1" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

function template_convert_entities()
{
	global $context, $txt, $settings, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['entity_convert_title'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<p>', $txt['entity_convert_introduction'], '</p>
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities;start=0;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
					<input type="submit" value="', $txt['entity_convert_proceed'], '" class="button_submit" />
				</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

?>