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
use SMF\Profile;
use SMF\Theme;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for allowing the member to choose their time zone.
 */
?><?php
// Just in case...
Theme::loadTemplate('TimeZoneSelect');
?>
							<dt>
								<strong>
									<label for="timezone"><?= Lang::getTxt('timezone', file: 'Profile') ?></label>
								</strong>
							</dt>
							<dd><?php /* This part is easy enough. */ ?><?php $this->subTemplate('timezone_select', ['name' => 'timezone', 'selection' => Profile::$member->timezone]); ?>
							</dd>