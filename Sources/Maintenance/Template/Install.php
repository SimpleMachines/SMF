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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Template;
use SMF\Maintenance\TemplateInterface;

/**
 * Tempalte for Installer
 */
class Install implements TemplateInterface
{
    /**
     * Upper template for installer.
     */
	public static function upper(): void
	{
		if (Maintenance::$sub_template) {
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
                                    <input type="submit" id="contbutt" name="contbutt" value="', Lang::$txt['upgrade_continue'], '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['skip'])) {
				echo '
                                    <input type="submit" id="skip" name="skip" value="', Lang::$txt['upgrade_skip'], '" onclick="return submitThisOnce(this);" class="button">';
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
	public static function Welcome(): void
	{
		echo '
            <script src="https://www.simplemachines.org/smf/current-version.js?version=' . urlencode(SMF_VERSION) . '"></script>

            <p>', sprintf(Lang::$txt['install_welcome_desc'], SMF_VERSION), '</p>
            <div id="version_warning" class="noticebox hidden">
                <h3>', Lang::$txt['error_warning_notice'], '</h3>
                ', sprintf(Lang::$txt['error_script_outdated'], '<em id="smfVersion" style="white-space: nowrap;">??</em>', '<em id="yourVersion" style="white-space: nowrap;">' . SMF_VERSION . '</em>'), '
            </div>';

		// Oh no!
		if (!empty(Maintenance::$fatal_error) || count(Maintenance::$errors) > 0 || count(Maintenance::$warnings) > 0) {
			Template::warningsAndErrors();
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
	public static function CheckFilesWritable(): void
	{
		echo '
            <p>', Lang::$txt['ftp_setup_why_info'], '</p>
            <ul class="error_content">
                <li>', implode('</li>
                <li>', Maintenance::$context['failed_files']), '</li>
            </ul>';

		if (isset(Maintenance::$context['systemos'], Maintenance::$context['detected_path']) && Maintenance::$context['systemos'] == 'linux') {
			echo '
            <hr>
            <p>', Lang::$txt['chmod_linux_info'], '</p>
            <samp># chmod a+w ', implode(' ' . Maintenance::$context['detected_path'] . '/', Maintenance::$context['failed_files']), '</samp>';
		}

		// This is serious!
		if (!empty(Maintenance::$fatal_error) || count(Maintenance::$errors) > 0 || count(Maintenance::$warnings) > 0) {
			Template::warningsAndErrors();

			return;
		}

		echo '
            <hr>
            <p>', Lang::$txt['ftp_setup_info'], '</p>';

		if (!empty(Maintenance::$context['ftp_errors'])) {
			echo '
            <div class="error_message">
                ', Lang::$txt['error_ftp_no_connect'], '<br><br>
                <code>', implode('<br>', Maintenance::$context['ftp_errors']), '</code>
            </div>';
		}

		echo '
            <form action="', Maintenance::$context['form_url'], '" method="post">
                <dl class="settings">
                    <dt>
                        <label for="ftp_server">', Lang::$txt['ftp_server'], ':</label>
                    </dt>
                    <dd>
                        <div class="floatright">
                            <label for="ftp_port" class="textbox"><strong>', Lang::$txt['ftp_port'], ':&nbsp;</strong></label>
                            <input type="text" size="3" name="ftp_port" id="ftp_port" value="', Maintenance::$context['ftp']['port'], '">
                        </div>
                        <input type="text" size="30" name="ftp_server" id="ftp_server" value="', Maintenance::$context['ftp']['server'], '">
                        <div class="smalltext block">', Lang::$txt['ftp_server_info'], '</div>
                    </dd>
                    <dt>
                        <label for="ftp_username">', Lang::$txt['ftp_username'], ':</label>
                    </dt>
                    <dd>
                        <input type="text" size="30" name="ftp_username" id="ftp_username" value="', Maintenance::$context['ftp']['username'], '">
                        <div class="smalltext block">', Lang::$txt['ftp_username_info'], '</div>
                    </dd>
                    <dt>
                        <label for="ftp_password">', Lang::$txt['ftp_password'], ':</label>
                    </dt>
                    <dd>
                        <input type="password" size="30" name="ftp_password" id="ftp_password">
                        <div class="smalltext block">', Lang::$txt['ftp_password_info'], '</div>
                    </dd>
                    <dt>
                        <label for="ftp_path">', Lang::$txt['ftp_path'], ':</label>
                    </dt>
                    <dd>
                        <input type="text" size="30" name="ftp_path" id="ftp_path" value="', Maintenance::$context['ftp']['path'], '">
                        <div class="smalltext block">', Maintenance::$context['ftp']['path_msg'], '</div>
                    </dd>
                </dl>
                <div class="righttext buttons">
                    <input type="submit" value="', Lang::$txt['ftp_connect'], '" onclick="return submitThisOnce(this);" class="button">
                </div>
            </form>
            <a href="', Maintenance::$context['form_url'], '">', Lang::$txt['error_message_click'], '</a> ', Lang::$txt['ftp_setup_again'];
	}

    /**
     * Database Settings page for installer.
     */
	public static function DatabaseSettings(): void
	{
		echo '
            <p>', Lang::$txt['db_settings_info'], '</p>';

		Template::warningsAndErrors();

		echo '
            <dl class="settings">';

		// More than one database type?
		if (count(Maintenance::$context['databases']) > 1) {
			echo '
                <dt>
                    <label for="db_type_input">', Lang::$txt['db_settings_type'], ':</label>
                </dt>
                <dd>
                    <select name="db_type" id="db_type_input" onchange="toggleDBInput();">';

			foreach (Maintenance::$context['databases'] as $key => $db) {
				echo '
                        <option value="', $key, '"', isset($_POST['db_type']) && $_POST['db_type'] == $key ? ' selected' : '', '>', $db['name'], '</option>';
			}

			echo '
                    </select>
                    <div class="smalltext">', Lang::$txt['db_settings_type_info'], '</div>
                </dd>';
		} else {
			echo '
                <dd>
                    <input type="hidden" name="db_type" value="', Maintenance::$context['db']['type'], '">
                </dd>';
		}

		echo '
                <dt>
                    <label for="db_server_input">', Lang::$txt['db_settings_server'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="db_server" id="db_server_input" value="', Maintenance::$context['db']['server'], '" size="30">
                    <div class="smalltext">', Lang::$txt['db_settings_server_info'], '</div>
                </dd>
                <dt>
                    <label for="db_port_input">', Lang::$txt['db_settings_port'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="db_port" id="db_port_input" value="', Maintenance::$context['db']['port'], '">
                    <div class="smalltext">', Lang::$txt['db_settings_port_info'], '</div>
                </dd>
                <dt>
                    <label for="db_user_input">', Lang::$txt['db_settings_username'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="db_user" id="db_user_input" value="', Maintenance::$context['db']['user'], '" size="30">
                    <div class="smalltext">', Lang::$txt['db_settings_username_info'], '</div>
                </dd>
                <dt>
                    <label for="db_passwd_input">', Lang::$txt['db_settings_password'], ':</label>
                </dt>
                <dd>
                    <input type="password" name="db_passwd" id="db_passwd_input" value="', Maintenance::$context['db']['pass'], '" size="30">
                    <div class="smalltext">', Lang::$txt['db_settings_password_info'], '</div>
                </dd>
                <dt>
                    <label for="db_name_input">', Lang::$txt['db_settings_database'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="db_name" id="db_name_input" value="', empty(Maintenance::$context['db']['name']) ? 'smf' : Maintenance::$context['db']['name'], '" size="30">
                    <div class="smalltext">
                        ', Lang::$txt['db_settings_database_info'], '
                        <span id="db_name_info_warning">', Lang::$txt['db_settings_database_info_note'], '</span>
                    </div>
                </dd>
                <dt>
                    <label for="db_prefix_input">', Lang::$txt['db_settings_prefix'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="db_prefix" id="db_prefix_input" value="', Maintenance::$context['db']['prefix'], '" size="30">
                    <div class="smalltext">', Lang::$txt['db_settings_prefix_info'], '</div>
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
	public static function ForumSettings(): void
	{
		echo '
            <h3>', Lang::$txt['install_settings_info'], '</h3>';

		Template::warningsAndErrors();

		echo '
            <dl class="settings">
                <dt>
                    <label for="mbname_input">', Lang::$txt['install_settings_name'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="mbname" id="mbname_input" value="', Lang::$txt['install_settings_name_default'], '" size="65">
                    <div class="smalltext">', Lang::$txt['install_settings_name_info'], '</div>
                </dd>
                <dt>
                    <label for="boardurl_input">', Lang::$txt['install_settings_url'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="boardurl" id="boardurl_input" value="', Maintenance::$context['detected_url'], '" size="65">
                    <div class="smalltext">', Lang::$txt['install_settings_url_info'], '</div>
                </dd>
                <dt>
                    <label for="reg_mode">', Lang::$txt['install_settings_reg_mode'], ':</label>
                </dt>
                <dd>
                    <select name="reg_mode" id="reg_mode">
                        <optgroup label="', Lang::$txt['install_settings_reg_modes'], ':">
                            <option value="0" selected>', Lang::$txt['install_settings_reg_immediate'], '</option>
                            <option value="1">', Lang::$txt['install_settings_reg_email'], '</option>
                            <option value="2">', Lang::$txt['install_settings_reg_admin'], '</option>
                            <option value="3">', Lang::$txt['install_settings_reg_disabled'], '</option>
                        </optgroup>
                    </select>
                    <div class="smalltext">', Lang::$txt['install_settings_reg_mode_info'], '</div>
                </dd>
                <dt>', Lang::$txt['install_settings_compress'], ':</dt>
                <dd>
                    <input type="checkbox" name="compress" id="compress_check" checked>
                    <label for="compress_check">', Lang::$txt['install_settings_compress_title'], '</label>
                    <div class="smalltext">', Lang::$txt['install_settings_compress_info'], '</div>
                </dd>
                <dt>', Lang::$txt['install_settings_dbsession'], ':</dt>
                <dd>
                    <input type="checkbox" name="dbsession" id="dbsession_check" checked>
                    <label for="dbsession_check">', Lang::$txt['install_settings_dbsession_title'], '</label>
                    <div class="smalltext">', Maintenance::$context['test_dbsession'] ? Lang::$txt['install_settings_dbsession_info1'] : Lang::$txt['install_settings_dbsession_info2'], '</div>
                </dd>
                <dt>', Lang::$txt['install_settings_stats'], ':</dt>
                <dd>
                    <input type="checkbox" name="stats" id="stats_check" checked="checked">
                    <label for="stats_check">', Lang::$txt['install_settings_stats_title'], '</label>
                    <div class="smalltext">', Lang::$txt['install_settings_stats_info'], '</div>
                </dd>
                <dt>', Lang::$txt['force_ssl'], ':</dt>
                <dd>
                    <input type="checkbox" name="force_ssl" id="force_ssl"', Maintenance::$context['ssl_chkbx_checked'] ? ' checked' : '',
						Maintenance::$context['ssl_chkbx_protected'] ? ' disabled' : '', '>
                    <label for="force_ssl">', Lang::$txt['force_ssl_label'], '</label>
                    <div class="smalltext"><strong>', Lang::$txt['force_ssl_info'], '</strong></div>
                </dd>
            </dl>';

	}

    /**
     * Database Populate page for installer.
     */
	public static function DatabasePopulation(): void
	{
		echo '
            <p>', !empty(Maintenance::$context['was_refresh']) ? Lang::$txt['user_refresh_install_desc'] : Lang::$txt['db_populate_info'], '</p>';

		if (!empty(Maintenance::$context['sql_results'])) {
			echo '
            <ul>
                <li>', implode('</li><li>', Maintenance::$context['sql_results']), '</li>
            </ul>';
		}

		if (!empty(Maintenance::$context['failures'])) {
			echo '
            <div class="red">', Lang::$txt['error_db_queries'], '</div>
            <ul>';

			foreach (Maintenance::$context['failures'] as $line => $fail) {
				echo '
                <li><strong>', Lang::$txt['error_db_queries_line'], $line + 1, ':</strong> ', nl2br(htmlspecialchars($fail)), '</li>';
			}

			echo '
            </ul>';
		}

		echo '
            <p>', Lang::$txt['db_populate_info2'], '</p>';

			Template::warningsAndErrors();

		echo '
            <input type="hidden" name="pop_done" value="1">';
	}

    /**
     * Admin Account page for installer.
     */
	public static function AdminAccount(): void
	{
		echo '
            <p>', Lang::$txt['user_settings_info'], '</p>';

			Template::warningsAndErrors();

		echo '
            <dl class="settings">
                <dt>
                    <label for="username">', Lang::$txt['user_settings_username'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="username" id="username" value="', Maintenance::$context['username'], '" size="40">
                    <div class="smalltext">', Lang::$txt['user_settings_username_info'], '</div>
                </dd>
                <dt>
                    <label for="password1">', Lang::$txt['user_settings_password'], ':</label>
                </dt>
                <dd>
                    <input type="password" name="password1" id="password1" size="40">
                    <div class="smalltext">', Lang::$txt['user_settings_password_info'], '</div>
                </dd>
                <dt>
                    <label for="password2">', Lang::$txt['user_settings_again'], ':</label>
                </dt>
                <dd>
                    <input type="password" name="password2" id="password2" size="40">
                    <div class="smalltext">', Lang::$txt['user_settings_again_info'], '</div>
                </dd>
                <dt>
                    <label for="email">', Lang::$txt['user_settings_admin_email'], ':</label>
                </dt>
                <dd>
                    <input type="email" name="email" id="email" value="', Maintenance::$context['email'], '" size="40">
                    <div class="smalltext">', Lang::$txt['user_settings_admin_email_info'], '</div>
                </dd>
                <dt>
                    <label for="server_email">', Lang::$txt['user_settings_server_email'], ':</label>
                </dt>
                <dd>
                    <input type="text" name="server_email" id="server_email" value="', Maintenance::$context['server_email'], '" size="40">
                    <div class="smalltext">', Lang::$txt['user_settings_server_email_info'], '</div>
                </dd>
            </dl>';

		if (Maintenance::$context['require_db_confirm']) {
			echo '
            <h2>', Lang::$txt['user_settings_database'], '</h2>
            <p>', Lang::$txt['user_settings_database_info'], '</p>
    
            <div class="lefttext">
                <input type="password" name="password3" size="30">
            </div>';
		}
	}

    /**
     * Delete Installer page for installer.
     */
	public static function DeleteInstall(): void
	{
		echo '
		<p>', Lang::$txt['congratulations_help'], '</p>';

		Template::warningsAndErrors();

		// Install directory still writable?
		if (Maintenance::$context['dir_still_writable']) {
			echo '
            <p><em>', Lang::$txt['still_writable'], '</em></p>';
		}

		// Don't show the box if it's like 99% sure it won't work :P.
		if (Maintenance::$context['probably_delete_install']) {
			echo '
            <label>
                <input type="checkbox" id="delete_self" onclick="doTheDelete();">
                <strong>', Lang::$txt['delete_installer'], !isset($_SESSION['installer_temp_ftp']) ? ' ' . Lang::$txt['delete_installer_maybe'] : '', '</strong>
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
            <p>', sprintf(Lang::$txt['go_to_your_forum'], Config::$boardurl . '/index.php'), '</p>
            <br>
            ', Lang::$txt['good_luck'];
	}

}

?>