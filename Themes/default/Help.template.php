<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
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
	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<meta name="robots" content="noindex">
		<title>', Utils::$context['page_title'], '</title>
		', Theme::template_css(), '
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', Utils::$context['help_text'], '<br>
			<br>
			<a href="javascript:self.close();">', Lang::$txt['close_window'], '</a>
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
				<h3 class="catbg">', Lang::$txt['manual_smf_user_help'], '</h3>
			</div>
			<div id="help_container">
				<div id="helpmain" class="windowbg">
					<p>', Lang::getTxt('manual_welcome', ['forum_name' => Utils::$context['forum_name_html_safe']]), '</p>
					<p>', Lang::$txt['manual_introduction'], '</p>
					<ul>';

	foreach (Utils::$context['manual_sections'] as $section_id => $wiki_id)
		echo '
						<li><a href="', Utils::$context['wiki_url'], '/', Utils::$context['wiki_prefix'], $wiki_id, (Lang::$txt['lang_dictionary'] != 'en' ? '/' . Lang::$txt['lang_dictionary'] : ''), '" target="_blank" rel="noopener">', Lang::$txt['manual_section_' . $section_id . '_title'], '</a> - ', Lang::$txt['manual_section_' . $section_id . '_desc'], '</li>';

	echo '
					</ul>
					<p>', Lang::getTxt('manual_docs_and_credits', ['wiki_url' => Utils::$context['wiki_url'], 'credits_url' => Config::$scripturl . '?action=credits']), '</p>
				</div><!-- #helpmain -->
			</div><!-- #help_container -->';
}

?>