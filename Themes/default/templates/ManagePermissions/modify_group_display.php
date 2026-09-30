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
 * The way of looking at permissions.
 *
 * @param string $type The permissions type
 */
?><?php
$permission_type = &Utils::$context['permissions'][$type];
$disable_field = Utils::$context['profile']['can_modify'] ? '' : 'disabled ';
?><?php foreach ($permission_type['columns'] as $column): ?>

					<table class="table_grid half_content"><?php foreach ($column as $permissionGroup): ?><?php if (empty($permissionGroup['permissions'])): ?><?php continue; ?><?php endif; ?><?php
// Are we likely to have something in this group to display or is it all hidden?
$has_display_content = false;
?><?php if (!$permissionGroup['hidden']): ?><?php /* Before we go any further check we are going to have some data to print otherwise we just have a silly heading. */ ?><?php foreach ($permissionGroup['permissions'] as $permission): ?><?php if (!$permission['hidden']): ?><?php $has_display_content = true; ?><?php endif; ?><?php endforeach; ?><?php if ($has_display_content): ?>

						<tr class="title_bar">
							<th></th>
							<th<?= Utils::$context['group']['id'] == -1 ? ' colspan="2"' : '' ?> class="smalltext"><?= $permissionGroup['name'] ?></th><?php if (Utils::$context['group']['id'] != -1): ?>

							<th><?= Lang::getTxt('permissions_option_own', file: 'ManagePermissions') ?></th>
							<th><?= Lang::getTxt('permissions_option_any', file: 'ManagePermissions') ?></th><?php endif; ?>

						</tr><?php endif; ?><?php endif; ?><?php foreach ($permissionGroup['permissions'] as $permission): ?><?php if (!$permission['hidden'] && !$permissionGroup['hidden']): ?>

						<tr class="windowbg">
							<td>
								<?= $permission['show_help'] ? '<a href="' . Config::$scripturl . '?action=helpadmin;help=permissionhelp_' . $permission['id'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a>' : '' ?>

							</td>
							<td class="lefttext full_width">
								<?= $permission['name'] ?><?= (!empty($permission['note']) ? '<br>
								<strong class="smalltext">' . $permission['note'] . '</strong>' : '') ?>

							</td>
							<td><?php if ($permission['has_own_any']): ?><?php /* Guests can't do their own thing. */ ?><?php if (Utils::$context['group']['id'] != -1): ?><?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>

								<input type="checkbox" name="perm[<?= $permission_type['id'] ?>][<?= $permission['own']['id'] ?>]"<?= $permission['own']['select'] == 'on' ? ' checked="checked"' : '' ?> value="on" id="<?= $permission['own']['id'] ?>_on" <?= $disable_field ?>><?php else: ?>

								<select name="perm[<?= $permission_type['id'] ?>][<?= $permission['own']['id'] ?>]" <?= $disable_field ?>><?php foreach (['on', 'off', 'deny'] as $c): ?>

									<option <?= $permission['own']['select'] == $c ? ' selected' : '' ?> value="<?= $c ?>"><?= Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions') ?></option><?php endforeach; ?>

								</select><?php endif; ?>

							</td>
							<td><?php endif; ?><?php if (empty(Config::$modSettings['permission_enable_deny']) || Utils::$context['group']['id'] == -1): ?>

								<input type="checkbox" name="perm[<?= $permission_type['id'] ?>][<?= $permission['any']['id'] ?>]"<?= $permission['any']['select'] == 'on' ? ' checked="checked"' : '' ?> value="on" <?= $disable_field ?>><?php else: ?>

								<select name="perm[<?= $permission_type['id'] ?>][<?= $permission['any']['id'] ?>]" <?= $disable_field ?>><?php foreach (['on', 'off', 'deny'] as $c): ?>

									<option <?= $permission['any']['select'] == $c ? ' selected' : '' ?> value="<?= $c ?>"><?= Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions') ?></option><?php endforeach; ?>

								</select><?php endif; ?><?php else: ?><?php if (Utils::$context['group']['id'] != -1): ?>

							</td>
							<td><?php endif; ?><?php if (empty(Config::$modSettings['permission_enable_deny']) || Utils::$context['group']['id'] == -1): ?>

								<input type="checkbox" name="perm[<?= $permission_type['id'] ?>][<?= $permission['id'] ?>]"<?= $permission['select'] == 'on' ? ' checked="checked"' : '' ?> value="on" <?= $disable_field ?>><?php else: ?>

								<select name="perm[<?= $permission_type['id'] ?>][<?= $permission['id'] ?>]" <?= $disable_field ?>><?php foreach (['on', 'off', 'deny'] as $c): ?>

									<option <?= $permission['select'] == $c ? ' selected' : '' ?> value="<?= $c ?>"><?= Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions') ?></option><?php endforeach; ?>

								</select><?php endif; ?><?php endif; ?>

							</td>
						</tr><?php endif; ?><?php endforeach; ?><?php endforeach; ?>

					</table><?php endforeach; ?>

					<br class="clear">