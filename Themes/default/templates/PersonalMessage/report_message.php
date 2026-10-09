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
 * Template for reporting a personal message.
 */
?>
	<form action="<?= Config::$scripturl ?>?action=pm;sa=report;l=<?= Utils::$context['current_label_id'] ?>" method="post" accept-charset="UTF-8">
		<input type="hidden" name="pmsg" value="<?= Utils::$context['pm_id'] ?>">
		<div class="information">
			<?= Lang::getTxt('pm_report_desc', file: 'PersonalMessage') ?>
		</div>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_report_title', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
<?php
// If there is more than one admin on the forum, allow the user to choose the one they want to direct to.
// @todo Why?
?>
<?php if (Utils::$context['admin_count'] > 1): ?>
				<dt>
					<strong><?= Lang::getTxt('pm_report_admins', file: 'PersonalMessage') ?></strong>
				</dt>
				<dd>
					<select name="id_admin">
						<option value="0"><?= Lang::getTxt('pm_report_all_admins', file: 'PersonalMessage') ?></option>
<?php foreach (Utils::$context['admins'] as $id => $name): ?>
						<option value="<?= $id ?>"><?= $name ?></option>
<?php endforeach; ?>
					</select>
				</dd>
<?php endif; ?>
				<dt>
					<strong><?= Lang::getTxt('pm_report_reason', file: 'PersonalMessage') ?></strong>
				</dt>
				<dd>
					<textarea name="reason" rows="4" cols="70" style="width: 80%;"></textarea>
				</dd>
			</dl>
			<div class="righttext">
				<input type="submit" name="report" value="<?= Lang::getTxt('pm_report_message', file: 'PersonalMessage') ?>" class="button">
			</div>
		</div><!-- .windowbg -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>