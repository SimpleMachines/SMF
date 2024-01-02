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
use SMF\Utils;

/**
 * Shows a list of smiley sets so you can edit them.
 */
function template_editsets()
{
	template_show_list('smiley_set_list');
}

/**
 * Modifying a smiley set.
 */
function template_modifyset()
{
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=smileys;sa=editsets" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
			', Utils::$context['current_set']['is_new'] ? Lang::$txt['smiley_set_new'] : Lang::$txt['smiley_set_modify_existing'], '
			</h3>
		</div>';

	if (Utils::$context['current_set']['is_new'] && !empty(Config::$modSettings['smiley_enable']))
	{
		echo '
		<div class="information noup">
			', Lang::$txt['smiley_set_import_info'], '
		</div>';
	}
	// If this is an existing set, and there are still un-added smileys - offer an import opportunity.
	elseif (!empty(Utils::$context['current_set']['can_import']))
	{
		echo '
		<div class="information noup">
			', Utils::$context['smiley_set_unused_message'], '
		</div>';
	}

	echo '
		<div class="windowbg noup">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_sets_name">', Lang::$txt['smiley_sets_name'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_sets_name" id="smiley_sets_name" value="', Utils::$context['current_set']['name'], '">
				</dd>
				<dt>
					<strong><label for="smiley_sets_path">', Lang::$txt['smiley_sets_url'], '</label>: </strong>
				</dt>
				<dd>
					', Config::$modSettings['smileys_url'], '/';

	if (empty(Utils::$context['smiley_set_dirs']) || !empty(Utils::$context['make_new']))
	{
		echo '
					<input type="text" name="smiley_sets_path" id="smiley_sets_path" value="', Utils::$context['current_set']['path'], '"> ';
	}
	else
	{
		echo '
					<select name="smiley_sets_path" id="smiley_sets_path">';

		foreach (Utils::$context['smiley_set_dirs'] as $smiley_set_dir)
			echo '
						<option value="', $smiley_set_dir['id'], '"', $smiley_set_dir['current'] ? ' selected' : '', $smiley_set_dir['selectable'] ? '' : ' disabled', '>', $smiley_set_dir['id'], '</option>';
		echo '
					</select> ';
	}
	echo '
					/..
				</dd>
				<dt>
					<strong><label for="smiley_sets_default">', Lang::$txt['smiley_set_select_default'], '</label>: </strong>
				</dt>
				<dd>
					<input type="checkbox" name="smiley_sets_default" id="smiley_sets_default" value="1"', Utils::$context['current_set']['is_default'] ? ' checked' : '', '>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="', Lang::$txt['smiley_sets_save'], '" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="', Utils::$context['admin-mss_token_var'], '" value="', Utils::$context['admin-mss_token'], '">
		<input type="hidden" name="set" value="', Utils::$context['current_set']['path'], '">
	</form>';
}

/**
 * Editing an individual smiley
 */
function template_modifysmiley()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="', Utils::$context['character_set'], '" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['smiley_modify_existing'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', Lang::$txt['smiley_preview_using_set'], ': </strong>
					<select id="set" onchange="updatePreview($(\'#smiley_filename_\' + $(\'#set\').val()).val(), $(\'#set\').val());">';

	foreach (Utils::$context['smiley_sets'] as $smiley_set)
		echo '
					<option value="', $smiley_set['path'], '"', Utils::$context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

	echo '
					</select>
				</dt>
				<dd>
					<img src="', Config::$modSettings['smileys_url'], '/', Config::$modSettings['smiley_sets_default'], '/', Utils::$context['current_smiley']['filename'], '" id="preview" alt="">
				</dd>
				<dt>
					<strong><label for="smiley_filename">', Lang::$txt['smileys_filename'], '</label>: </strong>
				</dt>';

	if (empty(Utils::$context['filenames']))
	{
		echo '
				<dd>
					<input type="text" name="smiley_filename" id="smiley_filename" value="', Utils::$context['current_smiley']['filename'], '">
				</dd>';
	}
	else
	{
		foreach (Utils::$context['smiley_sets'] as $set => $smiley_set)
		{
			echo '
				<dt>
					', $smiley_set['name'], '
				</dt>
				<dd', in_array($set, Utils::$context['missing_sets']) ? ' class="errorbox"' : '', '>
					<select name="smiley_filename[', $set, ']" id="smiley_filename_', $set, '" onchange="$(\'#set\').val(\'', $set, '\');updatePreview($(\'#smiley_filename_\' + $(\'#set\').val()).val(), $(\'#set\').val());">';

			foreach (Utils::$context['filenames'][$set] as $filename)
				echo '
						<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', $filename['disabled'] ? ' disabled' : '', '>', $filename['id'], '</option>';

			echo '
					</select>
					<input type="file" name="smiley_upload[', $set, ']" id="smiley_upload_', $set, '">
				</dd>';
		}
	}

	echo '
			</dl>
			<dl class="settings">
				<dt>
					<strong><label for="smiley_code">', Lang::$txt['smileys_code'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="', Utils::$context['current_smiley']['code'], '">
				</dd>
				<dt>
					<strong><label for="smiley_description">', Lang::$txt['smileys_description'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_description" id="smiley_description" value="', Utils::$context['current_smiley']['description'], '">
				</dd>
				<dt>
					<strong><label for="smiley_location">', Lang::$txt['smileys_location'], '</label>: </strong>
				</dt>
				<dd>
					<select name="smiley_location" id="smiley_location">
						<option value="0"', Utils::$context['current_smiley']['location'] == 0 ? ' selected' : '', '>
							', Lang::$txt['smileys_location_form'], '
						</option>
						<option value="1"', Utils::$context['current_smiley']['location'] == 1 ? ' selected' : '', '>
							', Lang::$txt['smileys_location_hidden'], '
						</option>
						<option value="2"', Utils::$context['current_smiley']['location'] == 2 ? ' selected' : '', '>
							', Lang::$txt['smileys_location_popup'], '
						</option>
					</select>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="', Lang::$txt['smileys_save'], '" class="button">
			<input type="submit" name="deletesmiley" value="', Lang::$txt['smileys_delete'], '" data-confirm="', Lang::$txt['smileys_delete_confirm'], '" class="button you_sure">
		</div><!-- .windowbg -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="smiley" value="', Utils::$context['current_smiley']['id'], '">
	</form>';
}

/**
 * Adding a new smiley.
 */
function template_addsmiley()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="', Utils::$context['character_set'], '" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['smileys_add_method'], '</h3>
		</div>
		<div class="windowbg">
			<ul>
				<li>
					<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked> ', Lang::$txt['smileys_add_existing'], '</label>
				</li>
				<li>
					<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload"> ', Lang::$txt['smileys_add_upload'], '</label>
				</li>
			</ul>
			<br>
			<fieldset id="ex_settings">
				<dl class="settings">
					<dt>
						<strong><label for="preview">', Lang::$txt['smiley_preview'], '</label>: </strong>
					</dt>
					<dd>
						<img src="', Config::$modSettings['smileys_url'], '/', Config::$modSettings['smiley_sets_default'], '/', Utils::$context['filenames'][Utils::$context['selected_set']]['smiley']['id'], '" id="preview" alt="">
					</dd>
					<dt>
						<strong><label for="smiley_filename">', Lang::$txt['smileys_filename'], '</label>: </strong>
					</dt>
					<dd>';

	if (empty(Utils::$context['filenames']))
		echo '
						<input type="text" name="smiley_filename" id="smiley_filename" value="', Utils::$context['current_smiley']['filename'], '" onchange="selectMethod(\'existing\');">';
	else
	{
		echo '
						<select name="smiley_filename" id="smiley_filename" onchange="updatePreview($(\'#smiley_filename\').val());selectMethod(\'existing\');">';

		foreach (Utils::$context['smiley_sets'] as $smiley_set)
		{
			echo '
							<optgroup label="', $smiley_set['name'], '">';

			if (!empty(Utils::$context['filenames'][$smiley_set['path']]))
			{
				foreach (Utils::$context['filenames'][$smiley_set['path']] as $filename)
					echo '
								<option value="', $smiley_set['path'], '/', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';
			}

			echo '
							</optgroup>';
		}

		echo '
						</select>';
	}

	echo '
					</dd>
				</dl>
			</fieldset>
			<fieldset id="ul_settings" style="display: none;">
				<dl class="settings">
					<dt>
						<a href="', Config::$scripturl, '?action=helpadmin;help=smiley_sameall" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
						<strong><label for="sameall">', Lang::$txt['smileys_add_upload_all'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="sameall" id="sameall" onclick="swapUploads(); selectMethod(\'upload\');" checked>
					</dd>
					<dt>
						<strong>', Lang::$txt['smileys_add_upload_choose'], ':</strong>
					</dt>
					<dt class="upload_sameall">
						', Lang::$txt['smileys_add_upload_choose_desc'], '
					</dt>
					<dd class="upload_sameall">
						<input type="file" name="uploadSmiley" id="uploadSmiley" onchange="selectMethod(\'upload\');">
					</dd>';

	foreach (Utils::$context['smiley_sets'] as $smiley_set)
		echo '
					<dt class="upload_more" style="display: none;">
						', sprintf(Lang::$txt['smileys_add_upload_for'], '<strong>' . $smiley_set['name'] . '</strong>'), ':
					</dt>
					<dd class="upload_more" style="display: none;">
						<input type="file" name="individual_', $smiley_set['path'], '" disabled onchange="selectMethod(\'upload\');">
					</dd>';

	echo '
				</dl>
			</fieldset>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['smiley_new'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_code">', Lang::$txt['smileys_code'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="">
				</dd>
				<dt>
					<strong><label for="smiley_description">', Lang::$txt['smileys_description'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_description" id="smiley_description" value="">
				</dd>
				<dt>
					<strong><label for="smiley_location">', Lang::$txt['smileys_location'], '</label>: </strong>
				</dt>
				<dd>
					<select name="smiley_location" id="smiley_location">
						<option value="0" selected>
							', Lang::$txt['smileys_location_form'], '
						</option>
						<option value="1">
							', Lang::$txt['smileys_location_hidden'], '
						</option>
						<option value="2">
							', Lang::$txt['smileys_location_popup'], '
						</option>
					</select>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="', Lang::$txt['smileys_save'], '" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>';
}

/**
 * Ordering smileys.
 */
function template_setorder()
{
	foreach (Utils::$context['smileys'] as $location)
	{
		echo '
	<form action="', Config::$scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $location['title'], '</h3>
		</div>
		<div class="information noup">
			', $location['description'], '
		</div>
		<div class="move_smileys windowbg noup">
			<strong>', empty(Utils::$context['move_smiley']) ? Lang::$txt['smileys_move_select_smiley'] : Lang::$txt['smileys_move_select_destination'], '...</strong><br>';

		foreach ($location['rows'] as $row)
		{
			if (!empty(Utils::$context['move_smiley']))
				echo '
			<a href="', Config::$scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', Utils::$context['move_smiley'], ';row=', $row[0]['row'], ';reorder=1;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons select_below" title="', Lang::$txt['smileys_move_here'], '"></span></a>';

			foreach ($row as $smiley)
			{
				if (empty(Utils::$context['move_smiley']))
					echo '
			<a href="', Config::$scripturl, '?action=admin;area=smileys;sa=setorder;move=', $smiley['id'], '"><img src="', Config::$modSettings['smileys_url'], '/', Config::$modSettings['smiley_sets_default'], '/', $smiley['filename'], '" alt="', $smiley['description'], '"></a>';
				else
					echo '
			<img src="', Config::$modSettings['smileys_url'], '/', Config::$modSettings['smiley_sets_default'], '/', $smiley['filename'], '" alt="', $smiley['description'], '" ', $smiley['selected'] ? 'class="selected_item"' : '', '>
			<a href="', Config::$scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', Utils::$context['move_smiley'], ';after=', $smiley['id'], ';reorder=1;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" title="', Lang::$txt['smileys_move_here'], '"><span class="main_icons select_below" title="', Lang::$txt['smileys_move_here'], '"></span></a>';
			}

			echo '
			<br>';
		}
		if (!empty(Utils::$context['move_smiley']))
			echo '
			<a href="', Config::$scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', Utils::$context['move_smiley'], ';row=', $location['last_row'], ';reorder=1;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons select_below" title="', Lang::$txt['smileys_move_here'], '"></span></a>';
		echo '
		</div><!-- .windowbg -->
		<input type="hidden" name="reorder" value="1">
	</form>';
	}
}

/**
 * Editing Message Icons
 */
function template_editicons()
{
	template_show_list('message_icon_list');
}

/**
 * Editing an individual message icon
 */
function template_editicon()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=smileys;sa=editicon;icon=', Utils::$context['new_icon'] ? '0' : Utils::$context['icon']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', Utils::$context['new_icon'] ? Lang::$txt['icons_new_icon'] : Lang::$txt['icons_edit_icon'], '
			</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">';

	if (!Utils::$context['new_icon'])
		echo '
				<dt>
					<strong>', Lang::$txt['smiley_preview'], ': </strong>
				</dt>
				<dd>
					<img src="', Utils::$context['icon']['image_url'], '" alt="', Utils::$context['icon']['title'], '">
				</dd>';

	echo '
				<dt>
					<strong><label for="icon_filename">', Lang::$txt['smileys_filename'], '</label>: </strong><br><span class="smalltext">', sprintf(Lang::$txt['icons_extension_must_be'], '.png'), '</span>
				</dt>
				<dd>
					<input type="text" name="icon_filename" id="icon_filename" value="', !empty(Utils::$context['icon']['filename']) ? Utils::$context['icon']['filename'] . '.png' : '', '">
				</dd>
				<dt>
					<strong><label for="icon_description">', Lang::$txt['smileys_description'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="icon_description" id="icon_description" value="', !empty(Utils::$context['icon']['title']) ? Utils::$context['icon']['title'] : '', '">
				</dd>
				<dt>
					<strong><label for="icon_board_select">', Lang::$txt['icons_board'], '</label>: </strong>
				</dt>
				<dd>
					<select name="icon_board" id="icon_board_select">
						<option value="0"', empty(Utils::$context['icon']['board_id']) ? ' selected' : '', '>', Lang::$txt['icons_edit_icons_all_boards'], '</option>';

	foreach (Utils::$context['categories'] as $category)
	{
		echo '
						<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
							<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';

		echo '
						</optgroup>';
	}

	echo '
					</select>
				</dd>
				<dt>
					<strong><label for="icon_location">', Lang::$txt['smileys_location'], '</label>: </strong>
				</dt>
				<dd>
					<select name="icon_location" id="icon_location">
						<option value="0"', empty(Utils::$context['icon']['after']) ? ' selected' : '', '>', Lang::$txt['icons_location_first_icon'], '</option>';

	// Print the list of all the icons it can be put after...
	foreach (Utils::$context['icons'] as $id => $data)
		if (empty(Utils::$context['icon']['id']) || $id != Utils::$context['icon']['id'])
			echo '
						<option value="', $id, '"', !empty(Utils::$context['icon']['after']) && $id == Utils::$context['icon']['after'] ? ' selected' : '', '>', Lang::$txt['icons_location_after'], ': ', $data['title'], '</option>';

	echo '
					</select>
				</dd>
			</dl>';

	if (!Utils::$context['new_icon'])
		echo '
			<input type="hidden" name="icon" value="', Utils::$context['icon']['id'], '">';

	echo '
			<input type="submit" name="icons_save" value="', Lang::$txt['smileys_save'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</div><!-- .windowbg -->
	</form>';
}

?>