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

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<br />
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<p class="smalltext centertext">', $txt['password_reminder_desc'], '</p>
				<dl>
					<dt>', $txt['user_email'], ':</dt>
					<dd><input type="text" name="user" size="30" class="input_text" /></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['reminder_continue'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

function template_reminder_pick()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<br />
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked="checked" class="input_radio" /></dt>
					<label for="reminder_type_email">', $txt['authentication_' . $context['account_type'] . '_email'], '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret" class="input_radio" />
					<label for="reminder_type_secret">', $txt['authentication_' . $context['account_type'] . '_secret'], '</label>
				</p>
				<p class="centertext"><input type="submit" value="', $txt['reminder_continue'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</div>
		<input type="hidden" name="uid" value="', $context['current_member']['id'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

function template_sent()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<br />
		<div class="tborder login" id="reminder_sent">
			<div class="cat_bar">
				<h3 class="catbg">' . $context['page_title'] . '</h3>
			</div>
			<p class="information">' . $context['description'] . '</p>
		</div>';
}

function template_set_password()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/register.js"></script>
	<br />
	<form action="', $scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<dl>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22" class="input_password" />
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22" class="input_password" />
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
				</dl>
				<p class="align_center"><input type="submit" value="', $txt['save'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '" />
		<input type="hidden" name="u" value="', $context['memID'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>
	<script type="text/javascript"><!-- // --><![CDATA[
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("reminder_form", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
// ]]></script>';
}

function template_ask()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/register.js"></script>
	<br />
	<form action="', $scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<p class="smalltext">', $context['account_type'] == 'password' ? $txt['enter_new_password'] : $txt['openid_secret_reminder'], '</p>
				<dl>
					<dt>', $txt['secret_question'], ':</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt>', $txt['secret_answer'], ':</dt>
					<dd><input type="text" name="secret_answer" size="22" class="input_text" /></dd>';

	if ($context['account_type'] == 'password')
		echo '
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22" class="input_password" />
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22" class="input_password" />
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.gif" alt="*" />
						</span>
					</dd>';

	echo '
				</dl>
				<p class="align_center"><input type="submit" value="', $txt['save'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</div>
		<input type="hidden" name="uid" value="', $context['remind_user'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';

	if ($context['account_type'] == 'password')
		echo '
<script type="text/javascript"><!-- // --><![CDATA[
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
// ]]></script>';

}

?>