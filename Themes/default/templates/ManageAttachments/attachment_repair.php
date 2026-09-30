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
 * The file repair page
 */
?><?php /* If we've completed just let them know! */ ?><?php if (Utils::$context['completed']): ?>

	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('repair_attachments_complete', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<?= Lang::getTxt('repair_attachments_complete_desc', file: 'Admin') ?>

		</div>
	</div><?php /* What about if no errors were even found? */ ?><?php elseif (!Utils::$context['errors_found']): ?>

	<div id="manage_attachments">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('repair_attachments_complete', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<?= Lang::getTxt('repair_attachments_no_errors', file: 'Admin') ?>

		</div>
	</div><?php /* Otherwise, I'm sad to say, we have a problem! */ ?><?php else: ?>

	<div id="manage_attachments">
		<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('repair_attachments', file: 'Admin') ?></h3>
			</div>
			<div class="windowbg">
				<p><?= Lang::getTxt('repair_attachments_error_desc', file: 'Admin') ?></p><?php /* Loop through each error reporting the status */ ?><?php foreach (Utils::$context['repair_errors'] as $error => $number): ?><?php if (!empty($number)): ?>

				<input type="checkbox" name="to_fix[]" id="<?= $error ?>" value="<?= $error ?>">
				<label for="<?= $error ?>"><?= Lang::getTxt('attach_repair_' . $error, [$number], file: 'Admin') ?></label><br><?php endif; ?><?php endforeach; ?>

				<br>
				<input type="submit" value="<?= Lang::getTxt('repair_attachments_continue', file: 'Admin') ?>" class="button">
				<input type="submit" name="cancel" value="<?= Lang::getTxt('repair_attachments_cancel', file: 'Admin') ?>" class="button">
			</div>
		</form>
	</div><!-- #manage_attachments --><?php endif; ?>
