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
 * This template asks the user what messages they want to prune.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_prune', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=pm;sa=prune" method="post" accept-charset="UTF-8" onsubmit="return confirm('<?= Lang::getTxt('pm_prune_warning', file: 'PersonalMessage') ?>');">
				<p><?= Lang::getTxt('pm_prune_desc1', file: 'PersonalMessage') ?> <input type="text" name="age" size="3" value="14"> <?= Lang::getTxt('pm_prune_desc2', file: 'PersonalMessage') ?></p>
				<input type="submit" value="<?= Lang::getTxt('delete', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=pm;sa=removeall2" method="post" onsubmit="return confirm('<?= Lang::getTxt('pm_remove_all_warning', file: 'PersonalMessage') ?>');">
				<p><?= Lang::getTxt('pm_remove_all', file: 'PersonalMessage') ?></p>
				<input type="submit" value="<?= Lang::getTxt('delete_all_prune', file: 'PersonalMessage') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
		</div>