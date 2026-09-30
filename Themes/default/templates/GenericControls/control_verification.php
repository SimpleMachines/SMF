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
use SMF\Verifier;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Displays a verification form with CAPTCHA or question-based challenges.
 *
 * Used to validate user input for forms, supporting various verification
 * mechanisms such as CAPTCHA images, reCAPTCHA, and custom questions.
 *
 * @param int|string $verify_id The unique identifier for the verification control.
 * @param string $display_type Determines how to display items:
 *   - `'single'`: Displays one item at a time (e.g., in a loop).
 *   - `'all'`: Displays all items together.
 * @param bool $reset Whether to reset the internal tracking counter.
 *
 * @return ?bool Returns `false` if no items are left to display, `true` if displaying a single item, and `null` otherwise.
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['display_type' => 'all', 'reset' => false], EXTR_SKIP);
?><?php
$verify_context = Verifier::$loaded[$verify_id];
// Reset tracking if necessary.
?><?php if (empty($verify_context->tracking) || $reset): ?><?php $verify_context->tracking = 0; ?><?php endif; ?><?php
$total_items = count($verify_context->questions) + ($verify_context->show_visual || $verify_context->can_recaptcha ? 1 : 0);
// Stop if all items are processed.
?><?php if ($verify_context->tracking > $total_items): ?><?php return false; ?><?php endif; ?><?php /* Loop through each item to show them. */ ?><?php for ($i = 0; $i < $total_items; $i++): ?><?php /* If we're after a single item only show it if we're in the right place. */ ?><?php if ($display_type == 'single' && $verify_context->tracking != $i): ?><?php continue; ?><?php endif; ?><?php if ($display_type != 'single'): ?>

			<div id="verification_control_<?= $i ?>" class="verification_control"><?php endif; ?><?php /* Display empty field, but only if we have one, and it's the first time. */ ?><?php if ($verify_context->empty_field && empty($i)): ?>

				<div class="smalltext vv_special">
					<?= Lang::getTxt('visual_verification_hidden', file: 'General') ?>

					<input type="text" name="<?= $_SESSION[$verify_id . '_vv']['empty_field'] ?>" autocomplete="off" size="30" value="">
				</div><?php endif; ?><?php /* Do the actual stuff */ ?><?php if ($i == 0 && ($verify_context->show_visual || $verify_context->can_recaptcha)): ?><?php if ($verify_context->show_visual): ?><?php if (Utils::$context['use_graphic_library']): ?>

				<img src="<?= $verify_context->image_href ?>" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>"><?php else: ?>

				<img src="<?= $verify_context->image_href ?>;letter=1" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_1">
				<img src="<?= $verify_context->image_href ?>;letter=2" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_2">
				<img src="<?= $verify_context->image_href ?>;letter=3" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_3">
				<img src="<?= $verify_context->image_href ?>;letter=4" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_4">
				<img src="<?= $verify_context->image_href ?>;letter=5" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_5">
				<img src="<?= $verify_context->image_href ?>;letter=6" alt="<?= Lang::getTxt('visual_verification_description', file: 'General') ?>" id="verification_image_<?= $verify_id ?>_6"><?php endif; ?>

				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="<?= $verify_context->image_href ?>;sound" id="visual_verification_<?= $verify_id ?>_sound" rel="nofollow"><?= Lang::getTxt('visual_verification_sound', file: 'General') ?></a> / <a href="#visual_verification_<?= $verify_id ?>_refresh" id="visual_verification_<?= $verify_id ?>_refresh"><?= Lang::getTxt('visual_verification_request_new', file: 'General') ?></a><?= $display_type != 'quick_reply' ? '<br>' : '' ?><br>
					<?= Lang::getTxt('visual_verification_description', file: 'General') ?><?= $display_type != 'quick_reply' ? '<br>' : '' ?>

					<input type="text" name="<?= $verify_id ?>_vv[code]" value="" size="30" autocomplete="off" required>
				</div><?php endif; ?><?php if ($verify_context->can_recaptcha): ?><?php $lang = Lang::getTxt(Lang::txtExists('lang_recaptcha', file: 'General') ? 'lang_recaptcha' : 'lang_dictionary', file: 'General'); ?>

				<div class="g-recaptcha centertext" data-sitekey="<?= $verify_context->recaptcha_site_key ?>" data-theme="<?= $verify_context->recaptcha_theme ?>"></div>
				<br>
				<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=<?= $lang ?>"></script><?php endif; ?><?php else: ?><?php
// Where in the question array is this question?
$qIndex = $verify_context->show_visual || $verify_context->can_recaptcha ? $i - 1 : $i;
?><?php if (isset($verify_context->questions[$qIndex])): ?>

				<div class="smalltext">
					<?= $verify_context->questions[$qIndex]['q'] ?>:<br>
					<input type="text" name="<?= $verify_id ?>_vv[q][<?= $verify_context->questions[$qIndex]['id'] ?>]" size="30" value="<?= $verify_context->questions[$qIndex]['a'] ?>" <?= $verify_context->questions[$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '' ?> required>
				</div><?php endif; ?><?php endif; ?><?php if ($display_type != 'single'): ?>

			</div><!-- #verification_control_[i] --><?php endif; ?><?php /* If we were displaying just one and we did it, break. */ ?><?php if ($display_type == 'single' && $verify_context->tracking == $i): ?><?php break; ?><?php endif; ?><?php endfor; ?><?php
// Assume we found something, always.
$verify_context->tracking++;
// Tell something displaying piecemeal to keep going.
?><?php if ($display_type == 'single'): ?><?php return true; ?><?php endif; ?><?php return; ?>
