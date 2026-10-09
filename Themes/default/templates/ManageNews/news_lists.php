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
 * The settings page.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
			<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?><?php $this->subTemplate('show_list', ['list_id' => 'news_lists']); ?>
