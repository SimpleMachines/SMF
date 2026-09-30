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
 * This is the page shown when we've temporarily paused things such as during maintenance tasks, sending newsletters, etc.
 */
?>
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								<?= Lang::getTxt('not_done_title', file: 'Admin') ?>
							</h3>
						</div>
						<div class="windowbg">
							<?= Lang::getTxt('not_done_reason', file: 'Admin') ?>

<?php if (!empty(Utils::$context['continue_percent'])): ?>
							<div class="progress_bar">
								<span><?= Utils::$context['continue_percent'] ?>%</span>
								<div class="bar" style="width: <?= Utils::$context['continue_percent'] ?>%;"></div>
							</div>
<?php endif; ?>
<?php if (!empty(Utils::$context['substep_enabled'])): ?>
							<div class="progress_bar progress_blue">
								<span><?= Utils::$context['substep_title'] ?> (<?= Utils::$context['substep_continue_percent'] ?>%)</span>
								<div class="bar" style="width: <?= Utils::$context['substep_continue_percent'] ?>%;"></div>
							</div>
<?php endif; ?>
							<form action="<?= Config::$scripturl ?><?= Utils::$context['continue_get_data'] ?>" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
<?php /* Do we have a token? */ ?>
<?php if (isset(Utils::$context['not_done_token'], Utils::$context[Utils::$context['not_done_token'] . '_token'],Utils::$context[Utils::$context['not_done_token'] . '_token_var'])): ?>
							<input type="hidden" name="<?= Utils::$context[Utils::$context['not_done_token'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['not_done_token'] . '_token'] ?>">
<?php endif; ?>
								<input type="submit" name="cont" value="<?= Lang::getTxt('not_done_continue', file: 'Admin') ?>" class="button">
								<?= Utils::$context['continue_post_data'] ?>
							</form>
						</div><!-- .windowbg -->
					<script>
						doAutoSubmit(<?= Utils::$context['continue_countdown'] ?>, "<?= Lang::getTxt('not_done_continue', file: 'Admin') ?>");
					</script>