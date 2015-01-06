<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

function template_print_above()
{
	global $context, $txt, $topic, $scripturl;

	$url_text = $scripturl . '?action=printpage;topic=' . $topic . '.0';
	$url_images = $url_text . ';images';

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<meta name="robots" content="noindex">
		<link rel="canonical" href="', $context['canonical_url'], '">
		<title>', $txt['print_page'], ' - ', $context['topic_subject'], '</title>
		<style type="text/css">
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
			blockquote, code {
				border: 1px solid #000;
				margin: 3px;
				padding: 1px;
				display: block;
			}
			code {
				font: x-small monospace;
			}
			blockquote {
				font-size: x-small;
			}
			.smalltext, .quoteheader, .codeheader {
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
			@media print {
				.print_options {
					display:none;
				}
			}
			@media screen {
				.print_options {
					margin:1em;
				}
			}
		</style>
	</head>
	<body>
		<div class="print_options">';

	// which option is set, text or text&images
	if (isset($_REQUEST['images']))
		echo '
			<a href="', $url_text, '">', $txt['print_page_text'], '</a> | <strong><a href="', $url_images, '">', $txt['print_page_images'], '</a></strong>';
	else
		echo '
			<strong><a href="', $url_text, '">', $txt['print_page_text'], '</a></strong> | <a href="', $url_images, '">', $txt['print_page_images'], '</a>';

	echo '
		</div>
		<h1 id="title">', $context['forum_name_html_safe'], '</h1>
		<h2 id="linktree">', $context['category_name'], ' => ', (!empty($context['parent_boards']) ? implode(' => ', $context['parent_boards']) . ' => ' : ''), $context['board_name'], ' => ', $txt['topic_started'], ': ', $context['poster_name'], ' ', $txt['search_on'], ' ', $context['post_time'], '</h2>
		<div id="posts">';
}

function template_main()
{
	global $context, $options, $txt, $scripturl, $topic;

	if (!empty($context['poll']))
	{
		echo '
			<div id="poll_data">', $txt['poll'], '
				<div class="question">', $txt['poll_question'], ': <strong>', $context['poll']['question'], '</strong>';

		$options = 1;
		foreach ($context['poll']['options'] as $option)
			echo '
					<div class="', $option['voted_this'] ? 'voted' : '', '">', $txt['option'], ' ', $options++, ': <strong>', $option['option'], '</strong>
						', $context['allow_poll_view'] ? $txt['votes'] . ': ' . $option['votes'] . '' : '', '
					</div>';

		echo '
			</div>';
	}

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="postheader">
				', $txt['title'], ': <strong>', $post['subject'], '</strong><br>
				', $txt['post_by'], ': <strong>', $post['member'], '</strong> ', $txt['search_on'], ' <strong>', $post['time'], '</strong>
			</div>
			<div class="postbody">
				', $post['body'];

		// Show attachment images
		if (isset($_GET['images']) && !empty($context['printattach'][$post['id_msg']]))
		{
			echo '
				<hr>';

			foreach ($context['printattach'][$post['id_msg']] as $attach)
				echo '
					<img width="' . $attach['width'] . '" height="' . $attach['height'] . '" src="', $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attach['id_attach'] . '" alt="">';
		}

		echo '
			</div>';
	}
}

function template_print_below()
{
	global $topic, $txt, $scripturl;

	$url_text = $scripturl . '?action=printpage;topic=' . $topic . '.0';
	$url_images = $url_text . ';images';

	echo '
		</div>
		<div class="print_options">';

	// Show the text / image links
	if (isset($_GET['images']))
		echo '
			<a href="', $url_text, '">', $txt['print_page_text'], '</a> | <strong><a href="', $url_images, '">', $txt['print_page_images'], '</a></strong>';
	else
		echo '
			<strong><a href="', $url_text, '">', $txt['print_page_text'], '</a></strong> | <a href="', $url_images, '">', $txt['print_page_images'], '</a>';

	echo '
		</div>
		<div id="footer" class="smalltext">
			', theme_copyright(), '
		</div>
	</body>
</html>';
}

?>