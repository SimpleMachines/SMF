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
 * Display a progress page while creating a search index.
 */
?>

	<form action="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=createmsgindex;step=1" name="autoSubmit" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('search_create_index', file: 'Search') ?></h3>
		</div>
		<div class="windowbg">
			<div>
				<p><?= Lang::getTxt('search_create_index_not_ready', file: 'Search') ?></p>
				<div class="progress_bar">
					<span><?= Utils::$context['percentage'] ?>%</span>
					<div class="bar" style="width: <?= Utils::$context['percentage'] ?>%;"></div>
				</div>
			</div>
			<hr>
			<input type="submit" name="cont" value="<?= Lang::getTxt('search_create_index_continue', file: 'Search') ?>" class="button">
		</div>
		<input type="hidden" name="step" value="<?= Utils::$context['step'] ?>">
		<input type="hidden" name="start" value="<?= Utils::$context['start'] ?>">
		<input type="hidden" name="bytes_per_word" value="<?= Utils::$context['index_settings']['bytes_per_word'] ?>">
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>
	<script>
		doAutoSubmit(10, <?= Utils::escapeJavaScript(Lang::getTxt('search_create_index_continue', file: 'Search')) ?>);
	</script>