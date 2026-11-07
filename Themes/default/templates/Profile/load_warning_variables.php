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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Simply loads some theme variables common to several warning templates.
 */
?><?php
// Setup the warning mode
Utils::$context['warning_mode'] = [
	0 => 'none',
	Config::$modSettings['warning_watch'] => 'watched',
	Config::$modSettings['warning_moderate'] => 'moderated',
	Config::$modSettings['warning_mute'] => 'muted',
];
// Work out the starting warning.
Utils::$context['current_warning_mode'] = Utils::$context['warning_mode'][0];
?><?php foreach (Utils::$context['warning_mode'] as $limit => $warning): ?><?php if (Utils::$context['member']['warning'] >= $limit): ?><?php Utils::$context['current_warning_mode'] = $warning; ?><?php endif; ?><?php endforeach; ?>
