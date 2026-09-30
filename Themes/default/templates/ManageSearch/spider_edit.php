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
 * Add or edit a search engine spider.
 */
?>
	<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=sengines;sa=editspiders;sid=<?= Utils::$context['spider']['id'] ?>" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
		</div>
		<div class="information noup">
			<?= Lang::getTxt('add_spider_desc', file: 'Search') ?>
		</div>
		<div class="windowbg noup">
			<dl class="settings">
				<dt>
					<strong><label for="spider_name"><?= Lang::getTxt('spider_name', file: 'Search') ?></label></strong><br>
					<span class="smalltext"><?= Lang::getTxt('spider_name_desc', file: 'Search') ?></span>
				</dt>
				<dd>
					<input type="text" name="spider_name" id="spider_name" value="<?= Utils::$context['spider']['name'] ?>">
				</dd>
				<dt>
					<strong><label for="spider_agent"><?= Lang::getTxt('spider_agent', file: 'Search') ?></label></strong><br>
					<span class="smalltext"><?= Lang::getTxt('spider_agent_desc', file: 'Search') ?></span>
				</dt>
				<dd>
					<input type="text" name="spider_agent" id="spider_agent" value="<?= Utils::$context['spider']['agent'] ?>">
				</dd>
				<dt>
					<strong><label for="spider_ip"><?= Lang::getTxt('spider_ip_info', file: 'Search') ?></label></strong><br>
					<span class="smalltext"><?= Lang::getTxt('spider_ip_info_desc', file: 'Search') ?></span>
				</dt>
				<dd>
					<textarea name="spider_ip" id="spider_ip" rows="4" cols="20"><?= Utils::$context['spider']['ip_info'] ?></textarea>
				</dd>
			</dl>
			<hr>
			<input type="submit" name="save" value="<?= Utils::$context['page_title'] ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-ses_token_var'] ?>" value="<?= Utils::$context['admin-ses_token'] ?>">
		</div><!-- .windowbg -->
	</form>