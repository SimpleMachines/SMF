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

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	echo '
		<form action="', Config::$scripturl, '?action=signup" method="post" accept-charset="UTF-8" id="registration">';

	if (!empty(Utils::$context['agreement'])) {
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('registration_agreement', file: 'General'), '</h3>
			</div>
			<div class="roundframe">
				<div>', Utils::adjustHeadingLevels(Utils::$context['agreement'], 3), '</div>
			</div>';
	}

	if (!empty(Utils::$context['privacy_policy'])) {
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('privacy_policy', file: 'General'), '</h3>
			</div>
			<div class="roundframe">
				<div>', Utils::adjustHeadingLevels(Utils::$context['privacy_policy'], 3), '</div>
			</div>';
	}

		echo '
			<div id="confirm_buttons">';

	// Age restriction in effect?
	if (Utils::$context['show_coppa']) {
		echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', Utils::$context['coppa_agree_below'], '" class="button">';
	} else {
	echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['agree'], '" class="button" />';
	}

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['register_token_var'], '" value="', Utils::$context['register_token'], '">
				<input type="hidden" name="step" value="1">
			</div>
		</form>';
}

/**
 * Before registering - get their information.
 */
function template_registration_form()
{
	echo '
		<script>
			function verifyAgree()
			{
				if (currentAuthMethod == \'passwd\' && document.forms.registration.passwrd1.value != document.forms.registration.passwrd2.value)
				{
					alert("', Lang::getTxt('register_passwords_differ_js', file: 'Login'), '");
					return false;
				}

				return true;
			}

			var currentAuthMethod = \'', empty(Utils::$context['registration_passwordless']) ? 'passwd' : 'vouched', '\';
		</script>';

	// Any errors?
	if (!empty(Utils::$context['registration_errors'])) {
		echo '
		<div class="errorbox">
			<span>', Lang::getTxt('registration_errors_occurred', file: 'Login'), '</span>
			<ul>';

		// Cycle through each error and display an error message.
		foreach (Utils::$context['registration_errors'] as $error) {
			echo '
				<li>', $error, '</li>';
		}

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl, '?action=signup2" method="post" accept-charset="UTF-8" name="registration" id="registration" onsubmit="return verifyAgree();">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('registration_form', file: 'Login'), '</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">', Lang::getTxt('required_info', file: 'Login'), '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form">
						<dt>
							<strong><label for="reg_username">', Lang::getTxt('username', file: 'General'), '</label></strong>
						</dt>
						<dd>
							<input type="text" name="user" id="reg_username" data-autov="username" size="50" maxlength="25" value="', Utils::$context['username'] ?? '', '">
						</dd>
						<dt><strong><label for="reg_email">', Lang::getTxt('user_email_address', file: 'General'), '</label></strong></dt>
						<dd>
							<input type="email" name="email" id="reg_email" data-autov="reserve1" size="50" value="', Utils::$context['email'] ?? '', '">
						</dd>
					</dl>';

	/*
	 * Something has already vouched for them, so there is no password to
	 * choose. Say what will be signing them in instead, since a sign up form
	 * with no password box on it is otherwise a puzzle.
	 */
	if (!empty(Utils::$context['registration_passwordless'])) {
		echo '
					<dl class="register_form" id="passwordless_group">
						<dt><strong>', Lang::getTxt('registration_signing_in', file: 'Login'), '</strong></dt>
						<dd>', !empty(Utils::$context['registration_passkey_ready']) ? Lang::getTxt('registration_signing_in_passkey', file: 'Login') : Lang::getTxt('registration_signing_in_provider', ['provider' => Utils::$context['registration_vouched_by']], file: 'Login'), '</dd>
					</dl>';
	} else {
		echo '
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="reg_pwmain">', Lang::getTxt('choose_pass', file: 'General'), '</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="reg_pwmain" data-autov="pwmain" size="50">
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt>
							<strong><label for="reg_pwverify">', Lang::getTxt('verify_pass', file: 'General'), '</label></strong>
						</dt>
						<dd>
							<input type="password" name="passwrd2" id="reg_pwverify" data-autov="pwverify" size="50">
						</dd>
					</dl>';
	}

	/*
	 * The passkey offer is hidden until the script has decided this browser can
	 * do it. Nothing is lost if it cannot: the password boxes above are still
	 * there, and they are what the form falls back to.
	 */
	if (!empty(Utils::$context['offer_passkey_signup'])) {
		echo '
					<dl class="register_form" id="passkey_signup_group" style="display: none;">
						<dt>
							<strong>', Lang::getTxt('passkey_signup', file: 'Login'), '</strong>
							<span class="smalltext">', Lang::getTxt('passkey_signup_desc', file: 'Login'), '</span>
						</dt>
						<dd id="passkey_signup">
							<button type="button" class="button" id="passkey_signup_button">', Lang::getTxt('passkey_signup_button', file: 'Login'), '</button>
						</dd>
					</dl>';
	}

	echo '
					<dl class="register_form" id="notify_announcements">
						<dt>
							<strong><label for="notify_announcements">', Lang::getTxt('notify_announcements', file: 'General'), '</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="notify_announcements" id="notify_announcements"', Utils::$context['notify_announcements'] ? ' checked="checked"' : '', '>
						</dd>
					</dl>';

	// If there is any field marked as required, show it here!
	if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields'])) {
		echo '
					<dl class="register_form">';

		foreach (Utils::$context['custom_fields'] as $field) {
			if ($field['show_reg'] > 1) {
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', $field['input_html'], '</dd>';
			}
		}

		echo '
					</dl>';
	}

	echo '
				</fieldset>
			</div><!-- .roundframe -->';

	// If we have either of these, show the extra group.
	if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields'])) {
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', Lang::getTxt('additional_information', file: 'Login'), '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form" id="custom_group">';
	}

	if (!empty(Utils::$context['profile_fields'])) {
		// Any fields we particularly want?
		foreach (Utils::$context['profile_fields'] as $key => $field) {
			if ($field['type'] == 'callback') {
				if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func'])) {
					$callback_func = 'template_profile_' . $field['callback_func'];
					$callback_func();
				}
			} else {
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['label'], ':</strong>';

				// Does it have any subtext to show?
				if (!empty($field['subtext'])) {
					echo '
							<span class="smalltext">', $field['subtext'], '</span>';
				}

				echo '
						</dt>
						<dd>';

				// Want to put something infront of the box?
				if (!empty($field['preinput'])) {
					echo '
							', $field['preinput'];
				}

				// What type of data are we showing?
				if ($field['type'] == 'label') {
					echo '
							', $field['value'];
				}

				// Maybe it's a text box - very likely!
				elseif (in_array($field['type'], ['int', 'float', 'text', 'password', 'url'])) {
					echo '
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], '>';
				}

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check') {
					echo '
							<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" ', $field['input_attr'], '>';
				}

				// Always fun - select boxes!
				elseif ($field['type'] == 'select') {
					echo '
							<select name="', $key, '" id="', $key, '">';

					if (isset($field['options'])) {
						// Is this some code to generate the options?
						if (!is_array($field['options'])) {
							$field['options'] = eval($field['options']);
						}

						// Assuming we now have some!
						if (is_array($field['options'])) {
							foreach ($field['options'] as $value => $name) {
								echo '
								<option', (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ''), ' value="' . $value . '"', $value === $field['value'] ? ' selected' : '', '>', $name, '</option>';
							}
						}
					}

					echo '
							</select>';
				}

				// Something to end with?
				if (!empty($field['postinput'])) {
					echo '
							', $field['postinput'];
				}

				echo '
						</dd>';
			}
		}
	}

	// Are there any custom fields?
	if (!empty(Utils::$context['custom_fields'])) {
		foreach (Utils::$context['custom_fields'] as $field) {
			if ($field['show_reg'] < 2) {
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', $field['input_html'], '</dd>';
			}
		}
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields'])) {
		echo '
					</dl>
				</fieldset>
			</div><!-- .roundframe -->';
	}

	if (Utils::$context['visual_verification']) {
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', Lang::getTxt('verification', file: 'General'), '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset class="centertext">
					', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';
	}

	echo '
			<div id="confirm_buttons" class="flow_auto">';

	// Age restriction in effect?
	if (empty(Utils::$context['agree']) && Utils::$context['show_coppa']) {
		echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', Utils::$context['coppa_agree_below'], '" class="button">';
	} else {
	echo '
				<input type="submit" name="regSubmit" value="', Lang::getTxt('register', file: 'General'), '" class="button" onclick="this.disabled = true;form.submit();">';
	}

	echo '
			</div>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['register_token_var'], '" value="', Utils::$context['register_token'], '">
			<input type="hidden" name="step" value="2">
		</form>
		<script>
			var regTextStrings = {
				"username_valid": "', Lang::getTxt('registration_username_available', file: 'Login'), '",
				"username_invalid": "', Lang::getTxt('registration_username_unavailable', file: 'Login'), '",
				"username_check": "', Lang::getTxt('registration_username_check', file: 'Login'), '",
				"password_short": "', Lang::getTxt('registration_password_short', file: 'Login'), '",
				"password_reserved": "', Lang::getTxt('registration_password_reserved', file: 'Login'), '",
				"password_numbercase": "', Lang::getTxt('registration_password_numbercase', file: 'Login'), '",
				"password_no_match": "', Lang::getTxt('registration_password_no_match', file: 'Login'), '",
				"password_valid": "', Lang::getTxt('registration_password_valid', file: 'Login'), '"
			};
			var verificationHandle = new smfRegister("registration", ', empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'], ', regTextStrings);
		</script>';

	// Everything the passkey script needs to decide whether to offer itself.
	if (!empty(Utils::$context['offer_passkey_signup'])) {
		echo '
		<script>
			var smf_passkey_signup = {
				group: "passkey_signup_group",
				container: "passkey_signup",
				button: "passkey_signup_button",
				field: "smf_autov_username",
				hide: ["password1_group", "password2_group"],
				done: ', Utils::escapeJavaScript(Lang::getTxt('passkey_signup_done', file: 'Login')), ',
				failed: ', Utils::escapeJavaScript(Lang::getTxt('passkey_signup_failed', file: 'Login')), '
			};
		</script>';
	}
}

/**
 * After registration... all done ;).
 */
function template_after()
{
	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['title'], '</h3>
			</div>
			<div class="windowbg">
				<p>', Utils::$context['description'], '</p>
			</div>
		</div>';
}

/**
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	// Formulate a nice complicated message!
	echo '
			<div class="title_bar">
				<h3 class="titlebg">', Utils::$context['page_title'], '</h3>
			</div>
			<div id="coppa" class="roundframe noup">
				<p>', Utils::$context['coppa']['body'], '</p>
				<p>
					<span><a href="', Config::$scripturl, '?action=coppa;form;member=', Utils::$context['coppa']['id'], '" target="_blank" rel="noopener">', Lang::getTxt('coppa_form_link_popup', file: 'Login'), '</a> | <a href="', Config::$scripturl, '?action=coppa;form;dl;member=', Utils::$context['coppa']['id'], '">', Lang::getTxt('coppa_form_link_download', file: 'Login'), '</a></span>
				</p>
				<p>', Lang::getTxt(Utils::$context['coppa']['many_options'] ? 'coppa_send_to_two_options' : 'coppa_send_to_one_option', file: 'Login'), '</p>';

	// Can they send by post?
	if (!empty(Utils::$context['coppa']['post'])) {
		echo '
				<h4>1) ', Lang::getTxt('coppa_send_by_post', file: 'Login'), '</h4>
				<div class="coppa_contact">
					', Utils::$context['coppa']['post'], '
				</div>';
	}

	// Can they send by fax??
	if (!empty(Utils::$context['coppa']['fax'])) {
		echo '
				<h4>', !empty(Utils::$context['coppa']['post']) ? '2' : '1', ') ', Lang::getTxt('coppa_send_by_fax', file: 'Login'), '</h4>
				<div class="coppa_contact">
					', Utils::$context['coppa']['fax'], '
				</div>';
	}

	// Offer an alternative Phone Number?
	if (Utils::$context['coppa']['phone']) {
		echo '
				<p>', Utils::$context['coppa']['phone'], '</p>';
	}

	echo '
			</div><!-- #coppa -->';
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	// Show the form (As best we can)
	echo '
		<table style="width: 100%; padding: 3px; border: 0" class="tborder">
			<tr>
				<td>', Utils::$context['forum_contacts'], '</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', Lang::getTxt('coppa_form_address', file: 'Login'), '</em>: ', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', Lang::getTxt('coppa_form_date', file: 'Login'), '</em>: ', Utils::$context['ul'], '
					<br><br>
				</td>
			</tr>
			<tr>
				<td>
					', Utils::$context['coppa_body'], '
				</td>
			</tr>
		</table>
		<br>';
}

/**
 * Show a window containing the spoken verification code.
 */
function template_verification_sound()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', Lang::getTxt('visual_verification_sound', file: 'General'), '</title>
		<meta name="robots" content="noindex">
		', Theme::template_css(), '
		<style>';

	// Just show the help text and a "close window" link.
	echo '
		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;">';

	if (BrowserDetector::isBrowser('is_ie') || BrowserDetector::isBrowser('is_ie11')) {
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="', Utils::$context['verification_sound_href'], '">
			</object>';
	} else {
	echo '
			<audio src="', Utils::$context['verification_sound_href'], '" controls>
				<object type="audio/x-wav" data="', Utils::$context['verification_sound_href'], '">
					<a href="', Utils::$context['verification_sound_href'], '" rel="nofollow">', Utils::$context['verification_sound_href'], '</a>
				</object>
			</audio>';
	}

	echo '
			<br>
			<a href="', Utils::$context['verification_sound_href'], ';sound" rel="nofollow">', Lang::getTxt('visual_verification_sound_again', file: 'Login'), '</a><br>
			<a href="', Utils::$context['verification_sound_href'], '" rel="nofollow">', Lang::getTxt('visual_verification_sound_direct', file: 'Login'), '</a><br><br>
			<a href="javascript:self.close();">', Lang::getTxt('visual_verification_sound_close', file: 'Login'), '</a><br>
		</div><!-- .description -->
	</body>
</html>';
}

/**
 * The template for the form allowing an admin to register a user from the admin center.
 */
function template_admin_register()
{
	echo '
		<div id="admin_form_wrapper">
			<form id="postForm" action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::getTxt('admin_browse_register_new', file: 'Admin'), '</h3>
				</div>
				<div id="register_screen" class="windowbg">';

	if (!empty(Utils::$context['registration_done'])) {
		echo '
					<div class="infobox">
						', Utils::$context['registration_done'], '
					</div>';
	}

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', Lang::getTxt('admin_register_username', file: 'Login'), '</label></strong>
							<span class="smalltext">', Lang::getTxt('admin_register_username_desc', file: 'Login'), '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" size="50" maxlength="25">
						</dd>
						<dt>
							<strong><label for="email_input">', Lang::getTxt('admin_register_email', file: 'Login'), '</label></strong>
							<span class="smalltext">', Lang::getTxt('admin_register_email_desc', file: 'Login'), '</span>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" size="50">
						</dd>
						<dt>
							<strong><label for="password_input">', Lang::getTxt('admin_register_password', file: 'Login'), '</label></strong>
							<span class="smalltext">', Lang::getTxt('admin_register_password_desc', file: 'Login'), '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" size="50" onchange="onCheckChange();">
						</dd>';

	if (!empty(Utils::$context['member_groups'])) {
		echo '
						<dt>
							<strong><label for="group_select">', Lang::getTxt('admin_register_group', file: 'Login'), '</label></strong>
							<span class="smalltext">', Lang::getTxt('admin_register_group_desc', file: 'Login'), '</span>
						</dt>
						<dd>
							<select name="group" id="group_select">';

		foreach (Utils::$context['member_groups'] as $id => $name) {
			echo '
								<option value="', $id, '">', $name, '</option>';
		}

		echo '
							</select>
						</dd>';
	}

	// If there is any field marked as required, show it here!
	if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields'])) {
		foreach (Utils::$context['custom_fields'] as $field) {
			if ($field['show_reg'] > 1) {
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>
							', $field['input_html'], '
						</dd>';
			}
		}
	}

	echo '
						<dt>
							<strong><label for="emailPassword_check">', Lang::getTxt('admin_register_email_detail', file: 'Login'), '</label></strong>
							<span class="smalltext">', Lang::getTxt('admin_register_email_detail_desc', file: 'Login'), '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" checked disabled>
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', Lang::getTxt('admin_register_email_activate', file: 'Login'), '</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check"', !empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="', Lang::getTxt('register', file: 'General'), '" class="button">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						<input type="hidden" name="', Utils::$context['admin-regc_token_var'], '" value="', Utils::$context['admin-regc_token'], '">
					</div>
				</div><!-- #register_screen -->
			</form>
		</div><!-- #admin_form_wrapper -->
	<br class="clear">';
}

/**
 * Form for editing the agreement shown for people registering to the forum.
 */
function template_edit_agreement()
{
	if (!empty(Utils::$context['saved_successful'])) {
		echo '
		<div class="infobox">', Lang::getTxt('settings_saved', file: 'Admin'), '</div>';
	} elseif (!empty(Utils::$context['could_not_save'])) {
		echo '
		<div class="errorbox">', Lang::getTxt('admin_agreement_not_saved', file: 'Admin'), '</div>';
	}

	// Warning for if the file isn't writable.
	if (!empty(Utils::$context['warning'])) {
		echo '
		<div class="errorbox">', Utils::$context['warning'], '</div>';
	}

	// Just a big box to edit the text file ;)
	echo '
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('registration_agreement', file: 'General'), '</h3>
			</div>';

	// Is there more than one language to choose from?
	if (count(Utils::$context['editable_agreements']) > 1) {
		echo '
				<div class="information">
					<form action="', Config::$scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="UTF-8">
						<strong>', Lang::getTxt('admin_agreement_select_language', file: 'Admin'), '</strong>
						<select name="agree_lang" onchange="document.getElementById(\'change_reg\').submit();">';

		foreach (Utils::$context['editable_agreements'] as $file => $name) {
			echo '
							<option value="', $file, '"', Utils::$context['current_agreement'] == $file ? ' selected' : '', '>', $name, '</option>';
		}

		echo '
						</select>
						<div class="righttext">
							<input type="hidden" name="sa" value="agreement">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
							<input type="hidden" name="', Utils::$context['admin-rega_token_var'], '" value="', Utils::$context['admin-rega_token'], '">
							<input type="submit" name="change" value="', Lang::getTxt('admin_agreement_select_language', file: 'Admin'), '" class="button">
						</div>
					</form>
				</div><!-- .information -->';
	}

	// Show the actual agreement in an oversized text box.
	echo '
			<div class="windowbg" id="registration_agreement">
				<form action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
					<textarea cols="70" rows="20" name="agreement" id="agreement">', Utils::$context['agreement'], '</textarea>
					<div class="information">';

	if (empty(Utils::$context['agreement_history'])) {
		echo '
						<span>', Utils::$context['agreement_info'], '</span>';
	} else {
		echo '
						<a href="" onclick="return false;" class="modified">', Utils::$context['agreement_info'], '</a>
						<div id="edit_history_list_' . Utils::$context['current_agreement'] . '" class="edit_history_list">
							<div class="edit_history_count">
								' . Lang::getTxt('edit_history_count', [count(Utils::$context['agreement_history'])], file: 'General') . '
							</div>
							<ol>';

		foreach (Utils::$context['agreement_history'] as $hash => $linktext) {
			echo '
								<li>
									<a href="' . Config::$scripturl . '?action=agreement;sa=history;doc=0;lang=' . Utils::$context['current_agreement'] . ';hash=' . $hash . '" onclick="return reqOverlayDiv(this.href, ' . Utils::escapeJavaScript($linktext) . ', \'history\');">' . $linktext . '</a>
								</li>';
		}

		echo '
							</ol>
						</div>';
	}

	echo '
					</div>
					<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" class="button" onclick="return resetAgreementConfirm()" />
					<input type="hidden" name="agree_lang" value="', Utils::$context['current_agreement'], '">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<script>
						function resetAgreementConfirm()
						{
							return true;
						}
					</script>
					<input type="hidden" name="', Utils::$context['admin-rega_token_var'], '" value="', Utils::$context['admin-rega_token'], '">
				</form>
			</div><!-- #registration_agreement -->
		</div><!-- #admin_form_wrapper -->';
}

/**
 * Template for editing reserved words.
 */
function template_edit_reserved_words()
{
	if (!empty(Utils::$context['saved_successful'])) {
		echo '
	<div class="infobox">', Lang::getTxt('settings_saved', file: 'Admin'), '</div>';
	}

	echo '
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('admin_reserved_set', file: 'Admin'), '</h3>
		</div>
		<div class="windowbg">
			<h4>', Lang::getTxt('admin_reserved_line', file: 'Admin'), '</h4>
			<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", Utils::$context['reserved_words']), '</textarea>
			<dl class="settings">
				<dt>
					<label for="matchword">', Lang::getTxt('admin_match_whole', file: 'Admin'), '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchword" id="matchword"', Utils::$context['reserved_word_options']['match_word'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchcase">', Lang::getTxt('admin_match_case', file: 'Admin'), '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchcase" id="matchcase"', Utils::$context['reserved_word_options']['match_case'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchuser">', Lang::getTxt('admin_check_user', file: 'Admin'), '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchuser" id="matchuser"', Utils::$context['reserved_word_options']['match_user'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchname">', Lang::getTxt('admin_check_display', file: 'Admin'), '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchname" id="matchname"', Utils::$context['reserved_word_options']['match_name'] ? ' checked' : '', '>
				</dd>
			</dl>
			<div class="flow_auto">
				<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" name="save_reserved_names" class="button">
				<input type="hidden" name="sa" value="reservednames">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-regr_token_var'], '" value="', Utils::$context['admin-regr_token'], '">
			</div>
		</div><!-- .windowbg -->
	</form>';
}

// Form for editing the privacy policy shown to people registering to the forum.
function template_edit_privacy_policy()
{
	if (!empty(Utils::$context['saved_successful'])) {
		echo '
		<div class="infobox">', Lang::getTxt('settings_saved', file: 'Admin'), '</div>';
	}

	// Just a big box to edit the text file ;).
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('privacy_policy', file: 'General'), '</h3>
		</div>';

	// Is there more than one language to choose from?
	if (count(Utils::$context['editable_policies']) > 1) {
		echo '
			<div class="information">
				<form action="', Config::$scripturl, '?action=admin;area=regcenter" id="change_policy" method="post" accept-charset="UTF-8">
					<strong>', Lang::getTxt('admin_agreement_select_language', file: 'Admin'), '</strong>
					<select name="policy_lang" onchange="document.getElementById(\'change_policy\').submit();">';

		foreach (Utils::$context['editable_policies'] as $lang => $name) {
			echo '
						<option value="', $lang, '" ', Utils::$context['current_policy_lang'] == $lang ? 'selected="selected"' : '', '>', $name, '</option>';
		}

		echo '
					</select>
					<div class="righttext">
						<input type="hidden" name="sa" value="policy">
						<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						<input type="submit" name="change" value="', Lang::getTxt('admin_agreement_select_language_change', file: 'Admin'), '" class="button">
					</div>
				</form>
			</div>';
	}

	echo '
		<div class="windowbg" id="privacy_policy">
			<form action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">';

	// Show the actual policy in an oversized text box.
	echo '
			<textarea cols="70" rows="20" name="policy" id="agreement">', Utils::$context['privacy_policy'], '</textarea>
				<div class="information">';

	if (empty(Utils::$context['privacy_policy_history'])) {
		echo '
					<span>', Utils::$context['privacy_policy_info'], '</span>';
	} else {
		echo '
					<a href="" onclick="return false;" class="modified">', Utils::$context['privacy_policy_info'], '</a>
					<div id="edit_history_list_' . Utils::$context['current_policy_lang'] . '" class="edit_history_list">
						<div class="edit_history_count">
							' . Lang::getTxt('edit_history_count', [count(Utils::$context['privacy_policy_history'])], file: 'General') . '
						</div>
						<ol>';

		foreach (Utils::$context['privacy_policy_history'] as $hash => $linktext) {
			echo '
							<li>
								<a href="' . Config::$scripturl . '?action=agreement;sa=history;doc=1;lang=' . Utils::$context['current_policy_lang'] . ';hash=' . $hash . '" onclick="return reqOverlayDiv(this.href, ' . Utils::escapeJavaScript($linktext) . ', \'history\');">' . $linktext . '</a>
							</li>';
		}

		echo '
						</ol>
					</div>';
	}

	echo '
				</div>
				<div class="righttext">
					<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" class="button" onclick="return resetPolicyConfirm()" />
					<input type="hidden" name="policy_lang" value="', Utils::$context['current_policy_lang'], '" />
					<input type="hidden" name="sa" value="policy" />
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '" />
					<input type="hidden" name="', Utils::$context['admin-regp_token_var'], '" value="', Utils::$context['admin-regp_token'], '" />
					<script>
						function resetPolicyConfirm()
						{
							return true;
						}
					</script>
				</div>
			</form>
		</div>';
}
