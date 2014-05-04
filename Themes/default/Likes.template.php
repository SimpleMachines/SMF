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
	global $context, $settings, $txt, $modSettings;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '">
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

function template_like()
{
	global $context;

	echo '
	<ul class="floatleft">';
	if (!empty($context['data']['can_like']))
	{
		echo '
									<li class="like_button" id="msg_', $context['data']['id'], '_likes"', $ignoring ? ' style="display:none;"' : '', '><a href="', $scripturl, '?action=likes;ltype=msg;sa=like;like=', $context['data']['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="msg_like"><span class="', $context['data']['you'] ? 'unlike' : 'like', '"></span>', $context['data']['you'] ? $txt['unlike'] : $txt['like'], '</a></li>';
	}

	if (!empty($context['data']['count']))
	{
		$context['some_likes'] = true;
		$count = $context['data']['count'];
		$base = 'likes_';
		if ($context['data']['you'])
		{
			$base = 'you_' . $base;
			$count--;
		}
		$base .= (isset($txt[$base . $count])) ? $count : 'n';

		echo '
									<li class="like_count smalltext">', sprintf($txt[$base], $scripturl . '?action=likes;view;ltype=msg;like=' . $context['data']['id'], comma_format($count)), '</li>';
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