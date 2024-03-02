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
        <form id="upform" action="', Maintenance::getSelf(), '?' . Maintenance::setQueryString(), '" method="post">';
		}
	}

	/**
	 * Lower template for upgrader.
	 */
	public static function lower(): void
	{
		if (!empty(Maintenance::$context['pause'])) {
			echo '
								<em>', Lang::$txt['upgrade_incomplete'], '.</em><br>
	
								<h2 style="margin-top: 2ex;">', Lang::$txt['upgrade_not_quite_done'], '</h2>
								<h3>
									', Lang::$txt['upgrade_paused_overload'], '
								</h3>';
		}


		if (!empty(Maintenance::$context['continue']) || !empty(Maintenance::$context['skip']) || !empty(Maintenance::$context['try_again'])) {
			echo '
                                <div class="floatright">';

			if (!empty(Maintenance::$context['continue'])) {
				echo '
                                    <input type="submit" id="contbutt" name="contbutt" value="', Lang::$txt['action_continue'], '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['try_again'])) {
				echo '
                                    <input type="submit" id="try_again" name="try_again" value="', Lang::$txt['error_message_try_again'], '" onclick="return submitThisOnce(this);" class="button">';
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

		// Are we on a pause?
		if (!empty(Maintenance::$context['pause'])) {
			echo '
		<script defer>
			window.onload = doAutoSubmit;
		</script>';
		}

		echo '
		<script>
		let countdown = 3;
		let dontSubmit = false;
		function doAutoSubmit()
		{
			if (countdown == 0 && !dontSubmit) {
				document.getElementById("upform").submit();
			}
			else if (countdown == -1) {
				return;
			}

			document.getElementById("contbutt").value = "', Lang::$txt['action_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
		</script>';

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
				<h3>', Lang::$txt['error_warning_notice'], '</h3>
				', Lang::getTxt('upgrade_warning_out_of_date', ['SMF_VERSION' => SMF_VERSION, 'url' => 'https://www.simplemachines.org']), '
			</div>';

		Template::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		// Show a CHMOD form.
		self::chmod();

		if (!empty(Maintenance::$context['chmod']['files'])) {
			return;
		}

		// For large, pre 1.1 RC2 forums give them a warning about the possible impact of this upgrade!
		if (Maintenance::$context['is_large_forum']) {
			echo '
			<div class="errorbox">
				<h3>', Lang::$txt['error_warning_notice'], '</h3>
				', Lang::$txt['upgrade_warning_lots_data'], '
			</div>';
		}

		// Paths are incorrect?
		echo '
			<div class="errorbox', (file_exists(Maintenance::$theme_dir . '/scripts/script.js') ? ' hidden' : ''), '" id="js_script_missing_error">
				<h3>', Lang::$txt['critical_error'], '</h3>
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
	<input type="hidden" name="js_support" id="js_support" value="0">';

		// Say we want the continue button!
		Maintenance::$context['continue'] = !empty(Maintenance::$context['user']['id']) && time() - Maintenance::$context['updated'] < $tool->inactive_timeout ? 2 : 1;

		// This defines whether javascript is going to work elsewhere :D
		echo '
	<script>
		if (\'XMLHttpRequest\' in window && document.getElementById(\'js_support\'))
			document.getElementById(\'js_support\').value = 1;

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
		addEventListener("load", smfCurrentVersion);

		// This checks that the script file even exists!
		if (typeof(smfSelectText) == \'undefined\')
			document.getElementById(\'js_script_missing_error\').classList.remove(\'hidden\');
	</script>';
	}

	/**
	 * Upgrade options template.
	 */
	public static function upgradeOptions(): void
	{
		echo '
		<h3>', Lang::$txt['upgrade_areyouready'], '</h3>';

		Template::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		echo '
		<ul class="upgrade_settings">
			<li>
				<input type="checkbox" name="backup" id="backup" value="1" checked>
				<label for="backup">', Lang::$txt['upgrade_backup_table'], ' &quot;backup_' . Maintenance::$context['db_prefix'] . '&quot;.</label>
				(', Lang::$txt['upgrade_recommended'], ')
			</li>
			<li>
				<input type="checkbox" name="maint" id="maint" value="1" checked>
				<label for="maint">', Lang::$txt['upgrade_maintenance'], '</label>
				<span class="smalltext">(<a href="javascript:void(0)" onclick="document.getElementById(\'mainmess\').classList.toggle(\'hidden\')">', Lang::$txt['upgrade_customize'], '</a>)</span>
				<div id="mainmess" class="hidden">
					<strong class="smalltext">', Lang::$txt['upgrade_maintenance_title'], ' </strong><br>
					<input type="text" name="maintitle" size="30" value="', Maintenance::$context['message_title'], '"><br>
					<strong class="smalltext">', Lang::$txt['upgrade_maintenance_message'], ' </strong><br>
					<textarea name="mainmessage" rows="3" cols="50">', Maintenance::$context['message_body'], '</textarea>
				</div>
			</li>
			<li>
				<input type="checkbox" name="debug" id="debug" value="1">
				<label for="debug">', Lang::$txt['upgrade_debug_info'], '</label>
			</li>
			<li>
				<input type="checkbox" name="empty_error" id="empty_error" value="1">
				<label for="empty_error">', Lang::$txt['upgrade_empty_errorlog'], '</label>
			</li>';

		if (!empty(Maintenance::$context['karma_installed']['good']) || !empty(Maintenance::$context['karma_installed']['bad'])) {
		echo '
			<li>
				<input type="checkbox" name="delete_karma" id="delete_karma" value="1">
				<label for="delete_karma">', Lang::$txt['upgrade_delete_karma'], '</label>
			</li>';
		}

		// If attachment step has been run previously, offer an option to do it again.
		// Helpful if folks had improper attachment folders specified previously.
		if (!empty(Maintenance::$context['attachment_conversion'])) {
		echo '
			<li>
				<input type="checkbox" name="reprocess_attachments" id="reprocess_attachments" value="1">
				<label for="reprocess_attachments">', Lang::$txt['upgrade_reprocess_attachments'], '</label>
			</li>';
		}

		echo '
			<li>
				<input type="checkbox" name="stats" id="stats" value="1"', Maintenance::$context['sm_stats_configured'] ? '' : ' checked="checked"', '>
				<label for="stat">
					', Lang::$txt['upgrade_stats_collection'], '<br>
					<span class="smalltext">', Lang::getTxt('upgrade_stats_info', ['url' => 'https://www.simplemachines.org/about/stats.php']), '</a></span>
				</label>
			</li>
			<li>
				<input type="checkbox" name="migrateSettings" id="migrateSettings" value="1"', empty(Maintenance::$context['migrate_settings_recommended']) ? '' : ' checked="checked"', '>
				<label for="migrateSettings">
					', Lang::$txt['upgrade_migrate_settings_file'], '
				</label>
			</li>
		</ul>
		<input type="hidden" name="upcont" value="1">';
	}

	/**
	 * Backup database template.
	 */
	public static function backupDatabase(): void
	{
		echo '
		<h3>', Lang::$txt['upgrade_wait'], '</h3>';

		echo '
			<input type="hidden" name="backup_done" id="backup_done" value="0">
			<strong>', Lang::getTxt('upgrade_completedtables_outof', Maintenance::$context), '</strong>
			<div id="debug_section">
				<span id="debuginfo"></span>
			</div>';

		echo '
			<h3 id="current_tab">
				', Lang::$txt['upgrade_current_table'], ' &quot;<span id="current_table">', Maintenance::$context['cur_table_name'], '</span>&quot;
			</h3>
			<p id="commess" class="', Maintenance::$context['cur_table_num'] == Maintenance::$context['table_count'] ? 'inline_block' : 'hidden', '">', Lang::$txt['upgrade_backup_complete'], '</p>';

		echo '
            <div class="errorbox" id="errorbox" style="display: none;">
                <h3>', Lang::$txt['critical_error'], '</h3>
                <span>', Lang::$txt['error_unknown'], '</span>
            </div>';

		// Continue please!
		Maintenance::$context['continue'] = true;

		// Pour me a cup of javascript.
		echo '
					<script>
						const iTotalTables = ', Maintenance::$context['table_count'], ';
						const iStepWeight = ', Maintenance::$context['step_weight'], ';
						let iLastTableIndex = ', Maintenance::$context['cur_table_num'], ';
						let iStepProgress = 0;
						let sCurrentTableName = "";

						function getNextTables()
						{
							const url = "' . Maintenance::getSelf() . '?' . Maintenance::setQueryString() . '&json".replace(/substep=\d+/, "substep=" + iLastTableIndex);

							fetch(url, {
								method: "GET",
								credentials: "include",
							}).then(function(response){
								response.json().then(function(json) {
									if (json.success != true) {
										document.getElementById("errorbox").style.display = "";
										document.getElementById("contbutt").disabled = 0;
										document.getElementById("upform").src = document.getElementById("upform").src.replace(/substep=\d+/, "substep=" + iLastTableIndex);				
										return;
									}

									sCurrentTableName = json.data.current_table_name;
									iLastTableIndex = parseInt(json.data.current_table_index);
									iStepProgress = parseInt(json.data.substep_progres);

									// Update the page.
									setInnerHTML(document.getElementById("tab_done"), iLastTableIndex);
									setInnerHTML(document.getElementById("current_table"), sCurrentTableName);

									updateProgress(iLastTableIndex, iTotalTables, iStepWeight, iStepProgress);

									if (isDebug) {
										setOuterHTML(document.getElementById("debuginfo"), "<br>', Lang::$txt['upgrade_completed_table'], ' &quot;" + sCurrentTableName + "&quot;.<span id=\'debuginfo\'><" + "/span>");

										if (document.getElementById("debug_section").scrollHeight) {
											document.getElementById("debug_section").scrollTop = document.getElementById("debug_section").scrollHeight
										}
									}

									// Are we done yet?
									if (iLastTableIndex == iTotalTables) {
										document.getElementById("commess").classList.remove("hidden");
										document.getElementById("current_tab").classList.add("hidden");
										document.getElementById("contbutt").disabled = 0;
										document.getElementById("backup_done").value = 1;

										setTimeout("doAutoSubmit();", 1000);
									}
									else {
										getNextTables();
									}
								});
							}).catch(function(error) {
								console.log("Fetch Error:", error);

								document.getElementById("errorbox").style.display = "";
								if (isDebug) {
									document.getElementById("errorbox").getElementsByTagName("span")[0].innerText = error;
									document.getElementById("contbutt").disabled = 0;
									document.getElementById("upform").src = document.getElementById("upform").src.replace(/substep=\d+/, "substep=" + iLastTableIndex);				
								}
							});
						}

						// Lets try to let the browser handle this.
						window.addEventListener("load", (event) => {
							document.getElementById("contbutt").disabled = 1;
							getNextTables();
						});
					//# sourceURL=dynamicScript-bkup.js
					</script>';
	}

	/**
	 * Migrations template.
	 */
	public static function migrations(): void
	{

	}

	/**
	 * Cleanup template.
	 */
	public static function cleanup(): void
	{

	}

	/**
	 * Delete upgrade template.
	 */
	public static function deleteUpgrade(): void
	{

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