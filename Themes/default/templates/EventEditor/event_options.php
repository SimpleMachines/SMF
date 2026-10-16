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

use SMF\Calendar\Event;
use SMF\Lang;
use SMF\Topic;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for the event options fieldset that is used when creating an event.
 *
 * Used by Calendar.template.php and Post.template.php
 */
?>
				<fieldset id="event_options">
					<legend><?= Lang::getTxt('calendar_event_options', file: 'Calendar') ?></legend>
					<input type="hidden" name="calendar" value="1">
					<input type="hidden" name="eventid" value="<?= Utils::$context['event']->id ?>">
					<input type="hidden" name="recurrenceid" value="<?= Utils::$context['event']->selected_occurrence->id ?>"><?php /* Allow the user to link events to topics. */ ?><?php
if (
		Utils::$context['event']->type === Event::TYPE_EVENT
		&& (
			Utils::$context['event']->new
			|| Utils::$context['event']->selected_occurrence->is_first
		)
	):
?><?php if (empty(Utils::$context['event']->topic)): ?><?php $this->subTemplate('event_board'); ?><?php elseif (Utils::$context['event']->topic === (Topic::$topic_id ?? NAN)): ?><?php $this->subTemplate('event_unlink'); ?><?php elseif (Utils::$context['event']->new): ?><?php $this->subTemplate('event_link_to'); ?><?php endif; ?><?php endif; ?><?php $this->subTemplate('event_new'); ?>
				</fieldset>