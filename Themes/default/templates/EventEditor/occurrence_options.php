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
 * Template used when editing a single occurrence of an event.
 */
?><?php if (Utils::$context['event']->selected_occurrence->can_affect_future): ?>
						<dl id="occurrence_options">
							<dt class="clear">
								<?= Lang::getTxt('calendar_repeat_adjustment_label', file: 'Calendar') ?>
							</dt>
							<dd>
								<label>
									<input type="radio" name="affects_future" value="0" required checked>
									<span><?= Lang::getTxt('calendar_repeat_adjustment_this_only', file: 'Calendar') ?></span>
								</label>
								<br>
								<label>
									<input type="radio" name="affects_future" value="1" required class="you_sure" data-confirm="<?= Lang::getTxt('calendar_repeat_adjustment_confirm', file: 'Calendar') ?>">
									<span><?= Lang::getTxt('calendar_repeat_adjustment_this_and_future', file: 'Calendar') ?></span>
								</label>
							</dd>
						</dl><?php endif; ?>
