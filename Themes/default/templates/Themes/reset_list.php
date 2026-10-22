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
 * This lets you reset themes
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('themeadmin_reset_title', file: 'Admin') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('themeadmin_reset_tip', file: 'Themes') ?>
		</div>
		<div id="admin_form_wrapper">
<?php /* Show each theme.... with X for delete and a link to settings. */ ?>
<?php foreach (Utils::$context['themes'] as $theme): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= $theme['name'] ?></h3>
			</div>
			<div class="windowbg">
				<ul>
					<li>
						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=reset"><?= Lang::getTxt('themeadmin_reset_defaults', file: 'Themes') ?></a> <em class="smalltext"><?= Lang::getTxt('themeadmin_reset_defaults_current', [$theme['num_default_options']], file: 'Themes') ?></em>
					</li>
					<li>
						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=reset;who=1"><?= Lang::getTxt('themeadmin_reset_members', file: 'Themes') ?></a>
					</li>
					<li>
						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=reset;who=2;<?= Utils::$context['admin-stor_token_var'] ?>=<?= Utils::$context['admin-stor_token'] ?>" data-confirm="<?= Lang::getTxt('themeadmin_reset_remove_confirm', file: 'Themes') ?>" class="you_sure"><?= Lang::getTxt('themeadmin_reset_remove', file: 'Themes') ?></a> <em class="smalltext"><?= Lang::getTxt('themeadmin_reset_remove_current', [$theme['num_members']], file: 'Themes') ?></em>
					</li>
				</ul>
			</div>
<?php endforeach; ?>
		</div><!-- #admin_form_wrapper -->