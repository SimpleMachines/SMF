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
 * Little template just to say "Yep, it's been submitted"
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_report_title', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('pm_report_done', file: 'PersonalMessage') ?></p>
			<a href="<?= Config::$scripturl ?>?action=pm;l=<?= Utils::$context['current_label_id'] ?>"><?= Lang::getTxt('pm_report_return', file: 'PersonalMessage') ?></a>
		</div>