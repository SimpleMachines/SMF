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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for viewing a user's warnings
 */
?><?php $this->subTemplate('load_warning_variables'); ?>
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt('profile_viewwarning_for_user', Utils::$context['member'], file: 'Profile') ?>
			</h3>
		</div>
		<p class="information"><?= Lang::getTxt('viewWarning_help', file: 'Profile') ?></p>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><?= Lang::getTxt('profile_warning_name', file: 'Profile') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['member']['name'] ?>
				</dd>
				<dt>
					<strong><?= Lang::getTxt('profile_warning_level', file: 'Profile') ?></strong>
				</dt>
				<dd>
					<div class="generic_bar warning_level <?= Utils::$context['current_warning_mode'] ?>">
						<div class="bar" style="width: <?= Utils::$context['member']['warning'] ?>%;"></div>
						<span><?= Utils::$context['member']['warning'] ?>%</span>
					</div>
				</dd><?php /* There's some impact of this? */ ?><?php if (!empty(Utils::$context['level_effects'][Utils::$context['current_level']])): ?>
				<dt>
					<strong><?= Lang::getTxt('profile_viewwarning_impact', file: 'Profile') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['level_effects'][Utils::$context['current_level']] ?>
				</dd><?php endif; ?>
			</dl>
		</div><!-- .windowbg --><?php $this->subTemplate('show_list', ['list_id' => 'view_warnings']); ?>
