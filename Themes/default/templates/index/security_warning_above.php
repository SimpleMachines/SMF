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
 * The upper part of the security warning box
 */
?>
	<div class="errorbox">
		<p class="alert">!!</p>
		<h3><?= Lang::getTxt(!isset(Utils::$context['warnings']['file']) && empty(Utils::$context['auth_secret_missing']) ? 'generic_warning' : 'security_risk', file: 'General') ?></h3>
<?php foreach ((Utils::$context['warnings']['file'] ?? []) as $security_file): ?>
		<p><?= Lang::getTxt($security_file[0], $security_file[1], file: 'General') ?></p>
<?php endforeach; ?>
<?php for ($i = 0, $n = count(Utils::$context['warnings']) - 1; $i < $n; $i++): ?>
<?php if (is_string(Utils::$context['warnings'][$i])): ?>
		<p><?= Utils::$context['warnings'][$i] ?></p>
<?php else: ?>
		<p><?= Lang::getTxt(Utils::$context['warnings'][$i][0], Utils::$context['warnings'][$i][1] ?? [], file: 'General') ?></p>
<?php endif; ?>
<?php endfor; ?>
	</div>