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
use SMF\Time;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

// The main sub template - show the agreement and/or privacy policy
?><?php if (!empty(Utils::$context['accept_doc'])): ?>

	<form action="<?= Config::$scripturl ?>?action=acceptagreement;doc=<?= Utils::$context['accept_doc'] ?>" method="post"><?php endif; ?><?php if (!empty(Utils::$context['agreement'])): ?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt(!empty(Utils::$context['can_accept_agreement']) ? 'agreement_updated' : 'registration_agreement', file: 'General+Agreement') ?></h3>
		</div><?php if (!empty(Utils::$context['can_accept_agreement'])): ?>

		<div class="information noup">
			<?= Lang::getTxt('agreement_updated_desc', file: 'Agreement') ?>

		</div><?php elseif (!empty(Utils::$context['agreement_accepted_date'])): ?>

		<div class="information noup">
			<?= Lang::getTxt('agreement_accepted', ['date' => Time::create('@' . Utils::$context['agreement_accepted_date'])->format(null, false)], file: 'Agreement') ?>

		</div><?php endif; ?>

		<div class="windowbg noup">
			<?= Utils::adjustHeadingLevels(Utils::$context['agreement'], 3) ?>

		</div><?php endif; ?><?php if (!empty(Utils::$context['privacy_policy'])): ?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('privacy_policy' . (!empty(Utils::$context['can_accept_privacy_policy']) ? '_updated' : ''), file: 'General+Agreement') ?></h3>
		</div><?php if (!empty(Utils::$context['can_accept_privacy_policy'])): ?>

		<div class="information noup">
			<?= Lang::getTxt('privacy_policy_updated_desc', file: 'Agreement') ?>

		</div><?php elseif (!empty(Utils::$context['privacy_policy_accepted_date'])): ?>

		<div class="information noup">
			<?= Lang::getTxt('privacy_policy_accepted', ['date' => Time::create('@' . Utils::$context['privacy_policy_accepted_date'])->format(null, false)], file: 'Agreement') ?>

		</div><?php endif; ?>

		<div class="windowbg noup">
			<?= Utils::adjustHeadingLevels(Utils::$context['privacy_policy'], 3) ?>

		</div><?php endif; ?><?php if (!empty(Utils::$context['accept_doc'])): ?>

		<div id="confirm_buttons"><?php if (!empty(Utils::$context['document_has_visible_changes'])): ?>

			<a class="button" href="<?= Config::$scripturl ?>?action=agreement<?= (!isset($_GET['diff']) ? ';diff' : '') ?>"><?= Lang::getTxt((!isset($_GET['diff']) ? 'diff' : 'nodiff'), file: 'Agreement') ?></a><?php endif; ?>

			<input type="submit" value="<?= Lang::getTxt('agree', file: 'Agreement') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</div>
	</form><?php endif; ?>
