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
 * The form for sending out an announcement
 */
?>
	<div id="announcement">
		<form action="<?= Config::$scripturl ?>?action=announce;sa=send" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('announce_title', file: 'Post') ?></h3>
			</div>
			<div class="information">
				<?= Lang::getTxt('announce_desc', file: 'Post') ?>
			</div>
			<div class="windowbg">
				<p>
					<?= Lang::getTxt('announce_this_topic', ['subject' => '<a href="' . Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.0">' . Utils::$context['topic_subject'] . '</a>'], file: 'Post') ?>
				</p>
				<ul>
<?php foreach (Utils::$context['groups'] as $group): ?>
					<li>
						<label for="who_<?= $group['id'] ?>"><input type="checkbox" name="who[<?= $group['id'] ?>]" id="who_<?= $group['id'] ?>" value="<?= $group['id'] ?>" checked> <?= $group['name'] ?></label> <em>(<?= $group['member_count'] ?? Lang::getTxt('not_applicable', file: 'General') ?>)</em>
					</li>
<?php endforeach; ?>
					<li>
						<label for="checkall"><input type="checkbox" id="checkall" onclick="invertAll(this, this.form);" checked> <em><?= Lang::getTxt('check_all', file: 'General') ?></em></label>
					</li>
				</ul>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" value="<?= Lang::getTxt('post', file: 'General') ?>" class="button">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="topic" value="<?= Utils::$context['current_topic'] ?>">
					<input type="hidden" name="move" value="<?= Utils::$context['move'] ?>">
					<input type="hidden" name="goback" value="<?= Utils::$context['go_back'] ?>">
				</div>
				<br class="clear_right">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #announcement -->
	<br>