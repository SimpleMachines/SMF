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
 * Okay, that theme was installed/updated successfully!
 */
?>

<?php /* The aftermath. */ ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
		</div>
		<div class="windowbg">
<?php /* Oops! there was an error :( */ ?>
<?php if (!empty(Utils::$context['error_message'])): ?>
			<p>
				<?= Utils::$context['error_message'] ?>
			</p>
<?php /* Not much to show except a link back... */ ?>
<?php else: ?>
			<p>
				<a href="<?= Config::$scripturl ?>?action=admin;area=theme;sa=list;th=<?= Utils::$context['installed_theme']['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Utils::$context['installed_theme']['name'] ?></a> <?= Lang::getTxt('theme_' . (isset(Utils::$context['installed_theme']['updated']) ? 'updated' : 'installed') . '_message', file: 'Themes') ?>
			</p>
			<p>
				<a href="<?= Config::$scripturl ?>?action=admin;area=theme;sa=admin;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('back', file: 'General') ?></a>
			</p>
<?php endif; ?>
		</div><!-- .windowbg -->