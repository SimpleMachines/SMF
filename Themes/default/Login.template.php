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
use SMF\Theme;
use SMF\Utils;

/**
 * This is just the basic "login" form.
 */
function template_login()
{
	if (empty(Utils::$context['from_ajax']))
		echo '
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::$txt['login'], '
				</h3>
			</div>';

	echo '
			<form action="', Utils::$context['login_url'], '" name="frmLogin" method="post" accept-charset="', Utils::$context['character_set'], '" class="form_grid';

	if (empty(Utils::$context['from_ajax']))
		echo ' windowbg';

	echo '">';

	// Did they make a mistake last time?
	if (!empty(Utils::$context['login_errors']))
		echo '
				<p class="errorbox">', implode('<br>', Utils::$context['login_errors']), '</p>';

	// Or perhaps there's some special description for this time?
	if (isset(Utils::$context['description']))
		echo '
				<p class="descbox">', Utils::$context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
				<label>', Lang::$txt['username'], ':</label>
				<div>
					<input type="text" name="user" size="20" value="', Utils::$context['default_username'], '" required>
				</div>
				<label>', Lang::$txt['password'], ':</label>
				<div>
					<input type="password" name="passwrd" value="', Utils::$context['default_password'], '" size="20" required>
				</div>
				<label>', Lang::$txt['time_logged_in'], ':</label>
				<div>
					<select name="cookielength" id="cookielength">';

	foreach (Utils::$context['login_cookie_times'] as $cookie_time => $cookie_txt)
		echo '
						<option value="', $cookie_time, '"', Config::$modSettings['cookieTime'] == $cookie_time ? ' selected' : '', '>', Lang::$txt[$cookie_txt], '</option>';

	echo '
					</select>
				</div>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset(Utils::$context['login_show_undelete']))
		echo '
				<div class="checkbox">
					<input type="checkbox" name="undelete">
					<label class="alert">', Lang::$txt['undelete_account'], ':</label>
				</div>';

	echo '
				<input type="submit" value="', Lang::$txt['login'], '" class="button">
				<p class="smalltext centertext">
					<a href="', Config::$scripturl, '?action=reminder">', Lang::$txt['forgot_your_password'], '</a>
				</p>';

	if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1)
		echo '
					<p class="smalltext">
						', Lang::getTxt('welcome_guest_activate', ['scripturl' => Config::$scripturl]), '
					</p>';

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
				<script>
					var oLogin = new smf_Login({
						oForm: document.forms.frmLogin,
						bIsFromAjax: ', !empty(Utils::$context['from_ajax']) , ',
						sCors: ', Utils::$context['valid_cors_found'] ?? '', '
					});
					setTimeout(function() {
						document.forms.frmLogin.elements.', Utils::$context['default_username'] != '' ? 'passwrd' : 'user', '.focus();
					}, 150);';

	if (!empty(Utils::$context['from_ajax']) && (empty(Config::$modSettings['allow_cors']) || empty(Config::$modSettings['allow_cors_credentials']) || empty(Utils::$context['valid_cors_found']) || !in_array(Utils::$context['valid_cors_found'], array('same', 'subsite'))))
		echo '
					oLogin.login()';

	echo '
				</script>';

	if (!empty(Utils::$context['can_register']))
		echo '
				<hr>
				<p class="centertext">
					', sprintf(Lang::$txt['register_prompt'], Config::$scripturl), '
				</p>';

	echo '
			</form>';

	if (empty(Utils::$context['from_ajax']))
		echo '
		</div><!-- .login -->';
}

/**
 * TFA authentication form
 */
function template_login_tfa()
{
	if (empty(Utils::$context['from_ajax']))
		echo '
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['tfa_profile_label'], '
				</h3>
			</div>';

	echo '
			<div class="windowbg">';

	if (!empty(Utils::$context['tfa_error']) || !empty(Utils::$context['tfa_backup_error']))
		echo '
				<div class="error">
					', Lang::$txt['tfa_' . (!empty(Utils::$context['tfa_error']) ? 'code_' : 'backup_') . 'invalid'], '
				</div>';

	echo '
				<form action="', Utils::$context['tfa_url'], '" method="post" name="frmTfa">
					<div id="tfaCode">
						<p style="margin-bottom: 0.5em">', Lang::$txt['tfa_login_desc'], '</p>
						<div class="centertext">
							<strong>', Lang::$txt['tfa_code'], ':</strong>
							<input type="text" name="tfa_code" value="', !empty(Utils::$context['tfa_value']) ? Utils::$context['tfa_value'] : '', '">
							<input type="submit" class="button" name="submit" value="', Lang::$txt['login'], '">
						</div>
						<hr>
						<div class="centertext">
							<input type="button" class="button" name="backup" value="', Lang::$txt['tfa_backup'], '">
						</div>
					</div>
					<div id="tfaBackup" style="display: none;">
						<p style="margin-bottom: 0.5em">', Lang::$txt['tfa_backup_desc'], '</p>
						<div class="centertext">
							<strong>', Lang::$txt['tfa_backup_code'], ': </strong>
							<input type="text" name="tfa_backup" value="', !empty(Utils::$context['tfa_backup']) ? Utils::$context['tfa_backup'] : '', '">
							<input type="submit" class="button" name="submit" value="', Lang::$txt['login'], '">
						</div>
					</div>
				</form>
				<script>
					var form = document.forms.frmTfa;';

	if (!empty(Utils::$context['from_ajax']))
		echo '
					form.addEventListener("submit", e => {
						// If we are submitting backup code, let normal workflow follow since it redirects a couple times into a different page
						if (form.elements.tfa_backup.value != "")
							return true;

						e.preventDefault();
						e.stopPropagation();

						$.post(form.action, $(form).serialize(), function(data) {
							if (data.indexOf("<bo" + "dy") > -1)
								document.location = ', Utils::JavaScriptEscape(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : Config::$scripturl), ';
							else {
								$(form).parent().html($(data).find(".windowbg").html());
							}
						});
					});';

	echo '
					form.elements.backup.addEventListener("click", () => {
						form.getElementById("tfaBackup").style.display = "none";
						form.getElementById("tfaCode").style.display = "";
					});
				</script>
			</div><!-- .windowbg -->';

	if (empty(Utils::$context['from_ajax']))
		echo '
		</div><!-- .login -->';
}

/**
 * Tell a guest to get lost or login!
 */
function template_kick_guest()
{
	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="login">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['warning'], '</h3>
		</div>';

	// Show the message or default message.
	echo '
		<p class="noticebox">
			', Utils::$context['kick_message'], '
			<br>
			';

	if (Utils::$context['can_register'])
		echo sprintf(Lang::$txt['login_below_or_register'], Config::$scripturl . '?action=signup', Utils::$context['forum_name_html_safe']);
	else
		echo Lang::$txt['login_below'];

	// And now the login information.
	echo '
		</p>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons login"></span> ', Lang::$txt['login'], '
			</h3>
		</div>
		<div class="windowbg form_grid">
			<label>', Lang::$txt['username'], ':</label>
			<div><input type="text" name="user" autofocus size="20"></div>
			<label>', Lang::$txt['password'], ':</label>
			<div><input type="password" name="passwrd" size="20"></div>
			<label>', Lang::$txt['time_logged_in'], ':</label>
			<div>
				<select name="cookielength" id="cookielength">';

	foreach (Utils::$context['login_cookie_times'] as $cookie_time => $cookie_txt)
		echo '
					<option value="', $cookie_time, '"', Config::$modSettings['cookieTime'] == $cookie_time ? ' selected' : '', '>', Lang::$txt[$cookie_txt], '</option>';

	echo '
				</select>
			</div>
			<input type="submit" value="', Lang::$txt['login'], '" class="button">
			<p class="centertext smalltext">
				<a href="', Config::$scripturl, '?action=reminder">', Lang::$txt['forgot_your_password'], '</a>
			</p>
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
	</form>';
}

/**
 * This is for maintenance mode.
 */
function template_maintenance()
{
	// Display the administrator's message at the top.
	echo '
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="login" id="maintenance_mode">
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['title'], '</h3>
		</div>
		<p class="descbox">
			<img src="', Theme::$current->settings['images_url'], '/construction.png" wilabelh="40" height="40" alt="', Lang::$txt['in_maintain_mode'], '">
			', Utils::$context['description'], '<br class="clear">
		</p>
		<div class="title_bar">
			<h4 class="titlebg">', Lang::$txt['admin_login'], '</h4>
		</div>
		<div class="windowbg form_grid">
			<label>', Lang::$txt['username'], ':</label>
			<div><input type="text" name="user" autofocus size="20"></div>
			<label>', Lang::$txt['password'], ':</label>
			<div><input type="password" name="passwrd" size="20"></div>
			<label>', Lang::$txt['time_logged_in'], ':</label>
			<div>
				<select name="cookielength" id="cookielength">';

	foreach (Utils::$context['login_cookie_times'] as $cookie_time => $cookie_txt)
		echo '
					<option value="', $cookie_time, '"', Config::$modSettings['cookieTime'] == $cookie_time ? ' selected' : '', '>', Lang::$txt[$cookie_txt], '</option>';

	echo '
				</select>
			</div>
			<input type="submit" value="', Lang::$txt['login'], '" class="button">
		</div>
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
	</form>';
}

/**
 * This is for the security stuff - makes administrators login every so often.
 */
function template_admin_login()
{
	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
	<form action="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, array('http://' => 'https://')) : Config::$scripturl, Utils::$context['get_data'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="login" id="admin_login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::$txt['login'], '
				</h3>
			</div>
			<div class="windowbg centertext">';

	if (!empty(Utils::$context['incorrect_password']))
		echo '
				<div class="error">', Lang::$txt['admin_incorrect_password'], '</div>';

	echo '
				<strong>', Lang::$txt['password'], ':</strong>
				<input type="password" name="', Utils::$context['sessionCheckType'], '_pass" autofocus size="24">
				<a href="', Config::$scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a><br>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-login_token_var'], '" value="', Utils::$context['admin-login_token'], '">
				<input type="submit" value="', Lang::$txt['login'], '" class="button">';

	// Make sure to output all the old post data.
	echo Utils::$context['post_data'], '
			</div><!-- .windowbg -->
		</div><!-- #admin_login -->
	</form>';
}

/**
 * Activate your account manually?
 */
function template_retry_activate()
{
	// Just ask them for their code so they can try it again...
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<form action="', Config::$scripturl, '?action=activate;u=', Utils::$context['member_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="windowbg form_grid">';

	// You didn't even have an ID?
	if (empty(Utils::$context['member_id']))
		echo '
			<label>', Lang::$txt['invalid_activation_username'], ':</label>
			<div><input type="text" name="user" size="30"></div>';

	echo '
			<label>', Lang::$txt['invalid_activation_retry'], ':</label>
			<div><input type="text" name="code" size="30"></div>
			<input type="submit" value="', Lang::$txt['invalid_activation_submit'], '" class="button">
		</form>';
}

/**
 * The form for resending the activation code.
 */
function template_resend()
{
	// Just ask them for their code so they can try it again...
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<form action="', Config::$scripturl, '?action=activate;sa=resend" method="post" accept-charset="', Utils::$context['character_set'], '" class="windowbg form_grid">
			<label>', Lang::$txt['invalid_activation_username'], ':</label>
			<div><input type="text" name="user" autofocus size="40" value="', Utils::$context['default_username'], '"></div>
			<p>', Lang::$txt['invalid_activation_new'], '</p>
			<label>', Lang::$txt['invalid_activation_new_email'], ':</label>
			<div><input type="text" name="new_email" size="40"></div>
			<label>', Lang::$txt['invalid_activation_password'], ':</label>
			<div><input type="password" name="passwd" size="30"></div>';

	if (Utils::$context['can_activate'])
		echo '
			<p>', Lang::$txt['invalid_activation_known'], '</p>
			<label>', Lang::$txt['invalid_activation_retry'], ':</label>
			<div><input type="text" name="code" size="30"></div>';

	echo '
			<input type="submit" value="', Lang::$txt['invalid_activation_resend'], '" class="button">
		</form>';
}

/**
 * Confirm a logout.
 */
function template_logout()
{
	echo '
	<form action="', Config::$scripturl . '?action=logout;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['logout_confirm'], '</h3>
		</div>
		<div class="windowbg">
			<p class="information centertext">
				', Lang::$txt['logout_notice'], '
			</p>

			<p class="centertext">
				<input type="submit" value="', Lang::$txt['logout'], '" class="button">
				<input type="submit" name="cancel" value="', Lang::$txt['logout_return'], '" class="button">
			</p>
		</div>
	</form>';
}

?>