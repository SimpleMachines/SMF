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
 * Modifying a smiley set.
 */
?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=editsets" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
			<?= Lang::getTxt(Utils::$context['current_set']['is_new'] ? 'smiley_set_new' : 'smiley_set_modify_existing', file: 'ManageSmileys') ?>

			</h3>
		</div>
<?php if (Utils::$context['current_set']['is_new'] && !empty(Config::$modSettings['smiley_enable'])): ?>
		<div class="information noup">
			<?= Lang::getTxt('smiley_set_import_info', file: 'ManageSmileys') ?>

		</div>
<?php /* If this is an existing set, and there are still un-added smileys - offer an import opportunity. */ ?>
<?php elseif (!empty(Utils::$context['current_set']['can_import'])): ?>
		<div class="information noup">
			<?= Utils::$context['smiley_set_unused_message'] ?>

		</div>
<?php endif; ?>
		<div class="windowbg noup">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_sets_name"><?= Lang::getTxt('smiley_sets_name', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="smiley_sets_name" id="smiley_sets_name" value="<?= Utils::$context['current_set']['name'] ?>">
				</dd>
				<dt>
					<strong><label for="smiley_sets_path"><?= Lang::getTxt('smiley_sets_url', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<?= Config::$modSettings['smileys_url'] ?>/
<?php if (empty(Utils::$context['smiley_set_dirs']) || !empty(Utils::$context['make_new'])): ?>
					<input type="text" name="smiley_sets_path" id="smiley_sets_path" value="<?= Utils::$context['current_set']['path'] ?>"> 
<?php else: ?>
					<select name="smiley_sets_path" id="smiley_sets_path">
<?php foreach (Utils::$context['smiley_set_dirs'] as $smiley_set_dir): ?>
						<option value="<?= $smiley_set_dir['id'] ?>"<?= $smiley_set_dir['current'] ? ' selected' : '' ?><?= $smiley_set_dir['selectable'] ? '' : ' disabled' ?>><?= $smiley_set_dir['id'] ?></option>
<?php endforeach; ?>
					</select> 
<?php endif; ?>
					/..
				</dd>
				<dt>
					<strong><label for="smiley_sets_default"><?= Lang::getTxt('smiley_set_select_default', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="checkbox" name="smiley_sets_default" id="smiley_sets_default" value="1"<?= Utils::$context['current_set']['is_default'] ? ' checked' : '' ?>>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="<?= Lang::getTxt('smiley_sets_save', file: 'ManageSmileys') ?>" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		<input type="hidden" name="<?= Utils::$context['admin-mss_token_var'] ?>" value="<?= Utils::$context['admin-mss_token'] ?>">
		<input type="hidden" name="set" value="<?= Utils::$context['current_set']['path'] ?>">
	</form>