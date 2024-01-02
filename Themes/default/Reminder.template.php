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
 * The main "Here's how you can reset your password" page
 */
function template_main()
{
	echo '
	<br>
	<form action="', Config::$scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext centertext">', Lang::$txt['password_reminder_desc'], '</p>
				<dl>
					<dt>', Lang::$txt['user_email'], ':</dt>
					<dd><input type="text" name="user" size="30"></dd>
				</dl>
				<input type="submit" value="', Lang::$txt['reminder_continue'], '" class="button">
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
	<form action="', Config::$scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p><strong>', Lang::$txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked></dt>
					<label for="reminder_type_email">', Lang::$txt['authentication_password_email'], '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret">
					<label for="reminder_type_secret">', Lang::$txt['authentication_password_secret'], '</label>
				</p>
				<div class="flow_auto">
					<input type="submit" value="', Lang::$txt['reminder_continue'], '" class="button">
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
	<form action="', Config::$scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', Lang::$txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
						</span>
					</dd>
					<dt>', Lang::$txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="main_icons invalid"></span>
						</span>
					</dd>
				</dl>
				<p class="align_center">
					<input type="submit" value="', Lang::$txt['save'], '" class="button">
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
			"password_short": "', Lang::$txt['registration_password_short'], '",
			"password_reserved": "', Lang::$txt['registration_password_reserved'], '",
			"password_numbercase": "', Lang::$txt['registration_password_numbercase'], '",
			"password_no_match": "', Lang::$txt['registration_password_no_match'], '",
			"password_valid": "', Lang::$txt['registration_password_valid'], '"
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
	<form action="', Config::$scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="', Utils::$context['character_set'], '" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext">', Lang::$txt['enter_new_password'], '</p>
				<dl>
					<dt>', Lang::$txt['secret_question'], ':</dt>
					<dd>', Utils::$context['secret_question'], '</dd>
					<dt>', Lang::$txt['secret_answer'], ':</dt>
					<dd><input type="text" name="secret_answer" size="22"></dd>
					<dt>', Lang::$txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<span id="smf_autov_pwmain_img" class="main_icons invalid"></span>
						</span>
					</dd>
					<dt>', Lang::$txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<span id="smf_autov_pwverify_img" class="main_icons valid"></span>
						</span>
					</dd>
				</dl>
				<div class="auto_flow">
					<input type="submit" value="', Lang::$txt['save'], '" class="button">
					<input type="hidden" name="uid" value="', Utils::$context['remind_user'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['remind-sai_token_var'], '" value="', Utils::$context['remind-sai_token'], '">
				</div>
			</div><!-- .roundframe -->
		</div><!-- .login -->
	</form>
	<script>
		var regTextStrings = {
			"password_short": "', Lang::$txt['registration_password_short'], '",
			"password_reserved": "', Lang::$txt['registration_password_reserved'], '",
			"password_numbercase": "', Lang::$txt['registration_password_numbercase'], '",
			"password_no_match": "', Lang::$txt['registration_password_no_match'], '",
			"password_valid": "', Lang::$txt['registration_password_valid'], '"
		};
		var verificationHandle = new smfRegister("creator", ', empty(Config::$modSettings['password_strength']) ? 0 : Config::$modSettings['password_strength'], ', regTextStrings);
	</script>';

}

?>