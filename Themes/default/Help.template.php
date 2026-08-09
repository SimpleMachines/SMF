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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * This displays a help popup thingy
 */
function template_popup()
{
	// reqOverlayDiv() asks for this with ';ajax' on the end and shows whatever
	// comes back inside an overlay on the page it was called from. There is no
	// window to close in that case, and no use for a document of its own.
	if (isset($_REQUEST['ajax'])) {
		echo '
		<div class="windowbg description">
			', Utils::$context['help_text'], '
		</div>';

		return;
	}

	// Otherwise this really is a window of its own, so it needs the html.
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<meta name="robots" content="noindex">
		<title>', Utils::$context['page_title'], '</title>
		', Theme::template_css(), '
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', Utils::$context['help_text'], '<br>
			<br>
			<a href="javascript:self.close();">', Lang::getTxt('close_window', file: 'Help'), '</a>
		</div>
	</body>
</html>';
}

/**
 * The main help page
 */
function template_manual()
{
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('manual_smf_user_help', file: 'Manual'), '</h3>
			</div>
			<div id="help_container">
				<div id="helpmain" class="windowbg">
					<p>', Lang::getTxt('manual_welcome', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'Manual'), '</p>
					<p>', Lang::getTxt('manual_introduction', file: 'Manual'), '</p>
					<ul>';

	foreach (Utils::$context['manual_sections'] as $section_id => $wiki_id) {
		echo '
						<li><a href="', Utils::$context['wiki_url'], '/', Utils::$context['wiki_prefix'], $wiki_id, (Lang::getTxt('lang_dictionary', file: 'General') != 'en' ? '/' . Lang::getTxt('lang_dictionary', file: 'General') : ''), '" target="_blank" rel="noopener">', Lang::getTxt('manual_section_' . $section_id . '_title', file: 'Manual'), '</a> - ', Lang::getTxt('manual_section_' . $section_id . '_desc', file: 'Manual'), '</li>';
	}

	echo '
					</ul>
					<p>', Lang::getTxt('manual_docs_and_credits', ['wiki_url' => Utils::$context['wiki_url'], 'credits_url' => Config::$scripturl . '?action=credits'], file: 'Manual'), '</p>
				</div><!-- #helpmain -->
			</div><!-- #help_container -->';
}
