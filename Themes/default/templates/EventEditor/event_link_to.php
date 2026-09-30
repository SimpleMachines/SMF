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
use SMF\User;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for linking an existing topic to an event.
 */
?><?php /* If user cannot edit existing events, we don't need to bother showing these options at all. */ ?><?php if (!User::$me->allowedTo('calendar_edit_any') && !User::$me->allowedTo('calendar_edit_own')): ?><?php return; ?><?php endif; ?><?php /* If user can both create and edit events, show both options. */ ?><?php if (User::$me->allowedTo('calendar_post')): ?>
					<dl id="event_link_to">
						<dt class="clear">
							<?= Lang::getTxt('calendar_link_to', file: 'Calendar') ?>
						</dt>
						<dd>
							<label>
								<input type="radio" id="event_link_to_new" name="event_link_to" value="new" checked>
								<span><?= Lang::getTxt('calendar_event_new', file: 'Calendar') ?></span>
							</label>
							<label>
								<input type="radio" name="event_link_to" value="existing">
								<span><?= Lang::getTxt('calendar_event_existing', file: 'Calendar') ?></span>
							</label>
						</dd>
					</dl><?php endif; ?><?php /* Let the user specify an existing event to link to. */ ?>
					<dl id="event_id_to_link">
						<dt class="clear">
							<?= Lang::getTxt('calendar_link_event_id', file: 'Calendar') ?>
						</dt>
						<dd>
							<input type="text" name="event_id_to_link">
						</dd>
					</dl>