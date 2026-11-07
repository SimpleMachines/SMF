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
 * A simple confirmation that things were split as expected, with links to the current board and the old and new topics.
 */
?>
	<div id="split_topics">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('split', file: 'General') ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('split_successful', file: 'General') ?></p>
			<ul>
				<li>
					<a href="<?= Config::$scripturl ?>?board=<?= Utils::$context['current_board'] ?>.0"><?= Lang::getTxt('message_index', file: 'General') ?></a>
				</li>
				<li>
					<a href="<?= Config::$scripturl ?>?topic=<?= Utils::$context['old_topic'] ?>.0"><?= Lang::getTxt('origin_topic', file: 'General') ?></a>
				</li>
				<li>
					<a href="<?= Config::$scripturl ?>?topic=<?= Utils::$context['new_topic'] ?>.0"><?= Lang::getTxt('new_topic', file: 'General') ?></a>
				</li>
			</ul>
		</div><!-- .windowbg -->
	</div><!-- #split_topics -->