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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This template shows a backtrace of the given error
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Lang::getTxt('backtrace_title', file: 'ManageMaintenance') ?></title><?php Theme::template_css(); ?>

	</head>
	<body class="padding"><?php if (!empty(Utils::$context['error_info'])): ?>

			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('error', file: 'ManageMaintenance') ?>

				</h3>
			</div>
			<div class="windowbg" id="backtrace">
				<table class="table_grid">
					<tbody><?php if (!empty(Utils::$context['error_info']['error_type'])): ?>

						<tr class="title_bar">
							<td><strong><?= Lang::getTxt('error_type', file: 'ManageMaintenance') ?></strong></td>
						</tr>
						<tr class="windowbg">
							<td><?= ucfirst(Utils::$context['error_info']['error_type']) ?></td>
						</tr><?php endif; ?><?php if (!empty(Utils::$context['error_info']['message'])): ?>

						<tr class="title_bar">
							<td><strong><?= Lang::getTxt('error_message', file: 'ManageMaintenance') ?></strong></td>
						</tr>
						<tr class="windowbg lefttext">
							<td><code class="bbc_code" style="white-space: pre-line; overflow-y: auto"><?= Utils::$context['error_info']['message'] ?></code></td>
						</tr><?php endif; ?><?php if (!empty(Utils::$context['error_info']['file'])): ?>

						<tr class="title_bar">
							<td><strong><?= Lang::getTxt('file', file: 'General') ?></strong></td>
						</tr>
						<tr class="windowbg">
							<td><?= Utils::$context['error_info']['file'] ?></td>
						</tr><?php endif; ?><?php if (!empty(Utils::$context['error_info']['line'])): ?>

						<tr class="title_bar">
							<td><strong><?= Lang::getTxt('line', file: 'General') ?></strong></td>
						</tr>
						<tr class="windowbg">
							<td><?= Utils::$context['error_info']['line'] ?></td>
						</tr><?php endif; ?><?php if (!empty(Utils::$context['error_info']['url'])): ?>

						<tr class="title_bar">
							<td><strong><?= Lang::getTxt('error_url', file: 'ManageMaintenance') ?></strong></td>
						</tr>
						<tr class="windowbg word_break">
							<td><?= Utils::$context['error_info']['url'] ?></td>
						</tr><?php endif; ?>

					</tbody>
				</table>
			</div><?php endif; ?><?php if (!empty(Utils::$context['error_info']['backtrace'])): ?>

			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('backtrace_title', file: 'ManageMaintenance') ?>

				</h3>
			</div>
			<div class="windowbg">
				<ul class="padding"><?php foreach (Utils::$context['error_info']['backtrace'] as $key => $value): ?><?php /* Check for existing */ ?><?php if (!property_exists($value, 'file') || empty($value->file)): ?><?php $value->file = Lang::getTxt('unknown', file: 'General'); ?><?php endif; ?><?php if (!property_exists($value, 'line') || empty($value->line)): ?><?php $value->line = -1; ?><?php endif; ?>

					<li class="backtrace"><?= Lang::getTxt(
						'backtrace_info' . ($value->file == Lang::getTxt('unknown', file: 'General') && $value->line == -1 ? '_internal_function' : ''),
						[
							$key,
							(!empty($value->class) ? $value->class . $value->type : '') . $value->function,
							$value->file,
							$value->line,
							base64_encode($value->file),
							Config::$scripturl,
						],
						file: 'ManageMaintenance',
					) ?></li><?php endforeach; ?>

				</ul>
			</div><?php endif; ?>

	</body>
</html>