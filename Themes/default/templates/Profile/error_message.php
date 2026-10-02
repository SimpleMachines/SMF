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
use SMF\Profile;
use SMF\Security;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Small template for showing an error message upon a save problem in the profile.
 */
?>
		<div class="errorbox" <?= empty(Utils::$context['post_errors']) ? 'style="display:none" ' : '' ?>id="profile_error">
<?php if (!empty(Utils::$context['post_errors'])): ?>
			<span><?= !empty(Utils::$context['custom_error_title']) ? Utils::$context['custom_error_title'] : Lang::getTxt('profile_errors_occurred', file: 'Errors') ?></span>
			<ul id="list_errors">
<?php /* Cycle through each error and display an error message. */ ?>
<?php foreach (Utils::$context['post_errors'] as $error): ?>
<?php if ($error == 'password_short'): ?>
<?php $error_message = Lang::getTxt('profile_error_' . $error, [Security::minimumPasswordLength()], file: 'Profile'); ?>
<?php else: ?>
<?php $error_message = Lang::txtExists('profile_error_' . $error, file: 'Profile') ? Lang::getTxt('profile_error_' . $error, file: 'Profile') : $error; ?>
<?php endif; ?>
				<li><?= $error_message ?></li>
<?php endforeach; ?>
			</ul>
<?php endif; ?>
		</div><!-- #profile_error -->