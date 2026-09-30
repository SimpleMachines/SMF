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
 * Confirmation page shown when finished merging topics.
 */
?>
		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('merge', file: 'General') ?></h3>
			</div>
			<div class="windowbg">
				<p><?= Lang::getTxt('merge_successful', file: 'General') ?></p>
				<br>
				<ul>
					<li>
						<a href="<?= Config::$scripturl ?>?board=<?= Utils::$context['target_board'] ?>.0"><?= Lang::getTxt('message_index', file: 'General') ?></a>
					</li>
					<li>
						<a href="<?= Config::$scripturl ?>?topic=<?= Utils::$context['target_topic'] ?>.0"><?= Lang::getTxt('new_merged_topic', file: 'General') ?></a>
					</li>
				</ul>
			</div>
		</div>
		<br class="clear">