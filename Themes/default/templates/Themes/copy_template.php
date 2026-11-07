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
 * The page allowing you to copy a template from one theme to another.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('themeadmin_edit_filename', file: 'Themes') ?></h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('themeadmin_edit_copy_warning', file: 'Themes') ?>
		</div>
		<div class="windowbg">
			<ul class="theme_options"><?php foreach (Utils::$context['available_templates'] as $template): ?>
				<li class="flow_hidden windowbg">
					<span class="floatleft"><?= $template['filename'] ?><?= $template['already_exists'] ? ' <span class="error">(' . Lang::getTxt('themeadmin_edit_exists', file: 'Themes') . ')</span>' : '' ?></span>
					<span class="floatright"><?php if ($template['can_copy']): ?>
						<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=copy;template=<?= $template['value'] ?>" data-confirm="<?= Lang::getTxt($template['already_exists'] ? 'themeadmin_edit_overwrite_confirm' : 'themeadmin_edit_copy_confirm', file: 'Themes') ?>" class="you_sure"><?= Lang::getTxt('themeadmin_edit_do_copy', file: 'Themes') ?></a><?php else: ?><?= Lang::getTxt('themeadmin_edit_no_copy', file: 'Themes') ?><?php endif; ?>
					</span>
				</li><?php endforeach; ?>
			</ul>
		</div><!-- .windowbg -->