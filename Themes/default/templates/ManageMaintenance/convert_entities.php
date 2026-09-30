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
 * Template for converting entities to UTF-8 characters
 */
?>

	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('entity_convert_title', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('entity_convert_introduction', file: 'ManageMaintenance') ?></p>
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=database;activity=convertentities;start=0;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
			<input type="submit" value="<?= Lang::getTxt('entity_convert_proceed', file: 'ManageMaintenance') ?>" class="button">
			</form>
		</div>
	</div>