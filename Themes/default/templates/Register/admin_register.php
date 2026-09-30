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
 * The template for the form allowing an admin to register a user from the admin center.
 */
?>

		<div id="admin_form_wrapper">
			<form id="postForm" action="<?= Config::$scripturl ?>?action=admin;area=regcenter" method="post" accept-charset="UTF-8" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg"><?= Lang::getTxt('admin_browse_register_new', file: 'Admin') ?></h3>
				</div>
				<div id="register_screen" class="windowbg">
<?php if (!empty(Utils::$context['registration_done'])): ?>
					<div class="infobox">
						<?= Utils::$context['registration_done'] ?>

					</div>
<?php endif; ?>
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input"><?= Lang::getTxt('admin_register_username', file: 'Login') ?></label></strong>
							<span class="smalltext"><?= Lang::getTxt('admin_register_username_desc', file: 'Login') ?></span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" size="50" maxlength="25">
						</dd>
						<dt>
							<strong><label for="email_input"><?= Lang::getTxt('admin_register_email', file: 'Login') ?></label></strong>
							<span class="smalltext"><?= Lang::getTxt('admin_register_email_desc', file: 'Login') ?></span>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" size="50">
						</dd>
						<dt>
							<strong><label for="password_input"><?= Lang::getTxt('admin_register_password', file: 'Login') ?></label></strong>
							<span class="smalltext"><?= Lang::getTxt('admin_register_password_desc', file: 'Login') ?></span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" size="50" onchange="onCheckChange();">
						</dd>
<?php if (!empty(Utils::$context['member_groups'])): ?>
						<dt>
							<strong><label for="group_select"><?= Lang::getTxt('admin_register_group', file: 'Login') ?></label></strong>
							<span class="smalltext"><?= Lang::getTxt('admin_register_group_desc', file: 'Login') ?></span>
						</dt>
						<dd>
							<select name="group" id="group_select">
<?php foreach (Utils::$context['member_groups'] as $id => $name): ?>
								<option value="<?= $id ?>"><?= $name ?></option>
<?php endforeach; ?>
							</select>
						</dd>
<?php endif; ?>
<?php /* If there is any field marked as required, show it here! */ ?>
<?php if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields'])): ?>
<?php foreach (Utils::$context['custom_fields'] as $field): ?>
<?php if ($field['show_reg'] > 1): ?>
						<dt>
							<strong<?= !empty($field['is_error']) ? ' class="red"' : '' ?>><?= $field['name'] ?>:</strong>
							<span class="smalltext"><?= $field['desc'] ?></span>
						</dt>
						<dd>
							<?= $field['input_html'] ?>

						</dd>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
						<dt>
							<strong><label for="emailPassword_check"><?= Lang::getTxt('admin_register_email_detail', file: 'Login') ?></label></strong>
							<span class="smalltext"><?= Lang::getTxt('admin_register_email_detail_desc', file: 'Login') ?></span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" checked disabled>
						</dd>
						<dt>
							<strong><label for="emailActivate_check"><?= Lang::getTxt('admin_register_email_activate', file: 'Login') ?></label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check"<?= !empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1 ? ' checked' : '' ?> onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="<?= Lang::getTxt('register', file: 'General') ?>" class="button">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="<?= Utils::$context['admin-regc_token_var'] ?>" value="<?= Utils::$context['admin-regc_token'] ?>">
					</div>
				</div><!-- #register_screen -->
			</form>
		</div><!-- #admin_form_wrapper -->
	<br class="clear">