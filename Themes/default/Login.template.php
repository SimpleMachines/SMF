<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// This is just the basic "login" form.
function template_login()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	echo '
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/login_hd.png" alt="" class="icon"> ', $txt['login'], '
				</h3>
			</div>
			<div class="roundframe">
				<form class="login" action="', $context['login_url'], '" name="frmLogin" id="frmLogin" method="post" accept-charset="', $context['character_set'], '">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
					<div class="errorbox">', implode('<br>', $context['login_errors']), '</div><br>';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
					<p class="information">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
					<dl>
						<dt>', $txt['username'], ':</dt>
						<dd><input type="text" id="', !empty($context['from_ajax']) ? 'ajax_' : '', 'loginuser" name="user" size="20" value="', $context['default_username'], '" class="input_text"></dd>
						<dt>', $txt['password'], ':</dt>
						<dd><input type="password" id="', !empty($context['from_ajax']) ? 'ajax_' : '', 'loginpass" name="passwrd" value="', $context['default_password'], '" size="20" class="input_password"></dd>
					</dl>
					<dl>
						<dt>', $txt['mins_logged_in'], ':</dt>
						<dd><input type="number" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled' : '', ' class="input_text" min="1" max="525600"></dd>
						<dt>', $txt['always_logged_in'], ':</dt>
						<dd><input type="checkbox" name="cookieneverexp"', $context['never_expire'] ? ' checked' : '', ' class="input_check" onclick="this.form.cookielength.disabled = this.checked;"></dd>';
	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
						<dt class="alert">', $txt['undelete_account'], ':</dt>
						<dd><input type="checkbox" name="undelete" class="input_check"></dd>';
	echo '
					</dl>
					<p><input type="submit" value="', $txt['login'], '" class="button_submit"></p>
					<p class="smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
					<input type="hidden" name="hash_passwrd" value="">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '">
					<script type="text/javascript">
						setTimeout(function() {
							document.getElementById("', !empty($context['from_ajax']) ? 'ajax_' : '', isset($context['default_username']) && $context['default_username'] != '' ? 'loginpass' : 'loginuser', '").focus();
						}, 150);';
	if (!empty($context['from_ajax']) && (empty($modSettings['force_ssl']) || $modSettings['force_ssl'] == 2))
		echo '
						form = $("#frmLogin");
						form.submit(function(e) {
							e.preventDefault();
							e.stopPropagation();

							$.ajax({
								url: form.prop("action"),
								method: "POST",
								data: form.serialize(),
								success: function(data) {
									if (data.indexOf("<bo" + "dy") > -1)
										document.location = ', JavaScriptEscape(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : $scripturl), ';
									else {
										form.parent().html($(data).find(".roundframe").html());
									}
								},
								error: function() {
									document.location = ', JavaScriptEscape(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : $scripturl), ';
								}
							});

							return false;
						});';

	echo '
					</script>
				</form>';

	// It is a long story as to why we have this when we're clearly not going to use it.
	if (!empty($context['from_ajax']))
		echo '
					<br>
					<a href="javascript:self.close();"></a>';
	echo '
			</div>
		</div>';
}

// TFA authentication
function template_login_tfa()
{
	global $context, $scripturl, $modSettings, $txt;

	echo '
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['tfa_profile_label'] ,'
				</h3>
			</div>
			<div class="roundframe">';
	if (!empty($context['tfa_error']) || !empty($context['tfa_backup_error']))
		echo '
				<div class="error">', $txt['tfa_' . (!empty($context['tfa_error']) ? 'code_' : 'backup_') . 'invalid'], '</div>';
	echo '
				<form action="', $context['tfa_url'], '" method="post" id="frmTfa">
					<div id="tfaCode">
						', $txt['tfa_login_desc'], '<br>
						<strong>', $txt['tfa_code'], ':</strong>
						<input type="text" class="input_text" name="tfa_code" style="width: 150px;" value="', !empty($context['tfa_value']) ? $context['tfa_value'] : '', '">
						<input type="submit" class="button_submit" name="submit" value="', $txt['login'], '">
						<hr />
						<input type="button" class="button_submit" name="backup" value="', $txt['tfa_backup'], '" style="float: none; margin: 0;">
					</div>
					<div id="tfaBackup" style="display: none;">
						', $txt['tfa_backup_desc'], '<br>
						<strong>', $txt['tfa_backup_code'], ': </strong>
						<input type="text" class="input_text" name="tfa_backup" style="width: 150px;" value="', !empty($context['tfa_backup']) ? $context['tfa_backup'] : '', '">
						<input type="submit" class="button_submit" name="submit" value="', $txt['login'], '">
					</div>
				</form>
				<script type="text/javascript">
						form = $("#frmTfa");';
	if (!empty($context['from_ajax']))
		echo '
						form.submit(function(e) {
							// If we are submitting backup code, let normal workflow follow since it redirects a couple times into a different page
							if (form.find("input[name=tfa_backup]:first").val().length > 0)
								return true;

							e.preventDefault();
							e.stopPropagation();

							$.post(form.prop("action"), form.serialize(), function(data) {
								if (data.indexOf("<bo" + "dy") > -1)
									document.location = ', JavaScriptEscape(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : $scripturl), ';
								else {
									form.parent().html($(data).find(".roundframe").html());
								}
							});

							return false;
						});';
	echo '
						form.find("input[name=backup]").click(function(e) {
							$("#tfaBackup").show();
							$("#tfaCode").hide();
						});
				</script>
			</div>
		</div>';
}

// Tell a guest to get lost or login!
function template_kick_guest()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<form action="', $context['login_url'], '" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin">
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['warning'], '</h3>
			</div>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br>';


	if ($context['can_register'])
		echo sprintf($txt['login_below_or_register'], $scripturl . '?action=signup', $context['forum_name_html_safe']);
	else
		echo $txt['login_below'];

	// And now the login information.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/login_hd.png" alt="" class="icon"> ', $txt['login'], '
				</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" class="input_text"></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" size="20" class="input_password"></dd>
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text"></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp" class="input_check" onclick="this.form.cookielength.disabled = this.checked;"></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['login'], '" class="button_submit"></p>
				<p class="centertext smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '">
			<input type="hidden" name="hash_passwrd" value="">
		</div>
	</form>';

	// Do the focus thing...
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.user.focus();
		// ]]></script>';
}

// This is for maintenance mode.
function template_maintenance()
{
	global $context, $settings, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
<form action="', $context['login_url'], '" method="post" accept-charset="', $context['character_set'], '">
	<div class="tborder login" id="maintenance_mode">
		<div class="cat_bar">
			<h3 class="catbg">', $context['title'], '</h3>
		</div>
		<p class="information">
			<img class="floatleft" src="', $settings['images_url'], '/construction.png" width="40" height="40" alt="', $txt['in_maintain_mode'], '">
			', $context['description'], '<br class="clear">
		</p>
		<div class="title_bar">
			<h4 class="titlebg">', $txt['admin_login'], '</h4>
		</div>
		<div class="roundframe">
			<dl>
				<dt>', $txt['username'], ':</dt>
				<dd><input type="text" name="user" size="20" class="input_text"></dd>
				<dt>', $txt['password'], ':</dt>
				<dd><input type="password" name="passwrd" size="20" class="input_password"></dd>
				<dt>', $txt['mins_logged_in'], ':</dt>
				<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text"></dd>
				<dt>', $txt['always_logged_in'], ':</dt>
				<dd><input type="checkbox" name="cookieneverexp" class="input_check"></dd>
			</dl>
			<input type="submit" value="', $txt['login'], '" class="button_submit">
			<br class="clear">
		</div>
		<input type="hidden" name="hash_passwrd" value="">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '">
	</div>
</form>';
}

// This is for the security stuff - makes administrators login every so often.
function template_admin_login()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
<form action="', !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($scripturl, array('http://' => 'https://')) : $scripturl, $context['get_data'], '" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin">
	<div class="tborder login" id="admin_login">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/login_hd.png" alt="" class="icon"> ', $txt['login'], '
			</h3>
		</div>
		<div class="roundframe centertext">';

	if (!empty($context['incorrect_password']))
		echo '
			<div class="error">', $txt['admin_incorrect_password'], '</div>';

	echo '
			<strong>', $txt['password'], ':</strong>
			<input type="password" name="', $context['sessionCheckType'], '_pass" size="24" class="input_password">
			<a href="', $scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a><br>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-login_token_var'], '" value="', $context['admin-login_token'], '">
			<input type="submit" style="margin-top: 1em;" value="', $txt['login'], '" class="button_submit">';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
		</div>
	</div>
	<input type="hidden" name="', $context['sessionCheckType'], '_hash_pass" value="">
</form>';

	// Focus on the password box.
	echo '
<script><!-- // --><![CDATA[
	document.forms.frmLogin.', $context['sessionCheckType'], '_pass.focus();
// ]]></script>';
}

// Activate your account manually?
function template_retry_activate()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="30" class="input_text"></dd>';

	echo '
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text"></dd>
				</dl>
				<p><input type="submit" value="', $txt['invalid_activation_submit'], '" class="button_submit"></p>
			</div>
		</form>';
}

// Activate your account manually?
function template_resend()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="40" value="', $context['default_username'], '" class="input_text"></dd>
				</dl>
				<p>', $txt['invalid_activation_new'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_new_email'], ':</dt>
					<dd><input type="text" name="new_email" size="40" class="input_text"></dd>
					<dt>', $txt['invalid_activation_password'], ':</dt>
					<dd><input type="password" name="passwd" size="30" class="input_password"></dd>
				</dl>';

	if ($context['can_activate'])
		echo '
				<p>', $txt['invalid_activation_known'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text"></dd>
				</dl>';

	echo '
				<p><input type="submit" value="', $txt['invalid_activation_resend'], '" class="button_submit"></p>
			</div>
		</form>';
}

?>