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
 * Simple template for showing results of our optimization...
 */
?>

	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('maintain_optimize', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<p>
				<?= Utils::$context['database_numb_tables'] ?><br>
				<?= Lang::getTxt('database_optimize_attempt', file: 'ManageMaintenance') ?><br>
<?php /* List each table being optimized... */ ?>
<?php foreach (Utils::$context['optimized_tables'] as $table): ?>
				<?= Lang::getTxt('database_optimizing', [$table['name'], round($table['data_freed'], 2)], file: 'ManageMaintenance') ?><br>
<?php endforeach; ?>
<?php /* How did we go? */ ?>
				<br>
				<?= Utils::$context['num_tables_optimized'] == 0 ? Lang::getTxt('database_already_optimized', file: 'ManageMaintenance') : Utils::$context['num_tables_optimized'] . ' ' . Lang::getTxt('database_optimized', file: 'ManageMaintenance') ?>

			</p>
			<p><a href="<?= Config::$scripturl ?>?action=admin;area=maintain"><?= Lang::getTxt('maintain_return', file: 'ManageMaintenance') ?></a></p>
		</div><!-- .windowbg -->
	</div><!-- #manage_maintenance -->