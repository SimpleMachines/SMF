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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Outputs a list of boards, each one drawn by the template_bi_* helpers below.
 *
 * The board index calls this once per category, and the message index calls it
 * for the child boards of the board being viewed. Both used to carry their own
 * copy of this loop.
 *
 * Each board has: new (is it new?), id, name, description, moderators (see
 * below), link_moderators (just a list.), children (see below.), link_children
 * (easier to use.), children_new (are they new?), topics (# of), posts (# of),
 * link, href, and last_post. (see below.)
 *
 * @param array $boards The boards to draw, in the order they go in.
 */
?><?php foreach ($boards as $board): ?>
				<div id="board_<?= $board['id'] ?>" class="up_contain <?= (!empty($board['css_class']) ? $board['css_class'] : '') ?>">
					<div class="board_icon">
						<?= $this->hasSubTemplate('bi_' . $board['type'] . '_icon') ? $this->fetchSubTemplate('bi_' . $board['type'] . '_icon', ['board' => $board]) : $this->fetchSubTemplate('bi_board_icon', ['board' => $board]) ?>
					</div>
					<div class="info">
						<?= $this->hasSubTemplate('bi_' . $board['type'] . '_info') ? $this->fetchSubTemplate('bi_' . $board['type'] . '_info', ['board' => $board]) : $this->fetchSubTemplate('bi_board_info', ['board' => $board]) ?>
					</div><!-- .info --><?php /* Show some basic information about the number of posts, etc. */ ?>
					<div class="board_stats">
						<?= $this->hasSubTemplate('bi_' . $board['type'] . '_stats') ? $this->fetchSubTemplate('bi_' . $board['type'] . '_stats', ['board' => $board]) : $this->fetchSubTemplate('bi_board_stats', ['board' => $board]) ?>
					</div><?php /* Show the last post if there is one. */ ?>
					<div class="lastpost">
						<?= $this->hasSubTemplate('bi_' . $board['type'] . '_lastpost') ? $this->fetchSubTemplate('bi_' . $board['type'] . '_lastpost', ['board' => $board]) : $this->fetchSubTemplate('bi_board_lastpost', ['board' => $board]) ?>
					</div><?php /* Won't somebody think of the children! */ ?><?php if ($this->hasSubTemplate('bi_' . $board['type'] . '_children')): ?><?php $this->subTemplate('bi_' . $board['type'] . '_children', ['board' => $board]); ?><?php else: ?><?php $this->subTemplate('bi_board_children', ['board' => $board]); ?><?php endif; ?>
				</div><!-- #board_[id] --><?php endforeach; ?>
