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
 * Template to show for deleting a user's account - now with added delete post capability!
 */
?>

<?php /* The main containing header. */ ?>
		<form action="<?= Config::$scripturl ?>?action=profile;area=deleteaccount;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					<?= Lang::getTxt('deleteAccount', file: 'Profile') ?>
				</h3>
			</div>
<?php /* If deleting another account give them a lovely info box. */ ?>
<?php if (!Profile::$member->is_me): ?>
			<p class="information"><?= Lang::getTxt('deleteAccount_desc', file: 'Profile') ?></p>
<?php endif; ?>
			<div class="windowbg">
<?php /* If they are deleting their account AND the admin needs to approve it - give them another piece of info ;) */ ?>
<?php if (Utils::$context['needs_approval']): ?>
				<div class="noticebox"><?= Lang::getTxt('deleteAccount_approval', file: 'Profile') ?></div>
<?php endif; ?>
<?php /* If the user is deleting their own account warn them first - and require a password! */ ?>
<?php if (Profile::$member->is_me): ?>
				<div class="errorbox"><?= Lang::getTxt('own_profile_confirm', file: 'Profile') ?></div>
				<fieldset>
<?php if (!empty(Config::$modSettings['always_anonymize_deleted_accounts'])): ?>
					<?= Lang::getTxt('deleteAccount_anonymize_forced', file: 'Profile') ?>

<?php else: ?>
					<label for="anonymize">
						<input type="checkbox" name="anonymize" id="anonymize" value="1"> <?= Lang::getTxt('deleteAccount_anonymize', file: 'Profile') ?>
					</label>
<?php endif; ?>
				</fieldset>
				<fieldset>
					<legend><?= Lang::getTxt('required_security_reasons', file: 'Profile') ?></legend>
					<strong<?= (isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '') ?>><?= Lang::getTxt('current_password', file: 'Profile') ?></strong>
					<input type="password" name="oldpasswrd" size="20">
				</fieldset>
				<input type="submit" value="<?= Lang::getTxt('delete', file: 'General') ?>" class="button floatright">
<?php if (!empty(Utils::$context['token_check'])): ?>
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
<?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
				<input type="hidden" name="sa" value="<?= Utils::$context['menu_item_selected'] ?>">
<?php /* Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts! */ ?>
<?php else: ?>
				<div class="errorbox"><?= Lang::getTxt('deleteAccount_warning', file: 'Profile') ?></div>
<?php /* Only actually give these options if they are kind of important. */ ?>
<?php if (Utils::$context['can_delete_posts']): ?>
				<fieldset>
					<label for="deleteVotes">
						<input type="checkbox" name="deleteVotes" id="deleteVotes" value="1"> <?= Lang::getTxt('deleteAccount_votes', file: 'Profile') ?>
					</label>
				</fieldset>
				<fieldset>
					<label for="deletePosts">
						<input type="checkbox" name="deletePosts" id="deletePosts" value="1"> <?= Lang::getTxt('deleteAccount_posts', file: 'Profile') ?>
					</label>
					<select name="remove_type">
						<option value="posts"><?= Lang::getTxt('deleteAccount_all_posts', file: 'Profile') ?></option>
						<option value="topics"><?= Lang::getTxt('deleteAccount_topics', file: 'Profile') ?></option>
					</select>
<?php if (Utils::$context['show_perma_delete']): ?>
					<br>
					<label for="perma_delete"><input type="checkbox" name="perma_delete" id="perma_delete" value="1"> <?= Lang::getTxt('deleteAccount_permanent', file: 'Profile') ?></label>
<?php endif; ?>
				</fieldset>
<?php endif; ?>
				<fieldset>
					<label for="deleteAccount">
						<input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" onclick="if (this.checked) return confirm('<?= Lang::getTxt('deleteAccount_confirm', file: 'Profile') ?>');"> <?= Lang::getTxt('deleteAccount_member', file: 'Profile') ?>
					</label>
					<br>
<?php if (!empty(Config::$modSettings['always_anonymize_deleted_accounts'])): ?>
					<?= Lang::getTxt('deleteAccount_anonymize_forced', file: 'Profile') ?>

<?php else: ?>
					<label for="anonymize">
						<input type="checkbox" name="anonymize" id="anonymize" value="1"> <?= Lang::getTxt('deleteAccount_anonymize', file: 'Profile') ?>
					</label>
<?php endif; ?>
				</fieldset>
				<div>
					<input type="submit" value="<?= Lang::getTxt('delete', file: 'General') ?>" class="button floatright">
<?php if (!empty(Utils::$context['token_check'])): ?>
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
<?php endif; ?>
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
					<input type="hidden" name="sa" value="<?= Utils::$context['menu_item_selected'] ?>">
				</div>
<?php endif; ?>
			</div><!-- .windowbg -->
			<br>
		</form>