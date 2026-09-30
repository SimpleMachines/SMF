<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The header. Defines the look and layout of the page as well as a form for choosing print options.
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<meta name="robots" content="noindex">
		<link rel="canonical" href="<?= Utils::$context['canonical_url'] ?>">
		<title><?= Lang::getTxt('print_page', file: 'General') ?> - <?= Utils::$context['topic_subject'] ?></title>
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
			}<?php if (!empty(Config::$modSettings['max_image_width'])): ?>

			.bbc_img {
				max-width: <?= Config::$modSettings['max_image_width'] ?>px;
			}<?php endif; ?><?php if (!empty(Config::$modSettings['max_image_height'])): ?>

			.bbc_img {
				max-height: <?= Config::$modSettings['max_image_height'] ?>px;
			}<?php endif; ?>

		</style>
	</head>
	<body><?php $this->subTemplate('print_options'); ?>

		<h1 id="title"><?= Utils::$context['forum_name_html_safe'] ?></h1>
		<h2 id="linktree"><?= Utils::$context['category_name'] ?> ▸ <?= (!empty(Utils::$context['parent_boards']) ? implode(' ▸ ', Utils::$context['parent_boards']) . ' ▸ ' : '') ?><?= Utils::$context['board_name'] ?> ▸ <?= Lang::getTxt('started_by_member_time', ['member' => Utils::$context['poster_name'], 'time' => Utils::$context['post_time']], file: 'General') ?></h2>
		<div id="posts">