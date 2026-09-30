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
 * Shows permissions for items within a directory (called from template_file_permissions)
 *
 * @param string $ident A unique ID - typically the directory name
 * @param array $contents An array of items within the directory
 * @param int $level How far to go inside the directory
 * @param bool $has_more Whether there are more files to display besides what's in $contents
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['has_more' => false], EXTR_SKIP);
?><?php
$js_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident);
// Have we actually done something?
$drawn_div = false;
?><?php foreach ($contents as $name => $dir): ?><?php if (isset($dir['perms'])): ?><?php if (!$drawn_div): ?><?php $drawn_div = true; ?>

			</tbody>
			<tbody class="table_grid" id="<?= $js_ident ?>"><?php endif; ?><?php $cur_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident . '/' . $name); ?>

				<tr class="windowbg" id="content_<?= $cur_ident ?>">
					<td class="smalltext" width="30%"><?= str_repeat('&nbsp;', $level * 5) ?>

					<?= (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '<a id="link_' . $cur_ident . '" href="' . Config::$scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident . '/' . $name) . ';back_look=' . Utils::$context['back_look_data'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '#fol_' . $cur_ident . '" onclick="return expandFolder(\'' . $cur_ident . '\', \'' . addcslashes($ident . '/' . $name, "'\\") . '\');">' : '' ?><?php if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive')): ?>

						<span class="main_icons folder"></span><?php endif; ?>

						<?= $name ?>

						<?= (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '</a>' : '' ?>

					</td>
					<td class="smalltext">
						<span class="<?= ($dir['perms']['chmod'] ? 'success' : 'error') ?>"><?= Lang::getTxt($dir['perms']['chmod'] ? 'package_file_perms_writable' : 'package_file_perms_not_writable', file: 'Packages') ?></span>
						<?= ($dir['perms']['perms'] ? ' (' . Lang::getTxt('package_file_perms_chmod', file: 'Packages') . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : '') ?>

					</td>
					<td class="centertext perm_read"><input type="radio" name="permStatus[<?= $ident ?>/<?= $name ?>]" value="read"></td>
					<td class="centertext perm_writable"><input type="radio" name="permStatus[<?= $ident ?>/<?= $name ?>]" value="writable"></td>
					<td class="centertext perm_execute"><input type="radio" name="permStatus[<?= $ident ?>/<?= $name ?>]" value="execute"></td>
					<td class="centertext perm_custom"><input type="radio" name="permStatus[<?= $ident ?>/<?= $name ?>]" value="custom"></td>
					<td class="centertext perm_no_change"><input type="radio" name="permStatus[<?= $ident ?>/<?= $name ?>]" value="no_change" checked></td>
				</tr>
				<tr id="insert_div_loc_<?= $cur_ident ?>" style="display: none;"><td></td></tr><?php if (!empty($dir['contents'])): ?><?php $this->subTemplate('permission_show_contents', ['ident' => $ident . '/' . $name, 'contents' => $dir['contents'], 'level' => $level + 1, 'has_more' => !empty($dir['more_files'])]); ?><?php endif; ?><?php endif; ?><?php endforeach; ?><?php /* We have more files to show? */ ?><?php if ($has_more): ?>

				<tr class="windowbg" id="content_<?= $js_ident ?>_more">
					<td class="smalltext" width="40%"><?= str_repeat('&nbsp;', $level * 5) ?>

						<a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=perms;find=<?= base64_encode($ident) ?>;fileoffset=<?= (Utils::$context['file_offset'] + Utils::$context['file_limit']) ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>#fol_<?= preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident) ?>"><?= Lang::getTxt('package_file_perms_more_files', file: 'Packages') ?></a>
					</td>
					<td colspan="6"></td>
				</tr><?php endif; ?><?php if ($drawn_div): ?><?php
// Hide anything too far down the tree.
$isFound = false;
?><?php foreach (Utils::$context['look_for'] as $tree): ?><?php if (str_starts_with($tree, $ident)): ?><?php $isFound = true; ?><?php endif; ?><?php endforeach; ?><?php if ($level > 1 && !$isFound): ?>

		<script>
			expandFolder('<?= $js_ident ?>', '');
		</script><?php endif; ?><?php endif; ?>
