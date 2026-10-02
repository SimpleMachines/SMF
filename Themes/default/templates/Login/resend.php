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
 * The form for resending the activation code.
 */
?>

<?php /* Just ask them for their code so they can try it again... */ ?>
		<form action="<?= Config::$scripturl ?>?action=activate;sa=resend" method="post" accept-charset="UTF-8">
			<div class="title_bar">
				<h3 class="titlebg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt><?= Lang::getTxt('invalid_activation_username', file: 'Login') ?></dt>
					<dd><input type="text" name="user" size="40" value="<?= Utils::$context['default_username'] ?>"></dd>
				</dl>
				<p><?= Lang::getTxt('invalid_activation_new', file: 'Login') ?></p>
				<dl>
					<dt><?= Lang::getTxt('invalid_activation_new_email', file: 'Login') ?></dt>
					<dd><input type="text" name="new_email" size="40"></dd>
					<dt><?= Lang::getTxt('invalid_activation_password', file: 'Login') ?></dt>
					<dd><input type="password" name="passwd" size="30"></dd>
				</dl>
<?php if (Utils::$context['can_activate']): ?>
				<p><?= Lang::getTxt('invalid_activation_known', file: 'Login') ?></p>
				<dl>
					<dt><?= Lang::getTxt('invalid_activation_retry', file: 'Login') ?></dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>
<?php endif; ?>
				<p><input type="submit" value="<?= Lang::getTxt('invalid_activation_resend', file: 'Login') ?>" class="button"></p>
			</div><!-- .roundframe -->
		</form>