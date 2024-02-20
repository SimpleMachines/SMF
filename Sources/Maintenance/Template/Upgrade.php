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

declare(strict_types=1);

namespace SMF\Maintenance\Template;

use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Template;
use SMF\Maintenance\TemplateInterface;
use SMF\Sapi;

/**
 * Template for Upgrader
 */
class Upgrade implements TemplateInterface
{
	/**
	 * Upper template for upgrader.
	 */
	public static function upper(): void
	{
		if (count(Maintenance::$tool->getSteps()) - 1 !== (int) Maintenance::getCurrentStep()) {
		echo '
        <form action="', Maintenance::getSelf(), (Maintenance::$query_string !== '' ? '?' . Maintenance::$query_string : ''), '" method="post">';
		}
	}

	/**
	 * Lower template for upgrader.
	 */
	public static function lower(): void
	{
		if (!empty(Maintenance::$context['continue']) || !empty(Maintenance::$context['skip'])) {
			echo '
                                <div class="floatright">';

			if (!empty(Maintenance::$context['continue'])) {
				echo '
                                    <input type="submit" id="contbutt" name="contbutt" value="', Lang::$txt['action_continue'], '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['skip'])) {
				echo '
                                    <input type="submit" id="skip" name="skip" value="', Lang::$txt['action_skip'], '" onclick="return submitThisOnce(this);" class="button">';
			}
			echo '
                                </div>';
		}

		// Show the closing form tag and other data only if not in the last step
		if (count(Maintenance::$tool->getSteps()) - 1 !== (int) Maintenance::getCurrentStep()) {
			echo '
        </form>';
		}
	}

	/**
	 * Welcome page for upgrader.
	 */
	public static function welcomeLogin(): void
	{
		echo '
			<div id="no_js_container">
				<div class="errorbox">
				<h3>', Lang::$txt['critical_error'], '</h3>
				', Lang::$txt['error_no_javascript'], '
				</div>
			</div>
			<script>
				document.getElementById(\'no_js_container\').classList.add(\'hidden\');
			</script>';

		echo '
			<script src="https://www.simplemachines.org/smf/current-version.js?version=' . SMF_VERSION . '"></script>
			<h3>', Lang::getTxt('upgrade_ready_proceed', ['SMF_VERSION' => SMF_VERSION]), '</h3>

			<div id="version_warning" class="noticebox hidden">
				<h3>', Lang::$txt['upgrade_warning'], '</h3>
				', Lang::getTxt('upgrade_warning_out_of_date', ['SMF_VERSION' => SMF_VERSION, 'url' => 'https://www.simplemachines.org']), '
			</div>';

			if (!empty(Maintenance::$fatal_error) || count(Maintenance::$errors) > 0 || count(Maintenance::$warnings) > 0) {
				Template::warningsAndErrors();

				return;
			}

		// Show a CHMOD form.
		self::chmod();

		// For large, pre 1.1 RC2 forums give them a warning about the possible impact of this upgrade!
		if (Maintenance::$context['is_large_forum']) {
			echo '
			<div class="errorbox">
				<h3>', Lang::$txt['upgrade_warning'], '</h3>
				', Lang::$txt['upgrade_warning_lots_data'], '
			</div>';
		}

		// Paths are incorrect?
		echo '
			<div class="errorbox', (file_exists(Maintenance::$theme_dir . '/scripts/script.js') ? ' hidden' : ''), '" id="js_script_missing_error">
				<h3>', Lang::$txt['upgrade_critical_error'], '</h3>
				', Lang::getTxt('upgrade_error_script_js', ['url' => 'https://download.simplemachines.org/?tools']), '
			</div>';

		// Is there someone already doing this?
		if (!empty(Maintenance::$context['user']['id']) && (time() - Maintenance::$context['started'] < 72600 || time() - Maintenance::$context['updated'] < 3600)) {
			echo '
			<div class="errorbox">
				<h3>', Lang::$txt['upgrade_warning'], '</h3>
				<p>', Lang::getTxt('upgrade_time_user', Maintenance::$context['user']['name']), '</p>
				<p>', self::timeAgo(Maintenance::$context['started'], 'upgrade_time'), '</p>
				<p>', self::timeAgo(Maintenance::$context['updated'], 'upgrade_time_updated'), '</p>';

		if (time() - Maintenance::$context['updated'] < 600) {
			echo '
				<p>', Lang::$txt['upgrade_run_script'], ' ', Maintenance::$context['user']['name'], ' ', Lang::$txt['upgrade_run_script2'], '</p>';
		}

		/** @var \SMF\Maintenance\Tools\Upgrade Maintenance::$tool */
		$tool = &Maintenance::$tool;

		if ((time() - Maintenance::$context['updated']) > $tool->inactive_timeout) {
			echo '
				<p>', Lang::$txt['upgrade_run'], '</p>';
		} elseif ($tool->inactive_timeout > 120) {
			echo '
				<p>', Lang::getTxt('upgrade_script_timeout_minutes', ['name' => Maintenance::$context['user']['name'], 'timeout' => round($tool->inactive_timeout / 60, 1)]), '</p>';
		} else {
			echo '
				<p>', Lang::getTxt('upgrade_script_timeout_seconds', ['name' => Maintenance::$context['user']['name'], 'timeout' => $tool->inactive_timeout]), '</p>';
		}

		echo '
			</div>';
		}

		echo '
			<strong>', Lang::$txt['upgrade_admin_login'], ' ', Maintenance::$disable_security ? Lang::$txt['upgrade_admin_disabled'] : '', '</strong>
			<h3>', Lang::$txt['upgrade_sec_login'], '</h3>
			<dl class="settings adminlogin">
				<dt>
					<label for="user"', Maintenance::$disable_security ? ' disabled' : '', '>', Lang::$txt['upgrade_username'], '</label>
				</dt>
				<dd>
					<input type="text" name="user" value="', !empty(Maintenance::$context['username']) ? Maintenance::$context['username'] : '', '"', Maintenance::$disable_security ? ' disabled' : '', '>';

		if (!empty($upcontext['username_incorrect'])) {
			echo '
											<div class="smalltext red">', Lang::$txt['upgrade_wrong_username'], '</div>';
		}

		echo '
		</dd>
		<dt>
			<label for="passwrd"', Maintenance::$disable_security ? ' disabled' : '', '>', Lang::$txt['upgrade_password'], '</label>
		</dt>
		<dd>
			<input type="password" name="passwrd" value=""', Maintenance::$disable_security ? ' disabled' : '', '>';

		if (!empty($upcontext['password_failed'])) {
		echo '
			<div class="smalltext red">', Lang::$txt['upgrade_wrong_password'], '</div>';
		}

		echo '
		</dd>';

		// Can they continue?
		if (!empty(Maintenance::$context['user']['id']) && time() - Maintenance::$context['user']['updated'] >= $tool->inactive_timeout && Maintenance::$context['user']['step'] > 1) {
			echo '
		<dd>
			<label for="cont"><input type="checkbox" id="cont" name="cont" checked>', Lang::$txt['upgrade_continue_step'], '</label>
		</dd>';
		}

		echo '
	</dl>
	<span class="smalltext">
		', Lang::$txt['upgrade_bypass'], '
	</span>';

	if (!empty(Maintenance::$context['login_token_var'])) {
		echo '
	<input type="hidden" name="', Maintenance::$context['login_token_var'], '" value="', Maintenance::$context['login_token'], '">';
	}
		echo '
	<input type="hidden" name="login_attempt" id="login_attempt" value="1">
	<input type="hidden" name="js_works" id="js_works" value="0">';

		// Say we want the continue button!
		Maintenance::$context['continue'] = !empty(Maintenance::$context['user']['id']) && time() - Maintenance::$context['updated'] < $tool->inactive_timeout ? 2 : 1;

		// This defines whether javascript is going to work elsewhere :D
		echo '
	<script>
		if (\'XMLHttpRequest\' in window && document.getElementById(\'js_works\'))
			document.getElementById(\'js_works\').value = 1;

		// Latest version?
		function smfCurrentVersion()
		{
			var smfVer, yourVer;

			if (!(\'smfVersion\' in window))
				return;

			window.smfVersion = window.smfVersion.replace(/SMF\s?/g, \'\');

			smfVer = document.getElementById(\'smfVersion\');
			yourVer = document.getElementById(\'yourVersion\');

			setInnerHTML(smfVer, window.smfVersion);

			var currentVersion = getInnerHTML(yourVer);
			if (currentVersion < window.smfVersion)
				document.getElementById(\'version_warning\').classList.remove(\'hidden\');
		}
		addLoadEvent(smfCurrentVersion);

		// This checks that the script file even exists!
		if (typeof(smfSelectText) == \'undefined\')
			document.getElementById(\'js_script_missing_error\').classList.remove(\'hidden\');

	</script>';
	}


	/**
	 * Did we call the chmod template?
	 *
	 * @var bool True if we did, false otherwise.
	 */
	private static bool $chmod_called = false;

	/**
	 * Template for CHMOD.
	 */
	protected static function chmod()
	{
		// Don't call me twice!
		if (self::$chmod_called) {
			return;
		}
		self::$chmod_called = true;

		// Nothing?
		if (empty(Maintenance::$context['chmod']['files']) && empty(Maintenance::$context['chmod']['ftp_error'])) {
			return;
		}

		// Was it a problem with Windows?
		if (!empty(Maintenance::$context['chmod']['ftp_error']) && Maintenance::$context['chmod']['ftp_error'] == 'total_mess') {
			echo '
			<div class="error">
				<p>', Lang::$txt['upgrade_writable_files'], '</p>
				<ul class="error_content">
					<li>' . implode('</li>
					<li>', Maintenance::$context['chmod']['files']) . '</li>
				</ul>
			</div>';

			return false;
		}

		echo '
		<div class="panel">
			<h2>', Lang::$txt['upgrade_ftp_login'], '</h2>
			<h3>', Lang::$txt['upgrade_ftp_perms'], '</h3>
			<script>
				function warning_popup()
				{
					popup = window.open(\'\',\'popup\',\'height=150,width=400,scrollbars=yes\');
					var content = popup.document;
					content.write(\'<!DOCTYPE html>\n\');
					content.write(\'<html', Lang::$txt['lang_rtl'] == '1' ? ' dir="rtl"' : '', '>\n\t<head>\n\t\t<meta name="robots" content="noindex">\n\t\t\');
					content.write(\'<title>', Lang::$txt['upgrade_ftp_warning'], '</title>\n\t\t<link rel="stylesheet" href="', Maintenance::$theme_url, '/css/index.css">\n\t</head>\n\t<body id="popup">\n\t\t\');
					content.write(\'<div class="windowbg description">\n\t\t\t<h4>', Lang::$txt['upgrade_ftp_files'], '</h4>\n\t\t\t\');
					content.write(\'<p>', implode('<br>\n\t\t\t', Maintenance::$context['chmod']['files']), '</p>\n\t\t\t\');';

		if (Sapi::isOS([Sapi::OS_LINUX, Sapi::OS_MAC])) {
			echo '
					content.write(\'<hr>\n\t\t\t\');
					content.write(\'<p>', Lang::$txt['upgrade_ftp_shell'], '</p>\n\t\t\t\');
					content.write(\'<tt># chmod a+w ', implode(' ', Maintenance::$context['chmod']['files']), '</tt>\n\t\t\t\');';
		}

		echo '
					content.write(\'<a href="javascript:self.close();">close</a>\n\t\t</div>\n\t</body>\n</html>\');
					content.close();
				}
			</script>';

		if (!empty(Maintenance::$context['chmod']['ftp_error'])) {
			echo '
			<div class="error">
				<p>', Lang::$txt['upgrade_ftp_error'], '<p>
				<code>', Maintenance::$context['chmod']['ftp_error'], '</code>
			</div>';
		}

		echo '
				<dl class="settings">
					<dt>
						<label for="ftp_server">', Lang::$txt['ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port" class="textbox"><strong>', Lang::$txt['ftp_port'], ':</strong></label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', Maintenance::$context['chmod']['port'] ?? '21', '">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', Maintenance::$context['chmod']['server'] ?? 'localhost', '">
						<div class="smalltext">', Lang::$txt['ftp_server_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_username">', Lang::$txt['ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_username" id="ftp_username" value="', Maintenance::$context['chmod']['username'] ?? '', '">
						<div class="smalltext">', Lang::$txt['ftp_username_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_password">', Lang::$txt['ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="30" name="ftp_password" id="ftp_password">
						<div class="smalltext">', Lang::$txt['ftp_password_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_path">', Lang::$txt['ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_path" id="ftp_path" value="', Maintenance::$context['chmod']['path'] ?? '', '">
						<div class="smalltext">', !empty(Maintenance::$context['chmod']['path']) ? Lang::$txt['ftp_path_found_info'] : Lang::$txt['ftp_path_info'], '</div>
					</dd>
				</dl>

				<div class="righttext buttons">
					<input type="submit" value="', Lang::$txt['ftp_connect'], '" class="button">
				</div>';

		echo '
		</div><!-- .panel -->';
	}

	/**
	 * Provide a simple interface for showing time ago.
	 */
	protected static function timeAgo(int $timestamp, string $base_key): string
	{
		$ago = time() - $timestamp;
		$ago_hours = floor($ago / 3600);
		$ago_minutes = (int) (((int) ($ago / 60)) % 60);
		$ago_seconds = intval($ago % 60);
		$txt_suffix = $ago < 60 ? '_s' : ($ago < 3600 ? '_ms' : '_hms');

		return Lang::getTxt($base_key . $txt_suffix, ['s' => $ago_seconds, 'm' => $ago_minutes, 'h' => $ago_hours]);
	}
}

?>