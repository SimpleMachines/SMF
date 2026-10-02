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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Lists the tasks of a maintenance sub-action as a single pick-one form.
 *
 * Utils::$context['options'] is keyed by activity name. A task can override its
 * label and description with 'title' and 'info', and add markup after the
 * description with 'after'.
 */
?>
		<form action="<?= Utils::$context['post_url'] ?>" method="post" accept-charset="UTF-8" class="windowbg option_form">
<?php foreach (Utils::$context['options'] as $activity => $option): ?>
			<label>
				<input type="radio" name="activity" value="<?= $activity ?>">
				<strong><?= $option['title'] ?? Lang::getTxt('maintain_' . $activity, file: 'ManageMaintenance') ?></strong>
				<p><?= $option['info'] ?? Lang::getTxt('maintain_' . $activity . '_info', file: 'ManageMaintenance') ?><?= $option['after'] ?? '' ?></p>
			</label>
<?php endforeach; ?>
			<input type="submit" value="<?= Lang::getTxt('maintain_run_now', file: 'ManageMaintenance') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
		</form>