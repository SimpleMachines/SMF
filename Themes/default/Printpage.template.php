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
use SMF\Topic;
use SMF\Utils;

/**
 * The header. Defines the look and layout of the page as well as a form for choosing print options.
 */
function template_print_above()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<meta name="robots" content="noindex">
		<link rel="canonical" href="', Utils::$context['canonical_url'], '">
		<title>', Lang::$txt['print_page'], ' - ', Utils::$context['topic_subject'], '</title>
		<style>
			body, a {
				color: #000;
				background: #fff;
			}
			body, td, .normaltext {
				font-family: Verdana, arial, helvetica, serif;
				font-size: small;
			}
			h1#title {
				font-size: large;
				font-weight: bold;
			}
			h2#linktree {
				margin: 1em 0 2.5em 0;
				font-size: small;
				font-weight: bold;
			}
			dl#posts {
				width: 90%;
				margin: 0;
				padding: 0;
				list-style: none;
			}
			div.postheader, #poll_data {
				border: solid #000;
				border-width: 1px 0;
				padding: 4px 0;
			}
			div.postbody {
				margin: 1em 0 2em 2em;
			}
			table {
				empty-cells: show;
			}
			blockquote {
				margin: 0 0 8px 0;
				padding: 6px 10px;
				font-size: small;
				border: 1px solid #d6dfe2;
				border-left: 2px solid #aaa;
				border-right: 2px solid #aaa;
			}
			blockquote cite {
				display: block;
				border-bottom: 1px solid #aaa;
				font-size: 0.9em;
			}
			blockquote cite:before {
				color: #aaa;
				font-size: 22px;
				font-style: normal;
				margin-right: 5px;
			}
			code {
				border: 1px solid #000;
				margin: 3px;
				padding: 1px;
				display: block;
			}
			code {
				font: x-small monospace;
			}
			.smalltext, .codeheader {
				font-size: x-small;
			}
			.largetext {
				font-size: large;
			}
			.centertext {
				text-align: center;
			}
			hr {
				height: 1px;
				border: 0;
				color: black;
				background-color: black;
			}
			.voted {
				font-weight: bold;
			}
			#footer {
				font-family: Verdana, sans-serif;
			}
			@media print {
				.print_options {
					display: none;
				}
			}
			@media screen {
				.print_options {
					margin: 1em 0;
				}
			}';

	if (!empty(Config::$modSettings['max_image_width']))
		echo '
			.bbc_img {
				max-width: ' . Config::$modSettings['max_image_width'] . 'px;
			}';

	if (!empty(Config::$modSettings['max_image_height']))
		echo '
			.bbc_img {
				max-height: ' . Config::$modSettings['max_image_height'] . 'px;
			}';

	echo '
		</style>
	</head>
	<body>';

	template_print_options();

	echo '
		<h1 id="title">', Utils::$context['forum_name_html_safe'], '</h1>
		<h2 id="linktree">', Utils::$context['category_name'], ' => ', (!empty(Utils::$context['parent_boards']) ? implode(' => ', Utils::$context['parent_boards']) . ' => ' : ''), Utils::$context['board_name'], ' => ', Lang::$txt['topic_started'], ': ', Utils::$context['poster_name'], ' ', Lang::$txt['search_on'], ' ', Utils::$context['post_time'], '</h2>
		<div id="posts">';
}

/**
 * The main page. This shows the relevant info in a printer-friendly format
 */
function template_main()
{
	if (!empty(Utils::$context['poll']))
	{
		echo '
			<div id="poll_data">', Lang::$txt['poll'], '
				<div class="question">', Lang::$txt['poll_question'], ': <strong>', Utils::$context['poll']['question'], '</strong>';

		$options = 1;
		foreach (Utils::$context['poll']['options'] as $option)
			echo '
					<div class="', $option['voted_this'] ? 'voted' : '', '">', Lang::$txt['option'], ' ', $options++, ': <strong>', $option['option'], '</strong>
						', Utils::$context['allow_results_view'] ? Lang::$txt['votes'] . ': ' . $option['votes'] . '' : '', '
					</div>';

		echo '
			</div>';
	}

	foreach (Utils::$context['posts'] as $post)
	{
		echo '
			<div class="postheader">
				', Lang::$txt['title'], ': <strong>', $post['subject'], '</strong><br>
				', Lang::$txt['post_by'], ': <strong>', $post['member'], '</strong> ', Lang::$txt['search_on'], ' <strong>', $post['time'], '</strong>
			</div>
			<div class="postbody">
				', $post['body'];

		// Show attachment images
		if (isset($_GET['images']) && !empty(Utils::$context['printattach'][$post['id_msg']]))
		{
			echo '
				<hr>';

			foreach (Utils::$context['printattach'][$post['id_msg']] as $attach)
				echo '
					<img width="' . $attach['width'] . '" height="' . $attach['height'] . '" src="', Config::$scripturl . '?action=dlattach;topic=' . Topic::$topic_id . '.0;attach=' . $attach['id_attach'] . '" alt="">';
		}

		echo '
			</div><!-- .postbody -->';
	}
}

/**
 * The footer.
 */
function template_print_below()
{
	echo '
		</div><!-- #posts -->';

	template_print_options();

	echo '
		<div id="footer" class="smalltext">', Theme::copyright(), '</div>
	</body>
</html>';
}

/**
 * Displays the print page options
 */
function template_print_options()
{
	$url_text = Config::$scripturl . '?action=printpage;topic=' . Topic::$topic_id . '.0';
	$url_images = $url_text . ';images';

	echo '
		<div class="print_options">';

	// Which option is set, text or text&images
	if (isset($_REQUEST['images']))
		echo '
			<a href="', $url_text, '">', Lang::$txt['print_page_text'], '</a> | <strong><a href="', $url_images, '">', Lang::$txt['print_page_images'], '</a></strong>';
	else
		echo '
			<strong><a href="', $url_text, '">', Lang::$txt['print_page_text'], '</a></strong> | <a href="', $url_images, '">', Lang::$txt['print_page_images'], '</a>';

	echo '
		</div><!-- .print_options -->';
}

?>