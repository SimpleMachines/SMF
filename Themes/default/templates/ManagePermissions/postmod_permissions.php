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
 * Edit post moderation permissions.
 */
?>

					<div id="admin_form_wrapper">
						<form action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=postmod;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" name="postmodForm" id="postmodForm" accept-charset="UTF-8">
							<div class="cat_bar">
								<h3 class="catbg"><?= Lang::getTxt('permissions_post_moderation', file: 'Admin') ?></h3>
							</div>
<?php /* First, we have the bit where we can enable or disable this bad boy. */ ?>
							<div class="windowbg">
								<dl class="settings">
									<dt><?= Lang::getTxt('permissions_post_moderation_enable', file: 'ManagePermissions') ?></dt>
									<dd><input type="checkbox" name="postmod_active"<?= !empty(Config::$modSettings['postmod_active']) ? ' checked' : '' ?>></dd>
								</dl>
							</div>
<?php /* If we're not active, there's a bunch of stuff we don't need to show. */ ?>
<?php if (!empty(Config::$modSettings['postmod_active'])): ?>
<?php /* Got advanced permissions - if so warn! */ ?>
<?php if (!empty(Config::$modSettings['permission_enable_deny'])): ?>
							<div class="information"><?= Lang::getTxt('permissions_post_moderation_deny_note', file: 'ManagePermissions') ?></div>
<?php endif; ?>
							<div class="padding">
								<strong><?= Lang::getTxt('permissions_post_moderation_legend', file: 'ManagePermissions') ?></strong>
								<ul class="floatleft smalltext block">
									<li><span class="main_icons post_moderation_allow"></span><?= Lang::getTxt('permissions_post_moderation_allow', file: 'ManagePermissions') ?></li>
									<li><span class="main_icons post_moderation_moderate"></span><?= Lang::getTxt('permissions_post_moderation_moderate', file: 'ManagePermissions') ?></li>
									<li><span class="main_icons post_moderation_deny"></span><?= Lang::getTxt('permissions_post_moderation_disallow', file: 'ManagePermissions') ?></li>
								</ul>
								<br><br><br>
								<p class="righttext floatright block">
									<?= Lang::getTxt('permissions_post_moderation_select', file: 'ManagePermissions') ?>

									<select name="pid" onchange="document.forms.postmodForm.submit();">
<?php foreach (Utils::$context['profiles'] as $profile): ?>
<?php if ($profile['can_modify']): ?>
										<option value="<?= $profile['id'] ?>"<?= $profile['id'] == Utils::$context['current_profile'] ? ' selected' : '' ?>><?= $profile['name'] ?></option>
<?php endif; ?>
<?php endforeach; ?>
									</select>
									<input type="submit" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button">
								</p>
							</div><!-- .padding -->
							<table class="table_grid" id="postmod">
								<thead>
									<tr class="title_bar">
										<th></th>
										<th class="centercol" colspan="3">
											<?= Lang::getTxt('permissions_post_moderation_new_topics', file: 'ManagePermissions') ?>

										</th>
										<th class="centercol" colspan="3">
											<?= Lang::getTxt('permissions_post_moderation_replies_own', file: 'ManagePermissions') ?>

										</th>
										<th class="centercol" colspan="3">
											<?= Lang::getTxt('permissions_post_moderation_replies_any', file: 'ManagePermissions') ?>

										</th>
<?php if (Config::$modSettings['attachmentEnable'] == 1): ?>
										<th class="centercol" colspan="3">
											<?= Lang::getTxt('permissions_post_moderation_attachments', file: 'ManagePermissions') ?>

										</th>
<?php endif; ?>
									</tr>
									<tr class="windowbg">
										<th class="quarter_table">
											<?= Lang::getTxt('permissions_post_moderation_group', file: 'ManagePermissions') ?>

										</th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
<?php if (Config::$modSettings['attachmentEnable'] == 1): ?>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
<?php endif; ?>
									</tr>
								</thead>
								<tbody>
<?php foreach (Utils::$context['profile_groups'] as $group): ?>
									<tr class="windowbg">
										<td class="half_table">
											<span <?= ($group['color'] ? 'style="color: ' . $group['color'] . '"' : '') ?>><?= $group['name'] ?></span>
<?php if (!empty($group['children'])): ?>
											<br>
											<span class="smalltext"><?= Lang::getTxt('permissions_includes_inherited', ['list' => Lang::sentenceList(array_map(fn($grp) => '"' . $grp . '"', $group['children']))], file: 'ManagePermissions') ?></span>
<?php endif; ?>
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[<?= $group['id'] ?>]" value="allow"<?= $group['new_topic'] == 'allow' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[<?= $group['id'] ?>]" value="moderate"<?= $group['new_topic'] == 'moderate' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[<?= $group['id'] ?>]" value="disallow"<?= $group['new_topic'] == 'disallow' ? ' checked' : '' ?>>
										</td>
<?php /* Guests can't have "own" permissions */ ?>
<?php if ($group['id'] == '-1'): ?>
										<td colspan="3"></td>
<?php else: ?>
										<td class="centercol">
											<input type="radio" name="replies_own[<?= $group['id'] ?>]" value="allow"<?= $group['replies_own'] == 'allow' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_own[<?= $group['id'] ?>]" value="moderate"<?= $group['replies_own'] == 'moderate' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_own[<?= $group['id'] ?>]" value="disallow"<?= $group['replies_own'] == 'disallow' ? ' checked' : '' ?>>
										</td>
<?php endif; ?>
										<td class="centercol">
											<input type="radio" name="replies_any[<?= $group['id'] ?>]" value="allow"<?= $group['replies_any'] == 'allow' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_any[<?= $group['id'] ?>]" value="moderate"<?= $group['replies_any'] == 'moderate' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_any[<?= $group['id'] ?>]" value="disallow"<?= $group['replies_any'] == 'disallow' ? ' checked' : '' ?>>
										</td>
<?php if (Config::$modSettings['attachmentEnable'] == 1): ?>
										<td class="centercol">
											<input type="radio" name="attachment[<?= $group['id'] ?>]" value="allow"<?= $group['attachment'] == 'allow' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="attachment[<?= $group['id'] ?>]" value="moderate"<?= $group['attachment'] == 'moderate' ? ' checked' : '' ?>>
										</td>
										<td class="centercol">
											<input type="radio" name="attachment[<?= $group['id'] ?>]" value="disallow"<?= $group['attachment'] == 'disallow' ? ' checked' : '' ?>>
										</td>
<?php endif; ?>
									</tr>
<?php endforeach; ?>
								</tbody>
							</table>
<?php endif; ?>
								<input type="submit" name="save_changes" value="<?= Lang::getTxt('permissions_commit', file: 'ManagePermissions') ?>" class="button">
								<input type="hidden" name="<?= Utils::$context['admin-mppm_token_var'] ?>" value="<?= Utils::$context['admin-mppm_token'] ?>">
						</form>
					</div><!-- #admin_form_wrapper -->