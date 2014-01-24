<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_popup()
{
	global $context, $settings, $txt;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '">
		<meta name="robots" content="noindex">
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21">
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	</head>
	<body id="likes_popup">
		<div class="windowbg">
			<ul id="likes">';

	foreach ($context['likers'] as $liker => $like_details)
	{
		echo '
				<li>
					<span class="floatleft avatar">', $like_details['profile']['avatar']['image'], '</span>
					<span class="floatright">', $like_details['time'], '</span>
					<span class="floatleft">
						', $like_details['profile']['link_color'], '<br>
						', $like_details['profile']['group'], '
					</span>
				</li>';
	}

	echo '
			</ul>
			<br class="clear">
			<a href="javascript:self.close();">', $txt['close_window'], '</a>
		</div>
	</body>
</html>';
}

?>