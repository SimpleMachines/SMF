<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_print_above()
{
	global $context, $settings, $options, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<meta name="robots" content="noindex" />
		<link rel="canonical" href="', $context['canonical_url'], '" />
		<title>', $txt['print_page'], ' - ', $context['topic_subject'], '</title>
		<style type="text/css">
			body, a
			{
				color: #000;
				background: #fff;
			}
			body, td, .normaltext
			{
				font-family: Verdana, arial, helvetica, serif;
				font-size: small;
			}
			h1#title
			{
				font-size: large;
				font-weight: bold;
			}
			h2#linktree
			{
				margin: 1em 0 2.5em 0;
				font-size: small;
				font-weight: bold;
			}
			dl#posts
			{
				width: 90%;
				margin: 0;
				padding: 0;
				list-style: none;
			}
			div.postheader, #poll_data
			{
				border: solid #000;
				border-width: 1px 0;
				padding: 4px 0;
			}
			div.postbody
			{
				margin: 1em 0 2em 2em;
			}
			table
			{
				empty-cells: show;
			}
			blockquote, code
			{
				border: 1px solid #000;
				margin: 3px;
				padding: 1px;
				display: block;
			}
			code
			{
				font: x-small monospace;
			}
			blockquote
			{
				font-size: x-small;
			}
			.smalltext, .quoteheader, .codeheader
			{
				font-size: x-small;
			}
			.largetext
			{
				font-size: large;
			}
			.centertext
			{
				text-align: center;
			}
			hr
			{
				height: 1px;
				border: 0;
				color: black;
				background-color: black;
			}
			.voted
			{
				font-weight: bold;
			}
		</style>
	</head>
	<body>
		<h1 id="title">', $context['forum_name_html_safe'], '</h1>
		<h2 id="linktree">', $context['category_name'], ' => ', (!empty($context['parent_boards']) ? implode(' => ', $context['parent_boards']) . ' => ' : ''), $context['board_name'], ' => ', $txt['topic_started'], ': ', $context['poster_name'], ' ', $txt['search_on'], ' ', $context['post_time'], '</h2>
		<div id="posts">';
}

function template_main()
{
	global $context, $settings, $options, $txt;

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
		echo '
			<div class="postheader">
				', $txt['title'], ': <strong>', $post['subject'], '</strong><br />
				', $txt['post_by'], ': <strong>', $post['member'], '</strong> ', $txt['search_on'], ' <strong>', $post['time'], '</strong>
			</div>
			<div class="postbody">
				', $post['body'], '
			</div>';
}

function template_print_below()
{
	global $context, $settings, $options;

	echo '
		</div>
		<div id="footer" class="smalltext">
			', theme_copyright(), '
		</div>
	</body>
</html>';
}

?>