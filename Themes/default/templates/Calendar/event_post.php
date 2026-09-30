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
 * Template for posting a calendar event.
 */
?>

		<form action="<?= Config::$scripturl ?>?action=calendar;sa=post" method="post" name="postevent" accept-charset="UTF-8" onsubmit="submitonce(this);">
<?php if (!empty(Utils::$context['event']->new)): ?>
			<input type="hidden" name="eventid" value="<?= Utils::$context['event']->id ?>">
			<input type="hidden" name="recurrenceid" value="<?= Utils::$context['event']->selected_occurrence->id ?>">
<?php endif; ?>
<?php /* Start the main table. */ ?>
			<div id="post_event">
				<div class="cat_bar">
					<h3 class="catbg">
						<?= Utils::$context['page_title'] ?>

					</h3>
				</div>
<?php if (!empty(Utils::$context['post_error']['messages'])): ?>
				<div class="errorbox">
					<dl class="event_error">
						<dt>
							<?= Utils::$context['error_type'] == 'serious' ? '<strong>' . Lang::getTxt('error_while_submitting', file: 'General') . '</strong>' : '' ?>

						</dt>
						<dt class="error">
							<?= implode('<br>', Utils::$context['post_error']['messages']) ?>

						</dt>
					</dl>
				</div>
<?php endif; ?>
				<div class="roundframe noup"><?php $this->subTemplate('event_options'); ?>

					<div class="buttonlist">
						<input type="submit" value="<?= Lang::getTxt(empty(Utils::$context['event']->new) ? 'save' : 'post', file: 'General') ?>" class="button floatright">
<?php if (!Utils::$context['event']->new): ?>
						<input type="submit" name="deleteevent" value="<?= Lang::getTxt('calendar_repeat_delete_label', file: 'Calendar') ?>" class="button floatright you_sure" data-confirm="<?= Lang::getTxt(Utils::$context['event']->selected_occurrence->is_first ? 'calendar_confirm_delete' : 'calendar_confirm_occurrence_delete', file: 'Calendar') ?>">
<?php if (!Utils::$context['event']->selected_occurrence->is_first): ?>
						<a href="<?= Utils::$context['event']->modify_href ?>" class="button floatright"><?= Lang::getTxt('calendar_repeat_adjustment_edit_first', file: 'Calendar') ?></a>
<?php endif; ?>
<?php endif; ?>
					</div>
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				</div><!-- .roundframe -->
			</div><!-- #post_event -->
		</form>