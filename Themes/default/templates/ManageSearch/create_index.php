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
 * Create a search index.
 */
?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=createmsgindex;step=1" method="post" accept-charset="UTF-8" name="create_index">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('search_create_index', file: 'Search') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<label for="predefine_select"><?= Lang::getTxt('search_predefined', file: 'Search') ?></label>
				</dt>
				<dd>
					<select name="bytes_per_word" id="predefine_select">
						<option value="2"><?= Lang::getTxt('search_predefined_small', file: 'Search') ?></option>
						<option value="4" selected><?= Lang::getTxt('search_predefined_moderate', file: 'Search') ?></option>
						<option value="5"><?= Lang::getTxt('search_predefined_large', file: 'Search') ?></option>
					</select>
				</dd>
			</dl>
			<hr>
			<input type="submit" name="save" value="<?= Lang::getTxt('search_create_index_start', file: 'Search') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</div>
	</form>