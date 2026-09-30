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
 * Before showing users a registration form, show them the registration agreement.
 */
?>

		<form action="<?= Config::$scripturl ?>?action=signup" method="post" accept-charset="UTF-8" id="registration">
<?php if (!empty(Utils::$context['agreement'])): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('registration_agreement', file: 'General') ?></h3>
			</div>
			<div class="roundframe">
				<div><?= Utils::adjustHeadingLevels(Utils::$context['agreement'], 3) ?></div>
			</div>
<?php endif; ?>
<?php if (!empty(Utils::$context['privacy_policy'])): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('privacy_policy', file: 'General') ?></h3>
			</div>
			<div class="roundframe">
				<div><?= Utils::adjustHeadingLevels(Utils::$context['privacy_policy'], 3) ?></div>
			</div>
<?php endif; ?>
			<div id="confirm_buttons">
<?php /* Age restriction in effect? */ ?>
<?php if (Utils::$context['show_coppa']): ?>
				<input type="submit" name="accept_agreement" value="<?= Utils::$context['coppa_agree_above'] ?>" class="button"><br>
				<br>
				<input type="submit" name="accept_agreement_coppa" value="<?= Utils::$context['coppa_agree_below'] ?>" class="button">
<?php else: ?>
				<input type="submit" name="accept_agreement" value="<?= Utils::$context['agree'] ?>" class="button" />
<?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['register_token_var'] ?>" value="<?= Utils::$context['register_token'] ?>">
				<input type="hidden" name="step" value="1">
			</div>
		</form>