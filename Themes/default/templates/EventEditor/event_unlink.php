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
 * Template for unlinking a topic and an event.
 */
?><?php if (empty(Utils::$context['event']->topic) || empty(Config::$modSettings['cal_allow_unlinked'])): ?><?php return; ?><?php endif; ?>
					<dl>
						<dt class="clear">
							<?= Lang::getTxt('calendar_unlink', file: 'Calendar') ?>
						</dt>
						<dd>
							<input type="checkbox" name="unlink">
						</dd>
					</dl>
					<hr class="clear">