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
 * Repairing boards.
 */
?>
						<div id="section_header" class="cat_bar">
							<h3 class="catbg"><?= Utils::$context['error_search'] ? Lang::getTxt('errors_list', file: 'Admin') : Lang::getTxt('errors_fixing', file: 'Admin') ?>
							</h3>
						</div>
						<div class="windowbg"><?php /* Are we actually fixing them, or is this just a prompt? */ ?><?php if (Utils::$context['error_search']): ?><?php if (!empty(Utils::$context['to_fix'])): ?>
							<?= Lang::getTxt('errors_found', file: 'Admin') ?>
							<ul><?php foreach (Utils::$context['repair_errors'] as $error): ?>
								<li>
									<?= $error ?>
								</li><?php endforeach; ?>
							</ul>
							<p>
								<?= Lang::getTxt('errors_fix', file: 'Admin') ?>
							</p>
							<p class="padding">
								<strong><a href="<?= Config::$scripturl ?>?action=admin;area=repairboards;fixErrors;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;<?= Utils::$context['admin-repairboards_token_var'] ?>=<?= Utils::$context['admin-repairboards_token'] ?>"><?= Lang::getTxt('yes', file: 'General') ?></a> - <a href="<?= Config::$scripturl ?>?action=admin;area=maintain"><?= Lang::getTxt('no', file: 'General') ?></a></strong>
							</p><?php else: ?>
							<p><?= Lang::getTxt('maintain_no_errors', file: 'Admin') ?></p>
							<p class="padding">
								<a href="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=routine"><?= Lang::getTxt('maintain_return', file: 'ManageMaintenance') ?></a>
							</p><?php endif; ?><?php else: ?><?php if (!empty(Utils::$context['redirect_to_recount'])): ?>
							<p>
								<?= Lang::getTxt('errors_do_recount', file: 'Admin') ?>
							</p>
							<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=routine;activity=recount" id="recount_form" method="post">
								<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
								<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
								<input type="submit" name="recount" id="recount_now" value="<?= Lang::getTxt('errors_recount_now', file: 'Admin') ?>">
							</form><?php else: ?>
							<p><?= Lang::getTxt('errors_fixed', file: 'Admin') ?></p>
							<p class="padding">
								<a href="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=routine"><?= Lang::getTxt('maintain_return', file: 'ManageMaintenance') ?></a>
							</p><?php endif; ?><?php endif; ?>
						</div><!-- .windowbg --><?php if (!empty(Utils::$context['redirect_to_recount'])): ?>
					<script>
						doAutoSubmit(5, "<?= Utils::escapeJavaScript(Lang::getTxt('errors_recount_now', file: 'Admin')) ?>", "recount_form", "recount");
					</script><?php endif; ?>
