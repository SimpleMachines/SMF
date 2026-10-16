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
 * A form for displaying inline permissions, such as on a settings page.
 */
?>

<?php /* This looks really weird, but it keeps things nested properly... */ ?>
											<fieldset id="<?= Utils::$context['current_permission'] ?>">
												<legend><a href="javascript:void(0);" onclick="document.getElementById('<?= Utils::$context['current_permission'] ?>').style.display = 'none';document.getElementById('<?= Utils::$context['current_permission'] ?>_groups_link').style.display = 'block'; return false;" class="toggle_up"> <?= Lang::getTxt('avatar_select_permission', file: 'Admin') ?></a></legend>
<?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>
												<ul>
<?php else: ?>
												<div class="information"><?= Lang::getTxt('permissions_option_desc', file: 'ManagePermissions') ?></div>
												<dl class="settings">
													<dt>
														<span class="perms"><strong><?= Lang::getTxt('permissions_option_on', file: 'ManagePermissions') ?></strong></span>
														<span class="perms"><strong><?= Lang::getTxt('permissions_option_off', file: 'ManagePermissions') ?></strong></span>
														<span class="perms red"><strong><?= Lang::getTxt('permissions_option_deny', file: 'ManagePermissions') ?></strong></span>
													</dt>
													<dd>
													</dd>
<?php endif; ?>
<?php foreach (Utils::$context['member_groups'] as $group): ?>
<?php if (!empty(Config::$modSettings['permission_enable_deny'])): ?>
													<dt>
<?php else: ?>
													<li>
<?php endif; ?>
<?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>
														<input type="checkbox" name="<?= Utils::$context['current_permission'] ?>[<?= $group['id'] ?>]" value="on"<?= $group['status'] == 'on' ? ' checked' : '' ?>>
<?php else: ?>
														<span class="perms"><input type="radio" name="<?= Utils::$context['current_permission'] ?>[<?= $group['id'] ?>]" value="on"<?= $group['status'] == 'on' ? ' checked' : '' ?>></span>
														<span class="perms"><input type="radio" name="<?= Utils::$context['current_permission'] ?>[<?= $group['id'] ?>]" value="off"<?= $group['status'] == 'off' ? ' checked' : '' ?>></span>
														<span class="perms"><input type="radio" name="<?= Utils::$context['current_permission'] ?>[<?= $group['id'] ?>]" value="deny"<?= $group['status'] == 'deny' ? ' checked' : '' ?>></span>
<?php endif; ?>
<?php if (!empty(Config::$modSettings['permission_enable_deny'])): ?>
													</dt>
													<dd>
														<span<?= $group['is_postgroup'] ? ' style="font-style: italic;"' : '' ?>><?= $group['name'] ?></span>
													</dd>
<?php else: ?>
														<span<?= $group['is_postgroup'] ? ' style="font-style: italic;"' : '' ?>><?= $group['name'] ?></span>
													</li>
<?php endif; ?>
<?php endforeach; ?>
<?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>
													<li>
														<input type="checkbox" onclick="invertAll(this, this.form, '<?= Utils::$context['current_permission'] ?>[');">
														<span><?= Lang::getTxt('check_all', file: 'General') ?></span>
													</li>
												</ul>
<?php else: ?>
												</dl>
<?php endif; ?>
											</fieldset>

											<a href="javascript:void(0);" onclick="document.getElementById('<?= Utils::$context['current_permission'] ?>').style.display = 'block'; document.getElementById('<?= Utils::$context['current_permission'] ?>_groups_link').style.display = 'none'; return false;" id="<?= Utils::$context['current_permission'] ?>_groups_link" style="display: none;" class="toggle_down"> <?= Lang::getTxt('avatar_select_permission', file: 'Admin') ?></a>

											<script>
												document.getElementById("<?= Utils::$context['current_permission'] ?>").style.display = "none";
												document.getElementById("<?= Utils::$context['current_permission'] ?>_groups_link").style.display = "";
											</script>