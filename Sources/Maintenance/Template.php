<?php

namespace SMF\Maintenance;
use \SMF\Lang;
use \SMF\Maintenance;

class Template
{
    public static function header(): void
    {
        echo '<!DOCTYPE html>
    <html', Lang::$txt['lang_rtl'] == '1' ? ' dir="rtl"' : '', '>
    <head>
        <meta charset="', Lang::$txt['lang_character_set'] ?? 'UTF-8', '">
        <meta name="robots" content="noindex">
        <title>', Maintenance::$tool->getPageTitle(), '</title>
        <link rel="stylesheet" href="Themes/default/css/index.css">
        <link rel="stylesheet" href="Themes/default/css/install.css">', Lang::$txt['lang_rtl'] == '1' ? '
        <link rel="stylesheet" href="Themes/default/css/rtl.css">' : '', '
        <script src="Themes/default/scripts/jquery-' . JQUERY_VERSION . '.min.js"></script>
        <script src="Themes/default/scripts/script.js"></script>
    </head>
    <body>
        <div id="footerfix">
        <div id="header">
            <h1 class="forumtitle">', Maintenance::$tool->getPageTitle(), '</h1>
            <img id="smflogo" src="Themes/default/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
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
                                <label for="installer_language">', Lang::$txt['installer_language'], ':</label>
                                <select id="installer_language" name="lang_file" onchange="location.href = \'', Maintenance::getSelf(), '?lang_file=\' + this.options[this.selectedIndex].value;">';
    
            foreach (Maintenance::$languages as $lang => $name) {
                echo '
                                    <option', isset($_SESSION['lang_file']) && $_SESSION['lang_file'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';
            }
    
            echo '
                                </select>
                                <noscript><input type="submit" value="', Lang::$txt['installer_language_set'], '" class="button"></noscript>
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
                        <h2>', Lang::$txt['upgrade_progress'], '</h2>
                        <ul class="steps_list">';

        if (Maintenance::$tool->hasSteps()) {
            foreach (Maintenance::$tool->getSteps() as $num => $step) {
                echo '
                            <li', $num == Maintenance::getCurrentStep() ? ' class="stepcurrent"' : '', '>', Lang::$txt['upgrade_step'], ' ', $step->getID(), ': ', $step->getName(), '</li>';
            }
        }
    
        echo '
                        </ul>
                    </div>
                    <div id="install_progress">
                        <div id="progress_bar" class="progress_bar progress_green">
                            <h3>' . Lang::$txt['upgrade_overall_progress'], '</h3>
                            <span id="overall_text">', Maintenance::$overall_percent, '%</span>
                            <div id="overall_progress" class="bar" style="width: ', Maintenance::$overall_percent, '%;"></div>
                        </div>
                    </div>
                    <div id="main_screen" class="clear">
                        <h2>', Maintenance::$tool->getStepTitle(), '</h2>
                        <div class="panel">';
    
    }

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
    </body>
    </html>';
    
    }

    public static function warningsAndErrors(): void
    {
        // Errors are very serious..
        if (!empty(Maintenance::$fatal_error)) {
            echo '
            <div class="errorbox">
                <h3>', Lang::$txt['upgrade_critical_error'], '</h3>
                ', Maintenance::$fatal_error, '
            </div>';
        }
        else if (!empty(Maintenance::$errors)) {
            echo '
            <div class="errorbox">
                <h3>', Lang::$txt['upgrade_critical_error'], '</h3>
                ', implode('
                ', Maintenance::$errors), '
            </div>';
        }
        // A warning message?
        elseif (!empty(Maintenance::$warnings)) {
            echo '
            <div class="errorbox">
                <h3>', Lang::$txt['upgrade_warning'], '</h3>
                ', implode('
                ', Maintenance::$warnings), '
            </div>';
        }
    }

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