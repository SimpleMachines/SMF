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
 * This is just a really little helper to avoid duplicating code unnecessarily
 *
 * @param string $type The type of avatar
 */
?><?php
$w = !empty(Config::$modSettings['avatar_max_width_' . $type]) ? Lang::numberFormat(Config::$modSettings['avatar_max_width_' . $type]) : 0;
$h = !empty(Config::$modSettings['avatar_max_height_' . $type]) ? Lang::numberFormat(Config::$modSettings['avatar_max_height_' . $type]) : 0;
$suffix = (!empty($w) ? 'w' : '') . (!empty($h) ? 'h' : '');
?><?php if (empty($suffix)): ?><?php return; ?><?php endif; ?>
								<div class="smalltext"><?= Lang::getTxt('avatar_max_size_' . $suffix, ['w' => $w, 'h' => $h], file: 'Profile') ?></div>