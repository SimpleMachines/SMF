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
 * This lists all themes
 */
?><?php /* Show a nice confirmation message. */ ?><?php if (isset($_GET['done'])): ?>

	<div class="infobox">
		<?= Lang::getTxt('theme_confirmed_' . $_GET['done'], file: 'Themes') ?>

	</div><?php endif; ?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('themeadmin_list_heading', file: 'Themes') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('themeadmin_list_tip', file: 'Themes') ?>

		</div>
		<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=theme;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=list" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('theme_settings', file: 'Admin') ?></h3>
			</div>
			<br><?php /* Show each theme.... with X for delete, an enable/disable link and a link to their own settings page. */ ?><?php foreach (Utils::$context['themes'] as $theme): ?>

			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft">
						<?= $theme['name'] ?><?= (!empty($theme['version']) ? ' <em>(' . $theme['version'] . ')</em>' : '') ?>

					</span>
					<span class="floatright">
						<?= (!empty($theme['enable']) || $theme['id'] == 1 ? '<a href="' . Config::$scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=list"><span class="main_icons settings"></span></a>' : '') ?><?php /* You *cannot* disable/enable/delete the default theme. It's important! */ ?><?php if ($theme['id'] != 1): ?><?php /* Enable/Disable. */ ?>

						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;sa=enable;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;<?= Utils::$context['admin-tre_token_var'] ?>=<?= Utils::$context['admin-tre_token'] ?><?= (!empty($theme['enable']) ? ';disabled' : '') ?>" data-confirm="<?= Lang::getTxt('theme_' . (!empty($theme['enable']) ? 'disable' : 'enable') . '_confirm', file: 'Themes') ?>" class="you_sure"><span class="main_icons <?= !empty($theme['enable']) ? 'disable' : 'enable' ?>" title="<?= Lang::getTxt('theme_' . (!empty($theme['enable']) ? 'disable' : 'enable'), file: 'Themes') ?>"></span></a><?php /* Deleting. */ ?>

						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;sa=remove;th=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;<?= Utils::$context['admin-tr_token_var'] ?>=<?= Utils::$context['admin-tr_token'] ?>" data-confirm="<?= Lang::getTxt('theme_remove_confirm', file: 'Themes') ?>" class="you_sure"><span class="main_icons delete" title="<?= Lang::getTxt('theme_remove', file: 'Themes') ?>"></span></a><?php endif; ?>

					</span>
				</h3>
			</div><!-- .cat_bar -->
			<div class="windowbg">
				<dl class="settings themes_list">
					<dt><?= Lang::getTxt('themeadmin_list_theme_dir', file: 'Themes') ?></dt>
					<dd<?= $theme['valid_path'] ? '' : ' class="error"' ?>><?= $theme['theme_dir'] ?><?= $theme['valid_path'] ? '' : ' ' . Lang::getTxt('themeadmin_list_invalid', file: 'Themes') ?></dd>
					<dt><?= Lang::getTxt('themeadmin_list_theme_url', file: 'Themes') ?></dt>
					<dd><?= $theme['theme_url'] ?></dd>
					<dt><?= Lang::getTxt('themeadmin_list_images_url', file: 'Themes') ?></dt>
					<dd><?= $theme['images_url'] ?></dd>
				</dl>
			</div><?php endforeach; ?>

			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('themeadmin_list_reset', file: 'Themes') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="reset_dir"><?= Lang::getTxt('themeadmin_list_reset_dir', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" name="reset_dir" id="reset_dir" value="<?= Utils::$context['reset_dir'] ?>" size="40">
					</dd>
					<dt>
						<label for="reset_url"><?= Lang::getTxt('themeadmin_list_reset_url', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" name="reset_url" id="reset_url" value="<?= Utils::$context['reset_url'] ?>" size="40">
					</dd>
				</dl>
				<input type="submit" name="save" value="<?= Lang::getTxt('themeadmin_list_reset_go', file: 'Themes') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-tl_token_var'] ?>" value="<?= Utils::$context['admin-tl_token'] ?>">
			</div>
		</form>