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

namespace SMF\Maintenance;

use SMF\Lang;
use SMF\Maintenance;

/**
 * Template for Maintenance scripts.
 */
class Template
{
	/**
	 * Header template.
	 */
	public static function header(): void
	{
		echo '<!DOCTYPE html>
    <html', Lang::$txt['lang_rtl'] == '1' ? ' dir="rtl"' : '', '>
    <head>
        <meta charset="', Lang::$txt['lang_character_set'] ?? 'UTF-8', '">
        <meta name="robots" content="noindex">
        <title>', Maintenance::$tool->getPageTitle(), '</title>
        <link rel="stylesheet" href="', Maintenance::$theme_url, '/css/index.css">
        <link rel="stylesheet" href="', Maintenance::$theme_url, '/css/maintenance.css">', Lang::$txt['lang_rtl'] == '1' ? '
        <link rel="stylesheet" href="' . Maintenance::$theme_url . '/css/rtl.css">' : '', '
        <script src="', Maintenance::$theme_url, '/scripts/jquery-' . JQUERY_VERSION . '.min.js"></script>
        <script src="', Maintenance::$theme_url, '/scripts/script.js"></script>
        <script>
		    const smf_scripturl = \'', Maintenance::getSelf(), '\';
            const smf_charset = \'UTF-8\';
            const allow_xhjr_credentials = true;
            const isDebug = ', Maintenance::$tool->isDebug() ? 'true' : 'false', ';
            let startPercent = ', Maintenance::$overall_percent, ';
        </script>
    </head>
    <body>
        <div id="footerfix">
        <div id="header">
            <h1 class="forumtitle">', Maintenance::$tool->getScriptName(), '</h1>
            <img id="smflogo" src="', Maintenance::$theme_url, '/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
        </div>
        <div id="wrapper">';

		// Have we got a language drop down - if so do it on the first step only.
		if (!empty(Maintenance::$languages) && count(Maintenance::$languages) > 1 && Maintenance::getCurrentStep() == 0) {
			echo '
            <div id="upper_section">
                <div id="inner_section">
                    <div id="inner_wrap">
                        <div class="news">
                            <form action="', Maintenance::getSelf(), '" method="get">
                                <label for="maintenance_language">', Lang::$txt['maintenance_language'], ':</label>
                                <select id="maintenance_language" name="lang_file" onchange="location.href = \'', Maintenance::getSelf(), '?lang_file=\' + this.options[this.selectedIndex].value;">';

			foreach (Maintenance::$languages as $lang => $name) {
				echo '
                                    <option', isset($_SESSION['lang_file']) && $_SESSION['lang_file'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';
			}

			echo '
                                </select>
                                <noscript><input type="submit" value="', Lang::$txt['action_set'], '" class="button"></noscript>
                            </form>
                        </div><!-- .news -->
                        <hr class="clear">
                    </div><!-- #inner_wrap -->
                </div><!-- #inner_section -->
            </div><!-- #upper_section -->';
		}

		echo '
            <div id="content_section">
                <div id="main_content_section">
                    <div id="main_steps">
                        <h2>', Lang::$txt['maintenance_progress'], '</h2>
                        <ul class="steps_list">';

		if (Maintenance::$tool->hasSteps()) {
			foreach (Maintenance::$tool->getSteps() as $num => $step) {
				echo '
                            <li', $num == Maintenance::getCurrentStep() ? ' class="stepcurrent"' : '', '>', Lang::$txt['maintenance_step'], ' ', $step->getID(), ': ', $step->getName(), '</li>';
			}
		}

		echo '
                        </ul>
                    </div>
                    <div id="install_progress">
                        <div id="progress_bar" class="progress_bar progress_green">
                            <h3>' . Lang::$txt['maintenance_overall_progress'], '</h3>
                            <span id="overall_text">', Maintenance::$overall_percent, '%</span>
                            <div id="overall_progress" class="bar" style="width: ', Maintenance::$overall_percent, '%;"></div>
                        </div>';

		if (Maintenance::$total_substeps !== null) {
			echo '
                        <div id="progress_bar_step" class="progress_bar progress_yellow">
                            <h3>', Lang::$txt['maintenance_substep_progress'], '</h3>
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

                        <div id="time_elapsed" class="smalltext time_elapsed">
    						', Maintenance::getTimeElasped(), '
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
                <h3>', Lang::$txt['critical_error'], '</h3>
                ', Maintenance::$fatal_error, '
            </div>';
		} elseif (!empty(Maintenance::$errors)) {
			echo '
            <div class="errorbox">
                <h3>', Lang::$txt['critical_error'], '</h3>
                ', implode('
                ', Maintenance::$errors), '
            </div>';
		}
		// A warning message?
		elseif (!empty(Maintenance::$warnings)) {
			echo '
            <div class="errorbox">
                <h3>', Lang::$txt['warning'], '</h3>
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

		<div class="directory">', dirname(Maintenance::getSelf()) != '/' ? dirname(Maintenance::getSelf()) : '', '/Languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders. Please double check to make sure you <strong>have uploaded all the files in the distribution</strong>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the Themes folder.</p>
		<p>If you continue to get this error message, feel free to <a href="https://support.simplemachines.org/">look to us for support</a>.</p>
	</div></body>
</html>';

		die;
	}
}

?>