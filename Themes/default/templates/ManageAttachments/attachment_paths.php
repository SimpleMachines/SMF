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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The page that handles managing attachment paths.
 */
?><?php if (!empty(Config::$modSettings['attachment_basedirectories'])): ?><?php $this->subTemplate('show_list', ['list_id' => 'base_paths']); ?><?php endif; ?><?php $this->subTemplate('show_list', ['list_id' => 'attach_paths']); ?>
