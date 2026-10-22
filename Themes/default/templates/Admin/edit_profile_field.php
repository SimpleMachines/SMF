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
 * Template for editing a custom profile field
 */
?>

<?php /* All the javascript for this page - quite a bit in script.js! */ ?>
					<script>
						var startOptID = <?= count(Utils::$context['field']['options']) ?>;
					</script>
<?php /* any errors messages to show? */ ?>
<?php if (isset($_GET['msg'])): ?>
<?php if (Lang::txtExists('custom_option_' . $_GET['msg'], file: 'Errors')): ?>
					<div class="errorbox"><?= Lang::getTxt('custom_option_' . $_GET['msg'], file: 'Errors') ?>
					</div>
<?php endif; ?>
<?php endif; ?>
						<form action="<?= Config::$scripturl ?>?action=admin;area=featuresettings;sa=profileedit;fid=<?= Utils::$context['fid'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
							<div id="section_header" class="cat_bar">
								<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
							</div>
							<div class="windowbg">
								<fieldset>
									<legend><?= Lang::getTxt('custom_edit_general', file: 'ManageSettings') ?></legend>

									<dl class="settings">
										<dt>
											<a id="field_name_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=translatable_fields" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
											<strong><label for="field_name"><?= Lang::getTxt('custom_edit_name', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_name_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<input type="text" name="field_name" id="field_name" value="<?= Utils::$context['field']['name'] ?>" size="20" maxlength="40">
										</dd>
										<dt>
											<a id="field_desc_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=translatable_fields" onclick="return reqOverlayDiv(this.href);" class="help">
												<span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span>
											</a>
											<strong><label for="field_desc"><?= Lang::getTxt('custom_edit_desc', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_name_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<textarea name="field_desc" id="field_desc" rows="3" cols="40"><?= Utils::$context['field']['desc'] ?></textarea>
										</dd>
										<dt>
											<strong><label for="profile_area"><?= Lang::getTxt('custom_edit_profile', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_profile_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<select name="profile_area" id="profile_area">
												<option value="none"<?= Utils::$context['field']['profile_area'] == 'none' ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_profile_none', file: 'ManageSettings') ?></option>
												<option value="account"<?= Utils::$context['field']['profile_area'] == 'account' ? ' selected' : '' ?>><?= Lang::getTxt('account', file: 'General') ?></option>
												<option value="forumprofile"<?= Utils::$context['field']['profile_area'] == 'forumprofile' ? ' selected' : '' ?>><?= Lang::getTxt('forumprofile', file: 'General') ?></option>
												<option value="theme"<?= Utils::$context['field']['profile_area'] == 'theme' ? ' selected' : '' ?>><?= Lang::getTxt('theme', file: 'General') ?></option>
											</select>
										</dd>
										<dt>
											<a id="field_reg_require" href="<?= Config::$scripturl ?>?action=helpadmin;help=field_reg_require" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
											<strong><label for="reg"><?= Lang::getTxt('custom_edit_registration', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<select name="reg" id="reg">
												<option value="0"<?= Utils::$context['field']['reg'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_registration_disable', file: 'ManageSettings') ?></option>
												<option value="1"<?= Utils::$context['field']['reg'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_registration_allow', file: 'ManageSettings') ?></option>
												<option value="2"<?= Utils::$context['field']['reg'] == 2 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_registration_require', file: 'ManageSettings') ?></option>
											</select>
										</dd>
										<dt>
											<strong><label for="display"><?= Lang::getTxt('custom_edit_display', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="display" id="display"<?= Utils::$context['field']['display'] ? ' checked' : '' ?>>
										</dd>
										<dt>
											<strong><label for="mlist"><?= Lang::getTxt('custom_edit_mlist', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="mlist" id="show_mlist"<?= Utils::$context['field']['mlist'] ? ' checked' : '' ?>>
										</dd>
										<dt>
											<strong><label for="placement"><?= Lang::getTxt('custom_edit_placement', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<select name="placement" id="placement">
<?php foreach (Utils::$context['cust_profile_fields_placement'] as $order => $name): ?>
												<option value="<?= $order ?>"<?= Utils::$context['field']['placement'] == $order ? ' selected' : '' ?>><?= Lang::getTxt('custom_profile_placement_' . $name, file: 'ManageSettings') ?></option>
<?php endforeach; ?>
											</select>
										</dd>
										<dt>
											<a id="field_show_enclosed" href="<?= Config::$scripturl ?>?action=helpadmin;help=field_show_enclosed" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
											<strong><label for="enclose"><?= Lang::getTxt('custom_edit_enclose', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_enclose_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<textarea name="enclose" id="enclose" rows="10" cols="50"><?= @Utils::$context['field']['enclose'] ?></textarea>
										</dd>
									</dl>
								</fieldset>
								<fieldset>
									<legend><?= Lang::getTxt('custom_edit_input', file: 'ManageSettings') ?></legend>
									<dl class="settings">
										<dt>
											<strong><label for="field_type"><?= Lang::getTxt('custom_edit_picktype', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<select name="field_type" id="field_type" onchange="updateInputBoxes();">
<?php foreach (['text', 'textarea', 'select', 'radio', 'check'] as $field_type): ?>
												<option value="<?= $field_type ?>"<?= Utils::$context['field']['type'] == $field_type ? ' selected' : '' ?>><?= Lang::getTxt('custom_profile_type_' . $field_type, file: 'ManageSettings') ?></option>
<?php endforeach; ?>
											</select>
										</dd>
										<dt id="max_length_dt">
											<strong><label for="max_length_dd"><?= Lang::getTxt('custom_edit_max_length', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_max_length_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<input type="text" name="max_length" id="max_length_dd" value="<?= Utils::$context['field']['max_length'] ?>" size="7" maxlength="6">
										</dd>
										<dt id="dimension_dt">
											<strong><label for="dimension_dd"><?= Lang::getTxt('custom_edit_dimension', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd id="dimension_dd">
											<strong><?= Lang::getTxt('custom_edit_dimension_row', file: 'ManageSettings') ?></strong> <input type="text" name="rows" value="<?= Utils::$context['field']['rows'] ?>" size="5" maxlength="3">
											<strong><?= Lang::getTxt('custom_edit_dimension_col', file: 'ManageSettings') ?></strong> <input type="text" name="cols" value="<?= Utils::$context['field']['cols'] ?>" size="5" maxlength="3">
										</dd>
										<dt id="bbc_dt">
											<strong><label for="bbc_dd"><?= Lang::getTxt('custom_edit_bbc', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="bbc" id="bbc_dd"<?= Utils::$context['field']['bbc'] ? ' checked' : '' ?>>
										</dd>
										<dt id="options_dt">
											<a href="<?= Config::$scripturl ?>?action=helpadmin;help=customoptions" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
											<strong><label for="options_dd"><?= Lang::getTxt('custom_edit_options', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_options_desc', file: 'ManageSettings') ?></span>
											<br>
											<span><?= Lang::getTxt('custom_edit_name_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd id="options_dd">
<?php foreach (Utils::$context['field']['options'] as $k => $option): ?>
											<?= $k == 0 ? '' : '<br>' ?><input type="radio" name="default_select" value="<?= $k ?>"<?= Utils::$context['field']['default_select'] == $option ? ' checked' : '' ?>><input type="text" name="select_option[<?= $k ?>]" value="<?= $option ?>">
<?php endforeach; ?>
											<span id="addopt"></span>
											[<a href="" onclick="addOption(); return false;"><?= Lang::getTxt('custom_edit_options_more', file: 'ManageSettings') ?></a>]
										</dd>
										<dt id="default_dt">
											<strong><label for="default_dd"><?= Lang::getTxt('custom_edit_default', file: 'ManageSettings') ?></label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="default_check" id="default_dd"<?= Utils::$context['field']['default_check'] ? ' checked' : '' ?>>
										</dd>
									</dl>
								</fieldset>
								<fieldset>
									<legend><?= Lang::getTxt('custom_edit_advanced', file: 'ManageSettings') ?></legend>
									<dl class="settings">
										<dt id="mask_dt">
											<a id="custom_mask" href="<?= Config::$scripturl ?>?action=helpadmin;help=custom_mask" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
											<strong><label for="mask"><?= Lang::getTxt('custom_edit_mask', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_mask_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<select name="mask" id="mask" onchange="updateInputBoxes();">
												<option value="nohtml"<?= Utils::$context['field']['mask'] == 'nohtml' ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_mask_nohtml', file: 'ManageSettings') ?></option>
												<option value="email"<?= Utils::$context['field']['mask'] == 'email' ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_mask_email', file: 'ManageSettings') ?></option>
												<option value="number"<?= Utils::$context['field']['mask'] == 'number' ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_mask_number', file: 'ManageSettings') ?></option>
												<option value="regex"<?= str_starts_with(Utils::$context['field']['mask'], 'regex') ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_mask_regex', file: 'ManageSettings') ?></option>
											</select>
											<br>
											<span id="regex_div">
												<input type="text" name="regex" value="<?= Utils::$context['field']['regex'] ?>" size="30">
											</span>
										</dd>
										<dt>
											<strong><label for="private"><?= Lang::getTxt('custom_edit_privacy', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_privacy_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<select name="private" id="private" onchange="updateInputBoxes();">
												<option value="0"<?= Utils::$context['field']['private'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_privacy_all', file: 'ManageSettings') ?></option>
												<option value="1"<?= Utils::$context['field']['private'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_privacy_see', file: 'ManageSettings') ?></option>
												<option value="2"<?= Utils::$context['field']['private'] == 2 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_privacy_owner', file: 'ManageSettings') ?></option>
												<option value="3"<?= Utils::$context['field']['private'] == 3 ? ' selected' : '' ?>><?= Lang::getTxt('custom_edit_privacy_none', file: 'ManageSettings') ?></option>
											</select>
										</dd>
										<dt id="can_search_dt">
											<strong><label for="can_search_dd"><?= Lang::getTxt('custom_edit_can_search', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_can_search_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<input type="checkbox" name="can_search" id="can_search_dd"<?= Utils::$context['field']['can_search'] ? ' checked' : '' ?>>
										</dd>
										<dt>
											<strong><label for="can_search_check"><?= Lang::getTxt('custom_edit_active', file: 'ManageSettings') ?></label></strong><br>
											<span class="smalltext"><?= Lang::getTxt('custom_edit_active_desc', file: 'ManageSettings') ?></span>
										</dt>
										<dd>
											<input type="checkbox" name="active" id="can_search_check"<?= Utils::$context['field']['active'] ? ' checked' : '' ?>>
										</dd>
									</dl>
								</fieldset>
								<input type="submit" name="save" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
<?php if (Utils::$context['fid']): ?>
								<input type="submit" name="delete" value="<?= Lang::getTxt('delete', file: 'General') ?>" data-confirm="<?= Lang::getTxt('custom_edit_delete_sure', file: 'ManageSettings') ?>" class="button you_sure">
<?php endif; ?>
							</div><!-- .windowbg -->
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
							<input type="hidden" name="<?= Utils::$context['admin-ecp_token_var'] ?>" value="<?= Utils::$context['admin-ecp_token'] ?>">
						</form>
<?php /* Get the javascript bits right! */ ?>
					<script>
						updateInputBoxes();
					</script>