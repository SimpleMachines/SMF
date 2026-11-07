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
 * Choose which type of report to run?
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('generate_reports_type', file: 'Reports') ?></h3>
		</div>
		<form action="<?= Config::$scripturl ?>?action=admin;area=reports" method="post" accept-charset="UTF-8" class="windowbg option_form">
<?php /* Go through each type of report they can run. */ ?>
<?php foreach (Utils::$context['report_types'] as $type): ?>
			<label>
				<input type="radio" name="rt" value="<?= $type['id'] ?>"<?= $type['is_first'] ? ' checked' : '' ?>>
				<strong><?= $type['title'] ?></strong>
<?php if (isset($type['description'])): ?>
				<p><?= $type['description'] ?></p>
<?php endif; ?>
			</label>
<?php endforeach; ?>
			<input type="submit" name="continue" value="<?= Lang::getTxt('generate_reports_continue', file: 'Reports') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form>