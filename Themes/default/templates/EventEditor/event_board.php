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
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for choosing a board to create a linked topic in.
 */
?><?php /* If user cannot create new events, skip this. */ ?><?php if (!User::$me->allowedTo('calendar_post')): ?><?php return; ?><?php endif; ?>
					<dl id="topic_link_to">
						<dt class="clear">
							<?= Lang::getTxt('calendar_post_in', file: 'Calendar') ?>
						</dt>
						<dd><?php if (!empty(Config::$modSettings['cal_allow_unlinked'])): ?>
							<label>
								<input type="radio" id="link_to_none" name="link_to" value="none"<?= (empty(Utils::$context['event']->topic) ? ' checked' : '') ?>>
								<?= Lang::getTxt('calendar_only', file: 'Calendar') ?>
							</label><?php endif; ?><?php if (!empty(Utils::$context['event']->categories)): ?>
							<label>
								<input type="radio" id="link_to_board" name="link_to" value="board"<?= (empty(Utils::$context['event']->topic) && empty(Config::$modSettings['cal_allow_unlinked']) ? ' checked' : '') ?>>
								<?= Lang::getTxt('new_topic', file: 'General') ?>
							</label><?php endif; ?>
							<label>
								<input type="radio" id="link_to_topic" name="link_to" value="topic"<?= (!empty(Utils::$context['event']->topic) ? ' checked' : '') ?>>
								<?= Lang::getTxt('calendar_topic_existing', file: 'Calendar') ?>
							</label>
						</dd>
					</dl><?php if (!empty(Utils::$context['event']->categories)): ?>
					<dl id="event_board">
						<dt class="clear">
							<label><?= Lang::getTxt('board_name', file: 'General') ?></label>
						</dt>
						<dd>
							<select name="board"<?= empty(Utils::$context['event']->board) ? ' disabled' : '' ?>><?php foreach (Utils::$context['event']->categories as $category): ?>
								<optgroup label="<?= $category['name'] ?>"><?php foreach ($category['boards'] as $board): ?>
									<option value="<?= $board['id'] ?>"<?= $board['id'] == Utils::$context['event']->board ? ' selected' : '' ?>><?= $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '' ?> <?= $board['name'] ?></option><?php endforeach; ?>
								</optgroup><?php endforeach; ?>
							</select>
						</dd>
					</dl><?php endif; ?>
					<dl id="event_topic">
						<dt class="clear">
							<label><?= Lang::getTxt('calendar_link_topic_id', file: 'Calendar') ?></label>
						</dt>
						<dd>
							<input type="text" name="topic" value="<?= (!empty(Utils::$context['event']->topic) ? Utils::$context['event']->topic : '') ?>">
						</dd>
					</dl>
					<hr class="clear">