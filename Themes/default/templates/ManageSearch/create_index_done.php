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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Done creating a search index.
 */
?>

	<div class="cat_bar">
		<h3 class="catbg"><?= Lang::getTxt('search_create_index', file: 'Search') ?></h3>
	</div>
	<div class="windowbg">
		<p><?= Lang::getTxt('search_create_index_done', file: 'Search') ?></p>
		<p>
			<strong><a href="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=method"><?= Lang::getTxt('search_create_index_done_link', file: 'Search') ?></a></strong>
		</p>
	</div>