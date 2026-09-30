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
 * Modify a group's permissions
 */
?><?php /* Cannot be edited? */ ?><?php if (!Utils::$context['profile']['can_modify']): ?>

	<div class="errorbox">
		<?= Lang::getTxt('permission_cannot_edit', ['url' => Config::$scripturl . '?action=admin;area=permissions;sa=profiles'], file: 'ManagePermissions') ?>

	</div><?php else: ?>

	<script>
		window.smf_usedDeny = false;

		function warnAboutDeny()
		{
			if (window.smf_usedDeny)
				return confirm("<?= Lang::getTxt('permissions_deny_dangerous', file: 'ManagePermissions') ?>");
			else
				return true;
		}
	</script><?php endif; ?>

		<form id="permissions" action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=modify2;group=<?= Utils::$context['group']['id'] ?>;pid=<?= Utils::$context['profile']['id'] ?>" method="post" accept-charset="UTF-8" name="permissionForm" onsubmit="return warnAboutDeny();"><?php if (!empty(Config::$modSettings['permission_enable_deny']) && Utils::$context['group']['id'] != -1): ?>

			<div class="noticebox">
				<?= Lang::getTxt('permissions_option_desc', file: 'ManagePermissions') ?>

			</div><?php endif; ?>

			<div class="cat_bar">
				<h3 class="catbg"><?php if (Utils::$context['permission_type'] == 'board'): ?>

				<?= Lang::getTxt('permissions_for_in', ['group' => Utils::$context['group']['name'], 'profile' => Utils::$context['profile']['name']], file: 'ManagePermissions') ?><?php else: ?>

				<?= Lang::getTxt(Utils::$context['permission_type'] == 'global' ? 'permissions_general_for' : 'permissions_board_for', ['name' => Utils::$context['group']['name']], file: 'ManagePermissions') ?><?php endif; ?>

				</h3>
			</div><?php /* Draw out the main bits. */ ?><?php $this->subTemplate('modify_group_display', ['type' => Utils::$context['permission_type']]); ?><?php if (Utils::$context['profile']['can_modify']): ?>

			<div class="padding">
				<input type="submit" value="<?= Lang::getTxt('permissions_commit', file: 'ManagePermissions') ?>" class="button">
			</div><?php endif; ?><?php foreach (Utils::$context['hidden_perms'] as $hidden_perm): ?>

			<input type="hidden" name="perm[<?= $hidden_perm[0] ?>][<?= $hidden_perm[1] ?>]" value="<?= $hidden_perm[2] ?>"><?php endforeach; ?>

			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-mp_token_var'] ?>" value="<?= Utils::$context['admin-mp_token'] ?>">
		</form>