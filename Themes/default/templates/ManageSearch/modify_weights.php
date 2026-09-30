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
 * Modify the search weights.
 */
?>

	<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=weights" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('search_weights', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_frequency" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a><label for="weight1_val">
					<?= Lang::getTxt('search_weight_frequency', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_frequency" id="weight1_val" value="<?= empty(Config::$modSettings['search_weight_frequency']) ? '0' : Config::$modSettings['search_weight_frequency'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight1" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_frequency'] ?>%</span>
				</dd>
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_age" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
					<label for="weight2_val"><?= Lang::getTxt('search_weight_age', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_age" id="weight2_val" value="<?= empty(Config::$modSettings['search_weight_age']) ? '0' : Config::$modSettings['search_weight_age'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight2" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_age'] ?>%</span>
				</dd>
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_length" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
					<label for="weight3_val"><?= Lang::getTxt('search_weight_length', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_length" id="weight3_val" value="<?= empty(Config::$modSettings['search_weight_length']) ? '0' : Config::$modSettings['search_weight_length'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight3" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_length'] ?>%</span>
				</dd>
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_subject" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
					<label for="weight4_val"><?= Lang::getTxt('search_weight_subject', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_subject" id="weight4_val" value="<?= empty(Config::$modSettings['search_weight_subject']) ? '0' : Config::$modSettings['search_weight_subject'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight4" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_subject'] ?>%</span>
				</dd>
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_first_message" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
					<label for="weight5_val"><?= Lang::getTxt('search_weight_first_message', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_first_message" id="weight5_val" value="<?= empty(Config::$modSettings['search_weight_first_message']) ? '0' : Config::$modSettings['search_weight_first_message'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight5" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_first_message'] ?>%</span>
				</dd>
				<dt>
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_weight_sticky" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
					<label for="weight6_val"><?= Lang::getTxt('search_weight_sticky', file: 'Search') ?></label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_sticky" id="weight6_val" value="<?= empty(Config::$modSettings['search_weight_sticky']) ? '0' : Config::$modSettings['search_weight_sticky'] ?>" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight6" class="search_weight"><?= Utils::$context['relative_weights']['search_weight_sticky'] ?>%</span>
				</dd>
				<dt>
					<strong><?= Lang::getTxt('search_weights_total', file: 'Search') ?></strong>
				</dt>
				<dd>
					<span id="weighttotal" class="search_weight">
						<strong><?= Utils::$context['relative_weights']['total'] ?></strong>
					</span>
					<span class="search_weight"><strong>100%</strong></span>
				</dd>
			</dl>
			<input type="submit" name="save" value="<?= Lang::getTxt('search_weights_save', file: 'Search') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-msw_token_var'] ?>" value="<?= Utils::$context['admin-msw_token'] ?>">
		</div><!-- .windowbg -->
	</form>