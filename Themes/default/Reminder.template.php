<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * The main "Here's how you can reset your password" page
 */
function template_main()
{
	echo '
	<br>
	<form action="', Config::$scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_reminder', file: 'Profile'), '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext centertext">', Lang::getTxt('password_reminder_desc', file: 'Profile'), '</p>
				<dl>
					<dt>', Lang::getTxt('user_email', file: 'Profile'), '</dt>
					<dd><input type="text" name="user" size="30"></dd>
				</dl>
				<input type="submit" value="', Lang::getTxt('reminder_continue', file: 'Profile'), '" class="button">
				<br class="clear">
			</div>
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="', Utils::$context['remind_token_var'], '" value="', Utils::$context['remind_token'], '">
	</form>';
}

/**
 * The page to pick an option - secret question/answer (if set) or email
 */
function template_reminder_pick()
{
	echo '
	<br>
	<form action="', Config::$scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_reminder', file: 'Profile'), '</h3>
			</div>
			<div class="roundframe">
				<p><strong>', Lang::getTxt('authentication_options', file: 'Profile'), '</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked></dt>
					<label for="reminder_type_email">', Lang::getTxt('authentication_password_email', file: 'Profile'), '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret">
					<label for="reminder_type_secret">', Lang::getTxt('authentication_password_secret', file: 'Profile'), '</label>
				</p>
				<div class="flow_auto">
					<input type="submit" value="', Lang::getTxt('reminder_continue', file: 'Profile'), '" class="button">
					<input type="hidden" name="uid" value="', Utils::$context['current_member']['id'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['remind_token_var'], '" value="', Utils::$context['remind_token'], '">
				</div>
			</div><!-- .roundframe -->
		</div><!-- .login -->
	</form>';
}

/**
 * Just a simple "We sent you an email. Click the link in it to continue." message
 */
function template_sent()
{
	echo '
		<br>
		<div class="tborder login" id="reminder_sent">
			<div class="cat_bar">
				<h3 class="catbg">' . Utils::$context['page_title'] . '</h3>
			</div>
			<p class="information">' . Utils::$context['description'] . '</p>
		</div>';
}

/**
 * Template for setting the new password
 */
function template_set_password()
{
	echo '
	<br>
	<form action="', Config::$scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', Lang::getTxt('choose_pass', file: 'General'), '</dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
						</span>
					</dd>
					<dt>', Lang::getTxt('verify_pass', file: 'General'), '</dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="main_icons invalid"></span>
						</span>
					</dd>
				</dl>
				<p class="align_center">
					<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" class="button">
				</p>
			</div><!-- .roundframe -->
		</div><!-- .login -->
		<input type="hidden" name="code" value="', Utils::$context['code'], '">
		<input type="hidden" name="u" value="', Utils::$context['memID'], '">
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="', Utils::$context['remind-sp_token_var'], '" value="', Utils::$context['remind-sp_token'], '">
	</form>
	<script>
		var regTextStrings = {
			"password_short": "', Lang::getTxt('registration_password_short', file: 'Login'), '",
			"password_reserved": "', Lang::getTxt('registration_password_reserved', file: 'Login'), '",
			"password_numbercase": "', Lang::getTxt('registration_password_numbercase', file: 'Login'), '",
			"password_no_match": "', Lang::getTxt('registration_password_no_match', file: 'Login'), '",
			"password_valid": "', Lang::getTxt('registration_password_valid', file: 'Login'), '"
		};
		var verificationHandle = new smfRegister("reminder_form", ', empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'], ', regTextStrings);
	</script>';
}

/**
 * The page that asks a user to answer their secret question
 */
function template_ask()
{
	echo '
	<br>
	<form action="', Config::$scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_reminder', file: 'Profile'), '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext">', Lang::getTxt('enter_new_password', file: 'Profile'), '</p>
				<dl>
					<dt>', Lang::getTxt('secret_question', file: 'Profile'), '</dt>
					<dd>', Utils::$context['secret_question'], '</dd>
					<dt>', Lang::getTxt('secret_answer', file: 'Profile'), '</dt>
					<dd><input type="text" name="secret_answer" size="22"></dd>
					<dt>', Lang::getTxt('choose_pass', file: 'General'), '</dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
						</span>
					</dd>
					<dt>', Lang::getTxt('verify_pass', file: 'General'), '</dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="main_icons valid"></span>
						</span>
					</dd>
				</dl>
				<div class="auto_flow">
					<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" class="button">
					<input type="hidden" name="uid" value="', Utils::$context['remind_user'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['remind-sai_token_var'], '" value="', Utils::$context['remind-sai_token'], '">
				</div>
			</div><!-- .roundframe -->
		</div><!-- .login -->
	</form>
	<script>
		var regTextStrings = {
			"password_short": "', Lang::getTxt('registration_password_short', file: 'Login'), '",
			"password_reserved": "', Lang::getTxt('registration_password_reserved', file: 'Login'), '",
			"password_numbercase": "', Lang::getTxt('registration_password_numbercase', file: 'Login'), '",
			"password_no_match": "', Lang::getTxt('registration_password_no_match', file: 'Login'), '",
			"password_valid": "', Lang::getTxt('registration_password_valid', file: 'Login'), '"
		};
		var verificationHandle = new smfRegister("creator", ', empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'], ', regTextStrings);
	</script>';

}
