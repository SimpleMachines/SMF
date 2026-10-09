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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This lets you browse a list of files in a theme so you can choose which one to edit.
 */
?><?php if (!empty(Utils::$context['browse_title'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['browse_title'] ?></h3>
		</div><?php endif; ?>
		<table class="table_grid tborder">
			<thead>
				<tr class="title_bar">
					<th class="lefttext half_table" scope="col"><?= Lang::getTxt('themeadmin_edit_filename', file: 'Themes') ?></th>
					<th class="quarter_table" scope="col"><?= Lang::getTxt('themeadmin_edit_modified', file: 'Themes') ?></th>
					<th class="quarter_table" scope="col"><?= Lang::getTxt('themeadmin_edit_size', file: 'Themes') ?></th>
				</tr>
			</thead>
			<tbody><?php foreach (Utils::$context['theme_files'] as $file): ?>
				<tr class="windowbg">
					<td><?php if ($file['is_editable']): ?>
						<a href="<?= $file['href'] ?>"<?= $file['is_template'] ? ' style="font-weight: bold;"' : '' ?>><?= $file['filename'] ?></a><?php elseif ($file['is_directory']): ?>
						<a href="<?= $file['href'] ?>" class="is_directory"><span class="main_icons folder"></span><?= $file['filename'] ?></a><?php else: ?><?= $file['filename'] ?><?php endif; ?>
					</td>
					<td class="righttext"><?= !empty($file['last_modified']) ? $file['last_modified'] : '' ?></td>
					<td class="righttext"><?= $file['size'] ?></td>
				</tr><?php endforeach; ?>
			</tbody>
		</table>