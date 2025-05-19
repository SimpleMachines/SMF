<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Themes\default;

use SMF\Config;
use SMF\Lang;
use SMF\Maintenance\Maintenance;
use SMF\Sapi;

/**
 * Template for Upgrader
 */
class UpgradeTemplate extends MaintenanceTemplate
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * Did we call the chmod template?
	 *
	 * @var bool True if we did, false otherwise.
	 */
	private static bool $chmod_called = false;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Upper template for upgrader.
	 */
	public static function upper(): void
	{
		echo '
			<form id="upform" action="', Maintenance::$context['form_action'] ??  Maintenance::getSelf() . '?' . Maintenance::setQueryString(), '" method="post">';
	}

	/**
	 * Lower template for upgrader.
	 */
	public static function lower(): void
	{
		if (!empty(Maintenance::$context['pause'])) {
			echo '
					<em>', Lang::getTxt('upgrade_incomplete', file: 'Maintenance'), '.</em><br>

					<h2 style="margin-top: 2ex;">', Lang::getTxt('upgrade_not_quite_done', file: 'Maintenance'), '</h2>
					<h3>
						', Lang::getTxt('upgrade_paused_overload', file: 'Maintenance'), '
					</h3>';
		}


		if (!empty(Maintenance::$context['continue']) || !empty(Maintenance::$context['skip']) || !empty(Maintenance::$context['try_again'])) {
			echo '
					<div class="floatright">';

			if (!empty(Maintenance::$context['continue'])) {
				echo '
						<input type="submit" id="contbutt" name="contbutt" value="', Lang::getTxt('action_continue', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['try_again'])) {
				echo '
						<input type="submit" id="try_again" name="try_again" value="', Lang::getTxt('error_message_try_again', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['skip'])) {
				echo '
						<input type="submit" id="skip" name="skip" value="', Lang::getTxt('action_skip', file: 'Maintenance'), '" onclick="return submitThisOnce(this);" class="button">';
			}

			echo '
					</div>';
		}

		echo '
			</form>';

		echo '
			<script>
				let countdown = 3;
				let dontSubmit = false;

				function doAutoSubmit()
				{
					if (countdown == 0 && !dontSubmit) {
						document.getElementById("upform").submit();
					} else if (countdown == -1) {
						return;
					}

					document.getElementById("contbutt").value = "', Lang::getTxt('action_continue', file: 'Maintenance'), ' (" + countdown + ")";
					countdown--;

					setTimeout("doAutoSubmit();", 1000);
				}
			</script>';

		// Are we on a pause?
		if (!empty(Maintenance::$context['pause'])) {
			echo '
			<script defer>
				window.onload = doAutoSubmit;
			</script>';
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
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				', Lang::getTxt('error_no_javascript', file: 'Maintenance'), '
				</div>
			</div>
			<script>
				document.getElementById(\'no_js_container\').classList.add(\'hidden\');
			</script>';

		echo '
			<script src="https://www.simplemachines.org/smf/current-version.js?version=' . SMF_VERSION . '"></script>
			<p><strong>', Lang::getTxt('upgrade_ready_proceed', ['SMF_VERSION' => SMF_VERSION]), '</strong></p>

			<div id="version_warning" class="noticebox hidden">
				<h3>', Lang::getTxt('error_warning_notice', file: 'Maintenance'), '</h3>
				', Lang::getTxt('upgrade_warning_out_of_date', ['SMF_VERSION' => SMF_VERSION, 'url' => 'https://www.simplemachines.org']), '
			</div>';

		MaintenanceTemplate::warningsAndErrors();

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
				<h3>', Lang::getTxt('error_warning_notice', file: 'Maintenance'), '</h3>
				', Lang::getTxt('upgrade_warning_lots_data', file: 'Maintenance'), '
			</div>';
		}

		// Paths are incorrect?
		echo '
			<div class="errorbox', (file_exists(Maintenance::$theme_dir . '/scripts/script.js') ? ' hidden' : ''), '" id="js_script_missing_error">
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				', Lang::getTxt('upgrade_error_script_js', ['url' => 'https://download.simplemachines.org/?tools']), '
			</div>';

		// Is there someone already doing this?
		if (
			!empty(Maintenance::$context['user']['id'])
			&& (
				time() - Maintenance::$context['started'] < 72600
				|| time() - Maintenance::$context['updated'] < 3600
			)
		) {
			echo '
			<div class="errorbox">
				<h3>', Lang::getTxt('upgrade_warning', file: 'Maintenance'), '</h3>
				<p>', Lang::getTxt('upgrade_time_user', Maintenance::$context['user']), '</p>
				<p>', self::timeAgo(Maintenance::$context['started'], 'upgrade_time'), '</p>
				<p>', self::timeAgo(Maintenance::$context['updated'], 'upgrade_time_updated'), '</p>';

			if (time() - Maintenance::$context['updated'] < 600) {
				echo '
				<p>', Lang::getTxt('upgrade_run_script', file: 'Maintenance'), ' ', Maintenance::$context['user']['name'], ' ', Lang::getTxt('upgrade_run_script2', file: 'Maintenance'), '</p>';
			}

			if ((time() - Maintenance::$context['updated']) > Maintenance::$tool->inactive_timeout) {
				echo '
				<p>', Lang::getTxt('upgrade_run', file: 'Maintenance'), '</p>';
			} elseif (Maintenance::$tool->inactive_timeout > 120) {
				echo '
				<p>', Lang::getTxt('upgrade_script_timeout_minutes', ['name' => Maintenance::$context['user']['name'], 'timeout' => round(Maintenance::$tool->inactive_timeout / 60, 1)]), '</p>';
			} else {
				echo '
				<p>', Lang::getTxt('upgrade_script_timeout_seconds', ['name' => Maintenance::$context['user']['name'], 'timeout' => Maintenance::$tool->inactive_timeout]), '</p>';
			}

			echo '
			</div>';
		}

		echo '
			<p>
				<strong>', Lang::getTxt('upgrade_admin_login', file: 'Maintenance'), ' ', Maintenance::$disable_security ? Lang::getTxt('upgrade_admin_disabled', file: 'Maintenance') : '', '</strong>
				</br>
				', Lang::getTxt('upgrade_sec_login', file: 'Maintenance'), '
			</p>
			<dl class="settings adminlogin">
				<dt>
					<label for="user"', Maintenance::$disable_security ? ' disabled' : '', '>', Lang::getTxt('upgrade_username', file: 'Maintenance'), '</label>
				</dt>
				<dd>
					<input type="text" name="user" value="', !empty(Maintenance::$context['username']) ? Maintenance::$context['username'] : '', '"', Maintenance::$disable_security ? ' disabled' : '', '>';

		if (!empty($upcontext['username_incorrect'])) {
			echo '
					<div class="smalltext red">', Lang::getTxt('upgrade_wrong_username', file: 'Maintenance'), '</div>';
		}

		echo '
				</dd>
				<dt>
					<label for="passwrd"', Maintenance::$disable_security ? ' disabled' : '', '>', Lang::getTxt('upgrade_password', file: 'Maintenance'), '</label>
				</dt>
				<dd>
					<input type="password" name="passwrd" value=""', Maintenance::$disable_security ? ' disabled' : '', '>';

		if (!empty($upcontext['password_failed'])) {
			echo '
					<div class="smalltext red">', Lang::getTxt('upgrade_wrong_password', file: 'Maintenance'), '</div>';
		}

		echo '
				</dd>';

		// Can they continue?
		if (
			!empty(Maintenance::$context['user']['id'])
			&& time() - (Maintenance::$context['user']['updated'] ?? 0) >= Maintenance::$tool->inactive_timeout
			&& (Maintenance::$context['user']['step'] ?? 0) > 1
		) {
			echo '
				<dd>
					<label for="cont"><input type="checkbox" id="cont" name="cont" checked>', Lang::getTxt('upgrade_continue_step', file: 'Maintenance'), '</label>
				</dd>';
		}

		echo '
			</dl>
			<p class="smalltext">
				', Lang::getTxt('upgrade_bypass', file: 'Maintenance'), '
			</p>';

		if (!empty(Maintenance::$context['login_token_var'])) {
			echo '
			<input type="hidden" name="', Maintenance::$context['login_token_var'], '" value="', Maintenance::$context['login_token'], '">';
		}

		echo '
			<input type="hidden" name="login_attempt" id="login_attempt" value="1">
			<input type="hidden" name="js_support" id="js_support" value="0">';

		// Say we want the continue button!
		Maintenance::$context['continue'] = !empty(Maintenance::$context['user']['id']) && time() - Maintenance::$context['updated'] < Maintenance::$tool->inactive_timeout ? 2 : 1;

		// This defines whether javascript is going to work elsewhere :D
		echo '
			<script>
				if (\'XMLHttpRequest\' in window && document.getElementById(\'js_support\'))
					document.getElementById(\'js_support\').value = 1;

				// Latest version?
				function smfCurrentVersion()
				{
					var smfVer, yourVer;

					if (!(\'smfVersion\' in window)) {
						return;
					}

					window.smfVersion = window.smfVersion.replace(/SMF\s?/g, \'\');

					smfVer = document.getElementById(\'smfVersion\');
					yourVer = document.getElementById(\'yourVersion\');

					smfVer.innerHTML = window.smfVersion;

					var currentVersion = getInnerHTML(yourVer);

					if (currentVersion < window.smfVersion) {
						document.getElementById(\'version_warning\').classList.remove(\'hidden\');
					}
				}

				addEventListener("load", smfCurrentVersion);

				// This checks that the script file even exists!
				if (typeof(smfSelectText) == \'undefined\') {
					document.getElementById(\'js_script_missing_error\').classList.remove(\'hidden\');
				}
			</script>';
	}

	/**
	 * Upgrade options template.
	 */
	public static function upgradeOptions(): void
	{
		echo '
		<h3>', Lang::getTxt('upgrade_areyouready', file: 'Maintenance'), '</h3>';

		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		echo '
		<dl class="settings">
			<dt>
				<label for="backup">
					', Lang::getTxt('upgrade_backup_table', ['backup_' . Maintenance::$context['db_prefix']], file: 'Maintenance'), '
				</label>
				<br>
				<span class="smalltext">', Lang::getTxt(empty(Maintenance::$context['backup_recommended']) ? 'upgrade_backup_already_exists' : 'upgrade_recommended', file: 'Maintenance'), '</span>
			</dt>
			<dd>
				<input type="checkbox" name="backup" id="backup" value="1" ', empty(Maintenance::$context['backup_recommended']) ? '' : ' checked', '>
			</dd>
			<dt>
				<label for="maint">
					', Lang::getTxt('upgrade_maintenance', file: 'Maintenance'), '
				</label>
				<span class="smalltext">(<a href="javascript:void(0)" onclick="Array.from(document.getElementsByClassName(\'mainmess\')).forEach((element) => element.classList.toggle(\'hidden\'))">', Lang::getTxt('upgrade_customize', file: 'Maintenance'), '</a>)</span>
			</dt>
			<dd>
				<input type="checkbox" name="maint" id="maint" value="1" checked>
			</dd>
			<dt class="mainmess hidden">
				<label for="mainmessage">
					', Lang::getTxt('upgrade_maintenance_title', file: 'Maintenance'), '
				</label>
			</dt>
			<dd class="mainmess hidden">
				<input type="text" name="maintitle" size="30" value="', Maintenance::$context['message_title'], '">
			</dd>
			<dt class="mainmess hidden">
				<label for="mainmessage">
					', Lang::getTxt('upgrade_maintenance_message', file: 'Maintenance'), '
				</label>
			</dt>
			<dd class="mainmess hidden">
				<textarea name="mainmessage" rows="3" cols="50">', Maintenance::$context['message_body'], '</textarea>
			</dd>
			<dt>
				<label for="debug">
					', Lang::getTxt('upgrade_debug_info', file: 'Maintenance'), '
				</label>
			</dt>
			<dd>
				<input type="checkbox" name="debug" id="debug" value="1">
			</dd>
			<dt>
				<label for="empty_error">
					', Lang::getTxt('upgrade_empty_errorlog', file: 'Maintenance'), '
				</label>
			</dt>
			<dd>
				<input type="checkbox" name="empty_error" id="empty_error" value="1">
			</dd>';

		if (!empty(Maintenance::$context['karma_installed']['good']) || !empty(Maintenance::$context['karma_installed']['bad'])) {
			echo '
			<dt>
				<label for="delete_karma">
					', Lang::getTxt('upgrade_delete_karma', file: 'Maintenance'), '
				</label>
			</dt>
			<dd>
				<input type="checkbox" name="delete_karma" id="delete_karma" value="1">
			</dd>';
		}

		// If attachment step has been run previously, offer an option to do it again.
		// Helpful if folks had improper attachment folders specified previously.
		if (!empty(Maintenance::$context['attachment_conversion'])) {
			echo '
			<dt>
				<label for="reprocess_attachments">
					', Lang::getTxt('upgrade_reprocess_attachments', file: 'Maintenance'), '
				</label>
			</dt>
			<dd>
				<input type="checkbox" name="reprocess_attachments" id="reprocess_attachments" value="1">
			</dd>';
		}

		echo '
			<dt>
				<label for="stat">
					', Lang::getTxt('upgrade_stats_collection', file: 'Maintenance'), '
				</label>
				<br>
				<span class="smalltext">', Lang::getTxt('upgrade_stats_info', ['url' => 'https://www.simplemachines.org/about/stats.php']), '</span>
			</dt>
			<dd>
				<input type="checkbox" name="stats" id="stats" value="1"', Maintenance::$context['sm_stats_configured'] ? '' : ' checked="checked"', '>
			</dd>
			<dt>
				<label for="migrateSettings">
					', Lang::getTxt('upgrade_migrate_settings_file', file: 'Maintenance'), '
				</label>
			</dt>
			<dd>
				<input type="checkbox" name="migrateSettings" id="migrateSettings" value="1"', empty(Maintenance::$context['migrate_settings_recommended']) ? '' : ' checked', '>
			</dd>
		</dl>
		<input type="hidden" name="upcont" value="1">';
	}

	/**
	 * Backup database template.
	 */
	public static function backupDatabase(): void
	{
		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		// Show the continue button.
		Maintenance::$context['continue'] = true;

		echo '
			<h3>', Lang::getTxt('upgrade_wait', file: 'Maintenance'), '</h3>
			<input type="hidden" name="backup_done" id="backup_done" value="0">
			<strong>', Lang::getTxt('upgrade_completedtables_outof', Maintenance::$context), '</strong>
			<div id="debug_section">
				<span id="debuginfo"></span>
			</div>
			<h3 id="current_tab">
				', Lang::getTxt('upgrade_current_table', file: 'Maintenance'), ' &quot;<span id="current_table">', Maintenance::$context['cur_table_name'], '</span>&quot;
			</h3>
			<p id="commess" class="', Maintenance::$context['cur_table_num'] == Maintenance::$context['table_count'] ? 'inline_block' : 'hidden', '">', Lang::getTxt('upgrade_backup_complete', file: 'Maintenance'), '</p>
			<div class="errorbox" id="errorbox" style="display: none;">
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				<span>', Lang::getTxt('error_unknown', file: 'Maintenance'), '</span>
			</div>';

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
							iStepProgress = parseInt(json.data.substep_progress);

							// Update the page.
							//document.getElementById("tab_done").innerHTML = iLastTableIndex;
							document.getElementById("current_table").innerHTML = sCurrentTableName;

							updateProgress(iLastTableIndex, iTotalTables, iStepWeight, iStepProgress);

							if (isDebug) {
								setOuterHTML(document.getElementById("debuginfo"), "<br>', Lang::getTxt('upgrade_completed_table', file: 'Maintenance'), ' &quot;" + sCurrentTableName + "&quot;.<span id=\'debuginfo\'><" + "/span>");

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
			</script>';
	}

	/**
	 * Migrations template.
	 */
	public static function migrations(): void
	{
		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		// Continue please!
		Maintenance::$context['continue'] = true;
		Maintenance::$context['try_again'] = true;

		self::showStepWithSubSteps('migration', 'database_done');
	}

	/**
	 * Cleanup template.
	 */
	public static function cleanup(): void
	{
		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		// Show the continue button.
		Maintenance::$context['continue'] = true;

		self::showStepWithSubSteps('cleanup', 'cleanup_done');
	}

	/**
	 * Finalization template.
	 */
	public static function finalize(): void
	{
		Maintenance::$context['continue'] = true;

		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		echo '
				<h3>', Lang::getTxt('upgrade_done', ['boardurl' => Config::$boardurl]), '</h3>';

		if (!empty(Maintenance::$context['can_delete_script'])) {
			echo '
				<p>
					<label>
						<input type="checkbox" id="delete_self" onclick="doTheDelete();">
						<strong>', Lang::getTxt('delete_tool', ['SCRIPT' => basename(Maintenance::getSelf())]), !isset($_SESSION['temp_ftp']) ? ' ' . Lang::getTxt('delete_tool_maybe', file: 'Maintenance') : '', '</strong>
					</label>
				</p>
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

		// Show Upgrade time in debug mode when we completed the upgrade process totally
		if (isset(Maintenance::$context['upgrade_completed_time'])) {
			echo '
				<p>' . Maintenance::$context['upgrade_completed_time'] . '</p>';
		}

		echo '
				<p>
					', Lang::getTxt('upgrade_problems', ['url' => 'https://www.simplemachines.org'], file: 'Maintenance'), '
					<br>
					', Lang::getTxt('upgrade_luck', file: 'Maintenance'), '<br>
					Simple Machines
				</p>';
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Shows the HTML for a step that has substeps.
	 */
	protected static function showStepWithSubSteps(string $type, string $done_param): void
	{
		echo '
			<h3>', Lang::getTxt('upgrade_executing_substeps', ['type' => $type], file: 'Maintenance'), '</h3>
			<h4><em>', Lang::getTxt('upgrade_please_be_patient', file: 'Maintenance'), '</em></h4>
			<input type="hidden" name="', $done_param, '" id="', $done_param, '" value="0">
			<div id="debug_section">
				<span id="debuginfo"></span>
			</div>';

		echo '
			<h3 id="current_tab">',
			Lang::getTxt(
				'upgrade_current_substep',
				[
					'substep' => '<span id="current_substep">' . (Maintenance::$context['current_substep'] ?? '') . '</span>',
				],
				file: 'Maintenance',
			),
			'</h3>';

		echo '
			<strong>',
			Lang::getTxt(
				'upgrade_substep_progress',
				[
					'substep_done' => '<span id="substep_done">' . Maintenance::getCurrentSubStep() . '</span>',
					'total_substeps' => Maintenance::$total_substeps,
					'type' => $type,
				],
				file: 'Maintenance',
			),
			'</strong>';

		echo '
			<p id="commess" class="', Maintenance::getCurrentSubStep() == Maintenance::$total_substeps ? 'inline_block' : 'hidden', '">', Lang::getTxt('upgrade_step_complete', ['step' => Lang::getTxt('upgrade_step_' . $type, file: 'Maintenance')], file: 'Maintenance'), '</p>';

		echo '
			<div class="errorbox" id="errorbox" style="display: none;">
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				<span>', Lang::getTxt('error_unknown', file: 'Maintenance'), '</span>
			</div>';

		// Pour me a cup of javascript.
		echo '
			<script>
				const iTotalSubSteps = ', Maintenance::$total_substeps, ';
				const iStepWeight = ', Maintenance::$context['step_weight'], ';
				let iCurrentSubStep  = ', Maintenance::getCurrentSubStep(), ';
				let iCurrentStart  = ', Maintenance::getCurrentStart(), ';
				let iSubStepProgress = 0;
				let sCurrentSubStepName = "";
				let sNextSubStepName = "";

				function getNextSubstep()
				{
					const url = "' . Maintenance::getSelf() . '?' . Maintenance::setQueryString() . '&json"
						.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
						.replace(/start=\d+/, "start=" + iCurrentStart);

					fetch(url, {
						method: "GET",
						credentials: "include",
					}).then(function(response){
						if (response.headers.get("content-type").includes("json")) {
							response.json().then(function(json) {
								if (json.success != true) {
									document.getElementById("errorbox").style.display = "";

									if (document.getElementById("try_again")) {
										document.getElementById("try_again").style.display = "";
									}

									document.getElementById("upform").action = document.getElementById("upform").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);

									return;
								}

								sCurrentSubStepName = json.data.name;
								sNextSubStepName = json.data.next ?? "";
								iCurrentSubStep = parseInt(json.data.substep);
								iCurrentStart = parseInt(json.data.start);
								iSubStepProgress = iCurrentSubStep / iTotalSubSteps;

								// Update the page.
								document.getElementById("substep_done").innerHTML = iCurrentSubStep;
								document.getElementById("current_substep").innerHTML = sCurrentSubStepName;

								// Hold up, we caught a error.
								if (true == json.data.failed) {
									document.getElementById("errorbox").style.display = "";

									if (document.getElementById("try_again")) {
										document.getElementById("try_again").style.display = "";
									}

									document.getElementById("upform").action = document.getElementById("upform").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);

									if (isDebug) {
										document.getElementById("errorbox").getElementsByTagName("span")[0].innerHTML = json.debug.msg + "<br>" + json.debug.file + ":" + json.debug.line;
									}

									return;
								}

								updateProgress(iCurrentSubStep, iTotalSubSteps, iStepWeight, iSubStepProgress);

								if (isDebug) {
									setOuterHTML(document.getElementById("debuginfo"), "<br>', Lang::getTxt('upgrade_completed_substep', file: 'Maintenance'), ' &quot;" + sCurrentSubStepName + "&quot;.<span id=\'debuginfo\'><" + "/span>");

									if (document.getElementById("debug_section").scrollHeight) {
										document.getElementById("debug_section").scrollTop = document.getElementById("debug_section").scrollHeight
									}
								}

								// Are we done yet?
								if (iCurrentSubStep == iTotalSubSteps) {
									document.getElementById("commess").classList.remove("hidden");
									document.getElementById("current_substep").classList.add("hidden");
									document.getElementById("contbutt").disabled = 0;
									document.getElementById("' . $done_param . '").value = 1;

									setTimeout("doAutoSubmit();", 1000);
								} else {
									getNextSubstep();
								}
							}).catch(function(error) {
								console.error("Parse Error:", error);

								document.getElementById("errorbox").style.display = "";
								if (isDebug) {
									if (sNextSubStepName.length > 0) {
										document.getElementById("current_substep").innerHTML = sNextSubStepName;
									}

									document.getElementById("errorbox").getElementsByTagName("span")[0].innerText = error;

									if (document.getElementById("try_again")) {
										document.getElementById("try_again").style.display = "";
									}

									document.getElementById("upform").action = document.getElementById("upform").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);
								}
							})
						}
						else {
							response.text().then(function(msg) {
								console.error("Response Error");

								document.getElementById("errorbox").style.display = "";
								if (isDebug) {
									if (sNextSubStepName.length > 0) {
										document.getElementById("current_substep").innerHTML = sNextSubStepName;
									}
									document.getElementById("errorbox").getElementsByTagName("span")[0].outerHTML = msg;

									if (document.getElementById("try_again")) {
										document.getElementById("try_again").style.display = "";
									}

									document.getElementById("upform").action = document.getElementById("upform").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);
								}
							});
						}
					}).catch(function(error) {
						console.error("Fetch Error:", error);

						document.getElementById("errorbox").style.display = "";
						if (isDebug) {
							if (sNextSubStepName.length > 0) {
								document.getElementById("current_substep").innerHTML = sNextSubStepName;
							}

							document.getElementById("errorbox").getElementsByTagName("span")[0].innerText = error;

							if (document.getElementById("try_again")) {
								document.getElementById("try_again").style.display = "";
							}

							document.getElementById("upform").action = document.getElementById("upform").action
								.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
								.replace(/start=\d+/, "start=" + iCurrentStart);
						}
					});
				}

				// Lets try to let the browser handle this.
				window.addEventListener("load", (event) => {
					if (document.getElementById("try_again")) {
						document.getElementById("try_again").style.display = "none";
					}

					document.getElementById("contbutt").disabled = 1;

					getNextSubstep();
				});
			</script>';
	}

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
		if (
			empty(Maintenance::$context['chmod']['files'])
			&& empty(Maintenance::$context['chmod']['ftp_error'])
		) {
			return;
		}

		// Was it a problem with Windows?
		if (
			!empty(Maintenance::$context['chmod']['ftp_error'])
			&& Maintenance::$context['chmod']['ftp_error'] == 'total_mess'
		) {
			echo '
			<div class="error">
				<p>', Lang::getTxt('upgrade_writable_files', file: 'Maintenance'), '</p>
				<ul class="error_content">
					<li>' . implode('</li>
					<li>', Maintenance::$context['chmod']['files']) . '</li>
				</ul>
			</div>';

			return false;
		}

		echo '
		<div class="panel">
			<h2>', Lang::getTxt('upgrade_ftp_login', file: 'Maintenance'), '</h2>
			<h3>', Lang::getTxt('upgrade_ftp_perms', file: 'Maintenance'), '</h3>
			<script>
				function warning_popup()
				{
					popup = window.open(\'\',\'popup\',\'height=150,width=400,scrollbars=yes\');
					var content = popup.document;
					content.write(\'<!DOCTYPE html>\n\');
					content.write(\'<html', Lang::getTxt('lang_rtl', file: 'Maintenance') == '1' ? ' dir="rtl"' : '', '>\n\t<head>\n\t\t<meta name="robots" content="noindex">\n\t\t\');
					content.write(\'<title>', Lang::getTxt('upgrade_ftp_warning', file: 'Maintenance'), '</title>\n\t\t<link rel="stylesheet" href="', Maintenance::$theme_url, '/css/index.css">\n\t</head>\n\t<body id="popup">\n\t\t\');
					content.write(\'<div class="windowbg description">\n\t\t\t<h4>', Lang::getTxt('upgrade_ftp_files', file: 'Maintenance'), '</h4>\n\t\t\t\');
					content.write(\'<p>', implode('<br>\n\t\t\t', Maintenance::$context['chmod']['files']), '</p>\n\t\t\t\');';

		if (Sapi::isOS([Sapi::OS_LINUX, Sapi::OS_MAC])) {
			echo '
					content.write(\'<hr>\n\t\t\t\');
					content.write(\'<p>', Lang::getTxt('upgrade_ftp_shell', file: 'Maintenance'), '</p>\n\t\t\t\');
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
				<p>', Lang::getTxt('upgrade_ftp_error', file: 'Maintenance'), '<p>
				<code>', Maintenance::$context['chmod']['ftp_error'], '</code>
			</div>';
		}

		echo '
			<dl class="settings">
				<dt>
					<label for="ftp_server">', Lang::getTxt('ftp_server', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<div class="floatright">
						<label for="ftp_port" class="textbox"><strong>', Lang::getTxt('ftp_port', file: 'Maintenance'), ':</strong></label>
						<input type="text" size="3" name="ftp_port" id="ftp_port" value="', Maintenance::$context['chmod']['port'] ?? '21', '">
					</div>
					<input type="text" size="30" name="ftp_server" id="ftp_server" value="', Maintenance::$context['chmod']['server'] ?? 'localhost', '">
					<div class="smalltext">', Lang::getTxt('ftp_server_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="ftp_username">', Lang::getTxt('ftp_username', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" size="30" name="ftp_username" id="ftp_username" value="', Maintenance::$context['chmod']['username'] ?? '', '">
					<div class="smalltext">', Lang::getTxt('ftp_username_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="ftp_password">', Lang::getTxt('ftp_password', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="password" size="30" name="ftp_password" id="ftp_password">
					<div class="smalltext">', Lang::getTxt('ftp_password_info', file: 'Maintenance'), '</div>
				</dd>
				<dt>
					<label for="ftp_path">', Lang::getTxt('ftp_path', file: 'Maintenance'), ':</label>
				</dt>
				<dd>
					<input type="text" size="30" name="ftp_path" id="ftp_path" value="', Maintenance::$context['chmod']['path'] ?? '', '">
					<div class="smalltext">', !empty(Maintenance::$context['chmod']['path']) ? Lang::getTxt('ftp_path_found_info', file: 'Maintenance') : Lang::getTxt('ftp_path_info', file: 'Maintenance'), '</div>
				</dd>
			</dl>

			<div class="righttext buttons">
				<input type="submit" value="', Lang::getTxt('ftp_connect', file: 'Maintenance'), '" class="button">
			</div>
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
