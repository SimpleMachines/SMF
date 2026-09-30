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
 * THe page that shows which permissions profile applies to each board
 */
?>

		<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=board" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('permissions_boards', file: 'Admin') ?></h3>
			</div>
			<div class="information">
				<?= Lang::getTxt('permissions_boards_desc', file: 'ManagePermissions') ?>

			</div>

			<div class="cat_bar">
				<h3 id="board_permissions" class="catbg flow_hidden">
					<span class="perm_name floatleft"><?= Lang::getTxt('board_name', file: 'General') ?></span>
					<span class="perm_profile floatleft"><?= Lang::getTxt('permission_profile', file: 'ManagePermissions') ?></span>
				</h3>
			</div>
			<div class="windowbg">
<?php foreach (Utils::$context['categories'] as $category): ?>
				<div class="sub_bar">
					<h3 class="subbg"><?= $category['name'] ?></h3>
				</div>
<?php if (!empty($category['boards'])): ?>
				<ul class="perm_boards flow_hidden">
<?php endif; ?>
<?php foreach ($category['boards'] as $board): ?>
					<li class="flow_hidden">
						<span class="perm_board floatleft">
							<a href="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=board;boardid=<?= $board['id'] ?>;rid=permissions;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= str_repeat('-', $board['child_level']) ?> <?= $board['name'] ?></a>
						</span>
						<span class="perm_boardprofile floatleft">
<?php if (Utils::$context['edit_all']): ?>
							<select name="boardprofile[<?= $board['id'] ?>]">
<?php foreach (Utils::$context['profiles'] as $id => $profile): ?>
								<option value="<?= $id ?>"<?= $id == $board['profile'] ? ' selected' : '' ?>><?= $profile['name'] ?></option>
<?php endforeach; ?>
							</select>
<?php else: ?>
							<a href="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=index;pid=<?= $board['profile'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= $board['profile_name'] ?></a>
<?php endif; ?>
						</span>
					</li>
<?php endforeach; ?>
<?php if (!empty($category['boards'])): ?>
				</ul>
<?php endif; ?>
<?php endforeach; ?>
<?php if (Utils::$context['edit_all']): ?>
				<input type="submit" name="save_changes" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
<?php else: ?>
				<a class="button" href="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=board;edit;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('permissions_board_all', file: 'ManagePermissions') ?></a>
<?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-mpb_token_var'] ?>" value="<?= Utils::$context['admin-mpb_token'] ?>">
			</div><!-- .windowbg -->
		</form>