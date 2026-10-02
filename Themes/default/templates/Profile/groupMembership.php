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
 * Template for choosing group membership.
 */
?>

<?php /* The main containing header. */ ?>
		<form action="<?= Config::$scripturl ?>?action=profile;area=groupmembership;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					<?= Lang::getTxt('profile', file: 'General') ?>
				</h3>
			</div>
			<p class="information"><?= Lang::getTxt('groupMembership_info', file: 'Profile') ?></p>
<?php /* Do we have an update message? */ ?>
<?php if (!empty(Utils::$context['update_message'])): ?>
			<div class="infobox">
				<?= Utils::$context['update_message'] ?>
			</div>
<?php endif; ?>
			<div id="groups">
<?php /* Requesting membership to a group? */ ?>
<?php if (!empty(Utils::$context['group_request'])): ?>
			<div class="groupmembership">
				<div class="cat_bar">
					<h3 class="catbg"><?= Lang::getTxt('request_group_membership', file: 'Profile') ?></h3>
				</div>
				<div class="roundframe">
					<span><?= Lang::getTxt('request_group_membership_desc', file: 'Profile') ?></span>
					<textarea name="reason" rows="4"></textarea>
					<div class="righttext">
						<input type="hidden" name="gid" value="<?= Utils::$context['group_request']['id'] ?>">
						<input type="submit" name="req" value="<?= Lang::getTxt('submit_request', file: 'Profile') ?>" class="button">
						</div>
					</div>
				</div><!-- .groupmembership -->
<?php else: ?>
				<div class="title_bar">
					<h3 class="titlebg"><?= Lang::getTxt('current_membergroups', file: 'Profile') ?></h3>
				</div>
<?php foreach (Utils::$context['groups']['member'] as $group): ?>
				<div class="windowbg" id="primdiv_<?= $group['id'] ?>">
<?php if (Utils::$context['can_edit_primary']): ?>
					<input type="radio" name="primary" id="primary_<?= $group['id'] ?>" value="<?= $group['id'] ?>"<?= $group['is_primary'] ? ' checked' : '' ?><?= $group['can_be_primary'] ? '' : ' disabled' ?>>
<?php endif; ?>
					<label for="primary_<?= $group['id'] ?>"><strong><?= (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>') ?></strong><?= (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : '') ?></label>
<?php /* Can they leave their group? */ ?>
<?php if ($group['can_leave']): ?>
					<a href="<?= Config::$scripturl ?>?action=profile;save;u=<?= Utils::$context['id_member'] ?>;area=groupmembership;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;gid=<?= $group['id'] ?>;<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>=<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?= Lang::getTxt('leave_group', file: 'Profile') ?></a>
<?php endif; ?>
				</div><!-- .windowbg -->
<?php endforeach; ?>
<?php if (Utils::$context['can_edit_primary']): ?>
				<div class="padding righttext">
					<input type="submit" value="<?= Lang::getTxt('make_primary', file: 'Profile') ?>" class="button">
				</div>
<?php endif; ?>
<?php /* Any groups they can join? */ ?>
<?php if (!empty(Utils::$context['groups']['available'])): ?>
				<div class="title_bar">
					<h3 class="titlebg"><?= Lang::getTxt('available_groups', file: 'Profile') ?></h3>
				</div>
<?php foreach (Utils::$context['groups']['available'] as $group): ?>
				<div class="windowbg">
					<strong><?= (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>') ?></strong><?= (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : '') ?>

<?php if ($group['type'] == 3): ?>
					<a href="<?= Config::$scripturl ?>?action=profile;save;u=<?= Utils::$context['id_member'] ?>;area=groupmembership;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;gid=<?= $group['id'] ?>;<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>=<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>" class="button floatright"><?= Lang::getTxt('join_group', file: 'Profile') ?></a>
<?php elseif ($group['type'] == 2 && $group['pending']): ?>
					<span class="floatright"><?= Lang::getTxt('approval_pending', file: 'Profile') ?></span>
<?php elseif ($group['type'] == 2): ?>
					<a href="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=groupmembership;request=<?= $group['id'] ?>" class="button floatright"><?= Lang::getTxt('request_group', file: 'Profile') ?></a>
<?php endif; ?>
				</div><!-- .windowbg -->
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
			</div><!-- #groups -->
<?php if (!empty(Utils::$context['token_check'])): ?>
			<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
<?php endif; ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
		</form>