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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This actually displays the board index
 */
?>
	<div id="boardindex_table" class="boardindex_table"><?php
/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
?><?php foreach (Utils::$context['categories'] as $category): ?><?php /* If there's no parent boards we can see, avoid showing an empty category (unless its collapsed) */ ?><?php if (empty($category['boards']) && !$category['is_collapsed']): ?><?php continue; ?><?php endif; ?>
		<div class="main_container">
			<div class="cat_bar <?= $category['is_collapsed'] ? 'collapsed' : '' ?>" id="category_<?= $category['id'] ?>">
				<h3 class="catbg">
					<?= $category['link'] ?>
				</h3><?php /* If this category even can collapse, show a link to collapse it. */ ?><?php if ($category['can_collapse']): ?>
				<span id="category_<?= $category['id'] ?>_upshrink" class="<?= $category['is_collapsed'] ? 'toggle_down' : 'toggle_up' ?>" data-collapsed="<?= (int) $category['is_collapsed'] ?>" title="<?= Lang::getTxt(!$category['is_collapsed'] ? 'hide_category' : 'show_category', file: 'General') ?>" style="display: none;"></span><?php endif; ?><?= !empty($category['description']) ? '
				<div class="desc">' . $category['description'] . '</div>' : '' ?>
			</div>
			<div id="category_<?= $category['id'] ?>_boards" <?= (!empty($category['css_class']) ? ('class="' . $category['css_class'] . '"') : '') ?><?= $category['is_collapsed'] ? ' style="display: none;"' : '' ?>><?php $this->subTemplate('bi_board_list', ['boards' => $category['boards']]); ?>
			</div><!-- #category_[id]_boards -->
		</div><!-- .main_container --><?php endforeach; ?>
	</div><!-- #boardindex_table --><?php /* Show the mark all as read button? */ ?><?php if (!User::$me->is_guest && !empty(Utils::$context['categories'])): ?>
	<div class="mark_read">
		<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['mark_read_button'], 'direction' => 'right']); ?>
	</div><?php endif; ?>
