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
 * Before registering - get their information.
 */
?>

		<script>
			function verifyAgree()
			{
				if (currentAuthMethod == 'passwd' && document.forms.registration.passwrd1.value != document.forms.registration.passwrd2.value)
				{
					alert("<?= Lang::getTxt('register_passwords_differ_js', file: 'Login') ?>");
					return false;
				}

				return true;
			}

			var currentAuthMethod = 'passwd';
		</script>
<?php /* Any errors? */ ?>
<?php if (!empty(Utils::$context['registration_errors'])): ?>
		<div class="errorbox">
			<span><?= Lang::getTxt('registration_errors_occurred', file: 'Login') ?></span>
			<ul>
<?php /* Cycle through each error and display an error message. */ ?>
<?php foreach (Utils::$context['registration_errors'] as $error): ?>
				<li><?= $error ?></li>
<?php endforeach; ?>
			</ul>
		</div>
<?php endif; ?>
		<form action="<?= !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl ?>?action=signup2" method="post" accept-charset="UTF-8" name="registration" id="registration" onsubmit="return verifyAgree();">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('registration_form', file: 'Login') ?></h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg"><?= Lang::getTxt('required_info', file: 'Login') ?></h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form">
						<dt>
							<strong><label for="reg_username"><?= Lang::getTxt('username', file: 'General') ?></label></strong>
						</dt>
						<dd>
							<input type="text" name="user" id="reg_username" data-autov="username" size="50" maxlength="25" value="<?= Utils::$context['username'] ?? '' ?>">
						</dd>
						<dt><strong><label for="reg_email"><?= Lang::getTxt('user_email_address', file: 'General') ?></label></strong></dt>
						<dd>
							<input type="email" name="email" id="reg_email" data-autov="reserve1" size="50" value="<?= Utils::$context['email'] ?? '' ?>">
						</dd>
					</dl>
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="reg_pwmain"><?= Lang::getTxt('choose_pass', file: 'General') ?></label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="reg_pwmain" data-autov="pwmain" size="50">
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt>
							<strong><label for="reg_pwverify"><?= Lang::getTxt('verify_pass', file: 'General') ?></label></strong>
						</dt>
						<dd>
							<input type="password" name="passwrd2" id="reg_pwverify" data-autov="pwverify" size="50">
						</dd>
					</dl>
					<dl class="register_form" id="notify_announcements">
						<dt>
							<strong><label for="notify_announcements"><?= Lang::getTxt('notify_announcements', file: 'General') ?></label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="notify_announcements" id="notify_announcements"<?= Utils::$context['notify_announcements'] ? ' checked="checked"' : '' ?>>
						</dd>
					</dl>
<?php /* If there is any field marked as required, show it here! */ ?>
<?php if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields'])): ?>
					<dl class="register_form">
<?php foreach (Utils::$context['custom_fields'] as $field): ?>
<?php if ($field['show_reg'] > 1): ?>
						<dt>
							<strong<?= !empty($field['is_error']) ? ' class="red"' : '' ?>><?= $field['name'] ?>:</strong>
							<span class="smalltext"><?= $field['desc'] ?></span>
						</dt>
						<dd><?= $field['input_html'] ?></dd>
<?php endif; ?>
<?php endforeach; ?>
					</dl>
<?php endif; ?>
				</fieldset>
			</div><!-- .roundframe -->
<?php /* If we have either of these, show the extra group. */ ?>
<?php if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields'])): ?>
			<div class="title_bar">
				<h3 class="titlebg"><?= Lang::getTxt('additional_information', file: 'Login') ?></h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form" id="custom_group">
<?php endif; ?>
<?php if (!empty(Utils::$context['profile_fields'])): ?>
<?php /* Any fields we particularly want? */ ?>
<?php foreach (Utils::$context['profile_fields'] as $key => $field): ?>
<?php if ($field['type'] == 'callback'): ?>
<?php if (isset($field['callback_func']) && $this->hasSubTemplate('profile_' . $field['callback_func'])): ?>
<?php $this->subTemplate('profile_' . $field['callback_func']) ?>
<?php endif; ?>
<?php else: ?>
						<dt>
							<strong<?= !empty($field['is_error']) ? ' class="red"' : '' ?>><?= $field['label'] ?>:</strong>
<?php /* Does it have any subtext to show? */ ?>
<?php if (!empty($field['subtext'])): ?>
							<span class="smalltext"><?= $field['subtext'] ?></span>
<?php endif; ?>
						</dt>
						<dd>
<?php /* Want to put something infront of the box? */ ?>
<?php if (!empty($field['preinput'])): ?>
							<?= $field['preinput'] ?>

<?php endif; ?>
<?php /* What type of data are we showing? */ ?>
<?php if ($field['type'] == 'label'): ?>
							<?= $field['value'] ?>

<?php /* Maybe it's a text box - very likely! */ ?>
<?php elseif (in_array($field['type'], ['int', 'float', 'text', 'password', 'url'])): ?>
							<input type="<?= $field['type'] == 'password' ? 'password' : 'text' ?>" name="<?= $key ?>" id="<?= $key ?>" size="<?= empty($field['size']) ? 30 : $field['size'] ?>" value="<?= $field['value'] ?>" <?= $field['input_attr'] ?>>
<?php /* You "checking" me out? ;) */ ?>
<?php elseif ($field['type'] == 'check'): ?>
							<input type="hidden" name="<?= $key ?>" value="0"><input type="checkbox" name="<?= $key ?>" id="<?= $key ?>"<?= !empty($field['value']) ? ' checked' : '' ?> value="1" <?= $field['input_attr'] ?>>
<?php /* Always fun - select boxes! */ ?>
<?php elseif ($field['type'] == 'select'): ?>
							<select name="<?= $key ?>" id="<?= $key ?>">
<?php if (isset($field['options'])): ?>
<?php /* Is this some code to generate the options? */ ?>
<?php if (!is_array($field['options'])): ?>
<?php $field['options'] = eval($field['options']); ?>
<?php endif; ?>
<?php /* Assuming we now have some! */ ?>
<?php if (is_array($field['options'])): ?>
<?php foreach ($field['options'] as $value => $name): ?>
								<option<?= (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : '') ?> value="<?= $value ?>"<?= $value === $field['value'] ? ' selected' : '' ?>><?= $name ?></option>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
							</select>
<?php endif; ?>
<?php /* Something to end with? */ ?>
<?php if (!empty($field['postinput'])): ?>
							<?= $field['postinput'] ?>

<?php endif; ?>
						</dd>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php /* Are there any custom fields? */ ?>
<?php if (!empty(Utils::$context['custom_fields'])): ?>
<?php foreach (Utils::$context['custom_fields'] as $field): ?>
<?php if ($field['show_reg'] < 2): ?>
						<dt>
							<strong<?= !empty($field['is_error']) ? ' class="red"' : '' ?>><?= $field['name'] ?>:</strong>
							<span class="smalltext"><?= $field['desc'] ?></span>
						</dt>
						<dd><?= $field['input_html'] ?></dd>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php /* If we have either of these, close the list like a proper gent. */ ?>
<?php if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields'])): ?>
					</dl>
				</fieldset>
			</div><!-- .roundframe -->
<?php endif; ?>
<?php if (Utils::$context['visual_verification']): ?>
			<div class="title_bar">
				<h3 class="titlebg"><?= Lang::getTxt('verification', file: 'General') ?></h3>
			</div>
			<div class="roundframe noup">
				<fieldset class="centertext">
					<?php $this->subTemplate('control_verification', ['verify_id' => Utils::$context['visual_verification_id'], 'display_type' => 'all']); ?>

				</fieldset>
			</div>
<?php endif; ?>
			<div id="confirm_buttons" class="flow_auto">
<?php /* Age restriction in effect? */ ?>
<?php if (empty(Utils::$context['agree']) && Utils::$context['show_coppa']): ?>
				<input type="submit" name="accept_agreement" value="<?= Utils::$context['coppa_agree_above'] ?>" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="<?= Utils::$context['coppa_agree_below'] ?>" class="button">
<?php else: ?>
				<input type="submit" name="regSubmit" value="<?= Lang::getTxt('register', file: 'General') ?>" class="button" onclick="this.disabled = true;form.submit();">
<?php endif; ?>
			</div>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['register_token_var'] ?>" value="<?= Utils::$context['register_token'] ?>">
			<input type="hidden" name="step" value="2">
		</form>
		<script>
			var regTextStrings = {
				"username_valid": "<?= Lang::getTxt('registration_username_available', file: 'Login') ?>",
				"username_invalid": "<?= Lang::getTxt('registration_username_unavailable', file: 'Login') ?>",
				"username_check": "<?= Lang::getTxt('registration_username_check', file: 'Login') ?>",
				"password_short": "<?= Lang::getTxt('registration_password_short', file: 'Login') ?>",
				"password_reserved": "<?= Lang::getTxt('registration_password_reserved', file: 'Login') ?>",
				"password_numbercase": "<?= Lang::getTxt('registration_password_numbercase', file: 'Login') ?>",
				"password_no_match": "<?= Lang::getTxt('registration_password_no_match', file: 'Login') ?>",
				"password_valid": "<?= Lang::getTxt('registration_password_valid', file: 'Login') ?>"
			};
			var verificationHandle = new smfRegister("registration", <?= empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'] ?>, regTextStrings);
		</script>