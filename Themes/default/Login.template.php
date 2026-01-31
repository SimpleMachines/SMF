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
					<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
				</h3>
			</div>';

	echo '
			<form action="', Utils::$context['login_url'], '" name="frmLogin" method="post" accept-charset="UTF-8" class="form_grid';

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
				<label>', Lang::getTxt('username', file: 'General'), ':</label>
				<div>
					<input type="text" name="user" size="20" value="', Utils::$context['default_username'], '" required>
				</div>
				<label>', Lang::getTxt('password', file: 'General'), ':</label>
				<div>
					<input type="password" name="passwrd" value="', Utils::$context['default_password'], '" size="20" required>
				</div>
				<label>', Lang::getTxt('time_logged_in', file: 'General'), ':</label>
				<label>
					<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
					', Lang::getTxt('remember_me', file: 'General'), '
				</label>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset(Utils::$context['login_show_undelete']))
		echo '
				<div class="checkbox">
					<input type="checkbox" name="undelete">
					<label class="alert">', Lang::getTxt('undelete_account', file: 'Login'), ':</label>
				</div>';

	echo '
				<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
				<p class="smalltext centertext">
					<a href="', Config::$scripturl, '?action=reminder">', Lang::getTxt('forgot_your_password', file: 'General'), '</a>
				</p>';

	if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1)
		echo '
					<p class="smalltext">
						', Lang::getTxt('welcome_guest_activate', ['scripturl' => Config::$scripturl], file: 'General'), '
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
					', Lang::getTxt('register_prompt', ['scripturl' => Config::$scripturl], file: 'General'), '
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
					', Lang::getTxt('tfa_profile_label', file: 'Profile'), '
				</h3>
			</div>';

	echo '
			<div class="windowbg">';

	if (!empty(Utils::$context['tfa_error']) || !empty(Utils::$context['tfa_backup_error']))
		echo '
				<div class="error">
					', Lang::getTxt('tfa_' . (!empty(Utils::$context['tfa_error']) ? 'code_' : 'backup_') . 'invalid', file: 'Profile'), '
				</div>';

	echo '
				<form action="', Utils::$context['tfa_url'], '" method="post" name="frmTfa">
					<div id="tfaCode">
						<p style="margin-bottom: 0.5em">', Lang::getTxt('tfa_login_desc', file: 'Profile'), '</p>
						<div class="centertext">
							<strong>', Lang::getTxt('tfa_code', file: 'Profile'), ':</strong>
							<input type="text" name="tfa_code" value="', !empty(Utils::$context['tfa_value']) ? Utils::$context['tfa_value'] : '', '">
							<input type="submit" class="button" name="submit" value="', Lang::getTxt('login', file: 'General'), '">
						</div>
						<hr>
						<div class="centertext">
							<input type="button" class="button" name="backup" value="', Lang::getTxt('tfa_backup', file: 'Profile'), '">
						</div>
					</div>
					<div id="tfaBackup" style="display: none;">
						<p style="margin-bottom: 0.5em">', Lang::getTxt('tfa_backup_desc', file: 'Profile'), '</p>
						<div class="centertext">
							<strong>', Lang::getTxt('tfa_backup_code', file: 'Profile'), ': </strong>
							<input type="text" name="tfa_backup" value="', !empty(Utils::$context['tfa_backup']) ? Utils::$context['tfa_backup'] : '', '">
							<input type="submit" class="button" name="submit" value="', Lang::getTxt('login', file: 'General'), '">
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
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="UTF-8" class="login">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('warning', file: 'Login'), '</h3>
		</div>';

	// Show the message or default message.
	echo '
		<p class="noticebox">
			', Utils::$context['kick_message'], '
			<br>
			';

	if (Utils::$context['can_register'])
		echo Lang::getTxt('login_below_or_register', ['url' => Config::$scripturl . '?action=signup', 'forum_name' => Utils::$context['forum_name_html_safe']], file: 'Login');
	else
		echo Lang::getTxt('login_below', file: 'Login');

	// And now the login information.
	echo '
		</p>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
			</h3>
		</div>
		<div class="windowbg form_grid">
			<label>', Lang::getTxt('username', file: 'General'), ':</label>
			<div><input type="text" name="user" autofocus size="20"></div>
			<label>', Lang::getTxt('password', file: 'General'), ':</label>
			<div><input type="password" name="passwrd" size="20"></div>
			<label>', Lang::getTxt('time_logged_in', file: 'General'), ':</label>
			<label>
				<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
				', Lang::getTxt('remember_me', file: 'General'), '
			</label>
			<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
			<p class="centertext smalltext">
				<a href="', Config::$scripturl, '?action=reminder">', Lang::getTxt('forgot_your_password', file: 'General'), '</a>
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
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="UTF-8" class="login" id="maintenance_mode">
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['title'], '</h3>
		</div>
		<p class="descbox">
			<img src="', Theme::$current->settings['images_url'], '/construction.png" wilabelh="40" height="40" alt="', Lang::getTxt('in_maintain_mode', file: 'Login'), '">
			', Utils::$context['description'], '<br class="clear">
		</p>
		<div class="title_bar">
			<h4 class="titlebg">', Lang::getTxt('admin_login', file: 'General'), '</h4>
		</div>
		<div class="windowbg form_grid">
			<label>', Lang::getTxt('username', file: 'General'), ':</label>
			<div><input type="text" name="user" autofocus size="20"></div>
			<label>', Lang::getTxt('password', file: 'General'), ':</label>
			<div><input type="password" name="passwrd" size="20"></div>
			<label>', Lang::getTxt('time_logged_in', file: 'Generla'), ':</label>
			<label>
				<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
				', Lang::getTxt('remember_me', file: 'General'), '
			</label>
			<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
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
	<form action="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, array('http://' => 'https://')) : Config::$scripturl, Utils::$context['get_data'], '" method="post" accept-charset="UTF-8">
		<div class="login" id="admin_login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
				</h3>
			</div>
			<div class="windowbg centertext">';

	if (!empty(Utils::$context['incorrect_password']))
		echo '
				<div class="error">', Lang::getTxt('admin_incorrect_password', file: 'Admin'), '</div>';

	echo '
				<strong>', Lang::getTxt('password', file: 'General'), ':</strong>
				<input type="password" name="', Utils::$context['sessionCheckType'], '_pass" autofocus size="24">
				<a href="', Config::$scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a><br>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-login_token_var'], '" value="', Utils::$context['admin-login_token'], '">
				<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">';

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
		<form action="', Config::$scripturl, '?action=activate;u=', Utils::$context['member_id'], '" method="post" accept-charset="UTF-8" class="windowbg form_grid">';

	// You didn't even have an ID?
	if (empty(Utils::$context['member_id']))
		echo '
			<label>', Lang::getTxt('invalid_activation_username', file: 'Login'), ':</label>
			<div><input type="text" name="user" size="30"></div>';

	echo '
			<label>', Lang::getTxt('invalid_activation_retry', file: 'Login'), ':</label>
			<div><input type="text" name="code" size="30"></div>
			<input type="submit" value="', Lang::getTxt('invalid_activation_submit', file: 'Login'), '" class="button">
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
		<form action="', Config::$scripturl, '?action=activate;sa=resend" method="post" accept-charset="UTF-8" class="windowbg form_grid">
			<label>', Lang::getTxt('invalid_activation_username', file: 'Login'), ':</label>
			<div><input type="text" name="user" autofocus size="40" value="', Utils::$context['default_username'], '"></div>
			<p>', Lang::getTxt('invalid_activation_new', file: 'Login'), '</p>
			<label>', Lang::getTxt('invalid_activation_new_email', file: 'Login'), ':</label>
			<div><input type="text" name="new_email" size="40"></div>
			<label>', Lang::getTxt('invalid_activation_password', file: 'Login'), ':</label>
			<div><input type="password" name="passwd" size="30"></div>';

	if (Utils::$context['can_activate'])
		echo '
			<p>', Lang::getTxt('invalid_activation_known', file: 'Login'), '</p>
			<label>', Lang::getTxt('invalid_activation_retry', file: 'Login'), ':</label>
			<div><input type="text" name="code" size="30"></div>';

	echo '
			<input type="submit" value="', Lang::getTxt('invalid_activation_resend', file: 'Login'), '" class="button">
		</form>';
}

/**
 * Confirm a logout.
 */
function template_logout()
{
	echo '
	<form action="', Config::$scripturl . '?action=logout;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('logout_confirm', file: 'Login'), '</h3>
		</div>
		<div class="windowbg">
			<p class="information centertext">
				', Lang::getTxt('logout_notice', file: 'Login'), '
			</p>

			<p class="centertext">
				<input type="submit" value="', Lang::getTxt('logout', file: 'General'), '" class="button">
				<input type="submit" name="cancel" value="', Lang::getTxt('logout_return', file: 'Login'), '" class="button">
			</p>
		</div>
	</form>';
}
