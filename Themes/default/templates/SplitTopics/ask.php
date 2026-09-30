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
 * The form that asks how you want to split things
 */
?>

	<div id="split_topics">
		<form action="<?= Config::$scripturl ?>?action=splittopics;sa=split;topic=<?= Utils::$context['current_topic'] ?>.0" method="post" accept-charset="UTF-8">
			<input type="hidden" name="at" value="<?= Utils::$context['message']['id'] ?>">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('split', file: 'General') ?></h3>
			</div>
			<div class="windowbg">
				<p class="split_topics">
					<strong><label for="subname"><?= Lang::getTxt('subject_new_topic', file: 'General') ?></label></strong>
					<input type="text" name="subname" id="subname" value="<?= Utils::$context['message']['subject'] ?>" size="25">
				</p>
				<ul class="split_topics">
					<li>
						<input type="radio" id="onlythis" name="step2" value="onlythis" checked> <label for="onlythis"><?= Lang::getTxt('split_this_post', file: 'General') ?></label>
					</li>
					<li>
						<input type="radio" id="afterthis" name="step2" value="afterthis"> <label for="afterthis"><?= Lang::getTxt('split_after_and_this_post', file: 'General') ?></label>
					</li>
					<li>
						<input type="radio" id="selective" name="step2" value="selective"> <label for="selective"><?= Lang::getTxt('select_split_posts', file: 'General') ?></label>
					</li>
				</ul>
				<hr>
				<div class="auto_flow">
					<input type="submit" value="<?= Lang::getTxt('split', file: 'General') ?>" class="button">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				</div>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #split_topics -->