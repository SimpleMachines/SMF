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
 * Show... spider... stats...
 */
?><?php /* Standard fields. */ ?><?php $this->subTemplate('show_list', ['list_id' => 'spider_stat_list']); ?>
		<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=sengines;sa=stats" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('spider_logs_delete', file: 'Search') ?></h3>
			</div>
			<div class="windowbg">
				<p>
					<?= Lang::getTxt('spider_stats_delete_older', ['input' => '<input type="number" name="older" id="older" value="90" size="3">'], file: 'Search') ?>
				</p>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-ss_token_var'] ?>" value="<?= Utils::$context['admin-ss_token'] ?>">
				<input type="submit" name="delete_entries" value="<?= Lang::getTxt('spider_logs_delete_submit', file: 'Search') ?>" onclick="if (document.getElementById('older').value &lt; 1 &amp;&amp; !confirm('<?= addcslashes(Lang::getTxt('spider_logs_delete_confirm', file: 'Search'), "'") ?>')) return false; return true;" class="button">
				<br>
			</div>
		</form>