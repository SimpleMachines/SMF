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
 * This template shows a snippet of code from a file and highlights which line caused the error.
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Utils::$context['file_data']['file'] ?></title>
		<?= Theme::template_css() ?>
	</head>
	<body>
		<table class="errorfile_table"><?php foreach (Utils::$context['file_data']['contents'] as $index => $line): ?><?php
$line_num = $index + Utils::$context['file_data']['min'];
$is_target = $line_num == Utils::$context['file_data']['target'];
?>
			<tr>
				<td class="file_line<?= $is_target ? ' current">==&gt;' : '">' ?><?= $line_num ?>:</td>
				<td <?= $is_target ? 'class="current"' : '' ?>><?= $line ?></td>
			</tr><?php endforeach; ?>
		</table>
	</body>
</html>