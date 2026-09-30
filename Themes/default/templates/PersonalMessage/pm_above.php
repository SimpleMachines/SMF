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
 * This is for stuff above the menu in the personal messages section
 */
?>

	<div id="personal_messages"><?php /* Show the capacity bar, if available. */ ?><?php if (!empty(Utils::$context['limit_bar'])): ?>

		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft"><?= Lang::getTxt('pm_capacity', file: 'PersonalMessage') ?></span>
				<span class="floatleft capacity_bar">
					<span class="<?= Utils::$context['limit_bar']['percent'] > 85 ? 'full' : (Utils::$context['limit_bar']['percent'] > 40 ? 'filled' : 'empty') ?>" style="width: <?= Utils::$context['limit_bar']['percent'] / 10 ?>em;"></span>
				</span>
				<span class="floatright<?= Utils::$context['limit_bar']['percent'] > 90 ? ' alert' : '' ?>"><?= Utils::$context['limit_bar']['text'] ?></span>
			</h3>
		</div><?php endif; ?><?php /* Message sent? Show a small indication. */ ?><?php if (isset(Utils::$context['pm_sent'])): ?>

		<div class="infobox">
			<?= Lang::getTxt('pm_sent', file: 'PersonalMessage') ?>

		</div><?php endif; ?>
