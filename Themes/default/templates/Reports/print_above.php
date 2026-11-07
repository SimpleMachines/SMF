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

use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Header of the print page!
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Utils::$context['page_title'] ?></title>
		<link rel="stylesheet" href="<?= Theme::$current->settings['default_theme_url'] ?>/css/report.css<?= Utils::$context['browser_cache'] ?>">
		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/reports.js<?= Utils::$context['browser_cache'] ?>"></script>
	</head>
	<body>