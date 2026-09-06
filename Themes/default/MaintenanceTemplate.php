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

namespace SMF\Themes\default;

use SMF\Lang;
use SMF\Maintenance\Maintenance;
use SMF\Utils;

/**
 * Base template for Maintenance scripts.
 */
abstract class MaintenanceTemplate
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Header template.
	 */
	public static function header(): void
	{
		echo '<!DOCTYPE html>
	<html', Lang::getTxt('lang_rtl', file: 'General') == '1' ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Lang::getTxt('lang_character_set', file: 'General') ?: 'UTF-8', '">
		<meta name="robots" content="noindex">
		<title>', Maintenance::$tool->getPageTitle(), '</title>
		<link rel="stylesheet" href="', Maintenance::$theme_url, '/css/variables.css?' . Utils::$context['started'] . '">
		<link rel="stylesheet" href="', Maintenance::$theme_url, '/css/index.css?' . Utils::$context['started'] . '">
		<link rel="stylesheet" href="', Maintenance::$theme_url, '/css/maintenance.css?' . Utils::$context['started'] . '">', Lang::getTxt('lang_rtl', file: 'General') == '1' ? '
		<link rel="stylesheet" href="' . Maintenance::$theme_url . '/css/rtl.css?' . Utils::$context['started'] . '">' : '', '
		<script src="', Maintenance::$theme_url, '/scripts/jquery-' . JQUERY_VERSION . '.min.js"></script>
		<script src="', Maintenance::$theme_url, '/scripts/script.js?' . Utils::$context['started'] . '"></script>
		<script>
			const smf_scripturl = \'', Maintenance::getSelf(), '\';
			const smf_charset = \'UTF-8\';
			const allow_xhjr_credentials = true;
			const isDebug = ', Maintenance::$tool->isDebug() ? 'true' : 'false', ';
			const timeStarted = ', Utils::$context['started'], ';
			let startPercent = ', Maintenance::$overall_percent, ';
		</script>
	</head>
	<body>
		<div id="footerfix">
		<header id="header" class="content_wrapper">
			<h1 class="forumtitle">', Maintenance::$tool->getScriptName(), '</h1>
			<img id="smflogo" src="', Maintenance::$theme_url, '/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
		</header>
		<div id="wrapper" class="content_wrapper">';

		// Have we got a language drop down - if so do it on the first step only.
		if (!empty(Maintenance::$languages) && \count(Maintenance::$languages) > 1 && Maintenance::getCurrentStep() == 0) {
			echo '
			<div id="inner_wrap">
				<div class="news">
					<form action="', Maintenance::getSelf(), '" method="get">
						<label for="maintenance_language">', Lang::getTxt('maintenance_language', file: 'Maintenance'), ':</label>
						<select id="maintenance_language" name="lang_file" onchange="location.href = \'', Maintenance::getSelf(), '?lang_file=\' + this.options[this.selectedIndex].value;">';

			foreach (Maintenance::$languages as $lang => $name) {
				echo '
							<option', isset($_SESSION['lang_file']) && $_SESSION['lang_file'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';
			}

			echo '
						</select>
						<noscript><input type="submit" value="', Lang::getTxt('action_set', file: 'Maintenance'), '" class="button"></noscript>
					</form>
				</div><!-- .news -->
				<hr class="clear">
			</div><!-- #inner_wrap -->';
		}

		echo '
			<div id="content_section">
				<div id="main_content_section">
					<div id="main_steps">
						<h2>', Lang::getTxt('maintenance_progress', file: 'Maintenance'), '</h2>
						<ul class="steps_list">';

		if (Maintenance::$tool->hasSteps()) {
			foreach (Maintenance::$tool->getSteps() as $num => $step) {
				echo '
							<li', $num == Maintenance::getCurrentStep() ? ' class="stepcurrent"' : '', '>', Lang::getTxt('maintenance_step', file: 'Maintenance'), ' ', $step->getID(), ': ', $step->getName(), '</li>';
			}
		}

		echo '
						</ul>
					</div>
					<div id="install_progress">
						<div id="progress_bar" class="progress_bar progress_green">
							<h3>' . Lang::getTxt('maintenance_overall_progress', file: 'Maintenance'), '</h3>
							<span id="overall_text">', Maintenance::$overall_percent, '%</span>
							<div id="overall_progress" class="bar" style="width: ', Maintenance::$overall_percent, '%;"></div>
						</div>';

		if (Maintenance::$total_substeps !== null) {
			echo '
						<div id="progress_bar_step" class="progress_bar progress_yellow">
							<h3>', Lang::getTxt('maintenance_substep_progress', file: 'Maintenance'), '</h3>
							<div id="step_progress" class="bar" style="width: ', Maintenance::getSubStepProgress(), '%;"></div>
							<span id="step_text">', Maintenance::getSubStepProgress(), '%</span>
						</div>';
		}

		echo '
						<div id="substep_bar_div" class="progress_bar ', Maintenance::$total_items !== null ? '' : 'hidden', '">
							<h3 id="start_name">', trim(strtr(Maintenance::$item_name, ['.' => ''])), '</h3>
							<div id="substep_progress" class="bar" style="width: ', Maintenance::getItemsProgress(), '%;"></div>
							<span id="substep_text">', Maintenance::getItemsProgress(), '%</span>
						</div>

						<div class="smalltext time_elapsed">
							', Lang::getTxt('maintenance_time_elapsed', file: 'Maintenance'), '
							<time id="time_elapsed">', Maintenance::getTimeElapsed(), '</time>
						</div>
					</div>
					<div id="main_screen" class="clear">
						<h2>', Maintenance::$tool->getStepTitle(), '</h2>
						<div class="panel">';

	}

	/**
	 * Footer template.
	 */
	public static function footer(): void
	{
		echo '
						</div><!-- .panel -->
					</div><!-- #main_screen -->
				</div><!-- #main_content_section -->
			</div><!-- #content_section -->
		</div><!-- #wrapper -->
		</div><!-- #footerfix -->
		<div id="footer">
			<ul>
				<li class="copyright"><a href="https://www.simplemachines.org/" title="Simple Machines Forum" target="_blank" rel="noopener">' . SMF_FULL_VERSION . ' &copy; ' . SMF_SOFTWARE_YEAR . ', Simple Machines</a></li>
			</ul>
		</div>
		<script>
			// This function dynamically updates the step progress bar - and overall one as required.
			function updateProgress(current, max, step_weight, step_progress)
			{
				// What out the actual percent.
				const width = parseInt((current / max) * 100);
				if (document.getElementById("step_progress")) {
					document.getElementById("step_progress").style.width = width + "%";
					setInnerHTML(document.getElementById("step_text"), width + "%");
				}

				if (step_weight && document.getElementById("overall_progress")) {
					step_progress = step_progress ?? 0;
					const overall_progress = step_weight * ((100 - step_progress) / 100);
					const overall_width = parseInt(startPercent + width * (overall_progress / 100));

					document.getElementById("overall_progress").style.width = overall_width + "%";
					setInnerHTML(document.getElementById("overall_text"), overall_width + "%");
				}
			}

			// This function dynamically updates the item progress bar.
			function updateItemsProgress(current, max)
			{
				// What out the actual percent.
				const width = parseInt((current / max) * 100);

				if (document.getElementById("item_progress")) {
					document.getElementById("item_progress").style.width = width + "%";
					setInnerHTML(document.getElementById("item_text"), width + "%");
				}
			}

			// This function dynamically updates the "time elapsed" counter.
			function updateTimeElapsed()
			{
				const elapsed = (Date.now() / 1000) - timeStarted;

				const s = elapsed % 60;
				const m = Math.floor(elapsed / 60) % 60;
				const h = Math.floor(elapsed / 3600);

				if (document.getElementById("time_elapsed")) {
					document.getElementById("time_elapsed").textContent = ((h > 0) ? ((h < 10) ? "0" + h.toString() : h.toString()) + ":" : "") + ((m < 10) ? "0" + m.toString() : m.toString()) + ":" + ((s < 10) ? "0" + s.toString() : s.toString());
				}
			}
		</script>
	</body>
	</html>';

	}

	/**
	 * A simple errors display.
	 */
	public static function warningsAndErrors(): void
	{
		// Errors are very serious..
		if (!empty(Maintenance::$fatal_error)) {
			echo '
			<div class="errorbox">
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				', Maintenance::$fatal_error, '
			</div>';
		} elseif (!empty(Maintenance::$errors)) {
			echo '
			<div class="errorbox">
				<h3>', Lang::getTxt('critical_error', file: 'Maintenance'), '</h3>
				', implode('
				', Maintenance::$errors), '
			</div>';
		}
		// A warning message?
		elseif (!empty(Maintenance::$warnings)) {
			echo '
			<div class="errorbox">
				<h3>', Lang::getTxt('warning', file: 'Maintenance'), '</h3>
				', implode('
				', Maintenance::$warnings), '
			</div>';
		}
	}

	/**
	 * Shows a fatal error message if we are missing language files.
	 */
	public static function missingLanguages(): void
	{
		// Let's not cache this message, eh?
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('cache-control: no-cache');

		echo '<!DOCTYPE html>
<html>
	<head>
		<title>SMF Installer: Error!</title>
		<style>
			body {
				font-family: sans-serif;
				max-width: 700px; }

			h1 {
				font-size: 14pt; }

			.directory {
				margin: 0.3em;
				font-family: monospace;
				font-weight: bold; }
		</style>
	</head>
	<body>
		<h1>A critical error has occurred.</h1>

		<p>This installer was unable to find this tools\'s language file or files. They should be found under:</p>

		<div class="directory">', \dirname(Maintenance::getSelf()) != '/' ? \dirname(Maintenance::getSelf()) : '', '/Languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders. Please double check to make sure you <strong>have uploaded all the files in the distribution</strong>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the Themes folder.</p>
		<p>If you continue to get this error message, feel free to <a href="https://support.simplemachines.org/">look to us for support</a>.</p>
	</div></body>
</html>';

		die;
	}

	/**
	 * Shows the template for the Utf8ConverterStep.
	 *
	 * This template is here rather than in UpgradeTemplate because it might
	 * also be needed for converters and other tools.
	 */
	public static function convertUtf8(): void
	{
		MaintenanceTemplate::warningsAndErrors();

		if (!empty(Maintenance::$fatal_error)) {
			return;
		}

		// Show the continue button.
		Utils::$context['continue'] = true;

		self::showStepWithSubSteps('convertutf8', 'utf8_done');
	}

	/**
	 * Show the contents of the log, if available.
	 */
	public static function showLog(): void
	{
		if (!empty(Utils::$context['log_contents'])) {
			echo '
				<h3>
					<a class="bbc_link" onclick="document.getElementById(\'log\').style.display = \'\';">', Lang::getTxt('show_log', file: 'Maintenance'), '</a>
				</h3>
				<pre id="log" class="bbc_code" style="display:none">' . Utils::$context['log_contents'] . '</pre>';
		}
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
			<h3>', Lang::getTxt('upgrade_performing_substeps', ['type' => $type], file: 'Maintenance'), '</h3>
			<h4><em>', Lang::getTxt('upgrade_please_be_patient', file: 'Maintenance'), '</em></h4>
			<input type="hidden" name="', $done_param, '" id="', $done_param, '" value="0">
			<div id="debug_section" class="bbc_code', !Maintenance::$tool->isDebug() ? ' hidden' : '', '"><span id="debuginfo"></span></div>';

		echo '
			<h3 id="current_tab">',
			Lang::getTxt(
				'upgrade_current_substep',
				[
					'substep' => '<span id="current_substep">' . (Utils::$context['current_substep'] ?? '') . '</span>',
				],
				file: 'Maintenance',
			),
			'</h3>';

		echo '
			<strong>',
			Lang::getTxt(
				'upgrade_substep_progress',
				[
					'substep_num' => '<span id="substep_num">' . Maintenance::getCurrentSubStep() . '</span>',
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
				const iStepWeight = ', Utils::$context['step_weight'], ';
				let iCurrentSubStep = ', Maintenance::getCurrentSubStep(), ';
				let iCurrentStart = ', Maintenance::getCurrentStart(), ';
				let iSubStepProgress = 0;
				let sCurrentSubStepName = "";
				let sCurrentSubStepStatus = "";
				let sNextSubStepName = "";

				function getNextSubstep()
				{
					const url = "' . Maintenance::getSelf() . '?' . Maintenance::setQueryString() . ';json"
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

									document.getElementById("', Maintenance::$tool->form_id, '").action = document.getElementById("', Maintenance::$tool->form_id, '").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);

									return;
								}

								sCurrentSubStepName = json.data.name;
								sNextSubStepName = json.data.next ?? "";
								iCurrentSubStep = parseInt(json.data.substep);
								iCurrentStart = parseInt(json.data.start);
								iSubStepProgress = iCurrentSubStep / iTotalSubSteps;
								sCurrentSubStepStatus = json.data.skipped ? "', Lang::getTxt('log_skipped', file: 'Maintenance'), '" : (json.data.failed ? "', Lang::getTxt('log_failed', file: 'Maintenance'), '" : (json.data.completed ? "', Lang::getTxt('log_done', file: 'Maintenance'), '" : ""))

								// Update the page.
								document.getElementById("substep_num").innerHTML = iCurrentSubStep;
								document.getElementById("current_substep").innerHTML = sCurrentSubStepName;

								// Hold up, we caught a error.
								if (true == json.data.failed) {
									document.getElementById("errorbox").style.display = "";

									if (document.getElementById("try_again")) {
										document.getElementById("try_again").style.display = "";
									}

									document.getElementById("', Maintenance::$tool->form_id, '").action = document.getElementById("', Maintenance::$tool->form_id, '").action
										.replace(/substep=\d+/, "substep=" + iCurrentSubStep)
										.replace(/start=\d+/, "start=" + iCurrentStart);

									if (isDebug) {
										document.getElementById("errorbox").getElementsByTagName("span")[0].innerHTML = json.debug.msg + "<br>" + json.debug.file + ":" + json.debug.line;
									}

									return;
								}

								updateProgress(iCurrentSubStep, iTotalSubSteps, iStepWeight, iSubStepProgress);

								if (isDebug) {
									document.getElementById("debug_section").append(sCurrentSubStepName + "... " + sCurrentSubStepStatus + "\n");

									if (document.getElementById("debug_section").scrollHeight) {
										document.getElementById("debug_section").scrollTop = document.getElementById("debug_section").scrollHeight
									}
								}

								// Are we done yet?
								if (iCurrentSubStep == iTotalSubSteps) {
									document.getElementById("commess").classList.remove("hidden");
									document.getElementById("current_tab").classList.add("hidden");
									document.getElementById("contbutt").disabled = 0;
									document.getElementById("' . $done_param . '").value = 1;

									setTimeout(function() {
										doAutoSubmit(0, "", "' . Maintenance::$tool->form_id . '", "contbutt");
									}, 1000);
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

									document.getElementById("', Maintenance::$tool->form_id, '").action = document.getElementById("', Maintenance::$tool->form_id, '").action
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

									document.getElementById("', Maintenance::$tool->form_id, '").action = document.getElementById("', Maintenance::$tool->form_id, '").action
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

							document.getElementById("', Maintenance::$tool->form_id, '").action = document.getElementById("', Maintenance::$tool->form_id, '").action
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
}
