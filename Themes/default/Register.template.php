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
		<form action="', Config::$scripturl, '?action=signup" method="post" accept-charset="', Utils::$context['character_set'], '" id="registration">';

	if (!empty(Utils::$context['agreement']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['registration_agreement'], '</h3>
			</div>
			<div class="roundframe">
				<div>', Utils::$context['agreement'], '</div>
			</div>';

	if (!empty(Utils::$context['privacy_policy']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['privacy_policy'], '</h3>
			</div>
			<div class="roundframe">
				<div>', Utils::$context['privacy_policy'], '</div>
			</div>';

		echo '
			<div id="confirm_buttons">';

	// Age restriction in effect?
	if (Utils::$context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', Utils::$context['coppa_agree_below'], '" class="button">';
	else
		echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['agree'], '" class="button" />';

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
				if (currentAuthMethod == \'passwd\' && document.forms.registration.smf_autov_pwmain.value != document.forms.registration.smf_autov_pwverify.value)
				{
					alert("', Lang::$txt['register_passwords_differ_js'], '");
					return false;
				}

				return true;
			}

			var currentAuthMethod = \'passwd\';
		</script>';

	// Any errors?
	if (!empty(Utils::$context['registration_errors']))
	{
		echo '
		<div class="errorbox">
			<span>', Lang::$txt['registration_errors_occurred'], '</span>
			<ul>';

		// Cycle through each error and display an error message.
		foreach (Utils::$context['registration_errors'] as $error)
			echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, array('http://' => 'https://')) : Config::$scripturl, '?action=signup2" method="post" accept-charset="', Utils::$context['character_set'], '" name="registration" id="registration" onsubmit="return verifyAgree();">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['registration_form'], '</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">', Lang::$txt['required_info'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form">
						<dt>
							<strong><label for="smf_autov_username">', Lang::$txt['username'], ':</label></strong>
						</dt>
						<dd>
							<input type="text" name="user" id="smf_autov_username" size="50" tabindex="', Utils::$context['tabindex']++, '" maxlength="25" value="', isset(Utils::$context['username']) ? Utils::$context['username'] : '', '">
							<span id="smf_autov_username_div" style="display: none;">
								<a id="smf_autov_username_link" href="#">
									<span id="smf_autov_username_img" class="main_icons check"></span>
								</a>
							</span>
						</dd>
						<dt><strong><label for="smf_autov_reserve1">', Lang::$txt['user_email_address'], ':</label></strong></dt>
						<dd>
							<input type="email" name="email" id="smf_autov_reserve1" size="50" tabindex="', Utils::$context['tabindex']++, '" value="', isset(Utils::$context['email']) ? Utils::$context['email'] : '', '">
						</dd>
					</dl>
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="smf_autov_pwmain">', Lang::$txt['choose_pass'], ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="smf_autov_pwmain" size="50" tabindex="', Utils::$context['tabindex']++, '">
							<span id="smf_autov_pwmain_div" style="display: none;">
								<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt>
							<strong><label for="smf_autov_pwverify">', Lang::$txt['verify_pass'], ':</label></strong>
						</dt>
						<dd>
							<input type="password" name="passwrd2" id="smf_autov_pwverify" size="50" tabindex="', Utils::$context['tabindex']++, '">
							<span id="smf_autov_pwverify_div" style="display: none;">
								<span id="smf_autov_pwverify_img" class="main_icons valid"></span>
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="notify_announcements">
						<dt>
							<strong><label for="notify_announcements">', Lang::$txt['notify_announcements'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="notify_announcements" id="notify_announcements" tabindex="', Utils::$context['tabindex']++, '"', Utils::$context['notify_announcements'] ? ' checked="checked"' : '', '>
						</dd>
					</dl>';

	// If there is any field marked as required, show it here!
	if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields']))
	{
		echo '
					<dl class="register_form">';

		foreach (Utils::$context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', str_replace('name="', 'tabindex="' . Utils::$context['tabindex']++ . '" name="', $field['input_html']), '</dd>';

		echo '
					</dl>';
	}

	echo '
				</fieldset>
			</div><!-- .roundframe -->';

	// If we have either of these, show the extra group.
	if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields']))
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', Lang::$txt['additional_information'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form" id="custom_group">';

	if (!empty(Utils::$context['profile_fields']))
	{
		// Any fields we particularly want?
		foreach (Utils::$context['profile_fields'] as $key => $field)
		{
			if ($field['type'] == 'callback')
			{
				if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
				{
					$callback_func = 'template_profile_' . $field['callback_func'];
					$callback_func();
				}
			}
			else
			{
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['label'], ':</strong>';

				// Does it have any subtext to show?
				if (!empty($field['subtext']))
					echo '
							<span class="smalltext">', $field['subtext'], '</span>';

				echo '
						</dt>
						<dd>';

				// Want to put something infront of the box?
				if (!empty($field['preinput']))
					echo '
							', $field['preinput'];

				// What type of data are we showing?
				if ($field['type'] == 'label')
					echo '
							', $field['value'];

				// Maybe it's a text box - very likely!
				elseif (in_array($field['type'], array('int', 'float', 'text', 'password', 'url')))
					echo '
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', Utils::$context['tabindex']++, '" ', $field['input_attr'], '>';

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check')
					echo '
							<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" tabindex="', Utils::$context['tabindex']++, '" ', $field['input_attr'], '>';

				// Always fun - select boxes!
				elseif ($field['type'] == 'select')
				{
					echo '
							<select name="', $key, '" id="', $key, '" tabindex="', Utils::$context['tabindex']++, '">';

					if (isset($field['options']))
					{
						// Is this some code to generate the options?
						if (!is_array($field['options']))
							$field['options'] = eval($field['options']);

						// Assuming we now have some!
						if (is_array($field['options']))
							foreach ($field['options'] as $value => $name)
								echo '
								<option', (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ''), ' value="' . $value . '"', $value === $field['value'] ? ' selected' : '', '>', $name, '</option>';
					}

					echo '
							</select>';
				}

				// Something to end with?
				if (!empty($field['postinput']))
					echo '
							', $field['postinput'];

				echo '
						</dd>';
			}
		}
	}

	// Are there any custom fields?
	if (!empty(Utils::$context['custom_fields']))
	{
		foreach (Utils::$context['custom_fields'] as $field)
			if ($field['show_reg'] < 2)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', $field['input_html'], '</dd>';
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty(Utils::$context['profile_fields']) || !empty(Utils::$context['custom_fields']))
		echo '
					</dl>
				</fieldset>
			</div><!-- .roundframe -->';

	if (Utils::$context['visual_verification'])
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', Lang::$txt['verification'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset class="centertext">
					', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';

	echo '
			<div id="confirm_buttons" class="flow_auto">';

	// Age restriction in effect?
	if (empty(Utils::$context['agree']) && Utils::$context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', Utils::$context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', Utils::$context['coppa_agree_below'], '" class="button">';
	else
		echo '
				<input type="submit" name="regSubmit" value="', Lang::$txt['register'], '" tabindex="', Utils::$context['tabindex']++, '" class="button" onclick="this.disabled = true;form.submit();">';

	echo '
			</div>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['register_token_var'], '" value="', Utils::$context['register_token'], '">
			<input type="hidden" name="step" value="2">
		</form>
		<script>
			var regTextStrings = {
				"username_valid": "', Lang::$txt['registration_username_available'], '",
				"username_invalid": "', Lang::$txt['registration_username_unavailable'], '",
				"username_check": "', Lang::$txt['registration_username_check'], '",
				"password_short": "', Lang::$txt['registration_password_short'], '",
				"password_reserved": "', Lang::$txt['registration_password_reserved'], '",
				"password_numbercase": "', Lang::$txt['registration_password_numbercase'], '",
				"password_no_match": "', Lang::$txt['registration_password_no_match'], '",
				"password_valid": "', Lang::$txt['registration_password_valid'], '"
			};
			var verificationHandle = new smfRegister("registration", ', empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'], ', regTextStrings);
		</script>';
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
					<span><a href="', Config::$scripturl, '?action=coppa;form;member=', Utils::$context['coppa']['id'], '" target="_blank" rel="noopener">', Lang::$txt['coppa_form_link_popup'], '</a> | <a href="', Config::$scripturl, '?action=coppa;form;dl;member=', Utils::$context['coppa']['id'], '">', Lang::$txt['coppa_form_link_download'], '</a></span>
				</p>
				<p>', Utils::$context['coppa']['many_options'] ? Lang::$txt['coppa_send_to_two_options'] : Lang::$txt['coppa_send_to_one_option'], '</p>';

	// Can they send by post?
	if (!empty(Utils::$context['coppa']['post']))
		echo '
				<h4>1) ', Lang::$txt['coppa_send_by_post'], '</h4>
				<div class="coppa_contact">
					', Utils::$context['coppa']['post'], '
				</div>';

	// Can they send by fax??
	if (!empty(Utils::$context['coppa']['fax']))
		echo '
				<h4>', !empty(Utils::$context['coppa']['post']) ? '2' : '1', ') ', Lang::$txt['coppa_send_by_fax'], '</h4>
				<div class="coppa_contact">
					', Utils::$context['coppa']['fax'], '
				</div>';

	// Offer an alternative Phone Number?
	if (Utils::$context['coppa']['phone'])
		echo '
				<p>', Utils::$context['coppa']['phone'], '</p>';

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
					<em>', Lang::$txt['coppa_form_address'], '</em>: ', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '<br>
					', Utils::$context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', Lang::$txt['coppa_form_date'], '</em>: ', Utils::$context['ul'], '
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
		<meta charset="', Utils::$context['character_set'], '">
		<title>', Lang::$txt['visual_verification_sound'], '</title>
		<meta name="robots" content="noindex">
		', Theme::template_css(), '
		<style>';

	// Just show the help text and a "close window" link.
	echo '
		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;">';

	if (BrowserDetector::isBrowser('is_ie') || BrowserDetector::isBrowser('is_ie11'))
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="', Utils::$context['verification_sound_href'], '">
			</object>';
	else
		echo '
			<audio src="', Utils::$context['verification_sound_href'], '" controls>
				<object type="audio/x-wav" data="', Utils::$context['verification_sound_href'], '">
					<a href="', Utils::$context['verification_sound_href'], '" rel="nofollow">', Utils::$context['verification_sound_href'], '</a>
				</object>
			</audio>';

	echo '
			<br>
			<a href="', Utils::$context['verification_sound_href'], ';sound" rel="nofollow">', Lang::$txt['visual_verification_sound_again'], '</a><br>
			<a href="', Utils::$context['verification_sound_href'], '" rel="nofollow">', Lang::$txt['visual_verification_sound_direct'], '</a><br><br>
			<a href="javascript:self.close();">', Lang::$txt['visual_verification_sound_close'], '</a><br>
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
			<form id="postForm" action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', Utils::$context['character_set'], '" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['admin_browse_register_new'], '</h3>
				</div>
				<div id="register_screen" class="windowbg">';

	if (!empty(Utils::$context['registration_done']))
		echo '
					<div class="infobox">
						', Utils::$context['registration_done'], '
					</div>';

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', Lang::$txt['admin_register_username'], ':</label></strong>
							<span class="smalltext">', Lang::$txt['admin_register_username_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', Utils::$context['tabindex']++, '" size="50" maxlength="25">
						</dd>
						<dt>
							<strong><label for="email_input">', Lang::$txt['admin_register_email'], ':</label></strong>
							<span class="smalltext">', Lang::$txt['admin_register_email_desc'], '</span>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" tabindex="', Utils::$context['tabindex']++, '" size="50">
						</dd>
						<dt>
							<strong><label for="password_input">', Lang::$txt['admin_register_password'], ':</label></strong>
							<span class="smalltext">', Lang::$txt['admin_register_password_desc'], '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', Utils::$context['tabindex']++, '" size="50" onchange="onCheckChange();">
						</dd>';

	if (!empty(Utils::$context['member_groups']))
	{
		echo '
						<dt>
							<strong><label for="group_select">', Lang::$txt['admin_register_group'], ':</label></strong>
							<span class="smalltext">', Lang::$txt['admin_register_group_desc'], '</span>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', Utils::$context['tabindex']++, '">';

		foreach (Utils::$context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	// If there is any field marked as required, show it here!
	if (!empty(Utils::$context['custom_fields_required']) && !empty(Utils::$context['custom_fields']))
		foreach (Utils::$context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>
							', str_replace('name="', 'tabindex="' . Utils::$context['tabindex']++ . '" name="', $field['input_html']), '
						</dd>';

	echo '
						<dt>
							<strong><label for="emailPassword_check">', Lang::$txt['admin_register_email_detail'], ':</label></strong>
							<span class="smalltext">', Lang::$txt['admin_register_email_detail_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', Utils::$context['tabindex']++, '" checked disabled>
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', Lang::$txt['admin_register_email_activate'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', Utils::$context['tabindex']++, '"', !empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="', Lang::$txt['register'], '" tabindex="', Utils::$context['tabindex']++, '" class="button">
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
	if (!empty(Utils::$context['saved_successful']))
		echo '
		<div class="infobox">', Lang::$txt['settings_saved'], '</div>';

	elseif (!empty(Utils::$context['could_not_save']))
		echo '
		<div class="errorbox">', Lang::$txt['admin_agreement_not_saved'], '</div>';

	// Warning for if the file isn't writable.
	if (!empty(Utils::$context['warning']))
		echo '
		<div class="errorbox">', Utils::$context['warning'], '</div>';

	// Just a big box to edit the text file ;)
	echo '
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['registration_agreement'], '</h3>
			</div>
			<div class="windowbg" id="registration_agreement">';

	// Is there more than one language to choose from?
	if (count(Utils::$context['editable_agreements']) > 1)
	{
		echo '
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['language_configuration'], '</h3>
				</div>
				<div class="information">
					<form action="', Config::$scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="', Utils::$context['character_set'], '" style="display: inline;">
						<strong>', Lang::$txt['admin_agreement_select_language'], ':</strong>
						<select name="agree_lang" onchange="document.getElementById(\'change_reg\').submit();" tabindex="', Utils::$context['tabindex']++, '">';

		foreach (Utils::$context['editable_agreements'] as $file => $name)
			echo '
							<option value="', $file, '"', Utils::$context['current_agreement'] == $file ? ' selected' : '', '>', $name, '</option>';

		echo '
						</select>
						<div class="righttext">
							<input type="hidden" name="sa" value="agreement">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
							<input type="hidden" name="', Utils::$context['admin-rega_token_var'], '" value="', Utils::$context['admin-rega_token'], '">
							<input type="submit" name="change" value="', Lang::$txt['admin_agreement_select_language_change'], '" tabindex="', Utils::$context['tabindex']++, '" class="button">
						</div>
					</form>
				</div><!-- .information -->';
	}

	// Show the actual agreement in an oversized text box.
	echo '
				<form action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', Utils::$context['character_set'], '">
					<textarea cols="70" rows="20" name="agreement" id="agreement">', Utils::$context['agreement'], '</textarea>
					<div class="information">
						<span>', Utils::$context['agreement_info'], '</span>
					</div>
					<div class="righttext"><input type="submit" value="', Lang::$txt['save'], '" tabindex="', Utils::$context['tabindex']++, '" class="button" onclick="return resetAgreementConfirm()" />
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
	if (!empty(Utils::$context['saved_successful']))
		echo '
	<div class="infobox">', Lang::$txt['settings_saved'], '</div>';

	echo '
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['admin_reserved_set'], '</h3>
		</div>
		<div class="windowbg">
			<h4>', Lang::$txt['admin_reserved_line'], '</h4>
			<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", Utils::$context['reserved_words']), '</textarea>
			<dl class="settings">
				<dt>
					<label for="matchword">', Lang::$txt['admin_match_whole'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchword" id="matchword" tabindex="', Utils::$context['tabindex']++, '"', Utils::$context['reserved_word_options']['match_word'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchcase">', Lang::$txt['admin_match_case'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchcase" id="matchcase" tabindex="', Utils::$context['tabindex']++, '"', Utils::$context['reserved_word_options']['match_case'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchuser">', Lang::$txt['admin_check_user'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchuser" id="matchuser" tabindex="', Utils::$context['tabindex']++, '"', Utils::$context['reserved_word_options']['match_user'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchname">', Lang::$txt['admin_check_display'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchname" id="matchname" tabindex="', Utils::$context['tabindex']++, '"', Utils::$context['reserved_word_options']['match_name'] ? ' checked' : '', '>
				</dd>
			</dl>
			<div class="flow_auto">
				<input type="submit" value="', Lang::$txt['save'], '" name="save_reserved_names" tabindex="', Utils::$context['tabindex']++, '" class="button">
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
	if (!empty(Utils::$context['saved_successful']))
		echo '
		<div class="infobox">', Lang::$txt['settings_saved'], '</div>';

	// Just a big box to edit the text file ;).
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['privacy_policy'], '</h3>
		</div>
		<div class="windowbg" id="privacy_policy">';

	// Is there more than one language to choose from?
	if (count(Utils::$context['editable_policies']) > 1)
	{
		echo '
			<div class="information">
				<form action="', Config::$scripturl, '?action=admin;area=regcenter" id="change_policy" method="post" accept-charset="', Utils::$context['character_set'], '" style="display: inline;">
					<strong>', Lang::$txt['admin_agreement_select_language'], ':</strong>
					<select name="policy_lang" onchange="document.getElementById(\'change_policy\').submit();" tabindex="', Utils::$context['tabindex']++, '">';

		foreach (Utils::$context['editable_policies'] as $lang => $name)
			echo '
						<option value="', $lang, '" ', Utils::$context['current_policy_lang'] == $lang ? 'selected="selected"' : '', '>', $name, '</option>';

		echo '
					</select>
					<div class="righttext">
						<input type="hidden" name="sa" value="policy">
						<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
						<input type="submit" name="change" value="', Lang::$txt['admin_agreement_select_language_change'], '" tabindex="', Utils::$context['tabindex']++, '" class="button">
					</div>
				</form>
			</div>';
	}

	echo '
			<form action="', Config::$scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', Utils::$context['character_set'], '">';

	// Show the actual policy in an oversized text box.
	echo '
			<textarea cols="70" rows="20" name="policy" id="agreement">', Utils::$context['privacy_policy'], '</textarea>
				<div class="information">', Utils::$context['privacy_policy_info'], '</div>
				<div class="righttext">
					<input type="submit" value="', Lang::$txt['save'], '" tabindex="', Utils::$context['tabindex']++, '" class="button" onclick="return resetPolicyConfirm()" />
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

?>