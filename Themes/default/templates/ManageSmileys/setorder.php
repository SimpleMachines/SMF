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
 * Ordering smileys.
 */
?><?php foreach (Utils::$context['smileys'] as $location): ?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= $location['title'] ?></h3>
		</div>
		<div class="information noup">
			<?= $location['description'] ?>
		</div>
		<div class="move_smileys windowbg noup">
			<strong><?= Lang::getTxt(empty(Utils::$context['move_smiley']) ? 'smileys_move_select_smiley' : 'smileys_move_select_destination', file: 'ManageSmileys') ?></strong><br><?php foreach ($location['rows'] as $row): ?><?php if (!empty(Utils::$context['move_smiley'])): ?>
			<a href="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=setorder;location=<?= $location['id'] ?>;source=<?= Utils::$context['move_smiley'] ?>;row=<?= $row[0]['row'] ?>;reorder=1;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="main_icons select_below" title="<?= Lang::getTxt('smileys_move_here', file: 'ManageSmileys') ?>"></span></a><?php endif; ?><?php foreach ($row as $smiley): ?><?php if (empty(Utils::$context['move_smiley'])): ?>
			<a href="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=setorder;move=<?= $smiley['id'] ?>"><img src="<?= Config::$modSettings['smileys_url'] ?>/<?= Config::$modSettings['smiley_sets_default'] ?>/<?= $smiley['filename'] ?>" alt="<?= $smiley['description'] ?>"></a><?php else: ?>
			<img src="<?= Config::$modSettings['smileys_url'] ?>/<?= Config::$modSettings['smiley_sets_default'] ?>/<?= $smiley['filename'] ?>" alt="<?= $smiley['description'] ?>" <?= $smiley['selected'] ? 'class="selected_item"' : '' ?>>
			<a href="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=setorder;location=<?= $location['id'] ?>;source=<?= Utils::$context['move_smiley'] ?>;after=<?= $smiley['id'] ?>;reorder=1;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" title="<?= Lang::getTxt('smileys_move_here', file: 'ManageSmileys') ?>"><span class="main_icons select_below" title="<?= Lang::getTxt('smileys_move_here', file: 'ManageSmileys') ?>"></span></a><?php endif; ?><?php endforeach; ?>
			<br><?php endforeach; ?><?php if (!empty(Utils::$context['move_smiley'])): ?>
			<a href="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=setorder;location=<?= $location['id'] ?>;source=<?= Utils::$context['move_smiley'] ?>;row=<?= $location['last_row'] ?>;reorder=1;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="main_icons select_below" title="<?= Lang::getTxt('smileys_move_here', file: 'ManageSmileys') ?>"></span></a><?php endif; ?>
		</div><!-- .windowbg -->
		<input type="hidden" name="reorder" value="1">
	</form><?php endforeach; ?>
