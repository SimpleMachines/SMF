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
 * Template for listing all scheduled tasks.
 */
?><?php /* We completed some tasks? */ ?><?php if (!empty(Utils::$context['tasks_were_run'])): ?><?php if (empty(Utils::$context['scheduled_errors'])): ?>

	<div class="infobox">
		<?= Lang::getTxt('scheduled_tasks_were_run', file: 'ManageScheduledTasks') ?>

	</div><?php else: ?>

	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious"><?= Lang::getTxt('scheduled_tasks_were_run_errors', file: 'ManageScheduledTasks') ?></strong>
			</dt><?php foreach (Utils::$context['scheduled_errors'] as $task => $errors): ?>

			<dd class="error">
				<strong><?= Lang::txtExists('scheduled_task_' . $task, file: 'ManageScheduledTasks') ? Lang::getTxt('scheduled_task_' . $task, file: 'ManageScheduledTasks') : $task ?></strong>
				<ul>
					<li><?= implode('</li><li>', $errors) ?></li>
				</ul>
			</dd><?php endforeach; ?>

		</dl>
	</div><?php endif; ?><?php endif; ?><?php $this->subTemplate('show_list', ['list_id' => 'scheduled_tasks']); ?>
