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

// @todo
/*	This template file contains only the sub template fatal_error. It is
	shown when an error occurs, and should show at least a back button and
	Utils::$context['error_message'].
*/
/*
 * THis displays a fatal error message
 */
?><?php if (!empty(Utils::$context['simple_action'])): ?>

	<strong>
		<?= Utils::$context['error_title'] ?>

	</strong><br>
	<div <?= Utils::$context['error_code'] ?>class="padding">
		<?= Utils::$context['error_message'] ?>

	</div><?php else: ?>

	<div id="fatal_error">
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Utils::$context['error_title'] ?>

			</h3>
		</div>
		<div class="windowbg">
			<div <?= Utils::$context['error_code'] ?>class="padding">
				<?= Utils::$context['error_message'] ?>

			</div>
		</div>
	</div><?php /* Show a back button */ ?>

	<div class="centertext">
		<a class="button floatnone" href="<?= Utils::$context['error_link'] ?>"><?= Lang::getTxt('back', file: 'General') ?></a>
	</div><?php endif; ?>
