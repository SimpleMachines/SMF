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

// This is just the basic "login" form.
function template_login()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

		<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="', $context['character_set'], '" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="ie6_header floatleft"><img src="', $settings['images_url'], '/icons/login_sm.gif" alt="" class="icon" /> ', $txt['login'], '</span>
				</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe"><br class="clear" />';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		foreach ($context['login_errors'] as $error)
			echo '
				<p class="error">', $error, '</p>';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
				<p class="description">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" value="', $context['default_username'], '" class="input_text" /></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" value="', $context['default_password'], '" size="20" class="input_password" /></dd>
				</dl>';

	if (!empty($modSettings['enableOpenID']))
		echo '<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd><input type="text" name="openid_identifier" class="input_text openid_login" size="17" />&nbsp;<em><a href="', $scripturl, '?action=helpadmin;help=register_openid" onclick="return reqWin(this.href);" class="help">(?)</a></em></dd>
				</dl><hr />';

	echo '
				<dl>
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled="disabled"' : '', ' class="input_text" /></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp"', $context['never_expire'] ? ' checked="checked"' : '', ' class="input_check" onclick="this.form.cookielength.disabled = this.checked;" /></dd>';
	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
					<dt class="alert">', $txt['undelete_account'], ':</dt>
					<dd><input type="checkbox" name="undelete" class="input_check" /></dd>';
	echo '
				</dl>
				<p><input type="submit" value="', $txt['login'], '" class="button_submit" /></p>
				<p class="smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
				<input type="hidden" name="hash_passwrd" value="" />
			</div>
			<span class="lowerframe"><span></span></span>
		</div></form>';

	// Focus on the correct input - username or password.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			document.forms.frmLogin.', isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user', '.focus();
		// ]]></script>';
}

// Tell a guest to get lost or login!
function template_kick_guest()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="tborder login">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['warning'], '</h3>
			</div>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br />
				', $txt['login_below'], ' <a href="', $scripturl, '?action=register">', $txt['register_an_account'], '</a> ', sprintf($txt['login_with_forum'], $context['forum_name_html_safe']), '
			</p>';

	// And now the login information.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/login_sm.gif" alt="" class="icon" /> ', $txt['login'], '
				</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" class="input_text" /></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" size="20" class="input_password" /></dd>';

	if (!empty($modSettings['enableOpenID']))
		echo '
				</dl>
				<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd><input type="text" name="openid_identifier" class="input_text openid_login" size="17" /></dd>
				</dl>
				<hr />
				<dl>';

	echo '
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" /></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp" class="input_check" onclick="this.form.cookielength.disabled = this.checked;" /></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['login'], '" class="button_submit" /></p>
				<p class="centertext smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
			</div>
			<span class="lowerframe"><span></span></span>
			<input type="hidden" name="hash_passwrd" value="" />
		</div>
	</form>';

	// Do the focus thing...
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			document.forms.frmLogin.user.focus();
		// ]]></script>';
}

// This is for maintenance mode.
function template_maintenance()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
	<div class="tborder login" id="maintenance_mode">
		<div class="cat_bar">
			<h3 class="catbg">', $context['title'], '</h3>
		</div>
		<p class="description">
			<img class="floatleft" src="', $settings['images_url'], '/construction.png" width="40" height="40" alt="', $txt['in_maintain_mode'], '" />
			', $context['description'], '<br class="clear" />
		</p>
		<div class="title_bar">
			<h4 class="titlebg">', $txt['admin_login'], '</h4>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe">
			<dl>
				<dt>', $txt['username'], ':</dt>
				<dd><input type="text" name="user" size="20" class="input_text" /></dd>
				<dt>', $txt['password'], ':</dt>
				<dd><input type="password" name="passwrd" size="20" class="input_password" /></dd>
				<dt>', $txt['mins_logged_in'], ':</dt>
				<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" /></dd>
				<dt>', $txt['always_logged_in'], ':</dt>
				<dd><input type="checkbox" name="cookieneverexp" class="input_check" /></dd>
			</dl>
			<p class="centertext"><input type="submit" value="', $txt['login'], '" class="button_submit" /></p>
		</div>
		<span class="lowerframe"><span></span></span>
		<input type="hidden" name="hash_passwrd" value="" />
	</div>
</form>';
}

// This is for the security stuff - makes administrators login every so often.
function template_admin_login()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin" onsubmit="hashAdminPassword(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\');">
	<div class="tborder login" id="admin_login">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft"><img src="', $settings['images_url'], '/icons/login_sm.gif" alt="" class="icon" /> ', $txt['login'], '</span>
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">';

	if (!empty($context['incorrect_password']))
		echo '
			<div class="error">', $txt['admin_incorrect_password'], '</div>';

	echo '
			<strong>', $txt['password'], ':</strong>
			<input type="password" name="admin_pass" size="24" class="input_password" />
			<a href="', $scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a><br />
			<input type="submit" style="margin-top: 1em;" value="', $txt['login'], '" class="button_submit" />';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>
	<input type="hidden" name="admin_hash_pass" value="" />
</form>';

	// Focus on the password box.
	echo '
<script type="text/javascript"><!-- // --><![CDATA[
	document.forms.frmLogin.admin_pass.focus();
// ]]></script>';
}

// Activate your account manually?
function template_retry_activate()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="30" class="input_text" /></dd>';

	echo '
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text" /></dd>
				</dl>
				<p><input type="submit" value="', $txt['invalid_activation_submit'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</form>';
}

// Activate your account manually?
function template_resend()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="40" value="', $context['default_username'], '" class="input_text" /></dd>
				</dl>
				<p>', $txt['invalid_activation_new'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_new_email'], ':</dt>
					<dd><input type="text" name="new_email" size="40" class="input_text" /></dd>
					<dt>', $txt['invalid_activation_password'], ':</dt>
					<dd><input type="password" name="passwd" size="30" class="input_password" /></dd>
				</dl>';

	if ($context['can_activate'])
		echo '
				<p>', $txt['invalid_activation_known'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text" /></dd>
				</dl>';

	echo '
				<p><input type="submit" value="', $txt['invalid_activation_resend'], '" class="button_submit" /></p>
			</div>
			<span class="lowerframe"><span></span></span>
		</form>';
}

?>