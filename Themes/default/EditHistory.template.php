<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Theme;
use SMF\Utils;

/**
 * The template for displaying a diff in a popup or overlay.
 */
function template_diff()
{
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
			', Utils::$context['diff'], '<br>
			<br>
			<a href="javascript:self.close();"></a>
		</div>
	</body>
</html>';
}

?>
