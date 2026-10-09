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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The main moderation center.
 */
?><?php /* Show moderators notes. */ ?><?php $this->subTemplate('notes'); ?><?php /* Show a welcome message to the user. */ ?>
	<div id="modcenter"><?php /* Show all the blocks they want to see. */ ?><?php foreach (Utils::$context['mod_blocks'] as $block): ?>
		<div class="half_content"><?= $this->hasSubTemplate($block) ? $this->subTemplate($block) : '' ?></div><?php endforeach; ?>
	</div><!-- #modcenter -->