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
 * The page for editing themes.
 */
?>
	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('themeadmin_edit_title', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
<?php foreach (Utils::$context['themes'] as $theme): ?>
			<fieldset>
				<legend>
					<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=edit"><?= $theme['name'] ?></a><?= !empty($theme['version']) ? '
					<em>(' . $theme['version'] . ')</em>' : '' ?>
				</legend>
				<ul>
					<li><a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=edit"><?= Lang::getTxt('themeadmin_edit_browse', file: 'Themes') ?></a></li><?= $theme['can_edit_style'] ? '
					<li><a href="' . Config::$scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=css">' . Lang::getTxt('themeadmin_edit_style', file: 'Themes') . '</a></li>' : '' ?>
					<li><a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=copy"><?= Lang::getTxt('themeadmin_edit_copy_template', file: 'Themes') ?></a></li>
				</ul>
			</fieldset>
<?php endforeach; ?>
		</div><!-- .windowbg -->
	</div><!-- #admin_form_wrapper -->