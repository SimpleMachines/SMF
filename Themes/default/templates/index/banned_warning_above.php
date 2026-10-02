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
use SMF\Time;
use SMF\User;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The upper part of the ban warning box
 */
?>
	<div class="noticebox">
		<p><?= Lang::getTxt('you_are_post_banned', ['name' => User::$me->is_guest ? Lang::getTxt('guest_title', file: 'General') : User::$me->name]) ?></p>
<?php if (!empty($_SESSION['ban']['cannot_post']['reason'])): ?>
		<p><?= $_SESSION['ban']['cannot_post']['reason'] ?></p>
<?php endif; ?>
<?php if (!empty($_SESSION['ban']['expire_time'])): ?>
		<p><?= Lang::getTxt('your_ban_expires', ['datetime' => Time::create('@' . $_SESSION['ban']['expire_time'])->format(null, false)], file: 'General') ?></p>
<?php else: ?>
		<p><?= Lang::getTxt('your_ban_expires_never', file: 'General') ?></p>
<?php endif; ?>
	</div>