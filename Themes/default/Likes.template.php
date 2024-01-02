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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * This shows the popup that shows who likes a particular post.
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
	<body id="likes_popup">
		<div class="windowbg">
			<ul id="likes">';

	foreach (Utils::$context['likers'] as $liker => $like_details)
		echo '
				<li>
					', $like_details['profile']['avatar']['image'], '
					<span class="like_profile">
						', $like_details['profile']['link_color'], '
						<span class="description">', $like_details['profile']['group'], '</span>
					</span>
					<span class="floatright like_time">', $like_details['time'], '</span>
				</li>';

	echo '
			</ul>
			<br class="clear">
			<a href="javascript:self.close();">', Lang::$txt['close_window'], '</a>
		</div><!-- .windowbg -->
	</body>
</html>';
}

/**
 * Display a like button and info about how many people liked something
 */
function template_like()
{
	echo '
	<ul class="floatleft">';

	if (!empty(Utils::$context['data']['can_like']))
		echo '
		<li class="smflikebutton" id="', Utils::$context['data']['type'], '_', Utils::$context['data']['id_content'], '_likes"', '>
			<a href="', Config::$scripturl, '?action=likes;ltype=', Utils::$context['data']['type'], ';sa=like;like=', Utils::$context['data']['id_content'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="', Utils::$context['data']['type'], '_like"><span class="main_icons ', Utils::$context['data']['already_liked'] ? 'unlike' : 'like', '"></span> ', Utils::$context['data']['already_liked'] ? Lang::$txt['unlike'] : Lang::$txt['like'], '</a>
		</li>';

	if (!empty(Utils::$context['data']['count']))
	{
		Utils::$context['some_likes'] = true;
		$count = Utils::$context['data']['count'];
		$base = 'likes_';

		if (Utils::$context['data']['already_liked'])
		{
			$base = 'you_' . $base;
			$count--;
		}

		$base .= (isset(Lang::$txt[$base . $count])) ? $count : 'n';

		echo '
		<li class="like_count smalltext">', sprintf(Lang::$txt[$base], Config::$scripturl . '?action=likes;sa=view;ltype=' . Utils::$context['data']['type'] . ';js=1;like=' . Utils::$context['data']['id_content'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Lang::numberFormat($count)), '</li>';
	}

	echo '
	</ul>';
}

/**
 * A generic template that outputs any data passed to it...
 */
function template_generic()
{
	echo Utils::$context['data'];
}

?>