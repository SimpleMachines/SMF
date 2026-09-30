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
 * The page shown while the newsletter is being sent
 */
?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=email_members" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?= Lang::getTxt('admin_newsletters', file: 'Admin') ?>
				</h3>
			</div>
			<div class="windowbg">
				<div class="progress_bar">
					<span><?= Lang::getTxt('email_done', Utils::$context, file: 'Admin') ?></span>
					<div class="bar" style="width: <?= Utils::$context['percentage_done'] ?>%;"></div>
				</div>
				<hr>
				<input type="submit" name="cont" value="<?= Lang::getTxt('email_continue', file: 'Admin') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="subject" value="<?= Utils::$context['subject'] ?>">
				<input type="hidden" name="message" value="<?= Utils::$context['message'] ?>">
				<input type="hidden" name="start" value="<?= Utils::$context['start'] ?>">
				<input type="hidden" name="total_members" value="<?= Utils::$context['total_members'] ?>">
				<input type="hidden" name="total_emails" value="<?= Utils::$context['total_emails'] ?>">
				<input type="hidden" name="send_pm" value="<?= Utils::$context['send_pm'] ?>">
				<input type="hidden" name="send_html" value="<?= Utils::$context['send_html'] ?>">
				<input type="hidden" name="parse_html" value="<?= Utils::$context['parse_html'] ?>">
<?php /* All the things we must remember! */ ?>
<?php foreach (Utils::$context['recipients'] as $key => $values): ?>
				<input type="hidden" name="<?= $key ?>" value="<?= implode(($key == 'emails' ? ';' : ','), $values) ?>">
<?php endforeach; ?>
			</div><!-- .windowbg -->
		</form>

		<script>
			doAutoSubmit(2, <?= Utils::escapeJavaScript(Lang::getTxt('email_continue', file: 'Admin')) ?>);
		</script>