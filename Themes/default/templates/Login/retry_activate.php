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
 * Activate your account manually?
 */
?>

<?php /* Just ask them for their code so they can try it again... */ ?>
		<form action="<?= Config::$scripturl ?>?action=activate;u=<?= Utils::$context['member_id'] ?>" method="post" accept-charset="UTF-8">
			<div class="title_bar">
				<h3 class="titlebg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="roundframe">
				<dl>
<?php /* You didn't even have an ID? */ ?>
<?php if (empty(Utils::$context['member_id'])): ?>
					<dt><?= Lang::getTxt('invalid_activation_username', file: 'Login') ?></dt>
					<dd><input type="text" name="user" size="30"></dd>
<?php endif; ?>
					<dt><?= Lang::getTxt('invalid_activation_retry', file: 'Login') ?></dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>
				<p><input type="submit" value="<?= Lang::getTxt('invalid_activation_submit', file: 'Login') ?>" class="button"></p>
			</div>
		</form>