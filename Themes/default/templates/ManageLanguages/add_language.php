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

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Add a new language
 *
 */
?>
		<form id="admin_form_wrapper"action="<?= Config::$scripturl ?>?action=admin;area=languages;sa=add;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('add_language', file: 'ManageSettings') ?>
				</h3>
			</div>
			<div class="windowbg">
				<fieldset>
					<legend><?= Lang::getTxt('add_language_smf', file: 'ManageSettings') ?></legend>
					<label class="smalltext"><?= Lang::getTxt('add_language_smf_browse', file: 'ManageSettings') ?></label>
					<input type="text" name="smf_add" size="40" value="<?= !empty(Utils::$context['smf_search_term']) ? Utils::$context['smf_search_term'] : '' ?>">
<?php /* Do we have some errors? Too bad. Display a little error box. */ ?>
<?php if (!empty(Utils::$context['smf_error'])): ?>
					<div>
						<br>
						<p class="errorbox"><?= Lang::getTxt('add_language_error_' . Utils::$context['smf_error'], file: 'ManageSettings') ?></p>
					</div>
<?php endif; ?>
				</fieldset>
				<?= BrowserDetector::isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;"> ' : '' ?>
				<input type="submit" name="smf_add_sub" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button">
				<br>
			</div><!-- .windowbg -->
<?php /* Had some results? */ ?>
<?php if (!empty(Utils::$context['smf_languages']['rows'])): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('add_language_found_title', file: 'ManageSettings') ?></h3>
			</div>
			<div class="information"><?= Lang::getTxt('add_language_smf_found', file: 'ManageSettings') ?></div><?php $this->subTemplate('show_list', ['list_id' => 'smf_languages']); ?>

<?php endif; ?>
		</form>