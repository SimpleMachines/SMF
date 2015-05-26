<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

function template_popup()
{
	global $context, $settings, $txt, $modSettings;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<meta name="robots" content="noindex">
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'] ,'">
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'] ,'"></script>
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
						<span class="description">', $like_details['profile']['group'], '</span>
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

function template_like()
{
	global $context, $scripturl, $txt;

	echo '
	<ul class="floatleft">';

	if (!empty($context['data']['can_like']))
	{
		echo '
		<li class="like_button" id="', $context['data']['type'], '_', $context['data']['id_content'], '_likes"', '><a href="', $scripturl, '?action=likes;ltype=', $context['data']['type'], ';sa=like;like=', $context['data']['id_content'], ';', $context['session_var'], '=', $context['session_id'], '" class="', $context['data']['type'], '_like"><span class="generic_icons ', $context['data']['already_liked'] ? 'unlike' : 'like', '"></span> ', $context['data']['already_liked'] ? $txt['unlike'] : $txt['like'], '</a></li>';
	}

	if (!empty($context['data']['count']))
	{
		$context['some_likes'] = true;
		$count = $context['data']['count'];
		$base = 'likes_';
		if ($context['data']['already_liked'])
		{
			$base = 'you_' . $base;
			$count--;
		}
		$base .= (isset($txt[$base . $count])) ? $count : 'n';

		echo '
		<li class="like_count smalltext">', sprintf($txt[$base], $scripturl . '?action=likes;sa=view;ltype=' . $context['data']['type'] . ';js=1;like=' . $context['data']['id_content'] . ';' . $context['session_var'] . '=' . $context['session_id'], comma_format($count)), '</li>';
	}

	echo '
	</ul>';
}

function template_generic()
{
	global $context;

	echo $context['data'];
}

?>