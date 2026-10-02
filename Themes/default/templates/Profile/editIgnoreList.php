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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing the ignore list of the current user.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
	<div class="infobox"><?= Lang::getTxt(Profile::$member->is_me ? 'profile_updated_own' : 'profile_updated_else', Utils::$context['member'], file: 'Profile') ?></div><?php elseif (!empty(Utils::$context['saved_failed'])): ?>
	<div class="errorbox"><?= Utils::$context['saved_failed'] ?></div><?php endif; ?>
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt('editIgnoreList', file: 'Profile') ?>
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link"><?= Lang::getTxt('name', file: 'General') ?></th>
					<th scope="col" class="buddy_status"><?= Lang::getTxt('status', file: 'General') ?></th><?php if (Utils::$context['can_moderate_forum']): ?>
					<th scope="col" class="buddy_email"><?= Lang::getTxt('email', file: 'General') ?></th><?php endif; ?>
					<th scope="col" class="buddy_remove"><?= Lang::getTxt('ignore_remove', file: 'Profile') ?></th>
				</tr>
			</thead>
			<tbody><?php /* If they don't have anyone on their ignore list, don't list it! */ ?><?php if (empty(Utils::$context['ignore_list'])): ?>
				<tr class="windowbg">
					<td colspan="<?= Utils::$context['can_moderate_forum'] ? '4' : '3' ?>">
						<strong><?= Lang::getTxt('no_ignore', file: 'Profile') ?></strong>
					</td>
				</tr><?php endif; ?><?php /* Now loop through each buddy showing info on each. */ ?><?php foreach (Utils::$context['ignore_list'] as $member): ?>
				<tr class="windowbg">
					<td class="buddy_link"><?= $member['link'] ?></td>
					<td class="centertext buddy_status">
						<a href="<?= $member['online']['href'] ?>"><span class="<?= ($member['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= $member['online']['text'] ?>"></span></a>
					</td><?php if (Utils::$context['can_moderate_forum']): ?>
					<td class="centertext buddy_email">
						<a href="mailto:<?= $member['email'] ?>" rel="nofollow"><span class="main_icons mail icon" title="<?= Lang::getTxt('email', file: 'General') ?> <?= $member['name'] ?>"></span></a>
					</td><?php endif; ?>
					<td class="centertext buddy_remove">
						<a href="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=lists;sa=ignore;remove=<?= $member['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="main_icons delete" title="<?= Lang::getTxt('ignore_remove', file: 'Profile') ?>"></span></a>
					</td>
				</tr><?php endforeach; ?>
			</tbody>
		</table>
	</div><!-- #edit_buddies --><?php /* Add to the ignore list? */ ?>
	<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=lists;sa=ignore" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('ignore_add', file: 'Profile') ?></h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_ignore"><strong><?= Lang::getTxt('who_member', file: 'General') ?></strong></label>
				</dt>
				<dd>
					<input type="text" name="new_ignore" id="new_ignore" size="30">
					<input type="submit" value="<?= Lang::getTxt('ignore_add_button', file: 'Profile') ?>" class="button">
				</dd>
			</dl>
		</div><?php if (!empty(Utils::$context['token_check'])): ?>
		<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?php endif; ?>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<script>
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'new_ignore',
			sControlId: 'new_ignore',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
		});
	</script>