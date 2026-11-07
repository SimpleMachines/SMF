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
 * Template for showing and managing the buddy list.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
	<div class="infobox"><?= Lang::getTxt(Profile::$member->is_me ? 'profile_updated_own' : 'profile_updated_else', Utils::$context['member'], file: 'Profile') ?></div><?php elseif (!empty(Utils::$context['saved_failed'])): ?>
	<div class="errorbox"><?= Utils::$context['saved_failed'] ?></div><?php endif; ?>
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons people icon"></span> <?= Lang::getTxt('editBuddies', file: 'Profile') ?>
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col" class="quarter_table buddy_link"><?= Lang::getTxt('name', file: 'General') ?></th>
					<th scope="col" class="buddy_status"><?= Lang::getTxt('status', file: 'General') ?></th><?php if (Utils::$context['can_moderate_forum']): ?>
					<th scope="col" class="buddy_email"><?= Lang::getTxt('email', file: 'General') ?></th><?php endif; ?><?php if (!empty(Utils::$context['custom_pf'])): ?><?php foreach (Utils::$context['custom_pf'] as $column): ?>
					<th scope="col" class="buddy_custom_fields"><?= $column['label'] ?></th><?php endforeach; ?><?php endif; ?>
					<th scope="col" class="buddy_remove"><?= Lang::getTxt('remove', file: 'General') ?></th>
				</tr>
			</thead>
			<tbody><?php /* If they don't have any buddies don't list them! */ ?><?php if (empty(Utils::$context['buddies'])): ?>
				<tr class="windowbg">
					<td colspan="<?= Utils::$context['can_moderate_forum'] ? '10' : '9' ?>">
						<strong><?= Lang::getTxt('no_buddies', file: 'Profile') ?></strong>
					</td>
				</tr><?php /* Now loop through each buddy showing info on each. */ ?><?php else: ?><?php foreach (Utils::$context['buddies'] as $buddy): ?>
				<tr class="windowbg">
					<td class="buddy_link"><?= $buddy['link'] ?></td>
					<td class="centertext buddy_status">
						<a href="<?= $buddy['online']['href'] ?>"><span class="<?= ($buddy['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= $buddy['online']['text'] ?>"></span></a>
					</td><?php if ($buddy['show_email']): ?>
					<td class="buddy_email centertext">
						<a href="mailto:<?= $buddy['email'] ?>" rel="nofollow"><span class="main_icons mail icon" title="<?= Lang::getTxt('email', file: 'General') ?> <?= $buddy['name'] ?>"></span></a>
					</td><?php endif; ?><?php /* Show the custom profile fields for this user. */ ?><?php if (!empty(Utils::$context['custom_pf'])): ?><?php foreach (Utils::$context['custom_pf'] as $key => $column): ?>
					<td class="centertext buddy_custom_fields"><?= $buddy['options'][$key] ?></td><?php endforeach; ?><?php endif; ?>
					<td class="centertext buddy_remove">
						<a href="<?= Config::$scripturl ?>?action=profile;area=lists;sa=buddies;u=<?= Utils::$context['id_member'] ?>;remove=<?= $buddy['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="main_icons delete" title="<?= Lang::getTxt('buddy_remove', file: 'Profile') ?>"></span></a>
					</td>
				</tr><?php endforeach; ?><?php endif; ?>
			</tbody>
		</table>
	</div><!-- #edit_buddies --><?php /* Add a new buddy? */ ?>
	<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=lists;sa=buddies" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('buddy_add', file: 'Profile') ?></h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong><?= Lang::getTxt('who_member', file: 'General') ?>:</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_buddy" id="new_buddy" size="30">
					<input type="submit" value="<?= Lang::getTxt('buddy_add_button', file: 'Profile') ?>" class="button floatnone">
				</dd>
			</dl>
		</div><?php if (!empty(Utils::$context['token_check'])): ?>
		<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?php endif; ?>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<script>
		var oAddBuddySuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'new_buddy',
			sControlId: 'new_buddy',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
		});
	</script>