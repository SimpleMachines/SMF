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
 * Template for editing reserved words.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
	<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?>
	<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('admin_reserved_set', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<h4><?= Lang::getTxt('admin_reserved_line', file: 'Admin') ?></h4>
			<textarea cols="30" rows="6" name="reserved" id="reserved"><?= implode("\n", Utils::$context['reserved_words']) ?></textarea>
			<dl class="settings">
				<dt>
					<label for="matchword"><?= Lang::getTxt('admin_match_whole', file: 'Admin') ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="matchword" id="matchword"<?= Utils::$context['reserved_word_options']['match_word'] ? ' checked' : '' ?>>
				</dd>
				<dt>
					<label for="matchcase"><?= Lang::getTxt('admin_match_case', file: 'Admin') ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="matchcase" id="matchcase"<?= Utils::$context['reserved_word_options']['match_case'] ? ' checked' : '' ?>>
				</dd>
				<dt>
					<label for="matchuser"><?= Lang::getTxt('admin_check_user', file: 'Admin') ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="matchuser" id="matchuser"<?= Utils::$context['reserved_word_options']['match_user'] ? ' checked' : '' ?>>
				</dd>
				<dt>
					<label for="matchname"><?= Lang::getTxt('admin_check_display', file: 'Admin') ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="matchname" id="matchname"<?= Utils::$context['reserved_word_options']['match_name'] ? ' checked' : '' ?>>
				</dd>
			</dl>
			<div class="flow_auto">
				<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" name="save_reserved_names" class="button">
				<input type="hidden" name="sa" value="reservednames">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-regr_token_var'] ?>" value="<?= Utils::$context['admin-regr_token'] ?>">
			</div>
		</div><!-- .windowbg -->
	</form>