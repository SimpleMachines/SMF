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
 * Edit permission profiles (predefined).
 */
?>

	<div id="admin_form_wrapper">
		<form action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('permissions_profile_edit', file: 'ManagePermissions') ?></h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th><?= Lang::getTxt('permissions_profile_name', file: 'ManagePermissions') ?></th>
						<th><?= Lang::getTxt('permissions_profile_used_by', file: 'ManagePermissions') ?></th>
						<th class="table_icon"<?= !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '' ?>><?= Lang::getTxt('delete', file: 'General') ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach (Utils::$context['profiles'] as $profile): ?>
					<tr class="windowbg">
						<td>
<?php if (!empty(Utils::$context['show_rename_boxes']) && $profile['can_rename']): ?>
							<input type="text" name="rename_profile[<?= $profile['id'] ?>]" value="<?= $profile['name'] ?>">
<?php else: ?>
							<a href="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=index;pid=<?= $profile['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= $profile['name'] ?></a>
<?php endif; ?>
						</td>
						<td>
							<?= !empty($profile['boards_text']) ? $profile['boards_text'] : Lang::getTxt('permissions_profile_used_by_count', [0], file: 'ManagePermissions') ?>

						</td>
						<td<?= !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '' ?>>
							<input type="checkbox" name="delete_profile[]" value="<?= $profile['id'] ?>" <?= $profile['can_delete'] ? '' : 'disabled' ?>>
						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
			<div class="flow_auto righttext padding">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-mpp_token_var'] ?>" value="<?= Utils::$context['admin-mpp_token'] ?>">
<?php if (Utils::$context['can_rename_something']): ?>
				<input type="submit" name="rename" value="<?= Lang::getTxt(empty(Utils::$context['show_rename_boxes']) ? 'permissions_profile_rename' : 'permissions_commit', file: 'ManagePermissions') ?>" class="button">
<?php endif; ?>
				<input type="submit" name="delete" value="<?= Lang::getTxt('quickmod_delete_selected', file: 'General') ?>" class="button" <?= !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '' ?>>
			</div>
		</form>
		<br>
		<form action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('permissions_profile_new', file: 'ManagePermissions') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('permissions_profile_name', file: 'ManagePermissions') ?></strong>
					</dt>
					<dd>
						<input type="text" name="profile_name" value="">
					</dd>
					<dt>
						<strong><?= Lang::getTxt('permissions_profile_copy_from', file: 'ManagePermissions') ?></strong>
					</dt>
					<dd>
						<select name="copy_from">
<?php foreach (Utils::$context['profiles'] as $id => $profile): ?>
							<option value="<?= $id ?>"><?= $profile['name'] ?></option>
<?php endforeach; ?>
						</select>
					</dd>
				</dl>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-mpp_token_var'] ?>" value="<?= Utils::$context['admin-mpp_token'] ?>">
				<input type="submit" name="create" value="<?= Lang::getTxt('permissions_profile_new_create', file: 'ManagePermissions') ?>" class="button">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #admin_form_wrapper -->