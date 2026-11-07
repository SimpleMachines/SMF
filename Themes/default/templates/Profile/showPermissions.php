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
 * This template shows an admin which permissions a user have and which group(s) give them each permission.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt('showPermissions', file: 'Profile') ?>
			</h3>
		</div><?php if (Utils::$context['member']['has_all_permissions']): ?>
		<div class="information"><?= Lang::getTxt('showPermissions_all', file: 'Profile') ?></div><?php else: ?>
		<div class="information"><?= Lang::getTxt('showPermissions_help', file: 'Profile') ?></div>
		<div id="permissions" class="flow_hidden"><?php if (!empty(Utils::$context['no_access_boards'])): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('showPermissions_restricted_boards', file: 'Profile') ?></h3>
			</div>
			<div class="windowbg smalltext"><?= Lang::getTxt(
				'showPermissions_restricted_boards_desc',
				[
					'list' => Lang::sentenceList(array_map(
						fn($brd) => '<a href="' . Config::$scripturl . '?board=' . $brd['id'] . '.0" class="bbc_link">' . $brd['name'] . '</a>',
						Utils::$context['no_access_boards'],
					)),
				],
				file: 'Profile',
			) ?>
			</div><?php endif; ?><?php /* General Permissions section. */ ?>
			<div class="tborder">
				<div class="cat_bar">
					<h3 class="catbg"><?= Lang::getTxt('showPermissions_general', file: 'Profile') ?></h3>
				</div><?php if (!empty(Utils::$context['member']['permissions']['general'])): ?>
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th class="lefttext half_table"><?= Lang::getTxt('showPermissions_permission', file: 'Profile') ?></th>
							<th class="lefttext half_table"><?= Lang::getTxt('showPermissions_status', file: 'Profile') ?></th>
						</tr>
					</thead>
					<tbody><?php foreach (Utils::$context['member']['permissions']['general'] as $permission): ?>
						<tr class="windowbg">
							<td title="<?= $permission['id'] ?>">
								<?= $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'] ?>
							</td>
							<td class="smalltext"><?php if ($permission['is_denied']): ?>
								<span class="alert"><?= Lang::getTxt('showPermissions_denied', ['list' => Lang::sentenceList($permission['groups']['denied'])], file: 'Profile') ?></span><?php else: ?>
								<?= Lang::getTxt('showPermissions_given', ['list' => Lang::sentenceList($permission['groups']['allowed'])], file: 'Profile') ?><?php endif; ?>
							</td>
						</tr><?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- .tborder -->
			<br><?php else: ?>
			<p class="windowbg"><?= Lang::getTxt('showPermissions_none_general', file: 'Profile') ?></p><?php endif; ?><?php /* Board permission section. */ ?>
			<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=permissions#board_permissions" method="post" accept-charset="UTF-8">
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="board_permissions"><?= Lang::getTxt('showPermissions_select', file: 'Profile') ?></span>
						<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
							<option value="0"<?= Utils::$context['board'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('showPermissions_global', file: 'Profile') ?></option><?php if (!empty(Utils::$context['boards'])): ?>
							<option value="" disabled>---------------------------</option><?php endif; ?><?php /* Fill the box with any local permission boards. */ ?><?php foreach (Utils::$context['boards'] as $board): ?>
							<option value="<?= $board['id'] ?>"<?= $board['selected'] ? ' selected' : '' ?>><?= $board['name'] ?> (<?= $board['profile_name'] ?>)</option><?php endforeach; ?>
						</select>
					</h3>
				</div><!-- .cat_bar -->
			</form><?php if (!empty(Utils::$context['member']['permissions']['board'])): ?>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext half_table"><?= Lang::getTxt('showPermissions_permission', file: 'Profile') ?></th>
						<th class="lefttext half_table"><?= Lang::getTxt('showPermissions_status', file: 'Profile') ?></th>
					</tr>
				</thead>
				<tbody><?php foreach (Utils::$context['member']['permissions']['board'] as $permission): ?>
					<tr class="windowbg">
						<td title="<?= $permission['id'] ?>">
							<?= $permission['is_denied'] ? '<del class="error">' . $permission['name'] . '</del>' : $permission['name'] ?>
						</td>
						<td class="smalltext"><?php if ($permission['is_denied']): ?>
							<span class="alert"><?= Lang::getTxt('showPermissions_denied', ['list' => Lang::sentenceList($permission['groups']['denied'])], file: 'Profile') ?></span><?php else: ?>
							<?= Lang::getTxt('showPermissions_given', ['list' => Lang::sentenceList($permission['groups']['allowed'])], file: 'Profile') ?><?php endif; ?>
						</td>
					</tr><?php endforeach; ?>
				</tbody>
			</table><?php else: ?>
			<p class="windowbg"><?= Lang::getTxt('showPermissions_none_board', file: 'Profile') ?></p><?php endif; ?>
		</div><!-- #permissions --><?php endif; ?>
