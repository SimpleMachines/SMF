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

declare(strict_types=1);

namespace SMF\Themes\default;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance\Maintenance;

/**
 * Template for Installer
 */
class InstallTemplate extends MaintenanceTemplate
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Upper template for installer.
	 */
	public static function upper(): void
	{
		if (count(Maintenance::$tool->getSteps()) - 1 !== (int) Maintenance::getCurrentStep()) {
		echo '
		<form action="', Maintenance::getSelf(), (Maintenance::$sub_template !== '' ? '?step=' . Maintenance::getCurrentStep() : ''), '" method="post">';
		}
	}

	/**
	 * Lower template for installer.
	 */
	public static function lower(): void
	{
		if (!empty(Maintenance::$context['continue']) || !empty(Maintenance::$context['skip'])) {
			echo '
								<div class="floatright">';

			if (!empty(Maintenance::$context['continue'])) {
				echo '
									<input type="submit" id="contbutt" name="contbutt" value="', Lang::getTxt('action_continue', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['skip'])) {
				echo '
									<input type="submit" id="skip" name="skip" value="', Lang::getTxt('action_skip', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">';
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
	 * Welcome page for installer.
	 */
	public static function welcome(): void
	{
		echo '
			<script src="https://www.simplemachines.org/smf/current-version.js?version=' . urlencode(SMF_VERSION) . '"></script>

			<p>', Lang::getTxt('install_welcome_desc', ['SMF_VERSION' => SMF_VERSION]), '</p>
			<div id="version_warning" class="noticebox hidden">
				<h3>', Lang::getTxt('error_warning_notice', file: 'Maintenance'), '</h3>
				', Lang::getTxt('error_script_outdated', ['smfVersion' => '<em id="smfVersion" style="white-space: nowrap;">??</em>', 'yourVersion' => '<em id="yourVersion" style="white-space: nowrap;">' . SMF_VERSION . '</em>']), '
			</div>';

		// Oh no!
		if (!empty(Maintenance::$fatal_error) || count(Maintenance::$errors) > 0 || count(Maintenance::$warnings) > 0) {
			MaintenanceTemplate::warningsAndErrors();
		}

		// For the latest version stuff.
		echo '
			<script>
				// Latest version?
				function smfCurrentVersion()
				{
					var smfVer, yourVer;

					if (!(\'smfVersion\' in window))
						return;

					window.smfVersion = window.smfVersion.replace(/SMF\s?/g, \'\');

					smfVer = document.getElementById("smfVersion");
					yourVer = document.getElementById("yourVersion");

					setInnerHTML(smfVer, window.smfVersion);

					var currentVersion = getInnerHTML(yourVer);
					if (currentVersion < window.smfVersion)
						document.getElementById(\'version_warning\').classList.remove(\'hidden\');
				}
				addLoadEvent(smfCurrentVersion);
			</script>';
	}

	/**
	 * Check Files Writable page for installer.
	 */
	public static function checkFilesWritable(): void
	{
		echo '
			<p>', Lang::getTxt('ftp_setup_why_info', file: 'Maintenance'), '</p>
			<ul class="error_content">
				<li>', implode('</li>
				<li>', Maintenance::$context['chmod_files']), '</li>
			</ul>';

		if (isset(Maintenance::$context['systemos'], Maintenance::$context['detected_path']) && Maintenance::$context['systemos'] == 'linux') {
			echo '
			<hr>
			<p>', Lang::getTxt('chmod_linux_info', file: 'Maintenance'), '</p>
			<samp># chmod a+w ', implode(' ' . Maintenance::$context['detected_path'] . '/', Maintenance::$context['chmod_files']), '</samp>';
		}

		// This is serious!
		if (!empty(Maintenance::$fatal_error) || count(Maintenance::$errors) > 0 || count(Maintenance::$warnings) > 0) {
			MaintenanceTemplate::warningsAndErrors();

			return;
		}

		echo '
			<hr>
			<p>', Lang::getTxt('ftp_setup_info', file: 'Maintenance'), '</p>';

		if (!empty(Maintenance::$context['ftp_errors'])) {
			echo '
			<div class="error_message">
				', Lang::getTxt('error_ftp_no_connect', file: 'Maintenance'), '<br><br>
				<code>', implode('<br>', Maintenance::$context['ftp_errors']), '</code>
			</div>';
		}

		echo '
			<form action="', Maintenance::$context['form_url'], '" method="post">
				<dl class="settings">
					<dt>
						<label for="ftp_server">', Lang::getTxt('ftp_server', file: 'Maintenance'), ':</label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port" class="textbox"><strong>', Lang::getTxt('ftp_port', file: 'Maintenance'), ':&nbsp;</strong></label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', Maintenance::$context['chmod']['port'] ?? '', '">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', Maintenance::$context['chmod']['server'] ?? '', '">
						<div class="smalltext block">', Lang::getTxt('ftp_server_info', file: 'Maintenance'), '</div>
					</dd>
					<dt>
						<label for="ftp_username">', Lang::getTxt('ftp_username', file: 'Maintenance'), ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_username" id="ftp_username" value="', Maintenance::$context['chmod']['username'] ?? '', '">
						<div class="smalltext block">', Lang::getTxt('ftp_username_info', file: 'Maintenance'), '</div>
					</dd>
					<dt>
						<label for="ftp_password">', Lang::getTxt('ftp_password', file: 'Maintenance'), ':</label>
					</dt>
					<dd>
						<input type="password" size="30" name="ftp_password" id="ftp_password">
						<div class="smalltext block">', Lang::getTxt('ftp_password_info', file: 'Maintenance'), '</div>
					</dd>
					<dt>
						<label for="ftp_path">', Lang::getTxt('ftp_path', file: 'Maintenance'), ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_path" id="ftp_path" value="', Maintenance::$context['chmod']['path'] ?? '', '">
						<div class="smalltext block">', Maintenance::$context['chmod']['path_msg'] ?? '', '</div>
					</dd>
				</dl>
				<div class="righttext buttons">
					<input type="submit" value="', Lang::getTxt('ftp_connect', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">
				</div>
			</form>
			', Lang::getTxt('ftp_setup_again', ['url' => Maintenance::$context['form_url']], file: 'Maintenance');
	}

	/**
	 * Database Settings page for installer.
	 */
	public static function databaseSettings(): void
	{
		echo '
			<p>', Lang::getTxt('db_settings_info', file: 'Maintenance'), '</p>';

		MaintenanceTemplate::warningsAndErrors();

		echo '
			<dl class="settings">';

		// More than one database type?
		if (count(Maintenance::$context['databases']) > 1) {
			echo '
				<dt>
					<label for="db_type_input">', Lang::getText('db_settings_type', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<select name="db_type" id="db_type_input" onchange="toggleDBInput();">';

			foreach (Maintenance::$context['databases'] as $key => $db) {
				echo '
						<option value="', $key, '"', isset($_POST['db_type']) && $_POST['db_type'] == $key ? ' selected' : '', '>', $key, '</option>';
			}

			echo '
					</select>
					<div class="smalltext">', Lang::getTxt('db_settings_type_info', file: 'Maintenance'), '</div>
				</dd>';
		} else {
			echo '
				<dd>
					<input type="hidden" name="db_type" value="', Maintenance::$context['db']['type'], '">
				</dd>';
		}

		echo '
				<dt>
					<label for="db_server_input">', Lang::getTxt('db_settings_server', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="db_server" id="db_server_input" value="', Maintenance::$context['db']['server'], '" size="30">
					<div class="smalltext">', Lang::getTxt('db_settings_server_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="db_port_input">', Lang::getTxt('db_settings_port', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="db_port" id="db_port_input" value="', Maintenance::$context['db']['port'], '">
					<div class="smalltext">', Lang::getTxt('db_settings_port_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="db_user_input">', Lang::getTxt('db_settings_username', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="db_user" id="db_user_input" value="', Maintenance::$context['db']['user'], '" size="30">
					<div class="smalltext">', Lang::getTxt('db_settings_username_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="db_passwd_input">', Lang::getTxt('db_settings_password', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="password" name="db_passwd" id="db_passwd_input" value="', Maintenance::$context['db']['pass'], '" size="30">
					<div class="smalltext">', Lang::getTxt('db_settings_password_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="db_name_input">', Lang::getTxt('db_settings_database', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="db_name" id="db_name_input" value="', empty(Maintenance::$context['db']['name']) ? 'smf' : Maintenance::$context['db']['name'], '" size="30" pattern="^\w$">
					<div class="smalltext">
						', Lang::getTxt('db_settings_database_info', file: 'Maintenance'), '
						<span id="db_name_info_warning">', Lang::getTxt('db_settings_database_info_note', file: 'Maintenance'), '</span>
					</div>
				</dd>
				<dt>
					<label for="db_prefix_input">', Lang::getTxt('db_settings_prefix', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="db_prefix" id="db_prefix_input" value="', Maintenance::$context['db']['prefix'], '" size="30">
					<div class="smalltext">', Lang::getTxt('db_settings_prefix_info', file: 'Maintenance'), '</div>
				</dd>
			</dl>';

		// Toggles a warning related to db names in PostgreSQL
		echo '
			<script>
				function toggleDBInput()
				{
					if (document.getElementById(\'db_type_input\').value == \'postgresql\')
						document.getElementById(\'db_name_info_warning\').classList.add(\'hidden\');
					else
						document.getElementById(\'db_name_info_warning\').classList.remove(\'hidden\');
				}
				toggleDBInput();
			</script>';
	}

	/**
	 * Forum Settings page for installer.
	 */
	public static function forumSettings(): void
	{
		echo '
			<h3>', Lang::getText('install_settings_info', file: 'Maintenance'), '</h3>';

		MaintenanceTemplate::warningsAndErrors();

		echo '
			<dl class="settings">
				<dt>
					<label for="mbname_input">', Lang::getTxt('install_settings_name', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="mbname" id="mbname_input" value="', Lang::getTxt('install_settings_name_default', file: 'Maintenance'), '" size="65">
					<div class="smalltext">', Lang::getTxt('install_settings_name_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="boardurl_input">', Lang::getTxt('install_settings_url', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="boardurl" id="boardurl_input" value="', Maintenance::$context['detected_url'], '" size="65">
					<div class="smalltext">', Lang::getTxt('install_settings_url_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="reg_mode">', Lang::getTxt('install_settings_reg_mode', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<select name="reg_mode" id="reg_mode">
						<optgroup label="', Lang::getTxt('install_settings_reg_modes', file: 'Maintenance'), ':">
							<option value="0" selected>', Lang::getTxt('install_settings_reg_immediate', file: 'Maintenance'), '</option>
							<option value="1">', Lang::getTxt('install_settings_reg_email', file: 'Maintenance'), '</option>
							<option value="2">', Lang::getTxt('install_settings_reg_admin', file: 'Maintenance'), '</option>
							<option value="3">', Lang::getTxt('install_settings_reg_disabled', file: 'Maintenance'), '</option>
						</optgroup>
					</select>
					<div class="smalltext">', Lang::getTxt('install_settings_reg_mode_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>', Lang::getTxt('install_settings_compress', file: 'Maintenance'), ':</dt>
				<dd>
					<input type="checkbox" name="compress" id="compress_check" checked>
					<label for="compress_check">', Lang::getTxt('install_settings_compress_title', file: 'Maintenance'), '</label>
					<div class="smalltext">', Lang::getTxt('install_settings_compress_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>', Lang::getTxt('install_settings_dbsession', file: 'Maintenance'), ':</dt>
				<dd>
					<input type="checkbox" name="dbsession" id="dbsession_check" checked>
					<label for="dbsession_check">', Lang::getTxt('install_settings_dbsession_title', file: 'Maintenance'), '</label>
					<div class="smalltext">', Maintenance::$context['test_dbsession'] ? Lang::getTxt('install_settings_dbsession_info1', file: 'Maintenance') : Lang::getTxt('install_settings_dbsession_info2', file: 'Maintenance'), '</div>
				</dd>
				<dt>', Lang::getTxt('install_settings_stats', file: 'Maintenance'), ':</dt>
				<dd>
					<input type="checkbox" name="stats" id="stats_check" checked="checked">
					<label for="stats_check">', Lang::getTxt('install_settings_stats_title', file: 'Maintenance'), '</label>
					<div class="smalltext">', Lang::getTxt('install_settings_stats_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>', Lang::getTxt('force_ssl', file: 'Maintenance'), ':</dt>
				<dd>
					<input type="checkbox" name="force_ssl" id="force_ssl"', Maintenance::$context['ssl_chkbx_checked'] ? ' checked' : '',
						Maintenance::$context['ssl_chkbx_protected'] ? ' disabled' : '', '>
					<label for="force_ssl">', Lang::getTxt('force_ssl_label', file: 'Maintenance'), '</label>
					<div class="smalltext"><strong>', Lang::getTxt('force_ssl_info', file: 'Maintenance'), '</strong></div>
				</dd>
			</dl>';

	}

	/**
	 * Database Populate page for installer.
	 */
	public static function databasePopulation(): void
	{
		echo '
			<p>', !empty(Maintenance::$context['was_refresh']) ? Lang::getTxt('user_refresh_install_desc', file: 'Maintenance') : Lang::getTxt('db_populate_info', file: 'Maintenance'), '</p>';

		if (!empty(Maintenance::$context['sql_results'])) {
			echo '
			<ul>
				<li>', implode('</li><li>', Maintenance::$context['sql_results']), '</li>
			</ul>';
		}

		if (!empty(Maintenance::$context['failures'])) {
			echo '
			<div class="red">', Lang::getTxt('error_db_queries', file: 'Maintenance'), '</div>
			<ul>';

			foreach (Maintenance::$context['failures'] as $line => $fail) {
				echo '
				<li>', nl2br(htmlspecialchars($fail)), '</li>';
			}

			echo '
			</ul>';
		}

		echo '
			<p>', Lang::getTxt('db_populate_info2', file: 'Maintenance'), '</p>';

			MaintenanceTemplate::warningsAndErrors();

		echo '
			<input type="hidden" name="pop_done" value="1">';
	}

	/**
	 * Admin Account page for installer.
	 */
	public static function adminAccount(): void
	{
		echo '
			<p>', Lang::getTxt('user_settings_info', file: 'Maintenance'), '</p>';

			MaintenanceTemplate::warningsAndErrors();

		echo '
			<dl class="settings">
				<dt>
					<label for="username">', Lang::getTxt('user_settings_username', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="username" id="username" value="', Maintenance::$context['username'], '" size="40">
					<div class="smalltext">', Lang::getTxt('user_settings_username_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="password1">', Lang::getTxt('user_settings_password', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="password" name="password1" id="password1" size="40">
					<div class="smalltext">', Lang::getTxt('user_settings_password_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="password2">', Lang::getTxt('user_settings_again', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="password" name="password2" id="password2" size="40">
					<div class="smalltext">', Lang::getTxt('user_settings_again_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="email">', Lang::getTxt('user_settings_admin_email', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="email" name="email" id="email" value="', Maintenance::$context['email'], '" size="40">
					<div class="smalltext">', Lang::getTxt('user_settings_admin_email_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="server_email">', Lang::getTxt('user_settings_server_email', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" name="server_email" id="server_email" value="', Maintenance::$context['server_email'], '" size="40">
					<div class="smalltext">', Lang::getTxt('user_settings_server_email_info', file: 'Maintenance'), '</div>
				</dd>
			</dl>';

		if (Maintenance::$context['require_db_confirm']) {
			echo '
			<h2>', Lang::getTxt('user_settings_database', file: 'Maintenance'), '</h2>
			<p>', Lang::getTxt('user_settings_database_info', file: 'Maintenance'), '</p>

			<div class="lefttext">
				<input type="password" name="password3" size="30">
			</div>';
		}
	}

	/**
	 * Finalization page for installer.
	 */
	public static function finalize(): void
	{
		MaintenanceTemplate::warningsAndErrors();

		echo '
		<p>', Lang::getTxt('congratulations_help', file: 'Maintenance'), '</p>';

		MaintenanceTemplate::showLog();

		// Install directory still writable?
		if (Maintenance::$context['dir_still_writable']) {
			echo '
			<p><em>', Lang::getTxt('still_writable', file: 'Maintenance'), '</em></p>';
		}

		// Don't show the box if it's like 99% sure it won't work :P.
		if (Maintenance::$context['can_delete_script']) {
			echo '
			<label>
				<input type="checkbox" id="delete_self" onclick="doTheDelete();">
				<strong>', Lang::getTxt('delete_tool', ['SCRIPT' => basename(Maintenance::getSelf())]), !isset($_SESSION['temp_ftp']) ? ' ' . Lang::getTxt('delete_tool_maybe', file: 'Maintenance') : '', '</strong>
			</label>
			<script>
				function doTheDelete()
				{
					var theCheck = document.getElementById ? document.getElementById("delete_self") : document.all.delete_self;
					var tempImage = new Image();

					tempImage.src = "', Maintenance::getSelf(), '?delete=1&ts_" + (new Date().getTime());
					tempImage.width = 0;
					theCheck.disabled = true;
				}
			</script>';
		}

		echo '
			<p>', Lang::getTxt('go_to_your_forum', ['scripturl' => Config::$boardurl . '/index.php']), '</p>
			<br>
			', Lang::getTxt('good_luck', file: 'Maintenance');
	}
}
