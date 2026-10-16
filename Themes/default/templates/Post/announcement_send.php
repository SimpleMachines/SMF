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
 * The confirmation/progress page, displayed after the admin has clicked the button to send the announcement.
 */
?>
	<div id="announcement">
		<form action="<?= Config::$scripturl ?>?action=announce;sa=send" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="windowbg">
				<p>
					<?= Lang::getTxt('announce_sending', ['subject' => '<a href="' . Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.0" target="_blank" rel="noopener">' . Utils::$context['topic_subject'] . '</a>'], file: 'Post') ?>
				</p>
				<div class="progress_bar">
					<span><?= Lang::getTxt('announce_done', Utils::$context, file: 'Post') ?></span>
					<div class="bar" style="width: <?= Utils::$context['percentage_done'] ?>%;"></div>
				</div>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" name="cont" value="<?= Lang::getTxt('announce_continue', file: 'Post') ?>" class="button">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="topic" value="<?= Utils::$context['current_topic'] ?>">
					<input type="hidden" name="move" value="<?= Utils::$context['move'] ?>">
					<input type="hidden" name="goback" value="<?= Utils::$context['go_back'] ?>">
					<input type="hidden" name="start" value="<?= Utils::$context['start'] ?>">
					<input type="hidden" name="membergroups" value="<?= Utils::$context['membergroups'] ?>">
				</div>
				<br class="clear_right">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #announcement -->
	<br>
	<script>
		doAutoSubmit(2, <?= Utils::escapeJavaScript(Lang::getTxt('announce_continue', file: 'Post')) ?>);
	</script>