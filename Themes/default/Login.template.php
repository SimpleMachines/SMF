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
	echo '
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
				</h3>
			</div>
			<div class="roundframe">
				<form class="login" action="', Utils::$context['login_url'], '" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8"';

	// A plain submit is enough when the response is going to come back to the
	// page it was sent from. Anywhere else, login.js has to take the form over,
	// and this attribute is how it knows to.
	if (!empty(Utils::$context['from_ajax']) && (empty(Config::$modSettings['allow_cors']) || empty(Config::$modSettings['allow_cors_credentials']) || empty(Utils::$context['valid_cors_found']) || !in_array(Utils::$context['valid_cors_found'], ['same', 'subdomain']))) {
		echo ' data-ajax-login="', Utils::$context['valid_cors_found'] ?? '', '"';
	}

	echo '>';

	// Did they make a mistake last time?
	if (!empty(Utils::$context['login_errors'])) {
		echo '
					<div class="errorbox">', implode('<br>', Utils::$context['login_errors']), '</div>
					<br>';
	}

	// Or perhaps there's some special description for this time?
	if (isset(Utils::$context['description'])) {
		echo '
					<div class="information">', Utils::$context['description'], '</div>';
	}

	// Now just get the basic information - username, password, etc.
	echo '
					<dl>
						<dt>', Lang::getTxt('username', file: 'General'), '</dt>
						<dd>
							<input type="text" id="', !empty(Utils::$context['from_ajax']) ? 'ajax_' : '', 'loginuser" name="user" size="20" value="', Utils::$context['default_username'], '" required>
						</dd>
						<dt>', Lang::getTxt('password', file: 'General'), '</dt>
						<dd>
							<input type="password" id="', !empty(Utils::$context['from_ajax']) ? 'ajax_' : '', 'loginpass" name="passwrd" value="', Utils::$context['default_password'], '" size="20" required>
						</dd>
					</dl>
					<dl>
						<dt></dt>
						<dd>
							<label>
								<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
								', Lang::getTxt('remember_me', file: 'General'), '
							</label>
						</dd>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset(Utils::$context['login_show_undelete'])) {
		echo '
						<dt class="alert">', Lang::getTxt('undelete_account', file: 'Login'), '</dt>
						<dd><input type="checkbox" name="undelete"></dd>';
	}

	echo '
					</dl>
					<p>
						<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
					</p>
					<p class="smalltext">
						<a href="', Config::$scripturl, '?action=reminder">', Lang::getTxt('forgot_your_password', file: 'General'), '</a>
					</p>';

	if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1) {
		echo '
					<p class="smalltext">
						', Lang::getTxt('welcome_guest_activate', ['scripturl' => Config::$scripturl], file: 'General'), '
					</p>';
	}
	echo '
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
					<script>
						setTimeout(function() {
							document.getElementById("', !empty(Utils::$context['from_ajax']) ? 'ajax_' : '', isset(Utils::$context['default_username']) && Utils::$context['default_username'] != '' ? 'loginpass' : 'loginuser', '").focus();
						}, 150);';

	echo '
					</script>
				</form>';

	// Anything else offering to sign them in? Nothing does out of the box.
	if (!empty(Utils::$context['authentication_methods'])) {
		echo '
				<hr>
				<div class="centertext login_alternatives">
					<p class="smalltext">', Lang::getTxt('login_alternatives', file: 'Login'), '</p>';

		foreach (Utils::$context['authentication_methods'] as $method) {
			echo '
					<a class="button', isset($method['id']) ? ' login_with_' . $method['id'] : '', '" href="', $method['url'], '">', $method['title'], '</a>';
		}

		echo '
				</div><!-- .login_alternatives -->';
	}

	if (!empty(Utils::$context['can_register'])) {
		echo '
				<hr>
				<div class="centertext">
					', Lang::getTxt('register_prompt', ['scripturl' => Config::$scripturl], file: 'General'), '
				</div>';
	}

	// It is a long story as to why we have this when we're clearly not going to use it.
	if (!empty(Utils::$context['from_ajax'])) {
		echo '
				<br>
				<a href="javascript:self.close();"></a>';
	}

	echo '
			</div><!-- .roundframe -->
		</div><!-- .login -->';
}

/**
 * TFA authentication form
 */
function template_login_tfa()
{
	echo '
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('tfa_profile_label', file: 'Profile'), '
				</h3>
			</div>
			<div class="roundframe">';

	if (!empty(Utils::$context['tfa_error']) || !empty(Utils::$context['tfa_backup_error'])) {
		echo '
				<div class="error">
					', Lang::getTxt('tfa_' . (!empty(Utils::$context['tfa_error']) ? 'code_' : 'backup_') . 'invalid', file: 'Profile'), '
				</div>';
	}

	echo '
				<form action="', Utils::$context['tfa_url'], '" method="post" id="frmTfa">
					<div id="tfaCode">
						<p style="margin-bottom: 0.5em">', Lang::getTxt('tfa_login_desc', file: 'Profile'), '</p>
						<div class="centertext">
							<strong>', Lang::getTxt('tfa_code', file: 'Profile'), '</strong>
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
							<strong>', Lang::getTxt('tfa_backup_code', file: 'Profile'), '</strong>
							<input type="text" name="tfa_backup" value="', !empty(Utils::$context['tfa_backup']) ? Utils::$context['tfa_backup'] : '', '">
							<input type="submit" class="button" name="submit" value="', Lang::getTxt('login', file: 'General'), '">
						</div>
					</div>
				</form>
				<script>
					form = $("#frmTfa");';

	if (!empty(Utils::$context['from_ajax'])) {
		echo '
					form.submit(function(e) {
						// If we are submitting backup code, let normal workflow follow since it redirects a couple times into a different page
						if (form.find("input[name=tfa_backup]:first").val().length > 0)
							return true;

						e.preventDefault();
						e.stopPropagation();

						$.ajax({
							url: form.prop("action") + (form.prop("action").indexOf("?") !== -1 ? ";" : "?") + "ajax",
							method: "POST",
							headers: {
								"X-SMF-AJAX": 1
							},
							xhrFields: {
								withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
							},
							data: form.serialize(),
							success: function(data) {
								if (data.indexOf("<bo" + "dy") > -1) {';

		if (empty(Utils::$context['valid_cors_found']) || Utils::$context['valid_cors_found'] == 'same') {
			echo '
									document.location = ', Utils::escapeJavaScript(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : Config::$scripturl), ';';
		} else {
			echo '
									window.location.reload();';
		}

		echo '
								}
								else {
									window.location.reload();
								}
							},
							error: function(xhr) {
								var data = xhr.responseText;
								if (data.indexOf("<bo" + "dy") > -1) {
									document.open();
									document.write(data);
									document.close();
								}
								else
									form.parent().html($(data).filter("#fatal_error").html());
							}
						});

						return false;
					});';
	}

	echo '
					form.find("input[name=backup]").click(function(e) {
						$("#tfaBackup").show();
						$("#tfaCode").hide();
					});
				</script>
			</div><!-- .roundframe -->
		</div><!-- .login -->';
}

/**
 * Tell a guest to get lost or login!
 */
function template_kick_guest()
{
	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin">
		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('warning', file: 'Login'), '</h3>
			</div>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty(Utils::$context['kick_message']) ? Lang::getTxt('only_members_can_access', file: 'Login') : Utils::$context['kick_message'], '<br>';

	if (Utils::$context['can_register']) {
		echo Lang::getTxt('login_below_or_register', ['url' => Config::$scripturl . '?action=signup', 'forum_name' => Utils::$context['forum_name_html_safe']], file: 'Login');
	} else {
	echo Lang::getTxt('login_below', file: 'Login');
	}

	// And now the login information.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
				</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', Lang::getTxt('username', file: 'General'), '</dt>
					<dd><input type="text" name="user" size="20"></dd>
					<dt>', Lang::getTxt('password', file: 'General'), '</dt>
					<dd><input type="password" name="passwrd" size="20"></dd>
					<dt></dt>
					<dd>
						<label>
							<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
							', Lang::getTxt('remember_me', file: 'General'), '
						</label>
					</dd>
				</dl>
				<p class="centertext">
					<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
				</p>
				<p class="centertext smalltext">
					<a href="', Config::$scripturl, '?action=reminder">', Lang::getTxt('forgot_your_password', file: 'General'), '</a>
				</p>
			</div>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
		</div><!-- .login -->
	</form>';

	// Do the focus thing...
	echo '
	<script>
		document.forms.frmLogin.user.focus();
	</script>';
}

/**
 * This is for maintenance mode.
 */
function template_maintenance()
{
	// Display the administrator's message at the top.
	echo '
	<form action="', Utils::$context['login_url'], '" method="post" accept-charset="UTF-8">
		<div class="login" id="maintenance_mode">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['title'], '</h3>
			</div>
			<div class="information">
				<img class="floatleft" src="', Theme::$current->settings['images_url'], '/construction.png" width="40" height="40" alt="', Lang::getTxt('in_maintain_mode', file: 'Login'), '">
				', Utils::$context['description'], '<br class="clear">
			</div>
			<div class="title_bar">
				<h4 class="titlebg">', Lang::getTxt('admin_login', file: 'General'), '</h4>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', Lang::getTxt('username', file: 'General'), '</dt>
					<dd><input type="text" name="user" size="20"></dd>
					<dt>', Lang::getTxt('password', file: 'General'), '</dt>
					<dd><input type="password" name="passwrd" size="20"></dd>
					<dt></dt>
					<dd>
						<label>
							<input type="checkbox" name="cookieneverexp"', !empty(Utils::$context['never_expire']) ? ' checked' : '', '>
							', Lang::getTxt('remember_me', file: 'General'), '
						</label>
					</dd>
				</dl>
				<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">
				<br class="clear">
			</div>
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
		</div><!-- #maintenance_mode -->
	</form>';
}

/**
 * This is for the security stuff - makes administrators login every so often.
 */
function template_admin_login()
{
	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
	<form action="', !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl, Utils::$context['get_data'], '" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin">
		<div class="login" id="admin_login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons login"></span> ', Lang::getTxt('login', file: 'General'), '
				</h3>
			</div>
			<div class="roundframe centertext">';

	if (!empty(Utils::$context['incorrect_password'])) {
		echo '
				<div class="error">', Lang::getTxt('admin_incorrect_password', file: 'Admin'), '</div>';
	}

	echo '
				<strong>', Lang::getTxt('password', file: 'General'), '</strong>
				<input type="password" name="', Utils::$context['sessionCheckType'], '_pass" size="24">
				<a href="', Config::$scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a><br>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-login_token_var'], '" value="', Utils::$context['admin-login_token'], '">
				<input type="submit" value="', Lang::getTxt('login', file: 'General'), '" class="button">';

	// Make sure to output all the old post data.
	echo Utils::$context['post_data'], '
			</div><!-- .roundframe -->
		</div><!-- #admin_login -->
		<input type="hidden" name="', Utils::$context['sessionCheckType'], '_hash_pass" value="">
	</form>';

	// Focus on the password box.
	echo '
	<script>
		document.forms.frmLogin.', Utils::$context['sessionCheckType'], '_pass.focus();
	</script>';
}

/**
 * Activate your account manually?
 */
function template_retry_activate()
{
	// Just ask them for their code so they can try it again...
	echo '
		<form action="', Config::$scripturl, '?action=activate;u=', Utils::$context['member_id'], '" method="post" accept-charset="UTF-8">
			<div class="title_bar">
				<h3 class="titlebg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>';

	// You didn't even have an ID?
	if (empty(Utils::$context['member_id'])) {
		echo '
					<dt>', Lang::getTxt('invalid_activation_username', file: 'Login'), '</dt>
					<dd><input type="text" name="user" size="30"></dd>';
	}

	echo '
					<dt>', Lang::getTxt('invalid_activation_retry', file: 'Login'), '</dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>
				<p><input type="submit" value="', Lang::getTxt('invalid_activation_submit', file: 'Login'), '" class="button"></p>
			</div>
		</form>';
}

/**
 * The form for resending the activation code.
 */
function template_resend()
{
	// Just ask them for their code so they can try it again...
	echo '
		<form action="', Config::$scripturl, '?action=activate;sa=resend" method="post" accept-charset="UTF-8">
			<div class="title_bar">
				<h3 class="titlebg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', Lang::getTxt('invalid_activation_username', file: 'Login'), '</dt>
					<dd><input type="text" name="user" size="40" value="', Utils::$context['default_username'], '"></dd>
				</dl>
				<p>', Lang::getTxt('invalid_activation_new', file: 'Login'), '</p>
				<dl>
					<dt>', Lang::getTxt('invalid_activation_new_email', file: 'Login'), '</dt>
					<dd><input type="text" name="new_email" size="40"></dd>
					<dt>', Lang::getTxt('invalid_activation_password', file: 'Login'), '</dt>
					<dd><input type="password" name="passwd" size="30"></dd>
				</dl>';

	if (Utils::$context['can_activate']) {
		echo '
				<p>', Lang::getTxt('invalid_activation_known', file: 'Login'), '</p>
				<dl>
					<dt>', Lang::getTxt('invalid_activation_retry', file: 'Login'), '</dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>';
	}

	echo '
				<p><input type="submit" value="', Lang::getTxt('invalid_activation_resend', file: 'Login'), '" class="button"></p>
			</div><!-- .roundframe -->
		</form>';
}

/**
 * Confirm a logout.
 */
function template_logout()
{
	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<form action="', Config::$scripturl . '?action=logout;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8" name="frmLogout" id="frmLogout">
		<div class="logout">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('logout_confirm', file: 'Login'), '</h3>
			</div>
			<div class="roundframe">
				<p class="information centertext">
					', Lang::getTxt('logout_notice', file: 'Login'), '
				</p>

				<p class="centertext">
					<input type="submit" value="', Lang::getTxt('logout', file: 'General'), '" class="button">
					<input type="submit" name="cancel" value="', Lang::getTxt('logout_return', file: 'Login'), '" class="button">
				</p>
			</div>
		</div><!-- .logout -->
	</form>';
}
