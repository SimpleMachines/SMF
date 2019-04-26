<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=signup" method="post" accept-charset="', $context['character_set'], '" id="registration">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['registration_agreement'], '</h3>
			</div>
			<div class="roundframe">
				<p>', $context['agreement'], '</p>
			</div>
			<div id="confirm_buttons">';

	// Age restriction in effect?
	if ($context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" class="button">';
	else
		echo '
				<input type="submit" name="accept_agreement" value="', $txt['agreement_agree'], '" class="button">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '">
			</div><!-- .confirm_buttons -->
			<input type="hidden" name="step" value="1">
		</form>';

}

/**
 * Before registering - get their information.
 */
function template_registration_form()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
		<script>
			function verifyAgree()
			{
				if (currentAuthMethod == \'passwd\' && document.forms.registration.smf_autov_pwmain.value != document.forms.registration.smf_autov_pwverify.value)
				{
					alert("', $txt['register_passwords_differ_js'], '");
					return false;
				}

				return true;
			}

			var currentAuthMethod = \'passwd\';
		</script>';

	// Any errors?
	if (!empty($context['registration_errors']))
	{
		echo '
		<div class="errorbox">
			<span>', $txt['registration_errors_occurred'], '</span>
			<ul>';

		// Cycle through each error and display an error message.
		foreach ($context['registration_errors'] as $error)
			echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', !empty($modSettings['force_ssl']) ? strtr($scripturl, array('http://' => 'https://')) : $scripturl, '?action=signup2" method="post" accept-charset="', $context['character_set'], '" name="registration" id="registration" onsubmit="return verifyAgree();">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['registration_form'], '</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">', $txt['required_info'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form">
						<dt>
							<strong><label for="smf_autov_username">', $txt['username'], ':</label></strong>
						</dt>
						<dd>
							<input type="text" name="user" id="smf_autov_username" size="50" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '">
							<span id="smf_autov_username_div" style="display: none;">
								<a id="smf_autov_username_link" href="#">
									<span id="smf_autov_username_img" class="main_icons check"></span>
								</a>
							</span>
						</dd>
						<dt><strong><label for="smf_autov_reserve1">', $txt['user_email_address'], ':</label></strong></dt>
						<dd>
							<input type="text" name="email" id="smf_autov_reserve1" size="50" tabindex="', $context['tabindex']++, '" value="', isset($context['email']) ? $context['email'] : '', '">
						</dd>
					</dl>
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="smf_autov_pwmain">', ucwords($txt['choose_pass']), ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="smf_autov_pwmain" size="50" tabindex="', $context['tabindex']++, '">
							<span id="smf_autov_pwmain_div" style="display: none;">
								<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt>
							<strong><label for="smf_autov_pwverify">', ucwords($txt['verify_pass']), ':</label></strong>
						</dt>
						<dd>
							<input type="password" name="passwrd2" id="smf_autov_pwverify" size="50" tabindex="', $context['tabindex']++, '">
							<span id="smf_autov_pwverify_div" style="display: none;">
								<span id="smf_autov_pwverify_img" class="main_icons valid"></span>
							</span>
						</dd>
					</dl>';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
					<dl class="register_form" id="notify_announcements">
						<dt>
							<strong><label for="notify_announcements">', $txt['notify_announcements'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="notify_announcements" id="notify_announcements" tabindex="', $context['tabindex']++, '"', $context['notify_announcements'] ? ' checked="checked"' : '', '>
						</dd>
					</dl>';

	// If there is any field marked as required, show it here!
	if (!empty($context['custom_fields_required']) && !empty($context['custom_fields']))
	{
		echo '
					<dl class="register_form">';

		foreach ($context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', str_replace('name="', 'tabindex="' . $context['tabindex']++ . '" name="', $field['input_html']), '</dd>';

		echo '
					</dl>';
	}

	echo '
				</fieldset>
			</div><!-- .roundframe -->';

	// If we have either of these, show the extra group.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['additional_information'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset>
					<dl class="register_form" id="custom_group">';

	if (!empty($context['profile_fields']))
	{
		// Any fields we particularly want?
		foreach ($context['profile_fields'] as $key => $field)
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
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', $context['tabindex']++, '" ', $field['input_attr'], '>';

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check')
					echo '
							<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" tabindex="', $context['tabindex']++, '" ', $field['input_attr'], '>';

				// Always fun - select boxes!
				elseif ($field['type'] == 'select')
				{
					echo '
							<select name="', $key, '" id="', $key, '" tabindex="', $context['tabindex']++, '">';

					if (isset($field['options']))
					{
						// Is this some code to generate the options?
						if (!is_array($field['options']))
							$field['options'] = eval($field['options']);

						// Assuming we now have some!
						if (is_array($field['options']))
							foreach ($field['options'] as $value => $name)
								echo '
								<option', (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ''), ' value="' . $value . '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
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
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
			if ($field['show_reg'] < 2)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', $field['input_html'], '</dd>';
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
		echo '
					</dl>
				</fieldset>
			</div><!-- .roundframe -->';

	if ($context['visual_verification'])
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['verification'], '</h3>
			</div>
			<div class="roundframe noup">
				<fieldset class="centertext">
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';

	echo '
			<div id="confirm_buttons" class="flow_auto">';

	// Age restriction in effect?
	if (!$context['require_agreement'] && $context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" class="button">';
	else
		echo '
				<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="button">';

	echo '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '">
			<input type="hidden" name="step" value="2">
		</form>
		<script>
			var regTextStrings = {
				"username_valid": "', $txt['registration_username_available'], '",
				"username_invalid": "', $txt['registration_username_unavailable'], '",
				"username_check": "', $txt['registration_username_check'], '",
				"password_short": "', $txt['registration_password_short'], '",
				"password_reserved": "', $txt['registration_password_reserved'], '",
				"password_numbercase": "', $txt['registration_password_numbercase'], '",
				"password_no_match": "', $txt['registration_password_no_match'], '",
				"password_valid": "', $txt['registration_password_valid'], '"
			};
			var verificationHandle = new smfRegister("registration", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
		</script>';
}

/**
 * After registration... all done ;).
 */
function template_after()
{
	global $context;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
			<div class="cat_bar">
				<h3 class="catbg">', $context['title'], '</h3>
			</div>
			<div class="windowbg">
				<p>', $context['description'], '</p>
			</div>
		</div>';
}

/**
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	global $context, $txt, $scripturl;

	// Formulate a nice complicated message!
	echo '
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div id="coppa" class="roundframe noup">
				<p>', $context['coppa']['body'], '</p>
				<p>
					<span><a href="', $scripturl, '?action=coppa;form;member=', $context['coppa']['id'], '" target="_blank" rel="noopener">', $txt['coppa_form_link_popup'], '</a> | <a href="', $scripturl, '?action=coppa;form;dl;member=', $context['coppa']['id'], '">', $txt['coppa_form_link_download'], '</a></span>
				</p>
				<p>', $context['coppa']['many_options'] ? $txt['coppa_send_to_two_options'] : $txt['coppa_send_to_one_option'], '</p>';

	// Can they send by post?
	if (!empty($context['coppa']['post']))
		echo '
				<h4>1) ', $txt['coppa_send_by_post'], '</h4>
				<div class="coppa_contact">
					', $context['coppa']['post'], '
				</div>';

	// Can they send by fax??
	if (!empty($context['coppa']['fax']))
		echo '
				<h4>', !empty($context['coppa']['post']) ? '2' : '1', ') ', $txt['coppa_send_by_fax'], '</h4>
				<div class="coppa_contact">
					', $context['coppa']['fax'], '
				</div>';

	// Offer an alternative Phone Number?
	if ($context['coppa']['phone'])
		echo '
				<p>', $context['coppa']['phone'], '</p>';

	echo '
			</div><!-- #coppa -->';
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	global $context, $txt;

	// Show the form (As best we can)
	echo '
		<table style="width: 100%; padding: 3px; border: 0" class="tborder">
			<tr>
				<td>', $context['forum_contacts'], '</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_address'], '</em>: ', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_date'], '</em>: ', $context['ul'], '
					<br><br>
				</td>
			</tr>
			<tr>
				<td>
					', $context['coppa_body'], '
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
	global $context, $settings, $txt, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $txt['visual_verification_sound'], '</title>
		<meta name="robots" content="noindex">
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $context['browser_cache'], '">
		<style>';

	// Just show the help text and a "close window" link.
	echo '
		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;">';

	if (isBrowser('is_ie') || isBrowser('is_ie11'))
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="', $context['verification_sound_href'], '">
			</object>';
	else
		echo '
			<audio src="', $context['verification_sound_href'], '" controls>
				<object type="audio/x-wav" data="', $context['verification_sound_href'], '">
					<a href="', $context['verification_sound_href'], '" rel="nofollow">', $context['verification_sound_href'], '</a>
				</object>
			</audio>';

	echo '
			<br>
			<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">', $txt['visual_verification_sound_again'], '</a><br>
			<a href="', $context['verification_sound_href'], '" rel="nofollow">', $txt['visual_verification_sound_direct'], '</a><br><br>
			<a href="javascript:self.close();">', $txt['visual_verification_sound_close'], '</a><br>
		</div><!-- .description -->
	</body>
</html>';
}

/**
 * The template for the form allowing an admin to register a user from the admin center.
 */
function template_admin_register()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
		<div id="admin_form_wrapper">
			<form id="postForm" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['admin_browse_register_new'], '</h3>
				</div>
				<div id="register_screen" class="windowbg">';

	if (!empty($context['registration_done']))
		echo '
					<div class="infobox">
						', $context['registration_done'], '
					</div>';

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', $txt['admin_register_username'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_username_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="50" maxlength="25">
						</dd>
						<dt>
							<strong><label for="email_input">', $txt['admin_register_email'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="50">
						</dd>
						<dt>
							<strong><label for="password_input">', $txt['admin_register_password'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_password_desc'], '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', $context['tabindex']++, '" size="50" onchange="onCheckChange();">
						</dd>';

	if (!empty($context['member_groups']))
	{
		echo '
						<dt>
							<strong><label for="group_select">', $txt['admin_register_group'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_group_desc'], '</span>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', $context['tabindex']++, '">';

		foreach ($context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	// If there is any field marked as required, show it here!
	if (!empty($context['custom_fields_required']) && !empty($context['custom_fields']))
		foreach ($context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>
							', str_replace('name="', 'tabindex="' . $context['tabindex']++ . '" name="', $field['input_html']), '
						</dd>';

	echo '
						<dt>
							<strong><label for="emailPassword_check">', $txt['admin_register_email_detail'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_detail_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', $context['tabindex']++, '" checked disabled>
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', $txt['admin_register_email_activate'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="button">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="', $context['admin-regc_token_var'], '" value="', $context['admin-regc_token'], '">
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
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
		<div class="infobox">', $txt['settings_saved'], '</div>';

	elseif (!empty($context['could_not_save']))
		echo '
		<div class="errorbox">', $txt['admin_agreement_not_saved'], '</div>';

	// Warning for if the file isn't writable.
	if (!empty($context['warning']))
		echo '
		<div class="errorbox">', $context['warning'], '</div>';

	// Just a big box to edit the text file ;)
	echo '
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['registration_agreement'], '</h3>
			</div>
			<div class="windowbg" id="registration_agreement">';

	// Is there more than one language to choose from?
	if (count($context['editable_agreements']) > 1)
	{
		echo '
				<div class="cat_bar">
					<h3 class="catbg">', $txt['language_configuration'], '</h3>
				</div>
				<div class="information">
					<form action="', $scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="', $context['character_set'], '" style="display: inline;">
						<strong>', $txt['admin_agreement_select_language'], ':</strong>
						<select name="agree_lang" onchange="document.getElementById(\'change_reg\').submit();" tabindex="', $context['tabindex']++, '">';

		foreach ($context['editable_agreements'] as $file => $name)
			echo '
							<option value="', $file, '"', $context['current_agreement'] == $file ? ' selected' : '', '>', $name, '</option>';

		echo '
						</select>
						<div class="righttext">
							<input type="hidden" name="sa" value="agreement">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '">
							<input type="submit" name="change" value="', $txt['admin_agreement_select_language_change'], '" tabindex="', $context['tabindex']++, '" class="button">
						</div>
					</form>
				</div><!-- .information -->';
	}

	// Show the actual agreement in an oversized text box.
	echo '
				<form action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '">
					<textarea cols="70" rows="20" name="agreement" id="agreement">', $context['agreement'], '</textarea>
					<p>
						<label for="requireAgreement"><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked' : '', ' tabindex="', $context['tabindex']++, '" value="1"> ', $txt['admin_agreement'], '.</label>
					</p>
					<input type="submit" value="', $txt['save'], '" tabindex="', $context['tabindex']++, '" class="button">
					<input type="hidden" name="agree_lang" value="', $context['current_agreement'], '">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '">
				</form>
			</div><!-- #registration_agreement -->
		</div><!-- #admin_form_wrapper -->';
}

/**
 * Template for editing reserved words.
 */
function template_edit_reserved_words()
{
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
	<div class="infobox">', $txt['settings_saved'], '</div>';

	echo '
	<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['admin_reserved_set'], '</h3>
		</div>
		<div class="windowbg">
			<h4>', $txt['admin_reserved_line'], '</h4>
			<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", $context['reserved_words']), '</textarea>
			<dl class="settings">
				<dt>
					<label for="matchword">', $txt['admin_match_whole'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchword" id="matchword" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_word'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchcase">', $txt['admin_match_case'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchcase" id="matchcase" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_case'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchuser">', $txt['admin_check_user'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchuser" id="matchuser" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_user'] ? ' checked' : '', '>
				</dd>
				<dt>
					<label for="matchname">', $txt['admin_check_display'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="matchname" id="matchname" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_name'] ? ' checked' : '', '>
				</dd>
			</dl>
			<div class="flow_auto">
				<input type="submit" value="', $txt['save'], '" name="save_reserved_names" tabindex="', $context['tabindex']++, '" class="button">
				<input type="hidden" name="sa" value="reservednames">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-regr_token_var'], '" value="', $context['admin-regr_token'], '">
			</div>
		</div><!-- .windowbg -->
	</form>';
}

?>