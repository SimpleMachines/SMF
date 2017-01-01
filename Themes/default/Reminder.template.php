<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * The main "Here's how you can reset your password" page
 */
function template_main()
{
	global $context, $txt, $scripturl;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext centertext">', $txt['password_reminder_desc'], '</p>
				<dl>
					<dt>', $txt['user_email'], ':</dt>
					<dd><input type="text" name="user" size="30" class="input_text"></dd>
				</dl>
				<input type="submit" value="', $txt['reminder_continue'], '" class="button_submit">
				<br class="clear">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="', $context['remind_token_var'], '" value="', $context['remind_token'], '">
	</form>';
}

/**
 * The page to pick an option - secret question/answer (if set) or email
 */
function template_reminder_pick()
{
	global $context, $txt, $scripturl;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked class="input_radio"></dt>
					<label for="reminder_type_email">', $txt['authentication_password_email'], '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret" class="input_radio">
					<label for="reminder_type_secret">', $txt['authentication_password_secret'], '</label>
				</p>
				<div class="flow_auto">
					<input type="submit" value="', $txt['reminder_continue'], '" class="button_submit">
					<input type="hidden" name="uid" value="', $context['current_member']['id'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['remind_token_var'], '" value="', $context['remind_token'], '">
				</div>
			</div>
		</div>
	</form>';
}

/**
 * Just a simple "We sent you an email. Click the link in it to continue." message
 */
function template_sent()
{
	global $context;

	echo '
		<br>
		<div class="tborder login" id="reminder_sent">
			<div class="cat_bar">
				<h3 class="catbg">' . $context['page_title'] . '</h3>
			</div>
			<p class="information">' . $context['description'] . '</p>
		</div>';
}

/**
 * Template for setting the new password
 */
function template_set_password()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="', $context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22" class="input_password">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="generic_icons invalid"></span>
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22" class="input_password">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="generic_icons invalid"></span>
						</span>
					</dd>
				</dl>
				<p class="align_center"><input type="submit" value="', $txt['save'], '" class="button_submit"></p>
			</div>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '">
		<input type="hidden" name="u" value="', $context['memID'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="', $context['remind-sp_token_var'], '" value="', $context['remind-sp_token'], '">
	</form>
	<script>
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("reminder_form", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
</script>';
}

/**
 * The page that asks a user to answer their secret question
 */
function template_ask()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext">', $txt['enter_new_password'], '</p>
				<dl>
					<dt>', $txt['secret_question'], ':</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt>', $txt['secret_answer'], ':</dt>
					<dd><input type="text" name="secret_answer" size="22" class="input_text"></dd>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22" class="input_password">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="generic_icons invalid"></span>
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22" class="input_password">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="generic_icons valid"></span>
						</span>
					</dd>
				</dl>
				<div class="auto_flow">
					<input type="submit" value="', $txt['save'], '" class="button_submit">
					<input type="hidden" name="uid" value="', $context['remind_user'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['remind-sai_token_var'], '" value="', $context['remind-sai_token'], '">
				</div>
			</div>
		</div>
	</form>
<script>
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
</script>';

}

?>